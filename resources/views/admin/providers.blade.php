@extends('admin.layout')
@section('title', 'Service Providers - HCT')
@section('content')

@php
    $regions = \App\Models\Region::where('is_active', 1)->orderBy('name')->get();
    // The same two lists signup asks from, so a provider added by hand is
    // described exactly as one who signed up.
    $experienceCategories = \App\Models\SystemList::ofType('experience_category')->get();
    $serviceCategories = \App\Models\SystemList::ofType('service_category')->get();
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-people"></i> Service Providers</h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-success" id="addProviderBtn">
            <i class="bi bi-person-plus"></i> Add Provider
        </button>
        <button type="button" class="btn btn-sm btn-outline-success" id="importProvidersBtn">
            <i class="bi bi-upload"></i> Import CSV
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
                @foreach(\App\Models\ServiceProvider::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <input type="text" class="form-control form-control-sm heco-filter-lg" id="providerSearch" placeholder="Search...">
        <button type="button" class="btn btn-sm btn-danger d-none" id="providersBulkRemove">
            <i class="bi bi-trash me-1"></i> Delete <span id="providersBulkCount">0</span>
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

{{-- Import providers from a CSV (HECO's feedback, 2026-10-28) --}}
<div class="modal fade" id="importProvidersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upload"></i> Import providers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ol class="small mb-3">
                    <li><a href="{{ route('hct.providers.import-template') }}"><i class="bi bi-download"></i> Download the template</a> and open it in Excel or Google Sheets.</li>
                    <li>One provider per row. Every column is required: <code>{{ implode(', ', \App\Services\ProviderImportService::COLUMNS) }}</code>.</li>
                    <li><strong>region</strong> is one of: {{ \App\Models\Region::where('is_active', true)->orderBy('name')->pluck('name')->join(', ') }}.</li>
                    <li><strong>service_type</strong> is one of: {{ implode(', ', app(\App\Services\ProviderImportService::class)->serviceTypes()) }} (several may be separated by commas).</li>
                    <li>Save or download it as <strong>CSV</strong> and choose it below.</li>
                </ol>
                <p class="small text-muted">They are added as approved Service Providers, ready to be put on a trip. No email is sent to them. A row already in HECO (same SP ID or email) is reported, not added twice.</p>
                <input type="file" class="form-control form-control-sm mb-2" id="importProvidersFile" accept=".csv,text/csv">
                <div id="importProvidersResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="importProvidersGo"><i class="bi bi-upload"></i> Import</button>
            </div>
        </div>
    </div>
</div>

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
                    <div class="col-12">
                        {{-- A member can hold any of the three, or several: an HLH who
                             also runs a taxi is both, and each role decides what they
                             may then file — a host lists experiences, a supplier keeps
                             a rate card. One dropdown could only ever say one of them. --}}
                        <label class="form-label">Provider type *</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach(\App\Models\ServiceProvider::TYPE_LABELS as $value => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="ap_types"
                                           value="{{ $value }}" id="ap_type_{{ $value }}"
                                           @checked($value === 'osp')>
                                    <label class="form-check-label" for="ap_type_{{ $value }}">
                                        {{ strtoupper($value) }} — {{ $label }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12">
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
                    {{-- What they offer, asked the way signup asks it: a host by the
                         experiences they host, a supplier by the services they supply,
                         each only when that role is ticked. A regional partner sells
                         nothing. This replaced a "Services offered" box that wrote a
                         column their approved rates now fill, so whatever was ticked
                         here was overwritten by their first rate. --}}
                    <div class="col-12 ap-offer" data-ap-for="hlh">
                        <label class="form-label">Experiences they host <small class="text-muted">(Local Host)</small></label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($experienceCategories as $c)
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="ap_experience_categories" value="{{ $c->name }}">
                                    <span class="form-check-label">{{ $c->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 ap-offer" data-ap-for="osp">
                        <label class="form-label">Services they supply <small class="text-muted">(Service Provider)</small></label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($serviceCategories as $c)
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="ap_service_categories" value="{{ $c->name }}">
                                    <span class="form-check-label">{{ $c->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 small text-muted ap-offer-help">
                        <span class="ap-help-hlh">Their experiences</span><span class="ap-help-both"> and </span><span class="ap-help-osp">their rates</span> are added afterwards, from their page; this only records what they told you.
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
// Provider names carry apostrophes ("Dorje's Homestay") and land in both cell
// text and data-attributes, where a raw quote would end the attribute early.
function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}

function formatLastUpdated(p) {
    var role = p.last_updated_by_role;
    if (role === 'admin') return '<span class="badge bg-secondary">Admin</span>';
    if (role === 'provider') return '<span class="badge bg-info">Provider</span>';
    return '<span class="text-muted small">-</span>';
}

// Badge classes in a fixed order, so a provider holding several roles always
// reads the same way round.
var TYPE_BADGE_CLASS = {
    hlh: 'badge bg-success',
    osp: 'badge bg-warning text-dark',
    hrp: 'badge bg-info'
};

// A provider can be more than one thing at once — an HLH that also runs a taxi
// is an HLH and an OSP. provider_type only names the primary role, so reading
// it alone showed that provider as a plain HLH. Rows saved before
// provider_types existed still only have the primary one; fall back to it.
function renderTypes(p) {
    var held = (Array.isArray(p.provider_types) && p.provider_types.length)
        ? p.provider_types
        : (p.provider_type ? [p.provider_type] : []);
    if (!held.length) return '<span class="badge bg-secondary">-</span>';

    var known = Object.keys(TYPE_BADGE_CLASS).filter(function(t) {
        return held.indexOf(t) !== -1;
    });
    var unknown = held.filter(function(t) { return !TYPE_BADGE_CLASS[t]; });

    return known.map(function(t) {
        return '<span class="' + TYPE_BADGE_CLASS[t] + '">' + t.toUpperCase() + '</span>';
    }).concat(unknown.map(function(t) {
        return '<span class="badge bg-secondary">' + String(t).toUpperCase() + '</span>';
    })).join(' ');
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
            var typeBadge = renderTypes(p);

            // Admin sees the real status — banned and hidden are only masked
            // on the member-facing side.
            var statusBadge = '';
            if (p.status === 'approved') statusBadge = '<span class="badge bg-success">Approved</span>';
            else if (p.status === 'pending') statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
            else if (p.status === 'rejected') statusBadge = '<span class="badge bg-danger">Rejected</span>';
            else if (p.status === 'banned') statusBadge = '<span class="badge bg-dark">Banned</span>';
            else if (p.status === 'hidden') statusBadge = '<span class="badge bg-secondary">Hidden</span>';
            else statusBadge = '<span class="badge bg-secondary">' + escapeHtml(p.status || '-') + '</span>';

            html += '<tr data-id="' + p.id + '">';
            html += '<td>';
            html += '<i class="bi bi-square provider-check" role="button" data-id="' + p.id + '"></i>';
            html += '</td>';
            html += '<td>' + escapeHtml(p.name || '-') + '</td>';
            html += '<td>' + typeBadge + '</td>';
            html += '<td>' + (p.region ? p.region.name : '-') + '</td>';
            html += '<td>';
            if (p.phone_1) html += '<small><i class="bi bi-telephone"></i> ' + p.phone_1 + '</small><br>';
            if (p.email) html += '<small><i class="bi bi-envelope"></i> ' + p.email + '</small>';
            html += '</td>';
            html += '<td>' + statusBadge + '</td>';
            html += '<td>' + formatLastUpdated(p) + '</td>';
            html += '<td class="text-nowrap">';
            html += '<a class="btn btn-sm btn-outline-primary me-1" href="/providers/' + p.id + '"><i class="bi bi-eye"></i> View</a>';
            html += '<a class="btn btn-sm btn-outline-success me-1" href="/providers/' + p.id + '/edit"><i class="bi bi-pencil"></i> Edit</a>';
            html += '<button type="button" class="btn btn-sm btn-outline-danger provider-remove" data-id="' + p.id + '" data-name="' + escapeHtml(p.name) + '" title="Delete provider"><i class="bi bi-trash"></i></button>';
            html += '</td>';
            html += '</tr>';
        });
        $('#providersTable').html(html);
        refreshBulkBtn();
        renderPagination('#providersPagination', resp, loadProviders);
    });
}

function refreshBulkBtn() {
    var selected = $('.provider-check.provider-checked').length;
    $('#providersBulkCount').text(selected);
    $('#providersBulkRemove').toggleClass('d-none', selected === 0);
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

// ── Danger actions ───────────────────────────────────────────────────────
// The single-row and bulk deletes are the same shape — confirm, one request,
// report what came back — so they share a runner. The server's message is
// shown verbatim because it is the only side that knows whether a login
// survived the delete for being a traveller's too.
function providerAction(opts) {
    Swal.fire({
        title: opts.title,
        html: opts.html,
        icon: opts.icon || 'warning',
        showCancelButton: true,
        confirmButtonText: opts.confirmText,
        confirmButtonColor: opts.confirmColor || '#b54a4a',
        focusCancel: !!opts.focusCancel
    }).then(function(res) {
        if (!res.isConfirmed) return;
        var $btn = opts.$btn;
        var idle = $btn.html();
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i>');
        ajaxPost(opts.data, function(resp) {
            // The row is about to be re-rendered, but the bulk buttons in the
            // header are not — both get their label back either way.
            $btn.prop('disabled', false).html(idle);
            showAlert(resp.message || 'Done.', (resp.blocked || resp.skipped) ? 'warning' : 'success');
            loadProviders();
        }, function(xhr) {
            $btn.prop('disabled', false).html(idle);
            var r = xhr.responseJSON || {};
            showAlert(r.error || 'Action failed.', 'danger');
        });
    });
}

// Selected ids on this page.
function selectedProviderIds() {
    return $('.provider-check.provider-checked')
        .map(function() { return $(this).data('id'); }).get();
}

var DELETE_WARNING = '<strong>This cannot be undone.</strong><br>'
    + 'The provider record is deleted outright, so its email is free to apply again. '
    + 'All pricing, availability blocks, and room bookings go with it, and hosted experiences are detached. '
    + 'Providers holding payment records are skipped. The login is deleted only if it belongs to this provider '
    + 'and nothing else — a traveller\'s account is kept, minus its provider role.';

// Per-row delete.
$(document).on('click', '.provider-remove', function() {
    var $btn = $(this);
    providerAction({
        $btn: $btn,
        title: 'Delete ' + escapeHtml($btn.data('name')) + '?',
        html: DELETE_WARNING,
        confirmText: 'Yes, delete',
        focusCancel: true,
        data: { remove_provider: 1, provider_id: $btn.data('id') }
    });
});

// Bulk delete — the whole selection goes in one request; the server applies
// the same blocker and account checks per provider and names the ones it
// skipped.
$('#providersBulkRemove').on('click', function() {
    var ids = selectedProviderIds();
    if (!ids.length) return;
    providerAction({
        $btn: $(this),
        title: 'Delete ' + ids.length + ' provider(s)?',
        html: DELETE_WARNING,
        confirmText: 'Yes, delete ' + ids.length,
        focusCancel: true,
        data: { bulk_remove_providers: 1, ids: ids }
    });
});

// ── Import providers from a CSV ─────────────────────────────────────────
var importProvidersModal = new bootstrap.Modal(document.getElementById('importProvidersModal'));
$('#importProvidersBtn').on('click', function() {
    $('#importProvidersFile').val('');
    $('#importProvidersResult').empty();
    importProvidersModal.show();
});
$('#importProvidersGo').on('click', function() {
    var file = $('#importProvidersFile')[0].files[0];
    var $out = $('#importProvidersResult');
    if (!file) { $out.html('<div class="alert alert-warning py-2">Choose the CSV file first.</div>'); return; }
    var fd = new FormData();
    fd.append('import_providers', 1);
    fd.append('file', file);
    var $btn = $(this).prop('disabled', true);
    $.ajax({ url: '/ajax', type: 'POST', data: fd, processData: false, contentType: false, skipGlobalError: true })
        .done(function(resp) {
            var html = '<div class="alert alert-' + (resp.created ? 'success' : 'warning') + ' py-2 mb-2">'
                + resp.created + ' provider' + (resp.created === 1 ? '' : 's') + ' added.'
                + (resp.failed.length ? ' ' + resp.failed.length + ' row' + (resp.failed.length === 1 ? '' : 's') + ' not added; fix them in the file and import it again.' : '')
                + '</div>';
            if (resp.failed.length) {
                html += '<table class="table table-sm small"><thead><tr><th>Line</th><th>What is wrong</th></tr></thead><tbody>';
                resp.failed.forEach(function(f) {
                    html += '<tr><td>' + f.line + '</td><td>' + escapeHtml(f.reason) + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            $out.html(html);
            if (resp.created) loadProviders();
        })
        .fail(function(xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'The file could not be imported.';
            $out.html('<div class="alert alert-danger py-2">' + escapeHtml(msg) + '</div>');
        })
        .always(function() { $btn.prop('disabled', false); });
});

// ── Add Provider (manual) ────────────────────────────────────────────────
var addProviderModal = new bootstrap.Modal(document.getElementById('addProviderModal'));

// Each "what they offer" list shows only while its role is ticked.
function apShowOffers() {
    var types = $('#addProviderForm input[name="ap_types"]:checked').map(function() { return this.value; }).get();
    $('#addProviderForm .ap-offer').each(function() {
        $(this).toggleClass('d-none', types.indexOf($(this).data('ap-for')) === -1);
    });
    $('#addProviderForm .ap-offer-help').toggleClass('d-none', types.indexOf('hlh') === -1 && types.indexOf('osp') === -1);
    // Says only what this provider will add: an OSP has no experiences.
    $('#addProviderForm .ap-help-hlh').toggleClass('d-none', types.indexOf('hlh') === -1);
    $('#addProviderForm .ap-help-osp').toggleClass('d-none', types.indexOf('osp') === -1);
    $('#addProviderForm .ap-help-both').toggleClass('d-none', types.indexOf('hlh') === -1 || types.indexOf('osp') === -1);
}
$(document).on('change', '#addProviderForm input[name="ap_types"]', apShowOffers);

$('#addProviderBtn').on('click', function() {
    $('#addProviderForm')[0].reset();
    $('#addProviderAlert').empty();
    apShowOffers();
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

    var types = $('#addProviderForm input[name="ap_types"]:checked')
        .map(function() { return this.value; }).get();
    if (!types.length) return apWarn('Please choose at least one provider type.');

    var data = {
        add_provider: 1,
        provider_types: types,
        name: $('#ap_name').val().trim(),
        contact_person: $('#ap_contact').val().trim(),
        email: email,
        phone_1: $('#ap_phone1').val().trim(),
        phone_2: $('#ap_phone2').val().trim(),
        region_id: $('#ap_region').val(),
        address: $('#ap_address').val().trim(),
        status: $('#ap_status').val(),
        // Only the lists of the roles ticked: one left ticked under a role that
        // was then unticked is not theirs.
        experience_categories: types.indexOf('hlh') === -1 ? [] : $('#addProviderForm input[name="ap_experience_categories"]:checked')
            .map(function() { return this.value; }).get(),
        service_categories: types.indexOf('osp') === -1 ? [] : $('#addProviderForm input[name="ap_service_categories"]:checked')
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
