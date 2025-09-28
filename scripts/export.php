<?php
/**
 * Export endpoint
 * Path: custom/useractivitytracker/scripts/export.php
 * Version: 2025-09-27.beta-1
 */
require '../main.inc.php';
if (empty($user->rights->useractivitytracker->export)) accessforbidden();

$format = GETPOST('format','alpha');
$from = GETPOST('from','alpha');
$to = GETPOST('to','alpha');
if (empty($from)) $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
if (empty($to)) $to = dol_print_date(dol_now(), '%Y-%m-%d');

$cond = " WHERE entity=".(int)$conf->entity." AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";
$sql = "SELECT datestamp, action, element_type, object_id, ref, userid, username, ip, severity, note FROM ".$db->prefix()."alt_user_activity".$cond." ORDER BY datestamp DESC";
$res = $db->query($sql);

$filename = "user_activity_{$from}_to_{$to}." . ($format==='xls'?'xls':'csv');
header("Content-Type: text/".($format==='xls'?'xls':'csv'));
header('Content-Disposition: attachment; filename="'.$filename.'"');

echo "Date,Action,Element,ObjectID,Ref,UserID,Username,IP,Severity,Note\n";
if ($res) while ($o=$db->fetch_object($res)) {
    $row = array($o->datestamp,$o->action,$o->element_type,$o->object_id,$o->ref,$o->userid,$o->username,$o->ip,$o->severity,$o->note);
    $row = array_map(function($v){
        $v = (string)$v;
        $v = str_replace(["\r","\n","\""], [" "," ","\"\""], $v);
        return '"'.$v.'"';
    }, $row);
    echo implode(',', $row)."\n";
}
exit;
