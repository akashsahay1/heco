@extends('portal.layout')
@section('title', 'Explore Experiences - HECO Portal')

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<style>
    /* Custom dropdown styles */
    .custom-select-wrap {
        position: relative;
    }
    .custom-select-wrap select {
        display: none;
    }
    .custom-select-trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 16px;
        font-size: 1rem;
        border: 1px solid var(--color-border, #dee2e6);
        border-radius: 6px;
        background: #fff;
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-height: 42px;
    }
    .custom-select-trigger:hover {
        border-color: var(--heco-primary-500, #22c55e);
    }
    .custom-select-trigger .caret {
        margin-left: 8px;
        font-size: 0.7rem;
        color: var(--heco-neutral-400, #999);
        flex-shrink: 0;
    }
    .custom-select-options {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1080;
        background: #fff;
        border: 1px solid var(--color-border, #dee2e6);
        border-radius: 6px;
        margin-top: 2px;
        max-height: 220px;
        overflow-y: auto;
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        scrollbar-width: thin;
    }
    .custom-select-wrap.open .custom-select-options {
        display: block;
    }
    .custom-select-option {
        padding: 10px 16px;
        font-size: 1rem;
        cursor: pointer;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .custom-select-option:hover {
        background: var(--heco-neutral-50, #f8f9fa);
        color: var(--heco-primary-700, #15803d);
    }
    .custom-select-option.selected {
        background: var(--heco-primary-50, #f0fdf4);
        color: var(--heco-primary-700, #15803d);
        font-weight: 600;
    }
    .experience-grid-split {
        max-height: calc(100vh - 260px);
        overflow-y: auto;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 18px !important;
        padding: 4px;
    }
    .experience-grid-split .exp-card {
        margin-bottom: 0;
        position: relative;
        border-radius: 12px;
    }
    .experience-grid-split .exp-card-image {
        height: 170px;
    }
    .experience-grid-split .exp-card-body {
        padding: 14px 16px 14px;
    }
    .experience-grid-split .exp-card-title {
        font-size: 0.95rem;
        font-weight: 700;
        margin-bottom: 4px;
        line-height: 1.35;
    }
    /* Card host line */
    .exp-card-host {
        font-size: 0.82rem;
        color: var(--color-text-muted, #6c757d);
        margin: 0 0 3px;
        line-height: 1.35;
    }
    /* Card duration line */
    .exp-card-duration {
        font-size: 0.82rem;
        color: var(--color-text-muted, #6c757d);
        margin: 0 0 8px;
        line-height: 1.35;
    }
    .exp-card-duration i {
        font-size: 0.72rem;
        color: var(--heco-primary-500, #22c55e);
        margin-right: 2px;
    }
    /* Inline heart/fav button in bottom row */
    .exp-card-fav {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        margin-left: auto;
        flex-shrink: 0;
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
    }
    .exp-card-fav i {
        font-size: 1.5rem;
        color: #6b7280;
        transition: color 0.2s;
    }
    .exp-card-fav:hover i {
        color: #ef4444;
    }
    .exp-card-fav.preferred i {
        color: #ef4444;
    }
    /* Price + stars bottom row */
    .exp-card-bottom {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: nowrap;
        padding: 10px 0;
        margin-top: auto;
        border-top: 1px solid var(--color-border, #e5e7eb);
    }
    .exp-card-price {
        font-size: 0.88rem;
        font-weight: 700;
        color: var(--color-text, #1a1a1a);
        position: relative;
        top: -2px;
    }
    .exp-card-stars {
        display: inline-flex;
        align-items: center;
        gap: 1px;
        font-size: 1rem;
    }
    .exp-card-stars .bi-star-fill {
        color: #f5a623;
    }
    .exp-card-stars .bi-star {
        color: #ccc;
    }
    /* Small add/remove button on card */
    .exp-card-add-btn {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 1.5px solid var(--heco-primary-500, #22c55e);
        background: #fff;
        color: var(--heco-primary-600, #16a34a);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        padding: 0;
    }
    .exp-card-add-btn:hover {
        background: var(--heco-primary-500, #22c55e);
        color: #fff;
    }
    .exp-card-add-btn.added {
        background: var(--heco-primary-500, #22c55e);
        color: #fff;
        border-color: var(--heco-primary-500, #22c55e);
    }
    /* Collapsible Chat Panel */
    .chat-collapse {
        margin-bottom: var(--space-4, 16px);
    }
    .chat-collapse-outer {
        height: auto;
        margin-bottom: 25px;
        position: relative;
        z-index: 40;
    }
    .chat-collapse-panel {
        background: var(--color-bg-white, #fff);
        border: 1px solid var(--color-border, #e5e7eb);
        border-radius: 12px;
        overflow: hidden;
    }
    .chat-collapse-panel.expanded {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    }
    .chat-collapse-messages {
        max-height: 85px;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        transition: max-height 0.3s ease;
    }
    .chat-collapse-panel.expanded .chat-collapse-messages {
        max-height: 350px;
    }
    .chat-collapse-input-area {
        display: flex;
        align-items: center;
        padding: 10px 16px;
        border-top: 1px solid var(--color-border, #e5e7eb);
        gap: 8px;
    }
    .chat-collapse-input-area .inline-chat-input {
        flex: 1;
        border: 1px solid var(--color-border, #e5e7eb);
        border-radius: 2rem;
        padding: 8px 16px;
        font-size: 0.85rem;
        outline: none;
    }
    .chat-collapse-input-area .inline-chat-input:focus {
        border-color: var(--heco-primary-500, #22c55e);
        box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.15);
    }
    .chat-collapse-input-area .inline-chat-send {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: var(--heco-primary-500, #22c55e);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
    }
    .chat-collapse-input-area .inline-chat-send:hover {
        background: var(--heco-primary-600, #16a34a);
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
    .map-exp-popup .leaflet-popup-content-wrapper {
        padding: 0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    .map-exp-popup .leaflet-popup-content {
        margin: 0;
        width: 100% !important;
    }
    .map-popup-card {
        width: 320px;
    }
    .map-popup-card > img {
        width: 100%;
        height: 130px;
        object-fit: cover;
        border-radius: 12px 12px 0 0;
    }
    .map-popup-body {
        padding: 10px 12px 12px;
    }
    .map-popup-title {
        font-weight: 700;
        font-size: 14px;
        margin-bottom: 4px;
        color: var(--color-text, #1a1a1a);
    }
    .map-popup-meta {
        color: #6c757d;
        font-size: 11px;
        margin-bottom: 6px;
    }
    .map-popup-meta i {
        color: var(--heco-primary-500, #22c55e);
    }
    .map-popup-desc {
        font-size: 12px;
        color: #555;
        line-height: 1.4;
        margin-bottom: 8px;
    }
    .map-popup-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .map-popup-price {
        color: var(--heco-primary-700, #15803d);
        font-weight: 700;
        font-size: 13px;
    }
    .map-popup-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        font-size: 11px;
        font-weight: 600;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        background: var(--heco-primary-500, #22c55e);
        color: #fff;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .map-popup-btn:hover {
        background: var(--heco-primary-600, #16a34a);
    }
    .map-popup-btn.added {
        background: var(--heco-neutral-200, #e5e7eb);
        color: var(--heco-neutral-500, #6b7280);
        cursor: default;
    }
    #mapViewPanel #discoverMap {
        height: 100%;
        min-height: 400px;
        border-radius: 12px;
    }
    @media (max-width: 991px) {
        .experience-grid-split {
            max-height: none;
            overflow-y: visible;
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }
    @media (max-width: 576px) {
        .experience-grid-split {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endsection

@section('content')
@php
if ($trip) {
    $tripId = $trip->id;
    $selectedExpIds = $trip->selectedExperiences->pluck('experience_id')->toArray();
    $preferredExpIds = $trip->selectedExperiences->where('is_preferred', true)->pluck('experience_id')->toArray();
} elseif (!empty($guestTripData['experience_ids'] ?? [])) {
    $tripId = 'guest';
    $selectedExpIds = $guestTripData['experience_ids'];
    $preferredExpIds = [];
} else {
    $tripId = null;
    $selectedExpIds = [];
    $preferredExpIds = [];
}
$hasTrip = $trip || ($tripId === 'guest');
// Preference values (from DB trip or guest session)
$pAccom = ($trip ? $trip->accommodation_comfort : null) ?: ($guestTripData['accommodation_comfort'] ?? null) ?: 'Cat C - Standard';
$pVehicle = ($trip ? $trip->vehicle_comfort : null) ?: ($guestTripData['vehicle_comfort'] ?? null) ?: 'SUV (Innova/Crysta)';
$pGuide = ($trip ? $trip->guide_preference : null) ?: ($guestTripData['guide_preference'] ?? null) ?: 'English-speaking';
$pPace = ($trip ? $trip->travel_pace : null) ?: ($guestTripData['travel_pace'] ?? null) ?: 'Moderate';
$pBudget = ($trip ? $trip->budget_sensitivity : null) ?: ($guestTripData['budget_sensitivity'] ?? null) ?: 'Mid-range';
@endphp

<div class="heco-page">
    {{-- Hero Section --}}
    {{-- Hero section hidden
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
    --}}

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

                {{-- Side-by-side layout: Cards left + Map right --}}
                <div class="discover-layout">
                    {{-- LEFT: Filter button + Experience Cards --}}
                    <div class="discover-cards-panel">
                        {{-- AI Chat Panel (always visible) --}}
                        <div class="chat-collapse-outer">
                        <div class="chat-collapse-panel">
                            <div class="chatbot-popup-header" style="cursor:pointer;" id="chatCollapseToggle">
                                <div class="chatbot-popup-title">
                                    <i class="bi bi-robot"></i> AI Assistant
                                </div>
                                <button class="chatbot-popup-close" id="chatCollapseBtn">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                            <div class="chat-collapse-messages" id="collapseChatMessages">
                                <div class="chat-msg assistant">
                                    @if(auth()->check())
                                        Hey {{ auth()->user()->full_name ?? 'there' }}! I'm the HECO AI Assistant &mdash; welcome back! What kind of experience are you looking for?
                                    @else
                                        Hey there! I'm the HECO AI Assistant &mdash; I'd love to help you plan an amazing Himalayan adventure. What's your name?
                                    @endif
                                </div>
                            </div>
                            <div class="chat-collapse-input-area">
                                <input type="text" class="inline-chat-input" id="collapseChatInput" placeholder="Ask anything about experiences..." autocomplete="off">
                                <button class="inline-chat-send" id="collapseChatSend">
                                    <i class="bi bi-send-fill"></i>
                                </button>
                            </div>
                        </div>
                        </div>

                        {{-- Grid View (always visible) --}}
                        <div id="gridViewPanel">
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
                    </div>

                    {{-- RIGHT: Filters + Map --}}
                    <div class="discover-map-panel">
                        <div class="filter-bar">
                            <div class="row g-2 align-items-end">
                                <div class="col-lg-4 col-md-4 col-6">
                                    <label class="form-label">Continent</label>
                                    <select class="form-select form-select-sm" id="filterContinent">
                                        <option value="">All Continents</option>
                                    </select>
                                </div>
                                <div class="col-lg-4 col-md-4 col-6">
                                    <label class="form-label">Country</label>
                                    <select class="form-select form-select-sm" id="filterCountry">
                                        <option value="">All Countries</option>
                                    </select>
                                </div>
                                <div class="col-lg-4 col-md-4 col-6">
                                    <label class="form-label">Region</label>
                                    <select class="form-select form-select-sm" id="filterRegion">
                                        @foreach($regions as $region)
                                            <option value="{{ $region->id }}" @if($region->name === 'Tirthan Valley') selected @endif>{{ $region->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-4 col-md-4 col-6">
                                    <label class="form-label">Experience Type</label>
                                    <select class="form-select form-select-sm" id="filterType">
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
                                <div class="col-lg-4 col-md-4 col-6">
                                    <label class="form-label">Difficulty</label>
                                    <select class="form-select form-select-sm" id="filterDifficulty">
                                        <option value="">All Levels</option>
                                        <option value="easy">Easy</option>
                                        <option value="moderate">Moderate</option>
                                        <option value="challenging">Challenging</option>
                                        <option value="difficult">Difficult</option>
                                        <option value="expert">Expert</option>
                                    </select>
                                </div>
                                <div class="col-lg-4 col-md-4 col-6">
                                    <label class="form-label">Month</label>
                                    <select class="form-select form-select-sm" id="filterMonth">
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
                            </div>
                        </div>
                        <div id="mapViewPanel">
                            <div id="discoverMap"></div>
                        </div>
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
                    {{-- AI Chat inside Journey tab --}}
                    <div class="journey-chat-section">
                        <div class="journey-chat-header">
                            <i class="bi bi-robot"></i> AI Assistant
                        </div>
                        <div class="journey-chat-messages" id="journeyChatMessages">
                            <div class="chat-msg assistant">
                                @if(auth()->check())
                                    Hi {{ auth()->user()->full_name ?? 'there' }}! I can help you plan and modify your trip &mdash; change dates, add or remove days, update preferences, and more. Just ask!
                                @else
                                    Hi! I can help you modify your trip &mdash; change dates, add or remove days, update preferences, and more. Just ask!
                                @endif
                            </div>
                        </div>
                        <div class="journey-chat-input-area">
                            <input type="text" class="inline-chat-input" id="journeyChatInput"
                                placeholder="Ask AI to change dates, add days, update preferences..."
                                autocomplete="off">
                            <button class="inline-chat-send" id="journeyChatSend">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Shown if no trip --}}
                    <div id="noTripMessage" class="journey-empty-state d-none">
                        <div class="journey-empty-icon">
                            <i class="bi bi-map"></i>
                        </div>
                        <h3 class="journey-empty-title">Your journey starts here</h3>
                        <p class="journey-empty-desc">Add experiences from the Discover tab or chat with our AI assistant to begin planning your perfect adventure.</p>
                        <button class="exp-btn exp-btn-primary" style="display: inline-flex; padding: var(--space-4) var(--space-8);" onclick="jQuery('#tab-discover').click();">
                            <i class="bi bi-compass"></i> Explore Experiences
                        </button>
                    </div>

                    {{-- Journey panels — always visible --}}
                    <div id="journeyPanels" class="journey-panels">
                        {{-- Left: Selected Experiences List --}}
                        <div class="journey-panel">
                            <div class="journey-panel-header">
                                <h6 class="journey-panel-title"><i class="bi bi-list-check"></i> Experiences</h6>
                                <span class="tab-badge" id="expListCount">0</span>
                            </div>
                            <div class="journey-panel-body journey-sidebar" id="selectedExpList">
                            </div>
                        </div>

                        {{-- Center: Timeline --}}
                        <div class="journey-panel">
                            <div class="journey-panel-header">
                                <h6 class="journey-panel-title"><i class="bi bi-calendar3"></i> Trip Timeline</h6>
                                <div class="d-flex gap-2">
                                </div>
                            </div>
                            <div class="timeline-container" id="timelineContainer">
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

                            {{-- Trip Summary (auto-calculated) --}}
                            <div class="detail-card">
                                <div class="detail-card-header"><i class="bi bi-info-circle"></i> Trip Summary</div>
                                <div class="detail-card-body">
                                    <div class="pricing-row">
                                        <span><i class="bi bi-calendar-event"></i> Start Date</span>
                                        <span id="tripStartDateDisplay" class="editable-date" title="Click to change">
                                            {{ ($trip->start_date ?? null) ? $trip->start_date->format('d M Y') : ($guestTripData['start_date'] ?? '--') }}
                                        </span>
                                        <input type="date" id="tripStartDateInput" class="start-date-input d-none"
                                            value="{{ ($trip->start_date ?? null) ? $trip->start_date->format('Y-m-d') : ($guestTripData['start_date'] ?? '') }}">
                                    </div>
                                    <div class="pricing-row"><span><i class="bi bi-clock"></i> Duration</span><span id="tripDuration"></span></div>
                                    <div class="pricing-row"><span><i class="bi bi-geo-alt"></i> Regions</span><span id="tripRegions"></span></div>
                                    <div class="pricing-row"><span><i class="bi bi-card-list"></i> Experiences</span><span id="tripExpCount"></span></div>
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
                                            <option value="Cat E - Camping/Tents" {{ $pAccom == 'Cat E - Camping/Tents' ? 'selected' : '' }}>Cat E - Camping/Tents</option>
                                            <option value="Cat D - Basic/Homestay" {{ $pAccom == 'Cat D - Basic/Homestay' ? 'selected' : '' }}>Cat D - Basic/Homestay</option>
                                            <option value="Cat C - Standard" {{ $pAccom == 'Cat C - Standard' ? 'selected' : '' }}>Cat C - Standard</option>
                                            <option value="Cat B - Comfort" {{ $pAccom == 'Cat B - Comfort' ? 'selected' : '' }}>Cat B - Comfort</option>
                                            <option value="Cat A - Premium/Luxury" {{ $pAccom == 'Cat A - Premium/Luxury' ? 'selected' : '' }}>Cat A - Premium/Luxury</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Vehicle Comfort</label>
                                        <select class="form-select pref-input" id="prefVehicle">
                                            <option value="Local Transport" {{ $pVehicle == 'Local Transport' ? 'selected' : '' }}>Local Transport</option>
                                            <option value="SUV (Bolero/Scorpio)" {{ $pVehicle == 'SUV (Bolero/Scorpio)' ? 'selected' : '' }}>SUV (Bolero/Scorpio)</option>
                                            <option value="SUV (Innova/Crysta)" {{ $pVehicle == 'SUV (Innova/Crysta)' ? 'selected' : '' }}>SUV (Innova/Crysta)</option>
                                            <option value="Premium (Fortuner/Similar)" {{ $pVehicle == 'Premium (Fortuner/Similar)' ? 'selected' : '' }}>Premium (Fortuner/Similar)</option>
                                            <option value="Tempo Traveller" {{ $pVehicle == 'Tempo Traveller' ? 'selected' : '' }}>Tempo Traveller</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Guide Preference</label>
                                        <select class="form-select pref-input" id="prefGuide">
                                            <option value="No Guide" {{ $pGuide == 'No Guide' ? 'selected' : '' }}>No Guide</option>
                                            <option value="Local Guide" {{ $pGuide == 'Local Guide' ? 'selected' : '' }}>Local Guide</option>
                                            <option value="English-speaking" {{ $pGuide == 'English-speaking' ? 'selected' : '' }}>English-speaking</option>
                                            <option value="Certified/Expert" {{ $pGuide == 'Certified/Expert' ? 'selected' : '' }}>Certified/Expert</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Travel Pace</label>
                                        <select class="form-select pref-input" id="prefPace">
                                            <option value="Relaxed" {{ $pPace == 'Relaxed' ? 'selected' : '' }}>Relaxed</option>
                                            <option value="Moderate" {{ $pPace == 'Moderate' ? 'selected' : '' }}>Moderate</option>
                                            <option value="Active" {{ $pPace == 'Active' ? 'selected' : '' }}>Active</option>
                                            <option value="Intensive" {{ $pPace == 'Intensive' ? 'selected' : '' }}>Intensive</option>
                                        </select>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">Budget Sensitivity</label>
                                        <select class="form-select pref-input" id="prefBudget">
                                            <option value="Budget-friendly" {{ $pBudget == 'Budget-friendly' ? 'selected' : '' }}>Budget-friendly</option>
                                            <option value="Mid-range" {{ $pBudget == 'Mid-range' ? 'selected' : '' }}>Mid-range</option>
                                            <option value="Premium" {{ $pBudget == 'Premium' ? 'selected' : '' }}>Premium</option>
                                            <option value="No Limit" {{ $pBudget == 'No Limit' ? 'selected' : '' }}>No Limit</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Pricing Summary --}}
                            <div class="detail-card">
                                <div class="detail-card-header"><i class="bi bi-receipt"></i> Pricing Summary</div>
                                <div class="detail-card-body" id="pricingSummary">
                                    <div class="pricing-row"><span>Transport</span><span id="prTransport"></span></div>
                                    <div class="pricing-row"><span>Accommodation</span><span id="prAccommodation"></span></div>
                                    <div class="pricing-row"><span>Guide</span><span id="prGuide"></span></div>
                                    <div class="pricing-row"><span>Activities</span><span id="prActivities"></span></div>
                                    <div class="pricing-row"><span>Other</span><span id="prOther"></span></div>
                                    <div class="pricing-row"><span>Subtotal</span><span id="prSubtotal"></span></div>
                                    <div class="pricing-row"><span>RP Contribution</span><span id="prRP" class="rp-contribution"></span></div>
                                    <div class="pricing-row"><span>GST</span><span id="prGST"></span></div>
                                    <div class="pricing-row total"><span>Final Price</span><span id="prFinal"></span></div>
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


{{-- Floating AI Chatbot (hidden - replaced by collapsible panel in toolbar) --}}
{{--
<div class="chatbot-fab" id="chatbotFab" title="Chat with HECO AI">
    <i class="bi bi-robot"></i>
    <span class="chatbot-fab-pulse"></span>
</div>
<div class="chatbot-popup" id="chatbotPopup" style="display:none;">
    ...
</div>
--}}

{{-- Add Day Modal --}}
<div class="modal fade" id="addDayModal" tabindex="-1" aria-labelledby="addDayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 12px 40px rgba(22,163,74,0.15), 0 4px 16px rgba(0,0,0,0.08); overflow: hidden;">
            {{-- Green gradient header --}}
            <div style="background: linear-gradient(135deg, var(--heco-primary-600) 0%, var(--heco-primary-500) 100%); padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 34px; height: 34px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-calendar-plus" style="color: #fff; font-size: 16px;"></i>
                    </div>
                    <div>
                        <h6 id="addDayModalLabel" style="font-weight: 700; color: #fff; margin: 0; font-size: 15px;">Add a Day</h6>
                        <p id="addDayModalSubtitle" style="font-size: 12px; color: rgba(255,255,255,0.8); margin: 0;"></p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.65rem; opacity: 0.8;"></button>
            </div>
            {{-- Body --}}
            <div style="padding: 20px;">
                <label style="font-size: 13px; font-weight: 600; color: var(--heco-neutral-700); margin-bottom: 8px; display: block;">
                    What would you like to do on this day?
                </label>
                <textarea id="addDayDescription" class="form-control" rows="3"
                    placeholder="e.g. A rest day in Pokhara with lakeside walk and local food tasting..."
                    style="font-size: 13px; border-radius: 10px; resize: none; border: 1.5px solid var(--heco-primary-200); transition: border-color 0.2s, box-shadow 0.2s;"
                    onfocus="this.style.borderColor='var(--heco-primary-400)';this.style.boxShadow='0 0 0 3px rgba(34,197,94,0.1)'"
                    onblur="this.style.borderColor='var(--heco-primary-200)';this.style.boxShadow='none'"></textarea>
                <p style="font-size: 11px; color: var(--heco-neutral-400); margin: 8px 0 0; line-height: 1.4;">
                    <i class="bi bi-info-circle"></i> Describe your plans and AI will build the day for you
                </p>
            </div>
            {{-- Footer --}}
            <div style="padding: 0 20px 16px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" data-bs-dismiss="modal"
                    style="padding: 8px 18px; font-size: 13px; font-weight: 500; color: var(--heco-neutral-500); background: none; border: 1.5px solid var(--heco-neutral-200); border-radius: 10px; cursor: pointer; transition: all 0.2s;">
                    Cancel
                </button>
                <button type="button" id="addDayConfirmBtn"
                    style="padding: 8px 22px; font-size: 13px; font-weight: 600; color: #fff; background: linear-gradient(135deg, var(--heco-primary-500), var(--heco-primary-600)); border: none; border-radius: 10px; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.25);"
                    onmouseover="this.style.boxShadow='0 4px 14px rgba(22,163,74,0.35)';this.style.transform='translateY(-1px)'"
                    onmouseout="this.style.boxShadow='0 2px 8px rgba(22,163,74,0.25)';this.style.transform='none'">
                    <i class="bi bi-plus-lg" style="margin-right: 4px;"></i> Add Day
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Remove Experience Confirmation Modal --}}
<div class="modal fade" id="removeExpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 12px 40px rgba(220,38,38,0.15), 0 4px 16px rgba(0,0,0,0.08); overflow: hidden;">
            <div style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 34px; height: 34px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-x-circle" style="color: #fff; font-size: 16px;"></i>
                    </div>
                    <div>
                        <h6 style="font-weight: 700; color: #fff; margin: 0; font-size: 15px;">Remove Experience</h6>
                        <p id="removeExpModalSubtitle" style="font-size: 12px; color: rgba(255,255,255,0.8); margin: 0; max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.65rem; opacity: 0.8;"></button>
            </div>
            <div style="padding: 20px;">
                <p id="removeExpModalMsg" style="font-size: 13px; color: var(--heco-neutral-700); margin: 0; line-height: 1.5;">Are you sure you want to remove this experience?</p>
            </div>
            <div style="padding: 0 20px 16px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" data-bs-dismiss="modal"
                    style="padding: 8px 18px; font-size: 13px; font-weight: 500; color: var(--heco-neutral-500); background: none; border: 1.5px solid var(--heco-neutral-200); border-radius: 10px; cursor: pointer; transition: all 0.2s;">
                    Cancel
                </button>
                <button type="button" id="removeExpConfirmBtn"
                    style="padding: 8px 22px; font-size: 13px; font-weight: 600; color: #fff; background: linear-gradient(135deg, #dc2626, #ef4444); border: none; border-radius: 10px; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(220,38,38,0.25);"
                    onmouseover="this.style.boxShadow='0 4px 14px rgba(220,38,38,0.35)';this.style.transform='translateY(-1px)'"
                    onmouseout="this.style.boxShadow='0 2px 8px rgba(220,38,38,0.25)';this.style.transform='none'">
                    <i class="bi bi-trash3" style="margin-right: 4px;"></i> Remove
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Remove Day Confirmation Modal --}}
<div class="modal fade" id="removeDayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 12px 40px rgba(220,38,38,0.15), 0 4px 16px rgba(0,0,0,0.08); overflow: hidden;">
            <div style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 34px; height: 34px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-calendar-x" style="color: #fff; font-size: 16px;"></i>
                    </div>
                    <div>
                        <h6 style="font-weight: 700; color: #fff; margin: 0; font-size: 15px;">Remove Day</h6>
                        <p id="removeDayModalSubtitle" style="font-size: 12px; color: rgba(255,255,255,0.8); margin: 0;"></p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.65rem; opacity: 0.8;"></button>
            </div>
            <div style="padding: 20px;">
                <p id="removeDayModalMsg" style="font-size: 13px; color: var(--heco-neutral-700); margin: 0; line-height: 1.5;">Are you sure you want to remove this day?</p>
            </div>
            <div style="padding: 0 20px 16px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" data-bs-dismiss="modal"
                    style="padding: 8px 18px; font-size: 13px; font-weight: 500; color: var(--heco-neutral-500); background: none; border: 1.5px solid var(--heco-neutral-200); border-radius: 10px; cursor: pointer; transition: all 0.2s;">
                    Cancel
                </button>
                <button type="button" id="removeDayConfirmBtn"
                    style="padding: 8px 22px; font-size: 13px; font-weight: 600; color: #fff; background: linear-gradient(135deg, #dc2626, #ef4444); border: none; border-radius: 10px; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(220,38,38,0.25);"
                    onmouseover="this.style.boxShadow='0 4px 14px rgba(220,38,38,0.35)';this.style.transform='translateY(-1px)'"
                    onmouseout="this.style.boxShadow='0 2px 8px rgba(220,38,38,0.25)';this.style.transform='none'">
                    <i class="bi bi-trash3" style="margin-right: 4px;"></i> Remove
                </button>
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
    var preferredExpIds = {!! json_encode($preferredExpIds) !!};
    var discoverPage = 1;
    var discoverLoading = false;
    var discoverHasMore = true;
    var currentRegionId = '';
    var debounceTimer = null;

    // Region data for cascading filters
    var allRegions = {!! json_encode($regions->map(function($r) {
        return ['id' => $r->id, 'name' => $r->name, 'continent' => $r->continent, 'country' => $r->country];
    })->values()) !!};

    // Build continent dropdown from region data
    (function() {
        var continents = [];
        var countries = [];
        allRegions.forEach(function(r) {
            if (r.continent && continents.indexOf(r.continent) === -1) continents.push(r.continent);
            if (r.country && countries.indexOf(r.country) === -1) countries.push(r.country);
        });
        continents.sort();
        countries.sort();
        var cSel = jQuery('#filterContinent');
        continents.forEach(function(c) {
            cSel.append('<option value="' + c + '">' + c + '</option>');
        });
    })();

    // Cascade: Continent → Country → Region
    function updateCountryOptions() {
        var continent = jQuery('#filterContinent').val();
        var cSel = jQuery('#filterCountry');
        var prevVal = cSel.val();
        cSel.find('option:not(:first)').remove();
        var countries = [];
        allRegions.forEach(function(r) {
            if ((!continent || r.continent === continent) && r.country && countries.indexOf(r.country) === -1) {
                countries.push(r.country);
            }
        });
        countries.sort();
        countries.forEach(function(c) {
            cSel.append('<option value="' + c + '">' + c + '</option>');
        });
        if (countries.indexOf(prevVal) === -1) cSel.val('');
    }

    function updateRegionOptions() {
        var continent = jQuery('#filterContinent').val();
        var country = jQuery('#filterCountry').val();
        var rSel = jQuery('#filterRegion');
        var prevVal = rSel.val();
        rSel.empty();
        allRegions.forEach(function(r) {
            if ((!continent || r.continent === continent) && (!country || r.country === country)) {
                rSel.append('<option value="' + r.id + '">' + r.name + '</option>');
            }
        });
        if (!rSel.find('option[value="' + prevVal + '"]').length) rSel.val(rSel.find('option:first').val());
    }

    // Initialize country options on load
    updateCountryOptions();

    // ===================================
    // Custom dropdown builder
    // ===================================
    function buildCustomDropdown(sel) {
        var $sel = jQuery(sel);
        if ($sel.closest('.custom-select-wrap').length) {
            // Already wrapped — just rebuild options
            var $wrap = $sel.closest('.custom-select-wrap');
            var $optList = $wrap.find('.custom-select-options');
            var $trigger = $wrap.find('.custom-select-trigger span');
            $optList.empty();
            $sel.find('option').each(function() {
                var cls = 'custom-select-option' + ($sel.val() == jQuery(this).val() ? ' selected' : '');
                $optList.append('<div class="' + cls + '" data-value="' + jQuery(this).val() + '">' + jQuery(this).text() + '</div>');
            });
            var selText = $sel.find('option:selected').text() || $sel.find('option:first').text();
            $trigger.text(selText);
            return;
        }
        // First time — wrap the select
        var $wrap = jQuery('<div class="custom-select-wrap"></div>');
        $sel.wrap($wrap);
        $wrap = $sel.parent();
        var selText = $sel.find('option:selected').text() || $sel.find('option:first').text();
        var $trigger = jQuery('<div class="custom-select-trigger"><span>' + selText + '</span><i class="bi bi-chevron-down caret"></i></div>');
        var $optList = jQuery('<div class="custom-select-options"></div>');
        $sel.find('option').each(function() {
            var cls = 'custom-select-option' + ($sel.val() == jQuery(this).val() ? ' selected' : '');
            $optList.append('<div class="' + cls + '" data-value="' + jQuery(this).val() + '">' + jQuery(this).text() + '</div>');
        });
        $wrap.append($trigger).append($optList);

        // Toggle open
        $trigger.on('click', function(e) {
            e.stopPropagation();
            jQuery('.custom-select-wrap').not($wrap).removeClass('open');
            $wrap.toggleClass('open');
        });

        // Select option
        $optList.on('click', '.custom-select-option', function(e) {
            e.stopPropagation();
            var val = jQuery(this).data('value');
            $sel.val(val).trigger('change');
            $trigger.find('span').text(jQuery(this).text());
            $optList.find('.custom-select-option').removeClass('selected');
            jQuery(this).addClass('selected');
            $wrap.removeClass('open');
        });
    }

    // Close all custom dropdowns on outside click
    jQuery(document).on('click', function() {
        jQuery('.custom-select-wrap').removeClass('open');
    });

    // Initialize all filter dropdowns
    jQuery('.filter-bar select').each(function() {
        buildCustomDropdown(this);
    });

    // Rebuild custom dropdown after dynamic option changes
    var origUpdateCountry = updateCountryOptions;
    updateCountryOptions = function() {
        origUpdateCountry();
        buildCustomDropdown(jQuery('#filterCountry')[0]);
    };
    var origUpdateRegion = updateRegionOptions;
    updateRegionOptions = function() {
        origUpdateRegion();
        buildCustomDropdown(jQuery('#filterRegion')[0]);
    };

    // ===================================
    // LEAFLET MAP (lazy init)
    // ===================================
    var map = null;
    var mapInitialized = false;
    var markers = [];
    var markerMap = {};
    var pendingMapExperiences = [];

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

    function initMap() {
        if (mapInitialized) return;
        map = L.map('discoverMap').setView([20, 60], 3);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(map);
        mapInitialized = true;
        // Apply any pending marker data
        if (pendingMapExperiences.length) {
            updateMapMarkers(pendingMapExperiences);
        }
    }

    function clearMarkers() {
        if (!map) return;
        markers.forEach(function(m) { map.removeLayer(m); });
        markers = [];
        markerMap = {};
    }

    function updateMapMarkers(experiences) {
        // Store for lazy init
        pendingMapExperiences = experiences;
        if (!mapInitialized) return;

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

            var imgHtml = exp.card_image
                ? '<img src="' + exp.card_image + '">'
                : '';
            var isAdded = selectedExpIds.indexOf(exp.id) !== -1;
            var addBtnHtml = isAdded
                ? '<button class="map-popup-btn added" disabled><i class="bi bi-check-lg"></i> Added</button>'
                : '<button class="map-popup-btn btn-map-add-exp" data-exp-id="' + exp.id + '" data-exp-name="' + (exp.name || '').replace(/"/g, '&quot;') + '"><i class="bi bi-plus-lg"></i> Add to Journey</button>';

            var popupHtml = '<div class="map-popup-card">';
            popupHtml += imgHtml;
            popupHtml += '<div class="map-popup-body">';
            popupHtml += '<div class="map-popup-title">' + exp.name + '</div>';
            popupHtml += '<div class="map-popup-meta">';
            if (exp.region) popupHtml += '<i class="bi bi-geo-alt"></i> ' + exp.region.name + '&nbsp;&nbsp;';
            popupHtml += '<i class="bi bi-clock"></i> ' + durationText;
            if (exp.difficulty_level) popupHtml += '&nbsp;&nbsp;<i class="bi bi-speedometer2"></i> ' + exp.difficulty_level;
            popupHtml += '</div>';
            if (exp.short_description) {
                var desc = exp.short_description.length > 80 ? exp.short_description.substring(0, 80) + '...' : exp.short_description;
                popupHtml += '<div class="map-popup-desc">' + desc + '</div>';
            }
            popupHtml += '<div class="map-popup-footer">';
            if (exp.base_cost_per_person > 0) {
                popupHtml += '<span class="map-popup-price">' + fmtCurrency(exp.base_cost_per_person, exp.price_currency || 'INR') + '/person</span>';
            }
            popupHtml += addBtnHtml;
            popupHtml += '</div>';
            popupHtml += '</div></div>';

            var marker = L.marker([lat, lng], { icon: redIcon })
                .bindPopup(popupHtml, { maxWidth: 350, minWidth: 320, className: 'map-exp-popup', autoPan: false })
                .addTo(map);

            marker.on('mouseover', function() { this.openPopup(); });
            marker.on('mouseout', function(e) {
                var popup = this.getPopup();
                if (popup && popup.getElement()) {
                    var popupEl = popup.getElement();
                    // Keep open if mouse moved into the popup
                    popupEl.onmouseenter = function() { marker._keepPopup = true; };
                    popupEl.onmouseleave = function() { marker._keepPopup = false; marker.closePopup(); };
                }
                var self = this;
                setTimeout(function() { if (!self._keepPopup) self.closePopup(); }, 100);
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
        if (mapInitialized && markerMap[expId]) {
            markerMap[expId].openPopup();
        }
    });

    jQuery(document).on('mouseleave', '.exp-card', function() {
        var expId = jQuery(this).data('exp-id');
        if (mapInitialized && markerMap[expId]) {
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
    var aiGenerateXhr = null;
    var aiRetryCount = 0;
    var AI_MAX_RETRIES = 1;

    function autoGenerateItinerary() {
        if (!tripId || selectedExpIds.length === 0) return;

        // Abort any in-flight generation
        if (aiGenerateXhr) {
            aiGenerateXhr.abort();
            aiGenerateXhr = null;
        }
        aiGenerating = true;

        jQuery('#timelineContainer').html(
            '<div class="text-center py-4">' +
            '<div style="width:24px; height:24px; border:3px solid #e9ecef; border-top-color:#6c757d; border-radius:50%; animation:spinIcon 0.8s linear infinite; display:inline-block; vertical-align:middle; margin-right:10px;"></div>' +
            '<span style="font-size:0.875rem; color:#6c757d; vertical-align:middle;">Regenerating your itinerary...</span>' +
            '</div>'
        );

        aiGenerateXhr = jQuery.ajax({
            url: '/ajax',
            method: 'POST',
            data: { generate_itinerary: 1, trip_id: tripId },
            timeout: 120000,
            skipGlobalError: true,
            success: function(resp) {
                aiGenerating = false;
                aiGenerateXhr = null;
                loadTimeline();
                loadPricing();
            },
            error: function(xhr, status) {
                aiGenerateXhr = null;
                if (status === 'abort') return;
                aiGenerating = false;
                loadTimeline();
                loadPricing();
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

    // Continent cascade → update Country & Region dropdowns
    jQuery('#filterContinent').on('change', function() {
        updateCountryOptions();
        updateRegionOptions();
        discoverPage = 1;
        discoverHasMore = true;
        loadExperiences(false);
    });

    // Country cascade → update Region dropdown
    jQuery('#filterCountry').on('change', function() {
        updateRegionOptions();
        discoverPage = 1;
        discoverHasMore = true;
        loadExperiences(false);
    });

    // Filter changes (non-cascading filters)
    jQuery('#filterRegion, #filterType, #filterDifficulty, #filterMonth').on('change', function() {
        discoverPage = 1;
        discoverHasMore = true;
        loadExperiences(false);
    });

    // Clear filters
    jQuery('#clearFilters').on('click', function() {
        jQuery('#filterContinent, #filterCountry, #filterRegion, #filterType, #filterDifficulty, #filterMonth').val('');
        updateCountryOptions();
        updateRegionOptions();
        jQuery('.filter-bar select').each(function() { buildCustomDropdown(this); });
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
        var isPreferred = preferredExpIds.indexOf(exp.id) !== -1;
        var imgHtml = exp.card_image
            ? '<img src="' + exp.card_image + '" alt="' + exp.name + '">'
            : '<div class="exp-placeholder"><i class="bi bi-image"></i></div>';

        var durationText = '';
        if (exp.duration_type === 'less_than_day') {
            durationText = exp.duration_hours + ' hours';
        } else if (exp.duration_type === 'single_day') {
            durationText = '1 Day';
        } else {
            durationText = (exp.duration_days || '?') + ' Days';
        }

        var hostName = (exp.hlh && exp.hlh.name) ? exp.hlh.name : (exp.region ? exp.region.name : '');
        var isAdded = selectedExpIds.indexOf(exp.id) !== -1;

        var h = '<div class="exp-card" data-exp-id="' + exp.id + '">';
        h += '<div class="exp-card-image">';
        h += imgHtml;
        h += '</div>';
        h += '<div class="exp-card-body">';
        h += '<h3 class="exp-card-title"><a href="/experience/' + exp.slug + '" target="_blank">' + exp.name + '</a></h3>';
        if (hostName) h += '<p class="exp-card-host">Hosted by ' + hostName + '</p>';
        h += '<p class="exp-card-duration"><i class="bi bi-clock"></i> ' + durationText + '</p>';
        // Price + stars + heart row
        h += '<div class="exp-card-bottom">';
        if (exp.base_cost_per_person > 0) {
            h += '<span class="exp-card-price">From ' + fmtCurrency(exp.base_cost_per_person, exp.price_currency || 'INR') + '</span>';
        }
        var avg = parseFloat(exp.reviews_avg_rating) || 0;
        var rounded = Math.round(avg);
        if (avg > 0) {
            h += '<span class="exp-card-stars">';
            for (var s = 1; s <= 5; s++) {
                h += '<i class="bi ' + (s <= rounded ? 'bi-star-fill' : 'bi-star') + '"></i>';
            }
            h += '</span>';
        }
        h += '<button class="exp-card-fav ' + (isPreferred ? 'preferred' : '') + '" data-exp-id="' + exp.id + '" title="Add to favorites"><i class="bi bi-heart-fill"></i></button>';
        if (isAdded) {
            h += '<button class="exp-card-add-btn added btn-remove-journey-exp" data-exp-id="' + exp.id + '" data-exp-name="' + (exp.name || '').replace(/"/g, '&quot;') + '" title="Remove from Journey"><i class="bi bi-check-lg"></i></button>';
        } else {
            h += '<button class="exp-card-add-btn btn-add-exp" data-exp-id="' + exp.id + '" data-exp-name="' + (exp.name || '').replace(/"/g, '&quot;') + '" title="Add to Journey"><i class="bi bi-plus-lg"></i></button>';
        }
        h += '</div>';
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
        if (jQuery('#filterContinent').val()) params.continent = jQuery('#filterContinent').val();
        if (jQuery('#filterCountry').val()) params.country = jQuery('#filterCountry').val();
        var regionVal = jQuery('#filterRegion').val() || currentRegionId;
        if (regionVal) params.region_id = regionVal;
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

                if (resp.next_page_url) {
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
        }, function(xhr) {
            discoverLoading = false;
            var errorHtml = '<div class="journey-empty-state" style="grid-column: 1 / -1;">';
            errorHtml += '<div class="journey-empty-icon"><i class="bi bi-exclamation-circle" style="color:#dc3545;"></i></div>';
            errorHtml += '<h3 class="journey-empty-title">Failed to load experiences</h3>';
            errorHtml += '<p class="journey-empty-desc">Something went wrong. Please refresh the page and try again.</p>';
            errorHtml += '<button class="exp-btn exp-btn-primary" style="display:inline-flex; padding:var(--space-3) var(--space-6); margin-top:var(--space-3);" onclick="loadExperiences(false)"><i class="bi bi-arrow-clockwise me-2"></i>Retry</button>';
            errorHtml += '</div>';
            jQuery('#experienceGrid').html(errorHtml);
        });
    }

    // Initial load
    loadExperiences(false);
    initMap();
    setTimeout(function() { if (map) map.invalidateSize(); }, 300);

    // Heart/Prefer button
    jQuery(document).on('click', '.exp-card-fav', function(e) {
        e.stopPropagation();
        if (!isLoggedIn) {
            if (window.openAuthModal) { window.openAuthModal('login'); } else { window.location.href = '/home?auth=login'; }
            return;
        }
        var btn = jQuery(this);
        var expId = btn.data('exp-id');
        ensureTrip(function(tId) {
            ajaxPost({ prefer_experience: 1, trip_id: tId, experience_id: expId }, function(resp) {
                btn.toggleClass('preferred');
                if (resp.is_preferred) {
                    if (preferredExpIds.indexOf(expId) === -1) preferredExpIds.push(expId);
                } else {
                    preferredExpIds = preferredExpIds.filter(function(id) { return id !== expId; });
                }
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

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        ensureTrip(function(tId) {
            ajaxPost({ add_experience_to_trip: 1, trip_id: tId, experience_id: expId }, function(resp) {
                if (selectedExpIds.indexOf(expId) === -1) {
                    selectedExpIds.push(expId);
                }
                // Swap to remove button
                btn.prop('disabled', false)
                    .removeClass('btn-add-exp').addClass('btn-remove-journey-exp added')
                    .attr('title', 'Remove from Journey')
                    .html('<i class="bi bi-check-lg"></i>');
                updateJourneyBadge();
                showAlert('"' + expName + '" added to your journey!', 'success');
                if (resp.trip_id && !tripId) {
                    tripId = resp.trip_id;
                }
                jQuery('#noTripMessage').addClass('d-none');
                jQuery('#journeyPanels').removeClass('d-none');
                jQuery('#noImpactMessage').addClass('d-none');
                jQuery('#impactData').removeClass('d-none');
                loadSelectedExperiences();
                aiRetryCount = 0;
                autoGenerateItinerary();
                // Notify AI chat
                appendChatMsg('assistant', 'You have added **' + expName + '** to your trip.');
                scrollChat();
            }, function() {
                btn.prop('disabled', false).html('<i class="bi bi-plus-lg"></i>');
            });
        });
    });

    // Add to Journey from map popup
    jQuery(document).on('click', '.btn-map-add-exp', function(e) {
        e.stopPropagation();
        var btn = jQuery(this);
        var expId = parseInt(btn.data('exp-id'));
        var expName = btn.data('exp-name') || 'this experience';
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Adding...');
        ensureTrip(function(tId) {
            ajaxPost({ add_experience_to_trip: 1, trip_id: tId, experience_id: expId }, function(resp) {
                if (selectedExpIds.indexOf(expId) === -1) selectedExpIds.push(expId);
                btn.addClass('added').prop('disabled', true).html('<i class="bi bi-check-lg"></i> Added');
                // Also update the grid card button
                jQuery('.btn-add-exp[data-exp-id="' + expId + '"]')
                    .removeClass('btn-add-exp').addClass('btn-remove-journey-exp added')
                    .attr('title', 'Remove from Journey')
                    .html('<i class="bi bi-check-lg"></i>');
                updateJourneyBadge();
                if (resp.trip_id && !tripId) tripId = resp.trip_id;
                jQuery('#noTripMessage').addClass('d-none');
                jQuery('#journeyPanels').removeClass('d-none');
                loadSelectedExperiences();
                aiRetryCount = 0;
                autoGenerateItinerary();
                // Notify AI chat
                appendChatMsg('assistant', 'You have added **' + expName + '** to your trip.');
                scrollChat();
            }, function() {
                btn.prop('disabled', false).html('<i class="bi bi-plus-lg"></i> Add to Journey');
            });
        });
    });

    // Remove from Journey button (on discover/region cards)
    jQuery(document).on('click', '.btn-remove-journey-exp', function(e) {
        e.stopPropagation();
        var btn = jQuery(this);
        var expId = parseInt(btn.data('exp-id'));
        var expName = btn.data('exp-name');

        if (!tripId) return;

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        ajaxPost({ remove_experience_from_trip: 1, trip_id: tripId, experience_id: expId }, function() {
            selectedExpIds = selectedExpIds.filter(function(id) { return parseInt(id) !== expId; });
            // Swap back to add button
            btn.prop('disabled', false)
                .removeClass('btn-remove-journey-exp added').addClass('btn-add-exp')
                .attr('title', 'Add to Journey')
                .html('<i class="bi bi-plus-lg"></i>');
            updateJourneyBadge();
            loadSelectedExperiences();
            showAlert('"' + expName + '" removed from journey.', 'success');
            if (selectedExpIds.length > 0) {
                loadTimeline();
                loadPricing();
            } else {
                jQuery('#timelineContainer').html('<p class="text-center" style="font-size: var(--text-sm); color: var(--color-text-muted); padding: var(--space-6);">Days will appear here when experiences are added</p>');
                loadPricing();
            }
        });
    });

    // ===================================
    // YOUR JOURNEY TAB
    // ===================================

    // Sync chats + invalidate map when switching to Discover tab
    jQuery('button[data-bs-target="#pane-discover"]').on('shown.bs.tab', function() {
        syncChats();
        if (mapInitialized && map) setTimeout(function() { map.invalidateSize(); }, 100);
    });

    // Load journey data on tab show
    jQuery('button[data-bs-target="#pane-journey"]').on('shown.bs.tab', function() {
        if (tripId) {
            loadJourneyData();
        }
        syncChats();
    });

    function loadJourneyData() {
        if (!tripId) return;
        loadSelectedExperiences();
        // Don't overwrite the generating loader if AI is still working
        if (!aiGenerating) {
            loadTimeline();
        }
        loadPricing();
    }

    function loadSelectedExperiences() {
        ajaxPost({ get_trip_selected_experiences: 1, trip_id: tripId }, function(resp) {
            var items = resp.experiences || [];
            selectedExpIds = items.map(function(e) { return e.experience_id; });
            updateJourneyBadge();

            // Update trip summary
            var totalDays = 0;
            var regions = [];
            items.forEach(function(item) {
                var exp = item.experience;
                if (exp) {
                    if (exp.duration_type === 'multi_day') {
                        totalDays += (exp.duration_days || 1);
                    } else {
                        totalDays += 1;
                    }
                    var rName = exp.region ? (exp.region.name || '') : '';
                    if (rName && regions.indexOf(rName) === -1) regions.push(rName);
                }
            });
            jQuery('#tripDuration').text(totalDays + (totalDays === 1 ? ' Day' : ' Days'));
            jQuery('#tripRegions').text(regions.length > 0 ? regions.join(', ') : '');
            jQuery('#tripExpCount').text(items.length);

            if (items.length === 0) {
                jQuery('#selectedExpList').empty();
                jQuery('#tripDuration').text('');
                jQuery('#tripRegions').text('');
                jQuery('#tripExpCount').text('');
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
        jQuery('#timelineContainer').html(
            '<div class="text-center py-4">' +
            '<div style="width:24px; height:24px; border:3px solid #e9ecef; border-top-color:#6c757d; border-radius:50%; animation:spinIcon 0.8s linear infinite; display:inline-block; vertical-align:middle; margin-right:10px;"></div>' +
            '<span style="font-size:0.875rem; color:#6c757d; vertical-align:middle;">Processing...</span>' +
            '</div>'
        );
        ajaxPost({ get_trip_timeline: 1, trip_id: tripId }, function(resp) {
            renderTimelineData(resp);
        }, function() {
            jQuery('#timelineContainer').html(
                '<div class="text-center" style="padding:var(--space-6);">' +
                '<i class="bi bi-exclamation-circle" style="font-size:1.5rem; color:#dc3545;"></i>' +
                '<p class="mt-2" style="font-size:0.875rem; color:#6c757d;">Failed to load timeline. Please try again.</p>' +
                '</div>'
            );
        });
    }

    function renderTimelineData(resp) {
        var days = resp.days || [];

        // Filter out empty days (no experiences, no services, and no description)
        days = days.filter(function(day) {
            return (day.experiences && day.experiences.length > 0) || (day.services && day.services.length > 0) || day.description;
        });

        if (days.length === 0) {
            // If we have experiences but no days, auto-generate (with retry limit to avoid infinite loops)
            if (selectedExpIds.length > 0 && !aiGenerating && aiRetryCount <= AI_MAX_RETRIES) {
                aiRetryCount++;
                autoGenerateItinerary();
                return;
            }
            if (selectedExpIds.length > 0) {
                jQuery('#timelineContainer').html('<p class="text-center" style="font-size: var(--text-sm); color: var(--color-text-muted); padding: var(--space-6);" id="emptyTimeline">Could not generate itinerary. Please click "Regenerate" or try again later.</p>');
            } else {
                jQuery('#timelineContainer').empty();
            }
            return;
        }

        var html = '';
        var tripStartDate = resp.start_date ? new Date(resp.start_date) : null;

        // Deduplicate experiences within each day and limit multi-day experiences to their duration_days
        var _expOcc = {};
        days.forEach(function(d) {
            if (d.experiences && d.experiences.length) {
                // Remove duplicate experience_ids within the same day
                // AND cap multi-day experiences to their duration_days
                var seen = {};
                d.experiences = d.experiences.filter(function(de) {
                    var eid = de.experience_id || (de.experience && de.experience.id);
                    if (!eid || seen[eid]) return false;
                    seen[eid] = true;
                    // Check if this experience already reached its max days
                    var exp = de.experience;
                    var maxDays = (exp && exp.duration_type === 'multi_day' && exp.duration_days) ? exp.duration_days : 1;
                    if ((_expOcc[eid] || 0) >= maxDays) return false;
                    return true;
                });
                d.experiences.forEach(function(de) {
                    var eid = de.experience_id || (de.experience && de.experience.id);
                    if (eid) {
                        _expOcc[eid] = (_expOcc[eid] || 0) + 1;
                        de._expDayNum = _expOcc[eid];
                    }
                });
            }
        });

        // Fill missing days for multi-day experiences that have fewer TripDays than duration_days
        var _expSeen = {};
        days.forEach(function(d) {
            if (d.experiences && d.experiences.length) {
                d.experiences.forEach(function(de) {
                    var eid = de.experience_id || (de.experience && de.experience.id);
                    if (eid) _expSeen[eid] = { count: _expOcc[eid], experience: de.experience, de: de };
                });
            }
        });
        Object.keys(_expSeen).forEach(function(eid) {
            var info = _expSeen[eid];
            var exp = info.experience;
            if (!exp) return;
            // Use duration_days as the authoritative day count, fall back to exp.days.length
            var expectedDays = (exp.duration_type === 'multi_day' && exp.duration_days) ? exp.duration_days : (exp.days ? exp.days.length : 0);
            if (expectedDays > info.count) {
                for (var n = info.count + 1; n <= expectedDays; n++) {
                    var virtualDe = { experience_id: parseInt(eid), experience: exp, _expDayNum: n, cost_per_person: info.de.cost_per_person, start_time: null, end_time: null };
                    var expDay = exp.days ? exp.days.find(function(ed) { return ed.day_number === n; }) : null;
                    if (expDay) {
                        virtualDe.start_time = expDay.start_time;
                        virtualDe.end_time = expDay.end_time;
                    }
                    days.push({ id: 'virtual_' + eid + '_' + n, day_number: days.length + 1, experiences: [virtualDe], services: [] });
                }
            }
        });

        // Sync start date display & input
        if (resp.start_date) {
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var sd = new Date(resp.start_date);
            jQuery('#tripStartDateDisplay').text(sd.getDate() + ' ' + months[sd.getMonth()] + ' ' + sd.getFullYear());
            jQuery('#tripStartDateInput').val(resp.start_date);
        }

        // Trip ID header (only for logged-in users with real trip)
        if (tripId && tripId !== 'guest') {
            html += '<div class="trip-id-display">Trip ID : ' + tripId + '</div>';
        }

        days.forEach(function(day, index) {
            // Insert-day button: before Day 1 of each experience, after Day 1, and after last day
            if (index === 0) {
                // Always show add-day button before the very first day
                html += '<div class="timeline-add-day-row">';
                html += '<div></div>';
                html += '<div class="tl-insert-line">';
                html += '<button class="btn-insert-day" data-after-day="0" title="Add a day here">';
                html += '<i class="bi bi-plus-lg"></i>';
                html += '</button>';
                html += '</div>';
                html += '<div></div>';
                html += '</div>';
            } else {
                var prevDay = days[index - 1];
                var curDay = day;
                var showInsert = false;

                if (!prevDay.experiences || !prevDay.experiences.length) {
                    showInsert = true;
                } else {
                    var prevDe = prevDay.experiences[0];
                    var prevExp = prevDe.experience;
                    var prevDayNum = prevDe._expDayNum || 1;
                    var prevMaxDays = (prevExp && prevExp.duration_type === 'multi_day' && prevExp.duration_days) ? prevExp.duration_days : 1;

                    // Show after last day of experience only
                    if (prevDayNum >= prevMaxDays) {
                        showInsert = true;
                    }
                }

                // Also show before Day 1 of a new experience
                if (!showInsert && curDay.experiences && curDay.experiences.length) {
                    var curDe = curDay.experiences[0];
                    var curDayNum = curDe._expDayNum || 1;
                    if (curDayNum === 1) showInsert = true;
                }

                html += '<div class="timeline-add-day-row">';
                html += '<div></div>';
                if (showInsert) {
                    html += '<div class="tl-insert-line">';
                    html += '<button class="btn-insert-day" data-after-day="' + (prevDay.day_number || index) + '" title="Add a day here">';
                    html += '<i class="bi bi-plus-lg"></i>';
                    html += '</button>';
                    html += '</div>';
                } else {
                    html += '<div class="tl-insert-line"></div>';
                }
                html += '<div></div>';
                html += '</div>';
            }

            // Get day-wise title from first experience (use occurrence-based matching)
            var dayTitle = '';
            if (day.experiences && day.experiences.length) {
                var firstDe = day.experiences[0];
                var firstExp = firstDe.experience;
                if (firstExp && firstExp.days && firstExp.days.length) {
                    var edNum = firstDe._expDayNum || 1;
                    var matchedDay = firstExp.days.length === 1 ? firstExp.days[0] : firstExp.days.find(function(d) { return d.day_number === edNum; }) || firstExp.days[0];
                    if (matchedDay && matchedDay.title) dayTitle = matchedDay.title;
                }
            }

            // Format date as "2 Mar 2026"
            var formattedDate = '';
            var dateObj = null;
            if (day.date) {
                dateObj = new Date(day.date);
            } else if (tripStartDate) {
                dateObj = new Date(tripStartDate);
                dateObj.setDate(dateObj.getDate() + index);
            }
            if (dateObj && !isNaN(dateObj)) {
                var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                formattedDate = dateObj.getDate() + ' ' + months[dateObj.getMonth()] + ' ' + dateObj.getFullYear();
            }

            // === 3-column grid row ===
            html += '<div class="timeline-day" data-day-id="' + day.id + '">';

            // Left column: day label + inclusion icons from ExperienceDay
            html += '<div class="timeline-day-label">';
            html += '<span class="timeline-day-number">Day ' + (index + 1) + '</span>';
            if (formattedDate) html += '<span class="timeline-day-date">' + formattedDate + '</span>';
            // Collect inclusions from matching ExperienceDay
            var dayInclusions = [];
            if (day.experiences && day.experiences.length) {
                day.experiences.forEach(function(de) {
                    var exp = de.experience;
                    if (exp && exp.days && exp.days.length) {
                        var edNum = de._expDayNum || 1;
                        var ed = exp.days.length === 1 ? exp.days[0] : exp.days.find(function(d) { return d.day_number === edNum; }) || exp.days[0];
                        if (ed && ed.inclusions && ed.inclusions.length) {
                            ed.inclusions.forEach(function(inc) {
                                if (dayInclusions.indexOf(inc) === -1) dayInclusions.push(inc);
                            });
                        }
                    }
                });
            }
            if (dayInclusions.length) {
                var incIconMap = { breakfast: 'bi-cup-hot', lunch: 'bi-egg-fried', dinner: 'bi-moon-stars', snacks: 'bi-basket', accommodation: 'bi-house', guide: 'bi-person-badge', transport: 'bi-truck' };
                html += '<div class="timeline-day-svc-icons">';
                dayInclusions.forEach(function(inc) {
                    var ico = incIconMap[inc] || 'bi-check';
                    html += '<i class="bi ' + ico + '" title="' + inc.charAt(0).toUpperCase() + inc.slice(1) + '"></i>';
                });
                html += '</div>';
            }
            html += '</div>';

            // Center column: line + dot
            html += '<div class="timeline-day-line"><div class="timeline-day-dot"></div></div>';

            // Right column: content
            html += '<div class="timeline-day-content">';

            // Header row (title on left, actions on right)
            html += '<div class="timeline-day-header">';
            if (dayTitle) html += '<span class="timeline-day-title" title="' + dayTitle + '">' + dayTitle + '</span>';
            html += '<div style="display:flex;align-items:center;gap:4px;margin-left:auto;">';
            if (day.is_locked) html += '<i class="bi bi-lock" style="color: var(--heco-warning); font-size: 13px;" title="Locked"></i>';
            html += '<button class="btn-remove btn-remove-day" data-day-id="' + day.id + '" title="Remove Day"><i class="bi bi-trash"></i></button>';
            html += '</div>';
            html += '</div>';

            // Day experiences
            if (day.experiences && day.experiences.length) {
                var shownExpNames = [];
                day.experiences.forEach(function(de) {
                    var exp = de.experience;
                    var eName = exp ? exp.name : 'Experience';
                    var typeIconMap = {
                        trek: 'bi-signpost-split', cultural: 'bi-bank', adventure: 'bi-lightning',
                        wildlife: 'bi-binoculars', wellness: 'bi-heart-pulse', culinary: 'bi-cup-hot',
                        village: 'bi-houses', other: 'bi-star-fill'
                    };
                    var expIcon = (exp && exp.type) ? (typeIconMap[exp.type] || 'bi-star-fill') : 'bi-star-fill';
                    var alreadyShown = shownExpNames.indexOf(eName) !== -1;
                    shownExpNames.push(eName);

                    html += '<div class="timeline-exp-item">';
                    html += '<i class="bi ' + expIcon + '"></i>';
                    html += '<div class="timeline-exp-details">';
                    if (!alreadyShown) html += '<span class="timeline-exp-name">' + eName + '</span>';

                    if (exp && exp.days && exp.days.length) {
                        var edNum = de._expDayNum || 1;
                        var ed = exp.days.length === 1 ? exp.days[0] : exp.days.find(function(d) { return d.day_number === edNum; }) || exp.days[0];
                        if (ed && ed.short_description) {
                            html += '<div style="font-size:0.7rem; color:var(--heco-neutral-600, #475569);">' + ed.short_description + '</div>';
                        }
                    } else if (de.notes) {
                        html += '<div class="timeline-exp-notes">' + toBulletHtml(de.notes) + '</div>';
                    }

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

            // Day services (icons shown in left column)

            if ((!day.experiences || !day.experiences.length) && (!day.services || !day.services.length)) {
                var emptyDayText = 'Travel / Transition Day';
                var emptyDayDesc = '';
                if (day.description) {
                    emptyDayDesc = day.description;
                } else if (day.notes) {
                    emptyDayDesc = day.notes;
                }
                html += '<div style="text-align: center; padding: var(--space-3);">';
                html += '<p style="font-size: var(--text-sm); color: var(--heco-green, #2d6a4f); font-weight: 600; margin: 0;"><i class="bi bi-signpost-split"></i> ' + emptyDayText + '</p>';
                if (emptyDayDesc) {
                    html += '<p style="font-size: 0.75rem; color: var(--color-text-muted); margin: 4px 0 0;">' + emptyDayDesc + '</p>';
                } else {
                    html += '<p style="font-size: 0.75rem; color: var(--color-text-muted); margin: 4px 0 0;">Rest, travel between destinations, or explore at your own pace</p>';
                }
                html += '</div>';
            }

            html += '</div>'; // .timeline-day-content
            html += '</div>'; // .timeline-day
        });

        // Add-day button after the last day
        if (days.length > 0) {
            var lastDay = days[days.length - 1];
            html += '<div class="timeline-add-day-row">';
            html += '<div></div>';
            html += '<div class="tl-insert-line">';
            html += '<button class="btn-insert-day" data-after-day="' + (lastDay.day_number || days.length) + '" title="Add a day here">';
            html += '<i class="bi bi-plus-lg"></i>';
            html += '</button>';
            html += '</div>';
            html += '<div></div>';
            html += '</div>';
        }

        jQuery('#timelineContainer').html(html);
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

    // Remove experience — styled modal confirmation
    var pendingRemoveExpId = null;
    var pendingRemoveExpName = '';
    jQuery(document).on('click', '.btn-remove-exp', function() {
        var btn = jQuery(this);
        pendingRemoveExpId = parseInt(btn.data('exp-id'));
        pendingRemoveExpName = btn.closest('.journey-exp-item').find('.exp-name').text().trim() || 'this experience';
        if (!tripId || !pendingRemoveExpId) return;
        jQuery('#removeExpModalSubtitle').text(pendingRemoveExpName);
        jQuery('#removeExpModalMsg').html('Are you sure you want to remove <strong>' + pendingRemoveExpName + '</strong> from your trip?');
        var modal = new bootstrap.Modal(document.getElementById('removeExpModal'));
        modal.show();
    });
    jQuery('#removeExpConfirmBtn').on('click', function() {
        if (!pendingRemoveExpId) return;
        var btn = jQuery(this);
        var expId = pendingRemoveExpId;
        var expName = pendingRemoveExpName;
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Removing...');
        ajaxPost({ remove_experience_from_trip: 1, trip_id: tripId, experience_id: expId }, function() {
            bootstrap.Modal.getInstance(document.getElementById('removeExpModal')).hide();
            btn.prop('disabled', false).html('<i class="bi bi-trash3" style="margin-right:4px"></i> Remove');
            selectedExpIds = selectedExpIds.filter(function(id) { return parseInt(id) !== expId; });
            updateJourneyBadge();
            loadSelectedExperiences();
            loadTimeline();
            loadPricing();
            jQuery('.btn-remove-journey-exp[data-exp-id="' + expId + '"]')
                .removeClass('btn-remove-journey-exp added').addClass('btn-add-exp')
                .attr('title', 'Add to Journey')
                .html('<i class="bi bi-plus-lg"></i>');
            appendChatMsg('assistant', 'You have removed **' + expName + '** from your trip.');
            scrollChat();
            pendingRemoveExpId = null;
            pendingRemoveExpName = '';
        }, function() {
            btn.prop('disabled', false).html('<i class="bi bi-trash3" style="margin-right:4px"></i> Remove');
            showAlert('Failed to remove experience. Please try again.', 'danger');
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
                order.push(parseInt(jQuery(this).data('exp-id')));
            });
            selectedExpIds = order;
            // Save new order then regenerate timeline via AI
            ajaxPost({ reorder_experiences: 1, trip_id: tripId, order: order }, function() {
                autoGenerateItinerary();
            });
        });
    }


    // Helper: send a message to AI chat programmatically
    function sendAiMessage(msg) {
        // Switch to journey tab if not already there
        var journeyTab = jQuery('#tab-journey');
        if (journeyTab.length && !journeyTab.hasClass('active')) {
            journeyTab.click();
        }
        // Set the journey chat input and trigger send
        activeChatInput = '#journeyChatInput';
        jQuery('#journeyChatInput').val(msg);
        sendChatMessage();
        // Scroll to chat section so user can see the AI response
        var chatSection = document.querySelector('.journey-chat-section');
        if (chatSection) chatSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Insert Day — show popup for description, then send to AI
    var pendingInsertAfterDay = null;
    jQuery(document).on('click', '.btn-insert-day', function() {
        if (!tripId) return;
        pendingInsertAfterDay = jQuery(this).data('after-day');
        jQuery('#addDayModalSubtitle').text('Adding a new day after Day ' + pendingInsertAfterDay);
        jQuery('#addDayDescription').val('');
        var modal = new bootstrap.Modal(document.getElementById('addDayModal'));
        modal.show();
        setTimeout(function() { jQuery('#addDayDescription').focus(); }, 300);
    });

    jQuery('#addDayConfirmBtn').on('click', function() {
        if (pendingInsertAfterDay === null) return;
        var desc = jQuery('#addDayDescription').val().trim();
        var btn = jQuery(this);
        var insertAfter = pendingInsertAfterDay;
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Adding...');
        ajaxPost({
            add_day_to_trip: 1,
            trip_id: tripId,
            after_day_number: insertAfter,
            day_note: desc
        }, function(resp) {
            bootstrap.Modal.getInstance(document.getElementById('addDayModal')).hide();
            btn.prop('disabled', false).html('<i class="bi bi-plus-lg" style="margin-right:4px"></i> Add Day');
            showAlert('Day added successfully!', 'success');
            loadTimeline();
            loadPricing();
            // Notify AI about the added day
            var newDayNum = parseInt(insertAfter) + 1;
            var chatMsg = 'I have added a new day (Day ' + newDayNum + ') to my trip' + (insertAfter > 0 ? ' after Day ' + insertAfter : ' at the beginning') + '.';
            if (desc) chatMsg += ' Note: ' + desc;
            sendAiMessage(chatMsg);
            pendingInsertAfterDay = null;
        }, function() {
            btn.prop('disabled', false).html('<i class="bi bi-plus-lg" style="margin-right:4px"></i> Add Day');
            showAlert('Failed to add day. Please try again.', 'danger');
        });
    });

    // Remove Day — styled modal confirmation
    var pendingRemoveDayId = null;
    var pendingRemoveDayLabel = '';
    jQuery(document).on('click', '.btn-remove-day', function() {
        pendingRemoveDayId = jQuery(this).data('day-id');
        var dayEl = jQuery(this).closest('.timeline-day');
        pendingRemoveDayLabel = dayEl.find('.timeline-day-number').text() || 'this day';
        jQuery('#removeDayModalSubtitle').text(pendingRemoveDayLabel);
        jQuery('#removeDayModalMsg').html('Are you sure you want to remove <strong>' + pendingRemoveDayLabel + '</strong> from your trip? This action cannot be undone.');
        var modal = new bootstrap.Modal(document.getElementById('removeDayModal'));
        modal.show();
    });
    jQuery('#removeDayConfirmBtn').on('click', function() {
        if (!pendingRemoveDayId) return;
        var btn = jQuery(this);
        var dayLabel = pendingRemoveDayLabel;
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Removing...');
        ajaxPost({ remove_day_from_trip: 1, trip_id: tripId, day_id: pendingRemoveDayId }, function() {
            bootstrap.Modal.getInstance(document.getElementById('removeDayModal')).hide();
            btn.prop('disabled', false).html('<i class="bi bi-trash3" style="margin-right:4px"></i> Remove');
            showAlert('Day removed.', 'success');
            loadTimeline();
            loadPricing();
            // Notify AI about the removed day
            appendChatMsg('assistant', 'You have removed **' + dayLabel + '** from your trip.');
            scrollChat();
            pendingRemoveDayId = null;
            pendingRemoveDayLabel = '';
        }, function() {
            btn.prop('disabled', false).html('<i class="bi bi-trash3" style="margin-right:4px"></i> Remove');
            showAlert('Failed to remove day. Please try again.', 'danger');
        });
    });

    // Trip name — send to AI for confirmation
    var tripNameTimer = null;
    jQuery('#tripName').on('input', function() {
        var val = jQuery(this).val();
        clearTimeout(tripNameTimer);
        tripNameTimer = setTimeout(function() {
            if (val.trim()) {
                sendAiMessage('I want to change my trip name to "' + val.trim() + '".');
            }
        }, 1200);
    });

    // Group details — debounced, send to AI for confirmation
    var groupTimer = null;
    jQuery('.group-input').on('change', function() {
        clearTimeout(groupTimer);
        groupTimer = setTimeout(function() {
            var adults = parseInt(jQuery('#grpAdults').val()) || 1;
            var children = parseInt(jQuery('#grpChildren').val()) || 0;
            var infants = parseInt(jQuery('#grpInfants').val()) || 0;
            sendAiMessage('I want to update my group to ' + adults + ' adults, ' + children + ' children, and ' + infants + ' infants.');
        }, 600);
    });

    // Preferences — send to AI for confirmation
    jQuery('.pref-input').on('change', function() {
        var label = jQuery(this).closest('.mb-3, .mb-2').find('label').text().trim();
        var val = jQuery(this).val();
        sendAiMessage('I want to change my ' + label + ' preference to "' + val + '".');
    });

    // Start Date — click to edit
    jQuery('#tripStartDateDisplay').on('click', function() {
        jQuery(this).addClass('d-none');
        jQuery('#tripStartDateInput').removeClass('d-none').focus();
    });

    jQuery('#tripStartDateInput').on('change', function() {
        var val = jQuery(this).val();
        // Revert display and hide input — AI will update after confirmation
        jQuery('#tripStartDateDisplay').removeClass('d-none');
        jQuery(this).addClass('d-none');

        if (val) {
            var d = new Date(val);
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var formatted = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
            sendAiMessage('I want to change my trip start date to ' + formatted + '.');
        }
    });

    jQuery('#tripStartDateInput').on('blur', function() {
        var self = jQuery(this);
        setTimeout(function() {
            if (!self.is(':focus')) {
                self.addClass('d-none');
                jQuery('#tripStartDateDisplay').removeClass('d-none');
            }
        }, 200);
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
        var el = '<div class="chat-msg ' + role + '">' + escaped + '</div>';
        jQuery('#collapseChatMessages').append(el);
        jQuery('#journeyChatMessages').append(el);
    }

    function scrollChat() {
        var c1 = document.getElementById('collapseChatMessages');
        var c2 = document.getElementById('journeyChatMessages');
        if (c1) c1.scrollTop = c1.scrollHeight;
        if (c2) c2.scrollTop = c2.scrollHeight;
    }

    function loadChatHistory() {
        if (chatHistoryLoaded) return;
        ajaxPost({ get_chat_history: 1, trip_id: tripId || '' }, function(resp) {
            chatHistoryLoaded = true;
            var messages = resp.messages || [];
            if (messages.length === 0) return;
            jQuery('#collapseChatMessages').find('.chat-msg.assistant').first().remove();
            jQuery('#journeyChatMessages').find('.chat-msg.assistant').first().remove();
            messages.forEach(function(msg) {
                appendChatMsg(msg.role, msg.content);
            });
            scrollChat();
        });
    }

    // Sync both chat containers — copy from whichever has more messages
    function syncChats() {
        var mainMsgs = jQuery('#collapseChatMessages .chat-msg');
        var journeyMsgs = jQuery('#journeyChatMessages .chat-msg');
        if (mainMsgs.length > journeyMsgs.length) {
            jQuery('#journeyChatMessages').empty();
            mainMsgs.each(function() {
                jQuery('#journeyChatMessages').append(jQuery(this).clone());
            });
        } else if (journeyMsgs.length > mainMsgs.length) {
            jQuery('#collapseChatMessages').empty();
            journeyMsgs.each(function() {
                jQuery('#collapseChatMessages').append(jQuery(this).clone());
            });
        }
        scrollChat();
    }

    var activeChatInput = '#collapseChatInput';

    function sendChatMessage() {
        var msg = jQuery(activeChatInput).val().trim();
        if (!msg) return;

        jQuery(activeChatInput).val('');
        appendChatMsg('user', msg);
        scrollChat();

        var typingHtml = '<div class="chat-msg assistant chat-typing"><i class="bi bi-three-dots"></i> Thinking...</div>';
        jQuery('#collapseChatMessages').append(typingHtml);
        jQuery('#journeyChatMessages').append(typingHtml);
        scrollChat();

        var params = { chat_with_ai: 1, message: msg };
        if (tripId) params.trip_id = tripId;

        ajaxPost(params, function(resp) {
            jQuery('.chat-typing').remove();
            appendChatMsg('assistant', resp.response || 'I could not generate a response. Please try again.');

            if (resp.trip_id) {
                if (!tripId) {
                    jQuery('#noTripMessage').addClass('d-none');
                    jQuery('#journeyPanels').removeClass('d-none');
                    jQuery('#noImpactMessage').addClass('d-none');
                    jQuery('#impactData').removeClass('d-none');
                }
                tripId = resp.trip_id;
            }

            if (resp.trip_updated) {
                loadJourneyData();
                updateJourneyBadge();
            }

            // Sync UI inputs with AI-confirmed details
            if (resp.updated_details) {
                var d = resp.updated_details;
                if (d.adults !== undefined) jQuery('#grpAdults').val(d.adults);
                if (d.children !== undefined) jQuery('#grpChildren').val(d.children);
                if (d.infants !== undefined) jQuery('#grpInfants').val(d.infants);
                if (d.accommodation_comfort) jQuery('#prefAccommodation').val(d.accommodation_comfort);
                if (d.vehicle_comfort) jQuery('#prefVehicle').val(d.vehicle_comfort);
                if (d.guide_preference) jQuery('#prefGuide').val(d.guide_preference);
                if (d.start_date) {
                    jQuery('#tripStartDateInput').val(d.start_date);
                    var sd = new Date(d.start_date);
                    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    jQuery('#tripStartDateDisplay').text(sd.getDate() + ' ' + months[sd.getMonth()] + ' ' + sd.getFullYear());
                }
            }

            // Handle AI auto-added experiences
            if (resp.added_experience_ids && resp.added_experience_ids.length > 0) {
                resp.added_experience_ids.forEach(function(id) {
                    if (selectedExpIds.indexOf(id) === -1) selectedExpIds.push(id);
                    // Swap + button to check button on card
                    var btn = jQuery('.btn-add-exp[data-exp-id="' + id + '"]');
                    if (btn.length) {
                        btn.removeClass('btn-add-exp').addClass('btn-remove-journey-exp added')
                            .attr('title', 'Remove from Journey').html('<i class="bi bi-check-lg"></i>');
                    }
                });
                updateJourneyBadge();
                loadSelectedExperiences();
                aiRetryCount = 0;
                autoGenerateItinerary();
                showAlert(resp.added_experience_ids.length + ' experience(s) added to your journey!', 'success');
            }

            // Handle AI-removed experiences
            if (resp.removed_experience_ids && resp.removed_experience_ids.length > 0) {
                resp.removed_experience_ids.forEach(function(id) {
                    selectedExpIds = selectedExpIds.filter(function(eid) { return eid != id; });
                    // Swap check button back to + button on card
                    var btn = jQuery('.btn-remove-journey-exp[data-exp-id="' + id + '"]');
                    if (btn.length) {
                        btn.removeClass('btn-remove-journey-exp added').addClass('btn-add-exp')
                            .attr('title', 'Add to Journey').html('<i class="bi bi-plus-lg"></i>');
                    }
                });
                updateJourneyBadge();
                loadSelectedExperiences();
                aiRetryCount = 0;
                autoGenerateItinerary();
                showAlert('Experience(s) removed from journey.', 'info');
            }

            // Highlight AI-recommended experiences
            if (resp.recommended_experience_ids && resp.recommended_experience_ids.length > 0) {
                highlightRecommendedExperiences(resp.recommended_experience_ids);
            }

            scrollChat();
        }, function() {
            jQuery('.chat-typing').remove();
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
            // Sort cards so ai-recommended ones appear first
            var grid = jQuery('#experienceGrid');
            var cards = grid.children('.exp-card').detach();
            cards.sort(function(a, b) {
                var aRec = jQuery(a).hasClass('ai-recommended') ? 0 : 1;
                var bRec = jQuery(b).hasClass('ai-recommended') ? 0 : 1;
                return aRec - bRec;
            });
            grid.prepend(cards);

            jQuery('html, body').animate({
                scrollTop: grid.offset().top - 120
            }, 500);
        }
    }

    jQuery('#collapseChatInput').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            activeChatInput = '#collapseChatInput';
            sendChatMessage();
        }
    });

    jQuery('#collapseChatSend').on('click', function() {
        activeChatInput = '#collapseChatInput';
        sendChatMessage();
    });

    // Journey tab chat
    jQuery('#journeyChatInput').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            activeChatInput = '#journeyChatInput';
            sendChatMessage();
        }
    });

    jQuery('#journeyChatSend').on('click', function() {
        activeChatInput = '#journeyChatInput';
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

    // ===================================
    // AI CHAT COLLAPSE TOGGLE
    // ===================================
    jQuery('#chatCollapseToggle').on('click', function() {
        var panel = jQuery('.chat-collapse-panel');
        var outer = jQuery('.chat-collapse-outer');
        var icon = jQuery('#chatCollapseBtn i');

        if (!panel.hasClass('expanded')) {
            // Lock outer height before panel goes absolute
            outer.css('height', outer.outerHeight() + 'px');
            panel.addClass('expanded');
            icon.removeClass('bi-plus-lg').addClass('bi-dash-lg');
            var msgs = document.getElementById('collapseChatMessages');
            if (msgs) msgs.scrollTop = msgs.scrollHeight;
        } else {
            // First collapse messages back to 85px, then remove absolute
            panel.removeClass('expanded');
            icon.removeClass('bi-dash-lg').addClass('bi-plus-lg');
            // Wait for transition to finish, then release fixed height
            setTimeout(function() {
                outer.css('height', 'auto');
            }, 320);
        }
    });
});
</script>
@endsection
