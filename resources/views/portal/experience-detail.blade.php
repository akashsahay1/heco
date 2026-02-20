@extends('portal.layout')
@section('title', $experience->name . ' - HECO Portal')

@section('css')
<style>
    .hero-section {
        position: relative;
        min-height: 350px;
        background-size: cover;
        background-position: center;
        display: flex;
        align-items: flex-end;
    }
    .hero-section::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, rgba(0,0,0,0.1) 60%, transparent 100%);
    }
    .hero-content { position: relative; z-index: 1; width: 100%; }
    .hero-no-image {
        background: linear-gradient(135deg, var(--heco-green-dark), var(--heco-green));
    }

    .info-stat-card { border: none; text-align: center; }
    .info-stat-card .stat-icon { font-size: 1.8rem; color: var(--heco-green); }
    .info-stat-card .stat-value { font-size: 1.1rem; font-weight: 700; }
    .info-stat-card .stat-label { font-size: 0.75rem; color: #6c757d; }

    .detail-section { margin-bottom: 2rem; }
    .detail-section h5 { color: var(--heco-green-dark); border-bottom: 2px solid var(--heco-green-light); padding-bottom: 8px; margin-bottom: 16px; }

    .inclusion-item { display: flex; align-items: center; padding: 6px 0; }
    .inclusion-item .inclusion-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 10px; font-size: 0.9rem; }
    .inclusion-item .inclusion-icon.included { background: rgba(45,106,79,0.1); color: var(--heco-green); }
    .inclusion-item .inclusion-icon.not-included { background: rgba(220,53,69,0.1); color: #dc3545; }

    .season-badge { font-size: 0.8rem; padding: 4px 10px; }
    .month-badge { font-size: 0.75rem; margin: 2px; }

    .gallery-thumb { width: 100%; height: 160px; object-fit: cover; border-radius: 8px; cursor: pointer; transition: transform 0.2s; }
    .gallery-thumb:hover { transform: scale(1.03); }

    .difficulty-badge {
        font-size: 0.8rem; padding: 4px 12px;
    }
    .difficulty-easy { background-color: #d4edda; color: #155724; }
    .difficulty-moderate { background-color: #fff3cd; color: #856404; }
    .difficulty-challenging { background-color: #ffeaa7; color: #7d6608; }
    .difficulty-difficult { background-color: #f8d7da; color: #721c24; }
    .difficulty-expert { background-color: #721c24; color: #fff; }

    .sticky-action { position: sticky; top: 80px; }

    /* Reviews */
    .review-stars { color: #f5a623; }
    .review-stars .bi-star-fill { color: #f5a623; }
    .review-stars .bi-star { color: #ccc; }
    .star-picker .star-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #ccc; transition: color 0.15s; padding: 2px; }
    .star-picker .star-btn.active, .star-picker .star-btn:hover { color: #f5a623; }
    .review-card { border-left: 3px solid var(--heco-green); }
    .review-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--heco-green); }
</style>
@endsection

@section('content')
{{-- Hero Section --}}
<div class="hero-section {{ $experience->card_image ? '' : 'hero-no-image' }}"
    @if($experience->card_image)
        style="background-image: url('/storage/{{ $experience->card_image }}');"
    @endif
>
    <div class="hero-content p-4 text-white">
        <div class="container">
            <span class="badge bg-success mb-2">{{ ucfirst($experience->type) }}</span>
            @if($experience->difficulty_level)
                <span class="badge difficulty-badge difficulty-{{ $experience->difficulty_level }} mb-2">
                    {{ ucfirst($experience->difficulty_level) }}
                </span>
            @endif
            <h1 class="fw-bold mb-1">{{ $experience->name }}</h1>
            <p class="mb-0">
                @if($experience->region)
                    <i class="bi bi-geo-alt-fill"></i> {{ $experience->region->name }}
                @endif
                @if($experience->hlh)
                    <span class="ms-3"><i class="bi bi-building"></i> {{ $experience->hlh->name }}</span>
                @endif
            </p>
        </div>
    </div>
</div>

<div class="container py-4">
    <div class="row">
        {{-- Main Content --}}
        <div class="col-lg-8">

            {{-- Info stat cards --}}
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card info-stat-card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="stat-icon"><i class="bi bi-clock"></i></div>
                            <div class="stat-value">
                                @if($experience->duration_type === 'less_than_day')
                                    {{ $experience->duration_hours }}h
                                @elseif($experience->duration_type === 'single_day')
                                    1 Day
                                @else
                                    {{ $experience->duration_days }}D / {{ $experience->duration_nights }}N
                                @endif
                            </div>
                            <div class="stat-label">Duration</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card info-stat-card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="stat-icon"><i class="bi bi-people"></i></div>
                            <div class="stat-value">
                                {{ $experience->group_size_min ?? '?' }} - {{ $experience->group_size_max ?? '?' }}
                            </div>
                            <div class="stat-label">Group Size</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card info-stat-card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="stat-icon"><i class="bi bi-speedometer2"></i></div>
                            <div class="stat-value">{{ ucfirst($experience->difficulty_level ?? 'N/A') }}</div>
                            <div class="stat-label">Difficulty</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card info-stat-card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="stat-icon"><i class="bi bi-currency-exchange"></i></div>
                            <div class="stat-value">
                                @if($experience->base_cost_per_person > 0)
                                    <span class="js-price" data-amount="{{ $experience->base_cost_per_person }}" data-currency="{{ $experience->price_currency ?? 'INR' }}"></span>
                                @else
                                    On Request
                                @endif
                            </div>
                            <div class="stat-label">Per Person</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Description --}}
            @if($experience->short_description || $experience->long_description || $experience->unique_description)
            <div class="detail-section">
                <h5><i class="bi bi-info-circle"></i> About This Experience</h5>
                @if($experience->short_description)
                    <p class="lead">{{ $experience->short_description }}</p>
                @endif
                @if($experience->long_description)
                    <div class="mb-3">{!! nl2br(e($experience->long_description)) !!}</div>
                @endif
                @if($experience->unique_description)
                    <div class="card bg-success bg-opacity-10 border-0 p-3 mb-3">
                        <h6 class="text-success"><i class="bi bi-star"></i> What Makes It Unique</h6>
                        <p class="mb-0">{{ $experience->unique_description }}</p>
                    </div>
                @endif
            </div>
            @endif

            {{-- Cultural Context --}}
            @if($experience->cultural_context)
            <div class="detail-section">
                <h5><i class="bi bi-buildings"></i> Cultural Context</h5>
                <p>{!! nl2br(e($experience->cultural_context)) !!}</p>
            </div>
            @endif

            {{-- Inclusions --}}
            <div class="detail-section">
                <h5><i class="bi bi-check2-square"></i> Inclusions</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="inclusion-item">
                            <div class="inclusion-icon {{ $experience->includes_accommodation ? 'included' : 'not-included' }}">
                                <i class="bi {{ $experience->includes_accommodation ? 'bi-check-lg' : 'bi-x-lg' }}"></i>
                            </div>
                            <div>
                                <strong>Accommodation</strong>
                                @if($experience->includes_accommodation && $experience->accommodation_category)
                                    <br><small class="text-muted">{{ ucfirst($experience->accommodation_category) }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="inclusion-item">
                            <div class="inclusion-icon {{ $experience->includes_guide ? 'included' : 'not-included' }}">
                                <i class="bi {{ $experience->includes_guide ? 'bi-check-lg' : 'bi-x-lg' }}"></i>
                            </div>
                            <div><strong>Guide</strong></div>
                        </div>
                        <div class="inclusion-item">
                            <div class="inclusion-icon {{ $experience->includes_transport ? 'included' : 'not-included' }}">
                                <i class="bi {{ $experience->includes_transport ? 'bi-check-lg' : 'bi-x-lg' }}"></i>
                            </div>
                            <div><strong>Transport</strong></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="inclusion-item">
                            <div class="inclusion-icon {{ $experience->includes_meals_breakfast ? 'included' : 'not-included' }}">
                                <i class="bi {{ $experience->includes_meals_breakfast ? 'bi-check-lg' : 'bi-x-lg' }}"></i>
                            </div>
                            <div><strong>Breakfast</strong></div>
                        </div>
                        <div class="inclusion-item">
                            <div class="inclusion-icon {{ $experience->includes_meals_lunch ? 'included' : 'not-included' }}">
                                <i class="bi {{ $experience->includes_meals_lunch ? 'bi-check-lg' : 'bi-x-lg' }}"></i>
                            </div>
                            <div><strong>Lunch</strong></div>
                        </div>
                        <div class="inclusion-item">
                            <div class="inclusion-icon {{ $experience->includes_meals_dinner ? 'included' : 'not-included' }}">
                                <i class="bi {{ $experience->includes_meals_dinner ? 'bi-check-lg' : 'bi-x-lg' }}"></i>
                            </div>
                            <div><strong>Dinner</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Requirements --}}
            @if($experience->fitness_requirements || $experience->age_min || $experience->traveller_bring_list || $experience->clothing_recommendations || $experience->health_notes)
            <div class="detail-section">
                <h5><i class="bi bi-clipboard-check"></i> Requirements & Preparation</h5>
                <div class="row g-3">
                    @if($experience->fitness_requirements)
                    <div class="col-md-6">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-3">
                                <h6><i class="bi bi-heart-pulse text-success"></i> Fitness Requirements</h6>
                                <p class="small mb-0">{{ $experience->fitness_requirements }}</p>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if($experience->age_min || $experience->age_max)
                    <div class="col-md-6">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-3">
                                <h6><i class="bi bi-person-badge text-success"></i> Age Range</h6>
                                <p class="small mb-0">{{ $experience->age_min ?? '?' }} - {{ $experience->age_max ?? '?' }} years</p>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if($experience->traveller_bring_list)
                    <div class="col-md-6">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-3">
                                <h6><i class="bi bi-bag-check text-success"></i> What to Bring</h6>
                                <p class="small mb-0">{!! nl2br(e($experience->traveller_bring_list)) !!}</p>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if($experience->clothing_recommendations)
                    <div class="col-md-6">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-3">
                                <h6><i class="bi bi-suit-heart text-success"></i> Clothing</h6>
                                <p class="small mb-0">{!! nl2br(e($experience->clothing_recommendations)) !!}</p>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if($experience->health_notes)
                    <div class="col-md-6">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-3">
                                <h6><i class="bi bi-heart text-success"></i> Health Notes</h6>
                                <p class="small mb-0">{!! nl2br(e($experience->health_notes)) !!}</p>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Seasonality --}}
            @if($experience->best_seasons || $experience->available_months)
            <div class="detail-section">
                <h5><i class="bi bi-calendar-heart"></i> Seasonality</h5>
                @if(is_array($experience->best_seasons) && count($experience->best_seasons) > 0)
                    <div class="mb-3">
                        <strong class="small text-muted">Best Seasons</strong><br>
                        @foreach($experience->best_seasons as $season)
                            <span class="badge season-badge bg-success bg-opacity-25 text-success">{{ ucfirst($season) }}</span>
                        @endforeach
                    </div>
                @endif
                @if(is_array($experience->available_months) && count($experience->available_months) > 0)
                    <div class="mb-3">
                        <strong class="small text-muted">Available Months</strong><br>
                        @php
                            $monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                        @endphp
                        @foreach($experience->available_months as $m)
                            <span class="badge month-badge bg-light text-dark border">{{ $monthNames[intval($m) - 1] ?? $m }}</span>
                        @endforeach
                    </div>
                @endif
                @if($experience->seasonality_notes)
                    <p class="small text-muted"><i class="bi bi-info-circle"></i> {{ $experience->seasonality_notes }}</p>
                @endif
            </div>
            @endif

            {{-- Location Info --}}
            @if($experience->area || $experience->altitude_min || $experience->altitude_max || $experience->trekking_required)
            <div class="detail-section">
                <h5><i class="bi bi-pin-map"></i> Location Information</h5>
                <div class="row g-2">
                    @if($experience->area)
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-geo text-success me-2"></i>
                            <div>
                                <small class="text-muted">Area</small><br>
                                <strong class="small">{{ $experience->area }}</strong>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if($experience->altitude_min || $experience->altitude_max)
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-arrow-up-right text-success me-2"></i>
                            <div>
                                <small class="text-muted">Altitude Range</small><br>
                                <strong class="small">{{ $experience->altitude_min ?? '?' }}m - {{ $experience->altitude_max ?? '?' }}m</strong>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-signpost-2 text-success me-2"></i>
                            <div>
                                <small class="text-muted">Trekking Required</small><br>
                                <strong class="small">{{ $experience->trekking_required ? 'Yes' : 'No' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Gallery --}}
            @if(is_array($experience->gallery) && count($experience->gallery) > 0)
            <div class="detail-section">
                <h5><i class="bi bi-images"></i> Gallery</h5>
                <div class="row g-2">
                    @foreach($experience->gallery as $img)
                    <div class="col-6 col-md-4">
                        <img src="/storage/{{ $img }}" alt="Gallery image" class="gallery-thumb" onclick="openGallery('{{ $img }}')">
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Regenerative Project Connection --}}
            @if($experience->regenerativeProject)
            <div class="detail-section">
                <h5><i class="bi bi-tree"></i> Regenerative Impact</h5>
                <div class="card bg-success bg-opacity-10 border-0">
                    <div class="card-body">
                        <h6>{{ $experience->regenerativeProject->name }}</h6>
                        <span class="badge bg-success mb-2">{{ $experience->regenerativeProject->action_type ?? 'Conservation' }}</span>
                        @if($experience->regenerativeProject->short_description)
                            <p class="small mb-0">{{ $experience->regenerativeProject->short_description }}</p>
                        @endif
                        @if($experience->regenerativeProject->impact_unit)
                            <p class="small text-muted mt-1 mb-0"><i class="bi bi-bar-chart"></i> Impact measured in: {{ $experience->regenerativeProject->impact_unit }}</p>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Reviews & Ratings --}}
            <div class="detail-section" id="reviewsSection">
                <h5><i class="bi bi-chat-square-text"></i> Reviews & Ratings</h5>

                {{-- Summary bar --}}
                <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
                    <div class="me-3 text-center">
                        <div class="fs-2 fw-bold text-success" id="avgRatingDisplay">{{ $avgRating ? number_format($avgRating, 1) : '--' }}</div>
                        <div class="review-stars" id="avgStarsDisplay">
                            @for($i = 1; $i <= 5; $i++)
                                <i class="bi {{ $avgRating && $i <= round($avgRating) ? 'bi-star-fill' : 'bi-star' }}"></i>
                            @endfor
                        </div>
                    </div>
                    <div>
                        <span id="reviewCountDisplay">{{ $experience->reviews_count }}</span> {{ Str::plural('review', $experience->reviews_count) }}
                    </div>
                </div>

                {{-- Review list (loaded via AJAX) --}}
                <div id="reviewsList"></div>
                <div id="reviewsLoading" class="text-center py-3 d-none">
                    <span class="spinner-border spinner-border-sm text-success"></span> Loading reviews...
                </div>
                <div id="reviewsEmpty" class="text-center text-muted py-3 d-none">
                    No reviews yet. Be the first to share your experience!
                </div>
                <button class="btn btn-outline-secondary btn-sm w-100 mt-2 d-none" id="btnLoadMoreReviews">
                    Load More Reviews
                </button>

                <hr class="my-4">

                {{-- Submit review form --}}
                @auth
                    <h6>Write a Review</h6>
                    <div id="reviewFormWrapper">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Your Rating</label>
                            <div class="star-picker" id="starPicker">
                                @for($i = 1; $i <= 5; $i++)
                                    <button type="button" class="star-btn" data-value="{{ $i }}"><i class="bi bi-star"></i></button>
                                @endfor
                            </div>
                            <input type="hidden" id="reviewRating" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Title (optional)</label>
                            <input type="text" class="form-control" id="reviewTitle" maxlength="100" placeholder="Summarise your experience">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Your Review</label>
                            <textarea class="form-control" id="reviewBody" rows="3" maxlength="1000" placeholder="Share your thoughts about this experience..."></textarea>
                            <div class="form-text text-end"><span id="reviewCharCount">0</span>/1000</div>
                        </div>
                        <button class="btn btn-success" id="btnSubmitReview">
                            <i class="bi bi-send"></i> Submit Review
                        </button>
                    </div>
                    <div id="reviewAlreadySubmitted" class="alert alert-info d-none">
                        <i class="bi bi-check-circle"></i> You have already reviewed this experience.
                    </div>
                @else
                    <div class="text-center py-3">
                        <p class="text-muted mb-2">Log in to write a review</p>
                        <button class="btn btn-outline-success btn-sm" onclick="if(window.openAuthModal) window.openAuthModal('login'); else window.location.href='/home?auth=login';">
                            <i class="bi bi-box-arrow-in-right"></i> Log In
                        </button>
                    </div>
                @endauth
            </div>

        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            <div class="sticky-action">
                <div class="card shadow-sm mb-3">
                    <div class="card-body p-4 text-center">
                        @if($experience->base_cost_per_person > 0)
                            <div class="mb-2">
                                <span class="fs-3 fw-bold text-success js-price" data-amount="{{ $experience->base_cost_per_person }}" data-currency="{{ $experience->price_currency ?? 'INR' }}"></span>
                                <span class="text-muted">/ person</span>
                            </div>
                        @else
                            <p class="text-muted mb-2">Price on request</p>
                        @endif

                        @auth
                            <button class="btn btn-success btn-lg w-100 mb-2" id="btnAddToJourney">
                                <i class="bi bi-plus-lg"></i> Add to Journey
                            </button>
                            <button class="btn btn-outline-success w-100" id="btnPrefer">
                                <i class="bi bi-heart"></i> Save to Wishlist
                            </button>
                        @else
                            <button class="btn btn-success btn-lg w-100 mb-2" id="btnGuestAddToJourney">
                                <i class="bi bi-plus-lg"></i> Add to Journey
                            </button>
                            <button class="btn btn-outline-success w-100" id="btnGuestWishlist">
                                <i class="bi bi-heart"></i> Save to Wishlist
                            </button>
                        @endauth
                    </div>
                </div>

                {{-- Quick Facts --}}
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-lightning"></i> Quick Facts</h6></div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between small">
                                <span><i class="bi bi-geo-alt text-success"></i> Region</span>
                                <strong>{{ $experience->region->name ?? 'N/A' }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between small">
                                <span><i class="bi bi-tag text-success"></i> Type</span>
                                <strong>{{ ucfirst($experience->type) }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between small">
                                <span><i class="bi bi-speedometer2 text-success"></i> Difficulty</span>
                                <strong>{{ ucfirst($experience->difficulty_level ?? 'N/A') }}</strong>
                            </li>
                            @if($experience->weather_dependency)
                            <li class="list-group-item d-flex justify-content-between small">
                                <span><i class="bi bi-cloud-sun text-success"></i> Weather</span>
                                <strong>{{ ucfirst($experience->weather_dependency) }}</strong>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>

                <a href="/home" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-left"></i> Back to Discover
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Gallery Modal --}}
<div class="modal fade" id="galleryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body text-center p-0">
                <img id="galleryModalImg" src="" class="img-fluid rounded" style="max-height: 80vh;">
                <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
jQuery(function() {
    // Render JS-converted prices
    function renderPrices() {
        jQuery('.js-price').each(function() {
            var el = jQuery(this);
            var amount = parseFloat(el.data('amount'));
            var currency = el.data('currency') || 'INR';
            el.text(fmtCurrency(amount, currency));
        });
    }
    renderPrices();

    jQuery(document).on('currencyChanged', function() {
        renderPrices();
    });

    var experienceId = {!! json_encode($experience->id) !!};

    // Add to Journey — create trip first if none exists
    var detailTripId = null;

    function ensureDetailTrip(callback) {
        if (detailTripId) {
            callback(detailTripId);
        } else {
            ajaxPost({ create_trip: 1 }, function(resp) {
                detailTripId = resp.trip_id;
                callback(detailTripId);
            });
        }
    }

    jQuery('#btnAddToJourney').on('click', function() {
        var btn = jQuery(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Adding...');

        ensureDetailTrip(function(tId) {
            ajaxPost({ add_experience_to_trip: 1, trip_id: tId, experience_id: experienceId }, function(resp) {
                btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Added to Journey!');
                btn.removeClass('btn-success').addClass('btn-outline-success');
                showAlert('Experience added to your journey!', 'success');
                setTimeout(function() {
                    btn.html('<i class="bi bi-plus-lg"></i> Add to Journey').removeClass('btn-outline-success').addClass('btn-success');
                }, 3000);
            }, function(xhr) {
                btn.prop('disabled', false).html('<i class="bi bi-plus-lg"></i> Add to Journey');
            });
        });
    });

    // Prefer / Wishlist
    jQuery('#btnPrefer').on('click', function() {
        var btn = jQuery(this);
        ensureDetailTrip(function(tId) {
            ajaxPost({ prefer_experience: 1, trip_id: tId, experience_id: experienceId }, function(resp) {
                if (btn.hasClass('preferred')) {
                    btn.removeClass('preferred').html('<i class="bi bi-heart"></i> Save to Wishlist');
                } else {
                    btn.addClass('preferred').html('<i class="bi bi-heart-fill"></i> Saved!');
                }
                if (resp.message) showAlert(resp.message, 'success');
            });
        });
    });

    // Guest: Add to Journey (localStorage)
    jQuery('#btnGuestAddToJourney').on('click', function() {
        var btn = jQuery(this);
        var expName = {!! json_encode($experience->name) !!};
        var expSlug = {!! json_encode($experience->slug) !!};

        try {
            var items = JSON.parse(localStorage.getItem('heco_guest_journey') || '[]');
            var exists = false;
            for (var i = 0; i < items.length; i++) {
                if (items[i].id === experienceId) { exists = true; break; }
            }
            if (!exists) {
                items.push({ id: experienceId, name: expName, slug: expSlug });
                localStorage.setItem('heco_guest_journey', JSON.stringify(items));
                btn.html('<i class="bi bi-check-lg"></i> Added to Journey!').removeClass('btn-success').addClass('btn-outline-success');
                showAlert('"' + expName + '" added to your journey!', 'success');
                setTimeout(function() {
                    btn.html('<i class="bi bi-plus-lg"></i> Add to Journey').removeClass('btn-outline-success').addClass('btn-success');
                }, 3000);
            } else {
                showAlert('This experience is already in your journey.', 'info');
            }
        } catch (e) {
            showAlert('Could not save. Please try again.', 'danger');
        }
    });

    // Guest: Wishlist requires login
    jQuery('#btnGuestWishlist').on('click', function() {
        if (window.openAuthModal) {
            window.openAuthModal('login');
        } else {
            window.location.href = '/home?auth=login';
        }
    });
});

    // ===== Reviews =====
    var reviewPage = 1;

    function renderStars(rating) {
        var html = '';
        for (var i = 1; i <= 5; i++) {
            html += '<i class="bi ' + (i <= rating ? 'bi-star-fill' : 'bi-star') + '"></i>';
        }
        return html;
    }

    function renderReview(r) {
        var userName = r.user ? r.user.full_name : 'Anonymous';
        var initial = userName.charAt(0).toUpperCase();
        var avatar = r.user && r.user.avatar
            ? '<img src="/storage/' + r.user.avatar + '" class="review-avatar" alt="">'
            : '<div class="review-avatar">' + initial + '</div>';
        var date = new Date(r.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        var title = r.title ? '<strong>' + jQuery('<span>').text(r.title).html() + '</strong><br>' : '';
        return '<div class="card review-card mb-3"><div class="card-body p-3">' +
            '<div class="d-flex align-items-center mb-2">' + avatar +
            '<div class="ms-2"><strong class="small">' + jQuery('<span>').text(userName).html() + '</strong>' +
            '<div class="review-stars small">' + renderStars(r.rating) + '</div></div>' +
            '<small class="text-muted ms-auto">' + date + '</small></div>' +
            title + '<p class="mb-0 small">' + jQuery('<span>').text(r.body).html() + '</p></div></div>';
    }

    function loadReviews(page) {
        jQuery('#reviewsLoading').removeClass('d-none');
        ajaxPost({ get_reviews: 1, experience_id: experienceId, page: page }, function(resp) {
            jQuery('#reviewsLoading').addClass('d-none');
            if (resp.reviews && resp.reviews.length > 0) {
                jQuery('#reviewsEmpty').addClass('d-none');
                resp.reviews.forEach(function(r) {
                    jQuery('#reviewsList').append(renderReview(r));
                });
                if (resp.has_more) {
                    reviewPage = resp.next_page;
                    jQuery('#btnLoadMoreReviews').removeClass('d-none');
                } else {
                    jQuery('#btnLoadMoreReviews').addClass('d-none');
                }
            } else if (page === 1) {
                jQuery('#reviewsEmpty').removeClass('d-none');
            }
        }, function() {
            jQuery('#reviewsLoading').addClass('d-none');
        });
    }

    loadReviews(1);

    jQuery('#btnLoadMoreReviews').on('click', function() {
        loadReviews(reviewPage);
    });

    // Star picker
    jQuery('#starPicker .star-btn').on('click', function() {
        var val = jQuery(this).data('value');
        jQuery('#reviewRating').val(val);
        jQuery('#starPicker .star-btn').each(function() {
            var sv = jQuery(this).data('value');
            jQuery(this).toggleClass('active', sv <= val)
                .find('i').attr('class', sv <= val ? 'bi bi-star-fill' : 'bi bi-star');
        });
    });

    // Char count
    jQuery('#reviewBody').on('input', function() {
        jQuery('#reviewCharCount').text(jQuery(this).val().length);
    });

    // Submit review
    jQuery('#btnSubmitReview').on('click', function() {
        var rating = parseInt(jQuery('#reviewRating').val());
        var body = jQuery('#reviewBody').val().trim();
        var title = jQuery('#reviewTitle').val().trim();

        if (!rating || rating < 1) { showAlert('Please select a star rating.', 'warning'); return; }
        if (!body) { showAlert('Please write your review.', 'warning'); return; }

        var btn = jQuery(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Submitting...');

        ajaxPost({
            submit_review: 1,
            experience_id: experienceId,
            rating: rating,
            title: title || null,
            body: body,
        }, function(resp) {
            btn.prop('disabled', false).html('<i class="bi bi-send"></i> Submit Review');
            if (resp.review) {
                jQuery('#reviewsList').prepend(renderReview(resp.review));
                jQuery('#reviewsEmpty').addClass('d-none');
                jQuery('#reviewFormWrapper').addClass('d-none');
                jQuery('#reviewAlreadySubmitted').removeClass('d-none');
                // Update summary
                jQuery('#avgRatingDisplay').text(resp.avg_rating);
                jQuery('#avgStarsDisplay').html(renderStars(Math.round(resp.avg_rating)));
                jQuery('#reviewCountDisplay').text(resp.review_count);
                showAlert('Review submitted successfully!', 'success');
            }
        }, function(xhr) {
            btn.prop('disabled', false).html('<i class="bi bi-send"></i> Submit Review');
            var msg = xhr.responseJSON ? xhr.responseJSON.error : 'Failed to submit review.';
            if (msg.indexOf('already') !== -1) {
                jQuery('#reviewFormWrapper').addClass('d-none');
                jQuery('#reviewAlreadySubmitted').removeClass('d-none');
            }
            showAlert(msg, 'danger');
        });
    });
});

function openGallery(imgPath) {
    jQuery('#galleryModalImg').attr('src', '/storage/' + imgPath);
    new bootstrap.Modal(document.getElementById('galleryModal')).show();
}
</script>
@endsection
