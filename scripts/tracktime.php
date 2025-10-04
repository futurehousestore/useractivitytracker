<?php
/**
 * Time-on-page beacon receiver
 * Path: custom/useractivitytracker/scripts/tracktime.php
 * Writes action=PAGE_TIME with duration (seconds) in kpi1 and URI in ref
 */

require_once dirname(__DIR__, 2) . '/main.inc.php'; // ../../main.inc.php

// Must be logged in and module enabled
if (empty($conf->useractivitytracker->enabled)) {
    http_response_code(204); exit;
}
if (empty($user->id)) {
    http_response_code(204); exit;
}
if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)) {
    http_response_code(204); exit;
}
if (function_exists('getDolGlobalString') && getDolGlobalString('USERACTIVITYTRACKER_SKIP_USER_'.(int)$user->id)) {
    http_response_code(204); exit;
}

// Parse JSON body (sendBeacon often uses 'application/json' or 'text/plain')
$raw = file_get_contents('php://input');
$data = array();
if ($raw !== false && strlen($raw)) {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) $data = $tmp;
}

// Validate & sanitize fields
$uri   = isset($data['uri']) ? (string)$data['uri'] : '';
$title = isset($data['title']) ? (string)$data['title'] : '';
$event = isset($data['event']) ? (string)$data['event'] : 'hidden';
$dur   = isset($data['duration_sec']) ? (int)$data['duration_sec'] : 0;

$dur = max(1, min($dur, 86400)); // clamp 1..86400
$ref = substr($uri, 0, 128);

// Build payload
$payload = array(
    'event'    => $event,
    'uri'      => $uri,
    'title'    => $title,
    'duration' => $dur,
    'agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null
);
$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Insert
$now = function_exists('dol_now') ? dol_now() : time();
$ip  = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? (
        !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? preg_split('/\s*,\s*/', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]
        : ($_SERVER['REMOTE_ADDR'] ?? null)
);

$sql  = "INSERT INTO ".$db->prefix()."alt_user_activity".
        " (datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity, kpi1, note) VALUES (".
        $db->idate($now).", ".
        (int)$conf->entity.", ".
        "'PAGE_TIME', 'page', NULL, ".
        ($ref ? "'".$db->escape($ref)."'" : "NULL").", ".
        (int)$user->id.", ".
        "'".$db->escape($user->login)."', ".
        ($ip ? "'".$db->escape($ip)."'" : "NULL").", ".
        "'".$db->escape($json)."', ".
        "'info', ".
        (int)$dur.", ".
        "NULL)";

$db->query($sql);
// We don't echo anything to keep beacon fast
http_response_code(204);
