@extends('admin.layout')
@section('title', 'Service Providers - HCT')
@section('content')

@php
    $regions = \App\Models\Region::where('is_active', 1)->orderBy('name')->get();
    $serviceTypes = \App\Models\SystemList::ofType('service_type')->get();
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-people"></i> Service Providers</h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-success" id="addProviderBtn">
            <i class="bi bi-person-plus"></i> Add Provider
        </button>
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
        <button type="button" class="btn btn-sm btn-danger d-none" id="providersBulkPermDelete">
            <i class="bi bi-trash3 me-1"></i> Permanently delete <span id="providersBulkPermCount">0</span>
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

<div id="providersPagination" class="mt-3"></div>

{{-- Add Provider (manual) --}}
<div class="modal fade" id="addProviderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add Provider</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="addProviderAlert"></div>
                <form id="addProviderForm" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Provider type *</label>
                        <select class="form-select custom-select" id="ap_type">
                            <option value="hrp">HRP — Heco Regional Partner</option>
                            <option value="hlh">HLH — HECO Local Host</option>
                            <option value="osp" selected>OSP — Other Service Provider</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Name / Organization *</label>
                        <input type="text" class="form-control" id="ap_name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact person</label>
                        <input type="text" class="form-control" id="ap_contact">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" id="ap_email">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phone 1 *</label>
                        <input type="text" class="form-control" id="ap_phone1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phone 2</label>
                        <input type="text" class="form-control" id="ap_phone2">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Region *</label>
                        <select class="form-select custom-select" id="ap_region">
                            <option value="">Select region...</option>
                            @foreach($regions as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}, {{ $r->country }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" id="ap_address" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Services offered</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($serviceTypes as $st)
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="services_offered[]" value="{{ $st->name }}">
                                    <span class="form-check-label">{{ $st->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select custom-select" id="ap_status">
                            <option value="approved" selected>Approved (can log in)</option>
                            <option value="pending">Pending (under review)</option>
                        </select>
                    </div>
                </form>
                <div class="text-muted small mt-3">
                    <i class="bi bi-info-circle"></i>
                    An <strong>Approved</strong> provider gets a set-password email so they can sign in.
                    Bank details and detailed capabilities can be added afterwards from the provider's Edit page.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="addProviderSave">
                    <i class="bi bi-check-lg"></i> Create provider
                </button>
            </div>
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

function loadProviders(page) {
    ajaxPost({
        get_providers: 1,
        page: page || 1,
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
            // Checkbox cell — always rendered. The bulk action button is
            // mode-aware: non-removed selected → 'Remove'; removed selected
            // → 'Permanently delete'. Mixed selections show both buttons,
            // each operating only on the matching subset.
            html += '<td>';
            html += '<i class="bi bi-square provider-check" role="button" data-id="' + p.id + '" data-status="' + (p.status || '') + '"></i>';
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
        renderPagination('#providersPagination', resp, loadProviders);
    });
}

function refreshBulkBtn() {
    var $checked = $('.provider-check.provider-checked');
    var removedSelected = $checked.filter('[data-status="removed"]').length;
    var nonRemovedSelected = $checked.length - removedSelected;
    $('#providersBulkCount').text(nonRemovedSelected);
    $('#providersBulkPermCount').text(removedSelected);
    $('#providersBulkRemove').toggleClass('d-none', nonRemovedSelected === 0);
    $('#providersBulkPermDelete').toggleClass('d-none', removedSelected === 0);
}

$(function() {
    loadProviders();
    // Pick up a toast from the prior page (set via sessionStorage or
    // session()->with('flash') after a redirect from edit-provider).
    try {
        var clientFlash = sessionStorage.getItem('heco_flash');
        if (clientFlash) {
            sessionStorage.removeItem('heco_flash');
            showAlert(clientFlash, 'success');
        }
    } catch (e) {}
    @if(session('flash'))
        showAlert(@json(session('flash')), 'info');
    @endif
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

// Bulk remove — only acts on currently-non-removed selected rows.
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

// Bulk permanent delete — only acts on currently-removed selected rows.
// Each call goes through the per-provider blocker check (sp_payments,
// hosted experiences); per-row failures are reported in the final toast.
$('#providersBulkPermDelete').on('click', function() {
    var ids = $('.provider-check.provider-checked').filter(function() {
        return $(this).data('status') === 'removed';
    }).map(function() { return $(this).data('id'); }).get();
    if (!ids.length) return;
    Swal.fire({
        title: 'Permanently delete ' + ids.length + ' provider(s)?',
        html: '<strong>This cannot be undone.</strong><br>'
            + 'All pricing, availability blocks, and active bookings will be wiped from the database. '
            + 'Providers with payment records or hosted experiences will be skipped automatically.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, permanently delete',
        confirmButtonColor: '#b54a4a',
        focusCancel: true,
    }).then(function(res) {
        if (!res.isConfirmed) return;
        var $btn = $('#providersBulkPermDelete').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Deleting...');
        var done = 0, blocked = 0, failed = 0;
        var blockedMsgs = [];
        function next(i) {
            if (i >= ids.length) {
                $btn.prop('disabled', false).html('<i class="bi bi-trash3 me-1"></i> Permanently delete <span id="providersBulkPermCount">0</span>');
                var summary = done + ' deleted';
                if (blocked) summary += ', ' + blocked + ' blocked: ' + blockedMsgs.join('; ');
                if (failed) summary += ', ' + failed + ' failed';
                showAlert(summary, (blocked || failed) ? 'warning' : 'success');
                loadProviders();
                return;
            }
            ajaxPost({ permanently_delete_provider: 1, provider_id: ids[i] },
                function() { done++; next(i + 1); },
                function(xhr) {
                    var resp = xhr.responseJSON || {};
                    if (xhr.status === 422 && resp.blockers) {
                        blocked++;
                        blockedMsgs.push('#' + ids[i] + ': ' + (resp.error || 'blocked'));
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

// ── Add Provider (manual) ────────────────────────────────────────────────
var addProviderModal = new bootstrap.Modal(document.getElementById('addProviderModal'));

$('#addProviderBtn').on('click', function() {
    $('#addProviderForm')[0].reset();
    $('#addProviderAlert').empty();
    addProviderModal.show();
});

$('#addProviderSave').on('click', function() {
    var $btn = $(this);
    var apWarn = function(msg) {
        $('#addProviderAlert').html('<div class="alert alert-warning py-2 mb-2">' + msg + '</div>');
    };

    var email = ($('#ap_email').val() || '').trim();
    if (!$('#ap_name').val().trim()) return apWarn('Please enter a name / organization.');
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return apWarn('Please enter a valid email address.');
    if (!$('#ap_phone1').val().trim()) return apWarn('Please enter a primary phone number.');
    if (!$('#ap_region').val()) return apWarn('Please choose a region.');

    var data = {
        add_provider: 1,
        provider_type: $('#ap_type').val(),
        name: $('#ap_name').val().trim(),
        contact_person: $('#ap_contact').val().trim(),
        email: email,
        phone_1: $('#ap_phone1').val().trim(),
        phone_2: $('#ap_phone2').val().trim(),
        region_id: $('#ap_region').val(),
        address: $('#ap_address').val().trim(),
        status: $('#ap_status').val(),
        services_offered: $('#addProviderForm input[name="services_offered[]"]:checked')
            .map(function() { return this.value; }).get()
    };

    $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Creating...');
    ajaxPost(data, function() {
        $btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Create provider');
        addProviderModal.hide();
        showAlert('Provider created.', 'success');
        loadProviders();
    }, function(xhr) {
        $btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Create provider');
        var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Failed to create provider.') : 'Failed to create provider.';
        apWarn(msg);
    });
});
</script>
@endsection
