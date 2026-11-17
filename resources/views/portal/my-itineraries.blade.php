@extends('portal.layout')
@section('title', 'My Itineraries - HECO Portal')

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
            'running': 'badge-confirmed',
            'completed': 'badge-completed',
            'cancelled': 'badge-cancelled'
        };
        return map[status] || 'badge-open';
    }

    function statusLabel(status) {
        var map = {
            'not_confirmed': 'Open',
            'confirmed': 'Confirmed',
            'running': 'Under way',
            'completed': 'Completed',
            'cancelled': 'Cancelled'
        };
        return map[status] || status;
    }

    // fmt() and fmtCurrency() are global from layout.blade.php

    // Upcoming first, then Past (answers, 2026-11-12, Q7). Upcoming runs
    // soonest first, with undated plans at the end; Past runs latest first.
    function renderTrips(trips) {
        var upcoming = trips.filter(function(t) { return t.when !== 'past'; });
        var past = trips.filter(function(t) { return t.when === 'past'; });
        upcoming.sort(function(a, b) { return (a.start_date || '9999').localeCompare(b.start_date || '9999'); });
        past.sort(function(a, b) { return (b.start_date || '').localeCompare(a.start_date || ''); });
        var html = '';
        if (upcoming.length) html += '<h5 class="mb-3">Upcoming</h5>' + tripCards(upcoming);
        if (past.length) html += '<h5 class="mb-3' + (upcoming.length ? ' mt-4' : '') + '">Past</h5>' + tripCards(past);
        $('#tripsContainer').html(html);
    }

    function tripCards(trips) {
        var html = '<div class="row g-3">';
        trips.forEach(function(trip) {
            var tripName = trip.trip_name || 'Unnamed Trip';
            var regions = trip.regions || [];
            var regionBadges = '';
            regions.forEach(function(r) {
                regionBadges += '<span class="badge trip-region-badge me-1">' + r.name + '</span>';
            });

            var dateInfo = '';
            if (trip.start_date) {
                var sd = new Date(trip.start_date);
                var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                dateInfo = sd.getDate() + ' ' + months[sd.getMonth()] + ' ' + sd.getFullYear();
                if (trip.end_date) {
                    var ed = new Date(trip.end_date);
                    dateInfo += ' — ' + ed.getDate() + ' ' + months[ed.getMonth()] + ' ' + ed.getFullYear();
                }
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
                // What is left to pay on this trip; each is paid on its own page.
                if (trip.balance_due > 0) {
                    html += '<p class="small mb-0 text-danger">Balance due ' + fmtCurrency(trip.balance_due) + '</p>';
                } else if (trip.paid > 0) {
                    html += '<p class="small mb-0 text-success"><i class="bi bi-check-circle"></i> Paid in full</p>';
                }
            }
            html += '</div>';
            // Resume + Erase are draft-only. Once a trip is confirmed/running/completed/cancelled
            // it's a real booking — only View remains; cancellation must go through HCT support.
            var isDraft = trip.status === 'not_confirmed';
            html += '<div class="card-footer bg-transparent d-flex gap-2">';
            html += '<a href="/trip/' + trip.id + '" class="btn btn-sm btn-outline-success' + (isDraft ? '' : ' flex-fill') + '"><i class="bi bi-eye"></i> View</a>';
            // Each trip is paid on its own (answers, 2026-11-12, Q7). A draft
            // pays from Resume, which opens the same page.
            if (!isDraft && trip.balance_due > 0) {
                html += '<a href="/home?trip_id=' + trip.id + '#paymentCard" class="btn btn-sm btn-success flex-fill"><i class="bi bi-shield-lock"></i> Pay Now</a>';
            }
            if (isDraft) {
                html += '<a href="/home?trip_id=' + trip.id + '" class="btn btn-sm btn-success flex-fill"><i class="bi bi-play-fill"></i> Resume</a>';
                // A trip the HECO team planned is not the traveller's to erase.
                if (!trip.hct_built) {
                    html += '<button class="btn btn-sm btn-outline-danger btn-erase-trip" data-trip-id="' + trip.id + '" data-trip-name="' + tripName + '"><i class="bi bi-trash"></i> Erase</button>';
                }
            }
            html += '</div></div></div>';
        });
        html += '</div>';
        return html;
    }

    // Erase trip
    $(document).on('click', '.btn-erase-trip', function() {
        var tripId = $(this).data('trip-id');
        var tripName = $(this).data('trip-name');
        var card = $(this).closest('.col-md-6');
        Swal.fire({
            title: 'Erase this trip?',
            text: 'Are you sure you want to erase "' + tripName + '"? This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, erase it',
            confirmButtonColor: '#b54a4a'
        }).then(function(res) {
            if (!res.isConfirmed) return;
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
});
</script>
@endsection
