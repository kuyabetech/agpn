<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get sponsor page content from database
$page = getPageContent('sponsor');
$page_title = $page ? $page['page_title'] : 'Why Sponsor AGPN';
$meta_description = $page ? $page['meta_description'] : 'Partner with AGPN to empower the next generation of African talent and drive meaningful impact.';
$meta_keywords = $page ? $page['meta_keywords'] : 'sponsor AGPN, partnership, corporate sponsorship, CSR, Africa, impact investing';

// Get session messages
$success = isset($_SESSION['sponsor_success']) ? $_SESSION['sponsor_success'] : null;
$error = isset($_SESSION['sponsor_error']) ? $_SESSION['sponsor_error'] : null;
unset($_SESSION['sponsor_success'], $_SESSION['sponsor_error']);

// Form data for repopulation
$form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
unset($_SESSION['form_data']);

$show_breadcrumbs = true;
$breadcrumbs = [
    ['title' => 'Sponsor Us']
];
$body_class = 'sponsor-page';

include 'includes/header.php';
?>

<style>
/* ============================================
   SPONSORSHIP PAGE STYLES
   Features: Toast Notifications, Tier Cards,
   Live Form Validation, FAQ Section
============================================ */

:root {
    --navy: #0A1929;
    --navy-light: #1A2A3A;
    --gold: #FFB81C;
    --gold-light: #FFD700;
    --white: #ffffff;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-600: #6c757d;
    --gray-900: #212529;
    --success: #28a745;
    --danger: #dc3545;
    --bronze: #CD7F32;
    --silver: #C0C0C0;
    --gold-tier: #FFB81C;
}

/* ============================================
   HERO SECTION
============================================ */
.sponsor-hero {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 100px 0;
    position: relative;
    overflow: hidden;
}

.sponsor-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(255,184,28,0.1) 0%, transparent 70%);
    border-radius: 50%;
    animation: pulse 8s infinite;
}

.sponsor-hero::after {
    content: '';
    position: absolute;
    bottom: -50%;
    left: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,184,28,0.05) 0%, transparent 70%);
    border-radius: 50%;
    animation: pulse 8s infinite reverse;
}

@keyframes pulse {
    0%, 100% { opacity: 0.1; transform: scale(1); }
    50% { opacity: 0.2; transform: scale(1.1); }
}

.sponsor-hero-content {
    position: relative;
    z-index: 2;
    max-width: 800px;
    margin: 0 auto;
    text-align: center;
    color: var(--white);
}

.sponsor-hero h1 {
    color: var(--white);
    font-size: clamp(36px, 5vw, 56px);
    margin-bottom: 20px;
    animation: fadeInUp 1s ease;
}

.sponsor-hero p {
    color: rgba(255,255,255,0.9);
    font-size: clamp(16px, 2vw, 18px);
    line-height: 1.8;
    max-width: 600px;
    margin: 0 auto;
    animation: fadeInUp 1s ease 0.1s both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ============================================
   SECTION HEADERS
============================================ */
.section-header {
    text-align: center;
    max-width: 700px;
    margin: 0 auto 50px;
}

.section-subtitle {
    color: var(--gold);
    display: block;
    margin-bottom: 15px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 3px;
}

.section-header h2 {
    font-size: clamp(28px, 4vw, 36px);
    margin-bottom: 15px;
    color: var(--navy);
}

.section-header h2 span {
    color: var(--gold);
}

.section-header p {
    color: var(--gray-600);
    font-size: 16px;
    line-height: 1.8;
}

/* ============================================
   BENEFIT CARDS
============================================ */
.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}

.benefit-card {
    background: var(--white);
    padding: 40px 30px;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.benefit-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 0;
    background: var(--gold);
    transition: height 0.3s ease;
}

.benefit-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    border-color: var(--gold);
}

.benefit-card:hover::before {
    height: 100%;
}

.benefit-icon {
    font-size: 48px;
    color: var(--gold);
    margin-bottom: 25px;
    transition: all 0.3s ease;
}

.benefit-card:hover .benefit-icon {
    transform: scale(1.1) rotate(5deg);
}

.benefit-card h3 {
    font-size: 22px;
    margin-bottom: 15px;
    color: var(--navy);
}

.benefit-card p {
    color: var(--gray-600);
    line-height: 1.8;
    margin: 0;
}

/* ============================================
   TIER CARDS
============================================ */
.tiers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 30px;
}

.tier-card {
    background: var(--white);
    border-radius: 20px;
    padding: 40px 30px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
}

.tier-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

.tier-card.recommended {
    border: 2px solid var(--gold);
    transform: scale(1.05);
    z-index: 2;
}

.tier-card.recommended:hover {
    transform: scale(1.05) translateY(-10px);
}

.tier-badge {
    position: absolute;
    top: 20px;
    right: -35px;
    padding: 8px 40px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    transform: rotate(45deg);
}

.tier-badge.popular {
    background: var(--bronze);
    color: var(--white);
}

.tier-badge.recommended {
    background: var(--gold);
    color: var(--navy);
}

.tier-name {
    font-size: 2rem;
    margin-bottom: 10px;
}

.tier-name.bronze {
    color: var(--bronze);
}

.tier-name.silver {
    color: var(--silver);
}

.tier-name.gold {
    color: var(--gold-tier);
}

.tier-price {
    font-size: 2rem;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 30px;
}

.tier-price span {
    font-size: 1rem;
    font-weight: 400;
    color: var(--gray-600);
}

.tier-features {
    list-style: none;
    padding: 0;
    margin-bottom: 30px;
    text-align: left;
}

.tier-features li {
    margin-bottom: 12px;
    color: var(--gray-700);
    display: flex;
    align-items: center;
}

.tier-features i {
    width: 24px;
    margin-right: 10px;
}

.tier-features .fa-check {
    color: var(--gold);
}

.tier-features .fa-times {
    color: var(--danger);
}

.btn-tier {
    width: 100%;
    padding: 14px;
    border: 2px solid transparent;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    text-decoration: none;
    display: inline-block;
}

.btn-tier-outline {
    background: transparent;
    border-color: var(--navy);
    color: var(--navy);
}

.btn-tier-outline:hover {
    background: var(--navy);
    color: var(--white);
}

.btn-tier-primary {
    background: var(--gold);
    color: var(--navy);
}

.btn-tier-primary:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(255,184,28,0.3);
}

/* ============================================
   FORM SECTION
============================================ */
.form-section {
    padding: 80px 0;
    background: linear-gradient(135deg, var(--gray-100) 0%, var(--white) 100%);
}

.form-card {
    background: var(--white);
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
    border: 1px solid var(--gray-200);
    max-width: 800px;
    margin: 0 auto;
}

.form-card h3 {
    font-size: 28px;
    margin-bottom: 10px;
    color: var(--navy);
}

.form-card h3 span {
    color: var(--gold);
}

.form-card > p {
    color: var(--gray-600);
    margin-bottom: 30px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--navy);
}

.form-group label span {
    color: var(--danger);
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--gray-200);
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: var(--white);
}

.form-control:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 4px rgba(255,184,28,0.1);
}

.form-control.error {
    border-color: var(--danger);
    background: var(--danger-light);
}

select.form-control {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 16px;
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
}

.btn-submit {
    width: 100%;
    padding: 16px;
    background: var(--gold);
    color: var(--navy);
    border: none;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    position: relative;
    overflow: hidden;
}

.btn-submit::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
    z-index: 1;
}

.btn-submit:hover::before {
    width: 300px;
    height: 300px;
}

.btn-submit:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(255,184,28,0.3);
}

.btn-submit i {
    position: relative;
    z-index: 2;
}

/* ============================================
   ALERT MESSAGES
============================================ */
.alert {
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 15px;
    animation: slideInDown 0.5s ease;
    border-left: 4px solid;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left-color: var(--success);
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-left-color: var(--danger);
}

.alert i {
    font-size: 24px;
}

.alert strong {
    display: block;
    margin-bottom: 5px;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ============================================
   TOAST NOTIFICATIONS
============================================ */
.toast-container {
    position: fixed;
    top: 30px;
    right: 30px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    background: var(--white);
    border-radius: 12px;
    padding: 16px 20px;
    min-width: 300px;
    max-width: 400px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 15px;
    transform: translateX(100%);
    animation: slideIn 0.3s ease forwards;
    border-left: 4px solid;
}

.toast-success {
    border-left-color: var(--success);
}

.toast-error {
    border-left-color: var(--danger);
}

.toast-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toast-success .toast-icon {
    background: rgba(40,167,69,0.1);
    color: var(--success);
}

.toast-error .toast-icon {
    background: rgba(220,53,69,0.1);
    color: var(--danger);
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: 700;
    margin-bottom: 4px;
    color: var(--navy);
}

.toast-message {
    font-size: 14px;
    color: var(--gray-600);
}

.toast-close {
    color: var(--gray-500);
    cursor: pointer;
    font-size: 18px;
    transition: color 0.3s ease;
    background: none;
    border: none;
    padding: 0;
}

.toast-close:hover {
    color: var(--danger);
}

@keyframes slideIn {
    to {
        transform: translateX(0);
    }
}

@keyframes slideOut {
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* ============================================
   FAQ SECTION
============================================ */
.faq-section {
    padding: 80px 0;
    background: var(--white);
}

.faq-grid {
    max-width: 800px;
    margin: 0 auto;
}

.faq-item {
    background: var(--gray-100);
    border-radius: 16px;
    margin-bottom: 15px;
    overflow: hidden;
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
}

.faq-item:hover {
    border-color: var(--gold);
}

.faq-question {
    padding: 20px 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    transition: background 0.3s ease;
}

.faq-question:hover {
    background: var(--white);
}

.faq-question h4 {
    margin: 0;
    font-size: 18px;
    color: var(--navy);
    font-weight: 600;
}

.faq-question i {
    color: var(--gold);
    transition: transform 0.3s ease;
}

.faq-answer {
    padding: 0 25px;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.faq-answer p {
    color: var(--gray-600);
    padding: 0 0 20px;
    margin: 0;
    line-height: 1.8;
}

/* ============================================
   SPINNER
============================================ */
.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(10,25,41,0.1);
    border-radius: 50%;
    border-top-color: var(--navy);
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ============================================
   RESPONSIVE DESIGN
============================================ */
@media (max-width: 991px) {
    .tier-card.recommended {
        transform: scale(1);
    }
    
    .tier-card.recommended:hover {
        transform: translateY(-10px);
    }
}

@media (max-width: 767px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .form-card {
        padding: 30px;
    }
    
    .benefits-grid {
        grid-template-columns: 1fr;
    }
    
    .toast-container {
        left: 20px;
        right: 20px;
    }
    
    .toast {
        min-width: auto;
        width: 100%;
    }
    
    .faq-question h4 {
        font-size: 16px;
    }
}

@media (max-width: 575px) {
    .sponsor-hero {
        padding: 60px 0;
    }
    
    .form-card {
        padding: 20px;
    }
    
    .tier-card {
        padding: 30px 20px;
    }
    
    .tier-price {
        font-size: 1.8rem;
    }
}
</style>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<!-- Hero Section -->
<section class="sponsor-hero">
    <div class="container">
        <div class="sponsor-hero-content">
            <h1><?php echo $page ? htmlspecialchars($page['page_title']) : 'Partner With Us'; ?></h1>
            <p><?php echo $page ? htmlspecialchars(truncateText(strip_tags($page['page_content']), 150)) : 'Join our mission to empower the next generation of African talent and drive sustainable economic growth.'; ?></p>
        </div>
    </div>
</section>

<!-- Why Sponsor Section -->
<section class="section" style="padding: 80px 0;">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Why Partner With Us</span>
            <h2>Strategic Partnership <span>Opportunities</span></h2>
            <p>Forward-thinking organizations choose AGPN for meaningful impact and measurable results</p>
        </div>
        
        <div class="benefits-grid">
            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="fas fa-globe-africa"></i>
                </div>
                <h3>Impact at Scale</h3>
                <p>Reach thousands of talented individuals and businesses across Africa, creating lasting social and economic impact.</p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="fas fa-bullseye"></i>
                </div>
                <h3>Targeted Reach</h3>
                <p>Connect with your ideal audience - from digital professionals to aspiring students and growing SMEs.</p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="fas fa-medal"></i>
                </div>
                <h3>Brand Excellence</h3>
                <p>Associate your brand with innovation, education, and economic development in Africa's fastest-growing markets.</p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="fas fa-chart-network"></i>
                </div>
                <h3>Strategic Network</h3>
                <p>Access our extensive network of partners, alumni, and industry leaders across multiple sectors.</p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <h3>CSR Alignment</h3>
                <p>Perfectly aligned with corporate social responsibility goals focused on education, youth empowerment, and digital inclusion.</p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h3>Future-Ready</h3>
                <p>Invest in the skills and talent that will drive the future of work and economic growth.</p>
            </div>
        </div>
    </div>
</section>

<!-- Sponsorship Tiers -->
<section class="section" style="background: var(--gray-100); padding: 80px 0;">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Investment Levels</span>
            <h2>Sponsorship <span>Tiers</span></h2>
            <p>Choose the partnership level that aligns with your goals and budget</p>
        </div>
        
        <div class="tiers-grid">
            <!-- Bronze Tier -->
            <div class="tier-card">
                <div class="tier-badge popular">POPULAR</div>
                <h3 class="tier-name bronze">Bronze</h3>
                <div class="tier-price">$5,000+ <span>/ year</span></div>
                <ul class="tier-features">
                    <li><i class="fas fa-check"></i> Logo on website</li>
                    <li><i class="fas fa-check"></i> Social media mention</li>
                    <li><i class="fas fa-check"></i> Quarterly impact report</li>
                    <li><i class="fas fa-check"></i> 2 event tickets</li>
                    <li><i class="fas fa-times"></i> Speaking opportunity</li>
                    <li><i class="fas fa-times"></i> Exclusive networking</li>
                </ul>
                <a href="#sponsor-form" class="btn-tier btn-tier-outline">Select Tier</a>
            </div>
            
            <!-- Silver Tier - Recommended -->
            <div class="tier-card recommended">
                <div class="tier-badge recommended">RECOMMENDED</div>
                <h3 class="tier-name silver">Silver</h3>
                <div class="tier-price">$15,000+ <span>/ year</span></div>
                <ul class="tier-features">
                    <li><i class="fas fa-check"></i> All Bronze benefits</li>
                    <li><i class="fas fa-check"></i> Premium logo placement</li>
                    <li><i class="fas fa-check"></i> Dedicated blog post</li>
                    <li><i class="fas fa-check"></i> 5 event tickets</li>
                    <li><i class="fas fa-check"></i> Panel speaking slot</li>
                    <li><i class="fas fa-times"></i> Keynote opportunity</li>
                </ul>
                <a href="#sponsor-form" class="btn-tier btn-tier-primary">Select Tier</a>
            </div>
            
            <!-- Gold Tier -->
            <div class="tier-card">
                <h3 class="tier-name gold">Gold</h3>
                <div class="tier-price">$30,000+ <span>/ year</span></div>
                <ul class="tier-features">
                    <li><i class="fas fa-check"></i> All Silver benefits</li>
                    <li><i class="fas fa-check"></i> Title sponsorship</li>
                    <li><i class="fas fa-check"></i> Custom research report</li>
                    <li><i class="fas fa-check"></i> 10 event tickets</li>
                    <li><i class="fas fa-check"></i> Keynote speaking</li>
                    <li><i class="fas fa-check"></i> Board observer seat</li>
                </ul>
                <a href="#sponsor-form" class="btn-tier btn-tier-outline">Select Tier</a>
            </div>
        </div>
    </div>
</section>

<!-- Sponsor Form Section -->
<section id="sponsor-form" class="form-section">
    <div class="container">
        <div class="form-card">
            <h3>Become a <span>Sponsor</span></h3>
            <p>Fill out the form below and our partnerships team will contact you within 24 hours.</p>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Inquiry Sent Successfully!</strong>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Error!</strong>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <form action="forms/sponsor-handler.php" method="POST" id="sponsorForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="organization">Organization Name <span>*</span></label>
                        <input type="text" 
                               id="organization" 
                               name="organization" 
                               class="form-control" 
                               value="<?php echo isset($form_data['organization']) ? htmlspecialchars($form_data['organization']) : ''; ?>"
                               placeholder="Your Company Name" 
                               required
                               onkeyup="validateField(this)">
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_name">Contact Person <span>*</span></label>
                        <input type="text" 
                               id="contact_name" 
                               name="contact_name" 
                               class="form-control" 
                               value="<?php echo isset($form_data['contact_name']) ? htmlspecialchars($form_data['contact_name']) : ''; ?>"
                               placeholder="Full Name" 
                               required
                               onkeyup="validateField(this)">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address <span>*</span></label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               value="<?php echo isset($form_data['email']) ? htmlspecialchars($form_data['email']) : ''; ?>"
                               placeholder="contact@company.com" 
                               required
                               onkeyup="validateEmail(this)">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               class="form-control" 
                               value="<?php echo isset($form_data['phone']) ? htmlspecialchars($form_data['phone']) : ''; ?>"
                               placeholder="+234 800 123 4567"
                               onkeyup="validateField(this)">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="tier">Sponsorship Tier</label>
                    <select id="tier" name="tier" class="form-control">
                        <option value="bronze" <?php echo (isset($form_data['tier']) && $form_data['tier'] == 'bronze') ? 'selected' : ''; ?>>Bronze ($5,000+/year)</option>
                        <option value="silver" <?php echo (isset($form_data['tier']) && $form_data['tier'] == 'silver') ? 'selected' : ''; ?>>Silver ($15,000+/year)</option>
                        <option value="gold" <?php echo (isset($form_data['tier']) && $form_data['tier'] == 'gold') ? 'selected' : ''; ?>>Gold ($30,000+/year)</option>
                        <option value="custom" <?php echo (isset($form_data['tier']) && $form_data['tier'] == 'custom') ? 'selected' : ''; ?>>Custom Partnership</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="message">Message / Specific Interests</label>
                    <textarea id="message" 
                              name="message" 
                              class="form-control" 
                              rows="4"
                              placeholder="Tell us about your sponsorship goals and interests..."><?php echo isset($form_data['message']) ? htmlspecialchars($form_data['message']) : ''; ?></textarea>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-handshake"></i>
                    Submit Sponsorship Inquiry
                </button>
            </form>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">FAQ</span>
            <h2>Frequently Asked <span>Questions</span></h2>
            <p>Common questions about sponsorship opportunities</p>
        </div>
        
        <div class="faq-grid">
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <h4>How are sponsorship funds used?</h4>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Sponsorship funds directly support our core programs: digital skills training, study abroad scholarships, business development initiatives, and operational costs that ensure program quality and reach.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <h4>Can we customize a sponsorship package?</h4>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes! We work with each sponsor to create customized partnership packages that align with your specific goals, target audience, and budget. Select "Custom Partnership" in the form and we'll discuss options.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <h4>What reporting do sponsors receive?</h4>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Sponsors receive quarterly impact reports detailing program outcomes, participant success stories, financial transparency, and engagement metrics. Annual reports include comprehensive impact analysis.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <h4>Are sponsorships tax-deductible?</h4>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>AGPN is a registered organization. Sponsorships may be tax-deductible depending on your jurisdiction. Please consult your tax advisor for specific guidance.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// ============================================
// SPONSORSHIP PAGE - COMPLETE JAVASCRIPT
// Features: Toast Notifications, Form Validation,
// FAQ Accordion, Navbar Toggle, Smooth Scroll
// ============================================

(function() {
    'use strict';

    // ============================================
    // 1. TOAST NOTIFICATION SYSTEM
    // ============================================
    const ToastManager = {
        container: null,
        
        init: function() {
            this.container = document.getElementById('toastContainer');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toastContainer';
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        },
        
        show: function(title, message, type = 'success', duration = 5000) {
            if (!this.container) this.init();
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // Set icon based on type
            let icon = 'check-circle';
            if (type === 'error') icon = 'exclamation-circle';
            if (type === 'warning') icon = 'exclamation-triangle';
            if (type === 'info') icon = 'info-circle';
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.closest('.toast').remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            this.container.appendChild(toast);
            
            // Auto remove after duration
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => {
                        if (toast.parentNode) toast.remove();
                    }, 300);
                }
            }, duration);
        },
        
        success: function(title, message, duration) {
            this.show(title, message, 'success', duration);
        },
        
        error: function(title, message, duration) {
            this.show(title, message, 'error', duration);
        },
        
        warning: function(title, message, duration) {
            this.show(title, message, 'warning', duration);
        },
        
        info: function(title, message, duration) {
            this.show(title, message, 'info', duration);
        }
    };

    // ============================================
    // 2. FORM VALIDATION UTILITIES
    // ============================================
    const FormValidator = {
        validateRequired: function(field) {
            const isValid = field.value.trim() !== '';
            this.toggleFieldError(field, !isValid);
            return isValid;
        },
        
        validateEmail: function(field) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isValid = emailRegex.test(field.value.trim());
            this.toggleFieldError(field, !isValid && field.value.trim() !== '');
            return isValid;
        },
        
        validatePhone: function(field) {
            // Basic phone validation - allows +, numbers, spaces, and hyphens
            const phoneRegex = /^[\d\s\+\-\(\)]{10,}$/;
            const isValid = field.value.trim() === '' || phoneRegex.test(field.value.trim());
            this.toggleFieldError(field, !isValid);
            return isValid;
        },
        
        toggleFieldError: function(field, showError) {
            if (showError) {
                field.classList.add('error');
            } else {
                field.classList.remove('error');
            }
        },
        
        clearErrors: function() {
            document.querySelectorAll('.form-control.error').forEach(field => {
                field.classList.remove('error');
            });
        },
        
        getFirstErrorField: function() {
            return document.querySelector('.form-control.error');
        }
    };

    // ============================================
    // 3. FAQ ACCORDION
    // ============================================
    const FAQAccordion = {
        init: function() {
            const faqQuestions = document.querySelectorAll('.faq-question');
            
            faqQuestions.forEach(question => {
                question.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggle(question);
                });
            });
        },
        
        toggle: function(questionElement) {
            const faqItem = questionElement.closest('.faq-item');
            const answer = faqItem.querySelector('.faq-answer');
            const icon = questionElement.querySelector('i');
            
            if (answer.style.maxHeight) {
                // Close this FAQ
                answer.style.maxHeight = null;
                icon.style.transform = 'rotate(0deg)';
            } else {
                // Close all other FAQs
                document.querySelectorAll('.faq-item').forEach(item => {
                    if (item !== faqItem) {
                        const otherAnswer = item.querySelector('.faq-answer');
                        const otherIcon = item.querySelector('.faq-question i');
                        if (otherAnswer.style.maxHeight) {
                            otherAnswer.style.maxHeight = null;
                            otherIcon.style.transform = 'rotate(0deg)';
                        }
                    }
                });
                
                // Open this FAQ
                answer.style.maxHeight = answer.scrollHeight + 'px';
                icon.style.transform = 'rotate(180deg)';
            }
        }
    };

    // ============================================
    // 4. NAVBAR TOGGLE
    // ============================================
    const NavbarToggle = {
        init: function() {
            this.toggle = document.getElementById('navToggle');
            this.menu = document.getElementById('navMenu');
            
            if (this.toggle && this.menu) {
                this.toggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleMenu();
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', (e) => {
                    if (!this.menu.contains(e.target) && !this.toggle.contains(e.target)) {
                        this.closeMenu();
                    }
                });
                
                // Close menu on window resize (if desktop)
                window.addEventListener('resize', () => {
                    if (window.innerWidth > 768) {
                        this.closeMenu();
                    }
                });
            }
        },
        
        toggleMenu: function() {
            this.menu.classList.toggle('active');
            this.toggle.classList.toggle('active');
            this.toggle.setAttribute('aria-expanded', this.menu.classList.contains('active'));
        },
        
        closeMenu: function() {
            this.menu.classList.remove('active');
            this.toggle.classList.remove('active');
            this.toggle.setAttribute('aria-expanded', 'false');
        }
    };

    // ============================================
    // 5. SPONSOR FORM HANDLER
    // ============================================
    const SponsorForm = {
        form: null,
        submitBtn: null,
        
        init: function() {
            this.form = document.getElementById('sponsorForm');
            if (!this.form) return;
            
            this.submitBtn = document.getElementById('submitBtn');
            
            // Add real-time validation listeners
            this.addValidationListeners();
            
            // Handle form submission
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        },
        
        addValidationListeners: function() {
            const organization = document.getElementById('organization');
            const contactName = document.getElementById('contact_name');
            const email = document.getElementById('email');
            const phone = document.getElementById('phone');
            
            if (organization) {
                organization.addEventListener('keyup', () => FormValidator.validateRequired(organization));
                organization.addEventListener('blur', () => FormValidator.validateRequired(organization));
            }
            
            if (contactName) {
                contactName.addEventListener('keyup', () => FormValidator.validateRequired(contactName));
                contactName.addEventListener('blur', () => FormValidator.validateRequired(contactName));
            }
            
            if (email) {
                email.addEventListener('keyup', () => FormValidator.validateEmail(email));
                email.addEventListener('blur', () => FormValidator.validateEmail(email));
            }
            
            if (phone) {
                phone.addEventListener('keyup', () => FormValidator.validatePhone(phone));
                phone.addEventListener('blur', () => FormValidator.validatePhone(phone));
            }
        },
        
        validateForm: function() {
            FormValidator.clearErrors();
            
            const organization = document.getElementById('organization');
            const contactName = document.getElementById('contact_name');
            const email = document.getElementById('email');
            const phone = document.getElementById('phone');
            
            let isValid = true;
            let errorMessage = '';
            
            // Validate required fields
            if (!FormValidator.validateRequired(organization)) {
                isValid = false;
                errorMessage = 'Please enter organization name';
            }
            
            if (!FormValidator.validateRequired(contactName)) {
                isValid = false;
                errorMessage = errorMessage || 'Please enter contact name';
            }
            
            if (!FormValidator.validateEmail(email)) {
                isValid = false;
                errorMessage = errorMessage || 'Please enter a valid email address';
            }
            
            // Validate phone if provided
            if (phone.value.trim() && !FormValidator.validatePhone(phone)) {
                isValid = false;
                errorMessage = errorMessage || 'Please enter a valid phone number';
            }
            
            return { isValid, errorMessage };
        },
        
        handleSubmit: function(e) {
            e.preventDefault();
            
            const { isValid, errorMessage } = this.validateForm();
            
            if (!isValid) {
                ToastManager.error('Validation Error', errorMessage);
                
                // Scroll to first error
                const firstError = FormValidator.getFirstErrorField();
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                return;
            }
            
            // Show loading state
            this.setLoadingState(true);
            
            // Submit the form
            this.form.submit();
        },
        
        setLoadingState: function(isLoading) {
            if (!this.submitBtn) return;
            
            if (isLoading) {
                this.submitBtn.disabled = true;
                this.submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';
            } else {
                this.submitBtn.disabled = false;
                this.submitBtn.innerHTML = '<i class="fas fa-handshake"></i> Submit Sponsorship Inquiry';
            }
        }
    };

    // ============================================
    // 6. SCROLL TO FORM
    // ============================================
    const ScrollToForm = {
        init: function() {
            const tierButtons = document.querySelectorAll('.btn-tier');
            
            tierButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetId = button.getAttribute('href');
                    if (targetId && targetId === '#sponsor-form') {
                        this.scrollToForm(targetId);
                        
                        // Pre-select tier based on button context
                        const tierCard = button.closest('.tier-card');
                        if (tierCard) {
                            this.selectTier(tierCard);
                        }
                    }
                });
            });
        },
        
        scrollToForm: function(targetId) {
            const targetElement = document.querySelector(targetId);
            if (!targetElement) return;
            
            const headerHeight = document.querySelector('.site-header')?.offsetHeight || 0;
            const targetPosition = targetElement.offsetTop - headerHeight - 20;
            
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
            
            // Highlight form
            targetElement.classList.add('highlight');
            setTimeout(() => {
                targetElement.classList.remove('highlight');
            }, 2000);
        },
        
        selectTier: function(tierCard) {
            const tierName = tierCard.querySelector('.tier-name')?.textContent?.toLowerCase().trim();
            const tierSelect = document.getElementById('tier');
            
            if (tierSelect && tierName) {
                // Map tier names to select values
                const tierMap = {
                    'bronze': 'bronze',
                    'silver': 'silver',
                    'gold': 'gold'
                };
                
                const selectValue = tierMap[tierName];
                if (selectValue) {
                    tierSelect.value = selectValue;
                    
                    // Highlight the select
                    tierSelect.style.transition = 'border-color 0.3s ease';
                    tierSelect.style.borderColor = '#FFB81C';
                    setTimeout(() => {
                        tierSelect.style.borderColor = '';
                    }, 2000);
                }
            }
        }
    };

    // ============================================
    // 7. SESSION MESSAGE HANDLER
    // ============================================
    const SessionMessages = {
        init: function() {
            <?php if ($success): ?>
            ToastManager.success(
                'Success!', 
                '<?php echo addslashes($success); ?>'
            );
            <?php endif; ?>
            
            <?php if ($error): ?>
            ToastManager.error(
                'Error!', 
                '<?php echo addslashes($error); ?>'
            );
            <?php endif; ?>
        }
    };

    // ============================================
    // 8. ANIMATIONS AND EFFECTS
    // ============================================
    const PageAnimations = {
        init: function() {
            this.animateCards();
            this.addScrollEffects();
        },
        
        animateCards: function() {
            const cards = document.querySelectorAll('.benefit-card, .tier-card');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
            
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        },
        
        addScrollEffects: function() {
            // Add smooth scroll for all anchor links
            document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach(anchor => {
                anchor.addEventListener('click', (e) => {
                    const targetId = anchor.getAttribute('href');
                    const targetElement = document.querySelector(targetId);
                    
                    if (targetElement) {
                        e.preventDefault();
                        
                        const headerHeight = document.querySelector('.site-header')?.offsetHeight || 0;
                        const targetPosition = targetElement.offsetTop - headerHeight - 20;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                    }
                });
            });
        }
    };

    // ============================================
    // 9. INITIALIZE ALL MODULES
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Sponsorship page initialized');
        
        // Initialize all modules
        ToastManager.init();
        NavbarToggle.init();
        FAQAccordion.init();
        SponsorForm.init();
        ScrollToForm.init();
        SessionMessages.init();
        PageAnimations.init();
        
        // Add animation styles if not present
        if (!document.getElementById('animation-styles')) {
            const style = document.createElement('style');
            style.id = 'animation-styles';
            style.textContent = `
                @keyframes slideOut {
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
                
                .form-card.highlight {
                    animation: highlightPulse 2s ease;
                }
                
                @keyframes highlightPulse {
                    0%, 100% { box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
                    50% { box-shadow: 0 20px 40px rgba(255,184,28,0.3); }
                }
            `;
            document.head.appendChild(style);
        }
    });

    // ============================================
    // 10. WINDOW LOAD HANDLER
    // ============================================
    window.addEventListener('load', function() {
        console.log('Sponsorship page fully loaded');
        
        // Check URL hash on load
        if (window.location.hash === '#sponsor-form') {
            setTimeout(() => {
                const form = document.getElementById('sponsor-form');
                if (form) {
                    const headerHeight = document.querySelector('.site-header')?.offsetHeight || 0;
                    const targetPosition = form.offsetTop - headerHeight - 20;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            }, 500);
        }
    });

})();
</script>

<!-- Add this CSS for animations (if not already in your main stylesheet) -->
<style>
/* Toast Notification Animations */
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* Form Highlight Animation */
@keyframes highlightPulse {
    0%, 100% { 
        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        border-color: #e9ecef;
    }
    50% { 
        box-shadow: 0 20px 40px rgba(255,184,28,0.3);
        border-color: #FFB81C;
    }
}

.highlight {
    animation: highlightPulse 2s ease;
}

/* Loading Spinner */
.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(10,25,41,0.1);
    border-radius: 50%;
    border-top-color: #0A1929;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Toast Container Positioning */
.toast-container {
    position: fixed;
    top: 30px;
    right: 30px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
}

.toast {
    pointer-events: auto;
}

/* Mobile Responsive Toast */
@media (max-width: 767px) {
    .toast-container {
        top: 20px;
        right: 20px;
        left: 20px;
    }
    
    .toast {
        width: 100%;
        max-width: none;
    }
}

/* Form Field Validation */
.form-control.error {
    border-color: #dc3545 !important;
    background-color: #fff5f5;
}

.form-control.error:focus {
    box-shadow: 0 0 0 4px rgba(220,53,69,0.1);
}

/* FAQ Accordion Transitions */
.faq-answer {
    transition: max-height 0.3s ease;
    overflow: hidden;
}

.faq-question i {
    transition: transform 0.3s ease;
}

/* Card Hover Effects */
.benefit-card,
.tier-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}

/* Navbar Mobile Menu */
@media (max-width: 768px) {
    #navMenu.active {
        display: block !important;
        position: absolute;
        top: 70px;
        left: 0;
        right: 0;
        background: #ffffff;
        padding: 20px;
        box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        z-index: 1000;
    }
    
    #navMenu.active ul {
        flex-direction: column;
        gap: 10px;
    }
}
</style>
<?php
include 'includes/footer.php';
?>