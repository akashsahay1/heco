<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Region;
use App\Models\ServiceProvider;

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

        return view("admin.trip-manager.layout", compact("trip", "regions", "providers"));
    }
}
