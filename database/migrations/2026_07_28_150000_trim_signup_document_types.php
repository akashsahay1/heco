<?php

use App\Models\SystemList;
use Illuminate\Database\Migrations\Migration;

/**
 * The signup document step asked for a business licence and liability
 * insurance. The client dropped both: most members are individuals with
 * something to share rather than registered businesses, so demanding company
 * paperwork turns away exactly the people HECO is trying to onboard.
 *
 * Deactivated rather than deleted — providers who already uploaded one keep a
 * label for their stored file, and HCT can re-enable either from the control
 * panel if a region ever needs them.
 */
return new class extends Migration {
    private const DROPPED = ['Business license', 'Liability insurance'];

    public function up(): void
    {
        SystemList::where('list_type', 'document_type')
            ->whereIn('name', self::DROPPED)
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        SystemList::where('list_type', 'document_type')
            ->whereIn('name', self::DROPPED)
            ->update(['is_active' => true]);
    }
};
