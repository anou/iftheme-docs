<?php

function filter_tm_source_langs( $source_languages ) {
	global $wpdb, $sitepress;
	require_once WPML_ST_PATH . '/inc/filters/wpml-tm-filters.class.php';

	$tm_filter = new WPML_TM_Filters( $wpdb, $sitepress );

	return $tm_filter->filter_tm_source_langs( $source_languages );
}

add_filter( 'wpml_tm_allowed_source_languages', 'filter_tm_source_langs', 10, 1 );