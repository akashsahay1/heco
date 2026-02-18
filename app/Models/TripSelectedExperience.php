<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripSelectedExperience extends Model
{
    protected $fillable = ['trip_id', 'experience_id', 'is_preferred'];

    protected function casts(): array
    {
        return ['is_preferred' => 'boolean'];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }
}
