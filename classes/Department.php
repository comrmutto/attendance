<?php
/**
 * Department.php - Model สำหรับจัดการข้อมูลแผนก
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Employee.php';

class Department {
    
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
     * Find department by ID
     */
    public function find($id) {
        $sql = "SELECT d.*,
                       COUNT(DISTINCT e.id) as employee_count,
                       COUNT(DISTINCT CASE WHEN e.status = 1 THEN e.id END) as active_employee_count
                FROM departments d
                LEFT JOIN employees e ON d.id = e.department_id
                WHERE d.id = ?
                GROUP BY d.id";
        
        $this->data = $this->db->fetchOne($sql, [$id]);
        
        if ($this->data) {
            $this->id = $id;
            
            // Get managers
            $this->data['managers'] = $this->getManagers();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Find department by code
     */
    public function findByCode($code) {
        $id = $this->db->fetchValue("SELECT id FROM departments WHERE code = ?", [$code]);
        
        if ($id) {
            return $this->find($id);
        }
        
        return false;
    }
    
    /**
     * Get all departments
     */
    public static function all($filters = [], $page = 1, $limit = ITEMS_PER_PAGE) {
        $db = Database::getInstance();
        
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT SQL_CALC_FOUND_ROWS 
                d.*,
                COUNT(DISTINCT e.id) as employee_count,
                COUNT(DISTINCT CASE WHEN e.status = 1 THEN e.id END) as active_employee_count
                FROM departments d
                LEFT JOIN employees e ON d.id = e.department_id
                WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['status'])) {
            $sql .= " AND d.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (d.name LIKE ? OR d.code LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " GROUP BY d.id, d.name, d.code, d.status, d.created_at
                  ORDER BY d.name LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $departments = $db->fetchAll($sql, $params);
        $total = $db->fetchValue("SELECT FOUND_ROWS()");
        
        return [
            'data' => $departments,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Get active departments list for dropdown
     */
    public static function getList() {
        $db = Database::getInstance();
        return $db->fetchPairs("SELECT id, name FROM departments WHERE status = 1 ORDER BY name");
    }
    
    /**
     * Create new department
     */
    public function create($data) {
        $required = ['name', 'code'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
        
        // Check duplicate code
        $exists = $this->db->fetchValue("SELECT id FROM departments WHERE code = ?", [$data['code']]);
        if ($exists) {
            throw new Exception("Department code already exists");
        }
        
        $departmentData = [
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $departmentId = $this->db->insert('departments', $departmentData);
        
        if ($departmentId) {
            // Assign managers if provided
            if (!empty($data['managers'])) {
                foreach ($data['managers'] as $managerId) {
                    $this->assignManager($managerId);
                }
            }
            
            $this->find($departmentId);
            return $departmentId;
        }
        
        return false;
    }
    
    /**
     * Update department
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception("No department loaded");
        }
        
        // Check duplicate code if changed
        if (!empty($data['code']) && $data['code'] != $this->data['code']) {
            $exists = $this->db->fetchValue(
                "SELECT id FROM departments WHERE code = ? AND id != ?",
                [$data['code'], $this->id]
            );
            if ($exists) {
                throw new Exception("Department code already exists");
            }
        }
        
        $allowedFields = ['name', 'code', 'description', 'status'];
        
        $updateData = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field == 'code') {
                    $updateData[$field] = strtoupper($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        if (empty($updateData)) {
            return 0;
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->update('departments', $updateData, 'id = ?', [$this->id]);
    }
    
    /**
     * Delete department
     */
    public function delete() {
        if (!$this->id) {
            throw new Exception("No department loaded");
        }
        
        // Check if has employees
        $employeeCount = $this->db->fetchValue(
            "SELECT COUNT(*) FROM employees WHERE department_id = ?",
            [$this->id]
        );
        
        if ($employeeCount > 0) {
            throw new Exception("Cannot delete department with employees");
        }
        
        return $this->db->delete('departments', 'id = ?', [$this->id]);
    }
    
    /**
     * Assign manager to department
     */
    public function assignManager($employeeId) {
        if (!$this->id) {
            throw new Exception("No department loaded");
        }
        
        // Check if already assigned
        $exists = $this->db->fetchValue(
            "SELECT id FROM employee_departments WHERE employee_id = ? AND department_id = ?",
            [$employeeId, $this->id]
        );
        
        if ($exists) {
            return true;
        }
        
        return $this->db->insert('employee_departments', [
            'employee_id' => $employeeId,
            'department_id' => $this->id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Remove manager from department
     */
    public function removeManager($employeeId) {
        if (!$this->id) {
            throw new Exception("No department loaded");
        }
        
        return $this->db->delete(
            'employee_departments',
            'employee_id = ? AND department_id = ?',
            [$employeeId, $this->id]
        );
    }
    
    /**
     * Get managers of department
     */
    public function getManagers() {
        if (!$this->id) {
            return [];
        }
        
        $sql = "SELECT e.*, p.name as position_name
                FROM employee_departments ed
                JOIN employees e ON ed.employee_id = e.id
                LEFT JOIN positions p ON e.position_id = p.id
                WHERE ed.department_id = ? AND e.status = 1
                ORDER BY e.first_name";
        
        return $this->db->fetchAll($sql, [$this->id]);
    }
    
    /**
     * Get employees in department
     */
    public function getEmployees($activeOnly = true) {
        if (!$this->id) {
            return [];
        }
        
        $sql = "SELECT e.*, p.name as position_name
                FROM employees e
                LEFT JOIN positions p ON e.position_id = p.id
                WHERE e.department_id = ?";
        
        $params = [$this->id];
        
        if ($activeOnly) {
            $sql .= " AND e.status = 1";
        }
        
        $sql .= " ORDER BY p.level DESC, e.first_name";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get employee count
     */
    public function getEmployeeCount($activeOnly = true) {
        if (!$this->id) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) FROM employees WHERE department_id = ?";
        $params = [$this->id];
        
        if ($activeOnly) {
            $sql .= " AND status = 1";
        }
        
        return $this->db->fetchValue($sql, $params);
    }
    
    /**
     * Get department statistics
     */
    public function getStats() {
        if (!$this->id) {
            return [];
        }
        
        // Employee stats
        $employeeStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN gender = 'M' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN gender = 'F' THEN 1 ELSE 0 END) as female,
                AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) as avg_age,
                MIN(TIMESTAMPDIFF(YEAR, hire_date, CURDATE())) as min_tenure,
                MAX(TIMESTAMPDIFF(YEAR, hire_date, CURDATE())) as max_tenure,
                AVG(TIMESTAMPDIFF(YEAR, hire_date, CURDATE())) as avg_tenure
            FROM employees
            WHERE department_id = ? AND status = 1",
            [$this->id]
        );
        
        // Position distribution
        $positions = $this->db->fetchAll(
            "SELECT 
                p.name as position_name,
                p.level,
                COUNT(e.id) as count
            FROM positions p
            LEFT JOIN employees e ON p.id = e.position_id AND e.department_id = ? AND e.status = 1
            GROUP BY p.id, p.name, p.level
            ORDER BY p.level",
            [$this->id]
        );
        
        // Today's attendance
        $today = date('Y-m-d');
        $attendanceToday = $this->db->fetchOne(
            "SELECT 
                COUNT(DISTINCT a.employee_id) as present,
                SUM(CASE WHEN a.check_in_status = 'late' THEN 1 ELSE 0 END) as late,
                COUNT(l.id) as on_leave
            FROM employees e
            LEFT JOIN attendance_records a ON e.id = a.employee_id AND a.date = ?
            LEFT JOIN leave_requests l ON e.id = l.employee_id 
                AND ? BETWEEN l.start_date AND l.end_date 
                AND l.status = 'approved'
            WHERE e.department_id = ? AND e.status = 1",
            [$today, $today, $this->id]
        );
        
        return [
            'employees' => $employeeStats,
            'positions' => $positions,
            'attendance_today' => $attendanceToday,
            'attendance_rate' => $employeeStats['total'] > 0 
                ? round(($attendanceToday['present'] / $employeeStats['total']) * 100, 1)
                : 0
        ];
    }
    
    /**
     * Get attendance summary for period
     */
    public function getAttendanceSummary($startDate, $endDate) {
        if (!$this->id) {
            return [];
        }
        
        $sql = "SELECT 
                a.date,
                COUNT(DISTINCT a.employee_id) as present_count,
                COUNT(DISTINCT CASE WHEN a.check_in_status = 'late' THEN a.employee_id END) as late_count,
                AVG(a.work_hours) as avg_work_hours,
                SUM(a.overtime_hours) as total_overtime
                FROM attendance_records a
                JOIN employees e ON a.employee_id = e.id
                WHERE e.department_id = ? AND a.date BETWEEN ? AND ?
                GROUP BY a.date
                ORDER BY a.date";
        
        return $this->db->fetchAll($sql, [$this->id, $startDate, $endDate]);
    }
    
    /**
     * Check if employee is manager of this department
     */
    public function isManager($employeeId) {
        if (!$this->id) {
            return false;
        }
        
        $count = $this->db->fetchValue(
            "SELECT COUNT(*) FROM employee_departments WHERE employee_id = ? AND department_id = ?",
            [$employeeId, $this->id]
        );
        
        return $count > 0;
    }
    
    /**
     * Get department data
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
     * Get ID
     */
    public function getId() {
        return $this->id;
    }
    
    /**
     * Get name
     */
    public function getName() {
        return $this->data['name'] ?? '';
    }
    
    /**
     * Get code
     */
    public function getCode() {
        return $this->data['code'] ?? '';
    }
    
    /**
     * Check if active
     */
    public function isActive() {
        return ($this->data['status'] ?? 0) == 1;
    }
}