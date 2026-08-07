<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SpPricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #14 — a service provider can now create a 'meal' pricing row (service_type
 * enum + saveSpPricing validation both accept 'meal').
 */
class MealPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_create_meal_pricing(): void
    {
        $portal = config('app.portal_domain');

        $spUser = User::create([
            'full_name' => 'Host', 'email' => 'host@meal.test',
            'password' => 'password', 'user_role' => 'provider', 'status' => 'active',
        ]);
        $region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);
        // A rate card belongs to a supplier, so this homestay supplies too —
        // meals sold by the plate are a service, not the all-in experience an
        // HLH publishes.
        $provider = ServiceProvider::create([
            'provider_types' => ['hlh', 'osp'],
            'name' => 'Homestay', 'email' => 'host@meal.test',
            'phone_1' => '9990001111', 'region_id' => $region->id, 'status' => 'approved',
            'user_id' => $spUser->id,
        ]);

        $this->actingAs($spUser)
            ->post("http://{$portal}/ajax", [
                'save_sp_pricing' => 1,
                'provider_id' => $provider->id,
                'service_type' => 'meal',
                'category' => 'Dinner',
                'price' => 500,
                'unit' => 'per person',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('sp_pricing', [
            'service_provider_id' => $provider->id,
            'service_type' => 'meal',
            'price' => 500,
        ]);
    }
}
