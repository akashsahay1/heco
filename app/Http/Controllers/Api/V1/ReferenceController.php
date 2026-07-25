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
        // Experience authoring (HLH/OSP).
        'experience_type',
        'day_inclusion',
        'best_season',
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

    /** `submit_sp_application` — the public "become a partner" form. */
    public function submitApplication(Request $request): JsonResponse
    {
        return $this->ajax('submit_sp_application', $this->only($request, [
            'provider_type', 'business_type', 'registration_number', 'year_established',
            'name', 'contact_person', 'email', 'phone_1', 'phone_2',
            'region_id', 'address', 'city', 'postal_code', 'country',
            'services_offered', 'accommodation_categories',
            'vehicle_types', 'guide_types', 'activity_types', 'description', 'notes',
            'document_labels',
        ]), $request); // forward uploaded `documents[]` files
    }
}
