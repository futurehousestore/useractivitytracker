<?php
/**
 * Dashboard page
 * Path: custom/useractivitytracker/admin/useractivitytracker_dashboard.php
 * Version: 3.6.0 — Oro-style UI, kpi1 elapsed analytics, filter summary
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
    $entity = (int)$conf->entity;

    $allowed_severity = array('info','notice','warning','error');
    if ($severity_filter !== '' && !in_array($severity_filter, $allowed_severity, true)) {
        $severity_filter = '';
    }

    $cond  = " WHERE entity=".(int)$entity
            . " AND datestamp BETWEEN '".$db->escape($from)." 00:00:00'"
            . " AND '".$db->escape($to)." 23:59:59'";

    if ($search_action !== '')  $cond .= " AND action LIKE '%".$db->escape($search_action)."%'";
    if ($search_user   !== '')  $cond .= " AND username LIKE '%".$db->escape($search_user)."%'";
    if ($search_element!== '')  $cond .= " AND element_type LIKE '%".$db->escape($search_element)."%'";
    if ($severity_filter!== '') $cond .= " AND severity = '".$db->escape($severity_filter)."'";
    if ($ip_filter     !== '')  $cond .= " AND ip LIKE '%".$db->escape($ip_filter)."%'";
    return $cond;
}

function uat_count_patterns($str) {
    $str = trim((string)$str);
    if ($str === '') return 0;
    $parts = preg_split('/[,\n;]+/', $str);
    $n = 0;
    foreach ($parts as $p) if (trim($p) !== '') $n++;
    return $n;
}

/* ---- AJAX handler for refresh (returns stats; frontend still reloads page) ---- */
if (GETPOST('ajax', 'int') == 1) {
    header('Content-Type: application/json');

    $from           = trim(GETPOST('from','alphanohtml'));
    $to             = trim(GETPOST('to','alphanohtml'));
    $search_action  = trim(GETPOST('search_action','alphanohtml'));
    $search_user    = trim(GETPOST('search_user','alphanohtml'));
    $search_element = trim(GETPOST('search_element','alphanohtml'));
    $severity_filter= trim(GETPOST('severity_filter','alphanohtml'));
    $ip_filter      = trim(GETPOST('ip_filter','alphanohtml'));

    $page           = max(1, (int)GETPOST('page','int'));
    $limit_results  = max(1, min(100, (int)GETPOST('limit_results','int')));
    if ($limit_results <= 0) $limit_results = 20;
    $offset         = ($page - 1) * $limit_results;

    if ($from === '') $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
    if ($to   === '') $to   = dol_print_date(dol_now(), '%Y-%m-%d');

    $prefix = $db->prefix();
    $cond   = uat_build_where($db, $conf, $from, $to, $search_action, $search_user, $search_element, $severity_filter, $ip_filter);

    // Totals
    $totalCount = 0;
    $sql = "SELECT COUNT(*) as total FROM {$prefix}useractivitytracker_activity {$cond}";
    $res = $db->query($sql);
    if ($res && ($obj = $db->fetch_object($res))) { $totalCount = (int)$obj->total; $db->free($res); }

    // By action
    $byType = array();
    $sql = "SELECT action, COUNT(*) as n FROM {$prefix}useractivitytracker_activity {$cond} GROUP BY action ORDER BY n DESC LIMIT 10";
    $res = $db->query($sql);
    if ($res) { while ($o=$db->fetch_object($res)) $byType[]=$o; $db->free($res); }

    // By user
    $byUser = array();
    $sql = "SELECT username, COUNT(*) as n FROM {$prefix}useractivitytracker_activity {$cond} GROUP BY username ORDER BY n DESC LIMIT 10";
    $res = $db->query($sql);
    if ($res) { while ($o=$db->fetch_object($res)) $byUser[]=$o; $db->free($res); }

    // Recent
    $recentActivities = array();
    $sql = "SELECT rowid, datestamp, action, element_type, username, ref, severity, kpi1
            FROM {$prefix}useractivitytracker_activity {$cond}
            ORDER BY datestamp DESC LIMIT ".(int)$limit_results." OFFSET ".(int)$offset;
    $res = $db->query($sql);
    if ($res) { while ($o=$db->fetch_object($res)) $recentActivities[]=$o; $db->free($res); }

    // Top pages by kpi1
    $topPages = array();
    $sql = "SELECT uri, COUNT(*) AS visits, SUM(kpi1) AS total_sec, ROUND(AVG(kpi1),1) AS avg_sec
            FROM {$prefix}useractivitytracker_activity {$cond} AND uri IS NOT NULL AND kpi1 IS NOT NULL
            GROUP BY uri ORDER BY total_sec DESC LIMIT 10";
    $res = $db->query($sql);
    if ($res) { while ($o=$db->fetch_object($res)) $topPages[]=$o; $db->free($res); }

    // Time series by day (kpi1)
    $timeSeries = array();
    $sql = "SELECT DATE(datestamp) AS d, SUM(kpi1) AS sec
            FROM {$prefix}useractivitytracker_activity {$cond} AND kpi1 IS NOT NULL
            GROUP BY DATE(datestamp) ORDER BY d ASC";
    $res = $db->query($sql);
    if ($res) { while ($o=$db->fetch_object($res)) $timeSeries[]=$o; $db->free($res); }

    // Per-user kpi1
    $userTime = array();
    $sql = "SELECT username, COUNT(*) AS visits, SUM(kpi1) AS total_sec, ROUND(AVG(kpi1),1) AS avg_sec
            FROM {$prefix}useractivitytracker_activity {$cond} AND kpi1 IS NOT NULL
            GROUP BY username ORDER BY total_sec DESC LIMIT 10";
    $res = $db->query($sql);
    if ($res) { while ($o=$db->fetch_object($res)) $userTime[]=$o; $db->free($res); }

    $totalSec = 0; $eventsWithKpi1 = 0;
    foreach ($userTime as $row) {
        $totalSec       += (int)$row->total_sec;
        $eventsWithKpi1 += (int)$row->visits;
    }
    $avgSec = $eventsWithKpi1 > 0 ? round($totalSec / $eventsWithKpi1, 1) : 0;

    echo json_encode(array(
        'success' => true,
        'timestamp' => time(),
        'pagination' => array(
            'page'       => $page,
            'limit'      => $limit_results,
            'total'      => $totalCount,
            'totalPages' => $totalCount > 0 ? ceil($totalCount / $limit_results) : 0,
        ),
        'stats' => array(
            'total'        => $totalCount,
            'uniqueActions'=> count($byType),
            'activeUsers'  => count($byUser),
            'totalSeconds' => $totalSec,
            'avgSeconds'   => $avgSec,
        ),
        'chartData' => array(
            'activityType' => $byType,
            'userActivity' => $byUser,
            'topPages'     => $topPages,
            'timeSeries'   => $timeSeries,
            'userTime'     => $userTime,
        ),
        'recentActivities' => $recentActivities,
    ));
    exit;
}

/* ---- Inputs for initial render ---- */
$from           = trim(GETPOST('from','alphanohtml'));
$to             = trim(GETPOST('to','alphanohtml'));
$search_action  = trim(GETPOST('search_action','alphanohtml'));
$search_user    = trim(GETPOST('search_user','alphanohtml'));
$search_element = trim(GETPOST('search_element','alphanohtml'));
$severity_filter= trim(GETPOST('severity_filter','alphanohtml'));
$ip_filter      = trim(GETPOST('ip_filter','alphanohtml'));

$page           = max(1, (int)GETPOST('page','int'));
$limit_results  = max(1, min(100, (int)GETPOST('limit_results','int')));
if ($limit_results <= 0) $limit_results = 20;
$offset         = ($page - 1) * $limit_results;

if ($from === '') $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
if ($to   === '') $to   = dol_print_date(dol_now(), '%Y-%m-%d');

$prefix = $db->prefix();
$cond   = uat_build_where($db, $conf, $from, $to, $search_action, $search_user, $search_element, $severity_filter, $ip_filter);

/* ---- Stats queries ---- */
$byType = array();
$sql = "SELECT action, COUNT(*) as n
        FROM {$prefix}useractivitytracker_activity {$cond}
        GROUP BY action ORDER BY n DESC LIMIT 10";
$res = $db->query($sql);
if ($res) { while ($o=$db->fetch_object($res)) $byType[]=$o; $db->free($res); }

$byUser = array();
$sql = "SELECT username, COUNT(*) as n
        FROM {$prefix}useractivitytracker_activity {$cond}
        GROUP BY username ORDER BY n DESC LIMIT 10";
$res = $db->query($sql);
if ($res) { while ($o=$db->fetch_object($res)) $byUser[]=$o; $db->free($res); }

$byElement = array();
$sql = "SELECT element_type, COUNT(*) as n
        FROM {$prefix}useractivitytracker_activity {$cond} AND element_type IS NOT NULL
        GROUP BY element_type ORDER BY n DESC LIMIT 10";
$res = $db->query($sql);
if ($res) { while ($o=$db->fetch_object($res)) $byElement[]=$o; $db->free($res); }

/* Time analytics based on kpi1 */
$topPages = array();
$sql = "SELECT uri, COUNT(*) AS visits, SUM(kpi1) AS total_sec, ROUND(AVG(kpi1),1) AS avg_sec
        FROM {$prefix}useractivitytracker_activity {$cond} AND uri IS NOT NULL AND kpi1 IS NOT NULL
        GROUP BY uri ORDER BY total_sec DESC LIMIT 10";
$res = $db->query($sql);
if ($res) { while ($o=$db->fetch_object($res)) $topPages[]=$o; $db->free($res); }

$userTime = array();
$sql = "SELECT username, COUNT(*) AS visits, SUM(kpi1) AS total_sec, ROUND(AVG(kpi1),1) AS avg_sec
        FROM {$prefix}useractivitytracker_activity {$cond} AND kpi1 IS NOT NULL
        GROUP BY username ORDER BY total_sec DESC LIMIT 10";
$res = $db->query($sql);
if ($res) { while ($o=$db->fetch_object($res)) $userTime[]=$o; $db->free($res); }

$timeSeries = array();
$sql = "SELECT DATE(datestamp) AS d, SUM(kpi1) AS sec
        FROM {$prefix}useractivitytracker_activity {$cond} AND kpi1 IS NOT NULL
        GROUP BY DATE(datestamp) ORDER BY d ASC";
$res = $db->query($sql);
if ($res) { while ($o=$db->fetch_object($res)) $timeSeries[]=$o; $db->free($res); }

/* Totals & recent */
$totalCount = 0;
$sql = "SELECT COUNT(*) as total
        FROM {$prefix}useractivitytracker_activity {$cond}";
$res = $db->query($sql);
if ($res && ($obj=$db->fetch_object($res))) { $totalCount = (int)$obj->total; $db->free($res); }

$recentActivities = array();
$sql = "SELECT rowid, datestamp, action, element_type, username, ref, severity, kpi1
        FROM {$prefix}useractivitytracker_activity {$cond}
        ORDER BY datestamp DESC LIMIT ".(int)$limit_results;
$res = $db->query($sql);
if ($res) { while ($o=$db->fetch_object($res)) $recentActivities[]=$o; $db->free($res); }

/* Totals for kpi1 */
$totalSec = 0; $eventsWithKpi1 = 0;
foreach ($userTime as $row) {
    $totalSec       += (int)$row->total_sec;
    $eventsWithKpi1 += (int)$row->visits;
}
$avgSec = $eventsWithKpi1 > 0 ? round($totalSec / $eventsWithKpi1, 1) : 0;

/* Opportunistic retention cleanup */
$days = getDolGlobalInt('USERACTIVITYTRACKER_RETENTION_DAYS', 365);
$db->query("DELETE FROM ".$db->prefix()."useractivitytracker_activity
            WHERE datestamp < DATE_SUB(NOW(), INTERVAL ".((int)$days)." DAY)
              AND entity=".(int)$conf->entity);

/* Config snapshot for summary strip */
$dedup_window      = getDolGlobalInt('USERACTIVITYTRACKER_DEDUP_WINDOW', 2);
$track_elapsed     = getDolGlobalInt('USERACTIVITYTRACKER_TRACK_ELAPSED', 1);
$track_cli         = getDolGlobalInt('USERACTIVITYTRACKER_TRACK_CLI', 1);
$master_enabled    = getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1);
$enable_tracking   = getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1);
$tracking_on       = ($master_enabled && $enable_tracking) ? 1 : 0;

$action_whitelist  = getDolGlobalString('USERACTIVITYTRACKER_ACTION_WHITELIST', '');
$action_blacklist  = getDolGlobalString('USERACTIVITYTRACKER_ACTION_BLACKLIST', '');
$element_whitelist = getDolGlobalString('USERACTIVITYTRACKER_ELEMENT_WHITELIST', '');
$element_blacklist = getDolGlobalString('USERACTIVITYTRACKER_ELEMENT_BLACKLIST', '');

$actionWhitelistCount  = uat_count_patterns($action_whitelist);
$actionBlacklistCount  = uat_count_patterns($action_blacklist);
$elementWhitelistCount = uat_count_patterns($element_whitelist);
$elementBlacklistCount = uat_count_patterns($element_blacklist);

if ($actionWhitelistCount > 0) {
    $actionFilterSummary = 'Whitelist ('.$actionWhitelistCount.' pattern'.($actionWhitelistCount>1?'s':'').')';
} elseif ($actionBlacklistCount > 0) {
    $actionFilterSummary = 'Blacklist ('.$actionBlacklistCount.' pattern'.($actionBlacklistCount>1?'s':'').')';
} else {
    $actionFilterSummary = 'All actions tracked';
}

if ($elementWhitelistCount > 0) {
    $elementFilterSummary = 'Whitelist ('.$elementWhitelistCount.' pattern'.($elementWhitelistCount>1?'s':'').')';
} elseif ($elementBlacklistCount > 0) {
    $elementFilterSummary = 'Blacklist ('.$elementBlacklistCount.' pattern'.($elementBlacklistCount>1?'s':'').')';
} else {
    $elementFilterSummary = 'All elements tracked';
}

/* URLs */
$analysis_url  = dol_buildpath('/useractivitytracker/admin/useractivitytracker_analysis.php', 1);
$dashboard_url = $_SERVER['PHP_SELF'];
$setup_url     = dol_buildpath('/useractivitytracker/admin/useractivitytracker_setup.php', 1);
$export_base   = dol_buildpath('/useractivitytracker/scripts/export.php', 1);

/* --------------------------------------------------------------------------
 * View
 * ------------------------------------------------------------------------*/
llxHeader('', 'User Activity — Dashboard');

/* Same OroCommerce-style CSS as setup/analysis */
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
.uat-form-select {
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

/* Tables */
.uat-table-wrapper { overflow-x:auto; }

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

/* Hour bar for time series */
.uat-hour-bar {
    background: rgba(37, 99, 235, 0.15);
    border-radius: 9999px;
    height: 10px;
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
    grid-template-columns: minmax(0, 1.8fr) minmax(0, 1.8fr);
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .uat-grid-2 {
        grid-template-columns: 1fr;
    }
}

/* Timeline style (re-using data as list) */
.uat-timeline-empty {
    text-align:center;
    padding:1.5rem;
    color:var(--uat-text-secondary);
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

print '<div class="uat-container">';

/* Header */
print '<div class="uat-header">';
print '<div class="uat-header-content">';
print '<div class="uat-header-title">';
print '<div class="uat-header-icon"><i class="fas fa-tachometer-alt"></i></div>';
print '<div>';
print '<h1>User Activity — Dashboard</h1>';
print '<div style="font-size:0.875rem;opacity:0.9;margin-top:0.25rem;">Live snapshot of user events, hotspots and elapsed time</div>';
print '</div>';
print '</div>'; // title

print '<div class="uat-header-actions">';
print '<a href="'.dol_escape_htmltag($analysis_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-chart-area"></i><span>Analysis</span></a>';
print '<a href="'.dol_escape_htmltag($setup_url).'" class="uat-btn uat-btn-ghost uat-btn-sm"><i class="fas fa-cogs"></i><span>Settings</span></a>';
print '<button type="button" id="themeToggle" class="uat-btn uat-btn-ghost uat-btn-icon"><i class="fas fa-moon"></i></button>';
print '</div>'; // actions
print '</div>'; // header-content

print '<div class="uat-header-meta">';
print '<div class="uat-meta-item"><i class="fas fa-calendar"></i><span>Period: '.dol_escape_htmltag($from).' — '.dol_escape_htmltag($to).'</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-database"></i><span>'.number_format($totalCount).' events</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-toggle-on"></i><span>Tracking: '.($tracking_on?'Enabled':'Disabled').'</span></div>';
print '<div class="uat-meta-item"><i class="fas fa-stopwatch"></i><span>Total kpi1: '.$totalSec.' sec (avg '.$avgSec.'s)</span></div>';
print '</div>';

print '</div>'; // header

/* Stats cards */
$uniqueActions = count($byType);
$activeUsers   = count($byUser);
$daysCount     = count($timeSeries) > 0 ? count($timeSeries) : 1;
$dailyAverage  = $daysCount > 0 ? round($totalCount / $daysCount, 1) : 0.0;

print '<div class="uat-stats-grid">';

// Total activities
print '<div class="uat-stat-card" style="--card-color:#2563eb; --card-color-dark:#1d4ed8; --card-color-light:#3b82f6;">';
print '<div class="uat-stat-header">';
print '<div class="uat-stat-icon"><i class="fas fa-chart-bar"></i></div>';
print '<div>';
print '<div class="uat-stat-label">Total Activities</div>';
print '<div class="uat-stat-value">'.number_format($totalCount).'</div>';
print '<div class="uat-stat-sub">Events in selected period</div>';
print '</div>';
print '</div>';
print '</div>';

// Unique actions
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

// Active users
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

// Time metrics
print '<div class="uat-stat-card" style="--card-color:#f59e0b; --card-color-dark:#d97706; --card-color-light:#fbbf24;">';
print '<div class="uat-stat-header">';
print '<div class="uat-stat-icon"><i class="fas fa-stopwatch"></i></div>';
print '<div>';
print '<div class="uat-stat-label">Elapsed Time (kpi1)</div>';
print '<div class="uat-stat-value">'.$totalSec.'s</div>';
print '<div class="uat-stat-sub">Avg '.$avgSec.'s per event · ~'.$dailyAverage.' events/day</div>';
print '</div>';
print '</div>';
print '</div>';

print '</div>'; // stats-grid

/* Config summary strip */
print '<div class="uat-card" style="margin-bottom:1.5rem;">';
print '<div class="uat-card-body" style="padding:0.75rem 1.25rem;font-size:0.8rem;color:#475569;display:flex;flex-wrap:wrap;gap:1rem;align-items:center;">';
print '<div><strong><i class="fas fa-sliders-h"></i> Dedup:</strong> '.$dedup_window.'s window</div>';
print '<div><strong><i class="fas fa-stopwatch"></i> Elapsed:</strong> '.($track_elapsed?'On':'Off').' (kpi1)</div>';
print '<div><strong><i class="fas fa-terminal"></i> CLI:</strong> '.($track_cli?'Tracked':'Ignored').'</div>';
print '<div><strong><i class="fas fa-filter"></i> Action filter:</strong> '.dol_escape_htmltag($actionFilterSummary).'</div>';
print '<div><strong><i class="fas fa-layer-group"></i> Element filter:</strong> '.dol_escape_htmltag($elementFilterSummary).'</div>';
print '</div>';
print '</div>';

/* Filters card */
print '<div class="uat-card">';
print '<div class="uat-card-header">';
print '<div class="uat-card-title"><i class="fas fa-filter"></i> Filters & Export</div>';
print '<div>';
print '<a class="uat-btn uat-btn-secondary uat-btn-sm" href="'.$export_base.'?format=csv&from='.urlencode($from).'&to='.urlencode($to).'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user).'&search_element='.urlencode($search_element).'&severity_filter='.urlencode($severity_filter).'&ip_filter='.urlencode($ip_filter).'"><i class="fas fa-file-csv"></i><span>CSV</span></a>';
print '<a class="uat-btn uat-btn-secondary uat-btn-sm" href="'.$export_base.'?format=xls&from='.urlencode($from).'&to='.urlencode($to).'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user).'&search_element='.urlencode($search_element).'&severity_filter='.urlencode($severity_filter).'&ip_filter='.urlencode($ip_filter).'"><i class="fas fa-file-excel"></i><span>XLS</span></a>';
print '<button type="button" id="refreshData" class="uat-btn uat-btn-secondary uat-btn-sm"><i class="fas fa-sync-alt"></i><span>Refresh</span></button>';
print '</div>';
print '</div>';

print '<div class="uat-card-body">';
print '<form method="get" action="'.dol_escape_htmltag($dashboard_url).'">';
print '<div class="uat-form-grid">';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-calendar-alt"></i> From</label>';
print '<input type="date" name="from" id="from" class="uat-form-input" value="'.dol_escape_htmltag($from).'">';
print '</div>';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-calendar-alt"></i> To</label>';
print '<input type="date" name="to" id="to" class="uat-form-input" value="'.dol_escape_htmltag($to).'">';
print '</div>';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-bolt"></i> Action</label>';
print '<input type="text" name="search_action" id="search_action" class="uat-form-input" value="'.dol_escape_htmltag($search_action).'" placeholder="e.g. COMPANY_CREATE">';
print '</div>';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-user"></i> User</label>';
print '<input type="text" name="search_user" id="search_user" class="uat-form-input" value="'.dol_escape_htmltag($search_user).'" placeholder="Username">';
print '</div>';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-cubes"></i> Element type</label>';
print '<input type="text" name="search_element" id="search_element" class="uat-form-input" value="'.dol_escape_htmltag($search_element).'" placeholder="societe, facture, ...">';
print '</div>';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-exclamation-circle"></i> Severity</label>';
print '<select name="severity_filter" id="severity_filter" class="uat-form-select">';
$sevOps = array('' => 'All', 'info'=>'Info','warning'=>'Warning','error'=>'Error','notice'=>'Notice');
foreach ($sevOps as $k=>$label) {
    print '<option value="'.dol_escape_htmltag($k).'"'.($k===$severity_filter?' selected':'').'>'.dol_escape_htmltag($label).'</option>';
}
print '</select>';
print '</div>';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-network-wired"></i> IP</label>';
print '<input type="text" name="ip_filter" id="ip_filter" class="uat-form-input" value="'.dol_escape_htmltag($ip_filter).'" placeholder="192.168.1.1">';
print '</div>';
print '<div class="uat-form-group">';
print '<label class="uat-form-label"><i class="fas fa-list-ol"></i> Results</label>';
print '<select name="limit_results" id="limit_results" class="uat-form-select">';
foreach (array(20,50,100,200,500) as $opt) {
    print '<option value="'.$opt.'"'.($limit_results==$opt?' selected':'').'>'.$opt.' per load</option>';
}
print '</select>';
print '</div>';
print '</div>'; // form-grid
print '<div style="margin-top:1rem;display:flex;gap:0.75rem;align-items:center;justify-content:flex-end;">';
print '<button type="submit" class="uat-btn uat-btn-primary"><i class="fas fa-search"></i><span>Apply filters</span></button>';
print '<a href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="uat-btn uat-btn-secondary"><i class="fas fa-times"></i><span>Clear</span></a>';
print '</div>';
print '</form>';
print '</div>'; // card-body
print '</div>'; // card

/* Main grid: left (metrics) and right (recent/time) */
print '<div class="uat-grid-2">';

/* LEFT COLUMN: Activity by type/user + elements */
print '<div>';

// Activity by type
print '<div class="uat-card">';
print '<div class="uat-card-header"><div class="uat-card-title"><i class="fas fa-chart-pie"></i> Activity by Action</div></div>';
print '<div class="uat-card-body">';
print '<div class="uat-table-wrapper"><table class="uat-table">';
print '<thead><tr><th>Action</th><th style="text-align:right;">Events</th><th style="text-align:right;">Share</th></tr></thead><tbody>';
if ($byType) {
    foreach ($byType as $r) {
        $pct = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
        print '<tr><td>'.dol_escape_htmltag($r->action).'</td>';
        print '<td style="text-align:right;">'.$r->n.'</td>';
        print '<td style="text-align:right;">'.$pct.'%</td></tr>';
    }
} else {
    print '<tr><td colspan="3">No data found for selected filters.</td></tr>';
}
print '</tbody></table></div>';
print '</div>';
print '</div>';

// Activity by user
print '<div class="uat-card">';
print '<div class="uat-card-header"><div class="uat-card-title"><i class="fas fa-users"></i> Activity by User</div></div>';
print '<div class="uat-card-body">';
print '<div class="uat-table-wrapper"><table class="uat-table">';
print '<thead><tr><th>User</th><th style="text-align:right;">Events</th><th style="text-align:right;">Share</th></tr></thead><tbody>';
if ($byUser) {
    foreach ($byUser as $r) {
        $pct = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
        $name = $r->username ?: '(unknown)';
        print '<tr><td>'.dol_escape_htmltag($name).'</td>';
        print '<td style="text-align:right;">'.$r->n.'</td>';
        print '<td style="text-align:right;">'.$pct.'%</td></tr>';
    }
} else {
    print '<tr><td colspan="3">No user activity for selected filters.</td></tr>';
}
print '</tbody></table></div>';
print '</div>';
print '</div>';

// Activity by element type
print '<div class="uat-card">';
print '<div class="uat-card-header"><div class="uat-card-title"><i class="fas fa-cubes"></i> Activity by Element Type</div></div>';
print '<div class="uat-card-body">';
print '<div class="uat-table-wrapper"><table class="uat-table">';
print '<thead><tr><th>Element</th><th style="text-align:right;">Events</th></tr></thead><tbody>';
if ($byElement) {
    foreach ($byElement as $r) {
        print '<tr><td>'.dol_escape_htmltag($r->element_type).'</td>';
        print '<td style="text-align:right;">'.$r->n.'</td></tr>';
    }
} else {
    print '<tr><td colspan="2">No element data for selected filters.</td></tr>';
}
print '</tbody></table></div>';
print '</div>';
print '</div>';

print '</div>'; // left column

/* RIGHT COLUMN: Recent + time series + top pages/user time */
print '<div>';

// Recent activities
print '<div class="uat-card">';
print '<div class="uat-card-header"><div class="uat-card-title"><i class="fas fa-history"></i> Recent Activities</div></div>';
print '<div class="uat-card-body">';
if ($recentActivities) {
    $view_base = dol_buildpath('/useractivitytracker/admin/useractivitytracker_view.php', 1);
    print '<div class="uat-table-wrapper"><table class="uat-table">';
    print '<thead><tr><th>When</th><th>Action</th><th>User</th><th>Details</th><th style="text-align:right;">Δ (s)</th></tr></thead><tbody>';
    foreach ($recentActivities as $r) {
        $fullDate = dol_print_date(dol_stringtotime($r->datestamp), 'dayhour');
        $details  = array();
        if (!empty($r->element_type)) $details[] = 'Element: '.$r->element_type;
        if (!empty($r->ref))          $details[] = 'Ref: '.$r->ref;
        $elapsed  = $r->kpi1 ? (int)$r->kpi1 : 0;
        print '<tr>';
        print '<td>'.dol_escape_htmltag($fullDate).'</td>';
        print '<td><a href="'.$view_base.'?id='.(int)$r->rowid.'">'.dol_escape_htmltag($r->action).'</a></td>';
        print '<td>'.dol_escape_htmltag($r->username).'</td>';
        print '<td>'.($details?dol_escape_htmltag(implode(' | ',$details)):'').'</td>';
        print '<td style="text-align:right;">'.($elapsed>0?$elapsed:'').'</td>';
        print '</tr>';
    }
    print '</tbody></table></div>';
} else {
    print '<div class="uat-timeline-empty"><i class="fas fa-inbox" style="margin-right:0.5rem;"></i>No recent activities for the selected period.</div>';
}
print '</div>';
print '</div>';

// Time series per day
print '<div class="uat-card">';
print '<div class="uat-card-header"><div class="uat-card-title"><i class="fas fa-chart-line"></i> Time Spent per Day (kpi1)</div></div>';
print '<div class="uat-card-body">';
if (!empty($timeSeries)) {
    $maxSec = 0;
    foreach ($timeSeries as $row) { $maxSec = max($maxSec, (int)$row->sec); }
    print '<div class="uat-table-wrapper"><table class="uat-table">';
    print '<thead><tr><th>Date</th><th style="text-align:right;">Seconds</th></tr></thead><tbody>';
    foreach ($timeSeries as $row) {
        $d = dol_print_date(dol_stringtotime($row->d), '%d/%m/%Y');
        $sec = (int)$row->sec;
        $w = $maxSec > 0 ? ($sec / $maxSec) * 100 : 0;
        print '<tr>';
        print '<td>'.$d.'<div class="uat-hour-bar"><div class="uat-hour-bar-fill" style="width:'.$w.'%;"></div></div></td>';
        print '<td style="text-align:right;">'.$sec.'</td>';
        print '</tr>';
    }
    print '</tbody></table></div>';
} else {
    print '<div class="uat-timeline-empty">No elapsed time data (kpi1) for this range.</div>';
}
print '</div>';
print '</div>';

// Top pages & per-user time
print '<div class="uat-card">';
print '<div class="uat-card-header"><div class="uat-card-title"><i class="fas fa-stopwatch"></i> Top Pages by Total Time (kpi1)</div></div>';
print '<div class="uat-card-body">';
print '<div class="uat-table-wrapper"><table class="uat-table">';
print '<thead><tr><th>URI</th><th style="text-align:right;">Visits</th><th style="text-align:right;">Total sec</th><th style="text-align:right;">Avg sec</th></tr></thead><tbody>';
if ($topPages) {
    foreach ($topPages as $p) {
        print '<tr>';
        print '<td>'.dol_escape_htmltag($p->uri).'</td>';
        print '<td style="text-align:right;">'.(int)$p->visits.'</td>';
        print '<td style="text-align:right;">'.(int)$p->total_sec.'</td>';
        print '<td style="text-align:right;">'.(float)$p->avg_sec.'</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="4">No page-level kpi1 data.</td></tr>';
}
print '</tbody></table></div>';
print '</div>';
print '</div>';

print '<div class="uat-card">';
print '<div class="uat-card-header"><div class="uat-card-title"><i class="fas fa-user-clock"></i> Per-user Time on Pages (kpi1)</div></div>';
print '<div class="uat-card-body">';
print '<div class="uat-table-wrapper"><table class="uat-table">';
print '<thead><tr><th>User</th><th style="text-align:right;">Visits</th><th style="text-align:right;">Total sec</th><th style="text-align:right;">Avg sec</th></tr></thead><tbody>';
if ($userTime) {
    foreach ($userTime as $u) {
        $name = $u->username ?: '(unknown)';
        print '<tr>';
        print '<td>'.dol_escape_htmltag($name).'</td>';
        print '<td style="text-align:right;">'.(int)$u->visits.'</td>';
        print '<td style="text-align:right;">'.(int)$u->total_sec.'</td>';
        print '<td style="text-align:right;">'.(float)$u->avg_sec.'</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="4">No kpi1 data per user.</td></tr>';
}
print '</tbody></table></div>';
print '</div>';
print '</div>';

print '</div>'; // right column

print '</div>'; // main grid

print '</div>'; // container

/* Theme toggle + refresh JS */
$ajax_url = dol_buildpath('/useractivitytracker/admin/useractivitytracker_dashboard.php', 1);
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

  var refreshBtn = document.getElementById("refreshData");
  function qs(id){return document.getElementById(id);}
  function gatherFilters(){
    return {
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
  }
  function toQuery(obj){
    var p=[]; for (var k in obj) if (obj.hasOwnProperty(k)) p.push(encodeURIComponent(k)+"="+encodeURIComponent(obj[k]??""));
    return p.join("&");
  }
  function refresh(){
    var q = gatherFilters();
    fetch("'.$ajax_url.'?"+toQuery(q), {credentials:"same-origin"})
      .then(function(r){ return r.json(); })
      .then(function(){ 
          // Full reload so PHP view logic stays the single source of truth
          location.href = "'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?" + toQuery(q).replace("ajax=1","");
      })
      .catch(function(e){ if (window.console) console.error(e); });
  }
  if (refreshBtn) refreshBtn.addEventListener("click", refresh);
})();
</script>';

llxFooter();
