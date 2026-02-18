<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegenerativeProject extends Model
{
    protected $fillable = [
        'name', 'region_id', 'local_association', 'action_type',
        'short_description', 'detailed_description', 'impact_unit',
        'conversion_rules', 'measurement_frequency', 'reference_budget',
        'cost_per_impact_unit', 'active_periods', 'paused_periods',
        'operational_constraints', 'main_image', 'gallery', 'is_active',
        'fallback_for_regions',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'reference_budget' => 'decimal:2',
            'cost_per_impact_unit' => 'decimal:2',
            'active_periods' => 'array',
            'paused_periods' => 'array',
            'gallery' => 'array',
            'fallback_for_regions' => 'array',
        ];
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function experiences()
    {
        return $this->hasMany(Experience::class);
    }
}
