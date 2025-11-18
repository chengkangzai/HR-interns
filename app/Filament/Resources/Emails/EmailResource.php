<?php

namespace App\Filament\Resources\Emails;

use App\Filament\Resources\Emails\Pages\CreateEmail;
use App\Filament\Resources\Emails\Pages\EditEmail;
use App\Filament\Resources\Emails\Pages\ListEmails;
use App\Filament\Resources\Positions\Pages\ViewPositionEmail;
use App\Filament\Resources\Positions\PositionResource;
use App\Models\Email;
use App\Models\Position;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EmailResource extends Resource
{
    protected static ?string $model = Email::class;

    protected static ?string $slug = 'emails';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required(),

            TextInput::make('title')
                ->required(),

            TagsInput::make('cc')
                ->nestedRecursiveRules('email')
                ->validationMessages([
                    '*.email' => 'The email #:position must be a valid email address.',
                ])
                ->placeholder('Enter email addresses'),

            Select::make('position_id')
                ->relationship('position', 'title')
                ->suffixAction(function (string $context, ?Email $record) {
                    if (! $record) {
                        return null;
                    }
                    if ($context == 'create') {
                        return null;
                    }

                    return Action::make('view_position')
                        ->icon('heroicon-o-eye')
                        ->url(PositionResource::getUrl('view', ['record' => $record->position_id]), true);
                }),

            RichEditor::make('body')
                ->columnSpanFull()
                ->required()
                ->disableToolbarButtons([
                    'orderedList', // disable due to look bad in email
                    'bulletList', // disable due to look bad in email
                    'attachFiles', // disable due to no upload file support
                ]),

            SpatieMediaLibraryFileUpload::make('documents')
                ->preserveFilenames()
                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                ->collection('documents'),

            Placeholder::make('created_at')
                ->label('Created Date')
                ->content(fn (?Email $record): string => $record?->created_at?->diffForHumans() ?? '-'),

            Placeholder::make('updated_at')
                ->label('Last Modified Date')
                ->content(fn (?Email $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')
                ->limit(40)
                ->searchable()
                ->sortable(),

            TextColumn::make('title')
                ->searchable()
                ->limit(40)
                ->sortable(),

            TextColumn::make('position.title')
                ->badge()
                ->hidden(fn ($livewire) => $livewire instanceof ViewPositionEmail || $livewire->tableGrouping == 'position.title'),
        ])
            ->filtersFormColumns(2)
            ->filters([
                TrashedFilter::make()
                    ->columnSpanFull(),
                SelectFilter::make('position_id')
                    ->columnSpanFull()
                    ->searchable()
                    ->preload()
                    ->hidden(fn ($livewire) => $livewire instanceof ViewPositionEmail)
                    ->relationship('openPosition', 'title', function ($query) {
                        $query->whereHas('candidates');
                    })
                    ->label('Position'),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (Email $record) => EmailResource::getUrl('edit', ['record' => $record->id])),
                ReplicateAction::make()
                    ->schema([
                        Select::make('position_id')
                            ->options(function () {
                                return Position::query()
                                    ->get()
                                    ->groupBy('type')
                                    ->map(function ($positions) {
                                        return $positions->pluck('title', 'id');
                                    })
                                    ->toArray();
                            }),
                    ]),
            ])
            ->groups([
                'position.title',
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('sort')
            ->reorderable(function ($livewire) {
                if ($livewire instanceof ViewPositionEmail) {
                    return 'sort';
                }

                return null;
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmails::route('/'),
            'create' => CreateEmail::route('/create'),
            'edit' => EditEmail::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }
}
