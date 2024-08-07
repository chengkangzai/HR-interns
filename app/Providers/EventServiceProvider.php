<?php

namespace App\Providers;

use App\Listeners\LogSentMessage;
use App\Models\Email;
use App\Observers\EmailObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSent;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        MessageSent::class => [
            LogSentMessage::class,
        ],
    ];

    protected $observers = [
        Email::class => [
            EmailObserver::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
