<?php
/**
 * Hooks — User Activity Tracker (login/logout/page/action)
 * Path: custom/useractivitytracker/core/hooks/interface_99_modUserActivityTracker_Hooks.class.php
 * Version: 2.8.1-fix1
 */

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
        // Correct, module-scoped table
        $tNew = $this->db->prefix().'useractivitytracker_log';
        // Old, mistaken table name used in earlier draft
        $tOld = $this->db->prefix().'alt_user_activity';

        // Exists already?
        $resNew = $this->db->query("SHOW TABLES LIKE '".$this->db->escape($tNew)."'");
        if ($resNew && $this->db->num_rows($resNew) > 0) return;

        // If old table exists, migrate by renaming
        $resOld = $this->db->query("SHOW TABLES LIKE '".$this->db->escape($tOld)."'");
        if ($resOld && $this->db->num_rows($resOld) > 0) {
            $this->db->query("RENAME TABLE ".$tOld." TO ".$tNew);
            return;
        }

        // Fresh create
        $sql = "CREATE TABLE ".$tNew." (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->db->query($sql); // ignore if fails on charset (rare)
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

        // Build payload JSON (bounded)
        $full = $payload + array(
            'ts'            => $now,
            'request_uri'   => $_SERVER['REQUEST_URI'] ?? null,
            'request_method'=> $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'session_id'    => session_id()
        );
        $json = json_encode($full, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $max  = function_exists('getDolGlobalInt') ? max(1024, (int)getDolGlobalInt('USERACTIVITYTRACKER_PAYLOAD_MAX_BYTES', 65536)) : 65536;
        if (strlen((string)$json) > $max) $json = substr($json, 0, $max-3).'...';

        $severity = 'info';
        $U = strtoupper($action);
        if (strpos($U,'DELETE')!==false || strpos($U,'CANCEL')!==false) $severity = 'warning';
        elseif (strpos($U,'ERROR')!==false || strpos($U,'FAIL')!==false) $severity = 'error';
        elseif (strpos($U,'LOGIN')!==false || strpos($U,'LOGOUT')!==false) $severity = 'notice';

        $this->ensureTable();

        $t        = $this->db->prefix().'useractivitytracker_log';
        $entity   = (int) ($conf->entity ?? 1);
        $element  = $payload['element'] ?? null;
        $objid    = $payload['object_id'] ?? null;
        $ref      = $payload['ref'] ?? null;
        $userid   = !empty($user->id) ? (int)$user->id : null;
        $username = !empty($user->login) ? $user->login : ($payload['login'] ?? null);
        $ip       = $this->getClientIP();

        $sql  = "INSERT INTO ".$t." (datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity, kpi1, kpi2, note) VALUES (";
        $sql .=       $this->db->idate($now).", ";
        $sql .=       $entity.", ";
        $sql .=      "'".$this->db->escape($action)."', ";
        $sql .=       ($element ? "'".$this->db->escape($element)."'" : "NULL").", ";
        $sql .=       (is_null($objid) ? "NULL" : (int)$objid).", ";
        $sql .=       ($ref ? "'".$this->db->escape($ref)."'" : "NULL").", ";
        $sql .=       (is_null($userid) ? "NULL" : (int)$userid).", ";
        $sql .=       ($username ? "'".$this->db->escape($username)."'" : "NULL").", ";
        $sql .=       ($ip ? "'".$this->db->escape($ip)."'" : "NULL").", ";
        $sql .=       ($json ? "'".$this->db->escape($json)."'" : "NULL").", ";
        $sql .=      "'".$this->db->escape($severity)."', ";
        $sql .=       "NULL, NULL, NULL)";
        $this->db->query($sql);
    }
}
