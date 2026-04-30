<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HECO Portal - Regenerative Travel')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/fontawesome.min.css') }}" rel="stylesheet">
    <link href="{{ url('style.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ url('css/portal.css') }}?v={{ time() }}" rel="stylesheet">
    @yield('css')
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <div class="header-container">
            <!-- Logo -->
            <a href="/" class="header-logo">
                <img src="/images/logo/heco-logo-dark.png" alt="HECO" class="logo-img">
            </a>

            <!-- Desktop Nav -->
            <nav class="header-nav">
                <a href="/home" class="nav-link {{ request()->is('home') ? 'active' : '' }}">Explore</a>
                <a href="/home#experiences" class="nav-link">Experiences</a>
                <a href="/home#regions" class="nav-link">Regions</a>
                <a href="/join" class="nav-link {{ request()->is('join') ? 'active' : '' }}">Become a Partner</a>
            </nav>

            <!-- Header Actions -->
            <div class="header-actions">
                <button type="button" class="header-currency-btn" id="btnCurrencySelector" title="Change currency">
                    <img src="/images/flags/in.png" alt="" id="currentCurrencyFlag" class="header-currency-flag">
                    <span id="currentCurrencyLabel">INR</span>
                    <i class="bi bi-chevron-down header-currency-caret" id="currencyCaret"></i>
                </button>
                @guest
                    <button type="button" class="btn btn-outline-dark btn-sm header-auth-btn" id="btnOpenAuth">
                        Login
                    </button>
                    <button type="button" class="btn btn-success btn-sm header-auth-btn" id="btnOpenRegister">
                        Get Started
                    </button>
                @else
                    @if(auth()->user()->isServiceProvider())
                        <a href="/sp/dashboard" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-grid"></i> Dashboard
                        </a>
                    @endif
                    @if(auth()->user()->isTraveller())
                        <a href="/my-itineraries" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-journal-bookmark"></i> My Trips
                        </a>
                        <a href="/wishlist" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-heart"></i> Wishlist
                        </a>
                    @endif
                    <div class="user-dropdown">
                        <button type="button" class="user-dropdown-trigger" id="userDropdownTrigger">
                            <span class="user-avatar">
                                <i class="bi bi-person"></i>
                            </span>
                            <span class="user-name">{{ auth()->user()->full_name ?? auth()->user()->email }}</span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="user-dropdown-menu" id="userDropdownMenu">
                            <a href="/profile" class="dropdown-item">
                                <i class="bi bi-person-circle"></i> My Profile
                            </a>
                            <a href="/my-itineraries" class="dropdown-item">
                                <i class="bi bi-journal-bookmark"></i> My Itineraries
                            </a>
                            <a href="/wishlist" class="dropdown-item">
                                <i class="bi bi-heart"></i> My Wishlist
                            </a>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="/logout" id="logoutForm">
                                @csrf
                                <button type="submit" class="dropdown-item dropdown-item-danger">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                @endguest

                <!-- Mobile Menu Toggle -->
                <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Nav -->
        <nav class="mobile-nav" id="mobileNav">
            <a href="/home" class="mobile-nav-link">Explore</a>
            <a href="/home#experiences" class="mobile-nav-link">Experiences</a>
            <a href="/home#regions" class="mobile-nav-link">Regions</a>
            <a href="/join" class="mobile-nav-link">Become a Partner</a>
            @auth
                <div class="mobile-nav-divider"></div>
                <a href="/my-itineraries" class="mobile-nav-link">My Trips</a>
                <a href="/wishlist" class="mobile-nav-link"><i class="bi bi-heart"></i> My Wishlist</a>
            @endauth
            @guest
                <div class="mobile-nav-divider"></div>
                <button type="button" class="mobile-nav-link" data-open-auth="login">Login</button>
                <button type="button" class="mobile-nav-link text-primary" data-open-auth="register">Get Started</button>
            @endguest
        </nav>
    </header>

    @guest
    <!-- Auth Modal (Bootstrap 5) -->
    <div class="modal fade auth-modal" id="authModal" tabindex="-1" aria-labelledby="authModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content auth-modal-content">
                <button type="button" class="btn-close auth-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>

                <div class="auth-modal-hero">
                    <h4 class="auth-modal-title" id="authModalTitle">Welcome Back</h4>
                    <p class="auth-modal-subtitle">Plan regenerative journeys with HECO</p>
                </div>

                <div class="auth-modal-body">
                    <!-- Tabs -->
                    <ul class="nav nav-pills nav-fill auth-tabs">
                        <li class="nav-item">
                            <button class="nav-link active" data-auth-tab="login" type="button">Login</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-auth-tab="register" type="button">Sign Up</button>
                        </li>
                    </ul>

                    <!-- Login Form -->
                    <div class="auth-panel" id="loginPanel">
                        <form id="loginForm" class="auth-form">
                            <div class="auth-field">
                                <label class="auth-label">Email Address</label>
                                <div class="input-group auth-input-group">
                                    <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
                                </div>
                            </div>
                            <div class="auth-field">
                                <div class="auth-label-row">
                                    <label class="auth-label mb-0">Password</label>
                                    <a href="/forgot-password" class="auth-link-muted">Forgot?</a>
                                </div>
                                <div class="input-group auth-input-group">
                                    <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
                                    <button type="button" class="btn btn-outline-secondary password-toggle" tabindex="-1">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success w-100 auth-submit-btn" id="btnLogin">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </button>
                        </form>

                        <div class="auth-divider">
                            <span>or continue with</span>
                        </div>

                        <div class="auth-social-row">
                            <a href="/auth/google/redirect" class="social-icon-btn" title="Continue with Google" aria-label="Continue with Google">
                                <svg viewBox="0 0 24 24" width="22" height="22"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                            </a>
                            <a href="/auth/facebook/redirect" class="social-icon-btn" title="Continue with Facebook" aria-label="Continue with Facebook">
                                <svg viewBox="0 0 24 24" width="22" height="22"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>
                        </div>
                    </div>

                    <!-- Register Form -->
                    <div class="auth-panel" id="registerPanel" style="display: none;">
                        <form id="registerForm" class="auth-form">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="auth-field">
                                        <label class="auth-label">First Name</label>
                                        <div class="input-group auth-input-group">
                                            <input type="text" class="form-control" name="first_name" placeholder="Jane" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="auth-field">
                                        <label class="auth-label">Last Name</label>
                                        <div class="input-group auth-input-group">
                                            <input type="text" class="form-control" name="last_name" placeholder="Doe" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label">Email Address</label>
                                <div class="input-group auth-input-group">
                                    <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
                                </div>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label">Phone Number</label>
                                <div class="input-group auth-input-group">
                                    <input type="tel" class="form-control" name="phone" placeholder="+91 98765 43210">
                                </div>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label">Password</label>
                                <div class="input-group auth-input-group">
                                    <input type="password" class="form-control" name="password" placeholder="Min. 8 characters" required minlength="8">
                                    <button type="button" class="btn btn-outline-secondary password-toggle" tabindex="-1">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label">Confirm Password</label>
                                <div class="input-group auth-input-group">
                                    <input type="password" class="form-control" name="password_confirmation" placeholder="Repeat password" required>
                                    <button type="button" class="btn btn-outline-secondary password-toggle" tabindex="-1">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-check auth-terms">
                                <input type="checkbox" class="form-check-input" id="termsCheck" required>
                                <label class="form-check-label" for="termsCheck">
                                    I agree to the <a href="/terms" class="text-success text-decoration-none">Terms</a> and <a href="/privacy" class="text-success text-decoration-none">Privacy Policy</a>
                                </label>
                            </div>
                            <button type="submit" class="btn btn-success w-100 auth-submit-btn" id="btnRegister">
                                <i class="bi bi-person-plus me-2"></i>Create Account
                            </button>
                        </form>

                        <div class="auth-divider">
                            <span>or sign up with</span>
                        </div>

                        <div class="auth-social-row">
                            <a href="/auth/google/redirect" class="social-icon-btn" title="Sign up with Google" aria-label="Sign up with Google">
                                <svg viewBox="0 0 24 24" width="22" height="22"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                            </a>
                            <a href="/auth/facebook/redirect" class="social-icon-btn" title="Sign up with Facebook" aria-label="Sign up with Facebook">
                                <svg viewBox="0 0 24 24" width="22" height="22"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endguest

    <!-- Currency Selector Modal -->
    <div class="modal fade" id="currencyModal" tabindex="-1" aria-labelledby="currencyModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content rounded-4 shadow-lg">
                <div class="modal-header border-bottom px-4 pt-4 pb-3">
                    <h5 class="modal-title fw-bold" id="currencyModalTitle">
                        <i class="bi bi-currency-exchange me-2"></i>Select your currency
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="px-4 py-3 border-bottom bg-light">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="currencySearchInput" placeholder="Search by currency name or code..." autocomplete="off">
                    </div>
                </div>
                <div class="modal-body px-4 pb-4" style="max-height: 500px; overflow-y: auto;">
                    <p class="text-muted small fw-bold mb-2 text-uppercase" id="suggestedHeader">Suggested for you</p>
                    <div class="row g-2 mb-4" id="suggestedCurrencies"></div>
                    <p class="text-muted small fw-bold mb-2 text-uppercase">All currencies</p>
                    <div class="row g-2" id="allCurrenciesList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
        <script>jQuery(function() { showAlert('{{ session('success') }}', 'success'); });</script>
    @endif
    @if(session('error'))
        <script>jQuery(function() { showAlert('{{ session('error') }}', 'danger'); });</script>
    @endif

    <main class="main-content">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-main">
            <div class="container">
                <div class="footer-grid">
                    <!-- Brand Column -->
                    <div class="footer-brand">
                        <a href="/" class="footer-logo">
                            <img src="/images/logo/heco-logo-light.png" alt="HECO" class="logo-img">
                        </a>
                        <p class="footer-tagline">
                            HECO — Regenerative travel experiences that connect you with nature, culture, and local communities across the world.
                        </p>
                        <div class="footer-social">
                            <a href="#" class="social-link" title="Facebook"><i class="bi bi-facebook"></i></a>
                            <a href="#" class="social-link" title="Instagram"><i class="bi bi-instagram"></i></a>
                            <a href="#" class="social-link" title="Twitter"><i class="bi bi-twitter-x"></i></a>
                            <a href="#" class="social-link" title="YouTube"><i class="bi bi-youtube"></i></a>
                        </div>
                    </div>

                    <!-- Company Column -->
                    <div class="footer-links">
                        <h4 class="footer-heading">Company</h4>
                        <ul class="footer-nav">
                            <li><a href="/about">About Us</a></li>
                            <li><a href="/join">Partner With Us</a></li>
                            <li><a href="/about#team">Our Team</a></li>
                            <li><a href="/careers">Careers</a></li>
                        </ul>
                    </div>

                    <!-- Support Column -->
                    <div class="footer-links">
                        <h4 class="footer-heading">Support</h4>
                        <ul class="footer-nav">
                            <li><a href="/help">Help Center</a></li>
                            <li><a href="/contact">Contact Us</a></li>
                            <li><a href="/help#booking">FAQs</a></li>
                            <li><a href="/guidelines">Travel Guidelines</a></li>
                        </ul>
                    </div>

                    <!-- Contact Column -->
                    <div class="footer-contact">
                        <h4 class="footer-heading">Get in Touch</h4>
                        <ul class="contact-list">
                            <li>
                                <i class="bi bi-envelope"></i>
                                <a href="mailto:info@heco.eco">info@heco.eco</a>
                            </li>
                            <li>
                                <i class="bi bi-telephone"></i>
                                <a href="tel:+911234567890">+91 123 456 7890</a>
                            </li>
                            <li>
                                <i class="bi bi-geo-alt"></i>
                                <span>Himachal Pradesh, India</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="container">
                <div class="footer-bottom-content">
                    <p class="copyright">&copy; {{ date('Y') }} HECO. All rights reserved.</p>
                    <nav class="footer-legal">
                        <a href="/privacy">Privacy Policy</a>
                        <a href="/terms">Terms of Service</a>
                        <a href="/privacy#cookies">Cookie Policy</a>
                    </nav>
                </div>
            </div>
        </div>
    </footer>

    <script src="{{ url('js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ url('js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ url('js/sweetalert2.all.min.js') }}"></script>
    <script>
    jQuery(function() {
        // CSRF Setup
        jQuery.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content') }
        });

        // Auto-retry on 419 (CSRF token expired) — fetch fresh token and replay
        jQuery.ajaxPrefilter(function(options, originalOptions, jqXHR) {
            if (options._csrfRetry) return; // already a retry
            var originalError = options.error;
            options.error = function(xhr) {
                if (xhr.status === 419 && !options._csrfRetry) {
                    // Fetch a fresh CSRF token from the server
                    jQuery.get('/csrf-token').done(function(data) {
                        var newToken = data.token || data;
                        jQuery('meta[name="csrf-token"]').attr('content', newToken);
                        jQuery.ajaxSetup({ headers: { 'X-CSRF-TOKEN': newToken } });
                        // Retry original request
                        var retryOpts = jQuery.extend({}, originalOptions, { _csrfRetry: true });
                        jQuery.ajax(retryOpts);
                    }).fail(function() {
                        // Fresh token fetch failed — reload the page
                        window.location.reload();
                    });
                } else if (originalError) {
                    originalError.apply(this, arguments);
                }
            };
        });

        // Global AJAX error handler
        jQuery(document).ajaxError(function(event, jqXHR, settings) {
            // Skip global error handling for requests that handle errors themselves
            if (settings.skipGlobalError || settings._csrfRetry) return;
            if (jqXHR.status === 419) {
                // Handled by ajaxPrefilter retry above
                return;
            } else if (jqXHR.status === 401) {
                if (window.openAuthModal) { window.openAuthModal('login'); } else { window.location.href = '/home?auth=login'; }
            } else if (jqXHR.status === 422) {
                var resp = jqXHR.responseJSON;
                if (resp && resp.error) {
                    showAlert(resp.error, 'danger');
                }
            } else if (jqXHR.status >= 500) {
                showAlert('Server error. Please try again.', 'danger');
            }
        });

        // Show Alert (SweetAlert2 toast)
        window.showAlert = function(message, type) {
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
        };

        // Confirm Action
        window.confirmAction = function(message, callback) {
            Swal.fire({
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#15803d',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, proceed'
            }).then(function(result) {
                if (result.isConfirmed && callback) callback();
            });
        };

        // AJAX Post Helper
        window.ajaxPost = function(data, callback, errorCallback) {
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
        };

        // Mobile Navigation
        jQuery('#mobileMenuToggle').on('click', function() {
            var nav = jQuery('#mobileNav');
            nav.toggleClass('show');
            var icon = jQuery(this).find('i');
            if (nav.hasClass('show')) {
                icon.removeClass('bi-list').addClass('bi-x');
            } else {
                icon.removeClass('bi-x').addClass('bi-list');
            }
        });

        // User Dropdown
        jQuery('#userDropdownTrigger').on('click', function(e) {
            e.stopPropagation();
            jQuery('#userDropdownMenu').toggleClass('show');
        });

        jQuery(document).on('click', function(e) {
            if (!jQuery(e.target).closest('#userDropdownMenu, #userDropdownTrigger').length) {
                jQuery('#userDropdownMenu').removeClass('show');
            }
        });

        // ===== CURRENCY SYSTEM =====
        @php
            $currencyData = \App\Models\Currency::where('is_active', true)->orderBy('sort_order')->get()->keyBy('code')->map(fn($c) => [
                'name' => $c->name, 'symbol' => $c->symbol, 'locale' => $c->locale, 'flag' => $c->flag, 'rate' => (float)$c->rate_to_usd,
            ]);
        @endphp
        (function() {
            var currencies = @json($currencyData);

            var localeMap = {
                'en-IN': 'INR', 'hi': 'INR', 'hi-IN': 'INR',
                'en-US': 'USD', 'en': 'USD',
                'en-GB': 'GBP', 'en-AU': 'AUD', 'en-CA': 'CAD', 'en-SG': 'SGD',
                'de': 'EUR', 'de-DE': 'EUR', 'fr': 'EUR', 'fr-FR': 'EUR',
                'es': 'EUR', 'es-ES': 'EUR', 'it': 'EUR', 'nl': 'EUR',
                'es-PE': 'PEN', 'pt-BR': 'BRL',
                'ja': 'JPY', 'ja-JP': 'JPY',
                'zh': 'CNY', 'zh-CN': 'CNY',
                'ko': 'KRW', 'ko-KR': 'KRW',
                'th': 'THB', 'th-TH': 'THB',
                'ne': 'NPR', 'ne-NP': 'NPR',
                'de-CH': 'CHF', 'fr-CH': 'CHF'
            };

            var currentCurrency = 'INR';

            function detectCurrency() {
                var stored = localStorage.getItem('heco_currency');
                if (stored && currencies[stored]) return stored;

                var langs = navigator.languages ? navigator.languages.slice() : [];
                if (navigator.language && langs.indexOf(navigator.language) === -1) {
                    langs.unshift(navigator.language);
                }
                for (var i = 0; i < langs.length; i++) {
                    if (localeMap[langs[i]]) return localeMap[langs[i]];
                    var base = langs[i].split('-')[0];
                    if (localeMap[base]) return localeMap[base];
                }
                return 'USD';
            }

            currentCurrency = detectCurrency();
            localStorage.setItem('heco_currency', currentCurrency);
            jQuery('#currentCurrencyLabel').text(currentCurrency);
            if (currencies[currentCurrency] && currencies[currentCurrency].flag) {
                jQuery('#currentCurrencyFlag').attr('src', '/images/flags/' + currencies[currentCurrency].flag + '.png');
            }

            window.fmtCurrency = function(num, sourceCurrency) {
                if (num === null || num === undefined || num === '--' || num === '' || isNaN(num)) return '--';
                num = Number(num);
                if (num === 0) return '--';
                sourceCurrency = sourceCurrency || 'INR';
                var src = currencies[sourceCurrency] || { rate: 83 };
                var dst = currencies[currentCurrency] || { rate: 83 };
                var converted = (num / src.rate) * dst.rate;
                try {
                    return converted.toLocaleString(dst.locale || 'en-US', {
                        style: 'currency',
                        currency: currentCurrency,
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    });
                } catch (e) {
                    return (dst.symbol || '') + ' ' + Math.round(converted).toLocaleString();
                }
            };

            window.fmt = function(num) {
                if (num === null || num === undefined || num === '--') return '--';
                return Number(num).toLocaleString();
            };

            window.getCurrentCurrency = function() { return currentCurrency; };

            window.setCurrency = function(code) {
                if (!currencies[code]) return;
                currentCurrency = code;
                localStorage.setItem('heco_currency', code);
                jQuery('#currentCurrencyLabel').text(code);
                var flag = currencies[code].flag;
                if (flag) {
                    jQuery('#currentCurrencyFlag').attr('src', '/images/flags/' + flag + '.png');
                }
                jQuery(document).trigger('currencyChanged', [code]);
            };

            // Build suggested currencies
            var suggested = [currentCurrency];
            var defaults = ['USD', 'EUR', 'GBP', 'INR'];
            for (var i = 0; i < defaults.length; i++) {
                if (suggested.indexOf(defaults[i]) === -1 && currencies[defaults[i]]) {
                    suggested.push(defaults[i]);
                }
                if (suggested.length >= 4) break;
            }

            function renderCurrencyItem(code) {
                var c = currencies[code];
                if (!c) return '';
                var isActive = (code === currentCurrency) ? ' currency-pick-active' : '';
                var check = (code === currentCurrency) ? '<i class="bi bi-check-circle-fill text-success" style="font-size: 14px;"></i>' : '';
                var flagImg = c.flag ? '<img src="/images/flags/' + c.flag + '.png" alt="" class="currency-flag">' : '';
                return '<div class="col-6 col-md-3">'
                    + '<button class="currency-pick-item' + isActive + '" data-currency="' + code + '">'
                    + '<div class="d-flex align-items-center gap-2">'
                    + flagImg
                    + '<div class="flex-grow-1 text-start">'
                    + '<div class="currency-pick-name">' + c.name + '</div>'
                    + '<div class="currency-pick-code">' + c.symbol + ' ' + code + '</div>'
                    + '</div>'
                    + check
                    + '</div>'
                    + '</button>'
                    + '</div>';
            }

            function renderCurrencyModal(filter) {
                filter = (filter || '').toLowerCase();
                var sugHtml = '';
                for (var i = 0; i < suggested.length; i++) {
                    var c = currencies[suggested[i]];
                    if (filter && c && c.name.toLowerCase().indexOf(filter) === -1 && suggested[i].toLowerCase().indexOf(filter) === -1) continue;
                    sugHtml += renderCurrencyItem(suggested[i]);
                }
                jQuery('#suggestedCurrencies').html(sugHtml || '<div class="col-12"><p class="text-muted small">No matches</p></div>');
                jQuery('#suggestedHeader').toggle(!filter);

                var allHtml = '';
                var codes = Object.keys(currencies).sort(function(a, b) {
                    if (a === 'INR') return -1;
                    if (b === 'INR') return 1;
                    return currencies[a].name.localeCompare(currencies[b].name);
                });
                for (var j = 0; j < codes.length; j++) {
                    if (suggested.indexOf(codes[j]) !== -1 && !filter) continue;
                    var ci = currencies[codes[j]];
                    if (filter && ci.name.toLowerCase().indexOf(filter) === -1 && codes[j].toLowerCase().indexOf(filter) === -1) continue;
                    allHtml += renderCurrencyItem(codes[j]);
                }
                jQuery('#allCurrenciesList').html(allHtml || '<div class="col-12"><p class="text-muted small">No matches</p></div>');
            }

            var currencyModalEl = jQuery('#currencyModal');
            var bsCurrencyModal = currencyModalEl.length ? new bootstrap.Modal(currencyModalEl[0]) : null;

            jQuery('#btnCurrencySelector').on('click', function() {
                if (!bsCurrencyModal) return;
                jQuery('#currencySearchInput').val('');
                renderCurrencyModal('');
                bsCurrencyModal.show();
            });

            // Flip currency caret when modal opens/closes
            currencyModalEl.on('show.bs.modal', function() {
                jQuery('#currencyCaret').removeClass('bi-chevron-down').addClass('bi-chevron-up');
            }).on('hide.bs.modal', function() {
                jQuery('#currencyCaret').removeClass('bi-chevron-up').addClass('bi-chevron-down');
            });

            jQuery('#currencySearchInput').on('input', function() {
                renderCurrencyModal(jQuery(this).val());
            });

            jQuery(document).on('click', '.currency-pick-item', function() {
                var code = jQuery(this).data('currency');
                setCurrency(code);
                renderCurrencyModal(jQuery('#currencySearchInput').val());
                if (bsCurrencyModal) bsCurrencyModal.hide();
            });
        })();

        // Auth Modal (Bootstrap 5)
        var authModalEl = jQuery('#authModal');
        var bsAuthModal = authModalEl.length ? new bootstrap.Modal(authModalEl[0]) : null;

        window.openAuthModal = function(tab) {
            if (!bsAuthModal) return;
            switchAuthTab(tab || 'login');
            bsAuthModal.show();
        };

        function switchAuthTab(tab) {
            jQuery('[data-auth-tab]').each(function() {
                jQuery(this).toggleClass('active', jQuery(this).data('auth-tab') === tab);
            });

            if (tab === 'login') {
                jQuery('#loginPanel').show();
                jQuery('#registerPanel').hide();
                jQuery('#authModalTitle').text('Welcome Back');
            } else {
                jQuery('#loginPanel').hide();
                jQuery('#registerPanel').show();
                jQuery('#authModalTitle').text('Create Account');
            }
        }

        // Open Modal Buttons
        jQuery('#btnOpenAuth').on('click', function() {
            openAuthModal('login');
        });

        jQuery('#btnOpenRegister').on('click', function() {
            openAuthModal('register');
        });

        // Mobile auth buttons
        jQuery(document).on('click', '[data-open-auth]', function() {
            jQuery('#mobileNav').removeClass('show');
            openAuthModal(jQuery(this).data('open-auth'));
        });

        // Tab switching
        jQuery(document).on('click', '[data-auth-tab]', function() {
            switchAuthTab(jQuery(this).data('auth-tab'));
        });

        // Login Form
        jQuery('#loginForm').on('submit', function(e) {
            e.preventDefault();
            var btn = jQuery('#btnLogin');
            var originalText = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Logging in...');

            jQuery.ajax({
                url: '/ajax',
                method: 'POST',
                data: jQuery(this).serialize() + '&login=1',
                success: function(resp) {
                    if (resp.success) {
                        showAlert('Login successful!', 'success');
                        if (bsAuthModal) bsAuthModal.hide();
                        setTimeout(function() {
                            window.location.href = resp.redirect || '/home';
                        }, 500);
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html(originalText);
                    var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Login failed') : 'Request failed';
                    showAlert(msg, 'danger');
                }
            });
        });

        // Register Form
        jQuery('#registerForm').on('submit', function(e) {
            e.preventDefault();
            var btn = jQuery('#btnRegister');
            var originalText = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Creating account...');

            jQuery.ajax({
                url: '/ajax',
                method: 'POST',
                data: jQuery(this).serialize() + '&register=1',
                success: function(resp) {
                    if (resp.success) {
                        showAlert('Account created successfully!', 'success');
                        if (bsAuthModal) bsAuthModal.hide();
                        setTimeout(function() {
                            window.location.href = resp.redirect || '/home';
                        }, 500);
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html(originalText);
                    var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Registration failed') : 'Request failed';
                    showAlert(msg, 'danger');
                }
            });
        });

        // Smooth Scroll for anchor links
        jQuery(document).on('click', 'a[href^="#"]', function(e) {
            var target = jQuery(jQuery(this).attr('href'));
            if (target.length) {
                e.preventDefault();
                jQuery('html, body').animate({ scrollTop: target.offset().top }, 500);
            }
        });

        // Header scroll effect
        jQuery(window).on('scroll', function() {
            if (jQuery(window).scrollTop() > 50) {
                jQuery('.site-header').addClass('scrolled');
            } else {
                jQuery('.site-header').removeClass('scrolled');
            }
        });

        // Password Toggle
        jQuery(document).on('click', '.password-toggle', function() {
            var input = jQuery(this).closest('.input-group').find('input');
            var icon = jQuery(this).find('i');

            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('bi-eye').addClass('bi-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('bi-eye-slash').addClass('bi-eye');
            }
        });

        // Sync guest session trip to logged-in user after login/register, then redirect
        window.syncGuestJourneyAndRedirect = function(redirectUrl) {
            ajaxPost({ sync_guest_journey: 1 }, function(resp) {
                var url = redirectUrl || '/home';
                if (resp.trip_id) {
                    url = '/home?trip_id=' + resp.trip_id;
                }
                window.location.href = url;
            }, function() {
                window.location.href = redirectUrl || '/home';
            });
        };

        // Auto-open auth modal from URL param (?auth=login or ?auth=register)
        var urlParams = new URLSearchParams(window.location.search);
        var authParam = urlParams.get('auth');
        if (authParam && (authParam === 'login' || authParam === 'register')) {
            openAuthModal(authParam);
            // Clean URL without reload
            if (window.history.replaceState) {
                urlParams.delete('auth');
                var cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, '', cleanUrl);
            }
        }
    });
    </script>
    @yield('js')
</body>
</html>
