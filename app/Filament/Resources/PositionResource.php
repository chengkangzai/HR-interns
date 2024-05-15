<?php

namespace App\Filament\Resources;

use App\Enums\PositionStatus;
use App\Filament\Resources\PositionResource\Pages;
use App\Models\Position;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static ?string $slug = 'positions';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')
                ->required(),

            Select::make('status')
                ->options(PositionStatus::class)
                ->default(PositionStatus::OPEN)
                ->required(),

            TextInput::make('indeed_url')
                ->url()
                ->suffixAction(
                    Action::make('View')
                        ->url(fn (?string $state) => $state, true)
                        ->icon('heroicon-o-arrow-top-right-on-square')
                )
                ->columnSpanFull(),

            Section::make('Description')
                ->description('Provide a detailed description of the position that will be displayed on the public job board.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    RichEditor::make('description')
                        ->columnSpanFull(),
                ]),

            Section::make([
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn (?Position $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn (?Position $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')
                ->searchable()
                ->sortable(),

            TextColumn::make('description')
                ->toggleable(isToggledHiddenByDefault: true)
                ->limit(50),

            TextColumn::make('status')
                ->badge(),
        ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPositions::route('/'),
            'create' => Pages\CreatePosition::route('/create'),
            'view' => Pages\ViewPosition::route('/{record}'),
            'edit' => Pages\EditPosition::route('/{record}/edit'),
            'candidates' => Pages\ViewPositionCandidate::route('/{record}/candidates'),
            'emails' => Pages\ViewPositionEmail::route('/{record}/emails'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\ViewPosition::class,
            Pages\ViewPositionCandidate::class,
            Pages\ViewPositionEmail::class,
        ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }
}
