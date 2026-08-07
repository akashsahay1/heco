<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SpPricing;
use App\Models\SystemList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The per-service fields the client's data-collection document asks each OSP
 * for: a taxi's two per-kilometre rates, a guide's languages and wages, a
 * rental's item and deposit, and where a standard accommodation stands.
 *
 * The behaviour that matters most is not that the fields save — it is that a
 * form which never showed a field leaves it alone, and that a service only
 * ever carries the fields its own kind asks for.
 */
class OspServiceFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected Region $region;
    protected ServiceProvider $provider;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');

        $this->region = Region::create([
            'name' => 'Tirthan Valley',
            'slug' => 'tirthan-valley',
            'country' => 'India',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'full_name' => 'Osp Provider',
            'email' => 'osp@example.test',
            'password' => 'password',
            'user_role' => 'provider',
            'status' => 'active',
        ]);

        $this->provider = ServiceProvider::create([
            'user_id' => $this->user->id,
            'provider_type' => 'osp',
            'provider_types' => ['osp'],
            'name' => 'Valley Services',
            'email' => 'osp@example.test',
            'phone_1' => '9000000000',
            'region_id' => $this->region->id,
            'status' => 'approved',
        ]);
    }

    private function save(array $payload)
    {
        return $this->actingAs($this->user)->post("http://{$this->portal}/ajax", array_merge([
            'save_sp_pricing' => 1,
            'provider_id' => $this->provider->id,
        ], $payload));
    }

    /** The row an SP just submitted, which is pending rather than live. */
    private function saved(): SpPricing
    {
        return SpPricing::where('service_provider_id', $this->provider->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    public function test_a_taxi_keeps_a_plains_and_a_hill_rate(): void
    {
        $this->save([
            'service_type' => 'transport',
            'vehicle_type' => 'Sedan',
            'unit' => 'per km',
            'ac_available' => 1,
            'vehicle_count' => 3,
            'price_per_km_plains' => 14.50,
            'price_per_km_hills' => 22,
            'ac_extra_cost' => 300,
        ])->assertOk();

        $row = $this->saved();
        $this->assertTrue($row->ac_available);
        $this->assertSame(3, $row->vehicle_count);
        $this->assertEquals(14.50, $row->price_per_km_plains);
        $this->assertEquals(22, $row->price_per_km_hills);
        $this->assertEquals(300, $row->ac_extra_cost);
    }

    /**
     * The trip cost calculation reads one price and one unit. A taxi quoted
     * only per kilometre must therefore still answer with a price, or every
     * route it is used on would cost nothing.
     */
    public function test_a_per_km_taxi_still_produces_a_usable_price(): void
    {
        $this->save([
            'service_type' => 'transport',
            'vehicle_type' => 'Sedan',
            'unit' => '',
            'price_per_km_plains' => 18,
            'price_per_km_hills' => 26,
        ])->assertOk();

        $row = $this->saved();
        $this->assertEquals(18, $row->price, 'the plains rate becomes the row price');
        $this->assertSame('per km', $row->unit);
    }

    public function test_a_guide_records_languages_wages_and_qualifications(): void
    {
        $this->save([
            'service_type' => 'guide',
            'unit' => 'per day',
            'price' => 2500,
            'speaks_english' => 1,
            'languages' => ['French', 'Hebrew'],
            'wage_multi_day' => 3200,
            'is_certified' => 1,
            'has_first_aid' => 0,
        ])->assertOk();

        $row = $this->saved();
        $this->assertTrue($row->speaks_english);
        $this->assertSame(['French', 'Hebrew'], $row->languages);
        $this->assertEquals(2500, $row->price, 'the day wage stays the row price');
        $this->assertEquals(3200, $row->wage_multi_day);
        $this->assertTrue($row->is_certified);
        $this->assertFalse($row->has_first_aid);
    }

    /** "Other langages (from a list)" — so the options come from the database. */
    public function test_languages_are_a_system_list_hct_can_extend(): void
    {
        $languages = SystemList::ofType('language')->pluck('name');

        $this->assertContains('French', $languages);
        $this->assertContains('Hindi', $languages);
    }

    public function test_a_rental_records_its_item_and_deposit(): void
    {
        $this->save([
            'service_type' => 'rental',
            'rental_item' => 'Trekking tent (2 person)',
            'price' => 400,
            'unit' => 'per day',
            'security_deposit' => 2000,
        ])->assertOk();

        $row = $this->saved();
        $this->assertSame('Trekking tent (2 person)', $row->rental_item);
        $this->assertEquals(400, $row->price);
        $this->assertEquals(2000, $row->security_deposit);
    }

    public function test_a_rental_must_say_what_is_being_rented(): void
    {
        $this->save([
            'service_type' => 'rental',
            'price' => 400,
            'unit' => 'per day',
        ])->assertStatus(422);
    }

    public function test_a_standard_accommodation_records_where_it_stands(): void
    {
        $this->save([
            'service_type' => 'accommodation',
            'comfort_tier' => 'Category A',
            'room_category' => 'Double',
            'total_rooms' => 6,
            'price' => 3500,
            'latitude' => 31.6234567,
            'longitude' => 77.3456789,
            'guest_capacity' => 14,
            'seasonality_notes' => 'Closed in January.',
        ])->assertOk();

        $row = $this->saved();
        $this->assertEquals(31.6234567, $row->latitude);
        $this->assertEquals(77.3456789, $row->longitude);
        $this->assertSame(14, $row->guest_capacity);
        $this->assertSame('Closed in January.', $row->seasonality_notes);
    }

    /**
     * The bulk-add rows and the app's shorter editor do not show every field.
     * A save from one of those must not blank what another form had stored.
     */
    public function test_a_field_the_form_never_showed_is_left_alone(): void
    {
        $live = SpPricing::create([
            'service_provider_id' => $this->provider->id,
            'service_type' => 'transport',
            'vehicle_type' => 'Sedan',
            'unit' => 'per km',
            'price' => 18,
            'price_per_km_hills' => 26,
            'vehicle_count' => 3,
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        // A form that carries the rate but says nothing about the hill rate.
        $this->save([
            'id' => $live->id,
            'service_type' => 'transport',
            'vehicle_type' => 'Sedan',
            'unit' => 'per km',
            'price' => 20,
        ])->assertOk();

        $pending = SpPricing::where('pending_for_id', $live->id)->firstOrFail();
        $this->assertEquals(26, $pending->price_per_km_hills, 'the hill rate survived');
        $this->assertSame(3, $pending->vehicle_count);
        $this->assertEquals(20, $pending->price);
    }

    /** A deposit belongs to a rental; a taxi row must never pick one up. */
    public function test_one_service_does_not_carry_another_services_fields(): void
    {
        $this->save([
            'service_type' => 'transport',
            'vehicle_type' => 'Sedan',
            'unit' => 'per km',
            'price' => 18,
            'security_deposit' => 5000,
            'rental_item' => 'Not a rental',
        ])->assertOk();

        $row = $this->saved();
        $this->assertNull($row->security_deposit);
        $this->assertNull($row->rental_item);
    }

    /** The form itself must render, and must actually offer the new fields. */
    public function test_the_rates_page_offers_every_new_field(): void
    {
        $page = $this->actingAs($this->user)
            ->get("http://{$this->portal}/sp/pricing")
            ->assertOk();

        foreach ([
            'price_per_km_plains', 'price_per_km_hills', 'vehicle_count', 'ac_extra_cost',
            'wage_multi_day', 'languages[]', 'speaks_english', 'is_certified', 'has_first_aid',
            'rental_item', 'security_deposit',
            'latitude', 'longitude', 'guest_capacity', 'seasonality_notes',
        ] as $field) {
            $page->assertSee($field, false);
        }

        $page->assertSee('data-svc="rental"', false);
    }

    public function test_rental_is_offered_as_a_service_type(): void
    {
        $this->assertContains('Rental', SystemList::ofType('service_type')->pluck('name'));
    }
}
