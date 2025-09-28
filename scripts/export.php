<?php
/**
 * Export endpoint
 * Path: custom/useractivitytracker/scripts/export.php
 * Version: 2.5.0 — enable triggers by default, fix user tracking
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
    $fallbacks = array('../../main.inc.php', '../../../main.inc.php', '../main.inc.php');
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
if (empty($user->rights->useractivitytracker->export)) accessforbidden();

$format = GETPOST('format','alpha');
$from = GETPOST('from','alpha');
$to = GETPOST('to','alpha');
$search_action = GETPOST('search_action','alpha');
$search_user = GETPOST('search_user','alpha');
$search_element = GETPOST('search_element','alpha');

if (empty($from)) $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
if (empty($to)) $to = dol_print_date(dol_now(), '%Y-%m-%d');

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

$sql = "SELECT datestamp, action, element_type, object_id, ref, userid, username, ip, severity, note 
        FROM ".$db->prefix()."alt_user_activity".$cond." 
        ORDER BY datestamp DESC";

$res = $db->query($sql);

$filename = "user_activity_{$from}_to_{$to}".($search_action ? "_action_".$search_action : "").".".($format==='xls'?'xls':'csv');
header("Content-Type: text/".($format==='xls'?'xls':'csv'));
header('Content-Disposition: attachment; filename="'.$filename.'"');

echo "Date,Action,Element,ObjectID,Ref,UserID,Username,IP,Severity,Note\n";
if ($res) {
    while ($o=$db->fetch_object($res)) {
        $row = array(
            $o->datestamp, 
            $o->action, 
            $o->element_type, 
            $o->object_id, 
            $o->ref, 
            $o->userid, 
            $o->username, 
            $o->ip, 
            $o->severity, 
            $o->note
        );
        $row = array_map(function($v){
            $v = (string)$v;
            $v = str_replace(["\r","\n","\""], [" "," ","\"\""], $v);
            return '"'.$v.'"';
        }, $row);
        echo implode(',', $row)."\n";
    }
}
exit;
