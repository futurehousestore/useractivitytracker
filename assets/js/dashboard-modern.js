/**
 * Modern Dashboard JavaScript for User Activity Tracker
 * Version: 2.0.0
 * Features: Dark mode, AJAX refresh, charts, pagination, and more
 */

class UserActivityDashboard {
    constructor() {
        this.charts = {};
        this.refreshInterval = null;
        this.currentPage = 1;
        this.itemsPerPage = 10;
        
        this.init();
    }
    
    init() {
        this.setupThemeToggle();
        this.setupAutoRefresh();
        this.setupCharts();
        this.setupPagination();
        this.setupFilters();
        this.setupExport();
        this.loadSavedPreferences();
    }
    
    // Theme Management
    setupThemeToggle() {
        const toggle = document.getElementById('themeToggle');
        if (!toggle) return;
        
        toggle.addEventListener('click', () => {
            this.toggleTheme();
        });
        
        // Set initial theme
        const savedTheme = localStorage.getItem('uat-theme') || 'light';
        this.setTheme(savedTheme);
    }
    
    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    }
    
    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('uat-theme', theme);
        
        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            }
        }
        
        // Update charts for theme
        if (Object.keys(this.charts).length > 0) {
            setTimeout(() => this.updateChartsForTheme(theme), 100);
        }
    }
    
    // Auto Refresh
    setupAutoRefresh() {
        const refreshBtn = document.getElementById('refreshData');
        const autoRefreshToggle = document.getElementById('autoRefresh');
        
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.refreshData();
            });
        }
        
        if (autoRefreshToggle) {
            autoRefreshToggle.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.startAutoRefresh();
                } else {
                    this.stopAutoRefresh();
                }
            });
        }
    }
    
    startAutoRefresh(interval = 30000) { // 30 seconds
        this.stopAutoRefresh();
        this.refreshInterval = setInterval(() => {
            this.refreshData();
        }, interval);
    }
    
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
    
    async refreshData() {
        const refreshBtn = document.getElementById('refreshData');
        if (refreshBtn) {
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            refreshBtn.disabled = true;
        }
        
        try {
            const formData = new FormData(document.querySelector('.filter-panel form'));
            const params = new URLSearchParams(formData);
            
            const response = await fetch(`${window.location.pathname}?ajax=1&${params.toString()}`);
            const data = await response.json();
            
            this.updateDashboard(data);
            this.showNotification('Dashboard updated successfully', 'success');
        } catch (error) {
            console.error('Refresh failed:', error);
            this.showNotification('Failed to refresh dashboard', 'error');
        } finally {
            if (refreshBtn) {
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                refreshBtn.disabled = false;
            }
        }
    }
    
    updateDashboard(data) {
        // Update statistics cards
        if (data.stats) {
            this.updateStatsCards(data.stats);
        }
        
        // Update charts
        if (data.chartData) {
            this.updateCharts(data.chartData);
        }
        
        // Update recent activities
        if (data.recentActivities) {
            this.updateRecentActivities(data.recentActivities);
        }
    }
    
    updateStatsCards(stats) {
        const statElements = {
            'totalActivities': stats.total,
            'uniqueActions': stats.uniqueActions,
            'activeUsers': stats.activeUsers,
            'dateRange': stats.dateRange
        };
        
        Object.entries(statElements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
                element.closest('.stat-card')?.classList.add('fade-in');
            }
        });
    }
    
    // Charts Setup
    setupCharts() {
        this.setupActivityTypeChart();
        this.setupUserActivityChart();
        this.setupTimelineChart();
        this.setupHourlyChart();
    }
    
    setupActivityTypeChart() {
        const ctx = document.getElementById('activityTypeChart');
        if (!ctx) return;
        
        const data = this.getChartData('activityType');
        
        this.charts.activityType = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: [
                        '#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8',
                        '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6c757d'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    setupUserActivityChart() {
        const ctx = document.getElementById('userActivityChart');
        if (!ctx) return;
        
        const data = this.getChartData('userActivity');
        
        this.charts.userActivity = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Activities',
                    data: data.values,
                    backgroundColor: 'rgba(0, 123, 255, 0.8)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    setupTimelineChart() {
        const ctx = document.getElementById('timelineChart');
        if (!ctx) return;
        
        const data = this.getChartData('timeline');
        
        this.charts.timeline = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Daily Activity',
                    data: data.values,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    setupHourlyChart() {
        const ctx = document.getElementById('hourlyChart');
        if (!ctx) return;
        
        const data = this.getChartData('hourly');
        
        this.charts.hourly = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Activities by Hour',
                    data: data.values,
                    backgroundColor: 'rgba(23, 162, 184, 0.8)',
                    borderColor: 'rgba(23, 162, 184, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    getChartData(type) {
        // This would normally come from server data
        // For now, we'll extract from existing DOM elements
        switch (type) {
            case 'activityType':
                return this.extractTableData('.activity-type-table');
            case 'userActivity':
                return this.extractTableData('.user-activity-table');
            case 'timeline':
                return this.extractTableData('.timeline-table');
            case 'hourly':
                return this.extractHourlyData();
            default:
                return { labels: [], values: [] };
        }
    }
    
    extractTableData(selector) {
        const table = document.querySelector(selector);
        if (!table) return { labels: [], values: [] };
        
        const labels = [];
        const values = [];
        
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 2) {
                labels.push(cells[0].textContent.trim());
                values.push(parseInt(cells[1].textContent.trim()) || 0);
            }
        });
        
        return { labels, values };
    }
    
    extractHourlyData() {
        // Generate 24-hour labels and random data for demo
        const labels = [];
        const values = [];
        
        for (let i = 0; i < 24; i++) {
            labels.push(`${i.toString().padStart(2, '0')}:00`);
            values.push(Math.floor(Math.random() * 100));
        }
        
        return { labels, values };
    }
    
    updateChartsForTheme(theme) {
        const textColor = theme === 'dark' ? '#ffffff' : '#666';
        const gridColor = theme === 'dark' ? '#404040' : '#e5e5e5';
        
        Object.values(this.charts).forEach(chart => {
            if (chart.options.scales) {
                if (chart.options.scales.x) {
                    chart.options.scales.x.ticks.color = textColor;
                    chart.options.scales.x.grid.color = gridColor;
                }
                if (chart.options.scales.y) {
                    chart.options.scales.y.ticks.color = textColor;
                    chart.options.scales.y.grid.color = gridColor;
                }
            }
            if (chart.options.plugins && chart.options.plugins.legend) {
                chart.options.plugins.legend.labels.color = textColor;
            }
            chart.update();
        });
    }
    
    // Pagination
    setupPagination() {
        this.updatePagination();
    }
    
    updatePagination() {
        const recentActivities = document.querySelector('.recent-activities-container');
        if (!recentActivities) return;
        
        const items = recentActivities.querySelectorAll('.activity-item');
        const totalPages = Math.ceil(items.length / this.itemsPerPage);
        
        this.showPage(this.currentPage);
        this.renderPaginationControls(totalPages);
    }
    
    showPage(page) {
        const items = document.querySelectorAll('.activity-item');
        const startIndex = (page - 1) * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        
        items.forEach((item, index) => {
            if (index >= startIndex && index < endIndex) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
        
        this.currentPage = page;
    }
    
    renderPaginationControls(totalPages) {
        const container = document.getElementById('paginationControls');
        if (!container || totalPages <= 1) return;
        
        let html = '<div class="pagination-container">';
        html += '<div class="pagination-info">';
        html += `Showing page ${this.currentPage} of ${totalPages}`;
        html += '</div>';
        html += '<ul class="pagination">';
        
        // Previous button
        html += `<li><a href="#" onclick="dashboard.goToPage(${this.currentPage - 1})" 
                 ${this.currentPage === 1 ? 'style="pointer-events:none;opacity:0.5"' : ''}>
                 <i class="fas fa-chevron-left"></i></a></li>`;
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === this.currentPage) {
                html += `<li><span class="active">${i}</span></li>`;
            } else {
                html += `<li><a href="#" onclick="dashboard.goToPage(${i})">${i}</a></li>`;
            }
        }
        
        // Next button
        html += `<li><a href="#" onclick="dashboard.goToPage(${this.currentPage + 1})"
                 ${this.currentPage === totalPages ? 'style="pointer-events:none;opacity:0.5"' : ''}>
                 <i class="fas fa-chevron-right"></i></a></li>`;
        
        html += '</ul></div>';
        container.innerHTML = html;
    }
    
    goToPage(page) {
        const totalPages = Math.ceil(document.querySelectorAll('.activity-item').length / this.itemsPerPage);
        if (page >= 1 && page <= totalPages) {
            this.showPage(page);
            this.renderPaginationControls(totalPages);
        }
    }
    
    // Filters
    setupFilters() {
        const filterForm = document.querySelector('.filter-panel form');
        if (!filterForm) return;
        
        // Add element type filter
        this.addElementTypeFilter();
        
        // Setup advanced search
        this.setupAdvancedSearch();
        
        // Auto-submit on filter change
        const inputs = filterForm.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                if (document.getElementById('liveFilter')?.checked) {
                    filterForm.submit();
                }
            });
        });
    }
    
    addElementTypeFilter() {
        const filterRow = document.querySelector('.filter-row');
        if (!filterRow) return;
        
        // Check if element type filter already exists
        if (document.querySelector('[name="search_element_type"]')) return;
        
        const elementTypes = this.extractElementTypes();
        if (elementTypes.length === 0) return;
        
        const filterGroup = document.createElement('div');
        filterGroup.className = 'filter-group';
        filterGroup.innerHTML = `
            <label for="search_element_type">Element Type</label>
            <select name="search_element_type" id="search_element_type">
                <option value="">All Types</option>
                ${elementTypes.map(type => `<option value="${type}">${type}</option>`).join('')}
            </select>
        `;
        
        filterRow.appendChild(filterGroup);
    }
    
    extractElementTypes() {
        const types = new Set();
        const rows = document.querySelectorAll('.element-type-table tbody tr');
        rows.forEach(row => {
            const cell = row.querySelector('td');
            if (cell) {
                types.add(cell.textContent.trim());
            }
        });
        return Array.from(types);
    }
    
    setupAdvancedSearch() {
        const advancedToggle = document.getElementById('advancedSearchToggle');
        const advancedPanel = document.getElementById('advancedSearchPanel');
        
        if (advancedToggle && advancedPanel) {
            advancedToggle.addEventListener('click', () => {
                const isHidden = advancedPanel.style.display === 'none';
                advancedPanel.style.display = isHidden ? 'block' : 'none';
                advancedToggle.innerHTML = isHidden ? 
                    '<i class="fas fa-chevron-up"></i> Hide Advanced' : 
                    '<i class="fas fa-chevron-down"></i> Show Advanced';
            });
        }
    }
    
    // Export functionality
    setupExport() {
        const exportBtn = document.getElementById('exportPDF');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportToPDF();
            });
        }
    }
    
    exportToPDF() {
        // This would integrate with a PDF library like jsPDF
        this.showNotification('PDF export feature coming soon', 'info');
    }
    
    // User preferences
    loadSavedPreferences() {
        const savedPrefs = localStorage.getItem('uat-preferences');
        if (savedPrefs) {
            const prefs = JSON.parse(savedPrefs);
            
            // Apply saved preferences
            if (prefs.autoRefresh) {
                const toggle = document.getElementById('autoRefresh');
                if (toggle) {
                    toggle.checked = true;
                    this.startAutoRefresh();
                }
            }
            
            if (prefs.itemsPerPage) {
                this.itemsPerPage = prefs.itemsPerPage;
            }
        }
    }
    
    savePreferences() {
        const prefs = {
            theme: localStorage.getItem('uat-theme'),
            autoRefresh: document.getElementById('autoRefresh')?.checked || false,
            itemsPerPage: this.itemsPerPage
        };
        
        localStorage.setItem('uat-preferences', JSON.stringify(prefs));
    }
    
    // Utility functions
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type} fade-in`;
        notification.innerHTML = `
            <i class="fas fa-${this.getNotificationIcon(type)}"></i>
            ${message}
            <button onclick="this.parentElement.remove()" class="close-btn">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Style the notification
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            padding: 1rem 1.5rem;
            border-radius: 0.375rem;
            color: white;
            min-width: 300px;
            text-align: center;
            background: ${this.getNotificationColor(type)};
            box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.25);
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    
    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    getNotificationColor(type) {
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            warning: '#ffc107',
            info: '#17a2b8'
        };
        return colors[type] || '#17a2b8';
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new UserActivityDashboard();
});

// Save preferences before page unload
window.addEventListener('beforeunload', () => {
    if (window.dashboard) {
        window.dashboard.savePreferences();
    }
});