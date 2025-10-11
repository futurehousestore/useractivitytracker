<?php
/**
 * Hooks — User Activity Tracker (login/logout/page/action)
 * Path: custom/useractivitytracker/core/hooks/interface_99_modUserActivityTracker_Hooks.class.php
 * Version: 2.8.1 — Canonical table names via UserActivityTables helper
 */

require_once DOL_DOCUMENT_ROOT.'/custom/useractivitytracker/class/UserActivityTables.php';

class ActionsUserActivityTracker
{
    /** @var DoliDB */
    public $db;

    /** @var string */ public $error = '';
    /** @var array  */ public $errors = array();

    /** Dedup (action+userid) -> ts */
    private $dedupCache = array();
    private $dedupWindow = 2; // seconds

    public function __construct($db) { $this->db = $db; }

    /* ----------------------------- AUTH ------------------------------ */

    // Successful login (some cores call afterLogin, others doLogin)
    public function afterLogin($parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isTrackingEnabled(false)) return 0;
        $this->logActivity('USER_LOGIN', array(
            'login'      => isset($GLOBALS['user']->login) ? $GLOBALS['user']->login : null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ));
        return 0;
    }
    public function doLogin($parameters, &$object, &$action, $hookmanager) { return $this->afterLogin($parameters, $object, $action, $hookmanager); }

    // Logout (some cores call doLogout, others beforeLogout)
    public function doLogout($parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isTrackingEnabled(true)) return 0;
        $this->logActivity('USER_LOGOUT', array(
            'login'      => $GLOBALS['user']->login ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ));
        return 0;
    }
    public function beforeLogout($parameters, &$object, &$action, $hookmanager) { return $this->doLogout($parameters, $object, $action, $hookmanager); }

    /* --------------------------- PAGE VIEWS -------------------------- */

    // Called by llxFooter() contexts (global/main/admin); ignore ajax/static/api
    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isTrackingEnabled(true)) return 0;

        // Skip ajax/static/api calls
        if (!empty($_GET['ajax']) || !empty($_POST['ajax'])) return 0;
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('/\.(css|js|png|jpe?g|gif|ico|svg|woff2?|ttf|map)$/i', (string)$uri)) return 0;
        if (strpos($uri, '/api/') !== false) return 0;

        $this->logActivity('PAGE_VIEW', array(
            'uri'     => $uri,
            'script'  => $_SERVER['SCRIPT_NAME'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'title'   => $_SERVER['HTTP_X_DOL_TITLE'] ?? null
        ));
        return 0;
    }

    /* --------------------------- UI ACTIONS -------------------------- */

    // Many core pages execute doActions in these contexts
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isTrackingEnabled(true)) return 0;
        if (empty($action)) return 0;

        $significant = array('validate','confirm','delete','cancel','clone','merge');
        if (in_array($action, $significant, true)) {
            $this->logActivity('USER_ACTION_'.strtoupper($action), array(
                'action'     => $action,
                'context'    => is_object($object) ? get_class($object) : null,
                'element'    => is_object($object) && !empty($object->element) ? $object->element : null,
                'object_id'  => (is_object($object) && isset($object->id)) ? (int)$object->id : ((is_object($object) && isset($object->rowid)) ? (int)$object->rowid : null),
                'ref'        => (is_object($object) && !empty($object->ref)) ? $object->ref : null,
                'request'    => $_SERVER['REQUEST_METHOD'] ?? 'GET'
            ));
        }
        return 0;
    }

    /* ============================== CORE ============================== */

    private function isTrackingEnabled($requireUser)
    {
        global $conf, $user;

        // Module enabled?
        if (empty($conf->useractivitytracker->enabled)) return false;

        // Global switches (Dolibarr-style)
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1)) return false;
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)) return false;

        // Require logged user for most hooks
        if ($requireUser && (empty($user) || empty($user->id))) return false;

        // Per-user skip (optional)
        if (function_exists('getDolGlobalString') && !empty($user->id) && getDolGlobalString('USERACTIVITYTRACKER_SKIP_USER_'.(int)$user->id)) return false;

        return true;
    }

    private function getClientIP()
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))  return preg_split('/\s*,\s*/', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        if (!empty($_SERVER['HTTP_X_REAL_IP']))        return $_SERVER['HTTP_X_REAL_IP'];
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private function ensureTable()
    {
        // Use canonical table name via helper
        $table = UserActivityTables::log($this->db);
        
        // Check if table exists
        if ($this->db->DDLDescTable($table) >= 0) return;
        
        // Create fresh table
        $sql = "CREATE TABLE ".$table." (
            rowid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            datestamp DATETIME NOT NULL,
            fk_user INTEGER NULL,
            username VARCHAR(128) NULL,
            uri TEXT NULL,
            ip VARCHAR(64) NULL,
            ua VARCHAR(255) NULL,
            entity INTEGER NOT NULL DEFAULT 1,
            KEY idx_entity_date (entity, datestamp),
            KEY idx_user (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        @$this->db->query($sql); // Suppress errors - table creation is best-effort
    }

    private function logActivity($action, $payload = array())
    {
        global $conf, $user;

        // Dedup (avoid double log between multiple hook passes)
        $uid = !empty($user->id) ? (int)$user->id : 0;
        $key = $action.'_'.$uid;
        $now = function_exists('dol_now') ? dol_now() : time();
        if (isset($this->dedupCache[$key]) && ($now - $this->dedupCache[$key]) < $this->dedupWindow) return;
        $this->dedupCache[$key] = $now;

        $this->ensureTable();

        $table    = UserActivityTables::log($this->db);
        $entity   = (int) ($conf->entity ?? 1);
        $userid   = !empty($user->id) ? (int)$user->id : null;
        $username = !empty($user->login) ? $user->login : ($payload['login'] ?? null);
        $uri      = $_SERVER['REQUEST_URI'] ?? null;
        $ip       = $this->getClientIP();
        $ua       = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $sql  = "INSERT INTO ".$table." (datestamp, fk_user, username, uri, ip, ua, entity) VALUES (";
        $sql .=       $this->db->idate($now).", ";
        $sql .=       (is_null($userid) ? "NULL" : (int)$userid).", ";
        $sql .=       ($username ? "'".$this->db->escape($username)."'" : "NULL").", ";
        $sql .=       ($uri ? "'".$this->db->escape($uri)."'" : "NULL").", ";
        $sql .=       ($ip ? "'".$this->db->escape($ip)."'" : "NULL").", ";
        $sql .=       ($ua ? "'".$this->db->escape($ua)."'" : "NULL").", ";
        $sql .=       $entity.")";
        
        $this->db->query($sql);
    }
}
