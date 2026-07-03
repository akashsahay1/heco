<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayExperience;
use App\Models\User;
use App\Services\CostCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #42 — child/infant pricing is configurable (child_price_percent /
 * infant_price_percent). Infants at 0% don't change the price; children scale
 * by the configured fraction.
 */
class PaxTypePricingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Experience $exp;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('gst_percent', 5, 'financial');
        $this->user = User::create([
            'full_name' => 'U', 'email' => 'u@pax.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        $region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);
        $this->exp = Experience::create([
            'region_id' => $region->id, 'name' => 'Trek', 'slug' => 'trek', 'type' => 'nature',
            'short_description' => 'x', 'duration_type' => 'days', 'is_active' => true,
            'base_cost_per_person' => 10000, 'cost_activities' => 10000,
        ]);
    }

    private function tripCost(int $adults, int $children, int $infants): int
    {
        $trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $this->user->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => $adults, 'children' => $children, 'infants' => $infants,
            'start_date' => '2026-06-01', 'end_date' => '2026-06-02',
        ]);
        $day = TripDay::create(['trip_id' => $trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'added_by' => 'traveller']);
        TripDayExperience::create(['trip_day_id' => $day->id, 'experience_id' => $this->exp->id, 'cost_per_person' => 10000, 'sort_order' => 0]);
        return (int) app(CostCalculatorService::class)->calculate($trip)['total_cost'];
    }

    public function test_infants_at_zero_percent_do_not_change_price(): void
    {
        Setting::setValue('child_price_percent', 50, 'financial');
        Setting::setValue('infant_price_percent', 0, 'financial');
        $this->assertSame($this->tripCost(2, 0, 0), $this->tripCost(2, 0, 3));
    }

    public function test_children_scale_by_configured_percent(): void
    {
        Setting::setValue('child_price_percent', 50, 'financial');
        // 2 adults = 20,000 activities; +2 children @50% => peopleFactor 3 => 30,000.
        $this->assertSame(20000, $this->tripCost(2, 0, 0));
        $this->assertSame(30000, $this->tripCost(2, 2, 0));

        // Change the percent -> price changes without touching code.
        Setting::setValue('child_price_percent', 100, 'financial');
        $this->assertSame(40000, $this->tripCost(2, 2, 0));
    }
}
