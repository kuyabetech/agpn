<?php
// === Temporary error display - REMOVE in production ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get contact info
$contact_email = getSetting('contact_email', 'info@afroglobeprime.net');
$contact_phone = getSetting('contact_phone', '+234 800 123 4567');
$address = getSetting('address', 'Lagos, Nigeria');
$facebook_url = getSetting('facebook_url', '#');
$twitter_url = getSetting('twitter_url', '#');
$linkedin_url = getSetting('linkedin_url', '#');
$instagram_url = getSetting('instagram_url', '#');
$working_hours = getSetting('working_hours', 'Monday - Friday: 9:00 AM - 6:00 PM');
$google_maps = getSetting('google_maps', '');

// Get session messages
$success = isset($_SESSION['contact_success']) ? $_SESSION['contact_success'] : null;
$error = isset($_SESSION['contact_error']) ? $_SESSION['contact_error'] : null;
unset($_SESSION['contact_success'], $_SESSION['contact_error']);

// Form data for repopulation
$form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
unset($_SESSION['form_data']);

$show_breadcrumbs = true;
$breadcrumbs = [
    ['title' => 'Contact Us']
];
$body_class = 'contact-page';

include 'includes/header.php';
?>

<!-- Add this in header.php or here - critical for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- Add viewport if not already in header.php -->
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* ============================================
   SIMPLE BUT EFFECTIVE CONTACT PAGE STYLES
   GUARANTEED TO DISPLAY THE FORM CARD
============================================ */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Inter', sans-serif;
    line-height: 1.6;
    color: #333;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Hero Section */
.contact-hero {
    background: linear-gradient(135deg, #0A1929 0%, #1A2A3A 100%);
    padding: 60px 0;
    text-align: center;
}

.contact-hero h1 {
    color: #ffffff;
    font-size: 42px;
    margin-bottom: 15px;
}

.contact-hero p {
    color: rgba(255,255,255,0.9);
    font-size: 18px;
    max-width: 600px;
    margin: 0 auto;
}

/* Contact Section */
.contact-section {
    padding: 60px 0;
    background: #f8f9fa;
}

.contact-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 40px;
}

/* Contact Info Column */
.contact-info {
    flex: 1;
    min-width: 300px;
}

.contact-info h2 {
    font-size: 32px;
    margin-bottom: 30px;
    color: #0A1929;
}

.contact-info h2 span {
    color: #FFB81C;
}

.info-card {
    display: flex;
    gap: 20px;
    margin-bottom: 25px;
    padding: 20px;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.info-icon {
    width: 50px;
    height: 50px;
    background: rgba(255,184,28,0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #FFB81C;
    font-size: 20px;
    flex-shrink: 0;
}

.info-content h4 {
    font-size: 18px;
    margin-bottom: 5px;
    color: #0A1929;
}

.info-content p,
.info-content a {
    color: #6c757d;
    text-decoration: none;
    margin: 0;
}

.info-content a:hover {
    color: #FFB81C;
}

.social-links {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.social-link {
    width: 40px;
    height: 40px;
    background: #ffffff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0A1929;
    text-decoration: none;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.social-link:hover {
    background: #FFB81C;
    color: #0A1929;
    transform: translateY(-3px);
}

/* Contact Form Column - THIS IS THE CARD */
.contact-form-column {
    flex: 1;
    min-width: 300px;
}

.form-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
}

.form-card h3 {
    font-size: 28px;
    margin-bottom: 10px;
    color: #0A1929;
}

.form-card h3 span {
    color: #FFB81C;
}

.form-card > p {
    color: #6c757d;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #0A1929;
}

.form-group label span {
    color: #dc3545;
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #FFB81C;
    box-shadow: 0 0 0 3px rgba(255,184,28,0.1);
}

.form-control.error {
    border-color: #dc3545;
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
}

.btn-submit {
    width: 100%;
    padding: 16px;
    background: #FFB81C;
    color: #0A1929;
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
}

.btn-submit:hover {
    background: #FFD700;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(255,184,28,0.3);
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.privacy-note {
    text-align: center;
    margin-top: 20px;
    font-size: 13px;
    color: #adb5bd;
}

.privacy-note a {
    color: #FFB81C;
    text-decoration: none;
}

.privacy-note a:hover {
    text-decoration: underline;
}

/* Form Row for 2 columns */
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.form-row .form-group {
    flex: 1;
    margin-bottom: 0;
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 30px;
    right: 30px;
    z-index: 9999;
}

.toast {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 10px;
    min-width: 300px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 15px;
    animation: slideIn 0.3s ease;
    border-left: 4px solid;
}

.toast.success {
    border-left-color: #28a745;
}

.toast.error {
    border-left-color: #dc3545;
}

.toast-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toast.success .toast-icon {
    background: rgba(40,167,69,0.1);
    color: #28a745;
}

.toast.error .toast-icon {
    background: rgba(220,53,69,0.1);
    color: #dc3545;
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: 700;
    margin-bottom: 4px;
    color: #0A1929;
}

.toast-message {
    font-size: 14px;
    color: #6c757d;
}

.toast-close {
    color: #adb5bd;
    cursor: pointer;
    background: none;
    border: none;
    font-size: 18px;
}

/* Map Section */
.map-section {
    padding: 0 0 60px 0;
    background: #f8f9fa;
}

.map-container {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.map-container iframe {
    width: 100%;
    height: 400px;
    border: 0;
}

/* FAQ Section */
.faq-section {
    padding: 60px 0;
    background: #ffffff;
}

.section-header {
    text-align: center;
    margin-bottom: 40px;
}

.section-subtitle {
    color: #FFB81C;
    display: block;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.section-header h2 {
    font-size: 32px;
    color: #0A1929;
}

.section-header h2 span {
    color: #FFB81C;
}

.faq-grid {
    max-width: 800px;
    margin: 0 auto;
}

.faq-item {
    background: #f8f9fa;
    border-radius: 12px;
    margin-bottom: 15px;
    overflow: hidden;
}

.faq-question {
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    background: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
}

.faq-question h4 {
    margin: 0;
    font-size: 18px;
    color: #0A1929;
}

.faq-question i {
    color: #FFB81C;
    transition: transform 0.3s;
}

.faq-answer {
    padding: 0 20px;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.faq-answer p {
    padding: 20px 0;
    margin: 0;
    color: #6c757d;
}

/* Spinner */
.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(10,25,41,0.1);
    border-radius: 50%;
    border-top-color: #0A1929;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

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

/* Mobile Responsive */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .form-row .form-group {
        margin-bottom: 0;
    }
    
    .form-card {
        padding: 25px;
    }
    
    .toast-container {
        left: 20px;
        right: 20px;
    }
    
    .toast {
        min-width: auto;
        width: 100%;
    }
}
</style>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<!-- Hero Section -->
<section class="contact-hero">
    <div class="container">
        <h1>Contact Us</h1>
        <p>We're here to help and answer any questions you may have about our programs, partnerships, or services.</p>
    </div>
</section>

<!-- Contact Section -->
<section class="contact-section">
    <div class="container">
        <div class="contact-grid">
            
            <!-- LEFT COLUMN - Contact Information -->
            <div class="contact-info">
                <h2>Get in <span>Touch</span></h2>
                
                <!-- Address Card -->
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="info-content">
                        <h4>Office Address</h4>
                        <p><?php echo htmlspecialchars($address); ?></p>
                    </div>
                </div>
                
                <!-- Phone Card -->
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div class="info-content">
                        <h4>Phone Number</h4>
                        <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $contact_phone); ?>">
                            <?php echo htmlspecialchars($contact_phone); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Email Card -->
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="info-content">
                        <h4>Email Address</h4>
                        <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>">
                            <?php echo htmlspecialchars($contact_email); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Working Hours Card -->
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-content">
                        <h4>Working Hours</h4>
                        <p><?php echo nl2br(htmlspecialchars($working_hours)); ?></p>
                    </div>
                </div>
                
                <!-- Social Links -->
                <div class="social-links">
                    <?php if ($facebook_url && $facebook_url !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($facebook_url); ?>" class="social-link" target="_blank" rel="noopener">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($twitter_url && $twitter_url !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($twitter_url); ?>" class="social-link" target="_blank" rel="noopener">
                            <i class="fab fa-twitter"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($linkedin_url && $linkedin_url !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($linkedin_url); ?>" class="social-link" target="_blank" rel="noopener">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($instagram_url && $instagram_url !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($instagram_url); ?>" class="social-link" target="_blank" rel="noopener">
                            <i class="fab fa-instagram"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- RIGHT COLUMN - Contact Form (THE CARD) -->
            <div class="contact-form-column">
                <div class="form-card">
                    <h3>Send us a <span>Message</span></h3>
                    <p>Fill out the form below and we'll get back to you within 24 hours.</p>
                    
                    <form action="forms/contact-handler.php" method="POST" id="contactForm">
                        <input type="hidden" name="csrf_token" value="<?php 
                            echo function_exists('generateCSRFToken') 
                                ? generateCSRFToken() 
                                : (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''); 
                        ?>">
                        
                        <!-- Name and Email Row -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name <span>*</span></label>
                                <input type="text" 
                                       id="full_name" 
                                       name="full_name" 
                                       class="form-control" 
                                       value="<?php echo isset($form_data['full_name']) ? htmlspecialchars($form_data['full_name']) : ''; ?>"
                                       placeholder="John Doe" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address <span>*</span></label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control" 
                                       value="<?php echo isset($form_data['email']) ? htmlspecialchars($form_data['email']) : ''; ?>"
                                       placeholder="john@example.com" 
                                       required>
                            </div>
                        </div>
                        
                        <!-- Phone and Subject Row -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       class="form-control" 
                                       value="<?php echo isset($form_data['phone']) ? htmlspecialchars($form_data['phone']) : ''; ?>"
                                       placeholder="+234 800 123 4567">
                            </div>
                            
                            <div class="form-group">
                                <label for="subject">Subject</label>
                                <input type="text" 
                                       id="subject" 
                                       name="subject" 
                                       class="form-control" 
                                       value="<?php echo isset($form_data['subject']) ? htmlspecialchars($form_data['subject']) : ''; ?>"
                                       placeholder="General Inquiry">
                            </div>
                        </div>
                        
                        <!-- Message Field -->
                        <div class="form-group">
                            <label for="message">Your Message <span>*</span></label>
                            <textarea id="message" 
                                      name="message" 
                                      class="form-control" 
                                      rows="5" 
                                      required
                                      placeholder="Please write your message here..."><?php echo isset($form_data['message']) ? htmlspecialchars($form_data['message']) : ''; ?></textarea>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="fas fa-paper-plane"></i>
                            Send Message
                        </button>
                        
                        <p class="privacy-note">
                            By submitting this form, you agree to our 
                            <a href="<?php echo SITE_URL; ?>/privacy-policy.php">Privacy Policy</a>.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map Section (optional) -->
<?php if (!empty($google_maps)): ?>
<section class="map-section">
    <div class="container">
        <div class="map-container">
            <iframe 
                src="<?php echo htmlspecialchars($google_maps); ?>"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">FAQ</span>
            <h2>Frequently Asked <span>Questions</span></h2>
        </div>
        
        <div class="faq-grid">
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <h4>How quickly do you respond to inquiries?</h4>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>We typically respond to all inquiries within 24 hours during business days. For urgent matters, please call our office directly.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <h4>Do you offer virtual consultations?</h4>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes, we offer virtual consultations via video call for clients who cannot visit our office in person.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <h4>What are your business hours?</h4>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Our office is open Monday through Friday from 9:00 AM to 6:00 PM. We are closed on weekends and public holidays.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Toast Notification System
function showToast(title, message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-${icon}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Form handling & validation
document.addEventListener('DOMContentLoaded', function() {
    console.log('Contact page loaded');
    
    // Show session messages
    <?php if ($success): ?>
        showToast('Success!', `<?php echo addslashes($success); ?>`, 'success');
    <?php endif; ?>
    
    <?php if ($error): ?>
        showToast('Error', `<?php echo addslashes($error); ?>`, 'error');
    <?php endif; ?>
    
    const form = document.getElementById('contactForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        const fullName  = document.getElementById('full_name');
        const email     = document.getElementById('email');
        const message   = document.getElementById('message');
        const submitBtn = document.getElementById('submitBtn');
        
        let isValid = true;
        
        // Reset errors
        [fullName, email, message].forEach(el => el?.classList.remove('error'));
        
        if (!fullName?.value.trim()) {
            fullName?.classList.add('error');
            isValid = false;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email?.value.trim() || !emailRegex.test(email.value.trim())) {
            email?.classList.add('error');
            isValid = false;
        }
        
        if (!message?.value.trim()) {
            message?.classList.add('error');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            showToast('Validation Error', 'Please check the required fields.', 'error');
            return;
        }
        
        // Disable button & show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Sending...';
    });
});

// FAQ Toggle
function toggleFAQ(element) {
    const faqItem = element.closest('.faq-item');
    if (!faqItem) return;
    
    const answer = faqItem.querySelector('.faq-answer');
    const icon = element.querySelector('i');
    if (!answer || !icon) return;
    
    if (answer.style.maxHeight && answer.style.maxHeight !== '0px') {
        answer.style.maxHeight = null;
        icon.style.transform = 'rotate(0deg)';
    } else {
        answer.style.maxHeight = answer.scrollHeight + 'px';
        icon.style.transform = 'rotate(180deg)';
    }
}

</script>

<?php
include 'includes/footer.php';
?>