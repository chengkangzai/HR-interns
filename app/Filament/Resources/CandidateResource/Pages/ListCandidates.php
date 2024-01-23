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
        $statusCounts = Candidate::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            null => Tab::make('All')
                ->query(fn (Builder $query) => $query->whereNotIn('status', [
                    CandidateStatus::COMPLETED,
                    CandidateStatus::WITHDRAWN,
                    CandidateStatus::NO_RESPONSE,
                ]))
                ->badge(
                    $statusCounts
                        ->reject(fn ($_, $status) => $status === CandidateStatus::COMPLETED->value)
                        ->reject(fn ($_, $status) => $status === CandidateStatus::WITHDRAWN->value)
                        ->reject(fn ($_, $status) => $status === CandidateStatus::NO_RESPONSE->value)
                        ->sum()
                ),
            'pending' => Tab::make('Pending')
                ->badgeColor(CandidateStatus::PENDING->getColor())
                ->badge($statusCounts[CandidateStatus::PENDING->value] ?? 0)
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::PENDING)),
            'contacted' => Tab::make('Contacted')
                ->badgeColor(CandidateStatus::CONTACTED->getColor())
                ->badge($statusCounts[CandidateStatus::CONTACTED->value] ?? 0)
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::CONTACTED)),
            'no_response' => Tab::make('No Response')
                ->badgeColor(CandidateStatus::NO_RESPONSE->getColor())
                ->badge($statusCounts[CandidateStatus::NO_RESPONSE->value] ?? 0)
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::NO_RESPONSE)),
            'technical_test' => Tab::make('Technical Test')
                ->badgeColor(CandidateStatus::TECHNICAL_TEST->getColor())
                ->badge($statusCounts[CandidateStatus::TECHNICAL_TEST->value] ?? 0)
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::TECHNICAL_TEST)),
            'interview' => Tab::make('Interview')
                ->badgeColor(CandidateStatus::INTERVIEW->getColor())
                ->badge($statusCounts[CandidateStatus::INTERVIEW->value] ?? 0)
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::INTERVIEW)),
            'withdrawn' => Tab::make('Withdrawn')
                ->badgeColor(CandidateStatus::WITHDRAWN->getColor())
                ->badge($statusCounts[CandidateStatus::WITHDRAWN->value] ?? 0)
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::WITHDRAWN)),
            'hired' => Tab::make('Hired')
                ->badgeColor(CandidateStatus::HIRED->getColor())
                ->badge($statusCounts[CandidateStatus::HIRED->value] ?? 0)
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::HIRED)),
            'offer_accepted' => Tab::make('Offer Accepted')
                ->badgeColor(CandidateStatus::OFFER_ACCEPTED->getColor())
                ->badge($statusCounts[CandidateStatus::OFFER_ACCEPTED->value] ?? 0)
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::OFFER_ACCEPTED)),
            'completed' => Tab::make('Completed')
                ->badgeColor(CandidateStatus::COMPLETED->getColor())
                ->badge($statusCounts[CandidateStatus::COMPLETED->value] ?? 0)
                ->query(fn (Builder $query) => $query->where('status', CandidateStatus::COMPLETED)),

        ];
    }
}
