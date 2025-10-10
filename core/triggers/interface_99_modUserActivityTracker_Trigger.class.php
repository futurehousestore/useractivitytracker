<?php
/**
 * Universal trigger — User Activity Tracker
 * Path: custom/useractivitytracker/core/triggers/interface_99_modUserActivityTracker_Trigger.class.php
 * Version: 2.7.0 — extends DolibarrTriggers; master switch gate
 */

if (!class_exists('DolibarrTriggers')) {
    require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
}

class InterfaceUserActivityTrackerTrigger extends DolibarrTriggers
{
    /** @var DoliDB */
    public $db;

    public $family      = 'user';
    public $description = 'Logs Dolibarr triggers into alt_user_activity';
    public $version     = '2.7.0';
    public $name        = 'InterfaceUserActivityTrackerTrigger';
    public $picto       = 'useractivitytracker@useractivitytracker';

    public function __construct($db) { $this->db = $db; }
    public function getName()    { return $this->name; }
    public function getDesc()    { return $this->description; }
    public function getVersion() { return $this->version; }

    /**
     * @param string   $action
     * @param object   $object
     * @param User     $user
     * @param Translate $langs
     * @param Conf     $conf
     * @return int <0 if KO, >0 if OK, 0 if nothing done
     */
    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        // MASTER switch (v2.7 - central gate)
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1)) return 0;
        
        // Module + toggles
        if (empty($conf->useractivitytracker->enabled)) return 0;
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)) return 0;
        if (function_exists('getDolGlobalString') && !empty($user->id) && getDolGlobalString('USERACTIVITYTRACKER_SKIP_USER_'.(int)$user->id)) return 0;

        $this->ensureTable();

        $element = is_object($object) && !empty($object->element) ? $object->element : null;
        $objid   = is_object($object) && isset($object->id) ? (int)$object->id : ((is_object($object) && isset($object->rowid)) ? (int)$object->rowid : null);
        $ref     = (is_object($object) && !empty($object->ref)) ? $object->ref : null;
        $now     = function_exists('dol_now') ? dol_now() : time();

        // Build payload (sanitised)
        $payload = array(
            'class'       => is_object($object) ? get_class($object) : null,
            'element'     => $element,
            'object_id'   => $objid,
            'ref'         => $ref,
            'session_id'  => session_id(),
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'http_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );

        // Cap payload
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $max  = function_exists('getDolGlobalInt') ? max(1,(int)getDolGlobalInt('USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE',65536)) : 65536;
        if (strlen((string)$json) > $max) $json = substr($json, 0, $max - 3) . '...';

        // IP (proxy aware)
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? (
                !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? preg_split('/\s*,\s*/', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]
                : ($_SERVER['REMOTE_ADDR'] ?? null)
        );

        // Severity heuristics
        $severity = 'info';
        $UA = strtoupper((string)$action);
        if (strpos($UA,'DELETE')!==false || strpos($UA,'CANCEL')!==false) $severity = 'warning';
        elseif (strpos($UA,'LOGIN')!==false || strpos($UA,'LOGOUT')!==false) $severity = 'notice';
        elseif (strpos($UA,'ERROR')!==false || strpos($UA,'FAIL')!==false)   $severity = 'error';

        // INSERT
        $sql  = "INSERT INTO ".$this->db->prefix()."alt_user_activity";
        $sql .= " (datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity, note) VALUES (";
        $sql .=       $this->db->idate($now).", ";
        $sql .=       (int)$conf->entity.", ";
        $sql .=      "'".$this->db->escape($action)."', ";
        $sql .=       ($element ? "'".$this->db->escape($element)."'" : "NULL").", ";
        $sql .=       ($objid!==null ? (int)$objid : "NULL").", ";
        $sql .=       ($ref ? "'".$this->db->escape($ref)."'" : "NULL").", ";
        $sql .=       (int)$user->id.", ";
        $sql .=      "'".$this->db->escape($user->login)."', ";
        $sql .=       ($ip ? "'".$this->db->escape($ip)."'" : "NULL").", ";
        $sql .=       ($json ? "'".$this->db->escape($json)."'" : "NULL").", ";
        $sql .=      "'".$this->db->escape($severity)."', NULL)";
        if (!$this->db->query($sql)) {
            $this->errors[] = 'Insert error: '.$this->db->lasterror();
            return -1;
        }

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
}
