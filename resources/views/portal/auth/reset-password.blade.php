@extends('portal.layout')
@section('title', 'Reset Password - HECO Portal')

@section('content')
<div class="resetwrap">
    <div class="resetwrap-card">
        <div class="resetwrap-hero">
            <h1 class="resetwrap-heading">Reset your password</h1>
            <p class="resetwrap-subheading">Choose a new password for your account</p>
        </div>

        <div class="resetwrap-body">
            <div id="resetAlert" class="resetwrap-alert"></div>

            <form id="resetForm" class="resetwrap-form" autocomplete="off">
                <input type="hidden" name="token" value="{{ $token }}">
                {{-- Which account the link was issued for. An address can belong
                     to more than one — a traveller and an HCT login on the same
                     email — so without this the reset would land on whichever
                     row happens to be oldest. --}}
                <input type="hidden" name="role" value="{{ $role }}">

                <div class="mb-4">
                    <label class="resetwrap-label" for="resetEmail">Email Address</label>
                    <input type="email" class="form-control user_email" id="resetEmail" name="email" required autocomplete="email" value="{{ request('email') }}">
                </div>

                <div class="mb-4">
                    <label class="resetwrap-label" for="resetPassword">New Password</label>
                    <div class="resetwrap-password-field">
                        <input type="password" class="form-control user_password" id="resetPassword" name="password" required minlength="8" autocomplete="new-password">
                        <div class="password_toggle" data-target="#resetPassword" role="button" tabindex="0" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </div>
                    </div>
                    <small class="resetwrap-help">Minimum 8 characters</small>
                </div>

                <div class="mb-4">
                    <label class="resetwrap-label" for="resetPasswordConfirm">Confirm New Password</label>
                    <div class="resetwrap-password-field">
                        <input type="password" class="form-control user_password_confirm" id="resetPasswordConfirm" name="password_confirmation" required minlength="8" autocomplete="new-password">
                        <div class="password_toggle" data-target="#resetPasswordConfirm" role="button" tabindex="0" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success submit_btn" id="btnResetSubmit">
                    <i class="bi bi-shield-check me-2"></i> Reset Password
                </button>
            </form>
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
    var reset_form  = jQuery('#resetForm');
    var reset_btn   = jQuery('#btnResetSubmit');
    var reset_alert = jQuery('#resetAlert');

    function show_alert(type, msg) {
        reset_alert.html('<div class="alert alert-' + type + '">' + msg + '</div>');
    }

    // Password visibility toggle (scoped to .resetwrap)
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

        var pw  = jQuery('#resetPassword').val();
        var pw2 = jQuery('#resetPasswordConfirm').val();
        if (pw !== pw2) {
            show_alert('danger', 'Passwords do not match.');
            return;
        }

        var original_html = reset_btn.html();
        reset_btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Resetting...');
        reset_alert.empty();

        jQuery.ajax({
            url: '/reset-password',
            method: 'POST',
            data: reset_form.serialize(),
            headers: { 'Accept': 'application/json' },
            skipGlobalError: true,
            success: function(resp) {
                if (resp.success) {
                    show_alert('success',
                        '<i class="bi bi-check-circle me-1"></i> ' +
                        (resp.message || 'Password reset. Redirecting to login...')
                    );
                    setTimeout(function() {
                        window.location.href = resp.redirect || '/login';
                    }, 800);
                } else {
                    reset_btn.prop('disabled', false).html(original_html);
                    show_alert('danger', resp.error || resp.message || 'Reset failed.');
                }
            },
            error: function(xhr) {
                reset_btn.prop('disabled', false).html(original_html);
                var msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                    ? (xhr.responseJSON.error || xhr.responseJSON.message)
                    : 'Reset failed. Please try again or request a new link.';
                show_alert('danger', msg);
            }
        });
    });
});
</script>
@endsection
