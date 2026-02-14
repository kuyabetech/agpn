<?php
// ============================================
// SETTINGS MANAGEMENT PAGE - FIXED
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

// Only superadmin can access all settings
$is_superadmin = ($user['role'] === 'superadmin');

// Create settings table if it doesn't exist
try {
    $db = db();
    
    // Check if table exists
    $table_check = $db->query("SHOW TABLES LIKE 'site_settings'");
    if ($table_check->rowCount() == 0) {
        // Create settings table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `site_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `setting_key` varchar(100) NOT NULL,
                `setting_value` text,
                `setting_type` varchar(20) DEFAULT 'text',
                `created_by` int(11) DEFAULT NULL,
                `updated_by` int(11) DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `setting_key` (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
} catch (PDOException $e) {
    error_log("Error checking/creating settings table: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = db();
        $db->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            // Skip non-setting fields
            if (in_array($key, ['submit', 'action', 'csrf_token', 'MAX_FILE_SIZE'])) continue;
            
            // Sanitize value
            $value = sanitize($value);
            
            // Check if setting exists
            $check = $db->prepare("SELECT id FROM site_settings WHERE setting_key = ?");
            $check->execute([$key]);
            
            if ($check->fetch()) {
                // Update
                $stmt = $db->prepare("UPDATE site_settings SET setting_value = ?, updated_at = NOW(), updated_by = ? WHERE setting_key = ?");
                $stmt->execute([$value, $user['id'], $key]);
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value, setting_type, created_by, created_at) VALUES (?, ?, 'text', ?, NOW())");
                $stmt->execute([$key, $value, $user['id']]);
            }
        }
        
        // Handle logo upload
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === 0) {
            $upload = uploadImage($_FILES['site_logo'], 'logo');
            if ($upload['success']) {
                // Get old logo to delete
                $check = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo'");
                $check->execute();
                $old_logo = $check->fetchColumn();
                
                if ($old_logo && file_exists('../' . $old_logo)) {
                    unlink('../' . $old_logo);
                }
                
                // Update or insert logo setting
                $check = $db->prepare("SELECT id FROM site_settings WHERE setting_key = 'site_logo'");
                $check->execute();
                
                if ($check->fetch()) {
                    $stmt = $db->prepare("UPDATE site_settings SET setting_value = ?, updated_at = NOW(), updated_by = ? WHERE setting_key = 'site_logo'");
                    $stmt->execute([$upload['path'], $user['id']]);
                } else {
                    $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value, setting_type, created_by, created_at) VALUES ('site_logo', ?, 'image', ?, NOW())");
                    $stmt->execute([$upload['path'], $user['id']]);
                }
            } else {
                $_SESSION['error'] = $upload['error'];
            }
        }
        
        // Handle favicon upload
        if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === 0) {
            $upload = uploadImage($_FILES['site_favicon'], 'favicon');
            if ($upload['success']) {
                // Get old favicon to delete
                $check = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'site_favicon'");
                $check->execute();
                $old_favicon = $check->fetchColumn();
                
                if ($old_favicon && file_exists('../' . $old_favicon)) {
                    unlink('../' . $old_favicon);
                }
                
                $check = $db->prepare("SELECT id FROM site_settings WHERE setting_key = 'site_favicon'");
                $check->execute();
                
                if ($check->fetch()) {
                    $stmt = $db->prepare("UPDATE site_settings SET setting_value = ?, updated_at = NOW(), updated_by = ? WHERE setting_key = 'site_favicon'");
                    $stmt->execute([$upload['path'], $user['id']]);
                } else {
                    $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value, setting_type, created_by, created_at) VALUES ('site_favicon', ?, 'image', ?, NOW())");
                    $stmt->execute([$upload['path'], $user['id']]);
                }
            } else {
                $_SESSION['error'] = $upload['error'];
            }
        }
        
        $db->commit();
        $_SESSION['success'] = 'Settings saved successfully!';
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error saving settings: " . $e->getMessage());
        $_SESSION['error'] = 'Error saving settings: ' . $e->getMessage();
    } catch (Exception $e) {
        $db->rollBack();
        error_log("General error saving settings: " . $e->getMessage());
        $_SESSION['error'] = 'Error saving settings: ' . $e->getMessage();
    }
    redirect('settings.php');
}

// Handle reset to defaults
if (isset($_GET['reset']) && $_GET['reset'] === '1' && $is_superadmin) {
    try {
        $db = db();
        
        // Delete all settings
        $db->exec("DELETE FROM site_settings");
        
        $_SESSION['success'] = 'Settings reset to defaults. Please configure your settings.';
    } catch (PDOException $e) {
        error_log("Error resetting settings: " . $e->getMessage());
        $_SESSION['error'] = 'Error resetting settings';
    }
    redirect('settings.php');
}

// Handle clear cache
if (isset($_GET['clear_cache']) && $_GET['clear_cache'] === '1' && $is_superadmin) {
    try {
        // Clear template cache
        $cache_dir = ROOT_PATH . 'cache/';
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        $_SESSION['success'] = 'Cache cleared successfully!';
    } catch (Exception $e) {
        error_log("Error clearing cache: " . $e->getMessage());
        $_SESSION['error'] = 'Error clearing cache';
    }
    redirect('settings.php');
}

// Get all settings
$settings = [];
try {
    $db = db();
    $stmt = $db->query("SELECT * FROM site_settings ORDER BY setting_key");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
    $settings = [];
}

// Default values
$defaults = [
    'site_title' => 'Afroglobe Prime Network Limited',
    'site_tagline' => 'Empowering Global Excellence',
    'contact_email' => 'info@afroglobeprime.net',
    'contact_phone' => '+234 800 123 4567',
    'address' => 'Lagos, Nigeria',
    'facebook_url' => '#',
    'twitter_url' => '#',
    'linkedin_url' => '#',
    'instagram_url' => '#',
    'youtube_url' => '',
    'footer_copyright' => '© ' . date('Y') . ' Afroglobe Prime Network Limited. All Rights Reserved.',
    'maintenance_mode' => '0',
    'maintenance_message' => 'Site under maintenance. Please check back soon.',
    'timezone' => 'Africa/Lagos',
    'date_format' => 'F j, Y',
    'time_format' => 'H:i',
    'posts_per_page' => '10',
    'enable_comments' => '1',
    'moderate_comments' => '1'
];

// Merge with defaults
foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Set page title
$page_title = 'Settings';

// Breadcrumbs
$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => '#', 'title' => 'Settings']
];

// Include admin header
require_once '../includes/admin-header.php';
?>

<!-- Page Specific Styles -->
<style>
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 25px;
    }
    
    .settings-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        border: 1px solid var(--gray-200);
        transition: all 0.3s ease;
    }
    
    .settings-card:hover {
        box-shadow: 0 12px 20px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .settings-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .settings-header i {
        font-size: 28px;
        color: var(--gold);
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,184,28,0.1);
        border-radius: 14px;
    }
    
    .settings-header h3 {
        margin: 0;
        color: var(--navy);
        font-size: 18px;
        font-weight: 600;
    }
    
    .logo-preview-container {
        background: var(--gray-100);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        margin-bottom: 15px;
        border: 1px solid var(--gray-200);
        min-height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .logo-preview {
        max-width: 100%;
        max-height: 80px;
        object-fit: contain;
    }
    
    .favicon-preview-container {
        display: flex;
        align-items: center;
        gap: 15px;
        background: var(--gray-100);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 15px;
        border: 1px solid var(--gray-200);
    }
    
    .favicon-preview {
        width: 32px;
        height: 32px;
        object-fit: contain;
    }
    
    .social-links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .form-divider {
        margin: 30px 0 20px;
        border-top: 1px solid var(--gray-200);
        position: relative;
    }
    
    .form-divider span {
        position: absolute;
        top: -12px;
        left: 20px;
        background: white;
        padding: 0 15px;
        font-size: 12px;
        font-weight: 600;
        color: var(--gray-600);
        text-transform: uppercase;
    }
    
    .setting-hint {
        font-size: 12px;
        color: var(--gray-600);
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .setting-hint i {
        color: var(--gold);
        font-size: 12px;
    }
    
    .btn-export {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px;
        background: var(--gray-100);
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        color: var(--gray-700);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .btn-export:hover {
        background: var(--gray-200);
        border-color: var(--gray-400);
    }
    
    .btn-export i {
        color: var(--gold);
    }
    
    .btn-danger {
        background: #dc3545;
        color: white;
        border: none;
    }
    
    .btn-danger:hover {
        background: #c82333;
    }
    
    .character-count {
        font-size: 12px;
        color: var(--gray-600);
        margin-top: 5px;
        text-align: right;
    }
    
    .character-count.warning {
        color: #ffc107;
    }
    
    .character-count.danger {
        color: #dc3545;
    }
    
    @media (max-width: 768px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }
        
        .social-links-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

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
            <i class="fas fa-cog" style="color: var(--gold);"></i>
            Settings
        </h1>
        <div style="color: var(--gray-600);">
            <i class="fas fa-sliders-h"></i> 
            Configure your website settings and preferences
        </div>
    </div>
    
    <div style="display: flex; gap: 15px;">
        <?php if ($is_superadmin): ?>
            <button type="button" class="btn-secondary" onclick="if(confirm('Are you sure you want to reset all settings to defaults?')) window.location.href='?reset=1'">
                <i class="fas fa-undo-alt"></i> Reset to Defaults
            </button>
        <?php endif; ?>
        <button type="submit" form="settingsForm" class="btn-primary">
            <i class="fas fa-save"></i> Save Changes
        </button>
    </div>
</div>

<form method="POST" action="" id="settingsForm" enctype="multipart/form-data">
    <div class="settings-grid">
        <!-- General Settings -->
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-globe"></i>
                <h3>General Settings</h3>
            </div>
            
            <div class="form-group">
                <label>Site Title</label>
                <input type="text" name="site_title" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['site_title'] ?? $defaults['site_title']); ?>"
                       placeholder="Your website name">
                <div class="setting-hint">
                    <i class="fas fa-info-circle"></i> Used in browser title and SEO
                </div>
            </div>
            
            <div class="form-group">
                <label>Site Tagline</label>
                <input type="text" name="site_tagline" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['site_tagline'] ?? $defaults['site_tagline']); ?>"
                       placeholder="Your website slogan">
                <div class="setting-hint">
                    <i class="fas fa-info-circle"></i> Brief description of your site
                </div>
            </div>
            
            <div class="form-group">
                <label>Site Logo</label>
                <div class="logo-preview-container" id="logoContainer">
                    <?php if (!empty($settings['site_logo'])): ?>
                        <img src="<?php echo SITE_URL . '/' . $settings['site_logo']; ?>" class="logo-preview" id="logoPreview">
                    <?php else: ?>
                        <div style="color: var(--gray-500);">
                            <i class="fas fa-image" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                            <span>No logo uploaded</span>
                        </div>
                    <?php endif; ?>
                </div>
                <input type="file" name="site_logo" id="site_logo" class="form-control" accept="image/*">
                <div class="setting-hint">
                    <i class="fas fa-info-circle"></i> Recommended size: 200x60px, PNG format with transparency
                </div>
            </div>
            
            <div class="form-group">
                <label>Favicon</label>
                <div class="favicon-preview-container" id="faviconContainer">
                    <?php if (!empty($settings['site_favicon'])): ?>
                        <img src="<?php echo SITE_URL . '/' . $settings['site_favicon']; ?>" class="favicon-preview" id="faviconPreview">
                        <span>Current favicon</span>
                    <?php else: ?>
                        <span style="color: var(--gray-500);">No favicon uploaded</span>
                    <?php endif; ?>
                </div>
                <input type="file" name="site_favicon" id="site_favicon" class="form-control" accept=".ico,image/x-icon,image/png">
                <div class="setting-hint">
                    <i class="fas fa-info-circle"></i> Size: 32x32px, ICO or PNG format
                </div>
            </div>
            
            <div class="form-group">
                <label>Footer Copyright Text</label>
                <textarea name="footer_copyright" class="form-control" rows="3"><?php echo htmlspecialchars($settings['footer_copyright'] ?? $defaults['footer_copyright']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Timezone</label>
                <select name="timezone" class="form-control">
                    <?php
                    $timezones = ['Africa/Lagos', 'Africa/Nairobi', 'Africa/Cairo', 'Africa/Johannesburg', 'America/New_York', 'Europe/London', 'Asia/Dubai'];
                    $current_tz = $settings['timezone'] ?? $defaults['timezone'];
                    foreach ($timezones as $tz):
                    ?>
                        <option value="<?php echo $tz; ?>" <?php echo ($current_tz == $tz) ? 'selected' : ''; ?>>
                            <?php echo str_replace('_', ' ', $tz); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-address-book"></i>
                <h3>Contact Information</h3>
            </div>
            
            <div class="form-group">
                <label>Contact Email</label>
                <input type="email" name="contact_email" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['contact_email'] ?? $defaults['contact_email']); ?>"
                       placeholder="info@example.com">
            </div>
            
            <div class="form-group">
                <label>Contact Phone</label>
                <input type="text" name="contact_phone" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['contact_phone'] ?? $defaults['contact_phone']); ?>"
                       placeholder="+234 800 123 4567">
            </div>
            
            <div class="form-group">
                <label>Office Address</label>
                <textarea name="address" class="form-control" rows="3" 
                          placeholder="Your office address"><?php echo htmlspecialchars($settings['address'] ?? $defaults['address']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Google Maps Embed URL</label>
                <input type="url" name="google_maps" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['google_maps'] ?? ''); ?>"
                       placeholder="https://www.google.com/maps/embed?pb=...">
                <div class="setting-hint">
                    <i class="fas fa-info-circle"></i> Paste the embed URL from Google Maps
                </div>
            </div>
        </div>
        
        <!-- Social Media Links -->
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-share-alt"></i>
                <h3>Social Media</h3>
            </div>
            
            <div class="social-links-grid">
                <div class="form-group">
                    <label><i class="fab fa-facebook" style="color: #1877F2;"></i> Facebook</label>
                    <input type="url" name="facebook_url" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['facebook_url'] ?? $defaults['facebook_url']); ?>"
                           placeholder="https://facebook.com/yourpage">
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-twitter" style="color: #1DA1F2;"></i> Twitter</label>
                    <input type="url" name="twitter_url" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['twitter_url'] ?? $defaults['twitter_url']); ?>"
                           placeholder="https://twitter.com/yourhandle">
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-linkedin" style="color: #0077B5;"></i> LinkedIn</label>
                    <input type="url" name="linkedin_url" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['linkedin_url'] ?? $defaults['linkedin_url']); ?>"
                           placeholder="https://linkedin.com/company/yourcompany">
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-instagram" style="color: #E4405F;"></i> Instagram</label>
                    <input type="url" name="instagram_url" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['instagram_url'] ?? $defaults['instagram_url']); ?>"
                           placeholder="https://instagram.com/yourhandle">
                </div>
                
                <div class="form-group">
                    <label><i class="fab fa-youtube" style="color: #FF0000;"></i> YouTube</label>
                    <input type="url" name="youtube_url" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['youtube_url'] ?? ''); ?>"
                           placeholder="https://youtube.com/c/yourchannel">
                </div>
            </div>
        </div>
        
        <!-- SEO Settings -->
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-chart-line"></i>
                <h3>SEO Settings</h3>
            </div>
            
            <div class="form-group">
                <label>Default Meta Description</label>
                <textarea name="meta_description" id="metaDescription" class="form-control" rows="3" 
                          placeholder="Brief description of your website for search engines"><?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?></textarea>
                <div class="setting-hint">
                    <i class="fas fa-info-circle"></i> Recommended: 150-160 characters
                </div>
                <div class="character-count" id="metaDescCount"><?php echo strlen($settings['meta_description'] ?? ''); ?>/160 characters</div>
            </div>
            
            <div class="form-group">
                <label>Default Meta Keywords</label>
                <input type="text" name="meta_keywords" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?>"
                       placeholder="business, services, nigeria, africa">
                <div class="setting-hint">
                    <i class="fas fa-info-circle"></i> Comma-separated keywords
                </div>
            </div>
            
            <div class="form-group">
                <label>Google Analytics ID</label>
                <input type="text" name="google_analytics" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['google_analytics'] ?? ''); ?>"
                       placeholder="G-XXXXXXXXXX or UA-XXXXXXXXX-X">
                <div class="setting-hint">
                    <i class="fas fa-info-circle"></i> Format: UA-XXXXXXXXX-X or G-XXXXXXXXXX
                </div>
            </div>
        </div>
        
        <!-- Blog Settings -->
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-blog"></i>
                <h3>Blog Settings</h3>
            </div>
            
            <div class="form-group">
                <label>Posts Per Page</label>
                <input type="number" name="posts_per_page" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['posts_per_page'] ?? $defaults['posts_per_page']); ?>"
                       min="1" max="50">
                <div class="setting-hint">
                    <i class="fas fa-info-circle"></i> Number of blog posts to display per page
                </div>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="enable_comments" value="1" 
                           <?php echo ($settings['enable_comments'] ?? $defaults['enable_comments']) == '1' ? 'checked' : ''; ?>>
                    Enable comments on blog posts
                </label>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="moderate_comments" value="1" 
                           <?php echo ($settings['moderate_comments'] ?? $defaults['moderate_comments']) == '1' ? 'checked' : ''; ?>>
                    Moderate comments before publishing
                </label>
            </div>
            
            <div class="form-group">
                <label>Date Format</label>
                <select name="date_format" class="form-control">
                    <option value="F j, Y" <?php echo ($settings['date_format'] ?? $defaults['date_format']) == 'F j, Y' ? 'selected' : ''; ?>>January 1, 2025</option>
                    <option value="Y-m-d" <?php echo ($settings['date_format'] ?? '') == 'Y-m-d' ? 'selected' : ''; ?>>2025-01-01</option>
                    <option value="d/m/Y" <?php echo ($settings['date_format'] ?? '') == 'd/m/Y' ? 'selected' : ''; ?>>01/01/2025</option>
                    <option value="m/d/Y" <?php echo ($settings['date_format'] ?? '') == 'm/d/Y' ? 'selected' : ''; ?>>01/01/2025</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Time Format</label>
                <select name="time_format" class="form-control">
                    <option value="H:i" <?php echo ($settings['time_format'] ?? $defaults['time_format']) == 'H:i' ? 'selected' : ''; ?>>14:30 (24-hour)</option>
                    <option value="g:i A" <?php echo ($settings['time_format'] ?? '') == 'g:i A' ? 'selected' : ''; ?>>2:30 PM (12-hour)</option>
                </select>
            </div>
        </div>
        
        <!-- Maintenance -->
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-tools"></i>
                <h3>Maintenance</h3>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="maintenance_mode" value="1" 
                           <?php echo ($settings['maintenance_mode'] ?? $defaults['maintenance_mode']) == '1' ? 'checked' : ''; ?>>
                    Enable Maintenance Mode
                </label>
                <div class="setting-hint">
                    <i class="fas fa-info-circle"></i> When enabled, only admins can view the site
                </div>
            </div>
            
            <div class="form-group">
                <label>Maintenance Message</label>
                <textarea name="maintenance_message" class="form-control" rows="3"><?php echo htmlspecialchars($settings['maintenance_message'] ?? $defaults['maintenance_message']); ?></textarea>
            </div>
            
            <div class="form-divider">
                <span>Backup & Restore</span>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <button type="button" class="btn-export" onclick="exportSettings()">
                    <i class="fas fa-download"></i> Export Settings
                </button>
                <button type="button" class="btn-export" onclick="importSettings()">
                    <i class="fas fa-upload"></i> Import Settings
                </button>
            </div>
            
            <?php if ($is_superadmin): ?>
                <div style="margin-top: 20px;">
                    <button type="button" class="btn-export btn-danger" onclick="if(confirm('Are you sure you want to clear all cache?')) window.location.href='?clear_cache=1'">
                        <i class="fas fa-broom"></i> Clear Cache
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
// ============================================
// SETTINGS MANAGEMENT - JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ============================================
    // IMAGE PREVIEW
    // ============================================
    const logoInput = document.getElementById('site_logo');
    const faviconInput = document.getElementById('site_favicon');
    const logoPreview = document.getElementById('logoPreview');
    const faviconPreview = document.getElementById('faviconPreview');
    const logoContainer = document.getElementById('logoContainer');
    const faviconContainer = document.getElementById('faviconContainer');
    
    if (logoInput) {
        logoInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Create or update preview image
                    let preview = document.getElementById('logoPreview');
                    
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'logoPreview';
                        preview.className = 'logo-preview';
                        logoContainer.innerHTML = '';
                        logoContainer.appendChild(preview);
                    }
                    
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    if (faviconInput) {
        faviconInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Create or update preview image
                    let preview = document.getElementById('faviconPreview');
                    
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'faviconPreview';
                        preview.className = 'favicon-preview';
                        faviconContainer.innerHTML = '';
                        faviconContainer.appendChild(preview);
                        faviconContainer.appendChild(document.createTextNode(' New favicon'));
                    } else {
                        preview.src = e.target.result;
                    }
                    
                    preview.src = e.target.result;
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    // ============================================
    // META DESCRIPTION COUNTER
    // ============================================
    const metaDescription = document.getElementById('metaDescription');
    const metaDescCount = document.getElementById('metaDescCount');
    
    if (metaDescription && metaDescCount) {
        function updateMetaCount() {
            const count = metaDescription.value.length;
            metaDescCount.textContent = count + '/160 characters';
            
            if (count > 160) {
                metaDescCount.style.color = '#dc3545';
            } else if (count > 140) {
                metaDescCount.style.color = '#ffc107';
            } else {
                metaDescCount.style.color = 'var(--gray-600)';
            }
        }
        
        metaDescription.addEventListener('keyup', updateMetaCount);
        metaDescription.addEventListener('change', updateMetaCount);
        updateMetaCount();
    }
    
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
    // EXPORT SETTINGS
    // ============================================
    window.exportSettings = function() {
        const form = document.getElementById('settingsForm');
        const formData = new FormData(form);
        const settings = {};
        
        for (let [key, value] of formData.entries()) {
            // Skip file inputs and non-setting fields
            if (!key.includes('site_logo') && !key.includes('site_favicon') && key !== 'MAX_FILE_SIZE') {
                settings[key] = value;
            }
        }
        
        // Add current image paths if they exist
        <?php if (!empty($settings['site_logo'])): ?>
        settings.site_logo = '<?php echo addslashes($settings['site_logo']); ?>';
        <?php endif; ?>
        
        <?php if (!empty($settings['site_favicon'])): ?>
        settings.site_favicon = '<?php echo addslashes($settings['site_favicon']); ?>';
        <?php endif; ?>
        
        const blob = new Blob([JSON.stringify(settings, null, 2)], { type: 'application/json' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'agpn-settings-<?php echo date('Y-m-d'); ?>.json';
        a.click();
        window.URL.revokeObjectURL(url);
    };
    
    // ============================================
    // IMPORT SETTINGS
    // ============================================
    window.importSettings = function() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        
        input.onchange = function(e) {
            const file = e.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                try {
                    const settings = JSON.parse(e.target.result);
                    
                    for (let [key, value] of Object.entries(settings)) {
                        // Skip image paths on import (they need to be uploaded separately)
                        if (key === 'site_logo' || key === 'site_favicon') continue;
                        
                        const field = document.querySelector(`[name="${key}"]`);
                        if (field) {
                            if (field.type === 'checkbox') {
                                field.checked = value === '1' || value === true || value === 'on';
                            } else {
                                field.value = value;
                            }
                        }
                    }
                    
                    alert('Settings imported successfully! Click Save Changes to apply.');
                } catch (err) {
                    console.error(err);
                    alert('Invalid settings file');
                }
            };
            
            reader.readAsText(file);
        };
        
        input.click();
    };
    
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