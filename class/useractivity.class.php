<?php
/**
 * DAO for alt_user_activity
 * Path: custom/useractivitytracker/class/useractivity.class.php
 * Version: 2.8.0 — entity scoping, retention cleanup, analytics methods
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class UserActivity extends CommonObject
{
    /** Object identifiers */
    public $element       = 'alt_user_activity';
    public $table_element = 'alt_user_activity';

    /** Multicompany awareness */
    public $ismultientity = 1; // table has an entity column

    /** Fields */
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

    /** @var DoliDB */
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Load one activity by rowid
     * @param int $id
     * @return int 1 found, 0 not found, <0 on error
     */
    public function fetch($id)
    {
        $id = (int) $id;
        if ($id <= 0) return 0;

        $sql = "SELECT rowid, datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity, kpi1, kpi2, note
                FROM ".$this->db->prefix().$this->table_element."
                WHERE rowid = ".$id;

        $res = $this->db->query($sql);
        if (!$res) return -1;

        $obj = $this->db->fetch_object($res);
        $this->db->free($res);

        if ($obj) {
            $this->id          = (int) $obj->rowid;
            $this->datestamp   = $obj->datestamp;
            $this->entity      = (int) $obj->entity;
            $this->action      = $obj->action;
            $this->element_type= $obj->element_type;
            $this->object_id   = isset($obj->object_id) ? (int) $obj->object_id : null;
            $this->ref         = $obj->ref;
            $this->userid      = isset($obj->userid) ? (int) $obj->userid : null;
            $this->username    = $obj->username;
            $this->ip          = $obj->ip;
            $this->payload     = $obj->payload;
            $this->severity    = $obj->severity;
            $this->kpi1        = $obj->kpi1;
            $this->kpi2        = $obj->kpi2;
            $this->note        = $obj->note;
            return 1;
        }
        return 0;
    }

    /**
     * Get activity statistics for a date range
     * @param string $from  YYYY-MM-DD
     * @param string $to    YYYY-MM-DD
     * @param int    $entity
     * @return array
     */
    public function getActivityStats($from, $to, $entity = 1)
    {
        $stats = array(
            'total'        => 0,
            'by_action'    => array(),
            'by_user'      => array(),
            'by_day'       => array(),
            'by_severity'  => array()
        );

        $from = trim((string)$from);
        $to   = trim((string)$to);
        if ($from === '' || $to === '') return $stats;

        $cond  = " WHERE entity = ".((int)$entity);
        $cond .= " AND datestamp BETWEEN '".$this->db->escape($from)." 00:00:00' AND '".$this->db->escape($to)." 23:59:59'";

        // Total
        $sql = "SELECT COUNT(*) AS total FROM ".$this->db->prefix().$this->table_element.$cond;
        if ($res = $this->db->query($sql)) {
            if ($obj = $this->db->fetch_object($res)) $stats['total'] = (int)$obj->total;
            $this->db->free($res);
        }

        // By action
        $sql = "SELECT action, COUNT(*) AS n
                FROM ".$this->db->prefix().$this->table_element.$cond."
                GROUP BY action ORDER BY n DESC LIMIT 20";
        if ($res = $this->db->query($sql)) {
            while ($obj = $this->db->fetch_object($res)) {
                $stats['by_action'][$obj->action] = (int)$obj->n;
            }
            $this->db->free($res);
        }

        // By user
        $sql = "SELECT username, COUNT(*) AS n
                FROM ".$this->db->prefix().$this->table_element.$cond."
                GROUP BY username ORDER BY n DESC LIMIT 20";
        if ($res = $this->db->query($sql)) {
            while ($obj = $this->db->fetch_object($res)) {
                $stats['by_user'][$obj->username] = (int)$obj->n;
            }
            $this->db->free($res);
        }

        // By day
        $sql = "SELECT DATE(datestamp) AS d, COUNT(*) AS n
                FROM ".$this->db->prefix().$this->table_element.$cond."
                GROUP BY DATE(datestamp) ORDER BY d ASC";
        if ($res = $this->db->query($sql)) {
            while ($obj = $this->db->fetch_object($res)) {
                $stats['by_day'][$obj->d] = (int)$obj->n;
            }
            $this->db->free($res);
        }

        // By severity (exclude NULL)
        $sql = "SELECT severity, COUNT(*) AS n
                FROM ".$this->db->prefix().$this->table_element.$cond."
                AND severity IS NOT NULL
                GROUP BY severity ORDER BY n DESC";
        if ($res = $this->db->query($sql)) {
            while ($obj = $this->db->fetch_object($res)) {
                $stats['by_severity'][$obj->severity] = (int)$obj->n;
            }
            $this->db->free($res);
        }

        return $stats;
    }

    /**
     * Get recent session activities for a user (optionally filter by PHP session id)
     * @param int         $userid
     * @param string|null $session_id
     * @return array of stdClass rows
     */
    public function getUserSession($userid, $session_id = null)
    {
        $rows = array();
        $userid = (int)$userid;
        if ($userid <= 0) return $rows;

        $cond = " WHERE userid = ".$userid;
        if (!empty($session_id)) {
            // JSON contains "session_id":"<value>"
            $needle = $this->db->escape('"session_id":"'.(string)$session_id.'"');
            $cond  .= " AND payload LIKE '%".$needle."%'";
        }

        $sql = "SELECT rowid, datestamp, action, element_type, ref, severity
                FROM ".$this->db->prefix().$this->table_element.$cond."
                ORDER BY datestamp DESC
                LIMIT 100";
        if ($res = $this->db->query($sql)) {
            while ($obj = $this->db->fetch_object($res)) {
                $rows[] = $obj;
            }
            $this->db->free($res);
        }

        return $rows;
    }

    /**
     * Purge old activities according to retention policy
     * @param int $retention_days
     * @param int $entity
     * @return int number of deleted rows (>=0) or -1 on error
     */
    public function cleanOldActivities($retention_days, $entity = 1)
    {
        $retention_days = max(0, (int)$retention_days);

        $sql = "DELETE FROM ".$this->db->prefix().$this->table_element."
                WHERE entity = ".(int)$entity."
                AND datestamp < DATE_SUB(NOW(), INTERVAL ".$retention_days." DAY)";

        $res = $this->db->query($sql);
        if (!$res) return -1;

        // affected_rows() signature differs per driver; both forms are handled by Dolibarr
        return (int) $this->db->affected_rows($res);
    }

    /**
     * Simple anomaly heuristics (rate-limited failed logins; burst activity)
     * @param int $entity
     * @return array
     */
    public function detectAnomalies($entity = 1)
    {
        $anomalies = array();

        // Too many failed logins per IP in the last hour
        $sql = "SELECT ip, COUNT(*) AS attempts
                FROM ".$this->db->prefix().$this->table_element."
                WHERE entity = ".(int)$entity."
                  AND action LIKE '%LOGIN%'
                  AND severity = 'error'
                  AND datestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY ip
                HAVING attempts > 5";
        if ($res = $this->db->query($sql)) {
            while ($obj = $this->db->fetch_object($res)) {
                $anomalies[] = array(
                    'type'        => 'suspicious_login',
                    'ip'          => $obj->ip,
                    'attempts'    => (int)$obj->attempts,
                    'description' => "Multiple failed login attempts from IP: ".$obj->ip
                );
            }
            $this->db->free($res);
        }

        // Burst activity per user in last 10 minutes
        $sql = "SELECT userid, username, COUNT(*) AS activities
                FROM ".$this->db->prefix().$this->table_element."
                WHERE entity = ".(int)$entity."
                  AND datestamp >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                GROUP BY userid, username
                HAVING activities > 100";
        if ($res = $this->db->query($sql)) {
            while ($obj = $this->db->fetch_object($res)) {
                $anomalies[] = array(
                    'type'        => 'bulk_activity',
                    'userid'      => (int)$obj->userid,
                    'username'    => $obj->username,
                    'activities'  => (int)$obj->activities,
                    'description' => "High activity volume detected for user: ".$obj->username
                );
            }
            $this->db->free($res);
        }

        return $anomalies;
    }

    /**
     * Lightweight diagnostics (table/columns + recent stats)
     * @param int $entity
     * @return array
     */
    public function getDiagnostics($entity = 1)
    {
        $diag = array(
            'table_exists'          => false,
            'table_columns'         => array(),
            'recent_activity_count' => 0,
            'latest_activity'       => null
        );

        // Table existence
        $sql = "SHOW TABLES LIKE '".$this->db->escape($this->db->prefix().$this->table_element)."'";
        if ($res = $this->db->query($sql)) {
            $diag['table_exists'] = ($this->db->num_rows($res) > 0);
            $this->db->free($res);
        }

        if ($diag['table_exists']) {
            // Structure
            $sql = "DESCRIBE ".$this->db->prefix().$this->table_element;
            if ($res = $this->db->query($sql)) {
                while ($obj = $this->db->fetch_object($res)) {
                    $diag['table_columns'][] = $obj->Field;
                }
                $this->db->free($res);
            }

            // Last 7 days count
            $now_ts   = function_exists('dol_now') ? dol_now() : time();
            $week_ago = function_exists('dol_time_plus_duree') ? dol_time_plus_duree($now_ts, -7, 'd') : ($now_ts - 7*86400);
            $from     = function_exists('dol_print_date') ? dol_print_date($week_ago, '%Y-%m-%d') : date('Y-m-d', $week_ago);
            $to       = function_exists('dol_print_date') ? dol_print_date($now_ts,   '%Y-%m-%d') : date('Y-m-d', $now_ts);

            $sql = "SELECT COUNT(*) AS total
                    FROM ".$this->db->prefix().$this->table_element."
                    WHERE entity = ".(int)$entity."
                      AND datestamp BETWEEN '".$this->db->escape($from)." 00:00:00' AND '".$this->db->escape($to)." 23:59:59'";
            if ($res = $this->db->query($sql)) {
                if ($obj = $this->db->fetch_object($res)) $diag['recent_activity_count'] = (int)$obj->total;
                $this->db->free($res);
            }

            // Latest
            $sql = "SELECT datestamp, action, username
                    FROM ".$this->db->prefix().$this->table_element."
                    WHERE entity = ".(int)$entity."
                    ORDER BY datestamp DESC
                    LIMIT 1";
            if ($res = $this->db->query($sql)) {
                if ($obj = $this->db->fetch_object($res)) {
                    $diag['latest_activity'] = array(
                        'datestamp' => $obj->datestamp,
                        'action'    => $obj->action,
                        'username'  => $obj->username
                    );
                }
                $this->db->free($res);
            }
        }

        return $diag;
    }
}
