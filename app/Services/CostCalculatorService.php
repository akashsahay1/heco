<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Setting;
use App\Models\SpPricing;
use App\Models\ServiceProvider;
use Carbon\Carbon;

class CostCalculatorService
{
    /**
     * Preference-option multipliers were REMOVED (client req 3.2 — the experience
     * is now priced by per-person slabs, and provider services by rate × quantity
     * with an admin markup). Kept as an empty map so any legacy caller
     * (HomepageController, guest estimator) degrades to "no multiplier" instead of
     * fataling. Delete once all callers are cleaned up.
     */
    public static function getMultiplierMap(): array
    {
        return [];
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
     * RAW transport cost from a chosen provider row (no markup — this is what the
     * provider is owed), by pricing unit:
     *   "per km"     → rate × distance_km   (req 3.1; distance admin-set on the row)
     *   "per day"    → rate × trip days
     *   "per person" → rate × pax
     *   "per trip" / flat → flat rate
     */
    protected function providerTransportCost(SpPricing $pricing, Trip $trip, int $adults, int $children): int
    {
        $rate = (float) $pricing->price;
        $unit = strtolower((string) $pricing->unit);
        if (str_contains($unit, 'km')) {
            // Distance × per-km rate. No distance set → 0 (admin must enter the route KM).
            return (int) round($rate * (float) ($pricing->distance_km ?: 0));
        }
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
     * Admin markup applied to a provider's RAW price before the traveller sees it
     * (req 3.3). The markup is the platform's margin; the raw price is never shown.
     * Providers with no markup of their own fall back to the global default (0%).
     */
    protected function applyMarkup(int $raw, ?ServiceProvider $provider): int
    {
        if (!$provider) {
            $pct = (float) Setting::getValue('default_provider_markup_percent', 0);
        } else {
            $pct = $provider->effectiveMarkupPercent();
        }
        return (int) round($raw * (1 + $pct / 100));
    }

    /**
     * RAW amount owed to a provider for one pinned service on this trip (no markup —
     * this feeds SpPayment invoices, which pay the provider their contracted rate):
     *   accommodation → price × rooms × nights (rooms = ceil(pax / occupancy))
     *   guide         → price × guide-days
     *   transport     → per unit (km / day / person / flat)
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
        $trip->load([
            'tripDays.services.serviceProvider',
            'tripDays.experiences.experience.priceSlabs',
        ]);

        // Pax-type factors (#42): a child/infant bills at a configurable fraction of
        // an adult. peopleFactor is the "billable head" count used to multiply
        // per-person prices; groupSize is the raw headcount used to pick the slab.
        $adults   = max($trip->adults, 1);
        $children = $trip->children ?: 0;
        $infants  = $trip->infants ?: 0;
        $childFactor  = (float) Setting::getValue('child_price_percent', 50) / 100;
        $infantFactor = (float) Setting::getValue('infant_price_percent', 0) / 100;
        $peopleFactor = $adults + ($childFactor * $children) + ($infantFactor * $infants);
        $groupSize    = max($adults + $children + $infants, 1);

        // Extra day costs — different rates for rest and activity days.
        $restDayCostPerPerson     = (float) Setting::getValue('rest_day_cost_per_person', 2000);
        $activityDayCostPerPerson = (float) Setting::getValue('activity_day_cost_per_person', 5000);

        $experienceCost    = 0; // slab-priced experience bundle(s) — the "Experiences" line
        $accommodationCost = 0; // provider hotel (marked up) + day-level accommodation
        $transportCost     = 0; // provider transport (marked up, per-km) + day-level transport
        $guideCost         = 0; // provider guide (marked up)
        $activityCost      = 0; // day-level activity services (marked up)
        $otherCost         = 0; // day-level other/meal services (marked up)
        $extraDayCost      = 0;

        // Charge each experience once across all days.
        $chargedExperienceIds = [];

        foreach ($trip->tripDays as $day) {
            // Extra days (days without experiences).
            $hasExperiences = $day->experiences->isNotEmpty();
            if (!$hasExperiences && $day->day_type) {
                $costPerPerson = in_array($day->day_type, ['activity', 'free']) ? $activityDayCostPerPerson : $restDayCostPerPerson;
                $extraDayCost += $costPerPerson * $peopleFactor;
            }

            // Day-level SP-matched services are provider add-ons that STACK on top of
            // the experience bundle. cost=0 rows are bundled-cost placeholders — skip.
            // Each is shown at its marked-up price (raw provider price stays hidden).
            foreach ($day->services as $service) {
                $cost = (float) $service->cost;
                if ($cost <= 0) continue;
                $marked = $this->applyMarkup((int) round($cost), $service->serviceProvider);
                match ($service->service_type) {
                    'transport'     => $transportCost += $marked,
                    'accommodation' => $accommodationCost += $marked,
                    'guide'         => $guideCost += $marked,
                    'activity'      => $activityCost += $marked,
                    default         => $otherCost += $marked,
                };
            }

            foreach ($day->experiences as $dayExp) {
                if (in_array($dayExp->experience_id, $chargedExperienceIds)) {
                    $dayExp->update(['total_cost' => 0]);
                    continue;
                }
                $chargedExperienceIds[] = $dayExp->experience_id;
                $exp = $dayExp->experience;
                if (!$exp) continue;

                // The experience is ONE slab-priced bundle (req 3.2): per-person price
                // by group size × billable heads. Trek stay (tent), hotel↔trek
                // transport, included guide and activities are all inside this bundle;
                // provider hotel/transport/guide are separate stacked lines below.
                $perPerson = $exp->slabPricePerPerson($groupSize);
                if ($perPerson <= 0) {
                    // Legacy experiences with no slabs: fall back to the headline
                    // per-person price, then to the sum of the cost components.
                    $perPerson = (float) ($exp->base_cost_per_person
                        ?: ($exp->cost_accommodation + $exp->cost_logistics + $exp->cost_guide
                            + $exp->cost_activities + $exp->cost_other));
                }
                $line = (int) round($perPerson * $peopleFactor);
                $experienceCost += $line;
                $dayExp->update(['total_cost' => $line]);
            }
        }

        // ── Trip-level provider pins — separate marked-up lines (they STACK on the
        //    experience bundle). Guide is exclusive: it can only be pinned when the
        //    experience provides none, so it never double-charges. ────────────────
        if ($trip->accommodation_pricing_id && ($accomPricing = SpPricing::live()->with('serviceProvider')->find($trip->accommodation_pricing_id))) {
            $nights = $this->resolveNights($trip);
            $occupancy = max((int) ($accomPricing->default_occupancy ?: 2), 1);
            $rooms = max((int) ceil(($adults + $children) / $occupancy), 1);
            $raw = (int) round((float) $accomPricing->price * $rooms * $nights);
            $accommodationCost += $this->applyMarkup($raw, $accomPricing->serviceProvider);
        }
        if ($trip->guide_pricing_id && ($guidePricing = SpPricing::live()->with('serviceProvider')->find($trip->guide_pricing_id))) {
            $guideDays = max($trip->tripDays->count() ?: ($this->resolveNights($trip) + 1), 1);
            $raw = (int) round((float) $guidePricing->price * $guideDays);
            $guideCost += $this->applyMarkup($raw, $guidePricing->serviceProvider);
        }
        if ($trip->vehicle_pricing_id && ($vehiclePricing = SpPricing::live()->with('serviceProvider')->find($trip->vehicle_pricing_id))) {
            $raw = $this->providerTransportCost($vehiclePricing, $trip, $adults, $children);
            $transportCost += $this->applyMarkup($raw, $vehiclePricing->serviceProvider);
        }

        $totalCost = $experienceCost + $accommodationCost + $transportCost + $guideCost
            + $activityCost + $otherCost + $extraDayCost;

        // Margins RP/HRP/HCT are computed for INTERNAL payout/reporting ONLY — they are
        // NOT added to what the traveller pays (req 3.3). The per-provider markup baked
        // into the lines above is the platform's margin. Downstream: RP is surfaced to
        // the traveller as an info line ("goes to the regenerative project"); HRP and
        // HCT stay hidden from the traveller and are used for internal splits.
        //
        // Cast to float first — DB DECIMALs come back as strings ("0.00"), which are
        // truthy, so `?:` would skip the configured default.
        $rpPercent  = (float) $trip->margin_rp_percent      ?: (float) Setting::getValue('default_rp_margin_percent', 5);
        $hrpPercent = (float) $trip->margin_hrp_percent     ?: (float) Setting::getValue('default_hrp_margin_percent', 10);
        $hctPercent = (float) $trip->commission_hct_percent ?: (float) Setting::getValue('default_hct_commission_percent', 15);
        $rpAmount   = round($totalCost * $rpPercent / 100, 2);
        $hrpAmount  = round($totalCost * $hrpPercent / 100, 2);
        $hctAmount  = round($totalCost * $hctPercent / 100, 2);

        // What the traveller actually pays: trip cost + GST. No margins on top.
        $subtotal   = $totalCost;
        $gstPercent = (float) Setting::getValue('gst_percent', 5);
        $gstAmount  = round($subtotal * $gstPercent / 100, 2);
        $finalPrice = $subtotal + $gstAmount;

        $data = [
            'experience_cost'         => $experienceCost,
            'transport_cost'          => $transportCost,
            'accommodation_cost'      => $accommodationCost,
            'guide_cost'              => $guideCost,
            'activity_cost'           => $activityCost,
            'extra_day_cost'          => $extraDayCost,
            'other_cost'              => $otherCost,
            'total_cost'              => $totalCost,
            'margin_rp_percent'       => $rpPercent,
            'margin_rp_amount'        => $rpAmount,
            'margin_hrp_percent'      => $hrpPercent,
            'margin_hrp_amount'       => $hrpAmount,
            'commission_hct_percent'  => $hctPercent,
            'commission_hct_amount'   => $hctAmount,
            'subtotal'                => $subtotal,
            'gst_amount'              => $gstAmount,
            'final_price'             => $finalPrice,
        ];

        $trip->update($data);

        // Display-only extras for the pricing summary (not persisted).
        $data['gst_percent'] = $gstPercent;
        $data['adults']      = $adults;
        $data['children']    = $children;
        // Provider-only line totals (already marked up). The experience bundle is its
        // own line; provider hotel/transport/guide stack as separate lines.
        $data['accommodation_provider_cost'] = (int) $accommodationCost;
        $data['transport_provider_cost']     = (int) $transportCost;
        $data['guide_provider_cost']         = (int) $guideCost;

        return $data;
    }
}
