<?php
/**
 * Export Data page
 * Path: custom/useractivitytracker/admin/useractivitytracker_export.php
 * Version: 2.4.0 — dynamic main.inc.php resolver, bug fixes
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
require_once '../class/useractivity.class.php';

if (empty($user->rights->useractivitytracker->export)) accessforbidden();

$action = GETPOST('action','alpha');
$from = GETPOST('from','alpha');
$to = GETPOST('to','alpha');
$search_action = GETPOST('search_action','alpha');
$search_user = GETPOST('search_user','alpha');
$search_element = GETPOST('search_element','alpha');

if (empty($from)) $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
if (empty($to)) $to = dol_print_date(dol_now(), '%Y-%m-%d');

llxHeader('', 'User Activity — Export Data');
print load_fiche_titre('User Activity — Export Data', '', 'object_useractivitytracker@useractivitytracker');

print '<div class="fichecenter">';

// Export filters form
print '<div class="div-table-responsive-no-min">';
print '<form method="get" class="noborder" width="100%">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th colspan="4">Export Filters</th>';
print '</tr>';

print '<tr>';
print '<td width="200">Date Range</td>';
print '<td>';
print '<input type="date" name="from" value="'.dol_escape_htmltag($from).'" class="flat" style="margin-right: 10px;">';
print '<input type="date" name="to" value="'.dol_escape_htmltag($to).'" class="flat">';
print '</td>';
print '<td width="150">Search Action</td>';
print '<td><input type="text" name="search_action" value="'.dol_escape_htmltag($search_action).'" class="flat" placeholder="e.g. LOGIN, CREATE" size="15"></td>';
print '</tr>';

print '<tr>';
print '<td>Search User</td>';
print '<td><input type="text" name="search_user" value="'.dol_escape_htmltag($search_user).'" class="flat" placeholder="Username" size="20"></td>';
print '<td>Search Element</td>';
print '<td><input type="text" name="search_element" value="'.dol_escape_htmltag($search_element).'" class="flat" placeholder="e.g. thirdparty, invoice" size="15"></td>';
print '</tr>';

print '<tr>';
print '<td colspan="4" class="center">';
print '<input type="submit" value="Update Preview" class="button">';
print '</td>';
print '</tr>';

print '</table>';
print '</form>';
print '</div>';

print '<br>';

// Export buttons
$export_base = dol_buildpath('/useractivitytracker/scripts/export.php', 1);
print '<div class="center">';
print '<div class="inline-block" style="margin: 0 10px;">';
print '<a class="butAction" href="'.$export_base.'?format=csv&from='.urlencode($from).'&to='.urlencode($to)
    .'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user)
    .'&search_element='.urlencode($search_element).'">';
print '<i class="fas fa-file-csv"></i> Export CSV</a>';
print '</div>';

print '<div class="inline-block" style="margin: 0 10px;">';
print '<a class="butAction" href="'.$export_base.'?format=xls&from='.urlencode($from).'&to='.urlencode($to)
    .'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user)
    .'&search_element='.urlencode($search_element).'">';
print '<i class="fas fa-file-excel"></i> Export Excel</a>';
print '</div>';
print '</div>';

print '<br>';

// Preview section
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Date/Time</th>';
print '<th>Action</th>';
print '<th>Element</th>';
print '<th>User</th>';
print '<th>IP</th>';
print '<th>Severity</th>';
print '</tr>';

// Get preview data (limited to 20 rows)
$cond = " WHERE entity=".(int)$conf->entity." AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";

if (!empty($search_action)) {
    $cond .= " AND action LIKE '%".$db->escape($search_action)."%'";
}
if (!empty($search_user)) {
    $cond .= " AND username LIKE '%".$db->escape($search_user)."%'";  
}
if (!empty($search_element)) {
    $cond .= " AND element_type LIKE '%".$db->escape($search_element)."%'";
}

$sql = "SELECT datestamp, action, element_type, username, ip, severity 
        FROM ".$db->prefix()."alt_user_activity".$cond." 
        ORDER BY datestamp DESC 
        LIMIT 20";

$res = $db->query($sql);
$num = 0;
if ($res) $num = $db->num_rows($res);

if ($num == 0) {
    print '<tr><td colspan="6" class="center">';
    
    // Enhanced diagnostic message
    require_once '../class/useractivity.class.php';
    $activity = new UserActivity($db);
    $diagnostics = $activity->getDiagnostics($conf->entity);
    
    if (!$diagnostics['table_exists']) {
        print '❌ <strong>Database table missing</strong> - Module may not be properly installed.<br>';
        print '<small>Solution: Disable and re-enable the User Activity Tracker module</small>';
    } elseif ($diagnostics['recent_activity_count'] == 0) {
        print '⚠️ <strong>No activities found</strong> - Module may not be tracking activities.<br>';
        print '<small>Check: Server logs, trigger configuration, or try performing some actions in Dolibarr</small>';
    } else {
        print '📅 <strong>No activities found for the selected criteria</strong><br>';
        print '<small>Found '.$diagnostics['recent_activity_count'].' activities in last 7 days - try adjusting date range or filters</small>';
    }
    
    print '</td></tr>';
} else {
    $i = 0;
    while ($i < $num) {
        $obj = $db->fetch_object($res);
        
        $severity_colors = array(
            'info' => '#0984e3',
            'notice' => '#6c5ce7', 
            'warning' => '#fdcb6e',
            'error' => '#d63031'
        );
        $color = $severity_colors[$obj->severity] ?? '#636e72';
        
        print '<tr class="oddeven">';
        print '<td>'.dol_print_date($db->jdate($obj->datestamp), 'dayhour').'</td>';
        print '<td>'.dol_escape_htmltag($obj->action ?: 'N/A').'</td>';
        print '<td>'.dol_escape_htmltag($obj->element_type ?: 'N/A').'</td>';
        print '<td>'.dol_escape_htmltag($obj->username ?: 'N/A').'</td>';
        print '<td>'.dol_escape_htmltag($obj->ip ?: 'N/A').'</td>';
        print '<td><span style="color: '.$color.'; font-weight: bold;">'.strtoupper($obj->severity ?: 'INFO').'</span></td>';
        print '</tr>';
        $i++;
    }
}

print '</table>';
print '</div>';

print '<br>';
print '<div class="center">';
print '<a class="butAction" href="useractivitytracker_dashboard.php">← Back to Dashboard</a>';
print '</div>';

print '</div>';

llxFooter();