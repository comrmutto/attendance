<?php
/**
 * get_dashboard.php - API สำหรับดึงข้อมูลแสดงบน Dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';

// ตรวจสอบการ login
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$date = $_GET['date'] ?? date('Y-m-d');
$departmentId = $_GET['department_id'] ?? null;

// ตรวจสอบสิทธิ์การดูแผนก
if ($departmentId && !Auth::canViewDepartment($departmentId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    return;
}

try {
    $response = [
        'success' => true,
        'date' => $date,
        'date_formatted' => thaiDate($date, 'full'),
        'stats' => getDashboardStats($db, $date, $departmentId),
        'charts' => getChartData($db, $date, $departmentId),
        'departments' => getDepartmentSummary($db, $date, $departmentId),
        'recent_activity' => getRecentActivity($db, $date, $departmentId),
        'top_employees' => getTopEmployees($db, $date, $departmentId),
        'shift_summary' => getShiftSummary($db, $date, $departmentId),
        'attendance_trend' => getAttendanceTrend($db, $date, $departmentId),
        'notifications' => getNotifications($db, $date, $departmentId)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => APP_ENV === 'development' ? $e->getMessage() : null
    ]);
}

/**
 * ดึงสถิติหลัก
 */
function getDashboardStats($db, $date, $departmentId) {
    // จำนวนพนักงานทั้งหมด
    $sqlTotal = "SELECT COUNT(*) FROM employees WHERE status = 1";
    $paramsTotal = [];
    
    if ($departmentId) {
        $sqlTotal .= " AND department_id = ?";
        $paramsTotal[] = $departmentId;
    }
    
    $totalEmployees = (int)$db->fetchValue($sqlTotal, $paramsTotal);
    
    // จำนวนพนักงานที่มาวันนี้
    $sqlPresent = "SELECT COUNT(DISTINCT a.employee_id)
                   FROM attendance_records a
                   JOIN employees e ON a.employee_id = e.id
                   WHERE a.date = ? AND a.check_in_time IS NOT NULL";
    $paramsPresent = [$date];
    
    if ($departmentId) {
        $sqlPresent .= " AND e.department_id = ?";
        $paramsPresent[] = $departmentId;
    }
    
    $presentToday = (int)$db->fetchValue($sqlPresent, $paramsPresent);
    
    // จำนวนพนักงานที่สาย
    $sqlLate = "SELECT COUNT(DISTINCT a.employee_id)
                FROM attendance_records a
                JOIN employees e ON a.employee_id = e.id
                WHERE a.date = ? AND a.check_in_status = 'late'";
    $paramsLate = [$date];
    
    if ($departmentId) {
        $sqlLate .= " AND e.department_id = ?";
        $paramsLate[] = $departmentId;
    }
    
    $lateToday = (int)$db->fetchValue($sqlLate, $paramsLate);
    
    // จำนวนพนักงานที่ทำโอที
    $sqlOT = "SELECT COUNT(DISTINCT a.employee_id)
              FROM attendance_records a
              JOIN employees e ON a.employee_id = e.id
              WHERE a.date = ? AND a.overtime_hours > 0";
    $paramsOT = [$date];
    
    if ($departmentId) {
        $sqlOT .= " AND e.department_id = ?";
        $paramsOT[] = $departmentId;
    }
    
    $overtimeToday = (int)$db->fetchValue($sqlOT, $paramsOT);
    
    // จำนวนพนักงานที่ลา
    $sqlLeave = "SELECT COUNT(*) FROM leave_requests 
                 WHERE ? BETWEEN start_date AND end_date 
                 AND status = 'approved'";
    $paramsLeave = [$date];
    
    if ($departmentId) {
        $sqlLeave .= " AND employee_id IN (SELECT id FROM employees WHERE department_id = ?)";
        $paramsLeave[] = $departmentId;
    }
    
    $onLeave = (int)$db->fetchValue($sqlLeave, $paramsLeave);
    
    // คำนวณอัตราการเข้าทำงาน
    $attendanceRate = $totalEmployees > 0 
        ? round(($presentToday / $totalEmployees) * 100, 1) 
        : 0;
    
    // เวลาเฉลี่ยที่มาทำงาน
    $sqlAvgTime = "SELECT AVG(TIME_TO_SEC(TIME(check_in_time))) 
                   FROM attendance_records 
                   WHERE date = ? AND check_in_time IS NOT NULL";
    $paramsAvg = [$date];
    
    if ($departmentId) {
        $sqlAvgTime .= " AND employee_id IN (SELECT id FROM employees WHERE department_id = ?)";
        $paramsAvg[] = $departmentId;
    }
    
    $avgCheckIn = $db->fetchValue($sqlAvgTime, $paramsAvg);
    $avgCheckInTime = $avgCheckIn ? date('H:i', $avgCheckIn) : '-';
    
    return [
        'total_employees' => $totalEmployees,
        'present_today' => $presentToday,
        'absent_today' => $totalEmployees - $presentToday - $onLeave,
        'late_today' => $lateToday,
        'overtime_today' => $overtimeToday,
        'on_leave' => $onLeave,
        'attendance_rate' => $attendanceRate,
        'avg_check_in_time' => $avgCheckInTime,
        'peak_hour' => getPeakHour($db, $date, $departmentId)
    ];
}

/**
 * ดึงข้อมูลสำหรับ Charts
 */
function getChartData($db, $date, $departmentId) {
    // ข้อมูลการเข้างานรายชั่วโมง
    $hourlyData = getHourlyAttendance($db, $date, $departmentId);
    
    // ข้อมูลแยกตามแผนก
    $deptData = getDepartmentChartData($db, $date, $departmentId);
    
    // ข้อมูลแยกตามกะ
    $shiftData = getShiftChartData($db, $date, $departmentId);
    
    // ข้อมูลสถานะการเข้างาน
    $statusData = getStatusChartData($db, $date, $departmentId);
    
    return [
        'hourly' => $hourlyData,
        'by_department' => $deptData,
        'by_shift' => $shiftData,
        'by_status' => $statusData
    ];
}

/**
 * ข้อมูลรายชั่วโมง
 */
function getHourlyAttendance($db, $date, $departmentId) {
    $sql = "SELECT 
            HOUR(check_in_time) as hour,
            COUNT(*) as count
            FROM attendance_records a
            JOIN employees e ON a.employee_id = e.id
            WHERE a.date = ? AND a.check_in_time IS NOT NULL";
    $params = [$date];
    
    if ($departmentId) {
        $sql .= " AND e.department_id = ?";
        $params[] = $departmentId;
    }
    
    $sql .= " GROUP BY HOUR(check_in_time) ORDER BY hour";
    
    $results = $db->fetchAll($sql, $params);
    
    $labels = [];
    $checkIn = [];
    $checkOut = [];
    
    for ($i = 0; $i < 24; $i++) {
        $labels[] = $i . ':00';
        $checkIn[] = 0;
        $checkOut[] = 0;
    }
    
    foreach ($results as $row) {
        $checkIn[(int)$row['hour']] = (int)$row['count'];
    }
    
    // ข้อมูล check-out
    $sqlOut = "SELECT 
               HOUR(check_out_time) as hour,
               COUNT(*) as count
               FROM attendance_records a
               JOIN employees e ON a.employee_id = e.id
               WHERE a.date = ? AND a.check_out_time IS NOT NULL";
    $paramsOut = [$date];
    
    if ($departmentId) {
        $sqlOut .= " AND e.department_id = ?";
        $paramsOut[] = $departmentId;
    }
    
    $sqlOut .= " GROUP BY HOUR(check_out_time)";
    
    $resultsOut = $db->fetchAll($sqlOut, $paramsOut);
    
    foreach ($resultsOut as $row) {
        $checkOut[(int)$row['hour']] = (int)$row['count'];
    }
    
    return [
        'labels' => $labels,
        'check_in' => $checkIn,
        'check_out' => $checkOut
    ];
}

/**
 * ข้อมูลแยกตามแผนก (สำหรับ Chart)
 */
function getDepartmentChartData($db, $date, $departmentId) {
    if ($departmentId) {
        // ถ้าเลือกแผนกเดียว ให้แสดงรายละเอียดภายในแผนก
        $sql = "SELECT 
                CONCAT(e.first_name, ' ', e.last_name) as name,
                CASE WHEN a.check_in_time IS NOT NULL THEN 1 ELSE 0 END as status
                FROM employees e
                LEFT JOIN attendance_records a ON e.id = a.employee_id AND a.date = ?
                WHERE e.department_id = ? AND e.status = 1";
        
        $results = $db->fetchAll($sql, [$date, $departmentId]);
        
        $present = 0;
        $absent = 0;
        
        foreach ($results as $row) {
            if ($row['status']) {
                $present++;
            } else {
                $absent++;
            }
        }
        
        return [
            'labels' => ['มาทำงาน', 'ไม่มาทำงาน'],
            'values' => [$present, $absent],
            'colors' => ['#198754', '#dc3545']
        ];
    } else {
        // ถ้าไม่เลือกแผนก ให้แสดงทุกแผนก
        $sql = "SELECT 
                d.name,
                COUNT(DISTINCT e.id) as total,
                COUNT(DISTINCT CASE WHEN a.check_in_time IS NOT NULL THEN a.employee_id END) as present
                FROM departments d
                LEFT JOIN employees e ON d.id = e.department_id AND e.status = 1
                LEFT JOIN attendance_records a ON e.id = a.employee_id AND a.date = ?
                WHERE d.status = 1
                GROUP BY d.id, d.name
                ORDER BY d.name";
        
        $results = $db->fetchAll($sql, [$date]);
        
        $labels = [];
        $present = [];
        $absent = [];
        $colors = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d', '#0dcaf0', '#6610f2', '#d63384'];
        
        foreach ($results as $i => $row) {
            $labels[] = $row['name'];
            $present[] = (int)$row['present'];
            $absent[] = (int)$row['total'] - (int)$row['present'];
        }
        
        return [
            'labels' => $labels,
            'present' => $present,
            'absent' => $absent,
            'colors' => $colors
        ];
    }
}

/**
 * ข้อมูลแยกตามกะ
 */
function getShiftChartData($db, $date, $departmentId) {
    $sql = "SELECT 
            s.name,
            COUNT(a.id) as count
            FROM shifts s
            LEFT JOIN attendance_records a ON s.id = a.shift_id AND a.date = ?
            LEFT JOIN employees e ON a.employee_id = e.id
            WHERE 1=1";
    $params = [$date];
    
    if ($departmentId) {
        $sql .= " AND e.department_id = ?";
        $params[] = $departmentId;
    }
    
    $sql .= " GROUP BY s.id, s.name";
    
    $results = $db->fetchAll($sql, $params);
    
    $labels = [];
    $values = [];
    $colors = ['#0d6efd', '#6610f2'];
    
    foreach ($results as $row) {
        $labels[] = $row['name'];
        $values[] = (int)$row['count'];
    }
    
    return [
        'labels' => $labels,
        'values' => $values,
        'colors' => $colors
    ];
}

/**
 * ข้อมูลสถานะการเข้างาน
 */
function getStatusChartData($db, $date, $departmentId) {
    $sql = "SELECT 
            CASE 
                WHEN a.check_in_status = 'late' THEN 'มาสาย'
                WHEN a.check_in_status = 'on_time' THEN 'ตรงเวลา'
                WHEN a.check_in_status = 'early' THEN 'มาก่อนเวลา'
                ELSE 'ไม่ทราบ'
            END as status,
            COUNT(*) as count
            FROM attendance_records a
            JOIN employees e ON a.employee_id = e.id
            WHERE a.date = ? AND a.check_in_time IS NOT NULL";
    $params = [$date];
    
    if ($departmentId) {
        $sql .= " AND e.department_id = ?";
        $params[] = $departmentId;
    }
    
    $sql .= " GROUP BY a.check_in_status";
    
    $results = $db->fetchAll($sql, $params);
    
    $labels = [];
    $values = [];
    $colors = [
        'on_time' => '#198754',
        'late' => '#ffc107',
        'early' => '#0dcaf0'
    ];
    
    foreach ($results as $row) {
        $labels[] = $row['status'];
        $values[] = (int)$row['count'];
    }
    
    return [
        'labels' => $labels,
        'values' => $values,
        'colors' => array_values($colors)
    ];
}

/**
 * สรุปข้อมูลแยกตามแผนก
 */
function getDepartmentSummary($db, $date, $departmentId) {
    $sql = "SELECT 
            d.id,
            d.name,
            d.code,
            COUNT(DISTINCT e.id) as total_employees,
            COUNT(DISTINCT CASE WHEN a.check_in_time IS NOT NULL THEN a.employee_id END) as present,
            COUNT(DISTINCT CASE WHEN a.check_in_status = 'late' THEN a.employee_id END) as late,
            COUNT(DISTINCT CASE WHEN a.overtime_hours > 0 THEN a.employee_id END) as overtime
            FROM departments d
            LEFT JOIN employees e ON d.id = e.department_id AND e.status = 1
            LEFT JOIN attendance_records a ON e.id = a.employee_id AND a.date = ?
            WHERE d.status = 1";
    $params = [$date];
    
    if ($departmentId) {
        $sql .= " AND d.id = ?";
        $params[] = $departmentId;
    }
    
    $sql .= " GROUP BY d.id, d.name, d.code ORDER BY d.name";
    
    $results = $db->fetchAll($sql, $params);
    
    foreach ($results as &$row) {
        $row['absent'] = $row['total_employees'] - $row['present'];
        $row['attendance_rate'] = $row['total_employees'] > 0 
            ? round(($row['present'] / $row['total_employees']) * 100, 1) 
            : 0;
        $row['present_percent'] = $row['attendance_rate'];
    }
    
    return $results;
}

/**
 * กิจกรรมล่าสุด
 */
function getRecentActivity($db, $date, $departmentId, $limit = 10) {
    $sql = "SELECT 
            a.scan_time,
            a.inout_status,
            e.user_id,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            d.name as department_name,
            CASE 
                WHEN a.inout_status = 1 THEN 'check_in'
                WHEN a.inout_status = 2 THEN 'check_out'
                ELSE 'unknown'
            END as activity_type
            FROM attendance_logs a
            JOIN employees e ON a.user_id = e.user_id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE DATE(a.scan_time) = ?";
    $params = [$date];
    
    if ($departmentId) {
        $sql .= " AND e.department_id = ?";
        $params[] = $departmentId;
    }
    
    $sql .= " ORDER BY a.scan_time DESC LIMIT ?";
    $params[] = $limit;
    
    $results = $db->fetchAll($sql, $params);
    
    foreach ($results as &$row) {
        $row['time'] = date('H:i:s', strtotime($row['scan_time']));
        $row['time_formatted'] = thaiDate($row['scan_time'], 'time');
        $row['user_id_formatted'] = formatUserId($row['user_id']);
        $row['icon'] = $row['activity_type'] == 'check_in' ? 'fa-sign-in-alt' : 'fa-sign-out-alt';
        $row['color'] = $row['activity_type'] == 'check_in' ? 'success' : 'info';
    }
    
    return $results;
}

/**
 * พนักงานดีเด่น
 */
function getTopEmployees($db, $date, $departmentId, $limit = 5) {
    $sql = "SELECT 
            e.id,
            e.user_id,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            d.name as department_name,
            COUNT(a.id) as days_present,
            AVG(a.work_hours) as avg_work_hours,
            SUM(CASE WHEN a.check_in_status = 'on_time' THEN 1 ELSE 0 END) as on_time_days
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN attendance_records a ON e.id = a.employee_id 
                AND a.date BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND ?
            WHERE e.status = 1";
    $params = [$date, $date];
    
    if ($departmentId) {
        $sql .= " AND e.department_id = ?";
        $params[] = $departmentId;
    }
    
    $sql .= " GROUP BY e.id, e.user_id, e.first_name, e.last_name, d.name
              HAVING days_present > 0
              ORDER BY on_time_days DESC, avg_work_hours DESC
              LIMIT ?";
    $params[] = $limit;
    
    $results = $db->fetchAll($sql, $params);
    
    foreach ($results as &$row) {
        $row['user_id_formatted'] = formatUserId($row['user_id']);
        $row['avg_work_hours'] = round($row['avg_work_hours'], 1);
        $row['attendance_rate'] = round(($row['days_present'] / 30) * 100, 1);
    }
    
    return $results;
}

/**
 * สรุปแยกตามกะ
 */
function getShiftSummary($db, $date, $departmentId) {
    $sql = "SELECT 
            s.id,
            s.name,
            s.start_time,
            s.end_time,
            COUNT(DISTINCT e.id) as total_employees,
            COUNT(DISTINCT CASE WHEN a.check_in_time IS NOT NULL THEN a.employee_id END) as present,
            COUNT(DISTINCT CASE WHEN a.check_in_status = 'late' THEN a.employee_id END) as late
            FROM shifts s
            CROSS JOIN employees e
            LEFT JOIN attendance_records a ON e.id = a.employee_id 
                AND a.date = ? AND a.shift_id = s.id
            WHERE e.status = 1 AND e.shift_id = s.id";
    $params = [$date];
    
    if ($departmentId) {
        $sql .= " AND e.department_id = ?";
        $params[] = $departmentId;
    }
    
    $sql .= " GROUP BY s.id, s.name, s.start_time, s.end_time";
    
    return $db->fetchAll($sql, $params);
}

/**
 * แนวโน้มการเข้างานย้อนหลัง
 */
function getAttendanceTrend($db, $date, $departmentId, $days = 7) {
    $sql = "SELECT 
            a.date,
            COUNT(DISTINCT a.employee_id) as present_count,
            COUNT(DISTINCT CASE WHEN a.check_in_status = 'late' THEN a.employee_id END) as late_count,
            AVG(a.work_hours) as avg_work_hours
            FROM attendance_records a
            JOIN employees e ON a.employee_id = e.id
            WHERE a.date BETWEEN DATE_SUB(?, INTERVAL ? DAY) AND ?";
    $params = [$date, $days - 1, $date];
    
    if ($departmentId) {
        $sql .= " AND e.department_id = ?";
        $params[] = $departmentId;
    }
    
    $sql .= " GROUP BY a.date ORDER BY a.date";
    
    $results = $db->fetchAll($sql, $params);
    
    $labels = [];
    $present = [];
    $late = [];
    
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days", strtotime($date)));
        $labels[] = thaiDate($d, 'short');
        
        $found = false;
        foreach ($results as $row) {
            if ($row['date'] == $d) {
                $present[] = (int)$row['present_count'];
                $late[] = (int)$row['late_count'];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $present[] = 0;
            $late[] = 0;
        }
    }
    
    return [
        'labels' => $labels,
        'present' => $present,
        'late' => $late
    ];
}

/**
 * การแจ้งเตือน
 */
function getNotifications($db, $date, $departmentId) {
    $notifications = [];
    
    // ตรวจสอบพนักงานที่ขาดงานติดต่อกัน
    $sqlAbsent = "SELECT 
                  e.user_id,
                  CONCAT(e.first_name, ' ', e.last_name) as name,
                  COUNT(*) as absent_days
                  FROM employees e
                  JOIN attendance_records a ON e.id = a.employee_id
                  WHERE a.date BETWEEN DATE_SUB(?, INTERVAL 7 DAY) AND ?
                  AND a.check_in_time IS NULL
                  AND e.status = 1";
    $paramsAbsent = [$date, $date];
    
    if ($departmentId) {
        $sqlAbsent .= " AND e.department_id = ?";
        $paramsAbsent[] = $departmentId;
    }
    
    $sqlAbsent .= " GROUP BY e.id, e.user_id, e.first_name, e.last_name
                    HAVING absent_days >= 3";
    
    $absentees = $db->fetchAll($sqlAbsent, $paramsAbsent);
    
    foreach ($absentees as $absent) {
        $notifications[] = [
            'type' => 'warning',
            'title' => 'ขาดงานติดต่อกัน',
            'message' => formatUserId($absent['user_id']) . ' ' . $absent['name'] . 
                        ' ขาดงาน ' . $absent['absent_days'] . ' วันติดต่อกัน',
            'icon' => 'fa-exclamation-triangle',
            'time' => 'ตอนนี้'
        ];
    }
    
    // ตรวจสอบพนักงานที่มาสายบ่อย
    $sqlLate = "SELECT 
                e.user_id,
                CONCAT(e.first_name, ' ', e.last_name) as name,
                COUNT(*) as late_days
                FROM employees e
                JOIN attendance_records a ON e.id = a.employee_id
                WHERE a.date BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND ?
                AND a.check_in_status = 'late'
                AND e.status = 1";
    $paramsLate = [$date, $date];
    
    if ($departmentId) {
        $sqlLate .= " AND e.department_id = ?";
        $paramsLate[] = $departmentId;
    }
    
    $sqlLate .= " GROUP BY e.id, e.user_id, e.first_name, e.last_name
                  HAVING late_days >= 5";
    
    $lateEmployees = $db->fetchAll($sqlLate, $paramsLate);
    
    foreach ($lateEmployees as $late) {
        $notifications[] = [
            'type' => 'info',
            'title' => 'มาสายบ่อย',
            'message' => formatUserId($late['user_id']) . ' ' . $late['name'] . 
                        ' มาสาย ' . $late['late_days'] . ' วัน ใน 30 วันที่ผ่านมา',
            'icon' => 'fa-clock',
            'time' => 'ตอนนี้'
        ];
    }
    
    return $notifications;
}

/**
 * หาชั่วโมงที่มีคนมาทำงานมากที่สุด
 */
function getPeakHour($db, $date, $departmentId) {
    $sql = "SELECT 
            HOUR(check_in_time) as hour,
            COUNT(*) as count
            FROM attendance_records a
            JOIN employees e ON a.employee_id = e.id
            WHERE a.date = ? AND a.check_in_time IS NOT NULL";
    $params = [$date];
    
    if ($departmentId) {
        $sql .= " AND e.department_id = ?";
        $params[] = $departmentId;
    }
    
    $sql .= " GROUP BY HOUR(check_in_time) ORDER BY count DESC LIMIT 1";
    
    $result = $db->fetchOne($sql, $params);
    
    return $result ? $result['hour'] . ':00' : '-';
}