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

    var ACCENT = '#79a09f';

    // A label + value pair (two grid cells). Empty values render nothing so the
    // grid never shows a blank row.
    function field(label, val) {
        if (val === null || val === undefined || val === '') return '';
        return '<div style="color:#9a9a95;font-size:11px;text-transform:uppercase;letter-spacing:.05em;padding-top:3px;">' + label + '</div>'
             + '<div style="color:#33332f;font-size:14px;line-height:1.45;word-break:break-word;">' + val + '</div>';
    }
    function grid(inner) {
        return inner ? '<div style="display:grid;grid-template-columns:120px 1fr;gap:8px 14px;">' + inner + '</div>' : '';
    }
    // A titled card. Renders nothing when it has no content.
    function section(icon, title, inner) {
        if (!inner) return '';
        return '<div style="border:1px solid #ececea;border-radius:12px;padding:14px 16px;margin-bottom:12px;background:#fff;">'
             + '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">'
             +   '<i class="bi ' + icon + '" style="color:' + ACCENT + ';font-size:15px;"></i>'
             +   '<span style="font-weight:600;color:#575753;font-size:12px;text-transform:uppercase;letter-spacing:.06em;">' + title + '</span>'
             + '</div>' + inner + '</div>';
    }
    function chipsVal(arr) {
        arr = asList(arr);
        if (!arr.length) return '';
        return arr.map(function(s) {
            return '<span style="display:inline-block;background:#f3f6f5;color:#4b6b6a;border:1px solid #e2e9e8;'
                 + 'border-radius:20px;padding:2px 10px;font-size:12.5px;margin:0 4px 4px 0;">' + esc(s) + '</span>';
        }).join('');
    }
    function capField(label, arr) {
        var c = chipsVal(arr);
        return c ? field(label, c) : '';
    }

    // ── Header: avatar + name + type & status pills ─────────────────────
    var typeLabels = {
        hrp: 'HRP · Heco Regional Partner',
        hlh: 'HLH · Heco Local Host',
        osp: 'OSP · Other Service Provider',
    };
    // An applicant can hold more than one role — "an HLH can also select OSP".
    // Showing only the first would hide half of what they applied to do.
    var heldTypes = asList(app.provider_types);
    if (!heldTypes.length && app.provider_type) heldTypes = [app.provider_type];
    var typePills = heldTypes.map(function(t) {
        return '<span style="background:#eef4f3;color:#4b6b6a;border-radius:20px;padding:2px 10px;'
             + 'font-size:12px;font-weight:600;">' + esc(typeLabels[t] || String(t).toUpperCase()) + '</span>';
    }).join('');
    var name = app.name || 'Application';
    var initials = name.trim().split(/\s+/).slice(0, 2).map(function(w) { return w.charAt(0); }).join('').toUpperCase();
    var statusColors = { pending: ['#fff6e5', '#a6791f'], approved: ['#e9f4ee', '#2e7d4f'], rejected: ['#fbeceb', '#b53b34'] };
    var sc = statusColors[app.status] || ['#eef0ef', '#666'];
    var submitted = app.created_at ? app.created_at.substring(0, 10) : '';

    var header = '<div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;text-align:left;">'
      + '<div style="flex:none;width:54px;height:54px;border-radius:15px;background:#eef4f3;color:' + ACCENT + ';'
      +   'display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;">' + esc(initials || '·') + '</div>'
      + '<div style="min-width:0;">'
      +   '<div style="font-size:18px;font-weight:700;color:#333330;line-height:1.2;">' + esc(name) + '</div>'
      +   '<div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:6px;">'
      +     typePills
      +     (app.status ? '<span style="background:' + sc[0] + ';color:' + sc[1] + ';border-radius:20px;padding:2px 10px;'
      +        'font-size:12px;font-weight:600;text-transform:capitalize;">' + esc(app.status) + '</span>' : '')
      +     (submitted ? '<span style="color:#9a9a95;font-size:12px;">Submitted ' + esc(submitted) + '</span>' : '')
      +   '</div>'
      + '</div></div>';

    // ── Location: full postal address ───────────────────────────────────
    var addressLines = [];
    if (app.address) addressLines.push(esc(app.address));
    var cityLine = [app.city, app.postal_code].filter(Boolean).map(esc).join(' ');
    if (cityLine) addressLines.push(cityLine);
    if (app.country) addressLines.push(esc(app.country));

    // ── Documents ───────────────────────────────────────────────────────
    var docs = asList(app.documents), docHtml;
    if (docs.length) {
        docHtml = docs.map(function(doc) {
            var url = '/storage/' + doc.path;
            return '<div style="margin-bottom:5px;"><i class="bi bi-paperclip" style="color:#9a9a95;"></i> '
                 + '<a href="' + esc(url) + '" target="_blank" rel="noopener" style="color:' + ACCENT + ';">' + esc(doc.label || 'Document') + '</a> '
                 + '<span style="color:#9a9a95;font-size:12px;">' + esc(doc.original_name || '') + '</span></div>';
        }).join('');
    } else {
        docHtml = '<div style="color:#9a9a95;font-size:14px;">None uploaded</div>';
    }

    // ── Competences: an HRP has no catalogue, so this is what the decision
    //    to place them on a region actually rests on. ────────────────────
    var roles = asList(app.work_experience);
    var roleHtml = roles.map(function(r) {
        var meta = [r.organisation, r.years].filter(Boolean).map(esc).join(' · ');
        return '<div style="border-left:3px solid #e2e9e8;padding-left:8px;margin-bottom:6px;">'
             + '<div style="color:#33332f;font-size:13.5px;font-weight:600;">' + esc(r.role || '—')
             + (meta ? ' <span style="color:#9a9a95;font-weight:400;">· ' + meta + '</span>' : '') + '</div>'
             + (r.description ? '<div style="color:#575753;font-size:12.5px;">' + esc(r.description) + '</div>' : '')
             + '</div>';
    }).join('');

    var competenceGrid = grid(
        field('Education', [app.education_level, app.education_notes].filter(Boolean).map(esc).join('<br>'))
      + field('English', esc(app.english_level))
      + field('Computer', esc(app.computer_skill_level))
      + field('Causes', esc(app.causes_note))
      + field('Community', esc(app.community_note))
    );
    var competenceHtml = (app.provider_type === 'hrp' || (asList(app.provider_types).indexOf('hrp') !== -1))
        ? competenceGrid + (roleHtml
            ? '<div style="margin-top:10px;"><div style="color:#9a9a95;font-size:11px;text-transform:uppercase;'
              + 'letter-spacing:.05em;margin-bottom:4px;">Work experience</div>' + roleHtml + '</div>'
            : '')
        : '';

    // Whether there is a business at all. "No" is an answer worth showing —
    // most members will not have one, and an empty Business card alone does
    // not say whether they were asked.
    var businessAnswer = app.has_business === null || app.has_business === undefined
        ? ''
        : (Number(app.has_business) ? 'Yes' : 'No, not yet');

    // Which travellers they can host.
    var spokenLanguages = [];
    if (Number(app.speaks_english)) spokenLanguages.push('English');
    if (Number(app.speaks_hindi)) spokenLanguages.push('Hindi');
    var languageHtml = grid(
        field('Speaks', spokenLanguages.length ? esc(spokenLanguages.join(' · ')) : '')
      + field('Other', esc(app.other_languages))
    );

    // How we are allowed to reach them. A declined channel matters more than an
    // accepted one, so both are stated rather than only what they agreed to.
    var contactHtml = (app.contact_by_email === undefined && app.contact_by_whatsapp === undefined)
        ? ''
        : grid(
            field('Email', Number(app.contact_by_email) ? 'Yes' : 'No — do not email')
          + field('WhatsApp / SMS', Number(app.contact_by_whatsapp) ? 'Yes' : 'No — do not message')
        );

    var h = '<div style="text-align:left;">'
      + header
      + section('bi-person', 'Contact', grid(
            field('Contact', esc(app.contact_person))
          + field('Email', esc(app.email))
          + field('Phone', [app.phone_1, app.phone_2].filter(Boolean).map(esc).join(' · '))
        ))
      + section('bi-briefcase', 'Business', grid(
            field('Has a business', esc(businessAnswer))
          + field('Business type', esc(app.business_type))
          + field('Reg. number', esc(app.registration_number))
          + field('Year est.', esc(app.year_established))
        ))
      + section('bi-translate', 'Languages', languageHtml)
      + section('bi-geo-alt', 'Location', grid(
            field('Region', app.region ? esc(app.region.name) : '')
          + field('Address', addressLines.join('<br>'))
        ))
      + (app.notes ? section('bi-card-text', 'About',
            '<div style="color:#33332f;font-size:14px;line-height:1.5;">' + esc(app.notes) + '</div>') : '')
      + section('bi-stars', 'What they offer', grid(
            // The categories chosen per role held — this is the application.
            capField('Experiences', app.experience_categories)
          + capField('Services', app.service_categories)
          + field('Also offers', esc(app.other_services))
          + capField('Listed', app.services_offered)
          + capField('Accommodation', app.accommodation_categories)
          + capField('Vehicle types', app.vehicle_types)
          + capField('Guide', app.guide_types)
          + capField('Activity', app.activity_types)
        ))
      + section('bi-mortarboard', 'Competences', competenceHtml)
      + section('bi-chat-dots', 'How we may contact them', contactHtml)
      + section('bi-paperclip', 'Documents', docHtml)
      + '</div>';

    Swal.fire({
        html: h,
        width: 640,
        confirmButtonText: 'Close',
        confirmButtonColor: ACCENT,
    });
});
</script>
@endsection
