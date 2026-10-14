<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDayService extends Model
{
    protected $fillable = [
        'trip_day_id', 'service_provider_id', 'sp_pricing_id', 'room_quantity',
        'service_type', 'description',
        'from_location', 'to_location', 'cost', 'price_basis', 'is_included', 'notes', 'sort_order',
    ];

    public const BASIS_FLAT = 'flat';
    public const BASIS_PER_PERSON = 'per_person';

    public function isPerPerson(): bool
    {
        return $this->price_basis === self::BASIS_PER_PERSON;
    }

    /**
     * What this line comes to for the whole party, before markup.
     *
     * A flat line is its cost. A per-person line is its cost times the
     * people factor, so a child or infant is charged at the percentage set
     * in Settings, the same way an experience charges them.
     */
    public function groupCost(float $peopleFactor): float
    {
        $cost = (float) $this->cost;

        return $this->isPerPerson() ? $cost * $peopleFactor : $cost;
    }

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'is_included' => 'boolean',
            'room_quantity' => 'integer',
        ];
    }

    public function spPricing()
    {
        return $this->belongsTo(SpPricing::class, 'sp_pricing_id');
    }

    public function roomBookings()
    {
        return $this->hasMany(SpRoomBooking::class, 'trip_day_service_id');
    }

    public function tripDay()
    {
        return $this->belongsTo(TripDay::class);
    }

    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }
}
