<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }
        return view("portal.auth.login");
    }

    public function showRegister()
    {
        return view("portal.auth.register");
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect("/");
    }

    public function showForgotPassword()
    {
        return view("portal.auth.forgot-password");
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(["email" => "required|email"]);
        $status = Password::sendResetLink($request->only("email"));
        return back()->with("status", __($status));
    }

    public function showResetPassword(string $token)
    {
        return view("portal.auth.reset-password", ["token" => $token]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            "token" => "required",
            "email" => "required|email",
            "password" => "required|min:8|confirmed",
        ]);

        $status = Password::reset(
            $request->only("email", "password", "password_confirmation", "token"),
            function (User $user, string $password) {
                $user->forceFill(["password" => $password])->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect("/login")->with("status", __($status))
            : back()->withErrors(["email" => [__($status)]]);
    }

    protected function redirectByRole(User $user)
    {
        return match(true) {
            $user->isHct() => redirect('//' . config('app.admin_domain') . '/dashboard'),
            $user->isServiceProvider() => redirect("/sp/dashboard"),
            default => redirect("/home"),
        };
    }
}
