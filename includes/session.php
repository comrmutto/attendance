<?php
/**
 * session.php - ระบบจัดการ Session
 */

class Session {

    /**
     * เริ่มต้น session พร้อม config ที่ปลอดภัย
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);

            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => SESSION_PATH,
                'domain'   => SESSION_DOMAIN,
                'secure'   => SESSION_SECURE,
                'httponly' => SESSION_HTTPONLY,
                'samesite' => 'Lax',
            ]);

            session_start();
        }
    }

    /**
     * Set session value
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     */
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     */
    public static function has($key) {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session key
     */
    public static function remove($key) {
        unset($_SESSION[$key]);
    }

    /**
     * Destroy session
     */
    public static function destroy() {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Regenerate session ID (ป้องกัน session fixation)
     */
    public static function regenerate() {
        session_regenerate_id(true);
    }

    /**
     * Flash message - set
     */
    public static function flash($key, $message) {
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Flash message - get and clear
     */
    public static function getFlash($key) {
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        return null;
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current user ID
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current employee ID
     */
    public static function getEmployeeId() {
        return $_SESSION['employee_id'] ?? null;
    }

    /**
     * Get current user role
     */
    public static function getRole() {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Check if user has one of the given roles
     */
    public static function hasRole($roles) {
        if (!self::isLoggedIn()) return false;
        if (is_string($roles)) $roles = [$roles];
        return in_array($_SESSION['role'] ?? '', $roles);
    }

    /**
     * Check if user has minimum position level
     */
    public static function hasLevel($minLevel) {
        if (!self::isLoggedIn()) return false;
        return (int)($_SESSION['position_level'] ?? 0) >= (int)$minLevel;
    }

    /**
     * ดึง CSRF token (สร้างใหม่ถ้ายังไม่มี)
     */
    public static function getCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * ตรวจสอบ CSRF token
     */
    public static function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}