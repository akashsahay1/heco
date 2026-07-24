<?php

namespace App\Http\Resources;

use App\Models\ServiceProvider;

/**
 * The `service_providers` row as the mobile app expects it.
 *
 * Kept in one place because login, /auth/me and /provider/profile all return
 * the same shape — and because the app's ProviderAccount.fromJson is written
 * against exactly these keys.
 */
class ProviderAccountResource
{
    public static function make(?ServiceProvider $provider): ?array
    {
        if (!$provider) {
            return null;
        }

        return [
            'id' => $provider->id,
            'provider_type' => $provider->provider_type,
            'status' => $provider->status,
            'name' => $provider->name,
            'contact_person' => $provider->contact_person,
            'email' => $provider->email,
            'phone_1' => $provider->phone_1,
            'phone_2' => $provider->phone_2,
            'region_id' => $provider->region_id,
            'region_name' => $provider->region?->name,
            'address' => $provider->address,
            'services_offered' => self::list($provider->services_offered),
            'accommodation_categories' => self::list($provider->accommodation_categories),
            'vehicle_types' => self::list($provider->vehicle_types),
            'guide_types' => self::list($provider->guide_types),
            'activity_types' => self::list($provider->activity_types),
            'bank' => [
                'bank_name' => $provider->bank_name,
                'bank_ifsc' => $provider->bank_ifsc,
                'bank_account_name' => $provider->bank_account_name,
                'bank_account_number' => $provider->bank_account_number,
                'upi' => $provider->upi,
            ],
            'markup_percent' => $provider->markup_percent,
            'ical_url' => $provider->ical_url,
            'approved_at' => $provider->approved_at,
            'created_at' => $provider->created_at,
        ];
    }

    private static function list(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_string($value) && $value !== '') {
            return array_values(json_decode($value, true) ?: []);
        }
        return [];
    }
}
