<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\PasswordResetOtpService;

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

    /**
     * Email a 6-digit reset code (no web link) and open the on-site OTP step.
     *
     * A code is only sent for a real account, but the response is identical
     * either way and both cases land on the OTP page, so it can't be used to
     * discover which emails are registered. The verify step is gated by the
     * `pwreset_uid` session written here.
     */
    public function send_reset_link(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "email" => "required|email",
        ]);
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(["success" => false, "error" => $validator->errors()->first()], 422);
            }
            return back()->withErrors(["email" => $validator->errors()->first()]);
        }

        $email = $request->input("email");
        // Portal-side roles only. An address shared with an HCT login must not
        // let a reset started on the public site change the admin's password —
        // that reset lives on the admin domain.
        $user = User::findByEmailForRoles($email, User::PORTAL_ROLES);
        if ($user) {
            app(PasswordResetOtpService::class)->sendCode($user);
            session(["pwreset_uid" => $user->id]);
        }
        // Always remember the email so both existing and unknown addresses reach
        // the OTP page identically (no account enumeration).
        session(["pwreset_email" => $email]);

        $message = "If that email is registered, a 6-digit code is on its way.";
        if ($request->expectsJson()) {
            return response()->json([
                "success" => true,
                "message" => $message,
                "redirect" => "/reset-password-otp",
            ]);
        }
        return redirect("/reset-password-otp")->with("status", $message);
    }

    /** The on-site OTP + new-password step. Gated by the pwreset_email session. */
    public function show_reset_otp()
    {
        $email = session("pwreset_email");
        if (!$email) {
            return redirect()->route("password.request")
                ->with("status", "Start a password reset to continue.");
        }
        return view("portal.auth.reset-password-otp", ["email" => $email]);
    }

    /** Verify the emailed code and set the new password, then send them to login. */
    public function reset_with_otp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "otp" => "required|digits:6",
            "password" => ["required", "string", "min:8", "regex:/[0-9]/", "regex:/[^A-Za-z0-9]/", "confirmed"],
        ], [
            "otp.digits" => "Enter the 6-digit code we emailed you.",
            "password.min" => "Password must be at least 8 characters.",
            "password.regex" => "Password must include a number and a symbol.",
            "password.confirmed" => "The passwords do not match.",
        ]);
        if ($validator->fails()) {
            return response()->json(["success" => false, "error" => $validator->errors()->first()], 422);
        }

        $uid = session("pwreset_uid");
        $otp = app(PasswordResetOtpService::class);

        // Same generic message whether the email was never registered (no uid)
        // or the code is simply wrong — neither reveals whether the email exists.
        if (!$uid) {
            return response()->json(["success" => false, "error" => "That code is incorrect. Please try again."], 422);
        }
        if ($error = $otp->verify($uid, $request->input("otp"))) {
            return response()->json(["success" => false, "error" => $error], 422);
        }

        $user = User::find($uid);
        if (!$user) {
            return response()->json(["success" => false, "error" => "That code is incorrect. Please try again."], 422);
        }

        $user->password = $request->input("password");
        $user->password_set_at = now();
        $user->setRememberToken(Str::random(60));
        $user->save();

        $request->session()->forget(["pwreset_uid", "pwreset_email"]);

        return response()->json([
            "success" => true,
            "message" => "Your password has been updated.",
            "redirect" => "/login",
        ]);
    }

    public function show_reset_password(string $token)
    {
        return view("portal.auth.reset-password", [
            "token" => $token,
            // Carried through from the emailed link so the POST knows which of
            // the accounts on this address the link belongs to.
            "role" => request("role", ""),
        ]);
    }

    /**
     * Finish a token-link reset.
     *
     * This page serves both domains — HCT staff are sent here by the admin's
     * "reset password" action, and providers by the approval email — so the
     * roles it may touch cannot be inferred from the host. The link carries the
     * role instead, and it narrows the lookup to the one account the token was
     * minted for. A link issued before this existed carries no role and falls
     * back to the old behaviour rather than refusing to work.
     */
    public function reset_password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "token" => "required",
            "email" => "required|email",
            "password" => "required|min:8|confirmed",
            "role" => "nullable|string|in:traveller,hrp,hlh,osp,administrator,collaborator",
        ]);

        if ($request->expectsJson()) {
            if ($validator->fails()) {
                return response()->json(["success" => false, "error" => $validator->errors()->first()], 422);
            }
            $status = Password::reset(
                $this->reset_credentials($request),
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
            $this->reset_credentials($request),
            function (User $user, string $password) {
                $user->forceFill(["password" => $password])->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect("/login")->with("status", __($status))
            : back()->withErrors(["email" => [__($status)]]);
    }

    /**
     * Credentials for the password broker, narrowed to the link's own role.
     *
     * The broker's user provider turns every non-password key into a where
     * clause and then takes the first row, so on a shared address the email
     * alone would resolve to whichever account is oldest — and reset that one's
     * password instead. `user_role` pins it to the account the link was for.
     */
    protected function reset_credentials(Request $request): array
    {
        $credentials = $request->only("email", "password", "password_confirmation", "token");
        if ($request->filled("role")) {
            $credentials["user_role"] = $request->input("role");
        }
        return $credentials;
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
