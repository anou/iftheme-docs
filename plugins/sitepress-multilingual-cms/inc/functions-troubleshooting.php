<?php
/*
if ( !function_exists('sys_get_temp_dir')) {
  function sys_get_temp_dir() {
      if( $temp=getenv('TMP') )        return $temp;
      if( $temp=getenv('TEMP') )        return $temp;
      if( $temp=getenv('TMPDIR') )    return $temp;
      $temp=tempnam(__FILE__,'');
      if (file_exists($temp)) {
          unlink($temp);
          return dirname($temp);
      }
      return null;
  }
}
*/  
function icl_reset_wpml($blog_id = false){
    global $wpdb;
    
    if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'resetwpml'){
        check_admin_referer( 'resetwpml' );    
    }
    
    if(empty($blog_id)){
        $blog_id = isset($_POST['id']) ? $_POST['id'] : $wpdb->blogid;
    }
    
    define('ICL_IS_WPML_RESET', true);
      
    if($blog_id || !function_exists('is_multisite') || !is_multisite()){

        if(function_exists('is_multisite') && is_multisite()){
            switch_to_blog($blog_id);
        }
        
        $icl_tables = array(
            $wpdb->prefix . 'icl_languages',
            $wpdb->prefix . 'icl_languages_translations',
            $wpdb->prefix . 'icl_translations',
            $wpdb->prefix . 'icl_translation_status',    
            $wpdb->prefix . 'icl_translate_job',    
            $wpdb->prefix . 'icl_translate',    
            $wpdb->prefix . 'icl_locale_map',
            $wpdb->prefix . 'icl_flags',
            $wpdb->prefix . 'icl_content_status',
            $wpdb->prefix . 'icl_core_status',
            $wpdb->prefix . 'icl_node',
            $wpdb->prefix . 'icl_strings',
            $wpdb->prefix . 'icl_string_translations',
            $wpdb->prefix . 'icl_string_status',
            $wpdb->prefix . 'icl_string_positions',
            $wpdb->prefix . 'icl_message_status',
            $wpdb->prefix . 'icl_reminders',    
        );
                
        foreach($icl_tables as $icl_table){
            $wpdb->query("DROP TABLE IF EXISTS " . $icl_table);
        }
        
        delete_option('icl_sitepress_settings');
        delete_option('icl_sitepress_version');
        delete_option('_icl_cache');
        delete_option('_icl_admin_option_names');
        delete_option('wp_icl_translators_cached');
        delete_option('WPLANG');   
         
        $wpmu_sitewide_plugins = (array) maybe_unserialize( get_site_option( 'active_sitewide_plugins' ) );
        if(!isset($wpmu_sitewide_plugins[ICL_PLUGIN_FOLDER.'/sitepress.php'])){
            deactivate_plugins(basename(ICL_PLUGIN_PATH) . '/sitepress.php');
            $ra = get_option('recently_activated');
            $ra[basename(ICL_PLUGIN_PATH) . '/sitepress.php'] = time();
            update_option('recently_activated', $ra);        
        }else{
            update_option('_wpml_inactive', true);
        }
        
        
        if(isset($_REQUEST['submit'])){            
            wp_redirect(network_admin_url('admin.php?page='.ICL_PLUGIN_FOLDER.'/menu/network.php&updated=true&action=resetwpml'));
            exit();
        }
        
        if(function_exists('is_multisite') && is_multisite()){
            restore_current_blog(); 
        }
        
    }
}

?>
