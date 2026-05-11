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

        // Only approved service providers get the working dashboard. Pending /
        // rejected applicants are bounced to the public application page with a
        // status banner so they understand their application is still in review.
        $provider = ServiceProvider::where('user_id', auth()->id())->first();
        if (!$provider || $provider->status !== 'approved') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Your service provider application is still under review.'], 403);
            }
            $message = $provider && $provider->status === 'rejected'
                ? 'Your service provider application was not approved. Please contact HCT for details.'
                : 'Your service provider application is under review. You will get an email once it is approved.';
            return redirect('/join')->with('error', $message);
        }

        return $next($request);
    }
}
