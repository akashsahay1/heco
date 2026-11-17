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
        // Still being built by HECO.
        if ($trip->hiddenFrom($user)) {
            abort(403, 'HECO is still preparing this itinerary.');
        }

        // Notes for Team go only into a copy HECO's own staff ask for, ticked
        // off by default (answers, 2026-11-12, Q5). A traveller's copy never
        // has them, whatever the address says.
        $teamNotes = $user->isHct() && request()->boolean('team_notes');

        $pdf = app(PdfService::class)->generateTripItinerary($trip, $teamNotes);
        return $pdf->download("trip-" . $trip->trip_id . ".pdf");
    }
}
