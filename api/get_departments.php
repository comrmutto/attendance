<?php
/**
 * get_departments.php - API สำหรับดึงข้อมูลแผนก
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
            getDepartmentList();
            break;
            
        case 'detail':
            getDepartmentDetail();
            break;
            
        case 'employees':
            getDepartmentEmployees();
            break;
            
        case 'stats':
            getDepartmentStats();
            break;
            
        case 'hierarchy':
            getDepartmentHierarchy();
            break;
            
        case 'managers':
            getDepartmentManagers();
            break;
            
        case 'positions':
            getDepartmentPositions();
            break;
            
        case 'summary':
            getDepartmentsSummary();
            break;
            
        case 'attendance-summary':
            getDepartmentAttendanceSummary();
            break;
            
        case 'tree':
            getDepartmentTree();
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
            createDepartment();
            break;
            
        case 'update':
            updateDepartment();
            break;
            
        case 'delete':
            deleteDepartment();
            break;
            
        case 'assign-manager':
            assignDepartmentManager();
            break;
            
        case 'remove-manager':
            removeDepartmentManager();
            break;
            
        case 'change-status':
            changeDepartmentStatus();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

/**
 * จัดการ PUT request
 */
function handlePutRequest() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Department ID required']);
        return;
    }
    
    updateDepartmentData($id, $data);
}

/**
 * จัดการ DELETE request
 */
function handleDeleteRequest() {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Department ID required']);
        return;
    }
    
    deleteDepartmentData($id);
}

/**
 * ดึงรายการแผนก
 */
function getDepartmentList() {
    $db = Database::getInstance();
    
    // รับพารามิเตอร์
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : ITEMS_PER_PAGE;
    $offset = ($page - 1) * $limit;
    
    $filters = [
        'status' => $_GET['status'] ?? null,
        'search' => $_GET['search'] ?? null,
        'has_manager' => $_GET['has_manager'] ?? null
    ];
    
    // ตรวจสอบสิทธิ์การดู
    $positionLevel = $_SESSION['position_level'] ?? 0;
    
    $sql = "SELECT SQL_CALC_FOUND_ROWS 
            d.*,
            COUNT(DISTINCT e.id) as employee_count,
            COUNT(DISTINCT CASE WHEN e.status = 1 THEN e.id END) as active_employee_count,
            GROUP_CONCAT(DISTINCT CONCAT(m.first_name, ' ', m.last_name) SEPARATOR ', ') as managers,
            GROUP_CONCAT(DISTINCT m.user_id) as manager_user_ids
            FROM departments d
            LEFT JOIN employees e ON d.id = e.department_id
            LEFT JOIN employee_departments ed ON d.id = ed.department_id
            LEFT JOIN employees m ON ed.employee_id = m.id
            WHERE 1=1";
    
    $params = [];
    
    // กรองตามสิทธิ์
    if ($positionLevel < POSITION_GM && !Auth::isAdmin()) {
        $currentDeptId = $_SESSION['department_id'] ?? 0;
        $sql .= " AND (d.id = ? OR EXISTS (
                    SELECT 1 FROM employee_departments 
                    WHERE employee_id = ? AND department_id = d.id
                  ))";
        $params[] = $currentDeptId;
        $params[] = $_SESSION['employee_id'] ?? 0;
    }
    
    if ($filters['status'] !== null) {
        $sql .= " AND d.status = ?";
        $params[] = $filters['status'];
    }
    
    if ($filters['search']) {
        $sql .= " AND (d.name LIKE ? OR d.code LIKE ?)";
        $searchTerm = "%{$filters['search']}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($filters['has_manager'] !== null) {
        if ($filters['has_manager'] == 1) {
            $sql .= " HAVING COUNT(ed.employee_id) > 0";
        } else {
            $sql .= " HAVING COUNT(ed.employee_id) = 0";
        }
    }
    
    $sql .= " GROUP BY d.id, d.name, d.code, d.status, d.created_at
              ORDER BY d.name LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $departments = $db->fetchAll($sql, $params);
    $totalRecords = $db->fetchValue("SELECT FOUND_ROWS()");
    
    // จัดรูปแบบข้อมูล
    foreach ($departments as &$dept) {
        $dept['status_badge'] = $dept['status'] == 1 
            ? '<span class="badge bg-success">เปิดใช้งาน</span>' 
            : '<span class="badge bg-danger">ปิดใช้งาน</span>';
        
        $dept['employee_count'] = (int)$dept['employee_count'];
        $dept['active_employee_count'] = (int)$dept['active_employee_count'];
        
        // จัดรูปแบบรายชื่อผู้จัดการ
        if ($dept['managers']) {
            $managerIds = explode(',', $dept['manager_user_ids']);
            $managerNames = explode(', ', $dept['managers']);
            $formattedManagers = [];
            
            foreach ($managerNames as $i => $name) {
                $userId = $managerIds[$i] ?? '';
                $formattedManagers[] = formatUserId($userId) . ' ' . $name;
            }
            
            $dept['managers_formatted'] = $formattedManagers;
        } else {
            $dept['managers_formatted'] = [];
        }
        
        unset($dept['manager_user_ids']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $departments,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => (int)$totalRecords,
            'total_pages' => ceil($totalRecords / $limit)
        ],
        'filters' => $filters,
        'can_create' => Auth::hasLevel(POSITION_MANAGER),
        'can_edit' => Auth::hasLevel(POSITION_MANAGER),
        'can_delete' => Auth::hasLevel(POSITION_GM)
    ]);
}

/**
 * ดึงรายละเอียดแผนก
 */
function getDepartmentDetail() {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Department ID required']);
        return;
    }
    
    // ตรวจสอบสิทธิ์การดู
    if (!Auth::canViewDepartment($id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $db = Database::getInstance();
    
    $sql = "SELECT 
            d.*,
            COUNT(DISTINCT e.id) as total_employees,
            COUNT(DISTINCT CASE WHEN e.status = 1 THEN e.id END) as active_employees,
            GROUP_CONCAT(DISTINCT CONCAT(m.first_name, ' ', m.last_name) SEPARATOR ', ') as managers,
            GROUP_CONCAT(DISTINCT m.id) as manager_ids,
            GROUP_CONCAT(DISTINCT m.user_id) as manager_user_ids
            FROM departments d
            LEFT JOIN employees e ON d.id = e.department_id
            LEFT JOIN employee_departments ed ON d.id = ed.department_id
            LEFT JOIN employees m ON ed.employee_id = m.id AND m.status = 1
            WHERE d.id = ?
            GROUP BY d.id";
    
    $department = $db->fetchOne($sql, [$id]);
    
    if (!$department) {
        http_response_code(404);
        echo json_encode(['error' => 'Department not found']);
        return;
    }
    
    // จัดรูปแบบข้อมูล
    $department['created_at_formatted'] = thaiDate($department['created_at'], 'full');
    $department['status_badge'] = $department['status'] == 1 
        ? '<span class="badge bg-success">เปิดใช้งาน</span>' 
        : '<span class="badge bg-danger">ปิดใช้งาน</span>';
    
    // ดึงรายชื่อผู้จัดการแบบละเอียด
    if ($department['manager_ids']) {
        $managerIds = explode(',', $department['manager_ids']);
        $managerUserIds = explode(',', $department['manager_user_ids']);
        $managerNames = explode(', ', $department['managers']);
        
        $managers = [];
        foreach ($managerIds as $i => $managerId) {
            $managers[] = [
                'id' => $managerId,
                'user_id' => $managerUserIds[$i] ?? '',
                'user_id_formatted' => formatUserId($managerUserIds[$i] ?? ''),
                'name' => $managerNames[$i] ?? ''
            ];
        }
        $department['managers_list'] = $managers;
    } else {
        $department['managers_list'] = [];
    }
    
    // ดึงสถิติพนักงาน
    $department['employee_stats'] = getDepartmentEmployeeStats($id);
    
    // ดึงสถิติการเข้างานวันนี้
    $department['today_stats'] = getDepartmentTodayStats($id);
    
    // ดึงรายชื่อตำแหน่งในแผนก
    $department['positions'] = getDepartmentPositions($id);
    
    echo json_encode([
        'success' => true,
        'data' => $department
    ]);
}

/**
 * ดึงรายชื่อพนักงานในแผนก
 */
function getDepartmentEmployees() {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Department ID required']);
        return;
    }
    
    // ตรวจสอบสิทธิ์การดู
    if (!Auth::canViewDepartment($id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $db = Database::getInstance();
    
    $status = $_GET['status'] ?? null;
    $positionId = $_GET['position_id'] ?? null;
    
    $sql = "SELECT 
            e.id,
            e.user_id,
            e.first_name,
            e.last_name,
            e.email,
            e.phone,
            e.status,
            p.name as position_name,
            p.level as position_level,
            CONCAT(super.first_name, ' ', super.last_name) as supervisor_name,
            super.user_id as supervisor_user_id
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN employees super ON e.reports_to = super.id
            WHERE e.department_id = ?";
    
    $params = [$id];
    
    if ($status !== null) {
        $sql .= " AND e.status = ?";
        $params[] = $status;
    }
    
    if ($positionId) {
        $sql .= " AND e.position_id = ?";
        $params[] = $positionId;
    }
    
    $sql .= " ORDER BY p.level DESC, e.first_name";
    
    $employees = $db->fetchAll($sql, $params);
    
    foreach ($employees as &$emp) {
        $emp['user_id_formatted'] = formatUserId($emp['user_id']);
        $emp['fullname'] = $emp['first_name'] . ' ' . $emp['last_name'];
        $emp['supervisor_formatted'] = $emp['supervisor_name'] 
            ? formatUserId($emp['supervisor_user_id']) . ' ' . $emp['supervisor_name']
            : '-';
        $emp['status_badge'] = $emp['status'] == 1 
            ? '<span class="badge bg-success">กำลังทำงาน</span>' 
            : '<span class="badge bg-secondary">ออกแล้ว</span>';
    }
    
    echo json_encode([
        'success' => true,
        'data' => $employees,
        'total' => count($employees)
    ]);
}

/**
 * ดึงสถิติของแผนก
 */
function getDepartmentStats() {
    $db = Database::getInstance();
    
    $departmentId = $_GET['department_id'] ?? null;
    
    // ตรวจสอบสิทธิ์
    if ($departmentId && !Auth::canViewDepartment($departmentId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $whereClause = "";
    $params = [];
    
    if ($departmentId) {
        $whereClause = "WHERE e.department_id = ?";
        $params[] = $departmentId;
    }
    
    // สถิติทั่วไป
    $generalStats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_employees,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_employees,
            SUM(CASE WHEN gender = 'M' THEN 1 ELSE 0 END) as male_count,
            SUM(CASE WHEN gender = 'F' THEN 1 ELSE 0 END) as female_count,
            AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) as avg_age
        FROM employees e
        $whereClause
    ", $params);
    
    // สถิติตามตำแหน่ง
    $positionStats = $db->fetchAll("
        SELECT 
            p.name as position_name,
            p.level,
            COUNT(e.id) as employee_count
        FROM positions p
        LEFT JOIN employees e ON p.id = e.position_id AND e.status = 1
        " . ($departmentId ? "AND e.department_id = ?" : "") . "
        GROUP BY p.id, p.name, p.level
        ORDER BY p.level
    ", $departmentId ? [$departmentId] : []);
    
    // สถิติอายุงาน
    $tenureStats = $db->fetchAll("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 1 THEN 'น้อยกว่า 1 ปี'
                WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) BETWEEN 1 AND 3 THEN '1-3 ปี'
                WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) BETWEEN 3 AND 5 THEN '3-5 ปี'
                WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) BETWEEN 5 AND 10 THEN '5-10 ปี'
                ELSE 'มากกว่า 10 ปี'
            END as tenure_range,
            COUNT(*) as employee_count
        FROM employees e
        WHERE status = 1
        " . ($departmentId ? "AND department_id = ?" : "") . "
        GROUP BY tenure_range
        ORDER BY tenure_range
    ", $departmentId ? [$departmentId] : []);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'general' => $generalStats,
            'by_position' => $positionStats,
            'by_tenure' => $tenureStats
        ]
    ]);
}

/**
 * ดึงสรุปข้อมูลทุกแผนก
 */
function getDepartmentsSummary() {
    $db = Database::getInstance();
    
    $sql = "SELECT 
            d.id,
            d.name,
            d.code,
            COUNT(DISTINCT e.id) as total_employees,
            COUNT(DISTINCT CASE WHEN e.status = 1 THEN e.id END) as active_employees,
            COUNT(DISTINCT ed.employee_id) as manager_count,
            (
                SELECT COUNT(*) 
                FROM attendance_records a 
                JOIN employees emp ON a.employee_id = emp.id
                WHERE emp.department_id = d.id AND a.date = CURDATE()
            ) as present_today
            FROM departments d
            LEFT JOIN employees e ON d.id = e.department_id
            LEFT JOIN employee_departments ed ON d.id = ed.department_id
            WHERE d.status = 1
            GROUP BY d.id, d.name, d.code
            ORDER BY d.name";
    
    $departments = $db->fetchAll($sql);
    
    foreach ($departments as &$dept) {
        $dept['attendance_rate'] = $dept['active_employees'] > 0 
            ? round(($dept['present_today'] / $dept['active_employees']) * 100, 1)
            : 0;
        $dept['present_today'] = (int)$dept['present_today'];
    }
    
    // คำนวณรวม
    $totalEmployees = array_sum(array_column($departments, 'total_employees'));
    $totalActive = array_sum(array_column($departments, 'active_employees'));
    $totalPresent = array_sum(array_column($departments, 'present_today'));
    
    echo json_encode([
        'success' => true,
        'data' => [
            'departments' => $departments,
            'summary' => [
                'total_departments' => count($departments),
                'total_employees' => $totalEmployees,
                'total_active' => $totalActive,
                'total_present' => $totalPresent,
                'overall_attendance_rate' => $totalActive > 0 
                    ? round(($totalPresent / $totalActive) * 100, 1)
                    : 0
            ]
        ]
    ]);
}

/**
 * สร้างแผนกใหม่
 */
function createDepartment() {
    // ตรวจสอบสิทธิ์
    if (!Auth::hasLevel(POSITION_MANAGER)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // ตรวจสอบข้อมูลที่จำเป็น
    $required = ['name', 'code'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field {$field} is required"]);
            return;
        }
    }
    
    $db = Database::getInstance();
    
    // ตรวจสอบรหัสซ้ำ
    $existing = $db->fetchOne("SELECT id FROM departments WHERE code = ?", [$data['code']]);
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['error' => 'Department code already exists']);
        return;
    }
    
    // บันทึกข้อมูล
    $departmentData = [
        'name' => $data['name'],
        'code' => strtoupper($data['code']),
        'description' => $data['description'] ?? null,
        'status' => $data['status'] ?? 1,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $departmentId = $db->insert('departments', $departmentData);
    
    if ($departmentId) {
        // ถ้ามีการระบุผู้จัดการ
        if (!empty($data['managers'])) {
            foreach ($data['managers'] as $managerId) {
                $db->insert('employee_departments', [
                    'employee_id' => $managerId,
                    'department_id' => $departmentId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'สร้างแผนกเรียบร้อย',
            'department_id' => $departmentId
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create department']);
    }
}

/**
 * อัปเดตข้อมูลแผนก
 */
function updateDepartmentData($id, $data) {
    // ตรวจสอบสิทธิ์
    if (!Auth::hasLevel(POSITION_MANAGER)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $db = Database::getInstance();
    
    // ตรวจสอบว่ามีแผนก
    $department = $db->fetchOne("SELECT * FROM departments WHERE id = ?", [$id]);
    
    if (!$department) {
        http_response_code(404);
        echo json_encode(['error' => 'Department not found']);
        return;
    }
    
    // ตรวจสอบรหัสซ้ำ (ถ้ามีการเปลี่ยน)
    if (isset($data['code']) && $data['code'] != $department['code']) {
        $existing = $db->fetchOne("SELECT id FROM departments WHERE code = ? AND id != ?", 
                                  [$data['code'], $id]);
        if ($existing) {
            http_response_code(400);
            echo json_encode(['error' => 'Department code already exists']);
            return;
        }
    }
    
    // เตรียมข้อมูลอัปเดต
    $updateData = [];
    $allowedFields = ['name', 'code', 'description', 'status'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            if ($field == 'code') {
                $updateData[$field] = strtoupper($data[$field]);
            } else {
                $updateData[$field] = $data[$field];
            }
        }
    }
    
    if (!empty($updateData)) {
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        $db->update('departments', $updateData, 'id = ?', [$id]);
    }
    
    // อัปเดตผู้จัดการ
    if (isset($data['managers'])) {
        // ลบผู้จัดการเก่า
        $db->delete('employee_departments', 'department_id = ?', [$id]);
        
        // เพิ่มผู้จัดการใหม่
        foreach ($data['managers'] as $managerId) {
            $db->insert('employee_departments', [
                'employee_id' => $managerId,
                'department_id' => $id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'อัปเดตข้อมูลแผนกเรียบร้อย'
    ]);
}

/**
 * ลบแผนก
 */
function deleteDepartmentData($id) {
    // ตรวจสอบสิทธิ์
    if (!Auth::hasLevel(POSITION_GM)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        return;
    }
    
    $db = Database::getInstance();
    
    // ตรวจสอบว่ามีพนักงานในแผนกหรือไม่
    $employeeCount = $db->fetchValue("SELECT COUNT(*) FROM employees WHERE department_id = ?", [$id]);
    
    if ($employeeCount > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete department with employees']);
        return;
    }
    
    // ลบข้อมูล
    $db->beginTransaction();
    
    try {
        // ลบความสัมพันธ์ผู้จัดการ
        $db->delete('employee_departments', 'department_id = ?', [$id]);
        
        // ลบแผนก
        $db->delete('departments', 'id = ?', [$id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'ลบแผนกเรียบร้อย'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete department: ' . $e->getMessage()]);
    }
}

/**
 * ฟังก์ชันช่วยเหลือ
 */
function getDepartmentEmployeeStats($departmentId) {
    $db = Database::getInstance();
    
    return $db->fetchOne("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN gender = 'M' THEN 1 ELSE 0 END) as male,
            SUM(CASE WHEN gender = 'F' THEN 1 ELSE 0 END) as female,
            MIN(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) as min_age,
            MAX(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) as max_age,
            AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) as avg_age
        FROM employees 
        WHERE department_id = ?
    ", [$departmentId]);
}

function getDepartmentTodayStats($departmentId) {
    $db = Database::getInstance();
    $today = date('Y-m-d');
    
    return $db->fetchOne("
        SELECT 
            COUNT(DISTINCT a.employee_id) as present,
            SUM(CASE WHEN a.check_in_status = 'late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN a.overtime_hours > 0 THEN 1 ELSE 0 END) as overtime,
            COUNT(DISTINCT l.id) as on_leave
        FROM employees e
        LEFT JOIN attendance_records a ON e.id = a.employee_id AND a.date = ?
        LEFT JOIN leave_requests l ON e.id = l.employee_id 
            AND ? BETWEEN l.start_date AND l.end_date 
            AND l.status = 'approved'
        WHERE e.department_id = ? AND e.status = 1
    ", [$today, $today, $departmentId]);
}

function getDepartmentPositions($departmentId) {
    $db = Database::getInstance();
    
    return $db->fetchAll("
        SELECT 
            p.id,
            p.name,
            p.level,
            COUNT(e.id) as employee_count
        FROM positions p
        LEFT JOIN employees e ON p.id = e.position_id 
            AND e.department_id = ? AND e.status = 1
        GROUP BY p.id, p.name, p.level
        ORDER BY p.level
    ", [$departmentId]);
}

function getDepartmentAttendanceSummary() {
    $db = Database::getInstance();
    $date = $_GET['date'] ?? date('Y-m-d');
    
    $sql = "SELECT 
            d.id,
            d.name,
            d.code,
            COUNT(DISTINCT e.id) as total_employees,
            COUNT(DISTINCT CASE WHEN a.check_in_time IS NOT NULL THEN a.employee_id END) as present,
            COUNT(DISTINCT CASE WHEN a.check_in_status = 'late' THEN a.employee_id END) as late,
            COUNT(DISTINCT CASE WHEN a.overtime_hours > 0 THEN a.employee_id END) as overtime,
            COUNT(DISTINCT l.id) as on_leave
            FROM departments d
            LEFT JOIN employees e ON d.id = e.department_id AND e.status = 1
            LEFT JOIN attendance_records a ON e.id = a.employee_id AND a.date = ?
            LEFT JOIN leave_requests l ON e.id = l.employee_id 
                AND ? BETWEEN l.start_date AND l.end_date 
                AND l.status = 'approved'
            WHERE d.status = 1
            GROUP BY d.id, d.name, d.code
            ORDER BY d.name";
    
    $results = $db->fetchAll($sql, [$date, $date]);
    
    foreach ($results as &$row) {
        $row['absent'] = $row['total_employees'] - $row['present'] - $row['on_leave'];
        $row['attendance_rate'] = $row['total_employees'] > 0 
            ? round(($row['present'] / $row['total_employees']) * 100, 1)
            : 0;
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'date_formatted' => thaiDate($date, 'full'),
        'data' => $results
    ]);
}