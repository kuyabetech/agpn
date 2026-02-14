<?php
// ============================================
// SPONSORSHIP SUBMISSIONS MANAGEMENT PAGE - UPDATED
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

// Only superadmin and editor can view sponsorships
if (!hasPermission('editor')) {
    $_SESSION['error'] = 'You do not have permission to view sponsorships';
    redirect('dashboard.php');
}

// Handle status update via AJAX
if (isset($_POST['ajax']) && isset($_POST['id']) && isset($_POST['status'])) {
    $id = (int)$_POST['id'];
    $status = sanitize($_POST['status']);
    
    try {
        $db = db();
        $stmt = $db->prepare("UPDATE sponsorship_submissions SET status = ?, updated_at = NOW(), updated_by = ? WHERE id = ?");
        $stmt->execute([$status, $user['id'], $id]);
        
        // Log the status change
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at) 
            VALUES (?, 'status_update', 'sponsorship', ?, ?, NOW())
        ");
        $stmt->execute([$user['id'], $id, "Status changed to: $status"]);
        
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        exit;
    } catch (PDOException $e) {
        error_log("Error updating sponsorship status: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        $db = db();
        $stmt = $db->prepare("DELETE FROM sponsorship_submissions WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = 'Sponsorship submission deleted successfully';
    } catch (PDOException $e) {
        error_log("Error deleting sponsorship: " . $e->getMessage());
        $_SESSION['error'] = 'Error deleting submission';
    }
    redirect('sponsorships.php');
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['submission_ids'])) {
    $action = $_POST['bulk_action'];
    $submission_ids = explode(',', $_POST['submission_ids']);
    $submission_ids = array_filter(array_map('intval', $submission_ids));
    
    if (!empty($submission_ids) && !empty($action)) {
        try {
            $db = db();
            $placeholders = implode(',', array_fill(0, count($submission_ids), '?'));
            
            if ($action === 'delete') {
                $stmt = $db->prepare("DELETE FROM sponsorship_submissions WHERE id IN ($placeholders)");
                $stmt->execute($submission_ids);
                $_SESSION['success'] = count($submission_ids) . ' submissions deleted successfully';
            } elseif (in_array($action, ['pending', 'reviewed', 'contacted', 'approved', 'declined'])) {
                $stmt = $db->prepare("UPDATE sponsorship_submissions SET status = ?, updated_at = NOW(), updated_by = ? WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$action, $user['id']], $submission_ids));
                $_SESSION['success'] = count($submission_ids) . ' submissions marked as ' . $action;
            }
        } catch (PDOException $e) {
            error_log("Bulk action error: " . $e->getMessage());
            $_SESSION['error'] = 'Error performing bulk action';
        }
    }
    redirect('sponsorships.php');
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$filter_tier = isset($_GET['tier']) ? sanitize($_GET['tier']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

// Build query
$query = "SELECT * FROM sponsorship_submissions WHERE 1=1";
$params = [];

if ($filter_status !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter_status;
}

if ($filter_tier !== 'all') {
    $query .= " AND sponsorship_tier = ?";
    $params[] = $filter_tier;
}

if (!empty($search)) {
    $query .= " AND (organization LIKE ? OR contact_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($date_from)) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY 
    CASE 
        WHEN status = 'pending' THEN 1
        WHEN status = 'reviewed' THEN 2
        WHEN status = 'contacted' THEN 3
        WHEN status = 'approved' THEN 4
        WHEN status = 'declined' THEN 5
        ELSE 6
    END,
    created_at DESC";

// Get submissions
try {
    $db = db();
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics
    $total = $db->query("SELECT COUNT(*) FROM sponsorship_submissions")->fetchColumn() ?: 0;
    $pending = $db->query("SELECT COUNT(*) FROM sponsorship_submissions WHERE status = 'pending'")->fetchColumn() ?: 0;
    $reviewed = $db->query("SELECT COUNT(*) FROM sponsorship_submissions WHERE status = 'reviewed'")->fetchColumn() ?: 0;
    $contacted = $db->query("SELECT COUNT(*) FROM sponsorship_submissions WHERE status = 'contacted'")->fetchColumn() ?: 0;
    $approved = $db->query("SELECT COUNT(*) FROM sponsorship_submissions WHERE status = 'approved'")->fetchColumn() ?: 0;
    $declined = $db->query("SELECT COUNT(*) FROM sponsorship_submissions WHERE status = 'declined'")->fetchColumn() ?: 0;
    
    // Tier statistics
    $tier_stats = $db->query("
        SELECT sponsorship_tier, COUNT(*) as count 
        FROM sponsorship_submissions 
        GROUP BY sponsorship_tier 
        ORDER BY FIELD(sponsorship_tier, 'platinum', 'gold', 'silver', 'bronze', 'custom')
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching sponsorships: " . $e->getMessage());
    $submissions = [];
    $total = $pending = $reviewed = $contacted = $approved = $declined = 0;
    $tier_stats = [];
}

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Set page title
$page_title = 'Sponsorship Submissions';

// Breadcrumbs
$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => '#', 'title' => 'Sponsorships']
];

// Page specific CSS
$page_css = ['sponsorships.css'];

// Include admin header
require_once '../includes/admin-header.php';
?>

<!-- Page Specific Styles -->
<style>
    .sponsorship-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .sponsorship-stat-card {
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
    
    .sponsorship-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .sponsorship-stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }
    
    .sponsorship-stat-info h4 {
        font-size: 14px;
        color: var(--gray-600);
        margin-bottom: 8px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .sponsorship-stat-info .number {
        font-size: 28px;
        font-weight: 700;
        color: var(--navy);
        line-height: 1;
    }
    
    .filter-bar {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 25px;
        border: 1px solid var(--gray-200);
    }
    
    .filter-row {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .filter-group {
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--gray-100);
        padding: 5px 15px;
        border-radius: 8px;
        border: 1px solid var(--gray-200);
    }
    
    .filter-group i {
        color: var(--gold);
    }
    
    .submission-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 20px;
        border: 1px solid var(--gray-200);
        transition: all 0.3s ease;
        position: relative;
    }
    
    .submission-card:hover {
        box-shadow: 0 8px 16px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .submission-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .organization-info h3 {
        margin-bottom: 5px;
        font-size: 20px;
        font-weight: 700;
        color: var(--navy);
    }
    
    .organization-info h3 a {
        color: var(--navy);
        text-decoration: none;
    }
    
    .organization-info h3 a:hover {
        color: var(--gold);
    }
    
    .contact-details {
        display: flex;
        gap: 20px;
        font-size: 13px;
        color: var(--gray-600);
        flex-wrap: wrap;
    }
    
    .contact-details i {
        margin-right: 5px;
        color: var(--gold);
    }
    
    .tier-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 16px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }
    
    .tier-badge.platinum {
        background: linear-gradient(135deg, #e5e4e2, #bcc0c5);
        color: #2c3e50;
    }
    
    .tier-badge.gold {
        background: linear-gradient(135deg, #FFD700, #FFC800);
        color: #6b4f00;
    }
    
    .tier-badge.silver {
        background: linear-gradient(135deg, #C0C0C0, #A0A0A0);
        color: #2c3e50;
    }
    
    .tier-badge.bronze {
        background: linear-gradient(135deg, #CD7F32, #B87333);
        color: white;
    }
    
    .tier-badge.custom {
        background: var(--navy);
        color: white;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 16px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-badge.pending {
        background: rgba(255,193,7,0.1);
        color: #ffc107;
    }
    
    .status-badge.reviewed {
        background: rgba(23,162,184,0.1);
        color: #17a2b8;
    }
    
    .status-badge.contacted {
        background: rgba(111,66,193,0.1);
        color: #6f42c1;
    }
    
    .status-badge.approved {
        background: rgba(40,167,69,0.1);
        color: #28a745;
    }
    
    .status-badge.declined {
        background: rgba(220,53,69,0.1);
        color: #dc3545;
    }
    
    .submission-meta {
        display: flex;
        gap: 20px;
        padding-top: 15px;
        margin-top: 15px;
        border-top: 1px solid var(--gray-200);
        font-size: 12px;
        color: var(--gray-600);
        flex-wrap: wrap;
    }
    
    .submission-actions {
        display: flex;
        gap: 10px;
    }
    
    .btn-sponsor {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    
    .btn-sponsor.primary {
        background: var(--gold);
        color: var(--navy);
        border: 1px solid var(--gold);
    }
    
    .btn-sponsor.primary:hover {
        background: #FFD700;
        transform: translateY(-2px);
    }
    
    .btn-sponsor.secondary {
        background: white;
        color: var(--gray-700);
        border: 1px solid var(--gray-200);
    }
    
    .btn-sponsor.secondary:hover {
        background: var(--gray-100);
        border-color: var(--gray-400);
    }
    
    .btn-sponsor i {
        font-size: 12px;
    }
    
    .status-selector {
        width: 160px;
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid var(--gray-200);
        font-size: 13px;
        color: var(--gray-900);
        background: white;
        cursor: pointer;
    }
    
    .status-selector:focus {
        outline: none;
        border-color: var(--gold);
    }
    
    .tier-stats {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    
    .tier-stat-item {
        background: var(--gray-100);
        padding: 12px 20px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        border: 1px solid var(--gray-200);
    }
    
    .tier-stat-count {
        font-weight: 700;
        color: var(--navy);
        font-size: 18px;
    }
    
    .tier-stat-label {
        color: var(--gray-600);
        font-size: 13px;
        text-transform: capitalize;
    }
    
    .bulk-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    
    .bulk-actions select {
        padding: 10px 15px;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-size: 14px;
        color: var(--gray-900);
        background: white;
        cursor: pointer;
        min-width: 200px;
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
    
    .submission-checkbox {
        position: absolute;
        top: 25px;
        left: 25px;
        width: 18px;
        height: 18px;
        cursor: pointer;
        z-index: 5;
    }
    
    @media (max-width: 768px) {
        .sponsorship-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .submission-header {
            flex-direction: column;
        }
        
        .submission-actions {
            width: 100%;
            justify-content: flex-end;
        }
        
        .filter-row {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-group {
            width: 100%;
        }
    }
    
    @media (max-width: 576px) {
        .sponsorship-stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Flatpickr for date picker -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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
            <i class="fas fa-handshake" style="color: var(--gold);"></i>
            Sponsorship Submissions
        </h1>
        <div style="color: var(--gray-600);">
            <i class="fas fa-tag"></i> 
            Manage sponsorship inquiries and applications
        </div>
    </div>
    
    <div style="display: flex; gap: 15px;">
        <button class="btn-secondary" onclick="exportSubmissions()">
            <i class="fas fa-download"></i> Export
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="sponsorship-stats-grid">
    <div class="sponsorship-stat-card">
        <div class="sponsorship-stat-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-handshake"></i>
        </div>
        <div class="sponsorship-stat-info">
            <h4>Total</h4>
            <span class="number"><?php echo $total; ?></span>
        </div>
    </div>
    
    <div class="sponsorship-stat-card">
        <div class="sponsorship-stat-icon" style="background: rgba(255,193,7,0.1); color: #ffc107;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="sponsorship-stat-info">
            <h4>Pending</h4>
            <span class="number"><?php echo $pending; ?></span>
        </div>
    </div>
    
    <div class="sponsorship-stat-card">
        <div class="sponsorship-stat-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
            <i class="fas fa-search"></i>
        </div>
        <div class="sponsorship-stat-info">
            <h4>Reviewed</h4>
            <span class="number"><?php echo $reviewed; ?></span>
        </div>
    </div>
    
    <div class="sponsorship-stat-card">
        <div class="sponsorship-stat-icon" style="background: rgba(111,66,193,0.1); color: #6f42c1;">
            <i class="fas fa-phone"></i>
        </div>
        <div class="sponsorship-stat-info">
            <h4>Contacted</h4>
            <span class="number"><?php echo $contacted; ?></span>
        </div>
    </div>
    
    <div class="sponsorship-stat-card">
        <div class="sponsorship-stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="sponsorship-stat-info">
            <h4>Approved</h4>
            <span class="number"><?php echo $approved; ?></span>
        </div>
    </div>
    
    <div class="sponsorship-stat-card">
        <div class="sponsorship-stat-icon" style="background: rgba(220,53,69,0.1); color: #dc3545;">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="sponsorship-stat-info">
            <h4>Declined</h4>
            <span class="number"><?php echo $declined; ?></span>
        </div>
    </div>
</div>

<!-- Tier Statistics -->
<?php if (!empty($tier_stats)): ?>
<div style="margin-bottom: 25px;">
    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
        <i class="fas fa-chart-pie" style="color: var(--gold);"></i>
        <span style="font-weight: 600; color: var(--navy);">Distribution by Tier</span>
    </div>
    <div class="tier-stats">
        <?php foreach ($tier_stats as $tier): ?>
            <div class="tier-stat-item">
                <span class="tier-stat-count"><?php echo $tier['count']; ?></span>
                <span class="tier-stat-label"><?php echo ucfirst($tier['sponsorship_tier']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" id="filterForm">
        <div class="filter-row">
            <div style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
                <select name="status" id="statusFilter" class="form-control" style="width: 160px;">
                    <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="reviewed" <?php echo $filter_status == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                    <option value="contacted" <?php echo $filter_status == 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                    <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="declined" <?php echo $filter_status == 'declined' ? 'selected' : ''; ?>>Declined</option>
                </select>
                
                <select name="tier" id="tierFilter" class="form-control" style="width: 160px;">
                    <option value="all" <?php echo $filter_tier == 'all' ? 'selected' : ''; ?>>All Tiers</option>
                    <option value="platinum" <?php echo $filter_tier == 'platinum' ? 'selected' : ''; ?>>Platinum</option>
                    <option value="gold" <?php echo $filter_tier == 'gold' ? 'selected' : ''; ?>>Gold</option>
                    <option value="silver" <?php echo $filter_tier == 'silver' ? 'selected' : ''; ?>>Silver</option>
                    <option value="bronze" <?php echo $filter_tier == 'bronze' ? 'selected' : ''; ?>>Bronze</option>
                    <option value="custom" <?php echo $filter_tier == 'custom' ? 'selected' : ''; ?>>Custom</option>
                </select>
                
                <div class="filter-group">
                    <i class="fas fa-calendar"></i>
                    <input type="text" id="date_from" name="date_from" class="form-control" placeholder="From date" value="<?php echo htmlspecialchars($date_from); ?>" style="width: 120px;">
                </div>
                
                <div class="filter-group">
                    <i class="fas fa-calendar"></i>
                    <input type="text" id="date_to" name="date_to" class="form-control" placeholder="To date" value="<?php echo htmlspecialchars($date_to); ?>" style="width: 120px;">
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <div class="filter-group" style="flex: 1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" id="searchInput" class="form-control" placeholder="Search by organization, contact, or email..." value="<?php echo htmlspecialchars($search); ?>" style="width: 250px;">
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <?php if ($filter_status != 'all' || $filter_tier != 'all' || !empty($search) || !empty($date_from) || !empty($date_to)): ?>
                    <a href="sponsorships.php" class="btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Bulk Actions -->
<div class="bulk-actions">
    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" id="selectAll" style="width: 18px; height: 18px; cursor: pointer;">
            <label for="selectAll" style="font-size: 14px;">Select All</label>
        </div>
        
        <select id="bulkActionSelect" disabled>
            <option value="">Bulk Actions</option>
            <option value="pending">Mark Pending</option>
            <option value="reviewed">Mark Reviewed</option>
            <option value="contacted">Mark Contacted</option>
            <option value="approved">Mark Approved</option>
            <option value="declined">Mark Declined</option>
            <option value="delete">Delete Selected</option>
        </select>
        
        <button id="applyBulkAction" class="btn-apply" disabled>Apply</button>
    </div>
    
    <div style="font-size: 14px; color: var(--gray-600);">
        <i class="fas fa-handshake"></i> Total: <strong><?php echo count($submissions); ?></strong> submissions
    </div>
</div>

<!-- Submissions List -->
<?php if ($submissions): ?>
    <form id="bulkForm" method="POST" action="sponsorships.php">
        <input type="hidden" name="bulk_action" id="bulkActionValue">
        <input type="hidden" name="submission_ids" id="bulkSubmissionIds">
        
        <?php foreach ($submissions as $sub): ?>
            <div class="submission-card" data-id="<?php echo $sub['id']; ?>">
                <input type="checkbox" class="submission-checkbox" value="<?php echo $sub['id']; ?>">
                
                <div class="submission-header">
                    <div class="organization-info">
                        <h3>
                            <a href="view-sponsorship.php?id=<?php echo $sub['id']; ?>">
                                <?php echo htmlspecialchars($sub['organization']); ?>
                            </a>
                        </h3>
                        <div class="contact-details">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($sub['contact_name']); ?></span>
                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($sub['email']); ?></span>
                            <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($sub['phone']); ?></span>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <span class="tier-badge <?php echo $sub['sponsorship_tier']; ?>">
                            <i class="fas fa-<?php 
                                echo $sub['sponsorship_tier'] == 'platinum' ? 'crown' : 
                                    ($sub['sponsorship_tier'] == 'gold' ? 'star' : 
                                    ($sub['sponsorship_tier'] == 'silver' ? 'gem' : 
                                    ($sub['sponsorship_tier'] == 'bronze' ? 'medal' : 'adjust'))); 
                            ?>"></i>
                            <?php echo ucfirst($sub['sponsorship_tier']); ?>
                        </span>
                        
                        <span class="status-badge <?php echo $sub['status'] ?? 'pending'; ?>">
                            <i class="fas fa-<?php 
                                echo $sub['status'] == 'pending' ? 'clock' : 
                                    ($sub['status'] == 'reviewed' ? 'search' : 
                                    ($sub['status'] == 'contacted' ? 'phone' : 
                                    ($sub['status'] == 'approved' ? 'check-circle' : 'times-circle'))); 
                            ?>"></i>
                            <?php echo ucfirst($sub['status'] ?? 'pending'); ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($sub['message'])): ?>
                    <div style="background: var(--gray-100); padding: 15px; border-radius: 12px; margin: 15px 0; font-size: 14px; color: var(--gray-700);">
                        <?php echo nl2br(htmlspecialchars(truncateText($sub['message'], 200))); ?>
                        <?php if (strlen($sub['message']) > 200): ?>
                            <a href="view-sponsorship.php?id=<?php echo $sub['id']; ?>" style="color: var(--gold); margin-left: 5px; text-decoration: none;">
                                Read More
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($sub['budget'])): ?>
                    <div style="margin: 10px 0;">
                        <span style="display: inline-flex; align-items: center; gap: 5px; background: rgba(40,167,69,0.1); padding: 6px 14px; border-radius: 30px; font-size: 13px; color: #28a745;">
                            <i class="fas fa-coins"></i>
                            Estimated Budget: <?php echo htmlspecialchars($sub['budget']); ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <div class="submission-meta">
                    <span><i class="fas fa-calendar-alt"></i> Submitted: <?php echo formatDate($sub['created_at'], 'M d, Y \a\t g:i A'); ?></span>
                    <span><i class="fas fa-clock"></i> <?php echo timeAgo($sub['created_at']); ?></span>
                    <?php if (!empty($sub['event_date'])): ?>
                        <span><i class="fas fa-calendar-check"></i> Event Date: <?php echo formatDate($sub['event_date'], 'M d, Y'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="submission-actions">
                    <select class="status-selector" onchange="updateStatus(<?php echo $sub['id']; ?>, this.value)">
                        <option value="pending" <?php echo ($sub['status'] ?? 'pending') == 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                        <option value="reviewed" <?php echo ($sub['status'] ?? '') == 'reviewed' ? 'selected' : ''; ?>>🔍 Reviewed</option>
                        <option value="contacted" <?php echo ($sub['status'] ?? '') == 'contacted' ? 'selected' : ''; ?>>📞 Contacted</option>
                        <option value="approved" <?php echo ($sub['status'] ?? '') == 'approved' ? 'selected' : ''; ?>>✅ Approved</option>
                        <option value="declined" <?php echo ($sub['status'] ?? '') == 'declined' ? 'selected' : ''; ?>>❌ Declined</option>
                    </select>
                    
                    <a href="view-sponsorship.php?id=<?php echo $sub['id']; ?>" class="btn-sponsor secondary">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    
                    <a href="mailto:<?php echo htmlspecialchars($sub['email']); ?>?subject=Sponsorship%20Inquiry%20-%20<?php echo urlencode($sub['organization']); ?>" class="btn-sponsor primary">
                        <i class="fas fa-reply"></i> Reply
                    </a>
                    
                    <a href="?delete=<?php echo $sub['id']; ?>" class="btn-sponsor secondary" onclick="return confirm('Are you sure you want to delete this sponsorship submission? This action cannot be undone.')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </form>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-handshake"></i>
        <h3>No Sponsorship Inquiries</h3>
        <p>
            <?php if ($filter_status != 'all' || $filter_tier != 'all' || !empty($search) || !empty($date_from) || !empty($date_to)): ?>
                No submissions match your filter criteria.
                <br>
                <a href="sponsorships.php" style="color: var(--gold);">Clear filters</a> to see all submissions.
            <?php else: ?>
                There are no sponsorship submissions yet. When organizations submit sponsorship inquiries, they will appear here.
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<script>
// ============================================
// SPONSORSHIP SUBMISSIONS - JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    console.log('Sponsorship submissions page loaded');
    
    // ============================================
    // INITIALIZE DATE PICKERS
    // ============================================
    if (typeof flatpickr !== 'undefined') {
        flatpickr("#date_from", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });
        
        flatpickr("#date_to", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });
    }
    
    // ============================================
    // SELECT ALL FUNCTIONALITY
    // ============================================
    const selectAll = document.getElementById('selectAll');
    const submissionCheckboxes = document.querySelectorAll('.submission-checkbox');
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const applyButton = document.getElementById('applyBulkAction');
    const bulkActionValue = document.getElementById('bulkActionValue');
    const bulkSubmissionIds = document.getElementById('bulkSubmissionIds');
    
    function updateBulkActionState() {
        const checked = document.querySelectorAll('.submission-checkbox:checked').length;
        if (bulkActionSelect) bulkActionSelect.disabled = checked === 0;
        if (applyButton) applyButton.disabled = checked === 0;
    }
    
    function updateSelectAllState() {
        if (!selectAll) return;
        const checked = document.querySelectorAll('.submission-checkbox:checked').length;
        selectAll.checked = checked === submissionCheckboxes.length && submissionCheckboxes.length > 0;
        selectAll.indeterminate = checked > 0 && checked < submissionCheckboxes.length;
    }
    
    function updateSelectedIds() {
        if (!bulkSubmissionIds) return;
        const checkedIds = [];
        document.querySelectorAll('.submission-checkbox:checked').forEach(cb => {
            checkedIds.push(cb.value);
        });
        bulkSubmissionIds.value = checkedIds.join(',');
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            submissionCheckboxes.forEach(cb => {
                if (cb) cb.checked = this.checked;
            });
            updateBulkActionState();
            updateSelectAllState();
            updateSelectedIds();
        });
    }
    
    submissionCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateBulkActionState();
            updateSelectAllState();
            updateSelectedIds();
        });
    });
    
    // ============================================
    // BULK ACTION APPLY
    // ============================================
    if (applyButton) {
        applyButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const action = bulkActionSelect.value;
            const checkedIds = [];
            
            document.querySelectorAll('.submission-checkbox:checked').forEach(cb => {
                checkedIds.push(cb.value);
            });
            
            if (action && checkedIds.length > 0) {
                let confirmMessage = '';
                if (action === 'delete') {
                    confirmMessage = `Are you sure you want to delete ${checkedIds.length} submission(s)? This action cannot be undone.`;
                } else {
                    confirmMessage = `Are you sure you want to mark ${checkedIds.length} submission(s) as ${action}?`;
                }
                
                if (confirm(confirmMessage)) {
                    bulkActionValue.value = action;
                    bulkSubmissionIds.value = checkedIds.join(',');
                    document.getElementById('bulkForm').submit();
                }
            }
        });
    }
    
    // ============================================
    // STATUS UPDATE FUNCTION
    // ============================================
    window.updateStatus = function(id, status) {
        // Show loading state
        const selector = event.target;
        const originalValue = selector.value;
        selector.disabled = true;
        
        fetch('sponsorships.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ajax=1&id=${id}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update status badge
                const card = selector.closest('.submission-card');
                const statusBadge = card.querySelector('.status-badge');
                
                if (statusBadge) {
                    // Update badge class
                    statusBadge.className = `status-badge ${status}`;
                    
                    // Update badge icon and text
                    const icon = statusBadge.querySelector('i');
                    if (icon) {
                        if (status === 'pending') icon.className = 'fas fa-clock';
                        else if (status === 'reviewed') icon.className = 'fas fa-search';
                        else if (status === 'contacted') icon.className = 'fas fa-phone';
                        else if (status === 'approved') icon.className = 'fas fa-check-circle';
                        else if (status === 'declined') icon.className = 'fas fa-times-circle';
                    }
                    
                    statusBadge.innerHTML = icon.outerHTML + ' ' + status.charAt(0).toUpperCase() + status.slice(1);
                }
                
                // Show success notification
                showNotification('Status updated successfully', 'success');
            } else {
                // Revert selector
                selector.value = originalValue;
                showNotification('Error updating status', 'error');
            }
            
            selector.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            selector.value = originalValue;
            selector.disabled = false;
            showNotification('Error updating status', 'error');
        });
    };
    
    // ============================================
    // EXPORT FUNCTION
    // ============================================
    window.exportSubmissions = function() {
        const status = document.getElementById('statusFilter').value;
        const tier = document.getElementById('tierFilter').value;
        const search = document.getElementById('searchInput').value;
        const date_from = document.getElementById('date_from').value;
        const date_to = document.getElementById('date_to').value;
        
        window.location.href = `export-sponsorships.php?status=${status}&tier=${tier}&search=${encodeURIComponent(search)}&date_from=${date_from}&date_to=${date_to}`;
    };
    
    // ============================================
    // NOTIFICATION FUNCTION
    // ============================================
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = 'toast-notification';
        notification.innerHTML = `
            <div style="background: ${type === 'success' ? '#D4EDDA' : '#F8D7DA'}; color: ${type === 'success' ? '#155724' : '#721C24'}; padding: 15px 25px; border-radius: 10px; border-left: 4px solid ${type === 'success' ? '#28a745' : '#dc3545'}; display: flex; align-items: center; gap: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}" style="font-size: 20px;"></i>
                <span style="flex: 1; font-size: 14px;">${message}</span>
                <button onclick="this.closest('.toast-notification').remove()" style="background: none; border: none; color: ${type === 'success' ? '#155724' : '#721C24'}; cursor: pointer; padding: 5px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + F - Focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.getElementById('searchInput');
            if (searchInput) searchInput.focus();
        }
        
        // Escape - Clear search
        if (e.key === 'Escape') {
            const searchInput = document.getElementById('searchInput');
            if (searchInput && searchInput.value !== '') {
                searchInput.value = '';
                document.getElementById('filterForm').submit();
            }
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
    updateSelectedIds();
    
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