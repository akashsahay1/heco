<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Services\PdfService;

class PdfController extends Controller
{
    public function tripPdf(int $tripId)
    {
        $trip = Trip::findOrFail($tripId);

        // Serves both the admin route (hct middleware) and the portal route
        // (auth only): HCT staff may download any trip; a traveller only their
        // own. Without this a traveller could pull another traveller's itinerary.
        $user = auth()->user();
        if (!$user || (!$user->isHct() && (int) $trip->user_id !== (int) $user->id)) {
            abort(403);
        }

        // HCT staff see the internal margin breakdown (HRP/HCT); a traveller
        // downloading their own trip must NOT — those are hidden from travellers.
        $pdf = app(PdfService::class)->generateTripItinerary($trip, $user->isHct());
        return $pdf->download("trip-" . $trip->trip_id . ".pdf");
    }
}
