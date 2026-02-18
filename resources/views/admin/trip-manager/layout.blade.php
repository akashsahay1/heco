@extends('admin.layout')
@section('title', 'Trip Manager - ' . $trip->trip_id)

@section('css')
<style>
.trip-manager-col { height: calc(100vh - 140px); overflow-y: auto; padding: 10px; }
.tm-sidebar { background: #f8f9fa; }
.day-block { border: 1px solid #dee2e6; border-radius: 8px; padding: 12px; margin-bottom: 10px; background: #fff; cursor: pointer; transition: border-color 0.15s; }
.day-block:hover { border-color: #0d6efd; }
.day-block.locked { background: #f0f8f0; border-color: #198754; }
.day-block.selected { border-color: #0d6efd; border-width: 2px; }
.service-row { display: flex; align-items: center; gap: 8px; padding: 4px 0; border-bottom: 1px solid #f0f0f0; }
</style>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Top bar with trip info --}}
    <div class="bg-white border-bottom px-3 py-2 d-flex justify-content-between align-items-center">
        <div>
            <strong class="text-success">{{ $trip->trip_id }}</strong>
            <span class="text-muted ms-2">{{ $trip->trip_name ?? 'Unnamed Trip' }}</span>
            <span class="badge bg-{{ $trip->status === 'confirmed' ? 'success' : ($trip->status === 'running' ? 'primary' : 'warning text-dark') }} ms-2">{{ ucfirst(str_replace('_', ' ', $trip->status)) }}</span>
        </div>
        <div class="d-flex gap-2">
            <span class="text-muted small">{{ $trip->user->full_name ?? $trip->user->email ?? '' }}</span>
            <a href="{{ url('/pdf/trip/' . $trip->id) }}" class="btn btn-sm btn-outline-success" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
            <a href="{{ url('/dashboard') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    {{-- Two tabs: Trip Info | Trip Itinerary --}}
    <ul class="nav nav-tabs px-3 pt-2" id="tmTabs">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tripInfoTab">Trip Info</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tripItineraryTab">Trip Itinerary</a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tripInfoTab">
            @include('admin.trip-manager.trip-info')
        </div>
        <div class="tab-pane fade" id="tripItineraryTab">
            @include('admin.trip-manager.trip-itinerary')
        </div>
    </div>
</div>

{{-- Edit Service Modal --}}
<div class="modal fade" id="editServiceModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Edit Service</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editServiceForm">
                    <input type="hidden" id="editServiceId">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Service Type</label>
                        <select class="form-select form-select-sm" id="editServiceType">
                            <option value="accommodation">Accommodation</option>
                            <option value="transport">Transport</option>
                            <option value="guide">Guide</option>
                            <option value="activity">Activity</option>
                            <option value="meal">Meal</option>
                            <option value="permit">Permit</option>
                            <option value="equipment">Equipment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Description</label>
                        <input type="text" class="form-control form-control-sm" id="editServiceDesc">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Cost</label>
                        <input type="number" class="form-control form-control-sm" id="editServiceCost" step="0.01" min="0">
                    </div>
                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check"></i> Update Service</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
var tripId = {{ $trip->id }};
var tripData = @json($trip);

// ==========================================
// TRIP INFO TAB
// ==========================================

$('#tripInfoForm').on('submit', function(e) {
    e.preventDefault();
    var data = { update_trip_info: 1, trip_id: tripId };
    $(this).find('[name]').each(function() {
        data[$(this).attr('name')] = $(this).val();
    });
    ajaxPost(data, function() { showAlert('Trip info updated!'); });
});

$('#btnSaveNotes').on('click', function() {
    ajaxPost({
        update_trip_info: 1,
        trip_id: tripId,
        general_notes: $('#generalNotes').val()
    }, function() {
        showAlert('Notes saved!');
    });
});

$('#addPaymentForm').on('submit', function(e) {
    e.preventDefault();
    var data = { add_traveller_payment: 1, trip_id: tripId };
    $(this).find('[name]').each(function() {
        data[$(this).attr('name')] = $(this).val();
    });
    ajaxPost(data, function() {
        showAlert('Payment recorded!');
        loadTravellerPayments();
        $('#addPaymentForm')[0].reset();
        $('#addPaymentCollapse').collapse('hide');
    });
});

function loadTravellerPayments() {
    ajaxPost({ get_traveller_payment_history: 1, trip_id: tripId }, function(resp) {
        var html = '';
        var totalPaid = 0;
        if (resp.payments && resp.payments.length) {
            resp.payments.forEach(function(p) {
                totalPaid += parseFloat(p.amount) || 0;
                html += '<tr>';
                html += '<td>' + p.payment_date + '</td>';
                html += '<td class="text-end text-success fw-bold">&#8377;' + Number(p.amount).toLocaleString('en-IN') + '</td>';
                html += '<td>' + (p.mode || '-') + '</td>';
                html += '<td>' + (p.notes || '') + '</td>';
                html += '<td>' + (p.recorder ? p.recorder.full_name : '-') + '</td>';
                html += '</tr>';
            });
        }
        if (!html) {
            html = '<tr><td colspan="5" class="text-muted text-center">No payments recorded</td></tr>';
        }
        $('#paymentHistory').html(html);
        var finalPrice = parseFloat(tripData.final_price) || 0;
        var balance = finalPrice - totalPaid;
        $('#totalPaidDisplay').text('&#8377;' + totalPaid.toLocaleString('en-IN'));
        $('#balanceDueDisplay')
            .text('&#8377;' + balance.toLocaleString('en-IN'))
            .toggleClass('text-danger', balance > 0)
            .toggleClass('text-success', balance <= 0);
    });
}
loadTravellerPayments();

$('#btnRecalc').on('click', function() {
    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Calculating...');
    ajaxPost({ recalculate_trip_cost: 1, trip_id: tripId }, function(resp) {
        showAlert('Costs recalculated!');
        $btn.prop('disabled', false).html('<i class="bi bi-calculator"></i> Recalculate');
        location.reload();
    }, function() {
        $btn.prop('disabled', false).html('<i class="bi bi-calculator"></i> Recalculate');
    });
});

// ==========================================
// ITINERARY TAB
// ==========================================

var selectedDayId = null;

function loadItinerary() {
    ajaxPost({ get_trip_itinerary: 1, trip_id: tripId }, function(resp) {
        var t = resp.trip;
        var html = '';
        var daySelectHtml = '<option value="">-- Select Day --</option>';

        if (t.trip_days && t.trip_days.length) {
            t.trip_days.forEach(function(day) {
                var isLocked = day.is_locked;
                daySelectHtml += '<option value="' + day.id + '">Day ' + day.day_number + (day.title ? ' - ' + day.title : '') + '</option>';

                html += '<div class="day-block card mb-2 ' + (isLocked ? 'locked' : '') + (selectedDayId == day.id ? ' selected' : '') + '" data-day-id="' + day.id + '">';
                html += '<div class="card-header d-flex justify-content-between align-items-center bg-light py-1 px-2">';
                html += '<div class="d-flex align-items-center gap-2">';
                if (isLocked) html += '<i class="bi bi-lock-fill text-success" title="Locked by experience"></i>';
                html += '<strong class="small">Day ' + day.day_number;
                if (day.date) html += ' <span class="text-muted fw-normal">(' + day.date + ')</span>';
                html += '</strong>';
                if (day.title) html += '<span class="text-muted small ms-1">- ' + day.title + '</span>';
                html += '</div>';
                html += '<div class="d-flex gap-1">';
                html += '<button class="btn btn-sm btn-outline-secondary btn-move-day-up" data-id="' + day.id + '" title="Move up"><i class="bi bi-arrow-up"></i></button>';
                html += '<button class="btn btn-sm btn-outline-secondary btn-move-day-down" data-id="' + day.id + '" title="Move down"><i class="bi bi-arrow-down"></i></button>';
                if (!isLocked) html += '<button class="btn btn-sm btn-outline-danger btn-remove-day" data-id="' + day.id + '"><i class="bi bi-trash"></i></button>';
                html += '</div>';
                html += '</div><div class="card-body p-2">';

                if (day.experiences && day.experiences.length) {
                    day.experiences.forEach(function(de) {
                        var exp = de.experience || {};
                        html += '<div class="d-flex align-items-center p-1 mb-1 bg-success bg-opacity-10 rounded">';
                        html += '<i class="bi bi-star-fill text-success me-2"></i>';
                        html += '<span class="small flex-fill">' + (exp.name || 'Experience') + '</span>';
                        if (de.start_time) html += '<small class="text-muted me-2">' + de.start_time + '</small>';
                        if (de.total_cost > 0) html += '<small class="text-success me-2">&#8377;' + Number(de.total_cost).toLocaleString('en-IN') + '</small>';
                        if (!isLocked) html += '<button class="btn btn-sm btn-outline-danger btn-rm-day-exp" data-id="' + de.id + '"><i class="bi bi-x"></i></button>';
                        html += '</div>';
                    });
                }

                if (day.services && day.services.length) {
                    day.services.forEach(function(s) {
                        html += '<div class="d-flex align-items-center p-1 small">';
                        html += '<i class="bi bi-' + getServiceIcon(s.service_type) + ' ' + (s.is_included ? 'text-success' : 'text-danger') + ' me-2"></i>';
                        html += '<span class="flex-fill">' + (s.description || s.service_type) + '</span>';
                        if (s.service_provider) html += '<small class="text-muted me-2">' + s.service_provider.name + '</small>';
                        if (s.cost > 0) html += '<small class="text-success">&#8377;' + Number(s.cost).toLocaleString('en-IN') + '</small>';
                        html += '</div>';
                    });
                }

                if ((!day.experiences || !day.experiences.length) && (!day.services || !day.services.length)) {
                    html += '<p class="text-muted small text-center mb-0">Empty day - click to add services</p>';
                }

                if (day.description) {
                    html += '<div class="small text-muted mt-1 border-top pt-1"><i class="bi bi-chat-left-text me-1"></i>' + day.description + '</div>';
                }

                html += '</div></div>';
            });
        } else {
            html = '<div class="text-center py-5 text-muted">';
            html += '<i class="bi bi-calendar-x fs-1 d-block mb-2"></i>';
            html += 'No days in itinerary. Click <strong>+ Add Day</strong> to start building.';
            html += '</div>';
        }

        $('#itineraryTimeline').html(html);
        $('#targetDaySelect').html(daySelectHtml);

        if (selectedDayId) {
            loadDayServices(selectedDayId);
        }
    });
}

function getServiceIcon(type) {
    var icons = {
        accommodation: 'house-door',
        transport: 'car-front',
        guide: 'person-badge',
        activity: 'lightning',
        meal: 'cup-hot',
        permit: 'file-earmark-check',
        equipment: 'backpack'
    };
    return icons[type] || 'three-dots';
}

function selectDay(dayId) {
    selectedDayId = dayId;
    $('.day-block').removeClass('selected');
    $('.day-block[data-day-id="' + dayId + '"]').addClass('selected');
    $('#dayServicesPanel').show();
    $('#serviceDayId').val(dayId);
    var dayLabel = $('.day-block[data-day-id="' + dayId + '"]').find('strong.small').first().text();
    $('#selectedDayLabel').text(dayLabel || 'Selected Day');
    loadDayServices(dayId);
}

function loadDayServices(dayId) {
    ajaxPost({ get_day_services: 1, trip_day_id: dayId }, function(resp) {
        var html = '';
        if (resp.services && resp.services.length) {
            resp.services.forEach(function(s) {
                html += '<div class="service-row">';
                html += '<i class="bi bi-' + getServiceIcon(s.service_type) + ' ' + (s.is_included ? 'text-success' : 'text-danger') + '"></i>';
                html += '<div class="flex-fill">';
                html += '<div class="small fw-bold">' + (s.description || s.service_type) + '</div>';
                if (s.service_provider) html += '<div class="small text-muted">' + s.service_provider.name + '</div>';
                if (s.from_location || s.to_location) html += '<div class="small text-muted">' + (s.from_location || '') + (s.to_location ? ' &rarr; ' + s.to_location : '') + '</div>';
                html += '</div>';
                if (s.cost > 0) html += '<span class="small text-success fw-bold">&#8377;' + Number(s.cost).toLocaleString('en-IN') + '</span>';
                html += '<div class="d-flex gap-1 ms-1">';
                html += '<button class="btn btn-sm btn-outline-secondary btn-edit-service" data-id="' + s.id + '" data-type="' + s.service_type + '" data-description="' + (s.description || '').replace(/"/g, '&quot;') + '" data-cost="' + (s.cost || 0) + '" title="Edit"><i class="bi bi-pencil"></i></button>';
                html += '<button class="btn btn-sm btn-outline-danger rm-service" data-id="' + s.id + '" title="Remove"><i class="bi bi-x"></i></button>';
                html += '</div>';
                html += '</div>';
            });
        } else {
            html = '<p class="text-muted small text-center py-2">No services for this day. Add one below.</p>';
        }
        $('#dayServicesList').html(html);
    });
}

$('a[href="#tripItineraryTab"]').on('shown.bs.tab', function() { loadItinerary(); });

$('#searchExpBtn').on('click', function() {
    ajaxPost({
        search_experiences_for_trip: 1,
        trip_id: tripId,
        search: $('#searchExpInput').val(),
        region_id: $('#searchExpRegion').val(),
        type: $('#searchExpType').val()
    }, function(resp) {
        var html = '';
        if (resp.experiences && resp.experiences.length) {
            resp.experiences.forEach(function(e) {
                html += '<div class="border rounded p-2 mb-1">';
                html += '<div class="d-flex justify-content-between align-items-start">';
                html += '<div>';
                html += '<strong class="small">' + e.name + '</strong>';
                html += '<br><small class="text-muted">' + (e.region ? e.region.name : '') + ' | ' + (e.type || '') + '</small>';
                if (e.duration_hours) html += '<br><small class="text-muted"><i class="bi bi-clock"></i> ' + e.duration_hours + 'h</small>';
                if (e.base_cost_per_person > 0) html += ' <small class="text-success">&#8377;' + Number(e.base_cost_per_person).toLocaleString('en-IN') + '/pp</small>';
                html += '</div>';
                html += '<button class="btn btn-sm btn-success add-exp-to-day" data-id="' + e.id + '" title="Add to day"><i class="bi bi-plus"></i></button>';
                html += '</div></div>';
            });
        } else {
            html = '<p class="text-muted small text-center py-2">No experiences found</p>';
        }
        $('#expSearchResults').html(html);
    });
});

$('#searchExpInput').on('keypress', function(e) {
    if (e.which === 13) { e.preventDefault(); $('#searchExpBtn').click(); }
});

$(document).on('click', '.add-exp-to-day', function() {
    var expId = $(this).data('id');
    var dayId = $('#targetDaySelect').val();
    if (!dayId) { showAlert('Select a target day first', 'warning'); return; }
    ajaxPost({ add_experience_to_day: 1, trip_id: tripId, trip_day_id: dayId, experience_id: expId }, function() {
        loadItinerary();
        showAlert('Experience added to day!');
    });
});

$('#btnAddTripDay').on('click', function() {
    ajaxPost({ add_trip_day: 1, trip_id: tripId }, function() {
        loadItinerary();
        showAlert('Day added!');
    });
});

jQuery(document).on('click', '.btn-remove-day', function(e) {
    e.stopPropagation();
    var dayId = jQuery(this).data('id');
    confirmAction('Remove this day and all its experiences/services?', function() {
        ajaxPost({ remove_trip_day: 1, trip_day_id: dayId }, function() {
            selectedDayId = null;
            jQuery('#dayServicesPanel').hide();
            loadItinerary();
        });
    });
});

$(document).on('click', '.btn-rm-day-exp', function(e) {
    e.stopPropagation();
    ajaxPost({ remove_experience_from_day: 1, trip_day_experience_id: $(this).data('id') }, function() {
        loadItinerary();
    });
});

$(document).on('click', '.btn-move-day-up, .btn-move-day-down', function(e) {
    e.stopPropagation();
    var dayIds = [];
    $('.day-block').each(function() { dayIds.push($(this).data('day-id')); });
    var dayId = $(this).data('id');
    var idx = dayIds.indexOf(dayId);
    if ($(this).hasClass('btn-move-day-up') && idx > 0) {
        dayIds.splice(idx, 1);
        dayIds.splice(idx - 1, 0, dayId);
    } else if ($(this).hasClass('btn-move-day-down') && idx < dayIds.length - 1) {
        dayIds.splice(idx, 1);
        dayIds.splice(idx + 1, 0, dayId);
    } else {
        return;
    }
    ajaxPost({ reorder_trip_days: 1, trip_id: tripId, order: dayIds }, function() { loadItinerary(); });
});

$(document).on('click', '.day-block', function(e) {
    if ($(e.target).closest('button').length) return;
    selectDay($(this).data('day-id'));
});

$('#addServiceForm').on('submit', function(e) {
    e.preventDefault();
    var dayId = $('#serviceDayId').val();
    if (!dayId) { showAlert('Select a day first by clicking on it', 'warning'); return; }
    var data = { add_day_service: 1, trip_day_id: dayId };
    $(this).find('[name]').each(function() { data[$(this).attr('name')] = $(this).val(); });
    ajaxPost(data, function() {
        loadDayServices($('#serviceDayId').val());
        loadItinerary();
        $('#addServiceForm')[0].reset();
        $('#addServiceCollapse').collapse('hide');
        showAlert('Service added!');
    });
});

$(document).on('click', '.btn-edit-service', function() {
    var $btn = $(this);
    $('#editServiceId').val($btn.data('id'));
    $('#editServiceType').val($btn.data('type'));
    $('#editServiceDesc').val($btn.data('description'));
    $('#editServiceCost').val($btn.data('cost'));
    $('#editServiceModal').modal('show');
});

$('#editServiceForm').on('submit', function(e) {
    e.preventDefault();
    ajaxPost({
        edit_day_service: 1,
        service_id: $('#editServiceId').val(),
        service_type: $('#editServiceType').val(),
        description: $('#editServiceDesc').val(),
        cost: $('#editServiceCost').val()
    }, function() {
        $('#editServiceModal').modal('hide');
        loadDayServices($('#serviceDayId').val());
        loadItinerary();
        showAlert('Service updated!');
    });
});

jQuery(document).on('click', '.rm-service', function() {
    var serviceId = jQuery(this).data('id');
    confirmAction('Remove this service?', function() {
        ajaxPost({ remove_day_service: 1, service_id: serviceId }, function() {
            loadDayServices(jQuery('#serviceDayId').val());
            loadItinerary();
        });
    });
});

$(document).on('change', '.change-sp-select', function() {
    ajaxPost({
        change_day_service_provider: 1,
        service_id: $(this).data('service-id'),
        service_provider_id: $(this).val()
    }, function() {
        loadDayServices($('#serviceDayId').val());
        loadItinerary();
        showAlert('Provider updated!');
    });
});

jQuery('#btnAiRecalc').on('click', function() {
    var btn = jQuery(this);
    Swal.fire({
        title: 'AI Optimization',
        input: 'text',
        inputLabel: 'What would you like the AI to optimize?',
        inputValue: 'Optimize this itinerary for cost and experience balance',
        showCancelButton: true,
        confirmButtonColor: '#2d6a4f'
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
            ajaxPost({ request_ai_recalculation: 1, trip_id: tripId, instruction: result.value }, function(resp) {
                showAlert('AI recalculation complete!');
                if (resp.response) {
                    jQuery('#aiResponse').html('<div class="alert alert-info small mt-2"><strong>AI Response:</strong><br>' + resp.response + '</div>');
                }
                btn.prop('disabled', false).html('<i class="bi bi-robot"></i> Ask AI to Recalculate');
                loadItinerary();
            }, function() {
                btn.prop('disabled', false).html('<i class="bi bi-robot"></i> Ask AI to Recalculate');
            });
        }
    });
});
</script>
@endsection
