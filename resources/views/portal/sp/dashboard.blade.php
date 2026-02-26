@extends('portal.layout')
@section('title', 'Service Provider Dashboard - HECO Portal')

@section('content')
<div class="container py-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-building"></i> Service Provider Dashboard</h4>
            <span class="text-muted">{{ $provider->name }}</span>
            <span class="badge bg-{{ $provider->provider_type === 'HLH' ? 'success' : ($provider->provider_type === 'HRP' ? 'primary' : 'info') }} ms-2">{{ $provider->provider_type }}</span>
            <span class="badge bg-{{ $provider->status === 'approved' ? 'success' : ($provider->status === 'pending' ? 'warning text-dark' : 'secondary') }} ms-1">{{ ucfirst($provider->status ?? 'pending') }}</span>
        </div>
        <div>
            <a href="/home" class="btn btn-sm btn-outline-secondary"><i class="bi bi-house"></i> Home</a>
        </div>
    </div>

    <div class="row">
        {{-- Left Column --}}
        <div class="col-md-6">

            {{-- Identity Card --}}
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="bi bi-person-vcard"></i> Identity & Contact</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted small" style="width: 140px;">Name</td>
                                <td class="small fw-bold">{{ $provider->name }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Type</td>
                                <td class="small">
                                    @if($provider->provider_type === 'HLH')
                                        <span class="badge bg-success">HLH</span> HECO Local Host
                                    @elseif($provider->provider_type === 'HRP')
                                        <span class="badge bg-primary">HRP</span> HECO Resource Person
                                    @elseif($provider->provider_type === 'OSP')
                                        <span class="badge bg-info">OSP</span> Operational Service Provider
                                    @else
                                        <span class="badge bg-secondary">{{ $provider->provider_type }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Contact Person</td>
                                <td class="small">{{ $provider->contact_person ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Email</td>
                                <td class="small">{{ $provider->email ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Phone 1</td>
                                <td class="small">{{ $provider->phone_1 ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Phone 2</td>
                                <td class="small">{{ $provider->phone_2 ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Region</td>
                                <td class="small">{{ $provider->region->name ?? '-' }}{{ $provider->region && $provider->region->country ? ', ' . $provider->region->country : '' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Address</td>
                                <td class="small">{{ $provider->address ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Status</td>
                                <td>
                                    <span class="badge bg-{{ $provider->status === 'approved' ? 'success' : ($provider->status === 'pending' ? 'warning text-dark' : 'secondary') }}">
                                        {{ ucfirst($provider->status ?? 'pending') }}
                                    </span>
                                    @if($provider->approved_at)
                                        <small class="text-muted ms-1">Approved {{ $provider->approved_at->format('d M Y') }}</small>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Bank / Payment Details --}}
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="bi bi-bank"></i> Bank & Payment Details</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted small" style="width: 140px;">Bank Name</td>
                                <td class="small">{{ $provider->bank_name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">IFSC Code</td>
                                <td class="small">{{ $provider->bank_ifsc ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Account Name</td>
                                <td class="small">{{ $provider->bank_account_name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Account Number</td>
                                <td class="small">
                                    @if($provider->bank_account_number)
                                        {{ substr($provider->bank_account_number, 0, 4) }}****{{ substr($provider->bank_account_number, -4) }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">UPI</td>
                                <td class="small">{{ $provider->upi ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="alert alert-info small mt-2 mb-0">
                        <i class="bi bi-info-circle"></i> To update bank details, please contact HCT administration.
                    </div>
                </div>
            </div>

        </div>

        {{-- Right Column --}}
        <div class="col-md-6">

            {{-- Availability Calendar --}}
            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar3"></i> Availability Calendar</h6>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" id="calPrev"><i class="bi bi-chevron-left"></i></button>
                        <span class="small fw-bold mx-2" id="calMonthLabel"></span>
                        <button class="btn btn-sm btn-outline-secondary" id="calNext"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3 mb-2 small">
                        <span><span class="badge bg-success">&nbsp;&nbsp;</span> Available</span>
                        <span><span class="badge bg-danger">&nbsp;&nbsp;</span> Booked (Trip)</span>
                        <span><span class="badge bg-secondary">&nbsp;&nbsp;</span> Blocked</span>
                    </div>
                    <div id="spCalendarGrid" class="mb-3"></div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-danger" id="btnBlockSelected" disabled>Block Selected</button>
                        <button class="btn btn-sm btn-outline-success" id="btnUnblockSelected" disabled>Unblock Selected</button>
                    </div>
                    <hr>
                    <h6 class="small fw-bold mb-2"><i class="bi bi-link-45deg"></i> iCal Sync (Booking.com / Airbnb)</h6>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" class="form-control" id="icalUrlInput" placeholder="Paste iCal URL here..." value="{{ $provider->ical_url ?? '' }}">
                        <button class="btn btn-outline-primary" id="btnSaveIcal">Save</button>
                    </div>
                    @if($provider->ical_url)
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-outline-info" id="btnSyncIcal">Sync Now</button>
                            <small class="text-muted" id="icalSyncStatus">
                                @if($provider->ical_last_synced_at)
                                    Last synced: {{ $provider->ical_last_synced_at->format('d M Y H:i') }}
                                @else
                                    Not synced yet
                                @endif
                            </small>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Services & Pricing --}}
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="bi bi-list-check"></i> Services & Pricing</h6>
                </div>
                <div class="card-body">
                    {{-- Services Offered (for OSP) --}}
                    @if($provider->services_offered && count($provider->services_offered))
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Services Offered</label>
                            <div>
                                @foreach($provider->services_offered as $service)
                                    <span class="badge bg-success me-1 mb-1">{{ ucfirst($service) }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Accommodation Categories --}}
                    @if($provider->accommodation_categories && count($provider->accommodation_categories))
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Accommodation Categories</label>
                            <div>
                                @foreach($provider->accommodation_categories as $cat)
                                    <span class="badge bg-info me-1 mb-1">{{ ucfirst($cat) }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Vehicle Types --}}
                    @if($provider->vehicle_types && count($provider->vehicle_types))
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Vehicle Types</label>
                            <div>
                                @foreach($provider->vehicle_types as $vtype)
                                    <span class="badge bg-secondary me-1 mb-1">{{ ucfirst($vtype) }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Pricing Table --}}
                    @if($provider->pricing && $provider->pricing->count())
                        <div class="table-responsive mt-2">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="small">Service</th>
                                        <th class="small">Category</th>
                                        <th class="small">Description</th>
                                        <th class="small">Unit</th>
                                        <th class="small text-end">Price</th>
                                        <th class="small">Meal Plan</th>
                                        <th class="small text-center">Active</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($provider->pricing as $price)
                                        <tr class="{{ !$price->is_active ? 'text-muted' : '' }}">
                                            <td class="small">{{ ucfirst($price->service_type ?? '-') }}</td>
                                            <td class="small">{{ ucfirst($price->category ?? '-') }}</td>
                                            <td class="small">{{ $price->description ?? '-' }}</td>
                                            <td class="small">{{ $price->unit ?? '-' }}</td>
                                            <td class="small text-end text-success">&#8377;{{ number_format($price->price ?? 0, 2) }}</td>
                                            <td class="small">{{ $price->meal_plan ?? '-' }}</td>
                                            <td class="small text-center">
                                                @if($price->is_active)
                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                @else
                                                    <i class="bi bi-x-circle text-muted"></i>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted small text-center mb-0">No pricing records found. HCT will set up your pricing.</p>
                    @endif
                </div>
            </div>

            {{-- HLH: My Experiences --}}
            @if($provider->provider_type === 'HLH')
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-star"></i> My Experiences</h6>
                    </div>
                    <div class="card-body p-0">
                        @if($provider->experiences && $provider->experiences->count())
                            <div class="list-group list-group-flush">
                                @foreach($provider->experiences as $experience)
                                    <div class="list-group-item py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong class="small">{{ $experience->name }}</strong>
                                                @if($experience->type)
                                                    <span class="badge bg-success bg-opacity-25 text-success ms-1">{{ $experience->type }}</span>
                                                @endif
                                            </div>
                                            <div>
                                                @if($experience->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactive</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            @if($experience->region)
                                                <i class="bi bi-geo-alt"></i> {{ $experience->region->name }}
                                            @endif
                                            @if($experience->duration_hours)
                                                <span class="ms-2"><i class="bi bi-clock"></i> {{ $experience->duration_hours }}h</span>
                                            @elseif($experience->duration_days)
                                                <span class="ms-2"><i class="bi bi-clock"></i> {{ $experience->duration_days }}d{{ $experience->duration_nights ? '/' . $experience->duration_nights . 'n' : '' }}</span>
                                            @endif
                                            @if($experience->base_cost_per_person)
                                                <span class="ms-2 text-success">&#8377;{{ number_format($experience->base_cost_per_person, 2) }}/pp</span>
                                            @endif
                                        </div>
                                        @if($experience->short_description)
                                            <p class="small text-muted mb-0 mt-1">{{ Str::limit($experience->short_description, 120) }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-3 text-muted text-center small">No experiences listed yet. HCT will set up your experiences.</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- HRP: My Region --}}
            @if($provider->provider_type === 'HRP' && $provider->region)
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-map"></i> My Region</h6>
                    </div>
                    <div class="card-body">
                        <h6>{{ $provider->region->name }}</h6>
                        @if($provider->region->country)
                            <p class="small text-muted mb-1">{{ $provider->region->country }}</p>
                        @endif
                        @if($provider->region->description)
                            <p class="small">{{ $provider->region->description }}</p>
                        @endif

                        {{-- Trip Regions managed by this HRP --}}
                        @if($provider->tripRegions && $provider->tripRegions->count())
                            <hr>
                            <h6 class="small fw-bold">Trips in My Region</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="small">Trip ID</th>
                                            <th class="small">Trip Name</th>
                                            <th class="small">Status</th>
                                            <th class="small">Dates</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($provider->tripRegions as $tripRegion)
                                            @if($tripRegion->trip)
                                                <tr>
                                                    <td class="small text-success">{{ $tripRegion->trip->trip_id }}</td>
                                                    <td class="small">{{ $tripRegion->trip->trip_name ?? 'Unnamed' }}</td>
                                                    <td class="small">
                                                        <span class="badge bg-{{ $tripRegion->trip->status === 'confirmed' ? 'success' : ($tripRegion->trip->status === 'running' ? 'primary' : 'warning text-dark') }}">
                                                            {{ ucfirst(str_replace('_', ' ', $tripRegion->trip->status)) }}
                                                        </span>
                                                    </td>
                                                    <td class="small">
                                                        @if($tripRegion->trip->start_date)
                                                            {{ $tripRegion->trip->start_date->format('d M') }}
                                                            @if($tripRegion->trip->end_date)
                                                                - {{ $tripRegion->trip->end_date->format('d M Y') }}
                                                            @endif
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted small mb-0">No trips currently assigned to your region.</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- SP Payments summary --}}
            @if($provider->spPayments && $provider->spPayments->count())
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-cash-stack"></i> Payment Summary</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="small">Trip</th>
                                        <th class="small">Service</th>
                                        <th class="small text-end">Due</th>
                                        <th class="small text-end">Paid</th>
                                        <th class="small text-end">Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $totalDue = 0;
                                        $totalPaid = 0;
                                    @endphp
                                    @foreach($provider->spPayments as $payment)
                                        @php
                                            $totalDue += $payment->amount_due ?? 0;
                                            $totalPaid += $payment->amount_paid ?? 0;
                                        @endphp
                                        <tr>
                                            <td class="small text-success">{{ $payment->trip->trip_id ?? '-' }}</td>
                                            <td class="small">{{ $payment->service_type ?? '-' }}</td>
                                            <td class="small text-end">&#8377;{{ number_format($payment->amount_due ?? 0, 2) }}</td>
                                            <td class="small text-end text-success">&#8377;{{ number_format($payment->amount_paid ?? 0, 2) }}</td>
                                            <td class="small text-end {{ ($payment->balance ?? 0) > 0 ? 'text-danger' : 'text-success' }}">&#8377;{{ number_format($payment->balance ?? 0, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                    <tr class="fw-bold">
                                        <td colspan="2" class="small">Total</td>
                                        <td class="small text-end">&#8377;{{ number_format($totalDue, 2) }}</td>
                                        <td class="small text-end text-success">&#8377;{{ number_format($totalPaid, 2) }}</td>
                                        <td class="small text-end {{ ($totalDue - $totalPaid) > 0 ? 'text-danger' : 'text-success' }}">&#8377;{{ number_format($totalDue - $totalPaid, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
@endsection

@section('js')
<script>
jQuery(function() {
    var calYear = {{ now()->year }};
    var calMonth = {{ now()->month }};
    var calendarData = {};
    var selectedDates = [];
    var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function loadCalendar() {
        jQuery('#calMonthLabel').text(monthNames[calMonth - 1] + ' ' + calYear);
        ajaxPost({ get_sp_calendar: 1, year: calYear, month: calMonth }, function(resp) {
            calendarData = resp.calendar || {};
            renderCalendar();
            if (resp.ical_url) jQuery('#icalUrlInput').val(resp.ical_url);
        });
    }

    function renderCalendar() {
        var firstDay = new Date(calYear, calMonth - 1, 1).getDay();
        var daysInMonth = new Date(calYear, calMonth, 0).getDate();
        var html = '<div class="row g-0 text-center mb-1">';
        ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function(d) {
            html += '<div class="col small fw-bold text-muted">' + d + '</div>';
        });
        html += '</div><div class="row g-0 text-center">';

        for (var i = 0; i < firstDay; i++) {
            html += '<div class="col p-1"></div>';
        }

        for (var d = 1; d <= daysInMonth; d++) {
            var dateStr = calYear + '-' + String(calMonth).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            var info = calendarData[dateStr] || { status: 'available' };
            var bgClass = 'bg-success bg-opacity-25 text-success';
            var cursor = 'cursor: pointer;';
            var title = 'Available - click to select';
            if (info.status === 'booked') {
                bgClass = 'bg-danger bg-opacity-25 text-danger';
                cursor = 'cursor: not-allowed;';
                title = 'Booked (Trip #' + (info.trip_id || '') + ')';
            } else if (info.status === 'blocked') {
                bgClass = 'bg-secondary bg-opacity-25 text-secondary';
                cursor = 'cursor: pointer;';
                title = 'Blocked (' + (info.source || 'manual') + ')';
            }

            var isSelected = selectedDates.indexOf(dateStr) !== -1;
            var border = isSelected ? 'border: 2px solid #0d6efd;' : 'border: 1px solid transparent;';

            html += '<div class="col p-1">';
            html += '<div class="rounded-2 p-1 ' + bgClass + ' sp-cal-day" data-date="' + dateStr + '" data-status="' + info.status + '" data-source="' + (info.source || '') + '" style="' + cursor + border + '" title="' + title + '">';
            html += '<small>' + d + '</small>';
            html += '</div></div>';

            if ((firstDay + d) % 7 === 0) {
                html += '</div><div class="row g-0 text-center">';
            }
        }
        html += '</div>';
        jQuery('#spCalendarGrid').html(html);
        updateButtons();
    }

    function updateButtons() {
        var hasSelected = selectedDates.length > 0;
        jQuery('#btnBlockSelected').prop('disabled', !hasSelected);
        jQuery('#btnUnblockSelected').prop('disabled', !hasSelected);
    }

    jQuery('#spCalendarGrid').on('click', '.sp-cal-day', function() {
        var status = jQuery(this).data('status');
        if (status === 'booked') return; // Can't toggle trip bookings

        var date = jQuery(this).data('date');
        var idx = selectedDates.indexOf(date);
        if (idx === -1) {
            selectedDates.push(date);
        } else {
            selectedDates.splice(idx, 1);
        }
        renderCalendar();
    });

    jQuery('#calPrev').on('click', function() {
        calMonth--;
        if (calMonth < 1) { calMonth = 12; calYear--; }
        selectedDates = [];
        loadCalendar();
    });

    jQuery('#calNext').on('click', function() {
        calMonth++;
        if (calMonth > 12) { calMonth = 1; calYear++; }
        selectedDates = [];
        loadCalendar();
    });

    jQuery('#btnBlockSelected').on('click', function() {
        if (!selectedDates.length) return;
        ajaxPost({ sp_block_dates: 1, dates: selectedDates }, function(resp) {
            selectedDates = [];
            loadCalendar();
        });
    });

    jQuery('#btnUnblockSelected').on('click', function() {
        if (!selectedDates.length) return;
        ajaxPost({ sp_unblock_dates: 1, dates: selectedDates }, function(resp) {
            selectedDates = [];
            loadCalendar();
        });
    });

    jQuery('#btnSaveIcal').on('click', function() {
        var url = jQuery('#icalUrlInput').val().trim();
        ajaxPost({ sp_save_ical_url: 1, ical_url: url }, function(resp) {
            alert('iCal URL saved successfully.');
            location.reload();
        });
    });

    jQuery('#btnSyncIcal').on('click', function() {
        var btn = jQuery(this);
        btn.prop('disabled', true).text('Syncing...');
        ajaxPost({ sp_sync_ical_now: 1 }, function(resp) {
            btn.prop('disabled', false).text('Sync Now');
            jQuery('#icalSyncStatus').text('Last synced: just now');
            loadCalendar();
        }, function() {
            btn.prop('disabled', false).text('Sync Now');
        });
    });

    loadCalendar();
});
</script>
@endsection
