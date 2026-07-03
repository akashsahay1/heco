@extends('emails.layout')
@section('title', 'New Support Request')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">New support request</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    A traveller has submitted a support request through the portal.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:16px 0; border-collapse:collapse;">
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e; width:140px;">From</td>
        <td style="padding:8px 0;">{{ $supportRequest->user?->full_name ?? '—' }}</td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e;">Email</td>
        <td style="padding:8px 0;">{{ $supportRequest->user?->email ?? '—' }}</td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e;">Trip</td>
        <td style="padding:8px 0;">{{ $supportRequest->trip_id ? ('#' . $supportRequest->trip_id) : 'No trip attached' }}</td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e;">Status</td>
        <td style="padding:8px 0;">{{ ucfirst($supportRequest->traveller_status ?? 'lead') }}</td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e;">Submitted</td>
        <td style="padding:8px 0;">{{ optional($supportRequest->created_at)->format('Y-m-d H:i') }}</td>
    </tr>
</table>

<p style="margin:14px 0 6px; font-weight:600; color:#2d3a2e;">Message</p>
<p style="margin:0 0 14px; line-height:1.6; white-space:pre-line; background:#f6f8f6; border-radius:8px; padding:14px;">{{ $supportRequest->message }}</p>
@endsection
