@extends('emails.layout')
@section('title', 'Partner Application Received')

@section('content')
<h2 style="margin:0 0 16px; color:#79a09f; font-size:20px;">Hi {{ $name }},</h2>

<p style="margin:0 0 14px; line-height:1.6;">
    Thank you for applying to join the HECO Collective as a <strong>{{ $providerType }}</strong>. We're glad you'd like to partner with us.
</p>

<p style="margin:0 0 14px; line-height:1.6;">
    Our team reviews every application carefully. Here's what happens next:
</p>

<ol style="margin:0 0 14px; padding-left:20px; line-height:1.8;">
    <li>We'll review your submission within <strong>7&ndash;10 business days</strong>.</li>
    <li>If we need additional information, we'll reach out via email or phone.</li>
    <li>Once approved, you'll receive your dashboard login and onboarding pack.</li>
</ol>

<p style="margin:0 0 14px; line-height:1.6;">
    In the meantime, feel free to explore our <a href="https://hecoportal.test/guidelines" style="color:#79a09f; text-decoration:underline;">Partner Guidelines</a> to understand how we work together to deliver regenerative travel experiences.
</p>

<p style="margin:24px 0 0; line-height:1.6;">
    Warm regards,<br>
    The HECO Partnerships Team
</p>
@endsection
