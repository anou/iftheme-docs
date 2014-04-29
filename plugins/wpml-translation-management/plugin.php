<?php 
/*
Plugin Name: WPML Translation Management
Plugin URI: https://wpml.org/
Description: Add a complete translation process for WPML. <a href="https://wpml.org">Documentation</a>.
Author: ICanLocalize
Author URI: https://wpml.org
Version: 1.9.4
*/

if(defined('WPML_TM_VERSION')) return;

define('WPML_TM_VERSION', '1.9.4');
define('WPML_TM_PATH', dirname(__FILE__));

require WPML_TM_PATH . '/inc/constants.php';
require WPML_TM_PATH . '/inc/wpml-translation-management.class.php';

$WPML_Translation_Management = new WPML_Translation_Management;