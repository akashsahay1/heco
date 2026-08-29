@extends('emails.layout')
@section('title', 'New Rate For Review')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">New rate for review</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    <strong>{{ $providerName }}</strong> has submitted a rate and is waiting on a review.
    Until it is approved the service cannot be quoted on a trip.
</p>

<table cellpadding="0" cellspacing="0" style="margin:0 0 18px; line-height:1.7; font-size:14px;">
    <tr><td style="padding-right:14px; color:#7a7a6e;">Item</td><td>{{ $itemLabel }}</td></tr>
    @if($pricing->service_type)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Service</td><td>{{ ucfirst(str_replace('_', ' ', $pricing->service_type)) }}</td></tr>
    @endif
    @if($pricing->price !== null)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Price</td>
            <td>&#8377;{{ number_format((float) $pricing->price, 2) }}@if($pricing->unit) <span style="color:#7a7a6e;">/ {{ $pricing->unit }}</span>@endif</td></tr>
    @endif
    @if($pricing->comfort_tier)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Comfort tier</td><td>{{ $pricing->comfort_tier }}</td></tr>
    @endif
    @if($pricing->room_category)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Room</td><td>{{ $pricing->room_category }}</td></tr>
    @endif
    @if($pricing->vehicle_type)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Vehicle</td><td>{{ $pricing->vehicle_type }}</td></tr>
    @endif
</table>

<p style="margin:24px 0; text-align:center;">
    <a href="{{ (str_starts_with(config('app.url'), 'https') ? 'https' : 'http') . '://' . config('app.admin_domain') . route('hct.pending-pricing', [], false) }}"
       style="display:inline-block; background:#79a09f; color:#ffffff; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:600;">
        Review rates
    </a>
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    &mdash; HECO
</p>
@endsection
