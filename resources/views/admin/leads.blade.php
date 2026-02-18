@extends('admin.layout')
@section('title', 'Leads - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Leads Management</h5>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm" id="leadStageFilter" style="width: 150px;">
            <option value="">All Stages</option>
            <option value="follow_up" selected>Follow Up</option>
            <option value="won">Won</option>
            <option value="lost">Lost</option>
        </select>
        <input type="text" class="form-control form-control-sm" id="leadSearch" placeholder="Search..." style="width: 200px;">
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Traveller</th><th>Trip</th><th>Stage</th><th>Enquiry Date</th><th>Last Contact</th><th>Assigned To</th><th>Actions</th></tr>
                </thead>
                <tbody id="leadsTable"><tr><td colspan="7" class="text-center text-muted">Loading...</td></tr></tbody>
            </table>
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
function loadLeads() {
    ajaxPost({
        get_leads: 1,
        stage: $('#leadStageFilter').val(),
        search: $('#leadSearch').val()
    }, function(resp) {
        var html = '';
        var items = resp.data || [];
        if (!items.length) { html = '<tr><td colspan="7" class="text-center text-muted">No leads found</td></tr>'; }
        items.forEach(function(l) {
            var stageClass = l.stage === 'won' ? 'success' : (l.stage === 'lost' ? 'danger' : 'warning text-dark');
            html += '<tr>';
            html += '<td>' + (l.user ? l.user.full_name || l.user.email : '') + '</td>';
            html += '<td><a href="/trip-manager/' + l.trip_id + '" target="_blank">' + (l.trip ? l.trip.trip_id : '') + '</a></td>';
            html += '<td><span class="badge bg-' + stageClass + '">' + l.stage.replace('_', ' ') + '</span></td>';
            html += '<td><small>' + (l.enquiry_date ? l.enquiry_date.substring(0, 10) : '') + '</small></td>';
            html += '<td><small>' + (l.last_interaction_date ? l.last_interaction_date.substring(0, 10) : '-') + '</small></td>';
            html += '<td>' + (l.assigned_hct ? l.assigned_hct.full_name : '<em class="text-muted">Unassigned</em>') + '</td>';
            html += '<td>';
            html += '<button class="btn btn-sm btn-outline-primary view-lead" data-id="' + l.id + '"><i class="bi bi-eye"></i></button> ';
            if (l.stage === 'follow_up') {
                html += '<button class="btn btn-sm btn-outline-success mark-won" data-id="' + l.id + '" title="Mark Won"><i class="bi bi-check"></i></button> ';
                html += '<button class="btn btn-sm btn-outline-danger mark-lost" data-id="' + l.id + '" title="Mark Lost"><i class="bi bi-x"></i></button>';
            }
            html += '</td></tr>';
        });
        $('#leadsTable').html(html);
    });
}

$(function() { loadLeads(); });
$('#leadStageFilter, #leadSearch').on('change keyup', function() { loadLeads(); });

$(document).on('click', '.mark-won', function() {
    if (!confirm('Mark this lead as Won? This will confirm the trip.')) return;
    ajaxPost({ update_lead: 1, lead_id: $(this).data('id'), stage: 'won' }, function() { loadLeads(); });
});
$(document).on('click', '.mark-lost', function() {
    if (!confirm('Mark this lead as Lost?')) return;
    ajaxPost({ update_lead: 1, lead_id: $(this).data('id'), stage: 'lost' }, function() { loadLeads(); });
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
        html += '<div class="mb-2"><select class="form-select form-select-sm" id="leadInteraction"><option value="">Log Interaction...</option><option value="call">Call</option><option value="whatsapp">WhatsApp</option><option value="email">Email</option></select></div>';
        html += '<div class="mb-2"><textarea class="form-control form-control-sm" id="leadNotes" rows="2" placeholder="Notes...">' + (l.notes || '') + '</textarea></div>';
        html += '<button class="btn btn-sm btn-success" onclick="updateLead(' + l.id + ')">Save</button>';
        html += '</div></div>';
        $('#leadModalBody').html(html);
        new bootstrap.Modal('#leadModal').show();
    });
});

function updateLead(id) {
    var data = { update_lead: 1, lead_id: id, notes: $('#leadNotes').val() };
    var mode = $('#leadInteraction').val();
    if (mode) data.interaction_mode = mode;
    ajaxPost(data, function() {
        bootstrap.Modal.getInstance('#leadModal').hide();
        loadLeads();
    });
}
</script>
@endsection
