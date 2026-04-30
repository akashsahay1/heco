@extends('portal.layout')
@section('title', 'Contact Us - HECO Portal')

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
                        <input type="email" class="form-input" name="email" placeholder="you@example.com" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-input" name="phone" placeholder="+91 98765 43210">
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

                    <button type="submit" class="btn btn-success btn-lg w-100" id="btnSubmitContact">
                        <i class="bi bi-send me-2"></i>
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
