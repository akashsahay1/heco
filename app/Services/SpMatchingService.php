<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayService;
use App\Models\ServiceProvider;
use App\Models\SpPricing;
use Illuminate\Support\Facades\Log;

class SpMatchingService
{
    protected SpAvailabilityService $availability;

    public function __construct()
    {
        $this->availability = new SpAvailabilityService();
    }

    /**
     * Auto-assign available SPs to unassigned services in a trip.
     * Returns count of services that got an SP assigned.
     */
    public function assignProvidersToTrip(Trip $trip): int
    {
        $assigned = 0;

        $trip->load(['tripDays.services', 'regions']);
        $regionIds = $trip->regions()->pluck('regions.id')->toArray();

        foreach ($trip->tripDays as $day) {
            $date = $day->date;
            if (!$date) continue;

            foreach ($day->services as $service) {
                // Skip if already assigned
                if ($service->service_provider_id) continue;

                $sp = $this->findBestSp($service, $date, $regionIds);
                if (!$sp) continue;

                try {
                    // Book the date
                    $this->availability->bookForTrip($sp['provider']->id, $trip->id, $service->id, $date);

                    // Update the service with SP and real pricing
                    $updateData = ['service_provider_id' => $sp['provider']->id];
                    if ($sp['price'] !== null) {
                        $updateData['cost'] = $sp['price'];
                    }
                    $service->update($updateData);
                    $assigned++;
                } catch (\RuntimeException $e) {
                    // Conflict — SP got booked between our check and the booking
                    Log::debug("SP matching conflict for service #{$service->id}: " . $e->getMessage());
                }
            }
        }

        // Recalculate trip costs after assignment
        if ($assigned > 0) {
            try {
                app(CostCalculatorService::class)->recalculate($trip);
            } catch (\Exception $e) {
                Log::warning("Cost recalculation failed after SP matching: " . $e->getMessage());
            }
        }

        return $assigned;
    }

    /**
     * Find the best available SP for a service on a given date.
     */
    protected function findBestSp(TripDayService $service, $date, array $regionIds): ?array
    {
        // Find available SPs matching region and service type
        $candidates = ServiceProvider::where('status', 'approved')
            ->whereIn('region_id', $regionIds)
            ->whereJsonContains('services_offered', $service->service_type)
            ->whereDoesntHave('availability', function ($q) use ($date) {
                $q->where('date', $date);
            })
            ->with(['pricing' => function ($q) use ($service) {
                $q->where('service_type', $service->service_type)->where('is_active', true);
            }])
            ->get();

        if ($candidates->isEmpty()) return null;

        // Score candidates: prefer those with pricing data, then cheapest
        $best = null;
        $bestScore = -1;

        foreach ($candidates as $sp) {
            $pricing = $sp->pricing->first();
            $price = $pricing?->price;

            // Score: SPs with pricing get higher base score
            $score = $pricing ? 100 : 0;

            // Lower price = higher score (add inverse of price, capped)
            if ($price !== null && $price > 0) {
                $score += max(0, 50 - ($price / 100));
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['provider' => $sp, 'price' => $price];
            }
        }

        return $best;
    }
}
