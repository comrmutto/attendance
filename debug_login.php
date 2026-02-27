<?php
/**
 * debug_login.php - วางไว้ที่ root ของโปรเจค แล้วเปิด http://localhost:8000/debug_login.php
 * ลบไฟล์นี้ออกหลัง debug เสร็จ!
 */

require_once __DIR__ . '/config/config.php';
require_once CLASSES_PATH . 'Database.php';

$db = Database::getInstance();

echo "<h2>1. เช็ค DB Connection</h2>";
echo "<p style='color:green'>✅ Connected to: " . DB_NAME . "</p>";

echo "<h2>2. ดึงข้อมูล user 'admin' จาก DB</h2>";
$user = $db->fetchOne("SELECT id, username, password, role, status, employee_id FROM users WHERE username = 'admin'");

if (!$user) {
    echo "<p style='color:red'>❌ ไม่พบ user 'admin' ใน DB เลย</p>";
    exit;
}

echo "<pre>";
print_r($user);
echo "</pre>";

echo "<h2>3. เช็ค status</h2>";
if ($user['status'] != 1) {
    echo "<p style='color:red'>❌ status = {$user['status']} (ต้องเป็น 1)</p>";
} else {
    echo "<p style='color:green'>✅ status = 1</p>";
}

echo "<h2>4. เช็ค password_verify</h2>";
$testPasswords = ['admin123', 'Admin123', 'admin', '13792846'];
foreach ($testPasswords as $pass) {
    $result = password_verify($pass, $user['password']);
    $icon = $result ? "✅" : "❌";
    $color = $result ? "green" : "red";
    echo "<p style='color:{$color}'>{$icon} password_verify('{$pass}') = " . ($result ? 'TRUE' : 'FALSE') . "</p>";
}

echo "<h2>5. เช็ค hash format</h2>";
$prefix = substr($user['password'], 0, 4);
echo "<p>Hash prefix: <strong>{$prefix}</strong> (ต้องเป็น \$2y\$)</p>";
if ($prefix !== '$2y$') {
    echo "<p style='color:red'>❌ Hash format ผิด! เป็น {$prefix} แทนที่จะเป็น \$2y\$</p>";
    echo "<p>แก้ด้วย: <code>UPDATE users SET password = '" . password_hash('admin123', PASSWORD_DEFAULT) . "' WHERE username = 'admin';</code></p>";
} else {
    echo "<p style='color:green'>✅ Hash format ถูกต้อง</p>";
}

echo "<h2>6. Generate hash ใหม่สำหรับ 'admin123'</h2>";
$newHash = password_hash('admin123', PASSWORD_DEFAULT);
echo "<p>Hash ใหม่: <code>{$newHash}</code></p>";
echo "<p>SQL: <code>UPDATE users SET password = '{$newHash}' WHERE username = 'admin';</code></p>";

echo "<h2>7. เช็ค SQL query ใน Auth::attempt</h2>";
$sql = "SELECT u.*, e.id as employee_id, e.first_name, e.last_name, e.position_id, p.level 
        FROM users u 
        LEFT JOIN employees e ON u.employee_id = e.id
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE u.username = ? AND u.status = 1";
$result = $db->fetchOne($sql, ['admin']);
if (!$result) {
    echo "<p style='color:red'>❌ Query ใน Auth::attempt ไม่คืนค่า! เช็ค:</p>";
    echo "<ul>";
    echo "<li>status = 1 หรือเปล่า?</li>";
    echo "<li>มี table employees และ positions ไหม?</li>";
    echo "</ul>";
    
    // ลอง query แบบง่าย
    $simple = $db->fetchOne("SELECT * FROM users WHERE username = ?", ['admin']);
    echo "<p>Query แบบง่าย (ไม่มี JOIN): " . ($simple ? "✅ พบข้อมูล" : "❌ ไม่พบ") . "</p>";
} else {
    echo "<p style='color:green'>✅ Query สำเร็จ คืนค่าได้</p>";
    echo "<pre>" . print_r($result, true) . "</pre>";
}