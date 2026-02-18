<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'user_id', 'trip_id', 'assigned_hct_id', 'stage', 'enquiry_date',
        'last_interaction_date', 'interaction_mode', 'reminder_delay_days', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'enquiry_date' => 'datetime',
            'last_interaction_date' => 'datetime',
        ];
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
