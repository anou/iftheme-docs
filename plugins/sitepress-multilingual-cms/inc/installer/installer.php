<?php 
define('WP_INSTALLER_VERSION', '1.0');
  
include_once (dirname(__FILE__) . '/includes/installer.class.php');

function WP_Installer() {
    return WP_Installer::instance();
}


WP_Installer();


// Ext function 
function WP_Installer_Show_Products($args = array()){
    
    WP_Installer()->show_products($args);
    
}

 