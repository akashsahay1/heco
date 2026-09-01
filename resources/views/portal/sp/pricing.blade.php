@extends('portal.layout')
@section('title', 'Services, Rooms & Pricing - HECO Partner')

@section('content')
<div class="container py-4 heco-portal">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1"><i class="bi bi-cash-stack"></i> Services, Rooms &amp; Pricing</h4>
            <p class="text-muted small mb-0">
                Each <strong>Accommodation</strong> row is one room category (e.g. 4 Single Rooms).
                Each <strong>Transport / Guide / Activity</strong> row is one offered rate. Trip Manager
                + the AI pull these in when this property is included in a trip.
            </p>
        </div>
        <div class="d-flex gap-2">
            @if($provider->isHost())
                {{-- Rates and experiences are different things; providers look
                     for one while on the other, so link across. --}}
                <a href="{{ route('sp.experiences') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-layers"></i> My Experiences
                </a>
            @endif
            <a href="{{ route('sp.dashboard') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="card mb-4" id="spPricingCard" data-provider-id="{{ $provider->id }}">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle sp-pricing-table">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>Details</th>
                            <th>Rate</th>
                            <th>Inventory</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="spPriceBody">
                        <tr><td colspan="6" class="text-center text-muted small">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2 mt-2 flex-wrap">
                <button type="button" class="btn btn-sm sp-btn-primary" id="spPriceAdd">
                    <i class="bi bi-plus-lg me-1"></i> Add Service / Room
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" id="spMultiServiceAdd">
                    <i class="bi bi-collection me-1"></i> Add multiple services at once
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ============= DYNAMIC SERVICE-RATE MODAL ============= --}}
<div class="modal fade" id="spPriceModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-tags"></i> <span id="spPriceModalTitle">Add Service / Room</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body"><form id="spPriceForm">
        <input type="hidden" name="id">

        <div class="mb-3">
            <label class="form-label fw-bold">Service type <span class="text-danger">*</span></label>
            <select class="form-select custom-select" name="service_type" id="spServiceType" required>
                <option value="accommodation">🛏 Accommodation</option>
                <option value="transport">🚙 Transport</option>
                <option value="guide">👤 Guide</option>
                <option value="activity">🏔 Activity</option>
                <option value="rental">🎒 Rental</option>
                <option value="other">📦 Other</option>
            </select>
        </div>

        {{-- Add-mode tabs (only visible for Accommodation / Transport, hidden on Edit) --}}
        <ul class="nav nav-pills nav-fill mb-3 sp-add-mode-tabs d-none" id="spAddModeTabs" role="tablist">
            <li class="nav-item"><button type="button" class="nav-link active" data-add-mode="single"><i class="bi bi-1-square me-1"></i> Single entry</button></li>
            <li class="nav-item"><button type="button" class="nav-link" data-add-mode="bulk"><i class="bi bi-list-stars me-1"></i> Add multiple at once</button></li>
        </ul>

        {{-- ============= BULK ADD MODE ============= --}}
        <div class="add-mode-pane add-mode-bulk d-none">
            <div class="alert alert-info border small mb-3">
                <i class="bi bi-stars me-1"></i> Add multiple <span class="bulk-mode-label">room categories</span> in one go. Click <strong>+ Add another</strong> to append a row. Each row creates a separate entry.
            </div>

            {{-- Quick tier helper — pick a room category, get one row per comfort tier
                 so the SP can set prices for Cat A / B / C / D side-by-side. --}}
            <div class="quick-tier-helper bulk-accom-only card border-success border-opacity-25 bg-light p-2 mb-2 d-none">
                <div class="small fw-semibold mb-1"><i class="bi bi-lightning-charge me-1 text-success"></i> Quick: add one row for every comfort tier</div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Room Category</label>
                        <select class="form-select form-select-sm custom-select" id="quickTierRoomCategory" data-list-type="room_category">
                            <option value="">Select category...</option>
                            @foreach($roomCategories as $r)
                                <option value="{{ $r->name }}" data-desc="{{ $r->description }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn btn-sm btn-success w-100" id="quickTierGenerateBtn">
                            <i class="bi bi-plus-square me-1"></i> Generate {{ count($accommodationCategories) }} rows (one per tier)
                        </button>
                    </div>
                </div>
                <small class="text-muted d-block mt-1">Leave blank any tier you don't offer — only rows with a Rate will be saved.</small>
            </div>

            {{-- Quick room helper — the inverse of the tier helper above: pick a
                 comfort tier (e.g. Cat A) and get one row per room category so
                 the SP can add Single / Double / Twin / Triple … side-by-side
                 under that one tier. --}}
            <div class="quick-room-helper bulk-accom-only card border-success border-opacity-25 bg-light p-2 mb-2 d-none">
                <div class="small fw-semibold mb-1"><i class="bi bi-lightning-charge me-1 text-success"></i> Quick: add one row for every room type</div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Comfort Tier</label>
                        <select class="form-select form-select-sm custom-select" id="quickRoomComfortTier" data-list-type="accommodation_category">
                            <option value="">Select tier...</option>
                            @foreach($accommodationCategories as $c)
                                <option value="{{ $c->name }}" data-desc="{{ $c->description }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn btn-sm btn-success w-100" id="quickRoomGenerateBtn">
                            <i class="bi bi-plus-square me-1"></i> Generate {{ count($roomCategories) }} rows (one per room type)
                        </button>
                    </div>
                </div>
                <small class="text-muted d-block mt-1">Leave blank any room type you don't offer — only rows with a Rate will be saved.</small>
            </div>

            <div id="bulkRowsContainer"></div>
            <button type="button" class="btn btn-sm btn-outline-success mt-1" id="bulkAddRow"><i class="bi bi-plus-lg me-1"></i> Add another row</button>
        </div>

        {{-- Templates for bulk rows --}}
        <template id="bulkRowTplAccommodation">
            <div class="bulk-row card border p-2 mb-2" data-bulk-svc="accommodation">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Room Category <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="room_category" data-list-type="room_category">
                            <option value="">Pick...</option>
                            @foreach($roomCategories as $r)
                                <option value="{{ $r->name }}" data-desc="{{ $r->description }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Comfort Tier <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="comfort_tier" data-list-type="accommodation_category">
                            <option value="">Pick...</option>
                            @foreach($accommodationCategories as $c)
                                <option value="{{ $c->name }}" data-desc="{{ $c->description }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small mb-1">Total <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-sm bulk-field" data-field="total_rooms" min="1" max="500" placeholder="4">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Rate/night ₹ <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-field" data-field="price" placeholder="2500">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Meal Plan</label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="meal_plan" data-list-type="meal_plan">
                            <option value="">— none —</option>
                            @foreach($mealPlans as $m)
                                <option value="{{ $m->name }}" data-desc="{{ $m->description }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger bulk-row-remove" title="Remove this row"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </div>
        </template>

        <template id="bulkRowTplTransport">
            <div class="bulk-row card border p-2 mb-2" data-bulk-svc="transport">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Vehicle <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="vehicle_type" data-list-type="vehicle_type">
                            <option value="">Pick...</option>
                            @foreach($vehicleTypes as $v)
                                <option value="{{ $v->name }}" data-desc="{{ $v->description }}">{{ $v->name }}</option>
                            @endforeach
                        </select>
                        <small class="form-help-text text-muted d-block mt-1"></small>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small mb-1">Seats</label>
                        <input type="number" class="form-control form-control-sm bulk-field" data-field="vehicle_capacity" min="1" max="80" placeholder="7">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Rate ₹ <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-field" data-field="price" placeholder="25">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Unit <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="unit">
                            <option value="">Pick...</option>
                            @foreach($transportUnits as $u)
                                <option value="{{ $u->name }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Driver ₹/day</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-field" data-field="driver_allowance" placeholder="optional">
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger bulk-row-remove" title="Remove this row"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </div>
        </template>

        <hr class="my-3">

        <div class="add-mode-pane add-mode-single">

        {{-- ACCOMMODATION — one row per (comfort_tier × room_category) pair so
             a hotel can carry different inventory + price for Cat A Single
             (e.g. 2 rooms @ ₹4500) vs Cat A Double (3 rooms @ ₹5500). Comfort
             Tier is the primary visual axis; Room Category is a single pick
             per row. To list 4 tiers × 2 room types, the SP adds 8 rows
             (the Quick-tier helper and "All tiers" matrix make this fast). --}}
        <div class="svc-fields" data-svc="accommodation">
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-7">
                    <label class="form-label small">Comfort Tier <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="comfort_tier" data-list-type="accommodation_category">
                        <option value="">Select tier...</option>
                        @foreach($accommodationCategories as $c)
                            <option value="{{ $c->name }}" data-desc="{{ $c->description }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Room Category <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="room_category" data-list-type="room_category">
                        <option value="">Select category...</option>
                        @foreach($roomCategories as $r)
                            <option value="{{ $r->name }}" data-desc="{{ $r->description }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-5">
                    <label class="form-label small">Total Rooms <span class="text-danger">*</span></label>
                    <input type="number" min="1" max="500" class="form-control form-control-sm" name="total_rooms" placeholder="e.g. 4">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-7">
                    <label class="form-label small">Rate per night (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_accommodation" placeholder="e.g. 2500">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-12">
                    <label class="form-label small">Meal Plan</label>
                    <select class="form-select form-select-sm custom-select" name="meal_plan" data-list-type="meal_plan">
                        <option value="">— no meals (room only) —</option>
                        @foreach($mealPlans as $m)
                            <option value="{{ $m->name }}" data-desc="{{ $m->description }}">{{ $m->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            {{-- A hotel is a place before it is a rate. Optional, because a
                 provider adding a second room type has already told us once. --}}
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-4">
                    <label class="form-label small">Latitude</label>
                    <input type="number" step="0.0000001" min="-90" max="90" class="form-control form-control-sm"
                           name="latitude" placeholder="e.g. 31.6234567">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Longitude</label>
                    <input type="number" step="0.0000001" min="-180" max="180" class="form-control form-control-sm"
                           name="longitude" placeholder="e.g. 77.3456789">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Guests it sleeps</label>
                    <input type="number" min="1" max="2000" class="form-control form-control-sm"
                           name="guest_capacity" placeholder="e.g. 14">
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label small">Seasonality</label>
                <input type="text" maxlength="1000" class="form-control form-control-sm"
                       name="seasonality_notes" placeholder="e.g. Closed in January; best April to June">
            </div>
            <input type="hidden" name="unit_accommodation" value="per night">
        </div>

        {{-- TRANSPORT --}}
        <div class="svc-fields d-none" data-svc="transport">
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-7">
                    <label class="form-label small">Vehicle Type <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="vehicle_type" data-list-type="vehicle_type">
                        <option value="">Select vehicle...</option>
                        @foreach($vehicleTypes as $v)
                            <option value="{{ $v->name }}" data-desc="{{ $v->description }}">{{ $v->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Seating Capacity</label>
                    <input type="number" min="1" max="80" class="form-control form-control-sm" name="vehicle_capacity" placeholder="e.g. 7">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-5">
                    <label class="form-label small">Rate (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_transport" placeholder="e.g. 25">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Unit <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="unit_transport">
                        <option value="">Select...</option>
                        @foreach($transportUnits as $u)
                            <option value="{{ $u->name }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Driver Allowance (₹/day)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="driver_allowance" placeholder="optional">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            {{-- Which vehicle this rate is for. Optional, but it is what lets
                 HCT tell two identical Tempo Traveller rows apart. --}}
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-5">
                    <label class="form-label small">Make &amp; Model</label>
                    <input type="text" maxlength="120" class="form-control form-control-sm" name="vehicle_make_model" placeholder="e.g. Toyota Innova Crysta">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Registration No.</label>
                    <input type="text" maxlength="40" class="form-control form-control-sm" name="vehicle_registration_no" placeholder="e.g. HP 33 A 1234">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Year</label>
                    <input type="number" min="1950" max="{{ date('Y') + 1 }}" class="form-control form-control-sm" name="vehicle_year" placeholder="e.g. 2021">
                </div>
            </div>
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="driver_included" id="spDriverIncluded" value="1">
                        <label class="form-check-label small" for="spDriverIncluded">Driver included in this rate</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="fuel_tolls_extra" id="spFuelTollsExtra" value="1">
                        <label class="form-check-label small" for="spFuelTollsExtra">Fuel &amp; tolls billed separately</label>
                    </div>
                </div>
            </div>

            {{-- A hill kilometre costs more to drive than a plains one, so the
                 two are quoted separately. The plains rate is what a per-km
                 booking is billed at unless the route says otherwise. --}}
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-4">
                    <label class="form-label small">Cost per km — plains (₹)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                           name="price_per_km_plains" placeholder="e.g. 14.50">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Cost per km — hills (₹)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                           name="price_per_km_hills" placeholder="e.g. 22">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Number of vehicles</label>
                    <input type="number" min="1" max="500" class="form-control form-control-sm"
                           name="vehicle_count" placeholder="e.g. 3">
                </div>
            </div>
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="ac_available" id="spAcAvailable" value="1">
                        <label class="form-check-label small" for="spAcAvailable">Air conditioning available</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Extra cost for AC (₹)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                           name="ac_extra_cost" placeholder="optional">
                </div>
            </div>
        </div>

        {{-- GUIDE --}}
        <div class="svc-fields d-none" data-svc="guide">
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-7">
                    <label class="form-label small">Guide Type / Language <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="category_guide" data-list-type="guide_preference">
                        <option value="">Select guide type...</option>
                        @foreach($guideTypes as $g)
                            <option value="{{ $g->name }}" data-desc="{{ $g->description }}">{{ $g->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Rate per day (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_guide" placeholder="e.g. 3000">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label small">Specialties (optional)</label>
                <input type="text" class="form-control form-control-sm" name="specialties_guide" placeholder="e.g. Bird-watching, Hindi + English, IMF-certified">
            </div>
            {{-- The day rate above is for one day. A booking where the guide
                 stays the night is a rate of its own, not a multiple of it. --}}
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-6">
                    <label class="form-label small">Rate per day — multi-day with night stay (₹)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                           name="wage_multi_day" placeholder="optional">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Other languages</label>
                    <select class="form-select form-select-sm" name="languages[]" id="spGuideLanguages" multiple size="4">
                        @foreach($languages as $l)
                            <option value="{{ $l->name }}">{{ $l->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted d-block mt-1">Hold Ctrl (or ⌘) to pick more than one.</small>
                </div>
            </div>
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="speaks_english" id="spGuideEnglish" value="1">
                        <label class="form-check-label small" for="spGuideEnglish">Speaks English</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_certified" id="spGuideCertified" value="1">
                        <label class="form-check-label small" for="spGuideCertified">Certified guide</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="has_first_aid" id="spGuideFirstAid" value="1">
                        <label class="form-check-label small" for="spGuideFirstAid">First-aid trained</label>
                    </div>
                </div>
            </div>
            <input type="hidden" name="unit_guide" value="per day">
        </div>

        {{-- RENTAL --}}
        <div class="svc-fields d-none" data-svc="rental">
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-7">
                    <label class="form-label small">Item on rent <span class="text-danger">*</span></label>
                    <input type="text" maxlength="150" class="form-control form-control-sm"
                           name="rental_item" placeholder="e.g. Trekking tent (2 person), Mountain bike">
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Charges per day (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                           name="price_rental" placeholder="e.g. 400">
                </div>
            </div>
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-5">
                    {{-- Held against damage and returned, so it is not income. --}}
                    <label class="form-label small">Security deposit (₹)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                           name="security_deposit" placeholder="optional">
                </div>
                <div class="col-md-7">
                    <label class="form-label small">Other details</label>
                    <input type="text" class="form-control form-control-sm"
                           name="notes_rental" placeholder="e.g. Sizes S–XL, returned by 6 pm">
                </div>
            </div>
            <input type="hidden" name="unit_rental" value="per day">
        </div>

        {{-- ACTIVITY --}}
        <div class="svc-fields d-none" data-svc="activity">
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-7">
                    <label class="form-label small">Activity Type <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="category_activity" data-list-type="activity_type">
                        <option value="">Select activity...</option>
                        @foreach($activityTypes as $a)
                            <option value="{{ $a->name }}" data-desc="{{ $a->description }}">{{ $a->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Rate (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_activity" placeholder="e.g. 800">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-4">
                    <label class="form-label small">Unit <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="unit_activity">
                        <option value="">Select...</option>
                        @foreach($activityUnits as $u)
                            <option value="{{ $u->name }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Min group size</label>
                    <input type="number" min="1" max="500" class="form-control form-control-sm" name="min_group" placeholder="e.g. 2">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Max group size</label>
                    <input type="number" min="1" max="500" class="form-control form-control-sm" name="max_group" placeholder="e.g. 12">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label small">Specialties (optional)</label>
                <input type="text" class="form-control form-control-sm" name="specialties_activity" placeholder="e.g. Equipment included, certified instructor required">
            </div>
        </div>

        {{-- OTHER --}}
        <div class="svc-fields d-none" data-svc="other">
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-7">
                    <label class="form-label small">Service Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="category_other" placeholder="e.g. Permit fee, Camera fee, Equipment rental">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Rate (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_other" placeholder="e.g. 500">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label small">Unit <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="unit_other" placeholder="e.g. per item, per day, per trip">
            </div>
        </div>

        </div> {{-- /.add-mode-single --}}

        <hr class="my-3">

        <div class="add-mode-single-only">
        <div class="mb-2">
            <label class="form-label small">Internal note (optional)</label>
            <input type="text" class="form-control form-control-sm" name="description" placeholder="e.g. 'Off-season rate', 'Mountain-view rooms only'">
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="spPriceActive">
            <label class="form-check-label small" for="spPriceActive">Active — visible to Trip Manager &amp; AI suggestions</label>
        </div>
        </div> {{-- /.add-mode-single-only --}}

        <button type="submit" class="btn sp-btn-primary w-100" id="spPriceSaveBtn"><i class="bi bi-check-lg me-1"></i> Save</button>
    </form></div>
</div></div></div>

{{-- ============= MULTI-SERVICE-TYPE MODAL =============
     Tick the service types the provider offers, fill in one row of
     fields per ticked type. Save All creates one sp_pricing row per
     ticked section, each going through the normal approval workflow. --}}
<div class="modal fade" id="spMultiServiceModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-collection"></i> Add multiple services at once</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body"><form id="spMultiForm">
        <p class="small text-muted mb-3">Tick the service types this property / provider offers. Within each ticked section you can add as many rows as you need — each row becomes one rate entry and goes through the usual admin approval flow.</p>

        {{-- All sections share the same structure now: header checkbox, a
             rows container (.multi-svc-rows), and an "Add another" button.
             JS clones the per-service-type <template> at the bottom of this
             form into the container when a section is ticked OR the user
             clicks Add another. --}}
        @php
            $sections = [
                ['svc' => 'accommodation', 'id' => 'multiSvcAccom',    'label' => '🛏 Accommodation',                         'addLabel' => 'Add another room'],
                ['svc' => 'transport',     'id' => 'multiSvcTransport','label' => '🚙 Transport',                              'addLabel' => 'Add another vehicle'],
                ['svc' => 'guide',         'id' => 'multiSvcGuide',    'label' => '👤 Guide',                                  'addLabel' => 'Add another guide'],
                ['svc' => 'activity',      'id' => 'multiSvcActivity', 'label' => '🏔 Activity',                               'addLabel' => 'Add another activity'],
                ['svc' => 'other',         'id' => 'multiSvcOther',    'label' => '📦 Other (permits, fees, equipment, etc.)', 'addLabel' => 'Add another item'],
            ];
        @endphp
        @foreach($sections as $s)
            <div class="multi-svc-section card border mb-2" data-svc="{{ $s['svc'] }}">
                <div class="card-header py-2 d-flex align-items-center">
                    <div class="form-check m-0">
                        <input class="form-check-input multi-svc-toggle" type="checkbox" id="{{ $s['id'] }}" data-svc="{{ $s['svc'] }}">
                        <label class="form-check-label fw-semibold small" for="{{ $s['id'] }}">{{ $s['label'] }}</label>
                    </div>
                </div>
                <div class="card-body multi-svc-body d-none">
                    <div class="multi-svc-rows mb-2" data-svc="{{ $s['svc'] }}"></div>
                    <button type="button" class="btn btn-sm btn-outline-success multi-svc-add-row" data-svc="{{ $s['svc'] }}">
                        <i class="bi bi-plus-lg me-1"></i> {{ $s['addLabel'] }}
                    </button>
                </div>
            </div>
        @endforeach

        {{-- Row templates — Blade renders the options server-side, JS clones
             a fresh copy whenever the user adds a row. --}}
        <template id="multiSvcRowTpl-accommodation">
            <div class="bulk-row card border p-2 mb-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Comfort Tier <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="comfort_tier" data-list-type="accommodation_category">
                            <option value="">Pick...</option>
                            @foreach($accommodationCategories as $c)
                                <option value="{{ $c->name }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Room Category <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="room_category" data-list-type="room_category">
                            <option value="">Pick...</option>
                            @foreach($roomCategories as $r)
                                <option value="{{ $r->name }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small mb-1">Total <span class="text-danger">*</span></label>
                        <input type="number" min="1" max="500" class="form-control form-control-sm bulk-field" data-field="total_rooms" placeholder="4">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Rate/night ₹ <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-field" data-field="price" placeholder="2500">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Meal Plan</label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="meal_plan" data-list-type="meal_plan">
                            <option value="">— none —</option>
                            @foreach($mealPlans as $m)
                                <option value="{{ $m->name }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger multi-svc-row-remove" title="Remove this row"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </div>
        </template>

        <template id="multiSvcRowTpl-transport">
            <div class="bulk-row card border p-2 mb-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Vehicle Type <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="vehicle_type" data-list-type="vehicle_type">
                            <option value="">Pick...</option>
                            @foreach($vehicleTypes as $v)
                                <option value="{{ $v->name }}">{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small mb-1">Seats</label>
                        <input type="number" min="1" max="80" class="form-control form-control-sm bulk-field" data-field="vehicle_capacity" placeholder="7">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Rate ₹ <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-field" data-field="price" placeholder="25">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Unit <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="unit">
                            <option value="">Pick...</option>
                            @foreach($transportUnits as $u)
                                <option value="{{ $u->name }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Driver ₹/day</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-field" data-field="driver_allowance" placeholder="optional">
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger multi-svc-row-remove" title="Remove this row"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </div>
        </template>

        <template id="multiSvcRowTpl-guide">
            <div class="bulk-row card border p-2 mb-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Guide Type / Language <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="category" data-list-type="guide_preference">
                            <option value="">Pick...</option>
                            @foreach($guideTypes as $g)
                                <option value="{{ $g->name }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Rate/day ₹ <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-field" data-field="price" placeholder="3000">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Specialties</label>
                        <input type="text" class="form-control form-control-sm bulk-field" data-field="specialties" placeholder="optional — e.g. Bird-watching, Hindi + English">
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger multi-svc-row-remove" title="Remove this row"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </div>
        </template>

        <template id="multiSvcRowTpl-activity">
            <div class="bulk-row card border p-2 mb-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Activity Type <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="category" data-list-type="activity_type">
                            <option value="">Pick...</option>
                            @foreach($activityTypes as $a)
                                <option value="{{ $a->name }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Rate ₹ <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-field" data-field="price" placeholder="800">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Unit <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="unit">
                            <option value="">Pick...</option>
                            @foreach($activityUnits as $u)
                                <option value="{{ $u->name }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger multi-svc-row-remove" title="Remove this row"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </div>
        </template>

        <template id="multiSvcRowTpl-other">
            <div class="bulk-row card border p-2 mb-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Service Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm bulk-field" data-field="category" placeholder="e.g. Permit fee">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Rate ₹ <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-field" data-field="price" placeholder="500">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Unit <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm bulk-field" data-field="unit" placeholder="per item / per day / per trip">
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger multi-svc-row-remove" title="Remove this row"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </div>
        </template>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-sm sp-btn-primary" id="spMultiSaveBtn">
                <i class="bi bi-check-lg me-1"></i> Save All Selected
            </button>
        </div>
    </form></div>
</div></div></div>
@endsection

@section('js')
<script>
jQuery(function() {
    var providerId = jQuery('#spPricingCard').data('provider-id');
    var priceCache = {};
    var typeIcons = { accommodation: '🛏', transport: '🚙', guide: '👤', activity: '🏔', other: '📦' };

    function spEscape(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function loadPricing() {
        jQuery('#spPriceBody').html('<tr><td colspan="6" class="text-center text-muted small">Loading...</td></tr>');
        ajaxPost({ get_sp_pricing: 1, provider_id: providerId }, function(resp) {
            var rows = resp.rows || [];
            priceCache = {};
            if (!rows.length) {
                jQuery('#spPriceBody').html('<tr><td colspan="6" class="text-center text-muted small">No rates yet. Click <strong>Add Service / Room</strong> to set up the first one.</td></tr>');
                return;
            }
            // Count (comfort_tier × room_category) on active accommodation rows
            // so we can flag duplicates. The same (tier, room) pair on two
            // active rows is a mistake — different rooms at the same tier
            // should each have their own row (and that's fine), but two rows
            // for the same room at the same tier means the SP saved twice.
            var pairCounts = {};
            rows.forEach(function(r) {
                if (r.service_type !== 'accommodation' || !r.is_active || !r.comfort_tier) return;
                var key = r.comfort_tier + '|' + (r.room_category || r.category || '');
                pairCounts[key] = (pairCounts[key] || 0) + 1;
            });
            // ── Cell builders shared by grouped + flat rows ──
            function statusBadge(r) {
                if (r.approval_status === 'pending') {
                    var label = r.pending_for_id ? 'pending edit' : 'pending review';
                    return '<span class="badge bg-warning text-dark ms-1" title="Awaiting HCT admin approval — not yet visible to travellers"><i class="bi bi-hourglass-split me-1"></i>' + label + '</span>';
                }
                if (r.approval_status === 'rejected') {
                    var rejTitle = r.rejection_reason ? 'Rejected: ' + r.rejection_reason : 'Rejected by admin';
                    return '<span class="badge bg-danger ms-1" title="' + spEscape(rejTitle) + '"><i class="bi bi-x-circle me-1"></i>rejected</span>';
                }
                return '';
            }
            function rateCell(r) {
                return '&#8377;' + Number(r.price).toLocaleString('en-IN') + ' <span class="text-muted fw-normal small">' + spEscape(r.unit || '') + '</span>';
            }
            function inventoryCell(r) {
                if (r.service_type === 'accommodation' && r.total_rooms) {
                    return '<span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-door-closed"></i> ' + r.total_rooms + ' rooms</span>';
                }
                return '—';
            }
            function activeCell(r) {
                return r.is_active ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>';
            }
            function actionsCell(r) {
                var h = '';
                if (r.service_type === 'accommodation' && r.room_category) {
                    h += '  <button class="btn btn-sm btn-outline-success sp-price-tier-matrix me-1" title="Set prices for all tiers of this room"><i class="bi bi-grid-3x3-gap"></i></button>';
                }
                h += '  <button class="btn btn-sm btn-outline-primary sp-price-edit me-1" title="Edit this row only"><i class="bi bi-pencil"></i></button>';
                h += '  <button class="btn btn-sm btn-outline-danger sp-price-delete" title="Delete"><i class="bi bi-trash"></i></button>';
                return h;
            }
            // Details cell. For grouped accommodation children, hideTier=true so
            // the tier (already in the group header) is dropped and the Room
            // Category becomes the primary label.
            function detailsCell(r, hideTier) {
                var parts = [];
                if (r.service_type === 'accommodation') {
                    var pairKey = (r.comfort_tier || '') + '|' + (r.room_category || r.category || '');
                    var isDupTier = r.comfort_tier && pairCounts[pairKey] > 1;
                    var rooms = r.room_category || r.category || '';
                    if (!hideTier && r.comfort_tier) parts.push('<strong>' + spEscape(r.comfort_tier) + '</strong>');
                    if (rooms) parts.push(hideTier
                        ? '<strong>' + spEscape(rooms) + '</strong>'
                        : '<span class="text-muted small">' + spEscape(rooms) + '</span>');
                    if (isDupTier) parts.push('<span class="badge bg-warning text-dark" title="This tier + room combination appears on more than one row. Keep one row per (tier, room) pair."><i class="bi bi-exclamation-triangle me-1"></i>duplicate</span>');
                    if (r.meal_plan) parts.push('<span class="badge bg-light text-dark border">' + spEscape(r.meal_plan) + '</span>');
                } else if (r.service_type === 'transport') {
                    if (r.vehicle_type) parts.push('<strong>' + spEscape(r.vehicle_type) + '</strong>');
                    if (r.vehicle_capacity) parts.push('<small class="text-muted">' + r.vehicle_capacity + ' seats</small>');
                    if (r.driver_allowance) parts.push('<small class="text-muted">+ ₹' + Number(r.driver_allowance).toLocaleString('en-IN') + ' driver/day</small>');
                } else if (r.service_type === 'guide') {
                    if (r.category) parts.push('<strong>' + spEscape(r.category) + '</strong>');
                    if (r.specialties) parts.push('<small class="text-muted">' + spEscape(r.specialties) + '</small>');
                } else if (r.service_type === 'activity') {
                    if (r.category) parts.push('<strong>' + spEscape(r.category) + '</strong>');
                    if (r.min_group || r.max_group) parts.push('<small class="text-muted">' + ((r.min_group || '?') + '–' + (r.max_group || '?') + ' pax') + '</small>');
                    if (r.specialties) parts.push('<small class="text-muted">' + spEscape(r.specialties) + '</small>');
                } else {
                    parts.push('<strong>' + spEscape(r.category || '—') + '</strong>');
                }
                var d = parts.join(' · ');
                if (r.description) d += '<div class="small text-muted">' + spEscape(r.description) + '</div>';
                return d;
            }
            // Flat <tr> for non-accommodation service types (unchanged layout).
            function flatRow(r) {
                return '<tr data-id="' + r.id + '">'
                    + '<td><span class="sp-type-badge">' + (typeIcons[r.service_type] || '·') + ' <span class="text-capitalize">' + spEscape(r.service_type) + '</span></span>' + statusBadge(r) + '</td>'
                    + '<td>' + detailsCell(r, false) + '</td>'
                    + '<td class="small fw-bold">' + rateCell(r) + '</td>'
                    + '<td>' + inventoryCell(r) + '</td>'
                    + '<td>' + activeCell(r) + '</td>'
                    + '<td>' + actionsCell(r) + '</td>'
                    + '</tr>';
            }

            // Split accommodation (grouped by comfort tier) from everything else.
            var accomRows = [], otherRows = [];
            rows.forEach(function(r) {
                priceCache[r.id] = r;
                (r.service_type === 'accommodation' ? accomRows : otherRows).push(r);
            });

            var html = '';

            // ── Accommodation → collapsible group per comfort tier ──
            // Each tier is a clickable header row; its Single/Double/Triple…
            // room rows sit hidden underneath until the SP clicks to expand.
            if (accomRows.length) {
                var tierOrder = {};
                allComfortTiers.forEach(function(t, i) { tierOrder[t] = i; });
                var groups = {};
                accomRows.forEach(function(r) {
                    var t = r.comfort_tier || 'Other';
                    (groups[t] = groups[t] || []).push(r);
                });
                Object.keys(groups).sort(function(a, b) {
                    var ai = tierOrder[a] == null ? 999 : tierOrder[a];
                    var bi = tierOrder[b] == null ? 999 : tierOrder[b];
                    return ai - bi;
                }).forEach(function(tier) {
                    var gRows = groups[tier];
                    var totalRooms = gRows.reduce(function(s, r) { return s + (parseInt(r.total_rooms, 10) || 0); }, 0);
                    var gid = 'tier-' + tier.replace(/[^A-Za-z0-9]+/g, '-');
                    var anyPending = gRows.some(function(r) { return r.approval_status === 'pending'; });

                    html += '<tr class="sp-tier-group table-light" data-tier-group="' + spEscape(gid) + '" style="cursor:pointer;">';
                    html += '<td colspan="6">'
                        + '<i class="bi bi-chevron-right sp-tier-caret me-1"></i>'
                        + '<span class="sp-type-badge">🛏</span> '
                        + '<strong>' + spEscape(tier) + '</strong> '
                        + '<span class="text-muted small">— ' + gRows.length + ' room type' + (gRows.length > 1 ? 's' : '') + ' · ' + totalRooms + ' rooms total</span>'
                        + (anyPending ? ' <span class="badge bg-warning text-dark ms-1"><i class="bi bi-hourglass-split me-1"></i>has pending</span>' : '')
                        + '</td>';
                    html += '</tr>';

                    gRows.forEach(function(r) {
                        html += '<tr class="sp-tier-child d-none" data-tier-group="' + spEscape(gid) + '" data-id="' + r.id + '">';
                        html += '<td class="ps-4"><i class="bi bi-arrow-return-right text-muted me-1"></i>' + statusBadge(r) + '</td>';
                        html += '<td>' + detailsCell(r, true) + '</td>';
                        html += '<td class="small fw-bold">' + rateCell(r) + '</td>';
                        html += '<td>' + inventoryCell(r) + '</td>';
                        html += '<td>' + activeCell(r) + '</td>';
                        html += '<td>' + actionsCell(r) + '</td>';
                        html += '</tr>';
                    });
                });
            }

            // ── Other service types render flat, below the tier groups ──
            otherRows.forEach(function(r) { html += flatRow(r); });

            jQuery('#spPriceBody').html(html);
        });
    }

    // Expand / collapse a comfort-tier group: clicking the header row toggles
    // its child room rows and flips the chevron.
    jQuery(document).on('click', '.sp-tier-group', function() {
        var gid = jQuery(this).data('tier-group');
        var $children = jQuery('.sp-tier-child[data-tier-group="' + gid + '"]');
        var willOpen = $children.first().hasClass('d-none');
        $children.toggleClass('d-none', !willOpen);
        jQuery(this).find('.sp-tier-caret')
            .toggleClass('bi-chevron-down', willOpen)
            .toggleClass('bi-chevron-right', !willOpen);
    });

    function showServiceFields(type) {
        jQuery('.svc-fields').addClass('d-none');
        jQuery('.svc-fields[data-svc="' + type + '"]').removeClass('d-none');
        jQuery('select[data-list-type]').each(function() { jQuery(this).trigger('change'); });
        var label = jQuery('#spServiceType option:selected').text().replace(/^[^A-Za-z]+/, '');
        jQuery('#spPriceModalTitle').text(jQuery('#spPriceForm [name=id]').val() ? 'Edit: ' + label : 'Add: ' + label);
    }
    jQuery('#spServiceType').on('change', function() { showServiceFields(this.value); });

    function fillPriceForm(r) {
        var $f = jQuery('#spPriceForm');
        $f[0].reset();
        $f.find('[name=id]').val(r ? r.id : '');
        var t = r ? r.service_type : 'accommodation';
        $f.find('[name=service_type]').val(t);
        $f.find('[name=description]').val(r ? (r.description || '') : '');
        $f.find('[name=is_active]').prop('checked', r ? !!r.is_active : true);

        // For legacy data where room_category may carry a comma-list, pick the
        // first value so the single-select dropdown has a valid match.
        var roomCatStr = r ? (r.room_category || r.category || '') : '';
        var firstCat = roomCatStr.split(',')[0].trim();
        $f.find('[name=room_category]').val(firstCat);
        $f.find('[name=comfort_tier]').val(r ? (r.comfort_tier || '') : '');
        $f.find('[name=total_rooms]').val(r ? (r.total_rooms || '') : '');
        $f.find('[name=meal_plan]').val(r ? (r.meal_plan || '') : '');
        $f.find('[name=price_accommodation]').val(r && r.service_type === 'accommodation' ? r.price : '');

        $f.find('[name=vehicle_type]').val(r ? (r.vehicle_type || '') : '');
        $f.find('[name=vehicle_capacity]').val(r ? (r.vehicle_capacity || '') : '');
        $f.find('[name=driver_allowance]').val(r ? (r.driver_allowance || '') : '');
        $f.find('[name=price_transport]').val(r && r.service_type === 'transport' ? r.price : '');
        $f.find('[name=unit_transport]').val(r && r.service_type === 'transport' ? (r.unit || '') : '');
        $f.find('[name=vehicle_make_model]').val(r ? (r.vehicle_make_model || '') : '');
        $f.find('[name=vehicle_registration_no]').val(r ? (r.vehicle_registration_no || '') : '');
        $f.find('[name=vehicle_year]').val(r ? (r.vehicle_year || '') : '');
        $f.find('[name=driver_included]').prop('checked', r ? !!r.driver_included : false);
        $f.find('[name=fuel_tolls_extra]').prop('checked', r ? !!r.fuel_tolls_extra : false);

        $f.find('[name=category_guide]').val(r && r.service_type === 'guide' ? (r.category || '') : '');
        $f.find('[name=specialties_guide]').val(r && r.service_type === 'guide' ? (r.specialties || '') : '');
        $f.find('[name=price_guide]').val(r && r.service_type === 'guide' ? r.price : '');

        $f.find('[name=category_activity]').val(r && r.service_type === 'activity' ? (r.category || '') : '');
        $f.find('[name=min_group]').val(r ? (r.min_group || '') : '');
        $f.find('[name=max_group]').val(r ? (r.max_group || '') : '');
        $f.find('[name=specialties_activity]').val(r && r.service_type === 'activity' ? (r.specialties || '') : '');
        $f.find('[name=price_activity]').val(r && r.service_type === 'activity' ? r.price : '');
        $f.find('[name=unit_activity]').val(r && r.service_type === 'activity' ? (r.unit || '') : '');

        // Where the property stands and when it is open.
        $f.find('[name=latitude]').val(r ? (r.latitude || '') : '');
        $f.find('[name=longitude]').val(r ? (r.longitude || '') : '');
        $f.find('[name=guest_capacity]').val(r ? (r.guest_capacity || '') : '');
        $f.find('[name=seasonality_notes]').val(r ? (r.seasonality_notes || '') : '');

        // The taxi's two per-km rates and its fleet.
        $f.find('[name=price_per_km_plains]').val(r ? (r.price_per_km_plains || '') : '');
        $f.find('[name=price_per_km_hills]').val(r ? (r.price_per_km_hills || '') : '');
        $f.find('[name=vehicle_count]').val(r ? (r.vehicle_count || '') : '');
        $f.find('[name=ac_extra_cost]').val(r ? (r.ac_extra_cost || '') : '');
        $f.find('[name=ac_available]').prop('checked', r ? !!r.ac_available : false);

        // The guide's languages and qualifications. `languages` arrives as an
        // array from the JSON cast, so a missing one has to become one here.
        $f.find('[name="languages[]"]').val(r && Array.isArray(r.languages) ? r.languages : []);
        $f.find('[name=wage_multi_day]').val(r ? (r.wage_multi_day || '') : '');
        $f.find('[name=speaks_english]').prop('checked', r ? !!r.speaks_english : false);
        $f.find('[name=is_certified]').prop('checked', r ? !!r.is_certified : false);
        $f.find('[name=has_first_aid]').prop('checked', r ? !!r.has_first_aid : false);

        $f.find('[name=rental_item]').val(r ? (r.rental_item || '') : '');
        $f.find('[name=security_deposit]').val(r ? (r.security_deposit || '') : '');
        $f.find('[name=price_rental]').val(r && r.service_type === 'rental' ? r.price : '');
        $f.find('[name=notes_rental]').val(r && r.service_type === 'rental' ? (r.notes || '') : '');

        $f.find('[name=category_other]').val(r && r.service_type === 'other' ? (r.category || '') : '');
        $f.find('[name=price_other]').val(r && r.service_type === 'other' ? r.price : '');
        $f.find('[name=unit_other]').val(r && r.service_type === 'other' ? (r.unit || '') : '');

        // Refresh searchable dropdowns so the visible label reflects the new
        // underlying value (custom-select only updates its label on click /
        // initial build, not on programmatic .val()). Also fire change so any
        // help-text / dependent listeners pick up the new value.
        $f.find('.custom-select').each(function() {
            if (window.buildCustomDropdown) window.buildCustomDropdown(this);
            jQuery(this).trigger('change');
        });

        showServiceFields(t);
    }

    // ─── Bulk Add mode (Accommodation + Transport only, Add not Edit) ───
    var bulkEligibleTypes = ['accommodation', 'transport'];
    var currentAddMode = 'single';

    function setAddMode(mode) {
        currentAddMode = mode;
        jQuery('#spAddModeTabs .nav-link').removeClass('active').filter('[data-add-mode="' + mode + '"]').addClass('active');
        jQuery('.add-mode-bulk').toggleClass('d-none', mode !== 'bulk');
        jQuery('.add-mode-single, .add-mode-single-only').toggleClass('d-none', mode !== 'single');
        var $btn = jQuery('#spPriceSaveBtn');
        $btn.html(mode === 'bulk' ? '<i class="bi bi-check-lg me-1"></i> Save All Rows' : '<i class="bi bi-check-lg me-1"></i> Save');
    }

    function refreshBulkVisibility(serviceType, isEdit) {
        var eligible = bulkEligibleTypes.indexOf(serviceType) !== -1 && !isEdit;
        jQuery('#spAddModeTabs').toggleClass('d-none', !eligible);
        if (!eligible && currentAddMode === 'bulk') setAddMode('single');
        jQuery('.bulk-mode-label').text(serviceType === 'transport' ? 'vehicle types' : 'room categories');
        // Quick-tier helper only applies to accommodation
        jQuery('.bulk-accom-only').toggleClass('d-none', serviceType !== 'accommodation');
    }

    function addBulkRow(serviceType) {
        var tplId = serviceType === 'transport' ? '#bulkRowTplTransport' : '#bulkRowTplAccommodation';
        var $tpl = jQuery(tplId);
        if (!$tpl.length) return;
        jQuery('#bulkRowsContainer').append(jQuery($tpl[0].content.cloneNode(true)));
        jQuery('#bulkRowsContainer .bulk-field[data-list-type]').each(function() {
            if (!jQuery(this).closest('.custom-select-wrap').length) buildCustomDropdown(this);
        });
    }

    function resetBulkRows(serviceType) {
        jQuery('#bulkRowsContainer').empty();
        addBulkRow(serviceType);
    }

    function collectBulkRows(serviceType) {
        var rows = [];
        jQuery('#bulkRowsContainer .bulk-row').each(function() {
            var $r = jQuery(this);
            var row = { service_type: serviceType };
            // If this row was generated from an existing sp_pricing record,
            // carry its id so the save endpoint UPDATEs instead of creating
            // a duplicate (used by the "All tiers" matrix on Edit).
            var existingId = $r.attr('data-row-id');
            if (existingId) row.id = existingId;
            $r.find('.bulk-field').each(function() {
                var field = jQuery(this).data('field');
                var val = jQuery(this).val();
                if (val !== null && val !== '') row[field] = val;
            });
            // Skip rows the SP left blank with no existing record. Existing
            // rows with a cleared price are left untouched (use the trash
            // icon on the list to delete them).
            if (!row.price) return;
            if (Object.keys(row).filter(function(k) { return k !== 'service_type'; }).length) rows.push(row);
        });
        return rows;
    }

    jQuery('#spAddModeTabs').on('click', '.nav-link', function() {
        var mode = jQuery(this).data('add-mode');
        setAddMode(mode);
        if (mode === 'bulk') resetBulkRows(jQuery('#spServiceType').val());
    });

    jQuery('#bulkAddRow').on('click', function() { addBulkRow(jQuery('#spServiceType').val()); });

    // Quick-tier helper: pre-fill one row per comfort tier (Cat A / B / C / D)
    // for a chosen room category. SP only has to type Rate + Total for each.
    var allComfortTiers = @json($accommodationCategories->pluck('name')->values());
    jQuery('#quickTierGenerateBtn').on('click', function() {
        var roomCat = jQuery('#quickTierRoomCategory').val();
        if (!roomCat) {
            window.showError && window.showError('Pick a Room Category first');
            return;
        }
        // Wipe existing rows so the matrix is clean; SP can still remove or add more.
        jQuery('#bulkRowsContainer').empty();
        allComfortTiers.forEach(function(tier) {
            addBulkRow('accommodation');
            var $row = jQuery('#bulkRowsContainer .bulk-row').last();
            $row.find('.bulk-field[data-field=room_category]').val(roomCat);
            $row.find('.bulk-field[data-field=comfort_tier]').val(tier);
        });
        // Refresh dropdowns so labels reflect the pre-set values.
        jQuery('#bulkRowsContainer .custom-select').each(function() {
            if (window.buildCustomDropdown) window.buildCustomDropdown(this);
            jQuery(this).trigger('change');
        });
    });

    // Quick-room helper: the inverse of the tier helper above. Pick a comfort
    // tier (e.g. Cat A) and pre-fill one row per room category (Single, Double,
    // Triple …) so the SP only has to type Total + Rate for each room type.
    var allRoomCategories = @json($roomCategories->pluck('name')->values());
    jQuery('#quickRoomGenerateBtn').on('click', function() {
        var tier = jQuery('#quickRoomComfortTier').val();
        if (!tier) {
            window.showError && window.showError('Pick a Comfort Tier first');
            return;
        }
        // Wipe existing rows so the matrix is clean; SP can still remove or add more.
        jQuery('#bulkRowsContainer').empty();
        allRoomCategories.forEach(function(roomCat) {
            addBulkRow('accommodation');
            var $row = jQuery('#bulkRowsContainer .bulk-row').last();
            $row.find('.bulk-field[data-field=comfort_tier]').val(tier);
            $row.find('.bulk-field[data-field=room_category]').val(roomCat);
        });
        // Refresh dropdowns so labels reflect the pre-set values.
        jQuery('#bulkRowsContainer .custom-select').each(function() {
            if (window.buildCustomDropdown) window.buildCustomDropdown(this);
            jQuery(this).trigger('change');
        });
    });

    jQuery(document).on('click', '.bulk-row-remove', function() {
        var $rows = jQuery('#bulkRowsContainer .bulk-row');
        if ($rows.length <= 1) {
            jQuery(this).closest('.bulk-row').find('.bulk-field').val('');
        } else {
            jQuery(this).closest('.bulk-row').remove();
        }
    });

    jQuery('#spServiceType').on('change', function() {
        var t = this.value;
        var isEdit = !!jQuery('#spPriceForm [name=id]').val();
        refreshBulkVisibility(t, isEdit);
        if (currentAddMode === 'bulk') resetBulkRows(t);
    });

    jQuery('#spPriceAdd').on('click', function() {
        fillPriceForm(null);
        setAddMode('single');
        refreshBulkVisibility(jQuery('#spServiceType').val(), false);
        new bootstrap.Modal(jQuery('#spPriceModal')[0]).show();
    });

    // ─── Multi-service-type "add many in one go" flow ─────────────────────
    function resetMultiServiceModal() {
        jQuery('#spMultiForm .multi-svc-toggle').prop('checked', false);
        jQuery('#spMultiForm .multi-svc-body').addClass('d-none');
        jQuery('#spMultiForm .multi-svc-rows').empty();
    }

    function addMultiSvcRow(svc) {
        // jQuery('#x')[0] bridges to the raw DOM element so we can access
        // <template>.content — the project's coding-rules-approved pattern
        // for the rare cases where the API needs a DOM node.
        var tpl = jQuery('#multiSvcRowTpl-' + svc)[0];
        if (!tpl) return;
        var $container = jQuery('#spMultiForm .multi-svc-rows[data-svc="' + svc + '"]');
        var $frag = jQuery(tpl.content.cloneNode(true));
        $container.append($frag);
        // Wire custom-selects on the freshly cloned row
        $container.find('.bulk-row:last .custom-select').each(function() {
            if (!jQuery(this).closest('.custom-select-wrap').length && window.buildCustomDropdown) {
                window.buildCustomDropdown(this);
            }
        });
    }

    jQuery('#spMultiServiceAdd').on('click', function() {
        resetMultiServiceModal();
        new bootstrap.Modal(jQuery('#spMultiServiceModal')[0]).show();
    });

    // Toggle a section open/closed when its checkbox flips
    jQuery(document).on('change', '.multi-svc-toggle', function() {
        var svc = jQuery(this).data('svc');
        var $section = jQuery(this).closest('.multi-svc-section');
        $section.find('.multi-svc-body').toggleClass('d-none', !this.checked);
        if (this.checked) {
            // First time opening this section → seed one empty row
            var $rows = $section.find('.multi-svc-rows');
            if ($rows.children('.bulk-row').length === 0) {
                addMultiSvcRow(svc);
            }
        } else {
            // Closing the section clears its rows so a second open starts fresh
            $section.find('.multi-svc-rows').empty();
        }
    });

    // "+ Add another" within a section
    jQuery(document).on('click', '.multi-svc-add-row', function() {
        addMultiSvcRow(jQuery(this).data('svc'));
    });

    // Per-row remove — keep at least one row open inside a ticked section
    // (clearing values rather than removing) so the form structure stays sane.
    jQuery(document).on('click', '.multi-svc-row-remove', function() {
        var $row = jQuery(this).closest('.bulk-row');
        var $container = $row.parent();
        if ($container.children('.bulk-row').length > 1) {
            $row.remove();
        } else {
            $row.find('input').val('');
            $row.find('select').val('').each(function() {
                if (window.buildCustomDropdown) window.buildCustomDropdown(this);
            });
        }
    });

    function collectMultiServicePayloads() {
        var payloads = [];
        var errors = [];
        jQuery('#spMultiForm .multi-svc-section').each(function() {
            var $section = jQuery(this);
            if (!$section.find('.multi-svc-toggle').is(':checked')) return;
            var svc = $section.data('svc');
            var rowIdx = 0;
            $section.find('.multi-svc-rows .bulk-row').each(function() {
                rowIdx++;
                var row = { service_type: svc };
                jQuery(this).find('.bulk-field').each(function() {
                    var field = jQuery(this).data('field');
                    var val = jQuery(this).val();
                    if (val !== null && val !== '') row[field] = val;
                });
                // Per-type validation
                var required = {
                    accommodation: ['comfort_tier', 'room_category', 'total_rooms', 'price'],
                    transport:     ['vehicle_type', 'price', 'unit'],
                    guide:         ['category', 'price'],
                    activity:      ['category', 'price', 'unit'],
                    other:         ['category', 'price', 'unit'],
                }[svc] || [];
                var missing = required.filter(function(k) { return !row[k]; });
                if (missing.length) {
                    errors.push(svc + ' row ' + rowIdx + ': missing ' + missing.join(', '));
                    return;
                }
                if (svc === 'accommodation') { row.unit = 'per night'; row.category = row.room_category; }
                else if (svc === 'guide')    { row.unit = 'per day'; }
                payloads.push(row);
            });
        });
        return { payloads: payloads, errors: errors };
    }

    jQuery('#spMultiSaveBtn').on('click', function() {
        var $btn = jQuery(this);
        var result = collectMultiServicePayloads();
        if (result.errors.length) {
            window.showError && window.showError('Please complete all required fields: ' + result.errors.join('; '));
            return;
        }
        if (!result.payloads.length) {
            window.showError && window.showError('Tick at least one service type and fill its fields.');
            return;
        }
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');
        var saved = 0, failed = 0, errs = [];
        function saveNext(i) {
            if (i >= result.payloads.length) {
                $btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Save All Selected');
                if (failed) {
                    window.showError && window.showError(saved + ' saved, ' + failed + ' failed: ' + errs.join('; '));
                } else {
                    showAlert(saved + ' service rate(s) submitted — awaiting admin approval.', 'success');
                    bootstrap.Modal.getInstance(jQuery('#spMultiServiceModal')[0]).hide();
                    loadPricing();
                }
                return;
            }
            var payload = jQuery.extend({ save_sp_pricing: 1, provider_id: providerId, is_active: 1 }, result.payloads[i]);
            ajaxPost(payload, function() { saved++; saveNext(i + 1); }, function(xhr) {
                failed++;
                errs.push(payload.service_type + ': ' + ((xhr.responseJSON || {}).error || 'failed'));
                saveNext(i + 1);
            });
        }
        saveNext(0);
    });
    jQuery(document).on('click', '.sp-price-edit', function() {
        fillPriceForm(priceCache[jQuery(this).closest('tr').data('id')]);
        setAddMode('single');
        refreshBulkVisibility(jQuery('#spServiceType').val(), true);
        new bootstrap.Modal(jQuery('#spPriceModal')[0]).show();
    });

    // "All tiers" action — open the modal in bulk mode with one pre-populated
    // row per comfort tier for the clicked row's room_category. Existing tiers
    // are pre-filled (and carry their row id, so save updates instead of
    // creating duplicates); missing tiers are blank for the SP to fill.
    jQuery(document).on('click', '.sp-price-tier-matrix', function() {
        var clickedRow = priceCache[jQuery(this).closest('tr').data('id')];
        if (!clickedRow || !clickedRow.room_category) return;
        var roomCat = clickedRow.room_category;
        // Find existing rows for this room_category, keyed by comfort_tier
        var existingByTier = {};
        Object.keys(priceCache).forEach(function(id) {
            var row = priceCache[id];
            if (row.service_type !== 'accommodation') return;
            if ((row.room_category || row.category) !== roomCat) return;
            if (row.comfort_tier) existingByTier[row.comfort_tier] = row;
        });

        // Reset the modal into bulk mode for accommodation
        fillPriceForm(null);
        jQuery('#spServiceType').val('accommodation').trigger('change');
        setAddMode('bulk');
        refreshBulkVisibility('accommodation', false);
        jQuery('#bulkRowsContainer').empty();

        // Generate one row per comfort tier
        allComfortTiers.forEach(function(tier) {
            addBulkRow('accommodation');
            var $row = jQuery('#bulkRowsContainer .bulk-row').last();
            $row.find('.bulk-field[data-field=room_category]').val(roomCat);
            $row.find('.bulk-field[data-field=comfort_tier]').val(tier);
            var existing = existingByTier[tier];
            if (existing) {
                $row.attr('data-row-id', existing.id);
                $row.find('.bulk-field[data-field=total_rooms]').val(existing.total_rooms || '');
                $row.find('.bulk-field[data-field=price]').val(existing.price || '');
                $row.find('.bulk-field[data-field=meal_plan]').val(existing.meal_plan || '');
            }
        });
        jQuery('#bulkRowsContainer .custom-select').each(function() {
            if (window.buildCustomDropdown) window.buildCustomDropdown(this);
            jQuery(this).trigger('change');
        });
        jQuery('#spPriceModalTitle').text('All tier prices: ' + roomCat);
        new bootstrap.Modal(jQuery('#spPriceModal')[0]).show();
    });
    jQuery(document).on('click', '.sp-price-delete', function() {
        var id = jQuery(this).closest('tr').data('id');
        Swal.fire({
            title: 'Delete this row?',
            text: 'This cannot be undone.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#b54a4a'
        }).then(function(res) {
            if (!res.isConfirmed) return;
            ajaxPost({ delete_sp_pricing: 1, id: id }, function() {
                loadPricing();
                showAlert('Deleted.', 'success');
            });
        });
    });

    jQuery('#spPriceForm').on('submit', function(e) {
        e.preventDefault();

        // BULK MODE — save each row sequentially.
        if (currentAddMode === 'bulk') {
            var type = jQuery('#spServiceType').val();
            var rows = collectBulkRows(type);
            if (!rows.length) {
                showAlert('Fill at least one row before saving.', 'warning');
                return;
            }
            var $btn = jQuery('#spPriceSaveBtn');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving ' + rows.length + ' rows...');
            var saved = 0, failed = 0, errors = [];
            function saveNext(i) {
                if (i >= rows.length) {
                    $btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Save All Rows');
                    bootstrap.Modal.getInstance(jQuery('#spPriceModal')[0]).hide();
                    loadPricing();
                    var msg = saved + ' saved' + (failed ? ', ' + failed + ' failed: ' + errors.join('; ') : '.');
                    showAlert(msg, failed ? 'warning' : 'success');
                    return;
                }
                var row = rows[i];
                var payload = jQuery.extend({ save_sp_pricing: 1, provider_id: providerId, is_active: 1 }, row);
                if (type === 'accommodation') {
                    payload.category = payload.room_category;
                    payload.unit = 'per night';
                }
                ajaxPost(payload, function() { saved++; saveNext(i + 1); },
                    function(xhr) {
                        failed++;
                        errors.push(xhr.responseJSON ? (xhr.responseJSON.error || 'row ' + (i+1)) : 'row ' + (i+1));
                        saveNext(i + 1);
                    });
            }
            saveNext(0);
            return;
        }

        // SINGLE MODE (unchanged).
        var type = jQuery('#spServiceType').val();
        var data = {
            save_sp_pricing: 1,
            provider_id: providerId,
            id: jQuery(this).find('[name=id]').val() || '',
            service_type: type,
            description: jQuery(this).find('[name=description]').val() || '',
            is_active: jQuery(this).find('[name=is_active]').is(':checked') ? 1 : 0,
        };

        if (type === 'accommodation') {
            data.room_category = jQuery(this).find('[name=room_category]').val();
            data.comfort_tier  = jQuery(this).find('[name=comfort_tier]').val();
            data.total_rooms   = jQuery(this).find('[name=total_rooms]').val();
            data.meal_plan     = jQuery(this).find('[name=meal_plan]').val();
            data.price         = jQuery(this).find('[name=price_accommodation]').val();
            data.unit          = jQuery(this).find('[name=unit_accommodation]').val();
            data.category      = data.room_category;
            data.latitude          = jQuery(this).find('[name=latitude]').val();
            data.longitude         = jQuery(this).find('[name=longitude]').val();
            data.guest_capacity    = jQuery(this).find('[name=guest_capacity]').val();
            data.seasonality_notes = jQuery(this).find('[name=seasonality_notes]').val();
        } else if (type === 'transport') {
            data.vehicle_type     = jQuery(this).find('[name=vehicle_type]').val();
            data.vehicle_capacity = jQuery(this).find('[name=vehicle_capacity]').val();
            data.driver_allowance = jQuery(this).find('[name=driver_allowance]').val();
            data.price            = jQuery(this).find('[name=price_transport]').val();
            data.unit             = jQuery(this).find('[name=unit_transport]').val();
            data.vehicle_make_model      = jQuery(this).find('[name=vehicle_make_model]').val();
            data.vehicle_registration_no = jQuery(this).find('[name=vehicle_registration_no]').val();
            data.vehicle_year            = jQuery(this).find('[name=vehicle_year]').val();
            data.driver_included  = jQuery(this).find('[name=driver_included]').is(':checked') ? 1 : 0;
            data.fuel_tolls_extra = jQuery(this).find('[name=fuel_tolls_extra]').is(':checked') ? 1 : 0;
            data.price_per_km_plains = jQuery(this).find('[name=price_per_km_plains]').val();
            data.price_per_km_hills  = jQuery(this).find('[name=price_per_km_hills]').val();
            data.vehicle_count       = jQuery(this).find('[name=vehicle_count]').val();
            data.ac_extra_cost       = jQuery(this).find('[name=ac_extra_cost]').val();
            data.ac_available        = jQuery(this).find('[name=ac_available]').is(':checked') ? 1 : 0;
        } else if (type === 'guide') {
            data.category    = jQuery(this).find('[name=category_guide]').val();
            data.specialties = jQuery(this).find('[name=specialties_guide]').val();
            data.price       = jQuery(this).find('[name=price_guide]').val();
            data.unit        = jQuery(this).find('[name=unit_guide]').val();
            // An empty array still has to reach the server as a stated answer,
            // or clearing every language would read as "no answer given".
            data.languages      = jQuery(this).find('[name="languages[]"]').val() || [];
            data.wage_multi_day = jQuery(this).find('[name=wage_multi_day]').val();
            data.speaks_english = jQuery(this).find('[name=speaks_english]').is(':checked') ? 1 : 0;
            data.is_certified   = jQuery(this).find('[name=is_certified]').is(':checked') ? 1 : 0;
            data.has_first_aid  = jQuery(this).find('[name=has_first_aid]').is(':checked') ? 1 : 0;
        } else if (type === 'rental') {
            data.rental_item      = jQuery(this).find('[name=rental_item]').val();
            data.price            = jQuery(this).find('[name=price_rental]').val();
            data.unit             = jQuery(this).find('[name=unit_rental]').val();
            data.security_deposit = jQuery(this).find('[name=security_deposit]').val();
            data.notes            = jQuery(this).find('[name=notes_rental]').val();
        } else if (type === 'activity') {
            data.category    = jQuery(this).find('[name=category_activity]').val();
            data.min_group   = jQuery(this).find('[name=min_group]').val();
            data.max_group   = jQuery(this).find('[name=max_group]').val();
            data.specialties = jQuery(this).find('[name=specialties_activity]').val();
            data.price       = jQuery(this).find('[name=price_activity]').val();
            data.unit        = jQuery(this).find('[name=unit_activity]').val();
        } else {
            data.category = jQuery(this).find('[name=category_other]').val();
            data.price    = jQuery(this).find('[name=price_other]').val();
            data.unit     = jQuery(this).find('[name=unit_other]').val();
        }

        ajaxPost(data, function() {
            bootstrap.Modal.getInstance(jQuery('#spPriceModal')[0]).hide();
            loadPricing();
            showAlert('Saved.', 'success');
        }, function(xhr) {
            showAlert(xhr.responseJSON ? (xhr.responseJSON.error || 'Save failed') : 'Save failed', 'danger');
        });
    });

    loadPricing();
});
</script>
@endsection
