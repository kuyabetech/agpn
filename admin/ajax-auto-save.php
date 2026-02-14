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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_save'])) {
    $post_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = sanitize($_POST['title'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $excerpt = sanitize($_POST['excerpt'] ?? '');
    
    try {
        if ($post_id > 0) {
            // Update existing post
            $stmt = db()->prepare("
                UPDATE blog_posts SET
                    title = ?, slug = ?, content = ?, excerpt = ?, updated_at = NOW()
                WHERE id = ? AND status = 'draft'
            ");
            $stmt->execute([$title, $slug, $content, $excerpt, $post_id]);
            $response['success'] = true;
            $response['message'] = 'Auto-saved successfully';
        }
    } catch (PDOException $e) {
        error_log("Auto-save error: " . $e->getMessage());
        $response['message'] = 'Auto-save failed';
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;