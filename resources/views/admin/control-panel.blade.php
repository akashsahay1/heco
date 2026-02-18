@extends('admin.layout')
@section('title', 'Control Panel - HCT')
@section('content')

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-warning text-dark"><h6 class="mb-0"><i class="bi bi-bell"></i> Lead Reminders</h6></div>
            <div class="card-body" id="reminders" style="max-height: 400px; overflow-y: auto;">
                <p class="text-muted text-center small">Loading...</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-headset"></i> Support Requests</h6></div>
            <div class="card-body" id="cpSupport" style="max-height: 400px; overflow-y: auto;">
                <p class="text-muted text-center small">Loading...</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-link-45deg"></i> Content Management</h6></div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ url('/experiences') }}" class="btn btn-success btn-sm"><i class="bi bi-star me-1"></i> Manage Experiences</a>
                    <a href="{{ url('/experiences/create') }}" class="btn btn-outline-dark btn-sm"><i class="bi bi-plus me-1"></i> Create Experience</a>
                    <a href="{{ url('/regenerative-projects') }}" class="btn btn-success btn-sm"><i class="bi bi-tree me-1"></i> Manage RP</a>
                    <a href="{{ url('/regenerative-projects/create') }}" class="btn btn-outline-dark btn-sm"><i class="bi bi-plus me-1"></i> Create RP</a>
                    <a href="{{ url('/regions') }}" class="btn btn-success btn-sm"><i class="bi bi-globe-americas me-1"></i> Manage Regions</a>
                </div>
                <hr>
                <h6 class="small">AI Chat Interface</h6>
                <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;" id="hctAiChat"></div>
                <div class="input-group mt-2">
                    <input type="text" class="form-control form-control-sm" id="hctAiInput" placeholder="Ask AI about operations...">
                    <button class="btn btn-sm btn-success" id="hctAiSend"><i class="bi bi-send"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
jQuery(function() {
    ajaxPost({ get_lead_reminders: 1 }, function(resp) {
        var html = '';
        if (!resp.reminders.length) { html = '<p class="text-muted text-center small">No reminders due</p>'; }
        resp.reminders.forEach(function(r) {
            html += '<div class="border-bottom pb-2 mb-2">';
            html += '<strong class="small">' + (r.user ? r.user.full_name || r.user.email : '') + '</strong>';
            html += '<br><small class="text-muted">Trip: ' + (r.trip ? r.trip.trip_id : '') + '</small>';
            html += '<br><a href="{{ url('/leads') }}" class="btn btn-sm btn-outline-warning mt-1">Follow Up</a>';
            html += '</div>';
        });
        jQuery('#reminders').html(html);
    });

    ajaxPost({ get_support_requests: 1, unresolved_only: 1 }, function(resp) {
        var html = '';
        var items = resp.data || [];
        if (!items.length) { html = '<p class="text-muted text-center small">No unresolved requests</p>'; }
        items.forEach(function(r) {
            html += '<div class="border-bottom pb-2 mb-2">';
            html += '<strong class="small">' + (r.user ? r.user.full_name || r.user.email : '') + '</strong>';
            html += ' <span class="badge bg-' + (r.traveller_status === 'client' ? 'success' : 'warning text-dark') + '">' + r.traveller_status + '</span>';
            html += '<p class="small mb-1">' + r.message.substring(0, 120) + '</p>';
            html += '<button class="btn btn-sm btn-outline-success resolve-sr" data-id="' + r.id + '">Resolve</button>';
            html += '</div>';
        });
        jQuery('#cpSupport').html(html);
    });
});

jQuery(document).on('click', '.resolve-sr', function() {
    ajaxPost({ resolve_support_request: 1, id: jQuery(this).data('id') }, function() { location.reload(); });
});
</script>
@endsection
