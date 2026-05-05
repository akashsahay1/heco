<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Setting;

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
        // Children charged at 50% — bake into a single multiplier so every line item bills the same way.
        $peopleFactor = $adults + (0.5 * $children);
        $extraDayCost = 0;

        // Track which experiences have already been costed (charge once per experience, not per day)
        $chargedExperienceIds = [];

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

                // Fall back to the headline per-person price when the breakdown is missing
                // (older/legacy experiences) so we don't silently report ₹0.
                $componentSum = $accomComponent + $logisticsComponent + $guideComponent
                    + $activitiesComponent + $otherComponent;
                if ($componentSum <= 0) {
                    $activitiesComponent = (float) $dayExp->cost_per_person;
                    $componentSum = $activitiesComponent;
                }

                $accommodationCost += round($accomComponent      * $peopleFactor * $accomMultiplier);
                $transportCost     += round($logisticsComponent  * $peopleFactor * $vehicleMultiplier);
                $guideCost         += round($guideComponent      * $peopleFactor * $guideMultiplier);
                $activityCost      += round($activitiesComponent * $peopleFactor);
                $otherCost         += round($otherComponent      * $peopleFactor);

                $accommodationBase += $accomComponent     * $peopleFactor;
                $transportBase     += $logisticsComponent * $peopleFactor;
                $guideBase         += $guideComponent     * $peopleFactor;

                // Keep TripDayExperience.total_cost in sync for any downstream readers.
                $expTotal = ($accomComponent * $accomMultiplier
                    + $logisticsComponent * $vehicleMultiplier
                    + $guideComponent * $guideMultiplier
                    + $activitiesComponent
                    + $otherComponent) * $peopleFactor;
                $dayExp->update(['total_cost' => round($expTotal)]);
            }
        }

        // Travel pace scales activity-driven costs (activities, guide-led time, extra activity days)
        $activityCost = (int) round($activityCost * $paceMultiplier);
        $guideCost = (int) round($guideCost * $paceMultiplier);
        $extraDayCost = (int) round($extraDayCost * $paceMultiplier);
        $guideBase = $guideBase * $paceMultiplier;

        // Budget sensitivity scales the entire base trip cost (excludes margins/GST below)
        $transportCost = (int) round($transportCost * $budgetMultiplier);
        $accommodationCost = (int) round($accommodationCost * $budgetMultiplier);
        $guideCost = (int) round($guideCost * $budgetMultiplier);
        $activityCost = (int) round($activityCost * $budgetMultiplier);
        $otherCost = (int) round($otherCost * $budgetMultiplier);
        $extraDayCost = (int) round($extraDayCost * $budgetMultiplier);
        $transportBase = $transportBase * $budgetMultiplier;
        $accommodationBase = $accommodationBase * $budgetMultiplier;
        $guideBase = $guideBase * $budgetMultiplier;

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
