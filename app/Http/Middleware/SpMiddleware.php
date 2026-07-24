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

        // Only approved service providers get the working dashboard. A pending or
        // rejected applicant is sent to the "application under review" page; one
        // with no application on file is sent to the form to start one.
        $provider = ServiceProvider::where('user_id', auth()->id())->first();
        if (!$provider || $provider->status !== 'approved') {
            $message = $provider && $provider->status === 'rejected'
                ? 'Your service provider application was not approved. Please contact HCT for details.'
                : 'Your service provider application is under review. You will get an email once it is approved.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => $message], 403);
            }
            return redirect($provider ? route('sp.status') : route('sp.application'))
                ->with('error', $message);
        }

        return $next($request);
    }
}
