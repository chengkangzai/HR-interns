<?php

use App\Enums\CandidateStatus;
use App\Filament\Widgets\CalendarWidget;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
    $this->position = Position::factory()->create(['title' => 'Backend Intern']);
});

it('renders the calendar widget', function () {
    Livewire::test(CalendarWidget::class)->assertSuccessful();
});

it('shows active interns within the visible range', function (CandidateStatus $status) {
    Candidate::factory()->create([
        'name' => 'Ali',
        'status' => $status,
        'position_id' => $this->position->id,
        'from' => now()->subWeek(),
        'to' => now()->addWeek(),
    ]);

    Livewire::test(CalendarWidget::class)
        ->assertCalendarHasEvent('Ali - Backend Intern', now()->startOfMonth(), now()->endOfMonth())
        ->assertCalendarEventCount(1, now()->startOfMonth(), now()->endOfMonth());
})->with([
    CandidateStatus::INTERVIEW,
    CandidateStatus::OFFER_ACCEPTED,
    CandidateStatus::HIRED,
    CandidateStatus::COMPLETED,
]);

it('hides candidates who are not in an active status', function (CandidateStatus $status) {
    Candidate::factory()->create([
        'status' => $status,
        'position_id' => $this->position->id,
        'from' => now()->subWeek(),
        'to' => now()->addWeek(),
    ]);

    Livewire::test(CalendarWidget::class)->assertCalendarEventCount(0);
})->with([
    CandidateStatus::PENDING,
    CandidateStatus::CONTACTED,
    CandidateStatus::TECHNICAL_TEST,
    CandidateStatus::WITHDRAWN,
]);

it('hides candidates outside the visible range or without dates', function () {
    Candidate::factory()->create([
        'name' => 'Last Year',
        'status' => CandidateStatus::COMPLETED,
        'position_id' => $this->position->id,
        'from' => now()->subYears(2),
        'to' => now()->subYears(2)->addMonths(3),
    ]);

    Candidate::factory()->create([
        'name' => 'No Dates',
        'status' => CandidateStatus::HIRED,
        'position_id' => $this->position->id,
        'from' => null,
        'to' => null,
    ]);

    Livewire::test(CalendarWidget::class)
        ->assertCalendarEventCount(0, now()->startOfMonth(), now()->endOfMonth());
});

it('includes an internship that spans the whole visible range', function () {
    Candidate::factory()->create([
        'name' => 'Long Intern',
        'status' => CandidateStatus::HIRED,
        'position_id' => $this->position->id,
        'from' => now()->subMonths(3),
        'to' => now()->addMonths(3),
    ]);

    Livewire::test(CalendarWidget::class)
        ->assertCalendarHasEvent(['title' => 'Long Intern - Backend Intern', 'shouldOpenUrlInNewTab' => true], now()->startOfMonth(), now()->endOfMonth());
});
