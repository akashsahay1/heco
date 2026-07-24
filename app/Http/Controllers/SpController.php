<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Currency;
use App\Models\Region;
use App\Models\RegenerativeProject;
use App\Models\ServiceProvider;
use App\Models\SystemList;

class SpController extends Controller
{
    public function application()
    {
        $regions                 = Region::where("is_active", true)->orderBy("name")->get();
        $serviceTypes            = SystemList::ofType("service_type")->get();
        $accommodationCategories = SystemList::ofType("accommodation_category")->get();
        $vehicleTypes            = SystemList::ofType("vehicle_type")->get();
        $guideTypes              = SystemList::ofType("guide_preference")->get();
        $activityTypes           = SystemList::ofType("activity_type")->get();
        $businessTypes           = SystemList::ofType("business_type")->get();
        $documentTypes           = SystemList::ofType("document_type")->get();
        // Countries come from the regions HECO operates in, not a hardcoded list.
        $countries = Region::where("is_active", true)->whereNotNull("country")
            ->distinct()->orderBy("country")->pluck("country");

        return view("portal.sp.application", compact(
            "regions", "serviceTypes", "accommodationCategories",
            "vehicleTypes", "guideTypes", "activityTypes",
            "businessTypes", "documentTypes", "countries"
        ));
    }


    /**
     * "Application under review" page. Mirrors the mobile app's waiting-approval
     * screen: a signed-in provider whose application is still pending or was
     * rejected sees their status and the lifecycle timeline. Approved providers
     * are forwarded to the working dashboard; anyone without an application on
     * file is sent to the form.
     */
    public function applicationStatus()
    {
        $user = auth()->user();
        $provider = ServiceProvider::where("user_id", $user->id)->with("region")->first();

        if (!$provider) {
            return redirect()->route("sp.application")
                ->with("status", "Complete your service provider application to continue.");
        }

        // Approved providers see the "You're approved!" celebration with a link
        // into the dashboard (mirrors the app) rather than being bounced straight
        // in — the login redirect already takes them to the dashboard directly.
        return view("portal.sp.application-status", compact("provider"));
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
     * SP self-service experiences page.
     *
     * Only HLH (hosts) and OSP (operators) author experiences — an HRP is a
     * regional partner, not a host, so the page is refused for them. Everything
     * submitted here goes to HCT for review before travellers see it.
     */
    public function experiences()
    {
        $user = auth()->user();
        $provider = ServiceProvider::where("user_id", $user->id)->firstOrFail();

        if (!in_array($provider->provider_type, ["hlh", "osp"], true)) {
            return redirect()->route("sp.dashboard")->with(
                "status",
                "Experiences are managed by homestay/lodge hosts and other service providers."
            );
        }

        $regions                 = Region::where("is_active", true)->orderBy("name")->get();
        $regenerativeProjects    = RegenerativeProject::where("is_active", true)->orderBy("name")->get();
        $accommodationCategories = SystemList::ofType("accommodation_category")->get();
        $serviceTypes            = SystemList::ofType("service_type")->get();
        $currencies              = Currency::where("is_active", true)->orderBy("sort_order")->get();
        // Option sets the form used to hardcode — kept in system_lists so the
        // portal and the provider app never drift apart.
        $bestSeasons             = SystemList::ofType("best_season")->pluck("name");
        $dayInclusions           = SystemList::ofType("day_inclusion")->pluck("name");

        return view("portal.sp.experiences", compact(
            "provider", "regions", "regenerativeProjects",
            "accommodationCategories", "serviceTypes", "currencies",
            "bestSeasons", "dayInclusions"
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
