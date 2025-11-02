<?php

namespace App\Filament\Widgets;

use App\Enums\CandidateStatus;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Candidate;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    public function fetchEvents(array $info): array
    {
        return Candidate::query()
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
                fn (Candidate $event) => [
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
