@extends('admin.layout')
@section('title', 'Trips - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-luggage"></i> Trips</h5>
    <div class="d-flex gap-2 flex-wrap">
        <select class="form-select form-select-sm" id="statusFilter" style="width: 160px;">
            <option value="">All Statuses</option>
            <option value="not_confirmed">Not Confirmed</option>
            <option value="confirmed">Confirmed</option>
            <option value="running">Running</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <input type="date" class="form-control form-control-sm" id="dateFrom" style="width: 150px;" placeholder="From">
        <input type="date" class="form-control form-control-sm" id="dateTo" style="width: 150px;" placeholder="To">
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Trip ID</th>
                        <th>Traveller</th>
                        <th>Status</th>
                        <th>Dates</th>
                        <th>Regions</th>
                        <th>Total Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tripsTable">
                    <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
function statusBadgeClass(status) {
    switch (status) {
        case 'not_confirmed': return 'warning text-dark';
        case 'confirmed': return 'success';
        case 'running': return 'primary';
        case 'completed': return 'secondary';
        case 'cancelled': return 'danger';
        default: return 'light text-dark';
    }
}

function formatStatus(status) {
    return status ? status.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); }) : '';
}

function loadTrips() {
    var params = { get_upcoming_trips: 1 };
    var status = $('#statusFilter').val();
    var dateFrom = $('#dateFrom').val();
    var dateTo = $('#dateTo').val();
    if (status) params.status = status;
    if (dateFrom) params.date_from = dateFrom;
    if (dateTo) params.date_to = dateTo;

    ajaxPost(params, function(resp) {
        var html = '';
        var items = resp.data || [];
        if (!items.length) {
            html = '<tr><td colspan="7" class="text-center text-muted">No trips found</td></tr>';
        }
        items.forEach(function(t) {
            var regions = '';
            if (t.regions && t.regions.length) {
                regions = t.regions.map(function(r) { return r.name; }).join(', ');
            }
            html += '<tr>';
            html += '<td><a href="/trip-manager/' + t.id + '" target="_blank" class="fw-semibold">' + (t.trip_id || t.id) + '</a></td>';
            html += '<td>' + (t.user ? t.user.full_name || t.user.email : '-') + '</td>';
            html += '<td>';
            html += '<select class="form-select form-select-sm d-inline-block status-change" data-trip-id="' + t.id + '" style="width: 140px; font-size: 0.75rem;">';
            ['not_confirmed', 'confirmed', 'running', 'completed', 'cancelled'].forEach(function(s) {
                html += '<option value="' + s + '"' + (t.status === s ? ' selected' : '') + '>' + formatStatus(s) + '</option>';
            });
            html += '</select>';
            html += '</td>';
            html += '<td><small>' + (t.start_date ? t.start_date.substring(0, 10) : '-') + ' &mdash; ' + (t.end_date ? t.end_date.substring(0, 10) : '-') + '</small></td>';
            html += '<td><small>' + (regions || '-') + '</small></td>';
            html += '<td>' + (t.final_price ? '₹' + Number(t.final_price).toLocaleString() : '-') + '</td>';
            html += '<td><a href="/trip-manager/' + t.id + '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>';
            html += '</tr>';
        });
        $('#tripsTable').html(html);
    });
}

$(function() { loadTrips(); });

$('#statusFilter').on('change', function() { loadTrips(); });
$('#dateFrom, #dateTo').on('change', function() { loadTrips(); });

$(document).on('change', '.status-change', function() {
    var sel = $(this);
    var tripId = sel.data('trip-id');
    var newStatus = sel.val();
    if (!confirm('Change trip status to "' + formatStatus(newStatus) + '"?')) {
        loadTrips();
        return;
    }
    ajaxPost({ update_trip_status: 1, trip_id: tripId, status: newStatus }, function() {
        loadTrips();
    });
});
</script>
@endsection
