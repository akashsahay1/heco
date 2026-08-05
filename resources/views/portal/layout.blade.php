<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HECO Portal - Regenerative Travel')</title>
    {{-- The wordmark is far too wide to read at 16px, so the tab shows the
         four-hue motif taken from it. --}}
    <link rel="icon" href="{{ url('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ url('images/logo/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/fontawesome.min.css') }}" rel="stylesheet">
    <link href="{{ url('style.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ url('css/portal.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ url('css/air-datepicker.min.css') }}" rel="stylesheet">
    @yield('css')
</head>
<body class="heco-portal">
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
                    <a href="/login" class="btn btn-outline-dark btn-sm header-auth-btn">
                        Login
                    </a>
                    <a href="/sign-up" class="btn btn-success btn-sm header-auth-btn">
                        Get Started
                    </a>
                @else
                    @if(auth()->user()->isServiceProvider())
                        <a href="/sp/dashboard" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-grid"></i> Dashboard
                        </a>
                    @endif
                    @php
                        $headerAvatar = auth()->user()->avatar ?: (auth()->user()->photo ? '/storage/' . auth()->user()->photo : null);
                    @endphp
                    <div class="user-dropdown">
                        <button type="button" class="user-dropdown-trigger" id="userDropdownTrigger">
                            <span class="user-avatar">
                                <i class="bi bi-person {{ $headerAvatar ? 'profile-avatar-hidden' : '' }}" id="headerUserAvatarIcon"></i>
                                <img src="{{ $headerAvatar ?: '' }}" alt="Avatar" class="user-avatar-img {{ $headerAvatar ? '' : 'profile-avatar-hidden' }}" id="headerUserAvatarImg">
                            </span>
                            <span class="user-name">Hi {{ \Illuminate\Support\Str::before(auth()->user()->full_name ?: auth()->user()->email, ' ') }}</span>
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
                <a href="/login" class="mobile-nav-link">Login</a>
                <a href="/sign-up" class="mobile-nav-link text-primary">Get Started</a>
            @endguest
        </nav>
    </header>


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
                        <input type="text" class="form-control" id="currencySearchInput" autocomplete="off">
                    </div>
                </div>
                <div class="modal-body px-4 pb-4 modal-scroll">
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
                        @php $socialLinks = [
                            'bi-facebook' => config('app.social_facebook'),
                            'bi-instagram' => config('app.social_instagram'),
                            'bi-twitter-x' => config('app.social_twitter'),
                            'bi-youtube' => config('app.social_youtube'),
                        ]; $socialLinks = array_filter($socialLinks); @endphp
                        @if(count($socialLinks))
                        <div class="footer-social">
                            @foreach($socialLinks as $icon => $url)
                                <a href="{{ $url }}" class="social-link" target="_blank" rel="noopener"><i class="bi {{ $icon }}"></i></a>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    <!-- Company Column -->
                    <div class="footer-links">
                        <h4 class="footer-heading">Company</h4>
                        <ul class="footer-nav">
                            <li><a href="/about">About Us</a></li>
                            <li><a href="/join">Partner With Us</a></li>
                            {{-- Next to "Partner With Us", not under Support:
                                 this one is for the collective, while the
                                 Travel Guidelines there are for travellers. --}}
                            <li><a href="/partner-guidelines">Partner Guidelines</a></li>
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
                        <a href="/privacy-policy">Privacy Policy</a>
                        <a href="/terms">Terms of Service</a>
                        <a href="/privacy-policy#cookies">Cookie Policy</a>
                    </nav>
                </div>
            </div>
        </div>
    </footer>

    <script src="{{ url('js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ url('js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ url('js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ url('js/air-datepicker.min.js') }}"></script>
    <script src="{{ url('js/air-datepicker-en.js') }}"></script>
    <script src="{{ url('js/custom-select.js') }}?v={{ time() }}"></script>
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
        // Alias — pages (e.g. sp/pricing) call showError(); route to the toast so
        // their error paths surface instead of throwing ReferenceError silently.
        window.showError = function(message) { window.showAlert(message, 'danger'); };

        // Confirm Action
        window.confirmAction = function(message, callback) {
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
        };

        // AJAX Post Helper
        window.ajaxPost = function(data, callback, errorCallback) {
            // A plain object is form-encoded as before. FormData carries files,
            // and jQuery must be told to leave it alone or the browser never
            // sets the multipart boundary.
            var isForm = (typeof FormData !== 'undefined') && (data instanceof FormData);
            jQuery.ajax({
                url: '/ajax',
                method: 'POST',
                data: data,
                processData: !isForm,
                contentType: isForm ? false : undefined,
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

            // The headline price for an experience card, with the unit it is
            // charged in. An experiential stay is sold by the room, so it is
            // quoted per night and has no per-person figure at all — cards that
            // read base_cost_per_person showed such a listing with no price.
            // The server puts the answer on every experience as price_from.
            window.expPriceFrom = function(exp) {
                if (!exp || !exp.price_from || !(exp.price_from.amount > 0)) return null;
                return {
                    text: window.fmtCurrency(exp.price_from.amount, exp.price_from.currency || 'INR'),
                    unit: exp.price_from.unit || 'per person'
                };
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
                var check = (code === currentCurrency) ? '<i class="bi bi-check-circle-fill text-success fs-14"></i>' : '';
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

        // Auth modal removed — /login and /sign-up are now dedicated pages.

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

        // Backwards-compat: legacy ?auth=login and ?auth=register URLs redirect
        // to the dedicated pages.
        var legacyAuth = new URLSearchParams(window.location.search).get('auth');
        if (legacyAuth === 'login') {
            window.location.replace('/login');
        } else if (legacyAuth === 'register') {
            window.location.replace('/sign-up');
        }
    });
    </script>

    {{-- First-login nationality prompt: social signups bypass the signup form
         (which requires nationality), so capture it once here. Shown to any
         traveller still missing a nationality; blocks until they pick one. --}}
    @auth
    @if(auth()->user()->isTraveller() && empty(auth()->user()->nationality))
    <div class="modal fade" id="nationalityPromptModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="nationalityPromptTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow-lg">
                <div class="modal-header border-bottom px-4 pt-4 pb-3">
                    <h5 class="modal-title fw-bold" id="nationalityPromptTitle">
                        <i class="bi bi-globe2 me-2"></i>One quick thing
                    </h5>
                </div>
                <div class="modal-body px-4 py-4">
                    <p class="text-muted mb-3">Please tell us your nationality so we can show the right pricing for your trips.</p>
                    <label class="form-label fw-semibold" for="nationalityPromptSelect">Nationality <span class="text-danger">*</span></label>
                    <select class="form-select" id="nationalityPromptSelect">
                        <option value="">Select nationality</option>
                        @foreach(config('countries.list') as $country)
                            <option value="{{ $country }}">{{ $country }}</option>
                        @endforeach
                    </select>
                    <div class="text-danger small mt-2 d-none" id="nationalityPromptError">Please select your nationality.</div>
                </div>
                <div class="modal-footer border-top px-4 pb-4 pt-3">
                    <button type="button" class="btn btn-success w-100" id="btnSaveNationalityPrompt">
                        <i class="bi bi-check-lg me-1"></i> Save &amp; Continue
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
    jQuery(function() {
        var npEl = document.getElementById('nationalityPromptModal');
        if (!npEl) return;
        var npModal = new bootstrap.Modal(npEl);
        npModal.show();

        jQuery('#nationalityPromptSelect').on('change', function() {
            if (jQuery(this).val()) jQuery('#nationalityPromptError').addClass('d-none');
        });

        jQuery('#btnSaveNationalityPrompt').on('click', function() {
            var val = jQuery('#nationalityPromptSelect').val();
            if (!val) {
                jQuery('#nationalityPromptError').text('Please select your nationality.').removeClass('d-none');
                return;
            }
            var btn = jQuery(this);
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
            ajaxPost({ save_nationality: 1, nationality: val }, function() {
                npModal.hide();
                showAlert('Thanks! Your nationality has been saved.', 'success');
            }, function(xhr) {
                btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Save & Continue');
                var msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Could not save. Please try again.';
                jQuery('#nationalityPromptError').text(msg).removeClass('d-none');
            });
        });
    });
    </script>
    @endif
    @endauth

    @yield('js')
</body>
</html>
