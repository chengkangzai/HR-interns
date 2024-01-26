<?php

namespace App\Jobs;

use App\Models\Candidate;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Str;

class GenerateOfferLetterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Candidate $candidate,
        public ?int $pay,
        public ?string $workingFrom = '09:00',
        public ?string $workingTo = '18:00',
    ) {
    }

    public function handle(): void
    {
        $pdf = Pdf::loadView('template.offer-letter', [
            'candidate' => $this->candidate,
            'position' => $this->candidate->position,
            'pay' => $this->pay ?? 0,
            'schedule' => [
                'from' => Carbon::createFromTimeString($this->workingFrom),
                'to' => Carbon::createFromTimeString($this->workingTo),
            ],
        ])
            ->setPaper('A4')
            ->setOption([
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        $filename = storage_path().'/offer-letter-'.Str::slug($this->candidate->name).'.pdf';
        $pdf->save($filename);

        $this->candidate->copyMedia($filename)
            ->toMediaCollection('offer_letters');

        unlink($filename);
    }
}
