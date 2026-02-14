<?php
// ============================================
// INDIVIDUAL ARM PAGE - DYNAMIC FROM DATABASE
// Displays detailed information for a specific arm
// ============================================

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get arm slug from URL
$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';

if (empty($slug)) {
    redirect('arms.php');
}

// Get arm data from database
try {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM arms WHERE arm_slug = ? AND status = 'active'");
    $stmt->execute([$slug]);
    $arm = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$arm) {
        // Arm not found
        $_SESSION['error'] = 'Arm not found';
        redirect('arms.php');
    }
    
    // Get page content for arms overview (optional)
    $page = getPageContent('arms');
    
} catch (PDOException $e) {
    error_log("Error fetching arm: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading arm details';
    redirect('arms.php');
}

// Set page metadata
$page_title = $arm['arm_name'] . ' - AGPN Arm';
$meta_description = $arm['short_description'] ?: 'Learn more about this AGPN service arm';
$meta_keywords = $arm['arm_name'] . ', AGPN, services, ' . strtolower(str_replace(' ', ', ', $arm['arm_name']));

$show_breadcrumbs = true;
$breadcrumbs = [
    ['url' => 'arms.php', 'title' => 'The 6 Arms'],
    ['title' => $arm['arm_name']]
];
$body_class = 'arm-page';

include 'includes/header.php';
?>

<style>
/* ============================================
   INDIVIDUAL ARM PAGE - CLEAN & RESPONSIVE
   Displays full details for one arm
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
.arm-hero {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 60px 0;
    position: relative;
    overflow: hidden;
}

@media (min-width: 768px) {
    .arm-hero {
        padding: 80px 0;
    }
}

@media (min-width: 992px) {
    .arm-hero {
        padding: 100px 0;
    }
}

.arm-hero::before {
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
    .arm-hero::before {
        width: 400px;
        height: 400px;
    }
}

@keyframes pulse {
    0%, 100% { opacity: 0.1; transform: scale(1); }
    50% { opacity: 0.2; transform: scale(1.1); }
}

.arm-hero-content {
    position: relative;
    z-index: 2;
    max-width: 900px;
    margin: 0 auto;
    color: var(--white);
    text-align: center;
}

.arm-icon-large {
    font-size: 64px;
    color: var(--gold);
    margin-bottom: 20px;
    animation: fadeInUp 1s ease;
}

@media (min-width: 768px) {
    .arm-icon-large {
        font-size: 80px;
        margin-bottom: 25px;
    }
}

.arm-hero h1 {
    color: var(--white);
    font-size: 32px;
    margin-bottom: 15px;
    animation: fadeInUp 1s ease 0.1s both;
}

@media (min-width: 576px) {
    .arm-hero h1 {
        font-size: 36px;
    }
}

@media (min-width: 768px) {
    .arm-hero h1 {
        font-size: 42px;
        margin-bottom: 20px;
    }
}

@media (min-width: 992px) {
    .arm-hero h1 {
        font-size: 48px;
    }
}

.arm-number-badge {
    display: inline-block;
    background: rgba(255,255,255,0.1);
    color: var(--gold);
    font-size: 14px;
    font-weight: 600;
    padding: 6px 16px;
    border-radius: 30px;
    margin-bottom: 20px;
    animation: fadeInUp 1s ease 0.2s both;
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
   MAIN CONTENT SECTION
============================================ */
.arm-main-section {
    padding: 50px 0;
    background: var(--white);
}

@media (min-width: 768px) {
    .arm-main-section {
        padding: 70px 0;
    }
}

.arm-layout {
    display: flex;
    flex-direction: column;
    gap: 40px;
}

@media (min-width: 992px) {
    .arm-layout {
        flex-direction: row;
        gap: 50px;
    }
}

/* ============================================
   MAIN CONTENT
============================================ */
.arm-main-content {
    width: 100%;
}

@media (min-width: 992px) {
    .arm-main-content {
        width: calc(100% - 350px);
    }
}

.arm-description-full {
    font-size: 16px;
    line-height: 1.8;
    color: var(--gray-800);
}

@media (min-width: 768px) {
    .arm-description-full {
        font-size: 17px;
    }
}

.arm-description-full h2 {
    font-size: 24px;
    margin: 40px 0 20px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .arm-description-full h2 {
        font-size: 28px;
        margin: 50px 0 25px;
    }
}

.arm-description-full h3 {
    font-size: 20px;
    margin: 30px 0 15px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .arm-description-full h3 {
        font-size: 22px;
        margin: 35px 0 20px;
    }
}

.arm-description-full p {
    margin-bottom: 20px;
    color: var(--gray-600);
}

.arm-description-full ul,
.arm-description-full ol {
    margin-bottom: 20px;
    padding-left: 25px;
}

.arm-description-full li {
    margin-bottom: 8px;
    color: var(--gray-600);
}

.arm-description-full blockquote {
    background: var(--gray-100);
    padding: 25px;
    border-left: 4px solid var(--gold);
    border-radius: 8px;
    font-style: italic;
    margin: 30px 0;
    color: var(--gray-700);
}

@media (min-width: 768px) {
    .arm-description-full blockquote {
        padding: 30px;
        margin: 40px 0;
    }
}

/* ============================================
   SIDEBAR
============================================ */
.arm-sidebar {
    width: 100%;
}

@media (min-width: 992px) {
    .arm-sidebar {
        width: 300px;
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

/* Related Arms */
.related-arms {
    background: var(--white);
    border-radius: 16px;
    padding: 25px;
    border: 1px solid var(--gray-200);
}

.related-arms h4 {
    font-size: 18px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--gold);
    color: var(--navy);
    display: flex;
    align-items: center;
    gap: 10px;
}

.related-arms-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.related-arm-item {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    padding: 10px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.related-arm-item:hover {
    background: var(--gray-100);
}

.related-arm-icon {
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

.related-arm-info {
    flex: 1;
}

.related-arm-info h5 {
    font-size: 15px;
    color: var(--navy);
    margin-bottom: 3px;
    font-weight: 600;
}

.related-arm-info p {
    font-size: 12px;
    color: var(--gray-600);
    margin: 0;
}

/* ============================================
   CTA SECTION
============================================ */
.arm-cta {
    padding: 50px 0;
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    color: var(--white);
    text-align: center;
}

@media (min-width: 768px) {
    .arm-cta {
        padding: 70px 0;
    }
}

.arm-cta-wrapper {
    max-width: 700px;
    margin: 0 auto;
}

.arm-cta-wrapper h2 {
    color: var(--white);
    font-size: 26px;
    margin-bottom: 15px;
}

@media (min-width: 576px) {
    .arm-cta-wrapper h2 {
        font-size: 32px;
    }
}

@media (min-width: 768px) {
    .arm-cta-wrapper h2 {
        font-size: 36px;
        margin-bottom: 20px;
    }
}

.arm-cta-wrapper p {
    color: rgba(255,255,255,0.9);
    font-size: 16px;
    line-height: 1.7;
    margin-bottom: 25px;
}

@media (min-width: 768px) {
    .arm-cta-wrapper p {
        font-size: 18px;
        margin-bottom: 30px;
    }
}

.arm-cta-buttons {
    display: flex;
    flex-direction: column;
    gap: 15px;
    justify-content: center;
}

@media (min-width: 576px) {
    .arm-cta-buttons {
        flex-direction: row;
        gap: 20px;
    }
}

.arm-cta-buttons .btn {
    display: inline-block;
    padding: 14px 30px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    min-width: 200px;
}

@media (min-width: 768px) {
    .arm-cta-buttons .btn {
        padding: 16px 35px;
        font-size: 16px;
    }
}

.arm-cta-buttons .btn-primary {
    background: var(--gold);
    color: var(--navy);
}

.arm-cta-buttons .btn-primary:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(255,184,28,0.3);
}

.arm-cta-buttons .btn-outline-light {
    border: 2px solid var(--white);
    color: var(--white);
    background: transparent;
}

.arm-cta-buttons .btn-outline-light:hover {
    background: var(--white);
    color: var(--navy);
    transform: translateY(-2px);
}

/* ============================================
   UTILITY CLASSES
============================================ */
.text-gold {
    color: var(--gold);
}

.mt-4 {
    margin-top: 30px;
}

@media (min-width: 768px) {
    .mt-4 {
        margin-top: 40px;
    }
}

/* Empty State */
.arm-empty {
    text-align: center;
    padding: 60px 20px;
    background: var(--white);
    border-radius: 16px;
    border: 1px solid var(--gray-200);
}

.arm-empty i {
    font-size: 64px;
    color: var(--gray-300);
    margin-bottom: 20px;
}

.arm-empty h3 {
    font-size: 24px;
    margin-bottom: 10px;
    color: var(--navy);
}

.arm-empty p {
    color: var(--gray-600);
    max-width: 500px;
    margin: 0 auto;
}
</style>

<!-- ============================================
     HERO SECTION
============================================ -->
<section class="arm-hero">
    <div class="container">
        <div class="arm-hero-content">
            <div class="arm-icon-large">
                <i class="fas <?php echo !empty($arm['icon_class']) ? htmlspecialchars($arm['icon_class']) : 'fa-cube'; ?>"></i>
            </div>
            <div class="arm-number-badge">
                <?php
                // Get arm number by querying all arms and finding position
                try {
                    $all_arms = $db->query("SELECT id FROM arms WHERE status = 'active' ORDER BY display_order ASC, created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
                    $arm_number = 0;
                    foreach ($all_arms as $index => $a) {
                        if ($a['id'] == $arm['id']) {
                            $arm_number = $index + 1;
                            break;
                        }
                    }
                    if ($arm_number > 0) {
                        echo "Arm " . str_pad($arm_number, 2, '0', STR_PAD_LEFT);
                    }
                } catch (PDOException $e) {
                    // Silently fail
                }
                ?>
            </div>
            <h1><?php echo htmlspecialchars($arm['arm_name']); ?></h1>
        </div>
    </div>
</section>

<!-- ============================================
     MAIN CONTENT SECTION
============================================ -->
<section class="arm-main-section">
    <div class="container">
        <div class="arm-layout">
            
            <!-- MAIN CONTENT -->
            <div class="arm-main-content">
                <?php if (!empty($arm['full_description'])): ?>
                    <div class="arm-description-full">
                        <?php echo $arm['full_description']; ?>
                    </div>
                <?php else: ?>
                    <div class="arm-empty">
                        <i class="fas fa-file-alt"></i>
                        <h3>Detailed Information Coming Soon</h3>
                        <p>We're currently preparing detailed information about this service arm. Please check back later or contact us for immediate inquiries.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SIDEBAR -->
            <aside class="arm-sidebar">
                <div class="sidebar-sticky">
                    
                    <!-- Quick Info Card -->
                    <div class="info-card">
                        <h4><i class="fas fa-info-circle"></i> Quick Info</h4>
                        
                        <?php if (!empty($arm['short_description'])): ?>
                            <div class="info-item">
                                <div class="info-label">Overview:</div>
                                <div class="info-value"><?php echo htmlspecialchars($arm['short_description']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <div class="info-label">Status:</div>
                            <div class="info-value" style="color: <?php echo $arm['status'] == 'active' ? '#28a745' : '#dc3545'; ?>">
                                <i class="fas fa-<?php echo $arm['status'] == 'active' ? 'check-circle' : 'minus-circle'; ?>"></i>
                                <?php echo ucfirst($arm['status']); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($arm['display_order'])): ?>
                            <div class="info-item">
                                <div class="info-label">Display Order:</div>
                                <div class="info-value"><?php echo $arm['display_order']; ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <hr class="info-divider">
                        
                        <h4 style="margin-bottom: 15px;"><i class="fas fa-phone-alt"></i> Contact</h4>
                        
                        <?php $contact_email = getSetting('contact_email', 'info@afroglobeprime.net'); ?>
                        <?php if (!empty($contact_email)): ?>
                            <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($contact_email); ?></span>
                            </a>
                        <?php endif; ?>
                        
                        <?php $contact_phone = getSetting('contact_phone', ''); ?>
                        <?php if (!empty($contact_phone)): ?>
                            <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $contact_phone); ?>" class="contact-item">
                                <i class="fas fa-phone-alt"></i>
                                <span><?php echo htmlspecialchars($contact_phone); ?></span>
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo SITE_URL; ?>/contact.php" class="sidebar-btn">
                            Contact Us <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <!-- Other Arms -->
                    <?php
                    // Get other active arms
                    try {
                        $other_arms = $db->prepare("
                            SELECT * FROM arms 
                            WHERE id != ? AND status = 'active' 
                            ORDER BY display_order ASC, created_at ASC 
                            LIMIT 3
                        ");
                        $other_arms->execute([$arm['id']]);
                        $other_arms = $other_arms->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $other_arms = [];
                    }
                    ?>
                    
                    <?php if (!empty($other_arms)): ?>
                        <div class="related-arms">
                            <h4><i class="fas fa-cubes"></i> Other Arms</h4>
                            <div class="related-arms-list">
                                <?php foreach ($other_arms as $other): ?>
                                    <a href="arm.php?slug=<?php echo urlencode($other['arm_slug']); ?>" class="related-arm-item">
                                        <div class="related-arm-icon">
                                            <i class="fas <?php echo !empty($other['icon_class']) ? htmlspecialchars($other['icon_class']) : 'fa-cube'; ?>"></i>
                                        </div>
                                        <div class="related-arm-info">
                                            <h5><?php echo htmlspecialchars($other['arm_name']); ?></h5>
                                            <p><?php echo htmlspecialchars(truncateText($other['short_description'] ?: 'Learn more', 40)); ?></p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</section>

<!-- ============================================
     CTA SECTION
============================================ -->
<section class="arm-cta">
    <div class="container">
        <div class="arm-cta-wrapper">
            <h2>Interested in This Service?</h2>
            <p>Contact us today to learn more about how our <?php echo htmlspecialchars($arm['arm_name']); ?> arm can help you achieve your goals.</p>
            <div class="arm-cta-buttons">
                <a href="<?php echo SITE_URL; ?>/contact.php?subject=<?php echo urlencode('Inquiry about ' . $arm['arm_name']); ?>" class="btn btn-primary">
                    <i class="fas fa-envelope"></i> Inquire Now
                </a>
                <a href="<?php echo SITE_URL; ?>/arms.php" class="btn btn-outline-light">
                    View All Arms <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

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