@extends('portal.layout')
@section('title', 'About Us - HECO Portal')

@section('css')
<style>
/* Page Hero */
.page-hero {
    background: linear-gradient(135deg, var(--heco-primary-800) 0%, var(--heco-primary-600) 100%);
    padding: var(--space-24) var(--space-6);
    text-align: center;
    color: white;
}

.page-hero-content {
    max-width: 800px;
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
    margin-bottom: var(--space-6);
}

.page-hero-title {
    font-size: var(--text-4xl);
    font-weight: var(--font-bold);
    line-height: var(--leading-tight);
    margin: 0 0 var(--space-6);
}

.page-hero-subtitle {
    font-size: var(--text-lg);
    opacity: 0.9;
    line-height: var(--leading-relaxed);
    margin: 0;
}

/* Page Sections */
.page-section {
    padding: var(--space-20) var(--space-6);
}

.page-section-alt {
    background: var(--heco-neutral-50);
}

.page-section .container {
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
    max-width: 600px;
    margin: 0 auto;
}

/* About Grid */
.about-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-8);
}

.about-card {
    background: white;
    padding: var(--space-8);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--color-border);
}

.about-card-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, var(--heco-primary-500), var(--heco-primary-600));
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: var(--text-2xl);
    margin-bottom: var(--space-5);
}

.about-card-title {
    font-size: var(--text-xl);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-3);
}

.about-card-text {
    font-size: var(--text-base);
    color: var(--color-text-muted);
    line-height: var(--leading-relaxed);
    margin: 0;
}

/* How It Works */
.how-it-works-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-6);
}

.how-step {
    text-align: center;
    padding: var(--space-6);
}

.how-step-number {
    font-size: var(--text-4xl);
    font-weight: var(--font-bold);
    color: var(--heco-primary-200);
    margin-bottom: var(--space-4);
}

.how-step-title {
    font-size: var(--text-lg);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-3);
}

.how-step-text {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    line-height: var(--leading-relaxed);
    margin: 0;
}

/* Values Grid */
.values-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-6);
}

.value-card {
    background: white;
    padding: var(--space-6);
    border-radius: var(--radius-xl);
    border: 1px solid var(--color-border);
    transition: all var(--transition-base);
}

.value-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.value-icon {
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

.value-title {
    font-size: var(--text-base);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-2);
}

.value-text {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    line-height: var(--leading-relaxed);
    margin: 0;
}

/* Team Section */
.team-intro {
    max-width: 800px;
    margin: 0 auto var(--space-10);
    text-align: center;
}

.team-intro p {
    font-size: var(--text-base);
    color: var(--color-text-muted);
    line-height: var(--leading-relaxed);
    margin: 0 0 var(--space-4);
}

.team-cta {
    display: flex;
    justify-content: center;
    gap: var(--space-4);
}

/* Responsive */
@media (max-width: 991px) {
    .page-hero-title {
        font-size: var(--text-3xl);
    }

    .about-grid {
        grid-template-columns: 1fr;
    }

    .how-it-works-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .values-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 767px) {
    .page-hero {
        padding: var(--space-16) var(--space-4);
    }

    .page-hero-title {
        font-size: var(--text-2xl);
    }

    .page-section {
        padding: var(--space-12) var(--space-4);
    }

    .how-it-works-grid,
    .values-grid {
        grid-template-columns: 1fr;
    }

    .team-cta {
        flex-direction: column;
    }

    .team-cta .btn {
        width: 100%;
    }
}
</style>
@endsection

@section('content')
<!-- Hero Section -->
<section class="page-hero">
    <div class="page-hero-content">
        <span class="page-hero-label">About HECO</span>
        <h1 class="page-hero-title">Regenerating the Himalayas, One Journey at a Time</h1>
        <p class="page-hero-subtitle">
            We are a collective of local communities, conservationists, and travel enthusiasts
            working together to create meaningful travel experiences that benefit both travelers
            and local ecosystems.
        </p>
    </div>
</section>

<!-- Mission & Vision Section -->
<section class="page-section">
    <div class="container">
        <div class="about-grid">
            <div class="about-card">
                <div class="about-card-icon">
                    <i class="bi bi-bullseye"></i>
                </div>
                <h3 class="about-card-title">Our Mission</h3>
                <p class="about-card-text">
                    To transform tourism from an extractive industry into a regenerative
                    force that restores ecosystems, empowers local communities, and creates
                    profound connections between travelers and the mountains they visit.
                </p>
            </div>
            <div class="about-card">
                <div class="about-card-icon">
                    <i class="bi bi-eye"></i>
                </div>
                <h3 class="about-card-title">Our Vision</h3>
                <p class="about-card-text">
                    A world where tourism actively heals the environment, preserves
                    ancient cultures, and provides sustainable livelihoods for mountain
                    communities for generations to come.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- How HECO Works -->
<section class="page-section page-section-alt">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">How HECO Works</h2>
            <p class="section-subtitle">
                We connect conscious travelers with verified local partners who share our commitment
                to regenerative practices
            </p>
        </div>
        <div class="how-it-works-grid">
            <div class="how-step">
                <div class="how-step-number">01</div>
                <h4 class="how-step-title">Discover Experiences</h4>
                <p class="how-step-text">
                    Browse curated experiences designed by local experts who know the Himalayas intimately.
                    Each experience is vetted for authenticity and sustainability.
                </p>
            </div>
            <div class="how-step">
                <div class="how-step-number">02</div>
                <h4 class="how-step-title">Connect with Locals</h4>
                <p class="how-step-text">
                    Meet the HECO Resource Persons (HRP), Local Hosts (HLH), and Other Service
                    Providers (OSP) who will guide your journey with genuine hospitality.
                </p>
            </div>
            <div class="how-step">
                <div class="how-step-number">03</div>
                <h4 class="how-step-title">Travel Responsibly</h4>
                <p class="how-step-text">
                    Embark on your journey knowing that your travel directly supports conservation
                    efforts, local livelihoods, and cultural preservation initiatives.
                </p>
            </div>
            <div class="how-step">
                <div class="how-step-number">04</div>
                <h4 class="how-step-title">Leave a Positive Impact</h4>
                <p class="how-step-text">
                    A portion of every booking goes to our Regenerative Projects Fund, supporting
                    reforestation, wildlife conservation, and community development.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Core Values -->
<section class="page-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Our Core Values</h2>
            <p class="section-subtitle">
                The principles that guide everything we do
            </p>
        </div>
        <div class="values-grid">
            <div class="value-card">
                <div class="value-icon">
                    <i class="bi bi-recycle"></i>
                </div>
                <h4 class="value-title">Regenerative Travel</h4>
                <p class="value-text">
                    We go beyond sustainability. Our goal is to leave destinations better than
                    we found them through active restoration and conservation efforts integrated
                    into every experience.
                </p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="bi bi-people"></i>
                </div>
                <h4 class="value-title">Community-Driven</h4>
                <p class="value-text">
                    Local communities are at the heart of everything we do. They design the
                    experiences, set the pace, and receive fair compensation for sharing their
                    knowledge and hospitality.
                </p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="bi bi-gem"></i>
                </div>
                <h4 class="value-title">Authentic Experiences</h4>
                <p class="value-text">
                    No staged performances or tourist traps. Every interaction, every meal,
                    every story is genuine, offering travelers real connections with local
                    life and culture.
                </p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h4 class="value-title">Transparency</h4>
                <p class="value-text">
                    We believe travelers deserve to know exactly where their money goes. Our
                    pricing is transparent, and we share detailed impact reports showing how
                    tourism benefits flow to communities.
                </p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="bi bi-tree"></i>
                </div>
                <h4 class="value-title">Environmental Stewardship</h4>
                <p class="value-text">
                    From carbon-conscious travel planning to plastic-free operations, we
                    minimize environmental footprint while maximizing positive ecological impact
                    through active restoration.
                </p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="bi bi-heart"></i>
                </div>
                <h4 class="value-title">Cultural Respect</h4>
                <p class="value-text">
                    Ancient traditions, sacred sites, and local customs are honored and
                    protected. We educate travelers on cultural etiquette and ensure communities
                    control how their culture is shared.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Team Section -->
<section class="page-section page-section-alt">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">The People Behind HECO</h2>
            <p class="section-subtitle">
                A diverse team united by a shared love for the Himalayas
            </p>
        </div>
        <div class="team-intro">
            <p>
                HECO was founded by a group of conservationists, travel industry veterans, and
                community leaders who saw the need for a new approach to mountain tourism.
                Our team spans multiple regions and includes both urban professionals and
                village elders whose families have called these mountains home for generations.
            </p>
            <p>
                Together, we bring expertise in sustainable tourism, community development,
                wildlife conservation, and technology to create a platform that truly serves
                both travelers and local communities.
            </p>
        </div>
        <div class="team-cta">
            <a href="/join" class="btn btn-primary btn-lg">
                <i class="bi bi-handshake"></i>
                Join Our Mission
            </a>
            <a href="/contact" class="btn btn-secondary btn-lg">
                <i class="bi bi-envelope"></i>
                Get in Touch
            </a>
        </div>
    </div>
</section>
@endsection
