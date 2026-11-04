<?php

use App\Models\Trip;
use App\Services\TripStatusClock;
use Illuminate\Database\Migrations\Migration;

/**
 * Closed now means a trip has run and every payment on it is settled (HECO's
 * feedback, 2026-10-28). Before, paying in full closed a trip at once, and the
 * Trip Info dropdown could close one by hand, so trips that have not yet run,
 * or that still owe a provider, sit closed: out of the Calendar and the
 * Dashboard. They are opened again here; a completed trip that is settled
 * closes again by the same rule the clock uses. Cancelled trips are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Trip::where('stage', 'closed')
            ->whereIn('status', ['not_confirmed', 'confirmed', 'running', 'completed'])
            ->update(['stage' => 'open']);

        $clock = app(TripStatusClock::class);
        Trip::where('status', 'completed')->where('stage', 'open')->get()
            ->each(function (Trip $trip) use ($clock) {
                $clock->closeIfSettled($trip);
            });
    }

    public function down(): void
    {
        // Not reversible: which trips were closed by the old rule is not kept.
    }
};
