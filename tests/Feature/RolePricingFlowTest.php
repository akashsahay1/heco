<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\ExperiencePriceSlab;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\Setting;
use App\Models\SpPricing;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayExperience;
use App\Models\TripSelectedExperience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end, role-by-role drive of the new Trek Booking pricing model over the
 * real POST /ajax dispatcher — HCT admin, service providers (HRP/OSP/HLH),
 * traveller, and guest — covering slabs (3.2), per-km transport (3.1), per-provider
 * markup with hidden raw price + hidden HRP/HCT (3.3), and the authorization gate.
 */
class RolePricingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected Region $region;
    protected User $admin;
    protected User $traveller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        Setting::setValue('gst_percent', 5, 'financial');
        Setting::setValue('default_rp_margin_percent', 5, 'financial');
        Setting::setValue('default_hrp_margin_percent', 10, 'financial');
        Setting::setValue('default_hct_commission_percent', 15, 'financial');
        Setting::setValue('default_provider_markup_percent', 0, 'financial');

        $this->region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);
        $this->admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@rp.test',
            'password' => 'password', 'user_role' => 'hct_admin', 'status' => 'active',
        ]);
        $this->traveller = User::create([
            'full_name' => 'Trav', 'email' => 'trav@rp.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
    }

    private function ajax(array $payload)
    {
        return $this->post("http://{$this->portal}/ajax", $payload);
    }

    private function provider(string $type, string $email, float $markup = 0): ServiceProvider
    {
        return ServiceProvider::create([
            'provider_type' => $type, 'name' => strtoupper($type) . ' Co', 'email' => $email,
            'phone_1' => '9990000000', 'region_id' => $this->region->id,
            'status' => 'approved', 'markup_percent' => $markup,
        ]);
    }

    private function hlhExperience(array $slabs = [1 => 15000, 2 => 12000], float $guide = 0): Experience
    {
        $hlh = $this->provider('hlh', 'hlh' . uniqid() . '@rp.test');
        $exp = Experience::create([
            'hlh_id' => $hlh->id, 'region_id' => $this->region->id,
            'name' => 'GHNP Trek', 'slug' => 'ghnp-' . uniqid(), 'type' => 'nature',
            'short_description' => 'x', 'duration_type' => 'multi_day', 'is_active' => true,
            'base_cost_per_person' => 12000, 'cost_guide' => $guide,
        ]);
        foreach ($slabs as $n => $p) {
            ExperiencePriceSlab::create(['experience_id' => $exp->id, 'min_persons' => $n, 'price_per_person' => $p]);
        }
        return $exp;
    }

    // ── HCT admin: experiences with slabs ───────────────────────────────────
    public function test_hct_admin_creates_experience_with_slabs(): void
    {
        $hlh = $this->provider('hlh', 'hlh-slab@rp.test');
        $resp = $this->actingAs($this->admin)->ajax([
            'save_experience' => 1,
            'name' => 'Slab Trek', 'region_id' => $this->region->id, 'hlh_id' => $hlh->id,
            'type' => 'nature', 'short_description' => 'x', 'duration_type' => 'multi_day',
            'price_slabs' => [
                ['min_persons' => 1, 'price_per_person' => 15000],
                ['min_persons' => 2, 'price_per_person' => 12000],
                ['min_persons' => 6, 'price_per_person' => 9000],
            ],
        ]);
        $resp->assertStatus(200)->assertJson(['success' => true]);

        $exp = Experience::where('name', 'Slab Trek')->firstOrFail();
        $this->assertSame(3, $exp->priceSlabs()->count());
        $this->assertSame(12000.0, (float) $exp->slabPricePerPerson(2));
        $this->assertSame(9000.0, (float) $exp->slabPricePerPerson(8)); // 6+ tier
        // base_cost_per_person = cheapest slab (the "from" price).
        $this->assertSame(9000.0, (float) $exp->base_cost_per_person);
    }

    // ── HCT admin: per-provider markup + per-km transport ───────────────────
    public function test_hct_admin_sets_markup_and_per_km_pricing(): void
    {
        $hotel = $this->provider('hrp', 'hotel-mk@rp.test');
        $this->actingAs($this->admin)->ajax([
            'edit_provider' => 1, 'provider_id' => $hotel->id,
            'name' => $hotel->name, 'email' => $hotel->email, 'phone_1' => $hotel->phone_1,
            'region_id' => $this->region->id, 'provider_type' => 'hrp', 'status' => 'approved',
            'markup_percent' => 15,
        ])->assertStatus(200);
        $this->assertSame(15.0, (float) $hotel->fresh()->markup_percent);

        // Admin saves a per-km transport rate (approved directly).
        $cab = $this->provider('osp', 'cab-km@rp.test');
        $this->actingAs($this->admin)->ajax([
            'save_sp_pricing' => 1, 'provider_id' => $cab->id, 'service_type' => 'transport',
            'vehicle_type' => 'SUV', 'unit' => 'per km', 'price' => 40, 'distance_km' => 50,
        ])->assertStatus(200);
        $row = SpPricing::where('service_provider_id', $cab->id)->firstOrFail();
        $this->assertSame(50.0, (float) $row->distance_km);
        $this->assertSame('per km', $row->unit);
    }

    // ── Authorization: traveller & SP can't do admin-only things ────────────
    public function test_traveller_cannot_create_experience_or_edit_provider(): void
    {
        $this->actingAs($this->traveller)->ajax([
            'save_experience' => 1, 'name' => 'X', 'region_id' => $this->region->id,
        ])->assertStatus(403);

        $hotel = $this->provider('hrp', 'h-403@rp.test');
        $this->actingAs($this->traveller)->ajax([
            'edit_provider' => 1, 'provider_id' => $hotel->id, 'markup_percent' => 99,
        ])->assertStatus(403);
        $this->assertSame(0.0, (float) $hotel->fresh()->markup_percent);
    }

    public function test_service_provider_cannot_set_own_markup(): void
    {
        $osp = $this->provider('osp', 'osp-self@rp.test', 0);
        $user = User::create([
            'full_name' => 'OSP', 'email' => 'ospuser@rp.test', 'password' => 'password',
            'user_role' => 'osp', 'status' => 'active',
        ]);
        $osp->update(['user_id' => $user->id]);

        // SP updates own profile and tries to sneak in a markup — must be ignored.
        $this->actingAs($user)->ajax([
            'update_sp_profile' => 1, 'name' => 'OSP New Name', 'markup_percent' => 80,
        ])->assertStatus(200);

        $osp->refresh();
        $this->assertSame('OSP New Name', $osp->name);      // profile change applied
        $this->assertSame(0.0, (float) $osp->markup_percent); // markup untouched
    }

    // ── Traveller & guest: sees marked-up price, never raw ──────────────────
    public function test_traveller_sees_marked_up_provider_price_not_raw(): void
    {
        $hotel = $this->provider('hrp', 'hotel-view@rp.test', 20);
        SpPricing::create([
            'service_provider_id' => $hotel->id, 'service_type' => 'accommodation',
            'comfort_tier' => 'Cat C - Standard', 'room_category' => 'Standard',
            'unit' => 'per night', 'price' => 1000, 'default_occupancy' => 2,
            'approval_status' => 'approved', 'is_active' => true,
        ]);
        $resp = $this->actingAs($this->traveller)->ajax([
            'get_category_providers' => 1, 'service_type' => 'accommodation',
            'category' => 'Cat C - Standard', 'region_id' => $this->region->id,
        ])->assertStatus(200);
        // 1000 + 20% = 1200; raw 1000 must never appear.
        $this->assertSame(1200.0, (float) $resp->json('providers.0.price'));
    }

    // ── Traveller: full trip pricing over the real endpoint ─────────────────
    public function test_traveller_full_trip_pricing_new_model(): void
    {
        $exp = $this->hlhExperience();

        $hotel = $this->provider('hrp', 'hotel-full@rp.test', 15);
        $hotelRate = SpPricing::create([
            'service_provider_id' => $hotel->id, 'service_type' => 'accommodation',
            'comfort_tier' => 'Cat C - Standard', 'room_category' => 'Standard',
            'unit' => 'per night', 'price' => 5000, 'default_occupancy' => 2,
            'approval_status' => 'approved', 'is_active' => true,
        ]);
        $cab = $this->provider('osp', 'cab-full@rp.test', 10);
        $cabRate = SpPricing::create([
            'service_provider_id' => $cab->id, 'service_type' => 'transport',
            'vehicle_type' => 'SUV', 'unit' => 'per km', 'price' => 40, 'distance_km' => 50,
            'approval_status' => 'approved', 'is_active' => true,
        ]);

        $trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $this->traveller->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 2, 'children' => 0, 'infants' => 0,
            'start_date' => '2026-06-01', 'end_date' => '2026-06-03',
        ]);
        $day = TripDay::create(['trip_id' => $trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'added_by' => 'traveller']);
        TripDayExperience::create(['trip_day_id' => $day->id, 'experience_id' => $exp->id, 'cost_per_person' => 0, 'sort_order' => 0]);

        // Pin the hotel + cab via the real preferences endpoint.
        $this->actingAs($this->traveller)->ajax([
            'update_travel_preferences' => 1, 'trip_id' => $trip->id,
            'accommodation_provider_id' => $hotel->id, 'accommodation_pricing_id' => $hotelRate->id,
            'vehicle_provider_id' => $cab->id, 'vehicle_pricing_id' => $cabRate->id,
        ])->assertStatus(200);

        $p = $this->actingAs($this->traveller)->ajax([
            'get_trip_pricing' => 1, 'trip_id' => $trip->id,
        ])->assertStatus(200)->json('pricing');

        $this->assertSame(24000, (int) $p['experience_cost']);     // 2p slab 12000 × 2
        $this->assertSame(11500, (int) $p['accommodation_cost']);  // 10000 + 15%
        $this->assertSame(2200, (int) $p['transport_cost']);       // 50km×40 + 10%
        $this->assertSame(37700, (int) $p['total_cost']);
        // HRP/HCT are NOT added to the traveller's total.
        $this->assertSame(37700.0, (float) $p['subtotal']);
        $this->assertSame(39585.0, (float) $p['final_price']);
    }

    // ── Guide exclusivity across the endpoint ───────────────────────────────
    public function test_guide_is_blocked_when_experience_includes_one(): void
    {
        $exp = $this->hlhExperience(guide: 3000);
        $trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $this->traveller->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 2, 'start_date' => '2026-06-01', 'end_date' => '2026-06-02',
        ]);
        $day = TripDay::create(['trip_id' => $trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'added_by' => 'traveller']);
        TripDayExperience::create(['trip_day_id' => $day->id, 'experience_id' => $exp->id, 'cost_per_person' => 0, 'sort_order' => 0]);
        // The real add-to-trip flow records the experience here too; guide exclusivity
        // reads trip_selected_experiences.
        TripSelectedExperience::create(['trip_id' => $trip->id, 'experience_id' => $exp->id, 'sort_order' => 0]);

        $resp = $this->actingAs($this->traveller)->ajax([
            'get_category_providers' => 1, 'service_type' => 'guide',
            'category' => 'Certified', 'region_id' => $this->region->id, 'trip_id' => $trip->id,
        ])->assertStatus(200);
        $this->assertTrue($resp->json('guide_included'));
    }
}
