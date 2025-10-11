<?php
/**
 * Export Data page
 * Path: custom/useractivitytracker/admin/useractivitytracker_export.php
 * Version: 2.8.0 — entity scoping, strict validation, severity badges
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
$from = trim(GETPOST('from','alphanohtml'));
$to = trim(GETPOST('to','alphanohtml'));
$search_action = trim(GETPOST('search_action','alphanohtml'));
$search_user = trim(GETPOST('search_user','alphanohtml'));
$search_element = trim(GETPOST('search_element','alphanohtml'));
$severity_filter = trim(GETPOST('severity_filter','alphanohtml'));

// Validate severity
$allowed_severity = array('info', 'notice', 'warning', 'error');
if ($severity_filter !== '' && !in_array($severity_filter, $allowed_severity, true)) {
    $severity_filter = '';
}

if (empty($from)) $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
if (empty($to)) $to = dol_print_date(dol_now(), '%Y-%m-%d');

llxHeader('', 'User Activity — Export Data');
print load_fiche_titre('User Activity — Export Data', '', 'object_useractivitytracker@useractivitytracker');

// Add severity badge styles (v2.7)
print '<style>
.uat-severity-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}
.uat-severity-info {
    background-color: #e3f2fd;
    color: #1976d2;
}
.uat-severity-notice {
    background-color: #fff3e0;
    color: #f57c00;
}
.uat-severity-warning {
    background-color: #fff9c4;
    color: #f57f17;
}
.uat-severity-error {
    background-color: #ffebee;
    color: #c62828;
}
</style>';

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
print '<td>Severity</td>';
print '<td><select name="severity_filter" class="flat">';
print '<option value="">All</option>';
print '<option value="info"'.($severity_filter==='info'?' selected':'').'>Info</option>';
print '<option value="notice"'.($severity_filter==='notice'?' selected':'').'>Notice</option>';
print '<option value="warning"'.($severity_filter==='warning'?' selected':'').'>Warning</option>';
print '<option value="error"'.($severity_filter==='error'?' selected':'').'>Error</option>';
print '</select></td>';
print '</tr>';

print '<tr>';
print '<td>Search Element</td>';
print '<td colspan="3"><input type="text" name="search_element" value="'.dol_escape_htmltag($search_element).'" class="flat" placeholder="e.g. thirdparty, invoice" size="30"></td>';
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

// Export buttons with JSON/NDJSON support (v2.7)
$export_base = dol_buildpath('/useractivitytracker/scripts/export.php', 1);
$export_params = '&from='.urlencode($from).'&to='.urlencode($to)
    .'&search_action='.urlencode($search_action).'&search_user='.urlencode($search_user)
    .'&search_element='.urlencode($search_element).'&severity_filter='.urlencode($severity_filter);

print '<div class="center">';
print '<div class="inline-block" style="margin: 0 10px;">';
print '<a class="butAction" href="'.$export_base.'?format=csv'.$export_params.'">';
print '<i class="fas fa-file-csv"></i> Export CSV</a>';
print '</div>';

print '<div class="inline-block" style="margin: 0 10px;">';
print '<a class="butAction" href="'.$export_base.'?format=xls'.$export_params.'">';
print '<i class="fas fa-file-excel"></i> Export Excel</a>';
print '</div>';

print '<div class="inline-block" style="margin: 0 10px;">';
print '<a class="butAction" href="'.$export_base.'?format=json'.$export_params.'">';
print '<i class="fas fa-file-code"></i> Export JSON</a>';
print '</div>';

print '<div class="inline-block" style="margin: 0 10px;">';
print '<a class="butAction" href="'.$export_base.'?format=ndjson'.$export_params.'">';
print '<i class="fas fa-stream"></i> Export NDJSON</a>';
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

// Get preview data (limited to 20 rows) with strict entity scoping
$entity = (int)$conf->entity;
$cond = " WHERE entity=" . $entity . " AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";

if (!empty($search_action)) {
    $cond .= " AND action LIKE '%".$db->escape($search_action)."%'";
}
if (!empty($search_user)) {
    $cond .= " AND username LIKE '%".$db->escape($search_user)."%'";  
}
if (!empty($search_element)) {
    $cond .= " AND element_type LIKE '%".$db->escape($search_element)."%'";
}
if (!empty($severity_filter)) {
    $cond .= " AND severity = '".$db->escape($severity_filter)."'";
}

$sql = "SELECT datestamp, action, element_type, username, ip, severity 
        FROM ".$db->prefix()."useractivitytracker_activity".$cond." 
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
        
        // Severity badge (v2.7)
        $sev = strtolower($obj->severity ?: 'info');
        $sev_class = 'uat-severity-badge uat-severity-' . $sev;
        
        print '<tr class="oddeven">';
        print '<td>'.dol_print_date($db->jdate($obj->datestamp), 'dayhour').'</td>';
        print '<td>'.dol_escape_htmltag($obj->action ?: 'N/A').'</td>';
        print '<td>'.dol_escape_htmltag($obj->element_type ?: 'N/A').'</td>';
        print '<td>'.dol_escape_htmltag($obj->username ?: 'N/A').'</td>';
        print '<td>'.dol_escape_htmltag($obj->ip ?: 'N/A').'</td>';
        print '<td><span class="'.$sev_class.'">'.strtoupper($sev).'</span></td>';
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