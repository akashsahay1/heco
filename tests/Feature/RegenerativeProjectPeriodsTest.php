<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\RegenerativeProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Active and paused periods used to be textareas asking HCT to hand-write a
 * JSON array of period objects. Nobody running this dashboard should have to
 * know JSON, and one stray comma silently emptied the field.
 *
 * They are now rows of two dates. The old JSON shape still has to load, so a
 * project saved before the change does not lose its periods.
 */
class RegenerativeProjectPeriodsTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminDomain;
    protected User $admin;
    protected Region $region;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminDomain = config('app.admin_domain');
        $this->admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'user_role' => 'hct_admin', 'status' => 'active',
        ]);
        $this->region = Region::create([
            'name' => 'Kumaon', 'slug' => 'kumaon', 'country' => 'India', 'is_active' => true,
        ]);
    }

    private function save(array $overrides = []): RegenerativeProject
    {
        $this->actingAs($this->admin)
            ->post("http://{$this->adminDomain}/ajax", array_merge([
                'save_regenerative_project' => 1,
                'name' => 'Gori Ganga Riverbank Restoration',
                'region_id' => $this->region->id,
                'action_type' => 'River & Aquatic Habitat',
                'short_description' => 'Restoring native trout spawning grounds along the Gori Ganga.',
                'impact_unit' => 'metres of riverbank',
            ], $overrides))
            ->assertOk();

        return RegenerativeProject::firstWhere('name', 'Gori Ganga Riverbank Restoration');
    }

    public function test_periods_are_saved_as_date_rows(): void
    {
        $project = $this->save([
            'active_periods' => [
                ['start' => '2026-03-01', 'end' => '2026-06-30'],
                ['start' => '2026-09-01', 'end' => '2026-11-30'],
            ],
            'paused_periods' => [
                ['start' => '2026-07-01', 'end' => '2026-08-31'],
            ],
        ]);

        $this->assertSame([
            ['start' => '2026-03-01', 'end' => '2026-06-30'],
            ['start' => '2026-09-01', 'end' => '2026-11-30'],
        ], $project->active_periods);

        $this->assertSame([
            ['start' => '2026-07-01', 'end' => '2026-08-31'],
        ], $project->paused_periods);
    }

    public function test_the_empty_row_a_repeater_leaves_behind_is_dropped(): void
    {
        $project = $this->save([
            'active_periods' => [
                ['start' => '2026-03-01', 'end' => '2026-06-30'],
                ['start' => '', 'end' => ''],
            ],
        ]);

        $this->assertCount(1, $project->active_periods);
    }

    public function test_a_half_filled_row_is_kept(): void
    {
        $project = $this->save([
            'active_periods' => [['start' => '2026-03-01', 'end' => '']],
        ]);

        $this->assertSame(
            [['start' => '2026-03-01', 'end' => null]],
            $project->active_periods,
            'an open-ended period is a real answer',
        );
    }

    public function test_a_project_saved_as_json_before_the_change_still_loads(): void
    {
        $project = $this->save([
            'active_periods' => '[{"start":"2025-04-01","end":"2025-10-31"}]',
        ]);

        $this->assertSame(
            [['start' => '2025-04-01', 'end' => '2025-10-31']],
            $project->active_periods,
        );
    }

    public function test_the_form_no_longer_asks_for_json(): void
    {
        $this->actingAs($this->admin)
            ->get("http://{$this->adminDomain}/regenerative-projects/create")
            ->assertOk()
            ->assertDontSee('JSON array')
            ->assertSee('Add period');
    }
}
