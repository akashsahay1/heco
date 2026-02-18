<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 12px;
        color: #333;
        margin: 0;
        padding: 20px;
    }
    .header {
        text-align: center;
        border-bottom: 2px solid #198754;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .header h1 {
        color: #198754;
        margin: 0;
        font-size: 22px;
    }
    .header p {
        margin: 4px 0 0;
        color: #666;
        font-size: 13px;
    }
    .trip-info {
        margin-bottom: 20px;
    }
    .trip-info table {
        width: 100%;
    }
    .trip-info td {
        padding: 4px 8px;
        vertical-align: top;
    }
    .trip-info .label {
        color: #666;
        font-size: 11px;
        width: 120px;
    }
    .trip-info .value {
        font-weight: bold;
    }
    .day-section {
        margin-bottom: 15px;
        page-break-inside: avoid;
    }
    .day-header {
        background: #198754;
        color: white;
        padding: 6px 12px;
        font-weight: bold;
        font-size: 13px;
    }
    .day-content {
        border: 1px solid #dee2e6;
        border-top: none;
        padding: 8px 12px;
    }
    .exp-row {
        padding: 4px 0;
        border-bottom: 1px solid #eee;
    }
    .exp-row:last-child {
        border-bottom: none;
    }
    .exp-name {
        font-weight: bold;
        color: #198754;
    }
    .service-row {
        padding: 2px 0;
        font-size: 11px;
        color: #666;
    }
    .service-icon {
        display: inline-block;
        width: 14px;
        text-align: center;
    }
    .financial-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .financial-table td,
    .financial-table th {
        border: 1px solid #ddd;
        padding: 6px 8px;
        font-size: 11px;
    }
    .financial-table th {
        background: #f8f9fa;
        font-weight: bold;
    }
    .text-right {
        text-align: right;
    }
    .text-center {
        text-align: center;
    }
    .text-success {
        color: #198754;
    }
    .text-muted {
        color: #999;
    }
    .bold {
        font-weight: bold;
    }
    .section-title {
        font-size: 14px;
        font-weight: bold;
        color: #198754;
        border-bottom: 1px solid #198754;
        padding-bottom: 4px;
        margin: 20px 0 10px;
    }
    .footer {
        text-align: center;
        font-size: 10px;
        color: #999;
        margin-top: 30px;
        border-top: 1px solid #ddd;
        padding-top: 10px;
    }
    .payment-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .payment-table td,
    .payment-table th {
        border: 1px solid #ddd;
        padding: 4px 8px;
        font-size: 11px;
    }
    .payment-table th {
        background: #f8f9fa;
    }
</style>
</head>
<body>

{{-- 1. Header --}}
<div class="header">
    <h1>HECO Portal</h1>
    <p>HECO - Trip Itinerary</p>
</div>

{{-- 2. Trip Info Table --}}
<div class="trip-info">
    <table>
        <tr>
            <td class="label">Trip ID</td>
            <td class="value text-success">{{ $trip->trip_id }}</td>
            <td class="label">Status</td>
            <td class="value">{{ ucfirst(str_replace('_', ' ', $trip->status ?? 'draft')) }}</td>
        </tr>
        <tr>
            <td class="label">Trip Name</td>
            <td class="value">{{ $trip->trip_name ?? 'Unnamed Trip' }}</td>
            <td class="label">Traveller</td>
            <td class="value">{{ $trip->user->full_name ?? $trip->user->email ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Start Date</td>
            <td class="value">{{ $trip->start_date ? $trip->start_date->format('d M Y') : '-' }}</td>
            <td class="label">End Date</td>
            <td class="value">{{ $trip->end_date ? $trip->end_date->format('d M Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Regions</td>
            <td class="value">
                @if($trip->regions && $trip->regions->count())
                    {{ $trip->regions->pluck('name')->join(', ') }}
                @else
                    -
                @endif
            </td>
            <td class="label">Group Size</td>
            <td class="value">
                {{ ($trip->adults ?? 0) }} adults
                @if(($trip->children ?? 0) > 0), {{ $trip->children }} children @endif
                @if(($trip->infants ?? 0) > 0), {{ $trip->infants }} infants @endif
            </td>
        </tr>
        <tr>
            <td class="label">Origin</td>
            <td class="value">{{ ucfirst($trip->traveller_origin ?? '-') }}</td>
            <td class="label">Start Location</td>
            <td class="value">{{ $trip->start_location ?? '-' }}</td>
        </tr>
        @if($trip->pickup_location || $trip->drop_location)
            <tr>
                <td class="label">Pickup</td>
                <td class="value">{{ $trip->pickup_location ?? '-' }}{{ $trip->pickup_time ? ' at ' . $trip->pickup_time : '' }}</td>
                <td class="label">Drop</td>
                <td class="value">{{ $trip->drop_location ?? '-' }}{{ $trip->drop_time ? ' at ' . $trip->drop_time : '' }}</td>
            </tr>
        @endif
    </table>
</div>

{{-- 3. Day-by-Day Itinerary --}}
<div class="section-title">Day-by-Day Itinerary</div>

@if($trip->tripDays && $trip->tripDays->count())
    @foreach($trip->tripDays as $day)
        <div class="day-section">
            <div class="day-header">
                Day {{ $day->day_number }}
                @if($day->date)
                    - {{ $day->date->format('D, d M Y') }}
                @endif
                @if($day->title)
                    : {{ $day->title }}
                @endif
            </div>
            <div class="day-content">
                {{-- Description --}}
                @if($day->description)
                    <div style="margin-bottom: 6px; font-style: italic; color: #666;">{{ $day->description }}</div>
                @endif

                {{-- Experiences --}}
                @if($day->experiences && $day->experiences->count())
                    @foreach($day->experiences as $dayExp)
                        <div class="exp-row">
                            <span class="exp-name">{{ $dayExp->experience->name ?? 'Experience' }}</span>
                            @if($dayExp->start_time)
                                <span class="text-muted" style="margin-left: 8px;">{{ $dayExp->start_time }}@if($dayExp->end_time) - {{ $dayExp->end_time }}@endif</span>
                            @endif
                            @if($dayExp->total_cost > 0)
                                <span style="float: right;" class="text-success bold">&#8377;{{ number_format($dayExp->total_cost, 2) }}</span>
                            @endif
                            @if($dayExp->experience && $dayExp->experience->short_description)
                                <br><span class="text-muted" style="font-size: 10px;">{{ Str::limit($dayExp->experience->short_description, 150) }}</span>
                            @endif
                        </div>
                    @endforeach
                @endif

                {{-- Services --}}
                @if($day->services && $day->services->count())
                    <div style="margin-top: 6px; border-top: 1px solid #eee; padding-top: 4px;">
                        @foreach($day->services as $service)
                            <div class="service-row">
                                <span class="service-icon">
                                    @switch($service->service_type)
                                        @case('accommodation') &#9750; @break
                                        @case('transport') &#9992; @break
                                        @case('guide') &#9733; @break
                                        @case('meal') &#9749; @break
                                        @case('activity') &#9889; @break
                                        @default &#8226;
                                    @endswitch
                                </span>
                                <span style="text-transform: capitalize;">{{ $service->service_type }}</span>:
                                {{ $service->description ?? '' }}
                                @if($service->serviceProvider)
                                    <span class="text-muted">({{ $service->serviceProvider->name }})</span>
                                @endif
                                @if($service->from_location || $service->to_location)
                                    <span class="text-muted">
                                        {{ $service->from_location ?? '' }}{{ $service->to_location ? ' &rarr; ' . $service->to_location : '' }}
                                    </span>
                                @endif
                                @if($service->cost > 0)
                                    <span style="float: right;">&#8377;{{ number_format($service->cost, 2) }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Empty day notice --}}
                @if((!$day->experiences || !$day->experiences->count()) && (!$day->services || !$day->services->count()))
                    <div class="text-muted" style="text-align: center; padding: 8px 0;">No activities or services scheduled</div>
                @endif
            </div>
        </div>
    @endforeach
@else
    <div class="text-muted text-center" style="padding: 20px;">No itinerary days have been created yet.</div>
@endif

{{-- 4. Financial Summary --}}
<div class="section-title">Financial Summary</div>

<table class="financial-table">
    <thead>
        <tr>
            <th>Cost Component</th>
            <th class="text-right">Amount (&#8377;)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Transport Cost</td>
            <td class="text-right">{{ number_format($trip->transport_cost ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Accommodation Cost</td>
            <td class="text-right">{{ number_format($trip->accommodation_cost ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Guide Cost</td>
            <td class="text-right">{{ number_format($trip->guide_cost ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Activity Cost</td>
            <td class="text-right">{{ number_format($trip->activity_cost ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Other Cost</td>
            <td class="text-right">{{ number_format($trip->other_cost ?? 0, 2) }}</td>
        </tr>
        <tr style="background: #f8f9fa;">
            <td class="bold">Total Cost</td>
            <td class="text-right bold">{{ number_format($trip->total_cost ?? 0, 2) }}</td>
        </tr>
        @if(($trip->margin_rp_amount ?? 0) > 0)
            <tr>
                <td>RP Margin ({{ $trip->margin_rp_percent ?? 0 }}%)</td>
                <td class="text-right">{{ number_format($trip->margin_rp_amount ?? 0, 2) }}</td>
            </tr>
        @endif
        @if(($trip->margin_hrp_amount ?? 0) > 0)
            <tr>
                <td>HRP Margin ({{ $trip->margin_hrp_percent ?? 0 }}%)</td>
                <td class="text-right">{{ number_format($trip->margin_hrp_amount ?? 0, 2) }}</td>
            </tr>
        @endif
        @if(($trip->commission_hct_amount ?? 0) > 0)
            <tr>
                <td>HCT Commission ({{ $trip->commission_hct_percent ?? 0 }}%)</td>
                <td class="text-right">{{ number_format($trip->commission_hct_amount ?? 0, 2) }}</td>
            </tr>
        @endif
        <tr style="background: #f8f9fa;">
            <td class="bold">Subtotal</td>
            <td class="text-right bold">{{ number_format($trip->subtotal ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>GST</td>
            <td class="text-right">{{ number_format($trip->gst_amount ?? 0, 2) }}</td>
        </tr>
        <tr style="background: #198754; color: white;">
            <td class="bold" style="font-size: 13px;">Final Price</td>
            <td class="text-right bold" style="font-size: 13px;">&#8377;{{ number_format($trip->final_price ?? 0, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- 5. Traveller Payments --}}
@if($trip->travellerPayments && $trip->travellerPayments->count())
    <div class="section-title">Traveller Payments</div>

    <table class="payment-table">
        <thead>
            <tr>
                <th>Date</th>
                <th class="text-right">Amount (&#8377;)</th>
                <th>Mode</th>
            </tr>
        </thead>
        <tbody>
            @php $totalPaid = 0; @endphp
            @foreach($trip->travellerPayments as $payment)
                @php $totalPaid += $payment->amount ?? 0; @endphp
                <tr>
                    <td>{{ $payment->payment_date ? $payment->payment_date->format('d M Y') : '-' }}</td>
                    <td class="text-right">{{ number_format($payment->amount ?? 0, 2) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $payment->mode ?? '-')) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: #f8f9fa;">
                <td class="bold">Total Paid</td>
                <td class="text-right bold">&#8377;{{ number_format($totalPaid, 2) }}</td>
                <td></td>
            </tr>
            <tr>
                <td class="bold">Balance Due</td>
                <td class="text-right bold" style="color: {{ (($trip->final_price ?? 0) - $totalPaid) > 0 ? '#dc3545' : '#198754' }};">
                    &#8377;{{ number_format(($trip->final_price ?? 0) - $totalPaid, 2) }}
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
@endif

{{-- 6. Footer --}}
<div class="footer">
    Generated by HECO Portal on {{ date('d M Y, h:i A') }}
    <br>
    This document is system-generated. For queries, contact info@heco.eco
</div>

</body>
</html>
