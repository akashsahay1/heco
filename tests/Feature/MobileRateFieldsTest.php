<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SpPricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every field the app's rate forms collect has to survive the trip to the
 * database.
 *
 * The mobile API forwards an explicit allow-list into the portal's ajax
 * dispatcher, and anything missing from that list is dropped without a word.
 * The client's service fields — a taxi's plains and hill rates, a guide's
 * languages and two wage levels, a rental's item and deposit — were added to
 * the database, the portal and the app, but never to the list. A provider could
 * fill in the whole form on their phone, see "Pending Review", and have HCT
 * receive nothing but the vehicle type.
 *
 * These tests post what the app posts and read back what was stored.
 */
class MobileRateFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected ServiceProvider $osp;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $region = Region::create([
            'name' => 'Kumaon', 'slug' => 'kumaon', 'country' => 'India', 'is_active' => true,
        ]);
        $user = User::create([
            'full_name' => 'Supplier', 'email' => 'osp@example.test',
            'password' => 'password', 'user_role' => 'osp', 'status' => 'active',
        ]);
        $this->osp = ServiceProvider::create([
            'user_id' => $user->id, 'provider_type' => 'osp', 'provider_types' => ['osp'],
            'name' => 'Nanda Devi Taxi Service', 'email' => 'osp@example.test',
            'phone_1' => '9000000000', 'region_id' => $region->id, 'status' => 'approved',
        ]);
        [, $this->token] = ApiToken::issueFor($user, 'test-device');
    }

    private function save(array $payload): SpPricing
    {
        $this->postJson('/api/v1/provider/pricing', $payload, [
            'Authorization' => 'Bearer ' . $this->token,
        ])->assertOk();

        return SpPricing::where('service_provider_id', $this->osp->id)->latest()->firstOrFail();
    }

    public function test_a_taxi_keeps_its_plains_and_hill_rates(): void
    {
        $rate = $this->save([
            'service_type' => 'transport',
            'vehicle_type' => 'SUV (Innova/Crysta)',
            'price' => 18,
            'unit' => 'per km',
            'price_per_km_plains' => 14,
            'price_per_km_hills' => 22,
            'vehicle_count' => 3,
            'ac_available' => true,
            'ac_extra_cost' => 500,
        ]);

        $this->assertSame('14.00', $rate->price_per_km_plains);
        $this->assertSame('22.00', $rate->price_per_km_hills);
        $this->assertSame(3, $rate->vehicle_count);
        $this->assertTrue((bool) $rate->ac_available);
        $this->assertSame('500.00', $rate->ac_extra_cost);
    }

    public function test_a_guide_keeps_their_languages_wages_and_certificates(): void
    {
        $rate = $this->save([
            'service_type' => 'guide',
            'description' => 'Trekking guide, Johar valley',
            'price' => 1800,
            'unit' => 'per day',
            'speaks_english' => true,
            'languages' => ['Hindi', 'Kumaoni', 'English'],
            'wage_multi_day' => 2200,
            'is_certified' => true,
            'has_first_aid' => true,
        ]);

        $this->assertTrue((bool) $rate->speaks_english);
        $this->assertSame(['Hindi', 'Kumaoni', 'English'], $rate->languages);
        $this->assertSame('2200.00', $rate->wage_multi_day);
        $this->assertTrue((bool) $rate->is_certified);
        $this->assertTrue((bool) $rate->has_first_aid);
    }

    public function test_a_rental_keeps_its_item_and_deposit(): void
    {
        $rate = $this->save([
            'service_type' => 'rental',
            'description' => 'Trekking gear',
            'price' => 350,
            'unit' => 'per day',
            'rental_item' => 'Sleeping bag rated to -10C',
            'security_deposit' => 2000,
        ]);

        $this->assertSame('Sleeping bag rated to -10C', $rate->rental_item);
        $this->assertSame('2000.00', $rate->security_deposit);
    }

    public function test_an_accommodation_keeps_its_location_and_capacity(): void
    {
        $rate = $this->save([
            'service_type' => 'accommodation',
            'comfort_tier' => 'Cat C - Standard',
            'room_category' => 'Deluxe Room',
            'total_rooms' => 6,
            'price' => 3200,
            'unit' => 'per room per night',
            'latitude' => 30.0668,
            'longitude' => 80.2380,
            'guest_capacity' => 14,
            'seasonality_notes' => 'Closed mid-January after heavy snow.',
        ]);

        // Compared as numbers: the column's scale is the database's business,
        // not this test's.
        $this->assertEqualsWithDelta(30.0668, (float) $rate->latitude, 0.00001);
        $this->assertEqualsWithDelta(80.2380, (float) $rate->longitude, 0.00001);
        $this->assertSame(14, $rate->guest_capacity);
        $this->assertStringContainsString('heavy snow', $rate->seasonality_notes);
    }
}
