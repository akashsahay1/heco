<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-category room bookings — one row per (room category × date × trip).
 *
 * Sits alongside the existing sp_availability table:
 *   - sp_availability  → whole-SP blocks (manual / iCal inbound sync).
 *                        Used when an SP is unavailable for the whole day
 *                        regardless of which room category.
 *   - sp_room_bookings → per-room-category allocations to specific trips.
 *                        Used when a trip books X rooms of category Y on date Z.
 *
 * Availability formula:
 *   available(category, date) =
 *       sp_pricing.total_rooms (for that category)
 *       - SUM(sp_room_bookings.quantity WHERE sp_pricing_id = X AND date = D
 *             AND status IN ('held','confirmed'))
 *       - (if sp_availability has a row for SP-wide block on D, all 0)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_room_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sp_pricing_id')->constrained('sp_pricing')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('trip_day_service_id')->nullable()->constrained('trip_day_services')->nullOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('quantity')->default(1);
            // held = pre-confirm (trip is not_confirmed)
            // confirmed = trip has been confirmed
            // released = booking voided (trip cancelled / SP swapped) — kept for audit
            $table->enum('status', ['held', 'confirmed', 'released'])->default('held');
            // Track who/what created the booking (trip_manager / ai / sp_self / manual).
            $table->string('source', 30)->default('trip_manager');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sp_pricing_id', 'date', 'status']);
            $table->index(['trip_id', 'status']);
            $table->index(['trip_day_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_room_bookings');
    }
};
