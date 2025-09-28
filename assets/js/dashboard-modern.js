/**
 * Modern Dashboard JavaScript for User Activity Tracker
 * Version: 2.3.0
 * Features: Dark mode, AJAX refresh, charts, pagination, sidebar navigation,
 * timeline visualization, PDF export, drag-and-drop widgets, and more
 */

class UserActivityDashboard {
    constructor() {
        this.charts = {};
        this.refreshInterval = null;
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.sidebarOpen = false;
        this.widgetLayout = this.loadWidgetLayout();
        
        this.init();
    }
    
    init() {
        this.setupThemeToggle();
        this.setupAutoRefresh();
        this.setupCharts();
        this.setupPagination();
        this.setupFilters();
        this.setupExport();
        this.setupNavigation();
        this.setupTimeline();
        this.setupDragAndDrop();
        this.setupQuickFilters();
        this.loadSavedPreferences();
        this.initializeAnimations();
    }

    // ============================================
    // ENHANCED NAVIGATION SYSTEM
    // ============================================
    
    setupNavigation() {
        this.setupSidebar();
        this.setupBreadcrumbs();
        this.setupQuickActions();
    }

    setupSidebar() {
        // Create sidebar if it doesn't exist
        if (!document.querySelector('.dashboard-sidebar')) {
            this.createSidebar();
        }

        // Mobile toggle button
        const mobileToggle = document.querySelector('.mobile-nav-toggle') || this.createMobileToggle();
        mobileToggle.addEventListener('click', () => {
            this.toggleSidebar();
        });

        // Sidebar toggle button
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                this.toggleSidebar();
            });
        }

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                this.showSidebar();
            } else {
                this.hideSidebar();
            }
        });
    }

    createSidebar() {
        const sidebar = document.createElement('div');
        sidebar.className = 'dashboard-sidebar';
        sidebar.innerHTML = `
            <div class="sidebar-header">
                <a href="#" class="sidebar-brand">
                    <i class="fas fa-chart-bar"></i>
                    Activity Tracker
                </a>
                <button class="sidebar-toggle">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="#dashboard" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#timeline" class="nav-link">
                        <i class="fas fa-timeline"></i>
                        <span class="nav-text">Timeline</span>
                        <span class="nav-badge">New</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#analytics" class="nav-link">
                        <i class="fas fa-chart-pie"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#users" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#settings" class="nav-link">
                        <i class="fas fa-cogs"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#export" class="nav-link">
                        <i class="fas fa-download"></i>
                        <span class="nav-text">Export Data</span>
                    </a>
                </li>
            </ul>
        `;
        document.body.insertBefore(sidebar, document.body.firstChild);

        // Add navigation event listeners
        this.setupSidebarNavigation(sidebar);
    }

    setupSidebarNavigation(sidebar) {
        const navLinks = sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Update active state
                navLinks.forEach(nl => nl.classList.remove('active'));
                link.classList.add('active');
                
                // Handle navigation
                const href = link.getAttribute('href');
                this.navigateToSection(href.substring(1));
                
                // Close sidebar on mobile
                if (window.innerWidth <= 768) {
                    this.hideSidebar();
                }
            });
        });
    }

    createMobileToggle() {
        const toggle = document.createElement('button');
        toggle.className = 'mobile-nav-toggle';
        toggle.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.appendChild(toggle);
        return toggle;
    }

    toggleSidebar() {
        this.sidebarOpen ? this.hideSidebar() : this.showSidebar();
    }

    showSidebar() {
        const sidebar = document.querySelector('.dashboard-sidebar');
        const content = document.querySelector('.dashboard-content');
        
        if (sidebar) {
            sidebar.classList.add('show');
            this.sidebarOpen = true;
        }
        
        if (content && window.innerWidth > 768) {
            content.classList.add('sidebar-open');
        }
    }

    hideSidebar() {
        const sidebar = document.querySelector('.dashboard-sidebar');
        const content = document.querySelector('.dashboard-content');
        
        if (sidebar) {
            sidebar.classList.remove('show');
            this.sidebarOpen = false;
        }
        
        if (content) {
            content.classList.remove('sidebar-open');
        }
    }

    setupBreadcrumbs() {
        if (!document.querySelector('.breadcrumb-container')) {
            this.createBreadcrumbs();
        }
    }

    createBreadcrumbs() {
        const breadcrumbHTML = `
            <div class="breadcrumb-container">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="#dashboard">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item active">Overview</li>
                    </ol>
                </nav>
            </div>
        `;
        
        const container = document.querySelector('.dashboard-content') || document.querySelector('.dashboard-container');
        if (container) {
            container.insertAdjacentHTML('afterbegin', breadcrumbHTML);
        }
    }

    updateBreadcrumbs(path) {
        const breadcrumb = document.querySelector('.breadcrumb');
        if (breadcrumb) {
            const pathParts = path.split('/').filter(part => part);
            let breadcrumbHTML = `
                <li class="breadcrumb-item">
                    <a href="#dashboard">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
            `;
            
            pathParts.forEach((part, index) => {
                const isLast = index === pathParts.length - 1;
                const formattedPart = part.charAt(0).toUpperCase() + part.slice(1);
                
                if (isLast) {
                    breadcrumbHTML += `<li class="breadcrumb-item active">${formattedPart}</li>`;
                } else {
                    breadcrumbHTML += `
                        <li class="breadcrumb-item">
                            <a href="#${pathParts.slice(0, index + 1).join('/')}">${formattedPart}</a>
                        </li>
                    `;
                }
            });
            
            breadcrumb.innerHTML = breadcrumbHTML;
        }
    }

    setupQuickActions() {
        if (!document.querySelector('.quick-actions')) {
            this.createQuickActions();
        }
    }

    createQuickActions() {
        const quickActionsHTML = `
            <div class="quick-actions">
                <a href="#" class="quick-action-btn" data-action="refresh">
                    <i class="fas fa-sync-alt"></i>
                    <span class="action-label">Refresh Data</span>
                </a>
                <a href="#" class="quick-action-btn" data-action="export">
                    <i class="fas fa-file-pdf"></i>
                    <span class="action-label">Export PDF</span>
                </a>
                <a href="#" class="quick-action-btn" data-action="timeline">
                    <i class="fas fa-timeline"></i>
                    <span class="action-label">View Timeline</span>
                </a>
                <a href="#" class="quick-action-btn" data-action="settings">
                    <i class="fas fa-cogs"></i>
                    <span class="action-label">Dashboard Settings</span>
                </a>
            </div>
        `;
        
        const container = document.querySelector('.dashboard-content') || document.querySelector('.dashboard-container');
        if (container) {
            const breadcrumbContainer = container.querySelector('.breadcrumb-container');
            if (breadcrumbContainer) {
                breadcrumbContainer.insertAdjacentHTML('afterend', quickActionsHTML);
            } else {
                container.insertAdjacentHTML('afterbegin', quickActionsHTML);
            }
        }

        // Add event listeners for quick actions
        document.querySelectorAll('.quick-action-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const action = btn.dataset.action;
                this.handleQuickAction(action);
            });
        });
    }

    handleQuickAction(action) {
        switch (action) {
            case 'refresh':
                this.refreshData();
                break;
            case 'export':
                this.exportToPDF();
                break;
            case 'timeline':
                this.navigateToSection('timeline');
                break;
            case 'settings':
                this.showDashboardSettings();
                break;
        }
    }

    navigateToSection(section) {
        this.updateBreadcrumbs(section);
        
        // Hide all sections
        document.querySelectorAll('[data-section]').forEach(el => {
            el.style.display = 'none';
        });
        
        // Show target section
        const targetSection = document.querySelector(`[data-section="${section}"]`);
        if (targetSection) {
            targetSection.style.display = 'block';
            targetSection.classList.add('fade-in-up');
        }
    }
    
    // ============================================
    // USER ACTIVITY TIMELINE
    // ============================================

    setupTimeline() {
        this.createTimelineContainer();
        this.loadTimelineData();
    }

    createTimelineContainer() {
        if (!document.querySelector('.timeline-container')) {
            const timelineHTML = `
                <div class="timeline-container" data-section="timeline" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <span><i class="fas fa-timeline"></i> Activity Timeline</span>
                            <div class="card-tools">
                                <button class="card-tool" onclick="dashboard.refreshTimeline()">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button class="card-tool" onclick="dashboard.toggleTimelineView()">
                                    <i class="fas fa-expand-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="timeline-filters mb-3">
                                <div class="quick-filters">
                                    <button class="quick-filter-btn active" data-range="today">Today</button>
                                    <button class="quick-filter-btn" data-range="week">This Week</button>
                                    <button class="quick-filter-btn" data-range="month">This Month</button>
                                    <button class="quick-filter-btn" data-range="all">All Time</button>
                                </div>
                            </div>
                            <div class="activity-timeline" id="activityTimeline">
                                <div class="timeline-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    Loading timeline data...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const container = document.querySelector('.dashboard-content') || document.querySelector('.dashboard-container');
            if (container) {
                container.insertAdjacentHTML('beforeend', timelineHTML);
            }

            // Add event listeners for timeline filters
            document.querySelectorAll('.timeline-filters .quick-filter-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.timeline-filters .quick-filter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    this.loadTimelineData(btn.dataset.range);
                });
            });
        }
    }

    loadTimelineData(range = 'today') {
        const timeline = document.getElementById('activityTimeline');
        if (!timeline) return;

        timeline.innerHTML = '<div class="timeline-loading"><i class="fas fa-spinner fa-spin"></i> Loading timeline data...</div>';

        // Simulate API call for timeline data
        setTimeout(() => {
            const timelineData = this.generateTimelineData(range);
            this.renderTimeline(timelineData);
        }, 800);
    }

    generateTimelineData(range) {
        const activities = [
            {
                id: 1,
                action: 'User Login',
                user: 'admin',
                time: '2024-01-15 14:30:00',
                type: 'success',
                details: 'Successful login from IP 192.168.1.100',
                element: 'Authentication',
                severity: 'info'
            },
            {
                id: 2,
                action: 'Company Created',
                user: 'john.doe',
                time: '2024-01-15 14:25:00',
                type: 'success',
                details: 'New company "Acme Corp" created with ID #1234',
                element: 'Company',
                severity: 'info'
            },
            {
                id: 3,
                action: 'Failed Login Attempt',
                user: 'unknown_user',
                time: '2024-01-15 14:20:00',
                type: 'warning',
                details: 'Multiple failed login attempts detected from IP 192.168.1.200',
                element: 'Authentication',
                severity: 'warning'
            },
            {
                id: 4,
                action: 'Data Export',
                user: 'mary.smith',
                time: '2024-01-15 14:15:00',
                type: 'info',
                details: 'Customer data exported to CSV format',
                element: 'Export',
                severity: 'info'
            },
            {
                id: 5,
                action: 'System Error',
                user: 'system',
                time: '2024-01-15 14:10:00',
                type: 'danger',
                details: 'Database connection timeout occurred',
                element: 'System',
                severity: 'error'
            }
        ];

        return activities;
    }

    renderTimeline(activities) {
        const timeline = document.getElementById('activityTimeline');
        if (!timeline) return;

        let timelineHTML = '';
        
        activities.forEach((activity, index) => {
            const timeFormatted = new Date(activity.time).toLocaleString();
            timelineHTML += `
                <div class="timeline-item ${activity.type} fade-in-up stagger-${(index % 4) + 1}">
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <span class="timeline-action">${activity.action}</span>
                            <span class="timeline-time">
                                <i class="fas fa-clock"></i>
                                ${timeFormatted}
                            </span>
                        </div>
                        <div class="timeline-details">${activity.details}</div>
                        <div class="timeline-meta">
                            <span><i class="fas fa-user"></i> ${activity.user}</span>
                            <span><i class="fas fa-tag"></i> ${activity.element}</span>
                            <span class="severity-${activity.severity}">
                                <i class="fas fa-info-circle"></i> ${activity.severity.toUpperCase()}
                            </span>
                        </div>
                    </div>
                </div>
            `;
        });

        timeline.innerHTML = timelineHTML || '<div class="text-center text-muted">No activities found for the selected period.</div>';
    }

    refreshTimeline() {
        const activeFilter = document.querySelector('.timeline-filters .quick-filter-btn.active');
        const range = activeFilter ? activeFilter.dataset.range : 'today';
        this.loadTimelineData(range);
        this.showNotification('Timeline refreshed successfully', 'success');
    }

    toggleTimelineView() {
        const timeline = document.querySelector('.timeline-container');
        if (timeline) {
            timeline.classList.toggle('fullscreen');
            this.showNotification('Timeline view toggled', 'info');
        }
    }

    // ============================================
    // ENHANCED PDF EXPORT FUNCTIONALITY
    // ============================================

    setupExport() {
        const exportBtn = document.getElementById('exportPDF');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportToPDF();
            });
        }

        // Add PDF export library if not already loaded
        if (typeof window.jsPDF === 'undefined') {
            this.loadPDFLibrary();
        }
    }

    loadPDFLibrary() {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
        script.onload = () => {
            console.log('jsPDF library loaded successfully');
        };
        document.head.appendChild(script);
    }

    exportToPDF() {
        if (typeof window.jsPDF === 'undefined') {
            this.showNotification('PDF export library is loading. Please try again in a moment.', 'warning');
            this.loadPDFLibrary();
            return;
        }

        this.showNotification('Generating PDF export...', 'info');
        
        try {
            const { jsPDF } = window.jsPDF;
            const pdf = new jsPDF();
            
            // Document header
            pdf.setFontSize(20);
            pdf.setFont(undefined, 'bold');
            pdf.text('User Activity Dashboard Report', 20, 20);
            
            pdf.setFontSize(12);
            pdf.setFont(undefined, 'normal');
            pdf.text(`Generated on: ${new Date().toLocaleString()}`, 20, 30);
            
            // Add stats summary
            this.addStatsToReport(pdf, 50);
            
            // Add recent activities
            this.addActivitiesToReport(pdf, 90);
            
            // Add charts (if available)
            this.addChartsToReport(pdf, 150);
            
            // Save the PDF
            const filename = `activity-report-${new Date().toISOString().split('T')[0]}.pdf`;
            pdf.save(filename);
            
            this.showNotification('PDF export completed successfully!', 'success');
        } catch (error) {
            console.error('PDF export error:', error);
            this.showNotification('Error generating PDF export. Please try again.', 'error');
        }
    }

    addStatsToReport(pdf, startY) {
        pdf.setFontSize(16);
        pdf.setFont(undefined, 'bold');
        pdf.text('Dashboard Statistics', 20, startY);
        
        const stats = this.extractDashboardStats();
        let currentY = startY + 10;
        
        pdf.setFontSize(10);
        pdf.setFont(undefined, 'normal');
        
        stats.forEach(stat => {
            pdf.text(`${stat.label}: ${stat.value}`, 25, currentY);
            currentY += 8;
        });
    }

    addActivitiesToReport(pdf, startY) {
        pdf.setFontSize(16);
        pdf.setFont(undefined, 'bold');
        pdf.text('Recent Activities', 20, startY);
        
        const activities = this.extractRecentActivities();
        let currentY = startY + 10;
        
        pdf.setFontSize(8);
        pdf.setFont(undefined, 'normal');
        
        activities.forEach(activity => {
            if (currentY > 270) {
                pdf.addPage();
                currentY = 20;
            }
            
            pdf.text(`${activity.time} - ${activity.action} (${activity.user})`, 25, currentY);
            currentY += 6;
            
            if (activity.details) {
                pdf.setFont(undefined, 'italic');
                pdf.text(`   ${activity.details}`, 25, currentY);
                pdf.setFont(undefined, 'normal');
                currentY += 6;
            }
            currentY += 2;
        });
    }

    addChartsToReport(pdf, startY) {
        // Try to capture chart images and add them to PDF
        const charts = document.querySelectorAll('canvas');
        if (charts.length > 0) {
            pdf.setFontSize(16);
            pdf.setFont(undefined, 'bold');
            pdf.text('Charts and Visualizations', 20, startY);
            
            let currentY = startY + 20;
            
            charts.forEach((chart, index) => {
                try {
                    if (currentY > 200) {
                        pdf.addPage();
                        currentY = 20;
                    }
                    
                    const imgData = chart.toDataURL('image/png');
                    pdf.addImage(imgData, 'PNG', 20, currentY, 160, 80);
                    currentY += 90;
                } catch (error) {
                    console.warn(`Could not export chart ${index}:`, error);
                }
            });
        }
    }

    extractDashboardStats() {
        const stats = [];
        
        document.querySelectorAll('.stat-card, .dashboard-stat').forEach(card => {
            const label = card.querySelector('.stat-label, .card-header')?.textContent?.trim();
            const value = card.querySelector('.stat-value, .stat-number')?.textContent?.trim();
            
            if (label && value) {
                stats.push({ label, value });
            }
        });
        
        return stats;
    }

    extractRecentActivities() {
        const activities = [];
        
        document.querySelectorAll('.activity-item, .timeline-item').forEach(item => {
            const action = item.querySelector('.activity-action, .timeline-action')?.textContent?.trim();
            const time = item.querySelector('.activity-time, .timeline-time')?.textContent?.trim();
            const user = item.querySelector('.activity-user')?.textContent?.trim() || 'Unknown';
            const details = item.querySelector('.activity-details, .timeline-details')?.textContent?.trim();
            
            if (action && time) {
                activities.push({ action, time, user, details });
            }
        });
        
        return activities.slice(0, 50); // Limit to 50 most recent
    }
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

    // ============================================
    // DRAG AND DROP WIDGETS & QUICK FILTERS
    // ============================================

    setupDragAndDrop() {
        this.initializeDraggableWidgets();
        this.setupWidgetLayout();
    }

    initializeDraggableWidgets() {
        const widgets = document.querySelectorAll('.dashboard-card, .widget');
        
        widgets.forEach(widget => {
            widget.draggable = true;
            widget.classList.add('draggable');
            
            widget.addEventListener('dragstart', (e) => {
                widget.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', widget.outerHTML);
                e.dataTransfer.setData('text/plain', widget.id || widget.className);
            });
            
            widget.addEventListener('dragend', (e) => {
                widget.classList.remove('dragging');
            });
        });

        this.setupDropZones();
    }

    setupDropZones() {
        const dropZones = document.querySelectorAll('.widgets-grid, .dashboard-content');
        
        dropZones.forEach(zone => {
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });
            
            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                this.saveWidgetLayout();
                this.showNotification('Widget layout updated', 'success');
            });
        });
    }

    saveWidgetLayout() {
        const widgets = document.querySelectorAll('.draggable');
        const layout = [];
        
        widgets.forEach((widget, index) => {
            layout.push({
                id: widget.id || `widget-${index}`,
                order: index,
                className: widget.className
            });
        });
        
        localStorage.setItem('uat-widget-layout', JSON.stringify(layout));
        this.widgetLayout = layout;
    }

    loadWidgetLayout() {
        const saved = localStorage.getItem('uat-widget-layout');
        return saved ? JSON.parse(saved) : [];
    }

    setupWidgetLayout() {
        if (this.widgetLayout.length > 0) {
            setTimeout(() => {
                this.applyWidgetLayout();
            }, 100);
        }
    }

    applyWidgetLayout() {
        const container = document.querySelector('.widgets-grid, .dashboard-content');
        if (!container) return;
        
        this.widgetLayout
            .sort((a, b) => a.order - b.order)
            .forEach(item => {
                const widget = document.getElementById(item.id) || 
                              document.querySelector(`.${item.className.split(' ')[0]}`);
                if (widget) {
                    container.appendChild(widget);
                }
            });
    }

    setupQuickFilters() {
        this.createQuickFiltersPanel();
    }

    createQuickFiltersPanel() {
        if (!document.querySelector('.dashboard-quick-filters')) {
            const quickFiltersHTML = `
                <div class="dashboard-quick-filters mb-4">
                    <div class="card">
                        <div class="card-header">
                            <span><i class="fas fa-filter"></i> Quick Filters</span>
                            <div class="card-tools">
                                <button class="card-tool" onclick="dashboard.clearAllFilters()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="quick-filters">
                                <button class="quick-filter-btn" data-filter="today">Today</button>
                                <button class="quick-filter-btn" data-filter="week">This Week</button>
                                <button class="quick-filter-btn" data-filter="month">This Month</button>
                                <button class="quick-filter-btn" data-filter="year">This Year</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const filterPanel = document.querySelector('.filter-panel');
            if (filterPanel) {
                filterPanel.insertAdjacentHTML('afterend', quickFiltersHTML);
            }

            document.querySelectorAll('.dashboard-quick-filters .quick-filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.applyQuickFilter(btn);
                });
            });
        }
    }

    applyQuickFilter(button) {
        const filter = button.dataset.filter;
        const isActive = button.classList.contains('active');
        
        // Toggle filter state
        document.querySelectorAll('.quick-filter-btn').forEach(btn => btn.classList.remove('active'));
        
        if (!isActive) {
            button.classList.add('active');
            this.showNotification(`Applied ${filter} filter`, 'info');
        } else {
            this.showNotification('Filter cleared', 'info');
        }
    }

    clearAllFilters() {
        document.querySelectorAll('.quick-filter-btn.active').forEach(btn => {
            btn.classList.remove('active');
        });
        this.showNotification('All filters cleared', 'info');
    }

    initializeAnimations() {
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.classList.add('fade-in-up', `stagger-${(index % 4) + 1}`);
        });

        document.querySelectorAll('.dashboard-card, .card').forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('fade-in-up');
            }, index * 100);
        });
    }

    showDashboardSettings() {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Dashboard Settings</h3>
                    <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
                </div>
                <div class="modal-body">
                    <div class="setting-group">
                        <label>
                            <input type="checkbox" id="autoRefreshSetting" ${this.refreshInterval ? 'checked' : ''}>
                            Enable auto-refresh
                        </label>
                    </div>
                    <div class="setting-group">
                        <label for="refreshInterval">Refresh interval (seconds):</label>
                        <select id="refreshIntervalSetting">
                            <option value="30">30 seconds</option>
                            <option value="60" selected>60 seconds</option>
                            <option value="300">5 minutes</option>
                        </select>
                    </div>
                    <div class="setting-group">
                        <button class="btn btn-danger" onclick="dashboard.resetWidgetLayout()">
                            Reset Widget Layout
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="dashboard.saveDashboardSettings()">Save Settings</button>
                    <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Cancel</button>
                </div>
            </div>
        `;
        
        modal.style.cssText = `
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 2000;
            display: flex; align-items: center; justify-content: center;
        `;
        
        modal.querySelector('.modal-content').style.cssText = `
            background: var(--card-bg); padding: 2rem; border-radius: 0.5rem;
            min-width: 400px; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.3);
        `;
        
        document.body.appendChild(modal);
    }

    saveDashboardSettings() {
        const autoRefresh = document.getElementById('autoRefreshSetting').checked;
        const interval = document.getElementById('refreshIntervalSetting').value;
        
        if (autoRefresh) {
            this.startAutoRefresh(parseInt(interval) * 1000);
        } else {
            this.stopAutoRefresh();
        }
        
        this.showNotification('Settings saved successfully', 'success');
        document.querySelector('.modal-overlay').remove();
    }

    resetWidgetLayout() {
        localStorage.removeItem('uat-widget-layout');
        this.showNotification('Widget layout reset. Refresh the page to see changes.', 'info');
    }
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