@extends('admin.layout')
@section('title', 'Travelers - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-person-lines-fill"></i> Travelers</h5>
    <div class="d-flex gap-2">
        <input type="text" class="form-control form-control-sm" id="travelerSearch" placeholder="Search by name, email or phone..." style="width: 300px;">
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th class="text-center">Trips</th>
                        <th class="text-end">Total Spent</th>
                        <th>Last Trip</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="travelersTable">
                    <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Traveler Detail Modal -->
<div class="modal fade" id="travelerModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge"></i> Traveler Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="travelerModalBody">
                <div class="text-center text-muted py-4">Loading...</div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
var travelerModal;
var travelersList = [];

function loadTravelers() {
    ajaxPost({
        get_travelers_list: 1,
        search: $('#travelerSearch').val()
    }, function(resp) {
        var html = '';
        var items = resp.data || [];
        travelersList = items;
        if (!items.length) {
            html = '<tr><td colspan="7" class="text-center text-muted">No travelers found</td></tr>';
        }
        items.forEach(function(t) {
            html += '<tr>';
            html += '<td>' + (t.full_name || '-') + '</td>';
            html += '<td><small>' + (t.email || '-') + '</small></td>';
            html += '<td><small>' + (t.mobile || '-') + '</small></td>';
            html += '<td class="text-center"><span class="badge bg-primary">' + (t.trips_count || 0) + '</span></td>';
            html += '<td class="text-end"><small>' + (t.total_spent ? Number(t.total_spent).toLocaleString('en-IN') : '0') + '</small></td>';
            html += '<td><small>' + (t.last_trip_date ? t.last_trip_date.substring(0, 10) : '-') + '</small></td>';
            html += '<td><button class="btn btn-sm btn-outline-primary view-traveler" data-id="' + t.id + '"><i class="bi bi-eye"></i> View</button></td>';
            html += '</tr>';
        });
        $('#travelersTable').html(html);
    });
}

function loadTravelerDetail(userId) {
    var traveler = null;
    travelersList.forEach(function(t) {
        if (t.id == userId) traveler = t;
    });

    var html = '';

    // Traveler Info
    html += '<div class="row mb-4">';
    html += '<div class="col-md-6">';
    html += '<h6 class="border-bottom pb-2"><i class="bi bi-person"></i> Traveler Information</h6>';
    html += '<table class="table table-sm table-borderless">';
    if (traveler) {
        html += '<tr><td class="text-muted" style="width:140px;">Name</td><td><strong>' + (traveler.full_name || '-') + '</strong></td></tr>';
        html += '<tr><td class="text-muted">Email</td><td>' + (traveler.email || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Phone</td><td>' + (traveler.mobile || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Address</td><td>' + (traveler.address || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Auth Type</td><td>' + (traveler.auth_type || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Registered</td><td>' + (traveler.created_at ? traveler.created_at.substring(0, 10) : '-') + '</td></tr>';
    }
    html += '</table>';
    html += '</div>';

    // Summary Stats
    html += '<div class="col-md-6">';
    html += '<h6 class="border-bottom pb-2"><i class="bi bi-bar-chart"></i> Summary</h6>';
    html += '<div class="row g-2">';
    html += '<div class="col-6"><div class="border rounded p-2 text-center"><div class="fs-4 fw-bold text-primary">' + (traveler ? traveler.trips_count || 0 : 0) + '</div><small class="text-muted">Total Trips</small></div></div>';
    html += '<div class="col-6"><div class="border rounded p-2 text-center"><div class="fs-4 fw-bold text-success">' + (traveler && traveler.total_spent ? Number(traveler.total_spent).toLocaleString('en-IN') : '0') + '</div><small class="text-muted">Total Spent</small></div></div>';
    html += '</div>';
    html += '</div>';
    html += '</div>';

    // Trip History
    html += '<h6 class="border-bottom pb-2"><i class="bi bi-luggage"></i> Trip History</h6>';
    html += '<div id="travelerTrips"><p class="text-muted small">Loading trips...</p></div>';

    // Payment History
    html += '<h6 class="border-bottom pb-2 mt-3"><i class="bi bi-cash-stack"></i> Payment History</h6>';
    html += '<div id="travelerPayments"><p class="text-muted small">Loading payments...</p></div>';

    $('#travelerModalBody').html(html);

    // Load trip history
    ajaxPost({ get_traveler_trips: 1, user_id: userId }, function(tripResp) {
        var trips = tripResp.trips || [];
        var th = '';
        if (!trips.length) {
            th = '<p class="text-muted small">No trips found for this traveler.</p>';
        } else {
            th += '<div class="table-responsive"><table class="table table-sm table-bordered">';
            th += '<thead class="table-light"><tr><th>Trip ID</th><th>Start Date</th><th>End Date</th><th>Status</th><th class="text-end">Final Price</th></tr></thead><tbody>';
            trips.forEach(function(t) {
                var statusClass = 'secondary';
                if (t.status === 'confirmed') statusClass = 'success';
                else if (t.status === 'completed') statusClass = 'primary';
                else if (t.status === 'cancelled') statusClass = 'danger';
                else if (t.status === 'ongoing') statusClass = 'info';
                th += '<tr>';
                th += '<td><a href="/trip-manager/' + (t.trip_id || t.id) + '" target="_blank">' + (t.trip_id || t.id || '-') + '</a></td>';
                th += '<td><small>' + (t.start_date ? t.start_date.substring(0, 10) : '-') + '</small></td>';
                th += '<td><small>' + (t.end_date ? t.end_date.substring(0, 10) : '-') + '</small></td>';
                th += '<td><span class="badge bg-' + statusClass + '">' + (t.status || '-') + '</span></td>';
                th += '<td class="text-end">' + (t.final_price ? Number(t.final_price).toLocaleString('en-IN') : '-') + '</td>';
                th += '</tr>';
            });
            th += '</tbody></table></div>';
        }
        $('#travelerTrips').html(th);
    });

    // Load payment history
    ajaxPost({ get_traveler_payment_history: 1, user_id: userId }, function(payResp) {
        var payments = payResp.payments || [];
        var ph = '';
        if (!payments.length) {
            ph = '<p class="text-muted small">No payments found for this traveler.</p>';
        } else {
            ph += '<div class="table-responsive"><table class="table table-sm table-bordered">';
            ph += '<thead class="table-light"><tr><th>Trip ID</th><th class="text-end">Amount</th><th>Date</th><th>Mode</th></tr></thead><tbody>';
            var totalPaid = 0;
            payments.forEach(function(pay) {
                var amount = parseFloat(pay.amount) || 0;
                totalPaid += amount;
                ph += '<tr>';
                ph += '<td><a href="/trip-manager/' + (pay.trip_id || '-') + '" target="_blank">' + (pay.trip_id || '-') + '</a></td>';
                ph += '<td class="text-end">' + amount.toLocaleString('en-IN') + '</td>';
                ph += '<td><small>' + (pay.date ? pay.date.substring(0, 10) : (pay.created_at ? pay.created_at.substring(0, 10) : '-')) + '</small></td>';
                ph += '<td>' + (pay.mode || pay.payment_mode || '-') + '</td>';
                ph += '</tr>';
            });
            ph += '<tr class="table-light fw-bold">';
            ph += '<td>Total</td>';
            ph += '<td class="text-end">' + totalPaid.toLocaleString('en-IN') + '</td>';
            ph += '<td colspan="2"></td>';
            ph += '</tr>';
            ph += '</tbody></table></div>';
        }
        $('#travelerPayments').html(ph);
    });
}

$(function() {
    travelerModal = new bootstrap.Modal('#travelerModal');
    loadTravelers();
});

$('#travelerSearch').on('keyup', function() { loadTravelers(); });

$(document).on('click', '.view-traveler', function() {
    var userId = $(this).data('id');
    $('#travelerModalBody').html('<div class="text-center text-muted py-4">Loading...</div>');
    travelerModal.show();
    loadTravelerDetail(userId);
});
</script>
@endsection
