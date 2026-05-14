<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SystemList;
use App\Models\Trip;
use App\Services\CostCalculatorService;

class HomepageController extends Controller
{
    public function landing()
    {
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->isHct()) return redirect('//' . config('app.admin_domain') . '/dashboard');
            if ($user->isServiceProvider()) return redirect("/sp/dashboard");
        }
        $regions = Region::where("is_active", true)->orderBy("sort_order")->get();
        // Category cards must link with the real experience-type strings so the
        // /home filter actually matches them (audit H2).
        $experienceTypes = Experience::where("is_active", true)
            ->whereNotNull("type")
            ->where("type", "!=", "")
            ->distinct()
            ->orderBy("type")
            ->pluck("type")
            ->values();
        // Real headline counts for the landing-page stats (no more hard-coded "20+").
        $experienceCount = Experience::where("is_active", true)->count();
        $regionCount = $regions->count();
        $communityCount = ServiceProvider::where("status", "approved")
            ->whereIn("provider_type", ["hlh", "hrp"])
            ->count();
        return view("portal.landing", compact(
            "regions", "experienceTypes", "experienceCount", "regionCount", "communityCount"
        ));
    }

    public function home(Request $request)
    {
        $regions = Region::where("is_active", true)->orderBy("sort_order")->get();
        $experiences = Experience::where("is_active", true)
            ->with(["region", "hlh"])
            ->orderBy("sort_order")
            ->paginate(12);

        $trip = null;
        $guestTripData = null;
        if (auth()->check()) {
            if ($request->has("trip_id")) {
                $trip = Trip::where("id", $request->trip_id)
                    ->where("user_id", auth()->id())
                    ->with(["selectedExperiences.experience", "tripDays.experiences.experience", "tripDays.services", "tripRegions.region"])
                    ->first();
            } else {
                $trip = Trip::where("user_id", auth()->id())
                    ->whereIn("status", ["not_confirmed"])
                    ->with(["selectedExperiences.experience", "tripDays.experiences.experience", "tripDays.services", "tripRegions.region"])
                    ->latest()
                    ->first();
            }
        } else {
            // Load guest trip from session (no DB)
            $guestTripData = session('guest_trip');
        }

        // Right-sidebar preference dropdowns — DB-driven (system_lists).
        $prefLists = SystemList::whereIn('list_type', [
                'accommodation_comfort',
                'vehicle_comfort',
                'guide_preference',
                'travel_pace',
                'budget_sensitivity',
            ])
            ->where('is_active', 1)
            ->orderBy('list_type')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('list_type');

        // Filter accommodation_comfort + vehicle_comfort to only show options
        // that approved SPs in this trip's region(s) actually provide.
        $regionIds = $this->resolveTripRegionIds($trip, $guestTripData);
        if (!empty($regionIds)) {
            $available = $this->resolveAvailableCapabilities($regionIds);

            // The traveller's currently stored value is preserved even if no
            // SP offers it any more, so they don't lose their selection.
            $currentAccom   = $trip?->accommodation_comfort ?? ($guestTripData['accommodation_comfort'] ?? null);
            $currentVehicle = $trip?->vehicle_comfort       ?? ($guestTripData['vehicle_comfort']       ?? null);
            $currentGuide   = $trip?->guide_preference      ?? ($guestTripData['guide_preference']      ?? null);

            if (!empty($available['accommodation']) && isset($prefLists['accommodation_comfort'])) {
                $prefLists['accommodation_comfort'] = $prefLists['accommodation_comfort']
                    ->filter(fn($item) => in_array($item->name, $available['accommodation'], true) || $item->name === $currentAccom)
                    ->values();
            }
            if (!empty($available['vehicle']) && isset($prefLists['vehicle_comfort'])) {
                $prefLists['vehicle_comfort'] = $prefLists['vehicle_comfort']
                    ->filter(fn($item) => in_array($item->name, $available['vehicle'], true) || $item->name === $currentVehicle)
                    ->values();
            }
            if (!empty($available['guide']) && isset($prefLists['guide_preference'])) {
                // "No Guide" is always offered (it's an opt-out, not an SP capability).
                $prefLists['guide_preference'] = $prefLists['guide_preference']
                    ->filter(fn($item) =>
                        $item->name === 'No Guide'
                        || in_array($item->name, $available['guide'], true)
                        || $item->name === $currentGuide
                    )
                    ->values();
            }
        }

        $multiplierMap = CostCalculatorService::getMultiplierMap();

        // Filter dropdown options must mirror the actual DB strings so every option
        // returns results (see audit H2). 'extreme' is forced in even if the seed
        // data happens not to contain it yet.
        $experienceTypes = Experience::where("is_active", true)
            ->whereNotNull("type")
            ->where("type", "!=", "")
            ->distinct()
            ->orderBy("type")
            ->pluck("type")
            ->values();
        $difficultyLevels = Experience::where("is_active", true)
            ->whereNotNull("difficulty_level")
            ->where("difficulty_level", "!=", "")
            ->distinct()
            ->pluck("difficulty_level")
            ->push("extreme")
            ->unique()
            ->values();
        // Sort difficulty by intuitive order, unknown values last.
        $difficultyOrder = ['easy', 'moderate', 'challenging', 'difficult', 'extreme', 'expert'];
        $difficultyLevels = $difficultyLevels
            ->sortBy(fn($d) => array_search(strtolower($d), $difficultyOrder) === false ? 99 : array_search(strtolower($d), $difficultyOrder))
            ->values();

        return view("portal.homepage", compact("regions", "experiences", "trip", "guestTripData", "prefLists", "multiplierMap", "experienceTypes", "difficultyLevels"));
    }

    /**
     * Resolve the region IDs in scope for the current trip — from tripRegions
     * if present, falling back to the regions of the selected experiences.
     * Guests use the session's experience_ids.
     */
    protected function resolveTripRegionIds(?Trip $trip, ?array $guestTripData): array
    {
        if ($trip) {
            $ids = $trip->tripRegions()->pluck('region_id')->filter()->unique()->values()->all();
            if (!empty($ids)) return $ids;
            $expIds = $trip->selectedExperiences()->pluck('experience_id')->filter()->unique()->all();
            if (!empty($expIds)) {
                return Experience::whereIn('id', $expIds)->pluck('region_id')->filter()->unique()->values()->all();
            }
            return [];
        }
        $expIds = $guestTripData['experience_ids'] ?? [];
        if (empty($expIds)) return [];
        return Experience::whereIn('id', $expIds)->pluck('region_id')->filter()->unique()->values()->all();
    }

    /**
     * Union of SP-offered comfort tiers, vehicle types, and guide types
     * across approved providers in the given regions. Accommodation tiers
     * are derived from sp_pricing.comfort_tier (per-row source of truth)
     * rather than service_providers.accommodation_categories (legacy).
     */
    protected function resolveAvailableCapabilities(array $regionIds): array
    {
        $approvedSpIds = ServiceProvider::where('status', 'approved')
            ->whereIn('region_id', $regionIds)
            ->pluck('id');

        $accom = \App\Models\SpPricing::whereIn('service_provider_id', $approvedSpIds)
            ->where('service_type', 'accommodation')
            ->where('is_active', true)
            ->whereNotNull('comfort_tier')
            ->where('comfort_tier', '!=', '')
            ->pluck('comfort_tier')
            ->unique()
            ->values()
            ->all();

        $sps = ServiceProvider::whereIn('id', $approvedSpIds)
            ->get(['vehicle_types', 'guide_types']);

        $vehicle = [];
        $guide = [];
        foreach ($sps as $sp) {
            if (is_array($sp->vehicle_types)) {
                $vehicle = array_merge($vehicle, $sp->vehicle_types);
            }
            if (is_array($sp->guide_types)) {
                $guide = array_merge($guide, $sp->guide_types);
            }
        }
        return [
            'accommodation' => $accom,
            'vehicle'       => array_values(array_unique($vehicle)),
            'guide'         => array_values(array_unique($guide)),
        ];
    }

    public function experienceDetail(string $slug)
    {
        $experience = Experience::where("slug", $slug)
            ->where("is_active", true)
            ->with(["region", "hlh", "regenerativeProject"])
            ->withCount('reviews')
            ->firstOrFail();

        $avgRating = $experience->reviews()->avg('rating');

        // If the hosting HLH has at least one active accommodation row, the
        // experience-detail page renders a "Stay options" widget that lets the
        // traveller pick dates and see live per-room-category availability.
        $hostHasRooms = false;
        if ($experience->hlh) {
            $hostHasRooms = \App\Models\SpPricing::where('service_provider_id', $experience->hlh->id)
                ->where('service_type', 'accommodation')
                ->where('is_active', true)
                ->whereNotNull('total_rooms')
                ->exists();
        }

        return view("portal.experience-detail", compact("experience", "avgRating", "hostHasRooms"));
    }
}
