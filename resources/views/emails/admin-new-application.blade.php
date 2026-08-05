@extends('emails.layout')
@section('title', 'New Partner Application')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">New partner application</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    <strong>{{ $provider->name }}</strong> has applied to join the HECO Collective as a
    <strong>{{ $providerType }}</strong>.
</p>

<table cellpadding="0" cellspacing="0" style="margin:0 0 18px; line-height:1.7; font-size:14px;">
    <tr><td style="padding-right:14px; color:#7a7a6e;">Contact</td><td>{{ $provider->contact_person ?: '—' }}</td></tr>
    <tr><td style="padding-right:14px; color:#7a7a6e;">Email</td><td>{{ $provider->email }}</td></tr>
    <tr><td style="padding-right:14px; color:#7a7a6e;">Phone</td><td>{{ $provider->phone_1 }}</td></tr>
    <tr><td style="padding-right:14px; color:#7a7a6e;">Region</td><td>{{ $provider->region?->name ?: '—' }}</td></tr>
    @if($provider->city || $provider->country)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Based in</td><td>{{ trim(($provider->city ?: '') . ' ' . ($provider->country ?: '')) }}</td></tr>
    @endif
    @if($provider->business_type)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Business type</td><td>{{ $provider->business_type }}</td></tr>
    @endif
    @if(!empty($provider->services_offered))
        <tr><td style="padding-right:14px; color:#7a7a6e;">Services</td><td>{{ implode(', ', (array) $provider->services_offered) }}</td></tr>
    @endif
    @if(!empty($provider->documents))
        <tr><td style="padding-right:14px; color:#7a7a6e;">Documents</td><td>{{ count((array) $provider->documents) }} uploaded</td></tr>
    @endif
</table>

<p style="margin:24px 0; text-align:center;">
    {{-- Straight to the application that arrived, not the queue it sits in.
         The scheme comes from app.url rather than being written in: the mail
         is often sent while handling a request on the portal, so taking the
         scheme from the request would hardcode whatever that happened to be.
         The path comes from the route, so the id cannot drift. --}}
    <a href="{{ (str_starts_with(config('app.url'), 'https') ? 'https' : 'http') . '://' . config('app.admin_domain') . route('hct.providers.show', $provider->id, false) }}"
       style="display:inline-block; background:#79a09f; color:#ffffff; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:600;">
        Review application
    </a>
</p>

<p style="margin:0; line-height:1.6; font-size:13px; color:#7a7a6e;">
    Submitted {{ $provider->created_at?->format('d M Y, H:i') }}.
</p>
@endsection
