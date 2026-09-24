<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Trip;
use App\Models\TravellerPayment;

class LeadService
{
    public function createOrGetLead(Trip $trip): Lead
    {
        return Lead::firstOrCreate(
            ['trip_id' => $trip->id],
            [
                'user_id' => $trip->user_id,
                'stage' => 'follow_up',
                'enquiry_date' => now(),
            ]
        );
    }

    public function updateInteraction(Lead $lead, string $mode, ?string $notes = null): void
    {
        $lead->update([
            'last_interaction_date' => now(),
            'interaction_mode' => $mode,
            'notes' => $notes ?? $lead->notes,
        ]);
    }

    public function markWon(Lead $lead): void
    {
        $lead->update(['stage' => 'won']);

        // A lead filed by hand has no trip until the traveller makes one, so
        // there may be nothing to confirm. Winning the enquiry is the point;
        // confirming a journey is only possible once there is one.
        $lead->trip?->update(['status' => 'confirmed']);
    }

    public function markLost(Lead $lead): void
    {
        $lead->update(['stage' => 'lost']);
    }

    /** What has actually been paid. Abandoned attempts do not count. */
    public function amountPaid(Trip $trip): float
    {
        return (float) TravellerPayment::where('trip_id', $trip->id)
            ->where('payment_status', 'paid')
            ->sum('amount');
    }

    /** A payment confirms a trip that nobody has confirmed yet. */
    public function paymentShouldConfirm(Trip $trip): bool
    {
        return $this->amountPaid($trip) > 0 && $trip->status === 'not_confirmed';
    }

    /**
     * Everything a payment does to a trip, apart from confirming it.
     *
     * Confirming is deliberately not done here. It used to be, with a bare
     * $trip->update(['status' => 'confirmed']), which set the word and nothing
     * else: no rooms were made firm at the property and no partner was invoiced.
     * That was survivable while HCT pressing Confirmed was the usual route,
     * because that route does the whole job. It stopped being survivable when a
     * payment became the usual route - a paid trip left every host unpaid on
     * paper and its rooms still merely held, which is to say still sellable.
     *
     * The caller confirms through applyTripStatus(), the one place that knows
     * what confirming a trip means. See paymentShouldConfirm().
     */
    public function checkPaymentAndTransition(Trip $trip): void
    {
        $totalPaid = $this->amountPaid($trip);
        if ($totalPaid <= 0) {
            return;
        }

        // Lock the trip from further edits when the traveller has paid in full.
        // Tiny epsilon absorbs decimal rounding (e.g. 8585.99 vs 8586.00).
        $finalPrice = (float) $trip->final_price;
        if ($finalPrice > 0 && ($totalPaid + 0.01) >= $finalPrice && $trip->stage === 'open') {
            $trip->update(['stage' => 'closed']);
        }

        // Lead workflow runs as a side-effect — never a precondition for the
        // transitions above (self-service paid trips often have no lead in
        // follow_up).
        if ($trip->lead && $trip->lead->stage === 'follow_up') {
            $this->markWon($trip->lead);
        }

        $this->winLeadsFor($trip);
    }

    /**
     * Close the enquiry this payment answers.
     *
     * A lead is filed before there is any trip: somebody rang, and the only
     * thing held about them is an email. When that person later signs up with
     * it, builds a journey and pays, the enquiry has been answered and nobody
     * needs to ring them again - so it is won, and tied to the trip that
     * answered it, by that email.
     *
     * Matched on the traveller's address rather than on user_id, because the
     * account may not have existed when the enquiry was filed.
     */
    public function winLeadsFor(Trip $trip): void
    {
        $email = $trip->user?->email;
        if (! $email) {
            return;
        }

        $open = Lead::where('stage', 'follow_up')
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->get();

        foreach ($open as $lead) {
            // Keep the trip that answered it, so the lead stops being a loose
            // note and can be opened from the leads page.
            if (! $lead->trip_id) {
                $lead->update(['trip_id' => $trip->id, 'user_id' => $trip->user_id]);
            }
            $this->markWon($lead);
        }
    }

    public function getReminders(): \Illuminate\Database\Eloquent\Collection
    {
        return Lead::where('stage', 'follow_up')
            ->where(function ($q) {
                $q->whereNull('last_interaction_date')
                    ->whereRaw('DATE_ADD(enquiry_date, INTERVAL reminder_delay_days DAY) <= NOW()')
                    ->orWhereRaw('DATE_ADD(last_interaction_date, INTERVAL reminder_delay_days DAY) <= NOW()');
            })
            ->with(['user', 'trip', 'assignedHct'])
            ->get();
    }
}
