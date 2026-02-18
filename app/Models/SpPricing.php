<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpPricing extends Model
{
    protected $table = 'sp_pricing';

    protected $fillable = [
        'service_provider_id', 'service_type', 'category', 'description',
        'unit', 'price', 'meal_plan', 'vehicle_type', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }
}
