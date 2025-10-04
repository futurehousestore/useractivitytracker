<?php
/**
 * Activity Details Viewer
 * Path: custom/useractivitytracker/admin/useractivitytracker_view.php
 * Version: 2.7.0 — entity scoping, payload viewer enhancements
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

if (empty($user->rights->useractivitytracker->read)) accessforbidden();

$id = GETPOST('id', 'int');
$activity = new UserActivity($db);

if (!$activity->fetch($id)) {
    accessforbidden('Activity not found');
}

llxHeader('', 'Activity Details');
print load_fiche_titre('Activity Details #'.$id, '', 'object_useractivitytracker@useractivitytracker');

print '<div class="fichecenter">';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><th colspan="2">Activity Information</th></tr>';

print '<tr><td width="200"><strong>ID</strong></td><td>'.$activity->id.'</td></tr>';
print '<tr><td><strong>Date/Time</strong></td><td>'.dol_print_date(dol_stringtotime($activity->datestamp), 'dayhour').'</td></tr>';
print '<tr><td><strong>Action</strong></td><td><code>'.dol_escape_htmltag($activity->action).'</code></td></tr>';
print '<tr><td><strong>User</strong></td><td>'.dol_escape_htmltag($activity->username).' (ID: '.$activity->userid.')</td></tr>';
print '<tr><td><strong>Element Type</strong></td><td>'.dol_escape_htmltag($activity->element_type ?: 'N/A').'</td></tr>';
print '<tr><td><strong>Object ID</strong></td><td>'.($activity->object_id ?: 'N/A').'</td></tr>';
print '<tr><td><strong>Reference</strong></td><td>'.dol_escape_htmltag($activity->ref ?: 'N/A').'</td></tr>';
print '<tr><td><strong>IP Address</strong></td><td>'.dol_escape_htmltag($activity->ip ?: 'N/A').'</td></tr>';
print '<tr><td><strong>Severity</strong></td><td>';

$severity_colors = array(
    'info' => '#0984e3',
    'notice' => '#6c5ce7', 
    'warning' => '#fdcb6e',
    'error' => '#d63031'
);
$color = $severity_colors[$activity->severity] ?? '#636e72';
print '<span style="color: '.$color.'; font-weight: bold;">'.strtoupper($activity->severity ?: 'INFO').'</span>';
print '</td></tr>';

print '<tr><td><strong>Entity</strong></td><td>'.$activity->entity.'</td></tr>';

if ($activity->note) {
    print '<tr><td><strong>Note</strong></td><td>'.dol_escape_htmltag($activity->note).'</td></tr>';
}

print '</table>';

// Payload section
if ($activity->payload) {
    $payload_data = json_decode($activity->payload, true);
    
    print '<br><table class="noborder" width="100%">';
    print '<tr class="liste_titre"><th><i class="fas fa-database"></i> Payload Data</th></tr>';
    print '<tr><td>';
    
    if (is_array($payload_data)) {
        print '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; overflow-x: auto;">';
        print htmlspecialchars(json_encode($payload_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        print '</pre>';
    } else {
        print '<div class="warning">Invalid JSON payload</div>';
        print '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;">';
        print htmlspecialchars($activity->payload);
        print '</pre>';
    }
    
    print '</td></tr>';
    print '</table>';
}

// Related activities (same user, similar timeframe)
$related_sql = "SELECT rowid, datestamp, action, element_type, ref, severity 
                FROM ".$db->prefix()."alt_user_activity 
                WHERE userid = ".(int)$activity->userid." 
                AND entity = ".(int)$conf->entity."
                AND rowid != ".(int)$activity->id."
                AND datestamp BETWEEN DATE_SUB('".$db->escape($activity->datestamp)."', INTERVAL 1 HOUR) 
                AND DATE_ADD('".$db->escape($activity->datestamp)."', INTERVAL 1 HOUR)
                ORDER BY datestamp DESC 
                LIMIT 10";

$related_res = $db->query($related_sql);
$related_activities = array();
if ($related_res) {
    while ($obj = $db->fetch_object($related_res)) {
        $related_activities[] = $obj;
    }
}

if (!empty($related_activities)) {
    print '<br><table class="noborder" width="100%">';
    print '<tr class="liste_titre"><th colspan="4"><i class="fas fa-history"></i> Related Activities (±1 hour)</th></tr>';
    print '<tr class="liste_titre"><th><i class="fas fa-clock"></i> Time</th><th><i class="fas fa-bolt"></i> Action</th><th><i class="fas fa-cube"></i> Element</th><th><i class="fas fa-tag"></i> Ref</th></tr>';
    
    foreach ($related_activities as $rel) {
        print '<tr>';
        print '<td>'.dol_print_date(dol_stringtotime($rel->datestamp), 'hour').'</td>';
        print '<td><a href="?id='.$rel->rowid.'">'.dol_escape_htmltag($rel->action).'</a></td>';
        print '<td>'.dol_escape_htmltag($rel->element_type ?: 'N/A').'</td>';
        print '<td>'.dol_escape_htmltag($rel->ref ?: 'N/A').'</td>';
        print '</tr>';
    }
    
    print '</table>';
}

print '</div>';

print '<div class="tabsAction">';
print '<a class="butAction" href="useractivitytracker_dashboard.php">← Back to Dashboard</a>';
if (!empty($user->rights->useractivitytracker->export)) {
    $export_params = 'format=csv&from='.dol_print_date(dol_stringtotime($activity->datestamp), '%Y-%m-%d').'&to='.dol_print_date(dol_stringtotime($activity->datestamp), '%Y-%m-%d');
    print '<a class="butAction" href="../scripts/export.php?'.$export_params.'">Export Day</a>';
}
print '</div>';

llxFooter();