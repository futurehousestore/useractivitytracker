<?php
/**
 * Module descriptor — User Activity Tracker
 * Path: custom/useractivitytracker/core/modules/modUserActivityTracker.class.php
 * Version: 2.8.1 — Canonical table names, SQL migration path
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
        $this->version      = '2.8.1';
        $this->const_name   = 'MAIN_MODULE_' . strtoupper($this->rights_class);
        $this->special      = 0;
        $this->picto        = 'title.svg@useractivitytracker';

        /* Register only contexts (not method names). Disable triggers to avoid double logging. */
        $this->module_parts = array(
            'triggers' => 0,
            'hooks' => array(
                // Auth
                'login',
                // Global/common pages
                'global','main','admin','toprightmenu',
                // Popular cards where doActions is invoked
                'thirdpartycard','usercard','invoicecard','propalcard','ordercard',
                'productcard','stock','stockproduct','agenda'
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

        // Master enable + soft kill switch from setup (visible in const admin)
        $this->const[] = array('USERACTIVITYTRACKER_MASTER_ENABLED', 'chaine', '1', 'Master switch to enable the User Activity Tracker module', 1, '');
        $this->const[] = array('USERACTIVITYTRACKER_ENABLE_TRACKING', 'chaine', '1', 'Soft switch to enable/disable logging without disabling module', 1, '');
        $this->const[] = array('USERACTIVITYTRACKER_PAYLOAD_MAX_BYTES', 'chaine', '65536', 'Max JSON payload size written into the log table', 1, '');

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
     * Migration logic for v2.8.1 - migrates from legacy alt_user_activity to canonical names
     * Note: The trigger handles actual data migration automatically
     */
    private function runMigration()
    {
        // Migration is now handled by the trigger's migrateLegacy() method
        // which runs automatically on first trigger execution
        // This keeps the module init clean and lightweight
        return;
    }

    /** Disable module */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
