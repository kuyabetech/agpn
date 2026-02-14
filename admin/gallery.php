<?php
// ============================================
// GALLERY MANAGEMENT PAGE - UPDATED
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

// Handle gallery creation
if (isset($_POST['create_gallery'])) {
    $gallery_name = sanitize($_POST['gallery_name']);
    $gallery_slug = sanitize($_POST['gallery_slug']) ?: createSlug($gallery_name);
    $description = sanitize($_POST['description']);
    
    if (empty($gallery_name)) {
        $_SESSION['error'] = 'Gallery name is required';
    } else {
        try {
            // Check if slug exists
            $check = db()->prepare("SELECT id FROM galleries WHERE gallery_slug = ?");
            $check->execute([$gallery_slug]);
            
            if ($check->fetch()) {
                $gallery_slug = $gallery_slug . '-' . uniqid();
            }
            
            $stmt = db()->prepare("INSERT INTO galleries (gallery_name, gallery_slug, description) VALUES (?, ?, ?)");
            $stmt->execute([$gallery_name, $gallery_slug, $description]);
            
            $_SESSION['success'] = 'Gallery created successfully';
            redirect('gallery.php');
        } catch (PDOException $e) {
            error_log("Error creating gallery: " . $e->getMessage());
            $_SESSION['error'] = 'Error creating gallery';
        }
    }
    redirect('gallery.php');
}

// Handle image upload
if (isset($_POST['upload_images']) && isset($_GET['gallery_id'])) {
    $gallery_id = (int)$_GET['gallery_id'];
    
    if (!empty($_FILES['images']['name'][0])) {
        $files = $_FILES['images'];
        $uploaded = 0;
        $failed = 0;
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === 0) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                
                $upload = uploadImage($file, 'galleries/' . $gallery_id);
                
                if ($upload['success']) {
                    try {
                        $image_title = sanitize(pathinfo($files['name'][$i], PATHINFO_FILENAME));
                        $caption = sanitize($_POST['captions'][$i] ?? '');
                        
                        $stmt = db()->prepare("
                            INSERT INTO gallery_images (gallery_id, image_title, image_path, caption, display_order) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$gallery_id, $image_title, $upload['path'], $caption, $i]);
                        $uploaded++;
                    } catch (PDOException $e) {
                        error_log("Error saving image: " . $e->getMessage());
                        $failed++;
                    }
                } else {
                    $failed++;
                }
            }
        }
        
        $_SESSION['success'] = "{$uploaded} images uploaded successfully. {$failed} failed.";
        redirect('gallery.php?view=' . $gallery_id);
    } else {
        $_SESSION['error'] = 'Please select at least one image';
    }
    redirect('gallery.php?view=' . $gallery_id);
}

// Handle delete gallery
if (isset($_GET['delete_gallery']) && is_numeric($_GET['delete_gallery'])) {
    $gallery_id = (int)$_GET['delete_gallery'];
    
    try {
        // Get all images to delete files
        $stmt = db()->prepare("SELECT image_path FROM gallery_images WHERE gallery_id = ?");
        $stmt->execute([$gallery_id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Delete image files
        foreach ($images as $image) {
            $file_path = '../' . $image['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete gallery directory
        $dir_path = '../uploads/galleries/' . $gallery_id;
        if (is_dir($dir_path)) {
            $files = glob($dir_path . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($dir_path);
        }
        
        // Delete database records
        $stmt = db()->prepare("DELETE FROM galleries WHERE id = ?");
        $stmt->execute([$gallery_id]);
        
        $_SESSION['success'] = 'Gallery deleted successfully';
    } catch (PDOException $e) {
        error_log("Error deleting gallery: " . $e->getMessage());
        $_SESSION['error'] = 'Error deleting gallery';
    }
    redirect('gallery.php');
}

// Handle delete image
if (isset($_GET['delete_image']) && is_numeric($_GET['delete_image'])) {
    $image_id = (int)$_GET['delete_image'];
    $gallery_id = (int)$_GET['gallery_id'];
    
    try {
        // Get image path
        $stmt = db()->prepare("SELECT image_path FROM gallery_images WHERE id = ?");
        $stmt->execute([$image_id]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($image) {
            $file_path = '../' . $image['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete database record
        $stmt = db()->prepare("DELETE FROM gallery_images WHERE id = ?");
        $stmt->execute([$image_id]);
        
        $_SESSION['success'] = 'Image deleted successfully';
    } catch (PDOException $e) {
        error_log("Error deleting image: " . $e->getMessage());
        $_SESSION['error'] = 'Error deleting image';
    }
    redirect('gallery.php?view=' . $gallery_id);
}

// Handle update image details
if (isset($_POST['update_image'])) {
    $image_id = (int)$_POST['image_id'];
    $gallery_id = (int)$_POST['gallery_id'];
    $image_title = sanitize($_POST['image_title']);
    $caption = sanitize($_POST['caption']);
    $display_order = (int)$_POST['display_order'];
    
    try {
        $stmt = db()->prepare("
            UPDATE gallery_images 
            SET image_title = ?, caption = ?, display_order = ? 
            WHERE id = ?
        ");
        $stmt->execute([$image_title, $caption, $display_order, $image_id]);
        
        $_SESSION['success'] = 'Image updated successfully';
    } catch (PDOException $e) {
        error_log("Error updating image: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating image';
    }
    redirect('gallery.php?view=' . $gallery_id);
}

// Handle set as cover
if (isset($_GET['set_cover']) && is_numeric($_GET['set_cover'])) {
    $image_id = (int)$_GET['set_cover'];
    $gallery_id = (int)$_GET['gallery_id'];
    
    try {
        // Reset all images in gallery to normal order
        $stmt = db()->prepare("UPDATE gallery_images SET display_order = 0 WHERE gallery_id = ? AND display_order = -1");
        $stmt->execute([$gallery_id]);
        
        // Set new cover (using display_order = -1 to mark as cover)
        $stmt = db()->prepare("UPDATE gallery_images SET display_order = -1 WHERE id = ?");
        $stmt->execute([$image_id]);
        
        $_SESSION['success'] = 'Cover image updated';
    } catch (PDOException $e) {
        error_log("Error setting cover: " . $e->getMessage());
        $_SESSION['error'] = 'Error setting cover image';
    }
    redirect('gallery.php?view=' . $gallery_id);
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['image_ids'])) {
    $action = $_POST['bulk_action'];
    $gallery_id = (int)$_POST['gallery_id'];
    $image_ids = explode(',', $_POST['image_ids']);
    $image_ids = array_filter(array_map('intval', $image_ids));
    
    if (!empty($image_ids) && !empty($action)) {
        try {
            $placeholders = implode(',', array_fill(0, count($image_ids), '?'));
            
            if ($action === 'delete') {
                // Get image paths
                $stmt = db()->prepare("SELECT image_path FROM gallery_images WHERE id IN ($placeholders)");
                $stmt->execute($image_ids);
                $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Delete files
                foreach ($images as $image) {
                    $file_path = '../' . $image['image_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                // Delete records
                $stmt = db()->prepare("DELETE FROM gallery_images WHERE id IN ($placeholders)");
                $stmt->execute($image_ids);
                $_SESSION['success'] = count($image_ids) . ' images deleted successfully';
            }
        } catch (PDOException $e) {
            error_log("Bulk action error: " . $e->getMessage());
            $_SESSION['error'] = 'Error performing bulk action';
        }
    }
    redirect('gallery.php?view=' . $gallery_id);
}

// Get current view
$current_gallery_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$current_gallery = null;
$gallery_images = [];

// Get all galleries
try {
    $db = db();
    
    $stmt = $db->query("
        SELECT g.*, 
               (SELECT COUNT(*) FROM gallery_images WHERE gallery_id = g.id) as image_count,
               (SELECT image_path FROM gallery_images WHERE gallery_id = g.id ORDER BY 
                    CASE WHEN display_order = -1 THEN 0 ELSE 1 END,
                    display_order ASC, id ASC LIMIT 1) as cover_image
        FROM galleries g
        ORDER BY g.created_at DESC
    ");
    $galleries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current gallery details if viewing
    if ($current_gallery_id > 0) {
        $stmt = $db->prepare("SELECT * FROM galleries WHERE id = ?");
        $stmt->execute([$current_gallery_id]);
        $current_gallery = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get gallery images
        $stmt = $db->prepare("
            SELECT * FROM gallery_images 
            WHERE gallery_id = ? 
            ORDER BY 
                CASE WHEN display_order = -1 THEN 0 ELSE 1 END,
                display_order ASC,
                id ASC
        ");
        $stmt->execute([$current_gallery_id]);
        $gallery_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Statistics
    $total_galleries = count($galleries);
    $total_images = $db->query("SELECT COUNT(*) FROM gallery_images")->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    error_log("Error fetching galleries: " . $e->getMessage());
    $galleries = [];
    $gallery_images = [];
    $total_galleries = $total_images = 0;
}

// Calculate gallery storage
$gallery_size = 0;
if ($total_images > 0 && isset($db)) {
    try {
        $stmt = $db->query("SELECT image_path FROM gallery_images");
        $all_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_images as $img) {
            $path = '../' . $img['image_path'];
            if (file_exists($path)) {
                $gallery_size += filesize($path);
            }
        }
    } catch (PDOException $e) {
        error_log("Error calculating gallery size: " . $e->getMessage());
    }
}

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Set page title
$page_title = $current_gallery ? htmlspecialchars($current_gallery['gallery_name']) : 'Gallery Management';

// Breadcrumbs
$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => 'gallery.php', 'title' => 'Galleries']
];

if ($current_gallery) {
    $breadcrumbs[] = ['url' => '#', 'title' => $current_gallery['gallery_name']];
}

// Page specific CSS
$page_css = ['gallery.css'];

// Include admin header
require_once '../includes/admin-header.php';
?>

<!-- Page Specific Styles -->
<style>
    /* Gallery Management Specific Styles */
    .gallery-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .gallery-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    
    .gallery-stat-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        border: 1px solid var(--gray-200);
        transition: all 0.3s ease;
    }
    
    .gallery-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .gallery-stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }
    
    .gallery-stat-info h4 {
        font-size: 14px;
        color: var(--gray-600);
        margin-bottom: 8px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .gallery-stat-info .number {
        font-size: 32px;
        font-weight: 700;
        color: var(--navy);
        line-height: 1;
    }
    
    /* Galleries Grid */
    .galleries-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 25px;
        margin-top: 25px;
    }
    
    .gallery-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        border: 1px solid var(--gray-200);
        transition: all 0.3s ease;
        position: relative;
    }
    
    .gallery-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        border-color: var(--gold);
    }
    
    .gallery-cover {
        height: 180px;
        background: var(--gray-100);
        position: relative;
        overflow: hidden;
    }
    
    .gallery-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    
    .gallery-card:hover .gallery-cover img {
        transform: scale(1.1);
    }
    
    .gallery-cover-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
        color: var(--gold);
        font-size: 48px;
    }
    
    .gallery-info {
        padding: 20px;
    }
    
    .gallery-info h3 {
        margin-bottom: 8px;
        font-size: 18px;
        color: var(--navy);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .gallery-info p {
        color: var(--gray-600);
        font-size: 13px;
        margin-bottom: 15px;
        line-height: 1.6;
    }
    
    .gallery-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
        color: var(--gray-600);
        padding-top: 15px;
        border-top: 1px solid var(--gray-200);
    }
    
    .gallery-actions {
        position: absolute;
        top: 15px;
        right: 15px;
        display: flex;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .gallery-card:hover .gallery-actions {
        opacity: 1;
    }
    
    .btn-action {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--gray-600);
        background: white;
        border: 1px solid var(--gray-200);
        transition: all 0.2s ease;
        text-decoration: none;
    }
    
    .btn-action:hover {
        background: var(--gold);
        color: var(--navy);
        border-color: var(--gold);
        transform: translateY(-2px);
    }
    
    .btn-action.delete:hover {
        background: #dc3545;
        color: white;
        border-color: #dc3545;
    }
    
    /* Images Grid */
    .images-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 25px;
    }
    
    .image-item {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        border: 1px solid var(--gray-200);
        transition: all 0.3s ease;
        position: relative;
    }
    
    .image-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        border-color: var(--gold);
    }
    
    .image-preview {
        height: 160px;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }
    
    .image-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .image-item:hover .image-preview img {
        transform: scale(1.1);
    }
    
    .image-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(10,25,41,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .image-item:hover .image-overlay {
        opacity: 1;
    }
    
    .image-overlay a {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--navy);
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .image-overlay a:hover {
        background: var(--gold);
        transform: scale(1.1);
    }
    
    .image-details {
        padding: 15px;
    }
    
    .image-title {
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .image-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        color: var(--gray-600);
    }
    
    .cover-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: var(--gold);
        color: var(--navy);
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        z-index: 2;
    }
    
    .upload-area {
        border: 2px dashed var(--gray-300);
        border-radius: 16px;
        padding: 40px;
        text-align: center;
        background: var(--gray-100);
        transition: all 0.3s ease;
        cursor: pointer;
        margin-bottom: 30px;
    }
    
    .upload-area:hover {
        border-color: var(--gold);
        background: rgba(255,184,28,0.05);
    }
    
    .upload-area i {
        font-size: 48px;
        color: var(--gold);
        margin-bottom: 15px;
    }
    
    .upload-area h3 {
        color: var(--navy);
        margin-bottom: 10px;
    }
    
    .file-list {
        margin-top: 20px;
        text-align: left;
    }
    
    .file-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px;
        background: white;
        border-radius: 8px;
        margin-bottom: 10px;
        border: 1px solid var(--gray-200);
    }
    
    .file-preview {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        object-fit: cover;
    }
    
    .file-info {
        flex: 1;
    }
    
    .file-name {
        font-weight: 600;
        margin-bottom: 4px;
    }
    
    .file-size {
        font-size: 11px;
        color: var(--gray-600);
    }
    
    .btn-apply {
        padding: 10px 20px;
        background: var(--navy);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    
    .btn-apply:hover:not(:disabled) {
        background: var(--navy-light);
        transform: translateY(-1px);
    }
    
    .btn-apply:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--gold);
        box-shadow: 0 0 0 3px rgba(255,184,28,0.1);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 16px;
        border: 2px dashed var(--gray-300);
    }
    
    .empty-state i {
        font-size: 64px;
        color: var(--gray-400);
        margin-bottom: 20px;
    }
    
    .empty-state h3 {
        color: var(--navy);
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: var(--gray-600);
        margin-bottom: 25px;
    }
    
    @media (max-width: 768px) {
        .galleries-grid,
        .images-grid {
            grid-template-columns: 1fr;
        }
        
        .gallery-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .upload-area {
            padding: 30px 20px;
        }
        
        .gallery-stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Lightbox2 CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css">

<!-- Toast Notifications -->
<?php if ($success): ?>
    <div class="toast-notification animate__animated animate__fadeInRight">
        <div style="background: #D4EDDA; color: #155724; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #28a745;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="toast-notification animate__animated animate__fadeInRight">
        <div style="background: #F8D7DA; color: #721C24; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #dc3545;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="gallery-stats-grid animate__animated animate__fadeIn">
    <div class="gallery-stat-card">
        <div class="gallery-stat-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-images"></i>
        </div>
        <div class="gallery-stat-info">
            <h4>Total Galleries</h4>
            <span class="number"><?php echo $total_galleries; ?></span>
        </div>
    </div>
    
    <div class="gallery-stat-card">
        <div class="gallery-stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-image"></i>
        </div>
        <div class="gallery-stat-info">
            <h4>Total Images</h4>
            <span class="number"><?php echo $total_images; ?></span>
        </div>
    </div>
    
    <div class="gallery-stat-card">
        <div class="gallery-stat-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
            <i class="fas fa-database"></i>
        </div>
        <div class="gallery-stat-info">
            <h4>Storage Used</h4>
            <span class="number">
                <?php
                if ($gallery_size < 1048576) {
                    echo round($gallery_size / 1024, 2) . ' KB';
                } elseif ($gallery_size < 1073741824) {
                    echo round($gallery_size / 1048576, 2) . ' MB';
                } else {
                    echo round($gallery_size / 1073741824, 2) . ' GB';
                }
                ?>
            </span>
        </div>
    </div>
    
    <div class="gallery-stat-card">
        <div class="gallery-stat-icon" style="background: rgba(111,66,193,0.1); color: #6f42c1;">
            <i class="fas fa-percentage"></i>
        </div>
        <div class="gallery-stat-info">
            <h4>Avg per Gallery</h4>
            <span class="number">
                <?php echo $total_galleries > 0 ? round($total_images / $total_galleries, 1) : 0; ?>
            </span>
        </div>
    </div>
</div>

<?php if ($current_gallery): ?>
    <!-- Hidden file input for uploads -->
    <form method="POST" action="?gallery_id=<?php echo $current_gallery_id; ?>" enctype="multipart/form-data" id="uploadForm">
        <input type="file" name="images[]" id="fileInput" multiple accept="image/*" style="display: none;" onchange="handleFileSelect()">
        <input type="hidden" name="upload_images" value="1">
    </form>
    
    <!-- Upload Area -->
    <div class="upload-area animate__animated animate__fadeIn" onclick="document.getElementById('fileInput').click()">
        <i class="fas fa-cloud-upload-alt"></i>
        <h3>Upload Images to Gallery</h3>
        <p style="color: var(--gray-600);">Drag and drop images here or click to browse</p>
        <p style="font-size: 12px; color: var(--gray-500); margin-top: 10px;">
            Supported formats: JPG, PNG, GIF, WEBP • Max size: 5MB per image
        </p>
    </div>
    
    <!-- File list for preview -->
    <div id="fileList" class="file-list" style="display: none;"></div>
    
    <!-- Bulk Actions -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="selectAllImages" style="width: 18px; height: 18px; cursor: pointer;">
                <label for="selectAllImages" style="font-size: 14px;">Select All</label>
            </div>
            
            <select id="bulkActionSelect" class="form-control" style="width: 150px;" disabled>
                <option value="">Bulk Actions</option>
                <option value="delete">Delete Selected</option>
            </select>
            
            <button id="applyBulkAction" class="btn-apply" disabled>Apply</button>
        </div>
        
        <div style="font-size: 14px; color: var(--gray-600);">
            <i class="fas fa-images"></i> <?php echo count($gallery_images); ?> images in this gallery
        </div>
    </div>
    
    <!-- Images Grid -->
    <?php if ($gallery_images): ?>
        <div class="images-grid animate__animated animate__fadeIn" id="imagesGrid">
            <?php foreach ($gallery_images as $image): ?>
                <div class="image-item" data-id="<?php echo $image['id']; ?>">
                    <?php if ($image['display_order'] == -1): ?>
                        <span class="cover-badge">
                            <i class="fas fa-crown"></i> Cover
                        </span>
                    <?php endif; ?>
                    
                    <div class="image-preview">
                        <a href="<?php echo SITE_URL . '/' . $image['image_path']; ?>" 
                           data-lightbox="gallery-<?php echo $current_gallery_id; ?>"
                           data-title="<?php echo htmlspecialchars($image['image_title'] ?? 'Gallery Image'); ?>">
                            <img src="<?php echo SITE_URL . '/' . $image['image_path']; ?>" 
                                 alt="<?php echo htmlspecialchars($image['image_title'] ?? 'Gallery Image'); ?>"
                                 loading="lazy">
                        </a>
                        <div class="image-overlay">
                            <a href="#" onclick="openEditModal(<?php echo $image['id']; ?>, '<?php echo addslashes($image['image_title'] ?? ''); ?>', '<?php echo addslashes($image['caption'] ?? ''); ?>', <?php echo $image['display_order'] ?? 0; ?>)">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($image['display_order'] != -1): ?>
                                <a href="?set_cover=<?php echo $image['id']; ?>&gallery_id=<?php echo $current_gallery_id; ?>" title="Set as Cover">
                                    <i class="fas fa-crown"></i>
                                </a>
                            <?php endif; ?>
                            <a href="?delete_image=<?php echo $image['id']; ?>&gallery_id=<?php echo $current_gallery_id; ?>" 
                               onclick="return confirm('Are you sure you want to delete this image?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    <div class="image-details">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" class="image-checkbox" value="<?php echo $image['id']; ?>" style="width: 16px; height: 16px;">
                            <div class="image-title">
                                <?php echo htmlspecialchars($image['image_title'] ?: 'Untitled'); ?>
                            </div>
                        </div>
                        <div class="image-meta">
                            <span><i class="fas fa-calendar"></i> <?php echo formatDate($image['created_at'], 'M d, Y'); ?></span>
                            <span>
                                <?php
                                $img_path = '../' . $image['image_path'];
                                if (file_exists($img_path)) {
                                    $size = filesize($img_path);
                                    if ($size < 1048576) {
                                        echo round($size / 1024, 1) . ' KB';
                                    } else {
                                        echo round($size / 1048576, 1) . ' MB';
                                    }
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state animate__animated animate__fadeIn">
            <i class="fas fa-images"></i>
            <h3>No Images Yet</h3>
            <p>Start uploading images to this gallery.</p>
            <button onclick="document.getElementById('fileInput').click()" class="btn-primary" style="margin-top: 15px;">
                <i class="fas fa-upload"></i> Upload Images
            </button>
        </div>
    <?php endif; ?>
    
<?php else: ?>
    <!-- Galleries Grid -->
    <?php if ($galleries): ?>
        <div class="galleries-grid animate__animated animate__fadeIn">
            <?php foreach ($galleries as $gallery): ?>
                <div class="gallery-card">
                    <div class="gallery-actions">
                        <a href="?view=<?php echo $gallery['id']; ?>" class="btn-action" title="View Gallery">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="?delete_gallery=<?php echo $gallery['id']; ?>" 
                           class="btn-action delete" 
                           title="Delete Gallery"
                           onclick="return confirm('Are you sure you want to delete this gallery and all its images?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                    
                    <a href="?view=<?php echo $gallery['id']; ?>" style="text-decoration: none;">
                        <div class="gallery-cover">
                            <?php if ($gallery['cover_image']): ?>
                                <img src="<?php echo SITE_URL . '/' . $gallery['cover_image']; ?>" 
                                     alt="<?php echo htmlspecialchars($gallery['gallery_name']); ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="gallery-cover-placeholder">
                                    <i class="fas fa-images"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="gallery-info">
                            <h3>
                                <?php echo htmlspecialchars($gallery['gallery_name']); ?>
                                <span style="font-size: 12px; background: var(--gray-100); padding: 4px 10px; border-radius: 20px;">
                                    <?php echo $gallery['image_count']; ?> images
                                </span>
                            </h3>
                            
                            <?php if ($gallery['description']): ?>
                                <p><?php echo htmlspecialchars(truncateText($gallery['description'], 80)); ?></p>
                            <?php endif; ?>
                            
                            <div class="gallery-meta">
                                <span>
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo formatDate($gallery['created_at'], 'M d, Y'); ?>
                                </span>
                                <span>
                                    <i class="fas fa-image"></i> 
                                    <?php echo $gallery['image_count']; ?> images
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state animate__animated animate__fadeIn">
            <i class="fas fa-images"></i>
            <h3>No Galleries Yet</h3>
            <p>Create your first gallery to start uploading images.</p>
            <button onclick="openCreateGalleryModal()" class="btn-primary" style="margin-top: 15px;">
                <i class="fas fa-plus-circle"></i> Create Gallery
            </button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Create Gallery Modal -->
<div id="createGalleryModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="modal-close" onclick="closeCreateGalleryModal()">&times;</span>
        <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-folder-plus" style="color: var(--gold);"></i>
            Create New Gallery
        </h2>
        
        <form method="POST" action="gallery.php">
            <div class="form-group">
                <label>Gallery Name <span style="color: #dc3545;">*</span></label>
                <input type="text" name="gallery_name" class="form-control" placeholder="e.g., Events 2025" required>
            </div>
            
            <div class="form-group">
                <label>Gallery Slug (URL)</label>
                <input type="text" name="gallery_slug" class="form-control" placeholder="events-2025">
                <small style="color: var(--gray-600);">Leave empty to auto-generate</small>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Brief description of this gallery..."></textarea>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" name="create_gallery" class="btn-primary" style="flex: 1;">
                    <i class="fas fa-check"></i> Create Gallery
                </button>
                <button type="button" class="btn-secondary" onclick="closeCreateGalleryModal()" style="flex: 1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Image Modal -->
<div id="editImageModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="modal-close" onclick="closeEditModal()">&times;</span>
        <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-edit" style="color: var(--gold);"></i>
            Edit Image Details
        </h2>
        
        <form method="POST" action="gallery.php?view=<?php echo $current_gallery_id; ?>" id="editImageForm">
            <input type="hidden" name="image_id" id="edit_image_id">
            <input type="hidden" name="gallery_id" value="<?php echo $current_gallery_id; ?>">
            <input type="hidden" name="update_image" value="1">
            
            <div class="form-group">
                <label>Image Title</label>
                <input type="text" name="image_title" id="edit_image_title" class="form-control" placeholder="e.g., Conference Hall">
            </div>
            
            <div class="form-group">
                <label>Caption</label>
                <textarea name="caption" id="edit_image_caption" class="form-control" rows="3" placeholder="Brief description of this image..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Display Order</label>
                <input type="number" name="display_order" id="edit_image_order" class="form-control" min="-1" value="0">
                <small style="color: var(--gray-600);">Lower numbers appear first. Use -1 for cover image.</small>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="button" class="btn-secondary" onclick="closeEditModal()" style="flex: 1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lightbox2 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js"></script>

<script>
    // ============================================
    // GALLERY MANAGEMENT - JAVASCRIPT
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';
        
        // Lightbox configuration
        if (typeof lightbox !== 'undefined') {
            lightbox.option({
                'resizeDuration': 200,
                'wrapAround': true,
                'albumLabel': 'Image %1 of %2',
                'fadeDuration': 300,
                'imageFadeDuration': 300
            });
        }
        
        // ============================================
        // MODAL FUNCTIONS
        // ============================================
        
        const createModal = document.getElementById('createGalleryModal');
        const editModal = document.getElementById('editImageModal');
        
        window.openCreateGalleryModal = function() {
            if (createModal) {
                createModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        };
        
        window.closeCreateGalleryModal = function() {
            if (createModal) {
                createModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        };
        
        window.openEditModal = function(id, title, caption, order) {
            if (editModal) {
                document.getElementById('edit_image_id').value = id;
                document.getElementById('edit_image_title').value = title;
                document.getElementById('edit_image_caption').value = caption;
                document.getElementById('edit_image_order').value = order;
                editModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        };
        
        window.closeEditModal = function() {
            if (editModal) {
                editModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        };
        
        // ============================================
        // CLOSE MODALS WHEN CLICKING OUTSIDE
        // ============================================
        
        window.addEventListener('click', function(e) {
            if (e.target == createModal) {
                closeCreateGalleryModal();
            }
            if (e.target == editModal) {
                closeEditModal();
            }
        });
        
        // ============================================
        // FILE UPLOAD HANDLING
        // ============================================
        
        window.handleFileSelect = function() {
            const input = document.getElementById('fileInput');
            const fileList = document.getElementById('fileList');
            
            if (input && input.files.length > 0) {
                fileList.style.display = 'block';
                fileList.innerHTML = '<h4 style="margin-bottom: 15px;">Selected Files:</h4>';
                
                for (let i = 0; i < input.files.length; i++) {
                    const file = input.files[i];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const fileItem = document.createElement('div');
                        fileItem.className = 'file-item';
                        fileItem.innerHTML = `
                            <img src="${e.target.result}" class="file-preview" alt="${file.name}">
                            <div class="file-info">
                                <div class="file-name">${file.name}</div>
                                <div class="file-size">${(file.size / 1024).toFixed(1)} KB</div>
                            </div>
                            <span style="color: #28a745;">
                                <i class="fas fa-check-circle"></i>
                            </span>
                        `;
                        fileList.appendChild(fileItem);
                    };
                    
                    reader.readAsDataURL(file);
                }
                
                // Auto submit after short delay
                setTimeout(() => {
                    if (confirm(`Upload ${input.files.length} image(s)?`)) {
                        document.getElementById('uploadForm').submit();
                    }
                }, 500);
            }
        };
        
        // ============================================
        // DRAG AND DROP
        // ============================================
        
        const uploadArea = document.querySelector('.upload-area');
        
        if (uploadArea) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            uploadArea.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                const input = document.getElementById('fileInput');
                if (input) {
                    input.files = files;
                    handleFileSelect();
                }
            }, false);
        }
        
        // ============================================
        // SELECT ALL FUNCTIONALITY
        // ============================================
        
        const selectAll = document.getElementById('selectAllImages');
        const imageCheckboxes = document.querySelectorAll('.image-checkbox');
        const bulkActionSelect = document.getElementById('bulkActionSelect');
        const applyButton = document.getElementById('applyBulkAction');
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                imageCheckboxes.forEach(cb => {
                    if (cb) cb.checked = this.checked;
                });
                updateBulkActionState();
            });
        }
        
        imageCheckboxes.forEach(cb => {
            if (cb) {
                cb.addEventListener('change', function() {
                    if (selectAll) {
                        const checked = document.querySelectorAll('.image-checkbox:checked').length;
                        selectAll.checked = checked === imageCheckboxes.length;
                        selectAll.indeterminate = checked > 0 && checked < imageCheckboxes.length;
                    }
                    updateBulkActionState();
                });
            }
        });
        
        function updateBulkActionState() {
            const checked = document.querySelectorAll('.image-checkbox:checked').length;
            if (bulkActionSelect) bulkActionSelect.disabled = checked === 0;
            if (applyButton) applyButton.disabled = checked === 0;
        }
        
        // ============================================
        // BULK ACTION APPLY
        // ============================================
        
        if (applyButton) {
            applyButton.addEventListener('click', function() {
                const action = bulkActionSelect.value;
                const checkedIds = [];
                
                document.querySelectorAll('.image-checkbox:checked').forEach(cb => {
                    checkedIds.push(cb.value);
                });
                
                if (action === 'delete' && checkedIds.length > 0) {
                    if (confirm(`Are you sure you want to delete ${checkedIds.length} image(s)?`)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'gallery.php?view=<?php echo $current_gallery_id; ?>';
                        
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'bulk_action';
                        actionInput.value = action;
                        
                        const idsInput = document.createElement('input');
                        idsInput.type = 'hidden';
                        idsInput.name = 'image_ids';
                        idsInput.value = checkedIds.join(',');
                        
                        const galleryInput = document.createElement('input');
                        galleryInput.type = 'hidden';
                        galleryInput.name = 'gallery_id';
                        galleryInput.value = '<?php echo $current_gallery_id; ?>';
                        
                        form.appendChild(actionInput);
                        form.appendChild(idsInput);
                        form.appendChild(galleryInput);
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            });
        }
        
        // ============================================
        // KEYBOARD SHORTCUTS
        // ============================================
        
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N - New Gallery
            if ((e.ctrlKey || e.metaKey) && e.key === 'n' && !<?php echo $current_gallery ? 'true' : 'false'; ?>) {
                e.preventDefault();
                openCreateGalleryModal();
            }
            
            // Escape - Close modals
            if (e.key === 'Escape') {
                closeCreateGalleryModal();
                closeEditModal();
            }
        });
        
        // ============================================
        // AUTO-HIDE TOAST NOTIFICATIONS
        // ============================================
        
        setTimeout(() => {
            document.querySelectorAll('.toast-notification').forEach(el => {
                el.style.animation = 'fadeOutRight 0.3s ease';
                setTimeout(() => el.remove(), 300);
            });
        }, 5000);
        
        // ============================================
        // INITIAL CHECKBOX STATE
        // ============================================
        
        updateBulkActionState();
        
    }); // End DOMContentLoaded
</script>

<?php
// Include admin footer
require_once '../includes/admin-footer.php';
?>