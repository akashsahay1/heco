@extends('portal.layout')
@section('title', 'Profile - HECO Portal')

@section('css')
<style>
    .profile-avatar {
        width: 80px; height: 80px; border-radius: 50%;
        object-fit: cover; border: 3px solid var(--heco-green);
    }
    .profile-avatar-placeholder {
        width: 80px; height: 80px; border-radius: 50%;
        background: var(--heco-green); color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; font-weight: 600;
    }
    .auth-badge { font-size: 0.75rem; }
</style>
@endsection

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
                        @if($user->avatar)
                            <img src="{{ $user->avatar }}" alt="Avatar" class="profile-avatar me-3">
                        @elseif($user->photo)
                            <img src="/storage/{{ $user->photo }}" alt="Photo" class="profile-avatar me-3">
                        @else
                            <div class="profile-avatar-placeholder me-3">
                                {{ strtoupper(substr($user->full_name ?? $user->email, 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <h5 class="mb-1">{{ $user->full_name ?? 'Traveller' }}</h5>
                            <span class="text-muted small">{{ $user->email }}</span><br>
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
                        <div class="mb-3">
                            <label for="mobile" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="mobile" value="{{ $user->mobile ?? '' }}" placeholder="+91 9876543210">
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" rows="3" placeholder="Your city, state, country...">{{ $user->address ?? '' }}</textarea>
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
            address: $('#address').val(),
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
</script>
@endsection
