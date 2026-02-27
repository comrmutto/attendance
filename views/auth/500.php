<?php
/**
 * 500 Internal Server Error Page
 * แสดงเมื่อเกิดข้อผิดพลาดภายในเซิร์ฟเวอร์
 */
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - เกิดข้อผิดพลาดภายในระบบ</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .error-container {
            text-align: center;
            background: white;
            padding: 60px 50px;
            border-radius: 30px;
            box-shadow: 0 30px 60px rgba(220,53,69,0.15);
            max-width: 600px;
            width: 100%;
            border-top: 5px solid #dc3545;
        }
        
        .error-code {
            font-size: 140px;
            font-weight: 800;
            color: #dc3545;
            line-height: 1;
            margin-bottom: 20px;
            text-shadow: 5px 5px 0 rgba(220,53,69,0.2);
        }
        
        .error-title {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }
        
        .error-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
        }
        
        .error-icon {
            font-size: 80px;
            color: #dc3545;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
        }
        
        .btn-home {
            background: #dc3545;
            color: white;
            padding: 14px 40px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s;
            border: none;
            box-shadow: 0 10px 20px rgba(220,53,69,0.3);
        }
        
        .btn-home:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(220,53,69,0.4);
            color: white;
        }
        
        .error-ref {
            margin-top: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 13px;
            color: #999;
            border-left: 4px solid #dc3545;
            text-align: left;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="error-code">500</div>
        <h1 class="error-title">เกิดข้อผิดพลาดภายในระบบ</h1>
        <div class="error-message">
            ขออภัย ระบบเกิดข้อผิดพลาดทางเทคนิค<br>
            กรุณาลองใหม่อีกครั้งในภายหลัง
        </div>
        
        <a href="/dashboard" class="btn-home">
            <i class="fas fa-home me-2"></i>
            กลับสู่หน้าหลัก
        </a>
        
        <div class="error-ref">
            <i class="fas fa-code"></i> 
            <strong>Reference ID:</strong> <?php echo uniqid('ERR_'); ?><br>
            <i class="fas fa-clock"></i> 
            <strong>เวลา:</strong> <?php echo date('d/m/Y H:i:s'); ?><br>
            <i class="fas fa-bug"></i> 
            <small>หากปัญหายังคงอยู่ กรุณาติดต่อผู้ดูแลระบบ พร้อมแจ้ง Reference ID นี้</small>
        </div>
    </div>
</body>
</html>