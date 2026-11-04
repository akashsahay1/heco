<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A trip HCT is still building can be kept from the traveller.
 *
 * HECO asked for a button that shows or hides a custom itinerary, so the team
 * can build and revise it privately and show it only when it is ready
 * (feedback, 2026-10-28). Every trip that exists stays visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->boolean('itinerary_hidden')->default(false)->after('stage');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('itinerary_hidden');
        });
    }
};
