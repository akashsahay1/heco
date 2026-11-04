@extends('admin.layout')
@section('title', 'GST & Reporting - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-receipt"></i> GST & Reporting</h5>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">From</label>
                <input type="text" class="form-control form-control-sm" id="gstFromDisplay" placeholder="dd-mm-yyyy" autocomplete="off" readonly>
                <input type="hidden" id="gstFrom">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">To</label>
                <input type="text" class="form-control form-control-sm" id="gstToDisplay" placeholder="dd-mm-yyyy" autocomplete="off" readonly>
                <input type="hidden" id="gstTo">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" id="generateBtn"><i class="bi bi-arrow-repeat"></i> Generate</button>
                <button class="btn btn-sm btn-outline-success" id="exportBtn"><i class="bi bi-file-earmark-spreadsheet"></i> Export to Excel</button>
            </div>
        </div>
        <small class="text-muted d-block mt-1">A trip is counted on the day it was created.</small>
    </div>
</div>

<div class="row g-3 mb-3" id="summaryCards">
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body text-center">
                <div class="small text-muted">Total Revenue</div>
                <div class="fs-4 fw-bold text-success" id="totalRevenue">-</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body text-center">
                <div class="small text-muted">GST Collected</div>
                <div class="fs-4 fw-bold text-warning" id="totalGst">-</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body text-center">
                <div class="small text-muted">Net Revenue</div>
                <div class="fs-4 fw-bold text-primary" id="netRevenue">-</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Trip-Level Breakdown</h6>
        <span class="badge bg-secondary" id="tripCount">0 trips</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Trip ID</th>
                        <th>Created</th>
                        <th>Traveller</th>
                        <th>Trip Dates</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">GST %</th>
                        <th class="text-end">GST Amount</th>
                        <th class="text-end">Final Price</th>
                    </tr>
                </thead>
                <tbody id="gstTable">
                    <tr><td colspan="8" class="text-center text-muted">Choose the dates and click Generate</td></tr>
                </tbody>
                <tfoot id="gstTotals" class="table-light d-none">
                    <tr class="fw-bold">
                        <td colspan="4" class="text-end">Totals:</td>
                        <td class="text-end" id="footSubtotal">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end" id="footGst">-</td>
                        <td class="text-end" id="footFinal">-</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function gstPeriod() {
    return { from_date: $('#gstFrom').val(), to_date: $('#gstTo').val() };
}

// The box shows dd-mm-yyyy; the hidden field keeps yyyy-mm-dd for the server.
function gstIso(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function loadGstReport() {
    $('#gstTable').html('<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>');
    $('#gstTotals').hide();

    ajaxPost($.extend({ get_gst_report: 1 }, gstPeriod()), function(resp) {
        var summary = resp.summary || {};
        var trips = resp.trips || [];

        $('#totalRevenue').text('₹' + Number(summary.total_revenue || 0).toLocaleString());
        $('#totalGst').text('₹' + Number(summary.total_gst || 0).toLocaleString());
        $('#netRevenue').text('₹' + Number(summary.net_revenue || 0).toLocaleString());
        $('#tripCount').text(trips.length + ' trip' + (trips.length !== 1 ? 's' : ''));

        var html = '';
        var totSubtotal = 0, totGst = 0, totFinal = 0;

        if (!trips.length) {
            html = '<tr><td colspan="8" class="text-center text-muted">No trips found for this period</td></tr>';
            $('#gstTotals').hide();
        } else {
            trips.forEach(function(t) {
                var subtotal = Number(t.subtotal || 0);
                var gstAmt = Number(t.gst_amount || 0);
                var finalPrice = Number(t.final_price || 0);
                totSubtotal += subtotal;
                totGst += gstAmt;
                totFinal += finalPrice;

                html += '<tr>';
                html += '<td><a href="/trip-manager/' + (t.id || t.trip_id || '') + '" target="_blank">' + esc(t.trip_id || '-') + '</a></td>';
                html += '<td><small>' + (t.created_at || '-') + '</small></td>';
                html += '<td>' + esc(t.user ? t.user.full_name || '' : '-') + '</td>';
                html += '<td><small>' + (t.start_date ? t.start_date.substring(0, 10) : '-') + ' to ' + (t.end_date ? t.end_date.substring(0, 10) : '-') + '</small></td>';
                html += '<td class="text-end">₹' + subtotal.toLocaleString() + '</td>';
                html += '<td class="text-end">' + (t.gst_percent || 0) + '%</td>';
                html += '<td class="text-end">₹' + gstAmt.toLocaleString() + '</td>';
                html += '<td class="text-end">₹' + finalPrice.toLocaleString() + '</td>';
                html += '</tr>';
            });

            $('#footSubtotal').text('₹' + totSubtotal.toLocaleString());
            $('#footGst').text('₹' + totGst.toLocaleString());
            $('#footFinal').text('₹' + totFinal.toLocaleString());
            $('#gstTotals').show();
        }

        $('#gstTable').html(html);
    });
}

var gstFromPicker, gstToPicker;

$(function() {
    var pick = function(box, hidden) {
        return new AirDatepicker(box, {
            locale: window.airDatepickerEn,
            dateFormat: 'dd-MM-yyyy',
            autoClose: true,
            position: 'bottom left',
            onSelect: function(o) { $(hidden).val(o.date ? gstIso(o.date) : ''); }
        });
    };
    gstFromPicker = pick('#gstFromDisplay', '#gstFrom');
    gstToPicker = pick('#gstToDisplay', '#gstTo');

    // Opens on this month, first day to last. The hidden fields are set here
    // as well: the picker's onSelect fires a tick later, after the first load.
    var now = new Date();
    var first = new Date(now.getFullYear(), now.getMonth(), 1);
    var last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    gstFromPicker.selectDate(first);
    gstToPicker.selectDate(last);
    $('#gstFrom').val(gstIso(first));
    $('#gstTo').val(gstIso(last));
    loadGstReport();
});

$('#generateBtn').on('click', function() { loadGstReport(); });

// The same months as on screen, as a file Excel opens.
$('#exportBtn').on('click', function() {
    var p = gstPeriod();
    if (!p.from_date || !p.to_date) {
        showAlert('Pick a From date and a To date.', 'warning');
        return;
    }
    if (p.from_date > p.to_date) {
        showAlert('From has to come before To.', 'warning');
        return;
    }
    window.location.href = '/gst/export?' + $.param(gstPeriod());
});
</script>
@endsection
