<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AiConversation;
use App\Models\Experience;
use App\Models\Trip;
use App\Models\TripRegion;
use App\Models\TripSelectedExperience;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        if (!in_array($provider, ["google", "facebook"])) {
            abort(404);
        }
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        if (!in_array($provider, ["google", "facebook"])) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect("/login")->with("error", "Social login failed. Please try again.");
        }

        $idField = $provider . "_id";
        $user = User::where($idField, $socialUser->getId())->first();

        if (!$user) {
            $user = User::where("email", $socialUser->getEmail())->first();
            if ($user) {
                $user->update([$idField => $socialUser->getId()]);
            } else {
                $user = User::create([
                    "full_name" => $socialUser->getName(),
                    "email" => $socialUser->getEmail(),
                    "auth_type" => $provider,
                    $idField => $socialUser->getId(),
                    "avatar" => $socialUser->getAvatar(),
                    "user_role" => "traveller",
                ]);
            }
        }

        Auth::login($user, true);

        // Preserve guest trip data before clearing
        $guestTrip = session('guest_trip');
        $guestChat = session('guest_chat');

        // Clear previous trip data so traveller starts fresh each login
        if ($user->isTraveller()) {
            $trips = $user->trips()->whereIn('status', ['draft', 'not_confirmed'])->get();
            foreach ($trips as $t) {
                $t->aiConversations()->where('context_type', 'traveller_chat')->delete();
                $t->tripDays()->each(function ($day) {
                    $day->experiences()->delete();
                    $day->services()->delete();
                    $day->delete();
                });
                $t->selectedExperiences()->delete();
                $t->tripRegions()->delete();
                $t->update([
                    'adults' => 2, 'children' => 0, 'infants' => 0,
                    'start_location' => null, 'end_location' => null,
                    'start_date' => null, 'end_date' => null,
                    'anchor_point' => null, 'pickup_preference' => null,
                    'accommodation_comfort' => null, 'vehicle_comfort' => null,
                    'guide_preference' => null, 'travel_pace' => null,
                    'budget_sensitivity' => null, 'ai_raw_response' => null,
                    'trip_name' => 'My Trip',
                ]);
            }
        }

        // Restore guest trip data and sync to DB trip
        if (!empty($guestTrip['experience_ids'] ?? [])) {
            session(['guest_trip' => $guestTrip]);
            if ($guestChat) session(['guest_chat' => $guestChat]);

            // Sync guest journey to DB immediately (social login redirects, no AJAX sync call)
            $trip = Trip::where("user_id", $user->id)
                ->whereIn("status", ["draft", "not_confirmed"])
                ->orderBy("updated_at", "desc")
                ->first();
            if (!$trip) {
                $trip = Trip::create([
                    "trip_id" => Trip::generateTripId(),
                    "user_id" => $user->id,
                    "trip_name" => $guestTrip['trip_name'] ?? "My Trip",
                    "status" => "draft",
                    "stage" => "traveller_exploring",
                    "adults" => $guestTrip['adults'] ?? 1,
                    "children" => $guestTrip['children'] ?? 0,
                    "infants" => $guestTrip['infants'] ?? 0,
                    "accommodation_comfort" => $guestTrip['accommodation_comfort'] ?? null,
                    "vehicle_comfort" => $guestTrip['vehicle_comfort'] ?? null,
                    "guide_preference" => $guestTrip['guide_preference'] ?? null,
                ]);
            } else {
                $trip->update([
                    "adults" => $guestTrip['adults'] ?? $trip->adults,
                    "children" => $guestTrip['children'] ?? $trip->children,
                    "infants" => $guestTrip['infants'] ?? $trip->infants,
                    "trip_name" => $guestTrip['trip_name'] ?? $trip->trip_name,
                ]);
            }
            foreach ($guestTrip['experience_ids'] as $index => $expId) {
                $experience = Experience::find($expId);
                if (!$experience) continue;
                TripSelectedExperience::firstOrCreate([
                    "trip_id" => $trip->id,
                    "experience_id" => $experience->id,
                ], ["sort_order" => $index]);
                if ($experience->region_id) {
                    TripRegion::firstOrCreate([
                        "trip_id" => $trip->id,
                        "region_id" => $experience->region_id,
                    ]);
                }
            }
            session()->forget(['guest_chat', 'guest_trip']);
        } else {
            session()->forget(['guest_chat', 'guest_trip']);
        }

        return match(true) {
            $user->isHct() => redirect('//' . config('app.admin_domain') . '/dashboard'),
            $user->isServiceProvider() => redirect("/sp/dashboard"),
            default => redirect("/home"),
        };
    }
}
