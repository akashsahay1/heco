<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\Setting;
use App\Models\SpPricing;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayExperience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end verification of the two-step accommodation booking flow at the
 * HTTP layer: category -> provider list -> save selection -> provider-driven
 * pricing -> category-change clears provider -> confirm trip.
 *
 * Drives the real POST /ajax endpoint as an authenticated traveller on the
 * portal domain, against an in-memory sqlite DB (RefreshDatabase) seeded with
 * the minimum data the flow needs.
 */
class AccommodationBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Portal domain so Route::domain() group resolves. */
    protected string $portal;

    protected User $traveller;
    protected Region $region;
    protected Experience $experience;
    protected ServiceProvider $provider;
    protected array $pricingByTier = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->portal = config('app.portal_domain');

        // Pricing settings the CostCalculatorService reads.
        Setting::setValue('rest_day_cost_per_person', 2000);
        Setting::setValue('activity_day_cost_per_person', 5000);
        Setting::setValue('default_rp_margin_percent', 5);
        Setting::setValue('default_hrp_margin_percent', 10);
        Setting::setValue('default_hct_commission_percent', 15);
        Setting::setValue('gst_percent', 5);

        $this->traveller = User::create([
            'full_name'   => 'Test Traveller',
            'email'       => 'traveller@example.test',
            'password'    => 'password',
            'user_role'   => 'traveller',
            'nationality' => 'India',
            'status'      => 'active',
        ]);

        $this->region = Region::create([
            'name'      => 'Test Valley',
            'slug'      => 'test-valley',
            'is_active' => true,
        ]);

        // One experience with a cost breakdown so the trip has a non-zero base.
        $this->experience = Experience::create([
            'region_id'         => $this->region->id,
            'name'              => 'River Walk',
            'slug'              => 'river-walk',
            'type'              => 'nature',
            'short_description' => 'A gentle riverside walk.',
            'duration_type'     => 'hours',
            'is_active'         => true,
            'base_cost_per_person' => 5000,
            'cost_accommodation'   => 1000,
            'cost_logistics'       => 500,
            'cost_guide'           => 800,
            'cost_activities'      => 2000,
            'cost_other'           => 0,
        ]);

        // Approved provider offering accommodation in Cat A-D (mirrors the
        // "Mountain View" seed row: ₹6501 / ₹4200 / ₹2500 / ₹1500).
        $this->provider = ServiceProvider::create([
            'provider_type' => 'hlh',
            'name'          => 'Mountain View',
            'email'         => 'mv@example.test',
            'phone_1'       => '9990001111',
            'region_id'     => $this->region->id,
            'status'        => 'approved',
            'accommodation_categories' => ['Cat A - Premium/Luxury'],
        ]);

        $tiers = [
            'Cat A - Premium/Luxury' => 6501,
            'Cat B - Comfort'        => 4200,
            'Cat C - Standard'       => 2500,
            'Cat D - Basic/Homestay' => 1500,
        ];
        foreach ($tiers as $tier => $price) {
            $this->pricingByTier[$tier] = SpPricing::create([
                'service_provider_id' => $this->provider->id,
                'service_type'        => 'accommodation',
                'category'            => $tier,
                'room_category'       => 'Double Room',
                'comfort_tier'        => $tier,
                'unit'                => 'night',
                'price'               => $price,
                'total_rooms'         => 10,
                'default_occupancy'   => 2,
                'is_active'           => true,
                'approval_status'     => 'approved',
            ]);
        }
    }

    /** POST /ajax on the portal domain as the traveller. */
    protected function ajax(array $payload)
    {
        return $this->actingAs($this->traveller)
            ->post("http://{$this->portal}/ajax", $payload);
    }

    /** A trip with one experience-day and a 4-night date span (3 nights = end-start). */
    protected function makeTrip(): Trip
    {
        $start = '2026-06-01';
        $end   = '2026-06-04'; // 3 nights

        $trip = Trip::create([
            'trip_id'   => Trip::generateTripId(),
            'user_id'   => $this->traveller->id,
            'trip_name' => 'My Trip',
            'status'    => 'not_confirmed',
            'stage'     => 'open',
            'adults'    => 2,
            'children'  => 0,
            'infants'   => 0,
            'start_date' => $start,
            'end_date'   => $end,
        ]);

        $day = TripDay::create([
            'trip_id'    => $trip->id,
            'day_number' => 1,
            'sort_order' => 0,
            'date'       => $start,
            'day_type'   => 'activity',
            'added_by'   => 'traveller',
        ]);
        TripDayExperience::create([
            'trip_day_id'     => $day->id,
            'experience_id'   => $this->experience->id,
            'cost_per_person' => 5000,
            'sort_order'      => 0,
        ]);

        return $trip;
    }

    public function test_full_accommodation_booking_flow(): void
    {
        // 1. Authenticated traveller loads the portal homepage (GET 200).
        $home = $this->actingAs($this->traveller)->get("http://{$this->portal}/home");
        $home->assertStatus(200);

        $trip = $this->makeTrip();

        // 3. get_category_providers for Cat C -> >=1 provider with full fields.
        $catC = $this->ajax([
            'get_category_providers' => 1,
            'service_type'           => 'accommodation',
            'category'               => 'Cat C - Standard',
        ]);
        $catC->assertStatus(200)->assertJson(['success' => true]);
        $providers = $catC->json('providers');
        $this->assertNotEmpty($providers, 'Expected at least one Cat C provider');
        $first = $providers[0];
        foreach (['pricing_id', 'provider_id', 'provider_name', 'price', 'unit'] as $field) {
            $this->assertArrayHasKey($field, $first, "providers[0] missing $field");
        }
        $this->assertSame('Mountain View', $first['provider_name']);
        $this->assertEquals(2500.0, (float) $first['price']);
        $this->assertSame('night', $first['unit']);
        $this->assertEquals($this->pricingByTier['Cat C - Standard']->id, $first['pricing_id']);

        // 4. get_category_providers for a tier no provider offers -> empty list.
        $catE = $this->ajax([
            'get_category_providers' => 1,
            'service_type'           => 'accommodation',
            'category'               => 'Cat E - Camping/Tents',
        ]);
        $catE->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame([], $catE->json('providers'), 'Cat E should have no providers');

        // 5. update_travel_preferences saves the category + provider + pricing.
        $pricingId  = $this->pricingByTier['Cat C - Standard']->id;
        $save = $this->ajax([
            'update_travel_preferences'  => 1,
            'trip_id'                    => $trip->id,
            'accommodation_comfort'      => 'Cat C - Standard',
            'accommodation_provider_id'  => $this->provider->id,
            'accommodation_pricing_id'   => $pricingId,
        ]);
        $save->assertStatus(200)->assertJson(['success' => true]);

        $trip->refresh();
        $this->assertSame('Cat C - Standard', $trip->accommodation_comfort);
        $this->assertEquals($this->provider->id, $trip->accommodation_provider_id);
        $this->assertEquals($pricingId, $trip->accommodation_pricing_id);

        // 6. get_trip_pricing reflects provider rate: 2500 * 1 room * 3 nights = 7500.
        // rooms = ceil(2 pax / 2 occupancy) = 1; nights = 2026-06-04 - 2026-06-01 = 3.
        $pricingResp = $this->ajax([
            'get_trip_pricing' => 1,
            'trip_id'          => $trip->id,
        ]);
        $pricingResp->assertStatus(200)->assertJson(['success' => true]);
        $pricing = $pricingResp->json('pricing');
        $this->assertEquals(
            9500,
            (float) $pricing['accommodation_cost'],
            'Accommodation stacks: experience trek-stay 1000 x peopleFactor(2) = 2000 '
                . 'PLUS provider hotel 2500 x 1 room x 3 nights = 7500 => 9500'
        );
        $this->assertEquals(1.0, (float) $pricing['accommodation_multiplier'], 'Cat C category multiplier is 1.0');

        // The provider hotel adds ON TOP of the experience trek-stay — it does not
        // replace it — so the line is neither the pure provider rate (7500) nor the
        // pure experience estimate (2000).
        $this->assertNotEquals(7500, (float) $pricing['accommodation_cost']);
        $this->assertNotEquals(2000, (float) $pricing['accommodation_cost']);

        // 7. Changing category alone (no pricing id) clears provider + pricing.
        $changeCat = $this->ajax([
            'update_travel_preferences' => 1,
            'trip_id'                   => $trip->id,
            'accommodation_comfort'     => 'Cat B - Comfort',
        ]);
        $changeCat->assertStatus(200)->assertJson(['success' => true]);

        $trip->refresh();
        $this->assertSame('Cat B - Comfort', $trip->accommodation_comfort);
        $this->assertNull($trip->accommodation_provider_id, 'Provider should be cleared on category change');
        $this->assertNull($trip->accommodation_pricing_id, 'Pricing should be cleared on category change');

        // 8. Confirm trip -> status becomes confirmed.
        $confirm = $this->ajax([
            'confirm_trip' => 1,
            'trip_id'      => $trip->id,
        ]);
        $confirm->assertStatus(200)->assertJson(['success' => true, 'status' => 'confirmed']);

        $trip->refresh();
        $this->assertSame('confirmed', $trip->status);
        $this->assertSame('open', $trip->stage, 'Stage should stay open after traveller confirm');
    }
}
