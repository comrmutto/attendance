<?php
$title = 'รายละเอียดการเข้างาน';
$att = $attendance;
$emp = $employee;
?>

<div class="page-title">
    <div>
        <h2><i class="fas fa-clock me-2"></i>รายละเอียดการเข้างาน</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard">หน้าแรก</a></li>
                <li class="breadcrumb-item"><a href="/attendance">บันทึกเวลา</a></li>
                <li class="breadcrumb-item active">รายละเอียด</li>
            </ol>
        </nav>
    </div>
    <div>
        <a href="/attendance" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>กลับ
        </a>
        <?php if (Auth::hasLevel(POSITION_SUPERVISOR)): ?>
        <a href="/attendance/edit?id=<?php echo $att['id']; ?>" class="btn btn-warning ms-2">
            <i class="fas fa-edit me-2"></i>แก้ไข
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Employee Info -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user me-2"></i>ข้อมูลพนักงาน</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="avatar-large mx-auto mb-3">
                        <?php echo strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)); ?>
                    </div>
                    <h4><?php echo $emp['first_name'] . ' ' . $emp['last_name']; ?></h4>
                    <p class="text-muted"><?php echo formatUserId($emp['user_id']); ?></p>
                </div>
                
                <table class="table table-sm">
                    <tr>
                        <th>แผนก:</th>
                        <td><?php echo $emp['department_name'] ?? '-'; ?></td>
                    </tr>
                    <tr>
                        <th>ตำแหน่ง:</th>
                        <td><?php echo $emp['position_name'] ?? '-'; ?></td>
                    </tr>
                    <tr>
                        <th>กะ:</th>
                        <td><?php echo $att['shift_name'] ?? '-'; ?></td>
                    </tr>
                    <tr>
                        <th>เวลาทำงาน:</th>
                        <td><?php echo $att['start_time'] ?? '-'; ?> - <?php echo $att['end_time'] ?? '-'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Attendance Details -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle me-2"></i>รายละเอียดการเข้างาน</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-box p-3 <?php echo $att['check_in_time'] ? 'bg-light' : 'bg-light-danger'; ?> rounded mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">เวลาเข้า</small>
                                    <h3 class="mb-0">
                                        <?php echo $att['check_in_time'] ? date('H:i', strtotime($att['check_in_time'])) : '-'; ?>
                                    </h3>
                                    <small><?php echo $att['check_in_time'] ? thaiDate($att['check_in_time'], 'date') : ''; ?></small>
                                </div>
                                <div class="text-center">
                                    <?php if ($att['check_in_time']): ?>
                                        <?php if ($att['check_in_status'] == 'late'): ?>
                                            <span class="badge bg-warning p-3">
                                                <i class="fas fa-clock fa-2x"></i>
                                            </span>
                                            <div class="mt-2">สาย <?php echo $att['late_minutes']; ?> นาที</div>
                                        <?php elseif ($att['check_in_status'] == 'early'): ?>
                                            <span class="badge bg-info p-3">
                                                <i class="fas fa-clock fa-2x"></i>
                                            </span>
                                            <div class="mt-2">มาก่อนเวลา</div>
                                        <?php else: ?>
                                            <span class="badge bg-success p-3">
                                                <i class="fas fa-check-circle fa-2x"></i>
                                            </span>
                                            <div class="mt-2">ตรงเวลา</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-danger p-3">
                                            <i class="fas fa-times-circle fa-2x"></i>
                                        </span>
                                        <div class="mt-2">ไม่พบข้อมูล</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-box p-3 <?php echo $att['check_out_time'] ? 'bg-light' : 'bg-light-danger'; ?> rounded mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">เวลาออก</small>
                                    <h3 class="mb-0">
                                        <?php echo $att['check_out_time'] ? date('H:i', strtotime($att['check_out_time'])) : '-'; ?>
                                    </h3>
                                    <small><?php echo $att['check_out_time'] ? thaiDate($att['check_out_time'], 'date') : ''; ?></small>
                                </div>
                                <div class="text-center">
                                    <?php if ($att['check_out_time']): ?>
                                        <?php if ($att['check_out_status'] == 'early_leave'): ?>
                                            <span class="badge bg-warning p-3">
                                                <i class="fas fa-sign-out-alt fa-2x"></i>
                                            </span>
                                            <div class="mt-2">กลับก่อน <?php echo $att['early_leave_minutes']; ?> นาที</div>
                                        <?php elseif ($att['overtime_hours'] > 0): ?>
                                            <span class="badge bg-info p-3">
                                                <i class="fas fa-clock fa-2x"></i>
                                            </span>
                                            <div class="mt-2">OT <?php echo $att['overtime_hours']; ?> ชม.</div>
                                        <?php else: ?>
                                            <span class="badge bg-success p-3">
                                                <i class="fas fa-check-circle fa-2x"></i>
                                            </span>
                                            <div class="mt-2">ตรงเวลา</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary p-3">
                                            <i class="fas fa-clock fa-2x"></i>
                                        </span>
                                        <div class="mt-2">รอออกเวลา</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="stat-card primary text-center p-3">
                            <div class="stat-value"><?php echo $att['work_hours'] ?? 0; ?></div>
                            <div class="stat-label">ชั่วโมงทำงาน</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card info text-center p-3">
                            <div class="stat-value"><?php echo $att['overtime_hours'] ?? 0; ?></div>
                            <div class="stat-label">ชั่วโมง OT</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card warning text-center p-3">
                            <div class="stat-value"><?php echo date('H:i', strtotime($att['created_at'])); ?></div>
                            <div class="stat-label">บันทึกเมื่อ</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Log History -->
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="fas fa-history me-2"></i>ประวัติการสแกน</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($logs as $log): ?>
                    <div class="timeline-item">
                        <div class="timeline-badge <?php echo $log['inout_status'] == 1 ? 'bg-success' : 'bg-info'; ?>">
                            <i class="fas <?php echo $log['inout_status'] == 1 ? 'fa-sign-in-alt' : 'fa-sign-out-alt'; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?php echo $log['inout_status'] == 1 ? 'เข้างาน' : 'เลิกงาน'; ?></strong>
                                    <p class="mb-1"><?php echo thaiDate($log['scan_time'], 'full'); ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-server me-1"></i>IP: <?php echo $log['device_ip']; ?>
                                    </small>
                                </div>
                                <?php if ($log['raw']): ?>
                                <button class="btn btn-sm btn-outline-secondary" 
                                        onclick="alert(JSON.stringify(<?php echo $log['raw']; ?>, null, 2))">
                                    <i class="fas fa-code"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(45deg, var(--primary-color), #0b5ed7);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: bold;
}

.bg-light-danger {
    background-color: rgba(220, 53, 69, 0.1);
}

.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 25px;
    width: 2px;
    height: 100%;
    background: var(--primary-color);
    top: 0;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
    padding-left: 70px;
}

.timeline-badge {
    position: absolute;
    left: 0;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    z-index: 1;
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}

.timeline-content {
    background: white;
    border-radius: 10px;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
</style>