
<?php
/**
 * DAO for alt_user_activity
 * Path: custom/useractivitytracker/class/useractivity.class.php
 * Version: 2025-09-27.beta-1
 */
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
}
