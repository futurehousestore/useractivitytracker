
<?php
/**
 * Dashboard page
 * Path: custom/useractivitytracker/admin/useractivitytracker_dashboard.php
 * Version: 2025-09-27.beta-1
 */
require '../../main.inc.php';

if (empty($user->rights->useractivitytracker->read)) accessforbidden();

$from = GETPOST('from','alpha');
$to = GETPOST('to','alpha');
if (empty($from)) $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
if (empty($to)) $to = dol_print_date(dol_now(), '%Y-%m-%d');

$prefix = $db->prefix();
$cond = " WHERE entity=".(int)$conf->entity." AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";

$byType = array();
$sql = "SELECT action, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY action ORDER BY n DESC LIMIT 10";
$res = $db->query($sql);
if ($res) while ($o=$db->fetch_object($res)) $byType[]=$o;

$byUser = array();
$sql = "SELECT username, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY username ORDER BY n DESC LIMIT 10";
$res = $db->query($sql);
if ($res) while ($o=$db->fetch_object($res)) $byUser[]=$o;

$timeline = array();
$sql = "SELECT DATE(datestamp) as d, COUNT(*) as n FROM {$prefix}alt_user_activity {$cond} GROUP BY DATE(datestamp) ORDER BY d ASC";
$res = $db->query($sql);
if ($res) while ($o=$db->fetch_object($res)) $timeline[]=$o;

llxHeader('', 'User Activity — Dashboard');
print load_fiche_titre('User Activity — Dashboard');
print '<form method="get"><div class="fichecenter">';
print 'From <input type="date" name="from" value="'.dol_escape_htmltag($from).'">';
print ' To <input type="date" name="to" value="'.dol_escape_htmltag($to).'">';
print ' <input type="submit" class="button" value="Filter">';
print ' <a class="button" href="/custom/useractivitytracker/scripts/export.php?format=csv&from='.urlencode($from).'&to='.urlencode($to).'">Export CSV</a>';
print ' <a class="button" href="/custom/useractivitytracker/scripts/export.php?format=xls&from='.urlencode($from).'&to='.urlencode($to).'">Export XLS</a>';
print '</div></form>';

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="noborder" width="100%"><tr class="liste_titre"><td>Activity by Type</td><td class="right">Count</td></tr>';
foreach($byType as $r) print '<tr><td>'.dol_escape_htmltag($r->action).'</td><td class="right">'.$r->n.'</td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="noborder" width="100%"><tr class="liste_titre"><td>Activity by User</td><td class="right">Count</td></tr>';
foreach($byUser as $r) print '<tr><td>'.dol_escape_htmltag($r->username).'</td><td class="right">'.$r->n.'</td></tr>';
print '</table>';
print '</div>';
print '</div><div class="clearboth"></div>';

print '<br><table class="noborder" width="100%">';
print '<tr class="liste_titre"><td colspan="2">Activity Timeline</td></tr>';
print '<tr><td>Date</td><td>Count</td></tr>';
foreach($timeline as $r) print '<tr><td>'.dol_escape_htmltag($r->d).'</td><td>'.$r->n.'</td></tr>';
print '</table>';

llxFooter();
