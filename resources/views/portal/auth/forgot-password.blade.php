@extends('portal.layout')
@section('title', 'Forgot Password - HECO Portal')

@section('content')
<div class="forgotwrap">
    <div class="forgotwrap-card">
        <div class="forgotwrap-hero">
            <h1 class="forgotwrap-heading">Forgot your password?</h1>
            <p class="forgotwrap-subheading">Enter your email and we'll send you a 6-digit reset code</p>
        </div>

        <div class="forgotwrap-body">
            <div id="forgotAlert" class="forgotwrap-alert">
                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif
            </div>

            <form id="forgotForm" class="forgotwrap-form" autocomplete="on">
                <div class="mb-4">
                    <label class="forgotwrap-label" for="forgotEmail">Email Address</label>
                    <input type="email" class="form-control user_email" id="forgotEmail" name="email" required autocomplete="email" value="{{ old('email') }}">
                </div>

                <button type="submit" class="btn btn-success submit_btn" id="btnForgotSubmit">
                    <i class="bi bi-envelope-paper me-2"></i> Send Reset Code
                </button>
            </form>
        </div>

        <div class="forgotwrap-footer">
            Remembered it? <a href="/login">Back to login</a>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
jQuery(function() {
    var forgot_form  = jQuery('#forgotForm');
    var forgot_btn   = jQuery('#btnForgotSubmit');
    var forgot_alert = jQuery('#forgotAlert');

    function show_alert(type, msg) {
        forgot_alert.html('<div class="alert alert-' + type + '">' + msg + '</div>');
    }

    // CSRF header is sent automatically via the global ajaxSetup in portal/layout.blade.php.
    forgot_form.on('submit', function(e) {
        e.preventDefault();
        var original_html = forgot_btn.html();
        forgot_btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Sending...');
        forgot_alert.empty();

        jQuery.ajax({
            url: '/forgot-password',
            method: 'POST',
            data: forgot_form.serialize(),
            headers: { 'Accept': 'application/json' },
            skipGlobalError: true,
            success: function(resp) {
                if (resp.success) {
                    show_alert('success',
                        '<i class="bi bi-check-circle me-1"></i> ' +
                        (resp.message || 'A 6-digit reset code has been sent to your email.')
                    );
                    // Move on to the on-site code + new-password step.
                    window.location.href = resp.redirect || '/reset-password-otp';
                } else {
                    forgot_btn.prop('disabled', false).html(original_html);
                    show_alert('danger', resp.error || resp.message || 'Could not send reset code.');
                }
            },
            error: function(xhr) {
                forgot_btn.prop('disabled', false).html(original_html);
                var msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                    ? (xhr.responseJSON.error || xhr.responseJSON.message)
                    : 'Could not send reset link. Please try again.';
                show_alert('danger', msg);
            }
        });
    });
});
</script>
@endsection
