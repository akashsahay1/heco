<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the #4 double-charge bug: adding an experience to a
 * trip-manager day must NOT count the experience's bundled components twice
 * (once as TripDayService rows, once via the Experience breakdown in
 * CostCalculatorService). The component service rows are cost=0 placeholders;
 * the Experience breakdown is the single source of truth.
 */
class ExperienceToDayPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_experience_to_day_does_not_double_charge(): void
    {
        $portal = config('app.portal_domain');

        // Margins/GST so calculate() has its inputs (total_cost is pre-margin).
        Setting::setValue('default_rp_margin_percent', 5, 'financial');
        Setting::setValue('default_hrp_margin_percent', 10, 'financial');
        Setting::setValue('default_hct_commission_percent', 15, 'financial');
        Setting::setValue('gst_percent', 5, 'financial');

        $admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@dc.test',
            'password' => 'password', 'user_role' => 'hct_admin', 'status' => 'active',
        ]);

        $region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);

        // Components sum to the 13,000 headline (accom 3k + logistics 4k + guide 4k
        // + activities 2k + other 0).
        $exp = Experience::create([
            'region_id' => $region->id,
            'name' => 'GHNP Trek', 'slug' => 'ghnp-trek', 'type' => 'nature',
            'short_description' => 'Trek.', 'duration_type' => 'days', 'is_active' => true,
            'base_cost_per_person' => 13000,
            'cost_accommodation' => 3000,
            'cost_logistics'     => 4000,
            'cost_guide'         => 4000,
            'cost_activities'    => 2000,
            'cost_other'         => 0,
        ]);

        // adults=1 -> peopleFactor=1, no comfort prefs -> multipliers=1.0, so the
        // single-count trip cost is exactly the component sum: 13,000.
        $trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $admin->id,
            'trip_name' => 'DC Trip', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 1, 'children' => 0, 'infants' => 0,
            'start_date' => '2026-06-01', 'end_date' => '2026-06-02',
        ]);
        $day = TripDay::create([
            'trip_id' => $trip->id, 'day_number' => 1, 'sort_order' => 0,
            'date' => '2026-06-01', 'day_type' => 'activity', 'added_by' => 'hct',
        ]);

        // Add the experience to the day via the real (gated) admin endpoint.
        $this->actingAs($admin)
            ->post("http://{$portal}/ajax", [
                'add_experience_to_day' => 1,
                'day_id' => $day->id,
                'experience_id' => $exp->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Component placeholder rows exist but carry no cost (bundled cost is the
        // Experience breakdown — single source).
        $componentRows = TripDayService::where('trip_day_id', $day->id)
            ->whereIn('service_type', ['accommodation', 'transport', 'guide'])
            ->get();
        $this->assertCount(3, $componentRows, 'accommodation/transport/guide placeholders created');
        foreach ($componentRows as $row) {
            $this->assertSame(0.0, (float) $row->cost, "{$row->service_type} placeholder must be cost=0");
        }

        // Trip cost = single count = 13,000 (double-charge would push it to ~24,000).
        $trip->refresh();
        $this->assertSame(13000.0, (float) $trip->total_cost);
    }
}
