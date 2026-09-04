<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The API is registered here rather than via `withRouting(api:)` for one
        // reason: that helper binds no domain, so /api/* answered on the admin
        // host as readily as on the portal — and it did, in production. The
        // mobile app is a *provider* client; it belongs to the consumer portal
        // and has no business on the back-office domain.
        //
        // `web.php` already scopes its two halves this way, so this only brings
        // the API in line. The prefix and middleware group replicate what
        // `withRouting(api:)` applied.
        then: function () {
            Route::middleware('api')
                //->domain(config('app.portal_domain'))
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if ($request->getHost() === config('app.admin_domain')) {
                return route('admin.login');
            }
            return route('login');
        });

        $middleware->alias([
            'hct' => \App\Http\Middleware\HctMiddleware::class,
            // Named for the role it checks. It was registered as 'hct_admin'
            // while routes/web.php asked for 'administrator' — the rename in
            // fe58fe8 reached the roles, the model and the routes but not this
            // line, so /admin and /travel-preferences answered 500 with
            // "Target class [administrator] does not exist" from 2026-08-07 on.
            'administrator' => \App\Http\Middleware\HctAdminMiddleware::class,
            'sp' => \App\Http\Middleware\SpMiddleware::class,
            // Bearer-token auth for the mobile app (see AuthenticateApiToken).
            'api.token' => \App\Http\Middleware\AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
