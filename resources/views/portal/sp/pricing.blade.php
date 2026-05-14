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
        <a href="{{ route('sp.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
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
            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-sm sp-btn-primary" id="spPriceAdd">
                    <i class="bi bi-plus-lg me-1"></i> Add Service / Room
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
                            <option value="per km">per km</option>
                            <option value="per day">per day</option>
                            <option value="per trip">per trip</option>
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

        {{-- ACCOMMODATION — pricing is per comfort tier, not per room category.
             One row = one comfort tier. Room types (Single / Double / etc.) are
             stored as an optional comma-separated list under room_category. --}}
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
                    <label class="form-label small">Rate per night (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_accommodation" placeholder="e.g. 2500">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-5">
                    <label class="form-label small">Total Rooms at this tier <span class="text-danger">*</span></label>
                    <input type="number" min="1" max="500" class="form-control form-control-sm" name="total_rooms" placeholder="e.g. 4">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-7">
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
            <div class="mb-2">
                <label class="form-label small mb-1">Room Types Offered <span class="text-muted small">(optional)</span></label>
                <div class="room-type-checklist border rounded p-2 bg-light">
                    @foreach($roomCategories as $r)
                        <label class="room-type-chip me-2 d-inline-flex align-items-center">
                            <input type="checkbox" class="form-check-input me-1 room-type-cb" value="{{ $r->name }}">
                            <span class="small">{{ $r->name }}</span>
                        </label>
                    @endforeach
                </div>
                <small class="text-muted d-block mt-1"><i class="bi bi-info-circle me-1"></i>Tick whichever room types you offer at this comfort tier. Leave all blank if a single mixed inventory.</small>
                <input type="hidden" name="room_category" value="">
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
                        <option value="per km">per km</option>
                        <option value="per day">per day</option>
                        <option value="per trip">per trip</option>
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Driver Allowance (₹/day)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="driver_allowance" placeholder="optional">
                    <small class="form-help-text text-muted d-block mt-1"></small>
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
            <input type="hidden" name="unit_guide" value="per day">
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
                        <option value="per person">per person</option>
                        <option value="per group">per group</option>
                        <option value="per day">per day</option>
                        <option value="per person per day">per person per day</option>
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
            var html = '';
            rows.forEach(function(r) {
                priceCache[r.id] = r;

                var details = '';
                if (r.service_type === 'accommodation') {
                    var parts = [];
                    // Comfort tier is now the primary label
                    if (r.comfort_tier)   parts.push('<strong>' + spEscape(r.comfort_tier) + '</strong>');
                    var rooms = r.room_category || r.category || '';
                    if (rooms)            parts.push('<span class="text-muted small">' + spEscape(rooms) + '</span>');
                    if (r.meal_plan)      parts.push('<span class="badge bg-light text-dark border">' + spEscape(r.meal_plan) + '</span>');
                    details = parts.join(' · ');
                } else if (r.service_type === 'transport') {
                    var parts = [];
                    if (r.vehicle_type)    parts.push('<strong>' + spEscape(r.vehicle_type) + '</strong>');
                    if (r.vehicle_capacity) parts.push('<small class="text-muted">' + r.vehicle_capacity + ' seats</small>');
                    if (r.driver_allowance) parts.push('<small class="text-muted">+ ₹' + Number(r.driver_allowance).toLocaleString('en-IN') + ' driver/day</small>');
                    details = parts.join(' · ');
                } else if (r.service_type === 'guide') {
                    var parts = [];
                    if (r.category)    parts.push('<strong>' + spEscape(r.category) + '</strong>');
                    if (r.specialties) parts.push('<small class="text-muted">' + spEscape(r.specialties) + '</small>');
                    details = parts.join(' · ');
                } else if (r.service_type === 'activity') {
                    var parts = [];
                    if (r.category)  parts.push('<strong>' + spEscape(r.category) + '</strong>');
                    if (r.min_group || r.max_group) {
                        var g = (r.min_group || '?') + '–' + (r.max_group || '?') + ' pax';
                        parts.push('<small class="text-muted">' + g + '</small>');
                    }
                    if (r.specialties) parts.push('<small class="text-muted">' + spEscape(r.specialties) + '</small>');
                    details = parts.join(' · ');
                } else {
                    details = '<strong>' + spEscape(r.category || '—') + '</strong>';
                }
                if (r.description) details += '<div class="small text-muted">' + spEscape(r.description) + '</div>';

                var inventory = '—';
                if (r.service_type === 'accommodation' && r.total_rooms) {
                    inventory = '<span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-door-closed"></i> ' + r.total_rooms + ' rooms</span>';
                }

                html += '<tr data-id="' + r.id + '">';
                html += '<td><span class="sp-type-badge">' + (typeIcons[r.service_type] || '·') + ' <span class="text-capitalize">' + spEscape(r.service_type) + '</span></span></td>';
                html += '<td>' + details + '</td>';
                html += '<td class="small fw-bold">&#8377;' + Number(r.price).toLocaleString('en-IN') + ' <span class="text-muted fw-normal small">' + spEscape(r.unit || '') + '</span></td>';
                html += '<td>' + inventory + '</td>';
                html += '<td>' + (r.is_active ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>') + '</td>';
                html += '<td>';
                if (r.service_type === 'accommodation' && r.room_category) {
                    html += '  <button class="btn btn-sm btn-outline-success sp-price-tier-matrix me-1" title="Set prices for all tiers of this room"><i class="bi bi-grid-3x3-gap"></i></button>';
                }
                html += '  <button class="btn btn-sm btn-outline-primary sp-price-edit me-1" title="Edit this row only"><i class="bi bi-pencil"></i></button>';
                html += '  <button class="btn btn-sm btn-outline-danger sp-price-delete" title="Delete"><i class="bi bi-trash"></i></button>';
                html += '</td>';
                html += '</tr>';
            });
            jQuery('#spPriceBody').html(html);
        });
    }

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

        var roomCatStr = r ? (r.room_category || r.category || '') : '';
        $f.find('[name=room_category]').val(roomCatStr);
        $f.find('[name=comfort_tier]').val(r ? (r.comfort_tier || '') : '');
        $f.find('[name=total_rooms]').val(r ? (r.total_rooms || '') : '');
        $f.find('[name=meal_plan]').val(r ? (r.meal_plan || '') : '');
        $f.find('[name=price_accommodation]').val(r && r.service_type === 'accommodation' ? r.price : '');

        // Room Types checklist — derive from the comma-separated room_category
        var picked = (roomCatStr || '').split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        $f.find('.room-type-cb').each(function() {
            jQuery(this).prop('checked', picked.indexOf(jQuery(this).val()) !== -1);
        });

        $f.find('[name=vehicle_type]').val(r ? (r.vehicle_type || '') : '');
        $f.find('[name=vehicle_capacity]').val(r ? (r.vehicle_capacity || '') : '');
        $f.find('[name=driver_allowance]').val(r ? (r.driver_allowance || '') : '');
        $f.find('[name=price_transport]').val(r && r.service_type === 'transport' ? r.price : '');
        $f.find('[name=unit_transport]').val(r && r.service_type === 'transport' ? (r.unit || '') : '');

        $f.find('[name=category_guide]').val(r && r.service_type === 'guide' ? (r.category || '') : '');
        $f.find('[name=specialties_guide]').val(r && r.service_type === 'guide' ? (r.specialties || '') : '');
        $f.find('[name=price_guide]').val(r && r.service_type === 'guide' ? r.price : '');

        $f.find('[name=category_activity]').val(r && r.service_type === 'activity' ? (r.category || '') : '');
        $f.find('[name=min_group]').val(r ? (r.min_group || '') : '');
        $f.find('[name=max_group]').val(r ? (r.max_group || '') : '');
        $f.find('[name=specialties_activity]').val(r && r.service_type === 'activity' ? (r.specialties || '') : '');
        $f.find('[name=price_activity]').val(r && r.service_type === 'activity' ? r.price : '');
        $f.find('[name=unit_activity]').val(r && r.service_type === 'activity' ? (r.unit || '') : '');

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
            // Comma-join the Room Types checklist into the room_category string.
            var picked = jQuery(this).find('.room-type-cb:checked').map(function() {
                return jQuery(this).val();
            }).get().join(', ');
            data.room_category = picked;
            data.comfort_tier  = jQuery(this).find('[name=comfort_tier]').val();
            data.total_rooms   = jQuery(this).find('[name=total_rooms]').val();
            data.meal_plan     = jQuery(this).find('[name=meal_plan]').val();
            data.price         = jQuery(this).find('[name=price_accommodation]').val();
            data.unit          = jQuery(this).find('[name=unit_accommodation]').val();
            data.category      = data.room_category;
        } else if (type === 'transport') {
            data.vehicle_type     = jQuery(this).find('[name=vehicle_type]').val();
            data.vehicle_capacity = jQuery(this).find('[name=vehicle_capacity]').val();
            data.driver_allowance = jQuery(this).find('[name=driver_allowance]').val();
            data.price            = jQuery(this).find('[name=price_transport]').val();
            data.unit             = jQuery(this).find('[name=unit_transport]').val();
        } else if (type === 'guide') {
            data.category    = jQuery(this).find('[name=category_guide]').val();
            data.specialties = jQuery(this).find('[name=specialties_guide]').val();
            data.price       = jQuery(this).find('[name=price_guide]').val();
            data.unit        = jQuery(this).find('[name=unit_guide]').val();
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
