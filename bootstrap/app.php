<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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
            'hct_admin' => \App\Http\Middleware\HctAdminMiddleware::class,
            'sp' => \App\Http\Middleware\SpMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
