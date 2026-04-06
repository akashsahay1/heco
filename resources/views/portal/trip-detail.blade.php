@extends('portal.layout')
@section('title', ($trip->trip_name ?: 'Trip Details') . ' - HECO Portal')

@section('css')
<style>
    .td-header { background: linear-gradient(135deg, rgba(45,106,79,0.06), rgba(45,106,79,0.02)); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
    .td-header h3 { font-weight: 700; color: var(--heco-green, #2d6a4f); margin: 0 0 8px; }
    .td-meta { font-size: 13px; color: var(--heco-neutral-600, #475569); }
    .td-meta i { margin-right: 4px; }
    .td-meta span { margin-right: 16px; }
    .td-badge { font-size: 11px; padding: 3px 10px; border-radius: 20px; font-weight: 600; }
    .td-badge-open { background: #dbeafe; color: #1d4ed8; }
    .td-badge-confirmed { background: #dcfce7; color: #166534; }
    .td-badge-completed { background: #f3f4f6; color: #374151; }
    .td-badge-running { background: #fef3c7; color: #92400e; }

    .td-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
    @media (max-width: 768px) { .td-grid { grid-template-columns: 1fr; } }

    .td-card { background: #fff; border-radius: 12px; border: 1px solid var(--color-border, #e2e8f0); padding: 20px; margin-bottom: 16px; }
    .td-card-title { font-size: 14px; font-weight: 700; color: var(--heco-green, #2d6a4f); margin-bottom: 12px; }
    .td-card-title i { margin-right: 6px; }

    /* Timeline (read-only) */
    .td-timeline { padding: 16px 8px; }
    .td-day { display: grid; grid-template-columns: 64px 20px 1fr; margin-bottom: 8px; }
    .td-day-label { text-align: right; padding-right: 8px; }
    .td-day-num { font-size: 13px; font-weight: 700; color: var(--heco-green); }
    .td-day-date { font-size: 10px; color: var(--heco-neutral-500); display: block; }
    .td-day-line { position: relative; }
    .td-day-line::before { content: ''; position: absolute; top: 0; bottom: 0; left: 50%; transform: translateX(-50%); width: 3px; background: var(--heco-primary-300, #86efac); }
    .td-day-dot { width: 10px; height: 10px; background: var(--heco-green, #2d6a4f); border-radius: 50%; position: absolute; top: 6px; left: 50%; transform: translateX(-50%); z-index: 1; }
    .td-day-content { padding: 0 0 12px 12px; }
    .td-day-title { font-size: 14px; font-weight: 600; color: var(--heco-primary-700, #15803d); margin-bottom: 4px; }
    .td-day-time { font-size: 11px; color: var(--heco-neutral-500); }
    .td-exp-item { background: linear-gradient(135deg, rgba(34,197,94,0.06), rgba(34,197,94,0.02)); border-radius: 8px; padding: 8px 12px; margin-top: 6px; font-size: 12px; color: var(--heco-neutral-700); }
    .td-exp-name { font-weight: 600; font-size: 13px; color: var(--color-text); }
    .td-group-header { display: grid; grid-template-columns: 64px 20px 1fr; margin-bottom: 4px; }
    .td-group-title { font-size: 13px; font-weight: 700; color: var(--heco-green); padding: 6px 12px; background: linear-gradient(135deg, rgba(45,106,79,0.08), rgba(45,106,79,0.03)); border-radius: 6px; border-left: 3px solid var(--heco-green); display: flex; justify-content: space-between; align-items: center; }
    .td-group-price { font-size: 12px; font-weight: 600; color: var(--heco-primary-600); }
    .td-empty-day { text-align: center; padding: 12px; font-size: 13px; color: var(--heco-green); font-weight: 600; }

    /* Pricing */
    .td-pr-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
    .td-pr-row:last-child { border-bottom: none; }
    .td-pr-row.total { font-weight: 700; font-size: 15px; color: var(--heco-green); border-top: 2px solid var(--heco-green); padding-top: 10px; margin-top: 4px; }
    .td-pr-row.paid { color: #166534; font-weight: 600; }
    .td-pr-row.balance { color: #dc2626; font-weight: 600; }

    /* Payments */
    .td-pay-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    .td-pay-item:last-child { border-bottom: none; }
    .td-pay-status { font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
    .td-pay-status.paid { background: #dcfce7; color: #166534; }
    .td-pay-status.pending { background: #fef3c7; color: #92400e; }
    .td-pay-status.failed { background: #fee2e2; color: #991b1b; }
</style>
@endsection

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
                    <div class="mt-2">
                        @foreach($trip->tripRegions as $tr)
                            <span class="badge bg-success bg-opacity-25 text-success">{{ $tr->region->name ?? '' }}</span>
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
