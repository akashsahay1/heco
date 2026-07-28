<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\BridgesAjax;
use App\Http\Controllers\Controller;
use App\Models\Region;
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
        'meal_plan',
        'room_category',
        'business_type',
        'document_type',
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
        ]);
    }

    /**
     * `submit_sp_application` — the public "become a partner" form.
     *
     * This list is an allow-list: anything the app sends that is not named here
     * is dropped silently, before the handler ever sees it. A field added to
     * both the app and the handler still arrives empty until it is added here
     * too, and nothing complains — so every new question on the signup screens
     * has to be added to this list in the same change.
     */
    public function submitApplication(Request $request): JsonResponse
    {
        return $this->ajax('submit_sp_application', $this->only($request, [
            // An applicant can hold more than one role; `provider_type` stays
            // for older builds that only ever sent one.
            'provider_type', 'provider_types',
            'business_type', 'registration_number', 'year_established',
            // Whether there is a business at all. Null is a real answer here —
            // it means the question was never put to them.
            'has_business',
            'name', 'contact_person', 'email', 'phone_1', 'phone_2',
            'region_id', 'address', 'city', 'postal_code', 'country',
            'password', 'password_confirmation',
            // Which travellers they can host.
            'speaks_english', 'speaks_hindi', 'other_languages',
            // What they offer, per role held.
            'experience_categories', 'service_categories', 'other_services',
            'services_offered', 'accommodation_categories',
            'vehicle_types', 'guide_types', 'activity_types', 'description', 'notes',
            // "Many users won't regularly check their email."
            'contact_by_email', 'contact_by_whatsapp',
            'document_labels',
        ]), $request); // forward uploaded `documents[]` files
    }
}
