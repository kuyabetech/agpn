<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAuth();

$user = Auth::user();
$testimonial_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$testimonial_id) {
    redirect('testimonials.php');
}

// Get testimonial data
try {
    $stmt = db()->prepare("SELECT * FROM testimonials WHERE id = ?");
    $stmt->execute([$testimonial_id]);
    $testimonial = $stmt->fetch();
    
    if (!$testimonial) {
        redirect('testimonials.php');
    }
} catch (PDOException $e) {
    error_log("Error fetching testimonial: " . $e->getMessage());
    redirect('testimonials.php');
}

$error = '';
$success = '';

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
    $client_photo = $testimonial['client_photo'];
    if (isset($_FILES['client_photo']) && $_FILES['client_photo']['error'] === 0) {
        $upload = uploadImage($_FILES['client_photo'], 'testimonials');
        if ($upload['success']) {
            // Delete old photo if exists
            if ($client_photo && file_exists('../' . $client_photo)) {
                unlink('../' . $client_photo);
            }
            $client_photo = $upload['path'];
        } else {
            $error = $upload['error'];
        }
    }
    
    // Remove photo if requested
    if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
        if ($client_photo && file_exists('../' . $client_photo)) {
            unlink('../' . $client_photo);
        }
        $client_photo = null;
    }
    
    // Validate
    if (empty($client_name)) {
        $error = 'Client name is required';
    } elseif (empty($testimonial_text)) {
        $error = 'Testimonial text is required';
    } elseif (strlen($testimonial_text) < 10) {
        $error = 'Testimonial text must be at least 10 characters';
    } else {
        try {
            $stmt = db()->prepare("
                UPDATE testimonials SET
                    client_name = ?, client_position = ?, company = ?, 
                    testimonial_text = ?, client_photo = ?, rating = ?, 
                    display_order = ?, status = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $client_name,
                $client_position ?: null,
                $company ?: null,
                $testimonial_text,
                $client_photo,
                $rating ?: null,
                $display_order ?: 0,
                $status,
                $testimonial_id
            ]);
            
            $_SESSION['success'] = 'Testimonial updated successfully!';
            redirect('testimonials.php');
            
        } catch (PDOException $e) {
            error_log("Error updating testimonial: " . $e->getMessage());
            $error = 'Error updating testimonial. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Testimonial - AGPN Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        .testimonial-form {
            max-width: 900px;
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
        
        .photo-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--gold);
            margin-top: 15px;
        }
        
        .char-counter {
            font-size: 12px;
            color: var(--gray-600);
            text-align: right;
            margin-top: 5px;
        }
        
        .preview-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
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
        }
        
        .preview-author {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .preview-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--gray-500);
            overflow: hidden;
        }
        
        .preview-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .delete-photo-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #dc3545;
            background: rgba(220,53,69,0.1);
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .delete-photo-btn:hover {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- ... same sidebar as add-testimonial.php ... -->
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-title">
                        <h1>Edit Testimonial</h1>
                        <span class="breadcrumb">
                            <a href="dashboard.php">Dashboard</a> / 
                            <a href="testimonials.php">Testimonials</a> / 
                            Edit
                        </span>
                    </div>
                </div>
                
                <div class="header-right">
                    <a href="testimonials.php" class="btn-secondary" style="margin-right: 10px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button type="submit" form="testimonialForm" class="btn-primary">
                        <i class="fas fa-save"></i> Update Testimonial
                    </button>
                </div>
            </header>
            
            <?php if ($error): ?>
                <div style="background: #F8D7DA; color: #721C24; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="testimonial-form">
                <form method="POST" action="" id="testimonialForm" enctype="multipart/form-data">
                    <div class="grid" style="grid-template-columns: 2fr 1fr; gap: 25px;">
                        <!-- Main Content Column -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-quote-right"></i> Testimonial Details</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Client Name <span style="color: #dc3545;">*</span></label>
                                    <input type="text" name="client_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($testimonial['client_name']); ?>" 
                                           placeholder="e.g., John Smith" required
                                           id="clientName" onkeyup="updatePreview()">
                                </div>
                                
                                <div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group">
                                        <label>Client Position</label>
                                        <input type="text" name="client_position" class="form-control" 
                                               value="<?php echo htmlspecialchars($testimonial['client_position'] ?? ''); ?>" 
                                               placeholder="e.g., CEO"
                                               id="clientPosition" onkeyup="updatePreview()">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Company</label>
                                        <input type="text" name="company" class="form-control" 
                                               value="<?php echo htmlspecialchars($testimonial['company'] ?? ''); ?>" 
                                               placeholder="e.g., Tech Corp"
                                               id="company" onkeyup="updatePreview()">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Testimonial Text <span style="color: #dc3545;">*</span></label>
                                    <textarea name="testimonial_text" class="form-control" 
                                              rows="6" placeholder="Write the client's testimonial here..."
                                              id="testimonialText" 
                                              onkeyup="updatePreview(); updateCharCount();"
                                              required><?php echo htmlspecialchars($testimonial['testimonial_text']); ?></textarea>
                                    <div class="char-counter" id="charCount"><?php echo strlen($testimonial['testimonial_text']); ?> characters</div>
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
                                    <input type="hidden" name="rating" id="ratingValue" value="<?php echo $testimonial['rating'] ?? 5; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sidebar Column -->
                        <div style="display: flex; flex-direction: column; gap: 25px;">
                            <!-- Settings Card -->
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-cog"></i> Settings</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <option value="active" <?php echo $testimonial['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $testimonial['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Display Order</label>
                                        <input type="number" name="display_order" class="form-control" 
                                               value="<?php echo $testimonial['display_order'] ?? 0; ?>" min="0">
                                        <small>Lower numbers appear first</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Client Photo Card -->
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-camera"></i> Client Photo</h3>
                                </div>
                                <div class="card-body">
                                    <div style="text-align: center;">
                                        <?php if ($testimonial['client_photo']): ?>
                                            <img src="<?php echo SITE_URL . '/' . $testimonial['client_photo']; ?>" 
                                                 class="photo-preview" id="photoPreview">
                                        <?php else: ?>
                                            <img src="" class="photo-preview" id="photoPreview" style="display: none;">
                                        <?php endif; ?>
                                        
                                        <div style="margin-top: 15px;">
                                            <label for="client_photo" class="btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                                <i class="fas fa-upload"></i> <?php echo $testimonial['client_photo'] ? 'Change Photo' : 'Choose Photo'; ?>
                                            </label>
                                            <input type="file" name="client_photo" id="client_photo" 
                                                   accept="image/*" style="display: none;" 
                                                   onchange="previewImage(this)">
                                        </div>
                                        
                                        <?php if ($testimonial['client_photo']): ?>
                                            <div class="delete-photo-btn" onclick="removeImage()">
                                                <i class="fas fa-trash"></i> Remove Current Photo
                                            </div>
                                            <input type="hidden" name="remove_photo" id="removePhoto" value="0">
                                        <?php endif; ?>
                                        
                                        <small style="display: block; margin-top: 10px; color: var(--gray-600);">
                                            Recommended: Square image, at least 300x300px
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Meta Info Card -->
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-info-circle"></i> Information</h3>
                                </div>
                                <div class="card-body">
                                    <div style="background: var(--gray-100); padding: 15px; border-radius: 8px;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                            <span style="color: var(--gray-600);">Created:</span>
                                            <span style="font-weight: 600;"><?php echo formatDate($testimonial['created_at'], 'M d, Y'); ?></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;">
                                            <span style="color: var(--gray-600);">Last Updated:</span>
                                            <span style="font-weight: 600;"><?php echo formatDate($testimonial['created_at'], 'H:i'); ?></span>
                                        </div>
                                    </div>
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
                        <?php echo truncateText($testimonial['testimonial_text'], 200); ?>
                    </div>
                    
                    <div class="preview-author">
                        <div class="preview-avatar" id="previewAvatar">
                            <?php if ($testimonial['client_photo']): ?>
                                <img src="<?php echo SITE_URL . '/' . $testimonial['client_photo']; ?>">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h4 id="previewName"><?php echo $testimonial['client_name']; ?></h4>
                            <p id="previewTitle" style="color: var(--gray-600); margin: 5px 0 0;">
                                <?php 
                                    $title = [];
                                    if ($testimonial['client_position']) $title[] = $testimonial['client_position'];
                                    if ($testimonial['company']) $title[] = $testimonial['company'];
                                    echo $title ? implode(', ', $title) : 'Position, Company';
                                ?>
                            </p>
                            <div id="previewRating" style="color: #ffc107; margin-top: 5px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?php echo $i <= ($testimonial['rating'] ?? 5) ? 'fas' : 'far'; ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        });
        
        // Rating stars functionality
        const stars = document.querySelectorAll('.rating-stars i');
        const ratingInput = document.getElementById('ratingValue');
        const currentRating = parseInt(ratingInput.value);
        
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
        
        // Initialize rating
        updateRatingDisplay(currentRating);
        
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
        
        stars.forEach(star => {
            star.addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingInput.value);
                updateRatingDisplay(currentRating);
            });
        });
        
        // Character counter
        function updateCharCount() {
            const text = document.getElementById('testimonialText').value;
            const count = text.length;
            const counter = document.getElementById('charCount');
            counter.textContent = count + ' characters';
        }
        
        // Live preview update
        function updatePreview() {
            const name = document.getElementById('clientName').value || 'Client Name';
            const position = document.getElementById('clientPosition').value || 'Position';
            const company = document.getElementById('company').value;
            const text = document.getElementById('testimonialText').value || 'Your testimonial text will appear here...';
            
            document.getElementById('previewName').textContent = name;
            
            let titleText = position;
            if (company) {
                titleText += ', ' + company;
            }
            document.getElementById('previewTitle').textContent = titleText;
            document.getElementById('previewText').textContent = text;
        }
        
        function updatePreviewRating(rating) {
            const ratingContainer = document.getElementById('previewRating');
            if (ratingContainer) {
                ratingContainer.innerHTML = '';
                for (let i = 1; i <= 5; i++) {
                    const star = document.createElement('i');
                    star.className = i <= rating ? 'fas fa-star' : 'far fa-star';
                    ratingContainer.appendChild(star);
                }
            }
        }
        
        // Image preview
        function previewImage(input) {
            const preview = document.getElementById('photoPreview');
            const avatar = document.getElementById('previewAvatar');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    
                    // Update preview avatar
                    avatar.innerHTML = `<img src="${e.target.result}" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function removeImage() {
            const preview = document.getElementById('photoPreview');
            const avatar = document.getElementById('previewAvatar');
            const removePhoto = document.getElementById('removePhoto');
            
            preview.style.display = 'none';
            preview.src = '';
            avatar.innerHTML = '<i class="fas fa-user"></i>';
            
            if (removePhoto) {
                removePhoto.value = '1';
            }
            
            // Hide delete button
            event.target.closest('.delete-photo-btn').style.display = 'none';
        }
        
        // Initialize preview with current values
        updatePreview();
    </script>
</body>
</html>