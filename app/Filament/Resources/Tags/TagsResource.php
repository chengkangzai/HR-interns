<?php

namespace App\Filament\Resources\Tags;

use Filament\Schemas\Schema;
use DB;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Filament\Resources\TagsResource\Pages;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Tags\Tag;

class TagsResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $slug = 'tags';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name.en')
                    ->label('Name'),

                TextInput::make('type')
                    ->label('Type'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->placeholder('default')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('Usage Count')
                    ->sortable()
                    ->getStateUsing(fn (Tag $record) => DB::table('taggables')
                        ->where('tag_id', $record->getKey())
                        ->count()),
            ])
            ->filters([
                SelectFilter::make('type'),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTags::route('/'),
        ];
    }
}
