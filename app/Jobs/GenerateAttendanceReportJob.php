<?php

namespace App\Jobs;

use App\Models\Candidate;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use CCK\LaravelOfficeHolidays\Enums\HolidayType;
use CCK\LaravelOfficeHolidays\Enums\MalaysiaStates;
use CCK\LaravelOfficeHolidays\LaravelOfficeHolidays;
use CCK\LaravelOfficeHolidays\Saloon\Dto\HolidayDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class GenerateAttendanceReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Candidate $candidate,
    ) {
    }

    public function handle(): void
    {
        $period = CarbonPeriod::create($this->candidate->from->startOfMonth(), $this->candidate->to->endOfMonth());

        $holidays = (new LaravelOfficeHolidays())
            ->getHolidaysByState('malaysia', $period->start->year, MalaysiaStates::KualaLumpur->value)
            ->filter(fn (HolidayDto $holiday) => $holiday->type == HolidayType::REGIONAL_HOLIDAY || $holiday->type == HolidayType::NATIONAL_HOLIDAY)
            ->map(fn (HolidayDto $holiday) => $holiday->date->toDateString());

        if ($period->start->year !== $period->end->year) {
            $holidays = $holidays->merge(
                (new LaravelOfficeHolidays())
                    ->getHolidaysByState('malaysia', $period->end->year, MalaysiaStates::KualaLumpur->value)
                    ->filter(fn (HolidayDto $holiday) => $holiday->type == HolidayType::REGIONAL_HOLIDAY || $holiday->type == HolidayType::NATIONAL_HOLIDAY)
                    ->map(fn (HolidayDto $holiday) => $holiday->date->toDateString())
            );
        }

        $attendances = collect($period)
            ->map(fn (Carbon $date) => [
                'monthName' => $date->shortMonthName.' / '.str($date->year)->substr(2),
                'date' => $date,
                'day' => $date->day,
            ])
            ->groupBy('monthName')
            ->map(fn ($dates) => $dates
                ->mapWithKeys(function ($date) use ($holidays) {
                    $state = 'Y';

                    if ($holidays->contains($date['date']->format('Y-m-d'))) {
                        $state = 'PH';
                    }

                    if ($date['date']->isWeekend()) {
                        $state = 'WE';
                    }

                    //return - if candidate havent join company or date is before candidate join
                    if ($date['date'] < $this->candidate->from || $date['date'] > $this->candidate->to) {
                        $state = 'NA';
                    }

                    return [
                        $date['day'] => $state,
                    ];
                })
                ->toArray()
            )
            ->toArray();

        $pdf = Pdf::loadView('template.attendance-report', [
            'name' => $this->candidate->name,
            'attendance' => $attendances,
        ])
            ->setPaper('A4', 'landscape')
            ->setOption([
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        $filename = storage_path().'/attendance-report-'.Str::slug($this->candidate->name).'.pdf';
        $pdf->save($filename);

        $this->candidate->copyMedia($filename)
            ->toMediaCollection('other_documents');

        unlink($filename);
    }
}
