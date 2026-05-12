<?php

namespace Database\Seeders;

use App\Models\SystemList;
use App\Services\CostCalculatorService;
use Illuminate\Database\Seeder;

/**
 * Seeds the five "travel preference" system_lists types whose option labels
 * must mirror the keys of CostCalculatorService::getMultiplierMap() exactly
 * (the pricing engine matches stored trip column strings against those labels,
 * e.g. trips.accommodation_comfort = "Cat C - Standard").
 *
 * Types: accommodation_comfort, vehicle_comfort, guide_preference,
 *        travel_pace, budget_sensitivity.
 *
 * Idempotent — updateOrCreate on (list_type, name).
 */
class PreferenceListsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CostCalculatorService::getMultiplierMap() as $listType => $options) {
            $sort = 0;
            foreach (array_keys($options) as $label) {
                SystemList::updateOrCreate(
                    ['list_type' => $listType, 'name' => $label],
                    ['sort_order' => $sort++, 'is_active' => true]
                );
            }
        }
    }
}
