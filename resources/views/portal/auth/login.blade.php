@extends('portal.layout')
@section('title', 'Login - HECO Portal')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4"><i class="bi bi-mountain text-success"></i> Login</h3>

                    <div id="login-alert"></div>

                    <form id="loginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <button type="submit" class="btn btn-success w-100 mb-3">Login</button>
                    </form>

                    <div class="text-center mb-3">
                        <a href="/forgot-password" class="text-muted small">Forgot password?</a>
                    </div>

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
                        Don't have an account? <a href="/register" class="text-success">Register</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
$('#loginForm').on('submit', function(e) {
    e.preventDefault();
    ajaxPost({
        userlogin: 1,
        email: $('#email').val(),
        password: $('#password').val(),
        remember: $('#remember').is(':checked') ? 1 : 0
    }, function(resp) {
        if (resp.success) {
            window.location.href = resp.redirect || '/home';
        }
    }, function(xhr) {
        let msg = xhr.responseJSON ? xhr.responseJSON.error : 'Login failed';
        $('#login-alert').html('<div class="alert alert-danger">' + msg + '</div>');
    });
});
</script>
@endsection
