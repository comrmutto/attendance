<?php
/**
 * Employee.php - Model สำหรับจัดการข้อมูลพนักงาน
 */

require_once __DIR__ . '/Database.php';

class Employee {
    
    private $db;
    private $id;
    private $data = [];
    
    /**
     * Constructor
     */
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        
        if ($id) {
            $this->find($id);
        }
    }
    
    /**
     * Find employee by ID
     */
    public function find($id) {
        $sql = "SELECT e.*, 
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
                       u.role as user_role
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN positions p ON e.position_id = p.id
                LEFT JOIN shifts s ON e.shift_id = s.id
                LEFT JOIN employees super ON e.reports_to = super.id
                LEFT JOIN users u ON e.id = u.employee_id
                WHERE e.id = ?";
        
        $this->data = $this->db->fetchOne($sql, [$id]);
        
        if ($this->data) {
            $this->id = $id;
            return true;
        }
        
        return false;
    }
    
    /**
     * Find employee by user_id
     */
    public function findByUserId($userId) {
        $sql = "SELECT id FROM employees WHERE user_id = ?";
        $id = $this->db->fetchValue($sql, [$userId]);
        
        if ($id) {
            return $this->find($id);
        }
        
        return false;
    }
    
    /**
     * Get all employees
     */
    public static function all($filters = [], $page = 1, $limit = ITEMS_PER_PAGE) {
        $db = Database::getInstance();
        
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT SQL_CALC_FOUND_ROWS 
                e.*,
                d.name as department_name,
                p.name as position_name,
                p.level as position_level,
                CONCAT(super.first_name, ' ', super.last_name) as supervisor_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN positions p ON e.position_id = p.id
                LEFT JOIN employees super ON e.reports_to = super.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['department_id'])) {
            $sql .= " AND e.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (!empty($filters['position_id'])) {
            $sql .= " AND e.position_id = ?";
            $params[] = $filters['position_id'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND e.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (e.user_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY e.department_id, e.first_name LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $employees = $db->fetchAll($sql, $params);
        $total = $db->fetchValue("SELECT FOUND_ROWS()");
        
        return [
            'data' => $employees,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Create new employee
     */
    public function create($data) {
        // Validate required fields
        $required = ['user_id', 'first_name', 'last_name', 'department_id', 'position_id', 'hire_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
        
        // Check duplicate user_id
        $exists = $this->db->fetchValue("SELECT id FROM employees WHERE user_id = ?", [$data['user_id']]);
        if ($exists) {
            throw new Exception("User ID already exists");
        }
        
        // Prepare data
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
        
        $this->db->beginTransaction();
        
        try {
            // Insert employee
            $employeeId = $this->db->insert('employees', $employeeData);
            
            if (!$employeeId) {
                throw new Exception("Failed to create employee");
            }
            
            // Create user account
            $username = $data['username'] ?? $data['user_id'];
            $password = password_hash($data['password'] ?? '123456', PASSWORD_DEFAULT);
            $role = $this->getDefaultRole($data['position_id']);
            
            $userData = [
                'employee_id' => $employeeId,
                'username' => $username,
                'password' => $password,
                'role' => $role,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('users', $userData);
            
            $this->db->commit();
            
            $this->find($employeeId);
            return $employeeId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Update employee
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception("No employee loaded");
        }
        
        $allowedFields = [
            'first_name', 'last_name', 'nickname', 'gender', 'birth_date',
            'email', 'phone', 'address', 'department_id', 'position_id',
            'shift_id', 'reports_to', 'status'
        ];
        
        $updateData = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (empty($updateData)) {
            return 0;
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->update('employees', $updateData, 'id = ?', [$this->id]);
    }
    
    /**
     * Delete employee (soft delete)
     */
    public function delete() {
        if (!$this->id) {
            throw new Exception("No employee loaded");
        }
        
        // Check if has subordinates
        $subordinates = $this->db->fetchValue("SELECT COUNT(*) FROM employees WHERE reports_to = ?", [$this->id]);
        if ($subordinates > 0) {
            throw new Exception("Cannot delete employee with subordinates");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Disable user account
            $this->db->update('users', ['status' => 0], 'employee_id = ?', [$this->id]);
            
            // Soft delete employee
            $result = $this->db->update('employees', [
                'status' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$this->id]);
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get subordinates
     */
    public function getSubordinates($recursive = false) {
        if (!$this->id) {
            throw new Exception("No employee loaded");
        }
        
        if ($recursive) {
            $sql = "WITH RECURSIVE emp_tree AS (
                        SELECT id FROM employees WHERE reports_to = ?
                        UNION ALL
                        SELECT e.id FROM employees e
                        INNER JOIN emp_tree et ON e.reports_to = et.id
                    )
                    SELECT e.*, p.name as position_name
                    FROM emp_tree et
                    JOIN employees e ON et.id = e.id
                    LEFT JOIN positions p ON e.position_id = p.id
                    ORDER BY e.first_name";
        } else {
            $sql = "SELECT e.*, p.name as position_name
                    FROM employees e
                    LEFT JOIN positions p ON e.position_id = p.id
                    WHERE e.reports_to = ?
                    ORDER BY e.first_name";
        }
        
        return $this->db->fetchAll($sql, [$this->id]);
    }
    
    /**
     * Get supervisor
     */
    public function getSupervisor() {
        if (!$this->id || empty($this->data['reports_to'])) {
            return null;
        }
        
        return new self($this->data['reports_to']);
    }
    
    /**
     * Get attendance records
     */
    public function getAttendance($startDate = null, $endDate = null, $limit = 30) {
        if (!$this->id) {
            throw new Exception("No employee loaded");
        }
        
        $sql = "SELECT a.*, s.name as shift_name
                FROM attendance_records a
                LEFT JOIN shifts s ON a.shift_id = s.id
                WHERE a.employee_id = ?";
        
        $params = [$this->id];
        
        if ($startDate) {
            $sql .= " AND a.date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND a.date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY a.date DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get attendance statistics
     */
    public function getAttendanceStats($year = null, $month = null) {
        if (!$this->id) {
            throw new Exception("No employee loaded");
        }
        
        $year = $year ?: date('Y');
        $month = $month ?: date('m');
        
        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $sql = "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN check_in_time IS NOT NULL THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN check_in_status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN check_in_time IS NULL THEN 1 ELSE 0 END) as absent_days,
                SUM(overtime_hours) as total_overtime,
                AVG(work_hours) as avg_work_hours,
                MIN(check_in_time) as earliest_check_in,
                MAX(check_in_time) as latest_check_in
                FROM attendance_records
                WHERE employee_id = ? AND date BETWEEN ? AND ?";
        
        $stats = $this->db->fetchOne($sql, [$this->id, $startDate, $endDate]);
        
        if ($stats) {
            $stats['attendance_rate'] = $stats['total_days'] > 0 
                ? round(($stats['present_days'] / $stats['total_days']) * 100, 1)
                : 0;
        }
        
        return $stats;
    }
    
    /**
     * Get leave requests
     */
    public function getLeaves($status = null) {
        if (!$this->id) {
            throw new Exception("No employee loaded");
        }
        
        $sql = "SELECT l.*, lt.name as leave_type_name
                FROM leave_requests l
                LEFT JOIN leave_types lt ON l.leave_type_id = lt.id
                WHERE l.employee_id = ?";
        
        $params = [$this->id];
        
        if ($status) {
            $sql .= " AND l.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY l.start_date DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get remaining leave days
     */
    public function getRemainingLeaveDays() {
        if (!$this->id) {
            throw new Exception("No employee loaded");
        }
        
        $year = date('Y');
        
        // Get leave types
        $leaveTypes = $this->db->fetchAll("SELECT * FROM leave_types WHERE status = 1");
        
        $result = [];
        foreach ($leaveTypes as $type) {
            // Get total approved leaves
            $used = $this->db->fetchValue(
                "SELECT SUM(COALESCE(days_count, DATEDIFF(end_date, start_date) + 1)) as total_days
                 FROM leave_requests
                 WHERE employee_id = ? AND leave_type_id = ? AND status = 'approved'
                 AND YEAR(start_date) = ?",
                [$this->id, $type['id'], $year]
            );
            
            $result[$type['code']] = [
                'name' => $type['name'],
                'total' => $type['days_per_year'],
                'used' => (int)$used,
                'remaining' => $type['days_per_year'] - (int)$used
            ];
        }
        
        return $result;
    }
    
    /**
     * Get default role based on position
     */
    private function getDefaultRole($positionId) {
        $level = $this->db->fetchValue("SELECT level FROM positions WHERE id = ?", [$positionId]);
        
        if ($level >= POSITION_GM) return 'gm';
        if ($level >= POSITION_MANAGER) return 'manager';
        if ($level >= POSITION_SUPERVISOR) return 'supervisor';
        return 'employee';
    }
    
    /**
     * Get full name
     */
    public function getFullName() {
        return ($this->data['first_name'] ?? '') . ' ' . ($this->data['last_name'] ?? '');
    }
    
    /**
     * Get formatted user ID
     */
    public function getFormattedUserId() {
        return formatUserId($this->data['user_id'] ?? '');
    }
    
    /**
     * Check if employee is active
     */
    public function isActive() {
        return ($this->data['status'] ?? 0) == 1;
    }
    
    /**
     * Get employee data
     */
    public function toArray() {
        return $this->data;
    }
    
    /**
     * Get property
     */
    public function __get($name) {
        return $this->data[$name] ?? null;
    }
    
    /**
     * Set property
     */
    public function __set($name, $value) {
        $this->data[$name] = $value;
    }
    
    /**
     * Check if property exists
     */
    public function __isset($name) {
        return isset($this->data[$name]);
    }
    
    /**
     * Get ID
     */
    public function getId() {
        return $this->id;
    }
}