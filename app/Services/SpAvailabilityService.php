<?php

namespace App\Services;

use App\Models\SpAvailability;
use App\Models\ServiceProvider;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class SpAvailabilityService
{
    /**
     * Check if an SP is available for an entire date range.
     */
    public function isAvailable(int $spId, string|Carbon $start, string|Carbon $end): bool
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        return !SpAvailability::where('service_provider_id', $spId)
            ->whereBetween('date', [$start, $end])
            ->exists();
    }

    /**
     * Check if an SP is available on a single date.
     */
    public function isAvailableOnDate(int $spId, string|Carbon $date): bool
    {
        return !SpAvailability::where('service_provider_id', $spId)
            ->where('date', Carbon::parse($date)->startOfDay())
            ->exists();
    }

    /**
     * Find SPs available for an entire date range, optionally filtered by region and service type.
     */
    public function findAvailableSps(string|Carbon $start, string|Carbon $end, ?int $regionId = null, ?string $serviceType = null): Collection
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        $query = ServiceProvider::where('status', 'approved')
            ->whereDoesntHave('availability', function ($q) use ($start, $end) {
                $q->whereBetween('date', [$start, $end]);
            });

        if ($regionId) {
            $query->where('region_id', $regionId);
        }

        if ($serviceType) {
            $query->whereJsonContains('services_offered', $serviceType);
        }

        return $query->get();
    }

    /**
     * Find SPs available on a single date.
     */
    public function findAvailableForDate(string|Carbon $date, ?int $regionId = null, ?string $serviceType = null): Collection
    {
        return $this->findAvailableSps($date, $date, $regionId, $serviceType);
    }

    /**
     * Manually block dates for an SP.
     */
    public function blockDates(int $spId, array $dates, ?string $notes = null): int
    {
        $count = 0;
        foreach ($dates as $date) {
            SpAvailability::updateOrCreate(
                ['service_provider_id' => $spId, 'date' => Carbon::parse($date)->startOfDay()],
                ['status' => 'blocked', 'source' => 'manual', 'notes' => $notes]
            );
            $count++;
        }
        return $count;
    }

    /**
     * Manually unblock dates (only manual blocks can be unblocked this way).
     */
    public function unblockDates(int $spId, array $dates): int
    {
        return SpAvailability::where('service_provider_id', $spId)
            ->where('source', 'manual')
            ->whereIn('date', array_map(fn($d) => Carbon::parse($d)->startOfDay(), $dates))
            ->delete();
    }

    /**
     * Book an SP for a trip service on a specific date. Throws on conflict.
     */
    public function bookForTrip(int $spId, int $tripId, int $tripDayServiceId, string|Carbon $date): SpAvailability
    {
        $date = Carbon::parse($date)->startOfDay();

        $existing = SpAvailability::where('service_provider_id', $spId)
            ->where('date', $date)
            ->first();

        if ($existing) {
            throw new \RuntimeException(
                "SP #{$spId} is not available on {$date->format('Y-m-d')}: already {$existing->status} ({$existing->source})"
            );
        }

        return SpAvailability::create([
            'service_provider_id' => $spId,
            'date' => $date,
            'status' => 'booked',
            'source' => 'trip_assignment',
            'trip_id' => $tripId,
            'trip_day_service_id' => $tripDayServiceId,
        ]);
    }

    /**
     * Release a booking by trip_day_service_id.
     */
    public function releaseBooking(int $tripDayServiceId): int
    {
        return SpAvailability::where('trip_day_service_id', $tripDayServiceId)
            ->where('source', 'trip_assignment')
            ->delete();
    }

    /**
     * Get availability data for a month (for calendar display).
     */
    public function getMonthCalendar(int $spId, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $blocks = SpAvailability::where('service_provider_id', $spId)
            ->whereBetween('date', [$start, $end])
            ->get();

        $calendar = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $dateStr = $day->format('Y-m-d');
            $block = $blocks->firstWhere('date', $day->startOfDay());
            $calendar[$dateStr] = $block ? [
                'status' => $block->status,
                'source' => $block->source,
                'trip_id' => $block->trip_id,
                'notes' => $block->notes,
            ] : ['status' => 'available'];
        }

        return $calendar;
    }
}
