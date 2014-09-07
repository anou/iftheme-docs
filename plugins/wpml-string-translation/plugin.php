<?php
/*
Plugin Name: WPML String Translation
Plugin URI: https://wpml.org/
Description: Adds theme and plugins localization capabilities to WPML. <a href="https://wpml.org">Documentation</a>.
Author: OnTheGoSystems
Author URI: http://www.onthegosystems.com/
Version: 2.0.9.1
*/

if(defined('WPML_ST_VERSION')) return;

define('WPML_ST_VERSION', '2.0.9.1');
define('WPML_ST_PATH', dirname(__FILE__));

require WPML_ST_PATH . '/inc/constants.php';
require WPML_ST_PATH . '/inc/wpml-string-translation.class.php';
require WPML_ST_PATH . '/inc/widget-text.php';

$WPML_String_Translation = new WPML_String_Translation;
