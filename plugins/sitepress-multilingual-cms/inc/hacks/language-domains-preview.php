<?php
//fixes preview function for the case of using separate domains for the language negotion

global $sitepress, $sitepress_settings;
if ( isset( $sitepress ) ) {
	$current_language = $sitepress->get_current_language();
	$default_language = $sitepress->get_default_language();
	if ( isset( $_GET[ 'preview' ] ) && $sitepress_settings[ 'language_negotiation_type' ] == 2 && $current_language != $default_language ) {
		$post_id        = $_GET[ 'p' ];
		$lang           = $current_language;
		$default_home   = get_home_url();
		$_GET[ 'lang' ] = $lang;
		$redir          = $default_home . '?' . http_build_query( $_GET );
		wp_redirect( $redir );
		exit;
	}

	if ( isset( $_GET[ 'preview' ] ) && $sitepress_settings[ 'language_negotiation_type' ] == 2 ) {
		add_filter( 'icl_current_language', 'icl_current_language_preview_hack_filter' );
		function icl_current_language_preview_hack_filter( $lang ) {
			if ( isset( $_GET[ 'lang' ] ) ) {
				$lang = esc_html( $_GET[ 'lang' ] );
			}

			return $lang;
		}
	}
}