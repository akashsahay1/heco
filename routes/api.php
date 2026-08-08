<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProviderController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\UploadTestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (v1)
|--------------------------------------------------------------------------
|
| Serves the HECO Provider app. Everything past auth is a thin facade over the
| existing AjaxController dispatcher (see App\Http\Controllers\Api\V1), so the
| app and the web portal can never drift apart in behaviour.
|
| Auth is a bearer token issued by POST /api/v1/auth/login and verified by the
| `api.token` middleware, which signs the user in for the rest of the request.
| No session, no CSRF — these routes are not in the web middleware group.
|
*/

// Where /upload-test sends its file. Here rather than in web.php because
// these routes carry no CSRF, so curl can ask the same question the page does.
// Temporary: delete with the page once the answer is in hand.
Route::post('upload-test', [UploadTestController::class, 'store']);

Route::prefix('v1')->group(function () {

    // ── Public ───────────────────────────────────────────────────────────
    Route::post('auth/login', [AuthController::class, 'login']);
    // Authenticated by the refresh token in the body, not a bearer header.
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    // Finishes the reset with the emailed code — no web link involved.
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('reference', [ReferenceController::class, 'index']);
    // Signup. The path stays what it has always been so builds already on
    // people's phones keep working; only what handles it moved.
    Route::post('providers/applications', [AuthController::class, 'register']);

    // ── Authenticated ────────────────────────────────────────────────────
    Route::middleware('api.token')->group(function () {

        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::prefix('provider')->group(function () {
            Route::get('profile', [ProviderController::class, 'profile']);
            Route::put('profile', [ProviderController::class, 'updateProfile']);
            // POST, not PUT: PHP does not parse a multipart body on PUT.
            Route::post('profile/photo', [ProviderController::class, 'updatePhoto']);
            // Reading them needs no route of its own — they come back on the
            // provider record, like the photo does.
            Route::post('profile/documents', [ProviderController::class, 'addDocument']);

            Route::get('bookings', [ProviderController::class, 'bookings']);

            // A regional partner overseeing the providers in their region.
            Route::get('region/providers', [ProviderController::class, 'regionProviders']);

            Route::get('pricing', [ProviderController::class, 'pricing']);
            Route::post('pricing', [ProviderController::class, 'savePricing']);
            Route::delete('pricing/{id}', [ProviderController::class, 'deletePricing'])->whereNumber('id');

            Route::get('availability', [ProviderController::class, 'availability']);
            Route::post('availability/block', [ProviderController::class, 'blockDates']);
            Route::post('availability/unblock', [ProviderController::class, 'unblockDates']);
            Route::post('availability/ical', [ProviderController::class, 'saveIcalUrl']);
            Route::post('availability/ical/sync', [ProviderController::class, 'syncIcal']);

            Route::get('experiences', [ProviderController::class, 'experiences']);
            Route::post('experiences', [ProviderController::class, 'saveExperience']);
            Route::post('experiences/{id}/toggle', [ProviderController::class, 'toggleExperience'])->whereNumber('id');
            Route::delete('experiences/{id}', [ProviderController::class, 'deleteExperience'])->whereNumber('id');
        });

        Route::post('support', [ProviderController::class, 'requestSupport']);
    });
});
