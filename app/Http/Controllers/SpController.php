<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SystemList;

class SpController extends Controller
{
    public function application()
    {
        $regions = Region::where("is_active", true)->orderBy("name")->get();
        $serviceTypes = SystemList::ofType("service_type")->get();
        return view("portal.sp.application", compact("regions", "serviceTypes"));
    }

    public function dashboard()
    {
        $user = auth()->user();
        $provider = ServiceProvider::where("user_id", $user->id)->with(["region", "pricing", "lastUpdatedBy"])->first();
        if (!$provider) {
            return redirect()->route("sp.application")->with("status", "Complete your service provider application to access the dashboard.");
        }
        return view("portal.sp.dashboard", compact("provider"));
    }

    public function editProfile()
    {
        $user = auth()->user();
        $provider = ServiceProvider::where("user_id", $user->id)->with(["region", "lastUpdatedBy"])->firstOrFail();
        $regions = Region::where("is_active", true)->orderBy("name")->get();
        $serviceTypes            = SystemList::ofType("service_type")->get();
        $accommodationCategories = SystemList::ofType("accommodation_category")->get();
        $vehicleTypes            = SystemList::ofType("vehicle_type")->get();
        $guideTypes              = SystemList::ofType("guide_preference")->get();
        $activityTypes           = SystemList::ofType("activity_type")->get();
        return view("portal.sp.edit-profile", compact(
            "provider", "regions",
            "serviceTypes", "accommodationCategories", "vehicleTypes", "guideTypes", "activityTypes"
        ));
    }

    /**
     * SP self-service "Services, Rooms & Pricing" page — mirrors the admin
     * /providers/{id}/edit pricing card so SPs can manage their own room
     * inventory + rates without going through HCT.
     */
    public function pricing()
    {
        $user = auth()->user();
        $provider = ServiceProvider::where("user_id", $user->id)->firstOrFail();
        $serviceTypes            = SystemList::ofType("service_type")->get();
        $accommodationCategories = SystemList::ofType("accommodation_category")->get();
        $vehicleTypes            = SystemList::ofType("vehicle_type")->get();
        $guideTypes              = SystemList::ofType("guide_preference")->get();
        $activityTypes           = SystemList::ofType("activity_type")->get();
        $occupancyUnits          = SystemList::ofType("occupancy_unit")->get();
        $mealPlans               = SystemList::ofType("meal_plan")->get();
        $roomCategories          = SystemList::ofType("room_category")->get();
        return view("portal.sp.pricing", compact(
            "provider",
            "serviceTypes", "accommodationCategories", "vehicleTypes", "guideTypes", "activityTypes",
            "occupancyUnits", "mealPlans", "roomCategories"
        ));
    }
}
