<?php
// ============================================
// ADMIN MASTER HEADER & SIDEBAR - FIXED
// Include this at the top of every admin page
// ============================================

// Prevent direct access
if (!defined('IN_ADMIN')) {
    // Only allow direct access check if IN_ADMIN is not defined
    if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
        exit('Direct access not allowed');
    }
}

// Define IN_ADMIN if not already defined
if (!defined('IN_ADMIN')) {
    define('IN_ADMIN', true);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

// Get current user
$user = Auth::user();
if (!$user) {
    header('Location: logout.php');
    exit;
}

// Initialize counts array
$counts = [
    'total_pages' => 0,
    'total_blog_posts' => 0,
    'total_testimonials' => 0,
    'total_galleries' => 0,
    'total_certificates' => 0,
    'total_arms' => 0,
    'unread_contacts' => 0,
    'pending_applications' => 0,
    'pending_sponsorships' => 0,
    'total_users' => 0,
    'total_backups' => 0
];

// Get all counts for sidebar badges with error handling
try {
    $db = db();
    
    // Main counts
    $counts['total_pages'] = (int) ($db->query("SELECT COUNT(*) FROM pages")->fetchColumn() ?: 0);
    $counts['total_blog_posts'] = (int) ($db->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn() ?: 0);
    
    // Content counts
    $counts['total_testimonials'] = (int) ($db->query("SELECT COUNT(*) FROM testimonials")->fetchColumn() ?: 0);
    $counts['total_galleries'] = (int) ($db->query("SELECT COUNT(*) FROM galleries")->fetchColumn() ?: 0);
    $counts['total_certificates'] = (int) ($db->query("SELECT COUNT(*) FROM certificates")->fetchColumn() ?: 0);
    $counts['total_arms'] = (int) ($db->query("SELECT COUNT(*) FROM arms")->fetchColumn() ?: 0);
    
    // Form counts
    $counts['unread_contacts'] = (int) ($db->query("SELECT COUNT(*) FROM contact_submissions WHERE is_read = 0")->fetchColumn() ?: 0);
    $counts['pending_applications'] = (int) ($db->query("SELECT COUNT(*) FROM application_submissions WHERE status = 'pending'")->fetchColumn() ?: 0);
    $counts['pending_sponsorships'] = (int) ($db->query("SELECT COUNT(*) FROM sponsorship_submissions WHERE status = 'pending'")->fetchColumn() ?: 0);
    
    // System counts
    $counts['total_users'] = (int) ($db->query("SELECT COUNT(*) FROM admin_users")->fetchColumn() ?: 0);
    $counts['total_backups'] = (int) ($db->query("SELECT COUNT(*) FROM backup_logs")->fetchColumn() ?: 0);
    
} catch (PDOException $e) {
    error_log("Error fetching sidebar counts: " . $e->getMessage());
    // Counts already initialized to 0
}

// Extract counts for easy access
extract($counts);

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get recent activity for sidebar footer
$recent_activity = null;
try {
    $recent_activity = db()->query("
        (SELECT 'page' as type, page_name as name, updated_at as date FROM pages ORDER BY updated_at DESC LIMIT 1)
        UNION
        (SELECT 'post', title, updated_at FROM blog_posts ORDER BY updated_at DESC LIMIT 1)
        UNION
        (SELECT 'setting', setting_key, updated_at FROM site_settings ORDER BY updated_at DESC LIMIT 1)
        ORDER BY date DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching recent activity: " . $e->getMessage());
}

// Get system info with error handling
$php_version = phpversion();
$mysql_version = 'Unknown';
try {
    $mysql_version = db()->getAttribute(PDO::ATTR_SERVER_VERSION);
} catch (PDOException $e) {
    error_log("Error fetching MySQL version: " . $e->getMessage());
}

// Helper function for time ago
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        if (!$datetime) return 'Never';
        
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        
        return date('M j, Y', $time);
    }
}

// Set default page title if not set
if (!isset($page_title)) {
    $page_title = 'Dashboard';
}

// Get user initials for avatar
$user_initials = '';
if (isset($user['full_name'])) {
    $names = explode(' ', $user['full_name']);
    foreach ($names as $name) {
        $user_initials .= strtoupper(substr($name, 0, 1));
    }
} else {
    $user_initials = 'A';
}

// Total notifications count
$total_notifications = $unread_contacts + $pending_applications + $pending_sponsorships;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title><?php echo htmlspecialchars($page_title); ?> - AGPN Admin Panel</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Admin Stylesheet -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo file_exists('../assets/css/admin.css') ? filemtime('../assets/css/admin.css') : '1.0'; ?>">
    
    <!-- Page Specific CSS -->
    <?php if (isset($page_css)): ?>
        <?php $css_files = is_array($page_css) ? $page_css : [$page_css]; ?>
        <?php foreach ($css_files as $css_file): ?>
            <?php if (preg_match('/^[a-zA-Z0-9_\-]+\.css$/', $css_file)): ?>
                <link rel="stylesheet" href="../assets/css/<?php echo htmlspecialchars($css_file); ?>?v=<?php echo file_exists('../assets/css/' . $css_file) ? filemtime('../assets/css/' . $css_file) : '1.0'; ?>">
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Chart.js for analytics pages -->
    <?php if (in_array($current_page, ['dashboard', 'reports'])): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <?php endif; ?>
    
    <!-- AOS Animation for dashboard -->
    <?php if ($current_page == 'dashboard'): ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <?php endif; ?>
    
    <style>
        /* ============================================
           ADMIN LAYOUT - ENHANCED
        ============================================ */
        
        :root {
            --navy: #0A1929;
            --navy-light: #1A2A3A;
            --navy-dark: #051220;
            --gold: #FFB81C;
            --gold-dark: #E5A600;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: var(--gray-900);
            background: var(--gray-100);
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            background: var(--gray-100);
            position: relative;
            overflow-x: hidden;
        }
        
        /* ============================================
           COMPLETE SIDEBAR STYLES
        ============================================ */
        
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--navy) 0%, var(--navy-light) 100%);
            color: var(--white);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 30px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }
        
        .logo-area {
            text-align: center;
        }
        
        .sidebar-header h2 {
            color: var(--gold);
            margin-bottom: 5px;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .sidebar-header p {
            color: var(--gray-400);
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .sidebar-user {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(255,184,28,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            font-size: 24px;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        .user-info strong {
            font-size: 15px;
            color: var(--white);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-info small {
            font-size: 12px;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-dot.active {
            background: var(--success);
            box-shadow: 0 0 0 2px rgba(40,167,69,0.2);
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .nav-section {
            margin-bottom: 25px;
        }
        
        .nav-section-title {
            padding: 0 20px;
            margin-bottom: 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gray-500);
            opacity: 0.7;
        }
        
        .sidebar-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-nav li {
            margin-bottom: 2px;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--gray-300);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
        }
        
        .sidebar-nav a i {
            width: 24px;
            margin-right: 12px;
            font-size: 18px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .sidebar-nav a span:not(.nav-badge) {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar-nav li.active a,
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            color: var(--gold);
            border-left-color: var(--gold);
        }
        
        .sidebar-nav .nav-badge {
            margin-left: auto;
            background: var(--gold);
            color: var(--navy);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .sidebar-nav .nav-badge.warning {
            background: var(--warning);
            color: var(--gray-900);
        }
        
        .sidebar-nav .logout {
            margin-top: 40px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 20px;
        }
        
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: linear-gradient(180deg, transparent, var(--navy-dark));
            flex-shrink: 0;
        }
        
        .theme-toggle-wrapper {
            margin-bottom: 15px;
        }
        
        .theme-toggle {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 30px;
            padding: 5px;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        
        .theme-toggle i {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: var(--gray-400);
            transition: all 0.3s ease;
        }
        
        .theme-toggle i.active {
            background: var(--gold);
            color: var(--navy);
        }
        
        .system-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .system-info small {
            color: var(--gray-500);
            font-size: 11px;
            opacity: 0.8;
        }
        
        /* ============================================
           MAIN CONTENT HEADER
        ============================================ */
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            width: calc(100% - 280px);
            min-height: 100vh;
            background: var(--gray-100);
            display: flex;
            flex-direction: column;
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--navy);
            cursor: pointer;
            padding: 10px;
            margin-right: 5px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .menu-toggle:hover {
            background: var(--gray-100);
        }
        
        .page-title h1 {
            color: var(--navy);
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 5px 0;
            line-height: 1.2;
        }
        
        .breadcrumb {
            color: var(--gray-600);
            font-size: 13px;
        }
        
        .breadcrumb a {
            color: var(--gray-600);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .breadcrumb a:hover {
            color: var(--gold);
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .header-search {
            display: flex;
            align-items: center;
            background: var(--gray-100);
            border: 1px solid var(--gray-200);
            border-radius: 30px;
            padding: 0 15px;
            width: 280px;
            transition: all 0.3s ease;
        }
        
        .header-search:focus-within {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(255,184,28,0.1);
            background: var(--white);
        }
        
        .header-search i {
            color: var(--gray-600);
            font-size: 14px;
        }
        
        .header-search input {
            border: none;
            padding: 12px 10px;
            width: 100%;
            font-size: 14px;
            background: transparent;
        }
        
        .header-search input:focus {
            outline: none;
        }
        
        .header-notifications {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .notification-icon {
            position: relative;
            cursor: pointer;
        }
        
        .notification-icon i {
            font-size: 22px;
            color: var(--gray-600);
            transition: color 0.3s ease;
        }
        
        .notification-icon:hover i {
            color: var(--gold);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: var(--white);
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }
        
        .user-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 30px;
            transition: all 0.3s ease;
        }
        
        .user-dropdown:hover {
            background: var(--gray-100);
        }
        
        .user-avatar-small {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--gold);
            color: var(--navy);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .user-dropdown span {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .user-dropdown i {
            font-size: 12px;
            color: var(--gray-600);
        }
        
        .content-body {
            padding: 30px;
            flex: 1;
        }
        
        /* ============================================
           SIDEBAR OVERLAY FOR MOBILE
        ============================================ */
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1500;
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(3px);
        }
        
        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }
        
        /* ============================================
           RESPONSIVE BREAKPOINTS
        ============================================ */
        
        @media (min-width: 1400px) {
            .sidebar {
                width: 300px;
            }
            .main-content {
                margin-left: 300px;
                width: calc(100% - 300px);
            }
        }
        
        @media (max-width: 1199px) {
            .header-search {
                width: 240px;
            }
        }
        
        @media (max-width: 991px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar .sidebar-header h2,
            .sidebar .sidebar-header p,
            .sidebar .sidebar-user .user-info,
            .sidebar .sidebar-nav a span:not(.nav-badge),
            .sidebar .sidebar-footer .system-info,
            .sidebar .nav-section-title,
            .sidebar .nav-badge,
            .sidebar .theme-toggle-wrapper span {
                display: none;
            }
            
            .sidebar .sidebar-user {
                padding: 20px 0;
                justify-content: center;
            }
            
            .sidebar .sidebar-nav a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar .sidebar-nav a i {
                margin: 0;
                font-size: 22px;
            }
            
            .sidebar .sidebar-footer {
                padding: 20px 0;
                text-align: center;
            }
            
            .sidebar .theme-toggle {
                margin: 0 auto;
            }
            
            .main-content {
                margin-left: 80px;
                width: calc(100% - 80px);
            }
        }
        
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .sidebar.active .sidebar-header h2,
            .sidebar.active .sidebar-header p,
            .sidebar.active .sidebar-user .user-info,
            .sidebar.active .sidebar-nav a span,
            .sidebar.active .sidebar-footer .system-info,
            .sidebar.active .nav-section-title,
            .sidebar.active .nav-badge,
            .sidebar.active .theme-toggle-wrapper span {
                display: block;
            }
            
            .sidebar.active .sidebar-nav a {
                justify-content: flex-start;
                padding: 12px 20px;
            }
            
            .sidebar.active .sidebar-nav a i {
                margin-right: 12px;
                font-size: 18px;
            }
            
            .sidebar.active .sidebar-user {
                padding: 20px;
                justify-content: flex-start;
            }
            
            .sidebar.active .sidebar-footer {
                padding: 20px;
                text-align: left;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .content-header {
                padding: 15px 20px;
            }
            
            .header-right {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }
            
            .header-search {
                width: 100%;
            }
            
            .header-notifications {
                justify-content: space-between;
            }
            
            .content-body {
                padding: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .content-header {
                padding: 15px;
            }
            
            .header-left {
                width: 100%;
            }
            
            .page-title h1 {
                font-size: 20px;
            }
            
            .breadcrumb {
                display: none;
            }
            
            .user-dropdown span {
                display: none;
            }
            
            .content-body {
                padding: 15px;
            }
        }
        
        @media print {
            .sidebar,
            .menu-toggle,
            .header-right,
            .sidebar-overlay {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            .content-header {
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body class="<?php echo isset($body_class) ? htmlspecialchars($body_class) : ''; ?>">
    <div class="admin-wrapper">
        
        <!-- SIDEBAR OVERLAY FOR MOBILE -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- ============================================
             COMPLETE SIDEBAR - ALL ADMIN FEATURES
        ============================================ -->
        <aside class="sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <div class="logo-area">
                    <h2>AGPN</h2>
                    <p>Admin Panel v2.0</p>
                </div>
            </div>
            
            <div class="sidebar-user">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-info">
                    <strong><?php echo htmlspecialchars($user['full_name'] ?? 'Administrator'); ?></strong>
                    <small>
                        <span class="status-dot active"></span>
                        <?php echo htmlspecialchars(ucfirst($user['role'] ?? 'Administrator')); ?>
                    </small>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <!-- MAIN SECTION -->
                <div class="nav-section">
                    <p class="nav-section-title">Main</p>
                    <ul>
                        <li class="<?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                            <a href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Dashboard</span>
                                <span class="nav-badge">Live</span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page == 'pages' ? 'active' : ''; ?>">
                            <a href="pages.php">
                                <i class="fas fa-file-alt"></i>
                                <span>Pages</span>
                                <span class="nav-badge"><?php echo $total_pages; ?></span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page == 'blog' ? 'active' : ''; ?>">
                            <a href="blog.php">
                                <i class="fas fa-blog"></i>
                                <span>Blog Posts</span>
                                <span class="nav-badge"><?php echo $total_blog_posts; ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- CONTENT SECTION -->
                <div class="nav-section">
                    <p class="nav-section-title">Content</p>
                    <ul>
                        <li class="<?php echo $current_page == 'testimonials' ? 'active' : ''; ?>">
                            <a href="testimonials.php">
                                <i class="fas fa-quote-right"></i>
                                <span>Testimonials</span>
                                <span class="nav-badge"><?php echo $total_testimonials; ?></span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page == 'gallery' ? 'active' : ''; ?>">
                            <a href="gallery.php">
                                <i class="fas fa-images"></i>
                                <span>Gallery</span>
                                <span class="nav-badge"><?php echo $total_galleries; ?></span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page == 'certificates' ? 'active' : ''; ?>">
                            <a href="certificates.php">
                                <i class="fas fa-certificate"></i>
                                <span>Certificates</span>
                                <span class="nav-badge"><?php echo $total_certificates; ?></span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page == 'arms' ? 'active' : ''; ?>">
                            <a href="arms.php">
                                <i class="fas fa-cubes"></i>
                                <span>6 Arms</span>
                                <span class="nav-badge"><?php echo $total_arms; ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- FORMS SECTION -->
                <div class="nav-section">
                    <p class="nav-section-title">Forms</p>
                    <ul>
                        <li class="<?php echo $current_page == 'contact' || $current_page == 'forms' ? 'active' : ''; ?>">
                            <a href="forms.php">
                                <i class="fas fa-inbox"></i>
                                <span>Contact Submissions</span>
                                <?php if ($unread_contacts > 0): ?>
                                    <span class="nav-badge warning"><?php echo $unread_contacts; ?> new</span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="<?php echo $current_page == 'applications' ? 'active' : ''; ?>">
                            <a href="applications.php">
                                <i class="fas fa-file-signature"></i>
                                <span>Applications</span>
                                <?php if ($pending_applications > 0): ?>
                                    <span class="nav-badge warning"><?php echo $pending_applications; ?> pending</span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="<?php echo $current_page == 'sponsorships' ? 'active' : ''; ?>">
                            <a href="sponsorships.php">
                                <i class="fas fa-handshake"></i>
                                <span>Sponsorships</span>
                                <?php if ($pending_sponsorships > 0): ?>
                                    <span class="nav-badge warning"><?php echo $pending_sponsorships; ?> pending</span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- REPORTS SECTION -->
                <div class="nav-section">
                    <p class="nav-section-title">Reports</p>
                    <ul>
                        <li class="<?php echo $current_page == 'reports' ? 'active' : ''; ?>">
                            <a href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                <span>Analytics</span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page == 'backup' ? 'active' : ''; ?>">
                            <a href="backup.php">
                                <i class="fas fa-database"></i>
                                <span>Backup</span>
                                <span class="nav-badge"><?php echo $total_backups; ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- SYSTEM SECTION -->
                <div class="nav-section">
                    <p class="nav-section-title">System</p>
                    <ul>
                        <li class="<?php echo $current_page == 'users' ? 'active' : ''; ?>">
                            <a href="users.php">
                                <i class="fas fa-users-cog"></i>
                                <span>User Management</span>
                                <span class="nav-badge"><?php echo $total_users; ?></span>
                            </a>
                        </li>
                        <li class="<?php echo $current_page == 'settings' ? 'active' : ''; ?>">
                            <a href="settings.php">
                                <i class="fas fa-cog"></i>
                                <span>Settings</span>
                            </a>
                        </li>
                        <li class="logout">
                            <a href="logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="theme-toggle-wrapper">
                    <span style="color: var(--gray-400); font-size: 12px; margin-bottom: 10px; display: block;">Theme</span>
                    <div class="theme-toggle" id="themeToggle">
                        <i class="fas fa-sun active"></i>
                        <i class="fas fa-moon"></i>
                    </div>
                </div>
                <div class="system-info">
                    <small>AGPN v2.0</small>
                    <small>PHP <?php echo htmlspecialchars(substr($php_version, 0, 6)); ?></small>
                    <?php if ($recent_activity && !empty($recent_activity['date'])): ?>
                        <small>Last: <?php echo timeAgo($recent_activity['date']); ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

        <!-- ============================================
             MAIN CONTENT AREA
        ============================================ -->
        <main class="main-content">
            
            <!-- ========================================
                 TOP HEADER WITH SEARCH & USER MENU
            ======================================== -->
            <header class="content-header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-title">
                        <h1><?php echo htmlspecialchars($page_title); ?></h1>
                        <span class="breadcrumb">
                            <a href="dashboard.php">Home</a> 
                            <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
                                <?php foreach ($breadcrumbs as $crumb): ?>
                                    <?php if (isset($crumb['url']) && isset($crumb['title'])): ?>
                                        / <a href="<?php echo htmlspecialchars($crumb['url']); ?>"><?php echo htmlspecialchars($crumb['title']); ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                / <span><?php echo htmlspecialchars($page_title); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <div class="header-right">
                    <div class="header-search">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search..." id="globalSearch" aria-label="Global search">
                    </div>
                    
                    <div class="header-notifications">
                        <div class="notification-icon">
                            <i class="fas fa-bell"></i>
                            <?php if ($total_notifications > 0): ?>
                                <span class="notification-badge">
                                    <?php echo min($total_notifications, 99); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="user-dropdown" id="userDropdown">
                            <div class="user-avatar-small">
                                <?php echo htmlspecialchars($user_initials); ?>
                            </div>
                            <span><?php echo htmlspecialchars($user['full_name'] ?? 'Admin'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ========================================
                 CONTENT BODY - PAGE SPECIFIC CONTENT
                 THIS DIV MUST BE CLOSED IN FOOTER
            ======================================== -->
            <div class="content-body">