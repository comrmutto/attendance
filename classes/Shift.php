<?php
/**
 * Shift.php - Model สำหรับจัดการข้อมูลกะทำงาน
 */

require_once __DIR__ . '/Database.php';

class Shift {
    
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
     * Find shift by ID
     */
    public function find($id) {
        $sql = "SELECT s.*,
                       COUNT(DISTINCT e.id) as employee_count
                FROM shifts s
                LEFT JOIN employees e ON s.id = e.shift_id AND e.status = 1
                WHERE s.id = ?
                GROUP BY s.id";
        
        $this->data = $this->db->fetchOne($sql, [$id]);
        
        if ($this->data) {
            $this->id = $id;
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all shifts
     */
    public static function all($activeOnly = true) {
        $db = Database::getInstance();
        
        $sql = "SELECT s.*,
                       COUNT(DISTINCT e.id) as employee_count
                FROM shifts s
                LEFT JOIN employees e ON s.id = e.shift_id AND e.status = 1";
        
        if ($activeOnly) {
            $sql .= " WHERE s.status = 1";
        }
        
        $sql .= " GROUP BY s.id ORDER BY s.start_time";
        
        return $db->fetchAll($sql);
    }
    
    /**
     * Get shifts list for dropdown
     */
    public static function getList() {
        $db = Database::getInstance();
        return $db->fetchPairs("SELECT id, name FROM shifts WHERE status = 1 ORDER BY start_time");
    }
    
    /**
     * Create new shift
     */
    public function create($data) {
        $required = ['name', 'start_time', 'end_time'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
        
        $shiftData = [
            'name' => $data['name'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'grace_period' => $data['grace_period'] ?? 15,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $shiftId = $this->db->insert('shifts', $shiftData);
        
        if ($shiftId) {
            $this->find($shiftId);
            return $shiftId;
        }
        
        return false;
    }
    
    /**
     * Update shift
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception("No shift loaded");
        }
        
        $allowedFields = ['name', 'start_time', 'end_time', 'grace_period', 'description', 'status'];
        
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
        
        return $this->db->update('shifts', $updateData, 'id = ?', [$this->id]);
    }
    
    /**
     * Delete shift
     */
    public function delete() {
        if (!$this->id) {
            throw new Exception("No shift loaded");
        }
        
        // Check if has employees
        $employeeCount = $this->db->fetchValue(
            "SELECT COUNT(*) FROM employees WHERE shift_id = ?",
            [$this->id]
        );
        
        if ($employeeCount > 0) {
            throw new Exception("Cannot delete shift with assigned employees");
        }
        
        return $this->db->delete('shifts', 'id = ?', [$this->id]);
    }
    
    /**
     * Get employees assigned to this shift
     */
    public function getEmployees($activeOnly = true) {
        if (!$this->id) {
            return [];
        }
        
        $sql = "SELECT e.*, d.name as department_name, p.name as position_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN positions p ON e.position_id = p.id
                WHERE e.shift_id = ?";
        
        $params = [$this->id];
        
        if ($activeOnly) {
            $sql .= " AND e.status = 1";
        }
        
        $sql .= " ORDER BY e.first_name";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get employee count
     */
    public function getEmployeeCount($activeOnly = true) {
        if (!$this->id) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) FROM employees WHERE shift_id = ?";
        $params = [$this->id];
        
        if ($activeOnly) {
            $sql .= " AND status = 1";
        }
        
        return $this->db->fetchValue($sql, $params);
    }
    
    /**
     * Get attendance summary for this shift
     */
    public function getAttendanceSummary($date = null) {
        if (!$this->id) {
            return [];
        }
        
        $date = $date ?: date('Y-m-d');
        
        $sql = "SELECT 
                COUNT(DISTINCT e.id) as total_employees,
                COUNT(DISTINCT CASE WHEN a.check_in_time IS NOT NULL THEN a.employee_id END) as present,
                COUNT(DISTINCT CASE WHEN a.check_in_status = 'late' THEN a.employee_id END) as late,
                AVG(a.work_hours) as avg_work_hours,
                SUM(a.overtime_hours) as total_overtime
                FROM employees e
                LEFT JOIN attendance_records a ON e.id = a.employee_id AND a.date = ? AND a.shift_id = ?
                WHERE e.shift_id = ? AND e.status = 1";
        
        $stats = $this->db->fetchOne($sql, [$date, $this->id, $this->id]);
        
        if ($stats) {
            $stats['absent'] = $stats['total_employees'] - $stats['present'];
            $stats['attendance_rate'] = $stats['total_employees'] > 0 
                ? round(($stats['present'] / $stats['total_employees']) * 100, 1)
                : 0;
        }
        
        return $stats;
    }
    
    /**
     * Check if current time is within shift hours
     */
    public function isWithinShift($time = null) {
        if (!$this->id) {
            return false;
        }
        
        $time = $time ?: time();
        $currentTime = date('H:i:s', $time);
        
        $start = strtotime($this->data['start_time']);
        $end = strtotime($this->data['end_time']);
        $current = strtotime($currentTime);
        
        if ($end < $start) {
            // Overnight shift
            return ($current >= $start) || ($current <= $end);
        } else {
            return ($current >= $start && $current <= $end);
        }
    }
    
    /**
     * Get shift duration in hours
     */
    public function getDuration() {
        if (!$this->id) {
            return 0;
        }
        
        $start = strtotime($this->data['start_time']);
        $end = strtotime($this->data['end_time']);
        
        if ($end < $start) {
            $end += 24 * 3600; // Add one day
        }
        
        return round(($end - $start) / 3600, 1);
    }
    
    /**
     * Determine shift for given time
     */
    public static function determineShift($datetime) {
        $db = Database::getInstance();
        $time = date('H:i:s', strtotime($datetime));
        
        $shifts = self::all();
        
        foreach ($shifts as $shift) {
            $start = strtotime($shift['start_time']);
            $end = strtotime($shift['end_time']);
            $current = strtotime($time);
            
            if ($end < $start) {
                // Overnight shift
                if ($current >= $start || $current <= $end) {
                    return $shift['id'];
                }
            } else {
                if ($current >= $start && $current <= $end) {
                    return $shift['id'];
                }
            }
        }
        
        // Default to first shift
        return $shifts[0]['id'] ?? 1;
    }
    
    /**
     * Get shift data
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
     * Check if active
     */
    public function isActive() {
        return ($this->data['status'] ?? 0) == 1;
    }
}