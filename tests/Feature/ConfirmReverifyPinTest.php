<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SpPricing;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayExperience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #16 — confirming a trip must re-verify pinned provider rates. A rate that was
 * valid when pinned but has since been unapproved/deactivated must block confirm.
 */
class ConfirmReverifyPinTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected User $traveller;
    protected Trip $trip;
    protected SpPricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');

        $this->traveller = User::create([
            'full_name' => 'Trav', 'email' => 'trav@cf.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        $region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);
        $exp = Experience::create([
            'region_id' => $region->id, 'name' => 'Trek', 'slug' => 'trek', 'type' => 'nature',
            'short_description' => 'x', 'duration_type' => 'single_day', 'is_active' => true,
            'base_cost_per_person' => 5000, 'cost_accommodation' => 1000, 'cost_activities' => 1000,
        ]);
        $provider = ServiceProvider::create([
            'provider_type' => 'hlh', 'name' => 'Stay', 'email' => 's@cf.test',
            'phone_1' => '9990001111', 'region_id' => $region->id, 'status' => 'approved',
        ]);
        $this->pricing = SpPricing::create([
            'service_provider_id' => $provider->id, 'service_type' => 'accommodation',
            'category' => 'Cat C - Standard', 'room_category' => 'Double', 'unit' => 'night',
            'price' => 2500, 'total_rooms' => 5, 'default_occupancy' => 2, 'is_active' => true,
            'approval_status' => 'approved',
        ]);
        // Trip with the rate pinned (was valid at pin time) and an itinerary.
        $this->trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $this->traveller->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 2, 'start_date' => '2026-06-01', 'end_date' => '2026-06-03',
            'accommodation_provider_id' => $provider->id,
            'accommodation_pricing_id' => $this->pricing->id,
        ]);
        $day = TripDay::create(['trip_id' => $this->trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'added_by' => 'traveller']);
        TripDayExperience::create(['trip_day_id' => $day->id, 'experience_id' => $exp->id, 'cost_per_person' => 1000, 'sort_order' => 0]);
    }

    private function confirm()
    {
        return $this->actingAs($this->traveller)
            ->post("http://{$this->portal}/ajax", ['confirm_trip' => 1, 'trip_id' => $this->trip->id]);
    }

    public function test_confirm_with_valid_pin_succeeds(): void
    {
        $this->confirm()->assertStatus(200);
        $this->assertSame('confirmed', $this->trip->fresh()->status);
    }

    public function test_confirm_blocked_when_pinned_rate_deactivated(): void
    {
        $this->pricing->update(['is_active' => false]);
        $this->confirm()->assertStatus(422);
        $this->assertSame('not_confirmed', $this->trip->fresh()->status);
    }
}
