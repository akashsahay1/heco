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
 * A day-level provider service STACKS on top of the experience bundle — both are
 * charged. The experience is one slab-priced bundle (its trek-time stay is inside
 * that bundle, NOT on the accommodation line); the provider hotel is its own line.
 */
class DayProviderReplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_level_provider_stacks_on_experience_bundle(): void
    {
        $user = User::create([
            'full_name' => 'U', 'email' => 'u@dpr.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        $region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);
        // Experience bundles accommodation 1000 + activities 1000.
        $exp = Experience::create([
            'region_id' => $region->id, 'name' => 'Trek', 'slug' => 'trek', 'type' => 'nature',
            'short_description' => 'x', 'duration_type' => 'single_day', 'is_active' => true,
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

        // Experience bundle = base_cost_per_person 2000 x peopleFactor(1) = 2000
        // (no slabs configured -> falls back to base_cost_per_person).
        $this->assertSame(2000, (int) $breakdown['experience_cost']);
        // Accommodation line = the provider hotel only (4000); markup 0% => 4000.
        $this->assertSame(4000, (int) $breakdown['accommodation_cost']);
        $this->assertSame(4000, (int) $breakdown['accommodation_provider_cost']);
        // Trip cost = experience bundle 2000 + provider hotel 4000 = 6000.
        $this->assertSame(6000, (int) $breakdown['total_cost']);
    }
}
