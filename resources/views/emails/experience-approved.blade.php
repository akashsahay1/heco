@extends('emails.layout')
@section('title', 'Your Experience Is Live')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Good news, {{ $name }}!</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    <strong>{{ $experience->name }}</strong> has been reviewed and <strong>approved</strong> by the HECO team.
    It is now live, and travellers planning a trip can find it.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px; border:1px solid #e6e3d8; border-radius:6px;">
    @if($experience->category)
    <tr>
        <td style="padding:10px 14px; background:#f7f5ee; font-size:13px; color:#7a7a6e; width:40%;">Category</td>
        <td style="padding:10px 14px; font-size:14px; color:#2d3a2e;">{{ $experience->category }}</td>
    </tr>
    @endif
    @if($experience->area || $experience->region)
    <tr>
        <td style="padding:10px 14px; background:#f7f5ee; font-size:13px; color:#7a7a6e; border-top:1px solid #e6e3d8;">Where</td>
        <td style="padding:10px 14px; font-size:14px; color:#2d3a2e; border-top:1px solid #e6e3d8;">
            {{ trim(($experience->area ?: '') . ($experience->area && $experience->region ? ', ' : '') . ($experience->region?->name ?: '')) }}
        </td>
    </tr>
    @endif
    {{-- A stay is sold by the night and has no length; what it has is the size
         of the house. Everything else is an outing, and its length is the
         first thing a traveller asks about. --}}
    @if($experience->isStay())
        <tr>
            <td style="padding:10px 14px; background:#f7f5ee; font-size:13px; color:#7a7a6e; border-top:1px solid #e6e3d8;">Size</td>
            <td style="padding:10px 14px; font-size:14px; color:#2d3a2e; border-top:1px solid #e6e3d8;">
                {{ $experience->total_rooms ?: '—' }} rooms &middot; {{ $experience->total_guests ?: '—' }} guests
            </td>
        </tr>
    @elseif($experience->duration_type)
        <tr>
            <td style="padding:10px 14px; background:#f7f5ee; font-size:13px; color:#7a7a6e; border-top:1px solid #e6e3d8;">Duration</td>
            <td style="padding:10px 14px; font-size:14px; color:#2d3a2e; border-top:1px solid #e6e3d8;">
                @if($experience->duration_type === 'less_than_day')
                    {{ (float) $experience->duration_hours }} hours
                @elseif($experience->duration_type === 'single_day')
                    1 day
                @else
                    {{ $experience->duration_days }} days / {{ $experience->duration_nights }} nights
                @endif
            </td>
        </tr>
    @endif
</table>

@if($experience->slug)
<p style="margin:24px 0; text-align:center;">
    {{-- The listing as a traveller sees it, on the portal rather than the
         admin side: this goes to the member who wrote it. --}}
    <a href="{{ rtrim(config('app.url'), '/') . '/experience/' . $experience->slug }}"
       style="display:inline-block; background:#79a09f; color:#ffffff; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:600;">
        See your listing
    </a>
</p>
@endif

<p style="margin:0 0 14px; line-height:1.6;">
    Nothing more is needed from you. If something in it needs changing, open it in your HECO app &mdash;
    edits go back to us for a quick look before they reach travellers.
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    Warm regards,<br>
    The HECO Team
</p>
@endsection
