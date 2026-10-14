<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A day service's cost was one flat figure for the whole group.
 *
 * In the Excel CRM some lines are priced per head (a rafting ticket, a
 * monastery entry) and some for the whole party (a jeep, a porter). With only
 * a flat cost HCT had to multiply by the headcount by hand, and the figure
 * went stale the moment a traveller was added to or dropped from the trip.
 *
 * price_basis says which it is. Every row already saved stays 'flat', so
 * nothing already quoted changes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_day_services', function (Blueprint $table) {
            $table->string('price_basis', 12)->default('flat')->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('trip_day_services', function (Blueprint $table) {
            $table->dropColumn('price_basis');
        });
    }
};
