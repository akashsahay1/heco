<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A draft is a half-finished listing. The client asked for it because "many
 * users won't have all the information or photos ready in one session".
 *
 * The save handler still demanded a region, a type, a short description and a
 * duration to store one — so the app offered "Save draft", the host pressed it
 * with a name and a category, and nothing was kept. Submitting for review still
 * validates in full: a draft is never published or reviewed.
 */
class ExperienceDraftIsPartialTest extends TestCase
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
            'name' => 'Kumaon', 'slug' => 'kumaon', 'country' => 'India', 'is_active' => true,
        ]);
        $user = User::create([
            'full_name' => 'Host', 'email' => 'host@example.test',
            'password' => 'password', 'user_role' => 'hlh', 'status' => 'active',
        ]);
        $this->hlh = ServiceProvider::create([
            'user_id' => $user->id, 'provider_type' => 'hlh', 'provider_types' => ['hlh'],
            'name' => 'Munsiyari Homestay', 'email' => 'host@example.test',
            'phone_1' => '9000000000', 'region_id' => $this->region->id, 'status' => 'approved',
        ]);
    }

    private function save(array $payload)
    {
        return $this->actingAs($this->hlh->user)
            ->post("http://{$this->portal}/ajax", array_merge(['save_sp_experience' => 1], $payload));
    }

    public function test_a_draft_needs_only_a_name_and_a_category(): void
    {
        $this->save([
            'save_as_draft' => 1,
            'name' => 'Kumaoni Kitchen Afternoon',
            'category' => 'Workshops, Handicrafts, Local Knowledge & Storytelling',
        ])->assertOk();

        $draft = Experience::firstWhere('name', 'Kumaoni Kitchen Afternoon');

        $this->assertNotNull($draft, 'the half-finished listing was not kept');
        $this->assertSame('draft', $draft->approval_status);
        $this->assertNull($draft->submitted_at, 'a draft was never submitted');
        $this->assertFalse((bool) $draft->is_active);
    }

    public function test_a_draft_still_needs_a_name_to_be_findable(): void
    {
        $this->save([
            'save_as_draft' => 1,
            'category' => 'Workshops, Handicrafts, Local Knowledge & Storytelling',
        ])->assertStatus(422);

        $this->assertSame(0, Experience::count());
    }

    /**
     * The other half of the bargain: what goes to HCT is complete.
     */
    public function test_submitting_for_review_still_demands_the_full_set(): void
    {
        $this->save([
            'name' => 'Kumaoni Kitchen Afternoon',
            'category' => 'Workshops, Handicrafts, Local Knowledge & Storytelling',
        ])->assertStatus(422);

        $this->assertSame(0, Experience::count());
    }

    public function test_a_draft_can_be_finished_later_and_submitted(): void
    {
        $this->save([
            'save_as_draft' => 1,
            'name' => 'Kumaoni Kitchen Afternoon',
            'category' => 'Workshops, Handicrafts, Local Knowledge & Storytelling',
        ])->assertOk();

        $draft = Experience::firstWhere('name', 'Kumaoni Kitchen Afternoon');

        $this->save([
            'id' => $draft->id,
            'name' => 'Kumaoni Kitchen Afternoon',
            'category' => 'Workshops, Handicrafts, Local Knowledge & Storytelling',
            'region_id' => $this->region->id,
            'type' => 'Culinary',
            'short_description' => 'Cook a Kumaoni meal with the family.',
            'duration_type' => 'less_than_day',
        ])->assertOk();

        $finished = $draft->fresh();
        $this->assertSame('pending', $finished->approval_status);
        $this->assertNotNull($finished->submitted_at);
    }
}
