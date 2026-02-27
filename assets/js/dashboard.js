/**
 * dashboard.js - จัดการ Dashboard
 */

class DashboardManager {
    constructor() {
        this.apiBase = '/api/dashboard';
        this.charts = {};
        this.refreshInterval = 300000; // 5 นาที
        this.init();
    }
    
    init() {
        this.loadDashboardData();
        this.bindEvents();
        this.startAutoRefresh();
    }
    
    bindEvents() {
        // ปุ่มรีเฟรช
        $('#refreshDashboard').on('click', () => {
            this.loadDashboardData();
        });
        
        // เปลี่ยนแผนก
        $('#departmentFilter').on('change', () => {
            this.loadDashboardData();
        });
        
        // เปลี่ยนวันที่
        $('#dateFilter').on('change', () => {
            this.loadDashboardData();
        });
    }
    
    /**
     * โหลดข้อมูล Dashboard
     */
    loadDashboardData() {
        this.showLoading();
        
        const filters = {
            department: $('#departmentFilter').val(),
            date: $('#dateFilter').val() || new Date().toISOString().slice(0,10)
        };
        
        $.ajax({
            url: this.apiBase,
            method: 'GET',
            data: filters,
            dataType: 'json',
            success: (response) => {
                this.updateStats(response.stats);
                this.updateCharts(response.charts);
                this.updateDepartmentList(response.departments);
                this.updateRecentActivity(response.recentActivity);
                this.updateLastUpdate();
            },
            error: (xhr, status, error) => {
                this.showError('ไม่สามารถโหลดข้อมูล Dashboard ได้');
                console.error('Dashboard error:', error);
            },
            complete: () => {
                this.hideLoading();
            }
        });
    }
    
    /**
     * อัปเดตสถิติ
     */
    updateStats(stats) {
        $('#totalEmployees').text(stats.totalEmployees);
        $('#presentToday').text(stats.presentToday);
        $('#absentToday').text(stats.absentToday);
        $('#lateToday').text(stats.lateToday);
        $('#onLeave').text(stats.onLeave);
        $('#overtime').text(stats.overtime);
        
        // อัปเดตเปอร์เซ็นต์
        const attendanceRate = (stats.presentToday / stats.totalEmployees * 100).toFixed(1);
        $('#attendanceRate').text(attendanceRate + '%');
        $('#attendanceRateBar').css('width', attendanceRate + '%');
    }
    
    /**
     * อัปเดต Charts
     */
    updateCharts(charts) {
        this.updateAttendanceChart(charts.attendanceByHour);
        this.updateDepartmentChart(charts.byDepartment);
        this.updateShiftChart(charts.byShift);
    }
    
    /**
     * กราฟการเข้างานรายชั่วโมง
     */
    updateAttendanceChart(data) {
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        
        if (this.charts.attendance) {
            this.charts.attendance.destroy();
        }
        
        this.charts.attendance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'เข้างาน',
                        data: data.checkIn,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'เลิกงาน',
                        data: data.checkOut,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
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
    }
    
    /**
     * กราฟแยกตามแผนก
     */
    updateDepartmentChart(data) {
        const ctx = document.getElementById('departmentChart').getContext('2d');
        
        if (this.charts.department) {
            this.charts.department.destroy();
        }
        
        this.charts.department = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: [
                        '#0d6efd',
                        '#198754',
                        '#ffc107',
                        '#dc3545',
                        '#6c757d',
                        '#0dcaf0',
                        '#6610f2',
                        '#d63384'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                }
            }
        });
    }
    
    /**
     * กราฟแยกตามกะ
     */
    updateShiftChart(data) {
        const ctx = document.getElementById('shiftChart').getContext('2d');
        
        if (this.charts.shift) {
            this.charts.shift.destroy();
        }
        
        this.charts.shift = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['กะเช้า (08:00-17:30)', 'กะดึก (20:00-05:30)'],
                datasets: [{
                    data: [data.morning, data.night],
                    backgroundColor: ['#0d6efd', '#6610f2']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    
    /**
     * อัปเดตรายการแผนก
     */
    updateDepartmentList(departments) {
        const container = $('#departmentList');
        container.empty();
        
        departments.forEach(dept => {
            const rate = (dept.present / dept.total * 100).toFixed(1);
            const row = `
                <tr>
                    <td>${dept.name}</td>
                    <td>${dept.total}</td>
                    <td>${dept.present}</td>
                    <td>${dept.absent}</td>
                    <td>${dept.late}</td>
                    <td>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: ${rate}%"></div>
                        </div>
                        <small class="text-muted">${rate}%</small>
                    </td>
                </tr>
            `;
            container.append(row);
        });
    }
    
    /**
     * อัปเดตกิจกรรมล่าสุด
     */
    updateRecentActivity(activities) {
        const container = $('#recentActivity');
        container.empty();
        
        activities.forEach(activity => {
            const time = new Date(activity.time).toLocaleTimeString('th-TH');
            const icon = this.getActivityIcon(activity.type);
            const statusClass = this.getStatusClass(activity.status);
            
            const item = `
                <div class="activity-item">
                    <div class="activity-icon ${statusClass}">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="activity-details">
                        <div class="activity-user">${this.formatUserId(activity.user_id)} - ${activity.name}</div>
                        <div class="activity-time">${time}</div>
                        <span class="badge-status ${statusClass}">${activity.status}</span>
                    </div>
                </div>
            `;
            container.append(item);
        });
    }
    
    /**
     * รูปแบบไอคอนตามประเภท
     */
    getActivityIcon(type) {
        const icons = {
            'check_in': 'fa-sign-in-alt',
            'check_out': 'fa-sign-out-alt',
            'late': 'fa-clock',
            'overtime': 'fa-clock'
        };
        return icons[type] || 'fa-circle';
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
            'check_in': 'present',
            'check_out': 'present',
            'late': 'late',
            'overtime': 'overtime',
            'absent': 'absent'
        };
        return classes[status] || '';
    }
    
    /**
     * อัปเดตเวลาล่าสุด
     */
    updateLastUpdate() {
        const now = new Date();
        $('#lastUpdate').text(now.toLocaleTimeString('th-TH'));
    }
    
    /**
     * เริ่ม Auto Refresh
     */
    startAutoRefresh() {
        setInterval(() => {
            this.loadDashboardData();
        }, this.refreshInterval);
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
     * แสดง error
     */
    showError(message) {
        const toast = $(`<div class="toast-notification error">${message}</div>`);
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
    window.dashboard = new DashboardManager();
});