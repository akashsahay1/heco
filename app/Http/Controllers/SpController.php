<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Currency;
use App\Models\Region;
use App\Models\RegenerativeProject;
use App\Models\ServiceProvider;
use App\Models\Setting;
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
        // Competence options — only a regional partner is shown these.
        $educationLevels    = SystemList::ofType("education_level")->get();
        $englishLevels      = SystemList::ofType("english_level")->get();
        $computerSkillLevels = SystemList::ofType("computer_skill_level")->get();
        return view("portal.sp.edit-profile", compact(
            "provider", "regions",
            "serviceTypes", "accommodationCategories", "vehicleTypes", "guideTypes", "activityTypes",
            "educationLevels", "englishLevels", "computerSkillLevels"
        ));
    }

    /**
     * SP self-service experiences page.
     *
     * Only a provider acting as an HLH (host) authors experiences — an HRP is a
     * regional partner and an OSP supplies services into an experience, neither
     * hosts one, so the page is refused unless "hlh" is among their types.
     * Everything submitted here goes to HCT for review before travellers see it.
     */
    public function experiences()
    {
        $user = auth()->user();
        $provider = ServiceProvider::where("user_id", $user->id)->firstOrFail();

        if (!$provider->isHost()) {
            return redirect()->route("sp.dashboard")->with(
                "status",
                "Experiences are managed by homestay/lodge hosts."
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
        // The three structural categories. Chosen first; each shows a different
        // set of sections.
        $experienceCategories    = SystemList::ofType("experience_category")->pluck("name");
        $roomCategories          = SystemList::ofType("room_category")->pluck("name");
        $mealPlans               = SystemList::ofType("meal_plan")->pluck("name");

        // How many listings this host may hold. HCT edits it in the control
        // panel (Settings → Providers); 0 means no limit.
        $experienceCap = (int) Setting::getValue("max_experiences_per_provider", 10);

        return view("portal.sp.experiences", compact(
            "provider", "regions", "regenerativeProjects",
            "accommodationCategories", "serviceTypes", "currencies",
            "bestSeasons", "dayInclusions", "experienceCap",
            "experienceCategories", "roomCategories", "mealPlans"
        ));
    }

    /**
     * SP self-service "Services, Rooms & Pricing" page — mirrors the admin
     * /providers/{id}/edit pricing card so SPs can manage their own room
     * inventory + rates without going through HCT.
     *
     * A rate card is what an OSP sells, so it is offered only to providers who
     * signed up as one. A pure host gets experiences instead; a host that also
     * ticked OSP (it runs a taxi as well as a homestay) gets both.
     */
    public function pricing()
    {
        $user = auth()->user();
        $provider = ServiceProvider::where("user_id", $user->id)->firstOrFail();

        if (!$provider->suppliesServices()) {
            return redirect()->route("sp.dashboard")->with(
                "status",
                "Rates and services are managed by providers offering services."
            );
        }

        $serviceTypes            = SystemList::ofType("service_type")->get();
        $accommodationCategories = SystemList::ofType("accommodation_category")->get();
        $vehicleTypes            = SystemList::ofType("vehicle_type")->get();
        $guideTypes              = SystemList::ofType("guide_preference")->get();
        $activityTypes           = SystemList::ofType("activity_type")->get();
        $occupancyUnits          = SystemList::ofType("occupancy_unit")->get();
        // `occupancy_unit` mixes how a room is sold with how a price is
        // charged, so each picker reads the list that is actually its own.
        $roomOccupancies         = SystemList::ofType("room_occupancy")->get();
        $transportUnits          = SystemList::ofType("transport_unit")->get();
        $activityUnits           = SystemList::ofType("activity_unit")->get();
        $mealPlans               = SystemList::ofType("meal_plan")->get();
        $roomCategories          = SystemList::ofType("room_category")->get();
        // "Other langages (from a list)" for a guide — HCT extends the list
        // from the control panel like every other one.
        $languages               = SystemList::ofType("language")->get();
        return view("portal.sp.pricing", compact(
            "provider",
            "serviceTypes", "accommodationCategories", "vehicleTypes", "guideTypes", "activityTypes",
            "occupancyUnits", "roomOccupancies", "transportUnits", "activityUnits",
            "mealPlans", "roomCategories", "languages"
        ));
    }
}
