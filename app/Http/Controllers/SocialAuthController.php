<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        return match(true) {
            $user->isHct() => redirect('//' . config('app.admin_domain') . '/dashboard'),
            $user->isServiceProvider() => redirect("/sp/dashboard"),
            default => redirect("/home"),
        };
    }
}
