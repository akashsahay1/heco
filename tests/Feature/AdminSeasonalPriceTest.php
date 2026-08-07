<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin experience form's "Seasonal Price Variation" used to be a raw JSON
 * textarea. It's now a friendly row editor (season label + % change), posted as
 * bracket-notation arrays. The save handler keeps only named rows and stores the
 * percent as a clean number — no JSON typing by a non-technical admin.
 */
class AdminSeasonalPriceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'full_name' => 'HCT Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'user_role' => 'administrator', 'status' => 'active',
        ]);
    }

    private function context(): array
    {
        $region = Region::create([
            'name' => 'Tirthan Valley', 'slug' => 'tirthan-valley',
            'country' => 'India', 'is_active' => true,
        ]);
        $host = User::create([
            'full_name' => 'Host', 'email' => 'host@example.test',
            'password' => 'password', 'user_role' => 'provider', 'status' => 'active',
        ]);
        $provider = ServiceProvider::create([
            'user_id' => $host->id, 'provider_type' => 'hlh', 'name' => 'Valley Host',
            'email' => 'host@example.test', 'phone_1' => '9000000000',
            'region_id' => $region->id, 'status' => 'approved',
        ]);
        return [$region, $provider];
    }

    private function base(Region $region, ServiceProvider $provider): array
    {
        return [
            'save_experience' => 1,
            'name' => 'Ridge Walk',
            'region_id' => $region->id,
            'hlh_id' => $provider->id,
            'type' => 'Trek',
            'short_description' => 'A walk along the ridge.',
            'duration_type' => 'single_day',
        ];
    }

    private function ajax(array $payload)
    {
        $admin = config('app.admin_domain');
        return $this->post("http://{$admin}/ajax", $payload);
    }

    public function test_named_rows_are_stored_as_numbers_and_blanks_dropped(): void
    {
        [$region, $provider] = $this->context();

        $this->actingAs($this->admin())->ajax($this->base($region, $provider) + [
            'seasonal_price_variation' => [
                ['label' => 'Peak — Oct to Nov', 'adjustment_percent' => '20'],
                ['label' => '', 'adjustment_percent' => '5'],          // no label → dropped
                ['label' => 'Monsoon — Jul to Aug', 'adjustment_percent' => '-15'],
            ],
        ])->assertOk();

        $exp = Experience::firstWhere('name', 'Ridge Walk');
        $this->assertNotNull($exp);
        // Loose compare: after the JSON round-trip 20.0 comes back as 20 — the
        // number and the structure are what matter, not int-vs-float.
        $this->assertEquals([
            ['label' => 'Peak — Oct to Nov', 'adjustment_percent' => 20],
            ['label' => 'Monsoon — Jul to Aug', 'adjustment_percent' => -15],
        ], $exp->seasonal_price_variation);
    }

    public function test_all_blank_rows_store_null(): void
    {
        [$region, $provider] = $this->context();

        $this->actingAs($this->admin())->ajax($this->base($region, $provider) + [
            'seasonal_price_variation' => [
                ['label' => '', 'adjustment_percent' => ''],
            ],
        ])->assertOk();

        $this->assertNull(Experience::firstWhere('name', 'Ridge Walk')->seasonal_price_variation);
    }
}
