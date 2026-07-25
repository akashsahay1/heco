@extends('admin.layout')
@section('title', 'Provider Applications - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-envelope-paper"></i> Provider Applications</h5>
    <div class="d-flex gap-2">
        <div class="heco-filter-md">
            <select class="form-select form-select-sm custom-select" id="appStatusFilter">
                <option value="" selected>All</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        <input type="text" class="form-control form-control-sm heco-filter-lg" id="appSearch" placeholder="Search name / email / phone">
    </div>
</div>

<div class="row g-3" id="applicationsContainer">
    <div class="col-12 text-center text-muted py-4">Loading...</div>
</div>
<div id="applicationsPagination" class="mt-3"></div>

@endsection

@section('js')
<script>
// Keeps the full application objects for the "View details" popup, keyed by id.
var appsById = {};

function loadApplications(page) {
    ajaxPost({
        get_provider_applications: 1,
        page: page || 1,
        status: $('#appStatusFilter').val(),
        search: $('#appSearch').val()
    }, function(resp) {
        var html = '';
        var items = resp.data || [];
        appsById = {};
        if (!items.length) {
            html = '<div class="col-12 text-center text-muted py-4">No applications found</div>';
            $('#applicationsContainer').html(html);
            renderPagination('#applicationsPagination', resp, loadApplications);
            return;
        }
        items.forEach(function(app) {
            appsById[app.id] = app;
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

            // Full details (all wizard fields + documents)
            html += '<button class="btn btn-sm btn-outline-secondary w-100 mb-2 view-app" data-id="' + app.id + '"><i class="bi bi-eye"></i> View details</button>';

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
        renderPagination('#applicationsPagination', resp, loadApplications);
    });
}

$(function() { loadApplications(); });

$('#appStatusFilter').on('change', function() { loadApplications(); });
$('#appSearch').on('keyup', function() { loadApplications(); });

$(document).on('click', '.approve-app', function() {
    var id = $(this).data('id');
    var $btn = $(this);
    Swal.fire({
        title: 'Approve this provider application?',
        text: 'A user account will be created for the provider and a set-password email will be sent.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve',
        confirmButtonColor: '#79a09f',
    }).then(function(res) {
        if (!res.isConfirmed) return;
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
        ajaxPost({ approve_provider: 1, provider_id: id }, function(resp) {
            showAlert('Application approved.', 'success');
            loadApplications();
        });
    });
});

$(document).on('click', '.reject-app', function() {
    var id = $(this).data('id');
    var $btn = $(this);
    Swal.fire({
        title: 'Reject this provider application?',
        text: 'You can reverse this later by re-approving the application.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, reject',
        confirmButtonColor: '#b54a4a',
    }).then(function(res) {
        if (!res.isConfirmed) return;
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
        ajaxPost({ reject_provider: 1, provider_id: id }, function(resp) {
            showAlert('Application rejected.', 'info');
            loadApplications();
        });
    });
});

// ── Full application details ────────────────────────────────────────────
// Array columns arrive already cast to arrays, but tolerate a JSON string too.
function asList(v) {
    if (Array.isArray(v)) return v;
    if (typeof v === 'string' && v !== '') {
        try { var p = JSON.parse(v); return Array.isArray(p) ? p : [v]; } catch (e) { return [v]; }
    }
    return [];
}
function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).on('click', '.view-app', function() {
    var app = appsById[$(this).data('id')];
    if (!app) return;

    function row(label, val) {
        if (val === null || val === undefined || val === '') return '';
        return '<div class="mb-2"><div class="text-muted small text-uppercase" style="letter-spacing:.04em;">'
            + label + '</div><div>' + val + '</div></div>';
    }
    function chips(label, arr) {
        arr = asList(arr);
        if (!arr.length) return '';
        var b = arr.map(function(s) { return '<span class="badge bg-light text-dark border me-1 mb-1">' + esc(s) + '</span>'; }).join('');
        return '<div class="mb-2"><div class="text-muted small text-uppercase" style="letter-spacing:.04em;">' + label + '</div><div>' + b + '</div></div>';
    }

    var h = '<div class="text-start">';
    h += row('Type', esc((app.provider_type || '').toUpperCase()));
    h += row('Business type', esc(app.business_type));
    h += row('Registration no.', esc(app.registration_number));
    h += row('Year established', esc(app.year_established));
    h += row('Contact person', esc(app.contact_person));
    h += row('Email', esc(app.email));
    h += row('Phone', [app.phone_1, app.phone_2].filter(Boolean).map(esc).join(' · '));
    h += row('Region', app.region ? esc(app.region.name) : '');
    // Full postal address: street, then "city postal", then country — each on its own line.
    var addressLines = [];
    if (app.address) addressLines.push(esc(app.address));
    var cityLine = [app.city, app.postal_code].filter(Boolean).map(esc).join(' ');
    if (cityLine) addressLines.push(cityLine);
    if (app.country) addressLines.push(esc(app.country));
    h += row('Address', addressLines.join('<br>'));
    h += row('About', esc(app.notes));
    h += chips('Services offered', app.services_offered);
    h += chips('Accommodation categories', app.accommodation_categories);
    h += chips('Vehicle types', app.vehicle_types);
    h += chips('Guide specialisations', app.guide_types);
    h += chips('Activity types', app.activity_types);

    var docs = asList(app.documents);
    if (docs.length) {
        var d = docs.map(function(doc) {
            var url = '/storage/' + doc.path;
            return '<div class="mb-1"><i class="bi bi-paperclip text-muted"></i> '
                + '<a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(doc.label || 'Document') + '</a> '
                + '<span class="text-muted small">' + esc(doc.original_name || '') + '</span></div>';
        }).join('');
        h += '<div class="mb-2"><div class="text-muted small text-uppercase" style="letter-spacing:.04em;">Documents</div>' + d + '</div>';
    } else {
        h += row('Documents', '<span class="text-muted">None uploaded</span>');
    }
    h += '<div class="text-muted small mt-2">Submitted ' + (app.created_at ? app.created_at.substring(0, 10) : '-') + '</div>';
    h += '</div>';

    Swal.fire({
        title: esc(app.name || 'Application'),
        html: h,
        width: 620,
        confirmButtonText: 'Close',
        confirmButtonColor: '#79a09f',
    });
});
</script>
@endsection
