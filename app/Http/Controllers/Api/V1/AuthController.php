<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderAccountResource;
use App\Models\ApiToken;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Services\AccountOtpService;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Bearer-token sign-in for the HECO Provider app.
 *
 * Only service providers may sign in here — travellers and HCT staff use the
 * web portal, and letting their credentials mint a mobile token would hand out
 * a session for an app that has nothing to show them.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device' => 'nullable|string|max:120',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        // One message for both cases — never reveal which emails exist.
        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Incorrect email or password.'],
            ]);
        }

        if (!$user->isServiceProvider()) {
            return response()->json([
                'error' => 'This app is for HECO service providers. Please use the web portal.',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json(['error' => 'This account is not active.'], 403);
        }

        $provider = ServiceProvider::with('region')->where('user_id', $user->id)->first();

        [$access, $refresh] = ApiToken::issuePair($user, $request->input('device'));

        return response()->json([
            'success' => true,
            'token_type' => 'Bearer',
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_in_days' => ApiToken::ACCESS_DAYS,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'user_role' => $user->user_role,
            ],
            'provider' => ProviderAccountResource::make($provider),
        ]);
    }


    /**
     * Exchange a refresh token for a new pair. The presented refresh token is
     * consumed, so a stolen one is usable at most once — and only until the
     * real device refreshes and invalidates it.
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);

        $token = ApiToken::with('user')
            ->where('token_hash', ApiToken::hashFor($request->input('refresh_token')))
            ->where('name', ApiToken::REFRESH)
            ->first();

        if (!$token || !$token->user || $token->isExpired()) {
            $token?->delete();
            return response()->json(['error' => 'Invalid or expired refresh token.'], 401);
        }

        if ($token->user->status !== 'active') {
            return response()->json(['error' => 'This account is not active.'], 403);
        }

        $device = $token->device;
        $user = $token->user;
        $token->delete();

        [$access, $refresh] = ApiToken::issuePair($user, $device);

        return response()->json([
            'success' => true,
            'token_type' => 'Bearer',
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_in_days' => ApiToken::ACCESS_DAYS,
        ]);
    }

    /** The account behind the current token — used on app start. */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();
        $provider = ServiceProvider::with('region')->where('user_id', $user->id)->first();

        if (!$provider) {
            return response()->json([
                'error' => 'No service provider profile is linked to this account.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'provider' => ProviderAccountResource::make($provider),
        ]);
    }

    /** Revoke just this device's token. */
    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')?->delete();
        return response()->json(['success' => true]);
    }

    /** Revoke every token for the account — "sign out everywhere". */
    public function logoutAll(Request $request): JsonResponse
    {
        ApiToken::where('user_id', Auth::id())->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Start an in-app password reset: email a one-time code and hand back the
     * token the app carries to [resetPassword]. The app never sends the provider
     * to a web reset link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $otp = app(PasswordResetOtpService::class);
        $user = User::where('email', $request->input('email'))->first();

        // Only real provider accounts get a code, but a token is always minted
        // so the response is identical either way and cannot enumerate accounts.
        if ($user && $user->isServiceProvider() && $user->status === 'active') {
            $otp->sendCode($user);
        } else {
            $user = null;
        }

        return response()->json([
            'success' => true,
            'verification' => $otp->startSession($user),
            'message' => 'If that email is registered, a code is on its way.',
        ]);
    }

    /**
     * Finish the reset: verify the code, set the new password and sign them in,
     * so they land straight back in the app.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'verification' => 'required|string',
            'otp' => 'required|digits:6',
            'password' => ['required', 'string', 'min:8', 'regex:/[0-9]/', 'regex:/[^A-Za-z0-9]/'],
            'device' => 'nullable|string|max:120',
        ], [
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must include a number and a symbol.',
        ]);

        $otp = app(PasswordResetOtpService::class);
        $user = $otp->userFor($request->input('verification'));

        // Same message whether the token is a decoy, expired, or the code is
        // simply wrong — nothing here reveals whether the email exists.
        if (!$user) {
            return response()->json(['error' => 'That code is incorrect. Please try again.'], 422);
        }
        if ($error = $otp->verify($user->id, $request->input('otp'))) {
            return response()->json(['error' => $error], 422);
        }

        $user->password = $request->input('password');
        $user->password_set_at = now();
        $user->save();

        $otp->clearSession($request->input('verification'));

        // Every other device's session dies with the old password.
        ApiToken::where('user_id', $user->id)->delete();
        [$access, $refresh] = ApiToken::issuePair($user, $request->input('device'));

        $provider = ServiceProvider::with('region')->where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'token_type' => 'Bearer',
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_in_days' => ApiToken::ACCESS_DAYS,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'user_role' => $user->user_role,
            ],
            'provider' => ProviderAccountResource::make($provider),
        ]);
    }

    /**
     * Finish an app signup: confirm the code emailed when the application was
     * submitted, set the password, and hand back a token pair so the app lands
     * straight on the "under review" screen. The provider is still pending
     * approval — that is a separate gate from having a working login.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'verification' => 'required|string',
            'otp' => 'required|digits:6',
            'password' => ['required', 'string', 'min:8', 'regex:/[0-9]/', 'regex:/[^A-Za-z0-9]/'],
            'device' => 'nullable|string|max:120',
        ], [
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must include a number and a symbol.',
        ]);

        $otp = app(AccountOtpService::class);
        $user = $otp->userFor($request->input('verification'));

        // Same message whether the token is a decoy, expired, or the code is
        // simply wrong — nothing here reveals whether the email exists.
        if (!$user) {
            return response()->json(['error' => 'That code is incorrect. Please try again.'], 422);
        }
        if ($error = $otp->verify($user->id, $request->input('otp'))) {
            return response()->json(['error' => $error], 422);
        }

        // Email confirmed + password chosen: the account is now fully set up.
        // password_set_at is what finalizeApproval() reads to skip the emailed
        // set-password link when approving.
        $user->password = $request->input('password');
        $user->password_set_at = now();
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->save();

        $otp->clearSession($request->input('verification'));

        [$access, $refresh] = ApiToken::issuePair($user, $request->input('device'));

        $provider = ServiceProvider::with('region')->where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'token_type' => 'Bearer',
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_in_days' => ApiToken::ACCESS_DAYS,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'user_role' => $user->user_role,
            ],
            'provider' => ProviderAccountResource::make($provider),
        ]);
    }

    /** Re-send the signup verification code the app is waiting on. */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate(['verification' => 'required|string']);

        $otp = app(AccountOtpService::class);
        $user = $otp->userFor($request->input('verification'));

        // Silent success for an unknown/expired token so a caller can't probe
        // which tokens are live.
        if ($user) {
            $provider = ServiceProvider::where('user_id', $user->id)->first();
            $otp->sendCode($user, $provider?->contact_person ?: $user->full_name);
        }

        return response()->json([
            'success' => true,
            'message' => 'If your code has expired, a new one is on its way.',
        ]);
    }
}
