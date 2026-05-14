<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend sp_pricing so a single row can represent the full operational
 * detail of any service type — including hotel-style room inventory
 * (total rooms of a category) for Accommodation rows.
 *
 * Accommodation rows now carry:  room_category + total_rooms + meal_plan
 *                                + default_occupancy
 * Transport rows now carry:      vehicle_capacity + driver_allowance
 * Activity rows now carry:       min_group + max_group + specialties
 * Guide rows now carry:          specialties
 *
 * Old columns (category, vehicle_type, meal_plan as free-text) remain for
 * backward compatibility; new typed columns are preferred going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            // Accommodation
            $table->string('room_category', 100)->nullable()->after('category');
            $table->unsignedSmallInteger('total_rooms')->nullable()->after('room_category');
            $table->string('default_occupancy', 50)->nullable()->after('total_rooms');
            // Transport
            $table->unsignedSmallInteger('vehicle_capacity')->nullable()->after('vehicle_type');
            $table->decimal('driver_allowance', 10, 2)->nullable()->after('vehicle_capacity');
            // Activity / Guide
            $table->unsignedSmallInteger('min_group')->nullable()->after('driver_allowance');
            $table->unsignedSmallInteger('max_group')->nullable()->after('min_group');
            $table->text('specialties')->nullable()->after('max_group');
        });
    }

    public function down(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->dropColumn([
                'room_category',
                'total_rooms',
                'default_occupancy',
                'vehicle_capacity',
                'driver_allowance',
                'min_group',
                'max_group',
                'specialties',
            ]);
        });
    }
};
