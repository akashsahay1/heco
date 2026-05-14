<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a Trip Day Service is an Accommodation pulled from a specific SP room
 * category (e.g. Mountain View — Double Room × 2), the service row needs to
 * point at the originating sp_pricing row and carry the room quantity.
 *
 * Both columns are nullable so:
 *   - Existing non-accommodation services (transport / guide / activity) keep
 *     working without change.
 *   - Legacy accommodation services without a specific room category remain
 *     valid; they just won't allocate from sp_room_bookings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_day_services', function (Blueprint $table) {
            $table->foreignId('sp_pricing_id')->nullable()->after('service_provider_id')
                ->constrained('sp_pricing')->nullOnDelete();
            $table->unsignedSmallInteger('room_quantity')->nullable()->after('sp_pricing_id');
            $table->index('sp_pricing_id');
        });
    }

    public function down(): void
    {
        Schema::table('trip_day_services', function (Blueprint $table) {
            $table->dropForeign(['sp_pricing_id']);
            $table->dropColumn(['sp_pricing_id', 'room_quantity']);
        });
    }
};
