<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
use App\Services\RazorpayService;
use App\Mail\WelcomeEmail;
use App\Mail\BookingConfirmationEmail;
use App\Mail\PaymentReceivedEmail;
use App\Mail\SpApplicationReceivedEmail;
use App\Mail\SpApplicationApprovedEmail;
use App\Mail\ProfileUpdatedEmail;
use App\Mail\PasswordChangedEmail;
use App\Mail\PasswordResetEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

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
            'adults' => 2,
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
            'adults' => 2,
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
        $paceMultiplier = match ($guestData['travel_pace'] ?? '') {
            'Relaxed' => 0.9,
            'Moderate' => 1.0,
            'Active' => 1.15,
            'Intensive' => 1.3,
            default => 1.0,
        };
        $budgetMultiplier = match ($guestData['budget_sensitivity'] ?? '') {
            'Budget-friendly' => 0.85,
            'Mid-range' => 1.0,
            'Premium' => 1.25,
            'No Limit' => 1.5,
            default => 1.0,
        };

        $numNights = max($numDays - 1, 0);
        $transportCost = round($baseTransport * $numDays * $vehicleMultiplier);
        $accommodationCost = round($baseAccommodation * $numNights * $adults * $accomMultiplier);
        $guideCost = round($baseGuide * $numDays * $guideMultiplier);

        // Pace scales activity-driven costs; budget scales overall trip base cost
        $activityCost = (int) round($activityCost * $paceMultiplier);
        $guideCost = (int) round($guideCost * $paceMultiplier);

        $transportCost = (int) round($transportCost * $budgetMultiplier);
        $accommodationCost = (int) round($accommodationCost * $budgetMultiplier);
        $guideCost = (int) round($guideCost * $budgetMultiplier);
        $activityCost = (int) round($activityCost * $budgetMultiplier);

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
            'vehicle_multiplier' => $vehicleMultiplier,
            'accommodation_multiplier' => $accomMultiplier,
            'guide_multiplier' => $guideMultiplier,
            'pace_multiplier' => $paceMultiplier,
            'budget_multiplier' => $budgetMultiplier,
            'gst_percent' => $gstPercent,
            'adults' => $adults,
            'children' => $guestData['children'] ?? 0,
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

            // Skip empty activity days, but keep arrival/departure/rest/travel/free days
            $dayType = $day['day_type'] ?? 'activity';
            if (empty($dayExperiences) && empty($day['description']) && !in_array($dayType, ['arrival', 'departure', 'rest', 'travel', 'free'])) continue;
            $dayNum++;

            $days[] = [
                'id' => $i + 1, // raw itinerary index (1-based) for correct removal
                'day_number' => $dayNum,
                'title' => $day['title'] ?? 'Day ' . $dayNum,
                'description' => $day['description'] ?? null,
                'day_type' => $dayType,
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
        // Default timeout of 20s for faster failure instead of hanging
        $defaultTimeout = 20;

        $gemini = app(GeminiService::class);
        if ($gemini->isAvailable()) {
            $geminiOpts = $options;
            $geminiOpts['timeout'] = $fastTimeout ?: ($options['timeout'] ?? $defaultTimeout);
            $response = $gemini->chat($messages, $geminiOpts);
            if ($response) return $response;
        }

        $groq = app(GroqService::class);
        if ($groq->isAvailable()) {
            $groqOpts = $options;
            if ($fastTimeout) $groqOpts['timeout'] = $fastTimeout;
            $response = $groq->chat($messages, $groqOpts);
            if ($response) return $response;
            // Skip retries — daily rate limits (TPD) won't reset in seconds
        }

        $ollama = app(OllamaService::class);
        if (!$ollama->isAvailable()) {
            \Log::warning('Ollama not available, all AI providers failed');
            return null;
        }

        // Minimal prompt + short history for local model speed
        $ollamaMessages = [
            ['role' => 'system', 'content' => 'You are a helpful travel assistant for HECO. Help plan eco-friendly trips. Be concise.']
        ];
        // Only keep last 2 user/assistant exchanges
        $nonSystem = array_filter($messages, fn($m) => $m['role'] !== 'system');
        $ollamaMessages = array_merge($ollamaMessages, array_slice(array_values($nonSystem), -3));

        $ollamaOpts = $options;
        $ollamaOpts['max_tokens'] = 256;
        $ollamaOpts['timeout'] = 90;

        \Log::info('Calling Ollama as fallback');
        $result = $ollama->chat($ollamaMessages, $ollamaOpts['model'] ?? null, $ollamaOpts);
        if (!$result) {
            \Log::warning('Ollama chat returned null');
        }
        return $result;
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
            if ($request->has('upload_profile_photo')) {
                return $this->uploadProfilePhoto($request);
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
            if ($request->has('create_razorpay_order')) {
                return $this->createRazorpayOrder($request);
            }
            if ($request->has('log_razorpay_failure')) {
                return $this->logRazorpayFailure($request);
            }
            if ($request->has('verify_razorpay_payment')) {
                return $this->verifyRazorpayPayment($request);
            }
            if ($request->has('get_traveller_payment_history')) {
                return $this->getTravellerPaymentHistory($request);
            }
            if ($request->has('get_trip_impact')) {
                return $this->getTripImpact($request);
            }
            if ($request->has('request_support')) {
                return $this->requestSupport($request);
            }
            if ($request->has('subscribe_newsletter')) {
                return $this->subscribeNewsletter($request);
            }
            if ($request->has('get_user_trips')) {
                return $this->getUserTrips($request);
            }
            if ($request->has('reopen_trip')) {
                return $this->reopenTrip($request);
            }
            if ($request->has('confirm_trip')) {
                return $this->confirmTrip($request);
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
            if ($request->has('delete_system_list_item')) {
                return $this->deleteSystemListItem($request);
            }
            if ($request->has('reset_hct_user_password')) {
                return $this->resetHctUserPassword($request);
            }
            if ($request->has('get_ai_prompts')) {
                return $this->getAiPrompts($request);
            }
            if ($request->has('save_ai_prompt')) {
                return $this->saveAiPrompt($request);
            }
            if ($request->has('delete_ai_prompt')) {
                return $this->deleteAiPrompt($request);
            }
            if ($request->has('get_activity_logs')) {
                return $this->getActivityLogs($request);
            }
            if ($request->has('get_sp_pricing')) {
                return $this->getSpPricing($request);
            }
            if ($request->has('save_sp_pricing')) {
                return $this->saveSpPricing($request);
            }
            if ($request->has('delete_sp_pricing')) {
                return $this->deleteSpPricing($request);
            }
            if ($request->has('get_pending_pricing')) {
                return $this->getPendingPricing($request);
            }
            if ($request->has('approve_pricing')) {
                return $this->approvePricing($request);
            }
            if ($request->has('reject_pricing')) {
                return $this->rejectPricing($request);
            }
            if ($request->has('get_room_availability')) {
                return $this->getRoomAvailability($request);
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
            if ($request->has('create_sp_payment')) {
                return $this->createSpPayment($request);
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
            if ($request->has('remove_provider')) {
                return $this->removeProvider($request);
            }
            if ($request->has('restore_provider')) {
                return $this->restoreProvider($request);
            }
            if ($request->has('permanently_delete_provider')) {
                return $this->permanentlyDeleteProvider($request);
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
            if ($request->has('bulk_delete_regions')) {
                return $this->bulkDeleteRegions($request);
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
            if ($request->has('bulk_delete_currencies')) {
                return $this->bulkDeleteCurrencies($request);
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
            if ($request->has('bulk_delete_experiences')) {
                return $this->bulkDeleteExperiences($request);
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
            if ($request->has('bulk_delete_regenerative_projects')) {
                return $this->bulkDeleteRegenerativeProjects($request);
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
            if ($request->has('update_sp_profile')) {
                return $this->updateSpProfile($request);
            }
            if ($request->has('get_sp_assigned_trips')) {
                return $this->getSpAssignedTrips($request);
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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Not found'], 404);
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

            // Sync guest trip directly into DB (don't rely on separate AJAX call)
            $syncedTripId = null;
            if ($user->isTraveller() && !empty($guestTrip['experience_ids'] ?? [])) {
                $syncedTripId = $this->syncGuestTripToDb($user, $guestTrip, $guestChat ?: []);
            }
            session()->forget(['guest_chat', 'guest_trip']);

            // Check if traveller has any planned journey
            $hasTrip = $syncedTripId || ($user->isTraveller() && $user->trips()
                ->whereIn('status', ['not_confirmed'])
                ->where(fn($q) => $q->whereHas('selectedExperiences')->orWhereHas('tripDays'))
                ->exists());

            $redirect = match(true) {
                $user->isHct() => '//' . config('app.admin_domain') . '/dashboard',
                $user->isServiceProvider() => "/sp/dashboard",
                $syncedTripId !== null => "/home?trip_id={$syncedTripId}&tab=journey",
                $hasTrip => "/home?tab=journey",
                default => "/home",
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
            "phone" => "nullable|string|max:20",
            "address1" => "nullable|string|max:500",
            "address2" => "nullable|string|max:500",
            "city" => "nullable|string|max:100",
            "state" => "nullable|string|max:100",
            "country" => "nullable|string|in:" . implode(',', config('countries.list')),
            "postal_code" => "nullable|string|max:20",
            "gender" => "nullable|in:male,female,other,prefer_not_to_say",
            "date_of_birth" => "nullable|date|before:today",
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
            "mobile" => $request->phone,
            "address1" => $request->address1,
            "address2" => $request->address2,
            "city" => $request->city,
            "state" => $request->state,
            "country" => $request->country,
            "postal_code" => $request->postal_code,
            "gender" => $request->gender,
            "date_of_birth" => $request->date_of_birth,
        ]);

        $this->sendMail($user->email, new WelcomeEmail($user->full_name, url('/home')), 'welcome:' . $user->id);

        Auth::login($user);

        // Sync guest trip directly into DB
        $syncedTripId = null;
        if (!empty($guestTrip['experience_ids'] ?? [])) {
            $syncedTripId = $this->syncGuestTripToDb($user, $guestTrip, $guestChat ?: []);
        }
        session()->forget(['guest_chat', 'guest_trip']);

        $redirect = $syncedTripId ? "/home?trip_id={$syncedTripId}&tab=journey" : "/home";
        return response()->json(["success" => true, "redirect" => $redirect, "trip_id" => $syncedTripId]);
    }

    protected function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            "full_name" => "sometimes|required|string|max:255",
            "mobile" => "nullable|string|max:20",
            "address1" => "nullable|string|max:500",
            "address2" => "nullable|string|max:500",
            "city" => "nullable|string|max:100",
            "state" => "nullable|string|max:100",
            "country" => "nullable|string|in:" . implode(',', config('countries.list')),
            "postal_code" => "nullable|string|max:20",
            "gender" => "nullable|in:male,female,other,prefer_not_to_say",
            "date_of_birth" => "nullable|date|before:today",
            "newsletter_optin" => "nullable|boolean",
            "portal_notify_optin" => "nullable|boolean",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $fieldLabels = [
            'full_name' => 'Full name',
            'mobile' => 'Mobile',
            'address1' => 'Address line 1',
            'address2' => 'Address line 2',
            'city' => 'City',
            'state' => 'State',
            'country' => 'Country',
            'postal_code' => 'Postal code',
            'gender' => 'Gender',
            'date_of_birth' => 'Date of birth',
            'newsletter_optin' => 'Newsletter subscription',
            'portal_notify_optin' => 'Portal notifications',
        ];
        $incoming = $request->only(array_keys($fieldLabels));
        // Normalise the checkbox-style fields to real booleans before storing.
        foreach (['newsletter_optin', 'portal_notify_optin'] as $boolField) {
            if (array_key_exists($boolField, $incoming)) {
                $incoming[$boolField] = $request->boolean($boolField);
            }
        }
        $changes = [];
        foreach ($incoming as $field => $newValue) {
            $current = $user->{$field};
            if ($field === 'date_of_birth' && $current) {
                $current = $current->format('Y-m-d');
            }
            if (in_array($field, ['newsletter_optin', 'portal_notify_optin'], true)) {
                if ((bool) $current !== (bool) $newValue) {
                    $changes[$fieldLabels[$field]] = $newValue ? 'On' : 'Off';
                }
                continue;
            }
            if ((string) ($current ?? '') !== (string) ($newValue ?? '')) {
                $changes[$fieldLabels[$field]] = $newValue;
            }
        }

        $user->update($incoming);

        if (!empty($changes) && $user->email) {
            $this->sendMail(
                $user->email,
                new ProfileUpdatedEmail($user->full_name ?: 'there', $changes, now()->format('d M Y, h:i A')),
                'profile_updated:' . $user->id
            );
        }

        return response()->json(["success" => true, "message" => "Profile updated"]);
    }

    protected function uploadProfilePhoto(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(["error" => "Please log in."], 401);
        }

        $validator = Validator::make($request->all(), [
            "profile_photo" => "required|file|mimes:jpg,jpeg,png,webp|max:4096",
        ], [
            "profile_photo.mimes" => "Please choose a JPG, PNG or WEBP image.",
            "profile_photo.max"   => "The image must be 4 MB or smaller.",
            "profile_photo.required" => "Please choose an image to upload.",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $file = $request->file("profile_photo");
        if (!$file->isValid() || !str_starts_with((string) $file->getMimeType(), 'image/')) {
            return response()->json(["error" => "That file does not look like a valid image."], 422);
        }

        $path = \App\Services\ImageUploadService::storeUploadedImage($file, 'users', 512);
        if (!$path) {
            return response()->json(["error" => "Could not process the image. Please try a different file."], 422);
        }

        // Remove the previous locally-stored photo (never touch remote OAuth URLs).
        $previous = $user->avatar;
        $user->update(["avatar" => $path]);
        if ($previous && $previous !== $path) {
            \App\Services\ImageUploadService::deleteLocal($previous);
        }

        return response()->json([
            "success"  => true,
            "message"  => "Profile photo updated",
            "avatar"   => $path,
        ]);
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

        if ($user->email) {
            $this->sendMail(
                $user->email,
                new PasswordChangedEmail($user->full_name ?: 'there', now()->format('d M Y, h:i A')),
                'password_changed:' . $user->id
            );
        }

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

        // One review per user per experience — update the existing one rather than 500 on the unique index.
        $review = Review::updateOrCreate(
            ['user_id' => Auth::id(), 'experience_id' => $request->experience_id],
            ['rating' => $request->rating, 'title' => $request->title, 'body' => $request->body]
        );

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

        // Build conversation history. Keep only the last few turns — Groq's free tier
        // is capped at 6000 tokens/minute and chat history is the biggest variable cost.
        if ($isGuest) {
            $guestChat = session("guest_chat", []);
            $guestChat[] = ["role" => "user", "content" => $request->message];
            if (count($guestChat) > 8) {
                $guestChat = array_slice($guestChat, -8);
            }
            $history = $guestChat;
            $gt = $this->guestTrip();
            $selectedExpIds = $gt['experience_ids'] ?? [];
            // Build region anchor points from selected experiences
            $regionAnchors = [];
            if (!empty($selectedExpIds)) {
                $regionAnchors = Region::whereHas('experiences', fn($q) => $q->whereIn('id', $selectedExpIds))
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
                "selected_count" => count($selectedExpIds),
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
                    "adults" => 2,
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
                ->orderByDesc("created_at")
                ->limit(8)
                ->get()
                ->reverse()
                ->values()
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
                "selected_count" => count($selectedExpIds),
            ]);
            $userName = $user->full_name ?? "Traveller";
        }

        // Three-stage catalog scoping (keeps tokens bounded while preventing
        // the AI from hallucinating experiences):
        //   A) Selections exist  → send only the selected experiences.
        //   B) No selections, but a region is resolvable from page filters or
        //      the trip's regions → send a lightweight catalog for THAT region.
        //   C) Nothing in scope → send an empty catalog AND a region list so
        //      the AI asks the traveller which region first instead of inventing.
        $expCols = ['id', 'name', 'type', 'region_id', 'duration_type', 'duration_days', 'difficulty_level', 'base_cost_per_person', 'available_months'];
        $expMap = fn($e) => [
            'id' => $e->id,
            'name' => $e->name,
            'type' => $e->type,
            'region' => $e->region->name ?? '',
            'region_id' => $e->region_id,
            'duration' => $e->duration_type === 'multi_day' ? ($e->duration_days ?? 1) . 'd' : '1d',
            'difficulty' => $e->difficulty_level,
            'price' => $e->base_cost_per_person,
            'months' => $e->available_months,
        ];

        $activeRegionId = null;
        $availableRegions = [];

        if (!empty($selectedExpIds)) {
            // (A) Send the selected experiences as-is.
            $experiencesJson = Experience::whereIn('id', $selectedExpIds)
                ->select($expCols)->with('region:id,name,continent,country')
                ->get()->map($expMap)->toJson();
            $activeRegionId = (int) Experience::whereIn('id', $selectedExpIds)->value('region_id') ?: null;
        } else {
            // Resolve an active region from (in priority order): the discover-tab
            // filter the user posted with this request, the trip's tripRegions
            // table, or the landing-preferences session bag.
            $currentFilters = $request->get('current_filters') ? json_decode($request->get('current_filters'), true) : [];
            if (!empty($currentFilters['region_id'])) {
                $activeRegionId = (int) $currentFilters['region_id'];
            } elseif ($trip && $trip->tripRegions()->exists()) {
                $activeRegionId = (int) $trip->tripRegions()->value('region_id');
            } elseif (!empty(session('landing_preferences')['region_id'] ?? null)) {
                $activeRegionId = (int) session('landing_preferences')['region_id'];
            }

            if ($activeRegionId) {
                // (B) Region is in scope — send only that region's active experiences.
                $experiencesJson = Experience::where('region_id', $activeRegionId)
                    ->where('is_active', true)
                    ->select($expCols)->with('region:id,name,continent,country')
                    ->get()->map($expMap)->toJson();
            } else {
                // (C) No region in scope — empty experiences list, and a region list
                // so the AI can present options instead of inventing names.
                $experiencesJson = '[]';
                $availableRegions = Region::where('is_active', true)
                    ->select('id', 'name', 'continent', 'country')
                    ->orderBy('continent')->orderBy('country')->orderBy('name')
                    ->get()->map(fn($r) => [
                        'id' => $r->id, 'name' => $r->name,
                        'continent' => $r->continent, 'country' => $r->country,
                    ])->toArray();
            }
        }

        // Inject active region + available regions into the existing trip_context
        // JSON (already passed to the prompt) so the AI sees the single source of
        // truth without needing a new prompt placeholder.
        $tripContextArr = json_decode($tripContext, true) ?: [];
        $tripContextArr['active_region_id'] = $activeRegionId;
        $tripContextArr['available_regions'] = $availableRegions;

        // Inject stay options grounded to real SP room inventory + bookings.
        // Resolve dates from (priority order):
        //   1. trip.start_date / end_date (already saved on the trip)
        //   2. dates the user just typed in this message (regex-extracted) —
        //      so first-turn "from 15-09 to 17-09" still gets real rooms.
        // If still no dates, leave stay_options empty and the prompt rule
        // will instruct the AI to ask for dates instead of inventing.
        $tripContextArr['stay_options_for_dates'] = [];
        $startDate = $tripContextArr['start_date'] ?? null;
        $endDate = $tripContextArr['end_date'] ?? $startDate;
        if (!$startDate) {
            [$startDate, $endDate] = $this->extractDatesFromMessage($request->message ?? '') + [null, null];
            if ($startDate) {
                $tripContextArr['inferred_start_date'] = $startDate;
                $tripContextArr['inferred_end_date'] = $endDate ?: $startDate;
            }
        }
        if ($activeRegionId && $startDate) {
            try {
                $stayOptions = app(\App\Services\RoomAvailabilityService::class)
                    ->stayOptionsForRegion($activeRegionId, $startDate, $endDate ?: $startDate)
                    ->take(20) // keep the list bounded — AI doesn't need 100 rooms
                    ->values()
                    ->toArray();
                $tripContextArr['stay_options_for_dates'] = $stayOptions;
            } catch (\Throwable $e) {
                \Log::warning('chatWithAi: stay_options query failed: ' . $e->getMessage());
            }
        }

        $tripContext = json_encode($tripContextArr);

        $promptBuilder = app(PromptBuilderService::class);
        $promptData = $promptBuilder->build("traveller_chat", [
            "user_name" => $userName,
            "experiences_json" => $experiencesJson,
            "trip_context" => $tripContext,
        ]);

        $currentDateInstruction = "\n\nTODAY: " . now()->format('jS F Y') . ". Dates without a year = nearest future occurrence.";

        $formattingInstruction = "\n\nFORMATTING: Bold (**text**) all continent/country/region/experience names, dates, prices, durations. Every option in a list MUST be bolded.";

        $recommendIdInstruction = "\n\nRECOMMEND: When recommending experiences, append [RECOMMEND_IDS:1,5,12] at end (hidden from user).";

        $tripDetailsInstruction = "\n\nTRIP DETAILS: When traveller provides details, summarize & confirm first. After confirmation, append [TRIP_DETAILS:{\"key\":\"value\"}] (hidden). Keys: traveller_name (no confirm needed), start_location, end_location, start_date (YYYY-MM-DD), end_date, budget_notes, anchor_point, pickup_preference (private_taxi/local_transport), adults, children, infants (integers), accommodation_comfort (Cat A/B/C/D/E), vehicle_comfort (Local Transport / SUV (Bolero/Scorpio) / SUV (Innova/Crysta) / Premium (Fortuner/Similar) / Tempo Traveller), guide_preference (No Guide / Local Guide / English-speaking / Certified/Expert), travel_pace (Relaxed / Moderate / Active / Intensive), budget_sensitivity (Budget-friendly / Mid-range / Premium / No Limit).";

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

        $conversationFlowInstruction = "\n\nCONVERSATION FLOW (ask step by step, show options as bold lists):\n1. Name (guests only).\n2. Destination: ask Continent then Country then Region step by step. The ONLY valid regions are in CURRENT_TRIP_CONTEXT.available_regions (when that field is present). NEVER name a region or country not in that list. NEVER mention Nepal, Tibet, Bhutan, Pakistan, or any country that's not represented in available_regions.\n3. Experience type & difficulty preference.\n4. Travel date, group size, starting city — ask 2 at a time max.\n5. ABSOLUTE RULE on experience names + IDs:\n   • You may ONLY name an experience that appears (by exact name) in AVAILABLE EXPERIENCES.\n   • You may ONLY put an id in RECOMMEND_IDS or ADD_TO_TRIP that appears (by exact id) in AVAILABLE EXPERIENCES.\n   • If AVAILABLE EXPERIENCES is `[]` (empty), you have NO catalog yet. In that case your response must NOT contain ANY of: trek names, peak names, route names, sample itineraries, mountain names, fictional experience names, or RECOMMEND_IDS. Even if the traveller asks for specific trek suggestions, you must respond with: 'I'd love to suggest specific treks — first, let's pick a region so I show you only experiences we actually run.' Then present 3-5 region NAMES from CURRENT_TRIP_CONTEXT.available_regions (group by continent if helpful), ask the traveller to pick one, and emit [SET_FILTERS:{\"region_id\":N}] with the chosen region's id. Do NOT proceed to recommend treks/experiences until the next turn (when AVAILABLE EXPERIENCES will be populated).\n   • Famous Himalayan names you must NEVER mention unless they're literally in AVAILABLE EXPERIENCES by exact name: Annapurna, Everest, Manaslu, Markha, Pin Parvati, Hampta Pass, Roopkund, Kuari Pass, Kilimanjaro, Poon Hill, Tilicho.\n6. ABSOLUTE RULE on accommodation / stay suggestions:\n   • Recommend stays/rooms ONLY from CURRENT_TRIP_CONTEXT.stay_options_for_dates (this is the live inventory for the active region + trip dates).\n   • Never invent hotel names, room types, or per-night rates. Never say 'we have a charming guesthouse' unless it appears by exact name in stay_options_for_dates.\n   • If stay_options_for_dates is empty: either dates aren't set yet (ask the traveller for start/end date) OR the active region has no rooms available for those dates (say so and offer alternatives from available_regions). Never fabricate stays.\n   • When stay_options_for_dates has entries, list rooms as: '**[sp_name]** — [room_category] (₹[rate_per_night]/night [meal_plan]) · [rooms_available] left'.\n\nIf filters already selected (see CURRENT FILTERS), skip the region question. Only ask MISSING details. Single region per trip — all selections must come from the same region (active_region_id).\n\nSET_FILTERS: When traveller picks continent/country/region, append: [SET_FILTERS:{\"continent\":\"X\",\"country\":\"Y\",\"region_id\":N}] — include only chosen keys. Hidden from user." . $filterContext;

        $allInstructions = $currentDateInstruction . $formattingInstruction . $recommendIdInstruction . $tripDetailsInstruction . $addToTripInstruction . $confirmationRule . $conversationFlowInstruction;

        $messages = [];
        if ($promptData) {
            $messages[] = ["role" => "system", "content" => $promptData["system_prompt"] . $allInstructions];
        } else {
            $messages[] = ["role" => "system", "content" => "You are a helpful travel assistant for HECO (Himalayan Ecotourism Collective). Help travellers plan regenerative trips. Suggest experiences, help with itinerary planning, and answer questions about destinations. Be warm, knowledgeable, and encourage sustainable travel.\n\nThe traveller's currently selected experiences:\n" . $experiencesJson . $allInstructions];
        }

        $messages = array_merge($messages, $history);

        $aiResponse = $this->callAi($messages, [
            "temperature" => $promptData["temperature"] ?? 0.7,
            "max_tokens" => $promptData["max_tokens"] ?? 1500,
        ]);

        if (!$aiResponse || empty($aiResponse["content"])) {
            \Log::warning('AI chat: all providers failed', ['is_guest' => $isGuest, 'trip_id' => $trip?->id]);
        }

        $responseText = $aiResponse["content"] ?? "Our AI assistant is busy right now (rate limit). Please wait about a minute and try again — or use the controls on the right to update your trip directly.";

        // Parse SET_FILTERS tag
        $setFilters = null;
        if (preg_match('/\[SET_FILTERS:(\{.+?\})\]/s', $responseText, $filterMatch)) {
            $setFilters = json_decode($filterMatch[1], true) ?: null;
            $responseText = trim(preg_replace('/\s*\[SET_FILTERS:\{.+?\}\]/s', '', $responseText));
        }

        // Parse recommended experience IDs. Validate against real catalog so
        // fabricated IDs (which Gemini/Groq sometimes invent in discovery mode)
        // never reach the UI. Scope to the active region when one is set.
        $recommendedIds = [];
        if (preg_match('/\[RECOMMEND_IDS:([\d,]+)\]/', $responseText, $matches)) {
            $rawIds = array_map("intval", explode(",", $matches[1]));
            $responseText = trim(preg_replace('/\s*\[RECOMMEND_IDS:[\d,]+\]/', '', $responseText));
            if (!empty($rawIds)) {
                $q = Experience::where('is_active', true)->whereIn('id', $rawIds);
                if ($activeRegionId) {
                    $q->where('region_id', $activeRegionId);
                }
                $recommendedIds = $q->pluck('id')->all();
                if (count($recommendedIds) !== count($rawIds)) {
                    \Log::info('chatWithAi: dropped fabricated RECOMMEND_IDS', [
                        'raw' => $rawIds,
                        'kept' => $recommendedIds,
                        'active_region_id' => $activeRegionId,
                    ]);
                }
            }
        }

        // Parse trip details from AI response
        if (preg_match('/\[TRIP_DETAILS:(\{.+?\})\]/s', $responseText, $tdMatch)) {
            $extractedDetails = json_decode($tdMatch[1], true) ?: [];
            $responseText = trim(preg_replace('/\s*\[TRIP_DETAILS:\{.+?\}\]/s', '', $responseText));

            // Handle traveller name separately
            $travellerName = $extractedDetails['traveller_name'] ?? null;
            unset($extractedDetails['traveller_name']);

            $allowedKeys = ['start_location', 'end_location', 'start_date', 'end_date', 'budget_notes', 'anchor_point', 'pickup_preference', 'adults', 'children', 'infants', 'accommodation_comfort', 'vehicle_comfort', 'guide_preference', 'travel_pace', 'budget_sensitivity'];
            $extractedDetails = array_intersect_key($extractedDetails, array_flip($allowedKeys));

            // Convert empty date strings to null to avoid MySQL date format errors
            foreach (['start_date', 'end_date'] as $dateKey) {
                if (isset($extractedDetails[$dateKey]) && $extractedDetails[$dateKey] === '') {
                    $extractedDetails[$dateKey] = null;
                }
            }

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

        // Fallback: parse group size directly from user message if AI didn't extract it
        $userMsg = $request->message ?? '';
        if (preg_match('/(\d+)\s*adults?/i', $userMsg, $adultMatch)) {
            $parsedAdults = (int) $adultMatch[1];
            if ($parsedAdults > 0 && $parsedAdults <= 50) {
                $fallbackDetails = ['adults' => $parsedAdults];
                if (preg_match('/(\d+)\s*child(?:ren)?/i', $userMsg, $childMatch)) {
                    $fallbackDetails['children'] = (int) $childMatch[1];
                }
                if (preg_match('/(\d+)\s*infants?/i', $userMsg, $infantMatch)) {
                    $fallbackDetails['infants'] = (int) $infantMatch[1];
                }
                if ($isGuest) {
                    $guestData = $this->guestTrip();
                    foreach ($fallbackDetails as $k => $v) {
                        $guestData[$k] = $v;
                    }
                    $this->saveGuestTrip($guestData);
                } elseif ($trip) {
                    $trip->update($fallbackDetails);
                }
                // Merge into extractedDetails so frontend gets updated values
                if (!isset($extractedDetails)) $extractedDetails = [];
                $extractedDetails = array_merge($extractedDetails, $fallbackDetails);
                $detailsUpdated = true;
            }
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

        // Final safety net: strip any leftover control markers that slipped past
        // primary parsing (e.g. malformed JSON inside [TRIP_DETAILS:] would
        // otherwise leak the raw tag to the user).
        $responseText = preg_replace('/\[(?:TRIP_DETAILS|SET_FILTERS):\{.+?\}\]/s', '', $responseText);
        $responseText = preg_replace('/\[(?:RECOMMEND_IDS|ADD_TO_TRIP|REMOVE_FROM_TRIP):[\d,\s]*\]/', '', $responseText);
        $responseText = trim($responseText);

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
        return response()->json([
            "days" => $days,
            "start_date" => $trip->start_date?->toDateString(),
            "trip_status" => $trip->status,
            "trip_stage" => $trip->stage,
        ]);
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
        $validator = Validator::make($request->all(), ['experience_id' => 'required|integer|exists:experiences,id']);
        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid experience.'], 422);
        }
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

        // Keep the timeline in sync with the selected experiences so a 5-day
        // selection always renders 5 days (no stale state from a prior generate).
        app(ItineraryService::class)->rebuildFromExperiences($trip);

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

        // Reset group size if no experiences left
        if ($trip->selectedExperiences()->count() === 0) {
            $trip->update(['adults' => 0, 'children' => 0, 'infants' => 0]);
        }

        // Rebuild the timeline so day count tracks the remaining experiences.
        app(ItineraryService::class)->rebuildFromExperiences($trip);

        return response()->json(["success" => true, "trip_id" => $trip->id]);
    }

    protected function preferExperience(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(["error" => "Login required"], 401);
        }

        // No trip_id is sent only from the wishlist page, where the heart button is
        // always a REMOVE on items the user has already hearted. If we find records,
        // unheart them; if we don't, treat it as a no-op rather than falling through
        // and silently attaching the experience to the user's first trip.
        if (!$request->filled('trip_id') || $request->trip_id === 'guest') {
            $userTripIds = Trip::where('user_id', Auth::id())->pluck('id');
            $records = TripSelectedExperience::whereIn('trip_id', $userTripIds)
                ->where('experience_id', $request->experience_id)
                ->where('is_preferred', true)
                ->get();

            if ($records->isNotEmpty()) {
                foreach ($records as $rec) {
                    $rec->update(['is_preferred' => false]);
                }
                return response()->json(["success" => true, "is_preferred" => false]);
            }
            return response()->json(["success" => true, "is_preferred" => false]);
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

        // Rebuild so the day order on the timeline matches the new experience order.
        app(ItineraryService::class)->rebuildFromExperiences($trip);

        return response()->json(["success" => true]);
    }

    protected function updateGroupDetails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "adults" => "nullable|integer|min:1|max:50",
            "children" => "nullable|integer|min:0|max:50",
            "infants" => "nullable|integer|min:0|max:50",
            "traveller_origin" => "nullable|in:indian,foreigner",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $adults = max(1, (int) ($request->adults ?? 1));
        $children = max(0, (int) ($request->children ?? 0));
        $infants = max(0, (int) ($request->infants ?? 0));

        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $gt['adults'] = $adults;
            $gt['children'] = $children;
            $gt['infants'] = $infants;
            $this->saveGuestTrip($gt);
            return response()->json(["success" => true]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["error" => "Trip not found"], 404);

        $data = ["adults" => $adults, "children" => $children, "infants" => $infants];
        if ($request->filled("traveller_origin")) {
            $data["traveller_origin"] = $request->traveller_origin;
        }
        $trip->update($data);
        return response()->json(["success" => true]);
    }

    protected function updateTripStartDate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "start_date" => "nullable|date|after_or_equal:today",
        ], [
            "start_date.date" => "Please pick a valid date.",
            "start_date.after_or_equal" => "Trip start date cannot be in the past.",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

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
        $validator = Validator::make($request->all(), [
            "accommodation_comfort" => "nullable|string|max:100",
            "vehicle_comfort"       => "nullable|string|max:100",
            "guide_preference"      => "nullable|string|max:100",
            "travel_pace"           => "nullable|string|max:100",
            "budget_sensitivity"    => "nullable|string|max:100",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }
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
        $validator = Validator::make($request->all(), [
            "trip_name" => "required|string|min:2|max:120",
        ], [
            "trip_name.required" => "Please enter a trip name.",
            "trip_name.min"      => "The trip name must be at least 2 characters.",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        if (!Auth::check()) {
            $gt = $this->guestTrip();
            $gt['trip_name'] = $request->trip_name;
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
        $dayType = $request->input('day_type', 'rest');
        $validDayTypes = ['arrival', 'activity', 'rest', 'travel', 'departure', 'free'];
        if (!in_array($dayType, $validDayTypes)) $dayType = 'rest';

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

            $dayTypeLabels = ['rest' => 'Rest & Relax', 'travel' => 'Travel Day', 'free' => 'Explore Nearby', 'activity' => 'Activity Day', 'arrival' => 'Arrival Day', 'departure' => 'Departure Day'];
            $day = TripDay::create([
                "trip_id" => $trip->id,
                "day_number" => $afterDayNumber + 1,
                "sort_order" => $afterDayNumber,
                "title" => $dayTypeLabels[$dayType] ?? null,
                "description" => $dayNote,
                "day_type" => $dayType,
                "added_by" => "traveller",
            ]);
        } else {
            $maxDay = $trip->tripDays()->max("day_number") ?? 0;
            $dayTypeLabels = ['rest' => 'Rest & Relax', 'travel' => 'Travel Day', 'free' => 'Explore Nearby', 'activity' => 'Activity Day', 'arrival' => 'Arrival Day', 'departure' => 'Departure Day'];
            $day = TripDay::create([
                "trip_id" => $trip->id,
                "day_number" => $maxDay + 1,
                "sort_order" => $maxDay,
                "title" => $dayTypeLabels[$dayType] ?? null,
                "description" => $dayNote,
                "day_type" => $dayType,
                "added_by" => "traveller",
            ]);
        }

        // Recalculate dates for ALL days and update trip end_date
        if ($trip->start_date) {
            $trip->tripDays()->orderBy('day_number')->each(function ($d) use ($trip) {
                $d->update(['date' => $trip->start_date->copy()->addDays($d->day_number - 1)]);
            });
            $maxDay = $trip->tripDays()->max('day_number');
            $trip->update(['end_date' => $trip->start_date->copy()->addDays($maxDay - 1)]);
        }

        return response()->json(["success" => true, "day" => $day->fresh()]);
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
            $newDate = $trip->start_date ? $trip->start_date->copy()->addDays($i) : null;
            $day->update(["day_number" => $i + 1, "sort_order" => $i, "date" => $newDate]);
        }

        // Update trip end_date
        if ($trip->start_date) {
            $maxDay = $trip->tripDays()->max('day_number') ?: 0;
            $trip->update(['end_date' => $maxDay > 0 ? $trip->start_date->copy()->addDays($maxDay - 1) : $trip->start_date]);
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

        // Include payment balance for portal
        $totalPaid = $trip->travellerPayments()
            ->where('payment_status', 'paid')
            ->sum('amount');
        $pricing['total_paid'] = $totalPaid;
        $pricing['balance_due'] = max(0, ($pricing['final_price'] ?? 0) - $totalPaid);

        return response()->json(["success" => true, "pricing" => $pricing]);
    }

    protected function getTripImpact(Request $request): JsonResponse
    {
        if (!Auth::check() || $request->trip_id === 'guest' || !$request->filled('trip_id')) {
            return response()->json(["success" => true, "impacts" => [], "total_contribution" => 0]);
        }
        $trip = Trip::where("id", $request->trip_id)
            ->where("user_id", Auth::id())
            ->first();
        if (!$trip) {
            return response()->json(["success" => true, "impacts" => [], "total_contribution" => 0]);
        }

        $impact = app(ImpactCalculatorService::class)->calculateForTrip($trip);

        // Normalise the service's per-project rows into the flat shape the
        // homepage Impact tab expects (audit H1: key was `impact` not `impacts`,
        // and the field names didn't match).
        $impacts = [];
        foreach (($impact['projects'] ?? []) as $p) {
            $impacts[] = [
                'region_name'       => $p['region'] ?? '',
                'project_name'      => $p['project_name'] ?? 'Regenerative Project',
                'action_type'       => $p['action_type'] ?? null,
                'contribution'      => $p['contribution'] ?? 0,
                'impact_value'      => $p['impact_units'] ?? null,
                'impact_unit_label' => $p['impact_unit_label'] ?? null,
            ];
        }

        return response()->json([
            "success"            => true,
            "impacts"            => $impacts,
            "total_contribution" => $impact['total_contribution'] ?? 0,
        ]);
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

    protected function subscribeNewsletter(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "email" => "required|email|max:255",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $email = strtolower(trim($request->email));

        // If this email belongs to a registered user, flip their opt-in.
        $user = User::where("email", $email)->first();
        if ($user) {
            $user->update(["newsletter_optin" => true]);
        }

        ActivityLog::create([
            "user_id" => $user?->id,
            "action" => "newsletter_subscribe",
            "details" => $email,
            "ip_address" => $request->ip(),
        ]);

        return response()->json(["success" => true, "message" => "Thanks for subscribing!"]);
    }

    protected function getUserTrips(Request $request): JsonResponse
    {
        $user = Auth::user();
        $trips = Trip::where("user_id", $user->id)
            ->where("status", "!=", "cancelled")
            ->with(["regions", "tripRegions.region", "selectedExperiences.experience"])
            ->orderBy("updated_at", "desc")
            ->get();
        return response()->json(["trips" => $trips]);
    }

    protected function reopenTrip(Request $request): JsonResponse
    {
        $user = Auth::user();
        $trip = Trip::where("id", $request->trip_id)->where("user_id", $user->id)->first();
        if (!$trip) {
            return response()->json(["error" => "Trip not found"], 404);
        }
        // Mirrors eraseTrip: once a trip is confirmed/running/completed it's a real
        // booking. Reopening (status→not_confirmed) would break payment reconciliation
        // and SP coordination, so route the user through HCT instead.
        if ($trip->status !== 'not_confirmed') {
            return response()->json([
                "error" => "This trip can no longer be reopened. Confirmed trips must be modified by our team — please use Request Support.",
            ], 422);
        }
        $trip->update(["stage" => "open"]);
        return response()->json(["success" => true]);
    }

    protected function confirmTrip(Request $request): JsonResponse
    {
        $user = Auth::user();
        $trip = Trip::where("id", $request->trip_id)->where("user_id", $user->id)->first();
        if (!$trip) {
            return response()->json(["error" => "Trip not found"], 404);
        }
        if ($trip->status !== 'not_confirmed' || $trip->stage !== 'open') {
            return response()->json(["error" => "This trip is already confirmed or locked."], 422);
        }
        $hasItinerary = $trip->tripDays()->exists() || $trip->selectedExperiences()->exists();
        if (!$hasItinerary) {
            return response()->json(["error" => "Add at least one experience to your trip before confirming."], 422);
        }
        // Confirm the trip but keep the stage open — closing the stage is an
        // explicit HCT action (matches the C2 guard against silent downgrades).
        $trip->update(["status" => "confirmed"]);
        // Promote any held SP room bookings → confirmed.
        app(\App\Services\RoomAvailabilityService::class)->confirmForTrip($trip->id);
        return response()->json(["success" => true, "status" => "confirmed"]);
    }

    protected function eraseTrip(Request $request): JsonResponse
    {
        $user = Auth::user();
        $trip = Trip::where("id", $request->trip_id)->where("user_id", $user->id)->first();
        if (!$trip) {
            return response()->json(["error" => "Trip not found"], 404);
        }
        // Only draft (unpaid) trips can be erased by the traveller. Once a payment
        // has been received the trip is a real booking — cancellation must go
        // through HCT (refund, SP coordination, etc.) per MVP rules.
        if ($trip->status !== 'not_confirmed') {
            return response()->json([
                "error" => "This trip can no longer be erased. Confirmed trips must be cancelled by our team — please use Request Support.",
            ], 422);
        }
        $trip->update(["status" => "cancelled", "stage" => "closed"]);
        // Free up any held room bookings.
        app(\App\Services\RoomAvailabilityService::class)->releaseForTrip($trip->id);
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
            "start_date" => $gt['start_date'] ?: null,
            "end_date" => $gt['end_date'] ?: null,
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
            ->whereIn("status", ["not_confirmed"])
            ->orderBy("updated_at", "desc")
            ->first();

        if (!$trip) {
            $trip = Trip::create(array_merge($guestDetails, [
                "trip_id" => Trip::generateTripId(),
                "user_id" => $user->id,
                "status" => "not_confirmed",
                "stage" => "open",
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
            "start_date" => $gt['start_date'] ?: null,
            "end_date" => $gt['end_date'] ?: null,
            "anchor_point" => $gt['anchor_point'] ?? null,
            "pickup_preference" => $gt['pickup_preference'] ?? null,
            "accommodation_comfort" => $gt['accommodation_comfort'] ?? null,
            "vehicle_comfort" => $gt['vehicle_comfort'] ?? null,
            "guide_preference" => $gt['guide_preference'] ?? null,
            "travel_pace" => $gt['travel_pace'] ?? null,
            "budget_sensitivity" => $gt['budget_sensitivity'] ?? null,
        ];

        $trip = Trip::where("user_id", $user->id)
            ->whereIn("status", ["not_confirmed"])
            ->orderBy("updated_at", "desc")
            ->first();

        if (!$trip) {
            $trip = Trip::create(array_merge($guestDetails, [
                "trip_id" => Trip::generateTripId(),
                "user_id" => $user->id,
                "status" => "not_confirmed",
                "stage" => "open",
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
            // Sync group size from request if provided
            if ($request->filled('adults')) {
                $gt['adults'] = (int) $request->adults;
                $gt['children'] = (int) ($request->children ?? $gt['children'] ?? 0);
                $gt['infants'] = (int) ($request->infants ?? $gt['infants'] ?? 0);
                $this->saveGuestTrip($gt);
            }
            // Start date and group size are required
            if (empty($gt['start_date'])) {
                return response()->json(["error" => "Please set a start date for your trip before generating the itinerary."], 422);
            }
            if (empty($gt['adults']) || $gt['adults'] < 1) {
                return response()->json(["error" => "Please set the group size (adults) before generating the itinerary."], 422);
            }
            $expIds = $gt['experience_ids'] ?? [];
            if (empty($expIds)) {
                return response()->json(["error" => "Add experiences to your trip first"], 422);
            }
            $expModels = Experience::whereIn('id', $expIds)->with(['region', 'days'])->get()
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
            // Start date and group size are required
            if (!$trip->start_date) {
                return response()->json(["error" => "Please set a start date for your trip before generating the itinerary."], 422);
            }
            if (!$trip->adults || $trip->adults < 1) {
                return response()->json(["error" => "Please set the group size (adults) before generating the itinerary."], 422);
            }
            // Sync group size from request if provided
            if ($request->filled('adults')) {
                $trip->update([
                    'adults' => (int) $request->adults,
                    'children' => (int) ($request->children ?? $trip->children),
                    'infants' => (int) ($request->infants ?? $trip->infants),
                ]);
            }
            $trip->load(["selectedExperiences" => function ($q) { $q->orderBy('sort_order'); }, "selectedExperiences.experience.region", "selectedExperiences.experience.days"]);
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

        // Calculate duration and build day-to-experience mapping
        $dayMapping = [];
        $dayNum = 1;

        // Experience days only (no separate arrival/departure days)
        foreach ($experiences as $exp) {
            $expDays = ($exp["duration_type"] === "multi_day") ? ($exp["duration_days"] ?? 1) : 1;
            for ($d = 1; $d <= $expDays; $d++) {
                $dayMapping[] = [
                    "day" => $dayNum,
                    "day_type" => "activity",
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

        $dayMappingInstruction = "\n\nDAY-TO-EXPERIENCE MAPPING (follow this EXACTLY — do NOT add, remove, or reorder days):\n" . json_encode($dayMapping) . "\n\nYou MUST create exactly " . $totalDays . " days. Rules:\n- Activity days: MUST include the experience_id from the mapping. For multi-day experiences, write unique title/notes per day.\n- Include 'day_type' field in each day's JSON output (activity).";

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

        // Index experiences by ID for fast lookup while filling day details.
        $expById = $expModels->keyBy('id');

        // Trip-level service preferences seed the per-day services.
        if ($isGuest) {
            $accomComfort = $gt['accommodation_comfort'] ?? null;
            $vehicleComfort = $gt['vehicle_comfort'] ?? null;
            $guidePref = $gt['guide_preference'] ?? null;
        } else {
            $accomComfort = $trip->accommodation_comfort;
            $vehicleComfort = $trip->vehicle_comfort;
            $guidePref = $trip->guide_preference;
        }

        // Maps inclusion labels (from the Experience editor) to TripDayService rows.
        $inclusionToService = [
            'breakfast'      => ['type' => 'meal',          'desc' => 'Breakfast'],
            'lunch'          => ['type' => 'meal',          'desc' => 'Lunch'],
            'dinner'         => ['type' => 'meal',          'desc' => 'Dinner'],
            'snacks'         => ['type' => 'meal',          'desc' => 'Snacks'],
            'accommodation'  => ['type' => 'accommodation', 'desc' => 'Accommodation'],
            'guide'          => ['type' => 'guide',         'desc' => 'Guide'],
            'transport'      => ['type' => 'transport',     'desc' => 'Transport'],
        ];

        // Build deterministic itinerary from day mapping. Each day pulls its
        // title/description/inclusions from the matching ExperienceDay row
        // (filled in the Experience editor), falling back to phase-aware
        // defaults if no per-day data exists. AI is purely additive.
        $parsed = ["days" => []];
        foreach ($dayMapping as $idx => $dm) {
            $aiDay = $aiDays[$idx] ?? [];
            $expId = $dm["experience_id"];
            $exp = $expById->get($expId);
            $expName = $dm["experience_name"];
            $dayOfExp = $dm["day_of_experience"] ?? 1;
            $totalExpDays = $dm["total_experience_days"] ?? 1;
            $regionName = $exp?->region?->name;

            // Pull the matching ExperienceDay (Day N of the experience).
            $expDay = $exp?->days?->firstWhere('day_number', $dayOfExp);

            // Title — prefer the editor's day title, then generic.
            $genericTitle = $totalExpDays > 1
                ? $expName . " — Day " . $dayOfExp . " of " . $totalExpDays
                : $expName;
            $editorTitle = $expDay?->title ? ($expName . ' — ' . $expDay->title) : null;

            // Description — prefer the editor's per-day short description,
            // then phase phrasing built from experience.short_description.
            if ($totalExpDays > 1) {
                if ($dayOfExp === 1) {
                    $phase = "Begin your " . $expName . " journey";
                } elseif ($dayOfExp === $totalExpDays) {
                    $phase = "Conclude your " . $expName . " journey";
                } else {
                    $phase = "Continue your " . $expName . " journey";
                }
            } else {
                $phase = "Spend the day exploring " . $expName;
            }
            $genericDescription = $regionName ? ($phase . " in " . $regionName . ".") : ($phase . ".");
            if ($exp?->short_description) {
                $genericDescription .= " " . $exp->short_description;
            }
            $editorDescription = $expDay?->short_description ?: null;

            // Times — prefer the editor's per-day times, else 09:00–17:00.
            $startTime = $expDay?->start_time ?: "09:00";
            $endTime   = $expDay?->end_time   ?: "17:00";

            // Services — first from the day's inclusions list (these are
            // bundled into the experience price → cost 0, is_included true).
            $services = [];
            $coveredTypes = [];
            $inclusions = is_array($expDay?->inclusions) ? $expDay->inclusions : [];
            foreach ($inclusions as $inc) {
                $key = strtolower(trim((string) $inc));
                if (!isset($inclusionToService[$key])) continue;
                $map = $inclusionToService[$key];
                $services[] = [
                    "service_type" => $map['type'],
                    "description"  => $map['desc'],
                    "is_included"  => true,
                    "cost"         => 0,
                ];
                $coveredTypes[$map['type']] = true;
            }

            // Trip-preference services fill any gaps the inclusions don't cover
            // and that the experience itself doesn't bundle.
            if ($accomComfort && empty($coveredTypes['accommodation']) && empty($exp?->includes_accommodation)) {
                $services[] = [
                    "service_type" => "accommodation",
                    "description"  => $accomComfort . ($regionName ? " accommodation in " . $regionName : " accommodation"),
                    "is_included"  => true,
                    "cost"         => 0,
                ];
            }
            if ($vehicleComfort && $vehicleComfort !== 'Local Transport' && empty($coveredTypes['transport']) && empty($exp?->includes_transport)) {
                $services[] = [
                    "service_type" => "transport",
                    "description"  => $vehicleComfort,
                    "is_included"  => true,
                    "cost"         => 0,
                ];
            }
            if ($guidePref && $guidePref !== 'No Guide' && empty($coveredTypes['guide']) && empty($exp?->includes_guide)) {
                $services[] = [
                    "service_type" => "guide",
                    "description"  => $guidePref . " guide for " . $expName,
                    "is_included"  => true,
                    "cost"         => 0,
                ];
            }
            // AI-suggested services last (additive only).
            if (!empty($aiDay["services"]) && is_array($aiDay["services"])) {
                foreach ($aiDay["services"] as $aiSvc) {
                    $services[] = $aiSvc;
                }
            }

            $parsed["days"][] = [
                "title"       => $aiDay["title"] ?? $editorTitle ?? $genericTitle,
                "description" => $aiDay["description"] ?? $aiDay["notes"] ?? $editorDescription ?? $genericDescription,
                "notes"       => $aiDay["notes"] ?? null,
                "day_type"    => "activity",
                "experiences" => [[
                    "experience_id" => $expId,
                    "name"          => $expName,
                    "start_time"    => $aiDay["experiences"][0]["start_time"] ?? $startTime,
                    "end_time"      => $aiDay["experiences"][0]["end_time"]   ?? $endTime,
                    "notes"         => $aiDay["experiences"][0]["notes"]      ?? null,
                ]],
                "services" => $services,
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
                // Remove activity days with no experiences left (keep arrival/departure/rest/travel/free days)
                $parsed['days'] = array_values(array_filter($parsed['days'], function ($day) {
                    $dayType = $day['day_type'] ?? 'activity';
                    return !empty($day['experiences']) || in_array($dayType, ['arrival', 'departure', 'rest', 'travel', 'free']);
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
        $newStatus = $user->status === "active" ? "inactive" : "active";
        $user->update(["status" => $newStatus]);
        return response()->json(["success" => true, "status" => $newStatus]);
    }

    protected function getSystemLists(Request $request): JsonResponse
    {
        $type = $request->get("list_type", "service_type");
        $items = SystemList::where("list_type", $type)->orderBy("sort_order")->get();
        return response()->json(["items" => $items]);
    }

    protected function saveSystemListItem(Request $request): JsonResponse
    {
        $data = $request->only(["list_type", "name", "sort_order", "description"]);
        if ($request->has("is_active")) {
            $data["is_active"] = $request->boolean("is_active");
        }
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
        $item->update(["is_active" => !$item->is_active]);
        return response()->json(["success" => true, "is_active" => $item->is_active]);
    }

    protected function deleteSystemListItem(Request $request): JsonResponse
    {
        $ids = $request->input("ids");
        if (is_array($ids) && count($ids)) {
            SystemList::whereIn("id", $ids)->delete();
        } elseif ($request->filled("id")) {
            SystemList::where("id", $request->id)->delete();
        } else {
            return response()->json(["error" => "Nothing to delete"], 422);
        }
        return response()->json(["success" => true]);
    }

    protected function resetHctUserPassword(Request $request): JsonResponse
    {
        $user = User::whereIn("user_role", ["hct_admin", "hct_collaborator"])->findOrFail($request->user_id);
        $user->update(["password" => Str::random(40)]);

        try {
            $token = Password::createToken($user);
            $resetUrl = route("password.reset", ["token" => $token, "email" => $user->email]);
            $this->sendMail($user->email, new PasswordResetEmail($user->full_name ?: $user->email, $resetUrl), "hct_pw_reset:" . $user->id);
        } catch (\Throwable $e) {
            Log::error("HCT password reset email failed [" . $user->id . "]: " . $e->getMessage());
        }

        return response()->json(["success" => true]);
    }

    protected function getAiPrompts(Request $request): JsonResponse
    {
        $prompts = AiPrompt::orderBy("key")->get();
        return response()->json(["prompts" => $prompts]);
    }

    protected function saveAiPrompt(Request $request): JsonResponse
    {
        $data = $request->only([
            "name", "key", "system_prompt", "user_prompt_template",
            "model", "temperature", "max_tokens", "response_format", "notes",
        ]);
        if ($request->has("is_active")) {
            $data["is_active"] = $request->boolean("is_active");
        }

        if ($request->filled("id")) {
            $prompt = AiPrompt::findOrFail($request->id);
            $prompt->update($data);
        } else {
            $validator = Validator::make($data, [
                "name"                  => "required|string|max:255",
                "key"                   => "required|string|max:100|unique:ai_prompts,key",
                "system_prompt"         => "required|string",
                "user_prompt_template"  => "required|string",
            ]);
            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()], 422);
            }
            $prompt = AiPrompt::create($data);
        }
        return response()->json(["success" => true, "prompt" => $prompt]);
    }

    protected function deleteAiPrompt(Request $request): JsonResponse
    {
        $ids = $request->input("ids");
        if (is_array($ids) && count($ids)) {
            AiPrompt::whereIn("id", $ids)->delete();
        } elseif ($request->filled("id")) {
            AiPrompt::where("id", $request->id)->delete();
        } else {
            return response()->json(["error" => "Nothing to delete"], 422);
        }
        return response()->json(["success" => true]);
    }

    protected function getActivityLogs(Request $request): JsonResponse
    {
        $logs = ActivityLog::with("user")->orderByDesc("created_at")->paginate(30);

        // `details` is cast to array; surface a plain-text fallback for rows that
        // were written as a non-JSON string so the viewer always has something.
        $logs->getCollection()->transform(function ($log) {
            $raw = $log->getRawOriginal("details");
            $log->setAttribute("details_text", is_string($raw) ? $raw : null);
            return $log;
        });

        return response()->json($logs);
    }

    /**
     * Enforce ownership: HCT can read any provider; an approved SP can only
     * read its own. Returns the provider_id to use or a 403 JsonResponse.
     */
    protected function resolveSpPricingProviderId(Request $request): int|JsonResponse
    {
        $user = Auth::user();
        $requested = (int) $request->input('provider_id');

        if ($user && $user->isHct()) {
            return $requested ?: 0;
        }
        if ($user && $user->isServiceProvider()) {
            $sp = ServiceProvider::where('user_id', $user->id)->where('status', 'approved')->first();
            if (!$sp) return response()->json(['error' => 'Unauthorized'], 403);
            // SP can only act on its own pricing; ignore any provider_id they send.
            return $sp->id;
        }
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    protected function getSpPricing(Request $request): JsonResponse
    {
        $providerId = $this->resolveSpPricingProviderId($request);
        if ($providerId instanceof JsonResponse) return $providerId;

        $rows = SpPricing::where("service_provider_id", $providerId)
            ->orderBy("service_type")
            ->get();
        return response()->json(["rows" => $rows]);
    }

    protected function saveSpPricing(Request $request): JsonResponse
    {
        // Resolve and enforce ownership. SP can only save against own id.
        $providerId = $this->resolveSpPricingProviderId($request);
        if ($providerId instanceof JsonResponse) return $providerId;
        $request->merge(['provider_id' => $providerId]);

        // If editing an existing row, ensure it belongs to this provider.
        if ($request->filled('id')) {
            $existing = SpPricing::find($request->id);
            if (!$existing || (int) $existing->service_provider_id !== $providerId) {
                return response()->json(['error' => 'Not your pricing row.'], 403);
            }
        }

        // Per-service-type validation. The dynamic modal in the UI only shows
        // the relevant fields for each service_type, but server-side we still
        // enforce the right combination.
        $serviceType = $request->input('service_type');
        $baseRules = [
            "provider_id"  => "required|exists:service_providers,id",
            "service_type" => "required|in:accommodation,transport,guide,activity,other",
            "price"        => "required|numeric|min:0",
        ];

        $rulesByType = [
            'accommodation' => [
                // Each row represents a (comfort_tier × room_category) pair so
                // hotels can have different inventory + price for Cat A Single
                // vs Cat A Double, Cat B Single vs Cat C Single, etc. Both
                // fields required; the UI shows tier first but stores both.
                "comfort_tier"      => "required|string|max:80",
                "room_category"     => "required|string|max:100",
                "total_rooms"       => "required|integer|min:1|max:500",
                "default_occupancy" => "nullable|string|max:50",
                "meal_plan"         => "nullable|string|max:100",
                "unit"              => "nullable|string|max:50",
            ],
            'transport' => [
                "vehicle_type"      => "required|string|max:100",
                "vehicle_capacity"  => "nullable|integer|min:1|max:80",
                "driver_allowance"  => "nullable|numeric|min:0",
                "unit"              => "required|string|max:50",
            ],
            'guide' => [
                "specialties"       => "nullable|string|max:500",
                "unit"              => "required|string|max:50",
            ],
            'activity' => [
                "category"          => "nullable|string|max:100",
                "min_group"         => "nullable|integer|min:1|max:500",
                "max_group"         => "nullable|integer|min:1|max:500",
                "unit"              => "required|string|max:50",
            ],
            'other' => [
                "unit"              => "required|string|max:50",
            ],
        ];

        $rules = array_merge($baseRules, $rulesByType[$serviceType] ?? []);
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $data = [
            "service_provider_id" => $request->provider_id,
            "service_type"        => $serviceType,
            "category"            => $request->input("category") ?: null,
            "description"         => $request->input("description") ?: null,
            "unit"                => $request->input("unit") ?: null,
            "price"               => $request->price,
            "meal_plan"           => $request->input("meal_plan") ?: null,
            "vehicle_type"        => $request->input("vehicle_type") ?: null,
            "notes"               => $request->input("notes") ?: null,
            // new fields
            "room_category"       => $request->input("room_category") ?: null,
            "comfort_tier"        => $request->input("comfort_tier") ?: null,
            "total_rooms"         => $request->filled("total_rooms") ? (int) $request->total_rooms : null,
            "default_occupancy"   => $request->input("default_occupancy") ?: null,
            "vehicle_capacity"    => $request->filled("vehicle_capacity") ? (int) $request->vehicle_capacity : null,
            "driver_allowance"    => $request->filled("driver_allowance") ? (float) $request->driver_allowance : null,
            "min_group"           => $request->filled("min_group") ? (int) $request->min_group : null,
            "max_group"           => $request->filled("max_group") ? (int) $request->max_group : null,
            "specialties"         => $request->input("specialties") ?: null,
        ];
        if ($request->has("is_active")) {
            $data["is_active"] = $request->boolean("is_active");
        }

        // Approval workflow:
        //   - HCT admins save directly approved (no self-review needed).
        //   - SPs submit pending: NEW rows are created as pending+inactive;
        //     EDITS create a SEPARATE pending row pointing at the live one,
        //     leaving the live row untouched until admin approves.
        $user = Auth::user();
        $isAdmin = $user && $user->isHct();

        if ($request->filled("id")) {
            $existing = SpPricing::findOrFail($request->id);
            if ($isAdmin) {
                $row = $existing;
                $row->update($data);
            } else {
                // Drop any prior pending edit for this row so the SP doesn't
                // stack multiple pending changes on the same live row.
                SpPricing::where('pending_for_id', $existing->id)
                    ->where('approval_status', 'pending')
                    ->delete();
                $row = SpPricing::create(array_merge($data, [
                    'is_active'        => false,
                    'approval_status'  => 'pending',
                    'pending_for_id'   => $existing->id,
                    'submitted_at'     => now(),
                    'submitted_by'     => $user->id,
                ]));
            }
        } else {
            if ($isAdmin) {
                $row = SpPricing::create($data);
            } else {
                $row = SpPricing::create(array_merge($data, [
                    'is_active'        => false,
                    'approval_status'  => 'pending',
                    'submitted_at'     => now(),
                    'submitted_by'     => $user->id,
                ]));
            }
        }
        return response()->json(["success" => true, "row" => $row, "pending" => $row->approval_status === 'pending']);
    }

    /**
     * HCT admin: list every pending pricing row across all providers, with
     * the live row it would replace (for edits) so the admin sees the diff.
     */
    protected function getPendingPricing(Request $request): JsonResponse
    {
        if (!Auth::user() || !Auth::user()->isHct()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $rows = SpPricing::pending()
            ->with(['serviceProvider:id,name,provider_type', 'pendingFor', 'submitter:id,full_name,email'])
            ->orderBy('submitted_at', 'desc')
            ->get();
        return response()->json(['rows' => $rows]);
    }

    /**
     * HCT admin: approve a pending pricing row.
     *   - NEW row (pending_for_id null): flip status → approved + activate.
     *   - EDIT row: squash submitted fields into the live row, then delete
     *     the pending row. The live row's id (and any sp_room_bookings
     *     pointing at it) stays intact.
     */
    protected function approvePricing(Request $request): JsonResponse
    {
        if (!Auth::user() || !Auth::user()->isHct()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $pending = SpPricing::pending()->findOrFail($request->id);

        if ($pending->pending_for_id) {
            $target = SpPricing::find($pending->pending_for_id);
            if ($target) {
                // Copy the substantive fields the SP wanted to change.
                $target->update([
                    'service_type'      => $pending->service_type,
                    'category'          => $pending->category,
                    'description'       => $pending->description,
                    'unit'              => $pending->unit,
                    'price'             => $pending->price,
                    'meal_plan'         => $pending->meal_plan,
                    'vehicle_type'      => $pending->vehicle_type,
                    'room_category'     => $pending->room_category,
                    'comfort_tier'      => $pending->comfort_tier,
                    'total_rooms'       => $pending->total_rooms,
                    'default_occupancy' => $pending->default_occupancy,
                    'vehicle_capacity'  => $pending->vehicle_capacity,
                    'driver_allowance'  => $pending->driver_allowance,
                    'min_group'         => $pending->min_group,
                    'max_group'         => $pending->max_group,
                    'specialties'       => $pending->specialties,
                    'approved_at'       => now(),
                    'approved_by'       => Auth::id(),
                ]);
            }
            $pending->delete();
            return response()->json(['success' => true, 'mode' => 'edit_merged', 'row_id' => $target?->id]);
        }

        // New row → activate in place.
        $pending->update([
            'approval_status' => 'approved',
            'is_active'       => true,
            'approved_at'     => now(),
            'approved_by'     => Auth::id(),
        ]);
        return response()->json(['success' => true, 'mode' => 'created', 'row_id' => $pending->id]);
    }

    /** HCT admin: reject a pending pricing row with a reason. */
    protected function rejectPricing(Request $request): JsonResponse
    {
        if (!Auth::user() || !Auth::user()->isHct()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $request->validate(['id' => 'required|integer', 'reason' => 'nullable|string|max:500']);
        $pending = SpPricing::pending()->findOrFail($request->id);
        $pending->update([
            'approval_status'   => 'rejected',
            'is_active'         => false,
            'approved_at'       => now(),
            'approved_by'       => Auth::id(),
            'rejection_reason'  => $request->input('reason'),
        ]);
        return response()->json(['success' => true]);
    }

    protected function deleteSpPricing(Request $request): JsonResponse
    {
        // Resolve owning provider; SPs can only delete their own rows.
        $providerId = $this->resolveSpPricingProviderId($request);
        if ($providerId instanceof JsonResponse) return $providerId;

        $ids = $request->input("ids");
        if (is_array($ids) && count($ids)) {
            SpPricing::whereIn("id", $ids)
                ->where("service_provider_id", $providerId)
                ->delete();
        } elseif ($request->filled("id")) {
            SpPricing::where("id", $request->id)
                ->where("service_provider_id", $providerId)
                ->delete();
        } else {
            return response()->json(["error" => "Nothing to delete"], 422);
        }
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
            ->orderByDesc("created_at")
            ->limit(20)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($m) => ["role" => $m->role, "content" => $m->content])
            ->toArray();

        // Slim trip summary — avoid $trip->toJson() with deep relations (was ~20k chars per call).
        $trip->load([
            'selectedExperiences:id,trip_id,experience_id,sort_order',
            'selectedExperiences.experience:id,name,type,region_id,duration_type,duration_days,difficulty_level,base_cost_per_person',
            'selectedExperiences.experience.region:id,name',
            'tripRegions.region:id,name',
            'user:id,full_name,email',
        ]);

        $tripSummary = [
            'trip_id' => $trip->trip_id,
            'status' => $trip->status,
            'stage' => $trip->stage,
            'adults' => $trip->adults,
            'children' => $trip->children,
            'infants' => $trip->infants,
            'start_date' => optional($trip->start_date)->toDateString(),
            'end_date' => optional($trip->end_date)->toDateString(),
            'start_location' => $trip->start_location,
            'end_location' => $trip->end_location,
            'anchor_point' => $trip->anchor_point,
            'pickup_preference' => $trip->pickup_preference,
            'accommodation_comfort' => $trip->accommodation_comfort,
            'vehicle_comfort' => $trip->vehicle_comfort,
            'guide_preference' => $trip->guide_preference,
            'total_days' => $trip->tripDays()->count(),
            'traveller' => [
                'name' => $trip->user->full_name ?? null,
                'email' => $trip->user->email ?? null,
            ],
            'regions' => $trip->tripRegions->pluck('region.name')->filter()->values(),
            'selected_experiences' => $trip->selectedExperiences->map(fn($se) => [
                'id' => $se->experience->id ?? null,
                'name' => $se->experience->name ?? null,
                'type' => $se->experience->type ?? null,
                'region' => $se->experience->region->name ?? null,
                'duration' => ($se->experience->duration_type ?? '') === 'multi_day'
                    ? ($se->experience->duration_days ?? 1) . 'd'
                    : '1d',
                'difficulty' => $se->experience->difficulty_level ?? null,
                'base_cost' => $se->experience->base_cost_per_person ?? null,
            ])->values(),
        ];

        $promptBuilder = app(PromptBuilderService::class);
        $promptData = $promptBuilder->build("hct_chat", [
            "trip_json" => json_encode($tripSummary),
        ]);

        $messages = [];

        if ($promptData) {
            $messages[] = ["role" => "system", "content" => $promptData["system_prompt"]];
        } else {
            $messages[] = ["role" => "system", "content" => "You are an AI assistant for the HCT (HECO Core Team) operations team. Help with trip planning, itinerary optimization, and operational decisions. Provide structured suggestions in JSON when asked about itinerary modifications."];
        }
        $messages = array_merge($messages, $history);

        $aiResponse = $this->callAi($messages, [
            "temperature" => $promptData["temperature"] ?? 0.7,
            "max_tokens" => $promptData["max_tokens"] ?? 1500,
        ]);
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

        // Empty assigned_hct_id from the "Unassigned" option means clear it.
        if (array_key_exists("assigned_hct_id", $data) && ($data["assigned_hct_id"] === "" || $data["assigned_hct_id"] === null)) {
            $data["assigned_hct_id"] = null;
        }

        if (isset($data["interaction_mode"]) && $data["interaction_mode"] !== "") {
            $data["last_interaction_date"] = now();
        } else {
            unset($data["interaction_mode"]);
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
        $query = Trip::with(["user", "regions"]);

        // Status filter — blank / "all" means no filter.
        $status = $request->get("status", "");
        if (!empty($status) && $status !== "all") {
            $allowed = ['not_confirmed', 'confirmed', 'running', 'completed', 'cancelled'];
            if (in_array($status, $allowed, true)) {
                $query->where("status", $status);
            }
        }
        if ($request->filled("date_from")) {
            $query->whereDate("start_date", ">=", $request->date_from);
        }
        if ($request->filled("date_to")) {
            $query->whereDate("start_date", "<=", $request->date_to);
        }
        // Dashboard widget passes within_days to scope to the next N days.
        if ($request->filled("within_days")) {
            $days = (int) $request->within_days;
            $query->whereNotNull("start_date")
                  ->whereDate("start_date", ">=", now()->toDateString())
                  ->whereDate("start_date", "<=", now()->addDays($days)->toDateString());
        }
        if ($request->filled("limit")) {
            $trips = $query->orderByRaw("start_date IS NULL, start_date ASC")->limit((int) $request->limit)->get();
            return response()->json([
                "trips" => $trips,
                "data" => $trips,
            ]);
        }

        // NULL start_date trips last; otherwise chronological.
        $trips = $query->orderByRaw("start_date IS NULL, start_date ASC")->paginate(20);
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
        $newStatus = $request->status;

        $allowed = ['not_confirmed', 'confirmed', 'running', 'completed', 'cancelled'];
        if (!in_array($newStatus, $allowed, true)) {
            return response()->json(["error" => "Invalid trip status."], 422);
        }

        // Guard: don't allow downgrading a trip that has already progressed
        // (confirmed / running / completed) back to 'not_confirmed' — that would
        // re-open a locked trip and orphan any payments against it. Cancelling a
        // progressed trip is still allowed.
        $progressed = ['confirmed', 'running', 'completed'];
        if ($newStatus === 'not_confirmed' && in_array($trip->status, $progressed, true)) {
            return response()->json([
                "error" => "Can't move a '{$trip->status}' trip back to 'not confirmed'. Cancel it instead if it shouldn't proceed.",
            ], 422);
        }

        $trip->update(["status" => $newStatus]);
        if (in_array($newStatus, ["completed", "cancelled"], true)) {
            $trip->update(["stage" => "closed"]);
        }

        // Keep the linked lead in sync when an admin confirms via /trips dropdown,
        // mirroring what LeadService::checkPaymentAndTransition does for paid trips
        // and what the leads-page "Mark Won" button does.
        if ($newStatus === 'confirmed' && $trip->lead && $trip->lead->stage === 'follow_up') {
            app(\App\Services\LeadService::class)->markWon($trip->lead);
        }

        // SP room bookings lifecycle:
        // - confirmed → flip held bookings to confirmed (rooms reserved hard)
        // - cancelled → release all active bookings (rooms freed)
        $room = app(\App\Services\RoomAvailabilityService::class);
        if ($newStatus === 'confirmed') {
            $room->confirmForTrip($trip->id);
        } elseif ($newStatus === 'cancelled') {
            $room->releaseForTrip($trip->id);
        }

        return response()->json(["success" => true]);
    }

    protected function getCalendarTrips(Request $request): JsonResponse
    {
        $month = (int) $request->get("month", now()->month);
        $year = (int) $request->get("year", now()->year);

        $monthStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $monthEnd = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        // Include any trip whose date range overlaps the displayed month, in any non-cancelled-only status.
        $trips = Trip::whereIn("status", ["not_confirmed", "confirmed", "running", "completed", "cancelled"])
            ->whereNotNull("start_date")
            ->whereNotNull("end_date")
            ->whereDate("start_date", "<=", $monthEnd)
            ->whereDate("end_date", ">=", $monthStart)
            ->with(["user", "tripRegions.region"])
            ->get();

        return response()->json(["trips" => $trips]);
    }

    protected function getSpPayments(Request $request): JsonResponse
    {
        $query = SpPayment::with(["trip", "serviceProvider", "entries"]);
        if ($request->filled("trip_id")) {
            $query->where("trip_id", $request->trip_id);
        }
        if ($request->filled("trip_search")) {
            $search = $request->trip_search;
            $query->whereHas("trip", function ($q) use ($search) {
                $q->where("trip_id", "like", "%{$search}%")
                  ->orWhere("trip_name", "like", "%{$search}%");
            });
        }
        $payments = $query->orderBy("created_at", "desc")->paginate(20);
        // Expose the HECO-T-… code as a separate field; leave the numeric trip_id FK intact.
        $payments->getCollection()->transform(function ($p) {
            $p->trip_code = $p->trip?->trip_id;
            return $p;
        });
        return response()->json($payments);
    }

    /**
     * Create a payable record (SpPayment) for a service provider against a trip.
     * This is the data source the SP-side "Payment Summary", the admin "/providers/{id}"
     * payment history and the admin "/payments" SP tab all read from. Payment entries
     * (actual disbursements) are then added against this record via addSpPaymentEntry().
     */
    protected function createSpPayment(Request $request): JsonResponse
    {
        if (!Auth::user()?->isHct()) {
            return response()->json(["error" => "Unauthorized"], 403);
        }

        $validator = Validator::make($request->all(), [
            "trip_id" => "required|exists:trips,id",
            "service_provider_id" => "required|exists:service_providers,id",
            "service_type" => "required|string|max:50",
            "amount_due" => "required|numeric|min:0",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $spPayment = SpPayment::create([
            "trip_id" => $request->trip_id,
            "service_provider_id" => $request->service_provider_id,
            "service_type" => $request->service_type,
            "amount_due" => $request->amount_due,
            "amount_paid" => 0,
            "balance" => $request->amount_due,
            "notes" => $request->notes,
        ]);

        return response()->json(["success" => true, "id" => $spPayment->id]);
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
        $trips = Trip::whereHas("travellerPayments")
            ->with(["user", "travellerPayments"])
            ->get()
            ->map(function ($trip) {
                $totalPaid = $trip->travellerPayments->where('payment_status', 'paid')->sum("amount");
                return [
                    "trip" => ["id" => $trip->id, "trip_id" => $trip->trip_id],
                    "user" => ["full_name" => $trip->user->full_name ?? '', "email" => $trip->user->email ?? ''],
                    "total_due" => $trip->final_price ?? 0,
                    "total_paid" => $totalPaid,
                    "balance" => ($trip->final_price ?? 0) - $totalPaid,
                    "status" => $trip->status,
                ];
            });

        return response()->json(["payments" => $trips]);
    }

    protected function getGstReport(Request $request): JsonResponse
    {
        $month = $request->get("month", now()->month);
        $year = $request->get("year", now()->year);

        $gstPercent = (float) Setting::getValue("gst_percent", 5);

        $trips = Trip::whereIn("status", ["confirmed", "running", "completed"])
            ->whereMonth("created_at", $month)
            ->whereYear("created_at", $year)
            ->with("user")
            ->orderBy("created_at")
            ->get()
            ->map(function ($t) use ($gstPercent) {
                $subtotal = (float) ($t->subtotal ?? 0);
                $gstAmount = (float) ($t->gst_amount ?? 0);
                $finalPrice = (float) ($t->final_price ?? 0);
                // Derive the effective rate per trip when possible; fall back to the global setting.
                $effectiveRate = $subtotal > 0 ? round($gstAmount / $subtotal * 100, 2) : $gstPercent;
                return [
                    "id" => $t->id,
                    "trip_id" => $t->trip_id,
                    "user" => [
                        "full_name" => $t->user->full_name ?? $t->user->email ?? "-",
                        "email" => $t->user->email ?? "",
                    ],
                    "start_date" => $t->start_date,
                    "end_date" => $t->end_date,
                    "subtotal" => $subtotal,
                    "gst_percent" => $effectiveRate,
                    "gst_amount" => $gstAmount,
                    "final_price" => $finalPrice,
                    "status" => $t->status,
                ];
            })->values();

        $totalRevenue = $trips->sum("final_price");
        $totalGst = $trips->sum("gst_amount");
        $netRevenue = $trips->sum("subtotal");

        return response()->json([
            "success" => true,
            "summary" => [
                "total_revenue" => $totalRevenue,
                "total_gst" => $totalGst,
                "net_revenue" => $netRevenue,
            ],
            "trips" => $trips,
        ]);
    }

    protected function getProviders(Request $request): JsonResponse
    {
        $query = ServiceProvider::with(["region", "lastUpdatedBy:id,full_name,email"]);

        // Status filter — blank means hide removed (default), "all" means
        // include removed too, anything else is honoured as-is.
        $status = $request->get("status", "");
        if (!empty($status) && $status !== "all") {
            $query->where("status", $status);
        } elseif (empty($status)) {
            $query->where("status", "!=", "removed");
        }
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
        $providers = $query->orderBy("name")->paginate(20);
        return response()->json($providers);
    }

    protected function editProvider(Request $request): JsonResponse
    {
        // Hard gate — status (and every other admin-controlled field) can only be
        // changed by an HCT user, even if the request comes through portal /ajax.
        if (!Auth::user()?->isHct()) {
            return response()->json(["error" => "Unauthorized"], 403);
        }
        $provider = ServiceProvider::findOrFail($request->provider_id);
        $wasApproved = $provider->status === 'approved';

        $data = $request->only([
            "name", "contact_person", "email", "phone_1", "phone_2",
            "address", "region_id", "provider_type",
            "bank_name", "bank_ifsc", "bank_account_name",
            "bank_account_number", "upi", "services_offered",
            "accommodation_categories", "vehicle_types", "guide_types", "activity_types",
            "notes", "status",
        ]);
        // Track approval timestamp when status flips to approved for the first time
        if (($data['status'] ?? null) === 'approved' && !$wasApproved) {
            $data['approved_at'] = now();
            $data['approved_by'] = Auth::id();
        }
        $data['last_updated_by'] = Auth::id();
        $data['last_updated_by_role'] = 'admin';
        $provider->update($data);

        // If status flipped to approved here (not just via the Provider Applications
        // tab), run the same side-effects as approveProvider: ensure the SP has a
        // User account and email them the set-password link. Without this, the SP
        // can't log in and never knows they were approved.
        if (($data['status'] ?? null) === 'approved' && !$wasApproved) {
            $this->finalizeApproval($provider->fresh());
        }

        return response()->json(["success" => true]);
    }

    /**
     * Side-effects of approving an SP — runs from approveProvider AND from
     * editProvider when status flips to approved.
     *
     * 1. Ensures the SP has a linked User account (creates one if missing,
     *    finds an existing one by email, or updates the role on an existing
     *    user with a different email-matching).
     * 2. Generates a password-reset token and emails the SP a set-password
     *    link via SpApplicationApprovedEmail.
     *
     * Idempotent — if the SP already has user_id, skips creation. If the
     * mail send fails, logs it and continues (non-fatal).
     */
    protected function finalizeApproval(ServiceProvider $provider): void
    {
        // 1. Ensure linked User exists.
        if (!$provider->user_id && $provider->email) {
            $user = User::where('email', $provider->email)->first();
            if (!$user) {
                $user = User::create([
                    'full_name' => $provider->name,
                    'email'     => $provider->email,
                    'password'  => Str::random(40),
                    'auth_type' => 'email',
                    'user_role' => $provider->provider_type,
                ]);
            } else {
                $user->update(['user_role' => $provider->provider_type]);
            }
            $provider->forceFill(['user_id' => $user->id])->save();
        }

        // 2. Email the SP a set-password link so they can log in.
        $user = $provider->user_id ? User::find($provider->user_id) : null;
        if (!$user || !$user->email) {
            Log::warning('finalizeApproval: SP has no linked user/email; skipping mail', ['provider_id' => $provider->id]);
            return;
        }

        $providerLabel = match ($provider->provider_type) {
            'hrp' => 'Himalayan Regenerative Partner (HRP)',
            'hlh' => 'Homestay Local Host (HLH)',
            'osp' => 'Other Service Provider (OSP)',
            default => 'Partner',
        };
        try {
            $token = Password::createToken($user);
            $setPasswordUrl = route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]);
            $contactName = $provider->contact_person ?: $provider->name;
            $this->sendMail(
                $user->email,
                new SpApplicationApprovedEmail($contactName, $providerLabel, $setPasswordUrl),
                'sp_approved:' . $provider->id
            );
        } catch (\Throwable $e) {
            Log::error('SP approval email failed [' . $provider->id . ']: ' . $e->getMessage());
        }
    }

    protected function updateSpProfile(Request $request): JsonResponse
    {
        [$provider, $err] = $this->resolveApprovedSp();
        if ($err) return $err;
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            "name" => "required|string|max:255",
            "contact_person" => "nullable|string|max:255",
            "email" => "nullable|email|max:255",
            "phone_1" => "nullable|string|max:30",
            "phone_2" => "nullable|string|max:30",
            "address" => "nullable|string|max:500",
            "bank_name" => "nullable|string|max:255",
            "bank_ifsc" => "nullable|string|max:20",
            "bank_account_name" => "nullable|string|max:255",
            "bank_account_number" => "nullable|string|max:30",
            "upi" => "nullable|string|max:100",
            "services_offered" => "nullable|array",
            "services_offered.*" => "string|max:100",
            "accommodation_categories" => "nullable|array",
            "accommodation_categories.*" => "string|max:100",
            "vehicle_types" => "nullable|array",
            "vehicle_types.*" => "string|max:100",
            "guide_types" => "nullable|array",
            "guide_types.*" => "string|max:100",
            "activity_types" => "nullable|array",
            "activity_types.*" => "string|max:100",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        // SP cannot change own status, approval, or audit fields
        $data = $request->only([
            "name", "contact_person", "email", "phone_1", "phone_2",
            "address", "bank_name", "bank_ifsc", "bank_account_name",
            "bank_account_number", "upi", "services_offered",
            "accommodation_categories", "vehicle_types", "guide_types", "activity_types",
        ]);
        $data['last_updated_by'] = $user->id;
        $data['last_updated_by_role'] = 'provider';
        $provider->update($data);
        return response()->json(["success" => true]);
    }

    /**
     * Trips the logged-in service provider is attached to. The primary link is
     * TripDayService.service_provider_id (per-day services); for HRPs the
     * tripRegions.hrp_id link is also folded in. Returns one row per trip with
     * the day numbers and the services the SP is providing.
     */
    protected function getSpAssignedTrips(Request $request): JsonResponse
    {
        [$provider, $err] = $this->resolveApprovedSp();
        if ($err) return $err;

        $tripsById = [];

        // 1) Per-day services assigned to this SP.
        $services = TripDayService::where('service_provider_id', $provider->id)
            ->with(['tripDay.trip'])
            ->get();
        foreach ($services as $svc) {
            $day = $svc->tripDay;
            $trip = $day?->trip;
            if (!$trip) {
                continue;
            }
            if (!isset($tripsById[$trip->id])) {
                $tripsById[$trip->id] = [
                    'id' => $trip->id,
                    'trip_id' => $trip->trip_id,
                    'trip_name' => $trip->trip_name,
                    'start_date' => $trip->start_date,
                    'end_date' => $trip->end_date,
                    'status' => $trip->status,
                    '_days' => [],
                    '_services' => [],
                ];
            }
            if ($day->day_number !== null) {
                $tripsById[$trip->id]['_days'][$day->day_number] = true;
            }
            $label = $svc->description ?: ucfirst((string) $svc->service_type);
            $tripsById[$trip->id]['_services'][$label] = true;
        }

        // 2) HRP-managed regions (if this provider is an HRP).
        if (strtolower((string) $provider->provider_type) === 'hrp') {
            $tripRegions = TripRegion::where('hrp_id', $provider->id)->with('trip')->get();
            foreach ($tripRegions as $tr) {
                $trip = $tr->trip;
                if (!$trip) {
                    continue;
                }
                if (!isset($tripsById[$trip->id])) {
                    $tripsById[$trip->id] = [
                        'id' => $trip->id,
                        'trip_id' => $trip->trip_id,
                        'trip_name' => $trip->trip_name,
                        'start_date' => $trip->start_date,
                        'end_date' => $trip->end_date,
                        'status' => $trip->status,
                        '_days' => [],
                        '_services' => [],
                    ];
                }
                $tripsById[$trip->id]['_services']['Regional Partner (HRP)'] = true;
            }
        }

        $trips = collect($tripsById)->map(function ($t) {
            $days = array_keys($t['_days']);
            sort($days);
            $services = array_keys($t['_services']);
            unset($t['_days'], $t['_services']);
            $t['days'] = $days ? implode(', ', $days) : '';
            $t['services'] = $services ? implode(', ', $services) : '';
            return $t;
        })->sortBy('start_date')->values();

        return response()->json(["trips" => $trips]);
    }

    protected function getProviderTrips(Request $request): JsonResponse
    {
        // Each row in the provider "Trip History" table is a service this SP is
        // attached to on some trip day (via TripDayService.service_provider_id).
        // The blade reads row.service_type and row.trip.{id,trip_id,status,...}.
        $services = TripDayService::where("service_provider_id", $request->provider_id)
            ->with(["tripDay.trip.user", "tripDay:id,trip_id,day_number,date"])
            ->get();

        $rows = $services->map(function ($svc) {
            $trip = optional(optional($svc->tripDay)->trip);
            if (!$trip || !$trip->id) {
                return null;
            }
            return [
                "service_type" => $svc->service_type,
                "description" => $svc->description,
                "day_number" => optional($svc->tripDay)->day_number,
                "date" => optional(optional($svc->tripDay)->date)?->toDateString(),
                "trip" => [
                    "id" => $trip->id,
                    "trip_id" => $trip->trip_id,
                    "trip_name" => $trip->trip_name,
                    "status" => $trip->status,
                    "start_date" => optional($trip->start_date)?->toDateString(),
                    "end_date" => optional($trip->end_date)?->toDateString(),
                    "traveller" => optional($trip->user)->full_name ?: optional($trip->user)->email,
                ],
            ];
        })->filter()->sortByDesc(fn($r) => $r["trip"]["start_date"] ?: "")->values();

        return response()->json(["trips" => $rows, "data" => $rows]);
    }

    protected function getProviderPaymentHistory(Request $request): JsonResponse
    {
        $payments = SpPayment::where("service_provider_id", $request->provider_id)
            ->with(["trip", "entries"])
            ->orderBy("created_at", "desc")
            ->get();
        return response()->json(["payments" => $payments]);
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
        $query = ServiceProvider::with("region");

        // Status filter — blank / "all" means no filter; defaults to "pending"
        // because this screen is the SP application inbox.
        $status = $request->get("status", "");
        if (!empty($status) && $status !== "all") {
            $query->where("status", $status);
        }

        $applications = $query->orderBy("created_at", "desc")->paginate(20);
        return response()->json($applications);
    }

    protected function approveProvider(Request $request): JsonResponse
    {
        $provider = ServiceProvider::findOrFail($request->provider_id);
        $wasApproved = $provider->status === 'approved';

        $provider->update([
            "status" => "approved",
            "approved_at" => $wasApproved ? $provider->approved_at : now(),
            "approved_by" => $wasApproved ? $provider->approved_by : Auth::id(),
        ]);

        // First-time approval → ensure linked user + send set-password email.
        if (!$wasApproved) {
            $this->finalizeApproval($provider->fresh());
        }

        return response()->json(["success" => true]);
    }

    protected function rejectProvider(Request $request): JsonResponse
    {
        $provider = ServiceProvider::findOrFail($request->provider_id);
        $provider->update(["status" => "rejected"]);
        return response()->json(["success" => true]);
    }

    /**
     * Soft-archive a service provider. HCT admin only.
     * Side effects:
     *   - provider.status        → 'removed'
     *   - linked user.status     → 'inactive' (so they can't log in)
     *   - sp_pricing.is_active   → false   (so trip-manager/traveller skip them)
     *   - sp_room_bookings.status → 'released' on any held/confirmed rows
     *
     * The provider row is kept (NOT hard-deleted) so historical trips,
     * payments, and references stay intact. Admin can flip back to approved
     * via restoreProvider().
     */
    protected function removeProvider(Request $request): JsonResponse
    {
        if (!Auth::user() || !Auth::user()->isHctAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $provider = ServiceProvider::findOrFail($request->provider_id);

        $provider->update([
            'status'             => 'removed',
            'last_updated_by'    => Auth::id(),
            'last_updated_by_role' => 'admin',
        ]);
        if ($provider->user_id) {
            \App\Models\User::where('id', $provider->user_id)->update(['status' => 'inactive']);
        }
        SpPricing::where('service_provider_id', $provider->id)->update(['is_active' => false]);
        // Release every active room booking against this SP's pricing rows.
        $pricingIds = SpPricing::where('service_provider_id', $provider->id)->pluck('id');
        if ($pricingIds->isNotEmpty()) {
            \App\Models\SpRoomBooking::whereIn('sp_pricing_id', $pricingIds)
                ->whereIn('status', ['held', 'confirmed'])
                ->update(['status' => 'released']);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Hard-delete a provider. HCT admin only. Requires the provider to already
     * be in 'removed' state (so admins always go through the soft-archive
     * step first and can't accidentally nuke a live provider).
     *
     * Blocked if:
     *   - any sp_payments rows reference this provider (financial history)
     *   - any experiences are still hosted by this provider
     * Both must be cleaned up manually by the admin before hard delete.
     *
     * Cascades automatically via DB FKs:
     *   - sp_pricing            → cascadeOnDelete
     *   - sp_availability       → cascadeOnDelete
     *   - sp_room_bookings      → cascade via sp_pricing
     *   - trip_day_services     → service_provider_id set NULL (history kept)
     *   - trip_day_services     → sp_pricing_id set NULL via sp_pricing FK
     *
     * Linked user row is deleted explicitly.
     */
    protected function permanentlyDeleteProvider(Request $request): JsonResponse
    {
        if (!Auth::user() || !Auth::user()->isHctAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $provider = ServiceProvider::findOrFail($request->provider_id);
        if ($provider->status !== 'removed') {
            return response()->json([
                'error' => 'Provider must be in "removed" state before permanent deletion. Use Remove first.',
            ], 422);
        }

        // Blockers — these have hard FK constraints (RESTRICT) on the
        // service_providers row and would throw a DB error if we tried to
        // delete. Surface a friendly message instead.
        $paymentCount    = \App\Models\SpPayment::where('service_provider_id', $provider->id)->count();
        $experienceCount = \App\Models\Experience::where('hlh_id', $provider->id)->count();
        if ($paymentCount > 0 || $experienceCount > 0) {
            $blockers = [];
            if ($paymentCount > 0)    $blockers[] = "$paymentCount payment record(s)";
            if ($experienceCount > 0) $blockers[] = "$experienceCount hosted experience(s)";
            return response()->json([
                'error' => 'Cannot permanently delete — provider has ' . implode(' + ', $blockers)
                    . '. Reassign or archive those first.',
                'blockers' => [
                    'sp_payments' => $paymentCount,
                    'experiences' => $experienceCount,
                ],
            ], 422);
        }

        $userId = $provider->user_id;
        \DB::transaction(function () use ($provider, $userId) {
            $provider->delete();
            if ($userId) {
                \App\Models\User::where('id', $userId)->delete();
            }
        });
        return response()->json(['success' => true]);
    }

    /**
     * Reverse a removeProvider() — admin only. Flips status back to 'approved'
     * and reactivates the linked user. Pricing rows stay inactive so the
     * admin can review and re-enable individual rates intentionally.
     */
    protected function restoreProvider(Request $request): JsonResponse
    {
        if (!Auth::user() || !Auth::user()->isHctAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $provider = ServiceProvider::findOrFail($request->provider_id);
        if ($provider->status !== 'removed') {
            return response()->json(['error' => 'Provider is not in removed state'], 422);
        }
        $provider->update([
            'status'             => 'approved',
            'last_updated_by'    => Auth::id(),
            'last_updated_by_role' => 'admin',
        ]);
        if ($provider->user_id) {
            \App\Models\User::where('id', $provider->user_id)->update(['status' => 'active']);
        }
        return response()->json(['success' => true]);
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
            "name" => [
                "required", "string", "max:255",
                \Illuminate\Validation\Rule::unique("regions", "name")->ignore($request->region_id),
            ],
            "continent" => "required|string|max:100",
            "country" => "required|string|max:100",
        ];

        $validator = Validator::make($request->all(), $rules, [
            "name.unique" => "A region with this name already exists.",
        ]);
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
        if ($request->filled("type")) {
            $query->where("type", $request->type);
        }
        if ($request->filled("difficulty")) {
            $query->where("difficulty_level", $request->difficulty);
        }
        // Status: "1" => active, "0" => inactive, blank => no filter.
        if ($request->filled("status") && in_array((string) $request->status, ["0", "1"], true)) {
            $query->where("is_active", (bool) $request->status);
        }

        $experiences = $query->orderBy("sort_order")->paginate(20);

        return response()->json([
            "experiences" => $experiences->items(),
            "data" => $experiences->items(),
            "pagination" => [
                "current_page" => $experiences->currentPage(),
                "last_page" => $experiences->lastPage(),
                "total" => $experiences->total(),
                "per_page" => $experiences->perPage(),
            ],
        ]);
    }

    protected function saveExperience(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "id"                => "nullable|integer|exists:experiences,id",
            "name"              => "required|string|max:255",
            "region_id"         => "required|integer|exists:regions,id",
            "hlh_id"            => "required|integer|exists:service_providers,id",
            "type"              => "required|string|max:100",
            "short_description" => "required|string|max:500",
            "duration_type"     => "required|in:less_than_day,single_day,multi_day",
        ], [
            "name.required"              => "Please enter an experience name.",
            "region_id.required"         => "Please choose a region.",
            "region_id.exists"           => "The selected region is invalid.",
            "hlh_id.required"            => "Please choose an HLH (host).",
            "hlh_id.exists"              => "The selected HLH is invalid.",
            "type.required"              => "Please choose an experience type.",
            "short_description.required" => "Please write a short description.",
            "duration_type.required"     => "Please choose a duration type.",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $data = $request->except(["_token", "save_experience", "experience_days", "gallery"]);

        if ($request->hasFile("card_image")) {
            $stored = \App\Services\ImageUploadService::storeUploadedImage($request->file("card_image"), "experiences", 1200);
            if ($stored) {
                $data["card_image"] = $stored;
            } else {
                return response()->json(["error" => "Failed to upload card image. Use JPG, PNG, or WebP."], 422);
            }
        } else {
            unset($data["card_image"]);
        }

        // Handle JSON fields (gallery handled separately below — it's both an
        // existing-paths JSON string AND new multipart uploads).
        foreach (["best_seasons", "available_months", "restricted_months", "unavailable_months", "osp_services", "seasonal_price_variation"] as $jsonField) {
            if (isset($data[$jsonField]) && is_string($data[$jsonField])) {
                $data[$jsonField] = json_decode($data[$jsonField], true);
            }
        }

        if (!empty($data["slug"])) {
            $data["slug"] = Str::slug($data["slug"]);
        } elseif (!empty($data["name"])) {
            $data["slug"] = Str::slug($data["name"]);
        }

        // Auto-sync base_cost_per_person from the breakdown so what HCT enters
        // and what the trip is charged stay in lockstep.
        $data["base_cost_per_person"] = (float) ($data["cost_accommodation"] ?? 0)
            + (float) ($data["cost_logistics"] ?? 0)
            + (float) ($data["cost_guide"] ?? 0)
            + (float) ($data["cost_activities"] ?? 0)
            + (float) ($data["cost_other"] ?? 0);

        if ($request->filled("id")) {
            $experience = Experience::findOrFail($request->id);
            $experience->update($data);
        } else {
            $experience = Experience::create($data);
        }

        // Append any newly uploaded gallery images. The form's hidden gallery
        // field (JSON string of existing paths to keep) was previously the only
        // path that touched this column; new multipart uploads were silently
        // dropped.
        $existingGallery = $experience->gallery ?? [];
        if (is_string($existingGallery)) {
            $existingGallery = json_decode($existingGallery, true) ?: [];
        }
        $newPaths = [];
        foreach ((array) $request->file("gallery", []) as $galleryFile) {
            if ($galleryFile) {
                $stored = \App\Services\ImageUploadService::storeUploadedImage($galleryFile, "experiences", 1200);
                if ($stored) $newPaths[] = $stored;
            }
        }
        if (!empty($newPaths)) {
            $experience->update(["gallery" => array_values(array_merge($existingGallery, $newPaths))]);
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
        $experience->update(["is_active" => !$experience->is_active]);
        return response()->json(["success" => true, "is_active" => $experience->is_active]);
    }

    protected function getRegenerativeProjects(Request $request): JsonResponse
    {
        $query = RegenerativeProject::with("region");
        if ($request->filled("region_id")) {
            $query->where("region_id", $request->region_id);
        }
        if ($request->filled("status")) {
            $query->where("is_active", $request->boolean("status"));
        }
        if ($request->filled("search")) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where("name", "like", "%{$s}%")->orWhere("short_description", "like", "%{$s}%");
            });
        }
        $projects = $query->orderBy("name")->paginate(20);
        return response()->json([
            "data" => $projects->items(),
            "pagination" => [
                "current_page" => $projects->currentPage(),
                "last_page" => $projects->lastPage(),
                "total" => $projects->total(),
                "per_page" => $projects->perPage(),
            ],
        ]);
    }

    protected function saveRegenerativeProject(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "name" => "required|string|max:255",
            "region_id" => "required|exists:regions,id",
            "action_type" => "required|string|max:255",
            "short_description" => "required|string",
            "impact_unit" => "required|string|max:255",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $data = $request->except(["_token", "save_regenerative_project", "id", "project_id", "gallery"]);

        if ($request->hasFile("main_image")) {
            $stored = \App\Services\ImageUploadService::storeUploadedImage($request->file("main_image"), "regenerative_projects", 1200);
            if ($stored) {
                $data["main_image"] = $stored;
            } else {
                return response()->json(["error" => "Failed to upload main image. Use JPG, PNG, or WebP."], 422);
            }
        } else {
            unset($data["main_image"]);
        }

        foreach (["active_periods", "paused_periods", "fallback_for_regions"] as $jsonField) {
            if (isset($data[$jsonField]) && is_string($data[$jsonField])) {
                $data[$jsonField] = json_decode($data[$jsonField], true);
            }
        }

        $editId = $request->input("project_id", $request->input("id"));
        if ($editId) {
            $project = RegenerativeProject::findOrFail($editId);
            $project->update($data);
        } else {
            $project = RegenerativeProject::create($data);
        }

        // Append newly uploaded gallery images (same pattern as saveExperience).
        $existingGallery = $project->gallery ?? [];
        if (is_string($existingGallery)) {
            $existingGallery = json_decode($existingGallery, true) ?: [];
        }
        $newPaths = [];
        foreach ((array) $request->file("gallery", []) as $galleryFile) {
            if ($galleryFile) {
                $stored = \App\Services\ImageUploadService::storeUploadedImage($galleryFile, "regenerative_projects", 1200);
                if ($stored) $newPaths[] = $stored;
            }
        }
        if (!empty($newPaths)) {
            $project->update(["gallery" => array_values(array_merge($existingGallery, $newPaths))]);
        }

        return response()->json(["success" => true, "project" => $project]);
    }

    protected function disableRegenerativeProject(Request $request): JsonResponse
    {
        $id = $request->input("project_id", $request->input("id"));
        $project = RegenerativeProject::findOrFail($id);
        $project->update(["is_active" => !$project->is_active]);
        return response()->json(["success" => true, "is_active" => $project->is_active]);
    }

    // ===========================
    // BULK DELETE HANDLERS
    // ===========================

    /**
     * Normalise an "ids" payload (array or comma string) to an array of ints.
     */
    protected function bulkIds(Request $request): array
    {
        $ids = $request->input("ids", []);
        if (is_string($ids)) {
            $ids = array_filter(explode(",", $ids));
        }
        return array_values(array_unique(array_map("intval", (array) $ids)));
    }

    protected function bulkDeleteExperiences(Request $request): JsonResponse
    {
        $ids = $this->bulkIds($request);
        if (empty($ids)) {
            return response()->json(["error" => "No items selected"], 422);
        }
        // No soft-delete trait on Experience; mirror the single "disable" action.
        $count = Experience::whereIn("id", $ids)->update(["is_active" => false]);
        return response()->json(["success" => true, "message" => "{$count} experience(s) deactivated"]);
    }

    protected function bulkDeleteRegenerativeProjects(Request $request): JsonResponse
    {
        $ids = $this->bulkIds($request);
        if (empty($ids)) {
            return response()->json(["error" => "No items selected"], 422);
        }
        $count = RegenerativeProject::whereIn("id", $ids)->update(["is_active" => false]);
        return response()->json(["success" => true, "message" => "{$count} project(s) deactivated"]);
    }

    protected function bulkDeleteRegions(Request $request): JsonResponse
    {
        $ids = $this->bulkIds($request);
        if (empty($ids)) {
            return response()->json(["error" => "No items selected"], 422);
        }
        $deleted = 0;
        $skipped = [];
        foreach (Region::whereIn("id", $ids)->withCount("experiences")->get() as $region) {
            if ($region->experiences_count > 0) {
                $skipped[] = $region->name;
                continue;
            }
            $region->delete();
            $deleted++;
        }
        $msg = "{$deleted} region(s) deleted";
        if (!empty($skipped)) {
            $msg .= ". Skipped (have experiences): " . implode(", ", $skipped);
        }
        return response()->json(["success" => true, "message" => $msg]);
    }

    protected function bulkDeleteCurrencies(Request $request): JsonResponse
    {
        $ids = $this->bulkIds($request);
        if (empty($ids)) {
            return response()->json(["error" => "No items selected"], 422);
        }
        $deleted = 0;
        $skipped = [];
        foreach (Currency::whereIn("id", $ids)->get() as $currency) {
            if (in_array($currency->code, ["USD", "INR"], true)) {
                $skipped[] = $currency->code;
                continue;
            }
            $currency->delete();
            $deleted++;
        }
        $msg = "{$deleted} currency(ies) deleted";
        if (!empty($skipped)) {
            $msg .= ". Skipped (base currency): " . implode(", ", $skipped);
        }
        return response()->json(["success" => true, "message" => $msg]);
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
        $data = $request->only([
            "trip_name", "status", "stage", "traveller_origin", "adults", "children", "infants",
            "start_date", "end_date", "start_location", "end_location",
            "pickup_location", "pickup_time", "drop_location", "drop_time",
            "operations_notes", "accommodation_comfort", "vehicle_comfort",
            "guide_preference", "travel_pace", "budget_sensitivity", "other_preferences",
            "margin_rp_percent", "margin_hrp_percent", "commission_hct_percent",
            "general_notes",
        ]);
        // Convert empty date strings to null to avoid MySQL date format errors
        foreach (['start_date', 'end_date'] as $dateKey) {
            if (isset($data[$dateKey]) && $data[$dateKey] === '') {
                $data[$dateKey] = null;
            }
        }
        $trip->update($data);

        // Only recalculate when there's an itinerary — otherwise CostCalculatorService
        // would persist an all-zero breakdown and wipe the trip's cost columns
        // (same hazard recalculateTripCost() guards against).
        if ($trip->tripDays()->exists() || $trip->selectedExperiences()->exists()) {
            app(CostCalculatorService::class)->calculate($trip);
        }

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
            "payment_status" => "paid",
        ]);

        app(LeadService::class)->checkPaymentAndTransition($trip);

        return response()->json(["success" => true]);
    }

    protected function createRazorpayOrder(Request $request): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return response()->json(["error" => "Please log in to make a payment."], 401);
            }

            $trip = $this->resolveTrip($request);
            if (!$trip) {
                return response()->json(["error" => "Trip not found."], 404);
            }

            // Only fall back to the trip's final price when no amount was sent at all;
            // an explicit amount of 0 / blank is a bad request, not "charge me everything".
            if ($request->filled('amount')) {
                $amountInRupees = (float) $request->amount;
            } elseif ($request->has('amount')) {
                // amount key present but null/empty/0 → reject
                $amountInRupees = 0.0;
            } else {
                $amountInRupees = (float) $trip->final_price;
            }
            if ($amountInRupees <= 0) {
                return response()->json(["error" => "Invalid payment amount."], 422);
            }

            $amountInPaise = (int) round($amountInRupees * 100);
            \Log::info('Razorpay createOrder', [
                'trip_id' => $trip->id,
                'inr' => $amountInRupees,
                'paise' => $amountInPaise,
            ]);

            $razorpay = app(RazorpayService::class);
            $order = $razorpay->createOrder(
                $amountInPaise,
                'INR',
                'trip_' . $trip->id . '_' . time(),
                ['trip_id' => (string) $trip->id, 'user_id' => (string) Auth::id()]
            );

            TravellerPayment::create([
                'trip_id'            => $trip->id,
                'user_id'            => Auth::id(),
                'amount'             => $amountInRupees,
                'payment_date'       => now()->toDateString(),
                'mode'               => 'razorpay',
                'razorpay_order_id'  => $order['id'],
                'payment_status'     => 'pending',
                'recorded_by'        => Auth::id(),
            ]);

            return response()->json([
                'success'  => true,
                'order_id' => $order['id'],
                'amount'   => $amountInPaise,
                'currency' => 'INR',
                'key_id'   => config('services.razorpay.key_id'),
                'name'     => Auth::user()->full_name,
                'email'    => Auth::user()->email,
                'contact'  => Auth::user()->mobile ?: '',
            ]);
        } catch (\Exception $e) {
            \Log::error('Razorpay order creation failed: ' . $e->getMessage());
            return response()->json(["error" => "Payment order failed: " . $e->getMessage()], 500);
        }
    }

    protected function logRazorpayFailure(Request $request): JsonResponse
    {
        \Log::warning('Razorpay payment failed', [
            'user_id'      => Auth::id(),
            'order_id'     => $request->order_id,
            'amount_inr'   => $request->amount_inr,
            'code'         => $request->code,
            'reason'       => $request->reason,
            'description'  => $request->description,
            'source'       => $request->source,
            'step'         => $request->step,
        ]);

        // Mark the pending TravellerPayment record as failed so the trip's
        // balance_due isn't permanently inflated by abandoned attempts.
        if ($request->order_id) {
            TravellerPayment::where('razorpay_order_id', $request->order_id)
                ->where('payment_status', 'pending')
                ->update([
                    'payment_status' => 'failed',
                    'notes' => trim(($request->code ? '[' . $request->code . '] ' : '') . ($request->description ?: '')) ?: null,
                ]);
        }

        return response()->json(['success' => true]);
    }

    protected function verifyRazorpayPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $razorpay = app(RazorpayService::class);
        $verified = $razorpay->verifySignature(
            $request->razorpay_order_id,
            $request->razorpay_payment_id,
            $request->razorpay_signature
        );

        $payment = TravellerPayment::where('razorpay_order_id', $request->razorpay_order_id)->first();
        if (!$payment) {
            return response()->json(["error" => "Payment record not found."], 404);
        }

        if ($verified) {
            $payment->update([
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
                'payment_status'      => 'paid',
            ]);

            app(LeadService::class)->checkPaymentAndTransition($payment->trip);

            $trip = $payment->trip()->with('user')->first();
            if ($trip && $trip->user && $trip->user->email) {
                $travellerName = $trip->user->full_name ?: 'Traveller';
                $tripData = [
                    'traveller_name' => $travellerName,
                    'trip_id' => $trip->trip_id,
                    'trip_name' => $trip->trip_name,
                    'start_date' => $trip->start_date ? \Carbon\Carbon::parse($trip->start_date)->format('d M Y') : 'TBD',
                    'end_date' => $trip->end_date ? \Carbon\Carbon::parse($trip->end_date)->format('d M Y') : 'TBD',
                    'adults' => $trip->adults,
                    'children' => $trip->children,
                    'total_cost' => $trip->final_price ?: $trip->total_cost,
                ];
                $paymentData = [
                    'traveller_name' => $travellerName,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : now()->format('d M Y'),
                    'trip_id' => $trip->trip_id,
                    'reference' => $payment->razorpay_payment_id,
                ];

                $this->sendMail($trip->user->email, new PaymentReceivedEmail($paymentData), 'payment:' . $payment->id);

                if ($trip->status === 'confirmed') {
                    $this->sendMail($trip->user->email, new BookingConfirmationEmail($tripData), 'booking:' . $trip->id);
                }
            }

            return response()->json(['success' => true, 'message' => 'Payment verified successfully!']);
        }

        $payment->update(['payment_status' => 'failed']);
        return response()->json(['error' => 'Payment verification failed.'], 400);
    }

    protected function getTravellerPaymentHistory(Request $request): JsonResponse
    {
        $trip = Trip::find($request->trip_id);

        $payments = TravellerPayment::where("trip_id", $request->trip_id)
            ->with(["recorder", "trip:id,trip_id,trip_name,final_price"])
            ->orderBy("payment_date", "desc")
            ->get();

        $rows = $payments->map(function ($p) {
            return [
                "id" => $p->id,
                "amount" => $p->amount,
                "payment_date" => optional($p->payment_date)?->toDateString(),
                "mode" => $p->mode,
                "method" => $p->mode,
                // Keep payment_status (existing consumers) AND status (new shape).
                "payment_status" => $p->payment_status,
                "status" => $p->payment_status,
                "notes" => $p->notes,
                "reference" => $p->razorpay_payment_id,
                "recorder" => $p->recorder ? [
                    "id" => $p->recorder->id,
                    "full_name" => $p->recorder->full_name,
                    "email" => $p->recorder->email,
                ] : null,
                "trip" => $p->trip ? [
                    "id" => $p->trip->id,
                    "trip_id" => $p->trip->trip_id,
                    "trip_name" => $p->trip->trip_name,
                ] : null,
            ];
        });

        $totalPaid = $payments->where("payment_status", "paid")->sum("amount");
        $finalPrice = $trip ? (float) $trip->final_price : 0;

        return response()->json([
            "payments" => $rows,
            "total_paid" => $totalPaid,
            "total_due" => $finalPrice,
            "balance" => $finalPrice - $totalPaid,
            "trip" => $trip ? [
                "id" => $trip->id,
                "trip_id" => $trip->trip_id,
                "trip_name" => $trip->trip_name,
                "final_price" => $trip->final_price,
            ] : null,
        ]);
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

        // The TripDayExperience charges only the activity portion. Other components
        // (accommodation / transport / guide / other) become separate TripDayService
        // entries so the trip's pricing breakdown stays accurate and nothing is
        // double-counted under "activity_cost".
        $dayExp = app(ItineraryService::class)->addExperienceToDay($day, $experience, array_merge(
            $request->all(),
            ["cost_per_person" => (float) $experience->cost_activities]
        ));

        $componentMap = [
            "cost_accommodation" => ["accommodation", $experience->accommodation_category ?? "Accommodation"],
            "cost_logistics"     => ["transport",     "Logistics / Transport"],
            "cost_guide"         => ["guide",         "Guide service"],
            "cost_other"         => ["other",         "Other"],
        ];
        foreach ($componentMap as $field => [$serviceType, $description]) {
            $cost = (float) $experience->{$field};
            if ($cost <= 0) continue;
            TripDayService::create([
                "trip_day_id" => $day->id,
                "service_type" => $serviceType,
                "description" => $description,
                "cost" => $cost,
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
        $validator = Validator::make($request->all(), [
            "day_id" => "required|integer|exists:trip_days,id",
            "service_type" => "required|in:accommodation,transport,guide,activity,meal,other",
            "service_provider_id" => "nullable|integer|exists:service_providers,id",
            "sp_pricing_id" => "nullable|integer|exists:sp_pricing,id",
            "room_quantity" => "nullable|integer|min:1|max:500",
            "cost" => "nullable|numeric|min:0",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $service = TripDayService::create([
            "trip_day_id" => $request->day_id,
            "service_provider_id" => $request->service_provider_id,
            "sp_pricing_id" => $request->sp_pricing_id,
            "room_quantity" => $request->room_quantity,
            "service_type" => $request->service_type,
            "description" => $request->description,
            "from_location" => $request->from_location,
            "to_location" => $request->to_location,
            "cost" => $request->cost ?? 0,
            "is_included" => $request->boolean("is_included", false),
            "notes" => $request->notes,
        ]);

        // If this accommodation service was tied to a specific room category,
        // allocate held rooms in sp_room_bookings for the day's date.
        $this->allocateRoomsForTripService($service);

        return response()->json(["success" => true, "service" => $service]);
    }

    protected function editDayService(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "service_id" => "required|integer|exists:trip_day_services,id",
            "service_type" => "nullable|in:accommodation,transport,guide,activity,meal,other",
            "service_provider_id" => "nullable|integer|exists:service_providers,id",
            "sp_pricing_id" => "nullable|integer|exists:sp_pricing,id",
            "room_quantity" => "nullable|integer|min:1|max:500",
            "cost" => "nullable|numeric|min:0",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $service = TripDayService::findOrFail($request->service_id);
        $oldPricingId = $service->sp_pricing_id;
        $oldQty = $service->room_quantity;

        $service->update($request->only([
            "service_provider_id", "sp_pricing_id", "room_quantity",
            "service_type", "description",
            "from_location", "to_location", "cost", "is_included", "notes",
        ]));

        // Room booking sync: if the room category or quantity changed, release the
        // old allocation and create a new one. (Same pricing/qty = no-op.)
        $service->refresh();
        if ($oldPricingId !== $service->sp_pricing_id || $oldQty !== $service->room_quantity) {
            app(\App\Services\RoomAvailabilityService::class)->releaseForTripDayService($service->id);
            $this->allocateRoomsForTripService($service);
        }

        return response()->json(["success" => true]);
    }

    protected function removeDayService(Request $request): JsonResponse
    {
        // Free up any held room bookings before deleting the service.
        app(\App\Services\RoomAvailabilityService::class)->releaseForTripDayService((int) $request->service_id);
        TripDayService::destroy($request->service_id);
        return response()->json(["success" => true]);
    }

    /**
     * If a TripDayService is an Accommodation tied to a specific sp_pricing
     * row (room category), create sp_room_bookings rows for the trip day's
     * date with the requested quantity. Held = trip not yet confirmed;
     * promoted to confirmed when trip status flips.
     */
    protected function allocateRoomsForTripService(TripDayService $service): void
    {
        if ($service->service_type !== 'accommodation' || !$service->sp_pricing_id) return;
        $qty = (int) ($service->room_quantity ?: 1);
        $day = TripDay::with('trip')->find($service->trip_day_id);
        if (!$day || !$day->date) return;
        $tripStatus = $day->trip?->status;
        $bookingStatus = $tripStatus === 'confirmed' ? 'confirmed' : 'held';
        app(\App\Services\RoomAvailabilityService::class)->book(
            spPricingId: $service->sp_pricing_id,
            tripId: $day->trip_id,
            tripDayServiceId: $service->id,
            date: $day->date,
            quantity: $qty,
            status: $bookingStatus,
            source: 'trip_manager'
        );
    }

    protected function changeDayServiceProvider(Request $request): JsonResponse
    {
        $service = TripDayService::with('tripDay.trip')->findOrFail($request->service_id);
        $newSpId = $request->service_provider_id;
        $date = $service->tripDay->date;
        $trip = $service->tripDay->trip;

        $availabilityService = new SpAvailabilityService();

        // Release any per-room bookings under the old SP for this service.
        app(\App\Services\RoomAvailabilityService::class)->releaseForTripDayService($service->id);

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
                ->where('approval_status', 'approved')
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

        // Guard: CostCalculatorService::calculate() PERSISTS the result (it calls
        // $trip->update($data) at the end), so calling it on a trip with no
        // itinerary produces an all-zero breakdown and wipes any existing
        // (seeded or manually-entered) cost columns. Refuse rather than destroy.
        $hasItinerary = $trip->tripDays()->exists()
            || $trip->selectedExperiences()->exists();
        if (!$hasItinerary) {
            return response()->json([
                "error" => "This trip has no itinerary yet — add experiences or days before recalculating, otherwise the existing costs would be wiped.",
            ], 422);
        }

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
            "email" => "required|email|unique:service_providers,email",
            "phone_1" => "required|string|max:20",
            "region_id" => "required|exists:regions,id",
        ], [
            "email.unique" => "An application with this email already exists. Please contact us if you need to update your application.",
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
            "guide_types" => $request->guide_types,
            "activity_types" => $request->activity_types,
            "notes" => $request->input('description', $request->input('notes')),
            "status" => "pending",
        ]);

        $providerLabel = match ($provider->provider_type) {
            'hrp' => 'Himalayan Regenerative Partner (HRP)',
            'hlh' => 'Homestay Local Host (HLH)',
            'osp' => 'Other Service Provider (OSP)',
            default => 'Partner',
        };
        $contactName = $provider->contact_person ?: $provider->name;
        $this->sendMail($provider->email, new SpApplicationReceivedEmail($contactName, $providerLabel), 'sp_application:' . $provider->id);

        return response()->json(["success" => true, "message" => "Application submitted successfully"]);
    }

    /**
     * Send a mail without letting failures break the calling action.
     * Errors are logged with the supplied tag for traceability.
     */
    protected function sendMail(string $to, $mailable, string $tag = ''): void
    {
        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('Mail send failed [' . $tag . ']: ' . $e->getMessage(), [
                'to' => $to,
                'mailable' => get_class($mailable),
            ]);
        }
    }

    // ===========================
    // SETTINGS & PDF
    // ===========================

    protected function getSettings(Request $request): JsonResponse
    {
        // Use ?: not the second arg of get() — `?group=` (empty string after
        // ConvertEmptyStringsToNull middleware) would otherwise filter to "" and
        // return [] instead of the default 'general' group.
        $group = $request->input("group") ?: "general";
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
        $rules = [
            "name" => "required|string|max:255",
            "key" => [
                "required", "string", "max:100",
                \Illuminate\Validation\Rule::unique("pdf_templates", "key")->ignore($request->id),
            ],
            "paper_size" => "nullable|string|max:20",
            "orientation" => "nullable|in:portrait,landscape",
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

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

    /**
     * Resolve the currently logged-in SP and verify the application is approved.
     * Mirrors SpMiddleware's gate so AJAX handlers don't accept calls from
     * pending/rejected applicants who bypass the dashboard route.
     * Returns [ServiceProvider, null] on success or [null, JsonResponse] on failure.
     *
     * @return array{0: ?ServiceProvider, 1: ?JsonResponse}
     */
    protected function resolveApprovedSp(): array
    {
        $user = Auth::user();
        if (!$user || !$user->isServiceProvider()) {
            return [null, response()->json(['error' => 'Unauthorized'], 403)];
        }
        $sp = ServiceProvider::where('user_id', $user->id)->first();
        if (!$sp) {
            return [null, response()->json(['error' => 'No provider found'], 404)];
        }
        if ($sp->status !== 'approved') {
            $message = $sp->status === 'rejected'
                ? 'Your service provider application was not approved. Please contact HCT for details.'
                : 'Your service provider application is under review. You will get an email once it is approved.';
            return [null, response()->json(['error' => $message], 403)];
        }
        return [$sp, null];
    }

    protected function getSpCalendar(Request $request): JsonResponse
    {
        [$sp, $err] = $this->resolveApprovedSp();
        if ($err) return $err;

        $year = (int) ($request->year ?: now()->year);
        $month = (int) ($request->month ?: now()->month);

        $service = new SpAvailabilityService();
        $calendar = $service->getMonthCalendar($sp->id, $year, $month);
        $rooms = $this->buildRoomCalendar($sp->id, $year, $month);

        return response()->json([
            'calendar' => $calendar,
            'rooms'    => $rooms,
            'ical_url' => $sp->ical_url,
            'ical_last_synced_at' => $sp->ical_last_synced_at?->format('d M Y H:i'),
        ]);
    }

    /**
     * Per-room-category availability for an SP across a date range.
     *
     * Used by:
     *   - Experience detail page (traveller-side "Stay options" widget)
     *   - Trip Manager edit-service modal (admin picking room category)
     *
     * Auth: HCT can query any SP; an SP can query their own; travellers
     * (logged in or guest) can query any approved SP (read-only — no
     * inventory write).
     *
     * Input:  service_provider_id, start_date (YYYY-MM-DD),
     *         end_date (optional — defaults to start_date)
     * Output: { sp_name, currency, dates: [...], categories: [
     *            { sp_pricing_id, room_category, total, available,
     *              rate, meal_plan, default_occupancy } ] }
     *
     * For multi-night ranges, the "available" count returned is the
     * MIN available across every night in the range (binding constraint).
     */
    protected function getRoomAvailability(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'service_provider_id' => 'required|integer|exists:service_providers,id',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $sp = ServiceProvider::findOrFail($request->service_provider_id);

        // Approved-only — don't leak pending/rejected SP inventory.
        if ($sp->status !== 'approved') {
            return response()->json(['error' => 'Provider not approved'], 403);
        }

        $svc = app(\App\Services\RoomAvailabilityService::class);

        // Default: today + 1 night if no dates supplied.
        $start = $request->filled('start_date') ? \Carbon\Carbon::parse($request->start_date) : now()->startOfDay();
        $end = $request->filled('end_date') ? \Carbon\Carbon::parse($request->end_date) : $start->copy();
        if ($end->lt($start)) $end = $start->copy();

        // Pull each accommodation category. For multi-night, compute MIN
        // available across the range (the binding constraint).
        $rows = SpPricing::where('service_provider_id', $sp->id)
            ->where('service_type', 'accommodation')
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereNotNull('total_rooms')
            ->orderBy('id')
            ->get();

        $categories = $rows->map(function (SpPricing $row) use ($svc, $start, $end) {
            $minAvail = (int) $row->total_rooms;
            foreach (\Carbon\CarbonPeriod::create($start, $end) as $d) {
                $minAvail = min($minAvail, $svc->availableForCategory($row->id, $d));
                if ($minAvail === 0) break;
            }
            return [
                'sp_pricing_id'     => $row->id,
                'room_category'    => $row->room_category ?: $row->category,
                'total'            => (int) $row->total_rooms,
                'available'        => $minAvail,
                'rate'             => (float) $row->price,
                'meal_plan'        => $row->meal_plan,
                'default_occupancy' => $row->default_occupancy,
            ];
        })->values();

        $nights = \Carbon\CarbonPeriod::create($start, $end)->count();

        return response()->json([
            'sp_id'      => $sp->id,
            'sp_name'    => $sp->name,
            'start_date' => $start->format('Y-m-d'),
            'end_date'   => $end->format('Y-m-d'),
            'nights'     => $nights,
            'categories' => $categories,
        ]);
    }

    /**
     * Best-effort date extraction from a free-text user message. Used by
     * chatWithAi so first-turn "from 15-09 to 17-09" can ground stay
     * options without waiting for the AI to round-trip [TRIP_DETAILS].
     *
     * Returns [start_date, end_date] as YYYY-MM-DD strings (or nulls).
     */
    protected function extractDatesFromMessage(string $message): array
    {
        if ($message === '') return [null, null];
        $msg = ' ' . $message . ' ';
        // Match YYYY-MM-DD pairs first.
        if (preg_match_all('/(\d{4})-(\d{1,2})-(\d{1,2})/', $msg, $matches)) {
            $found = [];
            foreach ($matches[0] as $d) {
                try {
                    $found[] = \Carbon\Carbon::parse($d)->format('Y-m-d');
                } catch (\Throwable) {}
            }
            if (count($found) >= 1) {
                return [$found[0], $found[1] ?? null];
            }
        }
        // Fall back to "DD Month" / "Month DD" forms — best effort, single date.
        if (preg_match('/(\d{1,2})\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\s*(\d{2,4})?/i', $msg, $m)) {
            try {
                $year = $m[3] ?? null;
                $year = $year && strlen($year) <= 2 ? '20' . $year : $year;
                $parsed = \Carbon\Carbon::parse($m[1] . ' ' . $m[2] . ' ' . ($year ?: now()->year))->format('Y-m-d');
                return [$parsed, null];
            } catch (\Throwable) {}
        }
        return [null, null];
    }

    /**
     * Per-date per-room-category availability for a month. Used by SP and
     * admin calendars to show "Sgl 2/2 · Dbl 3/4" type breakdowns instead
     * of just the binary day-level status.
     *
     * @return array<string, array<int, array{room_category:string,total:int,available:int,booked:int}>>
     *   Keyed by YYYY-MM-DD.
     */
    protected function buildRoomCalendar(int $spId, int $year, int $month): array
    {
        $svc = app(\App\Services\RoomAvailabilityService::class);
        $start = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $out = [];
        foreach (\Carbon\CarbonPeriod::create($start, $end) as $day) {
            $key = $day->format('Y-m-d');
            $cats = $svc->categoriesForDate($spId, $key)
                ->map(fn($c) => [
                    'room_category' => $c['room_category'],
                    'comfort_tier'  => $c['comfort_tier'] ?? null,
                    'total'         => $c['total'],
                    'available'     => $c['available'],
                    'booked'        => $c['booked'],
                ])
                ->values()
                ->all();
            if (!empty($cats)) $out[$key] = $cats;
        }
        return $out;
    }

    protected function spBlockDates(Request $request): JsonResponse
    {
        [$sp, $err] = $this->resolveApprovedSp();
        if ($err) return $err;

        $dates = $request->input('dates', []);
        if (empty($dates)) return response()->json(['error' => 'No dates provided'], 422);

        $service = new SpAvailabilityService();
        $result = $service->blockDates($sp->id, $dates, $request->notes);

        return response()->json([
            'success' => true,
            'blocked' => $result['blocked'],
            'conflicts' => $result['conflicts'],
        ]);
    }

    protected function spUnblockDates(Request $request): JsonResponse
    {
        [$sp, $err] = $this->resolveApprovedSp();
        if ($err) return $err;

        $dates = $request->input('dates', []);
        if (empty($dates)) return response()->json(['error' => 'No dates provided'], 422);

        $service = new SpAvailabilityService();
        $count = $service->unblockDates($sp->id, $dates);

        return response()->json(['success' => true, 'unblocked' => $count]);
    }

    protected function spSaveIcalUrl(Request $request): JsonResponse
    {
        [$sp, $err] = $this->resolveApprovedSp();
        if ($err) return $err;

        $sp->update(['ical_url' => $request->ical_url ?: null]);

        return response()->json(['success' => true]);
    }

    protected function spSyncIcalNow(Request $request): JsonResponse
    {
        [$sp, $err] = $this->resolveApprovedSp();
        if ($err) return $err;
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
        $rooms = $this->buildRoomCalendar($sp->id, $year, $month);

        return response()->json([
            'calendar' => $calendar,
            'rooms'    => $rooms,
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
        $result = $service->blockDates($sp->id, $dates, $request->notes);

        return response()->json([
            'success' => true,
            'blocked' => $result['blocked'],
            'conflicts' => $result['conflicts'],
        ]);
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
