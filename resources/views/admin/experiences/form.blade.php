@extends('admin.layout')
@section('title', ($experience ?? null) ? 'Edit Experience - HCT' : 'Create Experience - HCT')
@section('content')

@php
    $e = $experience ?? null;
    $regions = $regions ?? \App\Models\Region::where('is_active', 1)->orderBy('name')->get();
    $providers = $providers ?? \App\Models\ServiceProvider::where('provider_type', 'hlh')->where('status', 'approved')->orderBy('name')->get();
    $regenerativeProjects = $regenerativeProjects ?? \App\Models\RegenerativeProject::where('is_active', 1)->orderBy('name')->get();

    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $bestSeasons = $e && $e->best_seasons ? $e->best_seasons : [];
    $availableMonths = $e && $e->available_months ? $e->available_months : [];
    $restrictedMonths = $e && $e->restricted_months ? $e->restricted_months : [];
    $unavailableMonths = $e && $e->unavailable_months ? $e->unavailable_months : [];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <a href="{{ url('/experiences') }}" class="text-decoration-none text-muted me-2"><i class="bi bi-arrow-left"></i></a>
        <i class="bi bi-compass"></i> {{ $e ? 'Edit' : 'Create' }} Experience
    </h5>
    @if($e)
        <span class="badge bg-{{ $e->is_active ? 'success' : 'secondary' }}">{{ $e->is_active ? 'Active' : 'Inactive' }}</span>
    @endif
</div>

<form id="experienceForm" enctype="multipart/form-data" novalidate>
    @if($e)
        <input type="hidden" name="experience_id" value="{{ $e->id }}">
    @endif

    <div class="accordion" id="experienceAccordion">

        {{-- Section 1: Basic Info --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#sectionBasic">
                    <i class="bi bi-info-circle me-2"></i> Basic Information
                </button>
            </h2>
            <div id="sectionBasic" class="accordion-collapse collapse show" data-bs-parent="#experienceAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="expName" value="{{ $e->name ?? '' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Slug</label>
                            <input type="text" class="form-control" name="slug" id="expSlug" value="{{ $e->slug ?? '' }}" placeholder="Auto-generated from name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="type" required>
                                <option value="">Select Type</option>
                                @foreach(['trek','cultural','wildlife','adventure','wellness','culinary','homestay','volunteering'] as $t)
                                    <option value="{{ $t }}" {{ $e && $e->type === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">HLH Provider <span class="text-danger">*</span></label>
                            <select class="form-select" name="hlh_id" required>
                                <option value="">Select Provider</option>
                                @foreach($providers as $prov)
                                    <option value="{{ $prov->id }}" {{ $e && $e->hlh_id == $prov->id ? 'selected' : '' }}>{{ $prov->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Region <span class="text-danger">*</span></label>
                            <select class="form-select" name="region_id" required>
                                <option value="">Select Region</option>
                                @foreach($regions as $r)
                                    <option value="{{ $r->id }}" {{ $e && $e->region_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Regenerative Project</label>
                            <select class="form-select" name="regenerative_project_id">
                                <option value="">None</option>
                                @foreach($regenerativeProjects as $rp)
                                    <option value="{{ $rp->id }}" {{ $e && $e->regenerative_project_id == $rp->id ? 'selected' : '' }}>{{ $rp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Short Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="short_description" rows="2" maxlength="500" required>{{ $e->short_description ?? '' }}</textarea>
                            <small class="text-muted"><span id="shortDescCount">{{ strlen($e->short_description ?? '') }}</span>/500 characters</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Long Description</label>
                            <textarea class="form-control" name="long_description" rows="5">{{ $e->long_description ?? '' }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unique Description</label>
                            <textarea class="form-control" name="unique_description" rows="3">{{ $e->unique_description ?? '' }}</textarea>
                            <small class="text-muted">What makes this experience unique</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cultural Context</label>
                            <textarea class="form-control" name="cultural_context" rows="3">{{ $e->cultural_context ?? '' }}</textarea>
                            <small class="text-muted">Cultural significance and background</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Duration & Schedule --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionDuration">
                    <i class="bi bi-clock me-2"></i> Duration & Schedule
                </button>
            </h2>
            <div id="sectionDuration" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Duration Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input duration-type-radio" type="radio" name="duration_type" id="dtLessThanDay" value="less_than_day" {{ $e && $e->duration_type === 'less_than_day' ? 'checked' : (!$e ? 'checked' : '') }}>
                                    <label class="form-check-label" for="dtLessThanDay">Less Than a Day</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input duration-type-radio" type="radio" name="duration_type" id="dtSingleDay" value="single_day" {{ $e && $e->duration_type === 'single_day' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="dtSingleDay">Single Day</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input duration-type-radio" type="radio" name="duration_type" id="dtMultiDay" value="multi_day" {{ $e && $e->duration_type === 'multi_day' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="dtMultiDay">Multi-Day</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3" id="durationHoursGroup">
                            <label class="form-label">Duration Hours</label>
                            <input type="number" class="form-control" name="duration_hours" value="{{ $e->duration_hours ?? '' }}" step="0.5" min="0.5" max="24">
                        </div>
                        <div class="col-md-3 multi-day-field" id="durationDaysGroup">
                            <label class="form-label">Duration Days</label>
                            <input type="number" class="form-control" name="duration_days" value="{{ $e->duration_days ?? '' }}" min="1">
                        </div>
                        <div class="col-md-3 multi-day-field" id="durationNightsGroup">
                            <label class="form-label">Duration Nights</label>
                            <input type="number" class="form-control" name="duration_nights" value="{{ $e->duration_nights ?? '' }}" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" name="start_time" value="{{ $e->start_time ?? '' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time" value="{{ $e->end_time ?? '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 3: Inclusions --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionInclusions">
                    <i class="bi bi-check2-square me-2"></i> Inclusions
                </button>
            </h2>
            <div id="sectionInclusions" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="includes_accommodation" id="includesAccommodation" value="1" {{ $e && $e->includes_accommodation ? 'checked' : '' }}>
                                <label class="form-check-label" for="includesAccommodation"><i class="bi bi-house"></i> Includes Accommodation</label>
                            </div>
                        </div>
                        <div class="col-md-4" id="accommodationCategoryGroup" style="{{ $e && $e->includes_accommodation ? '' : 'display:none;' }}">
                            <label class="form-label">Accommodation Category</label>
                            <select class="form-select" name="accommodation_category">
                                <option value="">Select</option>
                                @foreach(['budget','standard','premium','luxury','camping','homestay'] as $ac)
                                    <option value="{{ $ac }}" {{ $e && $e->accommodation_category === $ac ? 'selected' : '' }}>{{ ucfirst($ac) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label d-block">Meals Included</label>
                            <div class="d-flex gap-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="includes_meals_breakfast" id="mealsBreakfast" value="1" {{ $e && $e->includes_meals_breakfast ? 'checked' : '' }}>
                                    <label class="form-check-label" for="mealsBreakfast"><i class="bi bi-cup-hot"></i> Breakfast</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="includes_meals_lunch" id="mealsLunch" value="1" {{ $e && $e->includes_meals_lunch ? 'checked' : '' }}>
                                    <label class="form-check-label" for="mealsLunch"><i class="bi bi-egg-fried"></i> Lunch</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="includes_meals_dinner" id="mealsDinner" value="1" {{ $e && $e->includes_meals_dinner ? 'checked' : '' }}>
                                    <label class="form-check-label" for="mealsDinner"><i class="bi bi-moon-stars"></i> Dinner</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label d-block">Other Inclusions</label>
                            <div class="d-flex gap-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="includes_guide" id="includesGuide" value="1" {{ $e && $e->includes_guide ? 'checked' : '' }}>
                                    <label class="form-check-label" for="includesGuide"><i class="bi bi-person-badge"></i> Guide</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="includes_transport" id="includesTransport" value="1" {{ $e && $e->includes_transport ? 'checked' : '' }}>
                                    <label class="form-check-label" for="includesTransport"><i class="bi bi-truck"></i> Transport</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 4: Location & Geography --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionLocation">
                    <i class="bi bi-geo-alt me-2"></i> Location & Geography
                </button>
            </h2>
            <div id="sectionLocation" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Start Latitude</label>
                            <input type="number" class="form-control" name="start_latitude" value="{{ $e->start_latitude ?? '' }}" step="any" placeholder="e.g. 31.1048">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Longitude</label>
                            <input type="number" class="form-control" name="start_longitude" value="{{ $e->start_longitude ?? '' }}" step="any" placeholder="e.g. 77.1734">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Latitude</label>
                            <input type="number" class="form-control" name="end_latitude" value="{{ $e->end_latitude ?? '' }}" step="any">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Longitude</label>
                            <input type="number" class="form-control" name="end_longitude" value="{{ $e->end_longitude ?? '' }}" step="any">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Area</label>
                            <input type="text" class="form-control" name="area" value="{{ $e->area ?? '' }}" placeholder="e.g. Tirthan Valley">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Altitude Min (m)</label>
                            <input type="number" class="form-control" name="altitude_min" value="{{ $e->altitude_min ?? '' }}" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Altitude Max (m)</label>
                            <input type="number" class="form-control" name="altitude_max" value="{{ $e->altitude_max ?? '' }}" min="0">
                        </div>
                        <div class="col-md-2">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="trekking_required" id="trekkingRequired" value="1" {{ $e && $e->trekking_required ? 'checked' : '' }}>
                                <label class="form-check-label" for="trekkingRequired">Trekking Required</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="road_seasonal_closure" id="roadClosure" value="1" {{ $e && $e->road_seasonal_closure ? 'checked' : '' }}>
                                <label class="form-check-label" for="roadClosure">Road Seasonal Closure</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 5: Requirements & Constraints --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionRequirements">
                    <i class="bi bi-shield-check me-2"></i> Requirements & Constraints
                </button>
            </h2>
            <div id="sectionRequirements" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Difficulty Level <span class="text-danger">*</span></label>
                            <select class="form-select" name="difficulty_level" required>
                                <option value="">Select</option>
                                <option value="easy" {{ $e && $e->difficulty_level === 'easy' ? 'selected' : '' }}>Easy</option>
                                <option value="moderate" {{ $e && $e->difficulty_level === 'moderate' ? 'selected' : '' }}>Moderate</option>
                                <option value="challenging" {{ $e && $e->difficulty_level === 'challenging' ? 'selected' : '' }}>Challenging</option>
                                <option value="extreme" {{ $e && $e->difficulty_level === 'extreme' ? 'selected' : '' }}>Extreme</option>
                            </select>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Fitness Requirements</label>
                            <textarea class="form-control" name="fitness_requirements" rows="2">{{ $e->fitness_requirements ?? '' }}</textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Minimum Age</label>
                            <input type="number" class="form-control" name="age_min" value="{{ $e->age_min ?? '' }}" min="0" max="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Maximum Age</label>
                            <input type="number" class="form-control" name="age_max" value="{{ $e->age_max ?? '' }}" min="0" max="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Group Size Min</label>
                            <input type="number" class="form-control" name="group_size_min" value="{{ $e->group_size_min ?? '' }}" min="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Group Size Max</label>
                            <input type="number" class="form-control" name="group_size_max" value="{{ $e->group_size_max ?? '' }}" min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Weather Dependency</label>
                            <textarea class="form-control" name="weather_dependency" rows="2">{{ $e->weather_dependency ?? '' }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cultural Sensitivities</label>
                            <textarea class="form-control" name="cultural_sensitivities" rows="2">{{ $e->cultural_sensitivities ?? '' }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Environmental Constraints</label>
                            <textarea class="form-control" name="environmental_constraints" rows="2">{{ $e->environmental_constraints ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 6: Seasonality --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionSeasonality">
                    <i class="bi bi-calendar-event me-2"></i> Seasonality
                </button>
            </h2>
            <div id="sectionSeasonality" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Best Seasons</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach(['spring','summer','monsoon','autumn','winter'] as $season)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="best_seasons[]" value="{{ $season }}" id="season_{{ $season }}"
                                            {{ in_array($season, $bestSeasons) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="season_{{ $season }}">{{ ucfirst($season) }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Seasonality Notes</label>
                            <textarea class="form-control" name="seasonality_notes" rows="2">{{ $e->seasonality_notes ?? '' }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Available Months</label>
                            <div class="border rounded p-2" style="max-height: 180px; overflow-y: auto;">
                                @foreach($months as $idx => $month)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="available_months[]" value="{{ $idx + 1 }}" id="avail_{{ $idx }}"
                                            {{ in_array($idx + 1, $availableMonths) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="avail_{{ $idx }}">{{ $month }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Restricted Months</label>
                            <div class="border rounded p-2" style="max-height: 180px; overflow-y: auto;">
                                @foreach($months as $idx => $month)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="restricted_months[]" value="{{ $idx + 1 }}" id="restr_{{ $idx }}"
                                            {{ in_array($idx + 1, $restrictedMonths) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="restr_{{ $idx }}">{{ $month }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unavailable Months</label>
                            <div class="border rounded p-2" style="max-height: 180px; overflow-y: auto;">
                                @foreach($months as $idx => $month)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="unavailable_months[]" value="{{ $idx + 1 }}" id="unavail_{{ $idx }}"
                                            {{ in_array($idx + 1, $unavailableMonths) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="unavail_{{ $idx }}">{{ $month }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 7: Costing --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionCosting">
                    <i class="bi bi-currency-exchange me-2"></i> Costing
                </button>
            </h2>
            <div id="sectionCosting" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Price Currency <span class="text-danger">*</span></label>
                            <select class="form-select" name="price_currency" id="priceCurrency">
                                @foreach(\App\Models\Currency::where('is_active', true)->orderBy('sort_order')->get() as $cur)
                                    <option value="{{ $cur->code }}" data-symbol="{{ $cur->symbol }}" {{ (isset($e) && $e->price_currency === $cur->code) || (!isset($e) && $cur->code === 'INR') ? 'selected' : '' }}>
                                        {{ $cur->symbol }} {{ $cur->code }} - {{ $cur->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Base Cost Per Person <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text cost-symbol">{{ isset($e) && $e->price_currency ? \App\Models\Currency::where('code', $e->price_currency)->value('symbol') ?? '₹' : '₹' }}</span>
                                <input type="number" class="form-control" name="base_cost_per_person" value="{{ $e->base_cost_per_person ?? '' }}" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cost Accommodation</label>
                            <div class="input-group">
                                <span class="input-group-text cost-symbol">₹</span>
                                <input type="number" class="form-control" name="cost_accommodation" value="{{ $e->cost_accommodation ?? '' }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cost Logistics</label>
                            <div class="input-group">
                                <span class="input-group-text cost-symbol">₹</span>
                                <input type="number" class="form-control" name="cost_logistics" value="{{ $e->cost_logistics ?? '' }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cost Guide</label>
                            <div class="input-group">
                                <span class="input-group-text cost-symbol">₹</span>
                                <input type="number" class="form-control" name="cost_guide" value="{{ $e->cost_guide ?? '' }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cost Activities</label>
                            <div class="input-group">
                                <span class="input-group-text cost-symbol">₹</span>
                                <input type="number" class="form-control" name="cost_activities" value="{{ $e->cost_activities ?? '' }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cost Other</label>
                            <div class="input-group">
                                <span class="input-group-text cost-symbol">₹</span>
                                <input type="number" class="form-control" name="cost_other" value="{{ $e->cost_other ?? '' }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Single Supplement</label>
                            <div class="input-group">
                                <span class="input-group-text cost-symbol">₹</span>
                                <input type="number" class="form-control" name="single_supplement" value="{{ $e->single_supplement ?? '' }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Seasonal Price Variation</label>
                            <textarea class="form-control" name="seasonal_price_variation" rows="3" placeholder='e.g. [{"season":"winter","multiplier":1.2},{"season":"monsoon","multiplier":0.8}]'>{{ $e && $e->seasonal_price_variation ? json_encode($e->seasonal_price_variation, JSON_PRETTY_PRINT) : '' }}</textarea>
                            <small class="text-muted">JSON array of seasonal price adjustments</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 8: OSP & Practical Info --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionPractical">
                    <i class="bi bi-journal-text me-2"></i> OSP & Practical Info
                </button>
            </h2>
            <div id="sectionPractical" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="osps_involved" id="ospsInvolved" value="1" {{ $e && $e->osps_involved ? 'checked' : '' }}>
                                <label class="form-check-label" for="ospsInvolved"><i class="bi bi-people"></i> OSPs Involved</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">OSP Services</label>
                            <textarea class="form-control" name="osp_services" rows="3" placeholder='e.g. [{"name":"Local guide","provider":"Village Association"}]'>{{ $e && $e->osp_services ? json_encode($e->osp_services, JSON_PRETTY_PRINT) : '' }}</textarea>
                            <small class="text-muted">JSON array of OSP service details</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Traveller Bring List</label>
                            <textarea class="form-control" name="traveller_bring_list" rows="3" placeholder="List of things travellers should bring...">{{ $e->traveller_bring_list ?? '' }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Clothing Recommendations</label>
                            <textarea class="form-control" name="clothing_recommendations" rows="3" placeholder="Recommended clothing and gear...">{{ $e->clothing_recommendations ?? '' }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Health Notes</label>
                            <textarea class="form-control" name="health_notes" rows="3" placeholder="Health advisories, vaccinations, altitude sickness...">{{ $e->health_notes ?? '' }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Connectivity Notes</label>
                            <textarea class="form-control" name="connectivity_notes" rows="3" placeholder="Mobile/internet connectivity info...">{{ $e->connectivity_notes ?? '' }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cultural Etiquette</label>
                            <textarea class="form-control" name="cultural_etiquette" rows="3" placeholder="Do's and don'ts, local customs...">{{ $e->cultural_etiquette ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 9: Operational (HCT Internal) --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionOperational">
                    <i class="bi bi-lock me-2"></i> Operational (HCT Internal)
                </button>
            </h2>
            <div id="sectionOperational" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                <div class="accordion-body">
                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-info-circle"></i> This section is for internal HCT use only and is not visible to travellers or providers.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Operational Risks</label>
                            <textarea class="form-control" name="operational_risks" rows="3" placeholder="Known risks and mitigation strategies...">{{ $e->operational_risks ?? '' }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Past Issues</label>
                            <textarea class="form-control" name="past_issues" rows="3" placeholder="Historical issues, complaints, incidents...">{{ $e->past_issues ?? '' }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Backup Options</label>
                            <textarea class="form-control" name="backup_options" rows="3" placeholder="Alternative plans if experience cannot run...">{{ $e->backup_options ?? '' }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency Notes</label>
                            <textarea class="form-control" name="emergency_notes" rows="3" placeholder="Emergency contacts, nearest hospital, evacuation routes...">{{ $e->emergency_notes ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 10: Media & Settings --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionMedia">
                    <i class="bi bi-image me-2"></i> Media & Settings
                </button>
            </h2>
            <div id="sectionMedia" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Card Image</label>
                            @if($e && $e->card_image)
                                <div class="mb-2">
                                    <img src="{{ $e->card_image }}" class="rounded" style="max-height: 120px;" alt="Card image" id="currentCardImage">
                                </div>
                            @endif
                            <input type="file" class="form-control" name="card_image" accept="image/*" id="cardImageInput">
                            <small class="text-muted">Recommended: 800x600px, JPG or PNG</small>
                            <div id="cardImagePreview" class="mt-2"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gallery Images</label>
                            @if($e && $e->gallery && count($e->gallery))
                                <div class="mb-2 d-flex gap-1 flex-wrap" id="currentGallery">
                                    @foreach($e->gallery as $gidx => $img)
                                        <div class="position-relative">
                                            <img src="{{ $img }}" class="rounded" style="height: 60px; width: 80px; object-fit: cover;" alt="Gallery">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <input type="file" class="form-control" name="gallery[]" accept="image/*" multiple>
                            <small class="text-muted">Select multiple images for the gallery</small>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ !$e || $e->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">Active</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" value="{{ $e->sort_order ?? 0 }}" min="0">
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="d-flex justify-content-between mt-4 mb-3">
        <a href="{{ url('/experiences') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Cancel
        </a>
        <button type="submit" class="btn btn-success btn-lg" id="btnSave">
            <i class="bi bi-check-lg"></i> Save Experience
        </button>
    </div>
</form>

@endsection

@section('js')
<script>
jQuery(function() {
    toggleDurationFields();
    toggleAccommodationCategory();

    // Update currency symbols when currency dropdown changes
    jQuery('#priceCurrency').on('change', function() {
        var selected = jQuery(this).find(':selected');
        var symbol = selected.data('symbol') || '₹';
        jQuery('#sectionCosting .cost-symbol').text(symbol);
    });
});

// Auto-slug generation from name
jQuery('#expName').on('keyup blur', function() {
    var slug = jQuery(this).val()
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    jQuery('#expSlug').val(slug);
});

// Short description character count
jQuery('textarea[name="short_description"]').on('input', function() {
    jQuery('#shortDescCount').text(jQuery(this).val().length);
});

// Duration type toggle
jQuery('.duration-type-radio').on('change', function() {
    toggleDurationFields();
});

function toggleDurationFields() {
    var durationType = jQuery('input[name="duration_type"]:checked').val();
    if (durationType === 'less_than_day') {
        jQuery('#durationHoursGroup').show();
        jQuery('.multi-day-field').hide();
    } else if (durationType === 'multi_day') {
        jQuery('#durationHoursGroup').hide();
        jQuery('.multi-day-field').show();
    } else {
        jQuery('#durationHoursGroup').hide();
        jQuery('.multi-day-field').hide();
    }
}

// Accommodation category toggle
jQuery('#includesAccommodation').on('change', function() {
    toggleAccommodationCategory();
});

function toggleAccommodationCategory() {
    if (jQuery('#includesAccommodation').is(':checked')) {
        jQuery('#accommodationCategoryGroup').show();
    } else {
        jQuery('#accommodationCategoryGroup').hide();
    }
}

// Card image preview
jQuery('#cardImageInput').on('change', function() {
    var file = this.files[0];
    if (file) {
        var reader = new FileReader();
        reader.onload = function(ev) {
            jQuery('#cardImagePreview').html('<img src="' + ev.target.result + '" class="rounded" style="max-height: 100px;" alt="Preview">');
        };
        reader.readAsDataURL(file);
    } else {
        jQuery('#cardImagePreview').empty();
    }
});

// Form submission
jQuery('#experienceForm').on('submit', function(ev) {
    ev.preventDefault();

    // Expand all collapsed accordion sections before validation
    jQuery('#experienceAccordion .accordion-collapse:not(.show)').each(function() {
        new bootstrap.Collapse(this, { toggle: true });
    });

    // Check required fields
    var firstInvalid = null;
    jQuery(this).find('[required]').each(function() {
        if (!jQuery(this).val()) {
            jQuery(this).addClass('is-invalid');
            if (!firstInvalid) firstInvalid = jQuery(this);
        } else {
            jQuery(this).removeClass('is-invalid');
        }
    });
    if (firstInvalid) {
        setTimeout(function() {
            firstInvalid.focus();
            firstInvalid[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 400);
        showAlert('Please fill in all required fields', 'danger');
        return;
    }

    var form = this;
    var formData = new FormData(form);
    formData.append('save_experience', 1);

    // Handle unchecked checkboxes - send 0 for boolean fields
    var booleanFields = [
        'includes_accommodation', 'includes_meals_breakfast', 'includes_meals_lunch',
        'includes_meals_dinner', 'includes_guide', 'includes_transport',
        'trekking_required', 'road_seasonal_closure', 'osps_involved', 'is_active'
    ];
    booleanFields.forEach(function(field) {
        if (!formData.get(field)) {
            formData.set(field, '0');
        }
    });

    var btn = jQuery('#btnSave');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

    jQuery.ajax({
        url: '/ajax',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(resp) {
            showAlert('Experience saved successfully!', 'success');
            setTimeout(function() {
                window.location.href = "{{ url('/experiences') }}";
            }, 1000);
        },
        error: function(xhr) {
            btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Save Experience');
            var msg = 'Failed to save experience';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                } else if (xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    var errorList = [];
                    for (var field in errors) {
                        errorList.push(errors[field].join(', '));
                    }
                    msg = errorList.join('<br>');
                }
            }
            showAlert(msg, 'danger');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
});
</script>
@endsection
