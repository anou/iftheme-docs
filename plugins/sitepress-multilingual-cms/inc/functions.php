<?php
/**
 * SitePress Template functions
 *
 * @package wpml-core
 */

function wpml_site_uses_icl() {
    //site_id
    //access_key
    //icl_account_email

    $site_id           = false;
    $access_key        = false;
    $icl_account_email = false;

    global $sitepress;
    if ( isset( $sitepress ) ) {
        $site_id           = $sitepress->get_setting( 'site_id' );
        $access_key        = $sitepress->get_setting( 'access_key' );
        $icl_account_email = $sitepress->get_setting( 'icl_account_email' );
    } else {
        $sitepress_settings = get_option( 'icl_sitepress_settings' );
        if ( $sitepress_settings ) {
            $site_id           = isset( $sitepress_settings[ 'site_id' ] ) ? $sitepress_settings[ 'site_id' ] : false;
            $access_key        = isset( $sitepress_settings[ 'access_key' ] ) ? $sitepress_settings[ 'access_key' ] : false;
            $icl_account_email = isset( $sitepress_settings[ 'icl_account_email' ] ) ? $sitepress_settings[ 'icl_account_email' ] : false;
        }
    }

    return ( $site_id || $access_key || $icl_account_email );
}

/**
 * @param string $key
 * @param bool   $default
 *
 * @return bool|mixed
 */
function icl_get_setting( $key, $default = false ) {
	global $sitepress;

	if ( isset( $sitepress ) ) {
		return $sitepress->get_setting( $key, $default );
	} else {
		//We don't have an instance of SitePress class: let's try with $sitepress_settings
		global $sitepress_settings;

		if ( ! isset( $sitepress_settings ) ) {
			//We don't have an instance of $sitepress_settings variable.
			//This means that probably we are in a stage where this instance can't be created
			//Therefore, let's directly read the settings from the DB
			$sitepress_settings = get_option( 'icl_sitepress_settings' );
		}

		return isset( $sitepress_settings[ $key ] ) ? $sitepress_settings[ $key ] : $default;
	}
}

/**
 * @param string $key
 * @param mixed  $value
 * @param bool   $save_now Must call icl_save_settings() to permanently store the value
 */
function icl_set_setting( $key, $value, $save_now = false ) {
	global $sitepress;
	if ( isset( $sitepress ) ) {
		$sitepress->set_setting( $key, $value, $save_now );
	} else {
		//We don't have an instance of SitePress class: let's try with $sitepress_settings
		global $sitepress_settings;

		if ( ! isset( $sitepress_settings ) ) {
			//We don't have an instance of $sitepress_settings variable.
			//This means that probably we are in a stage where this instance can't be created
			//Therefore, let's directly read the settings from the DB
			$sitepress_settings = get_option( 'icl_sitepress_settings' );
		}
		$sitepress_settings[$key] = $value;

		//We need to save settings anyway, in this case
		update_option( 'icl_sitepress_settings', $sitepress_settings );

		do_action( 'icl_save_settings', $sitepress_settings );
	}
}

function icl_save_settings() {
	global $sitepress;
	$sitepress->save_settings();
}

/**
 * Add settings link to plugin page.
 *
 * @param $links
 * @param $file
 *
 * @return array
 */
function icl_plugin_action_links($links, $file) {
    $this_plugin = basename(ICL_PLUGIN_PATH) . '/sitepress.php';
    if($file == $this_plugin) {
        $links[] = '<a href="admin.php?page='.basename(ICL_PLUGIN_PATH).'/menu/languages.php">' . __('Configure', 'sitepress') . '</a>';
    }
    return $links;
}

if(defined('ICL_DEBUG_MODE') && ICL_DEBUG_MODE){           
    add_action('admin_notices', '_icl_deprecated_icl_debug_mode');
}

function _icl_deprecated_icl_debug_mode(){
    echo '<div class="updated"><p><strong>ICL_DEBUG_MODE</strong> no longer supported. Please use <strong>WP_DEBUG</strong> instead.</p></div>';
} 

if(!function_exists('icl_js_escape')) {
	function icl_js_escape( $str ) {
		$str = esc_js( $str );
		$str = htmlspecialchars_decode( $str );

		return $str;
	}
}

function icl_nobreak($str){
    return preg_replace("# #", '&nbsp;', $str);
} 

function icl_strip_control_chars($string){
    // strip out control characters (all but LF, NL and TAB)
    $string = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $string);
    return $string;
}

function _icl_tax_has_objects_recursive($id, $term_id = -1, $rec = 0){
    // based on the case where two categories were one the parent of another
    // eliminating the chance of infinite loops by letting this function calling itself too many times
    // 100 is the default limit in most of teh php configuration
    //
    // this limit this function to work only with categories nested up to 60 levels
    // should enough for most cases
    if($rec > 60) return false;
    
    global $wpdb;
    
    if($term_id === -1){
        $term_id = $wpdb->get_var($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $id));
    }
    
    $children = $wpdb->get_results($wpdb->prepare("
        SELECT term_taxonomy_id, term_id, count FROM {$wpdb->term_taxonomy} WHERE parent = %d
    ", $term_id));
    
    $count = 0;
    foreach($children as $ch){
        $count += $ch->count;
    }
    
    if($count){
        return true;
    }else{
        foreach($children as $ch){
            if(_icl_tax_has_objects_recursive($ch->term_taxonomy_id, $ch->term_id,  $rec+1)){
                return true;
            }    
        }
        
    }                    
    return false;
}    

function icl_get_post_children_recursive($post, $type = 'page'){
    global $wpdb;
    
    $post = (array)$post;
    
    $children = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_parent IN (".join(',', $post).")", $type));
    
    if(!empty($children)){
        $children = array_merge($children, icl_get_post_children_recursive($children));
    }
    
    return $children;
    
}

function icl_get_tax_children_recursive($id, $taxonomy = 'category'){
    global $wpdb;
    
    $id = (array)$id;    
    
    $children = $wpdb->get_col($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} x WHERE x.taxonomy=%s AND parent IN (".join(',', $id).")", $taxonomy));
    
    if(!empty($children)){
        $children = array_merge($children, icl_get_tax_children_recursive($children));
    }
    
    return $children;
    
}

function _icl_trash_restore_prompt(){
    if(isset($_GET['lang'])){
        $post = get_post(intval($_GET['post']));
        if(isset($post->post_status) && $post->post_status == 'trash'){
            $post_type_object = get_post_type_object( $post->post_type );
            $ret = '<p>';
            $ret .= sprintf(__('This translation is currently in the trash. You need to either <a href="%s">delete it permanently</a> or <a href="%s">restore</a> it in order to continue.'), 
                get_delete_post_link($post->ID, '', true) , 
                wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID)
                );
            $ret .= '</p>';
            wp_die($ret);
        }            
    }    
}

function icl_pop_info($message, $icon='info', $args = array()){
    switch($icon){
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
    extract($defaults);
    extract($args, EXTR_OVERWRITE);

	/** @var $but_style array */
	/** @var $icon_size string */

    ?>
    <div class="icl_pop_info_wrap">
		<img class="icl_pop_info_but <?php echo join(' ', $but_style)?>" src="<?php echo $icon ?>" width="<?php echo $icon_size ?>" height="<?php echo $icon_size ?>" alt="info" />
    <div class="icl_cyan_box icl_pop_info">
    <img class="icl_pop_info_but_close" align="right" src="<?php echo ICL_PLUGIN_URL ?>/res/img/ico-close.png" width="12" height="12" alt="x" />
    <?php echo $message; ?>
    </div>
    </div>
    <?php
}

function icl_is_post_edit(){
    static $is;
    if(is_null($is)){
        global $pagenow;
        $is = ($pagenow == 'post-new.php' || ($pagenow == 'post.php' && isset($_GET['action']) && $_GET['action']=='edit'));    
    }
    return $is;
}

/**
 * Build or update duplicated posts from a master post.
 *
 * @param  string                $master_post_id The ID of the post to duplicate from. Master post doesn't need to be in the default language.
 *
 * @uses SitePress
 * @uses TranslationManagement
 */
function icl_makes_duplicates( $master_post_id )
{
	$post = get_post($master_post_id);
	$post_type = $post->post_type;

	if($post->post_status == 'auto-draft' || $post->post_type == 'revision') {
		return;
	}

	global $sitepress, $iclTranslationManagement;
	if ( !isset( $iclTranslationManagement ) ) {
		$iclTranslationManagement = new TranslationManagement;
	}
	if ( $sitepress->is_translated_post_type( $post_type ) ) {
		$iclTranslationManagement->make_duplicates_all( $master_post_id );
	}
}

/**
 * Build duplicated posts from a master post only in case of the duplicate not being present at the time.
 *
 * @param  string                $master_post_id The ID of the post to duplicate from. Master post doesn't need to be in the default language.
 *
 * @uses SitePress
 */
function icl_makes_duplicates_public( $master_post_id ) {

	global $sitepress;

	$master_post = get_post( $master_post_id );

	if ( $master_post->post_status == 'auto-draft' || $master_post->post_type == 'revision' ) {
		return;
	}

	$active_langs = $sitepress->get_active_languages();

	foreach ( $active_langs as $lang_to => $one ) {

		$trid = $sitepress->get_element_trid( $master_post->ID, 'post_' . $master_post->post_type );
		$lang_from = $sitepress->get_source_language_by_trid( $trid );

		if ( $lang_from == $lang_to ) {
			continue;
		}

		$post_array[ 'post_author' ]    = $master_post->post_author;
		$post_array[ 'post_date' ]      = $master_post->post_date;
		$post_array[ 'post_date_gmt' ]  = $master_post->post_date_gmt;
		$post_array[ 'post_content' ]   = addslashes_gpc( apply_filters( 'icl_duplicate_generic_string', $master_post->post_content, $lang_to, array( 'context' => 'post', 'attribute' => 'content', 'key' => $master_post->ID ) ) );
		$post_array[ 'post_title' ]     = addslashes_gpc( apply_filters( 'icl_duplicate_generic_string', $master_post->post_title, $lang_to, array( 'context' => 'post', 'attribute' => 'title', 'key' => $master_post->ID ) ) );
		$post_array[ 'post_excerpt' ]   = addslashes_gpc( apply_filters( 'icl_duplicate_generic_string', $master_post->post_excerpt, $lang_to, array( 'context' => 'post', 'attribute' => 'excerpt', 'key' => $master_post->ID ) ) );
		$post_array[ 'post_status' ]    = $master_post->post_status;
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
 * 
 * @global wpdb $wpdb
 * @param string $text
 * @return string
 */
function wpml_like_escape($text) {
	global $wpdb;
	
	if (method_exists($wpdb, 'esc_like')) {
		return $wpdb->esc_like($text);
	}

	/** @noinspection PhpDeprecationInspection */
	return like_escape($text);
}

/**
 * @param $url
 * Removes the subdirectory in which WordPress is installed from a url.
 * If WordPress is not installed in a subdirectory, then then input is returned unaltered.
 * @return string
 */
function wpml_strip_subdir_from_url( $url ) {
    global $sitepress;

    remove_filter( 'home_url', array( $sitepress, 'home_url' ), 1, 4 );

    //Remove potentially existing subdir slug before checking the url
    $subdir       = parse_url( home_url(), PHP_URL_PATH );
    $subdir_slugs = explode( '/', $subdir );

    add_filter( 'home_url', array( $sitepress, 'home_url' ), 1, 4 );

    $url_path  = parse_url( $url, PHP_URL_PATH );
    $url_slugs = explode( '/', $url_path );

    foreach ( (array) $url_slugs as $key => $slug ) {
        if ( ! trim( $slug ) ) {
            unset( $url_slugs[ $key ] );
        }
    }

    foreach ( (array) $subdir_slugs as $key => $slug ) {
        if ( ! trim( $slug ) ) {
            unset( $subdir_slugs[ $key ] );
        }
    }

    if ( ! empty( $subdir_slugs ) && ! empty( $url_slugs ) ) {
        foreach ( $subdir_slugs as $key => $slug ) {
            if ( isset( $url_slugs[ $key ] ) && $slug == $url_slugs[ $key ] ) {
                unset( $url_slugs[ $key ] );
            }
        }

        $url_path_new = join( '/', $url_slugs );

        $url = str_replace( $url_path, $url_path_new, $url );
    }

    return $url;
}

/**
 * Changes array of items into string of items, separated by comma and sql-escaped 
 * 
 * @see https://coderwall.com/p/zepnaw 
 * 
 * @global wpdb $wpdb
 * @param array $items items to be joined into string
 * @param string $format %s or %d
 * @return string Items separated by comma and sql-escaped 
 */
function wpml_prepare_in(array $items, $format = '%s') { 
	global $wpdb;
	
	$how_many = count($items);
	$placeholders = array_fill(0, $how_many, $format);
	$prepared_format = implode(",", $placeholders);
	$prepared_in = $wpdb->prepare($prepared_format, $items);
	
	return $prepared_in;
	
}