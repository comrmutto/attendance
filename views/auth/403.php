<?php
/**
 * 403 Forbidden Page
 */
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - ไม่อนุญาตให้เข้าถึง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Sarabun', sans-serif;
        }
        .error-container {
            text-align: center;
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            margin: 20px;
        }
        .error-code {
            font-size: 120px;
            font-weight: 700;
            color: #dc3545;
            line-height: 1;
            margin-bottom: 20px;
            text-shadow: 5px 5px 0 rgba(220,53,69,0.2);
        }
        .error-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 15px;
        }
        .error-message {
            color: #666;
            margin-bottom: 30px;
        }
        .btn-home {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.3s;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        .btn-back {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">403</div>
        <div class="error-title">⚠️ ไม่อนุญาตให้เข้าถึง</div>
        <div class="error-message">
            คุณไม่มีสิทธิ์เข้าถึงหน้านี้<br>
            หากคิดว่านี่คือข้อผิดพลาด กรุณาติดต่อผู้ดูแลระบบ
        </div>
        <div>
            <a href="/dashboard" class="btn-home">กลับสู่หน้าหลัก</a>
            <a href="javascript:history.back()" class="btn-back">ย้อนกลับ</a>
        </div>
    </div>
</body>
</html>