<?php
/**
 * DAO for alt_user_activity
 * Path: custom/useractivitytracker/class/useractivity.class.php
 * Version: 2.5.0 — enable triggers by default, fix user tracking
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class UserActivity extends CommonObject
{
    public $element = 'alt_user_activity';
    public $table_element = 'alt_user_activity';

    public $id;
    public $datestamp;
    public $entity;
    public $action;
    public $element_type;
    public $object_id;
    public $ref;
    public $userid;
    public $username;
    public $ip;
    public $payload;
    public $severity;
    public $kpi1;
    public $kpi2;
    public $note;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function fetch($id)
    {
        $sql = "SELECT rowid, datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity, kpi1, kpi2, note
                FROM ".$this->db->prefix().$this->table_element."
                WHERE rowid=".(int)$id;
        $res = $this->db->query($sql);
        if ($res && ($obj = $this->db->fetch_object($res)))
        {
            $this->id = (int)$obj->rowid;
            foreach ($obj as $k=>$v) $this->$k = $v;
            return 1;
        }
        return 0;
    }
    
    /**
     * Get activity statistics for a date range
     * @param string $from Date from (Y-m-d format)
     * @param string $to Date to (Y-m-d format)  
     * @param int $entity Entity ID
     * @return array Statistics array
     */
    public function getActivityStats($from, $to, $entity = 1)
    {
        $stats = array(
            'total' => 0,
            'by_action' => array(),
            'by_user' => array(),
            'by_day' => array(),
            'by_severity' => array()
        );
        
        $cond = " WHERE entity=".(int)$entity." AND datestamp BETWEEN '".$this->db->escape($from)." 00:00:00' AND '".$this->db->escape($to)." 23:59:59'";
        
        // Total count
        $sql = "SELECT COUNT(*) as total FROM ".$this->db->prefix().$this->table_element.$cond;
        $res = $this->db->query($sql);
        if ($res && ($obj = $this->db->fetch_object($res))) {
            $stats['total'] = (int)$obj->total;
        }
        
        // By action
        $sql = "SELECT action, COUNT(*) as n FROM ".$this->db->prefix().$this->table_element.$cond." GROUP BY action ORDER BY n DESC LIMIT 20";
        $res = $this->db->query($sql);
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $stats['by_action'][$obj->action] = (int)$obj->n;
            }
        }
        
        // By user
        $sql = "SELECT username, COUNT(*) as n FROM ".$this->db->prefix().$this->table_element.$cond." GROUP BY username ORDER BY n DESC LIMIT 20";
        $res = $this->db->query($sql);
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $stats['by_user'][$obj->username] = (int)$obj->n;
            }
        }
        
        // By day
        $sql = "SELECT DATE(datestamp) as d, COUNT(*) as n FROM ".$this->db->prefix().$this->table_element.$cond." GROUP BY DATE(datestamp) ORDER BY d ASC";
        $res = $this->db->query($sql);
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $stats['by_day'][$obj->d] = (int)$obj->n;
            }
        }
        
        // By severity
        $sql = "SELECT severity, COUNT(*) as n FROM ".$this->db->prefix().$this->table_element.$cond." AND severity IS NOT NULL GROUP BY severity ORDER BY n DESC";
        $res = $this->db->query($sql);
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $stats['by_severity'][$obj->severity] = (int)$obj->n;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get user session information
     * @param int $userid User ID
     * @param string $session_id Session ID
     * @return array Session activities
     */
    public function getUserSession($userid, $session_id = null)
    {
        $activities = array();
        $cond = " WHERE userid=".(int)$userid;
        
        if ($session_id) {
            $cond .= " AND payload LIKE '%\"session_id\":\"".addslashes($session_id)."\"%'";
        }
        
        $sql = "SELECT rowid, datestamp, action, element_type, ref, severity 
                FROM ".$this->db->prefix().$this->table_element.$cond."
                ORDER BY datestamp DESC LIMIT 100";
        
        $res = $this->db->query($sql);
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $activities[] = $obj;
            }
        }
        
        return $activities;
    }
    
    /**
     * Clean old activities based on retention policy
     * @param int $retention_days Number of days to keep
     * @param int $entity Entity ID
     * @return int Number of records deleted
     */
    public function cleanOldActivities($retention_days, $entity = 1)
    {
        $sql = "DELETE FROM ".$this->db->prefix().$this->table_element."
                WHERE entity=".(int)$entity." 
                AND datestamp < DATE_SUB(NOW(), INTERVAL ".(int)$retention_days." DAY)";
        
        $res = $this->db->query($sql);
        if ($res) {
            return $this->db->affected_rows($res);
        }
        
        return 0;
    }
    
    /**
     * Detect anomalous activity patterns
     * @param int $entity Entity ID
     * @return array Array of potential anomalies
     */
    public function detectAnomalies($entity = 1)
    {
        $anomalies = array();
        
        // Detect unusual login patterns (too many failed attempts)
        $sql = "SELECT ip, COUNT(*) as attempts 
                FROM ".$this->db->prefix().$this->table_element."
                WHERE entity=".(int)$entity." 
                AND action LIKE '%LOGIN%' 
                AND severity = 'error' 
                AND datestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY ip 
                HAVING attempts > 5";
        
        $res = $this->db->query($sql);
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $anomalies[] = array(
                    'type' => 'suspicious_login',
                    'ip' => $obj->ip,
                    'attempts' => $obj->attempts,
                    'description' => "Multiple failed login attempts from IP: ".$obj->ip
                );
            }
        }
        
        // Detect bulk operations (unusual high activity in short time)
        $sql = "SELECT userid, username, COUNT(*) as activities
                FROM ".$this->db->prefix().$this->table_element."
                WHERE entity=".(int)$entity." 
                AND datestamp >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                GROUP BY userid, username 
                HAVING activities > 100";
        
        $res = $this->db->query($sql);
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $anomalies[] = array(
                    'type' => 'bulk_activity',
                    'userid' => $obj->userid,
                    'username' => $obj->username,
                    'activities' => $obj->activities,
                    'description' => "High activity volume detected for user: ".$obj->username
                );
            }
        }
        
        return $anomalies;
    }
    
    /**
     * Get module diagnostic information
     * @param int $entity Entity ID
     * @return array Diagnostic information
     */
    public function getDiagnostics($entity = 1)
    {
        $diagnostics = array();
        
        // Check table existence
        $sql = "SHOW TABLES LIKE '".$this->db->prefix().$this->table_element."'";
        $res = $this->db->query($sql);
        $diagnostics['table_exists'] = ($res && $this->db->num_rows($res) > 0);
        if ($res) $this->db->free($res);
        
        if ($diagnostics['table_exists']) {
            // Check table structure
            $sql = "DESCRIBE ".$this->db->prefix().$this->table_element;
            $res = $this->db->query($sql);
            $columns = array();
            if ($res) {
                while ($obj = $this->db->fetch_object($res)) {
                    $columns[] = $obj->Field;
                }
                $this->db->free($res);
            }
            $diagnostics['table_columns'] = $columns;
            
            // Get recent activity count (last 7 days)
            $week_ago = dol_print_date(dol_time_plus_duree(dol_now(), -7, 'd'), '%Y-%m-%d');
            $now = dol_print_date(dol_now(), '%Y-%m-%d');
            $sql = "SELECT COUNT(*) as total FROM ".$this->db->prefix().$this->table_element.
                   " WHERE entity=".(int)$entity." AND datestamp BETWEEN '".$this->db->escape($week_ago)." 00:00:00' AND '".$this->db->escape($now)." 23:59:59'";
            $res = $this->db->query($sql);
            $diagnostics['recent_activity_count'] = 0;
            if ($res && ($obj = $this->db->fetch_object($res))) {
                $diagnostics['recent_activity_count'] = (int)$obj->total;
                $this->db->free($res);
            }
            
            // Get latest activity
            $sql = "SELECT datestamp, action, username FROM ".$this->db->prefix().$this->table_element.
                   " WHERE entity=".(int)$entity." ORDER BY datestamp DESC LIMIT 1";
            $res = $this->db->query($sql);
            $diagnostics['latest_activity'] = null;
            if ($res && ($obj = $this->db->fetch_object($res))) {
                $diagnostics['latest_activity'] = array(
                    'datestamp' => $obj->datestamp,
                    'action' => $obj->action,
                    'username' => $obj->username
                );
                $this->db->free($res);
            }
        }
        
        return $diagnostics;
    }
}
