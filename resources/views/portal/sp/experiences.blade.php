@extends('portal.layout')
@section('title', 'My Experiences - HECO Partner')

@section('content')
<div class="container py-4 heco-portal">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1"><i class="bi bi-layers"></i> My Experiences</h4>
            <p class="text-muted small mb-0">
                Build the experiences you run. Each one goes to HECO for review — once approved
                it becomes visible to travellers and can be added to trips.
            </p>
        </div>
        <a href="{{ route('sp.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="card mb-4" id="spExpCard" data-provider-id="{{ $provider->id }}">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Experience</th>
                            <th>Type</th>
                            <th>Region</th>
                            <th>Duration</th>
                            <th>From ₹</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="spExpBody">
                        <tr><td colspan="7" class="text-center text-muted small">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm sp-btn-primary mt-2" id="spExpAdd">
                <i class="bi bi-plus-lg me-1"></i> Add Experience
            </button>
        </div>
    </div>
</div>

{{-- ============= EXPERIENCE MODAL ============= --}}
<div class="modal fade" id="spExpModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-layers"></i> <span id="spExpModalTitle">Add Experience</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body"><form id="spExpForm">
        <input type="hidden" name="id">

        <div class="alert alert-warning small py-2 d-none" id="spExpRejected">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Not approved.</strong> <span id="spExpRejectedReason"></span>
            Make the changes and submit again.
        </div>

        <div class="alert alert-info small py-2 d-none" id="spExpLiveEdit">
            <i class="bi bi-info-circle"></i>
            This experience is <strong>live</strong>. Your changes go to HECO for review —
            travellers keep seeing the approved version until they are accepted.
        </div>

        <div class="accordion" id="spExpAccordion">

            {{-- 1. Basics --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#spSecBasic">
                        <i class="bi bi-info-circle me-2"></i> Basic Information
                    </button>
                </h2>
                <div id="spSecBasic" class="accordion-collapse collapse show" data-bs-parent="#spExpAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label class="form-label small">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" name="name" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small">Slug</label>
                                <input type="text" class="form-control form-control-sm" name="slug">
                                <small class="text-muted">Leave blank and we build it from the name.</small>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small">Type <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm custom-select" name="type" required>
                                    <option value="">Select...</option>
                                    @foreach(['Trek','Cultural Immersion','Wildlife','Adventure','Nature','Wellness','Culinary','Homestay','Volunteering'] as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Region <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm custom-select" name="region_id" required>
                                    <option value="">Select...</option>
                                    @foreach($regions as $r)
                                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Regenerative Project</label>
                                <select class="form-select form-select-sm custom-select" name="regenerative_project_id">
                                    <option value="">None</option>
                                    @foreach($regenerativeProjects as $rp)
                                        <option value="{{ $rp->id }}">{{ $rp->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Short Description <span class="text-danger">*</span></label>
                                <textarea class="form-control form-control-sm" name="short_description" rows="2" maxlength="500" required></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Long Description</label>
                                <textarea class="form-control form-control-sm" name="long_description" rows="4"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">What makes it unique</label>
                                <textarea class="form-control form-control-sm" name="unique_description" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Cultural Context</label>
                                <textarea class="form-control form-control-sm" name="cultural_context" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Duration --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#spSecDuration">
                        <i class="bi bi-clock me-2"></i> Duration &amp; Schedule
                    </button>
                </h2>
                <div id="spSecDuration" class="accordion-collapse collapse" data-bs-parent="#spExpAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small">Duration Type <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm custom-select" name="duration_type" id="spExpDurationType" required>
                                    <option value="less_than_day">Less than a day</option>
                                    <option value="single_day" selected>Single day</option>
                                    <option value="multi_day">Multi-day</option>
                                </select>
                            </div>
                            <div class="col-md-2 sp-exp-hours d-none">
                                <label class="form-label small">Hours</label>
                                <input type="number" step="0.5" min="0" class="form-control form-control-sm" name="duration_hours">
                            </div>
                            <div class="col-md-2 sp-exp-multi">
                                <label class="form-label small">Days</label>
                                <input type="number" min="1" class="form-control form-control-sm" name="duration_days" value="1">
                            </div>
                            <div class="col-md-2 sp-exp-multi">
                                <label class="form-label small">Nights</label>
                                <input type="number" min="0" class="form-control form-control-sm" name="duration_nights" value="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Start Time</label>
                                <input type="text" class="form-control form-control-sm" name="start_time" placeholder="09:00">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">End Time</label>
                                <input type="text" class="form-control form-control-sm" name="end_time" placeholder="17:00">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Inclusions --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#spSecInclusions">
                        <i class="bi bi-check2-square me-2"></i> Inclusions
                    </button>
                </h2>
                <div id="spSecInclusions" class="accordion-collapse collapse" data-bs-parent="#spExpAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="includes_accommodation" value="1" id="spIncAccom">
                                    <label class="form-check-label small" for="spIncAccom">Accommodation</label>
                                </div>
                            </div>
                            <div class="col-md-8" id="spAccomCatGroup" style="display:none">
                                <select class="form-select form-select-sm custom-select" name="accommodation_category">
                                    <option value="">Accommodation category...</option>
                                    @foreach($accommodationCategories as $c)
                                        <option value="{{ $c->name }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @foreach([
                                'includes_meals_breakfast' => 'Breakfast',
                                'includes_meals_lunch' => 'Lunch',
                                'includes_meals_dinner' => 'Dinner',
                                'includes_guide' => 'Guide',
                                'includes_transport' => 'Transport',
                            ] as $field => $label)
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" id="sp_{{ $field }}">
                                        <label class="form-check-label small" for="sp_{{ $field }}">{{ $label }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. Location --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#spSecLocation">
                        <i class="bi bi-geo-alt me-2"></i> Location
                    </button>
                </h2>
                <div id="spSecLocation" class="accordion-collapse collapse" data-bs-parent="#spExpAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Area</label>
                                <input type="text" class="form-control form-control-sm" name="area" placeholder="e.g. Tirthan Valley, Banjar">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Min Altitude (m)</label>
                                <input type="number" class="form-control form-control-sm" name="altitude_min">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Max Altitude (m)</label>
                                <input type="number" class="form-control form-control-sm" name="altitude_max">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Start Latitude</label>
                                <input type="text" class="form-control form-control-sm" name="start_latitude">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Start Longitude</label>
                                <input type="text" class="form-control form-control-sm" name="start_longitude">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">End Latitude</label>
                                <input type="text" class="form-control form-control-sm" name="end_latitude">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">End Longitude</label>
                                <input type="text" class="form-control form-control-sm" name="end_longitude">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="trekking_required" value="1" id="spTrek">
                                    <label class="form-check-label small" for="spTrek">Trekking required</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="road_seasonal_closure" value="1" id="spRoadClose">
                                    <label class="form-check-label small" for="spRoadClose">Road closes seasonally</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 5. Requirements --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#spSecReq">
                        <i class="bi bi-clipboard-check me-2"></i> Requirements
                    </button>
                </h2>
                <div id="spSecReq" class="accordion-collapse collapse" data-bs-parent="#spExpAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label small">Difficulty <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm custom-select" name="difficulty_level" required>
                                    <option value="">Select</option>
                                    <option value="easy">Easy</option>
                                    <option value="moderate">Moderate</option>
                                    <option value="challenging">Challenging</option>
                                    <option value="extreme">Extreme</option>
                                </select>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label small">Fitness Requirements</label>
                                <textarea class="form-control form-control-sm" name="fitness_requirements" rows="2"></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Min Age</label>
                                <input type="number" min="0" class="form-control form-control-sm" name="age_min">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Max Age</label>
                                <input type="number" min="0" class="form-control form-control-sm" name="age_max">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Min Group</label>
                                <input type="number" min="1" class="form-control form-control-sm" name="group_size_min">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Max Group</label>
                                <input type="number" min="1" class="form-control form-control-sm" name="group_size_max">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Weather Dependency</label>
                                <textarea class="form-control form-control-sm" name="weather_dependency" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Cultural Sensitivities</label>
                                <textarea class="form-control form-control-sm" name="cultural_sensitivities" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Environmental Constraints</label>
                                <textarea class="form-control form-control-sm" name="environmental_constraints" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 6. Seasonality --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#spSecSeason">
                        <i class="bi bi-calendar-event me-2"></i> Seasonality
                    </button>
                </h2>
                <div id="spSecSeason" class="accordion-collapse collapse" data-bs-parent="#spExpAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Best Seasons</label>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($bestSeasons as $season)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="best_seasons[]" value="{{ $season }}" id="spSeason_{{ $season }}">
                                            <label class="form-check-label small" for="spSeason_{{ $season }}">{{ ucfirst($season) }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Seasonality Notes</label>
                                <textarea class="form-control form-control-sm" name="seasonality_notes" rows="2"></textarea>
                            </div>
                            @foreach([
                                'available_months' => 'Available Months',
                                'restricted_months' => 'Restricted Months',
                                'unavailable_months' => 'Unavailable Months',
                            ] as $field => $label)
                                <div class="col-md-6">
                                    <label class="form-label small">{{ $label }}</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $idx => $m)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="{{ $field }}[]" value="{{ $idx + 1 }}" id="sp_{{ $field }}_{{ $idx }}">
                                                <label class="form-check-label small" for="sp_{{ $field }}_{{ $idx }}">{{ $m }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- 7. Costing --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#spSecCost">
                        <i class="bi bi-cash-stack me-2"></i> Costing
                    </button>
                </h2>
                <div id="spSecCost" class="accordion-collapse collapse" data-bs-parent="#spExpAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small">Price Currency <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm custom-select" name="price_currency">
                                    @foreach($currencies as $cur)
                                        <option value="{{ $cur->code }}" {{ $cur->code === 'INR' ? 'selected' : '' }}>
                                            {{ $cur->symbol }} {{ $cur->code }} - {{ $cur->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @foreach([
                                'cost_accommodation' => 'Accommodation',
                                'cost_logistics' => 'Logistics',
                                'cost_guide' => 'Guide',
                                'cost_activities' => 'Activities',
                                'cost_other' => 'Other',
                                'single_supplement' => 'Single Supplement',
                            ] as $field => $label)
                                <div class="col-md-4">
                                    <label class="form-label small">{{ $label }}</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm sp-cost-input" name="{{ $field }}">
                                </div>
                            @endforeach
                            <div class="col-md-4">
                                <label class="form-label small">
                                    Total Per Person <span class="text-muted">(auto)</span>
                                </label>
                                <input type="number" step="0.01" min="0" readonly tabindex="-1"
                                       class="form-control form-control-sm bg-light" name="base_cost_per_person" id="spBaseCost">
                                <small class="text-muted">Cheapest tier below, or the sum of the components.</small>
                            </div>
                        </div>

                        <hr class="my-3">
                        <label class="form-label small fw-semibold">Per-person price tiers</label>
                        <p class="text-muted small mb-2">
                            Bigger groups usually pay less per head. The cheapest tier becomes the
                            "from" price travellers see. Leave empty to use the component costs above.
                        </p>
                        <div id="spSlabRows"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="spSlabAdd">
                            <i class="bi bi-plus-lg"></i> Add tier
                        </button>

                        <div class="mt-3">
                            <label class="form-label small">Seasonal Price Variation</label>
                            <textarea class="form-control form-control-sm" name="seasonal_price_variation" rows="3"
                                      placeholder='e.g. [{"season":"peak","months":[5,6],"adjust_percent":15}]'></textarea>
                            <small class="text-muted">Optional JSON list of seasonal adjustments.</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 8. Day-wise itinerary --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#spSecDays">
                        <i class="bi bi-signpost-split me-2"></i> Day-wise Itinerary
                    </button>
                </h2>
                <div id="spSecDays" class="accordion-collapse collapse" data-bs-parent="#spExpAccordion">
                    <div class="accordion-body">
                        <p class="text-muted small mb-2">
                            What happens on each day. Multi-day experiences can have as many days as you need.
                        </p>
                        <div id="spDayCards"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="spDayAdd">
                            <i class="bi bi-plus-lg"></i> Add another day
                        </button>
                    </div>
                </div>
            </div>

            {{-- 9. Practical --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#spSecPractical">
                        <i class="bi bi-backpack me-2"></i> Practical Information
                    </button>
                </h2>
                <div id="spSecPractical" class="accordion-collapse collapse" data-bs-parent="#spExpAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="osps_involved" value="1" id="spOspsInvolved">
                                    <label class="form-check-label small" for="spOspsInvolved">
                                        <i class="bi bi-people"></i> Other service providers involved
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small">OSP Services</label>
                                <textarea class="form-control form-control-sm" name="osp_services" rows="2"
                                          placeholder='e.g. ["Transport from Aut", "Camping equipment"]'></textarea>
                                <small class="text-muted">Optional JSON list of what other providers cover.</small>
                            </div>
                            @foreach([
                                'traveller_bring_list' => 'What travellers should bring',
                                'clothing_recommendations' => 'Clothing recommendations',
                                'health_notes' => 'Health notes',
                                'connectivity_notes' => 'Connectivity notes',
                                'cultural_etiquette' => 'Cultural etiquette',
                            ] as $field => $label)
                                <div class="col-md-6">
                                    <label class="form-label small">{{ $label }}</label>
                                    <textarea class="form-control form-control-sm" name="{{ $field }}" rows="2"></textarea>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- 10. Operational --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#spSecOps">
                        <i class="bi bi-exclamation-triangle me-2"></i> Operational Notes
                    </button>
                </h2>
                <div id="spSecOps" class="accordion-collapse collapse" data-bs-parent="#spExpAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            @foreach([
                                'operational_risks' => 'Operational risks',
                                'past_issues' => 'Past issues',
                                'backup_options' => 'Backup options',
                                'emergency_notes' => 'Emergency notes',
                            ] as $field => $label)
                                <div class="col-md-6">
                                    <label class="form-label small">{{ $label }}</label>
                                    <textarea class="form-control form-control-sm" name="{{ $field }}" rows="2"></textarea>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- 11. Photos --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#spSecMedia">
                        <i class="bi bi-images me-2"></i> Photos
                    </button>
                </h2>
                <div id="spSecMedia" class="accordion-collapse collapse" data-bs-parent="#spExpAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Card Image</label>
                                <div class="mb-2 d-none" id="spCardImageCurrent">
                                    <img src="" alt="Current card image" class="rounded sp-exp-card-preview" id="spCardImageThumb">
                                </div>
                                <input type="file" class="form-control form-control-sm" name="card_image" accept="image/*">
                                <small class="text-muted">The photo travellers see first. JPG, PNG or WebP.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Gallery Images</label>
                                <div class="mb-2 d-flex gap-1 flex-wrap d-none" id="spGalleryCurrent"></div>
                                <input type="file" class="form-control form-control-sm" name="gallery[]" accept="image/*" multiple>
                                <small class="text-muted">Pick several at once. New photos are added to what is already there.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <button type="submit" class="btn sp-btn-primary w-100 mt-3" id="spExpSaveBtn">
            <i class="bi bi-send me-1"></i> Submit for review
        </button>
    </form></div>
</div></div></div>

{{-- Confirm delete --}}
<div class="modal fade" id="spExpDeleteModal" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-body text-center py-4">
        <i class="bi bi-exclamation-triangle text-danger fs-2"></i>
        <p class="mb-1 mt-2">Remove this experience?</p>
        <p class="text-muted small mb-3">It stops being offered. Trips already using it are unaffected.</p>
        <button type="button" class="btn btn-sm btn-danger" id="spExpDeleteConfirm">Remove</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
    </div>
</div></div></div>
@endsection

@section('js')
<script>
var spExpRows = [];
var spDayCounter = 0;
var spSlabCounter = 0;
var spExpDeleteId = null;

var SP_DAY_INCLUSIONS = @json($dayInclusions);

function spEsc(str) {
    return jQuery('<div>').text(str == null ? '' : str).html();
}

function spStatusBadge(row) {
    if (row.approval_status === 'pending') {
        return '<span class="badge bg-warning text-dark">Under review</span>';
    }
    if (row.approval_status === 'rejected') {
        return '<span class="badge bg-danger">Not approved</span>';
    }
    var badge = row.is_active
        ? '<span class="badge bg-success">Live</span>'
        : '<span class="badge bg-secondary">Hidden</span>';
    // An approved experience can be live AND carry an unreviewed revision.
    if (row.has_pending_changes) {
        badge += ' <span class="badge bg-warning text-dark">Changes under review</span>';
    }
    return badge;
}

function spDurationLabel(row) {
    if (row.duration_type === 'multi_day') {
        return (row.duration_days || 1) + 'D / ' + (row.duration_nights || 0) + 'N';
    }
    if (row.duration_type === 'less_than_day') {
        return (row.duration_hours || '') + ' hrs';
    }
    return 'Single day';
}

function spLoadExperiences() {
    ajaxPost({ get_sp_experiences: 1 }, function(res) {
        spExpRows = res.experiences || [];
        var body = jQuery('#spExpBody').empty();

        if (!spExpRows.length) {
            body.append('<tr><td colspan="7" class="text-center text-muted small py-3">' +
                'No experiences yet. Add the first one to get started.</td></tr>');
            return;
        }

        jQuery.each(spExpRows, function(i, row) {
            var canToggle = row.approval_status === 'approved';
            var tr = '<tr>';
            tr += '<td><strong>' + spEsc(row.name) + '</strong><div class="text-muted small">' +
                  spEsc(row.short_description || '') + '</div></td>';
            tr += '<td class="small">' + spEsc(row.type || '-') + '</td>';
            tr += '<td class="small">' + spEsc(row.region ? row.region.name : '-') + '</td>';
            tr += '<td class="small">' + spDurationLabel(row) + '</td>';
            tr += '<td class="small">' + (row.base_cost_per_person || 0) + '</td>';
            tr += '<td>' + spStatusBadge(row) + '</td>';
            tr += '<td class="text-nowrap">';
            tr += '<button type="button" class="btn btn-sm btn-outline-secondary sp-exp-edit" data-id="' + row.id + '" title="Edit"><i class="bi bi-pencil"></i></button> ';
            if (canToggle) {
                tr += '<button type="button" class="btn btn-sm btn-outline-secondary sp-exp-toggle" data-id="' + row.id + '" title="Show / hide"><i class="bi bi-eye"></i></button> ';
            }
            tr += '<button type="button" class="btn btn-sm btn-outline-danger sp-exp-delete" data-id="' + row.id + '" title="Remove"><i class="bi bi-trash"></i></button>';
            tr += '</td></tr>';
            body.append(tr);
        });
    });
}

// ── Day cards ───────────────────────────────────────────────────────────
function spAddDayCard(data, removable) {
    var i = spDayCounter++;
    var container = jQuery('#spDayCards');
    var dayNum = container.children().length + 1;
    var inclusions = (data && data.inclusions) ? data.inclusions : [];

    var card = '<div class="card mb-2 sp-day-item">';
    card += '<div class="card-header py-2 d-flex align-items-center gap-2">';
    card += '<span class="badge bg-secondary sp-day-badge">Day ' + dayNum + '</span>';
    card += '<input type="hidden" class="sp-day-number" name="experience_days[' + i + '][day_number]" value="' + dayNum + '">';
    card += '<input type="text" class="form-control form-control-sm" name="experience_days[' + i + '][title]" placeholder="Day title" value="' + spEsc(data ? data.title : '') + '">';
    if (removable) {
        card += '<button type="button" class="btn btn-sm btn-outline-danger sp-day-remove"><i class="bi bi-x-lg"></i></button>';
    }
    card += '</div><div class="card-body py-2">';
    card += '<textarea class="form-control form-control-sm mb-2" rows="2" name="experience_days[' + i + '][short_description]" placeholder="What happens this day">' + spEsc(data ? data.short_description : '') + '</textarea>';
    card += '<label class="form-label small mb-1 fw-semibold">Inclusions</label><div class="d-flex flex-wrap gap-2">';
    jQuery.each(SP_DAY_INCLUSIONS, function(n, opt) {
        var checked = jQuery.inArray(opt, inclusions) !== -1 ? ' checked' : '';
        card += '<div class="form-check form-check-inline mb-0">';
        card += '<input class="form-check-input" type="checkbox" name="experience_days[' + i + '][inclusions][]" value="' + opt + '" id="spDayInc_' + i + '_' + opt + '"' + checked + '>';
        card += '<label class="form-check-label small" for="spDayInc_' + i + '_' + opt + '">' + opt.charAt(0).toUpperCase() + opt.slice(1) + '</label>';
        card += '</div>';
    });
    card += '</div></div></div>';
    container.append(card);
}

function spRenumberDays() {
    jQuery('#spDayCards .sp-day-item').each(function(idx) {
        jQuery(this).find('.sp-day-badge').text('Day ' + (idx + 1));
        jQuery(this).find('.sp-day-number').val(idx + 1);
    });
}

/// Multi-day experiences get a repeater; the rest get exactly one card.
function spRebuildDays(existingDays) {
    var isMulti = jQuery('#spExpDurationType').val() === 'multi_day';
    jQuery('#spDayAdd').toggleClass('d-none', !isMulti);
    jQuery('#spDayCards').empty();
    spDayCounter = 0;

    var days = existingDays || [];
    if (isMulti) {
        if (days.length) {
            jQuery.each(days, function(i, d) { spAddDayCard(d, true); });
        } else {
            spAddDayCard(null, true);
        }
    } else {
        spAddDayCard(days.length ? days[0] : null, false);
    }
}

/// Mirrors what the server does on save: the cheapest tier wins, otherwise the
/// components are summed. Kept read-only so the two can never disagree.
function spRecalcBaseCost() {
    var cheapest = null;
    jQuery('#spSlabRows .sp-slab-row').each(function() {
        var pax = parseInt(jQuery(this).find('input').eq(0).val(), 10);
        var price = parseFloat(jQuery(this).find('input').eq(1).val());
        if (pax >= 1 && price > 0 && (cheapest === null || price < cheapest)) {
            cheapest = price;
        }
    });

    if (cheapest === null) {
        cheapest = 0;
        jQuery('.sp-cost-input').each(function() {
            if (jQuery(this).attr('name') === 'single_supplement') return;
            cheapest += parseFloat(jQuery(this).val()) || 0;
        });
    }

    jQuery('#spBaseCost').val(cheapest ? cheapest.toFixed(2) : '');
}

// ── Price slabs ─────────────────────────────────────────────────────────
function spAddSlabRow(slab) {
    var i = spSlabCounter++;
    var row = '<div class="row g-2 mb-2 sp-slab-row">';
    row += '<div class="col-5"><input type="number" min="1" class="form-control form-control-sm" name="price_slabs[' + i + '][min_persons]" placeholder="Min guests" value="' + (slab ? slab.min_persons : '') + '"></div>';
    row += '<div class="col-5"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price_slabs[' + i + '][price_per_person]" placeholder="Price per person" value="' + (slab ? slab.price_per_person : '') + '"></div>';
    row += '<div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger sp-slab-remove"><i class="bi bi-x-lg"></i></button></div>';
    row += '</div>';
    jQuery('#spSlabRows').append(row);
}

// ── Modal ───────────────────────────────────────────────────────────────
function spOpenExperience(row) {
    var form = jQuery('#spExpForm')[0];
    form.reset();
    jQuery('#spSlabRows').empty();
    spSlabCounter = 0;
    jQuery('#spExpRejected').addClass('d-none');
    jQuery('#spExpLiveEdit').addClass('d-none');
    jQuery('#spCardImageCurrent').addClass('d-none');
    jQuery('#spGalleryCurrent').addClass('d-none').empty();

    jQuery('#spExpModalTitle').text(row ? 'Edit Experience' : 'Add Experience');
    jQuery('#spExpForm input[name=id]').val(row ? row.id : '');

    if (row) {
        jQuery.each(row, function(key, value) {
            var field = jQuery('#spExpForm [name="' + key + '"]');
            if (!field.length) return;
            if (field.attr('type') === 'checkbox') {
                field.prop('checked', value == 1);
            } else {
                field.val(value);
            }
        });

        jQuery.each(['best_seasons', 'available_months', 'restricted_months'], function(n, key) {
            var values = row[key] || [];
            jQuery('#spExpForm [name="' + key + '[]"]').each(function() {
                jQuery(this).prop('checked', jQuery.inArray(jQuery(this).val(), values.map(String)) !== -1);
            });
        });

        jQuery.each(row.price_slabs || [], function(n, slab) { spAddSlabRow(slab); });

        // These two are stored as JSON, so the raw value would render as
        // "[object Object]" through the generic field loop above.
        jQuery.each(['osp_services', 'seasonal_price_variation'], function(n, key) {
            var value = row[key];
            jQuery('#spExpForm [name="' + key + '"]').val(
                value ? JSON.stringify(value, null, 2) : ''
            );
        });

        // File inputs cannot be pre-filled — show what is already stored so the
        // provider knows leaving them empty keeps the existing photos.
        if (row.card_image) {
            jQuery('#spCardImageThumb').attr('src', row.card_image);
            jQuery('#spCardImageCurrent').removeClass('d-none');
        }
        if ((row.gallery || []).length) {
            var gallery = jQuery('#spGalleryCurrent').removeClass('d-none');
            jQuery.each(row.gallery, function(n, img) {
                gallery.append('<img src="' + spEsc(img) + '" alt="Gallery" class="rounded sp-exp-gallery-thumb">');
            });
        }

        if (row.approval_status === 'rejected') {
            jQuery('#spExpRejectedReason').text(row.rejection_reason || '');
            jQuery('#spExpRejected').removeClass('d-none');
        } else if (row.approval_status === 'approved') {
            jQuery('#spExpLiveEdit').removeClass('d-none');
        }
    }

    spToggleDurationFields();
    spRebuildDays(row ? row.days : null);
    spRecalcBaseCost();
    jQuery('#spAccomCatGroup').toggle(jQuery('#spIncAccom').is(':checked'));

    new bootstrap.Modal(jQuery('#spExpModal')[0]).show();
}

function spToggleDurationFields() {
    var type = jQuery('#spExpDurationType').val();
    jQuery('.sp-exp-hours').toggleClass('d-none', type !== 'less_than_day');
    jQuery('.sp-exp-multi').toggleClass('d-none', type === 'less_than_day');
}

jQuery(function() {
    spLoadExperiences();

    jQuery('#spExpAdd').on('click', function() { spOpenExperience(null); });

    jQuery(document).on('click', '.sp-exp-edit', function() {
        var id = jQuery(this).data('id');
        var row = null;
        jQuery.each(spExpRows, function(i, r) { if (r.id == id) row = r; });
        if (row) spOpenExperience(row);
    });

    jQuery(document).on('click', '.sp-exp-toggle', function() {
        ajaxPost({ toggle_sp_experience: 1, id: jQuery(this).data('id') }, function() {
            spLoadExperiences();
        });
    });

    jQuery(document).on('click', '.sp-exp-delete', function() {
        spExpDeleteId = jQuery(this).data('id');
        new bootstrap.Modal(jQuery('#spExpDeleteModal')[0]).show();
    });

    jQuery('#spExpDeleteConfirm').on('click', function() {
        ajaxPost({ delete_sp_experience: 1, id: spExpDeleteId }, function() {
            bootstrap.Modal.getInstance(jQuery('#spExpDeleteModal')[0]).hide();
            showAlert('Experience removed.', 'success');
            spLoadExperiences();
        });
    });

    jQuery('#spExpDurationType').on('change', function() {
        spToggleDurationFields();
        spRebuildDays(null);
    });

    jQuery('#spDayAdd').on('click', function() { spAddDayCard(null, true); spRenumberDays(); });
    jQuery(document).on('click', '.sp-day-remove', function() {
        jQuery(this).closest('.sp-day-item').remove();
        spRenumberDays();
    });

    jQuery('#spSlabAdd').on('click', function() { spAddSlabRow(null); spRecalcBaseCost(); });
    jQuery(document).on('click', '.sp-slab-remove', function() {
        jQuery(this).closest('.sp-slab-row').remove();
        spRecalcBaseCost();
    });
    jQuery(document).on('input', '#spSlabRows input, .sp-cost-input', spRecalcBaseCost);

    jQuery('#spIncAccom').on('change', function() {
        jQuery('#spAccomCatGroup').toggle(jQuery(this).is(':checked'));
    });

    jQuery('#spExpForm').on('submit', function(e) {
        e.preventDefault();
        var btn = jQuery('#spExpSaveBtn');
        var reset = function() {
            btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i> Submit for review');
        };
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Submitting...');

        // FormData, not serializeArray — the photo inputs are files, and
        // serializeArray silently drops them.
        var data = new FormData(this);
        data.append('save_sp_experience', 1);

        // Unchecked boxes post nothing; send an explicit 0 so clearing sticks.
        jQuery('#spExpForm input[type=checkbox]').each(function() {
            var name = jQuery(this).attr('name');
            if (name.indexOf('[]') !== -1) return;
            if (!jQuery(this).is(':checked')) data.append(name, 0);
        });

        jQuery.ajax({
            url: '/ajax',
            method: 'POST',
            data: data,
            processData: false,
            contentType: false,
            success: function() {
                bootstrap.Modal.getInstance(jQuery('#spExpModal')[0]).hide();
                showAlert('Submitted — HECO will review it shortly.', 'success');
                reset();
                spLoadExperiences();
            },
            error: function(xhr) {
                reset();
                var msg = (xhr && xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error : 'Could not save. Please check the form.';
                showAlert(msg, 'danger');
            }
        });
    });
});
</script>
@endsection
