<?php

namespace Database\Seeders;

use App\Models\SystemList;
use Illuminate\Database\Seeder;

/**
 * Seeds the right-sidebar travel preference dropdowns into system_lists.
 * Values mirror the strings that were previously hardcoded in
 * resources/views/portal/homepage.blade.php so existing trips' stored
 * values stay valid (e.g. trips.accommodation_comfort = "Cat C - Standard"
 * still finds a matching option).
 *
 * Idempotent — uses firstOrCreate so re-running won't duplicate rows.
 */
class PreferenceListsSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            'accommodation_comfort' => [
                'Cat E - Camping/Tents',
                'Cat D - Basic/Homestay',
                'Cat C - Standard',
                'Cat B - Comfort',
                'Cat A - Premium/Luxury',
            ],
            'vehicle_comfort' => [
                'Local Transport',
                'SUV (Bolero/Scorpio)',
                'SUV (Innova/Crysta)',
                'Premium (Fortuner/Similar)',
                'Tempo Traveller',
            ],
            'guide_preference' => [
                'No Guide',
                'Local Guide',
                'English-speaking',
                'Certified/Expert',
            ],
            'travel_pace' => [
                'Relaxed',
                'Moderate',
                'Active',
                'Intensive',
            ],
            'budget_sensitivity' => [
                'Budget-friendly',
                'Mid-range',
                'Premium',
                'No Limit',
            ],
        ];

        foreach ($lists as $type => $items) {
            foreach ($items as $idx => $name) {
                SystemList::firstOrCreate(
                    ['list_type' => $type, 'name' => $name],
                    ['is_active' => 1, 'sort_order' => $idx]
                );
            }
        }
    }
}
