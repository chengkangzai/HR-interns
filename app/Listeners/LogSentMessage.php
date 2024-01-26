<?php

namespace App\Listeners;

use App\Mail\DefaultMail;
use Illuminate\Mail\Events\MessageSent;

class LogSentMessage
{
    public function handle(MessageSent $event): void
    {
        switch ($event->data['mailable']) {
            case DefaultMail::class:
                $this->logSentEmail($event);
                break;
            default:
                break;
        }
    }

    private function logSentEmail(MessageSent $event): void
    {
        activity()
            ->performedOn($event->data['candidate'])
            ->withProperties([
                'email' => $event->data['email']->id,
                'candidate' => $event->data['candidate']->id,
                'subject' => $event->message->getSubject(),
                'to' => $event->message->getTo(),
                'cc' => $event->message->getCc(),
                'bcc' => $event->message->getBcc(),
                'html_body' => $event->message->getHtmlBody(),
            ])
            ->event('email_sent')
            ->log('Email has been sent to candidate');
    }
}
