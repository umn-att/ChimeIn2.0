<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\SubmitResponse;
use App\Events\StartSession;
use App\Events\EndSession;
use App\Listeners\InvalidateResultsCache;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\Event' => [
            'App\Listeners\EventListener',
        ],
        SubmitResponse::class => [
            InvalidateResultsCache::class,
        ],
        StartSession::class => [
            InvalidateResultsCache::class,
        ],
        EndSession::class => [
            InvalidateResultsCache::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
