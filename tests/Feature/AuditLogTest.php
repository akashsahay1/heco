<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #26 — admin mutations must write an audit-log row (who did what). Previously
 * only newsletter events were logged.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_update_writes_activity_log(): void
    {
        $portal = config('app.portal_domain');
        Setting::setValue('gst_percent', 5, 'financial');

        $admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@audit.test',
            'password' => 'password', 'user_role' => 'administrator', 'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post("http://{$portal}/ajax", ['save_settings' => 1, 'settings' => ['gst_percent' => 9]])
            ->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'settings_updated',
            'user_id' => $admin->id,
            'model_type' => 'Setting',
        ]);
    }
}
