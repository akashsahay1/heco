<?php

namespace Tests\Feature;

use App\Mail\SupportRequestEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * #27 — a submitted support request must notify the team by email, not just
 * write a DB row that goes unseen.
 */
class SupportRequestEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_request_sends_notification_email(): void
    {
        Mail::fake();
        $portal = config('app.portal_domain');

        $traveller = User::create([
            'full_name' => 'Trav', 'email' => 'trav@sup.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);

        $this->actingAs($traveller)
            ->post("http://{$portal}/ajax", [
                'request_support' => 1,
                'message' => 'I need help with my booking.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        Mail::assertSent(SupportRequestEmail::class);
    }
}
