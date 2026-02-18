<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDay extends Model
{
    protected $fillable = [
        'trip_id', 'day_number', 'date', 'title', 'description',
        'is_experience_day', 'experience_group_id', 'is_locked', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_experience_day' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function experiences()
    {
        return $this->hasMany(TripDayExperience::class)->orderBy('sort_order');
    }

    public function services()
    {
        return $this->hasMany(TripDayService::class)->orderBy('sort_order');
    }
}
