@extends('emails.layout')
@section('title', 'New Newsletter Signup')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">New newsletter signup</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    Someone just joined the HECO newsletter.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:16px 0; border-collapse:collapse;">
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e; width:140px;">Email</td>
        <td style="padding:8px 0;">{{ $subscriber->email }}</td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e;">Registered user?</td>
        <td style="padding:8px 0;">
            @if($subscriber->is_customer)
                Yes — user&nbsp;#{{ $subscriber->user_id }}
            @else
                No
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e;">Source</td>
        <td style="padding:8px 0;">{{ $subscriber->source ?: '—' }}</td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e;">IP</td>
        <td style="padding:8px 0;">{{ $subscriber->ip_address ?: '—' }}</td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600; color:#2d3a2e;">Signed up at</td>
        <td style="padding:8px 0;">{{ optional($subscriber->subscribed_at)->format('Y-m-d H:i') }}</td>
    </tr>
</table>

<p style="margin:24px 0; text-align:center;">
    <a href="{{ (str_starts_with(config('app.url'), 'https') ? 'https' : 'http') . '://' . config('app.admin_domain') }}/newsletter" style="display:inline-block; background:#79a09f; color:#ffffff; padding:10px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">
        Open Subscriber Admin
    </a>
</p>
@endsection
