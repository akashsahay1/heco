@extends('emails.layout')
@section('title', $subjectLine ?? 'A note from HECO')

@section('content')
{{-- Admin-authored HTML body. {!! !!} is intentional: the composer in the
     admin panel writes the markup directly so editors can use richer
     formatting (headings, links, images) without losing it. --}}
{!! $bodyHtml !!}

<hr style="border:none; border-top:1px solid #e5e7eb; margin:28px 0;">

<p style="margin:0; font-size:12px; color:#7a7a6e; line-height:1.6;">
    You're receiving this because <strong>{{ $recipientEmail }}</strong> is subscribed to the HECO newsletter.
    To unsubscribe, just reply to this email and we'll remove you immediately.
</p>
@endsection
