<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the central authorization gate on the shared AJAX dispatcher
 * (AjaxController::index / authorizeAction). Because both hecoadmin.test/ajax
 * and the PUBLIC hecoportal.test/ajax route through the same index(), admin
 * actions must be gated in the controller, not just by route middleware.
 *
 * These drive the real POST /ajax on the PORTAL (public) domain — the exact
 * surface that previously exposed admin actions unauthenticated.
 */
class AjaxAuthorizationGateTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected User $traveller;
    protected User $collaborator;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');

        // A real, whitelisted setting key so an authorized saveSettings succeeds.
        Setting::setValue('gst_percent', 5, 'financial');

        $this->traveller = User::create([
            'full_name' => 'Trav', 'email' => 'trav@example.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        $this->collaborator = User::create([
            'full_name' => 'Collab', 'email' => 'collab@example.test',
            'password' => 'password', 'user_role' => 'hct_collaborator', 'status' => 'active',
        ]);
        $this->admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'user_role' => 'hct_admin', 'status' => 'active',
        ]);
    }

    private function ajax(array $payload)
    {
        return $this->post("http://{$this->portal}/ajax", $payload);
    }

    /** The headline vuln: admin action via the public portal /ajax, no login. */
    public function test_unauthenticated_admin_action_is_blocked(): void
    {
        $this->ajax(['save_settings' => 1, 'settings' => ['gst_percent' => 0]])
            ->assertStatus(401);
    }

    /** Unauthenticated privilege-escalation vector must be blocked. */
    public function test_unauthenticated_create_hct_user_is_blocked(): void
    {
        $this->ajax(['create_hct_user' => 1, 'user_role' => 'hct_admin', 'email' => 'x@x.test'])
            ->assertStatus(401);
    }

    /** A logged-in traveller is not staff -> 403 on an admin action. */
    public function test_traveller_cannot_hit_admin_action(): void
    {
        $this->actingAs($this->traveller)
            ->ajax(['save_settings' => 1, 'settings' => ['gst_percent' => 0]])
            ->assertStatus(403);
    }

    /** hct_admin-only actions reject an hct_collaborator. */
    public function test_collaborator_cannot_hit_hct_admin_action(): void
    {
        $this->actingAs($this->collaborator)
            ->ajax(['save_settings' => 1, 'settings' => ['gst_percent' => 0]])
            ->assertStatus(403);
    }

    /** hct_collaborator may perform plain hct actions. */
    public function test_collaborator_can_hit_hct_action(): void
    {
        $this->actingAs($this->collaborator)
            ->ajax(['get_leads' => 1])
            ->assertStatus(200);
    }

    /** hct_admin passes the gate and the whitelist accepts a known key. */
    public function test_admin_can_save_whitelisted_setting(): void
    {
        $this->actingAs($this->admin)
            ->ajax(['save_settings' => 1, 'settings' => ['gst_percent' => 8]])
            ->assertStatus(200)
            ->assertJsonPath('rejected', []);

        $this->assertSame('8', (string) Setting::getValue('gst_percent'));
    }

    /** saveSettings rejects an unknown/injection key (does not persist it). */
    public function test_save_settings_rejects_unknown_key(): void
    {
        $this->actingAs($this->admin)
            ->ajax(['save_settings' => 1, 'settings' => ['evil_injected_key' => 'boom']])
            ->assertStatus(200)
            ->assertJsonPath('rejected', ['evil_injected_key']);

        $this->assertNull(Setting::getValue('evil_injected_key'));
    }

    /** Public/guest actions remain reachable without auth (no regression). */
    public function test_public_action_still_reachable_unauthenticated(): void
    {
        $this->ajax(['get_experiences_for_discover' => 1])
            ->assertStatus(200);
    }
}
