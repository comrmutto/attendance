<?php
/**
 * functions.php - ฟังก์ชันช่วยเหลือต่างๆ
 */

/**
 * แสดงผลข้อมูลแบบ Debug
 */
function debug($data, $exit = false) {
    echo '<pre>';
    if (is_array($data) || is_object($data)) {
        print_r($data);
    } else {
        var_dump($data);
    }
    echo '</pre>';
    
    if ($exit) {
        exit;
    }
}

/**
 * Redirect ไปยัง URL ที่กำหนด
 */
function redirect($url, $statusCode = 302) {
    header('Location: ' . $url, true, $statusCode);
    exit;
}

/**
 * สร้าง URL สำหรับระบบ
 */
function url($path = '') {
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * สร้าง URL สำหรับ assets
 */
function asset($path) {
    return APP_URL . '/assets/' . ltrim($path, '/');
}

/**
 * แปลง user_id ให้อยู่ในรูปแบบ MRTXXX
 */
function formatUserId($userId) {
    $prefix = 'MRT';
    $padded = str_pad($userId, 3, '0', STR_PAD_LEFT);
    return $prefix . $padded;
}

/**
 * แปลงวันที่ให้อยู่ในรูปแบบไทย
 */
function thaiDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '-';
    
    $thaiMonths = [
        '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม',
        '04' => 'เมษายน', '05' => 'พฤษภาคม', '06' => 'มิถุนายน',
        '07' => 'กรกฎาคม', '08' => 'สิงหาคม', '09' => 'กันยายน',
        '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
    ];
    
    $thaiShortMonths = [
        '01' => 'ม.ค.', '02' => 'ก.พ.', '03' => 'มี.ค.',
        '04' => 'เม.ย.', '05' => 'พ.ค.', '06' => 'มิ.ย.',
        '07' => 'ก.ค.', '08' => 'ส.ค.', '09' => 'ก.ย.',
        '10' => 'ต.ค.', '11' => 'พ.ย.', '12' => 'ธ.ค.'
    ];
    
    $timestamp = strtotime($date);
    $year = date('Y', $timestamp) + 543;
    $month = date('m', $timestamp);
    $day = date('d', $timestamp);
    $time = date('H:i', $timestamp);
    
    switch ($format) {
        case 'full':
            return "{$day} {$thaiMonths[$month]} {$year} เวลา {$time} น.";
        case 'date':
            return "{$day} {$thaiMonths[$month]} {$year}";
        case 'short':
            return "{$day} {$thaiShortMonths[$month]} {$year}";
        case 'time':
            return $time;
        default:
            return date($format, $timestamp);
    }
}

/**
 * คำนวณอายุงาน
 */
function calculateTenure($hireDate) {
    $hire = new DateTime($hireDate);
    $now = new DateTime();
    $diff = $hire->diff($now);
    
    $years = $diff->y;
    $months = $diff->m;
    $days = $diff->d;
    
    if ($years > 0) {
        return "{$years} ปี {$months} เดือน";
    } elseif ($months > 0) {
        return "{$months} เดือน {$days} วัน";
    } else {
        return "{$days} วัน";
    }
}

/**
 * คำนวณเวลาทำงาน
 */
function calculateWorkHours($checkIn, $checkOut) {
    if (empty($checkIn) || empty($checkOut)) {
        return 0;
    }
    
    $in = new DateTime($checkIn);
    $out = new DateTime($checkOut);
    $diff = $in->diff($out);
    
    return round($diff->h + ($diff->i / 60), 2);
}

/**
 * ตรวจสอบว่าสายหรือไม่
 */
function isLate($checkIn, $shiftStart, $gracePeriod = 15) {
    if (empty($checkIn)) return false;
    
    $checkInTime = new DateTime($checkIn);
    $shiftStartTime = new DateTime($checkIn->format('Y-m-d') . ' ' . $shiftStart);
    $graceEndTime = clone $shiftStartTime;
    $graceEndTime->modify("+{$gracePeriod} minutes");
    
    return $checkInTime > $graceEndTime;
}

/**
 * หาวันที่เริ่มรอบปัจจุบัน (ตัดวันที่ 21)
 */
function getCurrentCutoffStart() {
    $today = new DateTime();
    $cutoffDay = CUTOFF_DAY;
    $currentMonth = $today->format('Y-m');
    
    if ($today->format('d') >= $cutoffDay) {
        return date('Y-m-') . str_pad($cutoffDay, 2, '0', STR_PAD_LEFT);
    } else {
        $lastMonth = new DateTime('first day of last month');
        return $lastMonth->format('Y-m-') . str_pad($cutoffDay, 2, '0', STR_PAD_LEFT);
    }
}

/**
 * หาวันที่สิ้นสุดรอบปัจจุบัน
 */
function getCurrentCutoffEnd() {
    $start = new DateTime(getCurrentCutoffStart());
    $end = clone $start;
    $end->modify('+1 month -1 day');
    return $end->format('Y-m-d');
}

/**
 * สร้าง CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * ตรวจสอบ CSRF token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * แสดง flash message
 */
function flash($key, $message = null, $type = 'info') {
    if ($message !== null) {
        $_SESSION['flash'][$key] = [
            'message' => $message,
            'type' => $type
        ];
    } elseif (isset($_SESSION['flash'][$key])) {
        $flash = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        
        return '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">
                    ' . $flash['message'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
    
    return null;
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * สร้าง pagination
 */
function paginate($currentPage, $totalPages, $url = '?page=%d') {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav><ul class="pagination">';
    
    // Previous button
    $prevPage = $currentPage - 1;
    $html .= '<li class="page-item ' . ($currentPage == 1 ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . sprintf($url, $prevPage) . '">ก่อนหน้า</a>';
    $html .= '</li>';
    
    // Page numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == 1 || $i == $totalPages || ($i >= $currentPage - 2 && $i <= $currentPage + 2)) {
            $html .= '<li class="page-item ' . ($i == $currentPage ? 'active' : '') . '">';
            $html .= '<a class="page-link" href="' . sprintf($url, $i) . '">' . $i . '</a>';
            $html .= '</li>';
        } elseif ($i == $currentPage - 3 || $i == $currentPage + 3) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Next button
    $nextPage = $currentPage + 1;
    $html .= '<li class="page-item ' . ($currentPage == $totalPages ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . sprintf($url, $nextPage) . '">ถัดไป</a>';
    $html .= '</li>';
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * อัปโหลดไฟล์
 */
function uploadFile($file, $targetDir = null) {
    if ($targetDir === null) {
        $targetDir = UPLOADS_PATH;
    }
    
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $fileName = time() . '_' . basename($file['name']);
    $targetFile = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'ไฟล์มีขนาดใหญ่เกินไป'];
    }
    
    // Check file type
    if (!in_array($fileType, ALLOWED_EXTENSIONS)) {
        return ['error' => 'ประเภทไฟล์ไม่ถูกต้อง'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => true, 'filename' => $fileName];
    }
    
    return ['error' => 'ไม่สามารถอัปโหลดไฟล์ได้'];
}

/**
 * ส่งอีเมล
 */
function sendMail($to, $subject, $message, $from = null) {
    if ($from === null) {
        $from = MAIL_FROM_ADDRESS;
    }
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . $from . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Log กิจกรรม
 */
function logActivity($userId, $action, $description, $ip = null) {
    if ($ip === null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    $db = Database::getInstance();
    
    $data = [
        'user_id' => $userId,
        'action' => $action,
        'description' => $description,
        'ip_address' => $ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return $db->insert('activity_logs', $data);
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ipaddress = '';
    
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    
    return $ipaddress;
}