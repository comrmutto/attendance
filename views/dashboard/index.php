<?php
$title = 'แดชบอร์ด';
?>

<div class="page-title">
    <div>
        <h2><i class="fas fa-home me-2"></i>แดชบอร์ด</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard">หน้าแรก</a></li>
                <li class="breadcrumb-item active">แดชบอร์ด</li>
            </ol>
        </nav>
    </div>
    <div>
        <button class="btn btn-outline-primary me-2" onclick="location.reload()">
            <i class="fas fa-sync-alt me-2"></i>รีเฟรช
        </button>
        <button class="btn btn-outline-secondary" onclick="printPage()">
            <i class="fas fa-print me-2"></i>พิมพ์
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card primary">
            <div class="stat-value"><?php echo $summary['total_employees'] ?? 0; ?></div>
            <div class="stat-label">พนักงานทั้งหมด</div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card success">
            <div class="stat-value"><?php echo $summary['present_today'] ?? 0; ?></div>
            <div class="stat-label">มาทำงานวันนี้</div>
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card warning">
            <div class="stat-value"><?php echo $summary['late_today'] ?? 0; ?></div>
            <div class="stat-label">มาสายวันนี้</div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card info">
            <div class="stat-value"><?php echo $summary['attendance_rate'] ?? 0; ?>%</div>
            <div class="stat-label">อัตราการเข้า</div>
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Attendance Chart -->
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar me-2"></i>การเข้างานรายชั่วโมง</h5>
            </div>
            <div class="card-body">
                <canvas id="hourlyChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Status Chart -->
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2"></i>สถานะวันนี้</h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <!-- Recent Activity -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history me-2"></i>กิจกรรมล่าสุด</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($recent_activity)): ?>
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>ไม่มีกิจกรรม</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $activity): ?>
                        <div class="list-group-item">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <span class="badge bg-<?php echo $activity['color']; ?> p-3">
                                        <i class="fas <?php echo $activity['icon']; ?>"></i>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo $activity['employee_name']; ?></strong>
                                            <small class="text-muted ms-2"><?php echo $activity['user_id_formatted']; ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo $activity['time_formatted']; ?></small>
                                    </div>
                                    <div>
                                        <small>
                                            <?php echo $activity['department_name']; ?> • 
                                            <?php echo $activity['type'] == 'check_in' ? 'เข้างาน' : 'เลิกงาน'; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notifications -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-bell me-2"></i>การแจ้งเตือน</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($notifications)): ?>
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                            <p>ไม่มีการแจ้งเตือน</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item">
                            <div class="d-flex">
                                <div class="me-3">
                                    <span class="badge bg-<?php echo $notification['type']; ?> p-3">
                                        <i class="fas <?php echo $notification['icon']; ?>"></i>
                                    </span>
                                </div>
                                <div>
                                    <div class="fw-bold"><?php echo $notification['title']; ?></div>
                                    <p class="mb-1"><?php echo $notification['message']; ?></p>
                                    <small class="text-muted"><?php echo $notification['time']; ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($team_attendance)): ?>
<!-- Team Attendance (for supervisors) -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users me-2"></i>ทีมของฉัน</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>รหัสพนักงาน</th>
                                <th>ชื่อ-สกุล</th>
                                <th>แผนก</th>
                                <th>เวลาเข้า</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_attendance as $member): ?>
                            <tr>
                                <td><?php echo $member['user_id_formatted']; ?></td>
                                <td><?php echo $member['name']; ?></td>
                                <td><?php echo $member['employee']['department_name'] ?? '-'; ?></td>
                                <td><?php echo $member['check_in']; ?></td>
                                <td><?php echo $member['status_badge']; ?></td>
                                <td>
                                    <a href="/employee/profile?id=<?php echo $member['employee']['id']; ?>" 
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
    </div>
</div>
<?php endif; ?>

<script>
// Hourly Chart
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($charts['hourly']['labels']); ?>,
        datasets: [{
            label: 'เข้างาน',
            data: <?php echo json_encode($charts['hourly']['check_in']); ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'เลิกงาน',
            data: <?php echo json_encode($charts['hourly']['check_out']); ?>,
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            }
        },
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

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($charts['status']['labels']); ?>,
        datasets: [{
            data: <?php echo json_encode($charts['status']['values']); ?>,
            backgroundColor: <?php echo json_encode($charts['status']['colors']); ?>
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

// Auto refresh every 5 minutes
startAutoRefresh(function() {
    location.reload();
}, 300000);
</script>