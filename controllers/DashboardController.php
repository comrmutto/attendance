<?php
/**
 * DashboardController.php - Controller สำหรับแสดงหน้า Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Attendance.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Department.php';

class DashboardController {
    
    /**
     * Constructor - ตรวจสอบการ login
     */
    public function __construct() {
        requireLogin();
    }
    
    /**
     * แสดงหน้า Dashboard หลัก
     */
    public function index() {
        $db = Database::getInstance();
        $user = Auth::user();
        
        // ดึงข้อมูลสำหรับ dashboard
        $data = [
            'user' => $user,
            'summary' => $this->getSummaryData(),
            'attendance_today' => $this->getTodayAttendance(),
            'department_stats' => $this->getDepartmentStats(),
            'recent_activity' => $this->getRecentActivity(),
            'notifications' => $this->getNotifications(),
            'charts' => $this->getChartData()
        ];
        
        // ถ้าเป็นหัวหน้า/ผู้จัดการ ให้ดึงข้อมูลเพิ่ม
        if (Auth::hasLevel(POSITION_SUPERVISOR)) {
            $data['team_attendance'] = $this->getTeamAttendance();
            $data['pending_requests'] = $this->getPendingRequests();
        }
        
        // ถ้าเป็น GM/Admin ให้ดึงข้อมูลภาพรวมทั้งองค์กร
        if (Auth::isAdmin()) {
            $data['company_stats'] = $this->getCompanyStats();
            $data['department_performance'] = $this->getDepartmentPerformance();
        }
        
        $this->view('dashboard/index', $data);
    }
    
    /**
     * ดึงข้อมูลสรุป
     */
    private function getSummaryData() {
        $date = date('Y-m-d');
        $departmentId = null;
        
        // ถ้าไม่ใช่ admin ให้ดูเฉพาะแผนกตัวเอง
        if (!Auth::isAdmin() && !Auth::isGM()) {
            $departmentId = $_SESSION['department_id'] ?? null;
        }
        
        return Attendance::getSummary($date, $departmentId);
    }
    
    /**
     * ดึงข้อมูลการเข้าวันนี้
     */
    private function getTodayAttendance() {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        
        $sql = "SELECT 
                a.*,
                e.user_id,
                e.first_name,
                e.last_name,
                d.name as department_name,
                s.name as shift_name
                FROM attendance_records a
                JOIN employees e ON a.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN shifts s ON a.shift_id = s.id
                WHERE a.date = ?
                ORDER BY a.check_in_time DESC
                LIMIT 10";
        
        $records = $db->fetchAll($sql, [$date]);
        
        foreach ($records as &$record) {
            $record['user_id_formatted'] = formatUserId($record['user_id']);
            $record['employee_name'] = $record['first_name'] . ' ' . $record['last_name'];
            $record['check_in_formatted'] = $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-';
            $record['status_badge'] = $this->getStatusBadge($record);
        }
        
        return $records;
    }
    
    /**
     * ดึงสถิติแยกแผนก
     */
    private function getDepartmentStats() {
        return Attendance::getByDepartment();
    }
    
    /**
     * ดึงกิจกรรมล่าสุด
     */
    private function getRecentActivity($limit = 10) {
        $db = Database::getInstance();
        
        $sql = "SELECT 
                l.scan_time,
                l.inout_status,
                e.user_id,
                e.first_name,
                e.last_name,
                d.name as department_name
                FROM attendance_logs l
                JOIN employees e ON l.user_id = e.user_id
                LEFT JOIN departments d ON e.department_id = d.id
                ORDER BY l.scan_time DESC
                LIMIT ?";
        
        $activities = $db->fetchAll($sql, [$limit]);
        
        foreach ($activities as &$activity) {
            $activity['time_formatted'] = thaiDate($activity['scan_time'], 'time');
            $activity['date_formatted'] = thaiDate($activity['scan_time'], 'short');
            $activity['user_id_formatted'] = formatUserId($activity['user_id']);
            $activity['employee_name'] = $activity['first_name'] . ' ' . $activity['last_name'];
            $activity['type'] = $activity['inout_status'] == 1 ? 'check_in' : 'check_out';
            $activity['icon'] = $activity['inout_status'] == 1 ? 'fa-sign-in-alt' : 'fa-sign-out-alt';
            $activity['color'] = $activity['inout_status'] == 1 ? 'success' : 'info';
        }
        
        return $activities;
    }
    
    /**
     * ดึงการแจ้งเตือน
     */
    private function getNotifications() {
        $db = Database::getInstance();
        $notifications = [];
        
        // ตรวจสอบพนักงานที่ขาดงาน
        $absentees = $db->fetchAll(
            "SELECT e.user_id, e.first_name, e.last_name, 
                    COUNT(*) as absent_days
             FROM employees e
             JOIN attendance_records a ON e.id = a.employee_id
             WHERE a.date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
             AND a.check_in_time IS NULL
             AND e.status = 1
             GROUP BY e.id
             HAVING absent_days >= 3
             LIMIT 5"
        );
        
        foreach ($absentees as $absent) {
            $notifications[] = [
                'type' => 'warning',
                'title' => 'ขาดงานติดต่อกัน',
                'message' => formatUserId($absent['user_id']) . ' ' . 
                            $absent['first_name'] . ' ' . $absent['last_name'] . 
                            ' ขาดงาน ' . $absent['absent_days'] . ' วันติดต่อกัน',
                'icon' => 'fa-exclamation-triangle',
                'time' => 'ตอนนี้'
            ];
        }
        
        // ตรวจสอบวันเกิด
        $birthdays = $db->fetchAll(
            "SELECT user_id, first_name, last_name, birth_date
             FROM employees
             WHERE MONTH(birth_date) = MONTH(CURDATE())
             AND DAY(birth_date) = DAY(CURDATE())
             AND status = 1
             LIMIT 5"
        );
        
        foreach ($birthdays as $birthday) {
            $notifications[] = [
                'type' => 'info',
                'title' => 'วันเกิด',
                'message' => formatUserId($birthday['user_id']) . ' ' . 
                            $birthday['first_name'] . ' ' . $birthday['last_name'] . 
                            ' วันเกิดวันนี้',
                'icon' => 'fa-birthday-cake',
                'time' => 'ตอนนี้'
            ];
        }
        
        // ตรวจสอบครบรอบการทำงาน
        $anniversaries = $db->fetchAll(
            "SELECT user_id, first_name, last_name, hire_date,
                    TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) as years
             FROM employees
             WHERE MONTH(hire_date) = MONTH(CURDATE())
             AND DAY(hire_date) = DAY(CURDATE())
             AND status = 1
             AND hire_date <= CURDATE()
             LIMIT 5"
        );
        
        foreach ($anniversaries as $ann) {
            $notifications[] = [
                'type' => 'success',
                'title' => 'ครบรอบการทำงาน',
                'message' => formatUserId($ann['user_id']) . ' ' . 
                            $ann['first_name'] . ' ' . $ann['last_name'] . 
                            ' ครบรอบ ' . $ann['years'] . ' ปี',
                'icon' => 'fa-award',
                'time' => 'ตอนนี้'
            ];
        }
        
        return $notifications;
    }
    
    /**
     * ดึงข้อมูลสำหรับ charts
     */
    private function getChartData() {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        
        // ข้อมูลรายชั่วโมง
        $hourly = Attendance::getHourlyStats($date);
        
        // ข้อมูลสถานะการเข้างาน
        $statusData = $db->fetchAll(
            "SELECT 
                check_in_status,
                COUNT(*) as count
             FROM attendance_records
             WHERE date = ? AND check_in_time IS NOT NULL
             GROUP BY check_in_status",
            [$date]
        );
        
        $statusLabels = [];
        $statusValues = [];
        $statusColors = [
            'on_time' => '#198754',
            'late' => '#ffc107',
            'early' => '#0dcaf0',
            'manual' => '#6c757d'
        ];
        
        foreach ($statusData as $row) {
            $statusLabels[] = $this->getStatusLabel($row['check_in_status']);
            $statusValues[] = (int)$row['count'];
        }
        
        // ข้อมูลแนวโน้ม 7 วัน
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $trend[$d] = [
                'date' => $d,
                'date_formatted' => thaiDate($d, 'short'),
                'present' => 0,
                'late' => 0
            ];
        }
        
        $trendData = $db->fetchAll(
            "SELECT 
                date,
                COUNT(DISTINCT employee_id) as present,
                SUM(CASE WHEN check_in_status = 'late' THEN 1 ELSE 0 END) as late
             FROM attendance_records
             WHERE date BETWEEN DATE_SUB(?, INTERVAL 6 DAY) AND ?
             GROUP BY date",
            [$date, $date]
        );
        
        foreach ($trendData as $row) {
            if (isset($trend[$row['date']])) {
                $trend[$row['date']]['present'] = (int)$row['present'];
                $trend[$row['date']]['late'] = (int)$row['late'];
            }
        }
        
        return [
            'hourly' => [
                'labels' => array_column($hourly, 'hour'),
                'check_in' => array_column($hourly, 'check_in'),
                'check_out' => array_column($hourly, 'check_out')
            ],
            'status' => [
                'labels' => $statusLabels,
                'values' => $statusValues,
                'colors' => array_values($statusColors)
            ],
            'trend' => [
                'labels' => array_column($trend, 'date_formatted'),
                'present' => array_column($trend, 'present'),
                'late' => array_column($trend, 'late')
            ]
        ];
    }
    
    /**
     * ดึงข้อมูลการเข้างานของทีม (สำหรับหัวหน้า)
     */
    private function getTeamAttendance() {
        $db = Database::getInstance();
        $employeeId = $_SESSION['employee_id'];
        
        // ดึงลูกน้องทั้งหมด
        $subordinates = $db->fetchAll(
            "WITH RECURSIVE emp_tree AS (
                SELECT id FROM employees WHERE reports_to = ?
                UNION ALL
                SELECT e.id FROM employees e
                INNER JOIN emp_tree et ON e.reports_to = et.id
             )
             SELECT e.*, d.name as department_name
             FROM emp_tree et
             JOIN employees e ON et.id = e.id
             LEFT JOIN departments d ON e.department_id = d.id
             ORDER BY e.first_name",
            [$employeeId]
        );
        
        $date = date('Y-m-d');
        $result = [];
        
        foreach ($subordinates as $sub) {
            $attendance = $db->fetchOne(
                "SELECT * FROM attendance_records 
                 WHERE employee_id = ? AND date = ?",
                [$sub['id'], $date]
            );
            
            $result[] = [
                'employee' => $sub,
                'user_id_formatted' => formatUserId($sub['user_id']),
                'name' => $sub['first_name'] . ' ' . $sub['last_name'],
                'attendance' => $attendance,
                'status' => $attendance ? $attendance['check_in_status'] : 'absent',
                'check_in' => $attendance ? date('H:i', strtotime($attendance['check_in_time'])) : '-',
                'status_badge' => $this->getStatusBadge($attendance)
            ];
        }
        
        return $result;
    }
    
    /**
     * ดึงคำขอที่รอการอนุมัติ
     */
    private function getPendingRequests() {
        $db = Database::getInstance();
        $employeeId = $_SESSION['employee_id'];
        
        // คำขอลาที่รออนุมัติ
        $leaveRequests = $db->fetchAll(
            "SELECT l.*, e.user_id, e.first_name, e.last_name,
                    lt.name as leave_type
             FROM leave_requests l
             JOIN employees e ON l.employee_id = e.id
             JOIN leave_types lt ON l.leave_type_id = lt.id
             WHERE l.status = 'pending' 
             AND e.reports_to = ?
             ORDER BY l.created_at DESC
             LIMIT 10",
            [$employeeId]
        );
        
        foreach ($leaveRequests as &$req) {
            $req['user_id_formatted'] = formatUserId($req['user_id']);
            $req['employee_name'] = $req['first_name'] . ' ' . $req['last_name'];
            $req['date_range'] = thaiDate($req['start_date'], 'short') . ' - ' . thaiDate($req['end_date'], 'short');
        }
        
        return $leaveRequests;
    }
    
    /**
     * ดึงสถิติภาพรวมบริษัท
     */
    private function getCompanyStats() {
        $db = Database::getInstance();
        
        return $db->fetchOne(
            "SELECT 
                (SELECT COUNT(*) FROM employees WHERE status = 1) as total_employees,
                (SELECT COUNT(*) FROM departments WHERE status = 1) as total_departments,
                (SELECT COUNT(*) FROM users WHERE status = 1) as total_users,
                (SELECT COUNT(*) FROM attendance_records WHERE date = CURDATE()) as today_attendance
             FROM DUAL"
        );
    }
    
    /**
     * ดึงประสิทธิภาพของแต่ละแผนก
     */
    private function getDepartmentPerformance() {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        
        return $db->fetchAll(
            "SELECT 
                d.id,
                d.name,
                COUNT(DISTINCT e.id) as total_employees,
                COUNT(DISTINCT CASE WHEN a.check_in_time IS NOT NULL THEN a.employee_id END) as present,
                ROUND(COUNT(DISTINCT CASE WHEN a.check_in_time IS NOT NULL THEN a.employee_id END) / 
                      NULLIF(COUNT(DISTINCT e.id), 0) * 100, 1) as attendance_rate
             FROM departments d
             LEFT JOIN employees e ON d.id = e.department_id AND e.status = 1
             LEFT JOIN attendance_records a ON e.id = a.employee_id AND a.date = ?
             WHERE d.status = 1
             GROUP BY d.id, d.name
             ORDER BY attendance_rate DESC",
            [$date]
        );
    }
    
    /**
     * รูปแบบ badge ตามสถานะ
     */
    private function getStatusBadge($record) {
        if (!$record || empty($record['check_in_time'])) {
            return '<span class="badge bg-danger">ขาด</span>';
        }
        
        if ($record['check_in_status'] == 'late') {
            return '<span class="badge bg-warning">สาย</span>';
        }
        
        if ($record['overtime_hours'] > 0) {
            return '<span class="badge bg-info">OT</span>';
        }
        
        return '<span class="badge bg-success">มา</span>';
    }
    
    /**
     * แปลงสถานะเป็นภาษาไทย
     */
    private function getStatusLabel($status) {
        $labels = [
            'on_time' => 'ตรงเวลา',
            'late' => 'สาย',
            'early' => 'มาก่อนเวลา',
            'manual' => 'บันทึกด้วยตนเอง',
            'absent' => 'ขาด'
        ];
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * แสดง view
     */
    private function view($view, $data = []) {
        extract($data);
        
        // กำหนด layout
        $content = VIEWS_PATH . $view . '.php';
        
        // โหลด header
        require VIEWS_PATH . 'layouts/header.php';
        
        // โหลด sidebar
        require VIEWS_PATH . 'layouts/sidebar.php';
        
        // โหลด content
        if (file_exists($content)) {
            require $content;
        } else {
            echo "View not found: " . $view;
        }
        
        // โหลด footer
        require VIEWS_PATH . 'layouts/footer.php';
    }
}