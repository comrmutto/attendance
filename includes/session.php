<?php
/**
 * auth.php - ระบบตรวจสอบสิทธิ์ผู้ใช้
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../classes/User.php';


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
            // ตั้งค่า session
            Session::set('user_id', $user['id']);
            Session::set('employee_id', $user['employee_id']);
            Session::set('username', $user['username']);
            Session::set('role', $user['role']);
            Session::set('position_level', $user['level']);
            Session::set('user_name', $user['first_name'] . ' ' . $user['last_name']);
            
            // Regenerate session id เพื่อป้องกัน session fixation
            Session::regenerate();
            
            // อัปเดต last login
            $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
            
            // ตั้งค่า remember me
            if ($remember) {
                self::setRememberMe($user['id']);
            }
            
            // Log กิจกรรม
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
        
        // ลบ remember me token
        self::clearRememberMe($userId);
        
        // Log กิจกรรม
        if ($userId) {
            logActivity($userId, 'logout', 'User logged out');
        }
        
        // ลบ session
        Session::destroy();
    }
    
    /**
     * ตรวจสอบว่าผู้ใช้ login หรือไม่
     */
    public static function check() {
        if (Session::isLoggedIn()) {
            return true;
        }
        
        // ตรวจสอบ remember me token
        return self::checkRememberMe();
    }
    
    /**
     * ดึงข้อมูลผู้ใช้ปัจจุบัน
     */
    public static function user() {
        if (!self::check()) {
            return null;
        }
        
        $userId = Session::getUserId();
        
        if ($userId) {
            return User::find($userId);
        }
        
        return null;
    }
    
    /**
     * ตรวจสอบสิทธิ์ตาม role
     */
    public static function hasRole($roles) {
        return self::check() && Session::hasRole($roles);
    }
    
    /**
     * ตรวจสอบสิทธิ์ตาม position level
     */
    public static function hasLevel($minLevel) {
        return self::check() && Session::hasLevel($minLevel);
    }
    
    /**
     * ตรวจสอบว่าเป็นผู้ดูแลระบบหรือไม่
     */
    public static function isAdmin() {
        return self::hasRole(['admin', 'gm']);
    }
    
    /**
     * ตรวจสอบว่าเป็นหัวหน้างานหรือไม่
     */
    public static function isSupervisor() {
        return self::hasLevel(POSITION_SUPERVISOR);
    }
    
    /**
     * ตรวจสอบว่าเป็น GM หรือไม่
     */
    public static function isGM() {
        return self::hasLevel(POSITION_GM);
    }
    
    /**
     * ตรวจสอบว่าสามารถดูข้อมูลพนักงานนี้ได้หรือไม่
     */
    public static function canViewEmployee($employeeId) {
        if (!self::check()) {
            return false;
        }
        
        // Admin/GM ดูได้ทุกคน
        if (self::isAdmin()) {
            return true;
        }
        
        $currentEmpId = Session::getEmployeeId();
        
        // ดูข้อมูลตัวเอง
        if ($currentEmpId == $employeeId) {
            return true;
        }
        
        // ตรวจสอบว่าเป็นหัวหน้าหรือไม่
        $db = Database::getInstance();
        
        // ตรวจสอบสายการบังคับบัญชา
        $sql = "SELECT id FROM employees WHERE reports_to = ? AND id = ?";
        $isSubordinate = $db->fetchOne($sql, [$currentEmpId, $employeeId]);
        
        if ($isSubordinate) {
            return true;
        }
        
        // GM ดูได้หลายแผนก
        if (self::isGM()) {
            $sql = "SELECT ed.* FROM employee_departments ed 
                    WHERE ed.employee_id = ? AND ed.department_id = (
                        SELECT department_id FROM employees WHERE id = ?
                    )";
            $canView = $db->fetchOne($sql, [$currentEmpId, $employeeId]);
            
            if ($canView) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ตรวจสอบว่าสามารถดูแผนกนี้ได้หรือไม่
     */
    public static function canViewDepartment($departmentId) {
        if (!self::check()) {
            return false;
        }
        
        // Admin/GM ดูได้ทุกแผนก
        if (self::isAdmin()) {
            return true;
        }
        
        $currentEmpId = Session::getEmployeeId();
        
        // ตรวจสอบว่าเป็นพนักงานในแผนกหรือไม่
        $db = Database::getInstance();
        
        $sql = "SELECT id FROM employees WHERE department_id = ? AND id = ?";
        $inDepartment = $db->fetchOne($sql, [$departmentId, $currentEmpId]);
        
        if ($inDepartment) {
            return true;
        }
        
        // GM ดูได้หลายแผนก
        if (self::isGM()) {
            $sql = "SELECT * FROM employee_departments 
                    WHERE employee_id = ? AND department_id = ?";
            $canView = $db->fetchOne($sql, [$currentEmpId, $departmentId]);
            
            if ($canView) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ตั้งค่า remember me token
     */
    private static function setRememberMe($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 2592000); // 30 days
        
        $db = Database::getInstance();
        
        // ลบ token เก่า
        $db->delete('remember_tokens', 'user_id = ?', [$userId]);
        
        // บันทึก token ใหม่
        $db->insert('remember_tokens', [
            'user_id' => $userId,
            'token' => password_hash($token, PASSWORD_DEFAULT),
            'expires_at' => $expires
        ]);
        
        // ตั้งค่า cookie
        setcookie(
            'remember_token',
            $userId . ':' . $token,
            time() + 2592000,
            '/',
            '',
            false,
            true
        );
    }
    
    /**
     * ตรวจสอบ remember me token
     */
    private static function checkRememberMe() {
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }
        
        list($userId, $token) = explode(':', $_COOKIE['remember_token'], 2);
        
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM remember_tokens 
                WHERE user_id = ? AND expires_at > NOW() 
                ORDER BY id DESC LIMIT 1";
        
        $record = $db->fetchOne($sql, [$userId]);
        
        if ($record && password_verify($token, $record['token'])) {
            // Login อัตโนมัติ
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
    
    /**
     * ลบ remember me token
     */
    private static function clearRememberMe($userId) {
        $db = Database::getInstance();
        $db->delete('remember_tokens', 'user_id = ?', [$userId]);
        
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

/**
 * Middleware: ต้อง login เท่านั้น
 */
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!Auth::check()) {
            Session::flash('error', 'กรุณาเข้าสู่ระบบก่อน');
            redirect('/login');
        }
    }
}

/**
 * Middleware: ต้องมี role ที่กำหนด
 */
if (!function_exists('requireRole')) {
    function requireRole($roles) {
    requireLogin();
    
    if (!Auth::hasRole($roles)) {
        http_response_code(403);
        require VIEWS_PATH . 'auth/403.php';
        exit;
    }
}

}
/**
 * Middleware: ต้องมี position level ตามที่กำหนด
 */
if (!function_exists('requireLevel')) {
    function requireLevel($minLevel) {
    requireLogin();
    
    if (!Auth::hasLevel($minLevel)) {
        http_response_code(403);
        require VIEWS_PATH . 'auth/403.php';
        exit;
    }
}
}
}