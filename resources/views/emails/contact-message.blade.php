@extends('emails.layout')
@section('title', 'Contact form message')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Somebody wrote in</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    This came through the Contact page. Reply to this mail and it goes straight
    back to them.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:16px 0; border-collapse:collapse;">
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e; width:140px;">Name</td>
        <td style="padding:8px 0;">{{ $name }}</td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e;">Email</td>
        <td style="padding:8px 0;"><a href="mailto:{{ $email }}" style="color:#79a09f;">{{ $email }}</a></td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e;">Phone</td>
        <td style="padding:8px 0;">{{ $phone ?: '—' }}</td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e;">About</td>
        <td style="padding:8px 0;">{{ $topic }}</td>
    </tr>
</table>

<p style="margin:0 0 8px; font-weight:600; color:#2d3a2e;">What they said</p>
<div style="margin:0 0 20px; padding:14px 16px; background:#f6f8f6; border-left:3px solid #79a09f; line-height:1.6; white-space:pre-wrap;">{{ $body }}</div>

@if($sentFrom)
<p style="margin:0; color:#8a948b; font-size:12px;">Sent from {{ $sentFrom }}</p>
@endif
@endsection
