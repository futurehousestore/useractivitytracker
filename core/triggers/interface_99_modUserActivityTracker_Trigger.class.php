<?php
/**
 * Universal logger trigger for User Activity Tracker
 * Path: custom/useractivitytracker/core/triggers/interface_99_modUserActivityTracker_Trigger.class.php
 * Version: 2.5.1 — FIX: extend DolibarrTriggers + correct run_trigger() signature
 */

if (!class_exists('DolibarrTriggers')) {
    require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
}

class InterfaceUserActivityTrackerTrigger extends DolibarrTriggers
{
    public $db;
    public $family = 'user';
    public $description = 'Logs Dolibarr triggers into alt_user_activity';
    public $version = '2.5.1';
    public $name = 'InterfaceUserActivityTrackerTrigger';
    public $picto = 'useractivitytracker@useractivitytracker';

    public function __construct($db) { $this->db = $db; }
    public function getName()    { return $this->name; }
    public function getDesc()    { return $this->description; }
    public function getVersion() { return $this->version; }

    public function run_trigger($action, $object, $user, $langs, $conf)
    {
        if (empty($conf->useractivitytracker->enabled)) return 0;
        if (!getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)) return 0;
        if (getDolGlobalString('USERACTIVITYTRACKER_SKIP_USER_' . (int)$user->id)) return 0;

        $this->ensureTable();

        $element = is_object($object) && !empty($object->element) ? $object->element : null;
        $objid   = is_object($object) && isset($object->id) ? (int)$object->id : (is_object($object) && isset($object->rowid) ? (int)$object->rowid : null);
        $now     = dol_now();

        $payload = array(
            'GET'         => $_GET ?? null,
            'POST'        => array_filter($_POST ?? [], function($k){ return !in_array(strtolower($k), array('password','token','newpassword','oldpassword')); }, ARRAY_FILTER_USE_KEY),
            'class'       => is_object($object) ? get_class($object) : null,
            'ref'         => (is_object($object) && !empty($object->ref)) ? $object->ref : null,
            'session_id'  => session_id(),
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'http_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $max  = max(1, (int)getDolGlobalInt('USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE', 65536));
        if (strlen((string)$json) > $max) {
            unset($payload['GET'], $payload['POST']);
            $payload['_truncated'] = true;
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen((string)$json) > $max) $json = substr($json, 0, $max - 3) . '...';
        }

        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? preg_split('/\s*,\s*/', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] : ($_SERVER['REMOTE_ADDR'] ?? null));

        $severity = 'info';
        $uact = strtoupper((string)$action);
        if (strpos($uact, 'DELETE') !== false || strpos($uact, 'CANCEL') !== false) $severity = 'warning';
        elseif (strpos($uact, 'LOGIN') !== false || strpos($uact, 'LOGOUT') !== false) $severity = 'notice';
        elseif (strpos($uact, 'ERROR') !== false || strpos($uact, 'FAIL') !== false) $severity = 'error';

        $sql  = "INSERT INTO ".$this->db->prefix()."alt_user_activity";
        $sql .= " (datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity, note) VALUES (";
        $sql .=       $this->db->idate($now).", ";
        $sql .=       (int)$conf->entity.", ";
        $sql .=      "'".$this->db->escape($action)."', ";
        $sql .=       ($element ? "'".$this->db->escape($element)."'" : "NULL").", ";
        $sql .=       ($objid   ? (int)$objid : "NULL").", ";
        $sql .=       (is_object($object) && !empty($object->ref) ? "'".$this->db->escape($object->ref)."'" : "NULL").", ";
        $sql .=       (int)$user->id.", ";
        $sql .=      "'".$this->db->escape($user->login)."', ";
        $sql .=       ($ip ? "'".$this->db->escape($ip)."'" : "NULL").", ";
        $sql .=       ($json ? "'".$this->db->escape($json)."'" : "NULL").", ";
        $sql .=      "'".$this->db->escape($severity)."', ";
        $sql .=       "NULL)";
        if (!$this->db->query($sql)) { $this->errors[] = 'Insert error: '.$this->db->lasterror(); return -1; }

        $this->maybePushWebhook($action, $element, $objid, $user, $conf, $ip, $payload, $now);
        return 1;
    }

    private function ensureTable()
    {
        $t = $this->db->prefix().'alt_user_activity';
        $res = $this->db->query("SHOW TABLES LIKE '".$this->db->escape($t)."'");
        if ($res && $this->db->num_rows($res) > 0) return;
        $sql = "CREATE TABLE ".$t." (
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
            INDEX idx_entity (entity)
        ) ENGINE=innodb DEFAULT CHARSET=utf8;";
        $this->db->query($sql);
    }

    private function maybePushWebhook($action, $element, $objid, $user, $conf, $ip, $payload, $now)
    {
        $url = getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_URL', '');
        if (empty($url)) return;

        $data = array(
            'action'   => $action,
            'element'  => $element,
            'objectid' => $objid,
            'user'     => array('id' => (int)$user->id, 'login' => $user->login),
            'entity'   => (int)$conf->entity,
            'severity' => (isset($payload['_severity']) ? $payload['_severity'] : null),
            'ip'       => $ip,
            'payload'  => $payload,
            'time'     => dol_print_date($now, 'dayhourrfc')
        );
        $payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = array('Content-Type: application/json', 'Content-Length: '.strlen($payloadJson), 'User-Agent: Dolibarr-UserActivityTracker/2.5.1');
        $secret  = getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_SECRET', '');
        if (!empty($secret)) {
            $sig = hash_hmac('sha256', $payloadJson, $secret);
            $headers[] = 'X-Webhook-Secret: '.$secret;
            $headers[] = 'X-Hub-Signature-256: sha256='.$sig;
        }

        $max = 3; $delay = 1;
        for ($i = 0; $i < $max; $i++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payloadJson,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 5
            ));
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300) break;
            sleep($delay);
            $delay *= 2;
        }
    }
}
