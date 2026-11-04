@extends('admin.layout')
@section('title', 'HCT Dashboard')
@section('content')

<div class="row g-3 mb-4" id="statsRow">
    <div class="col-md-3 col-6">
        <div class="card stat-card"><div class="card-body">
            <div class="stat-value" id="statLeads">-</div>
            <div class="stat-label">Active Leads</div>
        </div></div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card"><div class="card-body">
            <div class="stat-value" id="statTrips">-</div>
            <div class="stat-label">Active Trips</div>
        </div></div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card"><div class="card-body">
            <div class="stat-value" id="statApplications">-</div>
            <div class="stat-label">Pending Applications</div>
        </div></div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card"><div class="card-body">
            <div class="stat-value" id="statRevenue">-</div>
            <div class="stat-label">Revenue This Month</div>
        </div></div>
    </div>
</div>

{{-- What needs doing today (HECO's feedback, 2026-10-28): money still owed,
     enquiries waiting for a call back, and support. --}}
<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-cash-coin"></i> Pending Payments</h6>
                <span class="badge bg-warning text-dark" id="pendingCount">0</span>
            </div>
            <div class="card-body scroll-panel-md" id="pendingList">
                <p class="text-muted text-center small">Loading...</p>
            </div>
            <div class="card-footer small text-muted py-1" id="pendingRule"></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-bell"></i> Lead Reminders</h6>
                <span class="badge bg-warning text-dark" id="reminderCount">0</span>
            </div>
            <div class="card-body scroll-panel-md" id="reminderList">
                <p class="text-muted text-center small">Loading...</p>
            </div>
        </div>
    </div>
</div>

{{-- Record a payment without opening the trip --}}
<div class="modal fade" id="dashPayModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Add payment <span class="text-muted" id="dashPayTrip"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger py-2 small d-none" id="dashPayError"></div>
                <input type="hidden" id="dashPayTripId">
                <label class="form-label small fw-bold mb-1">Amount (&#8377;)</label>
                <input type="number" class="form-control form-control-sm mb-2" id="dashPayAmount" step="0.01" min="0.01">
                <label class="form-label small fw-bold mb-1">Date</label>
                <input type="date" class="form-control form-control-sm mb-2" id="dashPayDate" value="{{ date('Y-m-d') }}">
                <label class="form-label small fw-bold mb-1">Mode</label>
                <select class="form-select form-select-sm custom-select mb-2" id="dashPayMode">
                    @foreach(\App\Models\SystemList::ofType('payment_mode')->where('is_active', true)->get() as $m)
                        <option value="{{ \Illuminate\Support\Str::snake(strtolower($m->name)) }}">{{ $m->name }}</option>
                    @endforeach
                </select>
                <input type="text" class="form-control form-control-sm" id="dashPayNotes" placeholder="Notes (optional)">
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-success" id="dashPaySave">Save payment</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h6 class="mb-0">Unresolved Support</h6>
                <span class="badge bg-danger" id="supportCount">0</span>
            </div>
            <div class="card-body scroll-panel-md" id="supportList">
                <p class="text-muted text-center small">Loading...</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Upcoming Trips (30 days)</h6></div>
            <div class="card-body scroll-panel-md" id="upcomingList">
                <p class="text-muted text-center small">Loading...</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Quick Links</h6></div>
            <div class="card-body">
                <a href="{{ url('/experiences/create') }}" class="btn btn-outline-success btn-sm w-100 mb-2"><i class="bi bi-plus"></i> New Experience</a>
                <a href="{{ url('/regenerative-projects/create') }}" class="btn btn-outline-success btn-sm w-100 mb-2"><i class="bi bi-plus"></i> New Regenerative Project</a>
                <a href="{{ url('/provider-applications') }}" class="btn btn-outline-success btn-sm w-100 mb-2"><i class="bi bi-envelope-paper"></i> Review Applications</a>
                <a href="{{ url('/leads') }}" class="btn btn-outline-success btn-sm w-100"><i class="bi bi-funnel"></i> Manage Leads</a>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
$(function() {
    ajaxPost({ get_dashboard_stats: 1 }, function(resp) {
        var s = resp.stats;
        $('#statLeads').text(s.total_leads);
        $('#statTrips').text(s.active_trips);
        $('#statApplications').text(s.pending_applications);
        $('#statRevenue').text('₹' + Number(s.revenue_this_month).toLocaleString());
        $('#supportCount').text(s.unresolved_support);
    });

    ajaxPost({ get_support_requests: 1, unresolved_only: 1 }, function(resp) {
        var html = '';
        var items = resp.data || [];
        if (!items.length) { html = '<p class="text-muted text-center small">No unresolved requests</p>'; }
        items.forEach(function(r) {
            html += '<div class="border-bottom pb-2 mb-2"><strong class="small">' + (r.user ? r.user.full_name || r.user.email : 'Unknown') + '</strong>';
            html += '<span class="badge bg-' + (r.traveller_status === 'client' ? 'success' : 'warning text-dark') + ' ms-1">' + r.traveller_status + '</span>';
            html += '<p class="small mb-1">' + r.message.substring(0, 100) + '</p>';
            html += '<button class="btn btn-sm btn-outline-success resolve-btn" data-id="' + r.id + '">Resolve</button></div>';
        });
        $('#supportList').html(html);
    });

    ajaxPost({ get_upcoming_trips: 1, within_days: 30, limit: 12 }, function(resp) {
        var items = resp.trips || resp.data || [];
        var html = '';
        if (!items.length) {
            $('#upcomingList').html('<p class="text-muted text-center small">No trips starting in the next 30 days</p>');
            return;
        }
        items.forEach(function(t) {
            var regions = (t.regions && t.regions.length) ? t.regions.map(function(r) { return r.name; }).join(', ') : '';
            var code = t.trip_id || t.id;
            var traveller = t.user ? (t.user.full_name || t.user.email) : '-';
            var start = t.start_date ? String(t.start_date).substring(0, 10) : '-';
            var statusClass = t.status === 'confirmed' ? 'success' : (t.status === 'running' ? 'primary' : (t.status === 'cancelled' ? 'danger' : 'warning text-dark'));
            html += '<div class="border-bottom pb-2 mb-2">';
            html += '<div class="d-flex justify-content-between align-items-start">';
            html += '<a href="/trip-manager/' + t.id + '" class="small fw-semibold">' + code + '</a>';
            html += '<span class="badge bg-' + statusClass + '">' + (t.status ? t.status.replace(/_/g, ' ') : '-') + '</span>';
            html += '</div>';
            html += '<div class="small text-muted">' + traveller + (regions ? ' &middot; ' + regions : '') + '</div>';
            html += '<div class="small"><i class="bi bi-calendar-event"></i> ' + start + '</div>';
            html += '</div>';
        });
        $('#upcomingList').html(html);
    });
});

function dashEsc(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }
function dashMoney(n) { return '&#8377;' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); }

function loadPendingPayments() {
    ajaxPost({ get_pending_payments: 1 }, function(resp) {
        var items = resp.items || [];
        $('#pendingCount').text(items.length);
        $('#pendingRule').text('Fully paid trips leave this list. A part-paid trip comes back ' + resp.days_before
            + ' day' + (resp.days_before == 1 ? '' : 's') + ' before it starts (Control Panel > Settings).');
        if (!items.length) {
            $('#pendingList').html('<p class="text-muted text-center small mb-0">Nothing to chase right now.</p>');
            return;
        }
        var html = '';
        items.forEach(function(p) {
            html += '<div class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start gap-2">';
            html += '<div class="small">';
            html += '<a href="/trip-manager/' + p.id + '" class="fw-semibold">' + dashEsc(p.trip_id) + '</a> &middot; ' + dashEsc(p.traveller || '-');
            html += '<div class="text-muted">' + dashEsc(p.why) + (p.start_date ? ' &middot; starts ' + dashEsc(p.start_date) : '') + '</div>';
            html += '<div>Due <strong>' + dashMoney(p.balance) + '</strong> <span class="text-muted">of ' + dashMoney(p.final_price) + (p.paid > 0 ? ', paid ' + dashMoney(p.paid) : '') + '</span></div>';
            html += '</div>';
            html += '<button class="btn btn-sm btn-outline-success dash-add-pay" data-id="' + p.id + '" data-code="' + dashEsc(p.trip_id) + '" data-balance="' + p.balance + '"><i class="bi bi-plus"></i> Add payment</button>';
            html += '</div>';
        });
        $('#pendingList').html(html);
    });
}

function loadLeadReminders() {
    ajaxPost({ get_lead_reminders: 1 }, function(resp) {
        var rems = resp.reminders || [];
        $('#reminderCount').text(rems.length);
        if (!rems.length) {
            $('#reminderList').html('<p class="text-muted text-center small mb-0">No reminders due</p>');
            return;
        }
        var html = '';
        rems.forEach(function(r) {
            // A lead holds its own name now; an older one has it on the account.
            var who = r.full_name || (r.user ? (r.user.full_name || r.user.email) : '') || r.email || '-';
            html += '<div class="border-bottom pb-2 mb-2 small">';
            html += '<strong>' + dashEsc(who) + '</strong>' + (r.email ? ' <span class="text-muted">' + dashEsc(r.email) + '</span>' : '');
            if (r.trip) html += '<div class="text-muted">Trip: <a href="/trip-manager/' + r.trip.id + '">' + dashEsc(r.trip.trip_id) + '</a></div>';
            html += '<a href="/leads" class="btn btn-sm btn-outline-warning mt-1">Follow up</a>';
            html += '</div>';
        });
        $('#reminderList').html(html);
    });
}

$(function() {
    loadPendingPayments();
    loadLeadReminders();
});

$(document).on('click', '.dash-add-pay', function() {
    var $b = $(this);
    $('#dashPayTripId').val($b.data('id'));
    $('#dashPayTrip').text($b.data('code'));
    $('#dashPayAmount').val($b.data('balance'));
    $('#dashPayNotes').val('');
    $('#dashPayError').addClass('d-none').text('');
    if (window.buildCustomDropdown) buildCustomDropdown($('#dashPayMode')[0]);
    new bootstrap.Modal(document.getElementById('dashPayModal')).show();
});

$('#dashPaySave').on('click', function() {
    var $btn = $(this).prop('disabled', true);
    ajaxPost({
        add_traveller_payment: 1,
        trip_id: $('#dashPayTripId').val(),
        amount: $('#dashPayAmount').val(),
        payment_date: $('#dashPayDate').val(),
        mode: $('#dashPayMode').val(),
        notes: $('#dashPayNotes').val()
    }, function(resp) {
        $btn.prop('disabled', false);
        bootstrap.Modal.getInstance(document.getElementById('dashPayModal')).hide();
        showAlert(resp && resp.rooms_warning ? resp.rooms_warning : 'Payment recorded.', resp && resp.rooms_warning ? 'warning' : 'success');
        loadPendingPayments();
    }, function(xhr) {
        $btn.prop('disabled', false);
        $('#dashPayError').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.error) || 'The payment could not be saved.');
    });
});

$(document).on('click', '.resolve-btn', function() {
    var btn = $(this);
    ajaxPost({ resolve_support_request: 1, id: btn.data('id') }, function() {
        btn.closest('.border-bottom').fadeOut();
    });
});
</script>
@endsection
