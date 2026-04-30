@extends('admin.layout')
@section('title', 'Provider — ' . $provider->name . ' - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('hct.providers') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Back to Providers
        </a>
        <h5 class="mb-0 mt-1"><i class="bi bi-person-badge"></i> {{ $provider->name ?: '-' }}</h5>
    </div>
    <div class="d-flex align-items-center gap-2">
        @php
            $statusClass = [
                'approved' => 'bg-success',
                'pending' => 'bg-warning text-dark',
                'rejected' => 'bg-danger',
            ][$provider->status] ?? 'bg-secondary';
            $typeClass = [
                'hrp' => 'bg-info',
                'hlh' => 'bg-success',
                'osp' => 'bg-warning text-dark',
            ][$provider->provider_type] ?? 'bg-secondary';
        @endphp
        <span class="badge {{ $typeClass }}">{{ strtoupper($provider->provider_type ?: '-') }}</span>
        <span class="badge {{ $statusClass }}">{{ ucfirst($provider->status ?: '-') }}</span>
        <a href="{{ route('hct.providers.edit', $provider->id) }}" class="btn btn-sm btn-success ms-2">
            <i class="bi bi-pencil-square"></i> Edit
        </a>
    </div>
</div>

@if($provider->last_updated_by)
    <div class="alert alert-light border small py-2 mb-3 d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-clock-history"></i>
            Last updated by <strong>{{ $provider->lastUpdatedBy->full_name ?? $provider->lastUpdatedBy->email ?? 'unknown' }}</strong>
            <span class="badge bg-secondary ms-1">{{ ucfirst($provider->last_updated_by_role ?: '-') }}</span>
            on {{ $provider->updated_at?->format('d M Y, h:i A') }}
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="border-bottom pb-2"><i class="bi bi-info-circle"></i> Provider Information</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" style="width:160px;">Name</td><td><strong>{{ $provider->name ?: '-' }}</strong></td></tr>
                    <tr><td class="text-muted">Contact Person</td><td>{{ $provider->contact_person ?: '-' }}</td></tr>
                    <tr><td class="text-muted">Email</td><td>{{ $provider->email ?: '-' }}</td></tr>
                    <tr><td class="text-muted">Phone 1</td><td>{{ $provider->phone_1 ?: '-' }}</td></tr>
                    <tr><td class="text-muted">Phone 2</td><td>{{ $provider->phone_2 ?: '-' }}</td></tr>
                    <tr><td class="text-muted">Address</td><td>{{ $provider->address ?: '-' }}</td></tr>
                    <tr><td class="text-muted">Region</td><td>{{ optional($provider->region)->name ?: '-' }}</td></tr>
                    <tr><td class="text-muted">Linked User</td><td>{{ optional($provider->user)->email ?: '-' }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="border-bottom pb-2"><i class="bi bi-bank"></i> Bank Details</h6>
                <table class="table table-sm table-borderless mb-3">
                    <tr><td class="text-muted" style="width:160px;">Bank Name</td><td>{{ $provider->bank_name ?: '-' }}</td></tr>
                    <tr><td class="text-muted">IFSC</td><td>{{ $provider->bank_ifsc ?: '-' }}</td></tr>
                    <tr><td class="text-muted">Account Name</td><td>{{ $provider->bank_account_name ?: '-' }}</td></tr>
                    <tr><td class="text-muted">Account Number</td><td>{{ $provider->bank_account_number ?: '-' }}</td></tr>
                    <tr><td class="text-muted">UPI</td><td>{{ $provider->upi ?: '-' }}</td></tr>
                </table>

                <h6 class="border-bottom pb-2"><i class="bi bi-gear"></i> Capabilities</h6>
                <div class="mb-2"><strong class="small">Services:</strong>
                    @forelse(($provider->services_offered ?: []) as $s)
                        <span class="badge bg-light text-dark border me-1">{{ $s }}</span>
                    @empty
                        <span class="text-muted small">None listed</span>
                    @endforelse
                </div>
                <div class="mb-2"><strong class="small">Accommodation:</strong>
                    @forelse(($provider->accommodation_categories ?: []) as $a)
                        <span class="badge bg-light text-dark border me-1">{{ $a }}</span>
                    @empty
                        <span class="text-muted small">None listed</span>
                    @endforelse
                </div>
                <div class="mb-2"><strong class="small">Vehicle Types:</strong>
                    @forelse(($provider->vehicle_types ?: []) as $v)
                        <span class="badge bg-light text-dark border me-1">{{ $v }}</span>
                    @empty
                        <span class="text-muted small">None listed</span>
                    @endforelse
                </div>
                <div><strong class="small">Guide Types:</strong>
                    @forelse(($provider->guide_types ?: []) as $g)
                        <span class="badge bg-light text-dark border me-1">{{ $g }}</span>
                    @empty
                        <span class="text-muted small">None listed</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="border-bottom pb-2"><i class="bi bi-pencil-square"></i> Admin Controls</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Status</label>
                        <select class="form-select form-select-sm" id="editProviderStatus" data-id="{{ $provider->id }}">
                            <option value="approved" @selected($provider->status === 'approved')>Approved</option>
                            <option value="pending" @selected($provider->status === 'pending')>Pending</option>
                            <option value="rejected" @selected($provider->status === 'rejected')>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label small text-muted">Internal Notes</label>
                        <textarea class="form-control form-control-sm" id="editProviderNotes" rows="2">{{ $provider->notes }}</textarea>
                    </div>
                </div>
                <button class="btn btn-sm btn-success mt-3" id="saveProviderBtn" data-id="{{ $provider->id }}">
                    <i class="bi bi-check-lg"></i> Save Changes
                </button>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="border-bottom pb-2"><i class="bi bi-luggage"></i> Trip History</h6>
                <div id="providerTrips"><p class="text-muted small mb-0">Loading trips...</p></div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="border-bottom pb-2"><i class="bi bi-cash-stack"></i> Payment History</h6>
                <div id="providerPayments"><p class="text-muted small mb-0">Loading payments...</p></div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="border-bottom pb-2"><i class="bi bi-calendar3"></i> Availability Calendar</h6>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <button class="btn btn-sm btn-outline-secondary" id="adminCalPrev"><i class="bi bi-chevron-left"></i></button>
                    <span class="small fw-bold" id="adminCalMonthLabel"></span>
                    <button class="btn btn-sm btn-outline-secondary" id="adminCalNext"><i class="bi bi-chevron-right"></i></button>
                    <span class="ms-3 small"><span class="badge bg-success">&nbsp;&nbsp;</span> Available</span>
                    <span class="small"><span class="badge bg-danger">&nbsp;&nbsp;</span> Booked</span>
                    <span class="small"><span class="badge bg-secondary">&nbsp;&nbsp;</span> Blocked</span>
                </div>
                <div id="adminCalendarGrid" class="mb-2"></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-danger" id="adminBtnBlock" disabled>Block Selected</button>
                    <button class="btn btn-sm btn-outline-success" id="adminBtnUnblock" disabled>Unblock Selected</button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
var providerId = {{ $provider->id }};

$(function() {
    initAdminCalendar(providerId);
    loadTrips();
    loadPayments();
});

function loadTrips() {
    ajaxPost({ get_provider_trips: 1, provider_id: providerId }, function(tripResp) {
        var trips = tripResp.trips || [];
        var th = '';
        if (!trips.length) {
            th = '<p class="text-muted small mb-0">No trips found for this provider.</p>';
        } else {
            th += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            th += '<thead class="table-light"><tr><th>Trip ID</th><th>Status</th><th>Service Type</th></tr></thead><tbody>';
            trips.forEach(function(t) {
                var trip = t.trip || t;
                var tId = trip.trip_id || trip.id || '-';
                var statusClass = trip.status === 'confirmed' ? 'success' : (trip.status === 'cancelled' ? 'danger' : 'secondary');
                th += '<tr>';
                th += '<td><a href="/trip-manager/' + tId + '" target="_blank">' + tId + '</a></td>';
                th += '<td><span class="badge bg-' + statusClass + '">' + (trip.status || '-') + '</span></td>';
                th += '<td>' + (t.service_type || '-') + '</td>';
                th += '</tr>';
            });
            th += '</tbody></table></div>';
        }
        $('#providerTrips').html(th);
    });
}

function loadPayments() {
    ajaxPost({ get_provider_payment_history: 1, provider_id: providerId }, function(payResp) {
        var payments = payResp.payments || [];
        var ph = '';
        if (!payments.length) {
            ph = '<p class="text-muted small mb-0">No payments found for this provider.</p>';
        } else {
            ph += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            ph += '<thead class="table-light"><tr><th>Trip ID</th><th class="text-end">Due</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead><tbody>';
            var totalDue = 0, totalPaid = 0, totalBalance = 0;
            payments.forEach(function(pay) {
                var trip = pay.trip || {};
                var tId = trip.trip_id || '-';
                var due = parseFloat(pay.amount_due) || 0;
                var paid = parseFloat(pay.amount_paid) || 0;
                var bal = parseFloat(pay.balance) || 0;
                totalDue += due; totalPaid += paid; totalBalance += bal;
                ph += '<tr>';
                ph += '<td><a href="/trip-manager/' + tId + '" target="_blank">' + tId + '</a></td>';
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
}

$(document).on('click', '#saveProviderBtn', function() {
    var id = $(this).data('id');
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Saving...');
    ajaxPost({
        edit_provider: 1,
        provider_id: id,
        status: $('#editProviderStatus').val(),
        notes: $('#editProviderNotes').val()
    }, function() {
        window.location.reload();
    }, function() {
        btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Save Changes');
        alert('Failed to save. Try again.');
    });
});

// Admin SP Availability Calendar (same pattern as providers.blade.php)
var adminCalYear, adminCalMonth, adminCalData = {}, adminSelectedDates = [], adminCalSpId;
var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function initAdminCalendar(spId) {
    adminCalSpId = spId;
    adminCalYear = new Date().getFullYear();
    adminCalMonth = new Date().getMonth() + 1;
    adminSelectedDates = [];
    loadAdminCalendar();
}

function loadAdminCalendar() {
    $('#adminCalMonthLabel').text(monthNames[adminCalMonth - 1] + ' ' + adminCalYear);
    ajaxPost({ admin_get_sp_calendar: 1, service_provider_id: adminCalSpId, year: adminCalYear, month: adminCalMonth }, function(resp) {
        adminCalData = resp.calendar || {};
        renderAdminCalendar();
    });
}

function renderAdminCalendar() {
    var firstDay = new Date(adminCalYear, adminCalMonth - 1, 1).getDay();
    var daysInMonth = new Date(adminCalYear, adminCalMonth, 0).getDate();
    var html = '<div class="row g-0 text-center mb-1">';
    ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function(d) {
        html += '<div class="col small fw-bold text-muted">' + d + '</div>';
    });
    html += '</div><div class="row g-0 text-center">';
    for (var i = 0; i < firstDay; i++) html += '<div class="col p-1"></div>';
    for (var d = 1; d <= daysInMonth; d++) {
        var dateStr = adminCalYear + '-' + String(adminCalMonth).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        var info = adminCalData[dateStr] || { status: 'available' };
        var bgClass = 'bg-success bg-opacity-25 text-success';
        var cursor = 'cursor: pointer;';
        if (info.status === 'booked') { bgClass = 'bg-danger bg-opacity-25 text-danger'; cursor = 'cursor: not-allowed;'; }
        else if (info.status === 'blocked') { bgClass = 'bg-secondary bg-opacity-25 text-secondary'; cursor = 'cursor: pointer;'; }
        var isSelected = adminSelectedDates.indexOf(dateStr) !== -1;
        var border = isSelected ? 'border: 2px solid #0d6efd;' : 'border: 1px solid transparent;';
        html += '<div class="col p-1"><div class="rounded-2 p-1 ' + bgClass + ' admin-cal-day" data-date="' + dateStr + '" data-status="' + info.status + '" style="' + cursor + border + '"><small>' + d + '</small></div></div>';
        if ((firstDay + d) % 7 === 0) html += '</div><div class="row g-0 text-center">';
    }
    html += '</div>';
    $('#adminCalendarGrid').html(html);
    $('#adminBtnBlock').prop('disabled', !adminSelectedDates.length);
    $('#adminBtnUnblock').prop('disabled', !adminSelectedDates.length);
}

$(document).on('click', '.admin-cal-day', function() {
    if ($(this).data('status') === 'booked') return;
    var date = $(this).data('date');
    var idx = adminSelectedDates.indexOf(date);
    if (idx === -1) adminSelectedDates.push(date); else adminSelectedDates.splice(idx, 1);
    renderAdminCalendar();
});

$(document).on('click', '#adminCalPrev', function() {
    adminCalMonth--; if (adminCalMonth < 1) { adminCalMonth = 12; adminCalYear--; }
    adminSelectedDates = []; loadAdminCalendar();
});

$(document).on('click', '#adminCalNext', function() {
    adminCalMonth++; if (adminCalMonth > 12) { adminCalMonth = 1; adminCalYear++; }
    adminSelectedDates = []; loadAdminCalendar();
});

$(document).on('click', '#adminBtnBlock', function() {
    if (!adminSelectedDates.length) return;
    ajaxPost({ admin_sp_block_dates: 1, service_provider_id: adminCalSpId, dates: adminSelectedDates }, function() {
        adminSelectedDates = []; loadAdminCalendar();
    });
});

$(document).on('click', '#adminBtnUnblock', function() {
    if (!adminSelectedDates.length) return;
    ajaxPost({ admin_sp_unblock_dates: 1, service_provider_id: adminCalSpId, dates: adminSelectedDates }, function() {
        adminSelectedDates = []; loadAdminCalendar();
    });
});
</script>
@endsection
