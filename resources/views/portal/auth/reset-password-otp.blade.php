@extends('portal.layout')
@section('title', 'Enter Reset Code - HECO Portal')

@section('content')
<div class="resetwrap">
    <div class="resetwrap-card">
        <div class="resetwrap-hero">
            <h1 class="resetwrap-heading">Enter your code</h1>
            <p class="resetwrap-subheading">
                We emailed a 6-digit code to <strong>{{ $email }}</strong>.
                Enter it and choose a new password.
            </p>
        </div>

        <div class="resetwrap-body">
            <div id="resetAlert" class="resetwrap-alert">
                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif
            </div>

            <form id="resetOtpForm" class="resetwrap-form" autocomplete="off">
                <div class="mb-4">
                    <label class="resetwrap-label" for="resetOtp">Verification code</label>
                    <input type="text" class="form-control text-center" id="resetOtp" name="otp"
                        inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                        placeholder="------" style="letter-spacing:8px;" required>
                </div>

                <div class="mb-4">
                    <label class="resetwrap-label" for="resetPassword">New Password</label>
                    <div class="resetwrap-password-field">
                        <input type="password" class="form-control" id="resetPassword" name="password" required minlength="8" autocomplete="new-password">
                        <div class="password_toggle" data-target="#resetPassword" role="button" tabindex="0" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </div>
                    </div>
                    <small class="resetwrap-help">At least 8 characters, with a number and a symbol.</small>
                </div>

                <div class="mb-4">
                    <label class="resetwrap-label" for="resetPasswordConfirm">Confirm New Password</label>
                    <div class="resetwrap-password-field">
                        <input type="password" class="form-control" id="resetPasswordConfirm" name="password_confirmation" required minlength="8" autocomplete="new-password">
                        <div class="password_toggle" data-target="#resetPasswordConfirm" role="button" tabindex="0" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success submit_btn" id="btnResetOtpSubmit">
                    <i class="bi bi-shield-check me-2"></i> Reset Password
                </button>
            </form>

            <div class="text-center mt-3">
                <span class="text-muted small">Didn't get the code?</span>
                <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="btnResendCode">Resend code</button>
            </div>
        </div>

        <div class="resetwrap-footer">
            Remembered it? <a href="/login">Back to login</a>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
jQuery(function() {
    var reset_form  = jQuery('#resetOtpForm');
    var reset_btn   = jQuery('#btnResetOtpSubmit');
    var reset_alert = jQuery('#resetAlert');
    var reset_email = @json($email);

    function show_alert(type, msg) {
        reset_alert.html('<div class="alert alert-' + type + '">' + msg + '</div>');
    }

    jQuery('.resetwrap .password_toggle').on('click', function() {
        var toggle_el = jQuery(this);
        var field_el  = jQuery(toggle_el.data('target'));
        var icon_el   = toggle_el.find('i');
        if (field_el.attr('type') === 'password') {
            field_el.attr('type', 'text');
            icon_el.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            field_el.attr('type', 'password');
            icon_el.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    // CSRF token comes from the global ajaxSetup header in portal/layout.blade.php.
    reset_form.on('submit', function(e) {
        e.preventDefault();
        reset_alert.empty();

        var otp = jQuery('#resetOtp').val().trim();
        var pw  = jQuery('#resetPassword').val();
        var pw2 = jQuery('#resetPasswordConfirm').val();

        if (!/^\d{6}$/.test(otp)) return show_alert('danger', 'Enter the 6-digit code we emailed you.');
        if (pw.length < 8 || !/[0-9]/.test(pw) || !/[^A-Za-z0-9]/.test(pw)) {
            return show_alert('danger', 'Password must be at least 8 characters and include a number and a symbol.');
        }
        if (pw !== pw2) return show_alert('danger', 'Passwords do not match.');

        var original_html = reset_btn.html();
        reset_btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Resetting...');

        jQuery.ajax({
            url: '/reset-password-otp',
            method: 'POST',
            data: { otp: otp, password: pw, password_confirmation: pw2 },
            headers: { 'Accept': 'application/json' },
            skipGlobalError: true,
            success: function(resp) {
                if (resp.success) {
                    show_alert('success',
                        '<i class="bi bi-check-circle me-1"></i> ' +
                        (resp.message || 'Password reset. Redirecting to login...')
                    );
                    setTimeout(function() { window.location.href = resp.redirect || '/login'; }, 800);
                } else {
                    reset_btn.prop('disabled', false).html(original_html);
                    show_alert('danger', resp.error || resp.message || 'Reset failed.');
                }
            },
            error: function(xhr) {
                reset_btn.prop('disabled', false).html(original_html);
                var msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                    ? (xhr.responseJSON.error || xhr.responseJSON.message)
                    : 'Reset failed. Please try again or request a new code.';
                show_alert('danger', msg);
            }
        });
    });

    jQuery('#btnResendCode').on('click', function() {
        var $link = jQuery(this).prop('disabled', true).text('Sending...');
        jQuery.ajax({
            url: '/forgot-password',
            method: 'POST',
            data: { email: reset_email },
            headers: { 'Accept': 'application/json' },
            skipGlobalError: true,
            success: function(resp) {
                show_alert('success', (resp && resp.message) ? resp.message : "We've sent a new code to your email.");
            },
            error: function() {
                show_alert('danger', 'Could not resend the code.');
            },
            complete: function() {
                setTimeout(function() { $link.prop('disabled', false).text('Resend code'); }, 3000);
            }
        });
    });
});
</script>
@endsection
