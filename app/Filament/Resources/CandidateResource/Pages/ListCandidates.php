<?php

namespace App\Filament\Resources\CandidateResource\Pages;

use App\Enums\CandidateStatus;
use App\Filament\Resources\CandidateResource;
use App\Models\Candidate;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCandidates extends ListRecords
{
    protected static string $resource = CandidateResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            null => Tab::make('All')
                ->badge(Candidate::count()),
            'pending' => Tab::make('Pending')
                ->badgeColor(CandidateStatus::PENDING->getColor())
                ->badge(Candidate::where('status', CandidateStatus::PENDING)->count())
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::PENDING)),
            'technical_test' => Tab::make('Technical Test')
                ->badgeColor(CandidateStatus::TECHNICAL_TEST->getColor())
                ->badge(Candidate::where('status', CandidateStatus::TECHNICAL_TEST)->count())
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::TECHNICAL_TEST)),
            'interview' => Tab::make('Interview')
                ->badgeColor(CandidateStatus::INTERVIEW->getColor())
                ->badge(Candidate::where('status', CandidateStatus::INTERVIEW)->count())
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::INTERVIEW)),
            'withdrawn' => Tab::make('Withdrawn')
                ->badgeColor(CandidateStatus::WITHDRAWN->getColor())
                ->badge(Candidate::where('status', CandidateStatus::WITHDRAWN)->count())
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::WITHDRAWN)),
            'hired' => Tab::make('Hired')
                ->badgeColor(CandidateStatus::HIRED->getColor())
                ->badge(Candidate::where('status', CandidateStatus::HIRED)->count())
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::HIRED)),
            'offer_accepted' => Tab::make('Offer Accepted')
                ->badgeColor(CandidateStatus::OFFER_ACCEPTED->getColor())
                ->badge(Candidate::where('status', CandidateStatus::OFFER_ACCEPTED)->count())
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::OFFER_ACCEPTED)),
            'completed' => Tab::make('Completed')
                ->badgeColor(CandidateStatus::COMPLETED->getColor())
                ->badge(Candidate::where('status', CandidateStatus::COMPLETED)->count())
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::COMPLETED)),
        ];
    }
}
