/**
 * Advanced Dashboard Features for User Activity Tracker
 * Version: 1.0.0
 * Features: User comparison, heatmaps, trends analysis
 */

class AdvancedDashboard extends UserActivityDashboard {
    constructor() {
        super();
        this.setupAdvancedFeatures();
    }
    
    setupAdvancedFeatures() {
        this.setupUserComparison();
        this.setupActivityHeatmap();
        this.setupTrendAnalysis();
        this.setupDashboardSettings();
    }
    
    // User Comparison Feature
    setupUserComparison() {
        const compareBtn = document.getElementById('compareUsers');
        if (compareBtn) {
            compareBtn.addEventListener('click', () => {
                this.showUserComparisonModal();
            });
        }
    }
    
    showUserComparisonModal() {
        const modal = this.createModal('User Comparison', this.getUserComparisonContent());
        document.body.appendChild(modal);
        
        // Setup comparison functionality
        this.setupComparisonControls(modal);
    }
    
    getUserComparisonContent() {
        return `
            <div class="comparison-container">
                <div class="comparison-controls mb-3">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Select Users to Compare (max 4)</label>
                            <div class="user-selection">
                                <label><input type="checkbox" value="admin"> admin</label>
                                <label><input type="checkbox" value="john.doe"> john.doe</label>
                                <label><input type="checkbox" value="mary.smith"> mary.smith</label>
                                <label><input type="checkbox" value="sales.manager"> sales.manager</label>
                            </div>
                        </div>
                        <div class="filter-group">
                            <label>Comparison Period</label>
                            <select id="comparisonPeriod">
                                <option value="7">Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 90 days</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary" onclick="window.dashboard.runComparison()">
                                <i class="fas fa-chart-bar"></i> Compare
                            </button>
                        </div>
                    </div>
                </div>
                <div id="comparisonResults" class="comparison-results">
                    <div class="text-center p-4" style="color: var(--text-secondary);">
                        Select users and click "Compare" to see detailed comparison
                    </div>
                </div>
            </div>
        `;
    }
    
    setupComparisonControls(modal) {
        const checkboxes = modal.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                const checked = modal.querySelectorAll('input[type="checkbox"]:checked');
                if (checked.length > 4) {
                    cb.checked = false;
                    this.showNotification('Maximum 4 users can be compared', 'warning');
                }
            });
        });
    }
    
    runComparison() {
        const modal = document.querySelector('.modal');
        const selected = Array.from(modal.querySelectorAll('input[type="checkbox"]:checked'))
            .map(cb => cb.value);
        
        if (selected.length < 2) {
            this.showNotification('Select at least 2 users to compare', 'warning');
            return;
        }
        
        const resultsContainer = modal.querySelector('#comparisonResults');
        resultsContainer.innerHTML = '<div class="loading text-center p-4"><i class="fas fa-spinner fa-spin"></i> Loading comparison...</div>';
        
        // Simulate API call
        setTimeout(() => {
            resultsContainer.innerHTML = this.generateComparisonResults(selected);
            this.createComparisonCharts(selected);
        }, 1000);
    }
    
    generateComparisonResults(users) {
        const metrics = {
            'admin': { activities: 423, logins: 89, creates: 156, modifies: 178 },
            'john.doe': { activities: 289, logins: 67, creates: 98, modifies: 124 },
            'mary.smith': { activities: 198, logins: 45, creates: 67, modifies: 86 },
            'sales.manager': { activities: 145, logins: 34, creates: 52, modifies: 59 }
        };
        
        let html = '<div class="comparison-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem;">';
        
        users.forEach(user => {
            const data = metrics[user] || { activities: 0, logins: 0, creates: 0, modifies: 0 };
            html += `
                <div class="comparison-card dashboard-card">
                    <div class="card-header">
                        <span><i class="fas fa-user"></i> ${user}</span>
                    </div>
                    <div class="card-body">
                        <div class="metric-row">
                            <span>Total Activities:</span>
                            <strong>${data.activities}</strong>
                        </div>
                        <div class="metric-row">
                            <span>Login Sessions:</span>
                            <strong>${data.logins}</strong>
                        </div>
                        <div class="metric-row">
                            <span>Creates:</span>
                            <strong>${data.creates}</strong>
                        </div>
                        <div class="metric-row">
                            <span>Modifies:</span>
                            <strong>${data.modifies}</strong>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        html += '<div class="chart-wrapper" style="height: 400px; margin: 2rem 0;"><canvas id="comparisonChart"></canvas></div>';
        
        return html;
    }
    
    createComparisonCharts(users) {
        const ctx = document.getElementById('comparisonChart');
        if (!ctx) return;
        
        const colors = ['#007bff', '#28a745', '#ffc107', '#dc3545'];
        const metrics = {
            'admin': { activities: 423, logins: 89, creates: 156, modifies: 178 },
            'john.doe': { activities: 289, logins: 67, creates: 98, modifies: 124 },
            'mary.smith': { activities: 198, logins: 45, creates: 67, modifies: 86 },
            'sales.manager': { activities: 145, logins: 34, creates: 52, modifies: 59 }
        };
        
        const datasets = users.map((user, index) => ({
            label: user,
            data: [
                metrics[user].activities,
                metrics[user].logins,
                metrics[user].creates,
                metrics[user].modifies
            ],
            backgroundColor: colors[index % colors.length],
            borderColor: colors[index % colors.length],
            borderWidth: 1
        }));
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Total Activities', 'Login Sessions', 'Creates', 'Modifies'],
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'User Activity Comparison'
                    },
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }
    
    // Activity Heatmap
    setupActivityHeatmap() {
        const heatmapBtn = document.getElementById('showHeatmap');
        if (heatmapBtn) {
            heatmapBtn.addEventListener('click', () => {
                this.showActivityHeatmap();
            });
        }
    }
    
    showActivityHeatmap() {
        const modal = this.createModal('Activity Heatmap', this.getHeatmapContent());
        document.body.appendChild(modal);
        
        setTimeout(() => {
            this.generateHeatmap();
        }, 100);
    }
    
    getHeatmapContent() {
        return `
            <div class="heatmap-container">
                <div class="heatmap-controls mb-3">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Heatmap Type</label>
                            <select id="heatmapType">
                                <option value="hourly">Hourly Activity</option>
                                <option value="daily">Daily Activity</option>
                                <option value="user-action">User vs Action</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Time Period</label>
                            <select id="heatmapPeriod">
                                <option value="7">Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 90 days</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary" onclick="window.dashboard.updateHeatmap()">
                                <i class="fas fa-sync-alt"></i> Update
                            </button>
                        </div>
                    </div>
                </div>
                <div id="heatmapChart" class="heatmap-chart"></div>
            </div>
        `;
    }
    
    generateHeatmap() {
        const container = document.getElementById('heatmapChart');
        if (!container) return;
        
        // Generate sample heatmap data
        const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        const hours = Array.from({length: 24}, (_, i) => `${i.toString().padStart(2, '0')}:00`);
        
        let heatmapHTML = '<div class="heatmap-grid">';
        heatmapHTML += '<div class="heatmap-labels-y">';
        days.forEach(day => {
            heatmapHTML += `<div class="heatmap-label">${day}</div>`;
        });
        heatmapHTML += '</div>';
        
        heatmapHTML += '<div class="heatmap-content">';
        heatmapHTML += '<div class="heatmap-labels-x">';
        hours.forEach(hour => {
            heatmapHTML += `<div class="heatmap-label">${hour}</div>`;
        });
        heatmapHTML += '</div>';
        
        heatmapHTML += '<div class="heatmap-cells">';
        for (let day = 0; day < 7; day++) {
            for (let hour = 0; hour < 24; hour++) {
                const intensity = Math.random();
                const className = intensity > 0.8 ? 'high' : intensity > 0.5 ? 'medium' : intensity > 0.2 ? 'low' : 'minimal';
                heatmapHTML += `<div class="heatmap-cell ${className}" title="${days[day]} ${hours[hour]}: ${Math.floor(intensity * 50)} activities"></div>`;
            }
        }
        heatmapHTML += '</div></div></div>';
        
        heatmapHTML += `
            <div class="heatmap-legend mt-3">
                <span>Less</span>
                <div class="legend-cells">
                    <div class="legend-cell minimal"></div>
                    <div class="legend-cell low"></div>
                    <div class="legend-cell medium"></div>
                    <div class="legend-cell high"></div>
                </div>
                <span>More</span>
            </div>
        `;
        
        container.innerHTML = heatmapHTML;
    }
    
    updateHeatmap() {
        this.generateHeatmap();
        this.showNotification('Heatmap updated successfully', 'success');
    }
    
    // Trend Analysis
    setupTrendAnalysis() {
        const trendBtn = document.getElementById('showTrends');
        if (trendBtn) {
            trendBtn.addEventListener('click', () => {
                this.showTrendAnalysis();
            });
        }
    }
    
    showTrendAnalysis() {
        const modal = this.createModal('Activity Trend Analysis', this.getTrendAnalysisContent());
        modal.style.width = '90vw';
        modal.style.maxWidth = '1200px';
        document.body.appendChild(modal);
        
        setTimeout(() => {
            this.generateTrendCharts();
        }, 100);
    }
    
    getTrendAnalysisContent() {
        return `
            <div class="trend-analysis">
                <div class="trend-controls mb-3">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Analysis Period</label>
                            <select id="trendPeriod">
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 90 days</option>
                                <option value="365">Last year</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Grouping</label>
                            <select id="trendGrouping">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="trend-charts">
                    <div class="dashboard-card mb-3">
                        <div class="card-header">
                            <span><i class="fas fa-chart-line"></i> Activity Trends</span>
                        </div>
                        <div class="card-body">
                            <div class="chart-wrapper" style="height: 300px;">
                                <canvas id="activityTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card mb-3">
                        <div class="card-header">
                            <span><i class="fas fa-users"></i> User Growth</span>
                        </div>
                        <div class="card-body">
                            <div class="chart-wrapper" style="height: 300px;">
                                <canvas id="userGrowthChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="trend-insights">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <span><i class="fas fa-lightbulb"></i> Insights & Recommendations</span>
                            </div>
                            <div class="card-body">
                                <div class="insight-item">
                                    <i class="fas fa-arrow-up text-success"></i>
                                    <strong>Activity increased 15%</strong> compared to previous period
                                </div>
                                <div class="insight-item">
                                    <i class="fas fa-clock text-info"></i>
                                    <strong>Peak hours:</strong> 9-11 AM and 2-4 PM
                                </div>
                                <div class="insight-item">
                                    <i class="fas fa-user-plus text-primary"></i>
                                    <strong>3 new active users</strong> this period
                                </div>
                                <div class="insight-item">
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                    <strong>Low weekend activity</strong> - consider weekend campaigns
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    generateTrendCharts() {
        // Activity Trend Chart
        const trendCtx = document.getElementById('activityTrendChart');
        if (trendCtx) {
            const labels = Array.from({length: 30}, (_, i) => {
                const date = new Date();
                date.setDate(date.getDate() - (29 - i));
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });
            
            const data = labels.map(() => Math.floor(Math.random() * 100) + 20);
            
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Daily Activities',
                        data: data,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        }
        
        // User Growth Chart
        const growthCtx = document.getElementById('userGrowthChart');
        if (growthCtx) {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
            const userData = [18, 20, 21, 22, 23, 23];
            
            new Chart(growthCtx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Active Users',
                        data: userData,
                        backgroundColor: '#28a745',
                        borderColor: '#1e7e34',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        }
    }
    
    // Dashboard Settings
    setupDashboardSettings() {
        const settingsBtn = document.getElementById('dashboardSettings');
        if (settingsBtn) {
            settingsBtn.addEventListener('click', () => {
                this.showDashboardSettings();
            });
        }
    }
    
    showDashboardSettings() {
        const modal = this.createModal('Dashboard Settings', this.getSettingsContent());
        document.body.appendChild(modal);
    }
    
    getSettingsContent() {
        const currentPrefs = this.loadCurrentPreferences();
        
        return `
            <div class="settings-container">
                <div class="settings-section">
                    <h6><i class="fas fa-palette"></i> Appearance</h6>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" id="setting-darkmode" ${currentPrefs.darkMode ? 'checked' : ''}> 
                            Enable Dark Mode
                        </label>
                    </div>
                    <div class="setting-item">
                        <label>Auto-refresh Interval</label>
                        <select id="setting-refresh">
                            <option value="0">Disabled</option>
                            <option value="15000" ${currentPrefs.refreshInterval === 15000 ? 'selected' : ''}>15 seconds</option>
                            <option value="30000" ${currentPrefs.refreshInterval === 30000 ? 'selected' : ''}>30 seconds</option>
                            <option value="60000" ${currentPrefs.refreshInterval === 60000 ? 'selected' : ''}>1 minute</option>
                        </select>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h6><i class="fas fa-table"></i> Data Display</h6>
                    <div class="setting-item">
                        <label>Items per page</label>
                        <select id="setting-pagesize">
                            <option value="10" ${currentPrefs.itemsPerPage === 10 ? 'selected' : ''}>10</option>
                            <option value="20" ${currentPrefs.itemsPerPage === 20 ? 'selected' : ''}>20</option>
                            <option value="50" ${currentPrefs.itemsPerPage === 50 ? 'selected' : ''}>50</option>
                        </select>
                    </div>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" id="setting-animations" ${currentPrefs.animations !== false ? 'checked' : ''}> 
                            Enable animations
                        </label>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h6><i class="fas fa-bell"></i> Notifications</h6>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" id="setting-notifications" ${currentPrefs.notifications !== false ? 'checked' : ''}> 
                            Show notifications
                        </label>
                    </div>
                </div>
                
                <div class="settings-actions mt-3">
                    <button type="button" class="btn btn-primary" onclick="window.dashboard.saveSettings()">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.dashboard.resetSettings()">
                        <i class="fas fa-undo"></i> Reset to Defaults
                    </button>
                </div>
            </div>
        `;
    }
    
    loadCurrentPreferences() {
        const saved = localStorage.getItem('uat-preferences');
        return saved ? JSON.parse(saved) : {
            darkMode: false,
            refreshInterval: 30000,
            itemsPerPage: 10,
            animations: true,
            notifications: true
        };
    }
    
    saveSettings() {
        const settings = {
            darkMode: document.getElementById('setting-darkmode')?.checked || false,
            refreshInterval: parseInt(document.getElementById('setting-refresh')?.value || '30000'),
            itemsPerPage: parseInt(document.getElementById('setting-pagesize')?.value || '10'),
            animations: document.getElementById('setting-animations')?.checked !== false,
            notifications: document.getElementById('setting-notifications')?.checked !== false
        };
        
        localStorage.setItem('uat-preferences', JSON.stringify(settings));
        this.applySettings(settings);
        this.showNotification('Settings saved successfully', 'success');
        
        // Close modal
        const modal = document.querySelector('.modal');
        if (modal) modal.remove();
    }
    
    applySettings(settings) {
        // Apply dark mode
        if (settings.darkMode !== (document.documentElement.getAttribute('data-theme') === 'dark')) {
            this.toggleTheme();
        }
        
        // Apply refresh interval
        if (settings.refreshInterval > 0) {
            this.startAutoRefresh(settings.refreshInterval);
            const checkbox = document.getElementById('autoRefresh');
            if (checkbox) checkbox.checked = true;
        } else {
            this.stopAutoRefresh();
            const checkbox = document.getElementById('autoRefresh');
            if (checkbox) checkbox.checked = false;
        }
        
        // Apply items per page
        this.itemsPerPage = settings.itemsPerPage;
        this.updatePagination();
        
        // Apply animations
        document.documentElement.style.setProperty('--transition', settings.animations ? 'all 0.15s ease-in-out' : 'none');
    }
    
    resetSettings() {
        const defaultSettings = {
            darkMode: false,
            refreshInterval: 30000,
            itemsPerPage: 10,
            animations: true,
            notifications: true
        };
        
        localStorage.setItem('uat-preferences', JSON.stringify(defaultSettings));
        this.applySettings(defaultSettings);
        this.showNotification('Settings reset to defaults', 'info');
        
        // Close modal and reopen to show updated values
        const modal = document.querySelector('.modal');
        if (modal) modal.remove();
        setTimeout(() => this.showDashboardSettings(), 100);
    }
    
    // Utility method to create modals
    createModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'modal fade-in';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1050;
        `;
        
        modal.innerHTML = `
            <div class="modal-content dashboard-card" style="width: 90vw; max-width: 800px; max-height: 90vh; overflow-y: auto; margin: 0;">
                <div class="card-header">
                    <span>${title}</span>
                    <button type="button" class="close-modal" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>
                <div class="card-body">
                    ${content}
                </div>
            </div>
        `;
        
        // Close modal functionality
        modal.querySelector('.close-modal').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });
        
        return modal;
    }
}

// Initialize advanced dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new AdvancedDashboard();
});