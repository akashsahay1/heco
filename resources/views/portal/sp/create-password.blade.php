@extends('portal.layout')
@section('title', 'Verify your email - HECO Portal')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="text-center mb-4">
                <div class="sp-check-badge mx-auto mb-3">
                    <i class="bi bi-envelope-check"></i>
                </div>
                <h2 class="mb-2">Application submitted!</h2>
                <p class="text-muted">
                    We emailed a 6-digit verification code to
                    <strong>{{ $email }}</strong>. Enter it below and set a password
                    to finish creating your account.
                </p>
            </div>

            <div id="cp-alert"></div>

            <form id="createPasswordForm" novalidate class="sp-form-section">
                <div class="mb-3">
                    <label class="form-label">Verification code *</label>
                    <input type="text" class="form-control form-control-lg text-center"
                        name="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                        placeholder="------" style="letter-spacing:8px;" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Create password *</label>
                    <input type="password" class="form-control" name="password"
                        autocomplete="new-password" required>
                    <div class="form-text">At least 8 characters, with a number and a symbol.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm password *</label>
                    <input type="password" class="form-control" name="password_confirmation"
                        autocomplete="new-password" required>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn sp-btn-primary btn-lg" id="cpSubmit">
                        <i class="bi bi-check2-circle"></i> Verify &amp; continue
                    </button>
                </div>
            </form>

            <div class="text-center mt-3">
                <span class="text-muted small">Didn't get the code?</span>
                <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="cpResend">
                    Resend code
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('css')
<style>
    .sp-check-badge {
        width: 68px; height: 68px; border-radius: 50%;
        background: #eef4f3; color: #79A09F;
        display: flex; align-items: center; justify-content: center;
        font-size: 34px;
    }
</style>
@endsection

@section('js')
<script>
jQuery(function() {
    function alertBox(type, msg) {
        jQuery('#cp-alert').html('<div class="alert alert-' + type + '">' + msg + '</div>');
    }

    jQuery('#createPasswordForm').on('submit', function(e) {
        e.preventDefault();
        jQuery('#cp-alert').empty();

        var otp = jQuery('input[name="otp"]').val().trim();
        var pw = jQuery('input[name="password"]').val();
        var pwc = jQuery('input[name="password_confirmation"]').val();

        if (!/^\d{6}$/.test(otp)) return alertBox('warning', 'Enter the 6-digit code we emailed you.');
        if (pw.length < 8 || !/[0-9]/.test(pw) || !/[^A-Za-z0-9]/.test(pw)) {
            return alertBox('warning', 'Password must be at least 8 characters and include a number and a symbol.');
        }
        if (pw !== pwc) return alertBox('warning', 'The passwords do not match.');

        var $btn = jQuery('#cpSubmit');
        var original = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Verifying...');

        jQuery.ajax({
            url: '/ajax', method: 'POST',
            data: { verify_and_set_password: 1, otp: otp, password: pw, password_confirmation: pwc },
            success: function(resp) {
                if (resp.success) {
                    window.location.href = resp.redirect || '/application-status';
                } else {
                    $btn.prop('disabled', false).html(original);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(original);
                var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Verification failed.') : 'Verification failed.';
                alertBox('danger', msg);
            }
        });
    });

    jQuery('#cpResend').on('click', function() {
        var $link = jQuery(this);
        $link.prop('disabled', true).text('Sending...');
        jQuery.ajax({
            url: '/ajax', method: 'POST', data: { resend_account_otp: 1 },
            success: function(resp) {
                alertBox('success', (resp && resp.message) ? resp.message : "We've sent a new code to your email.");
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Could not resend the code.') : 'Could not resend the code.';
                alertBox('danger', msg);
            },
            complete: function() {
                setTimeout(function() { $link.prop('disabled', false).text('Resend code'); }, 3000);
            }
        });
    });
});
</script>
@endsection
