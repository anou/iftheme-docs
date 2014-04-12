<?php

/**
 * http://wy351s.local/?wysija-page=1&controller=confirm&wysija-key=4d5c04e327932b99d394f0963fa71dc4&action=subscriptions&wysijap=subscriptions&demo=1
 * http://wy351s.local/?wysija-page=1&controller=module&action=init&wysijap=subscriptions&module=a&hook=newsletter
 */
defined('WYSIJA') or die('Restricted access');

class WYSIJA_control_front_module extends WYSIJA_control_front{
    /**
     * 
     * @var string title of the page
     */
    public $subtitle;
    
    /**
     *
     * @var html content of the page
     */
    public $title;
    
    /**
     * Dispatcher of module
     */
    public function init() {
        if (empty($_REQUEST['module']) || empty($_REQUEST['hook'])) {
            wp_redirect( home_url() ); exit;
        }
        $module_name = $_REQUEST['module'];
        $hook = $_REQUEST['hook'];
        $extension = !empty($_REQUEST['extension'])
                ?
                in_array($_REQUEST['extension'], array(WYSIJA, WYSIJANLP)) ? $_REQUEST['extension'] : WYSIJA
                : WYSIJA;
        $hook_params = array_merge($_REQUEST, array('controller_object' => $this));
        $this->subtitle = WYSIJA_module::get_instance_by_name($module_name, $extension)->{'hook_'.$hook}($hook_params);
    }
}
