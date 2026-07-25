@extends('portal.layout')
@section('title', 'Become a Partner - HECO Portal')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="text-center mb-4">
                <h2><i class="bi bi-people sp-accent"></i> Join HECO as a Service Provider</h2>
                <p class="text-muted">Partner with us to offer regenerative travel experiences</p>
            </div>

            <div id="sp-alert"></div>

            <form id="spApplicationForm" novalidate>

                {{-- Provider type --}}
                <div class="sp-form-section">
                    <h5>Provider type</h5>
                    <div class="row g-3 mb-1">
                        @foreach([
                            ['hrp','HRP','HECO Resource Person — Regional operations partner'],
                            ['hlh','HLH','HECO Local Host — Experience provider'],
                            ['osp','OSP','Other Service Provider — Accommodation, transport, etc.'],
                        ] as [$val,$code,$desc])
                            <div class="col-md-4">
                                <label class="form-check card p-3 h-100 sp-type-card">
                                    <input class="form-check-input" type="radio" name="provider_type" value="{{ $val }}" required>
                                    <span class="form-check-label">
                                        <strong>{{ $code }}</strong><br>
                                        <small class="text-muted">{{ $desc }}</small>
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Personal --}}
                <div class="sp-form-section">
                    <h5>Tell us about you</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full name *</label>
                            <input type="text" class="form-control" name="contact_person" placeholder="e.g. Aarav Mehta" required>
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
                            <label class="form-label">City / region *</label>
                            <select class="form-select custom-select" name="region_id" required>
                                <option value="">Select your city / region...</option>
                                @foreach($regions as $region)
                                    <option value="{{ $region->id }}">{{ $region->name }}, {{ $region->country }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p class="form-text mt-2 mb-0">
                        <i class="bi bi-shield-lock sp-accent"></i>
                        After you submit, we'll email a code to verify your address and
                        help you set a password.
                    </p>
                </div>

                {{-- Business --}}
                <div class="sp-form-section">
                    <h5>Your business</h5>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Business / trading name *</label>
                            <input type="text" class="form-control" name="name" placeholder="e.g. Tirthan Eco Retreat" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Business type</label>
                            <select class="form-select custom-select" name="business_type">
                                <option value="">Choose a type...</option>
                                @foreach($businessTypes as $bt)
                                    <option value="{{ $bt->name }}">{{ $bt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Reg. number</label>
                            <input type="text" class="form-control" name="registration_number" placeholder="Optional">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Year established</label>
                            <input type="text" class="form-control" name="year_established" placeholder="2019"
                                inputmode="numeric" maxlength="4">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Short description *</label>
                            <textarea class="form-control" name="description" rows="4"
                                placeholder="Describe your experiences, hosting or services, and what makes them special."
                                required></textarea>
                            <div class="form-text">At least 20 characters.</div>
                        </div>
                    </div>
                </div>

                {{-- Services --}}
                <div class="sp-form-section">
                    <h5>Your services</h5>
                    <label class="form-label">Services you can offer *</label>
                    <div class="sp-services-grid">
                        @foreach($serviceTypes as $st)
                            <label class="sp-service-check">
                                <input type="checkbox" name="services_offered[]" value="{{ $st->name }}" class="js-service">
                                <span>{{ $st->name }}</span>
                            </label>
                        @endforeach
                    </div>

                    @foreach([
                        ['Accommodation', 'Accommodation categories', 'accommodation_categories', $accommodationCategories],
                        ['Transport', 'Vehicle types', 'vehicle_types', $vehicleTypes],
                        ['Guide', 'Guide specialisations', 'guide_types', $guideTypes],
                        ['Activity', 'Activity types', 'activity_types', $activityTypes],
                    ] as [$needs, $heading, $field, $options])
                        <div class="capability-block mt-4" data-requires="{{ $needs }}" hidden>
                            <label class="form-label">{{ $heading }}</label>
                            <div class="sp-services-grid">
                                @foreach($options as $opt)
                                    <label class="sp-service-check">
                                        <input type="checkbox" name="{{ $field }}[]" value="{{ $opt->name }}">
                                        <span>{{ $opt->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Address --}}
                <div class="sp-form-section">
                    <h5>Where are you based?</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Street address</label>
                            <textarea class="form-control" name="address" rows="2"
                                placeholder="e.g. Hawa Mahal Road 24"></textarea>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" placeholder="Jaipur">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Postal code</label>
                            <input type="text" class="form-control" name="postal_code" placeholder="302002" maxlength="12">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Country</label>
                            <select class="form-select custom-select" name="country">
                                <option value="">Choose a country...</option>
                                @foreach($countries as $c)
                                    <option value="{{ $c }}">{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Documents --}}
                <div class="sp-form-section">
                    <h5>Verify your identity <span class="text-muted fw-normal">(optional)</span></h5>
                    <p class="text-muted small">
                        Upload clear photos or PDFs. Max 10MB each. You can also add these later.
                    </p>
                    @foreach($documentTypes as $dt)
                        <div class="doc-row" data-label="{{ $dt->name }}">
                            <div class="doc-row-label">
                                <i class="bi bi-file-earmark-arrow-up sp-accent"></i>
                                <span>{{ $dt->name }}</span>
                            </div>
                            <input type="file" class="form-control js-doc-file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>
                    @endforeach
                </div>

                {{-- Terms + submit --}}
                <div class="sp-form-section">
                    <label class="d-flex align-items-start gap-2">
                        <input type="checkbox" id="acceptTerms" class="form-check-input mt-1">
                        <span>I confirm the details above are accurate and I accept the
                            <a href="{{ route('terms') }}" target="_blank">HECO partner terms</a>.</span>
                    </label>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn sp-btn-primary btn-lg" id="spSubmit">
                        <i class="bi bi-send"></i> Submit application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('css')
<style>
    .sp-type-card { cursor: pointer; margin: 0; }
    .sp-type-card:has(input:checked) { border-color: #79A09F; box-shadow: 0 0 0 1px #79A09F inset; }
    .doc-row { display: flex; gap: 12px; align-items: center; margin-bottom: 10px;
               padding: 10px 12px; border: 1px solid #e3e3df; border-radius: 12px; background: #fff; }
    .doc-row-label { display: flex; align-items: center; gap: 8px; min-width: 190px; font-weight: 600; }
    .doc-row .form-control { max-width: 320px; }
</style>
@endsection

@section('js')
<script>
jQuery(function() {
    function warn(msg) {
        jQuery('#sp-alert').html('<div class="alert alert-warning">' + msg + '</div>');
        jQuery('html, body').animate({ scrollTop: jQuery('#sp-alert').offset().top - 90 }, 200);
        return false;
    }

    // Capability blocks follow the selected services. Unchecking a service also
    // clears its chips so stale values are never submitted.
    jQuery('.js-service').on('change', function() {
        var $block = jQuery('.capability-block[data-requires="' + jQuery(this).val() + '"]');
        if (this.checked) {
            $block.prop('hidden', false);
        } else {
            $block.prop('hidden', true).find('input:checked').prop('checked', false);
        }
    });

    function collect() {
        return {
            provider_type: jQuery('input[name="provider_type"]:checked').val() || '',
            name: jQuery('input[name="name"]').val().trim(),
            contact_person: jQuery('input[name="contact_person"]').val().trim(),
            email: jQuery('input[name="email"]').val().trim(),
            phone_1: jQuery('input[name="phone_1"]').val().trim(),
            phone_2: jQuery('input[name="phone_2"]').val().trim(),
            description: jQuery('textarea[name="description"]').val().trim(),
            region_id: jQuery('select[name="region_id"]').val(),
            address: jQuery('textarea[name="address"]').val().trim(),
            city: jQuery('input[name="city"]').val().trim(),
            postal_code: jQuery('input[name="postal_code"]').val().trim(),
            country: jQuery('select[name="country"]').val() || '',
            business_type: jQuery('select[name="business_type"]').val() || '',
            registration_number: jQuery('input[name="registration_number"]').val().trim(),
            year_established: jQuery('input[name="year_established"]').val().trim()
        };
    }

    function validate(d) {
        jQuery('#sp-alert').empty();
        if (!d.provider_type) return warn('Please choose a provider type.');
        if (!d.contact_person) return warn('Please enter your full name.');
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(d.email)) return warn('Please enter a valid email address.');
        if (!d.phone_1) return warn('Please enter a primary phone number.');
        if (!d.region_id) return warn('Please choose your city / region.');
        if (!d.name) return warn('Please enter your business / trading name.');
        if (d.description.length < 20) return warn('Please describe your business (at least 20 characters).');
        if (!jQuery('input[name="services_offered[]"]:checked').length) return warn('Select at least one service you provide.');
        if (!jQuery('#acceptTerms').is(':checked')) return warn('Please accept the partner terms to continue.');
        return true;
    }

    jQuery('#spApplicationForm').on('submit', function(e) {
        e.preventDefault();
        var d = collect();
        if (validate(d) !== true) return;

        var fd = new FormData();
        fd.append('submit_sp_application', 1);
        Object.keys(d).forEach(function(k) { fd.append(k, d[k]); });
        ['services_offered','accommodation_categories','vehicle_types','guide_types','activity_types'].forEach(function(k) {
            jQuery('input[name="' + k + '[]"]:checked').each(function() { fd.append(k + '[]', this.value); });
        });
        jQuery('.doc-row').each(function() {
            var file = jQuery(this).find('.js-doc-file')[0].files[0];
            if (file) {
                fd.append('documents[]', file);
                fd.append('document_labels[]', jQuery(this).data('label'));
            }
        });

        var $btn = jQuery('#spSubmit');
        var original = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Submitting...');

        jQuery.ajax({
            url: '/ajax', method: 'POST', data: fd, processData: false, contentType: false,
            success: function(resp) {
                if (resp.success) {
                    window.location.href = resp.redirect || '/create-password';
                } else {
                    $btn.prop('disabled', false).html(original);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(original);
                var msg = xhr.responseJSON ? (xhr.responseJSON.error || 'Failed to submit application.') : 'Failed to submit application.';
                jQuery('#sp-alert').html('<div class="alert alert-danger">' + msg + '</div>');
            }
        });
    });
});
</script>
@endsection
