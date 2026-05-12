@extends('admin.layout')
@section('title', 'Payments - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Payments</h5>
</div>

<ul class="nav nav-pills mb-3" id="paymentsTabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#spPayments"><i class="bi bi-people"></i> SP Payments</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#travellerPayments"><i class="bi bi-person"></i> Traveller Payments</a>
    </li>
</ul>

<div class="tab-content">
    {{-- SP Payments Tab --}}
    <div class="tab-pane fade show active" id="spPayments">
        <div class="d-flex gap-2 mb-3">
            <input type="text" class="form-control form-control-sm" id="spTripSearch" style="width: 250px;">
            <button class="btn btn-sm btn-outline-primary" id="spSearchBtn"><i class="bi bi-search"></i> Search</button>
        </div>

        <div id="spPaymentsList">
            <p class="text-muted text-center">Loading...</p>
        </div>
    </div>

    {{-- Traveller Payments Tab --}}
    <div class="tab-pane fade" id="travellerPayments">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Trip ID</th>
                                <th>Traveller</th>
                                <th>Total Due</th>
                                <th>Total Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="travellerPaymentsTable">
                            <tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Traveller Payment Entries Modal --}}
<div class="modal fade" id="travellerEntriesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Traveller Payment Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="travellerEntriesBody">
                <p class="text-muted text-center">Loading...</p>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
function loadSpPayments() {
    var params = { get_sp_payments: 1 };
    var search = $('#spTripSearch').val();
    if (search) params.trip_search = search;

    ajaxPost(params, function(resp) {
        var items = resp.data || [];
        var html = '';

        if (!items.length) {
            html = '<p class="text-muted text-center">No SP payments found</p>';
            $('#spPaymentsList').html(html);
            return;
        }

        html += '<div class="accordion" id="spAccordion">';
        items.forEach(function(sp, idx) {
            var balance = Number(sp.balance || 0);
            var balanceClass = balance > 0 ? 'text-danger' : 'text-success';
            var collapseId = 'spCollapse' + sp.id;
            var headingId = 'spHeading' + sp.id;

            html += '<div class="accordion-item">';
            html += '<h2 class="accordion-header" id="' + headingId + '">';
            html += '<button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#' + collapseId + '">';
            html += '<div class="d-flex justify-content-between w-100 me-3 align-items-center">';
            html += '<div>';
            html += '<strong>' + (sp.service_provider ? sp.service_provider.name : '-') + '</strong>';
            html += ' <span class="badge bg-info ms-1">' + (sp.service_type || '-') + '</span>';
            html += ' <small class="text-muted ms-2">Trip: ' + (sp.trip_id || '-') + '</small>';
            html += '</div>';
            html += '<div class="text-end">';
            html += '<span class="small">Due: ₹' + Number(sp.amount_due || 0).toLocaleString() + '</span>';
            html += ' <span class="small ms-2">Paid: ₹' + Number(sp.amount_paid || 0).toLocaleString() + '</span>';
            html += ' <span class="small ms-2 fw-bold ' + balanceClass + '">Bal: ₹' + balance.toLocaleString() + '</span>';
            html += '</div>';
            html += '</div>';
            html += '</button>';
            html += '</h2>';
            html += '<div id="' + collapseId + '" class="accordion-collapse collapse" data-bs-parent="#spAccordion">';
            html += '<div class="accordion-body">';

            // Payment history section
            html += '<div class="mb-3">';
            html += '<h6 class="small fw-bold">Payment History</h6>';
            html += '<div id="spHistory' + sp.id + '">';
            if (sp.entries && sp.entries.length) {
                html += buildEntryTable(sp.entries);
            } else {
                html += '<p class="text-muted small">No payments recorded yet.</p>';
            }
            html += '</div>';
            html += '<button class="btn btn-sm btn-outline-secondary mt-1 load-sp-history" data-id="' + sp.id + '"><i class="bi bi-arrow-clockwise"></i> Refresh History</button>';
            html += '</div>';

            // Add payment form
            html += '<hr>';
            html += '<h6 class="small fw-bold">Add Payment</h6>';
            html += '<div class="row g-2">';
            html += '<div class="col-md-3"><input type="number" class="form-control form-control-sm sp-amount" data-id="' + sp.id + '" step="0.01"></div>';
            html += '<div class="col-md-3">';
            html += '<input type="text" class="form-control form-control-sm sp-date-display" data-id="' + sp.id + '" readonly autocomplete="off">';
            html += '<input type="hidden" class="sp-date" data-id="' + sp.id + '">';
            html += '</div>';
            html += '<div class="col-md-3">';
            html += '<select class="form-select form-select-sm sp-mode custom-select" data-id="' + sp.id + '">';
            html += '<option value="bank_transfer">Bank Transfer</option>';
            html += '<option value="upi">UPI</option>';
            html += '<option value="cash">Cash</option>';
            html += '<option value="remitly">Remitly</option>';
            html += '</select>';
            html += '</div>';
            html += '<div class="col-md-3"><input type="text" class="form-control form-control-sm sp-notes" data-id="' + sp.id + '"></div>';
            html += '</div>';
            html += '<button class="btn btn-sm btn-success mt-2 add-sp-payment" data-id="' + sp.id + '"><i class="bi bi-plus-circle"></i> Add Payment</button>';

            html += '</div>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        $('#spPaymentsList').html(html);
        // Wire up Air Datepickers + searchable mode selects on the freshly-rendered rows.
        $('#spPaymentsList .sp-date-display').each(function() {
            var $disp = $(this);
            var $hidden = $('#spPaymentsList .sp-date[data-id="' + $disp.data('id') + '"]');
            new AirDatepicker(this, {
                locale: window.airDatepickerEn,
                dateFormat: 'dd-MM-yyyy',
                autoClose: true,
                position: 'bottom left',
                onSelect: function(o) {
                    $hidden.val(o.date ? isoFromDate(o.date) : '');
                }
            });
        });
        if (window.buildCustomDropdown) {
            $('#spPaymentsList .sp-mode').each(function() { buildCustomDropdown(this, { searchable: false }); });
        }
    });
}

function isoFromDate(d) {
    var pad = function(n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
}

function buildEntryTable(entries) {
    var html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
    html += '<thead class="table-light"><tr><th>Date</th><th>Amount</th><th>Mode</th><th>Notes</th></tr></thead><tbody>';
    entries.forEach(function(e) {
        html += '<tr>';
        html += '<td><small>' + (e.payment_date ? e.payment_date.substring(0, 10) : '-') + '</small></td>';
        html += '<td>₹' + Number(e.amount || 0).toLocaleString() + '</td>';
        html += '<td><small>' + (e.mode ? e.mode.replace(/_/g, ' ') : '-') + '</small></td>';
        html += '<td><small>' + (e.notes || '-') + '</small></td>';
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    return html;
}

function loadTravellerPayments() {
    ajaxPost({ get_traveller_payments_overview: 1 }, function(resp) {
        var items = resp.payments || resp.data || [];
        var html = '';
        if (!items.length) {
            html = '<tr><td colspan="6" class="text-center text-muted">No traveller payments found</td></tr>';
        }
        items.forEach(function(p) {
            var balance = Number(p.balance || 0);
            var balanceClass = balance > 0 ? 'text-danger' : 'text-success';
            var statusLabel = balance <= 0 ? 'Paid' : 'Pending';
            var statusBadge = balance <= 0 ? 'success' : 'warning text-dark';

            html += '<tr class="traveller-row" style="cursor: pointer;" data-trip-num="' + (p.trip ? p.trip.id || '' : '') + '" data-trip-id="' + (p.trip ? p.trip.trip_id : '') + '" data-user="' + (p.user ? p.user.full_name || p.user.email : '') + '" data-due="' + (p.total_due || 0) + '" data-paid="' + (p.total_paid || 0) + '">';
            html += '<td><a href="/trip-manager/' + (p.trip ? p.trip.id || '' : '') + '" target="_blank" onclick="event.stopPropagation();">' + (p.trip ? p.trip.trip_id : '-') + '</a></td>';
            html += '<td>' + (p.user ? p.user.full_name || p.user.email : '-') + '</td>';
            html += '<td>₹' + Number(p.total_due || 0).toLocaleString() + '</td>';
            html += '<td>₹' + Number(p.total_paid || 0).toLocaleString() + '</td>';
            html += '<td class="fw-bold ' + balanceClass + '">₹' + balance.toLocaleString() + '</td>';
            html += '<td><span class="badge bg-' + statusBadge + '">' + statusLabel + '</span></td>';
            html += '</tr>';
        });
        $('#travellerPaymentsTable').html(html);
    });
}

$(function() {
    loadSpPayments();
    loadTravellerPayments();
});

$('#spSearchBtn').on('click', function() { loadSpPayments(); });
$('#spTripSearch').on('keyup', function(e) { if (e.key === 'Enter') loadSpPayments(); });

$(document).on('click', '.add-sp-payment', function() {
    var id = $(this).data('id');
    var amount = $('.sp-amount[data-id="' + id + '"]').val();
    var paymentDate = $('.sp-date[data-id="' + id + '"]').val();
    var mode = $('.sp-mode[data-id="' + id + '"]').val();
    var notes = $('.sp-notes[data-id="' + id + '"]').val();

    if (!amount || !paymentDate) {
        showAlert('Please enter amount and date.', 'warning');
        return;
    }

    ajaxPost({
        add_sp_payment_entry: 1,
        sp_payment_id: id,
        amount: amount,
        payment_date: paymentDate,
        mode: mode,
        notes: notes
    }, function() {
        loadSpPayments();
    });
});

$(document).on('click', '.load-sp-history', function() {
    var id = $(this).data('id');
    var container = $('#spHistory' + id);
    container.html('<p class="text-muted small">Loading...</p>');
    ajaxPost({ get_sp_payment_history: 1, sp_payment_id: id }, function(resp) {
        var entries = resp.entries || [];
        if (!entries.length) {
            container.html('<p class="text-muted small">No payments recorded yet.</p>');
        } else {
            container.html(buildEntryTable(entries));
        }
    });
});

function travellerPaymentStatusBadge(status) {
    var cls = 'secondary';
    if (status === 'paid') cls = 'success';
    else if (status === 'pending') cls = 'warning text-dark';
    else if (status === 'failed') cls = 'danger';
    return '<span class="badge bg-' + cls + '">' + (status || '-') + '</span>';
}

$(document).on('click', '.traveller-row', function() {
    var tripNum = $(this).data('trip-num');
    var tripCode = $(this).data('trip-id');
    var user = $(this).data('user');
    var dueAttr = $(this).data('due');
    var paidAttr = $(this).data('paid');

    var header = '<div class="mb-3">';
    header += '<p class="mb-1"><strong>Trip:</strong> ' + (tripNum ? '<a href="/trip-manager/' + tripNum + '" target="_blank">' + tripCode + '</a>' : tripCode) + '</p>';
    header += '<p class="mb-1"><strong>Traveller:</strong> ' + user + '</p>';
    header += '</div>';

    $('#travellerEntriesBody').html(header + '<p class="text-muted text-center">Loading payment history...</p>');
    new bootstrap.Modal('#travellerEntriesModal').show();

    if (!tripNum) {
        $('#travellerEntriesBody').html(header + '<p class="text-muted text-center">No trip linked.</p>');
        return;
    }

    ajaxPost({ get_traveller_payment_history: 1, trip_id: tripNum }, function(resp) {
        var payments = resp.payments || [];
        var totalDue = Number(resp.total_due != null ? resp.total_due : dueAttr) || 0;
        var totalPaid = Number(resp.total_paid != null ? resp.total_paid : paidAttr) || 0;
        var balance = Number(resp.balance != null ? resp.balance : (totalDue - totalPaid));

        var html = header;
        html += '<div class="row text-center mb-3">';
        html += '<div class="col"><div class="small text-muted">Total Due</div><div class="fw-bold">₹' + totalDue.toLocaleString() + '</div></div>';
        html += '<div class="col"><div class="small text-muted">Total Paid</div><div class="fw-bold text-success">₹' + totalPaid.toLocaleString() + '</div></div>';
        html += '<div class="col"><div class="small text-muted">Balance</div><div class="fw-bold ' + (balance > 0 ? 'text-danger' : 'text-success') + '">₹' + balance.toLocaleString() + '</div></div>';
        html += '</div>';

        if (!payments.length) {
            html += '<p class="text-muted text-center mb-0">No payments recorded yet.</p>';
        } else {
            html += '<h6 class="small fw-bold">Payment History</h6>';
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
            html += '<thead class="table-light"><tr><th>Date</th><th class="text-end">Amount</th><th>Mode</th><th>Status</th><th>Recorded By</th></tr></thead><tbody>';
            payments.forEach(function(p) {
                html += '<tr>';
                html += '<td><small>' + (p.payment_date ? String(p.payment_date).substring(0, 10) : '-') + '</small></td>';
                html += '<td class="text-end fw-semibold"><small>₹' + Number(p.amount || 0).toLocaleString() + '</small></td>';
                html += '<td><small>' + (p.mode ? p.mode.replace(/_/g, ' ') : '-') + '</small></td>';
                html += '<td>' + travellerPaymentStatusBadge(p.payment_status || p.status) + '</td>';
                html += '<td><small>' + (p.recorder ? (p.recorder.full_name || p.recorder.email || '-') : '-') + '</small></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
        }
        $('#travellerEntriesBody').html(html);
    }, function() {
        $('#travellerEntriesBody').html(header + '<p class="text-danger text-center mb-0">Failed to load payment history.</p>');
    });
});

$('a[data-bs-toggle="pill"]').on('shown.bs.tab', function(e) {
    if ($(e.target).attr('href') === '#travellerPayments') {
        loadTravellerPayments();
    } else {
        loadSpPayments();
    }
});
</script>
@endsection
