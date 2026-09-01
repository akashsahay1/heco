<?php

use App\Models\SystemList;
use Illuminate\Database\Migrations\Migration;

/**
 * One list was doing three jobs, and every form that read it showed the wrong
 * two thirds.
 *
 * `occupancy_unit` holds how a room is sold — per single, per double — beside
 * how a price is charged — per km, per day, per group. The provider app fed
 * the whole of it to every picker, so a member choosing how their room is
 * normally sold was offered "per km", and one pricing a vehicle was offered
 * "per double". The portal's own form got this right by hardcoding the right
 * options into the page instead, which is how the two surfaces came to
 * disagree, and is not where option lists belong.
 *
 * So the jobs are separated and each gets its own list, the portal's own
 * options being the ones HCT already settled on. `occupancy_unit` is left
 * exactly as it is: every value here is spelled as it is spelled there, so a
 * rate card already saying "per day" still reads correctly.
 */
return new class extends Migration {
    private const LISTS = [
        // How a room is sold. Nothing about price.
        'room_occupancy' => [
            ['per single', 'One person in the room. The highest rate per head.'],
            ['per double', 'Two people sharing the room. The usual for a couple.'],
            ['per triple', 'Three people sharing one room.'],
            ['per quad',   'Four sharing — a family room, or a dormitory.'],
            ['per room',   'A flat rate for the room, whatever the number of people in it.'],
        ],
        // What a vehicle's price is measured in.
        'transport_unit' => [
            ['per km',   'Billed by the distance driven.'],
            ['per day',  'A flat rate for the day, whatever the distance.'],
            ['per trip', 'A flat rate for the whole journey, there and back.'],
        ],
        // What an activity's price is measured in.
        'activity_unit' => [
            ['per person',          'Each traveller pays this.'],
            ['per group',           'One rate for the whole group, whatever its size.'],
            ['per day',             'Charged by the day.'],
            ['per person per day',  'Each traveller pays this for each day.'],
        ],
    ];

    public function up(): void
    {
        foreach (self::LISTS as $type => $values) {
            foreach ($values as $i => [$name, $description]) {
                SystemList::firstOrCreate(
                    ['list_type' => $type, 'name' => $name],
                    [
                        'description' => $description,
                        'is_active' => true,
                        'sort_order' => ($i + 1) * 10,
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        foreach (self::LISTS as $type => $values) {
            SystemList::where('list_type', $type)
                ->whereIn('name', array_column($values, 0))
                ->delete();
        }
    }
};
