<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Filament\Resources\EmailResource;
use App\Filament\Resources\PositionResource;
use Filament\Forms\Form;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;

class ViewPositionEmail extends ManageRelatedRecords
{
    protected static string $resource = PositionResource::class;

    protected static string $relationship = 'emails';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Emails';
    }

    public function form(Form $form): Form
    {
        return EmailResource::form($form);
    }

    public function table(Table $table): Table
    {
        return EmailResource::table($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
