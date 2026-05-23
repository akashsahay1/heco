@extends('emails.layout')
@section('title', 'Pricing Update Approved')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Good news, {{ $name }}!</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    Your pricing update has been reviewed and <strong>approved</strong> by the HECO team. It is now live and will be used for upcoming trip costings.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px; border:1px solid #e6e3d8; border-radius:6px;">
    <tr>
        <td style="padding:10px 14px; background:#f7f5ee; font-size:13px; color:#7a7a6e; width:40%;">Item</td>
        <td style="padding:10px 14px; font-size:14px; color:#2d3a2e;">{{ $itemLabel }}</td>
    </tr>
    @if($oldPrice)
    <tr>
        <td style="padding:10px 14px; background:#f7f5ee; font-size:13px; color:#7a7a6e; border-top:1px solid #e6e3d8;">Previous price</td>
        <td style="padding:10px 14px; font-size:14px; color:#9a9a8e; text-decoration:line-through; border-top:1px solid #e6e3d8;">&#8377;{{ $oldPrice }}@if($unit) <span style="text-decoration:none; color:#9a9a8e;">/ {{ $unit }}</span>@endif</td>
    </tr>
    @endif
    <tr>
        <td style="padding:10px 14px; background:#f7f5ee; font-size:13px; color:#7a7a6e; border-top:1px solid #e6e3d8;">{{ $oldPrice ? 'New price' : 'Approved price' }}</td>
        <td style="padding:10px 14px; font-size:15px; font-weight:700; color:#2d3a2e; border-top:1px solid #e6e3d8;">&#8377;{{ $newPrice }}@if($unit) <span style="font-weight:400; font-size:13px; color:#7a7a6e;">/ {{ $unit }}</span>@endif</td>
    </tr>
</table>

<p style="margin:0 0 14px; line-height:1.6;">
    No further action is needed on your side &mdash; the new rate is already in effect. If you didn&rsquo;t request this change or have any questions, just reply to this email.
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    Warm regards,<br>
    The HECO Team
</p>
@endsection
