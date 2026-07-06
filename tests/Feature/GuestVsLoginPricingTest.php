<?php

namespace Tests\Feature;

use App\Http\Controllers\AjaxController;
use App\Models\Experience;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayExperience;
use App\Models\User;
use App\Models\ServiceProvider;
use App\Models\SpPricing;
use App\Services\CostCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * #6/#7 — a guest must see the SAME trip cost they'll see after logging in.
 * computeGuestPricing uses the same slab-bundle + marked-up-provider model as
 * CostCalculatorService, so the totals match for identical experiences/pax.
 */
class GuestVsLoginPricingTest extends TestCase
{
    use RefreshDatabase;

    protected Experience $exp;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'full_name' => 'U', 'email' => 'u@gp.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        Setting::setValue('default_rp_margin_percent', 5, 'financial');
        Setting::setValue('default_hrp_margin_percent', 10, 'financial');
        Setting::setValue('default_hct_commission_percent', 15, 'financial');
        Setting::setValue('gst_percent', 5, 'financial');

        $region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);
        $this->exp = Experience::create([
            'region_id' => $region->id, 'name' => 'Trek', 'slug' => 'trek', 'type' => 'nature',
            'short_description' => 'x', 'duration_type' => 'days', 'is_active' => true,
            'base_cost_per_person' => 13000,
            'cost_accommodation' => 3000, 'cost_logistics' => 4000,
            'cost_guide' => 4000, 'cost_activities' => 2000, 'cost_other' => 0,
        ]);
    }

    private function loginTotal(array $prefs): float
    {
        $trip = Trip::create(array_merge([
            'trip_id' => Trip::generateTripId(), 'user_id' => $this->user->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 2, 'children' => 0, 'infants' => 0,
            'start_date' => '2026-06-01', 'end_date' => '2026-06-02',
        ], $prefs));
        $day = TripDay::create(['trip_id' => $trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'day_type' => 'activity', 'added_by' => 'traveller']);
        TripDayExperience::create(['trip_day_id' => $day->id, 'experience_id' => $this->exp->id, 'cost_per_person' => 2000, 'sort_order' => 0]);
        return (float) app(CostCalculatorService::class)->calculate($trip)['total_cost'];
    }

    private function guestTotal(array $prefs): float
    {
        $guestData = array_merge([
            'adults' => 2, 'children' => 0,
            'experience_ids' => [$this->exp->id],
            'ai_itinerary' => ['days' => [['experiences' => [['experience_id' => $this->exp->id]]]]],
        ], $prefs);
        $m = new ReflectionMethod(AjaxController::class, 'computeGuestPricing');
        $m->setAccessible(true);
        return (float) $m->invoke(app(AjaxController::class), $guestData)['total_cost'];
    }

    public function test_guest_and_login_match_with_no_preferences(): void
    {
        $this->assertSame($this->loginTotal([]), $this->guestTotal([]));
    }

    public function test_guest_and_login_match_with_preferences_set(): void
    {
        // Preference selects no longer affect price (multipliers removed) — guest and
        // login still match, and setting prefs doesn't change the total.
        $prefs = ['accommodation_comfort' => 'Cat B - Comfort', 'guide_preference' => 'Certified/Expert'];
        $this->assertSame($this->loginTotal($prefs), $this->guestTotal($prefs));
        $this->assertSame($this->loginTotal([]), $this->loginTotal($prefs));
    }

    public function test_guest_provider_accommodation_stacks_on_bundle(): void
    {
        $provider = ServiceProvider::create([
            'provider_type' => 'hlh', 'name' => 'Stay', 'email' => 's@gp.test',
            'phone_1' => '9990001111', 'region_id' => $this->exp->region_id, 'status' => 'approved',
            'markup_percent' => 0,
        ]);
        $pricing = SpPricing::create([
            'service_provider_id' => $provider->id, 'service_type' => 'accommodation',
            'unit' => 'night', 'price' => 2000, 'default_occupancy' => 2,
            'is_active' => true, 'approval_status' => 'approved',
        ]);
        // 2 days -> nights 1; 2 adults / occupancy 2 -> 1 room -> provider 2000.
        $guestData = [
            'adults' => 2, 'children' => 0,
            'experience_ids' => [$this->exp->id],
            'ai_itinerary' => ['days' => [
                ['experiences' => [['experience_id' => $this->exp->id]]],
                ['experiences' => [['experience_id' => $this->exp->id]]],
            ]],
            'accommodation_pricing_id' => $pricing->id,
        ];
        $m = new ReflectionMethod(AjaxController::class, 'computeGuestPricing');
        $m->setAccessible(true);
        $r = $m->invoke(app(AjaxController::class), $guestData);

        // Experience is one slab bundle: base_cost_per_person 13000 x peopleFactor(2) = 26000.
        $this->assertSame(26000, (int) $r['experience_cost']);
        // Accommodation line = the provider hotel only (2000, markup 0%); the trek-stay
        // is inside the experience bundle, not on this line.
        $this->assertSame(2000, (int) $r['accommodation_provider_cost']);
        $this->assertSame(2000, (int) $r['accommodation_cost']);
        // Provider stacks on the bundle: 26000 + 2000 = 28000.
        $this->assertSame(28000, (int) $r['total_cost']);
    }
}
