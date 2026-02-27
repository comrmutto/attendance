<?php
/**
 * AttendanceController.php - Controller สำหรับจัดการข้อมูลการเข้างาน
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Attendance.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Shift.php';

class AttendanceController {
    
    /**
     * Constructor - ตรวจสอบการ login
     */
    public function __construct() {
        requireLogin();
    }
    
    /**
     * แสดงรายการการเข้างาน
     */
    public function index() {
        $page = $_GET['page'] ?? 1;
        $filters = [
            'department_id' => $_GET['department_id'] ?? null,
            'employee_id' => $_GET['employee_id'] ?? null,
            'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
            'shift_id' => $_GET['shift_id'] ?? null,
            'status' => $_GET['status'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        
        // จำกัดสิทธิ์การดู
        if (!Auth::isAdmin() && !Auth::isGM()) {
            if (Auth::hasLevel(POSITION_MANAGER)) {
                // ผู้จัดการดูได้เฉพาะพนักงานในแผนก
                $filters['department_id'] = $_SESSION['department_id'];
            } elseif (Auth::hasLevel(POSITION_SUPERVISOR)) {
                // หัวหน้าดูลูกน้อง
                $employeeIds = $this->getSubordinateIds();
                if (!empty($employeeIds)) {
                    $filters['employee_id'] = $employeeIds;
                } else {
                    $filters['employee_id'] = $_SESSION['employee_id'];
                }
            } else {
                // พนักงานทั่วไปดูเฉพาะตัวเอง
                $filters['employee_id'] = $_SESSION['employee_id'];
            }
        }
        
        $attendance = Attendance::getRecords($filters, $page);
        
        // ดึงข้อมูลสำหรับ filter
        $db = Database::getInstance();
        $departments = $db->fetchAll("SELECT id, name FROM departments WHERE status = 1 ORDER BY name");
        $shifts = Shift::getList();
        
        $data = [
            'attendance' => $attendance['data'],
            'pagination' => [
                'current_page' => $attendance['page'],
                'total_pages' => $attendance['pages'],
                'total' => $attendance['total']
            ],
            'filters' => $filters,
            'departments' => $departments,
            'shifts' => $shifts,
            'summary' => Attendance::getSummary($filters['date_to'], $filters['department_id'])
        ];
        
        $this->view('attendance/index', $data);
    }
    
    /**
     * แสดงรายละเอียดการเข้างาน
     */
    public function view() {
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            Session::flash('error', 'ไม่พบข้อมูล');
            redirect('/attendance');
        }
        
        $attendance = new Attendance($id);
        
        if (!$attendance->getId()) {
            Session::flash('error', 'ไม่พบข้อมูล');
            redirect('/attendance');
        }
        
        // ตรวจสอบสิทธิ์
        if (!Auth::canViewEmployee($attendance->employee_id)) {
            $this->forbidden();
            return;
        }
        
        // ดึงข้อมูลพนักงาน
        $employee = new Employee($attendance->employee_id);
        
        // ดึงข้อมูล logs
        $db = Database::getInstance();
        $logs = $db->fetchAll(
            "SELECT * FROM attendance_logs 
             WHERE attendance_id = ? OR (user_id = ? AND DATE(scan_time) = ?)
             ORDER BY scan_time",
            [$id, $employee->user_id, $attendance->date]
        );
        
        $data = [
            'attendance' => $attendance->toArray(),
            'employee' => $employee->toArray(),
            'logs' => $logs
        ];
        
        $this->view('attendance/view', $data);
    }
    
    /**
     * แสดงการเข้างานของตัวเอง
     */
    public function myAttendance() {
        $_GET['employee_id'] = $_SESSION['employee_id'];
        $this->index();
    }
    
    /**
     * แสดงรายงาน
     */
    public function report() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_SUPERVISOR)) {
            $this->forbidden();
            return;
        }
        
        $type = $_GET['type'] ?? 'daily';
        $date = $_GET['date'] ?? date('Y-m-d');
        $month = $_GET['month'] ?? date('Y-m');
        $departmentId = $_GET['department_id'] ?? null;
        
        $data = [
            'type' => $type,
            'date' => $date,
            'month' => $month,
            'department_id' => $departmentId
        ];
        
        switch ($type) {
            case 'daily':
                $data['report'] = $this->getDailyReport($date, $departmentId);
                break;
            case 'monthly':
                $data['report'] = $this->getMonthlyReport($month, $departmentId);
                break;
            case 'summary':
                $data['report'] = $this->getSummaryReport($month, $departmentId);
                break;
        }
        
        // ดึงข้อมูลแผนก
        $db = Database::getInstance();
        $departments = $db->fetchAll("SELECT id, name FROM departments WHERE status = 1 ORDER BY name");
        $data['departments'] = $departments;
        
        $this->view('attendance/report', $data);
    }
    
    /**
     * ส่งออกรายงาน
     */
    public function export() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_SUPERVISOR)) {
            $this->forbidden();
            return;
        }
        
        $type = $_GET['type'] ?? 'daily';
        $format = $_GET['format'] ?? 'excel';
        
        switch ($type) {
            case 'daily':
                $this->exportDailyReport();
                break;
            case 'monthly':
                $this->exportMonthlyReport();
                break;
        }
    }
    
    /**
     * ฟอร์มบันทึกการเข้างานด้วยตนเอง
     */
    public function manualForm() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_SUPERVISOR)) {
            $this->forbidden();
            return;
        }
        
        $employeeId = $_GET['employee_id'] ?? null;
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $db = Database::getInstance();
        
        // ดึงข้อมูลพนักงาน (เฉพาะที่สามารถดูได้)
        $employees = [];
        if (Auth::isAdmin() || Auth::isGM()) {
            $employees = $db->fetchAll(
                "SELECT id, user_id, first_name, last_name 
                 FROM employees WHERE status = 1 ORDER BY first_name"
            );
        } elseif (Auth::hasLevel(POSITION_MANAGER)) {
            $employees = $db->fetchAll(
                "SELECT id, user_id, first_name, last_name 
                 FROM employees 
                 WHERE department_id = ? AND status = 1 
                 ORDER BY first_name",
                [$_SESSION['department_id']]
            );
        } else {
            $subordinateIds = $this->getSubordinateIds();
            if (!empty($subordinateIds)) {
                $placeholders = implode(',', array_fill(0, count($subordinateIds), '?'));
                $employees = $db->fetchAll(
                    "SELECT id, user_id, first_name, last_name 
                     FROM employees 
                     WHERE id IN ($placeholders) AND status = 1 
                     ORDER BY first_name",
                    $subordinateIds
                );
            }
        }
        
        $shifts = Shift::getList();
        
        $data = [
            'employees' => $employees,
            'shifts' => $shifts,
            'selected_employee' => $employeeId,
            'selected_date' => $date
        ];
        
        $this->view('attendance/manual', $data);
    }
    
    /**
     * บันทึกการเข้างานด้วยตนเอง
     */
    public function manualSave() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_SUPERVISOR)) {
            $this->forbidden();
            return;
        }
        
        // ตรวจสอบ CSRF
        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Invalid request');
            redirect('/attendance/manual');
        }
        
        $data = [
            'employee_id' => $_POST['employee_id'] ?? null,
            'date' => $_POST['date'] ?? null,
            'shift_id' => $_POST['shift_id'] ?? null,
            'check_in_time' => $_POST['check_in_time'] ?? null,
            'check_out_time' => $_POST['check_out_time'] ?? null,
            'notes' => $_POST['notes'] ?? null
        ];
        
        try {
            $attendance = new Attendance();
            $id = $attendance->manualEntry($data);
            
            Session::flash('success', 'บันทึกข้อมูลการเข้างานเรียบร้อย');
            redirect('/attendance/view?id=' . $id);
            
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            redirect('/attendance/manual?' . http_build_query([
                'employee_id' => $data['employee_id'],
                'date' => $data['date']
            ]));
        }
    }
    
    /**
     * แก้ไขข้อมูลการเข้างาน
     */
    public function edit() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_SUPERVISOR)) {
            $this->forbidden();
            return;
        }
        
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            Session::flash('error', 'ไม่พบข้อมูล');
            redirect('/attendance');
        }
        
        $attendance = new Attendance($id);
        
        if (!$attendance->getId()) {
            Session::flash('error', 'ไม่พบข้อมูล');
            redirect('/attendance');
        }
        
        // ตรวจสอบสิทธิ์การแก้ไข
        if (!Auth::canViewEmployee($attendance->employee_id)) {
            $this->forbidden();
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // บันทึกการแก้ไข
            if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                Session::flash('error', 'Invalid request');
                redirect('/attendance/edit?id=' . $id);
            }
            
            $updateData = [
                'check_in_time' => $_POST['check_in_time'] ?? null,
                'check_out_time' => $_POST['check_out_time'] ?? null,
                'shift_id' => $_POST['shift_id'] ?? null,
                'notes' => $_POST['notes'] ?? null
            ];
            
            try {
                $attendance->updateRecord($updateData);
                Session::flash('success', 'อัปเดตข้อมูลเรียบร้อย');
                redirect('/attendance/view?id=' . $id);
            } catch (Exception $e) {
                Session::flash('error', $e->getMessage());
            }
        }
        
        // แสดงฟอร์มแก้ไข
        $shifts = Shift::getList();
        
        $data = [
            'attendance' => $attendance->toArray(),
            'shifts' => $shifts
        ];
        
        $this->view('attendance/edit', $data);
    }
    
    /**
     * ประมวลผลข้อมูลการเข้างาน
     */
    public function process() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_SUPERVISOR)) {
            $this->forbidden();
            return;
        }
        
        $date = $_POST['date'] ?? date('Y-m-d');
        
        try {
            Attendance::processDaily($date);
            Session::flash('success', 'ประมวลผลข้อมูลวันที่ ' . thaiDate($date, 'short') . ' เรียบร้อย');
        } catch (Exception $e) {
            Session::flash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
        
        redirect('/attendance');
    }
    
    /**
     * รายงานรายวัน
     */
    private function getDailyReport($date, $departmentId = null) {
        $db = Database::getInstance();
        
        $sql = "SELECT 
                d.name as department_name,
                COUNT(DISTINCT e.id) as total_employees,
                COUNT(DISTINCT CASE WHEN a.check_in_time IS NOT NULL THEN a.employee_id END) as present,
                COUNT(DISTINCT CASE WHEN a.check_in_status = 'late' THEN a.employee_id END) as late,
                COUNT(DISTINCT l.id) as on_leave,
                SUM(a.overtime_hours) as total_overtime
                FROM departments d
                LEFT JOIN employees e ON d.id = e.department_id AND e.status = 1
                LEFT JOIN attendance_records a ON e.id = a.employee_id AND a.date = ?
                LEFT JOIN leave_requests l ON e.id = l.employee_id 
                    AND ? BETWEEN l.start_date AND l.end_date 
                    AND l.status = 'approved'
                WHERE d.status = 1";
        
        $params = [$date, $date];
        
        if ($departmentId) {
            $sql .= " AND d.id = ?";
            $params[] = $departmentId;
        }
        
        $sql .= " GROUP BY d.id, d.name ORDER BY d.name";
        
        $data = $db->fetchAll($sql, $params);
        
        foreach ($data as &$row) {
            $row['absent'] = $row['total_employees'] - $row['present'] - $row['on_leave'];
            $row['attendance_rate'] = $row['total_employees'] > 0 
                ? round(($row['present'] / $row['total_employees']) * 100, 1)
                : 0;
        }
        
        return $data;
    }
    
    /**
     * รายงานรายเดือน
     */
    private function getMonthlyReport($month, $departmentId = null) {
        $db = Database::getInstance();
        
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $sql = "SELECT 
                e.user_id,
                e.first_name,
                e.last_name,
                d.name as department_name,
                COUNT(a.id) as working_days,
                SUM(CASE WHEN a.check_in_status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN a.check_in_time IS NULL THEN 1 ELSE 0 END) as absent_days,
                SUM(a.overtime_hours) as total_overtime,
                AVG(a.work_hours) as avg_work_hours
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN attendance_records a ON e.id = a.employee_id 
                    AND a.date BETWEEN ? AND ?
                WHERE e.status = 1";
        
        $params = [$startDate, $endDate];
        
        if ($departmentId) {
            $sql .= " AND e.department_id = ?";
            $params[] = $departmentId;
        }
        
        $sql .= " GROUP BY e.id, e.user_id, e.first_name, e.last_name, d.name
                  ORDER BY d.name, e.first_name";
        
        return $db->fetchAll($sql, $params);
    }
    
    /**
     * รายงานสรุป
     */
    private function getSummaryReport($month, $departmentId = null) {
        $db = Database::getInstance();
        
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        return $db->fetchAll(
            "SELECT 
                date,
                COUNT(DISTINCT employee_id) as present_count,
                SUM(CASE WHEN check_in_status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(overtime_hours) as overtime_hours
             FROM attendance_records a
             JOIN employees e ON a.employee_id = e.id
             WHERE a.date BETWEEN ? AND ?
             " . ($departmentId ? "AND e.department_id = ?" : "") . "
             GROUP BY a.date
             ORDER BY a.date",
            $departmentId 
                ? [$startDate, $endDate, $departmentId]
                : [$startDate, $endDate]
        );
    }
    
    /**
     * ส่งออกรายงานรายวัน
     */
    private function exportDailyReport() {
        $date = $_GET['date'] ?? date('Y-m-d');
        $departmentId = $_GET['department_id'] ?? null;
        
        $data = $this->getDailyReport($date, $departmentId);
        
        // TODO: สร้างไฟล์ Excel
        // ใช้ PhpSpreadsheet
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="daily_report_' . $date . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, ['แผนก', 'พนักงานทั้งหมด', 'มาทำงาน', 'ขาด', 'ลา', 'สาย', 'อัตราการเข้า', 'ชั่วโมง OT']);
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, [
                $row['department_name'],
                $row['total_employees'],
                $row['present'],
                $row['absent'],
                $row['on_leave'],
                $row['late'],
                $row['attendance_rate'] . '%',
                $row['total_overtime']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * ส่งออกรายงานรายเดือน
     */
    private function exportMonthlyReport() {
        $month = $_GET['month'] ?? date('Y-m');
        $departmentId = $_GET['department_id'] ?? null;
        
        $data = $this->getMonthlyReport($month, $departmentId);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="monthly_report_' . $month . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, ['รหัสพนักงาน', 'ชื่อ-สกุล', 'แผนก', 'วันทำงาน', 'สาย', 'ขาด', 'OT (ชม.)', 'ชม.เฉลี่ย']);
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, [
                formatUserId($row['user_id']),
                $row['first_name'] . ' ' . $row['last_name'],
                $row['department_name'],
                $row['working_days'],
                $row['late_days'],
                $row['absent_days'],
                $row['total_overtime'],
                number_format($row['avg_work_hours'], 1)
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * ดึง ID ลูกน้องทั้งหมด
     */
    private function getSubordinateIds() {
        $db = Database::getInstance();
        $employeeId = $_SESSION['employee_id'];
        
        return $db->fetchAll(
            "WITH RECURSIVE emp_tree AS (
                SELECT id FROM employees WHERE reports_to = ?
                UNION ALL
                SELECT e.id FROM employees e
                INNER JOIN emp_tree et ON e.reports_to = et.id
             )
             SELECT id FROM emp_tree",
            [$employeeId]
        );
    }
    
    /**
     * แสดงหน้า 403
     */
    private function forbidden() {
        http_response_code(403);
        require VIEWS_PATH . 'auth/403.php';
        exit;
    }
    
    /**
     * แสดง view
     */
    private function view($view, $data = []) {
        extract($data);
        
        $content = VIEWS_PATH . $view . '.php';
        
        require VIEWS_PATH . 'layouts/header.php';
        require VIEWS_PATH . 'layouts/sidebar.php';
        
        if (file_exists($content)) {
            require $content;
        } else {
            echo "View not found: " . $view;
        }
        
        require VIEWS_PATH . 'layouts/footer.php';
    }
}

// จัดการ action
$action = $_GET['action'] ?? 'index';
$controller = new AttendanceController();

switch ($action) {
    case 'view':
        $controller->view();
        break;
    case 'my-attendance':
        $controller->myAttendance();
        break;
    case 'report':
        $controller->report();
        break;
    case 'export':
        $controller->export();
        break;
    case 'manual':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->manualSave();
        } else {
            $controller->manualForm();
        }
        break;
    case 'edit':
        $controller->edit();
        break;
    case 'process':
        $controller->process();
        break;
    default:
        $controller->index();
}