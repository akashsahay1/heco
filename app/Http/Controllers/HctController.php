<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\Experience;
use App\Models\RegenerativeProject;
use App\Models\SystemList;

class HctController extends Controller
{
    public function dashboard()
    {
        return view("admin.dashboard");
    }

    public function admin()
    {
        $hctUsers = User::whereIn("user_role", ["hct_admin", "hct_collaborator"])->get();
        return view("admin.admin", compact("hctUsers"));
    }

    public function travelPreferences()
    {
        return view("admin.travel-preferences");
    }

    public function editTravelPreference($id)
    {
        $allowedTypes = [
            "accommodation_comfort",
            "vehicle_comfort",
            "guide_preference",
            "travel_pace",
            "budget_sensitivity",
        ];
        $item = SystemList::findOrFail($id);
        if (!in_array($item->list_type, $allowedTypes, true)) {
            abort(404);
        }
        $labels = [
            "accommodation_comfort" => "Accommodation Comfort",
            "vehicle_comfort"       => "Vehicle Comfort",
            "guide_preference"      => "Guide Preference",
            "travel_pace"           => "Travel Pace",
            "budget_sensitivity"    => "Budget Sensitivity",
        ];
        return view("admin.travel-preferences-edit", [
            "item"      => $item,
            "typeLabel" => $labels[$item->list_type] ?? $item->list_type,
        ]);
    }

    public function controlPanel()
    {
        $settingGroups = \App\Models\Setting::query()
            ->select("group")
            ->distinct()
            ->orderBy("group")
            ->pluck("group")
            ->all();
        if (empty($settingGroups)) {
            $settingGroups = ["general"];
        }
        $systemListTypes = [
            "service_type"           => "Service Types",
            "accommodation_category" => "Accommodation Categories",
            "vehicle_type"           => "Vehicle Types",
            "activity_type"          => "Activity Types",
            "experience_type"        => "Experience Types",
            "payment_mode"           => "Payment Modes",
        ];
        return view("admin.control-panel", compact("settingGroups", "systemListTypes"));
    }

    public function leads(\Illuminate\Http\Request $request)
    {
        $hctUsers = User::whereIn("user_role", ["hct_admin", "hct_collaborator"])
            ->where("status", "active")
            ->orderBy("full_name")
            ->get(["id", "full_name", "email"]);

        // Server-side filters (Travelers-style — explicit Apply, GET form,
        // full-page reload). See feedback memory 'feedback-server-side-listings'.
        $stage  = $request->get('stage', '');
        $search = trim($request->get('search', ''));

        $query = \App\Models\Lead::with(['user', 'trip', 'assignedHct']);
        if ($stage !== '') $query->where('stage', $stage);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn($u) =>
                    $u->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                )->orWhereHas('trip', fn($t) => $t->where('trip_id', 'like', "%{$search}%"));
            });
        }
        $leads = $query->orderBy('enquiry_date', 'desc')->paginate(20)->withQueryString();

        return view('admin.leads', compact('hctUsers', 'leads', 'stage', 'search'));
    }

    public function trips(\Illuminate\Http\Request $request)
    {
        // Server-side filters (Travelers-style). Mirrors getUpcomingTrips AJAX
        // logic so the existing endpoint stays usable for the dashboard widget.
        $status   = $request->get('status', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo   = $request->get('date_to', '');
        $search   = trim($request->get('search', ''));

        $query = \App\Models\Trip::with(['user', 'regions']);
        $allowedStatuses = ['not_confirmed', 'confirmed', 'running', 'completed', 'cancelled'];
        if (in_array($status, $allowedStatuses, true)) {
            $query->where('status', $status);
        }
        if ($dateFrom !== '') $query->whereDate('start_date', '>=', $dateFrom);
        if ($dateTo !== '')   $query->whereDate('start_date', '<=', $dateTo);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('trip_id', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) =>
                      $u->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                  );
            });
        }

        $trips = $query->orderByRaw('start_date IS NULL, start_date ASC')
            ->paginate(20)
            ->withQueryString();

        return view('admin.trips', compact('trips', 'status', 'dateFrom', 'dateTo', 'search'));
    }

    public function calendar()
    {
        return view("admin.calendar");
    }

    public function payments()
    {
        return view("admin.payments");
    }

    public function gst()
    {
        return view("admin.gst");
    }

    public function providers(\Illuminate\Http\Request $request)
    {
        $regions = Region::where("is_active", true)->orderBy("name")->get();

        // Server-side filters (Travelers-style — explicit Apply, GET form).
        // Empty status = show all (including removed) by default. Specific
        // status values filter to that one status.
        $status        = $request->get('status', '');
        $providerType  = $request->get('provider_type', '');
        $regionId      = $request->get('region_id', '');
        $search        = trim($request->get('search', ''));

        $query = \App\Models\ServiceProvider::with(['region', 'lastUpdatedBy']);

        $allowedStatuses = ['approved', 'pending', 'rejected', 'removed'];
        if (in_array($status, $allowedStatuses, true)) {
            $query->where('status', $status);
        }
        if ($providerType !== '') $query->where('provider_type', $providerType);
        if ($regionId !== '')     $query->where('region_id', $regionId);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_1', 'like', "%{$search}%");
            });
        }

        $providers = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.providers', compact(
            'regions', 'providers', 'status', 'providerType', 'regionId', 'search'
        ));
    }

    public function providerShow($id)
    {
        $provider = ServiceProvider::with(["region", "user", "lastUpdatedBy", "approvedBy", "pricing"])->find($id);
        if (!$provider) {
            return redirect()->route('hct.providers')
                ->with('flash', "Provider #{$id} no longer exists.");
        }
        return view("admin.providers.show", compact("provider"));
    }

    public function providerEdit($id)
    {
        $provider = ServiceProvider::with(["region", "lastUpdatedBy"])->find($id);
        if (!$provider) {
            return redirect()->route('hct.providers')
                ->with('flash', "Provider #{$id} no longer exists.");
        }
        $regions = Region::where("is_active", true)->orderBy("name")->get();
        $serviceTypes            = SystemList::ofType("service_type")->get();
        $accommodationCategories = SystemList::ofType("accommodation_category")->get();
        $vehicleTypes            = SystemList::ofType("vehicle_type")->get();
        $guideTypes              = SystemList::ofType("guide_preference")->get();
        $activityTypes           = SystemList::ofType("activity_type")->get();
        $occupancyUnits          = SystemList::ofType("occupancy_unit")->get();
        $mealPlans               = SystemList::ofType("meal_plan")->get();
        $roomCategories          = SystemList::ofType("room_category")->get();
        return view("admin.providers.edit", compact(
            "provider", "regions",
            "serviceTypes", "accommodationCategories", "vehicleTypes", "guideTypes", "activityTypes",
            "occupancyUnits", "mealPlans", "roomCategories"
        ));
    }

    public function travelers(Request $request)
    {
        $segment = $request->get('segment', 'all');
        if (!in_array($segment, ['all', 'with_bookings', 'without_bookings'], true)) {
            $segment = 'all';
        }
        $search = trim((string) $request->get('search', ''));

        // A "booking" is a trip that has progressed past exploration.
        $bookingStatuses = ['confirmed', 'running', 'completed'];

        $query = User::where('user_role', 'traveller')->withCount('trips');

        if ($segment === 'with_bookings') {
            $query->whereHas('trips', fn($q) => $q->whereIn('status', $bookingStatuses));
        } elseif ($segment === 'without_bookings') {
            $query->whereDoesntHave('trips', fn($q) => $q->whereIn('status', $bookingStatuses));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $travelers = $query->orderBy('created_at', 'desc')->paginate(30)->withQueryString();

        return view('admin.travelers', compact('travelers', 'segment', 'search'));
    }

    public function providerApplications()
    {
        return view("admin.provider-applications");
    }

    public function pendingPricing()
    {
        $pendingCount = \App\Models\SpPricing::where('approval_status', 'pending')->count();
        return view("admin.pending-pricing", compact('pendingCount'));
    }

    public function regions()
    {
        return view("admin.regions.index");
    }

    public function currencies()
    {
        return view("admin.currencies.index");
    }

    public function experiences()
    {
        return view("admin.experiences.index");
    }

    public function createExperience()
    {
        $regions = Region::where("is_active", true)->orderBy("name")->get();
        $hlhs = ServiceProvider::where("provider_type", "hlh")->where("status", "approved")->get();
        $rps = RegenerativeProject::where("is_active", true)->get();
        return view("admin.experiences.form", compact("regions", "hlhs", "rps"));
    }

    public function editExperience(int $id)
    {
        $experience = Experience::with('days')->findOrFail($id);
        $regions = Region::where("is_active", true)->orderBy("name")->get();
        $hlhs = ServiceProvider::where("provider_type", "hlh")->where("status", "approved")->get();
        $rps = RegenerativeProject::where("is_active", true)->get();
        return view("admin.experiences.form", compact("experience", "regions", "hlhs", "rps"));
    }

    public function regenerativeProjects()
    {
        return view("admin.regenerative-projects.index");
    }

    public function createRegenerativeProject()
    {
        $regions = Region::where("is_active", true)->orderBy("name")->get();
        return view("admin.regenerative-projects.form", compact("regions"));
    }

    public function editRegenerativeProject(int $id)
    {
        $project = RegenerativeProject::findOrFail($id);
        $regions = Region::where("is_active", true)->orderBy("name")->get();
        return view("admin.regenerative-projects.form", compact("project", "regions"));
    }
}
