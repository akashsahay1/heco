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
use App\Services\CostCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * #6/#7 — a guest must see the SAME trip cost they'll see after logging in.
 * computeGuestPricing now uses the same component-breakdown model as
 * CostCalculatorService, so the totals match for identical experiences/pax/prefs.
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

    public function test_guest_and_login_match_with_comfort_multiplier(): void
    {
        $prefs = ['accommodation_comfort' => 'Cat B - Comfort', 'guide_preference' => 'Certified/Expert'];
        $this->assertSame($this->loginTotal($prefs), $this->guestTotal($prefs));
    }
}
