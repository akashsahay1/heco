@extends('admin.layout')
@section('title', 'Provider Applications - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-envelope-paper"></i> Provider Applications</h5>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm" id="appStatusFilter" style="width: 160px;">
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="">All</option>
        </select>
    </div>
</div>

<div class="row g-3" id="applicationsContainer">
    <div class="col-12 text-center text-muted py-4">Loading...</div>
</div>

@endsection

@section('js')
<script>
function loadApplications() {
    ajaxPost({
        get_provider_applications: 1,
        status: $('#appStatusFilter').val()
    }, function(resp) {
        var html = '';
        var items = resp.data || [];
        if (!items.length) {
            html = '<div class="col-12 text-center text-muted py-4">No applications found</div>';
            $('#applicationsContainer').html(html);
            return;
        }
        items.forEach(function(app) {
            var typeBadge = '';
            if (app.provider_type === 'hrp') typeBadge = '<span class="badge bg-info">HRP</span>';
            else if (app.provider_type === 'hlh') typeBadge = '<span class="badge bg-success">HLH</span>';
            else if (app.provider_type === 'osp') typeBadge = '<span class="badge bg-warning text-dark">OSP</span>';
            else typeBadge = '<span class="badge bg-secondary">' + (app.provider_type || '-') + '</span>';

            var services = [];
            try { services = typeof app.services_offered === 'string' ? JSON.parse(app.services_offered) : (app.services_offered || []); } catch(e) {}

            html += '<div class="col-md-6 col-lg-4">';
            html += '<div class="card h-100">';
            html += '<div class="card-body">';

            // Header: name + type badge
            html += '<div class="d-flex justify-content-between align-items-start mb-2">';
            html += '<h6 class="card-title mb-0">' + (app.name || 'Unnamed') + '</h6>';
            html += typeBadge;
            html += '</div>';

            // Contact info
            html += '<div class="small mb-2">';
            if (app.email) html += '<div><i class="bi bi-envelope text-muted"></i> ' + app.email + '</div>';
            if (app.phone_1) html += '<div><i class="bi bi-telephone text-muted"></i> ' + app.phone_1 + '</div>';
            html += '</div>';

            // Region + date
            html += '<div class="small mb-2">';
            if (app.region) html += '<span class="me-3"><i class="bi bi-geo-alt text-muted"></i> ' + (app.region.name || '-') + '</span>';
            html += '<span><i class="bi bi-calendar text-muted"></i> ' + (app.created_at ? app.created_at.substring(0, 10) : '-') + '</span>';
            html += '</div>';

            // Services offered
            if (services.length) {
                html += '<div class="mb-3">';
                html += '<small class="text-muted d-block mb-1">Services Offered:</small>';
                services.forEach(function(s) {
                    html += '<span class="badge bg-light text-dark border me-1 mb-1">' + s + '</span>';
                });
                html += '</div>';
            }

            // Status-dependent footer
            if (app.status === 'pending') {
                html += '<div class="d-flex gap-2">';
                html += '<button class="btn btn-sm btn-success flex-fill approve-app" data-id="' + app.id + '"><i class="bi bi-check-lg"></i> Approve</button>';
                html += '<button class="btn btn-sm btn-danger flex-fill reject-app" data-id="' + app.id + '"><i class="bi bi-x-lg"></i> Reject</button>';
                html += '</div>';
            } else if (app.status === 'approved') {
                html += '<div class="d-flex justify-content-between align-items-center">';
                html += '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Approved</span>';
                html += '<small class="text-muted">' + (app.approved_at ? app.approved_at.substring(0, 10) : '') + '</small>';
                html += '</div>';
            } else if (app.status === 'rejected') {
                html += '<div class="d-flex justify-content-between align-items-center">';
                html += '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rejected</span>';
                html += '<small class="text-muted">' + (app.approved_at ? app.approved_at.substring(0, 10) : '') + '</small>';
                html += '</div>';
            }

            html += '</div>';
            html += '</div>';
            html += '</div>';
        });
        $('#applicationsContainer').html(html);
    });
}

$(function() { loadApplications(); });

$('#appStatusFilter').on('change', function() { loadApplications(); });

$(document).on('click', '.approve-app', function() {
    var id = $(this).data('id');
    if (!confirm('Approve this provider application? A user account will be created for the provider.')) return;
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
    ajaxPost({ approve_provider: 1, provider_id: id }, function(resp) {
        loadApplications();
    });
});

$(document).on('click', '.reject-app', function() {
    var id = $(this).data('id');
    if (!confirm('Reject this provider application? This action can be reversed later.')) return;
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
    ajaxPost({ reject_provider: 1, provider_id: id }, function(resp) {
        loadApplications();
    });
});
</script>
@endsection
