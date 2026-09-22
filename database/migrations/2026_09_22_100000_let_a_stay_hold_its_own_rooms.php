<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let an experiential stay hold rooms, the way a partner's hotel already can.
 *
 * sp_room_bookings was built around sp_pricing: a room category belonging to an
 * OSP, with its own total_rooms. A stay is not that. Its rooms live on the
 * experience itself (experiences.total_rooms) and its prices live in the
 * occupancy x meal-plan grid, which is pricing rather than inventory: three
 * rooms sold as "double" or "twin" are the same three rooms.
 *
 * So a stay holds against the EXPERIENCE, not against a rate. sp_pricing_id
 * becomes optional and experience_id joins it; exactly one of the two is set.
 *
 * Without this a stay could be sold to as many travellers as asked for it. The
 * trip confirmed, the host was invoiced, and nobody discovered the room was
 * gone until two parties arrived for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_room_bookings', function (Blueprint $table) {
            $table->foreignId('experience_id')->nullable()->after('sp_pricing_id')
                ->constrained('experiences')->cascadeOnDelete();
            $table->index(['experience_id', 'date', 'status']);
        });

        // A partner's room still names its category; a stay names its listing.
        Schema::table('sp_room_bookings', function (Blueprint $table) {
            $table->foreignId('sp_pricing_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sp_room_bookings', function (Blueprint $table) {
            $table->dropIndex(['experience_id', 'date', 'status']);
            $table->dropConstrainedForeignId('experience_id');
        });
    }
};
