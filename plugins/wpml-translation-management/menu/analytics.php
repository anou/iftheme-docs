<?php
if ( $sitepress->get_setting( "enable_analytics" ) ) {
	wp_enqueue_style( "css-analytics", WPML_TM_URL . '/res/css/analytics.css' );
	include( 'analytics-iframe.php' );
} else {
	include( 'analytics-enable.php' );
}
