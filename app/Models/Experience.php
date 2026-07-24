<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Experience extends Model
{
    protected $fillable = [
        'hlh_id', 'owner_provider_id', 'owner_type',
        'region_id', 'regenerative_project_id', 'name', 'slug', 'type',
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
        'approval_status', 'submitted_at', 'submitted_by',
        'approved_at', 'approved_by', 'rejection_reason',
        'pending_changes', 'pending_submitted_at', 'pending_submitted_by',
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
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'pending_changes' => 'array',
            'pending_submitted_at' => 'datetime',
        ];
    }

    public function hlh()
    {
        return $this->belongsTo(ServiceProvider::class, 'hlh_id');
    }

    /**
     * The provider (HLH or OSP) who authored this experience and may edit it.
     * See the add_owner_provider_to_experiences_table migration.
     */
    public function ownerProvider()
    {
        return $this->belongsTo(ServiceProvider::class, 'owner_provider_id');
    }

    /** Experiences a given provider owns. */
    public function scopeOwnedBy($query, int $providerId)
    {
        return $query->where('owner_provider_id', $providerId);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Anything sitting in HCT's review queue: a brand-new submission, or a
     * live experience with an edit parked against it.
     */
    public function scopePending($query)
    {
        return $query->where(function ($q) {
            $q->where('approval_status', 'pending')
              ->orWhereNotNull('pending_changes');
        });
    }

    /** A live experience with an unreviewed revision waiting. */
    public function hasPendingChanges(): bool
    {
        return !empty($this->pending_changes);
    }

    /**
     * Surfaced to the portal and the app so a live experience can be shown as
     * "changes under review" without shipping the whole parked payload.
     */
    protected $appends = ['has_pending_changes'];

    public function getHasPendingChangesAttribute(): bool
    {
        return $this->hasPendingChanges();
    }

    /**
     * Reviewed and live. Anything traveller-facing must go through this —
     * `is_active` alone would also match experiences nobody has approved.
     */
    public function scopeLive($query)
    {
        return $query->where('approval_status', 'approved')->where('is_active', true);
    }

    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function regenerativeProject()
    {
        return $this->belongsTo(RegenerativeProject::class);
    }

    public function days()
    {
        return $this->hasMany(ExperienceDay::class)->orderBy('day_number');
    }

    public function priceSlabs()
    {
        return $this->hasMany(ExperiencePriceSlab::class)->orderBy('min_persons');
    }

    /**
     * Per-person selling price for a party of $pax (req 3.2). Picks the slab with
     * the largest min_persons <= $pax (so min_persons=6 serves "6+"). Falls back
     * to base_cost_per_person when the experience has no slabs configured, so
     * legacy experiences keep pricing exactly as before.
     */
    public function slabPricePerPerson(int $pax): float
    {
        $pax = max($pax, 1);
        $slab = $this->priceSlabs
            ->where('min_persons', '<=', $pax)
            ->sortByDesc('min_persons')
            ->first();

        if ($slab) {
            return (float) $slab->price_per_person;
        }

        // No slab at/below pax — use the smallest configured slab if any, else base.
        $smallest = $this->priceSlabs->sortBy('min_persons')->first();
        return $smallest ? (float) $smallest->price_per_person : (float) $this->base_cost_per_person;
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
