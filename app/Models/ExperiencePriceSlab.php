<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One per-person price tier for an experience, keyed by group size.
 * See create_experience_price_slabs_table migration for the selection rule.
 */
class ExperiencePriceSlab extends Model
{
    protected $fillable = [
        'experience_id', 'min_persons', 'price_per_person',
    ];

    protected function casts(): array
    {
        return [
            'min_persons' => 'integer',
            'price_per_person' => 'decimal:2',
        ];
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }
}
