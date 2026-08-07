<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An experiential stay is sold by the room, not by the head. Its price lives in
 * experience_room_rates (occupancy × meal plan) and base_cost_per_person stays
 * 0 — so every card that read base_cost_per_person showed a fully priced
 * homestay with no price at all, and the detail page printed nothing.
 *
 * price_from is the single answer to "from how much, and in what unit?" so the
 * eight places that render a headline price do not each get it wrong.
 */
class StayPriceFromTest extends TestCase
{
    use RefreshDatabase;

    protected Region $region;
    protected ServiceProvider $hlh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->region = Region::create([
            'name' => 'Kumaon', 'slug' => 'kumaon',
            'country' => 'India', 'is_active' => true,
        ]);
        $user = User::create([
            'full_name' => 'Host', 'email' => 'host@example.test',
            'password' => 'password', 'user_role' => 'provider', 'status' => 'active',
        ]);
        $this->hlh = ServiceProvider::create([
            'user_id' => $user->id, 'provider_types' => ['hlh'],
            'name' => 'Munsiyari Homestay', 'email' => 'host@example.test',
            'phone_1' => '9000000000', 'region_id' => $this->region->id, 'status' => 'approved',
        ]);
    }

    private function experience(array $overrides = []): Experience
    {
        return Experience::create(array_merge([
            'name' => 'Munsiyari Village Stay',
            'slug' => 'munsiyari-village-stay',
            'region_id' => $this->region->id,
            'hlh_id' => $this->hlh->id,
            'owner_provider_id' => $this->hlh->id,
            'owner_type' => 'hlh',
            'category' => Experience::CATEGORY_STAY,
            'type' => 'Cultural Immersion',
            'short_description' => 'A working farmhouse above the Gori valley.',
            'duration_type' => 'multi_day',
            'price_currency' => 'INR',
            'base_cost_per_person' => 0,
            'is_active' => true,
            'approval_status' => 'approved',
        ], $overrides));
    }

    private function withRates(Experience $experience, array $rates): Experience
    {
        foreach ($rates as $i => [$occupancy, $mealPlan, $price]) {
            $experience->roomRates()->create([
                'occupancy' => $occupancy, 'meal_plan' => $mealPlan,
                'price' => $price, 'sort_order' => $i,
            ]);
        }
        return $experience->fresh();
    }

    public function test_a_stay_quotes_its_cheapest_room_per_night(): void
    {
        $experience = $this->withRates($this->experience(), [
            ['Double', 'Breakfast only', 3200],
            ['Single', 'Breakfast only', 2200],
            ['Triple', 'All meals', 6000],
        ]);

        $this->assertSame(
            ['amount' => 2200.0, 'unit' => 'per night', 'currency' => 'INR'],
            $experience->price_from,
        );
    }

    public function test_everything_else_still_quotes_per_person(): void
    {
        $trek = $this->experience([
            'name' => 'Khaliya Top Trek', 'slug' => 'khaliya-top-trek',
            'category' => 'Guided Cultural & Outdoor Activities',
            'base_cost_per_person' => 2900,
        ]);

        $this->assertSame(
            ['amount' => 2900.0, 'unit' => 'per person', 'currency' => 'INR'],
            $trek->price_from,
        );
    }

    /**
     * A half-finished draft has no rates yet. The views treat null as "say
     * nothing", which is right — better than printing a confident zero.
     */
    public function test_a_stay_with_no_rates_yet_quotes_nothing(): void
    {
        $this->assertNull($this->experience()->price_from);
    }

    public function test_a_room_rate_of_zero_is_not_a_price(): void
    {
        $experience = $this->withRates($this->experience(), [
            ['Double', 'Room only', 0],
            ['Triple', 'All meals', 4000],
        ]);

        $this->assertSame(4000.0, $experience->price_from['amount']);
    }

    /**
     * price_from is appended to every serialised experience, so a list of stays
     * must not fetch rates one card at a time.
     */
    public function test_a_list_query_resolves_the_price_without_a_query_per_card(): void
    {
        foreach (range(1, 5) as $i) {
            $this->withRates(
                $this->experience(['name' => "Stay {$i}", 'slug' => "stay-{$i}"]),
                [['Double', 'Breakfast only', 1000 * $i]],
            );
        }

        DB::enableQueryLog();
        $listed = Experience::where('is_active', true)
            ->withRoomRateFrom()
            ->get();
        $listed->toJson();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries, 'the aggregate should answer every card in one query');
        $this->assertSame(1000.0, $listed->firstWhere('name', 'Stay 1')->price_from['amount']);
        $this->assertSame(5000.0, $listed->firstWhere('name', 'Stay 5')->price_from['amount']);
    }

    public function test_the_detail_page_shows_the_room_rate_table_instead_of_a_per_person_price(): void
    {
        $experience = $this->withRates($this->experience(), [
            ['Double', 'Breakfast only', 3200],
            ['Single', 'Breakfast only', 2200],
        ]);

        $response = $this->get('http://' . config('app.portal_domain') . '/experience/' . $experience->slug);

        $response->assertOk()
            ->assertSee('Room rates')
            ->assertSee('Rates are per room per night')
            ->assertSee('Breakfast only')
            ->assertDontSee('Per Person');
    }
}
