<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HECO Admin')</title>
    <link rel="icon" href="{{ url('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ url('images/logo/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/fontawesome.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/air-datepicker.min.css') }}" rel="stylesheet">
    <link href="{{ url('style.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ url('css/admin.css') }}?v={{ time() }}" rel="stylesheet">
    <style>
        /* Pulses the sidebar count so a pending approval is noticed, without the
           harsh on/off flash of a true blink. Respects reduced-motion. */
        .hct-badge-blink { animation: hctBadgePulse 1.4s ease-in-out infinite; }
        @keyframes hctBadgePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: .45; transform: scale(1.12); }
        }
        @media (prefers-reduced-motion: reduce) {
            .hct-badge-blink { animation: none; }
        }
    </style>
    @yield('css')
</head>
<body class="hct-body heco-admin">

    <!-- Sidebar -->
    <aside class="hct-sidebar" id="hctSidebar">
        <div class="hct-sidebar-brand">
            {{-- The sidebar is dark, so it takes the white wordmark. --}}
            <img src="{{ url('images/logo/heco-logo-light.png') }}" alt="HECO" class="hct-brand-logo">
            <span>Admin</span>
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
            <a href="{{ url('/newsletter') }}" class="hct-nav-link {{ request()->routeIs('hct.newsletter') ? 'active' : '' }}">
                <i class="bi bi-envelope-heart"></i> Newsletter
            </a>
            @php $pendingApplicationCount = \App\Models\ServiceProvider::where('status', 'pending')->count(); @endphp
            <a href="{{ url('/provider-applications') }}" class="hct-nav-link {{ request()->routeIs('hct.provider-applications') ? 'active' : '' }}">
                <i class="bi bi-envelope-paper"></i> Applications
                @if($pendingApplicationCount > 0)
                    <span class="badge bg-danger ms-auto hct-badge-blink"
                          title="{{ $pendingApplicationCount }} application(s) awaiting approval">
                        {{ $pendingApplicationCount }}
                    </span>
                @endif
            </a>
            @php $pendingPricingCount = \App\Models\SpPricing::where('approval_status', 'pending')->count(); @endphp
            <a href="{{ url('/pending-pricing') }}" class="hct-nav-link {{ request()->routeIs('hct.pending-pricing') ? 'active' : '' }}">
                <i class="bi bi-cash-stack"></i> Pending Rates
                @if($pendingPricingCount > 0)
                    <span class="badge bg-danger ms-auto">{{ $pendingPricingCount }}</span>
                @endif
            </a>

            <div class="hct-nav-section">CONTENT</div>
            <a href="{{ url('/regions') }}" class="hct-nav-link {{ request()->routeIs('hct.regions*') ? 'active' : '' }}">
                <i class="bi bi-globe-americas"></i> Regions
            </a>
            @php $pendingExperienceCount = \App\Models\Experience::pending()->count(); @endphp
            <a href="{{ url('/experiences') }}" class="hct-nav-link {{ request()->routeIs('hct.experiences*') ? 'active' : '' }}">
                <i class="bi bi-compass"></i> Experiences
                @if($pendingExperienceCount > 0)
                    <span class="badge bg-danger ms-auto">{{ $pendingExperienceCount }}</span>
                @endif
            </a>
            <a href="{{ url('/regenerative-projects') }}" class="hct-nav-link {{ request()->routeIs('hct.rp*') ? 'active' : '' }}">
                <i class="bi bi-tree"></i> Regenerative Projects
            </a>

            @if(auth()->user()->isHctAdmin())
            <div class="hct-nav-section">SETTINGS</div>
            <a href="{{ url('/travel-preferences') }}" class="hct-nav-link {{ request()->routeIs('hct.travel-preferences') ? 'active' : '' }}">
                <i class="bi bi-sliders2"></i> Travel Preferences
            </a>
            @endif
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
    <script src="{{ url('js/air-datepicker.min.js') }}"></script>
    <script src="{{ url('js/air-datepicker-en.js') }}"></script>
    <script src="{{ url('js/custom-select.js') }}?v={{ time() }}"></script>
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
        // Alias — several pages call showError(); route it to the toast so their
        // error paths surface instead of throwing ReferenceError and going silent.
        window.showError = function (message) { showAlert(message, 'danger'); };
        window.showAlert = showAlert;

        // Show/hide a password. Delegated from the document so it also works on
        // fields that live inside a modal, which is not in the DOM at load.
        // Keyboard-reachable too, since the control is a div rather than a button.
        jQuery(document).on('click keydown', '.password_toggle', function(e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            var field = jQuery(jQuery(this).data('target'));
            var shown = field.attr('type') === 'text';
            field.attr('type', shown ? 'password' : 'text');
            jQuery(this).attr('aria-label', shown ? 'Show password' : 'Hide password')
                .find('i').toggleClass('bi-eye', shown).toggleClass('bi-eye-slash', !shown);
        });

        function confirmAction(message, callback) {
            Swal.fire({
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#79a09f',
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

        // Shared numbered pagination for AJAX-driven lists.
        // `resp` may be a Laravel paginator JSON (current_page/last_page/total at
        // top level) or an object with a `.pagination` sub-object — both work.
        // `onPage(pageNumber)` is invoked when the user picks a page.
        window.renderPagination = function(containerSelector, resp, onPage) {
            var $c = jQuery(containerSelector);
            if (!$c.length) return;
            var p = (resp && resp.pagination) ? resp.pagination : (resp || {});
            var current = parseInt(p.current_page, 10) || 1;
            var last = parseInt(p.last_page, 10) || 1;
            var total = parseInt(p.total, 10) || 0;

            if (last <= 1) { $c.empty(); return; }

            var html = '<nav class="d-flex justify-content-between align-items-center flex-wrap gap-2">';
            html += '<small class="text-muted">' + total + ' total</small>';
            html += '<ul class="pagination pagination-sm mb-0 flex-wrap">';
            html += '<li class="page-item' + (current <= 1 ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (current - 1) + '">&laquo;</a></li>';

            var start = Math.max(1, current - 2);
            var end = Math.min(last, current + 2);
            if (start > 1) {
                html += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
                if (start > 2) html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
            for (var i = start; i <= end; i++) {
                html += '<li class="page-item' + (i === current ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
            }
            if (end < last) {
                if (end < last - 1) html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                html += '<li class="page-item"><a class="page-link" href="#" data-page="' + last + '">' + last + '</a></li>';
            }
            html += '<li class="page-item' + (current >= last ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (current + 1) + '">&raquo;</a></li>';
            html += '</ul></nav>';
            $c.html(html);

            // Scoped, idempotent click binding (off before on guards re-renders).
            $c.off('click.pg').on('click.pg', 'a.page-link[data-page]', function(e) {
                e.preventDefault();
                var pg = parseInt(jQuery(this).data('page'), 10);
                if (pg >= 1 && pg <= last && pg !== current && typeof onPage === 'function') {
                    onPage(pg);
                }
            });
        };

        // Sidebar toggle
        (function() {
            var $sidebar = jQuery('#hctSidebar');
            var $wrapper = jQuery('#hctMainWrapper');
            var $toggle = jQuery('#sidebarToggle');
            var collapsed = localStorage.getItem('hct-sidebar-collapsed') === 'true';

            function applySidebarState() {
                $sidebar.toggleClass('collapsed', collapsed);
                $wrapper.toggleClass('sidebar-collapsed', collapsed);
            }

            applySidebarState();

            $toggle.on('click', function() {
                collapsed = !collapsed;
                localStorage.setItem('hct-sidebar-collapsed', collapsed);
                applySidebarState();
            });

            // Mobile overlay close: tapping the dimmed sidebar area itself closes it.
            $sidebar.on('click', function(e) {
                if (window.innerWidth < 992 && e.target === this) {
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
