<?php
/**
 * AuthController.php - Controller สำหรับจัดการการเข้าสู่ระบบ
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/User.php';

class AuthController {
    
    /**
     * แสดงหน้า login
     */
public function loginForm() {
    if (Auth::check()) {
        redirect('/dashboard');
    }
    
    // ← เพิ่มบรรทัดนี้ เพื่อ generate CSRF token ก่อน render
    Session::getCsrfToken();
    
    $this->view('auth/login');
}
    
    /**
     * ตรวจสอบการ login
     */
    public function login() {
        // ตรวจสอบ CSRF token
        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Invalid request');
            redirect('/login');
        }
        
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // ตรวจสอบข้อมูล
        if (empty($username) || empty($password)) {
            Session::flash('error', 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
            redirect('/login');
        }
        
        // ตรวจสอบการ login
        if (Auth::attempt($username, $password, $remember)) {
            // บันทึกประวัติการ login
            $this->logLogin($_SESSION['user_id'], true);
            
            // ไปยังหน้าที่ต้องการหรือหน้าหลัก
            $redirect = $_SESSION['redirect_after_login'] ?? '/dashboard';
            unset($_SESSION['redirect_after_login']);
            
            redirect($redirect);
        } else {
            // บันทึกประวัติการ login ที่ล้มเหลว
            $this->logLogin(null, false, $username);
            
            Session::flash('error', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
            redirect('/login');
        }
    }
    
    /**
     * logout
     */
    public function logout() {
        Auth::logout();
        Session::flash('success', 'ออกจากระบบเรียบร้อย');
        redirect('/login');
    }
    
    /**
     * แสดงฟอร์มขอรีเซ็ตรหัสผ่าน
     */
    public function forgotPasswordForm() {
        $this->view('auth/forgot-password');
    }
    
    /**
     * ส่งอีเมลรีเซ็ตรหัสผ่าน
     */
    public function forgotPassword() {
        $email = $_POST['email'] ?? '';
        
        if (empty($email)) {
            Session::flash('error', 'กรุณากรอกอีเมล');
            redirect('/forgot-password');
        }
        
        $db = Database::getInstance();
        
        // ค้นหาผู้ใช้จากอีเมล
        $user = $db->fetchOne(
            "SELECT u.*, e.email FROM users u 
             JOIN employees e ON u.employee_id = e.id 
             WHERE e.email = ?",
            [$email]
        );
        
        if ($user) {
            // สร้าง token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // บันทึก token
            $db->insert('password_resets', [
                'user_id' => $user['id'],
                'token' => password_hash($token, PASSWORD_DEFAULT),
                'expires_at' => $expires,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // ส่งอีเมล (จำลอง)
            $resetLink = APP_URL . "/reset-password?token=" . $token . "&email=" . urlencode($email);
            
            // TODO: ส่งอีเมลจริง
            error_log("Password reset link: " . $resetLink);
        }
        
        // แสดงข้อความสำเร็จเสมอ (ป้องกันการค้นหาอีเมล)
        Session::flash('success', 'ระบบได้ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว');
        redirect('/login');
    }
    
    /**
     * แสดงฟอร์มรีเซ็ตรหัสผ่าน
     */
    public function resetPasswordForm() {
        $token = $_GET['token'] ?? '';
        $email = $_GET['email'] ?? '';
        
        if (empty($token) || empty($email)) {
            Session::flash('error', 'ลิงก์ไม่ถูกต้อง');
            redirect('/login');
        }
        
        // ตรวจสอบ token
        $db = Database::getInstance();
        
        $reset = $db->fetchOne(
            "SELECT pr.*, u.id as user_id, e.email 
             FROM password_resets pr
             JOIN users u ON pr.user_id = u.id
             JOIN employees e ON u.employee_id = e.id
             WHERE e.email = ? AND pr.expires_at > NOW()
             ORDER BY pr.created_at DESC LIMIT 1",
            [$email]
        );
        
        if (!$reset || !password_verify($token, $reset['token'])) {
            Session::flash('error', 'ลิงก์ไม่ถูกต้องหรือหมดอายุ');
            redirect('/login');
        }
        
        $_SESSION['reset_user_id'] = $reset['user_id'];
        $_SESSION['reset_email'] = $email;
        
        $this->view('auth/reset-password');
    }
    
    /**
     * บันทึกรหัสผ่านใหม่
     */
    public function resetPassword() {
        if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_email'])) {
            Session::flash('error', 'กรุณาทำรายการใหม่อีกครั้ง');
            redirect('/login');
        }
        
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (strlen($password) < 8) {
            Session::flash('error', 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');
            redirect('/reset-password');
        }
        
        if ($password !== $confirm) {
            Session::flash('error', 'รหัสผ่านไม่ตรงกัน');
            redirect('/reset-password');
        }
        
        $db = Database::getInstance();
        
        // อัปเดตรหัสผ่าน
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $db->update('users', 
            ['password' => $hashedPassword, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?', 
            [$_SESSION['reset_user_id']]
        );
        
        // ลบ token ที่ใช้แล้ว
        $db->delete('password_resets', 'user_id = ?', [$_SESSION['reset_user_id']]);
        
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_email']);
        
        Session::flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อย กรุณาเข้าสู่ระบบ');
        redirect('/login');
    }
    
    /**
     * เปลี่ยนรหัสผ่าน (สำหรับผู้ใช้ที่ login แล้ว)
     */
    public function changePassword() {
        requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->view('auth/change-password');
            return;
        }
        
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        // ตรวจสอบ
        if (empty($current) || empty($new) || empty($confirm)) {
            Session::flash('error', 'กรุณากรอกข้อมูลให้ครบถ้วน');
            redirect('/change-password');
        }
        
        if (strlen($new) < 8) {
            Session::flash('error', 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร');
            redirect('/change-password');
        }
        
        if ($new !== $confirm) {
            Session::flash('error', 'รหัสผ่านใหม่ไม่ตรงกัน');
            redirect('/change-password');
        }
        
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        // ตรวจสอบรหัสผ่านปัจจุบัน
        $user = $db->fetchOne("SELECT password FROM users WHERE id = ?", [$userId]);
        
        if (!password_verify($current, $user['password'])) {
            Session::flash('error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
            redirect('/change-password');
        }
        
        // อัปเดตรหัสผ่าน
        $hashedPassword = password_hash($new, PASSWORD_DEFAULT);
        $db->update('users', 
            ['password' => $hashedPassword, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?', 
            [$userId]
        );
        
        Session::flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อย');
        redirect('/profile');
    }
    
    /**
     * บันทึกประวัติการ login
     */
    private function logLogin($userId, $success, $username = null) {
        $db = Database::getInstance();
        
        $db->insert('login_logs', [
            'user_id' => $userId,
            'username' => $username,
            'success' => $success ? 1 : 0,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * แสดง view
     */
    private function view($view, $data = []) {
        extract($data);
        require VIEWS_PATH . $view . '.php';
    }
}