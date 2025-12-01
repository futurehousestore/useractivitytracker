<?php
/**
 * Universal trigger — User Activity Tracker
 * Path: custom/useractivitytracker/core/triggers/interface_99_modUserActivityTracker_UserActivityTracker.class.php
 * Version: 3.5.0 — Filters + KPIs (elapsed) + smarter debug
 */

if (!class_exists('DolibarrTriggers')) {
    require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
}

// Optional helper for canonical table names
if (!class_exists('UserActivityTables')) {
    $helper = DOL_DOCUMENT_ROOT . '/custom/useractivitytracker/class/UserActivityTables.php';
    if (file_exists($helper)) {
        require_once $helper;
    }
}

/**
 * Trigger class for UserActivityTracker
 */
class InterfaceUserActivityTracker extends DolibarrTriggers
{
    /** @var DoliDB */
    public $db;

    public $family      = 'user';
    public $description = 'Logs Dolibarr business events into llx_useractivitytracker_activity';
    public $version     = '3.5.0';
    public $name        = 'UserActivityTracker';
    public $picto       = 'useractivitytracker@useractivitytracker';

    /** @var string|null Debug log file path */
    private $logFile = null;

    /** @var array Deduplication cache: key => last timestamp */
    private $dedupCache = array();

    /** @var int Deduplication window in seconds */
    private $dedupWindow = 2;

    /** @var bool Track elapsed seconds between events in this session */
    private $trackElapsed = true;

    public function __construct($db)
    {
        $this->db = $db;

        if (defined('DOL_DOCUMENT_ROOT')) {
            $logDir = DOL_DOCUMENT_ROOT . '/custom/useractivitytracker/log';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            if (is_dir($logDir) && is_writable($logDir)) {
                $this->logFile = $logDir . '/uat_trigger.log';
            }
        }

        // Load configuration for dedup and elapsed tracking
        if (function_exists('getDolGlobalInt')) {
            $dedup = getDolGlobalInt('USERACTIVITYTRACKER_DEDUP_WINDOW', $this->dedupWindow);
            if ($dedup < 0) {
                $dedup = 0;
            }
            $this->dedupWindow = (int) $dedup;

            $this->trackElapsed = (bool) getDolGlobalInt('USERACTIVITYTRACKER_TRACK_ELAPSED', 1);
        }

        $this->debug('================= InterfaceUserActivityTracker constructor =================');
        $this->debug('constructor: dedupWindow=' . $this->dedupWindow . ' trackElapsed=' . ($this->trackElapsed ? '1' : '0'));
    }

    public function getName()    { return $this->name; }
    public function getDesc()    { return $this->description; }
    public function getVersion() { return $this->version; }

    /**
     * Debug logger:
     * - Always logs to Apache/PHP error log with [UAT] prefix
     * - Additionally logs to custom/useractivitytracker/log/uat_trigger.log if possible
     */
    private function debug($msg)
    {
        $line = '[UAT] ' . date('Y-m-d H:i:s') . ' ' . $msg;
        error_log($line);

        if (!empty($this->logFile)) {
            @error_log($line . PHP_EOL, 3, $this->logFile);
        }
    }

    private function isTrackingEnabled($user, $conf)
    {
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1)) {
            $this->debug('isTrackingEnabled: MASTER switch OFF');
            return false;
        }

        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)) {
            $this->debug('isTrackingEnabled: ENABLE_TRACKING OFF');
            return false;
        }

        if (function_exists('getDolGlobalString') && !empty($user->id)
            && getDolGlobalString('USERACTIVITYTRACKER_SKIP_USER_' . (int) $user->id)
        ) {
            $this->debug('isTrackingEnabled: user ' . (int) $user->id . ' in skip list');
            return false;
        }

        // Optionally skip CLI / cron events
        if (PHP_SAPI === 'cli' && function_exists('getDolGlobalInt')
            && !getDolGlobalInt('USERACTIVITYTRACKER_TRACK_CLI', 1)
        ) {
            $this->debug('isTrackingEnabled: PHP_SAPI=cli and TRACK_CLI=0 → skip');
            return false;
        }

        $this->debug('isTrackingEnabled: OK');
        return true;
    }

    private function getActivityTable()
    {
        if (class_exists('UserActivityTables') && method_exists('UserActivityTables', 'activity')) {
            return UserActivityTables::activity($this->db);
        }
        return $this->db->prefix() . 'useractivitytracker_activity';
    }

    private static function ensureActivityTable($db, $table)
    {
        if ($db->DDLDescTable($table) >= 0) {
            return;
        }

        $sql = "CREATE TABLE " . $table . " (
            rowid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            datestamp DATETIME NOT NULL,
            datelog DATETIME NULL,
            fk_user INTEGER NULL,
            userid INTEGER NULL,
            username VARCHAR(128) NULL,
            login VARCHAR(128) NULL,
            action VARCHAR(64) NOT NULL,
            event_code VARCHAR(64) NULL,
            element_type VARCHAR(64) NULL,
            object_type VARCHAR(64) NULL,
            element_id INTEGER NULL,
            object_id BIGINT NULL,
            ref VARCHAR(255) NULL,
            severity ENUM('info','notice','warning','error') NOT NULL DEFAULT 'info',
            ip VARCHAR(64) NULL,
            ua VARCHAR(255) NULL,
            user_agent TEXT NULL,
            uri TEXT NULL,
            url TEXT NULL,
            kpi1 BIGINT NULL,
            kpi2 BIGINT NULL,
            note TEXT NULL,
            entity INTEGER NOT NULL DEFAULT 1,
            extraparams TEXT NULL,
            payload TEXT NULL,
            KEY idx_entity_date (entity, datestamp),
            KEY idx_action (action),
            KEY idx_user (username),
            KEY idx_element (element_type, element_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $db->query($sql);
    }

    /**
     * Check if value matches any pattern in list (supports * wildcard).
     *
     * @param string|null $value
     * @param string|array|null $list Comma/semicolon/newline-separated string or array
     * @return bool
     */
    private function matchesAnyPatternList($value, $list)
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (empty($list)) {
            return false;
        }

        if (!is_array($list)) {
            $list = preg_split('/[,\n;]+/', (string) $list);
        }

        $valueUpper = strtoupper((string) $value);

        foreach ($list as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }
            $patternUpper = strtoupper($pattern);

            if (function_exists('fnmatch')) {
                if (fnmatch($patternUpper, $valueUpper)) {
                    return true;
                }
            } else {
                // Fallback: convert * to .*
                $regex = '/^' . str_replace('\*', '.*', preg_quote($patternUpper, '/')) . '$/i';
                if (preg_match($regex, $valueUpper)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Apply action/element whitelists/blacklists.
     *
     * @param string $action
     * @param string|null $element
     * @return bool true if we should track, false if we should skip
     */
    private function shouldTrackEvent($action, $element)
    {
        if (!function_exists('getDolGlobalString')) {
            // No fine-grained filters available → track everything (other checks already applied)
            return true;
        }

        $element = $element ?: '';

        $actionWhitelist  = getDolGlobalString('USERACTIVITYTRACKER_ACTION_WHITELIST', '');
        $actionBlacklist  = getDolGlobalString('USERACTIVITYTRACKER_ACTION_BLACKLIST', '');
        $elementWhitelist = getDolGlobalString('USERACTIVITYTRACKER_ELEMENT_WHITELIST', '');
        $elementBlacklist = getDolGlobalString('USERACTIVITYTRACKER_ELEMENT_BLACKLIST', '');

        // ACTION whitelist
        if (!empty($actionWhitelist) && !$this->matchesAnyPatternList($action, $actionWhitelist)) {
            $this->debug('shouldTrackEvent: action "' . $action . '" NOT in whitelist → skip');
            return false;
        }

        // ACTION blacklist
        if (!empty($actionBlacklist) && $this->matchesAnyPatternList($action, $actionBlacklist)) {
            $this->debug('shouldTrackEvent: action "' . $action . '" in blacklist → skip');
            return false;
        }

        // ELEMENT whitelist
        if (!empty($elementWhitelist) && !$this->matchesAnyPatternList($element, $elementWhitelist)) {
            $this->debug('shouldTrackEvent: element "' . $element . '" NOT in whitelist → skip');
            return false;
        }

        // ELEMENT blacklist
        if (!empty($elementBlacklist) && $this->matchesAnyPatternList($element, $elementBlacklist)) {
            $this->debug('shouldTrackEvent: element "' . $element . '" in blacklist → skip');
            return false;
        }

        return true;
    }

    /**
     * Trigger entry point
     */
    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        $this->debug('runTrigger: action=' . $action . ' user=' . (is_object($user) ? $user->id : 'null'));

        if (!$this->isTrackingEnabled($user, $conf)) {
            $this->debug('runTrigger: tracking disabled by configuration');
            return 0;
        }

        $element = (is_object($object) && !empty($object->element)) ? $object->element : null;
        $objid   = (is_object($object) && isset($object->id)) ? (int) $object->id
                   : ((is_object($object) && isset($object->rowid)) ? (int) $object->rowid : 0);
        $userid  = (is_object($user) && isset($user->id)) ? (int) $user->id : 0;
        $now     = function_exists('dol_now') ? dol_now() : time();

        // Whitelist / blacklist filters
        if (!$this->shouldTrackEvent($action, $element)) {
            $this->debug('runTrigger: shouldTrackEvent returned false → event skipped');
            return 0;
        }

        // Dedup
        $dedupKey = $action . '|' . $userid . '|' . $element . '|' . $objid;
        if ($this->dedupWindow > 0) {
            if (isset($this->dedupCache[$dedupKey]) && ($now - $this->dedupCache[$dedupKey]) < $this->dedupWindow) {
                $this->debug('runTrigger: duplicate event skipped for key ' . $dedupKey);
                return 0;
            }
            $this->dedupCache[$dedupKey] = $now;
        }

        // Ensure table exists
        $table = $this->getActivityTable();
        self::ensureActivityTable($this->db, $table);

        // Severity
        $severity = 'info';
        $upper    = strtoupper((string) $action);
        if (strpos($upper, 'DELETE') !== false || strpos($upper, 'CANCEL') !== false) {
            $severity = 'warning';
        } elseif (strpos($upper, 'LOGIN') !== false || strpos($upper, 'LOGOUT') !== false) {
            $severity = 'notice';
        } elseif (strpos($upper, 'ERROR') !== false || strpos($upper, 'FAIL') !== false) {
            $severity = 'error';
        } elseif (strpos($upper, 'VALIDATE') !== false || strpos($upper, 'CONFIRM') !== false) {
            // Promote important confirmation steps to at least notice
            $severity = 'notice';
        }

        // Request info
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? (
            !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
                ? preg_split('/\s*,\s*/', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
                : ($_SERVER['REMOTE_ADDR'] ?? null)
        );

        $ref = (is_object($object) && !empty($object->ref)) ? $object->ref : null;

        $payload = array(
            'class'       => is_object($object) ? get_class($object) : null,
            'element'     => $element,
            'id'          => $objid,
            'ref'         => $ref,
            'action'      => $action,
            'ip'          => $ip,
            'severity'    => $severity,
            'session_id'  => session_id(),
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        );

        $json = json_encode($payload);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $json = null;
        }

        // Payload size limit
        $max = 65536;
        if (function_exists('getDolGlobalInt')) {
            $max = (int) getDolGlobalInt(
                'USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE',
                getDolGlobalInt('USERACTIVITYTRACKER_PAYLOAD_MAX_BYTES', 65536)
            );
            if ($max < 1) {
                $max = 65536;
            }
        }
        if ($json !== null && strlen($json) > $max) {
            $json = substr($json, 0, $max - 3) . '...';
        }

        // In this schema, we store JSON payload into ua (TEXT/VARCHAR depending on your migration)
        $ua  = $json !== null ? $json : null;
        $uri = $_SERVER['REQUEST_URI'] ?? null;

        // === IMPORTANT FIX: datestamp as a quoted string ===
        // Use a plain MySQL-compatible datetime string and escape it.
        $datestr = date('Y-m-d H:i:s', $now);

        // KPIs & note
        $kpi1 = null;
        $kpi2 = null;
        $note = null;

        // Time elapsed since previous event in this PHP session
        if ($this->trackElapsed && function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            $last = isset($_SESSION['UAT_LAST_TS']) ? (int) $_SESSION['UAT_LAST_TS'] : 0;
            if ($last > 0 && $now > $last) {
                $elapsed = (int) ($now - $last);
                // Cap to 24h to avoid crazy values
                if ($elapsed > 86400) {
                    $elapsed = 86400;
                }
                $kpi1 = $elapsed;
            }
            $_SESSION['UAT_LAST_TS'] = $now;
        }

        // Example usage of kpi2: store object id (if any) as a simple numeric KPI
        if ($objid > 0) {
            $kpi2 = $objid;
        }

        // Build human-readable note
        $noteParts = array();
        $noteParts[] = 'user=' . (!empty($user->login) ? $user->login : ('id=' . $userid));
        $noteParts[] = 'action=' . $action;
        if (!empty($element)) {
            $noteParts[] = 'element=' . $element;
        }
        if ($objid > 0) {
            $noteParts[] = 'id=' . $objid;
        }
        if (!empty($ref)) {
            $noteParts[] = 'ref=' . $ref;
        }
        if ($kpi1 !== null) {
            $noteParts[] = 'elapsed=' . $kpi1 . 's';
        }
        $note = implode(' | ', $noteParts);

        $sql  = "INSERT INTO " . $table;
        $sql .= " (datestamp, fk_user, username, action, element_type, element_id, ref,";
        $sql .= " severity, ip, ua, uri, kpi1, kpi2, note, entity) VALUES (";
        $sql .=      "'" . $this->db->escape($datestr) . "', ";
        $sql .=      ($userid > 0 ? (int) $userid : "NULL") . ", ";
        $sql .=      (!empty($user->login) ? "'" . $this->db->escape($user->login) . "'" : "NULL") . ", ";
        $sql .=     "'" . $this->db->escape($action) . "', ";
        $sql .=      (!empty($element) ? "'" . $this->db->escape($element) . "'" : "NULL") . ", ";
        $sql .=      ($objid > 0 ? (int) $objid : "NULL") . ", ";
        $sql .=      (!empty($ref) ? "'" . $this->db->escape($ref) . "'" : "NULL") . ", ";
        $sql .=     "'" . $this->db->escape($severity) . "', ";
        $sql .=      (!empty($ip) ? "'" . $this->db->escape($ip) . "'" : "NULL") . ", ";
        $sql .=      (!empty($ua) ? "'" . $this->db->escape($ua) . "'" : "NULL") . ", ";
        $sql .=      (!empty($uri) ? "'" . $this->db->escape($uri) . "'" : "NULL") . ", ";
        $sql .=      (!is_null($kpi1) ? (int) $kpi1 : "NULL") . ", ";
        $sql .=      (!is_null($kpi2) ? (int) $kpi2 : "NULL") . ", ";
        $sql .=      (!empty($note) ? "'" . $this->db->escape($note) . "'" : "NULL") . ", ";
        $sql .=      (int) ($conf->entity ?? 1);
        $sql .= ")";

        if (!$this->db->query($sql)) {
            $this->debug('runTrigger: insert failed - ' . $this->db->lasterror() . ' — SQL=' . $sql);
            return 0;
        }

        $this->debug('runTrigger: insert OK for action=' . $action . ', element=' . ($element ?: 'NULL') . ', id=' . $objid . ', kpi1=' . (is_null($kpi1) ? 'NULL' : $kpi1));
        return 1;
    }
}
