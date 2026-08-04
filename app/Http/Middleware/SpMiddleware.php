<?php

namespace App\Http\Middleware;

use App\Models\ServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SpMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->isServiceProvider()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            return redirect('/login')->with('error', 'Access denied.');
        }

        // A pending or rejected applicant is sent to the "application under
        // review" page; one with no application on file is sent to the form to
        // start one. A hidden provider keeps the working dashboard — being
        // hidden only takes them out of what travellers are shown, so they can
        // still keep their rates and availability current.
        $provider = ServiceProvider::where('user_id', auth()->id())->first();
        if (!$provider || !$provider->canSignIn()) {
            // A banned provider is told the same thing a hidden one would be.
            // Which of the two it is, and why, is HCT's business — the wording
            // here must not let them work it out.
            $message = match (true) {
                $provider && $provider->isBanned() => 'This account is currently out of service. Please contact HCT.',
                $provider && $provider->status === 'rejected' => 'Your service provider application was not approved. Please contact HCT for details.',
                default => 'Your service provider application is under review. You will get an email once it is approved.',
            };
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => $message], 403);
            }
            return redirect($provider ? route('sp.status') : route('sp.application'))
                ->with('error', $message);
        }

        return $next($request);
    }
}
