<?php

namespace App\Services;

use App\Models\ServiceProvider;
use App\Models\SpAvailability;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IcalSyncService
{
    /**
     * Sync a single provider's iCal feed.
     * Returns count of blocked dates imported.
     */
    public function syncProvider(ServiceProvider $sp): int
    {
        if (!$sp->ical_url) {
            throw new \RuntimeException("No iCal URL configured for SP #{$sp->id}");
        }

        $response = Http::timeout(30)->get($sp->ical_url);
        if (!$response->successful()) {
            throw new \RuntimeException("Failed to fetch iCal feed: HTTP {$response->status()}");
        }

        $events = $this->parseIcal($response->body());

        // Remove all existing iCal blocks for this SP
        SpAvailability::where('service_provider_id', $sp->id)
            ->where('source', 'ical')
            ->delete();

        $count = 0;
        foreach ($events as $event) {
            $dates = $this->expandDateRange($event['start'], $event['end']);
            foreach ($dates as $date) {
                // Don't overwrite manual blocks or trip bookings
                $existing = SpAvailability::where('service_provider_id', $sp->id)
                    ->where('date', $date)
                    ->first();

                if (!$existing) {
                    SpAvailability::create([
                        'service_provider_id' => $sp->id,
                        'date' => $date,
                        'status' => 'blocked',
                        'source' => 'ical',
                        'ical_uid' => $event['uid'] ?? null,
                        'notes' => $event['summary'] ?? 'iCal booking',
                    ]);
                    $count++;
                }
            }
        }

        $sp->update(['ical_last_synced_at' => now()]);

        return $count;
    }

    /**
     * Sync all providers that have an iCal URL.
     */
    public function syncAll(): array
    {
        $providers = ServiceProvider::whereNotNull('ical_url')
            ->where('ical_url', '!=', '')
            ->get();

        $results = [];
        foreach ($providers as $sp) {
            try {
                $count = $this->syncProvider($sp);
                $results[$sp->id] = ['name' => $sp->name, 'synced' => $count, 'error' => null];
            } catch (\Exception $e) {
                Log::warning("iCal sync failed for SP #{$sp->id}: " . $e->getMessage());
                $results[$sp->id] = ['name' => $sp->name, 'synced' => 0, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Simple iCal parser - extracts VEVENT blocks with DTSTART, DTEND, UID, SUMMARY.
     */
    protected function parseIcal(string $icalData): array
    {
        $events = [];
        $lines = preg_split('/\r?\n/', $icalData);
        $inEvent = false;
        $event = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $event = [];
                continue;
            }

            if ($line === 'END:VEVENT') {
                $inEvent = false;
                if (!empty($event['start'])) {
                    // If no end date, assume single day
                    if (empty($event['end'])) {
                        $event['end'] = $event['start'];
                    }
                    $events[] = $event;
                }
                continue;
            }

            if (!$inEvent) continue;

            if (str_starts_with($line, 'DTSTART')) {
                $event['start'] = $this->parseIcalDate($line);
            } elseif (str_starts_with($line, 'DTEND')) {
                $event['end'] = $this->parseIcalDate($line);
            } elseif (str_starts_with($line, 'UID:')) {
                $event['uid'] = substr($line, 4);
            } elseif (str_starts_with($line, 'SUMMARY:')) {
                $event['summary'] = substr($line, 8);
            }
        }

        return $events;
    }

    /**
     * Parse an iCal date line into a Carbon date.
     * Handles: DTSTART;VALUE=DATE:20260301, DTSTART:20260301T120000Z, etc.
     */
    protected function parseIcalDate(string $line): ?Carbon
    {
        // Extract the value after the last colon
        $parts = explode(':', $line);
        $value = end($parts);

        if (!$value) return null;

        // Remove trailing Z
        $value = rtrim($value, 'Z');

        try {
            if (strlen($value) === 8) {
                // Date only: 20260301
                return Carbon::createFromFormat('Ymd', $value)->startOfDay();
            } elseif (strlen($value) >= 15) {
                // DateTime: 20260301T120000
                return Carbon::createFromFormat('Ymd\THis', substr($value, 0, 15))->startOfDay();
            }
        } catch (\Exception $e) {
            Log::debug("Failed to parse iCal date: {$line}");
        }

        return null;
    }

    /**
     * Expand a date range into individual dates.
     * For booking.com iCal, DTEND is the checkout date (exclusive).
     */
    protected function expandDateRange(Carbon $start, Carbon $end): array
    {
        // iCal DTEND is exclusive for DATE values
        $endExclusive = $end->copy()->subDay();
        if ($endExclusive->lt($start)) {
            $endExclusive = $start->copy();
        }

        $dates = [];
        foreach (CarbonPeriod::create($start, $endExclusive) as $date) {
            $dates[] = $date->startOfDay();
        }

        return $dates;
    }
}
