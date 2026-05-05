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
        $lead->trip->update(['status' => 'confirmed']);
    }

    public function markLost(Lead $lead): void
    {
        $lead->update(['stage' => 'lost']);
    }

    public function checkPaymentAndTransition(Trip $trip): void
    {
        // Only successful payments count. Pending/failed rows from abandoned
        // Razorpay attempts must not transition the trip.
        $totalPaid = (float) TravellerPayment::where('trip_id', $trip->id)
            ->where('payment_status', 'paid')
            ->sum('amount');
        if ($totalPaid <= 0) return;

        $finalPrice = (float) $trip->final_price;
        $updates = [];
        if ($trip->status === 'not_confirmed') {
            $updates['status'] = 'confirmed';
        }
        // Lock the trip from further edits when the traveller has paid in full.
        // Tiny epsilon absorbs decimal rounding (e.g. 8585.99 vs 8586.00).
        if ($finalPrice > 0 && ($totalPaid + 0.01) >= $finalPrice && $trip->stage === 'open') {
            $updates['stage'] = 'closed';
        }
        if (!empty($updates)) {
            $trip->update($updates);
        }

        // Lead workflow runs as a side-effect — never a precondition for the
        // status/stage transitions above (self-service paid trips often have
        // no lead in follow_up).
        if ($trip->lead && $trip->lead->stage === 'follow_up') {
            $this->markWon($trip->lead);
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
