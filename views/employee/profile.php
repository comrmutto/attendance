<?php
$title = 'โปรไฟล์พนักงาน';
$emp = $employee;
?>

<div class="page-title">
    <div>
        <h2><i class="fas fa-user me-2"></i>โปรไฟล์พนักงาน</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard">หน้าแรก</a></li>
                <li class="breadcrumb-item"><a href="/employee">พนักงาน</a></li>
                <li class="breadcrumb-item active">โปรไฟล์</li>
            </ol>
        </nav>
    </div>
    <div>
        <?php if ($can_edit): ?>
        <a href="/employee/edit?id=<?php echo $emp['id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit me-2"></i>แก้ไขข้อมูล
        </a>
        <?php endif; ?>
        <button class="btn btn-outline-secondary ms-2" onclick="printPage()">
            <i class="fas fa-print me-2"></i>พิมพ์
        </button>
    </div>
</div>

<div class="row">
    <!-- Profile Card -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="profile-avatar mx-auto mb-3">
                    <?php echo strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)); ?>
                </div>
                <h3 class="mb-1"><?php echo $emp['first_name'] . ' ' . $emp['last_name']; ?></h3>
                <?php if ($emp['nickname']): ?>
                <p class="text-muted mb-2">(<?php echo $emp['nickname']; ?>)</p>
                <?php endif; ?>
                <p class="mb-3">
                    <span class="badge bg-primary"><?php echo $emp['position_name'] ?? '-'; ?></span>
                    <span class="badge bg-info"><?php echo $emp['department_name'] ?? '-'; ?></span>
                </p>
                
                <div class="d-flex justify-content-center mb-3">
                    <div class="px-3 text-center">
                        <div class="fw-bold"><?php echo $stats['yearly']['total_days'] ?? 0; ?></div>
                        <small class="text-muted">วันทำงาน</small>
                    </div>
                    <div class="px-3 text-center">
                        <div class="fw-bold"><?php echo $stats['yearly']['attendance_rate'] ?? 0; ?>%</div>
                        <small class="text-muted">อัตราการเข้า</small>
                    </div>
                    <div class="px-3 text-center">
                        <div class="fw-bold"><?php echo $stats['yearly']['total_overtime'] ?? 0; ?></div>
                        <small class="text-muted">OT (ชม.)</small>
                    </div>
                </div>
                
                <hr>
                
                <div class="text-start">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>ข้อมูลส่วนตัว</h6>
                    
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">รหัสพนักงาน:</th>
                            <td><?php echo formatUserId($emp['user_id']); ?></td>
                        </tr>
                        <tr>
                            <th>เพศ:</th>
                            <td>
                                <?php 
                                $genders = ['M' => 'ชาย', 'F' => 'หญิง', 'O' => 'อื่นๆ'];
                                echo $genders[$emp['gender']] ?? '-';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>วันเกิด:</th>
                            <td><?php echo $emp['birth_date'] ? thaiDate($emp['birth_date'], 'full') : '-'; ?></td>
                        </tr>
                        <tr>
                            <th>อายุ:</th>
                            <td>
                                <?php 
                                if ($emp['birth_date']) {
                                    $age = date_diff(date_create($emp['birth_date']), date_create('today'))->y;
                                    echo $age . ' ปี';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>เบอร์โทร:</th>
                            <td><?php echo $emp['phone'] ?? '-'; ?></td>
                        </tr>
                        <tr>
                            <th>อีเมล:</th>
                            <td><?php echo $emp['email'] ?? '-'; ?></td>
                        </tr>
                        <tr>
                            <th>ที่อยู่:</th>
                            <td><?php echo $emp['address'] ?? '-'; ?></td>
                        </tr>
                    </table>
                    
                    <h6 class="mb-3 mt-4"><i class="fas fa-briefcase me-2"></i>ข้อมูลการทำงาน</h6>
                    
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">วันที่เริ่มงาน:</th>
                            <td><?php echo $emp['hire_date'] ? thaiDate($emp['hire_date'], 'full') : '-'; ?></td>
                        </tr>
                        <tr>
                            <th>อายุงาน:</th>
                            <td><?php echo $emp['tenure'] ?? '-'; ?></td>
                        </tr>
                        <tr>
                            <th>กะ:</th>
                            <td><?php echo $emp['shift_name'] ?? '-'; ?></td>
                        </tr>
                        <tr>
                            <th>ผู้บังคับบัญชา:</th>
                            <td><?php echo $emp['supervisor_name'] ?? '-'; ?></td>
                        </tr>
                        <tr>
                            <th>สถานะ:</th>
                            <td>
                                <?php if ($emp['status'] == 1): ?>
                                <span class="badge bg-success">กำลังทำงาน</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">ออกแล้ว</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Attendance History -->
    <div class="col-md-8">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <div class="stat-value"><?php echo $stats['month']['present_days'] ?? 0; ?></div>
                    <div class="stat-label">มาทำงานเดือนนี้</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card warning">
                    <div class="stat-value"><?php echo $stats['month']['late_days'] ?? 0; ?></div>
                    <div class="stat-label">สายเดือนนี้</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card info">
                    <div class="stat-value"><?php echo $stats['month']['total_overtime'] ?? 0; ?></div>
                    <div class="stat-label">OT เดือนนี้ (ชม.)</div>
                </div>
            </div>
        </div>
        
        <!-- Leave Balance -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-calendar-alt me-2"></i>วันลาคงเหลือ</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($leave_remaining as $code => $leave): ?>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 text-center">
                            <div class="small text-muted mb-2"><?php echo $leave['name']; ?></div>
                            <div class="h3 mb-0"><?php echo $leave['remaining']; ?></div>
                            <div class="small text-muted">/ <?php echo $leave['total']; ?> วัน</div>
                            <div class="progress mt-2" style="height: 5px;">
                                <div class="progress-bar bg-success" 
                                     style="width: <?php echo ($leave['used'] / $leave['total']) * 100; ?>%"></div>
                            </div>
                            <small class="text-muted">ใช้ไป <?php echo $leave['used']; ?> วัน</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Attendance -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history me-2"></i>ประวัติการเข้างานล่าสุด</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>วันที่</th>
                                <th>เวลาเข้า</th>
                                <th>เวลาออก</th>
                                <th>ชั่วโมง</th>
                                <th>สถานะ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_attendance as $att): ?>
                            <tr>
                                <td><?php echo thaiDate($att['date'], 'short'); ?></td>
                                <td><?php echo $att['check_in_time'] ? date('H:i', strtotime($att['check_in_time'])) : '-'; ?></td>
                                <td><?php echo $att['check_out_time'] ? date('H:i', strtotime($att['check_out_time'])) : '-'; ?></td>
                                <td><?php echo $att['work_hours'] ?? '-'; ?></td>
                                <td>
                                    <?php
                                    if (!$att['check_in_time']) {
                                        echo '<span class="badge bg-danger">ขาด</span>';
                                    } elseif ($att['check_in_status'] == 'late') {
                                        echo '<span class="badge bg-warning">สาย</span>';
                                    } elseif ($att['overtime_hours'] > 0) {
                                        echo '<span class="badge bg-info">OT</span>';
                                    } else {
                                        echo '<span class="badge bg-success">ปกติ</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="/attendance/view?id=<?php echo $att['id']; ?>" 
                                       class="btn btn-sm btn-icon btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Subordinates (if supervisor) -->
        <?php if (!empty($subordinates)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="fas fa-users me-2"></i>ลูกน้องในสังกัด</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>รหัสพนักงาน</th>
                                <th>ชื่อ-สกุล</th>
                                <th>ตำแหน่ง</th>
                                <th>แผนก</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subordinates as $sub): ?>
                            <tr>
                                <td><?php echo formatUserId($sub['user_id']); ?></td>
                                <td><?php echo $sub['first_name'] . ' ' . $sub['last_name']; ?></td>
                                <td><?php echo $sub['position_name'] ?? '-'; ?></td>
                                <td><?php echo $sub['department_name'] ?? '-'; ?></td>
                                <td>
                                    <a href="/employee/profile?id=<?php echo $sub['id']; ?>" 
                                       class="btn btn-sm btn-icon btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), #0b5ed7);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: bold;
    box-shadow: 0 10px 20px rgba(13, 110, 253, 0.3);
}

.stat-card {
    padding: 20px;
    border-radius: 10px;
    color: white;
    text-align: center;
}

.stat-card.primary { background: linear-gradient(45deg, var(--primary-color), #0b5ed7); }
.stat-card.warning { background: linear-gradient(45deg, var(--warning-color), #ffca2c); color: var(--dark-color); }
.stat-card.info { background: linear-gradient(45deg, var(--info-color), #0aa2c0); }

.table th {
    border-top: none;
    color: var(--secondary-color);
    font-weight: 500;
    font-size: 0.9rem;
}
</style>