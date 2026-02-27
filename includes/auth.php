<?php
/**
 * auth.php - ระบบตรวจสอบสิทธิ์ผู้ใช้
 */

require_once __DIR__ . '/session.php';          // ← แก้: require session ไม่ใช่ตัวเอง
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Database.php';

if (!class_exists('Auth')) {
    class Auth {

        /**
         * ตรวจสอบการ login
         */
        public static function attempt($username, $password, $remember = false) {
            $db = Database::getInstance();

            $sql = "SELECT u.*, e.id as employee_id, e.first_name, e.last_name, e.position_id, p.level
                    FROM users u
                    LEFT JOIN employees e ON u.employee_id = e.id
                    LEFT JOIN positions p ON e.position_id = p.id
                    WHERE u.username = ? AND u.status = 1";

            $user = $db->fetchOne($sql, [$username]);

            if ($user && password_verify($password, $user['password'])) {
                Session::set('user_id', $user['id']);
                Session::set('employee_id', $user['employee_id']);
                Session::set('username', $user['username']);
                Session::set('role', $user['role']);
                Session::set('position_level', $user['level']);
                Session::set('user_name', $user['first_name'] . ' ' . $user['last_name']);

                Session::regenerate();

                $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

                if ($remember) {
                    self::setRememberMe($user['id']);
                }

                logActivity($user['id'], 'login', 'User logged in');

                return true;
            }

            return false;
        }

        /**
         * logout ผู้ใช้
         */
        public static function logout() {
            $userId = Session::getUserId();

            self::clearRememberMe($userId);

            if ($userId) {
                logActivity($userId, 'logout', 'User logged out');
            }

            Session::destroy();
        }

        /**
         * ตรวจสอบว่าผู้ใช้ login หรือไม่
         */
        public static function check() {
            if (Session::isLoggedIn()) {
                return true;
            }
            return self::checkRememberMe();
        }

        /**
         * Verify CSRF token
         */
        public static function verifyCsrfToken($token) {
            return Session::verifyCsrfToken($token);
        }

        /**
         * ดึงข้อมูลผู้ใช้ปัจจุบัน
         */
        public static function user() {
            if (!self::check()) return null;
            $userId = Session::getUserId();
            return $userId ? User::find($userId) : null;
        }

        public static function hasRole($roles) {
            return self::check() && Session::hasRole($roles);
        }

        public static function hasLevel($minLevel) {
            return self::check() && Session::hasLevel($minLevel);
        }

        public static function isAdmin() {
            return self::hasRole(['admin', 'gm']);
        }

        public static function isSupervisor() {
            return self::hasLevel(POSITION_SUPERVISOR);
        }

        public static function isGM() {
            return self::hasLevel(POSITION_GM);
        }

        public static function canViewEmployee($employeeId) {
            if (!self::check()) return false;
            if (self::isAdmin()) return true;

            $currentEmpId = Session::getEmployeeId();
            if ($currentEmpId == $employeeId) return true;

            $db = Database::getInstance();
            $isSubordinate = $db->fetchOne(
                "SELECT id FROM employees WHERE reports_to = ? AND id = ?",
                [$currentEmpId, $employeeId]
            );

            return (bool)$isSubordinate;
        }

        public static function canViewDepartment($departmentId) {
            if (!self::check()) return false;
            if (self::isAdmin()) return true;

            $currentEmpId = Session::getEmployeeId();
            $db = Database::getInstance();

            $inDepartment = $db->fetchOne(
                "SELECT id FROM employees WHERE department_id = ? AND id = ?",
                [$departmentId, $currentEmpId]
            );

            return (bool)$inDepartment;
        }

        private static function setRememberMe($userId) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 2592000);
            $db      = Database::getInstance();

            $db->delete('remember_tokens', 'user_id = ?', [$userId]);
            $db->insert('remember_tokens', [
                'user_id'    => $userId,
                'token'      => password_hash($token, PASSWORD_DEFAULT),
                'expires_at' => $expires,
            ]);

            setcookie('remember_token', $userId . ':' . $token, time() + 2592000, '/', '', false, true);
        }

        private static function checkRememberMe() {
            if (!isset($_COOKIE['remember_token'])) return false;

            [$userId, $token] = explode(':', $_COOKIE['remember_token'], 2);
            $db = Database::getInstance();

            $record = $db->fetchOne(
                "SELECT * FROM remember_tokens WHERE user_id = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
                [$userId]
            );

            if ($record && password_verify($token, $record['token'])) {
                $user = User::find($userId);
                if ($user) {
                    Session::set('user_id', $user['id']);
                    Session::set('employee_id', $user['employee_id']);
                    Session::set('username', $user['username']);
                    Session::set('role', $user['role']);
                    return true;
                }
            }

            return false;
        }

        private static function clearRememberMe($userId) {
            if ($userId) {
                $db = Database::getInstance();
                $db->delete('remember_tokens', 'user_id = ?', [$userId]);
            }
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
}   // ← แก้: ปิด if(!class_exists) ถูกต้อง ไม่มี } เกิน

// ── Middleware functions ───────────────────────────────────────────────────────

if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!Auth::check()) {
            Session::flash('error', 'กรุณาเข้าสู่ระบบก่อน');
            redirect('/login');
        }
    }
}

if (!function_exists('requireRole')) {
    function requireRole($roles) {
        requireLogin();
        if (!Auth::hasRole($roles)) {
            http_response_code(403);
            $view = VIEWS_PATH . 'auth/403.php';
            file_exists($view) ? require $view : die('<h1>403 Forbidden</h1>');
        }
    }
}

if (!function_exists('requireLevel')) {
    function requireLevel($minLevel) {
        requireLogin();
        if (!Auth::hasLevel($minLevel)) {
            http_response_code(403);
            $view = VIEWS_PATH . 'auth/403.php';
            file_exists($view) ? require $view : die('<h1>403 Forbidden</h1>');
        }
    }
}

