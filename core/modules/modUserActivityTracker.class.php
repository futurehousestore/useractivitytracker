
<?php
/**
 * Module descriptor — User Activity Tracker
 * Path: custom/useractivitytracker/core/modules/modUserActivityTracker.class.php
 * Version: 2025-09-27.beta-1
 */
require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modUserActivityTracker extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;
        $this->numero = 990501; // random large id avoiding collisions
        $this->rights_class = 'useractivitytracker';
        $this->family = "technic";
        $this->name = preg_replace('/^mod/i','', get_class($this));
        $this->description = "Track and analyse user activity across Dolibarr";
        $this->version = '2025-09-27.beta-1';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->rights_class);
        $this->special = 0;
        $this->picto='title.svg@useractivitytracker';
        $this->module_parts = array(
            'triggers' => 1
        );
        $this->dirs = array('/useractivitytracker/');
        $this->config_page_url = array('useractivitytracker_setup.php@useractivitytracker');
        $this->depends = array(); // core only
        $this->phpmin = array(8,1);
        $this->langfiles = array('useractivitytracker@useractivitytracker');
        $this->const = array(
            0 => array('USERACTIVITYTRACKER_RETENTION_DAYS','chaine','365','Retention in days',1,''),
            1 => array('USERACTIVITYTRACKER_WEBHOOK_URL','chaine','','Webhook URL',1,''),
            2 => array('USERACTIVITYTRACKER_WEBHOOK_SECRET','chaine','','Webhook secret (optional)',1,''),
            3 => array('USERACTIVITYTRACKER_ENABLE_ANOMALY','chaine','1','Enable anomaly heuristics',1,''),
        );

        // Rights
        $this->rights = array();
        $r=0;
        $this->rights[$r][0] = 99050101;
        $this->rights[$r][1] = 'Read activity dashboard';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read';
        $r++;
        $this->rights[$r][0] = 99050102;
        $this->rights[$r][1] = 'Export activity';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'export';
        $r++;
        $this->rights[$r][0] = 99050103;
        $this->rights[$r][1] = 'Administer module';
        $this->rights[$r][2] = 'a';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'admin';

        // Menus
        $this->menu = array();
        $r=0;
        $this->menu[$r]=array(
            'fk_menu'=>'fk_mainmenu=tools,fk_leftmenu=',
            'type'=>'left',
            'titre'=>'User Activity',
            'mainmenu'=>'tools',
            'leftmenu'=>'useractivitytracker',
            'url'=>'/custom/useractivitytracker/admin/useractivitytracker_dashboard.php',
            'langs'=>'useractivitytracker@useractivitytracker',
            'position'=>1000,
            'enabled'=>'1',
            'perms'=>'$user->hasRight("useractivitytracker","read")',
            'target'=>'',
            'user'=>2
        );
        $r++;
    }

    public function init($options='')
    {
        $sql = array();
        $prefix = $this->db->prefix();
        $sql[] = "CREATE TABLE IF NOT EXISTS {{prefix}}alt_user_activity (
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
        ) ENGINE=innodb;";
        $sql[0] = str_replace("{{prefix}}", $prefix, $sql[0]);
        $res = $this->_load_tables($sql);
        return $res;
    }

    public function remove($options='')
    {
        // Keep table by default
        return 1;
    }

    private function _load_tables(array $sqls)
    {
        $error=0;
        foreach ($sqls as $sql)
        {
            $res = $this->db->query($sql);
            if (!$res) { $error++; }
        }
        return $error?0:1;
    }
}
