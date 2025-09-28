<?php
/**
 * Module descriptor — User Activity Tracker
 * Path: custom/useractivitytracker/core/modules/modUserActivityTracker.class.php
 * Version: 2.2.0
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modUserActivityTracker extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;
        parent::__construct($db);

        $this->db           = $db;
        $this->numero       = 990501; // random large id avoiding collisions
        $this->rights_class = 'useractivitytracker';
        $this->family       = 'technic';
        $this->name         = preg_replace('/^mod/i', '', get_class($this));
        $this->description  = 'Track and analyse user activity across Dolibarr';
        $this->version      = '2.2.0';
        $this->const_name   = 'MAIN_MODULE_' . strtoupper($this->rights_class);
        $this->special      = 0;
        $this->picto        = 'title.svg@useractivitytracker';

        // Parts
        $this->module_parts = array(
            'triggers' => 1,
            'hooks' => array('all'), // Enable comprehensive hooks for login/logout and other activities
        );

        // Create these dirs at enable time (relative to htdocs/custom)
        $this->dirs = array('/useractivitytracker/');

        // Config pages
        $this->config_page_url = array('useractivitytracker_setup.php@useractivitytracker');

        // Compatibility
        $this->depends               = array();
        $this->conflictwith          = array();
        $this->phpmin                = array(7, 4);
        $this->need_dolibarr_version = array(14, 0); // tested up to 22.x
        $this->langfiles             = array('useractivitytracker@useractivitytracker');

        // Constants installed on enable
        $this->const = array(
            array('USERACTIVITYTRACKER_RETENTION_DAYS', 'chaine', '365', 'Retention in days',               1, ''),
            array('USERACTIVITYTRACKER_WEBHOOK_URL',    'chaine', '',    'Webhook URL',                     1, ''),
            array('USERACTIVITYTRACKER_WEBHOOK_SECRET', 'chaine', '',    'Webhook secret (optional)',       1, ''),
            array('USERACTIVITYTRACKER_ENABLE_ANOMALY', 'chaine', '1',   'Enable anomaly heuristics (0/1)', 1, ''),
        );

        // Rights
        $this->rights = array();
        $r = 0;

        $this->rights[$r][0] = 99050101;
        $this->rights[$r][1] = 'Read activity dashboard';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 1; // granted to admin on install
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

        // ---------------------------
        // Menus (do NOT prefix with /custom; Dolibarr resolves it)
        // ---------------------------
        $this->menu = array();

        // Top: Activity Tracker
        $this->menu[] = array(
            'fk_menu'  => 0,                      // <— TOP MENU: must be 0
            'type'     => 'top',
            'titre'    => 'Activity Tracker',
            'mainmenu' => 'useractivitytracker',
            'leftmenu' => '',                     // no left key at top level
            'url'      => '/useractivitytracker/admin/useractivitytracker_dashboard.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 55,
            'enabled'  => '$conf->useractivitytracker->enabled',
            'perms'    => '$user->rights->useractivitytracker->read',
            'target'   => '',
            'user'     => 2
        );

        // Left: Dashboard
        $this->menu[] = array(
            'fk_menu'  => 'fk_mainmenu=useractivitytracker',   // <— LEFT under our top
            'type'     => 'left',
            'titre'    => 'Dashboard',
            'mainmenu' => 'useractivitytracker',
            'leftmenu' => 'useractivitytracker_dashboard',
            'url'      => '/useractivitytracker/admin/useractivitytracker_dashboard.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 100,
            'enabled'  => '$conf->useractivitytracker->enabled',
            'perms'    => '$user->rights->useractivitytracker->read',
            'target'   => '',
            'user'     => 2
        );

        // Left: Settings
        $this->menu[] = array(
            'fk_menu'  => 'fk_mainmenu=useractivitytracker',
            'type'     => 'left',
            'titre'    => 'Settings',
            'mainmenu' => 'useractivitytracker',
            'leftmenu' => 'useractivitytracker_setup',
            'url'      => '/useractivitytracker/admin/useractivitytracker_setup.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 200,
            'enabled'  => '$conf->useractivitytracker->enabled',
            'perms'    => '$user->rights->useractivitytracker->admin',
            'target'   => '',
            'user'     => 2
        );

        // Left: Export
        $this->menu[] = array(
            'fk_menu'  => 'fk_mainmenu=useractivitytracker',
            'type'     => 'left',
            'titre'    => 'Export Data',
            'mainmenu' => 'useractivitytracker',
            'leftmenu' => 'useractivitytracker_export',
            'url'      => '/useractivitytracker/admin/useractivitytracker_dashboard.php#export',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 300,
            'enabled'  => '$conf->useractivitytracker->enabled',
            'perms'    => '$user->rights->useractivitytracker->export',
            'target'   => '',
            'user'     => 2
        );

        // Left: Analysis
        $this->menu[] = array(
            'fk_menu'  => 'fk_mainmenu=useractivitytracker',
            'type'     => 'left',
            'titre'    => 'Analysis',
            'mainmenu' => 'useractivitytracker',
            'leftmenu' => 'useractivitytracker_analysis',
            'url'      => '/useractivitytracker/admin/useractivitytracker_analysis.php',
            'langs'    => 'useractivitytracker@useractivitytracker',
            'position' => 400,
            'enabled'  => '$conf->useractivitytracker->enabled',
            'perms'    => '$user->rights->useractivitytracker->read',
            'target'   => '',
            'user'     => 2
        );

        // Optional: legacy Tools menu
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

    /**
     * Enable module
     */
    public function init($options = '')
    {
        // Load SQL from /useractivitytracker/sql/ if present
        $this->_load_tables('/useractivitytracker/sql/');

        // Register constants, rights, menus, boxes, cron, etc.
        $sql = array(); // extra SQL statements if needed
        return $this->_init($sql, $options);
    }

    /**
     * Disable module
     */
    public function remove($options = '')
    {
        // Keep DB tables by default. Remove framework artifacts.
        $sql = array(); // extra cleanup SQL if needed
        return $this->_remove($sql, $options);
    }
}
