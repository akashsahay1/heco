<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExperienceDay extends Model
{
    protected $fillable = [
        'experience_id', 'day_number', 'title', 'short_description',
        'start_time', 'end_time', 'inclusions', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'inclusions' => 'array',
        ];
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }
}
