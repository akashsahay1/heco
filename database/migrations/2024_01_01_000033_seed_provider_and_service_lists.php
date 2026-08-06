<?php

use App\Models\SystemList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The dropdown options a signup form cannot work without.
 *
 * These arrived one at a time, each riding along with the migration that added
 * the column it fills. Those migrations were folded into the create-table files
 * they belonged to; the seeding could not go with them, because a CREATE TABLE
 * has nowhere to put rows. It lives here instead.
 *
 * firstOrCreate throughout: HCT edits these lists from the control panel, and a
 * re-run must not undo their edits or duplicate what is already there.
 */
return new class extends Migration
{
    private const LISTS = [
        'business_type' => [
            'Sole proprietor',
            'Registered company',
            'Partnership',
            'Cooperative',
            'Informal / individual',
        ],
        'education_level' => [
            'No formal schooling',
            'Primary school',
            'Higher secondary',
            'Diploma / ITI',
            "Bachelor's degree",
            "Master's degree or above",
            'Other',
        ],
        'english_level' => [
            'None',
            'Basic',
            'Conversational',
            'Fluent',
        ],
        'computer_skill_level' => [
            'None',
            'Basic',
            'Intermediate',
            'Advanced',
        ],
        // What a member can offer, split by the role that offers it.
        'experience_category' => [
            'Experiential accommodation',
            'Guided Cultural & Outdoor Activities',
            'Workshops, Handicrafts, Local Knowledge & Storytelling',
        ],
        'service_category' => [
            'Standard accommodation',
            'Taxi services',
            'Guide',
            'Rental',
            'Other services',
        ],
        // "Other languages (from a list)" — a list, not free text, so HCT can
        // extend it from the control panel like every other one.
        'language' => [
            'English', 'Hindi', 'French', 'German', 'Spanish', 'Italian',
            'Russian', 'Japanese', 'Mandarin', 'Hebrew', 'Nepali',
        ],
    ];

    public function up(): void
    {
        foreach (self::LISTS as $listType => $names) {
            foreach ($names as $i => $name) {
                SystemList::firstOrCreate(
                    ['list_type' => $listType, 'name' => $name],
                    ['is_active' => true, 'sort_order' => ($i + 1) * 10],
                );
            }
        }

        // A service the client's document asks for and the list never had.
        SystemList::firstOrCreate(
            ['list_type' => 'service_type', 'name' => 'Rental'],
            [
                'description' => 'Equipment or gear hired out by the day.',
                'is_active' => true,
                'sort_order' => 60,
            ],
        );

        // The markup applied when a provider carries none of their own.
        DB::table('settings')->updateOrInsert(
            ['key' => 'default_provider_markup_percent'],
            ['value' => '0', 'group' => 'financial'],
        );
    }

    public function down(): void
    {
        foreach (self::LISTS as $listType => $names) {
            SystemList::where('list_type', $listType)->whereIn('name', $names)->delete();
        }

        SystemList::where('list_type', 'service_type')->where('name', 'Rental')->delete();
        DB::table('settings')->where('key', 'default_provider_markup_percent')->delete();
    }
};
