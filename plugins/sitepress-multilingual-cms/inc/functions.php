<?php
/**
 * SitePress Template functions
 * @package wpml-core
 */

function wpml_site_uses_icl() {
	global $wpdb;

	$icl_job_count = false;

	$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}icl_translation_status'" );

	if ( $table_exists ) {
		$icl_job_count_query = "SELECT COUNT(*)
							FROM {$wpdb->prefix}icl_translation_status
							WHERE translation_service = 'icanlocalize'";
		$icl_job_count       = $wpdb->get_var( $icl_job_count_query );
	}

	return $icl_job_count;
}

/**
 * @param string      $key
 * @param mixed|false $default
 *
 * @return bool|mixed
 * @since      3.1
 * @deprecated 3.2 use 'wpml_setting' filter instead
 */
function icl_get_setting( $key, $default = false ) {
    global $sitepress_settings;
    $sitepress_settings = isset($sitepress_settings) ? $sitepress_settings : get_option('icl_sitepress_settings');

    return isset( $sitepress_settings[ $key ] ) ? $sitepress_settings[ $key ] : $default;
}

/**
 * Get a WPML setting value
 * If the Main SitePress Class cannot be access to the function will read the setting from the database
 * Will return false if the requested key is not set or
 * the default value passed in the function's second parameter
 *
 * @param mixed|false $default     Required. The value to return if the settings key does not exist.
 *                                 (typically it's false, but you may want to use something else)
 * @param string      $key         The settings name key to return the value of
 * @param mixed       $deprecated  Deprecated param.
 *
 * @todo  [WPML 3.3] Remove deprecated argument
 *
 * @return mixed The value of the requested setting, or $default
 * @since 3.2
 * @use \SitePress::api_hooks
 */
function wpml_get_setting_filter( $default, $key, $deprecated = null ) {
    $default = $deprecated !== null  && !$default ? $deprecated : $default;

    return icl_get_setting($key, $default);
}

/**
 * @param string      $key
 * @param string      $sub_key
 * @param mixed|false $default
 *
 * @return bool|mixed
 * @since      3.1
 * @deprecated 3.2 use 'wpml_sub_setting' filter instead
 */
function icl_get_sub_setting( $key, $sub_key, $default = false ) {
	$parent = icl_get_setting( $key, array() );

	return isset( $parent[ $sub_key ] ) ? $parent[ $sub_key ] : $default;
}

/**
 * Get a WPML sub setting value
 * @uses  \wpml_get_setting_filter
 *
 * @param mixed|false $default     Required. The value to return if the settings key does not exist.
 *                                 (typically it's false, but you may want to use something else)
 * @param string      $key         The settings name key the sub key belongs to
 * @param string      $sub_key     The sub key to return the value of
 * @param mixed       $deprecated  Deprecated param
 *
 * @todo  [WPML 3.3] Remove deprecated argument
 *
 * @return mixed The value of the requested setting, or $default
 * @since 3.2
 * @use \SitePress::api_hooks
 */
function wpml_get_sub_setting_filter( $default, $key, $sub_key, $deprecated = null ) {
	$default = $deprecated !== null  && !$default ? $deprecated : $default;

	$parent = wpml_get_setting_filter( $key, array() );

	return isset( $parent[ $sub_key ] ) ? $parent[ $sub_key ] : $default;
}

/**
 * @param string $key
 * @param mixed  $value
 * @param bool   $save_now Must call icl_save_settings() to permanently store the value
 */
function icl_set_setting( $key, $value, $save_now = false ) {
	global $sitepress_settings;

	$sitepress_settings[ $key ] = $value;

	if ( $save_now === true ) {
		//We need to save settings anyway, in this case
		update_option( 'icl_sitepress_settings', $sitepress_settings );
		do_action( 'icl_save_settings', $sitepress_settings );
	}
}

function icl_save_settings() {
	global $sitepress;
	$sitepress->save_settings();
}

function icl_get_settings() {
	global $sitepress;

	return isset( $sitepress ) ? $sitepress->get_settings() : false;
}

/**
 * Add settings link to plugin page.
 *
 * @param $links
 * @param $file
 *
 * @return array
 */
function icl_plugin_action_links( $links, $file ) {
	$this_plugin = basename( ICL_PLUGIN_PATH ) . '/sitepress.php';
	if ( $file == $this_plugin ) {
		$links[ ] = '<a href="admin.php?page=' . basename( ICL_PLUGIN_PATH ) . '/menu/languages.php">' . __( 'Configure', 'sitepress' ) . '</a>';
	}

	return $links;
}

if ( defined( 'ICL_DEBUG_MODE' ) && ICL_DEBUG_MODE ) {
	add_action( 'admin_notices', '_icl_deprecated_icl_debug_mode' );
}

function _icl_deprecated_icl_debug_mode() {
	echo '<div class="updated"><p><strong>ICL_DEBUG_MODE</strong> no longer supported. Please use <strong>WP_DEBUG</strong> instead.</p></div>';
}

if ( ! function_exists( 'icl_js_escape' ) ) {
	function icl_js_escape( $str ) {
		$str = esc_js( $str );
		$str = htmlspecialchars_decode( $str );

		return $str;
	}
}

function icl_nobreak( $str ) {
	return preg_replace( "# #", '&nbsp;', $str );
}

function icl_strip_control_chars( $string ) {
	// strip out control characters (all but LF, NL and TAB)
	$string = preg_replace( '/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $string );

	return $string;
}

function _icl_tax_has_objects_recursive( $id, $term_id = - 1, $rec = 0 ) {
	// based on the case where two categories were one the parent of another
	// eliminating the chance of infinite loops by letting this function calling itself too many times
	// 100 is the default limit in most of teh php configuration
	//
	// this limit this function to work only with categories nested up to 60 levels
	// should enough for most cases
	if ( $rec > 60 ) {
		return false;
	}

	global $wpdb;

	if ( $term_id === - 1 ) {
		$term_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $id ) );
	}

	$children = $wpdb->get_results( $wpdb->prepare( "
        SELECT term_taxonomy_id, term_id, count FROM {$wpdb->term_taxonomy} WHERE parent = %d
    ", $term_id ) );

	$count = 0;
	foreach ( $children as $ch ) {
		$count += $ch->count;
	}

	if ( $count ) {
		return true;
	} else {
		foreach ( $children as $ch ) {
			if ( _icl_tax_has_objects_recursive( $ch->term_taxonomy_id, $ch->term_id, $rec + 1 ) ) {
				return true;
			}
		}
	}

	return false;
}

function icl_get_post_children_recursive( $post, $type = 'page' ) {
	global $wpdb;

	$post = (array) $post;

	$children = $wpdb->get_col( $wpdb->prepare( "SELECT ID
                                               FROM {$wpdb->posts}
                                               WHERE post_type=%s
                                                AND post_parent IN (" . wpml_prepare_in( $post, '%d' ) . ")", $type ) );

	if ( ! empty( $children ) ) {
		$children = array_merge( $children, icl_get_post_children_recursive( $children ) );
	}

	return $children;
}

function icl_get_tax_children_recursive( $id, $taxonomy = 'category' ) {
	global $wpdb;

	$id = (array) $id;

	$children = $wpdb->get_col( $wpdb->prepare( "SELECT term_id
                                               FROM {$wpdb->term_taxonomy} x
                                               WHERE x.taxonomy=%s
                                                AND parent IN (" . wpml_prepare_in( $id, '%d' ) . ")", $taxonomy ) );

	if ( ! empty( $children ) ) {
		$children = array_merge( $children, icl_get_tax_children_recursive( $children ) );
	}

	return $children;
}

function _icl_trash_restore_prompt() {
	if ( isset( $_GET[ 'lang' ] ) ) {
		$post = get_post( intval( $_GET[ 'post' ] ) );
		if ( isset( $post->post_status ) && $post->post_status == 'trash' ) {
			$post_type_object = get_post_type_object( $post->post_type );
			$ret              = '<p>';
			$ret .= sprintf( __( 'This translation is currently in the trash. You need to either <a href="%s">delete it permanently</a> or <a href="%s">restore</a> it in order to continue.' ), get_delete_post_link( $post->ID, '', true ), wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link
			                                                                                                                                                                                                                                                                    . '&amp;action=untrash', $post->ID ) ),
			                                                                                                                                                                                                                                                'untrash-post_'
			                                                                                                                                                                                                                                                . $post->ID ) );
			$ret .= '</p>';
			wp_die( $ret );
		}
	}
}

function icl_pop_info( $message, $icon = 'info', $args = array() ) {
	switch ( $icon ) {
		case 'info':
			$icon = ICL_PLUGIN_URL . '/res/img/info.png';
			break;
		case 'question':
			$icon = ICL_PLUGIN_URL . '/res/img/question1.png';
			break;
	}

	$defaults = array(
		'icon_size' => 16,
		'but_style' => array()
	);
	extract( $defaults );
	extract( $args, EXTR_OVERWRITE );

	/** @var $but_style array */
	/** @var $icon_size string */

	$close_icon = ICL_PLUGIN_URL . '/res/img/ico-close.png';
	?>
	<div class="icl_pop_info_wrap">
		<img class="icl_pop_info_but <?php echo join( ' ', $but_style )?>" src="<?php echo $icon ?>" width="<?php echo $icon_size ?>" height="<?php echo $icon_size ?>" alt="info"/>

		<div class="icl_cyan_box icl_pop_info">
			<img class="icl_pop_info_but_close" align="right" src="<?php echo $close_icon; ?>" width="12" height="12" alt="x"/>
			<?php echo $message; ?>
		</div>
	</div>
<?php
}

/**
 * Build or update duplicated posts from a master post.
 *
 * @param  string $master_post_id The ID of the post to duplicate from. Master post doesn't need to be in the default language.
 *
 * @uses       SitePress
 * @uses       TranslationManagement
 * @since      unknown
 * @deprecated 3.2 use 'wpml_admin_make_duplicates' action instead
 */
function icl_makes_duplicates( $master_post_id ) {
	wpml_admin_make_post_duplicates_action( $master_post_id );
}

/**
 * Build or update duplicated posts from a master post.
 * To be used only for admin backend actions
 * @see   $iclTranslationManagement in \SitePress:: __construct
 *
 * @param  int $master_post_id    The ID of the post to duplicate from.
 *                                The ID can be that of a post, page or custom post
 *                                Master post doesn't need to be in the default language.
 *
 * @uses  SitePress
 * @uses  TranslationManagement
 * @since 3.2
 * @use \SitePress::api_hooks
 */
function wpml_admin_make_post_duplicates_action( $master_post_id ) {
	$post      = get_post( $master_post_id );
	$post_type = $post->post_type;

	if ( $post->post_status == 'auto-draft' || $post->post_type == 'revision' ) {
		return;
	}

	global $sitepress;
	$iclTranslationManagement = wpml_load_core_tm();
	if ( $sitepress->is_translated_post_type( $post_type ) ) {
		$iclTranslationManagement->make_duplicates_all( $master_post_id );
	}
}

/**
 * Build duplicated posts from a master post only in case of the duplicate not being present at the time.
 *
 * @param  string $master_post_id The ID of the post to duplicate from. Master post doesn't need to be in the default language.
 *
 * @uses       SitePress
 * @since      unknown
 * @deprecated 3.2 use 'wpml_make_post_duplicates' action instead
 */
function icl_makes_duplicates_public( $master_post_id ) {
	wpml_make_post_duplicates_action( $master_post_id );
}

/**
 * Build duplicated posts from a master post only in case of the duplicate not being present at the time.
 *
 * @param  int $master_post_id    The ID of the post to duplicate from.
 *                                Master post doesn't need to be in the default language.
 *
 * @uses  SitePress
 * @since 3.2
 * @use \SitePress::api_hooks
 */
function wpml_make_post_duplicates_action( $master_post_id ) {

	global $sitepress;

	$master_post = get_post( $master_post_id );

	if ( $master_post->post_status == 'auto-draft' || $master_post->post_type == 'revision' ) {
		return;
	}

	$active_langs = $sitepress->get_active_languages();

	foreach ( $active_langs as $lang_to => $one ) {

		$trid      = $sitepress->get_element_trid( $master_post->ID, 'post_' . $master_post->post_type );
		$lang_from = $sitepress->get_source_language_by_trid( $trid );

		if ( $lang_from == $lang_to ) {
			continue;
		}

		$post_array[ 'post_author' ]   = $master_post->post_author;
		$post_array[ 'post_date' ]     = $master_post->post_date;
		$post_array[ 'post_date_gmt' ] = $master_post->post_date_gmt;
		$post_array[ 'post_content' ]  = addslashes_gpc( apply_filters( 'icl_duplicate_generic_string', $master_post->post_content, $lang_to, array( 'context' => 'post', 'attribute' => 'content', 'key' => $master_post->ID ) ) );
		$post_array[ 'post_title' ]    = addslashes_gpc( apply_filters( 'icl_duplicate_generic_string', $master_post->post_title, $lang_to, array( 'context' => 'post', 'attribute' => 'title', 'key' => $master_post->ID ) ) );
		$post_array[ 'post_excerpt' ]  = addslashes_gpc( apply_filters( 'icl_duplicate_generic_string', $master_post->post_excerpt, $lang_to, array( 'context' => 'post', 'attribute' => 'excerpt', 'key' => $master_post->ID ) ) );
		$post_array[ 'post_status' ]   = $master_post->post_status;
		//TODO [WPML 3.3.] wp_insert_post() does accept 'post_category': even though is not part of the WP_Post object, it deals with it. But as far as I know $master_post doesn't have this property, when set with get_post(), so probably we need to fix that, shouldn't we?
		$post_array[ 'post_category' ]  = $master_post->post_category;
		$post_array[ 'comment_status' ] = $master_post->comment_status;
		$post_array[ 'ping_status' ]    = $master_post->ping_status;
		$post_array[ 'post_name' ]      = $master_post->post_name;
		$post_array[ 'menu_order' ]     = $master_post->menu_order;
		$post_array[ 'post_type' ]      = $master_post->post_type;
		$post_array[ 'post_mime_type' ] = $master_post->post_mime_type;

		if ( $master_post->post_parent ) {
			$parent                      = icl_object_id( $master_post->post_parent, $master_post->post_type, false, $lang_to );
			$post_array[ 'post_parent' ] = $parent;
		}

		$id = wp_insert_post( $post_array );

		$sitepress->set_element_language_details( $id, 'post_' . $post_array[ 'post_type' ], $trid, $lang_to, $lang_from, false );
	}
}

/**
 * Wrapper function for deprecated like_escape() and recommended wpdb::esc_like()
 * @global wpdb  $wpdb
 *
 * @param string $text
 *
 * @return string
 */
function wpml_like_escape( $text ) {
	global $wpdb;

	if ( method_exists( $wpdb, 'esc_like' ) ) {
		return $wpdb->esc_like( $text );
	}

	/** @noinspection PhpDeprecationInspection */

	return like_escape( $text );
}

function icl_do_not_promote() {
	return defined( 'ICL_DONT_PROMOTE' ) && ICL_DONT_PROMOTE;
}

/**
 * @param $time
 *
 * @return string
 */
function icl_convert_to_user_time( $time ) {

	//offset between server time and user time in seconds
	$time_offset = get_option( 'gmt_offset' ) * 3600;
	$local_time  = __( 'Last Update Time could not be determined', 'sitepress' );

	try {
		//unix time stamp in server time
		$creation_time = strtotime( $time );
		//creating dates before 2014 are impossible
		if ( $creation_time !== false ) {
			$local_time = date( 'Y-m-d H:i:s', $creation_time + $time_offset );
		}
	} catch ( Exception $e ) {
		//Ignoring the exception, as we already set the default value in $local_time
	}

	return $local_time;
}

/**
 * Check if given language is activated
 * @global sitepress $sitepress
 *
 * @param string     $language 2 letters language code
 *
 * @return boolean
 * @since      unknown
 * @deprecated 3.2 use 'wpml_language_is_active' filter instead
 */
function icl_is_language_active( $language ) {
	global $sitepress;

	$active_languages = $sitepress->get_active_languages();

	return isset( $active_languages[ $language ] );
}

/**
 * Checks if given language is enabled
 * @global sitepress $sitepress
 *
 * @param mixed      $empty_value   This is normally the value the filter will be modifying.
 *                                  We are not filtering anything here therefore the NULL value
 *                                  This for the filter function to actually receive the full argument list:
 *                                  apply_filters('wpml_language_is_active', '', $language_code);
 * @param string     $language_code The language code to check Accepts a 2-letter language code
 *
 * @return boolean
 * @since 3.2
 * @use \SitePress::api_hooks
 */
function wpml_language_is_active_filter( $empty_value, $language_code ) {
	global $sitepress;

	return $sitepress->is_active_language( $language_code );
}

/**
 * @param string $url url either with or without schema
 *                    Removes the subdirectory in which WordPress is installed from a url.
 *                    If WordPress is not installed in a subdirectory, then the input is returned unaltered.
 *
 * @return string the url input without the blog's subdirectory. Potentially existing schemata on the input are kept intact.
 */
function wpml_strip_subdir_from_url( $url ) {
	/** @var WPML_URL_Converter $wpml_url_converter */
	global $wpml_url_converter;

	$subdir       = parse_url( $wpml_url_converter->get_abs_home(), PHP_URL_PATH );
	$subdir_slugs = array_values( array_filter( explode( '/', $subdir ) ) );

	$url_path_expl = explode( '/', preg_replace( '#^(http|https)://#', '', $url ) );
	array_shift( $url_path_expl );
	$url_slugs        = array_values( array_filter( $url_path_expl ) );
	$url_slugs_before = $url_slugs;
	$url_slugs        = array_diff_assoc( $url_slugs, $subdir_slugs );
	$url              = str_replace( '/' . join( '/', $url_slugs_before ), '/' . join( '/', $url_slugs ), $url );

	return untrailingslashit( $url );
}

/**
 * Changes array of items into string of items, separated by comma and sql-escaped
 * @see https://coderwall.com/p/zepnaw
 * @global wpdb  $wpdb
 *
 * @param mixed|array  $items  item(s) to be joined into string
 * @param string $format %s or %d
 *
 * @return string Items separated by comma and sql-escaped
 */
function wpml_prepare_in( $items, $format = '%s' ) {
	global $wpdb;

	$items    = (array) $items;
	$how_many = count( $items );
	if ( $how_many > 0 ) {
		$placeholders    = array_fill( 0, $how_many, $format );
		$prepared_format = implode( ",", $placeholders );
		$prepared_in     = $wpdb->prepare( $prepared_format, $items );
	} else {
		$prepared_in = "";
	}

	return $prepared_in;
}

function is_not_installing_plugins() {
	$checked = isset( $_REQUEST[ 'checked' ] ) ? (array) $_REQUEST[ 'checked' ] : array();

	if ( ! isset( $_REQUEST[ 'action' ] ) ) {
		return true;
	} elseif ( $_REQUEST[ 'action' ] != 'activate' && $_REQUEST[ 'action' ] != 'activate-selected' ) {
		return true;
	} elseif ( ( ! isset( $_REQUEST[ 'plugin' ] ) || $_REQUEST[ 'plugin' ] != basename( ICL_PLUGIN_PATH ) . '/' . basename( __FILE__ ) ) && ! in_array( ICL_PLUGIN_FOLDER . '/' . basename( __FILE__ ), $checked ) ) {
		return true;
	} elseif ( in_array( ICL_PLUGIN_FOLDER . '/' . basename( __FILE__ ), $checked ) && ! isset( $sitepress ) ) {
		return true;
	}

	return false;
}

function wpml_mb_strtolower( $string ) {
	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( $string );
	}

	return strtolower( $string );
}

function wpml_mb_strpos( $haystack, $needle, $offset = 0 ) {
	if ( function_exists( 'mb_strpos' ) ) {
		return mb_strpos( $haystack, $needle, $offset );
	}

	return strpos( $haystack, $needle, $offset );
}

function wpml_mb_strlen( $str ) {
	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $str );
	}

	return strlen( $str );
}

function wpml_set_plugin_as_inactive() {
	global $icl_plugin_inactive;
	if ( ! defined( 'ICL_PLUGIN_INACTIVE' ) ) {
		define( 'ICL_PLUGIN_INACTIVE', true );
	}
	$icl_plugin_inactive = true;
}

function wpml_version_is( $version_to_check, $comparison = '==' ) {
	return version_compare( ICL_SITEPRESS_VERSION, $version_to_check, $comparison ) && function_exists( 'wpml_site_uses_icl' );
}

function wpml_site_uses_icl_message_notice() {
	$messages[ ] = 'Your website is configured to connect to ICanLocalize.';
	$messages[ ] = 'This version of WPML cannot be used in this case and it will be not functional until you downgrade or update, once available, to a version which is compatible.';
	$messages[ ] = 'To downgrade to a previous compatible version of WPML please %sfollow this link%s.';

	$translated_messages      = array();
	$downgrade_url            = 'https://wpml.org/forums/topic/how-to-downgrade-from-wpml-3-2/';
	$downgrade_anchor_opening = '<a href="' . $downgrade_url . '" target="_blank">';
	$downgrade_anchor_closing = '</a>';
	foreach ( $messages as $message ) {
		$translated_messages[ ] = sprintf( __( $message, 'sitepress' ), $downgrade_anchor_opening, $downgrade_anchor_closing );
	}
	?>
	<div class="error">
		<p><?php echo implode( '<br/>', $translated_messages ); ?></p>
	</div>
<?php
}

function wpml_apply_include_filters() {
	if ( icl_get_setting( 'language_domains' ) ) {
		add_filter( 'plugins_url', 'wpml_filter_include_url' ); //so plugin includes get the correct path
		add_filter( 'template_directory_uri', 'wpml_filter_include_url' ); //js includes get correct path
		add_filter( 'stylesheet_directory_uri', 'wpml_filter_include_url' ); //style.css gets included right
	}
}

if(!function_exists('wpml_filter_include_url')) {
	function wpml_filter_include_url( $result ) {
		if ( isset( $_SERVER[ 'HTTP_HOST' ] ) ) {
			$http_host_parts = explode( ':', $_SERVER[ 'HTTP_HOST' ] );
			unset( $http_host_parts[ 1 ] );
			$http_host_without_port = implode( $http_host_parts );
			$path                   = str_replace( parse_url( $result, PHP_URL_HOST ), $http_host_without_port, $result );
		} else {
			$path = '';
		}

		return $path;
	}
}

/**
 * Interrupts the plugin activation process if the WPML Core Plugin could not be activated
 */
function icl_suppress_activation() {
	$active_plugins    = get_option( 'active_plugins' );
	$icl_sitepress_idx = array_search( ICL_PLUGIN_FOLDER . '/sitepress.php', $active_plugins );
	if ( false !== $icl_sitepress_idx ) {
		unset( $active_plugins[ $icl_sitepress_idx ] );
		update_option( 'active_plugins', $active_plugins );
		unset( $_GET[ 'activate' ] );
		$recently_activated = get_option( 'recently_activated' );
		if ( ! isset( $recently_activated[ ICL_PLUGIN_FOLDER . '/sitepress.php' ] ) ) {
			$recently_activated[ ICL_PLUGIN_FOLDER . '/sitepress.php' ] = time();
			update_option( 'recently_activated', $recently_activated );
		}
	}
}

/**
 * @param SitePress $sitepress
 */
function activate_installer( $sitepress ) {
	// installer hook - start
	include_once ICL_PLUGIN_PATH . '/inc/installer/loader.php'; //produces global variable $wp_installer_instance
	$args = array(
		'plugins_install_tab' => 1,
		'high_priority'       => 1,
		'site_key_nags'       => array(
			array(
				'repository_id' => 'wpml',
				'product_name'  => 'WPML',
				'condition_cb'  => array( $sitepress, 'setup' )
			)
		)
	);
	/** @var WP_Installer $wp_installer_instance */
	WP_Installer_Setup( $wp_installer_instance, $args );
	// installer hook - end
}

function wpml_missing_filter_input_notice() {
	?>
	<div class="message error">
		<h3><?php _e( "WPML can't be functional because it requires a disabled PHP extension!", 'sitepress' ) ?></h3>

		<p><?php _e( "To ensure and improve the security of your website, WPML makes use of the ", 'sitepress' ) ?><a href="http://php.net/manual/en/book.filter.php">PHP Data Filtering</a> extension.<br><br>
			<?php _e( "The filter extension is enabled by default as of PHP 5.2.0. Before this time an experimental PECL extension was
            used, however, the PECL version is no longer recommended to be used or updated. (source: ", 'sitepress' ) ?><a href="http://php.net/manual/en/filter.installation.php">PHP Manual Function Reference Variable and
			                                                                                                                                                                       Type Related Extensions Filter
			                                                                                                                                                                       Installing/Configuring</a>)<br>
			<br>
			<?php _e( "The filter extension is enabled by default as of PHP 5.2, therefore it must have been disabled by either you or your host.", 'sitepress' ) ?>
			<br><?php _e( "To enable it, either you or your host will need to open your website's php.ini file and either:", 'sitepress' ) ?><br>
		<ol>
			<li><?php _e( "Remove the 'filter_var' string from the 'disable_functions' directive or...", 'sitepress' ) ?>
			</li>
			<li><?php _e( "Add the following line:", 'sitepress' ) ?> <code class="inline-code">extension=filter.so</code></li>
		</ol>
		<?php $ini_location = php_ini_loaded_file();
		if ( $ini_location !== false ) {
			?>
			<strong><?php echo __( "Your php.ini file is located at", 'sitepress' ) . ' ' . $ini_location ?>.</strong>
		<?php
		}
		?>
	</div>
<?php
}

function repair_el_type_collate() {
	global $wpdb;

	$correct_collate = $wpdb->get_var (
		"SELECT collation_name
          FROM information_schema.COLUMNS
          WHERE TABLE_NAME = '{$wpdb->posts}'
                AND COLUMN_NAME = 'post_type'
                    AND table_schema = (SELECT DATABASE())
          LIMIT 1"
	);

	// translations
	$table_name = $wpdb->prefix . 'icl_translations';
	$sql
	            = "
             ALTER TABLE `{$table_name}`
                CHANGE `element_type` `element_type` VARCHAR( 36 ) NOT NULL DEFAULT 'post_post' COLLATE {$correct_collate}
            ";
	if ( $wpdb->query ( $sql ) === false ) {
		throw new Exception( $wpdb->last_error );
	}
}
