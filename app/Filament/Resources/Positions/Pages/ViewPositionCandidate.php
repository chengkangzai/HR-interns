<?php

namespace App\Filament\Resources\Positions\Pages;

use App\Enums\CandidateStatus;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Positions\PositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ViewPositionCandidate extends ManageRelatedRecords
{
    protected static string $resource = PositionResource::class;

    protected static string $relationship = 'candidates';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Candidates';
    }

    public function form(Schema $schema): Schema
    {
        return CandidateResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return CandidateResource::table($table)
            ->defaultGroup('status')
            ->filters([
                SelectFilter::make('status')
                    ->options(CandidateStatus::class)
                    ->default([
                        CandidateStatus::PENDING->value,
                        CandidateStatus::CONTACTED->value,
                        CandidateStatus::TECHNICAL_TEST->value,
                        CandidateStatus::INTERVIEW->value,
                        CandidateStatus::HIRED->value,
                        CandidateStatus::OFFER_ACCEPTED->value,
                    ])
                    ->multiple()
                    ->label('Status'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->fillForm([
                        'position_id' => $this->record->id,
                        'status' => CandidateStatus::PENDING->value,
                    ]),
            ]);
    }
}
