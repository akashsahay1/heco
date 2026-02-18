@extends('portal.layout')
@section('title', 'Become a Partner - HECO Portal')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="text-center mb-4">
                <h2><i class="bi bi-people text-success"></i> Join HECO as a Service Provider</h2>
                <p class="text-muted">Partner with us to offer regenerative travel experiences</p>
            </div>

            <div id="sp-alert"></div>

            <form id="spApplicationForm">
                <div class="sp-form-section">
                    <h5>Provider Type</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="form-check card p-3">
                                <input class="form-check-input" type="radio" name="provider_type" value="hrp" id="typeHrp" required>
                                <label class="form-check-label" for="typeHrp">
                                    <strong>HRP</strong><br><small class="text-muted">HECO Resource Person - Regional operations partner</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check card p-3">
                                <input class="form-check-input" type="radio" name="provider_type" value="hlh" id="typeHlh">
                                <label class="form-check-label" for="typeHlh">
                                    <strong>HLH</strong><br><small class="text-muted">HECO Local Host - Experience provider</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check card p-3">
                                <input class="form-check-input" type="radio" name="provider_type" value="osp" id="typeOsp">
                                <label class="form-check-label" for="typeOsp">
                                    <strong>OSP</strong><br><small class="text-muted">Other Service Provider - Accommodation, transport, etc.</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sp-form-section">
                    <h5>Basic Information</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name / Organization *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Phone 1 *</label>
                            <input type="text" class="form-control" name="phone_1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Phone 2</label>
                            <input type="text" class="form-control" name="phone_2">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Region *</label>
                            <select class="form-select" name="region_id" required>
                                <option value="">Select your region...</option>
                                @foreach($regions as $region)
                                    <option value="{{ $region->id }}">{{ $region->name }}, {{ $region->country }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="sp-form-section">
                    <h5>About Your Services</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Describe your services and experience *</label>
                            <textarea class="form-control" name="description" rows="4" required placeholder="Tell us about your background, experience, and the services you can offer..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-send"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('css')
<style>
.sp-form-section {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.sp-form-section h5 {
    color: var(--heco-green);
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e5e7eb;
}
.form-check.card {
    cursor: pointer;
    transition: all 0.2s;
}
.form-check.card:hover {
    border-color: var(--heco-green);
}
.form-check-input:checked + .form-check-label {
    color: var(--heco-green);
}
</style>
@endsection

@section('js')
<script>
$(function() {
    $('#spApplicationForm').on('submit', function(e) {
        e.preventDefault();
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Submitting...');

        var formData = {};
        $(this).serializeArray().forEach(function(item) {
            formData[item.name] = item.value;
        });
        formData.sp_application = 1;

        ajaxPost(formData, function(resp) {
            btn.prop('disabled', false).html('<i class="bi bi-send"></i> Submit Application');
            if (resp.success) {
                $('#sp-alert').html('<div class="alert alert-success"><i class="bi bi-check-circle"></i> Application submitted successfully! We will review and get back to you soon.</div>');
                $('#spApplicationForm')[0].reset();
            }
        }, function(xhr) {
            btn.prop('disabled', false).html('<i class="bi bi-send"></i> Submit Application');
            var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Failed to submit application.') : 'Failed to submit application.';
            $('#sp-alert').html('<div class="alert alert-danger">' + msg + '</div>');
        });
    });
});
</script>
@endsection
