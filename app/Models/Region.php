<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'continent', 'country', 'external_url',
        'latitude', 'longitude', 'is_active', 'sort_order', 'image',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_active' => 'boolean',
        ];
    }

    public function experiences()
    {
        return $this->hasMany(Experience::class);
    }

    public function serviceProviders()
    {
        return $this->hasMany(ServiceProvider::class);
    }

    public function regenerativeProjects()
    {
        return $this->hasMany(RegenerativeProject::class);
    }

    public function tripRegions()
    {
        return $this->hasMany(TripRegion::class);
    }
}
