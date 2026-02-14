<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get about page content from database
$page = getPageContent('about');
$page_title = $page ? $page['page_title'] : 'About Us';
$meta_description = $page ? $page['meta_description'] : 'Learn about Afroglobe Prime Network Limited (AGPN) - our mission, vision, and commitment to excellence.';
$meta_keywords = $page ? $page['meta_keywords'] : 'AGPN, about us, mission, vision, leadership, team, Nigeria, Africa, business development';

// Get statistics for impact section from database
try {
    $db = db();
    $stats = [
        'graduates' => $db->query("SELECT COUNT(*) FROM application_submissions WHERE status = 'accepted'")->fetchColumn() ?: 0,
        'partners' => $db->query("SELECT COUNT(DISTINCT company) FROM testimonials WHERE status = 'active' AND company IS NOT NULL AND company != ''")->fetchColumn() ?: 0,
        'countries' => $db->query("SELECT COUNT(DISTINCT country) FROM application_submissions WHERE country IS NOT NULL AND country != ''")->fetchColumn() ?: 0,
        'programs' => $db->query("SELECT COUNT(*) FROM arms WHERE status = 'active'")->fetchColumn() ?: 0,
        'years' => date('Y') - 2015 // Since 2015
    ];
    
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $stats = [
        'graduates' => 0,
        'partners' => 0,
        'countries' => 0,
        'programs' => 0,
        'years' => date('Y') - 2015
    ];
}

// Get team members from database (if table exists)
$team_members = [];
try {
    $db = db();
    // Check if team_members table exists
    $table_check = $db->query("SHOW TABLES LIKE 'team_members'");
    if ($table_check->rowCount() > 0) {
        $team_members = $db->query("SELECT * FROM team_members WHERE status = 'active' ORDER BY display_order ASC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching team: " . $e->getMessage());
    $team_members = [];
}

// Get contact information from settings
$contact_email = getSetting('contact_email', '');
$contact_phone = getSetting('contact_phone', '');
$address = getSetting('address', '');
$about_image = getSetting('about_image', '');

$show_breadcrumbs = true;
$breadcrumbs = [
    ['title' => 'About Us']
];
$body_class = 'about-page';

// Ensure AOS is loaded
$page_css = ['aos.css'];
$page_js = ['aos.js', 'main.js']; // Make sure main.js is loaded

include 'includes/header.php';
?>

<!-- Page Header / Hero -->
<section class="page-hero" style="background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); padding: 80px 0; position: relative; overflow: hidden;">
    <div class="container" style="position: relative; z-index: 2;">
        <div class="page-hero-content" style="max-width: 800px; color: var(--white);">
            <span class="section-subtitle" style="color: var(--gold); display: block; margin-bottom: 15px;">About AGPN</span>
            <h1 style="color: var(--white); font-size: clamp(36px, 5vw, 56px); margin-bottom: 20px;"><?php echo $page ? htmlspecialchars($page['page_title']) : 'About Us'; ?></h1>
            <?php if ($page && !empty($page['page_content'])): ?>
                <p style="color: rgba(255,255,255,0.9); font-size: clamp(16px, 2vw, 18px); line-height: 1.8; max-width: 600px;">
                    <?php echo htmlspecialchars(truncateText(strip_tags($page['page_content']), 150)); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Decorative Elements -->
    <div style="position: absolute; top: 0; right: 0; width: 40%; height: 100%; background: radial-gradient(circle at 70% 50%, rgba(255,184,28,0.1) 0%, transparent 70%); z-index: 1;"></div>
    <div style="position: absolute; bottom: -50px; left: -50px; width: 200px; height: 200px; background: rgba(255,184,28,0.05); border-radius: 50%; z-index: 1;"></div>
</section>

<!-- Main Content -->
<section class="about-main" style="padding: 80px 0;">
    <div class="container">
        <div class="about-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: start;">
            <!-- Content Column -->
            <div class="about-content" data-aos="fade-right">
                <?php if ($page && !empty($page['page_content'])): ?>
                    <?php echo $page['page_content']; ?>
                <?php else: ?>
                    <p style="color: var(--gray-600); line-height: 1.8;">No content has been added to the about page yet. Please check back later.</p>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar Column -->
            <div class="about-sidebar" data-aos="fade-left">
                <!-- Main Image -->
                <?php if (!empty($about_image)): ?>
                <div style="position: relative; margin-bottom: 30px;">
                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($about_image); ?>" 
                         alt="About AGPN" 
                         style="width: 100%; border-radius: 20px; box-shadow: var(--shadow-xl);"
                         loading="lazy">
                    
                    <!-- Experience Badge (only if years > 0) -->
                    <?php if ($stats['years'] > 0): ?>
                    <div style="position: absolute; bottom: -20px; right: -20px; width: 120px; height: 120px; background: var(--gold); border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--navy); box-shadow: var(--shadow-xl); border: 5px solid white;">
                        <span style="font-size: 36px; font-weight: 800; line-height: 1;"><?php echo $stats['years']; ?>+</span>
                        <span style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Years</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Impact Stats (only show if any stats > 0) -->
                <?php if ($stats['graduates'] > 0 || $stats['partners'] > 0 || $stats['countries'] > 0 || $stats['programs'] > 0): ?>
                <div style="background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); padding: 40px; border-radius: 20px; color: white; margin-bottom: 30px;">
                    <h3 style="color: var(--gold); margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-chart-line"></i> Our Impact
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px;">
                        <?php if ($stats['graduates'] > 0): ?>
                        <div style="text-align: center;">
                            <div style="font-size: 36px; font-weight: 800; color: var(--gold); line-height: 1; margin-bottom: 8px;"><?php echo number_format($stats['graduates']); ?>+</div>
                            <div style="font-size: 14px; opacity: 0.9;">Professionals Trained</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($stats['partners'] > 0): ?>
                        <div style="text-align: center;">
                            <div style="font-size: 36px; font-weight: 800; color: var(--gold); line-height: 1; margin-bottom: 8px;"><?php echo number_format($stats['partners']); ?>+</div>
                            <div style="font-size: 14px; opacity: 0.9;">Partner Organizations</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($stats['countries'] > 0): ?>
                        <div style="text-align: center;">
                            <div style="font-size: 36px; font-weight: 800; color: var(--gold); line-height: 1; margin-bottom: 8px;"><?php echo number_format($stats['countries']); ?>+</div>
                            <div style="font-size: 14px; opacity: 0.9;">Countries Reached</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($stats['programs'] > 0): ?>
                        <div style="text-align: center;">
                            <div style="font-size: 36px; font-weight: 800; color: var(--gold); line-height: 1; margin-bottom: 8px;"><?php echo $stats['programs']; ?></div>
                            <div style="font-size: 14px; opacity: 0.9;">Core Programs</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Contact (only show if contact info exists) -->
                <?php if (!empty($contact_email) || !empty($contact_phone) || !empty($address)): ?>
                <div style="background: white; padding: 30px; border-radius: 20px; box-shadow: var(--shadow); border: 1px solid var(--gray-200);">
                    <h3 style="margin-bottom: 20px; color: var(--navy); display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-phone-alt" style="color: var(--gold);"></i> Quick Contact
                    </h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php if (!empty($contact_email)): ?>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <i class="fas fa-envelope" style="color: var(--gold); width: 20px;"></i>
                            <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" style="color: var(--navy); text-decoration: none;">
                                <?php echo htmlspecialchars($contact_email); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($contact_phone)): ?>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <i class="fas fa-phone" style="color: var(--gold); width: 20px;"></i>
                            <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $contact_phone); ?>" style="color: var(--navy); text-decoration: none;">
                                <?php echo htmlspecialchars($contact_phone); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($address)): ?>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <i class="fas fa-map-marker-alt" style="color: var(--gold); width: 20px;"></i>
                            <span style="color: var(--gray-600);"><?php echo htmlspecialchars($address); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <hr style="margin: 25px 0; border: none; border-top: 1px solid var(--gray-200);">
                    
                    <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <i class="fas fa-paper-plane"></i> Send Us a Message
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Leadership Team Section (only show if team members exist) -->
<?php if (!empty($team_members)): ?>
<section class="leadership-section" style="padding: 80px 0; background: var(--gray-100);">
    <div class="container">
        <div class="section-header" style="text-align: center; max-width: 700px; margin: 0 auto 50px;">
            <span class="section-subtitle" style="color: var(--gold); display: block; margin-bottom: 15px;">Our Leadership</span>
            <h2 style="font-size: 36px; margin-bottom: 20px; color: var(--navy);">Meet the <span class="text-gold">Team</span></h2>
            <p style="color: var(--gray-600); font-size: 16px;">Experienced professionals committed to your success</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px;">
            <?php foreach ($team_members as $member): ?>
                <div class="team-card" style="background: white; border-radius: 20px; padding: 40px 25px; text-align: center; box-shadow: var(--shadow); border: 1px solid var(--gray-200); transition: var(--transition);">
                    <div style="position: relative; display: inline-block; margin-bottom: 25px;">
                        <?php if (!empty($member['image'])): ?>
                            <img src="<?php echo SITE_URL . '/' . htmlspecialchars($member['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                 style="width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 5px solid var(--gold-light);"
                                 loading="lazy">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($member['name']); ?>&size=140&background=0A1929&color=FFB81C&bold=true" 
                                 alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                 style="width: 140px; height: 140px; border-radius: 50%; border: 5px solid var(--gold-light);"
                                 loading="lazy">
                        <?php endif; ?>
                        
                        <!-- Social Icons (if linkedin exists) -->
                        <?php if (!empty($member['linkedin'])): ?>
                        <a href="<?php echo htmlspecialchars($member['linkedin']); ?>" target="_blank" rel="noopener noreferrer" style="position: absolute; bottom: 0; right: 0; background: var(--gold); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--navy); border: 3px solid white; text-decoration: none;">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <h3 style="font-size: 22px; margin-bottom: 5px; color: var(--navy);"><?php echo htmlspecialchars($member['name']); ?></h3>
                    <p style="color: var(--gold); font-weight: 600; margin-bottom: 15px;"><?php echo htmlspecialchars($member['position']); ?></p>
                    <?php if (!empty($member['bio'])): ?>
                        <p style="color: var(--gray-600); font-size: 14px; line-height: 1.7;"><?php echo htmlspecialchars($member['bio']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA Section -->
<section class="cta-section" style="background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); padding: 80px 0;">
    <div class="container">
        <div class="cta-wrapper" style="max-width: 800px; margin: 0 auto; text-align: center; color: white;">
            <h2 style="color: white; font-size: 36px; margin-bottom: 20px;">Ready to Work With Us?</h2>
            <p style="color: rgba(255,255,255,0.9); font-size: 18px; margin-bottom: 30px;">Let's create something extraordinary together. Get in touch with our team to discuss how we can help you achieve your goals.</p>
            
            <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-primary" style="background: var(--gold); color: var(--navy); padding: 15px 35px; font-size: 16px; border-radius: 50px;">
                    <i class="fas fa-handshake"></i> Contact Us
                </a>
                <a href="<?php echo SITE_URL; ?>/sponsorship.php" class="btn btn-outline-light" style="border: 2px solid white; color: white; padding: 15px 35px; font-size: 16px; border-radius: 50px;">
                    <i class="fas fa-heart"></i> Support AGPN
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Styles -->
<style>
.team-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.team-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-xl);
}

@media (max-width: 768px) {
    .about-grid {
        grid-template-columns: 1fr !important;
        gap: 40px !important;
    }
    
    .page-hero {
        padding: 60px 0 !important;
    }
}
</style>

<!-- Add this script block to ensure navbar works -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle - make sure this runs
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    
    if (navToggle && navMenu) {
        console.log('Navbar elements found - initializing mobile menu');
        
        navToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Toggle classes
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
            
            // Update aria-expanded
            const isExpanded = navMenu.classList.contains('active');
            navToggle.setAttribute('aria-expanded', isExpanded);
            
            console.log('Mobile menu toggled:', isExpanded ? 'open' : 'closed');
        });
        
        // Close menu when clicking on a nav link (for mobile)
        const navLinks = navMenu.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    navMenu.classList.remove('active');
                    navToggle.classList.remove('active');
                    navToggle.setAttribute('aria-expanded', 'false');
                }
            });
        });
    } else {
        console.warn('Navbar elements not found - check IDs: navToggle, navMenu');
        console.log('navToggle:', navToggle);
        console.log('navMenu:', navMenu);
    }
    
    // Close menu when clicking outside (for mobile)
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            const isClickInside = navMenu?.contains(e.target) || navToggle?.contains(e.target);
            
            if (!isClickInside && navMenu?.classList.contains('active')) {
                navMenu.classList.remove('active');
                navToggle?.classList.remove('active');
                navToggle?.setAttribute('aria-expanded', 'false');
            }
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && navMenu?.classList.contains('active')) {
            navMenu.classList.remove('active');
            navToggle?.classList.remove('active');
            navToggle?.setAttribute('aria-expanded', 'false');
        }
    });
});
</script>

<?php
// Page specific JavaScript
// $page_js is already set above
include 'includes/footer.php';
?>