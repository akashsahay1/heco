@extends('emails.layout')
@section('title', 'Payment Received')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Payment Received &mdash; Thank You!</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    Hi {{ $payment['traveller_name'] ?? 'there' }}, we've successfully received your payment. This email is your receipt — please retain it for your records.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e3d8; border-radius:6px; margin:20px 0;">
    <tr>
        <td style="padding:14px 18px; border-bottom:1px solid #e5e3d8;">
            <strong style="color:#7a7a6e; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Amount Paid</strong><br>
            <span style="font-size:20px; color:#79a09f; font-weight:600;">&#8377; {{ number_format($payment['amount'] ?? 0) }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 18px; border-bottom:1px solid #e5e3d8;">
            <strong style="color:#7a7a6e; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Payment Date</strong><br>
            <span style="font-size:15px;">{{ $payment['payment_date'] ?? now()->format('d M Y') }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 18px; border-bottom:1px solid #e5e3d8;">
            <strong style="color:#7a7a6e; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Trip ID</strong><br>
            <span style="font-size:15px;">{{ $payment['trip_id'] ?? 'HECO-XXXX' }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 18px;">
            <strong style="color:#7a7a6e; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Payment Reference</strong><br>
            <span style="font-size:15px; font-family:monospace;">{{ $payment['reference'] ?? 'pay_xxxxxxxxxxxxxx' }}</span>
        </td>
    </tr>
</table>

<p style="margin:0 0 14px; line-height:1.6;">
    A formal GST invoice will follow separately within 24 hours. If you need it sooner, just reply to this email and we'll prioritise it.
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    Thank you for choosing HECO,<br>
    The HECO Team
</p>
@endsection
