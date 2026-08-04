<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use App\Mail\PasswordResetEmail;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Roles grouped by the door they come in through.
     *
     * An email is unique per role, not outright (users_email_role_unique) —
     * the same person can hold a traveller account and an HCT login on one
     * address. That makes "find the user with this email" ambiguous on its
     * own, so every credential lookup narrows to the roles its entry point
     * actually serves and lets the password settle any remainder. See
     * findByCredentials().
     */
    public const HCT_ROLES = ['hct_admin', 'hct_collaborator'];
    public const PROVIDER_ROLES = ['hrp', 'hlh', 'osp'];
    public const PORTAL_ROLES = ['traveller', 'hrp', 'hlh', 'osp'];

    protected $fillable = [
        'full_name', 'email', 'password', 'auth_type', 'user_role',
        'mobile', 'address1', 'address2', 'city', 'state', 'country', 'postal_code',
        'nationality', 'gender', 'date_of_birth',
        'google_id', 'facebook_id', 'avatar', 'photo',
        'newsletter_optin', 'portal_notify_optin', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'newsletter_optin' => 'boolean',
            'portal_notify_optin' => 'boolean',
            'date_of_birth' => 'date',
        ];
    }

    /**
     * Age in completed years, or null if DOB unknown.
     * Used for trek age-eligibility checks.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    /**
     * Map the user's nationality to the trip-pricing origin bucket
     * ('indian' / 'foreigner'). Returns null when nationality is unknown so
     * callers can leave traveller_origin blank rather than guess.
     */
    public function travellerOrigin(): ?string
    {
        if (!$this->nationality) {
            return null;
        }
        return strcasecmp($this->nationality, 'India') === 0 ? 'indian' : 'foreigner';
    }

    public function isHctAdmin(): bool
    {
        return $this->user_role === 'hct_admin';
    }

    public function isHctCollaborator(): bool
    {
        return $this->user_role === 'hct_collaborator';
    }

    public function isHct(): bool
    {
        return in_array($this->user_role, ['hct_admin', 'hct_collaborator']);
    }

    public function isTraveller(): bool
    {
        return $this->user_role === 'traveller';
    }

    public function isServiceProvider(): bool
    {
        return in_array($this->user_role, ['hrp', 'hlh', 'osp']);
    }

    /**
     * The account behind an email and password, for one entry point.
     *
     * Auth::attempt() is not usable where an email may repeat: its provider
     * takes the first row matching the email and checks the password against
     * that row alone, so the second account would be permanently unreachable —
     * silently, and looking exactly like a wrong password. This narrows by
     * role first and then lets the password settle whatever is left, so two
     * accounts on one address both work as long as their passwords differ.
     *
     * @param array<int,string> $roles
     */
    public static function findByCredentials(string $email, string $password, array $roles): ?self
    {
        $candidates = static::where('email', $email)
            ->whereIn('user_role', $roles)
            ->orderBy('id')
            ->get();

        foreach ($candidates as $candidate) {
            if ($candidate->password && Hash::check($password, $candidate->password)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Validation rule for "this address is free" — within one set of roles,
     * which is as far as uniqueness now goes. Pass the id of the row being
     * edited so an update does not collide with itself.
     *
     * @param array<int,string> $roles
     */
    public static function uniqueEmailRule(array $roles, ?int $ignoreId = null): Unique
    {
        $rule = Rule::unique('users', 'email')
            ->where(fn ($query) => $query->whereIn('user_role', $roles));

        return $ignoreId ? $rule->ignore($ignoreId) : $rule;
    }

    /**
     * The account an email-only flow (password reset, social sign-in) belongs
     * to, for one entry point. There is no password here to tell two apart, so
     * the oldest match wins — the account the address was first used for.
     *
     * @param array<int,string> $roles
     */
    public static function findByEmailForRoles(string $email, array $roles): ?self
    {
        return static::where('email', $email)
            ->whereIn('user_role', $roles)
            ->orderBy('id')
            ->first();
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function serviceProvider()
    {
        return $this->hasOne(ServiceProvider::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function assignedLeads()
    {
        return $this->hasMany(Lead::class, 'assigned_hct_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Override the framework's default plaintext reset email with our branded
     * Mailable. The token comes from Laravel's password broker.
     */
    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $this->email,
            // Which of the accounts on this address the link is for — see the
            // role constants above.
            'role' => $this->user_role,
        ]);

        try {
            Mail::to($this->email)->send(new PasswordResetEmail($this->full_name ?: 'there', $resetUrl));
        } catch (\Throwable $e) {
            Log::error('Password reset email failed [' . $this->id . ']: ' . $e->getMessage());
        }
    }
}
