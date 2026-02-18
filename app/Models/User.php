<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'full_name', 'email', 'password', 'auth_type', 'user_role',
        'mobile', 'address', 'google_id', 'facebook_id', 'avatar', 'photo',
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
        ];
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
}
