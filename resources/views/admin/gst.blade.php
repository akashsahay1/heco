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
                <label class="form-label small mb-1">Month</label>
                <select class="form-select form-select-sm" id="gstMonth" style="width: 140px;">
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Year</label>
                <input type="number" class="form-control form-control-sm" id="gstYear" style="width: 100px;" min="2020" max="2099">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" id="generateBtn"><i class="bi bi-arrow-repeat"></i> Generate</button>
            </div>
        </div>
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
                        <th>Traveller</th>
                        <th>Trip Dates</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">GST %</th>
                        <th class="text-end">GST Amount</th>
                        <th class="text-end">Final Price</th>
                    </tr>
                </thead>
                <tbody id="gstTable">
                    <tr><td colspan="7" class="text-center text-muted">Select month/year and click Generate</td></tr>
                </tbody>
                <tfoot id="gstTotals" class="table-light" style="display: none;">
                    <tr class="fw-bold">
                        <td colspan="3" class="text-end">Totals:</td>
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
function loadGstReport() {
    var month = $('#gstMonth').val();
    var year = $('#gstYear').val();

    if (!month || !year) {
        alert('Please select month and year.');
        return;
    }

    $('#gstTable').html('<tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>');
    $('#gstTotals').hide();

    ajaxPost({ get_gst_report: 1, month: parseInt(month), year: parseInt(year) }, function(resp) {
        var summary = resp.summary || {};
        var trips = resp.trips || [];

        $('#totalRevenue').text('₹' + Number(summary.total_revenue || 0).toLocaleString());
        $('#totalGst').text('₹' + Number(summary.total_gst || 0).toLocaleString());
        $('#netRevenue').text('₹' + Number(summary.net_revenue || 0).toLocaleString());
        $('#tripCount').text(trips.length + ' trip' + (trips.length !== 1 ? 's' : ''));

        var html = '';
        var totSubtotal = 0, totGst = 0, totFinal = 0;

        if (!trips.length) {
            html = '<tr><td colspan="7" class="text-center text-muted">No trips found for this period</td></tr>';
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
                html += '<td><a href="/trip-manager/' + (t.id || t.trip_id) + '" target="_blank">' + (t.trip_id || '-') + '</a></td>';
                html += '<td>' + (t.user ? t.user.full_name || '' : '-') + '</td>';
                html += '<td><small>' + (t.start_date ? t.start_date.substring(0, 10) : '-') + ' &mdash; ' + (t.end_date ? t.end_date.substring(0, 10) : '-') + '</small></td>';
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

$(function() {
    var now = new Date();
    $('#gstMonth').val(now.getMonth() + 1);
    $('#gstYear').val(now.getFullYear());
    loadGstReport();
});

$('#generateBtn').on('click', function() { loadGstReport(); });
</script>
@endsection
