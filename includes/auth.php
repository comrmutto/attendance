<?php
/**
 * auth.php - ระบบตรวจสอบสิทธิ์และจัดการผู้ใช้
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Employee.php';

class Auth {
    
    /**
     * ตรวจสอบการ login
     * @param string $username
     * @param string $password
     * @param bool $remember
     * @return bool
     */
    public static function attempt($username, $password, $remember = false) {
        $db = Database::getInstance();
        
        $sql = "SELECT u.*, 
                       e.id as employee_id, 
                       e.first_name, 
                       e.last_name, 
                       e.position_id,
                       e.department_id,
                       e.reports_to,
                       p.level,
                       p.name as position_name,
                       d.name as department_name
                FROM users u 
                LEFT JOIN employees e ON u.employee_id = e.id
                LEFT JOIN positions p ON e.position_id = p.id
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE u.username = ? AND u.status = 1";
        
        $user = $db->fetchOne($sql, [$username]);
        
        if ($user && password_verify($password, $user['password'])) {
            // ตั้งค่า session
            self::setUserSession($user);
            
            // Regenerate session id เพื่อป้องกัน session fixation
            Session::regenerate();
            
            // อัปเดต last login
            $db->update('users', 
                       ['last_login' => date('Y-m-d H:i:s')], 
                       'id = ?', 
                       [$user['id']]);
            
            // ตั้งค่า remember me
            if ($remember) {
                self::setRememberMe($user['id']);
            }
            
            // Log กิจกรรม
            self::logActivity($user['id'], 'login', 'เข้าสู่ระบบ');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * ตั้งค่า session สำหรับผู้ใช้
     * @param array $user
     */
    private static function setUserSession($user) {
        $_SESSION = array_merge($_SESSION, [
            'user_id' => $user['id'],
            'employee_id' => $user['employee_id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'position_level' => $user['level'],
            'position_name' => $user['position_name'],
            'department_id' => $user['department_id'],
            'department_name' => $user['department_name'],
            'fullname' => $user['first_name'] . ' ' . $user['last_name'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'login_time' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        // ตั้งค่า CSRF token
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    
    /**
     * logout ผู้ใช้
     */
    public static function logout() {
        $userId = Session::getUserId();
        
        // ลบ remember me token
        if ($userId) {
            self::clearRememberMe($userId);
            self::logActivity($userId, 'logout', 'ออกจากระบบ');
        }
        
        // ลบ session ทั้งหมด
        $_SESSION = [];
        
        // ลบ session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // ทำลาย session
        session_destroy();
    }
    
    /**
     * ตรวจสอบว่าผู้ใช้ login หรือไม่
     * @return bool
     */
    public static function check() {
        if (!Session::isLoggedIn()) {
            return self::checkRememberMe();
        }
        
        // ตรวจสอบ session timeout (8 ชั่วโมง)
        $loginTime = $_SESSION['login_time'] ?? 0;
        if (time() - $loginTime > 28800) { // 8 hours
            self::logout();
            return false;
        }
        
        // ตรวจสอบ IP และ User Agent (ป้องกัน session hijacking)
        if (($_SESSION['ip_address'] ?? '') !== ($_SERVER['REMOTE_ADDR'] ?? '') ||
            ($_SESSION['user_agent'] ?? '') !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            self::logout();
            return false;
        }
        
        return true;
    }
    
    /**
     * ดึงข้อมูลผู้ใช้ปัจจุบัน
     * @return array|null
     */
    public static function user() {
        if (!self::check()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'employee_id' => $_SESSION['employee_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'fullname' => $_SESSION['fullname'] ?? null,
            'first_name' => $_SESSION['first_name'] ?? null,
            'last_name' => $_SESSION['last_name'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'position_level' => $_SESSION['position_level'] ?? null,
            'position_name' => $_SESSION['position_name'] ?? null,
            'department_id' => $_SESSION['department_id'] ?? null,
            'department_name' => $_SESSION['department_name'] ?? null
        ];
    }
    
    /**
     * ตรวจสอบสิทธิ์ตาม role
     * @param string|array $roles
     * @return bool
     */
    public static function hasRole($roles) {
        if (!self::check()) {
            return false;
        }
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['role'] ?? '', $roles);
    }
    
    /**
     * ตรวจสอบสิทธิ์ตาม position level
     * @param int $minLevel
     * @return bool
     */
    public static function hasLevel($minLevel) {
        if (!self::check()) {
            return false;
        }
        
        return ($_SESSION['position_level'] ?? 0) >= $minLevel;
    }
    
    /**
     * ตรวจสอบว่าเป็นผู้ดูแลระบบหรือไม่
     * @return bool
     */
    public static function isAdmin() {
        return self::hasRole(['admin', 'gm']);
    }
    
    /**
     * ตรวจสอบว่าเป็นหัวหน้างานหรือไม่
     * @return bool
     */
    public static function isSupervisor() {
        return self::hasLevel(POSITION_SUPERVISOR);
    }
    
    /**
     * ตรวจสอบว่าเป็น GM หรือไม่
     * @return bool
     */
    public static function isGM() {
        return self::hasLevel(POSITION_GM);
    }
    
    /**
     * ตรวจสอบว่าเป็นพนักงานทั่วไปหรือไม่
     * @return bool
     */
    public static function isEmployee() {
        return self::hasRole('employee') && !self::isSupervisor() && !self::isGM();
    }
    
    /**
     * ตรวจสอบว่าสามารถดูข้อมูลพนักงานนี้ได้หรือไม่
     * @param int $employeeId
     * @return bool
     */
    public static function canViewEmployee($employeeId) {
        if (!self::check()) {
            return false;
        }
        
        // Admin/GM ดูได้ทุกคน
        if (self::isAdmin()) {
            return true;
        }
        
        $currentEmpId = $_SESSION['employee_id'] ?? null;
        
        // ดูข้อมูลตัวเอง
        if ($currentEmpId == $employeeId) {
            return true;
        }
        
        // ตรวจสอบว่าเป็นหัวหน้าหรือไม่
        $db = Database::getInstance();
        
        // ตรวจสอบสายการบังคับบัญชา (หัวหน้าดูลูกน้อง)
        $sql = "SELECT id FROM employees WHERE reports_to = ? AND id = ?";
        $isSubordinate = $db->fetchOne($sql, [$currentEmpId, $employeeId]);
        
        if ($isSubordinate) {
            return true;
        }
        
        // ตรวจสอบว่าหัวหน้าสามารถดูลูกน้องของลูกน้องได้ (recursive)
        if (self::hasLevel(POSITION_MANAGER)) {
            $sql = "WITH RECURSIVE emp_tree AS (
                        SELECT id FROM employees WHERE reports_to = ?
                        UNION ALL
                        SELECT e.id FROM employees e
                        INNER JOIN emp_tree et ON e.reports_to = et.id
                    )
                    SELECT * FROM emp_tree WHERE id = ?";
            $canView = $db->fetchOne($sql, [$currentEmpId, $employeeId]);
            
            if ($canView) {
                return true;
            }
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
     * @param int $departmentId
     * @return bool
     */
    public static function canViewDepartment($departmentId) {
        if (!self::check()) {
            return false;
        }
        
        // Admin/GM ดูได้ทุกแผนก
        if (self::isAdmin()) {
            return true;
        }
        
        $currentEmpId = $_SESSION['employee_id'] ?? null;
        $currentDeptId = $_SESSION['department_id'] ?? null;
        
        // ดูแผนกตัวเอง
        if ($currentDeptId == $departmentId) {
            return true;
        }
        
        // Manager/Head ดูแผนกในสังกัดได้
        if (self::hasLevel(POSITION_MANAGER)) {
            $db = Database::getInstance();
            
            // ตรวจสอบว่าเป็นหัวหน้าแผนกหรือไม่
            $sql = "SELECT id FROM employees 
                    WHERE department_id = ? AND id IN (
                        SELECT reports_to FROM employees WHERE department_id = ?
                    )";
            $isHead = $db->fetchOne($sql, [$departmentId, $departmentId]);
            
            if ($isHead) {
                return true;
            }
        }
        
        // GM ดูได้หลายแผนก
        if (self::isGM()) {
            $db = Database::getInstance();
            
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
     * ดึงรายชื่อพนักงานที่สามารถดูได้
     * @return array
     */
    public static function getViewableEmployees() {
        if (!self::check()) {
            return [];
        }
        
        $db = Database::getInstance();
        $currentEmpId = $_SESSION['employee_id'] ?? null;
        $currentDeptId = $_SESSION['department_id'] ?? null;
        
        // Admin/GM ดูได้ทุกคน
        if (self::isAdmin()) {
            $sql = "SELECT e.*, d.name as department_name, p.name as position_name,
                           CONCAT(e.first_name, ' ', e.last_name) as fullname
                    FROM employees e
                    LEFT JOIN departments d ON e.department_id = d.id
                    LEFT JOIN positions p ON e.position_id = p.id
                    WHERE e.status = 1
                    ORDER BY e.department_id, e.first_name";
            return $db->fetchAll($sql);
        }
        
        // Manager ดูได้แผนกตัวเอง + ลูกน้อง
        if (self::hasLevel(POSITION_MANAGER)) {
            $sql = "WITH RECURSIVE emp_tree AS (
                        SELECT id FROM employees WHERE reports_to = ? OR id = ?
                        UNION ALL
                        SELECT e.id FROM employees e
                        INNER JOIN emp_tree et ON e.reports_to = et.id
                    )
                    SELECT e.*, d.name as department_name, p.name as position_name,
                           CONCAT(e.first_name, ' ', e.last_name) as fullname
                    FROM employees e
                    LEFT JOIN departments d ON e.department_id = d.id
                    LEFT JOIN positions p ON e.position_id = p.id
                    WHERE e.id IN (SELECT id FROM emp_tree) AND e.status = 1
                    ORDER BY e.department_id, e.first_name";
            return $db->fetchAll($sql, [$currentEmpId, $currentEmpId]);
        }
        
        // Supervisor ดูได้ลูกน้อง
        if (self::hasLevel(POSITION_SUPERVISOR)) {
            $sql = "SELECT e.*, d.name as department_name, p.name as position_name,
                           CONCAT(e.first_name, ' ', e.last_name) as fullname
                    FROM employees e
                    LEFT JOIN departments d ON e.department_id = d.id
                    LEFT JOIN positions p ON e.position_id = p.id
                    WHERE (e.reports_to = ? OR e.id = ?) AND e.status = 1
                    ORDER BY e.first_name";
            return $db->fetchAll($sql, [$currentEmpId, $currentEmpId]);
        }
        
        // พนักงานทั่วไป ดูได้เฉพาะตัวเอง
        $sql = "SELECT e.*, d.name as department_name, p.name as position_name,
                       CONCAT(e.first_name, ' ', e.last_name) as fullname
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN positions p ON e.position_id = p.id
                WHERE e.id = ? AND e.status = 1";
        
        $result = $db->fetchOne($sql, [$currentEmpId]);
        return $result ? [$result] : [];
    }
    
    /**
     * ตั้งค่า remember me token
     * @param int $userId
     */
    private static function setRememberMe($userId) {
        $token = bin2hex(random_bytes(32));
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', time() + 2592000); // 30 days
        
        $db = Database::getInstance();
        
        // ลบ token เก่า
        $db->delete('remember_tokens', 'user_id = ?', [$userId]);
        
        // บันทึก token ใหม่
        $db->insert('remember_tokens', [
            'user_id' => $userId,
            'token' => $hashedToken,
            'expires_at' => $expires,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // ตั้งค่า cookie (secure ใน production)
        setcookie(
            'remember_token',
            $userId . ':' . $token,
            [
                'expires' => time() + 2592000,
                'path' => '/',
                'domain' => '',
                'secure' => (APP_ENV === 'production'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }
    
    /**
     * ตรวจสอบ remember me token
     * @return bool
     */
    private static function checkRememberMe() {
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }
        
        $parts = explode(':', $_COOKIE['remember_token'], 2);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($userId, $token) = $parts;
        
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM remember_tokens 
                WHERE user_id = ? AND expires_at > NOW() 
                ORDER BY id DESC LIMIT 1";
        
        $record = $db->fetchOne($sql, [$userId]);
        
        if ($record && password_verify($token, $record['token'])) {
            // Login อัตโนมัติ
            $sql = "SELECT u.*, 
                           e.id as employee_id, 
                           e.first_name, 
                           e.last_name, 
                           e.position_id,
                           e.department_id,
                           p.level
                    FROM users u 
                    LEFT JOIN employees e ON u.employee_id = e.id
                    LEFT JOIN positions p ON e.position_id = p.id
                    WHERE u.id = ? AND u.status = 1";
            
            $user = $db->fetchOne($sql, [$userId]);
            
            if ($user) {
                self::setUserSession($user);
                self::logActivity($userId, 'auto_login', 'เข้าสู่ระบบอัตโนมัติ (Remember Me)');
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ลบ remember me token
     * @param int $userId
     */
    private static function clearRememberMe($userId) {
        $db = Database::getInstance();
        $db->delete('remember_tokens', 'user_id = ?', [$userId]);
        
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    /**
     * บันทึกกิจกรรม
     * @param int $userId
     * @param string $action
     * @param string $description
     */
    private static function logActivity($userId, $action, $description) {
        $db = Database::getInstance();
        
        $db->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * ตรวจสอบ CSRF token
     * @param string $token
     * @return bool
     */
    public static function verifyCsrfToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * สร้าง CSRF token field สำหรับฟอร์ม
     * @return string
     */
    public static function csrfField() {
        $token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

/**
 * Middleware: ต้อง login เท่านั้น
 */
function requireLogin() {
    if (!Auth::check()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        Session::flash('error', 'กรุณาเข้าสู่ระบบก่อน');
        redirect('/login');
    }
}

/**
 * Middleware: ต้องมี role ที่กำหนด
 * @param string|array $roles
 */
function requireRole($roles) {
    requireLogin();
    
    if (!Auth::hasRole($roles)) {
        http_response_code(403);
        require VIEWS_PATH . 'auth/403.php';
        exit;
    }
}

/**
 * Middleware: ต้องมี position level ตามที่กำหนด
 * @param int $minLevel
 */
function requireLevel($minLevel) {
    requireLogin();
    
    if (!Auth::hasLevel($minLevel)) {
        http_response_code(403);
        require VIEWS_PATH . 'auth/403.php';
        exit;
    }
}

/**
 * Middleware: ตรวจสอบสิทธิ์ดูข้อมูลพนักงาน
 * @param int $employeeId
 */
function requireCanViewEmployee($employeeId) {
    requireLogin();
    
    if (!Auth::canViewEmployee($employeeId)) {
        http_response_code(403);
        require VIEWS_PATH . 'auth/403.php';
        exit;
    }
}

/**
 * Middleware: ตรวจสอบสิทธิ์ดูข้อมูลแผนก
 * @param int $departmentId
 */
function requireCanViewDepartment($departmentId) {
    requireLogin();
    
    if (!Auth::canViewDepartment($departmentId)) {
        http_response_code(403);
        require VIEWS_PATH . 'auth/403.php';
        exit;
    }
}

/**
 * Middleware: ตรวจสอบ CSRF token
 */
function requireCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        if (!Auth::verifyCsrfToken($token)) {
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
}

/**
 * Middleware: จำกัดอัตราการเรียกใช้ (Rate limiting)
 * @param int $limit
 * @param int $timeWindow
 */
function requireRateLimit($limit = 60, $timeWindow = 60) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = 'rate_limit_' . $ip;
    $now = time();
    
    $requests = $_SESSION[$key] ?? ['count' => 0, 'first_request' => $now];
    
    if ($now - $requests['first_request'] > $timeWindow) {
        $requests = ['count' => 1, 'first_request' => $now];
    } else {
        $requests['count']++;
    }
    
    $_SESSION[$key] = $requests;
    
    if ($requests['count'] > $limit) {
        http_response_code(429);
        die('Too many requests. Please try again later.');
    }
}

// ตั้งค่า error handler สำหรับ authentication errors
set_exception_handler(function($e) {
    if ($e instanceof PDOException) {
        error_log("Database Error in Auth: " . $e->getMessage());
        die('ระบบมีปัญหาชั่วคราว กรุณาลองใหม่อีกครั้ง');
    }
});