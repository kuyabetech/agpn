<?php
// ============================================
// APPLICATIONS MANAGEMENT - COMPLETE ADMIN
// Features: List, Filter, View, Update Status, Delete
// With Full Sidebar Integration & Responsive Design
// ============================================

define('IN_ADMIN', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAuth();

// Check if user has permission
if (!hasPermission('editor')) {
    $_SESSION['error'] = 'You do not have permission to access applications';
    redirect('dashboard.php');
}

$user = Auth::user();
$page_title = 'Applications Management';

// Handle status update
if (isset($_POST['update_status']) && isset($_POST['application_id']) && isset($_POST['status'])) {
    $app_id = (int)$_POST['application_id'];
    $new_status = sanitize($_POST['status']);
    $valid_statuses = ['pending', 'reviewed', 'contacted', 'accepted', 'rejected'];
    
    if (in_array($new_status, $valid_statuses)) {
        try {
            $stmt = db()->prepare("UPDATE application_submissions SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $app_id]);
            
            $_SESSION['success'] = "Application #{$app_id} status updated to " . ucfirst($new_status);
            
            // Log activity
            error_log("Application {$app_id} status changed to {$new_status} by " . ($user['username'] ?? 'admin'));
        } catch (PDOException $e) {
            error_log("Error updating application status: " . $e->getMessage());
            $_SESSION['error'] = "Error updating application status";
        }
    }
    redirect('applications.php');
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $app_id = (int)$_GET['delete'];
    
    try {
        // Start transaction
        db()->beginTransaction();
        
        // Get CV path and additional files
        $stmt = db()->prepare("SELECT cv_path FROM application_submissions WHERE id = ?");
        $stmt->execute([$app_id]);
        $app = $stmt->fetch();
        
        // Get additional files
        $stmt_files = db()->prepare("SELECT file_path FROM application_files WHERE application_id = ?");
        $stmt_files->execute([$app_id]);
        $files = $stmt_files->fetchAll();
        
        // Delete CV file
        if ($app && $app['cv_path'] && file_exists('../' . $app['cv_path'])) {
            unlink('../' . $app['cv_path']);
        }
        
        // Delete additional files
        foreach ($files as $file) {
            if (file_exists('../' . $file['file_path'])) {
                unlink('../' . $file['file_path']);
            }
        }
        
        // Delete application (cascades to files)
        $stmt_del = db()->prepare("DELETE FROM application_submissions WHERE id = ?");
        $stmt_del->execute([$app_id]);
        
        // Commit transaction
        db()->commit();
        
        $_SESSION['success'] = "Application #{$app_id} deleted successfully";
        
    } catch (PDOException $e) {
        // Rollback on error
        db()->rollBack();
        error_log("Error deleting application: " . $e->getMessage());
        $_SESSION['error'] = "Error deleting application";
    }
    redirect('applications.php');
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['application_ids'])) {
    $action = $_POST['bulk_action'];
    $app_ids = explode(',', $_POST['application_ids']);
    $valid_statuses = ['pending', 'reviewed', 'contacted', 'accepted', 'rejected'];
    
    if (!empty($app_ids) && !empty($action)) {
        try {
            $placeholders = implode(',', array_fill(0, count($app_ids), '?'));
            
            if ($action === 'delete') {
                // Start transaction
                db()->beginTransaction();
                
                // Get all CV paths
                $stmt = db()->prepare("SELECT cv_path FROM application_submissions WHERE id IN ($placeholders)");
                $stmt->execute($app_ids);
                $apps = $stmt->fetchAll();
                
                // Get all additional files
                $stmt_files = db()->prepare("SELECT file_path FROM application_files WHERE application_id IN ($placeholders)");
                $stmt_files->execute($app_ids);
                $files = $stmt_files->fetchAll();
                
                // Delete CV files
                foreach ($apps as $app) {
                    if ($app['cv_path'] && file_exists('../' . $app['cv_path'])) {
                        unlink('../' . $app['cv_path']);
                    }
                }
                
                // Delete additional files
                foreach ($files as $file) {
                    if (file_exists('../' . $file['file_path'])) {
                        unlink('../' . $file['file_path']);
                    }
                }
                
                // Delete records
                $stmt_del = db()->prepare("DELETE FROM application_submissions WHERE id IN ($placeholders)");
                $stmt_del->execute($app_ids);
                
                // Commit transaction
                db()->commit();
                
                $_SESSION['success'] = count($app_ids) . " applications deleted successfully";
                
            } elseif (in_array($action, $valid_statuses)) {
                $stmt = db()->prepare("UPDATE application_submissions SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$action], $app_ids));
                $_SESSION['success'] = count($app_ids) . " applications marked as " . ucfirst($action);
            }
            
        } catch (PDOException $e) {
            db()->rollBack();
            error_log("Bulk action error: " . $e->getMessage());
            $_SESSION['error'] = "Error performing bulk action";
        }
    }
    redirect('applications.php');
}

// Get filter parameters
$filter_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Build count query
$count_query = "SELECT COUNT(*) FROM application_submissions WHERE 1=1";
$query = "SELECT * FROM application_submissions WHERE 1=1";
$params = [];

if ($filter_type !== 'all') {
    $count_query .= " AND application_type = ?";
    $query .= " AND application_type = ?";
    $params[] = $filter_type;
}

if ($filter_status !== 'all') {
    $count_query .= " AND status = ?";
    $query .= " AND status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $count_query .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $query .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_from)) {
    $count_query .= " AND DATE(created_at) >= ?";
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $count_query .= " AND DATE(created_at) <= ?";
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

// Get applications with pagination
try {
    // Get total count
    $stmt_count = db()->prepare($count_query);
    $stmt_count->execute(array_slice($params, 0, count($params) - ($filter_type === 'all' ? 0 : 1)));
    $total_applications = $stmt_count->fetchColumn();
    $total_pages = ceil($total_applications / $per_page);
    
    // Get applications for current page
    $stmt = db()->prepare($query);
    $page_params = array_merge($params, [$per_page, $offset]);
    $stmt->execute($page_params);
    $applications = $stmt->fetchAll();
    
    // Statistics
    $stats = [
        'total' => db()->query("SELECT COUNT(*) FROM application_submissions")->fetchColumn() ?: 0,
        'pending' => db()->query("SELECT COUNT(*) FROM application_submissions WHERE status = 'pending'")->fetchColumn() ?: 0,
        'reviewed' => db()->query("SELECT COUNT(*) FROM application_submissions WHERE status = 'reviewed'")->fetchColumn() ?: 0,
        'contacted' => db()->query("SELECT COUNT(*) FROM application_submissions WHERE status = 'contacted'")->fetchColumn() ?: 0,
        'accepted' => db()->query("SELECT COUNT(*) FROM application_submissions WHERE status = 'accepted'")->fetchColumn() ?: 0,
        'rejected' => db()->query("SELECT COUNT(*) FROM application_submissions WHERE status = 'rejected'")->fetchColumn() ?: 0,
    ];
    
    // Program statistics
    $program_stats = [
        'digital-skills' => db()->query("SELECT COUNT(*) FROM application_submissions WHERE application_type = 'digital-skills'")->fetchColumn() ?: 0,
        'study-abroad' => db()->query("SELECT COUNT(*) FROM application_submissions WHERE application_type = 'study-abroad'")->fetchColumn() ?: 0,
        'business-growth' => db()->query("SELECT COUNT(*) FROM application_submissions WHERE application_type = 'business-growth'")->fetchColumn() ?: 0,
        'sponsorship' => db()->query("SELECT COUNT(*) FROM application_submissions WHERE application_type = 'sponsorship'")->fetchColumn() ?: 0,
    ];
    
} catch (PDOException $e) {
    error_log("Error fetching applications: " . $e->getMessage());
    $applications = [];
    $stats = ['total' => 0, 'pending' => 0, 'reviewed' => 0, 'contacted' => 0, 'accepted' => 0, 'rejected' => 0];
    $program_stats = ['digital-skills' => 0, 'study-abroad' => 0, 'business-growth' => 0, 'sponsorship' => 0];
    $total_pages = 0;
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Include admin header
include '../includes/admin-header.php';
?>

<style>
/* ============================================
   APPLICATIONS MANAGEMENT - CUSTOM STYLES
============================================ */

.applications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 20px rgba(0,0,0,0.05);
    border-color: var(--gold);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-info h4 {
    font-size: 14px;
    color: var(--gray-600);
    margin-bottom: 5px;
    font-weight: 500;
}

.stat-info .number {
    font-size: 28px;
    font-weight: 700;
    color: var(--navy);
    line-height: 1;
}

.filter-bar {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    border: 1px solid var(--gray-200);
}

.filter-form .filter-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-form .form-control {
    min-width: 150px;
    flex: 1;
}

.program-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.program-stat-item {
    background: var(--gray-100);
    border-radius: 12px;
    padding: 15px;
    text-align: center;
}

.program-stat-item .program-name {
    font-size: 13px;
    color: var(--gray-600);
    margin-bottom: 5px;
}

.program-stat-item .program-count {
    font-size: 24px;
    font-weight: 700;
    color: var(--navy);
}

.table-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    overflow: hidden;
    margin-bottom: 30px;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background: #f8fafc;
    padding: 16px 20px;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gray-600);
    border-bottom: 2px solid var(--gray-200);
    white-space: nowrap;
}

.table td {
    padding: 16px 20px;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
}

.table tbody tr:hover {
    background: #fafbfc;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
}

.status-pending {
    background: rgba(255,193,7,0.1);
    color: #856404;
}

.status-reviewed {
    background: rgba(23,162,184,0.1);
    color: #0c5460;
}

.status-contacted {
    background: rgba(0,123,255,0.1);
    color: #004085;
}

.status-accepted {
    background: rgba(40,167,69,0.1);
    color: #155724;
}

.status-rejected {
    background: rgba(220,53,69,0.1);
    color: #721c24;
}

.program-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: rgba(255,184,28,0.1);
    color: var(--navy);
}

.action-group {
    display: flex;
    gap: 8px;
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

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
}

.page-item {
    list-style: none;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 12px;
    border-radius: 8px;
    background: white;
    border: 1px solid var(--gray-200);
    color: var(--gray-700);
    text-decoration: none;
    transition: all 0.2s ease;
}

.page-link:hover,
.page-item.active .page-link {
    background: var(--gold);
    color: var(--navy);
    border-color: var(--gold);
}

.bulk-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.bulk-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* Responsive */
@media (max-width: 1200px) {
    .program-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .filter-form .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-form .form-control {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .stats-cards {
        grid-template-columns: 1fr;
    }
    
    .program-stats {
        grid-template-columns: 1fr;
    }
    
    .table-container {
        overflow-x: auto;
    }
    
    .table {
        min-width: 1000px;
    }
    
    .bulk-actions-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .bulk-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .bulk-actions select,
    .bulk-actions button {
        width: 100%;
    }
}

/* Dark Mode */
body.dark-mode .stat-card,
body.dark-mode .filter-bar,
body.dark-mode .table-container {
    background: var(--navy-light);
    border-color: rgba(255,255,255,0.1);
}

body.dark-mode .stat-info h4,
body.dark-mode .stat-info .number {
    color: var(--white);
}

body.dark-mode .table th {
    background: var(--navy-dark);
    color: var(--gray-300);
    border-bottom-color: rgba(255,255,255,0.1);
}

body.dark-mode .table td {
    color: var(--gray-300);
    border-bottom-color: rgba(255,255,255,0.1);
}

body.dark-mode .table tbody tr:hover {
    background: rgba(255,255,255,0.05);
}

body.dark-mode .program-stat-item {
    background: var(--navy-dark);
}

body.dark-mode .program-stat-item .program-name {
    color: var(--gray-400);
}

body.dark-mode .program-stat-item .program-count {
    color: var(--white);
}

body.dark-mode .page-link {
    background: var(--navy-light);
    border-color: rgba(255,255,255,0.1);
    color: var(--gray-300);
}

body.dark-mode .page-link:hover {
    background: var(--gold);
    color: var(--navy);
}
</style>

<!-- ============================================
     PAGE HEADER
============================================ -->
<div class="applications-header">
    <div class="page-title">
        <h1>Applications Management</h1>
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> / Applications
        </div>
    </div>
    <div class="header-actions">
        <button class="btn-secondary" onclick="exportApplications()">
            <i class="fas fa-download"></i> Export CSV
        </button>
    </div>
</div>

<!-- ============================================
     TOAST NOTIFICATIONS
============================================ -->
<?php if ($success): ?>
    <div class="toast-notification">
        <div style="background: #D4EDDA; color: #155724; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #28a745; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
            <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: #155724; margin-left: auto; cursor: pointer;">×</button>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="toast-notification">
        <div style="background: #F8D7DA; color: #721C24; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #dc3545; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
            <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: #721C24; margin-left: auto; cursor: pointer;">×</button>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================
     STATISTICS CARDS
============================================ -->
<div class="stats-cards">
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-file-signature"></i>
        </div>
        <div class="stat-info">
            <h4>Total Applications</h4>
            <div class="number"><?php echo $stats['total']; ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(255,193,7,0.1); color: #ffc107;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h4>Pending</h4>
            <div class="number"><?php echo $stats['pending']; ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h4>Reviewed</h4>
            <div class="number"><?php echo $stats['reviewed']; ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-info">
            <h4>Accepted</h4>
            <div class="number"><?php echo $stats['accepted']; ?></div>
        </div>
    </div>
</div>

<!-- ============================================
     PROGRAM STATISTICS
============================================ -->
<div class="program-stats">
    <div class="program-stat-item">
        <div class="program-name">Digital Skills</div>
        <div class="program-count"><?php echo $program_stats['digital-skills']; ?></div>
    </div>
    <div class="program-stat-item">
        <div class="program-name">Study Abroad</div>
        <div class="program-count"><?php echo $program_stats['study-abroad']; ?></div>
    </div>
    <div class="program-stat-item">
        <div class="program-name">Business Growth</div>
        <div class="program-count"><?php echo $program_stats['business-growth']; ?></div>
    </div>
    <div class="program-stat-item">
        <div class="program-name">Sponsorship</div>
        <div class="program-count"><?php echo $program_stats['sponsorship']; ?></div>
    </div>
</div>

<!-- ============================================
     FILTER BAR
============================================ -->
<div class="filter-bar">
    <form method="GET" action="" class="filter-form">
        <div class="filter-row">
            <select name="type" class="form-control">
                <option value="all">All Programs</option>
                <option value="digital-skills" <?php echo $filter_type == 'digital-skills' ? 'selected' : ''; ?>>Digital Skills</option>
                <option value="study-abroad" <?php echo $filter_type == 'study-abroad' ? 'selected' : ''; ?>>Study Abroad</option>
                <option value="business-growth" <?php echo $filter_type == 'business-growth' ? 'selected' : ''; ?>>Business Growth</option>
                <option value="sponsorship" <?php echo $filter_type == 'sponsorship' ? 'selected' : ''; ?>>Sponsorship</option>
            </select>
            
            <select name="status" class="form-control">
                <option value="all">All Status</option>
                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="reviewed" <?php echo $filter_status == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                <option value="contacted" <?php echo $filter_status == 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                <option value="accepted" <?php echo $filter_status == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            
            <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone..." value="<?php echo htmlspecialchars($search); ?>">
            
            <input type="date" name="date_from" class="form-control" placeholder="From" value="<?php echo $date_from; ?>">
            <input type="date" name="date_to" class="form-control" placeholder="To" value="<?php echo $date_to; ?>">
            
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="applications.php" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<!-- ============================================
     BULK ACTIONS BAR
============================================ -->
<div class="bulk-actions-bar">
    <div class="bulk-actions">
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" id="selectAll" style="width: 18px; height: 18px; cursor: pointer;">
            <label for="selectAll">Select All</label>
        </div>
        
        <select id="bulkActionSelect" class="form-control" style="width: 200px;" disabled>
            <option value="">Bulk Actions</option>
            <option value="pending">Mark Pending</option>
            <option value="reviewed">Mark Reviewed</option>
            <option value="contacted">Mark Contacted</option>
            <option value="accepted">Mark Accepted</option>
            <option value="rejected">Mark Rejected</option>
            <option value="delete">Delete Selected</option>
        </select>
        
        <button id="applyBulkAction" class="btn btn-apply" disabled>Apply</button>
    </div>
    
    <div class="pagination-info">
        Showing <?php echo count($applications); ?> of <?php echo $total_applications; ?> applications
    </div>
</div>

<!-- ============================================
     APPLICATIONS TABLE
============================================ -->
<div class="table-container">
    <table class="table" id="applicationsTable">
        <thead>
            <tr>
                <th width="40"></th>
                <th>ID</th>
                <th>Applicant</th>
                <th>Program</th>
                <th>Contact</th>
                <th>Country</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($applications): ?>
                <?php foreach ($applications as $app): ?>
                    <tr data-id="<?php echo $app['id']; ?>">
                        <td>
                            <input type="checkbox" class="application-checkbox" value="<?php echo $app['id']; ?>" style="width: 18px; height: 18px; cursor: pointer;">
                        </td>
                        <td><span style="font-weight: 600;">#<?php echo $app['id']; ?></span></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 40px; height: 40px; background: rgba(255,184,28,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--gold);">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                    <div style="font-size: 11px; color: var(--gray-500);">ID: #<?php echo $app['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="program-badge">
                                <i class="fas fa-<?php 
                                    echo $app['application_type'] == 'digital-skills' ? 'laptop-code' : 
                                        ($app['application_type'] == 'study-abroad' ? 'graduation-cap' : 
                                        ($app['application_type'] == 'business-growth' ? 'chart-line' : 'handshake')); 
                                ?>"></i>
                                <?php echo str_replace('-', ' ', ucwords($app['application_type'])); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 13px;"><?php echo htmlspecialchars($app['email']); ?></div>
                            <div style="font-size: 12px; color: var(--gray-600);"><?php echo htmlspecialchars($app['phone']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($app['country']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                <i class="fas fa-<?php 
                                    echo $app['status'] == 'pending' ? 'clock' : 
                                        ($app['status'] == 'reviewed' ? 'check-circle' : 
                                        ($app['status'] == 'contacted' ? 'phone' : 
                                        ($app['status'] == 'accepted' ? 'check-double' : 'times-circle'))); 
                                ?>"></i>
                                <?php echo ucfirst($app['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-weight: 500;"><?php echo formatDate($app['created_at'], 'M d, Y'); ?></div>
                            <div style="font-size: 11px; color: var(--gray-600);"><?php echo timeAgo($app['created_at']); ?></div>
                        </td>
                        <td>
                            <div class="action-group">
                                <a href="view-application.php?id=<?php echo $app['id']; ?>" class="btn-action" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="#" onclick="updateStatus(<?php echo $app['id']; ?>)" class="btn-action" title="Update Status">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?php echo $app['id']; ?>" class="btn-action delete" title="Delete" 
                                   onclick="return confirm('Are you sure you want to delete this application? This action cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 60px 20px;">
                        <i class="fas fa-file-signature" style="font-size: 48px; color: var(--gray-300); margin-bottom: 15px;"></i>
                        <h3 style="margin-bottom: 10px; color: var(--gray-700);">No Applications Found</h3>
                        <p style="color: var(--gray-600);">No applications match your current filters.</p>
                        <a href="applications.php" class="btn btn-primary" style="margin-top: 15px;">Clear Filters</a>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ============================================
     PAGINATION
============================================ -->
<?php if ($total_pages > 1): ?>
    <ul class="pagination">
        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $page - 1; ?>&type=<?php echo $filter_type; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
        </li>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo $filter_type; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>
        
        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $page + 1; ?>&type=<?php echo $filter_type; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        </li>
    </ul>
<?php endif; ?>

<script>
// ============================================
// APPLICATIONS MANAGEMENT - JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ========================================
    // SELECT ALL CHECKBOX FUNCTIONALITY
    // ========================================
    const selectAll = document.getElementById('selectAll');
    const applicationCheckboxes = document.querySelectorAll('.application-checkbox');
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const applyButton = document.getElementById('applyBulkAction');
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            applicationCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionState();
        });
    }
    
    applicationCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllState();
            updateBulkActionState();
        });
    });
    
    function updateSelectAllState() {
        if (!selectAll) return;
        
        const checkedCount = document.querySelectorAll('.application-checkbox:checked').length;
        selectAll.checked = checkedCount === applicationCheckboxes.length && applicationCheckboxes.length > 0;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < applicationCheckboxes.length;
    }
    
    function updateBulkActionState() {
        const checkedCount = document.querySelectorAll('.application-checkbox:checked').length;
        
        if (bulkActionSelect) {
            bulkActionSelect.disabled = checkedCount === 0;
        }
        
        if (applyButton) {
            applyButton.disabled = checkedCount === 0;
        }
    }
    
    // ========================================
    // BULK ACTION APPLY
    // ========================================
    if (applyButton) {
        applyButton.addEventListener('click', function() {
            const action = bulkActionSelect.value;
            const checkedIds = [];
            
            document.querySelectorAll('.application-checkbox:checked').forEach(checkbox => {
                checkedIds.push(checkbox.value);
            });
            
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            if (checkedIds.length === 0) {
                alert('Please select at least one application');
                return;
            }
            
            let confirmMessage = '';
            
            if (action === 'delete') {
                confirmMessage = `Are you sure you want to delete ${checkedIds.length} application(s)? This action cannot be undone.`;
            } else {
                confirmMessage = `Are you sure you want to mark ${checkedIds.length} application(s) as ${action}?`;
            }
            
            if (confirm(confirmMessage)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'applications.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'bulk_action';
                actionInput.value = action;
                
                const idsInput = document.createElement('input');
                idsInput.type = 'hidden';
                idsInput.name = 'application_ids';
                idsInput.value = checkedIds.join(',');
                
                form.appendChild(actionInput);
                form.appendChild(idsInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    
    // ========================================
    // TOAST NOTIFICATIONS AUTO-HIDE
    // ========================================
    setTimeout(() => {
        document.querySelectorAll('.toast-notification').forEach(el => {
            el.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => el.remove(), 300);
        });
    }, 5000);
});

// ========================================
// EXPORT TO CSV
// ========================================
function exportApplications() {
    const table = document.getElementById('applicationsTable');
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        
        cols.forEach((col, index) => {
            // Skip checkbox column
            if (index !== 0) {
                let text = col.textContent.trim().replace(/"/g, '""');
                rowData.push('"' + text + '"');
            }
        });
        
        csv.push(rowData.join(','));
    });
    
    const csvContent = '\uFEFF' + csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'applications_<?php echo date('Y-m-d'); ?>.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// ========================================
// UPDATE STATUS (to be implemented in view page)
// ========================================
function updateStatus(id) {
    window.location.href = 'view-application.php?id=' + id;
}

// ========================================
// ADD TOAST ANIMATION
// ========================================
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
document.head.appendChild(style);
</script>

<?php
// Include admin footer
include '../includes/admin-footer.php';
?>