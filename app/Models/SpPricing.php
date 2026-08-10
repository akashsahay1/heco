<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpPricing extends Model
{
    protected $table = 'sp_pricing';

    protected $fillable = [
        'service_provider_id', 'service_type', 'category', 'description',
        'unit', 'price', 'meal_plan', 'vehicle_type', 'notes', 'is_active',
        // hotel-style room inventory
        'room_category', 'comfort_tier', 'total_rooms', 'default_occupancy',
        // transport extras
        'vehicle_capacity', 'driver_allowance', 'distance_km',
        'vehicle_make_model', 'vehicle_registration_no', 'vehicle_year', 'vehicle_photos',
        'driver_included', 'fuel_tolls_extra',
        // taxi extras — two per-km rates, since hills and plains differ
        'ac_available', 'vehicle_count',
        'price_per_km_plains', 'price_per_km_hills', 'ac_extra_cost',
        // activity / guide extras
        'min_group', 'max_group', 'specialties',
        'speaks_english', 'languages', 'wage_multi_day', 'is_certified', 'has_first_aid',
        // rental
        'rental_item', 'security_deposit',
        // standard accommodation — a place before it is a rate
        'latitude', 'longitude', 'guest_capacity', 'seasonality_notes', 'photos',
        // approval workflow
        'approval_status', 'pending_for_id',
        'submitted_at', 'submitted_by',
        'approved_at', 'approved_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'driver_allowance' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'total_rooms' => 'integer',
            'vehicle_capacity' => 'integer',
            'vehicle_year' => 'integer',
            'vehicle_photos' => 'array',
            'driver_included' => 'boolean',
            'fuel_tolls_extra' => 'boolean',
            'min_group' => 'integer',
            'max_group' => 'integer',
            'ac_available' => 'boolean',
            'vehicle_count' => 'integer',
            'price_per_km_plains' => 'decimal:2',
            'price_per_km_hills' => 'decimal:2',
            'ac_extra_cost' => 'decimal:2',
            'speaks_english' => 'boolean',
            'languages' => 'array',
            'wage_multi_day' => 'decimal:2',
            'is_certified' => 'boolean',
            'has_first_aid' => 'boolean',
            'security_deposit' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'guest_capacity' => 'integer',
            'photos' => 'array',
            'is_active' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** Live rows used by trip-manager / traveller / cost calc / availability. */
    public function scopeLive($query)
    {
        return $query->where('approval_status', 'approved')->where('is_active', true);
    }

    /** Pending changes awaiting admin review. */
    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    /** The original live row this pending edit is staged against, if any. */
    public function pendingFor()
    {
        return $this->belongsTo(self::class, 'pending_for_id');
    }

    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    /** Optional extras that sit alongside this rate. */
    public function addons()
    {
        return $this->hasMany(SpPricingAddon::class)->orderBy('sort_order');
    }

    // Named "submitter" / "approver" (not submittedBy / approvedBy) to avoid
    // the relation's snake_case JSON key colliding with the FK column.
    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
