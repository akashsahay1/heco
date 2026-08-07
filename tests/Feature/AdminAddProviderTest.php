<?php

namespace Tests\Feature;

use App\Mail\SpApplicationApprovedEmail;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * HCT can add a provider manually (bypassing the public application form). An
 * approved manual add must also mint the login + set-password email so the
 * provider can sign in — same side-effect as approving an application.
 */
class AdminAddProviderTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected Region $region;
    protected User $admin;
    protected User $traveller;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->portal = config('app.portal_domain');
        $this->region = Region::create([
            'name' => 'Tirthan Valley', 'slug' => 'tirthan-valley',
            'country' => 'India', 'is_active' => true,
        ]);
        $this->admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'user_role' => 'administrator', 'status' => 'active',
        ]);
        $this->traveller = User::create([
            'full_name' => 'Trav', 'email' => 'trav@example.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
    }

    private function ajax(array $payload)
    {
        return $this->post("http://{$this->portal}/ajax", $payload);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'add_provider' => 1,
            'provider_type' => 'osp',
            'name' => 'Mountain View Hotels',
            'contact_person' => 'Pradeep',
            'email' => 'newprovider@example.test',
            'phone_1' => '9812345678',
            'region_id' => $this->region->id,
            'address' => 'Jibhi, Banjar',
            'services_offered' => ['Accommodation', 'Transport'],
            'status' => 'approved',
        ], $overrides);
    }

    public function test_administrator_can_add_an_approved_provider_with_login(): void
    {
        $res = $this->actingAs($this->admin)->ajax($this->payload());
        $res->assertOk()->assertJson(['success' => true]);

        $provider = ServiceProvider::where('email', 'newprovider@example.test')->firstOrFail();
        $this->assertSame('approved', $provider->status);
        $this->assertNotNull($provider->approved_at);
        $this->assertNotNull($provider->user_id);
        $this->assertEqualsCanonicalizing(['Accommodation', 'Transport'], $provider->services_offered);

        $user = User::find($provider->user_id);
        $this->assertSame('provider', $user->user_role);

        // Manually-added provider never set a password → the approval email
        // MUST carry a set-password link so they can get in.
        Mail::assertSent(SpApplicationApprovedEmail::class, fn ($mail) => !empty($mail->setPasswordUrl));
    }

    public function test_pending_manual_add_creates_no_login_and_sends_no_email(): void
    {
        $this->actingAs($this->admin)->ajax($this->payload(['status' => 'pending']))->assertOk();

        $provider = ServiceProvider::where('email', 'newprovider@example.test')->firstOrFail();
        $this->assertSame('pending', $provider->status);
        $this->assertNull($provider->user_id);
        $this->assertNull($provider->approved_at);
        Mail::assertNotSent(SpApplicationApprovedEmail::class);
    }

    public function test_non_hct_cannot_add_a_provider(): void
    {
        $this->actingAs($this->traveller)->ajax($this->payload())->assertStatus(403);
        $this->assertDatabaseCount('service_providers', 0);
    }

    /** The sidebar has to make a waiting application impossible to miss. */
    public function test_sidebar_blinks_a_count_when_applications_await_approval(): void
    {
        $admin = config('app.admin_domain');

        // Nothing pending → no badge at all.
        $this->actingAs($this->admin)
            ->get("http://{$admin}/provider-applications")
            ->assertOk()
            ->assertDontSee('awaiting approval');

        ServiceProvider::create([
            'provider_types' => ['osp'],
            'name' => 'Waiting Co',
            'email' => 'waiting@example.test',
            'phone_1' => '9800000000',
            'region_id' => $this->region->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->get("http://{$admin}/provider-applications")
            ->assertOk()
            ->assertSee('awaiting approval');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->actingAs($this->admin)->ajax($this->payload())->assertOk();
        $this->actingAs($this->admin)->ajax($this->payload(['name' => 'Dup']))->assertStatus(422);
    }
}
