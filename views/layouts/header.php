<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - <?php echo $title ?? 'ระบบบันทึกเวลา'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Moment.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/th.js"></script>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --info-color: #0dcaf0;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --dark-color: #212529;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f4f6f9;
            overflow-x: hidden;
        }
        
        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            transition: all 0.3s;
            min-height: 100vh;
            background-color: #f4f6f9;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Loading Spinner */
        .spinner-wrapper {
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
        }
        
        .spinner-wrapper.show {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast-notification {
            min-width: 300px;
            margin-bottom: 10px;
            padding: 15px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border-left: 4px solid;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .toast-notification.show {
            transform: translateX(0);
        }
        
        .toast-notification.success {
            border-left-color: var(--success-color);
        }
        
        .toast-notification.error {
            border-left-color: var(--danger-color);
        }
        
        .toast-notification.warning {
            border-left-color: var(--warning-color);
        }
        
        .toast-notification.info {
            border-left-color: var(--info-color);
        }
        
        /* Badges */
        .badge-status {
            padding: 5px 10px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .badge-status.present {
            background: rgba(25, 135, 84, 0.15);
            color: var(--success-color);
        }
        
        .badge-status.absent {
            background: rgba(220, 53, 69, 0.15);
            color: var(--danger-color);
        }
        
        .badge-status.late {
            background: rgba(255, 193, 7, 0.15);
            color: var(--warning-color);
        }
        
        .badge-status.leave {
            background: rgba(108, 117, 125, 0.15);
            color: var(--secondary-color);
        }
        
        .badge-status.overtime {
            background: rgba(13, 110, 253, 0.15);
            color: var(--primary-color);
        }
        
        /* Cards */
        .stat-card {
            padding: 20px;
            border-radius: 10px;
            color: white;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card .stat-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 3rem;
            opacity: 0.3;
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .stat-card.primary { background: linear-gradient(45deg, var(--primary-color), #0b5ed7); }
        .stat-card.success { background: linear-gradient(45deg, var(--success-color), #157347); }
        .stat-card.warning { background: linear-gradient(45deg, var(--warning-color), #ffca2c); color: var(--dark-color); }
        .stat-card.danger { background: linear-gradient(45deg, var(--danger-color), #bb2d3b); }
        .stat-card.info { background: linear-gradient(45deg, var(--info-color), #0aa2c0); }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .table thead th {
            border-top: none;
            border-bottom: 2px solid var(--primary-color);
            color: var(--dark-color);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            vertical-align: middle;
            padding: 15px 10px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.02);
        }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        /* Buttons */
        .btn {
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: #0b5ed7;
            border-color: #0b5ed7;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        .btn-icon {
            width: 35px;
            height: 35px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        
        /* Header */
        .header {
            height: 70px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 998;
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header .toggle-sidebar {
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--secondary-color);
        }
        
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header .user-info .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), #0b5ed7);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .header .user-info .user-details {
            text-align: right;
        }
        
        .header .user-info .user-name {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 2px;
        }
        
        .header .user-info .user-role {
            font-size: 0.85rem;
            color: var(--secondary-color);
        }
        
        /* Content */
        .content {
            padding: 30px;
        }
        
        .page-title {
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .page-title h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .page-title .breadcrumb {
            background: none;
            padding: 0;
            margin: 0;
        }
        
        .page-title .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .page-title .breadcrumb-item.active {
            color: var(--secondary-color);
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        /* Modal */
        .modal-content {
            border: none;
            border-radius: 15px;
        }
        
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 20px 25px;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.05);
            padding: 15px 25px;
        }
        
        /* Print Styles */
        @media print {
            .sidebar,
            .header,
            .btn,
            .no-print {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--secondary-color);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="spinner-wrapper" id="loadingSpinner">
        <div class="spinner"></div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="wrapper">