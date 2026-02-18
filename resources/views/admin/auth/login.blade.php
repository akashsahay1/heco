<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Login - HECO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ url('css/bootstrap.min.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ url('css/bootstrap-icons.min.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ url('css/admin.css') }}?v={{ time() }}" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1e2e 0%, #2d3748 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .admin-login-card {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .admin-login-brand {
            text-align: center;
            font-size: 1.75rem;
            font-weight: 700;
            color: #2d6a4f;
            margin-bottom: 8px;
        }
        .admin-login-subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 24px;
        }
        .admin-login-error {
            background: #f8d7da;
            color: #842029;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="admin-login-card">
        <div class="admin-login-brand">
            <i class="bi bi-mountain"></i> HECO Admin
        </div>
        <p class="admin-login-subtitle">Sign in to your admin account</p>

        <div id="loginError" class="admin-login-error mb-3" style="display: none;"></div>

        <form id="adminLoginForm">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" id="loginEmail" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" id="loginPassword" required>
            </div>
            <button type="submit" class="btn btn-success w-100" id="btnLogin">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
        </form>
    </div>

    <script src="{{ url('js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ url('js/bootstrap.bundle.min.js') }}"></script>
    <script>
        jQuery.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content') }
        });

        jQuery('#adminLoginForm').on('submit', function(e) {
            e.preventDefault();
            var btn = jQuery('#btnLogin');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Signing in...');
            jQuery('#loginError').hide();

            jQuery.ajax({
                url: '/ajax',
                method: 'POST',
                data: {
                    adminlogin: 1,
                    email: jQuery('#loginEmail').val(),
                    password: jQuery('#loginPassword').val()
                },
                success: function(resp) {
                    if (resp.success) {
                        window.location.href = '/dashboard';
                    } else {
                        jQuery('#loginError').text(resp.error || 'Login failed').show();
                        btn.prop('disabled', false).html('<i class="bi bi-box-arrow-in-right me-1"></i> Sign In');
                    }
                },
                error: function(xhr) {
                    var msg = 'Login failed. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    jQuery('#loginError').text(msg).show();
                    btn.prop('disabled', false).html('<i class="bi bi-box-arrow-in-right me-1"></i> Sign In');
                }
            });
        });
    </script>
</body>
</html>
