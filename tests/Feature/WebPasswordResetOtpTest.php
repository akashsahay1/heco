<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The web portal "forgot password" flow now confirms a 6-digit code on-site
 * (no emailed link) and sets the new password — mirroring the mobile app. The
 * token-based link stays only for admin-added providers' set-password email.
 */
class WebPasswordResetOtpTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->portal = config('app.portal_domain');
    }

    private function user(): User
    {
        return User::create([
            'full_name' => 'Reset Me',
            'email' => 'reset.me@example.test',
            'password' => Hash::make('OldPass1!'),
            'user_role' => 'traveller',
            'status' => 'active',
        ]);
    }

    private function postJ(string $path, array $data)
    {
        return $this->postJson("http://{$this->portal}{$path}", $data);
    }

    /** Request a code and return it (read off the faked email). */
    private function requestCode(string $email): ?string
    {
        $this->postJ('/forgot-password', ['email' => $email])
            ->assertOk()
            ->assertJson(['success' => true, 'redirect' => '/reset-password-otp']);

        $code = null;
        Mail::assertSent(PasswordResetOtpEmail::class, function ($mail) use (&$code) {
            $code = $mail->otp;
            return true;
        });
        return $code;
    }

    public function test_requesting_a_code_emails_it_and_opens_the_otp_page(): void
    {
        $this->user();
        $code = $this->requestCode('reset.me@example.test');
        $this->assertNotNull($code);

        $this->get("http://{$this->portal}/reset-password-otp")
            ->assertOk()
            ->assertSee('Enter your code')
            ->assertSee('reset.me@example.test');
    }

    public function test_the_otp_page_bounces_a_visitor_with_no_reset_in_progress(): void
    {
        $this->get("http://{$this->portal}/reset-password-otp")
            ->assertRedirect('/forgot-password');
    }

    public function test_the_correct_code_sets_the_new_password(): void
    {
        $user = $this->user();
        $code = $this->requestCode('reset.me@example.test');

        $this->postJ('/reset-password-otp', [
            'otp' => $code,
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ])->assertOk()->assertJson(['success' => true, 'redirect' => '/login']);

        $this->assertTrue(Hash::check('BrandNew1!', $user->fresh()->password));
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $user = $this->user();
        $code = $this->requestCode('reset.me@example.test');
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->postJ('/reset-password-otp', [
            'otp' => $wrong,
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('OldPass1!', $user->fresh()->password));
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $this->user();
        $code = $this->requestCode('reset.me@example.test');

        $this->postJ('/reset-password-otp', [
            'otp' => $code,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(422);
    }

    public function test_an_unknown_email_looks_identical_but_cannot_reset(): void
    {
        // No account for this email: still success + OTP page (no enumeration),
        // but no code was sent and any code fails to reset.
        $this->postJ('/forgot-password', ['email' => 'nobody@example.test'])
            ->assertOk()
            ->assertJson(['success' => true, 'redirect' => '/reset-password-otp']);

        Mail::assertNothingSent();

        $this->postJ('/reset-password-otp', [
            'otp' => '123456',
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ])->assertStatus(422);
    }
}
