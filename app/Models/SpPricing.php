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
        'room_category', 'total_rooms', 'default_occupancy',
        // transport extras
        'vehicle_capacity', 'driver_allowance',
        // activity / guide extras
        'min_group', 'max_group', 'specialties',
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
        ];
    }

    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }
}
