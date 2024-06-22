<?php

namespace App\Jobs;

use App\Mail\DefaultMail;
use App\Models\Candidate;
use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly Email $email, private readonly Candidate $candidate) {}

    public function handle(): void
    {
        Mail::to($this->candidate->email)
            ->send(new DefaultMail($this->candidate, $this->email));
    }
}
