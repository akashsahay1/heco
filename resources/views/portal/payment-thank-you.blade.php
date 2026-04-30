@extends('portal.layout')
@section('title', 'Payment Successful - HECO Portal')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body text-center p-5">
                    <div class="mx-auto mb-4 d-flex align-items-center justify-content-center"
                         style="width:88px; height:88px; border-radius:50%; background: linear-gradient(135deg, var(--heco-primary-500, #22c55e) 0%, var(--heco-primary-700, #15803d) 100%); color:#fff;">
                        <i class="bi bi-check-lg" style="font-size:48px; line-height:1;"></i>
                    </div>

                    <h2 class="fw-bold mb-2" style="color: var(--heco-primary-800, #14532d);">Payment Successful</h2>
                    <p class="text-muted mb-4" style="font-size: 0.95rem;">
                        Thank you{{ optional($trip->user)->full_name ? ', ' . $trip->user->full_name : '' }} — your payment has been received and your booking is confirmed.
                    </p>

                    <div class="border rounded-3 p-3 mb-4 text-start" style="background: #f8fafc;">
                        <div class="row small">
                            <div class="col-6 mb-2"><span class="text-muted">Trip ID:</span> <strong>{{ $trip->trip_id }}</strong></div>
                            <div class="col-6 mb-2"><span class="text-muted">Status:</span> <strong class="text-capitalize">{{ str_replace('_', ' ', $trip->status) }}</strong></div>
                            @if($trip->start_date)
                                <div class="col-6 mb-2"><span class="text-muted">Start:</span> <strong>{{ \Carbon\Carbon::parse($trip->start_date)->format('d M Y') }}</strong></div>
                            @endif
                            @if($trip->end_date)
                                <div class="col-6 mb-2"><span class="text-muted">End:</span> <strong>{{ \Carbon\Carbon::parse($trip->end_date)->format('d M Y') }}</strong></div>
                            @endif
                            @if($trip->tripRegions->isNotEmpty())
                                <div class="col-12"><span class="text-muted">Regions:</span> <strong>{{ $trip->tripRegions->pluck('region.name')->filter()->implode(', ') }}</strong></div>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                        <a href="{{ route('trip.detail', $trip->id) }}" class="btn btn-success btn-lg px-4" style="background: linear-gradient(135deg, var(--heco-primary-600, #16a34a) 0%, var(--heco-primary-700, #15803d) 100%); border: 0;">
                            <i class="bi bi-journal-text me-2"></i>View Itinerary
                        </a>
                        <a href="{{ route('my-itineraries') }}" class="btn btn-outline-secondary btn-lg px-4">
                            <i class="bi bi-grid me-2"></i>My Itineraries
                        </a>
                    </div>

                    <p class="text-muted small mt-4 mb-0">
                        A confirmation email is on its way. Need help? <a href="/contact" class="text-success">Contact our team</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
