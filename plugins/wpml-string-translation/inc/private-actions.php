<?php

function wpml_st_parse_config( $file ) {
	require_once WPML_ST_PATH . '/inc/admin-texts/wpml-admin-text-import.class.php';
	$config = new WPML_Admin_Text_Configuration( $file );
	$import = new WPML_Admin_Text_Import();
	$import->parse_config( $config->get_config_array() );
}

add_action( 'wpml_parse_config_file', 'wpml_st_parse_config', 10, 1 );