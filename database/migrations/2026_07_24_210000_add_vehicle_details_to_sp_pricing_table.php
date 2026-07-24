<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The provider app's "Add vehicle" screen asks for the things that identify an
 * actual vehicle — make/model, registration, year, photos — plus what the rate
 * does and does not cover. `sp_pricing` only carried the type, seats and driver
 * allowance, so a transport rate could not say which vehicle it was for.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->string('vehicle_make_model', 120)->nullable()->after('vehicle_type');
            $table->string('vehicle_registration_no', 40)->nullable()->after('vehicle_make_model');
            $table->unsignedSmallInteger('vehicle_year')->nullable()->after('vehicle_registration_no');
            // Stored paths, like experiences.gallery.
            $table->json('vehicle_photos')->nullable()->after('vehicle_year');
            // What the quoted rate covers. Nullable rather than defaulted so an
            // existing row reads as "not stated" instead of a claim nobody made.
            $table->boolean('driver_included')->nullable()->after('driver_allowance');
            $table->boolean('fuel_tolls_extra')->nullable()->after('driver_included');
        });
    }

    public function down(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->dropColumn([
                'vehicle_make_model', 'vehicle_registration_no', 'vehicle_year',
                'vehicle_photos', 'driver_included', 'fuel_tolls_extra',
            ]);
        });
    }
};
