<?php

namespace App\Services;

use App\Models\ServiceProvider;
use App\Models\SpAvailability;
use App\Models\SpPricing;
use App\Models\SpRoomBooking;
use App\Models\Trip;
use App\Models\TripDayService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Per-room-category availability for SP accommodation rows.
 *
 * Layered on top of two tables:
 *   - sp_pricing       — defines categories + total_rooms per category
 *   - sp_availability  — whole-SP blocks (manual / iCal)
 *   - sp_room_bookings — per-category trip allocations
 *
 * All "available" math:
 *   if (SP has a whole-day block on D)  →  0 across all categories
 *   else                               →  total_rooms - active bookings on D
 */
class RoomAvailabilityService
{
    /**
     * Get the available count for a single (room category, date).
     */
    public function availableForCategory(int $spPricingId, string|Carbon $date): int
    {
        $row = SpPricing::find($spPricingId);
        if (!$row || $row->service_type !== 'accommodation' || !$row->total_rooms) {
            return 0;
        }
        $day = Carbon::parse($date)->startOfDay();

        if ($this->spIsBlockedOnDate($row->service_provider_id, $day)) {
            return 0;
        }

        $booked = (int) SpRoomBooking::where('sp_pricing_id', $spPricingId)
            ->whereDate('date', $day)
            ->active()
            ->sum('quantity');

        return max(0, ((int) $row->total_rooms) - $booked);
    }

    /**
     * Get availability for every accommodation category of an SP on a given date.
     *
     * @return Collection<array{sp_pricing_id:int, room_category:string, total:int, available:int, booked:int, rate:float, meal_plan:?string}>
     */
    public function categoriesForDate(int $spId, string|Carbon $date): Collection
    {
        $day = Carbon::parse($date)->startOfDay();
        $blocked = $this->spIsBlockedOnDate($spId, $day);

        return SpPricing::where('service_provider_id', $spId)
            ->where('service_type', 'accommodation')
            ->where('is_active', true)
            ->whereNotNull('total_rooms')
            ->orderBy('id')
            ->get()
            ->map(function (SpPricing $row) use ($day, $blocked) {
                $booked = $blocked ? (int) $row->total_rooms : (int) SpRoomBooking::where('sp_pricing_id', $row->id)
                    ->whereDate('date', $day)
                    ->active()
                    ->sum('quantity');
                return [
                    'sp_pricing_id' => $row->id,
                    'room_category' => $row->room_category ?: $row->category,
                    'comfort_tier'  => $row->comfort_tier,
                    'total'         => (int) $row->total_rooms,
                    'booked'        => $booked,
                    'available'     => max(0, ((int) $row->total_rooms) - $booked),
                    'rate'          => (float) $row->price,
                    'meal_plan'     => $row->meal_plan,
                ];
            });
    }

    /**
     * Find all SPs in a region with at least one accommodation category that
     * has rooms available across every date in the given range. Used by the
     * AI chat handler + experience detail page to surface real stay options.
     *
     * @return Collection<array> — one row per (sp × category) pair
     */
    public function stayOptionsForRegion(int $regionId, string|Carbon $start, string|Carbon $end, ?int $minPax = null): Collection
    {
        $startDay = Carbon::parse($start)->startOfDay();
        $endDay = Carbon::parse($end)->startOfDay();
        if ($endDay->lt($startDay)) $endDay = $startDay->copy();

        $sps = ServiceProvider::where('region_id', $regionId)
            ->where('status', 'approved')
            ->whereHas('pricing', fn($q) => $q->where('service_type', 'accommodation')->where('is_active', true)->whereNotNull('total_rooms'))
            ->get();

        $options = collect();
        foreach ($sps as $sp) {
            $categories = SpPricing::where('service_provider_id', $sp->id)
                ->where('service_type', 'accommodation')
                ->where('is_active', true)
                ->whereNotNull('total_rooms')
                ->orderBy('id')
                ->get();

            foreach ($categories as $row) {
                // Walk every date in range; the LOWEST available count is the binding
                // constraint (you can only book the smallest free slot across the stay).
                $minAvail = (int) $row->total_rooms;
                foreach (CarbonPeriod::create($startDay, $endDay) as $d) {
                    $minAvail = min($minAvail, $this->availableForCategory($row->id, $d));
                    if ($minAvail === 0) break;
                }
                if ($minAvail <= 0) continue;

                $options->push([
                    'sp_id'           => $sp->id,
                    'sp_name'         => $sp->name,
                    'sp_pricing_id'   => $row->id,
                    'room_category'   => $row->room_category ?: $row->category,
                    'rate_per_night'  => (float) $row->price,
                    'meal_plan'       => $row->meal_plan,
                    'rooms_available' => $minAvail,
                    'rooms_total'     => (int) $row->total_rooms,
                ]);
            }
        }
        return $options;
    }

    /**
     * Allocate N rooms of a category for a trip across one date.
     * Returns the booking row on success, or null if not enough rooms.
     * Idempotent on (sp_pricing_id, trip_id, trip_day_service_id, date) —
     * an existing held/confirmed row for the same trip-day-service is updated
     * instead of duplicated.
     */
    public function book(int $spPricingId, int $tripId, ?int $tripDayServiceId, string|Carbon $date, int $quantity = 1, string $status = 'held', string $source = 'trip_manager'): ?SpRoomBooking
    {
        $day = Carbon::parse($date)->startOfDay();
        $available = $this->availableForCategory($spPricingId, $day);
        // Ignore quantity already booked by THIS trip-day-service when checking.
        $existing = $tripDayServiceId
            ? SpRoomBooking::where('sp_pricing_id', $spPricingId)
                ->where('trip_day_service_id', $tripDayServiceId)
                ->whereDate('date', $day)
                ->active()
                ->first()
            : null;
        if ($existing) {
            $available += (int) $existing->quantity;
        }
        if ($quantity > $available) {
            return null;
        }

        $attrs = [
            'sp_pricing_id'       => $spPricingId,
            'trip_id'             => $tripId,
            'trip_day_service_id' => $tripDayServiceId,
            'date'                => $day,
        ];
        return SpRoomBooking::updateOrCreate($attrs, [
            'quantity' => $quantity,
            'status'   => $status,
            'source'   => $source,
        ]);
    }

    /**
     * Release every active booking for a given trip-day-service (used when the
     * service is removed from the trip or the SP is swapped).
     */
    public function releaseForTripDayService(int $tripDayServiceId): int
    {
        return SpRoomBooking::where('trip_day_service_id', $tripDayServiceId)
            ->active()
            ->update(['status' => 'released']);
    }

    /**
     * Release every active booking attached to a trip (used on trip cancel /
     * erase). Confirmed bookings are also marked released — we keep the row
     * for audit but free the rooms.
     */
    public function releaseForTrip(int $tripId): int
    {
        return SpRoomBooking::where('trip_id', $tripId)
            ->active()
            ->update(['status' => 'released']);
    }

    /**
     * Promote every active 'held' booking of a trip to 'confirmed'. Called
     * when trip status flips not_confirmed → confirmed.
     */
    public function confirmForTrip(int $tripId): int
    {
        return SpRoomBooking::where('trip_id', $tripId)
            ->where('status', 'held')
            ->update(['status' => 'confirmed']);
    }

    /**
     * Helper — is this SP fully blocked on this date via sp_availability?
     */
    protected function spIsBlockedOnDate(int $spId, Carbon $day): bool
    {
        return SpAvailability::where('service_provider_id', $spId)
            ->whereDate('date', $day)
            ->whereIn('status', ['blocked', 'booked'])
            ->exists();
    }
}
