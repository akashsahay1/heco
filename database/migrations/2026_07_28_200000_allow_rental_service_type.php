<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Rental" is a service the client's document asks for, and the column would
 * not hold it: service_type was an enum, which MySQL enforces as a list and
 * SQLite as a CHECK constraint, so a rental row was rejected outright.
 *
 * A plain string keeps both engines happy and leaves the allowed values where
 * they can be read and extended — the service_type system list and the
 * handler's own validation rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->string('service_type', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->enum('service_type', [
                'accommodation', 'transport', 'guide', 'activity', 'meal', 'other',
            ])->change();
        });
    }
};
