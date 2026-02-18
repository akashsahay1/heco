@extends('admin.layout')
@section('title', 'Service Providers - HCT')
@section('content')

@php $regions = \App\Models\Region::where('is_active', 1)->orderBy('name')->get(); @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-people"></i> Service Providers</h5>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm" id="providerTypeFilter" style="width: 140px;">
            <option value="">All Types</option>
            <option value="hrp">HRP</option>
            <option value="hlh">HLH</option>
            <option value="osp">OSP</option>
        </select>
        <select class="form-select form-select-sm" id="regionFilter" style="width: 160px;">
            <option value="">All Regions</option>
            @foreach($regions as $r)
                <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
        </select>
        <select class="form-select form-select-sm" id="statusFilter" style="width: 140px;">
            <option value="">All Status</option>
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
            <option value="rejected">Rejected</option>
        </select>
        <input type="text" class="form-control form-control-sm" id="providerSearch" placeholder="Search..." style="width: 200px;">
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Region</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="providersTable">
                    <tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Provider Detail Modal -->
<div class="modal fade" id="providerModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge"></i> Provider Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="providerModalBody">
                <div class="text-center text-muted py-4">Loading...</div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
var providerModal;

function loadProviders() {
    ajaxPost({
        get_providers: 1,
        provider_type: $('#providerTypeFilter').val(),
        region_id: $('#regionFilter').val(),
        status: $('#statusFilter').val(),
        search: $('#providerSearch').val()
    }, function(resp) {
        var html = '';
        var items = resp.data || [];
        if (!items.length) {
            html = '<tr><td colspan="6" class="text-center text-muted">No providers found</td></tr>';
        }
        items.forEach(function(p) {
            var typeBadge = '';
            if (p.provider_type === 'hrp') typeBadge = '<span class="badge bg-info">HRP</span>';
            else if (p.provider_type === 'hlh') typeBadge = '<span class="badge bg-success">HLH</span>';
            else if (p.provider_type === 'osp') typeBadge = '<span class="badge bg-warning text-dark">OSP</span>';
            else typeBadge = '<span class="badge bg-secondary">' + (p.provider_type || '-') + '</span>';

            var statusBadge = '';
            if (p.status === 'approved') statusBadge = '<span class="badge bg-success">Approved</span>';
            else if (p.status === 'pending') statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
            else if (p.status === 'rejected') statusBadge = '<span class="badge bg-danger">Rejected</span>';
            else statusBadge = '<span class="badge bg-secondary">' + (p.status || '-') + '</span>';

            html += '<tr>';
            html += '<td>' + (p.name || '-') + '</td>';
            html += '<td>' + typeBadge + '</td>';
            html += '<td>' + (p.region ? p.region.name : '-') + '</td>';
            html += '<td>';
            if (p.phone_1) html += '<small><i class="bi bi-telephone"></i> ' + p.phone_1 + '</small><br>';
            if (p.email) html += '<small><i class="bi bi-envelope"></i> ' + p.email + '</small>';
            html += '</td>';
            html += '<td>' + statusBadge + '</td>';
            html += '<td><button class="btn btn-sm btn-outline-primary view-provider" data-id="' + p.id + '" data-userid="' + (p.user_id || '') + '"><i class="bi bi-eye"></i> View</button></td>';
            html += '</tr>';
        });
        $('#providersTable').html(html);
    });
}

function loadProviderDetail(providerId, userId) {
    ajaxPost({ get_providers: 1, provider_type: '', region_id: '', status: '', search: '' }, function(resp) {
        var p = null;
        (resp.data || []).forEach(function(item) {
            if (item.id == providerId) p = item;
        });
        if (!p) {
            $('#providerModalBody').html('<p class="text-muted">Provider not found.</p>');
            return;
        }

        var html = '';

        // Provider Info
        html += '<div class="row mb-4">';
        html += '<div class="col-md-6">';
        html += '<h6 class="border-bottom pb-2"><i class="bi bi-info-circle"></i> Provider Information</h6>';
        html += '<table class="table table-sm table-borderless">';
        html += '<tr><td class="text-muted" style="width:140px;">Name</td><td><strong>' + (p.name || '-') + '</strong></td></tr>';
        html += '<tr><td class="text-muted">Contact Person</td><td>' + (p.contact_person || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Email</td><td>' + (p.email || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Phone 1</td><td>' + (p.phone_1 || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Phone 2</td><td>' + (p.phone_2 || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Address</td><td>' + (p.address || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Region</td><td>' + (p.region ? p.region.name : '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Type</td><td>' + (p.provider_type || '-').toUpperCase() + '</td></tr>';
        html += '<tr><td class="text-muted">Status</td><td>';
        html += '<select class="form-select form-select-sm d-inline-block" style="width:130px;" id="editProviderStatus" data-id="' + p.id + '">';
        html += '<option value="approved"' + (p.status === 'approved' ? ' selected' : '') + '>Approved</option>';
        html += '<option value="pending"' + (p.status === 'pending' ? ' selected' : '') + '>Pending</option>';
        html += '<option value="rejected"' + (p.status === 'rejected' ? ' selected' : '') + '>Rejected</option>';
        html += '</select></td></tr>';
        html += '</table>';
        html += '</div>';

        // Bank Details
        html += '<div class="col-md-6">';
        html += '<h6 class="border-bottom pb-2"><i class="bi bi-bank"></i> Bank Details</h6>';
        html += '<table class="table table-sm table-borderless">';
        html += '<tr><td class="text-muted" style="width:140px;">Bank Name</td><td>' + (p.bank_name || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">IFSC</td><td>' + (p.ifsc_code || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Account Name</td><td>' + (p.account_name || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">Account Number</td><td>' + (p.account_number || '-') + '</td></tr>';
        html += '<tr><td class="text-muted">UPI</td><td>' + (p.upi_id || '-') + '</td></tr>';
        html += '</table>';

        // Services, Accommodation, Vehicle Types
        html += '<h6 class="border-bottom pb-2 mt-3"><i class="bi bi-gear"></i> Capabilities</h6>';
        var services = [];
        try { services = typeof p.services_offered === 'string' ? JSON.parse(p.services_offered) : (p.services_offered || []); } catch(e) {}
        html += '<p class="mb-1"><strong>Services:</strong> ';
        if (services.length) {
            services.forEach(function(s) { html += '<span class="badge bg-outline-secondary border me-1">' + s + '</span>'; });
        } else { html += '<span class="text-muted">None listed</span>'; }
        html += '</p>';

        var accomm = [];
        try { accomm = typeof p.accommodation_categories === 'string' ? JSON.parse(p.accommodation_categories) : (p.accommodation_categories || []); } catch(e) {}
        html += '<p class="mb-1"><strong>Accommodation:</strong> ';
        if (accomm.length) {
            accomm.forEach(function(a) { html += '<span class="badge bg-outline-secondary border me-1">' + a + '</span>'; });
        } else { html += '<span class="text-muted">None listed</span>'; }
        html += '</p>';

        var vehicles = [];
        try { vehicles = typeof p.vehicle_types === 'string' ? JSON.parse(p.vehicle_types) : (p.vehicle_types || []); } catch(e) {}
        html += '<p class="mb-1"><strong>Vehicle Types:</strong> ';
        if (vehicles.length) {
            vehicles.forEach(function(v) { html += '<span class="badge bg-outline-secondary border me-1">' + v + '</span>'; });
        } else { html += '<span class="text-muted">None listed</span>'; }
        html += '</p>';

        html += '</div>';
        html += '</div>';

        // Notes
        html += '<div class="mb-3">';
        html += '<label class="form-label small text-muted">Internal Notes</label>';
        html += '<textarea class="form-control form-control-sm" id="editProviderNotes" rows="2">' + (p.notes || '') + '</textarea>';
        html += '<button class="btn btn-sm btn-success mt-2" id="saveProviderBtn" data-id="' + p.id + '"><i class="bi bi-check-lg"></i> Save Changes</button>';
        html += '</div>';

        // Trip History
        html += '<h6 class="border-bottom pb-2"><i class="bi bi-luggage"></i> Trip History</h6>';
        html += '<div id="providerTrips"><p class="text-muted small">Loading trips...</p></div>';

        // Payment History
        html += '<h6 class="border-bottom pb-2 mt-3"><i class="bi bi-cash-stack"></i> Payment History</h6>';
        html += '<div id="providerPayments"><p class="text-muted small">Loading payments...</p></div>';

        $('#providerModalBody').html(html);

        // Load trip history
        ajaxPost({ get_provider_trips: 1, provider_id: providerId }, function(tripResp) {
            var trips = tripResp.trips || [];
            var th = '';
            if (!trips.length) {
                th = '<p class="text-muted small">No trips found for this provider.</p>';
            } else {
                th += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                th += '<thead class="table-light"><tr><th>Trip ID</th><th>Status</th><th>Service Type</th></tr></thead><tbody>';
                trips.forEach(function(t) {
                    var trip = t.trip || t;
                    var tripId = trip.trip_id || trip.id || '-';
                    var statusClass = trip.status === 'confirmed' ? 'success' : (trip.status === 'cancelled' ? 'danger' : 'secondary');
                    th += '<tr>';
                    th += '<td><a href="/trip-manager/' + tripId + '" target="_blank">' + tripId + '</a></td>';
                    th += '<td><span class="badge bg-' + statusClass + '">' + (trip.status || '-') + '</span></td>';
                    th += '<td>' + (t.service_type || '-') + '</td>';
                    th += '</tr>';
                });
                th += '</tbody></table></div>';
            }
            $('#providerTrips').html(th);
        });

        // Load payment history
        ajaxPost({ get_provider_payment_history: 1, provider_id: providerId }, function(payResp) {
            var payments = payResp.payments || [];
            var ph = '';
            if (!payments.length) {
                ph = '<p class="text-muted small">No payments found for this provider.</p>';
            } else {
                ph += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                ph += '<thead class="table-light"><tr><th>Trip ID</th><th>Amount Due</th><th>Amount Paid</th><th>Balance</th></tr></thead><tbody>';
                var totalDue = 0, totalPaid = 0, totalBalance = 0;
                payments.forEach(function(pay) {
                    var trip = pay.trip || {};
                    var tripId = trip.trip_id || '-';
                    var due = parseFloat(pay.amount_due) || 0;
                    var paid = parseFloat(pay.amount_paid) || 0;
                    var bal = parseFloat(pay.balance) || 0;
                    totalDue += due;
                    totalPaid += paid;
                    totalBalance += bal;
                    ph += '<tr>';
                    ph += '<td><a href="/trip-manager/' + tripId + '" target="_blank">' + tripId + '</a></td>';
                    ph += '<td class="text-end">' + due.toLocaleString('en-IN') + '</td>';
                    ph += '<td class="text-end">' + paid.toLocaleString('en-IN') + '</td>';
                    ph += '<td class="text-end">' + (bal > 0 ? '<span class="text-danger">' + bal.toLocaleString('en-IN') + '</span>' : bal.toLocaleString('en-IN')) + '</td>';
                    ph += '</tr>';
                });
                ph += '<tr class="table-light fw-bold">';
                ph += '<td>Total</td>';
                ph += '<td class="text-end">' + totalDue.toLocaleString('en-IN') + '</td>';
                ph += '<td class="text-end">' + totalPaid.toLocaleString('en-IN') + '</td>';
                ph += '<td class="text-end">' + (totalBalance > 0 ? '<span class="text-danger">' + totalBalance.toLocaleString('en-IN') + '</span>' : totalBalance.toLocaleString('en-IN')) + '</td>';
                ph += '</tr>';
                ph += '</tbody></table></div>';
            }
            $('#providerPayments').html(ph);
        });
    });
}

$(function() {
    providerModal = new bootstrap.Modal('#providerModal');
    loadProviders();
});

$('#providerTypeFilter, #regionFilter, #statusFilter').on('change', function() { loadProviders(); });
$('#providerSearch').on('keyup', function() { loadProviders(); });

$(document).on('click', '.view-provider', function() {
    var providerId = $(this).data('id');
    var userId = $(this).data('userid');
    $('#providerModalBody').html('<div class="text-center text-muted py-4">Loading...</div>');
    providerModal.show();
    loadProviderDetail(providerId, userId);
});

$(document).on('click', '#saveProviderBtn', function() {
    var id = $(this).data('id');
    ajaxPost({
        edit_provider: 1,
        provider_id: id,
        status: $('#editProviderStatus').val(),
        notes: $('#editProviderNotes').val()
    }, function(resp) {
        providerModal.hide();
        loadProviders();
    });
});
</script>
@endsection
