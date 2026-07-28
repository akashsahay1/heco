<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One cell of an experiential stay's pricing grid: what a given occupancy costs
 * on a given meal plan.
 *
 * The client's shape is "Pricing table (single, double, triple, meal plans)" —
 * a grid rather than the single per-person price the other experience
 * categories use, because a homestay charges by room and board, not by head.
 */
class ExperienceRoomRate extends Model
{
    protected $fillable = [
        'experience_id', 'occupancy', 'meal_plan', 'price', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }
}
