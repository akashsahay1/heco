@extends('portal.layout')
@section('title', ($trip->trip_name ?: 'Trip Details') . ' - HECO Portal')

@section('content')
<div class="container py-4">
    {{-- Back link --}}
    <a href="/my-itineraries" class="text-muted text-decoration-none mb-3 d-inline-block" style="font-size:13px;">
        <i class="bi bi-arrow-left"></i> Back to Itineraries
    </a>

    {{-- Header --}}
    <div class="td-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h3>{{ $trip->trip_name ?: 'My Trip' }}</h3>
                <div class="td-meta">
                    <span><i class="bi bi-hash"></i>{{ $trip->trip_id }}</span>
                    @if($trip->start_date)
                        <span><i class="bi bi-calendar3"></i>{{ $trip->start_date->format('d M Y') }}@if($trip->end_date) — {{ $trip->end_date->format('d M Y') }}@endif</span>
                    @endif
                    <span><i class="bi bi-people"></i>{{ $trip->adults }} Adults{{ $trip->children ? ', ' . $trip->children . ' Children' : '' }}{{ $trip->infants ? ', ' . $trip->infants . ' Infants' : '' }}</span>
                </div>
                @if($trip->tripRegions && $trip->tripRegions->count())
                    <div class="mt-2 d-flex flex-wrap gap-1">
                        @foreach($trip->tripRegions as $tr)
                            @if($tr->region)
                                <span class="td-region-badge">{{ $tr->region->name }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
            <div>
                @php
                    $statusMap = ['not_confirmed' => ['Open', 'open'], 'confirmed' => ['Confirmed', 'confirmed'], 'running' => ['Running', 'running'], 'completed' => ['Completed', 'completed']];
                    $s = $statusMap[$trip->status] ?? ['Unknown', 'open'];
                @endphp
                <span class="td-badge td-badge-{{ $s[1] }}">{{ $s[0] }}</span>
            </div>
        </div>
    </div>

    {{-- Grid: Timeline (left) + Sidebar (right) --}}
    <div class="td-grid">
        {{-- Timeline --}}
        <div>
            <div class="td-card">
                <div class="td-card-title"><i class="bi bi-calendar3"></i> Trip Timeline</div>
                <div class="td-timeline" id="timelineContainer">
                    <div class="text-center py-4 text-muted">Loading timeline...</div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div>
            {{-- Pricing --}}
            <div class="td-card">
                <div class="td-card-title"><i class="bi bi-receipt"></i> Pricing Summary</div>
                <div id="pricingContainer">
                    <div class="text-center py-3 text-muted" style="font-size:13px;">Loading...</div>
                </div>
            </div>

            {{-- Payment History --}}
            <div class="td-card">
                <div class="td-card-title"><i class="bi bi-credit-card"></i> Payment History</div>
                <div id="paymentsContainer">
                    <div class="text-center py-3 text-muted" style="font-size:13px;">Loading...</div>
                </div>
            </div>

            {{-- Experiences --}}
            <div class="td-card">
                <div class="td-card-title"><i class="bi bi-list-check"></i> Experiences</div>
                <div>
                    @forelse($trip->selectedExperiences as $se)
                        <div class="d-flex align-items-center gap-2 mb-2" style="font-size:13px;">
                            @if($se->experience && $se->experience->card_image)
                                <img src="{{ $se->experience->card_image }}" alt="" style="width:36px; height:36px; border-radius:6px; object-fit:cover;">
                            @endif
                            <div>
                                <strong>{{ $se->experience->name ?? 'Experience #'.$se->experience_id }}</strong>
                                <span class="text-muted" style="font-size:11px;">ID: #{{ $se->experience_id }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted" style="font-size:13px;">No experiences added.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
jQuery(function() {
    var tripId = {{ $trip->id }};
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    // Load timeline
    ajaxPost({ get_trip_timeline: 1, trip_id: tripId }, function(resp) {
        renderTimeline(resp);
    });

    // Load pricing
    ajaxPost({ get_trip_pricing: 1, trip_id: tripId }, function(resp) {
        renderPricing(resp.pricing || resp);
    });

    // Load payments
    ajaxPost({ get_traveller_payment_history: 1, trip_id: tripId }, function(resp) {
        renderPayments(resp.payments || []);
    });

    function renderTimeline(resp) {
        var days = resp.days || [];
        if (!days.length) {
            jQuery('#timelineContainer').html('<p class="text-center text-muted" style="font-size:13px;">No itinerary generated yet.</p>');
            return;
        }

        var tripStartDate = resp.start_date ? new Date(resp.start_date) : null;
        var shownGroups = {};
        var html = '';

        days.forEach(function(day, index) {
            // Date
            var dateObj = null;
            if (day.date) {
                dateObj = new Date(day.date);
            } else if (tripStartDate) {
                dateObj = new Date(tripStartDate);
                dateObj.setDate(dateObj.getDate() + index);
            }
            var formattedDate = dateObj ? dateObj.getDate() + ' ' + months[dateObj.getMonth()] + ' ' + dateObj.getFullYear() : '';

            // Experience group header
            if (day.experiences && day.experiences.length) {
                var firstExp = day.experiences[0].experience;
                var expName = firstExp ? firstExp.name : '';
                if (expName && !shownGroups[expName]) {
                    shownGroups[expName] = true;
                    var expPrice = firstExp && firstExp.base_cost_per_person ? fmtCurrency(firstExp.base_cost_per_person) + '/person' : '';
                    html += '<div class="td-group-header">';
                    html += '<div></div><div class="td-day-line"></div>';
                    html += '<div class="td-group-title"><span>' + expName + ' <span style="font-size:10px;color:var(--heco-neutral-500);">#' + firstExp.id + '</span></span>';
                    if (expPrice) html += '<span class="td-group-price">' + expPrice + '</span>';
                    html += '</div></div>';
                }
            }

            // Day title
            var dayTitle = '';
            if (day.experiences && day.experiences.length) {
                var firstDe = day.experiences[0];
                var fe = firstDe.experience;
                if (fe && fe.days && fe.days.length) {
                    var edNum = firstDe._expDayNum || 1;
                    var matched = fe.days.length === 1 ? fe.days[0] : fe.days.find(function(d) { return d.day_number === edNum; }) || fe.days[0];
                    if (matched && matched.title) dayTitle = matched.title;
                }
            }

            // Time
            var dayTime = '';
            if (day.experiences && day.experiences.length) {
                var de0 = day.experiences[0];
                if (de0.start_time) dayTime = de0.start_time + (de0.end_time ? ' - ' + de0.end_time : '');
            }

            html += '<div class="td-day">';
            // Left
            html += '<div class="td-day-label"><span class="td-day-num">Day ' + (index + 1) + '</span><span class="td-day-date">' + formattedDate + '</span></div>';
            // Center
            html += '<div class="td-day-line"><div class="td-day-dot"></div></div>';
            // Right
            html += '<div class="td-day-content">';

            if (dayTitle) {
                html += '<div class="td-day-title">' + dayTitle;
                if (dayTime) html += ' <span class="td-day-time"><i class="bi bi-clock"></i> ' + dayTime + '</span>';
                html += '</div>';
            }

            if (day.experiences && day.experiences.length) {
                day.experiences.forEach(function(de) {
                    var exp = de.experience;
                    var desc = '';
                    if (exp && exp.days && exp.days.length) {
                        var edn = de._expDayNum || 1;
                        var ed = exp.days.length === 1 ? exp.days[0] : exp.days.find(function(d) { return d.day_number === edn; }) || exp.days[0];
                        if (ed && ed.short_description) desc = ed.short_description;
                    } else if (de.notes) {
                        desc = de.notes;
                    }
                    html += '<div class="td-exp-item">';
                    if (desc) html += desc;
                    html += '</div>';
                });
            } else {
                // Empty day (arrival, departure, rest etc.)
                var dayTypeMap = {
                    arrival: { icon: 'bi-airplane', label: 'Arrival & Acclimatization' },
                    departure: { icon: 'bi-airplane', label: 'Departure Day' },
                    rest: { icon: 'bi-moon', label: 'Rest & Relax' },
                    travel: { icon: 'bi-signpost-split', label: 'Travel Day' },
                    free: { icon: 'bi-compass', label: 'Free Day' },
                    activity: { icon: 'bi-lightning', label: 'Activity Day' }
                };
                var dt = dayTypeMap[day.day_type] || dayTypeMap['travel'];
                var label = day.title || dt.label;
                var desc = day.description || day.notes || '';
                html += '<div class="td-empty-day"><i class="bi ' + dt.icon + '"></i> ' + label + '</div>';
                if (desc) html += '<div style="font-size:12px; color:var(--heco-neutral-500); text-align:center;">' + desc + '</div>';
            }

            html += '</div></div>';
        });

        jQuery('#timelineContainer').html(html);
    }

    function renderPricing(p) {
        var html = '';
        html += '<div class="td-pr-row"><span>Transport</span><span>' + fmtCurrency(p.transport_cost) + '</span></div>';
        html += '<div class="td-pr-row"><span>Accommodation</span><span>' + fmtCurrency(p.accommodation_cost) + '</span></div>';
        html += '<div class="td-pr-row"><span>Guide</span><span>' + fmtCurrency(p.guide_cost) + '</span></div>';
        html += '<div class="td-pr-row"><span>Activities</span><span>' + fmtCurrency(p.activity_cost) + '</span></div>';
        html += '<div class="td-pr-row"><span>Extra Days</span><span>' + fmtCurrency(p.extra_day_cost) + '</span></div>';
        html += '<div class="td-pr-row"><span>Other</span><span>' + fmtCurrency(p.other_cost) + '</span></div>';
        html += '<div class="td-pr-row"><span>Subtotal</span><span>' + fmtCurrency(p.subtotal) + '</span></div>';
        html += '<div class="td-pr-row"><span>RP Contribution</span><span>' + fmtCurrency(p.margin_rp_amount) + '</span></div>';
        html += '<div class="td-pr-row"><span>GST</span><span>' + fmtCurrency(p.gst_amount) + '</span></div>';
        html += '<div class="td-pr-row total"><span>Final Price</span><span>' + fmtCurrency(p.final_price) + '</span></div>';
        if (p.total_paid > 0) {
            html += '<div class="td-pr-row paid"><span>Total Paid</span><span>' + fmtCurrency(p.total_paid) + '</span></div>';
        }
        if (p.balance_due > 0) {
            html += '<div class="td-pr-row balance"><span>Balance Due</span><span>' + fmtCurrency(p.balance_due) + '</span></div>';
        }
        jQuery('#pricingContainer').html(html);
    }

    function renderPayments(payments) {
        if (!payments.length) {
            jQuery('#paymentsContainer').html('<p class="text-center text-muted" style="font-size:13px;">No payments yet.</p>');
            return;
        }
        var html = '';
        payments.forEach(function(pay) {
            var d = pay.payment_date ? new Date(pay.payment_date) : null;
            var dateStr = d ? d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() : '';
            var status = pay.payment_status || 'pending';
            html += '<div class="td-pay-item">';
            html += '<div><strong>' + fmtCurrency(pay.amount) + '</strong><br><span style="font-size:11px; color:var(--heco-neutral-500);">' + dateStr + ' &middot; ' + (pay.mode || '') + '</span></div>';
            html += '<span class="td-pay-status ' + status + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
            html += '</div>';
        });
        jQuery('#paymentsContainer').html(html);
    }
});
</script>
@endsection
