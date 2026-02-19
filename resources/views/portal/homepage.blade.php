@extends('portal.layout')
@section('title', 'Explore Experiences - HECO Portal')

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<style>
    .experience-grid-split {
        max-height: calc(100vh - 280px);
        overflow-y: auto;
        grid-template-columns: repeat(2, 1fr) !important;
    }
    .experience-grid-split .exp-card {
        margin-bottom: 0;
    }
    .experience-grid-split .exp-card.map-highlight {
        box-shadow: 0 0 0 3px var(--heco-success, #2d6a4f);
        transition: box-shadow 0.3s ease;
    }
    .leaflet-popup-content-wrapper {
        border-radius: 10px;
    }
    .leaflet-popup-content {
        margin: 8px 12px;
        font-size: 13px;
        line-height: 1.4;
    }
    .map-popup-title {
        font-weight: 600;
        margin-bottom: 4px;
    }
    .map-popup-meta {
        color: #6c757d;
        font-size: 12px;
    }
    .map-popup-price {
        color: #2d6a4f;
        font-weight: 600;
        margin-top: 4px;
    }
    @media (max-width: 991px) {
        .experience-grid-split {
            max-height: none;
            overflow-y: visible;
        }
        #discoverMap {
            height: 350px !important;
            position: relative !important;
            top: 0 !important;
            margin-bottom: 16px;
        }
        .col-lg-6:has(#discoverMap) {
            order: -1;
        }
    }
</style>
@endsection

@section('content')
@php
if ($trip) {
    $tripId = $trip->id;
    $selectedExpIds = $trip->selectedExperiences->pluck('experience_id')->toArray();
} elseif (!empty($guestTripData['experience_ids'] ?? [])) {
    $tripId = 'guest';
    $selectedExpIds = $guestTripData['experience_ids'];
} else {
    $tripId = null;
    $selectedExpIds = [];
}
$hasTrip = $trip || ($tripId === 'guest');
// Preference values (from DB trip or guest session)
$pAccom = $trip->accommodation_comfort ?? ($guestTripData['accommodation_comfort'] ?? '');
$pVehicle = $trip->vehicle_comfort ?? ($guestTripData['vehicle_comfort'] ?? '');
$pGuide = $trip->guide_preference ?? ($guestTripData['guide_preference'] ?? '');
$pPace = $trip->travel_pace ?? ($guestTripData['travel_pace'] ?? '');
$pBudget = $trip->budget_sensitivity ?? ($guestTripData['budget_sensitivity'] ?? '');
@endphp

<div class="heco-page">
    {{-- Hero Section --}}
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="bi bi-globe-americas"></i>
                    Regenerative Travel Worldwide
                </div>
                <h1 class="hero-title">Discover Transformative Experiences</h1>
                <p class="hero-subtitle">
                    Explore authentic adventures that connect you with local communities and contribute to the regeneration of ecosystems worldwide.
                </p>
            </div>
        </div>
    </section>

    {{-- Main Tab Navigation --}}
    <div class="main-tabs-wrapper">
        <div class="container">
            <ul class="nav main-tabs" id="mainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-discover" data-bs-toggle="tab" data-bs-target="#pane-discover" type="button" role="tab">
                        <i class="bi bi-compass"></i>
                        <span>Discover Regions and Experiences</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-journey" data-bs-toggle="tab" data-bs-target="#pane-journey" type="button" role="tab">
                        <i class="bi bi-map"></i>
                        <span>Your Journey</span>
                        <span class="tab-badge" id="journeyCount">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-impact" data-bs-toggle="tab" data-bs-target="#pane-impact" type="button" role="tab">
                        <i class="bi bi-leaf"></i>
                        <span>Impact</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="mainTabContent">

        {{-- ============================================= --}}
        {{-- DISCOVER TAB --}}
        {{-- ============================================= --}}
        <div class="tab-pane fade show active" id="pane-discover" role="tabpanel">
            <div class="content-container">

                {{-- Inline AI Chat Widget --}}
                <div class="inline-chat-section mb-4">
                    <h2 class="inline-chat-heading">
                        <i class="bi bi-robot"></i>
                        HECO AI Assistant
                    </h2>
                    <div id="inlineChatMessages">
                        <div class="chat-msg assistant">
                            Hi! I'm HECO AI. Tell me about your trip &mdash; dates, group size, interests &mdash; and I'll find the perfect experiences for you.
                        </div>
                    </div>
                    <div class="inline-chat-input-area">
                        <input type="text" class="inline-chat-input" id="inlineChatInput"
                            placeholder="Ask anything about experiences, destinations, travel plans..."
                            autocomplete="off">
                        <button class="inline-chat-send" id="inlineChatSend">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </div>

                {{-- Filter bar --}}
                <div class="filter-bar">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3 col-md-6 col-6">
                            <label class="form-label">Experience Type</label>
                            <select class="form-select" id="filterType">
                                <option value="">All Types</option>
                                <option value="trek">Trek</option>
                                <option value="cultural">Cultural</option>
                                <option value="spiritual">Spiritual</option>
                                <option value="nature">Nature & Wildlife</option>
                                <option value="adventure">Adventure</option>
                                <option value="wellness">Wellness</option>
                                <option value="culinary">Culinary</option>
                                <option value="volunteering">Volunteering</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 col-6">
                            <label class="form-label">Difficulty</label>
                            <select class="form-select" id="filterDifficulty">
                                <option value="">All Levels</option>
                                <option value="easy">Easy</option>
                                <option value="moderate">Moderate</option>
                                <option value="challenging">Challenging</option>
                                <option value="difficult">Difficult</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 col-6">
                            <label class="form-label">Month</label>
                            <select class="form-select" id="filterMonth">
                                <option value="">Any Month</option>
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 col-6">
                            <button class="btn-clear-filters w-100" id="clearFilters">
                                <i class="bi bi-x-circle"></i>
                                <span>Clear Filters</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Split: Experience list + Map --}}
                <div class="row g-3">
                    {{-- Left: Experience cards --}}
                    <div class="col-lg-6">
                        <div id="experienceGrid" class="experience-grid experience-grid-split">
                            <div class="loading-state" style="grid-column: 1 / -1;">
                                <div class="loading-spinner"></div>
                                <p class="loading-text">Loading experiences...</p>
                            </div>
                        </div>

                        {{-- Load more --}}
                        <div class="load-more-wrapper d-none" id="loadMoreWrap">
                            <button class="btn-load-more" id="loadMore">
                                <i class="bi bi-arrow-down-circle"></i>
                                <span>Load More Experiences</span>
                            </button>
                        </div>
                    </div>

                    {{-- Right: Map --}}
                    <div class="col-lg-6">
                        <div id="discoverMap" style="height: calc(100vh - 280px); min-height: 400px; border-radius: 12px; position: sticky; top: 100px; z-index: 1;"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================= --}}
        {{-- YOUR JOURNEY TAB --}}
        {{-- ============================================= --}}
        <div class="tab-pane fade" id="pane-journey" role="tabpanel">
            <div class="content-container">
                <div id="journeyContent">
                    {{-- Shown if no trip --}}
                    <div id="noTripMessage" class="journey-empty-state {{ $hasTrip ? 'd-none' : '' }}">
                        <div class="journey-empty-icon">
                            <i class="bi bi-map"></i>
                        </div>
                        <h3 class="journey-empty-title">Your journey starts here</h3>
                        <p class="journey-empty-desc">Add experiences from the Discover tab or chat with our AI assistant to begin planning your perfect adventure.</p>
                        <button class="exp-btn exp-btn-primary" style="display: inline-flex; padding: var(--space-4) var(--space-8);" onclick="jQuery('#tab-discover').click();">
                            <i class="bi bi-compass"></i> Explore Experiences
                        </button>
                    </div>

                    {{-- Shown if trip exists --}}
                    <div id="journeyPanels" class="journey-panels {{ $hasTrip ? '' : 'd-none' }}">
                        {{-- Left: Selected Experiences List --}}
                        <div class="journey-panel">
                            <div class="journey-panel-header">
                                <h6 class="journey-panel-title"><i class="bi bi-list-check"></i> Experiences</h6>
                                <span class="tab-badge" id="expListCount">0</span>
                            </div>
                            <div class="journey-panel-body journey-sidebar" id="selectedExpList">
                                <p class="text-center" style="font-size: var(--text-sm); color: var(--color-text-muted); padding: var(--space-4);">No experiences added yet</p>
                            </div>
                        </div>

                        {{-- Center: Timeline --}}
                        <div class="journey-panel">
                            <div class="journey-panel-header">
                                <h6 class="journey-panel-title"><i class="bi bi-calendar3"></i> Trip Timeline</h6>
                                <div class="d-flex gap-2">
                                    <button class="exp-btn exp-btn-primary" style="padding: var(--space-2) var(--space-3);" id="btnAddDay">
                                        <i class="bi bi-plus-lg"></i> Add Day
                                    </button>
                                </div>
                            </div>
                            <div class="timeline-container" id="timelineContainer">
                                <p class="text-center" style="font-size: var(--text-sm); color: var(--color-text-muted); padding: var(--space-6);" id="emptyTimeline">
                                    Days will appear here when experiences are added
                                </p>
                            </div>
                        </div>

                        {{-- Right: Details & Pricing --}}
                        <div>
                            {{-- Trip Name --}}
                            <div class="detail-card">
                                <div class="detail-card-body">
                                    <label class="form-label">Trip Name</label>
                                    <input type="text" class="form-control" id="tripName" placeholder="Name your trip..." value="{{ $trip->trip_name ?? ($guestTripData['trip_name'] ?? '') }}">
                                </div>
                            </div>

                            {{-- Group Details --}}
                            <div class="detail-card">
                                <div class="detail-card-header"><i class="bi bi-people"></i> Group Details</div>
                                <div class="detail-card-body">
                                    <div class="row g-2">
                                        <div class="col-4">
                                            <label class="form-label">Adults</label>
                                            <input type="number" class="form-control group-input" id="grpAdults" min="1" max="20" value="{{ $trip->adults ?? ($guestTripData['adults'] ?? 2) }}">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label">Children</label>
                                            <input type="number" class="form-control group-input" id="grpChildren" min="0" max="10" value="{{ $trip->children ?? ($guestTripData['children'] ?? 0) }}">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label">Infants</label>
                                            <input type="number" class="form-control group-input" id="grpInfants" min="0" max="5" value="{{ $trip->infants ?? ($guestTripData['infants'] ?? 0) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Travel Preferences --}}
                            <div class="detail-card">
                                <div class="detail-card-header"><i class="bi bi-sliders"></i> Travel Preferences</div>
                                <div class="detail-card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Accommodation Comfort</label>
                                        <select class="form-select pref-input" id="prefAccommodation">
                                            <option value="">-- Select --</option>
                                            <option value="basic" {{ $pAccom == 'basic' ? 'selected' : '' }}>Basic</option>
                                            <option value="standard" {{ $pAccom == 'standard' ? 'selected' : '' }}>Standard</option>
                                            <option value="comfort" {{ $pAccom == 'comfort' ? 'selected' : '' }}>Comfort</option>
                                            <option value="premium" {{ $pAccom == 'premium' ? 'selected' : '' }}>Premium</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Vehicle Comfort</label>
                                        <select class="form-select pref-input" id="prefVehicle">
                                            <option value="">-- Select --</option>
                                            <option value="shared" {{ $pVehicle == 'shared' ? 'selected' : '' }}>Shared</option>
                                            <option value="private_basic" {{ $pVehicle == 'private_basic' ? 'selected' : '' }}>Private Basic</option>
                                            <option value="private_suv" {{ $pVehicle == 'private_suv' ? 'selected' : '' }}>Private SUV</option>
                                            <option value="private_premium" {{ $pVehicle == 'private_premium' ? 'selected' : '' }}>Private Premium</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Guide Preference</label>
                                        <select class="form-select pref-input" id="prefGuide">
                                            <option value="">-- Select --</option>
                                            <option value="no_guide" {{ $pGuide == 'no_guide' ? 'selected' : '' }}>No Guide</option>
                                            <option value="local_guide" {{ $pGuide == 'local_guide' ? 'selected' : '' }}>Local Guide</option>
                                            <option value="certified_guide" {{ $pGuide == 'certified_guide' ? 'selected' : '' }}>Certified Guide</option>
                                            <option value="expert_guide" {{ $pGuide == 'expert_guide' ? 'selected' : '' }}>Expert Guide</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Travel Pace</label>
                                        <select class="form-select pref-input" id="prefPace">
                                            <option value="">-- Select --</option>
                                            <option value="relaxed" {{ $pPace == 'relaxed' ? 'selected' : '' }}>Relaxed</option>
                                            <option value="moderate" {{ $pPace == 'moderate' ? 'selected' : '' }}>Moderate</option>
                                            <option value="active" {{ $pPace == 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="intensive" {{ $pPace == 'intensive' ? 'selected' : '' }}>Intensive</option>
                                        </select>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">Budget Sensitivity</label>
                                        <select class="form-select pref-input" id="prefBudget">
                                            <option value="">-- Select --</option>
                                            <option value="budget" {{ $pBudget == 'budget' ? 'selected' : '' }}>Budget-Friendly</option>
                                            <option value="value" {{ $pBudget == 'value' ? 'selected' : '' }}>Best Value</option>
                                            <option value="comfort" {{ $pBudget == 'comfort' ? 'selected' : '' }}>Comfort Priority</option>
                                            <option value="no_limit" {{ $pBudget == 'no_limit' ? 'selected' : '' }}>No Limit</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Pricing Summary --}}
                            <div class="detail-card">
                                <div class="detail-card-header"><i class="bi bi-receipt"></i> Pricing Summary</div>
                                <div class="detail-card-body" id="pricingSummary">
                                    <div class="pricing-row"><span>Transport</span><span id="prTransport">--</span></div>
                                    <div class="pricing-row"><span>Accommodation</span><span id="prAccommodation">--</span></div>
                                    <div class="pricing-row"><span>Guide</span><span id="prGuide">--</span></div>
                                    <div class="pricing-row"><span>Activities</span><span id="prActivities">--</span></div>
                                    <div class="pricing-row"><span>Other</span><span id="prOther">--</span></div>
                                    <div class="pricing-row"><span>Subtotal</span><span id="prSubtotal">--</span></div>
                                    <div class="pricing-row"><span>RP Contribution</span><span id="prRP" class="rp-contribution">--</span></div>
                                    <div class="pricing-row"><span>GST</span><span id="prGST">--</span></div>
                                    <div class="pricing-row total"><span>Final Price</span><span id="prFinal">--</span></div>
                                </div>
                            </div>

                            {{-- Request Support --}}
                            <div class="detail-card">
                                <div class="detail-card-body">
                                    <textarea class="form-control mb-3" id="supportMessage" rows="2" placeholder="Need help? Describe your question..."></textarea>
                                    <button class="btn-request-support" id="btnRequestSupport">
                                        <i class="bi bi-headset"></i> Request Support
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================= --}}
        {{-- IMPACT TAB --}}
        {{-- ============================================= --}}
        <div class="tab-pane fade" id="pane-impact" role="tabpanel">
            <div class="content-container">
                <div id="impactContent">
                    <div id="noImpactMessage" class="journey-empty-state {{ $hasTrip ? 'd-none' : '' }}">
                        <div class="journey-empty-icon">
                            <i class="bi bi-leaf"></i>
                        </div>
                        <h3 class="journey-empty-title">See your positive impact</h3>
                        <p class="journey-empty-desc">Build your journey first, then discover how your trip contributes to regenerative projects in the Himalayas.</p>
                    </div>

                    <div id="impactData" class="{{ $hasTrip ? '' : 'd-none' }}">
                        {{-- Summary row --}}
                        <div class="impact-summary-grid" id="impactSummaryRow">
                            <div class="impact-stat-card">
                                <div class="impact-stat-value" id="impactTotalRP">--</div>
                                <div class="impact-stat-label">Total RP Contribution</div>
                            </div>
                            <div class="impact-stat-card">
                                <div class="impact-stat-value" id="impactRegionCount">--</div>
                                <div class="impact-stat-label">Impact Regions</div>
                            </div>
                            <div class="impact-stat-card">
                                <div class="impact-stat-value" id="impactProjectCount">--</div>
                                <div class="impact-stat-label">Projects Supported</div>
                            </div>
                        </div>

                        {{-- Per-region impact cards --}}
                        <div class="impact-section-header">
                            <div class="section-icon">
                                <i class="bi bi-tree"></i>
                            </div>
                            <h3 class="impact-section-title">Your Impact Breakdown</h3>
                        </div>
                        <div class="impact-cards-grid" id="impactCards">
                            <div class="loading-state" style="grid-column: 1 / -1;">
                                <div class="loading-spinner"></div>
                                <p class="loading-text">Calculating your impact...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
jQuery(function() {
    var isLoggedIn = {{ auth()->check() ? 'true' : 'false' }};
    var tripId = {!! json_encode($tripId) !!};
    var selectedExpIds = {!! json_encode($selectedExpIds) !!};
    var discoverPage = 1;
    var discoverLoading = false;
    var discoverHasMore = true;
    var currentRegionId = '';
    var debounceTimer = null;

    // ===================================
    // LEAFLET MAP INIT
    // ===================================
    var map = L.map('discoverMap').setView([20, 60], 3);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18
    }).addTo(map);

    var markers = [];
    var markerMap = {};

    var redIcon = L.icon({
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41],
        className: 'hue-rotate-marker'
    });

    // Add CSS for red marker tint
    var markerStyle = document.createElement('style');
    markerStyle.textContent = '.hue-rotate-marker { filter: hue-rotate(140deg) saturate(1.5); }';
    document.head.appendChild(markerStyle);

    function clearMarkers() {
        markers.forEach(function(m) { map.removeLayer(m); });
        markers = [];
        markerMap = {};
    }

    function updateMapMarkers(experiences) {
        clearMarkers();
        var bounds = [];

        experiences.forEach(function(exp) {
            var lat = parseFloat(exp.start_latitude || (exp.region ? exp.region.latitude : 0));
            var lng = parseFloat(exp.start_longitude || (exp.region ? exp.region.longitude : 0));
            if (!lat || !lng) return;

            var durationText = '';
            if (exp.duration_type === 'less_than_day') durationText = exp.duration_hours + 'h';
            else if (exp.duration_type === 'single_day') durationText = '1 Day';
            else durationText = (exp.duration_days || '?') + ' Days';

            var popupHtml = '<div class="map-popup-title">' + exp.name + '</div>';
            popupHtml += '<div class="map-popup-meta">';
            if (exp.region) popupHtml += '<i class="bi bi-geo-alt"></i> ' + exp.region.name + '<br>';
            popupHtml += '<i class="bi bi-clock"></i> ' + durationText;
            if (exp.difficulty_level) popupHtml += ' &middot; ' + exp.difficulty_level;
            popupHtml += '</div>';
            if (exp.base_cost_per_person > 0) {
                popupHtml += '<div class="map-popup-price">' + fmtCurrency(exp.base_cost_per_person, exp.price_currency || 'INR') + '/person</div>';
            }

            var marker = L.marker([lat, lng], { icon: redIcon })
                .bindPopup(popupHtml, { maxWidth: 250 })
                .addTo(map);

            marker.on('click', function() {
                var card = jQuery('#experienceGrid .exp-card[data-exp-id="' + exp.id + '"]');
                if (card.length) {
                    jQuery('#experienceGrid .exp-card').removeClass('map-highlight');
                    card.addClass('map-highlight');
                    var grid = jQuery('#experienceGrid');
                    grid.animate({ scrollTop: grid.scrollTop() + card.position().top - 10 }, 300);
                }
            });

            markers.push(marker);
            markerMap[exp.id] = marker;
            bounds.push([lat, lng]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 10 });
        }
    }

    // Highlight marker on card hover
    jQuery(document).on('mouseenter', '.exp-card', function() {
        var expId = jQuery(this).data('exp-id');
        if (markerMap[expId]) {
            markerMap[expId].openPopup();
        }
    });

    jQuery(document).on('mouseleave', '.exp-card', function() {
        var expId = jQuery(this).data('exp-id');
        if (markerMap[expId]) {
            markerMap[expId].closePopup();
        }
    });

    // ===================================
    // UTILITY
    // ===================================
    // fmt() and fmtCurrency() are now global from layout.blade.php

    // ===================================
    // AUTO AI GENERATION
    // ===================================
    var aiGenerating = false;

    function autoGenerateItinerary() {
        if (aiGenerating || !tripId || selectedExpIds.length === 0) return;
        aiGenerating = true;

        jQuery('#timelineContainer').html(
            '<div class="text-center py-4">' +
            '<span class="spinner-border spinner-border-sm me-2"></span>' +
            '<span style="font-size: var(--text-sm); color: var(--color-text-muted);">Generating your itinerary...</span>' +
            '</div>'
        );

        jQuery.ajax({
            url: '/ajax',
            method: 'POST',
            data: { generate_itinerary: 1, trip_id: tripId },
            timeout: 120000,
            success: function(resp) {
                aiGenerating = false;
                if (resp.success) {
                    loadTimeline();
                    loadPricing();
                } else {
                    jQuery('#timelineContainer').html(
                        '<p class="text-center" style="font-size: var(--text-sm); color: var(--color-text-muted); padding: var(--space-6);">' + (resp.error || 'Generation failed. Try again later.') + '</p>'
                    );
                }
            },
            error: function(xhr) {
                aiGenerating = false;
                var msg = xhr.statusText === 'timeout' ? 'AI generation timed out. Try again later.' : 'AI generation failed.';
                jQuery('#timelineContainer').html(
                    '<p class="text-center" style="font-size: var(--text-sm); color: var(--color-text-muted); padding: var(--space-6);">' + msg + '</p>'
                );
            }
        });
    }

    function ensureTrip(callback) {
        if (tripId) {
            callback(tripId);
        } else if (!isLoggedIn) {
            // Guest: no DB call, just use session via "guest" pseudo-ID
            tripId = 'guest';
            jQuery('#noTripMessage').addClass('d-none');
            jQuery('#journeyPanels').removeClass('d-none');
            jQuery('#noImpactMessage').addClass('d-none');
            jQuery('#impactData').removeClass('d-none');
            callback(tripId);
        } else {
            ajaxPost({ create_trip: 1 }, function(resp) {
                tripId = resp.trip_id;
                jQuery('#noTripMessage').addClass('d-none');
                jQuery('#journeyPanels').removeClass('d-none');
                jQuery('#noImpactMessage').addClass('d-none');
                jQuery('#impactData').removeClass('d-none');
                callback(tripId);
            });
        }
    }

    function updateJourneyBadge() {
        jQuery('#journeyCount').text(selectedExpIds.length);
        jQuery('#expListCount').text(selectedExpIds.length);
    }

    updateJourneyBadge();



    // ===================================
    // DISCOVER TAB
    // ===================================

    // Filter changes
    jQuery('#filterType, #filterDifficulty, #filterMonth').on('change', function() {
        discoverPage = 1;
        discoverHasMore = true;
        loadExperiences(false);
    });

    // Clear filters
    jQuery('#clearFilters').on('click', function() {
        jQuery('#filterType, #filterDifficulty, #filterMonth').val('');
        jQuery('#experienceGrid .exp-card').removeClass('ai-recommended');
        currentRegionId = '';
        discoverPage = 1;
        discoverHasMore = true;
        loadExperiences(false);

        if (typeof map !== 'undefined') {
            map.setView([20, 60], 3);
        }
    });

    // Load more
    jQuery('#loadMore').on('click', function() {
        discoverPage++;
        loadExperiences(true);
    });

    var allDiscoverExps = [];

    function buildExpCardHtml(exp) {
        var isPreferred = selectedExpIds.indexOf(exp.id) !== -1;
        var imgHtml = exp.card_image
            ? '<img src="/storage/' + exp.card_image + '" alt="' + exp.name + '">'
            : '<div class="exp-placeholder"><i class="bi bi-image"></i></div>';

        var durationText = '';
        if (exp.duration_type === 'less_than_day') {
            durationText = exp.duration_hours + 'h';
        } else if (exp.duration_type === 'single_day') {
            durationText = '1 Day';
        } else {
            durationText = (exp.duration_days || '?') + ' Days';
        }

        var regionName = exp.region ? exp.region.name : '';
        var expType = exp.type ? exp.type.charAt(0).toUpperCase() + exp.type.slice(1) : '';
        var difficulty = exp.difficulty_level ? exp.difficulty_level.charAt(0).toUpperCase() + exp.difficulty_level.slice(1) : '';

        var h = '<div class="exp-card" data-exp-id="' + exp.id + '">';
        h += '<div class="exp-card-image">';
        h += imgHtml;
        if (expType) h += '<span class="exp-card-badge">' + expType + '</span>';
        h += '<button class="exp-card-heart ' + (isPreferred ? 'preferred' : '') + '" data-exp-id="' + exp.id + '" title="Add to favorites"><i class="bi bi-heart-fill"></i></button>';
        h += '</div>';
        h += '<div class="exp-card-body">';
        h += '<h3 class="exp-card-title"><a href="/experience/' + exp.slug + '" target="_blank">' + exp.name + '</a></h3>';
        h += '<p class="exp-card-desc">' + (exp.short_description ? exp.short_description.substring(0, 120) + (exp.short_description.length > 120 ? '...' : '') : '') + '</p>';
        h += '<div class="exp-card-meta">';
        if (regionName) h += '<span class="exp-meta-item"><i class="bi bi-geo-alt"></i> ' + regionName + '</span>';
        h += '<span class="exp-meta-item"><i class="bi bi-clock"></i> ' + durationText + '</span>';
        if (difficulty) h += '<span class="exp-difficulty-badge">' + difficulty + '</span>';
        h += '</div>';
        if (exp.base_cost_per_person > 0) {
            h += '<div class="exp-card-price">';
            h += '<span class="exp-price-amount">' + fmtCurrency(exp.base_cost_per_person, exp.price_currency || 'INR') + '</span>';
            h += '<span class="exp-price-label">/ person</span>';
            h += '</div>';
        }
        h += '</div>';
        h += '<div class="exp-card-footer">';
        h += '<a href="/experience/' + exp.slug + '" target="_blank" class="exp-btn exp-btn-outline"><i class="bi bi-eye"></i> Details</a>';
        h += '<button class="exp-btn exp-btn-primary btn-add-exp" data-exp-id="' + exp.id + '" data-exp-name="' + exp.name + '"><i class="bi bi-plus-lg"></i> Add</button>';
        h += '</div>';
        h += '</div>';
        return h;
    }

    function loadExperiences(append) {
        if (discoverLoading) return;
        discoverLoading = true;

        var params = {
            get_experiences_for_discover: 1,
            page: discoverPage
        };
        if (currentRegionId) params.region_id = currentRegionId;
        if (jQuery('#filterType').val()) params.type = jQuery('#filterType').val();
        if (jQuery('#filterDifficulty').val()) params.difficulty = jQuery('#filterDifficulty').val();
        if (jQuery('#filterMonth').val()) params.month = jQuery('#filterMonth').val();

        if (!append) {
            allDiscoverExps = [];
            jQuery('#experienceGrid').html('<div class="loading-state" style="grid-column: 1 / -1;"><div class="loading-spinner"></div><p class="loading-text">Loading experiences...</p></div>');
        }

        ajaxPost(params, function(resp) {
            discoverLoading = false;
            var items = resp.data || [];
            allDiscoverExps = allDiscoverExps.concat(items);
            var html = '';

            if (items.length === 0 && !append) {
                html = '<div class="journey-empty-state" style="grid-column: 1 / -1;">';
                html += '<div class="journey-empty-icon"><i class="bi bi-search"></i></div>';
                html += '<h3 class="journey-empty-title">No experiences found</h3>';
                html += '<p class="journey-empty-desc">Try adjusting your filters or selecting a different region.</p>';
                html += '</div>';
                jQuery('#loadMoreWrap').addClass('d-none');
            } else {
                items.forEach(function(exp) {
                    html += buildExpCardHtml(exp);
                });

                if (resp.has_more) {
                    discoverHasMore = true;
                    jQuery('#loadMoreWrap').removeClass('d-none');
                } else {
                    discoverHasMore = false;
                    jQuery('#loadMoreWrap').addClass('d-none');
                }
            }

            if (append) {
                jQuery('#experienceGrid').append(html);
            } else {
                jQuery('#experienceGrid').html(html);
            }

            // Update map markers
            updateMapMarkers(allDiscoverExps);
        }, function() {
            discoverLoading = false;
        });
    }

    // Initial load
    loadExperiences(false);

    // Heart/Prefer button
    jQuery(document).on('click', '.exp-card-heart', function(e) {
        e.stopPropagation();
        var btn = jQuery(this);
        var expId = btn.data('exp-id');
        ensureTrip(function(tId) {
            ajaxPost({ prefer_experience: 1, trip_id: tId, experience_id: expId }, function(resp) {
                btn.toggleClass('preferred');
                if (resp.message) showAlert(resp.message, 'success');
            });
        });
    });

    // Add to Journey button
    jQuery(document).on('click', '.btn-add-exp', function(e) {
        e.stopPropagation();
        var btn = jQuery(this);
        var expId = btn.data('exp-id');
        var expName = btn.data('exp-name');

        ensureTrip(function(tId) {
            ajaxPost({ add_experience_to_trip: 1, trip_id: tId, experience_id: expId }, function(resp) {
                if (selectedExpIds.indexOf(expId) === -1) {
                    selectedExpIds.push(expId);
                }
                updateJourneyBadge();
                showAlert('"' + expName + '" added to your journey!', 'success');
                if (resp.trip_id && !tripId) {
                    tripId = resp.trip_id;
                }
                jQuery('#noTripMessage').addClass('d-none');
                jQuery('#journeyPanels').removeClass('d-none');
                loadSelectedExperiences();
                autoGenerateItinerary();
            });
        });
    });

    // ===================================
    // YOUR JOURNEY TAB
    // ===================================

    // Load journey data on tab show
    jQuery('button[data-bs-target="#pane-journey"]').on('shown.bs.tab', function() {
        if (tripId) {
            loadJourneyData();
        }
    });

    function loadJourneyData() {
        if (!tripId) return;
        loadSelectedExperiences();
        loadTimeline();
        loadPricing();
    }

    function loadSelectedExperiences() {
        ajaxPost({ get_trip_selected_experiences: 1, trip_id: tripId }, function(resp) {
            var items = resp.experiences || [];
            selectedExpIds = items.map(function(e) { return e.experience_id; });
            updateJourneyBadge();

            if (items.length === 0) {
                jQuery('#selectedExpList').html('<p class="text-center" style="font-size: var(--text-sm); color: var(--color-text-muted); padding: var(--space-4);">No experiences added yet</p>');
                return;
            }
            var html = '';
            items.forEach(function(item, idx) {
                var exp = item.experience;
                var name = exp ? exp.name : 'Experience #' + item.experience_id;
                var thumb = exp && exp.card_image ? exp.card_image : '';
                html += '<div class="journey-exp-item" data-exp-id="' + item.experience_id + '" draggable="true">';
                html += '<i class="bi bi-grip-vertical grip-icon"></i>';
                if (thumb) html += '<img src="' + thumb + '" alt="" class="exp-thumb">';
                html += '<div class="exp-info">';
                html += '<span class="exp-name">' + name + '</span>';
                html += '<span class="exp-id">Experience ID : ' + item.experience_id + '</span>';
                html += '</div>';
                html += '<button class="btn-remove btn-remove-exp" data-exp-id="' + item.experience_id + '" title="Remove"><i class="bi bi-x"></i></button>';
                html += '</div>';
            });
            jQuery('#selectedExpList').html(html);
            initDragReorder();
        });
    }

    // Convert newline-separated text into bullet-point HTML list
    function toBulletHtml(text) {
        if (!text) return '';
        var lines = text.split('\n').filter(function(l) { return l.trim().length > 0; });
        if (lines.length === 0) return '';
        var html = '<ul>';
        lines.forEach(function(line) {
            html += '<li>' + line.replace(/^[\u2022\-\*]\s*/, '') + '</li>';
        });
        html += '</ul>';
        return html;
    }

    function loadTimeline() {
        ajaxPost({ get_trip_timeline: 1, trip_id: tripId }, function(resp) {
            var days = resp.days || [];
            if (days.length === 0) {
                jQuery('#timelineContainer').html('<p class="text-center" style="font-size: var(--text-sm); color: var(--color-text-muted); padding: var(--space-6);" id="emptyTimeline">Days will appear here when experiences are added</p>');
                return;
            }

            var html = '';

            // Trip ID header (only for logged-in users with real trip)
            if (tripId && tripId !== 'guest') {
                html += '<div class="trip-id-display">Trip ID : ' + tripId + '</div>';
            }

            days.forEach(function(day) {
                html += '<div class="timeline-day" data-day-id="' + day.id + '">';
                html += '<div class="timeline-day-header">';
                html += '<div class="timeline-day-info">';
                html += '<span class="timeline-day-number">Day ' + day.day_number + '</span>';
                if (day.date) html += '<span class="timeline-day-date">' + day.date + '</span>';
                if (day.title) html += '<span class="timeline-day-title">' + day.title + '</span>';
                html += '</div>';
                html += '<div style="display: flex; align-items: center; gap: var(--space-2);">';
                if (day.is_locked) html += '<i class="bi bi-lock" style="color: var(--heco-warning);" title="Locked"></i>';
                html += '<button class="btn-remove btn-remove-day" data-day-id="' + day.id + '" title="Remove Day"><i class="bi bi-trash"></i></button>';
                html += '</div></div>';

                // Day description as bullet points
                if (day.description) {
                    html += '<div class="timeline-day-desc">' + toBulletHtml(day.description) + '</div>';
                }

                // Day experiences with full detail
                if (day.experiences && day.experiences.length) {
                    day.experiences.forEach(function(de) {
                        var exp = de.experience;
                        var eName = exp ? exp.name : 'Experience';
                        var typeIconMap = {
                            trek: 'bi-signpost-split', cultural: 'bi-bank', adventure: 'bi-lightning',
                            wildlife: 'bi-binoculars', wellness: 'bi-heart-pulse', culinary: 'bi-cup-hot',
                            village: 'bi-houses', other: 'bi-star-fill'
                        };
                        var expIcon = (exp && exp.type) ? (typeIconMap[exp.type] || 'bi-star-fill') : 'bi-star-fill';

                        html += '<div class="timeline-exp-item">';
                        html += '<i class="bi ' + expIcon + '"></i>';
                        html += '<div class="timeline-exp-details">';
                        html += '<span class="timeline-exp-name">' + eName + '</span>';
                        if (de.experience_id) html += '<span class="timeline-exp-id">Experience ID : ' + de.experience_id + '</span>';
                        if (de.notes) html += '<div class="timeline-exp-notes">' + toBulletHtml(de.notes) + '</div>';
                        html += '</div>';
                        html += '<div class="timeline-exp-meta">';
                        if (de.start_time) html += '<span class="timeline-exp-time">' + de.start_time + (de.end_time ? ' - ' + de.end_time : '') + '</span>';
                        if (de.cost_per_person && parseFloat(de.cost_per_person) > 0) {
                            html += '<span class="timeline-exp-cost">' + fmtCurrency(de.cost_per_person) + '/person</span>';
                        }
                        html += '</div>';
                        html += '</div>';
                    });
                }

                // Day services
                if (day.services && day.services.length) {
                    html += '<div class="service-icons">';
                    day.services.forEach(function(svc) {
                        var iconMap = { accommodation: 'bi-house-door', transport: 'bi-car-front', guide: 'bi-person-badge', activity: 'bi-lightning', meal: 'bi-cup-hot', other: 'bi-three-dots' };
                        var icon = iconMap[svc.service_type] || iconMap.other;
                        var colorClass = svc.is_included ? 'included' : 'not-included';
                        html += '<span class="service-icon-item" title="' + (svc.service_type ? svc.service_type.charAt(0).toUpperCase() + svc.service_type.slice(1) : '') + (svc.is_included ? ' (included)' : ' (not included)') + '">';
                        html += '<i class="bi ' + icon + ' ' + colorClass + '"></i>';
                        if (svc.description) html += ' ' + svc.description;
                        if (svc.cost > 0) html += ' <span style="color: var(--heco-success); font-weight: var(--font-medium);">' + fmtCurrency(svc.cost) + '</span>';
                        html += '</span>';
                    });
                    html += '</div>';
                }

                if ((!day.experiences || !day.experiences.length) && (!day.services || !day.services.length)) {
                    html += '<p style="font-size: var(--text-sm); color: var(--color-text-muted); text-align: center; margin: 0; padding: var(--space-2);">No activities planned yet</p>';
                }

                html += '</div>';
            });
            jQuery('#timelineContainer').html(html);
        });
    }

    function loadPricing() {
        ajaxPost({ get_trip_pricing: 1, trip_id: tripId }, function(resp) {
            var p = resp.pricing || resp;
            jQuery('#prTransport').text(fmtCurrency(p.transport_cost));
            jQuery('#prAccommodation').text(fmtCurrency(p.accommodation_cost));
            jQuery('#prGuide').text(fmtCurrency(p.guide_cost));
            jQuery('#prActivities').text(fmtCurrency(p.activity_cost));
            jQuery('#prOther').text(fmtCurrency(p.other_cost));
            jQuery('#prSubtotal').text(fmtCurrency(p.subtotal));
            jQuery('#prRP').text(fmtCurrency(p.margin_rp_amount));
            jQuery('#prGST').text(fmtCurrency(p.gst_amount));
            jQuery('#prFinal').text(fmtCurrency(p.final_price));
        });
    }

    // Remove experience
    jQuery(document).on('click', '.btn-remove-exp', function() {
        var expId = jQuery(this).data('exp-id');
        ajaxPost({ remove_experience_from_trip: 1, trip_id: tripId, experience_id: expId }, function() {
            selectedExpIds = selectedExpIds.filter(function(id) { return id !== expId; });
            updateJourneyBadge();
            loadSelectedExperiences();
            showAlert('Experience removed.', 'success');
            if (selectedExpIds.length > 0) {
                autoGenerateItinerary();
            } else {
                jQuery('#timelineContainer').html('<p class="text-center" style="font-size: var(--text-sm); color: var(--color-text-muted); padding: var(--space-6);">Days will appear here when experiences are added</p>');
                loadPricing();
            }
        });
    });

    // Drag and reorder
    function initDragReorder() {
        var listEl = document.getElementById('selectedExpList');
        if (!listEl) return;
        var dragItem = null;

        jQuery(listEl).off('dragstart dragover dragend drop');

        jQuery(listEl).on('dragstart', '.journey-exp-item', function(e) {
            dragItem = this;
            jQuery(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        });

        jQuery(listEl).on('dragover', '.journey-exp-item', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            var rect = this.getBoundingClientRect();
            var midY = rect.top + rect.height / 2;
            if (e.originalEvent.clientY < midY) {
                jQuery(this).before(dragItem);
            } else {
                jQuery(this).after(dragItem);
            }
        });

        jQuery(listEl).on('dragend', '.journey-exp-item', function() {
            jQuery(this).removeClass('dragging');
            var order = [];
            jQuery('#selectedExpList .journey-exp-item').each(function() {
                order.push(jQuery(this).data('exp-id'));
            });
            ajaxPost({ reorder_experiences: 1, trip_id: tripId, order: order }, function() {
                loadTimeline();
            });
        });
    }

    // Add Day
    jQuery('#btnAddDay').on('click', function() {
        if (!tripId) return;
        ajaxPost({ add_day_to_trip: 1, trip_id: tripId }, function(resp) {
            showAlert('Day added.', 'success');
            loadTimeline();
        });
    });

    // Remove Day
    jQuery(document).on('click', '.btn-remove-day', function() {
        var dayId = jQuery(this).data('day-id');
        if (!confirm('Remove this day from the trip?')) return;
        ajaxPost({ remove_day_from_trip: 1, trip_id: tripId, day_id: dayId }, function() {
            showAlert('Day removed.', 'success');
            loadTimeline();
            loadPricing();
        });
    });

    // Trip name save (debounced)
    jQuery('#tripName').on('input', function() {
        var val = jQuery(this).val();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            if (tripId) {
                ajaxPost({ save_trip_name: 1, trip_id: tripId, name: val });
            }
        }, 800);
    });

    // Group details
    jQuery('.group-input').on('change', function() {
        if (!tripId) return;
        ajaxPost({
            update_group_details: 1,
            trip_id: tripId,
            adults: parseInt(jQuery('#grpAdults').val()) || 1,
            children: parseInt(jQuery('#grpChildren').val()) || 0,
            infants: parseInt(jQuery('#grpInfants').val()) || 0
        }, function() {
            loadPricing();
        });
    });

    // Preferences
    jQuery('.pref-input').on('change', function() {
        if (!tripId) return;
        ajaxPost({
            update_travel_preferences: 1,
            trip_id: tripId,
            accommodation_comfort: jQuery('#prefAccommodation').val(),
            vehicle_comfort: jQuery('#prefVehicle').val(),
            guide_preference: jQuery('#prefGuide').val(),
            travel_pace: jQuery('#prefPace').val(),
            budget_sensitivity: jQuery('#prefBudget').val()
        }, function() {
            loadPricing();
        });
    });

    // Request Support
    jQuery('#btnRequestSupport').on('click', function() {
        var msg = jQuery('#supportMessage').val().trim();
        if (!msg) {
            showAlert('Please enter a message.', 'warning');
            return;
        }
        if (!tripId) {
            showAlert('Please add an experience to start a trip first.', 'warning');
            return;
        }
        ajaxPost({ request_support: 1, trip_id: tripId, message: msg }, function() {
            showAlert('Support request sent! Our team will get back to you soon.', 'success');
            jQuery('#supportMessage').val('');
        });
    });

    // ===================================
    // IMPACT TAB
    // ===================================

    jQuery('button[data-bs-target="#pane-impact"]').on('shown.bs.tab', function() {
        if (tripId) loadImpactData();
    });

    function loadImpactData() {
        if (!tripId) return;
        ajaxPost({ get_trip_impact: 1, trip_id: tripId }, function(resp) {
            var impacts = resp.impacts || [];
            var totalContribution = 0;
            var regionSet = {};
            var projectCount = 0;

            impacts.forEach(function(imp) {
                totalContribution += parseFloat(imp.contribution || 0);
                if (imp.region_name) regionSet[imp.region_name] = true;
                projectCount++;
            });

            jQuery('#impactTotalRP').text(fmtCurrency(totalContribution));
            jQuery('#impactRegionCount').text(Object.keys(regionSet).length);
            jQuery('#impactProjectCount').text(projectCount);

            if (impacts.length === 0) {
                var emptyHtml = '<div class="journey-empty-state" style="grid-column: 1 / -1;">';
                emptyHtml += '<div class="journey-empty-icon"><i class="bi bi-tree"></i></div>';
                emptyHtml += '<h3 class="journey-empty-title">No impact data yet</h3>';
                emptyHtml += '<p class="journey-empty-desc">Complete your journey planning to see your contribution to regenerative projects.</p>';
                emptyHtml += '</div>';
                jQuery('#impactCards').html(emptyHtml);
                return;
            }

            var html = '';
            impacts.forEach(function(imp) {
                html += '<div class="impact-card">';
                html += '<div class="impact-card-region"><i class="bi bi-geo-alt-fill"></i> ' + (imp.region_name || 'Unknown Region') + '</div>';
                html += '<div class="impact-card-project">' + (imp.project_name || 'Regenerative Project') + '</div>';
                html += '<span class="impact-card-type">' + (imp.action_type || 'Conservation') + '</span>';
                html += '<div class="impact-card-stats">';
                html += '<div class="impact-card-stat-row"><span>Your Contribution</span><span class="value">' + fmtCurrency(imp.contribution) + '</span></div>';
                if (imp.impact_value && imp.impact_units) {
                    html += '<div class="impact-card-stat-row"><span>Impact</span><span class="value">' + imp.impact_value + ' ' + imp.impact_units + '</span></div>';
                }
                html += '</div></div>';
            });
            jQuery('#impactCards').html(html);
        });
    }

    // ===================================
    // INLINE AI CHAT
    // ===================================

    var chatHistoryLoaded = false;

    function appendChatMsg(role, content) {
        var escaped = jQuery('<div/>').text(content).html().replace(/\n/g, '<br>');
        if (role === 'assistant') {
            escaped = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        }
        var el = jQuery('<div class="chat-msg ' + role + '">' + escaped + '</div>');
        jQuery('#inlineChatMessages').append(el);
    }

    function scrollChat() {
        var container = document.getElementById('inlineChatMessages');
        if (container) container.scrollTop = container.scrollHeight;
    }

    function loadChatHistory() {
        if (chatHistoryLoaded) return;
        ajaxPost({ get_chat_history: 1, trip_id: tripId || '' }, function(resp) {
            chatHistoryLoaded = true;
            var messages = resp.messages || [];
            if (messages.length === 0) return;
            jQuery('#inlineChatMessages').find('.chat-msg.assistant').first().remove();
            messages.forEach(function(msg) {
                appendChatMsg(msg.role, msg.content);
            });
            scrollChat();
        });
    }

    function sendChatMessage() {
        var msg = jQuery('#inlineChatInput').val().trim();
        if (!msg) return;

        jQuery('#inlineChatInput').val('');
        appendChatMsg('user', msg);
        scrollChat();

        var typingEl = jQuery('<div class="chat-msg assistant"><i class="bi bi-three-dots"></i> Thinking...</div>');
        jQuery('#inlineChatMessages').append(typingEl);
        scrollChat();

        var params = { chat_with_ai: 1, message: msg };
        if (tripId) params.trip_id = tripId;

        ajaxPost(params, function(resp) {
            typingEl.remove();
            appendChatMsg('assistant', resp.response || 'I could not generate a response. Please try again.');

            if (resp.trip_id && !tripId) {
                tripId = resp.trip_id;
                jQuery('#noTripMessage').addClass('d-none');
                jQuery('#journeyPanels').removeClass('d-none');
                jQuery('#noImpactMessage').addClass('d-none');
                jQuery('#impactData').removeClass('d-none');
            }

            if (resp.trip_updated) {
                loadJourneyData();
                selectedExpIds = resp.selected_experience_ids || selectedExpIds;
                updateJourneyBadge();
            }

            // Highlight AI-recommended experiences
            if (resp.recommended_experience_ids && resp.recommended_experience_ids.length > 0) {
                highlightRecommendedExperiences(resp.recommended_experience_ids);
            }

            scrollChat();
        }, function() {
            typingEl.remove();
            appendChatMsg('assistant', 'Sorry, something went wrong. Please try again.');
            scrollChat();
        });
    }

    function highlightRecommendedExperiences(expIds) {
        jQuery('#experienceGrid .exp-card').removeClass('ai-recommended');
        var foundAny = false;
        expIds.forEach(function(id) {
            var card = jQuery('#experienceGrid .exp-card[data-exp-id="' + id + '"]');
            if (card.length) {
                card.addClass('ai-recommended');
                foundAny = true;
            }
        });
        if (foundAny) {
            jQuery('html, body').animate({
                scrollTop: jQuery('#experienceGrid .exp-card.ai-recommended').first().offset().top - 120
            }, 500);
        }
    }

    jQuery('#inlineChatInput').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChatMessage();
        }
    });

    jQuery('#inlineChatSend').on('click', function() {
        sendChatMessage();
    });

    // Load chat history on page load
    loadChatHistory();

    // ===================================
    // INIT: Load journey data if trip exists
    // ===================================
    if (tripId) {
        loadJourneyData();
    }

    // Re-render when currency changes
    jQuery(document).on('currencyChanged', function() {
        loadExperiences(false);
        if (typeof allDiscoverExps !== 'undefined' && allDiscoverExps.length) {
            updateMapMarkers(allDiscoverExps);
        }
    });
});
</script>
@endsection
