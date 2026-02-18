<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    protected $fillable = [
        'trip_id', 'user_id', 'trip_name', 'status', 'stage', 'traveller_origin',
        'adults', 'children', 'infants', 'start_date', 'end_date',
        'start_location', 'end_location', 'pickup_location', 'pickup_time',
        'drop_location', 'drop_time', 'operations_notes',
        'accommodation_comfort', 'vehicle_comfort', 'guide_preference',
        'travel_pace', 'budget_sensitivity', 'other_preferences',
        'transport_cost', 'accommodation_cost', 'guide_cost', 'activity_cost', 'other_cost', 'total_cost',
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
            'other_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'final_price' => 'decimal:2',
        ];
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
