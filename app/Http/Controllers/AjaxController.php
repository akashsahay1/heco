<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SpPricing;
use App\Models\Experience;
use App\Models\RegenerativeProject;
use App\Models\Trip;
use App\Models\TripRegion;
use App\Models\TripDay;
use App\Models\TripDayExperience;
use App\Models\TripDayService;
use App\Models\TripSelectedExperience;
use App\Models\Lead;
use App\Models\TravellerPayment;
use App\Models\SpPayment;
use App\Models\SpPaymentEntry;
use App\Models\SupportRequest;
use App\Models\AiConversation;
use App\Models\AiPrompt;
use App\Models\SystemList;
use App\Models\Setting;
use App\Models\Currency;
use App\Models\ActivityLog;
use App\Models\PdfTemplate;
use App\Models\Review;
use App\Models\SpAvailability;
use App\Services\OllamaService;
use App\Services\GeminiService;
use App\Services\GroqService;
use App\Services\PromptBuilderService;
use App\Services\ItineraryService;
use App\Services\CostCalculatorService;
use App\Services\LeadService;
use App\Services\ImpactCalculatorService;
use App\Services\SpAvailabilityService;

class AjaxController extends Controller
{
    /**
     * Resolve the current trip for a logged-in user only.
     */
    protected function resolveTrip(Request $request): ?Trip
    {
        $user = Auth::user();
        if (!$user) return null;

        if ($request->filled('trip_id') && $request->trip_id !== 'guest') {
            return Trip::where('id', $request->trip_id)->where('user_id', $user->id)->first();
        }
        return Trip::where('user_id', $user->id)
            ->whereIn('status', ['not_confirmed'])
            ->orderBy('updated_at', 'desc')
            ->first();
    }

    /**
     * Get or create a trip for a logged-in user.
     */
    protected function ensureAuthTrip(Request $request): Trip
    {
        $trip = $this->resolveTrip($request);
        if ($trip) return $trip;

        return Trip::create([
            'trip_id' => Trip::generateTripId(),
            'user_id' => Auth::id(),
            'trip_name' => 'My Trip',
            'status' => 'not_confirmed',
            'stage' => 'open',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]);
    }

    /**
     * Get guest trip data from session.
     */
    protected function guestTrip(): array
    {
        return session('guest_trip', [
            'experience_ids' => [],
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'trip_name' => 'My Trip',
            'accommodation_comfort' => '',
            'vehicle_comfort' => '',
            'guide_preference' => '',
            'travel_pace' => '',
            'budget_sensitivity' => '',
            'start_location' => '',
            'end_location' => '',
            'start_date' => '',
            'end_date' => '',
            'budget_notes' => '',
            'anchor_point' => '',
            'pickup_preference' => '',
            'ai_itinerary' => null,
            'ai_raw_response' => null,
        ]);
    }

    /**
     * Save guest trip data to session.
     */
    protected function saveGuestTrip(array $data): void
    {
        session(['guest_trip' => $data]);
    }

    /**
     * Compute pricing from session itinerary data for guests.
     */
    protected function computeGuestPricing(array $guestData): array
    {
        $adults = $guestData['adults'] ?: 1;
        $activityCost = 0;
        $numDays = 0;

        $itinerary = $guestData['ai_itinerary'] ?? null;
        if ($itinerary && isset($itinerary['days'])) {
            $numDays = count($itinerary['days']);
            $expIds = [];
            foreach ($itinerary['days'] as $day) {
                foreach ($day['experiences'] ?? [] as $exp) {
                    if (isset($exp['experience_id'])) {
                        $expIds[] = $exp['experience_id'];
                    }
                }
            }
            if (!empty($expIds)) {
                $experiences = Experience::whereIn('id', $expIds)->pluck('base_cost_per_person', 'id');
                foreach ($itinerary['days'] as $day) {
                    foreach ($day['experiences'] ?? [] as $exp) {
                        $eid = $exp['experience_id'] ?? null;
                        $costPerPerson = $experiences[$eid] ?? 0;
                        $activityCost += $costPerPerson * $adults;
                    }
                }
            }
        }

        // Estimate transport, accommodation, guide using base rates and preference multipliers
        $baseTransport = (float) Setting::getValue('base_transport_per_day', 3500);
        $baseAccommodation = (float) Setting::getValue('base_accommodation_per_night', 2500);
        $baseGuide = (float) Setting::getValue('base_guide_per_day', 2000);

        $accomMultiplier = match ($guestData['accommodation_comfort'] ?? '') {
            'Cat E - Camping/Tents' => 0.5,
            'Cat D - Basic/Homestay' => 0.7,
            'Cat C - Standard' => 1.0,
            'Cat B - Comfort' => 1.5,
            'Cat A - Premium/Luxury' => 2.5,
            default => 1.0,
        };
        $vehicleMultiplier = match ($guestData['vehicle_comfort'] ?? '') {
            'Local Transport' => 0.5,
            'SUV (Bolero/Scorpio)' => 0.8,
            'SUV (Innova/Crysta)' => 1.0,
            'Premium (Fortuner/Similar)' => 1.5,
            'Tempo Traveller' => 1.3,
            default => 1.0,
        };
        $guideMultiplier = match ($guestData['guide_preference'] ?? '') {
            'No Guide' => 0.0,
            'Local Guide' => 0.7,
            'English-speaking' => 1.0,
            'Certified/Expert' => 1.5,
            default => 1.0,
        };

        $numNights = max($numDays - 1, 0);
        $transportCost = round($baseTransport * $numDays * $vehicleMultiplier);
        $accommodationCost = round($baseAccommodation * $numNights * $adults * $accomMultiplier);
        $guideCost = round($baseGuide * $numDays * $guideMultiplier);

        $totalCost = $transportCost + $accommodationCost + $guideCost + $activityCost;
        $rpPercent = (float) Setting::getValue('default_rp_margin_percent', 5);
        $hrpPercent = (float) Setting::getValue('default_hrp_margin_percent', 10);
        $hctPercent = (float) Setting::getValue('default_hct_commission_percent', 15);

        $rpAmount = round($totalCost * $rpPercent / 100, 2);
        $hrpAmount = round($totalCost * $hrpPercent / 100, 2);
        $hctAmount = round($totalCost * $hctPercent / 100, 2);

        $subtotal = $totalCost + $rpAmount + $hrpAmount + $hctAmount;
        $gstPercent = (float) Setting::getValue('gst_percent', 5);
        $gstAmount = round($subtotal * $gstPercent / 100, 2);
        $finalPrice = $subtotal + $gstAmount;

        return [
            'transport_cost' => $transportCost,
            'accommodation_cost' => $accommodationCost,
            'guide_cost' => $guideCost,
            'activity_cost' => $activityCost,
            'other_cost' => 0,
            'total_cost' => $totalCost,
            'margin_rp_percent' => $rpPercent,
            'margin_rp_amount' => $rpAmount,
            'margin_hrp_percent' => $hrpPercent,
            'margin_hrp_amount' => $hrpAmount,
            'commission_hct_percent' => $hctPercent,
            'commission_hct_amount' => $hctAmount,
            'subtotal' => $subtotal,
            'gst_amount' => $gstAmount,
            'final_price' => $finalPrice,
        ];
    }

    /**
     * Build timeline response from session itinerary for guests.
     */
    protected function buildGuestTimeline(array $guestData): array
    {
        $itinerary = $guestData['ai_itinerary'] ?? null;
        if (!$itinerary || !isset($itinerary['days'])) return [];

        $adults = $guestData['adults'] ?: 1;
        $currentExpIds = $guestData['experience_ids'] ?? [];

        // Collect all experience IDs to fetch from DB in one query (only those still in trip)
        $expIds = [];
        foreach ($itinerary['days'] as $day) {
            foreach ($day['experiences'] ?? [] as $exp) {
                if (isset($exp['experience_id']) && in_array($exp['experience_id'], $currentExpIds)) {
                    $expIds[] = $exp['experience_id'];
                }
            }
        }
        $experiences = Experience::with('days')->whereIn('id', $expIds)->get()->keyBy('id');

        $days = [];
        $dayNum = 0;
        foreach ($itinerary['days'] as $i => $day) {
            $dayExperiences = [];
            $j = 0;
            foreach ($day['experiences'] ?? [] as $exp) {
                $eid = $exp['experience_id'] ?? null;
                // Skip experiences that were removed from the trip
                if ($eid && !in_array($eid, $currentExpIds)) continue;
                $expModel = $eid ? ($experiences[$eid] ?? null) : null;
                $costPerPerson = $expModel ? $expModel->base_cost_per_person : 0;

                $dayExperiences[] = [
                    'id' => ($i + 1) * 100 + $j + 1,
                    'experience_id' => $eid,
                    'start_time' => $exp['start_time'] ?? null,
                    'end_time' => $exp['end_time'] ?? null,
                    'notes' => $exp['notes'] ?? null,
                    'cost_per_person' => $costPerPerson,
                    'total_cost' => $costPerPerson * $adults,
                    'experience' => $expModel ? $expModel->toArray() : null,
                ];
                $j++;
            }

            // Skip days with no experiences and no description after filtering
            if (empty($dayExperiences) && empty($day['description'])) continue;
            $dayNum++;

            $days[] = [
                'id' => $i + 1, // raw itinerary index (1-based) for correct removal
                'day_number' => $dayNum,
                'title' => $day['title'] ?? 'Day ' . $dayNum,
                'description' => $day['description'] ?? null,
                'date' => $day['date'] ?? null,
                'is_locked' => false,
                'experiences' => $dayExperiences,
                'services' => [],
            ];
        }

        return $days;
    }

    /**
     * Normalize AI itinerary: convert array description/notes to newline strings.
     */
    protected function normalizeItinerary(array $parsed): array
    {
        if (!isset($parsed['days'])) return $parsed;

        foreach ($parsed['days'] as &$day) {
            if (isset($day['description']) && is_array($day['description'])) {
                $day['description'] = implode("\n", $day['description']);
            }
            if (isset($day['notes']) && is_array($day['notes'])) {
                $day['description'] = ($day['description'] ?? '') . "\n" . implode("\n", $day['notes']);
            }
            foreach ($day['experiences'] ?? [] as &$exp) {
                if (isset($exp['notes']) && is_array($exp['notes'])) {
                    $exp['notes'] = implode("\n", $exp['notes']);
                }
                if (isset($exp['description']) && is_array($exp['description'])) {
                    $exp['notes'] = ($exp['notes'] ?? '') . "\n" . implode("\n", $exp['description']);
                }
            }
        }

        return $parsed;
    }

    /**
     * Attempt to repair truncated JSON by closing open brackets/braces.
     */
    protected function repairTruncatedJson(string $json): string
    {
        // Trim trailing incomplete string/value
        $json = preg_replace('/,\s*"[^"]*$/', '', $json);
        $json = preg_replace('/,\s*$/', '', $json);

        // Count unclosed brackets and braces
        $opens = 0;
        $opensArr = 0;
        $inString = false;
        $escape = false;

        for ($i = 0; $i < strlen($json); $i++) {
            $ch = $json[$i];
            if ($escape) { $escape = false; continue; }
            if ($ch === '\\') { $escape = true; continue; }
            if ($ch === '"') { $inString = !$inString; continue; }
            if ($inString) continue;
            if ($ch === '{') $opens++;
            elseif ($ch === '}') $opens--;
            elseif ($ch === '[') $opensArr++;
            elseif ($ch === ']') $opensArr--;
        }

        // Close any unclosed brackets/braces
        $json .= str_repeat(']', max(0, $opensArr));
        $json .= str_repeat('}', max(0, $opens));

        return $json;
    }

    /**
     * Call AI: try Gemini first, fall back to Ollama.
     */
    protected function callAi(array $messages, array $options = []): ?array
    {
        // Allow callers to override timeout for faster fallback (e.g. itinerary generation)
        $fastTimeout = $options['fast_timeout'] ?? null;
        $maxRetries = $options['max_retries'] ?? 2;

        $gemini = app(GeminiService::class);
        if ($gemini->isAvailable()) {
            $geminiOpts = $options;
            if ($fastTimeout) $geminiOpts['timeout'] = $fastTimeout;
            $response = $gemini->chat($messages, $geminiOpts);
            if ($response) return $response;
        }

        $groq = app(GroqService::class);
        if ($groq->isAvailable()) {
            $groqOpts = $options;
            if ($fastTimeout) $groqOpts['timeout'] = $fastTimeout;
            // Retry Groq with delay on rate limit (429)
            for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
                $response = $groq->chat($messages, $groqOpts);
                if ($response) return $response;
                if ($attempt < $maxRetries) {
                    sleep(3); // Wait 3 seconds before retry (TPM limit resets quickly)
                }
            }
        }

        $ollama = app(OllamaService::class);
        if (!$ollama->isAvailable()) return null;

        return $ollama->chat($messages, $options['model'] ?? null, $options);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            // ===== AUTH & USER =====
            if ($request->has('userlogin') || $request->has('login')) {
                return $this->userLogin($request);
            }
            if ($request->has('usersignup') || $request->has('register')) {
                return $this->userSignup($request);
            }
            if ($request->has('update_profile')) {
                return $this->updateProfile($request);
            }
            if ($request->has('change_password')) {
                return $this->changePassword($request);
            }

            // ===== TRAVELLER HOMEPAGE =====
            if ($request->has('get_regions_for_map')) {
                return $this->getRegionsForMap($request);
            }
            if ($request->has('get_experiences_for_discover')) {
                return $this->getExperiencesForDiscover($request);
            }
            if ($request->has('get_experience_detail')) {
                return $this->getExperienceDetail($request);
            }
            if ($request->has('get_reviews')) {
                return $this->getReviews($request);
            }
            if ($request->has('check_review_eligibility')) {
                return $this->checkReviewEligibility($request);
            }
            if ($request->has('submit_review')) {
                return $this->submitReview($request);
            }
            if ($request->has('set_landing_preferences')) {
                return $this->setLandingPreferences($request);
            }
            if ($request->has('chat_with_ai')) {
                return $this->chatWithAi($request);
            }
            if ($request->has('create_trip')) {
                return $this->createTrip($request);
            }
            if ($request->has('get_trip_selected_experiences')) {
                return $this->getTripSelectedExperiences($request);
            }
            if ($request->has('get_trip_timeline')) {
                return $this->getTripTimeline($request);
            }
            if ($request->has('get_chat_history')) {
                return $this->getChatHistory($request);
            }
            if ($request->has('sync_guest_journey')) {
                return $this->syncGuestJourney($request);
            }
            if ($request->has('generate_itinerary')) {
                return $this->generateItinerary($request);
            }
            if ($request->has('add_experience_to_trip')) {
                return $this->addExperienceToTrip($request);
            }
            if ($request->has('remove_experience_from_trip')) {
                return $this->removeExperienceFromTrip($request);
            }
            if ($request->has('prefer_experience')) {
                return $this->preferExperience($request);
            }
            if ($request->has('get_wishlist')) {
                return $this->getWishlist($request);
            }
            if ($request->has('reorder_experiences')) {
                return $this->reorderExperiences($request);
            }
            if ($request->has('update_group_details')) {
                return $this->updateGroupDetails($request);
            }
            if ($request->has('update_trip_start_date')) {
                return $this->updateTripStartDate($request);
            }
            if ($request->has('update_travel_preferences')) {
                return $this->updateTravelPreferences($request);
            }
            if ($request->has('save_trip_name')) {
                return $this->saveTripName($request);
            }
            if ($request->has('add_day_to_trip')) {
                return $this->addDayToTrip($request);
            }
            if ($request->has('remove_day_from_trip')) {
                return $this->removeDayFromTrip($request);
            }
            if ($request->has('get_trip_pricing')) {
                return $this->getTripPricing($request);
            }
            if ($request->has('get_trip_impact')) {
                return $this->getTripImpact($request);
            }
            if ($request->has('request_support')) {
                return $this->requestSupport($request);
            }
            if ($request->has('get_user_trips')) {
                return $this->getUserTrips($request);
            }
            if ($request->has('erase_trip')) {
                return $this->eraseTrip($request);
            }

            // ===== HCT DASHBOARD =====
            if ($request->has('get_dashboard_stats')) {
                return $this->getDashboardStats($request);
            }
            if ($request->has('create_hct_user')) {
                return $this->createHctUser($request);
            }
            if ($request->has('update_hct_user')) {
                return $this->updateHctUser($request);
            }
            if ($request->has('deactivate_hct_user')) {
                return $this->deactivateHctUser($request);
            }
            if ($request->has('get_system_lists')) {
                return $this->getSystemLists($request);
            }
            if ($request->has('save_system_list_item')) {
                return $this->saveSystemListItem($request);
            }
            if ($request->has('deactivate_system_list_item')) {
                return $this->deactivateSystemListItem($request);
            }
            if ($request->has('get_support_requests')) {
                return $this->getSupportRequests($request);
            }
            if ($request->has('resolve_support_request')) {
                return $this->resolveSupportRequest($request);
            }
            if ($request->has('chat_with_ai_hct')) {
                return $this->chatWithAiHct($request);
            }
            if ($request->has('get_lead_reminders')) {
                return $this->getLeadReminders($request);
            }
            if ($request->has('get_leads')) {
                return $this->getLeads($request);
            }
            if ($request->has('update_lead')) {
                return $this->updateLead($request);
            }
            if ($request->has('get_lead_history')) {
                return $this->getLeadHistory($request);
            }
            if ($request->has('get_upcoming_trips')) {
                return $this->getUpcomingTrips($request);
            }
            if ($request->has('get_trips_by_date_range')) {
                return $this->getTripsByDateRange($request);
            }
            if ($request->has('update_trip_status')) {
                return $this->updateTripStatus($request);
            }
            if ($request->has('get_calendar_trips')) {
                return $this->getCalendarTrips($request);
            }
            if ($request->has('get_sp_payments')) {
                return $this->getSpPayments($request);
            }
            if ($request->has('add_sp_payment_entry')) {
                return $this->addSpPaymentEntry($request);
            }
            if ($request->has('edit_sp_payment_entry')) {
                return $this->editSpPaymentEntry($request);
            }
            if ($request->has('get_sp_payment_history')) {
                return $this->getSpPaymentHistory($request);
            }
            if ($request->has('get_traveller_payments_overview')) {
                return $this->getTravellerPaymentsOverview($request);
            }
            if ($request->has('get_gst_report')) {
                return $this->getGstReport($request);
            }
            if ($request->has('get_providers')) {
                return $this->getProviders($request);
            }
            if ($request->has('edit_provider')) {
                return $this->editProvider($request);
            }
            if ($request->has('get_provider_trips')) {
                return $this->getProviderTrips($request);
            }
            if ($request->has('get_provider_payment_history')) {
                return $this->getProviderPaymentHistory($request);
            }
            if ($request->has('get_travelers_list')) {
                return $this->getTravelersList($request);
            }
            if ($request->has('get_traveler_trips')) {
                return $this->getTravelerTrips($request);
            }
            if ($request->has('get_traveler_payment_history')) {
                return $this->getTravelerPaymentHistory($request);
            }
            if ($request->has('get_provider_applications')) {
                return $this->getProviderApplications($request);
            }
            if ($request->has('approve_provider')) {
                return $this->approveProvider($request);
            }
            if ($request->has('reject_provider')) {
                return $this->rejectProvider($request);
            }

            // ===== REGION MANAGEMENT =====
            if ($request->has('get_regions_list')) {
                return $this->getRegionsList($request);
            }
            if ($request->has('save_region')) {
                return $this->saveRegion($request);
            }
            if ($request->has('toggle_region')) {
                return $this->toggleRegion($request);
            }
            if ($request->has('delete_region')) {
                return $this->deleteRegion($request);
            }

            // ===== CURRENCY MANAGEMENT =====
            if ($request->has('get_currencies_list')) {
                return $this->getCurrenciesList($request);
            }
            if ($request->has('save_currency')) {
                return $this->saveCurrency($request);
            }
            if ($request->has('toggle_currency')) {
                return $this->toggleCurrency($request);
            }
            if ($request->has('delete_currency')) {
                return $this->deleteCurrency($request);
            }

            // ===== EXPERIENCE & RP MANAGEMENT =====
            if ($request->has('get_experiences_list')) {
                return $this->getExperiencesList($request);
            }
            if ($request->has('save_experience')) {
                return $this->saveExperience($request);
            }
            if ($request->has('disable_experience')) {
                return $this->disableExperience($request);
            }
            if ($request->has('get_regenerative_projects')) {
                return $this->getRegenerativeProjects($request);
            }
            if ($request->has('save_regenerative_project')) {
                return $this->saveRegenerativeProject($request);
            }
            if ($request->has('disable_regenerative_project')) {
                return $this->disableRegenerativeProject($request);
            }

            // ===== TRIP MANAGER =====
            if ($request->has('get_trip_info')) {
                return $this->getTripInfo($request);
            }
            if ($request->has('update_trip_info')) {
                return $this->updateTripInfo($request);
            }
            if ($request->has('add_traveller_payment')) {
                return $this->addTravellerPayment($request);
            }
            if ($request->has('get_traveller_payment_history')) {
                return $this->getTravellerPaymentHistory($request);
            }
            if ($request->has('edit_traveller_payment')) {
                return $this->editTravellerPayment($request);
            }
            if ($request->has('get_trip_itinerary')) {
                return $this->getTripItinerary($request);
            }
            if ($request->has('search_experiences_for_trip')) {
                return $this->searchExperiencesForTrip($request);
            }
            if ($request->has('add_experience_to_day')) {
                return $this->addExperienceToDay($request);
            }
            if ($request->has('remove_experience_from_day')) {
                return $this->removeExperienceFromDay($request);
            }
            if ($request->has('reorder_trip_days')) {
                return $this->reorderTripDays($request);
            }
            if ($request->has('add_trip_day')) {
                return $this->addTripDay($request);
            }
            if ($request->has('remove_trip_day')) {
                return $this->removeTripDay($request);
            }
            if ($request->has('get_day_services')) {
                return $this->getDayServices($request);
            }
            if ($request->has('add_day_service')) {
                return $this->addDayService($request);
            }
            if ($request->has('edit_day_service')) {
                return $this->editDayService($request);
            }
            if ($request->has('remove_day_service')) {
                return $this->removeDayService($request);
            }
            if ($request->has('change_day_service_provider')) {
                return $this->changeDayServiceProvider($request);
            }
            if ($request->has('request_ai_recalculation')) {
                return $this->requestAiRecalculation($request);
            }
            if ($request->has('recalculate_trip_cost')) {
                return $this->recalculateTripCost($request);
            }

            // ===== SP APPLICATION =====
            if ($request->has('submit_sp_application')) {
                return $this->submitSpApplication($request);
            }

            // ===== SP AVAILABILITY (Portal) =====
            if ($request->has('get_sp_calendar')) {
                return $this->getSpCalendar($request);
            }
            if ($request->has('sp_block_dates')) {
                return $this->spBlockDates($request);
            }
            if ($request->has('sp_unblock_dates')) {
                return $this->spUnblockDates($request);
            }
            if ($request->has('sp_save_ical_url')) {
                return $this->spSaveIcalUrl($request);
            }
            if ($request->has('sp_sync_ical_now')) {
                return $this->spSyncIcalNow($request);
            }

            // ===== SP AVAILABILITY (Admin) =====
            if ($request->has('admin_get_sp_calendar')) {
                return $this->adminGetSpCalendar($request);
            }
            if ($request->has('admin_sp_block_dates')) {
                return $this->adminSpBlockDates($request);
            }
            if ($request->has('admin_sp_unblock_dates')) {
                return $this->adminSpUnblockDates($request);
            }

            // ===== SETTINGS & PDF =====
            if ($request->has('get_settings')) {
                return $this->getSettings($request);
            }
            if ($request->has('save_settings')) {
                return $this->saveSettings($request);
            }
            if ($request->has('get_pdf_templates')) {
                return $this->getPdfTemplates($request);
            }
            if ($request->has('save_pdf_template')) {
                return $this->savePdfTemplate($request);
            }

            return response()->json(['error' => 'Unknown action'], 400);

        } catch (\Exception $e) {
            \Log::error('AjaxController error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Server error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Admin domain AJAX entry point - delegates to the same index() method.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        // Handle admin-specific login
        if ($request->has('adminlogin')) {
            return $this->adminLogin($request);
        }
        // All other actions go through the standard dispatcher
        return $this->index($request);
    }

    /**
     * Portal domain AJAX entry point - delegates to the same index() method.
     */
    public function portalIndex(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * Admin login - validates credentials and checks for HCT role.
     */
    protected function adminLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            if (!$user->isHct()) {
                Auth::logout();
                $request->session()->invalidate();
                return response()->json(['error' => 'Admin accounts only. Use the portal to log in.'], 403);
            }
            $request->session()->regenerate();
            return response()->json(['success' => true, 'redirect' => '/dashboard']);
        }

        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    // ===========================
    // AUTH & USER
    // ===========================

    protected function userLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "email" => "required|email",
            "password" => "required",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        // Capture guest trip data BEFORE login (session regenerate will lose it)
        $guestTrip = session('guest_trip');
        $guestChat = session('guest_chat');

        if (Auth::attempt(["email" => $request->email, "password" => $request->password], $request->boolean("remember"))) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Clear previous trip data so traveller starts fresh each login
            if ($user->isTraveller()) {
                $trips = $user->trips()->whereIn('status', ['draft', 'not_confirmed'])->get();
                foreach ($trips as $t) {
                    $t->aiConversations()->where('context_type', 'traveller_chat')->delete();
                    $t->tripDays()->each(function ($day) {
                        $day->experiences()->delete();
                        $day->services()->delete();
                        $day->delete();
                    });
                    $t->selectedExperiences()->delete();
                    $t->tripRegions()->delete();
                    $t->update([
                        'adults' => 1, 'children' => 0, 'infants' => 0,
                        'start_location' => null, 'end_location' => null,
                        'start_date' => null, 'end_date' => null,
                        'anchor_point' => null, 'pickup_preference' => null,
                        'accommodation_comfort' => null, 'vehicle_comfort' => null,
                        'guide_preference' => null, 'travel_pace' => null,
                        'budget_sensitivity' => null, 'ai_raw_response' => null,
                        'trip_name' => 'My Trip',
                    ]);
                }
            }

            // Sync guest trip directly into DB (don't rely on separate AJAX call)
            $syncedTripId = null;
            if ($user->isTraveller() && !empty($guestTrip['experience_ids'] ?? [])) {
                $syncedTripId = $this->syncGuestTripToDb($user, $guestTrip, $guestChat ?: []);
            }
            session()->forget(['guest_chat', 'guest_trip']);

            $redirect = match(true) {
                $user->isHct() => '//' . config('app.admin_domain') . '/dashboard',
                $user->isServiceProvider() => "/sp/dashboard",
                default => $syncedTripId ? "/home?trip_id={$syncedTripId}" : "/home",
            };
            return response()->json(["success" => true, "redirect" => $redirect, "trip_id" => $syncedTripId]);
        }

        return response()->json(["error" => "Invalid credentials"], 401);
    }

    protected function userSignup(Request $request): JsonResponse
    {
        // Accept either full_name or first_name + last_name
        $fullName = $request->full_name ?? trim(($request->first_name ?? '') . ' ' . ($request->last_name ?? ''));
        $request->merge(["full_name" => $fullName]);

        $validator = Validator::make($request->all(), [
            "full_name" => "required|string|max:255",
            "email" => "required|email|unique:users,email",
            "password" => "required|min:8|confirmed",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        // Capture guest trip data BEFORE creating user (Auth::login regenerates session)
        $guestTrip = session('guest_trip');
        $guestChat = session('guest_chat');

        $user = User::create([
            "full_name" => $fullName,
            "email" => $request->email,
            "password" => $request->password,
            "auth_type" => "email",
            "user_role" => "traveller",
        ]);

        Auth::login($user);

        // Sync guest trip directly into DB
        $syncedTripId = null;
        if (!empty($guestTrip['experience_ids'] ?? [])) {
            $syncedTripId = $this->syncGuestTripToDb($user, $guestTrip, $guestChat ?: []);
        }
        session()->forget(['guest_chat', 'guest_trip']);

        $redirect = $syncedTripId ? "/home?trip_id={$syncedTripId}" : "/home";
        return response()->json(["success" => true, "redirect" => $redirect, "trip_id" => $syncedTripId]);
    }

    protected function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            "full_name" => "nullable|string|max:255",
            "mobile" => "nullable|string|max:20",
            "address" => "nullable|string",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $user->update($request->only(["full_name", "mobile", "address"]));
        return response()->json(["success" => true, "message" => "Profile updated"]);
    }

    protected function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            "current_password" => "required",
            "new_password" => "required|min:8|confirmed",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(["error" => "Current password is incorrect"], 422);
        }

        $user->update(["password" => $request->new_password]);
        return response()->json(["success" => true, "message" => "Password changed"]);
    }

    // ===========================
    // TRAVELLER HOMEPAGE
    // ===========================

    protected function getRegionsForMap(Request $request): JsonResponse
    {
        $regions = Region::where("is_active", true)
            ->whereNotNull("latitude")
            ->whereNotNull("longitude")
            ->orderBy("sort_order")
            ->get(["id", "name", "slug", "description", "continent", "country", "latitude", "longitude", "image", "external_url"]);
        return response()->json(["regions" => $regions]);
    }

    protected function getExperiencesForDiscover(Request $request): JsonResponse
    {
        $query = Experience::where("is_active", true)->with(["region", "hlh", "days"]);

        if ($request->filled("continent")) {
            $query->whereHas("region", function ($q) use ($request) {
                $q->where("continent", $request->continent);
            });
        }
        if ($request->filled("country")) {
            $query->whereHas("region", function ($q) use ($request) {
                $q->where("country", $request->country);
            });
        }
        if ($request->filled("region_id")) {
            $query->where("region_id", $request->region_id);
        }
        if ($request->filled("type")) {
            $query->where("type", $request->type);
        }
        if ($request->filled("difficulty")) {
            $query->where("difficulty_level", $request->difficulty);
        }
        if ($request->filled("duration_type")) {
            $query->where("duration_type", $request->duration_type);
        }
        if ($request->filled("search")) {
            $search = trim($request->search);
            $words = preg_split('/\s+/', strtolower($search));
            $query->where(function($q) use ($search, $words) {
                // Full phrase match
                $q->where("name", "like", "%{$search}%")
                  ->orWhere("short_description", "like", "%{$search}%")
                  ->orWhere("type", "like", "%{$search}%");
                // Individual word matches (including stripped plural)
                foreach ($words as $word) {
                    $stem = rtrim($word, 's');
                    $q->orWhere("name", "like", "%{$word}%")
                      ->orWhere("short_description", "like", "%{$word}%")
                      ->orWhere("type", "like", "%{$word}%");
                    if ($stem !== $word && strlen($stem) >= 3) {
                        $q->orWhere("name", "like", "%{$stem}%")
                          ->orWhere("short_description", "like", "%{$stem}%")
                          ->orWhere("type", "like", "%{$stem}%");
                    }
                }
            });
        }
        if ($request->filled("month")) {
            $month = (int) $request->month;
            $query->where(function ($q) use ($month) {
                $q->whereJsonContains("available_months", $month)
                  ->orWhereNull("available_months");
            });
        }

        $experiences = $query->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->orderBy("sort_order")
            ->paginate(12);
        return response()->json($experiences);
    }

    protected function getExperienceDetail(Request $request): JsonResponse
    {
        $experience = Experience::with(["region", "hlh", "regenerativeProject", "days"])
            ->where("id", $request->experience_id)
            ->where("is_active", true)
            ->first();

        if (!$experience) {
            return response()->json(["error" => "Experience not found"], 404);
        }
        return response()->json(["experience" => $experience]);
    }

    protected function checkReviewEligibility(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['eligible' => false]);
        }

        $expId = $request->experience_id;
        $hasCompleted = Trip::where('user_id', Auth::id())
            ->where('status', 'completed')
            ->whereHas('tripDays.experiences', function ($q) use ($expId) {
                $q->where('experience_id', $expId);
            })
            ->exists();

        return response()->json([
            'eligible' => $hasCompleted,
        ]);
    }

    protected function getReviews(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'experience_id' => 'required|integer|exists:experiences,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $reviews = Review::where('experience_id', $request->experience_id)
            ->with('user:id,full_name,avatar')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'reviews' => $reviews->items(),
            'has_more' => $reviews->hasMorePages(),
            'next_page' => $reviews->hasMorePages() ? $reviews->currentPage() + 1 : null,
        ]);
    }

    protected function submitReview(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Login required to submit a review.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'experience_id' => 'required|integer|exists:experiences,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:100',
            'body' => 'required|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Only travellers who completed a trip with this experience can review
        $hasCompleted = Trip::where('user_id', Auth::id())
            ->where('status', 'completed')
            ->whereHas('tripDays.experiences', function ($q) use ($request) {
                $q->where('experience_id', $request->experience_id);
            })
            ->exists();
        if (!$hasCompleted) {
            return response()->json(['error' => 'You can only review experiences from your completed trips.'], 422);
        }

        $review = Review::create([
            'user_id' => Auth::id(),
            'experience_id' => $request->experience_id,
            'rating' => $request->rating,
            'title' => $request->title,
            'body' => $request->body,
        ]);

        $review->load('user:id,full_name,avatar');

        $avgRating = Review::where('experience_id', $request->experience_id)->avg('rating');
        $reviewCount = Review::where('experience_id', $request->experience_id)->count();

        return response()->json([
            'success' => true,
            'review' => $review,
            'avg_rating' => round($avgRating, 1),
            'review_count' => $reviewCount,
        ]);
    }

    protected function setLandingPreferences(Request $request): JsonResponse
    {
        $preferences = $request->only(["travel_style", "interests", "duration", "group_size", "budget_range"]);
        session(["landing_preferences" => $preferences]);
        return response()->json(["success" => true]);
    }

    protected function chatWithAi(Request $request): JsonResponse
    {
        set_time_limit(120);

        $validator = Validator::make($request->all(), [
            "message" => "required|string|max:2000",
            "trip_id" => "nullable",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $user = Auth::user();
        $isGuest = !$user;

        // Build conversation history
        if ($isGuest) {
            $guestChat = session("guest_chat", []);
            $guestChat[] = ["role" => "user", "content" => $request->message];
            if (count($guestChat) > 20) {
                $guestChat = array_slice($guestChat, -20);
            }
            $history = $guestChat;
            $gt = $this->guestTrip();
            // Build region anchor points from selected experiences
            $regionAnchors = [];
            if (!empty($gt['experience_ids'])) {
                $regionAnchors = Region::whereHas('experiences', fn($q) => $q->whereIn('id', $gt['experience_ids']))
                    ->whereNotNull('anchor_points')
                    ->get()
                    ->mapWithKeys(fn($r) => [$r->name => $r->anchor_points])
                    ->toArray();
            }
            $tripContext = json_encode([
                "trip_id" => "guest",
                "adults" => $gt["adults"] ?? 0,
                "children" => $gt["children"] ?? 0,
                "start_location" => $gt["start_location"] ?? null,
                "end_location" => $gt["end_location"] ?? null,
                "start_date" => $gt["start_date"] ?? null,
                "end_date" => $gt["end_date"] ?? null,
                "anchor_point" => $gt["anchor_point"] ?? null,
                "pickup_preference" => $gt["pickup_preference"] ?? null,
                "region_anchor_points" => $regionAnchors,
                "preferences" => session("landing_preferences", []),
            ]);
            $userName = $gt["traveller_name"] ?? "Traveller";
            $trip = null;
        } else {
            $trip = null;
            if ($request->filled("trip_id") && $request->trip_id !== "guest") {
                $trip = Trip::where("id", $request->trip_id)->where("user_id", $user->id)->first();
            }
            if (!$trip) {
                $trip = Trip::create([
                    "trip_id" => Trip::generateTripId(),
                    "user_id" => $user->id,
                    "status" => "not_confirmed",
                    "stage" => "open",
                ]);
                app(LeadService::class)->createOrGetLead($trip);
            }
            AiConversation::create([
                "trip_id" => $trip->id,
                "user_id" => $user->id,
                "role" => "user",
                "content" => $request->message,
                "context_type" => "traveller_chat",
            ]);
            $history = AiConversation::where("trip_id", $trip->id)
                ->where("context_type", "traveller_chat")
                ->orderBy("created_at")
                ->get()
                ->map(fn($m) => ["role" => $m->role, "content" => $m->content])
                ->toArray();
            // Build region anchor points from selected experiences
            $regionAnchors = [];
            $selectedExpIds = $trip->selectedExperiences()->pluck('experience_id')->toArray();
            if (!empty($selectedExpIds)) {
                $regionAnchors = Region::whereHas('experiences', fn($q) => $q->whereIn('id', $selectedExpIds))
                    ->whereNotNull('anchor_points')
                    ->get()
                    ->mapWithKeys(fn($r) => [$r->name => $r->anchor_points])
                    ->toArray();
            }
            $tripContext = json_encode([
                "trip_id" => $trip->id,
                "adults" => $trip->adults,
                "children" => $trip->children,
                "start_location" => $trip->start_location,
                "end_location" => $trip->end_location,
                "start_date" => $trip->start_date,
                "end_date" => $trip->end_date,
                "anchor_point" => $trip->anchor_point,
                "pickup_preference" => $trip->pickup_preference,
                "region_anchor_points" => $regionAnchors,
                "preferences" => session("landing_preferences", []),
            ]);
            $userName = $user->full_name ?? "Traveller";
        }

        // Experience catalog for AI context (compact — no short_description to reduce token usage)
        $experiences = Experience::where("is_active", true)
            ->select("id", "name", "type", "region_id", "duration_type", "duration_days", "difficulty_level", "base_cost_per_person", "available_months")
            ->with("region:id,name,continent,country")
            ->limit(50)
            ->get();

        // Build available destinations hierarchy for AI context
        $activeRegions = Region::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'continent', 'country']);
        $destinationHierarchy = [];
        foreach ($activeRegions as $r) {
            $continent = $r->continent ?: 'Other';
            $country = $r->country ?: 'Other';
            $destinationHierarchy[$continent][$country][] = ['id' => $r->id, 'name' => $r->name];
        }

        // Build compact region summary: types, difficulty levels, best months per region
        $regionSummary = [];
        foreach ($experiences as $exp) {
            $rId = $exp->region_id;
            if (!isset($regionSummary[$rId])) {
                $regionSummary[$rId] = [
                    'name' => $exp->region->name ?? 'Unknown',
                    'types' => [],
                    'difficulties' => [],
                    'best_months' => [],
                    'count' => 0,
                ];
            }
            $rs = &$regionSummary[$rId];
            $rs['count']++;
            if ($exp->type && !in_array($exp->type, $rs['types'])) $rs['types'][] = $exp->type;
            if ($exp->difficulty_level && !in_array($exp->difficulty_level, $rs['difficulties'])) $rs['difficulties'][] = $exp->difficulty_level;
            if (is_array($exp->available_months)) {
                foreach ($exp->available_months as $m) {
                    if (!in_array($m, $rs['best_months'])) $rs['best_months'][] = $m;
                }
            }
            unset($rs);
        }
        $regionSummaryJson = json_encode($regionSummary);

        $promptBuilder = app(PromptBuilderService::class);
        $promptData = $promptBuilder->build("traveller_chat", [
            "user_name" => $userName,
            "experiences_json" => $experiences->map(function ($e) {
                return [
                    'id' => $e->id, 'name' => $e->name, 'type' => $e->type,
                    'region' => $e->region->name ?? '', 'region_id' => $e->region_id,
                    'duration' => $e->duration_type === 'multi_day' ? ($e->duration_days ?? 1) . 'd' : '1d',
                    'difficulty' => $e->difficulty_level,
                    'price' => $e->base_cost_per_person,
                    'months' => $e->available_months,
                ];
            })->toJson(),
            "trip_context" => $tripContext,
        ]);

        $currentDateInstruction = "\n\nTODAY: " . now()->format('jS F Y') . ". Dates without a year = nearest future occurrence.";

        $formattingInstruction = "\n\nFORMATTING: Bold (**text**) all continent/country/region/experience names, dates, prices, durations. Every option in a list MUST be bolded.";

        $recommendIdInstruction = "\n\nRECOMMEND: When recommending experiences, append [RECOMMEND_IDS:1,5,12] at end (hidden from user).";

        $tripDetailsInstruction = "\n\nTRIP DETAILS: When traveller provides details, summarize & confirm first. After confirmation, append [TRIP_DETAILS:{\"key\":\"value\"}] (hidden). Keys: traveller_name (no confirm needed), start_location, end_location, start_date (YYYY-MM-DD), end_date, budget_notes, anchor_point, pickup_preference (private_taxi/local_transport), adults, children, infants (integers), accommodation_comfort (Cat A-E), vehicle_comfort, guide_preference.";

        $addToTripInstruction = "\n\nADD/REMOVE: Confirm before adding/removing. After confirmation: [ADD_TO_TRIP:1,5] or [REMOVE_FROM_TRIP:5] (hidden). If traveller confirms details + experiences together, include BOTH [TRIP_DETAILS] and [ADD_TO_TRIP] in same response.";

        $confirmationRule = "\n\nCONFIRMATION: Only confirm when CHANGING something (add/remove/update). Normal chat = respond naturally, no confirmation needed. Never use action tags without confirmation.";

        // Build current filter context from request
        $currentFilters = $request->get('current_filters') ? json_decode($request->get('current_filters'), true) : [];
        $filterContext = "";
        if (!empty($currentFilters)) {
            $filterParts = [];
            if (!empty($currentFilters['continent'])) $filterParts[] = "Continent: " . $currentFilters['continent'];
            if (!empty($currentFilters['country'])) $filterParts[] = "Country: " . $currentFilters['country'];
            if (!empty($currentFilters['region_name'])) $filterParts[] = "Region: " . $currentFilters['region_name'];
            if (!empty($currentFilters['experience_type'])) $filterParts[] = "Experience Type: " . $currentFilters['experience_type'];
            if (!empty($currentFilters['difficulty'])) $filterParts[] = "Difficulty: " . $currentFilters['difficulty'];
            if (!empty($currentFilters['month'])) $filterParts[] = "Month: " . $currentFilters['month'];
            if (!empty($filterParts)) {
                $filterContext = "\n\nCURRENT FILTER SELECTIONS (set by the traveller on the page):\n" . implode("\n", $filterParts) . "\nThe traveller has already selected these filters manually. Acknowledge what they've chosen and do NOT re-ask about details that are already selected. Only ask about MISSING information.";
            }
        }

        $conversationFlowInstruction = "\n\nCONVERSATION FLOW (ask step by step, show options as bold lists):\n1. Name (guests only)\n2. Destination: Continent → Country → Region. Show available options at each step. For regions, mention types & best months from REGION SUMMARY.\n3. Experience type & difficulty preference (show what's available in the region).\n4. Travel date (mention best months), group size, starting city — ask 2 at a time max.\n5. Recommend matching experiences with name, duration, difficulty, price.\n\nIf filters already selected (see CURRENT FILTERS), skip those steps. Only ask MISSING details. Single region per trip — don't suggest other regions.\n\nDESTINATIONS:" . json_encode($destinationHierarchy) . "\nREGION SUMMARY:" . $regionSummaryJson . "\n\nSET_FILTERS: When traveller picks continent/country/region, append: [SET_FILTERS:{\"continent\":\"X\",\"country\":\"Y\",\"region_id\":N}] — include only chosen keys. Hidden from user." . $filterContext;

        $allInstructions = $currentDateInstruction . $formattingInstruction . $recommendIdInstruction . $tripDetailsInstruction . $addToTripInstruction . $confirmationRule . $conversationFlowInstruction;

        $messages = [];
        if ($promptData) {
            $messages[] = ["role" => "system", "content" => $promptData["system_prompt"] . $allInstructions];
        } else {
            $messages[] = ["role" => "system", "content" => "You are a helpful travel assistant for HECO (Himalayan Ecotourism Collective). Help travellers plan regenerative trips. Suggest experiences, help with itinerary planning, and answer questions about destinations. Be warm, knowledgeable, and encourage sustainable travel.\n\nYou have access to the following experience catalog:\n" . $experiences->toJson() . $allInstructions];
        }

        $messages = array_merge($messages, $history);

        $aiResponse = $this->callAi($messages, [
            "temperature" => $promptData["temperature"] ?? 0.7,
            "max_tokens" => $promptData["max_tokens"] ?? 4096,
        ]);

        if (!$aiResponse || empty($aiResponse["content"])) {
            \Log::warning('AI chat: all providers failed', ['is_guest' => $isGuest, 'trip_id' => $trip?->id]);
        }

        $responseText = $aiResponse["content"] ?? "I apologize, I am having trouble connecting right now. Please try again or contact our team for assistance.";

        // Parse SET_FILTERS tag
        $setFilters = null;
        if (preg_match('/\[SET_FILTERS:(\{[^]]+\})\]/', $responseText, $filterMatch)) {
            $setFilters = json_decode($filterMatch[1], true) ?: null;
            $responseText = trim(preg_replace('/\s*\[SET_FILTERS:\{[^]]+\}\]/', '', $responseText));
        }

        // Parse recommended experience IDs
        $recommendedIds = [];
        if (preg_match('/\[RECOMMEND_IDS:([\d,]+)\]/', $responseText, $matches)) {
            $recommendedIds = array_map("intval", explode(",", $matches[1]));
            $responseText = trim(preg_replace('/\s*\[RECOMMEND_IDS:[\d,]+\]/', '', $responseText));
        }

        // Parse trip details from AI response
        if (preg_match('/\[TRIP_DETAILS:(\{[^]]+\})\]/', $responseText, $tdMatch)) {
            $extractedDetails = json_decode($tdMatch[1], true) ?: [];
            $responseText = trim(preg_replace('/\s*\[TRIP_DETAILS:\{[^]]+\}\]/', '', $responseText));

            // Handle traveller name separately
            $travellerName = $extractedDetails['traveller_name'] ?? null;
            unset($extractedDetails['traveller_name']);

            $allowedKeys = ['start_location', 'end_location', 'start_date', 'end_date', 'budget_notes', 'anchor_point', 'pickup_preference', 'adults', 'children', 'infants', 'accommodation_comfort', 'vehicle_comfort', 'guide_preference'];
            $extractedDetails = array_intersect_key($extractedDetails, array_flip($allowedKeys));

            if ($isGuest) {
                $guestData = $this->guestTrip();
                foreach ($extractedDetails as $k => $v) {
                    $guestData[$k] = $v;
                }
                if ($travellerName) {
                    $guestData['traveller_name'] = $travellerName;
                }
                $this->saveGuestTrip($guestData);
            } elseif ($trip) {
                if (!empty($extractedDetails)) {
                    $trip->update($extractedDetails);
                }
            }
            $detailsUpdated = true;
        } else {
            $detailsUpdated = false;
        }

        // Determine the current trip region (single-region constraint)
        $currentTripRegionId = null;
        if ($isGuest) {
            $guestData = $this->guestTrip();
            $guestIds = $guestData['experience_ids'] ?? [];
            if (!empty($guestIds)) {
                $currentTripRegionId = Experience::whereIn('id', $guestIds)->whereNotNull('region_id')->value('region_id');
            }
        } elseif ($trip) {
            $currentTripRegionId = TripSelectedExperience::where('trip_id', $trip->id)
                ->join('experiences', 'experiences.id', '=', 'trip_selected_experiences.experience_id')
                ->whereNotNull('experiences.region_id')
                ->value('experiences.region_id');
        }

        // Parse ADD_TO_TRIP tag (flexible regex: handles spaces, trailing commas)
        $addedExperienceIds = [];
        if (preg_match('/\[ADD_TO_TRIP:\s*([\d,\s]+?)\s*\]/', $responseText, $addMatch)) {
            $requestedIds = array_map('intval', array_filter(preg_split('/[\s,]+/', $addMatch[1]), fn($v) => $v !== ''));
            $responseText = trim(preg_replace('/\s*\[ADD_TO_TRIP:\s*[\d,\s]+?\s*\]/', '', $responseText));

            // Validate experience IDs exist
            $validIds = Experience::where('is_active', true)->whereIn('id', $requestedIds)->pluck('id')->toArray();

            // Filter by single-region constraint
            $validIds = array_filter($validIds, function ($id) use (&$currentTripRegionId) {
                $exp = Experience::find($id);
                if (!$exp || !$exp->region_id) return true;
                if (!$currentTripRegionId) { $currentTripRegionId = $exp->region_id; return true; }
                return $exp->region_id == $currentTripRegionId;
            });

            if ($isGuest) {
                $guestData = $this->guestTrip();
                $existing = $guestData['experience_ids'] ?? [];
                foreach ($validIds as $expId) {
                    if (!in_array($expId, $existing)) {
                        $existing[] = $expId;
                        $addedExperienceIds[] = $expId;
                    }
                }
                $guestData['experience_ids'] = $existing;
                $this->saveGuestTrip($guestData);
            } elseif ($trip) {
                foreach ($validIds as $expId) {
                    $alreadyAdded = TripSelectedExperience::where('trip_id', $trip->id)
                        ->where('experience_id', $expId)->exists();
                    if (!$alreadyAdded) {
                        $maxSort = TripSelectedExperience::where('trip_id', $trip->id)->max('sort_order') ?? 0;
                        TripSelectedExperience::create([
                            'trip_id' => $trip->id,
                            'experience_id' => $expId,
                            'sort_order' => $maxSort + 1,
                        ]);
                        $addedExperienceIds[] = $expId;
                    }
                }
            }
        }

        // Fallback: if AI text says it added/adding an experience but no [ADD_TO_TRIP] tag was found,
        // try to match experience names from the catalog and add them automatically
        if (empty($addedExperienceIds) && preg_match('/(?:added|adding|I\'ve added|I have added|added .* to your trip|adding .* to your)/i', $responseText)) {
            $fallbackIds = [];
            foreach ($experiences as $exp) {
                // Check if the experience name appears in the AI response
                if (stripos($responseText, $exp->name) !== false) {
                    // Single-region constraint
                    if ($exp->region_id && $currentTripRegionId && $exp->region_id != $currentTripRegionId) continue;
                    $fallbackIds[] = $exp->id;
                }
            }
            if (!empty($fallbackIds)) {
                \Log::info('AI ADD_TO_TRIP fallback triggered', ['experience_ids' => $fallbackIds, 'trip_id' => $trip?->id]);
                if ($isGuest) {
                    $guestData = $this->guestTrip();
                    $existing = $guestData['experience_ids'] ?? [];
                    foreach ($fallbackIds as $expId) {
                        if (!in_array($expId, $existing)) {
                            $existing[] = $expId;
                            $addedExperienceIds[] = $expId;
                            if (!$currentTripRegionId) {
                                $currentTripRegionId = Experience::find($expId)?->region_id;
                            }
                        }
                    }
                    $guestData['experience_ids'] = $existing;
                    $this->saveGuestTrip($guestData);
                } elseif ($trip) {
                    foreach ($fallbackIds as $expId) {
                        $alreadyAdded = TripSelectedExperience::where('trip_id', $trip->id)
                            ->where('experience_id', $expId)->exists();
                        if (!$alreadyAdded) {
                            $maxSort = TripSelectedExperience::where('trip_id', $trip->id)->max('sort_order') ?? 0;
                            TripSelectedExperience::create([
                                'trip_id' => $trip->id,
                                'experience_id' => $expId,
                                'sort_order' => $maxSort + 1,
                            ]);
                            $addedExperienceIds[] = $expId;
                            if (!$currentTripRegionId) {
                                $currentTripRegionId = Experience::find($expId)?->region_id;
                            }
                        }
                    }
                }
            }
        }

        // Parse REMOVE_FROM_TRIP tag
        $removedExperienceIds = [];
        if (preg_match('/\[REMOVE_FROM_TRIP:\s*([\d,\s]+?)\s*\]/', $responseText, $removeMatch)) {
            $removeIds = array_map('intval', array_filter(preg_split('/[\s,]+/', $removeMatch[1]), fn($v) => $v !== ''));
            $responseText = trim(preg_replace('/\s*\[REMOVE_FROM_TRIP:\s*[\d,\s]+?\s*\]/', '', $responseText));

            if ($isGuest) {
                $guestData = $this->guestTrip();
                $existing = $guestData['experience_ids'] ?? [];
                $guestData['experience_ids'] = array_values(array_filter($existing, function ($id) use ($removeIds) {
                    return !in_array($id, $removeIds);
                }));
                $guestData['ai_itinerary'] = null;
                $guestData['ai_raw_response'] = null;
                $this->saveGuestTrip($guestData);
                $removedExperienceIds = $removeIds;
            } elseif ($trip) {
                foreach ($removeIds as $expId) {
                    TripSelectedExperience::where('trip_id', $trip->id)
                        ->where('experience_id', $expId)
                        ->delete();

                    TripDayExperience::where('experience_id', $expId)
                        ->whereHas('tripDay', function ($q) use ($trip) {
                            $q->where('trip_id', $trip->id);
                        })
                        ->delete();

                    $removedExperienceIds[] = $expId;
                }
                // Remove empty days (no experiences left)
                $trip->tripDays()->whereDoesntHave('experiences')->delete();
            }
        }

        $tripUpdated = !empty($addedExperienceIds) || !empty($removedExperienceIds) || $detailsUpdated;
        $updatedDetails = $detailsUpdated ? ($extractedDetails ?? []) : [];

        // Save assistant response
        if ($isGuest) {
            $guestChat[] = ["role" => "assistant", "content" => $responseText];
            if (count($guestChat) > 20) {
                $guestChat = array_slice($guestChat, -20);
            }
            session(["guest_chat" => $guestChat]);

            return response()->json([
                "success" => true,
                "response" => $responseText,
                "trip_id" => "guest",
                "recommended_experience_ids" => $recommendedIds,
                "added_experience_ids" => $addedExperienceIds,
                "removed_experience_ids" => $removedExperienceIds,
                "trip_updated" => $tripUpdated,
                "updated_details" => $updatedDetails,
                "set_filters" => $setFilters,
            ]);
        }

        AiConversation::create([
            "trip_id" => $trip->id,
            "user_id" => $user->id,
            "role" => "assistant",
            "content" => $responseText,
            "context_type" => "traveller_chat",
        ]);

        return response()->json([
            "success" => true,
            "response" => $responseText,
            "trip_id" => $trip->id,
            "recommended_experience_ids" => $recommendedIds,
            "added_experience_ids" => $addedExperienceIds,
            "removed_experience_ids" => $removedExperienceIds,
            "trip_updated" => $tripUpdated,
            "updated_details" => $updatedDetails,
            "set_filters" => $setFilters,
        ]);
    }

    protected function createTrip(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            $this->saveGuestTrip($this->guestTrip());
            return response()->json(["success" => true, "trip_id" => "guest"]);
        }
        $trip = $this->ensureAuthTrip($request);
        return response()->json(["success" => true, "trip_id" => $trip->id]);
    }

    protected function getTripSelectedExperiences(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $ids = $gt['experience_ids'] ?? [];
            if (empty($ids)) return response()->json(["experiences" => []]);

            $exps = Experience::whereIn('id', $ids)->with('region')->get()
                ->sortBy(function ($exp) use ($ids) {
                    return array_search($exp->id, $ids);
                })->values();
            $items = $exps->map(function ($exp) {
                return [
                    'experience_id' => $exp->id,
                    'experience' => $exp,
                ];
            })->values();
            return response()->json(["experiences" => $items]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["experiences" => []]);

        $experiences = TripSelectedExperience::where("trip_id", $trip->id)
            ->with("experience.region")
            ->orderBy("sort_order")
            ->get();

        return response()->json(["experiences" => $experiences]);
    }

    protected function getTripTimeline(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $days = $this->buildGuestTimeline($gt);
            $startDate = $gt['start_date'] ?? null;
            return response()->json(["days" => $days, "start_date" => $startDate]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["days" => []]);

        $days = $trip->tripDays()->with(["experiences.experience.days", "services"])->get();
        return response()->json(["days" => $days, "start_date" => $trip->start_date?->toDateString()]);
    }

    protected function getChatHistory(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(["messages" => session("guest_chat", [])]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["messages" => []]);

        $messages = $trip->aiConversations()
            ->where("context_type", "traveller_chat")
            ->orderBy("created_at")
            ->get()
            ->map(function ($msg) {
                return ["role" => $msg->role, "content" => $msg->content];
            });

        return response()->json(["messages" => $messages]);
    }

    protected function addExperienceToTrip(Request $request): JsonResponse
    {
        $experience = Experience::findOrFail($request->experience_id);

        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $ids = $gt['experience_ids'] ?? [];

            // Single-region constraint for guests
            if (!empty($ids) && $experience->region_id) {
                $existingRegionId = Experience::whereIn('id', $ids)->whereNotNull('region_id')->value('region_id');
                if ($existingRegionId && $existingRegionId != $experience->region_id) {
                    return response()->json(["error" => "You can only add experiences from one region at a time."], 422);
                }
            }

            if (!in_array($experience->id, $ids)) {
                $ids[] = $experience->id;
            }
            $gt['experience_ids'] = $ids;
            $this->saveGuestTrip($gt);
            return response()->json(["success" => true, "trip_id" => "guest", "message" => "Experience added to trip"]);
        }

        $trip = $this->ensureAuthTrip($request);

        // Single-region constraint: only allow experiences from one region per trip
        if ($experience->region_id) {
            $existingRegionId = TripSelectedExperience::where('trip_id', $trip->id)
                ->join('experiences', 'experiences.id', '=', 'trip_selected_experiences.experience_id')
                ->whereNotNull('experiences.region_id')
                ->value('experiences.region_id');
            if ($existingRegionId && $existingRegionId != $experience->region_id) {
                return response()->json(["error" => "You can only add experiences from one region at a time."], 422);
            }
        }

        $maxSort = TripSelectedExperience::where('trip_id', $trip->id)->max('sort_order') ?? 0;
        TripSelectedExperience::firstOrCreate([
            "trip_id" => $trip->id,
            "experience_id" => $experience->id,
        ], [
            "sort_order" => $maxSort + 1,
        ]);

        if ($experience->region_id) {
            TripRegion::firstOrCreate([
                "trip_id" => $trip->id,
                "region_id" => $experience->region_id,
            ]);
        }

        return response()->json(["success" => true, "trip_id" => $trip->id, "message" => "Experience added to trip"]);
    }

    protected function removeExperienceFromTrip(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $gt['experience_ids'] = array_values(array_filter($gt['experience_ids'] ?? [], function ($id) use ($request) {
                return $id != $request->experience_id;
            }));
            // Clear itinerary since experiences changed
            $gt['ai_itinerary'] = null;
            $gt['ai_raw_response'] = null;
            // Reset group size if no experiences left
            if (empty($gt['experience_ids'])) {
                $gt['adults'] = 0;
                $gt['children'] = 0;
                $gt['infants'] = 0;
            }
            $this->saveGuestTrip($gt);
            return response()->json(["success" => true, "trip_id" => "guest"]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["error" => "Trip not found"], 404);

        TripSelectedExperience::where("trip_id", $trip->id)
            ->where("experience_id", $request->experience_id)
            ->delete();

        // Also remove from timeline days
        TripDayExperience::where("experience_id", $request->experience_id)
            ->whereHas("tripDay", function ($q) use ($trip) {
                $q->where("trip_id", $trip->id);
            })
            ->delete();

        // Remove empty days (no experiences left)
        $trip->tripDays()->whereDoesntHave('experiences')->delete();

        // Reset group size if no experiences left
        if ($trip->selectedExperiences()->count() === 0) {
            $trip->update(['adults' => 0, 'children' => 0, 'infants' => 0]);
        }

        return response()->json(["success" => true, "trip_id" => $trip->id]);
    }

    protected function preferExperience(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(["error" => "Login required"], 401);
        }

        // If no trip_id provided (e.g. from wishlist page), toggle across all user's trips
        if (!$request->filled('trip_id') || $request->trip_id === 'guest') {
            $userTripIds = Trip::where('user_id', Auth::id())->pluck('id');
            $records = TripSelectedExperience::whereIn('trip_id', $userTripIds)
                ->where('experience_id', $request->experience_id)
                ->where('is_preferred', true)
                ->get();

            if ($records->isNotEmpty()) {
                // Remove from wishlist across all trips
                foreach ($records as $rec) {
                    $rec->update(['is_preferred' => false]);
                }
                return response()->json(["success" => true, "is_preferred" => false]);
            }
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["error" => "Trip not found"], 404);

        $maxSort = TripSelectedExperience::where('trip_id', $trip->id)->max('sort_order') ?? 0;
        $sel = TripSelectedExperience::firstOrCreate([
            "trip_id" => $trip->id,
            "experience_id" => $request->experience_id,
        ], [
            "sort_order" => $maxSort + 1,
        ]);
        $sel->update(["is_preferred" => !$sel->is_preferred]);

        return response()->json(["success" => true, "is_preferred" => $sel->is_preferred]);
    }

    protected function getWishlist(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(["error" => "Login required"], 401);
        }

        $experienceIds = TripSelectedExperience::where('is_preferred', true)
            ->whereHas('trip', function ($q) {
                $q->where('user_id', Auth::id());
            })
            ->pluck('experience_id')
            ->unique()
            ->values();

        $experiences = Experience::whereIn('id', $experienceIds)
            ->with('region')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->get();

        return response()->json(["success" => true, "data" => $experiences]);
    }

    protected function reorderExperiences(Request $request): JsonResponse
    {
        $order = $request->order;
        if (!is_array($order) || empty($order)) {
            return response()->json(["error" => "No order provided"], 422);
        }

        $order = array_map('intval', $order);

        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $gt['experience_ids'] = $order;
            $gt['ai_itinerary'] = null;
            $gt['ai_raw_response'] = null;
            $this->saveGuestTrip($gt);
            return response()->json(["success" => true]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["error" => "Trip not found"], 404);

        foreach ($order as $index => $expId) {
            TripSelectedExperience::where('trip_id', $trip->id)
                ->where('experience_id', $expId)
                ->update(['sort_order' => $index]);
        }

        return response()->json(["success" => true]);
    }

    protected function updateGroupDetails(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $gt['adults'] = (int) ($request->adults ?? 0);
            $gt['children'] = (int) ($request->children ?? 0);
            $gt['infants'] = (int) ($request->infants ?? 0);
            $this->saveGuestTrip($gt);
            return response()->json(["success" => true]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["error" => "Trip not found"], 404);

        $trip->update($request->only(["adults", "children", "infants", "traveller_origin"]));
        return response()->json(["success" => true]);
    }

    protected function updateTripStartDate(Request $request): JsonResponse
    {
        $date = $request->start_date ?: null;

        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $gt['start_date'] = $date;
            $this->saveGuestTrip($gt);
            return response()->json(["success" => true]);
        }

        $trip = $this->ensureAuthTrip($request);
        $trip->update(["start_date" => $date]);

        // Update existing trip day dates
        foreach ($trip->tripDays()->orderBy('day_number')->get() as $day) {
            $day->update([
                "date" => $date ? \Carbon\Carbon::parse($date)->addDays($day->day_number - 1) : null,
            ]);
        }

        return response()->json(["success" => true]);
    }

    protected function updateTravelPreferences(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            $gt = $this->guestTrip();
            foreach (['accommodation_comfort', 'vehicle_comfort', 'guide_preference', 'travel_pace', 'budget_sensitivity'] as $key) {
                if ($request->has($key)) $gt[$key] = $request->$key;
            }
            $this->saveGuestTrip($gt);
            return response()->json(["success" => true]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["error" => "Trip not found"], 404);

        $trip->update($request->only([
            "accommodation_comfort", "vehicle_comfort", "guide_preference",
            "travel_pace", "budget_sensitivity",
        ]));
        return response()->json(["success" => true]);
    }

    protected function saveTripName(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $gt['trip_name'] = $request->trip_name ?? 'My Trip';
            $this->saveGuestTrip($gt);
            return response()->json(["success" => true]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["error" => "Trip not found"], 404);

        $trip->update(["trip_name" => $request->trip_name]);
        return response()->json(["success" => true]);
    }

    protected function addDayToTrip(Request $request): JsonResponse
    {
        $afterDayNumber = $request->input('after_day_number');
        $dayNote = $request->input('day_note');

        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $itinerary = $gt['ai_itinerary'] ?? ['days' => []];
            $dayCount = count($itinerary['days']);

            if ($afterDayNumber !== null && $afterDayNumber !== '') {
                // Insert after the specified day and renumber
                $insertAt = (int) $afterDayNumber; // 0-indexed insert position
                array_splice($itinerary['days'], $insertAt, 0, [[
                    'title' => 'Day ' . ($insertAt + 1),
                    'description' => $dayNote,
                    'experiences' => [],
                ]]);
                // Renumber all days
                foreach ($itinerary['days'] as $i => &$d) {
                    $d['title'] = 'Day ' . ($i + 1);
                }
                unset($d);
            } else {
                $itinerary['days'][] = [
                    'title' => 'Day ' . ($dayCount + 1),
                    'description' => $dayNote,
                    'experiences' => [],
                ];
            }

            $gt['ai_itinerary'] = $itinerary;
            $this->saveGuestTrip($gt);
            $newDayNum = ($afterDayNumber !== null && $afterDayNumber !== '') ? (int) $afterDayNumber + 1 : $dayCount + 1;
            return response()->json(["success" => true, "day" => ['id' => $newDayNum, 'day_number' => $newDayNum]]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["error" => "Trip not found"], 404);

        if ($afterDayNumber !== null && $afterDayNumber !== '') {
            $afterDayNumber = (int) $afterDayNumber;
            // Shift all days after the insertion point up by 1
            $trip->tripDays()
                ->where('day_number', '>', $afterDayNumber)
                ->orderByDesc('day_number')
                ->each(function ($d) {
                    $d->update(['day_number' => $d->day_number + 1, 'sort_order' => $d->day_number]);
                });

            $day = TripDay::create([
                "trip_id" => $trip->id,
                "day_number" => $afterDayNumber + 1,
                "sort_order" => $afterDayNumber,
                "description" => $dayNote,
            ]);
        } else {
            $maxDay = $trip->tripDays()->max("day_number") ?? 0;
            $day = TripDay::create([
                "trip_id" => $trip->id,
                "day_number" => $maxDay + 1,
                "sort_order" => $maxDay,
            ]);
        }

        return response()->json(["success" => true, "day" => $day]);
    }

    protected function removeDayFromTrip(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $itinerary = $gt['ai_itinerary'] ?? ['days' => []];
            $dayId = (int) $request->day_id;
            if (isset($itinerary['days'][$dayId - 1])) {
                array_splice($itinerary['days'], $dayId - 1, 1);
            }
            $gt['ai_itinerary'] = $itinerary;
            $this->saveGuestTrip($gt);
            return response()->json(["success" => true]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["error" => "Trip not found"], 404);

        TripDay::where("id", $request->day_id)->where("trip_id", $trip->id)->delete();

        $days = $trip->tripDays()->orderBy("sort_order")->get();
        foreach ($days as $i => $day) {
            $day->update(["day_number" => $i + 1, "sort_order" => $i]);
        }

        return response()->json(["success" => true]);
    }

    protected function getTripPricing(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $pricing = $this->computeGuestPricing($gt);
            return response()->json(["success" => true, "pricing" => $pricing]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["success" => true, "pricing" => []]);

        $calculator = app(CostCalculatorService::class);
        $pricing = $calculator->calculate($trip);
        return response()->json(["success" => true, "pricing" => $pricing]);
    }

    protected function getTripImpact(Request $request): JsonResponse
    {
        if (!Auth::check() || $request->trip_id === 'guest') {
            return response()->json(["success" => true, "impacts" => []]);
        }
        $trip = Trip::findOrFail($request->trip_id);
        $impact = app(ImpactCalculatorService::class)->calculateForTrip($trip);
        return response()->json(["success" => true, "impact" => $impact]);
    }

    protected function requestSupport(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(["error" => "Please log in to request support."], 401);
        }

        $validator = Validator::make($request->all(), [
            "message" => "required|string",
            "trip_id" => "nullable|integer",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $hasPayment = false;
        if ($request->filled("trip_id") && $request->trip_id !== 'guest') {
            $hasPayment = TravellerPayment::where("trip_id", $request->trip_id)->exists();
        }

        SupportRequest::create([
            "user_id" => $user->id,
            "trip_id" => ($request->trip_id !== 'guest') ? $request->trip_id : null,
            "message" => $request->message,
            "traveller_status" => $hasPayment ? "client" : "lead",
        ]);

        return response()->json(["success" => true, "message" => "Support request submitted"]);
    }

    protected function getUserTrips(Request $request): JsonResponse
    {
        $user = Auth::user();
        $trips = Trip::where("user_id", $user->id)
            ->with(["tripRegions.region", "selectedExperiences.experience"])
            ->orderBy("updated_at", "desc")
            ->get();
        return response()->json(["trips" => $trips]);
    }

    protected function eraseTrip(Request $request): JsonResponse
    {
        $user = Auth::user();
        $trip = Trip::where("id", $request->trip_id)->where("user_id", $user->id)->first();
        if (!$trip) {
            return response()->json(["error" => "Trip not found"], 404);
        }
        $trip->update(["status" => "cancelled", "stage" => "closed"]);
        return response()->json(["success" => true]);
    }

    protected function syncGuestJourney(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(["error" => "Login required"], 401);
        }

        $gt = session('guest_trip');
        if (!$gt || empty($gt['experience_ids'] ?? [])) {
            session()->forget('guest_trip');
            return response()->json(["success" => true]);
        }

        // Guest trip details to transfer
        $guestDetails = [
            "trip_name" => $gt['trip_name'] ?? "My Trip",
            "adults" => $gt['adults'] ?? 1,
            "children" => $gt['children'] ?? 0,
            "infants" => $gt['infants'] ?? 0,
            "start_location" => $gt['start_location'] ?? null,
            "end_location" => $gt['end_location'] ?? null,
            "start_date" => $gt['start_date'] ?? null,
            "end_date" => $gt['end_date'] ?? null,
            "anchor_point" => $gt['anchor_point'] ?? null,
            "pickup_preference" => $gt['pickup_preference'] ?? null,
            "accommodation_comfort" => $gt['accommodation_comfort'] ?? null,
            "vehicle_comfort" => $gt['vehicle_comfort'] ?? null,
            "guide_preference" => $gt['guide_preference'] ?? null,
            "travel_pace" => $gt['travel_pace'] ?? null,
            "budget_sensitivity" => $gt['budget_sensitivity'] ?? null,
        ];

        // Find or create a trip for the logged-in user
        $trip = Trip::where("user_id", $user->id)
            ->whereIn("status", ["draft", "not_confirmed"])
            ->orderBy("updated_at", "desc")
            ->first();

        if (!$trip) {
            $trip = Trip::create(array_merge($guestDetails, [
                "trip_id" => Trip::generateTripId(),
                "user_id" => $user->id,
                "status" => "draft",
                "stage" => "traveller_exploring",
            ]));
        } else {
            // Update existing trip with guest details
            $trip->update($guestDetails);
        }

        // Transfer selected experiences (preserving guest order)
        foreach ($gt['experience_ids'] as $index => $expId) {
            $experience = Experience::find($expId);
            if (!$experience) continue;

            TripSelectedExperience::firstOrCreate([
                "trip_id" => $trip->id,
                "experience_id" => $experience->id,
            ], [
                "sort_order" => $index,
            ]);

            if ($experience->region_id) {
                TripRegion::firstOrCreate([
                    "trip_id" => $trip->id,
                    "region_id" => $experience->region_id,
                ]);
            }
        }

        // Transfer AI itinerary if exists
        $aiItinerary = $gt['ai_itinerary'] ?? null;
        if ($aiItinerary && isset($aiItinerary['days'])) {
            $trip->update(["ai_raw_response" => $gt['ai_raw_response'] ?? null]);

            // Clear existing days
            $trip->tripDays()->each(function ($day) {
                $day->experiences()->delete();
                $day->services()->delete();
                $day->delete();
            });

            // Persist AI itinerary to DB
            $itineraryService = app(ItineraryService::class);
            $itineraryService->parseAndCreateFromAi($trip, $aiItinerary);

            // Calculate pricing
            $costCalculator = app(CostCalculatorService::class);
            $costCalculator->calculate($trip);
        }

        // Transfer guest chat history to DB
        $guestChat = session('guest_chat', []);
        if (!empty($guestChat)) {
            foreach ($guestChat as $msg) {
                AiConversation::create([
                    "trip_id" => $trip->id,
                    "user_id" => $user->id,
                    "role" => $msg['role'] ?? 'user',
                    "content" => $msg['content'] ?? '',
                    "context_type" => "traveller_chat",
                ]);
            }
        }

        app(LeadService::class)->createOrGetLead($trip);

        // Clear guest session
        session()->forget(['guest_trip', 'guest_chat']);

        return response()->json(["success" => true, "trip_id" => $trip->id]);
    }

    /**
     * Sync guest trip data directly into DB for a user (called from login/signup).
     * Returns the trip ID or null.
     */
    protected function syncGuestTripToDb($user, array $gt, array $chatHistory = []): ?int
    {
        if (empty($gt['experience_ids'] ?? [])) return null;

        $guestDetails = [
            "trip_name" => $gt['trip_name'] ?? "My Trip",
            "adults" => $gt['adults'] ?? 1,
            "children" => $gt['children'] ?? 0,
            "infants" => $gt['infants'] ?? 0,
            "start_location" => $gt['start_location'] ?? null,
            "end_location" => $gt['end_location'] ?? null,
            "start_date" => $gt['start_date'] ?? null,
            "end_date" => $gt['end_date'] ?? null,
            "anchor_point" => $gt['anchor_point'] ?? null,
            "pickup_preference" => $gt['pickup_preference'] ?? null,
            "accommodation_comfort" => $gt['accommodation_comfort'] ?? null,
            "vehicle_comfort" => $gt['vehicle_comfort'] ?? null,
            "guide_preference" => $gt['guide_preference'] ?? null,
            "travel_pace" => $gt['travel_pace'] ?? null,
            "budget_sensitivity" => $gt['budget_sensitivity'] ?? null,
        ];

        $trip = Trip::where("user_id", $user->id)
            ->whereIn("status", ["draft", "not_confirmed"])
            ->orderBy("updated_at", "desc")
            ->first();

        if (!$trip) {
            $trip = Trip::create(array_merge($guestDetails, [
                "trip_id" => Trip::generateTripId(),
                "user_id" => $user->id,
                "status" => "draft",
                "stage" => "traveller_exploring",
            ]));
        } else {
            $trip->update($guestDetails);
        }

        // Transfer selected experiences
        foreach ($gt['experience_ids'] as $index => $expId) {
            $experience = Experience::find($expId);
            if (!$experience) continue;
            TripSelectedExperience::firstOrCreate([
                "trip_id" => $trip->id,
                "experience_id" => $experience->id,
            ], ["sort_order" => $index]);
            if ($experience->region_id) {
                TripRegion::firstOrCreate([
                    "trip_id" => $trip->id,
                    "region_id" => $experience->region_id,
                ]);
            }
        }

        // Transfer AI itinerary if exists
        $aiItinerary = $gt['ai_itinerary'] ?? null;
        if ($aiItinerary && isset($aiItinerary['days'])) {
            $trip->update(["ai_raw_response" => $gt['ai_raw_response'] ?? null]);
            $trip->tripDays()->each(function ($day) {
                $day->experiences()->delete();
                $day->services()->delete();
                $day->delete();
            });
            app(ItineraryService::class)->parseAndCreateFromAi($trip, $aiItinerary);
            app(CostCalculatorService::class)->calculate($trip);
        }

        // Transfer chat history
        if (!empty($chatHistory)) {
            foreach ($chatHistory as $msg) {
                AiConversation::create([
                    "trip_id" => $trip->id,
                    "user_id" => $user->id,
                    "role" => $msg['role'] ?? 'user',
                    "content" => $msg['content'] ?? '',
                    "context_type" => "traveller_chat",
                ]);
            }
        }

        app(LeadService::class)->createOrGetLead($trip);

        return $trip->id;
    }

    protected function generateItinerary(Request $request): JsonResponse
    {
        set_time_limit(120);

        $isGuest = !Auth::check();

        // Build experience list from session or DB
        if ($isGuest) {
            $gt = $this->guestTrip();
            $expIds = $gt['experience_ids'] ?? [];
            if (empty($expIds)) {
                return response()->json(["error" => "Add experiences to your trip first"], 422);
            }
            $expModels = Experience::whereIn('id', $expIds)->with('region')->get()
                ->sortBy(function ($exp) use ($expIds) {
                    return array_search($exp->id, $expIds);
                })->values();
            $adults = $gt['adults'] ?: 1;
            $preferences = ($gt['accommodation_comfort'] ?: 'standard') . " comfort, " . ($gt['travel_pace'] ?: 'moderate') . " pace";
        } else {
            $trip = $this->resolveTrip($request);
            if (!$trip) {
                return response()->json(["error" => "No trip found. Add experiences first."], 422);
            }
            $trip->load(["selectedExperiences" => function ($q) { $q->orderBy('sort_order'); }, "selectedExperiences.experience.region"]);
            $expModels = $trip->selectedExperiences->pluck('experience')->filter();
            $adults = $trip->adults ?: 1;
            $preferences = ($trip->accommodation_comfort ?? "standard") . " comfort, " . ($trip->travel_pace ?? "moderate") . " pace";
        }

        $experiences = $expModels->map(function ($exp) {
            if (!$exp) return null;
            return [
                "experience_id" => $exp->id,
                "name" => $exp->name,
                "slug" => $exp->slug,
                "type" => $exp->type,
                "region" => $exp->region->name ?? "Unknown",
                "duration_type" => $exp->duration_type,
                "duration_days" => $exp->duration_days,
                "duration_hours" => $exp->duration_hours,
                "difficulty_level" => $exp->difficulty_level,
                "base_cost_per_person" => $exp->base_cost_per_person,
                "includes_accommodation" => $exp->includes_accommodation,
                "includes_guide" => $exp->includes_guide,
                "includes_transport" => $exp->includes_transport,
            ];
        })->filter()->values()->toArray();

        if (empty($experiences)) {
            return response()->json(["error" => "Add experiences to your trip first"], 422);
        }

        // Calculate duration and build day-to-experience mapping
        $dayMapping = [];
        $dayNum = 1;
        foreach ($experiences as $exp) {
            $expDays = ($exp["duration_type"] === "multi_day") ? ($exp["duration_days"] ?? 1) : 1;
            for ($d = 1; $d <= $expDays; $d++) {
                $dayMapping[] = [
                    "day" => $dayNum,
                    "experience_id" => $exp["experience_id"],
                    "experience_name" => $exp["name"],
                    "day_of_experience" => $d,
                    "total_experience_days" => $expDays,
                ];
                $dayNum++;
            }
        }
        $totalDays = $dayNum - 1;
        $duration = max($totalDays, $request->get("duration", $totalDays));

        // Build regions list from experiences
        $regions = collect($experiences)->pluck("region")->unique()->implode(", ");
        $children = $isGuest ? ($gt['children'] ?? 0) : ($trip->children ?? 0);

        // Gather trip details (start/end location, dates, anchor point)
        if ($isGuest) {
            $startLocation = $gt['start_location'] ?? '';
            $endLocation = $gt['end_location'] ?? '';
            $startDate = $gt['start_date'] ?? '';
            $endDate = $gt['end_date'] ?? '';
            $anchorPoint = $gt['anchor_point'] ?? '';
            $pickupPref = $gt['pickup_preference'] ?? '';
        } else {
            $startLocation = $trip->start_location ?? '';
            $endLocation = $trip->end_location ?? '';
            $startDate = $trip->start_date ?? '';
            $endDate = $trip->end_date ?? '';
            $anchorPoint = $trip->anchor_point ?? '';
            $pickupPref = $trip->pickup_preference ?? '';
        }

        // Build prompt
        $promptBuilder = app(PromptBuilderService::class);
        $promptData = $promptBuilder->build("itinerary_generation", [
            "selected_experiences" => json_encode($experiences),
            "duration" => $duration,
            "group_size" => $adults,
            "children" => $children,
            "preferences" => $preferences,
            "regions" => $regions,
            "start_location" => $startLocation ?: 'Not specified',
            "end_location" => $endLocation ?: ($startLocation ?: 'Not specified'),
            "start_date" => $startDate ?: 'Not specified',
            "end_date" => $endDate ?: 'Not specified',
            "anchor_point" => $anchorPoint ?: 'Not specified',
            "pickup_preference" => $pickupPref ?: 'Not specified',
        ]);

        $dayMappingInstruction = "\n\nDAY-TO-EXPERIENCE MAPPING (follow this EXACTLY — do NOT add, remove, or reorder days):\n" . json_encode($dayMapping) . "\n\nYou MUST create exactly " . $totalDays . " days. Each day MUST include the experience_id specified in the mapping above in its 'experiences' array. For multi-day experiences, write a unique title and notes for each day (e.g. Day 1 of 3: arrival and setup, Day 2 of 3: main activity, Day 3 of 3: wrap-up). Do NOT create extra arrival/departure days. Travel/pickup logistics should be handled as services within the existing days.";

        $messages = [];
        if ($promptData) {
            $messages[] = ["role" => "system", "content" => $promptData["system_prompt"] . $dayMappingInstruction];
            $messages[] = ["role" => "user", "content" => $promptData["user_prompt"]];
        } else {
            $fallbackContext = "Create a " . $duration . "-day itinerary for " . $adults . " adults using: " . json_encode($experiences);
            if ($startLocation) $fallbackContext .= "\nStarting from: " . $startLocation;
            if ($startDate) $fallbackContext .= "\nDates: " . $startDate . " to " . ($endDate ?: 'flexible');
            $messages[] = ["role" => "system", "content" => "You are an itinerary planner. Output JSON only: {\"days\": [{\"title\": \"...\", \"experiences\": [{\"experience_id\": N, \"name\": \"...\", \"start_time\": \"09:00\", \"end_time\": \"17:00\", \"notes\": \"...\"}], \"notes\": \"...\"}]}" . $dayMappingInstruction];
            $messages[] = ["role" => "user", "content" => $fallbackContext];
        }

        $aiResponse = $this->callAi($messages, [
            "temperature" => $promptData["temperature"] ?? 0.5,
            "max_tokens" => $promptData["max_tokens"] ?? 2048,
            "format" => "json",
            "gemini_model" => "gemini-2.5-flash-lite",
            "fast_timeout" => 30,
        ]);

        // Try to get AI titles/notes, but don't fail if AI is unavailable
        $aiDays = [];
        $responseText = '';
        if ($aiResponse && !empty($aiResponse["content"])) {
            $responseText = $aiResponse["content"];
            $jsonStr = $responseText;
            if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $responseText, $m)) {
                $jsonStr = trim($m[1]);
            }
            $aiParsed = json_decode($jsonStr, true);
            if (!$aiParsed) {
                $aiParsed = json_decode($this->repairTruncatedJson($jsonStr), true);
            }
            if ($aiParsed && isset($aiParsed["days"])) {
                $aiParsed = $this->normalizeItinerary($aiParsed);
                $aiDays = $aiParsed["days"];
            }
        }

        // Build deterministic itinerary from day mapping — AI only provides titles/notes
        $parsed = ["days" => []];
        foreach ($dayMapping as $idx => $dm) {
            $aiDay = $aiDays[$idx] ?? [];
            $expName = $dm["experience_name"];
            $dayOfExp = $dm["day_of_experience"];
            $totalExpDays = $dm["total_experience_days"];
            $defaultTitle = $totalExpDays > 1
                ? $expName . " — Day " . $dayOfExp . " of " . $totalExpDays
                : $expName;

            $parsed["days"][] = [
                "title" => $aiDay["title"] ?? $defaultTitle,
                "description" => $aiDay["description"] ?? $aiDay["notes"] ?? null,
                "notes" => $aiDay["notes"] ?? null,
                "experiences" => [[
                    "experience_id" => $dm["experience_id"],
                    "name" => $expName,
                    "start_time" => $aiDay["experiences"][0]["start_time"] ?? "09:00",
                    "end_time" => $aiDay["experiences"][0]["end_time"] ?? "17:00",
                    "notes" => $aiDay["experiences"][0]["notes"] ?? null,
                ]],
                "services" => $aiDay["services"] ?? [],
            ];
        }

        if ($isGuest) {
            // Re-read session to get latest experience_ids (may have changed during AI processing)
            $gt = $this->guestTrip();
            $currentExpIds = $gt['experience_ids'] ?? [];

            // Filter AI itinerary to only include experiences still in the trip
            if (!empty($currentExpIds) && isset($parsed['days'])) {
                foreach ($parsed['days'] as &$day) {
                    $day['experiences'] = array_values(array_filter($day['experiences'] ?? [], function ($exp) use ($currentExpIds) {
                        return in_array($exp['experience_id'] ?? null, $currentExpIds);
                    }));
                }
                unset($day);
                // Remove days with no experiences left
                $parsed['days'] = array_values(array_filter($parsed['days'], function ($day) {
                    return !empty($day['experiences']);
                }));
            }

            $gt['ai_itinerary'] = $parsed;
            $gt['ai_raw_response'] = $responseText;
            $this->saveGuestTrip($gt);

            $days = $this->buildGuestTimeline($gt);
            $pricing = $this->computeGuestPricing($gt);

            return response()->json([
                "success" => true,
                "days" => $days,
                "pricing" => $pricing,
                "trip_id" => "guest",
                "message" => "Itinerary generated successfully!",
            ]);
        }

        // Persist to DB for logged-in users
        $trip->update(["ai_raw_response" => $responseText]);

        $itineraryService = app(ItineraryService::class);
        $result = $itineraryService->parseAndCreateFromAi($trip, $parsed);

        if (!$result) {
            return response()->json([
                "success" => false,
                "error" => "Failed to save itinerary. Please try again.",
            ], 422);
        }

        $costCalculator = app(CostCalculatorService::class);
        $pricing = $costCalculator->calculate($trip);

        $days = $trip->tripDays()->with(["experiences.experience.days", "services"])->get();

        return response()->json([
            "success" => true,
            "days" => $days,
            "pricing" => $pricing,
            "trip_id" => $trip->id,
            "message" => "Itinerary generated successfully!",
        ]);
    }

    // ===========================
    // HCT DASHBOARD
    // ===========================

    protected function getDashboardStats(Request $request): JsonResponse
    {
        $stats = [
            "total_leads" => Lead::where("stage", "follow_up")->count(),
            "active_trips" => Trip::whereIn("status", ["confirmed", "running"])->count(),
            "pending_applications" => ServiceProvider::where("status", "pending")->count(),
            "unresolved_support" => SupportRequest::where("is_resolved", false)->count(),
            "total_travelers" => User::where("user_role", "traveller")->count(),
            "total_providers" => ServiceProvider::where("status", "approved")->count(),
            "upcoming_trips" => Trip::where("status", "confirmed")
                ->where("start_date", ">=", now())
                ->where("start_date", "<=", now()->addDays(30))
                ->count(),
            "revenue_this_month" => TravellerPayment::whereMonth("payment_date", now()->month)
                ->whereYear("payment_date", now()->year)
                ->sum("amount"),
        ];
        return response()->json(["stats" => $stats]);
    }

    protected function createHctUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "full_name" => "required|string|max:255",
            "email" => "required|email|unique:users,email",
            "password" => "required|min:8",
            "user_role" => "required|in:hct_admin,hct_collaborator",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $user = User::create([
            "full_name" => $request->full_name,
            "email" => $request->email,
            "password" => $request->password,
            "user_role" => $request->user_role,
            "auth_type" => "email",
        ]);

        return response()->json(["success" => true, "user" => $user]);
    }

    protected function updateHctUser(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->user_id);
        $data = $request->only(["full_name", "email", "user_role", "mobile", "photo"]);
        if ($request->filled("password")) {
            $data["password"] = $request->password;
        }
        $user->update($data);
        return response()->json(["success" => true]);
    }

    protected function deactivateHctUser(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->user_id);
        $user->update(["status" => "inactive"]);
        return response()->json(["success" => true]);
    }

    protected function getSystemLists(Request $request): JsonResponse
    {
        $type = $request->get("list_type", "service_type");
        $items = SystemList::where("list_type", $type)->orderBy("sort_order")->get();
        return response()->json(["items" => $items]);
    }

    protected function saveSystemListItem(Request $request): JsonResponse
    {
        $data = $request->only(["list_type", "name", "sort_order"]);
        if ($request->filled("id")) {
            $item = SystemList::findOrFail($request->id);
            $item->update($data);
        } else {
            $item = SystemList::create($data);
        }
        return response()->json(["success" => true, "item" => $item]);
    }

    protected function deactivateSystemListItem(Request $request): JsonResponse
    {
        $item = SystemList::findOrFail($request->id);
        $item->update(["is_active" => false]);
        return response()->json(["success" => true]);
    }

    protected function getSupportRequests(Request $request): JsonResponse
    {
        $query = SupportRequest::with(["user", "trip"]);
        if ($request->boolean("unresolved_only", true)) {
            $query->where("is_resolved", false);
        }
        $requests = $query->orderBy("created_at", "desc")->paginate(20);
        return response()->json($requests);
    }

    protected function resolveSupportRequest(Request $request): JsonResponse
    {
        $sr = SupportRequest::findOrFail($request->id);
        $sr->update(["is_resolved" => true, "resolved_by" => Auth::id()]);
        return response()->json(["success" => true]);
    }

    protected function chatWithAiHct(Request $request): JsonResponse
    {
        set_time_limit(120);

        $user = Auth::user();
        $trip = Trip::findOrFail($request->trip_id);

        AiConversation::create([
            "trip_id" => $trip->id,
            "user_id" => $user->id,
            "role" => "user",
            "content" => $request->message,
            "context_type" => "hct_chat",
        ]);

        $history = AiConversation::where("trip_id", $trip->id)
            ->where("context_type", "hct_chat")
            ->orderBy("created_at")
            ->get()
            ->map(fn($m) => ["role" => $m->role, "content" => $m->content])
            ->toArray();

        $trip->load(["tripDays.experiences.experience", "tripDays.services", "tripRegions.region", "user"]);

        $promptBuilder = app(PromptBuilderService::class);
        $promptData = $promptBuilder->build("hct_chat", [
            "trip_json" => $trip->toJson(),
        ]);

        $messages = [];

        if ($promptData) {
            $messages[] = ["role" => "system", "content" => $promptData["system_prompt"]];
        } else {
            $messages[] = ["role" => "system", "content" => "You are an AI assistant for the HCT (HECO Core Team) operations team. Help with trip planning, itinerary optimization, and operational decisions. Provide structured suggestions in JSON when asked about itinerary modifications."];
        }
        $messages = array_merge($messages, $history);

        $aiResponse = $this->callAi($messages);
        $responseText = $aiResponse["content"] ?? "AI is currently unavailable. Please try again.";

        AiConversation::create([
            "trip_id" => $trip->id,
            "user_id" => $user->id,
            "role" => "assistant",
            "content" => $responseText,
            "context_type" => "hct_chat",
        ]);

        return response()->json(["success" => true, "response" => $responseText]);
    }

    protected function getLeadReminders(Request $request): JsonResponse
    {
        $reminders = app(LeadService::class)->getReminders();
        return response()->json(["reminders" => $reminders]);
    }

    protected function getLeads(Request $request): JsonResponse
    {
        $query = Lead::with(["user", "trip", "assignedHct"]);

        if ($request->filled("stage")) {
            $query->where("stage", $request->stage);
        }
        if ($request->filled("search")) {
            $search = $request->search;
            $query->whereHas("user", fn($q) => $q->where("full_name", "like", "%{$search}%")->orWhere("email", "like", "%{$search}%"));
        }

        $leads = $query->orderBy("enquiry_date", "desc")->paginate(20);
        return response()->json($leads);
    }

    protected function updateLead(Request $request): JsonResponse
    {
        $lead = Lead::findOrFail($request->lead_id);
        $data = $request->only(["stage", "assigned_hct_id", "interaction_mode", "reminder_delay_days", "notes"]);

        if (isset($data["interaction_mode"])) {
            $data["last_interaction_date"] = now();
        }

        $lead->update($data);

        if ($request->stage === "won") {
            app(LeadService::class)->markWon($lead);
        } elseif ($request->stage === "lost") {
            app(LeadService::class)->markLost($lead);
        }

        return response()->json(["success" => true]);
    }

    protected function getLeadHistory(Request $request): JsonResponse
    {
        $lead = Lead::with(["user", "trip.travellerPayments", "assignedHct"])->findOrFail($request->lead_id);
        $conversations = AiConversation::where("trip_id", $lead->trip_id)->orderBy("created_at")->get();
        return response()->json(["lead" => $lead, "conversations" => $conversations]);
    }

    protected function getUpcomingTrips(Request $request): JsonResponse
    {
        $trips = Trip::whereIn("status", ["confirmed", "running"])
            ->with(["user", "tripRegions.region"])
            ->orderBy("start_date")
            ->paginate(20);
        return response()->json($trips);
    }

    protected function getTripsByDateRange(Request $request): JsonResponse
    {
        $trips = Trip::whereBetween("start_date", [$request->start_date, $request->end_date])
            ->with(["user", "tripRegions.region"])
            ->orderBy("start_date")
            ->get();
        return response()->json(["trips" => $trips]);
    }

    protected function updateTripStatus(Request $request): JsonResponse
    {
        $trip = Trip::findOrFail($request->trip_id);
        $trip->update(["status" => $request->status]);
        if (in_array($request->status, ["completed", "cancelled"])) {
            $trip->update(["stage" => "closed"]);
        }
        return response()->json(["success" => true]);
    }

    protected function getCalendarTrips(Request $request): JsonResponse
    {
        $month = $request->get("month", now()->month);
        $year = $request->get("year", now()->year);

        $trips = Trip::whereIn("status", ["confirmed", "running"])
            ->where(function ($q) use ($month, $year) {
                $q->whereMonth("start_date", $month)->whereYear("start_date", $year)
                  ->orWhere(function ($q2) use ($month, $year) {
                      $q2->whereMonth("end_date", $month)->whereYear("end_date", $year);
                  });
            })
            ->with(["user", "tripRegions.region"])
            ->get();

        return response()->json(["trips" => $trips]);
    }

    protected function getSpPayments(Request $request): JsonResponse
    {
        $query = SpPayment::with(["trip", "serviceProvider"]);
        if ($request->filled("trip_id")) {
            $query->where("trip_id", $request->trip_id);
        }
        $payments = $query->orderBy("created_at", "desc")->paginate(20);
        return response()->json($payments);
    }

    protected function addSpPaymentEntry(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "sp_payment_id" => "required|exists:sp_payments,id",
            "amount" => "required|numeric|min:0.01",
            "payment_date" => "required|date",
            "mode" => "required|string",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $spPayment = SpPayment::findOrFail($request->sp_payment_id);

        SpPaymentEntry::create([
            "sp_payment_id" => $spPayment->id,
            "amount" => $request->amount,
            "payment_date" => $request->payment_date,
            "mode" => $request->mode,
            "notes" => $request->notes,
            "recorded_by" => Auth::id(),
        ]);

        $totalPaid = $spPayment->entries()->sum("amount");
        $spPayment->update([
            "amount_paid" => $totalPaid,
            "balance" => $spPayment->amount_due - $totalPaid,
        ]);

        return response()->json(["success" => true]);
    }

    protected function editSpPaymentEntry(Request $request): JsonResponse
    {
        $entry = SpPaymentEntry::findOrFail($request->entry_id);
        $entry->update($request->only(["amount", "payment_date", "mode", "notes"]));

        $spPayment = $entry->spPayment;
        $totalPaid = $spPayment->entries()->sum("amount");
        $spPayment->update([
            "amount_paid" => $totalPaid,
            "balance" => $spPayment->amount_due - $totalPaid,
        ]);

        return response()->json(["success" => true]);
    }

    protected function getSpPaymentHistory(Request $request): JsonResponse
    {
        $entries = SpPaymentEntry::where("sp_payment_id", $request->sp_payment_id)
            ->with("recorder")
            ->orderBy("payment_date", "desc")
            ->get();
        return response()->json(["entries" => $entries]);
    }

    protected function getTravellerPaymentsOverview(Request $request): JsonResponse
    {
        $trips = Trip::whereIn("status", ["confirmed", "running", "completed"])
            ->with(["user", "travellerPayments"])
            ->get()
            ->map(function ($trip) {
                $totalPaid = $trip->travellerPayments->sum("amount");
                return [
                    "trip_id" => $trip->trip_id,
                    "traveller" => $trip->user->full_name ?? $trip->user->email,
                    "final_price" => $trip->final_price,
                    "total_paid" => $totalPaid,
                    "balance" => $trip->final_price - $totalPaid,
                    "status" => $trip->status,
                ];
            });

        return response()->json(["payments" => $trips]);
    }

    protected function getGstReport(Request $request): JsonResponse
    {
        $month = $request->get("month", now()->month);
        $year = $request->get("year", now()->year);

        $trips = Trip::whereIn("status", ["confirmed", "running", "completed"])
            ->whereMonth("created_at", $month)
            ->whereYear("created_at", $year)
            ->with("user")
            ->get()
            ->map(fn($t) => [
                "trip_id" => $t->trip_id,
                "traveller" => $t->user->full_name ?? $t->user->email,
                "subtotal" => $t->subtotal,
                "gst_amount" => $t->gst_amount,
                "final_price" => $t->final_price,
                "status" => $t->status,
            ]);

        $totalGst = $trips->sum("gst_amount");

        return response()->json(["trips" => $trips, "total_gst" => $totalGst]);
    }

    protected function getProviders(Request $request): JsonResponse
    {
        $query = ServiceProvider::where("status", "approved")->with("region");
        if ($request->filled("provider_type")) {
            $query->where("provider_type", $request->provider_type);
        }
        if ($request->filled("region_id")) {
            $query->where("region_id", $request->region_id);
        }
        if ($request->filled("search")) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where("name", "like", "%{$search}%")->orWhere("email", "like", "%{$search}%");
            });
        }
        $providers = $query->paginate(20);
        return response()->json($providers);
    }

    protected function editProvider(Request $request): JsonResponse
    {
        $provider = ServiceProvider::findOrFail($request->provider_id);
        $provider->update($request->only([
            "name", "contact_person", "email", "phone_1", "phone_2",
            "address", "bank_name", "bank_ifsc", "bank_account_name",
            "bank_account_number", "upi", "services_offered",
            "accommodation_categories", "vehicle_types", "activity_types", "notes",
        ]));
        return response()->json(["success" => true]);
    }

    protected function getProviderTrips(Request $request): JsonResponse
    {
        $trips = Trip::whereHas("tripDays.services", fn($q) => $q->where("service_provider_id", $request->provider_id))
            ->with("user")
            ->orderBy("start_date", "desc")
            ->get();
        return response()->json(["trips" => $trips]);
    }

    protected function getProviderPaymentHistory(Request $request): JsonResponse
    {
        $payments = SpPayment::where("service_provider_id", $request->provider_id)
            ->with(["trip", "entries"])
            ->orderBy("created_at", "desc")
            ->get();
        return response()->json(["payments" => $payments]);
    }

    protected function getTravelersList(Request $request): JsonResponse
    {
        $travelers = User::where("user_role", "traveller")
            ->whereHas("trips", fn($q) => $q->where("status", "confirmed"))
            ->withCount("trips")
            ->paginate(20);
        return response()->json($travelers);
    }

    protected function getTravelerTrips(Request $request): JsonResponse
    {
        $trips = Trip::where("user_id", $request->user_id)
            ->with("tripRegions.region")
            ->orderBy("created_at", "desc")
            ->get();
        return response()->json(["trips" => $trips]);
    }

    protected function getTravelerPaymentHistory(Request $request): JsonResponse
    {
        $payments = TravellerPayment::where("user_id", $request->user_id)
            ->with(["trip", "recorder"])
            ->orderBy("payment_date", "desc")
            ->get();
        return response()->json(["payments" => $payments]);
    }

    protected function getProviderApplications(Request $request): JsonResponse
    {
        $applications = ServiceProvider::where("status", "pending")
            ->with("region")
            ->orderBy("created_at", "desc")
            ->paginate(20);
        return response()->json($applications);
    }

    protected function approveProvider(Request $request): JsonResponse
    {
        $provider = ServiceProvider::findOrFail($request->provider_id);

        if (!$provider->user_id) {
            $user = User::where("email", $provider->email)->first();
            if (!$user) {
                $user = User::create([
                    "full_name" => $provider->name,
                    "email" => $provider->email,
                    "password" => Str::random(12),
                    "auth_type" => "email",
                    "user_role" => $provider->provider_type,
                ]);
            } else {
                $user->update(["user_role" => $provider->provider_type]);
            }
            $provider->user_id = $user->id;
        }

        $provider->update([
            "status" => "approved",
            "approved_at" => now(),
            "approved_by" => Auth::id(),
            "user_id" => $provider->user_id,
        ]);

        return response()->json(["success" => true]);
    }

    protected function rejectProvider(Request $request): JsonResponse
    {
        $provider = ServiceProvider::findOrFail($request->provider_id);
        $provider->update(["status" => "rejected"]);
        return response()->json(["success" => true]);
    }

    // ===========================
    // REGION MANAGEMENT
    // ===========================

    protected function getRegionsList(Request $request): JsonResponse
    {
        $query = Region::withCount("experiences");

        if ($request->filled("search")) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where("name", "like", "%{$search}%")
                  ->orWhere("country", "like", "%{$search}%")
                  ->orWhere("continent", "like", "%{$search}%");
            });
        }
        if ($request->filled("continent")) {
            $query->where("continent", $request->continent);
        }
        if ($request->filled("country")) {
            $query->where("country", $request->country);
        }
        if ($request->filled("status") && $request->status !== "") {
            $query->where("is_active", $request->status);
        }

        $regions = $query->orderBy("continent")->orderBy("country")->orderBy("sort_order")->get();

        return response()->json(["data" => $regions]);
    }

    protected function saveRegion(Request $request): JsonResponse
    {
        $rules = [
            "name" => "required|string|max:255",
            "continent" => "required|string|max:100",
            "country" => "required|string|max:100",
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $data = [
            "name" => $request->name,
            "slug" => Str::slug($request->name),
            "description" => $request->description,
            "continent" => $request->continent,
            "country" => $request->country,
            "latitude" => $request->latitude,
            "longitude" => $request->longitude,
            "external_url" => $request->external_url,
            "is_active" => $request->boolean("is_active", true),
        ];

        if ($request->filled("region_id")) {
            $region = Region::findOrFail($request->region_id);
            $region->update($data);
            $msg = "Region updated successfully";
        } else {
            $data["sort_order"] = Region::max("sort_order") + 1;
            $region = Region::create($data);
            $msg = "Region created successfully";
        }

        return response()->json(["success" => $msg, "region" => $region]);
    }

    protected function toggleRegion(Request $request): JsonResponse
    {
        $region = Region::findOrFail($request->region_id);
        $region->update(["is_active" => !$region->is_active]);
        return response()->json(["success" => "Region " . ($region->is_active ? "activated" : "deactivated")]);
    }

    protected function deleteRegion(Request $request): JsonResponse
    {
        $region = Region::findOrFail($request->region_id);
        if ($region->experiences()->count() > 0) {
            return response()->json(["error" => "Cannot delete region with existing experiences. Deactivate instead."], 422);
        }
        $region->delete();
        return response()->json(["success" => "Region deleted"]);
    }

    // ===========================
    // CURRENCY MANAGEMENT
    // ===========================

    protected function getCurrenciesList(Request $request): JsonResponse
    {
        $query = Currency::query();

        if ($request->filled("search")) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where("name", "like", "%{$search}%")
                  ->orWhere("code", "like", "%{$search}%")
                  ->orWhere("symbol", "like", "%{$search}%");
            });
        }
        if ($request->filled("status")) {
            $query->where("is_active", $request->status);
        }

        $currencies = $query->orderBy("sort_order")->get();
        return response()->json(["data" => $currencies]);
    }

    protected function saveCurrency(Request $request): JsonResponse
    {
        $rules = [
            "code" => "required|string|size:3",
            "name" => "required|string|max:100",
            "symbol" => "required|string|max:10",
            "rate_to_usd" => "required|numeric|min:0.000001",
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $data = [
            "code" => strtoupper($request->code),
            "name" => $request->name,
            "symbol" => $request->symbol,
            "locale" => $request->locale ?? "en-US",
            "flag" => $request->flag ?? "",
            "rate_to_usd" => $request->rate_to_usd,
            "is_active" => $request->is_active ? true : false,
            "sort_order" => $request->sort_order ?? 0,
        ];

        if ($request->filled("currency_id")) {
            $currency = Currency::findOrFail($request->currency_id);
            $currency->update($data);
            return response()->json(["success" => "Currency updated"]);
        }

        // Check for duplicate code
        if (Currency::where("code", $data["code"])->exists()) {
            return response()->json(["error" => "Currency code already exists"], 422);
        }

        Currency::create($data);
        return response()->json(["success" => "Currency added"]);
    }

    protected function toggleCurrency(Request $request): JsonResponse
    {
        $currency = Currency::findOrFail($request->currency_id);
        $currency->update(["is_active" => !$currency->is_active]);
        return response()->json(["success" => "Currency " . ($currency->is_active ? "activated" : "deactivated")]);
    }

    protected function deleteCurrency(Request $request): JsonResponse
    {
        $currency = Currency::findOrFail($request->currency_id);
        if (in_array($currency->code, ["USD", "INR"])) {
            return response()->json(["error" => "Cannot delete base currencies (USD, INR)"], 422);
        }
        $currency->delete();
        return response()->json(["success" => "Currency deleted"]);
    }

    // ===========================
    // EXPERIENCE & RP MANAGEMENT
    // ===========================

    protected function getExperiencesList(Request $request): JsonResponse
    {
        $query = Experience::with(["region", "hlh", "days"]);
        if ($request->filled("search")) {
            $search = $request->search;
            $query->where("name", "like", "%{$search}%");
        }
        if ($request->filled("region_id")) {
            $query->where("region_id", $request->region_id);
        }
        $experiences = $query->orderBy("sort_order")->paginate(20);
        return response()->json($experiences);
    }

    protected function saveExperience(Request $request): JsonResponse
    {
        $data = $request->except(["_token", "save_experience", "experience_days"]);

        if ($request->hasFile("card_image")) {
            $file = $request->file("card_image");
            $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/experiences'), $filename);
            $data["card_image"] = '/uploads/experiences/' . $filename;
        } else {
            unset($data["card_image"]);
        }

        // Handle JSON fields
        foreach (["best_seasons", "available_months", "restricted_months", "unavailable_months", "gallery", "osp_services", "seasonal_price_variation"] as $jsonField) {
            if (isset($data[$jsonField]) && is_string($data[$jsonField])) {
                $data[$jsonField] = json_decode($data[$jsonField], true);
            }
        }

        if (!empty($data["slug"])) {
            $data["slug"] = Str::slug($data["slug"]);
        } elseif (!empty($data["name"])) {
            $data["slug"] = Str::slug($data["name"]);
        }

        if ($request->filled("id")) {
            $experience = Experience::findOrFail($request->id);
            $experience->update($data);
        } else {
            $experience = Experience::create($data);
        }

        // Save day-wise details
        $experience->days()->delete();
        $daysData = $request->input('experience_days', []);
        foreach ($daysData as $idx => $dayData) {
            $experience->days()->create([
                'day_number' => $dayData['day_number'] ?? ($idx + 1),
                'title' => $dayData['title'] ?? null,
                'short_description' => $dayData['short_description'] ?? null,
                'start_time' => $dayData['start_time'] ?? null,
                'end_time' => $dayData['end_time'] ?? null,
                'inclusions' => $dayData['inclusions'] ?? [],
                'sort_order' => $idx,
            ]);
        }

        return response()->json(["success" => true, "experience" => $experience]);
    }

    protected function disableExperience(Request $request): JsonResponse
    {
        $experience = Experience::findOrFail($request->id);
        $experience->update(["is_active" => false]);
        return response()->json(["success" => true]);
    }

    protected function getRegenerativeProjects(Request $request): JsonResponse
    {
        $projects = RegenerativeProject::with("region")->orderBy("name")->paginate(20);
        return response()->json($projects);
    }

    protected function saveRegenerativeProject(Request $request): JsonResponse
    {
        $data = $request->except(["_token", "save_regenerative_project"]);

        if ($request->hasFile("main_image")) {
            $data["main_image"] = $request->file("main_image")->store("rp", "public");
        } else {
            unset($data["main_image"]);
        }

        foreach (["gallery", "active_periods", "paused_periods", "fallback_for_regions"] as $jsonField) {
            if (isset($data[$jsonField]) && is_string($data[$jsonField])) {
                $data[$jsonField] = json_decode($data[$jsonField], true);
            }
        }

        if ($request->filled("id")) {
            $project = RegenerativeProject::findOrFail($request->id);
            $project->update($data);
        } else {
            $project = RegenerativeProject::create($data);
        }

        return response()->json(["success" => true, "project" => $project]);
    }

    protected function disableRegenerativeProject(Request $request): JsonResponse
    {
        $project = RegenerativeProject::findOrFail($request->id);
        $project->update(["is_active" => false]);
        return response()->json(["success" => true]);
    }

    // ===========================
    // TRIP MANAGER
    // ===========================

    protected function getTripInfo(Request $request): JsonResponse
    {
        $trip = Trip::with([
            "user", "tripRegions.region", "tripRegions.hrp",
            "travellerPayments.recorder", "spPayments.serviceProvider",
            "lead.assignedHct",
        ])->findOrFail($request->trip_id);
        return response()->json(["trip" => $trip]);
    }

    protected function updateTripInfo(Request $request): JsonResponse
    {
        $trip = Trip::findOrFail($request->trip_id);
        $trip->update($request->only([
            "trip_name", "traveller_origin", "adults", "children", "infants",
            "start_date", "end_date", "start_location", "end_location",
            "pickup_location", "pickup_time", "drop_location", "drop_time",
            "operations_notes", "accommodation_comfort", "vehicle_comfort",
            "guide_preference", "travel_pace", "budget_sensitivity",
            "margin_rp_percent", "margin_hrp_percent", "commission_hct_percent",
            "general_notes",
        ]));

        app(CostCalculatorService::class)->calculate($trip);

        return response()->json(["success" => true]);
    }

    protected function addTravellerPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "trip_id" => "required|exists:trips,id",
            "amount" => "required|numeric|min:0.01",
            "payment_date" => "required|date",
            "mode" => "required|string",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $trip = Trip::findOrFail($request->trip_id);

        TravellerPayment::create([
            "trip_id" => $trip->id,
            "user_id" => $trip->user_id,
            "amount" => $request->amount,
            "payment_date" => $request->payment_date,
            "mode" => $request->mode,
            "notes" => $request->notes,
            "recorded_by" => Auth::id(),
        ]);

        app(LeadService::class)->checkPaymentAndTransition($trip);

        return response()->json(["success" => true]);
    }

    protected function getTravellerPaymentHistory(Request $request): JsonResponse
    {
        $payments = TravellerPayment::where("trip_id", $request->trip_id)
            ->with("recorder")
            ->orderBy("payment_date", "desc")
            ->get();
        return response()->json(["payments" => $payments]);
    }

    protected function editTravellerPayment(Request $request): JsonResponse
    {
        $payment = TravellerPayment::findOrFail($request->payment_id);
        $payment->update($request->only(["amount", "payment_date", "mode", "notes"]));
        return response()->json(["success" => true]);
    }

    protected function getTripItinerary(Request $request): JsonResponse
    {
        $trip = Trip::with([
            "tripDays.experiences.experience.region",
            "tripDays.experiences.experience.hlh",
            "tripDays.experiences.experience.days",
            "tripDays.services.serviceProvider",
            "selectedExperiences.experience",
        ])->findOrFail($request->trip_id);

        return response()->json(["trip" => $trip]);
    }

    protected function searchExperiencesForTrip(Request $request): JsonResponse
    {
        $query = Experience::where("is_active", true)->with(["region", "hlh"]);

        if ($request->filled("search")) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where("name", "like", "%{$search}%")
                  ->orWhere("short_description", "like", "%{$search}%");
            });
        }
        if ($request->filled("region_id")) {
            $query->where("region_id", $request->region_id);
        }
        if ($request->filled("type")) {
            $query->where("type", $request->type);
        }

        $experiences = $query->orderBy("sort_order")->limit(20)->get();
        return response()->json(["experiences" => $experiences]);
    }

    protected function addExperienceToDay(Request $request): JsonResponse
    {
        $day = TripDay::findOrFail($request->day_id);
        $experience = Experience::findOrFail($request->experience_id);

        $dayExp = app(ItineraryService::class)->addExperienceToDay($day, $experience, $request->all());

        if ($experience->includes_accommodation) {
            TripDayService::create([
                "trip_day_id" => $day->id,
                "service_type" => "accommodation",
                "description" => $experience->accommodation_category ?? "Accommodation",
                "cost" => $experience->cost_accommodation,
                "is_included" => true,
            ]);
        }
        if ($experience->includes_guide) {
            TripDayService::create([
                "trip_day_id" => $day->id,
                "service_type" => "guide",
                "description" => "Guide service",
                "cost" => $experience->cost_guide,
                "is_included" => true,
            ]);
        }

        return response()->json(["success" => true, "day_experience" => $dayExp]);
    }

    protected function removeExperienceFromDay(Request $request): JsonResponse
    {
        TripDayExperience::destroy($request->day_experience_id);
        return response()->json(["success" => true]);
    }

    protected function reorderTripDays(Request $request): JsonResponse
    {
        $order = $request->get("order", []);
        foreach ($order as $index => $dayId) {
            TripDay::where("id", $dayId)->update(["sort_order" => $index, "day_number" => $index + 1]);
        }
        return response()->json(["success" => true]);
    }

    protected function addTripDay(Request $request): JsonResponse
    {
        $trip = Trip::findOrFail($request->trip_id);
        $maxDay = $trip->tripDays()->max("day_number") ?? 0;

        $day = TripDay::create([
            "trip_id" => $trip->id,
            "day_number" => $maxDay + 1,
            "sort_order" => $maxDay,
        ]);

        return response()->json(["success" => true, "day" => $day]);
    }

    protected function removeTripDay(Request $request): JsonResponse
    {
        $day = TripDay::findOrFail($request->day_id);
        $tripId = $day->trip_id;
        $day->delete();

        $days = TripDay::where("trip_id", $tripId)->orderBy("sort_order")->get();
        foreach ($days as $i => $d) {
            $d->update(["day_number" => $i + 1, "sort_order" => $i]);
        }

        return response()->json(["success" => true]);
    }

    protected function getDayServices(Request $request): JsonResponse
    {
        $services = TripDayService::where("trip_day_id", $request->day_id)
            ->with("serviceProvider")
            ->orderBy("sort_order")
            ->get();
        return response()->json(["services" => $services]);
    }

    protected function addDayService(Request $request): JsonResponse
    {
        $service = TripDayService::create([
            "trip_day_id" => $request->day_id,
            "service_provider_id" => $request->service_provider_id,
            "service_type" => $request->service_type,
            "description" => $request->description,
            "from_location" => $request->from_location,
            "to_location" => $request->to_location,
            "cost" => $request->cost ?? 0,
            "is_included" => $request->boolean("is_included", false),
            "notes" => $request->notes,
        ]);
        return response()->json(["success" => true, "service" => $service]);
    }

    protected function editDayService(Request $request): JsonResponse
    {
        $service = TripDayService::findOrFail($request->service_id);
        $service->update($request->only([
            "service_provider_id", "service_type", "description",
            "from_location", "to_location", "cost", "is_included", "notes",
        ]));
        return response()->json(["success" => true]);
    }

    protected function removeDayService(Request $request): JsonResponse
    {
        TripDayService::destroy($request->service_id);
        return response()->json(["success" => true]);
    }

    protected function changeDayServiceProvider(Request $request): JsonResponse
    {
        $service = TripDayService::with('tripDay.trip')->findOrFail($request->service_id);
        $newSpId = $request->service_provider_id;
        $date = $service->tripDay->date;
        $trip = $service->tripDay->trip;

        $availabilityService = new SpAvailabilityService();

        // Release old booking if the service had an SP
        if ($service->service_provider_id) {
            $availabilityService->releaseBooking($service->id);
        }

        // Check availability and book new SP
        if ($newSpId && $date) {
            if (!$availabilityService->isAvailableOnDate($newSpId, $date)) {
                return response()->json(['error' => 'This service provider is not available on ' . $date->format('d M Y')], 422);
            }
            $availabilityService->bookForTrip($newSpId, $trip->id, $service->id, $date);
        }

        // Update pricing from SpPricing if available
        $updateData = ['service_provider_id' => $newSpId];
        if ($newSpId) {
            $pricing = SpPricing::where('service_provider_id', $newSpId)
                ->where('service_type', $service->service_type)
                ->where('is_active', true)
                ->first();
            if ($pricing) {
                $updateData['cost'] = $pricing->price;
            }
        }

        $service->update($updateData);
        return response()->json(["success" => true]);
    }

    protected function requestAiRecalculation(Request $request): JsonResponse
    {
        $trip = Trip::with(["tripDays.experiences.experience", "tripDays.services", "tripRegions.region", "user"])->findOrFail($request->trip_id);

        $promptBuilder = app(PromptBuilderService::class);
        $promptData = $promptBuilder->build("itinerary_optimization", [
            "trip_json" => $trip->toJson(),
            "instruction" => $request->get("instruction", "Optimize this itinerary for cost and experience balance"),
        ]);

        $messages = [];
        if ($promptData) {
            $messages[] = ["role" => "system", "content" => $promptData["system_prompt"]];
            $messages[] = ["role" => "user", "content" => $promptData["user_prompt"]];
        } else {
            $messages[] = ["role" => "system", "content" => "You are an itinerary optimization AI. Analyze the trip and suggest improvements."];
            $messages[] = ["role" => "user", "content" => "Optimize this trip: " . $trip->toJson()];
        }

        $aiResponse = $this->callAi($messages);
        $responseText = $aiResponse["content"] ?? "AI is currently unavailable.";

        $trip->update(["ai_raw_response" => $responseText]);

        return response()->json(["success" => true, "response" => $responseText]);
    }

    protected function recalculateTripCost(Request $request): JsonResponse
    {
        $trip = Trip::findOrFail($request->trip_id);
        $pricing = app(CostCalculatorService::class)->calculate($trip);
        return response()->json(["success" => true, "pricing" => $pricing]);
    }

    // ===========================
    // SP APPLICATION
    // ===========================

    protected function submitSpApplication(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "provider_type" => "required|in:hrp,hlh,osp",
            "name" => "required|string|max:255",
            "email" => "required|email",
            "phone_1" => "required|string|max:20",
            "region_id" => "required|exists:regions,id",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $provider = ServiceProvider::create([
            "provider_type" => $request->provider_type,
            "name" => $request->name,
            "contact_person" => $request->contact_person,
            "email" => $request->email,
            "phone_1" => $request->phone_1,
            "phone_2" => $request->phone_2,
            "region_id" => $request->region_id,
            "address" => $request->address,
            "services_offered" => $request->services_offered,
            "accommodation_categories" => $request->accommodation_categories,
            "vehicle_types" => $request->vehicle_types,
            "activity_types" => $request->activity_types,
            "notes" => $request->notes,
            "status" => "pending",
        ]);

        return response()->json(["success" => true, "message" => "Application submitted successfully"]);
    }

    // ===========================
    // SETTINGS & PDF
    // ===========================

    protected function getSettings(Request $request): JsonResponse
    {
        $group = $request->get("group", "general");
        $settings = Setting::where("group", $group)->get();
        return response()->json(["settings" => $settings]);
    }

    protected function saveSettings(Request $request): JsonResponse
    {
        $settings = $request->get("settings", []);
        foreach ($settings as $key => $value) {
            Setting::setValue($key, $value, $request->get("group", "general"));
        }
        return response()->json(["success" => true]);
    }

    protected function getPdfTemplates(Request $request): JsonResponse
    {
        $templates = PdfTemplate::all();
        return response()->json(["templates" => $templates]);
    }

    protected function savePdfTemplate(Request $request): JsonResponse
    {
        $data = $request->only(["name", "key", "header_html", "footer_html", "css", "paper_size", "orientation"]);
        if ($request->filled("id")) {
            $template = PdfTemplate::findOrFail($request->id);
            $template->update($data);
        } else {
            $template = PdfTemplate::create($data);
        }
        return response()->json(["success" => true, "template" => $template]);
    }

    // ===========================
    // SP AVAILABILITY (Portal - logged-in SP)
    // ===========================

    protected function getSpCalendar(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->isServiceProvider()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $sp = ServiceProvider::where('user_id', $user->id)->first();
        if (!$sp) return response()->json(['error' => 'No provider found'], 404);

        $year = (int) ($request->year ?: now()->year);
        $month = (int) ($request->month ?: now()->month);

        $service = new SpAvailabilityService();
        $calendar = $service->getMonthCalendar($sp->id, $year, $month);

        return response()->json([
            'calendar' => $calendar,
            'ical_url' => $sp->ical_url,
            'ical_last_synced_at' => $sp->ical_last_synced_at?->format('d M Y H:i'),
        ]);
    }

    protected function spBlockDates(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->isServiceProvider()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $sp = ServiceProvider::where('user_id', $user->id)->first();
        if (!$sp) return response()->json(['error' => 'No provider found'], 404);

        $dates = $request->input('dates', []);
        if (empty($dates)) return response()->json(['error' => 'No dates provided'], 422);

        $service = new SpAvailabilityService();
        $count = $service->blockDates($sp->id, $dates, $request->notes);

        return response()->json(['success' => true, 'blocked' => $count]);
    }

    protected function spUnblockDates(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->isServiceProvider()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $sp = ServiceProvider::where('user_id', $user->id)->first();
        if (!$sp) return response()->json(['error' => 'No provider found'], 404);

        $dates = $request->input('dates', []);
        if (empty($dates)) return response()->json(['error' => 'No dates provided'], 422);

        $service = new SpAvailabilityService();
        $count = $service->unblockDates($sp->id, $dates);

        return response()->json(['success' => true, 'unblocked' => $count]);
    }

    protected function spSaveIcalUrl(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->isServiceProvider()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $sp = ServiceProvider::where('user_id', $user->id)->first();
        if (!$sp) return response()->json(['error' => 'No provider found'], 404);

        $sp->update(['ical_url' => $request->ical_url ?: null]);

        return response()->json(['success' => true]);
    }

    protected function spSyncIcalNow(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->isServiceProvider()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $sp = ServiceProvider::where('user_id', $user->id)->first();
        if (!$sp) return response()->json(['error' => 'No provider found'], 404);
        if (!$sp->ical_url) return response()->json(['error' => 'No iCal URL configured'], 422);

        try {
            $syncService = new \App\Services\IcalSyncService();
            $result = $syncService->syncProvider($sp);
            return response()->json(['success' => true, 'synced' => $result]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }

    // ===========================
    // SP AVAILABILITY (Admin)
    // ===========================

    protected function adminGetSpCalendar(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->isHct()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $sp = ServiceProvider::findOrFail($request->service_provider_id);
        $year = (int) ($request->year ?: now()->year);
        $month = (int) ($request->month ?: now()->month);

        $service = new SpAvailabilityService();
        $calendar = $service->getMonthCalendar($sp->id, $year, $month);

        return response()->json([
            'calendar' => $calendar,
            'provider_name' => $sp->name,
            'ical_url' => $sp->ical_url,
            'ical_last_synced_at' => $sp->ical_last_synced_at?->format('d M Y H:i'),
        ]);
    }

    protected function adminSpBlockDates(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->isHct()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $sp = ServiceProvider::findOrFail($request->service_provider_id);
        $dates = $request->input('dates', []);
        if (empty($dates)) return response()->json(['error' => 'No dates provided'], 422);

        $service = new SpAvailabilityService();
        $count = $service->blockDates($sp->id, $dates, $request->notes);

        return response()->json(['success' => true, 'blocked' => $count]);
    }

    protected function adminSpUnblockDates(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->isHct()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $sp = ServiceProvider::findOrFail($request->service_provider_id);
        $dates = $request->input('dates', []);
        if (empty($dates)) return response()->json(['error' => 'No dates provided'], 422);

        $service = new SpAvailabilityService();
        $count = $service->unblockDates($sp->id, $dates);

        return response()->json(['success' => true, 'unblocked' => $count]);
    }
}
