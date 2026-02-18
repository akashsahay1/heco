@extends('portal.layout')
@section('title', 'Register - HECO Portal')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4"><i class="bi bi-mountain text-success"></i> Create Account</h3>

                    <div id="register-alert"></div>

                    <form id="registerForm">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="reg_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="reg_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="reg_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="reg_password" name="password" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100 mb-3">Create Account</button>
                    </form>

                    <hr>
                    <div class="d-grid gap-2">
                        <a href="/auth/google/redirect" class="btn btn-outline-danger">
                            <i class="bi bi-google"></i> Continue with Google
                        </a>
                        <a href="/auth/facebook/redirect" class="btn btn-outline-primary">
                            <i class="bi bi-facebook"></i> Continue with Facebook
                        </a>
                    </div>

                    <p class="text-center mt-3 mb-0">
                        Already have an account? <a href="/login" class="text-success">Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
$('#registerForm').on('submit', function(e) {
    e.preventDefault();
    ajaxPost({
        usersignup: 1,
        full_name: $('#full_name').val(),
        email: $('#reg_email').val(),
        password: $('#reg_password').val(),
        password_confirmation: $('#password_confirmation').val()
    }, function(resp) {
        if (resp.success) {
            window.location.href = resp.redirect || '/home';
        }
    }, function(xhr) {
        let msg = xhr.responseJSON ? xhr.responseJSON.error : 'Registration failed';
        $('#register-alert').html('<div class="alert alert-danger">' + msg + '</div>');
    });
});
</script>
@endsection
