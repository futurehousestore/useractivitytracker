<?php
/**
 * Module descriptor — User Activity Tracker
 * Path: custom/useractivitytracker/core/modules/modUserActivityTracker.class.php
 * Version: 2.5.5 — force module name, explicit hook contexts, triggers on
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modUserActivityTracker extends DolibarrModules
{
    public function __construct($db)
    {
        parent::__construct($db);

        $this->db           = $db;
        $this->numero       = 990501;                       // arbitrary id avoiding collisions
        $this->rights_class = 'useractivitytracker';
        $this->family       = 'technic';

        // IMPORTANT: force exact folder name so HookManager resolves paths on case-sensitive FS
        $this->name         = 'useractivitytracker';

        $this->description  = 'Track and analyse user activity across Dolibarr';
        $this->version      = '2.7.0';
        $this->const_name   = 'MAIN_MODULE_' . strtoupper($this->rights_class);
        $this->special      = 0;
        $this->picto        = 'title.svg@useractivitytracker';

        $this->module_parts = array(
            'triggers' => 1,
            'hooks'    => array(
                // Authentication
                'login',
                // Global/UI
                'global','main','toprightmenu','admin',
                // Common objects/cards where users act
                'usercard','thirdpartycard','societeagenda',
                'invoicecard','propalcard','ordercard',
                'productcard','stockproduct','stock','agenda',
                // Footer / page lifecycle
                'formObjectOptions','printCommonFooter'
            )
        );

        // create on enable (relative to htdocs/custom)
        $this->dirs = array('/useractivitytracker/');

        // setup page
        $this->config_page_url = array('useractivitytracker_setup.php@useractivitytracker');

        // compatibility
        $this->phpmin                = '7.4';
        $this->need_dolibarr_version = '14.0';
        $this->langfiles             = array('useractivitytracker@useractivitytracker');

        $this->depends      = array();
        $this->conflictwith = array();

        // constants on enable
        $this->const = array(
            array('USERACTIVITYTRACKER_MASTER_ENABLED',   'chaine','1',     'Master tracking switch (0/1)',            1,''),
            array('USERACTIVITYTRACKER_RETENTION_DAYS',   'chaine','365',   'Retention in days',                       1,''),
            array('USERACTIVITYTRACKER_PAYLOAD_MAX_BYTES','chaine','65536', 'Max payload size (bytes)',                1,''),
            array('USERACTIVITYTRACKER_CAPTURE_IP',       'chaine','1',     'Capture IP addresses (0/1)',              1,''),
            array('USERACTIVITYTRACKER_CAPTURE_PAYLOAD',  'chaine','full',  'Capture payload: off|truncated|full',     1,''),
            array('USERACTIVITYTRACKER_WEBHOOK_URL',      'chaine','',      'Webhook URL',                             1,''),
            array('USERACTIVITYTRACKER_WEBHOOK_SECRET',   'chaine','',      'Webhook secret (optional)',               1,''),
            array('USERACTIVITYTRACKER_ENABLE_ANOMALY',   'chaine','1',     'Enable anomaly heuristics (0/1)',         1,''),
            array('USERACTIVITYTRACKER_ENABLE_TRACKING',  'chaine','1',     'Enable user tracking by default (0/1)',   1,''),
            array('USERACTIVITYTRACKER_MAX_PAYLOAD_SIZE', 'chaine','65536', 'Max JSON payload size (bytes)',           1,'')
        );

        // rights
        $this->rights = array();
        $r = 0;

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

        // menus (no /custom prefix)
        $this->menu = array();

        // top
        $this->menu[] = array(
            'fk_menu'  => 0,
            'type'     => 'top',
            'titre'    => 'Activity Tracker',
            'mainmenu' => $this->rights_class,
            'leftmenu' => '',
            'url'      => '/useractivitytracker/admin/useractivitytracker_dashboard.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 55,
            'enabled'  => '$conf->'.$this->rights_class.'->enabled',
            'perms'    => '$user->rights->'.$this->rights_class.'->read',
            'target'   => '',
            'user'     => 2
        );

        // left: dashboard
        $this->menu[] = array(
            'fk_menu'  => 'fk_mainmenu='.$this->rights_class,
            'type'     => 'left',
            'titre'    => 'Dashboard',
            'mainmenu' => $this->rights_class,
            'leftmenu' => $this->rights_class.'_dashboard',
            'url'      => '/useractivitytracker/admin/useractivitytracker_dashboard.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 100,
            'enabled'  => '$conf->'.$this->rights_class.'->enabled',
            'perms'    => '$user->rights->'.$this->rights_class.'->read',
            'target'   => '',
            'user'     => 2
        );

        // left: settings
        $this->menu[] = array(
            'fk_menu'  => 'fk_mainmenu='.$this->rights_class,
            'type'     => 'left',
            'titre'    => 'Settings',
            'mainmenu' => $this->rights_class,
            'leftmenu' => $this->rights_class.'_setup',
            'url'      => '/useractivitytracker/admin/useractivitytracker_setup.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 200,
            'enabled'  => '$conf->'.$this->rights_class.'->enabled',
            'perms'    => '$user->rights->'.$this->rights_class.'->admin',
            'target'   => '',
            'user'     => 2
        );

        // left: export
        $this->menu[] = array(
            'fk_menu'  => 'fk_mainmenu='.$this->rights_class,
            'type'     => 'left',
            'titre'    => 'Export Data',
            'mainmenu' => $this->rights_class,
            'leftmenu' => $this->rights_class.'_export',
            'url'      => '/useractivitytracker/admin/useractivitytracker_export.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 300,
            'enabled'  => '$conf->'.$this->rights_class.'->enabled',
            'perms'    => '$user->rights->'.$this->rights_class.'->export',
            'target'   => '',
            'user'     => 2
        );

        // left: analysis
        $this->menu[] = array(
            'fk_menu'  => 'fk_mainmenu='.$this->rights_class,
            'type'     => 'left',
            'titre'    => 'Analysis',
            'mainmenu' => $this->rights_class,
            'leftmenu' => $this->rights_class.'_analysis',
            'url'      => '/useractivitytracker/admin/useractivitytracker_analysis.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 400,
            'enabled'  => '$conf->'.$this->rights_class.'->enabled',
            'perms'    => '$user->rights->'.$this->rights_class.'->read',
            'target'   => '',
            'user'     => 2
        );

        // optional legacy tools
        $this->menu[] = array(
            'fk_menu'  => 'fk_mainmenu=tools',
            'type'     => 'left',
            'titre'    => 'User Activity',
            'mainmenu' => 'tools',
            'leftmenu' => 'useractivitytracker',
            'url'      => '/useractivitytracker/admin/useractivitytracker_dashboard.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 1000,
            'enabled'  => '$conf->useractivitytracker->enabled',
            'perms'    => '$user->rights->useractivitytracker->read',
            'target'   => '',
            'user'     => 2
        );
    }

    /** Enable module */
    public function init($options = '')
    {
        $this->_load_tables('/useractivitytracker/sql/');
        
        // Run migration to add new indexes if upgrading
        $this->runMigration();
        
        $sql = array();
        return $this->_init($sql, $options);
    }
    
    /**
     * Migration logic for v2.7 - adds new indexes idempotently
     */
    private function runMigration()
    {
        $table = $this->db->prefix() . 'alt_user_activity';
        
        // Check if table exists first
        $chk = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");
        if (!$chk || $this->db->num_rows($chk) == 0) {
            // Table doesn't exist yet, will be created by SQL file
            return;
        }
        
        // Add new indexes idempotently (MySQL silently ignores if exists via ALTER IGNORE)
        $indexes = array(
            "ALTER TABLE " . $table . " ADD INDEX IF NOT EXISTS idx_entity_datestamp (entity, datestamp)",
            "ALTER TABLE " . $table . " ADD INDEX IF NOT EXISTS idx_entity_user_datestamp (entity, userid, datestamp)"
        );
        
        foreach ($indexes as $sql) {
            // Use DDL that works on older MySQL too
            $sql_compat = str_replace('ADD INDEX IF NOT EXISTS', 'ADD INDEX', $sql);
            // Try adding, ignore error if already exists
            @$this->db->query($sql_compat);
        }
    }

    /** Disable module */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
