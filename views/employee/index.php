<?php
$title = 'จัดการพนักงาน';
?>

<div class="page-title">
    <div>
        <h2><i class="fas fa-users me-2"></i>จัดการพนักงาน</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard">หน้าแรก</a></li>
                <li class="breadcrumb-item active">พนักงาน</li>
            </ol>
        </nav>
    </div>
    <div>
        <?php if (Auth::hasLevel(POSITION_MANAGER)): ?>
        <a href="/employee/create" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>เพิ่มพนักงาน
        </a>
        <?php endif; ?>
        <button class="btn btn-outline-secondary ms-2" onclick="printPage()">
            <i class="fas fa-print me-2"></i>พิมพ์
        </button>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <form method="GET" action="/employee" id="filterForm">
        <div class="row">
            <div class="col-md-3">
                <label class="form-label">แผนก</label>
                <select name="department_id" class="form-select">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>" 
                        <?php echo ($filters['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                        <?php echo $dept['name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">ตำแหน่ง</label>
                <select name="position_id" class="form-select">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($positions as $pos): ?>
                    <option value="<?php echo $pos['id']; ?>" 
                        <?php echo ($filters['position_id'] ?? '') == $pos['id'] ? 'selected' : ''; ?>>
                        <?php echo $pos['name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">สถานะ</label>
                <select name="status" class="form-select">
                    <option value="">ทั้งหมด</option>
                    <option value="1" <?php echo ($filters['status'] ?? '') === '1' ? 'selected' : ''; ?>>กำลังทำงาน</option>
                    <option value="0" <?php echo ($filters['status'] ?? '') === '0' ? 'selected' : ''; ?>>ออกแล้ว</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">ค้นหา</label>
                <div class="input-group">
                    <input type="text" name="search" class="form-control" 
                           placeholder="ชื่อ, นามสกุล, รหัสพนักงาน..." 
                           value="<?php echo $filters['search'] ?? ''; ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Employees Table -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>รหัสพนักงาน</th>
                    <th>ชื่อ-สกุล</th>
                    <th>แผนก</th>
                    <th>ตำแหน่ง</th>
                    <th>เบอร์โทร</th>
                    <th>อีเมล</th>
                    <th>วันที่เริ่มงาน</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <i class="fas fa-users fa-4x mb-3 text-muted"></i>
                        <p class="text-muted">ไม่พบข้อมูลพนักงาน</p>
                        <?php if (Auth::hasLevel(POSITION_MANAGER)): ?>
                        <a href="/employee/create" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>เพิ่มพนักงานแรก
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><strong><?php echo formatUserId($emp['user_id']); ?></strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-2">
                                    <?php echo strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <?php echo $emp['first_name'] . ' ' . $emp['last_name']; ?>
                                    <?php if ($emp['nickname']): ?>
                                    <br><small class="text-muted">(<?php echo $emp['nickname']; ?>)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?php echo $emp['department_name'] ?? '-'; ?></td>
                        <td><?php echo $emp['position_name'] ?? '-'; ?></td>
                        <td><?php echo $emp['phone'] ?? '-'; ?></td>
                        <td><?php echo $emp['email'] ?? '-'; ?></td>
                        <td><?php echo $emp['hire_date'] ? thaiDate($emp['hire_date'], 'short') : '-'; ?></td>
                        <td>
                            <?php if ($emp['status'] == 1): ?>
                            <span class="badge bg-success">กำลังทำงาน</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">ออกแล้ว</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="/employee/profile?id=<?php echo $emp['id']; ?>" 
                                   class="btn btn-sm btn-icon btn-primary" title="ดูข้อมูล">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (Auth::hasLevel(POSITION_SUPERVISOR)): ?>
                                <a href="/employee/edit?id=<?php echo $emp['id']; ?>" 
                                   class="btn btn-sm btn-icon btn-warning" title="แก้ไข">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (Auth::hasLevel(POSITION_MANAGER) && $emp['id'] != $_SESSION['employee_id']): ?>
                                <button type="button" class="btn btn-sm btn-icon btn-danger" 
                                        onclick="deleteEmployee(<?php echo $emp['id']; ?>, '<?php echo formatUserId($emp['user_id']); ?>')"
                                        title="ลบ">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ยืนยันการลบ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>คุณต้องการลบพนักงาน <span id="deleteEmployeeName" class="fw-bold"></span> ใช่หรือไม่?</p>
                <p class="text-danger"><small>การลบพนักงานจะเปลี่ยนสถานะเป็น "ออกแล้ว" เท่านั้น ข้อมูลจะยังคงอยู่ในระบบ</small></p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="/employee/delete" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" id="deleteEmployeeId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger">ลบ</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: linear-gradient(45deg, var(--primary-color), #0b5ed7);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: bold;
}

.btn-group .btn-icon {
    margin: 0 2px;
}
</style>

<script>
function deleteEmployee(id, userId) {
    document.getElementById('deleteEmployeeId').value = id;
    document.getElementById('deleteEmployeeName').textContent = userId;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

// Reset filters
function resetFilters() {
    window.location.href = '/employee';
}

// Export to CSV
function exportToCSV() {
    const employees = <?php echo json_encode($employees); ?>;
    
    const data = employees.map(emp => ({
        'รหัสพนักงาน': '<?php echo USER_ID_PREFIX; ?>' + emp.user_id.padStart(3, '0'),
        'ชื่อ': emp.first_name,
        'นามสกุล': emp.last_name,
        'แผนก': emp.department_name || '-',
        'ตำแหน่ง': emp.position_name || '-',
        'เบอร์โทร': emp.phone || '-',
        'อีเมล': emp.email || '-',
        'วันที่เริ่มงาน': emp.hire_date || '-',
        'สถานะ': emp.status == 1 ? 'กำลังทำงาน' : 'ออกแล้ว'
    }));
    
    exportToCSVFile(data, 'employees_<?php echo date('Ymd'); ?>.csv');
}

function exportToCSVFile(data, filename) {
    const csv = data.map(row => 
        Object.values(row).map(value => 
            typeof value === 'string' ? `"${value}"` : value
        ).join(',')
    ).join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>