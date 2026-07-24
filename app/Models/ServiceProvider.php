<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProvider extends Model
{
    protected $fillable = [
        'user_id', 'provider_type', 'business_type', 'registration_number',
        'year_established', 'name', 'contact_person', 'email',
        'phone_1', 'phone_2', 'region_id', 'address', 'city', 'postal_code',
        'country', 'bank_name',
        'bank_ifsc', 'bank_account_name', 'bank_account_number', 'upi',
        'services_offered', 'accommodation_categories', 'vehicle_types',
        'guide_types', 'activity_types', 'documents', 'notes', 'ical_url', 'ical_last_synced_at',
        'status', 'markup_percent', 'approved_at', 'approved_by',
        'last_updated_by', 'last_updated_by_role',
    ];

    protected function casts(): array
    {
        return [
            'markup_percent' => 'decimal:2',
            'services_offered' => 'array',
            'accommodation_categories' => 'array',
            'vehicle_types' => 'array',
            'guide_types' => 'array',
            'activity_types' => 'array',
            'documents' => 'array',
            'approved_at' => 'datetime',
            'ical_last_synced_at' => 'datetime',
        ];
    }

    /**
     * Admin markup % applied to this provider's raw prices before the traveller
     * sees them (req 3.3). Falls back to the global default_provider_markup_percent
     * setting when the provider has none of its own. 0 = no markup.
     */
    public function effectiveMarkupPercent(): float
    {
        $val = $this->markup_percent;
        if ($val === null || $val === '') {
            $val = Setting::getValue('default_provider_markup_percent', 0);
        }
        return (float) $val;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function pricing()
    {
        return $this->hasMany(SpPricing::class);
    }

    public function experiences()
    {
        return $this->hasMany(Experience::class, 'hlh_id');
    }

    public function tripRegions()
    {
        // Trips in this partner's region. Match region_id -> region_id: the
        // hrp_id column was never populated anywhere, so the old hrp_id-based
        // relation was always empty (#37). region_id is set at application time.
        return $this->hasMany(TripRegion::class, 'region_id', 'region_id');
    }

    public function spPayments()
    {
        return $this->hasMany(SpPayment::class);
    }

    public function availability()
    {
        return $this->hasMany(SpAvailability::class);
    }

    public function blockedDates()
    {
        return $this->hasMany(SpAvailability::class)->whereIn('status', ['booked', 'blocked']);
    }
}
