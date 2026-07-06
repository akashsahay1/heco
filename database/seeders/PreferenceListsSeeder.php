<?php

namespace Database\Seeders;

use App\Models\SystemList;
use Illuminate\Database\Seeder;

/**
 * Seeds the "travel preference" system_lists types used by the portal + admin
 * dropdowns. accommodation_comfort / vehicle_comfort / guide_preference also
 * drive provider filtering (get_category_providers matches these strings).
 *
 * The option labels ARE the keys of $descriptions below — this seeder is the
 * single source of truth for them (the old CostCalculatorService multiplier map
 * was removed with the slab-pricing change).
 *
 * Idempotent — updateOrCreate on (list_type, name).
 */
class PreferenceListsSeeder extends Seeder
{
    public function run(): void
    {
        // The option labels for each list_type ARE the keys here; the values are
        // admin-facing descriptions of what each level means.
        $descriptions = [
            'accommodation_comfort' => [
                'Cat E - Camping/Tents'      => 'Wilderness camping in tents — sleeping bags + camp kitchen. Cheapest option (~0.5× standard rate). Best for treks where no built lodging exists.',
                'Cat D - Basic/Homestay'     => 'Village homestays or simple guesthouses with local hospitality, shared/private bathrooms. Authentic + cheap (~0.7× standard). Limited amenities.',
                'Cat C - Standard'           => 'Standard hotels or quality homestays — private bath, clean rooms, reliable hot water. The HECO default (1.0× rate). Most travellers pick this.',
                'Cat B - Comfort'            => 'Mid-upper hotels or boutique lodges — ensuite, room service, better food. ~1.5× standard rate.',
                'Cat A - Premium/Luxury'     => 'Premium 4-5 star hotels — full amenities, AC, room service, fine dining. ~2.5× standard rate. Niche, but available.',
            ],
            'vehicle_comfort' => [
                'Local Transport'            => 'Public bus / shared taxi / tuktuk. Adventure travellers only — comfortable for some, brutal for others. ~0.5× standard rate.',
                'SUV (Bolero/Scorpio)'       => 'Sturdy 4×4 for rough mountain roads. Less smooth than Innova but more capable on extreme terrain. ~0.8× standard.',
                'SUV (Innova/Crysta)'        => 'HECO default — comfortable 7-seater, good on highways + mountain roads. 1.0× rate.',
                'Premium (Fortuner/Similar)' => 'Top-tier SUV — Fortuner, Endeavour. Maximum comfort + status. ~1.5× standard.',
                'Tempo Traveller'            => '12-17 seater mini-bus for larger groups. Cheaper per head than two SUVs but less manoeuvrable. ~1.3× standard.',
            ],
            'guide_preference' => [
                'No Guide'                   => 'No guide — traveller self-navigates or uses experience-provided host only. Saves the guide cost entirely (0× rate).',
                'Local Guide'                => 'Local guide, basic English or via translator. Strong on local knowledge, lighter on language. ~0.7× standard guide rate.',
                'English-speaking'           => 'Fluent English guide — HECO default for international travellers. 1.0× rate.',
                'Certified/Expert'           => 'Trekking guide certified by IMF / AMG, or domain expert (botanist, photographer, historian). ~1.5× standard.',
            ],
            'travel_pace' => [
                'Relaxed'                    => 'Long sleep-ins, 1-2 activities per day, plenty of downtime. Suits families with kids, older travellers, or anyone wanting to soak it in. ~0.9× rate.',
                'Moderate'                   => 'HECO default — 2-3 activities per day, time to relax in between, reasonable wake-up times. 1.0× rate.',
                'Active'                     => 'Packed days — 3-4 activities, early starts, full days. Suits 25-45 active travellers. ~1.15× rate.',
                'Intensive'                  => 'Maximum pack-in — every daylight hour used, multiple cities/regions, fast transitions. Adventure-only. ~1.3× rate.',
            ],
            'budget_sensitivity' => [
                'Budget-friendly'            => 'Trip optimised for cost — Cat D/E accommodation, local transport, no premium services. ~0.85× total trip cost vs baseline.',
                'Mid-range'                  => 'HECO default — Cat C accommodation, Innova vehicle, English guide. 1.0× rate. Best value.',
                'Premium'                    => 'Premium services without extravagance — Cat B accommodation, top guides. ~1.25× rate.',
                'No Limit'                   => 'No cost constraint — best of everything. ~1.5× rate. Niche but real.',
            ],
        ];

        foreach ($descriptions as $listType => $options) {
            $sort = 0;
            foreach (array_keys($options) as $label) {
                SystemList::updateOrCreate(
                    ['list_type' => $listType, 'name' => $label],
                    [
                        'sort_order'  => $sort++,
                        'is_active'   => true,
                        'description' => $descriptions[$listType][$label] ?? null,
                    ]
                );
            }
        }
    }
}
