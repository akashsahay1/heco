<?php

use App\Models\SystemList;
use Illuminate\Database\Migrations\Migration;

/**
 * The four verification documents the design's signup step 6 asks for. Kept in
 * `system_lists` like every other option set so HCT can rename or extend them
 * without a release.
 */
return new class extends Migration {
    private const DOCUMENT_TYPES = [
        'Government ID',
        'Business license',
        'Liability insurance',
        'Profile photo',
    ];

    public function up(): void
    {
        foreach (self::DOCUMENT_TYPES as $i => $name) {
            SystemList::firstOrCreate(
                ['list_type' => 'document_type', 'name' => $name],
                ['is_active' => true, 'sort_order' => $i + 1],
            );
        }
    }

    public function down(): void
    {
        SystemList::where('list_type', 'document_type')->delete();
    }
};
