<?php

add_action('plugins_loaded', 'icl_st_init');

function icl_st_init(){                       
    global $sitepress_settings, $sitepress, $wpdb, $icl_st_err_str;

    if ( $GLOBALS['pagenow'] === 'site-new.php' && isset($_REQUEST['action']) && 'add-site' === $_REQUEST['action'] ) return;
    
    add_action('icl_update_active_languages', 'icl_update_string_status_all');
    add_action('update_option_blogname', 'icl_st_update_blogname_actions',5,2);
    add_action('update_option_blogdescription', 'icl_st_update_blogdescription_actions',5,2);

    if(isset($_GET['icl_action']) && $_GET['icl_action'] == 'view_string_in_page'){
        icl_st_string_in_page($_GET['string_id']);
        exit;
    }

    if(isset($_GET['icl_action']) && $_GET['icl_action'] == 'view_string_in_source'){
        icl_st_string_in_source($_GET['string_id']);
        exit;
    }
    
    if ( get_magic_quotes_gpc() && isset($_GET['page']) && $_GET['page'] === WPML_ST_FOLDER . '/menu/string-translation.php'){
        $_POST = stripslashes_deep( $_POST );         
    }
              
    if(!isset($sitepress_settings['existing_content_language_verified']) || !$sitepress_settings['existing_content_language_verified']){
        return;
    }          
    
    if(!isset($sitepress_settings['st']['sw'])){
        $sitepress_settings['st']['sw'] = array();  //no settings for now
        $sitepress->save_settings($sitepress_settings); 
        $init_all = true;
    }
    
    if(!isset($sitepress_settings['st']['strings_per_page'])){
        $sitepress_settings['st']['strings_per_page'] = 10;
        $sitepress->save_settings($sitepress_settings); 
    }elseif(isset($_GET['strings_per_page']) && $_GET['strings_per_page'] > 0){
        $sitepress_settings['st']['strings_per_page'] = $_GET['strings_per_page'];
        $sitepress->save_settings($sitepress_settings); 
    }
    if(!isset($sitepress_settings['st']['icl_st_auto_reg'])){
        $sitepress_settings['st']['icl_st_auto_reg'] = 'disable';
        $sitepress->save_settings($sitepress_settings); 
    }
    if(empty($sitepress_settings['st']['strings_language'])){
        $iclsettings['st']['strings_language'] = $sitepress_settings['st']['strings_language'] = 'en';
        $sitepress->save_settings($iclsettings);
    }
    
    if(!isset($sitepress_settings['st']['translated-users'])) $sitepress_settings['st']['translated-users'] = array();
    
    if((isset($_POST['iclt_st_sw_save']) && wp_verify_nonce($_POST['_wpnonce'], 'icl_sw_form')) || isset($init_all)){
            if(isset($init_all)){
                
                global $WPML_String_Translation;
                $WPML_String_Translation->initialize_wp_and_widget_strings( );
                
            }  
                    
            if(isset($_POST['iclt_st_sw_save'])){
                $updat_string_statuses = false;
                $sitepress_settings['st']['sw'] = $_POST['icl_st_sw'];
                if($sitepress_settings['st']['strings_language'] != $_POST['icl_st_sw']['strings_language']){
                    $updat_string_statuses = true;
                }
                $sitepress_settings['st']['strings_language'] = $_POST['icl_st_sw']['strings_language'];
                $sitepress->save_settings($sitepress_settings); 
                                
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}icl_strings SET language=%s WHERE language <> %s", 
                    $sitepress_settings['st']['strings_language'], $sitepress_settings['st']['strings_language']));
                
                if($updat_string_statuses){
                    icl_update_string_status_all();
                }
                
                
                //register author strings                
                if(!empty($sitepress_settings['st']['translated-users'])){
                    icl_st_register_user_strings_all();
                }
                
                wp_redirect(admin_url('admin.php?page='. WPML_ST_FOLDER .'/menu/string-translation.php&updated=true'));
            }
            
    }

	// handle po file upload
	if ( isset( $_POST[ 'icl_po_upload' ] ) && wp_verify_nonce( $_POST[ '_wpnonce' ], 'icl_po_form' ) ) {

		if ( $_FILES[ 'icl_po_file' ][ 'size' ] == 0 ) {
			$icl_st_err_str = __( 'File upload error', 'wpml-string-translation' );
		} else {
            
            global $po_importer;
            
            require_once( WPML_ST_PATH . '/inc/gettext/wpml-po-import.class.php' );
        
            $po_importer = new WPML_PO_Import( $_FILES[ 'icl_po_file' ][ 'tmp_name' ] );
            
            $icl_st_err_str = $po_importer->get_errors( );
            
		}
	} elseif ( isset( $_POST[ 'action' ] ) && 'icl_st_save_strings' == $_POST[ 'action' ] ) {
		$arr = array_intersect_key( $_POST[ 'icl_strings' ], array_flip( $_POST[ 'icl_strings_selected' ] ) );
		//$arr = array_map('html_entity_decode', $arr);
		if ( isset( $_POST[ 'icl_st_po_language' ] ) ) {
			$arr_t = array_intersect_key( $_POST[ 'icl_translations' ], array_flip( $_POST[ 'icl_strings_selected' ] ) );
			$arr_f = array_intersect_key( $_POST[ 'icl_fuzzy' ], array_flip( $_POST[ 'icl_strings_selected' ] ) );
			//$arr_t = array_map('html_entity_decode', $arr_t);
		}
		$arr_c = array_intersect_key( $_POST[ 'icl_context' ], array_flip( $_POST[ 'icl_strings_selected' ] ) );

		foreach ( $arr as $k => $string ) {

            $string = str_replace('\n', "\n", $string );

			$name = isset( $_POST[ 'icl_name' ][ $k ] ) && $_POST[ 'icl_name' ][ $k ] ? $_POST[ 'icl_name' ][ $k ] : md5( $string );

			$string_id = icl_register_string( array(
                                                    'domain' => $_POST[ 'icl_st_domain_name' ],
                                                    'context' => $arr_c[ $k ]
                                                   ),
                                             $name,
                                             $string );
			if ( $string_id && isset( $_POST[ 'icl_st_po_language' ] ) ) {
				if ( $arr_t[ $k ] != "" ) {
					if ( $arr_f[ $k ] ) {
						$_status = ICL_TM_NOT_TRANSLATED;
					} else {
						$_status = ICL_TM_COMPLETE;
					}
                    $translation = str_replace('\n', "\n", $arr_t[ $k ] );

					icl_add_string_translation( $string_id, $_POST[ 'icl_st_po_language' ], $translation, $_status );
					icl_update_string_status( $string_id );
				}
			}
		}
	}
    
    //handle po export
    if(isset($_POST['icl_st_pie_e']) && wp_verify_nonce($_POST['_wpnonce'], 'icl_po_export')){
        //force some filters
        if(isset($_GET['status'])) unset($_GET['status']);
        $_GET['show_results']='all';
        if($_POST['icl_st_e_context']){
            $_GET['context'] = $_POST['icl_st_e_context'];
        }
                                                    
        $_GET['translation_language'] = $_POST['icl_st_e_language'];
        $strings = icl_get_string_translations();
        if(!empty($strings)){
            $po = icl_st_generate_po_file($strings, !isset($_POST['icl_st_pe_translations']));
        }else{
            $po = "";  
        }
        if(!isset($_POST['icl_st_pe_translations'])){
            $popot = 'pot';
            $poname = $_POST['icl_st_e_context'] ? urlencode($_POST['icl_st_e_context']) : 'all_context'; 
        }else{
            $popot = 'po';
            $poname = $_GET['context'] . '-' . $_GET['translation_language'];
        }
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Disposition: attachment; filename=".$poname.'.'.$popot.";");
        header("Content-Length: ". strlen($po));
        echo $po;
        exit(0);
    }
    
    // handle string translation request
    elseif ( isset( $_POST[ 'icl_st_action' ] ) && $_POST[ 'icl_st_action' ] == 'send_strings' ) {

        add_action( 'init', 'icl_send_strings_action' );
    }
    
    
    // hook into blog title and tag line    
    add_filter('option_blogname', 'icl_sw_filters_blogname');
    add_filter('option_blogdescription', 'icl_sw_filters_blogdescription');        
    add_filter('widget_title', 'icl_sw_filters_widget_title', 0);  //highest priority
    add_filter('widget_text', 'icl_sw_filters_widget_text', 0); //highest priority

	$setup_complete = apply_filters('WPML_get_setting', false, 'setup_complete' );
	$theme_localization_type = apply_filters('WPML_get_setting', false, 'theme_localization_type' );
	if ( $setup_complete
	     && $theme_localization_type == 1
	) {
		add_filter( 'gettext', 'icl_sw_filters_gettext', 9, 3 );
		add_filter( 'gettext_with_context', 'icl_sw_filters_gettext_with_context', 1, 4 );
		add_filter( 'ngettext', 'icl_sw_filters_ngettext', 9, 5 );
		add_filter( 'ngettext_with_context', 'icl_sw_filters_nxgettext', 9, 6 );
	}
    
    $widget_groups = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'widget\\_%'");
    foreach($widget_groups as $w){
        add_action('update_option_' . $w->option_name, 'icl_st_update_widget_title_actions', 5, 2);
    }
    
    add_action('update_option_widget_text', 'icl_st_update_text_widgets_actions', 5, 2);
    add_action('update_option_sidebars_widgets', '__icl_st_init_register_widget_titles');
    
    if($icl_st_err_str){
        add_action('admin_notices', 'icl_st_admin_notices');
    }
		if (isset($_REQUEST['string-translated']) && $_REQUEST['string-translated'] == true) {
			add_action('admin_notices', 'icl_st_admin_notices_string_updated');
		}
    
    add_filter('get_the_author_first_name', 'icl_st_author_first_name_filter', 10, 2);
    add_filter('get_the_author_last_name', 'icl_st_author_last_name_filter', 10, 2);
    add_filter('get_the_author_nickname', 'icl_st_author_nickname_filter', 10, 2);
    add_filter('get_the_author_description', 'icl_st_author_description_filter', 10, 2);
    add_filter('the_author', 'icl_st_author_displayname_filter', 10);
    
}

function icl_send_strings_action( ) {
    if ( wp_verify_nonce(
        filter_input( INPUT_POST, 'iclnonce', FILTER_SANITIZE_STRING ),
        'icl-string-translation'
    ) ) {
        $_POST        = stripslashes_deep( $_POST );
        $string_ids   = explode( ',', $_POST[ 'strings' ] );
        $translate_to = array();
        foreach ( $_POST[ 'translate_to' ] as $lang_to => $one ) {
            $translate_to[ $lang_to ] = $lang_to;
        }
        if ( ! empty( $translate_to ) ) {
            global $WPML_String_Translation;
            TranslationProxy_Basket::add_strings_to_basket($string_ids, $WPML_String_Translation->get_strings_language(), $translate_to);
        }
    }
    
}

add_action('profile_update', 'icl_st_register_user_strings');
add_action('user_register', 'icl_st_register_user_strings');

function __icl_st_init_register_widget_titles(){

    // create a list of active widgets
    $active_widgets = array();
    $widgets = (array)get_option('sidebars_widgets');    
    
    foreach($widgets as $k=>$w){                     
        if('wp_inactive_widgets' != $k && $k != 'array_version'){
            if(is_array($widgets[$k]))
            foreach($widgets[$k] as $v){                
                $active_widgets[] = $v;
            }
        }
    }                      
    foreach($active_widgets as $aw){        
        $int = preg_match('#-([0-9]+)$#i',$aw, $matches);
        if($int){
            $suffix = $matches[1];
        }else{
            $suffix = 1;
        }
        $name = preg_replace('#-[0-9]+#','',$aw);                

        $value = get_option("widget_".$name);
        if(isset($value[$suffix]['title']) && $value[$suffix]['title']){
            $w_title = $value[$suffix]['title'];     
        }else{
            $w_title = __icl_get_default_widget_title($aw);
            $value[$suffix]['title'] = $w_title;
            update_option("widget_".$name, $value);
        }
        
        if($w_title){            
            icl_register_string('Widgets', 'widget title - ' . md5($w_title), $w_title);
        }
    }    
}

function __icl_get_default_widget_title($id){
    if(preg_match('#archives(-[0-9]+)?$#i',$id)){                        
        $w_title = 'Archives';
    }elseif(preg_match('#categories(-[0-9]+)?$#i',$id)){
        $w_title = 'Categories';
    }elseif(preg_match('#calendar(-[0-9]+)?$#i',$id)){
        $w_title = 'Calendar';
    }elseif(preg_match('#links(-[0-9]+)?$#i',$id)){
        $w_title = 'Links';
    }elseif(preg_match('#meta(-[0-9]+)?$#i',$id)){
        $w_title = 'Meta';
    }elseif(preg_match('#pages(-[0-9]+)?$#i',$id)){
        $w_title = 'Pages';
    }elseif(preg_match('#recent-posts(-[0-9]+)?$#i',$id)){
        $w_title = 'Recent Posts';
    }elseif(preg_match('#recent-comments(-[0-9]+)?$#i',$id)){
        $w_title = 'Recent Comments';
    }elseif(preg_match('#rss-links(-[0-9]+)?$#i',$id)){
        $w_title = 'RSS';
    }elseif(preg_match('#search(-[0-9]+)?$#i',$id)){
        $w_title = 'Search';
    }elseif(preg_match('#tag-cloud(-[0-9]+)?$#i',$id)){
        $w_title = 'Tag Cloud';
    }else{
        $w_title = false;
    }  
    return $w_title;  
}

/**
 * Registers a string for translation
 *
 * @param string  $context              The context for the string
 * @param string  $name                 A name to help the translator understand what’s being translated
 * @param string  $value                The string value
 * @param bool    $allow_empty_value    This param is not being used
 *
 * @return int string_id of the just registered string or the id found in the database corresponding to the
 *             input parameters
 */
function icl_register_string( $context, $name, $value, $allow_empty_value = false ) {
	global $WPML_String_Translation;
    
    if ( ! $name ) {
        $name = md5( $value );
    }

	/** @var WPML_Admin_String_Filter $admin_string_filter */
	$strings_language    = $WPML_String_Translation->get_strings_language();
	$admin_string_filter = $WPML_String_Translation->get_admin_string_filter( $strings_language );

	if ( $admin_string_filter ) {
        $string_id = $admin_string_filter->register_string( $context, $name, $value, $allow_empty_value );
    } else {
        $string_id = null;
    }

	return $string_id;
}

/**
 * @since      unknown
 * @deprecated 3.2 use 'wpml_register_string_for_translation' action instead.
 */
add_filter('register_string_for_translation', 'icl_register_string', 10, 4);


/**
 * Registers a string for translation
 *
 * @api
 *
 * @param string  $context              The context for the string
 * @param string  $name                 A name to help the translator understand what’s being translated
 * @param string  $value                The string value
 * @param bool    $allow_empty_value    This param is not being used
 *
 */
function wpml_register_single_string_action( $context, $name, $value, $allow_empty_value = false ) {
	global $WPML_String_Translation;

	/** @var WPML_Admin_String_Filter $admin_string_filter */
	$strings_language    = $WPML_String_Translation->get_strings_language();
	$admin_string_filter = $WPML_String_Translation->get_admin_string_filter( $strings_language );

	if ( $admin_string_filter ) {
        $admin_string_filter->register_string( $context, $name, $value, $allow_empty_value );
    }
}

/**
 * @since 3.2
 * @api
 */
add_action('wpml_register_single_string', 'wpml_register_single_string_action', 10, 4);

function icl_translate( $context, $name, $original_value = false, $allow_empty_value = false, &$has_translation = null, $target_lang = null ) {
	$result = $original_value;

	// We don't want to translate if the blog has been switched
	// using the switch_to_blog function.
	// The WPML tables might not exist in other blogs in a multisite install.
	$is_switched_blog = is_multisite() && ms_is_switched();
    if ( $is_switched_blog ) {
        // See if the switched from blog is the same as the current blog
    	$blog = end( $GLOBALS['_wp_switched_stack'] );

        if ( $GLOBALS['blog_id'] == $blog ) {
            $is_switched_blog = false;
        }
    }
    
	if ( ! $is_switched_blog ) {

		global $WPML_String_Translation;

		$lang_code = $target_lang ? $target_lang : $WPML_String_Translation->get_current_string_language( $name );
		/** @var WPML_Displayed_String_Filter $filter_instance */
		$filter_instance = $WPML_String_Translation->get_string_filter( $lang_code );

		if ( $filter_instance && $lang_code != $WPML_String_Translation->get_strings_language() ) {
			$result = $filter_instance->translate_by_name_and_context( $original_value, $name, $context, $has_translation );
		}
	}

	return $result;
}

function icl_st_is_registered_string($context, $name){
    global $wpdb;
    static $cache = array();
    if(isset($cache[$context][$name])){
        $string_id = $cache[$context][$name];
    }else{
        $string_id = $wpdb->get_var($wpdb->prepare("
            SELECT id 
            FROM {$wpdb->prefix}icl_strings
            WHERE context = %s AND name = %s ", $context, $name));
        $cache[$context][$name] = $string_id;
    }
    return $string_id;
}

function icl_st_string_has_translations($context, $name){
    global $wpdb;
    $sql = $wpdb->prepare(
        "
        SELECT COUNT(st.id) 
        FROM {$wpdb->prefix}icl_string_translations st 
        JOIN {$wpdb->prefix}icl_strings s ON s.id=st.string_id
        WHERE s.context = %s AND s.name = %s",
        $context,
        $name
    );

    return $wpdb->get_var($sql);
}

function icl_update_string_status($string_id){
    global $wpdb, $sitepress, $sitepress_settings;    
    $st = $wpdb->get_results($wpdb->prepare("SELECT language, status FROM {$wpdb->prefix}icl_string_translations WHERE string_id=%d", $string_id));    
    
    if($st){  

		if (isset($sitepress_settings['st']['strings_language'])) {
			$strings_language = $sitepress_settings['st']['strings_language'];
		} else {
			$strings_language = false;
		}
		foreach($st as $t){
			if( $strings_language != $t->language){
				$translations[$t->language] = $t->status;
			}
		}  
        
        $active_languages = $sitepress->get_active_languages();
        
        if(empty($translations) || max($translations) == ICL_TM_NOT_TRANSLATED){
            $status = ICL_TM_NOT_TRANSLATED;
        }elseif( in_array(ICL_TM_WAITING_FOR_TRANSLATOR,$translations) ){
            $status = ICL_TM_WAITING_FOR_TRANSLATOR;
        }elseif(count($translations) < count($active_languages) - intval(in_array($strings_language, array_keys($active_languages)))){
            if(in_array(ICL_TM_NEEDS_UPDATE,$translations)){
                $status = ICL_TM_NEEDS_UPDATE;
            }elseif(in_array(ICL_TM_COMPLETE,$translations)){
                $status = ICL_STRING_TRANSLATION_PARTIAL;            
            }else{
                $status = ICL_TM_NOT_TRANSLATED;
            }            
        }elseif(ICL_TM_NEEDS_UPDATE == array_unique($translations)){
            $status = ICL_TM_NEEDS_UPDATE;
        }else{
            if(in_array(ICL_TM_NEEDS_UPDATE,$translations)){
                $status = ICL_TM_NEEDS_UPDATE;
            }elseif(in_array(ICL_TM_NOT_TRANSLATED,$translations)){
                $status = ICL_STRING_TRANSLATION_PARTIAL;            
            }else{
                $status = ICL_TM_COMPLETE;
            }
        }
    }else{
        $status = ICL_TM_NOT_TRANSLATED;
    }    
    $wpdb->update($wpdb->prefix.'icl_strings', array('status'=>$status), array('id'=>$string_id));
    return $status;    
}

function icl_update_string_status_all(){
    global $wpdb;
    $res = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}icl_strings");
    foreach($res as $id){
        icl_update_string_status($id);
    }
}

function icl_unregister_string($context, $name){
    global $wpdb; 
    $string_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}icl_strings
                                                WHERE context=%s AND name=%s",
                                               $context, $name));
    if($string_id){
        $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_strings WHERE id=%d", $string_id));
        $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_string_translations WHERE string_id=%d", $string_id));
        $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_string_positions WHERE string_id=%d", $string_id));
    }
    do_action('icl_st_unregister_string', $string_id);
}  

function __icl_unregister_string_multi($arr){
    global $wpdb; 
    $str = wpml_prepare_in( $arr, '%d' );
    $wpdb->query("
        DELETE s.*, t.* FROM {$wpdb->prefix}icl_strings s LEFT JOIN {$wpdb->prefix}icl_string_translations t ON s.id = t.string_id
        WHERE s.id IN ({$str})");
    $wpdb->query("DELETE FROM {$wpdb->prefix}icl_string_positions WHERE string_id IN ({$str})");
    do_action('icl_st_unregister_string_multi', $arr);
}

/**
 * @since      unknown
 * @deprecated 3.2 use 'wpml_translate_string' filter instead.
 */
function translate_string_filter( $original_value, $context, $name, $has_translation = null, $disable_auto_register = false, $language_code = null ) {
	return icl_t( $context, $name, $original_value, $has_translation, $disable_auto_register, $language_code );
}

/**
 * @since      unknown
 * @deprecated 3.2 use 'wpml_translate_single_string' filter instead.
 */
add_filter('translate_string', 'translate_string_filter', 10, 5);

/**
 * Retrieve a string translation
 * Looks for a string with matching $context and $name.
 * If it finds it, it looks for translation in the current language or the language specified
 * If a translation exists, it will return it. Otherwise, it will return the original string.
 *
 * @api
 *
 * @param string|bool $original_value           The string's original value
 * @param string      $context                  The string's registered context
 * @param string      $name                     The string's registered name
 * @param null|string $language_code            Return the translation in this language
 *                                              Default is NULL which returns the current language
 * @param bool|null   $has_translation          Currently unused. Defaults to NULL
 * @param bool        $disable_auto_register    Set to false internally - see icl_t
 *
 * @return string
 */
function wpml_translate_single_string_filter( $original_value, $context, $name, $language_code = null, $has_translation = null, $disable_auto_register = false ) {
	if(is_string($name)){
		return icl_t( $context, $name, $original_value, $has_translation, $disable_auto_register, $language_code );
	}
}

/**
 * @api
 * @since 3.2
 */
add_filter('wpml_translate_single_string', 'wpml_translate_single_string_filter', 10, 6);

/**
 * Retrieve a string translation
 * Looks for a string with matching $context and $name.
 * If it finds it, it looks for translation in the current language or the language specified
 * If a translation exists, it will return it. Otherwise, it will return the original string.
 *
 * @param string|bool $original_value           The string's original value
 * @param string      $context                  The string's registered context
 * @param string      $name                     The string's registered name
 * @param bool|null   $has_translation          Currently unused. Defaults to NULL
 * @param bool        $disable_auto_register    Currently unused. Set to false in calling icl_translate
 * @param null|string $language_code            Return the translation in this language
 *                                              Default is NULL which returns the current language
 *
 * @return string
 */
function icl_t( $context, $name, $original_value = false, &$has_translation = null, $disable_auto_register = false, $language_code = null ) {

	return icl_translate( $context, $name, $original_value, false, $has_translation, $language_code );
}

/**
 * @param $name
 *
 * Checks whether a given string is to be translated in the Admin back-end.
 * Currently only tagline and title of a site are to be translated.
 * All other admin strings are to always be displayed in the user admin language.
 *
 * @return bool
 */
function is_translated_admin_string( $name ) {
    $translated = false;

    $exclusions = array( 'Tagline', 'Blog Title' );

    if ( in_array( $name, $exclusions ) ) {
        $translated = true;
    }

    return $translated;
}

/**
 * Helper function for icl_t()
 * @param array $result
 * @param string $original_value
 * @return boolean
 */
function _icl_is_string_change($result, $original_value) {
	
	if ($result == false) {
		return false;
	} 
	
	if (!isset($result['value'])) {
		return false;
	}
	return (
                $result['translated'] && $result['original'] != $original_value ||
                !$result['translated'] && $result['value'] != $original_value
            );
}

function icl_add_string_translation( $string_id, $language, $value = null, $status = false, $translator_id = null, $translation_service = null, $batch_id = null ) {
    global $wpdb, $sitepress;
    
    $current_user = $sitepress->get_current_user();
    $res = $wpdb->get_row($wpdb->prepare("SELECT id, value, status
                                          FROM {$wpdb->prefix}icl_string_translations
                                          WHERE string_id=%d AND language=%s",
                                         $string_id, $language ) );

		// the same string should not be sent two times to translation
    if(isset($res->status) && $res->status == ICL_TM_WAITING_FOR_TRANSLATOR && is_null($value)) {
		return false;
	}

    if($res){
		$st_id     = $res->id;
		$st_update = array();
		if ( ! is_null( $value ) && $value != $res->value ) {  // null $value is for sending to translation. don't override existing.
			$st_update[ 'value' ] = $value;
		}
		if ( $status ) {
			$st_update[ 'status' ] = $status;
		} elseif ( $status === ICL_TM_NOT_TRANSLATED ) {
			$st_update[ 'status' ] = ICL_TM_NOT_TRANSLATED;
		}

		if ( ! empty( $st_update ) ) {
			if ( ! is_null( $translator_id ) ) {
				$st_update[ 'translator_id' ] = $current_user->ID;
			}

			if ( $translation_service ) {
				$st_update[ 'translation_service' ] = $translation_service;
			}

			if ( $batch_id ) {
				$st_update[ 'batch_id' ] = $batch_id;
			}

			$st_update[ 'translation_date' ] = current_time( "mysql" );
			$wpdb->update( $wpdb->prefix . 'icl_string_translations', $st_update, array( 'id' => $st_id ) );
		}
	} else {
		if ( ! $status ) {
			$status = ICL_TM_NOT_TRANSLATED;
		}
		$st = array(
			'string_id' => $string_id,
			'language'  => $language,
			'status'    => $status
		);
		if ( ! is_null( $value ) ) {
			$st[ 'value' ] = $value;
		}
		if ( is_null( $translator_id ) ) {
			$st[ 'translator_id' ] = $current_user->ID;
		}
        else{
            $st[ 'translator_id' ] = $translator_id;
        }

		if ( $translation_service ) {
			$st[ 'translation_service' ] = $translation_service;
		}

		if ( $batch_id ) {
			$st[ 'batch_id' ] = $batch_id;
		}

		$wpdb->insert( $wpdb->prefix . 'icl_string_translations', $st );
		$st_id = $wpdb->insert_id;
	}

	/** @var $ICL_Pro_Translation WPML_Pro_Translation */
	global $ICL_Pro_Translation;
	if ( $ICL_Pro_Translation ) {
		$ICL_Pro_Translation->_content_fix_links_to_translated_content( $st_id, $language, 'string' );
	}

	icl_update_string_status( $string_id );

	do_action( 'icl_st_add_string_translation', $st_id );

	return $st_id;
}

/**
 * 
 * @global WPDB $wpdb
 * @global array $sitepress_settings
 * @param string $option_name
 * @param string $language
 * @param string $new_value
 * @param int|bool $status
 * @param int $translator_id
 * @param int $rec_level
 * @return boolean|mixed
 */
function icl_update_string_translation($option_name, $language, $new_value = null, $status = false, $translator_id = null, $rec_level = 0) {
	global $wpdb, $WPML_String_Translation;
	
	if (!is_array($new_value)) {
		$new_value = (array) $new_value;
	}
	
	$updated = array();
	
	foreach ($new_value as $index => $value) {

		if (is_array($value)) {
			$name = "[". $option_name ."][" . $index . "]";
			$result = icl_update_string_translation($name, $language, $value, $status, $translator_id, $rec_level + 1);
			$updated[] = array_sum( explode(",", $result) );
		} else {
			if (is_string($index)) {
				if ($rec_level == 0) {
					$name = "[". $option_name ."]" . $index;
				} else {
					$name = $option_name . $index;
				}
			} else {
				$name = $option_name;
			}
			
			$select_original_string = "SELECT * FROM {$wpdb->prefix}icl_strings WHERE name = %s AND language = %s";
			$original_string = $wpdb->get_row($wpdb->prepare($select_original_string, $name, $WPML_String_Translation->get_strings_language()));
			if (!$original_string || !isset($original_string->id) || !is_numeric($original_string->id)) {
				continue;
			}

			$updated[] = icl_add_string_translation($original_string->id, $language, $value, $status, $translator_id);
			
		}

	}
	
	if (array_sum($updated) > 0) {
		return join(",", $updated);
	} else {
		return false;
	}
}

function icl_get_string_id( $string, $context, $name = false ) {
	global $wpdb;

	$sql          = "SELECT id FROM {$wpdb->prefix}icl_strings WHERE value=%s AND context=%s";
	$prepare_args = array( $string, $context );
	if ( $name !== false ) {
		$sql .= " AND name = %s ";
		$prepare_args[ ] = $name;
	}

	$id = (int) $wpdb->get_var( $wpdb->prepare( $sql, $prepare_args ) );

	return $id;
}

function icl_get_string_translations() {
	global $sitepress, $wpdb, $wp_query;

	$WPML_ST_Strings = new WPML_ST_Strings($sitepress, $wpdb, $wp_query);
	return $WPML_ST_Strings->get_string_translations();
}

/**
 * Returns indexed array with language code and value of string
 *
 * @param int         $string_id     ID of string in icl_strings DB table
 * @param bool|string $language_code false, or language code
 *
 * @return string
 */
function icl_get_string_by_id( $string_id, $language_code = false ) {
	global $wpdb, $sitepress_settings;

	if ( !$language_code ) {
		$language_code = $sitepress_settings[ 'st' ][ 'strings_language' ];
	}

	if ( $language_code == $sitepress_settings[ 'st' ][ 'strings_language' ] ) {

		$result_prepared = $wpdb->prepare( "SELECT language, value FROM {$wpdb->prefix}icl_strings WHERE id=%d", $string_id );
		$result = $wpdb->get_row( $result_prepared );

		if ( $result ) {
			return $result->value;
		}

	} else {
		$translations = icl_get_string_translations_by_id( $string_id );
		if ( isset( $translations[ $language_code ] ) ) {
			return $translations[ $language_code ]['value'];
		}
	}

	return false;
}

function icl_get_string_translations_by_id($string_id){
    global $wpdb;
    
    $translations = array();
    
    if ( $string_id ) {
        $results = $wpdb->get_results($wpdb->prepare("SELECT language, value, status FROM {$wpdb->prefix}icl_string_translations WHERE string_id=%d", $string_id));
        foreach($results as $row){
            $translations[$row->language] = array('value' => $row->value, 'status' => $row->status);
        }
    }
    
    return $translations;
    
}

function icl_get_relative_translation_status($string_id, $translator_id){
    global $wpdb, $sitepress, $sitepress_settings;
    
    $current_user = $sitepress->get_current_user();
    
    $user_lang_pairs = get_user_meta($current_user->ID, $wpdb->prefix.'language_pairs', true);

	$src_langs = array_intersect( array_keys( $sitepress->get_active_languages() ),
	                              array_keys( $user_lang_pairs[ $sitepress_settings[ 'st' ][ 'strings_language' ] ] ) );
    
    if(empty($src_langs)) return ICL_TM_NOT_TRANSLATED;
    
    $sql = "SELECT st.status
            FROM {$wpdb->prefix}icl_strings s 
            JOIN {$wpdb->prefix}icl_string_translations st ON s.id = st.string_id
            WHERE st.language IN (" . wpml_prepare_in( $src_langs ) . ") AND s.id = %d
    ";
    $statuses = $wpdb->get_col($wpdb->prepare($sql, $string_id));
    
    $status = ICL_TM_NOT_TRANSLATED;
    $one_incomplete = false;
    foreach($statuses as $s){
        if($s == ICL_TM_COMPLETE){
            $status = ICL_TM_COMPLETE;
        }elseif($s == ICL_TM_NOT_TRANSLATED){
            $one_incomplete = true;
        }
    }
    
    if($status == ICL_TM_COMPLETE && $one_incomplete){
        $status = ICL_STRING_TRANSLATION_PARTIAL;        
    }
    
    return $status;
}

function icl_get_strings_tracked_in_pages($string_translations){
    global $wpdb;
    // get string position in page - if found
    $found_strings = $strings_in_page = array();
    foreach(array_keys((array)$string_translations) as $string_id){
        $found_strings[] = $string_id;
    }
    if($found_strings){
        $res = $wpdb->get_results("
            SELECT kind, string_id  FROM {$wpdb->prefix}icl_string_positions 
            WHERE string_id IN (" . wpml_prepare_in($found_strings, '%d' ) . ")");
        foreach($res as $row){
            $strings_in_page[$row->kind][$row->string_id] = true;
        }
    }
    return $strings_in_page;
}

function icl_sw_filters_blogname($val){
	$val = icl_t('WP', 'Blog Title', $val);
	return $val;
}

function icl_sw_filters_blogdescription($val){
	$val = icl_t('WP', 'Tagline', $val);
  return $val;
}

function icl_sw_filters_widget_title($val){
	$val = icl_t('Widgets', 'widget title - ' . md5($val) , $val);
  return $val;  
}

function icl_sw_filters_widget_text($val){ 
	$val = icl_t('Widgets', 'widget body - ' . md5($val) , $val);
  return $val;
}

/**
 * @param      $translation String This parameter is not important to the filter since we filter before other filters.
 * @param      $text
 * @param      $domain
 * @param bool $name
 *
 * @return bool|mixed|string
 */
function icl_sw_filters_gettext( $translation, $text, $domain, $name = false ) {
    global $sitepress_settings;

	// We need to check for recursion just in case another function called 
	// from this function has a call to translate something else.
	// We'll end up in an infinite loop if this happens
	// https://onthegosystems.myjetbrains.com/youtrack/issue/wpmlst-473
	static $stop_recursion = false;
	if ( $stop_recursion ) {
		return $translation;
	}
	$stop_recursion = true;

    $has_translation = null;

    if ( ! defined( 'ICL_STRING_TRANSLATION_DYNAMIC_CONTEXT' ) ) {
        define( 'ICL_STRING_TRANSLATION_DYNAMIC_CONTEXT', 'wpml_string' );
    }
    
	if ( isset( $sitepress_settings[ 'st' ][ 'track_strings' ] ) && $sitepress_settings[ 'st' ][ 'track_strings' ] && did_action( 'after_setup_theme' ) && current_user_can( 'edit_others_posts' ) ) {
		if ( ! is_admin( ) ) {
            // track strings if the user has enabled this and if it's and editor or admin
            icl_st_track_string( $text, $domain, ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_PAGE );
        }
	}
    

    $register_dynamic_string = false;
    if ( $domain == ICL_STRING_TRANSLATION_DYNAMIC_CONTEXT ) {
        $register_dynamic_string = true;
    }

    if ( $register_dynamic_string ) {
        // register strings if the user has used ICL_STRING_TRANSLATION_DYNAMIC_CONTEXT (or it's value) as a text domain
        icl_register_string( $domain, $name, $text );
    }

    if ( ! $name ) {
        $name = md5( $text );
    }

    $ret_translation = icl_translate( $domain, $name, $text, false, $has_translation );

    if ( ! $has_translation ) {
        $ret_translation = $translation;
    }

    if ( isset( $_GET[ 'icl_string_track_value' ] ) && isset( $_GET[ 'icl_string_track_context' ] )
         && stripslashes( $_GET[ 'icl_string_track_context' ] ) == $domain && stripslashes( $_GET[ 'icl_string_track_value' ] ) == $text
    ) {
        $ret_translation = '<span style="background-color:' . $sitepress_settings[ 'st' ][ 'hl_color' ] . '">' . $ret_translation . '</span>';
    }

    $stop_recursion = false;
	
    return $ret_translation;
}

function icl_st_track_string( $text, $domain, $kind = ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_PAGE ) {

    if (is_multisite() && ms_is_switched()) {
        return;
    }

    require_once dirname(__FILE__) . '/gettext/wpml-string-scanner.class.php';

    static $string_scanner = null;
    if ( !$string_scanner ) {
        $string_scanner = new WPML_String_Scanner( );
    }
    $string_scanner->track_string( $text, $domain, $kind );
}

function icl_sw_filters_gettext_with_context($translation, $text, $_gettext_context, $domain){
    if ( $_gettext_context ) {
        return icl_sw_filters_gettext( $translation, $text, array( 'domain' => $domain, 'context' => $_gettext_context ) );
    } else {
        return icl_sw_filters_gettext( $translation, $text, $domain );
    }
}

function icl_sw_filters_ngettext($translation, $single, $plural, $number, $domain, $_gettext_context = false){    
    if($number == 1){
        return icl_sw_filters_gettext_with_context($translation, $single, $_gettext_context, $domain);    
    }else{
        return icl_sw_filters_gettext_with_context($translation, $plural, $_gettext_context, $domain);            
    }
}

function icl_sw_filters_nxgettext($translation, $single, $plural, $number, $_gettext_context, $domain){        
    return icl_sw_filters_ngettext($translation, $single, $plural, $number, $domain, $_gettext_context);
}

function icl_st_author_first_name_filter($value, $user_id){
    global $sitepress_settings;
    
    if(false === $user_id){
        global $authordata;
        $user_id = $authordata->data->ID;
    }
    
    $user = new WP_User($user_id);        
    if ( is_array( $user->roles ) && array_intersect($user->roles, (array)$sitepress_settings['st']['translated-users'])){
        $value = icl_st_translate_author_fields('first_name', $value, $user_id);
    }
        
    return $value;
}

function icl_st_author_last_name_filter($value, $user_id){
    global $sitepress_settings;
    
    if(false === $user_id){
        global $authordata;
        $user_id = $authordata->data->ID;
    }
    
    $user = new WP_User($user_id);        
    if ( is_array( $user->roles ) && array_intersect($user->roles, (array)$sitepress_settings['st']['translated-users'])){
        $value = icl_st_translate_author_fields('last_name', $value, $user_id);
    }
        
    return $value;
}

function icl_st_author_nickname_filter($value, $user_id){
    global $sitepress_settings;

    if(false === $user_id){
        global $authordata;
        $user_id = $authordata->data->ID;
    }
    
    $user = new WP_User($user_id);        
    if ( is_array( $user->roles ) && array_intersect($user->roles, (array)$sitepress_settings['st']['translated-users'])){
        $value = icl_st_translate_author_fields('nickname', $value, $user_id);
    }
        
    return $value;
}    

function icl_st_author_description_filter($value, $user_id){
    global $sitepress_settings;
    
    if(false === $user_id){
        global $authordata;
        if(empty($authordata->data)) return $value;
        $user_id = $authordata->data->ID;
    }
    
    $user = new WP_User($user_id);        

	if(!isset($sitepress_settings['st']['translated-users'])) $sitepress_settings['st']['translated-users'] = array();

    if ( is_array( $user->roles ) && is_array($sitepress_settings['st']['translated-users']) && array_intersect($user->roles, $sitepress_settings['st']['translated-users'])){
        $value = icl_st_translate_author_fields('description', $value, $user_id);
    }
    
    return $value;
}    

function icl_st_author_displayname_filter($value){
    global $authordata, $sitepress_settings;
    
    if(isset($authordata->ID)){    
        $user = new WP_User($authordata->ID);        
        if ( is_array( $user->roles ) && isset($sitepress_settings['st']['translated-users']) && array_intersect($user->roles, (array)$sitepress_settings['st']['translated-users'])){
            $value = icl_st_translate_author_fields('display_name', $value, isset($authordata->ID)?$authordata->ID:null);
        }
    }
    
    return $value;
}        

function icl_st_translate_author_fields($field, $value, $user_id){
    global $sitepress_settings, $sitepress;
    
    $current_user = $sitepress->get_current_user();
    
    if(empty($user_id)) $user_id = $current_user->ID;
	if(!isset($sitepress_settings['st']['translated-users'])) $sitepress_settings['st']['translated-users'] = array();
    
    $user = new WP_User($user_id);        
    if ( is_array( $user->roles ) && is_array($sitepress_settings['st']['translated-users'])  && array_intersect($user->roles, (array)$sitepress_settings['st']['translated-users'])){
        $value = icl_translate('Authors', $field . '_' . $user_id, $value, true);
    }

    return $value;
}

function icl_st_register_user_strings($user_id){
    global $sitepress_settings;
    
    $user = new WP_User($user_id);        

	if(!isset($sitepress_settings['st']['translated-users'])) $sitepress_settings['st']['translated-users'] = array();

    if ( is_array( $user->roles ) && is_array($sitepress_settings['st']['translated-users'])  && array_intersect($user->roles, (array)$sitepress_settings['st']['translated-users'])){
        $fields = array('first_name', 'last_name', 'nickname', 'description');                  
        foreach($fields as $field){
            icl_register_string('Authors', $field . '_' . $user_id, get_the_author_meta($field, $user_id), true);
        }
        
        icl_register_string('Authors', 'display_name_' . $user_id, $user->display_name, true);    
    }
} 
    
function icl_st_register_user_strings_all(){
    global $wpdb;
    $users = get_users(array('blog_id'=>$wpdb->blogid, 'fields'=>'ID'));
    foreach($users as $uid){
        icl_st_register_user_strings($uid);
    }    
}

function icl_st_update_string_actions( $context, $name, $old_value, $new_value, $force_complete = false ) {
	global $wpdb;
	require_once 'wpml-st-string-update.class.php';

	$string_update = new WPML_ST_String_Update( $wpdb );
	$string_update->update_string( $context, $name, $old_value, $new_value, $force_complete );
}

function icl_st_update_blogname_actions($old, $new){
    icl_st_update_string_actions('WP', 'Blog Title', $old, $new, true );
}

function icl_st_update_blogdescription_actions($old, $new){
    icl_st_update_string_actions('WP', 'Tagline', $old, $new, true );
}

function icl_st_update_widget_title_actions($old_options, $new_options){        
    
    if(isset($new_options['title'])){ // case of 1 instance only widgets
        $buf = $new_options;
        unset($new_options);
        $new_options[0] = $buf;
        unset($buf);
        $buf = $old_options;
        unset($old_options);
        $old_options[0] = $buf;
        unset($buf);        
    }
    
    foreach($new_options as $k=>$o){
        if(isset($o['title'])){
            if(isset($old_options[$k]['title']) && $old_options[$k]['title']){
                icl_st_update_string_actions('Widgets', 'widget title - ' . md5($old_options[$k]['title']), $old_options[$k]['title'], $o['title']);        
            }else{                
                if($new_options[$k]['title']){          
                    icl_register_string('Widgets', 'widget title - ' . md5($new_options[$k]['title']), $new_options[$k]['title']);
                }                
            }            
        }
    }    
}

function icl_st_update_text_widgets_actions($old_options, $new_options){
    global $wpdb;
    
    // remove filter for showing permalinks instead of sticky links while saving
    $GLOBALS['__disable_absolute_links_permalink_filter'] = 1;
    
    $widget_text = get_option('widget_text');    
    if(is_array($widget_text)){
        foreach($widget_text as $k=>$w){
            if(isset($old_options[$k]['text']) && trim($old_options[$k]['text']) && $old_options[$k]['text'] != $w['text']){
                $old_md5 = md5($old_options[$k]['text']);
                $string = $wpdb->get_row($wpdb->prepare("SELECT id, value, status FROM {$wpdb->prefix}icl_strings WHERE context=%s AND name=%s", 'Widgets', 'widget body - ' . $old_md5));    
                if ($string) {
                    icl_st_update_string_actions('Widgets', 'widget body - ' . $old_md5, $old_options[$k]['text'], $w['text']);
                } else {
                    icl_register_string('Widgets', 'widget body - ' . md5($w['text']), $w['text']);
                }
            }elseif(isset($new_options[$k]['text']) && $old_options[$k]['text']!=$new_options[$k]['text']){
                icl_register_string('Widgets', 'widget body - ' . md5($new_options[$k]['text']), $new_options[$k]['text']);
            }
        }
    }

    // add back the filter for showing permalinks instead of sticky links after saving
    unset($GLOBALS['__disable_absolute_links_permalink_filter']);
    
}

function icl_st_get_contexts($status){
    global $wpdb, $sitepress, $sitepress_settings;
    $extra_cond = '';
	$joins = '';
	$results = false;

    $current_user = $sitepress->get_current_user();
    
    if($status !== false){
        if($status == ICL_TM_COMPLETE){
            $extra_cond .= " AND s.status = " . ICL_TM_COMPLETE;
        }else{
            $extra_cond .= " AND s.status IN (" . ICL_STRING_TRANSLATION_PARTIAL . "," . ICL_TM_NEEDS_UPDATE . "," . ICL_TM_NOT_TRANSLATED . ")";
        }        
    }

    if(icl_st_is_translator()){
        $user_langs = get_user_meta($current_user->ID, $wpdb->prefix.'language_pairs', true);

        $active_langs = $sitepress->get_active_languages();
        if(!empty($user_langs[$sitepress_settings['st']['strings_language']])){
            
            foreach($user_langs[$sitepress_settings['st']['strings_language']] as $lang=>$one){
                if(isset($active_langs[$lang])){
                    $lcode_alias = esc_sql(str_replace('-', '', $lang));
                    $joins[] = $wpdb->prepare(" JOIN {$wpdb->prefix}icl_string_translations {$lcode_alias}_str
                                                    ON {$lcode_alias}_str.string_id = s.id AND {$lcode_alias}_str.language= %s
                                                        AND
                    ( 
                        {$lcode_alias}_str.status = " . ICL_TM_WAITING_FOR_TRANSLATOR .
                        " OR {$lcode_alias}_str.translator_id = %d ) " , $lcode_alias, $current_user->ID );
                }            
                
            }            
            
            $sql = "
                SELECT s.context, COUNT(s.context) AS c FROM {$wpdb->prefix}icl_strings s
                ".join("\n", $joins)."
                WHERE 1 {$extra_cond} 
                GROUP BY context
                ORDER BY context ASC
            ";
	        
            $results = $wpdb->get_results($sql);
        }
        
    }else{
        $results = $wpdb->get_results( "
            SELECT context, COUNT(context) AS c
            FROM {$wpdb->prefix}icl_strings s
            WHERE 1 {$extra_cond}
            GROUP BY context 
            ORDER BY context ASC" );
        
    }
    
    return $results;
}

function icl_st_admin_notices(){
    global $icl_st_err_str;
    if($icl_st_err_str){
        echo '<div class="error"><p>' . $icl_st_err_str . '</p></div>';
    }    
}

function icl_st_generate_po_file( $strings ) {

	require_once( WPML_ST_PATH . '/inc/gettext/wpml-po-parser.class.php' );

	$po = WPML_PO_Parser::create_po( $strings );

	return $po;
}

function icl_st_string_in_page($string_id){
    global $wpdb;
    // get urls   
    $urls = $wpdb->get_col($wpdb->prepare("SELECT position_in_page 
                            FROM {$wpdb->prefix}icl_string_positions 
                            WHERE string_id = %d AND kind = %d", $string_id, ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_PAGE));
    if(!empty($urls)){
        $string = $wpdb->get_row($wpdb->prepare("SELECT context, value FROM {$wpdb->prefix}icl_strings WHERE id=%d", $string_id));
        echo '<div id="icl_show_source_top">';
        for($i = 0; $i < count($urls); $i++){
            $c = $i+1;
            if(strpos($urls[$i], '?') !== false){
                $urls[$i] .= '&icl_string_track_value=' . $string->value;
            }else{
                $urls[$i] .= '?icl_string_track_value=' . $string->value;
            }            
            $urls[$i] .= '&icl_string_track_context=' . $string->context;

            echo '<a href="#" onclick="jQuery(\'#icl_string_track_frame_wrap iframe\').attr(\'src\',\''.esc_url($urls[$i]).'\');jQuery(\'#icl_string_track_url a\').html(\''.esc_url($urls[$i]).'\').attr(\'href\',  \''.esc_url($urls[$i]).'\'); return false;">'.$c.'</a><br />';
            
        }
        echo '</div>';
        echo '<div id="icl_string_track_frame_wrap">';        
        echo '<iframe onload="iclResizeIframe()" src="'.$urls[0].'" width="10" height="10" frameborder="0" marginheight="0" marginwidth="0"></iframe>';
        echo '<div id="icl_string_track_url" class="icl_string_track_url"><a href="'.esc_url($urls[0]).'">' . esc_html($urls[0]) . "</a></div>\n";
        echo '</div>';        
    }else{
        _e('No records found', 'wpml-string-translation');
    }
}

function icl_st_string_in_source($string_id){
    global $wpdb, $sitepress_settings;
    // get positions    
    $files = $wpdb->get_col($wpdb->prepare("SELECT position_in_page 
                            FROM {$wpdb->prefix}icl_string_positions 
                            WHERE string_id = %d AND kind = %d", $string_id, ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_SOURCE));
    
    if(!empty($files)){
        echo '<div id="icl_show_source_top">';
        for($i = 0; $i < count($files); $i++){            
            $c = $i+1;
            $exp = explode('::', $files[$i]);
            $line = $exp[1];
            echo '<a href="#" onclick="icl_show_in_source('.$i.','.$line.')">'.$c.'</a><br />';
        }
        echo '</div>';
        echo '<div id="icl_show_source_wrap">';
        for($i = 0; $i < count($files); $i++){            
            $exp = explode('::', $files[$i]);
            $file = $exp[0];
            if(!file_exists($file) || !is_readable($file)) continue;
            $line = $exp[1];
            echo '<div class="icl_string_track_source" id="icl_string_track_source_'.$i.'"';
            if($i > 0){
                echo 'style="display:none"';
            }
            echo '>';
            if($i == 0){
                echo '<script type="text/javascript">icl_show_in_source_scroll_once = ' . $line . '</script>';
            }
            echo '<div class="icl_string_track_filename">' . $file . "</div>\n";
            echo '<pre>';        
            $content = file($file);
            echo '<ol>';
            $hl_color = !empty($sitepress_settings['st']['hl_color'])?$sitepress_settings['st']['hl_color']:'#FFFF00';
            foreach($content as $k=>$l){
                if($k == $line-1){
                    $hl =  ' style="background-color:'.$hl_color.';"';
                }else{
                    $hl = '';   
                }
                echo '<li id="icl_source_line_'.$i.'_'.$k.'"'.$hl.'">' . esc_html($l) . '&nbsp;</li>';
            }
            echo '</ol>';
            echo '</pre>';
            echo '</div>'; 
        }
        echo '</div>';
    }else{
        _e('No records found', 'wpml-string-translation');
    }    
}

function _icl_st_get_options_writes($path){
    static $found_writes = array();
    if(is_dir($path)){        
        $dh = opendir($path);
        while($file = readdir($dh)){
            if($file=="." || $file=="..") continue;
            if(is_dir($path . '/' . $file)){
                _icl_st_get_options_writes($path . '/' . $file);                
            }elseif(preg_match('#(\.php|\.inc)$#i', $file)){
                $content = file_get_contents($path . '/' . $file);
                $int = preg_match_all('#(add|update)_option\(([^,]+),([^)]+)\)#im', $content, $matches);
                if($int){
                    foreach($matches[2] as $m){
                        $option_name = trim($m);
                        if(0 === strpos($option_name, '"') || 0 === strpos($option_name, "'")){
                            $option_name = trim($option_name, "\"'");
                        }elseif(false === strpos($option_name, '$')){
                            if(false !== strpos($option_name, '::')){
                                $cexp = explode('::', $option_name);
                                if (class_exists($cexp[0])){
                                    if (defined($cexp[0].'::'. $cexp[1])){
                                        $option_name = constant($cexp[0].'::'. $cexp[1]);
                                    }
                                }
                            }else{
                                if (defined( $option_name )){
                                    $option_name = constant($option_name);
                                }
                            }                            
                        }else{
                            $option_name = false;
                        }
                        if($option_name){
                            $found_writes[] = $option_name;
                        }
                    }
                }
            }
        }
    } 
    return $found_writes;
}


function __array_unique_recursive($array){
    $scalars = array();
    foreach($array as $key=>$value){
        if(is_scalar($value)){
            if(isset($scalars[$value])){
                unset($array[$key]);
            }else{
                $scalars[$value] = true;    
            }
        }elseif(is_array($value)){
            $array[$key] = __array_unique_recursive($value);
        }
    }   
    return $array; 
}

function _icl_st_filter_empty_options_out($array){
    $empty_found = false;
    foreach($array as $k=>$v){
        if(is_array($v) && !empty($v)){
            list($array[$k], $empty_found) = _icl_st_filter_empty_options_out($v);
        }else{
            if(empty($v)){
                unset($array[$k]);
                $empty_found = true;
            }
        }
    }
    return array($array, $empty_found);
}

function wpml_register_admin_strings($serialized_array){
    try{
        wpml_st_load_admin_texts()->icl_register_admin_options(unserialize($serialized_array));
    }catch(Exception $e){
        trigger_error($e->getMessage(), E_USER_WARNING);
    }
}

function _icl_st_translator_notification($user, $source, $target){
    global $wpdb, $sitepress;
    
    $_ldetails = $sitepress->get_language_details($source);
    $source_en = $_ldetails['english_name'];
    $_ldetails = $sitepress->get_language_details($target);
    $target_en = $_ldetails['english_name'];
    
    $message = __("You have been assigned to a new translation job from %s to %s.

Start editing: %s

You can view your other translation jobs here: %s

 This message was automatically sent by Translation Management running on WPML. To stop receiving these notifications contact the system administrator at %s.

 This email is not monitored for replies.

 - The folks at ICanLocalize
4730 S Fort Apache Rd, Suite 300, Las Vegas, NV 89147-7947, USA
", 'sitepress');
    
    
    $to = $user->user_email;
    $subject = sprintf(__("You have been assigned to a new translation job on %s.", 'sitepress'), get_bloginfo('name'));
    $body = sprintf($message, 
        $source_en, $target_en, admin_url('admin.php?page='.WPML_ST_FOLDER.'/menu/string-translation.php'), 
            admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php'), home_url());

    wp_mail($to, $subject, $body);
    
    $meta = get_user_meta($user->ID, $wpdb->prefix . 'strings_notification', 1);
    $meta[$source][$target] = 1;        
    update_user_meta($user->ID, $wpdb->prefix . 'strings_notification', $meta);
}

function icl_st_reset_current_trasnslator_notifications(){
    global $sitepress, $wpdb;
    $current_user = $sitepress->get_current_user();
    $mkey = $wpdb->prefix . 'strings_notification'; 
    if(!empty($current_user->$mkey)){
        update_user_meta($current_user->ID, $mkey, array());
    }
}

function icl_is_string_translation($translation) {
    // determine if the $translation data is for string translation.
    
    foreach($translation as $key => $value) {
        if($key == 'body' or $key == 'title') {
            return false;
        }
        if (preg_match("/string-(.*)/", $key)){
            return true;
        }
    }
    
    // if we get here assume it's not a string.
    return false;
    
}

function icl_translation_add_string_translation( $rid, $translation, $lang_code ) {
	global $wpdb;
	foreach ( $translation as $key => $value ) {
		if ( preg_match( "/string-(.*)/", $key, $match ) ) {
			$string_id = $match[ 1 ];

            $string_translation_id = $wpdb->get_var($wpdb->prepare("SELECT id
                                                      FROM {$wpdb->prefix}icl_string_translations
                                                      WHERE string_id=%d AND language=%s",
                                                     $string_id, $lang_code ) );
            
			$md5_when_sent        = $wpdb->get_var( $wpdb->prepare( "	SELECT md5
																		FROM {$wpdb->prefix}icl_string_status
                														WHERE rid=%d AND string_translation_id=%d",
			                                                        $rid, $string_translation_id ) );
			$current_string_value = $wpdb->get_var( $wpdb->prepare( "	SELECT value
																		FROM {$wpdb->prefix}icl_strings
																		WHERE id=%d",
			                                                        $string_id ) );
			if ( $md5_when_sent == md5( $current_string_value ) ) {
				$status = ICL_TM_COMPLETE;
			} else {
				$status = ICL_TM_NEEDS_UPDATE;
			}
			$value = str_replace( '&#0A;', "\n", $value );
			icl_add_string_translation( $string_id, $lang_code, html_entity_decode( $value ), $status );
		}
	}

	return true;
}

function icl_st_get_pending_string_translations_stats(){
    global $wpdb, $sitepress, $sitepress_settings;
    
    $current_user = $sitepress->get_current_user();
    
    $user_lang_pairs = get_user_meta($current_user->ID, $wpdb->prefix.'language_pairs', true);    
    
    $stats = array();

    if(!empty($user_lang_pairs[$sitepress_settings['st']['strings_language']])){
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT COUNT(id) AS c, language 
            FROM {$wpdb->prefix}icl_string_translations 
            WHERE status=%d AND language IN (" . wpml_prepare_in(
                                                         array_keys(
                                                             $user_lang_pairs[ $sitepress_settings[ 'st' ][ 'strings_language' ] ]
                                                         )
                                                     ) . ")
                    AND (translator_id IS NULL or translator_id > 0)
            GROUP BY language
            ORDER BY c DESC
            ",
            ICL_TM_WAITING_FOR_TRANSLATOR
        ));
        
        foreach($results as $r){
            $_stats[$r->language] = $r->c;
        }
        
        foreach($user_lang_pairs[$sitepress_settings['st']['strings_language']] as $lang=>$one){
            $stats[$lang] = isset($_stats[$lang]) ? $_stats[$lang] : 0;
        }
    }

    return $stats;
}

function icl_st_is_translator(){
    return current_user_can('translate')  
	&& !current_user_can('manage_options') 
	&& !current_user_can('manage_categories') 
	&& !current_user_can('wpml_manage_string_translation');
}

function icl_st_debug($str){
    trigger_error($str, E_USER_WARNING);
}

function icl_st_admin_notices_string_updated() {
	?>
	<div class="updated">
			<p><?php _e( 'Strings translations updated', 'wpml-string-translation' ); ?></p>
	</div>
	<?php
}
