<?php
// ============================================
// PROGRAM PAGE TEMPLATE - CLEAN & RESPONSIVE
// Use for: digital-skills.php, business-growth.php, study-abroad.php
// ============================================

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get page name from filename
$page_name = basename(__FILE__, '.php');

// Get program page content from database
$page = getPageContent($page_name);

// Set default content based on page name
$program_data = [
    'digital-skills' => [
        'title' => 'Digital Skills Training',
        'subtitle' => 'Future-proof your career with in-demand tech skills',
        'description' => 'Comprehensive digital skills training programs to prepare you for the future of work.',
        'features' => [
            'Web Development' => 'HTML, CSS, JavaScript, React, Node.js, and full-stack development.',
            'Data Science' => 'Python, SQL, machine learning, data visualization, and analytics.',
            'Digital Marketing' => 'SEO, social media, content marketing, Google Analytics, and paid advertising.',
            'UI/UX Design' => 'User research, wireframing, prototyping, Figma, and design thinking.'
        ],
        'duration' => '10-14 weeks',
        'format' => 'Online / Hybrid',
        'icon' => 'fa-laptop-code'
    ],
    'business-growth' => [
        'title' => 'Business Growth Services',
        'subtitle' => 'Expert guidance for sustainable business growth',
        'description' => 'Strategic consulting and support services to accelerate your business growth.',
        'features' => [
            'Business Strategy' => 'Comprehensive planning and roadmap development',
            'Market Entry' => 'Research and strategy for new market expansion',
            'Operational Excellence' => 'Process optimization and efficiency improvement',
            'Investment Readiness' => 'Pitch preparation, financial modeling, investor connections',
            'Digital Transformation' => 'Technology adoption and digital strategy'
        ],
        'duration' => 'Customized',
        'format' => 'In-person / Virtual',
        'icon' => 'fa-chart-line'
    ],
    'study-abroad' => [
        'title' => 'Study Abroad Programs',
        'subtitle' => 'Your gateway to global education',
        'description' => 'International education opportunities at top universities worldwide.',
        'features' => [
            'University Admissions' => 'Expert guidance on university selection and application processes',
            'Scholarships' => 'Access to scholarship opportunities and financial aid resources',
            'Visa Assistance' => 'Comprehensive visa application support and interview preparation',
            'Pre-departure' => 'Orientation, accommodation, and cultural integration support'
        ],
        'duration' => 'Varies by program',
        'format' => 'In-person abroad',
        'icon' => 'fa-graduation-cap'
    ]
];

// Use database content if available, otherwise use defaults
if ($page) {
    $page_title = $page['page_title'];
    $meta_description = $page['meta_description'];
    $hero_title = $page['page_title'];
    $hero_subtitle = getSetting('site_tagline');
    $content = $page['page_content'];
    $featured_image = $page['featured_image'] ?? '';
} else {
    $page_title = $program_data[$page_name]['title'] ?? 'Program';
    $meta_description = $program_data[$page_name]['description'] ?? 'AGPN Program';
    $hero_title = $program_data[$page_name]['title'] ?? 'Program';
    $hero_subtitle = $program_data[$page_name]['subtitle'] ?? 'Empowering excellence';
    $content = '';
    $featured_image = '';
}

// Get page-specific data
$program = $program_data[$page_name] ?? null;

$show_breadcrumbs = true;
$breadcrumbs = [
    ['title' => 'Programs'],
    ['title' => $hero_title]
];
$body_class = 'program-page program-' . $page_name;

include 'includes/header.php';
?>

<style>
/* ============================================
   PROGRAM PAGE - CLEAN & RESPONSIVE
   Consistent design across all program pages
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
    --gray-800: #343a40;
    --gray-900: #212529;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    line-height: 1.6;
    color: var(--gray-800);
}

.container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* ============================================
   HERO SECTION
============================================ */
.program-hero {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 60px 0;
    position: relative;
    overflow: hidden;
}

@media (min-width: 768px) {
    .program-hero {
        padding: 80px 0;
    }
}

@media (min-width: 992px) {
    .program-hero {
        padding: 100px 0;
    }
}

.program-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,184,28,0.1) 0%, transparent 70%);
    border-radius: 50%;
    animation: pulse 8s infinite;
}

@media (min-width: 768px) {
    .program-hero::before {
        width: 400px;
        height: 400px;
    }
}

@keyframes pulse {
    0%, 100% { opacity: 0.1; transform: scale(1); }
    50% { opacity: 0.2; transform: scale(1.1); }
}

.program-hero-content {
    position: relative;
    z-index: 2;
    max-width: 900px;
    margin: 0 auto;
    color: var(--white);
    text-align: center;
}

.program-icon {
    font-size: 48px;
    color: var(--gold);
    margin-bottom: 20px;
    animation: fadeInUp 1s ease;
}

@media (min-width: 768px) {
    .program-icon {
        font-size: 64px;
        margin-bottom: 25px;
    }
}

.program-hero h1 {
    color: var(--white);
    font-size: 32px;
    margin-bottom: 15px;
    animation: fadeInUp 1s ease 0.1s both;
}

@media (min-width: 576px) {
    .program-hero h1 {
        font-size: 36px;
    }
}

@media (min-width: 768px) {
    .program-hero h1 {
        font-size: 42px;
        margin-bottom: 20px;
    }
}

@media (min-width: 992px) {
    .program-hero h1 {
        font-size: 48px;
    }
}

.program-hero p {
    color: rgba(255,255,255,0.9);
    font-size: 16px;
    line-height: 1.7;
    max-width: 700px;
    margin: 0 auto;
    animation: fadeInUp 1s ease 0.2s both;
}

@media (min-width: 768px) {
    .program-hero p {
        font-size: 18px;
    }
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
   MAIN CONTENT LAYOUT
============================================ */
.program-section {
    padding: 50px 0;
    background: var(--white);
}

@media (min-width: 768px) {
    .program-section {
        padding: 70px 0;
    }
}

.program-layout {
    display: flex;
    flex-direction: column;
    gap: 40px;
}

@media (min-width: 992px) {
    .program-layout {
        flex-direction: row;
        gap: 50px;
    }
}

/* ============================================
   MAIN CONTENT
============================================ */
.program-main {
    width: 100%;
}

@media (min-width: 992px) {
    .program-main {
        width: calc(100% - 380px);
    }
}

/* Featured Image */
.program-featured-image {
    margin-bottom: 30px;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.program-featured-image img {
    width: 100%;
    height: auto;
    display: block;
}

/* Content Styling */
.program-content {
    font-size: 16px;
    line-height: 1.8;
    color: var(--gray-800);
}

@media (min-width: 768px) {
    .program-content {
        font-size: 17px;
    }
}

.program-content h2 {
    font-size: 24px;
    margin: 40px 0 20px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .program-content h2 {
        font-size: 28px;
        margin: 50px 0 25px;
    }
}

.program-content h3 {
    font-size: 20px;
    margin: 30px 0 15px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .program-content h3 {
        font-size: 22px;
        margin: 35px 0 20px;
    }
}

.program-content h4 {
    font-size: 18px;
    margin: 25px 0 12px;
    color: var(--navy);
}

.program-content p {
    margin-bottom: 20px;
    color: var(--gray-600);
}

.program-content ul,
.program-content ol {
    margin-bottom: 20px;
    padding-left: 25px;
}

.program-content li {
    margin-bottom: 8px;
    color: var(--gray-600);
}

.program-content blockquote {
    background: var(--gray-100);
    padding: 25px;
    border-left: 4px solid var(--gold);
    border-radius: 8px;
    font-style: italic;
    margin: 30px 0;
    color: var(--gray-700);
}

@media (min-width: 768px) {
    .program-content blockquote {
        padding: 30px;
        margin: 40px 0;
    }
}

/* Features Grid */
.features-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin: 30px 0;
}

@media (min-width: 576px) {
    .features-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
}

@media (min-width: 768px) {
    .features-grid {
        gap: 25px;
        margin: 40px 0;
    }
}

.feature-card {
    background: var(--white);
    padding: 25px;
    border-radius: 16px;
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
    height: 100%;
}

@media (min-width: 768px) {
    .feature-card {
        padding: 30px;
    }
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    border-color: var(--gold);
}

.feature-icon {
    font-size: 32px;
    color: var(--gold);
    margin-bottom: 15px;
}

@media (min-width: 768px) {
    .feature-icon {
        font-size: 36px;
        margin-bottom: 20px;
    }
}

.feature-card h4 {
    font-size: 18px;
    margin-bottom: 12px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .feature-card h4 {
        font-size: 20px;
        margin-bottom: 15px;
    }
}

.feature-card p {
    color: var(--gray-600);
    font-size: 14px;
    line-height: 1.7;
    margin: 0;
}

@media (min-width: 768px) {
    .feature-card p {
        font-size: 15px;
    }
}

/* Feature List */
.feature-list {
    list-style: none;
    padding: 0;
    margin: 20px 0 30px;
}

.feature-list li {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 15px;
    color: var(--gray-600);
}

.feature-list i {
    color: var(--gold);
    font-size: 16px;
    margin-top: 3px;
    flex-shrink: 0;
}

.feature-list strong {
    color: var(--navy);
    font-weight: 600;
}

/* CTA Box */
.cta-box {
    background: linear-gradient(135deg, var(--gray-100) 0%, var(--white) 100%);
    padding: 30px;
    border-radius: 16px;
    margin-top: 50px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

@media (min-width: 576px) {
    .cta-box {
        padding: 40px;
        text-align: left;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 30px;
    }
}

@media (min-width: 768px) {
    .cta-box {
        padding: 50px;
        margin-top: 60px;
    }
}

.cta-content h3 {
    font-size: 22px;
    margin-bottom: 10px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .cta-content h3 {
        font-size: 24px;
    }
}

.cta-content p {
    color: var(--gray-600);
    margin-bottom: 20px;
    font-size: 15px;
}

@media (min-width: 576px) {
    .cta-content p {
        margin-bottom: 0;
    }
}

.cta-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 250px;
}

@media (min-width: 576px) {
    .cta-buttons {
        flex-direction: row;
    }
}

.cta-buttons .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 14px;
}

@media (min-width: 768px) {
    .cta-buttons .btn {
        padding: 14px 28px;
        font-size: 15px;
    }
}

.cta-buttons .btn-primary {
    background: var(--gold);
    color: var(--navy);
    border: none;
}

.cta-buttons .btn-primary:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(255,184,28,0.3);
}

.cta-buttons .btn-outline {
    background: transparent;
    color: var(--navy);
    border: 2px solid var(--gold);
}

.cta-buttons .btn-outline:hover {
    background: var(--gold);
    color: var(--navy);
    transform: translateY(-2px);
}

/* ============================================
   SIDEBAR
============================================ */
.program-sidebar {
    width: 100%;
}

@media (min-width: 992px) {
    .program-sidebar {
        width: 330px;
    }
}

.sidebar-sticky {
    position: sticky;
    top: 100px;
}

.info-card {
    background: var(--white);
    border-radius: 16px;
    padding: 25px;
    border: 1px solid var(--gray-200);
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    margin-bottom: 25px;
}

@media (min-width: 768px) {
    .info-card {
        padding: 30px;
    }
}

.info-card h4 {
    font-size: 18px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--gold);
    color: var(--navy);
    display: flex;
    align-items: center;
    gap: 10px;
}

@media (min-width: 768px) {
    .info-card h4 {
        font-size: 20px;
    }
}

.info-item {
    margin-bottom: 20px;
}

.info-label {
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 5px;
    font-size: 14px;
}

.info-value {
    color: var(--gray-600);
    font-size: 15px;
    line-height: 1.6;
}

.info-divider {
    border: none;
    border-top: 1px solid var(--gray-200);
    margin: 20px 0;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    color: var(--gray-600);
    text-decoration: none;
    transition: color 0.3s ease;
    font-size: 14px;
}

.contact-item i {
    color: var(--gold);
    width: 20px;
    font-size: 16px;
}

.contact-item:hover {
    color: var(--gold);
}

.sidebar-btn {
    display: block;
    width: 100%;
    padding: 14px;
    background: var(--gold);
    color: var(--navy);
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    text-decoration: none;
    margin-top: 20px;
}

.sidebar-btn:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(255,184,28,0.2);
}

/* Related Programs */
.related-programs {
    background: var(--white);
    border-radius: 16px;
    padding: 25px;
    border: 1px solid var(--gray-200);
}

.related-programs h4 {
    font-size: 18px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--gold);
    color: var(--navy);
    display: flex;
    align-items: center;
    gap: 10px;
}

.related-programs-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.related-program-item {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    padding: 10px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.related-program-item:hover {
    background: var(--gray-100);
}

.related-program-icon {
    width: 40px;
    height: 40px;
    background: rgba(255,184,28,0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 18px;
    flex-shrink: 0;
}

.related-program-info {
    flex: 1;
}

.related-program-info h5 {
    font-size: 15px;
    color: var(--navy);
    margin-bottom: 3px;
    font-weight: 600;
}

.related-program-info p {
    font-size: 12px;
    color: var(--gray-600);
    margin: 0;
}

/* ============================================
   BENEFITS SECTION
============================================ */
.benefits-section {
    padding: 50px 0;
    background: var(--gray-100);
}

@media (min-width: 768px) {
    .benefits-section {
        padding: 70px 0;
    }
}

.section-title {
    text-align: center;
    max-width: 700px;
    margin: 0 auto 40px;
}

@media (min-width: 768px) {
    .section-title {
        margin-bottom: 50px;
    }
}

.section-subtitle {
    display: block;
    color: var(--gold);
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 10px;
}

.section-title h2 {
    font-size: 28px;
    margin-bottom: 15px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .section-title h2 {
        font-size: 32px;
    }
}

.section-title p {
    color: var(--gray-600);
    font-size: 15px;
    line-height: 1.6;
}

.benefits-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

@media (min-width: 576px) {
    .benefits-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
}

@media (min-width: 992px) {
    .benefits-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 25px;
    }
}

.benefit-item {
    text-align: center;
    padding: 25px;
    background: var(--white);
    border-radius: 12px;
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
}

.benefit-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    border-color: var(--gold);
}

.benefit-icon {
    font-size: 32px;
    color: var(--gold);
    margin-bottom: 15px;
}

.benefit-item h4 {
    font-size: 16px;
    color: var(--navy);
    margin-bottom: 8px;
}

.benefit-item p {
    font-size: 13px;
    color: var(--gray-600);
    margin: 0;
    line-height: 1.6;
}

/* ============================================
   RESPONSIVE UTILITIES
============================================ */
.text-center {
    text-align: center;
}

.mt-4 {
    margin-top: 30px;
}

@media (min-width: 768px) {
    .mt-4 {
        margin-top: 40px;
    }
}
</style>

<!-- ============================================
     HERO SECTION
============================================ -->
<section class="program-hero" <?php echo $featured_image ? 'style="background-image: linear-gradient(135deg, rgba(10,25,41,0.95), rgba(26,42,58,0.9)), url(' . SITE_URL . '/' . $featured_image . '); background-size: cover; background-position: center;"' : ''; ?>>
    <div class="container">
        <div class="program-hero-content">
            <?php if ($program && isset($program['icon'])): ?>
                <div class="program-icon">
                    <i class="fas <?php echo $program['icon']; ?>"></i>
                </div>
            <?php endif; ?>
            
            <h1><?php echo htmlspecialchars($hero_title); ?></h1>
            <p><?php echo htmlspecialchars($hero_subtitle); ?></p>
        </div>
    </div>
</section>

<!-- ============================================
     MAIN CONTENT SECTION
============================================ -->
<section class="program-section">
    <div class="container">
        <div class="program-layout">
            
            <!-- MAIN CONTENT -->
            <div class="program-main">
                
                <!-- Featured Image (if available) -->
                <?php if ($featured_image): ?>
                    <div class="program-featured-image">
                        <img src="<?php echo SITE_URL . '/' . $featured_image; ?>" 
                             alt="<?php echo htmlspecialchars($hero_title); ?>"
                             loading="lazy">
                    </div>
                <?php endif; ?>

                <!-- Program Content -->
                <div class="program-content">
                    <?php if ($content): ?>
                        <?php echo $content; ?>
                    <?php elseif ($program): ?>
                        <!-- Default content for this program -->
                        <h2><?php echo htmlspecialchars($program['title']); ?></h2>
                        <p><?php echo htmlspecialchars($program['description']); ?></p>
                        
                        <h3>Program Features</h3>
                        
                        <?php if ($page_name == 'digital-skills'): ?>
                            <div class="features-grid">
                                <?php foreach ($program['features'] as $title => $desc): ?>
                                    <div class="feature-card">
                                        <div class="feature-icon">
                                            <i class="fas fa-code"></i>
                                        </div>
                                        <h4><?php echo $title; ?></h4>
                                        <p><?php echo $desc; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($page_name == 'business-growth'): ?>
                            <ul class="feature-list">
                                <?php foreach ($program['features'] as $title => $desc): ?>
                                    <li>
                                        <i class="fas fa-check-circle"></i>
                                        <div>
                                            <strong><?php echo $title; ?>:</strong> <?php echo $desc; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php elseif ($page_name == 'study-abroad'): ?>
                            <div class="features-grid">
                                <?php foreach ($program['features'] as $title => $desc): ?>
                                    <div class="feature-card">
                                        <div class="feature-icon">
                                            <i class="fas fa-graduation-cap"></i>
                                        </div>
                                        <h4><?php echo $title; ?></h4>
                                        <p><?php echo $desc; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- CTA Box -->
                <div class="cta-box">
                    <div class="cta-content">
                        <h3>Ready to Get Started?</h3>
                        <p>Contact our team today to learn more about this program and how we can support your goals.</p>
                    </div>
                    <div class="cta-buttons">
                        <a href="<?php echo SITE_URL; ?>/apply.php?program=<?php echo $page_name; ?>" class="btn btn-primary">Apply Now</a>
                        <a href="<?php echo SITE_URL; ?>/contact.php?program=<?php echo $page_name; ?>" class="btn btn-outline">Request Info</a>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <aside class="program-sidebar">
                <div class="sidebar-sticky">
                    
                    <!-- Program Info Card -->
                    <?php if ($program): ?>
                        <div class="info-card">
                            <h4><i class="fas fa-info-circle"></i> Program Information</h4>
                            
                            <div class="info-item">
                                <div class="info-label">Duration:</div>
                                <div class="info-value"><?php echo $program['duration']; ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Format:</div>
                                <div class="info-value"><?php echo $program['format']; ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Certification:</div>
                                <div class="info-value">Industry-recognized certificate</div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Next Start Date:</div>
                                <div class="info-value">Rolling admissions</div>
                            </div>
                            
                            <hr class="info-divider">
                            
                            <h4 style="margin-bottom: 15px;"><i class="fas fa-phone-alt"></i> Quick Contact</h4>
                            
                            <a href="mailto:<?php echo htmlspecialchars(getSetting('contact_email', 'info@afroglobeprime.net')); ?>" class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars(getSetting('contact_email', 'info@afroglobeprime.net')); ?></span>
                            </a>
                            
                            <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', getSetting('contact_phone', '')); ?>" class="contact-item">
                                <i class="fas fa-phone-alt"></i>
                                <span><?php echo htmlspecialchars(getSetting('contact_phone', '+234 800 123 4567')); ?></span>
                            </a>
                            
                            <a href="<?php echo SITE_URL; ?>/contact.php" class="sidebar-btn">
                                Contact Us Today <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Related Programs -->
                    <div class="related-programs">
                        <h4><i class="fas fa-cubes"></i> Other Programs</h4>
                        <div class="related-programs-list">
                            <?php if ($page_name != 'digital-skills'): ?>
                                <a href="<?php echo SITE_URL; ?>/digital-skills.php" class="related-program-item">
                                    <div class="related-program-icon">
                                        <i class="fas fa-laptop-code"></i>
                                    </div>
                                    <div class="related-program-info">
                                        <h5>Digital Skills</h5>
                                        <p>Tech training programs</p>
                                    </div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page_name != 'business-growth'): ?>
                                <a href="<?php echo SITE_URL; ?>/business-growth.php" class="related-program-item">
                                    <div class="related-program-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="related-program-info">
                                        <h5>Business Growth</h5>
                                        <p>Scale your business</p>
                                    </div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page_name != 'study-abroad'): ?>
                                <a href="<?php echo SITE_URL; ?>/study-abroad.php" class="related-program-item">
                                    <div class="related-program-icon">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <div class="related-program-info">
                                        <h5>Study Abroad</h5>
                                        <p>Global education</p>
                                    </div>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

<!-- ============================================
     BENEFITS SECTION (if applicable)
============================================ -->
<?php if ($page_name == 'digital-skills' || $page_name == 'study-abroad'): ?>
<section class="benefits-section">
    <div class="container">
        <div class="section-title">
            <span class="section-subtitle">Why Choose Us</span>
            <h2>Program <span class="text-gold">Benefits</span></h2>
            <p>What makes our programs stand out</p>
        </div>
        
        <div class="benefits-grid">
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <h4>Certified Programs</h4>
                <p>Industry-recognized certificates upon completion</p>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h4>Expert Instructors</h4>
                <p>Learn from experienced industry professionals</p>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <h4>Job Placement</h4>
                <p>Access to our network of hiring partners</p>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h4>Flexible Learning</h4>
                <p>Online and in-person options available</p>
            </div>
        </div>
    </div>
</section>
<?php elseif ($page_name == 'business-growth'): ?>
<section class="benefits-section">
    <div class="container">
        <div class="section-title">
            <span class="section-subtitle">Why Partner With Us</span>
            <h2>Business <span class="text-gold">Benefits</span></h2>
            <p>How we help your business grow</p>
        </div>
        
        <div class="benefits-grid">
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h4>Accelerated Growth</h4>
                <p>Proven strategies for rapid scaling</p>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <h4>Expert Network</h4>
                <p>Connect with industry leaders and investors</p>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h4>Data-Driven Insights</h4>
                <p>Make informed decisions with our analytics</p>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <h4>Global Reach</h4>
                <p>Access international markets and opportunities</p>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     SIMPLE JAVASCRIPT
============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const headerHeight = document.querySelector('.site-header')?.offsetHeight || 0;
                const targetPosition = targetElement.offsetTop - headerHeight - 20;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
});
</script>

<?php
include 'includes/footer.php';
?>