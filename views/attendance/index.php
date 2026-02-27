<?php
$title = 'บันทึกเวลา';
?>

<div class="page-title">
    <div>
        <h2><i class="fas fa-clock me-2"></i>บันทึกเวลา</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard">หน้าแรก</a></li>
                <li class="breadcrumb-item active">บันทึกเวลา</li>
            </ol>
        </nav>
    </div>
    <div>
        <?php if (Auth::hasLevel(POSITION_SUPERVISOR)): ?>
        <button class="btn btn-primary me-2" onclick="window.location.href='/attendance/manual'">
            <i class="fas fa-plus-circle me-2"></i>บันทึกด้วยตนเอง
        </button>
        <button class="btn btn-success me-2" onclick="exportData()">
            <i class="fas fa-file-excel me-2"></i>ส่งออก
        </button>
        <?php endif; ?>
        <button class="btn btn-outline-secondary" onclick="printPage()">
            <i class="fas fa-print me-2"></i>พิมพ์
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card success">
            <div class="stat-value"><?php echo $summary['present_today'] ?? 0; ?></div>
            <div class="stat-label">มาทำงานวันนี้</div>
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <div class="stat-value"><?php echo $summary['late_today'] ?? 0; ?></div>
            <div class="stat-label">มาสายวันนี้</div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card danger">
            <div class="stat-value"><?php echo $summary['absent_today'] ?? 0; ?></div>
            <div class="stat-label">ขาดงานวันนี้</div>
            <div class="stat-icon"><i class="fas fa-user-times"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <div class="stat-value"><?php echo $summary['attendance_rate'] ?? 0; ?>%</div>
            <div class="stat-label">อัตราการเข้า</div>
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <form method="GET" action="/attendance" id="filterForm">
        <div class="row">
            <div class="col-md-3">
                <label class="form-label">จากวันที่</label>
                <input type="date" name="date_from" class="form-control" 
                       value="<?php echo $filters['date_from']; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">ถึงวันที่</label>
                <input type="date" name="date_to" class="form-control" 
                       value="<?php echo $filters['date_to']; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">แผนก</label>
                <select name="department_id" class="form-select">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>" 
                        <?php echo $filters['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                        <?php echo $dept['name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">กะ</label>
                <select name="shift_id" class="form-select">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($shifts as $id => $name): ?>
                    <option value="<?php echo $id; ?>" 
                        <?php echo $filters['shift_id'] == $id ? 'selected' : ''; ?>>
                        <?php echo $name; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">สถานะ</label>
                <select name="status" class="form-select">
                    <option value="">ทั้งหมด</option>
                    <option value="on_time" <?php echo $filters['status'] == 'on_time' ? 'selected' : ''; ?>>ตรงเวลา</option>
                    <option value="late" <?php echo $filters['status'] == 'late' ? 'selected' : ''; ?>>สาย</option>
                    <option value="absent" <?php echo $filters['status'] == 'absent' ? 'selected' : ''; ?>>ขาด</option>
                </select>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <label class="form-label">ค้นหา</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="ชื่อ, รหัสพนักงาน..." 
                       value="<?php echo $filters['search'] ?? ''; ?>">
            </div>
            <div class="col-md-8 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-2"></i>ค้นหา
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                    <i class="fas fa-undo me-2"></i>ล้าง
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Attendance Table -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover" id="attendanceTable">
            <thead>
                <tr>
                    <th>รหัสพนักงาน</th>
                    <th>ชื่อ-สกุล</th>
                    <th>แผนก</th>
                    <th>วันที่</th>
                    <th>กะ</th>
                    <th>เวลาเข้า</th>
                    <th>เวลาออก</th>
                    <th>ชั่วโมง</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendance)): ?>
                <tr>
                    <td colspan="10" class="text-center py-4">
                        <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                        <p class="text-muted">ไม่พบข้อมูล</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($attendance as $record): ?>
                    <tr>
                        <td><?php echo formatUserId($record['user_id']); ?></td>
                        <td><?php echo $record['employee_name']; ?></td>
                        <td><?php echo $record['department_name'] ?? '-'; ?></td>
                        <td><?php echo thaiDate($record['date'], 'short'); ?></td>
                        <td><?php echo $record['shift_name'] ?? '-'; ?></td>
                        <td><?php echo $record['check_in_formatted']; ?></td>
                        <td><?php echo $record['check_out_formatted']; ?></td>
                        <td><?php echo $record['work_hours'] ?? '-'; ?></td>
                        <td>
                            <?php
                            if (!$record['check_in_time']) {
                                echo '<span class="badge bg-danger">ขาด</span>';
                            } elseif ($record['check_in_status'] == 'late') {
                                echo '<span class="badge bg-warning">สาย</span>';
                            } elseif ($record['overtime_hours'] > 0) {
                                echo '<span class="badge bg-info">OT</span>';
                            } else {
                                echo '<span class="badge bg-success">ปกติ</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="/attendance/view?id=<?php echo $record['id']; ?>" 
                               class="btn btn-sm btn-icon btn-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if (Auth::hasLevel(POSITION_SUPERVISOR)): ?>
                            <a href="/attendance/edit?id=<?php echo $record['id']; ?>" 
                               class="btn btn-sm btn-icon btn-warning">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $pagination['current_page'] <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?>&<?php echo http_build_query($filters); ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
            
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <?php if ($i == 1 || $i == $pagination['total_pages'] || ($i >= $pagination['current_page'] - 2 && $i <= $pagination['current_page'] + 2)): ?>
                <li class="page-item <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php elseif ($i == $pagination['current_page'] - 3 || $i == $pagination['current_page'] + 3): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
            <?php endfor; ?>
            
            <li class="page-item <?php echo $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?>&<?php echo http_build_query($filters); ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<script>
function resetFilters() {
    window.location.href = '/attendance';
}

function exportData() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = '/attendance/export?' + params.toString();
}

// Process attendance (for supervisors)
<?php if (Auth::hasLevel(POSITION_SUPERVISOR)): ?>
function processAttendance() {
    if (!confirm('ต้องการประมวลผลข้อมูลการเข้างานวันนี้หรือไม่?')) {
        return;
    }
    
    showLoading();
    
    $.post('/attendance/process', {
        date: '<?php echo date('Y-m-d'); ?>',
        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
    }, function(response) {
        hideLoading();
        showToast('ประมวลผลข้อมูลเรียบร้อย', 'success');
        setTimeout(() => {
            location.reload();
        }, 1500);
    }).fail(function() {
        hideLoading();
        showToast('เกิดข้อผิดพลาด', 'error');
    });
}
<?php endif; ?>
</script>