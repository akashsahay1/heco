@extends('admin.layout')
@section('title', 'Leads - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        Leads Management
        <span class="badge bg-secondary ms-2" title="Total in current view">{{ number_format($leads->total()) }}</span>
    </h5>
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
                            <td>{{ $l->user ? ($l->user->full_name ?: $l->user->email) : '-' }}</td>
                            <td>
                                @if($l->trip)
                                    <a href="{{ url('/trip-manager/'.$l->trip_id) }}" target="_blank">{{ $l->trip->trip_id }}</a>
                                @else
                                    -
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
$(document).on('click', '.view-lead', function() {
    ajaxPost({ get_lead_history: 1, lead_id: $(this).data('id') }, function(resp) {
        var l = resp.lead;
        var html = '<div class="row"><div class="col-md-6">';
        html += '<h6>Traveller: ' + (l.user ? l.user.full_name || l.user.email : '') + '</h6>';
        html += '<p>Stage: <strong>' + l.stage + '</strong></p>';
        html += '<p>Enquiry: ' + (l.enquiry_date || '') + '</p>';
        html += '<p>Last Contact: ' + (l.last_interaction_date || 'Never') + '</p>';
        html += '<p>Notes: ' + (l.notes || '-') + '</p>';
        html += '</div><div class="col-md-6">';
        html += '<h6>Update Lead</h6>';
        html += '<div class="mb-2"><label class="form-label small text-muted mb-1">Assigned HCT</label><select class="form-select form-select-sm custom-select" id="leadAssignedHct"><option value="">— Unassigned —</option>';
        hctUsers.forEach(function(u) {
            var sel = (l.assigned_hct_id == u.id) ? ' selected' : '';
            html += '<option value="' + u.id + '"' + sel + '>' + (u.full_name || u.email) + '</option>';
        });
        html += '</select></div>';
        html += '<div class="mb-2"><label class="form-label small text-muted mb-1">Log Interaction</label><select class="form-select form-select-sm custom-select" id="leadInteraction"><option value="">— None —</option><option value="call">Call</option><option value="whatsapp">WhatsApp</option><option value="email">Email</option></select></div>';
        html += '<div class="mb-2"><label class="form-label small text-muted mb-1">Notes</label><textarea class="form-control form-control-sm" id="leadNotes" rows="2">' + (l.notes || '') + '</textarea></div>';
        html += '<button class="btn btn-sm btn-success" onclick="updateLead(' + l.id + ')">Save</button>';
        html += '</div></div>';
        $('#leadModalBody').html(html);
        if (window.buildCustomDropdown) {
            buildCustomDropdown($('#leadAssignedHct')[0]);
            buildCustomDropdown($('#leadInteraction')[0]);
        }
        new bootstrap.Modal(jQuery('#leadModal')[0]).show();
    });
});

function updateLead(id) {
    var data = { update_lead: 1, lead_id: id, notes: $('#leadNotes').val(), assigned_hct_id: $('#leadAssignedHct').val() };
    var mode = $('#leadInteraction').val();
    if (mode) data.interaction_mode = mode;
    ajaxPost(data, function() {
        bootstrap.Modal.getInstance(jQuery('#leadModal')[0]).hide();
        showAlert('Lead updated.', 'success');
        location.reload();
    });
}
</script>
@endsection
