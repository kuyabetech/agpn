<?php
// ============================================
// FORM SUBMISSIONS MANAGEMENT PAGE
// ============================================

// Define admin constant
define('IN_ADMIN', true);

// ============================================
// REQUIRED FILES
// ============================================
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
Auth::requireAuth();
$user = Auth::user();

// ============================================
// INITIALIZE VARIABLES
// ============================================
$success = null;
$error   = null;

// ============================================
// SINGLE ACTIONS (READ / UNREAD / DELETE)
// ============================================

// Mark as Read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    try {
        $stmt = db()->prepare("
            UPDATE contact_submissions 
            SET is_read = 1, read_at = NOW(), read_by = ? 
            WHERE id = ?
        ");
        $stmt->execute([$user['id'], $_GET['read']]);

        $_SESSION['success'] = "Submission marked as read successfully";
    } catch (PDOException $e) {
        error_log("Mark as read error: " . $e->getMessage());
        $_SESSION['error'] = "Error marking submission as read";
    }

    redirect('forms.php');
}

// Mark as Unread
if (isset($_GET['unread']) && is_numeric($_GET['unread'])) {
    try {
        $stmt = db()->prepare("
            UPDATE contact_submissions 
            SET is_read = 0, read_at = NULL, read_by = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$_GET['unread']]);

        $_SESSION['success'] = "Submission marked as unread successfully";
    } catch (PDOException $e) {
        error_log("Mark as unread error: " . $e->getMessage());
        $_SESSION['error'] = "Error marking submission as unread";
    }

    redirect('forms.php');
}

// Delete Submission
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = db()->prepare("DELETE FROM contact_submissions WHERE id = ?");
        $stmt->execute([$_GET['delete']]);

        $_SESSION['success'] = "Submission deleted successfully";
    } catch (PDOException $e) {
        error_log("Delete submission error: " . $e->getMessage());
        $_SESSION['error'] = "Error deleting submission";
    }

    redirect('forms.php');
}

// ============================================
// BULK ACTIONS
// ============================================
if (isset($_POST['bulk_action']) && !empty($_POST['submission_ids'])) {

    $action = $_POST['bulk_action'];
    $ids    = array_filter(array_map('intval', explode(',', $_POST['submission_ids'])));

    if (!empty($ids)) {

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {

            if ($action === 'read') {

                $stmt = db()->prepare("
                    UPDATE contact_submissions 
                    SET is_read = 1, read_at = NOW(), read_by = ? 
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute(array_merge([$user['id']], $ids));

                $_SESSION['success'] = count($ids) . " submission(s) marked as read";

            } elseif ($action === 'unread') {

                $stmt = db()->prepare("
                    UPDATE contact_submissions 
                    SET is_read = 0, read_at = NULL, read_by = NULL 
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($ids);

                $_SESSION['success'] = count($ids) . " submission(s) marked as unread";

            } elseif ($action === 'delete') {

                $stmt = db()->prepare("
                    DELETE FROM contact_submissions 
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($ids);

                $_SESSION['success'] = count($ids) . " submission(s) deleted successfully";
            }

        } catch (PDOException $e) {
            error_log("Bulk action error: " . $e->getMessage());
            $_SESSION['error'] = "Error performing bulk action";
        }
    }

    redirect('forms.php');
}

// ============================================
// FILTERS & SEARCH
// ============================================
$filter    = $_GET['filter'] ?? 'all';
$search    = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

$query = "
    SELECT cs.*, 
           u.full_name AS reader_name,
           u.avatar AS reader_avatar
    FROM contact_submissions cs
    LEFT JOIN admin_users u ON cs.read_by = u.id
    WHERE 1=1
";

$params = [];

// Status Filter
if ($filter === 'unread') {
    $query .= " AND cs.is_read = 0";
} elseif ($filter === 'read') {
    $query .= " AND cs.is_read = 1";
}

// Search
if (!empty($search)) {
    $query .= " AND (
        cs.full_name LIKE ? OR 
        cs.email LIKE ? OR 
        cs.subject LIKE ? OR 
        cs.message LIKE ? OR 
        cs.phone LIKE ?
    )";

    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

// Date Range
if (!empty($date_from)) {
    $query .= " AND DATE(cs.submitted_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(cs.submitted_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY cs.submitted_at DESC";

// ============================================
// FETCH DATA + STATISTICS
// ============================================
try {

    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $db = db();

    $total_submissions = $db->query("SELECT COUNT(*) FROM contact_submissions")->fetchColumn() ?: 0;
    $unread_count      = $db->query("SELECT COUNT(*) FROM contact_submissions WHERE is_read = 0")->fetchColumn() ?: 0;
    $read_count        = $total_submissions - $unread_count;

    $today_count = $db->query("
        SELECT COUNT(*) FROM contact_submissions 
        WHERE DATE(submitted_at) = CURDATE()
    ")->fetchColumn() ?: 0;

    $week_count = $db->query("
        SELECT COUNT(*) FROM contact_submissions 
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")->fetchColumn() ?: 0;

    $avg_response = $db->query("
        SELECT AVG(TIMESTAMPDIFF(HOUR, submitted_at, read_at)) 
        FROM contact_submissions 
        WHERE is_read = 1 AND read_at IS NOT NULL
    ")->fetchColumn() ?: 0;

} catch (PDOException $e) {

    error_log("Fetch submissions error: " . $e->getMessage());

    $submissions = [];
    $total_submissions = $unread_count = $read_count = $today_count = $week_count = $avg_response = 0;
}

// ============================================
// SESSION MESSAGES
// ============================================
$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error'] ?? null;

unset($_SESSION['success'], $_SESSION['error']);

// ============================================
// PAGE SETTINGS
// ============================================
$page_title = 'Form Submissions';

$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => '#', 'title' => 'Form Submissions']
];

// Include admin header
require_once '../includes/admin-header.php';
?>
<!-- Page Specific Styles -->
<style>
    .submission-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    
    .submission-stat-card {
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
    
    .submission-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .submission-stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }
    
    .submission-stat-info h4 {
        font-size: 14px;
        color: var(--gray-600);
        margin-bottom: 8px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .submission-stat-info .number {
        font-size: 32px;
        font-weight: 700;
        color: var(--navy);
        line-height: 1;
        margin-bottom: 5px;
    }
    
    .submission-stat-info .trend {
        font-size: 12px;
        color: var(--gray-600);
    }
    
    .filter-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        border: 1px solid var(--gray-200);
    }
    
    .filter-tabs {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .filter-tab {
        padding: 8px 20px;
        border-radius: 30px;
        background: white;
        color: var(--gray-600);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .filter-tab i {
        font-size: 12px;
    }
    
    .filter-tab.active {
        background: var(--gold);
        color: var(--navy);
        border-color: var(--gold);
    }
    
    .filter-tab:hover {
        background: var(--gray-100);
    }
    
    .filter-tab.active:hover {
        background: var(--gold-light);
    }
    
    .date-range {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .date-input {
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--gray-100);
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid var(--gray-200);
    }
    
    .date-input i {
        color: var(--gold);
    }
    
    .date-input input {
        border: none;
        background: transparent;
        font-size: 13px;
        width: 120px;
    }
    
    .date-input input:focus {
        outline: none;
    }
    
    .submission-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        transition: all 0.3s ease;
        border: 1px solid var(--gray-200);
        margin-bottom: 20px;
        position: relative;
    }
    
    .submission-card:hover {
        box-shadow: 0 8px 16px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .submission-card.unread {
        background: rgba(255,184,28,0.02);
        border-left: 4px solid var(--gold);
    }
    
    .submission-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .submission-author {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .author-avatar {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: rgba(255,184,28,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gold);
        font-size: 24px;
    }
    
    .author-info h3 {
        margin-bottom: 5px;
        font-size: 18px;
        font-weight: 600;
        color: var(--navy);
    }
    
    .author-info .contact-details {
        display: flex;
        gap: 20px;
        font-size: 13px;
        color: var(--gray-600);
        flex-wrap: wrap;
    }
    
    .author-info .contact-details i {
        margin-right: 5px;
        color: var(--gold);
    }
    
    .submission-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .btn-submission {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gray-600);
        background: white;
        border: 1px solid var(--gray-200);
        transition: all 0.2s ease;
        text-decoration: none;
    }
    
    .btn-submission:hover {
        background: var(--gold);
        color: var(--navy);
        border-color: var(--gold);
        transform: translateY(-2px);
    }
    
    .btn-submission.delete:hover {
        background: #dc3545;
        color: white;
        border-color: #dc3545;
    }
    
    .submission-meta {
        display: flex;
        gap: 20px;
        font-size: 12px;
        color: var(--gray-600);
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--gray-200);
        flex-wrap: wrap;
    }
    
    .submission-meta i {
        margin-right: 5px;
        color: var(--gold);
    }
    
    .submission-subject {
        font-weight: 600;
        color: var(--navy);
        margin-bottom: 10px;
    }
    
    .submission-message {
        background: var(--gray-100);
        padding: 20px;
        border-radius: 12px;
        font-size: 14px;
        line-height: 1.7;
        margin-top: 15px;
    }
    
    .read-receipt {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        background: rgba(40,167,69,0.1);
        color: #28a745;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .bulk-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .bulk-actions select {
        padding: 10px 15px;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-size: 14px;
        color: var(--gray-900);
        background: white;
        cursor: pointer;
        min-width: 180px;
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
        .submission-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .submission-author {
            width: 100%;
        }
        
        .submission-actions {
            width: 100%;
            justify-content: flex-end;
        }
        
        .filter-bar {
            flex-direction: column;
            align-items: stretch;
        }
        
        .date-range {
            width: 100%;
        }
        
        .date-input {
            flex: 1;
        }
        
        .date-input input {
            width: 100%;
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
            <i class="fas fa-inbox" style="color: var(--gold);"></i>
            Form Submissions
        </h1>
        <div style="color: var(--gray-600);">
            <i class="fas fa-envelope"></i> 
            Manage and respond to contact form submissions
        </div>
    </div>
    
    <div style="display: flex; gap: 15px;">
        <button class="btn-secondary" onclick="exportSubmissions()">
            <i class="fas fa-download"></i> Export
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="submission-stats-grid">
    <div class="submission-stat-card">
        <div class="submission-stat-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-envelope"></i>
        </div>
        <div class="submission-stat-info">
            <h4>Total Submissions</h4>
            <span class="number"><?php echo $total_submissions; ?></span>
            <span class="trend">All time</span>
        </div>
    </div>
    
    <div class="submission-stat-card">
        <div class="submission-stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="submission-stat-info">
            <h4>Unread</h4>
            <span class="number"><?php echo $unread_count; ?></span>
            <span class="trend">Need attention</span>
        </div>
    </div>
    
    <div class="submission-stat-card">
        <div class="submission-stat-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="submission-stat-info">
            <h4>Today</h4>
            <span class="number"><?php echo $today_count; ?></span>
            <span class="trend">Last 24 hours</span>
        </div>
    </div>
    
    <div class="submission-stat-card">
        <div class="submission-stat-icon" style="background: rgba(111,66,193,0.1); color: #6f42c1;">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="submission-stat-info">
            <h4>This Week</h4>
            <span class="number"><?php echo $week_count; ?></span>
            <span class="trend">Last 7 days</span>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <div class="filter-tabs">
        <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
            <i class="fas fa-inbox"></i> All (<?php echo $total_submissions; ?>)
        </a>
        <a href="?filter=unread" class="filter-tab <?php echo $filter == 'unread' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Unread (<?php echo $unread_count; ?>)
        </a>
        <a href="?filter=read" class="filter-tab <?php echo $filter == 'read' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i> Read (<?php echo $read_count; ?>)
        </a>
    </div>
    
    <form method="GET" id="filterForm" style="display: flex; gap: 15px; flex-wrap: wrap;">
        <div class="date-range">
            <div class="date-input">
                <i class="fas fa-calendar"></i>
                <input type="text" id="date_from" name="date_from" placeholder="From date" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="date-input">
                <i class="fas fa-calendar"></i>
                <input type="text" id="date_to" name="date_to" placeholder="To date" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
        </div>
        
        <div class="header-search" style="width: 250px;">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search submissions..." value="<?php echo htmlspecialchars($search); ?>">
            <?php if (!empty($search) || !empty($date_from) || !empty($date_to) || $filter != 'all'): ?>
                <a href="forms.php" style="color: var(--gray-600); margin-left: 10px;">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>
        </div>
        
        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
        <button type="submit" class="btn-apply" style="padding: 8px 20px;">Apply</button>
    </form>
</div>

<!-- Bulk Actions -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div class="bulk-actions">
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" id="selectAll" style="width: 18px; height: 18px; cursor: pointer;">
            <label for="selectAll" style="font-size: 14px;">Select All</label>
        </div>
        
        <select id="bulkActionSelect" disabled>
            <option value="">Bulk Actions</option>
            <option value="read">Mark as Read</option>
            <option value="unread">Mark as Unread</option>
            <option value="delete">Delete Selected</option>
        </select>
        
        <button id="applyBulkAction" class="btn-apply" disabled>Apply</button>
    </div>
    
    <div style="font-size: 14px; color: var(--gray-600);">
        <i class="fas fa-envelope"></i> Total: <strong><?php echo count($submissions); ?></strong> submissions
    </div>
</div>

<!-- Submissions List -->
<?php if ($submissions): ?>
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <?php foreach ($submissions as $sub): ?>
            <div class="submission-card <?php echo $sub['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $sub['id']; ?>">
                <input type="checkbox" class="submission-checkbox" value="<?php echo $sub['id']; ?>">
                
                <div class="submission-header">
                    <div class="submission-author">
                        <div class="author-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="author-info">
                            <h3><?php echo htmlspecialchars($sub['full_name']); ?></h3>
                            <div class="contact-details">
                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($sub['email']); ?></span>
                                <?php if (!empty($sub['phone'])): ?>
                                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($sub['phone']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="submission-actions">
                        <?php if ($sub['is_read']): ?>
                            <a href="?unread=<?php echo $sub['id']; ?>" class="btn-submission" title="Mark as Unread">
                                <i class="fas fa-envelope"></i>
                            </a>
                        <?php else: ?>
                            <a href="?read=<?php echo $sub['id']; ?>" class="btn-submission" title="Mark as Read">
                                <i class="fas fa-check-circle"></i>
                            </a>
                        <?php endif; ?>
                        
                        <a href="mailto:<?php echo htmlspecialchars($sub['email']); ?>" class="btn-submission" title="Reply via Email">
                            <i class="fas fa-reply"></i>
                        </a>
                        
                        <a href="?delete=<?php echo $sub['id']; ?>" class="btn-submission delete" title="Delete" 
                           onclick="return confirm('Are you sure you want to delete this submission? This action cannot be undone.')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
                
                <div class="submission-meta">
                    <span><i class="fas fa-calendar-alt"></i> <?php echo formatDate($sub['submitted_at'], 'F j, Y \a\t g:i A'); ?></span>
                    <?php if (!empty($sub['subject'])): ?>
                        <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($sub['subject']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($sub['ip_address'])): ?>
                        <span><i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($sub['ip_address']); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($sub['subject'])): ?>
                    <div class="submission-subject">
                        <i class="fas fa-quote-right"></i> <?php echo htmlspecialchars($sub['subject']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="submission-message">
                    <?php echo nl2br(htmlspecialchars($sub['message'])); ?>
                </div>
                
                <?php if ($sub['is_read'] && !empty($sub['read_at'])): ?>
                    <div style="margin-top: 15px; display: flex; justify-content: flex-end;">
                        <div class="read-receipt">
                            <i class="fas fa-check-circle"></i> 
                            Read <?php echo formatDate($sub['read_at'], 'M d, Y \a\t g:i A'); ?>
                            <?php if (!empty($sub['reader_name'])): ?>
                                by <?php echo htmlspecialchars($sub['reader_name']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No Submissions Found</h3>
        <p><?php echo $search ? 'No results match your search criteria.' : 'No form submissions yet. When visitors submit forms, they will appear here.'; ?></p>
        <?php if ($search || $filter != 'all' || $date_from || $date_to): ?>
            <a href="forms.php" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px; margin-top: 15px;">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// ============================================
// FORM SUBMISSIONS - JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    console.log('Form submissions page loaded');
    
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
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            submissionCheckboxes.forEach(cb => {
                if (cb) cb.checked = this.checked;
            });
            updateBulkActionState();
            updateSelectAllState();
        });
    }
    
    submissionCheckboxes.forEach(cb => {
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
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'forms.php';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'bulk_action';
                    actionInput.value = action;
                    
                    const idsInput = document.createElement('input');
                    idsInput.type = 'hidden';
                    idsInput.name = 'submission_ids';
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
    // EXPORT SUBMISSIONS
    // ============================================
    window.exportSubmissions = function() {
        const filter = '<?php echo $filter; ?>';
        const search = '<?php echo addslashes($search); ?>';
        const date_from = '<?php echo $date_from; ?>';
        const date_to = '<?php echo $date_to; ?>';
        
        window.location.href = `export-submissions.php?filter=${filter}&search=${encodeURIComponent(search)}&date_from=${date_from}&date_to=${date_to}`;
    };
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + F - Focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.querySelector('.header-search input');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Escape - Clear search
        if (e.key === 'Escape') {
            const searchInput = document.querySelector('.header-search input');
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