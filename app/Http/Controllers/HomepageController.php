<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SystemList;
use App\Models\Trip;

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
        return view("portal.landing", compact("regions"));
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
                    ->whereIn("status", ["draft", "not_confirmed"])
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

        return view("portal.homepage", compact("regions", "experiences", "trip", "guestTripData", "prefLists"));
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
     * Union of SP-offered accommodation categories and vehicle types
     * across approved providers in the given regions.
     */
    protected function resolveAvailableCapabilities(array $regionIds): array
    {
        $sps = ServiceProvider::where('status', 'approved')
            ->whereIn('region_id', $regionIds)
            ->get(['accommodation_categories', 'vehicle_types', 'guide_types']);

        $accom = [];
        $vehicle = [];
        $guide = [];
        foreach ($sps as $sp) {
            if (is_array($sp->accommodation_categories)) {
                $accom = array_merge($accom, $sp->accommodation_categories);
            }
            if (is_array($sp->vehicle_types)) {
                $vehicle = array_merge($vehicle, $sp->vehicle_types);
            }
            if (is_array($sp->guide_types)) {
                $guide = array_merge($guide, $sp->guide_types);
            }
        }
        return [
            'accommodation' => array_values(array_unique($accom)),
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

        return view("portal.experience-detail", compact("experience", "avgRating"));
    }
}
