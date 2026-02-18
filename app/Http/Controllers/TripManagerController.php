<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Region;
use App\Models\ServiceProvider;

class TripManagerController extends Controller
{
    public function show(int $tripId)
    {
        $trip = Trip::with([
            "user", "tripRegions.region", "tripRegions.hrp",
            "tripDays.experiences.experience.region",
            "tripDays.experiences.experience.hlh",
            "tripDays.services.serviceProvider",
            "selectedExperiences.experience",
            "lead", "travellerPayments", "spPayments.serviceProvider",
        ])->findOrFail($tripId);

        $regions = Region::where("is_active", true)->get();
        $providers = ServiceProvider::where("status", "approved")->with("region")->get();

        return view("admin.trip-manager.layout", compact("trip", "regions", "providers"));
    }
}
