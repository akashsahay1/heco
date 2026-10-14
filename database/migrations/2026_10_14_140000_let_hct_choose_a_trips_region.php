<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A trip's regions were only ever read off its experiences. A trip HCT builds
 * from custom days has none, so it said "No region yet", any valley's provider
 * could be pinned to it, and its regional partner was never invoiced. And a
 * region put on by hand was wiped the next time the experiences were synced,
 * because the sync could not tell it apart from one an experience had added.
 *
 * added_by tells them apart: 'hct' rows are HCT's choice and the sync leaves
 * them alone; 'system' rows (every row already saved) follow the experiences.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_regions', function (Blueprint $table) {
            $table->string('added_by', 12)->default('system')->after('hrp_id');
        });
    }

    public function down(): void
    {
        Schema::table('trip_regions', function (Blueprint $table) {
            $table->dropColumn('added_by');
        });
    }
};
