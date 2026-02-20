<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Setting;

class CostCalculatorService
{
    /**
     * Multipliers based on travel preferences.
     */
    protected function getAccommodationMultiplier(?string $comfort): float
    {
        return match ($comfort) {
            'Cat E - Camping/Tents' => 0.5,
            'Cat D - Basic/Homestay' => 0.7,
            'Cat C - Standard' => 1.0,
            'Cat B - Comfort' => 1.5,
            'Cat A - Premium/Luxury' => 2.5,
            default => 1.0,
        };
    }

    protected function getVehicleMultiplier(?string $vehicle): float
    {
        return match ($vehicle) {
            'Local Transport' => 0.5,
            'SUV (Bolero/Scorpio)' => 0.8,
            'SUV (Innova/Crysta)' => 1.0,
            'Premium (Fortuner/Similar)' => 1.5,
            'Tempo Traveller' => 1.3,
            default => 1.0,
        };
    }

    protected function getGuideMultiplier(?string $guide): float
    {
        return match ($guide) {
            'No Guide' => 0.0,
            'Local Guide' => 0.7,
            'English-speaking' => 1.0,
            'Certified/Expert' => 1.5,
            default => 1.0,
        };
    }

    public function calculate(Trip $trip): array
    {
        $trip->load(['tripDays.services', 'tripDays.experiences.experience']);

        $accomMultiplier = $this->getAccommodationMultiplier($trip->accommodation_comfort);
        $vehicleMultiplier = $this->getVehicleMultiplier($trip->vehicle_comfort);
        $guideMultiplier = $this->getGuideMultiplier($trip->guide_preference);

        $transportCost = 0;
        $accommodationCost = 0;
        $guideCost = 0;
        $activityCost = 0;
        $otherCost = 0;

        foreach ($trip->tripDays as $day) {
            foreach ($day->services as $service) {
                $cost = $service->cost;
                match ($service->service_type) {
                    'transport' => $transportCost += round($cost * $vehicleMultiplier),
                    'accommodation' => $accommodationCost += round($cost * $accomMultiplier),
                    'guide' => $guideCost += round($cost * $guideMultiplier),
                    'activity' => $activityCost += $cost,
                    default => $otherCost += $cost,
                };
            }
            foreach ($day->experiences as $dayExp) {
                $expCost = $dayExp->total_cost;
                if ($expCost == 0 && $dayExp->cost_per_person > 0) {
                    $expCost = $dayExp->cost_per_person * ($trip->adults ?? 2);
                    $dayExp->update(['total_cost' => $expCost]);
                }
                $activityCost += $expCost;
            }
        }

        $totalCost = $transportCost + $accommodationCost + $guideCost + $activityCost + $otherCost;

        $rpPercent = $trip->margin_rp_percent ?: (float) Setting::getValue('default_rp_margin_percent', 5);
        $hrpPercent = $trip->margin_hrp_percent ?: (float) Setting::getValue('default_hrp_margin_percent', 10);
        $hctPercent = $trip->commission_hct_percent ?: (float) Setting::getValue('default_hct_commission_percent', 15);

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

        return $data;
    }
}
