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
 * #18 — a traveller may only pin a provider rate that (a) belongs to the named
 * provider, (b) is the right service type, and (c) is approved + active.
 * Arbitrary / unapproved / mismatched pricing ids must be rejected.
 */
class PricingPinValidationTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected User $traveller;
    protected Trip $trip;
    protected ServiceProvider $provider;
    protected SpPricing $approved;
    protected SpPricing $pending;
    protected ServiceProvider $otherProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');

        $this->traveller = User::create([
            'full_name' => 'Trav', 'email' => 'trav@pin.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        $region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);
        $exp = Experience::create([
            'region_id' => $region->id, 'name' => 'Trek', 'slug' => 'trek', 'type' => 'nature',
            'short_description' => 'x', 'duration_type' => 'single_day', 'is_active' => true,
            'base_cost_per_person' => 5000, 'cost_accommodation' => 1000, 'cost_activities' => 1000,
        ]);
        $this->trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $this->traveller->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 2, 'start_date' => '2026-06-01', 'end_date' => '2026-06-03',
        ]);
        $day = TripDay::create(['trip_id' => $this->trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'added_by' => 'traveller']);
        TripDayExperience::create(['trip_day_id' => $day->id, 'experience_id' => $exp->id, 'cost_per_person' => 1000, 'sort_order' => 0]);

        $this->provider = ServiceProvider::create([
            'provider_type' => 'hlh', 'name' => 'Stay Co', 'email' => 's@pin.test',
            'phone_1' => '9990001111', 'region_id' => $region->id, 'status' => 'approved',
            'accommodation_categories' => ['Cat C - Standard'],
        ]);
        $this->otherProvider = ServiceProvider::create([
            'provider_type' => 'hlh', 'name' => 'Other Co', 'email' => 'o@pin.test',
            'phone_1' => '9990002222', 'region_id' => $region->id, 'status' => 'approved',
        ]);

        $base = [
            'service_provider_id' => $this->provider->id, 'service_type' => 'accommodation',
            'category' => 'Cat C - Standard', 'room_category' => 'Double', 'unit' => 'night',
            'price' => 2500, 'total_rooms' => 5, 'default_occupancy' => 2, 'is_active' => true,
        ];
        $this->approved = SpPricing::create($base + ['approval_status' => 'approved']);
        $this->pending  = SpPricing::create($base + ['approval_status' => 'pending']);
    }

    private function pin(int $providerId, int $pricingId)
    {
        return $this->actingAs($this->traveller)->post("http://{$this->portal}/ajax", [
            'update_travel_preferences' => 1, 'trip_id' => $this->trip->id,
            'accommodation_comfort' => 'Cat C - Standard',
            'accommodation_provider_id' => $providerId,
            'accommodation_pricing_id' => $pricingId,
        ]);
    }

    public function test_valid_pin_is_accepted(): void
    {
        $this->pin($this->provider->id, $this->approved->id)->assertStatus(200);
        $this->assertEquals($this->approved->id, $this->trip->fresh()->accommodation_pricing_id);
    }

    public function test_unapproved_pricing_is_rejected(): void
    {
        $this->pin($this->provider->id, $this->pending->id)->assertStatus(422);
    }

    public function test_pricing_from_wrong_provider_is_rejected(): void
    {
        $this->pin($this->otherProvider->id, $this->approved->id)->assertStatus(422);
    }
}
