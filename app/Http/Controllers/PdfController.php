<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Services\PdfService;

class PdfController extends Controller
{
    public function tripPdf(int $tripId)
    {
        $trip = Trip::findOrFail($tripId);
        $pdf = app(PdfService::class)->generateTripItinerary($trip);
        return $pdf->download("trip-" . $trip->trip_id . ".pdf");
    }
}
