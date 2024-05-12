<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Enums\CandidateStatus;
use App\Filament\Resources\CandidateResource;
use App\Filament\Resources\PositionResource;
use Filament\Forms\Form;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ViewPositionCandidate extends ManageRelatedRecords
{
    protected static string $resource = PositionResource::class;

    protected static string $relationship = 'candidates';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
            ->filters([
                SelectFilter::make('status')
                    ->options(CandidateStatus::class)
                    ->default([
                        CandidateStatus::PENDING->value,
                        CandidateStatus::COMPLETED->value,
                        CandidateStatus::TECHNICAL_TEST->value,
                        CandidateStatus::INTERVIEW->value,
                    ])
                    ->multiple()
                    ->label('Status'),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
