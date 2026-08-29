@extends('emails.layout')
@section('title', 'New Experience For Review')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">New experience for review</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    <strong>{{ $providerName }}</strong> has submitted <strong>{{ $experience->name }}</strong> and is waiting on a review.
</p>

<table cellpadding="0" cellspacing="0" style="margin:0 0 18px; line-height:1.7; font-size:14px;">
    @if($experience->category)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Category</td><td>{{ $experience->category }}</td></tr>
    @endif
    @if($experience->type)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Type</td><td>{{ $experience->type }}</td></tr>
    @endif
    <tr><td style="padding-right:14px; color:#7a7a6e;">Region</td><td>{{ $experience->region?->name ?: '—' }}</td></tr>
    @if($experience->area)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Area</td><td>{{ $experience->area }}</td></tr>
    @endif
    @if($experience->isStay())
        <tr><td style="padding-right:14px; color:#7a7a6e;">Size</td><td>{{ $experience->total_rooms ?: '—' }} rooms, {{ $experience->total_guests ?: '—' }} guests</td></tr>
    @elseif($experience->duration_type)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Duration</td><td>
            @if($experience->duration_type === 'less_than_day')
                {{ (float) $experience->duration_hours }} hours
            @elseif($experience->duration_type === 'single_day')
                1 day
            @else
                {{ $experience->duration_days }} days / {{ $experience->duration_nights }} nights
            @endif
        </td></tr>
    @endif
    @if($experience->base_cost_per_person)
        <tr><td style="padding-right:14px; color:#7a7a6e;">Cost per person</td><td>&#8377;{{ number_format((float) $experience->base_cost_per_person, 2) }}</td></tr>
    @endif
</table>

@if($experience->short_description)
<p style="margin:0 0 18px; line-height:1.6; color:#4a4a42; font-style:italic;">
    &ldquo;{{ $experience->short_description }}&rdquo;
</p>
@endif

<p style="margin:24px 0; text-align:center;">
    {{-- Straight to the listing that arrived, not the queue it sits in. The
         scheme comes from app.url rather than the request, which is whatever
         happened to be handling the save. --}}
    <a href="{{ (str_starts_with(config('app.url'), 'https') ? 'https' : 'http') . '://' . config('app.admin_domain') . route('hct.experiences.edit', $experience->id, false) }}"
       style="display:inline-block; background:#79a09f; color:#ffffff; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:600;">
        Review experience
    </a>
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    &mdash; HECO
</p>
@endsection
