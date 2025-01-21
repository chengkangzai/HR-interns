<?php

namespace App\Filament\Resources;

use App\Enums\PositionStatus;
use App\Enums\PositionType;
use App\Enums\PositionUrlSource;
use App\Filament\Resources\PositionResource\Pages;
use App\Models\Position;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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

            Select::make('type')
                ->options(PositionType::class)
                ->required(),

            Forms\Components\Repeater::make('urls')
                ->schema([
                    Select::make('source')
                        ->required()
                        ->options(PositionUrlSource::class),

                    TextInput::make('url')
                        ->suffixAction(fn (?string $state) => $state
                            ? Forms\Components\Actions\Action::make('view')
                                ->label('View')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url($state, true)
                            : null
                        )
                        ->reactive()
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            if (! $state) {
                                $set('source', null);

                                return;
                            }

                            $source = match (true) {
                                str_contains(strtolower($state), 'indeed.com') => PositionUrlSource::INDEED,
                                str_contains(strtolower($state), 'linkedin.com') => PositionUrlSource::LINKED_IN,
                                str_contains(strtolower($state), 'developerkaki.my') => PositionUrlSource::DEV_KAKI,
                                str_contains(strtolower($state), 'sunway-csm') => PositionUrlSource::SUNWAY_PORTAL,
                                default => null
                            };

                            $set('source', $source);
                        })
                        ->url()
                        ->required(),
                ]),

            Section::make('Description')
                ->description('Provide a detailed description of the position that will be displayed on the public job board.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    RichEditor::make('description')
                        ->columnSpanFull(),
                ]),

            Forms\Components\SpatieMediaLibraryFileUpload::make('documents')
                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                ->collection('documents'),

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
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(50),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('candidates_count')
                    ->counts('candidates')
                    ->sortable(),

                TextColumn::make('urls_count')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric()
                    ->getStateUsing(fn (?Position $record) => count($record->urls ?? [])),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->default(PositionStatus::OPEN->value)
                    ->options(PositionStatus::class)
                    ->label('Status'),

                SelectFilter::make('type')
                    ->options(PositionType::class)
                    ->multiple()
                    ->label('Type'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                Action::make('view_external')
                    ->form([
                        Select::make('url')
                            ->options(function (Position $record) {
                                $option = [];
                                foreach ($record->urls ?? [] as $url) {
                                    $urlParts = parse_url($url['url']);

                                    $part = str(
                                        $urlParts['path'].
                                        (isset($urlParts['query']) ? '?'.$urlParts['query'] : '')
                                    )->limit(50);

                                    $option[$url['url']] = PositionUrlSource::from($url['source'])->getLabel().': '.$part;
                                }

                                return $option;
                            }),
                    ])
                    ->action(function (array $data) {
                        return redirect($data['url']);
                    })
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(function (?Position $record) {
                        if (count($record?->urls ?? []) == 1) {
                            return $record->urls[0]['url'];
                        }

                        return null;
                    }, true)
                    ->visible(fn (?Position $record): bool => count($record->urls ?? []) >= 1),
            ])
            ->defaultGroup('type');
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
