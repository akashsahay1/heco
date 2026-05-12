@extends('admin.layout')
@section('title', 'Email Test - HECO Admin')

@section('content')
<div class="hct-main-content">
    <div class="hct-page-header">
        <div>
            <h1 class="hct-page-title">Email Test</h1>
            <p class="hct-page-subtitle">Send all transactional emails to a test address. Useful for verifying templates, SMTP delivery and inbox rendering.</p>
        </div>
    </div>

    <div class="card email-test-card">
        <div class="card-body p-4">
            <div class="alert alert-info d-flex align-items-start" role="alert">
                <i class="bi bi-info-circle me-2 mt-1"></i>
                <div>
                    <div><strong>Mailer:</strong> <code>{{ config('mail.default') }}</code></div>
                    @if(config('mail.default') === 'log')
                        <div class="small mt-1">Mail driver is set to <code>log</code> — emails will be written to <code>storage/logs/laravel.log</code> instead of being delivered. Switch <code>MAIL_MAILER</code> in <code>.env</code> to <code>smtp</code> for real delivery.</div>
                    @else
                        <div class="small mt-1">Sending via <code>{{ config('mail.mailers.' . config('mail.default') . '.host', '—') }}</code> as <code>{{ config('mail.from.address') }}</code>.</div>
                    @endif
                </div>
            </div>

            <form id="emailTestForm">
                <div class="mb-3">
                    <label for="testEmail" class="form-label">Recipient email address</label>
                    <input type="email" class="form-control" id="testEmail" name="email" required value="{{ auth()->user()->email ?? '' }}">
                    <div class="form-text">All emails below will be sent to this address with sample data.</div>
                </div>

                <ul class="list-group mb-3" id="emailList">
                    <li class="list-group-item d-flex justify-content-between align-items-center" data-key="welcome">
                        <span><i class="bi bi-envelope-heart me-2"></i> Welcome email</span>
                        <span class="badge bg-secondary status-badge">pending</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center" data-key="password_reset">
                        <span><i class="bi bi-key me-2"></i> Password reset</span>
                        <span class="badge bg-secondary status-badge">pending</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center" data-key="booking_confirmation">
                        <span><i class="bi bi-journal-check me-2"></i> Booking confirmation</span>
                        <span class="badge bg-secondary status-badge">pending</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center" data-key="payment_received">
                        <span><i class="bi bi-receipt me-2"></i> Payment received</span>
                        <span class="badge bg-secondary status-badge">pending</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center" data-key="sp_application_received">
                        <span><i class="bi bi-people me-2"></i> SP application received</span>
                        <span class="badge bg-secondary status-badge">pending</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center" data-key="profile_updated">
                        <span><i class="bi bi-person-gear me-2"></i> Profile updated</span>
                        <span class="badge bg-secondary status-badge">pending</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center" data-key="password_changed">
                        <span><i class="bi bi-shield-lock me-2"></i> Password changed</span>
                        <span class="badge bg-secondary status-badge">pending</span>
                    </li>
                </ul>

                <button type="submit" class="btn btn-success" id="btnSendAll">
                    <i class="bi bi-send me-2"></i> Send All Emails
                </button>
            </form>

            <div id="emailTestResult" class="mt-4 d-none"></div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
jQuery(function ($) {
    var $form = $('#emailTestForm');
    var $btn = $('#btnSendAll');
    var $resultBox = $('#emailTestResult');

    function setRowStatus(key, status, errorMsg) {
        var $badge = $('#emailList li[data-key="' + key + '"] .status-badge');
        if (!$badge.length) return;
        $badge.attr('class', 'badge status-badge').removeAttr('title');
        if (status === 'sent') {
            $badge.addClass('bg-success').text('sent');
        } else if (status === 'failed') {
            $badge.addClass('bg-danger').text('failed').attr('title', errorMsg || '');
        } else {
            $badge.addClass('bg-secondary').text('pending');
        }
    }

    function resetRows() {
        $('#emailList .status-badge').attr('class', 'badge bg-warning text-dark status-badge').text('sending...');
    }

    $form.on('submit', function (e) {
        e.preventDefault();

        var email = $.trim($('#testEmail').val());
        if (!email) return;

        var original = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Sending...');
        $resultBox.addClass('d-none');
        resetRows();

        $.ajax({
            url: '{{ url('/email-test/send') }}',
            method: 'POST',
            dataType: 'json',
            data: { email: email },
            skipGlobalError: true
        }).done(function (data) {
            $btn.prop('disabled', false).html(original);
            if (!data || !data.success) {
                $resultBox.removeClass('d-none').attr('class', 'mt-4 alert alert-danger').text('Request failed.');
                return;
            }
            var okCount = 0, failCount = 0;
            $.each(data.results || {}, function (key, r) {
                setRowStatus(key, r.status, r.error);
                if (r.status === 'sent') { okCount++; } else { failCount++; }
            });
            $resultBox.removeClass('d-none')
                .attr('class', 'mt-4 alert ' + (failCount === 0 ? 'alert-success' : 'alert-warning'))
                .html('<strong>Done.</strong> ' + okCount + ' sent, ' + failCount + ' failed. Recipient: <code>' + data.to + '</code> via <code>' + data.mailer + '</code>.');
        }).fail(function (xhr) {
            $btn.prop('disabled', false).html(original);
            var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Request failed.';
            $resultBox.removeClass('d-none').attr('class', 'mt-4 alert alert-danger').text('Error: ' + msg);
        });
    });
});
</script>
@endsection
