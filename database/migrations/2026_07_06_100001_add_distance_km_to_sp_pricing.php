<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-km transport pricing (client req 3.1). A transport sp_pricing row now
 * carries a fixed route distance: admin enters distance_km once per route
 * (anchor → hotel), the provider sets the per-km rate in `price` with
 * unit = 'per km', and the calculator bills distance_km × price.
 * Nullable so non-per-km rows (per day / flat) are unaffected.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->decimal('distance_km', 8, 2)->nullable()->after('driver_allowance');
        });
    }

    public function down(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->dropColumn('distance_km');
        });
    }
};
