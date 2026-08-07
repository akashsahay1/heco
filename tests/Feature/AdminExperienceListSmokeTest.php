<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin Experiences page is fed by one ajax call and shows "Loading..."
 * forever if it fails. price_from is appended to every serialised experience,
 * and for a stay it reaches for room rates — so this list is the place that
 * would break first if that ever throws.
 */
class AdminExperienceListSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_loads_with_a_stay_and_a_priced_experience(): void
    {
        $admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'user_role' => 'administrator', 'status' => 'active',
        ]);
        $region = Region::create([
            'name' => 'Kumaon', 'slug' => 'kumaon', 'country' => 'India', 'is_active' => true,
        ]);

        $stay = Experience::create([
            'name' => 'Munsiyari Village Stay', 'slug' => 'munsiyari-village-stay',
            'region_id' => $region->id, 'type' => 'Cultural Immersion',
            'category' => Experience::CATEGORY_STAY,
            'short_description' => 'A farmhouse above the valley.',
            'duration_type' => 'multi_day', 'base_cost_per_person' => 0, 'is_active' => true,
        ]);
        $stay->roomRates()->create([
            'occupancy' => 'Double', 'meal_plan' => 'Breakfast only', 'price' => 3200,
        ]);

        Experience::create([
            'name' => 'Khaliya Top Trek', 'slug' => 'khaliya-top-trek',
            'region_id' => $region->id, 'type' => 'Trekking',
            'category' => 'Guided Cultural & Outdoor Activities',
            'short_description' => 'A meadow walk.',
            'duration_type' => 'multi_day', 'base_cost_per_person' => 2900, 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post('http://' . config('app.admin_domain') . '/ajax', ['get_experiences_list' => 1]);

        $response->assertOk();
        $names = array_column($response->json('data'), 'name');

        $this->assertContains('Munsiyari Village Stay', $names);
        $this->assertContains('Khaliya Top Trek', $names);
    }
}
