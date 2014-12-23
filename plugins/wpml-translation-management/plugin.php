<?php 
/*
Plugin Name: WPML Translation Management
Plugin URI: https://wpml.org/
Description: Add a complete translation process for WPML. <a href="https://wpml.org">Documentation</a>.
Author: OnTheGoSystems
Author URI: http://www.onthegosystems.com/
Version: 1.9.9
*/

if(defined('WPML_TM_VERSION')) return;

define('WPML_TM_VERSION', '1.9.9');
define('WPML_TM_PATH', dirname(__FILE__));

require WPML_TM_PATH . '/inc/constants.php';
require WPML_TM_PATH . '/inc/ajax.php';
require WPML_TM_PATH . '/inc/wpml-translation-management.class.php';

$WPML_Translation_Management = new WPML_Translation_Management;