<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Filament\Resources\CandidateResource;
use App\Filament\Resources\PositionResource;
use Filament\Forms\Form;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;

class ViewPositionCandidate extends ManageRelatedRecords
{
    protected static string $resource = PositionResource::class;

    protected static string $relationship = 'candidates';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected ?string $maxContentWidth = 'full';

    public static function getNavigationLabel(): string
    {
        return 'Candidates';
    }

    public function form(Form $form): Form
    {
        return CandidateResource::form($form);
    }

    public function table(Table $table): Table
    {
        return CandidateResource::table($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
