<?php
/**
 * Export endpoint
 * Path: custom/useractivitytracker/scripts/export.php
 * Version: 2.7.0 — entity scoping, JSON/NDJSON support, strict validation
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

$format = trim(GETPOST('format','alpha'));
$from = trim(GETPOST('from','alphanohtml'));
$to = trim(GETPOST('to','alphanohtml'));
$search_action = trim(GETPOST('search_action','alphanohtml'));
$search_user = trim(GETPOST('search_user','alphanohtml'));
$search_element = trim(GETPOST('search_element','alphanohtml'));
$severity_filter = trim(GETPOST('severity_filter','alphanohtml'));

// Validate format
$allowed_formats = array('csv', 'xls', 'json', 'ndjson');
if (!in_array($format, $allowed_formats, true)) {
    $format = 'csv';
}

// Validate severity
$allowed_severity = array('info', 'notice', 'warning', 'error');
if ($severity_filter !== '' && !in_array($severity_filter, $allowed_severity, true)) {
    $severity_filter = '';
}

if (empty($from)) $from = dol_print_date(dol_time_plus_duree(dol_now(), -30, 'd'), '%Y-%m-%d');
if (empty($to)) $to = dol_print_date(dol_now(), '%Y-%m-%d');

// Entity scoping (v2.7)
$entity = (int)$conf->entity;
$cond = " WHERE entity=" . $entity . " AND datestamp BETWEEN '".$db->escape($from)." 00:00:00' AND '".$db->escape($to)." 23:59:59'";

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
if (!empty($severity_filter)) {
    $cond .= " AND severity = '".$db->escape($severity_filter)."'";
}

$sql = "SELECT datestamp, action, element_type, object_id, ref, userid, username, ip, severity, note 
        FROM ".$db->prefix()."alt_user_activity".$cond." 
        ORDER BY datestamp DESC";

$res = $db->query($sql);

// Set appropriate headers based on format
if ($format === 'json') {
    $filename = "user_activity_{$from}_to_{$to}.json";
    header("Content-Type: application/json");
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    
    $records = array();
    if ($res) {
        while ($o = $db->fetch_object($res)) {
            $records[] = array(
                'datestamp' => $o->datestamp,
                'action' => $o->action,
                'element_type' => $o->element_type,
                'object_id' => $o->object_id,
                'ref' => $o->ref,
                'userid' => $o->userid,
                'username' => $o->username,
                'ip' => $o->ip,
                'severity' => $o->severity,
                'note' => $o->note
            );
        }
    }
    echo json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} elseif ($format === 'ndjson') {
    $filename = "user_activity_{$from}_to_{$to}.ndjson";
    header("Content-Type: application/x-ndjson");
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    
    if ($res) {
        while ($o = $db->fetch_object($res)) {
            $record = array(
                'datestamp' => $o->datestamp,
                'action' => $o->action,
                'element_type' => $o->element_type,
                'object_id' => $o->object_id,
                'ref' => $o->ref,
                'userid' => $o->userid,
                'username' => $o->username,
                'ip' => $o->ip,
                'severity' => $o->severity,
                'note' => $o->note
            );
            echo json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }
    }
    
} else {
    // CSV/XLS format
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
}
exit;
