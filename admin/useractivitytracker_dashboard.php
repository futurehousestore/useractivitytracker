
<?php
/**
 * Dashboard page
 * Path: custom/useractivitytracker/admin/useractivitytracker_dashboard.php
 * Version: 1.0.0
 */
require '../../main.inc.php';

if (empty($user->rights->useractivitytracker->read)) accessforbidden();

$from = GETPOST('from','alpha');
$to = GETPOST('to','alpha');
$search_action = GETPOST('search_action','alpha');
$search_user = GETPOST('search_user','alpha');
$search_element = GETPOST('search_element','alpha');

if (empty($from)) $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
if (empty($to)) $to = dol_print_date(dol_now(), '%Y-%m-%d');

$prefix = $db->prefix();
$cond = " WHERE entity=".(int)$conf->entity." AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";

// Add search filters
if (!empty($search_action)) {
    $cond .= " AND action LIKE '%".$db->escape($search_action)."%'";
}
if (!empty($search_user)) {
    $cond .= " AND username LIKE '%".$db->escape($search_user)."%'";
}
if (!empty($search_element)) {
    $cond .= " AND element_type LIKE '%".$db->escape($search_element)."%'";
}

// Get statistics
$byType = array();
$sql = "SELECT action, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY action ORDER BY n DESC LIMIT 10";
$res = $db->query($sql);
if ($res) while ($o=$db->fetch_object($res)) $byType[]=$o;

$byUser = array();
$sql = "SELECT username, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY username ORDER BY n DESC LIMIT 10";
$res = $db->query($sql);
if ($res) while ($o=$db->fetch_object($res)) $byUser[]=$o;

$byElement = array();
$sql = "SELECT element_type, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} AND element_type IS NOT NULL GROUP BY element_type ORDER BY n DESC LIMIT 10";
$res = $db->query($sql);
if ($res) while ($o=$db->fetch_object($res)) $byElement[]=$o;

$timeline = array();
$sql = "SELECT DATE(datestamp) as d, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY DATE(datestamp) ORDER BY d ASC";
$res = $db->query($sql);
if ($res) while ($o=$db->fetch_object($res)) $timeline[]=$o;

// Get total count and recent activities
$totalCount = 0;
$sql = "SELECT COUNT(*) as total FROM {$prefix}alt_user_activity {$cond}";
$res = $db->query($sql);
if ($res && ($obj = $db->fetch_object($res))) $totalCount = $obj->total;

$recentActivities = array();
$sql = "SELECT datestamp, action, element_type, username, ref FROM {$prefix}alt_user_activity {$cond} ORDER BY datestamp DESC LIMIT 20";
$res = $db->query($sql);
if ($res) while ($o=$db->fetch_object($res)) $recentActivities[]=$o;

// Opportunistic retention cleanup
$days = (int)($conf->global->USERACTIVITYTRACKER_RETENTION_DAYS ?: 365);
$db->query("DELETE FROM ".$db->prefix()."alt_user_activity WHERE datestamp < DATE_SUB(NOW(), INTERVAL ".((int)$days)." DAY) AND entity=".(int)$conf->entity);

llxHeader('', 'User Activity — Dashboard');
print load_fiche_titre('User Activity — Dashboard', '', 'object_useractivitytracker@useractivitytracker');

// Filter form
print '<div class="div-table-responsive-no-min">';
print '<form method="get" class="border valignmiddle">';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<th colspan="6">Filters</th>';
print '</tr>';
print '<tr>';
print '<td>From</td>';
print '<td><input type="date" name="from" value="'.dol_escape_htmltag($from).'"></td>';
print '<td>To</td>';
print '<td><input type="date" name="to" value="'.dol_escape_htmltag($to).'"></td>';
print '<td rowspan="2">';
print '<input type="submit" class="button" value="Apply Filters">';
print '</td>';
print '<td rowspan="2">';
print '<a class="button" href="?">Clear Filters</a>';
print '</td>';
print '</tr>';
print '<tr>';
print '<td>Action</td>';
print '<td><input type="text" name="search_action" value="'.dol_escape_htmltag($search_action).'" placeholder="e.g. COMPANY_CREATE"></td>';
print '<td>User</td>';
print '<td><input type="text" name="search_user" value="'.dol_escape_htmltag($search_user).'" placeholder="Username"></td>';
print '</tr>';
print '</table>';
print '</form>';
print '</div>';

// Summary stats
print '<br><div class="fichecenter">';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><th colspan="4">Summary Statistics</th></tr>';
print '<tr>';
print '<td width="25%"><strong>Total Activities</strong></td>';
print '<td width="25%">'.$totalCount.'</td>';
print '<td width="25%"><strong>Date Range</strong></td>';
print '<td width="25%">'.$from.' to '.$to.'</td>';
print '</tr>';
print '<tr>';
print '<td><strong>Unique Actions</strong></td>';
print '<td>'.count($byType).'</td>';
print '<td><strong>Active Users</strong></td>';
print '<td>'.count($byUser).'</td>';
print '</tr>';
print '</table>';
print '</div>';

// Export buttons
print '<br><div class="center">';
print '<a class="button" href="/custom/useractivitytracker/scripts/export.php?format=csv&from='.urlencode($from).'&to='.urlencode($to).'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user).'">Export CSV</a> ';
print '<a class="button" href="/custom/useractivitytracker/scripts/export.php?format=xls&from='.urlencode($from).'&to='.urlencode($to).'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user).'">Export XLS</a>';
print '</div>';

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="noborder" width="100%"><tr class="liste_titre"><td>Activity by Type</td><td class="right">Count</td></tr>';
foreach($byType as $r) {
    $percentage = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
    print '<tr><td>'.dol_escape_htmltag($r->action).'</td><td class="right">'.$r->n.' ('.$percentage.'%)</td></tr>';
}
if (empty($byType)) print '<tr><td colspan="2">No data found</td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="noborder" width="100%"><tr class="liste_titre"><td>Activity by User</td><td class="right">Count</td></tr>';
foreach($byUser as $r) {
    $percentage = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
    print '<tr><td>'.dol_escape_htmltag($r->username).'</td><td class="right">'.$r->n.' ('.$percentage.'%)</td></tr>';
}
if (empty($byUser)) print '<tr><td colspan="2">No data found</td></tr>';
print '</table>';
print '</div>';
print '</div><div class="clearboth"></div>';

print '<br><div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="noborder" width="100%"><tr class="liste_titre"><td>Activity by Element Type</td><td class="right">Count</td></tr>';
foreach($byElement as $r) {
    $percentage = $totalCount > 0 ? round(($r->n / $totalCount) * 100, 1) : 0;
    print '<tr><td>'.dol_escape_htmltag($r->element_type).'</td><td class="right">'.$r->n.' ('.$percentage.'%)</td></tr>';
}
if (empty($byElement)) print '<tr><td colspan="2">No data found</td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="noborder" width="100%"><tr class="liste_titre"><td>Recent Activities</td><td>User</td><td>Element</td></tr>';
foreach($recentActivities as $r) {
    $time = dol_print_date(dol_stringtotime($r->datestamp), '%H:%M');
    $date = dol_print_date(dol_stringtotime($r->datestamp), '%d/%m');
    print '<tr>';
    print '<td><small>'.$date.' '.$time.'</small><br>'.dol_escape_htmltag($r->action);
    if ($r->ref) print '<br><em>'.dol_escape_htmltag($r->ref).'</em>';
    print '</td>';
    print '<td>'.dol_escape_htmltag($r->username).'</td>';
    print '<td>'.dol_escape_htmltag($r->element_type).'</td>';
    print '</tr>';
}
if (empty($recentActivities)) print '<tr><td colspan="3">No recent activities</td></tr>';
print '</table>';
print '</div>';
print '</div><div class="clearboth"></div>';

print '<br><table class="noborder" width="100%">';
print '<tr class="liste_titre"><td colspan="2">Activity Timeline</td></tr>';
print '<tr><td width="200">Date</td><td>Count</td></tr>';

if (!empty($timeline)) {
    $max_count = max(array_column($timeline, 'n'));
    foreach($timeline as $r) {
        $bar_width = $max_count > 0 ? min(100, ($r->n / $max_count) * 100) : 0;
        print '<tr><td>'.dol_print_date(dol_stringtotime($r->d), '%A %d %B %Y').'</td>';
        print '<td>'.$r->n;
        if ($bar_width > 0) {
            print ' <div style="background: #4CAF50; height: 8px; width: '.$bar_width.'%; margin-top: 2px; border-radius: 2px;"></div>';
        }
        print '</td></tr>';
    }
} else {
    print '<tr><td colspan="2">No timeline data available</td></tr>';
}
print '</table>';

llxFooter();
