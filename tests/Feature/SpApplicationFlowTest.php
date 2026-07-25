<?php

namespace Tests\Feature;

use App\Mail\AccountOtpEmail;
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
        ], $overrides);
    }

    /** Submit the application and return the 6-digit code we emailed. */
    private function submitAndCaptureCode(array $overrides = []): string
    {
        $this->ajax($this->validPayload($overrides))->assertOk();

        $code = null;
        Mail::assertSent(AccountOtpEmail::class, function ($mail) use (&$code) {
            $code = $mail->otp;
            return true;
        });
        $this->assertNotNull($code, 'A verification code should have been emailed.');
        return $code;
    }

    public function test_submitting_creates_a_pending_account_and_emails_a_code(): void
    {
        $res = $this->ajax($this->validPayload());

        $res->assertOk()->assertJson([
            'success' => true,
            'redirect' => '/create-password',
        ]);

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->first();
        $this->assertNotNull($provider);
        $this->assertSame('pending', $provider->status);
        $this->assertNotNull($provider->user_id);

        $user = User::find($provider->user_id);
        $this->assertSame('hlh', $user->user_role);
        // No usable password yet, and they are NOT signed in — that happens only
        // after the emailed code is confirmed on the create-password screen.
        $this->assertNull($user->password_set_at);
        $this->assertGuest();

        Mail::assertSent(AccountOtpEmail::class, fn ($m) => $m->hasTo('aarav.mehta@example.test'));
    }

    public function test_submitting_emails_the_applicant_and_hct(): void
    {
        $this->ajax($this->validPayload());

        Mail::assertSent(SpApplicationReceivedEmail::class, fn ($m) => $m->hasTo('aarav.mehta@example.test'));
        Mail::assertSent(AdminNewApplicationEmail::class);
    }

    public function test_the_create_password_page_renders_for_a_pending_applicant(): void
    {
        $this->ajax($this->validPayload());

        $this->get("http://{$this->portal}/create-password")
            ->assertOk()
            ->assertSee('Application submitted')
            ->assertSee('aarav.mehta@example.test');
    }

    public function test_the_create_password_page_bounces_a_visitor_with_no_pending_session(): void
    {
        $this->get("http://{$this->portal}/create-password")
            ->assertRedirect('/join');
    }

    public function test_verifying_the_code_sets_the_password_and_signs_them_in(): void
    {
        $code = $this->submitAndCaptureCode();

        $this->ajax([
            'verify_and_set_password' => 1,
            'otp' => $code,
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
        ])->assertOk()->assertJson([
            'success' => true,
            'redirect' => '/application-status',
        ]);

        $user = User::where('email', 'aarav.mehta@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Passw0rd!', $user->password));
        $this->assertNotNull($user->password_set_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $code = $this->submitAndCaptureCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->ajax([
            'verify_and_set_password' => 1,
            'otp' => $wrong,
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
        ])->assertStatus(422);

        $user = User::where('email', 'aarav.mehta@example.test')->firstOrFail();
        $this->assertNull($user->password_set_at);
        $this->assertGuest();
    }

    public function test_a_weak_password_is_rejected_at_verify(): void
    {
        $code = $this->submitAndCaptureCode();

        $this->ajax([
            'verify_and_set_password' => 1,
            'otp' => $code,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(422);

        $this->assertNull(User::where('email', 'aarav.mehta@example.test')->firstOrFail()->password_set_at);
    }

    public function test_verify_without_a_pending_session_is_refused(): void
    {
        // No submit first — there is no pending user tied to this session.
        $this->ajax([
            'verify_and_set_password' => 1,
            'otp' => '123456',
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
        ])->assertStatus(419);
    }

    public function test_resending_emails_a_fresh_code(): void
    {
        $this->ajax($this->validPayload())->assertOk();
        Mail::assertSent(AccountOtpEmail::class, 1);

        $this->ajax(['resend_account_otp' => 1])->assertOk()->assertJson(['success' => true]);
        Mail::assertSent(AccountOtpEmail::class, 2);
    }

    public function test_approval_after_signup_sends_a_login_notice_not_a_set_password_link(): void
    {
        $code = $this->submitAndCaptureCode();
        $this->ajax([
            'verify_and_set_password' => 1,
            'otp' => $code,
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
        ])->assertOk();

        $provider = ServiceProvider::where('email', 'aarav.mehta@example.test')->firstOrFail();
        $admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'user_role' => 'hct_admin', 'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->ajax(['approve_provider' => 1, 'provider_id' => $provider->id])
            ->assertOk();

        Mail::assertSent(SpApplicationApprovedEmail::class, fn ($mail) => $mail->setPasswordUrl === null);
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
        Storage::disk('public')->assertExists($provider->documents[0]['path']);
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

    public function test_existing_user_email_links_but_is_not_auto_logged_in(): void
    {
        $traveller = User::create([
            'full_name' => 'Existing Traveller',
            'email' => 'existing@example.test',
            'password' => 'password',
            'user_role' => 'traveller',
        ]);

        $this->ajax($this->validPayload(['email' => 'existing@example.test']))
            ->assertOk()
            ->assertJson(['redirect' => '/login', 'existing_account' => true]);

        $provider = ServiceProvider::where('email', 'existing@example.test')->firstOrFail();
        $this->assertSame($traveller->id, $provider->user_id);
        $this->assertGuest();

        // An unreviewed application must NOT change what the account can already
        // do — this used to silently demote the traveller to a provider.
        $this->assertSame('traveller', $traveller->fresh()->user_role);
    }

    public function test_approval_promotes_the_linked_account_to_the_provider_role(): void
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
            'password' => 'password', 'user_role' => 'hct_admin', 'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->ajax(['approve_provider' => 1, 'provider_id' => $provider->id])
            ->assertOk();

        $this->assertSame('hlh', $traveller->fresh()->user_role);
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
}
