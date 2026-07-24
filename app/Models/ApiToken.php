<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A bearer token for the mobile app.
 *
 * Tokens are stored hashed; [issueFor] is the only place the plaintext exists.
 */
class ApiToken extends Model
{
    protected $fillable = ['user_id', 'name', 'token_hash', 'device', 'last_used_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** Token kinds. Only ACCESS may authenticate an API call. */
    public const ACCESS = 'access';
    public const REFRESH = 'refresh';

    /**
     * Access tokens are short enough that a leaked one ages out; the refresh
     * token outlives them so a provider is not signed out every month.
     */
    public const ACCESS_DAYS = 30;
    public const REFRESH_DAYS = 90;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashFor(string $plainText): string
    {
        return hash('sha256', $plainText);
    }

    /**
     * Issue one token. Returns [$model, $plainTextToken] — the plaintext is
     * never persisted and cannot be recovered later.
     */
    public static function issueFor(User $user, ?string $device = null, string $name = self::ACCESS): array
    {
        $plainText = Str::random(64);
        $days = $name === self::REFRESH ? self::REFRESH_DAYS : self::ACCESS_DAYS;

        $token = self::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => self::hashFor($plainText),
            'device' => $device,
            'expires_at' => now()->addDays($days),
        ]);

        return [$token, $plainText];
    }

    /**
     * Issue a fresh access + refresh pair. Returns the two plaintext strings.
     */
    public static function issuePair(User $user, ?string $device = null): array
    {
        [, $access] = self::issueFor($user, $device, self::ACCESS);
        [, $refresh] = self::issueFor($user, $device, self::REFRESH);

        return [$access, $refresh];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
