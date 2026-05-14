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
                        $caps = [
                            ['name' => 'services_offered',         'label' => 'Services Offered',         'options' => $serviceTypes,            'current' => $provider->services_offered ?? []],
                            ['name' => 'accommodation_categories', 'label' => 'Accommodation Categories', 'options' => $accommodationCategories, 'current' => $provider->accommodation_categories ?? []],
                            ['name' => 'vehicle_types',            'label' => 'Vehicle Types',            'options' => $vehicleTypes,            'current' => $provider->vehicle_types ?? []],
                            ['name' => 'guide_types',              'label' => 'Guide Types',              'options' => $guideTypes,              'current' => $provider->guide_types ?? []],
                            ['name' => 'activity_types',           'label' => 'Activity Types',           'options' => $activityTypes,           'current' => $provider->activity_types ?? []],
                        ];
                    @endphp

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
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-danger d-none" id="spPriceBulkDelete"><i class="bi bi-trash me-1"></i> Delete Selected</button>
            <button type="button" class="btn btn-sm btn-success" id="spPriceAdd"><i class="bi bi-plus-lg me-1"></i> Add Service / Room</button>
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
            <div id="bulkRowsContainer"></div>
            <button type="button" class="btn btn-sm btn-outline-success mt-1" id="bulkAddRow"><i class="bi bi-plus-lg me-1"></i> Add another row</button>
        </div>

        {{-- Hidden templates for bulk rows — JS clones these. --}}
        <template id="bulkRowTplAccommodation">
            <div class="bulk-row card border p-2 mb-2" data-bulk-svc="accommodation">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Room Category <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm custom-select bulk-field" data-field="room_category" data-list-type="room_category">
                            <option value="">Pick...</option>
                            @foreach($roomCategories as $r)
                                <option value="{{ $r->name }}" data-desc="{{ $r->description }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                        <small class="form-help-text text-muted d-block mt-1"></small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Total <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-sm bulk-field" data-field="total_rooms" min="1" max="500" placeholder="4">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Rate/night ₹ <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-field" data-field="price" placeholder="2500">
                    </div>
                    <div class="col-md-3">
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

        {{-- ============= ACCOMMODATION FIELDS ============= --}}
        <div class="svc-fields" data-svc="accommodation">
            <div class="row g-2 mb-2">
                <div class="col-md-7">
                    <label class="form-label small">Room Category <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="room_category" data-list-type="room_category" data-required-for="accommodation">
                        <option value="">Select category...</option>
                        @foreach($roomCategories as $r)
                            <option value="{{ $r->name }}" data-desc="{{ $r->description }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-help-text text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Total Rooms <span class="text-danger">*</span></label>
                    <input type="number" min="1" max="500" class="form-control form-control-sm" name="total_rooms" placeholder="e.g. 4" data-required-for="accommodation">
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-md-5">
                    <label class="form-label small">Rate per night (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_accommodation" placeholder="e.g. 2500" data-price-for="accommodation">
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

            <input type="hidden" name="unit_accommodation" value="per night">
        </div>

        {{-- ============= TRANSPORT FIELDS ============= --}}
        <div class="svc-fields d-none" data-svc="transport">
            <div class="row g-2 mb-2">
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
                    <small class="text-muted">Number of passenger seats.</small>
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-md-5">
                    <label class="form-label small">Rate (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_transport" placeholder="e.g. 25" data-price-for="transport">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Unit <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="unit_transport" data-required-for="transport">
                        <option value="">Select...</option>
                        <option value="per km">per km</option>
                        <option value="per day">per day</option>
                        <option value="per trip">per trip</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Driver Allowance (₹/day)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="driver_allowance" placeholder="optional">
                </div>
            </div>
        </div>

        {{-- ============= GUIDE FIELDS ============= --}}
        <div class="svc-fields d-none" data-svc="guide">
            <div class="row g-2 mb-2">
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
            <div class="row g-2 mb-2">
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
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <label class="form-label small">Unit <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm custom-select" name="unit_activity" data-required-for="activity">
                        <option value="">Select...</option>
                        <option value="per person">per person</option>
                        <option value="per group">per group</option>
                        <option value="per day">per day</option>
                        <option value="per person per day">per person per day</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Min group size</label>
                    <input type="number" min="1" max="500" class="form-control form-control-sm" name="min_group" placeholder="e.g. 2">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Max group size</label>
                    <input type="number" min="1" max="500" class="form-control form-control-sm" name="max_group" placeholder="e.g. 12">
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small">Specialties (optional)</label>
                <input type="text" class="form-control form-control-sm" name="specialties_activity" placeholder="e.g. Equipment included, certified instructor required">
            </div>
        </div>

        {{-- ============= OTHER FIELDS ============= --}}
        <div class="svc-fields d-none" data-svc="other">
            <div class="row g-2 mb-2">
                <div class="col-md-7">
                    <label class="form-label small">Service Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="category_other" placeholder="e.g. Permit fee, Camera fee, Equipment rental" data-required-for="other">
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Rate (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_other" placeholder="e.g. 500" data-price-for="other">
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

jQuery('#providerEditForm').on('submit', function(e) {
    e.preventDefault();
    var btn = jQuery('#saveBtn');
    btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Saving...');

    var data = {
        edit_provider: 1,
        services_offered:         getDdValues('services_offered'),
        accommodation_categories: getDdValues('accommodation_categories'),
        vehicle_types:            getDdValues('vehicle_types'),
        guide_types:              getDdValues('guide_types'),
        activity_types:           getDdValues('activity_types')
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

    function loadPricing() {
        jQuery('#spPriceBody').html('<tr><td colspan="7" class="text-center text-muted small">Loading...</td></tr>');
        ajaxPost({ get_sp_pricing: 1, provider_id: providerId }, function(resp) {
            var rows = resp.rows || [];
            priceCache = {};
            if (!rows.length) { jQuery('#spPriceBody').html('<tr><td colspan="7" class="text-center text-muted small">No rates yet. Click <strong>Add Service / Room</strong> below to set up the first one.</td></tr>'); refreshBulkBtn(); return; }
            var html = '';
            rows.forEach(function(r) {
                priceCache[r.id] = r;

                // Build a service-type-specific "Details" cell
                var details = '';
                if (r.service_type === 'accommodation') {
                    var parts = [];
                    if (r.room_category) parts.push('<strong>' + spEscape(r.room_category) + '</strong>');
                    else if (r.category)  parts.push('<strong>' + spEscape(r.category) + '</strong>');
                    if (r.meal_plan)      parts.push('<span class="text-muted">' + spEscape(r.meal_plan) + '</span>');
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
                html += '<td><button class="btn btn-sm btn-outline-primary sp-price-edit" title="Edit"><i class="bi bi-pencil"></i></button></td>';
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
            ajaxPost({ delete_sp_pricing: 1, ids: ids }, function() { loadPricing(); showAlert('Deleted.', 'success'); });
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

        // Accommodation
        $f.find('[name=room_category]').val(r ? (r.room_category || r.category || '') : '');
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
            $r.find('.bulk-field').each(function() {
                var field = jQuery(this).data('field');
                var val = jQuery(this).val();
                if (val !== null && val !== '') row[field] = val;
            });
            // Skip entirely blank rows
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
