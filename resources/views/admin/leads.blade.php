@extends('admin.layout')
@section('title', 'Leads - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        Leads Management
        <span class="badge bg-secondary ms-2" title="Total in current view">{{ number_format($leads->total()) }}</span>
    </h5>
    {{-- Most enquiries reach HECO by phone or WhatsApp rather than through the
         portal, and until now there was no way to put one into the system. --}}
    <button type="button" class="btn btn-primary btn-sm" id="addLeadBtn">
        <i class="bi bi-plus-lg"></i> Add Lead
    </button>
</div>

{{-- Server-side filter card (Travelers-style). --}}
<form method="GET" action="{{ url('/leads') }}" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Stage</label>
                <select class="form-select form-select-sm custom-select" name="stage">
                    <option value=""          {{ $stage === ''          ? 'selected' : '' }}>All stages</option>
                    <option value="follow_up" {{ $stage === 'follow_up' ? 'selected' : '' }}>Follow Up</option>
                    <option value="won"       {{ $stage === 'won'       ? 'selected' : '' }}>Won</option>
                    <option value="lost"      {{ $stage === 'lost'      ? 'selected' : '' }}>Lost</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Search (traveller name / email / trip ID)</label>
                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Type to search...">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> Apply
                </button>
                @if($stage !== '' || $search !== '')
                    <a href="{{ url('/leads') }}" class="btn btn-outline-secondary btn-sm">
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
                        <th>Traveller</th>
                        <th>Trip</th>
                        <th>Stage</th>
                        <th>Enquiry Date</th>
                        <th>Last Contact</th>
                        <th>Assigned To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $l)
                        @php
                            $stageClass = match($l->stage) {
                                'won'  => 'success',
                                'lost' => 'danger',
                                default => 'warning text-dark',
                            };
                        @endphp
                        <tr>
                            {{-- A lead carries its own name and email now. An
                                 older one was filed by creating an account, so
                                 its details live there; travellerName() reads
                                 whichever is held. --}}
                            <td>
                                {{ $l->travellerName() }}
                                @if ($l->travellerEmail())
                                    <div class="small text-muted">{{ $l->travellerEmail() }}</div>
                                @endif
                            </td>
                            <td>
                                @if($l->trip)
                                    <a href="{{ url('/trip-manager/'.$l->trip_id) }}" target="_blank">{{ $l->trip->trip_id }}</a>
                                @else
                                    {{-- Filing an enquiry does not open a trip. One
                                         appears here when the traveller builds it. --}}
                                    <span class="small text-muted">No trip yet</span>
                                @endif
                            </td>
                            <td><span class="badge bg-{{ $stageClass }}">{{ str_replace('_', ' ', $l->stage) }}</span></td>
                            <td><small>{{ optional($l->enquiry_date)->format('Y-m-d') ?: '-' }}</small></td>
                            <td><small>{{ optional($l->last_interaction_date)->format('Y-m-d') ?: '-' }}</small></td>
                            <td>
                                @if($l->assignedHct)
                                    {{ $l->assignedHct->full_name }}
                                @else
                                    <em class="text-muted">Unassigned</em>
                                @endif
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary view-lead" data-id="{{ $l->id }}"><i class="bi bi-eye"></i></button>
                                @if($l->stage === 'follow_up')
                                    <button class="btn btn-sm btn-outline-success mark-won" data-id="{{ $l->id }}" title="Mark Won"><i class="bi bi-check"></i></button>
                                    <button class="btn btn-sm btn-outline-danger mark-lost" data-id="{{ $l->id }}" title="Mark Lost"><i class="bi bi-x"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No leads found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($leads->hasPages())
    <div class="mt-3 d-flex justify-content-center">
        {{ $leads->links() }}
    </div>
@endif

<div class="modal fade" id="addLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="addLeadError"></div>

                {{-- No traveller picker any more. A lead is an enquiry, keyed
                     by the email it came in on: no account is opened for it and
                     none has to exist. If that address already belongs to a
                     traveller, the handler ties the two together by itself.
                     Offering a picker beside the boxes only raised the question
                     of which one won. --}}
                <p class="small text-muted mb-3">
                    <i class="bi bi-info-circle"></i>
                    This files an enquiry so somebody can be rung back. It does not
                    open an account or a trip. The email is what matters: when that
                    person signs up with it, builds a journey and pays, this enquiry
                    is marked Won by itself.
                </p>

                <div class="row g-2 mb-3" id="newTravellerFields">
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="leadName">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control form-control-sm" id="leadEmail">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Phone</label>
                        <input type="text" class="form-control form-control-sm" id="leadMobile">
                    </div>
                </div>

                <hr>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted mb-1">Region</label>
                        <select class="form-select form-select-sm custom-select" id="leadRegion">
                            <option value="">Not said yet</option>
                            @foreach($regions as $r)
                                <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->country }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Adults</label>
                        <input type="number" class="form-control form-control-sm" id="leadAdults" value="2" min="1" max="60">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Children</label>
                        <input type="number" class="form-control form-control-sm" id="leadChildren" value="0" min="0" max="60">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">From</label>
                        <input type="text" class="form-control form-control-sm" id="leadStartDisplay" placeholder="dd-mm-yyyy" autocomplete="off">
                        <input type="hidden" id="leadStart">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">To</label>
                        <input type="text" class="form-control form-control-sm" id="leadEndDisplay" placeholder="dd-mm-yyyy" autocomplete="off">
                        <input type="hidden" id="leadEnd">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Came in by</label>
                        <select class="form-select form-select-sm custom-select" id="leadMode">
                            <option value="">Not recorded</option>
                            <option value="call">Call</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Assign to</label>
                        <select class="form-select form-select-sm custom-select" id="leadAssigned">
                            <option value="">Unassigned</option>
                            @foreach($hctUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-md-12">
                        <label class="form-label small text-muted mb-1">Notes</label>
                        <textarea class="form-control form-control-sm" id="newLeadNotes" rows="3" placeholder="What they asked for, in their own words."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveLeadBtn">Save Lead</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="leadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Lead Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="leadModalBody"></div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
var hctUsers = @json($hctUsers);

// Lead-detail modal stays AJAX (read+write inside the modal). The list
// itself is server-rendered; write actions reload the page to pick up
// the new state with the current filters preserved.
$(document).on('click', '.mark-won', function() {
    var id = $(this).data('id');
    confirmAction('Mark this lead as Won? This will confirm the trip.', function() {
        ajaxPost({ update_lead: 1, lead_id: id, stage: 'won' }, function() {
            showAlert('Lead marked as Won.', 'success');
            location.reload();
        });
    });
});
$(document).on('click', '.mark-lost', function() {
    var id = $(this).data('id');
    confirmAction('Mark this lead as Lost?', function() {
        ajaxPost({ update_lead: 1, lead_id: id, stage: 'lost' }, function() {
            showAlert('Lead marked as Lost.', 'info');
            location.reload();
        });
    });
});
// The regions the enquiry can name, for the edit form below.
var leadRegions = @json($regions->map(fn ($r) => ['id' => $r->id, 'name' => $r->name . ' (' . $r->country . ')'])->values());

function leadEsc(v) {
    return jQuery('<div>').text(v === null || v === undefined ? '' : String(v)).html();
}

// A stored moment, in the reader's own clock. The server keeps UTC; shown
// raw it read "2026-10-28T06:01:53.000000Z".
function leadWhen(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (isNaN(d)) return '';
    var p = function(n) { return String(n).padStart(2, '0'); };
    var h = d.getHours(), ap = h < 12 ? 'am' : 'pm';
    return p(d.getDate()) + '-' + p(d.getMonth() + 1) + '-' + d.getFullYear() + ', ' + p(h % 12 || 12) + ':' + p(d.getMinutes()) + ' ' + ap;
}

// A plain day for the datepicker's box, and back.
function leadDay(ymd) {
    if (!ymd) return '';
    var x = ymd.split('-');
    return x[2] + '-' + x[1] + '-' + x[0];
}
function leadIso(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

$(document).on('click', '.view-lead', function() {
    ajaxPost({ get_lead_history: 1, lead_id: $(this).data('id') }, function(resp) {
        var l = resp.lead, shown = resp.shown || {};
        // Once a trip stands on this address, the address is the traveller's.
        var emailLocked = !!l.trip_id;
        var html = '<div class="alert alert-danger py-2 small d-none" id="leadEditError"></div>';
        html += '<div class="row"><div class="col-md-6">';
        html += '<h6>Enquiry</h6>';
        html += '<p class="small text-muted mb-2">Stage: <strong>' + leadEsc(l.stage.replace('_', ' ')) + '</strong>'
            + ' &middot; Enquiry: ' + (leadWhen(l.enquiry_date) || '-')
            + ' &middot; Last contact: ' + (leadWhen(l.last_interaction_date) || 'Never') + '</p>';
        html += '<div class="row g-2 mb-2">'
            + '<div class="col-md-6"><label class="form-label small text-muted mb-1">Name <span class="text-danger">*</span></label>'
            + '<input type="text" class="form-control form-control-sm" id="editLeadName" value="' + leadEsc(shown.name === '-' ? '' : shown.name) + '"></div>'
            + '<div class="col-md-6"><label class="form-label small text-muted mb-1">Phone</label>'
            + '<input type="text" class="form-control form-control-sm" id="editLeadMobile" value="' + leadEsc(l.mobile) + '"></div>'
            + '<div class="col-12"><label class="form-label small text-muted mb-1">Email <span class="text-danger">*</span></label>'
            + '<input type="email" class="form-control form-control-sm" id="editLeadEmail" value="' + leadEsc(shown.email) + '"' + (emailLocked ? ' readonly' : '') + '>'
            + (emailLocked ? '<div class="form-text">This lead already has a trip, so the email is the traveller\'s. Change it on the Travelers page.</div>' : '')
            + '</div></div>';
        html += '<div class="row g-2 mb-2">'
            + '<div class="col-md-6"><label class="form-label small text-muted mb-1">Region</label>'
            + '<select class="form-select form-select-sm custom-select" id="editLeadRegion"><option value="">Not said yet</option>';
        leadRegions.forEach(function(r) {
            html += '<option value="' + r.id + '"' + (l.region_id == r.id ? ' selected' : '') + '>' + leadEsc(r.name) + '</option>';
        });
        html += '</select></div>'
            + '<div class="col-md-3"><label class="form-label small text-muted mb-1">Adults</label>'
            + '<input type="number" class="form-control form-control-sm" id="editLeadAdults" min="1" max="60" value="' + leadEsc(l.adults) + '"></div>'
            + '<div class="col-md-3"><label class="form-label small text-muted mb-1">Children</label>'
            + '<input type="number" class="form-control form-control-sm" id="editLeadChildren" min="0" max="60" value="' + leadEsc(l.children) + '"></div>'
            + '</div>';
        html += '<div class="row g-2 mb-2">'
            + '<div class="col-md-6"><label class="form-label small text-muted mb-1">From</label>'
            + '<input type="text" class="form-control form-control-sm" id="editLeadStartDisplay" placeholder="dd-mm-yyyy" autocomplete="off" value="' + leadDay(shown.start_date) + '">'
            + '<input type="hidden" id="editLeadStart" value="' + leadEsc(shown.start_date) + '"></div>'
            + '<div class="col-md-6"><label class="form-label small text-muted mb-1">To</label>'
            + '<input type="text" class="form-control form-control-sm" id="editLeadEndDisplay" placeholder="dd-mm-yyyy" autocomplete="off" value="' + leadDay(shown.end_date) + '">'
            + '<input type="hidden" id="editLeadEnd" value="' + leadEsc(shown.end_date) + '"></div>'
            + '</div>';
        html += '</div><div class="col-md-6">';
        html += '<h6>Follow-up</h6>';
        html += '<div class="mb-2"><label class="form-label small text-muted mb-1">Assigned HCT</label><select class="form-select form-select-sm custom-select" id="leadAssignedHct"><option value="">— Unassigned —</option>';
        hctUsers.forEach(function(u) {
            var sel = (l.assigned_hct_id == u.id) ? ' selected' : '';
            html += '<option value="' + u.id + '"' + sel + '>' + leadEsc(u.full_name || u.email) + '</option>';
        });
        html += '</select></div>';
        html += '<div class="mb-2"><label class="form-label small text-muted mb-1">Log Interaction</label><select class="form-select form-select-sm custom-select" id="leadInteraction"><option value="">— None —</option><option value="call">Call</option><option value="whatsapp">WhatsApp</option><option value="email">Email</option></select></div>';
        html += '<div class="mb-2"><label class="form-label small text-muted mb-1">Notes</label><textarea class="form-control form-control-sm" id="leadNotes" placeholder="Notes..." rows="4">' + leadEsc(l.notes) + '</textarea></div>';
        html += '<div class="d-flex gap-2">';
        html += '<button class="btn btn-sm btn-success" id="leadSaveBtn" onclick="updateLead(' + l.id + ')">Save</button>';
        // The journey for this enquiry, started from what is written above, so
        // the trip manager never types the name and email in a second time.
        if (l.trip_id) {
            html += '<a class="btn btn-sm btn-outline-primary" href="/trip-manager/' + l.trip_id + '" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Open Trip</a>';
        } else if (l.stage === 'follow_up') {
            html += '<button class="btn btn-sm btn-primary" id="leadTripBtn" onclick="createTripFromLead(' + l.id + ')"><i class="bi bi-plus-lg"></i> Create Trip</button>';
        }
        html += '</div>';
        html += '</div></div>';
        $('#leadModalBody').html(html);
        if (window.buildCustomDropdown) {
            buildCustomDropdown($('#editLeadRegion')[0]);
            buildCustomDropdown($('#leadAssignedHct')[0]);
            buildCustomDropdown($('#leadInteraction')[0]);
        }
        [['#editLeadStartDisplay', '#editLeadStart'], ['#editLeadEndDisplay', '#editLeadEnd']].forEach(function(pair) {
            new AirDatepicker(pair[0], {
                locale: window.airDatepickerEn,
                dateFormat: 'dd-MM-yyyy',
                autoClose: true,
                position: 'bottom left',
                selectedDates: $(pair[1]).val() ? [new Date($(pair[1]).val() + 'T00:00:00')] : [],
                onSelect: function(o) { $(pair[1]).val(o.date ? leadIso(o.date) : ''); }
            });
        });
        new bootstrap.Modal(jQuery('#leadModal')[0]).show();
    });
});

function updateLead(id, then) {
    var data = {
        update_lead: 1,
        lead_id: id,
        full_name: $('#editLeadName').val().trim(),
        email: $('#editLeadEmail').val().trim(),
        mobile: $('#editLeadMobile').val().trim(),
        region_id: $('#editLeadRegion').val(),
        adults: $('#editLeadAdults').val(),
        children: $('#editLeadChildren').val(),
        start_date: $('#editLeadStart').val(),
        end_date: $('#editLeadEnd').val(),
        notes: $('#leadNotes').val(),
        assigned_hct_id: $('#leadAssignedHct').val()
    };
    var mode = $('#leadInteraction').val();
    if (mode) data.interaction_mode = mode;
    var $btns = $('#leadSaveBtn, #leadTripBtn').prop('disabled', true);
    $('#leadEditError').addClass('d-none').text('');
    // Its own error line inside the popup, rather than the page-wide alert
    // that sits behind it.
    ajaxPost(data, function() {
        if (then) return then(data, $btns);
        bootstrap.Modal.getInstance(jQuery('#leadModal')[0]).hide();
        showAlert('Lead updated.', 'success');
        location.reload();
    }, function(xhr) {
        $btns.prop('disabled', false);
        leadEditFailed(xhr, 'The lead could not be saved.');
    });
}

function leadEditFailed(xhr, fallback) {
    var j = xhr.responseJSON || {};
    var msg = j.error || (j.errors ? Object.values(j.errors)[0][0] : j.message) || fallback;
    $('#leadEditError').removeClass('d-none').text(msg);
}

// Saved first, so whatever was just corrected is what the trip is made from,
// then opened in the trip manager to build its days.
function createTripFromLead(id) {
    updateLead(id, function(saved, $btns) {
        ajaxPost({
            create_trip_for_traveller: 1,
            lead_id: id,
            full_name: saved.full_name,
            email: saved.email,
            adults: saved.adults,
            children: saved.children,
            start_date: saved.start_date,
            region_id: saved.region_id
        }, function(resp) {
            window.location = '/trip-manager/' + resp.trip_row_id;
        }, function(xhr) {
            $btns.prop('disabled', false);
            leadEditFailed(xhr, 'The trip could not be created.');
        });
    });
}
// Filing an enquiry that came in by phone or WhatsApp. It builds the same
// three rows the portal builds when a traveller does it themselves — the
// traveller, the trip, and the lead — so everything downstream works on it
// unchanged.
jQuery(function() {
    // Air Datepicker hands back a Date; the hidden box holds what the server
    // wants. Local parts, not toISOString(), which shifts a morning in India
    // back to the day before.
    function isoFromDate(d) {
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    new AirDatepicker('#leadStartDisplay', {
        locale: window.airDatepickerEn,
        dateFormat: 'dd-MM-yyyy',
        autoClose: true,
        position: 'bottom left',
        onSelect: function(o) { jQuery('#leadStart').val(o.date ? isoFromDate(o.date) : ''); }
    });
    new AirDatepicker('#leadEndDisplay', {
        locale: window.airDatepickerEn,
        dateFormat: 'dd-MM-yyyy',
        autoClose: true,
        position: 'bottom left',
        onSelect: function(o) { jQuery('#leadEnd').val(o.date ? isoFromDate(o.date) : ''); }
    });

    jQuery(document).on('click', '#addLeadBtn', function() {
        jQuery('#addLeadError').addClass('d-none').text('');
        new bootstrap.Modal(document.getElementById('addLeadModal')).show();
    });

    jQuery(document).on('click', '#saveLeadBtn', function() {
        var $btn = jQuery(this).prop('disabled', true);

        ajaxPost({
            create_lead: 1,
            full_name: jQuery('#leadName').val(),
            email: jQuery('#leadEmail').val(),
            mobile: jQuery('#leadMobile').val(),
            region_id: jQuery('#leadRegion').val(),
            adults: jQuery('#leadAdults').val(),
            children: jQuery('#leadChildren').val(),
            start_date: jQuery('#leadStart').val(),
            end_date: jQuery('#leadEnd').val(),
            interaction_mode: jQuery('#leadMode').val(),
            assigned_hct_id: jQuery('#leadAssigned').val(),
            notes: jQuery('#newLeadNotes').val()
        }, function(resp) {
            showAlert('Enquiry filed for ' + resp.traveller + '.', 'success');
            location.reload();
        }, function(xhr) {
            // Shown inside the form rather than as a banner: what is wrong is
            // one of the boxes they are looking at.
            var msg = 'Could not file that lead.';
            if (xhr && xhr.responseJSON) {
                var e = xhr.responseJSON.errors;
                msg = e ? jQuery.map(e, function(v) { return v[0]; }).join(' ') : (xhr.responseJSON.message || msg);
            }
            jQuery('#addLeadError').removeClass('d-none').text(msg);
            $btn.prop('disabled', false);
        });
    });
});
</script>
@endsection
