<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trip;

class TravellerController extends Controller
{
    public function myItineraries()
    {
        $trips = Trip::where("user_id", auth()->id())
            ->with(["tripRegions.region", "selectedExperiences"])
            ->orderBy("updated_at", "desc")
            ->get();
        return view("portal.my-itineraries", compact("trips"));
    }

    public function resumeTrip(int $tripId)
    {
        $trip = Trip::where("id", $tripId)
            ->where("user_id", auth()->id())
            ->firstOrFail();
        return redirect("/home?trip_id=" . $trip->id);
    }

    public function profile()
    {
        $user = auth()->user();
        return view("portal.profile", compact("user"));
    }
}
