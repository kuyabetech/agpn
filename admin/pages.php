<?php
// ============================================
// PAGES MANAGEMENT PAGE - FIXED
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

// Initialize variables
$success = null;
$error = null;

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        // Check if page is protected
        $check = db()->prepare("SELECT page_name FROM pages WHERE id = ?");
        $check->execute([$_GET['delete']]);
        $page_to_delete = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($page_to_delete && !in_array($page_to_delete['page_name'], ['home', 'about', 'contact'])) {
            $stmt = db()->prepare("DELETE FROM pages WHERE id = ?");
            $stmt->execute([$_GET['delete']]);
            $_SESSION['success'] = "Page deleted successfully";
            
            // Log activity
            error_log("Page deleted: " . $page_to_delete['page_name'] . " by " . ($user['username'] ?? 'unknown'));
        } else {
            $_SESSION['error'] = "Cannot delete protected page";
        }
    } catch (PDOException $e) {
        error_log("Error deleting page: " . $e->getMessage());
        $_SESSION['error'] = "Cannot delete this page. It may have dependencies.";
    }
    redirect('pages.php');
}

// Handle duplicate page
if (isset($_GET['duplicate']) && is_numeric($_GET['duplicate'])) {
    try {
        $stmt = db()->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$_GET['duplicate']]);
        $original = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($original) {
            $new_name = $original['page_name'] . '-copy-' . date('Ymd');
            $new_title = $original['page_title'] . ' (Copy)';
            
            // Check if name exists
            $check = db()->prepare("SELECT id FROM pages WHERE page_name = ?");
            $check->execute([$new_name]);
            
            if (!$check->fetch()) {
                $insert = db()->prepare("
                    INSERT INTO pages (page_name, page_title, page_content, meta_description, meta_keywords, status, last_edited_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'draft', ?, NOW(), NOW())
                ");
                $insert->execute([
                    $new_name,
                    $new_title,
                    $original['page_content'],
                    $original['meta_description'],
                    $original['meta_keywords'],
                    $_SESSION['admin_user_id'] ?? $user['id']
                ]);
                $_SESSION['success'] = "Page duplicated successfully";
            } else {
                $_SESSION['error'] = "A page with this name already exists";
            }
        }
    } catch (PDOException $e) {
        error_log("Error duplicating page: " . $e->getMessage());
        $_SESSION['error'] = "Error duplicating page";
    }
    redirect('pages.php');
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['page_ids'])) {
    $action = $_POST['bulk_action'];
    $page_ids = explode(',', $_POST['page_ids']);
    $page_ids = array_filter(array_map('intval', $page_ids));
    
    if (!empty($page_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($page_ids), '?'));
            
            if ($action === 'publish') {
                $stmt = db()->prepare("UPDATE pages SET status = 'published', updated_at = NOW(), last_edited_by = ? WHERE id IN ($placeholders)");
                $params = array_merge([$_SESSION['admin_user_id'] ?? $user['id']], $page_ids);
                $stmt->execute($params);
                $_SESSION['success'] = count($page_ids) . " pages published successfully";
            } elseif ($action === 'draft') {
                $stmt = db()->prepare("UPDATE pages SET status = 'draft', updated_at = NOW(), last_edited_by = ? WHERE id IN ($placeholders)");
                $params = array_merge([$_SESSION['admin_user_id'] ?? $user['id']], $page_ids);
                $stmt->execute($params);
                $_SESSION['success'] = count($page_ids) . " pages moved to draft";
            } elseif ($action === 'delete') {
                // Filter out protected pages
                $protected = ['home', 'about', 'contact'];
                $filtered_ids = [];
                
                foreach ($page_ids as $id) {
                    $check = db()->prepare("SELECT page_name FROM pages WHERE id = ?");
                    $check->execute([$id]);
                    $page = $check->fetch(PDO::FETCH_ASSOC);
                    if ($page && !in_array($page['page_name'], $protected)) {
                        $filtered_ids[] = $id;
                    }
                }
                
                if (!empty($filtered_ids)) {
                    $placeholders = implode(',', array_fill(0, count($filtered_ids), '?'));
                    $stmt = db()->prepare("DELETE FROM pages WHERE id IN ($placeholders)");
                    $stmt->execute($filtered_ids);
                    $_SESSION['success'] = count($filtered_ids) . " pages deleted successfully";
                } else {
                    $_SESSION['error'] = "Cannot delete protected pages";
                }
            }
        } catch (PDOException $e) {
            error_log("Bulk action error: " . $e->getMessage());
            $_SESSION['error'] = "Error performing bulk action";
        }
        redirect('pages.php');
    }
}

// Get all pages with additional stats
try {
    $db = db();
    
    // Get pages with editor info
    $stmt = $db->query("
        SELECT p.*, 
               u.full_name as editor_name,
               u.role as editor_role,
               u.avatar as editor_avatar
        FROM pages p
        LEFT JOIN admin_users u ON p.last_edited_by = u.id
        ORDER BY 
            CASE 
                WHEN p.page_name IN ('home', 'about', 'contact') THEN 1
                ELSE 2
            END,
            p.page_name ASC
    ");
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $total_pages = count($pages);
    $published_pages = $db->query("SELECT COUNT(*) FROM pages WHERE status = 'published'")->fetchColumn() ?: 0;
    $draft_pages = $db->query("SELECT COUNT(*) FROM pages WHERE status = 'draft'")->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    error_log("Error fetching pages: " . $e->getMessage());
    $pages = [];
    $total_pages = $published_pages = $draft_pages = 0;
}

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Set page title
$page_title = 'Manage Pages';

// Breadcrumbs
$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => '#', 'title' => 'Pages']
];

// Include admin header
require_once '../includes/admin-header.php';
?>

<!-- Page Specific Styles -->
<style>
    /* Stats Cards - Responsive Grid */
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card-mini {
        background: white;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        transition: all 0.3s ease;
        border: 1px solid var(--gray-200);
    }
    
    .stat-card-mini:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .stat-icon-mini {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }
    
    .stat-info-mini h4 {
        font-size: 14px;
        color: var(--gray-600);
        margin-bottom: 5px;
        font-weight: 500;
    }
    
    .stat-info-mini .number {
        font-size: 28px;
        font-weight: 700;
        color: var(--navy);
        line-height: 1;
    }
    
    .stat-info-mini .trend {
        font-size: 12px;
        color: #28a745;
        margin-left: 8px;
    }
    
    /* Action Bar - Responsive */
    .action-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
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
    
    .search-box {
        display: flex;
        align-items: center;
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        padding: 0 15px;
        width: 300px;
    }
    
    .search-box i {
        color: var(--gray-600);
    }
    
    .search-box input {
        border: none;
        padding: 12px 10px;
        width: 100%;
        font-size: 14px;
        background: transparent;
    }
    
    .search-box input:focus {
        outline: none;
    }
    
    /* Table Container - Responsive */
    .table-container {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        overflow: hidden;
        position: relative;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }
    
    .table thead th {
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
    
    .table tbody td {
        padding: 16px 20px;
        vertical-align: middle;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .table tbody tr {
        transition: all 0.2s ease;
    }
    
    .table tbody tr:hover {
        background: #fafbfc;
    }
    
    .table tbody tr.selected {
        background: rgba(255,184,28,0.05);
    }
    
    .table tbody tr.protected-row {
        background: rgba(108,117,125,0.02);
    }
    
    /* Page Badges */
    .page-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
    }
    
    .page-badge i {
        margin-right: 5px;
    }
    
    .page-badge.published {
        background: rgba(40,167,69,0.1);
        color: #28a745;
    }
    
    .page-badge.draft {
        background: rgba(255,193,7,0.1);
        color: #ffc107;
    }
    
    .page-name {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .page-name i {
        color: var(--gold);
        font-size: 16px;
    }
    
    .page-name code {
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 12px;
        color: var(--navy);
    }
    
    .protected-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        background: rgba(108,117,125,0.1);
        color: var(--gray-600);
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
        white-space: nowrap;
    }
    
    .protected-badge i {
        margin-right: 4px;
    }
    
    /* Action Group */
    .action-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
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
    
    .btn-action.view:hover {
        background: var(--navy);
        color: white;
        border-color: var(--navy);
    }
    
    /* Page Info Footer */
    .page-info-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding: 15px 20px;
        background: white;
        border-radius: 12px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .page-stats {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .page-actions {
        display: flex;
        gap: 10px;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 64px;
        color: var(--gray-300);
        margin-bottom: 20px;
    }
    
    .empty-state h3 {
        color: var(--gray-900);
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: var(--gray-600);
        margin-bottom: 25px;
    }
    
    /* Editor Avatar */
    .editor-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .editor-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        object-fit: cover;
    }
    
    /* Responsive */
    @media (max-width: 1199px) {
        .stats-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 991px) {
        .action-bar {
            flex-direction: column;
            align-items: stretch;
        }
        
        .bulk-actions {
            width: 100%;
        }
        
        .bulk-actions select {
            flex: 1;
        }
        
        .search-box {
            width: 100%;
        }
    }
    
    @media (max-width: 768px) {
        .stats-cards {
            grid-template-columns: 1fr;
        }
        
        .page-info-footer {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .page-stats {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            width: 100%;
        }
        
        .page-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
    
    @media (max-width: 576px) {
        .stat-card-mini {
            padding: 15px;
        }
        
        .stat-icon-mini {
            width: 45px;
            height: 45px;
            font-size: 20px;
        }
        
        .stat-info-mini .number {
            font-size: 24px;
        }
        
        .action-bar {
            padding: 12px 15px;
        }
        
        .bulk-actions {
            flex-direction: column;
            width: 100%;
        }
        
        .bulk-actions select {
            width: 100%;
        }
        
        .btn-apply {
            width: 100%;
        }
        
        .page-name {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .protected-badge {
            margin-left: 0;
            margin-top: 5px;
        }
    }
</style>

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

<!-- Stats Cards -->
<div class="stats-cards animate__animated animate__fadeIn">
    <div class="stat-card-mini">
        <div class="stat-icon-mini" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-info-mini">
            <h4>Total Pages</h4>
            <div>
                <span class="number"><?php echo $total_pages; ?></span>
            </div>
        </div>
    </div>
    
    <div class="stat-card-mini">
        <div class="stat-icon-mini" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info-mini">
            <h4>Published</h4>
            <div>
                <span class="number"><?php echo $published_pages; ?></span>
                <span class="trend"><?php echo $total_pages > 0 ? round(($published_pages/$total_pages)*100) : 0; ?>%</span>
            </div>
        </div>
    </div>
    
    <div class="stat-card-mini">
        <div class="stat-icon-mini" style="background: rgba(255,193,7,0.1); color: #ffc107;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info-mini">
            <h4>Drafts</h4>
            <div>
                <span class="number"><?php echo $draft_pages; ?></span>
            </div>
        </div>
    </div>
    
    <div class="stat-card-mini">
        <div class="stat-icon-mini" style="background: rgba(108,117,125,0.1); color: var(--gray-600);">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="stat-info-mini">
            <h4>Protected</h4>
            <div>
                <span class="number">3</span>
            </div>
        </div>
    </div>
</div>

<!-- Action Bar -->
<div class="action-bar animate__animated animate__fadeIn">
    <form method="POST" id="bulkActionForm" style="display: flex; gap: 10px; align-items: center; flex: 1; flex-wrap: wrap;">
        <div class="bulk-actions">
            <select name="bulk_action" id="bulkActionSelect" disabled>
                <option value="">Bulk Actions</option>
                <option value="publish">Publish</option>
                <option value="draft">Move to Draft</option>
                <option value="delete">Delete</option>
            </select>
            <button type="submit" class="btn-apply" id="applyBulkAction" disabled>Apply</button>
        </div>
        <input type="hidden" name="page_ids" id="selectedPageIds">
    </form>
    
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="tableSearch" placeholder="Search by title, name, or content..." aria-label="Search pages">
    </div>
</div>

<!-- Pages Table -->
<div class="table-container animate__animated animate__fadeIn">
    <?php if ($pages): ?>
        <div class="table-responsive">
            <table class="table" id="pagesTable">
                <thead>
                    <tr>
                        <th width="40">
                            <input type="checkbox" id="selectAll" style="width: 18px; height: 18px; cursor: pointer;" aria-label="Select all pages">
                        </th>
                        <th>ID</th>
                        <th>Page Name</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Last Editor</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <?php 
                            $is_protected = in_array($page['page_name'], ['home', 'about', 'contact']);
                            $row_class = $is_protected ? 'protected-row' : '';
                        ?>
                        <tr class="<?php echo $row_class; ?>" data-page-id="<?php echo $page['id']; ?>">
                            <td>
                                <?php if (!$is_protected): ?>
                                    <input type="checkbox" class="page-checkbox" value="<?php echo $page['id']; ?>" style="width: 18px; height: 18px; cursor: pointer;" aria-label="Select page">
                                <?php endif; ?>
                            </td>
                            <td><span style="font-weight: 600;">#<?php echo $page['id']; ?></span></td>
                            <td>
                                <div class="page-name">
                                    <i class="fas fa-file-alt"></i>
                                    <code><?php echo htmlspecialchars($page['page_name']); ?></code>
                                    <?php if ($is_protected): ?>
                                        <span class="protected-badge">
                                            <i class="fas fa-shield-alt"></i> Protected
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500; color: var(--gray-900);">
                                    <?php echo htmlspecialchars($page['page_title']); ?>
                                </div>
                                <?php if (!empty($page['meta_description'])): ?>
                                    <small style="color: var(--gray-600); display: block; margin-top: 4px;">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars(truncateText($page['meta_description'], 50)); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($page['status'] == 'published'): ?>
                                    <span class="page-badge published">
                                        <i class="fas fa-check-circle"></i> Published
                                    </span>
                                <?php else: ?>
                                    <span class="page-badge draft">
                                        <i class="fas fa-clock"></i> Draft
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo formatDate($page['updated_at'], 'M d, Y'); ?></div>
                                <small style="color: var(--gray-600);"><?php echo formatDate($page['updated_at'], 'H:i'); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($page['editor_name'])): ?>
                                    <div class="editor-info">
                                        <?php if (!empty($page['editor_avatar'])): ?>
                                            <img src="<?php echo htmlspecialchars($page['editor_avatar']); ?>" 
                                                 class="editor-avatar" 
                                                 alt="<?php echo htmlspecialchars($page['editor_name']); ?>"
                                                 loading="lazy">
                                        <?php else: ?>
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($page['editor_name']); ?>&size=24&background=FFB81C&color=0A1929" 
                                                 class="editor-avatar" 
                                                 alt="<?php echo htmlspecialchars($page['editor_name']); ?>"
                                                 loading="lazy">
                                        <?php endif; ?>
                                        <span style="font-size: 14px;"><?php echo htmlspecialchars($page['editor_name']); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--gray-500);">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-group">
                                    <a href="edit-page.php?id=<?php echo $page['id']; ?>" class="btn-action" title="Edit Page" aria-label="Edit page">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if (!$is_protected): ?>
                                        <a href="?duplicate=<?php echo $page['id']; ?>" class="btn-action" title="Duplicate Page" onclick="return confirm('Create a copy of this page?')" aria-label="Duplicate page">
                                            <i class="fas fa-copy"></i>
                                        </a>
                                        <a href="?delete=<?php echo $page['id']; ?>" class="btn-action delete" title="Delete Page" onclick="return confirm('Are you sure you want to delete this page? This action cannot be undone.')" aria-label="Delete page">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo SITE_URL; ?>/<?php echo $page['page_name'] == 'home' ? '' : $page['page_name'] . '.php'; ?>" 
                                       target="_blank" 
                                       class="btn-action view" 
                                       title="View Page" 
                                       aria-label="View page">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h3>No Pages Found</h3>
            <p>Get started by creating your first page.</p>
            <a href="edit-page.php" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 25px;">
                <i class="fas fa-plus-circle"></i> Create New Page
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Page Info Footer -->
<?php if ($pages): ?>
<div class="page-info-footer animate__animated animate__fadeIn">
    <div class="page-stats">
        <span style="color: var(--gray-600); display: flex; align-items: center; gap: 5px;">
            <i class="fas fa-database"></i> Total: <strong><?php echo $total_pages; ?></strong> pages
        </span>
        <span style="color: var(--gray-600); display: flex; align-items: center; gap: 5px;">
            <i class="fas fa-check-circle" style="color: #28a745;"></i> Published: <strong><?php echo $published_pages; ?></strong>
        </span>
        <span style="color: var(--gray-600); display: flex; align-items: center; gap: 5px;">
            <i class="fas fa-clock" style="color: #ffc107;"></i> Draft: <strong><?php echo $draft_pages; ?></strong>
        </span>
    </div>
    
    <div class="page-actions">
        <button onclick="exportTableToCSV()" class="btn-action" title="Export to CSV" aria-label="Export to CSV">
            <i class="fas fa-download"></i>
        </button>
        <button onclick="window.location.reload()" class="btn-action" title="Refresh" aria-label="Refresh page">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>
</div>
<?php endif; ?>

<script>
    // ============================================
    // PAGES MANAGEMENT - JAVASCRIPT
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';
        
        // --------------------------------------------------------
        // 1. SELECT ALL CHECKBOX FUNCTIONALITY
        // --------------------------------------------------------
        const selectAllCheckbox = document.getElementById('selectAll');
        const pageCheckboxes = document.querySelectorAll('.page-checkbox');
        const bulkActionSelect = document.getElementById('bulkActionSelect');
        const applyButton = document.getElementById('applyBulkAction');
        const selectedPageIds = document.getElementById('selectedPageIds');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                pageCheckboxes.forEach(checkbox => {
                    if (checkbox) checkbox.checked = this.checked;
                });
                updateBulkActionState();
                updateSelectedIds();
                highlightSelectedRows();
            });
        }
        
        pageCheckboxes.forEach(checkbox => {
            if (checkbox) {
                checkbox.addEventListener('change', function() {
                    updateSelectAllState();
                    updateBulkActionState();
                    updateSelectedIds();
                    highlightSelectedRows();
                });
            }
        });
        
        function updateSelectAllState() {
            if (!selectAllCheckbox) return;
            
            const checkedCount = document.querySelectorAll('.page-checkbox:checked').length;
            selectAllCheckbox.checked = checkedCount === pageCheckboxes.length && pageCheckboxes.length > 0;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < pageCheckboxes.length;
        }
        
        function updateBulkActionState() {
            const checkedCount = document.querySelectorAll('.page-checkbox:checked').length;
            
            if (bulkActionSelect) {
                bulkActionSelect.disabled = checkedCount === 0;
            }
            
            if (applyButton) {
                applyButton.disabled = checkedCount === 0;
            }
        }
        
        function updateSelectedIds() {
            if (!selectedPageIds) return;
            
            const checkedIds = [];
            document.querySelectorAll('.page-checkbox:checked').forEach(checkbox => {
                checkedIds.push(checkbox.value);
            });
            
            selectedPageIds.value = checkedIds.join(',');
        }
        
        function highlightSelectedRows() {
            document.querySelectorAll('#pagesTable tbody tr').forEach(row => {
                const checkbox = row.querySelector('.page-checkbox');
                if (checkbox && checkbox.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
        }
        
        // --------------------------------------------------------
        // 2. SEARCH FUNCTIONALITY
        // --------------------------------------------------------
        const searchInput = document.getElementById('tableSearch');
        const table = document.getElementById('pagesTable');
        
        function filterTable(searchTerm) {
            if (!table) return;
            
            const tbody = table.getElementsByTagName('tbody')[0];
            if (!tbody) return;
            
            const rows = tbody.getElementsByTagName('tr');
            const term = searchTerm.toLowerCase();
            
            Array.from(rows).forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        }
        
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                filterTable(this.value);
            });
        }
        
        // --------------------------------------------------------
        // 3. BULK ACTION CONFIRMATION
        // --------------------------------------------------------
        const bulkActionForm = document.getElementById('bulkActionForm');
        
        if (bulkActionForm) {
            bulkActionForm.addEventListener('submit', function(e) {
                const action = document.getElementById('bulkActionSelect').value;
                const checkedCount = document.querySelectorAll('.page-checkbox:checked').length;
                
                if (checkedCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one page.');
                    return;
                }
                
                if (action === 'delete') {
                    if (!confirm(`Are you sure you want to delete ${checkedCount} page(s)? Protected pages will be skipped.`)) {
                        e.preventDefault();
                    }
                } else if (action === 'publish' || action === 'draft') {
                    if (!confirm(`Are you sure you want to ${action} ${checkedCount} page(s)?`)) {
                        e.preventDefault();
                    }
                }
            });
        }
        
        // --------------------------------------------------------
        // 4. ROW CLICK SELECTION
        // --------------------------------------------------------
        const rows = document.querySelectorAll('#pagesTable tbody tr');
        rows.forEach(row => {
            row.addEventListener('click', function(e) {
                // Don't toggle if clicking on checkbox, action buttons, or links
                if (e.target.type === 'checkbox' || 
                    e.target.closest('.btn-action') || 
                    e.target.tagName === 'A' ||
                    e.target.tagName === 'I' && e.target.closest('a')) {
                    return;
                }
                
                const checkbox = this.querySelector('.page-checkbox');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    updateSelectAllState();
                    updateBulkActionState();
                    updateSelectedIds();
                    
                    if (checkbox.checked) {
                        this.classList.add('selected');
                    } else {
                        this.classList.remove('selected');
                    }
                }
            });
        });
        
        // --------------------------------------------------------
        // 5. EXPORT TO CSV
        // --------------------------------------------------------
        window.exportTableToCSV = function() {
            const table = document.getElementById('pagesTable');
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
            
            const csvContent = csv.join('\n');
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'agpn_pages_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        };
        
        // --------------------------------------------------------
        // 6. KEYBOARD SHORTCUTS
        // --------------------------------------------------------
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N - New Page
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'edit-page.php';
            }
            
            // Ctrl/Cmd + F - Focus Search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.getElementById('tableSearch');
                if (searchInput) {
                    searchInput.focus();
                }
            }
            
            // Escape - Clear Search
            if (e.key === 'Escape') {
                const searchInput = document.getElementById('tableSearch');
                if (searchInput && searchInput.value !== '') {
                    searchInput.value = '';
                    filterTable('');
                }
            }
        });
        
        // --------------------------------------------------------
        // 7. INITIAL STATE
        // --------------------------------------------------------
        updateSelectAllState();
        updateBulkActionState();
        highlightSelectedRows();
        
        // --------------------------------------------------------
        // 8. AUTO-HIDE TOAST NOTIFICATIONS
        // --------------------------------------------------------
        setTimeout(() => {
            document.querySelectorAll('.toast-notification').forEach(el => {
                el.style.animation = 'fadeOutRight 0.3s ease';
                setTimeout(() => el.remove(), 300);
            });
        }, 5000);
        
    }); // End DOMContentLoaded
</script>

<?php
// Include admin footer
require_once '../includes/admin-footer.php';
?>