<?php
/**
 * get_employees.php - API สำหรับดึงข้อมูลพนักงาน
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

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
            
        case 'PUT':
            handlePutRequest();
            break;
            
        case 'DELETE':
            handleDeleteRequest();
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
            getEmployeeList();
            break;
            
        case 'detail':
            getEmployeeDetail();
            break;
            
        case 'hierarchy':
            getEmployeeHierarchy();
            break;
            
        case 'subordinates':
            getSubordinates();
            break;
            
        case 'supervisors':
            getSupervisors();
            break;
            
        case 'by-department':
            getEmployeesByDepartment();
            break;
            
        case 'by-position':
            getEmployeesByPosition();
            break;
            
        case 'search':
            searchEmployees();
            break;
            
        case 'birthdays':
            getBirthdaysThisMonth();
            break;
            
        case 'new-hires':
            getNewHires();
            break;
            
        case 'stats':
            getEmployeeStats();
            break;
            
        case 'tree':
            getOrganizationTree();
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
        case 'create':
            createEmployee();
            break;
            
        case 'update':
            updateEmployee();
            break;
            
        case 'delete':
            deleteEmployee();
            break;
            
        case 'bulk-import':
            bulkImportEmployees();
            break;
            
        case 'assign-supervisor':
            assignSupervisor();
            break;
            
        case 'change-status':
            changeEmployeeStatus();
            break;
            
        case 'assign-shift':
            assignShift();
            break;
            
        case 'assign-department':
            assignDepartment();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

/**
 * จัดการ PUT request (แก้ไขข้อมูล)
 */
function handlePutRequest() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Employee ID required']);
        return;
    }
    
    updateEmployeeData($id, $data);
}

/**
 * จัดการ DELETE request
 */
function handleDeleteRequest() {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Employee ID required']);
        return;
    }
    
    deleteEmployeeData($id);
}

/**
 * ดึงรายการพนักงาน
 */
function getEmployeeList() {
    $db = Database::getInstance();
    
    // รับพารามิเตอร์
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : ITEMS_PER_PAGE;
    $offset = ($page - 1) * $limit;
    
    $filters = [
        'department_id' => $_GET['department_id'] ?? null,
        'position_id' => $_GET['position_id'] ?? null,
        'shift_id' => $_GET['shift_id'] ?? null,
        'status' => $_GET['status'] ?? null,
        'search' => $_GET['search'] ?? null,
        'gender' => $_GET['gender'] ?? null
    ];
    
    // ตรวจสอบสิทธิ์การดูข้อมูล
    $currentEmpId = $_SESSION['employee_id'] ?? null;
    $positionLevel = $_SESSION['position_level'] ?? 0;
    
    // สร้าง SQL
    $sql = "SELECT SQL_CALC_FOUND_ROWS 
            e.*,
            d.name as department_name,
            d.code as department_code,
            p.name as position_name,
            p.level as position_level,
            s.name as shift_name,
            s.start_time,
            s.end_time,
            CONCAT(super.first_name, ' ', super.last_name) as supervisor_name,
            super.user_id as supervisor_user_id
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN shifts s ON e.shift_id = s.id
            LEFT JOIN employees super ON e.reports_to = super.id
            WHERE 1=1";
    
    $params = [];
    
    // กรองตามสิทธิ์การดู
    if ($positionLevel < POSITION_SUPERVISOR && !Auth::isAdmin()) {
        // พนักงานทั่วไปเห็นเฉพาะตัวเอง
        $sql .= " AND e.id = ?";
        $params[] = $currentEmpId;
    } elseif ($positionLevel >= POSITION_MANAGER && !Auth::isAdmin()) {
        // ผู้จัดการเห็นพนักงานในแผนกตัวเอง
        $sql .= " AND (e.department_id = (SELECT department_id FROM employees WHERE id = ?) 
                  OR e.reports_to = ? OR e.id = ?)";
        $params[] = $currentEmpId;
        $params[] = $currentEmpId;
        $params[] = $currentEmpId;
    }
    
    if ($filters['department_id']) {
        $sql .= " AND e.department_id = ?";
        $params[] = $filters['department_id'];
    }
    
    if ($filters['position_id']) {
        $sql .= " AND e.position_id = ?";
        $params[] = $filters['position_id'];
    }
    
    if ($filters['shift_id']) {
        $sql .= " AND e.shift_id = ?";
        $params[] = $filters['shift_id'];
    }
    
    if ($filters['status'] !== null) {
        $sql .= " AND e.status = ?";
        $params[] = $filters['status'];
    }
    
    if ($filters['gender']) {
        $sql .= " AND e.gender = ?";
        $params[] = $filters['gender'];
    }
    
    if ($filters['search']) {
        $sql .= " AND (e.user_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? 
                  OR e.email LIKE ? OR e.phone LIKE ?)";
        $searchTerm = "%{$filters['search']}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY e.department_id, e.first_name LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $employees = $db->fetchAll($sql, $params);
    $totalRecords = $db->fetchValue("SELECT FOUND_ROWS()");
    
    // จัดรูปแบบข้อมูล
    foreach ($employees as &$emp) {
        $emp['user_id_formatted'] = formatUserId($emp['user_id']);
        $emp['fullname'] = $emp['first_name'] . ' ' . $emp['last_name'];
        $emp['hire_date_formatted'] = $emp['hire_date'] ? thaiDate($emp['hire_date'], 'short') : '-';
        $emp['tenure'] = $emp['hire_date'] ? calculateTenure($emp['hire_date']) : '-';
        $emp['status_badge'] = getStatusBadgeEmployee($emp['status']);
        $emp['supervisor_name_formatted'] = $emp['supervisor_name'] ?? '-';
        $emp['supervisor_id_formatted'] = $emp['supervisor_user_id'] ? formatUserId($emp['supervisor_user_id']) : '-';
        
        // เพิ่มข้อมูลสถิติ
        $emp['attendance_stats'] = getEmployeeAttendanceStats($emp['id']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $employees,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => (int)$totalRecords,
            'total_pages' => ceil($totalRecords / $limit)
        ],
        'filters' => $filters,
        'can_create' => Auth::hasLevel(POSITION_MANAGER),
        'can_edit' => Auth::hasLevel(POSITION_SUPERVISOR),
        'can_delete' => Auth::hasLevel(POSITION_MANAGER)
    ]);
}

/**
 * ดึงรายละเอียดพนักงาน
 */
function getEmployeeDetail() {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Employee ID required']);
        return;
    }
    
    // ตรวจสอบสิทธิ์การดู
    if (!Auth::canViewEmployee($id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $db = Database::getInstance();
    
    $sql = "SELECT 
            e.*,
            d.name as department_name,
            d.code as department_code,
            p.name as position_name,
            p.level as position_level,
            s.name as shift_name,
            s.start_time,
            s.end_time,
            CONCAT(super.first_name, ' ', super.last_name) as supervisor_name,
            super.user_id as supervisor_user_id,
            u.username,
            u.role as user_role,
            u.last_login
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN shifts s ON e.shift_id = s.id
            LEFT JOIN employees super ON e.reports_to = super.id
            LEFT JOIN users u ON e.id = u.employee_id
            WHERE e.id = ?";
    
    $employee = $db->fetchOne($sql, [$id]);
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode(['error' => 'Employee not found']);
        return;
    }
    
    // จัดรูปแบบข้อมูล
    $employee['user_id_formatted'] = formatUserId($employee['user_id']);
    $employee['fullname'] = $employee['first_name'] . ' ' . $employee['last_name'];
    $employee['hire_date_formatted'] = $employee['hire_date'] ? thaiDate($employee['hire_date'], 'full') : '-';
    $employee['birth_date_formatted'] = $employee['birth_date'] ? thaiDate($employee['birth_date'], 'full') : '-';
    $employee['tenure'] = $employee['hire_date'] ? calculateTenure($employee['hire_date']) : '-';
    $employee['supervisor_name_formatted'] = $employee['supervisor_name'] ?? '-';
    $employee['supervisor_id_formatted'] = $employee['supervisor_user_id'] ? formatUserId($employee['supervisor_user_id']) : '-';
    
    // ดึงข้อมูลลูกน้อง
    $employee['subordinates'] = getSubordinatesList($id);
    
    // ดึงประวัติการเข้างานล่าสุด
    $employee['recent_attendance'] = getRecentAttendance($id, 5);
    
    // ดึงสถิติการเข้างาน
    $employee['attendance_stats'] = getDetailedAttendanceStats($id);
    
    // ดึงข้อมูลแผนกที่ดูแล (สำหรับ GM)
    if ($employee['position_level'] >= POSITION_GM) {
        $employee['managed_departments'] = getManagedDepartments($id);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $employee
    ]);
}

/**
 * ดึงโครงสร้างองค์กร
 */
function getEmployeeHierarchy() {
    $db = Database::getInstance();
    
    $departmentId = $_GET['department_id'] ?? null;
    
    // ตรวจสอบสิทธิ์
    if ($departmentId && !Auth::canViewDepartment($departmentId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    // ดึงข้อมูล GM ก่อน
    $sqlGM = "SELECT e.*, p.level, d.name as department_name
              FROM employees e
              JOIN positions p ON e.position_id = p.id
              LEFT JOIN departments d ON e.department_id = d.id
              WHERE p.level >= ? AND e.status = 1
              ORDER BY p.level DESC";
    
    $gms = $db->fetchAll($sqlGM, [POSITION_GM]);
    
    $hierarchy = [];
    
    foreach ($gms as $gm) {
        $gmNode = [
            'id' => $gm['id'],
            'user_id' => formatUserId($gm['user_id']),
            'name' => $gm['first_name'] . ' ' . $gm['last_name'],
            'position' => 'GM',
            'department' => $gm['department_name'],
            'children' => []
        ];
        
        // ดึงผู้จัดการที่รายงานตรงต่อ GM
        $sqlManager = "SELECT e.*, p.level, d.name as department_name
                       FROM employees e
                       JOIN positions p ON e.position_id = p.id
                       LEFT JOIN departments d ON e.department_id = d.id
                       WHERE e.reports_to = ? AND e.status = 1
                       ORDER BY d.name, e.first_name";
        
        $managers = $db->fetchAll($sqlManager, [$gm['id']]);
        
        foreach ($managers as $manager) {
            $managerNode = [
                'id' => $manager['id'],
                'user_id' => formatUserId($manager['user_id']),
                'name' => $manager['first_name'] . ' ' . $manager['last_name'],
                'position' => 'Manager',
                'department' => $manager['department_name'],
                'children' => []
            ];
            
            // ดึงหัวหน้าแผนก/ซุปเปอร์ไวเซอร์
            $sqlSupervisor = "SELECT e.*, p.level, d.name as department_name
                              FROM employees e
                              JOIN positions p ON e.position_id = p.id
                              LEFT JOIN departments d ON e.department_id = d.id
                              WHERE e.reports_to = ? AND e.status = 1
                              ORDER BY e.first_name";
            
            $supervisors = $db->fetchAll($sqlSupervisor, [$manager['id']]);
            
            foreach ($supervisors as $supervisor) {
                $supervisorNode = [
                    'id' => $supervisor['id'],
                    'user_id' => formatUserId($supervisor['user_id']),
                    'name' => $supervisor['first_name'] . ' ' . $supervisor['last_name'],
                    'position' => $supervisor['position_name'] ?? 'Supervisor',
                    'department' => $supervisor['department_name'],
                    'children' => []
                ];
                
                // ดึงพนักงานทั่วไป
                $sqlStaff = "SELECT e.*, p.name as position_name, d.name as department_name
                             FROM employees e
                             LEFT JOIN positions p ON e.position_id = p.id
                             LEFT JOIN departments d ON e.department_id = d.id
                             WHERE e.reports_to = ? AND e.status = 1
                             ORDER BY e.first_name";
                
                $staffs = $db->fetchAll($sqlStaff, [$supervisor['id']]);
                
                foreach ($staffs as $staff) {
                    $supervisorNode['children'][] = [
                        'id' => $staff['id'],
                        'user_id' => formatUserId($staff['user_id']),
                        'name' => $staff['first_name'] . ' ' . $staff['last_name'],
                        'position' => $staff['position_name'] ?? 'Staff',
                        'department' => $staff['department_name'],
                        'type' => 'staff'
                    ];
                }
                
                $managerNode['children'][] = $supervisorNode;
            }
            
            $gmNode['children'][] = $managerNode;
        }
        
        $hierarchy[] = $gmNode;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $hierarchy
    ]);
}

/**
 * ดึงรายชื่อลูกน้อง
 */
function getSubordinates() {
    $id = $_GET['id'] ?? $_SESSION['employee_id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Employee ID required']);
        return;
    }
    
    $db = Database::getInstance();
    
    // ใช้ recursive CTE ดึงลูกน้องทั้งหมด
    $sql = "WITH RECURSIVE emp_tree AS (
                SELECT id, user_id, first_name, last_name, position_id, department_id, reports_to, 1 as level
                FROM employees 
                WHERE reports_to = ?
                UNION ALL
                SELECT e.id, e.user_id, e.first_name, e.last_name, e.position_id, e.department_id, e.reports_to, et.level + 1
                FROM employees e
                INNER JOIN emp_tree et ON e.reports_to = et.id
            )
            SELECT 
                et.*,
                p.name as position_name,
                d.name as department_name,
                CONCAT(super.first_name, ' ', super.last_name) as supervisor_name
            FROM emp_tree et
            LEFT JOIN positions p ON et.position_id = p.id
            LEFT JOIN departments d ON et.department_id = d.id
            LEFT JOIN employees super ON et.reports_to = super.id
            ORDER BY et.level, d.name, et.first_name";
    
    $subordinates = $db->fetchAll($sql, [$id]);
    
    foreach ($subordinates as &$sub) {
        $sub['user_id_formatted'] = formatUserId($sub['user_id']);
        $sub['fullname'] = $sub['first_name'] . ' ' . $sub['last_name'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $subordinates,
        'total' => count($subordinates)
    ]);
}

/**
 * ค้นหาพนักงาน
 */
function searchEmployees() {
    $db = Database::getInstance();
    
    $keyword = $_GET['q'] ?? '';
    $departmentId = $_GET['department_id'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    
    if (strlen($keyword) < 2) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }
    
    $sql = "SELECT 
            e.id,
            e.user_id,
            e.first_name,
            e.last_name,
            e.email,
            e.phone,
            d.name as department_name,
            p.name as position_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.status = 1
            AND (e.user_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
    
    $searchTerm = "%{$keyword}%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    
    if ($departmentId) {
        $sql .= " AND e.department_id = ?";
        $params[] = $departmentId;
    }
    
    $sql .= " ORDER BY e.first_name LIMIT ?";
    $params[] = $limit;
    
    $results = $db->fetchAll($sql, $params);
    
    foreach ($results as &$row) {
        $row['user_id_formatted'] = formatUserId($row['user_id']);
        $row['fullname'] = $row['first_name'] . ' ' . $row['last_name'];
        $row['label'] = $row['user_id_formatted'] . ' - ' . $row['fullname'] . ' (' . $row['department_name'] . ')';
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

/**
 * สร้างพนักงานใหม่
 */
function createEmployee() {
    // ตรวจสอบสิทธิ์
    if (!Auth::hasLevel(POSITION_MANAGER)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // ตรวจสอบข้อมูลที่จำเป็น
    $required = ['user_id', 'first_name', 'last_name', 'department_id', 'position_id', 'hire_date'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field {$field} is required"]);
            return;
        }
    }
    
    // ตรวจสอบ user_id ซ้ำ
    $db = Database::getInstance();
    $existing = $db->fetchOne("SELECT id FROM employees WHERE user_id = ?", [$data['user_id']]);
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID already exists']);
        return;
    }
    
    // เตรียมข้อมูล
    $employeeData = [
        'user_id' => $data['user_id'],
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'nickname' => $data['nickname'] ?? null,
        'gender' => $data['gender'] ?? null,
        'birth_date' => $data['birth_date'] ?? null,
        'email' => $data['email'] ?? null,
        'phone' => $data['phone'] ?? null,
        'address' => $data['address'] ?? null,
        'department_id' => $data['department_id'],
        'position_id' => $data['position_id'],
        'shift_id' => $data['shift_id'] ?? 1,
        'reports_to' => $data['reports_to'] ?? null,
        'hire_date' => $data['hire_date'],
        'status' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // เริ่ม transaction
    $db->beginTransaction();
    
    try {
        // บันทึกข้อมูลพนักงาน
        $employeeId = $db->insert('employees', $employeeData);
        
        if (!$employeeId) {
            throw new Exception('Failed to create employee');
        }
        
        // สร้าง user login
        $username = $data['username'] ?? $data['user_id'];
        $password = password_hash($data['password'] ?? '123456', PASSWORD_DEFAULT);
        
        $userData = [
            'employee_id' => $employeeId,
            'username' => $username,
            'password' => $password,
            'role' => getDefaultRole($data['position_id']),
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('users', $userData);
        
        // บันทึกประวัติ
        $db->insert('employee_history', [
            'employee_id' => $employeeId,
            'action' => 'create',
            'description' => 'สร้างพนักงานใหม่',
            'changed_by' => $_SESSION['user_id'],
            'changed_at' => date('Y-m-d H:i:s')
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'สร้างพนักงานเรียบร้อย',
            'employee_id' => $employeeId,
            'user_id_formatted' => formatUserId($data['user_id'])
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create employee: ' . $e->getMessage()]);
    }
}

/**
 * อัปเดตข้อมูลพนักงาน
 */
function updateEmployeeData($id, $data) {
    // ตรวจสอบสิทธิ์
    if (!Auth::hasLevel(POSITION_SUPERVISOR) && $_SESSION['employee_id'] != $id) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $db = Database::getInstance();
    
    // ตรวจสอบว่าพนักงานมีอยู่
    $employee = $db->fetchOne("SELECT * FROM employees WHERE id = ?", [$id]);
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode(['error' => 'Employee not found']);
        return;
    }
    
    // เตรียมข้อมูลที่อนุญาตให้แก้ไข
    $allowedFields = [
        'first_name', 'last_name', 'nickname', 'gender', 'birth_date',
        'email', 'phone', 'address', 'department_id', 'position_id',
        'shift_id', 'reports_to', 'status'
    ];
    
    $updateData = [];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    if (empty($updateData)) {
        http_response_code(400);
        echo json_encode(['error' => 'No data to update']);
        return;
    }
    
    $updateData['updated_at'] = date('Y-m-d H:i:s');
    
    // อัปเดตข้อมูล
    $result = $db->update('employees', $updateData, 'id = ?', [$id]);
    
    if ($result !== false) {
        // บันทึกประวัติ
        $changes = [];
        foreach ($updateData as $field => $value) {
            if ($field != 'updated_at' && $employee[$field] != $value) {
                $changes[] = "$field: {$employee[$field]} -> $value";
            }
        }
        
        if (!empty($changes)) {
            $db->insert('employee_history', [
                'employee_id' => $id,
                'action' => 'update',
                'description' => 'แก้ไขข้อมูล: ' . implode(', ', $changes),
                'changed_by' => $_SESSION['user_id'],
                'changed_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'อัปเดตข้อมูลเรียบร้อย',
            'changes' => $changes
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update employee']);
    }
}

/**
 * ลบพนักงาน (เปลี่ยนสถานะ)
 */
function deleteEmployeeData($id) {
    // ตรวจสอบสิทธิ์
    if (!Auth::hasLevel(POSITION_MANAGER)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $db = Database::getInstance();
    
    // ตรวจสอบว่าพนักงานมีอยู่
    $employee = $db->fetchOne("SELECT * FROM employees WHERE id = ?", [$id]);
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode(['error' => 'Employee not found']);
        return;
    }
    
    // ตรวจสอบว่ามีลูกน้องหรือไม่
    $subordinates = $db->fetchValue("SELECT COUNT(*) FROM employees WHERE reports_to = ?", [$id]);
    
    if ($subordinates > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete employee with subordinates']);
        return;
    }
    
    // เริ่ม transaction
    $db->beginTransaction();
    
    try {
        // ลบ user login
        $db->delete('users', 'employee_id = ?', [$id]);
        
        // เปลี่ยนสถานะพนักงานเป็น inactive
        $db->update('employees', [
            'status' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);
        
        // บันทึกประวัติ
        $db->insert('employee_history', [
            'employee_id' => $id,
            'action' => 'delete',
            'description' => 'ลบพนักงาน',
            'changed_by' => $_SESSION['user_id'],
            'changed_at' => date('Y-m-d H:i:s')
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'ลบพนักงานเรียบร้อย'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete employee: ' . $e->getMessage()]);
    }
}

/**
 * ฟังก์ชันช่วยเหลือ
 */
function getEmployeeAttendanceStats($employeeId) {
    $db = Database::getInstance();
    
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    
    // สถิติวันนี้
    $todayStats = $db->fetchOne(
        "SELECT check_in_status, check_out_status, work_hours 
         FROM attendance_records 
         WHERE employee_id = ? AND date = ?",
        [$employeeId, $today]
    );
    
    // สถิติเดือนนี้
    $monthStats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN check_in_status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN check_in_time IS NULL THEN 1 ELSE 0 END) as absent_days,
            AVG(work_hours) as avg_work_hours,
            SUM(overtime_hours) as total_overtime
         FROM attendance_records 
         WHERE employee_id = ? AND date >= ?",
        [$employeeId, $monthStart]
    );
    
    return [
        'today' => $todayStats ? [
            'status' => $todayStats['check_in_status'] ?? 'absent',
            'work_hours' => $todayStats['work_hours'] ?? 0
        ] : null,
        'month' => $monthStats ? [
            'total_days' => (int)$monthStats['total_days'],
            'late_days' => (int)$monthStats['late_days'],
            'absent_days' => (int)$monthStats['absent_days'],
            'avg_work_hours' => round($monthStats['avg_work_hours'] ?? 0, 1),
            'total_overtime' => round($monthStats['total_overtime'] ?? 0, 1)
        ] : null
    ];
}

function getDetailedAttendanceStats($employeeId) {
    $db = Database::getInstance();
    
    $year = date('Y');
    $month = date('m');
    
    // สถิติรายเดือน
    $monthlyStats = $db->fetchAll(
        "SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            COUNT(*) as total_days,
            SUM(CASE WHEN check_in_status = 'on_time' THEN 1 ELSE 0 END) as on_time,
            SUM(CASE WHEN check_in_status = 'late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN check_in_time IS NULL THEN 1 ELSE 0 END) as absent,
            SUM(overtime_hours) as overtime,
            AVG(work_hours) as avg_work_hours
         FROM attendance_records 
         WHERE employee_id = ? AND YEAR(date) = ?
         GROUP BY DATE_FORMAT(date, '%Y-%m')
         ORDER BY month DESC
         LIMIT 6",
        [$employeeId, $year]
    );
    
    // สถิติรวมทั้งปี
    $yearStats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN check_in_status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN check_in_time IS NULL THEN 1 ELSE 0 END) as absent_days,
            SUM(overtime_hours) as total_overtime,
            AVG(work_hours) as avg_work_hours
         FROM attendance_records 
         WHERE employee_id = ? AND YEAR(date) = ?",
        [$employeeId, $year]
    );
    
    return [
        'monthly' => $monthlyStats,
        'yearly' => $yearStats ? [
            'total_days' => (int)$yearStats['total_days'],
            'late_days' => (int)$yearStats['late_days'],
            'absent_days' => (int)$yearStats['absent_days'],
            'attendance_rate' => $yearStats['total_days'] > 0 
                ? round((($yearStats['total_days'] - $yearStats['absent_days']) / $yearStats['total_days']) * 100, 1)
                : 0,
            'total_overtime' => round($yearStats['total_overtime'] ?? 0, 1),
            'avg_work_hours' => round($yearStats['avg_work_hours'] ?? 0, 1)
        ] : null
    ];
}

function getRecentAttendance($employeeId, $limit = 5) {
    $db = Database::getInstance();
    
    $sql = "SELECT 
            date,
            check_in_time,
            check_out_time,
            check_in_status,
            check_out_status,
            work_hours,
            overtime_hours
            FROM attendance_records 
            WHERE employee_id = ? AND date <= CURRENT_DATE
            ORDER BY date DESC
            LIMIT ?";
    
    $records = $db->fetchAll($sql, [$employeeId, $limit]);
    
    foreach ($records as &$record) {
        $record['date_formatted'] = thaiDate($record['date'], 'short');
        $record['check_in_formatted'] = $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-';
        $record['check_out_formatted'] = $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '-';
        $record['status_badge'] = getAttendanceStatusBadge($record);
    }
    
    return $records;
}

function getSubordinatesList($employeeId) {
    $db = Database::getInstance();
    
    $sql = "SELECT 
            e.id,
            e.user_id,
            e.first_name,
            e.last_name,
            p.name as position_name,
            d.name as department_name
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.reports_to = ? AND e.status = 1
            ORDER BY e.first_name";
    
    $subordinates = $db->fetchAll($sql, [$employeeId]);
    
    foreach ($subordinates as &$sub) {
        $sub['user_id_formatted'] = formatUserId($sub['user_id']);
        $sub['fullname'] = $sub['first_name'] . ' ' . $sub['last_name'];
    }
    
    return $subordinates;
}

function getManagedDepartments($employeeId) {
    $db = Database::getInstance();
    
    $sql = "SELECT d.* 
            FROM employee_departments ed
            JOIN departments d ON ed.department_id = d.id
            WHERE ed.employee_id = ? AND d.status = 1";
    
    return $db->fetchAll($sql, [$employeeId]);
}

function getStatusBadgeEmployee($status) {
    if ($status == 1) {
        return '<span class="badge bg-success">กำลังทำงาน</span>';
    } else {
        return '<span class="badge bg-secondary">ออกแล้ว</span>';
    }
}

function getAttendanceStatusBadge($record) {
    if (!$record['check_in_time']) {
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

function getDefaultRole($positionId) {
    $db = Database::getInstance();
    $level = $db->fetchValue("SELECT level FROM positions WHERE id = ?", [$positionId]);
    
    if ($level >= POSITION_GM) return 'gm';
    if ($level >= POSITION_MANAGER) return 'manager';
    if ($level >= POSITION_SUPERVISOR) return 'supervisor';
    return 'employee';
}