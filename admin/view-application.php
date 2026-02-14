<?php
// ============================================
// VIEW APPLICATION DETAILS
// Complete application view with file downloads
// ============================================

define('IN_ADMIN', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAuth();

if (!hasPermission('editor')) {
    $_SESSION['error'] = 'You do not have permission to view applications';
    redirect('applications.php');
}

$user = Auth::user();
$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$application_id) {
    redirect('applications.php');
}

// Get application details
try {
    $stmt = db()->prepare("
        SELECT a.*, 
               COUNT(f.id) as additional_files_count
        FROM application_submissions a
        LEFT JOIN application_files f ON a.id = f.application_id
        WHERE a.id = ?
        GROUP BY a.id
    ");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch();
    
    if (!$application) {
        $_SESSION['error'] = 'Application not found';
        redirect('applications.php');
    }
    
    // Get additional files
    $stmt_files = db()->prepare("SELECT * FROM application_files WHERE application_id = ? ORDER BY created_at DESC");
    $stmt_files->execute([$application_id]);
    $additional_files = $stmt_files->fetchAll();
    
    // Mark as read
    if (!$application['is_read']) {
        $stmt_read = db()->prepare("UPDATE application_submissions SET is_read = 1, read_at = NOW() WHERE id = ?");
        $stmt_read->execute([$application_id]);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching application: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading application';
    redirect('applications.php');
}

$page_title = 'View Application - ' . $application['full_name'];

// Handle status update
if (isset($_POST['update_status'])) {
    $new_status = sanitize($_POST['status']);
    $valid_statuses = ['pending', 'reviewed', 'contacted', 'accepted', 'rejected'];
    
    if (in_array($new_status, $valid_statuses)) {
        try {
            $stmt = db()->prepare("UPDATE application_submissions SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $application_id]);
            
            $_SESSION['success'] = "Application status updated to " . ucfirst($new_status);
            redirect('view-application.php?id=' . $application_id);
        } catch (PDOException $e) {
            error_log("Error updating status: " . $e->getMessage());
            $error = "Error updating status";
        }
    }
}

include '../includes/admin-header.php';
?>

<style>
.application-container {
    max-width: 1200px;
    margin: 0 auto;
}

.application-header {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    border-radius: 20px;
    padding: 30px;
    color: white;
    margin-bottom: 30px;
}

.application-header h1 {
    color: white;
    margin-bottom: 10px;
}

.status-selector {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    padding: 10px 20px;
    border-radius: 30px;
    cursor: pointer;
}

.status-selector option {
    background: var(--navy);
    color: white;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
    margin-bottom: 30px;
}

.info-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid var(--gray-200);
}

.info-card h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--gray-200);
}

.info-card h3 i {
    color: var(--gold);
}

.info-row {
    display: flex;
    margin-bottom: 15px;
}

.info-label {
    width: 140px;
    color: var(--gray-600);
    font-size: 14px;
}

.info-value {
    flex: 1;
    font-weight: 500;
}

.message-card {
    background: var(--gray-100);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 30px;
    border-left: 4px solid var(--gold);
}

.message-card h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.file-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 20px;
    border: 1px solid var(--gray-200);
}

.file-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
    background: var(--gray-100);
    border-radius: 12px;
    margin-bottom: 10px;
}

.file-item:last-child {
    margin-bottom: 0;
}

.file-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.file-icon {
    width: 40px;
    height: 40px;
    background: rgba(255,184,28,0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
}

.file-details .file-name {
    font-weight: 600;
    margin-bottom: 4px;
}

.file-details .file-meta {
    font-size: 12px;
    color: var(--gray-600);
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .info-row {
        flex-direction: column;
        gap: 5px;
    }
    
    .info-label {
        width: 100%;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>

<!-- ============================================
     APPLICATION HEADER
============================================ -->
<div class="application-container">
    <div class="application-header">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <span class="program-badge" style="background: var(--gold); color: var(--navy); padding: 8px 20px; border-radius: 30px; font-weight: 600;">
                <i class="fas fa-<?php 
                    echo $application['application_type'] == 'digital-skills' ? 'laptop-code' : 
                        ($application['application_type'] == 'study-abroad' ? 'graduation-cap' : 
                        ($application['application_type'] == 'business-growth' ? 'chart-line' : 'handshake')); 
                ?>"></i>
                <?php echo str_replace('-', ' ', ucwords($application['application_type'])); ?>
            </span>
            
            <form method="POST" style="display: inline-block;">
                <select name="status" class="status-selector" onchange="this.form.submit()">
                    <option value="pending" <?php echo $application['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="reviewed" <?php echo $application['status'] == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                    <option value="contacted" <?php echo $application['status'] == 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                    <option value="accepted" <?php echo $application['status'] == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                    <option value="rejected" <?php echo $application['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <input type="hidden" name="update_status" value="1">
            </form>
        </div>
        
        <h1><?php echo htmlspecialchars($application['full_name']); ?></h1>
        <p>Application #<?php echo $application['id']; ?> • Submitted <?php echo timeAgo($application['created_at']); ?></p>
    </div>
    
    <!-- ========================================
         PERSONAL INFORMATION
    ======================================== -->
    <div class="info-grid">
        <div class="info-card">
            <h3><i class="fas fa-user"></i> Personal Information</h3>
            
            <div class="info-row">
                <span class="info-label">Full Name</span>
                <span class="info-value"><?php echo htmlspecialchars($application['full_name']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Email Address</span>
                <span class="info-value">
                    <a href="mailto:<?php echo $application['email']; ?>"><?php echo $application['email']; ?></a>
                </span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Phone Number</span>
                <span class="info-value">
                    <a href="tel:<?php echo $application['phone']; ?>"><?php echo $application['phone']; ?></a>
                </span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Country</span>
                <span class="info-value"><?php echo htmlspecialchars($application['country']); ?></span>
            </div>
            
            <?php if ($application['date_of_birth']): ?>
            <div class="info-row">
                <span class="info-label">Date of Birth</span>
                <span class="info-value"><?php echo formatDate($application['date_of_birth'], 'F j, Y'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($application['gender']): ?>
            <div class="info-row">
                <span class="info-label">Gender</span>
                <span class="info-value"><?php echo ucfirst($application['gender']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($application['address']): ?>
            <div class="info-row">
                <span class="info-label">Address</span>
                <span class="info-value"><?php echo nl2br(htmlspecialchars($application['address'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- ========================================
             APPLICATION METADATA
        ======================================== -->
        <div class="info-card">
            <h3><i class="fas fa-info-circle"></i> Application Details</h3>
            
            <div class="info-row">
                <span class="info-label">Application ID</span>
                <span class="info-value">#<?php echo $application['id']; ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Program</span>
                <span class="info-value"><?php echo str_replace('-', ' ', ucwords($application['application_type'])); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value">
                    <span class="status-badge status-<?php echo $application['status']; ?>">
                        <?php echo ucfirst($application['status']); ?>
                    </span>
                </span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Submitted</span>
                <span class="info-value"><?php echo formatDate($application['created_at'], 'F j, Y \a\t g:i A'); ?></span>
            </div>
            
            <?php if ($application['read_at']): ?>
            <div class="info-row">
                <span class="info-label">Viewed</span>
                <span class="info-value"><?php echo formatDate($application['read_at'], 'F j, Y \a\t g:i A'); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <span class="info-label">IP Address</span>
                <span class="info-value"><?php echo $application['ip_address'] ?? 'N/A'; ?></span>
            </div>
        </div>
    </div>
    
    <!-- ========================================
         COVER LETTER / STATEMENT OF PURPOSE
    ======================================== -->
    <?php if ($application['cover_letter']): ?>
    <div class="message-card">
        <h3><i class="fas fa-envelope"></i> Cover Letter / Statement of Purpose</h3>
        <div style="line-height: 1.8; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?></div>
    </div>
    <?php endif; ?>
    
    <!-- ========================================
         DOCUMENTS
    ======================================== -->
    <div class="info-card">
        <h3><i class="fas fa-file-alt"></i> Documents</h3>
        
        <!-- CV/Resume -->
        <?php if ($application['cv_path']): ?>
        <div class="file-item">
            <div class="file-info">
                <div class="file-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="file-details">
                    <div class="file-name">CV/Resume</div>
                    <div class="file-meta">
                        Uploaded: <?php echo formatDate($application['created_at'], 'M d, Y'); ?>
                    </div>
                </div>
            </div>
            <a href="<?php echo SITE_URL . '/' . $application['cv_path']; ?>" class="btn-action" download>
                <i class="fas fa-download"></i>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Additional Files -->
        <?php if ($additional_files): ?>
            <h4 style="margin: 25px 0 15px;">Additional Documents</h4>
            <?php foreach ($additional_files as $file): ?>
                <div class="file-item">
                    <div class="file-info">
                        <div class="file-icon">
                            <i class="fas fa-file-<?php echo $file['file_type'] == 'pdf' ? 'pdf' : 'alt'; ?>"></i>
                        </div>
                        <div class="file-details">
                            <div class="file-name"><?php echo htmlspecialchars($file['file_name']); ?></div>
                            <div class="file-meta">
                                <?php echo round($file['file_size'] / 1024, 2); ?> KB • 
                                <?php echo formatDate($file['created_at'], 'M d, Y'); ?>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo SITE_URL . '/' . $file['file_path']; ?>" class="btn-action" download>
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- ========================================
         ACTION BUTTONS
    ======================================== -->
    <div class="action-buttons">
        <a href="mailto:<?php echo $application['email']; ?>?subject=Your Application to <?php echo str_replace('-', ' ', ucwords($application['application_type'])); ?> Program" 
           class="btn btn-primary">
            <i class="fas fa-reply"></i> Send Email
        </a>
        
        <a href="applications.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Applications
        </a>
        
        <a href="?delete=<?php echo $application['id']; ?>" class="btn btn-danger" 
           onclick="return confirm('Are you sure you want to delete this application? This action cannot be undone.')">
            <i class="fas fa-trash"></i> Delete Application
        </a>
    </div>
</div>

<?php include '../includes/admin-footer.php'; ?>