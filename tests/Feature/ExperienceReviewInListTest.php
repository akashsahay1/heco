<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reviewing experiences used to live on its own page. It now happens in the one
 * Experiences list, so that list has to answer both questions HCT asks:
 * where a listing stands in review, and whether it is switched on.
 *
 * They stay separate columns on purpose — an approved listing turned off for
 * the season is not waiting on anybody.
 */
class ExperienceReviewInListTest extends TestCase
{
    use RefreshDatabase;

    protected string $admin_domain;
    protected User $admin;
    protected Region $region;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin_domain = config('app.admin_domain');
        $this->admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'user_role' => 'hct_admin', 'status' => 'active',
        ]);
        $this->region = Region::create([
            'name' => 'Kumaon', 'slug' => 'kumaon', 'country' => 'India', 'is_active' => true,
        ]);
    }

    private function experience(string $name, string $approval, bool $active, array $extra = []): Experience
    {
        return Experience::create(array_merge([
            'name' => $name,
            'slug' => str($name)->slug(),
            'region_id' => $this->region->id,
            'type' => 'Trek',
            'short_description' => 'A walk.',
            'duration_type' => 'multi_day',
            'base_cost_per_person' => 1000,
            'approval_status' => $approval,
            'is_active' => $active,
        ], $extra));
    }

    /** @return string[] */
    private function filterBy(?string $status): array
    {
        $payload = ['get_experiences_list' => 1];
        if ($status !== null) {
            $payload['status'] = $status;
        }
        $res = $this->actingAs($this->admin)
            ->post("http://{$this->admin_domain}/ajax", $payload);
        $res->assertOk();

        $names = array_column($res->json('data'), 'name');
        sort($names);
        return $names;
    }

    public function test_pending_can_be_found_from_the_experiences_list(): void
    {
        $this->experience('Waiting Trek', 'pending', false);
        $this->experience('Live Trek', 'approved', true);
        $this->experience('Unfinished Trek', 'draft', false);

        $this->assertSame(['Waiting Trek'], $this->filterBy('pending'));
        $this->assertSame(['Unfinished Trek'], $this->filterBy('draft'));
        $this->assertSame(['Live Trek'], $this->filterBy('approved'));
    }

    /**
     * The one case that would break if approval and visibility were merged.
     */
    public function test_an_approved_listing_switched_off_is_not_pending(): void
    {
        $this->experience('Winter-closed Trek', 'approved', false);
        $this->experience('Waiting Trek', 'pending', false);

        $this->assertSame(['Waiting Trek'], $this->filterBy('pending'));
        $this->assertSame(
            ['Waiting Trek', 'Winter-closed Trek'],
            $this->filterBy('0'),
            'both are switched off, whatever their review state',
        );
    }

    public function test_a_live_listing_with_a_parked_edit_still_counts_as_pending(): void
    {
        $this->experience('Revised Trek', 'approved', true, [
            'pending_changes' => ['name' => 'Revised Trek v2'],
        ]);
        $this->experience('Live Trek', 'approved', true);

        $this->assertSame(['Revised Trek'], $this->filterBy('pending'));
    }

    public function test_no_filter_still_returns_everything(): void
    {
        $this->experience('Waiting Trek', 'pending', false);
        $this->experience('Live Trek', 'approved', true);

        $this->assertCount(2, $this->filterBy(null));
    }

    public function test_the_old_pending_page_lands_on_the_filtered_list(): void
    {
        $this->actingAs($this->admin)
            ->get("http://{$this->admin_domain}/pending-experiences")
            ->assertRedirect(route('hct.experiences', ['status' => 'pending']));
    }
}
