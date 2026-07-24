@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-layers me-2"></i>Pending Experience Approvals</h1>
        <p class="text-muted small mb-0">
            Experiences submitted by hosts (HLH) and operators (OSP). Use <strong>Edit</strong>
            to adjust the details yourself before approving.
            <br>
            A <strong>new submission</strong> is not visible to travellers until you approve it.
            A <strong>revision</strong> is a change to an experience that is already live — the
            approved version keeps selling either way; approving swaps it, rejecting discards
            the change.
        </p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div id="pendingExpList" class="text-center text-muted small py-4">
            <i class="bi bi-hourglass-split me-1"></i> Loading...
        </div>
        <div id="pendingExpPagination" class="mt-3"></div>
    </div>
</div>

{{-- Reject modal --}}
<div class="modal fade" id="rejectExpModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h6 class="modal-title">Reject experience</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="rejectExpId">
        <label class="form-label small">Reason (optional — the provider sees this)</label>
        <textarea class="form-control form-control-sm" id="rejectExpReason" rows="3"
                  placeholder="e.g. Please add a day-wise itinerary and the group size limits"></textarea>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger" id="confirmRejectExp"><i class="bi bi-x-lg me-1"></i> Reject</button>
    </div>
</div></div></div>
@endsection

@section('js')
<script>
jQuery(function() {

    function esc(str) {
        return jQuery('<div>').text(str == null ? '' : str).html();
    }

    function durationLabel(row) {
        if (row.duration_type === 'multi_day') {
            return (row.duration_days || 1) + 'D / ' + (row.duration_nights || 0) + 'N';
        }
        if (row.duration_type === 'less_than_day') {
            return (row.duration_hours || '') + ' hrs';
        }
        return 'Single day';
    }

    function loadPending(page) {
        ajaxPost({ get_pending_experiences: 1, page: page || 1 }, function(resp) {
            var rows = resp.rows || [];
            var list = jQuery('#pendingExpList').empty();

            if (!rows.length) {
                list.html('<div class="text-muted small py-3">' +
                    '<i class="bi bi-check2-circle me-1"></i> Nothing waiting for review.</div>');
                jQuery('#pendingExpPagination').empty();
                return;
            }

            var html = '<div class="table-responsive"><table class="table table-sm align-middle">';
            html += '<thead class="table-light"><tr>' +
                '<th>Experience</th><th>Provider</th><th>Region</th><th>Duration</th>' +
                '<th>Days</th><th>From ₹</th><th>Submitted</th><th class="text-end">Actions</th>' +
                '</tr></thead><tbody>';

            jQuery.each(rows, function(i, row) {
                var provider = row.owner_provider || {};
                html += '<tr>';
                // A revision is a live experience with changes parked against
                // it — approving replaces the live version, rejecting keeps it.
                var kind = row.has_pending_changes
                    ? '<span class="badge bg-info text-dark">Revision of a live experience</span>'
                    : '<span class="badge bg-warning text-dark">New submission</span>';
                html += '<td><strong>' + esc(row.name) + '</strong> ' + kind +
                        '<div class="text-muted small">' + esc(row.short_description || '') + '</div></td>';
                html += '<td class="small">' + esc(provider.name || '-') +
                        '<div class="text-muted">' + esc((provider.provider_type || '').toUpperCase()) + '</div></td>';
                html += '<td class="small">' + esc(row.region ? row.region.name : '-') + '</td>';
                html += '<td class="small">' + durationLabel(row) + '</td>';
                html += '<td class="small">' + ((row.days || []).length || '-') + '</td>';
                html += '<td class="small">' + (row.base_cost_per_person || 0) + '</td>';
                html += '<td class="small">' + esc(row.submitted_at || '-') + '</td>';
                html += '<td class="text-end text-nowrap">';
                html += '<a href="/experiences/' + row.id + '/edit" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a> ';
                html += '<button type="button" class="btn btn-sm btn-success approve-exp" data-id="' + row.id + '"><i class="bi bi-check-lg"></i> Approve</button> ';
                html += '<button type="button" class="btn btn-sm btn-outline-danger reject-exp" data-id="' + row.id + '"><i class="bi bi-x-lg"></i></button>';
                html += '</td></tr>';
            });

            html += '</tbody></table></div>';
            list.html(html);

            renderPagination('#pendingExpPagination', resp, loadPending);
        });
    }

    jQuery(document).on('click', '.approve-exp', function() {
        var btn = jQuery(this).prop('disabled', true);
        ajaxPost({ approve_experience: 1, id: btn.data('id') }, function() {
            showAlert('Experience approved — it is live now.', 'success');
            loadPending();
        }, function(xhr) {
            btn.prop('disabled', false);
            showAlert(xhr.responseJSON ? (xhr.responseJSON.error || 'Could not approve') : 'Could not approve', 'danger');
        });
    });

    jQuery(document).on('click', '.reject-exp', function() {
        jQuery('#rejectExpId').val(jQuery(this).data('id'));
        jQuery('#rejectExpReason').val('');
        new bootstrap.Modal(jQuery('#rejectExpModal')[0]).show();
    });

    jQuery('#confirmRejectExp').on('click', function() {
        ajaxPost({
            reject_experience: 1,
            id: jQuery('#rejectExpId').val(),
            reason: jQuery('#rejectExpReason').val()
        }, function() {
            bootstrap.Modal.getInstance(jQuery('#rejectExpModal')[0]).hide();
            showAlert('Experience rejected — the provider can revise and resubmit.', 'success');
            loadPending();
        });
    });

    loadPending();
});
</script>
@endsection
