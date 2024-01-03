<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CandidateResource\Pages;
use App\Models\Candidate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->required(),

                TextInput::make('phone_number')
                    ->required(),
            ])->columns(2),

            Section::make([
                DatePicker::make('from'),

                DatePicker::make('to'),
            ])->columns(2),

            Section::make([
                SpatieMediaLibraryFileUpload::make('resume')
                    ->label('Resume')
                    ->acceptedFileTypes(['application/pdf']),

                SpatieMediaLibraryFileUpload::make('documents')
                    ->label('Other Documents')
                    ->acceptedFileTypes(['application/pdf']),
            ])->columns(2),

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
        return $table->columns([
            TextColumn::make('name')
                ->searchable()
                ->sortable(),

            TextColumn::make('email')
                ->searchable()
                ->sortable(),

            TextColumn::make('phone_number'),

            TextColumn::make('from')
                ->date(),

            TextColumn::make('to')
                ->date(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCandidates::route('/'),
            'create' => Pages\CreateCandidate::route('/create'),
            'edit' => Pages\EditCandidate::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }
}
