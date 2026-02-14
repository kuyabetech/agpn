<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Only allow AJAX requests
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    exit;
}

// Require authentication
Auth::requireAuth();

$response = ['success' => false, 'message' => ''];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        $stmt = db()->prepare("SELECT * FROM arms WHERE id = ?");
        $stmt->execute([$id]);
        $arm = $stmt->fetch();
        
        if ($arm) {
            $response['success'] = true;
            $response['arm'] = $arm;
        } else {
            $response['message'] = 'Arm not found';
        }
    } catch (PDOException $e) {
        error_log("Error fetching arm: " . $e->getMessage());
        $response['message'] = 'Database error';
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;