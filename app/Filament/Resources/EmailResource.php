<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailResource\Pages;
use App\Models\Email;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailResource extends Resource
{
    protected static ?string $model = Email::class;

    protected static ?string $slug = 'emails';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required(),

            TextInput::make('title')
                ->required(),

            TagsInput::make('cc')
                ->nestedRecursiveRules('email')
                ->validationMessages([
                    '*.email' => 'The email #:position must be a valid email address.',
                ])
                ->suggestions([
                    'eddiechong@pixalink.io',
                    'cheng.kang@pixalink.io',
                    'deserie@pixalink.io',
                ])
                ->default(['eddiechong@pixalink.io'])
                ->placeholder('Enter email addresses'),

            RichEditor::make('body')
                ->columnSpanFull()
                ->required()
                ->disableToolbarButtons([
                    'orderedList', //disable due to look bad in email
                    'bulletList', //disable due to look bad in email
                    'attachFiles', //disable due to no upload file support
                ]),

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
                ->searchable()
                ->sortable(),

            TextColumn::make('title')
                ->searchable()
                ->limit(50)
                ->sortable(),

            TextColumn::make('body')
                ->limit(50),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmails::route('/'),
            'create' => Pages\CreateEmail::route('/create'),
            'edit' => Pages\EditEmail::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }
}
