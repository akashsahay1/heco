@extends('portal.layout')
@section('title', 'My Itineraries - HECO Portal')

@section('css')
<style>
    .trip-card { transition: transform 0.2s, box-shadow 0.2s; border: none; border-left: 4px solid var(--heco-green); }
    .trip-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
    .trip-status { font-size: 0.75rem; }
    .trip-status.badge-open { background-color: #0d6efd; }
    .trip-status.badge-confirmed { background-color: var(--heco-green); }
    .trip-status.badge-completed { background-color: #6c757d; }
    .trip-status.badge-cancelled { background-color: #dc3545; }
    .trip-regions .badge { font-size: 0.7rem; }
    .empty-state { padding: 80px 20px; }
    .empty-state i { font-size: 5rem; color: #dee2e6; }
</style>
@endsection

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-journal-richtext text-success"></i> Your Itineraries</h3>
            <p class="text-muted mb-0">View, resume, or manage your saved trip plans.</p>
        </div>
        <a href="/home" class="btn btn-success">
            <i class="bi bi-plus-lg"></i> New Journey
        </a>
    </div>

    <div id="tripsContainer">
        <div class="text-center py-5">
            <div class="spinner-border text-success" role="status"></div>
            <p class="text-muted mt-2">Loading your itineraries...</p>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
$(function() {
    loadTrips();

    function loadTrips() {
        ajaxPost({ get_user_trips: 1 }, function(resp) {
            var trips = resp.trips || [];
            if (trips.length === 0) {
                renderEmptyState();
                return;
            }
            renderTrips(trips);
        });
    }

    function renderEmptyState() {
        var html = '<div class="text-center empty-state">';
        html += '<i class="bi bi-map"></i>';
        html += '<h4 class="text-muted mt-3">No itineraries yet</h4>';
        html += '<p class="text-muted">Start exploring the Himalayas and build your first regenerative travel plan.</p>';
        html += '<a href="/home" class="btn btn-success btn-lg"><i class="bi bi-compass"></i> Start Exploring</a>';
        html += '</div>';
        $('#tripsContainer').html(html);
    }

    function statusBadgeClass(status) {
        var map = {
            'not_confirmed': 'badge-open',
            'confirmed': 'badge-confirmed',
            'completed': 'badge-completed',
            'cancelled': 'badge-cancelled'
        };
        return map[status] || 'badge-open';
    }

    function statusLabel(status) {
        var map = {
            'not_confirmed': 'Open',
            'confirmed': 'Confirmed',
            'completed': 'Completed',
            'cancelled': 'Cancelled'
        };
        return map[status] || status;
    }

    // fmt() and fmtCurrency() are global from layout.blade.php

    function renderTrips(trips) {
        var html = '<div class="row g-3">';
        trips.forEach(function(trip) {
            var tripName = trip.trip_name || 'Unnamed Trip';
            var regions = trip.regions || [];
            var regionBadges = '';
            regions.forEach(function(r) {
                regionBadges += '<span class="badge bg-success bg-opacity-25 text-success me-1">' + r.name + '</span>';
            });

            var dateInfo = '';
            if (trip.start_date) {
                dateInfo = trip.start_date;
                if (trip.end_date) dateInfo += ' to ' + trip.end_date;
            }

            html += '<div class="col-md-6 col-lg-4">';
            html += '<div class="card trip-card h-100 shadow-sm">';
            html += '<div class="card-body">';
            html += '<div class="d-flex justify-content-between align-items-start mb-2">';
            html += '<h6 class="card-title mb-0">' + tripName + '</h6>';
            html += '<span class="badge trip-status ' + statusBadgeClass(trip.status) + '">' + statusLabel(trip.status) + '</span>';
            html += '</div>';
            html += '<p class="text-muted small mb-2"><i class="bi bi-hash"></i> ' + (trip.trip_id || 'ID: ' + trip.id) + '</p>';
            if (dateInfo) {
                html += '<p class="small mb-2"><i class="bi bi-calendar3"></i> ' + dateInfo + '</p>';
            }
            if (regionBadges) {
                html += '<div class="trip-regions mb-2">' + regionBadges + '</div>';
            }
            if (trip.final_price > 0) {
                html += '<p class="mb-0"><strong class="text-success">' + fmtCurrency(trip.final_price) + '</strong></p>';
            }
            html += '</div>';
            html += '<div class="card-footer bg-transparent d-flex gap-2">';
            html += '<a href="/home?trip_id=' + trip.id + '" class="btn btn-sm btn-success flex-fill"><i class="bi bi-play-fill"></i> Resume</a>';
            html += '<button class="btn btn-sm btn-outline-danger btn-erase-trip" data-trip-id="' + trip.id + '" data-trip-name="' + tripName + '"><i class="bi bi-trash"></i> Erase</button>';
            html += '</div></div></div>';
        });
        html += '</div>';
        $('#tripsContainer').html(html);
    }

    // Erase trip
    $(document).on('click', '.btn-erase-trip', function() {
        var tripId = $(this).data('trip-id');
        var tripName = $(this).data('trip-name');
        if (!confirm('Are you sure you want to erase "' + tripName + '"? This cannot be undone.')) return;

        var card = $(this).closest('.col-md-6');
        ajaxPost({ erase_trip: 1, trip_id: tripId }, function(resp) {
            card.fadeOut(300, function() {
                $(this).remove();
                if ($('#tripsContainer .trip-card').length === 0) {
                    renderEmptyState();
                }
            });
            showAlert('Trip erased successfully.', 'success');
        });
    });
});
</script>
@endsection
