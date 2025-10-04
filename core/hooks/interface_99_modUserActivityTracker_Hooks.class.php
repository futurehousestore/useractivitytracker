<?php
/**
 * Hooks class — User Activity Tracker (enhanced login/logout/page/action logging)
 * Path: custom/useractivitytracker/core/hooks/interface_99_modUserActivityTracker_Hooks.class.php
 * Version: 2.7.0 — UAT_MASTER_ENABLED gate, entity scoping, parameterized queries
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';

class ActionsUserActivityTracker
{
    /** @var DoliDB */
    public $db;

    /** @var string */
    public $error = '';

    /** @var array */
    public $errors = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    /* ---------------------------------------------------------------------
     * LOGIN / LOGOUT (provide multiple hook names for broader coverage)
     * ------------------------------------------------------------------- */

    /** Dolibarr hook (some versions) */
    public function doLogin($parameters, &$object, &$action, $hookmanager)  { return $this->handleLogin($parameters); }
    /** Alias used by some contexts/themes */
    public function afterLogin($parameters, &$object, &$action, $hookmanager) { return $this->handleLogin($parameters); }

    /** Dolibarr hook (some versions) */
    public function doLogout($parameters, &$object, &$action, $hookmanager) { return $this->handleLogout($parameters); }
    /** Alias used by some contexts/themes */
    public function beforeLogout($parameters, &$object, &$action, $hookmanager) { return $this->handleLogout($parameters); }

    /* ---------------------------------------------------------------------
     * FAILED LOGIN (common name)
     * ------------------------------------------------------------------- */
    public function failedLogin($parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isTrackingEnabled(/*requireUser*/ false)) return 0;

        $this->logActivity('USER_LOGIN_FAILED', array(
            'attempted_login' => isset($parameters['login']) ? (string)$parameters['login'] : 'unknown',
            'ip'              => $this->getClientIP(),
            'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'failure_reason'  => $parameters['reason'] ?? 'authentication_failed'
        ));

        return 0;
    }

    /* ---------------------------------------------------------------------
     * PAGE VIEWS (non-AJAX, non-static)
     * ------------------------------------------------------------------- */
    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isTrackingEnabled(/*requireUser*/ true)) return 0;

        // Skip AJAX/background requests
        if (!empty($_GET['ajax']) || !empty($_POST['ajax'])) return 0;

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        // Skip static assets and API calls
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|map)$/i', (string)$uri)) return 0;
        if (strpos($uri, '/api/') !== false) return 0;

        $this->logActivity('PAGE_VIEW', array(
            'uri'      => $uri,
            'script'   => $_SERVER['SCRIPT_NAME'] ?? null,
            'referer'  => $_SERVER['HTTP_REFERER'] ?? null,
            'method'   => $_SERVER['REQUEST_METHOD'] ?? 'GET'
        ));

        return 0;
    }

    /* ---------------------------------------------------------------------
     * “doActions” — capture some UI actions that may not have triggers
     * ------------------------------------------------------------------- */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isTrackingEnabled(/*requireUser*/ true)) return 0;

        // Track significant UI actions that sometimes bypass triggers
        $significant = array('validate', 'confirm', 'delete', 'cancel', 'clone', 'merge');
        if (!empty($action) && in_array($action, $significant, true)) {
            $this->logActivity('USER_ACTION_' . strtoupper($action), array(
                'action'    => $action,
                'context'   => is_object($object) ? get_class($object) : 'unknown',
                'object_id' => (is_object($object) && isset($object->id)) ? (int)$object->id : ((is_object($object) && isset($object->rowid)) ? (int)$object->rowid : null),
                'ref'       => (is_object($object) && !empty($object->ref)) ? $object->ref : null
            ));
        }

        return 0;
    }

    /* =====================================================================
     * INTERNALS
     * ===================================================================*/

    private function handleLogin($parameters)
    {
        if (!$this->isTrackingEnabled(/*requireUser*/ false)) return 0;

        $this->logActivity('USER_LOGIN', array(
            'login'       => $parameters['login'] ?? ($GLOBALS['user']->login ?? null),
            'ip'          => $this->getClientIP(),
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id'  => session_id(),
            'login_method'=> $parameters['authmode'] ?? 'standard'
        ));

        return 0;
    }

    private function handleLogout($parameters)
    {
        if (!$this->isTrackingEnabled(/*requireUser*/ false)) return 0;

        $this->logActivity('USER_LOGOUT', array(
            'login'       => $GLOBALS['user']->login ?? null,
            'ip'          => $this->getClientIP(),
            'session_id'  => session_id(),
            'logout_type' => $parameters['type'] ?? 'manual'
        ));

        return 0;
    }

    /**
     * Guard: module + global toggle (+ optionally a logged-in user)
     *
     * @param bool $requireUser true to require $user->id
     * @return bool
     */
    private function isTrackingEnabled($requireUser)
    {
        global $conf, $user;

        // MASTER switch (v2.7 - central gate)
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1)) {
            return false;
        }

        if (empty($conf->useractivitytracker->enabled)) return false;

        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)) {
            return false;
        }

        if ($requireUser && (empty($user) || empty($user->id))) {
            return false;
        }

        if (function_exists('getDolGlobalString') && !empty($user->id) && getDolGlobalString('USERACTIVITYTRACKER_SKIP_USER_' . (int)$user->id)) {
            return false;
        }

        return true;
    }

    /**
     * Core logger — writes to llx_alt_user_activity
     *
     * @param string $action
     * @param array  $payload
     * @return void
     */
    private function logActivity($action, $payload = array())
    {
        global $user, $conf;

        // MASTER switch (v2.7 - central gate, returns early if disabled)
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1)) return;
        
        // Double-check toggles (cheap)
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)) return;
        if (function_exists('getDolGlobalString') && !empty($user->id) && getDolGlobalString('USERACTIVITYTRACKER_SKIP_USER_' . (int)$user->id)) return;

        $now = function_exists('dol_now') ? dol_now() : time();

        // Build and cap payload
        $full = array_merge($payload, array(
            'timestamp'      => $now,
            'php_session'    => session_id(),
            'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'server_name'    => $_SERVER['SERVER_NAME'] ?? null
        ));

        $json = json_encode($full, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $max  = function_exists('getDolGlobalInt') ? max(1, (int)getDolGlobalInt('USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE', 65536)) : 65536;

        if (strlen((string)$json) > $max) {
            $full['_truncated'] = true;
            // light compaction: drop larger subtrees first
            unset($full['user_agent'], $full['server_name']);
            $json = json_encode($full, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen((string)$json) > $max) {
                $json = substr($json, 0, $max - 3) . '...';
            }
        }

        // Severity
        $severity = 'info';
        $u = strtoupper((string)$action);
        if (strpos($u, 'FAILED') !== false || strpos($u, 'ERROR') !== false) $severity = 'error';
        elseif (strpos($u, 'LOGIN') !== false || strpos($u, 'LOGOUT') !== false) $severity = 'notice';

        // Prepare columns
        $userid   = !empty($user->id) ? (int)$user->id : null;
        $username = !empty($user->login) ? $user->login : ($payload['login'] ?? null);
        $ip       = $this->getClientIP();

        // Ensure table exists (no-op if present)
        $this->createTableIfMissing();

        // Build INSERT (use idate *without* quotes)
        $sql  = "INSERT INTO ".$this->db->prefix()."alt_user_activity";
        $sql .= " (datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity, note) VALUES (";
        $sql .=       $this->db->idate($now).", ";
        $sql .=       (int)$conf->entity.", ";
        $sql .=      "'".$this->db->escape($action)."', ";
        $sql .=      "'hook_event', ";
        $sql .=       "NULL, ";
        $sql .=       "NULL, ";
        $sql .=       ($userid !== null ? (int)$userid : "NULL").", ";
        $sql .=       ($username !== null ? "'".$this->db->escape($username)."'" : "NULL").", ";
        $sql .=       ($ip ? "'".$this->db->escape($ip)."'" : "NULL").", ";
        $sql .=       ($json ? "'".$this->db->escape($json)."'" : "NULL").", ";
        $sql .=      "'".$this->db->escape($severity)."', ";
        $sql .=       "NULL)";

        $res = $this->db->query($sql);
        if (!$res) {
            error_log("User Activity Tracker Hook: insert failed — ".$this->db->lasterror());
        }
    }

    /**
     * Client IP with common proxy headers
     *
     * @return string|null
     */
    private function getClientIP()
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))  return preg_split('/\s*,\s*/', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        if (!empty($_SERVER['HTTP_X_REAL_IP']))        return $_SERVER['HTTP_X_REAL_IP'];
        if (!empty($_SERVER['REMOTE_ADDR']))           return $_SERVER['REMOTE_ADDR'];
        return null;
    }

    /**
     * Create table if missing (idempotent)
     */
    private function createTableIfMissing()
    {
        $table = $this->db->prefix().'alt_user_activity';
        $chk = $this->db->query("SHOW TABLES LIKE '".$this->db->escape($table)."'");
        if ($chk && $this->db->num_rows($chk) > 0) return;

        $sql = "CREATE TABLE ".$table." (
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
