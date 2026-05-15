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
        'vehicle_capacity', 'driver_allowance',
        // activity / guide extras
        'min_group', 'max_group', 'specialties',
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
            'total_rooms' => 'integer',
            'vehicle_capacity' => 'integer',
            'min_group' => 'integer',
            'max_group' => 'integer',
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
