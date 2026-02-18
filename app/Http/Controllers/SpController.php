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
        $provider = ServiceProvider::where("user_id", $user->id)->with(["region", "pricing"])->first();
        return view("portal.sp.dashboard", compact("provider"));
    }
}
