<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HECO Admin')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/fontawesome.min.css') }}" rel="stylesheet">
    <link href="{{ url('style.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ url('css/admin.css') }}?v={{ time() }}" rel="stylesheet">
    @yield('css')
</head>
<body class="hct-body">

    <!-- Sidebar -->
    <aside class="hct-sidebar" id="hctSidebar">
        <div class="hct-sidebar-brand">
            <i class="bi bi-mountain"></i> HECO Admin
        </div>
        <nav class="hct-sidebar-nav">
            <div class="hct-nav-section">OVERVIEW</div>
            <a href="{{ url('/dashboard') }}" class="hct-nav-link {{ request()->routeIs('hct.dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            @if(auth()->user()->isHctAdmin())
            <a href="{{ url('/admin') }}" class="hct-nav-link {{ request()->routeIs('hct.admin') ? 'active' : '' }}">
                <i class="bi bi-shield-lock"></i> Admin
            </a>
            @endif
            <a href="{{ url('/control-panel') }}" class="hct-nav-link {{ request()->routeIs('hct.control-panel') ? 'active' : '' }}">
                <i class="bi bi-sliders"></i> Control Panel
            </a>

            <div class="hct-nav-section">SALES & OPERATIONS</div>
            <a href="{{ url('/leads') }}" class="hct-nav-link {{ request()->routeIs('hct.leads') ? 'active' : '' }}">
                <i class="bi bi-funnel"></i> Leads
            </a>
            <a href="{{ url('/trips') }}" class="hct-nav-link {{ request()->routeIs('hct.trips') ? 'active' : '' }}">
                <i class="bi bi-luggage"></i> Trips
            </a>
            <a href="{{ url('/calendar') }}" class="hct-nav-link {{ request()->routeIs('hct.calendar') ? 'active' : '' }}">
                <i class="bi bi-calendar3"></i> Calendar
            </a>

            <div class="hct-nav-section">FINANCE</div>
            <a href="{{ url('/payments') }}" class="hct-nav-link {{ request()->routeIs('hct.payments') ? 'active' : '' }}">
                <i class="bi bi-cash-stack"></i> Payments
            </a>
            <a href="{{ url('/gst') }}" class="hct-nav-link {{ request()->routeIs('hct.gst') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i> GST
            </a>
            <a href="{{ url('/currencies') }}" class="hct-nav-link {{ request()->routeIs('hct.currencies') ? 'active' : '' }}">
                <i class="bi bi-currency-exchange"></i> Currencies
            </a>

            <div class="hct-nav-section">PEOPLE</div>
            <a href="{{ url('/providers') }}" class="hct-nav-link {{ request()->routeIs('hct.providers') ? 'active' : '' }}">
                <i class="bi bi-people"></i> Providers
            </a>
            <a href="{{ url('/travelers') }}" class="hct-nav-link {{ request()->routeIs('hct.travelers') ? 'active' : '' }}">
                <i class="bi bi-person-lines-fill"></i> Travelers
            </a>
            <a href="{{ url('/provider-applications') }}" class="hct-nav-link {{ request()->routeIs('hct.provider-applications') ? 'active' : '' }}">
                <i class="bi bi-envelope-paper"></i> Applications
            </a>

            <div class="hct-nav-section">CONTENT</div>
            <a href="{{ url('/regions') }}" class="hct-nav-link {{ request()->routeIs('hct.regions*') ? 'active' : '' }}">
                <i class="bi bi-globe-americas"></i> Regions
            </a>
            <a href="{{ url('/experiences') }}" class="hct-nav-link {{ request()->routeIs('hct.experiences*') ? 'active' : '' }}">
                <i class="bi bi-compass"></i> Experiences
            </a>
            <a href="{{ url('/regenerative-projects') }}" class="hct-nav-link {{ request()->routeIs('hct.rp*') ? 'active' : '' }}">
                <i class="bi bi-tree"></i> Regenerative Projects
            </a>
        </nav>
    </aside>

    <!-- Main Area -->
    <div class="hct-main-wrapper" id="hctMainWrapper">
        <header class="hct-topbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link text-muted p-0 me-3" id="sidebarToggle" title="Toggle sidebar">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h5 class="mb-0 text-dark">@yield('title', 'Dashboard')</h5>
            </div>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-muted" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2 fs-5"></i>
                    <span class="d-none d-md-inline">{{ auth()->user()->full_name ?? auth()->user()->email }}</span>
                    <span class="badge bg-success bg-opacity-25 text-success ms-2 d-none d-md-inline small">{{ str_replace('_', ' ', auth()->user()->user_role) }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><span class="dropdown-item-text small text-muted">{{ auth()->user()->email }}</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ url('/logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-1"></i> Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mx-4 mt-3" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mx-4 mt-3" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <main class="hct-content">
            @yield('content')
        </main>
    </div>

    <script src="{{ url('js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ url('js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ url('js/sweetalert2.all.min.js') }}"></script>
    <script>
        jQuery.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content') }
        });

        function showAlert(message, type) {
            type = type || 'success';
            var iconMap = { success: 'success', danger: 'error', warning: 'warning', info: 'info' };
            Swal.fire({
                text: message,
                icon: iconMap[type] || 'info',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        }

        function confirmAction(message, callback) {
            Swal.fire({
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2d6a4f',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, proceed'
            }).then(function(result) {
                if (result.isConfirmed && callback) callback();
            });
        }

        function ajaxPost(data, callback, errorCallback) {
            jQuery.ajax({
                url: '/ajax',
                method: 'POST',
                data: data,
                success: function(response) {
                    if (callback) callback(response);
                },
                error: function(xhr) {
                    if (errorCallback) {
                        errorCallback(xhr);
                    } else {
                        var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Error') : 'Request failed';
                        showAlert(msg, 'danger');
                    }
                }
            });
        }

        // Sidebar toggle
        (function() {
            var sidebar = document.getElementById('hctSidebar');
            var wrapper = document.getElementById('hctMainWrapper');
            var toggle = document.getElementById('sidebarToggle');
            var collapsed = localStorage.getItem('hct-sidebar-collapsed') === 'true';

            function applySidebarState() {
                if (collapsed) {
                    sidebar.classList.add('collapsed');
                    wrapper.classList.add('sidebar-collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                    wrapper.classList.remove('sidebar-collapsed');
                }
            }

            applySidebarState();

            toggle.addEventListener('click', function() {
                collapsed = !collapsed;
                localStorage.setItem('hct-sidebar-collapsed', collapsed);
                applySidebarState();
            });

            // Mobile overlay close
            sidebar.addEventListener('click', function(e) {
                if (window.innerWidth < 992 && e.target === sidebar) {
                    collapsed = true;
                    localStorage.setItem('hct-sidebar-collapsed', collapsed);
                    applySidebarState();
                }
            });
        })();

        // Global AJAX error handler
        jQuery(document).ajaxError(function(event, jqXHR) {
            if (jqXHR.status === 401) {
                window.location.href = '/login';
            } else if (jqXHR.status === 422) {
                var resp = jqXHR.responseJSON;
                if (resp && resp.error) {
                    showAlert(resp.error, 'danger');
                }
            } else if (jqXHR.status >= 500) {
                showAlert('Server error. Please try again.', 'danger');
            }
        });
    </script>
    @yield('js')
</body>
</html>
