<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An optional extra hung off an experience — a guided village walk, a cooking
 * class, a yoga session, birdwatching.
 *
 * The client's intent is that a host enriches one listing rather than creating
 * a separate experience for every small thing they can do, which keeps their
 * catalogue readable and keeps them under the listing cap.
 */
class ExperienceAddon extends Model
{
    protected $fillable = [
        'experience_id', 'name', 'description', 'price', 'price_unit',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }
}
