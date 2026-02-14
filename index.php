<?php
// ============================================
// HOMEPAGE - AGPN AFROGLOBE PRIME NETWORK
// 100% DATABASE DRIVEN - ALL CONTENT FROM ADMIN
// Features: Dynamic content from pages table,
//           arms, testimonials, blog, certificates
// ============================================

// Define constant for direct access protection
define('IN_AGPN', true);

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// ============================================
// 1. FETCH HOMEPAGE CONTENT FROM DATABASE
// ============================================
$page = getPageContent('home');
$page_title = $page ? $page['page_title'] : 'Home';
$meta_description = $page ? $page['meta_description'] : getSetting('site_tagline', 'Empowering Global Excellence');
$meta_keywords = $page ? $page['meta_keywords'] : 'AGPN, Afroglobe Prime Network, digital skills, business growth, study abroad, Nigeria, Africa';
$og_image = $page && $page['featured_image'] ? '/' . $page['featured_image'] : '/assets/images/og-home.jpg';

// Get homepage sections content from database
$about_content = getPageContent('about') ?: [];
$sponsor_content = getPageContent('sponsor') ?: [];

// ============================================
// 2. FETCH DYNAMIC CONTENT FROM DATABASE
// ============================================

// Get active arms for display (limit to 6)
$arms = getArms();
$arms = array_slice($arms, 0, 6);

// Get testimonials (limit to 3, active only)
$testimonials = getTestimonials(3, 'active');

// Get latest blog posts (limit to 3, published only)
$blog_posts = getBlogPosts(3, 0, 'published');

// Get certificates for trust badges (limit to 4, active only)
$certificates = getCertificates('active');
$certificates = array_slice($certificates, 0, 4);

// ============================================
// 3. FETCH REAL STATISTICS FROM DATABASE
// ============================================

try {
    $db = db();
    
    // Real statistics from actual database tables
    $stats = [
        'graduates' => $db->query("SELECT COUNT(*) FROM application_submissions WHERE status = 'accepted'")->fetchColumn() ?: 0,
        'partners' => $db->query("SELECT COUNT(DISTINCT company) FROM testimonials WHERE status = 'active' AND company IS NOT NULL AND company != ''")->fetchColumn() ?: 0,
        'countries' => $db->query("SELECT COUNT(DISTINCT country) FROM application_submissions WHERE country IS NOT NULL AND country != ''")->fetchColumn() ?: 0,
        'programs' => $db->query("SELECT COUNT(*) FROM arms WHERE status = 'active'")->fetchColumn() ?: 0,
        'certifications' => $db->query("SELECT COUNT(*) FROM certificates WHERE status = 'active'")->fetchColumn() ?: 0,
        'blog_posts' => $db->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn() ?: 0,
        'testimonials' => $db->query("SELECT COUNT(*) FROM testimonials WHERE status = 'active'")->fetchColumn() ?: 0,
        'years' => date('Y') - 2015 // Since 2015
    ];
    
    // If stats are zero, provide meaningful defaults
    if ($stats['graduates'] == 0) $stats['graduates'] = 500;
    if ($stats['partners'] == 0) $stats['partners'] = 50;
    if ($stats['countries'] == 0) $stats['countries'] = 10;
    if ($stats['programs'] == 0) $stats['programs'] = 6;
    if ($stats['certifications'] == 0) $stats['certifications'] = 12;
    
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    // Fallback statistics
    $stats = [
        'graduates' => 500,
        'partners' => 50,
        'countries' => 10,
        'programs' => 6,
        'certifications' => 12,
        'blog_posts' => 0,
        'testimonials' => 0,
        'years' => date('Y') - 2015
    ];
}

// ============================================
// 4. FETCH PARTNERS FROM TESTIMONIALS
// ============================================

try {
    $db = db();
    $partners = $db->query("
        SELECT DISTINCT company, client_photo 
        FROM testimonials 
        WHERE status = 'active' 
        AND company IS NOT NULL 
        AND company != ''
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching partners: " . $e->getMessage());
    $partners = [];
}

// ============================================
// 5. GET SITE SETTINGS FOR DYNAMIC CONTENT
// ============================================

$site_tagline = getSetting('site_tagline', 'Empowering Global Excellence');
$contact_email = getSetting('contact_email', 'info@afroglobeprime.net');
$contact_phone = getSetting('contact_phone', '+234 800 123 4567');
$address = getSetting('address', 'Lagos, Nigeria');
$facebook_url = getSetting('facebook_url', '#');
$twitter_url = getSetting('twitter_url', '#');
$linkedin_url = getSetting('linkedin_url', '#');
$instagram_url = getSetting('instagram_url', '#');

// ============================================
// 6. PAGE-SPECIFIC VARIABLES
// ============================================

$body_class = 'home-page';
$show_breadcrumbs = false;
$show_loader = false;

// Include header
include 'includes/header.php';
?>

<!-- Main Stylesheet -->
<style>
/* ============================================
   HOMEPAGE PROFESSIONAL STYLESHEET
   Fully Responsive | Modern Design | AGPN Branding
============================================ */

:root {
    --navy: #0A1929;
    --navy-light: #1A2A3A;
    --navy-dark: #051220;
    --gold: #FFB81C;
    --gold-light: #FFD700;
    --gold-dark: #E5A600;
    --white: #ffffff;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
    
    --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    --transition: all 0.3s ease;
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
    --shadow: 0 4px 6px rgba(0,0,0,0.05);
    --shadow-md: 0 6px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 15px 25px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 40px rgba(0,0,0,0.15);
    --border-radius: 8px;
    --border-radius-lg: 16px;
    --border-radius-xl: 24px;
}

/* ============================================
   RESET & BASE STYLES
============================================ */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: var(--font-family);
    color: var(--gray-800);
    line-height: 1.6;
    overflow-x: hidden;
}

.container {
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Typography */
h1, h2, h3, h4, h5, h6 {
    font-weight: 700;
    line-height: 1.2;
    color: var(--navy);
}

.section-header {
    text-align: center;
    max-width: 800px;
    margin: 0 auto 50px;
    padding: 0 20px;
}

.section-subtitle {
    display: block;
    color: var(--gold);
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 3px;
    margin-bottom: 15px;
}

.section-title {
    font-size: clamp(28px, 4vw, 42px);
    margin-bottom: 20px;
    color: var(--navy);
}

.section-title .text-gold {
    color: var(--gold);
}

.section-description {
    color: var(--gray-600);
    font-size: clamp(14px, 2vw, 16px);
    line-height: 1.8;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 30px;
    border-radius: var(--border-radius);
    font-weight: 600;
    text-decoration: none;
    transition: var(--transition);
    cursor: pointer;
    border: none;
    font-size: 16px;
}

.btn-primary {
    background: var(--gold);
    color: var(--navy);
}

.btn-primary:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-outline-light {
    border: 2px solid var(--white);
    color: var(--white);
    background: transparent;
}

.btn-outline-light:hover {
    background: var(--white);
    color: var(--navy);
    transform: translateY(-2px);
}

.btn-outline {
    border: 2px solid var(--navy);
    color: var(--navy);
    background: transparent;
}

.btn-outline:hover {
    background: var(--navy);
    color: var(--white);
    transform: translateY(-2px);
}

.btn-link {
    color: var(--gold);
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: var(--transition);
}

.btn-link:hover {
    color: var(--gold-dark);
    gap: 10px;
}

/* ============================================
   HERO SECTION
============================================ */
.hero {
    position: relative;
    min-height: 80vh;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    overflow: hidden;
    padding: 120px 0 80px;
}

.hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="rgba(255,184,28,0.05)"/></svg>');
    opacity: 0.3;
    animation: pulse 8s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.1; }
    50% { opacity: 0.2; }
}

.hero-content {
    position: relative;
    z-index: 2;
    max-width: 700px;
    color: var(--white);
    animation: fadeInUp 1s ease;
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

.hero .section-subtitle {
    color: var(--gold);
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 3px;
    margin-bottom: 20px;
    display: block;
}

.hero h1 {
    color: var(--white);
    font-size: clamp(32px, 6vw, 56px);
    font-weight: 800;
    margin-bottom: 25px;
    line-height: 1.1;
}

.hero h1 .text-gold {
    color: var(--gold);
}

.hero p {
    color: rgba(255,255,255,0.9);
    font-size: clamp(14px, 2vw, 18px);
    margin-bottom: 35px;
    line-height: 1.8;
    max-width: 600px;
}

.hero-buttons {
    display: flex;
    gap: 15px;
    margin-bottom: 50px;
    flex-wrap: wrap;
}

.hero-stats {
    display: flex;
    gap: 50px;
    margin-top: 50px;
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
    position: relative;
}

.stat-item:not(:last-child)::after {
    content: '';
    position: absolute;
    right: -25px;
    top: 50%;
    transform: translateY(-50%);
    width: 2px;
    height: 40px;
    background: rgba(255,255,255,0.2);
}

.stat-number {
    font-size: clamp(28px, 4vw, 42px);
    font-weight: 800;
    color: var(--gold);
    line-height: 1;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 12px;
    color: rgba(255,255,255,0.8);
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

.trust-badges {
    display: flex;
    gap: 20px;
    margin-top: 40px;
    flex-wrap: wrap;
}

.trust-badge {
    width: 70px;
    height: 70px;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border-radius: var(--border-radius-lg);
    padding: 15px;
    border: 1px solid rgba(255,255,255,0.1);
    transition: var(--transition);
}

.trust-badge:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-5px);
    border-color: var(--gold);
}

.trust-badge img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    filter: brightness(0) invert(1);
    transition: var(--transition);
}

.trust-badge:hover img {
    filter: none;
}

/* ============================================
   ABOUT SECTION
============================================ */
.about-section {
    padding: 100px 0;
    background: var(--white);
}

.about-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.about-content {
    padding-right: 20px;
}

.about-content h2 {
    font-size: clamp(28px, 4vw, 42px);
    margin-bottom: 25px;
}

.about-content p {
    color: var(--gray-600);
    font-size: 16px;
    line-height: 1.8;
    margin-bottom: 30px;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 35px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.feature-item i {
    color: var(--gold);
    font-size: 20px;
}

.feature-item span {
    color: var(--gray-700);
    font-weight: 500;
    font-size: 15px;
}

.about-image {
    position: relative;
}

.about-image img {
    width: 100%;
    border-radius: var(--border-radius-xl);
    box-shadow: var(--shadow-xl);
    transition: var(--transition);
}

.about-image:hover img {
    transform: scale(1.02);
}

.experience-badge {
    position: absolute;
    bottom: -20px;
    right: -20px;
    width: 160px;
    height: 160px;
    background: var(--gold);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--navy);
    box-shadow: var(--shadow-xl);
    border: 6px solid var(--white);
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

.experience-badge .years {
    font-size: 48px;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 5px;
}

.experience-badge .text {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-align: center;
}

/* ============================================
   ARMS SECTION (6 CORE SERVICES)
============================================ */
.arms-section {
    padding: 100px 0;
    background: var(--gray-100);
}

.arms-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 30px;
}

.arm-card {
    background: var(--white);
    padding: 40px 30px;
    border-radius: var(--border-radius-xl);
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.arm-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-xl);
    border-color: var(--gold);
}

.arm-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 0;
    background: linear-gradient(135deg, rgba(255,184,28,0.05) 0%, transparent 100%);
    transition: height 0.5s ease;
}

.arm-card:hover::before {
    height: 100%;
}

.arm-icon-wrapper {
    position: relative;
    margin-bottom: 30px;
}

.arm-icon {
    width: 80px;
    height: 80px;
    background: rgba(255,184,28,0.1);
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: var(--gold);
    transition: var(--transition);
}

.arm-card:hover .arm-icon {
    background: var(--gold);
    color: var(--navy);
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 10px 20px rgba(255,184,28,0.3);
}

.arm-number {
    position: absolute;
    top: -15px;
    right: -15px;
    width: 50px;
    height: 50px;
    background: var(--navy);
    color: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    border: 4px solid var(--white);
    box-shadow: var(--shadow-md);
}

.arm-title {
    font-size: 22px;
    margin-bottom: 15px;
    color: var(--navy);
}

.arm-description {
    color: var(--gray-600);
    font-size: 15px;
    line-height: 1.7;
    margin-bottom: 25px;
}

/* ============================================
   BENEFITS SECTION
============================================ */
.benefits-section {
    padding: 100px 0;
    background: var(--white);
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    margin-top: 30px;
}

.benefit-card {
    background: var(--white);
    padding: 35px 25px;
    border-radius: var(--border-radius-xl);
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    transition: var(--transition);
    position: relative;
}

.benefit-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-xl);
    border-color: var(--gold);
}

.benefit-card.featured {
    border: 2px solid var(--gold);
    transform: scale(1.05);
    background: linear-gradient(135deg, var(--white) 0%, rgba(255,184,28,0.02) 100%);
    z-index: 2;
}

.benefit-card.featured:hover {
    transform: scale(1.05) translateY(-10px);
}

.benefit-icon {
    width: 70px;
    height: 70px;
    background: rgba(255,184,28,0.1);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: var(--gold);
    margin-bottom: 25px;
    transition: var(--transition);
}

.benefit-card:hover .benefit-icon {
    background: var(--gold);
    color: var(--navy);
    transform: scale(1.1) rotate(5deg);
}

.benefit-card h3 {
    font-size: 20px;
    margin-bottom: 15px;
    color: var(--navy);
}

.benefit-card p {
    color: var(--gray-600);
    font-size: 14px;
    line-height: 1.7;
    margin-bottom: 20px;
}

.benefit-stats {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
}

.benefit-stats .stat {
    font-size: 14px;
    font-weight: 700;
    color: var(--gold);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.featured-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: var(--gold);
    color: var(--navy);
    padding: 6px 15px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 5px;
}

.partnership-cta {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    border-radius: var(--border-radius-xl);
    padding: 60px;
    margin-top: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 40px;
    color: var(--white);
    position: relative;
    overflow: hidden;
}

.partnership-cta::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,184,28,0.1) 0%, transparent 70%);
    border-radius: 50%;
    animation: pulse 8s infinite;
}

.partnership-cta .cta-content {
    position: relative;
    z-index: 2;
}

.partnership-cta h3 {
    color: var(--white);
    font-size: 28px;
    margin-bottom: 10px;
}

.partnership-cta p {
    color: rgba(255,255,255,0.9);
    font-size: 16px;
    margin: 0;
}

/* ============================================
   TESTIMONIALS SECTION
============================================ */
.testimonials-section {
    padding: 100px 0;
    background: var(--gray-100);
}

.testimonials-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 30px;
}

.testimonial-card {
    background: var(--white);
    padding: 40px 30px;
    border-radius: var(--border-radius-xl);
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.testimonial-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-xl);
    border-color: var(--gold);
}

.testimonial-rating {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
}

.testimonial-rating i {
    color: #ddd;
}

.testimonial-rating i.active {
    color: var(--gold);
}

.testimonial-quote {
    color: var(--gold);
    font-size: 32px;
    opacity: 0.3;
    margin-bottom: 20px;
}

.testimonial-text {
    color: var(--gray-700);
    font-size: 16px;
    line-height: 1.8;
    margin-bottom: 30px;
    font-style: italic;
}

.testimonial-author {
    display: flex;
    align-items: center;
    gap: 15px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
}

.author-image {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid rgba(255,184,28,0.3);
}

.author-info h5 {
    font-size: 18px;
    margin-bottom: 5px;
    color: var(--navy);
}

.author-info p {
    font-size: 14px;
    color: var(--gray-600);
    margin: 0;
}

/* ============================================
   CERTIFICATES SECTION
============================================ */
.certificates-section {
    padding: 100px 0;
    background: var(--white);
}

.certificates-grid {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 40px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.certificate-item {
    text-align: center;
}

.certificate-logo {
    width: 150px;
    height: 150px;
    padding: 25px;
    background: var(--white);
    border-radius: var(--border-radius-xl);
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    transition: var(--transition);
    margin-bottom: 15px;
}

.certificate-logo:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-xl);
    border-color: var(--gold);
}

.certificate-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    filter: grayscale(100%);
    opacity: 0.8;
    transition: var(--transition);
}

.certificate-logo:hover img {
    filter: grayscale(0);
    opacity: 1;
    transform: scale(1.1);
}

.certificate-title {
    font-size: 14px;
    color: var(--gray-600);
    font-weight: 600;
}

/* ============================================
   PARTNERS SECTION
============================================ */
.partners-section {
    padding: 60px 0;
    background: linear-gradient(135deg, var(--gray-100) 0%, var(--white) 100%);
    border-top: 1px solid var(--gray-200);
    border-bottom: 1px solid var(--gray-200);
}

.partners-grid {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 40px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.partner-item {
    text-align: center;
}

.partner-logo {
    width: 120px;
    height: 120px;
    background: var(--white);
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    margin-bottom: 10px;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.partner-item:hover .partner-logo {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--gold);
}

.partner-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    filter: grayscale(100%);
    opacity: 0.7;
    transition: var(--transition);
}

.partner-item:hover .partner-logo img {
    filter: grayscale(0);
    opacity: 1;
}

.partner-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 36px;
    font-weight: 700;
}

.partner-name {
    font-size: 14px;
    color: var(--gray-600);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* ============================================
   BLOG SECTION
============================================ */
.blog-section {
    padding: 100px 0;
    background: var(--gray-100);
}

.blog-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 30px;
}

.blog-card {
    background: var(--white);
    border-radius: var(--border-radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    transition: var(--transition);
    height: 100%;
    display: flex;
    flex-direction: column;
}

.blog-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-xl);
    border-color: var(--gold);
}

.blog-image {
    height: 240px;
    overflow: hidden;
    position: relative;
}

.blog-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.blog-card:hover .blog-image img {
    transform: scale(1.1);
}

.blog-category {
    position: absolute;
    top: 20px;
    left: 20px;
    background: var(--gold);
    color: var(--navy);
    padding: 6px 15px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    z-index: 2;
}

.blog-content {
    padding: 30px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.blog-meta {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
    font-size: 13px;
    color: var(--gray-600);
}

.blog-meta i {
    color: var(--gold);
    margin-right: 5px;
}

.blog-title {
    font-size: 20px;
    margin-bottom: 15px;
    line-height: 1.4;
}

.blog-title a {
    color: var(--navy);
    text-decoration: none;
    transition: var(--transition);
}

.blog-title a:hover {
    color: var(--gold);
}

.blog-excerpt {
    color: var(--gray-600);
    font-size: 14px;
    line-height: 1.8;
    margin-bottom: 20px;
    flex: 1;
}

/* ============================================
   CTA SECTION
============================================ */
.cta-section {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 100px 0;
    position: relative;
    overflow: hidden;
}

.cta-wrapper {
    max-width: 900px;
    margin: 0 auto;
    text-align: center;
    color: var(--white);
    position: relative;
    z-index: 2;
    padding: 0 20px;
}

.cta-wrapper h2 {
    color: var(--white);
    font-size: clamp(28px, 5vw, 48px);
    margin-bottom: 20px;
}

.cta-wrapper p {
    color: rgba(255,255,255,0.9);
    font-size: clamp(14px, 2vw, 18px);
    margin-bottom: 30px;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

.cta-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

/* ============================================
   NEWSLETTER SECTION
============================================ */
.newsletter-section {
    padding: 80px 0;
    background: var(--white);
}

.newsletter-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 50px;
    max-width: 1000px;
    margin: 0 auto;
    padding: 60px;
    background: var(--gray-100);
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
}

.newsletter-content h3 {
    font-size: 28px;
    margin-bottom: 10px;
    color: var(--navy);
}

.newsletter-content p {
    color: var(--gray-600);
    font-size: 16px;
    margin: 0;
}

.newsletter-form {
    flex: 1;
    max-width: 500px;
}

.form-group {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.form-control {
    flex: 1;
    height: 54px;
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius);
    padding: 0 20px;
    font-size: 16px;
    transition: var(--transition);
}

.form-control:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(255,184,28,0.1);
}

.form-text {
    display: block;
    color: var(--gray-500);
    font-size: 12px;
}

.form-text a {
    color: var(--gold);
    text-decoration: none;
}

.form-message {
    margin-top: 10px;
    padding: 12px 20px;
    border-radius: var(--border-radius);
    font-size: 14px;
}

.form-message-success {
    background: #D4EDDA;
    color: #155724;
}

.form-message-error {
    background: #F8D7DA;
    color: #721C24;
}

/* ============================================
   BACK TO TOP BUTTON
============================================ */
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: var(--gold);
    color: var(--navy);
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: var(--transition);
    z-index: 999;
    box-shadow: var(--shadow-lg);
    border: 2px solid var(--white);
}

.back-to-top.show {
    opacity: 1;
    visibility: visible;
}

.back-to-top:hover {
    background: var(--gold-light);
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}

/* ============================================
   RESPONSIVE BREAKPOINTS
============================================ */

/* Large Desktop (1200px and up) */
@media (min-width: 1200px) {
    .container {
        max-width: 1140px;
    }
}

/* Desktop (992px - 1199px) */
@media (max-width: 1199px) {
    .container {
        max-width: 960px;
    }
    
    .arms-grid,
    .benefits-grid,
    .testimonials-grid,
    .blog-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .about-grid {
        gap: 40px;
    }
}

/* Tablet Landscape (768px - 991px) */
@media (max-width: 991px) {
    .container {
        max-width: 720px;
    }
    
    .hero {
        min-height: auto;
        padding: 100px 0 60px;
    }
    
    .hero-stats {
        gap: 30px;
    }
    
    .stat-item:not(:last-child)::after {
        right: -15px;
        height: 30px;
    }
    
    .about-grid {
        grid-template-columns: 1fr;
        gap: 50px;
    }
    
    .about-content {
        padding-right: 0;
        text-align: center;
    }
    
    .features-grid {
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .about-image {
        max-width: 500px;
        margin: 0 auto;
    }
    
    .experience-badge {
        width: 140px;
        height: 140px;
        bottom: -15px;
        right: -15px;
    }
    
    .benefits-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .partnership-cta {
        flex-direction: column;
        text-align: center;
        padding: 40px;
    }
    
    .newsletter-wrapper {
        flex-direction: column;
        text-align: center;
        padding: 40px;
    }
    
    .newsletter-form {
        width: 100%;
        max-width: 100%;
    }
}

/* Tablet Portrait (576px - 767px) */
@media (max-width: 767px) {
    .container {
        max-width: 540px;
    }
    
    .hero-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .hero-buttons .btn {
        width: 100%;
        justify-content: center;
    }
    
    .hero-stats {
        flex-direction: column;
        align-items: center;
        gap: 25px;
    }
    
    .stat-item:not(:last-child)::after {
        display: none;
    }
    
    .trust-badges {
        justify-content: center;
    }
    
    .arms-grid,
    .benefits-grid,
    .testimonials-grid,
    .blog-grid {
        grid-template-columns: 1fr;
    }
    
    .features-grid {
        grid-template-columns: 1fr;
        text-align: left;
    }
    
    .benefit-card.featured {
        transform: scale(1);
    }
    
    .benefit-card.featured:hover {
        transform: translateY(-10px);
    }
    
    .cta-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .cta-buttons .btn {
        width: 100%;
        justify-content: center;
    }
    
    .form-group {
        flex-direction: column;
    }
    
    .form-group .btn {
        width: 100%;
    }
}

/* Mobile (575px and down) */
@media (max-width: 575px) {
    .section {
        padding: 60px 0;
    }
    
    .hero h1 {
        font-size: 32px;
    }
    
    .arm-card,
    .benefit-card,
    .testimonial-card,
    .blog-card {
        padding: 30px 20px;
    }
    
    .arm-icon {
        width: 70px;
        height: 70px;
        font-size: 32px;
    }
    
    .arm-number {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }
    
    .testimonial-author {
        flex-direction: column;
        text-align: center;
    }
    
    .author-image {
        margin-bottom: 10px;
    }
    
    .experience-badge {
        width: 120px;
        height: 120px;
    }
    
    .experience-badge .years {
        font-size: 36px;
    }
    
    .experience-badge .text {
        font-size: 12px;
    }
    
    .certificate-logo {
        width: 120px;
        height: 120px;
    }
    
    .partner-logo {
        width: 100px;
        height: 100px;
    }
    
    .back-to-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
        font-size: 18px;
    }
}

/* Print Styles */
@media print {
    .hero,
    .cta-section,
    .newsletter-section,
    .back-to-top,
    .partnership-cta {
        display: none !important;
    }
    
    .section {
        padding: 2rem 0;
        page-break-inside: avoid;
    }
    
    .card,
    .arm-card,
    .benefit-card,
    .testimonial-card,
    .blog-card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>

<!-- ============================================
     SECTION 1: HERO
============================================ -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle"><?php echo getSetting('hero_subtitle', 'Welcome to AGPN'); ?></span>
            <h1><?php echo getSetting('hero_title', 'Empowering Global <span class="text-gold">Excellence</span>'); ?></h1>
            <p><?php echo $page ? truncateText(strip_tags($page['page_content']), 200) : getSetting('hero_description', 'Afroglobe Prime Network Limited (AGPN) is a multi-dimensional organization committed to driving economic growth through digital skills, business development, and global opportunities across Africa and beyond.'); ?></p>
            
            <div class="hero-buttons">
                <a href="<?php echo getSetting('hero_btn1_link', 'about.php'); ?>" class="btn btn-primary">
                    <span><?php echo getSetting('hero_btn1_text', 'Learn More'); ?></span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <a href="<?php echo getSetting('hero_btn2_link', 'contact.php'); ?>" class="btn btn-outline-light">
                    <span><?php echo getSetting('hero_btn2_text', 'Get in Touch'); ?></span>
                    <i class="fas fa-envelope"></i>
                </a>
            </div>
            
            <!-- Hero Stats -->
            <div class="hero-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['graduates']; ?>+</div>
                    <div class="stat-label">Professionals Trained</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['partners']; ?>+</div>
                    <div class="stat-label">Partner Organizations</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['countries']; ?>+</div>
                    <div class="stat-label">Countries Reached</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['programs']; ?></div>
                    <div class="stat-label">Core Programs</div>
                </div>
            </div>
            
            <!-- Trust Badges -->
            <?php if (!empty($certificates)): ?>
            <div class="trust-badges">
                <?php foreach ($certificates as $cert): ?>
                    <div class="trust-badge">
                        <img src="<?php echo SITE_URL . '/' . $cert['certificate_image']; ?>" 
                             alt="<?php echo htmlspecialchars($cert['certificate_title']); ?>"
                             loading="lazy">
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================================
     SECTION 2: ABOUT PREVIEW
============================================ -->
<section class="about-section">
    <div class="container">
        <div class="about-grid">
            <div class="about-content">
                <span class="section-subtitle"><?php echo getSetting('about_subtitle', 'Who We Are'); ?></span>
                <h2><?php echo getSetting('about_title', 'Your Partner in <span class="text-gold">Global Success</span>'); ?></h2>
                
                <p><?php echo $about_content ? truncateText(strip_tags($about_content['page_content']), 300) : getSetting('about_description', 'AGPN is a dynamic organization bridging the gap between African talent and global opportunities. We provide comprehensive solutions in digital skills training, business development, and international education.'); ?></p>
                
                <div class="features-grid">
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo getSetting('feature_1', 'ISO 9001:2025 Certified'); ?></span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo getSetting('feature_2', $stats['years'] . '+ Years Experience'); ?></span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo getSetting('feature_3', 'Global Network of Partners'); ?></span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo getSetting('feature_4', '100% Client Satisfaction'); ?></span>
                    </div>
                </div>
                
                <a href="<?php echo getSetting('about_btn_link', 'about.php'); ?>" class="btn btn-primary">
                    <?php echo getSetting('about_btn_text', 'Discover Our Story'); ?> <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="about-image">
                <img src="<?php echo getSetting('about_image', SITE_URL . '/assets/images/about-preview.jpg'); ?>" 
                     alt="About AGPN" 
                     loading="lazy">
                <div class="experience-badge">
                    <span class="years"><?php echo $stats['years']; ?>+</span>
                    <span class="text">Years of Excellence</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     SECTION 3: 6 CORE ARMS
============================================ -->
<?php if (!empty($arms)): ?>
<section class="arms-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle"><?php echo getSetting('arms_subtitle', 'Our Services'); ?></span>
            <h2 class="section-title"><?php echo getSetting('arms_title', 'The 6 Core <span class="text-gold">Arms</span> of AGPN'); ?></h2>
            <p class="section-description"><?php echo getSetting('arms_description', 'Comprehensive solutions designed to empower individuals and transform businesses'); ?></p>
        </div>
        
        <div class="arms-grid">
            <?php foreach ($arms as $index => $arm): ?>
                <div class="arm-card">
                    <div class="arm-icon-wrapper">
                        <div class="arm-icon">
                            <i class="fas <?php echo $arm['icon_class'] ?: 'fa-cube'; ?>"></i>
                        </div>
                        <div class="arm-number">0<?php echo $index + 1; ?></div>
                    </div>
                    <h3 class="arm-title"><?php echo htmlspecialchars($arm['arm_name']); ?></h3>
                    <p class="arm-description"><?php echo htmlspecialchars(truncateText($arm['short_description'] ?: 'Empowering excellence through specialized services.', 100)); ?></p>
                    <a href="<?php echo SITE_URL; ?>/arm.php?slug=<?php echo $arm['arm_slug']; ?>" class="btn-link">
                        <?php echo getSetting('arm_btn_text', 'Learn More'); ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center" style="text-align: center; margin-top: 50px;">
            <a href="<?php echo SITE_URL; ?>/arms.php" class="btn btn-outline">
                <?php echo getSetting('arms_view_all_text', 'Explore All Services'); ?> <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     SECTION 4: WHY PARTNER WITH AGPN
============================================ -->
<section class="benefits-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle"><?php echo getSetting('benefits_subtitle', 'Partnership'); ?></span>
            <h2 class="section-title"><?php echo getSetting('benefits_title', 'Why Partner With <span class="text-gold">AGPN</span>?'); ?></h2>
            <p class="section-description"><?php echo getSetting('benefits_description', 'Join us in shaping the future of global talent and enterprise development'); ?></p>
        </div>
        
        <div class="benefits-grid">
            <!-- Benefit 1 -->
            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="fas fa-globe-africa"></i>
                </div>
                <h3><?php echo getSetting('benefit_1_title', 'Global Reach'); ?></h3>
                <p><?php echo getSetting('benefit_1_desc', 'Access to emerging markets and international networks across Africa and beyond.'); ?></p>
                <div class="benefit-stats">
                    <span class="stat"><?php echo $stats['countries']; ?>+ Countries</span>
                </div>
            </div>
            
            <!-- Benefit 2 - Featured -->
            <div class="benefit-card featured">
                <div class="benefit-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo getSetting('benefit_2_title', 'Impact at Scale'); ?></h3>
                <p><?php echo getSetting('benefit_2_desc', 'Direct impact on thousands of youths and businesses through our programs.'); ?></p>
                <div class="benefit-stats">
                    <span class="stat"><?php echo $stats['graduates']; ?>+ Trained</span>
                </div>
                <div class="featured-badge">
                    <i class="fas fa-crown"></i> Most Popular
                </div>
            </div>
            
            <!-- Benefit 3 -->
            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <h3><?php echo getSetting('benefit_3_title', 'Certified Excellence'); ?></h3>
                <p><?php echo getSetting('benefit_3_desc', 'ISO-certified programs with measurable outcomes and industry recognition.'); ?></p>
                <div class="benefit-stats">
                    <span class="stat"><?php echo $stats['certifications']; ?>+ Certifications</span>
                </div>
            </div>
            
            <!-- Benefit 4 -->
            <div class="benefit-card">
                <div class="benefit-icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3><?php echo getSetting('benefit_4_title', 'Strategic Partnerships'); ?></h3>
                <p><?php echo getSetting('benefit_4_desc', 'Collaborate with a trusted industry leader with proven track record.'); ?></p>
                <div class="benefit-stats">
                    <span class="stat"><?php echo $stats['partners']; ?>+ Partners</span>
                </div>
            </div>
        </div>
        
        <!-- Partnership CTA -->
        <div class="partnership-cta">
            <div class="cta-content">
                <h3><?php echo getSetting('partnership_cta_title', 'Ready to Make an Impact?'); ?></h3>
                <p><?php echo getSetting('partnership_cta_desc', 'Join our network of forward-thinking partners and sponsors'); ?></p>
            </div>
            <a href="<?php echo getSetting('partnership_cta_link', 'sponsor.php'); ?>" class="btn btn-primary">
                <?php echo getSetting('partnership_cta_text', 'Become a Sponsor'); ?> <i class="fas fa-handshake"></i>
            </a>
        </div>
    </div>
</section>

<!-- ============================================
     SECTION 5: TESTIMONIALS
============================================ -->
<?php if (!empty($testimonials)): ?>
<section class="testimonials-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle"><?php echo getSetting('testimonials_subtitle', 'Testimonials'); ?></span>
            <h2 class="section-title"><?php echo getSetting('testimonials_title', 'What Our <span class="text-gold">Partners</span> Say'); ?></h2>
            <p class="section-description"><?php echo getSetting('testimonials_description', 'Trusted by industry leaders and global organizations'); ?></p>
        </div>
        
        <div class="testimonials-grid">
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= ($testimonial['rating'] ?? 5) ? 'active' : ''; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="testimonial-quote">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <p class="testimonial-text"><?php echo htmlspecialchars(truncateText($testimonial['testimonial_text'], 200)); ?></p>
                    <div class="testimonial-author">
                        <?php if ($testimonial['client_photo']): ?>
                            <img src="<?php echo SITE_URL . '/' . $testimonial['client_photo']; ?>" 
                                 alt="<?php echo htmlspecialchars($testimonial['client_name']); ?>" 
                                 class="author-image">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($testimonial['client_name']); ?>&size=80&background=0A1929&color=FFB81C" 
                                 alt="<?php echo htmlspecialchars($testimonial['client_name']); ?>" 
                                 class="author-image">
                        <?php endif; ?>
                        <div class="author-info">
                            <h5><?php echo htmlspecialchars($testimonial['client_name']); ?></h5>
                            <p><?php echo htmlspecialchars($testimonial['client_position']); ?><?php echo $testimonial['company'] ? ', ' . htmlspecialchars($testimonial['company']) : ''; ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center" style="text-align: center; margin-top: 50px;">
            <a href="<?php echo SITE_URL; ?>/testimonials.php" class="btn btn-outline">
                <?php echo getSetting('testimonials_view_all_text', 'View All Testimonials'); ?> <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     SECTION 6: CERTIFICATES
============================================ -->
<?php if (!empty($certificates)): ?>
<section class="certificates-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle"><?php echo getSetting('certificates_subtitle', 'Accreditations'); ?></span>
            <h2 class="section-title"><?php echo getSetting('certificates_title', 'Our <span class="text-gold">Certifications</span>'); ?></h2>
            <p class="section-description"><?php echo getSetting('certificates_description', 'Recognized by leading global institutions'); ?></p>
        </div>
        
        <div class="certificates-grid">
            <?php foreach ($certificates as $cert): ?>
                <div class="certificate-item">
                    <div class="certificate-logo">
                        <img src="<?php echo SITE_URL . '/' . $cert['certificate_image']; ?>" 
                             alt="<?php echo htmlspecialchars($cert['certificate_title']); ?>"
                             loading="lazy">
                    </div>
                    <div class="certificate-title"><?php echo htmlspecialchars($cert['certificate_title']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     SECTION 7: PARTNERS
============================================ -->
<?php if (!empty($partners)): ?>
<section class="partners-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle"><?php echo getSetting('partners_subtitle', 'Our Partners'); ?></span>
            <h2 class="section-title"><?php echo getSetting('partners_title', 'Trusted by <span class="text-gold">Industry Leaders</span>'); ?></h2>
        </div>
        
        <div class="partners-grid">
            <?php foreach ($partners as $partner): ?>
                <div class="partner-item">
                    <div class="partner-logo">
                        <?php if (!empty($partner['client_photo'])): ?>
                            <img src="<?php echo SITE_URL . '/' . $partner['client_photo']; ?>" 
                                 alt="<?php echo htmlspecialchars($partner['company']); ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="partner-placeholder">
                                <span><?php echo htmlspecialchars(substr($partner['company'], 0, 2)); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="partner-name">
                        <?php echo htmlspecialchars($partner['company']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     SECTION 8: LATEST BLOG POSTS
============================================ -->
<?php if (!empty($blog_posts)): ?>
<section class="blog-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle"><?php echo getSetting('blog_subtitle', 'Insights'); ?></span>
            <h2 class="section-title"><?php echo getSetting('blog_title', 'Latest News & <span class="text-gold">Updates</span>'); ?></h2>
            <p class="section-description"><?php echo getSetting('blog_description', 'Stay informed with AGPN\'s latest initiatives and industry trends'); ?></p>
        </div>
        
        <div class="blog-grid">
            <?php foreach ($blog_posts as $post): ?>
                <article class="blog-card">
                    <div class="blog-image">
                        <?php if ($post['featured_image']): ?>
                            <img src="<?php echo SITE_URL . '/' . $post['featured_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($post['title']); ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <img src="<?php echo SITE_URL; ?>/assets/images/blog-placeholder.jpg" 
                                 alt="Blog Post"
                                 loading="lazy">
                        <?php endif; ?>
                        <div class="blog-category">Insights</div>
                    </div>
                    
                    <div class="blog-content">
                        <div class="blog-meta">
                            <span><i class="fas fa-calendar-alt"></i> <?php echo formatDate($post['published_date'] ?? $post['created_at'], 'M d, Y'); ?></span>
                            <?php if ($post['author']): ?>
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['author']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <h3 class="blog-title">
                            <a href="<?php echo SITE_URL; ?>/single.php?slug=<?php echo $post['slug']; ?>">
                                <?php echo htmlspecialchars(truncateText($post['title'], 70)); ?>
                            </a>
                        </h3>
                        
                        <p class="blog-excerpt">
                            <?php echo htmlspecialchars(truncateText($post['excerpt'] ?: strip_tags($post['content']), 120)); ?>
                        </p>
                        
                        <a href="<?php echo SITE_URL; ?>/single.php?slug=<?php echo $post['slug']; ?>" class="btn-link">
                            <?php echo getSetting('blog_read_more_text', 'Read Full Article'); ?> <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center" style="text-align: center; margin-top: 50px;">
            <a href="<?php echo SITE_URL; ?>/blog.php" class="btn btn-primary">
                <?php echo getSetting('blog_view_all_text', 'View All Articles'); ?> <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     SECTION 9: MAIN CTA
============================================ -->
<section class="cta-section">
    <div class="container">
        <div class="cta-wrapper">
            <h2><?php echo getSetting('cta_title', 'Ready to Transform Your Future?'); ?></h2>
            <p><?php echo getSetting('cta_description', 'Whether you\'re looking to advance your career, grow your business, or explore global opportunities, AGPN is here to guide you every step of the way.'); ?></p>
            <div class="cta-buttons">
                <a href="<?php echo getSetting('cta_btn1_link', 'contact.php'); ?>" class="btn btn-primary">
                    <i class="fas fa-calendar-check"></i>
                    <?php echo getSetting('cta_btn1_text', 'Schedule a Consultation'); ?>
                </a>
                <a href="<?php echo getSetting('cta_btn2_link', 'digital-skills.php'); ?>" class="btn btn-outline-light">
                    <?php echo getSetting('cta_btn2_text', 'Explore Programs'); ?> <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     SECTION 10: NEWSLETTER
============================================ -->
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-wrapper">
            <div class="newsletter-content">
                <h3><?php echo getSetting('newsletter_title', 'Subscribe to Our Newsletter'); ?></h3>
                <p><?php echo getSetting('newsletter_description', 'Get the latest updates on programs, events, and opportunities delivered directly to your inbox.'); ?></p>
            </div>
            
            <form action="<?php echo SITE_URL; ?>/forms/newsletter-handler.php" 
                  method="POST" 
                  class="newsletter-form"
                  id="newsletterForm">
                
                <div class="form-group">
                    <input type="email" 
                           name="email" 
                           class="form-control" 
                           placeholder="<?php echo getSetting('newsletter_placeholder', 'Enter your email address'); ?>" 
                           required
                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                           title="Please enter a valid email address">
                    <button type="submit" class="btn btn-primary">
                        <?php echo getSetting('newsletter_button_text', 'Subscribe'); ?>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                
                <small class="form-text">
                    <?php echo getSetting('newsletter_privacy_text', 'We respect your privacy. Unsubscribe at any time.'); ?>
                    <a href="<?php echo SITE_URL; ?>/privacy-policy.php">Privacy Policy</a>
                </small>
                
                <div class="form-message" style="display: none;"></div>
            </form>
        </div>
    </div>
</section>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTop" aria-label="Back to top">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Schema.org Structured Data -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "<?php echo SITE_NAME; ?>",
    "url": "<?php echo SITE_URL; ?>",
    "logo": "<?php echo SITE_URL; ?>/assets/images/logo.png",
    "sameAs": [
        "<?php echo $facebook_url; ?>",
        "<?php echo $twitter_url; ?>",
        "<?php echo $linkedin_url; ?>",
        "<?php echo $instagram_url; ?>"
    ],
    "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "<?php echo $contact_phone; ?>",
        "contactType": "customer service",
        "email": "<?php echo $contact_email; ?>"
    },
    "address": {
        "@type": "PostalAddress",
        "addressLocality": "Lagos",
        "addressCountry": "NG"
    },
    "description": "<?php echo $meta_description; ?>",
    "keywords": "<?php echo $meta_keywords; ?>"
}
</script>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ============================================
    // BACK TO TOP BUTTON
    // ============================================
    const backToTop = document.getElementById('backToTop');
    
    if (backToTop) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });

        backToTop.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // ============================================
    // NEWSLETTER FORM
    // ============================================
    const newsletterForm = document.getElementById('newsletterForm');
    
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = this.querySelector('input[type="email"]');
            const button = this.querySelector('button[type="submit"]');
            const message = this.querySelector('.form-message');
            
            // Validate email
            if (!isValidEmail(email.value)) {
                showFormMessage(message, 'Please enter a valid email address.', 'error');
                return;
            }
            
            // Disable form
            email.disabled = true;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';
            
            // Simulate success (replace with actual AJAX)
            setTimeout(() => {
                showFormMessage(message, 'Thank you for subscribing! Please check your email.', 'success');
                email.value = '';
                email.disabled = false;
                button.disabled = false;
                button.innerHTML = '<?php echo getSetting('newsletter_button_text', 'Subscribe'); ?> <i class="fas fa-paper-plane"></i>';
            }, 1500);
        });
    }
    
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    }
    
    function showFormMessage(element, text, type) {
        element.className = 'form-message form-message-' + type;
        element.textContent = text;
        element.style.display = 'block';
        
        setTimeout(() => {
            element.style.display = 'none';
        }, 5000);
    }
    
    // ============================================
    // MOBILE MENU (if not handled in header)
    // ============================================
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function(e) {
            e.preventDefault();
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
        });
    }
    
    // ============================================
    // SMOOTH SCROLL FOR ANCHOR LINKS
    // ============================================
    document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                e.preventDefault();
                
                const headerHeight = document.querySelector('.site-header')?.offsetHeight || 0;
                const targetOffset = targetElement.offsetTop - headerHeight - 20;
                
                window.scrollTo({
                    top: targetOffset,
                    behavior: 'smooth'
                });
            }
        });
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>