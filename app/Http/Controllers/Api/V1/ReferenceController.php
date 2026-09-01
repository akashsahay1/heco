<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\BridgesAjax;
use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\Setting;
use App\Models\SystemList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public reference data — the option sets behind every dropdown and
 * multi-select in the app.
 *
 * The app must never ship its own copy of these: they are DB-managed lists that
 * HCT edits at runtime, and a hardcoded mobile copy is exactly how the two
 * surfaces fall out of sync.
 */
class ReferenceController extends Controller
{
    use BridgesAjax;

    /** The SystemList types the provider app renders. */
    private const LIST_TYPES = [
        'service_type',
        'accommodation_category',
        'vehicle_type',
        'guide_preference',
        'activity_type',
        'occupancy_unit',
        // The three jobs `occupancy_unit` used to do at once. It stays for
        // anything still reading it; these are what the forms now offer.
        'room_occupancy',
        'transport_unit',
        'activity_unit',
        'meal_plan',
        'room_category',
        'business_type',
        'document_type',
        // The languages a guide can be asked for. The app read this from a
        // bundled copy while the endpoint never sent it, so the two lists were
        // free to disagree — and once the bundle went, the dropdown was empty.
        'language',
        // Experience authoring (HLH).
        'experience_type',
        'day_inclusion',
        'best_season',
        // What a member offers (signup screen 8) — and what they can then list.
        // Kept separate per role: an HLH's "Experiential accommodation" and an
        // OSP's "Standard accommodation" are different products.
        'experience_category',
        'service_category',
        // HRP competences.
        'education_level',
        'english_level',
        'computer_skill_level',
    ];

    public function index(): JsonResponse
    {
        $lists = [];
        foreach (self::LIST_TYPES as $type) {
            $lists[$type] = SystemList::ofType($type)->pluck('name')->values();
        }

        return response()->json([
            'success' => true,
            'regions' => Region::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'country']),
            // Countries come from the regions HECO actually operates in rather
            // than a hardcoded world list.
            'countries' => Region::where('is_active', true)
                ->whereNotNull('country')
                ->distinct()
                ->orderBy('country')
                ->pluck('country')
                ->values(),
            'system_lists' => $lists,
            'links' => self::links(),
            'support' => self::support(),
        ]);
    }

    /**
     * How a member reaches HECO from the Help screen.
     *
     * The app shipped its own address and a placeholder phone number that rang
     * nobody, so the one thing a stuck member would try did nothing. These are
     * settings HCT edits in the control panel; anything left blank comes back
     * null and the app simply does not offer that way of getting in touch.
     */
    public static function support(): array
    {
        $value = function (string $key) {
            $set = trim((string) Setting::getValue($key, ''));
            return $set === '' ? null : $set;
        };

        return [
            // A member always has somewhere to write: the address the rest of
            // the site already answers on, until HCT names a partner-facing one.
            'email' => $value('support_email') ?? $value('site_email'),
            'phone' => $value('support_phone'),
            'hours' => $value('support_hours'),
        ];
    }

    /**
     * The pages the app links out to — terms, privacy, help.
     *
     * Built from this installation's own routes rather than written into the
     * app, for two reasons. The app shipped pointing at heco.travel/legal/…,
     * which does not exist, so every one of those links was dead. And the
     * address differs per environment — these resolve against whatever host
     * the request arrived on, so a device gets a URL it can actually open.
     */
    public static function links(): array
    {
        $portal = fn (string $name) => route($name, [], true);

        return [
            'terms' => $portal('terms'),
            'privacy' => $portal('privacy'),
            'help' => $portal('help'),
            'contact' => $portal('contact'),
            // The partner-facing ones. This app is only ever used by members of
            // the collective, and /guidelines is packing and safety advice
            // written for travellers — not what a provider is asking to read.
            'guidelines' => $portal('partner-guidelines'),
            // What a host, a supplier and a regional partner each do. The app
            // pointed at a heco.travel address that was never built, so the
            // link went nowhere; this is the page that answers the question.
            'roles' => $portal('partner-guidelines'),
            'data_deletion' => $portal('data-deletion'),
        ];
    }

}
