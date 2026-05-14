<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpRoomBooking extends Model
{
    protected $table = 'sp_room_bookings';

    protected $fillable = [
        'sp_pricing_id', 'trip_id', 'trip_day_service_id',
        'date', 'quantity', 'status', 'source', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'integer',
        ];
    }

    public function spPricing()
    {
        return $this->belongsTo(SpPricing::class, 'sp_pricing_id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function tripDayService()
    {
        return $this->belongsTo(TripDayService::class);
    }

    // Scopes used by RoomAvailabilityService for the availability math.
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['held', 'confirmed']);
    }
}
