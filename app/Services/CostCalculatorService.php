<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Setting;
use App\Models\SpPricing;
use Carbon\Carbon;

class CostCalculatorService
{
    /**
     * Single source of truth for preference-option multipliers.
     * Used by the calculator AND surfaced to the portal view so each
     * dropdown option can show its computed price inline.
     */
    public static function getMultiplierMap(): array
    {
        return [
            'accommodation_comfort' => [
                'Cat E - Camping/Tents'      => 0.5,
                'Cat D - Basic/Homestay'     => 0.7,
                'Cat C - Standard'           => 1.0,
                'Cat B - Comfort'            => 1.5,
                'Cat A - Premium/Luxury'     => 2.5,
            ],
            'vehicle_comfort' => [
                'Local Transport'            => 0.5,
                'SUV (Bolero/Scorpio)'       => 0.8,
                'SUV (Innova/Crysta)'        => 1.0,
                'Premium (Fortuner/Similar)' => 1.5,
                'Tempo Traveller'            => 1.3,
            ],
            'guide_preference' => [
                'No Guide'                   => 0.0,
                'Local Guide'                => 0.7,
                'English-speaking'           => 1.0,
                'Certified/Expert'           => 1.5,
            ],
            'travel_pace' => [
                'Relaxed'                    => 0.9,
                'Moderate'                   => 1.0,
                'Active'                     => 1.15,
                'Intensive'                  => 1.3,
            ],
            'budget_sensitivity' => [
                'Budget-friendly'            => 0.85,
                'Mid-range'                  => 1.0,
                'Premium'                    => 1.25,
                'No Limit'                   => 1.5,
            ],
        ];
    }

    protected function lookup(string $listType, ?string $name): float
    {
        if ($name === null || $name === '') return 1.0;
        return self::getMultiplierMap()[$listType][$name] ?? 1.0;
    }

    protected function getAccommodationMultiplier(?string $comfort): float
    {
        return $this->lookup('accommodation_comfort', $comfort);
    }

    protected function getVehicleMultiplier(?string $vehicle): float
    {
        return $this->lookup('vehicle_comfort', $vehicle);
    }

    protected function getGuideMultiplier(?string $guide): float
    {
        return $this->lookup('guide_preference', $guide);
    }

    protected function getPaceMultiplier(?string $pace): float
    {
        return $this->lookup('travel_pace', $pace);
    }

    protected function getBudgetMultiplier(?string $budget): float
    {
        return $this->lookup('budget_sensitivity', $budget);
    }

    /**
     * Number of nights to bill provider-driven accommodation for. Prefers the
     * trip's start/end span; falls back to (day count - 1) for trips that don't
     * yet have dates set. Always at least 1 so a chosen provider is charged.
     */
    protected function resolveNights(Trip $trip): int
    {
        if ($trip->start_date && $trip->end_date) {
            $nights = (int) Carbon::parse($trip->start_date)->diffInDays(Carbon::parse($trip->end_date));
            if ($nights > 0) return $nights;
        }
        return max($trip->tripDays->count() - 1, 1);
    }

    /**
     * Transport cost from a chosen provider, by pricing unit:
     *   "per day"    → rate × trip days
     *   "per person" → rate × pax
     *   "per trip" / flat / "per km" (no distance stored) → flat rate
     * (Per-km can't be multiplied without a trip distance, so it falls back to
     * the flat rate; add a distance field later if per-km accuracy is needed.)
     */
    protected function providerTransportCost(SpPricing $pricing, Trip $trip, int $adults, int $children): int
    {
        $rate = (float) $pricing->price;
        $unit = strtolower((string) $pricing->unit);
        if (str_contains($unit, 'day')) {
            $days = max($trip->tripDays->count() ?: ($this->resolveNights($trip) + 1), 1);
            return (int) round($rate * $days);
        }
        if (str_contains($unit, 'person') || str_contains($unit, 'pax')) {
            return (int) round($rate * max($adults + $children, 1));
        }
        return (int) round($rate);
    }

    /**
     * Amount owed to a provider for one pinned service on this trip, using the
     * same rate × quantity rules as the provider-override block below:
     *   accommodation → price × rooms × nights (rooms = ceil(pax / occupancy))
     *   guide         → price × guide-days
     *   transport     → per unit (day / person / flat)
     * Used to auto-fill SpPayment invoices so provider payables aren't hand-typed.
     */
    public function providerPayable(SpPricing $pricing, Trip $trip, string $serviceType): int
    {
        $trip->loadMissing('tripDays');
        $adults = max((int) $trip->adults, 1);
        $children = (int) ($trip->children ?: 0);

        if ($serviceType === 'accommodation') {
            $nights = $this->resolveNights($trip);
            $occupancy = max((int) ($pricing->default_occupancy ?: 2), 1);
            $rooms = max((int) ceil(($adults + $children) / $occupancy), 1);
            return (int) round((float) $pricing->price * $rooms * $nights);
        }
        if ($serviceType === 'guide') {
            $guideDays = max($trip->tripDays->count() ?: ($this->resolveNights($trip) + 1), 1);
            return (int) round((float) $pricing->price * $guideDays);
        }
        if ($serviceType === 'transport') {
            return $this->providerTransportCost($pricing, $trip, $adults, $children);
        }
        return (int) round((float) $pricing->price);
    }

    public function calculate(Trip $trip): array
    {
        $trip->load(['tripDays.services', 'tripDays.experiences.experience']);

        $accomMultiplier = $this->getAccommodationMultiplier($trip->accommodation_comfort);
        $vehicleMultiplier = $this->getVehicleMultiplier($trip->vehicle_comfort);
        $guideMultiplier = $this->getGuideMultiplier($trip->guide_preference);
        $paceMultiplier = $this->getPaceMultiplier($trip->travel_pace);
        $budgetMultiplier = $this->getBudgetMultiplier($trip->budget_sensitivity);

        $transportCost = 0;
        $accommodationCost = 0;
        $guideCost = 0;
        $activityCost = 0;
        $otherCost = 0;

        // Track each per-option line at multiplier=1.0 so the portal can show
        // alternative prices (e.g. "what if I picked Premium SUV instead?")
        // inside each dropdown option without re-running the calculator.
        $transportBase = 0;
        $accommodationBase = 0;
        $guideBase = 0;

        // Extra day costs — different rates for rest and activity days
        $restDayCostPerPerson = (float) Setting::getValue('rest_day_cost_per_person', 2000);
        $activityDayCostPerPerson = (float) Setting::getValue('activity_day_cost_per_person', 5000);
        $adults = max($trip->adults, 1);
        $children = $trip->children ?: 0;
        $infants = $trip->infants ?: 0;
        // Pax-type pricing (#42): child/infant bill at a configurable fraction of an
        // adult (was a hardcoded 50% for children, infants ignored). One factor so
        // every line item bills the same way.
        $childFactor  = (float) Setting::getValue('child_price_percent', 50) / 100;
        $infantFactor = (float) Setting::getValue('infant_price_percent', 0) / 100;
        $peopleFactor = $adults + ($childFactor * $children) + ($infantFactor * $infants);
        $extraDayCost = 0;

        // Track which experiences have already been costed (charge once per experience, not per day)
        $chargedExperienceIds = [];

        // A day-level SP-assigned service (cost>0) REPLACES the experience's bundled
        // estimate for that category — it must NOT stack on top, which would double-
        // charge the traveller (provider rate + bundled component). Detect which
        // categories a real day service already covers so those components are dropped.
        $serviceCovered = ['transport' => false, 'accommodation' => false, 'guide' => false];
        foreach ($trip->tripDays as $day) {
            foreach ($day->services as $service) {
                if ((float) $service->cost > 0 && array_key_exists($service->service_type, $serviceCovered)) {
                    $serviceCovered[$service->service_type] = true;
                }
            }
        }

        foreach ($trip->tripDays as $day) {
            // Charge extra days (days without experiences)
            $hasExperiences = $day->experiences->isNotEmpty();
            if (!$hasExperiences && $day->day_type) {
                $costPerPerson = in_array($day->day_type, ['activity', 'free']) ? $activityDayCostPerPerson : $restDayCostPerPerson;
                $extraDayCost += $costPerPerson * $peopleFactor;
            }

            // SP-matched services contribute their booked price on top of the experience's bundled estimate.
            // Services left at cost=0 are placeholders for the bundled cost we already capture below from
            // the Experience breakdown, so skip them to avoid double-counting / zeroing out the line.
            foreach ($day->services as $service) {
                $cost = (float) $service->cost;
                if ($cost <= 0) continue;
                match ($service->service_type) {
                    'transport' => [$transportCost += round($cost * $vehicleMultiplier), $transportBase += $cost],
                    'accommodation' => [$accommodationCost += round($cost * $accomMultiplier), $accommodationBase += $cost],
                    'guide' => [$guideCost += round($cost * $guideMultiplier), $guideBase += $cost],
                    'activity' => $activityCost += $cost,
                    default => $otherCost += $cost,
                };
            }
            foreach ($day->experiences as $dayExp) {
                // Only charge each experience once across all days
                if (in_array($dayExp->experience_id, $chargedExperienceIds)) {
                    $dayExp->update(['total_cost' => 0]);
                    continue;
                }
                $chargedExperienceIds[] = $dayExp->experience_id;
                $exp = $dayExp->experience;
                if (!$exp) continue;

                // Split each experience's bundled price into its line items (accommodation/logistics/
                // guide/activities/other) so the pricing summary actually shows where the money goes
                // instead of dumping everything into "Activities" with 0s elsewhere.
                $accomComponent      = (float) $exp->cost_accommodation;
                $logisticsComponent  = (float) $exp->cost_logistics;
                $guideComponent      = (float) $exp->cost_guide;
                $activitiesComponent = (float) $exp->cost_activities;
                $otherComponent      = (float) $exp->cost_other;

                // Fall back to the headline per-person price when the breakdown is
                // missing (older/legacy experiences) so we don't silently report ₹0.
                // Use the Experience's base_cost_per_person — the SAME field the guest
                // estimator falls back to — so guest and logged-in quotes stay equal
                // even for legacy experiences (dayExp->cost_per_person is the activity
                // slice and is often 0, which under-charged the trip).
                $componentSum = $accomComponent + $logisticsComponent + $guideComponent
                    + $activitiesComponent + $otherComponent;
                if ($componentSum <= 0) {
                    $activitiesComponent = (float) ($exp->base_cost_per_person ?: $dayExp->cost_per_person);
                    $componentSum = $activitiesComponent;
                }

                // Drop any component already covered by a day-level provider so the
                // provider rate replaces (not stacks on) the bundled estimate (#F1).
                $effAccom     = $serviceCovered['accommodation'] ? 0.0 : $accomComponent;
                $effLogistics = $serviceCovered['transport']     ? 0.0 : $logisticsComponent;
                $effGuide     = $serviceCovered['guide']         ? 0.0 : $guideComponent;

                $accommodationCost += round($effAccom      * $peopleFactor * $accomMultiplier);
                $transportCost     += round($effLogistics  * $peopleFactor * $vehicleMultiplier);
                $guideCost         += round($effGuide      * $peopleFactor * $guideMultiplier);
                $activityCost      += round($activitiesComponent * $peopleFactor);
                $otherCost         += round($otherComponent      * $peopleFactor);

                $accommodationBase += $effAccom     * $peopleFactor;
                $transportBase     += $effLogistics * $peopleFactor;
                $guideBase         += $effGuide     * $peopleFactor;

                // Keep TripDayExperience.total_cost in sync for any downstream readers.
                $expTotal = ($effAccom * $accomMultiplier
                    + $effLogistics * $vehicleMultiplier
                    + $effGuide * $guideMultiplier
                    + $activitiesComponent
                    + $otherComponent) * $peopleFactor;
                $dayExp->update(['total_cost' => round($expTotal)]);
            }
        }

        // Travel pace scales activity-driven costs. Activities and Extra Days are
        // intentionally EXCLUDED — those lines are shown at their plain cost (no
        // preference multiplier), per product decision; only guide-led time is paced.
        $guideCost = (int) round($guideCost * $paceMultiplier);
        $guideBase = $guideBase * $paceMultiplier;

        // Budget sensitivity scales the base trip cost — again EXCLUDING Activities
        // and Extra Days (kept at plain cost).
        $transportCost = (int) round($transportCost * $budgetMultiplier);
        $accommodationCost = (int) round($accommodationCost * $budgetMultiplier);
        $guideCost = (int) round($guideCost * $budgetMultiplier);
        $otherCost = (int) round($otherCost * $budgetMultiplier);
        $transportBase = $transportBase * $budgetMultiplier;
        $accommodationBase = $accommodationBase * $budgetMultiplier;
        $guideBase = $guideBase * $budgetMultiplier;

        // ── Provider-driven costs ───────────────────────────────────────────────
        // When the traveller fixes a specific provider for accommodation / guide /
        // transport, that provider's sp_pricing rate is a contractual figure that
        // REPLACES the experience estimate for that line — equal to the rate shown
        // in the dropdown × the relevant quantity.
        if ($trip->accommodation_pricing_id && ($accomPricing = SpPricing::live()->find($trip->accommodation_pricing_id))) {
            $nights = $this->resolveNights($trip);
            $occupancy = max((int) ($accomPricing->default_occupancy ?: 2), 1);
            $rooms = max((int) ceil(($adults + $children) / $occupancy), 1);
            $accommodationCost = (int) round((float) $accomPricing->price * $rooms * $nights);
            $accomMultiplier = 1.0;
        }
        if ($trip->guide_pricing_id && ($guidePricing = SpPricing::live()->find($trip->guide_pricing_id))) {
            $guideDays = max($trip->tripDays->count() ?: ($this->resolveNights($trip) + 1), 1);
            $guideCost = (int) round((float) $guidePricing->price * $guideDays);
            $guideMultiplier = 1.0;
        }
        if ($trip->vehicle_pricing_id && ($vehiclePricing = SpPricing::live()->find($trip->vehicle_pricing_id))) {
            $transportCost = $this->providerTransportCost($vehiclePricing, $trip, $adults, $children);
            $vehicleMultiplier = 1.0;
        }

        $totalCost = $transportCost + $accommodationCost + $guideCost + $activityCost + $otherCost + $extraDayCost;

        // Cast to float first — DB returns DECIMAL columns as strings (e.g. "0.00"),
        // and any non-empty string is truthy in PHP, so `?:` would skip the default.
        $rpPercent  = (float) $trip->margin_rp_percent       ?: (float) Setting::getValue('default_rp_margin_percent', 5);
        $hrpPercent = (float) $trip->margin_hrp_percent      ?: (float) Setting::getValue('default_hrp_margin_percent', 10);
        $hctPercent = (float) $trip->commission_hct_percent  ?: (float) Setting::getValue('default_hct_commission_percent', 15);

        $rpAmount = round($totalCost * $rpPercent / 100, 2);
        $hrpAmount = round($totalCost * $hrpPercent / 100, 2);
        $hctAmount = round($totalCost * $hctPercent / 100, 2);

        $subtotal = $totalCost + $rpAmount + $hrpAmount + $hctAmount;
        $gstPercent = (float) Setting::getValue('gst_percent', 5);
        $gstAmount = round($subtotal * $gstPercent / 100, 2);
        $finalPrice = $subtotal + $gstAmount;

        $data = [
            'transport_cost' => $transportCost,
            'accommodation_cost' => $accommodationCost,
            'guide_cost' => $guideCost,
            'activity_cost' => $activityCost,
            'extra_day_cost' => $extraDayCost,
            'other_cost' => $otherCost,
            'total_cost' => $totalCost,
            'margin_rp_percent' => $rpPercent,
            'margin_rp_amount' => $rpAmount,
            'margin_hrp_percent' => $hrpPercent,
            'margin_hrp_amount' => $hrpAmount,
            'commission_hct_percent' => $hctPercent,
            'commission_hct_amount' => $hctAmount,
            'subtotal' => $subtotal,
            'gst_amount' => $gstAmount,
            'final_price' => $finalPrice,
        ];

        $trip->update($data);

        // Add display-only details for the pricing summary captions (not persisted)
        $data['vehicle_multiplier']       = $vehicleMultiplier;
        $data['accommodation_multiplier'] = $accomMultiplier;
        $data['guide_multiplier']         = $guideMultiplier;
        $data['pace_multiplier']          = $paceMultiplier;
        $data['budget_multiplier']        = $budgetMultiplier;
        $data['gst_percent']              = (float) Setting::getValue('gst_percent', 5);
        $data['adults']                   = $adults;
        $data['children']                 = $children;

        // Per-option base costs (multiplier=1.0 equivalent, with pace/budget already
        // baked in). Portal multiplies these by each dropdown option's data-multiplier
        // to render alternate prices inline.
        $data['transport_base']      = (int) round($transportBase);
        $data['accommodation_base']  = (int) round($accommodationBase);
        $data['guide_base']          = (int) round($guideBase);

        return $data;
    }
}
