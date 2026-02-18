@extends('portal.layout')
@section('title', 'Help Center - HECO Portal')

@section('css')
<style>
/* Help Hero */
.help-hero {
    background: linear-gradient(135deg, var(--heco-primary-800) 0%, var(--heco-primary-600) 100%);
    padding: var(--space-20) var(--space-6);
    text-align: center;
    color: white;
}

.help-hero-content {
    max-width: 600px;
    margin: 0 auto;
}

.help-hero-title {
    font-size: var(--text-4xl);
    font-weight: var(--font-bold);
    margin: 0 0 var(--space-3);
}

.help-hero-subtitle {
    font-size: var(--text-lg);
    opacity: 0.9;
    margin: 0 0 var(--space-8);
}

/* Help Search */
.help-search {
    max-width: 500px;
    margin: 0 auto;
}

.help-search-wrapper {
    position: relative;
}

.help-search-wrapper i {
    position: absolute;
    left: var(--space-5);
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-light);
    font-size: var(--text-lg);
}

.help-search-input {
    width: 100%;
    padding: var(--space-5) var(--space-5) var(--space-5) var(--space-12);
    font-size: var(--text-lg);
    border: none;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-xl);
}

.help-search-input:focus {
    outline: none;
    box-shadow: var(--shadow-xl), 0 0 0 3px rgba(255, 255, 255, 0.3);
}

/* Categories Section */
.help-categories {
    padding: var(--space-16) var(--space-6);
    background: white;
}

.help-categories .container {
    max-width: 1200px;
    margin: 0 auto;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-6);
}

.category-card {
    display: block;
    padding: var(--space-6);
    background: white;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    text-decoration: none;
    text-align: center;
    transition: all var(--transition-base);
}

.category-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--heco-primary-200);
}

.category-icon {
    width: 64px;
    height: 64px;
    background: var(--heco-primary-50);
    border-radius: var(--radius-xl);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--space-4);
    color: var(--heco-primary-600);
    font-size: var(--text-2xl);
}

.category-title {
    font-size: var(--text-lg);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-2);
}

.category-desc {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    margin: 0;
}

/* FAQ Section */
.help-faq {
    padding: var(--space-16) var(--space-6);
    background: var(--heco-neutral-50);
}

.help-faq .container {
    max-width: 900px;
    margin: 0 auto;
}

.faq-category {
    margin-bottom: var(--space-12);
    scroll-margin-top: 100px;
}

.faq-category-title {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    font-size: var(--text-xl);
    font-weight: var(--font-bold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-5);
}

.faq-category-title i {
    color: var(--heco-primary-600);
}

.faq-list {
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
    flex-shrink: 0;
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
    margin: 0 0 var(--space-4);
}

.faq-answer p:last-child {
    margin-bottom: 0;
}

.faq-answer ul, .faq-answer ol {
    margin: 0 0 var(--space-4);
    padding-left: var(--space-6);
}

.faq-answer li {
    font-size: var(--text-base);
    color: var(--color-text-muted);
    line-height: var(--leading-relaxed);
    margin-bottom: var(--space-2);
}

.faq-answer a {
    color: var(--color-primary);
    text-decoration: none;
}

.faq-answer a:hover {
    text-decoration: underline;
}

/* Help CTA */
.help-cta {
    text-align: center;
    padding: var(--space-12);
    background: white;
    border-radius: var(--radius-xl);
    border: 1px solid var(--color-border);
    margin-top: var(--space-8);
}

.help-cta h2 {
    font-size: var(--text-2xl);
    font-weight: var(--font-bold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-2);
}

.help-cta p {
    font-size: var(--text-base);
    color: var(--color-text-muted);
    margin: 0 0 var(--space-6);
}

.help-cta-buttons {
    display: flex;
    justify-content: center;
    gap: var(--space-4);
}

/* Responsive */
@media (max-width: 991px) {
    .categories-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 767px) {
    .help-hero {
        padding: var(--space-12) var(--space-4);
    }

    .help-hero-title {
        font-size: var(--text-2xl);
    }

    .help-categories,
    .help-faq {
        padding: var(--space-10) var(--space-4);
    }

    .categories-grid {
        grid-template-columns: 1fr;
    }

    .help-cta-buttons {
        flex-direction: column;
    }

    .help-cta-buttons .btn {
        width: 100%;
    }
}
</style>
@endsection

@section('content')
<!-- Hero Section -->
<section class="help-hero">
    <div class="help-hero-content">
        <h1 class="help-hero-title">How can we help you?</h1>
        <p class="help-hero-subtitle">
            Search our knowledge base or browse categories below
        </p>
        <div class="help-search">
            <div class="help-search-wrapper">
                <i class="bi bi-search"></i>
                <input type="text" class="help-search-input" id="helpSearch"
                    placeholder="Search for answers...">
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="help-categories">
    <div class="container">
        <div class="categories-grid">
            <a href="#booking" class="category-card">
                <div class="category-icon">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <h3 class="category-title">Booking & Reservations</h3>
                <p class="category-desc">How to book, modify, or cancel your experiences</p>
            </a>

            <a href="#payments" class="category-card">
                <div class="category-icon">
                    <i class="bi bi-credit-card"></i>
                </div>
                <h3 class="category-title">Payments & Refunds</h3>
                <p class="category-desc">Payment methods, invoices, and refund policies</p>
            </a>

            <a href="#account" class="category-card">
                <div class="category-icon">
                    <i class="bi bi-person-circle"></i>
                </div>
                <h3 class="category-title">Account & Profile</h3>
                <p class="category-desc">Managing your account settings and preferences</p>
            </a>

            <a href="#travel" class="category-card">
                <div class="category-icon">
                    <i class="bi bi-backpack2"></i>
                </div>
                <h3 class="category-title">Travel Preparation</h3>
                <p class="category-desc">What to pack, permits, and getting ready</p>
            </a>

            <a href="#safety" class="category-card">
                <div class="category-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h3 class="category-title">Safety & Insurance</h3>
                <p class="category-desc">Travel safety tips and insurance requirements</p>
            </a>

            <a href="#providers" class="category-card">
                <div class="category-icon">
                    <i class="bi bi-people"></i>
                </div>
                <h3 class="category-title">For Service Providers</h3>
                <p class="category-desc">Information for hosts and partners</p>
            </a>
        </div>
    </div>
</section>

<!-- FAQ Sections -->
<section class="help-faq">
    <div class="container">
        <!-- Booking FAQs -->
        <div id="booking" class="faq-category">
            <h2 class="faq-category-title">
                <i class="bi bi-calendar-check"></i>
                Booking & Reservations
            </h2>

            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>How do I book an experience?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            Booking an experience on HECO Portal is straightforward:
                        </p>
                        <ol>
                            <li>Browse experiences on our <a href="/home">Explore page</a></li>
                            <li>Select your preferred experience and check availability</li>
                            <li>Choose your dates and group size</li>
                            <li>Create an account or log in if you haven't already</li>
                            <li>Complete the payment process</li>
                            <li>Receive confirmation via email</li>
                        </ol>
                        <p>
                            After booking, our team will connect you with your local host to finalize
                            details and answer any questions.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>Can I modify my booking after confirmation?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            Yes, you can request modifications to your booking depending on availability.
                            Common modifications include:
                        </p>
                        <ul>
                            <li>Changing dates (subject to availability)</li>
                            <li>Adjusting group size</li>
                            <li>Adding optional activities</li>
                            <li>Special requests (dietary, accessibility, etc.)</li>
                        </ul>
                        <p>
                            To modify your booking, go to "My Itineraries" in your account or contact us
                            at <a href="mailto:bookings@himalayanecotourism.com">bookings@himalayanecotourism.com</a>.
                            Please note that date changes may result in price adjustments.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>How far in advance should I book?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            We recommend booking at least 2-4 weeks in advance for most experiences.
                            For peak season (April-June, September-November) and popular experiences,
                            booking 1-2 months ahead is advisable.
                        </p>
                        <p>
                            Multi-day treks and special experiences may require even earlier booking
                            due to permit requirements and limited group sizes.
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
                            Cancellation policies vary by experience type:
                        </p>
                        <ul>
                            <li><strong>Flexible:</strong> Full refund if cancelled 7+ days before start date</li>
                            <li><strong>Moderate:</strong> Full refund if cancelled 14+ days before start date;
                            50% refund for 7-14 days</li>
                            <li><strong>Strict:</strong> 50% refund if cancelled 30+ days before start date;
                            no refund within 30 days</li>
                        </ul>
                        <p>
                            The specific policy is clearly displayed on each experience listing.
                            Weather-related cancellations and force majeure situations are handled case by case.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payments FAQs -->
        <div id="payments" class="faq-category">
            <h2 class="faq-category-title">
                <i class="bi bi-credit-card"></i>
                Payments & Refunds
            </h2>

            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>What payment methods do you accept?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            We accept multiple payment methods for your convenience:
                        </p>
                        <ul>
                            <li>Credit/Debit Cards (Visa, MasterCard, American Express, RuPay)</li>
                            <li>UPI (Google Pay, PhonePe, Paytm, etc.)</li>
                            <li>Net Banking (all major Indian banks)</li>
                            <li>Digital Wallets (Paytm, Amazon Pay)</li>
                        </ul>
                        <p>
                            All payments are processed securely through our trusted payment partners
                            with industry-standard encryption.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>How long do refunds take to process?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            Once a refund is approved:
                        </p>
                        <ul>
                            <li>Credit/Debit Cards: 5-7 business days</li>
                            <li>UPI: 2-3 business days</li>
                            <li>Net Banking: 5-7 business days</li>
                            <li>Digital Wallets: 1-3 business days</li>
                        </ul>
                        <p>
                            Refunds are credited to the original payment method. If you don't see your
                            refund after the expected timeframe, please contact your bank or our support team.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>Can I pay in installments?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            For experiences above a certain value, we offer payment in two installments:
                        </p>
                        <ul>
                            <li>50% at the time of booking</li>
                            <li>50% at least 14 days before the experience start date</li>
                        </ul>
                        <p>
                            This option is available during checkout for eligible bookings. Please note
                            that the full amount must be paid before the experience begins.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account FAQs -->
        <div id="account" class="faq-category">
            <h2 class="faq-category-title">
                <i class="bi bi-person-circle"></i>
                Account & Profile
            </h2>

            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>How do I create an account?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            Creating an account is quick and easy:
                        </p>
                        <ol>
                            <li>Click "Get Started" on the top navigation</li>
                            <li>Fill in your name, email, and create a password</li>
                            <li>Alternatively, sign up using Google, Facebook, or Apple</li>
                            <li>Verify your email address</li>
                        </ol>
                        <p>
                            Once registered, you can browse experiences, save favorites, book trips,
                            and manage your profile.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>I forgot my password. How do I reset it?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            To reset your password:
                        </p>
                        <ol>
                            <li>Click "Login" and then "Forgot password?"</li>
                            <li>Enter your registered email address</li>
                            <li>Check your inbox for a password reset link</li>
                            <li>Click the link and create a new password</li>
                        </ol>
                        <p>
                            The reset link expires after 24 hours. If you don't receive the email,
                            check your spam folder or <a href="/contact">contact support</a>.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>How do I delete my account?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            We're sorry to see you go! To delete your account:
                        </p>
                        <ol>
                            <li>Log in to your account</li>
                            <li>Go to Profile Settings</li>
                            <li>Scroll to "Delete Account"</li>
                            <li>Confirm your decision</li>
                        </ol>
                        <p>
                            Please note that account deletion is permanent and you'll lose access to
                            your booking history. Active bookings must be completed or cancelled before
                            account deletion.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Travel Preparation FAQs -->
        <div id="travel" class="faq-category">
            <h2 class="faq-category-title">
                <i class="bi bi-backpack2"></i>
                Travel Preparation
            </h2>

            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>What should I pack for my trip?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            Packing requirements depend on your specific experience. Generally, we recommend:
                        </p>
                        <ul>
                            <li>Layered clothing suitable for mountain weather</li>
                            <li>Sturdy, comfortable walking/hiking shoes</li>
                            <li>Rain jacket or poncho</li>
                            <li>Sun protection (hat, sunglasses, sunscreen)</li>
                            <li>Personal medications and first aid basics</li>
                            <li>Reusable water bottle</li>
                            <li>Camera and extra batteries</li>
                        </ul>
                        <p>
                            A detailed packing list specific to your experience will be sent after booking.
                            Check our <a href="/guidelines">Travel Guidelines</a> for more information.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>Do I need any permits for my trip?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            Permit requirements vary by destination:
                        </p>
                        <ul>
                            <li><strong>Inner Line Permits:</strong> Required for certain areas in Himachal Pradesh,
                            Uttarakhand, and Northeast India</li>
                            <li><strong>Protected Area Permits:</strong> Required for wildlife sanctuaries and
                            national parks</li>
                            <li><strong>Trekking Permits:</strong> Required for certain high-altitude treks</li>
                        </ul>
                        <p>
                            Don't worry - we handle permit arrangements for all our experiences. You'll receive
                            specific permit information and requirements after booking.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Safety FAQs -->
        <div id="safety" class="faq-category">
            <h2 class="faq-category-title">
                <i class="bi bi-shield-check"></i>
                Safety & Insurance
            </h2>

            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>Is travel insurance required?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            While not mandatory for all experiences, we strongly recommend comprehensive
                            travel insurance that covers:
                        </p>
                        <ul>
                            <li>Medical emergencies and evacuation</li>
                            <li>Trip cancellation and interruption</li>
                            <li>Baggage loss or delay</li>
                            <li>Adventure activities (if applicable)</li>
                        </ul>
                        <p>
                            For high-altitude treks and adventure experiences, insurance with emergency
                            helicopter evacuation coverage is required. We can recommend trusted insurance
                            providers upon request.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>What safety measures are in place?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            Your safety is our priority. Our safety measures include:
                        </p>
                        <ul>
                            <li>Verified and vetted service providers</li>
                            <li>Trained local guides with first aid certification</li>
                            <li>Emergency communication equipment on remote trips</li>
                            <li>Acclimatization protocols for high-altitude experiences</li>
                            <li>24/7 emergency contact support</li>
                            <li>Weather monitoring and trip modifications when necessary</li>
                        </ul>
                        <p>
                            Detailed safety briefings are provided before each experience begins.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- For Providers FAQs -->
        <div id="providers" class="faq-category">
            <h2 class="faq-category-title">
                <i class="bi bi-people"></i>
                For Service Providers
            </h2>

            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>How do I become a HECO partner?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            We welcome local hosts and service providers who share our vision. To become a partner:
                        </p>
                        <ol>
                            <li>Visit our <a href="/join">Partner Application</a> page</li>
                            <li>Complete the application form with your details and experience offerings</li>
                            <li>Submit required documents (licenses, certifications, photos)</li>
                            <li>Our team will review your application within 7-10 business days</li>
                            <li>If approved, you'll receive onboarding information and training</li>
                        </ol>
                        <p>
                            We prioritize partners who demonstrate commitment to sustainable practices,
                            community benefit, and authentic experiences.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>What are the requirements to list experiences?</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>
                            To list experiences on HECO Portal, you need:
                        </p>
                        <ul>
                            <li>Valid business registration and relevant licenses</li>
                            <li>Appropriate insurance coverage</li>
                            <li>First aid trained staff (for adventure experiences)</li>
                            <li>Commitment to our Regenerative Tourism Standards</li>
                            <li>Clean record with no unresolved complaints</li>
                        </ul>
                        <p>
                            Additional requirements may apply based on the type of experience you offer.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Still Need Help -->
        <div class="help-cta">
            <h2>Still have questions?</h2>
            <p>Our support team is here to help you with any questions or concerns.</p>
            <div class="help-cta-buttons">
                <a href="/contact" class="btn btn-primary btn-lg">
                    <i class="bi bi-envelope"></i>
                    Contact Support
                </a>
                <a href="tel:+911234567890" class="btn btn-secondary btn-lg">
                    <i class="bi bi-telephone"></i>
                    Call Us
                </a>
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

            // Close all items in same category
            var category = item.closest('.faq-category');
            category.querySelectorAll('.faq-item').forEach(function(i) {
                i.classList.remove('active');
            });

            // Open clicked item if it wasn't active
            if (!isActive) {
                item.classList.add('active');
            }
        });
    });

    // Search functionality
    var searchInput = document.getElementById('helpSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();

            document.querySelectorAll('.faq-item').forEach(function(item) {
                var text = item.textContent.toLowerCase();
                if (query === '' || text.includes(query)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });

            // Show categories that have visible items
            document.querySelectorAll('.faq-category').forEach(function(category) {
                var visibleItems = category.querySelectorAll('.faq-item[style="display: block;"], .faq-item:not([style])');
                var hiddenCount = category.querySelectorAll('.faq-item[style="display: none;"]').length;
                var totalItems = category.querySelectorAll('.faq-item').length;

                if (hiddenCount === totalItems) {
                    category.style.display = 'none';
                } else {
                    category.style.display = 'block';
                }
            });
        });
    }

    // Smooth scroll for category links
    document.querySelectorAll('.category-card').forEach(function(card) {
        card.addEventListener('click', function(e) {
            var href = this.getAttribute('href');
            if (href.startsWith('#')) {
                e.preventDefault();
                var target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });
})();
</script>
@endsection
