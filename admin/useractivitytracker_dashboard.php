<?php
/**
 * Dashboard page
 * Path: custom/useractivitytracker/admin/useractivitytracker_dashboard.php
 * Version: 1.0.3 — dynamic main.inc.php resolver, safe links, minor SQL tidy
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

/* ---- Rights ---- */
if (empty($user->rights->useractivitytracker->read)) accessforbidden();

/* Handle AJAX requests */
if (GETPOST('ajax', 'int') == 1) {
    header('Content-Type: application/json');
    
    /* ---- Inputs ---- */
    $from           = trim(GETPOST('from','alphanohtml'));
    $to             = trim(GETPOST('to','alphanohtml'));
    $search_action  = trim(GETPOST('search_action','alphanohtml'));
    $search_user    = trim(GETPOST('search_user','alphanohtml'));
    $search_element = trim(GETPOST('search_element','alphanohtml'));
    
    /* Defaults: last 30 days */
    if ($from === '') $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
    if ($to   === '') $to   = dol_print_date(dol_now(), '%Y-%m-%d');
    
    /* ---- Build WHERE ---- */
    $prefix = $db->prefix();
    $cond   = " WHERE entity=".(int)$conf->entity
            ." AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";
    
    if ($search_action !== '') {
        $cond .= " AND action LIKE '%".$db->escape($search_action)."%'";
    }
    if ($search_user !== '') {
        $cond .= " AND username LIKE '%".$db->escape($search_user)."%'";
    }
    if ($search_element !== '') {
        $cond .= " AND element_type LIKE '%".$db->escape($search_element)."%'";
    }
    
    /* Get fresh data for AJAX response */
    $totalCount = 0;
    $sql = "SELECT COUNT(*) as total FROM {$prefix}alt_user_activity {$cond}";
    $res = $db->query($sql);
    if ($res && ($obj = $db->fetch_object($res))) { $totalCount = (int)$obj->total; $db->free($res); }
    
    $byType = array();
    $sql = "SELECT action, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY action ORDER BY n DESC LIMIT 10";
    $res = $db->query($sql);
    if ($res) { while ($o = $db->fetch_object($res)) $byType[] = $o; $db->free($res); }
    
    $byUser = array();
    $sql = "SELECT username, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY username ORDER BY n DESC LIMIT 10";
    $res = $db->query($sql);
    if ($res) { while ($o = $db->fetch_object($res)) $byUser[] = $o; $db->free($res); }
    
    $recentActivities = array();
    $sql = "SELECT rowid, datestamp, action, element_type, username, ref 
            FROM {$prefix}alt_user_activity {$cond} 
            ORDER BY datestamp DESC LIMIT 20";
    $res = $db->query($sql);
    if ($res) { while ($o = $db->fetch_object($res)) $recentActivities[] = $o; $db->free($res); }
    
    /* Return JSON response */
    $response = array(
        'success' => true,
        'timestamp' => time(),
        'stats' => array(
            'total' => $totalCount,
            'uniqueActions' => count($byType),
            'activeUsers' => count($byUser),
            'dateRange' => $from . ' to ' . $to
        ),
        'chartData' => array(
            'activityType' => $byType,
            'userActivity' => $byUser
        ),
        'recentActivities' => $recentActivities
    );
    
    echo json_encode($response);
    exit;
}

/* ---- Inputs ---- */
$from           = trim(GETPOST('from','alphanohtml'));
$to             = trim(GETPOST('to','alphanohtml'));
$search_action  = trim(GETPOST('search_action','alphanohtml'));
$search_user    = trim(GETPOST('search_user','alphanohtml'));
$search_element = trim(GETPOST('search_element','alphanohtml'));

/* Defaults: last 30 days */
if ($from === '') $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
if ($to   === '') $to   = dol_print_date(dol_now(), '%Y-%m-%d');

/* ---- Build WHERE ---- */
$prefix = $db->prefix();
$cond   = " WHERE entity=".(int)$conf->entity
        ." AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";

if ($search_action !== '') {
    $cond .= " AND action LIKE '%".$db->escape($search_action)."%'";
}
if ($search_user !== '') {
    $cond .= " AND username LIKE '%".$db->escape($search_user)."%'";
}
if ($search_element !== '') {
    $cond .= " AND element_type LIKE '%".$db->escape($search_element)."%'";
}

/* ---- Stats queries ---- */
$byType = array();
$sql = "SELECT action, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY action ORDER BY n DESC LIMIT 10";
$res = $db->query($sql);
if ($res) { while ($o = $db->fetch_object($res)) $byType[] = $o; $db->free($res); }

$byUser = array();
$sql = "SELECT username, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY username ORDER BY n DESC LIMIT 10";
$res = $db->query($sql);
if ($res) { while ($o = $db->fetch_object($res)) $byUser[] = $o; $db->free($res); }

$byElement = array();
$sql = "SELECT element_type, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} AND element_type IS NOT NULL GROUP BY element_type ORDER BY n DESC LIMIT 10";
$res = $db->query($sql);
if ($res) { while ($o = $db->fetch_object($res)) $byElement[] = $o; $db->free($res); }

$timeline = array();
$sql = "SELECT DATE(datestamp) as d, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY DATE(datestamp) ORDER BY d ASC";
$res = $db->query($sql);
if ($res) { while ($o = $db->fetch_object($res)) $timeline[] = $o; $db->free($res); }

/* Totals & recent */
$totalCount = 0;
$sql = "SELECT COUNT(*) as total FROM {$prefix}alt_user_activity {$cond}";
$res = $db->query($sql);
if ($res && ($obj = $db->fetch_object($res))) { $totalCount = (int)$obj->total; $db->free($res); }

$recentActivities = array();
$sql = "SELECT rowid, datestamp, action, element_type, username, ref 
        FROM {$prefix}alt_user_activity {$cond} 
        ORDER BY datestamp DESC LIMIT 20";
$res = $db->query($sql);
if ($res) { while ($o = $db->fetch_object($res)) $recentActivities[] = $o; $db->free($res); }

/* Opportunistic retention cleanup */
$days = getDolGlobalInt('USERACTIVITYTRACKER_RETENTION_DAYS', 365);
$db->query("DELETE FROM ".$db->prefix()."alt_user_activity 
            WHERE datestamp < DATE_SUB(NOW(), INTERVAL ".((int)$days)." DAY) 
              AND entity=".(int)$conf->entity);

/* ---- View ---- */
llxHeader('', 'User Activity — Dashboard');

/* Include modern assets */
print '<link rel="stylesheet" href="'.dol_buildpath('/useractivitytracker/assets/css/dashboard-modern.css', 1).'">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
print '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.min.js"></script>';
print '<script src="'.dol_buildpath('/useractivitytracker/assets/js/dashboard-modern.js', 1).'"></script>';

print '<div class="dashboard-container">';

/* Dark Mode Toggle */
print '<button id="themeToggle" class="theme-toggle" title="Toggle Dark Mode">';
print '<i class="fas fa-moon"></i>';
print '</button>';

print load_fiche_titre('User Activity — Dashboard', '', 'object_useractivitytracker@useractivitytracker');

/* Modern Filter Panel */
print '<div class="filter-panel">';
print '<form method="get">';
print '<div class="d-flex align-items-center justify-content-between mb-3">';
print '<h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>';
print '<div>';
print '<button type="button" id="refreshData" class="btn btn-outline-primary" style="margin-right: 10px;">';
print '<i class="fas fa-sync-alt"></i> Refresh';
print '</button>';
print '<label style="margin-right: 15px;">';
print '<input type="checkbox" id="autoRefresh" style="margin-right: 5px;"> Auto-refresh';
print '</label>';
print '<button type="button" id="advancedSearchToggle" class="btn btn-outline-primary">';
print '<i class="fas fa-chevron-down"></i> Show Advanced';
print '</button>';
print '</div>';
print '</div>';

print '<div class="filter-row">';
print '<div class="filter-group">';
print '<label for="from">From Date</label>';
print '<input type="date" name="from" id="from" value="'.dol_escape_htmltag($from).'">';
print '</div>';
print '<div class="filter-group">';
print '<label for="to">To Date</label>';
print '<input type="date" name="to" id="to" value="'.dol_escape_htmltag($to).'">';
print '</div>';
print '<div class="filter-group">';
print '<label for="search_action">Action</label>';
print '<input type="text" name="search_action" id="search_action" value="'.dol_escape_htmltag($search_action).'" placeholder="e.g. COMPANY_CREATE">';
print '</div>';
print '<div class="filter-group">';
print '<label for="search_user">User</label>';
print '<input type="text" name="search_user" id="search_user" value="'.dol_escape_htmltag($search_user).'" placeholder="Username">';
print '</div>';
print '<div class="filter-group">';
print '<label for="search_element">Element</label>';
print '<input type="text" name="search_element" id="search_element" value="'.dol_escape_htmltag($search_element).'" placeholder="Element type">';
print '</div>';
print '<div class="filter-group">';
print '<label>&nbsp;</label>';
print '<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>';
print '</div>';
print '</div>';

print '<div id="advancedSearchPanel" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">';
print '<div class="filter-row">';
print '<div class="filter-group">';
print '<label for="severity_filter">Severity</label>';
print '<select name="severity_filter" id="severity_filter">';
print '<option value="">All Severities</option>';
print '<option value="info">Info</option>';
print '<option value="warning">Warning</option>';
print '<option value="error">Error</option>';
print '<option value="notice">Notice</option>';
print '</select>';
print '</div>';
print '<div class="filter-group">';
print '<label for="ip_filter">IP Address</label>';
print '<input type="text" name="ip_filter" id="ip_filter" placeholder="192.168.1.1">';
print '</div>';
print '<div class="filter-group">';
print '<label for="limit_results">Results Limit</label>';
print '<select name="limit_results" id="limit_results">';
print '<option value="20">20 results</option>';
print '<option value="50">50 results</option>';
print '<option value="100">100 results</option>';
print '<option value="200">200 results</option>';
print '</select>';
print '</div>';
print '<div class="filter-group">';
print '<label>&nbsp;</label>';
print '<a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="btn btn-secondary"><i class="fas fa-times"></i> Clear All</a>';
print '</div>';
print '</div>';
print '</div>';

print '</form>';
print '</div>';

/* Modern Statistics Cards */
print '<div class="stats-grid">';
print '<div class="stat-card">';
print '<i class="fas fa-chart-bar stat-icon"></i>';
print '<span class="stat-number" id="totalActivities">'.$totalCount.'</span>';
print '<div class="stat-label">Total Activities</div>';
print '</div>';
print '<div class="stat-card success">';
print '<i class="fas fa-users stat-icon"></i>';
print '<span class="stat-number" id="activeUsers">'.count($byUser).'</span>';
print '<div class="stat-label">Active Users</div>';
print '</div>';
print '<div class="stat-card info">';
print '<i class="fas fa-cogs stat-icon"></i>';
print '<span class="stat-number" id="uniqueActions">'.count($byType).'</span>';
print '<div class="stat-label">Unique Actions</div>';
print '</div>';
print '<div class="stat-card warning">';
print '<i class="fas fa-calendar stat-icon"></i>';
print '<span class="stat-number">'.count($timeline).'</span>';
print '<div class="stat-label">Active Days</div>';
print '</div>';
print '</div>';

/* Export and Control Buttons */
$export_base = dol_buildpath('/useractivitytracker/scripts/export.php', 1);
print '<div class="text-center mb-4">';
print '<div class="btn-group" role="group">';
print '<a class="btn btn-success" href="'.$export_base.'?format=csv&from='.urlencode($from).'&to='.urlencode($to)
    .'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user)
    .'&search_element='.urlencode($search_element).'">';
print '<i class="fas fa-file-csv"></i> Export CSV</a>';
print '<a class="btn btn-success" href="'.$export_base.'?format=xls&from='.urlencode($from).'&to='.urlencode($to)
    .'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user)
    .'&search_element='.urlencode($search_element).'">';
print '<i class="fas fa-file-excel"></i> Export XLS</a>';
print '<button id="exportPDF" class="btn btn-outline-primary">';
print '<i class="fas fa-file-pdf"></i> Export PDF</button>';
print '</div>';
print '</div>';

/* Modern Dashboard Cards with Charts */
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">';

/* Activity by Type Card with Chart */
print '<div class="dashboard-card">';
print '<div class="card-header">';
print '<span><i class="fas fa-chart-pie"></i> Activity by Type</span>';
print '</div>';
print '<div class="card-body">';
print '<div style="display: grid; grid-template-columns: 1fr 300px; gap: 1rem; align-items: start;">';
print '<div class="chart-wrapper">';
print '<canvas id="activityTypeChart"></canvas>';
print '</div>';
print '<div>';
print '<table class="data-table activity-type-table">';
print '<thead><tr><th>Action</th><th style="text-align: right;">Count</th></tr></thead>';
print '<tbody>';
if ($byType) {
    foreach ($byType as $r) {
        $pct = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
        print '<tr><td>'.dol_escape_htmltag($r->action).'</td><td style="text-align: right;">'.$r->n.' ('.$pct.'%)</td></tr>';
    }
} else {
    print '<tr><td colspan="2">No data found</td></tr>';
}
print '</tbody></table>';
print '</div>';
print '</div>';
print '</div>';
print '</div>';

/* Activity by User Card with Chart */
print '<div class="dashboard-card">';
print '<div class="card-header">';
print '<span><i class="fas fa-users"></i> Activity by User</span>';
print '</div>';
print '<div class="card-body">';
print '<div style="display: grid; grid-template-columns: 1fr 300px; gap: 1rem; align-items: start;">';
print '<div class="chart-wrapper">';
print '<canvas id="userActivityChart"></canvas>';
print '</div>';
print '<div>';
print '<table class="data-table user-activity-table">';
print '<thead><tr><th>User</th><th style="text-align: right;">Count</th></tr></thead>';
print '<tbody>';
if ($byUser) {
    foreach ($byUser as $r) {
        $pct = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
        print '<tr><td>'.dol_escape_htmltag($r->username).'</td><td style="text-align: right;">'.$r->n.' ('.$pct.'%)</td></tr>';
    }
} else {
    print '<tr><td colspan="2">No data found</td></tr>';
}
print '</tbody></table>';
print '</div>';
print '</div>';
print '</div>';
print '</div>';

print '</div>';

/* Second Row of Cards */
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">';

/* Element Type Card */
print '<div class="dashboard-card">';
print '<div class="card-header">';
print '<span><i class="fas fa-cubes"></i> Activity by Element Type</span>';
print '</div>';
print '<div class="card-body">';
print '<table class="data-table element-type-table">';
print '<thead><tr><th>Element Type</th><th style="text-align: right;">Count</th></tr></thead>';
print '<tbody>';
if ($byElement) {
    foreach ($byElement as $r) {
        $pct = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
        print '<tr><td>'.dol_escape_htmltag($r->element_type).'</td><td style="text-align: right;">'.$r->n.' ('.$pct.'%)</td></tr>';
    }
} else {
    print '<tr><td colspan="2">No data found</td></tr>';
}
print '</tbody></table>';
print '</div>';
print '</div>';

/* Recent Activities Card */
print '<div class="dashboard-card">';
print '<div class="card-header">';
print '<span><i class="fas fa-history"></i> Recent Activities</span>';
print '<div>';
print '<button class="btn btn-outline-primary" onclick="dashboard.refreshData()" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">';
print '<i class="fas fa-sync-alt"></i>';
print '</button>';
print '</div>';
print '</div>';
print '<div class="card-body recent-activities-container">';
if ($recentActivities) {
    $view_base = dol_buildpath('/useractivitytracker/admin/useractivitytracker_view.php', 1);
    foreach ($recentActivities as $r) {
        $time = dol_print_date(dol_stringtotime($r->datestamp), '%H:%M');
        $date = dol_print_date(dol_stringtotime($r->datestamp), '%d/%m');
        print '<div class="activity-item" style="display: flex; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">';
        print '<div style="flex: 1;">';
        print '<div style="font-weight: 500;">';
        print '<a href="'.$view_base.'?id='.(int)$r->rowid.'" style="text-decoration: none; color: var(--primary-color);" title="View details">'.dol_escape_htmltag($r->action).'</a>';
        print '</div>';
        print '<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">';
        print '<i class="fas fa-user"></i> '.dol_escape_htmltag($r->username);
        if (!empty($r->element_type)) print ' | <i class="fas fa-cube"></i> '.dol_escape_htmltag($r->element_type);
        if (!empty($r->ref)) print '<br><em>'.dol_escape_htmltag($r->ref).'</em>';
        print '</div>';
        print '</div>';
        print '<div style="text-align: right; font-size: 0.75rem; color: var(--text-secondary);">';
        print '<div>'.$date.'</div>';
        print '<div>'.$time.'</div>';
        print '</div>';
        print '</div>';
    }
    print '<div id="paginationControls" style="margin-top: 1rem;"></div>';
} else {
    print '<div style="text-align: center; padding: 2rem; color: var(--text-secondary);">';
    print '<i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>';
    print 'No recent activities found';
    print '</div>';
}
print '</div>';
print '</div>';

print '</div>';

/* Activity Timeline Chart */
print '<div class="dashboard-card">';
print '<div class="card-header">';
print '<span><i class="fas fa-chart-line"></i> Activity Timeline</span>';
print '</div>';
print '<div class="card-body">';

if (!empty($timeline)) {
    print '<div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; align-items: start;">';
    print '<div class="chart-wrapper">';
    print '<canvas id="timelineChart"></canvas>';
    print '</div>';
    print '<div>';
    print '<table class="data-table timeline-table">';
    print '<thead><tr><th>Date</th><th style="text-align: right;">Count</th></tr></thead>';
    print '<tbody>';
    
    // Determine max for bar visualization
    $max_count = 0;
    foreach ($timeline as $r) { if ((int)$r->n > $max_count) $max_count = (int)$r->n; }
    
    foreach ($timeline as $r) {
        $bar_width = $max_count > 0 ? min(100, ((int)$r->n / $max_count) * 100) : 0;
        print '<tr>';
        print '<td>'.dol_print_date(dol_stringtotime($r->d), '%d/%m/%Y').'</td>';
        print '<td style="text-align: right; position: relative;">';
        print '<span style="position: relative; z-index: 2;">'.(int)$r->n.'</span>';
        if ($bar_width > 0) {
            print '<div class="progress" style="margin-top: 0.25rem;">';
            print '<div class="progress-bar" style="width: '.round($bar_width,1).'%;"></div>';
            print '</div>';
        }
        print '</td>';
        print '</tr>';
    }
    print '</tbody></table>';
    print '</div>';
    print '</div>';
} else {
    print '<div style="text-align: center; padding: 2rem; color: var(--text-secondary);">';
    print '<i class="fas fa-chart-line" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>';
    print 'No timeline data available for the selected period';
    print '</div>';
}
print '</div>';
print '</div>';

print '</div>'; /* Close dashboard-container */

llxFooter();
