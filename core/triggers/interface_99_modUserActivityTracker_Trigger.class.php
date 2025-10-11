<?php
/**
 * Universal trigger — User Activity Tracker
 * Path: custom/useractivitytracker/core/triggers/interface_99_modUserActivityTracker_Trigger.class.php
 * Version: 2.8.1 — Canonical table names, auto-migration from legacy tables
 */

if (!class_exists('DolibarrTriggers')) {
    require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
}

require_once DOL_DOCUMENT_ROOT.'/custom/useractivitytracker/class/UserActivityTables.php';

class InterfaceUserActivityTrackerTrigger extends DolibarrTriggers
{
    /** @var DoliDB */
    public $db;

    public $family      = 'user';
    public $description = 'Logs Dolibarr triggers into useractivitytracker_activity';
    public $version     = '2.8.1';
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
            // One-time migration from legacy names
            self::migrateLegacy($this->db);
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
            $table = UserActivityTables::activity($this->db);
            $sql  = "INSERT INTO ".$table;
            $sql .= " (datestamp, fk_user, username, action, element_type, element_id, ref, severity, ip, ua, uri, kpi1, kpi2, note, entity) VALUES (";
            $sql .=       $this->db->idate($now).", ";
            $sql .=       ($userid > 0 ? (int)$userid : "NULL").", ";
            $sql .=      "'".$this->db->escape($user->login)."', ";
            $sql .=      "'".$this->db->escape($action)."', ";
            $sql .=       ($element ? "'".$this->db->escape($element)."'" : "NULL").", ";
            $sql .=       ($objid > 0 ? (int)$objid : "NULL").", ";
            $sql .=       ($ref ? "'".$this->db->escape($ref)."'" : "NULL").", ";
            $sql .=      "'".$this->db->escape($severity)."', ";
            $sql .=       ($ip ? "'".$this->db->escape($ip)."'" : "NULL").", ";
            $sql .=       ($json ? "'".$this->db->escape(substr($json, 0, 255))."'" : "NULL").", ";
            $sql .=       "'".$this->db->escape($_SERVER['REQUEST_URI'] ?? '')."', ";
            $sql .=       "NULL, NULL, NULL, ";
            $sql .=       (int)$conf->entity.")";
            
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
     * One-time migration from legacy table names
     * Automatically migrates data from alt_user_activity to useractivitytracker_activity
     * Only copies intersecting columns to prevent errors from schema differences
     * 
     * @param DoliDB $db
     */
    private static function migrateLegacy($db)
    {
        static $migrated = false;
        if ($migrated) return; // Run only once per request
        $migrated = true;

        $new = UserActivityTables::activity($db);
        $old = $db->prefix().'alt_user_activity';
        
        // Create new table if missing
        self::ensureActivityTable($db, $new);
        
        // Check if legacy table exists
        $res = $db->DDLDescTable($old);
        if ($res <= 0) return; // Old table doesn't exist, nothing to migrate
        
        // Build column intersection to safely migrate
        $colsOld = array_map('strtolower', array_keys($db->database_specific_columns ?? array()));
        $db->DDLDescTable($new);
        $colsNew = array_map('strtolower', array_keys($db->database_specific_columns ?? array()));
        $cols = array_values(array_intersect($colsOld, $colsNew));
        
        if (empty($cols)) return; // No common columns
        
        // Map legacy column names to new canonical names
        $columnMap = array(
            'object_id' => 'element_id',
            'userid' => 'fk_user',
            'payload' => 'ua'
        );
        
        // Build SELECT and INSERT column lists
        $selectCols = array();
        $insertCols = array();
        foreach ($cols as $col) {
            if ($col === 'rowid' || $col === 'tms') continue; // Skip auto-generated fields
            
            $selectCols[] = '`'.$col.'`';
            // Use mapped column name if exists, otherwise use same name
            $insertCols[] = '`'.(isset($columnMap[$col]) ? $columnMap[$col] : $col).'`';
        }
        
        if (empty($selectCols)) return;
        
        $selectList = implode(',', $selectCols);
        $insertList = implode(',', $insertCols);
        
        // Migrate data with INSERT IGNORE to avoid conflicts
        $sql = "INSERT IGNORE INTO $new ($insertList) SELECT $selectList FROM $old";
        @$db->query($sql); // Suppress errors - migration is best-effort
    }

    /**
     * Create activity table if missing (idempotent)
     * 
     * @param DoliDB $db
     * @param string $table Full table name with prefix
     */
    private static function ensureActivityTable($db, $table)
    {
        if ($db->DDLDescTable($table) >= 0) return; // Table exists
        
        $sql = "CREATE TABLE ".$table." (
            rowid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            datestamp DATETIME NOT NULL,
            fk_user INTEGER NULL,
            username VARCHAR(128) NULL,
            action VARCHAR(64) NOT NULL,
            element_type VARCHAR(64) NULL,
            element_id INTEGER NULL,
            ref VARCHAR(255) NULL,
            severity ENUM('info','notice','warning','error') NOT NULL DEFAULT 'info',
            ip VARCHAR(64) NULL,
            ua VARCHAR(255) NULL,
            uri TEXT NULL,
            kpi1 BIGINT NULL,
            kpi2 BIGINT NULL,
            note TEXT NULL,
            entity INTEGER NOT NULL DEFAULT 1,
            extraparams TEXT NULL,
            KEY idx_entity_date (entity, datestamp),
            KEY idx_action (action),
            KEY idx_user (username),
            KEY idx_element (element_type, element_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $db->query($sql);
    }

    /**
     * Legacy table creation for backward compatibility
     * @deprecated Use ensureActivityTable instead
     */
    private function ensureTable()
    {
        // Delegate to new method
        $table = UserActivityTables::activity($this->db);
        self::ensureActivityTable($this->db, $table);
    }
}
