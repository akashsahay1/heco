@extends('portal.layout')
@section('title', 'Privacy Policy - HECO Portal')

@section('css')
<style>
/* Legal Hero */
.legal-hero {
    background: linear-gradient(135deg, var(--heco-primary-800) 0%, var(--heco-primary-700) 100%);
    padding: var(--space-20) var(--space-6);
    text-align: center;
    color: white;
}

.legal-hero-content {
    max-width: 800px;
    margin: 0 auto;
}

.legal-hero-title {
    font-size: var(--text-4xl);
    font-weight: var(--font-bold);
    margin: 0 0 var(--space-3);
}

.legal-hero-meta {
    font-size: var(--text-base);
    opacity: 0.8;
    margin: 0;
}

/* Legal Content */
.legal-content {
    padding: var(--space-16) var(--space-6);
}

.legal-content .container {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: var(--space-12);
}

/* Legal Nav */
.legal-nav {
    position: sticky;
    top: 100px;
    align-self: start;
    background: var(--heco-neutral-50);
    padding: var(--space-6);
    border-radius: var(--radius-xl);
    border: 1px solid var(--color-border);
}

.legal-nav h4 {
    font-size: var(--text-sm);
    font-weight: var(--font-semibold);
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: var(--tracking-wider);
    margin: 0 0 var(--space-4);
}

.legal-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.legal-nav li {
    margin-bottom: var(--space-2);
}

.legal-nav a {
    display: block;
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    text-decoration: none;
    padding: var(--space-2) 0;
    transition: all var(--transition-fast);
}

.legal-nav a:hover {
    color: var(--color-primary);
    padding-left: var(--space-2);
}

/* Legal Body */
.legal-body {
    max-width: 800px;
}

.legal-section {
    margin-bottom: var(--space-12);
    scroll-margin-top: 100px;
}

.legal-section h2 {
    font-size: var(--text-2xl);
    font-weight: var(--font-bold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-5);
    padding-bottom: var(--space-3);
    border-bottom: 2px solid var(--heco-primary-100);
}

.legal-section h3 {
    font-size: var(--text-lg);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-800);
    margin: var(--space-6) 0 var(--space-3);
}

.legal-section p {
    font-size: var(--text-base);
    color: var(--color-text);
    line-height: var(--leading-relaxed);
    margin: 0 0 var(--space-4);
}

.legal-section ul {
    margin: 0 0 var(--space-4);
    padding-left: var(--space-6);
}

.legal-section li {
    font-size: var(--text-base);
    color: var(--color-text);
    line-height: var(--leading-relaxed);
    margin-bottom: var(--space-2);
}

.legal-section a {
    color: var(--color-primary);
    text-decoration: none;
}

.legal-section a:hover {
    text-decoration: underline;
}

/* Contact Card */
.legal-contact-card {
    background: var(--heco-neutral-50);
    padding: var(--space-6);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    margin-top: var(--space-4);
}

.legal-contact-card p {
    margin: 0 0 var(--space-2);
}

.legal-contact-card p:last-child {
    margin-bottom: 0;
}

.legal-contact-card i {
    color: var(--color-primary);
    margin-right: var(--space-2);
}

/* Responsive */
@media (max-width: 991px) {
    .legal-content .container {
        grid-template-columns: 1fr;
    }

    .legal-nav {
        position: static;
        margin-bottom: var(--space-8);
    }
}

@media (max-width: 767px) {
    .legal-hero {
        padding: var(--space-12) var(--space-4);
    }

    .legal-hero-title {
        font-size: var(--text-2xl);
    }

    .legal-content {
        padding: var(--space-10) var(--space-4);
    }

    .legal-section h2 {
        font-size: var(--text-xl);
    }
}
</style>
@endsection

@section('content')
<!-- Hero Section -->
<section class="legal-hero">
    <div class="legal-hero-content">
        <h1 class="legal-hero-title">Privacy Policy</h1>
        <p class="legal-hero-meta">Last updated: {{ date('F d, Y') }}</p>
    </div>
</section>

<!-- Content Section -->
<section class="legal-content">
    <div class="container">
        <div class="legal-nav">
            <h4>Contents</h4>
            <ul>
                <li><a href="#introduction">Introduction</a></li>
                <li><a href="#information-collect">Information We Collect</a></li>
                <li><a href="#how-use">How We Use Your Information</a></li>
                <li><a href="#information-sharing">Information Sharing</a></li>
                <li><a href="#data-security">Data Security</a></li>
                <li><a href="#cookies">Cookies & Tracking</a></li>
                <li><a href="#your-rights">Your Rights</a></li>
                <li><a href="#data-retention">Data Retention</a></li>
                <li><a href="#children">Children's Privacy</a></li>
                <li><a href="#changes">Changes to Policy</a></li>
                <li><a href="#contact">Contact Us</a></li>
            </ul>
        </div>

        <div class="legal-body">
            <section id="introduction" class="legal-section">
                <h2>1. Introduction</h2>
                <p>
                    HECO ("HECO", "we", "us", or "our") is committed to protecting
                    your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard
                    your information when you visit our website and use our services.
                </p>
                <p>
                    By using HECO Portal, you agree to the collection and use of information in accordance
                    with this policy. If you do not agree with the terms of this privacy policy, please do
                    not access the site or use our services.
                </p>
            </section>

            <section id="information-collect" class="legal-section">
                <h2>2. Information We Collect</h2>

                <h3>2.1 Personal Information</h3>
                <p>We may collect personal information that you voluntarily provide to us when you:</p>
                <ul>
                    <li>Register for an account</li>
                    <li>Book an experience or trip</li>
                    <li>Subscribe to our newsletter</li>
                    <li>Contact us for support</li>
                    <li>Apply to become a service provider</li>
                </ul>
                <p>This information may include:</p>
                <ul>
                    <li>Name, email address, and phone number</li>
                    <li>Billing and shipping address</li>
                    <li>Payment information (processed securely through third-party providers)</li>
                    <li>Identity documents (for service provider verification)</li>
                    <li>Travel preferences and dietary requirements</li>
                    <li>Emergency contact information</li>
                </ul>

                <h3>2.2 Automatically Collected Information</h3>
                <p>When you access our platform, we automatically collect certain information, including:</p>
                <ul>
                    <li>Device information (type, operating system, browser)</li>
                    <li>IP address and approximate location</li>
                    <li>Pages visited and time spent on our platform</li>
                    <li>Referring website or source</li>
                    <li>Interactions with our services</li>
                </ul>
            </section>

            <section id="how-use" class="legal-section">
                <h2>3. How We Use Your Information</h2>
                <p>We use the information we collect for various purposes, including:</p>
                <ul>
                    <li><strong>Service Delivery:</strong> To process bookings, provide customer support, and deliver the experiences you've purchased</li>
                    <li><strong>Communication:</strong> To send booking confirmations, trip updates, and respond to your inquiries</li>
                    <li><strong>Personalization:</strong> To customize your experience and recommend relevant trips and experiences</li>
                    <li><strong>Safety:</strong> To share necessary information with service providers for your safety during trips</li>
                    <li><strong>Improvement:</strong> To analyze usage patterns and improve our platform and services</li>
                    <li><strong>Marketing:</strong> To send promotional communications (with your consent)</li>
                    <li><strong>Legal Compliance:</strong> To comply with applicable laws and regulations</li>
                </ul>
            </section>

            <section id="information-sharing" class="legal-section">
                <h2>4. Information Sharing</h2>
                <p>We may share your information with:</p>

                <h3>4.1 Service Providers</h3>
                <p>
                    When you book an experience, we share relevant information (name, contact details,
                    dietary requirements, etc.) with the local partners who will be hosting your experience.
                    This is necessary to deliver the services you've booked.
                </p>

                <h3>4.2 Third-Party Service Providers</h3>
                <p>
                    We work with trusted third parties who assist us in operating our platform, including:
                </p>
                <ul>
                    <li>Payment processors</li>
                    <li>Email service providers</li>
                    <li>Analytics services</li>
                    <li>Cloud hosting providers</li>
                </ul>
                <p>These providers are contractually obligated to protect your information.</p>

                <h3>4.3 Legal Requirements</h3>
                <p>
                    We may disclose your information when required by law, court order, or government request,
                    or when we believe disclosure is necessary to protect our rights, your safety, or the
                    safety of others.
                </p>
            </section>

            <section id="data-security" class="legal-section">
                <h2>5. Data Security</h2>
                <p>
                    We implement appropriate technical and organizational security measures to protect your
                    personal information, including:
                </p>
                <ul>
                    <li>Encryption of data in transit and at rest</li>
                    <li>Secure authentication mechanisms</li>
                    <li>Regular security assessments</li>
                    <li>Access controls and employee training</li>
                </ul>
                <p>
                    However, no method of transmission over the Internet is 100% secure. While we strive to
                    protect your information, we cannot guarantee absolute security.
                </p>
            </section>

            <section id="cookies" class="legal-section">
                <h2>6. Cookies and Tracking Technologies</h2>
                <p>
                    We use cookies and similar tracking technologies to enhance your experience on our platform.
                    These include:
                </p>
                <ul>
                    <li><strong>Essential Cookies:</strong> Required for the website to function properly</li>
                    <li><strong>Functional Cookies:</strong> Remember your preferences and settings</li>
                    <li><strong>Analytics Cookies:</strong> Help us understand how visitors use our site</li>
                    <li><strong>Marketing Cookies:</strong> Used to deliver relevant advertisements</li>
                </ul>
                <p>
                    You can control cookie preferences through your browser settings. Note that disabling
                    certain cookies may affect the functionality of our platform.
                </p>
            </section>

            <section id="your-rights" class="legal-section">
                <h2>7. Your Rights</h2>
                <p>Depending on your location, you may have the following rights regarding your personal data:</p>
                <ul>
                    <li><strong>Access:</strong> Request a copy of the personal data we hold about you</li>
                    <li><strong>Correction:</strong> Request correction of inaccurate or incomplete data</li>
                    <li><strong>Deletion:</strong> Request deletion of your personal data</li>
                    <li><strong>Portability:</strong> Request transfer of your data to another service</li>
                    <li><strong>Objection:</strong> Object to certain processing of your data</li>
                    <li><strong>Withdrawal:</strong> Withdraw consent for marketing communications</li>
                </ul>
                <p>
                    To exercise these rights, please contact us at
                    <a href="mailto:privacy@heco.eco">privacy@heco.eco</a>.
                </p>
            </section>

            <section id="data-retention" class="legal-section">
                <h2>8. Data Retention</h2>
                <p>
                    We retain your personal information for as long as necessary to fulfill the purposes
                    outlined in this policy, unless a longer retention period is required or permitted by law.
                    Generally:
                </p>
                <ul>
                    <li>Account information is retained while your account is active and for 3 years after closure</li>
                    <li>Booking records are retained for 7 years for legal and tax purposes</li>
                    <li>Marketing preferences are retained until you withdraw consent</li>
                </ul>
            </section>

            <section id="children" class="legal-section">
                <h2>9. Children's Privacy</h2>
                <p>
                    Our services are not intended for individuals under the age of 18. We do not knowingly
                    collect personal information from children. If you believe we have collected information
                    from a child, please contact us immediately and we will take steps to delete such information.
                </p>
            </section>

            <section id="changes" class="legal-section">
                <h2>10. Changes to This Policy</h2>
                <p>
                    We may update this Privacy Policy from time to time. We will notify you of any changes
                    by posting the new policy on this page and updating the "Last updated" date. We encourage
                    you to review this policy periodically for any changes.
                </p>
            </section>

            <section id="contact" class="legal-section">
                <h2>11. Contact Us</h2>
                <p>
                    If you have any questions about this Privacy Policy or our data practices, please contact us:
                </p>
                <div class="legal-contact-card">
                    <p><strong>HECO</strong></p>
                    <p>
                        <i class="bi bi-envelope"></i>
                        <a href="mailto:privacy@heco.eco">privacy@heco.eco</a>
                    </p>
                    <p>
                        <i class="bi bi-geo-alt"></i>
                        Himachal Pradesh, India
                    </p>
                </div>
            </section>
        </div>
    </div>
</section>
@endsection
