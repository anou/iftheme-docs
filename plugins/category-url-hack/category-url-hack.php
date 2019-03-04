<?php
/*
Plugin Name: Category redirect
Plugin URI: http://www.smol.org
Description: This plugin is designed to redirect a category's path to a custom URL. Juste have set witch category you want to redirect...
Author: David THOMAS
Version: 0.1
Author URI: http://www.smol.org/
*/

if (!class_exists("CategoryURLRedirect")) {
	class CategoryURLRedirect {
		/*
		 * generate the link to the options page under settings
		 */
		function create_menu() {
      add_options_page("Category redirect", "Category redirect", "manage_options", "category-redirect", array($this,'categ_url_hack_options'));  
		}
		
		/*
		 * generate the options page in the wordpress admin
		 */
		function categ_url_hack_options() {
		    $redirects = get_option('categ_url_hack');

        $categ_redirect = $redirects['categ'];
		    $categ_url_url = $redirects['url'];
        
        $args = array(
        	'hide_empty'         => 0, 
        	'hierarchical'       => 1, 
        	'name'               => 'categ_url_hack[categ]',
        	'id'                 => 'categ_redirect',
        	'selected'           => $categ_redirect,
        	'show_option_none'   => '--  ' . __('No redirect', 'categ_url_hack') . ' --'
        );
      ?>
      
      
      <div class="wrap">
        <div id="icon-edit" class="icon32"></div>
        <h2><?php _e( 'Category redirect settings', 'categ_url_hack' ); ?></h2>  
        <form method="post" action="options-general.php?page=category-redirect">
          <p><label for="categ_redirect"><?php _e('Choose a category to redirect', 'categ_url_hack');?></label>&nbsp;<?php wp_dropdown_categories( $args ) ?></p>
          <p><label for="categ_url_url"><?php _e("URL to redirect to:", 'categ_url_hack' ); ?></label>&nbsp;<input type="text" id="categ_url_url" name="categ_url_hack[url]" value="<?php echo $categ_url_url; ?>" placeholder="<?php _e('Relative or Absolute URL', 'categ_url_hack' );?>" size="20">&nbsp;<?php _e("eg: /my-page or https://www.smol.org" ); ?></p>  
          
          <p class="submit"><input type="submit" name="submit_categ_url" value="<?php _e('Save settings', 'categ_url_hack' ) ?>" /></p>
        </form>
      </div>
		<?php
		} // end of function categ_url_hack_options
		
		/*
		 * save the settings from the options page to the database
		 */
		function save_categ_redirect($data) {
			update_option('categ_url_hack', $data);
		}
		
		/*
		* Read the list of redirects and if the current page 
		* is found in the list, send the visitor on her way
		*/
		function categ_redirect() {

			$redirects = get_option('categ_url_hack');
			$categ = get_query_var('cat');
			$categz = array();

      // WPML compliant
/*
      $original = array_key_exists( 'wpml_object_id' , $GLOBALS['wp_filter'] ) ? apply_filters( 'wpml_object_id', $currenta, 'category', true, $default_lg ) : $currenta;
      apply_filters( 'wpml_object_id', int $element_id, string $element_type, bool $return_original_if_missing, mixed $ulanguage_code )
*/
      if ( array_key_exists( 'wpml_object_id' , $GLOBALS['wp_filter'] ) ) {
        global $sitepress, $wpdb;
        $defaultlg = $sitepress->get_default_language();
        
        $query = "SELECT trid, language_code, source_language_code FROM wp_icl_translations WHERE element_id='$categ' AND element_type='tax_category'";
        $trid = $wpdb->get_var( $query, 0 );
        $source_lg = $wpdb->get_var( $query, 2 );
        
        $query2 = "SELECT element_id FROM wp_icl_translations WHERE trid='$trid' AND element_type='tax_category'";
        $results_cat = $wpdb->get_results($query2, ARRAY_A);
        
        foreach ($results_cat as $k) {
          $categz[] = $k['element_id'];
        }
      }

			if ( !empty($redirects) && !empty($categz) ) {
			  if( $redirects['categ'] == $categ || in_array($redirects['categ'], $categz) ) {
			    wp_redirect( $redirects['url'] );
          exit();
        }
			}
		} // end function redirect
		
		/*
		 * utility function to get the full address of the current request
		 * credit: http://www.phpro.org/examples/Get-Full-URL.html
		 */
		function getAddress() {
			// check for https
			$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
			// return the full address
			return $protocol.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		}
	} // end class CategoryURLRedirect
} // end check for existance of class

// instantiate
$categ_redirect_plugin = new CategoryURLRedirect();

if (isset($categ_redirect_plugin)) {
	// add the redirect action, high priority
	add_action('wp_head', array($categ_redirect_plugin,'categ_redirect'), 1);

	// create the menu
	add_action('admin_menu', array($categ_redirect_plugin,'create_menu'));

	// if submitted, process the data
	if (isset($_POST['submit_categ_url'])) {
		$categ_redirect_plugin->save_categ_redirect($_POST['categ_url_hack']);
	}
}
