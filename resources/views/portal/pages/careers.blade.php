@extends('portal.layout')
@section('title', 'Careers - HECO Portal')

@section('css')
<style>
/* Careers Hero */
.careers-hero {
    background: linear-gradient(135deg, var(--heco-primary-900) 0%, var(--heco-primary-700) 100%);
    padding: var(--space-24) var(--space-6);
    text-align: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.careers-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpolygon fill='%23ffffff08' points='0,100 50,20 100,100'/%3E%3C/svg%3E") center/cover no-repeat;
    opacity: 0.5;
}

.careers-hero-content {
    position: relative;
    max-width: 700px;
    margin: 0 auto;
}

.careers-hero-label {
    display: inline-block;
    padding: var(--space-2) var(--space-4);
    background: rgba(255, 255, 255, 0.15);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: var(--font-medium);
    letter-spacing: var(--tracking-wider);
    text-transform: uppercase;
    margin-bottom: var(--space-6);
}

.careers-hero-title {
    font-size: var(--text-4xl);
    font-weight: var(--font-bold);
    line-height: var(--leading-tight);
    margin: 0 0 var(--space-5);
}

.careers-hero-subtitle {
    font-size: var(--text-lg);
    opacity: 0.9;
    line-height: var(--leading-relaxed);
    margin: 0 0 var(--space-8);
}

/* Why Join Section */
.why-join-section {
    padding: var(--space-20) var(--space-6);
}

.why-join-section .container {
    max-width: 1200px;
    margin: 0 auto;
}

.section-header {
    text-align: center;
    margin-bottom: var(--space-12);
}

.section-title {
    font-size: var(--text-3xl);
    font-weight: var(--font-bold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-3);
}

.section-subtitle {
    font-size: var(--text-lg);
    color: var(--color-text-muted);
    margin: 0;
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-6);
}

.benefit-card {
    padding: var(--space-6);
    background: white;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    transition: all var(--transition-base);
}

.benefit-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--heco-primary-200);
}

.benefit-icon {
    width: 52px;
    height: 52px;
    background: var(--heco-primary-50);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--heco-primary-600);
    font-size: var(--text-xl);
    margin-bottom: var(--space-4);
}

.benefit-title {
    font-size: var(--text-base);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-2);
}

.benefit-text {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    line-height: var(--leading-relaxed);
    margin: 0;
}

/* Values Section */
.values-section {
    padding: var(--space-20) var(--space-6);
    background: var(--heco-neutral-50);
}

.values-section .container {
    max-width: 1100px;
    margin: 0 auto;
}

.values-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-12);
    align-items: center;
}

.values-title {
    font-size: var(--text-3xl);
    font-weight: var(--font-bold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-4);
}

.values-text p {
    font-size: var(--text-base);
    color: var(--color-text);
    line-height: var(--leading-relaxed);
    margin: 0 0 var(--space-6);
}

.values-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.values-list li {
    display: flex;
    align-items: flex-start;
    gap: var(--space-3);
    margin-bottom: var(--space-3);
}

.values-list i {
    color: var(--heco-primary-600);
    font-size: var(--text-lg);
    margin-top: 2px;
}

.values-list span {
    font-size: var(--text-base);
    color: var(--color-text);
}

.values-image-placeholder {
    width: 100%;
    height: 400px;
    background: linear-gradient(135deg, var(--heco-primary-200), var(--heco-primary-300));
    border-radius: var(--radius-xl);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--heco-primary-700);
}

.values-image-placeholder i {
    font-size: 80px;
    margin-bottom: var(--space-4);
}

.values-image-placeholder span {
    font-size: var(--text-lg);
    font-weight: var(--font-medium);
}

/* Openings Section */
.openings-section {
    padding: var(--space-20) var(--space-6);
}

.openings-section .container {
    max-width: 900px;
    margin: 0 auto;
}

.openings-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-5);
}

.position-card {
    background: white;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    padding: var(--space-6);
    transition: all var(--transition-base);
}

.position-card:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--heco-primary-200);
}

.position-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--space-4);
    margin-bottom: var(--space-4);
}

.position-title {
    font-size: var(--text-xl);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-3);
}

.position-meta {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
}

.position-tag {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    padding: var(--space-1) var(--space-3);
    background: var(--heco-neutral-100);
    border-radius: var(--radius-full);
    font-size: var(--text-xs);
    color: var(--color-text-muted);
}

.position-tag i {
    font-size: var(--text-xs);
}

.position-description {
    font-size: var(--text-base);
    color: var(--color-text);
    line-height: var(--leading-relaxed);
    margin: 0 0 var(--space-4);
}

.position-requirements {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    line-height: var(--leading-relaxed);
    padding-top: var(--space-4);
    border-top: 1px solid var(--color-border);
}

/* No Positions */
.no-positions {
    text-align: center;
    padding: var(--space-12);
    background: var(--heco-neutral-50);
    border-radius: var(--radius-xl);
}

.no-positions-icon {
    font-size: 48px;
    color: var(--color-text-light);
    margin-bottom: var(--space-4);
}

.no-positions h3 {
    font-size: var(--text-xl);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-3);
}

.no-positions p {
    font-size: var(--text-base);
    color: var(--color-text-muted);
    max-width: 500px;
    margin: 0 auto;
}

/* General Application */
.general-application {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--space-6);
    padding: var(--space-8);
    background: var(--heco-primary-50);
    border-radius: var(--radius-xl);
    margin-top: var(--space-8);
}

.general-application h3 {
    font-size: var(--text-xl);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-2);
}

.general-application p {
    font-size: var(--text-base);
    color: var(--color-text-muted);
    margin: 0;
}

/* Process Section */
.process-section {
    padding: var(--space-20) var(--space-6);
    background: var(--heco-neutral-50);
}

.process-section .container {
    max-width: 1000px;
    margin: 0 auto;
}

.process-steps {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}

.process-step {
    text-align: center;
    flex: 1;
    max-width: 160px;
}

.process-step-number {
    width: 48px;
    height: 48px;
    background: var(--heco-primary-600);
    color: white;
    font-size: var(--text-xl);
    font-weight: var(--font-bold);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--space-4);
}

.process-step h4 {
    font-size: var(--text-base);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-2);
}

.process-step p {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    line-height: var(--leading-relaxed);
    margin: 0;
}

.process-connector {
    flex: 1;
    height: 2px;
    background: var(--heco-primary-200);
    margin-top: 24px;
    max-width: 60px;
}

/* Responsive */
@media (max-width: 991px) {
    .benefits-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .values-content {
        grid-template-columns: 1fr;
    }

    .values-image-placeholder {
        height: 300px;
    }

    .process-steps {
        flex-wrap: wrap;
        gap: var(--space-6);
        justify-content: center;
    }

    .process-connector {
        display: none;
    }
}

@media (max-width: 767px) {
    .careers-hero {
        padding: var(--space-16) var(--space-4);
    }

    .careers-hero-title {
        font-size: var(--text-2xl);
    }

    .why-join-section,
    .values-section,
    .openings-section,
    .process-section {
        padding: var(--space-12) var(--space-4);
    }

    .benefits-grid {
        grid-template-columns: 1fr;
    }

    .position-header {
        flex-direction: column;
    }

    .position-header .btn {
        width: 100%;
    }

    .general-application {
        flex-direction: column;
        text-align: center;
    }

    .process-step {
        max-width: 100%;
        width: 100%;
    }
}
</style>
@endsection

@section('content')
<!-- Hero Section -->
<section class="careers-hero">
    <div class="careers-hero-content">
        <span class="careers-hero-label">Join Our Team</span>
        <h1 class="careers-hero-title">Help Us Regenerate the Himalayas</h1>
        <p class="careers-hero-subtitle">
            Be part of a mission-driven team working to transform tourism into a force for
            ecological restoration and community empowerment.
        </p>
        <a href="#openings" class="btn btn-white btn-lg">
            <i class="bi bi-arrow-down"></i>
            View Open Positions
        </a>
    </div>
</section>

<!-- Why Join Section -->
<section class="why-join-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Why Work With Us</h2>
            <p class="section-subtitle">
                Join a team that's passionate about making a real difference
            </p>
        </div>

        <div class="benefits-grid">
            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="bi bi-globe-asia-australia"></i>
                </div>
                <h3 class="benefit-title">Meaningful Impact</h3>
                <p class="benefit-text">
                    Your work directly contributes to conservation, community development, and
                    preserving local culture for future generations.
                </p>
            </div>

            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="bi bi-laptop"></i>
                </div>
                <h3 class="benefit-title">Remote-First Culture</h3>
                <p class="benefit-text">
                    Work from anywhere. Whether you're in the mountains or a city, we value results
                    over presence and support flexible working arrangements.
                </p>
            </div>

            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <h3 class="benefit-title">Growth Opportunities</h3>
                <p class="benefit-text">
                    We're a growing organization with endless opportunities to learn, lead, and
                    shape the future of sustainable tourism.
                </p>
            </div>

            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="bi bi-airplane"></i>
                </div>
                <h3 class="benefit-title">Travel Perks</h3>
                <p class="benefit-text">
                    Experience the Himalayas firsthand. Team members get special discounts on
                    experiences and opportunities to visit our partner communities.
                </p>
            </div>

            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="bi bi-people"></i>
                </div>
                <h3 class="benefit-title">Diverse Team</h3>
                <p class="benefit-text">
                    Work alongside conservationists, tech professionals, community leaders, and
                    travel experts from diverse backgrounds.
                </p>
            </div>

            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="bi bi-heart"></i>
                </div>
                <h3 class="benefit-title">Work-Life Balance</h3>
                <p class="benefit-text">
                    We believe in sustainable work practices. Generous leave policies, mental health
                    support, and respect for personal time.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Values Section -->
<section class="values-section">
    <div class="container">
        <div class="values-content">
            <div class="values-text">
                <h2 class="values-title">Our Culture</h2>
                <p>
                    At HECO, we're building more than a business - we're building a movement. Our team is
                    united by a shared love for the Himalayas and a belief that tourism can be a force for good.
                </p>
                <ul class="values-list">
                    <li>
                        <i class="bi bi-check-circle"></i>
                        <span><strong>Purpose-driven:</strong> Every decision is guided by our mission to regenerate the Himalayas</span>
                    </li>
                    <li>
                        <i class="bi bi-check-circle"></i>
                        <span><strong>Community-first:</strong> We prioritize local communities in all we do</span>
                    </li>
                    <li>
                        <i class="bi bi-check-circle"></i>
                        <span><strong>Transparent:</strong> Open communication and honest feedback</span>
                    </li>
                    <li>
                        <i class="bi bi-check-circle"></i>
                        <span><strong>Collaborative:</strong> We succeed together as a team</span>
                    </li>
                    <li>
                        <i class="bi bi-check-circle"></i>
                        <span><strong>Adaptive:</strong> We embrace change and continuous learning</span>
                    </li>
                </ul>
            </div>
            <div class="values-image">
                <div class="values-image-placeholder">
                    <i class="bi bi-people-fill"></i>
                    <span>Our Team</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Open Positions Section -->
<section id="openings" class="openings-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Open Positions</h2>
            <p class="section-subtitle">
                Find your place in our growing team
            </p>
        </div>

        <div class="openings-list">
            <!-- Position 1 -->
            <div class="position-card">
                <div class="position-header">
                    <div class="position-info">
                        <h3 class="position-title">Full Stack Developer</h3>
                        <div class="position-meta">
                            <span class="position-tag">
                                <i class="bi bi-geo-alt"></i> Remote (India)
                            </span>
                            <span class="position-tag">
                                <i class="bi bi-clock"></i> Full-time
                            </span>
                            <span class="position-tag">
                                <i class="bi bi-briefcase"></i> Engineering
                            </span>
                        </div>
                    </div>
                    <a href="mailto:careers@himalayanecotourism.com?subject=Application: Full Stack Developer" class="btn btn-primary">
                        Apply Now
                    </a>
                </div>
                <p class="position-description">
                    Help build and scale our platform using Laravel, Vue.js, and modern web technologies.
                    You'll work on features that directly impact travelers and local communities.
                </p>
                <div class="position-requirements">
                    <strong>Requirements:</strong> 3+ years experience with PHP/Laravel, JavaScript,
                    and modern frontend frameworks. Experience with MySQL, Redis, and cloud infrastructure.
                </div>
            </div>

            <!-- Position 2 -->
            <div class="position-card">
                <div class="position-header">
                    <div class="position-info">
                        <h3 class="position-title">Community Partnership Manager</h3>
                        <div class="position-meta">
                            <span class="position-tag">
                                <i class="bi bi-geo-alt"></i> Himachal Pradesh
                            </span>
                            <span class="position-tag">
                                <i class="bi bi-clock"></i> Full-time
                            </span>
                            <span class="position-tag">
                                <i class="bi bi-briefcase"></i> Operations
                            </span>
                        </div>
                    </div>
                    <a href="mailto:careers@himalayanecotourism.com?subject=Application: Community Partnership Manager" class="btn btn-primary">
                        Apply Now
                    </a>
                </div>
                <p class="position-description">
                    Build and nurture relationships with local communities, hosts, and service providers.
                    You'll be the bridge between HECO and the communities we serve.
                </p>
                <div class="position-requirements">
                    <strong>Requirements:</strong> Fluency in Hindi and preferably a regional language.
                    Experience in community development or tourism operations. Willingness to travel frequently.
                </div>
            </div>

            <!-- Position 3 -->
            <div class="position-card">
                <div class="position-header">
                    <div class="position-info">
                        <h3 class="position-title">Content & Marketing Specialist</h3>
                        <div class="position-meta">
                            <span class="position-tag">
                                <i class="bi bi-geo-alt"></i> Remote (India)
                            </span>
                            <span class="position-tag">
                                <i class="bi bi-clock"></i> Full-time
                            </span>
                            <span class="position-tag">
                                <i class="bi bi-briefcase"></i> Marketing
                            </span>
                        </div>
                    </div>
                    <a href="mailto:careers@himalayanecotourism.com?subject=Application: Content & Marketing Specialist" class="btn btn-primary">
                        Apply Now
                    </a>
                </div>
                <p class="position-description">
                    Tell the stories of the Himalayas and our communities through compelling content.
                    Manage social media, create marketing campaigns, and build our brand presence.
                </p>
                <div class="position-requirements">
                    <strong>Requirements:</strong> 2+ years experience in content marketing or journalism.
                    Strong writing skills, photography/video experience a plus. Passion for travel and sustainability.
                </div>
            </div>

            <!-- No Positions Placeholder -->
            <div class="no-positions" style="display: none;">
                <div class="no-positions-icon">
                    <i class="bi bi-inbox"></i>
                </div>
                <h3>No Open Positions Right Now</h3>
                <p>
                    We don't have any open positions at the moment, but we're always looking for talented
                    people who share our passion. Send us your resume and we'll keep you in mind for
                    future opportunities.
                </p>
            </div>
        </div>

        <!-- General Application -->
        <div class="general-application">
            <div class="general-application-content">
                <h3>Don't See a Perfect Fit?</h3>
                <p>
                    We're always interested in hearing from passionate individuals. Send us your resume
                    and tell us how you'd like to contribute to our mission.
                </p>
            </div>
            <a href="mailto:careers@himalayanecotourism.com?subject=General Application" class="btn btn-secondary btn-lg">
                <i class="bi bi-envelope"></i>
                Send Your Resume
            </a>
        </div>
    </div>
</section>

<!-- Application Process -->
<section class="process-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Our Hiring Process</h2>
            <p class="section-subtitle">
                What to expect when you apply
            </p>
        </div>

        <div class="process-steps">
            <div class="process-step">
                <div class="process-step-number">1</div>
                <h4>Application Review</h4>
                <p>We review every application carefully and respond within 1-2 weeks.</p>
            </div>
            <div class="process-connector"></div>
            <div class="process-step">
                <div class="process-step-number">2</div>
                <h4>Initial Conversation</h4>
                <p>A 30-minute call to learn about you and share more about the role.</p>
            </div>
            <div class="process-connector"></div>
            <div class="process-step">
                <div class="process-step-number">3</div>
                <h4>Skills Assessment</h4>
                <p>A practical exercise or assignment relevant to the position.</p>
            </div>
            <div class="process-connector"></div>
            <div class="process-step">
                <div class="process-step-number">4</div>
                <h4>Team Interview</h4>
                <p>Meet the team you'll be working with and discuss the role in depth.</p>
            </div>
            <div class="process-connector"></div>
            <div class="process-step">
                <div class="process-step-number">5</div>
                <h4>Offer</h4>
                <p>If it's a mutual fit, we'll extend an offer to join our team!</p>
            </div>
        </div>
    </div>
</section>
@endsection
