<?php

namespace Tests\Feature;

use App\Http\Resources\ProviderAccountResource;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app's home shows how much room a member has left for listings. That
 * number is HCT's to change, so it travels with the account rather than being
 * repeated in the app — where it would quietly disagree with the server the
 * first time somebody edited the setting.
 */
class ProviderLimitsInAccountTest extends TestCase
{
    use RefreshDatabase;

    private function provider(): ServiceProvider
    {
        $region = Region::create([
            'name' => 'Kumaon', 'slug' => 'kumaon', 'country' => 'India', 'is_active' => true,
        ]);
        $user = User::create([
            'full_name' => 'Host', 'email' => 'host@example.test',
            'password' => 'password', 'user_role' => 'provider', 'status' => 'active',
        ]);

        return ServiceProvider::create([
            'user_id' => $user->id, 'provider_types' => ['hlh'],
            'name' => 'Munsiyari Homestay', 'email' => 'host@example.test',
            'phone_1' => '9000000000', 'region_id' => $region->id, 'status' => 'approved',
        ]);
    }

    public function test_the_account_carries_the_experience_cap(): void
    {
        $payload = ProviderAccountResource::make($this->provider());

        $this->assertSame(10, $payload['limits']['experiences']);
    }

    /** Only experiences are capped, so there is no second number to send. */
    public function test_no_cap_is_reported_for_the_rate_card(): void
    {
        $payload = ProviderAccountResource::make($this->provider());

        $this->assertArrayNotHasKey('services', $payload['limits']);
    }

    public function test_changing_the_setting_changes_what_the_app_is_told(): void
    {
        Setting::setValue('max_experiences_per_provider', 4);

        $payload = ProviderAccountResource::make($this->provider());

        $this->assertSame(4, $payload['limits']['experiences']);
    }

    /** The setting is free text, so it can hold something that is not a number. */
    public function test_a_nonsense_setting_falls_back_to_the_enforced_default(): void
    {
        Setting::setValue('max_experiences_per_provider', 'unlimited');

        $payload = ProviderAccountResource::make($this->provider());

        $this->assertSame(10, $payload['limits']['experiences']);
    }
}
