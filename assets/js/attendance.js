/**
 * attendance.js - จัดการฟังก์ชันเกี่ยวกับการเข้าออกงาน
 */

class AttendanceManager {
    constructor() {
        this.apiBase = '/api/attendance';
        this.currentPage = 1;
        this.filters = {
            department: '',
            employee: '',
            date: '',
            shift: ''
        };
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadAttendanceData();
    }
    
    bindEvents() {
        // ฟอร์มค้นหา
        $('#searchForm').on('submit', (e) => {
            e.preventDefault();
            this.filters = {
                department: $('#departmentFilter').val(),
                employee: $('#employeeFilter').val(),
                date: $('#dateFilter').val(),
                shift: $('#shiftFilter').val()
            };
            this.currentPage = 1;
            this.loadAttendanceData();
        });
        
        // ปุ่มรีเซ็ต
        $('#resetFilters').on('click', () => {
            this.resetFilters();
        });
        
        // ปุ่มส่งออก Excel
        $('#exportExcel').on('click', () => {
            this.exportToExcel();
        });
        
        // ปุ่มประมวลผลข้อมูล
        $('#processAttendance').on('click', () => {
            this.processAttendance();
        });
        
        // เปลี่ยนหน้า
        $(document).on('click', '.page-link', (e) => {
            e.preventDefault();
            const page = $(e.target).data('page');
            if (page) {
                this.currentPage = page;
                this.loadAttendanceData();
            }
        });
    }
    
    /**
     * โหลดข้อมูลการเข้างาน
     */
    loadAttendanceData() {
        this.showLoading();
        
        $.ajax({
            url: `${this.apiBase}?page=${this.currentPage}`,
            method: 'POST',
            data: this.filters,
            dataType: 'json',
            success: (response) => {
                this.renderTable(response.data);
                this.renderPagination(response.pagination);
                this.updateSummary(response.summary);
            },
            error: (xhr, status, error) => {
                this.showError('ไม่สามารถโหลดข้อมูลได้');
                console.error('Error:', error);
            },
            complete: () => {
                this.hideLoading();
            }
        });
    }
    
    /**
     * แสดงตารางข้อมูล
     */
    renderTable(data) {
        const tbody = $('#attendanceTable tbody');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.append('<tr><td colspan="8" class="text-center">ไม่พบข้อมูล</td></tr>');
            return;
        }
        
        data.forEach(item => {
            const row = `
                <tr>
                    <td>${this.formatUserId(item.user_id)}</td>
                    <td>${item.employee_name}</td>
                    <td>${item.department}</td>
                    <td>${item.shift}</td>
                    <td>${item.date}</td>
                    <td>${item.check_in || '-'}</td>
                    <td>${item.check_out || '-'}</td>
                    <td>
                        <span class="badge-status ${this.getStatusClass(item.status)}">
                            ${this.getStatusText(item.status)}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-icon btn-primary" onclick="attendance.viewDetail(${item.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }
    
    /**
     * แสดง pagination
     */
    renderPagination(pagination) {
        const container = $('#pagination');
        container.empty();
        
        if (pagination.totalPages <= 1) return;
        
        let html = '<ul class="pagination">';
        
        // Previous button
        html += `
            <li class="page-item ${pagination.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.currentPage - 1}">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;
        
        // Page numbers
        for (let i = 1; i <= pagination.totalPages; i++) {
            if (i === 1 || i === pagination.totalPages || 
                (i >= pagination.currentPage - 2 && i <= pagination.currentPage + 2)) {
                html += `
                    <li class="page-item ${i === pagination.currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            } else if (i === pagination.currentPage - 3 || i === pagination.currentPage + 3) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        // Next button
        html += `
            <li class="page-item ${pagination.currentPage === pagination.totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.currentPage + 1}">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;
        
        html += '</ul>';
        container.append(html);
    }
    
    /**
     * อัปเดตสรุปข้อมูล
     */
    updateSummary(summary) {
        $('#totalEmployees').text(summary.totalEmployees);
        $('#presentToday').text(summary.presentToday);
        $('#absentToday').text(summary.absentToday);
        $('#lateToday').text(summary.lateToday);
    }
    
    /**
     * ดูรายละเอียด
     */
    viewDetail(id) {
        $.ajax({
            url: `${this.apiBase}/detail/${id}`,
            method: 'GET',
            success: (data) => {
                this.showDetailModal(data);
            },
            error: () => {
                this.showError('ไม่สามารถโหลดรายละเอียดได้');
            }
        });
    }
    
    /**
     * แสดง modal รายละเอียด
     */
    showDetailModal(data) {
        const modal = $('#detailModal');
        
        modal.find('.modal-title').text(`รายละเอียดการเข้างาน: ${this.formatUserId(data.user_id)}`);
        modal.find('.employee-name').text(data.employee_name);
        modal.find('.employee-department').text(data.department);
        modal.find('.employee-position').text(data.position);
        modal.find('.shift-name').text(data.shift);
        modal.find('.check-in').text(data.check_in || '-');
        modal.find('.check-out').text(data.check_out || '-');
        modal.find('.work-hours').text(data.work_hours || '-');
        modal.find('.late-minutes').text(data.late_minutes || '0');
        modal.find('.status').html(`<span class="badge-status ${this.getStatusClass(data.status)}">${this.getStatusText(data.status)}</span>`);
        
        modal.modal('show');
    }
    
    /**
     * ส่งออก Excel
     */
    exportToExcel() {
        this.showLoading();
        
        $.ajax({
            url: `${this.apiBase}/export`,
            method: 'POST',
            data: this.filters,
            xhrFields: {
                responseType: 'blob'
            },
            success: (blob) => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `attendance_${new Date().toISOString().slice(0,10)}.xlsx`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            },
            error: () => {
                this.showError('ไม่สามารถส่งออกข้อมูลได้');
            },
            complete: () => {
                this.hideLoading();
            }
        });
    }
    
    /**
     * ประมวลผลข้อมูลการเข้างาน
     */
    processAttendance() {
        if (!confirm('ต้องการประมวลผลข้อมูลการเข้างานวันนี้หรือไม่?')) {
            return;
        }
        
        this.showLoading();
        
        $.ajax({
            url: `${this.apiBase}/process`,
            method: 'POST',
            data: { date: $('#processDate').val() || new Date().toISOString().slice(0,10) },
            success: (response) => {
                this.showSuccess('ประมวลผลข้อมูลสำเร็จ');
                this.loadAttendanceData();
            },
            error: () => {
                this.showError('ไม่สามารถประมวลผลข้อมูลได้');
            },
            complete: () => {
                this.hideLoading();
            }
        });
    }
    
    /**
     * รีเซ็ตตัวกรอง
     */
    resetFilters() {
        this.filters = {
            department: '',
            employee: '',
            date: '',
            shift: ''
        };
        
        $('#departmentFilter').val('');
        $('#employeeFilter').val('');
        $('#dateFilter').val('');
        $('#shiftFilter').val('');
        
        this.currentPage = 1;
        this.loadAttendanceData();
    }
    
    /**
     * จัดรูปแบบ user_id
     */
    formatUserId(userId) {
        const prefix = 'MRT';
        const padded = userId.toString().padStart(3, '0');
        return `${prefix}${padded}`;
    }
    
    /**
     * รูปแบบ status class
     */
    getStatusClass(status) {
        const classes = {
            'present': 'present',
            'absent': 'absent',
            'late': 'late',
            'overtime': 'overtime',
            'leave': 'leave'
        };
        return classes[status] || '';
    }
    
    /**
     * แสดงข้อความ status
     */
    getStatusText(status) {
        const texts = {
            'present': 'มาแล้ว',
            'absent': 'ขาดงาน',
            'late': 'สาย',
            'overtime': 'โอที',
            'leave': 'ลา'
        };
        return texts[status] || status;
    }
    
    /**
     * แสดง loading
     */
    showLoading() {
        $('.spinner-wrapper').addClass('show');
    }
    
    /**
     * ซ่อน loading
     */
    hideLoading() {
        $('.spinner-wrapper').removeClass('show');
    }
    
    /**
     * แสดง success message
     */
    showSuccess(message) {
        this.showToast(message, 'success');
    }
    
    /**
     * แสดง error message
     */
    showError(message) {
        this.showToast(message, 'error');
    }
    
    /**
     * แสดง toast notification
     */
    showToast(message, type = 'info') {
        const toast = $(`<div class="toast-notification ${type}">${message}</div>`);
        $('body').append(toast);
        
        setTimeout(() => {
            toast.addClass('show');
        }, 100);
        
        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3000);
    }
}

// Initialize
$(document).ready(() => {
    window.attendance = new AttendanceManager();
});