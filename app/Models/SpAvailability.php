<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpAvailability extends Model
{
    protected $table = 'sp_availability';

    protected $fillable = [
        'service_provider_id', 'date', 'status', 'source',
        'trip_id', 'trip_day_service_id', 'ical_uid', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function tripDayService()
    {
        return $this->belongsTo(TripDayService::class);
    }
}
