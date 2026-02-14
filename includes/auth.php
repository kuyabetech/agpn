<?php
// ============================================
// ADMIN AUTHENTICATION
// ============================================

require_once 'config.php';
require_once 'db.php';

class Auth {

    // ============================================
    // LOGIN
    // ============================================
    public static function login($username, $password) {
        try {
            $stmt = db()->prepare("SELECT * FROM admin_users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {

                // Set session
                $_SESSION[ADMIN_SESSION_KEY] = true;
                $_SESSION[ADMIN_USER_ID] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_name'] = $user['full_name'];
                $_SESSION['admin_role'] = $user['role'];

                // Update last login
                $update = db()->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $update->execute([$user['id']]);

                // Log login history
                $history = db()->prepare("
                    INSERT INTO admin_login_history 
                    (user_id, login_time, ip_address, user_agent, status)
                    VALUES (?, NOW(), ?, ?, 'success')
                ");

                $history->execute([
                    $user['id'],
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);

                return ['success' => true, 'user' => $user];
            }

            return ['success' => false, 'error' => 'Invalid username or password'];

        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'error' => 'System error. Please try again.'];
        }
    }

    // ============================================
    // LOGOUT
    // ============================================
    public static function logout() {
        $_SESSION = [];
        session_destroy();
        return true;
    }

    // ============================================
    // CHECK IF LOGGED IN
    // ============================================
    public static function check() {
        return isset($_SESSION[ADMIN_SESSION_KEY]) 
            && $_SESSION[ADMIN_SESSION_KEY] === true;
    }

    // ============================================
    // REQUIRE AUTH
    // ============================================
    public static function requireAuth() {
        if (!self::check()) {
            redirect(SITE_URL . '/admin/index.php');
            exit;
        }
    }

    // ============================================
    // GET CURRENT USER
    // ============================================
    public static function user() {
        if (!self::check()) {
            return null;
        }

        try {
            $stmt = db()->prepare("
                SELECT id, username, email, full_name, role, last_login 
                FROM admin_users 
                WHERE id = ?
            ");

            $stmt->execute([$_SESSION[ADMIN_USER_ID]]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return null;
        }
    }

    // ============================================
    // CHANGE PASSWORD
    // ============================================
    public static function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $stmt = db()->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Current password is incorrect'];
            }

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => HASH_COST]);

            $update = db()->prepare("
                UPDATE admin_users 
                SET password_hash = ?, updated_at = NOW() 
                WHERE id = ?
            ");

            $update->execute([$newHash, $userId]);

            return ['success' => true, 'message' => 'Password changed successfully'];

        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Database error'];
        }
    }
}
?>