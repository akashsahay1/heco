<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Region;
use App\Models\RegenerativeProject;
use App\Models\ServiceProvider;
use App\Services\CostCalculatorService;

class TripManagerController extends Controller
{
    public function show($tripId)
    {
        // Accept either the numeric PK or the string trip code (HECO-T-0103).
        // Several admin views build this link from $trip->trip_id (the code)
        // rather than the numeric id, which would 500 a strict integer route.
        $trip = Trip::with([
            "user", "tripRegions.region", "tripRegions.hrp",
            "tripDays.experiences.experience.region",
            "tripDays.experiences.experience.hlh",
            "tripDays.services.serviceProvider",
            "selectedExperiences.experience",
            "lead", "travellerPayments", "spPayments.serviceProvider",
        ])->where(function ($q) use ($tripId) {
            if (is_numeric($tripId)) {
                $q->where("id", (int) $tripId);
            }
            $q->orWhere("trip_id", $tripId);
        })->firstOrFail();

        $regions = Region::where("is_active", true)->get();
        $providers = ServiceProvider::where("status", "approved")->with("region")->get();
        $involved = $this->whoIsOnThisTrip($trip);

        // A custom-day trip names its regenerative project here. Any project
        // may go on any trip, whatever its region: every active one, and the
        // one already chosen even if it has since been switched off.
        $rpProjects = collect();
        if ($trip->isHctBuilt()) {
            $rpProjects = RegenerativeProject::where('is_active', true)
                ->when($trip->regenerative_project_id, fn ($q) => $q->orWhere('id', $trip->regenerative_project_id))
                ->orderBy('name')->get();
        }

        // Part of the total, but never stored on the trip, so the panel has to
        // ask for it. Without this its Financial Snapshot silently came up
        // short on every trip where somebody had a room to themselves.
        $calculator = app(CostCalculatorService::class);
        $singleSupplement = $calculator->singleSupplementFor($trip);

        // How many heads a per-person service line is multiplied by. Children
        // and infants count at the percentages set in Settings, the same as
        // they do for an experience, so the screen shows the same figure the
        // calculator charges.
        $peopleFactor = $calculator->partyBreakdown(
            (int) $trip->adults, (int) ($trip->children ?: 0), (int) ($trip->infants ?: 0),
        )['people_factor'];

        return view("admin.trip-manager.layout",
            compact("trip", "regions", "providers", "involved", "singleSupplement", "peopleFactor", "rpProjects"));
    }

    /**
     * The regions a trip visits and the partners it leans on.
     *
     * Asked for by the MVP specification, 13.3.2 - "Regions involved / HRPs
     * involved / HLHs and OSPs involved" - and never built. HCT could read a
     * trip's costs and its day-by-day plan, but not the one thing they need
     * when something goes wrong on the ground: who to ring.
     *
     * A partner reaches a trip four ways, and all four count: they host one of
     * its experiences, they are pinned for the whole trip, they are pinned on a
     * single day, or they coordinate the region it visits.
     */
    private function whoIsOnThisTrip(Trip $trip): array
    {
        $regionIds = $trip->tripRegions->pluck("region_id")->filter()->unique();

        // An HRP is found by their region. `trip_regions.hrp_id` exists but has
        // never been written, so matching on it always came back empty (#37).
        $hrps = ServiceProvider::where("status", "approved")
            ->whereIn("region_id", $regionIds)
            ->with("region")
            ->get()
            ->filter(fn (ServiceProvider $sp) => $sp->hasType("hrp"))
            ->values();

        $providerIds = collect();

        // The hosts of the experiences on the trip.
        foreach ($trip->tripDays as $day) {
            foreach ($day->experiences as $dayExp) {
                $providerIds->push($dayExp->experience?->owner_provider_id);
                $providerIds->push($dayExp->experience?->hlh_id);
            }
            // Anyone pinned to one of its days.
            foreach ($day->services as $service) {
                $providerIds->push($service->service_provider_id);
            }
        }

        // And anyone pinned for the whole trip.
        $providerIds->push($trip->accommodation_provider_id);
        $providerIds->push($trip->vehicle_provider_id);
        $providerIds->push($trip->guide_provider_id);

        $others = ServiceProvider::whereIn("id", $providerIds->filter()->unique())
            ->whereNotIn("id", $hrps->pluck("id"))
            ->with("region")
            ->get()
            ->sortBy("name")
            ->values();

        return [
            "regions" => $trip->tripRegions->pluck("region")->filter()->unique("id")->values(),
            "hrps"    => $hrps,
            "others"  => $others,
        ];
    }
}
