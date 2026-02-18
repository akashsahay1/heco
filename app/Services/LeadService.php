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
        $totalPaid = TravellerPayment::where('trip_id', $trip->id)->sum('amount');
        if ($totalPaid > 0 && $trip->lead && $trip->lead->stage === 'follow_up') {
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
