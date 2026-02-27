<?php
/**
 * login.php - หน้าเข้าสู่ระบบ
 */
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบบันทึกเวลา</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background */
        .bg-bubbles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            overflow: hidden;
        }
        
        .bg-bubbles li {
            position: absolute;
            list-style: none;
            display: block;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.15);
            bottom: -160px;
            animation: square 20s infinite;
            transition-timing-function: linear;
            border-radius: 50%;
        }
        
        .bg-bubbles li:nth-child(1) {
            left: 10%;
            width: 80px;
            height: 80px;
            animation-delay: 0s;
            animation-duration: 12s;
        }
        
        .bg-bubbles li:nth-child(2) {
            left: 20%;
            width: 120px;
            height: 120px;
            animation-delay: 2s;
            animation-duration: 17s;
        }
        
        .bg-bubbles li:nth-child(3) {
            left: 25%;
            width: 160px;
            height: 160px;
            animation-delay: 4s;
            animation-duration: 13s;
        }
        
        .bg-bubbles li:nth-child(4) {
            left: 40%;
            width: 60px;
            height: 60px;
            animation-delay: 0s;
            animation-duration: 15s;
        }
        
        .bg-bubbles li:nth-child(5) {
            left: 55%;
            width: 100px;
            height: 100px;
            animation-delay: 3s;
            animation-duration: 18s;
        }
        
        .bg-bubbles li:nth-child(6) {
            left: 70%;
            width: 140px;
            height: 140px;
            animation-delay: 7s;
            animation-duration: 20s;
        }
        
        .bg-bubbles li:nth-child(7) {
            left: 80%;
            width: 180px;
            height: 180px;
            animation-delay: 1s;
            animation-duration: 25s;
        }
        
        .bg-bubbles li:nth-child(8) {
            left: 90%;
            width: 90px;
            height: 90px;
            animation-delay: 5s;
            animation-duration: 22s;
        }
        
        .bg-bubbles li:nth-child(9) {
            left: 15%;
            width: 70px;
            height: 70px;
            animation-delay: 9s;
            animation-duration: 19s;
        }
        
        .bg-bubbles li:nth-child(10) {
            left: 65%;
            width: 110px;
            height: 110px;
            animation-delay: 11s;
            animation-duration: 24s;
        }
        
        @keyframes square {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.5;
            }
            100% {
                transform: translateY(-1200px) rotate(720deg);
                opacity: 0;
            }
        }
        
        /* Login Container */
        .login-container {
            max-width: 420px;
            width: 100%;
            z-index: 2;
            position: relative;
            animation: fadeInUp 0.8s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 35px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .logo-wrapper {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-circle {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .logo-circle i {
            font-size: 50px;
            color: white;
        }
        
        .logo-text h2 {
            color: #333;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 28px;
        }
        
        .logo-text p {
            color: #666;
            font-size: 15px;
        }
        
        /* Alert Messages */
        .alert {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .alert-danger {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .alert-warning {
            background: #fff3e0;
            color: #f57c00;
            border-left: 4px solid #f57c00;
        }
        
        .alert-info {
            background: #e3f2fd;
            color: #1976d2;
            border-left: 4px solid #1976d2;
        }
        
        /* Form */
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 15px;
        }
        
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            color: #999;
            font-size: 18px;
            z-index: 1;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.3s;
            background: white;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .form-control::placeholder {
            color: #aaa;
            font-size: 14px;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            color: #999;
            cursor: pointer;
            font-size: 18px;
            transition: color 0.3s;
            z-index: 1;
        }
        
        .password-toggle:hover {
            color: #667eea;
        }
        
        /* Form Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }
        
        .remember-me label {
            color: #666;
            font-size: 15px;
            cursor: pointer;
            user-select: none;
        }
        
        .forgot-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .forgot-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 18px;
            font-weight: 600;
            font-family: 'Sarabun', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-login:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login i {
            font-size: 18px;
            transition: transform 0.3s;
        }
        
        .btn-login:hover i {
            transform: translateX(5px);
        }
        
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .btn-login.loading .btn-text {
            opacity: 0;
        }
        
        .btn-login.loading .spinner-border {
            display: inline-block !important;
        }
        
        .spinner-border {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner 0.75s linear infinite;
            position: absolute;
        }
        
        @keyframes spinner {
            to { transform: rotate(360deg); }
        }
        
        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 30px;
            color: #999;
            font-size: 13px;
        }
        
        .login-footer p {
            margin-bottom: 5px;
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }
        
        .loading-overlay.show {
            display: flex;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            .logo-circle {
                width: 80px;
                height: 80px;
            }
            
            .logo-circle i {
                font-size: 40px;
            }
            
            .logo-text h2 {
                font-size: 24px;
            }
            
            .form-options {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
        
        /* Demo credentials (for development) */
        <?php if (APP_ENV === 'development'): ?>
        .demo-credentials {
            background: #f0f4f8;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            border: 1px dashed #667eea;
        }
        
        .demo-credentials h4 {
            color: #333;
            font-size: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .demo-credentials h4 i {
            color: #667eea;
        }
        
        .demo-credentials .cred-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 14px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .demo-credentials .cred-item:last-child {
            border-bottom: none;
        }
        
        .demo-credentials .cred-label {
            color: #666;
            font-weight: 500;
        }
        
        .demo-credentials .cred-value {
            color: #667eea;
            font-weight: 600;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <!-- Animated background bubbles -->
    <ul class="bg-bubbles">
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
    </ul>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <!-- Login Container -->
    <div class="login-container">
        <div class="login-card">
            <!-- Logo -->
            <div class="logo-wrapper">
                <div class="logo-circle">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="logo-text">
                    <h2><?php echo APP_NAME; ?></h2>
                    <p>ระบบบันทึกเวลาเข้าออกงาน</p>
                </div>
            </div>
            
            <!-- Alert Messages -->
           <?php
if (isset($_SESSION['flash'])) {
    foreach ($_SESSION['flash'] as $key => $flash) {
        // รองรับทั้ง string และ array
        if (is_array($flash)) {
            $type    = $flash['type'] ?? 'info';
            $message = $flash['message'] ?? '';
        } else {
            $type    = $key; // key คือ 'error', 'success', etc.
            $message = $flash;
        }
        $icon = $type == 'success' ? 'fa-check-circle' : ($type == 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
        echo '<div class="alert alert-' . $type . '">';
        echo '<i class="fas ' . $icon . '"></i> ' . $message;
        echo '</div>';
    }
    unset($_SESSION['flash']);
}
?>
            
            <!-- Demo Credentials (เฉพาะ development) -->
            <?php if (APP_ENV === 'development'): ?>
            <div class="demo-credentials">
                <h4>
                    <i class="fas fa-code"></i>
                    บัญชีทดสอบ
                </h4>
                <div class="cred-item">
                    <span class="cred-label">ผู้ดูแลระบบ:</span>
                    <span class="cred-value">admin / admin123</span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">ผู้จัดการ:</span>
                    <span class="cred-value">manager / manager123</span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">พนักงาน:</span>
                    <span class="cred-value">employee / employee123</span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" action="<?php echo APP_URL; ?>/login" id="loginForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo Session::getCsrfToken(); ?>">
                
                <!-- Username -->
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        ชื่อผู้ใช้
                    </label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="กรุณากรอกชื่อผู้ใช้" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               required 
                               autofocus>
                    </div>
                </div>
                
                <!-- Password -->
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        รหัสผ่าน
                    </label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="กรุณากรอกรหัสผ่าน" 
                               required>
                        <span class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <!-- Form Options -->
                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" name="remember" id="remember" value="1">
                        <label for="remember">จดจำฉันไว้</label>
                    </div>
                    <a href="<?php echo APP_URL; ?>/forgot-password" class="forgot-link">
                        ลืมรหัสผ่าน?
                    </a>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-text">เข้าสู่ระบบ</span>
                    <span class="spinner-border" role="status"></span>
                </button>
            </form>
            
            <!-- Footer -->
            <div class="login-footer">
                <p>พัฒนาโดย IT Department</p>
                <p>เวอร์ชัน <?php echo APP_VERSION; ?></p>
                <p>
                    <i class="fas fa-copyright"></i>
                    <?php echo date('Y'); ?> <?php echo APP_NAME; ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // DOM Elements
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const loadingOverlay = document.getElementById('loadingOverlay');
        
        // Toggle password visibility
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Toggle icon
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
        
        // Form submission
        loginForm.addEventListener('submit', function(e) {
            // Validate form
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                showToast('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', 'warning');
                return;
            }
            
            // Show loading
            loginBtn.classList.add('loading');
            loadingOverlay.classList.add('show');
            
            // Disable button to prevent double submission
            loginBtn.disabled = true;
        });
        
        // Show toast notification
        function showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `alert alert-${type}`;
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.zIndex = '9999';
            toast.style.minWidth = '300px';
            toast.style.animation = 'slideIn 0.3s ease';
            
            // Add icon
            const icon = document.createElement('i');
            icon.className = `fas ${
                type === 'success' ? 'fa-check-circle' :
                type === 'error' ? 'fa-exclamation-circle' :
                type === 'warning' ? 'fa-exclamation-triangle' :
                'fa-info-circle'
            }`;
            toast.appendChild(icon);
            
            // Add message
            toast.appendChild(document.createTextNode(' ' + message));
            
            // Add to body
            document.body.appendChild(toast);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                if (!alert.classList.contains('demo-credentials')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }
            });
        }, 5000);
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Add keyboard shortcut (Ctrl+Enter to submit)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                loginForm.submit();
            }
        });
        
        // Focus on username field
        window.addEventListener('load', function() {
            document.getElementById('username').focus();
        });
        
        // Add demo credentials on double click (for development)
        <?php if (APP_ENV === 'development'): ?>
        let clickCount = 0;
        document.addEventListener('dblclick', function(e) {
            clickCount++;
            if (clickCount === 3) {
                const demoAccounts = [
                    { username: 'admin', password: 'admin123' },
                    { username: 'manager', password: 'manager123' },
                    { username: 'employee', password: 'employee123' }
                ];
                
                const randomAccount = demoAccounts[Math.floor(Math.random() * demoAccounts.length)];
                document.getElementById('username').value = randomAccount.username;
                document.getElementById('password').value = randomAccount.password;
                
                showToast('เติมข้อมูลทดสอบอัตโนมัติ', 'info');
                clickCount = 0;
            }
            
            setTimeout(() => {
                clickCount = 0;
            }, 1000);
        });
        <?php endif; ?>
    </script>
</body>
</html>