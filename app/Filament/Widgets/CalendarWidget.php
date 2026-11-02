<?php

namespace App\Filament\Widgets;

use App\Enums\CandidateStatus;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Candidate;
use Saade\FilamentFullCalendar\Data\FetchInfo;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    /**
     * @return array<int, array{title: string, start: mixed, end: mixed, url: string, shouldOpenUrlInNewTab: bool}>
     */
    public function fetchEvents(FetchInfo $info): array
    {
        return $info->overlapping(Candidate::query(), 'from', 'to')
            ->with('position')
            ->whereIn('status', [
                CandidateStatus::COMPLETED,
                CandidateStatus::HIRED,
                CandidateStatus::INTERVIEW,
                CandidateStatus::OFFER_ACCEPTED,
            ])
            ->whereNotNull(['from', 'to'])
            ->get()
            ->map(
                fn (Candidate $event): array => [
                    'title' => $event->name.' - '.$event->position->title,
                    'start' => $event->from,
                    'end' => $event->to->endOfDay(),
                    'url' => CandidateResource::getUrl('view', ['record' => $event]),
                    'shouldOpenUrlInNewTab' => true,
                ]
            )
            ->all();
    }
}
