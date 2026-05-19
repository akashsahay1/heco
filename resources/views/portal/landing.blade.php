@extends('portal.layout')
@section('title', 'Welcome to HECO - Regenerative Travel')

@section('content')
<!-- Hero Section -->
<div class="landing-hero text-white d-flex align-items-center position-relative">
    <div class="container text-center position-relative landing-hero-stack">
        <p class="text-uppercase small fw-bold mb-3 landing-hero-eyebrow">HECO — Regenerative Travel Collective</p>
        <h1 class="display-3 fw-bold mb-3">Discover Regenerative<br>Travel Worldwide</h1>
        <p class="lead mb-4 mx-auto landing-hero-lead">
            Immerse yourself in authentic experiences that transform communities and restore landscapes.
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="/home" class="btn btn-light btn-lg px-5 fw-semibold sp-accent">
                <i class="bi bi-compass me-2"></i> Start Your Journey
            </a>
            <a href="/join" class="btn btn-outline-light btn-lg px-5 fw-semibold">
                <i class="bi bi-people me-2"></i> Become a Partner
            </a>
        </div>
    </div>
    <div class="scroll-indicator">
        <a href="#about" class="text-white text-decoration-none">
            <i class="bi bi-chevron-double-down fs-4"></i>
        </a>
    </div>
</div>

<!-- About HECO Section -->
<section class="landing-section" id="about">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <h2 class="landing-section-title">Travel That Gives Back</h2>
                <p class="text-muted mb-4">
                    HECO connects conscious travellers with authentic, community-driven
                    experiences across the world. Every journey contributes directly to local livelihoods,
                    conservation efforts, and cultural preservation.
                </p>
                <p class="text-muted">
                    Our network of local hosts, guides, and service providers ensures your travels create
                    meaningful impact while offering unforgettable adventures in one of the world's most
                    spectacular landscapes.
                </p>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-4">
                        <div class="landing-stat-card">
                            <div class="landing-stat-value">{{ $regionCount }}</div>
                            <div class="landing-stat-label">{{ \Str::plural('Region', $regionCount) }}</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="landing-stat-card">
                            <div class="landing-stat-value">{{ $experienceCount }}</div>
                            <div class="landing-stat-label">{{ \Str::plural('Experience', $experienceCount) }}</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="landing-stat-card">
                            <div class="landing-stat-value">{{ $communityCount }}</div>
                            <div class="landing-stat-label">{{ \Str::plural('Community', $communityCount) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="landing-section landing-bg-white">
    <div class="container text-center">
        <h2 class="landing-section-title">How It Works</h2>
        <p class="landing-section-subtitle">Plan your regenerative journey in three simple steps</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="landing-step-card">
                    <div class="landing-step-number">Step 1</div>
                    <div class="landing-step-icon"><i class="bi bi-search"></i></div>
                    <h5 class="fw-bold">Discover</h5>
                    <p class="text-muted small">Browse curated experiences across our regions. Filter by type, difficulty, and season to find your perfect adventure.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="landing-step-card">
                    <div class="landing-step-number">Step 2</div>
                    <div class="landing-step-icon"><i class="bi bi-calendar-check"></i></div>
                    <h5 class="fw-bold">Plan</h5>
                    <p class="text-muted small">Build your itinerary with our AI-powered planner. Combine experiences, set dates, and customize every detail of your trip.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="landing-step-card">
                    <div class="landing-step-number">Step 3</div>
                    <div class="landing-step-icon"><i class="bi bi-globe-americas"></i></div>
                    <h5 class="fw-bold">Travel</h5>
                    <p class="text-muted small">Experience authentic local culture with community hosts. Every trip directly supports communities and regenerative projects.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Regions -->
@if($regions->count())
<section class="landing-section" id="regions">
    <div class="container">
        <div class="landing-section-header">
            <h2 class="landing-section-title">Explore Our Regions</h2>
            <p class="landing-section-subtitle">Each region offers unique landscapes, cultures, and experiences</p>
        </div>
        <div class="row g-4">
            @php
                $regionImages = [
                    '/images/regions/region-1.jpg',
                    '/images/regions/region-2.jpg',
                    '/images/regions/region-3.jpg',
                    '/images/regions/region-4.jpg',
                    '/images/regions/region-5.jpg',
                    '/images/regions/region-6.jpg',
                ];
            @endphp
            @foreach($regions->take(6) as $idx => $region)
                <div class="col-md-4 col-6">
                    <a href="/home?region_id={{ $region->id }}" class="text-decoration-none">
                        <div class="landing-region-card">
                            <div class="landing-region-image" data-bg="{{ $regionImages[$idx % count($regionImages)] }}">
                                <div class="landing-region-overlay"></div>
                                <div class="landing-region-content">
                                    <div class="landing-region-name">{{ $region->name }}</div>
                                    <div class="landing-region-state">{{ $region->country ?? '' }}</div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Experience Categories -->
<section class="landing-section landing-bg-white">
    <div class="container text-center">
        <h2 class="landing-section-title">What Inspires You?</h2>
        <p class="landing-section-subtitle">Choose from diverse experience categories</p>
        <div class="row g-3">
            @php
                // Icons + blurbs keyed by the real experience-type strings stored in
                // the DB; any type without a mapping falls back to a generic icon.
                $catMeta = [
                    'Trek' => ['icon' => 'fa-solid fa-mountain', 'desc' => 'Alpine trails & mountain passes'],
                    'Cultural Immersion' => ['icon' => 'fa-solid fa-landmark', 'desc' => 'Traditions & heritage'],
                    'Nature' => ['icon' => 'fa-solid fa-leaf', 'desc' => 'Landscapes & ecology'],
                    'Wildlife' => ['icon' => 'fa-solid fa-paw', 'desc' => 'Birds & wildlife encounters'],
                    'Adventure' => ['icon' => 'fa-solid fa-person-hiking', 'desc' => 'Thrills & challenges'],
                    'Wellness' => ['icon' => 'fa-solid fa-spa', 'desc' => 'Healing & rejuvenation'],
                    'Culinary' => ['icon' => 'fa-solid fa-utensils', 'desc' => 'Local cuisine & cooking'],
                    'Volunteering' => ['icon' => 'fa-solid fa-hand-holding-heart', 'desc' => 'Give back to communities'],
                ];
            @endphp
            @foreach($experienceTypes as $type)
                @php $meta = $catMeta[$type] ?? ['icon' => 'fa-solid fa-compass', 'desc' => 'Explore experiences']; @endphp
                <div class="col-6 col-md-3">
                    <a href="/home?type={{ urlencode($type) }}" class="text-decoration-none">
                        <div class="landing-category-card">
                            <div class="landing-category-icon"><i class="{{ $meta['icon'] }}"></i></div>
                            <h6 class="fw-bold text-dark mb-1">{{ $type }}</h6>
                            <small class="text-muted">{{ $meta['desc'] }}</small>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Impact Section -->
<section class="landing-impact-section">
    <div class="container text-center">
        <h2 class="display-6 fw-bold mb-3">Travel That Gives Back</h2>
        <p class="mb-5 mx-auto landing-impact-lead">
            Every trip through HECO contributes to regenerative projects that restore ecosystems and empower communities.
        </p>
        <div class="row g-4 justify-content-center">
            <div class="col-md-3 col-6">
                <div class="fs-1 fw-bold">100%</div>
                <div class="small opacity-75">Community Direct</div>
            </div>
            <div class="col-md-3 col-6">
                <div class="fs-1 fw-bold">0</div>
                <div class="small opacity-75">Middlemen</div>
            </div>
            <div class="col-md-3 col-6">
                <div class="fs-1 fw-bold">Net+</div>
                <div class="small opacity-75">Environmental Impact</div>
            </div>
            <div class="col-md-3 col-6">
                <div class="fs-1 fw-bold">Local</div>
                <div class="small opacity-75">Hosts & Guides</div>
            </div>
        </div>
    </div>
</section>

<!-- CTA / Newsletter Section -->
<section class="landing-cta-section">
    <div class="container text-center">
        <h2 class="landing-section-title">Ready to Explore?</h2>
        <p class="text-muted mb-4">Start planning your regenerative journey today.</p>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="d-flex gap-3 justify-content-center flex-wrap mb-4">
                    <a href="/home" class="btn sp-btn-primary btn-lg px-4 fw-semibold">
                        <i class="bi bi-compass me-2"></i> Start Exploring
                    </a>
                    <a href="/join" class="btn btn-outline-secondary btn-lg px-4 fw-semibold">
                        <i class="bi bi-people me-2"></i> Partner With Us
                    </a>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <h6 class="fw-bold mb-2"><i class="bi bi-envelope me-1"></i> Stay in the loop</h6>
                <p class="text-muted small mb-3">Get occasional updates on new regions and experiences. No spam.</p>
                <form id="newsletterForm" class="newsletter-form d-flex gap-2 justify-content-center flex-wrap">
                    <input type="email" name="email" class="form-control newsletter-input" required>
                    <button type="submit" class="btn sp-btn-primary">Subscribe</button>
                </form>
                <div id="newsletterMsg" class="small mt-2"></div>
            </div>
        </div>
    </div>
</section>

@endsection

@section('js')
<script>
jQuery(function() {
    // (Background-image hydration for [data-bg] elements lives in app.js.)

    jQuery('#newsletterForm').on('submit', function(e) {
        e.preventDefault();
        var form = jQuery(this);
        var btn = form.find('button[type="submit"]');
        var email = form.find('input[name="email"]').val().trim();
        if (!email) return;
        btn.prop('disabled', true).text('Subscribing...');
        jQuery.ajax({
            url: '/ajax',
            method: 'POST',
            data: { subscribe_newsletter: 1, email: email },
            skipGlobalError: true,
            success: function(resp) {
                btn.prop('disabled', false).text('Subscribe');
                form[0].reset();
                jQuery('#newsletterMsg').removeClass('text-danger').addClass('text-success').text(resp.message || 'Thanks for subscribing!');
            },
            error: function(xhr) {
                btn.prop('disabled', false).text('Subscribe');
                var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Could not subscribe. Please try again.') : 'Could not subscribe. Please try again.';
                jQuery('#newsletterMsg').removeClass('text-success').addClass('text-danger').text(msg);
            }
        });
    });
});
</script>
@endsection
