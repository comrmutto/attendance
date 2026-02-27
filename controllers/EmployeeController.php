<?php
/**
 * EmployeeController.php - Controller สำหรับจัดการข้อมูลพนักงาน
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Department.php';

class EmployeeController {
    
    /**
     * Constructor - ตรวจสอบการ login
     */
    public function __construct() {
        requireLogin();
    }
    
    /**
     * แสดงรายการพนักงาน
     */
    public function index() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_SUPERVISOR) && !Auth::isAdmin()) {
            // พนักงานทั่วไปให้ไปที่โปรไฟล์ตัวเอง
            redirect('/employee/profile');
        }
        
        $page = $_GET['page'] ?? 1;
        $filters = [
            'department_id' => $_GET['department_id'] ?? null,
            'position_id' => $_GET['position_id'] ?? null,
            'status' => $_GET['status'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        
        // จำกัดสิทธิ์การดู
        if (!Auth::isAdmin() && !Auth::isGM()) {
            if (Auth::hasLevel(POSITION_MANAGER)) {
                // ผู้จัดการดูได้เฉพาะพนักงานในแผนก
                $filters['department_id'] = $_SESSION['department_id'];
            } else {
                // หัวหน้าดูลูกน้อง
                $filters['employee_ids'] = $this->getSubordinateIds();
            }
        }
        
        $employees = Employee::all($filters, $page);
        
        // ดึงข้อมูลสำหรับ filter
        $db = Database::getInstance();
        $departments = $db->fetchAll("SELECT id, name FROM departments WHERE status = 1 ORDER BY name");
        $positions = $db->fetchAll("SELECT id, name FROM positions ORDER BY level");
        
        $data = [
            'employees' => $employees['data'],
            'pagination' => [
                'current_page' => $employees['page'],
                'total_pages' => $employees['pages'],
                'total' => $employees['total']
            ],
            'filters' => $filters,
            'departments' => $departments,
            'positions' => $positions
        ];
        
        $this->view('employee/index', $data);
    }
    
    /**
     * แสดงโปรไฟล์พนักงาน
     */
    public function profile() {
        $id = $_GET['id'] ?? $_SESSION['employee_id'] ?? 0;
        
        if (!$id) {
            Session::flash('error', 'ไม่พบข้อมูลพนักงาน');
            redirect('/dashboard');
        }
        
        // ตรวจสอบสิทธิ์
        if (!Auth::canViewEmployee($id)) {
            $this->forbidden();
            return;
        }
        
        $employee = new Employee($id);
        
        if (!$employee->getId()) {
            Session::flash('error', 'ไม่พบข้อมูลพนักงาน');
            redirect('/dashboard');
        }
        
        // ดึงข้อมูลเพิ่มเติม
        $db = Database::getInstance();
        
        // ประวัติการเข้างานล่าสุด
        $recentAttendance = $db->fetchAll(
            "SELECT * FROM attendance_records 
             WHERE employee_id = ? 
             ORDER BY date DESC 
             LIMIT 30",
            [$id]
        );
        
        // สถิติการเข้างาน
        $stats = $employee->getAttendanceStats();
        
        // วันลาคงเหลือ
        $leaveRemaining = $employee->getRemainingLeaveDays();
        
        // ลูกน้อง (ถ้ามี)
        $subordinates = [];
        if ($employee->position_level >= POSITION_SUPERVISOR) {
            $subordinates = $employee->getSubordinates();
        }
        
        $data = [
            'employee' => $employee->toArray(),
            'recent_attendance' => $recentAttendance,
            'stats' => $stats,
            'leave_remaining' => $leaveRemaining,
            'subordinates' => $subordinates,
            'can_edit' => Auth::hasLevel(POSITION_SUPERVISOR) || $id == $_SESSION['employee_id']
        ];
        
        $this->view('employee/profile', $data);
    }
    
    /**
     * ฟอร์มเพิ่มพนักงาน
     */
    public function create() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_MANAGER)) {
            $this->forbidden();
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->store();
            return;
        }
        
        $db = Database::getInstance();
        
        $data = [
            'departments' => Department::getList(),
            'positions' => $db->fetchPairs("SELECT id, name FROM positions ORDER BY level"),
            'shifts' => $db->fetchPairs("SELECT id, name FROM shifts WHERE status = 1"),
            'supervisors' => $this->getSupervisorList()
        ];
        
        $this->view('employee/create', $data);
    }
    
    /**
     * บันทึกพนักงานใหม่
     */
    public function store() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_MANAGER)) {
            $this->forbidden();
            return;
        }
        
        // ตรวจสอบ CSRF
        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Invalid request');
            redirect('/employee/create');
        }
        
        $data = [
            'user_id' => $_POST['user_id'] ?? null,
            'first_name' => $_POST['first_name'] ?? null,
            'last_name' => $_POST['last_name'] ?? null,
            'nickname' => $_POST['nickname'] ?? null,
            'gender' => $_POST['gender'] ?? null,
            'birth_date' => $_POST['birth_date'] ?? null,
            'email' => $_POST['email'] ?? null,
            'phone' => $_POST['phone'] ?? null,
            'address' => $_POST['address'] ?? null,
            'department_id' => $_POST['department_id'] ?? null,
            'position_id' => $_POST['position_id'] ?? null,
            'shift_id' => $_POST['shift_id'] ?? 1,
            'reports_to' => $_POST['reports_to'] ?? null,
            'hire_date' => $_POST['hire_date'] ?? date('Y-m-d'),
            'username' => $_POST['username'] ?? null,
            'password' => $_POST['password'] ?? null
        ];
        
        try {
            $employee = new Employee();
            $id = $employee->create($data);
            
            Session::flash('success', 'เพิ่มพนักงานเรียบร้อย');
            redirect('/employee/profile?id=' . $id);
            
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            redirect('/employee/create');
        }
    }
    
    /**
     * ฟอร์มแก้ไขพนักงาน
     */
    public function edit() {
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            Session::flash('error', 'ไม่พบข้อมูลพนักงาน');
            redirect('/employee');
        }
        
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_SUPERVISOR) && $id != $_SESSION['employee_id']) {
            $this->forbidden();
            return;
        }
        
        $employee = new Employee($id);
        
        if (!$employee->getId()) {
            Session::flash('error', 'ไม่พบข้อมูลพนักงาน');
            redirect('/employee');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->update($id);
            return;
        }
        
        $db = Database::getInstance();
        
        $data = [
            'employee' => $employee->toArray(),
            'departments' => Department::getList(),
            'positions' => $db->fetchPairs("SELECT id, name FROM positions ORDER BY level"),
            'shifts' => $db->fetchPairs("SELECT id, name FROM shifts WHERE status = 1"),
            'supervisors' => $this->getSupervisorList($id)
        ];
        
        $this->view('employee/edit', $data);
    }
    
    /**
     * อัปเดตข้อมูลพนักงาน
     */
    public function update($id) {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_SUPERVISOR) && $id != $_SESSION['employee_id']) {
            $this->forbidden();
            return;
        }
        
        // ตรวจสอบ CSRF
        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Invalid request');
            redirect('/employee/edit?id=' . $id);
        }
        
        $data = [
            'first_name' => $_POST['first_name'] ?? null,
            'last_name' => $_POST['last_name'] ?? null,
            'nickname' => $_POST['nickname'] ?? null,
            'gender' => $_POST['gender'] ?? null,
            'birth_date' => $_POST['birth_date'] ?? null,
            'email' => $_POST['email'] ?? null,
            'phone' => $_POST['phone'] ?? null,
            'address' => $_POST['address'] ?? null,
            'department_id' => $_POST['department_id'] ?? null,
            'position_id' => $_POST['position_id'] ?? null,
            'shift_id' => $_POST['shift_id'] ?? null,
            'reports_to' => $_POST['reports_to'] ?? null,
            'status' => $_POST['status'] ?? 1
        ];
        
        try {
            $employee = new Employee($id);
            $employee->update($data);
            
            Session::flash('success', 'อัปเดตข้อมูลเรียบร้อย');
            redirect('/employee/profile?id=' . $id);
            
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            redirect('/employee/edit?id=' . $id);
        }
    }
    
    /**
     * ลบพนักงาน
     */
    public function delete() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_MANAGER)) {
            $this->forbidden();
            return;
        }
        
        $id = $_POST['id'] ?? 0;
        
        if (!$id) {
            Session::flash('error', 'ไม่พบข้อมูลพนักงาน');
            redirect('/employee');
        }
        
        // ตรวจสอบ CSRF
        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Invalid request');
            redirect('/employee');
        }
        
        try {
            $employee = new Employee($id);
            $employee->delete();
            
            Session::flash('success', 'ลบพนักงานเรียบร้อย');
            
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        
        redirect('/employee');
    }
    
    /**
     * แสดงรายชื่อลูกน้อง
     */
    public function subordinates() {
        $id = $_GET['id'] ?? $_SESSION['employee_id'] ?? 0;
        
        if (!$id) {
            Session::flash('error', 'ไม่พบข้อมูลพนักงาน');
            redirect('/dashboard');
        }
        
        // ตรวจสอบสิทธิ์
        if (!Auth::canViewEmployee($id)) {
            $this->forbidden();
            return;
        }
        
        $employee = new Employee($id);
        
        if (!$employee->getId()) {
            Session::flash('error', 'ไม่พบข้อมูลพนักงาน');
            redirect('/dashboard');
        }
        
        $subordinates = $employee->getSubordinates(true);
        
        $data = [
            'employee' => $employee->toArray(),
            'subordinates' => $subordinates
        ];
        
        $this->view('employee/subordinates', $data);
    }
    
    /**
     * ดึงรายชื่อผู้บังคับบัญชาที่สามารถเลือกได้
     */
    private function getSupervisorList($excludeId = null) {
        $db = Database::getInstance();
        
        $sql = "SELECT e.id, e.user_id, e.first_name, e.last_name, p.name as position_name
                FROM employees e
                JOIN positions p ON e.position_id = p.id
                WHERE e.status = 1 AND p.level >= ?";
        
        $params = [POSITION_SUPERVISOR];
        
        if ($excludeId) {
            $sql .= " AND e.id != ?";
            $params[] = $excludeId;
        }
        
        $sql .= " ORDER BY p.level DESC, e.first_name";
        
        $supervisors = $db->fetchAll($sql, $params);
        
        $result = [];
        foreach ($supervisors as $sup) {
            $result[$sup['id']] = formatUserId($sup['user_id']) . ' ' . 
                                  $sup['first_name'] . ' ' . $sup['last_name'] . 
                                  ' (' . $sup['position_name'] . ')';
        }
        
        return $result;
    }
    
    /**
     * ดึง ID ลูกน้องทั้งหมด
     */
    private function getSubordinateIds() {
        $db = Database::getInstance();
        $employeeId = $_SESSION['employee_id'];
        
        $ids = $db->fetchAll(
            "WITH RECURSIVE emp_tree AS (
                SELECT id FROM employees WHERE reports_to = ?
                UNION ALL
                SELECT e.id FROM employees e
                INNER JOIN emp_tree et ON e.reports_to = et.id
             )
             SELECT id FROM emp_tree",
            [$employeeId]
        );
        
        return array_column($ids, 'id');
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
$controller = new EmployeeController();

switch ($action) {
    case 'profile':
        $controller->profile();
        break;
    case 'create':
        $controller->create();
        break;
    case 'edit':
        $controller->edit();
        break;
    case 'delete':
        $controller->delete();
        break;
    case 'subordinates':
        $controller->subordinates();
        break;
    default:
        $controller->index();
}