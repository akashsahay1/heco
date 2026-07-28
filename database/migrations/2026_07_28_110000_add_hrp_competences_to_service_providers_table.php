<?php

use App\Models\SystemList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRP competences.
 *
 * An HRP sells nothing — no experiences, no rate card — so where an HLH has a
 * catalogue and an OSP has a price list, a regional partner has a profile. The
 * client's data-collection spec calls it "Competences" and asks for five things:
 * education background, English level and computer skills, work experiences,
 * dedication to social/environmental causes, and understanding of the local
 * community.
 *
 * Education and the two skill levels are stored as list-backed values so HCT can
 * compare partners when deciding who to put on a region. The last two are free
 * text on purpose: they are qualitative, and a self-assigned rating on "how
 * dedicated are you" would tell HCT nothing.
 */
return new class extends Migration {
    private const LISTS = [
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
    ];

    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->string('education_level')->nullable()->after('activity_types');
            $table->text('education_notes')->nullable()->after('education_level');
            $table->string('english_level')->nullable()->after('education_notes');
            $table->string('computer_skill_level')->nullable()->after('english_level');
            // A list of roles rather than one blob — "work experiences" is plural
            // in the spec, and HCT reads them one by one.
            $table->json('work_experience')->nullable()->after('computer_skill_level');
            $table->text('causes_note')->nullable()->after('work_experience');
            $table->text('community_note')->nullable()->after('causes_note');
        });

        foreach (self::LISTS as $listType => $names) {
            foreach ($names as $i => $name) {
                SystemList::firstOrCreate(
                    ['list_type' => $listType, 'name' => $name],
                    ['is_active' => true, 'sort_order' => $i + 1],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn([
                'education_level', 'education_notes', 'english_level',
                'computer_skill_level', 'work_experience', 'causes_note',
                'community_note',
            ]);
        });

        SystemList::whereIn('list_type', array_keys(self::LISTS))->delete();
    }
};
