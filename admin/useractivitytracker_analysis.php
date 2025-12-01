<?php
/**
 * Activity Analysis page
 * Path: custom/useractivitytracker/admin/useractivitytracker_analysis.php
 * Version: 3.0.0 — OroCommerce-style UI, entity scoping, anomaly diagnostics
 */

/* ---- Locate htdocs/main.inc.php (top-level, not inside a function!) ---- */
$dir  = __DIR__;
$main = null;
for ($i = 0; $i < 10; $i++) {
    $candidate = $dir . '/main.inc.php';
    if (is_file($candidate)) { $main = $candidate; break; }
    $dir = dirname($dir);
}
if (!$main) {
    // Fallbacks for common layouts
    $fallbacks = array('../../../main.inc.php', '../../../../main.inc.php', '../../main.inc.php');
    foreach ($fallbacks as $f) {
        $p = __DIR__ . '/' . $f;
        if (is_file($p)) { $main = $p; break; }
    }
}
if (!$main) {
    header($_SERVER['SERVER_PROTOCOL'].' 500 Internal Server Error');
    echo 'Fatal: Unable to locate Dolibarr main.inc.php from ' . __FILE__;
    exit;
}
require $main;

dol_include_once('/useractivitytracker/class/useractivity.class.php');

if (empty($user->rights->useractivitytracker->read)) accessforbidden();

$langs->load('admin');
$langs->load('other');

$action = GETPOST('action','alpha');
$from   = GETPOST('from','alpha');
$to     = GETPOST('to','alpha');

if (empty($from)) $from = dol_print_date(dol_time_plus_duree(dol_now(), -7, 'd'), '%Y-%m-%d');
if (empty($to))   $to   = dol_print_date(dol_now(), '%Y-%m-%d');

$activity = new UserActivity($db);

// Comprehensive stats
$stats = $activity->getActivityStats($from, $to, $conf->entity);

// Diagnostics
$diagnostics = $activity->getDiagnostics($conf->entity);

// Anomalies if enabled
$anomalies = array();
$anomaly_enabled = getDolGlobalString('USERACTIVITYTRACKER_ENABLE_ANOMALY');
if ($anomaly_enabled) {
    $anomalies = $activity->detectAnomalies($conf->entity);
}

// Top elements
$prefix = $db->prefix();
$cond = " WHERE entity=".(int)$conf->entity." AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";

$topElements = array();
$sql = "SELECT element_type, COUNT(*) as n, COUNT(DISTINCT userid) as unique_users 
        FROM {$prefix}useractivitytracker_activity {$cond} AND element_type IS NOT NULL 
        GROUP BY element_type ORDER BY n DESC LIMIT 15";
$res = $db->query($sql);
if ($res) {
    while ($o = $db->fetch_object($res)) $topElements[] = $o;
    $db->free($res);
}

// Hourly activity
$hourlyActivity = array();
$sql = "SELECT HOUR(datestamp) as h, COUNT(*) as n 
        FROM {$prefix}useractivitytracker_activity {$cond} 
        GROUP BY HOUR(datestamp) ORDER BY h";
$res = $db->query($sql);
if ($res) {
    while ($o = $db->fetch_object($res)) $hourlyActivity[(int)$o->h] = (int)$o->n;
    $db->free($res);
}
for ($h = 0; $h < 24; $h++) {
    if (!isset($hourlyActivity[$h])) $hourlyActivity[$h] = 0;
}
ksort($hourlyActivity);

$totalActivities   = (int)($stats['total'] ?? 0);
$uniqueActions     = isset($stats['by_action']) ? count($stats['by_action']) : 0;
$activeUsers       = isset($stats['by_user']) ? count($stats['by_user']) : 0;
$affectedElements  = count($topElements);
$daysCount         = isset($stats['by_day']) ? max(1, count($stats['by_day'])) : 1;
$dailyAverage      = $daysCount > 0 ? round($totalActivities / $daysCount, 1) : 0.0;
$busiest_day       = '';
if (!empty($stats['by_day'])) {
    $maxVal = max($stats['by_day']);
    $keys   = array_keys($stats['by_day'], $maxVal);
    $busiest_day = $keys ? $keys[0] : '';
}

$bySeverity = isset($stats['by_severity']) ? $stats['by_severity'] : array();
$anomaly_count = $anomaly_enabled ? count($anomalies) : 0;

$analysis_url   = $_SERVER['PHP_SELF'];
$dashboard_url  = dol_buildpath('/useractivitytracker/admin/useractivitytracker_dashboard.php', 1);
$setup_url      = dol_buildpath('/useractivitytracker/admin/useractivitytracker_setup.php', 1);
$token          = newToken();
$retention_days = getDolGlobalInt('USERACTIVITYTRACKER_RETENTION_DAYS', 365);
$curl_available = function_exists('curl_init') ? 1 : 0;

// Header
llxHeader('', 'User Activity — Analysis');

/* ----------------- OroCommerce-Inspired UI CSS (same system as setup/dashboard) ----------------- */
print '<style>
:root {
    --uat-primary: #2563eb;
    --uat-primary-dark: #1d4ed8;
    --uat-secondary: #64748b;
    --uat-success: #10b981;
    --uat-warning: #f59e0b;
    --uat-danger: #ef4444;
    --uat-info: #06b6d4;
    --uat-bg: #f8fafc;
    --uat-surface: #ffffff;
    --uat-border: #e2e8f0;
    --uat-text: #1e293b;
    --uat-text-secondary: #64748b;
    --uat-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --uat-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
    --uat-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    --uat-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
}

.uat-dark {
    --uat-bg: #0f172a;
    --uat-surface: #1e293b;
    --uat-border: #334155;
    --uat-text: #f1f5f9;
    --uat-text-secondary: #94a3b8;
}

.uat-container {
    max-width: 1600px;
    margin: 0 auto;
    padding: 1.5rem;
    background: var(--uat-bg);
    min-height: 100vh;
}

/* Header */
.uat-header {
    background: linear-gradient(135deg, var(--uat-primary) 0%, var(--uat-primary-dark) 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    box-shadow: var(--uat-shadow-lg);
}

.uat-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.uat-header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.uat-header-title h1 {
    margin: 0;
    font-size: 1.875rem;
    font-weight: 700;
    color: white;
}

.uat-header-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.uat-header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.uat-header-meta {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.875rem;
    flex-wrap: wrap;
}

.uat-meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Cards */
.uat-card {
    background: var(--uat-surface);
    border: 1px solid var(--uat-border);
    border-radius: 8px;
    box-shadow: var(--uat-shadow);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.uat-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--uat-border);
    background: linear-gradient(to bottom, var(--uat-surface) 0%, rgba(37, 99, 235, 0.02) 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.uat-card-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--uat-text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.uat-card-title i {
    color: var(--uat-primary);
}

.uat-card-body {
    padding: 1.5rem;
}

.uat-card-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--uat-border);
    background: rgba(37, 99, 235, 0.02);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* Stats Cards */
.uat-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.uat-stat-card {
    background: var(--uat-surface);
    border: 1px solid var(--uat-border);
    border-radius: 8px;
    padding: 1.25rem 1.5rem;
    box-shadow: var(--uat-shadow);
    position: relative;
    overflow: hidden;
}

.uat-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--card-color, var(--uat-primary)) 0%, var(--card-color-light, var(--uat-primary)) 100%);
}

.uat-stat-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.uat-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: linear-gradient(135deg, var(--card-color, var(--uat-primary)) 0%, var(--card-color-dark, var(--uat-primary-dark)) 100%);
    color: white;
    box-shadow: var(--uat-shadow-md);
}

.uat-stat-label {
    font-size: 0.875rem;
    color: var(--uat-text-secondary);
    margin-bottom: 0.125rem;
    font-weight: 500;
}

.uat-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--uat-text);
}

.uat-stat-sub {
    font-size: 0.813rem;
    color: var(--uat-text-secondary);
}

/* Form elements */
.uat-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
}

.uat-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.uat-form-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--uat-text-secondary);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.uat-form-input,
.uat-form-select,
.uat-form-text {
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--uat-border);
    border-radius: 6px;
    background: var(--uat-surface);
    color: var(--uat-text);
    font-size: 0.875rem;
    transition: all 0.2s;
    width: 100%;
    box-sizing: border-box;
}

.uat-form-input:focus,
.uat-form-select:focus {
    outline: none;
    border-color: var(--uat-primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.uat-form-help {
    font-size: 0.75rem;
    color: var(--uat-text-secondary);
}

.uat-toggle-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Buttons */
.uat-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border: 1px solid transparent;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.uat-btn-primary {
    background: linear-gradient(135deg, var(--uat-primary) 0%, var(--uat-primary-dark) 100%);
    color: white;
    box-shadow: var(--uat-shadow);
}

.uat-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: var(--uat-shadow-md);
}

.uat-btn-secondary {
    background: var(--uat-surface);
    color: var(--uat-text);
    border-color: var(--uat-border);
}

.uat-btn-secondary:hover {
    background: var(--uat-bg);
}

.uat-btn-ghost {
    background: transparent;
    color: white;
    border-color: rgba(255, 255, 255, 0.4);
}

.uat-btn-ghost:hover {
    background: rgba(255, 255, 255, 0.08);
}

.uat-btn-sm {
    padding: 0.4rem 0.9rem;
    font-size: 0.813rem;
}

.uat-btn-icon {
    padding: 0.5rem;
    width: 36px;
    height: 36px;
    justify-content: center;
}

/* Badges */
.uat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
}

.uat-badge-success { background: rgba(16, 185, 129, 0.1); color: #059669; }
.uat-badge-danger  { background: rgba(239, 68, 68, 0.1);  color: #dc2626; }
.uat-badge-info    { background: rgba(37, 99, 235, 0.1);  color: #1d4ed8; }
.uat-badge-warning { background: rgba(245, 158, 11, 0.1); color: #d97706; }

/* Tables inside cards */
.uat-table-wrapper {
    overflow-x: auto;
}

.uat-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.uat-table thead {
    background: linear-gradient(to bottom, rgba(37, 99, 235, 0.05) 0%, rgba(37, 99, 235, 0.02) 100%);
}

.uat-table th {
    padding: 0.75rem 0.9rem;
    text-align: left;
    font-weight: 600;
    color: var(--uat-text);
    border-bottom: 2px solid var(--uat-border);
    font-size: 0.813rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.uat-table td {
    padding: 0.7rem 0.9rem;
    border-bottom: 1px solid var(--uat-border);
    color: var(--uat-text);
}

.uat-table tbody tr:nth-child(even) {
    background: rgba(0, 0, 0, 0.01);
}

/* Hourly bar representation */
.uat-hour-bar {
    background: rgba(37, 99, 235, 0.15);
    border-radius: 9999px;
    height: 12px;
    position: relative;
    overflow: hidden;
    margin-top: 3px;
}

.uat-hour-bar-fill {
    height: 100%;
    border-radius: 9999px;
    background: linear-gradient(90deg, #2563eb 0%, #3b82f6 100%);
}

/* Layout grids */
.uat-grid-2 {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1.5fr);
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .uat-grid-2 {
        grid-template-columns: 1fr;
    }
}

/* Recommendations box */
.uat-info-box {
    border-radius: 8px;
    border: 1px dashed var(--uat-border);
    padding: 1rem 1.25rem;
    background: rgba(37, 99, 235, 0.02);
    font-size: 0.875rem;
    color: var(--uat-text-secondary);
}

.uat-info-box h3 {
    margin: 0 0 0.5rem 0;
    font-size: 0.95rem;
    color: var(--uat-text);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

/* Responsive */
@media (max-width: 768px) {
    .uat-container {
        padding: 1rem;
    }

    .uat-header-content {
        flex-direction: column;
        align-items: flex-start;
    }

    .uat-header-actions {
        width: 100%;
    }

    .uat-header-actions .uat-btn {
        flex-grow: 1;
        justify-content: center;
    }

    .uat-stats-grid {
        grid-template-columns: 1fr;
    }
}

/* Print */
@media print {
    .uat-header-actions,
    .uat-btn {
        display: none !important;
    }
    .uat-container {
        background: #ffffff;
        padding: 0;
    }
}
</style>';

/* ----------------- PAGE STRUCTURE ----------------- */
print '<div class="uat-container">';

/* Header */
print '<div class="uat-header">';
print '<div class="uat-header-content">';
print '<div class="uat-header-title">';
print '<div class="uat-header-icon"><i class="fas fa-chart-area"></i></div>';
print '<div>';
print '<h1>User Activity — Advanced Analysis</h1>';
print '<div style="font-size:0.875rem;opacity:0.9;margin-top:0.25rem;">Deep-dive into usage patterns, hotspots and security anomalies</div>';
print '</div>';
print '</div>'; // title

print '<div class="uat-header-actions">';
print '<a href="'.dol_escape_htmltag($dashboard_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>';
print '<a href="'.dol_escape_htmltag($setup_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-cogs"></i><span>Settings</span></a>';
print '<button type="button" id="themeToggle" class="uat-btn uat-btn-ghost uat-btn-icon"><i class="fas fa-moon"></i></button>';
print '</div>'; // actions
print '</div>'; // header-content

// Header meta
print '<div class="uat-header-meta">';
print '<div class="uat-meta-item"><i class="fas fa-calendar"></i><span>Period: '.dol_escape_htmltag($from).' — '.dol_escape_htmltag($to).'</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-database"></i><span>Total: '.number_format($totalActivities).' events</span></div>';
if ($anomaly_enabled) {
    $badgeClass = $anomaly_count > 0 ? 'uat-badge-danger' : 'uat-badge-success';
    $icon = $anomaly_count > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle';
    print '<div class="uat-meta-item"><i class="fas fa-shield-alt"></i><span>Anomalies: <span class="uat-badge '.$badgeClass.'"><i class="fas '.$icon.'"></i>'.$anomaly_count.'</span></span></div>';
} else {
    print '<div class="uat-meta-item"><i class="fas fa-shield-alt"></i><span>Anomaly detection disabled</span></div>';
}
print '<div class="uat-meta-item"><i class="fas fa-calendar-check"></i><span>Retention: '.$retention_days.' days</span></div>';
print '</div>'; // header-meta

print '</div>'; // header

/* Stats cards */
print '<div class="uat-stats-grid">';

// Total Activities
print '<div class="uat-stat-card" style="--card-color:#2563eb; --card-color-dark:#1d4ed8; --card-color-light:#3b82f6;">';
print '<div class="uat-stat-header">';
print '<div class="uat-stat-icon"><i class="fas fa-chart-bar"></i></div>';
print '<div>';
print '<div class="uat-stat-label">Total Activities</div>';
print '<div class="uat-stat-value">'.number_format($totalActivities).'</div>';
print '<div class="uat-stat-sub">Logged events in selected period</div>';
print '</div>';
print '</div>';
print '</div>';

// Unique Actions
print '<div class="uat-stat-card" style="--card-color:#10b981; --card-color-dark:#059669; --card-color-light:#34d399;">';
print '<div class="uat-stat-header">';
print '<div class="uat-stat-icon"><i class="fas fa-layer-group"></i></div>';
print '<div>';
print '<div class="uat-stat-label">Unique Actions</div>';
print '<div class="uat-stat-value">'.$uniqueActions.'</div>';
print '<div class="uat-stat-sub">Distinct action types captured</div>';
print '</div>';
print '</div>';
print '</div>';

// Active Users
print '<div class="uat-stat-card" style="--card-color:#8b5cf6; --card-color-dark:#7c3aed; --card-color-light:#a78bfa;">';
print '<div class="uat-stat-header">';
print '<div class="uat-stat-icon"><i class="fas fa-users"></i></div>';
print '<div>';
print '<div class="uat-stat-label">Active Users</div>';
print '<div class="uat-stat-value">'.$activeUsers.'</div>';
print '<div class="uat-stat-sub">Users with activity in this period</div>';
print '</div>';
print '</div>';
print '</div>';

// Affected elements / anomalies
$badgeClass = $anomaly_enabled
    ? ($anomaly_count > 0 ? 'uat-badge-danger' : 'uat-badge-success')
    : 'uat-badge-warning';
$badgeLabel = $anomaly_enabled
    ? ($anomaly_count > 0 ? $anomaly_count.' anomalie(s)' : 'No anomalies')
    : 'Detection off';

print '<div class="uat-stat-card" style="--card-color:#f59e0b; --card-color-dark:#d97706; --card-color-light:#fbbf24;">';
print '<div class="uat-stat-header">';
print '<div class="uat-stat-icon"><i class="fas fa-crosshairs"></i></div>';
print '<div>';
print '<div class="uat-stat-label">Affected Elements & Security</div>';
print '<div class="uat-stat-value">'.$affectedElements.' types</div>';
print '<div class="uat-stat-sub">Security status: <span class="uat-badge '.$badgeClass.'">'.$badgeLabel.'</span></div>';
print '</div>';
print '</div>';
print '</div>';

print '</div>'; // stats-grid

/* Filter card */
print '<form method="get" action="'.dol_escape_htmltag($analysis_url).'" class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-filter"></i> Analysis Period</div>';
print '</div>';
print '<div class="uat-card-body">';
print '<div class="uat-form-grid">';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-calendar-alt"></i> From</label>';
print '<input type="date" name="from" class="uat-form-input" value="'.dol_escape_htmltag($from).'">';
print '</div>';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-calendar-alt"></i> To</label>';
print '<input type="date" name="to" class="uat-form-input" value="'.dol_escape_htmltag($to).'">';
print '</div>';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-info-circle"></i> Hint</label>';
print '<div class="uat-form-help">Adjust the date range to zoom into spikes or quieter periods. Defaults to the last 7 days.</div>';
print '</div>';
print '</div>'; // form-grid
print '</div>'; // card-body
print '<div class="uat-card-footer">';
print '<button type="submit" class="uat-btn uat-btn-primary"><i class="fas fa-sync-alt"></i><span>Update Analysis</span></button>';
print '</div>';
print '</form>';

/* Main content grid: Activity metrics + Security / Anomalies */
print '<div class="uat-grid-2">';

/* LEFT: Activity metrics + hourly distribution */
print '<div>';

print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-tachometer-alt"></i> Activity Metrics</div>';
print '</div>';
print '<div class="uat-card-body">';
print '<div class="uat-table-wrapper">';
print '<table class="uat-table">';
print '<thead><tr><th>Metric</th><th style="text-align:right;">Value</th></tr></thead>';
print '<tbody>';
print '<tr><td>Total Activities</td><td style="text-align:right;"><strong>'.number_format($totalActivities).'</strong></td></tr>';
print '<tr><td>Unique Actions</td><td style="text-align:right;">'.$uniqueActions.'</td></tr>';
print '<tr><td>Active Users</td><td style="text-align:right;">'.$activeUsers.'</td></tr>';
print '<tr><td>Affected Elements</td><td style="text-align:right;">'.$affectedElements.'</td></tr>';
print '<tr><td>Daily Average</td><td style="text-align:right;">'.$dailyAverage.'</td></tr>';
print '<tr><td>Busiest Day</td><td style="text-align:right;">'.dol_escape_htmltag($busiest_day ?: '—').'</td></tr>';
print '</tbody>';
print '</table>';
print '</div>';
print '</div>';
print '</div>'; // metrics card

// Hourly distribution
print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-clock"></i> Activity by Hour of Day</div>';
print '</div>';
print '<div class="uat-card-body">';
if (!empty($hourlyActivity)) {
    $max_hourly = max($hourlyActivity);
    print '<div class="uat-table-wrapper">';
    print '<table class="uat-table">';
    print '<thead><tr><th>Hour</th><th style="text-align:right;">Events</th></tr></thead>';
    print '<tbody>';
    foreach ($hourlyActivity as $h => $count) {
        $bar_width = $max_hourly > 0 ? ($count / $max_hourly) * 100 : 0;
        print '<tr>';
        print '<td>'.sprintf('%02d:00', $h).'</td>';
        print '<td style="text-align:right;">'.$count;
        print '<div class="uat-hour-bar">';
        if ($bar_width > 0) {
            print '<div class="uat-hour-bar-fill" style="width:'.$bar_width.'%;"></div>';
        }
        print '</div>';
        print '</td>';
        print '</tr>';
    }
    print '</tbody>';
    print '</table>';
    print '</div>';
} else {
    print '<div class="uat-info-box"><h3><span>ℹ️</span>No hourly data</h3><p>No events found for the selected period.</p></div>';
}
print '</div>';
print '</div>'; // hourly card

print '</div>'; // left column

/* RIGHT: Security status, anomalies, system info */
print '<div>';

// Security status card
print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-shield-alt"></i> Security Status</div>';
print '</div>';
print '<div class="uat-card-body">';
print '<div class="uat-table-wrapper">';
print '<table class="uat-table">';
print '<thead><tr><th>Indicator</th><th style="text-align:right;">Value</th></tr></thead>';
print '<tbody>';

if ($anomaly_enabled) {
    $severity_style = $anomaly_count > 0 ? 'color:#d63031;font-weight:bold;' : 'color:#00b894;font-weight:bold;';
    print '<tr><td>Anomalies Detected</td><td style="text-align:right;"><span style="'.$severity_style.'">'.$anomaly_count.'</span></td></tr>';

    if (!empty($bySeverity)) {
        foreach ($bySeverity as $sev => $count) {
            $color = '#0984e3';
            if ($sev == 'warning') $color = '#fdcb6e';
            if ($sev == 'error')   $color = '#d63031';
            if ($sev == 'notice')  $color = '#6c5ce7';
            print '<tr><td>'.ucfirst($sev).' Events</td><td style="text-align:right;"><span style="color:'.$color.';font-weight:bold;">'.$count.'</span></td></tr>';
        }
    }
} else {
    print '<tr><td colspan="2"><em>Anomaly detection disabled (enable in Settings)</em></td></tr>';
}

print '<tr><td>cURL Available</td><td style="text-align:right;">';
if ($curl_available) {
    print '<span class="uat-badge uat-badge-success"><i class="fas fa-check"></i> Yes</span>';
} else {
    print '<span class="uat-badge uat-badge-danger"><i class="fas fa-times"></i> No</span>';
}
print '</td></tr>';

print '<tr><td>Retention (days)</td><td style="text-align:right;">'.$retention_days.'</td></tr>';

print '</tbody>';
print '</table>';
print '</div>';
print '</div>';
print '</div>'; // security card

// Top elements card
if (!empty($topElements)) {
    print '<div class="uat-card">';
    print '<div class="uat-card-header">';
    print '<div class="uat-card-title"><i class="fas fa-crosshairs"></i> Top Elements by Activity</div>';
    print '</div>';
    print '<div class="uat-card-body">';
    print '<div class="uat-table-wrapper">';
    print '<table class="uat-table">';
    print '<thead><tr><th>Element Type</th><th style="text-align:right;">Activities</th><th style="text-align:right;">Unique Users</th><th style="text-align:right;">Avg per User</th></tr></thead>';
    print '<tbody>';
    foreach ($topElements as $elem) {
        $avg_per_user = $elem->unique_users > 0 ? round($elem->n / $elem->unique_users, 1) : 0;
        print '<tr>';
        print '<td>'.dol_escape_htmltag($elem->element_type).'</td>';
        print '<td style="text-align:right;">'.$elem->n.'</td>';
        print '<td style="text-align:right;">'.$elem->unique_users.'</td>';
        print '<td style="text-align:right;">'.$avg_per_user.'</td>';
        print '</tr>';
    }
    print '</tbody>';
    print '</table>';
    print '</div>';
    print '</div>';
    print '</div>';
}

// Anomalies list card
if (!empty($anomalies)) {
    print '<div class="uat-card">';
    print '<div class="uat-card-header">';
    print '<div class="uat-card-title"><i class="fas fa-exclamation-triangle"></i> Security Anomalies</div>';
    print '</div>';
    print '<div class="uat-card-body">';
    print '<div class="uat-table-wrapper">';
    print '<table class="uat-table">';
    print '<thead><tr><th style="width:50px;">Type</th><th>Description</th></tr></thead>';
    print '<tbody>';
    foreach ($anomalies as $anomaly) {
        $icon = '';
        if ($anomaly['type'] == 'suspicious_login') $icon = '⚠️';
        if ($anomaly['type'] == 'bulk_activity')    $icon = '📊';
        print '<tr>';
        print '<td style="text-align:center;">'.$icon.'</td>';
        print '<td>'.dol_escape_htmltag($anomaly['description']).'</td>';
        print '</tr>';
    }
    print '</tbody>';
    print '</table>';
    print '</div>';
    print '</div>';
    print '</div>';
}

print '</div>'; // right column

print '</div>'; // main grid

/* Recommendations & diagnostics (full width) */
print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-lightbulb"></i> Recommendations</div>';
print '</div>';
print '<div class="uat-card-body">';
print '<div class="uat-info-box">';
print '<h3><span>💡</span> Recommendations</h3>';
print '<ul style="margin-top:0.5rem;margin-bottom:0;">';

if ($totalActivities == 0) {
    print '<li><strong>⚠️ No activity data found for the selected period.</strong></li>';

    $diagnostic_results = array();

    // 1. Table existence
    if (empty($diagnostics['table_exists'])) {
        $diagnostic_results[] = '❌ Database table <code>'.$db->prefix().'useractivitytracker_activity</code> does not exist';
    } else {
        $diagnostic_results[] = '✅ Database table exists';

        // 2. Table structure
        $required_columns = array('rowid', 'datestamp', 'entity', 'action', 'userid', 'username');
        $existing_columns = isset($diagnostics['table_columns']) ? $diagnostics['table_columns'] : array();
        $missing_columns = array_diff($required_columns, $existing_columns);
        if (!empty($missing_columns)) {
            $diagnostic_results[] = '❌ Table missing required columns: ' . implode(', ', $missing_columns);
        } else {
            $diagnostic_results[] = '✅ Table structure is correct';
        }

        // 3. Recent activity
        $recent_count = isset($diagnostics['recent_activity_count']) ? (int)$diagnostics['recent_activity_count'] : 0;
        if ($recent_count > 0) {
            $diagnostic_results[] = '📊 Found '.$recent_count.' activities in the last 7 days';

            if (!empty($diagnostics['latest_activity'])) {
                $latest = $diagnostics['latest_activity'];
                $latest_date = dol_print_date($db->jdate($latest['datestamp']), 'dayhour');
                $diagnostic_results[] = '📅 Latest activity: '.dol_escape_htmltag($latest['action']).' by '.dol_escape_htmltag($latest['username']).' on '.$latest_date;
            }

            $diagnostic_results[] = '💡 Recent activity found — the issue may be your selected date range ('.dol_escape_htmltag($from).' to '.dol_escape_htmltag($to).')';
        } else {
            $diagnostic_results[] = '❌ No activities found in the last 7 days — tracking may not be working';

            // Triggers check
            if (empty($conf->modules_parts['triggers']) || !in_array(1, $conf->modules_parts['triggers'])) {
                $diagnostic_results[] = '❌ Triggers may not be enabled in Dolibarr configuration';
            } else {
                $diagnostic_results[] = '✅ Triggers are enabled in Dolibarr';
            }

            // Tracking enabled?
            if (!getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)) {
                $diagnostic_results[] = '❌ User tracking is disabled — enable it in Settings';
            } else {
                $diagnostic_results[] = '✅ User tracking is enabled';
            }

            // Module enabled?
            if (empty($conf->useractivitytracker->enabled)) {
                $diagnostic_results[] = '❌ User Activity Tracker module may not be fully enabled';
            } else {
                $diagnostic_results[] = '✅ Module is enabled';
            }
        }

        // 4. DB write test (only if no recent activity)
        if ($recent_count == 0) {
            try {
                $test_sql = "INSERT INTO ".$db->prefix()."useractivitytracker_activity
                            (datestamp, entity, action, fk_user, username, severity)
                            VALUES (NOW(), ".(int)$conf->entity.", 'TEST_DIAGNOSTIC', ".(int)$user->id.", '".$db->escape($user->login)."', 'info')";
                $test_res = $db->query($test_sql);
                if ($test_res) {
                    $last_id = $db->last_insert_id($db->prefix()."useractivitytracker_activity");
                    if ($last_id) {
                        $db->query("DELETE FROM ".$db->prefix()."useractivitytracker_activity WHERE rowid = ".(int)$last_id);
                    }
                    $diagnostic_results[] = '✅ Database write permissions OK';
                } else {
                    $diagnostic_results[] = '❌ Database write test failed: ' . $db->lasterror();
                }
            } catch (Exception $e) {
                $diagnostic_results[] = '❌ Database write test failed: ' . $e->getMessage();
            }
        }
    }

    // Diagnostic results
    print '<li><strong>🔍 Diagnostic Results:</strong><ul>';
    foreach ($diagnostic_results as $result) {
        print '<li style="margin:5px 0;">'.$result.'</li>';
    }
    print '</ul></li>';

    // Troubleshooting steps
    print '<li><strong>🔧 Troubleshooting Steps:</strong><ul>';
    if (empty($diagnostics['table_exists'])) {
        print '<li><strong>Priority:</strong> Disable and re-enable the User Activity Tracker module to recreate the database table.</li>';
        print '<li>Check database permissions for table creation.</li>';
        print '<li>Verify that the module installation completed successfully.</li>';
    } elseif (($diagnostics['recent_activity_count'] ?? 0) == 0) {
        print '<li><strong>Priority:</strong> The module appears to not be tracking activities. Check:</li>';
        print '<li style="margin-left:20px;">• Server error logs for trigger execution failures.</li>';
        print '<li style="margin-left:20px;">• Perform some actions in Dolibarr (create/edit records, login/logout) and refresh this page.</li>';
        print '<li style="margin-left:20px;">• Verify triggers are enabled in Dolibarr configuration.</li>';
        print '<li style="margin-left:20px;">• Check if user tracking is disabled for your user (USERACTIVITYTRACKER_SKIP_USER_'.$user->id.').</li>';
    } else {
        if (!empty($diagnostics['latest_activity'])) {
            $latest2 = $diagnostics['latest_activity'];
            $latest_day_only = dol_print_date($db->jdate($latest2['datestamp']), 'day');
            print '<li>The latest activity was on '.$latest_day_only.'. Adjust the date range accordingly.</li>';
        } else {
            print '<li>Adjust the selected date range to include the period when activities occurred.</li>';
        }
    }
    print '</ul></li>';

} else {
    if ($activeUsers < 3) {
        print '<li>Consider expanding adoption: only <strong>'.$activeUsers.'</strong> users have activity in this period.</li>';
    }

    if ($anomaly_enabled && $anomaly_count > 0) {
        print '<li><strong>Security Alert:</strong> '.$anomaly_count.' anomalies detected. Review the security and anomaly sections above.</li>';
    }

    if (!getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_URL')) {
        print '<li>Configure a webhook URL in <strong>Settings</strong> for real-time push of critical activity.</li>';
    }

    if ($retention_days > 90) {
        print '<li>Your retention is '.$retention_days.' days. Consider reducing it for better performance and lighter storage.</li>';
    }

    print '<li>Export activity summaries regularly for compliance, audit or management reporting.</li>';
}

print '</ul>';
print '</div>'; // info-box
print '</div>'; // card-body
print '</div>'; // recommendations card

print '</div>'; // container

/* Theme toggle JS (same behaviour as dashboard/setup) */
print '<script>
(function(){
  "use strict";
  var themeBtn = document.getElementById("themeToggle");
  if (themeBtn) {
    themeBtn.addEventListener("click", function() {
      document.documentElement.classList.toggle("uat-dark");
      var icon = themeBtn.querySelector("i");
      if (icon) {
        icon.className = document.documentElement.classList.contains("uat-dark")
          ? "fas fa-sun"
          : "fas fa-moon";
      }
      try {
        localStorage.setItem("uat-dark", document.documentElement.classList.contains("uat-dark") ? "1" : "0");
      } catch(e) {}
    });
  }
  try {
    if (localStorage.getItem("uat-dark") === "1") {
      document.documentElement.classList.add("uat-dark");
      if (themeBtn) {
        var icon2 = themeBtn.querySelector("i");
        if (icon2) icon2.className = "fas fa-sun";
      }
    }
  } catch(e) {}
})();
</script>';

llxFooter();
