<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Three things the client asked for on the experience form: choose a category
 * first, save a draft and come back later, and hang optional add-ons off a
 * listing.
 *
 * The most important test here is the partial-save one. Per-category forms omit
 * whole sections by design, and the save handler used to delete and rebuild the
 * itinerary and price tiers on EVERY save — so a form that legitimately had no
 * day-by-day plan silently destroyed one.
 */
class ExperienceCategoryDraftAddonTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected Region $region;
    protected ServiceProvider $hlh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        $this->region = Region::create([
            'name' => 'Tirthan Valley', 'slug' => 'tirthan-valley',
            'country' => 'India', 'is_active' => true,
        ]);

        $user = User::create([
            'full_name' => 'Host', 'email' => 'host@example.test',
            'password' => 'password', 'user_role' => 'hlh', 'status' => 'active',
        ]);
        $this->hlh = ServiceProvider::create([
            'user_id' => $user->id, 'provider_type' => 'hlh', 'provider_types' => ['hlh'],
            'name' => 'Tirthan Eco Retreat', 'email' => 'host@example.test',
            'phone_1' => '9000000000', 'region_id' => $this->region->id, 'status' => 'approved',
        ]);
    }

    private function ajax(array $payload)
    {
        return $this->post("http://{$this->portal}/ajax", $payload);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'save_sp_experience' => 1,
            'name' => 'Tar Village Stay',
            'region_id' => $this->region->id,
            'type' => 'Cultural Immersion',
            'category' => 'Experiential accommodation',
            'short_description' => 'A stay in a remote Ladakhi village.',
            'duration_type' => 'multi_day',
        ], $overrides);
    }

    public function test_the_chosen_category_is_stored(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->payload())->assertOk();

        $this->assertSame(
            'Experiential accommodation',
            Experience::firstWhere('name', 'Tar Village Stay')->category,
        );
    }

    /**
     * The client's reason for drafts: "many users won't have all the
     * information or photos ready in one session." A draft must therefore not
     * land in HCT's review queue, and must not claim to have been submitted.
     */
    public function test_a_draft_is_saved_without_being_submitted(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->payload(['save_as_draft' => 1]))->assertOk();

        $experience = Experience::firstWhere('name', 'Tar Village Stay');

        $this->assertTrue($experience->isDraft());
        $this->assertFalse((bool) $experience->is_active);
        $this->assertNull($experience->submitted_at);
        $this->assertNull($experience->submitted_by);
        $this->assertSame(0, Experience::pending()->count());
    }

    public function test_submitting_a_draft_puts_it_in_the_queue(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->payload(['save_as_draft' => 1]))->assertOk();
        $draft = Experience::firstWhere('name', 'Tar Village Stay');

        $this->ajax($this->payload(['id' => $draft->id]))->assertOk();

        $experience = $draft->fresh();
        $this->assertFalse($experience->isDraft());
        $this->assertSame('pending', $experience->approval_status);
        $this->assertNotNull($experience->submitted_at);
        $this->assertSame(1, Experience::pending()->count());
    }

    /** A member must not be able to pull a selling listing back into a draft. */
    public function test_a_live_listing_cannot_be_turned_into_a_draft(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->payload())->assertOk();
        $experience = Experience::firstWhere('name', 'Tar Village Stay');
        $experience->update(['approval_status' => 'approved', 'is_active' => true]);

        $this->ajax($this->payload(['id' => $experience->id, 'save_as_draft' => 1]))->assertOk();

        $experience->refresh();
        $this->assertSame('approved', $experience->approval_status);
        $this->assertTrue((bool) $experience->is_active);
        $this->assertNotNull($experience->pending_changes);
    }

    /**
     * THE REGRESSION THAT MATTERS. A category form that has no day-by-day plan
     * simply does not send one — that must not wipe an itinerary the listing
     * already has.
     */
    public function test_a_save_that_omits_the_itinerary_leaves_it_intact(): void
    {
        $this->actingAs($this->hlh->user);

        $this->ajax($this->payload([
            'experience_days' => [
                ['day_number' => 1, 'title' => 'Arrive', 'inclusions' => ['dinner']],
                ['day_number' => 2, 'title' => 'Walk', 'inclusions' => ['breakfast']],
            ],
            'price_slabs' => [
                ['min_persons' => 2, 'price_per_person' => 5000],
                ['min_persons' => 4, 'price_per_person' => 4200],
            ],
        ]))->assertOk();

        $experience = Experience::firstWhere('name', 'Tar Village Stay');
        $this->assertCount(2, $experience->days);
        $this->assertCount(2, $experience->priceSlabs);

        // A later save from a form that carries neither section.
        $this->ajax($this->payload([
            'id' => $experience->id,
            'short_description' => 'A quieter description.',
        ]))->assertOk();

        $experience->refresh();
        $this->assertCount(2, $experience->days, 'the itinerary was destroyed by a partial save');
        $this->assertCount(2, $experience->priceSlabs, 'the price tiers were destroyed by a partial save');
        $this->assertSame('A quieter description.', $experience->short_description);
    }

    /** Sending an empty itinerary is a deliberate clear, not an omission. */
    public function test_sending_an_empty_itinerary_does_clear_it(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->payload([
            'experience_days' => [['day_number' => 1, 'title' => 'Arrive']],
        ]))->assertOk();

        $experience = Experience::firstWhere('name', 'Tar Village Stay');
        $this->assertCount(1, $experience->days);

        $this->ajax($this->payload(['id' => $experience->id, 'experience_days' => []]))->assertOk();

        $this->assertCount(0, $experience->fresh()->days);
    }

    public function test_addons_are_saved_against_the_experience(): void
    {
        $this->actingAs($this->hlh->user);

        $this->ajax($this->payload([
            'addons' => [
                ['name' => 'Guided village walk', 'price' => 500, 'price_unit' => 'per person'],
                ['name' => 'Ladakhi cooking class', 'description' => 'Two hours with the family.'],
                ['name' => '', 'price' => 900], // blank repeater row
            ],
        ]))->assertOk();

        $addons = Experience::firstWhere('name', 'Tar Village Stay')->addons;

        $this->assertCount(2, $addons, 'a blank row was stored as an add-on');
        $this->assertSame('Guided village walk', $addons[0]->name);
        $this->assertSame('500.00', $addons[0]->price);
        $this->assertNull($addons[1]->price, 'an unpriced add-on should stay unpriced');
    }

    /** Add-ons follow the same rule as the itinerary. */
    public function test_a_save_that_omits_addons_leaves_them_intact(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->payload([
            'addons' => [['name' => 'Birdwatching']],
        ]))->assertOk();

        $experience = Experience::firstWhere('name', 'Tar Village Stay');
        $this->assertCount(1, $experience->addons);

        $this->ajax($this->payload(['id' => $experience->id]))->assertOk();

        $this->assertCount(1, $experience->fresh()->addons);
    }

    /**
     * The pricing grid the client asked for on an experiential stay: "Pricing
     * table (single, double, triple, meal plans)".
     */
    public function test_a_stay_stores_its_capacity_and_room_pricing_grid(): void
    {
        $this->actingAs($this->hlh->user);

        $this->ajax($this->payload([
            'total_rooms' => 4,
            'total_guests' => 10,
            'room_rates' => [
                ['occupancy' => 'Single Room', 'meal_plan' => 'BB - Breakfast only', 'price' => 2500],
                ['occupancy' => 'Double Room', 'meal_plan' => 'BB - Breakfast only', 'price' => 3500],
                ['occupancy' => 'Double Room', 'meal_plan' => 'FB - Full Board', 'price' => 4800],
                ['occupancy' => '', 'meal_plan' => 'FB - Full Board', 'price' => 9999], // incomplete
            ],
        ]))->assertOk();

        $experience = Experience::firstWhere('name', 'Tar Village Stay');

        $this->assertSame(4, $experience->total_rooms);
        $this->assertSame(10, $experience->total_guests);
        $this->assertCount(3, $experience->roomRates, 'an incomplete row was stored');
        $this->assertSame('2500.00', $experience->roomRates->first()->price);
    }

    /** One price per occupancy + meal plan, or the grid has no answer. */
    public function test_a_repeated_grid_cell_is_stored_once(): void
    {
        $this->actingAs($this->hlh->user);

        $this->ajax($this->payload([
            'room_rates' => [
                ['occupancy' => 'Double Room', 'meal_plan' => 'FB - Full Board', 'price' => 4800],
                ['occupancy' => 'Double Room', 'meal_plan' => 'FB - Full Board', 'price' => 5200],
            ],
        ]))->assertOk();

        $rates = Experience::firstWhere('name', 'Tar Village Stay')->roomRates;

        $this->assertCount(1, $rates);
        $this->assertSame('4800.00', $rates->first()->price);
    }

    public function test_a_save_that_omits_room_rates_leaves_them_intact(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->payload([
            'room_rates' => [
                ['occupancy' => 'Single Room', 'meal_plan' => 'No meals', 'price' => 1800],
            ],
        ]))->assertOk();

        $experience = Experience::firstWhere('name', 'Tar Village Stay');
        $this->assertCount(1, $experience->roomRates);

        $this->ajax($this->payload(['id' => $experience->id]))->assertOk();

        $this->assertCount(1, $experience->fresh()->roomRates);
    }

    /**
     * The form has to actually offer the category first, and carry the sections
     * the three categories need between them.
     */
    public function test_the_form_offers_the_category_and_its_sections(): void
    {
        $page = $this->actingAs($this->hlh->user)
            ->get("http://{$this->portal}/sp/experiences")
            ->assertOk()
            ->getContent();

        // The picker, with the client's three categories.
        $this->assertStringContainsString('name="category"', $page);
        $this->assertStringContainsString('Experiential accommodation', $page);
        $this->assertStringContainsString('Guided Cultural &amp; Outdoor Activities', $page);
        $this->assertStringContainsString('Workshops, Handicrafts, Local Knowledge', $page);

        // A stay's capacity and pricing grid, and the add-ons repeater.
        $this->assertStringContainsString('name="total_rooms"', $page);
        $this->assertStringContainsString('name="total_guests"', $page);
        $this->assertStringContainsString('spRoomRateTpl', $page);
        $this->assertStringContainsString('spAddonTpl', $page);

        // And the draft button.
        $this->assertStringContainsString('spExpDraftBtn', $page);
    }

    /**
     * Sections are hidden by category rather than removed, so switching back
     * does not lose what was typed — and so the field-parity guard still sees
     * every field. Each section must therefore declare which categories it
     * belongs to.
     */
    public function test_sections_declare_the_categories_they_belong_to(): void
    {
        $page = $this->actingAs($this->hlh->user)
            ->get("http://{$this->portal}/sp/experiences")
            ->assertOk()
            ->getContent();

        $this->assertGreaterThanOrEqual(
            10,
            substr_count($page, 'data-exp-categories'),
            'form sections are not tagged with their categories',
        );

        // A stay prices by room, so the per-head costing section is not its.
        $this->assertStringNotContainsString(
            'data-exp-categories="Experiential accommodation|Guided Cultural &amp; Outdoor Activities|Workshops, Handicrafts, Local Knowledge &amp; Storytelling"'
                . "\">\n                <h2 class=\"accordion-header\">\n                    <button class=\"accordion-button collapsed\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#spSecCost\"",
            $page,
        );
    }

    /** Drafts are still listings — they count against the ceiling. */
    public function test_drafts_count_towards_the_listing_cap(): void
    {
        $this->actingAs($this->hlh->user);

        for ($i = 0; $i < 10; $i++) {
            $this->ajax($this->payload([
                'name' => "Draft {$i}",
                'save_as_draft' => 1,
            ]))->assertOk();
        }

        $this->ajax($this->payload(['name' => 'One Too Many']))->assertStatus(422);
    }
}
