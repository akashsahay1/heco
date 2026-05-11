@extends('portal.layout')
@section('title', 'Profile - HECO Portal')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <h3 class="mb-4"><i class="bi bi-person-circle text-success"></i> Your Profile</h3>

            {{-- Profile Card --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    {{-- Avatar and auth type --}}
                    <div class="d-flex align-items-center mb-4">
                        <div class="profile-avatar-wrap me-3">
                            @if($user->avatar)
                                <img src="{{ $user->avatar }}" alt="Avatar" class="profile-avatar" id="profileAvatarImg">
                            @elseif($user->photo)
                                <img src="/storage/{{ $user->photo }}" alt="Photo" class="profile-avatar" id="profileAvatarImg">
                            @else
                                <div class="profile-avatar-placeholder" id="profileAvatarPlaceholder">
                                    {{ strtoupper(substr($user->full_name ?? $user->email, 0, 1)) }}
                                </div>
                                <img src="" alt="Avatar" class="profile-avatar profile-avatar-hidden" id="profileAvatarImg">
                            @endif
                            <button type="button" class="profile-avatar-edit-btn" id="profilePhotoBtn" aria-label="Change profile photo">
                                <i class="bi bi-camera-fill"></i>
                            </button>
                            <input type="file" id="profilePhotoInput" accept="image/jpeg,image/png,image/webp" class="profile-photo-input">
                        </div>
                        <div>
                            <h5 class="mb-1">{{ $user->full_name ?? 'Traveller' }}</h5>
                            <span class="text-muted small">{{ $user->email }}</span><br>
                            <button type="button" class="btn btn-link btn-sm p-0 profile-photo-link" id="profilePhotoLink">
                                <i class="bi bi-image"></i> Change photo
                            </button><br>
                            <span class="profile-photo-status" id="profilePhotoStatus"></span>
                            @php
                                $authBadgeColor = match($user->auth_type ?? 'email') {
                                    'google' => 'bg-danger',
                                    'facebook' => 'bg-primary',
                                    default => 'bg-secondary',
                                };
                                $authIcon = match($user->auth_type ?? 'email') {
                                    'google' => 'bi-google',
                                    'facebook' => 'bi-facebook',
                                    default => 'bi-envelope',
                                };
                            @endphp
                            <span class="badge auth-badge {{ $authBadgeColor }} mt-1">
                                <i class="bi {{ $authIcon }}"></i> {{ ucfirst($user->auth_type ?? 'email') }} Account
                            </span>
                        </div>
                    </div>

                    <div id="profile-alert"></div>

                    <form id="profileForm">
                        <div class="mb-3">
                            <label for="fullName" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="fullName" value="{{ $user->full_name ?? '' }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="emailDisplay" class="form-label">Email</label>
                            <input type="email" class="form-control" id="emailDisplay" value="{{ $user->email }}" readonly disabled>
                            <div class="form-text">Email cannot be changed.</div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label for="mobile" class="form-label">Mobile Number</label>
                                <input type="text" class="form-control" id="mobile" value="{{ $user->mobile ?? '' }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="dateOfBirth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dateOfBirth" value="{{ optional($user->date_of_birth)->format('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender">
                                <option value="" {{ empty($user->gender) ? 'selected' : '' }}>—</option>
                                <option value="male" {{ $user->gender === 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ $user->gender === 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other" {{ $user->gender === 'other' ? 'selected' : '' }}>Other</option>
                                <option value="prefer_not_to_say" {{ $user->gender === 'prefer_not_to_say' ? 'selected' : '' }}>Prefer not to say</option>
                            </select>
                        </div>

                        <h6 class="text-muted small text-uppercase mt-4 mb-2"><i class="bi bi-geo-alt"></i> Address</h6>
                        <div class="mb-3">
                            <label for="address1" class="form-label">Address Line 1</label>
                            <input type="text" class="form-control" id="address1" value="{{ $user->address1 ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label for="address2" class="form-label">Address Line 2 <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control" id="address2" value="{{ $user->address2 ?? '' }}">
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" value="{{ $user->city ?? '' }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="state" class="form-label">State / Province</label>
                                <input type="text" class="form-control" id="state" value="{{ $user->state ?? '' }}">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label for="country" class="form-label">Country</label>
                                <select class="form-select" id="country">
                                    <option value="">Select country</option>
                                    @foreach(config('countries.list') as $country)
                                        <option value="{{ $country }}" {{ ($user->country ?? '') === $country ? 'selected' : '' }}>{{ $country }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="postalCode" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postalCode" value="{{ $user->postal_code ?? '' }}">
                            </div>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="newsletterOptin" {{ ($user->newsletter_optin ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="newsletterOptin">
                                    <i class="bi bi-envelope-heart"></i> Subscribe to newsletter
                                </label>
                                <div class="form-text">Receive updates about new experiences and destinations.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="portalNotifyOptin" {{ ($user->portal_notify_optin ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="portalNotifyOptin">
                                    <i class="bi bi-bell"></i> Portal notifications
                                </label>
                                <div class="form-text">Get notified about trip updates and support replies.</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-check-lg"></i> Save Profile
                        </button>
                    </form>
                </div>
            </div>

            {{-- Password Change Card (only for email auth) --}}
            @if(($user->auth_type ?? 'email') === 'email')
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-shield-lock"></i> Change Password</h6>
                </div>
                <div class="card-body p-4">
                    <div id="password-alert"></div>

                    <form id="passwordForm">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" required>
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" required minlength="8">
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmPassword" required minlength="8">
                        </div>

                        <button type="submit" class="btn btn-outline-success w-100">
                            <i class="bi bi-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
@endsection

@section('js')
<script>
$(function() {

    // Save profile
    $('#profileForm').on('submit', function(e) {
        e.preventDefault();
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

        ajaxPost({
            update_profile: 1,
            full_name: $('#fullName').val(),
            mobile: $('#mobile').val(),
            date_of_birth: $('#dateOfBirth').val(),
            gender: $('#gender').val(),
            address1: $('#address1').val(),
            address2: $('#address2').val(),
            city: $('#city').val(),
            state: $('#state').val(),
            country: $('#country').val(),
            postal_code: $('#postalCode').val(),
            newsletter_optin: $('#newsletterOptin').is(':checked') ? 1 : 0,
            portal_notify_optin: $('#portalNotifyOptin').is(':checked') ? 1 : 0
        }, function(resp) {
            btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Save Profile');
            $('#profile-alert').html('<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> Profile updated successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        }, function(xhr) {
            btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Save Profile');
            var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Failed to update profile.') : 'Failed to update profile.';
            $('#profile-alert').html('<div class="alert alert-danger alert-dismissible fade show">' + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        });
    });

    // Change password
    $('#passwordForm').on('submit', function(e) {
        e.preventDefault();
        var newPw = $('#newPassword').val();
        var confirmPw = $('#confirmPassword').val();

        if (newPw !== confirmPw) {
            $('#password-alert').html('<div class="alert alert-danger alert-dismissible fade show">Passwords do not match.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            return;
        }

        if (newPw.length < 8) {
            $('#password-alert').html('<div class="alert alert-danger alert-dismissible fade show">Password must be at least 8 characters.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            return;
        }

        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Changing...');

        ajaxPost({
            change_password: 1,
            current_password: $('#currentPassword').val(),
            new_password: newPw,
            new_password_confirmation: confirmPw
        }, function(resp) {
            btn.prop('disabled', false).html('<i class="bi bi-key"></i> Change Password');
            $('#passwordForm')[0].reset();
            $('#password-alert').html('<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> Password changed successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        }, function(xhr) {
            btn.prop('disabled', false).html('<i class="bi bi-key"></i> Change Password');
            var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Failed to change password.') : 'Failed to change password.';
            $('#password-alert').html('<div class="alert alert-danger alert-dismissible fade show">' + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        });
    });

});

// Profile photo upload (AJAX, multipart/form-data).
jQuery(function() {
    var maxBytes = 4 * 1024 * 1024;

    function pickPhoto() { jQuery('#profilePhotoInput').trigger('click'); }
    jQuery('#profilePhotoBtn, #profilePhotoLink').on('click', pickPhoto);

    function setPhotoStatus(text, isError) {
        var el = jQuery('#profilePhotoStatus');
        el.text(text || '').toggleClass('profile-photo-status-error', !!isError);
    }

    jQuery('#profilePhotoInput').on('change', function() {
        var file = this.files && this.files[0];
        if (!file) return;
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            setPhotoStatus('Please choose a JPG, PNG or WEBP image.', true);
            this.value = '';
            return;
        }
        if (file.size > maxBytes) {
            setPhotoStatus('The image must be 4 MB or smaller.', true);
            this.value = '';
            return;
        }

        var fd = new FormData();
        fd.append('upload_profile_photo', 1);
        fd.append('profile_photo', file);

        var input = this;
        setPhotoStatus('Uploading...', false);
        jQuery('#profilePhotoBtn').prop('disabled', true);

        jQuery.ajax({
            url: '/ajax',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            skipGlobalError: true,
            success: function(resp) {
                jQuery('#profilePhotoBtn').prop('disabled', false);
                input.value = '';
                if (resp && resp.avatar) {
                    var bust = resp.avatar + '?t=' + Date.now();
                    jQuery('#profileAvatarImg').attr('src', bust).removeClass('profile-avatar-hidden');
                    jQuery('#profileAvatarPlaceholder').addClass('profile-avatar-hidden');
                    jQuery('#headerUserAvatarImg').attr('src', bust).removeClass('profile-avatar-hidden');
                    jQuery('#headerUserAvatarIcon').addClass('profile-avatar-hidden');
                }
                setPhotoStatus('Photo updated.', false);
            },
            error: function(xhr) {
                jQuery('#profilePhotoBtn').prop('disabled', false);
                input.value = '';
                var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Could not upload the photo. Please try again.';
                setPhotoStatus(msg, true);
            }
        });
    });
});
</script>
@endsection
