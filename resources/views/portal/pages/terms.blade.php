@extends('portal.layout')
@section('title', 'Terms of Service - HECO Portal')

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

.legal-section ul, .legal-section ol {
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
        <h1 class="legal-hero-title">Terms of Service</h1>
        <p class="legal-hero-meta">Last updated: {{ date('F d, Y') }}</p>
    </div>
</section>

<!-- Content Section -->
<section class="legal-content">
    <div class="container">
        <div class="legal-nav">
            <h4>Contents</h4>
            <ul>
                <li><a href="#acceptance">Acceptance of Terms</a></li>
                <li><a href="#eligibility">Eligibility</a></li>
                <li><a href="#accounts">User Accounts</a></li>
                <li><a href="#services">Our Services</a></li>
                <li><a href="#bookings">Bookings & Payments</a></li>
                <li><a href="#cancellations">Cancellations & Refunds</a></li>
                <li><a href="#traveler-responsibilities">Traveler Responsibilities</a></li>
                <li><a href="#provider-terms">Service Provider Terms</a></li>
                <li><a href="#intellectual-property">Intellectual Property</a></li>
                <li><a href="#disclaimers">Disclaimers</a></li>
                <li><a href="#liability">Limitation of Liability</a></li>
                <li><a href="#indemnification">Indemnification</a></li>
                <li><a href="#governing-law">Governing Law</a></li>
                <li><a href="#changes">Changes to Terms</a></li>
                <li><a href="#contact">Contact Information</a></li>
            </ul>
        </div>

        <div class="legal-body">
            <section id="acceptance" class="legal-section">
                <h2>1. Acceptance of Terms</h2>
                <p>
                    Welcome to HECO Portal. These Terms of Service ("Terms") govern your access to and use of
                    the HECO Portal website, mobile applications, and services (collectively, the "Platform")
                    operated by HECO ("HECO", "we", "us", or "our").
                </p>
                <p>
                    By accessing or using our Platform, you agree to be bound by these Terms. If you disagree
                    with any part of the Terms, you may not access the Platform or use our services.
                </p>
            </section>

            <section id="eligibility" class="legal-section">
                <h2>2. Eligibility</h2>
                <p>
                    To use our Platform, you must:
                </p>
                <ul>
                    <li>Be at least 18 years of age</li>
                    <li>Have the legal capacity to enter into binding contracts</li>
                    <li>Not be prohibited from using our services under applicable law</li>
                    <li>Provide accurate and complete information during registration</li>
                </ul>
                <p>
                    Travelers under 18 may participate in experiences only when accompanied by a parent or
                    legal guardian who has agreed to these Terms.
                </p>
            </section>

            <section id="accounts" class="legal-section">
                <h2>3. User Accounts</h2>

                <h3>3.1 Account Creation</h3>
                <p>
                    To access certain features of our Platform, you must create an account. You are responsible
                    for maintaining the confidentiality of your account credentials and for all activities that
                    occur under your account.
                </p>

                <h3>3.2 Account Security</h3>
                <p>
                    You agree to:
                </p>
                <ul>
                    <li>Provide accurate, current, and complete information</li>
                    <li>Keep your login credentials secure and confidential</li>
                    <li>Notify us immediately of any unauthorized access</li>
                    <li>Not share your account with others</li>
                </ul>

                <h3>3.3 Account Termination</h3>
                <p>
                    We reserve the right to suspend or terminate your account at our discretion if you violate
                    these Terms, engage in fraudulent activity, or behave in a manner that may harm HECO, our
                    partners, or other users.
                </p>
            </section>

            <section id="services" class="legal-section">
                <h2>4. Our Services</h2>
                <p>
                    HECO Portal is an online marketplace that connects travelers with local service providers
                    across our partner regions worldwide. We provide:
                </p>
                <ul>
                    <li>A platform to discover and book eco-tourism experiences</li>
                    <li>Communication tools between travelers and service providers</li>
                    <li>Payment processing services</li>
                    <li>Customer support for booking-related queries</li>
                </ul>
                <p>
                    <strong>Important:</strong> HECO acts as an intermediary platform. We do not own, operate,
                    manage, or control any of the experiences or accommodations listed on our Platform. The
                    actual services are provided by independent local service providers.
                </p>
            </section>

            <section id="bookings" class="legal-section">
                <h2>5. Bookings and Payments</h2>

                <h3>5.1 Booking Process</h3>
                <p>
                    When you book an experience through our Platform:
                </p>
                <ul>
                    <li>You enter into a direct agreement with the service provider</li>
                    <li>You agree to the specific terms and conditions of that experience</li>
                    <li>Your booking is subject to availability and provider confirmation</li>
                </ul>

                <h3>5.2 Pricing</h3>
                <p>
                    All prices displayed on our Platform are in Indian Rupees (INR) unless otherwise stated.
                    Prices include applicable taxes as specified on the booking page. Additional costs may
                    apply for optional services or extras.
                </p>

                <h3>5.3 Payment</h3>
                <p>
                    Payment must be made at the time of booking unless otherwise specified. We accept various
                    payment methods as displayed during checkout. All payments are processed securely through
                    third-party payment providers.
                </p>

                <h3>5.4 Payment Distribution</h3>
                <p>
                    Payments are distributed to service providers after successful completion of experiences,
                    minus HECO's service fee. A portion of each booking supports our Regenerative Projects Fund.
                </p>
            </section>

            <section id="cancellations" class="legal-section">
                <h2>6. Cancellations and Refunds</h2>

                <h3>6.1 Traveler Cancellations</h3>
                <p>
                    Cancellation policies vary by experience and are clearly displayed on each listing.
                    Generally:
                </p>
                <ul>
                    <li><strong>Flexible:</strong> Full refund if cancelled 7+ days before start date</li>
                    <li><strong>Moderate:</strong> Full refund if cancelled 14+ days before start date</li>
                    <li><strong>Strict:</strong> 50% refund if cancelled 30+ days before start date</li>
                </ul>

                <h3>6.2 Provider Cancellations</h3>
                <p>
                    If a service provider cancels your booking, you will receive a full refund. We will also
                    assist in finding alternative experiences when possible.
                </p>

                <h3>6.3 Force Majeure</h3>
                <p>
                    In cases of natural disasters, government restrictions, or other circumstances beyond
                    reasonable control, special cancellation terms may apply. We will work with you and
                    service providers to find fair solutions.
                </p>

                <h3>6.4 Refund Processing</h3>
                <p>
                    Approved refunds are typically processed within 7-14 business days. The refund will be
                    credited to the original payment method.
                </p>
            </section>

            <section id="traveler-responsibilities" class="legal-section">
                <h2>7. Traveler Responsibilities</h2>
                <p>
                    As a traveler using our Platform, you agree to:
                </p>
                <ul>
                    <li>Provide accurate personal and travel information</li>
                    <li>Obtain necessary travel documents (visas, permits, vaccinations)</li>
                    <li>Have appropriate travel and health insurance</li>
                    <li>Follow safety instructions provided by service providers</li>
                    <li>Respect local communities, cultures, and environments</li>
                    <li>Comply with our Environmental Code of Conduct</li>
                    <li>Not engage in illegal activities during experiences</li>
                    <li>Report any safety concerns or incidents promptly</li>
                </ul>
            </section>

            <section id="provider-terms" class="legal-section">
                <h2>8. Service Provider Terms</h2>
                <p>
                    Service providers listing on our Platform must:
                </p>
                <ul>
                    <li>Complete our verification and vetting process</li>
                    <li>Maintain valid licenses and permits for their services</li>
                    <li>Provide accurate descriptions of experiences</li>
                    <li>Adhere to our Regenerative Tourism Standards</li>
                    <li>Maintain appropriate insurance coverage</li>
                    <li>Deliver services as described in their listings</li>
                    <li>Respond to booking requests promptly</li>
                </ul>
                <p>
                    Detailed service provider terms are outlined in our Service Provider Agreement.
                </p>
            </section>

            <section id="intellectual-property" class="legal-section">
                <h2>9. Intellectual Property</h2>

                <h3>9.1 HECO Content</h3>
                <p>
                    All content on our Platform, including logos, designs, text, graphics, and software, is
                    owned by HECO or our licensors and is protected by intellectual property laws. You may
                    not copy, modify, distribute, or create derivative works without our written permission.
                </p>

                <h3>9.2 User Content</h3>
                <p>
                    By posting reviews, photos, or other content on our Platform, you grant HECO a
                    non-exclusive, worldwide, royalty-free license to use, display, and distribute such
                    content for marketing and promotional purposes.
                </p>
            </section>

            <section id="disclaimers" class="legal-section">
                <h2>10. Disclaimers</h2>
                <p>
                    <strong>THE PLATFORM AND SERVICES ARE PROVIDED "AS IS" WITHOUT WARRANTIES OF ANY KIND.</strong>
                </p>
                <p>
                    We do not warrant that:
                </p>
                <ul>
                    <li>The Platform will be uninterrupted, error-free, or secure</li>
                    <li>Any experience will meet your expectations</li>
                    <li>Information provided by service providers is accurate</li>
                    <li>Results from using the Platform will be satisfactory</li>
                </ul>
                <p>
                    Travel inherently involves risks. You acknowledge that outdoor activities, adventure
                    sports, and travel in remote areas carry inherent dangers including injury, illness,
                    or death.
                </p>
            </section>

            <section id="liability" class="legal-section">
                <h2>11. Limitation of Liability</h2>
                <p>
                    TO THE MAXIMUM EXTENT PERMITTED BY LAW, HECO SHALL NOT BE LIABLE FOR:
                </p>
                <ul>
                    <li>Any indirect, incidental, special, or consequential damages</li>
                    <li>Loss of profits, data, or business opportunities</li>
                    <li>Personal injury or property damage during experiences</li>
                    <li>Actions or omissions of service providers</li>
                    <li>Service interruptions or Platform unavailability</li>
                </ul>
                <p>
                    Our total liability for any claim shall not exceed the amount you paid to HECO for the
                    specific booking giving rise to the claim.
                </p>
            </section>

            <section id="indemnification" class="legal-section">
                <h2>12. Indemnification</h2>
                <p>
                    You agree to indemnify, defend, and hold harmless HECO, its officers, directors, employees,
                    and partners from any claims, damages, losses, or expenses (including legal fees) arising
                    from:
                </p>
                <ul>
                    <li>Your violation of these Terms</li>
                    <li>Your use of the Platform or services</li>
                    <li>Your violation of any third-party rights</li>
                    <li>Content you submit to the Platform</li>
                </ul>
            </section>

            <section id="governing-law" class="legal-section">
                <h2>13. Governing Law and Dispute Resolution</h2>
                <p>
                    These Terms shall be governed by the laws of India. Any disputes arising from these Terms
                    or your use of the Platform shall be resolved through:
                </p>
                <ol>
                    <li><strong>Negotiation:</strong> Direct discussion between parties in good faith</li>
                    <li><strong>Mediation:</strong> If negotiation fails, through a mutually agreed mediator</li>
                    <li><strong>Arbitration:</strong> Binding arbitration in Shimla, Himachal Pradesh under
                    the Arbitration and Conciliation Act, 1996</li>
                </ol>
            </section>

            <section id="changes" class="legal-section">
                <h2>14. Changes to Terms</h2>
                <p>
                    We reserve the right to modify these Terms at any time. We will provide notice of material
                    changes by posting on the Platform and/or sending you an email. Your continued use of the
                    Platform after changes constitutes acceptance of the modified Terms.
                </p>
            </section>

            <section id="contact" class="legal-section">
                <h2>15. Contact Information</h2>
                <p>
                    For questions about these Terms, please contact us:
                </p>
                <div class="legal-contact-card">
                    <p><strong>HECO</strong></p>
                    <p>
                        <i class="bi bi-envelope"></i>
                        <a href="mailto:legal@himalayanecotourism.com">legal@himalayanecotourism.com</a>
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
