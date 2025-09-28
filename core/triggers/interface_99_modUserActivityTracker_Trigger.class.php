
<?php
/**
 * Universal logger trigger for User Activity Tracker
 * Path: custom/useractivitytracker/core/triggers/interface_99_modUserActivityTracker_Trigger.class.php
 * Version: 2.4.0 — dynamic main.inc.php resolver, bug fixes
 */
class InterfaceUserActivityTrackerTrigger
{
    public $family = 'user';
    public $description = 'Logs Dolibarr triggers into alt_user_activity';
    public $version = '2.0.0';
    public $name = 'InterfaceUserActivityTrackerTrigger';
    public $picto = 'useractivitytracker@useractivitytracker';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getName() { return $this->name; }
    public function getDesc() { return $this->description; }
    public function getVersion() { return $this->version; }

    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        // Avoid recursion and skip tracking for activity tracker itself
        if (! empty($object) && is_object($object) && isset($object->element) && $object->element === 'alt_user_activity') return 0;
        
        // Skip if user tracking is disabled for this user
        if (getDolGlobalString('USERACTIVITYTRACKER_SKIP_USER_'.$user->id)) return 0;

        try {
            $now = dol_now();
            
            // Enhanced payload with more context
            $payload = array(
                'GET' => $_GET ?? null,
                'POST' => array_filter($_POST ?? [], function($k) { 
                    // Filter out sensitive data
                    return !in_array(strtolower($k), ['password', 'token', 'newpassword', 'oldpassword']);
                }, ARRAY_FILTER_USE_KEY),
                'class' => is_object($object) ? get_class($object) : null,
                'ref' => (!empty($object->ref) ? $object->ref : null),
                'session_id' => session_id(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'http_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
            );
            
            // Limit payload size to prevent database issues
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if (strlen($json) > 65535) { // LONGTEXT limit consideration
                $payload['_truncated'] = true;
                unset($payload['GET'], $payload['POST']);
                $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }

            $element = is_object($object) && !empty($object->element) ? $object->element : null;
            $objid = is_object($object) && !empty($object->id) ? (int)$object->id : null;
            
            // Better IP detection
            $ip = null;
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $ip = $_SERVER['HTTP_X_REAL_IP'];
            } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            
            // Determine severity based on action
            $severity = 'info';
            if (strpos($action, 'DELETE') !== false || strpos($action, 'CANCEL') !== false) {
                $severity = 'warning';
            } elseif (strpos($action, 'LOGIN') !== false || strpos($action, 'LOGOUT') !== false) {
                $severity = 'notice';
            } elseif (strpos($action, 'ERROR') !== false || strpos($action, 'FAIL') !== false) {
                $severity = 'error';
            }

            $sql = "INSERT INTO ".$this->db->prefix()."alt_user_activity
                (datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity, note)
                VALUES (
                    ".$this->db->idate($now).", 
                    ".((int) $conf->entity).", 
                    '".$this->db->escape($action)."', 
                    ".($element? "'".$this->db->escape($element)."'" : "NULL").", 
                    ".($objid? (int)$objid : "NULL").", 
                    ".(!empty($object->ref)? "'".$this->db->escape($object->ref)."'" : "NULL").", 
                    ".((int)$user->id).", 
                    '".$this->db->escape($user->login)."', 
                    ".($ip? "'".$this->db->escape($ip)."'" : "NULL").", 
                    ".($json? "'".$this->db->escape($json)."'" : "NULL").", 
                    '".$this->db->escape($severity)."',
                    NULL
                )";

            $res = $this->db->query($sql);
            if (!$res) {
                $error_msg = "User Activity Tracker: Failed to log activity - " . $this->db->lasterror() . 
                           " | Action: $action | User: " . $user->login . " | Entity: " . $conf->entity;
                error_log($error_msg);
                
                // Check if table exists and try to create it if missing
                $table_check = $this->db->query("SHOW TABLES LIKE '".$this->db->prefix()."alt_user_activity'");
                if (!$table_check || $this->db->num_rows($table_check) == 0) {
                    error_log("User Activity Tracker: Table ".$this->db->prefix()."alt_user_activity does not exist - attempting to create");
                    $this->createTableIfMissing();
                    // Retry the insert once
                    $res = $this->db->query($sql);
                    if (!$res) {
                        error_log("User Activity Tracker: Retry failed after table creation - " . $this->db->lasterror());
                    }
                }
                
                if (!$res) return -1;
            }

            // Send webhook if configured
            if (getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_URL')) {
                $this->pushWebhook(getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_URL'), array(
                    'action' => $action,
                    'element_type' => $element,
                    'object_id' => $objid,
                    'ref' => !empty($object->ref) ? $object->ref : null,
                    'userid' => (int)$user->id,
                    'username' => $user->login,
                    'ip' => $ip,
                    'entity' => (int)$conf->entity,
                    'datestamp' => dol_print_date($now,'standard'),
                    'severity' => $severity,
                    'session_id' => session_id()
                ), getDolGlobalString('USERACTIVITYTRACKER_WEBHOOK_SECRET'));
            }

            return 1;
            
        } catch (Exception $e) {
            error_log("User Activity Tracker: Exception in runTrigger - " . $e->getMessage());
            return 0; // Don't fail the main process
        }
    }

    private function pushWebhook($url, array $data, $secret='')
    {
        if (!function_exists('curl_init')) {
            error_log("User Activity Tracker: cURL not available for webhook");
            return false;
        }
        
        $payload = json_encode($data);
        $headers = array(
            'Content-Type: application/json',
            'Content-Length: '.strlen($payload),
            'User-Agent: Dolibarr-UserActivityTracker/2.0.0'
        );
        
        if ($secret) { 
            $signature = hash_hmac('sha256', $payload, $secret);
            $headers[] = 'X-Webhook-Secret: '.$secret;
            $headers[] = 'X-Hub-Signature-256: sha256='.$signature;
        }
        
        // Retry logic: 3 attempts with exponential backoff
        $maxRetries = 3;
        $baseDelay = 1; // seconds
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Consider security implications
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Success criteria
            if ($httpCode >= 200 && $httpCode < 300) {
                return true;
            }
            
            // Log error and retry if not the last attempt
            error_log("User Activity Tracker: Webhook attempt {$attempt} failed - HTTP {$httpCode} - {$curlError}");
            
            if ($attempt < $maxRetries) {
                $delay = $baseDelay * pow(2, $attempt - 1); // exponential backoff
                sleep($delay);
            }
        }
        
        return false;
    }
    
    /**
     * Create the activity table if missing
     * @return bool Success
     */
    private function createTableIfMissing()
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS ".$this->db->prefix()."alt_user_activity (
                rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
                tms TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                datestamp DATETIME NULL,
                entity INTEGER NOT NULL DEFAULT 1,
                action VARCHAR(128) NOT NULL,
                element_type VARCHAR(64) NULL,
                object_id INTEGER NULL,
                ref VARCHAR(128) NULL,
                userid INTEGER NULL,
                username VARCHAR(128) NULL,
                ip VARCHAR(64) NULL,
                payload LONGTEXT NULL,
                severity VARCHAR(16) NULL,
                kpi1 DECIMAL(24,6) NULL,
                kpi2 DECIMAL(24,6) NULL,
                note VARCHAR(255) NULL,
                INDEX idx_action (action),
                INDEX idx_element (element_type, object_id),
                INDEX idx_user (userid),
                INDEX idx_datestamp (datestamp),
                INDEX idx_entity (entity)
            ) ENGINE=InnoDB";
            
            $res = $this->db->query($sql);
            if ($res) {
                error_log("User Activity Tracker: Successfully created missing table");
                return true;
            } else {
                error_log("User Activity Tracker: Failed to create table - " . $this->db->lasterror());
                return false;
            }
        } catch (Exception $e) {
            error_log("User Activity Tracker: Exception creating table - " . $e->getMessage());
            return false;
        }
    }
}
