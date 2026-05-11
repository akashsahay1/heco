@extends('admin.layout')
@section('title', 'Travelers - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-person-lines-fill"></i> Travelers
        <span class="badge bg-secondary ms-2" title="Total in current view">{{ number_format($travelers->total()) }}</span>
    </h5>
</div>

<form method="GET" action="{{ url('/travelers') }}" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Segment</label>
                <select class="form-select form-select-sm" name="segment">
                    <option value="all" {{ $segment === 'all' ? 'selected' : '' }}>All travelers</option>
                    <option value="with_bookings" {{ $segment === 'with_bookings' ? 'selected' : '' }}>With bookings</option>
                    <option value="without_bookings" {{ $segment === 'without_bookings' ? 'selected' : '' }}>Signed up &mdash; no booking yet</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> Apply
                </button>
                @if($segment !== 'all' || $search !== '')
                    <a href="{{ url('/travelers') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Joined</th>
                        <th class="text-center">Trips</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($travelers as $t)
                        <tr>
                            <td>{{ $t->full_name ?: '-' }}</td>
                            <td><small>{{ $t->email ?: '-' }}</small></td>
                            <td><small>{{ $t->mobile ?: '-' }}</small></td>
                            <td><small>{{ $t->created_at ? $t->created_at->format('d-m-Y') : '-' }}</small></td>
                            <td class="text-center">
                                <span class="badge bg-{{ $t->trips_count > 0 ? 'primary' : 'secondary' }}">{{ $t->trips_count }}</span>
                            </td>
                            <td>
                                @php
                                    $addressLines = array_filter([
                                        trim(($t->address1 ?? '') . (($t->address2 ?? '') ? ', ' . $t->address2 : '')),
                                        trim(implode(', ', array_filter([$t->city, $t->state]))),
                                        trim(implode(' ', array_filter([$t->country, $t->postal_code]))),
                                    ]);
                                    $combinedAddress = implode("\n", $addressLines);
                                @endphp
                                <button class="btn btn-sm btn-outline-primary view-traveler"
                                        data-id="{{ $t->id }}"
                                        data-name="{{ $t->full_name }}"
                                        data-email="{{ $t->email }}"
                                        data-mobile="{{ $t->mobile }}"
                                        data-address="{{ $combinedAddress }}"
                                        data-gender="{{ $t->gender }}"
                                        data-dob="{{ optional($t->date_of_birth)->format('d-m-Y') }}"
                                        data-age="{{ $t->age }}"
                                        data-auth="{{ $t->auth_type }}"
                                        data-joined="{{ $t->created_at ? $t->created_at->format('d-m-Y') : '' }}"
                                        data-trips-count="{{ $t->trips_count }}">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                @if($segment === 'without_bookings')
                                    Every traveler in this view has at least one booking.
                                @elseif($segment === 'with_bookings')
                                    No travelers with confirmed bookings yet.
                                @else
                                    No travelers found.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($travelers->hasPages())
    <div class="d-flex justify-content-between align-items-center mt-3">
        <small class="text-muted">
            Showing {{ $travelers->firstItem() }}&ndash;{{ $travelers->lastItem() }} of {{ number_format($travelers->total()) }}
        </small>
        {{ $travelers->links() }}
    </div>
@endif

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

function fmtDate(s) {
    if (!s) return '-';
    var d = new Date(s);
    if (isNaN(d.getTime())) return s.substring(0, 10);
    var dd = String(d.getDate()).padStart(2, '0');
    var mm = String(d.getMonth() + 1).padStart(2, '0');
    var yy = d.getFullYear();
    return dd + '-' + mm + '-' + yy;
}

function loadTravelerDetail(traveler) {
    var html = '';

    // Traveler Info
    html += '<div class="row mb-4">';
    html += '<div class="col-md-6">';
    html += '<h6 class="border-bottom pb-2"><i class="bi bi-person"></i> Traveler Information</h6>';
    html += '<table class="table table-sm table-borderless">';
    html += '<tr><td class="text-muted" style="width:140px;">Name</td><td><strong>' + (traveler.name || '-') + '</strong></td></tr>';
    html += '<tr><td class="text-muted">Email</td><td>' + (traveler.email || '-') + '</td></tr>';
    html += '<tr><td class="text-muted">Phone</td><td>' + (traveler.mobile || '-') + '</td></tr>';
    html += '<tr><td class="text-muted">Gender</td><td>' + (traveler.gender ? traveler.gender.replace(/_/g, ' ') : '-') + '</td></tr>';
    html += '<tr><td class="text-muted">Date of Birth</td><td>' + (traveler.dob || '-') + (traveler.age ? ' <span class="text-muted">(age ' + traveler.age + ')</span>' : '') + '</td></tr>';
    var addrHtml = traveler.address ? String(traveler.address).split('\n').filter(Boolean).join('<br>') : '-';
    html += '<tr><td class="text-muted">Address</td><td>' + addrHtml + '</td></tr>';
    html += '<tr><td class="text-muted">Auth Type</td><td>' + (traveler.auth || '-') + '</td></tr>';
    html += '<tr><td class="text-muted">Registered</td><td>' + (traveler.joined || '-') + '</td></tr>';
    html += '</table>';
    html += '</div>';

    // Summary Stats
    html += '<div class="col-md-6">';
    html += '<h6 class="border-bottom pb-2"><i class="bi bi-bar-chart"></i> Summary</h6>';
    html += '<div class="row g-2">';
    html += '<div class="col-12"><div class="border rounded p-2 text-center"><div class="fs-4 fw-bold text-primary">' + (traveler.tripsCount || 0) + '</div><small class="text-muted">Total Trips</small></div></div>';
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
    ajaxPost({ get_traveler_trips: 1, user_id: traveler.id }, function(tripResp) {
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
                else if (t.status === 'running') statusClass = 'info';
                th += '<tr>';
                th += '<td><a href="/trip-manager/' + t.id + '" target="_blank">' + (t.trip_id || t.id || '-') + '</a></td>';
                th += '<td><small>' + fmtDate(t.start_date) + '</small></td>';
                th += '<td><small>' + fmtDate(t.end_date) + '</small></td>';
                th += '<td><span class="badge bg-' + statusClass + '">' + (t.status || '-') + '</span></td>';
                th += '<td class="text-end">' + (t.final_price ? Number(t.final_price).toLocaleString('en-IN') : '-') + '</td>';
                th += '</tr>';
            });
            th += '</tbody></table></div>';
        }
        $('#travelerTrips').html(th);
    });

    // Load payment history
    ajaxPost({ get_traveler_payment_history: 1, user_id: traveler.id }, function(payResp) {
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
                ph += '<td><a href="/trip-manager/' + (pay.trip ? pay.trip.id : pay.trip_id) + '" target="_blank">' + (pay.trip ? pay.trip.trip_id : pay.trip_id || '-') + '</a></td>';
                ph += '<td class="text-end">' + amount.toLocaleString('en-IN') + '</td>';
                ph += '<td><small>' + fmtDate(pay.date || pay.created_at) + '</small></td>';
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
});

$(document).on('click', '.view-traveler', function() {
    var $b = $(this);
    var traveler = {
        id: $b.data('id'),
        name: $b.data('name'),
        email: $b.data('email'),
        mobile: $b.data('mobile'),
        address: $b.data('address'),
        gender: $b.data('gender'),
        dob: $b.data('dob'),
        age: $b.data('age'),
        auth: $b.data('auth'),
        joined: $b.data('joined'),
        tripsCount: $b.data('trips-count')
    };
    $('#travelerModalBody').html('<div class="text-center text-muted py-4">Loading...</div>');
    travelerModal.show();
    loadTravelerDetail(traveler);
});
</script>
@endsection
