<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room pricing for the "Experiential accommodation" category.
 *
 * That category is the one the experiences table had no home for: the
 * data-collection PDF gives it "Capacity (no of rooms, no guests)" and a
 * "Pricing table (single, double, triple, meal plans)", which is a grid, not the
 * single per-person price the other two categories use.
 *
 * The user confirmed it stays an EXPERIENCE rather than becoming sp_pricing
 * rows — an HLH's remote homestay is a curated stay, not an OSP's hotel room —
 * so the grid hangs off the experience.
 *
 * Capacity is stored as the two numbers the client asked for — rooms AND
 * guests. `group_size_max` was considered for the guest count and rejected:
 * that is the largest party this experience will take, while capacity is how
 * many the place sleeps. They coincide for a small homestay and diverge as soon
 * as a host takes two bookings at once, so collapsing them would quietly lose a
 * distinction the host actually made.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_rooms')->nullable()->after('accommodation_category');
            $table->unsignedSmallInteger('total_guests')->nullable()->after('total_rooms');
        });

        Schema::create('experience_room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            // From the room_category and meal_plan SystemLists, so HCT can edit
            // the options without a deploy.
            $table->string('occupancy');
            $table->string('meal_plan');
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // One price per occupancy + meal plan. Two rows for the same pair
            // would leave the grid with no answer for that cell.
            $table->unique(['experience_id', 'occupancy', 'meal_plan'], 'exp_room_rate_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_room_rates');

        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn(['total_rooms', 'total_guests']);
        });
    }
};
