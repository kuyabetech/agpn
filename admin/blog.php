<?php
// ============================================
// BLOG MANAGEMENT PAGE - CLEAN STABLE VERSION
// ============================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('IN_ADMIN', true);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAuth();
$user = Auth::user();

$db = db();

// --------------------------------------------
// DELETE POST
// --------------------------------------------
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $db->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($post && !empty($post['featured_image'])) {
            $image_path = '../' . $post['featured_image'];
            if (file_exists($image_path) && is_file($image_path)) {
                unlink($image_path);
            }
        }

        $stmt = $db->prepare("DELETE FROM blog_posts WHERE id = ?");
        $stmt->execute([$_GET['delete']]);

        $_SESSION['success'] = "Post deleted successfully";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Delete failed: " . $e->getMessage();
    }

    header("Location: blog.php");
    exit;
}

// --------------------------------------------
// TOGGLE STATUS
// --------------------------------------------
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    try {
        $stmt = $db->prepare("SELECT status FROM blog_posts WHERE id = ?");
        $stmt->execute([$_GET['toggle']]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($post) {
            if ($post['status'] === 'published') {
                $stmt = $db->prepare("
                    UPDATE blog_posts 
                    SET status = 'draft',
                        published_date = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
            } else {
                $stmt = $db->prepare("
                    UPDATE blog_posts 
                    SET status = 'published',
                        published_date = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
            }

            $stmt->execute([$_GET['toggle']]);
            $_SESSION['success'] = "Status updated successfully";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Toggle failed: " . $e->getMessage();
    }

    header("Location: blog.php");
    exit;
}

// --------------------------------------------
// BULK ACTIONS
// --------------------------------------------
if (isset($_POST['bulk_action']) && !empty($_POST['post_ids'])) {

    $action = $_POST['bulk_action'];
    $post_ids = array_filter(array_map('intval', explode(',', $_POST['post_ids'])));

    if (!empty($post_ids)) {
        $placeholders = implode(',', array_fill(0, count($post_ids), '?'));

        try {
            if ($action === 'publish') {
                $stmt = $db->prepare("
                    UPDATE blog_posts 
                    SET status='published',
                        published_date=NOW(),
                        updated_at=NOW()
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($post_ids);
                $_SESSION['success'] = "Posts published successfully";

            } elseif ($action === 'draft') {
                $stmt = $db->prepare("
                    UPDATE blog_posts 
                    SET status='draft',
                        published_date=NULL,
                        updated_at=NOW()
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($post_ids);
                $_SESSION['success'] = "Posts moved to draft";

            } elseif ($action === 'delete') {

                $stmt = $db->prepare("SELECT featured_image FROM blog_posts WHERE id IN ($placeholders)");
                $stmt->execute($post_ids);
                $images = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($images as $img) {
                    if (!empty($img)) {
                        $image_path = '../' . $img;
                        if (file_exists($image_path) && is_file($image_path)) {
                            unlink($image_path);
                        }
                    }
                }

                $stmt = $db->prepare("DELETE FROM blog_posts WHERE id IN ($placeholders)");
                $stmt->execute($post_ids);

                $_SESSION['success'] = "Posts deleted successfully";
            }

        } catch (PDOException $e) {
            $_SESSION['error'] = "Bulk action failed: " . $e->getMessage();
        }
    }

    header("Location: blog.php");
    exit;
}

// --------------------------------------------
// FETCH POSTS (SIMPLE SAFE QUERY)
// --------------------------------------------
try {
    $stmt = $db->query("SELECT * FROM blog_posts ORDER BY id DESC");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// --------------------------------------------
// STATS (SAFE VERSION)
// --------------------------------------------
$total_posts = count($posts);

$published_posts = 0;
$draft_posts = 0;
$total_views = 0;

foreach ($posts as $p) {
    if (isset($p['status']) && $p['status'] === 'published') {
        $published_posts++;
    }
    if (isset($p['status']) && $p['status'] === 'draft') {
        $draft_posts++;
    }
    if (isset($p['views'])) {
        $total_views += (int)$p['views'];
    }
}

$monthly_posts = []; // Disabled temporarily to avoid SQL errors

// --------------------------------------------
// SESSION MESSAGES
// --------------------------------------------
$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$page_title = 'Blog Management';

$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => '#', 'title' => 'Blog Posts']
];

require_once '../includes/admin-header.php';
?>
<!-- Page Specific Styles -->
<style>
    .blog-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    
    .blog-stat-card {
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
    
    .blog-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .blog-stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }
    
    .blog-stat-info h4 {
        font-size: 14px;
        color: var(--gray-600);
        margin-bottom: 8px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .blog-stat-info .number {
        font-size: 32px;
        font-weight: 700;
        color: var(--navy);
        line-height: 1;
    }
    
    .post-status {
        display: inline-flex;
        align-items: center;
        padding: 6px 14px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .post-status.published {
        background: rgba(40,167,69,0.1);
        color: #28a745;
    }
    
    .post-status.draft {
        background: rgba(255,193,7,0.1);
        color: #ffc107;
    }
    
    .featured-image-preview {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        object-fit: cover;
        background: var(--gray-100);
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--gray-200);
    }
    
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
        border: 1px solid var(--gray-200);
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
    
    .table-container {
        background: white;
        border-radius: 16px;
        border: 1px solid var(--gray-200);
        overflow: hidden;
        margin-bottom: 30px;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .table th {
        background: var(--gray-100);
        padding: 16px 20px;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--gray-600);
        border-bottom: 2px solid var(--gray-200);
        text-align: left;
    }
    
    .table td {
        padding: 16px 20px;
        border-bottom: 1px solid var(--gray-200);
        vertical-align: middle;
    }
    
    .table tr:hover {
        background: var(--gray-100);
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
    
    .btn-action.view:hover {
        background: var(--navy);
        color: white;
        border-color: var(--navy);
    }
    
    .chart-container {
        background: white;
        border-radius: 16px;
        padding: 25px;
        border: 1px solid var(--gray-200);
        margin-top: 30px;
    }
    
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
        color: var(--navy);
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: var(--gray-600);
        margin-bottom: 25px;
    }
    
    @media (max-width: 768px) {
        .blog-stats-grid {
            grid-template-columns: 1fr;
        }
        
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
        
        .table-container {
            overflow-x: auto;
        }
        
        .table {
            min-width: 900px;
        }
    }
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<!-- Toast Notifications -->
<?php if ($success): ?>
    <div class="toast-notification" id="successToast">
        <div style="background: #D4EDDA; color: #155724; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #28a745; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-check-circle"></i> 
            <span style="flex: 1;"><?php echo htmlspecialchars($success); ?></span>
            <button onclick="this.closest('.toast-notification').remove()" style="background: none; border: none; color: #155724; cursor: pointer; padding: 5px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="toast-notification" id="errorToast">
        <div style="background: #F8D7DA; color: #721C24; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #dc3545; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-exclamation-circle"></i> 
            <span style="flex: 1;"><?php echo htmlspecialchars($error); ?></span>
            <button onclick="this.closest('.toast-notification').remove()" style="background: none; border: none; color: #721C24; cursor: pointer; padding: 5px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- Blog Stats -->
<div class="blog-stats-grid">
    <div class="blog-stat-card">
        <div class="blog-stat-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-newspaper"></i>
        </div>
        <div class="blog-stat-info">
            <h4>Total Posts</h4>
            <span class="number"><?php echo $total_posts; ?></span>
        </div>
    </div>
    
    <div class="blog-stat-card">
        <div class="blog-stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="blog-stat-info">
            <h4>Published</h4>
            <span class="number"><?php echo $published_posts; ?></span>
        </div>
    </div>
    
    <div class="blog-stat-card">
        <div class="blog-stat-icon" style="background: rgba(255,193,7,0.1); color: #ffc107;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="blog-stat-info">
            <h4>Drafts</h4>
            <span class="number"><?php echo $draft_posts; ?></span>
        </div>
    </div>
    
    <div class="blog-stat-card">
        <div class="blog-stat-icon" style="background: rgba(111,66,193,0.1); color: #6f42c1;">
            <i class="fas fa-eye"></i>
        </div>
        <div class="blog-stat-info">
            <h4>Total Views</h4>
            <span class="number"><?php echo number_format($total_views); ?></span>
        </div>
    </div>
</div>

<!-- Action Bar -->
<div class="action-bar">
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
        <input type="hidden" name="post_ids" id="selectedPostIds">
    </form>
    
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="tableSearch" placeholder="Search posts by title, content, or author...">
    </div>
</div>

<!-- Posts Table -->
<div class="table-container">
    <?php if (!empty($posts)): ?>
        <div style="overflow-x: auto;">
            <table class="table" id="postsTable">
                <thead>
                    <tr>
                        <th width="40">
                            <input type="checkbox" id="selectAll" style="width: 18px; height: 18px; cursor: pointer;">
                        </th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Published</th>
                        <th>Views</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($posts as $post): ?>
                        <tr data-post-id="<?php echo $post['id']; ?>">
                            <td>
                                <input type="checkbox" class="post-checkbox" value="<?php echo $post['id']; ?>" style="width: 18px; height: 18px; cursor: pointer;">
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <?php if (!empty($post['featured_image'])): ?>
                                        <img src="<?php echo SITE_URL . '/' . $post['featured_image']; ?>" 
                                             class="featured-image-preview" 
                                             alt="<?php echo htmlspecialchars($post['title']); ?>"
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="featured-image-preview">
                                            <i class="fas fa-image" style="color: var(--gray-400);"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong style="color: var(--navy);"><?php echo htmlspecialchars($post['title']); ?></strong>
                                        <div style="font-size: 12px; color: var(--gray-600); margin-top: 4px;">
                                            <i class="fas fa-link"></i> 
                                            <a href="<?php echo SITE_URL; ?>/single.php?slug=<?php echo urlencode($post['slug']); ?>" 
                                               target="_blank" 
                                               style="color: var(--gray-600); text-decoration: none;">
                                                <?php echo htmlspecialchars($post['slug']); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($post['author'] ?? 'Admin'); ?>&size=32&background=FFB81C&color=0A1929" 
                                         style="width: 32px; height: 32px; border-radius: 50%;"
                                         alt="<?php echo htmlspecialchars($post['author'] ?? 'Admin'); ?>">
                                    <?php echo htmlspecialchars($post['author'] ?: 'Admin'); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($post['status'] == 'published'): ?>
                                    <span class="post-status published">
                                        <i class="fas fa-check-circle"></i> Published
                                    </span>
                                <?php else: ?>
                                    <span class="post-status draft">
                                        <i class="fas fa-clock"></i> Draft
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($post['published_date'])): ?>
                                    <div style="font-weight: 500;"><?php echo formatDate($post['published_date'], 'M d, Y'); ?></div>
                                    <div style="font-size: 11px; color: var(--gray-500);">
                                        <?php echo formatDate($post['published_date'], 'H:i'); ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--gray-500);">Not published</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="display: flex; align-items: center; gap: 5px; font-weight: 600;">
                                    <i class="fas fa-eye" style="color: var(--gray-600);"></i>
                                    <?php echo number_format($post['views'] ?? 0); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-group">
                                    <a href="edit-post.php?id=<?php echo $post['id']; ?>" class="btn-action" title="Edit Post">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?toggle=<?php echo $post['id']; ?>" class="btn-action" title="<?php echo $post['status'] == 'published' ? 'Unpublish' : 'Publish'; ?>">
                                        <i class="fas fa-<?php echo $post['status'] == 'published' ? 'eye-slash' : 'eye'; ?>"></i>
                                    </a>
                                    <a href="?delete=<?php echo $post['id']; ?>" class="btn-action delete" title="Delete Post" 
                                       onclick="return confirmDelete(event, this, 'post')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <a href="<?php echo SITE_URL; ?>/single.php?slug=<?php echo urlencode($post['slug']); ?>" 
                                       target="_blank" 
                                       class="btn-action view" 
                                       title="View Post">
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
            <i class="fas fa-newspaper"></i>
            <h3>No Blog Posts Yet</h3>
            <p>Start sharing your insights and updates with your audience.</p>
            <a href="add-post.php" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 25px; margin-top: 15px;">
                <i class="fas fa-plus-circle"></i> Create Your First Post
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Monthly Posts Chart -->
<?php if (!empty($monthly_posts)): ?>
<div class="chart-container">
    <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-chart-bar" style="color: var(--gold);"></i>
        Monthly Posts (Last 6 Months)
    </h3>
    <div style="height: 300px; position: relative;">
        <canvas id="monthlyChart"></canvas>
    </div>
</div>
<?php endif; ?>

<script>
// ============================================
// BLOG MANAGEMENT - JAVASCRIPT (FIXED)
// ============================================

// Global confirm delete
window.confirmDelete = function(event, element, type = 'post') {
    event.preventDefault();
    if (confirm('Are you sure you want to delete this ' + type + '? This action cannot be undone.')) {
        window.location.href = element.href;
    }
    return false;
};

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    console.log('Blog management page loaded');
    
    // ============================================
    // SELECT ALL CHECKBOX FUNCTIONALITY
    // ============================================
    const selectAllCheckbox = document.getElementById('selectAll');
    const postCheckboxes = document.querySelectorAll('.post-checkbox');
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const applyButton = document.getElementById('applyBulkAction');
    const selectedPostIds = document.getElementById('selectedPostIds');
    
    // Update bulk action state
    function updateBulkActionState() {
        const checkedCheckboxes = document.querySelectorAll('.post-checkbox:checked');
        const checkedCount = checkedCheckboxes.length;
        
        if (bulkActionSelect) {
            bulkActionSelect.disabled = checkedCount === 0;
        }
        
        if (applyButton) {
            applyButton.disabled = checkedCount === 0;
        }
        
        // Update hidden input with selected IDs
        if (selectedPostIds) {
            const ids = [];
            checkedCheckboxes.forEach(function(cb) {
                ids.push(cb.value);
            });
            selectedPostIds.value = ids.join(',');
        }
    }
    
    // Update select all state
    function updateSelectAllState() {
        if (!selectAllCheckbox) return;
        
        const checkedCheckboxes = document.querySelectorAll('.post-checkbox:checked');
        const allCheckboxes = document.querySelectorAll('.post-checkbox');
        
        if (checkedCheckboxes.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedCheckboxes.length === allCheckboxes.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }
    
    // Select all checkbox event
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function(e) {
            const checkboxes = document.querySelectorAll('.post-checkbox');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = e.target.checked;
            });
            updateBulkActionState();
            updateSelectAllState();
        });
    }
    
    // Individual checkbox events
    if (postCheckboxes.length > 0) {
        postCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                updateBulkActionState();
                updateSelectAllState();
            });
        });
    }
    
    // ============================================
    // BULK ACTION CONFIRMATION
    // ============================================
    const bulkActionForm = document.getElementById('bulkActionForm');
    
    if (bulkActionForm) {
        bulkActionForm.addEventListener('submit', function(e) {
            const action = bulkActionSelect ? bulkActionSelect.value : '';
            const checkedCheckboxes = document.querySelectorAll('.post-checkbox:checked');
            const checkedCount = checkedCheckboxes.length;
            
            if (checkedCount === 0) {
                e.preventDefault();
                alert('Please select at least one post.');
                return;
            }
            
            if (action === 'delete') {
                if (!confirm('Are you sure you want to delete ' + checkedCount + ' post(s)? This action cannot be undone.')) {
                    e.preventDefault();
                }
            } else if (action === 'publish' || action === 'draft') {
                if (!confirm('Are you sure you want to ' + action + ' ' + checkedCount + ' post(s)?')) {
                    e.preventDefault();
                }
            }
        });
    }
    
    // ============================================
    // SEARCH FUNCTIONALITY
    // ============================================
    const searchInput = document.getElementById('tableSearch');
    const tableBody = document.getElementById('tableBody');
    
    function filterTable(searchTerm) {
        if (!tableBody) return;
        
        const rows = tableBody.getElementsByTagName('tr');
        const term = searchTerm.toLowerCase().trim();
        
        Array.from(rows).forEach(function(row) {
            const text = row.textContent.toLowerCase();
            if (term === '') {
                row.style.display = '';
            } else {
                row.style.display = text.includes(term) ? '' : 'none';
            }
        });
    }
    
    if (searchInput) {
        // Add debounce to search
        let searchTimeout;
        searchInput.addEventListener('keyup', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                filterTable(e.target.value);
            }, 300);
        });
        
        // Clear search on escape
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                filterTable('');
            }
        });
    }
    
    // ============================================
    // MONTHLY CHART
    // ============================================
    const chartCanvas = document.getElementById('monthlyChart');
    
    if (chartCanvas && typeof Chart !== 'undefined') {
        <?php if (!empty($monthly_posts)): ?>
        const months = [
            <?php foreach ($monthly_posts as $stat): ?>
                '<?php echo $stat['month'] . ' ' . $stat['year']; ?>',
            <?php endforeach; ?>
        ];
        
        const chartData = [
            <?php foreach ($monthly_posts as $stat): ?>
                <?php echo (int)$stat['total']; ?>,
            <?php endforeach; ?>
        ];
        
        try {
            new Chart(chartCanvas, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Posts Published',
                        data: chartData,
                        backgroundColor: 'rgba(255,184,28,0.2)',
                        borderColor: '#FFB81C',
                        borderWidth: 2,
                        borderRadius: 6,
                        hoverBackgroundColor: 'rgba(255,184,28,0.3)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'white',
                            titleColor: '#0A1929',
                            bodyColor: '#495057',
                            borderColor: '#e9ecef',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            },
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    if (Math.floor(value) === value) {
                                        return value;
                                    }
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            console.log('Chart initialized successfully');
        } catch (error) {
            console.error('Error initializing chart:', error);
        }
        <?php endif; ?>
    }
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + N - New Post
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'edit-post.php';
        }
        
        // Ctrl/Cmd + F - Focus Search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
            }
        }
    });
    
    // ============================================
    // INITIAL STATE
    // ============================================
    updateBulkActionState();
    updateSelectAllState();
    
    // ============================================
    // TOAST NOTIFICATIONS AUTO-HIDE
    // ============================================
    const successToast = document.getElementById('successToast');
    const errorToast = document.getElementById('errorToast');
    
    if (successToast) {
        setTimeout(function() {
            successToast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(function() {
                if (successToast.parentNode) {
                    successToast.remove();
                }
            }, 300);
        }, 5000);
    }
    
    if (errorToast) {
        setTimeout(function() {
            errorToast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(function() {
                if (errorToast.parentNode) {
                    errorToast.remove();
                }
            }, 300);
        }, 5000);
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
    document.head.appendChild(style);
    
}); // End DOMContentLoaded
</script>

<?php
// Include admin footer
require_once '../includes/admin-footer.php';
?>