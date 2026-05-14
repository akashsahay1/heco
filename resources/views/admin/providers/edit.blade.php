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

{{-- ===== Services & Pricing ===== --}}
<div class="card mb-5" id="spPricingCard" data-provider-id="{{ $provider->id }}">
    <div class="card-body">
        <h6 class="border-bottom pb-2"><i class="bi bi-cash-stack"></i> Services &amp; Pricing
            <span class="text-muted small fw-normal">— flat rates the trip manager pulls in when this provider is assigned</span>
        </h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="w-check"><i class="bi bi-check2-square sp-price-selall" role="button" title="Select all"></i></th>
                        <th>Service</th>
                        <th>Category / Vehicle / Meal plan</th>
                        <th>Description</th>
                        <th class="w-rate">Rate</th>
                        <th class="w-active">Active</th>
                        <th class="w-status">Actions</th>
                    </tr>
                </thead>
                <tbody id="spPriceBody"><tr><td colspan="7" class="text-center text-muted small">Loading...</td></tr></tbody>
            </table>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-danger d-none" id="spPriceBulkDelete"><i class="bi bi-trash me-1"></i> Delete Selected</button>
            <button type="button" class="btn btn-sm btn-success" id="spPriceAdd"><i class="bi bi-plus-lg me-1"></i> Add Rate</button>
        </div>
    </div>
</div>

<div class="modal fade" id="spPriceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title">Service Rate</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="spPriceForm">
        <input type="hidden" name="id">
        <div class="mb-2"><label class="form-label small">Service type</label>
            <select class="form-select form-select-sm custom-select" name="service_type" required>
                <option value="accommodation">Accommodation</option>
                <option value="transport">Transport</option>
                <option value="guide">Guide</option>
                <option value="activity">Activity</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-md-7"><label class="form-label small">Rate (₹)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price" required></div>
            <div class="col-md-5"><label class="form-label small">Unit</label>
                <select class="form-select form-select-sm custom-select" name="unit" data-list-type="occupancy_unit" required>
                    <option value="">Select unit...</option>
                    @foreach($occupancyUnits as $u)
                        <option value="{{ $u->name }}" data-desc="{{ $u->description }}">{{ $u->name }}</option>
                    @endforeach
                </select>
                <small class="form-help-text text-muted d-block mt-1"></small>
            </div>
        </div>
        <div class="mb-2"><label class="form-label small">Category (for Accommodation — pick if applicable)</label>
            <select class="form-select form-select-sm custom-select" name="category" data-list-type="accommodation_category">
                <option value="">— none —</option>
                @foreach($accommodationCategories as $c)
                    <option value="{{ $c->name }}" data-desc="{{ $c->description }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <small class="form-help-text text-muted d-block mt-1"></small>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-md-6"><label class="form-label small">Vehicle type (for Transport)</label>
                <select class="form-select form-select-sm custom-select" name="vehicle_type" data-list-type="vehicle_type">
                    <option value="">— none —</option>
                    @foreach($vehicleTypes as $v)
                        <option value="{{ $v->name }}" data-desc="{{ $v->description }}">{{ $v->name }}</option>
                    @endforeach
                </select>
                <small class="form-help-text text-muted d-block mt-1"></small>
            </div>
            <div class="col-md-6"><label class="form-label small">Meal plan (for Accommodation)</label>
                <select class="form-select form-select-sm custom-select" name="meal_plan" data-list-type="meal_plan">
                    <option value="">— none —</option>
                    @foreach($mealPlans as $m)
                        <option value="{{ $m->name }}" data-desc="{{ $m->description }}">{{ $m->name }}</option>
                    @endforeach
                </select>
                <small class="form-help-text text-muted d-block mt-1"></small>
            </div>
        </div>
        <div class="mb-2"><label class="form-label small">Description / Notes for this rate (optional)</label><input type="text" class="form-control form-control-sm" name="description" placeholder="e.g. 'Off-season rate', 'Includes river-view room'"></div>
        <div class="mb-2"><label class="form-label small">Notes (optional)</label><textarea class="form-control form-control-sm" name="notes" rows="2"></textarea></div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="spPriceActive"><label class="form-check-label small" for="spPriceActive">Active</label></div>
        <button type="submit" class="btn btn-sm btn-success w-100">Save Rate</button>
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
    function loadPricing() {
        jQuery('#spPriceBody').html('<tr><td colspan="7" class="text-center text-muted small">Loading...</td></tr>');
        ajaxPost({ get_sp_pricing: 1, provider_id: providerId }, function(resp) {
            var rows = resp.rows || [];
            priceCache = {};
            if (!rows.length) { jQuery('#spPriceBody').html('<tr><td colspan="7" class="text-center text-muted small">No rates set. Add the first one.</td></tr>'); refreshBulkBtn(); return; }
            var html = '';
            rows.forEach(function(r) {
                priceCache[r.id] = r;
                var meta = [r.category, r.vehicle_type, r.meal_plan].filter(function(x) { return x; }).join(' / ');
                html += '<tr data-id="' + r.id + '">';
                html += '<td><i class="bi bi-square sp-price-check" role="button" data-id="' + r.id + '"></i></td>';
                html += '<td class="text-capitalize">' + spEscape(r.service_type) + '</td>';
                html += '<td class="small">' + spEscape(meta || '—') + '</td>';
                html += '<td class="small">' + spEscape(r.description || '—') + '</td>';
                html += '<td class="small fw-bold">&#8377;' + Number(r.price).toLocaleString('en-IN') + ' <span class="text-muted fw-normal">' + spEscape(r.unit) + '</span></td>';
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
    function fillPriceForm(r) {
        var $f = jQuery('#spPriceForm');
        $f[0].reset();
        $f.find('[name=id]').val(r ? r.id : '');
        $f.find('[name=service_type]').val(r ? r.service_type : 'accommodation');
        $f.find('[name=price]').val(r ? r.price : '');
        $f.find('[name=unit]').val(r ? r.unit : 'per night');
        $f.find('[name=category]').val(r ? (r.category || '') : '');
        $f.find('[name=vehicle_type]').val(r ? (r.vehicle_type || '') : '');
        $f.find('[name=meal_plan]').val(r ? (r.meal_plan || '') : '');
        $f.find('[name=description]').val(r ? (r.description || '') : '');
        $f.find('[name=notes]').val(r ? (r.notes || '') : '');
        $f.find('[name=is_active]').prop('checked', r ? !!r.is_active : true);
    }
    jQuery('#spPriceAdd').on('click', function() { fillPriceForm(null); new bootstrap.Modal('#spPriceModal').show(); });
    jQuery(document).on('click', '.sp-price-edit', function() { fillPriceForm(priceCache[jQuery(this).closest('tr').data('id')]); new bootstrap.Modal('#spPriceModal').show(); });
    jQuery('#spPriceForm').on('submit', function(e) {
        e.preventDefault();
        var data = { save_sp_pricing: 1, provider_id: providerId };
        jQuery(this).find('[name]').each(function() {
            if (this.type === 'checkbox') data[this.name] = this.checked ? 1 : 0;
            else data[this.name] = jQuery(this).val();
        });
        ajaxPost(data, function() { bootstrap.Modal.getInstance('#spPriceModal').hide(); loadPricing(); showAlert('Rate saved.', 'success'); },
            function(xhr) { showAlert(xhr.responseJSON ? (xhr.responseJSON.error || 'Save failed') : 'Save failed', 'danger'); });
    });
    loadPricing();
});
</script>
@endsection
