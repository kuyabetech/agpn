<?php
// ============================================
// AGPN HELPER FUNCTIONS
// ============================================
/**
 * Check if current user has required role permission
 * @param string $required_role - Minimum role required ('viewer', 'editor', 'superadmin')
 * @return bool - True if user has permission
 */
function hasPermission($required_role = 'viewer') {
    // Check if user is logged in
    if (!isset($_SESSION[ADMIN_SESSION_KEY]) || $_SESSION[ADMIN_SESSION_KEY] !== true) {
        return false;
    }
    
    // Get user role from session
    $user_role = $_SESSION['admin_role'] ?? 'viewer';
    
    // Define role hierarchy
    $role_hierarchy = [
        'viewer' => 1,
        'editor' => 2,
        'superadmin' => 3
    ];
    
    // Check if user has required permission
    $user_level = $role_hierarchy[$user_role] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 1;
    
    return $user_level >= $required_level;
}
// Sanitize input
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Generate slug
function createSlug($string) {
    $string = preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($string)));
    return trim($string, '-');
}

// Get page content
function getPageContent($pageName) {
    try {
        $stmt = db()->prepare("SELECT * FROM pages WHERE page_name = ? AND status = 'published'");
        $stmt->execute([$pageName]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching page: " . $e->getMessage());
        return null;
    }
}

// Get all arms
function getArms() {
    try {
        $stmt = db()->query("SELECT * FROM arms WHERE status = 'active' ORDER BY display_order");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching arms: " . $e->getMessage());
        return [];
    }
}

// Get testimonials
function getTestimonials($limit = null) {
    try {
        $query = "SELECT * FROM testimonials WHERE status = 'active' ORDER BY display_order";
        if ($limit) {
            $query .= " LIMIT " . intval($limit);
        }
        $stmt = db()->query($query);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching testimonials: " . $e->getMessage());
        return [];
    }
}

// Get blog posts
function getBlogPosts($limit = null, $offset = 0) {
    try {
        $query = "SELECT * FROM blog_posts WHERE status = 'published' ORDER BY published_date DESC";
        if ($limit) {
            $query .= " LIMIT " . intval($offset) . ", " . intval($limit);
        }
        $stmt = db()->query($query);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching blog posts: " . $e->getMessage());
        return [];
    }
}

// Get single blog post
function getBlogPost($slug) {
    try {
        $stmt = db()->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published'");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching blog post: " . $e->getMessage());
        return null;
    }
}

// Get certificates
function getCertificates() {
    try {
        $stmt = db()->query("SELECT * FROM certificates WHERE status = 'active' ORDER BY display_order");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching certificates: " . $e->getMessage());
        return [];
    }
}

// Get site setting
function getSetting($key, $default = '') {
    try {
        $stmt = db()->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log("Error fetching setting: " . $e->getMessage());
        return $default;
    }
}

// Check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION[ADMIN_SESSION_KEY]) && $_SESSION[ADMIN_SESSION_KEY] === true;
}

// Redirect
function redirect($url) {
    header("Location: " . $url);
    exit;
}

// Upload image
function uploadImage($file, $directory = 'general') {
    $target_dir = UPLOADS_PATH . $directory . '/';
    
    // Create directory if not exists
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $imageFileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $filename;
    
    // Check if image file is actual image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return ['success' => false, 'error' => 'File is not an image.'];
    }
    
    // Check file size (5MB max)
    if ($file['size'] > 5000000) {
        return ['success' => false, 'error' => 'File is too large. Max 5MB.'];
    }
    
    // Allow certain file formats
    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        return ['success' => false, 'error' => 'Only JPG, JPEG, PNG, GIF & WEBP files are allowed.'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return [
            'success' => true, 
            'path' => 'uploads/' . $directory . '/' . $filename,
            'url' => UPLOADS_URL . $directory . '/' . $filename
        ];
    }
    
    return ['success' => false, 'error' => 'Error uploading file.'];
}

// Format date
function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

// Truncate text
function truncateText($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $append;
}

// Generate random string
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

// Get navigation menu
function getNavigationMenu($location = 'primary') {
    try {
        $stmt = db()->prepare("
            SELECT mi.* FROM menu_items mi
            JOIN menus m ON mi.menu_id = m.id
            WHERE m.menu_location = ? AND mi.parent_id = 0
            ORDER BY mi.display_order
        ");
        $stmt->execute([$location]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching navigation: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if current page is the front page (homepage)
 * 
 * @return bool True if on homepage, false otherwise
 */
function is_front_page() {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page == 'index.php' || $current_page == '');
}
/**
 * Increment the view count of a blog post
 * @param int $post_id
 * @return bool success
 */
function incrementPostViews($post_id) {
    if (!is_numeric($post_id) || $post_id <= 0) {
        return false;
    }

    try {
        $pdo = db(); // assuming db() returns your PDO instance

        $stmt = $pdo->prepare("
            UPDATE blog_posts 
            SET views = views + 1 
            WHERE id = :id
        ");
        
        $stmt->execute(['id' => $post_id]);
        
        return $stmt->rowCount() > 0;
    }
    catch (PDOException $e) {
        error_log("Failed to increment post views: " . $e->getMessage());
        return false;
    }
}
function getRelatedPosts($current_post_id, $limit = 3) {
    $pdo = db();
    
    $stmt = $pdo->prepare("
        SELECT id, title, slug, excerpt, featured_image, published_date
        FROM blog_posts
        WHERE id != ?
          AND status = 'published'
        ORDER BY published_date DESC
        LIMIT ?
    ");
    
    $stmt->execute([$current_post_id, (int)$limit]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>