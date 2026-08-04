<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProvider extends Model
{
    protected $fillable = [
        'user_id', 'provider_type', 'provider_types', 'has_business',
        'experience_categories', 'service_categories', 'other_services',
        'business_type', 'registration_number',
        'year_established', 'name', 'contact_person', 'email',
        'phone_1', 'phone_2', 'speaks_english', 'speaks_hindi', 'other_languages',
        'contact_by_email', 'contact_by_whatsapp',
        'region_id', 'address', 'photo', 'city', 'postal_code',
        'country', 'bank_name',
        'bank_ifsc', 'bank_account_name', 'bank_account_number', 'upi',
        'services_offered', 'accommodation_categories', 'vehicle_types',
        'guide_types', 'activity_types',
        'education_level', 'education_notes', 'english_level',
        'computer_skill_level', 'work_experience', 'causes_note', 'community_note',
        'documents', 'notes', 'ical_url', 'ical_last_synced_at',
        'status', 'markup_percent', 'approved_at', 'approved_by',
        'last_updated_by', 'last_updated_by_role',
    ];

    protected function casts(): array
    {
        return [
            'markup_percent' => 'decimal:2',
            'provider_types' => 'array',
            'experience_categories' => 'array',
            'service_categories' => 'array',
            'has_business' => 'boolean',
            'speaks_english' => 'boolean',
            'speaks_hindi' => 'boolean',
            'contact_by_email' => 'boolean',
            'contact_by_whatsapp' => 'boolean',
            'services_offered' => 'array',
            'accommodation_categories' => 'array',
            'vehicle_types' => 'array',
            'guide_types' => 'array',
            'activity_types' => 'array',
            'work_experience' => 'array',
            'documents' => 'array',
            'approved_at' => 'datetime',
            'ical_last_synced_at' => 'datetime',
        ];
    }

    /**
     * Display names for the three roles, in one place because four different
     * views used to keep their own copy — and they had drifted: the portal said
     * "Homestay Local Host" where both client documents say "Heco Local Host".
     */
    public const TYPE_LABELS = [
        'hlh' => 'Heco Local Host (HLH)',
        'osp' => 'Other Service Provider (OSP)',
        'hrp' => 'Heco Regional Partner (HRP)',
    ];

    /**
     * What an admin may set the status to, and what each one reads as.
     *
     * 'removed' is deliberately absent: removing a provider deletes the row, so
     * the state no longer exists to be chosen. Rows written by the old
     * soft-delete still carry it and are shown as-is.
     */
    public const STATUS_LABELS = [
        'approved' => 'Approved',
        'pending'  => 'Pending',
        'rejected' => 'Rejected',
        'banned'   => 'Banned',
        'hidden'   => 'Hidden',
    ];

    /**
     * Blocked by HCT. The linked login is deactivated with the status, so this
     * shuts the app and the portal at once — unlike 'hidden', which is only a
     * pause.
     */
    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    /**
     * Temporarily out of service. They keep their login and can still manage
     * rates and availability; they are simply not offered to travellers,
     * matching, or Trip Manager while it lasts.
     */
    public function isHidden(): bool
    {
        return $this->status === 'hidden';
    }

    /**
     * May reach the provider dashboard / app. Everything traveller-facing
     * checks for 'approved' instead — a hidden provider signs in but is not
     * sold.
     */
    public function canSignIn(): bool
    {
        return in_array($this->status, ['approved', 'hidden'], true);
    }

    /**
     * Labels for every role this provider holds, in a fixed order so a provider
     * who is both always reads the same way round.
     */
    public function typeLabels(): array
    {
        $held = $this->types();
        return array_values(array_filter(
            array_map(
                fn ($type) => in_array($type, $held, true) ? self::TYPE_LABELS[$type] : null,
                array_keys(self::TYPE_LABELS),
            ),
        ));
    }

    /**
     * Every type this provider signed up as.
     *
     * A provider can be several things at once (an HLH that also runs a taxi is
     * an HLH and an OSP), so capability questions go through here rather than
     * through provider_type, which only names the primary one. Rows created
     * before provider_types existed fall back to that primary type.
     */
    public function types(): array
    {
        $types = $this->provider_types;
        return is_array($types) && $types !== []
            ? $types
            : array_filter([$this->provider_type]);
    }

    /** Does this provider act as the given type at all? */
    public function hasType(string $type): bool
    {
        return in_array($type, $this->types(), true);
    }

    /**
     * Providers holding a given role, primary or not — the query-side twin of
     * hasType(). An HLH that also runs a taxi must appear under OSP as well,
     * and must not vanish from the host list just because OSP happens to be
     * its primary type. Rows saved before provider_types existed fall back to
     * provider_type, exactly as types() does.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where(function ($q) use ($type) {
            $q->whereJsonContains('provider_types', $type)
                ->orWhere(function ($legacy) use ($type) {
                    $legacy->where('provider_type', $type)
                        ->where(function ($empty) {
                            $empty->whereNull('provider_types')
                                ->orWhereJsonLength('provider_types', 0);
                        });
                });
        });
    }

    /** Hosts author experiences; HCT reviews them. */
    public function isHost(): bool
    {
        return $this->hasType('hlh');
    }

    /** Service suppliers keep a rate card (rooms, taxi, guide, rental, other). */
    public function suppliesServices(): bool
    {
        return $this->hasType('osp');
    }

    /**
     * Regional partners sell nothing, so they fill in competences instead of a
     * catalogue — see the add_hrp_competences migration.
     */
    public function isRegionalPartner(): bool
    {
        return $this->hasType('hrp');
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
