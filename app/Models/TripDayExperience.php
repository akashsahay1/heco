<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDayExperience extends Model
{
    protected $fillable = [
        'trip_day_id', 'experience_id', 'start_time', 'end_time',
        'cost_per_person', 'total_cost', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'cost_per_person' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function tripDay()
    {
        return $this->belongsTo(TripDay::class);
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }
}
