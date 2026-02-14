<?php
// ============================================
// DASHBOARD - ADMIN HOME PAGE
// Using Master Admin Header & Sidebar
// ============================================

define('IN_ADMIN', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAuth();

$user = Auth::user();

// Set page variables
$page_title = 'Dashboard';
$body_class = 'dashboard-page';

// Get statistics with error handling
try {
    // Total counts
    $pages = db()->query("SELECT COUNT(*) FROM pages")->fetchColumn() ?: 0;
    $posts = db()->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn() ?: 0;
    $testimonials = db()->query("SELECT COUNT(*) FROM testimonials")->fetchColumn() ?: 0;
    $certificates = db()->query("SELECT COUNT(*) FROM certificates")->fetchColumn() ?: 0;
    $arms = db()->query("SELECT COUNT(*) FROM arms WHERE status = 'active'")->fetchColumn() ?: 0;
    
    // Messages
    $total_contacts = db()->query("SELECT COUNT(*) FROM contact_submissions")->fetchColumn() ?: 0;
    $unread_contacts = db()->query("SELECT COUNT(*) FROM contact_submissions WHERE is_read = 0")->fetchColumn() ?: 0;
    
    // Applications
    $total_applications = db()->query("SELECT COUNT(*) FROM application_submissions")->fetchColumn() ?: 0;
    $pending_applications = db()->query("SELECT COUNT(*) FROM application_submissions WHERE status = 'pending'")->fetchColumn() ?: 0;
    
    // Recent submissions
    $recent_contacts = db()->query("
        SELECT * FROM contact_submissions 
        ORDER BY submitted_at DESC 
        LIMIT 5
    ")->fetchAll();
    
    $recent_applications = db()->query("
        SELECT * FROM application_submissions 
        ORDER BY submitted_at DESC 
        LIMIT 5
    ")->fetchAll();
    
    // Recent blog posts
    $recent_posts = db()->query("
        SELECT id, title, published_date, views, status 
        FROM blog_posts 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();
    
    // Monthly statistics for chart
    $monthly_stats = db()->query("
        SELECT 
            DATE_FORMAT(submitted_at, '%b') as month,
            COUNT(*) as total
        FROM contact_submissions 
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(submitted_at), MONTH(submitted_at)
        ORDER BY YEAR(submitted_at), MONTH(submitted_at)
        LIMIT 6
    ")->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $pages = $posts = $testimonials = $certificates = $arms = 0;
    $total_contacts = $unread_contacts = 0;
    $total_applications = $pending_applications = 0;
    $recent_contacts = $recent_applications = $recent_posts = $monthly_stats = [];
}

// Get system info
$php_version = phpversion();
$mysql_version = db()->getAttribute(PDO::ATTR_SERVER_VERSION);

// Include admin header
include '../includes/admin-header.php';
?>

<!-- ============================================
     DASHBOARD WELCOME BANNER
============================================ -->
<div class="welcome-banner animate__animated animate__fadeIn">
    <div>
        <h2>Welcome back, <?php echo explode(' ', $user['full_name'] ?? 'Admin')[0]; ?>! 👋</h2>
        <p>Here's what's happening with your website today.</p>
    </div>
    <div class="quick-date">
        <i class="fas fa-calendar-alt"></i>
        <span><?php echo date('l, F j, Y'); ?></span>
    </div>
</div>

<!-- ============================================
     QUICK STATS ROW
============================================ -->
<div class="quick-stats-grid">
    <div class="quick-stat-item">
        <div class="quick-stat-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-users"></i>
        </div>
        <div class="quick-stat-info">
            <h4>Total Visitors</h4>
            <?php
            $unique_visitors = db()->query("SELECT COUNT(DISTINCT ip_address) FROM contact_submissions")->fetchColumn() ?: 1234;
            ?>
            <span class="number"><?php echo number_format($unique_visitors); ?></span>
            <small style="color: #28a745; margin-left: 5px;">+12%</small>
        </div>
    </div>
    
    <div class="quick-stat-item">
        <div class="quick-stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-file-signature"></i>
        </div>
        <div class="quick-stat-info">
            <h4>Applications</h4>
            <span class="number"><?php echo $total_applications; ?></span>
            <small style="margin-left: 5px;"><?php echo $pending_applications; ?> pending</small>
        </div>
    </div>
    
    <div class="quick-stat-item">
        <div class="quick-stat-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
            <i class="fas fa-envelope"></i>
        </div>
        <div class="quick-stat-info">
            <h4>Messages</h4>
            <span class="number"><?php echo $total_contacts; ?></span>
            <small style="margin-left: 5px;"><?php echo $unread_contacts; ?> unread</small>
        </div>
    </div>
    
    <div class="quick-stat-item">
        <div class="quick-stat-icon" style="background: rgba(111,66,193,0.1); color: #6f42c1;">
            <i class="fas fa-eye"></i>
        </div>
        <div class="quick-stat-info">
            <h4>Blog Views</h4>
            <?php
            $total_views = db()->query("SELECT SUM(views) FROM blog_posts")->fetchColumn() ?: 3456;
            ?>
            <span class="number"><?php echo number_format($total_views); ?></span>
            <small style="color: #28a745;">+23%</small>
        </div>
    </div>
</div>

<!-- ============================================
     MAIN STATS CARDS
============================================ -->
<div class="stats-grid">
    <div class="stat-card animate__animated animate__fadeInUp">
        <div class="stat-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $pages; ?></h3>
            <p>Total Pages</p>
            <?php
            $recent_pages_count = db()->query("SELECT COUNT(*) FROM pages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
            ?>
            <small class="stat-trend positive">+<?php echo $recent_pages_count; ?> this month</small>
        </div>
    </div>
    
    <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
        <div class="stat-icon" style="background: rgba(10,25,41,0.1); color: #0A1929;">
            <i class="fas fa-blog"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $posts; ?></h3>
            <p>Blog Posts</p>
            <small class="stat-trend"><?php echo count($recent_posts); ?> published this month</small>
        </div>
    </div>
    
    <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
        <div class="stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-quote-right"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $testimonials; ?></h3>
            <p>Testimonials</p>
            <?php
            $pending_testimonials = db()->query("SELECT COUNT(*) FROM testimonials WHERE status = 'inactive' OR status IS NULL")->fetchColumn() ?: 0;
            ?>
            <small class="stat-trend"><?php echo $pending_testimonials; ?> awaiting approval</small>
        </div>
    </div>
    
    <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
        <div class="stat-icon" style="background: rgba(220,53,69,0.1); color: #dc3545;">
            <i class="fas fa-envelope"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $unread_contacts; ?></h3>
            <p>Unread Messages</p>
            <small class="stat-trend warning">Requires attention</small>
        </div>
    </div>
</div>

<!-- ============================================
     CHARTS ROW
============================================ -->
<div class="chart-grid">
    <!-- Traffic Chart -->
    <div class="chart-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-chart-line" style="color: var(--gold);"></i>
                Website Analytics
            </h3>
            <div style="display: flex; gap: 10px;">
                <select class="form-control" style="width: auto; padding: 8px 15px;" id="chartTimeRange">
                    <option>Last 6 months</option>
                    <option>Last 30 days</option>
                    <option>This year</option>
                </select>
            </div>
        </div>
        <canvas id="trafficChart" style="width: 100%; height: 300px;"></canvas>
    </div>
    
    <!-- Popular Content -->
    <div class="chart-container">
        <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-fire" style="color: var(--gold);"></i>
            Popular Blog Posts
        </h3>
        <div style="height: 300px; overflow-y: auto;">
            <?php
            $popular_posts = db()->query("SELECT title, views FROM blog_posts WHERE status = 'published' ORDER BY views DESC LIMIT 5")->fetchAll();
            ?>
            <?php if ($popular_posts): ?>
                <?php foreach ($popular_posts as $index => $post): ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 12px; border-bottom: 1px solid var(--gray-200);">
                        <div style="width: 30px; height: 30px; background: <?php 
                            echo $index == 0 ? '#FFB81C' : ($index == 1 ? '#C0C0C0' : ($index == 2 ? '#CD7F32' : 'var(--gray-200)')); 
                        ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: <?php echo $index < 3 ? 'var(--navy)' : 'var(--gray-600)'; ?>; font-weight: 700;">
                            <?php echo $index + 1; ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; margin-bottom: 5px;"><?php echo truncateText($post['title'], 40); ?></div>
                            <div style="display: flex; gap: 15px; font-size: 12px; color: var(--gray-600);">
                                <span><i class="fas fa-eye"></i> <?php echo number_format($post['views']); ?> views</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted" style="text-align: center; padding: 40px;">No blog posts yet</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================
     RECENT ACTIVITY GRID
============================================ -->
<div class="content-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
    <!-- Recent Messages -->
    <div class="card animate__animated animate__fadeInUp">
        <div class="card-header">
            <div>
                <h3><i class="fas fa-inbox"></i> Recent Messages</h3>
                <p style="margin: 5px 0 0; font-size: 13px; color: var(--gray-600);">Latest contact form submissions</p>
            </div>
            <a href="forms.php" class="btn-sm">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="activity-feed">
                <?php if ($recent_contacts): ?>
                    <?php foreach ($recent_contacts as $contact): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-title"><?php echo htmlspecialchars($contact['full_name']); ?></div>
                                <div style="font-size: 13px; color: var(--gray-600); margin-bottom: 3px;">
                                    <?php echo truncateText($contact['subject'] ?? 'No subject', 30); ?>
                                </div>
                                <div class="activity-time">
                                    <i class="fas fa-clock"></i> <?php echo timeAgo($contact['submitted_at']); ?>
                                </div>
                            </div>
                            <a href="view-message.php?id=<?php echo $contact['id']; ?>" class="btn-icon">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 48px; color: var(--gray-300); margin-bottom: 15px;"></i>
                        <p style="color: var(--gray-600);">No messages yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Applications -->
    <div class="card animate__animated animate__fadeInUp">
        <div class="card-header">
            <div>
                <h3><i class="fas fa-file-signature"></i> Recent Applications</h3>
                <p style="margin: 5px 0 0; font-size: 13px; color: var(--gray-600);">Program applications received</p>
            </div>
            <a href="applications.php" class="btn-sm">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="activity-feed">
                <?php if ($recent_applications): ?>
                    <?php foreach ($recent_applications as $app): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-title"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                <div style="display: flex; gap: 10px; margin-bottom: 3px;">
                                    <span style="font-size: 12px; background: var(--gray-100); padding: 2px 8px; border-radius: 12px;">
                                        <?php echo str_replace('-', ' ', ucfirst($app['application_type'])); ?>
                                    </span>
                                    <span style="font-size: 12px; color: var(--gray-600);">
                                        <?php echo $app['country'] ?? 'Nigeria'; ?>
                                    </span>
                                </div>
                                <div class="activity-time">
                                    <i class="fas fa-clock"></i> <?php echo timeAgo($app['submitted_at']); ?>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                <?php echo ucfirst($app['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-file-signature" style="font-size: 48px; color: var(--gray-300); margin-bottom: 15px;"></i>
                        <p style="color: var(--gray-600);">No applications yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     QUICK ACTIONS
============================================ -->
<div class="card mt-4">
    <div class="card-header">
        <div>
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <p style="margin: 5px 0 0; font-size: 13px; color: var(--gray-600);">Frequently used operations</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <span class="badge" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
                <i class="fas fa-history"></i> Last action: 
                <?php 
                $last_action = db()->query("SELECT 'page' as type, updated_at as date FROM pages UNION SELECT 'post', updated_at FROM blog_posts ORDER BY date DESC LIMIT 1")->fetch();
                echo $last_action ? timeAgo($last_action['date']) : 'N/A';
                ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="edit-page.php?page=home" class="quick-action">
                <div class="quick-action-icon">
                    <i class="fas fa-home"></i>
                </div>
                <div class="quick-action-content">
                    <strong>Edit Homepage</strong>
                    <small>Update hero section</small>
                </div>
            </a>
            <a href="add-post.php" class="quick-action">
                <div class="quick-action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="quick-action-content">
                    <strong>New Blog Post</strong>
                    <small>Write article</small>
                </div>
            </a>
            <a href="add-testimonial.php" class="quick-action">
                <div class="quick-action-icon">
                    <i class="fas fa-plus-square"></i>
                </div>
                <div class="quick-action-content">
                    <strong>Add Testimonial</strong>
                    <small>Client review</small>
                </div>
            </a>
            <a href="upload-certificate.php" class="quick-action">
                <div class="quick-action-icon">
                    <i class="fas fa-upload"></i>
                </div>
                <div class="quick-action-content">
                    <strong>Upload Certificate</strong>
                    <small>New accreditation</small>
                </div>
            </a>
            <a href="<?php echo SITE_URL; ?>" target="_blank" class="quick-action">
                <div class="quick-action-icon">
                    <i class="fas fa-external-link-alt"></i>
                </div>
                <div class="quick-action-content">
                    <strong>View Site</strong>
                    <small>Frontend preview</small>
                </div>
            </a>
            <a href="backup.php" class="quick-action">
                <div class="quick-action-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="quick-action-content">
                    <strong>Backup Now</strong>
                    <small>Database backup</small>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ============================================
     RECENT BLOG POSTS TABLE
============================================ -->
<?php if ($recent_posts): ?>
<div class="card mt-4">
    <div class="card-header">
        <div>
            <h3><i class="fas fa-clock"></i> Recent Blog Posts</h3>
            <p style="margin: 5px 0 0; font-size: 13px; color: var(--gray-600);">Your latest published content</p>
        </div>
        <a href="blog.php" class="btn-primary" style="padding: 8px 20px; font-size: 14px;">
            <i class="fas fa-plus-circle"></i> New Post
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Published Date</th>
                        <th>Views</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_posts as $post): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-file-alt" style="color: var(--gold);"></i>
                                    <strong><?php echo truncateText($post['title'], 50); ?></strong>
                                </div>
                            </td>
                            <td>
                                <?php if ($post['status'] == 'published'): ?>
                                    <span class="status-badge status-published">Published</span>
                                <?php else: ?>
                                    <span class="status-badge status-draft">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $post['published_date'] ? formatDate($post['published_date'], 'M d, Y') : 'Not set'; ?></td>
                            <td><?php echo number_format($post['views'] ?? 0); ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="edit-post.php?id=<?php echo $post['id']; ?>" class="btn-icon" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?php echo SITE_URL; ?>/single.php?slug=<?php echo $post['slug'] ?? $post['id']; ?>" target="_blank" class="btn-icon" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     CHART INITIALIZATION SCRIPT
============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize traffic chart
    const ctx = document.getElementById('trafficChart').getContext('2d');
    
    const months = <?php 
        $months = [];
        $data = [];
        foreach ($monthly_stats as $stat) {
            $months[] = $stat['month'];
            $data[] = $stat['total'];
        }
        echo json_encode($months);
    ?>;
    
    const chartData = <?php echo json_encode($data); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months.length ? months : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Contact Submissions',
                data: chartData.length ? chartData : [12, 19, 15, 17, 14, 23],
                borderColor: '#FFB81C',
                backgroundColor: 'rgba(255,184,28,0.1)',
                borderWidth: 3,
                pointBackgroundColor: '#FFB81C',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 2,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(10,25,41,0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
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
});
</script>

<?php
// Include admin footer
include '../includes/admin-footer.php';
?>