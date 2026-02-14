<?php
// ============================================
// TESTIMONIALS MANAGEMENT - CLEAN STABLE VERSION
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
// DELETE TESTIMONIAL
// --------------------------------------------
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {

    try {
        // Get photo first
        $stmt = $db->prepare("SELECT client_photo FROM testimonials WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);

        // Delete record
        $stmt = $db->prepare("DELETE FROM testimonials WHERE id = ?");
        $stmt->execute([$_GET['delete']]);

        // Delete photo safely
        if ($testimonial && !empty($testimonial['client_photo'])) {
            $photo_path = '../' . $testimonial['client_photo'];
            if (file_exists($photo_path) && is_file($photo_path)) {
                unlink($photo_path);
            }
        }

        $_SESSION['success'] = "Testimonial deleted successfully";

    } catch (PDOException $e) {
        $_SESSION['error'] = "Delete failed: " . $e->getMessage();
    }

    header("Location: testimonials.php");
    exit;
}

// --------------------------------------------
// TOGGLE STATUS (SAFE VERSION)
// --------------------------------------------
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {

    try {
        $stmt = $db->prepare("SELECT status FROM testimonials WHERE id = ?");
        $stmt->execute([$_GET['toggle']]);
        $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($testimonial) {

            $new_status = ($testimonial['status'] === 'active') ? 'inactive' : 'active';

            $stmt = $db->prepare("
                UPDATE testimonials
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $_GET['toggle']]);

            $_SESSION['success'] = "Status updated successfully";
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = "Toggle failed: " . $e->getMessage();
    }

    header("Location: testimonials.php");
    exit;
}

// --------------------------------------------
// BULK ACTIONS
// --------------------------------------------
if (isset($_POST['bulk_action']) && !empty($_POST['selected'])) {

    $ids = array_map('intval', $_POST['selected']);
    $ids = array_filter($ids);

    if (!empty($ids)) {

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {

            if ($_POST['bulk_action'] === 'activate') {

                $stmt = $db->prepare("
                    UPDATE testimonials
                    SET status = 'active', updated_at = NOW()
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($ids);
                $_SESSION['success'] = "Selected testimonials activated";

            } elseif ($_POST['bulk_action'] === 'deactivate') {

                $stmt = $db->prepare("
                    UPDATE testimonials
                    SET status = 'inactive', updated_at = NOW()
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($ids);
                $_SESSION['success'] = "Selected testimonials deactivated";

            } elseif ($_POST['bulk_action'] === 'delete') {

                // Get photos first
                $stmt = $db->prepare("
                    SELECT client_photo FROM testimonials
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($ids);
                $photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($photos as $photo) {
                    if (!empty($photo)) {
                        $photo_path = '../' . $photo;
                        if (file_exists($photo_path) && is_file($photo_path)) {
                            unlink($photo_path);
                        }
                    }
                }

                $stmt = $db->prepare("
                    DELETE FROM testimonials
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($ids);

                $_SESSION['success'] = "Selected testimonials deleted";
            }

        } catch (PDOException $e) {
            $_SESSION['error'] = "Bulk action failed: " . $e->getMessage();
        }
    }

    header("Location: testimonials.php");
    exit;
}

// --------------------------------------------
// FETCH TESTIMONIALS (SAFE SIMPLE QUERY)
// --------------------------------------------
try {

    $stmt = $db->query("
        SELECT * FROM testimonials
        ORDER BY id DESC
    ");

    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Database Error: " . $e->getMessage());
}

// --------------------------------------------
// SAFE STATISTICS (NO CRASH VERSION)
// --------------------------------------------
$total_testimonials = count($testimonials);
$active_testimonials = 0;
$inactive_testimonials = 0;
$featured_testimonials = 0;

foreach ($testimonials as $t) {

    if (isset($t['status']) && $t['status'] === 'active') {
        $active_testimonials++;
    }

    if (isset($t['status']) && $t['status'] === 'inactive') {
        $inactive_testimonials++;
    }

    if (isset($t['is_featured']) && $t['is_featured']) {
        $featured_testimonials++;
    }
}

// --------------------------------------------
// SESSION MESSAGES
// --------------------------------------------
$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error'] ?? null;

unset($_SESSION['success'], $_SESSION['error']);

$page_title = 'Testimonials';

$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => '#', 'title' => 'Testimonials']
];

require_once '../includes/admin-header.php';
?>

<!-- Page Specific Styles -->
<style>
    .testimonials-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .testimonials-actions {
        display: flex;
        gap: 15px;
        align-items: center;
    }
    
    .filter-bar {
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid var(--gray-200);
    }
    
    .filter-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .search-box {
        display: flex;
        align-items: center;
        background: var(--gray-100);
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        padding: 0 15px;
    }
    
    .search-box i {
        color: var(--gray-500);
    }
    
    .search-box input {
        border: none;
        padding: 10px;
        width: 250px;
        background: transparent;
    }
    
    .search-box input:focus {
        outline: none;
    }
    
    .select-all {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .testimonial-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 25px;
        margin-top: 25px;
    }
    
    .testimonial-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        transition: all 0.3s ease;
        position: relative;
        border: 1px solid var(--gray-200);
        display: flex;
        flex-direction: column;
    }
    
    .testimonial-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .testimonial-card.inactive {
        opacity: 0.7;
        background: var(--gray-100);
    }
    
    .testimonial-card.featured {
        border-left: 4px solid var(--gold);
    }
    
    .testimonial-checkbox {
        position: absolute;
        top: 20px;
        left: 20px;
        z-index: 5;
    }
    
    .testimonial-quote {
        font-size: 24px;
        color: var(--gold);
        opacity: 0.3;
        margin-bottom: 15px;
    }
    
    .testimonial-text {
        color: var(--gray-900);
        line-height: 1.6;
        margin-bottom: 20px;
        font-style: italic;
        flex: 1;
    }
    
    .testimonial-author {
        display: flex;
        align-items: center;
        gap: 15px;
        border-top: 1px solid var(--gray-200);
        padding-top: 20px;
        margin-top: auto;
    }
    
    .author-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .author-info {
        flex: 1;
    }
    
    .author-info h4 {
        margin-bottom: 5px;
        color: var(--navy);
        font-size: 16px;
    }
    
    .author-info p {
        margin: 0;
        font-size: 13px;
        color: var(--gray-600);
    }
    
    .rating {
        color: #ffc107;
        margin-top: 5px;
        display: flex;
        gap: 2px;
    }
    
    .rating i {
        font-size: 12px;
    }
    
    .testimonial-actions {
        position: absolute;
        top: 20px;
        right: 20px;
        display: flex;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.3s ease;
        background: white;
        padding: 5px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .testimonial-card:hover .testimonial-actions {
        opacity: 1;
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        color: var(--gray-700);
        transition: all 0.3s ease;
        background: white;
        text-decoration: none;
    }
    
    .btn-icon:hover {
        background: var(--gold);
        color: var(--navy);
    }
    
    .btn-icon.delete:hover {
        background: var(--danger);
        color: white;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        border: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        gap: 20px;
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        background: rgba(255,184,28,0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: var(--gold);
    }
    
    .stat-info h3 {
        font-size: 28px;
        font-weight: 700;
        color: var(--navy);
        margin-bottom: 5px;
    }
    
    .stat-info p {
        color: var(--gray-600);
        font-size: 14px;
        margin: 0;
    }
    
    .featured-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: var(--gold);
        color: var(--navy);
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        margin-top: 10px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 16px;
        border: 2px dashed var(--gray-300);
        margin-top: 30px;
    }
    
    .empty-state i {
        font-size: 48px;
        color: var(--gray-400);
        margin-bottom: 20px;
    }
    
    .empty-state h3 {
        color: var(--navy);
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: var(--gray-600);
        max-width: 400px;
        margin: 0 auto;
    }
    
    .bulk-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .btn-outline {
        background: white;
        border: 1px solid var(--gray-300);
        color: var(--gray-700);
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .btn-outline:hover {
        background: var(--gray-100);
        border-color: var(--gray-400);
    }
    
    .btn-outline i {
        margin-right: 8px;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 20px;
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
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .testimonial-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-bar {
            flex-direction: column;
            gap: 15px;
        }
        
        .search-box input {
            width: 100%;
        }
    }
    
    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .testimonials-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }
    }
</style>

<!-- Notifications -->
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
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-quote-right"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_testimonials; ?></h3>
            <p>Total Testimonials</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $active_testimonials; ?></h3>
            <p>Active</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(255,193,7,0.1); color: #ffc107;">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $featured_testimonials; ?></h3>
            <p>Featured</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(108,117,125,0.1); color: var(--gray-600);">
            <i class="fas fa-eye-slash"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $inactive_testimonials; ?></h3>
            <p>Inactive</p>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar animate__animated animate__fadeIn">
    <div class="filter-group">
        <div class="select-all">
            <input type="checkbox" id="selectAll">
            <label for="selectAll">Select All</label>
        </div>
        
        <div class="bulk-actions" id="bulkActions" style="display: none;">
            <select id="bulkActionSelect" class="form-control" style="width: auto; padding: 8px 15px;">
                <option value="">Bulk Actions</option>
                <option value="activate">Activate</option>
                <option value="deactivate">Deactivate</option>
                <option value="delete">Delete</option>
            </select>
            <button type="button" class="btn-outline" onclick="doBulkAction()">
                Apply
            </button>
        </div>
    </div>
    
    <div class="filter-group">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search testimonials..." onkeyup="searchTestimonials()">
        </div>
        
        <select id="filterStatus" class="form-control" style="width: 150px;" onchange="filterTestimonials()">
            <option value="all">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="featured">Featured</option>
        </select>
        
        <a href="add-testimonial.php" class="btn-primary">
            <i class="fas fa-plus-circle"></i> Add New
        </a>
    </div>
</div>

<!-- Bulk Action Form -->
<form id="bulkForm" method="POST">
    <input type="hidden" name="bulk_action" id="bulkActionValue">
    
    <!-- Testimonials Grid -->
    <?php if ($testimonials): ?>
        <div class="testimonial-grid">
            <?php foreach ($testimonials as $t): ?>
                <div class="testimonial-card <?php 
                    echo $t['status'] != 'active' ? 'inactive' : ''; 
                    echo isset($t['is_featured']) && $t['is_featured'] ? ' featured' : '';
                ?>" data-status="<?php echo $t['status']; ?>" data-featured="<?php echo $t['is_featured'] ?? 0; ?>">
                    
                    <div class="testimonial-checkbox">
                        <input type="checkbox" name="selected[]" value="<?php echo $t['id']; ?>" class="item-checkbox">
                    </div>
                    
                    <div class="testimonial-actions">
                        <a href="edit-testimonial.php?id=<?php echo $t['id']; ?>" class="btn-icon" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?toggle=<?php echo $t['id']; ?>" class="btn-icon" title="<?php echo $t['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                            <i class="fas fa-<?php echo $t['status'] == 'active' ? 'eye' : 'eye-slash'; ?>"></i>
                        </a>
                        <a href="?delete=<?php echo $t['id']; ?>" class="btn-icon delete" title="Delete" 
                           onclick="return confirm('Are you sure you want to delete this testimonial?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                    
                    <div class="testimonial-quote">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    
                    <div class="testimonial-text">
                        <?php echo htmlspecialchars(truncateText($t['testimonial_text'], 150)); ?>
                    </div>
                    
                    <div class="testimonial-author">
                        <?php if (!empty($t['client_photo'])): ?>
                            <img src="<?php echo SITE_URL . '/' . htmlspecialchars($t['client_photo']); ?>" class="author-avatar" alt="<?php echo htmlspecialchars($t['client_name']); ?>">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($t['client_name']); ?>&size=50&background=FFB81C&color=0A1929" class="author-avatar" alt="<?php echo htmlspecialchars($t['client_name']); ?>">
                        <?php endif; ?>
                        <div class="author-info">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h4><?php echo htmlspecialchars($t['client_name']); ?></h4>
                                <span class="status-badge <?php echo $t['status']; ?>">
                                    <?php echo ucfirst($t['status']); ?>
                                </span>
                            </div>
                            <p><?php echo htmlspecialchars($t['client_position']); ?><?php echo !empty($t['company']) ? ', ' . htmlspecialchars($t['company']) : ''; ?></p>
                            
                            <?php if (!empty($t['rating'])): ?>
                                <div class="rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $t['rating'] ? '' : '-o'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($t['is_featured']) && $t['is_featured']): ?>
                                <span class="featured-badge">
                                    <i class="fas fa-star"></i> Featured
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: var(--gray-500);">
                        <span>
                            <i class="fas fa-sort-numeric-up"></i> Order: <?php echo $t['display_order'] ?? 0; ?>
                        </span>
                        <span>
                            <i class="fas fa-clock"></i> <?php echo formatDate($t['created_at'], 'M d, Y'); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state animate__animated animate__fadeIn">
            <i class="fas fa-quote-right"></i>
            <h3>No Testimonials Yet</h3>
            <p>Add your first client testimonial to build trust and credibility with potential clients.</p>
            <a href="add-testimonial.php" class="btn-primary" style="margin-top: 20px;">
                <i class="fas fa-plus-circle"></i> Add Your First Testimonial
            </a>
        </div>
    <?php endif; ?>
</form>

<script>
    // Mobile menu toggle (handled in admin-header.js)
    
    // Auto-hide toast notifications
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            document.querySelectorAll('.toast-notification').forEach(el => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 300);
            });
        }, 5000);
    });
    
    // Select all functionality
    document.getElementById('selectAll').addEventListener('change', function(e) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = e.target.checked;
        });
        toggleBulkActions();
    });
    
    // Individual checkbox change
    document.querySelectorAll('.item-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', toggleBulkActions);
    });
    
    // Toggle bulk actions visibility
    function toggleBulkActions() {
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        const bulkActions = document.getElementById('bulkActions');
        
        if (checkboxes.length > 0) {
            bulkActions.style.display = 'flex';
            document.getElementById('selectAll').checked = checkboxes.length === document.querySelectorAll('.item-checkbox').length;
        } else {
            bulkActions.style.display = 'none';
            document.getElementById('selectAll').checked = false;
        }
    }
    
    // Bulk action handler
    function doBulkAction() {
        const action = document.getElementById('bulkActionSelect').value;
        if (!action) {
            alert('Please select an action');
            return;
        }
        
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        if (checkboxes.length === 0) {
            alert('Please select items to perform bulk action');
            return;
        }
        
        let message = '';
        if (action === 'delete') {
            message = `Are you sure you want to delete ${checkboxes.length} testimonial(s)? This action cannot be undone.`;
        } else if (action === 'activate') {
            message = `Are you sure you want to activate ${checkboxes.length} testimonial(s)?`;
        } else if (action === 'deactivate') {
            message = `Are you sure you want to deactivate ${checkboxes.length} testimonial(s)?`;
        }
        
        if (confirm(message)) {
            document.getElementById('bulkActionValue').value = action;
            document.getElementById('bulkForm').submit();
        }
    }
    
    // Search functionality
    function searchTestimonials() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const cards = document.querySelectorAll('.testimonial-card');
        
        cards.forEach(card => {
            const text = card.querySelector('.testimonial-text').textContent.toLowerCase();
            const author = card.querySelector('h4').textContent.toLowerCase();
            const company = card.querySelector('p').textContent.toLowerCase();
            
            if (text.includes(searchTerm) || author.includes(searchTerm) || company.includes(searchTerm)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    // Filter functionality
    function filterTestimonials() {
        const filterValue = document.getElementById('filterStatus').value;
        const cards = document.querySelectorAll('.testimonial-card');
        
        cards.forEach(card => {
            const status = card.dataset.status;
            const featured = card.dataset.featured;
            
            if (filterValue === 'all') {
                card.style.display = 'flex';
            } else if (filterValue === 'featured') {
                card.style.display = featured === '1' ? 'flex' : 'none';
            } else {
                card.style.display = status === filterValue ? 'flex' : 'none';
            }
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + N for new testimonial
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'add-testimonial.php';
        }
        
        // Ctrl/Cmd + F for search focus
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            document.getElementById('searchInput').focus();
        }
    });
</script>

<?php
// Include admin footer
require_once '../includes/admin-footer.php';
?>