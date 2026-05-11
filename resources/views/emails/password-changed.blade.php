@extends('emails.layout')
@section('title', 'Your Password Was Changed')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Hi {{ $name }},</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    Your HECO Portal account password was changed on <strong>{{ $when }}</strong>.
</p>

<p style="margin:0 0 14px; line-height:1.6;">
    If this was you, no further action is required — you can sign in with your new password.
</p>

<div style="background:#fff8e6; border-left:4px solid #d4a72c; padding:14px 18px; margin:20px 0; border-radius:4px;">
    <p style="margin:0; line-height:1.6; font-size:14px; color:#5c4a16;">
        <strong>Didn't change your password?</strong><br>
        Your account may be compromised. Please <a href="{{ url('/forgot-password') }}" style="color:#79a09f; text-decoration:underline;">reset your password</a> immediately and reply to this email so we can help secure your account.
    </p>
</div>

<p style="margin:24px 0 0; line-height:1.6;">
    — The HECO Security Team
</p>
@endsection
