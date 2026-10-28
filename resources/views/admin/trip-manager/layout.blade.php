@extends('admin.layout')
@section('title', 'Trip Manager - ' . $trip->trip_id)

@section('content')
<div class="container-fluid px-0">
    {{-- Top bar with trip info --}}
    <div class="bg-white border-bottom px-3 py-2 d-flex justify-content-between align-items-center">
        <div>
            <strong class="text-success">{{ $trip->trip_id }}</strong>
            <span class="text-muted ms-2">{{ $trip->trip_name ?? 'Unnamed Trip' }}</span>
            <span class="badge bg-{{ $trip->status === 'confirmed' ? 'success' : ($trip->status === 'running' ? 'primary' : 'warning text-dark') }} ms-2" id="tmStatusBadge">{{ ucfirst(str_replace('_', ' ', $trip->status)) }}</span>
        </div>
        <div class="d-flex gap-2">
            <span class="text-muted small">{{ $trip->user->full_name ?? $trip->user->email ?? '' }}</span>
            <a href="{{ url('/pdf/trip/' . $trip->id) }}" class="btn btn-sm btn-outline-success" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
            <a href="{{ url('/dashboard') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    {{-- Two tabs: Trip Info | Trip Itinerary --}}
    <ul class="nav nav-tabs px-3 pt-2" id="tmTabs">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tripInfoTab">Trip Info</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tripItineraryTab">Trip Itinerary</a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tripInfoTab">
            @include('admin.trip-manager.trip-info')
        </div>
        <div class="tab-pane fade" id="tripItineraryTab">
            @include('admin.trip-manager.trip-itinerary')
        </div>
    </div>
</div>

{{-- Edit Day Modal: the day's own name and notes. A custom day has nothing
     else to say what it is, so this is where HCT writes it. --}}
<div class="modal fade" id="editDayModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Edit Day</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editDayForm">
                    <input type="hidden" id="editDayId">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Day title</label>
                        <input type="text" class="form-control form-control-sm" id="editDayTitle" maxlength="255" placeholder="e.g. Village walk and local lunch">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Description</label>
                        <textarea class="form-control form-control-sm" id="editDayDescription" rows="4" maxlength="2000" placeholder="What happens on this day"></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check"></i> Save Day</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- New regenerative project, from a custom-day trip's Margins. Only what
     the project cannot be saved without; photos go on its own page later. --}}
@if ($trip->isHctBuilt())
<div class="modal fade" id="newRpProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">New Regenerative Project</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newRpProjectForm">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Name</label>
                        <input type="text" class="form-control form-control-sm" name="name" maxlength="255" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Action type</label>
                        <input type="text" class="form-control form-control-sm" name="action_type" maxlength="255" placeholder="e.g. Reforestation" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Short description</label>
                        <textarea class="form-control form-control-sm" name="short_description" rows="3" required></textarea>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Impact unit</label>
                            <input type="text" class="form-control form-control-sm" name="impact_unit" maxlength="255" placeholder="e.g. trees" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Cost per impact unit</label>
                            <input type="number" class="form-control form-control-sm" name="cost_per_impact_unit" min="0" step="0.01">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check"></i> Create and use for this trip</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Edit Service Modal --}}
<div class="modal fade" id="editServiceModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Edit Service</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editServiceForm">
                    <input type="hidden" id="editServiceId">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Service Type</label>
                        <select class="form-select form-select-sm custom-select" id="editServiceType">
                            <option value="accommodation">Accommodation</option>
                            <option value="transport">Transport</option>
                            <option value="guide">Guide</option>
                            <option value="activity">Activity</option>
                            <option value="meal">Meal</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Description</label>
                        <input type="text" class="form-control form-control-sm" id="editServiceDesc">
                    </div>
                    <div class="row g-1 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Cost</label>
                            <input type="number" class="form-control form-control-sm" id="editServiceCost" step="0.01" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Charged</label>
                            <select class="form-select form-select-sm custom-select" id="editServicePriceBasis">
                                <option value="flat">Whole group</option>
                                <option value="per_person">Per person</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Service Provider</label>
                        <select class="form-select form-select-sm custom-select" id="editServiceProvider">
                            <option value="">No Provider</option>
                            @if(isset($providers))
                                @foreach($providers as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->provider_type }})</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    {{-- Room picker - shown only when service_type=accommodation AND a provider is selected.
                         Populated live from get_room_availability for the day's date. --}}
                    <div class="mb-2 d-none" id="editServiceRoomBlock">
                        <label class="form-label small fw-bold">Room Category</label>
                        <select class="form-select form-select-sm custom-select" id="editServiceRoom">
                            <option value="">Select room category…</option>
                        </select>
                        <small class="text-muted" id="editServiceRoomHelp"></small>
                    </div>
                    <div class="mb-2 d-none" id="editServiceQtyBlock">
                        <label class="form-label small fw-bold">Number of rooms</label>
                        <input type="number" class="form-control form-control-sm" id="editServiceRoomQty" min="1" max="500" value="1">
                    </div>

                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check"></i> Update Service</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
var tripId = {{ $trip->id }};
var tripData = @json($trip);
// Heads a per-person service is multiplied by (children/infants at their
// Settings percentage). Comes from the same calculator that prices the trip.
var tripPeopleFactor = {{ json_encode((float) ($peopleFactor ?? 1)) }};

// ── Regions chosen by hand (Trip Info > Who Is On This Trip)
jQuery(document).on('click', '#tmRegionAdd', function() {
    var regionId = jQuery('#tmRegionPick').val();
    if (!regionId) return;
    ajaxPost({ add_trip_region: 1, trip_id: tripId, region_id: regionId }, function() { location.reload(); });
});
jQuery(document).on('click', '.tm-region-remove', function() {
    ajaxPost({ remove_trip_region: 1, trip_id: tripId, region_id: jQuery(this).data('region-id') }, function() { location.reload(); });
});

// ── Regenerative project of a custom-day trip (Trip Info > Margins)
function setTripRpProject(projectId) {
    ajaxPost({ update_trip_info: 1, trip_id: tripId, regenerative_project_id: projectId }, function() { location.reload(); }, function(xhr) {
        // Refused (a paid trip is locked): show the project that is saved, not the one picked.
        var $sel = jQuery('#tmRpProject').val(tripData.regenerative_project_id || '');
        if (window.buildCustomDropdown) buildCustomDropdown($sel[0]);
        showAlert(xhr.responseJSON ? (xhr.responseJSON.error || 'Could not set the project') : 'Could not set the project', 'danger');
    });
}
jQuery(document).on('change', '#tmRpProject', function() {
    var val = jQuery(this).val();
    if (val === 'new') {
        jQuery(this).val(tripData.regenerative_project_id || '');
        if (window.buildCustomDropdown) buildCustomDropdown(this);
        jQuery('#newRpProjectModal').modal('show');
        return;
    }
    setTripRpProject(val);
});
jQuery(document).on('submit', '#newRpProjectForm', function(e) {
    e.preventDefault();
    var $submit = jQuery(this).find('[type=submit]').prop('disabled', true);
    var data = { save_regenerative_project: 1 };
    jQuery(this).serializeArray().forEach(function(f) { data[f.name] = f.value; });
    ajaxPost(data, function(resp) {
        setTripRpProject(resp.project.id);
    }, function(xhr) {
        $submit.prop('disabled', false);
        showAlert(xhr.responseJSON ? (xhr.responseJSON.error || 'Could not create the project') : 'Could not create the project', 'danger');
    });
});

/**
 * What a service line comes to for the whole party, before markup.
 */
function tmServiceGroupCost(s) {
    var cost = Number(s.cost) || 0;
    return s.price_basis === 'per_person' ? cost * tripPeopleFactor : cost;
}

/**
 * The price of a service line as HCT should read it: a per-person line shows
 * its working ("₹1,500 × 4 = ₹6,000"), a flat one just the figure.
 */
function tmServicePriceHtml(s) {
    var cost = Number(s.cost) || 0;
    if (cost <= 0) return '';
    var total = tmServiceGroupCost(s);
    var fmt = function(n) { return '&#8377;' + Number(n).toLocaleString('en-IN', { maximumFractionDigits: 2 }); };
    if (s.price_basis === 'per_person') {
        var pax = Number(tripPeopleFactor.toFixed(2));
        return '<span class="text-muted">' + fmt(cost) + ' &times; ' + pax + ' pax = </span>' + fmt(total);
    }
    return fmt(total);
}

// ==========================================
// TRIP INFO TAB
// ==========================================

function tmIsoFromDate(d) {
    var pad = function(n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
}

// Air Datepickers for Start Date / End Date / Add-Payment date (no native type=date).
function tmInitDatepicker(displayId, hiddenId) {
    var $disp = $('#' + displayId);
    if (!$disp.length) return;
    new AirDatepicker('#' + displayId, {
        locale: window.airDatepickerEn,
        dateFormat: 'dd-MM-yyyy',
        autoClose: true,
        position: 'bottom left',
        onSelect: function(o) {
            $('#' + hiddenId).val(o.date ? tmIsoFromDate(o.date) : '');
        }
    });
}
$(function() {
    tmInitDatepicker('startDateDisplay', 'startDateInput');
    tmInitDatepicker('endDateDisplay', 'endDateInput');
    tmInitDatepicker('addPaymentDateDisplay', 'addPaymentDateInput');
});

/**
 * A day's date, as somebody reads it.
 *
 * trip_days.date is cast to a date, so it reaches this screen as
 * "2025-10-18T00:00:00.000000Z" and that whole string was printed beside the
 * day number. Only the date part is taken, so no timezone can walk it onto the
 * day before.
 */
/**
 * What a kind of day is called, in the words the pickers use.
 *
 * day_type is stored as one word - rest, travel, free - and those words were
 * printed onto the screen as they are.
 */
function tmDayTypeLabel(type) {
    var labels = {
        rest: 'Rest & Relax', travel: 'Travel Day', free: 'Explore Nearby',
        activity: 'Activity Day', arrival: 'Arrival Day', departure: 'Departure Day',
        custom: 'Custom Day'
    };
    return labels[type] || type;
}

// The kinds of day HCT can pick on the timeline, in the order shown. A custom
// day is one written by hand: its own title, its own activities and prices.
var tmDayTypeOptions = [
    ['rest', 'Rest / travel day'],
    ['activity', 'Activity day'],
    ['free', 'Free day'],
    ['custom', 'Custom day']
];

// Text safe to put inside a double-quoted HTML attribute.
function tmAttr(v) {
    return String(v == null ? '' : v)
        .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function tmDayDate(raw) {
    var iso = String(raw).slice(0, 10);
    var parts = iso.split('-');
    if (parts.length !== 3) return raw;
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var m = parseInt(parts[1], 10) - 1;
    if (!months[m]) return raw;
    return parseInt(parts[2], 10) + ' ' + months[m] + ' ' + parts[0];
}

$('#tripInfoForm').on('submit', function(e) {
    e.preventDefault();
    var data = { update_trip_info: 1, trip_id: tripId };
    // The two payment boxes live inside this form; their fields are theirs.
    $(this).find('[name]').not('#addPaymentForm [name], #addSpPaymentForm [name]').each(function() {
        data[$(this).attr('name')] = $(this).val();
    });
    ajaxPost(data, function(resp) {
        showAlert('Trip info updated!');
        // Moving the dates can leave a night unreserved or carry an experience
        // into a month it does not run in. Both used to be returned and thrown
        // away here, so the save looked clean either way.
        var notes = [];
        if (resp && resp.rooms_warning) notes.push(resp.rooms_warning);
        if (resp && resp.season_warning) notes.push(resp.season_warning);
        if (notes.length && window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'Saved, with something to look at',
                text: notes.join(' '),
                confirmButtonColor: '#79a09f'
            });
        } else if (notes.length) {
            showAlert(notes.join(' '), 'warning');
        }
    });
});

$('#btnSaveNotes').on('click', function() {
    ajaxPost({
        update_trip_info: 1,
        trip_id: tripId,
        general_notes: $('#generalNotes').val()
    }, function() {
        showAlert('Notes saved!');
    });
});

$('#addPaymentBtn').on('click', function() {
    var $btn = $(this).prop('disabled', true);
    var data = { add_traveller_payment: 1, trip_id: tripId };
    $('#addPaymentForm').find('[name]').each(function() {
        data[$(this).attr('name')] = $(this).val();
    });
    ajaxPost(data, function(resp) {
        $btn.prop('disabled', false);
        loadTravellerPayments();
        $('#addPaymentForm').find('input[name=amount], input[name=notes]').val('');
        $('#addPaymentCollapse').collapse('hide');

        // A payment can confirm the trip. Keep the status box in step, or the
        // next Save on this tab would undo the confirmation.
        var $status = $('#tripInfoForm select[name=status]');
        if (resp && resp.trip_status && $status.val() !== resp.trip_status) {
            $status.val(resp.trip_status);
            if (window.buildCustomDropdown) buildCustomDropdown($status[0]);
            // And the badge beside the trip id, coloured as the server draws it.
            var badge = resp.trip_status === 'confirmed' ? 'success' : (resp.trip_status === 'running' ? 'primary' : 'warning text-dark');
            $('#tmStatusBadge').attr('class', 'badge bg-' + badge + ' ms-2')
                .text(resp.trip_status.charAt(0).toUpperCase() + resp.trip_status.slice(1).replace(/_/g, ' '));
        }

        if (resp && resp.rooms_warning) {
            Swal.fire({ text: resp.rooms_warning, icon: 'warning' });
        } else {
            showAlert('Payment recorded!');
        }
    }, function(xhr) {
        $btn.prop('disabled', false);
        var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Could not record the payment') : 'Could not record the payment';
        showAlert(msg, 'danger');
    });
});

function loadTravellerPayments() {
    ajaxPost({ get_traveller_payment_history: 1, trip_id: tripId }, function(resp) {
        var html = '';
        var totalPaid = 0;
        if (resp.payments && resp.payments.length) {
            resp.payments.forEach(function(p) {
                var statusClass = 'secondary';
                if (p.payment_status === 'paid') { statusClass = 'success'; totalPaid += parseFloat(p.amount) || 0; }
                else if (p.payment_status === 'pending') statusClass = 'warning';
                else if (p.payment_status === 'failed') statusClass = 'danger';
                html += '<tr>';
                html += '<td class="small">' + (p.payment_date || '-') + '</td>';
                html += '<td class="text-end fw-bold small">\u20B9' + Number(p.amount).toLocaleString('en-IN') + '</td>';
                html += '<td class="small">' + (p.mode || '-') + '</td>';
                html += '<td class="small"><span class="badge bg-' + statusClass + '">' + (p.payment_status || '-') + '</span></td>';
                html += '<td class="small">' + (p.recorder ? p.recorder.full_name : '-') + '</td>';
                html += '</tr>';
            });
        }
        if (!html) {
            html = '<tr><td colspan="5" class="text-muted text-center">No payments recorded</td></tr>';
        }
        $('#paymentHistory').html(html);
        var finalPrice = parseFloat(tripData.final_price) || 0;
        var balance = finalPrice - totalPaid;
        // Two decimals, the way Final Price beside them reads.
        var money = { minimumFractionDigits: 2, maximumFractionDigits: 2 };
        $('#totalPaidDisplay').html('\u20B9' + totalPaid.toLocaleString('en-IN', money));
        $('#balanceDueDisplay')
            .html('\u20B9' + balance.toLocaleString('en-IN', money))
            .toggleClass('text-danger', balance > 0)
            .toggleClass('text-success', balance <= 0);
    });
}
loadTravellerPayments();

$('#addSpPaymentBtn').on('click', function() {
    var data = { create_sp_payment: 1, trip_id: tripId };
    $('#addSpPaymentForm').find('[name]').each(function() {
        data[$(this).attr('name')] = $(this).val();
    });
    ajaxPost(data, function(resp) {
        // The server sends a message when it re-stated a payable this provider
        // already had instead of adding one. That is not what HCT just asked
        // for, so it waits to be read: the toast would be wiped by the reload.
        if (resp && resp.message) {
            Swal.fire({ text: resp.message, icon: 'info' }).then(function() { location.reload(); });
            return;
        }
        showAlert('SP payment created!');
        location.reload();
    }, function(xhr) {
        var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Could not create SP payment') : 'Could not create SP payment';
        showAlert(msg, 'danger');
    });
});

// The day's own title and notes.
$(document).on('click', '.btn-edit-day', function(e) {
    e.stopPropagation();
    var $btn = $(this);
    $('#editDayId').val($btn.data('id'));
    $('#editDayTitle').val($btn.data('title') || '');
    $('#editDayDescription').val($btn.data('description') || '');
    $('#editDayModal').modal('show');
});

$('#editDayForm').on('submit', function(e) {
    e.preventDefault();
    var $submit = $(this).find('[type=submit]').prop('disabled', true);
    ajaxPost({
        update_trip_day: 1,
        day_id: $('#editDayId').val(),
        title: $('#editDayTitle').val(),
        description: $('#editDayDescription').val()
    }, function() {
        $submit.prop('disabled', false);
        $('#editDayModal').modal('hide');
        loadItinerary();
        showAlert('Day updated.');
    }, function(xhr) {
        $submit.prop('disabled', false);
        var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Could not save the day') : 'Could not save the day';
        showAlert(msg, 'danger');
    });
});

$('#editDayModal').on('hidden.bs.modal', function() {
    if ($(this).data('reload-on-close')) {
        $(this).data('reload-on-close', false);
        location.reload();
    }
});

// Changing a day's type changes the price, so the panel is re-read.
$(document).on('change', '.day-type-pick', function() {
    var $sel = $(this).prop('disabled', true);
    var dayId = $sel.data('id');
    var newType = $sel.val();
    ajaxPost({ update_trip_day: 1, day_id: dayId, day_type: newType }, function() {
        showAlert('Day updated.');
        // A custom day has no name until HCT gives it one, so the editor
        // opens straight away. The page reloads once it closes, the same
        // reload every other type change does to refresh the price panel.
        if (newType === 'custom') {
            var $edit = $('.btn-edit-day[data-id="' + dayId + '"]');
            $('#editDayId').val(dayId);
            $('#editDayTitle').val($edit.data('title') || '');
            $('#editDayDescription').val($edit.data('description') || '');
            $('#editDayModal').data('reload-on-close', true).modal('show');
            return;
        }
        location.reload();
    }, function(xhr) {
        $sel.prop('disabled', false);
        var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Could not change the day') : 'Could not change the day';
        showAlert(msg, 'danger');
    });
});

$('#btnRecalc').on('click', function() {
    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Calculating...');
    ajaxPost({ recalculate_trip_cost: 1, trip_id: tripId }, function(resp) {
        showAlert('Costs recalculated!');
        $btn.prop('disabled', false).html('<i class="bi bi-calculator"></i> Recalculate');
        location.reload();
    }, function() {
        $btn.prop('disabled', false).html('<i class="bi bi-calculator"></i> Recalculate');
    });
});

// ==========================================
// ITINERARY TAB
// ==========================================

var selectedDayId = null;

function loadItinerary() {
    ajaxPost({ get_trip_itinerary: 1, trip_id: tripId }, function(resp) {
        var t = resp.trip;
        var html = '';
        var daySelectHtml = '<option value="">-- Select Day --</option>';

        if (t.trip_days && t.trip_days.length) {
            t.trip_days.forEach(function(day) {
                var isLocked = day.is_locked;
                daySelectHtml += '<option value="' + day.id + '">Day ' + day.day_number + (day.title ? ' - ' + day.title : '') + '</option>';

                html += '<div class="day-block card mb-2 ' + (isLocked ? 'locked' : '') + (selectedDayId == day.id ? ' selected' : '') + '" data-day-id="' + day.id + '" data-day-date="' + (day.date || '') + '">';
                html += '<div class="card-header d-flex justify-content-between align-items-center bg-light py-1 px-2">';
                html += '<div class="d-flex align-items-center gap-2">';
                if (isLocked) html += '<i class="bi bi-lock-fill text-success" title="Locked by experience"></i>';
                html += '<strong class="small">Day ' + day.day_number;
                if (day.date) html += ' <span class="text-muted fw-normal">(' + tmDayDate(day.date) + ')</span>';
                html += '</strong>';
                if (day.title) html += '<span class="text-muted small ms-1">- ' + day.title + '</span>';
                if (day.added_by === 'traveller') html += '<span class="badge bg-info text-white ms-2 badge-xs">Added by Traveller</span>';
                html += '</div>';
                html += '<div class="d-flex gap-1 align-items-center">';
                // What a day with nothing on it is charged at comes from its
                // type, and until now there was no way to say. Every empty day
                // was billed at the activity rate whether it was a trek day or
                // the morning somebody flew home.
                if (!isLocked) {
                    html += '<select class="form-select form-select-sm day-type-pick" data-id="' + day.id + '" style="width:auto" title="What kind of day this is. It sets what an empty day is charged.">';
                    tmDayTypeOptions.forEach(function(o) {
                        html += '<option value="' + o[0] + '"' + ((day.day_type || 'rest') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>';
                    });
                    html += '</select>';
                    html += '<button class="btn btn-sm btn-outline-secondary btn-edit-day" data-id="' + day.id + '" data-title="' + tmAttr(day.title) + '" data-description="' + tmAttr(day.description) + '" title="Edit day title and notes"><i class="bi bi-pencil"></i></button>';
                }
                html += '<button class="btn btn-sm btn-outline-secondary btn-move-day-up" data-id="' + day.id + '" title="Move up"><i class="bi bi-arrow-up"></i></button>';
                html += '<button class="btn btn-sm btn-outline-secondary btn-move-day-down" data-id="' + day.id + '" title="Move down"><i class="bi bi-arrow-down"></i></button>';
                if (!isLocked) html += '<button class="btn btn-sm btn-outline-danger btn-remove-day" data-id="' + day.id + '"><i class="bi bi-trash"></i></button>';
                html += '</div>';
                html += '</div><div class="card-body p-2">';

                if (day.experiences && day.experiences.length) {
                    day.experiences.forEach(function(de) {
                        var exp = de.experience || {};
                        html += '<div class="d-flex align-items-center p-1 mb-1 bg-success bg-opacity-10 rounded">';
                        html += '<i class="bi bi-star-fill text-success me-2"></i>';
                        html += '<span class="small flex-fill">' + (exp.name || 'Experience');
                        // A listing on this itinerary that is no longer live.
                        // It happens when its host is removed: the experience is
                        // kept so the trip keeps its record, but nobody can
                        // deliver it any more. Saying only "not active" leaves
                        // HCT wondering what to do, so it says what to do.
                        if (exp.id && !exp.is_active) {
                            html += '<span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-2">Not active</span>';
                            html += '<div class="text-danger" style="font-size:.72rem">'
                                + (exp.hlh_id
                                    ? 'This experience is not active. Publish it again, or replace it in this itinerary.'
                                    : 'This experience is not active - its host was removed. Assign a new HLH, or replace it in this itinerary.')
                                + '</div>';
                        }
                        html += '</span>';
                        if (de.start_time) html += '<small class="text-muted me-2">' + de.start_time + '</small>';
                        if (de.total_cost > 0) html += '<small class="text-success me-2">&#8377;' + Number(de.total_cost).toLocaleString('en-IN') + '</small>';
                        if (!isLocked) html += '<button class="btn btn-sm btn-outline-danger btn-rm-day-exp" data-id="' + de.id + '"><i class="bi bi-x"></i></button>';
                        html += '</div>';
                    });
                }

                if (day.services && day.services.length) {
                    day.services.forEach(function(s) {
                        html += '<div class="d-flex align-items-center p-1 small">';
                        html += '<i class="bi bi-' + getServiceIcon(s.service_type) + ' ' + (s.is_included ? 'text-success' : 'text-danger') + ' me-2"></i>';
                        html += '<span class="flex-fill">' + (s.description || s.service_type) + '</span>';
                        if (s.service_provider) html += '<small class="text-muted me-2">' + s.service_provider.name + '</small>';
                        if (s.cost > 0) html += '<small class="text-success">' + tmServicePriceHtml(s) + '</small>';
                        html += '</div>';
                    });
                }

                if ((!day.experiences || !day.experiences.length) && (!day.services || !day.services.length)) {
                    var dtMap = { arrival: 'bi-airplane', departure: 'bi-airplane', rest: 'bi-moon', travel: 'bi-signpost-split', free: 'bi-compass', activity: 'bi-lightning', custom: 'bi-pencil-square' };
                    var dtIcon = dtMap[day.day_type] || 'bi-calendar';
                    if (day.day_type && day.day_type !== 'activity') {
                        // The label, not the stored word. This printed day_type
                        // straight out, so a day added from this side - which
                        // sets no title - read "rest" on screen while the
                        // dropdown beside it called the same thing "Rest /
                        // travel day". The traveller's own Add Day writes a
                        // title and so looked right; the two doors disagreed.
                        html += '<div class="small text-center mb-1"><i class="bi ' + dtIcon + ' text-success me-1"></i><strong>' + (day.title || tmDayTypeLabel(day.day_type)) + '</strong></div>';
                        if (day.day_type === 'custom') {
                            html += '<p class="text-muted small text-center mb-0">Nothing is on this day yet, so it costs nothing. Click the day, then add its activities and prices under Add Service.</p>';
                        }
                    } else {
                        html += day.title
                            ? '<p class="text-muted small text-center mb-0">Nothing is on this day yet. "'
                                + day.title + '" is its name, not an experience on it. Click to add services.</p>'
                            : '<p class="text-muted small text-center mb-0">Empty day - click to add services</p>';
                    }
                }

                if (day.description) {
                    html += '<div class="small text-muted mt-1 pt-1"><i class="bi bi-chat-left-text me-1"></i>' + day.description + '</div>';
                }

                html += '</div></div>';
            });
        } else {
            html = '<div class="text-center py-5 text-muted">';
            html += '<i class="bi bi-calendar-x fs-1 d-block mb-2"></i>';
            html += 'No days in itinerary. Click <strong>+ Add Day</strong> to start building.';
            html += '</div>';
        }

        $('#itineraryTimeline').html(html);
        $('#targetDaySelect').html(daySelectHtml);
        if (window.buildCustomDropdown) buildCustomDropdown($('#targetDaySelect')[0]);

        if (selectedDayId) {
            selectDay(selectedDayId);
        }
    });
}

function getServiceIcon(type) {
    var icons = {
        accommodation: 'house-door',
        transport: 'car-front',
        guide: 'person-badge',
        activity: 'lightning',
        meal: 'cup-hot',
        permit: 'file-earmark-check',
        equipment: 'backpack'
    };
    return icons[type] || 'three-dots';
}

function selectDay(dayId) {
    selectedDayId = dayId;
    $('.day-block').removeClass('selected');
    $('.day-block[data-day-id="' + dayId + '"]').addClass('selected');
    // d-none is !important, so .show() alone cannot lift it.
    $('#dayServicesPanel').removeClass('d-none').show();
    $('#dayServicesPlaceholder').addClass('d-none');
    $('#serviceDayId').val(dayId);
    var dayLabel = $('.day-block[data-day-id="' + dayId + '"]').find('strong.small').first().text();
    $('#selectedDayLabel').text(dayLabel || 'Selected Day');
    loadDayServices(dayId);
}

function loadDayServices(dayId) {
    ajaxPost({ get_day_services: 1, day_id: dayId }, function(resp) {
        var html = '';
        if (resp.services && resp.services.length) {
            resp.services.forEach(function(s) {
                html += '<div class="service-row">';
                html += '<i class="bi bi-' + getServiceIcon(s.service_type) + ' ' + (s.is_included ? 'text-success' : 'text-danger') + '"></i>';
                html += '<div class="flex-fill">';
                html += '<div class="small fw-bold">' + (s.description || s.service_type) + '</div>';
                if (s.service_provider) html += '<div class="small text-muted">' + s.service_provider.name + '</div>';
                if (s.from_location || s.to_location) html += '<div class="small text-muted">' + (s.from_location || '') + (s.to_location ? ' &rarr; ' + s.to_location : '') + '</div>';
                html += '</div>';
                if (s.cost > 0) html += '<span class="small text-success fw-bold text-end">' + tmServicePriceHtml(s) + '</span>';
                html += '<div class="d-flex gap-1 ms-1">';
                var dayDate = $('.day-block[data-day-id="' + dayId + '"]').data('day-date') || '';
                html += '<button class="btn btn-sm btn-outline-secondary btn-edit-service" data-id="' + s.id + '" data-type="' + s.service_type + '" data-description="' + tmAttr(s.description) + '" data-cost="' + (s.cost || 0) + '" data-price-basis="' + (s.price_basis || 'flat') + '" data-sp-id="' + (s.service_provider_id || '') + '" data-sp-pricing-id="' + (s.sp_pricing_id || '') + '" data-room-qty="' + (s.room_quantity || '') + '" data-day-date="' + dayDate + '" title="Edit"><i class="bi bi-pencil"></i></button>';
                html += '<button class="btn btn-sm btn-outline-danger rm-service" data-id="' + s.id + '" title="Remove"><i class="bi bi-x"></i></button>';
                html += '</div>';
                html += '</div>';
            });
        } else {
            html = '<p class="text-muted small text-center py-2">No services for this day. Add one below.</p>';
        }
        $('#dayServicesList').html(html);
    });
}

$('a[href="#tripItineraryTab"]').on('shown.bs.tab', function() { loadItinerary(); });

// The tab being worked in survives a reload. Adding a day reloads the page,
// and it always came back on Trip Info, so HCT had to reopen the itinerary
// after every day they added. Kept per trip, for this browser tab only.
(function () {
    var key = 'tm-tab-' + tripId;
    $('#tmTabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        try { sessionStorage.setItem(key, $(this).attr('href')); } catch (e) {}
    });
    var saved = null;
    try { saved = sessionStorage.getItem(key); } catch (e) {}
    if (saved && saved !== '#tripInfoTab') {
        var link = $('#tmTabs a[href="' + saved + '"]')[0];
        if (link && window.bootstrap) bootstrap.Tab.getOrCreateInstance(link).show();
    }
})();

$('#searchExpBtn').on('click', function() {
    ajaxPost({
        search_experiences_for_trip: 1,
        trip_id: tripId,
        search: $('#searchExpInput').val(),
        region_id: $('#searchExpRegion').val(),
        type: $('#searchExpType').val()
    }, function(resp) {
        var html = '';
        if (resp.experiences && resp.experiences.length) {
            resp.experiences.forEach(function(e) {
                html += '<div class="border rounded p-2 mb-1">';
                html += '<div class="d-flex justify-content-between align-items-start">';
                html += '<div>';
                html += '<strong class="small">' + e.name + '</strong>';
                html += '<br><small class="text-muted">' + (e.region ? e.region.name : '') + ' | ' + (e.type || '') + '</small>';
                if (e.duration_hours) html += '<br><small class="text-muted"><i class="bi bi-clock"></i> ' + e.duration_hours + 'h</small>';
                if (e.base_cost_per_person > 0) html += ' <small class="text-success">&#8377;' + Number(e.base_cost_per_person).toLocaleString('en-IN') + '/pp</small>';
                html += '</div>';
                html += '<button class="btn btn-sm btn-success add-exp-to-day" data-id="' + e.id + '" title="Add to day"><i class="bi bi-plus"></i></button>';
                html += '</div></div>';
            });
        } else {
            html = '<p class="text-muted small text-center py-2">No experiences found</p>';
        }
        $('#expSearchResults').html(html);
    });
});

$('#searchExpInput').on('keypress', function(e) {
    if (e.which === 13) { e.preventDefault(); $('#searchExpBtn').click(); }
});

$(document).on('click', '.add-exp-to-day', function() {
    var expId = $(this).data('id');
    var dayId = $('#targetDaySelect').val();
    if (!dayId) { showAlert('Select a target day first', 'warning'); return; }
    ajaxPost({ add_experience_to_day: 1, trip_id: tripId, day_id: dayId, experience_id: expId }, function() {
        loadItinerary();
        showAlert('Experience added to day!');
    });
});

$('#btnAddTripDay').on('click', function() {
    ajaxPost({ add_trip_day: 1, trip_id: tripId }, function() {
        loadItinerary();
        showAlert('Day added!');
    });
});

// A custom day is added and opened for naming in one go, then selected so
// the services panel is ready for its activities.
$('#btnAddCustomDay').on('click', function() {
    ajaxPost({ add_trip_day: 1, trip_id: tripId, day_type: 'custom' }, function(resp) {
        var day = resp.day || {};
        loadItinerary();
        showAlert('Custom day added. Give it a title, then add its activities under Add Service.');
        if (day.id) {
            selectedDayId = day.id;
            $('#editDayId').val(day.id);
            $('#editDayTitle').val('');
            $('#editDayDescription').val('');
            $('#editDayModal').modal('show');
        }
    });
});

jQuery(document).on('click', '.btn-remove-day', function(e) {
    e.stopPropagation();
    var dayId = jQuery(this).data('id');
    confirmAction('Remove this day and all its experiences/services?', function() {
        ajaxPost({ remove_trip_day: 1, day_id: dayId }, function() {
            selectedDayId = null;
            jQuery('#dayServicesPanel').addClass('d-none').hide();
            jQuery('#dayServicesPlaceholder').removeClass('d-none');
            loadItinerary();
        });
    });
});

$(document).on('click', '.btn-rm-day-exp', function(e) {
    e.stopPropagation();
    ajaxPost({ remove_experience_from_day: 1, day_experience_id: $(this).data('id') }, function() {
        loadItinerary();
    });
});

$(document).on('click', '.btn-move-day-up, .btn-move-day-down', function(e) {
    e.stopPropagation();
    var dayIds = [];
    $('.day-block').each(function() { dayIds.push($(this).data('day-id')); });
    var dayId = $(this).data('id');
    var idx = dayIds.indexOf(dayId);
    if ($(this).hasClass('btn-move-day-up') && idx > 0) {
        dayIds.splice(idx, 1);
        dayIds.splice(idx - 1, 0, dayId);
    } else if ($(this).hasClass('btn-move-day-down') && idx < dayIds.length - 1) {
        dayIds.splice(idx, 1);
        dayIds.splice(idx + 1, 0, dayId);
    } else {
        return;
    }
    ajaxPost({ reorder_trip_days: 1, trip_id: tripId, order: dayIds }, function() { loadItinerary(); });
});

$(document).on('click', '.day-block', function(e) {
    if ($(e.target).closest('button').length) return;
    selectDay($(this).data('day-id'));
});

// What each Add Service box is for, said in the box for the type chosen.
var SERVICE_PLACEHOLDERS = {
    '':            ['Service name', 'From', 'To'],
    transport:     ['Service name, e.g. Jeep to the put-in', 'From, e.g. Dehradun airport', 'To, e.g. Rishikesh'],
    accommodation: ['Service name, e.g. Riverside camp, 1 night', 'Check-in place', 'Check-out place'],
    activity:      ['Service name, e.g. River rafting (16 km)', 'From (optional)', 'To (optional)'],
    guide:         ['Service name, e.g. Local guide, half day', 'From (optional)', 'To (optional)'],
    meal:          ['Service name, e.g. Welcome dinner', 'From (optional)', 'To (optional)'],
    other:         ['Service name, e.g. Camping and river permit', 'From (optional)', 'To (optional)']
};
function setServicePlaceholders() {
    var $form = $('#addServiceForm');
    var p = SERVICE_PLACEHOLDERS[$form.find('[name=service_type]').val()] || SERVICE_PLACEHOLDERS[''];
    $form.find('[name=description]').attr('placeholder', p[0]);
    $form.find('[name=from_location]').attr('placeholder', p[1]);
    $form.find('[name=to_location]').attr('placeholder', p[2]);
}
$(document).on('change', '#addServiceForm [name=service_type]', setServicePlaceholders);

$('#addServiceForm').on('submit', function(e) {
    e.preventDefault();
    var dayId = $('#serviceDayId').val();
    if (!dayId) { showAlert('Select a day first by clicking on it', 'warning'); return; }
    var data = { add_day_service: 1, day_id: dayId };
    $(this).find('[name]').each(function() { data[$(this).attr('name')] = $(this).val(); });
    ajaxPost(data, function(resp) {
        loadDayServices($('#serviceDayId').val());
        loadItinerary();
        $('#addServiceForm')[0].reset();
        if (window.buildCustomDropdown) $('#addServiceForm select.custom-select').each(function() { buildCustomDropdown(this); });
        setServicePlaceholders();
        $('#addServiceCollapse').collapse('hide');
        // The service was added either way. This only says when the night was
        // already covered by the experience, so the cost stacks.
        if (resp && resp.stacking_warning) {
            Swal.fire({
                icon: 'info',
                title: 'Added - and charged on top',
                text: resp.stacking_warning,
                confirmButtonColor: '#79a09f'
            });
            return;
        }
        showAlert('Service added!');
    });
});

$(document).on('click', '.btn-edit-service', function() {
    var $btn = $(this);
    $('#editServiceId').val($btn.data('id'));
    $('#editServiceType').val($btn.data('type'));
    $('#editServiceDesc').val($btn.data('description'));
    $('#editServiceCost').val($btn.data('cost'));
    $('#editServicePriceBasis').val($btn.data('price-basis') || 'flat');
    $('#editServiceProvider').val(String($btn.data('sp-id') || ''));
    $('#editServiceProvider').data('orig', String($btn.data('sp-id') || ''));
    // Stash the room link the row was saved with so the picker can preselect.
    $('#editServiceForm').data('orig-pricing-id', String($btn.data('sp-pricing-id') || ''));
    $('#editServiceForm').data('orig-qty', String($btn.data('room-qty') || ''));
    $('#editServiceForm').data('day-date', String($btn.data('day-date') || ''));
    $('#editServiceRoomQty').val($btn.data('room-qty') || 1);
    if (window.buildCustomDropdown) {
        buildCustomDropdown($('#editServiceType')[0]);
        buildCustomDropdown($('#editServicePriceBasis')[0]);
        buildCustomDropdown($('#editServiceProvider')[0]);
    }
    refreshEditServiceRoomPicker();
    $('#editServiceModal').modal('show');
});

// Reload the room-category picker whenever the type or SP changes, OR
// when the modal first opens. Only shown for accommodation + SP.
function refreshEditServiceRoomPicker() {
    var type = $('#editServiceType').val();
    var spId = $('#editServiceProvider').val();
    var $room = $('#editServiceRoom');
    var $qty = $('#editServiceRoomQty');
    var $roomBlock = $('#editServiceRoomBlock');
    var $qtyBlock = $('#editServiceQtyBlock');

    if (type !== 'accommodation' || !spId) {
        $roomBlock.addClass('d-none');
        $qtyBlock.addClass('d-none');
        return;
    }

    var dayDate = $('#editServiceForm').data('day-date') || '';
    var origPricingId = $('#editServiceForm').data('orig-pricing-id') || '';

    $roomBlock.removeClass('d-none');
    $qtyBlock.removeClass('d-none');
    $room.html('<option value="">Loading…</option>');
    $('#editServiceRoomHelp').text('');

    ajaxPost({
        get_room_availability: 1,
        service_provider_id: spId,
        start_date: dayDate || undefined,
        end_date: dayDate || undefined,
    }, function(resp) {
        var cats = resp.categories || [];
        if (!cats.length) {
            $room.html('<option value="">No room categories for this SP</option>');
            return;
        }
        var html = '<option value="">- no specific category -</option>';
        cats.forEach(function(c) {
            // When editing the same row, "available" should add back the qty
            // this row already holds so the option isn't shown as 0 free.
            var effectiveAvail = c.available + (String(c.sp_pricing_id) === String(origPricingId) ? (Number($qty.val()) || 0) : 0);
            var disabled = effectiveAvail === 0 ? ' disabled' : '';
            // rate_net is the partner's own price, sent to HCT only; c.rate is the
            // marked-up figure a traveller would be quoted.
            var shown = (c.rate_net !== undefined ? c.rate_net : c.rate);
            var label = c.room_category + ' - ₹' + Number(shown).toLocaleString('en-IN') + '/night · ' + effectiveAvail + ' of ' + c.total + ' available';
            if (c.meal_plan) label += ' (' + c.meal_plan + ')';
            html += '<option value="' + c.sp_pricing_id + '"' + disabled + '>' + label + '</option>';
        });
        $room.html(html);
        if (origPricingId) $room.val(origPricingId);
        if (window.buildCustomDropdown) buildCustomDropdown($room[0]);
        $('#editServiceRoomHelp').text('Live availability for ' + (dayDate || 'this day') + '.');
    }, function(xhr) {
        $room.html('<option value="">Could not load availability</option>');
        var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Lookup failed') : 'Lookup failed';
        $('#editServiceRoomHelp').text(msg);
    });
}
$(document).on('change', '#editServiceType, #editServiceProvider', refreshEditServiceRoomPicker);

$('#editServiceForm').on('submit', function(e) {
    e.preventDefault();
    var serviceId = $('#editServiceId').val();
    var newSp = String($('#editServiceProvider').val() || '');
    var origSp = String($('#editServiceProvider').data('orig') || '');

    function afterSave() {
        $('#editServiceModal').modal('hide');
        loadDayServices($('#serviceDayId').val());
        loadItinerary();
        showAlert('Service updated!');
    }

    var roomId = $('#editServiceRoom').val();
    var roomQty = $('#editServiceRoomQty').val();
    var serviceType = $('#editServiceType').val();
    var payload = {
        edit_day_service: 1,
        service_id: serviceId,
        service_type: serviceType,
        description: $('#editServiceDesc').val(),
        cost: $('#editServiceCost').val(),
        price_basis: $('#editServicePriceBasis').val() || 'flat',
    };
    if (serviceType === 'accommodation') {
        payload.sp_pricing_id = roomId || '';
        payload.room_quantity = roomId ? (roomQty || 1) : '';
    }

    ajaxPost(payload, function() {
        if (newSp !== origSp) {
            ajaxPost({
                change_day_service_provider: 1,
                service_id: serviceId,
                service_provider_id: newSp
            }, afterSave, function(xhr) {
                $('#editServiceModal').modal('hide');
                loadDayServices($('#serviceDayId').val());
                loadItinerary();
                showAlert(xhr.responseJSON ? (xhr.responseJSON.error || 'Provider change failed') : 'Provider change failed', 'danger');
            });
        } else {
            afterSave();
        }
    }, function(xhr) {
        var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Save failed') : 'Save failed';
        showAlert(msg, 'danger');
    });
});

jQuery(document).on('click', '.rm-service', function() {
    var serviceId = jQuery(this).data('id');
    confirmAction('Remove this service?', function() {
        ajaxPost({ remove_day_service: 1, service_id: serviceId }, function() {
            loadDayServices(jQuery('#serviceDayId').val());
            loadItinerary();
        });
    });
});

$(document).on('change', '.change-sp-select', function() {
    ajaxPost({
        change_day_service_provider: 1,
        service_id: $(this).data('service-id'),
        service_provider_id: $(this).val()
    }, function() {
        loadDayServices($('#serviceDayId').val());
        loadItinerary();
        showAlert('Provider updated!');
    });
});

jQuery('#btnAiRecalc').on('click', function() {
    var btn = jQuery(this);
    Swal.fire({
        title: 'AI Optimization',
        input: 'text',
        inputLabel: 'What would you like the AI to optimize?',
        inputValue: 'Optimize this itinerary for cost and experience balance',
        showCancelButton: true,
        confirmButtonColor: '#79a09f'
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
            ajaxPost({ request_ai_recalculation: 1, trip_id: tripId, instruction: result.value }, function(resp) {
                showAlert('The review is ready.');
                if (resp.response) {
                    // Written by a machine and read by a person. It used to be
                    // dropped into the page as it came, so an admin read "##"
                    // and "**" as themselves and the whole thing as one block.
                    // Escaped, so nothing in it can be taken for markup, then
                    // broken into paragraphs the way it was written.
                    var text = jQuery('<div>').text(resp.response).html();
                    var paras = text.split(/\n\s*\n/).map(function(p) {
                        return '<p class="mb-2">' + p.replace(/\n/g, '<br>') + '</p>';
                    }).join('');
                    jQuery('#aiResponse').html(
                        '<div class="alert alert-info small mt-2">'
                        + '<strong>What to check before confirming</strong>'
                        + '<div class="text-muted mb-2" style="font-size:0.85em">'
                        + 'This is only an opinion. Nothing on the trip has been changed.'
                        + '</div>' + paras + '</div>'
                    );
                }
                btn.prop('disabled', false).html('<i class="bi bi-robot"></i> Ask AI to Review');
                loadItinerary();
            }, function() {
                btn.prop('disabled', false).html('<i class="bi bi-robot"></i> Ask AI to Review');
            });
        }
    });
});
</script>
@endsection
