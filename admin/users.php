<?php
// ============================================
// USER MANAGEMENT PAGE - FULLY RESPONSIVE
// ============================================

// Define IN_ADMIN before including any files
define('IN_ADMIN', true);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
Auth::requireAuth();

// Get current user
$user = Auth::user();

// Only superadmin can access user management
if ($user['role'] !== 'superadmin') {
    $_SESSION['error'] = 'You do not have permission to access user management';
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle add user
if (isset($_POST['add_user'])) {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $full_name = sanitize($_POST['full_name']);
    $role = sanitize($_POST['role']);
    $status = sanitize($_POST['status'] ?? 'active');
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $_SESSION['error'] = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format';
    } elseif (strlen($password) < 8) {
        $_SESSION['error'] = 'Password must be at least 8 characters';
    } else {
        try {
            $db = db();
            
            // Check if username exists
            $check = $db->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            
            if ($check->fetch()) {
                $_SESSION['error'] = 'Username or email already exists';
            } else {
                $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                
                $stmt = $db->prepare("
                    INSERT INTO admin_users (username, email, password_hash, full_name, role, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$username, $email, $password_hash, $full_name, $role, $status]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                    VALUES (?, 'create', 'user', ?, ?, NOW())
                ");
                $stmt->execute([$user['id'], $db->lastInsertId(), "Created user: $full_name ($username)"]);
                
                $_SESSION['success'] = 'User added successfully';
                redirect('users.php');
            }
        } catch (PDOException $e) {
            error_log("Error adding user: " . $e->getMessage());
            $_SESSION['error'] = 'Error adding user';
        }
    }
    redirect('users.php');
}

// Handle edit user
if (isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $email = sanitize($_POST['email']);
    $full_name = sanitize($_POST['full_name']);
    $role = sanitize($_POST['role']);
    $status = sanitize($_POST['status']);
    
    if (empty($email) || empty($full_name)) {
        $_SESSION['error'] = 'Email and full name are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format';
    } else {
        try {
            $db = db();
            
            // Check if email exists for other users
            $check = $db->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
            $check->execute([$email, $user_id]);
            
            if ($check->fetch()) {
                $_SESSION['error'] = 'Email already in use by another user';
            } else {
                $stmt = $db->prepare("
                    UPDATE admin_users SET
                        email = ?,
                        full_name = ?,
                        role = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$email, $full_name, $role, $status, $user_id]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                    VALUES (?, 'update', 'user', ?, ?, NOW())
                ");
                $stmt->execute([$user['id'], $user_id, "Updated user: $full_name"]);
                
                $_SESSION['success'] = 'User updated successfully';
                redirect('users.php');
            }
        } catch (PDOException $e) {
            error_log("Error updating user: " . $e->getMessage());
            $_SESSION['error'] = 'Error updating user';
        }
    }
    redirect('users.php');
}

// Handle reset password
if (isset($_POST['reset_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = 'All password fields are required';
    } elseif (strlen($new_password) < 8) {
        $_SESSION['error'] = 'Password must be at least 8 characters';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = 'Passwords do not match';
    } else {
        try {
            $db = db();
            $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            
            $stmt = $db->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, 'password_reset', 'user', ?, 'Password reset', NOW())
            ");
            $stmt->execute([$user['id'], $user_id]);
            
            $_SESSION['success'] = 'Password reset successfully';
            redirect('users.php');
        } catch (PDOException $e) {
            error_log("Error resetting password: " . $e->getMessage());
            $_SESSION['error'] = 'Error resetting password';
        }
    }
    redirect('users.php');
}

// Handle delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    // Prevent deleting yourself
    if ($user_id == $user['id']) {
        $_SESSION['error'] = 'You cannot delete your own account';
    } else {
        try {
            $db = db();
            
            // Check if last superadmin
            $check = $db->prepare("SELECT role, full_name FROM admin_users WHERE id = ?");
            $check->execute([$user_id]);
            $target_user = $check->fetch(PDO::FETCH_ASSOC);
            
            if ($target_user && $target_user['role'] == 'superadmin') {
                $superadmin_count = $db->query("SELECT COUNT(*) FROM admin_users WHERE role = 'superadmin'")->fetchColumn();
                if ($superadmin_count <= 1) {
                    $_SESSION['error'] = 'Cannot delete the last superadmin account';
                    redirect('users.php');
                }
            }
            
            $stmt = $db->prepare("DELETE FROM admin_users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, 'delete', 'user', ?, ?, NOW())
            ");
            $stmt->execute([$user['id'], $user_id, "Deleted user: " . ($target_user['full_name'] ?? 'Unknown')]);
            
            $_SESSION['success'] = 'User deleted successfully';
        } catch (PDOException $e) {
            error_log("Error deleting user: " . $e->getMessage());
            $_SESSION['error'] = 'Error deleting user';
        }
    }
    redirect('users.php');
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['user_ids'])) {
    $action = $_POST['bulk_action'];
    $user_ids = explode(',', $_POST['user_ids']);
    $user_ids = array_filter(array_map('intval', $user_ids));
    
    if (!empty($user_ids) && !empty($action)) {
        try {
            $db = db();
            
            // Remove current user from bulk actions
            $user_ids = array_filter($user_ids, function($id) use ($user) {
                return $id != $user['id'];
            });
            
            if (empty($user_ids)) {
                $_SESSION['error'] = 'Cannot perform actions on your own account';
                redirect('users.php');
            }
            
            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
            
            if ($action === 'delete') {
                // Check for last superadmin
                $stmt = $db->prepare("SELECT role FROM admin_users WHERE id IN ($placeholders)");
                $stmt->execute($user_ids);
                $target_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $superadmin_count = $db->query("SELECT COUNT(*) FROM admin_users WHERE role = 'superadmin'")->fetchColumn();
                $deleting_superadmins = 0;
                
                foreach ($target_users as $target) {
                    if ($target['role'] == 'superadmin') {
                        $deleting_superadmins++;
                    }
                }
                
                if ($superadmin_count - $deleting_superadmins < 1) {
                    $_SESSION['error'] = 'Cannot delete all superadmin accounts';
                    redirect('users.php');
                }
                
                $stmt = $db->prepare("DELETE FROM admin_users WHERE id IN ($placeholders)");
                $stmt->execute($user_ids);
                $_SESSION['success'] = count($user_ids) . ' users deleted successfully';
                
                // Log bulk activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                    VALUES (?, 'bulk_delete', 'user', 0, ?, NOW())
                ");
                $stmt->execute([$user['id'], "Bulk deleted " . count($user_ids) . " users"]);
                
            } elseif ($action === 'active' || $action === 'inactive') {
                $stmt = $db->prepare("UPDATE admin_users SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$action], $user_ids));
                $_SESSION['success'] = count($user_ids) . ' users updated successfully';
                
            } elseif ($action === 'superadmin' || $action === 'editor' || $action === 'viewer') {
                $stmt = $db->prepare("UPDATE admin_users SET role = ?, updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$action], $user_ids));
                $_SESSION['success'] = count($user_ids) . ' users role updated to ' . ucfirst($action);
            }
        } catch (PDOException $e) {
            error_log("Bulk action error: " . $e->getMessage());
            $_SESSION['error'] = 'Error performing bulk action';
        }
    }
    redirect('users.php');
}

// Get all users
try {
    $db = db();
    
    $stmt = $db->query("
        SELECT *, 
               (SELECT COUNT(*) FROM admin_users) as total_users,
               (SELECT COUNT(*) FROM admin_users WHERE status = 'active') as active_users,
               (SELECT COUNT(*) FROM admin_users WHERE role = 'superadmin') as superadmin_count,
               (SELECT COUNT(*) FROM admin_users WHERE role = 'editor') as editor_count,
               (SELECT COUNT(*) FROM admin_users WHERE role = 'viewer') as viewer_count
        FROM admin_users 
        ORDER BY 
            CASE 
                WHEN role = 'superadmin' THEN 1
                WHEN role = 'editor' THEN 2
                ELSE 3
            END,
            username ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics
    $total_users = count($users);
    $active_users = $db->query("SELECT COUNT(*) FROM admin_users WHERE status = 'active'")->fetchColumn() ?: 0;
    $inactive_users = $total_users - $active_users;
    $superadmin_count = $db->query("SELECT COUNT(*) FROM admin_users WHERE role = 'superadmin'")->fetchColumn() ?: 0;
    $editor_count = $db->query("SELECT COUNT(*) FROM admin_users WHERE role = 'editor'")->fetchColumn() ?: 0;
    $viewer_count = $db->query("SELECT COUNT(*) FROM admin_users WHERE role = 'viewer'")->fetchColumn() ?: 0;
    
    // Today's logins
    $today_logins = $db->query("
        SELECT COUNT(*) FROM admin_users 
        WHERE DATE(last_login) = CURDATE()
    ")->fetchColumn() ?: 0;
    
    // Login activity for chart
    $login_activity = $db->query("
        SELECT 
            DATE(last_login) as login_date,
            COUNT(*) as login_count
        FROM admin_users 
        WHERE last_login IS NOT NULL
        AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(last_login)
        ORDER BY login_date ASC
        LIMIT 30
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent activity logs
    $recent_activity = $db->query("
        SELECT al.*, au.full_name as user_name
        FROM activity_logs al
        LEFT JOIN admin_users au ON al.user_id = au.id
        WHERE al.entity_type = 'user'
        ORDER BY al.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
    $total_users = $active_users = $inactive_users = 0;
    $superadmin_count = $editor_count = $viewer_count = 0;
    $today_logins = 0;
    $login_activity = [];
    $recent_activity = [];
}

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Set page title
$page_title = 'User Management';

// Breadcrumbs
$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => '#', 'title' => 'Users']
];

// Page specific CSS
$page_css = ['users.css'];

// Include admin header
require_once '../includes/admin-header.php';
?>

<style>
/* ============================================
   USER MANAGEMENT - FULLY RESPONSIVE
   Mobile-first approach with breakpoints
============================================ */

/* Base styles (mobile first) */
.users-stats-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
    margin-bottom: 25px;
}

@media (min-width: 576px) {
    .users-stats-grid {
        gap: 20px;
    }
}

@media (min-width: 768px) {
    .users-stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
}

@media (min-width: 992px) {
    .users-stats-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 25px;
    }
}

.users-stat-card {
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

@media (min-width: 768px) {
    .users-stat-card {
        padding: 25px;
        gap: 20px;
    }
}

.users-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 20px rgba(0,0,0,0.05);
    border-color: var(--gold);
}

.users-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

@media (min-width: 768px) {
    .users-stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        font-size: 28px;
    }
}

.users-stat-info h4 {
    font-size: 13px;
    color: var(--gray-600);
    margin-bottom: 5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

@media (min-width: 768px) {
    .users-stat-info h4 {
        font-size: 14px;
        margin-bottom: 8px;
    }
}

.users-stat-info .number {
    font-size: 24px;
    font-weight: 700;
    color: var(--navy);
    line-height: 1;
}

@media (min-width: 768px) {
    .users-stat-info .number {
        font-size: 28px;
    }
}

@media (min-width: 992px) {
    .users-stat-info .number {
        font-size: 32px;
    }
}

/* Dashboard grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

@media (min-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
}

@media (min-width: 992px) {
    .dashboard-grid {
        gap: 25px;
        margin-bottom: 30px;
    }
}

.role-distribution {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    margin-top: 15px;
}

@media (min-width: 576px) {
    .role-distribution {
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }
}

@media (min-width: 768px) {
    .role-distribution {
        margin-top: 20px;
    }
}

.role-item {
    text-align: center;
    padding: 15px;
    background: var(--gray-100);
    border-radius: 12px;
    transition: all 0.3s ease;
}

@media (min-width: 768px) {
    .role-item {
        padding: 20px;
    }
}

.role-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.05);
}

.role-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    font-size: 20px;
}

@media (min-width: 768px) {
    .role-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        margin: 0 auto 12px;
        font-size: 24px;
    }
}

.role-count {
    font-size: 20px;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 3px;
}

@media (min-width: 768px) {
    .role-count {
        font-size: 24px;
        margin-bottom: 5px;
    }
}

.role-label {
    font-size: 11px;
    color: var(--gray-600);
}

@media (min-width: 768px) {
    .role-label {
        font-size: 13px;
    }
}

.permission-tags {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

@media (min-width: 576px) {
    .permission-tags {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 15px;
    }
}

.permission-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Activity feed */
.activity-feed {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--gray-200);
    height: auto;
}

@media (min-width: 768px) {
    .activity-feed {
        padding: 25px;
    }
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--gray-200);
}

@media (min-width: 768px) {
    .activity-item {
        gap: 15px;
        padding: 15px 0;
    }
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(255,184,28,0.1);
    color: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}

@media (min-width: 768px) {
    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        font-size: 16px;
    }
}

.activity-details {
    flex: 1;
}

.activity-action {
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 4px;
    font-size: 13px;
}

@media (min-width: 768px) {
    .activity-action {
        font-size: 14px;
        margin-bottom: 5px;
    }
}

.activity-meta {
    font-size: 11px;
    color: var(--gray-600);
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

@media (min-width: 768px) {
    .activity-meta {
        font-size: 12px;
        gap: 15px;
    }
}

/* Bulk actions */
.bulk-actions {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
    background: white;
    padding: 15px;
    border-radius: 12px;
    border: 1px solid var(--gray-200);
}

@media (min-width: 576px) {
    .bulk-actions {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
    }
}

.bulk-actions-left {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
}

@media (min-width: 576px) {
    .bulk-actions-left {
        flex-direction: row;
        align-items: center;
        gap: 15px;
        width: auto;
    }
}

.bulk-actions select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 14px;
    color: var(--gray-900);
    background: white;
    cursor: pointer;
}

@media (min-width: 576px) {
    .bulk-actions select {
        width: 200px;
    }
}

.btn-apply {
    width: 100%;
    padding: 10px 16px;
    background: var(--navy);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

@media (min-width: 576px) {
    .btn-apply {
        width: auto;
        padding: 10px 20px;
    }
}

.btn-apply:hover:not(:disabled) {
    background: var(--navy-light);
    transform: translateY(-1px);
}

.btn-apply:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Table container - horizontal scroll on mobile */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 0 -20px;
    padding: 0 20px;
    width: calc(100% + 40px);
}

@media (min-width: 768px) {
    .table-responsive {
        margin: 0;
        padding: 0;
        width: 100%;
    }
}

.users-table {
    min-width: 900px;
    width: 100%;
    border-collapse: collapse;
}

@media (min-width: 1200px) {
    .users-table {
        min-width: 1000px;
    }
}

.users-table th {
    text-align: left;
    padding: 15px 16px;
    background: var(--gray-100);
    color: var(--gray-700);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--gray-200);
    white-space: nowrap;
}

@media (min-width: 768px) {
    .users-table th {
        padding: 18px 20px;
        font-size: 13px;
    }
}

.users-table td {
    padding: 15px 16px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-800);
    vertical-align: middle;
}

@media (min-width: 768px) {
    .users-table td {
        padding: 18px 20px;
    }
}

.users-table tr:hover {
    background: var(--gray-100);
}

/* User info cell - stacks on mobile */
.user-info-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

@media (max-width: 575px) {
    .user-info-cell {
        flex-direction: column;
        align-items: flex-start;
    }
}

.user-avatar-small {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(255,184,28,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 18px;
    flex-shrink: 0;
}

@media (min-width: 768px) {
    .user-avatar-small {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        font-size: 20px;
    }
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-name {
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 4px;
    font-size: 14px;
}

@media (min-width: 768px) {
    .user-name {
        font-size: 15px;
    }
}

.user-username {
    font-size: 11px;
    color: var(--gray-600);
    word-break: break-word;
}

@media (min-width: 768px) {
    .user-username {
        font-size: 12px;
    }
}

/* Badges */
.role-badge, .status-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    gap: 4px;
    white-space: nowrap;
}

@media (min-width: 768px) {
    .role-badge, .status-badge {
        padding: 6px 14px;
        font-size: 12px;
        gap: 6px;
    }
}

.role-badge.superadmin {
    background: rgba(255,184,28,0.15);
    color: var(--navy);
}

.role-badge.editor {
    background: rgba(40,167,69,0.1);
    color: #28a745;
}

.role-badge.viewer {
    background: rgba(23,162,184,0.1);
    color: #17a2b8;
}

.status-badge.active {
    background: rgba(40,167,69,0.1);
    color: #28a745;
}

.status-badge.inactive {
    background: rgba(108,117,125,0.1);
    color: var(--gray-600);
}

/* Action buttons */
.user-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

@media (min-width: 768px) {
    .user-actions {
        gap: 8px;
    }
}

.btn-user-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    background: white;
    border: 1px solid var(--gray-200);
    transition: all 0.2s ease;
    text-decoration: none;
}

@media (min-width: 768px) {
    .btn-user-action {
        width: 36px;
        height: 36px;
        border-radius: 10px;
    }
}

.btn-user-action:hover {
    background: var(--gold);
    color: var(--navy);
    border-color: var(--gold);
    transform: translateY(-2px);
}

.btn-user-action.delete:hover {
    background: #dc3545;
    color: white;
    border-color: #dc3545;
}

/* Chart container */
.chart-container-small {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-top: 25px;
    border: 1px solid var(--gray-200);
}

@media (min-width: 768px) {
    .chart-container-small {
        padding: 25px;
    }
}

.chart-container-small h3 {
    font-size: 16px;
    margin-bottom: 15px;
}

@media (min-width: 768px) {
    .chart-container-small h3 {
        font-size: 18px;
        margin-bottom: 20px;
    }
}

/* Modal responsive */
.user-modal-content {
    background: white;
    border-radius: 20px;
    padding: 25px 20px;
    max-width: 95%;
    width: 400px;
    max-height: 90vh;
    overflow-y: auto;
}

@media (min-width: 576px) {
    .user-modal-content {
        padding: 30px;
        width: 450px;
    }
}

@media (min-width: 768px) {
    .user-modal-content {
        padding: 35px;
        width: 500px;
    }
}

/* Page header responsive */
.page-header {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 25px;
}

@media (min-width: 576px) {
    .page-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.page-header-left h1 {
    font-size: 24px;
    margin-bottom: 5px;
}

@media (min-width: 768px) {
    .page-header-left h1 {
        font-size: 28px;
    }
}

.page-header-right {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
}

@media (min-width: 576px) {
    .page-header-right {
        flex-direction: row;
        width: auto;
    }
}

.page-header-right .btn {
    width: 100%;
    justify-content: center;
}

@media (min-width: 576px) {
    .page-header-right .btn {
        width: auto;
    }
}

/* Last login info */
.last-login {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.last-login-time {
    font-weight: 500;
    font-size: 13px;
    white-space: nowrap;
}

.last-login-ip {
    font-size: 11px;
    color: var(--gray-600);
    white-space: nowrap;
}

/* Checkbox */
.user-checkbox {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

@media (min-width: 768px) {
    .user-checkbox {
        width: 18px;
        height: 18px;
    }
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<!-- Toast Notifications -->
<?php if ($success): ?>
    <div class="toast-notification" id="successToast">
        <div style="background: #D4EDDA; color: #155724; padding: 12px 20px; border-radius: 8px; border-left: 4px solid #28a745; display: flex; align-items: center; gap: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
            <i class="fas fa-check-circle"></i> 
            <span style="flex: 1; font-size: 14px;"><?php echo htmlspecialchars($success); ?></span>
            <button onclick="this.closest('.toast-notification').remove()" style="background: none; border: none; color: #155724; cursor: pointer; padding: 5px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="toast-notification" id="errorToast">
        <div style="background: #F8D7DA; color: #721C24; padding: 12px 20px; border-radius: 8px; border-left: 4px solid #dc3545; display: flex; align-items: center; gap: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
            <i class="fas fa-exclamation-circle"></i> 
            <span style="flex: 1; font-size: 14px;"><?php echo htmlspecialchars($error); ?></span>
            <button onclick="this.closest('.toast-notification').remove()" style="background: none; border: none; color: #721C24; cursor: pointer; padding: 5px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1 style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
            <i class="fas fa-users-cog" style="color: var(--gold);"></i>
            User Management
        </h1>
        <div style="color: var(--gray-600); font-size: 14px;">
            <i class="fas fa-user-shield"></i> 
            Manage system users and permissions
        </div>
    </div>
    
    <div class="page-header-right">
        <button class="btn-primary" onclick="openAddModal()">
            <i class="fas fa-user-plus"></i> Add New User
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="users-stats-grid">
    <div class="users-stat-card">
        <div class="users-stat-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-users"></i>
        </div>
        <div class="users-stat-info">
            <h4>Total Users</h4>
            <span class="number"><?php echo $total_users; ?></span>
        </div>
    </div>
    
    <div class="users-stat-card">
        <div class="users-stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="users-stat-info">
            <h4>Active</h4>
            <span class="number"><?php echo $active_users; ?></span>
        </div>
    </div>
    
    <div class="users-stat-card">
        <div class="users-stat-icon" style="background: rgba(108,117,125,0.1); color: var(--gray-600);">
            <i class="fas fa-minus-circle"></i>
        </div>
        <div class="users-stat-info">
            <h4>Inactive</h4>
            <span class="number"><?php echo $inactive_users; ?></span>
        </div>
    </div>
    
    <div class="users-stat-card">
        <div class="users-stat-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="users-stat-info">
            <h4>Logged In Today</h4>
            <span class="number"><?php echo $today_logins; ?></span>
        </div>
    </div>
</div>

<!-- Role Distribution and Activity Feed -->
<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie" style="color: var(--gold);"></i> Role Distribution</h3>
        </div>
        <div class="card-body">
            <div class="role-distribution">
                <div class="role-item">
                    <div class="role-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="role-count"><?php echo $superadmin_count; ?></div>
                    <div class="role-label">Superadmins</div>
                </div>
                <div class="role-item">
                    <div class="role-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="role-count"><?php echo $editor_count; ?></div>
                    <div class="role-label">Editors</div>
                </div>
                <div class="role-item">
                    <div class="role-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="role-count"><?php echo $viewer_count; ?></div>
                    <div class="role-label">Viewers</div>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--gray-200);">
                <div class="permission-tags">
                    <div class="permission-item">
                        <span class="role-badge superadmin"><i class="fas fa-crown"></i> Superadmin</span>
                        <span style="font-size: 13px; color: var(--gray-600);">Full access</span>
                    </div>
                    <div class="permission-item">
                        <span class="role-badge editor"><i class="fas fa-edit"></i> Editor</span>
                        <span style="font-size: 13px; color: var(--gray-600);">Manage content</span>
                    </div>
                    <div class="permission-item">
                        <span class="role-badge viewer"><i class="fas fa-eye"></i> Viewer</span>
                        <span style="font-size: 13px; color: var(--gray-600);">Read only</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="activity-feed">
        <h3 style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; font-size: 18px;">
            <i class="fas fa-history" style="color: var(--gold);"></i>
            Recent Activity
        </h3>
        
        <?php if (!empty($recent_activity)): ?>
            <?php foreach ($recent_activity as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-<?php 
                            echo $activity['action'] == 'create' ? 'plus' : 
                                ($activity['action'] == 'update' ? 'edit' : 
                                ($activity['action'] == 'delete' ? 'trash' : 
                                ($activity['action'] == 'password_reset' ? 'key' : 'circle'))); 
                        ?>"></i>
                    </div>
                    <div class="activity-details">
                        <div class="activity-action">
                            <?php echo htmlspecialchars($activity['details']); ?>
                        </div>
                        <div class="activity-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo timeAgo($activity['created_at']); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 30px; color: var(--gray-500);">
                <i class="fas fa-history" style="font-size: 48px; margin-bottom: 15px;"></i>
                <p>No recent activity</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bulk Actions -->
<div class="bulk-actions">
    <div class="bulk-actions-left">
        <div style="display: flex; align-items: center; gap: 8px;">
            <input type="checkbox" id="selectAll" style="width: 18px; height: 18px; cursor: pointer;">
            <label for="selectAll" style="font-size: 14px;">Select All</label>
        </div>
        
        <select id="bulkActionSelect" disabled>
            <option value="">Bulk Actions</option>
            <option value="active">Set Active</option>
            <option value="inactive">Set Inactive</option>
            <option value="superadmin">Change to Superadmin</option>
            <option value="editor">Change to Editor</option>
            <option value="viewer">Change to Viewer</option>
            <option value="delete">Delete Selected</option>
        </select>
        
        <button id="applyBulkAction" class="btn-apply" disabled>Apply</button>
    </div>
    
    <div style="font-size: 14px; color: var(--gray-600);">
        <i class="fas fa-users"></i> Total: <strong><?php echo $total_users; ?></strong> users
    </div>
</div>

<!-- Users Table - with horizontal scroll on mobile -->
<div class="table-responsive">
    <table class="users-table" id="usersTable">
        <thead>
            <tr>
                <th width="40">
                    <input type="checkbox" id="selectAllTable" style="width: 18px; height: 18px; cursor: pointer;">
                </th>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Created</th>
                <th width="120">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr data-id="<?php echo $u['id']; ?>">
                    <td>
                        <?php if ($u['id'] != $user['id']): ?>
                            <input type="checkbox" class="user-checkbox" value="<?php echo $u['id']; ?>" style="width: 18px; height: 18px; cursor: pointer;">
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="user-info-cell">
                            <div class="user-avatar-small">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="user-details">
                                <span class="user-name"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                <span class="user-username">@<?php echo htmlspecialchars($u['username']); ?> • <?php echo htmlspecialchars($u['email']); ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="role-badge <?php echo $u['role']; ?>">
                            <i class="fas fa-<?php 
                                echo $u['role'] == 'superadmin' ? 'crown' : 
                                    ($u['role'] == 'editor' ? 'edit' : 'eye'); 
                            ?>"></i>
                            <?php echo ucfirst($u['role']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $u['status'] ?? 'active'; ?>">
                            <i class="fas fa-<?php echo ($u['status'] ?? 'active') == 'active' ? 'check-circle' : 'minus-circle'; ?>"></i>
                            <?php echo ucfirst($u['status'] ?? 'active'); ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($u['last_login'])): ?>
                            <div class="last-login">
                                <span class="last-login-time"><?php echo formatDate($u['last_login'], 'M d, Y'); ?></span>
                                <span class="last-login-ip">
                                    <i class="fas fa-clock"></i> <?php echo formatDate($u['last_login'], 'H:i'); ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <span style="color: var(--gray-500);">Never</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-size: 13px;"><?php echo formatDate($u['created_at'], 'M d, Y'); ?></span>
                    </td>
                    <td>
                        <div class="user-actions">
                            <?php if ($u['id'] != $user['id']): ?>
                                <a href="#" onclick="openEditModal(<?php echo $u['id']; ?>); return false;" class="btn-user-action" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" onclick="openPasswordModal(<?php echo $u['id']; ?>); return false;" class="btn-user-action" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </a>
                                <a href="?delete=<?php echo $u['id']; ?>" class="btn-user-action delete" title="Delete User" 
                                   onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php else: ?>
                                <span style="color: var(--gray-500); font-size: 12px; padding: 0 10px;">Current User</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Login Activity Chart -->
<?php if (!empty($login_activity)): ?>
<div class="chart-container-small">
    <h3 style="display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-chart-line" style="color: var(--gold);"></i>
        Login Activity (Last 30 Days)
    </h3>
    <canvas id="loginChart" style="width: 100%; height: 200px;"></canvas>
</div>
<?php endif; ?>

<!-- Add User Modal -->
<div id="addUserModal" class="user-modal">
    <div class="user-modal-content">
        <span class="user-modal-close" onclick="closeAddModal()">&times;</span>
        
        <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 22px;">
            <i class="fas fa-user-plus" style="color: var(--gold);"></i>
            Add New User
        </h2>
        
        <form method="POST" action="" id="addUserForm">
            <input type="hidden" name="add_user" value="1">
            
            <div class="form-group">
                <label>Full Name <span style="color: #dc3545;">*</span></label>
                <input type="text" name="full_name" class="form-control" placeholder="e.g., John Doe" required>
            </div>
            
            <div class="form-group">
                <label>Username <span style="color: #dc3545;">*</span></label>
                <input type="text" name="username" class="form-control" placeholder="e.g., johndoe" required>
            </div>
            
            <div class="form-group">
                <label>Email <span style="color: #dc3545;">*</span></label>
                <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
            </div>
            
            <div class="form-group">
                <label>Password <span style="color: #dc3545;">*</span></label>
                <input type="password" name="password" id="add_password" class="form-control" placeholder="••••••••" required onkeyup="checkPasswordStrength()">
                
                <div class="password-requirements" id="passwordRequirements">
                    <p style="font-weight: 600; margin-bottom: 8px;">Password Requirements:</p>
                    <div class="requirement" id="req-length">
                        <i class="fas fa-circle"></i> At least 8 characters
                    </div>
                    <div class="requirement" id="req-uppercase">
                        <i class="fas fa-circle"></i> At least 1 uppercase letter
                    </div>
                    <div class="requirement" id="req-lowercase">
                        <i class="fas fa-circle"></i> At least 1 lowercase letter
                    </div>
                    <div class="requirement" id="req-number">
                        <i class="fas fa-circle"></i> At least 1 number
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="editor">Editor</option>
                    <option value="viewer">Viewer</option>
                    <option value="superadmin">Superadmin</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px; flex-direction: column;">
                @media (min-width: 576px) {
                    .form-actions {
                        flex-direction: row;
                    }
                }
                <button type="submit" class="btn-primary" style="flex: 1; padding: 12px;">
                    <i class="fas fa-save"></i> Create User
                </button>
                <button type="button" class="btn-secondary" onclick="closeAddModal()" style="flex: 1; padding: 12px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="user-modal">
    <div class="user-modal-content">
        <span class="user-modal-close" onclick="closeEditModal()">&times;</span>
        
        <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 22px;">
            <i class="fas fa-user-edit" style="color: var(--gold);"></i>
            Edit User
        </h2>
        
        <form method="POST" action="" id="editUserForm">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="form-group">
                <label>Full Name <span style="color: #dc3545;">*</span></label>
                <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="edit_username" class="form-control" readonly disabled>
                <small style="color: var(--gray-600);">Username cannot be changed</small>
            </div>
            
            <div class="form-group">
                <label>Email <span style="color: #dc3545;">*</span></label>
                <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="edit_role" class="form-control">
                    <option value="editor">Editor</option>
                    <option value="viewer">Viewer</option>
                    <option value="superadmin">Superadmin</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px; flex-direction: column;">
                <button type="submit" class="btn-primary" style="flex: 1; padding: 12px;">
                    <i class="fas fa-save"></i> Update User
                </button>
                <button type="button" class="btn-secondary" onclick="closeEditModal()" style="flex: 1; padding: 12px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="passwordModal" class="user-modal">
    <div class="user-modal-content">
        <span class="user-modal-close" onclick="closePasswordModal()">&times;</span>
        
        <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 22px;">
            <i class="fas fa-key" style="color: var(--gold);"></i>
            Reset Password
        </h2>
        
        <form method="POST" action="" id="passwordForm">
            <input type="hidden" name="reset_password" value="1">
            <input type="hidden" name="user_id" id="password_user_id">
            
            <div class="form-group">
                <label>New Password <span style="color: #dc3545;">*</span></label>
                <input type="password" name="new_password" id="new_password" class="form-control" required onkeyup="checkNewPasswordStrength()">
            </div>
            
            <div class="form-group">
                <label>Confirm Password <span style="color: #dc3545;">*</span></label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required onkeyup="checkPasswordMatch()">
            </div>
            
            <div id="passwordMatchMessage" style="font-size: 13px; margin-bottom: 15px; display: none;"></div>
            
            <div class="password-requirements">
                <p style="font-weight: 600; margin-bottom: 8px;">Password Requirements:</p>
                <div class="requirement" id="new-req-length">
                    <i class="fas fa-circle"></i> At least 8 characters
                </div>
                <div class="requirement" id="new-req-match">
                    <i class="fas fa-circle"></i> Passwords match
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px; flex-direction: column;">
                <button type="submit" class="btn-primary" style="flex: 1; padding: 12px;">
                    <i class="fas fa-save"></i> Reset Password
                </button>
                <button type="button" class="btn-secondary" onclick="closePasswordModal()" style="flex: 1; padding: 12px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// USER MANAGEMENT - FIXED JAVASCRIPT
// All functions properly defined and event listeners attached
// ============================================

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    console.log('User management page loaded - JavaScript initialized');
    
    // ============================================
    // DOM ELEMENT REFERENCES
    // ============================================
    const addModal = document.getElementById('addUserModal');
    const editModal = document.getElementById('editUserModal');
    const passwordModal = document.getElementById('passwordModal');
    const selectAll = document.getElementById('selectAll');
    const selectAllTable = document.getElementById('selectAllTable');
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const applyButton = document.getElementById('applyBulkAction');
    
    console.log('Modal elements:', { addModal, editModal, passwordModal });
    
    // ============================================
    // MODAL FUNCTIONS - Attached to window for onclick access
    // ============================================
    
    window.openAddModal = function() {
        console.log('Opening add user modal');
        if (addModal) {
            addModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            console.error('Add modal not found');
        }
    };
    
    window.closeAddModal = function() {
        console.log('Closing add user modal');
        if (addModal) {
            addModal.classList.remove('active');
            document.body.style.overflow = '';
            
            // Reset form
            const form = document.getElementById('addUserForm');
            if (form) form.reset();
            
            // Reset password requirements
            const requirements = document.querySelectorAll('#passwordRequirements .requirement');
            requirements.forEach(req => {
                req.classList.remove('met');
                req.innerHTML = req.innerHTML.replace(/<i class="fas fa-check-circle"><\/i>/, '<i class="fas fa-circle"></i>');
            });
        }
    };
    
    window.openEditModal = function(id) {
        console.log('Opening edit modal for user ID:', id);
        if (!id) {
            console.error('No user ID provided');
            return;
        }
        
        // Show loading state
        if (editModal) {
            editModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // You might want to show a loading spinner here
        }
        
        // Fetch user data
        fetch(`ajax-get-user.php?id=${id}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('User data received:', data);
                if (data.success) {
                    const user = data.user;
                    
                    // Populate form fields
                    document.getElementById('edit_user_id').value = user.id || '';
                    document.getElementById('edit_full_name').value = user.full_name || '';
                    document.getElementById('edit_username').value = user.username || '';
                    document.getElementById('edit_email').value = user.email || '';
                    
                    const roleSelect = document.getElementById('edit_role');
                    if (roleSelect) roleSelect.value = user.role || 'editor';
                    
                    const statusSelect = document.getElementById('edit_status');
                    if (statusSelect) statusSelect.value = user.status || 'active';
                    
                    // Ensure modal stays active
                    editModal.classList.add('active');
                } else {
                    console.error('Failed to load user data:', data.error);
                    alert('Failed to load user data. Please try again.');
                    window.closeEditModal();
                }
            })
            .catch(error => {
                console.error('Error fetching user:', error);
                alert('Error loading user data. Please check console for details.');
                window.closeEditModal();
            });
    };
    
    window.closeEditModal = function() {
        console.log('Closing edit user modal');
        if (editModal) {
            editModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };
    
    window.openPasswordModal = function(id) {
        console.log('Opening password modal for user ID:', id);
        if (passwordModal) {
            document.getElementById('password_user_id').value = id;
            passwordModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Reset form
            document.getElementById('passwordForm').reset();
            document.getElementById('passwordMatchMessage').style.display = 'none';
            
            // Reset password requirements
            const lengthReq = document.getElementById('new-req-length');
            const matchReq = document.getElementById('new-req-match');
            if (lengthReq) {
                lengthReq.classList.remove('met');
                lengthReq.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
            }
            if (matchReq) {
                matchReq.classList.remove('met');
                matchReq.innerHTML = '<i class="fas fa-circle"></i> Passwords match';
            }
        }
    };
    
    window.closePasswordModal = function() {
        console.log('Closing password modal');
        if (passwordModal) {
            passwordModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };
    
    // ============================================
    // PASSWORD VALIDATION FUNCTIONS
    // ============================================
    
    window.checkPasswordStrength = function() {
        const password = document.getElementById('add_password');
        if (!password) return;
        
        const value = password.value;
        
        // Length check
        const lengthReq = document.getElementById('req-length');
        if (lengthReq) {
            if (value.length >= 8) {
                lengthReq.classList.add('met');
                lengthReq.innerHTML = '<i class="fas fa-check-circle"></i> At least 8 characters ✓';
            } else {
                lengthReq.classList.remove('met');
                lengthReq.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
            }
        }
        
        // Uppercase check
        const upperReq = document.getElementById('req-uppercase');
        if (upperReq) {
            if (/[A-Z]/.test(value)) {
                upperReq.classList.add('met');
                upperReq.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 uppercase letter ✓';
            } else {
                upperReq.classList.remove('met');
                upperReq.innerHTML = '<i class="fas fa-circle"></i> At least 1 uppercase letter';
            }
        }
        
        // Lowercase check
        const lowerReq = document.getElementById('req-lowercase');
        if (lowerReq) {
            if (/[a-z]/.test(value)) {
                lowerReq.classList.add('met');
                lowerReq.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 lowercase letter ✓';
            } else {
                lowerReq.classList.remove('met');
                lowerReq.innerHTML = '<i class="fas fa-circle"></i> At least 1 lowercase letter';
            }
        }
        
        // Number check
        const numReq = document.getElementById('req-number');
        if (numReq) {
            if (/[0-9]/.test(value)) {
                numReq.classList.add('met');
                numReq.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 number ✓';
            } else {
                numReq.classList.remove('met');
                numReq.innerHTML = '<i class="fas fa-circle"></i> At least 1 number';
            }
        }
    };
    
    window.checkNewPasswordStrength = function() {
        const password = document.getElementById('new_password');
        if (!password) return;
        
        const value = password.value;
        
        const lengthReq = document.getElementById('new-req-length');
        if (lengthReq) {
            if (value.length >= 8) {
                lengthReq.classList.add('met');
                lengthReq.innerHTML = '<i class="fas fa-check-circle"></i> At least 8 characters ✓';
            } else {
                lengthReq.classList.remove('met');
                lengthReq.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
            }
        }
        
        window.checkPasswordMatch();
    };
    
    window.checkPasswordMatch = function() {
        const password = document.getElementById('new_password');
        const confirm = document.getElementById('confirm_password');
        
        if (!password || !confirm) return;
        
        const matchReq = document.getElementById('new-req-match');
        const matchMessage = document.getElementById('passwordMatchMessage');
        
        if (confirm.value.length > 0) {
            if (password.value === confirm.value) {
                if (matchReq) {
                    matchReq.classList.add('met');
                    matchReq.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match ✓';
                }
                if (matchMessage) matchMessage.style.display = 'none';
            } else {
                if (matchReq) {
                    matchReq.classList.remove('met');
                    matchReq.innerHTML = '<i class="fas fa-circle"></i> Passwords match';
                }
                if (matchMessage) {
                    matchMessage.style.display = 'block';
                    matchMessage.innerHTML = '<span style="color: #dc3545;"><i class="fas fa-exclamation-circle"></i> Passwords do not match</span>';
                }
            }
        }
    };
    
    // ============================================
    // SELECT ALL FUNCTIONALITY
    // ============================================
    
    // Get all user checkboxes
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    console.log('User checkboxes found:', userCheckboxes.length);
    
    function updateBulkActionState() {
        const checked = document.querySelectorAll('.user-checkbox:checked').length;
        console.log('Checked checkboxes:', checked);
        
        if (bulkActionSelect) bulkActionSelect.disabled = checked === 0;
        if (applyButton) applyButton.disabled = checked === 0;
    }
    
    function updateSelectAllState() {
        const checked = document.querySelectorAll('.user-checkbox:checked').length;
        const total = document.querySelectorAll('.user-checkbox').length;
        
        if (selectAll) {
            selectAll.checked = checked === total && total > 0;
            selectAll.indeterminate = checked > 0 && checked < total;
        }
        
        if (selectAllTable) {
            selectAllTable.checked = checked === total && total > 0;
            selectAllTable.indeterminate = checked > 0 && checked < total;
        }
    }
    
    // Select all checkboxes
    if (selectAll) {
        selectAll.addEventListener('change', function(e) {
            console.log('Select all changed:', e.target.checked);
            userCheckboxes.forEach(cb => {
                if (cb) cb.checked = e.target.checked;
            });
            updateBulkActionState();
            updateSelectAllState();
        });
    }
    
    if (selectAllTable) {
        selectAllTable.addEventListener('change', function(e) {
            console.log('Select all (table) changed:', e.target.checked);
            userCheckboxes.forEach(cb => {
                if (cb) cb.checked = e.target.checked;
            });
            updateBulkActionState();
            updateSelectAllState();
        });
    }
    
    // Individual checkbox change
    userCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            console.log('Checkbox changed');
            updateBulkActionState();
            updateSelectAllState();
        });
    });
    
    // ============================================
    // BULK ACTION APPLY
    // ============================================
    
    if (applyButton) {
        applyButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const action = bulkActionSelect ? bulkActionSelect.value : '';
            const checkedIds = [];
            
            document.querySelectorAll('.user-checkbox:checked').forEach(cb => {
                checkedIds.push(cb.value);
            });
            
            console.log('Bulk action:', action, 'Selected IDs:', checkedIds);
            
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            if (checkedIds.length === 0) {
                alert('Please select at least one user');
                return;
            }
            
            let confirmMessage = '';
            if (action === 'delete') {
                confirmMessage = `Are you sure you want to delete ${checkedIds.length} user(s)? This action cannot be undone.`;
            } else if (action === 'active' || action === 'inactive') {
                confirmMessage = `Are you sure you want to set ${checkedIds.length} user(s) as ${action}?`;
            } else {
                confirmMessage = `Are you sure you want to change role of ${checkedIds.length} user(s) to ${action}?`;
            }
            
            if (confirm(confirmMessage)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'users.php';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'bulk_action';
                actionInput.value = action;
                
                const idsInput = document.createElement('input');
                idsInput.type = 'hidden';
                idsInput.name = 'user_ids';
                idsInput.value = checkedIds.join(',');
                
                form.appendChild(actionInput);
                form.appendChild(idsInput);
                document.body.appendChild(form);
                console.log('Submitting bulk action form');
                form.submit();
            }
        });
    }
    
    // ============================================
    // LOGIN ACTIVITY CHART
    // ============================================
    
    <?php if (!empty($login_activity)): ?>
    const chartCanvas = document.getElementById('loginChart');
    
    if (chartCanvas && typeof Chart !== 'undefined') {
        console.log('Initializing login activity chart');
        
        const dates = <?php 
            $dates = array_column($login_activity, 'login_date');
            echo json_encode($dates);
        ?>;
        
        const counts = <?php 
            $counts = array_column($login_activity, 'login_count');
            echo json_encode($counts);
        ?>;
        
        console.log('Chart data:', { dates, counts });
        
        try {
            new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Logins',
                        data: counts,
                        borderColor: '#FFB81C',
                        backgroundColor: 'rgba(255,184,28,0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#FFB81C',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
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
                            padding: 12
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
    } else {
        console.log('Chart canvas or Chart.js not available');
    }
    <?php endif; ?>
    
    // ============================================
    // CLOSE MODALS WHEN CLICKING OUTSIDE
    // ============================================
    
    window.addEventListener('click', function(e) {
        if (e.target === addModal) {
            window.closeAddModal();
        }
        if (e.target === editModal) {
            window.closeEditModal();
        }
        if (e.target === passwordModal) {
            window.closePasswordModal();
        }
    });
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    
    document.addEventListener('keydown', function(e) {
        // Ctrl+N - Add new user
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            window.openAddModal();
        }
        
        // Escape - Close modals
        if (e.key === 'Escape') {
            window.closeAddModal();
            window.closeEditModal();
            window.closePasswordModal();
        }
    });
    
    // ============================================
    // TOAST NOTIFICATIONS AUTO-HIDE
    // ============================================
    
    const successToast = document.getElementById('successToast');
    const errorToast = document.getElementById('errorToast');
    
    function hideToast(toast) {
        if (toast) {
            toast.style.transition = 'opacity 0.3s ease';
            toast.style.opacity = '0';
            setTimeout(function() {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }
    }
    
    if (successToast) {
        console.log('Success toast found, will auto-hide in 5 seconds');
        setTimeout(function() { hideToast(successToast); }, 5000);
    }
    
    if (errorToast) {
        console.log('Error toast found, will auto-hide in 5 seconds');
        setTimeout(function() { hideToast(errorToast); }, 5000);
    }
    
    // ============================================
    // INITIAL BULK ACTION STATE
    // ============================================
    
    updateBulkActionState();
    updateSelectAllState();
    
    console.log('User management JavaScript initialization complete');
    
}); // End DOMContentLoaded

// ============================================
// ADDITIONAL DEBUGGING - Check if elements exist
// ============================================
console.log('Checking critical elements:');
console.log('- addUserModal:', document.getElementById('addUserModal'));
console.log('- editUserModal:', document.getElementById('editUserModal'));
console.log('- passwordModal:', document.getElementById('passwordModal'));
console.log('- selectAll:', document.getElementById('selectAll'));
console.log('- bulkActionSelect:', document.getElementById('bulkActionSelect'));
console.log('- applyBulkAction:', document.getElementById('applyBulkAction'));
</script>

<!-- Add this small style for modal animations if not present -->
<style>
.user-modal {
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

.user-modal.active {
    display: flex;
}

.user-modal-content {
    background: white;
    border-radius: 24px;
    padding: 35px;
    max-width: 500px;
    width: 95%;
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

.user-modal-close {
    position: absolute;
    top: 20px;
    right: 25px;
    font-size: 28px;
    color: var(--gray-500);
    cursor: pointer;
    transition: all 0.2s ease;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.user-modal-close:hover {
    background: #fee;
    color: #dc3545;
    transform: rotate(90deg);
}

.password-requirements {
    background: var(--gray-100);
    padding: 15px;
    border-radius: 12px;
    margin-top: 10px;
    font-size: 12px;
}

.requirement {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    color: var(--gray-600);
}

.requirement i {
    width: 16px;
}

.requirement.met {
    color: #28a745;
}

.requirement.met i {
    color: #28a745;
}

.toast-notification {
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

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
</style>
<?php
// Include admin footer
require_once '../includes/admin-footer.php';
?>