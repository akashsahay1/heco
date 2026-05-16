@extends('admin.layout')
@section('title', 'Trips - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-luggage"></i> Trips
        <span class="badge bg-secondary ms-2" title="Total in current view">{{ number_format($trips->total()) }}</span>
    </h5>
</div>

{{-- Server-side filter card (Travelers-style). Date pickers use Air
     Datepicker; their hidden ISO fields are what the form actually
     submits. --}}
<form method="GET" action="{{ url('/trips') }}" class="card mb-3" id="tripFilterForm">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select form-select-sm custom-select" name="status">
                    <option value=""              {{ $status === ''              ? 'selected' : '' }}>All statuses</option>
                    <option value="not_confirmed" {{ $status === 'not_confirmed' ? 'selected' : '' }}>Not Confirmed</option>
                    <option value="confirmed"     {{ $status === 'confirmed'     ? 'selected' : '' }}>Confirmed</option>
                    <option value="running"       {{ $status === 'running'       ? 'selected' : '' }}>Running</option>
                    <option value="completed"     {{ $status === 'completed'     ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled"     {{ $status === 'cancelled'     ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Start From</label>
                <input type="text" class="form-control form-control-sm" id="dateFromDisplay" readonly autocomplete="off" placeholder="dd-mm-yyyy"
                    value="{{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d-m-Y') : '' }}">
                <input type="hidden" id="dateFromHidden" name="date_from" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Start To</label>
                <input type="text" class="form-control form-control-sm" id="dateToDisplay" readonly autocomplete="off" placeholder="dd-mm-yyyy"
                    value="{{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d-m-Y') : '' }}">
                <input type="hidden" id="dateToHidden" name="date_to" value="{{ $dateTo }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Search (trip ID / traveller)</label>
                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Type to search...">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> Apply
                </button>
                @if($status !== '' || $dateFrom !== '' || $dateTo !== '' || $search !== '')
                    <a href="{{ url('/trips') }}" class="btn btn-outline-secondary btn-sm">
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
                        <th>Trip ID</th>
                        <th>Traveller</th>
                        <th>Status</th>
                        <th>Dates</th>
                        <th>Regions</th>
                        <th>Total Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trips as $t)
                        @php
                            $regions = $t->regions->pluck('name')->implode(', ');
                            $allStatuses = ['not_confirmed', 'confirmed', 'running', 'completed', 'cancelled'];
                        @endphp
                        <tr>
                            <td><a href="{{ url('/trip-manager/'.$t->id) }}" target="_blank" class="fw-semibold">{{ $t->trip_id ?: $t->id }}</a></td>
                            <td>{{ $t->user ? ($t->user->full_name ?: $t->user->email) : '-' }}</td>
                            <td>
                                <select class="form-select form-select-sm custom-select status-change heco-trip-status" data-trip-id="{{ $t->id }}">
                                    @foreach($allStatuses as $s)
                                        <option value="{{ $s }}" {{ $t->status === $s ? 'selected' : '' }}>{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $s)) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <small>
                                    {{ optional($t->start_date)->format('Y-m-d') ?: '-' }} &mdash;
                                    {{ optional($t->end_date)->format('Y-m-d') ?: '-' }}
                                </small>
                            </td>
                            <td><small>{{ $regions ?: '-' }}</small></td>
                            <td>{{ $t->final_price ? '₹'.number_format($t->final_price, 0, '.', ',') : '-' }}</td>
                            <td>
                                <a href="{{ url('/trip-manager/'.$t->id) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No trips found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($trips->hasPages())
    <div class="mt-3 d-flex justify-content-center">
        {{ $trips->links() }}
    </div>
@endif

@endsection

@section('js')
<script>
function formatTripStatus(status) {
    return status ? status.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); }) : '';
}
function isoFromDate(d) {
    var pad = function(n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
}

jQuery(function() {
    // Wire Air Datepicker to the visible inputs; hidden inputs hold the ISO
    // value the form submits.
    new AirDatepicker('#dateFromDisplay', {
        locale: window.airDatepickerEn,
        dateFormat: 'dd-MM-yyyy',
        autoClose: true,
        position: 'bottom left',
        onSelect: function(o) {
            jQuery('#dateFromHidden').val(o.date ? isoFromDate(o.date) : '');
        }
    });
    new AirDatepicker('#dateToDisplay', {
        locale: window.airDatepickerEn,
        dateFormat: 'dd-MM-yyyy',
        autoClose: true,
        position: 'bottom left',
        onSelect: function(o) {
            jQuery('#dateToHidden').val(o.date ? isoFromDate(o.date) : '');
        }
    });

    // In-table status change → AJAX write + page reload to keep the
    // server-rendered list in sync with the filter state.
    jQuery(document).on('change', '.status-change', function() {
        var sel = jQuery(this);
        var tripId = sel.data('trip-id');
        var newStatus = sel.val();
        Swal.fire({
            text: 'Change trip status to "' + formatTripStatus(newStatus) + '"?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#79a09f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, change it'
        }).then(function(result) {
            if (!result.isConfirmed) { location.reload(); return; }
            ajaxPost({ update_trip_status: 1, trip_id: tripId, status: newStatus }, function() {
                showAlert('Trip status updated.', 'success');
                location.reload();
            }, function(xhr) {
                var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Could not update status') : 'Could not update status';
                showAlert(msg, 'danger');
                location.reload();
            });
        });
    });
});
</script>
@endsection
