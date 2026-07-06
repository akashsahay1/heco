<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    protected $fillable = [
        'trip_id', 'user_id', 'trip_name', 'status', 'stage', 'traveller_origin',
        'adults', 'children', 'infants', 'start_date', 'end_date',
        'start_location', 'end_location', 'anchor_point', 'pickup_preference',
        'pickup_location', 'pickup_time',
        'drop_location', 'drop_time', 'operations_notes',
        'accommodation_comfort', 'accommodation_provider_id', 'accommodation_pricing_id',
        'vehicle_comfort', 'vehicle_provider_id', 'vehicle_pricing_id',
        'guide_preference', 'guide_provider_id', 'guide_pricing_id',
        'travel_pace', 'budget_sensitivity', 'budget_notes', 'other_preferences',
        'transport_cost', 'accommodation_cost', 'guide_cost', 'activity_cost', 'extra_day_cost', 'other_cost', 'total_cost', 'experience_cost',
        'margin_rp_percent', 'margin_rp_amount', 'margin_hrp_percent', 'margin_hrp_amount',
        'commission_hct_percent', 'commission_hct_amount',
        'subtotal', 'gst_amount', 'final_price',
        'ai_raw_response', 'general_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'other_preferences' => 'array',
            'transport_cost' => 'decimal:2',
            'accommodation_cost' => 'decimal:2',
            'guide_cost' => 'decimal:2',
            'activity_cost' => 'decimal:2',
            'extra_day_cost' => 'decimal:2',
            'other_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'experience_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'final_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Default traveller_origin (Indian vs foreigner pricing bucket) from the
        // owner's nationality whenever it wasn't set explicitly. Covers every
        // creation path (guest sync, chat, ensureAuthTrip, admin). Stays editable
        // in Trip Manager for edge cases like NRIs or foreign nationals in India.
        static::creating(function (Trip $trip) {
            if (empty($trip->traveller_origin) && $trip->user_id) {
                $origin = User::find($trip->user_id)?->travellerOrigin();
                if ($origin) {
                    $trip->traveller_origin = $origin;
                }
            }
        });
    }

    public static function generateTripId(): string
    {
        $last = static::orderBy('id', 'desc')->first();
        $num = $last ? (intval(substr($last->trip_id, -4)) + 1) : 1;
        return 'HECO-T-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Chosen accommodation provider (category → provider → price flow). */
    public function accommodationProvider()
    {
        return $this->belongsTo(ServiceProvider::class, 'accommodation_provider_id');
    }

    /** The exact sp_pricing row that fixes the accommodation rate for this trip. */
    public function accommodationPricing()
    {
        return $this->belongsTo(SpPricing::class, 'accommodation_pricing_id');
    }

    /** Chosen transport provider (vehicle type → provider → rate flow). */
    public function vehicleProvider()
    {
        return $this->belongsTo(ServiceProvider::class, 'vehicle_provider_id');
    }

    /** The sp_pricing row recording the chosen transport provider's rate. */
    public function vehiclePricing()
    {
        return $this->belongsTo(SpPricing::class, 'vehicle_pricing_id');
    }

    /** Chosen guide provider (category → provider → price flow). */
    public function guideProvider()
    {
        return $this->belongsTo(ServiceProvider::class, 'guide_provider_id');
    }

    /** The exact sp_pricing row that fixes the guide (per-day) rate for this trip. */
    public function guidePricing()
    {
        return $this->belongsTo(SpPricing::class, 'guide_pricing_id');
    }

    public function tripRegions()
    {
        return $this->hasMany(TripRegion::class);
    }

    public function regions()
    {
        return $this->belongsToMany(Region::class, 'trip_regions');
    }

    public function tripDays()
    {
        return $this->hasMany(TripDay::class)->orderBy('day_number');
    }

    public function selectedExperiences()
    {
        return $this->hasMany(TripSelectedExperience::class);
    }

    public function lead()
    {
        return $this->hasOne(Lead::class);
    }

    public function travellerPayments()
    {
        return $this->hasMany(TravellerPayment::class);
    }

    public function spPayments()
    {
        return $this->hasMany(SpPayment::class);
    }

    public function aiConversations()
    {
        return $this->hasMany(AiConversation::class)->orderBy('created_at');
    }

    public function supportRequests()
    {
        return $this->hasMany(SupportRequest::class);
    }
}
