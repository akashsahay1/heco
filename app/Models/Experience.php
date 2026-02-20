<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Experience extends Model
{
    protected $fillable = [
        'hlh_id', 'region_id', 'regenerative_project_id', 'name', 'slug', 'type',
        'short_description', 'long_description', 'unique_description', 'cultural_context',
        'duration_type', 'duration_hours', 'duration_days', 'duration_nights',
        'start_time', 'end_time', 'includes_accommodation', 'accommodation_category',
        'includes_meals_breakfast', 'includes_meals_lunch', 'includes_meals_dinner',
        'includes_guide', 'includes_transport',
        'start_latitude', 'start_longitude', 'end_latitude', 'end_longitude', 'area',
        'trekking_required', 'road_seasonal_closure', 'altitude_max', 'altitude_min',
        'difficulty_level', 'fitness_requirements', 'age_min', 'age_max',
        'group_size_min', 'group_size_max', 'weather_dependency',
        'cultural_sensitivities', 'environmental_constraints',
        'best_seasons', 'available_months', 'restricted_months', 'unavailable_months', 'seasonality_notes',
        'base_cost_per_person', 'price_currency', 'cost_accommodation', 'cost_logistics', 'cost_guide',
        'cost_activities', 'cost_other', 'seasonal_price_variation', 'single_supplement',
        'osps_involved', 'osp_services',
        'traveller_bring_list', 'clothing_recommendations', 'health_notes',
        'connectivity_notes', 'cultural_etiquette',
        'operational_risks', 'past_issues', 'backup_options', 'emergency_notes',
        'card_image', 'gallery', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'includes_accommodation' => 'boolean',
            'includes_meals_breakfast' => 'boolean',
            'includes_meals_lunch' => 'boolean',
            'includes_meals_dinner' => 'boolean',
            'includes_guide' => 'boolean',
            'includes_transport' => 'boolean',
            'trekking_required' => 'boolean',
            'road_seasonal_closure' => 'boolean',
            'osps_involved' => 'boolean',
            'is_active' => 'boolean',
            'best_seasons' => 'array',
            'available_months' => 'array',
            'restricted_months' => 'array',
            'unavailable_months' => 'array',
            'seasonal_price_variation' => 'array',
            'osp_services' => 'array',
            'gallery' => 'array',
            'base_cost_per_person' => 'decimal:2',
            'duration_hours' => 'decimal:2',
        ];
    }

    public function hlh()
    {
        return $this->belongsTo(ServiceProvider::class, 'hlh_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function regenerativeProject()
    {
        return $this->belongsTo(RegenerativeProject::class);
    }

    public function tripDayExperiences()
    {
        return $this->hasMany(TripDayExperience::class);
    }

    public function tripSelectedExperiences()
    {
        return $this->hasMany(TripSelectedExperience::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function averageRating(): ?float
    {
        return $this->reviews()->avg('rating');
    }
}
