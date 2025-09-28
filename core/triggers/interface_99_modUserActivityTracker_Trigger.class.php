
<?php
/**
 * Universal logger trigger for User Activity Tracker
 * Path: custom/useractivitytracker/core/triggers/interface_99_modUserActivityTracker_Trigger.class.php
 * Version: 2025-09-27.beta-1
 */
class InterfaceUserActivityTrackerTrigger
{
    public $family = 'user';
    public $description = 'Logs Dolibarr triggers into alt_user_activity';
    public $version = '2025-09-27.beta-1';
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
        // Avoid recursion
        if (! empty($object) && is_object($object) && isset($object->element) && $object->element === 'alt_user_activity') return 0;

        $now = dol_now();
        $payload = array(
            'GET' => $_GET ?? null,
            'POST' => $_POST ?? null,
            'class' => is_object($object) ? get_class($object) : null,
            'ref' => (!empty($object->ref) ? $object->ref : null)
        );
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        $element = is_object($object) && !empty($object->element) ? $object->element : null;
        $objid = is_object($object) && !empty($object->id) ? (int)$object->id : null;
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

        $sql = "INSERT INTO ".$this->db->prefix()."alt_user_activity
            (datestamp, entity, action, element_type, object_id, ref, userid, username, ip, payload, severity)
            VALUES (".$this->db->idate($now).", ".((int) $conf->entity).", '".$this->db->escape($action)."', ".
            ($element? "'".$this->db->escape($element)."'" : "NULL").", ".
            ($objid? (int)$objid : "NULL").", ".
            (!empty($object->ref)? "'".$this->db->escape($object->ref)."'" : "NULL").", ".
            ((int)$user->id).", '".$this->db->escape($user->login)."', ".
            ($ip? "'".$this->db->escape($ip)."'" : "NULL").", ".
            ($json? "'".$this->db->escape($json)."'" : "NULL").", ".
            "'info')";

        $res = $this->db->query($sql);
        if (! $res) { return -1; }

        if (!empty($conf->global->USERACTIVITYTRACKER_WEBHOOK_URL)) {
            $this->pushWebhook($conf->global->USERACTIVITYTRACKER_WEBHOOK_URL, array(
                'action'=>$action,
                'element_type'=>$element,
                'object_id'=>$objid,
                'ref'=>!empty($object->ref)?$object->ref:null,
                'userid'=>(int)$user->id,
                'username'=>$user->login,
                'ip'=>$ip,
                'entity'=>(int)$conf->entity,
                'datestamp'=>dol_print_date($now,'standard')
            ), $conf->global->USERACTIVITYTRACKER_WEBHOOK_SECRET ?? '');
        }

        return 1;
    }

    private function pushWebhook($url, array $data, $secret='')
    {
        if (! function_exists('curl_init')) return;
        $ch = curl_init($url);
        $payload = json_encode($data);
        $headers = array('Content-Type: application/json','Content-Length: '.strlen($payload));
        if ($secret) { $headers[] = 'X-Webhook-Secret: '.$secret; }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }
}
