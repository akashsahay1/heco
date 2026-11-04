<?php

namespace App\Services;

use App\Models\SpPayment;
use App\Models\Trip;
use Illuminate\Support\Facades\Cache;

/**
 * Moves confirmed trips on by their dates: Running once the first day has
 * come, Completed once the last has gone.
 *
 * HECO asked for the status to follow from payments and dates instead of being
 * picked by hand (feedback, 2026-10-28). A payment already confirms a trip; the
 * dates are this. The live host has no cron, so it runs on the requests people
 * make anyway, at most once every few minutes, and is two UPDATEs when it does.
 *
 * A trip that has run is Closed once every payment on it is settled, both what
 * the traveller owes HECO and what HECO owes its providers (closeIfSettled).
 */
class TripStatusClock
{
    /** How often, in seconds, the dates are looked at. */
    private const EVERY = 300;

    public function tick(): void
    {
        // add() writes only when the key is absent, so of the requests that
        // arrive together one does the work and the rest go straight on.
        if (! Cache::add('trip-status-clock', true, self::EVERY)) {
            return;
        }

        $today = now()->toDateString();

        // A trip that never confirmed was never taken, whatever its dates.
        Trip::whereIn('status', ['confirmed', 'running'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->update(['status' => 'completed']);

        Trip::where('status', 'confirmed')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $today)
            ->update(['status' => 'running']);

        // A block, not an arrow: each() stops at the first callback that
        // returns false, and closeIfSettled returns false for every trip still
        // owed money, so one unsettled trip kept every later one from closing.
        Trip::where('status', 'completed')->where('stage', 'open')->get()
            ->each(function (Trip $trip) {
                $this->closeIfSettled($trip);
            });
    }

    /**
     * Close a completed trip whose money is all settled: the traveller has paid
     * the final price and every provider has been paid what they are owed.
     * Closed, it leaves the Calendar and the Dashboard, which then show only
     * live work (HECO's feedback, 2026-10-28). Returns whether it closed.
     */
    public function closeIfSettled(Trip $trip): bool
    {
        if ($trip->status !== 'completed' || $trip->stage !== 'open') {
            return false;
        }

        $paid = (float) $trip->travellerPayments()->where('payment_status', 'paid')->sum('amount');
        // Nothing paid is nothing settled: the trip's money has not been
        // written down yet, and closing would lock it before it is.
        if ($paid <= 0 || (float) $trip->final_price - $paid > 0.5) {
            return false;
        }
        if (SpPayment::where('trip_id', $trip->id)->where('balance', '>', 0.5)->exists()) {
            return false;
        }

        $trip->update(['stage' => 'closed']);

        return true;
    }
}
