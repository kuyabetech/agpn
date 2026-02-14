<?php
// ============================================
// BACKUP & MAINTENANCE PAGE - UPDATED
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

// Only superadmin can access backup management
if ($user['role'] !== 'superadmin') {
    $_SESSION['error'] = 'You do not have permission to access backup management';
    redirect('dashboard.php');
}

// Create backups directory if not exists
$backup_dir = ROOT_PATH . 'backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Database backups directory
$db_backup_dir = $backup_dir . 'database/';
if (!file_exists($db_backup_dir)) {
    mkdir($db_backup_dir, 0755, true);
}

// File backups directory
$file_backup_dir = $backup_dir . 'files/';
if (!file_exists($file_backup_dir)) {
    mkdir($file_backup_dir, 0755, true);
}

$error = '';
$success = '';

// ============================================
// 1. DATABASE BACKUP
// ============================================
if (isset($_POST['create_db_backup'])) {
    $backup_name = sanitize($_POST['backup_name']) ?: 'backup-' . date('Y-m-d-H-i-s');
    $backup_file = $db_backup_dir . $backup_name . '.sql';
    $include_data = isset($_POST['include_data']) ? true : false;
    $compress = isset($_POST['compress']) ? true : false;
    
    try {
        // Get database connection details
        $host = DB_HOST;
        $dbname = DB_NAME;
        $user = DB_USER;
        $pass = DB_PASS;
        
        // Create backup using mysqldump
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s %s > %s',
            escapeshellarg($host),
            escapeshellarg($user),
            escapeshellarg($pass),
            $include_data ? '' : '--no-data',
            escapeshellarg($dbname),
            escapeshellarg($backup_file)
        );
        
        system($command, $return_code);
        
        if ($return_code === 0) {
            // Compress if requested
            if ($compress) {
                $gz_file = $backup_file . '.gz';
                $gz = gzopen($gz_file, 'w9');
                gzwrite($gz, file_get_contents($backup_file));
                gzclose($gz);
                unlink($backup_file);
                $backup_file = $gz_file;
            }
            
            // Log backup creation
            $stmt = db()->prepare("
                INSERT INTO backup_logs (backup_name, backup_type, backup_file, file_size, created_by, created_at)
                VALUES (?, 'database', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $backup_name,
                basename($backup_file),
                filesize($backup_file),
                $_SESSION['admin_user_id'] ?? $user['id']
            ]);
            
            $_SESSION['success'] = 'Database backup created successfully';
        } else {
            $_SESSION['error'] = 'Failed to create database backup. Please check MySQL dump permissions.';
        }
    } catch (Exception $e) {
        error_log("Backup error: " . $e->getMessage());
        $_SESSION['error'] = 'Error creating backup: ' . $e->getMessage();
    }
    redirect('backup.php');
}

// ============================================
// 2. FILE BACKUP
// ============================================
if (isset($_POST['create_file_backup'])) {
    $backup_name = sanitize($_POST['backup_name']) ?: 'files-' . date('Y-m-d-H-i-s');
    $backup_file = $file_backup_dir . $backup_name . '.zip';
    
    try {
        $zip = new ZipArchive();
        
        if ($zip->open($backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Add uploads directory
            $uploads_path = UPLOADS_PATH;
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploads_path),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            $file_count = 0;
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $file_path = $file->getRealPath();
                    $relative_path = 'uploads/' . substr($file_path, strlen($uploads_path));
                    $zip->addFile($file_path, $relative_path);
                    $file_count++;
                }
            }
            
            $zip->close();
            
            // Log backup creation
            $stmt = db()->prepare("
                INSERT INTO backup_logs (backup_name, backup_type, backup_file, file_size, file_count, created_by, created_at)
                VALUES (?, 'files', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $backup_name,
                basename($backup_file),
                filesize($backup_file),
                $file_count,
                $_SESSION['admin_user_id'] ?? $user['id']
            ]);
            
            $_SESSION['success'] = "File backup created successfully. {$file_count} files backed up.";
        } else {
            $_SESSION['error'] = 'Failed to create zip archive';
        }
    } catch (Exception $e) {
        error_log("File backup error: " . $e->getMessage());
        $_SESSION['error'] = 'Error creating file backup: ' . $e->getMessage();
    }
    redirect('backup.php');
}

// ============================================
// 3. RESTORE BACKUP
// ============================================
if (isset($_POST['restore_backup'])) {
    $backup_id = (int)$_POST['backup_id'];
    $backup_file = sanitize($_POST['backup_file']);
    $backup_type = sanitize($_POST['backup_type']);
    $confirm = isset($_POST['confirm_restore']) ? true : false;
    
    if (!$confirm) {
        $_SESSION['error'] = 'Please confirm that you want to restore this backup';
    } else {
        try {
            if ($backup_type === 'database') {
                // Restore database
                $file_path = $db_backup_dir . $backup_file;
                
                if (!file_exists($file_path)) {
                    throw new Exception('Backup file not found');
                }
                
                // If gzipped, extract first
                if (pathinfo($file_path, PATHINFO_EXTENSION) === 'gz') {
                    $gz = gzopen($file_path, 'r');
                    $sql_content = '';
                    while (!gzeof($gz)) {
                        $sql_content .= gzgets($gz, 4096);
                    }
                    gzclose($gz);
                    
                    $temp_file = $db_backup_dir . 'temp_restore.sql';
                    file_put_contents($temp_file, $sql_content);
                    $file_path = $temp_file;
                }
                
                // Execute restore command
                $host = DB_HOST;
                $dbname = DB_NAME;
                $user = DB_USER;
                $pass = DB_PASS;
                
                $command = sprintf(
                    'mysql --host=%s --user=%s --password=%s %s < %s',
                    escapeshellarg($host),
                    escapeshellarg($user),
                    escapeshellarg($pass),
                    escapeshellarg($dbname),
                    escapeshellarg($file_path)
                );
                
                system($command, $return_code);
                
                // Clean up temp file
                if (isset($temp_file) && file_exists($temp_file)) {
                    unlink($temp_file);
                }
                
                if ($return_code === 0) {
                    $_SESSION['success'] = 'Database restored successfully';
                    
                    // Log restore
                    $stmt = db()->prepare("
                        UPDATE backup_logs SET restored_at = NOW(), restored_by = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['admin_user_id'] ?? $user['id'], $backup_id]);
                } else {
                    $_SESSION['error'] = 'Failed to restore database';
                }
                
            } elseif ($backup_type === 'files') {
                // Restore files
                $file_path = $file_backup_dir . $backup_file;
                
                if (!file_exists($file_path)) {
                    throw new Exception('Backup file not found');
                }
                
                $zip = new ZipArchive();
                if ($zip->open($file_path) === true) {
                    $zip->extractTo(ROOT_PATH);
                    $zip->close();
                    $_SESSION['success'] = 'Files restored successfully';
                    
                    // Log restore
                    $stmt = db()->prepare("
                        UPDATE backup_logs SET restored_at = NOW(), restored_by = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['admin_user_id'] ?? $user['id'], $backup_id]);
                } else {
                    $_SESSION['error'] = 'Failed to extract backup archive';
                }
            }
        } catch (Exception $e) {
            error_log("Restore error: " . $e->getMessage());
            $_SESSION['error'] = 'Error restoring backup: ' . $e->getMessage();
        }
    }
    redirect('backup.php');
}

// ============================================
// 4. DELETE BACKUP
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $backup_id = (int)$_GET['delete'];
    
    try {
        $stmt = db()->prepare("SELECT * FROM backup_logs WHERE id = ?");
        $stmt->execute([$backup_id]);
        $backup = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($backup) {
            // Delete file
            if ($backup['backup_type'] === 'database') {
                $file_path = $db_backup_dir . $backup['backup_file'];
            } else {
                $file_path = $file_backup_dir . $backup['backup_file'];
            }
            
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete record
            $stmt = db()->prepare("DELETE FROM backup_logs WHERE id = ?");
            $stmt->execute([$backup_id]);
            
            $_SESSION['success'] = 'Backup deleted successfully';
        }
    } catch (PDOException $e) {
        error_log("Error deleting backup: " . $e->getMessage());
        $_SESSION['error'] = 'Error deleting backup';
    }
    redirect('backup.php');
}

// ============================================
// 5. SCHEDULED BACKUPS
// ============================================
if (isset($_POST['save_schedule'])) {
    $schedule_enabled = isset($_POST['schedule_enabled']) ? 1 : 0;
    $schedule_frequency = sanitize($_POST['schedule_frequency']);
    $schedule_time = sanitize($_POST['schedule_time']);
    $schedule_type = sanitize($_POST['schedule_type']);
    $retention_days = (int)sanitize($_POST['retention_days']);
    
    try {
        // Save to settings table
        $settings = [
            'backup_schedule_enabled' => $schedule_enabled,
            'backup_schedule_frequency' => $schedule_frequency,
            'backup_schedule_time' => $schedule_time,
            'backup_schedule_type' => $schedule_type,
            'backup_retention_days' => $retention_days
        ];
        
        foreach ($settings as $key => $value) {
            $check = db()->prepare("SELECT id FROM site_settings WHERE setting_key = ?");
            $check->execute([$key]);
            
            if ($check->fetch()) {
                $stmt = db()->prepare("UPDATE site_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                $stmt = db()->prepare("INSERT INTO site_settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'text')");
                $stmt->execute([$key, $value]);
            }
        }
        
        $_SESSION['success'] = 'Backup schedule saved successfully';
    } catch (PDOException $e) {
        error_log("Error saving schedule: " . $e->getMessage());
        $_SESSION['error'] = 'Error saving backup schedule';
    }
    redirect('backup.php');
}

// ============================================
// 6. CACHE MANAGEMENT
// ============================================
if (isset($_POST['clear_cache'])) {
    $cache_type = $_POST['cache_type'];
    $cleared = [];
    
    try {
        // Clear template cache
        if ($cache_type === 'templates' || $cache_type === 'all') {
            $cache_dir = ROOT_PATH . 'cache/templates/';
            if (is_dir($cache_dir)) {
                $files = glob($cache_dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                $cleared[] = 'template cache';
            }
        }
        
        // Clear session cache
        if ($cache_type === 'sessions' || $cache_type === 'all') {
            $session_path = session_save_path();
            if ($session_path && is_dir($session_path)) {
                $files = glob($session_path . '/sess_*');
                $count = 0;
                foreach ($files as $file) {
                    if (is_file($file) && time() - filemtime($file) > 3600) {
                        unlink($file);
                        $count++;
                    }
                }
                $cleared[] = "$count old sessions";
            }
        }
        
        // Clear opcache
        if (($cache_type === 'opcache' || $cache_type === 'all') && function_exists('opcache_reset')) {
            opcache_reset();
            $cleared[] = 'OPcache';
        }
        
        $_SESSION['success'] = 'Cleared: ' . implode(', ', $cleared);
    } catch (Exception $e) {
        error_log("Cache clear error: " . $e->getMessage());
        $_SESSION['error'] = 'Error clearing cache: ' . $e->getMessage();
    }
    redirect('backup.php');
}

// ============================================
// 7. ERROR LOGS VIEWER
// ============================================
if (isset($_GET['view_log'])) {
    $log_file = sanitize($_GET['view_log']);
    $log_path = ROOT_PATH . 'logs/' . $log_file;
    
    if (file_exists($log_path)) {
        $log_content = file_get_contents($log_path);
        $log_lines = explode("\n", $log_content);
        $log_lines = array_reverse(array_slice($log_lines, -500)); // Last 500 lines
    }
}

// ============================================
// 8. CLEANUP OLD BACKUPS
// ============================================
if (isset($_GET['cleanup'])) {
    $days = (int)$_GET['cleanup'];
    
    try {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stmt = db()->prepare("
            SELECT * FROM backup_logs 
            WHERE created_at < ? 
            AND restored_at IS NULL
        ");
        $stmt->execute([$cutoff]);
        $old_backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $deleted = 0;
        foreach ($old_backups as $backup) {
            // Delete file
            if ($backup['backup_type'] === 'database') {
                $file_path = $db_backup_dir . $backup['backup_file'];
            } else {
                $file_path = $file_backup_dir . $backup['backup_file'];
            }
            
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete record
            $stmt = db()->prepare("DELETE FROM backup_logs WHERE id = ?");
            $stmt->execute([$backup['id']]);
            $deleted++;
        }
        
        $_SESSION['success'] = "Cleaned up {$deleted} old backups";
    } catch (PDOException $e) {
        error_log("Cleanup error: " . $e->getMessage());
        $_SESSION['error'] = 'Error cleaning up old backups';
    }
    redirect('backup.php');
}

// ============================================
// GET BACKUP STATISTICS
// ============================================
try {
    $db = db();
    
    // Get all backups
    $stmt = $db->query("
        SELECT bl.*, au.full_name as creator_name, au2.full_name as restorer_name
        FROM backup_logs bl
        LEFT JOIN admin_users au ON bl.created_by = au.id
        LEFT JOIN admin_users au2 ON bl.restored_by = au2.id
        ORDER BY bl.created_at DESC
    ");
    $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics
    $total_backups = count($backups);
    $db_backup_count = $db->query("SELECT COUNT(*) FROM backup_logs WHERE backup_type = 'database'")->fetchColumn() ?: 0;
    $file_backup_count = $db->query("SELECT COUNT(*) FROM backup_logs WHERE backup_type = 'files'")->fetchColumn() ?: 0;
    $total_backup_size = 0;
    
    foreach ($backups as $backup) {
        $total_backup_size += $backup['file_size'];
    }
    
    // Get backup schedule settings
    $schedule_enabled = getSetting('backup_schedule_enabled', 0);
    $schedule_frequency = getSetting('backup_schedule_frequency', 'daily');
    $schedule_time = getSetting('backup_schedule_time', '02:00');
    $schedule_type = getSetting('backup_schedule_type', 'database');
    $retention_days = getSetting('backup_retention_days', 30);
    
    // Get PHP error log
    $error_log_path = ini_get('error_log');
    $error_log_exists = $error_log_path && file_exists($error_log_path);
    
    if ($error_log_exists) {
        $error_log_size = filesize($error_log_path);
        $error_log_modified = filemtime($error_log_path);
        $error_log_lines = count(file($error_log_path));
    }
    
    // System health
    $disk_free = disk_free_space(ROOT_PATH);
    $disk_total = disk_total_space(ROOT_PATH);
    $disk_used = $disk_total - $disk_free;
    $disk_percent = round(($disk_used / $disk_total) * 100);
    
} catch (PDOException $e) {
    error_log("Error fetching backups: " . $e->getMessage());
    $backups = [];
    $total_backups = $db_backup_count = $file_backup_count = $total_backup_size = 0;
    $disk_free = $disk_total = $disk_used = $disk_percent = 0;
}

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Set page title
$page_title = 'Backup & Maintenance';

// Breadcrumbs
$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => '#', 'title' => 'Backup & Maintenance']
];

// Page specific CSS
$page_css = ['backup.css'];

// Include admin header
require_once '../includes/admin-header.php';
?>

<!-- Page Specific Styles -->
<style>
    /* Backup & Maintenance Specific Styles */
    .backup-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    
    .backup-stat-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        border: 1px solid var(--gray-200);
        transition: all 0.3s ease;
    }
    
    .backup-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .backup-stat-icon {
        width: 70px;
        height: 70px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
    }
    
    .backup-stat-info h4 {
        font-size: 14px;
        color: var(--gray-600);
        margin-bottom: 8px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .backup-stat-info .number {
        font-size: 32px;
        font-weight: 700;
        color: var(--navy);
        line-height: 1;
        margin-bottom: 5px;
    }
    
    .backup-stat-info .unit {
        font-size: 14px;
        color: var(--gray-600);
        margin-left: 5px;
    }
    
    /* Action Cards */
    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    
    .action-card {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        border: 1px solid var(--gray-200);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.05);
        border-color: var(--gold);
    }
    
    .action-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        background: rgba(255,184,28,0.1);
        color: var(--gold);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin-bottom: 20px;
    }
    
    .action-card h3 {
        margin-bottom: 10px;
        color: var(--navy);
        font-size: 20px;
    }
    
    .action-card p {
        color: var(--gray-600);
        margin-bottom: 25px;
        line-height: 1.6;
    }
    
    /* Backups Table */
    .backups-table-container {
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        margin-top: 25px;
    }
    
    .backups-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .backups-table th {
        text-align: left;
        padding: 18px 20px;
        background: var(--gray-100);
        color: var(--gray-700);
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--gray-200);
    }
    
    .backups-table td {
        padding: 18px 20px;
        border-bottom: 1px solid var(--gray-200);
        color: var(--gray-800);
        vertical-align: middle;
    }
    
    .backups-table tr:hover {
        background: var(--gray-100);
    }
    
    .backup-type-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 14px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .backup-type-badge.database {
        background: rgba(40,167,69,0.1);
        color: #28a745;
    }
    
    .backup-type-badge.files {
        background: rgba(23,162,184,0.1);
        color: #17a2b8;
    }
    
    .restore-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        background: rgba(255,184,28,0.1);
        color: var(--navy);
        border-radius: 30px;
        font-size: 11px;
    }
    
    /* Disk Usage Bar */
    .disk-usage-container {
        background: white;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 30px;
    }
    
    .disk-usage-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .disk-usage-bar {
        height: 12px;
        background: var(--gray-200);
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 10px;
    }
    
    .disk-usage-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--gold) 0%, #FFD700 100%);
        border-radius: 6px;
        transition: width 0.5s ease;
    }
    
    .disk-stats {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        color: var(--gray-600);
    }
    
    /* Modal Styles */
    .backup-modal {
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
    
    .backup-modal.active {
        display: flex;
    }
    
    .backup-modal-content {
        background: white;
        border-radius: 24px;
        padding: 35px;
        max-width: 550px;
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
    
    .backup-modal-close {
        position: absolute;
        top: 20px;
        right: 25px;
        font-size: 28px;
        color: var(--gray-500);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .backup-modal-close:hover {
        color: #dc3545;
        transform: rotate(90deg);
    }
    
    /* Log Viewer */
    .log-viewer {
        background: #1e1e1e;
        color: #d4d4d4;
        font-family: 'Courier New', monospace;
        font-size: 13px;
        padding: 20px;
        border-radius: 12px;
        max-height: 500px;
        overflow-y: auto;
        margin-top: 20px;
    }
    
    .log-line {
        padding: 4px 0;
        border-bottom: 1px solid #333;
        color: #ce9178;
    }
    
    .log-line.error {
        color: #f48771;
    }
    
    .log-line.warning {
        color: #dcdcaa;
    }
    
    /* Schedule Card */
    .schedule-card {
        background: linear-gradient(135deg, #0A1929 0%, #1A2A3A 100%);
        border-radius: 20px;
        padding: 30px;
        color: white;
        margin-bottom: 30px;
    }
    
    .schedule-card h3 {
        color: white;
        margin-bottom: 20px;
    }
    
    .schedule-card .form-group label {
        color: var(--gray-200);
    }
    
    .schedule-card .form-control {
        background: rgba(255,255,255,0.1);
        border-color: rgba(255,255,255,0.2);
        color: white;
    }
    
    .schedule-card .form-control option {
        background: var(--navy);
        color: white;
    }
    
    .btn-icon {
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
    
    .btn-icon:hover {
        background: var(--gold);
        color: var(--navy);
        border-color: var(--gold);
        transform: translateY(-2px);
    }
    
    .btn-icon.delete:hover {
        background: #dc3545;
        color: white;
        border-color: #dc3545;
    }
    
    @media (max-width: 768px) {
        .backup-stats-grid,
        .action-grid {
            grid-template-columns: 1fr;
        }
        
        .backups-table-container {
            overflow-x: auto;
        }
        
        .backups-table {
            min-width: 1000px;
        }
        
        .disk-usage-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
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

<!-- Statistics Cards -->
<div class="backup-stats-grid animate__animated animate__fadeIn">
    <div class="backup-stat-card">
        <div class="backup-stat-icon" style="background: rgba(255,184,28,0.1); color: #FFB81C;">
            <i class="fas fa-database"></i>
        </div>
        <div class="backup-stat-info">
            <h4>Total Backups</h4>
            <span class="number"><?php echo $total_backups; ?></span>
        </div>
    </div>
    
    <div class="backup-stat-card">
        <div class="backup-stat-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
            <i class="fas fa-database"></i>
        </div>
        <div class="backup-stat-info">
            <h4>Database Backups</h4>
            <span class="number"><?php echo $db_backup_count; ?></span>
        </div>
    </div>
    
    <div class="backup-stat-card">
        <div class="backup-stat-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
            <i class="fas fa-folder"></i>
        </div>
        <div class="backup-stat-info">
            <h4>File Backups</h4>
            <span class="number"><?php echo $file_backup_count; ?></span>
        </div>
    </div>
    
    <div class="backup-stat-card">
        <div class="backup-stat-icon" style="background: rgba(111,66,193,0.1); color: #6f42c1;">
            <i class="fas fa-hdd"></i>
        </div>
        <div class="backup-stat-info">
            <h4>Total Size</h4>
            <span class="number">
                <?php
                if ($total_backup_size < 1048576) {
                    echo round($total_backup_size / 1024, 2);
                    echo '<span class="unit">KB</span>';
                } elseif ($total_backup_size < 1073741824) {
                    echo round($total_backup_size / 1048576, 2);
                    echo '<span class="unit">MB</span>';
                } else {
                    echo round($total_backup_size / 1073741824, 2);
                    echo '<span class="unit">GB</span>';
                }
                ?>
            </span>
        </div>
    </div>
</div>

<!-- Disk Usage -->
<div class="disk-usage-container animate__animated animate__fadeIn">
    <div class="disk-usage-header">
        <h3 style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-chart-pie" style="color: var(--gold);"></i>
            Disk Usage
        </h3>
        <span style="background: var(--gray-100); padding: 8px 16px; border-radius: 30px; font-size: 13px;">
            <?php echo round($disk_free / 1073741824, 2); ?> GB free of <?php echo round($disk_total / 1073741824, 2); ?> GB
        </span>
    </div>
    
    <div class="disk-usage-bar">
        <div class="disk-usage-fill" style="width: <?php echo $disk_percent; ?>%;"></div>
    </div>
    
    <div class="disk-stats">
        <span><i class="fas fa-circle" style="color: var(--gold);"></i> Used: <?php echo round($disk_used / 1073741824, 2); ?> GB (<?php echo $disk_percent; ?>%)</span>
        <span><i class="fas fa-circle" style="color: var(--gray-300);"></i> Free: <?php echo round($disk_free / 1073741824, 2); ?> GB</span>
    </div>
</div>

<!-- Action Cards -->
<div class="action-grid animate__animated animate__fadeIn">
    <!-- Database Backup -->
    <div class="action-card">
        <div class="action-icon">
            <i class="fas fa-database"></i>
        </div>
        <h3>Database Backup</h3>
        <p>Create a complete backup of your database including all tables and data.</p>
        <button onclick="openBackupModal('database')" class="btn-primary">
            <i class="fas fa-plus-circle"></i> Create Backup
        </button>
    </div>
    
    <!-- File Backup -->
    <div class="action-card">
        <div class="action-icon">
            <i class="fas fa-folder"></i>
        </div>
        <h3>File Backup</h3>
        <p>Backup all uploaded files including images, certificates, and documents.</p>
        <button onclick="openBackupModal('files')" class="btn-primary">
            <i class="fas fa-plus-circle"></i> Create Backup
        </button>
    </div>
    
    <!-- Cache Management -->
    <div class="action-card">
        <div class="action-icon">
            <i class="fas fa-bolt"></i>
        </div>
        <h3>Cache Management</h3>
        <p>Clear template cache, session files, and OPcache to improve performance.</p>
        <button onclick="openCacheModal()" class="btn-primary">
            <i class="fas fa-broom"></i> Clear Cache
        </button>
    </div>
    
    <!-- Error Logs -->
    <div class="action-card">
        <div class="action-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Error Logs</h3>
        <p>View PHP error logs and system diagnostics.</p>
        <button onclick="location.href='?view_log=error_log'" class="btn-primary">
            <i class="fas fa-file-alt"></i> View Logs
        </button>
    </div>
</div>

<!-- Backup Schedule -->
<div class="schedule-card animate__animated animate__fadeIn">
    <h3 style="display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-clock" style="color: var(--gold);"></i>
        Automated Backup Schedule
    </h3>
    
    <form method="POST" action="">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; color: white;">
                    <input type="checkbox" name="schedule_enabled" value="1" <?php echo $schedule_enabled == 1 ? 'checked' : ''; ?>>
                    Enable Scheduled Backups
                </label>
            </div>
            
            <div class="form-group">
                <label>Frequency</label>
                <select name="schedule_frequency" class="form-control">
                    <option value="daily" <?php echo $schedule_frequency == 'daily' ? 'selected' : ''; ?>>Daily</option>
                    <option value="weekly" <?php echo $schedule_frequency == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                    <option value="monthly" <?php echo $schedule_frequency == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Time</label>
                <input type="time" name="schedule_time" class="form-control" value="<?php echo htmlspecialchars($schedule_time); ?>">
            </div>
            
            <div class="form-group">
                <label>Backup Type</label>
                <select name="schedule_type" class="form-control">
                    <option value="database" <?php echo $schedule_type == 'database' ? 'selected' : ''; ?>>Database Only</option>
                    <option value="files" <?php echo $schedule_type == 'files' ? 'selected' : ''; ?>>Files Only</option>
                    <option value="both" <?php echo $schedule_type == 'both' ? 'selected' : ''; ?>>Both</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Retention (days)</label>
                <input type="number" name="retention_days" class="form-control" value="<?php echo $retention_days; ?>" min="1" max="365">
            </div>
            
            <div class="form-group" style="display: flex; align-items: flex-end;">
                <button type="submit" name="save_schedule" class="btn-primary" style="width: 100%; background: var(--gold); color: var(--navy);">
                    <i class="fas fa-save"></i> Save Schedule
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Recent Backups -->
<div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0 20px;">
    <h3 style="display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-history" style="color: var(--gold);"></i>
        Recent Backups
    </h3>
    
    <button onclick="location.href='?cleanup=30'" class="btn-secondary">
        <i class="fas fa-broom"></i> Cleanup Old Backups
    </button>
</div>

<div class="backups-table-container animate__animated animate__fadeIn">
    <table class="backups-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Size</th>
                <th>Files</th>
                <th>Created</th>
                <th>Created By</th>
                <th>Restored</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($backups): ?>
                <?php foreach ($backups as $backup): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($backup['backup_name']); ?></strong>
                            <div style="font-size: 11px; color: var(--gray-500);">
                                <?php echo htmlspecialchars(basename($backup['backup_file'])); ?>
                            </div>
                        </td>
                        <td>
                            <span class="backup-type-badge <?php echo $backup['backup_type']; ?>">
                                <i class="fas fa-<?php echo $backup['backup_type'] == 'database' ? 'database' : 'folder'; ?>"></i>
                                <?php echo ucfirst($backup['backup_type']); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            if ($backup['file_size'] < 1048576) {
                                echo round($backup['file_size'] / 1024, 2) . ' KB';
                            } elseif ($backup['file_size'] < 1073741824) {
                                echo round($backup['file_size'] / 1048576, 2) . ' MB';
                            } else {
                                echo round($backup['file_size'] / 1073741824, 2) . ' GB';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($backup['file_count']): ?>
                                <?php echo $backup['file_count']; ?> files
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo formatDate($backup['created_at'], 'M d, Y'); ?>
                            <div style="font-size: 11px; color: var(--gray-500);">
                                <?php echo formatDate($backup['created_at'], 'H:i'); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($backup['creator_name'] ?? 'System'); ?></td>
                        <td>
                            <?php if ($backup['restored_at']): ?>
                                <span class="restore-badge">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo formatDate($backup['restored_at'], 'M d, Y'); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--gray-500);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <a href="#" onclick="openRestoreModal(<?php echo $backup['id']; ?>, '<?php echo htmlspecialchars($backup['backup_file']); ?>', '<?php echo $backup['backup_type']; ?>')" 
                                   class="btn-icon" title="Restore">
                                    <i class="fas fa-undo-alt"></i>
                                </a>
                                <a href="<?php echo ($backup['backup_type'] == 'database' ? $db_backup_dir : $file_backup_dir) . $backup['backup_file']; ?>" 
                                   download class="btn-icon" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <a href="?delete=<?php echo $backup['id']; ?>" class="btn-icon delete" title="Delete" 
                                   onclick="return confirm('Are you sure you want to delete this backup?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <i class="fas fa-database" style="font-size: 48px; color: var(--gray-300); margin-bottom: 15px;"></i>
                        <p style="color: var(--gray-600);">No backups have been created yet.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Log Viewer -->
<?php if (isset($log_content)): ?>
    <div style="margin-top: 30px;" class="animate__animated animate__fadeIn">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-file-alt" style="color: var(--gold);"></i>
                Error Log: <?php echo htmlspecialchars($log_file); ?>
            </h3>
            <button onclick="location.href='backup.php'" class="btn-secondary">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        
        <div class="log-viewer">
            <?php foreach ($log_lines as $line): ?>
                <?php if (trim($line) != ''): ?>
                    <div class="log-line <?php 
                        echo strpos($line, 'PHP Fatal error') !== false ? 'error' : 
                            (strpos($line, 'PHP Warning') !== false ? 'warning' : ''); 
                    ?>">
                        <?php echo htmlspecialchars($line); ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Database Backup Modal -->
<div id="dbBackupModal" class="backup-modal">
    <div class="backup-modal-content">
        <span class="backup-modal-close" onclick="closeBackupModal('database')">&times;</span>
        
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-database" style="color: var(--gold);"></i>
            Create Database Backup
        </h2>
        
        <form method="POST" action="">
            <input type="hidden" name="create_db_backup" value="1">
            
            <div class="form-group">
                <label>Backup Name (Optional)</label>
                <input type="text" name="backup_name" class="form-control" 
                       placeholder="backup-<?php echo date('Y-m-d-H-i-s'); ?>">
                <small style="color: var(--gray-600);">Leave empty for auto-generated name</small>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="include_data" value="1" checked>
                    Include table data (uncheck for structure only)
                </label>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="compress" value="1" checked>
                    Compress backup (GZIP)
                </label>
            </div>
            
            <div style="background: var(--gray-100); padding: 15px; border-radius: 12px; margin: 20px 0;">
                <p style="margin: 0; color: var(--gray-700);">
                    <i class="fas fa-info-circle" style="color: var(--gold);"></i>
                    Estimated size: Varies based on database size
                </p>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="btn-primary" style="flex: 1;">
                    <i class="fas fa-plus-circle"></i> Create Backup
                </button>
                <button type="button" class="btn-secondary" onclick="closeBackupModal('database')" style="flex: 1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- File Backup Modal -->
<div id="fileBackupModal" class="backup-modal">
    <div class="backup-modal-content">
        <span class="backup-modal-close" onclick="closeBackupModal('files')">&times;</span>
        
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-folder" style="color: var(--gold);"></i>
            Create File Backup
        </h2>
        
        <form method="POST" action="">
            <input type="hidden" name="create_file_backup" value="1">
            
            <div class="form-group">
                <label>Backup Name (Optional)</label>
                <input type="text" name="backup_name" class="form-control" 
                       placeholder="files-<?php echo date('Y-m-d-H-i-s'); ?>">
                <small style="color: var(--gray-600);">Leave empty for auto-generated name</small>
            </div>
            
            <div style="background: var(--gray-100); padding: 15px; border-radius: 12px; margin: 20px 0;">
                <p style="margin: 0; color: var(--gray-700);">
                    <i class="fas fa-info-circle" style="color: var(--gold);"></i>
                    This will backup all files in the uploads directory.
                </p>
                <p style="margin: 10px 0 0; color: var(--gray-600); font-size: 13px;">
                    Estimated size: 
                    <?php
                    $uploads_size = 0;
                    if (is_dir(UPLOADS_PATH)) {
                        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOADS_PATH));
                        foreach($files as $file) {
                            if($file->isFile()) {
                                $uploads_size += $file->getSize();
                            }
                        }
                    }
                    
                    if ($uploads_size < 1048576) {
                        echo round($uploads_size / 1024, 2) . ' KB';
                    } elseif ($uploads_size < 1073741824) {
                        echo round($uploads_size / 1048576, 2) . ' MB';
                    } else {
                        echo round($uploads_size / 1073741824, 2) . ' GB';
                    }
                    ?>
                </p>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="btn-primary" style="flex: 1;">
                    <i class="fas fa-plus-circle"></i> Create Backup
                </button>
                <button type="button" class="btn-secondary" onclick="closeBackupModal('files')" style="flex: 1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Restore Backup Modal -->
<div id="restoreModal" class="backup-modal">
    <div class="backup-modal-content">
        <span class="backup-modal-close" onclick="closeRestoreModal()">&times;</span>
        
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-undo-alt" style="color: var(--gold);"></i>
            Restore Backup
        </h2>
        
        <form method="POST" action="" id="restoreForm">
            <input type="hidden" name="restore_backup" value="1">
            <input type="hidden" name="backup_id" id="restore_backup_id">
            <input type="hidden" name="backup_file" id="restore_backup_file">
            <input type="hidden" name="backup_type" id="restore_backup_type">
            
            <div style="background: #FFF3CD; color: #856404; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                <p style="margin: 0; font-weight: 600;">
                    <i class="fas fa-exclamation-triangle"></i>
                    Warning: Restoring will overwrite current data!
                </p>
                <p style="margin: 10px 0 0; font-size: 14px;">
                    This action cannot be undone. Please ensure you have a recent backup before proceeding.
                </p>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="confirm_restore" value="1" required>
                    I understand that this will overwrite existing data
                </label>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="btn-primary" style="flex: 1; background: #dc3545;">
                    <i class="fas fa-undo-alt"></i> Restore Backup
                </button>
                <button type="button" class="btn-secondary" onclick="closeRestoreModal()" style="flex: 1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Cache Modal -->
<div id="cacheModal" class="backup-modal">
    <div class="backup-modal-content">
        <span class="backup-modal-close" onclick="closeCacheModal()">&times;</span>
        
        <h2 style="margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-bolt" style="color: var(--gold);"></i>
            Clear Cache
        </h2>
        
        <form method="POST" action="">
            <input type="hidden" name="clear_cache" value="1">
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; padding: 10px 0;">
                    <input type="radio" name="cache_type" value="templates" checked>
                    Template Cache
                </label>
                <label style="display: flex; align-items: center; gap: 10px; padding: 10px 0;">
                    <input type="radio" name="cache_type" value="sessions">
                    Old Sessions ( > 1 hour)
                </label>
                <?php if (function_exists('opcache_reset')): ?>
                <label style="display: flex; align-items: center; gap: 10px; padding: 10px 0;">
                    <input type="radio" name="cache_type" value="opcache">
                    OPcache
                </label>
                <?php endif; ?>
                <label style="display: flex; align-items: center; gap: 10px; padding: 10px 0;">
                    <input type="radio" name="cache_type" value="all">
                    Clear All Cache
                </label>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="btn-primary" style="flex: 1;">
                    <i class="fas fa-broom"></i> Clear Cache
                </button>
                <button type="button" class="btn-secondary" onclick="closeCacheModal()" style="flex: 1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // ============================================
    // BACKUP & MAINTENANCE - JAVASCRIPT
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';
        
        // ============================================
        // MODAL FUNCTIONS
        // ============================================
        
        const dbModal = document.getElementById('dbBackupModal');
        const fileModal = document.getElementById('fileBackupModal');
        const restoreModal = document.getElementById('restoreModal');
        const cacheModal = document.getElementById('cacheModal');
        
        window.openBackupModal = function(type) {
            if (type === 'database') {
                dbModal.classList.add('active');
            } else if (type === 'files') {
                fileModal.classList.add('active');
            }
            document.body.style.overflow = 'hidden';
        };
        
        window.closeBackupModal = function(type) {
            if (type === 'database') {
                dbModal.classList.remove('active');
            } else if (type === 'files') {
                fileModal.classList.remove('active');
            }
            document.body.style.overflow = '';
        };
        
        window.openRestoreModal = function(id, file, type) {
            document.getElementById('restore_backup_id').value = id;
            document.getElementById('restore_backup_file').value = file;
            document.getElementById('restore_backup_type').value = type;
            restoreModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        };
        
        window.closeRestoreModal = function() {
            restoreModal.classList.remove('active');
            document.body.style.overflow = '';
        };
        
        window.openCacheModal = function() {
            cacheModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        };
        
        window.closeCacheModal = function() {
            cacheModal.classList.remove('active');
            document.body.style.overflow = '';
        };
        
        // ============================================
        // CLOSE MODALS WHEN CLICKING OUTSIDE
        // ============================================
        
        window.addEventListener('click', function(e) {
            if (e.target == dbModal) {
                closeBackupModal('database');
            }
            if (e.target == fileModal) {
                closeBackupModal('files');
            }
            if (e.target == restoreModal) {
                closeRestoreModal();
            }
            if (e.target == cacheModal) {
                closeCacheModal();
            }
        });
        
        // ============================================
        // KEYBOARD SHORTCUTS
        // ============================================
        
        document.addEventListener('keydown', function(e) {
            // Escape - Close modals
            if (e.key === 'Escape') {
                closeBackupModal('database');
                closeBackupModal('files');
                closeRestoreModal();
                closeCacheModal();
            }
        });
        
        // ============================================
        // AUTO-HIDE TOAST NOTIFICATIONS
        // ============================================
        
        setTimeout(() => {
            document.querySelectorAll('.toast-notification').forEach(el => {
                el.style.animation = 'fadeOutRight 0.3s ease';
                setTimeout(() => el.remove(), 300);
            });
        }, 5000);
        
        // ============================================
        // CONFIRM DELETE
        // ============================================
        
        document.querySelectorAll('.btn-icon.delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this backup?')) {
                    e.preventDefault();
                }
            });
        });
        
    }); // End DOMContentLoaded
</script>

<?php
// Include admin footer
require_once '../includes/admin-footer.php';
?>