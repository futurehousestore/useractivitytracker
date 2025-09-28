<?php
/**
 * Hooks class for User Activity Tracker - Enhanced Login/Logout Tracking
 * Path: custom/useractivitytracker/core/hooks/interface_99_modUserActivityTracker_Hooks.class.php  
 * Version: 2.5.0 — enable triggers by default, fix user tracking
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';

class ActionsUserActivityTracker
{
    public $db;
    public $error = '';
    public $errors = array();
    
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook for login events - captures successful logins
     */
    public function doLogin($parameters, &$object, &$action, $hookmanager)
    {
        global $user, $conf;
        
        if (empty($conf->useractivitytracker->enabled)) return 0;
        
        // Log successful login
        $this->logActivity('USER_LOGIN', array(
            'login' => $parameters['login'] ?? $user->login,
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id' => session_id(),
            'login_method' => $parameters['authmode'] ?? 'standard'
        ));
        
        return 0;
    }

    /**
     * Hook for logout events  
     */
    public function doLogout($parameters, &$object, &$action, $hookmanager)
    {
        global $user, $conf;
        
        if (empty($conf->useractivitytracker->enabled)) return 0;
        
        // Log logout
        $this->logActivity('USER_LOGOUT', array(
            'login' => $user->login,
            'ip' => $this->getClientIP(),
            'session_id' => session_id(),
            'logout_type' => $parameters['type'] ?? 'manual'
        ));
        
        return 0;
    }

    /**
     * Hook for failed login attempts
     */
    public function failedLogin($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;
        
        if (empty($conf->useractivitytracker->enabled)) return 0;
        
        // Log failed login attempt
        $this->logActivity('USER_LOGIN_FAILED', array(
            'attempted_login' => $parameters['login'] ?? 'unknown',
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'failure_reason' => $parameters['reason'] ?? 'authentication_failed'
        ));
        
        return 0;
    }

    /**
     * Hook to track page visits and navigation
     */
    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        global $user, $conf;
        
        if (empty($conf->useractivitytracker->enabled) || empty($user->id)) return 0;
        
        // Only track significant page loads (not AJAX requests)
        if (!empty($_GET['ajax']) || !empty($_POST['ajax'])) return 0;
        
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Skip common static resources and API calls  
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|woff|woff2|ttf)$/i', $uri)) return 0;
        if (strpos($uri, '/api/') !== false) return 0;
        
        // Log page visit
        $this->logActivity('PAGE_VIEW', array(
            'uri' => $uri,
            'script' => $_SERVER['SCRIPT_NAME'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
        ));
        
        return 0;
    }

    /**
     * Hook for user actions - supplements trigger tracking
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $user, $conf;
        
        if (empty($conf->useractivitytracker->enabled) || empty($user->id)) return 0;
        
        // Track significant actions that might not have triggers
        $significantActions = array('validate', 'confirm', 'delete', 'cancel', 'clone', 'merge');
        
        if (in_array($action, $significantActions)) {
            $this->logActivity('USER_ACTION_' . strtoupper($action), array(
                'action' => $action,
                'context' => get_class($object) ?? 'unknown',
                'object_id' => !empty($object->id) ? $object->id : null,
                'ref' => !empty($object->ref) ? $object->ref : null
            ));
        }
        
        return 0;
    }

    /**
     * Log activity to database
     */
    private function logActivity($action, $payload = array())
    {
        global $user, $conf;
        
        try {
            $now = dol_now();
            
            // Enhanced payload with security context
            $fullPayload = array_merge($payload, array(
                'timestamp' => $now,
                'php_session' => session_id(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'server_name' => $_SERVER['SERVER_NAME'] ?? null
            ));
            
            $json = json_encode($fullPayload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if (strlen($json) > 65535) {
                $fullPayload['_truncated'] = true;
                $json = json_encode(array_slice($fullPayload, 0, 10), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            
            // Determine severity
            $severity = 'info';
            if (strpos($action, 'FAILED') !== false || strpos($action, 'ERROR') !== false) {
                $severity = 'error';
            } elseif (strpos($action, 'LOGIN') !== false || strpos($action, 'LOGOUT') !== false) {
                $severity = 'notice';
            }
            
            $sql = "INSERT INTO " . $this->db->prefix() . "alt_user_activity 
                    (datec, fk_user, action, element_type, fk_object, ref, 
                     ip, payload, entity, session_id, severity) 
                    VALUES ('" . $this->db->idate($now) . "', " . (int)$user->id . ", 
                           '" . $this->db->escape($action) . "', 'hook_event', NULL, NULL, 
                           '" . $this->db->escape($this->getClientIP()) . "', 
                           '" . $this->db->escape($json) . "', " . (int)$conf->entity . ", 
                           '" . $this->db->escape(session_id()) . "', '" . $this->db->escape($severity) . "')";
            
            $result = $this->db->query($sql);
            if (!$result) {
                error_log("User Activity Tracker Hook: Failed to log activity - " . $this->db->lasterror());
            }
            
        } catch (Exception $e) {
            error_log("User Activity Tracker Hook: Exception - " . $e->getMessage());
        }
    }

    /**
     * Get client IP address with proper forwarded header support
     */
    private function getClientIP()
    {
        $ip = null;
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
}