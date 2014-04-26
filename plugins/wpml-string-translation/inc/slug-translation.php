<?php

class WPML_Slug_Translation{
    
    
    static function setup(){
        global $sitepress_settings;
        /*
        if(!empty($sitepress_settings['posts_slug_translation']['on'])){
            add_filter('gettext_with_context', array('WPML_Slug_Translation', 'filters_gettext_with_context'), 0, 4); // high priority
        }
        */
    }
    
    static function init(){
        global $sitepress_settings;
        if(!empty($sitepress_settings['posts_slug_translation']['on'])){            
            add_filter('option_rewrite_rules', array('WPML_Slug_Translation', 'rewrite_rules_filter'), 1, 1); // high priority
            add_filter('post_type_link', array('WPML_Slug_Translation', 'post_type_link_filter'), 1, 4); // high priority
            add_action('_icl_before_archive_url', array('WPML_Slug_Translation', '_icl_before_archive_url'), 1, 2);
            add_action('_icl_after_archive_url', array('WPML_Slug_Translation', '_icl_after_archive_url'), 1, 2);
        }
        
        add_action('icl_ajx_custom_call', array('WPML_Slug_Translation', 'gui_save_options'), 10, 2);
        
    }
    
    /*
    static function filters_gettext_with_context($translation, $text, $_gettext_context, $domain){
        global $sitepress;
        if($_gettext_context == 'URL slug'){
            global $wpdb;
            $string_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}icl_strings WHERE name=%s AND value = %s", 'URL slug: ' . $text, $text));
            if(empty($string_id)){            
                icl_register_string('URL slugs - ' . $domain, 'URL slug: ' . $text, $text, false);
            }else{
                $tr = $wpdb->get_var($wpdb->prepare("SELECT value FROM {$wpdb->prefix}icl_string_translations WHERE string_id=%d AND language = %s", $string_id, $sitepress->get_current_language()));
                if(!empty($tr)){
                    $translation = $tr;
                }
            }
        }
        return $translation;
    }
    */
    
    static function rewrite_rules_filter($value){
        global $sitepress, $sitepress_settings, $wpdb;
        
        $strings_language = $sitepress_settings['st']['strings_language'];

		$current_language = $sitepress->get_current_language();
		if( $current_language != $strings_language){
            
            $queryable_post_types = get_post_types( array('publicly_queryable' => true) );
                
            foreach($queryable_post_types as $type){
                    
                if(!isset($sitepress_settings['posts_slug_translation']['types'][$type]) || !$sitepress_settings['posts_slug_translation']['types'][$type] || !$sitepress->is_translated_post_type($type)) continue;
                    
                $post_type_obj = get_post_type_object($type);
                $slug = isset($post_type_obj->rewrite['slug']) ? trim($post_type_obj->rewrite['slug'],'/') : false;
                 
                $slug_translation = $wpdb->get_var($wpdb->prepare("
                            SELECT t.value 
                            FROM {$wpdb->prefix}icl_string_translations t
                                JOIN {$wpdb->prefix}icl_strings s ON t.string_id = s.id
                            WHERE t.language = %s AND s.name = %s AND s.value = %s
                        ", $current_language, 'URL slug: ' . $slug, $slug));
                
                $using_tags = false;
                
                /* case of slug using %tags% - PART 1 of 2 - START */       
                if(preg_match('#%([^/]+)%#', $slug)){
                    $slug = preg_replace('#%[^/]+%#', '.+?', $slug);
                    $using_tags = true;
                }
                if(preg_match('#%([^/]+)%#', $slug_translation)){
                    $slug_translation = preg_replace('#%[^/]+%#', '.+?', $slug_translation);
                    $using_tags = true;
                }
                /* case of slug using %tags% - PART 1 of 2 - END */
                
                $buff_value = array();                     
                foreach((array)$value as $k=>$v){            
                    
                    if($slug && $slug != $slug_translation){                        
                        if(preg_match('#^[^/]*/?' . preg_quote($slug) . '/#', $k) && $slug != $slug_translation){
                            $k = preg_replace('#^([^/]*)(/?)' . preg_quote($slug) . '/#',  '$1$2' . $slug_translation . '/' , $k);    
                        }
                        
                    }
                    $buff_value[$k] = $v;
                }
                
                $value = $buff_value;
                unset($buff_value);                
                
                /* case of slug using %tags% - PART 2 of 2 - START */       
                if($using_tags){
                    if(preg_match('#\.\+\?#', $slug)){
                        $slug = preg_replace('#\.\+\?#', '(.+?)', $slug);
                    }
                    if(preg_match('#\.\+\?#', $slug_translation)){
                        $slug_translation = preg_replace('#\.\+\?#', '(.+?)', $slug_translation);
                    }
                    $buff_value = array();                     
                    foreach($value as $k=>$v){            
                        
                        if(trim($slug) && trim($slug_translation) && $slug != $slug_translation){
                            if(preg_match('#^[^/]*/?' . preg_quote($slug) . '/#', $k) && $slug != $slug_translation){
                                $k = preg_replace('#^([^/]*)(/?)' . preg_quote($slug) . '/#',  '$1$2' . $slug_translation . '/' , $k);    
                            }
                        }
                        $buff_value[$k] = $v;
                    }
                    
                    $value = $buff_value;
                    unset($buff_value);  
                }              
                /* case of slug using %tags% - PART 2 of 2 - END */       
                
            }
        }            
        
        return $value;
    }

	static function get_translated_slug( $slug, $language ) {
		global $wpdb, $sitepress_settings, $sitepress;

		$current_language = $sitepress->get_current_language();

		// Pre cache all results -- BEGIN
		$cache_key_args = array($slug, $current_language, $language);

		$cache_key = implode(':', array_filter($cache_key_args));
		$cache_group = 'get_translated_slug';

		$slugs_translations = wp_cache_get($cache_key, $cache_group);

		if(!$slugs_translations) {
			$slugs_translations_sql = false;

			if ( $language != $sitepress_settings[ 'st' ][ 'strings_language' ] ) {
				$slugs_translations_sql = "
												SELECT s.value as original, t.value
												FROM {$wpdb->prefix}icl_strings s
												JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id
												WHERE t.language = %s
												AND s.name LIKE %s
												AND s.language = %s
							";
			}else if ( $sitepress_settings[ 'st' ][ 'strings_language' ] != $current_language ) {
				$slugs_translations_sql = "
										SELECT t.value as original, s.value
										FROM {$wpdb->prefix}icl_strings s
										JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id
										WHERE t.language = %s
										AND s.name LIKE %s
										AND s.language = %s
							";
			}

			if($slugs_translations_sql) {
				$slugs_translations_prepared          = $wpdb->prepare( $slugs_translations_sql, array( $language, 'URL slug:%', $sitepress_settings[ 'st' ][ 'strings_language' ] ) );
				$slugs_translations          = $wpdb->get_results( $slugs_translations_prepared, 'ARRAY_A' );
				wp_cache_set($cache_key, $slugs_translations, $cache_group);
			}
		}
		// Pre cache all results -- END

		if($slugs_translations != null && $slugs_translations) {
			$translated_slug = false;
			foreach($slugs_translations as $slugs_row) {
				if($slugs_row['original'] == $slug) {
					$translated_slug = $slugs_row['value'];
					break;
				}
			}

			if ( $translated_slug ) {
				return $translated_slug;
			}
		}
		return $slug;
	}
    
    static function _icl_before_archive_url($post_type, $language){
        global $sitepress_settings, $sitepress;
        if(!empty($sitepress_settings['posts_slug_translation']['types'][$post_type])){
            global $wp_post_types;
            if($translated_slug = self::get_translated_slug($wp_post_types[$post_type]->rewrite['slug'], $language)){
                $wp_post_types[$post_type]->rewrite['slug_original'] = $wp_post_types[$post_type]->rewrite['slug'];
                $wp_post_types[$post_type]->rewrite['slug'] = $translated_slug;    
            }
        }
    }

    static function _icl_after_archive_url($post_type, $language){
        // restore default lang slug
        global $sitepress_settings, $sitepress;
        if(!empty($sitepress_settings['posts_slug_translation']['types'][$post_type])){
            global $wp_post_types;
            if(!empty($wp_post_types[$post_type]->rewrite['slug_original'])){
                $wp_post_types[$post_type]->rewrite['slug'] = $wp_post_types[$post_type]->rewrite['slug_original'];    
                unset($wp_post_types[$post_type]->rewrite['slug_original']);
            }
        }
    }
    
    static function post_type_link_filter($post_link, $post, $leavename, $sample){
        global $wpdb, $sitepress, $sitepress_settings;
        
        static $no_recursion_flag;
        
        if(!empty($no_recursion_flag)) return $post_link;
        
        if(!$sitepress->is_translated_post_type($post->post_type)){
            return $post_link;
        } 
        
        // get element language
        $ld = $sitepress->get_element_language_details($post->ID, 'post_' . $post->post_type);
        
        if(empty($ld)){
            return $post_link;
        } 

        static $cache;        
        
        if(!isset($cache[$post->ID][$leavename . '#' . $sample])){
            
            $strings_language = $sitepress_settings['st']['strings_language'];
            
            
            // fix permalink when object is not in the current language
            if($ld->language_code != $strings_language){

                $post_type = get_post_type_object($post->post_type);
                $slug_this = trim($post_type->rewrite['slug'], '/');
                
                $slug_real = $wpdb->get_var("
                            SELECT t.value 
                            FROM {$wpdb->prefix}icl_strings s    
                            JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id
                            WHERE s.value='". esc_sql($slug_this)."' 
                                AND s.language = '" . esc_sql($strings_language) . "' 
                                AND s.name LIKE 'URL slug:%' 
                                AND t.language = '" . esc_sql($ld->language_code) . "'
                ");
                
                if(empty($slug_real)) return $post_link;
                

                global $wp_rewrite;
                                                                
                if(isset($wp_rewrite->extra_permastructs[$post->post_type])){                                                                                                                
                    $struct_original = $wp_rewrite->extra_permastructs[$post->post_type]['struct'];
                                
                    $lslash = false !== strpos($struct_original, '/' . $slug_this) ? '/' : '';
                    //$wp_rewrite->extra_permastructs[$post->post_type]['struct'] = str_replace('/' . $slug_this, '/' . $slug_real, $struct_original);
                    $wp_rewrite->extra_permastructs[$post->post_type]['struct'] = preg_replace('@'. $lslash . $slug_this . '/@', $lslash.$slug_real.'/' , $struct_original);
                    $no_recursion_flag = true;
                    $post_link = get_post_permalink($post->ID, $leavename, $sample);
                    $no_recursion_flag = false;
                    $wp_rewrite->extra_permastructs[$post->post_type]['struct'] = $struct_original;
                    
                }else{
                    
                    // case of applying the page_link filter on default links                    
                    $post_link = preg_replace('@([\?&])'.$slug_this.'=@', '$1' . $slug_real . '=', $post_link);
                    
                }
                
                $cache[$post->ID][$leavename . '#' . $sample] = $post_link;
                    
                    
            }
            
        }else{
            
            $post_link = $cache[$post->ID][$leavename . '#' . $sample];
            
        }
                
        return $post_link;        
    }    
    
    static function gui_save_options($action , $data){
        
        switch($action){        
            case 'icl_slug_translation':        
                global $sitepress;
                $iclsettings['posts_slug_translation']['on'] = intval(!empty($_POST['icl_slug_translation_on']));
                $sitepress->save_settings($iclsettings);
                echo '1|' . $iclsettings['posts_slug_translation']['on'];
                break;
        }
        
    }
    
}