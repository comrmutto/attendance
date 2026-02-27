<?php
// ดึงข้อมูลผู้ใช้ปัจจุบัน
$user = Auth::user();
$currentUrl = $_SERVER['REQUEST_URI'];
?>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3><?php echo APP_NAME; ?></h3>
        <div class="logo-small">
            <i class="fas fa-clock"></i>
        </div>
    </div>
    
    <div class="user-profile">
        <div class="avatar">
            <?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1)); ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?php echo $user['fullname'] ?? 'User'; ?></div>
            <div class="user-role">
                <?php
                $roles = [
                    'admin' => 'ผู้ดูแลระบบ',
                    'gm' => 'GM',
                    'manager' => 'ผู้จัดการ',
                    'supervisor' => 'หัวหน้างาน',
                    'employee' => 'พนักงาน'
                ];
                echo $roles[$user['role']] ?? $user['role'];
                ?>
            </div>
        </div>
    </div>
    
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="/dashboard" class="nav-link <?php echo strpos($currentUrl, '/dashboard') !== false ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>แดชบอร์ด</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="/attendance" class="nav-link <?php echo strpos($currentUrl, '/attendance') !== false ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i>
                <span>บันทึกเวลา</span>
            </a>
        </li>
        
        <?php if (Auth::hasLevel(POSITION_SUPERVISOR)): ?>
        <li class="nav-item">
            <a href="/attendance/my-attendance" class="nav-link <?php echo strpos($currentUrl, '/attendance/my-attendance') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user-clock"></i>
                <span>เวลาของฉัน</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li class="nav-item">
            <a href="/employee" class="nav-link <?php echo strpos($currentUrl, '/employee') !== false ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>พนักงาน</span>
            </a>
        </li>
        
        <?php if (Auth::hasLevel(POSITION_MANAGER)): ?>
        <li class="nav-item">
            <a href="/department" class="nav-link <?php echo strpos($currentUrl, '/department') !== false ? 'active' : ''; ?>">
                <i class="fas fa-building"></i>
                <span>แผนก</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (Auth::hasLevel(POSITION_SUPERVISOR)): ?>
        <li class="nav-item">
            <a href="/attendance/report" class="nav-link <?php echo strpos($currentUrl, '/attendance/report') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>รายงาน</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li class="nav-item">
            <a href="/employee/profile" class="nav-link <?php echo strpos($currentUrl, '/employee/profile') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>โปรไฟล์</span>
            </a>
        </li>
        
        <?php if (Auth::isAdmin()): ?>
        <li class="nav-divider"></li>
        <li class="nav-header">จัดการระบบ</li>
        
        <li class="nav-item">
            <a href="/admin/users" class="nav-link">
                <i class="fas fa-user-cog"></i>
                <span>ผู้ใช้</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="/admin/settings" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>ตั้งค่า</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="/admin/logs" class="nav-link">
                <i class="fas fa-history"></i>
                <span>ประวัติการใช้งาน</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
    
    <div class="sidebar-footer">
        <a href="/logout" class="nav-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>ออกจากระบบ</span>
        </a>
    </div>
</nav>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <!-- Header -->
    <header class="header">
        <div class="toggle-sidebar" id="toggleSidebar">
            <i class="fas fa-bars"></i>
        </div>
        
        <div class="user-info">
            <div class="user-details">
                <div class="user-name"><?php echo $user['fullname'] ?? 'User'; ?></div>
                <div class="user-role"><?php echo $user['department_name'] ?? ''; ?></div>
            </div>
            <div class="avatar">
                <?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1)); ?>
            </div>
        </div>
    </header>
    
    <div class="content">