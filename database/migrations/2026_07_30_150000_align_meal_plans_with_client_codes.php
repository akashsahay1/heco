<?php

use App\Models\SystemList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Meal plans become the client's four: EP, CP, MAP, AP.
 *
 * Both of the client's documents say the same thing. The letter asks for
 * "Prices (Single / Double / Triple on EP / CP / MAP / AP)", and the
 * data-collection PDF draws the accommodation grid with exactly those four as
 * its columns. What we had was a six-entry hotel-industry list (No meals, BB,
 * HB, FB, MAP, AP) that answered a different question.
 *
 * Three vocabularies had grown up in the data — the list's own values, the
 * app's local fallback ("All meals", "Breakfast only") and older free text
 * ("Breakfast included"). All of them map onto the four, so existing rates keep
 * meaning what they meant instead of being silently orphaned by the rename.
 *
 * The code leads each name because that is how the client's own pricing table
 * is labelled; the words after it are for the host filling the form in, who
 * should not have to know what a European Plan is.
 */
return new class extends Migration {
    private const PLANS = [
        'EP - Room only' => 'European Plan. The room alone — the traveller arranges their own food.',
        'CP - With breakfast' => 'Continental Plan. Room and breakfast.',
        'MAP - Breakfast + one meal' => 'Modified American Plan. Room, breakfast and either lunch or dinner.',
        'AP - All meals' => 'American Plan. Room with breakfast, lunch and dinner.',
    ];

    /** Everything ever stored, and which of the four it becomes. */
    private const MAPPING = [
        'No meals' => 'EP - Room only',
        'Room only' => 'EP - Room only',
        'EP' => 'EP - Room only',
        'BB - Breakfast only' => 'CP - With breakfast',
        'Breakfast only' => 'CP - With breakfast',
        'Breakfast included' => 'CP - With breakfast',
        'CP' => 'CP - With breakfast',
        'HB - Half Board' => 'MAP - Breakfast + one meal',
        'Half Board' => 'MAP - Breakfast + one meal',
        'MAP - Modified American Plan' => 'MAP - Breakfast + one meal',
        'MAP' => 'MAP - Breakfast + one meal',
        'FB - Full Board' => 'AP - All meals',
        'Full Board' => 'AP - All meals',
        'AP - All Inclusive' => 'AP - All meals',
        'All meals' => 'AP - All meals',
        'AP' => 'AP - All meals',
    ];

    public function up(): void
    {
        // Rates first: while the old options still exist, nothing is orphaned
        // if this migration is interrupted half way.
        foreach (self::MAPPING as $old => $new) {
            DB::table('sp_pricing')->where('meal_plan', $old)->update(['meal_plan' => $new]);
            DB::table('experience_room_rates')->where('meal_plan', $old)->update(['meal_plan' => $new]);
        }

        SystemList::where('list_type', 'meal_plan')->delete();

        $order = 0;
        foreach (self::PLANS as $name => $description) {
            SystemList::create([
                'list_type' => 'meal_plan',
                'name' => $name,
                'description' => $description,
                'sort_order' => $order++,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        SystemList::where('list_type', 'meal_plan')->delete();

        $previous = [
            'No meals' => 'No meals included. Traveller pays for food separately.',
            'BB - Breakfast only' => 'Bed and Breakfast. Only morning meal included.',
            'HB - Half Board' => 'Breakfast and one main meal (usually dinner). Lunch is on the traveller.',
            'FB - Full Board' => 'All three meals — breakfast, lunch, dinner.',
            'MAP - Modified American Plan' => 'Breakfast + lunch OR dinner (traveller picks).',
            'AP - All Inclusive' => 'All meals + snacks + non-alcoholic drinks included.',
        ];

        $order = 0;
        foreach ($previous as $name => $description) {
            SystemList::create([
                'list_type' => 'meal_plan',
                'name' => $name,
                'description' => $description,
                'sort_order' => $order++,
                'is_active' => true,
            ]);
        }

        // The four cannot be told apart from the six they replaced — CP could
        // have been BB, AP could have been FB — so stored rates keep the new
        // names rather than being guessed back into the old ones.
    }
};
