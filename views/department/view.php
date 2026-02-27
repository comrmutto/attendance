<?php
$title = 'รายละเอียดแผนก';
$dept = $department;
?>

<div class="page-title">
    <div>
        <h2><i class="fas fa-building me-2"></i>รายละเอียดแผนก</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard">หน้าแรก</a></li>
                <li class="breadcrumb-item"><a href="/department">แผนก</a></li>
                <li class="breadcrumb-item active"><?php echo $dept['name']; ?></li>
            </ol>
        </nav>
    </div>
    <div>
        <?php if ($can_edit): ?>
        <a href="/department/edit?id=<?php echo $dept['id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit me-2"></i>แก้ไข
        </a>
        <?php endif; ?>
        <a href="/department" class="btn btn-outline-secondary ms-2">
            <i class="fas fa-arrow-left me-2"></i>กลับ
        </a>
    </div>
</div>

<!-- Department Info -->
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle me-2"></i>ข้อมูลแผนก</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <th>ชื่อแผนก:</th>
                        <td><?php echo $dept['name']; ?></td>
                    </tr>
                    <tr>
                        <th>รหัสแผนก:</th>
                        <td><span class="badge bg-primary"><?php echo $dept['code']; ?></span></td>
                    </tr>
                    <tr>
                        <th>จำนวนพนักงาน:</th>
                        <td><?php echo $dept['total_employees'] ?? 0; ?> คน</td>
                    </tr>
                    <tr>
                        <th>พนักงาน active:</th>
                        <td><?php echo $dept['active_employees'] ?? 0; ?> คน</td>
                    </tr>
                    <tr>
                        <th>สถานะ:</th>
                        <td><?php echo $dept['status_badge']; ?></td>
                    </tr>
                    <tr>
                        <th>สร้างเมื่อ:</th>
                        <td><?php echo $dept['created_at_formatted']; ?></td>
                    </tr>
                </table>
                
                <h6 class="mt-3 mb-2">ผู้จัดการแผนก</h6>
                <?php if (!empty($dept['managers_list'])): ?>
                    <div class="list-group">
                        <?php foreach ($dept['managers_list'] as $manager): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div><?php echo $manager['name']; ?></div>
                                <small class="text-muted"><?php echo $manager['user_id_formatted']; ?></small>
                            </div>
                            <a href="/employee/profile?id=<?php echo $manager['id']; ?>" 
                               class="btn btn-sm btn-icon btn-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-3">ไม่มีผู้จัดการ</p>
                <?php endif; ?>
                
                <?php if ($dept['description']): ?>
                <h6 class="mt-3 mb-2">รายละเอียด</h6>
                <p class="text-muted"><?php echo nl2br($dept['description']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Today's Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card success">
                    <div class="stat-value"><?php echo $today_stats['present_today'] ?? 0; ?></div>
                    <div class="stat-label">มาทำงานวันนี้</div>
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card warning">
                    <div class="stat-value"><?php echo $today_stats['late_today'] ?? 0; ?></div>
                    <div class="stat-label">สายวันนี้</div>
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card danger">
                    <div class="stat-value"><?php echo $today_stats['absent_today'] ?? 0; ?></div>
                    <div class="stat-label">ขาดวันนี้</div>
                    <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                </div>
            </div>
        </div>
        
        <!-- Monthly Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>สถิติรายเดือน</h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" style="height: 300px;"></canvas>
            </div>
        </div>
        
        <!-- Employees List -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users me-2"></i>รายชื่อพนักงานในแผนก (<?php echo count($employees); ?> คน)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>รหัสพนักงาน</th>
                                <th>ชื่อ-สกุล</th>
                                <th>ตำแหน่ง</th>
                                <th>สถานะ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <i class="fas fa-users fa-3x mb-3 text-muted"></i>
                                    <p class="text-muted">ไม่มีพนักงานในแผนก</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td><?php echo formatUserId($emp['user_id']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-2">
                                                <?php echo strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)); ?>
                                            </div>
                                            <?php echo $emp['first_name'] . ' ' . $emp['last_name']; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $emp['position_name'] ?? '-'; ?></td>
                                    <td>
                                        <?php if ($emp['status'] == 1): ?>
                                        <span class="badge bg-success">กำลังทำงาน</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">ออกแล้ว</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/employee/profile?id=<?php echo $emp['id']; ?>" 
                                           class="btn btn-sm btn-icon btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Position Distribution -->
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2"></i>สัดส่วนตำแหน่ง</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="positionChart" style="height: 250px;"></canvas>
                    </div>
                    <div class="col-md-6">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ตำแหน่ง</th>
                                    <th class="text-center">จำนวน</th>
                                    <th class="text-center">สัดส่วน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalEmployees = count($employees);
                                $positionCounts = [];
                                foreach ($employees as $emp) {
                                    $pos = $emp['position_name'] ?? 'ไม่มีตำแหน่ง';
                                    if (!isset($positionCounts[$pos])) {
                                        $positionCounts[$pos] = 0;
                                    }
                                    $positionCounts[$pos]++;
                                }
                                ?>
                                <?php foreach ($positionCounts as $pos => $count): ?>
                                <tr>
                                    <td><?php echo $pos; ?></td>
                                    <td class="text-center"><?php echo $count; ?></td>
                                    <td class="text-center">
                                        <?php echo round(($count / $totalEmployees) * 100, 1); ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(45deg, var(--primary-color), #0b5ed7);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: bold;
}

.stat-card {
    position: relative;
    overflow: hidden;
}

.stat-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 3rem;
    opacity: 0.2;
}
</style>

<script>
// Monthly Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
const monthlyData = <?php echo json_encode($monthly_stats); ?>;

new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: monthlyData.map(d => d.date),
        datasets: [{
            label: 'มาทำงาน',
            data: monthlyData.map(d => d.present_count),
            backgroundColor: 'rgba(25, 135, 84, 0.5)',
            borderColor: '#198754',
            borderWidth: 1
        }, {
            label: 'สาย',
            data: monthlyData.map(d => d.late_count),
            backgroundColor: 'rgba(255, 193, 7, 0.5)',
            borderColor: '#ffc107',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Position Chart
const positionCtx = document.getElementById('positionChart').getContext('2d');
const positionLabels = <?php echo json_encode(array_keys($positionCounts)); ?>;
const positionData = <?php echo json_encode(array_values($positionCounts)); ?>;

new Chart(positionCtx, {
    type: 'pie',
    data: {
        labels: positionLabels,
        datasets: [{
            data: positionData,
            backgroundColor: [
                '#0d6efd',
                '#198754',
                '#ffc107',
                '#dc3545',
                '#6c757d',
                '#0dcaf0'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>