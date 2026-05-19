@extends('emails.layout')
@section('title', 'Welcome to HECO')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Welcome to HECO!</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    Thanks for joining our newsletter. We'll send you occasional updates on new regions, hand-picked experiences, and stories from the communities we travel with — no spam, no clutter.
</p>

<p style="margin:0 0 14px; line-height:1.6;">
    HECO is a regenerative travel platform that connects curious travellers with carefully curated experiences across the Himalayas. Every trip directly supports local livelihoods, conservation efforts, and cultural preservation.
</p>

<p style="margin:24px 0; text-align:center;">
    <a href="{{ $homeUrl }}" style="display:inline-block; background:#79a09f; color:#ffffff; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:600;">
        Start Exploring
    </a>
</p>

<p style="margin:0 0 14px; line-height:1.6; font-size:13px; color:#7a7a6e;">
    You're receiving this because you subscribed at <a href="{{ url('/') }}" style="color:#5f8484;">{{ parse_url(url('/'), PHP_URL_HOST) }}</a>. If this wasn't you, simply ignore this message and you'll hear nothing further.
</p>
@endsection
