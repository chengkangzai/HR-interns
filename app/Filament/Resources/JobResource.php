<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JobResource\Pages;
use App\Models\Job;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JobResource extends Resource
{
    protected static ?string $model = Job::class;

    protected static ?string $slug = 'jobs';

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')
                ->required(),

            RichEditor::make('description'),

            TextInput::make('status')
                ->required(),

            Placeholder::make('created_at')
                ->label('Created Date')
                ->content(fn(?Job $record): string => $record?->created_at?->diffForHumans() ?? '-'),

            Placeholder::make('updated_at')
                ->label('Last Modified Date')
                ->content(fn(?Job $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')
                ->searchable()
                ->sortable(),

            TextColumn::make('description'),

            TextColumn::make('status'),
        ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobs::route('/'),
            'create' => Pages\CreateJob::route('/create'),
            'view' => Pages\ViewJob::route('/{record}/'),
            'edit' => Pages\EditJob::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }
}
