<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetEmail;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

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
        ]);

        try {
            Mail::to($this->email)->send(new PasswordResetEmail($this->full_name ?: 'there', $resetUrl));
        } catch (\Throwable $e) {
            Log::error('Password reset email failed [' . $this->id . ']: ' . $e->getMessage());
        }
    }
}
