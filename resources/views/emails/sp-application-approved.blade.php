@extends('emails.layout')
@section('title', 'Partner Application Approved')

@php
    // "Single structure, role-specific labels" is the client's own rule for
    // these three, and it holds here too: one letter, but the work waiting at
    // the end of it is not the same. A host lists their place, a service
    // provider prices theirs, and a regional partner sells nothing at all —
    // they oversee a region and are worked with by hand in the MVP.
    //
    // Somebody may be more than one of these, so this is a list, not a choice.
    $nextSteps = [];
    if (in_array('hlh', $types, true)) {
        $nextSteps[] = 'Add your place and the experiences you host, so travellers planning a trip can find them.';
    }
    if (in_array('osp', $types, true)) {
        $nextSteps[] = 'Add your rates and keep your availability calendar current, so HCT can bring you into trips.';
    }
    if (in_array('hrp', $types, true)) {
        $nextSteps[] = 'Your region, and the hosts and services working in it, are on your dashboard. HCT will be in touch about trips that pass through it.';
    }
    // An older application with no types recorded still needs a sentence.
    if (! $nextSteps) {
        $nextSteps[] = 'Complete your profile so HCT can start working with you.';
    }
@endphp

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
    <li>Open the HECO app and sign in with your email and new password.</li>
    @foreach($nextSteps as $step)
        <li>{{ $step }}</li>
    @endforeach
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
    <li>Open the HECO app and sign in with your email and password.</li>
    @foreach($nextSteps as $step)
        <li>{{ $step }}</li>
    @endforeach
</ol>

{{-- No button here. The app is where a partner works, and a link in an
     email cannot open it — sending them to a browser login instead would be
     pointing them away from the thing they were given. --}}
<p style="margin:0 0 14px; line-height:1.6; font-size:13px; color:#7a7a6e;">
    Forgot your password? Tap &ldquo;Forgot password?&rdquo; on the app&rsquo;s sign-in screen any time.
</p>
@endif

<p style="margin:24px 0 0; line-height:1.6;">
    Warm regards,<br>
    The HECO Partnerships Team
</p>
@endsection
