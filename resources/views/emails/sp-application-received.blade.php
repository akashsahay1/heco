@extends('emails.layout')
@section('title', 'Partner Application Received')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Hi {{ $name }},</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    Thank you for applying to join the HECO Collective as a <strong>{{ $providerType }}</strong>. We're glad you'd like to partner with us.
</p>

@if($setPasswordUrl)
<p style="margin:0 0 16px; line-height:1.6;">
    <strong>Next, create your password</strong> to secure your provider account. You can sign in and track your application status right away.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
    <tr>
        <td style="border-radius:8px; background:#79a09f;">
            <a href="{{ $setPasswordUrl }}" style="display:inline-block; padding:12px 22px; color:#ffffff; text-decoration:none; font-weight:600; border-radius:8px;">
                Create your password
            </a>
        </td>
    </tr>
</table>
@endif

<p style="margin:0 0 14px; line-height:1.6;">
    Here's what happens next:
</p>

<ol style="margin:0 0 14px; padding-left:20px; line-height:1.8;">
    @if($setPasswordUrl)<li>Create your password using the button above, then sign in.</li>@endif
    <li>Our team reviews your submission &mdash; usually within <strong>1&ndash;2 business days</strong>.</li>
    <li>If we need anything else, we'll reach out via email or phone.</li>
    <li>Once approved, you'll get full access to your provider dashboard.</li>
</ol>

<p style="margin:0 0 14px; line-height:1.6;">
    In the meantime, feel free to explore our <a href="https://hecoportal.test/guidelines" style="color:#79a09f; text-decoration:underline;">Partner Guidelines</a> to understand how we work together to deliver regenerative travel experiences.
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    Warm regards,<br>
    The HECO Partnerships Team
</p>
@endsection
