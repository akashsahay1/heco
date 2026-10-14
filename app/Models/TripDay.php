<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDay extends Model
{
    /**
     * A day HCT writes by hand: its own title, its own activities, its own
     * prices, none of them from the experience catalogue. Priced only by
     * what is on it.
     */
    public const TYPE_CUSTOM = 'custom';

    /** Every kind of day the Trip Manager lets HCT pick. */
    public const TYPES = ['rest', 'activity', 'free', 'travel', self::TYPE_CUSTOM];

    protected $fillable = [
        'trip_id', 'day_number', 'date', 'title', 'description', 'day_type',
        'added_by', 'is_experience_day', 'experience_group_id', 'is_locked', 'sort_order',
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
