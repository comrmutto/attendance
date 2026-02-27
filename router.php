<?php
/**
 * router.php - ตัวจัดการเส้นทางหลักของระบบ (Single Entry Point)
 * รองรับทั้ง Web Routes และ API Routes
 * ใช้งานกับ PHP built-in server: php -S localhost:8000 router.php
 */

// ============================================
// 1. การตั้งค่าพื้นฐาน
// ============================================

// แสดง error ใน development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// เริ่ม session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// กำหนดเวลามาตรฐาน
date_default_timezone_set('Asia/Bangkok');

// ============================================
// 2. กำหนดค่าคงที่พื้นฐาน
// ============================================

define('BASE_PATH', __DIR__);
define('DS', DIRECTORY_SEPARATOR);

// โหลดไฟล์ config
require_once BASE_PATH . '/config/config.php';

// ============================================
// 3. ฟังก์ชันช่วยเหลือสำหรับ Router
// ============================================

/**
 * หา Base URL ของระบบ
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    
    // ถ้ารันด้วย router.php หรือ index.php
    if (basename($scriptName) === 'router.php' || basename($scriptName) === 'index.php') {
        return $protocol . $host . '/';
    }
    
    return $protocol . $host . rtrim(dirname($scriptName), '/') . '/';
}

/**
 * แก้ไขปัญหาเส้นทางใน subdirectory
 */
function cleanRequestUri($requestUri, $scriptName) {
    // ตัด query string
    if (strpos($requestUri, '?') !== false) {
        $requestUri = substr($requestUri, 0, strpos($requestUri, '?'));
    }
    
    // ตัด base path ออก (กรณีรันใน subdirectory)
    $scriptDir = dirname($scriptName);
    if ($scriptDir !== '/' && $scriptDir !== '\\' && strpos($requestUri, $scriptDir) === 0) {
        $requestUri = substr($requestUri, strlen($scriptDir));
    }
    
    // ถ้า request เป็น root หรือว่าง ให้ใช้ '/'
    if (empty($requestUri)) {
        $requestUri = '/';
    }
    
    return $requestUri;
}

/**
 * ตรวจสอบว่าเป็น AJAX request หรือไม่
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * ตรวจสอบว่าเป็น API request หรือไม่
 */
function isApiRequest($uri) {
    return strpos($uri, '/api/') === 0;
}

/**
 * ส่งไฟล์ static (CSS, JS, images)
 */
function serveStaticFile($filePath) {
    if (!file_exists($filePath) || is_dir($filePath)) {
        return false;
    }
    
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    // MIME types ที่รองรับ
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'xml' => 'application/xml',
        'zip' => 'application/zip',
    ];
    
    if (isset($mimeTypes[$extension])) {
        header('Content-Type: ' . $mimeTypes[$extension]);
    } else {
        header('Content-Type: application/octet-stream');
    }
    
    // ตั้งค่า cache (1 วัน สำหรับ production, ไม่ cache สำหรับ development)
    if (APP_ENV === 'production') {
        header('Cache-Control: public, max-age=86400');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    } else {
        header('Cache-Control: no-cache, must-revalidate');
    }
    
    // ส่งไฟล์
    readfile($filePath);
    return true;
}

/**
 * จัดการ error 404
 */
function handle404() {
    http_response_code(404);
    
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Not Found',
            'message' => 'The requested URL was not found on this server.'
        ]);
    } else {
        require_once VIEWS_PATH . 'auth/404.php';
    }
    exit;
}

/**
 * จัดการ error 403
 */
function handle403() {
    http_response_code(403);
    
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Forbidden',
            'message' => 'You do not have permission to access this resource.'
        ]);
    } else {
        require_once VIEWS_PATH . 'auth/403.php';
    }
    exit;
}

/**
 * จัดการ error 500
 */
function handle500($message = 'Internal Server Error') {
    http_response_code(500);
    
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => APP_ENV === 'development' ? $message : 'Please contact administrator'
        ]);
    } else {
        require_once VIEWS_PATH . 'auth/500.php';
    }
    exit;
}

// ============================================
// 4. กำหนด Routes
// ============================================

class Router {
    
    private $routes = [];
    private $params = [];
    private $requestUri;
    private $requestMethod;
    
    public function __construct() {
        $this->requestUri = cleanRequestUri($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME']);
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->defineRoutes();
    }
    
    /**
     * กำหนด routes ทั้งหมด
     */
    private function defineRoutes() {
        // Web Routes
        $this->routes = [
            'GET' => [
                // Auth routes
                '/' => ['controller' => 'AuthController', 'action' => 'loginForm'],
                '/login' => ['controller' => 'AuthController', 'action' => 'loginForm'],
                '/logout' => ['controller' => 'AuthController', 'action' => 'logout'],
                '/forgot-password' => ['controller' => 'AuthController', 'action' => 'forgotPasswordForm'],
                '/reset-password' => ['controller' => 'AuthController', 'action' => 'resetPasswordForm'],
                
                // Dashboard
                '/dashboard' => ['controller' => 'DashboardController', 'action' => 'index'],
                
                // Attendance
                '/attendance' => ['controller' => 'AttendanceController', 'action' => 'index'],
                '/attendance/view' => ['controller' => 'AttendanceController', 'action' => 'view'],
                '/attendance/my-attendance' => ['controller' => 'AttendanceController', 'action' => 'myAttendance'],
                '/attendance/report' => ['controller' => 'AttendanceController', 'action' => 'report'],
                '/attendance/export' => ['controller' => 'AttendanceController', 'action' => 'export'],
                '/attendance/manual' => ['controller' => 'AttendanceController', 'action' => 'manualForm'],
                '/attendance/edit' => ['controller' => 'AttendanceController', 'action' => 'edit'],
                
                // Employee
                '/employee' => ['controller' => 'EmployeeController', 'action' => 'index'],
                '/employee/profile' => ['controller' => 'EmployeeController', 'action' => 'profile'],
                '/employee/create' => ['controller' => 'EmployeeController', 'action' => 'create'],
                '/employee/edit' => ['controller' => 'EmployeeController', 'action' => 'edit'],
                '/employee/subordinates' => ['controller' => 'EmployeeController', 'action' => 'subordinates'],
                
                // Department
                '/department' => ['controller' => 'DepartmentController', 'action' => 'index'],
                '/department/view' => ['controller' => 'DepartmentController', 'action' => 'view'],
                '/department/create' => ['controller' => 'DepartmentController', 'action' => 'create'],
                '/department/edit' => ['controller' => 'DepartmentController', 'action' => 'edit'],
                '/department/stats' => ['controller' => 'DepartmentController', 'action' => 'stats'],
                
                // Profile & Settings
                '/profile' => ['controller' => 'EmployeeController', 'action' => 'profile'],
                '/change-password' => ['controller' => 'AuthController', 'action' => 'changePassword'],
            ],
            
            'POST' => [
                // Auth
                '/login' => ['controller' => 'AuthController', 'action' => 'login'],
                '/forgot-password' => ['controller' => 'AuthController', 'action' => 'forgotPassword'],
                '/reset-password' => ['controller' => 'AuthController', 'action' => 'resetPassword'],
                '/change-password' => ['controller' => 'AuthController', 'action' => 'changePassword'],
                
                // Attendance
                '/attendance/process' => ['controller' => 'AttendanceController', 'action' => 'process'],
                '/attendance/manual' => ['controller' => 'AttendanceController', 'action' => 'manualSave'],
                '/attendance/edit' => ['controller' => 'AttendanceController', 'action' => 'edit'],
                
                // Employee
                '/employee/create' => ['controller' => 'EmployeeController', 'action' => 'store'],
                '/employee/edit' => ['controller' => 'EmployeeController', 'action' => 'update'],
                '/employee/delete' => ['controller' => 'EmployeeController', 'action' => 'delete'],
                
                // Department
                '/department/create' => ['controller' => 'DepartmentController', 'action' => 'store'],
                '/department/edit' => ['controller' => 'DepartmentController', 'action' => 'update'],
                '/department/delete' => ['controller' => 'DepartmentController', 'action' => 'delete'],
            ],
            
            // API Routes (GET)
            'API_GET' => [
                '/api/attendance' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/list' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/detail' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/summary' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/stats' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/today' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/by-date' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/by-employee' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/by-department' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/late' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/absent' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/overtime' => ['file' => '/api/get_attendance.php'],
                
                '/api/dashboard' => ['file' => '/api/get_dashboard.php'],
                
                '/api/employees' => ['file' => '/api/get_employees.php'],
                '/api/employees/list' => ['file' => '/api/get_employees.php'],
                '/api/employees/detail' => ['file' => '/api/get_employees.php'],
                '/api/employees/hierarchy' => ['file' => '/api/get_employees.php'],
                '/api/employees/subordinates' => ['file' => '/api/get_employees.php'],
                '/api/employees/search' => ['file' => '/api/get_employees.php'],
                '/api/employees/birthdays' => ['file' => '/api/get_employees.php'],
                '/api/employees/stats' => ['file' => '/api/get_employees.php'],
                
                '/api/departments' => ['file' => '/api/get_departments.php'],
                '/api/departments/list' => ['file' => '/api/get_departments.php'],
                '/api/departments/detail' => ['file' => '/api/get_departments.php'],
                '/api/departments/employees' => ['file' => '/api/get_departments.php'],
                '/api/departments/stats' => ['file' => '/api/get_departments.php'],
                '/api/departments/summary' => ['file' => '/api/get_departments.php'],
            ],
            
            // API Routes (POST)
            'API_POST' => [
                '/api/attendance/process' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/update' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/manual-entry' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/bulk-update' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/approve-overtime' => ['file' => '/api/get_attendance.php'],
                '/api/attendance/request-correction' => ['file' => '/api/get_attendance.php'],
                
                '/api/employees/create' => ['file' => '/api/get_employees.php'],
                '/api/employees/update' => ['file' => '/api/get_employees.php'],
                '/api/employees/delete' => ['file' => '/api/get_employees.php'],
                '/api/employees/bulk-import' => ['file' => '/api/get_employees.php'],
                '/api/employees/assign-supervisor' => ['file' => '/api/get_employees.php'],
                '/api/employees/change-status' => ['file' => '/api/get_employees.php'],
                
                '/api/departments/create' => ['file' => '/api/get_departments.php'],
                '/api/departments/update' => ['file' => '/api/get_departments.php'],
                '/api/departments/delete' => ['file' => '/api/get_departments.php'],
                '/api/departments/assign-manager' => ['file' => '/api/get_departments.php'],
                '/api/departments/change-status' => ['file' => '/api/get_departments.php'],
            ]
        ];
    }
    
    /**
     * ค้นหา route ที่ match
     */
    public function match() {
        // ตรวจสอบว่าเป็น API request หรือไม่
        if (isApiRequest($this->requestUri)) {
            return $this->matchApiRoute();
        }
        
        // หา web route
        return $this->matchWebRoute();
    }
    
    /**
     * ค้นหา web route
     */
    private function matchWebRoute() {
        $methodRoutes = $this->routes[$this->requestMethod] ?? [];
        
        foreach ($methodRoutes as $pattern => $route) {
            if ($this->matchPattern($pattern)) {
                $this->params['controller'] = $route['controller'];
                $this->params['action'] = $route['action'];
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ค้นหา API route
     */
    private function matchApiRoute() {
        $methodKey = 'API_' . $this->requestMethod;
        $methodRoutes = $this->routes[$methodKey] ?? [];
        
        foreach ($methodRoutes as $pattern => $route) {
            if ($this->matchPattern($pattern)) {
                $this->params = array_merge($this->params, $route);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ตรวจสอบว่า URI ตรงกับ pattern หรือไม่
     */
    private function matchPattern($pattern) {
        // แปลง pattern เป็น regex
        $pattern = str_replace('/', '\/', $pattern);
        $pattern = '/^' . $pattern . '(\?.*)?$/';
        
        if (preg_match($pattern, $this->requestUri)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * ดำเนินการตาม route ที่ match
     */
    public function dispatch() {
        try {
            // ถ้าเป็น static file ให้ serve ทันที
            $staticPath = BASE_PATH . $this->requestUri;
            if (serveStaticFile($staticPath)) {
                return true;
            }
            
            // ถ้าไม่เจอ route
            if (!$this->match()) {
                handle404();
                return false;
            }
            
            // ถ้าเป็น API request
            if (isset($this->params['file'])) {
                return $this->dispatchApi();
            }
            
            // ถ้าเป็น web request
            return $this->dispatchWeb();
            
        } catch (Exception $e) {
            handle500($e->getMessage());
            return false;
        }
    }
    
    /**
     * ดำเนินการ web route
     */
    private function dispatchWeb() {
        $controllerName = $this->params['controller'];
        $action = $this->params['action'];
        
        $controllerFile = CONTROLLERS_PATH . $controllerName . '.php';
        
        if (!file_exists($controllerFile)) {
            handle500("Controller not found: {$controllerName}");
            return false;
        }
        
        require_once $controllerFile;
        
        if (!class_exists($controllerName)) {
            handle500("Controller class not found: {$controllerName}");
            return false;
        }
        
        $controller = new $controllerName();
        
        if (!method_exists($controller, $action)) {
            handle500("Action not found: {$controllerName}::{$action}");
            return false;
        }
        
        // เรียกใช้ action
        $controller->$action();
        return true;
    }
    
    /**
     * ดำเนินการ API route
     */
    private function dispatchApi() {
        $apiFile = BASE_PATH . $this->params['file'];
        
        if (!file_exists($apiFile)) {
            handle500("API file not found: {$this->params['file']}");
            return false;
        }
        
        // ส่ง parameters ไปยัง API file
        $_GET = array_merge($_GET, ['action' => $this->getActionFromUri()]);
        
        require_once $apiFile;
        return true;
    }
    
    /**
     * ดึง action จาก URI สำหรับ API
     */
    private function getActionFromUri() {
        $parts = explode('/', trim($this->requestUri, '/'));
        $lastPart = end($parts);
        
        // แมป URI กับ action
        $actionMap = [
            'list' => 'list',
            'detail' => 'detail',
            'summary' => 'summary',
            'stats' => 'stats',
            'today' => 'today',
            'search' => 'search',
            'hierarchy' => 'hierarchy',
            'subordinates' => 'subordinates',
            'birthdays' => 'birthdays',
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
            'process' => 'process',
            'export' => 'export',
            'import' => 'import',
        ];
        
        return $actionMap[$lastPart] ?? $lastPart;
    }
}

// ============================================
// 5. เริ่มต้น Router และจัดการ request
// ============================================

try {
    $router = new Router();
    $router->dispatch();
} catch (Exception $e) {
    handle500($e->getMessage());
}