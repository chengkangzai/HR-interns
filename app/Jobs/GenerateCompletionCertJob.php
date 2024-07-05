<?php

namespace App\Jobs;

use App\Models\Candidate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class GenerateCompletionCertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Candidate $candidate
    ) {}

    public function handle(): void
    {
        $pdf = Pdf::loadView('template.completion-cert', [
            'candidate' => $this->candidate,
        ])
            ->setPaper('A4', 'landscape')
            ->setOption([
                'dpi' => 170,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        $filename = storage_path().'/completion-cert-'.Str::slug($this->candidate->name).'.pdf';
        $pdf->save($filename);

        $this->candidate->copyMedia($filename)
            ->toMediaCollection('completion_cert');

        unlink($filename);
    }
}
