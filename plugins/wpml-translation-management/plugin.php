<?php
/*
Plugin Name: WPML Translation Management
Plugin URI: https://wpml.org/
Description: Add a complete translation process for WPML | <a href="https://wpml.org">Documentation</a> | <a href="https://wpml.org/version/wpml-3-2/">WPML 3.2 release notes</a>
Author: OnTheGoSystems
Author URI: http://www.onthegosystems.com/
Version: 2.0.5
Plugin Slug: wpml-translation-management
*/

if ( defined( 'WPML_TM_VERSION' ) ) {
	return;
}

define( 'WPML_TM_VERSION', '2.0.5' );

// Do not uncomment the following line!
// If you need to use this constant, use it in the wp-config.php file
//define( 'WPML_TM_DEV_VERSION', '2.0.3-dev' );

define( 'WPML_TM_PATH', dirname( __FILE__ ) );


require WPML_TM_PATH . '/inc/wpml-dependencies-check/wpml-bundle-check.class.php';
require WPML_TM_PATH . '/inc/constants.php';
require WPML_TM_PATH . '/inc/translation-proxy/wpml-pro-translation.class.php';
require WPML_TM_PATH . '/inc/wpml-translation-management.class.php';
require WPML_TM_PATH . '/inc/wpml-translation-management-xliff.class.php';
require WPML_TM_PATH . '/inc/functions-load.php';


global $WPML_Translation_Management;
$WPML_Translation_Management = new WPML_Translation_Management();
