@extends('portal.layout')
@section('title', 'Travel Guidelines - HECO Portal')

@section('css')
<style>
/* Page Hero */
.page-hero {
    background: linear-gradient(135deg, var(--heco-primary-800) 0%, var(--heco-primary-600) 100%);
    padding: var(--space-20) var(--space-6);
    text-align: center;
    color: white;
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

/* Quick Links */
.quick-links {
    background: white;
    padding: var(--space-8) var(--space-6);
    border-bottom: 1px solid var(--color-border);
}

.quick-links .container {
    max-width: 800px;
    margin: 0 auto;
}

.quick-links-grid {
    display: flex;
    justify-content: center;
    gap: var(--space-4);
}

.quick-link {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-5);
    background: var(--heco-neutral-50);
    border-radius: var(--radius-full);
    text-decoration: none;
    color: var(--heco-primary-700);
    font-weight: var(--font-medium);
    font-size: var(--text-sm);
    transition: all var(--transition-fast);
}

.quick-link:hover {
    background: var(--heco-primary-50);
    transform: translateY(-2px);
}

.quick-link i {
    font-size: var(--text-lg);
}

/* Guidelines Sections */
.guidelines-section {
    padding: var(--space-16) var(--space-6);
    scroll-margin-top: 90px;
}

.guidelines-section-alt {
    background: var(--heco-neutral-50);
}

.guidelines-section .container {
    max-width: 1000px;
    margin: 0 auto;
}

.section-header-left {
    display: flex;
    align-items: flex-start;
    gap: var(--space-4);
    margin-bottom: var(--space-10);
}

.section-icon {
    width: 56px;
    height: 56px;
    background: var(--heco-primary-100);
    border-radius: var(--radius-xl);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--heco-primary-700);
    font-size: var(--text-2xl);
    flex-shrink: 0;
}

.section-title {
    font-size: var(--text-2xl);
    font-weight: var(--font-bold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-1);
}

.section-subtitle {
    font-size: var(--text-base);
    color: var(--color-text-muted);
    margin: 0;
}

/* Guideline Cards */
.guidelines-content {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-6);
    margin-bottom: var(--space-8);
}

.guideline-card {
    background: white;
    padding: var(--space-6);
    border-radius: var(--radius-xl);
    border: 1px solid var(--color-border);
}

.guidelines-section-alt .guideline-card {
    background: white;
}

.guideline-card h3 {
    font-size: var(--text-lg);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-4);
    padding-bottom: var(--space-3);
    border-bottom: 2px solid var(--heco-primary-100);
}

.guideline-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.guideline-card li {
    font-size: var(--text-sm);
    color: var(--color-text);
    line-height: var(--leading-relaxed);
    margin-bottom: var(--space-3);
    padding-left: var(--space-5);
    position: relative;
}

.guideline-card li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 8px;
    width: 6px;
    height: 6px;
    background: var(--heco-primary-400);
    border-radius: 50%;
}

.guideline-card li:last-child {
    margin-bottom: 0;
}

/* Emergency Box */
.emergency-box {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: var(--radius-xl);
    padding: var(--space-6);
}

.emergency-box h4 {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--text-base);
    font-weight: var(--font-semibold);
    color: #991b1b;
    margin: 0 0 var(--space-4);
}

.emergency-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-4);
}

.emergency-item {
    display: flex;
    flex-direction: column;
}

.emergency-label {
    font-size: var(--text-xs);
    color: #991b1b;
    text-transform: uppercase;
    letter-spacing: var(--tracking-wide);
    margin-bottom: var(--space-1);
}

.emergency-number {
    font-size: var(--text-lg);
    font-weight: var(--font-bold);
    color: #7f1d1d;
}

/* Packing Categories */
.packing-categories {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-6);
}

.packing-category {
    background: white;
    padding: var(--space-6);
    border-radius: var(--radius-xl);
    border: 1px solid var(--color-border);
}

.packing-category h3 {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--text-base);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-800);
    margin: 0 0 var(--space-4);
}

.packing-category h3 i {
    color: var(--heco-primary-600);
}

.packing-items {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.packing-item {
    display: flex;
    align-items: flex-start;
    gap: var(--space-2);
    font-size: var(--text-sm);
    color: var(--color-text);
}

.packing-item i {
    color: var(--heco-primary-500);
    margin-top: 2px;
    flex-shrink: 0;
}

/* Etiquette Grid */
.etiquette-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-5);
    margin-bottom: var(--space-8);
}

.etiquette-card {
    background: white;
    padding: var(--space-5);
    border-radius: var(--radius-xl);
    border: 1px solid var(--color-border);
    text-align: center;
}

.etiquette-icon {
    width: 52px;
    height: 52px;
    background: var(--heco-primary-50);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--heco-primary-600);
    font-size: var(--text-xl);
    margin: 0 auto var(--space-4);
}

.etiquette-card h4 {
    font-size: var(--text-base);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0 0 var(--space-2);
}

.etiquette-card p {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    line-height: var(--leading-relaxed);
    margin: 0;
}

/* Cultural Tips Box */
.cultural-tips-box {
    background: var(--heco-primary-50);
    padding: var(--space-6);
    border-radius: var(--radius-xl);
    border: 1px solid var(--heco-primary-100);
}

.cultural-tips-box h4 {
    font-size: var(--text-base);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-800);
    margin: 0 0 var(--space-4);
}

.cultural-tips-box ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.cultural-tips-box li {
    font-size: var(--text-sm);
    color: var(--heco-primary-700);
    line-height: var(--leading-relaxed);
    margin-bottom: var(--space-2);
    padding-left: var(--space-5);
    position: relative;
}

.cultural-tips-box li::before {
    content: '\2713';
    position: absolute;
    left: 0;
    color: var(--heco-primary-600);
}

/* Environment Section */
.environment-intro {
    max-width: 800px;
    margin-bottom: var(--space-8);
}

.environment-intro p {
    font-size: var(--text-lg);
    color: var(--color-text);
    line-height: var(--leading-relaxed);
    margin: 0;
}

.environment-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-6);
    margin-bottom: var(--space-8);
}

.environment-card {
    background: white;
    padding: var(--space-6);
    border-radius: var(--radius-xl);
    border: 1px solid var(--color-border);
}

.environment-header {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-4);
}

.environment-header i {
    font-size: var(--text-2xl);
    color: var(--heco-primary-600);
}

.environment-header h4 {
    font-size: var(--text-lg);
    font-weight: var(--font-semibold);
    color: var(--heco-primary-900);
    margin: 0;
}

.environment-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.environment-card li {
    font-size: var(--text-sm);
    color: var(--color-text);
    line-height: var(--leading-relaxed);
    margin-bottom: var(--space-2);
    padding-left: var(--space-5);
    position: relative;
}

.environment-card li::before {
    content: '\2022';
    position: absolute;
    left: 0;
    color: var(--heco-primary-500);
}

/* Pledge Box */
.pledge-box {
    background: var(--heco-primary-800);
    color: white;
    padding: var(--space-8);
    border-radius: var(--radius-xl);
}

.pledge-box h4 {
    font-size: var(--text-xl);
    font-weight: var(--font-bold);
    margin: 0 0 var(--space-2);
}

.pledge-box > p:first-of-type {
    font-size: var(--text-base);
    opacity: 0.9;
    margin: 0 0 var(--space-4);
}

.pledge-box ul {
    list-style: none;
    padding: 0;
    margin: 0 0 var(--space-5);
}

.pledge-box li {
    font-size: var(--text-base);
    line-height: var(--leading-relaxed);
    margin-bottom: var(--space-2);
    padding-left: var(--space-6);
    position: relative;
}

.pledge-box li::before {
    content: '\2713';
    position: absolute;
    left: 0;
    color: var(--heco-primary-300);
}

.pledge-note {
    font-size: var(--text-sm);
    opacity: 0.8;
    margin: 0;
    padding-top: var(--space-4);
    border-top: 1px solid rgba(255,255,255,0.2);
}

/* CTA Section */
.guidelines-cta {
    background: linear-gradient(135deg, var(--heco-primary-700), var(--heco-primary-600));
    padding: var(--space-16) var(--space-6);
    text-align: center;
    color: white;
}

.guidelines-cta .container {
    max-width: 600px;
    margin: 0 auto;
}

.guidelines-cta h2 {
    font-size: var(--text-3xl);
    font-weight: var(--font-bold);
    margin: 0 0 var(--space-3);
}

.guidelines-cta p {
    font-size: var(--text-lg);
    opacity: 0.9;
    margin: 0 0 var(--space-8);
}

.cta-buttons {
    display: flex;
    justify-content: center;
    gap: var(--space-4);
}

/* Responsive */
@media (max-width: 991px) {
    .guidelines-content {
        grid-template-columns: 1fr;
    }

    .packing-categories {
        grid-template-columns: 1fr;
    }

    .etiquette-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .emergency-grid {
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

    .quick-links-grid {
        flex-wrap: wrap;
    }

    .quick-link {
        flex: 1 1 45%;
        justify-content: center;
    }

    .guidelines-section {
        padding: var(--space-10) var(--space-4);
    }

    .section-header-left {
        flex-direction: column;
    }

    .etiquette-grid,
    .environment-grid {
        grid-template-columns: 1fr;
    }

    .emergency-grid {
        grid-template-columns: 1fr;
        gap: var(--space-3);
    }

    .cta-buttons {
        flex-direction: column;
    }

    .cta-buttons .btn {
        width: 100%;
    }
}
</style>
@endsection

@section('content')
<!-- Hero Section -->
<section class="page-hero">
    <div class="page-hero-content">
        <span class="page-hero-label">Be Prepared</span>
        <h1 class="page-hero-title">Travel Guidelines</h1>
        <p class="page-hero-subtitle">
            Essential information to help you prepare for a safe, enjoyable, and responsible
            journey through the Himalayas.
        </p>
    </div>
</section>

<!-- Quick Links -->
<section class="quick-links">
    <div class="container">
        <div class="quick-links-grid">
            <a href="#safety" class="quick-link">
                <i class="bi bi-shield-check"></i>
                <span>Safety Tips</span>
            </a>
            <a href="#packing" class="quick-link">
                <i class="bi bi-backpack2"></i>
                <span>Packing Guide</span>
            </a>
            <a href="#cultural" class="quick-link">
                <i class="bi bi-heart"></i>
                <span>Cultural Etiquette</span>
            </a>
            <a href="#environmental" class="quick-link">
                <i class="bi bi-tree"></i>
                <span>Environmental Responsibility</span>
            </a>
        </div>
    </div>
</section>

<!-- Safety Tips Section -->
<section id="safety" class="guidelines-section">
    <div class="container">
        <div class="section-header-left">
            <div class="section-icon">
                <i class="bi bi-shield-check"></i>
            </div>
            <div>
                <h2 class="section-title">Safety Tips</h2>
                <p class="section-subtitle">Stay safe during your Himalayan adventure</p>
            </div>
        </div>

        <div class="guidelines-content">
            <div class="guideline-card">
                <h3>Health & Medical</h3>
                <ul>
                    <li>
                        <strong>Travel Insurance:</strong> Ensure comprehensive coverage including
                        medical evacuation, especially for high-altitude treks
                    </li>
                    <li>
                        <strong>Medications:</strong> Carry prescription medications in original packaging
                        with a doctor's note; bring adequate supply plus extras
                    </li>
                    <li>
                        <strong>First Aid Kit:</strong> Include pain relievers, bandages, antiseptic,
                        anti-diarrheal medication, and any personal medications
                    </li>
                    <li>
                        <strong>Vaccinations:</strong> Consult your doctor about recommended vaccinations
                        at least 4-6 weeks before travel
                    </li>
                    <li>
                        <strong>Altitude Sickness:</strong> Acclimatize properly, ascend gradually,
                        stay hydrated, and know the symptoms (headache, nausea, fatigue)
                    </li>
                </ul>
            </div>

            <div class="guideline-card">
                <h3>Weather & Terrain</h3>
                <ul>
                    <li>
                        <strong>Weather Awareness:</strong> Mountain weather changes rapidly; always
                        check forecasts and be prepared for all conditions
                    </li>
                    <li>
                        <strong>Monsoon Season:</strong> June-September brings heavy rains and landslide
                        risks; plan accordingly or consider off-season travel
                    </li>
                    <li>
                        <strong>Winter Conditions:</strong> November-February can bring snow at high
                        altitudes; some routes may be closed
                    </li>
                    <li>
                        <strong>Trail Safety:</strong> Stay on marked trails, avoid shortcuts, and
                        don't venture into restricted areas
                    </li>
                    <li>
                        <strong>River Crossings:</strong> Cross at designated points; water levels
                        can rise quickly during rain
                    </li>
                </ul>
            </div>

            <div class="guideline-card">
                <h3>Personal Safety</h3>
                <ul>
                    <li>
                        <strong>Travel with Guides:</strong> Always use local guides for remote treks
                        and unfamiliar areas
                    </li>
                    <li>
                        <strong>Communication:</strong> Carry a charged phone; note that connectivity
                        may be limited in remote areas
                    </li>
                    <li>
                        <strong>Emergency Contacts:</strong> Save local emergency numbers and your
                        embassy contact; share your itinerary with family
                    </li>
                    <li>
                        <strong>Group Travel:</strong> Don't trek alone; inform your host of your
                        daily plans
                    </li>
                    <li>
                        <strong>Valuables:</strong> Keep important documents and valuables secure;
                        carry copies separately
                    </li>
                </ul>
            </div>
        </div>

        <div class="emergency-box">
            <h4><i class="bi bi-exclamation-triangle"></i> Emergency Contacts</h4>
            <div class="emergency-grid">
                <div class="emergency-item">
                    <span class="emergency-label">Police</span>
                    <span class="emergency-number">100</span>
                </div>
                <div class="emergency-item">
                    <span class="emergency-label">Ambulance</span>
                    <span class="emergency-number">102</span>
                </div>
                <div class="emergency-item">
                    <span class="emergency-label">Disaster Helpline</span>
                    <span class="emergency-number">108</span>
                </div>
                <div class="emergency-item">
                    <span class="emergency-label">HECO Support</span>
                    <span class="emergency-number">+91 123 456 7890</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Packing Guide Section -->
<section id="packing" class="guidelines-section guidelines-section-alt">
    <div class="container">
        <div class="section-header-left">
            <div class="section-icon">
                <i class="bi bi-backpack2"></i>
            </div>
            <div>
                <h2 class="section-title">Packing Guide</h2>
                <p class="section-subtitle">What to bring for your Himalayan journey</p>
            </div>
        </div>

        <div class="packing-categories">
            <!-- Clothing -->
            <div class="packing-category">
                <h3><i class="bi bi-person"></i> Clothing</h3>
                <div class="packing-items">
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Layered clothing (base, insulation, outer layers)</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Waterproof/windproof jacket</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Warm fleece or down jacket</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Trekking pants (quick-dry, comfortable)</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Thermal underwear for cold conditions</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Moisture-wicking t-shirts</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Warm hat and sun hat</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Gloves (warm and waterproof)</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Wool or synthetic hiking socks</span>
                    </div>
                </div>
            </div>

            <!-- Footwear -->
            <div class="packing-category">
                <h3><i class="bi bi-shoe"></i> Footwear</h3>
                <div class="packing-items">
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Sturdy, broken-in hiking boots</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Sandals or camp shoes for evenings</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Gaiters (for snow or mud)</span>
                    </div>
                </div>
            </div>

            <!-- Gear -->
            <div class="packing-category">
                <h3><i class="bi bi-compass"></i> Gear & Equipment</h3>
                <div class="packing-items">
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Daypack (20-30L)</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Reusable water bottle or hydration system</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Headlamp with extra batteries</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Trekking poles (recommended)</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Sleeping bag (for camping/homestays)</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Rain cover for backpack</span>
                    </div>
                </div>
            </div>

            <!-- Personal Items -->
            <div class="packing-category">
                <h3><i class="bi bi-bag"></i> Personal Items</h3>
                <div class="packing-items">
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Sunscreen (SPF 30+) and lip balm</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>UV-blocking sunglasses</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Toiletries (biodegradable preferred)</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Quick-dry towel</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Hand sanitizer and wet wipes</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Insect repellent</span>
                    </div>
                </div>
            </div>

            <!-- Electronics -->
            <div class="packing-category">
                <h3><i class="bi bi-phone"></i> Electronics</h3>
                <div class="packing-items">
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Phone with offline maps downloaded</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Portable power bank</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Camera with extra batteries/cards</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Universal adapter</span>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="packing-category">
                <h3><i class="bi bi-file-earmark-text"></i> Documents</h3>
                <div class="packing-items">
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Valid ID / Passport</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Permits (arranged by HECO)</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Travel insurance documents</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Booking confirmations (printed copies)</span>
                    </div>
                    <div class="packing-item">
                        <i class="bi bi-check2"></i>
                        <span>Emergency contact list</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Cultural Etiquette Section -->
<section id="cultural" class="guidelines-section">
    <div class="container">
        <div class="section-header-left">
            <div class="section-icon">
                <i class="bi bi-heart"></i>
            </div>
            <div>
                <h2 class="section-title">Cultural Etiquette</h2>
                <p class="section-subtitle">Respect and connect with local communities</p>
            </div>
        </div>

        <div class="etiquette-grid">
            <div class="etiquette-card">
                <div class="etiquette-icon">
                    <i class="bi bi-hand-thumbs-up"></i>
                </div>
                <h4>Greetings</h4>
                <p>
                    Learn basic local greetings. "Namaste" with folded hands is widely appreciated
                    across the Himalayan region. A smile and respectful nod goes a long way.
                </p>
            </div>

            <div class="etiquette-card">
                <div class="etiquette-icon">
                    <i class="bi bi-person-arms-up"></i>
                </div>
                <h4>Dress Code</h4>
                <p>
                    Dress modestly, especially when visiting religious sites. Cover shoulders and
                    knees. Remove shoes before entering homes and temples. Avoid wearing leather
                    in Hindu temples.
                </p>
            </div>

            <div class="etiquette-card">
                <div class="etiquette-icon">
                    <i class="bi bi-camera"></i>
                </div>
                <h4>Photography</h4>
                <p>
                    Always ask permission before photographing people. Some religious sites prohibit
                    photography. Avoid photographing military installations or sensitive areas.
                </p>
            </div>

            <div class="etiquette-card">
                <div class="etiquette-icon">
                    <i class="bi bi-building"></i>
                </div>
                <h4>Sacred Sites</h4>
                <p>
                    Walk clockwise around Buddhist stupas and monasteries. Don't point feet at
                    religious objects. Maintain silence and respect during ceremonies.
                </p>
            </div>

            <div class="etiquette-card">
                <div class="etiquette-icon">
                    <i class="bi bi-cup-hot"></i>
                </div>
                <h4>Food & Hospitality</h4>
                <p>
                    Accept offered food and tea graciously; refusing can be considered rude.
                    Eat with your right hand. Don't waste food. Compliment the cook!
                </p>
            </div>

            <div class="etiquette-card">
                <div class="etiquette-icon">
                    <i class="bi bi-cash"></i>
                </div>
                <h4>Bargaining</h4>
                <p>
                    Bargaining is acceptable in markets but do so respectfully. Remember that
                    tourism supports local livelihoods; paying fair prices helps communities.
                </p>
            </div>
        </div>

        <div class="cultural-tips-box">
            <h4>Pro Tips for Meaningful Connections</h4>
            <ul>
                <li>Learn a few words in the local language - it's always appreciated</li>
                <li>Show interest in local customs and traditions; ask questions respectfully</li>
                <li>Bring small gifts from home to share with your hosts</li>
                <li>Be patient - mountain life moves at a different pace</li>
                <li>Listen more than you speak; every elder has wisdom to share</li>
            </ul>
        </div>
    </div>
</section>

<!-- Environmental Responsibility Section -->
<section id="environmental" class="guidelines-section guidelines-section-alt">
    <div class="container">
        <div class="section-header-left">
            <div class="section-icon">
                <i class="bi bi-tree"></i>
            </div>
            <div>
                <h2 class="section-title">Environmental Responsibility</h2>
                <p class="section-subtitle">Leave no trace, only footprints</p>
            </div>
        </div>

        <div class="environment-intro">
            <p>
                The Himalayas are a fragile ecosystem facing increasing pressure from tourism and
                climate change. As a conscious traveler, your actions directly impact the
                preservation of these mountains for future generations.
            </p>
        </div>

        <div class="environment-grid">
            <div class="environment-card">
                <div class="environment-header">
                    <i class="bi bi-trash3"></i>
                    <h4>Waste Management</h4>
                </div>
                <ul>
                    <li>Carry a reusable bag for all your waste</li>
                    <li>Pack out everything you pack in</li>
                    <li>Avoid single-use plastics; bring reusable water bottle</li>
                    <li>Use biodegradable soaps and toiletries</li>
                    <li>Dispose of waste at designated collection points</li>
                </ul>
            </div>

            <div class="environment-card">
                <div class="environment-header">
                    <i class="bi bi-water"></i>
                    <h4>Water Conservation</h4>
                </div>
                <ul>
                    <li>Use water sparingly; it's precious in the mountains</li>
                    <li>Don't contaminate water sources with soap or waste</li>
                    <li>Carry water purification tablets/filter</li>
                    <li>Wash 200m away from streams and lakes</li>
                </ul>
            </div>

            <div class="environment-card">
                <div class="environment-header">
                    <i class="bi bi-flower1"></i>
                    <h4>Wildlife & Flora</h4>
                </div>
                <ul>
                    <li>Observe wildlife from a distance; don't feed animals</li>
                    <li>Stay on marked trails to protect vegetation</li>
                    <li>Don't pick flowers, plants, or remove natural objects</li>
                    <li>Keep noise levels low; respect animal habitats</li>
                </ul>
            </div>

            <div class="environment-card">
                <div class="environment-header">
                    <i class="bi bi-fire"></i>
                    <h4>Energy & Fire</h4>
                </div>
                <ul>
                    <li>Use designated cooking areas when camping</li>
                    <li>Never leave fires unattended; extinguish completely</li>
                    <li>Prefer local food to reduce cooking fuel needs</li>
                    <li>Turn off lights and electronics when not in use</li>
                </ul>
            </div>
        </div>

        <div class="pledge-box">
            <h4>The HECO Traveler Pledge</h4>
            <p>As a responsible traveler, I commit to:</p>
            <ul>
                <li>Leaving no trace of my visit in nature</li>
                <li>Respecting local communities, cultures, and traditions</li>
                <li>Supporting local businesses and economies</li>
                <li>Minimizing my environmental footprint</li>
                <li>Being an ambassador for sustainable tourism</li>
            </ul>
            <p class="pledge-note">
                By booking with HECO, you automatically support our Regenerative Projects Fund,
                which funds conservation and community development initiatives across the Himalayas.
            </p>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="guidelines-cta">
    <div class="container">
        <h2>Ready to Explore Responsibly?</h2>
        <p>
            Browse our curated experiences and start planning your adventure.
        </p>
        <div class="cta-buttons">
            <a href="/home" class="btn btn-white btn-lg">
                <i class="bi bi-compass"></i>
                Explore Experiences
            </a>
            <a href="/contact" class="btn btn-secondary btn-lg" style="color: white; border-color: white;">
                <i class="bi bi-question-circle"></i>
                Ask Us Anything
            </a>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
(function() {
    // Smooth scroll for quick links
    document.querySelectorAll('.quick-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
})();
</script>
@endsection
