    </div> <!-- /.content -->
</div> <!-- /.main-content -->

<script>
// Toggle sidebar
document.getElementById('toggleSidebar').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainContent').classList.toggle('expanded');
});

// Show loading spinner
function showLoading() {
    document.getElementById('loadingSpinner').classList.add('show');
}

// Hide loading spinner
function hideLoading() {
    document.getElementById('loadingSpinner').classList.remove('show');
}

// Show toast notification
function showToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas ${getToastIcon(type)} me-3 fa-lg"></i>
            <div>${message}</div>
        </div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            container.removeChild(toast);
        }, 300);
    }, duration);
}

function getToastIcon(type) {
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    return icons[type] || icons.info;
}

// Format user ID
function formatUserId(userId) {
    return 'MRT' + userId.toString().padStart(3, '0');
}

// Format date to Thai format
function formatThaiDate(date) {
    if (!date) return '-';
    const d = new Date(date);
    return d.toLocaleDateString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Format time
function formatTime(date) {
    if (!date) return '-';
    const d = new Date(date);
    return d.toLocaleTimeString('th-TH', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Format datetime
function formatDateTime(date) {
    if (!date) return '-';
    const d = new Date(date);
    return d.toLocaleString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Get status badge HTML
function getStatusBadge(status, type = 'attendance') {
    const badges = {
        attendance: {
            present: '<span class="badge-status present"><i class="fas fa-check-circle me-1"></i>มาแล้ว</span>',
            absent: '<span class="badge-status absent"><i class="fas fa-times-circle me-1"></i>ขาด</span>',
            late: '<span class="badge-status late"><i class="fas fa-clock me-1"></i>สาย</span>',
            leave: '<span class="badge-status leave"><i class="fas fa-calendar-minus me-1"></i>ลา</span>',
            overtime: '<span class="badge-status overtime"><i class="fas fa-clock me-1"></i>OT</span>'
        },
        employee: {
            active: '<span class="badge bg-success">กำลังทำงาน</span>',
            inactive: '<span class="badge bg-secondary">ออกแล้ว</span>'
        }
    };
    
    return badges[type][status] || status;
}

// Handle AJAX errors
$(document).ajaxError(function(event, jqXHR, settings, error) {
    let message = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
    
    if (jqXHR.status === 401) {
        message = 'กรุณาเข้าสู่ระบบอีกครั้ง';
        setTimeout(() => {
            window.location.href = '/login';
        }, 2000);
    } else if (jqXHR.status === 403) {
        message = 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้';
    } else if (jqXHR.status === 404) {
        message = 'ไม่พบข้อมูลที่ร้องขอ';
    } else if (jqXHR.status === 500) {
        message = 'เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์';
    }
    
    showToast(message, 'error');
});

// Auto refresh every 5 minutes (for dashboard)
let refreshInterval;
function startAutoRefresh(callback, interval = 300000) {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    refreshInterval = setInterval(callback, interval);
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
}

// Print current page
function printPage() {
    window.print();
}

// Export to CSV
function exportToCSV(data, filename) {
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

// Confirm action
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.remove();
        }, 500);
    });
}, 5000);
</script>

<!-- Custom Scripts -->
<script src="<?php echo asset('js/attendance.js'); ?>"></script>
<script src="<?php echo asset('js/dashboard.js'); ?>"></script>

</body>
</html>