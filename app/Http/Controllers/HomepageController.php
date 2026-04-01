<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Experience;
use App\Models\Region;
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

        return view("portal.homepage", compact("regions", "experiences", "trip", "guestTripData"));
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
