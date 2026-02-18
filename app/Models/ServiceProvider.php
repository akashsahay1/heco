<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProvider extends Model
{
    protected $fillable = [
        'user_id', 'provider_type', 'name', 'contact_person', 'email',
        'phone_1', 'phone_2', 'region_id', 'address', 'bank_name',
        'bank_ifsc', 'bank_account_name', 'bank_account_number', 'upi',
        'services_offered', 'accommodation_categories', 'vehicle_types',
        'activity_types', 'notes', 'status', 'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'services_offered' => 'array',
            'accommodation_categories' => 'array',
            'vehicle_types' => 'array',
            'activity_types' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function pricing()
    {
        return $this->hasMany(SpPricing::class);
    }

    public function experiences()
    {
        return $this->hasMany(Experience::class, 'hlh_id');
    }

    public function tripRegions()
    {
        return $this->hasMany(TripRegion::class, 'hrp_id');
    }

    public function spPayments()
    {
        return $this->hasMany(SpPayment::class);
    }
}
