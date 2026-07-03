<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayExperience;
use App\Models\TripDayService;
use App\Models\User;
use App\Services\CostCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1 — a day-level provider assigned to a service must REPLACE the experience's
 * bundled component for that category, not stack on top of it (which would
 * double-charge the traveller: provider rate + bundled estimate).
 */
class DayProviderReplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_level_provider_replaces_bundled_component(): void
    {
        $user = User::create([
            'full_name' => 'U', 'email' => 'u@dpr.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        $region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);
        // Experience bundles accommodation 1000 + activities 1000.
        $exp = Experience::create([
            'region_id' => $region->id, 'name' => 'Trek', 'slug' => 'trek', 'type' => 'nature',
            'short_description' => 'x', 'duration_type' => 'days', 'is_active' => true,
            'base_cost_per_person' => 2000, 'cost_accommodation' => 1000, 'cost_activities' => 1000,
        ]);
        // adults=1 -> peopleFactor=1, no comfort prefs -> multipliers=1.0.
        $trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $user->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 1, 'children' => 0, 'infants' => 0,
            'start_date' => '2026-06-01', 'end_date' => '2026-06-02',
        ]);
        $day = TripDay::create(['trip_id' => $trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'added_by' => 'traveller']);
        TripDayExperience::create(['trip_day_id' => $day->id, 'experience_id' => $exp->id, 'cost_per_person' => 1000, 'sort_order' => 0]);
        // A real accommodation provider assigned to the day at ₹4000.
        TripDayService::create([
            'trip_day_id' => $day->id, 'service_type' => 'accommodation',
            'description' => 'Hotel', 'cost' => 4000, 'is_included' => true,
        ]);

        $breakdown = app(CostCalculatorService::class)->calculate($trip);

        // Accommodation = provider rate only (4000), NOT 4000 + bundled 1000.
        $this->assertSame(4000, (int) $breakdown['accommodation_cost']);
        // Activities still counted (1000). Total = 4000 + 1000 = 5000 (not 6000).
        $this->assertSame(5000, (int) $breakdown['total_cost']);
    }
}
