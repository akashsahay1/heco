@extends('portal.layout')
@section('title', 'Data Deletion Instructions - HECO Portal')

@section('content')
<section class="page-hero">
    <div class="page-hero-content">
        <h1 class="page-hero-title">Data Deletion Instructions</h1>
        <p class="page-hero-subtitle">
            How to request deletion of your HECO account and personal data
        </p>
    </div>
</section>

<section class="dd-section">
    <div class="container">
        <div class="dd-card">
            <h2>Your Right to Deletion</h2>
            <p>
                HECO respects your privacy and your right to control your personal data. If you have signed
                in to HECO using Facebook Login, Google, or directly with email, you may request that we
                delete your account and all personal data we hold about you at any time.
            </p>

            <h2>How to Request Deletion</h2>
            <p>To delete your HECO account and all associated data, choose either of the methods below:</p>

            <h3 style="font-size: var(--text-lg); font-weight: 600; color: var(--heco-primary-900); margin: var(--space-4) 0 var(--space-2);">Option 1 — Email request</h3>
            <p>
                Send an email to
                <a href="mailto:privacy@heco.eco?subject=Data%20Deletion%20Request">privacy@heco.eco</a>
                from the email address registered on your HECO account, with the subject line
                <strong>"Data Deletion Request"</strong>. Include in the body:
            </p>
            <ul>
                <li>Your full name as registered on HECO</li>
                <li>The email address linked to your account</li>
                <li>(Optional) The login method you used: Facebook, Google, or email</li>
            </ul>

            <h3 style="font-size: var(--text-lg); font-weight: 600; color: var(--heco-primary-900); margin: var(--space-4) 0 var(--space-2);">Option 2 — Contact form</h3>
            <p>
                Visit our <a href="/contact">Contact page</a> and select the subject
                <strong>"Customer Support"</strong>. In your message, write
                <strong>"Please delete my HECO account and all my data."</strong>
            </p>

            <h2>What Will Be Deleted</h2>
            <p>Within 30 days of receiving your verified request, we will permanently delete:</p>
            <ul>
                <li>Your account profile (name, email, phone, photo, address)</li>
                <li>Your saved trip itineraries, preferences, and chat history with our AI</li>
                <li>Any social-login linkage (Facebook user ID, Google user ID)</li>
                <li>Communication and marketing preferences</li>
            </ul>

            <h2>What May Be Retained</h2>
            <p>
                We may retain a limited subset of data after deletion only where required by law or for
                legitimate, narrow purposes — for example:
            </p>
            <ul>
                <li>Financial records of completed bookings (retained as required by Indian tax / GST law)</li>
                <li>Anonymised aggregate analytics that cannot identify you</li>
            </ul>
            <p>
                Retained records will be stored securely and accessed only as necessary for the stated
                purpose.
            </p>

            <h2>Confirmation</h2>
            <p>
                Once your request has been processed, we will email you a confirmation that the deletion
                has been completed. If you signed in via Facebook, our system will also notify Facebook
                that the data linked to your Facebook user ID has been removed.
            </p>

            <h2>Questions?</h2>
            <p>
                If you have any questions about this process or our handling of your data, please review
                our <a href="/privacy-policy">Privacy Policy</a> or contact us at
                <a href="mailto:privacy@heco.eco">privacy@heco.eco</a>.
            </p>

            <div class="dd-meta">
                Last updated: {{ date('F Y') }}
            </div>
        </div>
    </div>
</section>
@endsection
