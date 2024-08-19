<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        'Spatie\Tags\Tag' => 'App\Policies\TagPolicy',
        'Croustibat\FilamentJobsMonitor\Models\QueueMonitor' => 'App\Policies\QueueMonitorPolicy',
        'Spatie\Activitylog\Models\Activity' => 'App\Policies\ActivityPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
