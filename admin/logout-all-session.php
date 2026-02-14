<?php
// ============================================
// LOGOUT ALL OTHER SESSIONS
// ============================================

define('IN_ADMIN', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAuth();

$user_id = $_SESSION[ADMIN_USER_ID];
$current_session_id = session_id();

$response = ['success' => false];

try {
    // Get all session files
    $session_path = session_save_path();
    if (empty($session_path)) {
        $session_path = sys_get_temp_dir();
    }
    
    $sessions = glob($session_path . '/sess_*');
    $deleted = 0;
    
    foreach ($sessions as $session_file) {
        $session_id = str_replace('sess_', '', basename($session_file));
        
        // Don't delete current session
        if ($session_id !== $current_session_id) {
            // Read session data to check if it belongs to this user
            $session_data = file_get_contents($session_file);
            if (strpos($session_data, 'admin_user_id|i:' . $user_id) !== false) {
                unlink($session_file);
                $deleted++;
            }
        }
    }
    
    $response['success'] = true;
    $response['deleted'] = $deleted;
    
} catch (Exception $e) {
    error_log("Error logging out sessions: " . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode($response);
exit;