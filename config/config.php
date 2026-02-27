<?php
/**
 * config.php - การตั้งค่าพื้นฐานของระบบ
 */

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Application Settings
define('APP_NAME', 'Attendance System');
define('APP_VERSION', '1.0.0');
// Auto-detect base URL (รองรับทั้ง localhost:8000 และ Laragon subdirectory)
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_script   = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$_base     = ($_script === '/' || $_script === '\\') ? '' : rtrim($_script, '/');
define('APP_URL', $_protocol . '://' . $_host . $_base);
define('APP_ENV', 'development'); // production, development, testing

// Session Settings
define('SESSION_NAME', 'attendance_session');
define('SESSION_LIFETIME', 7200); // 2 hours
define('SESSION_PATH', '/');
define('SESSION_DOMAIN', '');
define('SESSION_SECURE', false); // true ใน production (HTTPS)
define('SESSION_HTTPONLY', true);

// Database Settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '13792846');
define('DB_NAME', 'attendance_db');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

// Path Settings
define('ROOT_PATH', dirname(__DIR__) . '/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('CLASSES_PATH', ROOT_PATH . 'classes/');
define('CONTROLLERS_PATH', ROOT_PATH . 'controllers/');
define('VIEWS_PATH', ROOT_PATH . 'views/');
define('API_PATH', ROOT_PATH . 'api/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');
define('LOGS_PATH', ROOT_PATH . 'logs/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');

// Attendance Settings
define('CUTOFF_DAY', 21); // รอบตัดวันที่ 21 ของทุกเดือน
define('GRACE_PERIOD', 15); // ระยะผ่อนผัน (นาที)
define('MIN_WORK_HOURS', 8); // ชั่วโมงทำงานขั้นต่ำ
define('MAX_WORK_HOURS', 12); // ชั่วโมงทำงานสูงสุด

// Shift Times
define('MORNING_SHIFT_START', '08:00:00');
define('MORNING_SHIFT_END', '17:30:00');
define('NIGHT_SHIFT_START', '20:00:00');
define('NIGHT_SHIFT_END', '05:30:00');

// Position Levels
define('POSITION_STAFF', 1);
define('POSITION_LEADER', 2);
define('POSITION_SUPERVISOR', 3);
define('POSITION_ASST_MANAGER', 4);
define('POSITION_MANAGER', 5);
define('POSITION_GM', 6);

// User Roles
define('ROLE_EMPLOYEE', 'employee');
define('ROLE_SUPERVISOR', 'supervisor');
define('ROLE_MANAGER', 'manager');
define('ROLE_GM', 'gm');
define('ROLE_ADMIN', 'admin');

// Attendance Status
define('STATUS_PRESENT', 'present');
define('STATUS_ABSENT', 'absent');
define('STATUS_LATE', 'late');
define('STATUS_LEAVE', 'leave');
define('STATUS_HOLIDAY', 'holiday');
define('STATUS_OT', 'overtime');

// Cache Settings
define('CACHE_ENABLED', true);
define('CACHE_DIR', ROOT_PATH . 'cache/');
define('CACHE_LIFETIME', 3600); // 1 hour

// Pagination
define('ITEMS_PER_PAGE', 20);
define('MAX_PAGE_LINKS', 5);

// File Upload
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'xls']);
define('UPLOAD_PATH', ROOT_PATH . 'uploads/');

// API Settings
define('API_RATE_LIMIT', 100); // requests per minute
define('API_TIMEOUT', 30); // seconds

// Email Settings
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', 'noreply@attendance.com');
define('MAIL_FROM_NAME', APP_NAME);

// Create required directories if not exist
$directories = [LOGS_PATH, CACHE_DIR, UPLOAD_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Load helper functions
require_once INCLUDES_PATH . 'functions.php';