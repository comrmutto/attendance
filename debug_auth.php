<?php
/**
 * debug_auth.php - ทดสอบ Auth::attempt โดยตรง
 * วางที่ root แล้วเปิด http://localhost/attendance_system/debug_auth.php
 * ลบออกหลัง debug เสร็จ!
 */

require_once __DIR__ . '/config/config.php';
require_once CLASSES_PATH . 'Database.php';

// Start session manually
if (session_status() === PHP_SESSION_NONE) session_start();

echo "<h2>ทดสอบทีละ step</h2>";

// Step 1: Session::set ทำงานได้ไหม
echo "<h3>Step 1: Session</h3>";
$_SESSION['test'] = 'ok';
echo isset($_SESSION['test']) ? "<p style='color:green'>✅ Session ทำงานได้</p>" : "<p style='color:red'>❌ Session ใช้ไม่ได้</p>";

// Step 2: โหลด Session class
echo "<h3>Step 2: Load Session class</h3>";
try {
    require_once INCLUDES_PATH . 'session.php';
    echo class_exists('Session') 
        ? "<p style='color:green'>✅ Session class โหลดได้</p>" 
        : "<p style='color:red'>❌ Session class ไม่มี</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine() . "</p>";
}

// Step 3: โหลด functions.php
echo "<h3>Step 3: Load functions.php</h3>";
try {
    require_once INCLUDES_PATH . 'functions.php';
    echo function_exists('redirect') 
        ? "<p style='color:green'>✅ functions.php โหลดได้</p>" 
        : "<p style='color:red'>❌ redirect() ไม่มี</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine() . "</p>";
}

// Step 4: โหลด User class
echo "<h3>Step 4: Load User class</h3>";
try {
    require_once CLASSES_PATH . 'User.php';
    echo class_exists('User') 
        ? "<p style='color:green'>✅ User class โหลดได้</p>" 
        : "<p style='color:red'>❌ User class ไม่มี</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine() . "</p>";
}

// Step 5: โหลด auth.php
echo "<h3>Step 5: Load auth.php</h3>";
try {
    require_once INCLUDES_PATH . 'auth.php';
    echo class_exists('Auth') 
        ? "<p style='color:green'>✅ Auth class โหลดได้</p>" 
        : "<p style='color:red'>❌ Auth class ไม่มี</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine() . "</p>";
    echo "<p>หยุดที่ step นี้ แก้ error นี้ก่อน</p>";
    exit;
}

// Step 6: เรียก Auth::attempt จริง
echo "<h3>Step 6: Auth::attempt('admin', 'admin123')</h3>";
try {
    $result = Auth::attempt('admin', 'admin123');
    if ($result) {
        echo "<p style='color:green'>✅ Login สำเร็จ! Session ที่ set:</p>";
        echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    } else {
        echo "<p style='color:red'>❌ Auth::attempt คืนค่า false</p>";
        echo "<p>แปลว่า password_verify ล้มเหลว หรือ query ไม่คืนค่า</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Exception: <strong>" . $e->getMessage() . "</strong></p>";
    echo "<p>File: " . $e->getFile() . " line " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}