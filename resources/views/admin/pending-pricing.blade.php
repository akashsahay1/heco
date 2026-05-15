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
        ['total_rooms', 'Total rooms'],
        ['room_category', 'Room category'],
        ['comfort_tier', 'Comfort tier'],
        ['meal_plan', 'Meal plan'],
        ['vehicle_type', 'Vehicle type'],
        ['vehicle_capacity', 'Seats'],
        ['driver_allowance', 'Driver/day'],
        ['category', 'Category'],
        ['specialties', 'Specialties'],
        ['min_group', 'Min group'],
        ['max_group', 'Max group'],
        ['unit', 'Unit'],
    ];

    function escapeHtml(s) { return jQuery('<div>').text(s == null ? '' : s).html(); }
    function fmt(v) { return (v === null || v === '' || v === undefined) ? '—' : escapeHtml(v); }

    function diffRow(pending, original) {
        var rows = '';
        APPROVAL_FIELDS.forEach(function(f) {
            var key = f[0], label = f[1];
            var oldVal = original ? original[key] : null;
            var newVal = pending[key];
            // Skip rows where both sides are empty/null
            if ((oldVal === null || oldVal === '' || oldVal === undefined) &&
                (newVal === null || newVal === '' || newVal === undefined)) return;
            var changed = String(oldVal ?? '') !== String(newVal ?? '');
            if (!original) {
                // NEW row → show only the new value, highlighted
                rows += '<tr><td class="small text-muted">' + label + '</td>'
                     + '<td class="small fw-bold text-success">' + fmt(newVal) + '</td></tr>';
            } else if (changed) {
                rows += '<tr><td class="small text-muted">' + label + '</td>'
                     + '<td class="small"><span class="text-decoration-line-through text-muted me-2">' + fmt(oldVal) + '</span>'
                     + '<i class="bi bi-arrow-right text-muted mx-1"></i>'
                     + '<span class="fw-bold text-success">' + fmt(newVal) + '</span></td></tr>';
            }
        });
        return rows || '<tr><td colspan="2" class="small text-muted">No field changes</td></tr>';
    }

    function load() {
        ajaxPost({ get_pending_pricing: 1 }, function(resp) {
            var rows = resp.rows || [];
            if (!rows.length) {
                jQuery('#pendingList').html('<div class="text-center text-muted small py-4"><i class="bi bi-check2-circle me-1"></i> No pending pricing changes.</div>');
                return;
            }
            var html = '';
            rows.forEach(function(r) {
                var isEdit = !!r.pending_for_id;
                var icon = typeIcons[r.service_type] || '·';
                var sp = r.service_provider || {};
                var submitter = r.submitter || {};
                var diff = diffRow(r, r.pending_for);
                var modeBadge = isEdit
                    ? '<span class="badge bg-warning text-dark">EDIT</span>'
                    : '<span class="badge bg-info text-dark">NEW</span>';
                html += '<div class="card border mb-3" data-id="' + r.id + '">';
                html += '  <div class="card-header py-2 d-flex align-items-center">';
                html += '    ' + modeBadge;
                html += '    <span class="ms-2">' + icon + ' <strong>' + escapeHtml(sp.name || '?') + '</strong></span>';
                html += '    <span class="ms-2 small text-muted">(' + escapeHtml(sp.provider_type || '?').toUpperCase() + ')</span>';
                html += '    <span class="ms-3 small text-muted">' + escapeHtml(r.service_type) + '</span>';
                html += '    <span class="ms-auto small text-muted">Submitted ' + escapeHtml(r.submitted_at || '') + ' by ' + escapeHtml(submitter.full_name || submitter.email || '?') + '</span>';
                html += '  </div>';
                html += '  <div class="card-body">';
                html += '    <table class="table table-sm mb-3"><tbody>' + diff + '</tbody></table>';
                html += '    <div class="d-flex gap-2">';
                html += '      <button class="btn btn-sm btn-success btn-approve"><i class="bi bi-check-lg me-1"></i> Approve</button>';
                html += '      <button class="btn btn-sm btn-outline-danger btn-reject"><i class="bi bi-x-lg me-1"></i> Reject</button>';
                html += '    </div>';
                html += '  </div>';
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
