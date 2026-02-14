<?php
// ============================================
// CERTIFICATES MANAGEMENT PAGE - UPDATED
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

// Handle add certificate
if (isset($_POST['add_certificate'])) {
    $certificate_title = sanitize($_POST['certificate_title']);
    $issuing_body = sanitize($_POST['issuing_body']);
    $issue_date = sanitize($_POST['issue_date']);
    $expiry_date = sanitize($_POST['expiry_date']) ?: null;
    $display_order = (int)sanitize($_POST['display_order']);
    $status = sanitize($_POST['status']);
    
    // Handle certificate image upload
    if (isset($_FILES['certificate_image']) && $_FILES['certificate_image']['error'] === 0) {
        $upload = uploadImage($_FILES['certificate_image'], 'certificates');
        
        if ($upload['success']) {
            $certificate_image = $upload['path'];
            
            try {
                $stmt = db()->prepare("
                    INSERT INTO certificates (
                        certificate_title, certificate_image, issuing_body, 
                        issue_date, expiry_date, display_order, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $certificate_title,
                    $certificate_image,
                    $issuing_body ?: null,
                    $issue_date ?: null,
                    $expiry_date,
                    $display_order,
                    $status
                ]);
                
                $_SESSION['success'] = 'Certificate added successfully';
                redirect('certificates.php');
            } catch (PDOException $e) {
                error_log("Error adding certificate: " . $e->getMessage());
                $_SESSION['error'] = 'Error adding certificate';
            }
        } else {
            $_SESSION['error'] = $upload['error'];
        }
    } else {
        $_SESSION['error'] = 'Please select a certificate image';
    }
    redirect('certificates.php');
}

// Handle edit certificate
if (isset($_POST['edit_certificate'])) {
    $certificate_id = (int)$_POST['certificate_id'];
    $certificate_title = sanitize($_POST['certificate_title']);
    $issuing_body = sanitize($_POST['issuing_body']);
    $issue_date = sanitize($_POST['issue_date']);
    $expiry_date = sanitize($_POST['expiry_date']) ?: null;
    $display_order = (int)sanitize($_POST['display_order']);
    $status = sanitize($_POST['status']);
    
    try {
        // Get current image
        $stmt = db()->prepare("SELECT certificate_image FROM certificates WHERE id = ?");
        $stmt->execute([$certificate_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $certificate_image = $current['certificate_image'];
        
        // Handle new image upload
        if (isset($_FILES['certificate_image']) && $_FILES['certificate_image']['error'] === 0) {
            $upload = uploadImage($_FILES['certificate_image'], 'certificates');
            
            if ($upload['success']) {
                // Delete old image
                if ($certificate_image && file_exists('../' . $certificate_image)) {
                    unlink('../' . $certificate_image);
                }
                $certificate_image = $upload['path'];
            } else {
                $_SESSION['error'] = $upload['error'];
            }
        }
        
        if (!isset($_SESSION['error'])) {
            $stmt = db()->prepare("
                UPDATE certificates SET
                    certificate_title = ?,
                    certificate_image = ?,
                    issuing_body = ?,
                    issue_date = ?,
                    expiry_date = ?,
                    display_order = ?,
                    status = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $certificate_title,
                $certificate_image,
                $issuing_body ?: null,
                $issue_date ?: null,
                $expiry_date,
                $display_order,
                $status,
                $certificate_id
            ]);
            
            $_SESSION['success'] = 'Certificate updated successfully';
            redirect('certificates.php');
        }
    } catch (PDOException $e) {
        error_log("Error updating certificate: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating certificate';
    }
    redirect('certificates.php');
}

// Handle delete certificate
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $certificate_id = (int)$_GET['delete'];
    
    try {
        // Get image path
        $stmt = db()->prepare("SELECT certificate_image FROM certificates WHERE id = ?");
        $stmt->execute([$certificate_id]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete image file
        if ($cert && $cert['certificate_image'] && file_exists('../' . $cert['certificate_image'])) {
            unlink('../' . $cert['certificate_image']);
        }
        
        // Delete record
        $stmt = db()->prepare("DELETE FROM certificates WHERE id = ?");
        $stmt->execute([$certificate_id]);
        
        $_SESSION['success'] = 'Certificate deleted successfully';
    } catch (PDOException $e) {
        error_log("Error deleting certificate: " . $e->getMessage());
        $_SESSION['error'] = 'Error deleting certificate';
    }
    redirect('certificates.php');
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['certificate_ids'])) {
    $action = $_POST['bulk_action'];
    $cert_ids = explode(',', $_POST['certificate_ids']);
    $cert_ids = array_filter(array_map('intval', $cert_ids));
    
    if (!empty($cert_ids) && !empty($action)) {
        try {
            $placeholders = implode(',', array_fill(0, count($cert_ids), '?'));
            
            if ($action === 'delete') {
                // Get all images
                $stmt = db()->prepare("SELECT certificate_image FROM certificates WHERE id IN ($placeholders)");
                $stmt->execute($cert_ids);
                $certs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Delete image files
                foreach ($certs as $cert) {
                    if ($cert['certificate_image'] && file_exists('../' . $cert['certificate_image'])) {
                        unlink('../' . $cert['certificate_image']);
                    }
                }
                
                // Delete records
                $stmt = db()->prepare("DELETE FROM certificates WHERE id IN ($placeholders)");
                $stmt->execute($cert_ids);
                $_SESSION['success'] = count($cert_ids) . ' certificates deleted successfully';
            } elseif ($action === 'active' || $action === 'inactive') {
                $stmt = db()->prepare("UPDATE certificates SET status = ? WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$action], $cert_ids));
                $_SESSION['success'] = count($cert_ids) . ' certificates updated successfully';
            }
        } catch (PDOException $e) {
            error_log("Bulk action error: " . $e->getMessage());
            $_SESSION['error'] = 'Error performing bulk action';
        }
    }
    redirect('certificates.php');
}

// Get all certificates
try {
    $db = db();
    
    $stmt = $db->query("
        SELECT * FROM certificates 
        ORDER BY 
            CASE WHEN status = 'active' THEN 0 ELSE 1 END,
            display_order ASC,
            created_at DESC
    ");
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics
    $total_certificates = count($certificates);
    $active_certificates = $db->query("SELECT COUNT(*) FROM certificates WHERE status = 'active'")->fetchColumn() ?: 0;
    $expiring_soon = $db->query("
        SELECT COUNT(*) FROM certificates 
        WHERE expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ")->fetchColumn() ?: 0;
    $expired = $db->query("
        SELECT COUNT(*) FROM certificates 
        WHERE expiry_date IS NOT NULL 
        AND expiry_date < CURDATE()
    ")->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    error_log("Error fetching certificates: " . $e->getMessage());
    $certificates = [];
    $total_certificates = $active_certificates = $expiring_soon = $expired = 0;
}

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Set page title
$page_title = 'Certificates Management';

// Breadcrumbs
$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => '#', 'title' => 'Certificates']
];

// Page specific CSS
$page_css = ['certificates.css'];

// Include admin header
require_once '../includes/admin-header.php';
?>

<!-- Page Specific Styles -->
<style>
    /* Certificates Management Specific Styles */
    .cert-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    
    .cert-stat-card {
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
    
    .cert-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .cert-stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }
    
    .cert-stat-info h4 {
        font-size: 14px;
        color: var(--gray-600);
        margin-bottom: 8px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .cert-stat-info .number {
        font-size: 32px;
        font-weight: 700;
        color: var(--navy);
        line-height: 1;
    }
    
    .cert-stat-info .trend {
        font-size: 12px;
        margin-left: 8px;
    }
    
    .trend.warning {
        color: #ffc107;
    }
    
    .trend.danger {
        color: #dc3545;
    }
    
    /* Certificates Grid */
    .certificates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 25px;
        margin-top: 25px;
    }
    
    .certificate-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        border: 1px solid var(--gray-200);
        transition: all 0.3s ease;
        position: relative;
    }
    
    .certificate-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        border-color: var(--gold);
    }
    
    .certificate-card.inactive {
        opacity: 0.7;
        background: var(--gray-100);
    }
    
    .certificate-image {
        height: 200px;
        background: var(--gray-100);
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }
    
    .certificate-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        transition: transform 0.5s ease;
        padding: 15px;
    }
    
    .certificate-card:hover .certificate-image img {
        transform: scale(1.05);
    }
    
    .certificate-overlay {
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
    
    .certificate-card:hover .certificate-overlay {
        opacity: 1;
    }
    
    .certificate-overlay a {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--navy);
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .certificate-overlay a:hover {
        background: var(--gold);
        transform: scale(1.1);
    }
    
    .certificate-details {
        padding: 20px;
    }
    
    .certificate-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--navy);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .certificate-meta {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 15px;
    }
    
    .certificate-meta-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: var(--gray-600);
    }
    
    .certificate-meta-item i {
        width: 16px;
        color: var(--gold);
    }
    
    .certificate-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 15px;
        border-top: 1px solid var(--gray-200);
        margin-top: 5px;
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
    
    .status-badge.expiring {
        background: rgba(255,193,7,0.1);
        color: #856404;
    }
    
    .status-badge.expired {
        background: rgba(220,53,69,0.1);
        color: #dc3545;
    }
    
    .certificate-actions {
        position: absolute;
        top: 15px;
        right: 15px;
        display: flex;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 10;
    }
    
    .certificate-card:hover .certificate-actions {
        opacity: 1;
    }
    
    .btn-cert-action {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        transition: all 0.2s ease;
        text-decoration: none;
    }
    
    .btn-cert-action:hover {
        background: var(--gold);
        color: var(--navy);
        border-color: var(--gold);
        transform: translateY(-2px);
    }
    
    .btn-cert-action.delete:hover {
        background: #dc3545;
        color: white;
        border-color: #dc3545;
    }
    
    .cert-checkbox {
        position: absolute;
        top: 15px;
        left: 15px;
        width: 18px;
        height: 18px;
        z-index: 15;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .certificate-card:hover .cert-checkbox,
    .cert-checkbox:checked {
        opacity: 1;
    }
    
    /* Modal Styles */
    .cert-modal {
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
    
    .cert-modal.active {
        display: flex;
    }
    
    .cert-modal-content {
        background: white;
        border-radius: 20px;
        padding: 35px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .cert-modal-close {
        position: absolute;
        top: 20px;
        right: 25px;
        font-size: 28px;
        color: var(--gray-500);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .cert-modal-close:hover {
        color: #dc3545;
        transform: rotate(90deg);
    }
    
    .image-preview-container {
        border: 2px dashed var(--gray-300);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        margin-bottom: 20px;
        background: var(--gray-100);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .image-preview-container:hover {
        border-color: var(--gold);
        background: rgba(255,184,28,0.05);
    }
    
    .cert-preview {
        max-width: 100%;
        max-height: 200px;
        object-fit: contain;
        margin-bottom: 15px;
    }
    
    .date-input-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
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
        .certificates-grid {
            grid-template-columns: 1fr;
        }
        
        .cert-stats-grid {
            grid-template-columns: 1fr;
        }
        
        .date-input-group {
            grid-template-columns: 1fr;
        }
        
        .cert-modal-content {
            padding: 25px;
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
<div class="cert-stats-grid animate__animated animate__fadeIn">
    <div class="cert-stat-card">
        <div class="cert-stat-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-certificate"></i>
        </div>
        <div class="cert-stat-info">
            <h4>Total Certificates</h4>
            <span class="number"><?php echo $total_certificates; ?></span>
        </div>
    </div>
    
    <div class="cert-stat-card">
        <div class="cert-stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="cert-stat-info">
            <h4>Active</h4>
            <span class="number"><?php echo $active_certificates; ?></span>
        </div>
    </div>
    
    <div class="cert-stat-card">
        <div class="cert-stat-icon" style="background: rgba(255,193,7,0.1); color: #ffc107;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="cert-stat-info">
            <h4>Expiring Soon</h4>
            <span class="number"><?php echo $expiring_soon; ?></span>
            <span class="trend warning">90 days</span>
        </div>
    </div>
    
    <div class="cert-stat-card">
        <div class="cert-stat-icon" style="background: rgba(220,53,69,0.1); color: #dc3545;">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="cert-stat-info">
            <h4>Expired</h4>
            <span class="number"><?php echo $expired; ?></span>
            <span class="trend danger">needs renewal</span>
        </div>
    </div>
</div>

<!-- Bulk Actions -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;" class="animate__animated animate__fadeIn">
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
        <i class="fas fa-certificate"></i> Total: <strong><?php echo count($certificates); ?></strong> certificates
    </div>
</div>

<!-- Certificates Grid -->
<?php if ($certificates): ?>
    <div class="certificates-grid animate__animated animate__fadeIn" id="certificatesGrid">
        <?php foreach ($certificates as $cert): ?>
            <?php
            $is_expired = false;
            $is_expiring = false;
            
            if ($cert['expiry_date']) {
                $expiry = strtotime($cert['expiry_date']);
                $now = time();
                $days_to_expiry = ceil(($expiry - $now) / 86400);
                
                if ($expiry < $now) {
                    $is_expired = true;
                } elseif ($days_to_expiry <= 90) {
                    $is_expiring = true;
                }
            }
            ?>
            
            <div class="certificate-card <?php echo $cert['status'] != 'active' ? 'inactive' : ''; ?>" data-id="<?php echo $cert['id']; ?>">
                <input type="checkbox" class="cert-checkbox" value="<?php echo $cert['id']; ?>">
                
                <div class="certificate-actions">
                    <a href="#" onclick="openEditModal(<?php echo $cert['id']; ?>)" class="btn-cert-action" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="?delete=<?php echo $cert['id']; ?>" class="btn-cert-action delete" title="Delete" 
                       onclick="return confirm('Are you sure you want to delete this certificate?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
                
                <div class="certificate-image">
                    <a href="<?php echo SITE_URL . '/' . $cert['certificate_image']; ?>" 
                       data-lightbox="certificates"
                       data-title="<?php echo htmlspecialchars($cert['certificate_title']); ?>">
                        <img src="<?php echo SITE_URL . '/' . $cert['certificate_image']; ?>" 
                             alt="<?php echo htmlspecialchars($cert['certificate_title']); ?>"
                             loading="lazy">
                    </a>
                    <div class="certificate-overlay">
                        <a href="<?php echo SITE_URL . '/' . $cert['certificate_image']; ?>" 
                           data-lightbox="certificates-<?php echo $cert['id']; ?>"
                           data-title="<?php echo htmlspecialchars($cert['certificate_title']); ?>">
                            <i class="fas fa-search-plus"></i>
                        </a>
                        <a href="#" onclick="openEditModal(<?php echo $cert['id']; ?>)">
                            <i class="fas fa-pencil-alt"></i>
                        </a>
                    </div>
                </div>
                
                <div class="certificate-details">
                    <div class="certificate-title">
                        <?php echo htmlspecialchars($cert['certificate_title']); ?>
                        <?php if ($is_expired): ?>
                            <span class="status-badge expired">Expired</span>
                        <?php elseif ($is_expiring): ?>
                            <span class="status-badge expiring">Expiring Soon</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="certificate-meta">
                        <?php if ($cert['issuing_body']): ?>
                            <div class="certificate-meta-item">
                                <i class="fas fa-building"></i>
                                <span><?php echo htmlspecialchars($cert['issuing_body']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($cert['issue_date']): ?>
                            <div class="certificate-meta-item">
                                <i class="fas fa-calendar-check"></i>
                                <span>Issued: <?php echo formatDate($cert['issue_date'], 'M d, Y'); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($cert['expiry_date']): ?>
                            <div class="certificate-meta-item">
                                <i class="fas fa-calendar-times"></i>
                                <span>Expires: <?php echo formatDate($cert['expiry_date'], 'M d, Y'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="certificate-footer">
                        <span class="status-badge <?php echo $cert['status']; ?>">
                            <i class="fas fa-<?php echo $cert['status'] == 'active' ? 'check-circle' : 'minus-circle'; ?>"></i>
                            <?php echo ucfirst($cert['status']); ?>
                        </span>
                        <span style="font-size: 12px; color: var(--gray-500);">
                            <i class="fas fa-clock"></i> <?php echo formatDate($cert['created_at'], 'M d, Y'); ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state animate__animated animate__fadeIn">
        <i class="fas fa-certificate"></i>
        <h3>No Certificates Yet</h3>
        <p>Add your first certificate to display accreditations and achievements.</p>
        <button onclick="openAddModal()" class="btn-primary" style="margin-top: 15px;">
            <i class="fas fa-plus-circle"></i> Add Certificate
        </button>
    </div>
<?php endif; ?>

<!-- Add Certificate Modal -->
<div id="addCertificateModal" class="cert-modal">
    <div class="cert-modal-content">
        <span class="cert-modal-close" onclick="closeAddModal()">&times;</span>
        
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-plus-circle" style="color: var(--gold);"></i>
            Add New Certificate
        </h2>
        
        <form method="POST" action="" enctype="multipart/form-data" id="addCertificateForm">
            <input type="hidden" name="add_certificate" value="1">
            
            <div class="form-group">
                <label>Certificate Title <span style="color: #dc3545;">*</span></label>
                <input type="text" name="certificate_title" class="form-control" placeholder="e.g., ISO 9001:2025 Certification" required>
            </div>
            
            <div class="form-group">
                <label>Certificate Image <span style="color: #dc3545;">*</span></label>
                <div class="image-preview-container" onclick="document.getElementById('cert_image_add').click()">
                    <img id="imagePreview_add" class="cert-preview" style="display: none;">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: var(--gold); margin-bottom: 10px;"></i>
                    <p style="color: var(--gray-600); margin: 0;">Click to upload or drag and drop</p>
                    <p style="font-size: 12px; color: var(--gray-500); margin-top: 8px;">PNG, JPG, GIF up to 5MB</p>
                </div>
                <input type="file" name="certificate_image" id="cert_image_add" accept="image/*" style="display: none;" onchange="previewImage(this, 'imagePreview_add')" required>
            </div>
            
            <div class="form-group">
                <label>Issuing Body</label>
                <input type="text" name="issuing_body" class="form-control" placeholder="e.g., International Organization for Standardization">
            </div>
            
            <div class="date-input-group">
                <div class="form-group">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" class="form-control" value="0" min="0">
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
                    <i class="fas fa-save"></i> Save Certificate
                </button>
                <button type="button" class="btn-secondary" onclick="closeAddModal()" style="flex: 1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Certificate Modal -->
<div id="editCertificateModal" class="cert-modal">
    <div class="cert-modal-content">
        <span class="cert-modal-close" onclick="closeEditModal()">&times;</span>
        
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-edit" style="color: var(--gold);"></i>
            Edit Certificate
        </h2>
        
        <form method="POST" action="" enctype="multipart/form-data" id="editCertificateForm">
            <input type="hidden" name="edit_certificate" value="1">
            <input type="hidden" name="certificate_id" id="edit_certificate_id">
            
            <div class="form-group">
                <label>Certificate Title <span style="color: #dc3545;">*</span></label>
                <input type="text" name="certificate_title" id="edit_certificate_title" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Certificate Image</label>
                <div class="image-preview-container" onclick="document.getElementById('cert_image_edit').click()">
                    <img id="imagePreview_edit" class="cert-preview" style="display: none;">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: var(--gold); margin-bottom: 10px;"></i>
                    <p style="color: var(--gray-600); margin: 0;">Click to change image (optional)</p>
                </div>
                <input type="file" name="certificate_image" id="cert_image_edit" accept="image/*" style="display: none;" onchange="previewImage(this, 'imagePreview_edit')">
            </div>
            
            <div class="form-group">
                <label>Issuing Body</label>
                <input type="text" name="issuing_body" id="edit_issuing_body" class="form-control">
            </div>
            
            <div class="date-input-group">
                <div class="form-group">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date" id="edit_issue_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" id="edit_expiry_date" class="form-control">
                </div>
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
                    <i class="fas fa-save"></i> Update Certificate
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
    // CERTIFICATES MANAGEMENT - JAVASCRIPT
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';
        
        // Lightbox configuration
        if (typeof lightbox !== 'undefined') {
            lightbox.option({
                'resizeDuration': 200,
                'wrapAround': true,
                'albumLabel': 'Certificate %1 of %2',
                'fadeDuration': 300,
                'imageFadeDuration': 300
            });
        }
        
        // ============================================
        // MODAL FUNCTIONS
        // ============================================
        
        const addModal = document.getElementById('addCertificateModal');
        const editModal = document.getElementById('editCertificateModal');
        
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
                document.getElementById('addCertificateForm').reset();
                document.getElementById('imagePreview_add').style.display = 'none';
            }
        };
        
        window.openEditModal = function(id) {
            // Fetch certificate data via AJAX
            fetch(`ajax-get-certificate.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_certificate_id').value = data.certificate.id;
                        document.getElementById('edit_certificate_title').value = data.certificate.certificate_title;
                        document.getElementById('edit_issuing_body').value = data.certificate.issuing_body || '';
                        document.getElementById('edit_issue_date').value = data.certificate.issue_date || '';
                        document.getElementById('edit_expiry_date').value = data.certificate.expiry_date || '';
                        document.getElementById('edit_display_order').value = data.certificate.display_order || 0;
                        document.getElementById('edit_status').value = data.certificate.status || 'active';
                        
                        // Show current image preview
                        const preview = document.getElementById('imagePreview_edit');
                        if (data.certificate.certificate_image) {
                            preview.src = '<?php echo SITE_URL; ?>/' + data.certificate.certificate_image;
                            preview.style.display = 'block';
                        }
                        
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
            }
        };
        
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
        
        // ============================================
        // SELECT ALL FUNCTIONALITY
        // ============================================
        
        const selectAll = document.getElementById('selectAll');
        const certCheckboxes = document.querySelectorAll('.cert-checkbox');
        const bulkActionSelect = document.getElementById('bulkActionSelect');
        const applyButton = document.getElementById('applyBulkAction');
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                certCheckboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
                updateBulkActionState();
            });
        }
        
        certCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                updateBulkActionState();
                if (selectAll) {
                    const checked = document.querySelectorAll('.cert-checkbox:checked').length;
                    selectAll.checked = checked === certCheckboxes.length;
                    selectAll.indeterminate = checked > 0 && checked < certCheckboxes.length;
                }
            });
        });
        
        function updateBulkActionState() {
            const checked = document.querySelectorAll('.cert-checkbox:checked').length;
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
                
                document.querySelectorAll('.cert-checkbox:checked').forEach(cb => {
                    checkedIds.push(cb.value);
                });
                
                if (action && checkedIds.length > 0) {
                    let confirmMessage = '';
                    if (action === 'delete') {
                        confirmMessage = `Are you sure you want to delete ${checkedIds.length} certificate(s)?`;
                    } else {
                        confirmMessage = `Are you sure you want to mark ${checkedIds.length} certificate(s) as ${action}?`;
                    }
                    
                    if (confirm(confirmMessage)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'certificates.php';
                        
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'bulk_action';
                        actionInput.value = action;
                        
                        const idsInput = document.createElement('input');
                        idsInput.type = 'hidden';
                        idsInput.name = 'certificate_ids';
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
            // Ctrl+N - Add new certificate
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
        // AUTO-HIDE TOAST NOTIFICATIONS
        // ============================================
        
        setTimeout(() => {
            document.querySelectorAll('.toast-notification').forEach(el => {
                el.style.animation = 'fadeOutRight 0.3s ease';
                setTimeout(() => el.remove(), 300);
            });
        }, 5000);
        
        // ============================================
        // DRAG AND DROP FOR IMAGE UPLOAD
        // ============================================
        
        const dropZones = document.querySelectorAll('.image-preview-container');
        
        dropZones.forEach(zone => {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                zone.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            zone.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                const input = this.nextElementSibling;
                if (input && files.length > 0) {
                    input.files = files;
                    const previewId = input.id === 'cert_image_add' ? 'imagePreview_add' : 'imagePreview_edit';
                    previewImage(input, previewId);
                }
            }, false);
        });
        
        // ============================================
        // INITIAL BULK ACTION STATE
        // ============================================
        
        updateBulkActionState();
        
    }); // End DOMContentLoaded
</script>

<?php
// Include admin footer
require_once '../includes/admin-footer.php';
?>