<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use function Illuminate\Support\defer;
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
use App\Models\NewsletterSubscriber;
use App\Models\PdfTemplate;
use App\Models\Review;
use App\Models\SpAvailability;
use App\Services\AuthService;
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
use App\Mail\NewsletterWelcomeEmail;
use App\Mail\AdminNewApplicationEmail;
use App\Mail\AdminNewSubscriberEmail;
use App\Mail\NewsletterCampaignEmail;
use App\Mail\BookingConfirmationEmail;
use App\Mail\PaymentReceivedEmail;
use App\Mail\SpApplicationReceivedEmail;
use App\Mail\SupportRequestEmail;
use App\Mail\SpApplicationApprovedEmail;
use App\Mail\PricingApprovedEmail;
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
        // Mirror CostCalculatorService exactly so a guest sees the SAME price they
        // will see after logging in (#6/#7). New model (reqs 3.1-3.3): the experience
        // is one slab-priced bundle; provider hotel/transport/guide stack as separate
        // marked-up lines; RP/HRP/HCT are internal only (not added to the total).
        $adults = max((int) ($guestData['adults'] ?: 1), 1);
        $children = (int) ($guestData['children'] ?? 0);
        $infants = (int) ($guestData['infants'] ?? 0);
        // Same pax-type factors as CostCalculatorService so guest == login (#42).
        $childFactor  = (float) Setting::getValue('child_price_percent', 50) / 100;
        $infantFactor = (float) Setting::getValue('infant_price_percent', 0) / 100;
        $peopleFactor = $adults + ($childFactor * $children) + ($infantFactor * $infants);
        $groupSize    = max($adults + $children + $infants, 1);
        $defaultMarkup = (float) Setting::getValue('default_provider_markup_percent', 0);

        $experienceCost = 0;
        $transportCost = 0;
        $accommodationCost = 0;
        $guideCost = 0;
        $activityCost = 0;
        $otherCost = 0;

        // Charge each SELECTED experience once (matches the logged-in dedup). Each is a
        // slab-priced bundle: per-person price by group size × billable heads.
        $expIds = array_values(array_unique($guestData['experience_ids'] ?? []));
        $experiences = !empty($expIds) ? Experience::whereIn('id', $expIds)->with('priceSlabs')->get() : collect();
        foreach ($experiences as $exp) {
            $perPerson = $exp->slabPricePerPerson($groupSize);
            if ($perPerson <= 0) {
                $perPerson = (float) ($exp->base_cost_per_person
                    ?: ($exp->cost_accommodation + $exp->cost_logistics + $exp->cost_guide
                        + $exp->cost_activities + $exp->cost_other));
            }
            $experienceCost += (int) round($perPerson * $peopleFactor);
        }

        // Extra days (days in the itinerary with no experience) — rest vs activity.
        $restDayCostPerPerson = (float) Setting::getValue('rest_day_cost_per_person', 2000);
        $activityDayCostPerPerson = (float) Setting::getValue('activity_day_cost_per_person', 5000);
        $extraDayCost = 0;
        $itinerary = $guestData['ai_itinerary'] ?? null;
        if ($itinerary && isset($itinerary['days'])) {
            foreach ($itinerary['days'] as $day) {
                $hasExp = !empty($day['experiences'] ?? []);
                $dayType = $day['type'] ?? ($day['day_type'] ?? null);
                if (!$hasExp && $dayType) {
                    $costPerPerson = in_array($dayType, ['activity', 'free']) ? $activityDayCostPerPerson : $restDayCostPerPerson;
                    $extraDayCost += $costPerPerson * $peopleFactor;
                }
            }
        }
        $extraDayCost = (int) round($extraDayCost);

        // Provider pins stack as separate marked-up lines (hotel / anchor→hotel /
        // guide). Markup is per provider (raw price never shown). Guide is exclusive
        // — only present when the experience provides none.
        $numDays = ($itinerary && isset($itinerary['days'])) ? count($itinerary['days']) : 1;
        $nights = max($numDays - 1, 1);
        $totalPax = $adults + $children;
        $markup = function (int $raw, $sp) use ($defaultMarkup): int {
            $pct = $sp ? $sp->effectiveMarkupPercent() : $defaultMarkup;
            return (int) round($raw * (1 + $pct / 100));
        };
        $accommodationProviderCost = 0;
        $transportProviderCost = 0;
        $guideProviderCost = 0;
        if (!empty($guestData['accommodation_pricing_id']) && ($ap = SpPricing::live()->with('serviceProvider')->find($guestData['accommodation_pricing_id']))) {
            $occ = max((int) ($ap->default_occupancy ?: 2), 1);
            $rooms = max((int) ceil($totalPax / $occ), 1);
            $accommodationProviderCost = $markup((int) round((float) $ap->price * $rooms * $nights), $ap->serviceProvider);
            $accommodationCost += $accommodationProviderCost;
        }
        if (!empty($guestData['vehicle_pricing_id']) && ($vp = SpPricing::live()->with('serviceProvider')->find($guestData['vehicle_pricing_id']))) {
            $unit = strtolower((string) $vp->unit);
            if (str_contains($unit, 'km')) {
                $raw = (int) round((float) $vp->price * (float) ($vp->distance_km ?: 0));
            } elseif (str_contains($unit, 'day')) {
                $raw = (int) round((float) $vp->price * max($numDays, 1));
            } elseif (str_contains($unit, 'person') || str_contains($unit, 'pax')) {
                $raw = (int) round((float) $vp->price * max($totalPax, 1));
            } else {
                $raw = (int) round((float) $vp->price);
            }
            $transportProviderCost = $markup($raw, $vp->serviceProvider);
            $transportCost += $transportProviderCost;
        }
        if (!empty($guestData['guide_pricing_id']) && ($gp = SpPricing::live()->with('serviceProvider')->find($guestData['guide_pricing_id']))) {
            $guideDays = max($numDays, 1);
            $guideProviderCost = $markup((int) round((float) $gp->price * $guideDays), $gp->serviceProvider);
            $guideCost += $guideProviderCost;
        }

        $totalCost = $experienceCost + $transportCost + $accommodationCost + $guideCost + $activityCost + $otherCost + $extraDayCost;

        // RP/HRP/HCT computed for internal reporting only — NOT added to the total (req 3.3).
        $rpPercent = (float) Setting::getValue('default_rp_margin_percent', 5);
        $hrpPercent = (float) Setting::getValue('default_hrp_margin_percent', 10);
        $hctPercent = (float) Setting::getValue('default_hct_commission_percent', 15);
        $rpAmount = round($totalCost * $rpPercent / 100, 2);
        $hrpAmount = round($totalCost * $hrpPercent / 100, 2);
        $hctAmount = round($totalCost * $hctPercent / 100, 2);

        $subtotal = $totalCost;
        $gstPercent = (float) Setting::getValue('gst_percent', 5);
        $gstAmount = round($subtotal * $gstPercent / 100, 2);
        $finalPrice = $subtotal + $gstAmount;

        return [
            'experience_cost' => $experienceCost,
            'transport_cost' => $transportCost,
            'accommodation_cost' => $accommodationCost,
            'guide_cost' => $guideCost,
            'activity_cost' => $activityCost,
            'other_cost' => $otherCost,
            'extra_day_cost' => $extraDayCost,
            'total_cost' => $totalCost,
            'accommodation_provider_cost'   => $accommodationProviderCost,
            'transport_provider_cost'       => $transportProviderCost,
            'guide_provider_cost'           => $guideProviderCost,
            'margin_rp_percent' => $rpPercent,
            'margin_rp_amount' => $rpAmount,
            'margin_hrp_percent' => $hrpPercent,
            'margin_hrp_amount' => $hrpAmount,
            'commission_hct_percent' => $hctPercent,
            'commission_hct_amount' => $hctAmount,
            'subtotal' => $subtotal,
            'gst_amount' => $gstAmount,
            'final_price' => $finalPrice,
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

    /**
     * Per-action authorization levels for the shared AJAX dispatcher.
     *
     * Both the admin domain (hecoadmin.test/ajax -> adminIndex) and the PUBLIC
     * portal domain (hecoportal.test/ajax -> portalIndex) route through the same
     * index() method, so route middleware alone cannot protect admin actions —
     * every key is otherwise reachable unauthenticated from the public portal.
     * This map gates each dispatched key by the minimum trust level required.
     *
     * Levels: public | auth | sp | sp_or_hct | hct | administrator
     * Keep entries in the SAME ORDER as the index() dispatch chain below
     * (the first key present on the request is the one that will be dispatched).
     */
    private const ACTION_LEVELS = [
        // AUTH & USER
        'userlogin' => 'public', 'login' => 'public',
        'usersignup' => 'public', 'register' => 'public',
        'save_nationality' => 'auth',
        'update_profile' => 'auth',
        'upload_profile_photo' => 'auth',
        'change_password' => 'auth',
        // TRAVELLER HOMEPAGE (guest + traveller)
        'get_regions_for_map' => 'public',
        'get_experiences_for_discover' => 'public',
        'get_experience_detail' => 'public',
        'get_reviews' => 'public',
        'check_review_eligibility' => 'public',
        'submit_review' => 'auth',
        'set_landing_preferences' => 'public',
        'chat_with_ai' => 'public',
        'create_trip' => 'public',
        'get_trip_selected_experiences' => 'public',
        'get_trip_timeline' => 'public',
        'get_chat_history' => 'public',
        'sync_guest_journey' => 'auth',
        'generate_itinerary' => 'public',
        'add_experience_to_trip' => 'public',
        'remove_experience_from_trip' => 'public',
        'prefer_experience' => 'public',
        'get_wishlist' => 'public',
        'reorder_experiences' => 'public',
        'update_group_details' => 'public',
        'update_trip_start_date' => 'public',
        'update_travel_preferences' => 'public',
        'save_trip_name' => 'public',
        'add_day_to_trip' => 'public',
        'remove_day_from_trip' => 'public',
        'get_trip_pricing' => 'public',
        'get_category_providers' => 'public',
        'create_razorpay_order' => 'public',
        'log_razorpay_failure' => 'public',
        'verify_razorpay_payment' => 'public',
        'get_traveller_payment_history' => 'auth',
        'get_trip_impact' => 'public',
        'request_support' => 'public',
        'subscribe_newsletter' => 'public',
        'get_user_trips' => 'auth',
        'reopen_trip' => 'auth',
        'confirm_trip' => 'auth',
        'erase_trip' => 'auth',
        // HCT DASHBOARD
        'get_dashboard_stats' => 'hct',
        'create_hct_user' => 'administrator',
        'update_hct_user' => 'administrator',
        'deactivate_hct_user' => 'administrator',
        'get_system_lists' => 'hct',
        'save_system_list_item' => 'administrator',
        'deactivate_system_list_item' => 'administrator',
        'delete_system_list_item' => 'administrator',
        'reset_hct_user_password' => 'administrator',
        'get_ai_prompts' => 'hct',
        'save_ai_prompt' => 'administrator',
        'delete_ai_prompt' => 'administrator',
        'get_activity_logs' => 'hct',
        'get_newsletter_send_count' => 'hct',
        'send_newsletter_campaign' => 'administrator',
        'set_subscriber_status' => 'hct',
        'get_sp_pricing' => 'sp_or_hct',
        'save_sp_pricing' => 'sp_or_hct',
        'delete_sp_pricing' => 'sp_or_hct',
        'get_pending_pricing' => 'hct',
        'approve_pricing' => 'hct',
        'reject_pricing' => 'hct',
        // Provider-authored experiences awaiting HCT review
        'get_pending_experiences' => 'hct',
        'approve_experience' => 'hct',
        'reject_experience' => 'hct',
        'get_room_availability' => 'public',
        'get_support_requests' => 'hct',
        'resolve_support_request' => 'hct',
        'chat_with_ai_hct' => 'hct',
        'get_lead_reminders' => 'hct',
        'get_leads' => 'hct',
        'update_lead' => 'hct',
        'get_lead_history' => 'hct',
        'get_upcoming_trips' => 'hct',
        'get_trips_by_date_range' => 'hct',
        'update_trip_status' => 'hct',
        'get_calendar_trips' => 'hct',
        'get_sp_payments' => 'hct',
        'create_sp_payment' => 'hct',
        'add_sp_payment_entry' => 'hct',
        'edit_sp_payment_entry' => 'hct',
        'get_sp_payment_history' => 'hct',
        'get_traveller_payments_overview' => 'hct',
        'get_gst_report' => 'hct',
        'get_providers' => 'hct',
        'add_provider' => 'hct',
        'edit_provider' => 'hct',
        'get_provider_trips' => 'hct',
        'get_provider_payment_history' => 'hct',
        'get_traveler_trips' => 'hct',
        'get_traveler_payment_history' => 'hct',
        'get_provider_applications' => 'hct',
        'approve_provider' => 'hct',
        'reject_provider' => 'hct',
        'remove_provider' => 'administrator',
        'bulk_remove_providers' => 'administrator',
        // REGION
        'get_regions_list' => 'hct',
        'save_region' => 'hct',
        'toggle_region' => 'hct',
        'delete_region' => 'hct',
        'bulk_delete_regions' => 'hct',
        // CURRENCY (pricing-sensitive -> admin for writes)
        'get_currencies_list' => 'hct',
        'save_currency' => 'administrator',
        'toggle_currency' => 'administrator',
        'delete_currency' => 'administrator',
        'bulk_delete_currencies' => 'administrator',
        // EXPERIENCE & RP
        'get_experiences_list' => 'hct',
        'save_experience' => 'hct',
        'disable_experience' => 'hct',
        'bulk_delete_experiences' => 'hct',
        'get_regenerative_projects' => 'hct',
        'save_regenerative_project' => 'hct',
        'disable_regenerative_project' => 'hct',
        'bulk_delete_regenerative_projects' => 'hct',
        // TRIP MANAGER
        'get_trip_info' => 'hct',
        'update_trip_info' => 'hct',
        'add_traveller_payment' => 'hct',
        'edit_traveller_payment' => 'hct',
        'get_trip_itinerary' => 'hct',
        'search_experiences_for_trip' => 'hct',
        'add_experience_to_day' => 'hct',
        'remove_experience_from_day' => 'hct',
        'reorder_trip_days' => 'hct',
        'add_trip_day' => 'hct',
        'remove_trip_day' => 'hct',
        'get_day_services' => 'hct',
        'add_day_service' => 'hct',
        'edit_day_service' => 'hct',
        'remove_day_service' => 'hct',
        'change_day_service_provider' => 'hct',
        'request_ai_recalculation' => 'hct',
        'recalculate_trip_cost' => 'hct',
        // SP APPLICATION
        'submit_sp_application' => 'public',
        // SP AVAILABILITY (Portal - service provider)
        'get_sp_calendar' => 'sp',
        'sp_block_dates' => 'sp',
        'sp_unblock_dates' => 'sp',
        'sp_save_ical_url' => 'sp',
        'sp_sync_ical_now' => 'sp',
        'update_sp_profile' => 'sp',
        'update_sp_photo' => 'sp',
        'add_sp_document' => 'sp',
        'get_sp_assigned_trips' => 'sp',
        // A regional partner overseeing the providers in their region.
        'get_hrp_region_providers' => 'sp',
        // SP EXPERIENCES (HLH hosts author their own — see saveSpExperience)
        'get_sp_experiences' => 'sp',
        'save_sp_experience' => 'sp',
        'toggle_sp_experience' => 'sp',
        'delete_sp_experience' => 'sp',
        // SP AVAILABILITY (Admin)
        'admin_get_sp_calendar' => 'hct',
        'admin_sp_block_dates' => 'hct',
        'admin_sp_unblock_dates' => 'hct',
        // SETTINGS & PDF
        'get_settings' => 'hct',
        'save_settings' => 'administrator',
        'get_pdf_templates' => 'hct',
        'save_pdf_template' => 'administrator',
    ];

    /**
     * Central authorization gate for the shared AJAX dispatcher.
     * Finds the first ACTION_LEVELS key present on the request (mirrors the
     * dispatch order) and enforces its required trust level. Returns a 401/403
     * JSON response when the caller is not permitted, or null to allow.
     */
    private function authorizeAction(Request $request): ?JsonResponse
    {
        $level = null;
        foreach (self::ACTION_LEVELS as $key => $lvl) {
            if ($request->has($key)) {
                $level = $lvl;
                break;
            }
        }
        if ($level === null || $level === 'public') {
            return null;
        }
        $user = auth()->user();
        $ok = match ($level) {
            'auth'      => (bool) $user,
            'sp'        => $user && $user->isServiceProvider(),
            'sp_or_hct' => $user && ($user->isServiceProvider() || $user->isHct()),
            'hct'       => $user && $user->isHct(),
            'administrator' => $user && $user->isHctAdmin(),
            default     => false,
        };
        if ($ok) {
            return null;
        }
        return response()->json(
            ['error' => $user ? 'Forbidden' : 'Unauthorized'],
            $user ? 403 : 401
        );
    }

    /**
     * Itinerary/price mutation keys that must be rejected once a trip is locked
     * (paid or closed) — otherwise the total can drift after money is received.
     */
    private const LOCK_EDIT_KEYS = [
        // Admin / trip-manager edits.
        'update_trip_info', 'update_travel_preferences',
        'add_experience_to_day', 'remove_experience_from_day',
        'add_trip_day', 'remove_trip_day', 'reorder_trip_days',
        'add_day_service', 'edit_day_service', 'remove_day_service',
        'change_day_service_provider',
        // Traveller portal-builder edits — these mutate the same trip's
        // itinerary / pax / dates (the price basis), so a paid or closed trip
        // must reject them too. (Guests carry no trip_id and own no locked
        // trip, so the guard resolves to null and lets them through.)
        'add_experience_to_trip', 'remove_experience_from_trip',
        'add_day_to_trip', 'remove_day_from_trip',
        'update_group_details', 'update_trip_start_date', 'reorder_experiences',
    ];

    /**
     * A trip is locked for editing once its stage is closed or any traveller
     * payment has been received. Confirmed-but-unpaid trips stay editable so HCT
     * can still adjust them before payment.
     */
    private function tripIsLocked(Trip $trip): bool
    {
        if ($trip->stage === 'closed') {
            return true;
        }
        return $trip->travellerPayments()->where('payment_status', 'paid')->exists();
    }

    /**
     * True when every pinned provider rate is valid: it belongs to its named
     * provider, is the right service type, and is approved + active. Each
     * $pins entry is [providerId, pricingId, serviceType]; null pricing = skip.
     * Shared by the pin-time (#18) and confirm-time (#16) checks.
     */
    private function pinnedRatesValid(array $pins): bool
    {
        foreach ($pins as [$providerId, $pricingId, $serviceType]) {
            if (!$pricingId) {
                continue;
            }
            $ok = SpPricing::where('id', $pricingId)
                ->where('service_provider_id', $providerId)
                ->where('service_type', $serviceType)
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->exists();
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /**
     * Reserve room inventory for a TRIP-LEVEL accommodation pin across every
     * night of the trip. The day-level path already books via SpRoomBooking, but
     * a trip-level pin (Comfort & Partners) previously reserved nothing (#12).
     * book() is idempotent per date and refuses to overbook, so this is safe.
     */
    private function bookTripLevelAccommodation(Trip $trip): void
    {
        if (!$trip->accommodation_pricing_id || !$trip->start_date || !$trip->end_date) {
            return;
        }
        $pricing = SpPricing::find($trip->accommodation_pricing_id);
        if (!$pricing || $pricing->service_type !== 'accommodation') {
            return;
        }
        $adults = max((int) $trip->adults, 1);
        $children = (int) ($trip->children ?: 0);
        $occupancy = max((int) ($pricing->default_occupancy ?: 2), 1);
        $rooms = max((int) ceil(($adults + $children) / $occupancy), 1);

        $room = app(\App\Services\RoomAvailabilityService::class);
        $start = \Carbon\Carbon::parse($trip->start_date)->startOfDay();
        $end   = \Carbon\Carbon::parse($trip->end_date)->startOfDay();
        for ($d = $start->copy(); $d->lt($end); $d->addDay()) {
            $booked = $room->book($pricing->id, $trip->id, null, $d->copy(), $rooms, 'confirmed', 'trip_preference');
            if (!$booked) {
                \Log::warning('Trip-level accommodation could not be booked (availability)', [
                    'trip_id' => $trip->id, 'sp_pricing_id' => $pricing->id, 'date' => $d->toDateString(),
                ]);
            }
        }
    }

    /**
     * Create provider invoices (SpPayment) for a trip's pinned providers, with
     * the amount auto-computed as rate × quantity. One invoice per (trip,
     * provider, service_type) — safe to call again (dedupe). Used on confirm so
     * providers are actually billed (#13), instead of relying on manual entry.
     */
    private function createProviderInvoices(Trip $trip): void
    {
        $calc = app(CostCalculatorService::class);
        $pins = [
            ['accommodation_provider_id', 'accommodation_pricing_id', 'accommodation'],
            ['vehicle_provider_id',       'vehicle_pricing_id',       'transport'],
            ['guide_provider_id',         'guide_pricing_id',         'guide'],
        ];
        foreach ($pins as [$provKey, $priceKey, $serviceType]) {
            $providerId = $trip->{$provKey};
            $pricingId  = $trip->{$priceKey};
            if (!$providerId || !$pricingId) {
                continue;
            }
            $already = SpPayment::where('trip_id', $trip->id)
                ->where('service_provider_id', $providerId)
                ->where('service_type', $serviceType)
                ->exists();
            if ($already) {
                continue;
            }
            $pricing = SpPricing::find($pricingId);
            if (!$pricing) {
                continue;
            }
            $amount = $calc->providerPayable($pricing, $trip, $serviceType);
            SpPayment::create([
                'trip_id'             => $trip->id,
                'service_provider_id' => $providerId,
                'service_type'        => $serviceType,
                'amount_due'          => $amount,
                'amount_paid'         => 0,
                'balance'             => $amount,
                'notes'               => 'Auto-generated on trip confirmation.',
            ]);
        }

        // Day-level assigned providers (trip-manager) — sum each provider's booked
        // day-service cost per service_type and invoice it too, so nothing that was
        // pinned to specific days is left unbilled. Deduped against the pins above.
        $dayServices = TripDayService::whereHas('tripDay', fn($q) => $q->where('trip_id', $trip->id))
            ->whereNotNull('service_provider_id')
            ->where('cost', '>', 0)
            ->get()
            ->groupBy(fn($s) => $s->service_provider_id . '|' . $s->service_type);
        foreach ($dayServices as $group) {
            $first = $group->first();
            $providerId = (int) $first->service_provider_id;
            $serviceType = (string) $first->service_type;
            $already = SpPayment::where('trip_id', $trip->id)
                ->where('service_provider_id', $providerId)
                ->where('service_type', $serviceType)
                ->exists();
            if ($already) {
                continue;
            }
            $amount = (int) round($group->sum('cost'));
            if ($amount <= 0) {
                continue;
            }
            SpPayment::create([
                'trip_id'             => $trip->id,
                'service_provider_id' => $providerId,
                'service_type'        => $serviceType,
                'amount_due'          => $amount,
                'amount_paid'         => 0,
                'balance'             => $amount,
                'notes'               => 'Auto-generated on trip confirmation (day services).',
            ]);
        }
    }

    /**
     * Best-effort resolve the trip a mutation targets, from whichever id the
     * request carries (trip / day / day-experience / service / reorder list).
     */
    private function resolveTripFromRequest(Request $request): ?Trip
    {
        if ($request->filled('trip_id')) {
            return Trip::find($request->trip_id);
        }
        if ($request->filled('day_id')) {
            return optional(TripDay::find($request->day_id))->trip;
        }
        if ($request->filled('day_experience_id')) {
            return optional(optional(TripDayExperience::find($request->day_experience_id))->tripDay)->trip;
        }
        if ($request->filled('service_id')) {
            return optional(optional(TripDayService::find($request->service_id))->tripDay)->trip;
        }
        $order = $request->get('order');
        if (is_array($order) && !empty($order)) {
            return optional(TripDay::find(reset($order)))->trip;
        }
        return null;
    }

    /**
     * Write an audit-log row for an admin mutation. Best-effort — never breaks
     * the calling action. Records who did what to which model, plus details (#26).
     */
    private function logActivity(string $action, ?string $modelType = null, $modelId = null, array $details = []): void
    {
        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => $action,
                'model_type' => $modelType,
                'model_id'   => $modelId,
                'details'    => $details ?: null,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('ActivityLog write failed [' . $action . ']: ' . $e->getMessage());
        }
    }

    /**
     * Reject a mutation when it targets a locked (paid/closed) trip.
     * Returns a 423 response to block, or null to allow.
     */
    private function guardLockedTrip(Request $request): ?JsonResponse
    {
        foreach (self::LOCK_EDIT_KEYS as $key) {
            if ($request->has($key)) {
                $trip = $this->resolveTripFromRequest($request);
                if ($trip && $this->tripIsLocked($trip)) {
                    return response()->json([
                        'error' => 'This trip is locked (paid or closed) and can no longer be edited.',
                    ], 423);
                }
                break;
            }
        }
        return null;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            // Central authorization gate — see ACTION_LEVELS. Protects admin
            // actions reachable via the public portal /ajax (shared dispatcher).
            if ($denied = $this->authorizeAction($request)) {
                return $denied;
            }
            // Closed/paid trip lock — no itinerary/price edits after payment.
            if ($locked = $this->guardLockedTrip($request)) {
                return $locked;
            }

            // ===== AUTH & USER =====
            if ($request->has('userlogin') || $request->has('login')) {
                return $this->userLogin($request);
            }
            if ($request->has('usersignup') || $request->has('register')) {
                return $this->userSignup($request);
            }
            if ($request->has('save_nationality')) {
                return $this->saveNationality($request);
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
            if ($request->has('get_category_providers')) {
                return $this->getCategoryProviders($request);
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
            if ($request->has('get_newsletter_send_count')) {
                return $this->getNewsletterSendCount($request);
            }
            if ($request->has('send_newsletter_campaign')) {
                return $this->sendNewsletterCampaign($request);
            }
            if ($request->has('set_subscriber_status')) {
                return $this->setSubscriberStatus($request);
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
            if ($request->has('get_pending_experiences')) {
                return $this->getPendingExperiences($request);
            }
            if ($request->has('approve_experience')) {
                return $this->approveExperience($request);
            }
            if ($request->has('reject_experience')) {
                return $this->rejectExperience($request);
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
            if ($request->has('add_provider')) {
                return $this->addProvider($request);
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
            if ($request->has('bulk_remove_providers')) {
                return $this->bulkRemoveProviders($request);
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
            if ($request->has('update_sp_photo')) {
                return $this->updateSpPhoto($request);
            }
            if ($request->has('update_sp_profile')) {
                return $this->updateSpProfile($request);
            }
            if ($request->has('add_sp_document')) {
                return $this->addSpDocument($request);
            }
            if ($request->has('get_hrp_region_providers')) {
                return $this->getHrpRegionProviders($request);
            }

            if ($request->has('get_sp_assigned_trips')) {
                return $this->getSpAssignedTrips($request);
            }

            // ===== SP EXPERIENCES (HLH hosts author their own) =====
            if ($request->has('get_sp_experiences')) {
                return $this->getSpExperiences($request);
            }
            if ($request->has('save_sp_experience')) {
                return $this->saveSpExperience($request);
            }
            if ($request->has('toggle_sp_experience')) {
                return $this->toggleSpExperience($request);
            }
            if ($request->has('delete_sp_experience')) {
                return $this->deleteSpExperience($request);
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

        // Scoped to HCT roles rather than matching on email alone: the same
        // address may also belong to a traveller, and an unscoped lookup would
        // find that row first and reject the admin who owns this door.
        $user = User::findByCredentials($request->email, $request->password, User::HCT_ROLES);
        if (!$user) {
            // Distinguish "not an admin" from "wrong password" only when the
            // credentials are otherwise good, so this stays useless as an
            // account-probe for anyone who does not already know the password.
            if (User::findByCredentials($request->email, $request->password, User::PORTAL_ROLES)) {
                return response()->json(['error' => 'Admin accounts only. Use the portal to log in.'], 403);
            }
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        Auth::login($user);
        $request->session()->regenerate();
        return response()->json(['success' => true, 'redirect' => '/dashboard']);
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

        // Portal-side roles first — this door belongs to travellers and
        // providers, so when an address is held by both a traveller and an HCT
        // login it is the traveller's account that opens here. An HCT-only
        // address still gets in (and is redirected to the admin domain below),
        // which is how it has always worked.
        $user = User::findByCredentials($request->email, $request->password, User::PORTAL_ROLES)
            ?: User::findByCredentials($request->email, $request->password, User::HCT_ROLES);

        if ($user) {
            Auth::login($user, $request->boolean("remember"));
            $request->session()->regenerate();

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
            // Unique among travellers, not across the whole table: the address
            // may already carry this person's provider or HCT login, and that
            // is not a reason to refuse them a traveller account.
            "email" => ["required", "email", User::uniqueEmailRule(['traveller'])],
            "password" => "required|min:8|confirmed",
            "phone" => "nullable|string|max:20",
            "address1" => "nullable|string|max:500",
            "address2" => "nullable|string|max:500",
            "city" => "nullable|string|max:100",
            "state" => "nullable|string|max:100",
            "country" => "nullable|string|in:" . implode(',', config('countries.list')),
            "postal_code" => "nullable|string|max:20",
            "nationality" => "required|string|in:" . implode(',', config('countries.list')),
            "gender" => "nullable|in:male,female,other,prefer_not_to_say",
            "date_of_birth" => "nullable|date|before:today",
        ], [
            "nationality.required" => "Please select your nationality.",
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
            "nationality" => $request->nationality,
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

    /**
     * Lightweight endpoint for the post-social-login nationality prompt.
     * Social signups bypass the signup form (which requires nationality), so we
     * collect it on first login. Also seeds traveller_origin on the user's
     * existing trips that don't have one yet.
     */
    protected function saveNationality(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(["error" => "Not authenticated"], 401);
        }

        $validator = Validator::make($request->all(), [
            "nationality" => "required|string|in:" . implode(',', config('countries.list')),
        ], [
            "nationality.required" => "Please select your nationality.",
            "nationality.in" => "Please select a valid nationality.",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $user->update(["nationality" => $request->nationality]);

        // Apply the derived origin to the traveller's trips that don't yet have
        // one, so their current trip reflects it immediately (editable in admin).
        $origin = $user->travellerOrigin();
        if ($origin) {
            $user->trips()->whereNull('traveller_origin')->update(['traveller_origin' => $origin]);
        }

        return response()->json(["success" => true]);
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
            "nationality" => "nullable|string|in:" . implode(',', config('countries.list')),
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
            'nationality' => 'Nationality',
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
        $query = Experience::where("is_active", true)->with(["region", "hlh", "days"])
            // A stay quotes the cheapest room it offers; without this
            // aggregate every stay card would fetch its own rates.
            ->withRoomRateFrom();

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
        $expCols = ['id', 'name', 'type', 'region_id', 'category', 'duration_type', 'duration_days', 'difficulty_level', 'base_cost_per_person', 'price_currency', 'available_months'];
        $expMap = fn($e) => [
            'id' => $e->id,
            'name' => $e->name,
            'type' => $e->type,
            'region' => $e->region->name ?? '',
            'region_id' => $e->region_id,
            'duration' => $e->duration_type === 'multi_day' ? ($e->duration_days ?? 1) . 'd' : '1d',
            'difficulty' => $e->difficulty_level,
            'price' => $e->price_from['amount'] ?? 0,
            'price_unit' => $e->price_from['unit'] ?? 'per person',
            'months' => $e->available_months,
        ];

        $activeRegionId = null;
        $availableRegions = [];

        if (!empty($selectedExpIds)) {
            // (A) Send the selected experiences as-is.
            $experiencesJson = Experience::whereIn('id', $selectedExpIds)
                ->select($expCols)->withRoomRateFrom()
                ->with('region:id,name,continent,country')
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
                    ->select($expCols)->withRoomRateFrom()
                ->with('region:id,name,continent,country')
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
            // Candidate catalogue to name-match against — active experiences, scoped
            // to the trip's region when known (mirrors the single-region constraint).
            // Previously this loop referenced an undefined $experiences, throwing a
            // 500 whenever the AI said "added" without an [ADD_TO_TRIP] tag (#23).
            $candidateExperiences = Experience::where('is_active', true)
                ->when($currentTripRegionId, fn($q) => $q->where('region_id', $currentTripRegionId))
                ->get(['id', 'name', 'region_id']);
            foreach ($candidateExperiences as $exp) {
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

        // Derive end_date from the itinerary length so the trip always has a
        // complete date range (calendar, listings, etc. depend on end_date).
        if ($date) {
            $maxDay = $trip->tripDays()->max('day_number') ?: 1;
            $trip->update(['end_date' => \Carbon\Carbon::parse($date)->addDays($maxDay - 1)]);
        } else {
            $trip->update(['end_date' => null]);
        }

        return response()->json(["success" => true]);
    }

    /**
     * Return the approved service providers that offer a given accommodation
     * category (comfort tier), each with their real sp_pricing rate. Powers the
     * traveller flow: pick a category → choose a provider → see its price.
     * One option per live pricing row (a provider may list several room types
     * within the same tier), keyed by the pricing row id.
     */
    protected function getCategoryProviders(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "service_type" => "required|string|in:accommodation,transport,guide,activity,other",
            "category"     => "required|string|max:100",
            "region_id"    => "nullable|integer",
            "trip_id"      => "nullable",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        // Each service type categorises its pricing rows on a different column:
        // accommodation by comfort tier, transport by vehicle type, guide by category.
        $matchColumn = match ($request->service_type) {
            'accommodation' => 'comfort_tier',
            'transport'     => 'vehicle_type',
            default         => 'category', // guide / activity / other
        };

        // A trip is single-region, so only surface providers from the trip's
        // region. Without this, a Tirthan trip would also list Spiti/Ladakh
        // providers, which can never actually serve it.
        //
        // The region is resolved SERVER-SIDE from the trip's own experiences —
        // not the client-sent region_id — because the client value can go stale
        // (e.g. the traveller switches the trip to another region without the
        // provider cards reloading). The client region_id is only a fallback for
        // guest trips that have no DB row yet.
        $regionId = $request->input('region_id');
        if ($request->filled('trip_id') && $request->trip_id !== 'guest') {
            $trip = Trip::with('selectedExperiences')->find($request->trip_id);
            $expId = $trip?->selectedExperiences->pluck('experience_id')->first();
            if ($expId) {
                $tripRegionId = Experience::whereKey($expId)->value('region_id');
                if ($tripRegionId) $regionId = $tripRegionId;
            }
        }

        $rows = SpPricing::live()
            ->where('service_type', $request->service_type)
            ->where($matchColumn, $request->category)
            ->whereHas('serviceProvider', function ($q) use ($regionId) {
                $q->where('status', 'approved');
                if ($regionId) $q->where('region_id', $regionId);
            })
            ->with('serviceProvider:id,name,markup_percent')
            ->orderBy('price')
            ->get();

        // Never expose a provider's RAW price to the traveller (req 3.3): every price
        // shown is the admin-marked-up selling price. The markup is the platform's
        // hidden margin. For per-km transport the displayed figure is the marked-up
        // per-km rate; the actual line cost (distance × rate) is computed server-side.
        $providers = $rows->map(function ($r) {
            $pct = $r->serviceProvider ? $r->serviceProvider->effectiveMarkupPercent() : 0;
            return [
                'pricing_id'    => $r->id,
                'provider_id'   => $r->service_provider_id,
                'provider_name' => $r->serviceProvider?->name ?? 'Provider',
                'room_category' => $r->room_category,
                'price'         => round((float) $r->price * (1 + $pct / 100), 2),
                'unit'          => $r->unit ?: 'night',
            ];
        })->values();

        // Guide is exclusive — tell the UI to show a notice and hide the guide
        // provider list when the trip's experience already provides a guide.
        // Works for both a saved trip and a guest's session experiences.
        $guideIncluded = false;
        if ($request->service_type === 'guide') {
            if ($request->filled('trip_id') && $request->trip_id !== 'guest') {
                $t = Trip::find($request->trip_id);
                $guideIncluded = $t ? $this->tripHasIncludedGuide($t) : false;
            } else {
                $gExpIds = $this->guestTrip()['experience_ids'] ?? [];
                $guideIncluded = !empty($gExpIds)
                    && Experience::whereIn('id', $gExpIds)->where('cost_guide', '>', 0)->exists();
            }
        }

        return response()->json([
            "success" => true,
            "providers" => $providers,
            "guide_included" => $guideIncluded,
        ]);
    }

    protected function updateTravelPreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "accommodation_comfort"     => "nullable|string|max:100",
            "accommodation_provider_id" => "nullable|integer",
            "accommodation_pricing_id"  => "nullable|integer",
            "vehicle_comfort"           => "nullable|string|max:100",
            "vehicle_provider_id"       => "nullable|integer",
            "vehicle_pricing_id"        => "nullable|integer",
            "guide_preference"          => "nullable|string|max:100",
            "guide_provider_id"         => "nullable|integer",
            "guide_pricing_id"          => "nullable|integer",
            "travel_pace"               => "nullable|string|max:100",
            "budget_sensitivity"        => "nullable|string|max:100",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }
        if (!Auth::check()) {
            $gt = $this->guestTrip();
            // Guide is exclusive — block pinning a guide when the guest's experience
            // already provides one (the UI shows a notice; this is the safety net).
            if ($request->filled('guide_pricing_id')) {
                $gExpIds = $gt['experience_ids'] ?? [];
                if (!empty($gExpIds) && Experience::whereIn('id', $gExpIds)->where('cost_guide', '>', 0)->exists()) {
                    return response()->json([
                        "error" => "This experience already includes a guide, so an additional guide can't be added.",
                    ], 422);
                }
            }
            foreach (['accommodation_comfort', 'accommodation_provider_id', 'accommodation_pricing_id', 'vehicle_comfort', 'vehicle_provider_id', 'vehicle_pricing_id', 'guide_preference', 'guide_provider_id', 'guide_pricing_id', 'travel_pace', 'budget_sensitivity'] as $key) {
                if ($request->has($key)) $gt[$key] = $request->$key;
            }
            $this->saveGuestTrip($gt);
            return response()->json(["success" => true]);
        }

        $trip = $this->resolveTrip($request);
        if (!$trip) return response()->json(["error" => "Trip not found"], 404);

        $data = $request->only([
            "accommodation_comfort", "accommodation_provider_id", "accommodation_pricing_id",
            "vehicle_comfort", "vehicle_provider_id", "vehicle_pricing_id",
            "guide_preference", "guide_provider_id", "guide_pricing_id",
            "travel_pace", "budget_sensitivity",
        ]);

        // Changing a category invalidates any previously chosen provider (providers
        // differ per category), unless this same request also supplies a fresh pick.
        if ($request->has('accommodation_comfort') && !$request->has('accommodation_pricing_id')
            && $request->accommodation_comfort !== $trip->accommodation_comfort) {
            $data['accommodation_provider_id'] = null;
            $data['accommodation_pricing_id'] = null;
        }
        if ($request->has('vehicle_comfort') && !$request->has('vehicle_pricing_id')
            && $request->vehicle_comfort !== $trip->vehicle_comfort) {
            $data['vehicle_provider_id'] = null;
            $data['vehicle_pricing_id'] = null;
        }
        if ($request->has('guide_preference') && !$request->has('guide_pricing_id')
            && $request->guide_preference !== $trip->guide_preference) {
            $data['guide_provider_id'] = null;
            $data['guide_pricing_id'] = null;
        }

        // Validate every pinned rate belongs to its named provider and is an
        // approved, active row of the right service type — otherwise a traveller
        // could pin an arbitrary/unapproved pricing id to any provider (#18).
        $valid = $this->pinnedRatesValid([
            [$data['accommodation_provider_id'] ?? null, $data['accommodation_pricing_id'] ?? null, 'accommodation'],
            [$data['vehicle_provider_id'] ?? null,       $data['vehicle_pricing_id'] ?? null,       'transport'],
            [$data['guide_provider_id'] ?? null,         $data['guide_pricing_id'] ?? null,         'guide'],
        ]);
        if (!$valid) {
            return response()->json(["error" => "Selected provider rate is invalid or unavailable."], 422);
        }

        // Guide is EXCLUSIVE: if the trip's experience already provides a guide, an
        // additional guide provider can't be pinned on top (unlike accommodation /
        // transport, which are distinct segments and stack).
        if (!empty($data['guide_pricing_id']) && $this->tripHasIncludedGuide($trip)) {
            return response()->json([
                "error" => "This experience already includes a guide, so an additional guide can't be added.",
            ], 422);
        }

        $trip->update($data);
        return response()->json(["success" => true]);
    }

    /**
     * True when any experience selected on the trip already bundles a guide
     * (cost_guide > 0) — used to block adding a duplicate guide provider.
     */
    private function tripHasIncludedGuide(Trip $trip): bool
    {
        $ids = $trip->selectedExperiences()->pluck('experience_id');
        if ($ids->isEmpty()) {
            return false;
        }
        return Experience::whereIn('id', $ids)->where('cost_guide', '>', 0)->exists();
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
            $pricing['experiences'] = $this->pricingExperienceLines(
                $gt['experience_ids'] ?? [],
                (int) ($gt['adults'] ?? 1) + (int) ($gt['children'] ?? 0) + (int) ($gt['infants'] ?? 0)
            );
            return response()->json(["success" => true, "pricing" => $this->hideInternalMargins($pricing)]);
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

        // Per-experience base price (per person) for the pricing summary. Display
        // only — NOT summed into the total, since CostCalculatorService already
        // distributes each experience's cost across the component lines.
        $pricing['experiences'] = $this->pricingExperienceLines(
            $trip->selectedExperiences()->orderBy('sort_order')->pluck('experience_id')->all(),
            (int) $trip->adults + (int) $trip->children + (int) $trip->infants
        );

        return response()->json(["success" => true, "pricing" => $this->hideInternalMargins($pricing)]);
    }

    /**
     * Strip the internal HRP margin + HCT commission from a traveller-facing
     * pricing payload (req 3.3 — only RP is shown to the traveller, as an
     * informational contribution). HRP/HCT stay server-side for payout/reporting.
     */
    private function hideInternalMargins(array $pricing): array
    {
        unset(
            $pricing['margin_hrp_percent'], $pricing['margin_hrp_amount'],
            $pricing['commission_hct_percent'], $pricing['commission_hct_amount']
        );
        return $pricing;
    }

    /**
     * Itemised experience lines (name + per-person base price) for the pricing
     * summary, in the trip's experience order. Skips experiences with no price.
     */
    protected function pricingExperienceLines(array $expIds, int $groupSize = 1): array
    {
        if (empty($expIds)) return [];
        $exps = Experience::whereIn('id', $expIds)->with('priceSlabs')->get()->keyBy('id');
        $lines = [];
        foreach ($expIds as $id) {
            $e = $exps->get($id);
            if (!$e) continue;
            // Per-person price for THIS group size (req 3.2), matching the calculator.
            $perPerson = $e->slabPricePerPerson(max($groupSize, 1));
            if ($perPerson <= 0) $perPerson = (float) $e->base_cost_per_person;
            if ($perPerson <= 0) continue;
            $lines[] = [
                'name'             => $e->name,
                'price_per_person' => $perPerson,
                'currency'         => $e->price_currency ?: 'INR',
            ];
        }
        return $lines;
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

        // Only attach the trip (and read its payment status) when it belongs to
        // the caller — otherwise a user could reference someone else's trip and
        // learn whether it has a payment. HCT staff may reference any trip.
        $tripId = null;
        $hasPayment = false;
        if ($request->filled("trip_id") && $request->trip_id !== 'guest') {
            $trip = Trip::find($request->trip_id);
            if ($trip && ((int) $trip->user_id === (int) $user->id || $user->isHct())) {
                $tripId = $trip->id;
                $hasPayment = $trip->travellerPayments()->exists();
            }
        }

        $support = SupportRequest::create([
            "user_id" => $user->id,
            "trip_id" => $tripId,
            "message" => $request->message,
            "traveller_status" => $hasPayment ? "client" : "lead",
        ]);

        // Notify the team so support requests aren't silently stranded in the DB (#27).
        $support->setRelation('user', $user);
        $adminEmail = Setting::getValue('site_email') ?: 'info@heco.eco';
        $this->sendMail($adminEmail, new SupportRequestEmail($support), 'support_request:' . $support->id);

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

        // If this email belongs to registered users, flip their opt-in. Every
        // account on the address, not the first one found — the subscription
        // belongs to the inbox, not to one of its roles.
        User::where("email", $email)->update(["newsletter_optin" => true]);

        // Upsert into the canonical subscribers table. If the email was
        // previously unsubscribed, re-subscribe them.
        $subscriber = NewsletterSubscriber::firstOrNew(["email" => $email]);
        $isNew = !$subscriber->exists;
        $subscriber->fill([
            "user_id"         => $user?->id,
            "is_customer"     => (bool) $user,
            "source"          => $subscriber->source ?: ($request->input("source") ?: "landing"),
            "ip_address"      => $request->ip(),
            "unsubscribed_at" => null,
        ]);
        if ($isNew) {
            $subscriber->subscribed_at = now();
        }
        $subscriber->save();

        ActivityLog::create([
            "user_id" => $user?->id,
            "action" => "newsletter_subscribe",
            "details" => $email,
            "ip_address" => $request->ip(),
        ]);

        // Only fire welcome + admin notification on the first real subscribe.
        // Re-subscribes (after unsubscribe) and dedupe submits stay quiet.
        if ($isNew) {
            try {
                \Illuminate\Support\Facades\Mail::to($email)
                    ->send(new NewsletterWelcomeEmail($email, url('/home')));
            } catch (\Throwable $e) {
                \Log::warning("Newsletter welcome mail failed for {$email}: " . $e->getMessage());
            }

            $adminAddress = config('mail.admin_address');
            if ($adminAddress) {
                try {
                    \Illuminate\Support\Facades\Mail::to($adminAddress)
                        ->send(new AdminNewSubscriberEmail($subscriber));
                } catch (\Throwable $e) {
                    \Log::warning("Admin new-subscriber mail failed: " . $e->getMessage());
                }
            }
        }

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
        // Re-verify any pinned provider rates are still valid at confirm time — a
        // pricing row may have been unapproved or deactivated since it was pinned,
        // which would otherwise create an orphan booking on confirm (#16).
        $ratesValid = $this->pinnedRatesValid([
            [$trip->accommodation_provider_id, $trip->accommodation_pricing_id, 'accommodation'],
            [$trip->vehicle_provider_id,       $trip->vehicle_pricing_id,       'transport'],
            [$trip->guide_provider_id,         $trip->guide_pricing_id,         'guide'],
        ]);
        if (!$ratesValid) {
            return response()->json(["error" => "A selected provider rate is no longer available. Please review your Comfort & Partners selections before confirming."], 422);
        }
        // Confirm the trip but keep the stage open — closing the stage is an
        // explicit HCT action (matches the C2 guard against silent downgrades).
        $trip->update(["status" => "confirmed"]);
        // Promote any held SP room bookings → confirmed.
        app(\App\Services\RoomAvailabilityService::class)->confirmForTrip($trip->id);
        // Reserve room inventory for a trip-level accommodation pin (#12).
        $this->bookTripLevelAccommodation($trip);
        // Bill each pinned provider (amount auto-computed as rate × qty) so the
        // provider actually gets an invoice on confirm (#13).
        $this->createProviderInvoices($trip);
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
        // Fan the AI's day suggestions over a deterministic per-experience plan so
        // EVERY selected experience always gets its days. The raw AI response can
        // omit an experience, which previously dropped it from the timeline (e.g.
        // two experiences added but only one shown). rebuildFromExperiences merges
        // the AI titles/descriptions/services on top of the guaranteed structure.
        $result = $itineraryService->rebuildFromExperiences($trip, $parsed['days'] ?? []);

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
            // Unique among staff logins only — the same person may already be
            // a traveller or a provider on this address.
            "email" => ["required", "email", User::uniqueEmailRule(User::HCT_ROLES)],
            "password" => "required|min:8",
            "user_role" => "required|in:administrator,collaborator",
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

        $this->logActivity('hct_user_created', 'User', $user->id, ['role' => $user->user_role, 'email' => $user->email]);
        return response()->json(["success" => true, "user" => $user]);
    }

    protected function updateHctUser(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->user_id);

        // Validated rather than handed straight to update(): a clashing email
        // used to surface as a raw integrity-constraint 500 instead of saying
        // which field was wrong. Uniqueness is scoped to the role the row will
        // hold, and ignores this row so a no-op save doesn't collide with
        // itself.
        $targetRole = $request->input("user_role", $user->user_role);
        $validator = Validator::make($request->all(), [
            "full_name" => "sometimes|required|string|max:255",
            "email" => ["sometimes", "required", "email", User::uniqueEmailRule([$targetRole], $user->id)],
            "user_role" => "sometimes|required|in:administrator,collaborator",
            // nullable, because the form posts the password box on every save
            // and leaving it empty means "keep the current one" — without this
            // an ordinary name or email change fails the length rule on a blank
            // field. The save below only writes it when it is filled.
            "password" => "sometimes|nullable|min:8",
        ], [
            "email.unique" => "Another {$targetRole} account already uses this email.",
            "password.min" => "Password must be at least 8 characters.",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $data = $request->only(["full_name", "email", "user_role", "mobile", "photo"]);
        if ($request->filled("password")) {
            $data["password"] = $request->password;
        }
        $user->update($data);
        $this->logActivity('hct_user_updated', 'User', $user->id, [
            'fields' => array_keys($data),
            'password_reset' => $request->filled('password'),
        ]);
        return response()->json(["success" => true]);
    }

    protected function deactivateHctUser(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->user_id);
        $newStatus = $user->status === "active" ? "inactive" : "active";
        $user->update(["status" => $newStatus]);
        $this->logActivity('hct_user_status_changed', 'User', $user->id, ['status' => $newStatus]);
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
        $user = User::whereIn("user_role", ["administrator", "collaborator"])->findOrFail($request->user_id);
        $user->update(["password" => Str::random(40)]);

        try {
            $token = Password::createToken($user);
            // The role rides along: the reset page is shared with the portal,
            // and this address may also carry a traveller account.
            $resetUrl = route("password.reset", [
                "token" => $token,
                "email" => $user->email,
                "role" => $user->user_role,
            ]);
            $this->sendMail($user->email, new PasswordResetEmail($user->full_name ?: $user->email, $resetUrl), "hct_pw_reset:" . $user->id);
        } catch (\Throwable $e) {
            Log::error("HCT password reset email failed [" . $user->id . "]: " . $e->getMessage());
        }

        $this->logActivity('hct_user_password_reset', 'User', $user->id);
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
        $this->logActivity('ai_prompt_saved', 'AiPrompt', $prompt->id, ['key' => $prompt->key]);
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
        $this->logActivity('ai_prompt_deleted', 'AiPrompt', null, ['ids' => $ids ?: [$request->id]]);
        return response()->json(["success" => true]);
    }

    protected function getActivityLogs(Request $request): JsonResponse
    {
        $logs = ActivityLog::with("user")->orderByDesc("created_at")->paginate(config('pagination.admin_per_page', 20));

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
     * Build the same query the admin newsletter view uses, applying segment +
     * customer + search filters. Always scoped to active subscribers when
     * targeting sends (unsubscribed rows are excluded).
     */
    protected function buildNewsletterQuery(Request $request, bool $activeOnly = false)
    {
        $segment  = $request->input('segment', 'subscribed');
        $customer = $request->input('customer', 'any');
        $search   = trim((string) $request->input('search', ''));

        $query = NewsletterSubscriber::query();

        if ($activeOnly) {
            $query->whereNull('unsubscribed_at');
        } else {
            if ($segment === 'subscribed')   $query->whereNull('unsubscribed_at');
            if ($segment === 'unsubscribed') $query->whereNotNull('unsubscribed_at');
        }

        if ($customer === 'yes') $query->where('is_customer', true);
        if ($customer === 'no')  $query->where('is_customer', false);

        if ($search !== '') {
            $query->where('email', 'like', "%{$search}%");
        }

        return $query;
    }

    protected function getNewsletterSendCount(Request $request): JsonResponse
    {
        $count = $this->buildNewsletterQuery($request, activeOnly: true)->count();
        return response()->json(['count' => $count]);
    }

    protected function sendNewsletterCampaign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:180',
            'body'    => 'required|string|max:200000',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $subject = trim($request->input('subject'));
        $body    = $request->input('body'); // raw HTML, rendered via {!! !!} in the template

        $subscribers = $this->buildNewsletterQuery($request, activeOnly: true)->get();
        if ($subscribers->isEmpty()) {
            return response()->json(['error' => 'No active subscribers match this filter.'], 422);
        }

        $sent = 0;
        $failed = 0;
        $now = now();

        foreach ($subscribers as $sub) {
            try {
                \Illuminate\Support\Facades\Mail::to($sub->email)
                    ->send(new NewsletterCampaignEmail($subject, $body, $sub->email));
                $sub->last_emailed_at = $now;
                $sub->save();
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                \Log::warning("Newsletter campaign send failed for {$sub->email}: " . $e->getMessage());
            }
        }

        ActivityLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'newsletter_campaign_sent',
            'details'    => json_encode([
                'subject' => $subject,
                'sent'    => $sent,
                'failed'  => $failed,
                'filter'  => [
                    'segment'  => $request->input('segment'),
                    'customer' => $request->input('customer'),
                    'search'   => $request->input('search'),
                ],
            ]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'sent'    => $sent,
            'failed'  => $failed,
        ]);
    }

    protected function setSubscriberStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id'           => 'required|integer|exists:newsletter_subscribers,id',
            'unsubscribed' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $sub = NewsletterSubscriber::findOrFail($request->id);
        if ($request->boolean('unsubscribed')) {
            $sub->unsubscribed_at = now();
        } else {
            $sub->unsubscribed_at = null;
            if (!$sub->subscribed_at) $sub->subscribed_at = now();
        }
        $sub->save();

        return response()->json(['success' => true]);
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

            // A rate card is a supplier's. SpController already turns a host or
            // a regional partner away from the page, but the app talks to this
            // endpoint directly — so a regional partner, who sells nothing at
            // all, could still keep one.
            if (!$sp->suppliesServices()) {
                return response()->json([
                    'error' => 'Rates and services are managed by providers offering services.',
                ], 403);
            }

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

        // Rates are deliberately NOT capped. The ten-listing limit belongs to
        // experiences alone: a host offering more than ten experiences is a
        // catalogue HCT wants to look at, whereas a supplier legitimately
        // carries a long rate card — a taxi operator with several vehicles and
        // both plains and hill rates passes ten without doing anything unusual.

        // Per-service-type validation. The dynamic modal in the UI only shows
        // the relevant fields for each service_type, but server-side we still
        // enforce the right combination.
        $serviceType = $request->input('service_type');
        $baseRules = [
            "provider_id"  => "required|exists:service_providers,id",
            "service_type" => "required|in:accommodation,transport,guide,activity,meal,rental,other",
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
                // A hotel is a place before it is a rate: where it stands, how
                // many it sleeps, and when it is open.
                "latitude"          => "nullable|numeric|between:-90,90",
                "longitude"         => "nullable|numeric|between:-180,180",
                "guest_capacity"    => "nullable|integer|min:1|max:2000",
                "seasonality_notes" => "nullable|string|max:1000",
                "photos"            => "nullable|array|max:8",
                "photos.*"          => "image|max:10240",
                "photos_keep"       => "nullable|array|max:8",
                "photos_keep.*"     => "string|max:255",
            ],
            'transport' => [
                // A per-km taxi states the plains rate instead of a flat price,
                // and that rate becomes the row's price below — so the trip
                // cost calculation keeps working off a single field.
                "price"             => "required_without:price_per_km_plains|nullable|numeric|min:0",
                // A taxi priced per kilometre needs no unit picker — the rate
                // itself says what the unit is.
                "unit"              => "required_without:price_per_km_plains|nullable|string|max:50",
                "vehicle_type"      => "required|string|max:100",
                "ac_available"      => "nullable|boolean",
                "vehicle_count"     => "nullable|integer|min:1|max:500",
                "price_per_km_plains" => "nullable|numeric|min:0",
                "price_per_km_hills"  => "nullable|numeric|min:0",
                "ac_extra_cost"       => "nullable|numeric|min:0",
                "vehicle_capacity"  => "nullable|integer|min:1|max:80",
                "driver_allowance"  => "nullable|numeric|min:0",
                // Route distance (km) for per-km pricing (req 3.1): cost = price × distance.
                "distance_km"       => "nullable|numeric|min:0|max:100000",
                // Which vehicle this rate is for, and what it covers.
                "vehicle_make_model"      => "nullable|string|max:120",
                "vehicle_registration_no" => "nullable|string|max:40",
                "vehicle_year"            => "nullable|integer|min:1950|max:" . (date('Y') + 1),
                // New uploads arrive as files; paths the caller wants to keep
                // come back separately so the two never share a key.
                "vehicle_photos"          => "nullable|array|max:8",
                "vehicle_photos.*"        => "image|max:10240",
                "vehicle_photos_keep"     => "nullable|array|max:8",
                "vehicle_photos_keep.*"   => "string|max:255",
            ],
            'guide' => [
                "specialties"       => "nullable|string|max:500",
                "unit"              => "required|string|max:50",
                // `price` is the one-day wage. A multi-day booking where the
                // guide stays the night is a rate of its own, not a multiple.
                "speaks_english"    => "nullable|boolean",
                "languages"         => "nullable|array|max:20",
                "languages.*"       => "string|max:60",
                "wage_multi_day"    => "nullable|numeric|min:0",
                "is_certified"      => "nullable|boolean",
                "has_first_aid"     => "nullable|boolean",
            ],
            'rental' => [
                // `price` is the daily charge; the deposit is held, not earned.
                "rental_item"       => "required|string|max:150",
                "security_deposit"  => "nullable|numeric|min:0",
                "unit"              => "nullable|string|max:50",
            ],
            'activity' => [
                "category"          => "nullable|string|max:100",
                "min_group"         => "nullable|integer|min:1|max:500",
                "max_group"         => "nullable|integer|min:1|max:500",
                "unit"              => "required|string|max:50",
            ],
            'meal' => [
                // e.g. Breakfast / Lunch / Dinner, priced per person or per meal.
                "category"          => "nullable|string|max:100",
                "meal_plan"         => "nullable|string|max:100",
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
            "distance_km"         => $request->filled("distance_km") ? (float) $request->distance_km : null,
            "min_group"           => $request->filled("min_group") ? (int) $request->min_group : null,
            "max_group"           => $request->filled("max_group") ? (int) $request->max_group : null,
            "specialties"         => $request->input("specialties") ?: null,
        ];
        if ($request->has("is_active")) {
            $data["is_active"] = $request->boolean("is_active");
        }

        // Only the fields the chosen service actually asks for are written, so
        // a taxi row never carries a stray deposit and a guide row never a
        // per-kilometre rate.
        $this->applyServiceTypeFields($request, $serviceType, $data);

        if ($serviceType === 'transport') {
            $data["vehicle_make_model"]      = $request->input("vehicle_make_model") ?: null;
            $data["vehicle_registration_no"] = $request->input("vehicle_registration_no") ?: null;
            $data["vehicle_year"]            = $request->filled("vehicle_year") ? (int) $request->vehicle_year : null;
            // Only overwrite a stated answer — a form that omits the toggles
            // (e.g. the bulk rows) must not silently clear what was set before.
            foreach (['driver_included', 'fuel_tolls_extra'] as $flag) {
                if ($request->has($flag)) {
                    $data[$flag] = $request->boolean($flag);
                }
            }
            $photos = $this->storeVehiclePhotos($request);
            if ($photos !== null) {
                $data["vehicle_photos"] = $photos;
            }
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
                // The pending row replaces the live one wholesale once
                // approved, so it starts as a copy of it. Without this, any
                // field the submitting form did not show — the hill rate, the
                // vehicle photos — would be silently dropped on approval.
                $data = array_merge($this->copyableAttributes($existing), $data);
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
     * What carries over from a live rate row to the pending edit of it.
     *
     * Identity, timestamps and the approval trail belong to the row that holds
     * them and must not be copied; everything else is the offer itself.
     */
    protected function copyableAttributes(SpPricing $row): array
    {
        return collect($row->getAttributes())
            ->except([
                'id', 'created_at', 'updated_at',
                'approval_status', 'pending_for_id',
                'submitted_at', 'submitted_by',
                'approved_at', 'approved_by', 'rejection_reason',
                'is_active',
            ])
            ->all();
    }

    /**
     * The per-service fields from the client's data-collection document.
     *
     * A field is written only when the request mentions it, so a form that
     * never showed a section — the bulk-add rows, or the app's shorter
     * editor — leaves what was already stored alone instead of blanking it.
     */
    protected function applyServiceTypeFields(Request $request, string $serviceType, array &$data): void
    {
        $numeric = function (string $key) use ($request, &$data) {
            if ($request->has($key)) {
                $data[$key] = $request->filled($key) ? (float) $request->input($key) : null;
            }
        };
        $integer = function (string $key) use ($request, &$data) {
            if ($request->has($key)) {
                $data[$key] = $request->filled($key) ? (int) $request->input($key) : null;
            }
        };
        $flag = function (string $key) use ($request, &$data) {
            if ($request->has($key)) {
                $data[$key] = $request->boolean($key);
            }
        };
        $text = function (string $key) use ($request, &$data) {
            if ($request->has($key)) {
                $data[$key] = $request->input($key) ?: null;
            }
        };

        switch ($serviceType) {
            case 'transport':
                $flag('ac_available');
                $integer('vehicle_count');
                $numeric('price_per_km_plains');
                $numeric('price_per_km_hills');
                $numeric('ac_extra_cost');

                // The trip cost calculation reads one price and one unit, so a
                // taxi quoted per kilometre answers with its plains rate. The
                // hill rate stays alongside for a route that needs it.
                if (!$request->filled('price') && $request->filled('price_per_km_plains')) {
                    $data['price'] = (float) $request->input('price_per_km_plains');
                    $data['unit'] = $data['unit'] ?: 'per km';
                }
                break;

            case 'guide':
                $flag('speaks_english');
                $numeric('wage_multi_day');
                $flag('is_certified');
                $flag('has_first_aid');
                if ($request->has('languages')) {
                    $languages = array_values(array_filter(
                        array_map('trim', (array) $request->input('languages', [])),
                        fn ($l) => $l !== ''
                    ));
                    $data['languages'] = $languages ?: null;
                }
                break;

            case 'rental':
                $text('rental_item');
                $numeric('security_deposit');
                break;

            case 'accommodation':
                $numeric('latitude');
                $numeric('longitude');
                $integer('guest_capacity');
                $text('seasonality_notes');
                $photos = $this->storeServicePhotos($request);
                if ($photos !== null) {
                    $data['photos'] = $photos;
                }
                break;
        }
    }

    /**
     * Photos for a transport rate: the paths the caller kept, plus anything
     * newly uploaded. Returns null when the request said nothing about photos
     * at all, so the stored column is left alone rather than blanked.
     */
    protected function storeVehiclePhotos(Request $request): ?array
    {
        $keep = $request->input('vehicle_photos_keep');
        $files = array_filter((array) $request->file('vehicle_photos', []));

        if ($keep === null && !$files) {
            return null;
        }

        $paths = array_values(array_filter((array) $keep, 'is_string'));
        foreach ($files as $file) {
            $stored = \App\Services\ImageUploadService::storeUploadedImage($file, 'vehicles', 1200);
            if ($stored) {
                $paths[] = $stored;
            }
        }

        return $paths;
    }

    /**
     * The same contract as storeVehiclePhotos, for the property photos a
     * standard accommodation carries. Null means "the request said nothing",
     * which is different from "the provider removed them all".
     */
    protected function storeServicePhotos(Request $request): ?array
    {
        $keep = $request->input('photos_keep');
        $files = array_filter((array) $request->file('photos', []));

        if ($keep === null && !$files) {
            return null;
        }

        $paths = array_values(array_filter((array) $keep, 'is_string'));
        foreach ($files as $file) {
            $stored = \App\Services\ImageUploadService::storeUploadedImage($file, 'services', 1200);
            if ($stored) {
                $paths[] = $stored;
            }
        }

        return $paths;
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
            ->paginate(config('pagination.admin_per_page', 20));
        return response()->json([
            'rows' => $rows->items(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
                'per_page' => $rows->perPage(),
            ],
        ]);
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
        $pending = SpPricing::with(['serviceProvider.user', 'submitter'])->pending()->findOrFail($request->id);

        if ($pending->pending_for_id) {
            $target = SpPricing::find($pending->pending_for_id);
            $oldPrice = $target?->price;
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
                    'distance_km'       => $pending->distance_km,
                    'min_group'         => $pending->min_group,
                    'max_group'         => $pending->max_group,
                    'specialties'       => $pending->specialties,
                    'notes'             => $pending->notes,
                    'approved_at'       => now(),
                    'approved_by'       => Auth::id(),
                ]);
            }
            // Notify the provider their price change is approved (old → new).
            $this->notifyPricingApproved($pending, $oldPrice);
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
        $this->notifyPricingApproved($pending, null);
        return response()->json(['success' => true, 'mode' => 'created', 'row_id' => $pending->id]);
    }

    /**
     * Email the service provider that their pricing change is approved and live.
     * Fails silently (logged) so approval never breaks on a mail hiccup.
     */
    protected function notifyPricingApproved(SpPricing $pending, $oldPrice = null): void
    {
        $provider = $pending->serviceProvider;
        if (!$provider) {
            return;
        }
        $to = $provider->email ?: (optional($provider->user)->email ?: optional($pending->submitter)->email);
        if (!$to) {
            return;
        }

        $name = $provider->contact_person ?: ($provider->name ?: 'Partner');
        $itemLabel = $pending->description
            ?: ($pending->category ?: ucfirst(str_replace('_', ' ', (string) $pending->service_type)));
        // Only show "old → new" when the price actually changed (true price edits).
        $oldFmt = ($oldPrice !== null && (float) $oldPrice !== (float) $pending->price)
            ? number_format((float) $oldPrice, 2)
            : null;

        $this->sendMail(
            $to,
            new PricingApprovedEmail(
                $name,
                $itemLabel,
                number_format((float) $pending->price, 2),
                $pending->unit ?: null,
                $oldFmt
            ),
            'pricing_approved:' . $pending->id
        );
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

        $this->logActivity('lead_updated', 'Lead', $lead->id, [
            'fields' => array_keys($data), 'stage' => $request->stage,
        ]);
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
        $payments = $query->orderBy("created_at", "desc")->paginate(config('pagination.admin_per_page', 20));
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
            "amount_due" => "nullable|numeric|min:0",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        // An explicit amount always wins (HCT may enter a negotiated/discounted or
        // ad-hoc figure). Only when no amount is supplied do we auto-compute the
        // payable (rate × qty) for a provider that is pinned on the trip for this
        // service type, so the invoice isn't left at ₹0 or a hand-typed guess (#11).
        $trip = Trip::find($request->trip_id);
        if ($request->filled('amount_due')) {
            $amountDue = (float) $request->amount_due;
        } else {
            $amountDue = 0.0;
            $pricingId = match ($request->service_type) {
                'accommodation' => $trip?->accommodation_pricing_id,
                'transport'     => $trip?->vehicle_pricing_id,
                'guide'         => $trip?->guide_pricing_id,
                default         => null,
            };
            if ($trip && $pricingId && ($pricing = SpPricing::find($pricingId))
                && (int) $pricing->service_provider_id === (int) $request->service_provider_id) {
                $amountDue = app(CostCalculatorService::class)->providerPayable($pricing, $trip, $request->service_type);
            }
        }

        $spPayment = SpPayment::create([
            "trip_id" => $request->trip_id,
            "service_provider_id" => $request->service_provider_id,
            "service_type" => $request->service_type,
            "amount_due" => $amountDue,
            "amount_paid" => 0,
            "balance" => $amountDue,
            "notes" => $request->notes,
        ]);

        return response()->json(["success" => true, "id" => $spPayment->id, "amount_due" => $amountDue]);
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

        // Blank means every provider — removal deletes the row, so there is no
        // archived state left to hide.
        $status = $request->get("status", "");
        if (!empty($status) && $status !== "all") {
            $query->where("status", $status);
        }
        // Any role the provider holds, not just the primary one — an HLH that
        // also runs a taxi has to appear under OSP too.
        if ($request->filled("provider_type")) {
            $query->ofType($request->provider_type);
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
        $providers = $query->orderBy("name")->paginate(config('pagination.admin_per_page', 20));
        return response()->json($providers);
    }

    /**
     * Admin manually adds a provider (bypassing the public application form).
     * By default the provider is created already approved and a login + set-
     * password email is issued via finalizeApproval(), so they can sign in.
     * Capability sub-lists / bank details can be filled afterwards on the edit
     * page — this keeps the quick-add form focused.
     */
    protected function addProvider(Request $request): JsonResponse
    {
        if (!Auth::user()?->isHct()) {
            return response()->json(["error" => "Unauthorized"], 403);
        }

        $validator = Validator::make($request->all(), [
            "provider_type" => "required|in:hrp,hlh,osp",
            "name" => "required|string|max:255",
            "email" => "required|email|unique:service_providers,email",
            "phone_1" => "required|string|max:20",
            "region_id" => "required|exists:regions,id",
            "status" => "nullable|in:pending,approved,rejected",
        ], [
            "email.unique" => "A provider with this email already exists.",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        $status = $request->input('status', 'approved');

        $provider = ServiceProvider::create([
            "provider_type" => $request->provider_type,
            "business_type" => $request->business_type,
            "registration_number" => $request->registration_number,
            "year_established" => $request->year_established ?: null,
            "name" => $request->name,
            "contact_person" => $request->contact_person,
            "email" => $request->email,
            "phone_1" => $request->phone_1,
            "phone_2" => $request->phone_2,
            "region_id" => $request->region_id,
            "address" => $request->address,
            "city" => $request->city,
            "postal_code" => $request->postal_code,
            "country" => $request->country,
            "bank_name" => $request->bank_name,
            "bank_ifsc" => $request->bank_ifsc,
            "bank_account_name" => $request->bank_account_name,
            "bank_account_number" => $request->bank_account_number,
            "upi" => $request->upi,
            "services_offered" => $this->applicationArray($request, 'services_offered'),
            "accommodation_categories" => $this->applicationArray($request, 'accommodation_categories'),
            "vehicle_types" => $this->applicationArray($request, 'vehicle_types'),
            "guide_types" => $this->applicationArray($request, 'guide_types'),
            "activity_types" => $this->applicationArray($request, 'activity_types'),
            "notes" => $request->input('notes', $request->input('description')),
            "status" => $status,
            "approved_at" => $status === 'approved' ? now() : null,
            "approved_by" => $status === 'approved' ? Auth::id() : null,
            "last_updated_by" => Auth::id(),
            "last_updated_by_role" => 'admin',
        ]);

        // Approved on creation → issue the login + set-password email so the
        // provider can sign in (same side-effect as approving an application).
        if ($status === 'approved') {
            $this->finalizeApproval($provider->fresh());
        }

        return response()->json(["success" => true, "provider_id" => $provider->id]);
    }

    protected function editProvider(Request $request): JsonResponse
    {
        // Hard gate — status (and every other admin-controlled field) can only be
        // changed by an HCT user, even if the request comes through portal /ajax.
        if (!Auth::user()?->isHct()) {
            return response()->json(["error" => "Unauthorized"], 403);
        }
        $provider = ServiceProvider::findOrFail($request->provider_id);
        $previousStatus = $provider->status;
        $wasApproved = $previousStatus === 'approved';

        // The status drives who can sign in and who gets sold, so it is the one
        // field here that cannot be taken on trust — an unknown value used to
        // reach the enum column and come back as a 500.
        if ($request->has('status')) {
            $validator = Validator::make($request->all(), [
                'status' => ['required', Rule::in(array_keys(ServiceProvider::STATUS_LABELS))],
            ], ['status.in' => 'That is not a status a provider can be set to.']);
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }
        }

        $data = $request->only([
            "name", "contact_person", "email", "phone_1", "phone_2",
            "address", "city", "postal_code", "country",
            "business_type", "registration_number", "year_established",
            "region_id", "provider_type",
            "bank_name", "bank_ifsc", "bank_account_name",
            "bank_account_number", "upi", "services_offered",
            "accommodation_categories", "vehicle_types", "guide_types", "activity_types",
            "notes", "status",
        ]);
        // Per-provider admin markup (req 3.3) — HCT-only (this whole method is gated).
        // Clamp to 0–100%. Blank means "no markup" (0).
        if ($request->has('markup_percent')) {
            $data['markup_percent'] = max(0, min(100, (float) $request->input('markup_percent', 0)));
        }

        // Track approval timestamp when status flips to approved for the first time
        if (($data['status'] ?? null) === 'approved' && !$wasApproved) {
            $data['approved_at'] = now();
            $data['approved_by'] = Auth::id();
        }
        $data['last_updated_by'] = Auth::id();
        $data['last_updated_by_role'] = 'admin';
        $provider->update($data);

        $this->applyProviderBan($provider->fresh(), $previousStatus);

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
     * Keep the linked login in step with a ban.
     *
     * A ban is the one status that shuts the door: the account is deactivated,
     * so neither the app nor the portal will authenticate it, and every API
     * token it holds stops working. Any other status hands the login back —
     * that is what un-banning is, and it has to be symmetrical or an admin
     * could ban someone and never let them back in.
     *
     * 'hidden' deliberately does nothing here. It is a pause, not a
     * punishment: the provider keeps their login and their data, they are just
     * not offered to travellers while it lasts.
     *
     * An HCT login is never touched — staff sign in to the admin with it, and
     * a provider record must not be able to lock the admin out.
     *
     * Only the transition acts. Reading the new status alone would reactivate
     * an account on every unrelated save, undoing a deactivation that was done
     * for some other reason entirely.
     */
    protected function applyProviderBan(ServiceProvider $provider, string $previousStatus): void
    {
        $wasBanned = $previousStatus === 'banned';
        if ($wasBanned === $provider->isBanned() || !$provider->user_id) {
            return;
        }
        $user = \App\Models\User::find($provider->user_id);
        if (!$user || $user->isHct()) {
            return;
        }

        $user->update(['status' => $provider->isBanned() ? 'inactive' : 'active']);
        if ($provider->isBanned()) {
            // Every way in closes at once. The status alone only stops the next
            // sign-in — an app already holding a token, or a browser already
            // holding a session, would carry on working until either expired.
            \App\Models\ApiToken::where('user_id', $user->id)->delete();
            if (config('session.driver') === 'database') {
                \DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->delete();
            }
        }
        // Audit trail only. There is no "un-banned" state to record — the ban
        // coming off just means the login goes back to active, so that is what
        // this says.
        $this->logActivity(
            $provider->isBanned() ? 'provider_banned' : 'provider_activated',
            'ServiceProvider',
            $provider->id
        );
    }

    /**
     * Side-effects of approving an SP — runs from approveProvider AND from
     * editProvider when status flips to approved.
     *
     * 1. Ensures the SP has a linked User account — reusing their existing
     *    provider account for this address, or creating one.
     * 2. Generates a password-reset token and emails the SP a set-password
     *    link via SpApplicationApprovedEmail.
     *
     * Idempotent — if the SP already has user_id, skips creation. If the
     * mail send fails, logs it and continues (non-fatal).
     */
    protected function finalizeApproval(ServiceProvider $provider): void
    {
        // 1. Ensure the provider has a login of its own.
        //
        // The role is checked, not just the link. Matching on email alone used
        // to find the applicant's *traveller* account and overwrite its
        // user_role, costing them their traveller identity to gain a provider
        // one — and rows linked that way before emails were unique per role are
        // still in the table. A traveller account found here is therefore left
        // exactly as it is and a provider account is used in its place.
        $user = $provider->user_id ? User::find($provider->user_id) : null;

        if ($provider->email && (!$user || (!$user->isServiceProvider() && !$user->isHct()))) {
            $user = User::findByEmailForRoles($provider->email, User::PROVIDER_ROLES)
                ?: User::create([
                    'full_name' => $provider->name,
                    'email'     => $provider->email,
                    'password'  => Str::random(40),
                    'auth_type' => 'email',
                    'user_role' => 'provider',
                ]);
            $provider->forceFill(['user_id' => $user->id])->save();
        }

        // 2. Email the SP a set-password link so they can log in.
        if (!$user || !$user->email) {
            Log::warning('finalizeApproval: SP has no linked user/email; skipping mail', ['provider_id' => $provider->id]);
            return;
        }

        // Nothing to bring back in line: the account's role is 'provider' and
        // stays that way however their types change. This used to copy
        // provider_type onto the user and repair the two when they drifted,
        // which was the cost of storing one fact in two tables.

        $providerLabel = match ($provider->provider_type) {
            'hrp' => ServiceProvider::TYPE_LABELS['hrp'],
            'hlh' => ServiceProvider::TYPE_LABELS['hlh'],
            'osp' => ServiceProvider::TYPE_LABELS['osp'],
            default => 'Partner',
        };
        $contactName = $provider->contact_person ?: $provider->name;
        try {
            if ($user->password_set_at) {
                // The provider already verified their email and set a password
                // during signup (OTP flow) — just tell them they're live.
                $this->sendMail(
                    $user->email,
                    new SpApplicationApprovedEmail($contactName, $providerLabel),
                    'sp_approved:' . $provider->id
                );
            } else {
                // Never completed signup (e.g. an admin-added provider) — include
                // a link so they can set a password and get in.
                $token = Password::createToken($user);
                $setPasswordUrl = route('password.reset', [
                    'token' => $token,
                    'email' => $user->email,
                    // Their traveller account may share this address; the link
                    // must set the password on the provider login it approved.
                    'role' => $user->user_role,
                ]);
                $this->sendMail(
                    $user->email,
                    new SpApplicationApprovedEmail($contactName, $providerLabel, $setPasswordUrl),
                    'sp_approved:' . $provider->id
                );
            }
        } catch (\Throwable $e) {
            Log::error('SP approval email failed [' . $provider->id . ']: ' . $e->getMessage());
        }
    }

    /**
     * Take a profile picture off the request, if it sent one.
     *
     * Shared by the portal's profile form, which posts everything at once, and
     * the app's photo-only endpoint. Says nothing about the photo when the
     * request says nothing — a save of other fields must not wipe it.
     *
     * Returns an error response, or null when there is nothing wrong.
     */
    protected function applyProviderPhoto(Request $request, array &$data): ?JsonResponse
    {
        if ($request->hasFile('photo')) {
            $stored = \App\Services\ImageUploadService::storeUploadedImage(
                $request->file('photo'), 'providers', 600
            );
            if (!$stored) {
                return response()->json(['error' => 'Failed to upload the photo. Use JPG, PNG, or WebP.'], 422);
            }
            $data['photo'] = $stored;
        } elseif ($request->boolean('remove_photo')) {
            $data['photo'] = null;
        }

        return null;
    }

    /**
     * Change just the profile picture.
     *
     * Its own action because the app's profile endpoint is a PUT, and PHP does
     * not parse a multipart body on PUT — and because changing a picture should
     * not mean re-posting the whole profile.
     */
    protected function updateSpPhoto(Request $request): JsonResponse
    {
        [$provider, $err] = $this->resolveApprovedSp();
        if ($err) return $err;

        $data = [];
        if ($errResponse = $this->applyProviderPhoto($request, $data)) {
            return $errResponse;
        }
        if (!array_key_exists('photo', $data)) {
            return response()->json(['error' => 'No photo was sent.'], 422);
        }

        $data['last_updated_by'] = Auth::id();
        $data['last_updated_by_role'] = 'provider';
        $provider->update($data);

        return response()->json([
            'success' => true,
            'provider' => \App\Http\Resources\ProviderAccountResource::make($provider->fresh()),
        ]);
    }

    /**
     * File one more verification document after the application went in.
     *
     * A member who was asked for a permit, or who only had their ID to hand at
     * signup, had no way to send it — the app's Documents screen could add a
     * row to itself and nothing else.
     *
     * Arrives as `documents[]` with `document_labels[]`, exactly as on the
     * application form, so the same helper stores it in the same place under
     * the same rules. A document is a document whenever it turns up.
     */
    protected function addSpDocument(Request $request): JsonResponse
    {
        [$provider, $err] = $this->resolveApprovedSp();
        if ($err) return $err;

        $validator = Validator::make($request->all(), [
            "documents" => "required|array|max:8",
            "documents.*" => "file|mimes:jpg,jpeg,png,pdf|max:2048",
            "document_labels" => "required|array|max:8",
            "document_labels.*" => "string|max:100",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        // Same storage as a document filed at signup — same folder, same
        // naming, same rules. A document is a document whenever it turns up.
        $stored = app(AuthService::class)->storeDocuments(
            array_values((array) $request->file('documents', [])),
            (array) $request->input('document_labels', []),
        );
        if (!$stored) {
            return response()->json(["error" => "The document could not be saved."], 422);
        }

        $provider->update([
            'documents' => array_merge((array) $provider->documents, $stored),
            'last_updated_by' => Auth::id(),
            'last_updated_by_role' => 'provider',
        ]);

        return response()->json([
            'success' => true,
            'provider' => \App\Http\Resources\ProviderAccountResource::make($provider->fresh()),
        ]);
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
            // HRP competences — see the add_hrp_competences migration.
            "education_level" => "nullable|string|max:100",
            "education_notes" => "nullable|string|max:1000",
            "english_level" => "nullable|string|max:100",
            "computer_skill_level" => "nullable|string|max:100",
            "work_experience" => "nullable|array",
            "work_experience.*.role" => "nullable|string|max:255",
            "work_experience.*.organisation" => "nullable|string|max:255",
            "work_experience.*.years" => "nullable|string|max:100",
            "work_experience.*.description" => "nullable|string|max:1000",
            "causes_note" => "nullable|string|max:2000",
            "community_note" => "nullable|string|max:2000",
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

        // Competences belong to a regional partner. Silently dropping them for
        // anyone else keeps a non-HRP from stuffing the column via a raw post.
        if ($provider->isRegionalPartner()) {
            $data += $request->only([
                "education_level", "education_notes", "english_level",
                "computer_skill_level", "causes_note", "community_note",
            ]);
            // Blank rows the repeater leaves behind should not be stored.
            $roles = array_values(array_filter(
                $request->input('work_experience', []) ?: [],
                fn ($row) => is_array($row) && trim(implode('', array_map('strval', $row))) !== '',
            ));
            $data['work_experience'] = $roles;
        }
        if ($err = $this->applyProviderPhoto($request, $data)) {
            return $err;
        }

        $data['last_updated_by'] = $user->id;
        $data['last_updated_by_role'] = 'provider';
        $provider->update($data);

        return response()->json([
            'success' => true,
            'photo'   => $provider->fresh()->photo,
        ]);
    }

    /**
     * Trips the logged-in service provider is attached to. The primary link is
     * TripDayService.service_provider_id (per-day services); for HRPs the
     * tripRegions.hrp_id link is also folded in. Returns one row per trip with
     * the day numbers and the services the SP is providing.
     */
    /**
     * Trip context an assigned provider legitimately needs to prepare: group
     * size, where the guests are coming from, and the pickup/drop points.
     * Deliberately no traveller identity — the portal keeps that from SPs.
     */
    protected function spTripContext(Trip $trip): array
    {
        return [
            'region' => $trip->tripRegions->pluck('region.name')->filter()->unique()->implode(', '),
            'adults' => (int) $trip->adults,
            'children' => (int) $trip->children,
            'infants' => (int) $trip->infants,
            'traveller_origin' => $trip->traveller_origin,
            'pickup_location' => $trip->pickup_location,
            'drop_location' => $trip->drop_location,
            'notes' => $trip->operations_notes,
        ];
    }

    protected function getSpAssignedTrips(Request $request): JsonResponse
    {
        [$provider, $err] = $this->resolveApprovedSp();
        if ($err) return $err;

        $tripsById = [];

        // 1) Per-day services assigned to this SP.
        $services = TripDayService::where('service_provider_id', $provider->id)
            ->with(['tripDay.trip.tripRegions.region'])
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
                ] + $this->spTripContext($trip) + [
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

        // 2) HRP-managed regions (if this provider is an HRP). Match on region_id —
        // the provider's region_id is populated at application, whereas hrp_id was
        // never written anywhere, so the old hrp_id match was always empty (#37).
        if (strtolower((string) $provider->provider_type) === 'hrp' && $provider->region_id) {
            $tripRegions = TripRegion::where('region_id', $provider->region_id)
                ->with('trip.tripRegions.region')
                ->get();
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
                    ] + $this->spTripContext($trip) + [
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
        // Search across name / email / phone.
        $search = trim((string) $request->get("search", ""));
        if ($search !== "") {
            $query->where(function ($q) use ($search) {
                $q->where("name", "like", "%{$search}%")
                  ->orWhere("email", "like", "%{$search}%")
                  ->orWhere("phone_1", "like", "%{$search}%");
            });
        }

        $applications = $query->orderBy("created_at", "desc")->paginate(config('pagination.admin_per_page', 20));
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

        $this->logActivity('provider_approved', 'ServiceProvider', $provider->id, ['first_time' => !$wasApproved]);
        return response()->json(["success" => true]);
    }

    protected function rejectProvider(Request $request): JsonResponse
    {
        $provider = ServiceProvider::findOrFail($request->provider_id);
        $provider->update(["status" => "rejected"]);
        $this->logActivity('provider_rejected', 'ServiceProvider', $provider->id);
        return response()->json(["success" => true]);
    }

    /**
     * Decide what the account behind a provider is allowed to suffer when that
     * provider is removed or deleted.
     *
     * A provider is not always only a provider. Someone can travel with HECO
     * first and sign up to host later, and the account they sign up with reads
     * 'provider' from then on while their trips stay behind it. Reading the
     * role alone therefore misses exactly the case this guards against, and the
     * traveller footprint has to be counted as well.
     *
     * Returns:
     *   keep_login — the account outlives this provider (traveller history or
     *                HCT staff). It must not be deactivated or deleted; it only
     *                loses its provider role.
     *   deletable  — the user row may be physically deleted: provider-only, and
     *                nothing anywhere still points at it. Every reference
     *                checked below is a RESTRICT foreign key, so deleting under
     *                one fails at the database — they are checked up front
     *                rather than discovered as a 500.
     *
     * @return array{keep_login: bool, deletable: bool, reason: ?string}
     */
    protected function providerUserDisposition(?int $userId): array
    {
        if (!$userId) {
            return ['keep_login' => false, 'deletable' => false, 'reason' => null];
        }
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return ['keep_login' => false, 'deletable' => false, 'reason' => null];
        }
        if ($user->isHct()) {
            return ['keep_login' => true, 'deletable' => false, 'reason' => 'account is an HCT staff login'];
        }

        // Traveller-side belongings. Any one of these means a real person still
        // uses this login for something other than hosting.
        $travellerOwned = [
            'trip'    => \App\Models\Trip::where('user_id', $userId)->count(),
            'lead'    => \App\Models\Lead::where('user_id', $userId)->count(),
            'payment' => \App\Models\TravellerPayment::where('user_id', $userId)->count(),
        ];
        $held = array_filter($travellerOwned);

        if ($user->isTraveller() || !empty($held)) {
            return [
                'keep_login' => true,
                'deletable'  => false,
                'reason'     => empty($held)
                    ? 'account is also a traveller'
                    : 'account is also a traveller (' . $this->describeCounts($held) . ')',
            ];
        }

        // Provider-only, but rows that are neither traveller history nor
        // provider inventory can still block the delete. Keeping the (now
        // deactivated) user row is better than destroying their support or
        // chat history to force one through.
        $otherOwned = array_filter([
            'support request' => \App\Models\SupportRequest::where('user_id', $userId)->count(),
            'AI conversation' => \App\Models\AiConversation::where('user_id', $userId)->count(),
        ]);
        if (!empty($otherOwned)) {
            return [
                'keep_login' => false,
                'deletable'  => false,
                'reason'     => 'account still owns ' . $this->describeCounts($otherOwned),
            ];
        }

        return ['keep_login' => false, 'deletable' => true, 'reason' => null];
    }

    /**
     * "2 trips, 1 payment" — for the toast that explains why an account was
     * kept. Keys are singular labels, values their counts.
     */
    protected function describeCounts(array $counts): string
    {
        $parts = [];
        foreach ($counts as $label => $n) {
            $parts[] = $n . ' ' . \Illuminate\Support\Str::plural($label, $n);
        }
        return implode(', ', $parts);
    }

    /**
     * Strip the provider role off an account that is being kept, so the portal
     * stops offering provider screens while the traveller side keeps working.
     * Scoped to the provider role — an HCT login is never rewritten.
     */
    protected function demoteProviderUserToTraveller(int $userId): void
    {
        \App\Models\User::where('id', $userId)
            ->where('user_role', 'provider')
            ->update(['user_role' => 'traveller']);
    }

    /**
     * Delete a provider — the shared body of the single-row and bulk paths;
     * both callers check for HCT admin first.
     *
     * Removal is a real delete, not a status flag. A row kept under a 'removed'
     * status still owns its email, and `unique:service_providers,email` counts
     * it — so a member who was taken off the platform could never apply again,
     * and was told "an application with this email already exists" instead.
     * Nothing is archived here: once the row is gone the address is free and a
     * fresh application behaves like any other.
     *
     * Blocked if any sp_payments rows reference this provider (financial
     * history) — those must be archived by the admin first.
     *
     * Cascades automatically via DB FKs:
     *   - sp_pricing            → cascadeOnDelete
     *   - sp_availability       → cascadeOnDelete
     *   - sp_room_bookings      → cascade via sp_pricing
     *   - trip_day_services     → service_provider_id set NULL (history kept)
     *   - trip_day_services     → sp_pricing_id set NULL via sp_pricing FK
     *
     * The linked user row is deleted only when it belongs to this provider and
     * nothing else — see providerUserDisposition(). A login that is also a
     * traveller's survives, minus its provider role.
     *
     * Returns a blocker rather than throwing, so a bulk caller can skip this
     * provider and carry on with the rest of the selection.
     *
     * @return array{ok: bool, error: ?string, blockers: ?array, user: string, reason: ?string, detached_experiences: int}
     */
    protected function hardDeleteProvider(ServiceProvider $provider): array
    {
        $fail = fn (string $error, ?array $blockers = null) => [
            'ok' => false, 'error' => $error, 'blockers' => $blockers,
            'user' => 'none', 'reason' => null, 'detached_experiences' => 0,
        ];

        // Only sp_payments hard-blocks — financial history must be archived
        // separately. Experiences are auto-detached: hlh_id set to NULL (now
        // nullable as of 2026_05_16_140000) and the experience deactivated so
        // it stops appearing in active listings.
        $paymentCount = \App\Models\SpPayment::where('service_provider_id', $provider->id)->count();
        if ($paymentCount > 0) {
            return $fail(
                "Cannot delete — provider has {$paymentCount} payment record(s). Archive those first.",
                ['sp_payments' => $paymentCount]
            );
        }

        $userId = $provider->user_id;
        $providerId = $provider->id;
        $disposition = $this->providerUserDisposition($userId);
        $detachedExperiences = \App\Models\Experience::where('hlh_id', $provider->id)->count();

        \DB::transaction(function () use ($provider, $userId, $disposition) {
            // Detach hosted experiences (hlh_id → NULL, is_active → false)
            // so traveller listings stop surfacing them. Admin can reassign
            // them to a new HLH later if needed.
            \App\Models\Experience::where('hlh_id', $provider->id)->update([
                'hlh_id'    => null,
                'is_active' => false,
            ]);
            $provider->delete();  // cascades to sp_pricing, sp_availability, sp_room_bookings
            if (!$userId) {
                return;
            }
            if ($disposition['deletable']) {
                \App\Models\User::where('id', $userId)->delete();
                return;
            }
            // service_providers.user_id is nullOnDelete, so the link is already
            // gone. Hand the account back to the traveller side — it keeps its
            // trips and can sign in to the portal, it just stops being a
            // provider. Its email is free for a fresh application either way,
            // because uniqueness is per role.
            $this->demoteProviderUserToTraveller($userId);
            if ($disposition['keep_login']) {
                \App\Models\User::where('id', $userId)->update(['status' => 'active']);
            }
        });

        $userOutcome = 'none';
        if ($userId) {
            $userOutcome = $disposition['deletable']
                ? 'deleted'
                : ($disposition['keep_login'] ? 'kept' : 'orphaned');
        }
        $this->logActivity('provider_deleted', 'ServiceProvider', $providerId);

        return [
            'ok' => true, 'error' => null, 'blockers' => null,
            'user' => $userOutcome,
            'reason' => $disposition['reason'],
            'detached_experiences' => $detachedExperiences,
        ];
    }

    /**
     * Delete one provider. HCT admin only.
     */
    protected function removeProvider(Request $request): JsonResponse
    {
        if (!Auth::user() || !Auth::user()->isHctAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $provider = ServiceProvider::findOrFail($request->provider_id);
        $result = $this->hardDeleteProvider($provider);

        if (!$result['ok']) {
            return response()->json(array_filter([
                'error'    => $result['error'],
                'blockers' => $result['blockers'],
            ]), 422);
        }

        $message = 'Provider deleted.';
        if ($result['user'] === 'kept') {
            $message .= ' Login kept — ' . $result['reason'] . '.';
        } elseif ($result['user'] === 'orphaned') {
            $message .= ' Login kept (deactivated) — ' . $result['reason'] . '.';
        }
        return response()->json([
            'success' => true,
            'message' => $message,
            'user'    => $result['user'],
            'detached_experiences' => $result['detached_experiences'],
        ]);
    }

    /**
     * Bulk delete. Each provider goes through the same blocker and account
     * checks as the single-row path; a blocked one is named in the summary and
     * the rest still go through.
     */
    protected function bulkRemoveProviders(Request $request): JsonResponse
    {
        if (!Auth::user() || !Auth::user()->isHctAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $ids = $this->bulkIds($request);
        if (empty($ids)) {
            return response()->json(['error' => 'No providers selected'], 422);
        }

        $deleted = 0;
        $keptLogins = [];
        $blocked = [];
        foreach (ServiceProvider::whereIn('id', $ids)->get() as $provider) {
            $name = $provider->name;
            $result = $this->hardDeleteProvider($provider);
            if (!$result['ok']) {
                $blocked[] = $name . ' — ' . $result['error'];
                continue;
            }
            if (in_array($result['user'], ['kept', 'orphaned'], true)) {
                $keptLogins[] = $name;
            }
            $deleted++;
        }

        $msg = $deleted . ' provider(s) deleted';
        if (!empty($keptLogins)) {
            $msg .= '. Login kept: ' . implode(', ', $keptLogins);
        }
        if (!empty($blocked)) {
            $msg .= '. Blocked: ' . implode('; ', $blocked);
        }
        return response()->json([
            'success'     => true,
            'message'     => $msg,
            'deleted'     => $deleted,
            'kept_logins' => count($keptLogins),
            'blocked'     => count($blocked),
        ]);
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

        $query->orderBy("continent")->orderBy("country")->orderBy("sort_order");

        // List view opts into pagination; the cascading-filter dropdown builder
        // (buildRegionMap) calls this without the flag and needs the full set.
        if ($request->boolean("paginate")) {
            $regions = $query->paginate(config('pagination.admin_per_page', 20));
            return response()->json([
                "data" => $regions->items(),
                "pagination" => [
                    "current_page" => $regions->currentPage(),
                    "last_page" => $regions->lastPage(),
                    "total" => $regions->total(),
                    "per_page" => $regions->perPage(),
                ],
            ]);
        }

        $regions = $query->get();
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
            "image" => "nullable|file|mimes:jpg,jpeg,png,webp|max:6144",
        ];

        $validator = Validator::make($request->all(), $rules, [
            "name.unique" => "A region with this name already exists.",
            "image.mimes" => "Please choose a JPG, PNG or WEBP image.",
            "image.max"   => "The image must be 6 MB or smaller.",
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

        // Anchor points (map markers) — accept a JSON string or an array; the
        // model casts to array. Only overwrite when the field is present (#25).
        if ($request->has("anchor_points")) {
            $anchors = $request->input("anchor_points");
            if (is_string($anchors)) {
                $decoded = json_decode($anchors, true);
                $anchors = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
            }
            $data["anchor_points"] = is_array($anchors) ? array_values($anchors) : null;
        }

        // sort_order — allow reordering on UPDATE too (was create-only, #25).
        if ($request->filled("sort_order")) {
            $data["sort_order"] = (int) $request->sort_order;
        }

        $isNew = !$request->filled("region_id");
        $region = $isNew ? new Region() : Region::findOrFail($request->region_id);
        $oldImage = $region->image;

        // Image upload — stored year/month-wise + resized (ImageUploadService, #25).
        if ($request->hasFile("image")) {
            $path = \App\Services\ImageUploadService::storeUploadedImage($request->file("image"), 'regions', 1200);
            if (!$path) {
                return response()->json(["error" => "Could not process the image. Please try a different file."], 422);
            }
            $data["image"] = $path;
        }

        if ($isNew && !isset($data["sort_order"])) {
            $data["sort_order"] = Region::max("sort_order") + 1;
        }

        $region->fill($data)->save();

        // Delete the replaced image only after the new one is safely stored.
        if (!empty($data["image"]) && $oldImage && $oldImage !== $data["image"]) {
            \App\Services\ImageUploadService::deleteLocal($oldImage);
        }

        $this->logActivity('region_saved', 'Region', $region->id, [
            'name' => $region->name, 'created' => $isNew,
        ]);
        return response()->json([
            "success" => $isNew ? "Region created successfully" : "Region updated successfully",
            "region" => $region,
        ]);
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

        $currencies = $query->orderBy("sort_order")->paginate(config('pagination.admin_per_page', 20));
        return response()->json([
            "data" => $currencies->items(),
            "pagination" => [
                "current_page" => $currencies->currentPage(),
                "last_page" => $currencies->lastPage(),
                "total" => $currencies->total(),
                "per_page" => $currencies->perPage(),
            ],
        ]);
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
        // One Status filter covering both axes, so HCT does not need a separate
        // page to find what is waiting on them:
        //   "1" / "0"                    → live or switched off
        //   draft|pending|approved|...   → where it stands in review
        //
        // They stay separate columns because they answer different questions —
        // an approved listing switched off for the season is not "pending".
        if ($request->filled("status")) {
            $status = (string) $request->status;
            if (in_array($status, ["0", "1"], true)) {
                $query->where("is_active", (bool) $status);
            } elseif (in_array($status, ["draft", "pending", "approved", "rejected"], true)) {
                $status === "pending"
                    ? $query->pending()      // includes a live listing with a parked edit
                    : $query->where("approval_status", $status);
            }
        }

        $experiences = $query->orderBy("sort_order")->paginate(config('pagination.admin_per_page', 20));

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
        // A draft is a half-finished listing kept for later — the client's
        // reason being that "many users won't have all the information or
        // photos ready in one session". Demanding a full field set in order to
        // store one defeats the point, so a draft insists only on the name:
        // without it the provider cannot find their own draft again. Nothing is
        // published or reviewed off a draft, and submitting for review still
        // validates in full.
        $isDraft = $request->input('approval_status') === 'draft';
        $unlessDraft = fn (string $rules) => $isDraft
            ? str_replace('required', 'nullable', $rules)
            : $rules;

        $validator = Validator::make($request->all(), [
            "id"                => "nullable|integer|exists:experiences,id",
            "name"              => "required|string|max:255",
            "region_id"         => $unlessDraft("required|integer|exists:regions,id"),
            "hlh_id"            => "required|integer|exists:service_providers,id",
            "type"              => $unlessDraft("required|string|max:100"),
            // Which of the three structural categories this is — it decides
            // which fields the form even shows. Nullable so rows created before
            // categories existed can still be saved by HCT.
            "category"          => "nullable|string|max:120",
            "short_description" => $unlessDraft("required|string|max:500"),
            "duration_type"     => $unlessDraft("required|in:less_than_day,single_day,multi_day"),
            // Capacity of an experiential stay — the client's "no of rooms, no
            // guests". Distinct from group_size_max, which is the largest party
            // this experience will take rather than what the place sleeps.
            "total_rooms"       => "nullable|integer|min:1|max:500",
            "total_guests"      => "nullable|integer|min:1|max:2000",
            "room_rates"        => "nullable|array|max:40",
            "room_rates.*.occupancy" => "nullable|string|max:100",
            "room_rates.*.meal_plan" => "nullable|string|max:100",
            "room_rates.*.price"     => "nullable|numeric|min:0",
            "addons"            => "nullable|array|max:20",
            "addons.*.name"     => "nullable|string|max:150",
            "addons.*.description" => "nullable|string|max:1000",
            "addons.*.price"    => "nullable|numeric|min:0",
            "addons.*.price_unit"  => "nullable|string|max:50",
        ], [
            "name.required"              => "Please enter an experience name.",
            "region_id.required"         => "Please choose a region.",
            "region_id.exists"           => "The selected region is invalid.",
            // Says what to do, not just what is wrong: with no active host on
            // the platform the dropdown is empty, and this is the only thing
            // that tells the admin one has to be added and activated first.
            "hlh_id.required"            => "No provider selected. An experience must belong to an active provider — add and activate one first.",
            "hlh_id.exists"              => "The selected provider no longer exists.",
            "type.required"              => "Please choose an experience type.",
            "short_description.required" => "Please write a short description.",
            "duration_type.required"     => "Please choose a duration type.",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 422);
        }

        // Which providers may host is decided by the form's dropdown, which
        // lists active HLHs only. There is no second check here: this action is
        // HCT-only, so the only way to post anything else is an HCT user going
        // round their own form.
        $data = $request->except(["_token", "save_experience", "experience_days", "gallery", "price_slabs"]);

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

        // Seasonal price variation now comes from the friendly row editor
        // (label + % change) rather than a raw JSON textarea. Keep only rows the
        // admin actually named, and store the % as a clean number.
        if (isset($data["seasonal_price_variation"]) && is_array($data["seasonal_price_variation"])) {
            $data["seasonal_price_variation"] = array_values(array_filter(array_map(function ($row) {
                if (!is_array($row)) return null;
                $label = trim((string) ($row["label"] ?? ""));
                if ($label === "") return null;
                return [
                    "label" => $label,
                    "adjustment_percent" => (float) ($row["adjustment_percent"] ?? 0),
                ];
            }, $data["seasonal_price_variation"]))) ?: null;
        }

        if (!empty($data["slug"])) {
            $data["slug"] = Str::slug($data["slug"]);
        } elseif (!empty($data["name"])) {
            $data["slug"] = Str::slug($data["name"]);
        }

        // Per-person price slabs by group size (req 3.2). Normalize the posted rows to
        // a {min_persons => price_per_person} map (deduped, sorted, only valid rows).
        $slabs = [];
        foreach ((array) $request->input('price_slabs', []) as $row) {
            $mp = (int) ($row['min_persons'] ?? 0);
            $pp = (float) ($row['price_per_person'] ?? 0);
            if ($mp >= 1 && $pp > 0) $slabs[$mp] = $pp;
        }
        ksort($slabs);

        // Headline base_cost_per_person: when slabs are set, use the cheapest per-person
        // (the "from" price shown on cards) and as the calculator's fallback; otherwise
        // keep it in lockstep with the component breakdown for legacy experiences.
        if (!empty($slabs)) {
            $data["base_cost_per_person"] = min($slabs);
        } else {
            $data["base_cost_per_person"] = (float) ($data["cost_accommodation"] ?? 0)
                + (float) ($data["cost_logistics"] ?? 0)
                + (float) ($data["cost_guide"] ?? 0)
                + (float) ($data["cost_activities"] ?? 0)
                + (float) ($data["cost_other"] ?? 0);
        }

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

        // Day-wise itinerary. Replaced ONLY when the caller sent it — these used
        // to be deleted and rebuilt unconditionally, so any save that omitted
        // them wiped the itinerary. That is no longer a rare edge: per-category
        // forms omit whole sections by design (an experiential stay has no
        // day-by-day plan), and the mobile API can post a partial update too.
        if ($request->has('experience_days')) {
            $experience->days()->delete();
            foreach ((array) $request->input('experience_days', []) as $idx => $dayData) {
                $experience->days()->create([
                    'day_number' => $dayData['day_number'] ?? ($idx + 1),
                    'title' => $dayData['title'] ?? null,
                    'short_description' => $dayData['short_description'] ?? null,
                    'start_time' => $dayData['start_time'] ?? null,
                    'end_time' => $dayData['end_time'] ?? null,
                    // Always a list. The column is cast to array and every
                    // reader — the admin table, the detail page, the app —
                    // iterates it, so a string stored here takes those screens
                    // down rather than showing one odd row.
                    'inclusions' => $this->normaliseInclusions($dayData['inclusions'] ?? []),
                    'sort_order' => $idx,
                ]);
            }
        }

        // Per-person price slabs (req 3.2) — same rule, same reason.
        if ($request->has('price_slabs')) {
            $experience->priceSlabs()->delete();
            foreach ($slabs as $mp => $pp) {
                $experience->priceSlabs()->create(['min_persons' => $mp, 'price_per_person' => $pp]);
            }
        }

        // An experiential stay's occupancy × meal-plan grid. Same rule again:
        // only replaced when the caller actually sent it.
        if ($request->has('room_rates')) {
            $experience->roomRates()->delete();
            $seen = [];
            foreach ((array) $request->input('room_rates', []) as $idx => $rate) {
                $occupancy = trim((string) ($rate['occupancy'] ?? ''));
                $mealPlan = trim((string) ($rate['meal_plan'] ?? ''));
                $price = $rate['price'] ?? '';
                if ($occupancy === '' || $mealPlan === '' || $price === '') continue;

                // The grid must have one answer per cell; a repeated pair would
                // make the price ambiguous, so the first one entered wins.
                $cell = $occupancy . '|' . $mealPlan;
                if (isset($seen[$cell])) continue;
                $seen[$cell] = true;

                $experience->roomRates()->create([
                    'occupancy' => $occupancy,
                    'meal_plan' => $mealPlan,
                    'price' => $price,
                    'sort_order' => $idx,
                ]);
            }
        }

        // Optional extras hung off this experience.
        if ($request->has('addons')) {
            $experience->addons()->delete();
            foreach ((array) $request->input('addons', []) as $idx => $addon) {
                $name = trim((string) ($addon['name'] ?? ''));
                if ($name === '') continue; // blank repeater rows are not add-ons
                $experience->addons()->create([
                    'name' => $name,
                    'description' => $addon['description'] ?? null,
                    'price' => ($addon['price'] ?? '') === '' ? null : $addon['price'],
                    'price_unit' => $addon['price_unit'] ?? null,
                    'is_active' => filter_var($addon['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'sort_order' => $idx,
                ]);
            }
        }

        return response()->json([
            "success" => true,
            "experience" => $experience->load(['priceSlabs', 'addons', 'roomRates']),
        ]);
    }

    protected function disableExperience(Request $request): JsonResponse
    {
        $experience = Experience::findOrFail($request->id);
        $experience->update(["is_active" => !$experience->is_active]);
        return response()->json(["success" => true, "is_active" => $experience->is_active]);
    }

    // ===================================================================
    // SP-AUTHORED EXPERIENCES
    //
    // An experience hangs off the host that runs it, so only a provider acting
    // as an HLH (homestay/lodge host) authors them — alongside HCT, who keeps
    // full control from the admin side. Ownership lives on
    // experiences.owner_provider_id, so a host can only ever see and touch
    // their own rows. A provider that is only an HRP or only an OSP is
    // excluded: a regional partner and a service supplier feed services into
    // an experience, they do not host one. Note a provider may be several
    // types at once, so this asks hasType() rather than reading provider_type.
    // ===================================================================

    /** Resolve the caller as an approved host, or return the error response. */
    protected function resolveExperienceAuthor(): array
    {
        [$sp, $err] = $this->resolveApprovedSp();
        if ($err) return [null, $err];

        if (!$sp->isHost()) {
            return [null, response()->json([
                'error' => 'Only homestay/lodge hosts can publish experiences.',
            ], 403)];
        }
        return [$sp, null];
    }

    /** The caller's own experience by id, or an error response. */
    protected function resolveOwnExperience(int $id, ServiceProvider $sp): array
    {
        $experience = Experience::find($id);
        if (!$experience || (int) $experience->owner_provider_id !== (int) $sp->id) {
            return [null, response()->json(['error' => 'Not your experience.'], 403)];
        }
        return [$experience, null];
    }

    /**
     * How many listings a provider may create for itself.
     *
     * A bad-faith signup could otherwise bury HCT's review queue, so
     * self-service creation is capped. The limit lives in settings rather than
     * in code so HCT can lift it for a genuinely large provider without a
     * deploy, and 0 (or a blank setting) means no cap at all.
     */
    protected function listingCap(string $key): int
    {
        $value = Setting::getValue($key, 10);
        return is_numeric($value) ? (int) $value : 10;
    }

    /**
     * Refuse a NEW listing once the cap is reached. Editing an existing row is
     * never blocked, and HCT is never capped — the cap exists to protect them.
     *
     * Rejected listings are deliberately not counted by the callers. An
     * experience cannot be deleted at all (it may sit inside a booked trip, so
     * it is only ever hidden), which would otherwise let ten refusals lock a
     * provider out for good. Leaving rejections uncounted still bounds what
     * reaches HCT, because a rejected row only returns to the queue by being
     * edited and resubmitted — at which point it is pending, and counts again.
     */
    protected function listingCapError(string $key, int $current, string $noun): ?JsonResponse
    {
        $cap = $this->listingCap($key);
        if ($cap <= 0 || $current < $cap) {
            return null;
        }
        return response()->json([
            'error' => "You have reached the limit of {$cap} {$noun}. "
                     . "Please contact HECO if you need to list more.",
        ], 422);
    }

    /**
     * The hosts and service providers a regional partner oversees.
     *
     * The client's brief: "HRPs should have a dashboard listing all HLHs and
     * OSPs within their region so they can oversee local development." It is
     * READ-ONLY on purpose — no document gives an HRP approval powers, and MVP
     * doc 8.2.1 keeps their MVP role to coordination rather than decisions.
     *
     * Scoped to the partner's own region, and to approved providers only: a
     * pending applicant has not been vetted by HCT yet and is not yet theirs to
     * oversee. Bank details and documents are deliberately not returned — an
     * HRP coordinates these providers, they do not administer them.
     */
    protected function getHrpRegionProviders(Request $request): JsonResponse
    {
        [$sp, $err] = $this->resolveApprovedSp();
        if ($err) return $err;

        if (!$sp->isRegionalPartner()) {
            return response()->json([
                'error' => 'Only regional partners oversee a region.',
            ], 403);
        }

        if (!$sp->region_id) {
            return response()->json(['success' => true, 'region' => null, 'providers' => []]);
        }

        $providers = ServiceProvider::where('region_id', $sp->region_id)
            ->where('status', 'approved')
            ->where('id', '!=', $sp->id)
            ->orderBy('name')
            ->get()
            ->filter(fn ($p) => $p->isHost() || $p->suppliesServices())
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'contact_person' => $p->contact_person,
                'email' => $p->email,
                'phone_1' => $p->phone_1,
                'types' => $p->types(),
                'type_labels' => $p->typeLabels(),
                'experience_categories' => $p->experience_categories ?: [],
                'service_categories' => $p->service_categories ?: [],
            ])
            ->values();

        return response()->json([
            'success' => true,
            'region' => $sp->region?->only(['id', 'name', 'country']),
            'providers' => $providers,
        ]);
    }

    protected function getSpExperiences(Request $request): JsonResponse
    {
        [$sp, $err] = $this->resolveExperienceAuthor();
        if ($err) return $err;

        $experiences = Experience::ownedBy($sp->id)
            ->with(['region', 'priceSlabs', 'days', 'roomRates', 'addons'])
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'experiences' => $experiences]);
    }

    /**
     * Operational periods as a clean list of {start, end} rows.
     *
     * Accepts the rows the form posts, and still accepts a JSON string so a
     * project saved before the date-row editor existed keeps its data.
     */
    protected function normalisePeriods(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return [];
        }

        $periods = [];
        foreach ($value as $row) {
            $start = is_array($row) ? trim((string) ($row['start'] ?? '')) : '';
            $end   = is_array($row) ? trim((string) ($row['end'] ?? '')) : '';
            if ($start === '' && $end === '') {
                continue;
            }
            $periods[] = ['start' => $start ?: null, 'end' => $end ?: null];
        }

        return $periods;
    }

    /**
     * A day's inclusions as a clean list of non-empty strings.
     *
     * The column is cast to array and every reader iterates it, so anything
     * else stored here breaks a whole screen rather than one row. Comma-
     * separated text is accepted because that is what a plain form field or an
     * import naturally sends.
     */
    protected function normaliseInclusions(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => is_scalar($item) ? trim((string) $item) : '',
            $value,
        ), fn ($item) => $item !== ''));
    }

    protected function saveSpExperience(Request $request): JsonResponse
    {
        [$sp, $err] = $this->resolveExperienceAuthor();
        if ($err) return $err;

        $existing = null;
        if ($request->filled('id')) {
            [$existing, $ownErr] = $this->resolveOwnExperience((int) $request->id, $sp);
            if ($ownErr) return $ownErr;
        }

        if (!$existing) {
            $capErr = $this->listingCapError(
                'max_experiences_per_provider',
                Experience::ownedBy($sp->id)->where('approval_status', '!=', 'rejected')->count(),
                'experiences',
            );
            if ($capErr) return $capErr;
        }

        // Ownership is never taken from the client. `hlh_id` stays a valid FK:
        // an HLH hosts its own experience, an OSP may name the host it runs at
        // and otherwise points the column at itself.
        $hlhId = $sp->provider_type === 'hlh'
            ? $sp->id
            : ($request->input('hlh_id') ?: ($existing->hlh_id ?? $sp->id));

        $request->merge([
            'owner_provider_id' => $sp->id,
            'owner_type'        => $sp->provider_type,
            'hlh_id'            => $hlhId,
        ]);

        // Fields only HCT controls — a provider must not be able to reorder the
        // catalogue, publish itself, or stamp its own approval.
        foreach ([
            'sort_order', 'approval_status', 'approved_at', 'approved_by', 'rejection_reason',
            'pending_changes', 'pending_submitted_at', 'pending_submitted_by',
        ] as $field) {
            $request->request->remove($field);
        }

        // Revising an experience that is already live: park the edit and leave
        // the approved version selling untouched until HCT reviews it.
        if ($existing && $existing->approval_status === 'approved') {
            // Uploads cannot live in a JSON column, so store them now and park
            // the resulting paths. The live row's photos are left alone until
            // the revision is approved.
            if ($err = $this->stashExperienceUploads($request, $existing)) {
                return $err;
            }

            $existing->update([
                'pending_changes'      => $this->experiencePayload($request),
                'pending_submitted_at' => now(),
                'pending_submitted_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'pending_changes' => true,
                'message' => 'Your changes are with HECO for review. The live version stays available until then.',
            ]);
        }

        // Nothing live to protect — a new experience, or one still pending, a
        // draft, or rejected.
        //
        // "Save as draft" keeps it out of HCT's queue entirely: the client's
        // reason is that "many users won't have all the information or photos
        // ready in one session", so a half-finished listing must be storable
        // without asking anyone to review it. A draft is never submitted, so it
        // carries no submitted_at/by — those record an actual submission.
        //
        // Note this branch is only reached when nothing is live. Editing an
        // APPROVED listing always parks as a revision above, which is right: a
        // member should not be able to pull a selling listing back into a draft.
        $asDraft = $request->boolean('save_as_draft');

        $request->merge([
            'approval_status' => $asDraft ? 'draft' : 'pending',
            'is_active'       => false,
        ]);

        if (!$asDraft) {
            $request->merge([
                'submitted_at' => now(),
                'submitted_by' => Auth::id(),
            ]);
        }

        return $this->saveExperience($request);
    }

    /**
     * Move any uploaded photos into storage and replace them on the request
     * with their stored paths, so a parked revision carries paths rather than
     * files. Returns an error response if an upload was rejected.
     */
    protected function stashExperienceUploads(Request $request, Experience $existing): ?JsonResponse
    {
        if ($request->hasFile('card_image')) {
            $stored = \App\Services\ImageUploadService::storeUploadedImage(
                $request->file('card_image'), 'experiences', 1200
            );
            if (!$stored) {
                return response()->json(['error' => 'Failed to upload the card image. Use JPG, PNG, or WebP.'], 422);
            }
            $request->request->set('card_image', $stored);
            // Drop the upload itself: Request::all() merges the file bag over
            // the input bag, so leaving it here would put an UploadedFile —
            // not the path — into the JSON payload.
            $request->files->remove('card_image');
        }

        // New gallery photos are added to whatever the live row already has.
        $gallery = $existing->gallery ?? [];
        if (is_string($gallery)) {
            $gallery = json_decode($gallery, true) ?: [];
        }
        foreach ((array) $request->file('gallery', []) as $file) {
            if (!$file) continue;
            $stored = \App\Services\ImageUploadService::storeUploadedImage($file, 'experiences', 1200);
            if ($stored) $gallery[] = $stored;
        }
        $request->files->remove('gallery');
        if (!empty($gallery)) {
            $request->request->set('gallery', array_values($gallery));
        }

        return null;
    }

    /**
     * The submitted experience fields, as stored in `pending_changes` and
     * replayed through saveExperience() on approval. Ownership and review
     * columns are excluded — those are decided at replay time, not by whatever
     * was posted.
     */
    protected function experiencePayload(Request $request): array
    {
        // Read the POST bag directly rather than $request->except(): all()
        // merges in uploaded files, and Request memoises that conversion, so a
        // file would survive here even after being removed — and an
        // UploadedFile cannot be stored in a JSON column.
        $payload = $request->request->all();

        foreach ([
            '_token', 'save_sp_experience', 'id',
            'owner_provider_id', 'owner_type',
            'approval_status', 'is_active', 'sort_order',
            'submitted_at', 'submitted_by',
            'approved_at', 'approved_by', 'rejection_reason',
            'pending_changes', 'pending_submitted_at', 'pending_submitted_by',
        ] as $field) {
            unset($payload[$field]);
        }

        return $payload;
    }

    protected function toggleSpExperience(Request $request): JsonResponse
    {
        [$sp, $err] = $this->resolveExperienceAuthor();
        if ($err) return $err;
        [$experience, $ownErr] = $this->resolveOwnExperience((int) $request->id, $sp);
        if ($ownErr) return $ownErr;

        // Visibility is the provider's to control only once HCT has approved —
        // otherwise this would be a way to publish unreviewed content.
        if ($experience->approval_status !== 'approved') {
            return response()->json([
                'error' => 'This experience is still under review by HECO.',
            ], 422);
        }

        $experience->update(['is_active' => !$experience->is_active]);
        return response()->json(['success' => true, 'is_active' => $experience->is_active]);
    }

    // ── HCT review of provider-authored experiences ──────────────────────

    protected function getPendingExperiences(Request $request): JsonResponse
    {
        $rows = Experience::pending()
            ->with([
                'ownerProvider:id,name,provider_type',
                'region:id,name',
                'submitter:id,full_name,email',
            ])
            ->orderBy('submitted_at', 'desc')
            ->paginate(config('pagination.admin_per_page', 20));

        return response()->json([
            'rows' => $rows->items(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
                'per_page' => $rows->perPage(),
            ],
        ]);
    }

    protected function approveExperience(Request $request): JsonResponse
    {
        $experience = Experience::pending()->findOrFail($request->id);

        // A revision of a live experience: replay the parked payload through
        // the normal save path so slug, headline price, days and price slabs
        // are all derived exactly as they would be on any other save.
        if ($experience->hasPendingChanges()) {
            $parked = $experience->pending_changes;

            $replay = Request::create('/ajax', 'POST', array_merge(
                $parked,
                ['id' => $experience->id],
            ));
            $replay->setUserResolver($request->getUserResolver());

            $result = $this->saveExperience($replay);
            if ($result->getStatusCode() !== 200) {
                return $result;
            }

            // saveExperience only takes photos as uploaded files; a parked
            // revision carries already-stored paths, so apply those by hand.
            $media = [];
            if (!empty($parked['card_image'])) {
                $media['card_image'] = $parked['card_image'];
            }
            if (!empty($parked['gallery'])) {
                $media['gallery'] = (array) $parked['gallery'];
            }

            $experience->refresh()->update($media + [
                'approval_status'      => 'approved',
                'is_active'            => true,
                'approved_at'          => now(),
                'approved_by'          => Auth::id(),
                'rejection_reason'     => null,
                'pending_changes'      => null,
                'pending_submitted_at' => null,
                'pending_submitted_by' => null,
            ]);

            return response()->json(['success' => true]);
        }

        $experience->update([
            'approval_status'  => 'approved',
            'is_active'        => true,
            'approved_at'      => now(),
            'approved_by'      => Auth::id(),
            'rejection_reason' => null,
        ]);

        return response()->json(['success' => true]);
    }

    protected function rejectExperience(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer',
            'reason' => 'nullable|string|max:500',
        ]);

        $experience = Experience::pending()->findOrFail($request->id);

        // Discarding a revision leaves the approved version exactly as it was —
        // it never stopped selling, so there is nothing to restore.
        if ($experience->hasPendingChanges()) {
            $experience->update([
                'pending_changes'      => null,
                'pending_submitted_at' => null,
                'pending_submitted_by' => null,
                'rejection_reason'     => $request->input('reason') ?: null,
            ]);

            return response()->json(['success' => true, 'kept_live' => true]);
        }

        $experience->update([
            'approval_status'  => 'rejected',
            'is_active'        => false,
            'approved_at'      => now(),
            'approved_by'      => Auth::id(),
            'rejection_reason' => $request->input('reason') ?: null,
        ]);

        return response()->json(['success' => true]);
    }

    protected function deleteSpExperience(Request $request): JsonResponse
    {
        [$sp, $err] = $this->resolveExperienceAuthor();
        if ($err) return $err;
        [$experience, $ownErr] = $this->resolveOwnExperience((int) $request->id, $sp);
        if ($ownErr) return $ownErr;

        // Soft-remove: an experience may already sit inside booked itineraries,
        // so it is deactivated rather than deleted out from under a trip.
        $experience->update(['is_active' => false]);
        return response()->json(['success' => true]);
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
        $projects = $query->orderBy("name")->paginate(config('pagination.admin_per_page', 20));
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

        if (isset($data["fallback_for_regions"]) && is_string($data["fallback_for_regions"])) {
            $data["fallback_for_regions"] = json_decode($data["fallback_for_regions"], true);
        }

        // The form posts these as rows of two dates. A repeater always leaves
        // an empty row behind, and a row with neither date says nothing.
        foreach (["active_periods", "paused_periods"] as $periodField) {
            if (!array_key_exists($periodField, $data)) {
                continue;
            }
            $data[$periodField] = $this->normalisePeriods($data[$periodField]);
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

        $this->logActivity('trip_updated', 'Trip', $trip->id, ['fields' => array_keys($data)]);
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

        $payment = TravellerPayment::create([
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

        $this->logActivity('traveller_payment_added', 'TravellerPayment', $payment->id, [
            'trip_id' => $trip->id, 'amount' => (float) $request->amount, 'mode' => $request->mode,
        ]);
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

            // Payment amount is server-authoritative: always charge the full
            // outstanding balance, computed here from the canonical pricing —
            // never a client-supplied figure. This stops a tampered request
            // (e.g. amount=1 POSTed directly to /ajax) from "confirming" a trip
            // on a token payment. Any `amount` in the request is ignored.
            $pricing = app(CostCalculatorService::class)->calculate($trip);
            $totalPaid = (float) $trip->travellerPayments()
                ->where('payment_status', 'paid')
                ->sum('amount');
            $balanceDue = round(max(0, ((float) ($pricing['final_price'] ?? 0)) - $totalPaid), 2);

            if ($balanceDue <= 0) {
                return response()->json(["error" => "This trip is already paid in full."], 422);
            }

            $amountInRupees = $balanceDue;

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

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully!',
                // Return the payment's own (numeric) trip id so the client redirects to
                // the correct /trip/{id}/thank-you without relying on a stale window.tripId.
                'trip_id' => $payment->trip_id,
            ]);
        }

        $payment->update(['payment_status' => 'failed']);
        return response()->json(['error' => 'Payment verification failed.'], 400);
    }

    protected function getTravellerPaymentHistory(Request $request): JsonResponse
    {
        $trip = Trip::find($request->trip_id);

        // Ownership guard (IDOR): a traveller may only see their own trip's
        // payments; HCT staff may see any. Auth is already ensured by the gate.
        $user = auth()->user();
        if (!$trip || (!$user->isHct() && (int) $trip->user_id !== (int) $user->id)) {
            return response()->json(["error" => "Not found"], 404);
        }

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
        $this->logActivity('traveller_payment_edited', 'TravellerPayment', $payment->id, [
            'trip_id' => $payment->trip_id, 'amount' => (float) $payment->amount,
        ]);
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
        $query = Experience::where("is_active", true)->with(["region", "hlh"])
            ->withRoomRateFrom();

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

        // Component placeholder rows (accommodation / transport / guide / other) are
        // created at cost=0 — the bundled cost is captured ONCE by the Experience
        // breakdown in CostCalculatorService (single source of truth). Storing the
        // component cost here as well would double-count it (calculator adds cost>0
        // day-services on top of the exp components). A pinned provider later sets
        // the real rate on the row, replacing the bundled estimate for that line.
        $componentMap = [
            "cost_accommodation" => ["accommodation", $experience->accommodation_category ?? "Accommodation"],
            "cost_logistics"     => ["transport",     "Logistics / Transport"],
            "cost_guide"         => ["guide",         "Guide service"],
            "cost_other"         => ["other",         "Other"],
        ];
        foreach ($componentMap as $field => [$serviceType, $description]) {
            // Only create a placeholder for components the experience actually
            // bundles, so the itinerary breakdown/pin targets stay meaningful.
            if ((float) $experience->{$field} <= 0) continue;
            TripDayService::create([
                "trip_day_id" => $day->id,
                "service_type" => $serviceType,
                "description" => $description,
                "cost" => 0,
                "is_included" => true,
            ]);
        }

        // Persist a fresh total so the trip's price reflects the new experience
        // immediately (mutation endpoints otherwise leave a stale final_price).
        app(CostCalculatorService::class)->calculate($day->trip);

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

        // Validate the target is a real, approved provider before assigning it —
        // otherwise a day service could be pinned to an unapproved/non-existent
        // provider with untracked cost (NEW-B).
        if ($newSpId) {
            $sp = ServiceProvider::find($newSpId);
            if (!$sp || $sp->status !== 'approved') {
                return response()->json(['error' => 'Selected provider is not available.'], 422);
            }
        }

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
        // Reflect the provider's cost in the trip total immediately (NEW-C) —
        // otherwise the day-level assign leaves a stale final_price.
        if ($trip) {
            app(CostCalculatorService::class)->calculate($trip);
        }
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
        // The portal's /join wizard and the provider app both file the same
        // application, so both go through AuthService. Nothing here is
        // portal-specific except the session login below.
        $result = app(AuthService::class)->submitProviderApplication(
            $request->all(),
            array_values((array) $request->file('documents', [])),
        );

        // Only the web has a session — the mobile API is stateless and signs
        // in afterwards with the password the applicant just chose.
        if ($result['user'] && $request->hasSession()) {
            Auth::login($result['user']);
            $request->session()->regenerate();
        }

        return response()->json($result['body'], $result['status']);
    }



    /**
     * Normalise a multi-select application field to a clean list (or null).
     * The wizard posts these as `field[]`; a single value arrives as a scalar.
     */
    protected function applicationArray(Request $request, string $key): ?array
    {
        $val = $request->input($key);
        if ($val === null) return null;
        $val = array_values(array_filter((array) $val, fn($v) => $v !== null && $v !== ''));
        return $val ?: null;
    }




    /**
     * Send a mail without letting failures break the calling action.
     * Errors are logged with the supplied tag for traceability.
     */
    protected function sendMail(string $to, $mailable, string $tag = ''): void
    {
        // Sent after the response, not before it.
        //
        // An SMTP round trip is seconds of wall clock, and on Windows PHP
        // charges that to max_execution_time. A signup that also had documents
        // to receive, store and resize was spending its whole 30s budget and
        // dying mid-request — leaving the uploaded files on disk with no
        // application to belong to.
        //
        // defer() and not queue(): the mail still goes out from this same
        // process, so nothing depends on a worker being up, but the caller has
        // already had its answer by the time we start talking to the mail
        // server. Laravel skips deferred work when the response failed, which
        // is what we want — no "application received" for one that wasn't.
        defer(function () use ($to, $mailable, $tag) {
            try {
                Mail::to($to)->send($mailable);
            } catch (\Throwable $e) {
                Log::error('Mail send failed [' . $tag . ']: ' . $e->getMessage(), [
                    'to' => $to,
                    'mailable' => get_class($mailable),
                ]);
            }
        });
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
        if (!is_array($settings)) {
            return response()->json(["error" => "Invalid settings payload"], 422);
        }
        // Whitelist: only keys that already exist as Setting rows may be updated,
        // so arbitrary/injection keys are rejected. Each key keeps its own group.
        // No hardcoded list — the DB is the single source of truth; a genuinely
        // new setting must be seeded first before it becomes editable here.
        $existing = Setting::whereIn("key", array_keys($settings))->get()->keyBy("key");
        $rejected = array_values(array_diff(array_keys($settings), $existing->keys()->all()));
        if (!empty($rejected)) {
            Log::warning("saveSettings rejected unknown setting keys", [
                "keys" => $rejected,
                "user_id" => auth()->id(),
            ]);
        }
        $applied = [];
        foreach ($settings as $key => $value) {
            if ($existing->has($key)) {
                Setting::setValue($key, $value, $existing[$key]->group);
                $applied[$key] = $value;
            }
        }
        $this->logActivity('settings_updated', 'Setting', null, ['keys' => array_keys($applied)]);
        return response()->json(["success" => true, "rejected" => $rejected]);
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
