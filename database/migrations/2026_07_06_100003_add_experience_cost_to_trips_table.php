<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The experience (trek) bundle is now a single slab-priced line (req 3.2),
 * separate from the provider hotel/transport/guide add-on lines. Store it so
 * the trip-manager / pricing summary can show "Experiences" distinctly.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('experience_cost', 12, 2)->default(0)->after('total_cost');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('experience_cost');
        });
    }
};
