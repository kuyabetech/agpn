    </main>
    
    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <!-- Footer Main Grid -->
            <div class="footer-grid">
                <!-- About Column -->
                <div class="footer-col footer-about">
                    <div class="footer-logo">
                        <?php 
                        $site_logo = getSetting('site_logo', '');
                        if (!empty($site_logo) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $site_logo)): 
                        ?>
                            <img src="<?php echo SITE_URL . '/' . htmlspecialchars($site_logo); ?>" 
                                 alt="<?php echo SITE_NAME; ?>" 
                                 width="75" 
                                 height="75"
                                 class="footer-logo-image"
                                 loading="lazy" style="border-radius:50%; margin:10px;">
                        <?php else: ?>
                            <span class="footer-logo-text">AGPN</span>
                            <span class="footer-logo-tagline">Prime Network</span>
                        <?php endif; ?>
                    </div>
                    
                    <p class="footer-description"><?php echo htmlspecialchars(getSetting('site_tagline', 'Empowering Global Excellence')); ?></p>
                    
                    <div class="social-links">
                        <?php if (getSetting('facebook_url', '#') != '#'): ?>
                            <a href="<?php echo htmlspecialchars(getSetting('facebook_url', '#')); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               aria-label="Follow us on Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (getSetting('twitter_url', '#') != '#'): ?>
                            <a href="<?php echo htmlspecialchars(getSetting('twitter_url', '#')); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               aria-label="Follow us on Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (getSetting('linkedin_url', '#') != '#'): ?>
                            <a href="<?php echo htmlspecialchars(getSetting('linkedin_url', '#')); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               aria-label="Follow us on LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (getSetting('instagram_url', '#') != '#'): ?>
                            <a href="<?php echo htmlspecialchars(getSetting('instagram_url', '#')); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               aria-label="Follow us on Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (getSetting('youtube_url', '') != ''): ?>
                            <a href="<?php echo htmlspecialchars(getSetting('youtube_url', '')); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               aria-label="Subscribe to our YouTube channel">
                                <i class="fab fa-youtube"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Links Column -->
                <div class="footer-col">
                    <h4 class="footer-title">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>/about.php">About Us</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/arms.php">The 6 Arms</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/blog.php">Blog & Insights</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/testimonials.php">Testimonials</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/contact.php">Contact Us</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/faq.php">FAQ</a></li>
                    </ul>
                </div>
                
                <!-- Programs Column -->
                <div class="footer-col">
                    <h4 class="footer-title">Our Programs</h4>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>/digital-skills.php">Digital Skills Training</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/business-growth.php">Business Growth</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/study-abroad.php">Study Abroad</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/sponsorship.php">Sponsorship</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/certifications.php">Certifications</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/partnership.php">Partnerships</a></li>
                    </ul>
                </div>
                
                <!-- Contact Column -->
                <div class="footer-col">
                    <h4 class="footer-title">Contact Info</h4>
                    <ul class="contact-info">
                        <?php if (getSetting('address', '') != ''): ?>
                            <li>
                                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                                <span><?php echo htmlspecialchars(getSetting('address', 'Lagos, Nigeria')); ?></span>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (getSetting('contact_phone', '') != ''): ?>
                            <li>
                                <i class="fas fa-phone" aria-hidden="true"></i>
                                <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', getSetting('contact_phone', '')); ?>">
                                    <?php echo htmlspecialchars(getSetting('contact_phone', '')); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (getSetting('contact_email', '') != ''): ?>
                            <li>
                                <i class="fas fa-envelope" aria-hidden="true"></i>
                                <a href="mailto:<?php echo htmlspecialchars(getSetting('contact_email', '')); ?>">
                                    <?php echo htmlspecialchars(getSetting('contact_email', '')); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (getSetting('office_hours', '') != ''): ?>
                            <li>
                                <i class="fas fa-clock" aria-hidden="true"></i>
                                <span><?php echo htmlspecialchars(getSetting('office_hours', '')); ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <!-- Newsletter Signup (Optional) -->
                    <?php if (getSetting('footer_newsletter', '1') == '1'): ?>
                    <div class="footer-newsletter">
                        <h5>Subscribe to Newsletter</h5>
                        <form action="<?php echo SITE_URL; ?>/forms/newsletter-footer.php" method="POST" class="newsletter-form-footer">
                            <div class="input-group">
                                <input type="email" 
                                       name="email" 
                                       placeholder="Your email address" 
                                       required
                                       class="newsletter-input">
                                <button type="submit" class="newsletter-btn" aria-label="Subscribe">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                            <p class="privacy-note">We respect your privacy</p>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Footer Bottom Bar -->
            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <p class="copyright">
                        <?php 
                        $copyright = getSetting('footer_copyright', '© 2025 Afroglobe Prime Network Limited. All Rights Reserved.');
                        echo htmlspecialchars($copyright); 
                        ?>
                    </p>
                    
                    <div class="footer-bottom-links">
                        <a href="<?php echo SITE_URL; ?>/privacy-policy.php">Privacy Policy</a>
                        <span class="separator">|</span>
                        <a href="<?php echo SITE_URL; ?>/terms-of-service.php">Terms of Service</a>
                        <span class="separator">|</span>
                        <a href="<?php echo SITE_URL; ?>/sitemap.php">Sitemap</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Back to Top Button -->
    <button id="backToTop" class="back-to-top" aria-label="Back to top">
        <i class="fas fa-arrow-up" aria-hidden="true"></i>
    </button>

    <!-- Cookie Consent Popup Modal -->
    <div id="cookieConsent" class="cookie-modal" style="display: none;">
        <div class="cookie-modal-content">
            <div class="cookie-modal-header">
                <i class="fas fa-cookie-bite cookie-icon"></i>
                <h3>Cookie Consent</h3>
            </div>
            
            <div class="cookie-modal-body">
                <p>We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.</p>
                
                <div class="cookie-preferences">
                    <div class="cookie-preference-item">
                        <label class="cookie-toggle">
                            <input type="checkbox" id="necessaryCookies" checked disabled>
                            <span class="toggle-slider"></span>
                        </label>
                        <div class="preference-text">
                            <strong>Necessary</strong>
                            <span>Required for basic site functionality</span>
                        </div>
                    </div>
                    
                    <div class="cookie-preference-item">
                        <label class="cookie-toggle">
                            <input type="checkbox" id="analyticsCookies">
                            <span class="toggle-slider"></span>
                        </label>
                        <div class="preference-text">
                            <strong>Analytics</strong>
                            <span>Help us improve our website</span>
                        </div>
                    </div>
                    
                    <div class="cookie-preference-item">
                        <label class="cookie-toggle">
                            <input type="checkbox" id="marketingCookies">
                            <span class="toggle-slider"></span>
                        </label>
                        <div class="preference-text">
                            <strong>Marketing</strong>
                            <span>Personalized ads and content</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="cookie-modal-footer">
                <button class="btn btn-outline" id="cookieCustomize">Customize</button>
                <button class="btn btn-outline" id="cookieDecline">Decline All</button>
                <button class="btn btn-primary" id="cookieAccept">Accept All</button>
            </div>
            
            <div class="cookie-modal-footer-simple" style="display: none;">
                <button class="btn btn-outline" id="cookieDeclineSimple">Decline</button>
                <button class="btn btn-primary" id="cookieAcceptSimple">Accept All</button>
            </div>
            
            <div class="cookie-modal-link">
                <a href="<?php echo SITE_URL; ?>/privacy-policy.php">Privacy Policy</a> | 
                <a href="<?php echo SITE_URL; ?>/cookie-policy.php">Cookie Policy</a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- jQuery (only if needed - consider removing if not required) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
    
    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js" defer></script>
    
    <!-- Main JavaScript -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js?v=<?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/js/main.js') ? filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/js/main.js') : '1.0'; ?>" defer></script>
    
    <!-- Page Specific JavaScript -->
    <?php if (isset($page_js)): ?>
        <?php 
        $js_files = is_array($page_js) ? $page_js : [$page_js];
        foreach ($js_files as $js_file):
            $clean_js = preg_replace('/[^a-zA-Z0-9\-\.]/', '', $js_file);
            $js_path = $_SERVER['DOCUMENT_ROOT'] . '/assets/js/' . $clean_js;
            $js_version = file_exists($js_path) ? filemtime($js_path) : '1.0';
        ?>
            <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $clean_js; ?>?v=<?php echo $js_version; ?>" defer></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Custom JavaScript from Settings -->
    <?php $custom_js = getSetting('custom_js', ''); ?>
    <?php if (!empty($custom_js)): ?>
        <script>
            <?php echo $custom_js; ?>
        </script>
    <?php endif; ?>
    
    <!-- Cookie Consent and Initialization Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize AOS
            if (typeof AOS !== 'undefined') {
                AOS.init({
                    duration: 800,
                    once: true,
                    offset: 100,
                    disable: 'mobile'
                });
            }
            
            // Back to Top Button
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
            
            // Cookie Consent Popup
            const cookieModal = document.getElementById('cookieConsent');
            const customizeBtn = document.getElementById('cookieCustomize');
            const acceptBtn = document.getElementById('cookieAccept');
            const acceptSimpleBtn = document.getElementById('cookieAcceptSimple');
            const declineBtn = document.getElementById('cookieDecline');
            const declineSimpleBtn = document.getElementById('cookieDeclineSimple');
            const advancedFooter = document.querySelector('.cookie-modal-footer');
            const simpleFooter = document.querySelector('.cookie-modal-footer-simple');
            
            // Check if user has already made a choice
            const cookieConsent = localStorage.getItem('cookieConsent');
            const cookiePreferences = localStorage.getItem('cookiePreferences');
            
            if (!cookieConsent) {
                // Show popup after 2 seconds
                setTimeout(() => {
                    cookieModal.style.display = 'flex';
                    document.body.style.overflow = 'hidden'; // Prevent scrolling
                }, 2000);
            } else if (cookieConsent === 'custom' && cookiePreferences) {
                // Apply saved preferences
                const prefs = JSON.parse(cookiePreferences);
                document.getElementById('analyticsCookies').checked = prefs.analytics || false;
                document.getElementById('marketingCookies').checked = prefs.marketing || false;
            }
            
            // Toggle between simple and advanced view
            if (customizeBtn) {
                customizeBtn.addEventListener('click', function() {
                    advancedFooter.style.display = 'none';
                    simpleFooter.style.display = 'flex';
                });
            }
            
            // Accept All Cookies
            function acceptAllCookies() {
                document.getElementById('analyticsCookies').checked = true;
                document.getElementById('marketingCookies').checked = true;
                
                const preferences = {
                    necessary: true,
                    analytics: true,
                    marketing: true
                };
                
                localStorage.setItem('cookieConsent', 'accepted');
                localStorage.setItem('cookiePreferences', JSON.stringify(preferences));
                
                cookieModal.style.display = 'none';
                document.body.style.overflow = '';
                
                // Enable analytics and marketing scripts here
                enableTrackingScripts(true, true);
            }
            
            if (acceptBtn) {
                acceptBtn.addEventListener('click', acceptAllCookies);
            }
            
            if (acceptSimpleBtn) {
                acceptSimpleBtn.addEventListener('click', acceptAllCookies);
            }
            
            // Decline All Cookies
            function declineAllCookies() {
                document.getElementById('analyticsCookies').checked = false;
                document.getElementById('marketingCookies').checked = false;
                
                const preferences = {
                    necessary: true,
                    analytics: false,
                    marketing: false
                };
                
                localStorage.setItem('cookieConsent', 'declined');
                localStorage.setItem('cookiePreferences', JSON.stringify(preferences));
                
                cookieModal.style.display = 'none';
                document.body.style.overflow = '';
                
                // Disable analytics and marketing scripts
                enableTrackingScripts(false, false);
            }
            
            if (declineBtn) {
                declineBtn.addEventListener('click', declineAllCookies);
            }
            
            if (declineSimpleBtn) {
                declineSimpleBtn.addEventListener('click', declineAllCookies);
            }
            
            // Save custom preferences
            window.saveCookiePreferences = function() {
                const analytics = document.getElementById('analyticsCookies').checked;
                const marketing = document.getElementById('marketingCookies').checked;
                
                const preferences = {
                    necessary: true,
                    analytics: analytics,
                    marketing: marketing
                };
                
                localStorage.setItem('cookieConsent', 'custom');
                localStorage.setItem('cookiePreferences', JSON.stringify(preferences));
                
                cookieModal.style.display = 'none';
                document.body.style.overflow = '';
                
                // Enable/disable scripts based on preferences
                enableTrackingScripts(analytics, marketing);
            };
            
            // Function to enable/disable tracking scripts
            function enableTrackingScripts(enableAnalytics, enableMarketing) {
                // This function will be called when cookies are accepted/declined
                // You can implement dynamic script loading here
                
                if (enableAnalytics) {
                    // Load analytics scripts
                    console.log('Analytics enabled');
                }
                
                if (enableMarketing) {
                    // Load marketing scripts
                    console.log('Marketing enabled');
                }
            }
            
            // Close Announcement Bar
            window.closeAnnouncement = function() {
                const bar = document.getElementById('announcementBar');
                if (bar) {
                    bar.style.display = 'none';
                    document.cookie = 'announcement_closed=1; path=/; max-age=86400';
                }
            };
            
            // Click outside to close (optional)
            window.addEventListener('click', function(e) {
                if (e.target === cookieModal) {
                    // Don't close when clicking outside - forces user to make choice
                    // Comment this out if you want to allow closing by clicking outside
                }
            });
        });
    </script>
    
    <!-- Structured Data for Local Business -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "<?php echo SITE_NAME; ?>",
        "image": "<?php echo SITE_URL; ?>/assets/images/logo.png",
        "url": "<?php echo SITE_URL; ?>",
        "telephone": "<?php echo preg_replace('/[^0-9+]/', '', getSetting('contact_phone', '')); ?>",
        "email": "<?php echo getSetting('contact_email', ''); ?>",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "<?php echo getSetting('address', 'Lagos'); ?>",
            "addressLocality": "Lagos",
            "addressCountry": "NG"
        },
        "openingHoursSpecification": [
            {
                "@type": "OpeningHoursSpecification",
                "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"],
                "opens": "09:00",
                "closes": "18:00"
            }
        ],
        "sameAs": [
            "<?php echo getSetting('facebook_url', '#'); ?>",
            "<?php echo getSetting('twitter_url', '#'); ?>",
            "<?php echo getSetting('linkedin_url', '#'); ?>",
            "<?php echo getSetting('instagram_url', '#'); ?>"
        ]
    }
    </script>
    
    <!-- Performance Markers -->
    <script>
        window.addEventListener('load', function() {
            if (window.performance) {
                const perfData = window.performance.timing;
                const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
                console.log('Page loaded in: ' + pageLoadTime + 'ms');
            }
        });
    </script>
    
    <!-- CSS for Cookie Modal -->
    <style>
        .cookie-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .cookie-modal-content {
            background: white;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.4s ease;
        }
        
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .cookie-modal-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .cookie-icon {
            font-size: 40px;
            color: var(--gold, #FFB81C);
        }
        
        .cookie-modal-header h3 {
            font-size: 24px;
            margin: 0;
            color: var(--navy, #0A1929);
        }
        
        .cookie-modal-body {
            margin-bottom: 25px;
        }
        
        .cookie-modal-body p {
            color: var(--gray-600, #666);
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .cookie-preferences {
            background: var(--gray-100, #f8f9fa);
            border-radius: 12px;
            padding: 15px;
        }
        
        .cookie-preference-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-200, #eee);
        }
        
        .cookie-preference-item:last-child {
            border-bottom: none;
        }
        
        .cookie-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            flex-shrink: 0;
        }
        
        .cookie-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--gray-300, #ccc);
            transition: 0.3s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--gold, #FFB81C);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        input:disabled + .toggle-slider {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .preference-text {
            flex: 1;
        }
        
        .preference-text strong {
            display: block;
            color: var(--navy, #0A1929);
            margin-bottom: 3px;
        }
        
        .preference-text span {
            font-size: 13px;
            color: var(--gray-600, #666);
        }
        
        .cookie-modal-footer {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .cookie-modal-footer-simple {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .cookie-modal-link {
            text-align: center;
            font-size: 13px;
        }
        
        .cookie-modal-link a {
            color: var(--gold, #FFB81C);
            text-decoration: none;
        }
        
        .cookie-modal-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .cookie-modal-content {
                padding: 20px;
            }
            
            .cookie-modal-header h3 {
                font-size: 20px;
            }
            
            .cookie-modal-footer {
                flex-wrap: wrap;
            }
            
            .cookie-modal-footer .btn {
                flex: 1;
                min-width: 120px;
            }
        }
    </style>
</body>
</html>