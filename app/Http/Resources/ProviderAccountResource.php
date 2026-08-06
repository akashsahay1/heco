<?php

namespace App\Http\Resources;

use App\Models\ServiceProvider;
use App\Models\Setting;

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
            // provider_type names only the primary role. A provider can hold
            // several at once — an HLH that also runs a taxi is an HLH and an
            // OSP — and the app decides which tabs to show from this, so it
            // needs the whole set or it hides half of what they signed up for.
            'provider_type' => $provider->provider_type,
            'provider_types' => $provider->types(),
            // Banned and hidden both leave here as 'out_of_service'. Which of
            // the two it is, and why, is HCT's business — the app must not be
            // able to tell a member they were banned, or that they were merely
            // paused, and a wire value it never receives cannot leak either.
            'status' => in_array($provider->status, ['banned', 'hidden'], true)
                ? 'out_of_service'
                : $provider->status,
            // Whether this account still gets the working app. It is a separate
            // question from the status now: a hidden provider signs in and
            // manages their rates as usual, they are just not offered to
            // travellers, so the app cannot read it off the status alone.
            'can_sign_in' => $provider->canSignIn(),
            'name' => $provider->name,
            'contact_person' => $provider->contact_person,
            'email' => $provider->email,
            'phone_1' => $provider->phone_1,
            'phone_2' => $provider->phone_2,
            'region_id' => $provider->region_id,
            'region_name' => $provider->region?->name,
            'address' => $provider->address,
            // How many experiences this member may file. The server already
            // refuses the eleventh; the app shows how much room is left, and
            // must read the number from here rather than repeat it, because
            // HCT can change it in settings at any time.
            //
            // Only experiences are capped — a supplier's rate card is not, so
            // there is deliberately no second number here to report.
            'limits' => [
                'experiences' => self::cap('max_experiences_per_provider'),
            ],
            // The app has always been able to render this — it just never had
            // one to render, so every member saw their initials.
            'avatar_url' => $provider->photo ?: null,
            // What they filed to be verified. Sent so a member can see their
            // own paperwork; whether HCT has accepted it is not here, because
            // nothing records that — the application's status is the answer.
            'documents' => self::documents($provider->documents),
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

    /**
     * A listing cap as a number. Mirrors AjaxController::listingCap — the
     * setting is free text, so a blank or nonsense value falls back to the
     * same default the server enforces rather than reading as "no limit".
     */
    private static function cap(string $key): int
    {
        $value = Setting::getValue($key, 10);

        return is_numeric($value) ? (int) $value : 10;
    }

    /**
     * The stored documents as the app reads them: the label it was filed
     * under, something openable, and the name of the file the member chose.
     *
     * `path` becomes `url` because that is what it is to the app — the same
     * site-relative form as avatar_url, which the app resolves against
     * whichever origin it connected to.
     */
    private static function documents(mixed $value): array
    {
        $documents = is_array($value) ? $value : [];

        return array_values(array_map(fn (array $document): array => [
            'label' => $document['label'] ?? 'Document',
            'url' => $document['path'] ?? null,
            'original_name' => $document['original_name'] ?? null,
        ], $documents));
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
