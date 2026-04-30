@extends('admin.layout')
@section('title', 'Edit ' . $typeLabel . ' Option')

@section('content')
<div class="container-fluid p-4 tp-edit-page">

    <nav class="breadcrumb">
        <a href="{{ url('/travel-preferences') }}"><i class="bi bi-sliders2"></i> Travel Preferences</a>
        <span class="mx-2 text-muted">/</span>
        <span class="text-muted">Edit option</span>
    </nav>

    <div class="d-flex align-items-center mb-3">
        <a href="{{ url('/travel-preferences') }}" class="tp-back-btn me-3" title="Back to Travel Preferences">
            <i class="bi bi-arrow-left"></i>
        </a>
        <i class="bi bi-pencil-square me-2 fs-4 text-success"></i>
        <h4 class="mb-0">Edit {{ $typeLabel }} Option</h4>
    </div>

    <div class="form-card">
        <h5 class="form-section-title">Option Details</h5>
        <p class="form-section-subtitle">
            Updates here apply immediately to traveller dropdowns. Existing trips keep their saved value
            even if you rename the option.
        </p>

        <form id="prefEditForm">
            <input type="hidden" name="id" value="{{ $item->id }}">
            <input type="hidden" name="list_type" value="{{ $item->list_type }}">

            <div class="mb-3">
                <label class="form-label fw-semibold">Belongs to</label>
                <div><span class="read-only-pill">{{ $typeLabel }}</span></div>
                <small class="text-muted">List type cannot be changed. Create a new option in the correct section instead.</small>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Option name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" value="{{ $item->name }}" required maxlength="120">
                <small class="text-muted">This is the label shown to travellers and saved on every Trip that selects it.</small>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sort order</label>
                    <input type="number" class="form-control" name="sort_order" value="{{ $item->sort_order ?? 0 }}" min="0">
                    <small class="text-muted">Lower = appears first.</small>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="isActiveSwitch" name="is_active" value="1" {{ $item->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="isActiveSwitch">
                            <span class="status-on" style="{{ $item->is_active ? '' : 'display:none;' }}">Active — shown in dropdowns</span>
                            <span class="status-off" style="{{ $item->is_active ? 'display:none;' : '' }}">Inactive — hidden from new selections</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ url('/travel-preferences') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Cancel
                </a>
                <button type="submit" class="btn btn-success" id="btnSavePref">
                    <i class="bi bi-check-lg me-1"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script>
jQuery(function() {
    // Status label toggle
    jQuery('#isActiveSwitch').on('change', function() {
        var on = this.checked;
        jQuery('.status-on').toggle(on);
        jQuery('.status-off').toggle(!on);
    });

    jQuery('#prefEditForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = jQuery('#btnSavePref');
        var orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

        var payload = {
            save_system_list_item: 1,
            id: jQuery('input[name=id]').val(),
            list_type: jQuery('input[name=list_type]').val(),
            name: jQuery('input[name=name]').val().trim(),
            sort_order: parseInt(jQuery('input[name=sort_order]').val(), 10) || 0,
            is_active: jQuery('#isActiveSwitch').is(':checked') ? 1 : 0
        };

        ajaxPost(payload, function() {
            showAlert('Option updated.', 'success');
            setTimeout(function() {
                window.location.href = '/travel-preferences';
            }, 600);
        }, function(xhr) {
            $btn.prop('disabled', false).html(orig);
            var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Save failed') : 'Save failed';
            showAlert(msg, 'danger');
        });
    });
});
</script>
@endsection
