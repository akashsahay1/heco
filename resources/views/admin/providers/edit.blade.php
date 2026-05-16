@extends('admin.layout')
@section('title', 'Edit ' . $provider->name . ' - HCT')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('hct.providers.show', $provider->id) }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Back to Provider
        </a>
        <h5 class="mb-0 mt-1"><i class="bi bi-pencil-square"></i> Edit {{ $provider->name }}</h5>
    </div>
</div>

@if($provider->last_updated_by)
    <div class="alert alert-light border small py-2">
        <i class="bi bi-clock-history"></i>
        Last updated by <strong>{{ $provider->lastUpdatedBy->full_name ?? $provider->lastUpdatedBy->email ?? 'unknown' }}</strong>
        <span class="badge bg-secondary ms-1">{{ ucfirst($provider->last_updated_by_role ?: '-') }}</span>
        on {{ $provider->updated_at?->format('d M Y, h:i A') }}
    </div>
@endif

<form id="providerEditForm">
    <input type="hidden" name="provider_id" value="{{ $provider->id }}">

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="border-bottom pb-2"><i class="bi bi-person-vcard"></i> Identity & Contact</h6>
                    <div class="mb-2">
                        <label class="form-label small text-muted">Provider Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $provider->name }}" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Type</label>
                            <select name="provider_type" class="form-select form-select-sm custom-select">
                                <option value="hrp" @selected($provider->provider_type === 'hrp')>HRP</option>
                                <option value="hlh" @selected($provider->provider_type === 'hlh')>HLH</option>
                                <option value="osp" @selected($provider->provider_type === 'osp')>OSP</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Region</label>
                            <select name="region_id" class="form-select form-select-sm custom-select">
                                <option value="">-- None --</option>
                                @foreach($regions as $r)
                                    <option value="{{ $r->id }}" @selected($provider->region_id == $r->id)>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control form-control-sm" value="{{ $provider->contact_person }}">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Email</label>
                            <input type="email" name="email" class="form-control form-control-sm" value="{{ $provider->email }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Phone 1</label>
                            <input type="text" name="phone_1" class="form-control form-control-sm" value="{{ $provider->phone_1 }}">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted">Phone 2</label>
                        <input type="text" name="phone_2" class="form-control form-control-sm" value="{{ $provider->phone_2 }}">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted">Address</label>
                        <textarea name="address" class="form-control form-control-sm" rows="2">{{ $provider->address }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="border-bottom pb-2"><i class="bi bi-shield-check"></i> Status & Notes <span class="badge bg-secondary ms-1">Admin only</span></h6>
                    <div class="mb-2">
                        <label class="form-label small text-muted">Status</label>
                        <select name="status" class="form-select form-select-sm custom-select">
                            <option value="approved" @selected($provider->status === 'approved')>Approved</option>
                            <option value="pending" @selected($provider->status === 'pending')>Pending</option>
                            <option value="rejected" @selected($provider->status === 'rejected')>Rejected</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted">Internal Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3">{{ $provider->notes }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="border-bottom pb-2"><i class="bi bi-bank"></i> Bank Details</h6>
                    <div class="mb-2">
                        <label class="form-label small text-muted">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control form-control-sm" value="{{ $provider->bank_name }}">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">IFSC</label>
                            <input type="text" name="bank_ifsc" class="form-control form-control-sm" value="{{ $provider->bank_ifsc }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Account Number</label>
                            <input type="text" name="bank_account_number" class="form-control form-control-sm" value="{{ $provider->bank_account_number }}">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted">Account Name</label>
                        <input type="text" name="bank_account_name" class="form-control form-control-sm" value="{{ $provider->bank_account_name }}">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted">UPI ID</label>
                        <input type="text" name="upi" class="form-control form-control-sm" value="{{ $provider->upi }}">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="border-bottom pb-2"><i class="bi bi-gear"></i> Capabilities</h6>

                    @php
                        // Comfort tiers are now per-row on sp_pricing.comfort_tier;
                        // derive a read-only summary from the provider's current rows.
                        $derivedComfortTiers = \App\Models\SpPricing::where('service_provider_id', $provider->id)
                            ->where('service_type', 'accommodation')
                            ->where('is_active', true)
                            ->whereNotNull('comfort_tier')
                            ->where('comfort_tier', '!=', '')
                            ->pluck('comfort_tier')
                            ->unique()
                            ->values()
                            ->all();

                        $caps = [
                            ['name' => 'services_offered',         'label' => 'Services Offered',         'help' => null,                                                                                       'options' => $serviceTypes,            'current' => $provider->services_offered ?? []],
                            ['name' => 'vehicle_types',            'label' => 'Vehicle Types',            'help' => null,                                                                                       'options' => $vehicleTypes,            'current' => $provider->vehicle_types ?? []],
                            ['name' => 'guide_types',              'label' => 'Guide Types',              'help' => null,                                                                                       'options' => $guideTypes,              'current' => $provider->guide_types ?? []],
                            ['name' => 'activity_types',           'label' => 'Activity Types',           'help' => null,                                                                                       'options' => $activityTypes,           'current' => $provider->activity_types ?? []],
                        ];
                    @endphp

                    {{-- Read-only comfort-tier summary, auto-derived from sp_pricing rows.
                         The badges inside #comfortTierBadges are re-rendered by JS each
                         time the pricing list reloads, so admins see live updates after
                         adding/removing rows without needing to refresh the page. --}}
                    <div class="mb-2">
                        <label class="form-label small text-muted">Comfort Tiers Offered <span class="badge bg-light text-dark border ms-1 auto-pill">auto</span></label>
                        <div class="form-control form-control-sm bg-light comfort-tier-summary" id="comfortTierBadges">
                            @forelse($derivedComfortTiers as $tier)
                                <span class="badge bg-secondary me-1">{{ $tier }}</span>
                            @empty
                                <span class="text-muted small">No accommodation rows yet — set per row under Services, Rooms & Pricing.</span>
                            @endforelse
                        </div>
                        <small class="text-muted d-block mt-1"><i class="bi bi-info-circle me-1"></i>Derived from each accommodation row's Comfort Tier. Edit under Services, Rooms & Pricing to change.</small>
                    </div>

                    @foreach($caps as $idx => $cap)
                        <div class="{{ $idx === count($caps) - 1 ? 'mb-0' : 'mb-2' }}">
                            <label class="form-label small text-muted">{{ $cap['label'] }}</label>
                            <div class="ms-dropdown" data-name="{{ $cap['name'] }}">
                                <button type="button" class="form-select form-select-sm text-start ms-trigger">
                                    <span class="ms-label text-muted">Select options...</span>
                                </button>
                                <div class="ms-panel d-none">
                                    @forelse($cap['options'] as $opt)
                                        <label class="ms-option">
                                            <input type="checkbox" value="{{ $opt->name }}"
                                                   {{ in_array($opt->name, $cap['current'] ?: [], true) ? 'checked' : '' }}>
                                            <span>{{ $opt->name }}</span>
                                        </label>
                                    @empty
                                        <div class="ms-empty">No options available. Add them under <a href="{{ url('/travel-preferences') }}">Travel Preferences</a> or the matching system list.</div>
                                    @endforelse
                                </div>
                            </div>
                            @if(!empty($cap['help']))
                                <small class="text-muted d-block mt-1"><i class="bi bi-info-circle me-1"></i>{{ $cap['help'] }}</small>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3 mb-4">
        <button type="submit" class="btn btn-success" id="saveBtn">
            <i class="bi bi-check-lg"></i> Save Changes
        </button>
        <a href="{{ route('hct.providers.show', $provider->id) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

{{-- ===== Danger zone — Remove / Restore provider ===== --}}
@php
    $activePricingCount = \App\Models\SpPricing::where('service_provider_id', $provider->id)->count();
    $activeBookingsCount = \App\Models\SpRoomBooking::whereIn('sp_pricing_id',
        \App\Models\SpPricing::where('service_provider_id', $provider->id)->pluck('id'))
        ->whereIn('status', ['held', 'confirmed'])->count();
    $hostedExperiencesCount = \App\Models\Experience::where('hlh_id', $provider->id)->count();
    $isRemoved = $provider->status === 'removed';
@endphp
<div class="card mb-5 border-danger-subtle" id="providerDangerZone">
    <div class="card-body">
        <h6 class="border-bottom pb-2 text-danger"><i class="bi bi-exclamation-triangle"></i> Danger zone</h6>

        @if($isRemoved)
            @php
                $blockerPayments = \App\Models\SpPayment::where('service_provider_id', $provider->id)->count();
                $hostedExperiencesNow = \App\Models\Experience::where('hlh_id', $provider->id)->count();
                $canHardDelete = $blockerPayments === 0;
            @endphp
            <p class="mb-2 small">This provider is currently <strong>removed</strong> — they cannot log in, their pricing is inactive, and their inventory is hidden from Trip Manager &amp; travellers. Historical trips and references are preserved.</p>
            <div class="d-flex gap-2 flex-wrap mb-2">
                <button type="button" class="btn btn-sm btn-success" id="btnRestoreProvider" data-provider-id="{{ $provider->id }}">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Restore Provider
                </button>
                <button type="button" class="btn btn-sm btn-danger" id="btnPermanentDeleteProvider" data-provider-id="{{ $provider->id }}"
                    data-hosted-count="{{ $hostedExperiencesNow }}"
                    {{ $canHardDelete ? '' : 'disabled' }}>
                    <i class="bi bi-trash3 me-1"></i> Permanently Delete
                </button>
            </div>
            @if(!$canHardDelete)
                <small class="text-muted d-block"><i class="bi bi-info-circle me-1"></i>
                    Permanent delete blocked — {{ $blockerPayments }} payment {{ \Illuminate\Support\Str::plural('record', $blockerPayments) }} reference this provider. Archive those first.
                </small>
            @else
                <small class="text-muted d-block"><i class="bi bi-exclamation-triangle me-1"></i>
                    Permanent delete <strong>cannot be undone</strong>. All pricing, availability blocks, and room bookings will be wiped.
                    @if($hostedExperiencesNow > 0)
                        <strong>{{ $hostedExperiencesNow }} hosted {{ \Illuminate\Support\Str::plural('experience', $hostedExperiencesNow) }}</strong> will be auto-detached (host set to none, deactivated). Historical trip lines stay but lose the provider link.
                    @else
                        Historical trip lines stay but lose the provider link.
                    @endif
                </small>
            @endif
        @else
            <p class="mb-1 small">Removing a provider does the following <em>(reversible — admin can restore later)</em>:</p>
            <ul class="small text-muted mb-2">
                <li>Provider status set to <strong>removed</strong></li>
                <li>Linked user account marked <strong>inactive</strong> (can't log in)</li>
                <li><strong>{{ $activePricingCount }}</strong> pricing {{ \Illuminate\Support\Str::plural('row', $activePricingCount) }} marked inactive (hidden from Trip Manager / travellers)</li>
                <li><strong>{{ $activeBookingsCount }}</strong> active room {{ \Illuminate\Support\Str::plural('booking', $activeBookingsCount) }} released (no longer reserve inventory)</li>
                @if($hostedExperiencesCount > 0)
                    <li class="text-warning"><i class="bi bi-info-circle me-1"></i><strong>{{ $hostedExperiencesCount }}</strong> hosted {{ \Illuminate\Support\Str::plural('experience', $hostedExperiencesCount) }} will lose this provider as host — experiences stay but show no host until reassigned.</li>
                @endif
            </ul>
            <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoveProvider" data-provider-id="{{ $provider->id }}">
                <i class="bi bi-trash me-1"></i> Remove Provider
            </button>
        @endif
    </div>
</div>

{{-- ===== Services & Pricing — hotel-style inventory for accommodation, rates for everything else ===== --}}
<div class="card mb-5" id="spPricingCard" data-provider-id="{{ $provider->id }}">
    <div class="card-body">
        <h6 class="border-bottom pb-2"><i class="bi bi-cash-stack"></i> Services, Rooms &amp; Pricing
            <span class="text-muted small fw-normal">— Trip Manager pulls these in when this provider is assigned. For Accommodation, each row is a room category + how many rooms of that type.</span>
        </h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle sp-pricing-table">
                <thead class="table-light">
                    <tr>
                        <th class="w-check"><i class="bi bi-check2-square sp-price-selall" role="button" title="Select all"></i></th>
                        <th class="w-status">Type</th>
                        <th>Details</th>
                        <th class="w-rate">Rate</th>
                        <th class="w-status">Inventory</th>
                        <th class="w-active">Active</th>
                        <th class="w-status">Actions</th>
                    </tr>
                </thead>
                <tbody id="spPriceBody"><tr><td colspan="7" class="text-center text-muted small">Loading...</td></tr></tbody>
            </table>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-danger d-none" id="spPriceBulkDelete"><i class="bi bi-trash me-1"></i> Delete Selected</button>
            <button type="button" class="btn btn-sm btn-success" id="spPriceAdd"><i class="bi bi-plus-lg me-1"></i> Add Service / Room</button>
            <button type="button" class="btn btn-sm btn-outline-success" id="spMultiServiceAdd"><i class="bi bi-collection me-1"></i> Add multiple services at once</button>
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

        {{-- Step 1: Service type (always visible) --}}
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

        {{-- ============= BULK ADD MODE (Accommodation + Transport only) ============= --}}
        <div class="add-mode-pane add-mode-bulk d-none">
            <div class="alert alert-info border small mb-3">
                <i class="bi bi-stars me-1"></i> Add multiple <span class="bulk-mode-label">room categories</span> in one go. Click <strong>+ Add another</strong> to append a row. Each row creates a separate entry.
            </div>

            {{-- Quick tier helper — pick a room category, get one row per comfort tier. --}}
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

        {{-- Hidden templates for bulk rows — JS clones these. --}}
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

        {{-- ============= SINGLE-ENTRY FIELDS (default mode) ============= --}}
        <div class="add-mode-pane add-mode-single">

        {{-- ============= ACCOMMODATION FIELDS =============
             One row per (comfort_tier × room_category) pair so a hotel can
             carry different inventory + price for Cat A Single (2 rooms @
             ₹4500) vs Cat A Double (3 rooms @ ₹5500). Both fields required;
             Comfort Tier is the primary visual axis. --}}
        <div class="svc-fields" data-svc="accommodation">
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-7">
                    <label class="form-label small">Comfort Tier <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="comfort_tier" data-list-type="accommodation_category" data-required-for="accommodation">
                        <option value="">Select tier...</option>
                        @foreach($accommodationCategories as $c)
                            <option value="{{ $c->name }}" data-desc="{{ $c->description }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Room Category <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="room_category" data-list-type="room_category" data-required-for="accommodation">
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
                    <input type="number" min="1" max="500" class="form-control form-control-sm" name="total_rooms" placeholder="e.g. 4" data-required-for="accommodation">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-7">
                    <label class="form-label small">Rate per night (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_accommodation" placeholder="e.g. 2500" data-price-for="accommodation">
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

            <input type="hidden" name="unit_accommodation" value="per night">
        </div>

        {{-- ============= TRANSPORT FIELDS ============= --}}
        <div class="svc-fields d-none" data-svc="transport">
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-7">
                    <label class="form-label small">Vehicle Type <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="vehicle_type" data-list-type="vehicle_type" data-required-for="transport">
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
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_transport" placeholder="e.g. 25" data-price-for="transport">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Unit <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="unit_transport" data-required-for="transport">
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

        {{-- ============= GUIDE FIELDS ============= --}}
        <div class="svc-fields d-none" data-svc="guide">
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-7">
                    <label class="form-label small">Guide Type / Language <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="category_guide" data-list-type="guide_preference" data-required-for="guide">
                        <option value="">Select guide type...</option>
                        @foreach($guideTypes as $g)
                            <option value="{{ $g->name }}" data-desc="{{ $g->description }}">{{ $g->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Rate per day (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_guide" placeholder="e.g. 3000" data-price-for="guide">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label small">Specialties (optional)</label>
                <input type="text" class="form-control form-control-sm" name="specialties_guide" placeholder="e.g. Bird-watching, Hindi + English, IMF-certified">
            </div>
            <input type="hidden" name="unit_guide" value="per day">
        </div>

        {{-- ============= ACTIVITY FIELDS ============= --}}
        <div class="svc-fields d-none" data-svc="activity">
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-7">
                    <label class="form-label small">Activity Type <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="category_activity" data-list-type="activity_type" data-required-for="activity">
                        <option value="">Select activity...</option>
                        @foreach($activityTypes as $a)
                            <option value="{{ $a->name }}" data-desc="{{ $a->description }}">{{ $a->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Rate (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_activity" placeholder="e.g. 800" data-price-for="activity">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>

            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-4">
                    <label class="form-label small">Unit <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="unit_activity" data-required-for="activity">
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

        {{-- ============= OTHER FIELDS ============= --}}
        <div class="svc-fields d-none" data-svc="other">
            <div class="row g-2 mb-2 sp-field-row">
                <div class="col-md-7">
                    <label class="form-label small">Service Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="category_other" placeholder="e.g. Permit fee, Camera fee, Equipment rental" data-required-for="other">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Rate (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_other" placeholder="e.g. 500" data-price-for="other">
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label small">Unit <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="unit_other" placeholder="e.g. per item, per day, per trip" data-required-for="other">
            </div>
        </div>

        </div> {{-- /.add-mode-single --}}

        <hr class="my-3">

        {{-- Common: description + active (single mode only — bulk rows submit each
             with empty description; admin can edit individual entries afterwards) --}}
        <div class="add-mode-single-only">
        {{-- Common: description + active --}}
        <div class="mb-2">
            <label class="form-label small">Internal note (optional)</label>
            <input type="text" class="form-control form-control-sm" name="description" placeholder="e.g. 'Off-season rate', 'Mountain-view rooms only'">
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="spPriceActive">
            <label class="form-check-label small" for="spPriceActive">Active — visible to Trip Manager &amp; AI suggestions</label>
        </div>
        </div> {{-- /.add-mode-single-only --}}

        <button type="submit" class="btn btn-success w-100" id="spPriceSaveBtn"><i class="bi bi-check-lg me-1"></i> Save</button>
    </form></div>
</div></div></div>

{{-- ============= MULTI-SERVICE-TYPE MODAL =============
     Tick the service types this provider offers, fill one row per ticked
     type. Save All creates one sp_pricing row per ticked section. Admin
     saves are written directly approved (no pending). --}}
<div class="modal fade" id="spMultiServiceModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-collection"></i> Add multiple services at once</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body"><form id="spMultiForm">
        <p class="small text-muted mb-3">Tick the service types this provider offers. Within each ticked section you can add as many rows as you need — each row becomes one rate entry.</p>

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

        {{-- Row templates — Blade renders options server-side, JS clones a
             fresh copy whenever the user adds a row. --}}
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
                        <input type="text" class="form-control form-control-sm bulk-field" data-field="specialties" placeholder="optional">
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
                            <option value="per person">per person</option>
                            <option value="per group">per group</option>
                            <option value="per day">per day</option>
                            <option value="per person per day">per person per day</option>
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
            <button type="button" class="btn btn-sm btn-success" id="spMultiSaveBtn">
                <i class="bi bi-check-lg me-1"></i> Save All Selected
            </button>
        </div>
    </form></div>
</div></div></div>

@endsection

@section('js')
<script>
// === Multi-select dropdown ===
function updateMsLabel($dd) {
    var checked = $dd.find('input[type=checkbox]:checked');
    var $label = $dd.find('.ms-label');
    if (checked.length === 0) {
        $label.text('Select options...').addClass('text-muted');
    } else if (checked.length <= 3) {
        var names = checked.map(function() { return jQuery(this).val(); }).get();
        $label.text(names.join(', ')).removeClass('text-muted');
    } else {
        $label.text(checked.length + ' selected').removeClass('text-muted');
    }
}

function getDdValues(name) {
    return jQuery('.ms-dropdown[data-name="' + name + '"] input[type=checkbox]:checked')
        .map(function() { return jQuery(this).val(); }).get();
}

jQuery(function() {
    jQuery('.ms-dropdown').each(function() { updateMsLabel(jQuery(this)); });

    jQuery(document).on('click', '.ms-trigger', function(e) {
        e.stopPropagation();
        var $panel = jQuery(this).siblings('.ms-panel');
        jQuery('.ms-panel').not($panel).addClass('d-none');
        $panel.toggleClass('d-none');
    });

    jQuery(document).on('change', '.ms-panel input[type=checkbox]', function() {
        updateMsLabel(jQuery(this).closest('.ms-dropdown'));
    });

    jQuery(document).on('click', function(e) {
        if (!jQuery(e.target).closest('.ms-dropdown').length) {
            jQuery('.ms-panel').addClass('d-none');
        }
    });
});

// ─── Danger zone: Remove / Restore provider ──────────────────────────
jQuery('#btnRemoveProvider').on('click', function() {
    var id = jQuery(this).data('provider-id');
    Swal.fire({
        title: 'Remove this provider?',
        text: 'They will not be able to log in. Their pricing and bookings will be deactivated. You can restore them later.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove',
        confirmButtonColor: '#b54a4a',
    }).then(function(res) {
        if (!res.isConfirmed) return;
        ajaxPost({ remove_provider: 1, provider_id: id }, function() {
            showAlert('Provider removed.', 'success');
            setTimeout(function() { location.reload(); }, 700);
        });
    });
});

jQuery('#btnPermanentDeleteProvider').on('click', function() {
    var $btn = jQuery(this);
    var id = $btn.data('provider-id');
    var hosted = parseInt($btn.data('hosted-count') || 0, 10);
    var hostedLine = hosted > 0
        ? '<strong>' + hosted + ' hosted experience' + (hosted === 1 ? '' : 's')
            + '</strong> will be auto-detached (host set to none, deactivated).<br>'
        : '';
    Swal.fire({
        title: 'Permanently delete this provider?',
        html: '<strong>This cannot be undone.</strong><br><br>'
            + 'All pricing rows, availability blocks, and room bookings will be wiped.<br>'
            + hostedLine
            + 'Historical trip lines remain but lose the link to this provider. '
            + 'The linked user account will also be deleted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, permanently delete',
        confirmButtonColor: '#b54a4a',
        focusCancel: true,
    }).then(function(res) {
        if (!res.isConfirmed) return;
        ajaxPost({ permanently_delete_provider: 1, provider_id: id }, function(resp) {
            var detached = resp && resp.detached_experiences ? resp.detached_experiences : 0;
            var msg = detached
                ? 'Provider deleted. ' + detached + ' experience' + (detached === 1 ? '' : 's') + ' detached.'
                : 'Provider permanently deleted.';
            // Stash the toast for the providers listing to pick up after
            // navigation. Redirect immediately so the user can't refresh
            // the now-orphan edit URL during a delay window and trip a 404.
            try { sessionStorage.setItem('heco_flash', msg); } catch (e) {}
            window.location.href = '/providers';
        }, function(xhr) {
            var msg = (xhr.responseJSON || {}).error || 'Delete failed.';
            window.showError && window.showError(msg);
        });
    });
});

jQuery('#btnRestoreProvider').on('click', function() {
    var id = jQuery(this).data('provider-id');
    Swal.fire({
        title: 'Restore this provider?',
        text: 'Their account will be reactivated (status: approved). Pricing rows stay inactive — re-enable individually as needed.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, restore',
        confirmButtonColor: '#79a09f',
    }).then(function(res) {
        if (!res.isConfirmed) return;
        ajaxPost({ restore_provider: 1, provider_id: id }, function() {
            showAlert('Provider restored.', 'success');
            setTimeout(function() { location.reload(); }, 700);
        });
    });
});

jQuery('#providerEditForm').on('submit', function(e) {
    e.preventDefault();
    var btn = jQuery('#saveBtn');
    btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Saving...');

    var data = {
        edit_provider: 1,
        services_offered:         getDdValues('services_offered'),
        vehicle_types:            getDdValues('vehicle_types'),
        guide_types:              getDdValues('guide_types'),
        activity_types:           getDdValues('activity_types')
        // accommodation_categories: now per-row on sp_pricing.comfort_tier;
        // do NOT send so the AjaxController preserves whatever legacy value
        // is already on the provider row.
    };
    jQuery(this).find('input, textarea, select').each(function() {
        if (this.name) data[this.name] = jQuery(this).val();
    });

    ajaxPost(data, function() {
        window.location.href = '{{ route('hct.providers.show', $provider->id) }}';
    }, function() {
        btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Save Changes');
        alert('Failed to save. Please try again.');
    });
});

// === Services & Pricing ===
jQuery(function() {
    var providerId = jQuery('#spPricingCard').data('provider-id');
    var priceCache = {};

    function spEscape(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }
    function refreshBulkBtn() {
        jQuery('#spPriceBulkDelete').toggleClass('d-none', jQuery('.sp-price-check.sp-price-checked').length === 0);
    }
    var typeIcons = {
        accommodation: '🛏', transport: '🚙', guide: '👤', activity: '🏔', other: '📦'
    };

    // Rebuild the read-only "Comfort Tiers Offered" badge list from the live
    // pricing rows so the summary stays in sync without a page reload.
    function refreshComfortTierBadges(rows) {
        var $box = jQuery('#comfortTierBadges');
        if (!$box.length) return;
        var seen = {};
        var tiers = [];
        (rows || []).forEach(function(r) {
            if (r.service_type !== 'accommodation' || !r.is_active || !r.comfort_tier) return;
            if (!seen[r.comfort_tier]) {
                seen[r.comfort_tier] = true;
                tiers.push(r.comfort_tier);
            }
        });
        if (!tiers.length) {
            $box.html('<span class="text-muted small">No accommodation rows yet — set per row under Services, Rooms &amp; Pricing.</span>');
            return;
        }
        $box.html(tiers.map(function(t) {
            return '<span class="badge bg-secondary me-1">' + spEscape(t) + '</span>';
        }).join(''));
    }

    function loadPricing() {
        jQuery('#spPriceBody').html('<tr><td colspan="7" class="text-center text-muted small">Loading...</td></tr>');
        ajaxPost({ get_sp_pricing: 1, provider_id: providerId }, function(resp) {
            var rows = resp.rows || [];
            priceCache = {};
            // Refresh the auto-derived Comfort Tiers badges so admins don't
            // have to reload the page after adding/removing accommodation rows.
            refreshComfortTierBadges(rows);
            if (!rows.length) { jQuery('#spPriceBody').html('<tr><td colspan="7" class="text-center text-muted small">No rates yet. Click <strong>Add Service / Room</strong> below to set up the first one.</td></tr>'); refreshBulkBtn(); return; }

            // First pass: count (comfort_tier × room_category) pairs on active
            // accommodation rows so we can flag duplicate combinations. Multiple
            // room categories at the same tier is fine (Cat A Single + Cat A
            // Double); the same room at the same tier saved twice is the bug.
            var pairCounts = {};
            rows.forEach(function(r) {
                if (r.service_type !== 'accommodation' || !r.is_active || !r.comfort_tier) return;
                var key = r.comfort_tier + '|' + (r.room_category || r.category || '');
                pairCounts[key] = (pairCounts[key] || 0) + 1;
            });

            var html = '';
            rows.forEach(function(r) {
                priceCache[r.id] = r;
                var pairKey = (r.comfort_tier || '') + '|' + (r.room_category || r.category || '');
                var isDupTier = r.service_type === 'accommodation'
                    && r.comfort_tier
                    && pairCounts[pairKey] > 1;

                // Build a service-type-specific "Details" cell
                var details = '';
                if (r.service_type === 'accommodation') {
                    var parts = [];
                    // Comfort tier is now the primary label
                    if (r.comfort_tier)   parts.push('<strong>' + spEscape(r.comfort_tier) + '</strong>');
                    if (isDupTier)        parts.push('<span class="badge bg-warning text-dark" title="This tier + room combination appears on more than one row. Keep one row per (tier, room) pair."><i class="bi bi-exclamation-triangle me-1"></i>duplicate</span>');
                    var rooms = r.room_category || r.category || '';
                    if (rooms)            parts.push('<span class="text-muted small">' + spEscape(rooms) + '</span>');
                    if (r.meal_plan)      parts.push('<span class="badge bg-light text-dark border">' + spEscape(r.meal_plan) + '</span>');
                    if (r.default_occupancy) parts.push('<small class="text-muted">' + spEscape(r.default_occupancy) + '</small>');
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

                // Inventory cell — only meaningful for accommodation
                var inventory = '—';
                if (r.service_type === 'accommodation' && r.total_rooms) {
                    inventory = '<span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-door-closed"></i> ' + r.total_rooms + ' rooms</span>';
                }

                html += '<tr data-id="' + r.id + '">';
                html += '<td><i class="bi bi-square sp-price-check" role="button" data-id="' + r.id + '"></i></td>';
                html += '<td><span class="sp-type-pill">' + (typeIcons[r.service_type] || '·') + ' <span class="text-capitalize">' + spEscape(r.service_type) + '</span></span></td>';
                html += '<td>' + details + '</td>';
                html += '<td class="small fw-bold">&#8377;' + Number(r.price).toLocaleString('en-IN') + ' <span class="text-muted fw-normal small">' + spEscape(r.unit || '') + '</span></td>';
                html += '<td>' + inventory + '</td>';
                html += '<td>' + (r.is_active ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>') + '</td>';
                html += '<td>';
                if (r.service_type === 'accommodation' && r.room_category) {
                    html += '<button class="btn btn-sm btn-outline-success sp-price-tier-matrix me-1" title="Set prices for all tiers of this room"><i class="bi bi-grid-3x3-gap"></i></button>';
                }
                html += '<button class="btn btn-sm btn-outline-primary sp-price-edit" title="Edit this row only"><i class="bi bi-pencil"></i></button>';
                html += '</td>';
                html += '</tr>';
            });
            jQuery('#spPriceBody').html(html);
            refreshBulkBtn();
        });
    }
    jQuery(document).on('click', '.sp-price-check', function() {
        jQuery(this).toggleClass('sp-price-checked').toggleClass('bi-square').toggleClass('bi-check-square');
        refreshBulkBtn();
    });
    jQuery(document).on('click', '.sp-price-selall', function() {
        var anyUnchecked = jQuery('.sp-price-check:not(.sp-price-checked)').length > 0;
        jQuery('.sp-price-check').each(function() {
            jQuery(this).toggleClass('sp-price-checked', anyUnchecked).toggleClass('bi-square', !anyUnchecked).toggleClass('bi-check-square', anyUnchecked);
        });
        refreshBulkBtn();
    });
    jQuery('#spPriceBulkDelete').on('click', function() {
        var ids = jQuery('.sp-price-check.sp-price-checked').map(function() { return jQuery(this).data('id'); }).get();
        if (!ids.length) return;
        confirmAction('Delete ' + ids.length + ' rate(s)? This is permanent.', function() {
            // provider_id is required: resolveSpPricingProviderId on the backend
            // returns 0 for HCT admins when no provider_id is sent, which makes
            // the WHERE service_provider_id = 0 clause match nothing and the
            // delete silently no-ops.
            ajaxPost({ delete_sp_pricing: 1, ids: ids, provider_id: providerId }, function() {
                loadPricing();
                showAlert('Deleted.', 'success');
            });
        });
    });
    // Show only the fields relevant to the selected service type.
    function showServiceFields(type) {
        jQuery('.svc-fields').addClass('d-none');
        jQuery('.svc-fields[data-svc="' + type + '"]').removeClass('d-none');
        // Reset the help-text below dropdowns to match the currently-selected option.
        jQuery('select[data-list-type]').each(function() { jQuery(this).trigger('change'); });

        // Update modal title hint
        var label = jQuery('#spServiceType option:selected').text().replace(/^[^A-Za-z]+/, '');
        jQuery('#spPriceModalTitle').text(jQuery('#spPriceForm [name=id]').val() ? 'Edit: ' + label : 'Add: ' + label);
    }
    jQuery('#spServiceType').on('change', function() { showServiceFields(this.value); });

    // Populate the form for add or edit. Service-type-specific fields are
    // namespaced (price_accommodation, price_transport, etc.) so multiple
    // <input name=price...> don't collide; we re-fold on submit.
    function fillPriceForm(r) {
        var $f = jQuery('#spPriceForm');
        $f[0].reset();
        $f.find('[name=id]').val(r ? r.id : '');
        var t = r ? r.service_type : 'accommodation';
        $f.find('[name=service_type]').val(t);

        // Common
        $f.find('[name=description]').val(r ? (r.description || '') : '');
        $f.find('[name=is_active]').prop('checked', r ? !!r.is_active : true);

        // Accommodation — legacy data may have a comma-list under room_category;
        // pick the first value so the single-select dropdown has a valid match.
        var roomCatStr = r ? (r.room_category || r.category || '') : '';
        var firstCat = roomCatStr.split(',')[0].trim();
        $f.find('[name=room_category]').val(firstCat);
        $f.find('[name=comfort_tier]').val(r ? (r.comfort_tier || '') : '');
        $f.find('[name=total_rooms]').val(r ? (r.total_rooms || '') : '');
        $f.find('[name=meal_plan]').val(r ? (r.meal_plan || '') : '');
        $f.find('[name=price_accommodation]').val(r && r.service_type === 'accommodation' ? r.price : '');

        // Transport
        $f.find('[name=vehicle_type]').val(r ? (r.vehicle_type || '') : '');
        $f.find('[name=vehicle_capacity]').val(r ? (r.vehicle_capacity || '') : '');
        $f.find('[name=driver_allowance]').val(r ? (r.driver_allowance || '') : '');
        $f.find('[name=price_transport]').val(r && r.service_type === 'transport' ? r.price : '');
        $f.find('[name=unit_transport]').val(r && r.service_type === 'transport' ? (r.unit || '') : '');

        // Guide
        $f.find('[name=category_guide]').val(r && r.service_type === 'guide' ? (r.category || '') : '');
        $f.find('[name=specialties_guide]').val(r && r.service_type === 'guide' ? (r.specialties || '') : '');
        $f.find('[name=price_guide]').val(r && r.service_type === 'guide' ? r.price : '');

        // Activity
        $f.find('[name=category_activity]').val(r && r.service_type === 'activity' ? (r.category || '') : '');
        $f.find('[name=min_group]').val(r ? (r.min_group || '') : '');
        $f.find('[name=max_group]').val(r ? (r.max_group || '') : '');
        $f.find('[name=specialties_activity]').val(r && r.service_type === 'activity' ? (r.specialties || '') : '');
        $f.find('[name=price_activity]').val(r && r.service_type === 'activity' ? r.price : '');
        $f.find('[name=unit_activity]').val(r && r.service_type === 'activity' ? (r.unit || '') : '');

        // Other
        $f.find('[name=category_other]').val(r && r.service_type === 'other' ? (r.category || '') : '');
        $f.find('[name=price_other]').val(r && r.service_type === 'other' ? r.price : '');
        $f.find('[name=unit_other]').val(r && r.service_type === 'other' ? (r.unit || '') : '');

        // Refresh searchable dropdowns so the visible label reflects the new
        // underlying value (custom-select only updates its label on click /
        // initial build, not on programmatic .val()).
        $f.find('.custom-select').each(function() {
            if (window.buildCustomDropdown) window.buildCustomDropdown(this);
            jQuery(this).trigger('change');
        });

        showServiceFields(t);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Bulk Add mode — Accommodation + Transport only.
    // Edit mode always uses single-entry; bulk is "Add" only.
    // ─────────────────────────────────────────────────────────────────────
    var bulkEligibleTypes = ['accommodation', 'transport'];
    var currentAddMode = 'single';

    function setAddMode(mode) {
        currentAddMode = mode;
        jQuery('#spAddModeTabs .nav-link').removeClass('active').filter('[data-add-mode="' + mode + '"]').addClass('active');
        jQuery('.add-mode-bulk').toggleClass('d-none', mode !== 'bulk');
        jQuery('.add-mode-single, .add-mode-single-only').toggleClass('d-none', mode !== 'single');
        // Update Save button label
        var $btn = jQuery('#spPriceSaveBtn');
        if (mode === 'bulk') {
            $btn.html('<i class="bi bi-check-lg me-1"></i> Save All Rows');
        } else {
            $btn.html('<i class="bi bi-check-lg me-1"></i> Save');
        }
    }

    function refreshBulkVisibility(serviceType, isEdit) {
        var eligible = bulkEligibleTypes.indexOf(serviceType) !== -1 && !isEdit;
        jQuery('#spAddModeTabs').toggleClass('d-none', !eligible);
        if (!eligible && currentAddMode === 'bulk') {
            setAddMode('single');
        }
        // Update label "room categories" vs "vehicles"
        jQuery('.bulk-mode-label').text(serviceType === 'transport' ? 'vehicle types' : 'room categories');
        // Quick-tier helper only applies to accommodation
        jQuery('.bulk-accom-only').toggleClass('d-none', serviceType !== 'accommodation');
    }

    function addBulkRow(serviceType) {
        var tplId = serviceType === 'transport' ? '#bulkRowTplTransport' : '#bulkRowTplAccommodation';
        var $tpl = jQuery(tplId);
        if (!$tpl.length) return;
        var $clone = jQuery($tpl[0].content.cloneNode(true));
        jQuery('#bulkRowsContainer').append($clone);
        // Re-wrap any custom-select that was cloned
        jQuery('#bulkRowsContainer .bulk-field[data-list-type]').each(function() {
            if (!jQuery(this).closest('.custom-select-wrap').length) {
                buildCustomDropdown(this);
            }
        });
    }

    function resetBulkRows(serviceType) {
        jQuery('#bulkRowsContainer').empty();
        addBulkRow(serviceType); // always start with one empty row
    }

    function collectBulkRows(serviceType) {
        var rows = [];
        jQuery('#bulkRowsContainer .bulk-row').each(function() {
            var $r = jQuery(this);
            var row = { service_type: serviceType };
            // Carry the existing record's id so the save endpoint UPDATEs
            // instead of creating a duplicate (used by the "All tiers" matrix).
            var existingId = $r.attr('data-row-id');
            if (existingId) row.id = existingId;
            $r.find('.bulk-field').each(function() {
                var field = jQuery(this).data('field');
                var val = jQuery(this).val();
                if (val !== null && val !== '') row[field] = val;
            });
            // Skip rows left blank. A missing price means "don't offer this
            // tier/vehicle" — silently drop. Existing rows with a cleared
            // price are left untouched; use the row's trash icon to delete.
            if (!row.price) return;
            var meaningful = Object.keys(row).filter(function(k) { return k !== 'service_type'; });
            if (meaningful.length > 0) rows.push(row);
        });
        return rows;
    }

    jQuery('#spAddModeTabs').on('click', '.nav-link', function() {
        var mode = jQuery(this).data('add-mode');
        setAddMode(mode);
        if (mode === 'bulk') {
            resetBulkRows(jQuery('#spServiceType').val());
        }
    });

    jQuery('#bulkAddRow').on('click', function() {
        addBulkRow(jQuery('#spServiceType').val());
    });

    // Quick-tier helper: pre-fill one row per comfort tier (Cat A / B / C / D)
    // for a chosen room category. Admin only types Rate + Total for each.
    var allComfortTiers = @json($accommodationCategories->pluck('name')->values());
    jQuery('#quickTierGenerateBtn').on('click', function() {
        var roomCat = jQuery('#quickTierRoomCategory').val();
        if (!roomCat) {
            window.showError && window.showError('Pick a Room Category first');
            return;
        }
        jQuery('#bulkRowsContainer').empty();
        allComfortTiers.forEach(function(tier) {
            addBulkRow('accommodation');
            var $row = jQuery('#bulkRowsContainer .bulk-row').last();
            $row.find('.bulk-field[data-field=room_category]').val(roomCat);
            $row.find('.bulk-field[data-field=comfort_tier]').val(tier);
        });
        jQuery('#bulkRowsContainer .custom-select').each(function() {
            if (window.buildCustomDropdown) window.buildCustomDropdown(this);
            jQuery(this).trigger('change');
        });
    });

    jQuery(document).on('click', '.bulk-row-remove', function() {
        var $rows = jQuery('#bulkRowsContainer .bulk-row');
        if ($rows.length <= 1) {
            // Keep at least one row — clear instead of removing.
            jQuery(this).closest('.bulk-row').find('.bulk-field').val('');
        } else {
            jQuery(this).closest('.bulk-row').remove();
        }
    });

    // Reset bulk container when service type changes (and bulk mode is active).
    jQuery('#spServiceType').on('change', function() {
        var t = this.value;
        var isEdit = !!jQuery('#spPriceForm [name=id]').val();
        refreshBulkVisibility(t, isEdit);
        if (currentAddMode === 'bulk') resetBulkRows(t);
    });

    // ─── Multi-service-type "add many in one go" flow ─────────────────────
    function resetMultiServiceModal() {
        jQuery('#spMultiForm .multi-svc-toggle').prop('checked', false);
        jQuery('#spMultiForm .multi-svc-body').addClass('d-none');
        jQuery('#spMultiForm .multi-svc-rows').empty();
    }
    function addMultiSvcRow(svc) {
        var tpl = document.getElementById('multiSvcRowTpl-' + svc);
        if (!tpl) return;
        var $container = jQuery('#spMultiForm .multi-svc-rows[data-svc="' + svc + '"]');
        $container.append(jQuery(tpl.content.cloneNode(true)));
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
    jQuery(document).on('change', '.multi-svc-toggle', function() {
        var svc = jQuery(this).data('svc');
        var $section = jQuery(this).closest('.multi-svc-section');
        $section.find('.multi-svc-body').toggleClass('d-none', !this.checked);
        if (this.checked) {
            var $rows = $section.find('.multi-svc-rows');
            if ($rows.children('.bulk-row').length === 0) addMultiSvcRow(svc);
        } else {
            $section.find('.multi-svc-rows').empty();
        }
    });
    jQuery(document).on('click', '.multi-svc-add-row', function() {
        addMultiSvcRow(jQuery(this).data('svc'));
    });
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
                    showAlert(saved + ' service rate(s) saved.', 'success');
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

    // "All tiers" — open bulk modal pre-populated with one row per comfort
    // tier for the clicked row's room_category, with existing rows carrying
    // their id so save() updates them in place.
    jQuery(document).on('click', '.sp-price-tier-matrix', function() {
        var clickedRow = priceCache[jQuery(this).closest('tr').data('id')];
        if (!clickedRow || !clickedRow.room_category) return;
        var roomCat = clickedRow.room_category;
        var existingByTier = {};
        Object.keys(priceCache).forEach(function(id) {
            var row = priceCache[id];
            if (row.service_type !== 'accommodation') return;
            if ((row.room_category || row.category) !== roomCat) return;
            if (row.comfort_tier) existingByTier[row.comfort_tier] = row;
        });

        fillPriceForm(null);
        jQuery('#spServiceType').val('accommodation').trigger('change');
        setAddMode('bulk');
        refreshBulkVisibility('accommodation', false);
        jQuery('#bulkRowsContainer').empty();

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

    jQuery('#spPriceForm').on('submit', function(e) {
        e.preventDefault();

        // BULK MODE — loop through bulk rows, save each.
        if (currentAddMode === 'bulk') {
            var type = jQuery('#spServiceType').val();
            var rows = collectBulkRows(type);
            if (!rows.length) {
                showAlert('Fill at least one row before saving.', 'warning');
                return;
            }
            var $btn = jQuery('#spPriceSaveBtn');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving ' + rows.length + ' rows...');

            // Save sequentially so a 422 on one row doesn't lose the others.
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
                var payload = jQuery.extend({
                    save_sp_pricing: 1,
                    provider_id: providerId,
                    is_active: 1,
                }, row);
                if (type === 'accommodation') {
                    // Mirror room_category → category for backward compat;
                    // unit is implicit "per night".
                    payload.category = payload.room_category;
                    payload.unit = 'per night';
                }
                ajaxPost(payload, function() {
                    saved++;
                    saveNext(i + 1);
                }, function(xhr) {
                    failed++;
                    var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'row ' + (i+1) + ' failed') : 'row ' + (i+1) + ' failed';
                    errors.push(msg);
                    saveNext(i + 1);
                });
            }
            saveNext(0);
            return;
        }

        // SINGLE MODE — original logic.
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
            data.room_category    = jQuery(this).find('[name=room_category]').val();
            data.comfort_tier     = jQuery(this).find('[name=comfort_tier]').val();
            data.total_rooms      = jQuery(this).find('[name=total_rooms]').val();
            data.meal_plan        = jQuery(this).find('[name=meal_plan]').val();
            data.price            = jQuery(this).find('[name=price_accommodation]').val();
            data.unit             = jQuery(this).find('[name=unit_accommodation]').val();
            data.category         = data.room_category;
        } else if (type === 'transport') {
            data.vehicle_type     = jQuery(this).find('[name=vehicle_type]').val();
            data.vehicle_capacity = jQuery(this).find('[name=vehicle_capacity]').val();
            data.driver_allowance = jQuery(this).find('[name=driver_allowance]').val();
            data.price            = jQuery(this).find('[name=price_transport]').val();
            data.unit             = jQuery(this).find('[name=unit_transport]').val();
        } else if (type === 'guide') {
            data.category         = jQuery(this).find('[name=category_guide]').val();
            data.specialties      = jQuery(this).find('[name=specialties_guide]').val();
            data.price            = jQuery(this).find('[name=price_guide]').val();
            data.unit             = jQuery(this).find('[name=unit_guide]').val();
        } else if (type === 'activity') {
            data.category         = jQuery(this).find('[name=category_activity]').val();
            data.min_group        = jQuery(this).find('[name=min_group]').val();
            data.max_group        = jQuery(this).find('[name=max_group]').val();
            data.specialties      = jQuery(this).find('[name=specialties_activity]').val();
            data.price            = jQuery(this).find('[name=price_activity]').val();
            data.unit             = jQuery(this).find('[name=unit_activity]').val();
        } else {
            data.category         = jQuery(this).find('[name=category_other]').val();
            data.price            = jQuery(this).find('[name=price_other]').val();
            data.unit             = jQuery(this).find('[name=unit_other]').val();
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
