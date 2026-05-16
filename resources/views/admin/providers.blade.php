@extends('admin.layout')
@section('title', 'Service Providers - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-people"></i> Service Providers
        <span class="badge bg-secondary ms-2" title="Total in current view">{{ number_format($providers->total()) }}</span>
    </h5>
</div>

{{-- Server-side filter card — explicit Apply / Clear, full-page reload on
     submit. Bulk-action buttons sit alongside Apply so the admin can run a
     delete on the currently-filtered set without leaving the page. --}}
<form method="GET" action="{{ url('/providers') }}" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Type</label>
                <select class="form-select form-select-sm custom-select" name="provider_type">
                    <option value=""    {{ $providerType === ''    ? 'selected' : '' }}>All types</option>
                    <option value="hrp" {{ $providerType === 'hrp' ? 'selected' : '' }}>HRP</option>
                    <option value="hlh" {{ $providerType === 'hlh' ? 'selected' : '' }}>HLH</option>
                    <option value="osp" {{ $providerType === 'osp' ? 'selected' : '' }}>OSP</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Region</label>
                <select class="form-select form-select-sm custom-select" name="region_id">
                    <option value="">All regions</option>
                    @foreach($regions as $r)
                        <option value="{{ $r->id }}" {{ (string)$regionId === (string)$r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select form-select-sm custom-select" name="status">
                    <option value=""         {{ $status === ''         ? 'selected' : '' }}>All statuses</option>
                    <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="pending"  {{ $status === 'pending'  ? 'selected' : '' }}>Pending</option>
                    <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="removed"  {{ $status === 'removed'  ? 'selected' : '' }}>Removed</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Search (name / email / phone)</label>
                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Type to search...">
            </div>
            <div class="col-md-2 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> Apply
                </button>
                @if($status !== '' || $providerType !== '' || $regionId !== '' || $search !== '')
                    <a href="{{ url('/providers') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                @endif
            </div>
        </div>
    </div>
</form>

{{-- Bulk action toolbar — shows up only when at least one row is ticked. --}}
<div class="d-flex gap-2 mb-2 align-items-center">
    <button type="button" class="btn btn-sm btn-danger d-none" id="providersBulkRemove">
        <i class="bi bi-trash me-1"></i> Remove <span id="providersBulkCount">0</span>
    </button>
    <button type="button" class="btn btn-sm btn-danger d-none" id="providersBulkPermDelete">
        <i class="bi bi-trash3 me-1"></i> Permanently delete <span id="providersBulkPermCount">0</span>
    </button>
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
                <tbody>
                    @forelse($providers as $p)
                        @php
                            $typeBadge = match($p->provider_type) {
                                'hrp' => '<span class="badge bg-info">HRP</span>',
                                'hlh' => '<span class="badge bg-success">HLH</span>',
                                'osp' => '<span class="badge bg-warning text-dark">OSP</span>',
                                default => '<span class="badge bg-secondary">'.e($p->provider_type ?: '-').'</span>',
                            };
                            $statusBadge = match($p->status) {
                                'approved' => '<span class="badge bg-success">Approved</span>',
                                'pending'  => '<span class="badge bg-warning text-dark">Pending</span>',
                                'rejected' => '<span class="badge bg-danger">Rejected</span>',
                                'removed'  => '<span class="badge bg-dark">Removed</span>',
                                default    => '<span class="badge bg-secondary">'.e($p->status ?: '-').'</span>',
                            };
                            $lastUpdater = $p->last_updated_by_role;
                            if      ($lastUpdater === 'admin')    $lastBadge = '<span class="badge bg-secondary">Admin</span>';
                            elseif  ($lastUpdater === 'provider') $lastBadge = '<span class="badge bg-info">Provider</span>';
                            else                                  $lastBadge = '<span class="text-muted small">-</span>';
                        @endphp
                        <tr data-id="{{ $p->id }}" data-status="{{ $p->status }}" class="{{ $p->status === 'removed' ? 'text-muted' : '' }}">
                            <td>
                                <i class="bi bi-square provider-check" role="button" data-id="{{ $p->id }}" data-status="{{ $p->status }}"></i>
                            </td>
                            <td>{{ $p->name ?: '-' }}</td>
                            <td>{!! $typeBadge !!}</td>
                            <td>{{ $p->region ? $p->region->name : '-' }}</td>
                            <td>
                                @if($p->phone_1)<small><i class="bi bi-telephone"></i> {{ $p->phone_1 }}</small><br>@endif
                                @if($p->email)<small><i class="bi bi-envelope"></i> {{ $p->email }}</small>@endif
                            </td>
                            <td>{!! $statusBadge !!}</td>
                            <td>{!! $lastBadge !!}</td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary me-1" href="{{ url('/providers/'.$p->id) }}"><i class="bi bi-eye"></i> View</a>
                                <a class="btn btn-sm btn-outline-success" href="{{ url('/providers/'.$p->id.'/edit') }}"><i class="bi bi-pencil"></i> Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No providers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($providers->hasPages())
    <div class="mt-3 d-flex justify-content-center">
        {{ $providers->links() }}
    </div>
@endif

@endsection

@section('js')
<script>
function refreshBulkBtn() {
    var $checked = $('.provider-check.provider-checked');
    var removedSelected = $checked.filter('[data-status="removed"]').length;
    var nonRemovedSelected = $checked.length - removedSelected;
    $('#providersBulkCount').text(nonRemovedSelected);
    $('#providersBulkPermCount').text(removedSelected);
    $('#providersBulkRemove').toggleClass('d-none', nonRemovedSelected === 0);
    $('#providersBulkPermDelete').toggleClass('d-none', removedSelected === 0);
}

// Per-row checkbox toggle
$(document).on('click', '.provider-check', function() {
    $(this).toggleClass('provider-checked').toggleClass('bi-square').toggleClass('bi-check-square');
    refreshBulkBtn();
});

// Header "select all on this page"
$(document).on('click', '.providers-selall', function() {
    var anyUnchecked = $('.provider-check:not(.provider-checked)').length > 0;
    $('.provider-check').each(function() {
        $(this).toggleClass('provider-checked', anyUnchecked)
               .toggleClass('bi-square', !anyUnchecked)
               .toggleClass('bi-check-square', anyUnchecked);
    });
    refreshBulkBtn();
});

// Bulk remove — soft archive (status → removed)
$('#providersBulkRemove').on('click', function() {
    var ids = $('.provider-check.provider-checked').filter(function() {
        return $(this).data('status') !== 'removed';
    }).map(function() { return $(this).data('id'); }).get();
    if (!ids.length) return;
    Swal.fire({
        title: 'Remove ' + ids.length + ' provider(s)?',
        text: 'Each provider will be archived (status: removed). User accounts deactivated, pricing inactivated, active bookings released. Reversible.',
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
                showAlert(done + ' removed' + (failed ? ', ' + failed + ' failed' : '.'), failed ? 'warning' : 'success');
                location.reload();
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

// Bulk permanent delete — hard delete (status must already be 'removed').
// Per-row blockers (sp_payments) are reported in the final toast.
$('#providersBulkPermDelete').on('click', function() {
    var ids = $('.provider-check.provider-checked').filter(function() {
        return $(this).data('status') === 'removed';
    }).map(function() { return $(this).data('id'); }).get();
    if (!ids.length) return;
    Swal.fire({
        title: 'Permanently delete ' + ids.length + ' provider(s)?',
        html: '<strong>This cannot be undone.</strong><br>'
            + 'All pricing, availability, and bookings will be wiped. Hosted experiences auto-detached. '
            + 'Providers with payment records will be skipped automatically.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, permanently delete',
        confirmButtonColor: '#b54a4a',
        focusCancel: true,
    }).then(function(res) {
        if (!res.isConfirmed) return;
        var $btn = $('#providersBulkPermDelete').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Deleting...');
        var done = 0, blocked = 0, failed = 0;
        var msgs = [];
        function next(i) {
            if (i >= ids.length) {
                var summary = done + ' deleted';
                if (blocked) summary += ', ' + blocked + ' blocked: ' + msgs.join('; ');
                if (failed) summary += ', ' + failed + ' failed';
                showAlert(summary, (blocked || failed) ? 'warning' : 'success');
                location.reload();
                return;
            }
            ajaxPost({ permanently_delete_provider: 1, provider_id: ids[i] },
                function() { done++; next(i + 1); },
                function(xhr) {
                    var resp = xhr.responseJSON || {};
                    if (xhr.status === 422 && resp.blockers) {
                        blocked++;
                        msgs.push('#' + ids[i] + ': ' + (resp.error || 'blocked'));
                    } else {
                        failed++;
                    }
                    next(i + 1);
                }
            );
        }
        next(0);
    });
});
</script>
@endsection
