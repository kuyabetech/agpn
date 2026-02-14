<?php
// ============================================
// ADD TESTIMONIAL PAGE - UPDATED
// ============================================

// Define IN_ADMIN before including any files
define('IN_ADMIN', true);

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
Auth::requireAuth();

// Get current user
$user = Auth::user();
$error = '';
$success = '';

// Get next display order
try {
    $max_order = db()->query("SELECT MAX(display_order) FROM testimonials")->fetchColumn();
    $next_order = $max_order ? $max_order + 1 : 1;
} catch (PDOException $e) {
    error_log("Error fetching max order: " . $e->getMessage());
    $next_order = 1;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = sanitize($_POST['client_name']);
    $client_position = sanitize($_POST['client_position']);
    $company = sanitize($_POST['company']);
    $testimonial_text = sanitize($_POST['testimonial_text']);
    $rating = (int)sanitize($_POST['rating']);
    $display_order = (int)sanitize($_POST['display_order']);
    $status = sanitize($_POST['status']);
    
    // Handle client photo upload
    $client_photo = null;
    if (isset($_FILES['client_photo']) && $_FILES['client_photo']['error'] === 0) {
        $upload = uploadImage($_FILES['client_photo'], 'testimonials');
        if ($upload['success']) {
            $client_photo = $upload['path'];
        } else {
            $_SESSION['error'] = $upload['error'];
        }
    }
    
    // Validate
    if (empty($client_name)) {
        $_SESSION['error'] = 'Client name is required';
    } elseif (empty($testimonial_text)) {
        $_SESSION['error'] = 'Testimonial text is required';
    } elseif (strlen($testimonial_text) < 10) {
        $_SESSION['error'] = 'Testimonial text must be at least 10 characters';
    } else {
        try {
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO testimonials (
                    client_name, client_position, company, testimonial_text, 
                    client_photo, rating, display_order, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $client_name,
                $client_position ?: null,
                $company ?: null,
                $testimonial_text,
                $client_photo,
                $rating ?: null,
                $display_order ?: 0,
                $status ?: 'active'
            ]);
            
            $_SESSION['success'] = 'Testimonial added successfully!';
            redirect('testimonials.php');
            
        } catch (PDOException $e) {
            error_log("Error adding testimonial: " . $e->getMessage());
            $_SESSION['error'] = 'Error adding testimonial. Please try again.';
        }
    }
    redirect('add-testimonial.php');
}

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? $error ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Set page title
$page_title = 'Add New Testimonial';

// Breadcrumbs
$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => 'testimonials.php', 'title' => 'Testimonials'],
    ['url' => '#', 'title' => 'Add New']
];

// Page specific CSS
$page_css = ['testimonial-form.css'];

// Include admin header
require_once '../includes/admin-header.php';
?>

<!-- Page Specific Styles -->
<style>
    .testimonial-form {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .rating-stars {
        display: flex;
        gap: 10px;
        font-size: 24px;
        color: var(--gray-300);
        cursor: pointer;
        margin-top: 10px;
    }
    
    .rating-stars i {
        transition: all 0.2s ease;
    }
    
    .rating-stars i.active {
        color: #ffc107;
    }
    
    .rating-stars i:hover {
        transform: scale(1.2);
    }
    
    .photo-preview-container {
        text-align: center;
        margin-bottom: 20px;
    }
    
    .photo-preview {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--gold);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        display: none;
    }
    
    .photo-preview.active {
        display: block;
        margin: 0 auto;
    }
    
    .photo-placeholder {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        background: var(--gray-100);
        border: 2px dashed var(--gray-300);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        color: var(--gray-500);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .photo-placeholder:hover {
        border-color: var(--gold);
        background: rgba(255,184,28,0.05);
    }
    
    .photo-placeholder i {
        font-size: 48px;
        margin-bottom: 10px;
        color: var(--gold);
    }
    
    .char-counter {
        font-size: 12px;
        color: var(--gray-600);
        text-align: right;
        margin-top: 5px;
    }
    
    .char-counter.warning {
        color: #ffc107;
    }
    
    .char-counter.danger {
        color: #dc3545;
    }
    
    .preview-card {
        background: white;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: 1px solid var(--gray-200);
        margin-top: 30px;
    }
    
    .preview-quote {
        font-size: 32px;
        color: var(--gold);
        opacity: 0.3;
        margin-bottom: 15px;
    }
    
    .preview-text {
        font-style: italic;
        color: var(--gray-900);
        line-height: 1.8;
        margin-bottom: 20px;
        font-size: 16px;
    }
    
    .preview-author {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    
    .preview-avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: var(--gray-500);
        overflow: hidden;
    }
    
    .preview-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .preview-info h4 {
        margin-bottom: 5px;
        color: var(--navy);
        font-size: 18px;
    }
    
    .preview-info p {
        color: var(--gray-600);
        margin-bottom: 8px;
    }
    
    .preview-rating {
        color: #ffc107;
        display: flex;
        gap: 4px;
    }
    
    .grid-2 {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 25px;
    }
    
    @media (max-width: 992px) {
        .grid-2 {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Toast Notifications -->
<?php if ($success): ?>
    <div class="toast-notification" id="successToast">
        <div style="background: #D4EDDA; color: #155724; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #28a745; display: flex; align-items: center; gap: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
            <i class="fas fa-check-circle" style="font-size: 20px;"></i> 
            <span style="flex: 1; font-size: 14px;"><?php echo htmlspecialchars($success); ?></span>
            <button onclick="this.closest('.toast-notification').remove()" style="background: none; border: none; color: #155724; cursor: pointer; padding: 5px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="toast-notification" id="errorToast">
        <div style="background: #F8D7DA; color: #721C24; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #dc3545; display: flex; align-items: center; gap: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
            <i class="fas fa-exclamation-circle" style="font-size: 20px;"></i> 
            <span style="flex: 1; font-size: 14px;"><?php echo htmlspecialchars($error); ?></span>
            <button onclick="this.closest('.toast-notification').remove()" style="background: none; border: none; color: #721C24; cursor: pointer; padding: 5px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h1 style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
            <i class="fas fa-plus-circle" style="color: var(--gold);"></i>
            Add New Testimonial
        </h1>
        <div style="color: var(--gray-600);">
            <i class="fas fa-quote-right"></i> 
            Create a new client testimonial
        </div>
    </div>
    
    <div style="display: flex; gap: 15px;">
        <a href="testimonials.php" class="btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
        <button type="submit" form="testimonialForm" class="btn-primary">
            <i class="fas fa-save"></i> Save Testimonial
        </button>
    </div>
</div>

<div class="testimonial-form">
    <form method="POST" action="" id="testimonialForm" enctype="multipart/form-data">
        <div class="grid-2">
            <!-- Main Content Column -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-quote-right" style="color: var(--gold);"></i> Testimonial Details</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Client Name <span style="color: #dc3545;">*</span></label>
                        <input type="text" name="client_name" class="form-control form-control-lg" 
                               value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>" 
                               placeholder="e.g., John Smith" 
                               required
                               id="clientName">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Client Position</label>
                            <input type="text" name="client_position" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['client_position'] ?? ''); ?>" 
                                   placeholder="e.g., CEO"
                                   id="clientPosition">
                        </div>
                        
                        <div class="form-group">
                            <label>Company</label>
                            <input type="text" name="company" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>" 
                                   placeholder="e.g., Tech Corp"
                                   id="company">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Testimonial Text <span style="color: #dc3545;">*</span></label>
                        <textarea name="testimonial_text" class="form-control" 
                                  rows="6" placeholder="Write the client's testimonial here..."
                                  id="testimonialText"
                                  required><?php echo htmlspecialchars($_POST['testimonial_text'] ?? ''); ?></textarea>
                        <div class="char-counter" id="charCount">0 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Rating</label>
                        <div class="rating-stars" id="ratingStars">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                        </div>
                        <input type="hidden" name="rating" id="ratingValue" value="5">
                    </div>
                </div>
            </div>
            
            <!-- Sidebar Column -->
            <div style="display: flex; flex-direction: column; gap: 25px;">
                <!-- Settings Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cog" style="color: var(--gold);"></i> Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?php echo ($_POST['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($_POST['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Display Order</label>
                            <input type="number" name="display_order" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['display_order'] ?? $next_order); ?>" 
                                   min="0">
                            <small style="color: var(--gray-600);">Lower numbers appear first</small>
                        </div>
                    </div>
                </div>
                
                <!-- Client Photo Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-camera" style="color: var(--gold);"></i> Client Photo</h3>
                    </div>
                    <div class="card-body">
                        <div class="photo-preview-container">
                            <img src="" class="photo-preview" id="photoPreview">
                            <div class="photo-placeholder" id="photoPlaceholder" onclick="document.getElementById('client_photo').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span style="font-size: 14px;">Click to upload photo</span>
                            </div>
                        </div>
                        
                        <input type="file" name="client_photo" id="client_photo" 
                               accept="image/*" style="display: none;">
                        
                        <div style="display: flex; gap: 10px; justify-content: center; margin-top: 10px;">
                            <button type="button" class="btn-secondary" id="choosePhotoBtn">
                                <i class="fas fa-upload"></i> Choose Photo
                            </button>
                            <button type="button" class="btn-secondary" id="removePhotoBtn" style="display: none;">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
                        
                        <small style="display: block; margin-top: 15px; color: var(--gray-600); text-align: center;">
                            <i class="fas fa-info-circle"></i> Recommended: Square image, at least 300x300px
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <!-- Live Preview -->
    <div class="preview-card">
        <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-eye" style="color: var(--gold);"></i>
            Live Preview
        </h3>
        
        <div class="preview-quote">
            <i class="fas fa-quote-left"></i>
        </div>
        
        <div class="preview-text" id="previewText">
            Your testimonial text will appear here...
        </div>
        
        <div class="preview-author">
            <div class="preview-avatar" id="previewAvatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="preview-info">
                <h4 id="previewName">Client Name</h4>
                <p id="previewTitle">Position, Company</p>
                <div class="preview-rating" id="previewRating">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// ADD TESTIMONIAL - JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ============================================
    // RATING STARS FUNCTIONALITY
    // ============================================
    const stars = document.querySelectorAll('.rating-stars i');
    const ratingInput = document.getElementById('ratingValue');
    const previewRating = document.getElementById('previewRating');
    
    function updateRatingDisplay(rating) {
        stars.forEach((star, i) => {
            if (i < rating) {
                star.classList.remove('far');
                star.classList.add('fas', 'active');
            } else {
                star.classList.remove('fas', 'active');
                star.classList.add('far');
            }
        });
    }
    
    function updatePreviewRating(rating) {
        if (previewRating) {
            previewRating.innerHTML = '';
            for (let i = 1; i <= 5; i++) {
                const star = document.createElement('i');
                star.className = i <= rating ? 'fas fa-star' : 'far fa-star';
                previewRating.appendChild(star);
            }
        }
    }
    
    // Star hover effects
    stars.forEach((star, index) => {
        star.addEventListener('mouseenter', function() {
            stars.forEach((s, i) => {
                if (i <= index) {
                    s.classList.remove('far');
                    s.classList.add('fas', 'active');
                } else {
                    s.classList.remove('fas', 'active');
                    s.classList.add('far');
                }
            });
        });
        
        star.addEventListener('click', function() {
            const rating = index + 1;
            ratingInput.value = rating;
            updatePreviewRating(rating);
        });
    });
    
    // Reset stars on mouse leave
    stars.forEach(star => {
        star.addEventListener('mouseleave', function() {
            const currentRating = parseInt(ratingInput.value) || 5;
            updateRatingDisplay(currentRating);
        });
    });
    
    // Set default rating
    updateRatingDisplay(5);
    updatePreviewRating(5);
    
    // ============================================
    // CHARACTER COUNTER
    // ============================================
    const testimonialText = document.getElementById('testimonialText');
    const charCount = document.getElementById('charCount');
    
    function updateCharCount() {
        if (testimonialText && charCount) {
            const count = testimonialText.value.length;
            charCount.textContent = count + ' characters';
            
            if (count > 500) {
                charCount.className = 'char-counter warning';
            } else if (count > 1000) {
                charCount.className = 'char-counter danger';
            } else {
                charCount.className = 'char-counter';
            }
        }
    }
    
    if (testimonialText) {
        testimonialText.addEventListener('keyup', updateCharCount);
        testimonialText.addEventListener('change', updateCharCount);
        updateCharCount();
    }
    
    // ============================================
    // LIVE PREVIEW UPDATE
    // ============================================
    const clientName = document.getElementById('clientName');
    const clientPosition = document.getElementById('clientPosition');
    const company = document.getElementById('company');
    const previewName = document.getElementById('previewName');
    const previewTitle = document.getElementById('previewTitle');
    const previewText = document.getElementById('previewText');
    
    function updatePreview() {
        if (previewName) {
            previewName.textContent = clientName?.value || 'Client Name';
        }
        
        if (previewTitle) {
            let titleText = clientPosition?.value || 'Position';
            if (company?.value) {
                titleText += ', ' + company.value;
            }
            previewTitle.textContent = titleText;
        }
        
        if (previewText) {
            previewText.textContent = testimonialText?.value || 'Your testimonial text will appear here...';
        }
    }
    
    if (clientName) clientName.addEventListener('keyup', updatePreview);
    if (clientPosition) clientPosition.addEventListener('keyup', updatePreview);
    if (company) company.addEventListener('keyup', updatePreview);
    if (testimonialText) testimonialText.addEventListener('keyup', updatePreview);
    
    // ============================================
    // IMAGE PREVIEW
    // ============================================
    const photoInput = document.getElementById('client_photo');
    const photoPreview = document.getElementById('photoPreview');
    const photoPlaceholder = document.getElementById('photoPlaceholder');
    const choosePhotoBtn = document.getElementById('choosePhotoBtn');
    const removePhotoBtn = document.getElementById('removePhotoBtn');
    const previewAvatar = document.getElementById('previewAvatar');
    
    function previewImage(file) {
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Show image preview
                photoPreview.src = e.target.result;
                photoPreview.classList.add('active');
                if (photoPlaceholder) photoPlaceholder.style.display = 'none';
                if (removePhotoBtn) removePhotoBtn.style.display = 'inline-flex';
                
                // Update preview avatar
                if (previewAvatar) {
                    previewAvatar.innerHTML = `<img src="${e.target.result}" alt="Client photo">`;
                }
            };
            reader.readAsDataURL(file);
        }
    }
    
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                previewImage(this.files[0]);
            }
        });
    }
    
    if (choosePhotoBtn) {
        choosePhotoBtn.addEventListener('click', function() {
            photoInput.click();
        });
    }
    
    function removeImage() {
        photoInput.value = '';
        photoPreview.src = '';
        photoPreview.classList.remove('active');
        if (photoPlaceholder) photoPlaceholder.style.display = 'flex';
        if (removePhotoBtn) removePhotoBtn.style.display = 'none';
        
        // Reset preview avatar
        if (previewAvatar) {
            previewAvatar.innerHTML = '<i class="fas fa-user"></i>';
        }
    }
    
    if (removePhotoBtn) {
        removePhotoBtn.addEventListener('click', removeImage);
    }
    
    // ============================================
    // TOAST NOTIFICATIONS AUTO-HIDE
    // ============================================
    const successToast = document.getElementById('successToast');
    const errorToast = document.getElementById('errorToast');
    
    function hideToast(toast) {
        if (toast) {
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(function() {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }
    }
    
    if (successToast) {
        setTimeout(function() { hideToast(successToast); }, 5000);
    }
    
    if (errorToast) {
        setTimeout(function() { hideToast(errorToast); }, 5000);
    }
    
    // ============================================
    // ADD SLIDEOUT ANIMATION
    // ============================================
    const style = document.createElement('style');
    style.textContent = `
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
    `;
    
    if (!document.querySelector('style[data-animation="slideOut"]')) {
        style.setAttribute('data-animation', 'slideOut');
        document.head.appendChild(style);
    }
    
}); // End DOMContentLoaded
</script>

<?php
// Include admin footer
require_once '../includes/admin-footer.php';
?>