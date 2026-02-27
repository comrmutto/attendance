<?php
/**
 * User.php - Model สำหรับผู้ใช้
 */

require_once CLASSES_PATH . 'Database.php';

class User {
    
    /**
     * ค้นหาผู้ใช้ตาม ID
     */
    public static function find($id) {
        $db = Database::getInstance();
        
        $sql = "SELECT u.*, e.first_name, e.last_name, e.department_id, e.position_id, p.level 
                FROM users u 
                LEFT JOIN employees e ON u.employee_id = e.id
                LEFT JOIN positions p ON e.position_id = p.id
                WHERE u.id = ? AND u.status = 1";
        
        return $db->fetchOne($sql, [$id]);
    }
    
    /**
     * ค้นหาผู้ใช้ตาม username
     */
    public static function findByUsername($username) {
        $db = Database::getInstance();
        
        $sql = "SELECT u.*, e.first_name, e.last_name 
                FROM users u 
                LEFT JOIN employees e ON u.employee_id = e.id
                WHERE u.username = ? AND u.status = 1";
        
        return $db->fetchOne($sql, [$username]);
    }
    
    /**
     * อัปเดตข้อมูลผู้ใช้
     */
    public static function update($id, $data) {
        $db = Database::getInstance();
        
        return $db->update('users', $data, 'id = ?', [$id]);
    }
    
    /**
     * เปลี่ยนรหัสผ่าน
     */
    public static function changePassword($id, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        return self::update($id, ['password' => $hashedPassword]);
    }
    
    /**
     * ตรวจสอบรหัสผ่าน
     */
    public static function verifyPassword($id, $password) {
        $db = Database::getInstance();
        
        $sql = "SELECT password FROM users WHERE id = ?";
        $result = $db->fetchOne($sql, [$id]);
        
        if ($result && password_verify($password, $result['password'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * ดึงสิทธิ์การใช้งาน
     */
    public static function getPermissions($id) {
        $db = Database::getInstance();
        
        $sql = "SELECT u.role, p.level 
                FROM users u 
                LEFT JOIN employees e ON u.employee_id = e.id
                LEFT JOIN positions p ON e.position_id = p.id
                WHERE u.id = ?";
        
        return $db->fetchOne($sql, [$id]);
    }
}