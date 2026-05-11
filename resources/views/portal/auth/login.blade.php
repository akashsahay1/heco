@extends('portal.layout')
@section('title', 'Login - HECO Portal')

@section('content')
<div class="loginwrap">
    <div class="loginwrap-card">
        <div class="loginwrap-hero">
            <h1 class="loginwrap-heading">Welcome back</h1>
            <p class="loginwrap-subheading">Sign in to continue planning your journey</p>
        </div>

        <div class="loginwrap-body">
            <div id="loginAlert" class="loginwrap-alert"></div>

            <form id="loginForm" class="loginwrap-form" autocomplete="on">
                <div class="mb-4">
                    <label class="loginwrap-label" for="loginEmail">Email Address</label>
                    <input type="email" class="form-control user_email" id="loginEmail" name="email" required autocomplete="email">
                </div>

                <div class="mb-4">
                    <div class="loginwrap-label-row">
                        <label class="loginwrap-label" for="loginPassword">Password</label>
                        <a href="/forgot-password" class="loginwrap-link-muted">Forgot password?</a>
                    </div>
                    <div class="loginwrap-password-field">
                        <input type="password" class="form-control user_password" id="loginPassword" name="password" required autocomplete="current-password">
                        <div class="password_toggle" data-target="#loginPassword" role="button" tabindex="0" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="check-control remember_me" for="loginRemember">
                        <input type="checkbox" id="loginRemember" name="remember" class="check-control-input">
                        <span class="check-control-icon"><i class="fas fa-check"></i></span>
                        <span class="check-control-label">Remember Me</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-success submit_btn" id="btnLoginSubmit">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Login
                </button>
            </form>

            <div class="loginwrap-divider"><span>or continue with</span></div>

            <div class="loginwrap-social-row">
                <a href="/auth/google/redirect" class="social_icon_btn" title="Continue with Google" aria-label="Continue with Google">
                    <svg viewBox="0 0 24 24" width="22" height="22"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                </a>
                <a href="/auth/facebook/redirect" class="social_icon_btn" title="Continue with Facebook" aria-label="Continue with Facebook">
                    <svg viewBox="0 0 24 24" width="22" height="22"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
            </div>
        </div>

        <div class="loginwrap-footer">
            Don't have an account? <a href="/sign-up">Create one</a>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
jQuery(function() {
    var login_form  = jQuery('#loginForm');
    var login_btn   = jQuery('#btnLoginSubmit');
    var login_alert = jQuery('#loginAlert');

    function show_alert(type, msg) {
        login_alert.html('<div class="alert alert-' + type + '">' + msg + '</div>');
    }

    // Password visibility toggle
    jQuery('.loginwrap .password_toggle').on('click', function() {
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

    // AJAX login submission — CSRF token is sent via the global ajaxSetup
    // header in portal/layout.blade.php; we don't need a hidden @csrf field.
    login_form.on('submit', function(e) {
        e.preventDefault();
        var original_html = login_btn.html();
        login_btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Signing in...');
        login_alert.empty();

        jQuery.ajax({
            url: '/ajax',
            method: 'POST',
            data: login_form.serialize() + '&userlogin=1',
            skipGlobalError: true,
            success: function(resp) {
                if (resp.success) {
                    show_alert('success', '<i class="bi bi-check-circle me-1"></i> Signed in. Redirecting...');
                    setTimeout(function() {
                        window.location.href = resp.redirect || '/home';
                    }, 400);
                } else {
                    login_btn.prop('disabled', false).html(original_html);
                    show_alert('danger', resp.error || 'Login failed.');
                }
            },
            error: function(xhr) {
                login_btn.prop('disabled', false).html(original_html);
                var msg = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Login failed. Please check your credentials and try again.';
                show_alert('danger', msg);
            }
        });
    });
});
</script>
@endsection
