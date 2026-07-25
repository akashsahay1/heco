<?php

namespace App\Services;

use App\Mail\AccountOtpEmail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * One-time codes that let a brand-new applicant confirm their email and set a
 * password without ever leaving the portal/app for a web link.
 *
 * Mirrors {@see PasswordResetOtpService} but for the signup path: the code is
 * emailed the moment the application is submitted, and verifying it is what
 * activates the login. The plaintext code is never stored — only a bcrypt hash
 * in the cache, with a short expiry and an attempt cap. On the web a session
 * ties the browser to the pending user; the stateless mobile flow carries a
 * verification token instead.
 */
class AccountOtpService
{
    private const TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    private function otpKey(int $userId): string
    {
        return "account_otp:{$userId}";
    }

    private function sessionKey(string $token): string
    {
        return "account_session:{$token}";
    }

    /**
     * Generate a code for the user, cache it hashed, and email it. The display
     * name is the applicant's contact person (the user row may only carry the
     * business name at this point).
     */
    public function sendCode(User $user, ?string $displayName = null): void
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->otpKey($user->id), [
            'hash' => Hash::make($otp),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        try {
            Mail::to($user->email)->send(
                new AccountOtpEmail($displayName ?: $user->full_name, $otp)
            );
        } catch (\Throwable $e) {
            Log::error('Account OTP mail failed [' . $user->id . ']: ' . $e->getMessage());
        }
    }

    /**
     * Mint the stateless token the mobile app carries to the verify step. The
     * web flow uses a session instead, but shares the same verify() core.
     */
    public function startSession(?User $user): string
    {
        $token = Str::random(48);
        Cache::put($this->sessionKey($token), $user?->id ?? 0, now()->addMinutes(self::TTL_MINUTES));
        return $token;
    }

    /** The user behind a token, or null if it is unknown, expired or a decoy. */
    public function userFor(string $token): ?User
    {
        $id = (int) Cache::get($this->sessionKey($token), 0);
        return $id > 0 ? User::find($id) : null;
    }

    public function clearSession(string $token): void
    {
        Cache::forget($this->sessionKey($token));
    }

    /**
     * Check a submitted code. Returns an error message, or null on success — on
     * which the code is consumed so it cannot be replayed.
     */
    public function verify(int $userId, string $otp): ?string
    {
        $key = $this->otpKey($userId);
        $entry = Cache::get($key);
        if (!$entry) {
            return 'Your code has expired. Please request a new one.';
        }
        if (($entry['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
            return 'Too many attempts. Please request a new code.';
        }
        if (!Hash::check($otp, $entry['hash'])) {
            $entry['attempts'] = ($entry['attempts'] ?? 0) + 1;
            Cache::put($key, $entry, now()->addMinutes(self::TTL_MINUTES));
            return 'That code is incorrect. Please try again.';
        }
        Cache::forget($key);
        return null;
    }
}
