@extends('portal.layout')
@section('title', 'Sign Up - HECO Portal')

@section('content')
<div class="signupwrap">
    <div class="signupwrap-card">
        <div class="signupwrap-hero">
            <h1 class="signupwrap-heading">Create your HECO account</h1>
            <p class="signupwrap-subheading">Plan, save and book regenerative experiences across the Himalayas</p>
        </div>

        <div class="signupwrap-body">
            <div id="signupAlert" class="signupwrap-alert"></div>

            <form id="signupForm" class="signupwrap-form" autocomplete="on">
                {{-- Personal --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupFirstName">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control user_firstname" id="signupFirstName" name="first_name" required autocomplete="given-name">
                    </div>
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupLastName">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control user_lastname" id="signupLastName" name="last_name" required autocomplete="family-name">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupEmail">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control user_email" id="signupEmail" name="email" required autocomplete="email">
                    </div>
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupPhone">Phone Number</label>
                        <input type="tel" class="form-control user_phone" id="signupPhone" name="phone" autocomplete="tel">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupPassword">Password <span class="text-danger">*</span></label>
                        <div class="signupwrap-password-field">
                            <input type="password" class="form-control user_password" id="signupPassword" name="password" required minlength="8" autocomplete="new-password">
                            <div class="password_toggle" data-target="#signupPassword" role="button" tabindex="0" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </div>
                        </div>
                        <small class="signupwrap-help">Minimum 8 characters</small>
                    </div>
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupPasswordConfirm">Confirm Password <span class="text-danger">*</span></label>
                        <div class="signupwrap-password-field">
                            <input type="password" class="form-control user_password_confirm" id="signupPasswordConfirm" name="password_confirmation" required minlength="8" autocomplete="new-password">
                            <div class="password_toggle" data-target="#signupPasswordConfirm" role="button" tabindex="0" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupDob">Date of Birth</label>
                        <div class="signupwrap-dob-field">
                            <input type="text" class="form-control user_dob" id="signupDob" name="date_of_birth" autocomplete="bday" readonly>
                            <i class="bi bi-calendar3 dob_icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupGender">Gender</label>
                        <select class="custom-select user_gender" id="signupGender" name="gender">
                            <option value="">Prefer not to say</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                            <option value="prefer_not_to_say">Prefer not to say</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupNationality">Nationality <span class="text-danger">*</span></label>
                        <select class="custom-select user_nationality" id="signupNationality" name="nationality" required>
                            <option value="">Select nationality</option>
                            @foreach($countries as $country)
                                <option value="{{ $country }}">{{ $country }}</option>
                            @endforeach
                        </select>
                        <small class="signupwrap-help">Determines applicable pricing (Indian / foreign national).</small>
                    </div>
                </div>

                {{-- Address --}}
                <h6 class="signupwrap-section-heading"><i class="bi bi-geo-alt"></i> Address</h6>

                <div class="mb-4">
                    <label class="signupwrap-label" for="signupAddress1">Address Line 1</label>
                    <input type="text" class="form-control user_address1" id="signupAddress1" name="address1" autocomplete="address-line1">
                </div>

                <div class="mb-4">
                    <label class="signupwrap-label" for="signupAddress2">Address Line 2 <small class="text-muted">(optional)</small></label>
                    <input type="text" class="form-control user_address2" id="signupAddress2" name="address2" autocomplete="address-line2">
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupCity">City</label>
                        <input type="text" class="form-control user_city" id="signupCity" name="city" autocomplete="address-level2">
                    </div>
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupState">State / Province</label>
                        <input type="text" class="form-control user_state" id="signupState" name="state" autocomplete="address-level1">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupCountry">Country</label>
                        <select class="custom-select user_country" id="signupCountry" name="country">
                            <option value="">Select country</option>
                            @foreach($countries as $country)
                                <option value="{{ $country }}" {{ $country === 'India' ? 'selected' : '' }}>{{ $country }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="signupwrap-label" for="signupPostal">Postal Code</label>
                        <input type="text" class="form-control user_postal" id="signupPostal" name="postal_code" autocomplete="postal-code">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="check-control terms_check" for="signupTerms">
                        <input type="checkbox" id="signupTerms" class="check-control-input" required>
                        <span class="check-control-icon"><i class="fas fa-check"></i></span>
                        <span class="check-control-label">
                            I agree to the <a href="/terms" target="_blank">Terms of Service</a> and <a href="/privacy-policy" target="_blank">Privacy Policy</a>
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn btn-success submit_btn" id="btnSignupSubmit">
                    <i class="bi bi-person-plus me-2"></i> Create Account
                </button>
            </form>

            <div class="signupwrap-divider"><span>or sign up with</span></div>

            <div class="signupwrap-social-row">
                <a href="/auth/google/redirect" class="social_icon_btn" title="Sign up with Google" aria-label="Sign up with Google">
                    <svg viewBox="0 0 24 24" width="22" height="22"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                </a>
                <a href="/auth/facebook/redirect" class="social_icon_btn" title="Sign up with Facebook" aria-label="Sign up with Facebook">
                    <svg viewBox="0 0 24 24" width="22" height="22"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
            </div>
        </div>

        <div class="signupwrap-footer">
            Already have an account? <a href="/login">Sign in</a>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
jQuery(function() {
    var signup_form  = jQuery('#signupForm');
    var signup_btn   = jQuery('#btnSignupSubmit');
    var signup_alert = jQuery('#signupAlert');

    function show_alert(type, msg) {
        signup_alert.html('<div class="alert alert-' + type + '">' + msg + '</div>');
        jQuery('html, body').animate({ scrollTop: signup_alert.offset().top - 100 }, 200);
    }

    // Date of birth — Air Datepicker (no native type=date per coding rules).
    // English locale, format dd-MM-yyyy, can't be in the future.
    new AirDatepicker('#signupDob', {
        locale: window.airDatepickerEn,
        dateFormat: 'dd-MM-yyyy',
        autoClose: true,
        maxDate: new Date(),
        view: 'years',
        position: 'bottom left'
    });

    // Password visibility toggle
    jQuery('.signupwrap .password_toggle').on('click', function() {
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

    // AJAX submission — CSRF token comes from the global ajaxSetup header
    // in portal/layout.blade.php; no @csrf hidden field needed.
    signup_form.on('submit', function(e) {
        e.preventDefault();

        var pw  = jQuery('#signupPassword').val();
        var pw2 = jQuery('#signupPasswordConfirm').val();
        if (pw !== pw2) {
            show_alert('danger', 'Passwords do not match.');
            return;
        }
        if (!jQuery('#signupTerms').is(':checked')) {
            show_alert('danger', 'Please agree to the Terms of Service to continue.');
            return;
        }

        // Convert DOB from dd-mm-yyyy to yyyy-mm-dd for Laravel validation
        var dob_input = jQuery('#signupDob').val().trim();
        var dob_iso = '';
        if (dob_input) {
            var parts = dob_input.split('-');
            if (parts.length === 3) {
                dob_iso = parts[2] + '-' + parts[1] + '-' + parts[0];
            }
        }

        var original_html = signup_btn.html();
        signup_btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Creating account...');
        signup_alert.empty();

        var payload = signup_form.serializeArray();
        for (var i = 0; i < payload.length; i++) {
            if (payload[i].name === 'date_of_birth') {
                payload[i].value = dob_iso;
            }
        }
        payload.push({ name: 'register', value: '1' });

        jQuery.ajax({
            url: '/ajax',
            method: 'POST',
            data: payload,
            skipGlobalError: true,
            success: function(resp) {
                if (resp.success) {
                    show_alert('success', '<i class="bi bi-check-circle me-1"></i> Account created. Redirecting...');
                    setTimeout(function() {
                        window.location.href = resp.redirect || '/home';
                    }, 600);
                } else {
                    signup_btn.prop('disabled', false).html(original_html);
                    show_alert('danger', resp.error || 'Sign-up failed. Please try again.');
                }
            },
            error: function(xhr) {
                signup_btn.prop('disabled', false).html(original_html);
                var msg = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Sign-up failed. Please review your details and try again.';
                show_alert('danger', msg);
            }
        });
    });
});
</script>
@endsection
