<?php
/**
 * Module descriptor — User Activity Tracker
 * Path: custom/useractivitytracker/core/modules/modUserActivityTracker.class.php
 * Version: 1.0.0
 */
require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modUserActivityTracker extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;
        parent::__construct($db);

        $this->db = $db;
        $this->numero = 990501; // random large id avoiding collisions
        $this->rights_class = 'useractivitytracker';
        $this->family = "technic";
        $this->name = preg_replace('/^mod/i','', get_class($this));
        $this->description = "Track and analyse user activity across Dolibarr";
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->rights_class);
        $this->special = 0;
        $this->picto = 'title.svg@useractivitytracker';

        $this->module_parts = array(
            'triggers' => 1
        );

        // Create these dirs at enable time (relative to htdocs/custom)
        $this->dirs = array('/useractivitytracker/');

        $this->config_page_url = array('useractivitytracker_setup.php@useractivitytracker');
        $this->depends = array(); // core only
        $this->phpmin = array(7, 4); // Changed to PHP 7.4 as per requirement
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
        $r++;

        // Menus
        $this->menu = array();
        
        // Main menu item - top level
        $this->menu[0] = array(
            'fk_menu'  => '',
            'type'     => 'top',
            'titre'    => 'Activity Tracker',
            'mainmenu' => 'useractivitytracker',
            'leftmenu' => '',
            'url'      => '/custom/useractivitytracker/admin/useractivitytracker_dashboard.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 55,
            'enabled'  => '1',
            'perms'    => '$user->rights->useractivitytracker->read',
            'target'   => '',
            'user'     => 2
        );
        
        // Dashboard submenu
        $this->menu[1] = array(
            'fk_menu'  => 'fk_mainmenu=useractivitytracker',
            'type'     => 'left',
            'titre'    => 'Dashboard',
            'mainmenu' => 'useractivitytracker',
            'leftmenu' => 'useractivitytracker_dashboard',
            'url'      => '/custom/useractivitytracker/admin/useractivitytracker_dashboard.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 100,
            'enabled'  => '1',
            'perms'    => '$user->rights->useractivitytracker->read',
            'target'   => '',
            'user'     => 2
        );
        
        // Settings submenu
        $this->menu[2] = array(
            'fk_menu'  => 'fk_mainmenu=useractivitytracker',
            'type'     => 'left',
            'titre'    => 'Settings',
            'mainmenu' => 'useractivitytracker',
            'leftmenu' => 'useractivitytracker_setup',
            'url'      => '/custom/useractivitytracker/admin/useractivitytracker_setup.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 200,
            'enabled'  => '1',
            'perms'    => '$user->rights->useractivitytracker->admin',
            'target'   => '',
            'user'     => 2
        );
        
        // Export submenu
        $this->menu[3] = array(
            'fk_menu'  => 'fk_mainmenu=useractivitytracker',
            'type'     => 'left',
            'titre'    => 'Export Data',
            'mainmenu' => 'useractivitytracker',
            'leftmenu' => 'useractivitytracker_export',
            'url'      => '/custom/useractivitytracker/admin/useractivitytracker_dashboard.php#export',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 300,
            'enabled'  => '1',
            'perms'    => '$user->rights->useractivitytracker->export',
            'target'   => '',
            'user'     => 2
        );
        
        // Analysis submenu
        $this->menu[4] = array(
            'fk_menu'  => 'fk_mainmenu=useractivitytracker',
            'type'     => 'left',
            'titre'    => 'Analysis',
            'mainmenu' => 'useractivitytracker',
            'leftmenu' => 'useractivitytracker_analysis',
            'url'      => '/custom/useractivitytracker/admin/useractivitytracker_analysis.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 400,
            'enabled'  => '1',
            'perms'    => '$user->rights->useractivitytracker->read',
            'target'   => '',
            'user'     => 2
        );
        
        // Keep legacy Tools menu for backward compatibility
        $this->menu[5] = array(
            'fk_menu'  => 'fk_mainmenu=tools,fk_leftmenu=',
            'type'     => 'left',
            'titre'    => 'User Activity',
            'mainmenu' => 'tools',
            'leftmenu' => 'useractivitytracker',
            'url'      => '/custom/useractivitytracker/admin/useractivitytracker_dashboard.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 1000,
            'enabled'  => '1',
            'perms'    => '$user->rights->useractivitytracker->read',
            'target'   => '',
            'user'     => 2
        );
    }

    public function init($options = '')
    {
        // Use the parent method to execute table creation from SQL files
        return $this->_load_tables('/useractivitytracker/sql/', '');
    }

    public function remove($options = '')
    {
        // Keep table by default
        return 1;
    }

    /**
     * Create tables, keys and data required by module
     * Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
     * and create data commands must be stored in directory /useractivitytracker/sql/
     * This function is called by this->init
     *
     * @param   string      $reldir     Relative directory path where to scan files
     * @param   string      $onlywithsuffix     Only with this suffix
     * @return  int                     <=0 if KO, >0 if OK
     */
    protected function _load_tables($reldir, $onlywithsuffix = '')
    {
        // Execute custom SQL directly
        $sql = array();
        $sql[] = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "alt_user_activity (
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
        ) ENGINE=InnoDB;";
        
        $error = 0;
        foreach ($sql as $query) {
            $res = $this->db->query($query);
            if (!$res) {
                $error++;
            }
        }
        
        return $error ? 0 : 1;
    }
}
