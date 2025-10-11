<?php
/**
 * Hooks — User Activity Tracker (login/logout/page/action + time-on-page)
 * Path: custom/useractivitytracker/class/actions_useractivitytracker.class.php
 * Version: 2.8.0 — UAT_MASTER_ENABLED gate, entity scoping, parameterized queries
 */

class ActionsUseractivitytracker
{
    /** @var DoliDB */
    public $db;
    public $error = '';
    public $errors = array();

    public function __construct($db) { $this->db = $db; }

    /* -------- Hook entry points (aliases for version compatibility) ------- */

    // Login
    public function doLogin($parameters, &$object, &$action, $hookmanager)    { return $this->handleLogin($parameters); }
    public function afterLogin($parameters, &$object, &$action, $hookmanager) { return $this->handleLogin($parameters); }

    // Logout
    public function doLogout($parameters, &$object, &$action, $hookmanager)     { return $this->handleLogout($parameters); }
    public function beforeLogout($parameters, &$object, &$action, $hookmanager) { return $this->handleLogout($parameters); }

    // Failed login
    public function failedLogin($parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isTrackingEnabled(false)) return 0;
        $this->logActivity('USER_LOGIN_FAILED', array(
            'attempted_login' => isset($parameters['login']) ? (string)$parameters['login'] : 'unknown',
            'ip'              => $this->getClientIP(),
            'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'failure_reason'  => $parameters['reason'] ?? 'authentication_failed'
        ));
        return 0;
    }

    // Page views (ignore ajax/static/api) + inject time-on-page script
    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isTrackingEnabled(true)) return 0;
        if (!empty($_GET['ajax']) || !empty($_POST['ajax'])) return 0;

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|map)$/i', (string)$uri)) return 0;
        if (strpos($uri, '/api/') !== false) return 0;

        // Log a basic page view
        $this->logActivity('PAGE_VIEW', array(
            'uri'     => $uri,
            'script'  => $_SERVER['SCRIPT_NAME'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET'
        ));

        // Inject a tiny JS to measure time on page and send with navigator.sendBeacon
        if (function_exists('dol_buildpath')) {
            $trackUrl = dol_buildpath('/useractivitytracker/scripts/tracktime.php', 1);
            print '<script>(function(){try{'
                .'window.UAT_TRACK_URL='.json_encode($trackUrl).';'
                .'var uatStart=Date.now();'
                .'var sent=false;'
                .'function send(evt){if(sent)return;sent=true;'
                    .'var dur=Math.max(1,Math.round((Date.now()-uatStart)/1000));'
                    .'var data={'
                        .'event:evt,'
                        .'duration_sec:dur,'
                        .'uri:location.pathname+location.search,'
                        .'title:document.title,'
                        .'ts:(new Date()).toISOString()'
                    .'};'
                    .'try{var blob=new Blob([JSON.stringify(data)],{type:"application/json"});'
                        .'navigator.sendBeacon(window.UAT_TRACK_URL,blob);}catch(e){'
                        .'var xhr=new XMLHttpRequest();xhr.open("POST",window.UAT_TRACK_URL,true);'
                        .'xhr.setRequestHeader("Content-Type","application/json");'
                        .'xhr.send(JSON.stringify(data));}'
                .'}'
                .'window.addEventListener("pagehide",function(){send("pagehide");});'
                .'document.addEventListener("visibilitychange",function(){if(document.visibilityState==="hidden"){send("hidden");}});'
                .'window.addEventListener("beforeunload",function(){send("beforeunload");});'
                .'}catch(e){if(window.console&&console.debug){console.debug("UAT timing error",e);}}})();</script>';
        }

        return 0;
    }

    // Supplemental UI actions
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isTrackingEnabled(true)) return 0;

        $significant = array('validate','confirm','delete','cancel','clone','merge');
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

    /* ============================== Internals ============================= */

    private function handleLogin($parameters)
    {
        if (!$this->isTrackingEnabled(false)) return 0;

        $login = $parameters['login'] ?? ($GLOBALS['user']->login ?? null);
        $this->logActivity('USER_LOGIN', array(
            'login'        => $login,
            'ip'           => $this->getClientIP(),
            'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id'   => session_id(),
            'login_method' => $parameters['authmode'] ?? 'standard'
        ));
        return 0;
    }

    private function handleLogout($parameters)
    {
        if (!$this->isTrackingEnabled(false)) return 0;

        $this->logActivity('USER_LOGOUT', array(
            'login'       => $GLOBALS['user']->login ?? null,
            'ip'          => $this->getClientIP(),
            'session_id'  => session_id(),
            'logout_type' => $parameters['type'] ?? 'manual'
        ));
        return 0;
    }

    private function isTrackingEnabled($requireUser)
    {
        global $conf, $user;

        // MASTER switch (v2.7 - central gate)
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1)) {
            return false;
        }

        if (empty($conf->useractivitytracker->enabled)) return false;
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)) return false;

        if ($requireUser && (empty($user) || empty($user->id))) return false;
        if (function_exists('getDolGlobalString') && !empty($user->id) && getDolGlobalString('USERACTIVITYTRACKER_SKIP_USER_'.(int)$user->id)) return false;

        return true;
    }

    private function logActivity($action, $payload = array())
    {
        global $user, $conf;

        // MASTER switch (v2.7 - central gate, returns early if disabled)
        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_MASTER_ENABLED', 1)) return;

        if (function_exists('getDolGlobalInt') && !getDolGlobalInt('USERACTIVITYTRACKER_ENABLE_TRACKING', 1)) return;
        if (function_exists('getDolGlobalString') && !empty($user->id) && getDolGlobalString('USERACTIVITYTRACKER_SKIP_USER_'.(int)$user->id)) return;

        $now = function_exists('dol_now') ? dol_now() : time();

        $full = array_merge($payload, array(
            'timestamp'      => $now,
            'php_session'    => session_id(),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
        ));

        $json = json_encode($full, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $max  = function_exists('getDolGlobalInt') ? max(1, (int)getDolGlobalInt('USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE', 65536)) : 65536;
        if (strlen((string)$json) > $max) {
            $full['_truncated'] = true;
            unset($full['user_agent']);
            $json = json_encode($full, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen((string)$json) > $max) $json = substr($json, 0, $max - 3).'...';
        }

        $severity = 'info';
        $U = strtoupper((string)$action);
        if (strpos($U,'FAILED')!==false || strpos($U,'ERROR')!==false) $severity='error';
        elseif (strpos($U,'LOGIN')!==false || strpos($U,'LOGOUT')!==false) $severity='notice';

        $this->ensureTable();

        $userid   = !empty($user->id)    ? (int)$user->id    : null;
        $username = !empty($user->login) ? $user->login      : ($payload['login'] ?? null);
        $ip       = $this->getClientIP();

        $sql  = "INSERT INTO ".$this->db->prefix()."alt_user_activity";
        $sql .= " (datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity, note) VALUES (";
        $sql .=       $this->db->idate($now).", ";
        $sql .=       (int)$conf->entity.", ";
        $sql .=      "'".$this->db->escape($action)."', 'hook_event', NULL, NULL, ";
        $sql .=       ($userid!==null ? (int)$userid : "NULL").", ";
        $sql .=       ($username!==null ? "'".$this->db->escape($username)."'" : "NULL").", ";
        $sql .=       ($ip ? "'".$this->db->escape($ip)."'" : "NULL").", ";
        $sql .=       ($json ? "'".$this->db->escape($json)."'" : "NULL").", ";
        $sql .=      "'".$this->db->escape($severity)."', NULL)";
        $this->db->query($sql);
    }

    private function getClientIP()
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))  return preg_split('/\s*,\s*/', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        if (!empty($_SERVER['HTTP_X_REAL_IP']))        return $_SERVER['HTTP_X_REAL_IP'];
        if (!empty($_SERVER['REMOTE_ADDR']))           return $_SERVER['REMOTE_ADDR'];
        return null;
    }

    private function ensureTable()
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

// Case-insensitive aliases (safety)
if (!class_exists('ActionsUserActivityTracker')) {
    class_alias('ActionsUseractivitytracker', 'ActionsUserActivityTracker');
}
if (!class_exists('Actionsuseractivitytracker')) {
    class_alias('ActionsUseractivitytracker', 'Actionsuseractivitytracker');
}
