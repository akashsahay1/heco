<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\User;

class AuthController extends Controller
{
    public function show_login()
    {
        if (Auth::check()) {
            return $this->redirect_by_role(Auth::user());
        }
        return view("portal.auth.login");
    }

    public function show_signup()
    {
        if (Auth::check()) {
            return $this->redirect_by_role(Auth::user());
        }
        return view("portal.auth.signup", [
            "countries" => config("countries.list", []),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect("/");
    }

    public function show_forgot_password()
    {
        return view("portal.auth.forgot-password");
    }

    public function send_reset_link(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "email" => "required|email",
        ]);

        if ($request->expectsJson()) {
            if ($validator->fails()) {
                return response()->json(["success" => false, "error" => $validator->errors()->first()], 422);
            }
            $status = Password::sendResetLink($request->only("email"));
            $ok = $status === Password::RESET_LINK_SENT;
            return response()->json([
                "success" => $ok,
                "message" => __($status),
            ], $ok ? 200 : 422);
        }

        $validator->validate();
        $status = Password::sendResetLink($request->only("email"));
        return back()->with("status", __($status));
    }

    public function show_reset_password(string $token)
    {
        return view("portal.auth.reset-password", ["token" => $token]);
    }

    public function reset_password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "token" => "required",
            "email" => "required|email",
            "password" => "required|min:8|confirmed",
        ]);

        if ($request->expectsJson()) {
            if ($validator->fails()) {
                return response()->json(["success" => false, "error" => $validator->errors()->first()], 422);
            }
            $status = Password::reset(
                $request->only("email", "password", "password_confirmation", "token"),
                function (User $user, string $password) {
                    $user->forceFill(["password" => $password])->setRememberToken(Str::random(60));
                    $user->save();
                }
            );
            $ok = $status === Password::PASSWORD_RESET;
            return response()->json([
                "success" => $ok,
                "message" => __($status),
                "redirect" => $ok ? "/login" : null,
            ], $ok ? 200 : 422);
        }

        $validator->validate();
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

    protected function redirect_by_role(User $user)
    {
        return match(true) {
            $user->isHct() => redirect('//' . config('app.admin_domain') . '/dashboard'),
            $user->isServiceProvider() => redirect("/sp/dashboard"),
            default => redirect("/home"),
        };
    }
}
