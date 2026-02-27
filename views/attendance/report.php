<?php
$title = 'รายงานการเข้างาน';
?>

<div class="page-title">
    <div>
        <h2><i class="fas fa-chart-bar me-2"></i>รายงานการเข้างาน</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard">หน้าแรก</a></li>
                <li class="breadcrumb-item"><a href="/attendance">บันทึกเวลา</a></li>
                <li class="breadcrumb-item active">รายงาน</li>
            </ol>
        </nav>
    </div>
    <div>
        <button class="btn btn-success me-2" onclick="exportReport()">
            <i class="fas fa-file-excel me-2"></i>ส่งออก Excel
        </button>
        <button class="btn btn-outline-secondary" onclick="printPage()">
            <i class="fas fa-print me-2"></i>พิมพ์
        </button>
    </div>
</div>

<!-- Report Type Selector -->
<div class="filter-section">
    <ul class="nav nav-tabs" id="reportTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $type == 'daily' ? 'active' : ''; ?>" 
                    id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily" 
                    type="button" role="tab" onclick="switchReport('daily')">
                <i class="fas fa-calendar-day me-2"></i>รายงานรายวัน
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $type == 'monthly' ? 'active' : ''; ?>" 
                    id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly" 
                    type="button" role="tab" onclick="switchReport('monthly')">
                <i class="fas fa-calendar-alt me-2"></i>รายงานรายเดือน
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $type == 'summary' ? 'active' : ''; ?>" 
                    id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" 
                    type="button" role="tab" onclick="switchReport('summary')">
                <i class="fas fa-chart-pie me-2"></i>รายงานสรุป
            </button>
        </li>
    </ul>
</div>

<!-- Report Filters -->
<div class="filter-section mt-3">
    <form method="GET" action="/attendance/report" id="reportForm">
        <input type="hidden" name="type" id="reportType" value="<?php echo $type; ?>">
        
        <div class="row">
            <?php if ($type == 'daily'): ?>
            <div class="col-md-3">
                <label class="form-label">เลือกวันที่</label>
                <input type="date" name="date" class="form-control" 
                       value="<?php echo $date; ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            <?php elseif ($type == 'monthly' || $type == 'summary'): ?>
            <div class="col-md-3">
                <label class="form-label">เลือกเดือน</label>
                <input type="month" name="month" class="form-control" 
                       value="<?php echo $month; ?>" max="<?php echo date('Y-m'); ?>">
            </div>
            <?php endif; ?>
            
            <div class="col-md-3">
                <label class="form-label">แผนก</label>
                <select name="department_id" class="form-select">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>" 
                        <?php echo $department_id == $dept['id'] ? 'selected' : ''; ?>>
                        <?php echo $dept['name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i>แสดงรายงาน
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Report Content -->
<div class="tab-content mt-4">
    <!-- Daily Report -->
    <div class="tab-pane fade <?php echo $type == 'daily' ? 'show active' : ''; ?>" id="daily" role="tabpanel">
        <?php if ($type == 'daily'): ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-calendar-day me-2"></i>รายงานรายวัน ประจำวันที่ <?php echo thaiDate($date, 'full'); ?></h5>
                </div>
                <div class="card-body">
                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <?php 
                        $totalEmployees = array_sum(array_column($report, 'total_employees'));
                        $totalPresent = array_sum(array_column($report, 'present'));
                        $totalLate = array_sum(array_column($report, 'late'));
                        $totalAbsent = array_sum(array_column($report, 'absent'));
                        $totalLeave = array_sum(array_column($report, 'on_leave'));
                        ?>
                        <div class="col-md-3">
                            <div class="stat-card primary">
                                <div class="stat-value"><?php echo $totalEmployees; ?></div>
                                <div class="stat-label">พนักงานทั้งหมด</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card success">
                                <div class="stat-value"><?php echo $totalPresent; ?></div>
                                <div class="stat-label">มาทำงาน</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card warning">
                                <div class="stat-value"><?php echo $totalLate; ?></div>
                                <div class="stat-label">มาสาย</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card danger">
                                <div class="stat-value"><?php echo $totalAbsent; ?></div>
                                <div class="stat-label">ขาดงาน</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Department Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>แผนก</th>
                                    <th class="text-center">พนักงานทั้งหมด</th>
                                    <th class="text-center">มาทำงาน</th>
                                    <th class="text-center">ขาด</th>
                                    <th class="text-center">ลา</th>
                                    <th class="text-center">สาย</th>
                                    <th class="text-center">อัตราการเข้า</th>
                                    <th class="text-center">OT (ชม.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report as $row): ?>
                                <tr>
                                    <td><?php echo $row['department_name']; ?></td>
                                    <td class="text-center"><?php echo $row['total_employees']; ?></td>
                                    <td class="text-center"><?php echo $row['present']; ?></td>
                                    <td class="text-center"><?php echo $row['absent']; ?></td>
                                    <td class="text-center"><?php echo $row['on_leave']; ?></td>
                                    <td class="text-center"><?php echo $row['late']; ?></td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 5px;">
                                                <div class="progress-bar bg-success" 
                                                     style="width: <?php echo $row['attendance_rate']; ?>%"></div>
                                            </div>
                                            <span><?php echo $row['attendance_rate']; ?>%</span>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php echo $row['total_overtime'] ?? 0; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Monthly Report -->
    <div class="tab-pane fade <?php echo $type == 'monthly' ? 'show active' : ''; ?>" id="monthly" role="tabpanel">
        <?php if ($type == 'monthly'): ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-calendar-alt me-2"></i>รายงานรายเดือน <?php 
                        $monthYear = explode('-', $month);
                        echo $monthYear[1] . '/' . ($monthYear[0] + 543); 
                    ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>รหัสพนักงาน</th>
                                    <th>ชื่อ-สกุล</th>
                                    <th>แผนก</th>
                                    <th class="text-center">วันทำงาน</th>
                                    <th class="text-center">สาย</th>
                                    <th class="text-center">ขาด</th>
                                    <th class="text-center">OT (ชม.)</th>
                                    <th class="text-center">ชม.เฉลี่ย</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report as $row): ?>
                                <tr>
                                    <td><?php echo formatUserId($row['user_id']); ?></td>
                                    <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                                    <td><?php echo $row['department_name']; ?></td>
                                    <td class="text-center"><?php echo $row['working_days']; ?></td>
                                    <td class="text-center">
                                        <?php if ($row['late_days'] > 0): ?>
                                        <span class="badge bg-warning"><?php echo $row['late_days']; ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['absent_days'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $row['absent_days']; ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo $row['total_overtime']; ?></td>
                                    <td class="text-center"><?php echo number_format($row['avg_work_hours'], 1); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Summary Report -->
    <div class="tab-pane fade <?php echo $type == 'summary' ? 'show active' : ''; ?>" id="summary" role="tabpanel">
        <?php if ($type == 'summary'): ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line me-2"></i>รายงานสรุปประจำเดือน <?php 
                        $monthYear = explode('-', $month);
                        echo $monthYear[1] . '/' . ($monthYear[0] + 543); 
                    ?></h5>
                </div>
                <div class="card-body">
                    <!-- Summary Chart -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <canvas id="summaryChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                    
                    <!-- Summary Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>วันที่</th>
                                    <th class="text-center">มาทำงาน</th>
                                    <th class="text-center">สาย</th>
                                    <th class="text-center">OT (ชม.)</th>
                                    <th class="text-center">อัตราการเข้า</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalDays = count($report);
                                $totalPresent = 0;
                                $totalLate = 0;
                                $totalOT = 0;
                                ?>
                                <?php foreach ($report as $row): 
                                    $totalPresent += $row['present_count'];
                                    $totalLate += $row['late_count'];
                                    $totalOT += $row['overtime_hours'];
                                ?>
                                <tr>
                                    <td><?php echo thaiDate($row['date'], 'short'); ?></td>
                                    <td class="text-center"><?php echo $row['present_count']; ?></td>
                                    <td class="text-center"><?php echo $row['late_count']; ?></td>
                                    <td class="text-center"><?php echo $row['overtime_hours']; ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $rate = ($row['present_count'] / $totalEmployees) * 100;
                                        ?>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 5px;">
                                                <div class="progress-bar bg-success" 
                                                     style="width: <?php echo $rate; ?>%"></div>
                                            </div>
                                            <span><?php echo number_format($rate, 1); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th>รวม</th>
                                    <th class="text-center"><?php echo $totalPresent; ?></th>
                                    <th class="text-center"><?php echo $totalLate; ?></th>
                                    <th class="text-center"><?php echo number_format($totalOT, 1); ?></th>
                                    <th class="text-center">
                                        <?php 
                                        $avgRate = $totalPresent / ($totalDays * $totalEmployees) * 100;
                                        echo number_format($avgRate, 1) . '%';
                                        ?>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <script>
            // Summary Chart
            const summaryCtx = document.getElementById('summaryChart').getContext('2d');
            new Chart(summaryCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function($row) {
                        return thaiDate($row['date'], 'short');
                    }, $report)); ?>,
                    datasets: [{
                        label: 'มาทำงาน',
                        data: <?php echo json_encode(array_column($report, 'present_count')); ?>,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'สาย',
                        data: <?php echo json_encode(array_column($report, 'late_count')); ?>,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
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
                    }
                }
            });
            </script>
        <?php endif; ?>
    </div>
</div>

<!-- Export Options Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ส่งออกรายงาน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>เลือกรูปแบบไฟล์ที่ต้องการส่งออก</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-success" onclick="doExport('excel')">
                        <i class="fas fa-file-excel me-2"></i>Microsoft Excel (.xlsx)
                    </button>
                    <button class="btn btn-danger" onclick="doExport('csv')">
                        <i class="fas fa-file-csv me-2"></i>CSV (.csv)
                    </button>
                    <button class="btn btn-secondary" onclick="doExport('pdf')">
                        <i class="fas fa-file-pdf me-2"></i>PDF (.pdf)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchReport(type) {
    document.getElementById('reportType').value = type;
    document.getElementById('reportForm').submit();
}

function exportReport() {
    const modal = new bootstrap.Modal(document.getElementById('exportModal'));
    modal.show();
}

function doExport(format) {
    const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
    modal.hide();
    
    showLoading();
    
    const params = new URLSearchParams({
        type: '<?php echo $type; ?>',
        format: format,
        date: '<?php echo $date; ?>',
        month: '<?php echo $month; ?>',
        department_id: '<?php echo $department_id; ?>'
    });
    
    window.location.href = '/attendance/export?' + params.toString();
    
    setTimeout(() => {
        hideLoading();
    }, 2000);
}

// Print friendly styles
@media print {
    .filter-section,
    .nav-tabs,
    .btn,
    .modal {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .table {
        border-collapse: collapse !important;
    }
    
    .table td,
    .table th {
        background-color: #fff !important;
        color: #000 !important;
    }
}
</script>