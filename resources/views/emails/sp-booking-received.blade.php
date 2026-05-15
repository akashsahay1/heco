@extends('emails.layout')
@section('title', 'New Booking Received')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">New booking for your property, {{ $spName }}</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    A traveller's trip has just placed a {{ $status === 'confirmed' ? 'confirmed' : 'tentative' }} hold on your inventory.
</p>

<table style="width:100%; border-collapse:collapse; margin:16px 0;">
    <tr>
        <td style="padding:8px 12px; background:#f0f5f4; border-radius:6px 0 0 0; font-weight:600; color:#4a6663; width:35%;">Trip</td>
        <td style="padding:8px 12px; background:#f0f5f4; border-radius:0 6px 0 0;">#{{ $tripId }}</td>
    </tr>
    @if($travellerName)
    <tr>
        <td style="padding:8px 12px; background:#f6faf9; font-weight:600; color:#4a6663;">Traveller</td>
        <td style="padding:8px 12px; background:#f6faf9;">{{ $travellerName }}</td>
    </tr>
    @endif
    <tr>
        <td style="padding:8px 12px; background:#f0f5f4; font-weight:600; color:#4a6663;">Date</td>
        <td style="padding:8px 12px; background:#f0f5f4;">{{ $date }}</td>
    </tr>
    <tr>
        <td style="padding:8px 12px; background:#f6faf9; font-weight:600; color:#4a6663;">Room category</td>
        <td style="padding:8px 12px; background:#f6faf9;">{{ $roomCategory }}{{ $comfortTier ? ' — ' . $comfortTier : '' }}</td>
    </tr>
    <tr>
        <td style="padding:8px 12px; background:#f0f5f4; border-radius:0 0 0 6px; font-weight:600; color:#4a6663;">Quantity</td>
        <td style="padding:8px 12px; background:#f0f5f4; border-radius:0 0 6px 0;">{{ $quantity }} {{ \Illuminate\Support\Str::plural('room', $quantity) }}</td>
    </tr>
</table>

<p style="margin:0 0 14px; line-height:1.6;">
    @if($status === 'held')
        This booking is currently <strong>held</strong> — the traveller hasn't confirmed the trip yet. Your inventory is reserved and will not be offered to other trips. We'll send another email when the trip is confirmed (or released).
    @elseif($status === 'confirmed')
        This booking is <strong>confirmed</strong>. Please block the dates on any external channels (Booking.com / Airbnb) if not already syncing via iCal.
    @endif
</p>

<p style="margin:24px 0; text-align:center;">
    <a href="{{ url('/sp/dashboard') }}" style="display:inline-block; background:#79a09f; color:#ffffff; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:600;">
        Open Dashboard
    </a>
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    Warm regards,<br>
    The HECO Partnerships Team
</p>
@endsection
