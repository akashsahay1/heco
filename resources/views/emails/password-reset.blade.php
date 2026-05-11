@extends('emails.layout')
@section('title', 'Reset Your Password')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Hi {{ $name }},</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    We received a request to reset the password on your HECO Portal account. Click the button below to choose a new password.
</p>

<p style="margin:24px 0; text-align:center;">
    <a href="{{ $resetUrl }}" style="display:inline-block; background:#79a09f; color:#ffffff; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:600;">
        Reset Password
    </a>
</p>

<p style="margin:0 0 14px; line-height:1.6; font-size:13px; color:#7a7a6e;">
    This link will expire in 60 minutes. If you didn't request a password reset, you can safely ignore this email — your password will remain unchanged.
</p>

<p style="margin:0 0 14px; line-height:1.6; font-size:13px; color:#7a7a6e;">
    Trouble with the button? Paste this URL into your browser:<br>
    <span style="word-break:break-all; color:#79a09f;">{{ $resetUrl }}</span>
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    — The HECO Team
</p>
@endsection
