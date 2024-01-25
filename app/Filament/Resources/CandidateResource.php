<?php

namespace App\Filament\Resources;

use App\Enums\CandidateStatus;
use App\Enums\PositionStatus;
use App\Filament\Resources\CandidateResource\Pages;
use App\Jobs\SendEmailJob;
use App\Models\Candidate;
use App\Models\Email;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class CandidateResource extends Resource
{
    protected static ?string $model = Candidate::class;

    protected static ?string $slug = 'candidates';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make([
                TextInput::make('name')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, Set $set) => $set('name', str($state)->title()->__toString()))
                    ->required(),

                TextInput::make('email')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $state, Set $set) {
                        $set('email', str($state)->remove(' ')->remove('`'));
                    })
                    ->suffixAction(fn (?string $state) => FormAction::make('Email')
                        ->icon('heroicon-o-envelope-open')
                        ->tooltip('Send Email')
                        ->url('mailto:'.$state, true)
                    )
                    ->required(),

                PhoneInput::make('phone_number')
                    ->suffixAction(fn (?string $state) => FormAction::make('WhatsApp')
                        ->icon('heroicon-o-phone-arrow-up-right')
                        ->tooltip('Send WhatsApp Message')
                        ->url('https://wa.me/'.str_replace(['+', ' ', '(', ')', '-'], '', $state), true)
                    )
                    ->formatOnDisplay(true)
                    ->required(),
            ]),

            Section::make([
                Select::make('position_id')
                    ->suffixAction(function (string $context, ?Candidate $record) {
                        if (! $record) {
                            return null;
                        }
                        if ($context == 'create') {
                            return null;
                        }

                        return FormAction::make('view_position')
                            ->icon('heroicon-o-eye')
                            ->url(PositionResource::getUrl('view', ['record' => $record->position_id]), true);
                    })
                    ->relationship('position', 'title', fn (Builder $query) => $query->where('status', PositionStatus::OPEN))
                    ->createOptionForm([
                        TextInput::make('title')
                            ->required(),
                    ])
                    ->required(),

                Select::make('status')
                    ->options(CandidateStatus::class)
                    ->default(CandidateStatus::PENDING)
                    ->required(),
            ]),

            Section::make([
                DatePicker::make('from'),

                DatePicker::make('to'),

                Placeholder::make('range')
                    ->label('From - To')
                    ->visibleOn(['view', 'edit'])
                    ->content(fn (?Candidate $record): string => isset($record->from, $record->to) ? ceil($record->from->floatDiffInWeeks($record->to)).' weeks' : 'N/A'),
            ]),

            Section::make([
                SpatieMediaLibraryFileUpload::make('resume')
                    ->label('Resume')
                    ->collection('resumes')
                    ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']),

                SpatieMediaLibraryFileUpload::make('offer_letter')
                    ->multiple()
                    ->label('Offer Letter')
                    ->collection('offer_letters')
                    ->acceptedFileTypes(['application/pdf']),

                SpatieMediaLibraryFileUpload::make('documents')
                    ->label('Other Documents')
                    ->collection('other_documents')
                    ->multiple(),
            ]),

            RichEditor::make('notes')
                ->columnSpanFull(),

            Placeholder::make('created_at')
                ->label('Created Date')
                ->content(fn (?Candidate $record): string => $record?->created_at?->diffForHumans() ?? '-'),

            Placeholder::make('updated_at')
                ->label('Last Modified Date')
                ->content(fn (?Candidate $record): string => $record?->updated_at?->diffForHumans() ?? '-'),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->url(fn (Candidate $record) => 'mailto:'.$record->email, true)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_number')
                    ->url(fn (Candidate $record) => 'https://wa.me/'.str_replace(['+', ' ', '(', ')', '-'], '', $record->phone_number), true)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->fontFamily(FontFamily::Mono),

                TextColumn::make('range')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('from', $direction))
                    ->label('From - To')
                    ->getStateUsing(fn (Candidate $record) => isset($record->from, $record->to)
                        ? $record->from->format('d/m/Y').' - '.$record->to->format('d/m/Y').' ('.ceil($record->from->floatDiffInWeeks($record->to)).' weeks)'
                        : 'N/A'
                    ),

                TextColumn::make('position.title'),

                TextColumn::make('from')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->date(),

                TextColumn::make('to')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->date(),

                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CandidateStatus::class)
                    ->multiple()
                    ->label('Status'),

                SelectFilter::make('position_id')
                    ->searchable()
                    ->preload()
                    ->relationship('position', 'title')
                    ->label('Position'),
            ])
            ->actions([
                Action::make('status')
                    ->color(Color::Blue)
                    ->icon('heroicon-s-check-circle')
                    ->form([
                        Select::make('status')
                            ->options(CandidateStatus::class),
                    ])
                    ->action(fn (Candidate $record, array $data) => $record->update($data)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
                BulkAction::make('send_email')
                    ->form([
                        Select::make('email')
                            ->options(fn () => Email::pluck('name', 'id')),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $email = Email::find($data['email']);
                        $records->each(function (Candidate $record, $index) use ($email) {
                            SendEmailJob::dispatch($email, $record)->delay(now()->addSeconds($index * 30));
                        });

                        Notification::make()
                            ->success()
                            ->title('Email Sent')
                            ->body('Email has been sent to '.$records->count().' candidate(s). <br>'.
                                'ETA: <b>'.now()->addSeconds($records->count() * 30)->shortRelativeDiffForHumans().'</b>'
                            )
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(true),
                BulkAction::make('change_status')
                    ->form([
                        Select::make('status')
                            ->options(CandidateStatus::class),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $records->each(fn (Candidate $record) => $record->update([
                            'status' => $data['status'],
                        ]));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCandidates::route('/'),
            'create' => Pages\CreateCandidate::route('/create'),
            'view' => Pages\ViewCandidate::route('/{record}/'),
            'edit' => Pages\EditCandidate::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }
}
