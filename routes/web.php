<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\TravellerController;
use App\Http\Controllers\HctController;
use App\Http\Controllers\TripManagerController;
use App\Http\Controllers\SpController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\AjaxController;

// ============================================
// ADMIN DOMAIN (hecoadmin.test)
// ============================================
Route::domain(config('app.admin_domain'))->group(function () {

    // Auth
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    // Root redirect
    Route::get('/', fn() => redirect('/dashboard'));

    // HCT Dashboard (auth + hct middleware)
    Route::middleware(['auth', 'hct'])->group(function () {
        Route::get('/dashboard', [HctController::class, 'dashboard'])->name('hct.dashboard');
        Route::get('/control-panel', [HctController::class, 'controlPanel'])->name('hct.control-panel');
        Route::get('/leads', [HctController::class, 'leads'])->name('hct.leads');
        Route::get('/trips', [HctController::class, 'trips'])->name('hct.trips');
        Route::get('/calendar', [HctController::class, 'calendar'])->name('hct.calendar');
        Route::get('/payments', [HctController::class, 'payments'])->name('hct.payments');
        Route::get('/gst', [HctController::class, 'gst'])->name('hct.gst');
        Route::get('/providers', [HctController::class, 'providers'])->name('hct.providers');
        Route::get('/travelers', [HctController::class, 'travelers'])->name('hct.travelers');
        Route::get('/provider-applications', [HctController::class, 'providerApplications'])->name('hct.provider-applications');

        // Region Management
        Route::get('/regions', [HctController::class, 'regions'])->name('hct.regions');

        // Currency Management
        Route::get('/currencies', [HctController::class, 'currencies'])->name('hct.currencies');

        // Experience Management
        Route::get('/experiences', [HctController::class, 'experiences'])->name('hct.experiences');
        Route::get('/experiences/create', [HctController::class, 'createExperience'])->name('hct.experiences.create');
        Route::get('/experiences/{id}/edit', [HctController::class, 'editExperience'])->name('hct.experiences.edit');

        // Regenerative Projects
        Route::get('/regenerative-projects', [HctController::class, 'regenerativeProjects'])->name('hct.rp');
        Route::get('/regenerative-projects/create', [HctController::class, 'createRegenerativeProject'])->name('hct.rp.create');
        Route::get('/regenerative-projects/{id}/edit', [HctController::class, 'editRegenerativeProject'])->name('hct.rp.edit');

        // Admin tab (hct_admin only)
        Route::middleware('hct_admin')->group(function () {
            Route::get('/admin', [HctController::class, 'admin'])->name('hct.admin');
        });

        // Trip Manager
        Route::get('/trip-manager/{trip_id}', [TripManagerController::class, 'show'])->name('trip-manager');

        // PDF
        Route::get('/pdf/trip/{trip_id}', [PdfController::class, 'tripPdf'])->name('pdf.trip');
    });

    // AJAX
    Route::post('/ajax', [AjaxController::class, 'adminIndex'])->name('admin.ajax');
});

// ============================================
// PORTAL DOMAIN (hecoportal.test)
// ============================================
Route::domain(config('app.portal_domain'))->group(function () {

    // Public
    Route::get('/', [HomepageController::class, 'landing']);
    Route::get('/home', [HomepageController::class, 'home'])->name('home');
    Route::get('/experience/{slug}', [HomepageController::class, 'experienceDetail'])->name('experience.detail');
    Route::get('/join', [SpController::class, 'application'])->name('sp.application');

    // Static Pages
    Route::get('/about', fn() => view('portal.pages.about'))->name('about');
    Route::get('/privacy', fn() => view('portal.pages.privacy'))->name('privacy');
    Route::get('/terms', fn() => view('portal.pages.terms'))->name('terms');
    Route::get('/contact', fn() => view('portal.pages.contact'))->name('contact');
    Route::get('/help', fn() => view('portal.pages.help'))->name('help');
    Route::get('/careers', fn() => view('portal.pages.careers'))->name('careers');
    Route::get('/guidelines', fn() => view('portal.pages.guidelines'))->name('guidelines');

    // Auth (redirect to homepage with modal trigger)
    Route::get('/login', fn() => redirect('/home?auth=login'))->name('login');
    Route::get('/register', fn() => redirect('/home?auth=register'))->name('register');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

    // Social Auth
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');

    // Traveller (auth required)
    Route::middleware('auth')->group(function () {
        Route::get('/my-itineraries', [TravellerController::class, 'myItineraries'])->name('my-itineraries');
        Route::get('/my-itineraries/{trip_id}', [TravellerController::class, 'resumeTrip'])->name('trip.resume');
        Route::get('/profile', [TravellerController::class, 'profile'])->name('profile');
    });

    // SP Dashboard (auth + sp middleware)
    Route::middleware(['auth', 'sp'])->group(function () {
        Route::get('/sp/dashboard', [SpController::class, 'dashboard'])->name('sp.dashboard');
    });

    // AJAX
    Route::post('/ajax', [AjaxController::class, 'portalIndex'])->name('portal.ajax');
});
