<?php

use App\Models\SystemList;
use Illuminate\Database\Migrations\Migration;

/**
 * The experience form's remaining option sets were hardcoded — the day-card
 * inclusions in `portal/sp/experiences` JS and the seasons in its Blade, with a
 * second copy bundled in the provider app. Move them into `system_lists` like
 * every other dropdown so HCT edits them once and both surfaces follow.
 *
 * `experience_type` already lived here; it just was never exposed to the app.
 */
return new class extends Migration {
    /** `experience_days.inclusions` — what a single day of the itinerary covers. */
    private const DAY_INCLUSIONS = [
        'breakfast', 'lunch', 'dinner', 'snacks', 'accommodation', 'guide', 'transport',
    ];

    /** `experiences.best_seasons`. */
    private const BEST_SEASONS = ['spring', 'summer', 'monsoon', 'autumn', 'winter'];

    public function up(): void
    {
        foreach (['day_inclusion' => self::DAY_INCLUSIONS, 'best_season' => self::BEST_SEASONS] as $type => $names) {
            foreach ($names as $i => $name) {
                SystemList::firstOrCreate(
                    ['list_type' => $type, 'name' => $name],
                    ['is_active' => true, 'sort_order' => $i + 1],
                );
            }
        }
    }

    public function down(): void
    {
        SystemList::whereIn('list_type', ['day_inclusion', 'best_season'])->delete();
    }
};
