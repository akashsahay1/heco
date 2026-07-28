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
                    <tr><td class="text-muted w-cell-label-md">Name</td><td><strong>{{ $provider->name ?: '-' }}</strong></td></tr>
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
                    <tr><td class="text-muted w-cell-label-md">Bank Name</td><td>{{ $provider->bank_name ?: '-' }}</td></tr>
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

    {{-- Competences — a regional partner has no catalogue to judge, so this is
         what HCT reads when deciding whether to place them on a region. --}}
    @if($provider->isRegionalPartner())
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="border-bottom pb-2"><i class="bi bi-mortarboard"></i> Competences</h6>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-0">Education</label>
                        <div class="small">{{ $provider->education_level ?: '—' }}</div>
                        @if($provider->education_notes)
                            <div class="small text-muted">{{ $provider->education_notes }}</div>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-0">English</label>
                        <div class="small">{{ $provider->english_level ?: '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-0">Computer skills</label>
                        <div class="small">{{ $provider->computer_skill_level ?: '—' }}</div>
                    </div>
                </div>

                <hr class="my-3">
                <label class="form-label small text-muted">Work experience</label>
                @forelse(($provider->work_experience ?: []) as $role)
                    <div class="border-start border-3 ps-2 mb-2">
                        <div class="small fw-semibold">
                            {{ $role['role'] ?? '—' }}
                            @if(!empty($role['organisation']))
                                <span class="text-muted fw-normal">· {{ $role['organisation'] }}</span>
                            @endif
                            @if(!empty($role['years']))
                                <span class="text-muted fw-normal">· {{ $role['years'] }}</span>
                            @endif
                        </div>
                        @if(!empty($role['description']))
                            <div class="small text-muted">{{ $role['description'] }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted small">None listed</p>
                @endforelse

                <hr class="my-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted mb-0">Dedication to social / environmental causes</label>
                        <div class="small">{{ $provider->causes_note ?: '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted mb-0">Understanding of the local community</label>
                        <div class="small">{{ $provider->community_note ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="border-bottom pb-2"><i class="bi bi-pencil-square"></i> Admin Controls</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Status</label>
                        <select class="form-select form-select-sm custom-select" id="editProviderStatus" data-id="{{ $provider->id }}">
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

                {{-- iCal Sync status — read-only for admin --}}
                <div class="alert alert-light border small mb-3 py-2 px-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                        <div>
                            <strong><i class="bi bi-arrow-down-up me-1"></i>iCal Sync (inbound from Booking.com / Airbnb / etc.)</strong>
                            <div class="mt-1">
                                @if($provider->ical_url)
                                    <span class="text-muted">URL:</span>
                                    <code class="small">{{ \Illuminate\Support\Str::limit($provider->ical_url, 80) }}</code>
                                @else
                                    <span class="text-muted">No external calendar connected — availability comes only from HECO trip bookings + manual blocks.</span>
                                @endif
                            </div>
                            <div class="mt-1">
                                <span class="text-muted">Last synced:</span>
                                @if($provider->ical_last_synced_at)
                                    <strong>{{ $provider->ical_last_synced_at->format('d M Y H:i') }}</strong>
                                @else
                                    <em class="text-muted">never</em>
                                @endif
                            </div>
                        </div>
                        <small class="text-muted">SP manages this from their own dashboard. Admin view-only.</small>
                    </div>
                </div>

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

    {{-- Room inventory snapshot — for accommodation SPs --}}
    @php
        $accommodationRows = $provider->pricing->where('service_type', 'accommodation')->where('is_active', true)->values();
    @endphp
    @if($accommodationRows->count())
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="border-bottom pb-2"><i class="bi bi-door-closed"></i> Room Inventory</h6>
                <p class="text-muted small mb-2">All active accommodation rows from Services &amp; Pricing — what this property offers and how many rooms of each.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Room Category</th>
                                <th class="w-status text-end">Total Rooms</th>
                                <th class="w-status text-end">Rate / night</th>
                                <th>Meal Plan</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accommodationRows as $r)
                                <tr>
                                    <td><strong>{{ $r->room_category ?: $r->category ?: '—' }}</strong></td>
                                    <td class="text-end"><span class="badge bg-info-subtle text-info-emphasis">{{ $r->total_rooms ?? '—' }}</span></td>
                                    <td class="text-end fw-semibold">&#8377;{{ number_format($r->price, 2) }}</td>
                                    <td class="small">{{ $r->meal_plan ?: '—' }}</td>
                                    <td class="small text-muted">{{ $r->description ?: '—' }}</td>
                                </tr>
                            @endforeach
                            <tr class="table-light">
                                <td class="fw-bold">Total inventory</td>
                                <td class="text-end fw-bold">{{ $accommodationRows->sum('total_rooms') }} rooms</td>
                                <td colspan="3"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif
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

function fmtServiceType(s) {
    if (!s) return '-';
    return s.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
}
function fmtTripStatus(s) {
    return s ? s.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); }) : '-';
}

function loadTrips() {
    ajaxPost({ get_provider_trips: 1, provider_id: providerId }, function(tripResp) {
        var trips = tripResp.trips || tripResp.data || [];
        var th = '';
        if (!trips.length) {
            th = '<p class="text-muted small mb-0">No trips found for this provider.</p>';
        } else {
            th += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            th += '<thead class="table-light"><tr><th>Trip</th><th>Service Type</th><th>Day</th><th>Dates</th><th>Status</th></tr></thead><tbody>';
            trips.forEach(function(t) {
                var trip = t.trip || {};
                var tNum = trip.id;
                var tCode = trip.trip_id || trip.id || '-';
                var statusClass = trip.status === 'confirmed' ? 'success' : (trip.status === 'cancelled' ? 'danger' : (trip.status === 'completed' ? 'secondary' : 'warning text-dark'));
                var dayLabel = (t.day_number ? 'Day ' + t.day_number : '-');
                var dates = (trip.start_date || '-') + ' — ' + (trip.end_date || '-');
                th += '<tr>';
                th += '<td>' + (tNum ? '<a href="/trip-manager/' + tNum + '" target="_blank">' + tCode + '</a>' : tCode);
                if (trip.traveller) th += '<br><small class="text-muted">' + trip.traveller + '</small>';
                th += '</td>';
                th += '<td>' + fmtServiceType(t.service_type) + '</td>';
                th += '<td><small>' + dayLabel + '</small></td>';
                th += '<td><small>' + dates + '</small></td>';
                th += '<td><span class="badge bg-' + statusClass + '">' + fmtTripStatus(trip.status) + '</span></td>';
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
                var tNum = trip.id;
                var tCode = trip.trip_id || trip.id || '-';
                var due = parseFloat(pay.amount_due) || 0;
                var paid = parseFloat(pay.amount_paid) || 0;
                var bal = parseFloat(pay.balance) || 0;
                totalDue += due; totalPaid += paid; totalBalance += bal;
                ph += '<tr>';
                ph += '<td>' + (tNum ? '<a href="/trip-manager/' + tNum + '" target="_blank">' + tCode + '</a>' : tCode) + '</td>';
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

var adminRoomCalendar = {};

function adminRoomBreakdown(dateStr) {
    var cats = adminRoomCalendar[dateStr] || [];
    if (!cats.length) return '';
    var lines = cats.map(function(c) {
        var color = c.available === 0
            ? 'cal-room-row--sold'
            : (c.available < c.total ? 'cal-room-row--partial' : 'cal-room-row--ok');
        var tier = c.comfort_tier ? '<span class="admin-cal-room-tier">' + c.comfort_tier + '</span>' : '';
        var room = c.room_category || '—';
        return '<div class="admin-cal-room-row ' + color + '">' +
               tier +
               '<span class="admin-cal-room-name">' + room + '</span>' +
               '<span class="admin-cal-room-avail">' + c.available + '/' + c.total + '</span>' +
               '</div>';
    });
    return '<div class="admin-cal-rooms"><div class="admin-cal-rooms-title">Room availability</div>' + lines.join('') + '</div>';
}

function adminRoomTooltip(dateStr) {
    var cats = adminRoomCalendar[dateStr] || [];
    if (!cats.length) return '';
    return cats.map(function(c) {
        return c.room_category + ': ' + c.available + '/' + c.total + ' (' + c.booked + ' booked)';
    }).join('\n');
}

function loadAdminCalendar() {
    $('#adminCalMonthLabel').text(monthNames[adminCalMonth - 1] + ' ' + adminCalYear);
    ajaxPost({ admin_get_sp_calendar: 1, service_provider_id: adminCalSpId, year: adminCalYear, month: adminCalMonth }, function(resp) {
        adminCalData = resp.calendar || {};
        adminRoomCalendar = resp.rooms || {};
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
        var bgClass = 'cal-cell-available';
        var cursorClass = 'cursor-pointer';
        if (info.status === 'booked') { bgClass = 'cal-cell-booked'; cursorClass = 'cursor-not-allowed'; }
        else if (info.status === 'blocked') { bgClass = 'cal-cell-blocked'; cursorClass = 'cursor-pointer'; }
        var isSelected = adminSelectedDates.indexOf(dateStr) !== -1;
        var selClass = isSelected ? 'admin-cal-day--selected' : '';
        // Room breakdown lives inside the cell as a hidden CSS hover popover;
        // no native title attribute so the two tooltips don't compete.
        html += '<div class="col p-1"><div class="rounded-2 p-1 ' + bgClass + ' admin-cal-day ' + cursorClass + ' ' + selClass + '" data-date="' + dateStr + '" data-status="' + info.status + '"><small>' + d + '</small>' + adminRoomBreakdown(dateStr) + '</div></div>';
        if ((firstDay + d) % 7 === 0) html += '</div><div class="row g-0 text-center">';
    }
    html += '</div>';
    $('#adminCalendarGrid').html(html);
    $('#adminBtnBlock').prop('disabled', !adminSelectedDates.length);
    $('#adminBtnUnblock').prop('disabled', !adminSelectedDates.length);
}

// Position the room popover in viewport coords (position: fixed so it
// escapes ancestors with overflow: hidden). Measure real width/height
// first, then anchor below the cell — or above if there's no space.
$(document).on('mouseenter', '.admin-cal-day', function() {
    var $cell = $(this);
    var $rooms = $cell.find('.admin-cal-rooms');
    if (!$rooms.length) return;

    $rooms.css({ display: 'block', visibility: 'hidden', left: 0, top: 0 });
    var popoverH = $rooms.outerHeight();
    var popoverW = $rooms.outerWidth();

    var cellRect = $cell[0].getBoundingClientRect();
    var viewportW = $(window).width();
    var viewportH = $(window).height();
    var gap = 6;

    var left = cellRect.left + (cellRect.width / 2) - (popoverW / 2);
    left = Math.max(8, Math.min(left, viewportW - popoverW - 8));

    var spaceBelow = viewportH - cellRect.bottom;
    var spaceAbove = cellRect.top;
    var top;
    if (spaceBelow >= popoverH + gap || spaceBelow >= spaceAbove) {
        top = cellRect.bottom + gap;
    } else {
        top = cellRect.top - popoverH - gap;
    }
    top = Math.max(8, Math.min(top, viewportH - popoverH - 8));

    $rooms.css({ left: left + 'px', top: top + 'px', display: '', visibility: '' });
});

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
