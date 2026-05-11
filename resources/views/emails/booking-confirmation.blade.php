@extends('emails.layout')
@section('title', 'Your Booking is Confirmed')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Your booking is confirmed, {{ $trip['traveller_name'] ?? 'Traveller' }}!</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    We're thrilled to confirm your trip with HECO. Below is a summary of your itinerary — keep this email handy as your reference.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e3d8; border-radius:6px; margin:20px 0;">
    <tr>
        <td style="padding:14px 18px; border-bottom:1px solid #e5e3d8;">
            <strong style="color:#7a7a6e; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Trip ID</strong><br>
            <span style="font-size:15px;">{{ $trip['trip_id'] ?? 'HECO-XXXX' }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 18px; border-bottom:1px solid #e5e3d8;">
            <strong style="color:#7a7a6e; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Trip Name</strong><br>
            <span style="font-size:15px;">{{ $trip['trip_name'] ?? 'Himalayan Adventure' }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 18px; border-bottom:1px solid #e5e3d8;">
            <strong style="color:#7a7a6e; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Dates</strong><br>
            <span style="font-size:15px;">{{ $trip['start_date'] ?? 'TBD' }} &mdash; {{ $trip['end_date'] ?? 'TBD' }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 18px; border-bottom:1px solid #e5e3d8;">
            <strong style="color:#7a7a6e; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Travellers</strong><br>
            <span style="font-size:15px;">{{ $trip['adults'] ?? 2 }} adults, {{ $trip['children'] ?? 0 }} children</span>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 18px;">
            <strong style="color:#7a7a6e; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Total</strong><br>
            <span style="font-size:18px; color:#79a09f; font-weight:600;">&#8377; {{ number_format($trip['total_cost'] ?? 0) }}</span>
        </td>
    </tr>
</table>

<p style="margin:0 0 14px; line-height:1.6;">
    Your trip designer will be in touch shortly with your day-by-day itinerary, packing list, and host introductions. If anything needs to change, simply reply to this email.
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    Safe travels,<br>
    The HECO Team
</p>
@endsection
