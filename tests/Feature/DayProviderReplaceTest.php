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
 * Provider services and experience components are DIFFERENT real-world segments
 * for accommodation & transport (hotel vs trek-time stay; anchor→hotel vs
 * hotel→trek), so a day-level provider STACKS on top of the experience component
 * — both are charged, shown as separate lines. (It does NOT replace it.)
 */
class DayProviderReplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_level_provider_stacks_on_bundled_component(): void
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

        // Accommodation = experience trek-stay (1000) + provider hotel (4000) = 5000.
        $this->assertSame(5000, (int) $breakdown['accommodation_cost']);
        // + activities 1000 => trip cost 6000 (both accommodation segments charged).
        $this->assertSame(6000, (int) $breakdown['total_cost']);
    }
}
