<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Experiences are authored by the HLH hosts that run them — HCT keeps its own
 * admin-side control, while an HRP (regional partner) and an OSP (service
 * supplier) get no authoring at all. These drive the real POST /ajax on the
 * portal domain and cover the three things that matter: who may author, that
 * ownership is taken from the session rather than the payload, and that one
 * host cannot reach another's rows.
 */
class SpExperienceAuthoringTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected Region $region;
    protected ServiceProvider $hlh;
    /** A second host, so ownership isolation is tested between two authors. */
    protected ServiceProvider $hlh2;
    protected ServiceProvider $osp;
    protected ServiceProvider $hrp;
    /** Signed up as both a host and a service supplier. */
    protected ServiceProvider $hlhOsp;

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

        $this->hlh = $this->makeProvider('hlh', 'host@example.test');
        $this->hlh2 = $this->makeProvider('hlh', 'host2@example.test');
        $this->osp = $this->makeProvider('osp', 'osp@example.test');
        $this->hrp = $this->makeProvider('hrp', 'hrp@example.test');
        // A homestay that also runs a taxi — both catalogues at once.
        $this->hlhOsp = $this->makeProvider(['hlh', 'osp'], 'both@example.test');
    }

    /**
     * @param string|string[] $type One or more types; the first is the primary.
     */
    private function makeProvider($type, string $email, string $status = 'approved'): ServiceProvider
    {
        $types = (array) $type;
        $primary = $types[0];

        $user = User::create([
            'full_name' => strtoupper($primary) . ' User',
            'email' => $email,
            'password' => 'password',
            'user_role' => $primary,
            'status' => 'active',
        ]);

        return ServiceProvider::create([
            'user_id' => $user->id,
            'provider_type' => $primary,
            'provider_types' => $types,
            'name' => strtoupper($primary) . ' Provider',
            'email' => $email,
            'phone_1' => '9000000000',
            'region_id' => $this->region->id,
            'status' => $status,
        ]);
    }

    private function ajax(array $payload)
    {
        return $this->post("http://{$this->portal}/ajax", $payload);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'save_sp_experience' => 1,
            'name' => 'Riverside Forest Walk',
            'region_id' => $this->region->id,
            'type' => 'Nature',
            'short_description' => 'A slow morning walk along the Tirthan.',
            'duration_type' => 'single_day',
        ], $overrides);
    }

    public function test_hlh_can_create_an_experience_it_owns(): void
    {
        $this->actingAs($this->hlh->user);

        $this->ajax($this->validPayload())->assertOk()->assertJson(['success' => true]);

        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');
        $this->assertNotNull($experience);
        $this->assertSame($this->hlh->id, (int) $experience->owner_provider_id);
        $this->assertSame('hlh', $experience->owner_type);
        // An HLH hosts its own experience.
        $this->assertSame($this->hlh->id, (int) $experience->hlh_id);
    }

    /**
     * An OSP supplies services into an experience (taxi, guide, rental) but does
     * not host one, so authoring is refused the same way it is for an HRP.
     */
    public function test_osp_cannot_author_experiences(): void
    {
        $this->actingAs($this->osp->user);

        $this->ajax($this->validPayload(['name' => 'Rafting Descent']))->assertStatus(403);
        $this->assertDatabaseMissing('experiences', ['name' => 'Rafting Descent']);
    }

    public function test_hrp_cannot_author_experiences(): void
    {
        $this->actingAs($this->hrp->user);

        $this->ajax($this->validPayload())->assertStatus(403);
        $this->assertDatabaseMissing('experiences', ['name' => 'Riverside Forest Walk']);
    }

    public function test_unauthenticated_caller_is_blocked(): void
    {
        $this->ajax($this->validPayload())->assertStatus(401);
    }

    /** Ownership comes from the session, never from the posted payload. */
    public function test_owner_cannot_be_spoofed_via_payload(): void
    {
        $this->actingAs($this->hlh2->user);

        $this->ajax($this->validPayload([
            'name' => 'Spoof Attempt',
            'owner_provider_id' => $this->hlh->id,
            'owner_type' => 'hlh',
        ]))->assertOk();

        $experience = Experience::firstWhere('name', 'Spoof Attempt');
        $this->assertSame($this->hlh2->id, (int) $experience->owner_provider_id);
        $this->assertSame('hlh', $experience->owner_type);
    }

    public function test_provider_cannot_edit_another_providers_experience(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload())->assertOk();
        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');

        $this->actingAs($this->hlh2->user);
        $this->ajax($this->validPayload([
            'id' => $experience->id,
            'name' => 'Hijacked',
        ]))->assertStatus(403);

        $this->assertSame('Riverside Forest Walk', $experience->fresh()->name);
    }

    public function test_listing_returns_only_own_experiences(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload())->assertOk();

        $this->actingAs($this->hlh2->user);
        $this->ajax($this->validPayload(['name' => 'Second Host Only']))->assertOk();

        $response = $this->ajax(['get_sp_experiences' => 1])->assertOk();
        $names = collect($response->json('experiences'))->pluck('name')->all();

        $this->assertContains('Second Host Only', $names);
        $this->assertNotContains('Riverside Forest Walk', $names);
    }

    public function test_toggle_and_delete_are_scoped_to_the_owner(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload())->assertOk();
        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');

        // Another host cannot flip it.
        $this->actingAs($this->hlh2->user);
        $this->ajax(['toggle_sp_experience' => 1, 'id' => $experience->id])->assertStatus(403);
        $this->ajax(['delete_sp_experience' => 1, 'id' => $experience->id])->assertStatus(403);

        // The owner can — but only once HCT has approved it, since visibility
        // is not a provider's to grant before review.
        $this->actingAs($this->hctAdmin());
        $this->ajax(['approve_experience' => 1, 'id' => $experience->id])->assertOk();

        $this->actingAs($this->hlh->user);
        $this->ajax(['toggle_sp_experience' => 1, 'id' => $experience->id])->assertOk();
        $this->assertFalse((bool) $experience->fresh()->is_active);

        // Delete deactivates rather than destroying — trips may reference it.
        $this->ajax(['delete_sp_experience' => 1, 'id' => $experience->id])->assertOk();
        $this->assertDatabaseHas('experiences', ['id' => $experience->id, 'is_active' => false]);
    }

    /** The day-by-day itinerary posted by the app must round-trip. */
    public function test_day_wise_itinerary_is_saved_and_returned(): void
    {
        $this->actingAs($this->hlh->user);

        $this->ajax($this->validPayload([
            'name' => 'Three Day Ridge Walk',
            'duration_type' => 'multi_day',
            'duration_days' => 3,
            'experience_days' => [
                ['day_number' => 1, 'title' => 'Arrival', 'short_description' => 'Settle in.', 'inclusions' => ['dinner', 'accommodation']],
                ['day_number' => 2, 'title' => 'Ridge climb', 'short_description' => 'Long day up.', 'inclusions' => ['breakfast', 'lunch', 'guide']],
                ['day_number' => 3, 'title' => 'Descend', 'short_description' => 'Back by noon.', 'inclusions' => ['breakfast', 'transport']],
            ],
        ]))->assertOk();

        $experience = Experience::firstWhere('name', 'Three Day Ridge Walk');
        $days = $experience->days()->orderBy('day_number')->get();

        $this->assertCount(3, $days);
        $this->assertSame('Ridge climb', $days[1]->title);
        $this->assertSame(['breakfast', 'lunch', 'guide'], $days[1]->inclusions);

        // The listing the app reads back must carry them too.
        $listed = collect($this->ajax(['get_sp_experiences' => 1])->json('experiences'))
            ->firstWhere('name', 'Three Day Ridge Walk');
        $this->assertCount(3, $listed['days']);
    }

    /** Re-saving replaces the itinerary rather than appending to it. */
    public function test_resaving_replaces_the_itinerary(): void
    {
        $this->actingAs($this->hlh->user);

        $this->ajax($this->validPayload([
            'duration_type' => 'multi_day',
            'experience_days' => [
                ['day_number' => 1, 'title' => 'One'],
                ['day_number' => 2, 'title' => 'Two'],
            ],
        ]))->assertOk();
        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');
        $this->assertCount(2, $experience->days);

        $this->ajax($this->validPayload([
            'id' => $experience->id,
            'duration_type' => 'single_day',
            'experience_days' => [
                ['day_number' => 1, 'title' => 'Only day'],
            ],
        ]))->assertOk();

        $days = $experience->fresh()->days;
        $this->assertCount(1, $days);
        $this->assertSame('Only day', $days[0]->title);
    }

    // ── HCT review ───────────────────────────────────────────────────────

    /** The same reviewer every time — several tests reach for one. */
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

    public function test_a_submitted_experience_is_pending_and_not_live(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload())->assertOk();

        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');
        $this->assertSame('pending', $experience->approval_status);
        $this->assertFalse((bool) $experience->is_active);
        $this->assertSame($this->hlh->user->id, (int) $experience->submitted_by);
        $this->assertNotNull($experience->submitted_at);
    }

    /** A provider must not be able to publish its own unreviewed work. */
    public function test_a_provider_cannot_activate_a_pending_experience(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload())->assertOk();
        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');

        $this->ajax(['toggle_sp_experience' => 1, 'id' => $experience->id])->assertStatus(422);
        $this->assertFalse((bool) $experience->fresh()->is_active);
    }

    public function test_hct_approves_and_the_experience_goes_live(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload())->assertOk();
        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');

        $admin = $this->hctAdmin();
        $this->actingAs($admin);

        $this->ajax(['get_pending_experiences' => 1])
            ->assertOk()
            ->assertJsonPath('rows.0.name', 'Riverside Forest Walk');

        $this->ajax(['approve_experience' => 1, 'id' => $experience->id])->assertOk();

        $fresh = $experience->fresh();
        $this->assertSame('approved', $fresh->approval_status);
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertSame($admin->id, (int) $fresh->approved_by);
    }

    public function test_hct_rejects_with_a_reason_the_provider_can_see(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload())->assertOk();
        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');

        $this->actingAs($this->hctAdmin());
        $this->ajax([
            'reject_experience' => 1,
            'id' => $experience->id,
            'reason' => 'Please add a day-wise itinerary.',
        ])->assertOk();

        $fresh = $experience->fresh();
        $this->assertSame('rejected', $fresh->approval_status);
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertSame('Please add a day-wise itinerary.', $fresh->rejection_reason);

        // The provider sees the reason on their own listing.
        $this->actingAs($this->hlh->user);
        $listed = collect($this->ajax(['get_sp_experiences' => 1])->json('experiences'))
            ->firstWhere('name', 'Riverside Forest Walk');
        $this->assertSame('Please add a day-wise itinerary.', $listed['rejection_reason']);
    }

    /** Approve a freshly submitted experience so it is live for edit tests. */
    private function approvedExperience(): Experience
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload())->assertOk();
        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');

        $this->actingAs($this->hctAdmin());
        $this->ajax(['approve_experience' => 1, 'id' => $experience->id])->assertOk();

        return $experience->fresh();
    }

    /**
     * The point of parking revisions: a live experience keeps selling exactly
     * as approved while HCT looks at the changes.
     */
    public function test_editing_a_live_experience_leaves_the_approved_version_live(): void
    {
        $experience = $this->approvedExperience();
        $this->assertTrue((bool) $experience->is_active);

        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload([
            'id' => $experience->id,
            'name' => 'Riverside Forest Walk (revised)',
            'short_description' => 'Now with a river crossing.',
        ]))->assertOk()->assertJson(['pending_changes' => true]);

        $fresh = $experience->fresh();
        // Untouched and still selling.
        $this->assertSame('Riverside Forest Walk', $fresh->name);
        $this->assertSame('approved', $fresh->approval_status);
        $this->assertTrue((bool) $fresh->is_active);
        // But the revision is on record.
        $this->assertTrue($fresh->hasPendingChanges());
        $this->assertSame($this->hlh->user->id, (int) $fresh->pending_submitted_by);
    }

    public function test_approving_a_revision_applies_it_to_the_live_row(): void
    {
        $experience = $this->approvedExperience();

        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload([
            'id' => $experience->id,
            'name' => 'Riverside Forest Walk (revised)',
            'duration_type' => 'multi_day',
            'experience_days' => [
                ['day_number' => 1, 'title' => 'Arrive'],
                ['day_number' => 2, 'title' => 'Walk out'],
            ],
        ]))->assertOk();

        $this->actingAs($this->hctAdmin());
        $this->ajax(['approve_experience' => 1, 'id' => $experience->id])->assertOk();

        $fresh = $experience->fresh();
        $this->assertSame('Riverside Forest Walk (revised)', $fresh->name);
        $this->assertSame('approved', $fresh->approval_status);
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertFalse($fresh->hasPendingChanges());
        $this->assertCount(2, $fresh->days);
    }

    public function test_rejecting_a_revision_discards_it_and_keeps_the_live_version(): void
    {
        $experience = $this->approvedExperience();

        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload([
            'id' => $experience->id,
            'name' => 'Riverside Forest Walk (revised)',
        ]))->assertOk();

        $this->actingAs($this->hctAdmin());
        $this->ajax([
            'reject_experience' => 1,
            'id' => $experience->id,
            'reason' => 'The new title is confusing.',
        ])->assertOk()->assertJson(['kept_live' => true]);

        $fresh = $experience->fresh();
        $this->assertSame('Riverside Forest Walk', $fresh->name);
        $this->assertSame('approved', $fresh->approval_status);
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertFalse($fresh->hasPendingChanges());
        $this->assertSame('The new title is confusing.', $fresh->rejection_reason);
    }

    /** A revision has to reach HCT's queue or it would sit unseen forever. */
    public function test_a_revision_appears_in_the_review_queue(): void
    {
        $experience = $this->approvedExperience();

        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload([
            'id' => $experience->id,
            'name' => 'Riverside Forest Walk (revised)',
        ]))->assertOk();

        $this->actingAs($this->hctAdmin());
        $rows = $this->ajax(['get_pending_experiences' => 1])->assertOk()->json('rows');

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['has_pending_changes']);
    }

    // ── Photos ───────────────────────────────────────────────────────────

    public function test_a_card_image_can_be_uploaded_with_a_new_experience(): void
    {
        $this->actingAs($this->hlh->user);

        $this->post("http://{$this->portal}/ajax", $this->validPayload([
            'card_image' => UploadedFile::fake()->image('cottage.jpg', 900, 600),
        ]))->assertOk();

        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');
        $this->assertNotEmpty($experience->card_image);
        $this->assertStringContainsString('experiences', $experience->card_image);
    }

    /**
     * A photo on a revision must not replace the live one before approval —
     * the uploaded file is kept aside and only applied when HCT accepts it.
     */
    public function test_a_photo_on_a_revision_only_lands_once_approved(): void
    {
        $experience = $this->approvedExperience();
        $originalImage = $experience->card_image;

        $this->actingAs($this->hlh->user);
        $this->post("http://{$this->portal}/ajax", $this->validPayload([
            'id' => $experience->id,
            'card_image' => UploadedFile::fake()->image('new-cottage.jpg', 900, 600),
        ]))->assertOk();

        // Live row still shows the approved photo.
        $this->assertSame($originalImage, $experience->fresh()->card_image);

        $this->actingAs($this->hctAdmin());
        $this->ajax(['approve_experience' => 1, 'id' => $experience->id])->assertOk();

        $fresh = $experience->fresh();
        $this->assertNotEmpty($fresh->card_image);
        $this->assertNotSame($originalImage, $fresh->card_image);
    }

    /** An experience that was never approved is still edited in place. */
    public function test_editing_a_pending_experience_still_overwrites_it(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload())->assertOk();
        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');

        $this->ajax($this->validPayload([
            'id' => $experience->id,
            'name' => 'Renamed Before Review',
        ]))->assertOk();

        $fresh = $experience->fresh();
        $this->assertSame('Renamed Before Review', $fresh->name);
        $this->assertFalse($fresh->hasPendingChanges());
        $this->assertSame('pending', $fresh->approval_status);
    }

    /** Approval state is HCT's alone — a provider cannot post its way to live. */
    public function test_a_provider_cannot_self_approve_via_payload(): void
    {
        $this->actingAs($this->hlh->user);

        $this->ajax($this->validPayload([
            'approval_status' => 'approved',
            'is_active' => 1,
        ]))->assertOk();

        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');
        $this->assertSame('pending', $experience->approval_status);
        $this->assertFalse((bool) $experience->is_active);
    }

    public function test_a_provider_cannot_reach_the_review_actions(): void
    {
        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload())->assertOk();
        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');

        $this->ajax(['get_pending_experiences' => 1])->assertStatus(403);
        $this->ajax(['approve_experience' => 1, 'id' => $experience->id])->assertStatus(403);
        $this->ajax(['reject_experience' => 1, 'id' => $experience->id])->assertStatus(403);
    }

    // ── Pages ────────────────────────────────────────────────────────────

    public function test_the_sp_experiences_page_renders_for_a_host(): void
    {
        $this->actingAs($this->hlh->user)
            ->get("http://{$this->portal}/sp/experiences")
            ->assertOk()
            ->assertSee('My Experiences')
            ->assertSee('Day-wise Itinerary');
    }

    /**
     * Neither an HRP (regional partner) nor an OSP (service supplier) hosts an
     * experience, so the page is refused for both.
     */
    public function test_the_sp_experiences_page_is_refused_for_non_hosts(): void
    {
        foreach ([$this->hrp, $this->osp] as $provider) {
            $this->actingAs($provider->user)
                ->get("http://{$this->portal}/sp/experiences")
                ->assertRedirect(route('sp.dashboard'));
        }
    }

    /** The page is reached from the dashboard, so the link has to be there. */
    public function test_the_dashboard_links_to_experiences_for_a_host(): void
    {
        $this->actingAs($this->hlh->user)
            ->get("http://{$this->portal}/sp/dashboard")
            ->assertOk()
            ->assertSee('My Experiences')
            ->assertSee('/sp/experiences');
    }

    public function test_the_dashboard_hides_experiences_from_non_hosts(): void
    {
        foreach ([$this->hrp, $this->osp] as $provider) {
            $this->actingAs($provider->user)
                ->get("http://{$this->portal}/sp/dashboard")
                ->assertOk()
                ->assertDontSee('My Experiences');
        }
    }

    /**
     * Only a provider who is both sees both catalogues, so the cross-link is
     * exercised through one — a pure host has no rate card to link from.
     */
    public function test_the_pricing_page_links_across_to_experiences(): void
    {
        $this->actingAs($this->hlhOsp->user)
            ->get("http://{$this->portal}/sp/pricing")
            ->assertOk()
            ->assertSee('/sp/experiences');
    }

    /**
     * A rate card is what an OSP sells. A pure host has none, so the page is
     * refused and the dashboard card is hidden.
     */
    public function test_the_pricing_page_is_refused_for_a_pure_host(): void
    {
        $this->actingAs($this->hlh->user)
            ->get("http://{$this->portal}/sp/pricing")
            ->assertRedirect(route('sp.dashboard'));

        $this->actingAs($this->hlh->user)
            ->get("http://{$this->portal}/sp/dashboard")
            ->assertOk()
            ->assertDontSee('Services, Rooms &amp; Pricing', false);
    }

    /**
     * The client's screen 5 allows several answers: an HLH that also runs a
     * taxi ticks OSP too and must get BOTH catalogues, not one or the other.
     */
    public function test_a_host_that_also_supplies_services_gets_both_catalogues(): void
    {
        $this->actingAs($this->hlhOsp->user)
            ->get("http://{$this->portal}/sp/dashboard")
            ->assertOk()
            ->assertSee('My Experiences')
            ->assertSee('Services, Rooms &amp; Pricing', false);

        $this->actingAs($this->hlhOsp->user)
            ->get("http://{$this->portal}/sp/experiences")
            ->assertOk();
    }

    /**
     * A provider who is both must be shown as both. The dashboard used to read
     * the single primary type, so a host that also supplied services appeared
     * to be only a host.
     */
    public function test_the_dashboard_names_every_role_a_provider_holds(): void
    {
        $this->actingAs($this->hlhOsp->user)
            ->get("http://{$this->portal}/sp/dashboard")
            ->assertOk()
            ->assertSee('Heco Local Host (HLH)')
            ->assertSee('Other Service Provider (OSP)');

        // A pure host is not mislabelled as a supplier.
        $this->actingAs($this->hlh->user)
            ->get("http://{$this->portal}/sp/dashboard")
            ->assertOk()
            ->assertSee('Heco Local Host (HLH)')
            ->assertDontSee('Other Service Provider (OSP)');
    }

    /** Authoring follows the type set, not the single primary type. */
    public function test_a_provider_whose_primary_type_is_not_hlh_can_still_author(): void
    {
        $ospThenHost = $this->makeProvider(['osp', 'hlh'], 'osp-host@example.test');
        $this->actingAs($ospThenHost->user);

        $this->ajax($this->validPayload(['name' => 'Taxi Owner Homestay Walk']))->assertOk();

        $experience = Experience::firstWhere('name', 'Taxi Owner Homestay Walk');
        $this->assertSame($ospThenHost->id, (int) $experience->owner_provider_id);
    }

    /** An old row predating provider_types still resolves from its enum. */
    public function test_a_provider_with_no_type_set_falls_back_to_its_primary_type(): void
    {
        $this->hlh->forceFill(['provider_types' => null])->save();

        $this->actingAs($this->hlh->user);
        $this->ajax($this->validPayload())->assertOk();
    }

    /**
     * The provider form must offer the same fields the HCT form does — this is
     * the whole point of letting providers author their own experiences. Guards
     * against a field being added to the admin form and quietly missing here.
     */
    public function test_the_sp_form_offers_every_field_the_admin_form_does(): void
    {
        $adminForm = file_get_contents(resource_path('views/admin/experiences/form.blade.php'));
        preg_match_all('/name="([a-zA-Z_0-9]+)(\[\])?"/', $adminForm, $matches);

        // Set by the server or reserved for HCT: ownership, catalogue order and
        // the review state are deliberately not a provider's to post.
        $hctOnly = ['id', 'hlh_id', 'is_active', 'sort_order', '_token'];

        $expected = array_diff(array_unique($matches[1]), $hctOnly);
        $this->assertNotEmpty($expected, 'Could not read the admin form fields.');

        $spForm = $this->actingAs($this->hlh->user)
            ->get("http://{$this->portal}/sp/experiences")
            ->assertOk()
            ->getContent();

        $missing = [];
        foreach ($expected as $field) {
            // Array inputs post as `field[]`, so accept either spelling.
            $present = str_contains($spForm, 'name="' . $field . '"')
                || str_contains($spForm, 'name="' . $field . '[]"');
            if (!$present) {
                $missing[] = $field;
            }
        }

        $this->assertSame([], $missing, 'Missing from the provider form: ' . implode(', ', $missing));
    }

    /**
     * Review no longer has its own page — it happens in the Experiences list,
     * filtered. The old URL still has to land somewhere useful.
     */
    public function test_the_admin_reviews_experiences_in_the_experiences_list(): void
    {
        $admin = $this->hctAdmin();

        $this->actingAs($admin)
            ->get('http://' . config('app.admin_domain') . '/pending-experiences')
            ->assertRedirect(route('hct.experiences', ['status' => 'pending']));

        $this->actingAs($admin)
            ->get('http://' . config('app.admin_domain') . '/experiences')
            ->assertOk()
            ->assertSee('Pending review');
    }

    public function test_pending_provider_cannot_author(): void
    {
        $pending = $this->makeProvider('hlh', 'pending@example.test', 'pending');
        $this->actingAs($pending->user);

        $this->ajax($this->validPayload(['name' => 'Too Early']))->assertStatus(403);
        $this->assertDatabaseMissing('experiences', ['name' => 'Too Early']);
    }
}
