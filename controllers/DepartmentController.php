<?php
/**
 * DepartmentController.php - Controller สำหรับจัดการข้อมูลแผนก
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Department.php';
require_once __DIR__ . '/../classes/Attendance.php';

class DepartmentController {
    
    /**
     * Constructor - ตรวจสอบการ login
     */
    public function __construct() {
        requireLogin();
    }
    
    /**
     * แสดงรายการแผนก
     */
    public function index() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_MANAGER) && !Auth::isAdmin()) {
            $this->forbidden();
            return;
        }
        
        $page = $_GET['page'] ?? 1;
        $filters = [
            'status' => $_GET['status'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        
        $departments = Department::all($filters, $page);
        
        $data = [
            'departments' => $departments['data'],
            'pagination' => [
                'current_page' => $departments['page'],
                'total_pages' => $departments['pages'],
                'total' => $departments['total']
            ],
            'filters' => $filters
        ];
        
        $this->view('department/index', $data);
    }
    
    /**
     * แสดงรายละเอียดแผนก
     */
    public function view() {
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            Session::flash('error', 'ไม่พบข้อมูลแผนก');
            redirect('/department');
        }
        
        // ตรวจสอบสิทธิ์
        if (!Auth::canViewDepartment($id)) {
            $this->forbidden();
            return;
        }
        
        $department = new Department($id);
        
        if (!$department->getId()) {
            Session::flash('error', 'ไม่พบข้อมูลแผนก');
            redirect('/department');
        }
        
        // ดึงข้อมูลพนักงานในแผนก
        $employees = $department->getEmployees();
        
        // ดึงสถิติการเข้างานวันนี้
        $todayStats = Attendance::getSummary(date('Y-m-d'), $id);
        
        // ดึงสถิติรายเดือน
        $monthlyStats = $department->getAttendanceSummary(
            date('Y-m-01'),
            date('Y-m-t')
        );
        
        $data = [
            'department' => $department->toArray(),
            'employees' => $employees,
            'today_stats' => $todayStats,
            'monthly_stats' => $monthlyStats,
            'can_edit' => Auth::hasLevel(POSITION_MANAGER),
            'can_delete' => Auth::hasLevel(POSITION_GM)
        ];
        
        $this->view('department/view', $data);
    }
    
    /**
     * ฟอร์มเพิ่มแผนก
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
        
        // ดึงรายชื่อผู้จัดการที่สามารถเลือกได้
        $db = Database::getInstance();
        $managers = $db->fetchAll(
            "SELECT e.id, e.user_id, e.first_name, e.last_name, p.name as position_name
             FROM employees e
             JOIN positions p ON e.position_id = p.id
             WHERE e.status = 1 AND p.level >= ?
             ORDER BY p.level DESC, e.first_name",
            [POSITION_MANAGER]
        );
        
        $data = ['managers' => $managers];
        
        $this->view('department/create', $data);
    }
    
    /**
     * บันทึกแผนกใหม่
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
            redirect('/department/create');
        }
        
        $data = [
            'name' => $_POST['name'] ?? null,
            'code' => $_POST['code'] ?? null,
            'description' => $_POST['description'] ?? null,
            'managers' => $_POST['managers'] ?? []
        ];
        
        try {
            $department = new Department();
            $id = $department->create($data);
            
            Session::flash('success', 'เพิ่มแผนกเรียบร้อย');
            redirect('/department/view?id=' . $id);
            
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            redirect('/department/create');
        }
    }
    
    /**
     * ฟอร์มแก้ไขแผนก
     */
    public function edit() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_MANAGER)) {
            $this->forbidden();
            return;
        }
        
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            Session::flash('error', 'ไม่พบข้อมูลแผนก');
            redirect('/department');
        }
        
        $department = new Department($id);
        
        if (!$department->getId()) {
            Session::flash('error', 'ไม่พบข้อมูลแผนก');
            redirect('/department');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->update($id);
            return;
        }
        
        // ดึงรายชื่อผู้จัดการที่สามารถเลือกได้
        $db = Database::getInstance();
        $managers = $db->fetchAll(
            "SELECT e.id, e.user_id, e.first_name, e.last_name, p.name as position_name
             FROM employees e
             JOIN positions p ON e.position_id = p.id
             WHERE e.status = 1 AND p.level >= ?
             ORDER BY p.level DESC, e.first_name",
            [POSITION_MANAGER]
        );
        
        $data = [
            'department' => $department->toArray(),
            'managers' => $managers,
            'current_managers' => array_column($department->getManagers(), 'id')
        ];
        
        $this->view('department/edit', $data);
    }
    
    /**
     * อัปเดตข้อมูลแผนก
     */
    public function update($id) {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_MANAGER)) {
            $this->forbidden();
            return;
        }
        
        // ตรวจสอบ CSRF
        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Invalid request');
            redirect('/department/edit?id=' . $id);
        }
        
        $data = [
            'name' => $_POST['name'] ?? null,
            'code' => $_POST['code'] ?? null,
            'description' => $_POST['description'] ?? null,
            'status' => $_POST['status'] ?? 1,
            'managers' => $_POST['managers'] ?? []
        ];
        
        try {
            $department = new Department($id);
            $department->update($data);
            
            // อัปเดตผู้จัดการ
            $currentManagers = array_column($department->getManagers(), 'id');
            $newManagers = $data['managers'];
            
            // เพิ่มผู้จัดการใหม่
            foreach (array_diff($newManagers, $currentManagers) as $managerId) {
                $department->assignManager($managerId);
            }
            
            // ลบผู้จัดการที่ถูกเอาออก
            foreach (array_diff($currentManagers, $newManagers) as $managerId) {
                $department->removeManager($managerId);
            }
            
            Session::flash('success', 'อัปเดตข้อมูลแผนกเรียบร้อย');
            redirect('/department/view?id=' . $id);
            
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            redirect('/department/edit?id=' . $id);
        }
    }
    
    /**
     * ลบแผนก
     */
    public function delete() {
        // ตรวจสอบสิทธิ์
        if (!Auth::hasLevel(POSITION_GM)) {
            $this->forbidden();
            return;
        }
        
        $id = $_POST['id'] ?? 0;
        
        if (!$id) {
            Session::flash('error', 'ไม่พบข้อมูลแผนก');
            redirect('/department');
        }
        
        // ตรวจสอบ CSRF
        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Invalid request');
            redirect('/department');
        }
        
        try {
            $department = new Department($id);
            $department->delete();
            
            Session::flash('success', 'ลบแผนกเรียบร้อย');
            
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        
        redirect('/department');
    }
    
    /**
     * แสดงสถิติของแผนก
     */
    public function stats() {
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            Session::flash('error', 'ไม่พบข้อมูลแผนก');
            redirect('/department');
        }
        
        // ตรวจสอบสิทธิ์
        if (!Auth::canViewDepartment($id)) {
            $this->forbidden();
            return;
        }
        
        $department = new Department($id);
        
        if (!$department->getId()) {
            Session::flash('error', 'ไม่พบข้อมูลแผนก');
            redirect('/department');
        }
        
        $stats = $department->getStats();
        
        $data = [
            'department' => $department->toArray(),
            'stats' => $stats
        ];
        
        $this->view('department/stats', $data);
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
$controller = new DepartmentController();

switch ($action) {
    case 'view':
        $controller->view();
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
    case 'stats':
        $controller->stats();
        break;
    default:
        $controller->index();
}