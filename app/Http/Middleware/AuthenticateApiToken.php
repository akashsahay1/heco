<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the mobile app from an `Authorization: Bearer <token>` header.
 *
 * On success the user is signed in for the rest of the request via
 * Auth::setUser(), so everything downstream — including AjaxController's
 * ACTION_LEVELS gate, which reads auth()->user() — behaves exactly as it does
 * for a browser session. No session or CSRF token is involved.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainText = $request->bearerToken();

        if (!$plainText) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $token = ApiToken::with('user')
            ->where('token_hash', ApiToken::hashFor($plainText))
            ->first();

        // A refresh token may only be spent at /auth/refresh, never used to
        // authenticate an ordinary call.
        if (!$token || !$token->user || $token->name !== ApiToken::ACCESS) {
            return response()->json(['error' => 'Invalid token.'], 401);
        }

        if ($token->isExpired()) {
            $token->delete();
            return response()->json(['error' => 'Token expired.'], 401);
        }

        if ($token->user->status !== 'active') {
            return response()->json(['error' => 'This account is not active.'], 403);
        }

        Auth::setUser($token->user);
        $request->setUserResolver(fn() => $token->user);
        $request->attributes->set('api_token', $token);

        // Cheap enough to write on every call and useful for spotting stale
        // devices; skipped when it would be a no-op within the same minute.
        if (!$token->last_used_at || $token->last_used_at->diffInMinutes(now()) >= 1) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
