<?php
// ============================================
// AGPN CORE CONFIGURATION
// ============================================

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'agpn_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site Configuration
define('SITE_URL', 'http://localhost:8081/');
define('SITE_NAME', 'Afroglobe Prime Network Limited');
define('SITE_TAGLINE', 'Empowering Global Excellence');

// Paths
define('ROOT_PATH', dirname(__DIR__) . '/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('ADMIN_PATH', ROOT_PATH . 'admin/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');
define('UPLOADS_URL', SITE_URL . '/uploads/');

// Admin Session
define('ADMIN_SESSION_KEY', 'agpn_admin_logged_in');
define('ADMIN_USER_ID', 'agpn_admin_user_id');

// Security
define('HASH_COST', 12); // bcrypt cost factor

// Error Reporting (Turn off in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Africa/Lagos');

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-load functions
require_once INCLUDES_PATH . 'functions.php';
?>