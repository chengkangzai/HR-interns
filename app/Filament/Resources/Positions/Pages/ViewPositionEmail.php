<?php

namespace App\Filament\Resources\Positions\Pages;

use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use App\Filament\Resources\Emails\EmailResource;
use App\Filament\Resources\Positions\PositionResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;

class ViewPositionEmail extends ManageRelatedRecords
{
    protected static string $resource = PositionResource::class;

    protected static string $relationship = 'emails';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Emails';
    }

    public function form(Schema $schema): Schema
    {
        return EmailResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return EmailResource::table($table)
            ->headerActions([
                CreateAction::make()
                    ->fillForm([
                        'position_id' => $this->record->id,
                    ]),
            ]);
    }
}
