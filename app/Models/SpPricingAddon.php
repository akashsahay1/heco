<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An optional extra hung off a rate — an extra bed, an airport pickup, a
 * packed breakfast.
 *
 * The same idea as ExperienceAddon, on the supplier's side of the house: the
 * client's data-collection document asks for add-ons on a standard
 * accommodation, so a hotel can price what sits alongside the room rather than
 * filing each extra as a rate of its own.
 */
class SpPricingAddon extends Model
{
    protected $fillable = [
        'sp_pricing_id', 'name', 'description', 'price', 'price_unit',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function rate()
    {
        return $this->belongsTo(SpPricing::class, 'sp_pricing_id');
    }
}
