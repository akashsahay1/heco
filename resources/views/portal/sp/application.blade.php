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
                {{-- Several at once, because a partner can be several things:
                     a host who also runs a taxi is HLH and OSP both. The app has
                     always allowed this and the web form did not, so the same
                     person got a different answer depending which door they
                     came in by. --}}
                <div class="sp-form-section">
                    <h5>What would you like to do with HECO?</h5>
                    <p class="text-muted small mb-2">Choose everything that applies. Many partners do more than one.</p>
                    <div class="row g-3 mb-1">
                        @foreach([
                            ['hlh','HLH','HECO Local Host — you host experiences or stays'],
                            ['osp','OSP','Other Service Provider — rooms, transport, guiding, activities'],
                            ['hrp','HRP','HECO Regional Partner — you coordinate a region'],
                        ] as [$val,$code,$desc])
                            <div class="col-md-4">
                                <label class="form-check card p-3 h-100 sp-type-card">
                                    <input class="form-check-input js-provider-type" type="checkbox"
                                        name="provider_types[]" value="{{ $val }}">
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
                        <div class="col-md-6">
                            <label class="form-label">Create password *</label>
                            <input type="password" class="form-control" name="password" autocomplete="new-password" required>
                            <div class="form-text">At least 8 characters, with a number and a symbol.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm password *</label>
                            <input type="password" class="form-control" name="password_confirmation" autocomplete="new-password" required>
                        </div>
                    </div>
                </div>

                {{-- Languages --}}
                <div class="sp-form-section">
                    <h5>What do you speak?</h5>
                    <p class="text-muted small mb-2">So we know how to talk to you, and which travellers you can host.</p>
                    <div class="sp-services-grid">
                        <label class="sp-service-check">
                            <input type="checkbox" name="speaks_english" value="1">
                            <span>English</span>
                        </label>
                        <label class="sp-service-check">
                            <input type="checkbox" name="speaks_hindi" value="1">
                            <span>Hindi</span>
                        </label>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Any other languages</label>
                        <input type="text" class="form-control" name="other_languages"
                            placeholder="e.g. Ladakhi, Nepali, French">
                    </div>
                </div>

                {{-- Business --}}
                <div class="sp-form-section">
                    <h5>Your business</h5>
                    {{-- The client is explicit that most members will not have
                         one: "You don't need to own a business." So this is a
                         gate, not an assumption, and the trading name is only
                         asked for once somebody says yes. --}}
                    <div class="mb-3">
                        <label class="form-label">Do you already have a business?</label>
                        <div class="sp-services-grid">
                            <label class="sp-service-check">
                                <input type="radio" name="has_business" value="1" class="js-has-business">
                                <span>Yes, I have one</span>
                            </label>
                            <label class="sp-service-check">
                                <input type="radio" name="has_business" value="0" class="js-has-business">
                                <span>No, it is just me</span>
                            </label>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-7 js-business-only" hidden>
                            <label class="form-label">Business / trading name</label>
                            <input type="text" class="form-control" name="name" placeholder="e.g. Tirthan Eco Retreat">
                            <div class="form-text">Left empty, we will use your own name.</div>
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
                        <div class="col-md-7 js-business-only" hidden>
                            <label class="form-label">Reg. number</label>
                            <input type="text" class="form-control" name="registration_number" placeholder="Optional">
                        </div>
                        <div class="col-md-5 js-business-only" hidden>
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

                {{-- What they host, if they host. Shown only to an HLH. --}}
                <div class="sp-form-section js-role-block" data-role="hlh" hidden>
                    <h5>Experiences you host</h5>
                    <p class="text-muted small mb-2">What kind of thing do you offer a traveller?</p>
                    <div class="sp-services-grid">
                        @foreach($experienceCategories as $ec)
                            <label class="sp-service-check">
                                <input type="checkbox" name="experience_categories[]" value="{{ $ec->name }}">
                                <span>{{ $ec->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- What they supply, if they supply. Shown only to an OSP. --}}
                <div class="sp-form-section js-role-block" data-role="osp" hidden>
                    <h5>Services you supply</h5>
                    <div class="sp-services-grid">
                        @foreach($serviceCategories as $sc)
                            <label class="sp-service-check">
                                <input type="checkbox" name="service_categories[]" value="{{ $sc->name }}">
                                <span>{{ $sc->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Anything else you supply</label>
                        <input type="text" class="form-control" name="other_services"
                            placeholder="Permits, equipment hire, anything not listed above">
                    </div>
                </div>

                {{-- A regional partner sells nothing, so their application IS
                     their background. The client asked for exactly this: "For
                     HRPs, we would rather collect information about their
                     background and skills." Shown only to an HRP. --}}
                <div class="sp-form-section js-role-block" data-role="hrp" hidden>
                    <h5>Your background</h5>
                    <p class="text-muted small mb-2">A regional partner coordinates people rather than selling a room, so this is what we would like to know about you.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Education</label>
                            <select class="form-select custom-select" name="education_level">
                                <option value="">Choose one...</option>
                                @foreach($educationLevels as $el)
                                    <option value="{{ $el->name }}">{{ $el->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">English</label>
                            <select class="form-select custom-select" name="english_level">
                                <option value="">Choose one...</option>
                                @foreach($englishLevels as $el)
                                    <option value="{{ $el->name }}">{{ $el->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Computer skills</label>
                            <select class="form-select custom-select" name="computer_skill_level">
                                <option value="">Choose one...</option>
                                @foreach($computerSkillLevels as $cl)
                                    <option value="{{ $cl->name }}">{{ $cl->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Anything else about your studies</label>
                            <textarea class="form-control" name="education_notes" rows="2"
                                placeholder="Courses, training, certificates"></textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="form-label">Work you have done</label>
                        <div id="workExpRows"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="workExpAdd">
                            <i class="bi bi-plus-lg"></i> Add another role
                        </button>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <label class="form-label">What you care about</label>
                            <textarea class="form-control" name="causes_note" rows="2"
                                placeholder="Social or environmental work you have been part of"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Your community</label>
                            <textarea class="form-control" name="community_note" rows="2"
                                placeholder="How well you know the people and the place you would coordinate"></textarea>
                        </div>
                    </div>
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
                            <input type="text" class="form-control" name="city" placeholder="e.g. Jaipur">
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

                {{-- How to reach them --}}
                <div class="sp-form-section">
                    <h5>How can we reach you?</h5>
                    <div class="sp-services-grid">
                        <label class="sp-service-check">
                            <input type="checkbox" name="contact_by_email" value="1" checked>
                            <span>Email</span>
                        </label>
                        <label class="sp-service-check">
                            <input type="checkbox" name="contact_by_whatsapp" value="1">
                            <span>WhatsApp</span>
                        </label>
                    </div>
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

    function chosenTypes() {
        return jQuery('.js-provider-type:checked').map(function() { return this.value; }).get();
    }

    // What somebody is asked follows from what they said they do. A host is
    // asked what they host; a services partner what they supply; a regional
    // partner about themselves. Nobody is shown a question meant for somebody
    // else, which is what made the app's version readable.
    jQuery(document).on('change', '.js-provider-type', function() {
        var types = jQuery('.js-provider-type:checked').map(function() { return this.value; }).get();
        jQuery('.js-role-block').each(function() {
            var wanted = jQuery(this).data('role');
            var on = types.indexOf(wanted) !== -1;
            jQuery(this).prop('hidden', !on);
            if (!on) { jQuery(this).find('input:checked').prop('checked', false); }
        });
    });

    jQuery(document).on('change', '.js-has-business', function() {
        var yes = jQuery('.js-has-business:checked').val() === '1';
        jQuery('.js-business-only').prop('hidden', !yes);
        if (!yes) { jQuery('.js-business-only').find('input').val(''); }
    });

    jQuery(document).on('click', '#workExpAdd', function() {
        jQuery('#workExpRows').append(
            '<div class="work-exp-row border rounded p-2 mb-2">' +
            '<div class="row g-2">' +
            '<div class="col-6"><input type="text" class="form-control form-control-sm" data-key="role" placeholder="Role"></div>' +
            '<div class="col-6"><input type="text" class="form-control form-control-sm" data-key="organisation" placeholder="Organisation"></div>' +
            '<div class="col-12"><input type="text" class="form-control form-control-sm" data-key="years" placeholder="Years, e.g. 2019-2023"></div>' +
            '<div class="col-12"><textarea class="form-control form-control-sm" data-key="description" rows="2" placeholder="What you did"></textarea></div>' +
            '</div>' +
            '<button type="button" class="btn btn-sm btn-link text-danger p-0 mt-1 work-exp-remove">Remove</button>' +
            '</div>');
    });
    jQuery(document).on('click', '.work-exp-remove', function() { jQuery(this).closest('.work-exp-row').remove(); });

    function collect() {
        var types = chosenTypes();
        return {
            // provider_type stays, as the first of the set, because older code
            // and the admin screens still read it. provider_types is the truth.
            provider_type: types[0] || '',
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
            year_established: jQuery('input[name="year_established"]').val().trim(),
            password: jQuery('input[name="password"]').val(),
            password_confirmation: jQuery('input[name="password_confirmation"]').val(),

            // Everything below was asked by the app and not by this form, so
            // somebody applying from the website was never asked for it.
            speaks_english: jQuery('input[name="speaks_english"]').is(':checked') ? 1 : 0,
            speaks_hindi: jQuery('input[name="speaks_hindi"]').is(':checked') ? 1 : 0,
            other_languages: jQuery('input[name="other_languages"]').val().trim(),
            has_business: jQuery('input[name="has_business"]:checked').val() || '',
            other_services: jQuery('input[name="other_services"]').val().trim(),
            education_level: jQuery('select[name="education_level"]').val() || '',
            education_notes: jQuery('textarea[name="education_notes"]').val().trim(),
            english_level: jQuery('select[name="english_level"]').val() || '',
            computer_skill_level: jQuery('select[name="computer_skill_level"]').val() || '',
            causes_note: jQuery('textarea[name="causes_note"]').val().trim(),
            community_note: jQuery('textarea[name="community_note"]').val().trim(),
            contact_by_email: jQuery('input[name="contact_by_email"]').is(':checked') ? 1 : 0,
            contact_by_whatsapp: jQuery('input[name="contact_by_whatsapp"]').is(':checked') ? 1 : 0
        };
    }

    /** The work-experience rows, as the server expects them. */
    function workExperience() {
        var rows = [];
        jQuery('#workExpRows .work-exp-row').each(function() {
            var row = {
                role: jQuery(this).find('[data-key="role"]').val().trim(),
                organisation: jQuery(this).find('[data-key="organisation"]').val().trim(),
                years: jQuery(this).find('[data-key="years"]').val().trim(),
                description: jQuery(this).find('[data-key="description"]').val().trim()
            };
            if (row.role || row.organisation || row.years || row.description) { rows.push(row); }
        });
        return rows;
    }

    function validate(d) {
        jQuery('#sp-alert').empty();
        if (!chosenTypes().length) return warn('Please choose at least one thing you would like to do with HECO.');
        if (!d.contact_person) return warn('Please enter your full name.');
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(d.email)) return warn('Please enter a valid email address.');
        if (!d.phone_1) return warn('Please enter a primary phone number.');
        if (!d.region_id) return warn('Please choose your city / region.');
        if (d.has_business === '') return warn('Please tell us whether you already have a business.');
        if (d.has_business === '1' && !d.name) return warn('Please enter your business / trading name.');
        if (d.description.length < 20) return warn('Please describe your business (at least 20 characters).');
        if (d.password.length < 8 || !/[0-9]/.test(d.password) || !/[^A-Za-z0-9]/.test(d.password)) {
            return warn('Password must be at least 8 characters and include a number and a symbol.');
        }
        if (d.password !== d.password_confirmation) return warn('The passwords do not match.');
        if (chosenTypes().indexOf('hlh') !== -1
            && !jQuery('input[name="experience_categories[]"]:checked').length) {
            return warn('Select at least one kind of experience you host.');
        }
        if (chosenTypes().indexOf('osp') !== -1
            && !jQuery('input[name="service_categories[]"]:checked').length) {
            return warn('Select at least one service you supply.');
        }
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
        chosenTypes().forEach(function(v) { fd.append('provider_types[]', v); });
        // services_offered and the four category lists are not asked here, the
        // same as in the app. A partner sets them on their own profile once
        // they are approved, where they can be changed without reapplying.
        ['experience_categories','service_categories'].forEach(function(k) {
            jQuery('input[name="' + k + '[]"]:checked').each(function() { fd.append(k + '[]', this.value); });
        });
        workExperience().forEach(function(row, i) {
            Object.keys(row).forEach(function(k) {
                fd.append('work_experience[' + i + '][' + k + ']', row[k]);
            });
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
                    window.location.href = resp.redirect || '/application-status';
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
