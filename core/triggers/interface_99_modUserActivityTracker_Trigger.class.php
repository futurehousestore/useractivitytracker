<?php
/**
 * Universal trigger — User Activity Tracker
 * Path: custom/useractivitytracker/core/triggers/interface_99_modUserActivityTracker_Trigger.class.php
 * Version: 2.8.0 — Unified config checks, robust error handling, event deduplication
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
    public $version     = '2.8.0';
    public $name        = 'InterfaceUserActivityTrackerTrigger';
    public $picto       = 'useractivitytracker@useractivitytracker';

    /** @var array Deduplication cache (action_userid_objid => last_log_time) */
    private $dedupCache = array();

    /** @var int Deduplication window in seconds */
    private $dedupWindow = 2;

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
        // Use unified configuration check for consistency
        if (!$this->isTrackingEnabled($user, $conf)) {
            return 0;
        }

        // Event deduplication: prevent duplicate logging between trigger and hooks
        // Create unique key from action, userid, and object id
        $element = is_object($object) && !empty($object->element) ? $object->element : null;
        $objid   = is_object($object) && isset($object->id) ? (int)$object->id : ((is_object($object) && isset($object->rowid)) ? (int)$object->rowid : 0);
        $userid  = isset($user->id) ? (int)$user->id : 0;
        $now     = function_exists('dol_now') ? dol_now() : time();
        
        $dedupKey = $action . '_' . $userid . '_' . $objid;
        
        if (isset($this->dedupCache[$dedupKey])) {
            $lastLog = $this->dedupCache[$dedupKey];
            if (($now - $lastLog) < $this->dedupWindow) {
                // Skip: same event logged too recently (likely duplicate from hook)
                return 0;
            }
        }
        
        // Update deduplication cache
        $this->dedupCache[$dedupKey] = $now;

        // Ensure table exists with robust error handling
        try {
            $this->ensureTable();
        } catch (Exception $e) {
            error_log("User Activity Tracker Trigger: table creation failed — " . $e->getMessage());
            return 0; // Graceful degradation: skip logging if table can't be created
        }

        $ref     = (is_object($object) && !empty($object->ref)) ? $object->ref : null;

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

        // INSERT with try/catch for robust error handling
        try {
            $sql  = "INSERT INTO ".$this->db->prefix()."alt_user_activity";
            $sql .= " (datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity, note) VALUES (";
            $sql .=       $this->db->idate($now).", ";
            $sql .=       (int)$conf->entity.", ";
            $sql .=      "'".$this->db->escape($action)."', ";
            $sql .=       ($element ? "'".$this->db->escape($element)."'" : "NULL").", ";
            $sql .=       ($objid > 0 ? (int)$objid : "NULL").", ";
            $sql .=       ($ref ? "'".$this->db->escape($ref)."'" : "NULL").", ";
            $sql .=       ($userid > 0 ? (int)$userid : "NULL").", ";
            $sql .=      "'".$this->db->escape($user->login)."', ";
            $sql .=       ($ip ? "'".$this->db->escape($ip)."'" : "NULL").", ";
            $sql .=       ($json ? "'".$this->db->escape($json)."'" : "NULL").", ";
            $sql .=      "'".$this->db->escape($severity)."', NULL)";
            
            if (!$this->db->query($sql)) {
                $this->errors[] = 'Insert error: '.$this->db->lasterror();
                error_log("User Activity Tracker Trigger: insert failed — ".$this->db->lasterror());
                return 0; // Graceful degradation: log error but continue
            }
        } catch (Exception $e) {
            error_log("User Activity Tracker Trigger: database exception — " . $e->getMessage());
            return 0; // Graceful degradation: continue execution even if logging fails
        }

        return 1;
    }

    /**
     * Unified configuration check — used by trigger
     * Standardized coordination between master switch and tracking toggle
     * v2.8.0: Matches hooks implementation for consistency
     *
     * @param User $user
     * @param Conf $conf
     * @return bool
     */
    private function isTrackingEnabled($user, $conf)
    {
        // MASTER switch (v2.7+ - central gate)
        // When OFF, ALL tracking is disabled regardless of other settings
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1)) {
            return false;
        }
        
        // Module must be enabled
        if (empty($conf->useractivitytracker->enabled)) {
            return false;
        }
        
        // Tracking toggle (can be disabled even if module is enabled)
        // This provides granular control: module can be active but not tracking
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)) {
            return false;
        }
        
        // Per-user skip list (privacy/performance optimization)
        if (function_exists('getDolGlobalString') && !empty($user->id) && getDolGlobalString('USERACTIVITYTRACKER_SKIP_USER_'.(int)$user->id)) {
            return false;
        }

        return true;
    }

    /**
     * Create table if missing (idempotent with robust error handling)
     * v2.8.0: Added try/catch for graceful degradation
     *
     * @throws Exception if table check fails critically
     */
    private function ensureTable()
    {
        try {
            $t = $this->db->prefix().'alt_user_activity';
            
            // Check if table exists
            $res = $this->db->query("SHOW TABLES LIKE '".$this->db->escape($t)."'");
            if (!$res) {
                throw new Exception("Failed to check table existence: " . $this->db->lasterror());
            }
            
            if ($this->db->num_rows($res) > 0) {
                return; // Table exists, nothing to do
            }

            // Create table with proper schema
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
            
            $res = $this->db->query($sql);
            if (!$res) {
                throw new Exception("Failed to create table: " . $this->db->lasterror());
            }
        } catch (Exception $e) {
            // Re-throw for caller to handle
            error_log("User Activity Tracker Trigger: ensureTable error — " . $e->getMessage());
            throw $e;
        }
    }
}
