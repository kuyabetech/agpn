<?php
// ============================================
// 6 ARMS MANAGEMENT PAGE - UPDATED
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

// Handle add arm
if (isset($_POST['add_arm'])) {
    $arm_name = sanitize($_POST['arm_name']);
    $arm_slug = sanitize($_POST['arm_slug']) ?: createSlug($arm_name);
    $short_description = sanitize($_POST['short_description']);
    $full_description = $_POST['full_description'];
    $icon_class = sanitize($_POST['icon_class']);
    $display_order = (int)sanitize($_POST['display_order']);
    $status = sanitize($_POST['status']);
    
    // Handle featured image upload
    $featured_image = null;
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
        $upload = uploadImage($_FILES['featured_image'], 'arms');
        if ($upload['success']) {
            $featured_image = $upload['path'];
        } else {
            $_SESSION['error'] = $upload['error'];
        }
    }
    
    if (empty($arm_name)) {
        $_SESSION['error'] = 'Arm name is required';
    } elseif (!isset($_SESSION['error'])) {
        try {
            $db = db();
            
            // Check if slug exists
            $check = $db->prepare("SELECT id FROM arms WHERE arm_slug = ?");
            $check->execute([$arm_slug]);
            
            if ($check->fetch()) {
                $arm_slug = $arm_slug . '-' . uniqid();
            }
            
            $stmt = $db->prepare("
                INSERT INTO arms (
                    arm_name, arm_slug, short_description, full_description, 
                    icon_class, featured_image, display_order, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $arm_name,
                $arm_slug,
                $short_description ?: null,
                $full_description ?: null,
                $icon_class ?: 'fa-cube',
                $featured_image,
                $display_order,
                $status
            ]);
            
            $_SESSION['success'] = 'Arm added successfully';
            redirect('arms.php');
        } catch (PDOException $e) {
            error_log("Error adding arm: " . $e->getMessage());
            $_SESSION['error'] = 'Error adding arm';
        }
    }
    redirect('arms.php');
}

// Handle edit arm
if (isset($_POST['edit_arm'])) {
    $arm_id = (int)$_POST['arm_id'];
    $arm_name = sanitize($_POST['arm_name']);
    $arm_slug = sanitize($_POST['arm_slug']) ?: createSlug($arm_name);
    $short_description = sanitize($_POST['short_description']);
    $full_description = $_POST['full_description'];
    $icon_class = sanitize($_POST['icon_class']);
    $display_order = (int)sanitize($_POST['display_order']);
    $status = sanitize($_POST['status']);
    
    try {
        $db = db();
        
        // Get current featured image
        $stmt = $db->prepare("SELECT featured_image FROM arms WHERE id = ?");
        $stmt->execute([$arm_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $featured_image = $current['featured_image'];
        
        // Handle new featured image upload
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
            $upload = uploadImage($_FILES['featured_image'], 'arms');
            if ($upload['success']) {
                // Delete old image
                if ($featured_image && file_exists('../' . $featured_image)) {
                    unlink('../' . $featured_image);
                }
                $featured_image = $upload['path'];
            } else {
                $_SESSION['error'] = $upload['error'];
            }
        }
        
        // Remove image if requested
        if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            if ($featured_image && file_exists('../' . $featured_image)) {
                unlink('../' . $featured_image);
            }
            $featured_image = null;
        }
        
        // Check if slug exists (excluding current)
        $check = $db->prepare("SELECT id FROM arms WHERE arm_slug = ? AND id != ?");
        $check->execute([$arm_slug, $arm_id]);
        
        if ($check->fetch()) {
            $arm_slug = $arm_slug . '-' . uniqid();
        }
        
        if (!isset($_SESSION['error'])) {
            $stmt = $db->prepare("
                UPDATE arms SET
                    arm_name = ?,
                    arm_slug = ?,
                    short_description = ?,
                    full_description = ?,
                    icon_class = ?,
                    featured_image = ?,
                    display_order = ?,
                    status = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $arm_name,
                $arm_slug,
                $short_description ?: null,
                $full_description ?: null,
                $icon_class ?: 'fa-cube',
                $featured_image,
                $display_order,
                $status,
                $arm_id
            ]);
            
            $_SESSION['success'] = 'Arm updated successfully';
            redirect('arms.php');
        }
    } catch (PDOException $e) {
        error_log("Error updating arm: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating arm';
    }
    redirect('arms.php');
}

// Handle delete arm
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $arm_id = (int)$_GET['delete'];
    
    try {
        $db = db();
        
        // Get featured image
        $stmt = $db->prepare("SELECT featured_image FROM arms WHERE id = ?");
        $stmt->execute([$arm_id]);
        $arm = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete image file
        if ($arm && $arm['featured_image'] && file_exists('../' . $arm['featured_image'])) {
            unlink('../' . $arm['featured_image']);
        }
        
        // Delete record
        $stmt = $db->prepare("DELETE FROM arms WHERE id = ?");
        $stmt->execute([$arm_id]);
        
        $_SESSION['success'] = 'Arm deleted successfully';
    } catch (PDOException $e) {
        error_log("Error deleting arm: " . $e->getMessage());
        $_SESSION['error'] = 'Error deleting arm';
    }
    redirect('arms.php');
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['arm_ids'])) {
    $action = $_POST['bulk_action'];
    $arm_ids = explode(',', $_POST['arm_ids']);
    $arm_ids = array_filter(array_map('intval', $arm_ids));
    
    if (!empty($arm_ids) && !empty($action)) {
        try {
            $db = db();
            $placeholders = implode(',', array_fill(0, count($arm_ids), '?'));
            
            if ($action === 'delete') {
                // Get all images
                $stmt = $db->prepare("SELECT featured_image FROM arms WHERE id IN ($placeholders)");
                $stmt->execute($arm_ids);
                $arms = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Delete image files
                foreach ($arms as $arm) {
                    if ($arm['featured_image'] && file_exists('../' . $arm['featured_image'])) {
                        unlink('../' . $arm['featured_image']);
                    }
                }
                
                // Delete records
                $stmt = $db->prepare("DELETE FROM arms WHERE id IN ($placeholders)");
                $stmt->execute($arm_ids);
                $_SESSION['success'] = count($arm_ids) . ' arms deleted successfully';
            } elseif ($action === 'active' || $action === 'inactive') {
                $stmt = $db->prepare("UPDATE arms SET status = ? WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$action], $arm_ids));
                $_SESSION['success'] = count($arm_ids) . ' arms updated successfully';
            }
        } catch (PDOException $e) {
            error_log("Bulk action error: " . $e->getMessage());
            $_SESSION['error'] = 'Error performing bulk action';
        }
    }
    redirect('arms.php');
}

// Get all arms
try {
    $db = db();
    
    $stmt = $db->query("
        SELECT * FROM arms 
        ORDER BY display_order ASC, created_at DESC
    ");
    $arms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics
    $total_arms = count($arms);
    $active_arms = $db->query("SELECT COUNT(*) FROM arms WHERE status = 'active'")->fetchColumn() ?: 0;
    $inactive_arms = $db->query("SELECT COUNT(*) FROM arms WHERE status = 'inactive'")->fetchColumn() ?: 0;
    $max_order = $db->query("SELECT MAX(display_order) FROM arms")->fetchColumn() ?: 0;
    
    // Get all available FontAwesome icons (for icon picker)
    $icons = [
        'fa-laptop-code' => 'Digital Skills',
        'fa-chart-line' => 'Business Growth',
        'fa-graduation-cap' => 'Study Abroad',
        'fa-handshake' => 'Sponsorship',
        'fa-briefcase' => 'Career Development',
        'fa-lightbulb' => 'Innovation',
        'fa-globe' => 'Global',
        'fa-users' => 'Community',
        'fa-rocket' => 'Startup',
        'fa-cogs' => 'Services',
        'fa-shield-alt' => 'Security',
        'fa-leaf' => 'Sustainability',
        'fa-heart' => 'Wellness',
        'fa-book' => 'Education',
        'fa-flask' => 'Research',
        'fa-cube' => 'Default'
    ];
    
} catch (PDOException $e) {
    error_log("Error fetching arms: " . $e->getMessage());
    $arms = [];
    $total_arms = $active_arms = $inactive_arms = $max_order = 0;
    $icons = [];
}

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Set page title
$page_title = '6 Arms Management';

// Breadcrumbs
$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => '#', 'title' => '6 Arms']
];

// Page specific CSS
$page_css = ['arms.css'];

// Include admin header
require_once '../includes/admin-header.php';
?>

<!-- Page Specific Styles -->
<style>
    .arms-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    
    .arms-stat-card {
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
    
    .arms-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .arms-stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }
    
    .arms-stat-info h4 {
        font-size: 14px;
        color: var(--gray-600);
        margin-bottom: 8px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .arms-stat-info .number {
        font-size: 32px;
        font-weight: 700;
        color: var(--navy);
        line-height: 1;
    }
    
    /* Arms Grid */
    .arms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
        margin-top: 25px;
    }
    
    .arm-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        border: 1px solid var(--gray-200);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        display: flex;
        flex-direction: column;
    }
    
    .arm-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 30px rgba(0,0,0,0.08);
        border-color: var(--gold);
    }
    
    .arm-card.inactive {
        opacity: 0.75;
        background: var(--gray-100);
    }
    
    .arm-header {
        padding: 25px 25px 0 25px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .arm-icon {
        width: 70px;
        height: 70px;
        background: rgba(255,184,28,0.1);
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: var(--gold);
        transition: all 0.3s ease;
    }
    
    .arm-card:hover .arm-icon {
        background: var(--gold);
        color: var(--navy);
        transform: scale(1.05);
    }
    
    .arm-title {
        flex: 1;
    }
    
    .arm-title h3 {
        margin-bottom: 5px;
        font-size: 20px;
        font-weight: 700;
        color: var(--navy);
    }
    
    .arm-order {
        display: inline-block;
        padding: 4px 12px;
        background: var(--gray-100);
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        color: var(--gray-600);
    }
    
    .arm-image {
        margin: 15px 25px 0 25px;
        height: 140px;
        background: var(--gray-100);
        border-radius: 16px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .arm-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    
    .arm-card:hover .arm-image img {
        transform: scale(1.05);
    }
    
    .arm-image-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: var(--gray-400);
        font-size: 14px;
    }
    
    .arm-image-placeholder i {
        font-size: 48px;
        margin-bottom: 10px;
        color: var(--gray-300);
    }
    
    .arm-content {
        padding: 20px 25px;
        flex: 1;
    }
    
    .arm-description {
        color: var(--gray-700);
        font-size: 14px;
        line-height: 1.7;
        margin-bottom: 15px;
    }
    
    .arm-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        padding-top: 15px;
        border-top: 1px solid var(--gray-200);
        font-size: 12px;
        color: var(--gray-600);
    }
    
    .arm-meta i {
        color: var(--gold);
        margin-right: 6px;
    }
    
    .arm-actions {
        position: absolute;
        top: 20px;
        right: 20px;
        display: flex;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 10;
    }
    
    .arm-card:hover .arm-actions {
        opacity: 1;
    }
    
    .btn-arm-action {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        transition: all 0.2s ease;
        text-decoration: none;
        box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    }
    
    .btn-arm-action:hover {
        background: var(--gold);
        color: var(--navy);
        border-color: var(--gold);
        transform: translateY(-2px);
    }
    
    .btn-arm-action.delete:hover {
        background: #dc3545;
        color: white;
        border-color: #dc3545;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 14px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-badge.active {
        background: rgba(40,167,69,0.1);
        color: #28a745;
    }
    
    .status-badge.inactive {
        background: rgba(108,117,125,0.1);
        color: var(--gray-600);
    }
    
    /* Icon Picker */
    .icon-picker-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 15px;
        max-height: 300px;
        overflow-y: auto;
        padding: 15px;
        background: var(--gray-100);
        border-radius: 12px;
        margin-top: 10px;
    }
    
    .icon-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 15px 10px;
        background: white;
        border-radius: 12px;
        border: 1px solid var(--gray-200);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .icon-item:hover {
        background: var(--gold);
        border-color: var(--gold);
        transform: translateY(-2px);
    }
    
    .icon-item:hover i,
    .icon-item:hover span {
        color: var(--navy);
    }
    
    .icon-item i {
        font-size: 28px;
        color: var(--gray-700);
        margin-bottom: 8px;
    }
    
    .icon-item span {
        font-size: 11px;
        color: var(--gray-600);
        text-align: center;
    }
    
    .icon-item.selected {
        background: var(--gold);
        border-color: var(--gold);
    }
    
    .icon-item.selected i,
    .icon-item.selected span {
        color: var(--navy);
    }
    
    .selected-icon-display {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: var(--gray-100);
        border-radius: 12px;
        margin-bottom: 15px;
    }
    
    .selected-icon-preview {
        width: 60px;
        height: 60px;
        background: rgba(255,184,28,0.1);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: var(--gold);
    }
    
    /* Modal Styles */
    .arm-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
    }
    
    .arm-modal.active {
        display: flex;
    }
    
    .arm-modal-content {
        background: white;
        border-radius: 24px;
        padding: 35px;
        max-width: 800px;
        width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        animation: modalSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    @keyframes modalSlideIn {
        from {
            transform: translateY(-40px) scale(0.95);
            opacity: 0;
        }
        to {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
    }
    
    .arm-modal-close {
        position: absolute;
        top: 20px;
        right: 25px;
        font-size: 28px;
        color: var(--gray-500);
        cursor: pointer;
        transition: all 0.2s ease;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }
    
    .arm-modal-close:hover {
        background: #fee;
        color: #dc3545;
        transform: rotate(90deg);
    }
    
    .image-preview-container {
        border: 2px dashed var(--gray-300);
        border-radius: 16px;
        padding: 25px;
        text-align: center;
        margin-bottom: 20px;
        background: var(--gray-100);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .image-preview-container:hover {
        border-color: var(--gold);
        background: rgba(255,184,28,0.05);
    }
    
    .arm-preview {
        max-width: 100%;
        max-height: 200px;
        object-fit: contain;
        margin-bottom: 15px;
        border-radius: 12px;
    }
    
    /* Order Badge */
    .order-badge {
        position: absolute;
        bottom: 20px;
        right: 20px;
        background: var(--navy);
        color: white;
        padding: 6px 14px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .order-badge i {
        color: var(--gold);
    }
    
    .arm-checkbox {
        position: absolute;
        top: 20px;
        left: 20px;
        width: 18px;
        height: 18px;
        z-index: 15;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .arm-card:hover .arm-checkbox,
    .arm-checkbox:checked {
        opacity: 1;
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
    }
    
    .btn-apply:hover:not(:disabled) {
        background: var(--navy-light);
        transform: translateY(-1px);
    }
    
    .btn-apply:disabled {
        opacity: 0.5;
        cursor: not-allowed;
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
    
    .slug-preview {
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--gray-100);
        padding: 12px 15px;
        border-radius: 8px;
        font-size: 13px;
        color: var(--gray-600);
        flex-wrap: wrap;
    }
    
    .slug-preview i {
        color: var(--gold);
    }
    
    .slug-preview input {
        border: 1px solid var(--gray-200);
        background: white;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 13px;
        flex: 1;
        min-width: 200px;
    }
    
    .slug-preview input:focus {
        outline: none;
        border-color: var(--gold);
    }
    
    @media (max-width: 768px) {
        .arms-grid {
            grid-template-columns: 1fr;
        }
        
        .arm-modal-content {
            padding: 25px;
        }
        
        .icon-picker-grid {
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
        }
        
        .slug-preview {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .slug-preview input {
            width: 100%;
        }
    }
</style>

<!-- CKEditor -->
<script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>

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
            <i class="fas fa-cubes" style="color: var(--gold);"></i>
            6 Arms Management
        </h1>
        <div style="color: var(--gray-600);">
            <i class="fas fa-cube"></i> 
            Manage AGPN's core service offerings
        </div>
    </div>
    
    <div style="display: flex; gap: 15px;">
        <button class="btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> Add New Arm
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="arms-stats-grid">
    <div class="arms-stat-card">
        <div class="arms-stat-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-cubes"></i>
        </div>
        <div class="arms-stat-info">
            <h4>Total Arms</h4>
            <span class="number"><?php echo $total_arms; ?></span>
        </div>
    </div>
    
    <div class="arms-stat-card">
        <div class="arms-stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="arms-stat-info">
            <h4>Active</h4>
            <span class="number"><?php echo $active_arms; ?></span>
        </div>
    </div>
    
    <div class="arms-stat-card">
        <div class="arms-stat-icon" style="background: rgba(108,117,125,0.1); color: var(--gray-600);">
            <i class="fas fa-minus-circle"></i>
        </div>
        <div class="arms-stat-info">
            <h4>Inactive</h4>
            <span class="number"><?php echo $inactive_arms; ?></span>
        </div>
    </div>
    
    <div class="arms-stat-card">
        <div class="arms-stat-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
            <i class="fas fa-sort-numeric-down"></i>
        </div>
        <div class="arms-stat-info">
            <h4>Max Order</h4>
            <span class="number"><?php echo $max_order; ?></span>
        </div>
    </div>
</div>

<!-- Bulk Actions -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" id="selectAll" style="width: 18px; height: 18px; cursor: pointer;">
            <label for="selectAll" style="font-size: 14px;">Select All</label>
        </div>
        
        <select id="bulkActionSelect" class="form-control" style="width: 200px;" disabled>
            <option value="">Bulk Actions</option>
            <option value="active">Set Active</option>
            <option value="inactive">Set Inactive</option>
            <option value="delete">Delete Selected</option>
        </select>
        
        <button id="applyBulkAction" class="btn-apply" disabled>Apply</button>
    </div>
    
    <div style="font-size: 14px; color: var(--gray-600);">
        <i class="fas fa-cubes"></i> Total: <strong><?php echo $total_arms; ?></strong> arms
    </div>
</div>

<!-- Arms Grid -->
<?php if ($arms): ?>
    <div class="arms-grid" id="armsGrid">
        <?php foreach ($arms as $arm): ?>
            <div class="arm-card <?php echo $arm['status'] != 'active' ? 'inactive' : ''; ?>" data-id="<?php echo $arm['id']; ?>">
                <input type="checkbox" class="arm-checkbox" value="<?php echo $arm['id']; ?>">
                
                <div class="arm-actions">
                    <a href="#" onclick="openEditModal(<?php echo $arm['id']; ?>); return false;" class="btn-arm-action" title="Edit Arm">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="?delete=<?php echo $arm['id']; ?>" class="btn-arm-action delete" title="Delete Arm" 
                       onclick="return confirm('Are you sure you want to delete this arm? This action cannot be undone.')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
                
                <div class="arm-header">
                    <div class="arm-icon">
                        <i class="fas <?php echo $arm['icon_class'] ?: 'fa-cube'; ?>"></i>
                    </div>
                    <div class="arm-title">
                        <h3><?php echo htmlspecialchars($arm['arm_name']); ?></h3>
                        <span class="arm-order">Order #<?php echo $arm['display_order']; ?></span>
                    </div>
                </div>
                
                <?php if (!empty($arm['featured_image'])): ?>
                    <div class="arm-image">
                        <img src="<?php echo SITE_URL . '/' . $arm['featured_image']; ?>" 
                             alt="<?php echo htmlspecialchars($arm['arm_name']); ?>"
                             loading="lazy">
                    </div>
                <?php else: ?>
                    <div class="arm-image">
                        <div class="arm-image-placeholder">
                            <i class="fas fa-cube"></i>
                            <span>No image</span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="arm-content">
                    <div class="arm-description">
                        <?php echo !empty($arm['short_description']) ? htmlspecialchars(truncateText($arm['short_description'], 120)) : 'No description provided.'; ?>
                    </div>
                    
                    <div class="arm-meta">
                        <span><i class="fas fa-tag"></i> Slug: <?php echo htmlspecialchars($arm['arm_slug']); ?></span>
                        <span><i class="fas fa-calendar"></i> <?php echo formatDate($arm['created_at'], 'M d, Y'); ?></span>
                    </div>
                    
                    <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <span class="status-badge <?php echo $arm['status']; ?>">
                            <i class="fas fa-<?php echo $arm['status'] == 'active' ? 'check-circle' : 'minus-circle'; ?>"></i>
                            <?php echo ucfirst($arm['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="order-badge">
                    <i class="fas fa-sort-numeric-down"></i> Position: <?php echo $arm['display_order']; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-cubes"></i>
        <h3>No Arms Created Yet</h3>
        <p>Start by adding your first service arm. The 6 Arms are core to AGPN's offerings.</p>
        <button onclick="openAddModal()" class="btn-primary" style="margin-top: 15px;">
            <i class="fas fa-plus-circle"></i> Create Your First Arm
        </button>
    </div>
<?php endif; ?>

<!-- Add Arm Modal -->
<div id="addArmModal" class="arm-modal">
    <div class="arm-modal-content">
        <span class="arm-modal-close" onclick="closeAddModal()">&times;</span>
        
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-plus-circle" style="color: var(--gold);"></i>
            Add New Service Arm
        </h2>
        
        <form method="POST" action="" enctype="multipart/form-data" id="addArmForm">
            <input type="hidden" name="add_arm" value="1">
            
            <div class="form-group">
                <label>Arm Name <span style="color: #dc3545;">*</span></label>
                <input type="text" name="arm_name" id="add_arm_name" class="form-control" 
                       placeholder="e.g., Digital Skills Training" required>
            </div>
            
            <div class="form-group">
                <label>Arm Slug (URL)</label>
                <div class="slug-preview">
                    <i class="fas fa-link"></i>
                    <span><?php echo SITE_URL; ?>/arms/</span>
                    <input type="text" name="arm_slug" id="add_arm_slug" class="form-control" 
                           placeholder="digital-skills-training">
                </div>
                <small style="color: var(--gray-600);">Leave empty to auto-generate from name</small>
            </div>
            
            <div class="form-group">
                <label>Icon Selection</label>
                <div class="selected-icon-display" id="selectedIconDisplay_add">
                    <div class="selected-icon-preview" id="selectedIconPreview_add">
                        <i class="fas fa-cube"></i>
                    </div>
                    <div>
                        <strong>Selected Icon:</strong> 
                        <span id="selectedIconName_add">fa-cube (Default)</span>
                    </div>
                </div>
                <input type="hidden" name="icon_class" id="icon_class_add" value="fa-cube">
                
                <div style="margin-top: 15px;">
                    <button type="button" class="btn-secondary" onclick="toggleIconPicker('add')">
                        <i class="fas fa-icons"></i> Choose Icon
                    </button>
                </div>
                
                <div id="iconPicker_add" class="icon-picker-grid" style="display: none; margin-top: 15px;">
                    <?php foreach ($icons as $icon_class => $icon_name): ?>
                        <div class="icon-item" onclick="selectIcon('add', '<?php echo $icon_class; ?>', '<?php echo $icon_name; ?>')">
                            <i class="fas <?php echo $icon_class; ?>"></i>
                            <span><?php echo $icon_name; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Featured Image</label>
                <div class="image-preview-container" onclick="document.getElementById('arm_image_add').click()">
                    <img id="imagePreview_add" class="arm-preview" style="display: none;">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: var(--gold); margin-bottom: 10px;"></i>
                    <p style="color: var(--gray-600); margin: 0;">Click to upload or drag and drop</p>
                    <p style="font-size: 12px; color: var(--gray-500); margin-top: 8px;">Recommended: 600x400px, JPG/PNG up to 5MB</p>
                </div>
                <input type="file" name="featured_image" id="arm_image_add" accept="image/*" style="display: none;">
            </div>
            
            <div class="form-group">
                <label>Short Description</label>
                <textarea name="short_description" id="add_short_description" class="form-control" rows="3" 
                          placeholder="Brief overview of this service arm (max 200 characters)"></textarea>
            </div>
            
            <div class="form-group">
                <label>Full Description</label>
                <textarea name="full_description" id="add_full_description" class="form-control" rows="6"></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" class="form-control" 
                           value="<?php echo $max_order + 1; ?>" min="0">
                    <small>Lower numbers appear first</small>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Save Arm
                </button>
                <button type="button" class="btn-secondary" onclick="closeAddModal()" style="flex: 1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Arm Modal -->
<div id="editArmModal" class="arm-modal">
    <div class="arm-modal-content">
        <span class="arm-modal-close" onclick="closeEditModal()">&times;</span>
        
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-edit" style="color: var(--gold);"></i>
            Edit Service Arm
        </h2>
        
        <form method="POST" action="" enctype="multipart/form-data" id="editArmForm">
            <input type="hidden" name="edit_arm" value="1">
            <input type="hidden" name="arm_id" id="edit_arm_id">
            <input type="hidden" name="remove_image" id="edit_remove_image" value="0">
            
            <div class="form-group">
                <label>Arm Name <span style="color: #dc3545;">*</span></label>
                <input type="text" name="arm_name" id="edit_arm_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Arm Slug (URL)</label>
                <div class="slug-preview">
                    <i class="fas fa-link"></i>
                    <span><?php echo SITE_URL; ?>/arms/</span>
                    <input type="text" name="arm_slug" id="edit_arm_slug" class="form-control">
                </div>
            </div>
            
            <div class="form-group">
                <label>Icon Selection</label>
                <div class="selected-icon-display" id="selectedIconDisplay_edit">
                    <div class="selected-icon-preview" id="selectedIconPreview_edit">
                        <i class="fas fa-cube"></i>
                    </div>
                    <div>
                        <strong>Selected Icon:</strong> 
                        <span id="selectedIconName_edit">fa-cube (Default)</span>
                    </div>
                </div>
                <input type="hidden" name="icon_class" id="icon_class_edit" value="fa-cube">
                
                <div style="margin-top: 15px;">
                    <button type="button" class="btn-secondary" onclick="toggleIconPicker('edit')">
                        <i class="fas fa-icons"></i> Change Icon
                    </button>
                </div>
                
                <div id="iconPicker_edit" class="icon-picker-grid" style="display: none; margin-top: 15px;">
                    <?php foreach ($icons as $icon_class => $icon_name): ?>
                        <div class="icon-item" onclick="selectIcon('edit', '<?php echo $icon_class; ?>', '<?php echo $icon_name; ?>')">
                            <i class="fas <?php echo $icon_class; ?>"></i>
                            <span><?php echo $icon_name; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Featured Image</label>
                <div id="currentImageContainer" style="margin-bottom: 15px; display: none;">
                    <label style="display: block; margin-bottom: 10px;">Current Image:</label>
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <img id="current_featured_image" src="" alt="Current" style="max-width: 200px; max-height: 150px; border-radius: 8px; border: 1px solid var(--gray-200); padding: 5px;">
                        <button type="button" class="btn-secondary" onclick="removeCurrentImage()" style="background: #dc3545; color: white; border-color: #dc3545;">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
                
                <div class="image-preview-container" onclick="document.getElementById('arm_image_edit').click()">
                    <img id="imagePreview_edit" class="arm-preview" style="display: none;">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: var(--gold); margin-bottom: 10px;"></i>
                    <p style="color: var(--gray-600); margin: 0;">Click to upload new image (optional)</p>
                </div>
                <input type="file" name="featured_image" id="arm_image_edit" accept="image/*" style="display: none;">
            </div>
            
            <div class="form-group">
                <label>Short Description</label>
                <textarea name="short_description" id="edit_short_description" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Full Description</label>
                <textarea name="full_description" id="edit_full_description" class="form-control" rows="6"></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" id="edit_display_order" class="form-control" min="0">
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Update Arm
                </button>
                <button type="button" class="btn-secondary" onclick="closeEditModal()" style="flex: 1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// 6 ARMS MANAGEMENT - JAVASCRIPT (FIXED)
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    console.log('6 Arms management page loaded');
    
    // ============================================
    // CKEDITOR INITIALIZATION
    // ============================================
    if (typeof CKEDITOR !== 'undefined') {
        // Initialize add form editor
        if (document.getElementById('add_full_description')) {
            CKEDITOR.replace('add_full_description', {
                height: 250,
                toolbarGroups: [
                    { name: 'basicstyles', groups: ['basicstyles', 'cleanup'] },
                    { name: 'paragraph', groups: ['list', 'indent', 'blocks', 'align'] },
                    { name: 'links', groups: ['links'] },
                    { name: 'insert', groups: ['insert'] },
                    { name: 'styles', groups: ['styles'] },
                    { name: 'colors', groups: ['colors'] }
                ],
                removeButtons: 'Save,NewPage,Preview,Print,Templates,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,CreateDiv,BidiLtr,BidiRtl,Language,PageBreak,Iframe,Flash,Smiley,About'
            });
        }
        
        // Initialize edit form editor
        if (document.getElementById('edit_full_description')) {
            CKEDITOR.replace('edit_full_description', {
                height: 250,
                toolbarGroups: [
                    { name: 'basicstyles', groups: ['basicstyles', 'cleanup'] },
                    { name: 'paragraph', groups: ['list', 'indent', 'blocks', 'align'] },
                    { name: 'links', groups: ['links'] },
                    { name: 'insert', groups: ['insert'] },
                    { name: 'styles', groups: ['styles'] },
                    { name: 'colors', groups: ['colors'] }
                ],
                removeButtons: 'Save,NewPage,Preview,Print,Templates,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,CreateDiv,BidiLtr,BidiRtl,Language,PageBreak,Iframe,Flash,Smiley,About'
            });
        }
    }
    
    // ============================================
    // ADD MODAL FUNCTIONS
    // ============================================
    const addModal = document.getElementById('addArmModal');
    
    window.openAddModal = function() {
        if (addModal) {
            addModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };
    
    window.closeAddModal = function() {
        if (addModal) {
            addModal.classList.remove('active');
            document.body.style.overflow = '';
            // Reset form
            document.getElementById('addArmForm').reset();
            document.getElementById('imagePreview_add').style.display = 'none';
            document.getElementById('icon_class_add').value = 'fa-cube';
            document.getElementById('selectedIconPreview_add').innerHTML = '<i class="fas fa-cube"></i>';
            document.getElementById('selectedIconName_add').innerHTML = 'fa-cube (Default)';
            
            // Reset CKEditor
            if (CKEDITOR.instances.add_full_description) {
                CKEDITOR.instances.add_full_description.setData('');
            }
        }
    };
    
    // ============================================
    // EDIT MODAL FUNCTIONS
    // ============================================
    const editModal = document.getElementById('editArmModal');
    
    window.openEditModal = function(id) {
        // Fetch arm data via AJAX
        fetch(`ajax-get-arm.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const arm = data.arm;
                    
                    document.getElementById('edit_arm_id').value = arm.id;
                    document.getElementById('edit_arm_name').value = arm.arm_name;
                    document.getElementById('edit_arm_slug').value = arm.arm_slug;
                    document.getElementById('edit_short_description').value = arm.short_description || '';
                    document.getElementById('edit_display_order').value = arm.display_order || 0;
                    document.getElementById('edit_status').value = arm.status || 'active';
                    
                    // Set icon
                    document.getElementById('icon_class_edit').value = arm.icon_class || 'fa-cube';
                    document.getElementById('selectedIconPreview_edit').innerHTML = `<i class="fas ${arm.icon_class || 'fa-cube'}"></i>`;
                    document.getElementById('selectedIconName_edit').innerHTML = `${arm.icon_class || 'fa-cube'} (Custom)`;
                    
                    // Set CKEditor content
                    if (CKEDITOR.instances.edit_full_description) {
                        CKEDITOR.instances.edit_full_description.setData(arm.full_description || '');
                    }
                    
                    // Handle featured image
                    if (arm.featured_image) {
                        const img = document.getElementById('current_featured_image');
                        img.src = '<?php echo SITE_URL; ?>/' + arm.featured_image;
                        document.getElementById('currentImageContainer').style.display = 'block';
                    } else {
                        document.getElementById('currentImageContainer').style.display = 'none';
                    }
                    
                    // Reset remove image flag
                    document.getElementById('edit_remove_image').value = '0';
                    
                    editModal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            })
            .catch(error => console.error('Error:', error));
    };
    
    window.closeEditModal = function() {
        if (editModal) {
            editModal.classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('edit_remove_image').value = '0';
            document.getElementById('imagePreview_edit').style.display = 'none';
        }
    };
    
    window.removeCurrentImage = function() {
        document.getElementById('currentImageContainer').style.display = 'none';
        document.getElementById('edit_remove_image').value = '1';
    };
    
    // ============================================
    // SLUG GENERATOR
    // ============================================
    const addArmName = document.getElementById('add_arm_name');
    const addArmSlug = document.getElementById('add_arm_slug');
    
    if (addArmName && addArmSlug) {
        addArmName.addEventListener('keyup', function() {
            if (!addArmSlug.value || addArmSlug.value === '') {
                addArmSlug.value = this.value
                    .toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/--+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .trim();
            }
        });
    }
    
    const editArmName = document.getElementById('edit_arm_name');
    const editArmSlug = document.getElementById('edit_arm_slug');
    
    if (editArmName && editArmSlug) {
        editArmName.addEventListener('keyup', function() {
            if (!editArmSlug.value || editArmSlug.value === '') {
                editArmSlug.value = this.value
                    .toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/--+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .trim();
            }
        });
    }
    
    // ============================================
    // IMAGE PREVIEW
    // ============================================
    window.previewImage = function(input, previewId) {
        const preview = document.getElementById(previewId);
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    };
    
    // Add image preview listeners
    const armImageAdd = document.getElementById('arm_image_add');
    if (armImageAdd) {
        armImageAdd.addEventListener('change', function() {
            previewImage(this, 'imagePreview_add');
        });
    }
    
    const armImageEdit = document.getElementById('arm_image_edit');
    if (armImageEdit) {
        armImageEdit.addEventListener('change', function() {
            previewImage(this, 'imagePreview_edit');
        });
    }
    
    // ============================================
    // ICON PICKER
    // ============================================
    window.toggleIconPicker = function(type) {
        const picker = document.getElementById(`iconPicker_${type}`);
        if (picker) {
            if (picker.style.display === 'none' || picker.style.display === '') {
                picker.style.display = 'grid';
            } else {
                picker.style.display = 'none';
            }
        }
    };
    
    window.selectIcon = function(type, iconClass, iconName) {
        // Update hidden input
        const iconInput = document.getElementById(`icon_class_${type}`);
        if (iconInput) iconInput.value = iconClass;
        
        // Update preview
        const preview = document.getElementById(`selectedIconPreview_${type}`);
        if (preview) preview.innerHTML = `<i class="fas ${iconClass}"></i>`;
        
        const nameSpan = document.getElementById(`selectedIconName_${type}`);
        if (nameSpan) nameSpan.innerHTML = `${iconClass} (${iconName})`;
        
        // Hide picker
        const picker = document.getElementById(`iconPicker_${type}`);
        if (picker) picker.style.display = 'none';
    };
    
    // ============================================
    // SELECT ALL FUNCTIONALITY
    // ============================================
    const selectAll = document.getElementById('selectAll');
    const armCheckboxes = document.querySelectorAll('.arm-checkbox');
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const applyButton = document.getElementById('applyBulkAction');
    
    function updateBulkActionState() {
        const checked = document.querySelectorAll('.arm-checkbox:checked').length;
        if (bulkActionSelect) bulkActionSelect.disabled = checked === 0;
        if (applyButton) applyButton.disabled = checked === 0;
    }
    
    function updateSelectAllState() {
        if (!selectAll) return;
        const checked = document.querySelectorAll('.arm-checkbox:checked').length;
        selectAll.checked = checked === armCheckboxes.length && armCheckboxes.length > 0;
        selectAll.indeterminate = checked > 0 && checked < armCheckboxes.length;
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            armCheckboxes.forEach(cb => {
                if (cb) cb.checked = this.checked;
            });
            updateBulkActionState();
            updateSelectAllState();
        });
    }
    
    armCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateBulkActionState();
            updateSelectAllState();
        });
    });
    
    // ============================================
    // BULK ACTION APPLY
    // ============================================
    if (applyButton) {
        applyButton.addEventListener('click', function() {
            const action = bulkActionSelect.value;
            const checkedIds = [];
            
            document.querySelectorAll('.arm-checkbox:checked').forEach(cb => {
                checkedIds.push(cb.value);
            });
            
            if (action && checkedIds.length > 0) {
                let confirmMessage = '';
                if (action === 'delete') {
                    confirmMessage = `Are you sure you want to delete ${checkedIds.length} arm(s)? This action cannot be undone.`;
                } else {
                    confirmMessage = `Are you sure you want to mark ${checkedIds.length} arm(s) as ${action}?`;
                }
                
                if (confirm(confirmMessage)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'arms.php';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'bulk_action';
                    actionInput.value = action;
                    
                    const idsInput = document.createElement('input');
                    idsInput.type = 'hidden';
                    idsInput.name = 'arm_ids';
                    idsInput.value = checkedIds.join(',');
                    
                    form.appendChild(actionInput);
                    form.appendChild(idsInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        });
    }
    
    // ============================================
    // CLOSE MODALS WHEN CLICKING OUTSIDE
    // ============================================
    window.addEventListener('click', function(e) {
        if (e.target == addModal) {
            closeAddModal();
        }
        if (e.target == editModal) {
            closeEditModal();
        }
    });
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+N - Add new arm
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            openAddModal();
        }
        
        // Escape - Close modals
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
        }
    });
    
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
    // INITIAL BULK ACTION STATE
    // ============================================
    updateBulkActionState();
    updateSelectAllState();
    
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