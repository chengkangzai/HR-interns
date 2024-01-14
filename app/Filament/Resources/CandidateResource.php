<?php

namespace App\Filament\Resources;

use App\Enums\CandidateStatus;
use App\Enums\JobStatus;
use App\Filament\Resources\CandidateResource\Pages;
use App\Jobs\SendEmailJob;
use App\Mail\DefaultMail;
use App\Models\Candidate;
use App\Models\Email;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
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
use Illuminate\Support\Facades\Mail;
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
                    ->required(),

                TextInput::make('email')
                    ->reactive()
                    ->afterStateUpdated(function (string $state, Set $set) {
                        $set('email', str($state)->remove(' ')->remove('`'));
                    })
                    ->required(),

                PhoneInput::make('phone_number')
                    ->required(),
            ]),

            Section::make([
                Select::make('job_id')
                    ->suffixAction(fn(string $context, ?Candidate $record) => $context == 'create' || is_null($record->job) ? null : JobResource::getUrl('view', ['record' => $record->job]))
                    ->relationship('job', 'title', fn(Builder $query) => $query->where('status', JobStatus::OPEN))
                    ->createOptionForm([
                        TextInput::make('title')
                            ->required(),
                    ])
                    ->required(),

                Select::make('status')
                    ->options(CandidateStatus::class)
                    ->required(),
            ]),

            Section::make([
                DatePicker::make('from'),

                DatePicker::make('to'),
            ]),

            Section::make([
                SpatieMediaLibraryFileUpload::make('resume')
                    ->label('Resume')
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
                ->content(fn(?Candidate $record): string => $record?->created_at?->diffForHumans() ?? '-'),

            Placeholder::make('updated_at')
                ->label('Last Modified Date')
                ->content(fn(?Candidate $record): string => $record?->updated_at?->diffForHumans() ?? '-'),

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
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_number')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->fontFamily(FontFamily::Mono),

                TextColumn::make('range')
                    ->label('From - To')
                    ->getStateUsing(fn(Candidate $record) => isset($record->from, $record->to)
                        ? $record->from->format('d/m/Y') . ' - ' . $record->to->format('d/m/Y')
                        : 'N/A'
                    ),

                TextColumn::make('from')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->date(),

                TextColumn::make('to')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->date(),

                TextColumn::make('status')
                    ->badge()
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CandidateStatus::class)
                    ->label('Status')
            ])
            ->actions([
                Action::make('status')
                    ->color(Color::Blue)
                    ->icon('heroicon-s-check-circle')
                    ->form([
                        Select::make('status')
                            ->options(CandidateStatus::class)
                    ])
                    ->action(fn(Candidate $record, array $data) => $record->update($data)),
                EditAction::make(),
                DeleteAction::make()
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
                BulkAction::make('send_email')
                    ->form([
                        Select::make('email')
                            ->options(fn() => Email::pluck('name', 'id'))
                    ])
                    ->action(function (Collection $records, array $data) {
                        $email = Email::find($data['email']);
                        $records->each(function (Candidate $record) use ($email) {
                            SendEmailJob::dispatch($email, $record);
                        });
                    }),
                BulkAction::make('change_status')
                    ->form([
                        Select::make('status')
                            ->options(CandidateStatus::class)
                    ])
                    ->action(function (Collection $records, array $data) {
                        $records->each(fn(Candidate $record) => $record->update([
                            'status' => $data['status']
                        ]));
                    })
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
