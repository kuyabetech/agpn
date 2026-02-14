<?php
// ============================================
// ADMIN PROFILE MANAGEMENT - FIXED
// ============================================

define('IN_ADMIN', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAuth();

$user = Auth::user();
$user_id = $user['id'];
$page_title = 'My Profile';

// Initialize variables with default values
$profile = [];
$login_history = [];
$recent_activity = [];

// ============================================
// 1. UPDATE PROFILE INFORMATION
// ============================================
if (isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone'] ?? '');
    $bio = sanitize($_POST['bio'] ?? '');
    $notification_email = isset($_POST['notification_email']) ? 1 : 0;
    $notification_login = isset($_POST['notification_login']) ? 1 : 0;
    
    $errors = [];
    
    // Validate
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Check if email already exists for another user
    if (empty($errors)) {
        try {
            $check = db()->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
            $check->execute([$email, $user_id]);
            if ($check->fetch()) {
                $errors[] = 'Email address is already in use by another account';
            }
        } catch (PDOException $e) {
            error_log("Error checking email: " . $e->getMessage());
        }
    }
    
    // Handle profile picture upload
    $avatar_path = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $upload = uploadImage($_FILES['avatar'], 'avatars');
        if ($upload['success']) {
            $avatar_path = $upload['path'];
            
            // Delete old avatar if exists
            $old_avatar = db()->query("SELECT avatar FROM admin_users WHERE id = $user_id")->fetchColumn();
            if ($old_avatar && file_exists('../' . $old_avatar)) {
                unlink('../' . $old_avatar);
            }
        } else {
            $errors[] = $upload['error'];
        }
    }
    
    // Remove avatar if requested
    if (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] == '1') {
        $old_avatar = db()->query("SELECT avatar FROM admin_users WHERE id = $user_id")->fetchColumn();
        if ($old_avatar && file_exists('../' . $old_avatar)) {
            unlink('../' . $old_avatar);
        }
        $avatar_path = null;
    }
    
    // Update database
    if (empty($errors)) {
        try {
            $sql = "UPDATE admin_users SET 
                    full_name = ?, 
                    email = ?, 
                    phone = ?, 
                    bio = ?,
                    notification_email = ?,
                    notification_login = ?";
            $params = [$full_name, $email, $phone, $bio, $notification_email, $notification_login];
            
            if ($avatar_path !== null) {
                $sql .= ", avatar = ?";
                $params[] = $avatar_path;
            }
            
            $sql .= ", updated_at = NOW() WHERE id = ?";
            $params[] = $user_id;
            
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            
            // Update session
            $_SESSION['admin_name'] = $full_name;
            
            $_SESSION['success'] = 'Profile updated successfully';
            redirect('profile.php');
            
        } catch (PDOException $e) {
            error_log("Error updating profile: " . $e->getMessage());
            $errors[] = 'Database error occurred';
        }
    }
}

// ============================================
// 2. CHANGE PASSWORD
// ============================================
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($current_password)) {
        $errors[] = 'Current password is required';
    }
    
    if (empty($new_password)) {
        $errors[] = 'New password is required';
    } elseif (strlen($new_password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    if (empty($errors)) {
        try {
            // Verify current password
            $stmt = db()->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();
            
            if (!password_verify($current_password, $user_data['password_hash'])) {
                $errors[] = 'Current password is incorrect';
            } else {
                // Update password
                $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                $stmt = db()->prepare("UPDATE admin_users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$password_hash, $user_id]);
                
                $_SESSION['success'] = 'Password changed successfully';
                redirect('profile.php');
            }
        } catch (PDOException $e) {
            error_log("Error changing password: " . $e->getMessage());
            $errors[] = 'Database error occurred';
        }
    }
}

// ============================================
// 3. TWO-FACTOR AUTHENTICATION (2FA)
// ============================================
if (isset($_POST['toggle_2fa'])) {
    $enable_2fa = isset($_POST['enable_2fa']) ? 1 : 0;
    
    try {
        if ($enable_2fa && !$user['two_factor_secret']) {
            // Generate 2FA secret
            $secret = generateRandomString(32);
            $stmt = db()->prepare("UPDATE admin_users SET two_factor_secret = ?, two_factor_enabled = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$secret, $user_id]);
            
            $_SESSION['success'] = 'Two-factor authentication enabled';
        } elseif (!$enable_2fa && $user['two_factor_secret']) {
            // Disable 2FA
            $stmt = db()->prepare("UPDATE admin_users SET two_factor_secret = NULL, two_factor_enabled = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $_SESSION['success'] = 'Two-factor authentication disabled';
        }
        
        redirect('profile.php');
    } catch (PDOException $e) {
        error_log("Error toggling 2FA: " . $e->getMessage());
        $errors[] = 'Database error occurred';
    }
}

// ============================================
// 4. GET USER DATA - WITH ERROR HANDLING
// ============================================
try {
    // Get complete user profile
    $stmt = db()->prepare("
        SELECT * FROM admin_users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        $profile = $user; // Fallback to session data
    }
    
    // Get login history - handle case where table doesn't exist
    try {
        $stmt_history = db()->prepare("
            SELECT * FROM admin_login_history 
            WHERE user_id = ? 
            ORDER BY login_time DESC 
            LIMIT 10
        ");
        $stmt_history->execute([$user_id]);
        $login_history = $stmt_history->fetchAll();
        
        // Ensure it's an array
        if (!is_array($login_history)) {
            $login_history = [];
        }
    } catch (PDOException $e) {
        // Table might not exist yet - create it
        error_log("Login history table error: " . $e->getMessage());
        $login_history = [];
    }
    
    // Get recent activity
    try {
        $stmt_activity = db()->prepare("
            (SELECT 'page' as type, page_name as title, updated_at as time FROM pages WHERE last_edited_by = ? ORDER BY updated_at DESC LIMIT 3)
            UNION
            (SELECT 'post', title, updated_at FROM blog_posts WHERE author LIKE ? ORDER BY updated_at DESC LIMIT 3)
            UNION
            (SELECT 'setting', setting_key, updated_at FROM site_settings ORDER BY updated_at DESC LIMIT 3)
            ORDER BY time DESC LIMIT 5
        ");
        $search_term = '%' . $profile['full_name'] . '%';
        $stmt_activity->execute([$user_id, $search_term]);
        $recent_activity = $stmt_activity->fetchAll();
        
        if (!is_array($recent_activity)) {
            $recent_activity = [];
        }
    } catch (PDOException $e) {
        error_log("Recent activity error: " . $e->getMessage());
        $recent_activity = [];
    }
    
} catch (PDOException $e) {
    error_log("Error fetching profile data: " . $e->getMessage());
    $profile = $user;
    $login_history = [];
    $recent_activity = [];
}

// Ensure variables are arrays
$login_history = is_array($login_history) ? $login_history : [];
$recent_activity = is_array($recent_activity) ? $recent_activity : [];

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
$errors = $errors ?? [];
unset($_SESSION['success'], $_SESSION['error']);

// Include admin header
include '../includes/admin-header.php';
?>

<style>
/* ============================================
   PROFILE PAGE STYLES
============================================ */

.profile-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Profile Header */
.profile-header {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
    color: white;
    position: relative;
    overflow: hidden;
}

.profile-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,184,28,0.1) 0%, transparent 70%);
    border-radius: 50%;
}

.profile-header-content {
    display: flex;
    align-items: center;
    gap: 30px;
    position: relative;
    z-index: 2;
}

.profile-avatar-large {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid var(--gold);
    object-fit: cover;
    background: rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: var(--gold);
}

.profile-info h1 {
    color: white;
    margin-bottom: 10px;
    font-size: 32px;
}

.profile-info .role-badge {
    background: rgba(255,255,255,0.1);
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(255,255,255,0.2);
}

.profile-stats {
    display: flex;
    gap: 30px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.profile-stat {
    display: flex;
    flex-direction: column;
}

.profile-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--gold);
}

.profile-stat-label {
    font-size: 12px;
    color: rgba(255,255,255,0.8);
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Profile Navigation */
.profile-nav {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    border-bottom: 1px solid var(--gray-200);
    padding-bottom: 15px;
    flex-wrap: wrap;
}

.profile-nav-item {
    padding: 10px 20px;
    border-radius: 30px;
    color: var(--gray-600);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.profile-nav-item:hover {
    background: var(--gray-100);
    color: var(--navy);
}

.profile-nav-item.active {
    background: var(--gold);
    color: var(--navy);
}

/* Profile Cards */
.profile-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    border: 1px solid var(--gray-200);
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
}

.profile-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--gray-200);
}

.profile-card-header h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.profile-card-header h3 i {
    color: var(--gold);
}

/* Avatar Upload */
.avatar-section {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.avatar-preview {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--gold);
    background: var(--gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: var(--gray-400);
}

.avatar-upload {
    flex: 1;
}

.avatar-upload .file-upload-area {
    border: 2px dashed var(--gray-300);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--gray-100);
}

.avatar-upload .file-upload-area:hover {
    border-color: var(--gold);
    background: rgba(255,184,28,0.05);
}

.avatar-upload .file-upload-area i {
    font-size: 32px;
    color: var(--gold);
    margin-bottom: 10px;
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

/* Activity Timeline */
.timeline {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.timeline-item {
    display: flex;
    gap: 15px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--gray-200);
}

.timeline-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.timeline-icon {
    width: 40px;
    height: 40px;
    background: rgba(255,184,28,0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 18px;
    flex-shrink: 0;
}

.timeline-content {
    flex: 1;
}

.timeline-title {
    font-weight: 600;
    margin-bottom: 5px;
}

.timeline-meta {
    font-size: 12px;
    color: var(--gray-600);
    display: flex;
    gap: 15px;
}

/* Login History Table */
.login-history-table {
    width: 100%;
    border-collapse: collapse;
}

.login-history-table th {
    text-align: left;
    padding: 12px 0;
    color: var(--gray-600);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--gray-200);
}

.login-history-table td {
    padding: 12px 0;
    border-bottom: 1px solid var(--gray-200);
}

.login-history-table tr:last-child td {
    border-bottom: none;
}

/* Password Strength Meter */
.password-strength {
    margin-top: 15px;
    padding: 15px;
    background: var(--gray-100);
    border-radius: 12px;
}

.strength-meter {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
}

.strength-segment {
    flex: 1;
    height: 8px;
    background: var(--gray-300);
    border-radius: 4px;
    transition: all 0.3s ease;
}

.strength-segment.active {
    background: var(--gold);
}

.strength-text {
    font-size: 12px;
    color: var(--gray-600);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-500);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    color: var(--gray-300);
}

/* Error Messages */
.error-messages {
    background: #F8D7DA;
    color: #721C24;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #dc3545;
}

.error-messages ul {
    margin: 10px 0 0 20px;
}

/* Responsive */
@media (max-width: 992px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-stats {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .profile-header {
        padding: 30px 20px;
    }
    
    .profile-card {
        padding: 20px;
    }
    
    .avatar-section {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-nav {
        justify-content: center;
    }
    
    .login-history-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
}

/* Dark Mode */
body.dark-mode .profile-card {
    background: var(--navy-light);
    border-color: rgba(255,255,255,0.1);
}

body.dark-mode .profile-card h3 {
    color: var(--white);
}

body.dark-mode .avatar-upload .file-upload-area {
    background: var(--navy-dark);
    border-color: rgba(255,255,255,0.2);
}

body.dark-mode .password-strength {
    background: var(--navy-dark);
}

body.dark-mode .login-history-table th {
    color: var(--gray-400);
}

body.dark-mode .login-history-table td {
    color: var(--gray-300);
}

body.dark-mode .empty-state {
    color: var(--gray-500);
}

body.dark-mode .empty-state i {
    color: var(--gray-600);
}
</style>

<!-- ============================================
     PROFILE HEADER
============================================ -->
<div class="profile-container">
    <div class="profile-header">
        <div class="profile-header-content">
            <div class="profile-avatar-large">
                <?php if (!empty($profile['avatar'])): ?>
                    <img src="<?php echo SITE_URL . '/' . $profile['avatar']; ?>" 
                         alt="<?php echo htmlspecialchars($profile['full_name'] ?? 'Admin'); ?>"
                         style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($profile['full_name'] ?? 'Administrator'); ?></h1>
                <div class="role-badge">
                    <i class="fas fa-<?php echo ($profile['role'] ?? 'admin') == 'superadmin' ? 'crown' : (($profile['role'] ?? 'admin') == 'editor' ? 'edit' : 'eye'); ?>"></i>
                    <?php echo ucfirst($profile['role'] ?? 'Administrator'); ?>
                </div>
                
                <div class="profile-stats">
                    <div class="profile-stat">
                        <span class="profile-stat-value">
                            <?php 
                            $member_since = !empty($profile['created_at']) ? timeAgo($profile['created_at']) : 'New';
                            echo $member_since;
                            ?>
                        </span>
                        <span class="profile-stat-label">Member since</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value"><?php echo count($login_history); ?></span>
                        <span class="profile-stat-label">Recent logins</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value"><?php echo count($recent_activity); ?></span>
                        <span class="profile-stat-label">Activities</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Display errors if any -->
    <?php if (!empty($errors)): ?>
        <div class="error-messages">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- ========================================
         PROFILE NAVIGATION
    ======================================== -->
    <div class="profile-nav">
        <a href="#profile" class="profile-nav-item active" onclick="showTab('profile')">
            <i class="fas fa-user"></i> Profile Information
        </a>
        <a href="#security" class="profile-nav-item" onclick="showTab('security')">
            <i class="fas fa-shield-alt"></i> Security
        </a>
        <a href="#activity" class="profile-nav-item" onclick="showTab('activity')">
            <i class="fas fa-history"></i> Activity Log
        </a>
        <a href="#notifications" class="profile-nav-item" onclick="showTab('notifications')">
            <i class="fas fa-bell"></i> Notifications
        </a>
    </div>
    
    <!-- ========================================
         TOAST NOTIFICATIONS
    ======================================== -->
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
    
    <!-- ========================================
         TAB 1: PROFILE INFORMATION
    ======================================== -->
    <div id="profile-tab" class="tab-content active">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="profile-card">
                <div class="profile-card-header">
                    <h3><i class="fas fa-user-circle"></i> Profile Information</h3>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
                
                <div class="avatar-section">
                    <div class="avatar-preview" id="avatarPreview">
                        <?php if (!empty($profile['avatar'])): ?>
                            <img src="<?php echo SITE_URL . '/' . $profile['avatar']; ?>" 
                                 alt="Avatar" 
                                 style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="avatar-upload">
                        <div class="file-upload-area" onclick="document.getElementById('avatar').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload new profile picture</p>
                            <small>JPG, PNG, GIF up to 5MB</small>
                        </div>
                        <input type="file" id="avatar" name="avatar" accept="image/*" style="display: none;" onchange="previewAvatar(this)">
                        
                        <?php if (!empty($profile['avatar'])): ?>
                            <div style="margin-top: 15px;">
                                <label style="display: flex; align-items: center; gap: 10px;">
                                    <input type="checkbox" name="remove_avatar" value="1">
                                    <span style="color: var(--danger);">Remove current avatar</span>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($profile['username'] ?? ''); ?>" 
                               readonly disabled>
                        <small style="color: var(--gray-600);">Username cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span style="color: var(--danger);">*</span></label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Bio / About</label>
                        <textarea name="bio" class="form-control" rows="4" 
                                  placeholder="Tell us a little about yourself..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- ========================================
         TAB 2: SECURITY
    ======================================== -->
    <div id="security-tab" class="tab-content" style="display: none;">
        <!-- Change Password -->
        <div class="profile-card">
            <div class="profile-card-header">
                <h3><i class="fas fa-key"></i> Change Password</h3>
            </div>
            
            <form method="POST" action="" id="passwordForm">
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Current Password <span style="color: var(--danger);">*</span></label>
                        <input type="password" name="current_password" class="form-control" 
                               placeholder="Enter your current password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password <span style="color: var(--danger);">*</span></label>
                        <input type="password" name="new_password" id="new_password" class="form-control" 
                               placeholder="At least 8 characters" required onkeyup="checkPasswordStrength()">
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password <span style="color: var(--danger);">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                               placeholder="Re-enter new password" required onkeyup="checkPasswordMatch()">
                    </div>
                </div>
                
                <div id="passwordMatchMessage" style="display: none; margin-bottom: 15px;"></div>
                
                <div class="password-strength" id="passwordStrength">
                    <div class="strength-meter">
                        <div class="strength-segment" id="strength-1"></div>
                        <div class="strength-segment" id="strength-2"></div>
                        <div class="strength-segment" id="strength-3"></div>
                        <div class="strength-segment" id="strength-4"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Enter a password</div>
                </div>
                
                <button type="submit" name="change_password" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Password
                </button>
            </form>
        </div>
        
        <!-- Two-Factor Authentication -->
        <div class="profile-card">
            <div class="profile-card-header">
                <h3><i class="fas fa-shield-alt"></i> Two-Factor Authentication</h3>
            </div>
            
            <form method="POST" action="">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h4 style="margin-bottom: 10px;"><?php echo !empty($profile['two_factor_enabled']) ? '2FA is enabled' : '2FA is disabled'; ?></h4>
                        <p style="color: var(--gray-600); margin-bottom: 0;">
                            <?php if (!empty($profile['two_factor_enabled'])): ?>
                                Your account is protected with two-factor authentication.
                            <?php else: ?>
                                Add an extra layer of security to your account by enabling two-factor authentication.
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if (!empty($profile['two_factor_enabled'])): ?>
                        <button type="submit" name="toggle_2fa" class="btn btn-secondary" 
                                onclick="return confirm('Are you sure you want to disable two-factor authentication?')">
                            <i class="fas fa-shield-alt"></i> Disable 2FA
                        </button>
                        <input type="hidden" name="enable_2fa" value="0">
                    <?php else: ?>
                        <button type="submit" name="toggle_2fa" class="btn btn-primary">
                            <i class="fas fa-shield-alt"></i> Enable 2FA
                        </button>
                        <input type="hidden" name="enable_2fa" value="1">
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Session Management -->
        <div class="profile-card">
            <div class="profile-card-header">
                <h3><i class="fas fa-laptop"></i> Active Sessions</h3>
                <button class="btn btn-secondary" onclick="logoutAllSessions()">
                    <i class="fas fa-sign-out-alt"></i> Logout All Devices
                </button>
            </div>
            
            <p style="color: var(--gray-600); margin-bottom: 20px;">
                This is your current session. You can log out of all other devices and browsers.
            </p>
            
            <div style="background: var(--gray-100); padding: 20px; border-radius: 12px; display: flex; align-items: center; gap: 20px;">
                <div style="width: 50px; height: 50px; background: rgba(255,184,28,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--gold); font-size: 24px;">
                    <i class="fas fa-laptop"></i>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 5px;">Current Session</div>
                    <div style="font-size: 13px; color: var(--gray-600);">
                        <?php echo substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device', 0, 100); ?>
                    </div>
                </div>
                <div>
                    <span class="status-badge active">Active now</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ========================================
         TAB 3: ACTIVITY LOG
    ======================================== -->
    <div id="activity-tab" class="tab-content" style="display: none;">
        <!-- Recent Activity -->
        <div class="profile-card">
            <div class="profile-card-header">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
            </div>
            
            <?php if (!empty($recent_activity)): ?>
                <div class="timeline">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-<?php echo ($activity['type'] ?? '') == 'page' ? 'file-alt' : (($activity['type'] ?? '') == 'post' ? 'blog' : 'cog'); ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">
                                    <?php 
                                    echo ($activity['type'] ?? '') == 'page' ? 'Edited page: ' : 
                                        (($activity['type'] ?? '') == 'post' ? 'Updated post: ' : 'Changed setting: ');
                                    ?>
                                    <?php echo htmlspecialchars($activity['title'] ?? ''); ?>
                                </div>
                                <div class="timeline-meta">
                                    <span><i class="fas fa-clock"></i> <?php echo isset($activity['time']) ? timeAgo($activity['time']) : 'Recently'; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>No recent activity found.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Login History -->
        <div class="profile-card">
            <div class="profile-card-header">
                <h3><i class="fas fa-sign-in-alt"></i> Login History</h3>
            </div>
            
            <?php if (!empty($login_history)): ?>
                <table class="login-history-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>IP Address</th>
                            <th>Device</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($login_history as $login): ?>
                            <tr>
                                <td><?php echo isset($login['login_time']) ? formatDate($login['login_time'], 'M d, Y H:i') : 'N/A'; ?></td>
                                <td><?php echo $login['ip_address'] ?? 'N/A'; ?></td>
                                <td style="font-size: 12px;"><?php echo isset($login['user_agent']) ? truncateText($login['user_agent'], 50) : 'Unknown'; ?></td>
                                <td>
                                    <span class="status-badge active">Success</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-sign-in-alt"></i>
                    <p>No login history available.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ========================================
         TAB 4: NOTIFICATIONS
    ======================================== -->
    <div id="notifications-tab" class="tab-content" style="display: none;">
        <form method="POST" action="">
            <div class="profile-card">
                <div class="profile-card-header">
                    <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <input type="checkbox" name="notification_email" id="notify_email" 
                               <?php echo !empty($profile['notification_email']) ? 'checked' : ''; ?> value="1">
                        <div>
                            <label for="notify_email" style="font-weight: 600; cursor: pointer;">Email Notifications</label>
                            <p style="color: var(--gray-600); margin: 5px 0 0;">Receive email alerts for important updates and activities</p>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <input type="checkbox" name="notification_login" id="notify_login" 
                               <?php echo !empty($profile['notification_login']) ? 'checked' : ''; ?> value="1">
                        <div>
                            <label for="notify_login" style="font-weight: 600; cursor: pointer;">Login Alerts</label>
                            <p style="color: var(--gray-600); margin: 5px 0 0;">Get notified when there's a new login to your account</p>
                        </div>
                    </div>
                    
                    <hr style="border: none; border-top: 1px solid var(--gray-200); margin: 10px 0;">
                    
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <input type="checkbox" name="notification_newsletter" id="notify_newsletter" checked>
                        <div>
                            <label for="notify_newsletter" style="font-weight: 600; cursor: pointer;">Product Updates</label>
                            <p style="color: var(--gray-600); margin: 5px 0 0;">Receive updates about new features and improvements</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// PROFILE PAGE JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Check for hash in URL
    if (window.location.hash) {
        showTab(window.location.hash.substring(1));
    } else {
        // Default to profile tab
        showTab('profile');
    }
});

// ========================================
// TAB NAVIGATION
// ========================================
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Remove active class from nav items
    document.querySelectorAll('.profile-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Show selected tab
    const selectedTab = document.getElementById(tabName + '-tab');
    if (selectedTab) {
        selectedTab.style.display = 'block';
    }
    
    // Add active class to clicked nav item
    document.querySelectorAll('.profile-nav-item').forEach(item => {
        if (item.getAttribute('href') === '#' + tabName) {
            item.classList.add('active');
        }
    });
    
    // Update URL hash
    window.location.hash = tabName;
}

// ========================================
// AVATAR PREVIEW
// ========================================
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// ========================================
// PASSWORD STRENGTH METER
// ========================================
function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const strengthSegments = document.querySelectorAll('.strength-segment');
    const strengthText = document.getElementById('strengthText');
    
    let strength = 0;
    
    // Length check
    if (password.length >= 8) strength++;
    
    // Uppercase check
    if (/[A-Z]/.test(password)) strength++;
    
    // Lowercase check
    if (/[a-z]/.test(password)) strength++;
    
    // Number check
    if (/[0-9]/.test(password)) strength++;
    
    // Update strength meter
    for (let i = 0; i < strengthSegments.length; i++) {
        if (i < strength) {
            strengthSegments[i].classList.add('active');
        } else {
            strengthSegments[i].classList.remove('active');
        }
    }
    
    // Update strength text
    const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    strengthText.textContent = 'Password strength: ' + strengthLevels[strength];
    
    checkPasswordMatch();
}

function checkPasswordMatch() {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    const messageDiv = document.getElementById('passwordMatchMessage');
    
    if (confirmPass.length > 0) {
        if (newPass === confirmPass) {
            messageDiv.style.display = 'block';
            messageDiv.innerHTML = '<span style="color: #28a745;"><i class="fas fa-check-circle"></i> Passwords match</span>';
        } else {
            messageDiv.style.display = 'block';
            messageDiv.innerHTML = '<span style="color: #dc3545;"><i class="fas fa-exclamation-circle"></i> Passwords do not match</span>';
        }
    } else {
        messageDiv.style.display = 'none';
    }
}

// ========================================
// LOGOUT ALL SESSIONS
// ========================================
function logoutAllSessions() {
    if (confirm('Are you sure you want to log out all other devices? You will remain logged in on this device.')) {
        fetch('logout-all-sessions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('All other sessions have been logged out.');
            } else {
                alert('An error occurred. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
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