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
use App\Models\User;
use App\Http\Controllers\AjaxController;
use App\Services\CostCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The client's Trek Booking pricing model (reqs 3.1–3.3):
 *  - Experience priced by per-person slabs that vary with group size.
 *  - Provider transport billed per-km (distance × rate).
 *  - Per-provider admin markup baked into the traveller price (raw hidden).
 *  - RP/HRP/HCT are internal only — NOT added to what the traveller pays.
 * Mirrors the worked example agreed with the client (2 adults → ₹39,585).
 */
class TrekPricingModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('gst_percent', 5, 'financial');
        Setting::setValue('default_rp_margin_percent', 5, 'financial');
        Setting::setValue('default_hrp_margin_percent', 10, 'financial');
        Setting::setValue('default_hct_commission_percent', 15, 'financial');
        Setting::setValue('default_provider_markup_percent', 0, 'financial');
    }

    private function makeProvider(string $type, string $email, float $markup): ServiceProvider
    {
        $region = Region::firstOrCreate(['slug' => 'valley'], ['name' => 'Valley', 'is_active' => true]);
        return ServiceProvider::create([
            'provider_type' => $type, 'name' => ucfirst($type) . ' Co', 'email' => $email,
            'phone_1' => '9990001111', 'region_id' => $region->id,
            'status' => 'approved', 'markup_percent' => $markup,
        ]);
    }

    public function test_full_worked_example_two_adults(): void
    {
        $region = Region::firstOrCreate(['slug' => 'valley'], ['name' => 'Valley', 'is_active' => true]);
        $user = User::create([
            'full_name' => 'U', 'email' => 'u@trek.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);

        // Experience with per-person slabs: 1p = 15000, 2p = 12000.
        $exp = Experience::create([
            'region_id' => $region->id, 'name' => 'GHNP Trek', 'slug' => 'ghnp', 'type' => 'nature',
            'short_description' => 'x', 'duration_type' => 'single_day', 'is_active' => true,
            'base_cost_per_person' => 15000,
        ]);
        ExperiencePriceSlab::create(['experience_id' => $exp->id, 'min_persons' => 1, 'price_per_person' => 15000]);
        ExperiencePriceSlab::create(['experience_id' => $exp->id, 'min_persons' => 2, 'price_per_person' => 12000]);

        // Hotel provider (markup 15%): ₹5000/night, occupancy 2.
        $hotel = $this->makeProvider('hrp', 'hotel@trek.test', 15);
        $hotelRate = SpPricing::create([
            'service_provider_id' => $hotel->id, 'service_type' => 'accommodation',
            'unit' => 'per night', 'price' => 5000, 'default_occupancy' => 2,
            'approval_status' => 'approved', 'is_active' => true,
        ]);

        // Transport provider (markup 10%): ₹40/km, route distance 50 km.
        $cab = $this->makeProvider('osp', 'cab@trek.test', 10);
        $cabRate = SpPricing::create([
            'service_provider_id' => $cab->id, 'service_type' => 'transport',
            'unit' => 'per km', 'price' => 40, 'distance_km' => 50,
            'approval_status' => 'approved', 'is_active' => true,
        ]);

        // 2 adults, 2 nights (01→03 June).
        $trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $user->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 2, 'children' => 0, 'infants' => 0,
            'start_date' => '2026-06-01', 'end_date' => '2026-06-03',
            'accommodation_pricing_id' => $hotelRate->id, 'accommodation_provider_id' => $hotel->id,
            'vehicle_pricing_id' => $cabRate->id, 'vehicle_provider_id' => $cab->id,
        ]);
        $day = TripDay::create(['trip_id' => $trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'added_by' => 'traveller']);
        TripDayExperience::create(['trip_day_id' => $day->id, 'experience_id' => $exp->id, 'cost_per_person' => 0, 'sort_order' => 0]);

        $b = app(CostCalculatorService::class)->calculate($trip);

        // Experience: 2p slab 12000 × 2 = 24000.
        $this->assertSame(24000, (int) $b['experience_cost']);
        // Hotel: 5000 × 1 room × 2 nights = 10000, +15% markup = 11500.
        $this->assertSame(11500, (int) $b['accommodation_cost']);
        // Transport: 40 × 50 km = 2000, +10% markup = 2200.
        $this->assertSame(2200, (int) $b['transport_cost']);
        // Trip cost = 24000 + 11500 + 2200 = 37700 (NO RP/HRP/HCT added).
        $this->assertSame(37700, (int) $b['total_cost']);
        // GST 5% of 37700 = 1885; final = 39585.
        $this->assertSame(1885.0, (float) $b['gst_amount']);
        $this->assertSame(39585.0, (float) $b['final_price']);
        // RP/HRP/HCT are computed for internal reporting but NOT in the total.
        $this->assertSame(1885.0, (float) $b['margin_rp_amount']);   // 5% of 37700
        $this->assertSame(3770.0, (float) $b['margin_hrp_amount']);  // 10%
        $this->assertSame(5655.0, (float) $b['commission_hct_amount']);  // 15%
    }

    public function test_slab_price_changes_with_group_size(): void
    {
        $region = Region::firstOrCreate(['slug' => 'valley'], ['name' => 'Valley', 'is_active' => true]);
        $user = User::create([
            'full_name' => 'U', 'email' => 'u2@trek.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        $exp = Experience::create([
            'region_id' => $region->id, 'name' => 'Trek', 'slug' => 'trek2', 'type' => 'nature',
            'short_description' => 'x', 'duration_type' => 'single_day', 'is_active' => true,
            'base_cost_per_person' => 15000,
        ]);
        foreach ([[1, 15000], [2, 12000], [4, 10000]] as [$n, $p]) {
            ExperiencePriceSlab::create(['experience_id' => $exp->id, 'min_persons' => $n, 'price_per_person' => $p]);
        }

        $cost = function (int $adults) use ($user, $exp) {
            $trip = Trip::create([
                'trip_id' => Trip::generateTripId(), 'user_id' => $user->id,
                'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
                'adults' => $adults, 'children' => 0, 'infants' => 0,
                'start_date' => '2026-06-01', 'end_date' => '2026-06-02',
            ]);
            $day = TripDay::create(['trip_id' => $trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'added_by' => 'traveller']);
            TripDayExperience::create(['trip_day_id' => $day->id, 'experience_id' => $exp->id, 'cost_per_person' => 0, 'sort_order' => 0]);
            return (int) app(CostCalculatorService::class)->calculate($trip)['experience_cost'];
        };

        $this->assertSame(15000, $cost(1));       // 1p slab 15000 × 1
        $this->assertSame(24000, $cost(2));       // 2p slab 12000 × 2
        $this->assertSame(36000, $cost(3));       // min<=3 -> 2p slab 12000 × 3
        $this->assertSame(40000, $cost(4));       // 4p slab 10000 × 4
    }

    public function test_get_category_providers_returns_marked_up_price_not_raw(): void
    {
        $region = Region::firstOrCreate(['slug' => 'valley'], ['name' => 'Valley', 'is_active' => true]);
        // Provider with a 20% markup and a raw ₹1000/night Cat C rate.
        $hotel = $this->makeProvider('hrp', 'markup@trek.test', 20);
        SpPricing::create([
            'service_provider_id' => $hotel->id, 'service_type' => 'accommodation',
            'comfort_tier' => 'Cat C - Standard', 'room_category' => 'Standard Double',
            'unit' => 'per night', 'price' => 1000, 'default_occupancy' => 2,
            'approval_status' => 'approved', 'is_active' => true,
        ]);

        $req = Request::create('/ajax', 'POST', [
            'get_category_providers' => 1, 'service_type' => 'accommodation',
            'category' => 'Cat C - Standard', 'region_id' => $region->id,
        ]);
        $m = new ReflectionMethod(AjaxController::class, 'getCategoryProviders');
        $m->setAccessible(true);
        $providers = $m->invoke(app(AjaxController::class), $req)->getData(true)['providers'];

        $this->assertNotEmpty($providers);
        // Traveller sees the marked-up price (1000 + 20% = 1200), never the raw 1000.
        $this->assertSame(1200.0, (float) $providers[0]['price']);
    }
}
