<?php
/**
 * get_attendance.php - API สำหรับดึงข้อมูลการเข้างาน
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Attendance.php';

// ตรวจสอบการ login
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action);
            break;
            
        case 'POST':
            handlePostRequest($action);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => APP_ENV === 'development' ? $e->getMessage() : null
    ]);
}

/**
 * จัดการ GET requests
 */
function handleGetRequest($action) {
    switch ($action) {
        case 'list':
            getAttendanceList();
            break;
            
        case 'detail':
            getAttendanceDetail();
            break;
            
        case 'summary':
            getAttendanceSummary();
            break;
            
        case 'my-attendance':
            getMyAttendance();
            break;
            
        case 'export':
            exportAttendance();
            break;
            
        case 'stats':
            getAttendanceStats();
            break;
            
        case 'today':
            getTodayAttendance();
            break;
            
        case 'by-date':
            getAttendanceByDate();
            break;
            
        case 'by-employee':
            getAttendanceByEmployee();
            break;
            
        case 'by-department':
            getAttendanceByDepartment();
            break;
            
        case 'by-shift':
            getAttendanceByShift();
            break;
            
        case 'late':
            getLateEmployees();
            break;
            
        case 'absent':
            getAbsentEmployees();
            break;
            
        case 'overtime':
            getOvertimeEmployees();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

/**
 * จัดการ POST requests
 */
function handlePostRequest($action) {
    switch ($action) {
        case 'process':
            processAttendance();
            break;
            
        case 'update':
            updateAttendance();
            break;
            
        case 'manual-entry':
            manualAttendanceEntry();
            break;
            
        case 'bulk-update':
            bulkUpdateAttendance();
            break;
            
        case 'approve-overtime':
            approveOvertime();
            break;
            
        case 'request-correction':
            requestCorrection();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

/**
 * ดึงรายการการเข้างาน
 */
function getAttendanceList() {
    $db = Database::getInstance();
    
    // รับพารามิเตอร์
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : ITEMS_PER_PAGE;
    $offset = ($page - 1) * $limit;
    
    $filters = [
        'department_id' => $_GET['department_id'] ?? null,
        'employee_id' => $_GET['employee_id'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'shift_id' => $_GET['shift_id'] ?? null,
        'status' => $_GET['status'] ?? null,
        'search' => $_GET['search'] ?? null
    ];
    
    // ตรวจสอบสิทธิ์การดูข้อมูล
    $currentEmpId = $_SESSION['employee_id'] ?? null;
    $positionLevel = $_SESSION['position_level'] ?? 0;
    
    if ($positionLevel < POSITION_SUPERVISOR && !Auth::isAdmin()) {
        $filters['employee_id'] = $currentEmpId;
    }
    
    // สร้าง SQL
    $sql = "SELECT SQL_CALC_FOUND_ROWS 
            a.*,
            e.user_id,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            e.department_id,
            d.name as department_name,
            s.name as shift_name,
            s.start_time,
            s.end_time
            FROM attendance_records a
            JOIN employees e ON a.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN shifts s ON a.shift_id = s.id
            WHERE 1=1";
    
    $params = [];
    
    if ($filters['department_id']) {
        $sql .= " AND e.department_id = ?";
        $params[] = $filters['department_id'];
    }
    
    if ($filters['employee_id']) {
        $sql .= " AND a.employee_id = ?";
        $params[] = $filters['employee_id'];
    }
    
    if ($filters['date_from']) {
        $sql .= " AND a.date >= ?";
        $params[] = $filters['date_from'];
    }
    
    if ($filters['date_to']) {
        $sql .= " AND a.date <= ?";
        $params[] = $filters['date_to'];
    }
    
    if ($filters['shift_id']) {
        $sql .= " AND a.shift_id = ?";
        $params[] = $filters['shift_id'];
    }
    
    if ($filters['status']) {
        $sql .= " AND (a.check_in_status = ? OR a.check_out_status = ?)";
        $params[] = $filters['status'];
        $params[] = $filters['status'];
    }
    
    if ($filters['search']) {
        $sql .= " AND (e.user_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
        $searchTerm = "%{$filters['search']}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY a.date DESC, a.employee_id LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $records = $db->fetchAll($sql, $params);
    $totalRecords = $db->fetchValue("SELECT FOUND_ROWS()");
    
    // จัดรูปแบบข้อมูล
    foreach ($records as &$record) {
        $record['user_id_formatted'] = formatUserId($record['user_id']);
        $record['check_in_time_formatted'] = $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-';
        $record['check_out_time_formatted'] = $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '-';
        $record['date_formatted'] = thaiDate($record['date'], 'short');
        $record['status_badge'] = getStatusBadge($record);
        $record['work_hours_formatted'] = $record['work_hours'] ? number_format($record['work_hours'], 2) . ' ชม.' : '-';
    }
    
    echo json_encode([
        'success' => true,
        'data' => $records,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => (int)$totalRecords,
            'total_pages' => ceil($totalRecords / $limit)
        ],
        'filters' => $filters
    ]);
}

/**
 * ดึงรายละเอียดการเข้างาน
 */
function getAttendanceDetail() {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Attendance ID required']);
        return;
    }
    
    $db = Database::getInstance();
    
    $sql = "SELECT a.*,
            e.user_id,
            e.first_name,
            e.last_name,
            e.department_id,
            d.name as department_name,
            p.name as position_name,
            s.name as shift_name,
            s.start_time,
            s.end_time,
            s.grace_period,
            ci.raw as check_in_raw,
            co.raw as check_out_raw
            FROM attendance_records a
            JOIN employees e ON a.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN shifts s ON a.shift_id = s.id
            LEFT JOIN attendance_logs ci ON a.check_in_log_id = ci.id
            LEFT JOIN attendance_logs co ON a.check_out_log_id = co.id
            WHERE a.id = ?";
    
    $record = $db->fetchOne($sql, [$id]);
    
    if (!$record) {
        http_response_code(404);
        echo json_encode(['error' => 'Attendance record not found']);
        return;
    }
    
    // ตรวจสอบสิทธิ์การดู
    if (!Auth::canViewEmployee($record['employee_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    // จัดรูปแบบข้อมูล
    $record['user_id_formatted'] = formatUserId($record['user_id']);
    $record['fullname'] = $record['first_name'] . ' ' . $record['last_name'];
    $record['date_formatted'] = thaiDate($record['date'], 'full');
    $record['check_in_time_formatted'] = $record['check_in_time'] ? thaiDate($record['check_in_time'], 'full') : '-';
    $record['check_out_time_formatted'] = $record['check_out_time'] ? thaiDate($record['check_out_time'], 'full') : '-';
    $record['work_hours_formatted'] = $record['work_hours'] ? number_format($record['work_hours'], 2) . ' ชั่วโมง' : '-';
    $record['late_minutes_formatted'] = $record['late_minutes'] ? $record['late_minutes'] . ' นาที' : '-';
    $record['early_leave_minutes_formatted'] = $record['early_leave_minutes'] ? $record['early_leave_minutes'] . ' นาที' : '-';
    
    // เพิ่มข้อมูลการคำนวณ
    if ($record['check_in_time']) {
        $record['check_in_status_text'] = getCheckInStatusText($record);
        $record['check_in_status_color'] = getCheckInStatusColor($record);
    }
    
    if ($record['check_out_time']) {
        $record['check_out_status_text'] = getCheckOutStatusText($record);
        $record['check_out_status_color'] = getCheckOutStatusColor($record);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $record
    ]);
}

/**
 * ดึงสรุปข้อมูลการเข้างาน
 */
function getAttendanceSummary() {
    $db = Database::getInstance();
    
    $date = $_GET['date'] ?? date('Y-m-d');
    $departmentId = $_GET['department_id'] ?? null;
    
    // ตรวจสอบสิทธิ์
    if ($departmentId && !Auth::canViewDepartment($departmentId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    // จำนวนพนักงานทั้งหมด
    $sqlTotal = "SELECT COUNT(*) FROM employees WHERE status = 1";
    $paramsTotal = [];
    
    if ($departmentId) {
        $sqlTotal .= " AND department_id = ?";
        $paramsTotal[] = $departmentId;
    }
    
    $totalEmployees = $db->fetchValue($sqlTotal, $paramsTotal);
    
    // จำนวนพนักงานที่มาทำงานวันนี้
    $sqlPresent = "SELECT COUNT(DISTINCT a.employee_id)
                   FROM attendance_records a
                   JOIN employees e ON a.employee_id = e.id
                   WHERE a.date = ? AND a.check_in_time IS NOT NULL";
    $paramsPresent = [$date];
    
    if ($departmentId) {
        $sqlPresent .= " AND e.department_id = ?";
        $paramsPresent[] = $departmentId;
    }
    
    $presentToday = $db->fetchValue($sqlPresent, $paramsPresent);
    
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
    
    $lateToday = $db->fetchValue($sqlLate, $paramsLate);
    
    // จำนวนพนักงานที่ขาด
    $absentToday = $totalEmployees - $presentToday;
    
    // จำนวนพนักงานที่ทำโอที
    $sqlOvertime = "SELECT COUNT(DISTINCT a.employee_id)
                    FROM attendance_records a
                    JOIN employees e ON a.employee_id = e.id
                    WHERE a.date = ? AND a.overtime_hours > 0";
    $paramsOvertime = [$date];
    
    if ($departmentId) {
        $sqlOvertime .= " AND e.department_id = ?";
        $paramsOvertime[] = $departmentId;
    }
    
    $overtimeToday = $db->fetchValue($sqlOvertime, $paramsOvertime);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'date' => $date,
            'date_formatted' => thaiDate($date, 'full'),
            'total_employees' => (int)$totalEmployees,
            'present_today' => (int)$presentToday,
            'absent_today' => (int)$absentToday,
            'late_today' => (int)$lateToday,
            'overtime_today' => (int)$overtimeToday,
            'attendance_rate' => $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100, 1) : 0
        ]
    ]);
}

/**
 * ดึงข้อมูลการเข้างานของตัวเอง
 */
function getMyAttendance() {
    $employeeId = $_SESSION['employee_id'] ?? null;
    
    if (!$employeeId) {
        http_response_code(400);
        echo json_encode(['error' => 'Employee not found']);
        return;
    }
    
    $_GET['employee_id'] = $employeeId;
    getAttendanceList();
}

/**
 * ส่งออกข้อมูล Excel
 */
function exportAttendance() {
    require_once __DIR__ . '/../vendor/phpspreadsheet/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php';
    require_once __DIR__ . '/../vendor/phpspreadsheet/phpspreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php';
    
    $db = Database::getInstance();
    
    // รับพารามิเตอร์
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-t');
    $departmentId = $_GET['department_id'] ?? null;
    $employeeId = $_GET['employee_id'] ?? null;
    
    // ตรวจสอบสิทธิ์
    if ($departmentId && !Auth::canViewDepartment($departmentId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    // ดึงข้อมูล
    $sql = "SELECT 
            e.user_id,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            d.name as department_name,
            p.name as position_name,
            a.date,
            a.check_in_time,
            a.check_out_time,
            a.check_in_status,
            a.check_out_status,
            a.work_hours,
            a.overtime_hours,
            a.late_minutes,
            a.early_leave_minutes
            FROM attendance_records a
            JOIN employees e ON a.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE a.date BETWEEN ? AND ?";
    
    $params = [$dateFrom, $dateTo];
    
    if ($departmentId) {
        $sql .= " AND e.department_id = ?";
        $params[] = $departmentId;
    }
    
    if ($employeeId) {
        $sql .= " AND a.employee_id = ?";
        $params[] = $employeeId;
    }
    
    $sql .= " ORDER BY a.date, e.department_id, e.first_name";
    
    $records = $db->fetchAll($sql, $params);
    
    // สร้าง Excel file
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // ตั้งค่าหัวตาราง
    $headers = [
        'A1' => 'รหัสพนักงาน',
        'B1' => 'ชื่อ-สกุล',
        'C1' => 'แผนก',
        'D1' => 'ตำแหน่ง',
        'E1' => 'วันที่',
        'F1' => 'เวลาเข้า',
        'G1' => 'เวลาออก',
        'H1' => 'สถานะเข้า',
        'I1' => 'สถานะออก',
        'J1' => 'ชั่วโมงทำงาน',
        'K1' => 'โอที',
        'L1' => 'สาย (นาที)',
        'M1' => 'กลับก่อน (นาที)'
    ];
    
    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
        $sheet->getStyle($cell)->getFont()->setBold(true);
        $sheet->getStyle($cell)->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FFE0E0E0');
    }
    
    // ใส่ข้อมูล
    $row = 2;
    foreach ($records as $record) {
        $sheet->setCellValue('A' . $row, formatUserId($record['user_id']));
        $sheet->setCellValue('B' . $row, $record['employee_name']);
        $sheet->setCellValue('C' . $row, $record['department_name']);
        $sheet->setCellValue('D' . $row, $record['position_name']);
        $sheet->setCellValue('E' . $row, thaiDate($record['date'], 'short'));
        $sheet->setCellValue('F' . $row, $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-');
        $sheet->setCellValue('G' . $row, $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '-');
        $sheet->setCellValue('H' . $row, getCheckInStatusText($record));
        $sheet->setCellValue('I' . $row, getCheckOutStatusText($record));
        $sheet->setCellValue('J' . $row, $record['work_hours']);
        $sheet->setCellValue('K' . $row, $record['overtime_hours']);
        $sheet->setCellValue('L' . $row, $record['late_minutes']);
        $sheet->setCellValue('M' . $row, $record['early_leave_minutes']);
        
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'M') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // ส่งไฟล์
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="attendance_' . date('Ymd') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * ประมวลผลข้อมูลการเข้างาน
 */
function processAttendance() {
    // ตรวจสอบสิทธิ์ (เฉพาะ supervisor ขึ้นไป)
    if (!Auth::hasLevel(POSITION_SUPERVISOR)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $date = $_POST['date'] ?? date('Y-m-d');
    
    // เรียก stored procedure
    $db = Database::getInstance();
    $db->query("CALL process_daily_attendance(?)", [$date]);
    
    // บันทึกประวัติการประมวลผล
    $db->insert('process_logs', [
        'process_date' => $date,
        'processed_by' => $_SESSION['user_id'],
        'processed_at' => date('Y-m-d H:i:s'),
        'records_count' => $db->fetchValue("SELECT COUNT(*) FROM attendance_records WHERE date = ?", [$date])
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'ประมวลผลข้อมูลวันที่ ' . thaiDate($date, 'short') . ' เรียบร้อย'
    ]);
}

/**
 * บันทึกการเข้างานด้วยตนเอง (กรณีพิเศษ)
 */
function manualAttendanceEntry() {
    // ตรวจสอบสิทธิ์
    if (!Auth::hasLevel(POSITION_SUPERVISOR)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['employee_id', 'date', 'check_in_time', 'shift_id'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field {$field} is required"]);
            return;
        }
    }
    
    $db = Database::getInstance();
    
    // ตรวจสอบว่ามีข้อมูลซ้ำหรือไม่
    $existing = $db->fetchOne(
        "SELECT id FROM attendance_records WHERE employee_id = ? AND date = ?",
        [$data['employee_id'], $data['date']]
    );
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['error' => 'Attendance record already exists for this date']);
        return;
    }
    
    // บันทึกข้อมูล
    $recordId = $db->insert('attendance_records', [
        'employee_id' => $data['employee_id'],
        'shift_id' => $data['shift_id'],
        'date' => $data['date'],
        'check_in_time' => $data['check_in_time'],
        'check_out_time' => $data['check_out_time'] ?? null,
        'check_in_status' => 'manual',
        'check_out_status' => $data['check_out_time'] ? 'manual' : 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    // บันทึก log
    $db->insert('attendance_logs', [
        'device_ip' => 'MANUAL',
        'user_id' => $db->fetchValue("SELECT user_id FROM employees WHERE id = ?", [$data['employee_id']]),
        'scan_time' => $data['check_in_time'],
        'inout_status' => 1,
        'raw' => json_encode(['manual_entry' => true, 'entered_by' => $_SESSION['user_id']]),
        'processed' => 1,
        'attendance_id' => $recordId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'บันทึกข้อมูลเรียบร้อย',
        'record_id' => $recordId
    ]);
}

/**
 * ฟังก์ชันช่วยเหลือ
 */
function getStatusBadge($record) {
    if (!$record['check_in_time']) {
        return '<span class="badge bg-danger">ขาดงาน</span>';
    }
    
    if ($record['check_in_status'] == 'late') {
        return '<span class="badge bg-warning">มาสาย</span>';
    }
    
    if ($record['check_out_status'] == 'early_leave') {
        return '<span class="badge bg-warning">กลับก่อน</span>';
    }
    
    if ($record['overtime_hours'] > 0) {
        return '<span class="badge bg-success">ทำงานปกติ + OT</span>';
    }
    
    return '<span class="badge bg-success">ทำงานปกติ</span>';
}

function getCheckInStatusText($record) {
    if (!$record['check_in_time']) return 'ไม่พบข้อมูล';
    if ($record['check_in_status'] == 'late') return 'มาสาย';
    if ($record['check_in_status'] == 'early') return 'มาก่อนเวลา';
    if ($record['check_in_status'] == 'on_time') return 'ตรงเวลา';
    if ($record['check_in_status'] == 'manual') return 'บันทึกด้วยตนเอง';
    return $record['check_in_status'];
}

function getCheckInStatusColor($record) {
    if (!$record['check_in_time']) return 'secondary';
    if ($record['check_in_status'] == 'late') return 'warning';
    if ($record['check_in_status'] == 'early') return 'info';
    if ($record['check_in_status'] == 'on_time') return 'success';
    return 'primary';
}

function getCheckOutStatusText($record) {
    if (!$record['check_out_time']) return 'ไม่พบข้อมูล';
    if ($record['check_out_status'] == 'early_leave') return 'กลับก่อนเวลา';
    if ($record['check_out_status'] == 'overtime') return 'ทำโอที';
    if ($record['check_out_status'] == 'on_time') return 'ตรงเวลา';
    if ($record['check_out_status'] == 'manual') return 'บันทึกด้วยตนเอง';
    return $record['check_out_status'];
}

function getCheckOutStatusColor($record) {
    if (!$record['check_out_time']) return 'secondary';
    if ($record['check_out_status'] == 'early_leave') return 'warning';
    if ($record['check_out_status'] == 'overtime') return 'success';
    if ($record['check_out_status'] == 'on_time') return 'success';
    return 'primary';
}