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

    <div class="card" style="max-width:680px;">
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
                @csrf
                <div class="mb-3">
                    <label for="testEmail" class="form-label">Recipient email address</label>
                    <input type="email" class="form-control" id="testEmail" name="email" required value="{{ auth()->user()->email ?? '' }}">
                    <div class="form-text">All five emails below will be sent to this address with sample data.</div>
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

            <div id="emailTestResult" class="mt-4" style="display:none;"></div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
(function() {
    const form = document.getElementById('emailTestForm');
    const btn = document.getElementById('btnSendAll');
    const resultBox = document.getElementById('emailTestResult');

    function setRowStatus(key, status, errorMsg) {
        const row = document.querySelector('#emailList li[data-key="' + key + '"] .status-badge');
        if (!row) return;
        row.className = 'badge status-badge';
        if (status === 'sent') {
            row.classList.add('bg-success');
            row.textContent = 'sent';
        } else if (status === 'failed') {
            row.classList.add('bg-danger');
            row.textContent = 'failed';
            row.title = errorMsg || '';
        } else {
            row.classList.add('bg-secondary');
            row.textContent = 'pending';
        }
    }

    function resetRows() {
        document.querySelectorAll('#emailList .status-badge').forEach(b => {
            b.className = 'badge bg-warning text-dark status-badge';
            b.textContent = 'sending...';
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const email = document.getElementById('testEmail').value.trim();
        if (!email) return;

        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';
        resultBox.style.display = 'none';
        resetRows();

        fetch('{{ url('/email-test/send') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ email: email })
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = original;

            if (!data.success) {
                resultBox.style.display = 'block';
                resultBox.className = 'alert alert-danger';
                resultBox.textContent = 'Request failed.';
                return;
            }

            let okCount = 0, failCount = 0;
            Object.keys(data.results).forEach(key => {
                const r = data.results[key];
                setRowStatus(key, r.status, r.error);
                if (r.status === 'sent') okCount++; else failCount++;
            });

            resultBox.style.display = 'block';
            resultBox.className = 'alert ' + (failCount === 0 ? 'alert-success' : 'alert-warning');
            resultBox.innerHTML = '<strong>Done.</strong> ' + okCount + ' sent, ' + failCount + ' failed. Recipient: <code>' + data.to + '</code> via <code>' + data.mailer + '</code>.';
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = original;
            resultBox.style.display = 'block';
            resultBox.className = 'alert alert-danger';
            resultBox.textContent = 'Error: ' + err.message;
        });
    });
})();
</script>
@endsection
