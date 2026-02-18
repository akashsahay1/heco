<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDayService extends Model
{
    protected $fillable = [
        'trip_day_id', 'service_provider_id', 'service_type', 'description',
        'from_location', 'to_location', 'cost', 'is_included', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'is_included' => 'boolean',
        ];
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
