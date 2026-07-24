@extends('emails.layout')
@section('title', 'Partner Application Approved')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Welcome aboard, {{ $name }}!</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    Great news — your application to join the HECO Collective as a <strong>{{ $providerType }}</strong> has been approved. We're excited to have you on the team.
</p>

@if($setPasswordUrl)
<p style="margin:0 0 14px; line-height:1.6;">
    Here's how to get started:
</p>

<ol style="margin:0 0 14px; padding-left:20px; line-height:1.8;">
    <li>Set your password using the button below.</li>
    <li>Log in at the partner portal with your email and new password.</li>
    <li>Complete your profile and availability calendar so HCT can start assigning you to trips.</li>
</ol>

<p style="margin:24px 0; text-align:center;">
    <a href="{{ $setPasswordUrl }}" style="display:inline-block; background:#79a09f; color:#ffffff; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:600;">
        Set Your Password
    </a>
</p>

<p style="margin:0 0 14px; line-height:1.6; font-size:13px; color:#7a7a6e;">
    This link will expire in 60 minutes. If it lapses, you can request a fresh one any time using the &ldquo;Forgot password?&rdquo; link on the login page.
</p>

<p style="margin:0 0 14px; line-height:1.6; font-size:13px; color:#7a7a6e;">
    Trouble with the button? Paste this into your browser:<br>
    <span style="word-break:break-all; color:#79a09f;">{{ $setPasswordUrl }}</span>
</p>
@else
<p style="margin:0 0 14px; line-height:1.6;">
    Your provider account is <strong>live</strong>. Just log in with the password you created and:
</p>

<ol style="margin:0 0 14px; padding-left:20px; line-height:1.8;">
    <li>Log in at the partner portal with your email and password.</li>
    <li>Complete your profile and availability calendar so HCT can start assigning you to trips.</li>
</ol>

<p style="margin:24px 0; text-align:center;">
    <a href="{{ route('login') }}" style="display:inline-block; background:#79a09f; color:#ffffff; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:600;">
        Log in to your dashboard
    </a>
</p>

<p style="margin:0 0 14px; line-height:1.6; font-size:13px; color:#7a7a6e;">
    Forgot your password? Use the &ldquo;Forgot password?&rdquo; link on the login page any time.
</p>
@endif

<p style="margin:24px 0 0; line-height:1.6;">
    Warm regards,<br>
    The HECO Partnerships Team
</p>
@endsection
