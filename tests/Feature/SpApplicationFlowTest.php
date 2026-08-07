<?php

namespace Tests\Feature;

use App\Mail\AdminNewApplicationEmail;
use App\Mail\SpApplicationReceivedEmail;
use App\Mail\SpApplicationApprovedEmail;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The public "Become a Partner" flow. Submitting the multi-step application
 * creates a pending provider + a login, and emails a one-time code. The
 * applicant verifies the code and sets a password on the site (no email link),
 * then tracks status until approval — mirroring the mobile app's onboarding.
 */
class SpApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected Region $region;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->portal = config('app.portal_domain');
        $this->region = Region::create([
            'name' => 'Tirthan Valley',
            'slug' => 'tirthan-valley',
            'country' => 'India',
            'is_active' => true,
        ]);
    }

    private function ajax(array $payload)
    {
        return $this->post("http://{$this->portal}/ajax", $payload);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'submit_sp_application' => 1,
            'provider_type' => 'hlh',
            'name' => 'Green Valley Homestay',
            'contact_person' => 'Aarav Mehta',
            'email' => 'aarav.mehta@example.test',
            'phone_1' => '9812345678',
            'region_id' => $this->region->id,
            'address' => 'Gushaini, Banjar',
            'services_offered' => ['Accommodation', 'Guide'],
            'accommodation_categories' => ['Cat C - Standard'],
            'guide_types' => ['Local Guide'],
            'description' => 'We host travellers in a riverside homestay with local guides and home food.',
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
        ], $overrides);
    }

    /**
     * The client's screen 5 allows several roles at once — a host that also
     * runs a taxi ticks HLH and OSP. The primary type still lands in the enum
     * column so nothing that reads it has to change.
     */
    public function test_an_applicant_can_be_more_than_one_type(): void
    {
        $this->ajax($this->validPayload([
            'provider_type' => 'hlh',
            'provider_types' => ['hlh', 'osp'],
        ]))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->first();

        $this->assertSame('hlh', $provider->provider_type);
        $this->assertEqualsCanonicalizing(['hlh', 'osp'], $provider->provider_types);
        $this->assertTrue($provider->isHost());
        $this->assertTrue($provider->suppliesServices());
        $this->assertFalse($provider->isRegionalPartner());
    }

    /** The older single-type web form still produces a usable set. */
    public function test_a_single_type_application_still_fills_the_set(): void
    {
        $this->ajax($this->validPayload(['provider_type' => 'osp']))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->first();

        $this->assertSame(['osp'], $provider->provider_types);
        $this->assertTrue($provider->suppliesServices());
        $this->assertFalse($provider->isHost());
    }

    /** A junk role must not reach the column. */
    public function test_an_unknown_type_in_the_set_is_rejected(): void
    {
        $this->ajax($this->validPayload([
            'provider_types' => ['hlh', 'hacker'],
        ]))->assertStatus(422);

        $this->assertDatabaseMissing('service_providers', ['email' => 'aarav.mehta@example.test']);
    }

    /** Screen 6 — which languages a member speaks decides who they can host. */
    public function test_spoken_languages_are_stored(): void
    {
        $this->ajax($this->validPayload([
            'speaks_english' => true,
            'speaks_hindi' => true,
            'other_languages' => 'Pahari, Ladakhi',
        ]))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->first();

        $this->assertTrue($provider->speaks_english);
        $this->assertTrue($provider->speaks_hindi);
        $this->assertSame('Pahari, Ladakhi', $provider->other_languages);
    }

    /**
     * Screen 7 — answering No skips the business screen, so nothing from it may
     * be stored. A member without a business must not be given a business type.
     */
    public function test_answering_no_to_business_stores_no_business_details(): void
    {
        $this->ajax($this->validPayload([
            'has_business' => false,
            'business_type' => 'Registered company',
            'registration_number' => 'U74999HP2020PTC00001',
            'year_established' => '2020',
        ]))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->first();

        $this->assertFalse($provider->has_business);
        $this->assertNull($provider->business_type);
        $this->assertNull($provider->registration_number);
        $this->assertNull($provider->year_established);
    }

    public function test_answering_yes_to_business_keeps_the_details(): void
    {
        $this->ajax($this->validPayload([
            'has_business' => true,
            'business_type' => 'Registered company',
            'registration_number' => 'U74999HP2020PTC00001',
            'year_established' => '2020',
        ]))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->first();

        $this->assertTrue($provider->has_business);
        $this->assertSame('Registered company', $provider->business_type);
        $this->assertSame('U74999HP2020PTC00001', $provider->registration_number);
    }

    /**
     * Screen 8 — "whatever has been selected in your screen 5 comes here but
     * with the different categories". A host that also runs a taxi declares
     * from both lists, and the two are stored apart because an HLH's
     * experiential accommodation and an OSP's standard accommodation are
     * different products that merely share a word.
     */
    public function test_a_dual_role_member_declares_from_both_category_lists(): void
    {
        $this->ajax($this->validPayload([
            'provider_type' => 'hlh',
            'provider_types' => ['hlh', 'osp'],
            'experience_categories' => ['Experiential accommodation'],
            'service_categories' => ['Taxi services'],
            'other_services' => 'Airport pickups at odd hours',
        ]))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->first();

        $this->assertSame(['Experiential accommodation'], $provider->experience_categories);
        $this->assertSame(['Taxi services'], $provider->service_categories);
        $this->assertSame('Airport pickups at odd hours', $provider->other_services);
    }

    /** Categories belong to a role — picks for a role they did not take are dropped. */
    public function test_categories_for_a_role_they_did_not_pick_are_discarded(): void
    {
        $this->ajax($this->validPayload([
            'provider_type' => 'hlh',
            'provider_types' => ['hlh'],
            'experience_categories' => ['Experiential accommodation'],
            'service_categories' => ['Taxi services'],
            'other_services' => 'Should not be stored',
        ]))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->first();

        $this->assertSame(['Experiential accommodation'], $provider->experience_categories);
        $this->assertNull($provider->service_categories);
        $this->assertNull($provider->other_services);
    }

    /**
     * The client asked for WhatsApp/SMS alongside email "since many users won't
     * regularly check their email" — an approval notice nobody reads is worse
     * than one message too many.
     */
    public function test_contact_consent_is_stored(): void
    {
        $this->ajax($this->validPayload([
            'contact_by_email' => true,
            'contact_by_whatsapp' => false,
        ]))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->first();

        $this->assertTrue($provider->contact_by_email);
        $this->assertFalse($provider->contact_by_whatsapp);
    }

    /** A caller that never asks means "reachable", not "declined". */
    public function test_contact_consent_defaults_to_reachable(): void
    {
        $this->ajax($this->validPayload())->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->first();

        $this->assertTrue($provider->contact_by_email);
        $this->assertTrue($provider->contact_by_whatsapp);
    }

    /** The client capped uploads at 2 MB, and the app is not the only caller. */
    public function test_an_oversized_document_is_rejected(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->ajax($this->validPayload([
            'documents' => [
                \Illuminate\Http\UploadedFile::fake()->create('id.pdf', 3000), // 3 MB
            ],
            'document_labels' => ['Government ID'],
        ]))->assertStatus(422);

        $this->assertDatabaseMissing('service_providers', ['email' => 'aarav.mehta@example.test']);
    }

    public function test_a_document_of_the_wrong_type_is_rejected(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->ajax($this->validPayload([
            'documents' => [
                \Illuminate\Http\UploadedFile::fake()->create('payload.exe', 10),
            ],
            'document_labels' => ['Government ID'],
        ]))->assertStatus(422);
    }

    public function test_submitting_creates_the_account_and_signs_them_in(): void
    {
        $res = $this->ajax($this->validPayload());

        $res->assertOk()->assertJson([
            'success' => true,
            'redirect' => '/application-status',
        ]);

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->first();
        $this->assertNotNull($provider);
        $this->assertSame('pending', $provider->status);
        $this->assertNotNull($provider->user_id);

        $user = User::find($provider->user_id);
        // 'provider', not 'hlh': the account says it is a partner, and which
        // kinds of partner lives on the provider record, which can hold more
        // than one.
        $this->assertSame('provider', $user->user_role);
        $this->assertSame(['hlh'], $provider->provider_types);
        // The password came from the form, so they are signed in immediately.
        $this->assertTrue(Hash::check('Passw0rd!', $user->password));
        $this->assertNotNull($user->password_set_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_submitting_emails_both_the_applicant_and_hct(): void
    {
        $this->ajax($this->validPayload());

        Mail::assertSent(SpApplicationReceivedEmail::class, fn ($m) => $m->hasTo('aarav.mehta@example.test'));
        Mail::assertSent(AdminNewApplicationEmail::class);
    }

    public function test_approval_after_signup_sends_a_login_notice_not_a_set_password_link(): void
    {
        $this->ajax($this->validPayload());

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->firstOrFail();
        $admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'user_role' => 'administrator', 'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->ajax(['approve_provider' => 1, 'provider_id' => $provider->id])
            ->assertOk();

        Mail::assertSent(SpApplicationApprovedEmail::class, fn ($mail) => $mail->setPasswordUrl === null);
    }

    public function test_a_weak_password_is_rejected_at_signup(): void
    {
        $this->ajax($this->validPayload([
            'password' => 'password',
            'password_confirmation' => 'password',
        ]))->assertStatus(422);

        $this->assertDatabaseMissing('service_providers', ['email' => 'aarav.mehta@example.test']);
    }

    public function test_capability_arrays_and_documents_are_stored(): void
    {
        Storage::fake('public');

        $this->ajax($this->validPayload([
            'services_offered' => ['Accommodation', 'Transport', 'Guide', 'Activity'],
            'accommodation_categories' => ['Cat C - Standard'],
            'vehicle_types' => ['SUV (Innova/Crysta)'],
            'guide_types' => ['Local Guide'],
            'activity_types' => ['Trek', 'Nature Walk'],
            'documents' => [UploadedFile::fake()->create('id-proof.pdf', 12)],
            'document_labels' => ['ID Proof'],
        ]))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Accommodation', 'Transport', 'Guide', 'Activity'], $provider->services_offered);
        $this->assertSame(['SUV (Innova/Crysta)'], $provider->vehicle_types);
        $this->assertEqualsCanonicalizing(['Trek', 'Nature Walk'], $provider->activity_types);

        $this->assertCount(1, $provider->documents);
        $this->assertSame('ID Proof', $provider->documents[0]['label']);
        // Under public/uploads, not the storage disk: a document sits beside
        // the applicant's photo, and the path it is stored as is the URL it is
        // served from — no symlink in between, because the shared hosting this
        // deploys to cannot make one.
        $this->assertFileExists(public_path(ltrim($provider->documents[0]['path'], '/')));
    }

    /** The business-identity + postal-address fields the design's steps 3 & 5 ask for. */
    public function test_business_and_address_fields_are_stored(): void
    {
        $this->ajax($this->validPayload([
            'business_type' => 'Registered company',
            'registration_number' => 'UDYAM-HP-01-1234',
            'year_established' => '2019',
            'address' => 'Hawa Mahal Road 24',
            'city' => 'Jaipur',
            'postal_code' => '302002',
            'country' => 'India',
        ]))->assertOk();

        $p = ServiceProvider::where('email', 'aarav.mehta@example.test')->firstOrFail();
        $this->assertSame('Registered company', $p->business_type);
        $this->assertSame('UDYAM-HP-01-1234', $p->registration_number);
        $this->assertSame(2019, (int) $p->year_established);
        $this->assertSame('Hawa Mahal Road 24', $p->address);
        $this->assertSame('Jaipur', $p->city);
        $this->assertSame('302002', $p->postal_code);
        $this->assertSame('India', $p->country);
    }

    public function test_duplicate_provider_email_is_rejected(): void
    {
        $this->ajax($this->validPayload());
        $this->ajax($this->validPayload(['name' => 'Second Try']))->assertStatus(422);
    }

    /**
     * Someone who already travels with HECO and then applies to host gets a
     * second account on the same address, not a rewritten first one.
     *
     * The application used to be linked to whatever account held that email,
     * which meant a traveller lost their identity to gain a provider one. Both
     * accounts are real now — that is what users_email_role_unique allows.
     */
    public function test_a_traveller_who_applies_gets_a_separate_provider_account(): void
    {
        $traveller = User::create([
            'full_name' => 'Existing Traveller',
            'email' => 'existing@example.test',
            'password' => 'password',
            'user_role' => 'traveller',
        ]);

        $this->ajax($this->validPayload(['email' => 'existing@example.test']))
            ->assertOk()
            ->assertJson(['redirect' => '/application-status', 'existing_account' => false]);

        $provider = ServiceProvider::where('email', 'existing@example.test')->firstOrFail();
        $providerUser = User::find($provider->user_id);

        $this->assertNotSame($traveller->id, $providerUser->id);
        $this->assertSame('provider', $providerUser->user_role);

        // The traveller account is untouched — same role, and it is not the one
        // now signed in.
        $this->assertSame('traveller', $traveller->fresh()->user_role);
        $this->assertAuthenticatedAs($providerUser);
    }

    /**
     * Approval must not reach into the traveller account that happens to share
     * the address. It used to: the role was overwritten, and the person lost
     * their traveller identity to gain a provider one. They are two accounts
     * now, which is what the email+role index exists to allow.
     */
    public function test_approval_leaves_the_traveller_account_alone(): void
    {
        $traveller = User::create([
            'full_name' => 'Existing Traveller',
            'email' => 'existing@example.test',
            'password' => 'password',
            'user_role' => 'traveller',
        ]);
        $this->ajax($this->validPayload(['email' => 'existing@example.test']))->assertOk();

        $provider = ServiceProvider::where('email', 'existing@example.test')->firstOrFail();
        $admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'user_role' => 'administrator', 'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->ajax(['approve_provider' => 1, 'provider_id' => $provider->id])
            ->assertOk();

        $this->assertSame('traveller', $traveller->fresh()->user_role);

        // The application got an account of its own, on the same address.
        $providerUser = User::find($provider->fresh()->user_id);
        $this->assertNotNull($providerUser);
        $this->assertNotSame($traveller->id, $providerUser->id);
        $this->assertSame('provider', $providerUser->user_role);
    }

    public function test_pending_applicant_is_bounced_from_dashboard_to_status(): void
    {
        $this->ajax($this->validPayload());
        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->firstOrFail();
        $user = User::find($provider->user_id);

        $this->actingAs($user)
            ->get("http://{$this->portal}/sp/dashboard")
            ->assertRedirect('/application-status');
    }

    public function test_status_page_shows_the_timeline_for_a_pending_applicant(): void
    {
        $this->ajax($this->validPayload());
        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->firstOrFail();
        $user = User::find($provider->user_id);

        $this->actingAs($user)
            ->get("http://{$this->portal}/application-status")
            ->assertOk()
            ->assertSee('under review')
            ->assertSee('Application submitted');
    }

    public function test_approved_provider_sees_the_approved_screen_with_dashboard_link(): void
    {
        $this->ajax($this->validPayload());
        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->firstOrFail();
        $provider->update(['status' => 'approved']);
        $user = User::find($provider->user_id);

        $this->actingAs($user)
            ->get("http://{$this->portal}/application-status")
            ->assertOk()
            ->assertSee('Welcome to HECO')
            ->assertSee('Go to dashboard');
    }

    /**
     * Screen 10 collects the profile picture among the documents, and the
     * member who uploaded their own face expects to see it — not their
     * initials — the first time they open their profile.
     */
    public function test_the_profile_photo_document_also_becomes_the_avatar(): void
    {
        Storage::fake('public');

        $this->ajax($this->validPayload([
            'documents' => [
                UploadedFile::fake()->create('id.pdf', 12),
                UploadedFile::fake()->image('me.jpg', 800, 800),
            ],
            'document_labels' => ['Government ID', 'Profile photo'],
        ]))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->firstOrFail();

        // The avatar goes through ImageUploadService, which writes under
        // public/uploads and returns a web-relative path — not the documents disk.
        $this->assertNotEmpty($provider->photo, 'the uploaded profile photo never became the avatar');
        $this->assertFileExists(public_path(ltrim($provider->photo, '/')));
        // It remains a document too — HCT still verifies what was submitted.
        $this->assertCount(2, $provider->documents);

        @unlink(public_path(ltrim($provider->photo, '/')));
    }

    /** Without that slot filled there is simply no avatar — not a broken path. */
    public function test_an_applicant_who_uploads_no_photo_has_no_avatar(): void
    {
        Storage::fake('public');

        $this->ajax($this->validPayload([
            'documents' => [UploadedFile::fake()->create('id.pdf', 12)],
            'document_labels' => ['Government ID'],
        ]))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->firstOrFail();
        $this->assertNull($provider->photo);
    }

    /** HCT can rename the slot; the pairing is a setting, not a literal. */
    public function test_the_avatar_slot_is_configurable(): void
    {
        Storage::fake('public');
        \App\Models\Setting::setValue('signup_avatar_document', 'Passport photo');

        $this->ajax($this->validPayload([
            'documents' => [UploadedFile::fake()->image('me.jpg', 800, 800)],
            'document_labels' => ['Passport photo'],
        ]))->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->firstOrFail();
        $this->assertNotEmpty($provider->photo);

        @unlink(public_path(ltrim($provider->photo, '/')));
    }
}
