@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-cash-stack me-2"></i>Pending Pricing Approvals</h1>
        <p class="text-muted small mb-0">Rates submitted by Service Providers awaiting your review. Until approved, these rates are NOT visible to travellers or the Trip Manager.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div id="pendingList" class="text-center text-muted small py-4">
            <i class="bi bi-hourglass-split me-1"></i> Loading...
        </div>
        <div id="pendingPagination" class="mt-3"></div>
    </div>
</div>

{{-- Reject modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h6 class="modal-title">Reject pricing change</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="rejectRowId">
        <label class="form-label small">Reason for rejection (optional, shown to the SP)</label>
        <textarea class="form-control form-control-sm" id="rejectReason" rows="3" placeholder="e.g. Rate too high for this tier — please revise"></textarea>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger" id="confirmReject"><i class="bi bi-x-lg me-1"></i> Reject</button>
    </div>
</div></div></div>
@endsection

@section('js')
<script>
jQuery(function() {
    var typeIcons = { accommodation: '🛏', transport: '🚙', guide: '👤', activity: '🏔', other: '📦' };
    var APPROVAL_FIELDS = [
        ['price', 'Rate'],
        ['category', 'Category'],
        ['description', 'Description'],
        // Accommodation
        ['total_rooms', 'Total rooms'],
        ['room_category', 'Room category'],
        ['comfort_tier', 'Comfort tier'],
        ['default_occupancy', 'Default occupancy'],
        ['guest_capacity', 'Sleeps'],
        ['meal_plan', 'Meal plan'],
        // Transport. The per-km rates matter most of all: a transport row's
        // `price` is 0 by design, so a card without these showed HCT a rate of
        // nothing and asked them to approve it.
        ['vehicle_type', 'Vehicle type'],
        ['vehicle_make_model', 'Make & model'],
        ['vehicle_registration_no', 'Registration'],
        ['vehicle_year', 'Year'],
        ['vehicle_count', 'Vehicles'],
        ['vehicle_capacity', 'Seats'],
        ['price_per_km_plains', 'Per km (plains)'],
        ['price_per_km_hills', 'Per km (hills)'],
        ['distance_km', 'Distance (km)'],
        ['ac_available', 'AC'],
        ['ac_extra_cost', 'AC extra'],
        ['driver_included', 'Driver included'],
        ['driver_allowance', 'Driver/day'],
        ['fuel_tolls_extra', 'Fuel & tolls extra'],
        // Guide and activity
        ['specialties', 'Specialties'],
        ['min_group', 'Min group'],
        ['max_group', 'Max group'],
        ['speaks_english', 'Speaks English'],
        ['languages', 'Languages'],
        ['wage_multi_day', 'Multi-day wage'],
        ['is_certified', 'Certified'],
        ['has_first_aid', 'First aid'],
        // Rental
        ['rental_item', 'Item'],
        ['security_deposit', 'Security deposit'],
        // General
        ['seasonality_notes', 'Seasonality'],
        ['notes', 'Notes'],
        ['unit', 'Unit'],
    ];

    function escapeHtml(s) { return jQuery('<div>').text(s == null ? '' : s).html(); }
    function fmt(v) {
        if (v === null || v === '' || v === undefined) return '—';
        // A flag reads as Yes/No, not as the word "false". Note false is a real
        // answer here — "fuel and tolls are not extra" is worth showing.
        if (typeof v === 'boolean') return v ? 'Yes' : 'No';
        if (Array.isArray(v)) return v.length ? escapeHtml(v.join(', ')) : '—';
        return escapeHtml(v);
    }

    function diffRow(pending, original) {
        var rows = '';
        APPROVAL_FIELDS.forEach(function(f) {
            var key = f[0], label = f[1];
            var oldVal = original ? original[key] : null;
            var newVal = pending[key];
            if ((oldVal === null || oldVal === '' || oldVal === undefined) &&
                (newVal === null || newVal === '' || newVal === undefined)) return;
            var changed = String(oldVal ?? '') !== String(newVal ?? '');
            if (!original) {
                rows += '<div class="diff-row">'
                     +   '<span class="diff-label">' + label + '</span>'
                     +   '<span class="diff-value fw-bold text-success">' + fmt(newVal) + '</span>'
                     + '</div>';
            } else if (changed) {
                rows += '<div class="diff-row">'
                     +   '<span class="diff-label">' + label + '</span>'
                     +   '<span class="diff-value">'
                     +     '<span class="text-decoration-line-through text-muted me-2">' + fmt(oldVal) + '</span>'
                     +     '<i class="bi bi-arrow-right text-muted mx-1"></i>'
                     +     '<span class="fw-bold text-success">' + fmt(newVal) + '</span>'
                     +   '</span>'
                     + '</div>';
            }
        });
        return rows || '<div class="small text-muted">No field changes</div>';
    }

    function load(page) {
        ajaxPost({ get_pending_pricing: 1, page: page || 1 }, function(resp) {
            var rows = resp.rows || [];
            renderPagination('#pendingPagination', resp, load);
            if (!rows.length) {
                jQuery('#pendingList').html('<div class="text-center text-muted small py-4"><i class="bi bi-check2-circle me-1"></i> No pending pricing changes.</div>');
                return;
            }
            // Build a one-line identity string so the admin can see which row
            // is being changed, even when only one field differs.
            function rowIdentity(r) {
                var parts = [];
                if (r.service_type === 'accommodation') {
                    if (r.comfort_tier)   parts.push('<span class="badge bg-light text-dark border me-1">' + escapeHtml(r.comfort_tier) + '</span>');
                    if (r.room_category)  parts.push('<strong>' + escapeHtml(r.room_category) + '</strong>');
                    if (r.meal_plan)      parts.push('<span class="text-muted small">' + escapeHtml(r.meal_plan) + '</span>');
                } else if (r.service_type === 'transport') {
                    if (r.vehicle_type)   parts.push('<strong>' + escapeHtml(r.vehicle_type) + '</strong>');
                    if (r.vehicle_capacity) parts.push('<span class="text-muted small">' + r.vehicle_capacity + ' seats</span>');
                } else if (r.service_type === 'rental') {
                    if (r.rental_item)    parts.push('<strong>' + escapeHtml(r.rental_item) + '</strong>');
                    if (r.security_deposit) parts.push('<span class="text-muted small">' + escapeHtml(r.security_deposit) + ' deposit</span>');
                } else {
                    // guide, activity, meal, other — and anything added later.
                    // The old list named its types one by one, so a meal or a
                    // rental fell through to "(no details yet)" while carrying
                    // a perfectly good category.
                    if (r.category)       parts.push('<strong>' + escapeHtml(r.category) + '</strong>');
                    if (r.specialties)    parts.push('<span class="text-muted small">' + escapeHtml(r.specialties) + '</span>');
                }
                if (r.total_rooms)        parts.push('<span class="text-muted small">' + r.total_rooms + ' rooms total</span>');
                return parts.join(' · ') || '<span class="text-muted small">(no details yet)</span>';
            }

            // One card per member, not per rate. Someone filing four rates in
            // one sitting produced four cards with the same name at the top,
            // which read as four different applicants.
            var groups = [];
            var seen = {};
            rows.forEach(function(r) {
                var sp = r.service_provider || {};
                var key = sp.id || 'unknown';
                if (!seen[key]) {
                    seen[key] = { provider: sp, rows: [] };
                    groups.push(seen[key]);
                }
                seen[key].rows.push(r);
            });

            // Every role they hold. provider_type is only the first of the set,
            // so a host who also supplies transport showed as just HLH.
            function providerTypes(sp) {
                var types = sp.provider_types || (sp.provider_type ? [sp.provider_type] : []);
                return types.map(function(t) { return escapeHtml(t).toUpperCase(); }).join(' · ') || '?';
            }

            var html = '';
            groups.forEach(function(group) {
                var sp = group.provider;
                var count = group.rows.length;

                html += '<div class="card border mb-3">';
                html += '  <div class="card-header py-2 d-flex align-items-center">';
                html += '    <strong>' + escapeHtml(sp.name || '?') + '</strong>';
                html += '    <span class="ms-2 small text-muted">(' + providerTypes(sp) + ')</span>';
                html += '    <span class="ms-auto small text-muted">' + count + (count === 1 ? ' rate' : ' rates') + ' awaiting review</span>';
                html += '  </div>';

                group.rows.forEach(function(r, index) {
                    var isEdit = !!r.pending_for_id;
                    var icon = typeIcons[r.service_type] || '·';
                    var submitter = r.submitter || {};
                    var diff = diffRow(r, r.pending_for);
                    var modeBadge = isEdit
                        ? '<span class="badge bg-warning text-dark">EDIT</span>'
                        : '<span class="badge bg-info text-dark">NEW</span>';
                    // For EDITS, identity comes from the live (pending_for) row so the
                    // admin sees the row's actual identity in the system. For NEW
                    // rows, identity is the pending row itself.
                    var identity = rowIdentity(isEdit ? (r.pending_for || r) : r);

                    // data-id stays on the rate, not the card — approve and
                    // reject act on one rate at a time as they always did.
                    html += '  <div class="card-body' + (index ? ' border-top' : '') + '" data-id="' + r.id + '">';
                    html += '    <div class="d-flex align-items-center mb-2">';
                    html += '      ' + modeBadge;
                    html += '      <span class="ms-2">' + icon + ' <strong>' + escapeHtml(r.service_type) + '</strong></span>';
                    html += '      <span class="ms-auto small text-muted">Submitted ' + escapeHtml(r.submitted_at || '') + ' by ' + escapeHtml(submitter.full_name || submitter.email || '?') + '</span>';
                    html += '    </div>';
                    html += '    <div class="pending-identity">'
                         +    '<span class="diff-label">' + (isEdit ? 'Editing row' : 'New entry') + '</span>'
                         +    '<span class="diff-value">' + identity + '</span>'
                         +  '</div>';
                    html += '    <div class="diff-list">' + diff + '</div>';
                    html += '    <div class="d-flex gap-2">';
                    html += '      <button class="btn btn-sm btn-success btn-approve"><i class="bi bi-check-lg me-1"></i> Approve</button>';
                    html += '      <button class="btn btn-sm btn-outline-danger btn-reject"><i class="bi bi-x-lg me-1"></i> Reject</button>';
                    html += '    </div>';
                    html += '  </div>';
                });

                html += '</div>';
            });
            jQuery('#pendingList').html(html);
        });
    }

    jQuery(document).on('click', '.btn-approve', function() {
        var id = jQuery(this).closest('[data-id]').data('id');
        confirmAction('Approve this pricing change?', function() {
            ajaxPost({ approve_pricing: 1, id: id }, function() {
                showAlert('Approved.', 'success');
                load();
            });
        });
    });

    jQuery(document).on('click', '.btn-reject', function() {
        var id = jQuery(this).closest('[data-id]').data('id');
        jQuery('#rejectRowId').val(id);
        jQuery('#rejectReason').val('');
        new bootstrap.Modal(jQuery('#rejectModal')[0]).show();
    });

    jQuery('#confirmReject').on('click', function() {
        var id = jQuery('#rejectRowId').val();
        var reason = jQuery('#rejectReason').val();
        ajaxPost({ reject_pricing: 1, id: id, reason: reason }, function() {
            bootstrap.Modal.getInstance(jQuery('#rejectModal')[0]).hide();
            showAlert('Rejected.', 'info');
            load();
        });
    });

    load();
});
</script>
@endsection
