<?php
/**
 * Activity Analysis page
 * Path: custom/useractivitytracker/admin/useractivitytracker_analysis.php
 * Version: 1.0.0
 */
require '../../main.inc.php';
require_once '../class/useractivity.class.php';

if (empty($user->rights->useractivitytracker->read)) accessforbidden();

$action = GETPOST('action','alpha');
$from = GETPOST('from','alpha');
$to = GETPOST('to','alpha');

if (empty($from)) $from = dol_print_date(dol_time_plus_duree(dol_now(), -7, 'd'), '%Y-%m-%d');
if (empty($to)) $to = dol_print_date(dol_now(), '%Y-%m-%d');

$activity = new UserActivity($db);

// Get comprehensive stats
$stats = $activity->getActivityStats($from, $to, $conf->entity);

// Get anomalies if enabled
$anomalies = array();
if (getDolGlobalString('USERACTIVITYTRACKER_ENABLE_ANOMALY')) {
    $anomalies = $activity->detectAnomalies($conf->entity);
}

// Get top elements
$prefix = $db->prefix();
$cond = " WHERE entity=".(int)$conf->entity." AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";

$topElements = array();
$sql = "SELECT element_type, COUNT(*) as n, COUNT(DISTINCT userid) as unique_users 
        FROM {$prefix}alt_user_activity {$cond} AND element_type IS NOT NULL 
        GROUP BY element_type ORDER BY n DESC LIMIT 15";
$res = $db->query($sql);
if ($res) while ($o=$db->fetch_object($res)) $topElements[]=$o;

$hourlyActivity = array();
$sql = "SELECT HOUR(datestamp) as h, COUNT(*) as n 
        FROM {$prefix}alt_user_activity {$cond} 
        GROUP BY HOUR(datestamp) ORDER BY h";
$res = $db->query($sql);
if ($res) while ($o=$db->fetch_object($res)) $hourlyActivity[(int)$o->h]=$o->n;

// Fill missing hours with 0
for ($h = 0; $h < 24; $h++) {
    if (!isset($hourlyActivity[$h])) $hourlyActivity[$h] = 0;
}

llxHeader('', 'User Activity — Analysis');
print load_fiche_titre('User Activity — Advanced Analysis', '', 'object_useractivitytracker@useractivitytracker');

// Filter form
print '<form method="get" class="border valignmiddle">';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><th colspan="4">Analysis Period</th></tr>';
print '<tr>';
print '<td>From</td><td><input type="date" name="from" value="'.dol_escape_htmltag($from).'"></td>';
print '<td>To</td><td><input type="date" name="to" value="'.dol_escape_htmltag($to).'"></td>';
print '</tr>';
print '<tr><td colspan="4" class="center">';
print '<input type="submit" class="button" value="Update Analysis">';
print '</td></tr>';
print '</table>';
print '</form>';

// Summary metrics
print '<br><div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><th colspan="2">Activity Metrics</th></tr>';
print '<tr><td>Total Activities</td><td class="right"><strong>'.number_format($stats['total']).'</strong></td></tr>';
print '<tr><td>Unique Actions</td><td class="right">'.count($stats['by_action']).'</td></tr>';
print '<tr><td>Active Users</td><td class="right">'.count($stats['by_user']).'</td></tr>';
print '<tr><td>Affected Elements</td><td class="right">'.count($topElements).'</td></tr>';
print '<tr><td>Daily Average</td><td class="right">'.round($stats['total'] / max(1, count($stats['by_day'])), 1).'</td></tr>';
$busiest_day = '';
if (!empty($stats['by_day'])) {
    $busiest_day = array_keys($stats['by_day'], max($stats['by_day']))[0];
}
print '<tr><td>Busiest Day</td><td class="right">'.$busiest_day.'</td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><th colspan="2">Security Status</th></tr>';

if (getDolGlobalString('USERACTIVITYTRACKER_ENABLE_ANOMALY')) {
    $anomaly_count = count($anomalies);
    $severity_style = $anomaly_count > 0 ? 'color: #d63031; font-weight: bold;' : 'color: #00b894; font-weight: bold;';
    print '<tr><td>Anomalies Detected</td><td class="right"><span style="'.$severity_style.'">'.$anomaly_count.'</span></td></tr>';
    
    if (!empty($stats['by_severity'])) {
        foreach ($stats['by_severity'] as $sev => $count) {
            $color = '#0984e3'; // info blue
            if ($sev == 'warning') $color = '#fdcb6e'; // warning yellow
            if ($sev == 'error') $color = '#d63031'; // error red
            if ($sev == 'notice') $color = '#6c5ce7'; // notice purple
            print '<tr><td>'.ucfirst($sev).' Events</td><td class="right"><span style="color: '.$color.'; font-weight: bold;">'.$count.'</span></td></tr>';
        }
    }
} else {
    print '<tr><td colspan="2"><em>Anomaly detection disabled</em></td></tr>';
}

print '</table>';
print '</div>';
print '</div><div class="clearboth"></div>';

// Hourly activity chart
print '<br><table class="noborder" width="100%">';
print '<tr class="liste_titre"><th colspan="2">Activity by Hour of Day</th></tr>';
$max_hourly = max($hourlyActivity);
for ($h = 0; $h < 24; $h++) {
    $count = $hourlyActivity[$h];
    $bar_width = $max_hourly > 0 ? ($count / $max_hourly) * 100 : 0;
    print '<tr>';
    print '<td width="100">'.sprintf('%02d:00', $h).'</td>';
    print '<td>'.$count;
    if ($bar_width > 0) {
        print ' <div style="background: #2196F3; height: 12px; width: '.$bar_width.'%; margin-top: 2px; border-radius: 2px;"></div>';
    }
    print '</td>';
    print '</tr>';
}
print '</table>';

// Top elements with user engagement
if (!empty($topElements)) {
    print '<br><table class="noborder" width="100%">';
    print '<tr class="liste_titre"><th>Element Type</th><th class="right">Activities</th><th class="right">Unique Users</th><th class="right">Avg per User</th></tr>';
    foreach ($topElements as $elem) {
        $avg_per_user = $elem->unique_users > 0 ? round($elem->n / $elem->unique_users, 1) : 0;
        print '<tr>';
        print '<td>'.dol_escape_htmltag($elem->element_type).'</td>';
        print '<td class="right">'.$elem->n.'</td>';
        print '<td class="right">'.$elem->unique_users.'</td>';
        print '<td class="right">'.$avg_per_user.'</td>';
        print '</tr>';
    }
    print '</table>';
}

// Anomalies section
if (!empty($anomalies)) {
    print '<br><table class="noborder" width="100%">';
    print '<tr class="liste_titre"><th colspan="2">Security Anomalies</th></tr>';
    foreach ($anomalies as $anomaly) {
        $icon = '';
        if ($anomaly['type'] == 'suspicious_login') $icon = '⚠️';
        if ($anomaly['type'] == 'bulk_activity') $icon = '📊';
        
        print '<tr>';
        print '<td width="50" class="center">'.$icon.'</td>';
        print '<td>'.$anomaly['description'].'</td>';
        print '</tr>';
    }
    print '</table>';
}

// Action recommendations
print '<br><div class="info">';
print '<h3>💡 Recommendations</h3>';
print '<ul>';

if ($stats['total'] == 0) {
    print '<li>No activity data found for the selected period. Check if the module is properly tracking activities.</li>';
} else {
    if (count($stats['by_user']) < 3) {
        print '<li>Consider expanding user adoption - only '.count($stats['by_user']).' users active in this period.</li>';
    }
    
    if (getDolGlobalString('USERACTIVITYTRACKER_ENABLE_ANOMALY') && !empty($anomalies)) {
        print '<li><strong>Security Alert:</strong> '.count($anomalies).' anomalies detected. Review the security section above.</li>';
    }
    
    if (!getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_URL')) {
        print '<li>Consider setting up webhook notifications for real-time activity monitoring.</li>';
    }
    
    $retention_days = getDolGlobalInt('USERACTIVITYTRACKER_RETENTION_DAYS', 365);
    if ($retention_days > 90) {
        print '<li>Your retention period is '.$retention_days.' days. Consider reducing it for better performance.</li>';
    }
    
    print '<li>Use the export feature to create regular activity reports for compliance or analysis.</li>';
}

print '</ul>';
print '</div>';

llxFooter();