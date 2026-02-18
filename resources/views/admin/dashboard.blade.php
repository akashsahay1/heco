@extends('admin.layout')
@section('title', 'HCT Dashboard')
@section('content')

<div class="row g-3 mb-4" id="statsRow">
    <div class="col-md-3 col-6">
        <div class="card stat-card"><div class="card-body">
            <div class="stat-value" id="statLeads">-</div>
            <div class="stat-label">Active Leads</div>
        </div></div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card"><div class="card-body">
            <div class="stat-value" id="statTrips">-</div>
            <div class="stat-label">Active Trips</div>
        </div></div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card"><div class="card-body">
            <div class="stat-value" id="statApplications">-</div>
            <div class="stat-label">Pending Applications</div>
        </div></div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card"><div class="card-body">
            <div class="stat-value" id="statRevenue">-</div>
            <div class="stat-label">Revenue This Month</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h6 class="mb-0">Unresolved Support</h6>
                <span class="badge bg-danger" id="supportCount">0</span>
            </div>
            <div class="card-body" id="supportList" style="max-height: 300px; overflow-y: auto;">
                <p class="text-muted text-center small">Loading...</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Upcoming Trips (30 days)</h6></div>
            <div class="card-body" id="upcomingList" style="max-height: 300px; overflow-y: auto;">
                <p class="text-muted text-center small">Loading...</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Quick Links</h6></div>
            <div class="card-body">
                <a href="{{ url('/experiences/create') }}" class="btn btn-outline-success btn-sm w-100 mb-2"><i class="bi bi-plus"></i> New Experience</a>
                <a href="{{ url('/regenerative-projects/create') }}" class="btn btn-outline-success btn-sm w-100 mb-2"><i class="bi bi-plus"></i> New Regenerative Project</a>
                <a href="{{ url('/provider-applications') }}" class="btn btn-outline-success btn-sm w-100 mb-2"><i class="bi bi-envelope-paper"></i> Review Applications</a>
                <a href="{{ url('/leads') }}" class="btn btn-outline-success btn-sm w-100"><i class="bi bi-funnel"></i> Manage Leads</a>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
$(function() {
    ajaxPost({ get_dashboard_stats: 1 }, function(resp) {
        var s = resp.stats;
        $('#statLeads').text(s.total_leads);
        $('#statTrips').text(s.active_trips);
        $('#statApplications').text(s.pending_applications);
        $('#statRevenue').text('₹' + Number(s.revenue_this_month).toLocaleString());
        $('#supportCount').text(s.unresolved_support);
    });

    ajaxPost({ get_support_requests: 1, unresolved_only: 1 }, function(resp) {
        var html = '';
        var items = resp.data || [];
        if (!items.length) { html = '<p class="text-muted text-center small">No unresolved requests</p>'; }
        items.forEach(function(r) {
            html += '<div class="border-bottom pb-2 mb-2"><strong class="small">' + (r.user ? r.user.full_name || r.user.email : 'Unknown') + '</strong>';
            html += '<span class="badge bg-' + (r.traveller_status === 'client' ? 'success' : 'warning text-dark') + ' ms-1">' + r.traveller_status + '</span>';
            html += '<p class="small mb-1">' + r.message.substring(0, 100) + '</p>';
            html += '<button class="btn btn-sm btn-outline-success resolve-btn" data-id="' + r.id + '">Resolve</button></div>';
        });
        $('#supportList').html(html);
    });
});

$(document).on('click', '.resolve-btn', function() {
    var btn = $(this);
    ajaxPost({ resolve_support_request: 1, id: btn.data('id') }, function() {
        btn.closest('.border-bottom').fadeOut();
    });
});
</script>
@endsection
