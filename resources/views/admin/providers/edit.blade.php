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
                            <select name="provider_type" class="form-select form-select-sm">
                                <option value="hrp" @selected($provider->provider_type === 'hrp')>HRP</option>
                                <option value="hlh" @selected($provider->provider_type === 'hlh')>HLH</option>
                                <option value="osp" @selected($provider->provider_type === 'osp')>OSP</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Region</label>
                            <select name="region_id" class="form-select form-select-sm">
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
                        <select name="status" class="form-select form-select-sm">
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

    <div class="d-flex gap-2 mt-3 mb-5">
        <button type="submit" class="btn btn-success" id="saveBtn">
            <i class="bi bi-check-lg"></i> Save Changes
        </button>
        <a href="{{ route('hct.providers.show', $provider->id) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

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
</script>
@endsection
