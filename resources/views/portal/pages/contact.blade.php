@extends('portal.layout')
@section('title', 'Contact Us - HECO Portal')

@section('css')
<style>
/* Page Hero */
.page-hero {
    background: linear-gradient(135deg, var(--heco-primary-800) 0%, var(--heco-primary-600) 100%);
    padding: var(--space-24) var(--space-6);
    text-align: center;
    color: white;
}

.page-hero-compact {
    padding: var(--space-16) var(--space-6);
}

.page-hero-content {
    max-width: 700px;
    margin: 0 auto;
}

.page-hero-label {
    display: inline-block;
    padding: var(--space-2) var(--space-4);
    background: rgba(255, 255, 255, 0.15);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: var(--font-medium);
    letter-spacing: var(--tracking-wider);
    text-transform: uppercase;
    margin-bottom: var(--space-5);
}

.page-hero-title {
    font-size: var(--text-4xl);
    font-weight: var(--font-bold);
    line-height: var(--leading-tight);
    margin: 0 0 var(--space-4);
}

.page-hero-subtitle {
    font-size: var(--text-lg);
    opacity: 0.9;
    line-height: var(--leading-relaxed);
    margin: 0;
}

/* Contact Section */
.contact-section {
    padding: var(--space-16) var(--space-6);
}

.contact-section .container {
    max-width: 1200px;
    margin: 0 auto;
}

.contact-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: var(--space-12);
}

/* Contact Form */
.contact-form-wrapper {
    background: white;
    padding: var(--space-8);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--color-border);
}

.contact-form-title {
    font-size: var(--text-2xl);
    font-weight: var(--font-bold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-2);
}

.contact-form-subtitle {
    font-size: var(--text-base);
    color: var(--color-text-muted);
    margin: 0 0 var(--space-6);
}

.contact-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-4);
}

.contact-form .form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right var(--space-4) center;
    padding-right: var(--space-10);
}

.contact-form .form-textarea {
    resize: vertical;
    min-height: 120px;
}

/* Contact Info */
.contact-info-wrapper {
    display: flex;
    flex-direction: column;
    gap: var(--space-8);
}

.contact-info-cards {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
}

.contact-info-card {
    display: flex;
    gap: var(--space-4);
    padding: var(--space-5);
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    transition: all var(--transition-base);
}

.contact-info-card:hover {
    transform: translateX(4px);
    box-shadow: var(--shadow-md);
    border-color: var(--heco-primary-200);
}

.contact-info-icon {
    width: 48px;
    height: 48px;
    background: var(--heco-primary-50);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--heco-primary-600);
    font-size: var(--text-xl);
    flex-shrink: 0;
}

.contact-info-content h4 {
    font-size: var(--text-base);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-1);
}

.contact-info-content p {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    margin: 0 0 var(--space-2);
}

.contact-info-content a {
    font-size: var(--text-sm);
    color: var(--color-primary);
    text-decoration: none;
    font-weight: var(--font-medium);
}

.contact-info-content a:hover {
    text-decoration: underline;
}

/* Contact Social */
.contact-social {
    padding: var(--space-6);
    background: var(--heco-neutral-50);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
}

.contact-social h4 {
    font-size: var(--text-sm);
    font-weight: var(--font-semibold);
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: var(--tracking-wider);
    margin: 0 0 var(--space-4);
}

.contact-social-links {
    display: flex;
    gap: var(--space-2);
}

.contact-social-links .social-link {
    width: 44px;
    height: 44px;
    background: white;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--heco-neutral-600);
    font-size: var(--text-lg);
    text-decoration: none;
    transition: all var(--transition-fast);
    border: 1px solid var(--color-border);
}

.contact-social-links .social-link:hover {
    background: var(--heco-primary-600);
    color: white;
    border-color: var(--heco-primary-600);
    transform: translateY(-2px);
}

/* FAQ Section */
.faq-section {
    padding: var(--space-16) var(--space-6);
    background: var(--heco-neutral-50);
}

.faq-section .container {
    max-width: 800px;
    margin: 0 auto;
}

.section-header {
    text-align: center;
    margin-bottom: var(--space-10);
}

.section-title {
    font-size: var(--text-3xl);
    font-weight: var(--font-bold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-3);
}

.section-subtitle {
    font-size: var(--text-base);
    color: var(--color-text-muted);
    margin: 0;
}

.section-subtitle a {
    color: var(--color-primary);
    text-decoration: none;
}

.section-subtitle a:hover {
    text-decoration: underline;
}

/* FAQ Accordion */
.faq-accordion {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

.faq-item {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    overflow: hidden;
}

.faq-question {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    padding: var(--space-5) var(--space-6);
    background: transparent;
    border: none;
    text-align: left;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.faq-question:hover {
    background: var(--heco-neutral-50);
}

.faq-question span {
    font-size: var(--text-base);
    font-weight: var(--font-medium);
    color: var(--heco-primary-900);
}

.faq-question i {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    transition: transform var(--transition-fast);
}

.faq-item.active .faq-question i {
    transform: rotate(180deg);
}

.faq-answer {
    display: none;
    padding: 0 var(--space-6) var(--space-5);
}

.faq-item.active .faq-answer {
    display: block;
}

.faq-answer p {
    font-size: var(--text-base);
    color: var(--color-text-muted);
    line-height: var(--leading-relaxed);
    margin: 0;
}

.faq-answer a {
    color: var(--color-primary);
    text-decoration: none;
}

.faq-answer a:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 991px) {
    .contact-grid {
        grid-template-columns: 1fr;
    }

    .contact-info-cards {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 767px) {
    .page-hero {
        padding: var(--space-12) var(--space-4);
    }

    .page-hero-title {
        font-size: var(--text-2xl);
    }

    .contact-section,
    .faq-section {
        padding: var(--space-10) var(--space-4);
    }

    .contact-form-wrapper {
        padding: var(--space-6);
    }

    .contact-form .form-row {
        grid-template-columns: 1fr;
    }

    .contact-info-cards {
        grid-template-columns: 1fr;
    }
}
</style>
@endsection

@section('content')
<!-- Hero Section -->
<section class="page-hero page-hero-compact">
    <div class="page-hero-content">
        <span class="page-hero-label">Get in Touch</span>
        <h1 class="page-hero-title">We'd Love to Hear From You</h1>
        <p class="page-hero-subtitle">
            Have questions about your upcoming trip or want to learn more about partnering with us?
            Our team is here to help.
        </p>
    </div>
</section>

<!-- Contact Section -->
<section class="contact-section">
    <div class="container">
        <div class="contact-grid">
            <!-- Contact Form -->
            <div class="contact-form-wrapper">
                <h2 class="contact-form-title">Send us a Message</h2>
                <p class="contact-form-subtitle">
                    Fill out the form below and we'll get back to you within 24-48 hours.
                </p>

                <form id="contactForm" class="contact-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-input" name="first_name" placeholder="John" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-input" name="last_name" placeholder="Doe" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <div class="input-with-icon">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" class="form-input" name="email" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <div class="input-with-icon">
                            <i class="bi bi-phone input-icon"></i>
                            <input type="tel" class="form-input" name="phone" placeholder="+91 98765 43210">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Subject *</label>
                        <select class="form-input form-select" name="subject" required>
                            <option value="">Select a topic</option>
                            <option value="booking">Booking Inquiry</option>
                            <option value="experience">Experience Information</option>
                            <option value="partnership">Partnership Opportunity</option>
                            <option value="support">Customer Support</option>
                            <option value="feedback">Feedback & Suggestions</option>
                            <option value="media">Media & Press</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Message *</label>
                        <textarea class="form-input form-textarea" name="message" rows="5"
                            placeholder="Tell us how we can help you..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100" id="btnSubmitContact">
                        <i class="bi bi-send"></i>
                        Send Message
                    </button>
                </form>
            </div>

            <!-- Contact Info -->
            <div class="contact-info-wrapper">
                <div class="contact-info-cards">
                    <div class="contact-info-card">
                        <div class="contact-info-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="contact-info-content">
                            <h4>Email Us</h4>
                            <p>For general inquiries</p>
                            <a href="mailto:info@himalayanecotourism.com">info@himalayanecotourism.com</a>
                        </div>
                    </div>

                    <div class="contact-info-card">
                        <div class="contact-info-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div class="contact-info-content">
                            <h4>Call Us</h4>
                            <p>Mon-Sat, 9 AM - 6 PM IST</p>
                            <a href="tel:+911234567890">+91 123 456 7890</a>
                        </div>
                    </div>

                    <div class="contact-info-card">
                        <div class="contact-info-icon">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div class="contact-info-content">
                            <h4>Visit Us</h4>
                            <p>HECO Collective Office</p>
                            <span>Shimla, Himachal Pradesh, India</span>
                        </div>
                    </div>

                    <div class="contact-info-card">
                        <div class="contact-info-icon">
                            <i class="bi bi-chat-dots"></i>
                        </div>
                        <div class="contact-info-content">
                            <h4>Live Chat</h4>
                            <p>Quick answers to your questions</p>
                            <button type="button" class="btn btn-secondary btn-sm" id="openChat">
                                Start Chat
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Social Links -->
                <div class="contact-social">
                    <h4>Follow Us</h4>
                    <div class="contact-social-links">
                        <a href="#" class="social-link" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-link" title="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="social-link" title="Twitter"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" class="social-link" title="YouTube"><i class="bi bi-youtube"></i></a>
                        <a href="#" class="social-link" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">
                Quick answers to common questions. Can't find what you're looking for?
                <a href="/help">Visit our Help Center</a>
            </p>
        </div>

        <div class="faq-accordion">
            <div class="faq-item">
                <button class="faq-question" type="button">
                    <span>How do I book an experience?</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>
                        Booking an experience is easy! Browse our curated experiences, select your preferred
                        dates and group size, and complete the checkout process. You'll receive a confirmation
                        email with all the details. Our team will also connect you with your local host before
                        your trip.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" type="button">
                    <span>What is your cancellation policy?</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>
                        Cancellation policies vary by experience and are displayed on each listing page.
                        Generally, we offer flexible, moderate, and strict cancellation options. For full
                        details, please check the specific experience page or visit our
                        <a href="/terms">Terms of Service</a>.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" type="button">
                    <span>How do I become a service provider partner?</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>
                        We welcome local hosts, rural partners, and service providers who share our commitment
                        to regenerative tourism. Visit our <a href="/join">Partner Application</a> page to learn
                        about our requirements and submit your application. Our team reviews all applications
                        within 7-10 business days.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" type="button">
                    <span>Is travel insurance required?</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>
                        While not mandatory for all experiences, we strongly recommend comprehensive travel
                        insurance that covers medical emergencies, trip cancellation, and evacuation for
                        adventure and remote experiences. Many of our experiences involve outdoor activities
                        in mountainous terrain.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" type="button">
                    <span>What payment methods do you accept?</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>
                        We accept all major credit and debit cards (Visa, MasterCard, American Express),
                        UPI payments, net banking, and select digital wallets. All payments are processed
                        securely through our payment partners.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
(function() {
    // FAQ Accordion
    document.querySelectorAll('.faq-question').forEach(function(button) {
        button.addEventListener('click', function() {
            var item = this.closest('.faq-item');
            var isActive = item.classList.contains('active');

            // Close all items
            document.querySelectorAll('.faq-item').forEach(function(i) {
                i.classList.remove('active');
            });

            // Open clicked item if it wasn't active
            if (!isActive) {
                item.classList.add('active');
            }
        });
    });

    // Contact Form Submission
    var contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();

            var btn = document.getElementById('btnSubmitContact');
            var originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';

            var formData = new FormData(contactForm);
            formData.append('contact_form', 1);

            ajaxPost(Object.fromEntries(formData), function(resp) {
                btn.disabled = false;
                btn.innerHTML = originalText;
                if (resp.success) {
                    showAlert('Message sent successfully! We\'ll get back to you soon.', 'success');
                    contactForm.reset();
                }
            }, function() {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showAlert('Failed to send message. Please try again.', 'danger');
            });
        });
    }
})();
</script>
@endsection
