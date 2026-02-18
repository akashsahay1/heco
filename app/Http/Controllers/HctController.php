<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\Experience;
use App\Models\RegenerativeProject;

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

    public function controlPanel()
    {
        return view("admin.control-panel");
    }

    public function leads()
    {
        return view("admin.leads");
    }

    public function trips()
    {
        return view("admin.trips");
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

    public function providers()
    {
        $regions = Region::where("is_active", true)->orderBy("name")->get();
        return view("admin.providers", compact("regions"));
    }

    public function travelers()
    {
        return view("admin.travelers");
    }

    public function providerApplications()
    {
        return view("admin.provider-applications");
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
        $experience = Experience::findOrFail($id);
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
