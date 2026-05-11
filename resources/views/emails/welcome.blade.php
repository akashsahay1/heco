@extends('emails.layout')
@section('title', 'Welcome to HECO Portal')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Welcome, {{ $name }}!</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    Thank you for joining the HECO Portal — we're delighted to have you with us.
</p>

<p style="margin:0 0 14px; line-height:1.6;">
    HECO is a regenerative travel platform that connects curious travellers with carefully curated experiences across the Himalayas. Whether you're planning your first trek, a wellness retreat, or a deep-dive into a remote village, our local hosts and trip designers are ready to help you craft a journey that gives back to the people and places you visit.
</p>

<p style="margin:24px 0; text-align:center;">
    <a href="{{ $loginUrl }}" style="display:inline-block; background:#79a09f; color:#ffffff; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:600;">
        Start Exploring
    </a>
</p>

<p style="margin:0 0 14px; line-height:1.6;">
    If you have any questions, just reply to this email — a real person will get back to you.
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    Warm regards,<br>
    The HECO Team
</p>
@endsection
