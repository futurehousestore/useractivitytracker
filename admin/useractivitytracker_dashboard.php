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
print load_fiche_titre('User Activity — Dashboard', '', 'object_useractivitytracker@useractivitytracker');

/* Filter form */
print '<div class="div-table-responsive-no-min">';
print '<form method="get" class="border valignmiddle">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="6">Filters</th></tr>';
print '<tr>';
print '<td class="titlefield">From</td>';
print '<td><input class="flat" type="date" name="from" value="'.dol_escape_htmltag($from).'"></td>';
print '<td>To</td>';
print '<td><input class="flat" type="date" name="to" value="'.dol_escape_htmltag($to).'"></td>';
print '<td rowspan="2"><input type="submit" class="button" value="Apply Filters"></td>';
print '<td rowspan="2"><a class="button" href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">Clear Filters</a></td>';
print '</tr>';
print '<tr>';
print '<td>Action</td>';
print '<td><input class="flat" type="text" name="search_action" value="'.dol_escape_htmltag($search_action).'" placeholder="e.g. COMPANY_CREATE"></td>';
print '<td>User</td>';
print '<td><input class="flat" type="text" name="search_user" value="'.dol_escape_htmltag($search_user).'" placeholder="Username"></td>';
print '</tr>';
print '</table>';
print '</form>';
print '</div>';

/* Summary stats */
print '<br><div class="fichecenter">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="4">Summary Statistics</th></tr>';
print '<tr>';
print '<td width="25%"><strong>Total Activities</strong></td><td width="25%">'.$totalCount.'</td>';
print '<td width="25%"><strong>Date Range</strong></td><td width="25%">'.$from.' to '.$to.'</td>';
print '</tr><tr>';
print '<td><strong>Unique Actions</strong></td><td>'.count($byType).'</td>';
print '<td><strong>Active Users</strong></td><td>'.count($byUser).'</td>';
print '</tr>';
print '</table>';
print '</div>';

/* Export buttons (use dol_buildpath to resolve /custom) */
$export_base = dol_buildpath('/useractivitytracker/scripts/export.php', 1);
print '<br><div class="center">';
print '<a class="button" href="'.$export_base.'?format=csv&from='.urlencode($from).'&to='.urlencode($to)
    .'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user)
    .'&search_element='.urlencode($search_element).'">Export CSV</a> ';
print '<a class="button" href="'.$export_base.'?format=xls&from='.urlencode($from).'&to='.urlencode($to)
    .'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user)
    .'&search_element='.urlencode($search_element).'">Export XLS</a>';
print '</div>';

/* Two columns: by type / by user */
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="noborder centpercent"><tr class="liste_titre"><td>Activity by Type</td><td class="right">Count</td></tr>';
if ($byType) {
    foreach ($byType as $r) {
        $pct = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
        print '<tr><td>'.dol_escape_htmltag($r->action).'</td><td class="right">'.$r->n.' ('.$pct.'%)</td></tr>';
    }
} else {
    print '<tr><td colspan="2">No data found</td></tr>';
}
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="noborder centpercent"><tr class="liste_titre"><td>Activity by User</td><td class="right">Count</td></tr>';
if ($byUser) {
    foreach ($byUser as $r) {
        $pct = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
        print '<tr><td>'.dol_escape_htmltag($r->username).'</td><td class="right">'.$r->n.' ('.$pct.'%)</td></tr>';
    }
} else {
    print '<tr><td colspan="2">No data found</td></tr>';
}
print '</table>';
print '</div>';
print '</div><div class="clearboth"></div>';

/* Two columns: by element / recent */
print '<br><div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="noborder centpercent"><tr class="liste_titre"><td>Activity by Element Type</td><td class="right">Count</td></tr>';
if ($byElement) {
    foreach ($byElement as $r) {
        $pct = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
        print '<tr><td>'.dol_escape_htmltag($r->element_type).'</td><td class="right">'.$r->n.' ('.$pct.'%)</td></tr>';
    }
} else {
    print '<tr><td colspan="2">No data found</td></tr>';
}
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="noborder centpercent"><tr class="liste_titre"><td>Recent Activities</td><td>User</td><td>Element</td></tr>';
if ($recentActivities) {
    $view_base = dol_buildpath('/useractivitytracker/admin/useractivitytracker_view.php', 1);
    foreach ($recentActivities as $r) {
        $time = dol_print_date(dol_stringtotime($r->datestamp), '%H:%M');
        $date = dol_print_date(dol_stringtotime($r->datestamp), '%d/%m');
        print '<tr>';
        print '<td><small>'.$date.' '.$time.'</small><br>';
        print '<a href="'.$view_base.'?id='.(int)$r->rowid.'" title="View details">'.dol_escape_htmltag($r->action).'</a>';
        if (!empty($r->ref)) print '<br><em>'.dol_escape_htmltag($r->ref).'</em>';
        print '</td>';
        print '<td>'.dol_escape_htmltag($r->username).'</td>';
        print '<td>'.dol_escape_htmltag($r->element_type).'</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="3">No recent activities</td></tr>';
}
print '</table>';
print '</div>';
print '</div><div class="clearboth"></div>';

/* Timeline */
print '<br><table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">Activity Timeline</td></tr>';
print '<tr><td width="200">Date</td><td>Count</td></tr>';

if (!empty($timeline)) {
    // Determine max for bar visualization
    $max_count = 0;
    foreach ($timeline as $r) { if ((int)$r->n > $max_count) $max_count = (int)$r->n; }
    foreach ($timeline as $r) {
        $bar_width = $max_count > 0 ? min(100, ((int)$r->n / $max_count) * 100) : 0;
        print '<tr><td>'.dol_print_date(dol_stringtotime($r->d), '%A %d %B %Y').'</td>';
        print '<td>'.(int)$r->n;
        if ($bar_width > 0) {
            print ' <div style="background:#4CAF50;height:8px;width:'.round($bar_width,1).'%;margin-top:2px;border-radius:2px;"></div>';
        }
        print '</td></tr>';
    }
} else {
    print '<tr><td colspan="2">No timeline data available</td></tr>';
}
print '</table>';

llxFooter();
