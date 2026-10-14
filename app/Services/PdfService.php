<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\PdfTemplate;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    public function generateTripItinerary(Trip $trip, bool $showInternal = false): \Barryvdh\DomPDF\PDF
    {
        $trip->load([
            'user', 'tripDays.experiences.experience', 'tripDays.services.serviceProvider',
            'tripRegions.region', 'travellerPayments',
        ]);

        $template = PdfTemplate::where('key', 'trip_itinerary')->where('is_active', true)->first();

        // Heads a per-person service line is multiplied by, so the PDF shows
        // the same working the Trip Manager and the calculator use.
        $peopleFactor = app(CostCalculatorService::class)->partyBreakdown(
            (int) $trip->adults, (int) ($trip->children ?: 0), (int) ($trip->infants ?: 0),
        )['people_factor'];

        $pdf = Pdf::loadView('admin.pdf.trip-itinerary', [
            'trip' => $trip,
            'template' => $template,
            // Only HCT staff see internal HRP/HCT margins; false = traveller-safe.
            'showInternal' => $showInternal,
            'peopleFactor' => $peopleFactor,
        ]);

        if ($template) {
            $pdf->setPaper($template->paper_size, $template->orientation);
        }

        return $pdf;
    }
}
