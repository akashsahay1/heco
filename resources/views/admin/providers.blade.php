@extends('admin.layout')
@section('title', 'Service Providers - HCT')
@section('content')

@php $regions = \App\Models\Region::where('is_active', 1)->orderBy('name')->get(); @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-people"></i> Service Providers</h5>
    <div class="d-flex gap-2">
        <div class="heco-filter-sm">
            <select class="form-select form-select-sm custom-select" id="providerTypeFilter">
                <option value="">All Types</option>
                <option value="hrp">HRP</option>
                <option value="hlh">HLH</option>
                <option value="osp">OSP</option>
            </select>
        </div>
        <div class="heco-filter-md">
            <select class="form-select form-select-sm custom-select" id="regionFilter">
                <option value="">All Regions</option>
                @foreach($regions as $r)
                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="heco-filter-sm">
            <select class="form-select form-select-sm custom-select" id="statusFilter">
                <option value="" selected>All Statuses</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
                <option value="rejected">Rejected</option>
                <option value="removed">Removed</option>
                <option value="all">All (incl. removed)</option>
            </select>
        </div>
        <input type="text" class="form-control form-control-sm heco-filter-lg" id="providerSearch">
        <button type="button" class="btn btn-sm btn-danger d-none" id="providersBulkRemove">
            <i class="bi bi-trash me-1"></i> Remove <span id="providersBulkCount">0</span>
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="w-check"><i class="bi bi-check2-square providers-selall" role="button" title="Select all on this page"></i></th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Region</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Last updated by</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="providersTable">
                    <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
function formatLastUpdated(p) {
    var role = p.last_updated_by_role;
    if (role === 'admin') return '<span class="badge bg-secondary">Admin</span>';
    if (role === 'provider') return '<span class="badge bg-info">Provider</span>';
    return '<span class="text-muted small">-</span>';
}

function loadProviders() {
    ajaxPost({
        get_providers: 1,
        provider_type: $('#providerTypeFilter').val(),
        region_id: $('#regionFilter').val(),
        status: $('#statusFilter').val(),
        search: $('#providerSearch').val()
    }, function(resp) {
        var html = '';
        var items = resp.data || [];
        if (!items.length) {
            html = '<tr><td colspan="8" class="text-center text-muted">No providers found</td></tr>';
        }
        items.forEach(function(p) {
            var typeBadge = '';
            if (p.provider_type === 'hrp') typeBadge = '<span class="badge bg-info">HRP</span>';
            else if (p.provider_type === 'hlh') typeBadge = '<span class="badge bg-success">HLH</span>';
            else if (p.provider_type === 'osp') typeBadge = '<span class="badge bg-warning text-dark">OSP</span>';
            else typeBadge = '<span class="badge bg-secondary">' + (p.provider_type || '-') + '</span>';

            var statusBadge = '';
            if (p.status === 'approved') statusBadge = '<span class="badge bg-success">Approved</span>';
            else if (p.status === 'pending') statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
            else if (p.status === 'rejected') statusBadge = '<span class="badge bg-danger">Rejected</span>';
            else if (p.status === 'removed') statusBadge = '<span class="badge bg-dark">Removed</span>';
            else statusBadge = '<span class="badge bg-secondary">' + (p.status || '-') + '</span>';

            html += '<tr data-id="' + p.id + '" data-status="' + (p.status || '') + '" class="' + (p.status === 'removed' ? 'text-muted' : '') + '">';
            // Checkbox cell — only show on non-removed rows (those are the
            // only ones that can be bulk-removed; already-removed rows are
            // excluded from selection).
            html += '<td>';
            if (p.status !== 'removed') {
                html += '<i class="bi bi-square provider-check" role="button" data-id="' + p.id + '"></i>';
            }
            html += '</td>';
            html += '<td>' + (p.name || '-') + '</td>';
            html += '<td>' + typeBadge + '</td>';
            html += '<td>' + (p.region ? p.region.name : '-') + '</td>';
            html += '<td>';
            if (p.phone_1) html += '<small><i class="bi bi-telephone"></i> ' + p.phone_1 + '</small><br>';
            if (p.email) html += '<small><i class="bi bi-envelope"></i> ' + p.email + '</small>';
            html += '</td>';
            html += '<td>' + statusBadge + '</td>';
            html += '<td>' + formatLastUpdated(p) + '</td>';
            html += '<td>';
            html += '<a class="btn btn-sm btn-outline-primary me-1" href="/providers/' + p.id + '"><i class="bi bi-eye"></i> View</a>';
            html += '<a class="btn btn-sm btn-outline-success" href="/providers/' + p.id + '/edit"><i class="bi bi-pencil"></i> Edit</a>';
            html += '</td>';
            html += '</tr>';
        });
        $('#providersTable').html(html);
        refreshBulkBtn();
    });
}

function refreshBulkBtn() {
    var checked = $('.provider-check.provider-checked').length;
    $('#providersBulkCount').text(checked);
    $('#providersBulkRemove').toggleClass('d-none', checked === 0);
}

$(function() {
    loadProviders();
});

$('#providerTypeFilter, #regionFilter, #statusFilter').on('change', function() { loadProviders(); });
$('#providerSearch').on('keyup', function() { loadProviders(); });

// Per-row checkbox toggle
$(document).on('click', '.provider-check', function() {
    $(this).toggleClass('provider-checked').toggleClass('bi-square').toggleClass('bi-check-square');
    refreshBulkBtn();
});

// Header "select all on this page" — toggles every non-removed row
$(document).on('click', '.providers-selall', function() {
    var anyUnchecked = $('.provider-check:not(.provider-checked)').length > 0;
    $('.provider-check').each(function() {
        $(this).toggleClass('provider-checked', anyUnchecked)
               .toggleClass('bi-square', !anyUnchecked)
               .toggleClass('bi-check-square', anyUnchecked);
    });
    refreshBulkBtn();
});

// Bulk remove — loops through the selected IDs and calls remove_provider
$('#providersBulkRemove').on('click', function() {
    var ids = $('.provider-check.provider-checked').map(function() { return $(this).data('id'); }).get();
    if (!ids.length) return;
    Swal.fire({
        title: 'Remove ' + ids.length + ' provider(s)?',
        text: 'Each provider will be archived (status: removed). Their user accounts will be deactivated, pricing inactivated, active room bookings released. Reversible — admin can restore individually later.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove ' + ids.length,
        confirmButtonColor: '#b54a4a'
    }).then(function(res) {
        if (!res.isConfirmed) return;
        var $btn = $('#providersBulkRemove').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Removing...');
        var done = 0, failed = 0;
        function next(i) {
            if (i >= ids.length) {
                $btn.prop('disabled', false).html('<i class="bi bi-trash me-1"></i> Remove <span id="providersBulkCount">0</span>');
                showAlert(done + ' removed' + (failed ? ', ' + failed + ' failed' : '.'), failed ? 'warning' : 'success');
                loadProviders();
                return;
            }
            ajaxPost({ remove_provider: 1, provider_id: ids[i] },
                function() { done++; next(i + 1); },
                function() { failed++; next(i + 1); }
            );
        }
        next(0);
    });
});
</script>
@endsection
