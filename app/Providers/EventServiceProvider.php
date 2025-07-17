<?php

namespace App\Providers;

use App\Events\ScriptExecutionRequested;
use App\Listeners\ExecuteTriggeredScripts;
use App\Services\ScriptTriggerService;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     */
    protected $listen = [
        ScriptExecutionRequested::class => [
            ExecuteTriggeredScripts::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        // Register script trigger event listeners
        $this->app->make(ScriptTriggerService::class)->registerEventListeners();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}