@extends('portal.layout')
@section('title', 'Careers - HECO Portal')

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
