<?php
// Path: custom/useractivitytracker/scripts/tracktime.php
require_once dirname(__DIR__, 2).'/main.inc.php';

if (empty($conf->useractivitytracker->enabled) || empty($user->id)) { http_response_code(204); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$dur = isset($data['duration_sec']) ? (int)$data['duration_sec'] : 0;
$ref = isset($data['uri']) ? (string)$data['uri'] : null;

$db  = $db ?? $GLOBALS['db'];
$ip  = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? (
          !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
          ? preg_split('/\s*,\s*/', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
          : ($_SERVER['REMOTE_ADDR'] ?? null)
      );
$now = function_exists('dol_now') ? dol_now() : time();

// Ensure correct table exists (and migrate old mistaken name if present)
$tNew = $db->prefix().'useractivitytracker_log';
$tOld = $db->prefix().'alt_user_activity';
$resNew = $db->query("SHOW TABLES LIKE '".$db->escape($tNew)."'");
if (!($resNew && $db->num_rows($resNew) > 0)) {
    $resOld = $db->query("SHOW TABLES LIKE '".$db->escape($tOld)."'");
    if ($resOld && $db->num_rows($resOld) > 0) {
        @$db->query("RENAME TABLE ".$tOld." TO ".$tNew);
    } else {
        @$db->query("CREATE TABLE ".$tNew." (
            rowid INT(11) NOT NULL AUTO_INCREMENT,
            tms TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            datestamp DATETIME NULL,
            entity INT(11) NOT NULL DEFAULT 1,
            action VARCHAR(128) NOT NULL,
            element_type VARCHAR(64) NULL,
            object_id INT(11) NULL,
            ref VARCHAR(128) NULL,
            userid INT(11) NULL,
            username VARCHAR(128) NULL,
            ip VARCHAR(64) NULL,
            payload LONGTEXT NULL,
            severity VARCHAR(16) NULL,
            kpi1 DECIMAL(24,6) NULL,
            kpi2 DECIMAL(24,6) NULL,
            note VARCHAR(255) NULL,
            PRIMARY KEY (rowid),
            INDEX idx_action (action),
            INDEX idx_element (element_type, object_id),
            INDEX idx_user (userid),
            INDEX idx_datestamp (datestamp),
            INDEX idx_entity (entity),
            INDEX idx_entity_datestamp (entity, datestamp),
            INDEX idx_entity_user_datestamp (entity, userid, datestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
}
$t = $tNew;

$json = json_encode(array(
    'event'   => $data['event'] ?? 'pagehide',
    'title'   => $data['title'] ?? null,
    'raw'     => $data,
), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

$sql = "INSERT INTO ".$t." (datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity, kpi1, kpi2, note) VALUES (".
       $db->idate($now).", ".(int)$conf->entity.", 'PAGE_TIME', 'page', NULL, ".
       ($ref ? "'".$db->escape($ref)."'" : "NULL").", ".
       (int)$user->id.", '".$db->escape($user->login)."', ".
       ($ip ? "'".$db->escape($ip)."'" : "NULL").", ".
       "'".$db->escape($json)."', 'info', ".(int)$dur.", NULL, NULL)";
@$db->query($sql);

http_response_code(204);
