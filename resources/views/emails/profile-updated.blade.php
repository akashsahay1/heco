@extends('emails.layout')
@section('title', 'Your Profile Was Updated')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Hi {{ $name }},</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    This is a confirmation that your HECO Portal profile was updated on <strong>{{ $when }}</strong>.
</p>

@if(!empty($changes))
<p style="margin:0 0 8px; line-height:1.6;">The following details were changed:</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e3d8; border-radius:6px; margin:12px 0 20px;">
    @foreach($changes as $label => $value)
        <tr>
            <td style="padding:12px 18px; {{ ! $loop->last ? 'border-bottom:1px solid #e5e3d8;' : '' }}">
                <strong style="color:#7a7a6e; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">{{ $label }}</strong><br>
                <span style="font-size:15px;">{{ $value !== '' && $value !== null ? $value : '—' }}</span>
            </td>
        </tr>
    @endforeach
</table>
@endif

<p style="margin:0 0 14px; line-height:1.6; font-size:13px; color:#7a7a6e;">
    If you didn't make this change, please reply to this email immediately or reset your password from the login page.
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    — The HECO Team
</p>
@endsection
