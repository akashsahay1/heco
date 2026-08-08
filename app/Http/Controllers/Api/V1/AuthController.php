<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderAccountResource;
use App\Models\ApiToken;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Services\AuthService;
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
    /**
     * Signup — the public "become a partner" application.
     *
     * It lived on ReferenceController, which serves dropdown options, for one
     * reason: it was the other endpoint that needed no token. Creating an
     * account belongs beside signing in to one.
     *
     * Straight into AuthService, with no allow-list in between. The list used
     * to exist because the request was forwarded into a dispatcher that picked
     * its action by scanning for a known key, so raw client input could have
     * selected a different one. The route decides now, so a field the app adds
     * arrives on its own instead of vanishing until someone remembers to name
     * it here.
     */
    public function register(Request $request): JsonResponse
    {
        $result = app(AuthService::class)->submitProviderApplication(
            $request->all(),
            array_values((array) $request->file('documents', [])),
        );

        return response()->json($result['body'], $result['status']);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device' => 'nullable|string|max:120',
        ]);

        // Scoped to provider roles: an email is unique per role, so the same
        // address may also carry the owner's traveller account. This app is a
        // provider client, and matching on email alone could hand it the
        // traveller row and reject a perfectly good provider login.
        $user = User::findByCredentials(
            $request->input('email'),
            $request->input('password'),
            User::PROVIDER_ROLES
        );

        if (!$user) {
            // A traveller who tried the provider app is told where to go, but
            // only once their password checks out — otherwise this would answer
            // whether an address is registered. One message for every other
            // case: never reveal which emails exist.
            if (User::findByCredentials($request->input('email'), $request->input('password'), ['traveller'])) {
                return response()->json([
                    'error' => 'This app is for HECO service providers. Please use the web portal.',
                ], 403);
            }
            throw ValidationException::withMessages([
                'email' => ['Incorrect email or password.'],
            ]);
        }

        if ($user->status !== 'active') {
            // Deliberately says nothing about why. A banned provider lands
            // here, and must not learn that from the message.
            return response()->json(['error' => 'This account is currently out of service. Please contact HECO.'], 403);
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
            // Deliberately says nothing about why. A banned provider lands
            // here, and must not learn that from the message.
            return response()->json(['error' => 'This account is currently out of service. Please contact HECO.'], 403);
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

    /**
     * The account behind the current token — used on app start, and polled
     * while an application is under review.
     *
     * The role is checked here, not just at login. An email may now belong to
     * several accounts, and the one this token was minted for can stop being a
     * provider afterwards — a removed provider is demoted back to traveller
     * rather than deleted, and its tokens outlive the demotion. Reading the
     * role from the token's own user is what tells those apart; the address
     * cannot, because the traveller account shares it.
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isServiceProvider()) {
            return response()->json([
                'error' => 'This account is no longer a HECO service provider.',
            ], 403);
        }

        $provider = ServiceProvider::with('region')->where('user_id', $user->id)->first();

        if (!$provider) {
            return response()->json([
                'error' => 'No service provider profile is linked to this account.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            // Same block login and the password reset return, so the app reads
            // the signed-in role from one shape wherever it asks.
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'user_role' => $user->user_role,
            ],
            'provider' => ProviderAccountResource::make($provider),
        ]);
    }

    /** Revoke just this device's token. */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_token');
        if (! $token) {
            return response()->json(['success' => true]);
        }

        // The refresh token goes with it. Deleting only the access token left
        // its twin alive for ninety days, able to mint a fresh working pair —
        // so signing out on a phone you had lost signed you out of nothing.
        // issuePair() stamps both with the same device, and the table exists so
        // devices can be revoked independently, so the device is what goes.
        ApiToken::where('user_id', $token->user_id)
            ->when(
                $token->device === null,
                fn ($query) => $query->whereNull('device'),
                fn ($query) => $query->where('device', $token->device),
            )
            ->delete();

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
        // Provider roles only — resetting from this app must never reach the
        // traveller account that may share the address.
        $user = User::findByEmailForRoles($request->input('email'), User::PROVIDER_ROLES);

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
}
