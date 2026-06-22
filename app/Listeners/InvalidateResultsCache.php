<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Cache;

class InvalidateResultsCache
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     * Works with SubmitResponse, StartSession, and EndSession events.
     */
    public function handle($event): void
    {
        // All these events have a $session property
        if (property_exists($event, 'session') && $event->session) {
            // Invalidate results cache for this session
            $cacheKey = "results-session-{$event->session->id}";
            Cache::forget($cacheKey);
        }
    }
}
