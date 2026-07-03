<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SpPricing;
use App\Models\Trip;
use App\Models\TripSelectedExperience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guide is EXCLUSIVE — if the selected experience already provides a guide
 * (cost_guide > 0) a guide provider cannot be pinned on top; when the experience
 * has no guide (cost_guide = 0) pinning a guide provider is allowed.
 */
class GuideExclusiveTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected User $traveller;
    protected ServiceProvider $provider;
    protected SpPricing $guidePricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        $this->traveller = User::create([
            'full_name' => 'T', 'email' => 't@guide.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        $region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);
        $this->provider = ServiceProvider::create([
            'provider_type' => 'osp', 'name' => 'Guides Co', 'email' => 'g@guide.test',
            'phone_1' => '9990001111', 'region_id' => $region->id, 'status' => 'approved',
        ]);
        $this->guidePricing = SpPricing::create([
            'service_provider_id' => $this->provider->id, 'service_type' => 'guide',
            'unit' => 'day', 'price' => 1500, 'is_active' => true, 'approval_status' => 'approved',
        ]);
        $this->region = $region;
    }

    protected Region $region;

    private function pinGuide(Trip $trip)
    {
        return $this->actingAs($this->traveller)->post("http://{$this->portal}/ajax", [
            'update_travel_preferences' => 1, 'trip_id' => $trip->id,
            'guide_preference' => 'Local Guide',
            'guide_provider_id' => $this->provider->id,
            'guide_pricing_id' => $this->guidePricing->id,
        ]);
    }

    private function tripWithGuideCost(float $costGuide): Trip
    {
        $exp = Experience::create([
            'region_id' => $this->region->id, 'name' => 'Trek ' . $costGuide, 'slug' => 'trek-' . $costGuide,
            'type' => 'nature', 'short_description' => 'x', 'duration_type' => 'days', 'is_active' => true,
            'base_cost_per_person' => 5000, 'cost_activities' => 3000, 'cost_guide' => $costGuide,
        ]);
        $trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $this->traveller->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 2, 'start_date' => '2026-06-01', 'end_date' => '2026-06-03',
        ]);
        TripSelectedExperience::create(['trip_id' => $trip->id, 'experience_id' => $exp->id, 'sort_order' => 0]);
        return $trip;
    }

    public function test_guide_pin_blocked_when_experience_includes_guide(): void
    {
        $trip = $this->tripWithGuideCost(2000);
        $this->pinGuide($trip)
            ->assertStatus(422)
            ->assertJson(['error' => "This experience already includes a guide, so an additional guide can't be added."]);
    }

    public function test_guide_pin_allowed_when_experience_has_no_guide(): void
    {
        $trip = $this->tripWithGuideCost(0);
        $this->pinGuide($trip)->assertStatus(200);
        $this->assertEquals($this->guidePricing->id, $trip->fresh()->guide_pricing_id);
    }
}
