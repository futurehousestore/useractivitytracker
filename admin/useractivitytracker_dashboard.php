<?php
/**
 * Dashboard page
 * Path: custom/useractivitytracker/admin/useractivitytracker_dashboard.php
 * Version: 2.6.0 — adds time-on-page analytics; fixes filters & buttons; robust AJAX
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

/* ---- Helpers ---- */
function uat_build_where($db, $conf, $from, $to, $search_action, $search_user, $search_element, $severity_filter, $ip_filter) {
    $cond   = " WHERE entity=".(int)$conf->entity
            ." AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";

    if ($search_action !== '')  $cond .= " AND action LIKE '%".$db->escape($search_action)."%'";
    if ($search_user   !== '')  $cond .= " AND username LIKE '%".$db->escape($search_user)."%'";
    if ($search_element!== '')  $cond .= " AND element_type LIKE '%".$db->escape($search_element)."%'";
    if ($severity_filter!== '') $cond .= " AND severity = '".$db->escape($severity_filter)."'";
    if ($ip_filter     !== '')  $cond .= " AND ip LIKE '%".$db->escape($ip_filter)."%'";
    return $cond;
}

/* ---- AJAX handler ---- */
if (GETPOST('ajax', 'int') == 1) {
    header('Content-Type: application/json');

    // Inputs
    $from           = trim(GETPOST('from','alphanohtml'));
    $to             = trim(GETPOST('to','alphanohtml'));
    $search_action  = trim(GETPOST('search_action','alphanohtml'));
    $search_user    = trim(GETPOST('search_user','alphanohtml'));
    $search_element = trim(GETPOST('search_element','alphanohtml'));
    $severity_filter= trim(GETPOST('severity_filter','alphanohtml'));
    $ip_filter      = trim(GETPOST('ip_filter','alphanohtml'));
    $limit_results  = max(1, (int)GETPOST('limit_results','int'));
    if ($limit_results <= 0) $limit_results = 20;

    // Defaults: last 30 days
    if ($from === '') $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
    if ($to   === '') $to   = dol_print_date(dol_now(), '%Y-%m-%d');

    $prefix = $db->prefix();
    $cond   = uat_build_where($db, $conf, $from, $to, $search_action, $search_user, $search_element, $severity_filter, $ip_filter);

    // Totals
    $totalCount = 0;
    $sql = "SELECT COUNT(*) as total FROM {$prefix}alt_user_activity {$cond}";
    $res = $db->query($sql);
    if ($res && ($obj = $db->fetch_object($res))) { $totalCount = (int)$obj->total; $db->free($res); }

    // By action
    $byType = array();
    $sql = "SELECT action, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY action ORDER BY n DESC LIMIT 10";
    $res = $db->query($sql);
    if ($res) { while ($o = $db->fetch_object($res)) $byType[] = $o; $db->free($res); }

    // By user
    $byUser = array();
    $sql = "SELECT username, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY username ORDER BY n DESC LIMIT 10";
    $res = $db->query($sql);
    if ($res) { while ($o = $db->fetch_object($res)) $byUser[] = $o; $db->free($res); }

    // Recent activities
    $recentActivities = array();
    $sql = "SELECT rowid, datestamp, action, element_type, username, ref, severity 
            FROM {$prefix}alt_user_activity {$cond}
            ORDER BY datestamp DESC LIMIT ".(int)$limit_results;
    $res = $db->query($sql);
    if ($res) { while ($o = $db->fetch_object($res)) $recentActivities[] = $o; $db->free($res); }

    // Top pages by time (PAGE_TIME)
    $topPages = array();
    $sql = "SELECT ref AS uri, COUNT(*) AS visits, SUM(kpi1) AS total_sec, ROUND(AVG(kpi1),1) AS avg_sec
            FROM {$prefix}alt_user_activity {$cond} AND action='PAGE_TIME' AND ref IS NOT NULL
            GROUP BY ref ORDER BY total_sec DESC LIMIT 10";
    $res = $db->query($sql);
    if ($res) { while ($o = $db->fetch_object($res)) $topPages[] = $o; $db->free($res); }

    // Time by day (timeline of seconds)
    $timeSeries = array();
    $sql = "SELECT DATE(datestamp) AS d, SUM(kpi1) AS sec
            FROM {$prefix}alt_user_activity {$cond} AND action='PAGE_TIME'
            GROUP BY DATE(datestamp) ORDER BY d ASC";
    $res = $db->query($sql);
    if ($res) { while ($o = $db->fetch_object($res)) $timeSeries[] = $o; $db->free($res); }

    // Per-user dwell (aggregates)
    $userTime = array();
    $sql = "SELECT username, COUNT(*) AS visits, SUM(kpi1) AS total_sec, ROUND(AVG(kpi1),1) AS avg_sec
            FROM {$prefix}alt_user_activity {$cond} AND action='PAGE_TIME'
            GROUP BY username ORDER BY total_sec DESC LIMIT 10";
    $res = $db->query($sql);
    if ($res) { while ($o = $db->fetch_object($res)) $userTime[] = $o; $db->free($res); }

    echo json_encode(array(
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
            'userActivity' => $byUser,
            'topPages'     => $topPages,
            'timeSeries'   => $timeSeries,
            'userTime'     => $userTime
        ),
        'recentActivities' => $recentActivities
    ));
    exit;
}

/* ---- Inputs (non-AJAX first render) ---- */
$from           = trim(GETPOST('from','alphanohtml'));
$to             = trim(GETPOST('to','alphanohtml'));
$search_action  = trim(GETPOST('search_action','alphanohtml'));
$search_user    = trim(GETPOST('search_user','alphanohtml'));
$search_element = trim(GETPOST('search_element','alphanohtml'));
$severity_filter= trim(GETPOST('severity_filter','alphanohtml'));
$ip_filter      = trim(GETPOST('ip_filter','alphanohtml'));
$limit_results  = (int)GETPOST('limit_results','int');
if ($limit_results <= 0) $limit_results = 20;

/* Defaults: last 30 days */
if ($from === '') $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
if ($to   === '') $to   = dol_print_date(dol_now(), '%Y-%m-%d');

/* ---- Build WHERE ---- */
$prefix = $db->prefix();
$cond   = uat_build_where($db, $conf, $from, $to, $search_action, $search_user, $search_element, $severity_filter, $ip_filter);

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

/* Time analytics */
$topPages = array();
$sql = "SELECT ref AS uri, COUNT(*) AS visits, SUM(kpi1) AS total_sec, ROUND(AVG(kpi1),1) AS avg_sec
        FROM {$prefix}alt_user_activity {$cond} AND action='PAGE_TIME' AND ref IS NOT NULL
        GROUP BY ref ORDER BY total_sec DESC LIMIT 10";
$res = $db->query($sql);
if ($res) { while ($o = $db->fetch_object($res)) $topPages[] = $o; $db->free($res); }

$userTime = array();
$sql = "SELECT username, COUNT(*) AS visits, SUM(kpi1) AS total_sec, ROUND(AVG(kpi1),1) AS avg_sec
        FROM {$prefix}alt_user_activity {$cond} AND action='PAGE_TIME'
        GROUP BY username ORDER BY total_sec DESC LIMIT 10";
$res = $db->query($sql);
if ($res) { while ($o = $db->fetch_object($res)) $userTime[] = $o; $db->free($res); }

$timeSeries = array();
$sql = "SELECT DATE(datestamp) AS d, SUM(kpi1) AS sec
        FROM {$prefix}alt_user_activity {$cond} AND action='PAGE_TIME'
        GROUP BY DATE(datestamp) ORDER BY d ASC";
$res = $db->query($sql);
if ($res) { while ($o = $db->fetch_object($res)) $timeSeries[] = $o; $db->free($res); }

/* Totals & recent */
$totalCount = 0;
$sql = "SELECT COUNT(*) as total FROM {$prefix}alt_user_activity {$cond}";
$res = $db->query($sql);
if ($res && ($obj = $db->fetch_object($res))) { $totalCount = (int)$obj->total; $db->free($res); }

$recentActivities = array();
$sql = "SELECT rowid, datestamp, action, element_type, username, ref, severity 
        FROM {$prefix}alt_user_activity {$cond} 
        ORDER BY datestamp DESC LIMIT ".(int)$limit_results;
$res = $db->query($sql);
if ($res) { while ($o = $db->fetch_object($res)) $recentActivities[] = $o; $db->free($res); }

/* Opportunistic retention cleanup */
$days = getDolGlobalInt('USERACTIVITYTRACKER_RETENTION_DAYS', 365);
$db->query("DELETE FROM ".$db->prefix()."alt_user_activity 
            WHERE datestamp < DATE_SUB(NOW(), INTERVAL ".((int)$days)." DAY) 
              AND entity=".(int)$conf->entity);

/* ---- View ---- */
llxHeader('', 'User Activity — Dashboard');

/* Include assets */
print '<link rel="stylesheet" href="'.dol_buildpath('/useractivitytracker/assets/css/dashboard-modern.css', 1).'">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
print '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.min.js"></script>';

/* Container */
print '<div class="dashboard-container">';
print '<div class="dashboard-content" id="dashboardContent">';

/* Title + theme toggle */
print '<button id="themeToggle" class="theme-toggle" title="Toggle Dark Mode"><i class="fas fa-moon"></i></button>';
print load_fiche_titre('User Activity — Dashboard v2.6.0', '', 'object_useractivitytracker@useractivitytracker');

/* Stats cards */
print '<div class="stats-grid">';
print '<div class="stat-card success"><div class="stat-value">'.$totalCount.'</div><div class="stat-label">Total Activities</div></div>';
$activeUsers = count($byUser);
print '<div class="stat-card info"><div class="stat-value">'.$activeUsers.'</div><div class="stat-label">Active Users</div></div>';
$recentCount = count($recentActivities);
print '<div class="stat-card warning"><div class="stat-value">'.$recentCount.'</div><div class="stat-label">Recent Actions</div></div>';
// Total dwell seconds in range
$totalSec = 0; foreach ($userTime as $urow) { $totalSec += (int)$urow->total_sec; }
print '<div class="stat-card"><div class="stat-value">'.(int)$totalSec.'</div><div class="stat-label">Total Time (sec)</div></div>';
print '</div>';

/* When empty, show diagnostics */
if ($totalCount == 0) {
    require_once dirname(__DIR__).'/class/useractivity.class.php';
    $activity = new UserActivity($db);
    $diagnostics = $activity->getDiagnostics($conf->entity);

    print '<div class="card diagnostic-card" style="margin:20px 0;border-left:4px solid #f39c12;">';
    print '<div class="card-header" style="background:#fff3cd;color:#856404;"><span><i class="fas fa-exclamation-triangle"></i> No Activity Data Found - Diagnostics</span></div>';
    print '<div class="card-body" style="background:#fefefe;">';

    if (!$diagnostics['table_exists']) {
        print '<div class="alert alert-danger" style="padding:15px;margin:10px 0;background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;color:#721c24;">';
        print '<strong>❌ Critical:</strong> Table <code>'.$db->prefix().'alt_user_activity</code> not found. Disable/enable the module to create it.';
        print '</div>';
    } else {
        print '<div class="alert alert-info" style="padding:15px;margin:10px 0;background:#d1ecf1;border:1px solid #bee5eb;border-radius:4px;color:#0c5460;">';
        print '<strong>✅ Table:</strong> exists with '.count($diagnostics['table_columns']).' columns<br>';
        print '<strong>📊 Last 7d:</strong> '.$diagnostics['recent_activity_count'].' rows';
        if ($diagnostics['latest_activity']) {
            $latest_date = dol_print_date($db->jdate($diagnostics['latest_activity']['datestamp']), 'dayhour');
            print '<br><strong>📅 Latest:</strong> '.$diagnostics['latest_activity']['action'].' by '.$diagnostics['latest_activity']['username'].' on '.$latest_date;
        }
        print '</div>';
    }
    print '</div></div>';
}

/* Top toolbar */
$export_base = dol_buildpath('/useractivitytracker/scripts/export.php', 1);
print '<div class="d-flex justify-content-between align-items-center mb-4">';
print '<div>';
print '<a class="btn btn-success" href="'.$export_base.'?format=csv&from='.urlencode($from).'&to='.urlencode($to).'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user).'&search_element='.urlencode($search_element).'&severity_filter='.urlencode($severity_filter).'&ip_filter='.urlencode($ip_filter).'">';
print '<i class="fas fa-file-csv"></i> Export CSV</a> ';
print '<a class="btn btn-success" href="'.$export_base.'?format=xls&from='.urlencode($from).'&to='.urlencode($to).'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user).'&search_element='.urlencode($search_element).'&severity_filter='.urlencode($severity_filter).'&ip_filter='.urlencode($ip_filter).'">';
print '<i class="fas fa-file-excel"></i> Export XLS</a> ';
print '<button id="exportPDF" class="btn btn-outline-danger"><i class="fas fa-file-pdf"></i> Export PDF</button> ';
print '<button id="compareUsers" class="btn btn-outline-info"><i class="fas fa-balance-scale"></i> Compare Users</button> ';
print '<button id="dashboardSettings" class="btn btn-outline-secondary"><i class="fas fa-cogs"></i> Settings</button>';
print '</div>';
print '<div class="version-badge"><i class="fas fa-tag"></i> v2.6.0</div>';
print '</div>';

/* Filters */
print '<div class="filter-panel"><form method="get">';
print '<div class="d-flex align-items-center justify-content-between mb-3">';
print '<h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>';
print '<div>';
print '<button type="button" id="refreshData" class="btn btn-outline-primary" style="margin-right:10px;"><i class="fas fa-sync-alt"></i> Refresh</button>';
print '<label style="margin-right:15px;"><input type="checkbox" id="autoRefresh" style="margin-right:5px;"> Auto-refresh</label>';
print '<button type="button" id="advancedSearchToggle" class="btn btn-outline-primary"><i class="fas fa-chevron-down"></i> Show Advanced</button>';
print '</div></div>';

print '<div class="filter-row">';
print '<div class="filter-group"><label for="from">From Date</label><input type="date" name="from" id="from" value="'.dol_escape_htmltag($from).'"></div>';
print '<div class="filter-group"><label for="to">To Date</label><input type="date" name="to" id="to" value="'.dol_escape_htmltag($to).'"></div>';
print '<div class="filter-group"><label for="search_action">Action</label><input type="text" name="search_action" id="search_action" value="'.dol_escape_htmltag($search_action).'" placeholder="e.g. COMPANY_CREATE"></div>';
print '<div class="filter-group"><label for="search_user">User</label><input type="text" name="search_user" id="search_user" value="'.dol_escape_htmltag($search_user).'" placeholder="Username"></div>';
print '<div class="filter-group"><label for="search_element">Element</label><input type="text" name="search_element" id="search_element" value="'.dol_escape_htmltag($search_element).'" placeholder="Element type"></div>';
print '<div class="filter-group"><label>&nbsp;</label><button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button></div>';
print '</div>';

print '<div id="advancedSearchPanel" style="display:none;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border-color);">';
print '<div class="filter-row">';
print '<div class="filter-group"><label for="severity_filter">Severity</label><select name="severity_filter" id="severity_filter">';
$sevOps = array('' => 'All Severities', 'info'=>'Info','warning'=>'Warning','error'=>'Error','notice'=>'Notice');
foreach ($sevOps as $k=>$label) {
    print '<option value="'.dol_escape_htmltag($k).'"'.($k===$severity_filter?' selected':'').'>'.dol_escape_htmltag($label).'</option>';
}
print '</select></div>';
print '<div class="filter-group"><label for="ip_filter">IP Address</label><input type="text" name="ip_filter" id="ip_filter" value="'.dol_escape_htmltag($ip_filter).'" placeholder="192.168.1.1"></div>';
print '<div class="filter-group"><label for="limit_results">Results Limit</label><select name="limit_results" id="limit_results">';
foreach (array(20,50,100,200,500) as $opt) {
    print '<option value="'.$opt.'"'.($limit_results==$opt?' selected':'').'>'.$opt.' results</option>';
}
print '</select></div>';
print '<div class="filter-group"><label>&nbsp;</label><a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="btn btn-secondary"><i class="fas fa-times"></i> Clear All</a></div>';
print '</div></div>';

print '</form></div>';

/* First row: Activity by Type + Activity by User */
print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:2rem;">';

/* Activity by Type */
print '<div class="dashboard-card"><div class="card-header"><span><i class="fas fa-chart-pie"></i> Activity by Type</span></div><div class="card-body">';
print '<div style="display:grid;grid-template-columns:1fr 300px;gap:1rem;align-items:start;">';
print '<div class="chart-wrapper"><canvas id="activityTypeChart"></canvas></div>';
print '<div><table class="data-table"><thead><tr><th>Action</th><th style="text-align:right;">Count</th></tr></thead><tbody>';
if ($byType) {
    foreach ($byType as $r) {
        $pct = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
        print '<tr><td>'.dol_escape_htmltag($r->action).'</td><td style="text-align:right;">'.$r->n.' ('.$pct.'%)</td></tr>';
    }
} else print '<tr><td colspan="2">No data found</td></tr>';
print '</tbody></table></div></div></div></div>';

/* Activity by User */
print '<div class="dashboard-card"><div class="card-header"><span><i class="fas fa-users"></i> Activity by User</span></div><div class="card-body">';
print '<div style="display:grid;grid-template-columns:1fr 300px;gap:1rem;align-items:start;">';
print '<div class="chart-wrapper"><canvas id="userActivityChart"></canvas></div>';
print '<div><table class="data-table"><thead><tr><th>User</th><th style="text-align:right;">Count</th></tr></thead><tbody>';
if ($byUser) {
    foreach ($byUser as $r) {
        $pct = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
        print '<tr><td>'.dol_escape_htmltag($r->username).'</td><td style="text-align:right;">'.$r->n.' ('.$pct.'%)</td></tr>';
    }
} else print '<tr><td colspan="2">No data found</td></tr>';
print '</tbody></table></div></div></div></div>';

print '</div>'; // end first grid

/* Second row: Element type + Recent Activities */
print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:2rem;">';

/* Element Type */
print '<div class="dashboard-card"><div class="card-header"><span><i class="fas fa-cubes"></i> Activity by Element Type</span></div><div class="card-body">';
print '<table class="data-table"><thead><tr><th>Element Type</th><th style="text-align:right;">Count</th></tr></thead><tbody>';
if ($byElement) {
    foreach ($byElement as $r) {
        $pct = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
        print '<tr><td>'.dol_escape_htmltag($r->element_type).'</td><td style="text-align:right;">'.$r->n.' ('.$pct.'%)</td></tr>';
    }
} else print '<tr><td colspan="2">No data found</td></tr>';
print '</tbody></table></div></div>';

/* Recent Activities */
print '<div class="dashboard-card"><div class="card-header"><span><i class="fas fa-history"></i> Recent Activities</span>';
print '<div class="card-tools"><button class="card-tool" id="btnRefreshActivities" title="Refresh Activities"><i class="fas fa-sync-alt"></i></button></div>';
print '</div><div class="card-body recent-activities-container">';

if ($recentActivities) {
    $view_base = dol_buildpath('/useractivitytracker/admin/useractivitytracker_view.php', 1);
    print '<div class="activity-timeline">';
    foreach ($recentActivities as $index => $r) {
        $fullDate = dol_print_date(dol_stringtotime($r->datestamp), 'dayhour');
        $activityType = 'info';
        $al = strtolower($r->action);
        if (strpos($al,'delete')!==false || strpos($al,'fail')!==false || $r->severity==='error') $activityType='danger';
        elseif (strpos($al,'create')!==false || strpos($al,'add')!==false) $activityType='success';
        elseif (strpos($al,'update')!==false || strpos($al,'edit')!==false) $activityType='warning';

        print '<div class="timeline-item '.$activityType.'">';
        print '<div class="timeline-content">';
        print '<div class="timeline-header">';
        print '<a href="'.$view_base.'?id='.(int)$r->rowid.'" class="timeline-action">'.dol_escape_htmltag($r->action).'</a>';
        print '<span class="timeline-time"><i class="fas fa-clock"></i> '.$fullDate.'</span>';
        print '</div>';
        $details = array();
        if (!empty($r->element_type)) $details[] = 'Element: '.dol_escape_htmltag($r->element_type);
        if (!empty($r->ref)) $details[] = 'Ref: '.dol_escape_htmltag($r->ref);
        if ($details) print '<div class="timeline-details">'.implode(' | ',$details).'</div>';
        print '<div class="timeline-meta"><span><i class="fas fa-user"></i> '.dol_escape_htmltag($r->username).'</span>';
        if (!empty($r->severity)) print ' <span class="badge">'.strtoupper($r->severity).'</span>';
        print '</div></div></div>';
    }
    print '</div>';
} else {
    print '<div class="text-center py-4"><div class="mb-3"><i class="fas fa-inbox" style="font-size:3rem;color:var(--text-muted);"></i></div><h5 class="text-muted">No Recent Activities</h5></div>';
}
print '</div></div>';

print '</div>'; // end second grid

/* Third row: Time-on-page analytics */
print '<div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:2rem;">';

/* Time Series (seconds per day) */
print '<div class="dashboard-card"><div class="card-header"><span><i class="fas fa-chart-line"></i> Time Spent per Day (seconds)</span></div><div class="card-body">';
if (!empty($timeSeries)) {
    print '<div class="chart-wrapper"><canvas id="timeSeriesChart"></canvas></div>';
    print '<table class="data-table" style="margin-top:1rem;"><thead><tr><th>Date</th><th style="text-align:right;">Seconds</th></tr></thead><tbody>';
    foreach ($timeSeries as $row) {
        print '<tr><td>'.dol_print_date(dol_stringtotime($row->d), '%d/%m/%Y').'</td><td style="text-align:right;">'.(int)$row->sec.'</td></tr>';
    }
    print '</tbody></table>';
} else {
    print '<div style="text-align:center;padding:2rem;color:var(--text-secondary);">No time data in range</div>';
}
print '</div></div>';

/* Top Pages by Time */
print '<div class="dashboard-card"><div class="card-header"><span><i class="fas fa-stopwatch"></i> Top Pages by Total Time</span></div><div class="card-body">';
print '<div class="chart-wrapper"><canvas id="topPagesChart"></canvas></div>';
print '<table class="data-table" style="margin-top:1rem;"><thead><tr><th>Page (URI)</th><th style="text-align:right;">Visits</th><th style="text-align:right;">Total sec</th><th style="text-align:right;">Avg sec</th></tr></thead><tbody>';
if ($topPages) {
    foreach ($topPages as $p) {
        print '<tr>';
        print '<td>'.dol_escape_htmltag($p->uri).'</td>';
        print '<td style="text-align:right;">'.(int)$p->visits.'</td>';
        print '<td style="text-align:right;">'.(int)$p->total_sec.'</td>';
        print '<td style="text-align:right;">'.(float)$p->avg_sec.'</td>';
        print '</tr>';
    }
} else print '<tr><td colspan="4">No time data found</td></tr>';
print '</tbody></table></div></div>';

print '</div>'; // end third grid

/* Fourth row: Per-user dwell time */
print '<div class="dashboard-card"><div class="card-header"><span><i class="fas fa-user-clock"></i> Per-user Time on Pages</span></div><div class="card-body">';
print '<div class="chart-wrapper"><canvas id="userTimeChart"></canvas></div>';
print '<table class="data-table" style="margin-top:1rem;"><thead><tr><th>User</th><th style="text-align:right;">Visits</th><th style="text-align:right;">Total sec</th><th style="text-align:right;">Avg sec</th></tr></thead><tbody>';
if ($userTime) {
    foreach ($userTime as $u) {
        print '<tr>';
        print '<td>'.dol_escape_htmltag($u->username).'</td>';
        print '<td style="text-align:right;">'.(int)$u->visits.'</td>';
        print '<td style="text-align:right;">'.(int)$u->total_sec.'</td>';
        print '<td style="text-align:right;">'.(float)$u->avg_sec.'</td>';
        print '</tr>';
    }
} else print '<tr><td colspan="4">No time data found</td></tr>';
print '</tbody></table></div></div>';

/* Close containers */
print '</div>'; // dashboard-content
print '</div>'; // dashboard-container

/* jsPDF (for Export PDF button) */
print '<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>';

/* Inline JS to wire charts & buttons (no external JS dependency required) */
$js_byType     = json_encode($byType);
$js_byUser     = json_encode($byUser);
$js_topPages   = json_encode($topPages);
$js_timeSeries = json_encode($timeSeries);
$js_userTime   = json_encode($userTime);
$ajax_url      = dol_buildpath('/useractivitytracker/admin/useractivitytracker_dashboard.php', 1);

print '<script>
(function(){
  function qs(id){return document.getElementById(id);}
  function toLabelsVals(rows, lk, vk){var L=[], V=[]; (rows||[]).forEach(function(r){L.push(r[lk]); V.push(Number(r[vk]||0));}); return {labels:L, values:V};}

  // Charts
  function buildBarChart(canvasId, labels, data, title){
    if (!window.Chart) return;
    var ctx = document.getElementById(canvasId);
    if (!ctx) return;
    new Chart(ctx, {
      type: "bar",
      data: { labels: labels, datasets: [{ label: title||"", data: data }]},
      options: { responsive: true, plugins: { legend: { display: !!title }}, scales: { y: { beginAtZero: true } } }
    });
  }
  function buildLineChart(canvasId, labels, data, title){
    if (!window.Chart) return;
    var ctx = document.getElementById(canvasId);
    if (!ctx) return;
    new Chart(ctx, {
      type: "line",
      data: { labels: labels, datasets: [{ label: title||"", data: data, tension: .2 }]},
      options: { responsive: true, plugins: { legend: { display: !!title }}, scales: { y: { beginAtZero: true } } }
    });
  }

  // Initial charts from PHP
  try {
    var byType='.$js_byType.';
    var L1 = [], V1 = [];
    (byType||[]).forEach(function(r){L1.push(r.action); V1.push(Number(r.n||0));});
    buildBarChart("activityTypeChart", L1, V1, "Actions");

    var byUser='.$js_byUser.';
    var L2 = [], V2 = [];
    (byUser||[]).forEach(function(r){L2.push(r.username||"(unknown)"); V2.push(Number(r.n||0));});
    buildBarChart("userActivityChart", L2, V2, "User activity");

    var topPages='.$js_topPages.';
    var L3 = [], V3 = [];
    (topPages||[]).forEach(function(r){L3.push(r.uri||"(unknown)"); V3.push(Number(r.total_sec||0));});
    buildBarChart("topPagesChart", L3, V3, "Total seconds by page");

    var ts='.$js_timeSeries.';
    var L4 = [], V4 = [];
    (ts||[]).forEach(function(r){L4.push(r.d); V4.push(Number(r.sec||0));});
    buildLineChart("timeSeriesChart", L4, V4, "Seconds per day");

    var ut='.$js_userTime.';
    var L5 = [], V5 = [];
    (ut||[]).forEach(function(r){L5.push(r.username||"(unknown)"); V5.push(Number(r.total_sec||0));});
    buildBarChart("userTimeChart", L5, V5, "Total seconds per user");
  } catch(e){ console && console.warn && console.warn("UAT chart init failed", e); }

  // Filters - Advanced toggle
  var advBtn = qs("advancedSearchToggle"), advPane = qs("advancedSearchPanel");
  if (advBtn && advPane) {
    advBtn.addEventListener("click", function(){
      var show = advPane.style.display==="none" || advPane.style.display===""; 
      advPane.style.display = show ? "block" : "none";
      advBtn.innerHTML = show ? \'<i class="fas fa-chevron-up"></i> Hide Advanced\' : \'<i class="fas fa-chevron-down"></i> Show Advanced\';
    });
  }

  // Refresh (AJAX)
  var refreshBtn = qs("refreshData"), btnRA = qs("btnRefreshActivities"), auto = qs("autoRefresh");
  function gatherFilters(){
    var q = {
      ajax: 1,
      from: (qs("from")||{}).value||"",
      to: (qs("to")||{}).value||"",
      search_action: (qs("search_action")||{}).value||"",
      search_user: (qs("search_user")||{}).value||"",
      search_element: (qs("search_element")||{}).value||"",
      severity_filter: (qs("severity_filter")||{}).value||"",
      ip_filter: (qs("ip_filter")||{}).value||"",
      limit_results: (qs("limit_results")||{}).value||20
    };
    return q;
  }
  function toQuery(obj){
    var p=[]; for (var k in obj) if (obj.hasOwnProperty(k)) p.push(encodeURIComponent(k)+"="+encodeURIComponent(obj[k]??""));
    return p.join("&");
  }
  function refresh(){
    var q = gatherFilters();
    fetch("'.$ajax_url.'?"+toQuery(q), {credentials:"same-origin"})
      .then(r=>r.json()).then(function(j){
        // Soft reload: easiest is to full reload so all tables/charts update consistently
        // (keeps things bulletproof without duplicating view logic in JS)
        location.href = "'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?" + toQuery(q).replace("ajax=1",""); 
      }).catch(function(e){ console && console.error && console.error(e); });
  }
  if (refreshBtn) refreshBtn.addEventListener("click", refresh);
  if (btnRA) btnRA.addEventListener("click", refresh);

  // Auto-refresh every 30s (if checked)
  var timer=null;
  function arStart(){ if (timer) clearInterval(timer); timer = setInterval(refresh, 30000); }
  function arStop(){ if (timer) { clearInterval(timer); timer=null; } }
  if (auto){
    auto.addEventListener("change", function(){ this.checked ? arStart() : arStop(); });
  }

  // Export PDF (simple print of page as PDF)
  var btnPDF = qs("exportPDF");
  if (btnPDF) btnPDF.addEventListener("click", function(){
    try {
      if (window.jspdf && window.jspdf.jsPDF) {
        // Simple fallback to browser print (keeps layout/styles)
        window.print();
      } else { window.print(); }
    } catch(e) { window.print(); }
  });

  // Theme toggle (basic)
  var themeBtn = qs("themeToggle");
  if (themeBtn) themeBtn.addEventListener("click", function(){
    document.documentElement.classList.toggle("uat-dark");
    try { localStorage.setItem("uat-dark", document.documentElement.classList.contains("uat-dark")?"1":"0"); } catch(e){}
  });
  try { if (localStorage.getItem("uat-dark")==="1") document.documentElement.classList.add("uat-dark"); } catch(e){}
})();
</script>';

llxFooter();
