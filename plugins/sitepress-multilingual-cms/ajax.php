<?php
/**
 * @package wpml-core
 * @used-by Sitepress::ajax_setup
 */


global $wpdb, $sitepress, $sitepress_settings;


if (!isset($_POST['unit-test'])) {
    @header('Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option('blog_charset'));
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Sat, 16 Aug 1980 05:00:00 GMT");
}

$_icl_ajx_actions_no_nonce = array(
		'health_check'             => 1,
		'get_language_status_text' => 1,
		'get_original_comment'     => 1,
		'get_browser_language'     => 1,
		'set_xliff_newlines'       => 1,
);

if ( ! isset( $_icl_ajx_actions_no_nonce[ $_REQUEST[ 'icl_ajx_action' ] ] )
     && ! wp_verify_nonce( $_REQUEST[ '_icl_nonce' ], $_REQUEST[ 'icl_ajx_action' ] . '_nonce' )
     && ! ( isset( $_GET[ 'icl_ajx_action' ] ) && isset( $_GET[ 'nonce' ] ) && $_GET[ 'nonce' ] == wp_create_nonce( $_GET[ 'icl_ajx_action' ] ) )
) {
    die( 'Invalid nonce' );
}

$iclsettings = $this->get_settings();

$default_language = $this->get_default_language();

switch($_REQUEST['icl_ajx_action']){
    case 'health_check':
		$this->set_setting('ajx_health_checked', true, true);
        break;
    case 'set_active_languages':
        $resp = array();
        $old_active_languages_count = count($this->get_active_languages());
        $lang_codes = explode(',',$_POST['langs']);
        if($this->set_active_languages($lang_codes)){
            $resp[0] = 1;
            $active_langs = $this->get_active_languages();
            $iclresponse ='';
            $default_categories = $this->get_default_categories();
            $default_category_main = $wpdb->get_var("SELECT name FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tx ON t.term_id=tx.term_id
                WHERE term_taxonomy_id='{$default_categories[$default_language]}' AND taxonomy='category'");
            $default_category_trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id={$default_categories[$default_language]} AND element_type='tax_category'");

            foreach($active_langs as $lang){
                $is_default = ( $default_language ==$lang['code']);
                $iclresponse .= '<li ';
                if($is_default) $iclresponse .= 'class="default_language"';
                $iclresponse .= '><label><input type="radio" name="default_language" value="' . $lang['code'] .'" ';
                if($is_default) $iclresponse .= 'checked="checked"';
                $iclresponse .= '>' . $lang['display_name'];
                if($is_default) $iclresponse .= ' ('. __('default','sitepress') . ')';
                $iclresponse .= '</label></li>';

                if(!in_array($lang['code'],array_keys($default_categories))){
                   // Create category for language
                   // add it to defaults
                   if($default_category_main == 'Uncategorized'){
                        $this->switch_locale($lang['code']);
                        $tr_cat = __('Uncategorized', 'sitepress');
                        $this->switch_locale();
                        if($tr_cat == 'Uncategorized') $tr_cat .= ' @' . $lang['code'];
                   }else{
                        $tr_cat = $default_category_main . ' @' . $lang['code'];
                   }
                   $_POST['icl_trid'] = $default_category_trid;
                   $_POST['icl_tax_category_language'] = $lang['code'];

                   $tmp = term_exists($tr_cat, 'category');
                   if(!$tmp){
						 $tmp = wp_insert_term($tr_cat, 'category');
						 if (is_wp_error($tmp)) {
							trigger_error($tmp->get_error_message(), E_USER_ERROR);
							continue;
						}
						//during installation sitepress filters are disabled
						//we have to manually add entries to icl_translations table
						$sitepress->create_term( $tmp['term_id'], $tmp['term_taxonomy_id'] );
                   }
                   $default_categories[$lang['code']] = $tmp['term_taxonomy_id'];
                }
            }
            $this->set_default_categories($default_categories) ;

            $resp[1] = $iclresponse;
            // response 1 - blog got more than 2 languages; -1 blog reduced to 1 language; 0 - no change
            if(count($lang_codes) > 1){
                if(!$this->get_setting('setup_complete')){
                    $resp[2] = -2; //don't refresh the page and enable 'next'
                }else{
                    $resp[2] = 1;
                }
            }elseif($old_active_languages_count > 1 && count($lang_codes) < 2){
                if(!$this->get_setting('setup_complete')){
                    $resp[2] = -3; //don't refresh the page and disable 'next'
                }else{
                    $resp[2] = -1;
                }
            }else{
                if(!$this->get_setting('setup_complete')){
                    $resp[2] = -3; //don't refresh the page and disable 'next'
                }else{
                    $resp[2] = 0;
                }
            }
            if(count($active_langs) > 1){
                $iclsettings['dont_show_help_admin_notice'] = true;
                $this->save_settings($iclsettings);
            }
        }else{
            $resp[0] = 0;
        }

        if(empty($iclsettings['setup_complete'])){
            $iclsettings['setup_wizard_step'] = 3;
            $this->save_settings($iclsettings);
        }

        echo join('|',$resp);
        do_action('icl_update_active_languages');
        break;
    case 'set_default_language':
        $previous_default = $default_language;
        if($response = $this->set_default_language($_POST['lang'])){
            echo '1|'.$previous_default.'|';
        }else{
            echo'0||' ;
        }
        if(1 === $response){
            echo __('WordPress language file (.mo) is missing. Keeping existing display language.', 'sitepress');
        }
        break;
    case 'set_languages_order':
        $iclsettings['languages_order'] = explode(';', $_POST['order']);
        $this->save_settings($iclsettings);
        echo json_encode(array('message' => __('Languages order updated', 'sitepress')));
        break;
    case 'icl_tdo_options':
        $iclsettings['translated_document_status'] = intval($_POST['icl_translated_document_status']);
        $iclsettings['translated_document_page_url'] = $_POST['icl_translated_document_page_url'];
        $this->save_settings($iclsettings);
        echo '1|';
       break;
    case 'icl_save_language_negotiation_type':
        $iclsettings['language_negotiation_type'] = $_POST['icl_language_negotiation_type'];
        if(!empty($_POST['language_domains'])){
            $iclsettings['language_domains'] = $_POST['language_domains'];
        }
        if($iclsettings['language_negotiation_type'] == 1){
            $iclsettings['urls']['directory_for_default_language'] = isset($_POST['use_directory']) ? intval($_POST['use_directory']) : 0;
            if($iclsettings['urls']['directory_for_default_language']){
                $iclsettings['urls']['show_on_root']   = isset($_POST['use_directory']) ? $_POST['show_on_root'] : '';
                if($iclsettings['urls']['show_on_root'] == 'html_file'){
                    $iclsettings['urls']['root_html_file_path'] = isset($_POST['root_html_file_path']) ? $_POST['root_html_file_path'] : '';
                }else{
                    $iclsettings['urls']['hide_language_switchers'] = isset($_POST['hide_language_switchers']) ? $_POST['hide_language_switchers'] : 0;
                }
            }
        }
        $this->save_settings($iclsettings);
        echo 1;
        break;
    case 'icl_save_language_switcher_options':
        $_POST   = stripslashes_deep( $_POST );

	    if ( isset( $_POST[ 'icl_language_switcher_sidebars' ] ) ) {
		    global $wp_registered_widgets, $wp_registered_sidebars;
		    $widget_icl_lang_sel_widget = get_option( 'widget_icl_lang_sel_widget' );
		    $counter                    = is_array( $widget_icl_lang_sel_widget ) ? max( array_keys( $widget_icl_lang_sel_widget ) ) : 0;
		    if ( ! is_numeric( $counter ) || $counter<=0 ) {
			    $counter = 1;
		    }

		    $language_switcher_name            = 'icl_lang_sel_widget';
		    $language_switcher_prefix          = $language_switcher_name . '-';
		    $active_widgets                    = get_option( 'sidebars_widgets' );
		    $posted_language_switcher_sidebars = $_POST[ 'icl_language_switcher_sidebars' ];
		    $update_sidebars_widgets           = false;
		    foreach ( $posted_language_switcher_sidebars as $target_sidebar_id => $add_widget ) {
			    $widget_exists = false;
			    if(isset($active_widgets[ $target_sidebar_id ])) {
				    $active_sidebar_widgets = $active_widgets[ $target_sidebar_id ];
				    foreach ( $active_sidebar_widgets as $index => $active_sidebar_widget ) {
					    if ( strpos( $active_sidebar_widget, $language_switcher_prefix ) !== false ) {
						    $widget_exists = true;
						    break;
					    }
				    }
			    }
			    if($add_widget && !$widget_exists) {
				    if(isset($active_widgets[ $target_sidebar_id ])) {
					    $active_sidebar_widgets = $active_widgets[ $target_sidebar_id ];
					    array_unshift( $active_sidebar_widgets, $language_switcher_prefix . $counter );
				    } else {
					    $active_sidebar_widgets = array();
					    $active_sidebar_widgets[] = $language_switcher_prefix . $counter;
				    }
				    $language_switcher_content             = get_option( 'widget_' . $language_switcher_name );
				    $language_switcher_content[ $counter ] = array( 'title_show' => 0 );
				    if ( ! array_key_exists( '_multiwidget', $language_switcher_content ) ) {
					    $language_switcher_content[ '_multiwidget' ] = 1;
				    }
				    update_option( 'widget_' . $language_switcher_name, $language_switcher_content );
				    $counter ++;
				    $active_widgets[ $target_sidebar_id ] = $active_sidebar_widgets;
				    $update_sidebars_widgets              = true;
			    }elseif(!$add_widget && $widget_exists) {
				    foreach ( $active_sidebar_widgets as $index => $active_sidebar_widget ) {
					    if ( strpos( $active_sidebar_widget, $language_switcher_prefix ) !== false ) {
						    unset( $active_widgets[ $target_sidebar_id ][ $index ] );
						    $update_sidebars_widgets = true;
					    }
				    }
			    }
		    }
		    if ( $update_sidebars_widgets ) {
			    wp_set_sidebars_widgets( $active_widgets );
		    }
	    }

        $iclsettings['icl_lso_link_empty'] = @intval($_POST['icl_lso_link_empty']);
        $iclsettings['icl_lso_flags'] = isset($_POST['icl_lso_flags']) ? @intval($_POST['icl_lso_flags']) : 0;
        $iclsettings['icl_lso_native_lang'] = @intval($_POST['icl_lso_native_lang']);
        $iclsettings['icl_lso_display_lang'] = @intval($_POST['icl_lso_display_lang']);

        if(!$this->get_setting('setup_complete')){
            $iclsettings['setup_wizard_step'] = 4;
            //$iclsettings['setup_complete'] = 1;
            if(isset($iclsettings['setup_reset'])) unset($iclsettings['setup_reset']);

            $active_languages = $this->get_active_languages();
            foreach($active_languages as $code=>$lng){
                if($code != $default_language ){
                    if($this->_validate_language_per_directory($code)){
                        $iclsettings['language_negotiation_type'] = 1;
                    }
                    break;
                }
            }

        }

        if(isset($_POST['icl_lang_sel_config'])){
            $iclsettings['icl_lang_sel_config'] = $_POST['icl_lang_sel_config'];
        }

        if(isset($_POST['icl_lang_sel_footer_config'])){
            $iclsettings['icl_lang_sel_footer_config'] = $_POST['icl_lang_sel_footer_config'];
        }

        if (isset($_POST['icl_lang_sel_type']))
            $iclsettings['icl_lang_sel_type'] = $_POST['icl_lang_sel_type'];
        if (isset($_POST['icl_lang_sel_stype']))
            $iclsettings['icl_lang_sel_stype'] = $_POST['icl_lang_sel_stype'];

        if($iclsettings['icl_lang_sel_type'] == 'list'){
            $iclsettings['icl_lang_sel_orientation'] = $_POST['icl_lang_sel_orientation'];
        }

        if (isset($_POST['icl_lang_sel_footer']))
            $iclsettings['icl_lang_sel_footer'] = 1;
        else $iclsettings['icl_lang_sel_footer'] = 0;

        if (isset($_POST['icl_post_availability']))
            $iclsettings['icl_post_availability'] = 1;
        else $iclsettings['icl_post_availability'] = 0;

        if (isset($_POST['icl_post_availability_position']))
            $iclsettings['icl_post_availability_position'] = $_POST['icl_post_availability_position'];

        if (isset($_POST['icl_post_availability_text']))
            $iclsettings['icl_post_availability_text'] = $_POST['icl_post_availability_text'];

        $iclsettings['icl_widget_title_show'] = (isset($_POST['icl_widget_title_show'])) ? 1 : 0;
        $iclsettings['icl_additional_css'] = $_POST['icl_additional_css'];

        $iclsettings['display_ls_in_menu'] = @intval($_POST['display_ls_in_menu']);
        $iclsettings['menu_for_ls'] = @intval($_POST['menu_for_ls']);

        $iclsettings['icl_lang_sel_copy_parameters'] = join(', ', array_map('trim', explode(',', $_POST['copy_parameters'])));

        if(!$iclsettings['icl_lso_flags'] && !$iclsettings['icl_lso_native_lang'] && !$iclsettings['icl_lso_display_lang']){
            echo '0|';
            echo __('At least one of the language switcher style options needs to be checked', 'sitepress');
        }else{
            $this->save_settings($iclsettings);
            echo 1;
        }
        break;
    
    case 'registration_form_submit':
        
        $ret['error'] = '';
        
        if($_POST['button_action'] == 'later'){
            
            //success
            $ret['success'] = sprintf(__('WPML will work on your site, but you will not receive updates. WPML updates are essential for keeping your site running smoothly and secure. To receive automated updates, you need to complete the registration, in the %splugins admin%s page.', 'sitepress'), 
                '<a href="' . admin_url('plugin-install.php?tab=commercial') . '">', '</a>');
            
            
        }elseif($_POST['button_action'] == 'finish'){
            
            $iclsettings['setup_complete'] = 1;        
            
        }else{
        
            if(empty($_POST['installer_site_key'])){
                $ret['error'] = __('Missing site key.');
            }else{
                
                $iclsettings['site_key'] = $_POST['installer_site_key'];
                
                if(class_exists('WP_Installer')){
                    $args['repository_id'] = 'wpml';
                    $args['nonce'] = wp_create_nonce('save_site_key_' . $args['repository_id']) ;
                    $args['site_key'] = $_POST['installer_site_key'];
                    $args['return']   = 1;
                    $r = WP_Installer()->save_site_key($args);    
                    if(!empty($r['error'])){
                        $ret['error'] = $r['error'];
                        
                    }else{
                        
                        //success
                        $ret['success'] = __('Thank you for registering WPML on this site. You will receive automatic updates when new versions are available.', 'sitepress');
                    }
                }
                
            }
        }
        
        if(!empty($iclsettings)){
            $this->save_settings($iclsettings);    
        }
        
        
        echo json_encode($ret);
    
        break;
    
    case 'icl_admin_language_options':
        $iclsettings['admin_default_language'] = $_POST['icl_admin_default_language'];
        $this->save_settings($iclsettings);
        $this->icl_locale_cache->clear();
        echo 1;
        break;
    case 'icl_blog_posts':
        $iclsettings['show_untranslated_blog_posts'] = $_POST['icl_untranslated_blog_posts'];
        $this->save_settings($iclsettings);
        echo 1;
        break;
    case 'icl_page_sync_options':
        $iclsettings['sync_page_ordering'] = @intval($_POST['icl_sync_page_ordering']);
        $iclsettings['sync_page_parent'] = @intval($_POST['icl_sync_page_parent']);
        $iclsettings['sync_page_template'] = @intval($_POST['icl_sync_page_template']);
        $iclsettings['sync_comment_status'] = @intval($_POST['icl_sync_comment_status']);
        $iclsettings['sync_ping_status'] = @intval($_POST['icl_sync_ping_status']);
        $iclsettings['sync_sticky_flag'] = @intval($_POST['icl_sync_sticky_flag']);
        $iclsettings['sync_private_flag'] = @intval($_POST['icl_sync_private_flag']);
        $iclsettings['sync_post_format'] = @intval($_POST['icl_sync_post_format']);
        $iclsettings['sync_delete'] = @intval($_POST['icl_sync_delete']);
        $iclsettings['sync_delete_tax'] = @intval($_POST['icl_sync_delete_tax']);
        $iclsettings['sync_post_taxonomies'] = @intval($_POST['icl_sync_post_taxonomies']);
        $iclsettings['sync_post_date'] = @intval($_POST['icl_sync_post_date']);
        $iclsettings['sync_taxonomy_parents'] = @intval($_POST['icl_sync_taxonomy_parents']);
        $iclsettings['sync_comments_on_duplicates'] = @intval($_POST['icl_sync_comments_on_duplicates']);
        $this->save_settings($iclsettings);
        echo 1;
        break;
    case 'language_domains':
        $active_languages = $this->get_active_languages();
        
        $language_domains = isset($iclsettings['language_domains']) ? $iclsettings['language_domains'] : false;
        echo '<table class="language_domains">';
        foreach($active_languages as $lang){
            $home = get_site_url();
            if($lang['code']!= $default_language ){
                if(isset($language_domains[$lang['code']])){
                    $sugested_url = $language_domains[$lang['code']];
                }else{
                    $url_parts = parse_url($home);
                    $exp = explode('.' , $url_parts['host']);
                    if(count($exp) < 3){
                        $sugested_url = $url_parts['scheme'] . '://' . $lang['code'] . '.' . $url_parts['host'] . @strval($url_parts['path']);
                    }else{
                        array_shift($exp);
                        $sugested_url = $url_parts['scheme'] . '://' . $lang['code'] . '.' . join('.' , $exp) . @strval($url_parts['path']);
                    }
                }
            }

            echo '<tr>';
            echo '<td>' . $lang['display_name'] . '</td>';
            if($lang['code']== $default_language ){
                echo '<td id="icl_ln_home">' . $home . '</td>';
                echo '<td>&nbsp;</td>';
                echo '<td>&nbsp;</td>';
            }else{
                echo '<td><input type="text" id="language_domain_'.$lang['code'].'" name="language_domains['.$lang['code'].']" value="'.$sugested_url.'" size="40" /></td>';
                echo '<td id="icl_validation_result_'.$lang['code'].'"><label><input class="validate_language_domain" type="checkbox" name="validate_language_domains[]" value="'.$lang['code'].'" checked="checked" /> ' . __('Validate on save', 'sitepress') . '</label></td><td><span id="ajx_ld_'.$lang['code'].'"></span></td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        break;
    case 'validate_language_domain':
        if(false === strpos($_POST['url'],'?')){$url_glue='?';}else{$url_glue='&';}
        $url = $_POST['url'] . $url_glue . '____icl_validate_domain=1';
        $client = new WP_Http();
        $response = $client->request($url, 'timeout=15');
        if(!is_wp_error($response) && ($response['response']['code']=='200') && ($response['body'] == '<!--'.get_home_url().'-->')){
            echo 1;
        }else{
            echo 0;
        }
        break;
    case 'send_translation_request':

        global $iclTranslationManagement, $current_user;
        $post_ids = explode(',',$_POST['post_ids']);
        $target_languages = explode('#', $_POST['target_languages']);
        $target_translators = explode('#', $_POST['translators']);
        $post_types = $_POST['icl_post_type'];

        get_currentuserinfo();
        $translator_id = isset($_POST['translator_id']) ? $_POST['translator_id'] : $current_user->ID;
        foreach($post_ids as $post_id){

            if(isset($_POST['tn_note_'.$post_id]) && trim($_POST['tn_note_'.$post_id])){
                update_post_meta($post_id, '_icl_translator_note', $_POST['tn_note_'.$post_id]);
            }
            foreach($target_languages as $to_lang){
                $from_lang = $wpdb->get_var($wpdb->prepare("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s",
                    $post_id, 'post_'.$post_types[$post_id]));

                foreach($target_languages as $index => $_lg){
                    $translator[$_lg] = preg_replace('#-'.$_POST['service'].'$#', '', $target_translators[$index]) ;
                }

				if(isset($translator)) {
					$data = array(
						'translate_from'    => $from_lang,
						'translate_to'      => array($to_lang=>1),
						'iclpost'           => array($post_id),
						'service'           => 'icanlocalize',
						'iclnonce'          => wp_create_nonce('pro-translation-icl'),
						'translator'        => $translator
					);

					$jd = $iclTranslationManagement->send_jobs($data);
					$resp[] = array(
						'post_id' => $post_id,
						'status'  => !empty($jd)
					);
				} else {
					$resp = false;
				}
            }
        }
        echo json_encode($resp);
        break;
    case 'get_translator_status':
        if(!$this->icl_account_configured()) break;

        

        if(isset($_POST['cache'])) {
            $last_call = @intval($iclsettings['last_get_translator_status_call']);
            if (time() - $last_call < 24 * 60 * 60) {
                break;
          }
        }

        $iclsettings['last_get_translator_status_call'] = time();
        $this->get_icl_translator_status($iclsettings);
        $this->save_settings($iclsettings);

        echo @json_encode($iclsettings['icl_lang_status']);
        break;
    case 'get_language_status_text':

        if(!$this->icl_account_configured()) break;

        

        if(!isset($_POST['cache'])) {
            
            $this->get_icl_translator_status($iclsettings);
            $this->save_settings($iclsettings);
        }

        echo '1|' . intval($_POST['id']) . '|' . $this->get_language_status_text($_POST['from_lang'], $_POST['to_lang']);
        break;
    case 'icl_theme_localization_type':
        $icl_tl_type = @intval($_POST['icl_theme_localization_type']);
        $iclsettings['theme_localization_type'] = $icl_tl_type;
        $iclsettings['theme_localization_load_textdomain'] = @intval($_POST['icl_theme_localization_load_td']);
        $iclsettings['gettext_theme_domain_name'] = $_POST['textdomain_value'];
        if($icl_tl_type==1){
            icl_st_scan_theme_files();
        }elseif($icl_tl_type==2){
            $parent_theme = get_template_directory();
            $child_theme = get_stylesheet_directory();
            $languages_folders = array();

            if($found_folder = icl_tf_determine_mo_folder($parent_theme)){
                $languages_folders['parent'] = $found_folder;
            }
            if($parent_theme != $child_theme && $found_folder = icl_tf_determine_mo_folder($child_theme)){
                $languages_folders['child'] = $found_folder;
            }
            $iclsettings['theme_language_folders'] = $languages_folders;

        }
        $this->save_settings($iclsettings);
        echo '1|'.$icl_tl_type;
        break;
    case 'icl_ct_user_pref':
        $users = $wpdb->get_col("SELECT id FROM {$wpdb->users}");
        foreach($users as $uid){
            if(isset($_POST['icl_enable_comments_translation'][$uid])){
                update_user_meta($uid, 'icl_enable_comments_translation', 1);
            }else{
                delete_user_meta($uid, 'icl_enable_comments_translation');
            }
            if(isset($_POST['icl_enable_replies_translation'][$uid])){
                update_user_meta($uid, 'icl_enable_replies_translation', 1);
            }else{
                delete_user_meta($uid, 'icl_enable_replies_translation');
            }
        }
        echo '1|';
        break;
    case 'get_original_comment':
        $comment_id = $_POST['comment_id'];
        $trid = $this->get_element_trid($comment_id, 'comment');
        $res = $wpdb->get_row($wpdb->prepare("SELECT element_id, language_code FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND element_type='comment' AND element_id <> %d ", $trid, $comment_id));
        $original_cid = $res->element_id;
        $comment = $wpdb->get_row("SELECT * FROM {$wpdb->comments} WHERE comment_ID={$original_cid}");
        $comment->language_code = $res->language_code;
        if($res->language_code == $IclCommentsTranslation->user_language){
            $comment->translated_version = 1;
        }else{
            $comment->translated_version = 0;
            $comment->anchor_text = __('Back to translated version', 'sitepress');
        }
        echo json_encode($comment);
        break;
    case 'dismiss_help':
        $iclsettings['dont_show_help_admin_notice'] = true;
        $this->save_settings($iclsettings);
        break;
    case 'dismiss_page_estimate_hint':
        $iclsettings['dismiss_page_estimate_hint'] = !$this->get_setting('dismiss_page_estimate_hint');
        $this->save_settings($iclsettings);
        break;
    case 'toggle_pt_controls':
        $iclsettings['hide_professional_translation_controls'] = $_POST['value'];
        $this->save_settings($iclsettings);
        break;
    case 'dismiss_upgrade_notice':
        $iclsettings['hide_upgrade_notice'] = implode('.', array_slice(explode('.', ICL_SITEPRESS_VERSION), 0, 3));
        $this->save_settings($iclsettings);
        break;
    case 'setup_got_to_step1':
        $iclsettings['existing_content_language_verified'] = 0;
        $iclsettings['setup_wizard_step'] = 1;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}icl_translations");
        $wpdb->update($wpdb->prefix . 'icl_languages', array('active' => 0), array('active' => 1));
        $this->save_settings($iclsettings);

        break;
    case 'setup_got_to_step2':
        $iclsettings['setup_wizard_step'] = 2;
        $this->save_settings($iclsettings);
        break;
    case 'toggle_show_translations':
        
        $iclsettings['show_translations_flag'] = @intval(!$iclsettings['show_translations_flag']);
        $this->save_settings($iclsettings);
        break;
    case 'icl_messages':
        
        if(!$this->get_setting('icl_disable_reminders')) break;

        if(!empty($iclsettings['site_id']) && !empty($iclsettings['access_key']) && empty($iclsettings['icl_anonymous_user'])){
            $iclq = new ICanLocalizeQuery($iclsettings['site_id'], $iclsettings['access_key']);

            $output = '';

            if (isset($_POST['refresh']) && $_POST['refresh'] == 1) {
                $reminders = $iclq->get_reminders(true);
            } else {
                $reminders = $iclq->get_reminders();
            }

            $count = 0;
            foreach($reminders as $r) {
                $message = $r->message;
                $message = str_replace('[', '<', $message);
                $message = str_replace(']', '>', $message);
                $url = $r->url;
                $anchor_pos = strpos($url, '#');
                if ($anchor_pos !== false) {
                    $url = substr($url, 0, $anchor_pos);
                }

                if(false !== strpos($url,'?')){
                    $url_glue = '&';
                }else{
                    $url_glue = '?accesskey='.$this->get_setting('access_key') . '&compact=1';
                }
                $output .= $message . ' - ' . $this->create_icl_popup_link(ICL_API_ENDPOINT. $url . $url_glue . '&message_id=' . $r->id. '&TB_iframe=true') . __('View', 'sitepress') . '</a>';

                if ($r->can_delete == '1') {
                    $on_click = 'dismiss_message(' . $r->id . ', \'' . wp_create_nonce('icl_delete_message_nonce') . '\');';

                    $output .= ' - <a href="#" onclick="'. $on_click . '">Dismiss</a>';
                }
                $output .= '<br />';

                $count += 1;
                if ($count > 5) {
                    break;
                }

            }

            if ($output != '') {
                $reminder_count = sizeof($reminders);
                if ($reminder_count == 1){
                    $reminder_text = __('Show 1 reminder', 'sitepress');
                } else {
                    $reminder_text = sprintf(__('Show %d reminders', 'sitepress'), $reminder_count);
                }
                $resp = array('messages'=>$reminder_count, 'reminder_text' => $reminder_text, 'output'=>$output);
            } else {
                $resp = array('messages'=>0);
            }
        }else{
            $resp = array('messages'=>0);
        }
        echo json_encode($resp);
        break;
    case 'icl_delete_message':
        
        $iclq = new ICanLocalizeQuery($iclsettings['site_id'], $iclsettings['access_key']);
        $iclq->delete_message($_POST['message_id']);
        break;
    case 'icl_show_reminders':
        switch($_POST['state']){
            case 'show':
                $iclsettings['icl_show_reminders'] = 1;
                break;
            case 'hide':
                $iclsettings['icl_show_reminders'] = 0;
                break;
            case 'close':
                $iclsettings['icl_disable_reminders'] = 1;
                break;
            default: // nothing
        }
        $iclsettings['icl_show_reminders'] = $_POST['state']=='show'?1:0;
        $this->save_settings($iclsettings);
        break;
    case 'icl_help_links':
        
        $iclq = new ICanLocalizeQuery($iclsettings['site_id'], $iclsettings['access_key']);
        $links = $iclq->get_help_links();
        $lang = $iclsettings['admin_default_language'];
        if (!isset($links['resources'][$lang])) {
            $lang = 'en';
        }

        if (isset($links['resources'][$lang])) {
            $output = '<ul>';
            foreach( $links['resources'][$lang]['resource'] as $resource) {
                if (isset($resource['attr'])) {
                    $title = $resource['attr']['title'];
                    $url = $resource['attr']['url'];
                    $icon = $resource['attr']['icon'];
                    $icon_width = $resource['attr']['icon_width'];
                    $icon_height = $resource['attr']['icon_height'];
                } else {
                    $title = $resource['title'];
                    $url = $resource['url'];
                    $icon = $resource['icon'];
                    $icon_width = $resource['icon_width'];
                    $icon_height = $resource['icon_height'];
                }
                $output .= '<li>';
                if ($icon) {
                    $output .= '<img style="vertical-align: bottom; padding-right: 5px;" src="' . $icon . '"';
                    if ($icon_width) {
                        $output .= ' width="' . $icon_width . '"';
                    }
                    if ($icon_height) {
                        $output .= ' height="' . $icon_height . '"';
                    }
                    $output .= '>';
                }
                $output .= '<a href="' . $url . '">' . $title . '</a></li>';

            }
            $output .= '</ul>';
            echo '1|' . $output;
        } else {
            echo '0|';
        }
        break;
    case 'icl_show_sidebar':
        $iclsettings['icl_sidebar_minimized'] = $_POST['state']=='hide'?1:0;
        $this->save_settings($iclsettings);
        break;
    case 'icl_promote_form':
        $iclsettings['promote_wpml'] = @intval($_POST['icl_promote']);
        $this->save_settings($iclsettings);
        echo '1|';
        break;
    case 'save_translator_note':
        update_post_meta($_POST['post_id'], '_icl_translator_note', $_POST['note']);
        break;
    case 'icl_st_track_strings':
        foreach($_POST['icl_st'] as $k=>$v){
            $iclsettings['st'][$k] = $v;
        }
		if(isset($iclsettings)) {
        	$this->save_settings($iclsettings);
		}
        echo 1;
        break;
    case 'icl_st_more_options':
        $iclsettings['st']['translated-users'] = !empty($_POST['users']) ? array_keys($_POST['users']) : array();
        $this->save_settings($iclsettings);
        if(!empty($iclsettings['st']['translated-users'])){
            $sitepress_settings['st']['translated-users'] = $iclsettings['st']['translated-users'];
            icl_st_register_user_strings_all();
        }
        echo 1;
        break;
    case 'icl_st_ar_form':
        // Auto register string settings.
        $iclsettings['st']['icl_st_auto_reg'] = $_POST['icl_auto_reg_type'];
        $this->save_settings($iclsettings);
        echo 1;
        break;
    case 'affiliate_info_check':
        if( $this->icl_account_configured()
            && ($iclq = new ICanLocalizeQuery($this->get_setting('site_id'), $this->get_setting('access_key')))
            && $iclq->test_affiliate_info($_POST['icl_affiliate_id'], $_POST['icl_affiliate_key'])){
            $error = array('error'=>0);
        }else{
            $error = array('error'=>1);
        }
        echo json_encode($error);
        break;
    case 'icl_hide_languages':
        $iclsettings['hidden_languages'] = empty($_POST['icl_hidden_languages']) ? array() : $_POST['icl_hidden_languages'];
        $this->set_setting('hidden_languages', array()); //reset current value
        $active_languages = $this->get_active_languages();
        if(!empty($iclsettings['hidden_languages'])){
             if(1 == count($iclsettings['hidden_languages'])){
                 $out = sprintf(__('%s is currently hidden to visitors.', 'sitepress'),
                    $active_languages[$iclsettings['hidden_languages'][0]]['display_name']);
             }else{
                 foreach($iclsettings['hidden_languages'] as $l){
                     $_hlngs[] = $active_languages[$l]['display_name'];
                 }
                 $hlangs = join(', ', $_hlngs);
                 $out = sprintf(__('%s are currently hidden to visitors.', 'sitepress'), $hlangs);
             }
             $out .= ' ' . sprintf(__('You can enable its/their display for yourself, in your <a href="%s">profile page</a>.', 'sitepress'),
                                            'profile.php#wpml');
        } else {
            $out = __('All languages are currently displayed.', 'sitepress');
        }
        $this->save_settings($iclsettings);
        echo '1|'.$out;
        break;
    case 'icl_adjust_ids':
        $iclsettings['auto_adjust_ids'] = @intval($_POST['icl_adjust_ids']);
        $this->save_settings($iclsettings);
        echo '1|';
        break;
    case 'icl_automatic_redirect':
		if (!isset($_POST['icl_remember_language']) || $_POST['icl_remember_language'] < 24) {
			$_POST['icl_remember_language'] = 24;
		}
        $iclsettings['automatic_redirect'] = @intval($_POST['icl_automatic_redirect']);
        $iclsettings['remember_language'] = @intval($_POST['icl_remember_language']);
        $this->save_settings($iclsettings);
        echo '1|';
        break;
    case 'icl_troubleshooting_more_options':
        $iclsettings['troubleshooting_options'] = $_POST['troubleshooting_options'];
        $this->save_settings($iclsettings);
        echo '1|';
        break;
    case 'reset_languages':
		$langs_names = icl_get_languages_names();
		$lang_codes = icl_get_languages_codes();
        $lang_locales = icl_get_languages_locales();

        $active = $wpdb->get_col("SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1");

        $wpdb->query("TRUNCATE TABLE `{$wpdb->prefix}icl_languages`"); // @since 3.1.5 - mysql_* function deprecated in php 5.5+
        $wpdb->query("TRUNCATE TABLE `{$wpdb->prefix}icl_languages_translations`");
        $wpdb->query("TRUNCATE TABLE `{$wpdb->prefix}icl_flags`");

        foreach($langs_names as $key=>$val){
            if(strpos($key,'Norwegian Bokm')===0){ $key = 'Norwegian Bokmål'; $lang_codes[$key] = 'nb';} // exception for norwegian
            $default_locale = isset($lang_locales[$lang_codes[$key]]) ? $lang_locales[$lang_codes[$key]] : '';
            @$wpdb->insert($wpdb->prefix . 'icl_languages', array('english_name'=>$key, 'code'=>$lang_codes[$key], 'major'=>$val['major'], 'active'=>0, 'default_locale'=>$default_locale));
        }

        //restore active
        $wpdb->query("UPDATE {$wpdb->prefix}icl_languages SET active=1 WHERE code IN('".join("','",$active)."')");

        foreach($langs_names as $lang=>$val){
            if(strpos($lang,'Norwegian Bokm')===0){ $lang = 'Norwegian Bokmål'; $lang_codes[$lang] = 'nb';}
            foreach($val['tr'] as $k=>$display){
                if(strpos($k,'Norwegian Bokm')===0){ $k = 'Norwegian Bokmål';}
                if(!trim($display)){$display = $lang;}
                if(!($wpdb->get_var("SELECT id FROM {$wpdb->prefix}icl_languages_translations WHERE language_code='{$lang_codes[$lang]}' AND display_language_code='{$lang_codes[$k]}'"))){
                    $wpdb->insert($wpdb->prefix . 'icl_languages_translations',
                    array('language_code'=>$lang_codes[$lang], 'display_language_code'=>$lang_codes[$k], 'name'=>$display));
                }
            }
        }
        $wpdb->update($wpdb->prefix.'icl_flags', array('from_template'=>0),null);

        $codes = $wpdb->get_col("SELECT code FROM {$wpdb->prefix}icl_languages");
        foreach($codes as $code){
            if(!$code || $wpdb->get_var("SELECT lang_code FROM {$wpdb->prefix}icl_flags WHERE lang_code='{$code}'")) continue;
            if(!file_exists(ICL_PLUGIN_PATH.'/res/flags/'.$code.'.png')){
                $file = 'nil.png';
            }else{
                $file = $code.'.png';
            }
            $wpdb->insert($wpdb->prefix.'icl_flags', array('lang_code'=>$code, 'flag'=>$file, 'from_template'=>0));
        }

        icl_cache_clear();
        break;
    case 'icl_support_update_ticket':
        if (isset($_POST['ticket'])) {
            $temp = str_replace('icl_support_ticket_', '', $_POST['ticket']);
            $temp = explode('_', $temp);
            $id = (int)$temp[0];
            $num = (int)$temp[1];
            if ($id && $num) {
                if (isset($iclsettings['icl_support']['tickets'][$id])) {
                    $iclsettings['icl_support']['tickets'][$id]['messages'] = $num;
                    $this->save_settings($iclsettings);
                }
            }
        }
        break;
    case 'icl_custom_tax_sync_options':
        if(!empty($_POST['icl_sync_tax'])){
            foreach($_POST['icl_sync_tax'] as $k=>$v){
                $iclsettings['taxonomies_sync_option'][$k] = $v;
                if($v){
                    $this->verify_taxonomy_translations($k);
                }
            }
			if ( isset( $iclsettings ) ) {
				$this->save_settings($iclsettings);
			}
        }
        echo '1|';
        break;
    case 'icl_custom_posts_sync_options':

        if(!empty($_POST['icl_sync_custom_posts'])){
            foreach($_POST['icl_sync_custom_posts'] as $k=>$v){
                $iclsettings['custom_posts_sync_option'][$k] = $v;
                if($v){
                    $this->verify_post_translations($k);
                }
            }

			$posts_slug_translation = $this->get_setting( 'posts_slug_translation' );
			if(isset($posts_slug_translation['on']) && $posts_slug_translation['on']){
                if(isset($_POST['translate_slugs']) && !empty($_POST['translate_slugs'])){

                    foreach($_POST['translate_slugs'] as $type => $data){

												$iclsettings['posts_slug_translation']['types'][$type] = isset($data['on']) ? intval(!empty($data['on'])) : false;

                        //if(empty($_POST['icl_sync_custom_posts'][$type])) continue;

                        //$iclsettings['posts_slug_translation']['types'][$type] = intval(!empty($data['on']));

                        if(empty($iclsettings['posts_slug_translation']['types'][$type])) continue;

                        // assume it is already registered
                        $post_type_obj = get_post_type_object($type);
                        $slug = $post_type_obj->rewrite['slug'];
                        $string_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}icl_strings WHERE name = %s AND value = %s ", 'URL slug: ' . $slug, $slug));
                        if(empty($string_id)){
                            $string_id = icl_register_string('WordPress', 'URL slug: ' . $slug, $slug);
                        }
                        if($string_id){
                            foreach($this->get_active_languages() as $lang){
															$string_translation_settings = $this->get_setting( 'st' );
															if($lang['code'] != $string_translation_settings['strings_language']){
                                    // allow '/' in slugs
                                    //$data['langs'][$lang['code']] = sanitize_title_with_dashes($data['langs'][$lang['code']]);
                                    $data['langs'][$lang['code']] = join('/', array_map('sanitize_title_with_dashes', explode('/', $data['langs'][$lang['code']])));
                                    $data['langs'][$lang['code']] = urldecode($data['langs'][$lang['code']]);
                                    icl_add_string_translation($string_id, $lang['code'], $data['langs'][$lang['code']] , ICL_STRING_TRANSLATION_COMPLETE);
                                }
                            }
                            icl_update_string_status($string_id);
                        }
                    }
                }
            }

			if ( isset( $iclsettings ) ) {
				$this->save_settings($iclsettings);
			}
        }
        echo '1|';
        break;
	case 'copy_from_original':
		/*
		 * apply filtering as to add further elements
		 * filters will have to like as such
		 * add_filter('wpml_copy_from_original_fields', 'my_copy_from_original_fields');
		 *
		 * function my_copy_from_original_fields( $elements ) {
		 *  $custom_field = 'editor1';
		 *  $elements[ 'customfields' ][ $custom_fields ] = array(
		 *    'editor_name' => 'custom_editor_1',
		 *    'editor_type' => 'editor',
		 *    'value'       => 'test'
		 *  );
		 *
		 *  $custom_field = 'editor2';
		 *  $elements[ 'customfields' ][ $custom_fields ] = array(
		 *    'editor_name' => 'textbox1',
		 *    'editor_type' => 'text',
		 *    'value'       => 'testtext'
		 *  );
		 *
		 *  return $elements;
		 * }
		 * This filter would result in custom_editor_1 beeing populated with the value "test"
		 * and the textfield with id #textbox1 to be populated with "testtext".
		 * editor type is always either text when populating general fields or editor when populating
		 * a wp editor. The editor id can be either judged from the arguments used in the wp_editor() call
		 * or from looking at the tinyMCE.Editors object that the custom post type's editor sends to the browser.
		 */
		echo json_encode( wpml_copy_from_original_fields() );
		break;
    case 'save_user_preferences':
        $user_preferences = $this->get_user_preferences();
		$this->set_user_preferences(array_merge_recursive( $user_preferences, $_POST['user_preferences']));
        $this->save_user_preferences();
        break;
    case 'wpml_cf_translation_preferences':
        if (empty($_POST['custom_field'])) {
            echo '<span style="color:#FF0000;">'
            . __('Error: No custom field', 'wpml') . '</span>';
            die();
        }
        $_POST['custom_field'] = @strval($_POST['custom_field']);
        if (!isset($_POST['translate_action'])) {
            echo '<span style="color:#FF0000;">'
            . __('Error: Please provide translation action', 'wpml') . '</span>';
            die();
        }
        $_POST['translate_action'] = @intval($_POST['translate_action']);
        if (defined('WPML_TM_VERSION')) {
            global $iclTranslationManagement;
            if (!empty($iclTranslationManagement)) {
                $iclTranslationManagement->settings['custom_fields_translation'][$_POST['custom_field']] = $_POST['translate_action'];
                $iclTranslationManagement->save_settings();
                echo '<strong><em>' . __('Settings updated', 'wpml') . '</em></strong>';
            } else {
                echo '<span style="color:#FF0000;">'
                . __('Error: WPML Translation Management plugin not initiated', 'wpml')
                . '</span>';
            }
        } else {
            echo '<span style="color:#FF0000;">'
            . __('Error: Please activate WPML Translation Management plugin', 'wpml')
                    . '</span>';
        }
        break;
    case 'icl_seo_options':
        $iclsettings['seo']['head_langs'] = isset($_POST['icl_seo_head_langs']) ? intval($_POST['icl_seo_head_langs']) : 0;
        $iclsettings['seo']['canonicalization_duplicates'] = isset($_POST['icl_seo_canonicalization_duplicates']) ? intval($_POST['icl_seo_canonicalization_duplicates']) : 0;
        $this->save_settings($iclsettings);
        echo '1|';
        break;
    case 'dismiss_object_cache_warning':
        $iclsettings['dismiss_object_cache_warning'] = true;
        $this->save_settings($iclsettings);
        echo '1|';
        break;
    case 'update_option':
        $iclsettings[$_REQUEST['option']] = $_REQUEST['value'];
        $this->save_settings($iclsettings);
        break;
    case 'get_browser_language':
        $http_accept_language = $_SERVER[ 'HTTP_ACCEPT_LANGUAGE' ];
		$accepted_languages = explode(';', $http_accept_language);
		$default_accepted_language = $accepted_languages[0];
		$default_accepted_language_codes = explode(',', $default_accepted_language);
		echo mb_strtolower($default_accepted_language_codes[0]);
        break;
	case 'connect_translations':
		$new_trid = $_POST['new_trid'];
		$post_type = $_POST['post_type'];
		$post_id = $_POST['post_id'];
		$set_as_source = $_POST['set_as_source'];

		$language_details = $sitepress->get_element_language_details($post_id, 'post_' . $post_type);

		if ( $set_as_source ) {
			$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'source_language_code' => $language_details->language_code ), array( 'trid' => $new_trid, 'element_type' => 'post_' . $post_type ) );
			$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'source_language_code' => null, 'trid' => $new_trid ), array( 'element_id' => $post_id, 'element_type' => 'post_' . $post_type ) );
		} else {
			$original_element_language = $sitepress->get_default_language();
			$trid_elements             = $sitepress->get_element_translations( $new_trid, 'post_' . $post_type );
			if($trid_elements) {
				foreach ( $trid_elements as $trid_element ) {
					if ( $trid_element->original ) {
						$original_element_language = $trid_element->language_code;
						break;
					}
				}
			}
			$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'source_language_code' => $original_element_language, 'trid' => $new_trid ), array( 'element_id' => $post_id, 'element_type' => 'post_' . $post_type ) );
		}
		echo json_encode(true);
		break;
	case 'get_posts_from_trid':
		$trid = $_POST['trid'];
		$post_type = $_POST['post_type'];

		$translations = $sitepress->get_element_translations($trid, 'post_' . $post_type);

		$results = array();
		foreach($translations as $language_code => $translation) {
			$post = get_post($translation->element_id);
			$title = $post->post_title ? $post->post_title : strip_shortcodes(wp_trim_words( $post->post_content, 50 ));
			$source_language_code = $translation->source_language_code;
			$results[] = (object) array('language' => $language_code, 'title' => $title, 'source_language' => $source_language_code);
		}
		echo json_encode($results);
		break;
	case 'get_orphan_posts':

		$trid = $_POST['trid'];
		$post_type = $_POST['post_type'];
		$source_language = $_POST['source_language'];

		$results = SitePress::get_orphan_translations($trid, $post_type, $source_language);

		echo json_encode($results);

		break;
    default:
        do_action('icl_ajx_custom_call', $_REQUEST['icl_ajx_action'], $_REQUEST);
}

if (!isset($_POST['unit-test'])) {
    exit;
}

/**
 * wpml_copy_from_original_fields
 * Gets the content of a post, its excerpt as well as its title and returns it as an array
 *
 * @param
 *
 * @return array containing all the fields information
 */
function wpml_copy_from_original_fields() {
	global $wpdb;
	$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND language_code=%s", $_POST[ 'trid' ], $_POST[ 'lang' ] ) );
	$post    = get_post( $post_id );

	$fields_to_copy            = array( 'content' => 'post_content' );
	$fields_to_copy[ 'title' ] = 'post_title';

	$fields_contents = array();
	if ( ! empty( $post ) ) {
		foreach ( $fields_to_copy as $editor_key => $editor_field ) { //loops over the three fields to be inserted into the array
			if ( $editor_key == 'content' || $editor_key == 'excerpt' ) { //
				if ( $editor_key == 'content' ) {
					$editor_var = $_POST[ 'content_type' ]; //these variables are supplied by a javascript call in scripts.js icl_copy_from_original(lang, trid)
				} elseif ( $editor_key == 'excerpt' ) {
					$editor_var = $_POST[ 'excerpt_type' ];
				}
				if (isset($editor_var) && isset($_POST[ $editor_var ]) && $_POST[ $editor_var ] == 'rich' ) {
					$fields_contents[ $editor_key ] = htmlspecialchars_decode( wp_richedit_pre( $post->$editor_field ) );
				} else {
					$fields_contents[ $editor_key ] = htmlspecialchars_decode( wp_htmledit_pre( $post->$editor_field ) );
				}
			} elseif ( $editor_key == 'title' ) {
				$fields_contents[ $editor_key ] = strip_tags( $post->$editor_field );
			}
		}
		$fields_contents[ 'customfields' ] = apply_filters( 'wpml_copy_from_original_custom_fields', wpml_copy_from_original_custom_fields( $post ) );
	} else {
		$fields_contents[ 'error' ] = __( 'Post not found', 'sitepress' );
	}
	do_action( 'icl_copy_from_original', $post_id );

	return $fields_contents;
}

/**
 * wpml_copy_from_original_custom_fields
 * Gets the content of a custom posts custom field , its excerpt as well as its title and returns it as an array
 *
 * @param  (type) about this param
 *
 * @return array (type)
 */

function wpml_copy_from_original_custom_fields( $post ) {

	$elements                 = array();
	$elements [ 'post_type' ] = $post->post_type;
	$elements[ 'excerpt' ]    = array(
		'editor_name' => 'excerpt',
		'editor_type' => 'text',
		'value'       => $post->post_excerpt
	);

	return $elements;
}
