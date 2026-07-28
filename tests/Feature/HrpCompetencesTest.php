<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An HRP sells nothing, so instead of a catalogue it keeps a competences
 * profile — the client's data-collection spec asks for education, English and
 * computer levels, work experience, and two qualitative notes. These cover that
 * the profile saves, that it is refused to providers it does not belong to, and
 * that the pages show it to the right people.
 */
class HrpCompetencesTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected Region $region;
    protected ServiceProvider $hrp;
    protected ServiceProvider $osp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        $this->region = Region::create([
            'name' => 'Tirthan Valley',
            'slug' => 'tirthan-valley',
            'country' => 'India',
            'is_active' => true,
        ]);

        $this->hrp = $this->makeProvider(['hrp'], 'hrp@example.test');
        $this->osp = $this->makeProvider(['osp'], 'osp@example.test');
    }

    private function makeProvider(array $types, string $email): ServiceProvider
    {
        $user = User::create([
            'full_name' => strtoupper($types[0]) . ' User',
            'email' => $email,
            'password' => 'password',
            'user_role' => $types[0],
            'status' => 'active',
        ]);

        return ServiceProvider::create([
            'user_id' => $user->id,
            'provider_type' => $types[0],
            'provider_types' => $types,
            'name' => strtoupper($types[0]) . ' Provider',
            'email' => $email,
            'phone_1' => '9000000000',
            'region_id' => $this->region->id,
            'status' => 'approved',
        ]);
    }

    private function ajax(array $payload)
    {
        return $this->post("http://{$this->portal}/ajax", $payload);
    }

    private function competencePayload(array $overrides = []): array
    {
        return array_merge([
            'update_sp_profile' => 1,
            'name' => 'HRP Provider',
            'education_level' => "Bachelor's degree",
            'education_notes' => 'BA Geography, HP University, 2016',
            'english_level' => 'Conversational',
            'computer_skill_level' => 'Intermediate',
            'work_experience' => [
                ['role' => 'Field coordinator', 'organisation' => 'Forest Dept', 'years' => '2018-2021', 'description' => 'Ran village outreach.'],
            ],
            'causes_note' => 'Volunteer with a river clean-up collective.',
            'community_note' => 'Born in the valley; speak Pahari and Hindi.',
        ], $overrides);
    }

    public function test_an_hrp_can_save_its_competences(): void
    {
        $this->actingAs($this->hrp->user);
        $this->ajax($this->competencePayload())->assertOk();

        $hrp = $this->hrp->fresh();
        $this->assertSame("Bachelor's degree", $hrp->education_level);
        $this->assertSame('Conversational', $hrp->english_level);
        $this->assertSame('Intermediate', $hrp->computer_skill_level);
        $this->assertSame('Field coordinator', $hrp->work_experience[0]['role']);
        $this->assertStringContainsString('river clean-up', $hrp->causes_note);
        $this->assertStringContainsString('Pahari', $hrp->community_note);
    }

    /** Blank repeater rows are noise, not data. */
    public function test_empty_work_experience_rows_are_dropped(): void
    {
        $this->actingAs($this->hrp->user);
        $this->ajax($this->competencePayload([
            'work_experience' => [
                ['role' => 'Guide', 'organisation' => '', 'years' => '', 'description' => ''],
                ['role' => '', 'organisation' => '', 'years' => '', 'description' => ''],
            ],
        ]))->assertOk();

        $this->assertCount(1, $this->hrp->fresh()->work_experience);
    }

    /**
     * Competences belong to a regional partner. A provider who is not one must
     * not be able to stuff the columns by posting the fields directly.
     */
    public function test_a_non_partner_cannot_set_competences(): void
    {
        $this->actingAs($this->osp->user);
        $this->ajax($this->competencePayload(['name' => 'OSP Provider']))->assertOk();

        $osp = $this->osp->fresh();
        $this->assertNull($osp->education_level);
        $this->assertNull($osp->causes_note);
        $this->assertNull($osp->work_experience);
    }

    /** A provider who is both keeps the competences half. */
    public function test_a_partner_who_also_supplies_services_keeps_competences(): void
    {
        $both = $this->makeProvider(['osp', 'hrp'], 'both@example.test');
        $this->actingAs($both->user);

        $this->ajax($this->competencePayload(['name' => 'Both Provider']))->assertOk();

        $this->assertSame('Conversational', $both->fresh()->english_level);
    }

    public function test_the_profile_page_shows_competences_only_to_a_partner(): void
    {
        $this->actingAs($this->hrp->user)
            ->get("http://{$this->portal}/sp/profile/edit")
            ->assertOk()
            ->assertSee('Competences')
            ->assertSee('Understanding of the local community');

        $this->actingAs($this->osp->user)
            ->get("http://{$this->portal}/sp/profile/edit")
            ->assertOk()
            ->assertDontSee('Competences');
    }

    /** A partner sells nothing, so the capability pickers are not theirs. */
    public function test_the_profile_page_hides_capabilities_from_a_pure_partner(): void
    {
        $this->actingAs($this->hrp->user)
            ->get("http://{$this->portal}/sp/profile/edit")
            ->assertOk()
            ->assertDontSee('Vehicle Types');
    }

    private function hctAdmin(): User
    {
        return User::firstOrCreate(
            ['email' => 'hct@example.test'],
            [
                'full_name' => 'HCT Admin',
                'password' => 'password',
                'user_role' => 'hct_admin',
                'status' => 'active',
            ],
        );
    }

    /**
     * The whole point of collecting competences is that HCT reads them when
     * deciding who to place on a region — so they have to be on the page.
     */
    public function test_hct_can_read_a_partners_competences(): void
    {
        $this->actingAs($this->hrp->user);
        $this->ajax($this->competencePayload())->assertOk();

        $admin = config('app.admin_domain');
        $this->actingAs($this->hctAdmin())
            ->get("http://{$admin}/providers/{$this->hrp->id}")
            ->assertOk()
            ->assertSee('Competences')
            ->assertSee("Bachelor's degree")
            ->assertSee('Conversational')
            ->assertSee('Field coordinator')
            ->assertSee('Forest Dept')
            ->assertSee('river clean-up');
    }

    /** A provider with no competences to show should not get an empty card. */
    public function test_the_admin_page_hides_competences_for_a_non_partner(): void
    {
        $admin = config('app.admin_domain');
        $this->actingAs($this->hctAdmin())
            ->get("http://{$admin}/providers/{$this->osp->id}")
            ->assertOk()
            ->assertDontSee('Competences');
    }
}
