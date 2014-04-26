<?php

add_action( 'init', 'wpml_home_url_init', 0 );

add_filter( 'wp_page_menu_args', 'wpml_home_url_exclude_root_page_from_menus' );
add_filter( 'wp_list_pages_excludes', 'wpml_home_url_exclude_root_page' );
add_filter( 'page_attributes_dropdown_pages_args', 'wpml_home_url_exclude_root_page2' );
add_filter( 'get_pages', 'wpml_home_url_get_pages' );

add_action( 'template_redirect', 'wpml_home_url_redirect_home', 0 );
//add_filter( 'template_include', 'wpml_home_url_template_include' );

function wpml_home_url_init()
{
	global $pagenow, $sitepress, $sitepress_settings;

	if ( $pagenow == 'post.php' || $pagenow == 'post-new.php' ) {

		if ( isset( $_GET[ 'wpml_root_page' ] ) && $_GET[ 'wpml_root_page' ] && !empty( $sitepress_settings[ 'urls' ][ 'root_page' ] ) ) {
			$rp = get_post( $sitepress_settings[ 'urls' ][ 'root_page' ] );
			if ( $rp && $rp->post_status != 'trash' ) {
				wp_redirect( get_edit_post_link( $sitepress_settings[ 'urls' ][ 'root_page' ], 'no-display' ) );
				exit;
			}
		}


		if ( isset( $_GET[ 'wpml_root_page' ] ) && $_GET[ 'wpml_root_page' ] || ( isset( $_GET[ 'post' ] ) && $_GET[ 'post' ] == $sitepress_settings[ 'urls' ][ 'root_page' ] ) ) {
			remove_action( 'admin_head', array( $sitepress, 'post_edit_language_options' ) );
			add_action( 'admin_head', 'wpml_home_url_language_box_setup' );

			// don't filter the permalink
			// but filter permalink of others
			remove_action( 'page_link', array( $sitepress, 'permalink_filter' ), 1, 2 );
			add_action( 'page_link', 'wpml_home_url_permalink_filter', 1, 2 );
		}
	}

	add_action( 'save_post', 'wpml_home_url_save_post_actions', 0, 2 );
}

function wpml_home_url_permalink_filter( $p, $pid )
{
	global $sitepress_settings, $sitepress;

	if ( is_object( $pid ) ) {
		$pid = $pid->ID;
	}

	if ( $sitepress_settings[ 'urls' ][ 'root_page' ] != $pid ) {

		$p = $sitepress->permalink_filter( $p, $pid );

	} else {

		$p = home_url();

	}

	return $p;

}

function wpml_home_url_exclude_root_page_from_menus( $args )
{
	global $sitepress_settings;

	if ( !empty( $args[ 'exclude' ] ) ) {
		$args[ 'exclude' ] .= ',';
	} else {
		$args[ 'exclude' ] = '';
	}
	$args[ 'exclude' ] .= $sitepress_settings[ 'urls' ][ 'root_page' ];


	return $args;

}

function wpml_home_url_exclude_root_page( $excludes )
{
	global $sitepress_settings;

	$excludes[ ] = $sitepress_settings[ 'urls' ][ 'root_page' ];

	return $excludes;

}

function wpml_home_url_exclude_root_page2( $args )
{
	global $sitepress_settings;

	$args[ 'exclude' ][ ] = $sitepress_settings[ 'urls' ][ 'root_page' ];

	return $args;
}

function wpml_home_url_get_pages( $pages )
{
	global $sitepress_settings;

	foreach ( $pages as $k => $page ) {
		if ( $page->ID == $sitepress_settings[ 'urls' ][ 'root_page' ] ) {
			unset( $pages[ $k ] );
			$pages = array_values( $pages );
			break;
		}
	}

	return $pages;
}

function wpml_home_url_language_box_setup()
{
	add_meta_box( 'icl_div', __( 'Language', 'sitepress' ), 'wpml_home_url_language_box', 'page', 'side', 'high' );
}

function wpml_home_url_language_box( $post )
{
	global $sitepress_settings;

	if ( isset( $_GET[ 'wpml_root_page' ] ) || ( !empty( $sitepress_settings[ 'urls' ][ 'root_page' ] ) && $post->ID == $sitepress_settings[ 'urls' ][ 'root_page' ] ) ) {
		_e( "This page does not have a language since it's the site's root page." );
		echo '<input type="hidden" name="_wpml_root_page" value="1" />';

	}
}

function wpml_home_url_save_post_actions( $pidd, $post )
{
	global $sitepress, $wpdb, $iclTranslationManagement;

	if ( isset( $_POST[ '_wpml_root_page' ] ) && $_POST[ '_wpml_root_page' ] ) {

		if ( isset( $_POST[ 'autosave' ] ) || ( isset( $post->post_type ) && $post->post_type == 'revision' ) ) {
			return;
		}


		$iclsettings[ 'urls' ][ 'root_page' ] = $post->ID;
		$sitepress->save_settings( $iclsettings );

		remove_action( 'save_post', array( $sitepress, 'save_post_actions' ), 10, 2 );

		if ( !is_null( $iclTranslationManagement ) ) {
			remove_action( 'save_post', array( $iclTranslationManagement, 'save_post_actions' ), 11, 2 );
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translations WHERE element_type='post_page' AND element_id=%d", $post->ID ) );
	}
}

function wpml_home_url_setup_root_page()
{
	global $sitepress, $sitepress_settings;

	remove_action( 'template_redirect', 'redirect_canonical' );
	add_action( 'parse_query', 'wpml_home_url_parse_query' );

	remove_filter( 'posts_join', array( $sitepress, 'posts_join_filter' ), 10, 2 );
	remove_filter( 'posts_where', array( $sitepress, 'posts_where_filter' ), 10, 2 );

	$rp = get_post( $sitepress_settings[ 'urls' ][ 'root_page' ] );
	if ( $rp && $rp->post_status != 'trash' ) {
		$sitepress->ROOT_URL_PAGE_ID = $sitepress_settings[ 'urls' ][ 'root_page' ];
	}

}

function wpml_home_url_parse_query( $q )
{
	global $sitepress;

	$parts = parse_url( get_site_url() );

	if ( !isset( $parts[ 'path' ] ) ) {
		$parts[ 'path' ] = '';
	}

	// fix for root page when it has any parameters
	$server_request_parts = explode('?', $_SERVER[ 'REQUEST_URI' ]);
	
	$server_request_without_get = $server_request_parts[0];
	
	if ( trim( $parts[ 'path' ], '/' ) != trim( $server_request_without_get, '/' ) && $q->query_vars[ 'page_id' ] == get_option( 'page_on_front' ) ) {
		return $q;
	}

	if ( !empty( $sitepress->ROOT_URL_PAGE_ID ) ) {
		$q->query_vars[ 'page_id' ] = $sitepress->ROOT_URL_PAGE_ID;
		$q->query[ 'page_id' ]      = $sitepress->ROOT_URL_PAGE_ID;
		$q->is_page                 = 1;
		$q->queried_object          = new WP_Post( get_post( $sitepress->ROOT_URL_PAGE_ID ) );
		$q->queried_object_id       = $sitepress->ROOT_URL_PAGE_ID;
	}

	return $q;
}

function wpml_home_url_redirect_home()
{
	global $sitepress_settings;

	$queried_object = get_queried_object();
	$home           = get_site_url();
	$parts          = parse_url( $home );

	if ( !isset( $parts[ 'path' ] ) ) {
		$parts[ 'path' ] = '';
	}

	$request_url = $_SERVER[ 'REQUEST_URI' ];
	if ( $queried_object && isset( $queried_object->ID ) && $queried_object->ID == $sitepress_settings[ 'urls' ][ 'root_page' ] && trim( $parts[ 'path' ], '/' ) != trim( $request_url, '/' ) ) {
		wp_redirect( $home, 301 );
		exit;
	}

}

function wpml_home_url_template_include($template) {
	global $sitepress_settings;
	$id = get_queried_object_id();

	$is_root_page = isset( $sitepress_settings[ 'urls' ][ 'root_page' ] ) && $sitepress_settings[ 'urls' ][ 'root_page' ] == $id;
	if ( $is_root_page ) {
		set_query_var('page', get_query_var('page_id'));
		$template = get_page_template();
	}
    return $template;
}

function wpml_home_url_ls_hide_check()
{
	global $sitepress_settings, $sitepress;

	$hide = $sitepress_settings[ 'language_negotiation_type' ] == 1 && $sitepress_settings[ 'urls' ][ 'directory_for_default_language' ] && $sitepress_settings[ 'urls' ][ 'show_on_root' ] == 'page' && $sitepress_settings[ 'urls' ][ 'hide_language_switchers' ] && isset( $sitepress->ROOT_URL_PAGE_ID ) && $sitepress->ROOT_URL_PAGE_ID == $sitepress_settings[ 'urls' ][ 'root_page' ];

	return $hide;

}
