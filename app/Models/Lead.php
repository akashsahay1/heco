<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        // The enquiry itself. A lead needs no account and no trip: it is
        // remembered data, so that somebody can be rung back.
        'full_name', 'email', 'mobile',
        'region_id', 'adults', 'children', 'start_date', 'end_date',
        // Filled in later, if and when the enquiry becomes a real journey.
        'user_id', 'trip_id',
        'assigned_hct_id', 'stage', 'enquiry_date',
        'last_interaction_date', 'interaction_mode', 'reminder_delay_days', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'enquiry_date' => 'datetime',
            'last_interaction_date' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * The person who enquired, by whichever name we hold.
     *
     * An older lead was filed by creating an account, so its name lives there;
     * a lead filed now carries its own.
     */
    public function travellerName(): string
    {
        return $this->full_name ?: ($this->user?->full_name ?: '-');
    }

    public function travellerEmail(): ?string
    {
        return $this->email ?: $this->user?->email;
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function assignedHct()
    {
        return $this->belongsTo(User::class, 'assigned_hct_id');
    }
}
