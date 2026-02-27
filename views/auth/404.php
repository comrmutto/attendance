<?php
/**
 * 404 Not Found Page
 * แสดงเมื่อไม่พบหน้าที่ต้องการ
 */
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - ไม่พบหน้าที่ต้องการ</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }
        
        /* Animated background */
        .bg-bubbles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        
        .bg-bubbles li {
            position: absolute;
            list-style: none;
            display: block;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            bottom: -160px;
            animation: square 25s infinite;
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
            left: 70%;
            width: 100px;
            height: 100px;
            animation-delay: 3s;
            animation-duration: 11s;
        }
        
        .bg-bubbles li:nth-child(6) {
            left: 80%;
            width: 140px;
            height: 140px;
            animation-delay: 1s;
            animation-duration: 19s;
        }
        
        .bg-bubbles li:nth-child(7) {
            left: 32%;
            width: 180px;
            height: 180px;
            animation-delay: 7s;
            animation-duration: 23s;
        }
        
        .bg-bubbles li:nth-child(8) {
            left: 55%;
            width: 70px;
            height: 70px;
            animation-delay: 15s;
            animation-duration: 30s;
        }
        
        .bg-bubbles li:nth-child(9) {
            left: 15%;
            width: 90px;
            height: 90px;
            animation-delay: 2s;
            animation-duration: 25s;
        }
        
        .bg-bubbles li:nth-child(10) {
            left: 90%;
            width: 110px;
            height: 110px;
            animation-delay: 11s;
            animation-duration: 21s;
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
        
        .error-container {
            position: relative;
            z-index: 2;
            text-align: center;
            background: rgba(255, 255, 255, 0.95);
            padding: 60px 50px;
            border-radius: 30px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            backdrop-filter: blur(10px);
            animation: fadeInUp 0.8s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .error-code {
            font-size: 160px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 5px 5px 0 rgba(102,126,234,0.2);
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        .error-title {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }
        
        .error-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        
        .error-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .error-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
            border-left: 4px solid #667eea;
        }
        
        .error-details p {
            margin-bottom: 8px;
            color: #555;
            font-size: 15px;
        }
        
        .error-details i {
            width: 25px;
            color: #667eea;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        
        .btn-home {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 35px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }
        
        .btn-home:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102,126,234,0.4);
            color: white;
        }
        
        .btn-home i {
            font-size: 18px;
        }
        
        .btn-back {
            background: white;
            color: #667eea;
            padding: 14px 35px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s;
            border: 2px solid #667eea;
            cursor: pointer;
        }
        
        .btn-back:hover {
            background: #f8f9fa;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.2);
        }
        
        .search-box {
            margin-top: 20px;
            position: relative;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .search-box input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 5px 20px rgba(102,126,234,0.2);
        }
        
        .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
        }
        
        .help-links {
            margin-top: 30px;
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .help-links a {
            color: #667eea;
            text-decoration: none;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s;
        }
        
        .help-links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .fun-fact {
            margin-top: 25px;
            padding: 15px;
            background: #e8f5e9;
            border-radius: 10px;
            color: #2e7d32;
            font-size: 14px;
            border-left: 4px solid #4caf50;
            text-align: left;
        }
        
        .fun-fact i {
            margin-right: 10px;
            color: #4caf50;
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .error-container {
                background: rgba(33, 37, 41, 0.95);
            }
            
            .error-title {
                color: #fff;
            }
            
            .error-message {
                color: #aaa;
            }
            
            .error-details {
                background: #2d3238;
            }
            
            .error-details p {
                color: #ccc;
            }
            
            .btn-back {
                background: transparent;
                color: #fff;
                border-color: #fff;
            }
            
            .btn-back:hover {
                background: #2d3238;
            }
            
            .search-box input {
                background: #2d3238;
                border-color: #444;
                color: #fff;
            }
            
            .search-box input::placeholder {
                color: #888;
            }
            
            .fun-fact {
                background: #1e3a2a;
                color: #a5d6a7;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .error-code {
                font-size: 120px;
            }
            
            .error-title {
                font-size: 28px;
            }
            
            .error-container {
                padding: 40px 25px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-home, .btn-back {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .error-code {
                font-size: 100px;
            }
            
            .error-title {
                font-size: 24px;
            }
            
            .error-message {
                font-size: 16px;
            }
            
            .help-links {
                flex-direction: column;
                gap: 10px;
                align-items: center;
            }
        }
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
    
    <div class="error-container">
        <div class="error-code">404</div>
        <h1 class="error-title">ไม่พบหน้าที่ต้องการ</h1>
        
        <div class="error-message">
            ขออภัย หน้าที่คุณกำลังค้นหาไม่มีอยู่ในระบบ<br>
            หรืออาจถูกลบ ย้าย หรือเปลี่ยนชื่อไปแล้ว
        </div>
        
        <div class="error-details">
            <p><i class="fas fa-link"></i> <strong>URL ที่เรียก:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''); ?></p>
            <p><i class="fas fa-clock"></i> <strong>เวลา:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            <p><i class="fas fa-globe"></i> <strong>IP Address:</strong> <?php echo $_SERVER['REMOTE_ADDR'] ?? ''; ?></p>
        </div>
        
        <div class="action-buttons">
            <a href="/dashboard" class="btn-home">
                <i class="fas fa-home"></i>
                กลับสู่หน้าหลัก
            </a>
            <a href="javascript:history.back()" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                ย้อนกลับ
            </a>
        </div>
        
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="ค้นหาหน้าที่ต้องการ...">
        </div>
        
        <div class="help-links">
            <a href="/dashboard"><i class="fas fa-chart-bar"></i> แดชบอร์ด</a>
            <a href="/attendance"><i class="fas fa-clock"></i> บันทึกเวลา</a>
            <a href="/employees"><i class="fas fa-users"></i> พนักงาน</a>
            <a href="/contact-support"><i class="fas fa-headset"></i> ติดต่อผู้ดูแล</a>
        </div>
        
        <div class="fun-fact">
            <i class="fas fa-lightbulb"></i>
            <strong>รู้หรือไม่?</strong> 
            <?php
            $facts = [
                'หน้านี้อาจถูกย้ายไปยังหมวดหมู่อื่น',
                'คุณสามารถใช้เมนูด้านข้างเพื่อนำทาง',
                'หากต้องการความช่วยเหลือ ติดต่อ IT Support',
                'ระบบบันทึกทุกการเข้าใช้งานเพื่อความปลอดภัย',
                'เรามีคู่มือการใช้งานระบบให้ดาวน์โหลด'
            ];
            echo $facts[array_rand($facts)];
            ?>
        </div>
    </div>
    
    <!-- JavaScript for search functionality -->
    <script>
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    window.location.href = '/search?q=' + encodeURIComponent(searchTerm);
                }
            }
        });
        
        // Redirect countdown (optional)
        let seconds = 10;
        const countdownElement = document.createElement('div');
        countdownElement.style.marginTop = '15px';
        countdownElement.style.fontSize = '14px';
        countdownElement.style.color = '#999';
        
        function updateCountdown() {
            if (seconds > 0) {
                countdownElement.innerHTML = `จะกลับสู่หน้าหลักอัตโนมัติใน <strong>${seconds}</strong> วินาที`;
                seconds--;
                setTimeout(updateCountdown, 1000);
            } else {
                window.location.href = '/dashboard';
            }
        }
        
        // Uncomment to enable auto-redirect
        // document.querySelector('.error-container').appendChild(countdownElement);
        // updateCountdown();
        
        // Track 404 error for analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', '404_error', {
                'page_path': window.location.pathname,
                'page_title': document.title
            });
        }
        
        // Console message for developers
        console.log(
            '%c404 Error: Page not found\n%cURL: ' + window.location.pathname,
            'color: #dc3545; font-size: 16px; font-weight: bold;',
            'color: #666; font-size: 14px;'
        );
    </script>
    
    <!-- Bootstrap JS (optional, for any interactive elements) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>