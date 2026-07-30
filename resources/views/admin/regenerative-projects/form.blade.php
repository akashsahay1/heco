@extends('admin.layout')
@section('title', ($project ?? null) ? 'Edit Project - HCT' : 'Create Project - HCT')
@section('content')

@php
    $p = $project ?? null;
    $regions = $regions ?? \App\Models\Region::where('is_active', 1)->orderBy('name')->get();
    $allRegions = \App\Models\Region::orderBy('name')->get();
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <a href="{{ url('/regenerative-projects') }}" class="text-decoration-none text-muted me-2"><i class="bi bi-arrow-left"></i></a>
        <i class="bi bi-tree"></i> {{ $p ? 'Edit' : 'Create' }} Regenerative Project
    </h5>
</div>

<form id="projectForm" enctype="multipart/form-data">
    @if($p)
        <input type="hidden" name="project_id" value="{{ $p->id }}">
    @endif

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle"></i> Basic Information</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" value="{{ $p->name ?? '' }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Region <span class="text-danger">*</span></label>
                    <select class="form-select custom-select" name="region_id" required>
                        <option value="">Select Region</option>
                        @foreach($regions as $r)
                            <option value="{{ $r->id }}" {{ $p && $p->region_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Local Association</label>
                    <input type="text" class="form-control" name="local_association" value="{{ $p->local_association ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Action Type <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="action_type" value="{{ $p->action_type ?? '' }}" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Short Description</label>
                    <textarea class="form-control" name="short_description" rows="2" maxlength="500">{{ $p->short_description ?? '' }}</textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Detailed Description</label>
                    <textarea class="form-control" name="detailed_description" rows="5">{{ $p->detailed_description ?? '' }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-bar-chart"></i> Impact & Measurement</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Impact Unit <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="impact_unit" value="{{ $p->impact_unit ?? '' }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Measurement Frequency</label>
                    <select class="form-select custom-select" name="measurement_frequency">
                        <option value="">Select</option>
                        <option value="one_time" {{ $p && $p->measurement_frequency === 'one_time' ? 'selected' : '' }}>One Time</option>
                        <option value="periodic" {{ $p && $p->measurement_frequency === 'periodic' ? 'selected' : '' }}>Periodic</option>
                        <option value="cumulative" {{ $p && $p->measurement_frequency === 'cumulative' ? 'selected' : '' }}>Cumulative</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Conversion Rules</label>
                    <textarea class="form-control" name="conversion_rules" rows="3">{{ $p->conversion_rules ?? '' }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-currency-rupee"></i> Budget & Costs</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Reference Budget</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-currency-rupee"></i></span>
                        <input type="number" class="form-control" name="reference_budget" value="{{ $p->reference_budget ?? '' }}" step="0.01" min="0">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cost Per Impact Unit</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-currency-rupee"></i></span>
                        <input type="number" class="form-control" name="cost_per_impact_unit" value="{{ $p->cost_per_impact_unit ?? '' }}" step="0.01" min="0">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-clock-history"></i> Operational Periods</h6></div>
        <div class="card-body">
            <div class="row g-3">
                {{-- Both were textareas asking for a hand-written JSON array of
                     period objects. A period is a pair of dates, so it is asked
                     for as a pair of dates. --}}
                @foreach([
                    ['active_periods', 'Active Periods', 'When the project is running.'],
                    ['paused_periods', 'Paused Periods', 'When it is on hold — monsoon, funding gaps.'],
                ] as [$field, $label, $hint])
                    <div class="col-md-4">
                        <label class="form-label">{{ $label }}</label>
                        @php
                            $periods = $p && is_array($p->$field) ? $p->$field : [];
                        @endphp
                        <div class="period-rows" data-field="{{ $field }}">
                            @foreach($periods as $i => $period)
                                <div class="input-group input-group-sm mb-1 period-row">
                                    <input type="date" class="form-control" name="{{ $field }}[{{ $i }}][start]"
                                           value="{{ $period['start'] ?? '' }}" aria-label="Start date">
                                    <span class="input-group-text">to</span>
                                    <input type="date" class="form-control" name="{{ $field }}[{{ $i }}][end]"
                                           value="{{ $period['end'] ?? '' }}" aria-label="End date">
                                    <button type="button" class="btn btn-outline-secondary remove-period" title="Remove">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary add-period" data-field="{{ $field }}">
                            <i class="bi bi-plus"></i> Add period
                        </button>
                        <small class="text-muted d-block mt-1">{{ $hint }}</small>
                    </div>
                @endforeach
                <div class="col-md-4">
                    <label class="form-label">Operational Constraints</label>
                    <textarea class="form-control" name="operational_constraints" rows="3">{{ $p->operational_constraints ?? '' }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-image"></i> Media</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Main Image</label>
                    @if($p && $p->main_image)
                        <div class="mb-2">
                            <img src="{{ $p->main_image }}" class="rounded img-preview-md" alt="Current image">
                        </div>
                    @endif
                    <input type="file" class="form-control" name="main_image" accept="image/*">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gallery Images</label>
                    @if($p && $p->gallery && count($p->gallery))
                        <div class="mb-2 d-flex gap-1 flex-wrap">
                            @foreach($p->gallery as $img)
                                <img src="{{ $img }}" class="rounded img-thumb-gallery" alt="Gallery">
                            @endforeach
                        </div>
                    @endif
                    <input type="file" class="form-control" name="gallery[]" accept="image/*" multiple>
                    <small class="text-muted">Select multiple images for the gallery</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-gear"></i> Settings</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ !$p || $p->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Fallback for Regions</label>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($allRegions as $r)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="fallback_for_regions[]" value="{{ $r->id }}" id="fallbackRegion{{ $r->id }}"
                                    {{ $p && $p->fallback_for_regions && in_array($r->id, $p->fallback_for_regions) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="fallbackRegion{{ $r->id }}">{{ $r->name }}</label>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted">Select regions where this project can serve as a fallback</small>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ url('/regenerative-projects') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Cancel
        </a>
        <button type="submit" class="btn btn-success" id="btnSave">
            <i class="bi bi-check-lg"></i> Save Project
        </button>
    </div>
</form>

@endsection

@section('js')
<script>
// Operational periods are a repeating pair of dates. Row indexes only have to
// be unique within a field — the server drops blanks and re-packs the list.
$(document).on('click', '.add-period', function() {
    var field = $(this).data('field');
    var rows = $('.period-rows[data-field="' + field + '"]');
    var i = rows.children('.period-row').length;
    rows.append(
        '<div class="input-group input-group-sm mb-1 period-row">' +
        '<input type="date" class="form-control" name="' + field + '[' + i + '][start]" aria-label="Start date">' +
        '<span class="input-group-text">to</span>' +
        '<input type="date" class="form-control" name="' + field + '[' + i + '][end]" aria-label="End date">' +
        '<button type="button" class="btn btn-outline-secondary remove-period" title="Remove"><i class="bi bi-x"></i></button>' +
        '</div>'
    );
});

$(document).on('click', '.remove-period', function() {
    $(this).closest('.period-row').remove();
});

$('#projectForm').on('submit', function(e) {
    e.preventDefault();

    var form = this;
    var formData = new FormData(form);
    formData.append('save_regenerative_project', 1);

    if (!formData.get('is_active')) {
        formData.set('is_active', '0');
    }

    var btn = $('#btnSave');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

    $.ajax({
        url: '/ajax',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(resp) {
            showAlert('Regenerative project saved successfully!', 'success');
            setTimeout(function() {
                window.location.href = "{{ url('/regenerative-projects') }}";
            }, 1000);
        },
        error: function(xhr) {
            btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Save Project');
            var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Failed to save project') : 'Request failed';
            showAlert(msg, 'danger');
        }
    });
});
</script>
@endsection
