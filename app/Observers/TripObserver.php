<?php

namespace App\Observers;

use App\Models\Trip;
use App\Services\LeadService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Every new trip gets a lead, or joins the person's open one (HECO's
 * answers, 2026-11-12, Q6). Run after the commit, so a trip the team ties
 * to a lead inside the same transaction is already tied when this looks.
 */
class TripObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Trip $trip): void
    {
        app(LeadService::class)->leadForNewTrip($trip);
    }
}
