<?php

namespace App\Mail;

use App\Models\Candidate;
use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DefaultMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public string $mailable;

    public function __construct(
        public Candidate $candidate,
        public Email $email,
        public ?Collection $medias = null,
    ) {
        $this->mailable = self::class;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            to: [$this->candidate->email],
            cc: $this->email->cc,
            subject: $this->email->title
        );
    }

    public function content(): Content
    {
        $replaceTemplateValue = [
            '{{NAME}}' => $this->candidate?->name ?? 'Participant',
        ];

        $message = Str::replace(array_keys($replaceTemplateValue), array_values($replaceTemplateValue), $this->email->body);

        return new Content(
            markdown: 'emails.default',
            with: [
                'message' => $message,
            ]
        );
    }

    public function attachments(): array
    {
        if ($this->medias === null) {
            return [];
        }

        return $this->medias
            ->flatMap(fn (MediaCollection $medias) => $medias
                ->map(fn (Media $media) => $media->toMailAttachment())
            )
            ->toArray();
    }
}
