<?php
/**
 * Attendance.php - Model สำหรับจัดการข้อมูลการเข้างาน
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Employee.php';

class Attendance {
    
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
     * Find attendance record by ID
     */
    public function find($id) {
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
                       s.grace_period
                FROM attendance_records a
                JOIN employees e ON a.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN positions p ON e.position_id = p.id
                LEFT JOIN shifts s ON a.shift_id = s.id
                WHERE a.id = ?";
        
        $this->data = $this->db->fetchOne($sql, [$id]);
        
        if ($this->data) {
            $this->id = $id;
            return true;
        }
        
        return false;
    }
    
    /**
     * Get attendance records with filters
     */
    public static function getRecords($filters = [], $page = 1, $limit = ITEMS_PER_PAGE) {
        $db = Database::getInstance();
        
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT SQL_CALC_FOUND_ROWS 
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
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['employee_id'])) {
            $sql .= " AND a.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department_id'])) {
            $sql .= " AND e.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND a.date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND a.date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['shift_id'])) {
            $sql .= " AND a.shift_id = ?";
            $params[] = $filters['shift_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND (a.check_in_status = ? OR a.check_out_status = ?)";
            $params[] = $filters['status'];
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY a.date DESC, a.employee_id LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $records = $db->fetchAll($sql, $params);
        $total = $db->fetchValue("SELECT FOUND_ROWS()");
        
        // Format data
        foreach ($records as &$record) {
            $record['user_id_formatted'] = formatUserId($record['user_id']);
            $record['employee_name'] = $record['first_name'] . ' ' . $record['last_name'];
            $record['date_formatted'] = thaiDate($record['date'], 'short');
            $record['check_in_formatted'] = $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-';
            $record['check_out_formatted'] = $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '-';
        }
        
        return [
            'data' => $records,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Get today's attendance
     */
    public static function getToday($filters = []) {
        $filters['date_from'] = date('Y-m-d');
        $filters['date_to'] = date('Y-m-d');
        
        return self::getRecords($filters, 1, 1000);
    }
    
    /**
     * Get employee attendance
     */
    public static function getEmployeeAttendance($employeeId, $startDate = null, $endDate = null) {
        $filters = ['employee_id' => $employeeId];
        
        if ($startDate) {
            $filters['date_from'] = $startDate;
        }
        
        if ($endDate) {
            $filters['date_to'] = $endDate;
        }
        
        return self::getRecords($filters, 1, 1000);
    }
    
    /**
     * Create attendance record from log
     */
    public static function createFromLog($logId) {
        $db = Database::getInstance();
        
        // Get log data
        $log = $db->fetchOne("SELECT * FROM attendance_logs WHERE id = ?", [$logId]);
        
        if (!$log) {
            throw new Exception("Log not found");
        }
        
        // Get employee
        $employee = $db->fetchOne("SELECT * FROM employees WHERE user_id = ?", [$log['user_id']]);
        
        if (!$employee) {
            throw new Exception("Employee not found");
        }
        
        // Determine shift
        $scanTime = strtotime($log['scan_time']);
        $hour = date('H', $scanTime);
        
        if ($hour >= 5 && $hour < 14) {
            // Morning shift (5:00 - 14:00)
            $shiftId = 1;
            $date = date('Y-m-d', $scanTime);
        } else {
            // Night shift (14:00 - 5:00 next day)
            $shiftId = 2;
            if ($hour >= 0 && $hour < 5) {
                $date = date('Y-m-d', strtotime('-1 day', $scanTime));
            } else {
                $date = date('Y-m-d', $scanTime);
            }
        }
        
        // Check if record exists
        $existing = $db->fetchOne(
            "SELECT id FROM attendance_records WHERE employee_id = ? AND date = ?",
            [$employee['id'], $date]
        );
        
        $inoutStatus = $log['inout_status'] ?? 1;
        
        $db->beginTransaction();
        
        try {
            if ($existing) {
                // Update existing record
                if ($inoutStatus == 1 && empty($existing['check_in_time'])) {
                    $db->update('attendance_records', [
                        'check_in_time' => $log['scan_time'],
                        'check_in_log_id' => $logId,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$existing['id']]);
                    
                    $attendanceId = $existing['id'];
                } elseif ($inoutStatus == 2 && empty($existing['check_out_time'])) {
                    $db->update('attendance_records', [
                        'check_out_time' => $log['scan_time'],
                        'check_out_log_id' => $logId,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$existing['id']]);
                    
                    $attendanceId = $existing['id'];
                } else {
                    $attendanceId = $existing['id'];
                }
            } else {
                // Create new record
                if ($inoutStatus == 1) {
                    $attendanceId = $db->insert('attendance_records', [
                        'employee_id' => $employee['id'],
                        'shift_id' => $shiftId,
                        'date' => $date,
                        'check_in_time' => $log['scan_time'],
                        'check_in_log_id' => $logId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    $attendanceId = $db->insert('attendance_records', [
                        'employee_id' => $employee['id'],
                        'shift_id' => $shiftId,
                        'date' => $date,
                        'check_out_time' => $log['scan_time'],
                        'check_out_log_id' => $logId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            // Mark log as processed
            $db->update('attendance_logs', [
                'processed' => 1,
                'attendance_id' => $attendanceId
            ], 'id = ?', [$logId]);
            
            $db->commit();
            
            // Process status and hours
            self::processRecord($attendanceId);
            
            return $attendanceId;
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Process attendance record (calculate status, hours)
     */
    public static function processRecord($id) {
        $db = Database::getInstance();
        
        $record = $db->fetchOne("SELECT * FROM attendance_records WHERE id = ?", [$id]);
        
        if (!$record) {
            return false;
        }
        
        $shift = $db->fetchOne("SELECT * FROM shifts WHERE id = ?", [$record['shift_id']]);
        
        if (!$shift) {
            return false;
        }
        
        $updateData = [];
        
        // Process check-in
        if ($record['check_in_time']) {
            $checkInTime = strtotime($record['check_in_time']);
            $shiftStart = strtotime($record['date'] . ' ' . $shift['start_time']);
            $graceEnd = $shiftStart + ($shift['grace_period'] * 60);
            
            if ($checkInTime <= $graceEnd) {
                $updateData['check_in_status'] = 'on_time';
            } else {
                $updateData['check_in_status'] = 'late';
                $updateData['late_minutes'] = round(($checkInTime - $shiftStart) / 60);
            }
        }
        
        // Process check-out
        if ($record['check_out_time']) {
            $checkOutTime = strtotime($record['check_out_time']);
            
            if ($shift['start_time'] == MORNING_SHIFT_START) {
                // Morning shift
                $shiftEnd = strtotime($record['date'] . ' ' . $shift['end_time']);
                
                if ($checkOutTime < $shiftEnd) {
                    $updateData['check_out_status'] = 'early_leave';
                    $updateData['early_leave_minutes'] = round(($shiftEnd - $checkOutTime) / 60);
                } else {
                    $updateData['check_out_status'] = 'on_time';
                }
                
                // Calculate overtime
                if ($checkOutTime > $shiftEnd) {
                    $updateData['overtime_hours'] = round(($checkOutTime - $shiftEnd) / 3600, 1);
                }
            } else {
                // Night shift
                $shiftEnd = strtotime($record['date'] . ' +1 day ' . $shift['end_time']);
                
                if ($checkOutTime > $shiftEnd) {
                    $updateData['check_out_status'] = 'overtime';
                    $updateData['overtime_hours'] = round(($checkOutTime - $shiftEnd) / 3600, 1);
                } else {
                    $updateData['check_out_status'] = 'on_time';
                }
            }
        }
        
        // Calculate work hours
        if ($record['check_in_time'] && $record['check_out_time']) {
            $workHours = (strtotime($record['check_out_time']) - strtotime($record['check_in_time'])) / 3600;
            $updateData['work_hours'] = round($workHours, 1);
        }
        
        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $db->update('attendance_records', $updateData, 'id = ?', [$id]);
        }
        
        return true;
    }
    
    /**
     * Process daily attendance for all employees
     */
    public static function processDaily($date = null) {
        $date = $date ?: date('Y-m-d');
        $db = Database::getInstance();
        
        // Call stored procedure
        $db->query("CALL process_daily_attendance(?)", [$date]);
        
        return true;
    }
    
    /**
     * Get attendance summary
     */
    public static function getSummary($date = null, $departmentId = null) {
        $date = $date ?: date('Y-m-d');
        $db = Database::getInstance();
        
        // Total employees
        $sqlTotal = "SELECT COUNT(*) FROM employees WHERE status = 1";
        $paramsTotal = [];
        
        if ($departmentId) {
            $sqlTotal .= " AND department_id = ?";
            $paramsTotal[] = $departmentId;
        }
        
        $totalEmployees = $db->fetchValue($sqlTotal, $paramsTotal);
        
        // Present today
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
        
        // Late today
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
        
        // On leave
        $sqlLeave = "SELECT COUNT(*) FROM leave_requests 
                     WHERE ? BETWEEN start_date AND end_date 
                     AND status = 'approved'";
        $paramsLeave = [$date];
        
        if ($departmentId) {
            $sqlLeave .= " AND employee_id IN (SELECT id FROM employees WHERE department_id = ?)";
            $paramsLeave[] = $departmentId;
        }
        
        $onLeave = $db->fetchValue($sqlLeave, $paramsLeave);
        
        // Overtime
        $sqlOT = "SELECT COUNT(DISTINCT a.employee_id)
                  FROM attendance_records a
                  JOIN employees e ON a.employee_id = e.id
                  WHERE a.date = ? AND a.overtime_hours > 0";
        $paramsOT = [$date];
        
        if ($departmentId) {
            $sqlOT .= " AND e.department_id = ?";
            $paramsOT[] = $departmentId;
        }
        
        $overtimeToday = $db->fetchValue($sqlOT, $paramsOT);
        
        return [
            'date' => $date,
            'date_formatted' => thaiDate($date, 'full'),
            'total_employees' => (int)$totalEmployees,
            'present_today' => (int)$presentToday,
            'absent_today' => (int)($totalEmployees - $presentToday - $onLeave),
            'late_today' => (int)$lateToday,
            'on_leave' => (int)$onLeave,
            'overtime_today' => (int)$overtimeToday,
            'attendance_rate' => $totalEmployees > 0 
                ? round(($presentToday / $totalEmployees) * 100, 1)
                : 0
        ];
    }
    
    /**
     * Get statistics by department
     */
    public static function getByDepartment($date = null) {
        $date = $date ?: date('Y-m-d');
        $db = Database::getInstance();
        
        $sql = "SELECT 
                d.id,
                d.name,
                d.code,
                COUNT(DISTINCT e.id) as total_employees,
                COUNT(DISTINCT CASE WHEN a.check_in_time IS NOT NULL THEN a.employee_id END) as present,
                COUNT(DISTINCT CASE WHEN a.check_in_status = 'late' THEN a.employee_id END) as late,
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
        
        return $results;
    }
    
    /**
     * Get statistics by shift
     */
    public static function getByShift($date = null) {
        $date = $date ?: date('Y-m-d');
        $db = Database::getInstance();
        
        $sql = "SELECT 
                s.id,
                s.name,
                s.start_time,
                s.end_time,
                COUNT(DISTINCT a.employee_id) as employee_count,
                COUNT(DISTINCT CASE WHEN a.check_in_status = 'late' THEN a.employee_id END) as late_count,
                AVG(a.work_hours) as avg_work_hours,
                SUM(a.overtime_hours) as total_overtime
                FROM shifts s
                LEFT JOIN attendance_records a ON s.id = a.shift_id AND a.date = ?
                GROUP BY s.id, s.name, s.start_time, s.end_time";
        
        return $db->fetchAll($sql, [$date]);
    }
    
    /**
     * Get hourly statistics
     */
    public static function getHourlyStats($date = null) {
        $date = $date ?: date('Y-m-d');
        $db = Database::getInstance();
        
        $sql = "SELECT 
                HOUR(check_in_time) as hour,
                COUNT(*) as check_in_count
                FROM attendance_records
                WHERE date = ? AND check_in_time IS NOT NULL
                GROUP BY HOUR(check_in_time)
                ORDER BY hour";
        
        $checkIns = $db->fetchAll($sql, [$date]);
        
        $sql = "SELECT 
                HOUR(check_out_time) as hour,
                COUNT(*) as check_out_count
                FROM attendance_records
                WHERE date = ? AND check_out_time IS NOT NULL
                GROUP BY HOUR(check_out_time)
                ORDER BY hour";
        
        $checkOuts = $db->fetchAll($sql, [$date]);
        
        $result = [];
        for ($i = 0; $i < 24; $i++) {
            $result[$i] = [
                'hour' => $i,
                'check_in' => 0,
                'check_out' => 0
            ];
        }
        
        foreach ($checkIns as $ci) {
            $result[$ci['hour']]['check_in'] = (int)$ci['check_in_count'];
        }
        
        foreach ($checkOuts as $co) {
            $result[$co['hour']]['check_out'] = (int)$co['check_out_count'];
        }
        
        return array_values($result);
    }
    
    /**
     * Manual entry
     */
    public function manualEntry($data) {
        $required = ['employee_id', 'date', 'check_in_time', 'shift_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
        
        // Check duplicate
        $existing = $this->db->fetchOne(
            "SELECT id FROM attendance_records WHERE employee_id = ? AND date = ?",
            [$data['employee_id'], $data['date']]
        );
        
        if ($existing) {
            throw new Exception("Attendance record already exists for this date");
        }
        
        $recordData = [
            'employee_id' => $data['employee_id'],
            'shift_id' => $data['shift_id'],
            'date' => $data['date'],
            'check_in_time' => $data['check_in_time'],
            'check_out_time' => $data['check_out_time'] ?? null,
            'check_in_status' => 'manual',
            'check_out_status' => $data['check_out_time'] ? 'manual' : 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->beginTransaction();
        
        try {
            $recordId = $this->db->insert('attendance_records', $recordData);
            
            // Create log
            $employee = new Employee($data['employee_id']);
            
            $this->db->insert('attendance_logs', [
                'device_ip' => 'MANUAL',
                'user_id' => $employee->user_id,
                'scan_time' => $data['check_in_time'],
                'inout_status' => 1,
                'raw' => json_encode(['manual_entry' => true]),
                'processed' => 1,
                'attendance_id' => $recordId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!empty($data['check_out_time'])) {
                $this->db->insert('attendance_logs', [
                    'device_ip' => 'MANUAL',
                    'user_id' => $employee->user_id,
                    'scan_time' => $data['check_out_time'],
                    'inout_status' => 2,
                    'raw' => json_encode(['manual_entry' => true]),
                    'processed' => 1,
                    'attendance_id' => $recordId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            $this->db->commit();
            
            self::processRecord($recordId);
            
            return $recordId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Update attendance record
     */
    public function updateRecord($data) {
        if (!$this->id) {
            throw new Exception("No attendance record loaded");
        }
        
        $allowedFields = ['check_in_time', 'check_out_time', 'shift_id', 'notes'];
        
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
        
        $result = $this->db->update('attendance_records', $updateData, 'id = ?', [$this->id]);
        
        if ($result) {
            self::processRecord($this->id);
        }
        
        return $result;
    }
    
    /**
     * Get record data
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
}