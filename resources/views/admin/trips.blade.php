@extends('admin.layout')
@section('title', 'Trips - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-luggage"></i> Trips</h5>
    <div class="d-flex gap-2 flex-wrap">
        <div class="heco-filter-md">
            <select class="form-select form-select-sm custom-select" id="statusFilter">
                <option value="">All Statuses</option>
                <option value="not_confirmed">Not Confirmed</option>
                <option value="confirmed">Confirmed</option>
                <option value="running">Running</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <input type="text" class="form-control form-control-sm heco-filter-date" id="dateFromDisplay" readonly autocomplete="off">
        <input type="hidden" id="dateFrom">
        <input type="text" class="form-control form-control-sm heco-filter-date" id="dateToDisplay" readonly autocomplete="off">
        <input type="hidden" id="dateTo">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearTripFilters" title="Clear filters"><i class="bi bi-x-circle"></i></button>
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
function tripStatusBadgeClass(status) {
    switch (status) {
        case 'not_confirmed': return 'warning text-dark';
        case 'confirmed': return 'success';
        case 'running': return 'primary';
        case 'completed': return 'secondary';
        case 'cancelled': return 'danger';
        default: return 'light text-dark';
    }
}

function formatTripStatus(status) {
    return status ? status.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); }) : '';
}

function isoFromDate(d) {
    var pad = function(n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
}

function loadTrips() {
    var params = { get_upcoming_trips: 1 };
    var status = jQuery('#statusFilter').val();
    var dateFrom = jQuery('#dateFrom').val();
    var dateTo = jQuery('#dateTo').val();
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
            html += '<td>' + (t.user ? (t.user.full_name || t.user.email) : '-') + '</td>';
            html += '<td>';
            html += '<select class="form-select form-select-sm status-change heco-trip-status" data-trip-id="' + t.id + '">';
            ['not_confirmed', 'confirmed', 'running', 'completed', 'cancelled'].forEach(function(s) {
                html += '<option value="' + s + '"' + (t.status === s ? ' selected' : '') + '>' + formatTripStatus(s) + '</option>';
            });
            html += '</select>';
            html += '</td>';
            html += '<td><small>' + (t.start_date ? t.start_date.substring(0, 10) : '-') + ' &mdash; ' + (t.end_date ? t.end_date.substring(0, 10) : '-') + '</small></td>';
            html += '<td><small>' + (regions || '-') + '</small></td>';
            html += '<td>' + (t.final_price ? '₹' + Number(t.final_price).toLocaleString('en-IN') : '-') + '</td>';
            html += '<td><a href="/trip-manager/' + t.id + '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>';
            html += '</tr>';
        });
        jQuery('#tripsTable').html(html);
        if (window.buildCustomDropdown) {
            jQuery('#tripsTable .heco-trip-status').each(function() { buildCustomDropdown(this, { searchable: false }); });
        }
    });
}

jQuery(function() {
    if (window.buildCustomDropdown) {
        buildCustomDropdown(jQuery('#statusFilter')[0]);
    }
    jQuery('#statusFilter').on('change', function() { loadTrips(); });

    new AirDatepicker('#dateFromDisplay', {
        locale: window.airDatepickerEn,
        dateFormat: 'dd-MM-yyyy',
        autoClose: true,
        position: 'bottom left',
        onSelect: function(o) {
            jQuery('#dateFrom').val(o.date ? isoFromDate(o.date) : '');
            loadTrips();
        }
    });
    new AirDatepicker('#dateToDisplay', {
        locale: window.airDatepickerEn,
        dateFormat: 'dd-MM-yyyy',
        autoClose: true,
        position: 'bottom left',
        onSelect: function(o) {
            jQuery('#dateTo').val(o.date ? isoFromDate(o.date) : '');
            loadTrips();
        }
    });

    jQuery('#clearTripFilters').on('click', function() {
        jQuery('#statusFilter').val('');
        if (window.buildCustomDropdown) buildCustomDropdown(jQuery('#statusFilter')[0]);
        jQuery('#dateFromDisplay').val('');
        jQuery('#dateToDisplay').val('');
        jQuery('#dateFrom').val('');
        jQuery('#dateTo').val('');
        loadTrips();
    });

    loadTrips();
});

jQuery(document).on('change', '.status-change', function() {
    var sel = jQuery(this);
    var tripId = sel.data('trip-id');
    var newStatus = sel.val();
    Swal.fire({
        text: 'Change trip status to "' + formatTripStatus(newStatus) + '"?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2d6a4f',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, change it'
    }).then(function(result) {
        if (!result.isConfirmed) { loadTrips(); return; }
        ajaxPost({ update_trip_status: 1, trip_id: tripId, status: newStatus }, function() {
            loadTrips();
        }, function(xhr) {
            var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Could not update status') : 'Could not update status';
            showAlert(msg, 'danger');
            loadTrips();
        });
    });
});
</script>
@endsection
