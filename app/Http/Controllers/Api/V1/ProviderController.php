<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\BridgesAjax;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderAccountResource;
use App\Models\ServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The provider-facing mobile API.
 *
 * Every method here is a translation layer: it names the AJAX key the portal
 * already implements and hands over the whitelisted payload. Authorization,
 * ownership and validation all stay in AjaxController, which is what keeps the
 * app and the web portal in step.
 */
class ProviderController extends Controller
{
    use BridgesAjax;

    // ── Profile ──────────────────────────────────────────────────────────

    public function profile(): JsonResponse
    {
        $provider = ServiceProvider::with('region')->where('user_id', Auth::id())->first();

        if (!$provider) {
            return response()->json(['error' => 'No service provider profile found.'], 404);
        }

        return response()->json([
            'success' => true,
            'provider' => ProviderAccountResource::make($provider),
        ]);
    }

    /**
     * `update_sp_photo` — the profile picture on its own.
     *
     * Separate from updateProfile because that is a PUT, and PHP does not parse
     * a multipart body on PUT — and because changing a picture should not mean
     * re-posting the whole profile.
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        return $this->ajax('update_sp_photo', $this->only($request, ['remove_photo']), $request);
    }

    /**
     * `add_sp_document` — file a verification document after signup.
     *
     * The files themselves ride in the file bag as `documents[]`; only the
     * labels that pair with them are fields.
     */
    public function addDocument(Request $request): JsonResponse
    {
        return $this->ajax('add_sp_document', $this->only($request, ['document_labels']), $request);
    }

    /**
     * `update_sp_profile` — identity, contact, bank and the capability sets.
     *
     * Returns the saved record rather than a bare `{success:true}` so the app
     * renders what the database actually holds instead of echoing back its own
     * form state.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $result = $this->ajax('update_sp_profile', $this->only($request, [
            'name', 'contact_person', 'email', 'phone_1', 'phone_2', 'address',
            'bank_name', 'bank_ifsc', 'bank_account_name', 'bank_account_number', 'upi',
            'services_offered', 'accommodation_categories', 'vehicle_types',
            'guide_types', 'activity_types',
            // The picture itself travels in the file bag, not here; this is the
            // flag that clears one.
            'remove_photo',
        ]), $request);

        if ($result->getStatusCode() !== 200) {
            return $result;
        }

        return $this->profile();
    }

    // ── Bookings ─────────────────────────────────────────────────────────

    /** `get_sp_assigned_trips` — the trips this provider is attached to. */
    public function bookings(): JsonResponse
    {
        return $this->ajax('get_sp_assigned_trips');
    }

    /**
     * `get_hrp_region_providers` — the hosts and suppliers a regional partner
     * oversees. Read-only; the ajax handler refuses anyone who is not an HRP.
     */
    public function regionProviders(): JsonResponse
    {
        return $this->ajax('get_hrp_region_providers');
    }

    // ── Rate card (sp_pricing) ───────────────────────────────────────────

    public function pricing(): JsonResponse
    {
        return $this->ajax('get_sp_pricing');
    }

    public function savePricing(Request $request): JsonResponse
    {
        return $this->ajax('save_sp_pricing', $this->only($request, [
            'id', 'service_type', 'category', 'description', 'unit', 'price', 'notes',
            'room_category', 'comfort_tier', 'total_rooms', 'default_occupancy', 'meal_plan',
            'vehicle_type', 'vehicle_capacity', 'driver_allowance', 'distance_km',
            'vehicle_make_model', 'vehicle_registration_no', 'vehicle_year',
            'vehicle_photos_keep', 'driver_included', 'fuel_tolls_extra',
            'min_group', 'max_group', 'specialties', 'is_active',
            // The fields the client's service forms ask for. Anything not
            // listed here is dropped in silence, so a rate saved from the app
            // came back with every one of these empty.
            'ac_available', 'ac_extra_cost', 'vehicle_count',
            'price_per_km_plains', 'price_per_km_hills',
            'speaks_english', 'languages', 'wage_multi_day',
            'is_certified', 'has_first_aid',
            'rental_item', 'security_deposit',
            'latitude', 'longitude', 'guest_capacity', 'seasonality_notes',
            'photos_keep',
        ]), $request); // forward uploaded `vehicle_photos[]` files
    }

    public function deletePricing(int $id): JsonResponse
    {
        return $this->ajax('delete_sp_pricing', ['id' => $id]);
    }

    // ── Availability ─────────────────────────────────────────────────────

    public function availability(Request $request): JsonResponse
    {
        return $this->ajax('get_sp_calendar', $this->only($request, ['year', 'month']));
    }

    public function blockDates(Request $request): JsonResponse
    {
        return $this->ajax('sp_block_dates', $this->only($request, ['dates', 'notes']));
    }

    public function unblockDates(Request $request): JsonResponse
    {
        return $this->ajax('sp_unblock_dates', $this->only($request, ['dates']));
    }

    public function saveIcalUrl(Request $request): JsonResponse
    {
        return $this->ajax('sp_save_ical_url', $this->only($request, ['ical_url']));
    }

    public function syncIcal(): JsonResponse
    {
        return $this->ajax('sp_sync_ical_now');
    }

    // ── Experiences (HLH hosts author their own) ─────────────────────────

    public function experiences(): JsonResponse
    {
        return $this->ajax('get_sp_experiences');
    }

    public function saveExperience(Request $request): JsonResponse
    {
        return $this->ajax('save_sp_experience', $this->only($request, [
            'id', 'name', 'slug', 'type', 'region_id', 'regenerative_project_id',
            'short_description', 'long_description', 'unique_description', 'cultural_context',
            'duration_type', 'duration_hours', 'duration_days', 'duration_nights',
            'start_time', 'end_time',
            'includes_accommodation', 'accommodation_category',
            'includes_meals_breakfast', 'includes_meals_lunch', 'includes_meals_dinner',
            'includes_guide', 'includes_transport',
            'start_latitude', 'start_longitude', 'end_latitude', 'end_longitude',
            'area', 'altitude_min', 'altitude_max', 'trekking_required', 'road_seasonal_closure',
            'difficulty_level', 'fitness_requirements', 'age_min', 'age_max',
            'group_size_min', 'group_size_max', 'weather_dependency',
            'cultural_sensitivities', 'environmental_constraints',
            'best_seasons', 'available_months', 'restricted_months', 'unavailable_months',
            'seasonality_notes',
            'price_currency', 'cost_accommodation', 'cost_logistics', 'cost_guide',
            'cost_activities', 'cost_other', 'single_supplement', 'seasonal_price_variation',
            'price_slabs', 'experience_days',
            // Category decides the form's shape; add-ons hang off the listing;
            // save_as_draft keeps a half-finished listing out of HCT's queue.
            'category', 'addons', 'save_as_draft',
            // An experiential stay's capacity and its occupancy × meal-plan grid.
            'total_rooms', 'total_guests', 'room_rates',
            'osps_involved', 'osp_services', 'traveller_bring_list',
            'clothing_recommendations', 'health_notes', 'connectivity_notes', 'cultural_etiquette',
            'operational_risks', 'past_issues', 'backup_options', 'emergency_notes',
            'is_active',
        ]), $request);
    }

    public function toggleExperience(int $id): JsonResponse
    {
        return $this->ajax('toggle_sp_experience', ['id' => $id]);
    }

    public function deleteExperience(int $id): JsonResponse
    {
        return $this->ajax('delete_sp_experience', ['id' => $id]);
    }

    // ── Support ──────────────────────────────────────────────────────────

    public function requestSupport(Request $request): JsonResponse
    {
        return $this->ajax('request_support', $this->only($request, [
            'name', 'email', 'phone', 'subject', 'message',
        ]));
    }
}
