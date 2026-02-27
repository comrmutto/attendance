<?php
/**
 * reset-password.php - หน้ารีเซ็ตรหัสผ่าน
 */
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รีเซ็ตรหัสผ่าน - ระบบบันทึกเวลา</title>
    
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
        }
        
        .reset-container {
            max-width: 400px;
            width: 100%;
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
        
        .reset-card {
            background: white;
            border-radius: 20px;
            padding: 40px 35px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .header-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 35px;
        }
        
        h2 {
            text-align: center;
            color: #333;
            font-weight: 600;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .password-strength {
            margin-top: 8px;
        }
        
        .strength-bar {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .strength-progress {
            height: 100%;
            width: 0;
            transition: width 0.3s, background-color 0.3s;
        }
        
        .strength-text {
            font-size: 12px;
            margin-top: 5px;
            color: #666;
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .requirements {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            font-size: 13px;
        }
        
        .requirements h4 {
            color: #333;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .requirements li {
            color: #666;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .requirements li i {
            width: 16px;
            color: #999;
        }
        
        .requirements li.valid i {
            color: #2e7d32;
        }
        
        .requirements li.invalid i {
            color: #c33;
        }
        
        .alert {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert i {
            font-size: 18px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="header-icon">
                <i class="fas fa-lock"></i>
            </div>
            
            <h2>ตั้งรหัสผ่านใหม่</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="/attendance_system/reset-password" id="resetForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="password">รหัสผ่านใหม่</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="รหัสผ่านใหม่" 
                               required>
                        <span class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar">
                            <div class="strength-progress" id="strengthProgress"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">ยืนยันรหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control" 
                               id="confirm_password" 
                               name="confirm_password" 
                               placeholder="ยืนยันรหัสผ่าน" 
                               required>
                    </div>
                </div>
                
                <div class="requirements">
                    <h4>รหัสผ่านต้องประกอบด้วย:</h4>
                    <ul>
                        <li id="req-length">
                            <i class="fas fa-circle"></i>
                            อย่างน้อย 8 ตัวอักษร
                        </li>
                        <li id="req-uppercase">
                            <i class="fas fa-circle"></i>
                            ตัวพิมพ์ใหญ่ 1 ตัว
                        </li>
                        <li id="req-lowercase">
                            <i class="fas fa-circle"></i>
                            ตัวพิมพ์เล็ก 1 ตัว
                        </li>
                        <li id="req-number">
                            <i class="fas fa-circle"></i>
                            ตัวเลข 1 ตัว
                        </li>
                        <li id="req-special">
                            <i class="fas fa-circle"></i>
                            อักขระพิเศษ 1 ตัว (!@#$%^&*)
                        </li>
                        <li id="req-match">
                            <i class="fas fa-circle"></i>
                            รหัสผ่านตรงกัน
                        </li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn" disabled>
                    ตั้งรหัสผ่านใหม่
                </button>
            </form>
        </div>
    </div>
    
    <script>
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const togglePassword = document.getElementById('togglePassword');
        const submitBtn = document.getElementById('submitBtn');
        const strengthProgress = document.getElementById('strengthProgress');
        const strengthText = document.getElementById('strengthText');
        
        // Requirements elements
        const reqLength = document.getElementById('req-length');
        const reqUppercase = document.getElementById('req-uppercase');
        const reqLowercase = document.getElementById('req-lowercase');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');
        const reqMatch = document.getElementById('req-match');
        
        // Toggle password visibility
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
        
        // Check password strength
        function checkPasswordStrength() {
            const value = password.value;
            
            // Check requirements
            const hasLength = value.length >= 8;
            const hasUppercase = /[A-Z]/.test(value);
            const hasLowercase = /[a-z]/.test(value);
            const hasNumber = /[0-9]/.test(value);
            const hasSpecial = /[!@#$%^&*]/.test(value);
            const hasMatch = value === confirmPassword.value && value.length > 0;
            
            // Update requirement icons
            updateRequirement(reqLength, hasLength);
            updateRequirement(reqUppercase, hasUppercase);
            updateRequirement(reqLowercase, hasLowercase);
            updateRequirement(reqNumber, hasNumber);
            updateRequirement(reqSpecial, hasSpecial);
            updateRequirement(reqMatch, hasMatch);
            
            // Calculate strength
            let strength = 0;
            if (hasLength) strength += 1;
            if (hasUppercase) strength += 1;
            if (hasLowercase) strength += 1;
            if (hasNumber) strength += 1;
            if (hasSpecial) strength += 1;
            
            // Update progress bar
            const percentage = (strength / 5) * 100;
            strengthProgress.style.width = percentage + '%';
            
            // Update color and text
            if (strength <= 2) {
                strengthProgress.style.backgroundColor = '#dc3545';
                strengthText.textContent = 'ความปลอดภัยต่ำ';
                strengthText.style.color = '#dc3545';
            } else if (strength <= 4) {
                strengthProgress.style.backgroundColor = '#ffc107';
                strengthText.textContent = 'ความปลอดภัยปานกลาง';
                strengthText.style.color = '#ffc107';
            } else {
                strengthProgress.style.backgroundColor = '#28a745';
                strengthText.textContent = 'ความปลอดภัยสูง';
                strengthText.style.color = '#28a745';
            }
            
            // Enable/disable submit button
            const allValid = hasLength && hasUppercase && hasLowercase && 
                           hasNumber && hasSpecial && hasMatch;
            submitBtn.disabled = !allValid;
        }
        
        function updateRequirement(element, isValid) {
            const icon = element.querySelector('i');
            if (isValid) {
                icon.className = 'fas fa-check-circle';
                element.classList.add('valid');
                element.classList.remove('invalid');
            } else {
                icon.className = 'fas fa-circle';
                element.classList.remove('valid');
                element.classList.remove('invalid');
            }
        }
        
        password.addEventListener('input', checkPasswordStrength);
        confirmPassword.addEventListener('input', checkPasswordStrength);
        
        // Form submission
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            if (submitBtn.disabled) {
                e.preventDefault();
                alert('กรุณาตั้งรหัสผ่านให้ตรงตามข้อกำหนด');
            }
        });
    </script>
</body>
</html>