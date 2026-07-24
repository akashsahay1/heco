@extends('emails.layout')
@section('title', 'Password reset code')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Hi {{ $name }},</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    Use the code below to reset your HECO Provider password:
</p>

<div style="margin:0 0 18px; text-align:center;">
    <div style="display:inline-block; padding:14px 28px; background:#f3f6f5; border-radius:10px;
                font-size:34px; letter-spacing:10px; font-weight:700; color:#575753;">
        {{ $otp }}
    </div>
</div>

<p style="margin:0 0 14px; line-height:1.6; color:#777;">
    This code expires in <strong>10 minutes</strong>. If you didn't ask to reset your password,
    you can ignore this email — your password stays unchanged.
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    Warm regards,<br>
    The HECO Partnerships Team
</p>
@endsection
