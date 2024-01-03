<?php

namespace App\Mail;

use App\Models\Broadcast;
use App\Models\Campaign;
use App\Models\Candidate;
use App\Models\Email;
use Filament\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DefaultMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public string $mailable;

    public function __construct(
        public Candidate   $candidate,
        public Email       $email,
        public ?Collection $medias =null,
    )
    {
        $this->mailable = self::class;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            to: [$this->candidate->email],
            subject: $this->email->title
        );
    }

    public function content(): Content
    {
        $replaceTemplateValue = [
            '{{CUSTOMER_NAME}}' => $this->candidate->customer?->name ?? 'customer',
        ];

        $message = Str::replace(array_keys($replaceTemplateValue), array_values($replaceTemplateValue), $this->email->body);

        return new Content(
            markdown: 'emails.default',
            with: [
                'message' => $message
            ]
        );
    }
}
