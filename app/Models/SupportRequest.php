<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportRequest extends Model
{
    protected $fillable = [
        'user_id', 'trip_id', 'message', 'traveller_status',
        'is_resolved', 'resolved_by',
    ];

    protected function casts(): array
    {
        return ['is_resolved' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
