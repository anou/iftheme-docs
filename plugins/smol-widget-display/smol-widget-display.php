<?php
/**
 * Plugin Name: Smol Widget Display
 * Description: Adds options (custom class & display settings) to widgets configuration
 * Version: 1.0.0
 * Author: David THOMAS
 * Author URI: http://www.smol.org/studio-de-creation-sympathique/habitants/anou
 * License: GPL2
 * Text Domain: smol-widget
 * Domain Path: /languages
 */
/*
  Copyright 2014  David THOMAS  (email: anou(at)smol(dot)org)
  
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.
  
  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
  
  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Load plugin textdomain.
 *
 * @since 1.0
 */
function smol_widget_display_load_textdomain() {
  load_plugin_textdomain('smol-widget', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );  
}
add_action( 'plugins_loaded', 'smol_widget_display_load_textdomain' );

/* 
 * Smol Widget Display Widget
 */
//require_once(sprintf("%s/smol-widget-dispaly_widget.php", dirname(__FILE__)));


if(!class_exists('Smol_Widget_Display')) {
  
  class Smol_Widget_Display {
    /**
     * Construct the plugin object
     */
    public function __construct() {
      // Initialize Settings
/*
      require_once(sprintf("%s/smol-widget-display_settings.php", dirname(__FILE__)));
      $smol_widget_display_Settings = new Smol_Widget_Display_Settings();
*/
      
      // Add input fields(priority 5, 3 params)
      add_action('in_widget_form', array(&$this, 'swd_in_widget_form'),5,3);
      // Callback function for options update (priority 5, 3 params)
      add_filter('widget_update_callback', array( $this, 'swd_in_widget_form_update'),5,3);
      // add class names (default priority, one parameter)
      add_filter('dynamic_sidebar_params', array( $this, 'swd_dynamic_sidebar_params'),20);
      // add some CSS for widget. @TODO: widget cf.tabs-shortcode-and-widget to do something nice.
      add_action('wp_print_styles', array(&$this,'enqueue_swd_css'));

      
   		// Load plugin settings and show/hide widgets by altering the 
      // $sidebars_widgets global variable
      //add_action( 'init', array( $this, 'init_swd_context' ) );

      
      // for settings page
/*
      $plugin = plugin_basename(__FILE__);
      add_filter("plugin_action_links_$plugin", array( $this, 'plugin_settings_link' ));
*/
    } // END public function __construct

    /**
     * Activate the plugin
     */
    public static function activate() {
        // Do nothing
    } // END public static function activate

    /**
     * Deactivate the plugin
     */     
    public static function deactivate() {
        // Do nothing
    } // END public static function deactivate
		
		// Add the settings link to the plugins page
/*
		function plugin_settings_link($links) {
			$settings_link = '<a href="options-general.php?page=smol-widget">' . __('Settings', 'smol-widget') . '</a>';
			array_unshift($links, $settings_link);
			return $links;
		}
*/
    /**
     * hook into WP's in_widget_form action hook
     */
    public function swd_in_widget_form(&$t, $return, $instance) {
      $instance = wp_parse_args( (array) $instance, array( 'title' => '', 'class_name' => '', 'display_url' => '', 'display_categ' => '', 'on_children' => 0, 'swd_posts' => '', 'swd_pages' => '', 'swd_blog' => '') );
      //OPTIONAL: Declare each item in $instance as its own variable i.e. $type, $before.
      extract( $instance, EXTR_SKIP );
      
/*
      if ( !isset($class_name) )
          $class_name = null;
      if ( !isset($display_url) )
          $display_url = null;
      if ( !isset($display_categ) )
          $display_categ = null;
      if ( !isset($on_children) )
          $on_children = 0;
*/
      // CUSTOM CLASS
      $args_class_name = array(
        'label' => __('Custom class', 'smol-widget'),
        'field_id' => $t->get_field_id('class_name'),
        'field_name' => $t->get_field_name('class_name'),
        'description' => __('Fill with your custom class name', 'smol-widget'),
        'value' => $class_name
      );
      // URL
      $args_url = array(
        'label' => __('Display Path', 'smol-widget'),
        'field_id' => $t->get_field_id('display_url'),
        'field_name' => $t->get_field_name('display_url'),
        'description' => __('Fill with the desired path. Use <strong>*</strong> as a wildcard to target all pages under <em>my-path</em> has in <em>my-path/*</em>', 'smol-widget'),
        'value' => $display_url
      );
      // CATEGORY
      $args_category = array(
        'label' => __('Category', 'smol-widget'),
        'field_id' => $t->get_field_id('display_categ'),
        'field_name' => $t->get_field_name('display_categ'),
        'description' => __('Choose on which category you want to display this widget.', 'smol-widget'),
        'value' => $display_categ
      );
      // DISPLAYS ON CATEGORY'S CHILDREN
      $args_cat_children = array(
        'label' => __("Display on category's children", 'smol-widget'),
        'field_id' => $t->get_field_id('on_children'),
        'field_name' => $t->get_field_name('on_children'),
        'description' => '',
        'value' => $on_children
      );
      
      // MORE GENERAL PURPOSE
      $general = array(
        'swd_posts' => array(
          'label' => __("Display on all posts", 'smol-widget'),
          'field_id' => $t->get_field_id('swd_posts'),
          'field_name' => $t->get_field_name('swd_posts'),
          'description' => '',
          'value' => $swd_posts
        ),
        'swd_pages' => array(
          'label' => __("Display on all pages", 'smol-widget'),
          'field_id' => $t->get_field_id('swd_pages'),
          'field_name' => $t->get_field_name('swd_pages'),
          'description' => '',
          'value' => $swd_pages
        ),
        'swd_blog' => array(
          'label' => __("Display on blog homepage", 'smol-widget'),
          'field_id' => $t->get_field_id('swd_blog'),
          'field_name' => $t->get_field_name('swd_blog'),
          'description' => '',
          'value' => $swd_blog
        ),
      );
      ?>
      <p>
        <?php echo $this->settings_field_input_text($args_class_name); ?>
      </p>
      <p>
        <?php echo $this->settings_field_input_text($args_url); ?>
      </p>
      <p>
        <?php echo $this->settings_field_category($args_category); ?><br />
        <?php echo $this->settings_field_checkbox($args_cat_children); ?>
      </p>
      <p>
        <fieldset class="general_display"><legend><?php _e('General display', 'smol-widget');?></legend>
        <?php foreach ( $general as $tab ) {
          echo $this->settings_field_checkbox($tab);
        }
        ?>
        </fieldset>
      </p>

      <?php
      $retrun = null;
      return array($t, $return, $instance);
    }
    
    /**
     * This function provides text inputs for settings fields
     */
    public function settings_field_input_text($args) {
        // Get the field config from the $args array
        $label = $args['label'];
        $field_id = $args['field_id'];
        $field_name = $args['field_name'];
        $desc = $args['description'];
        // Get the value of this setting
        $value = $args['value'];
        // echo a proper input type="text"
        return sprintf('<label for="%s">%s</label><br /><input class="widefat" type="text" name="%s" id="%s" value="%s" /><span class="description">%s</span>', 
                $field_id,
                $label, 
                $field_name,
                $field_id, 
                $value, 
                $desc
             );

    } // END public function settings_field_input_text($args)

    /**
     * This function provides a checkbox
     */
    public function settings_field_checkbox($args) {
        // Get the field config from the $args array
        $label = $args['label'];
        $field_id = $args['field_id'];
        $field_name = $args['field_name'];
        $desc = $args['description'];
        // Get the value of this setting
        $value = $args['value'];
        // echo a proper input type="checkbox"

        return sprintf('<input id="%s" name="%s" type="checkbox" %s /><label for="%s">%s</label><br /><span class="description">%s</span>',
                $field_id,
                $field_name,
                checked( $value, 'on', false ),
                $field_id, 
                $label, 
                $desc
              );
    } // END public function settings_field_input_text($args)

    /**
     * This function provides a category select
     */
    public function settings_field_category($args) {
        // Get the field config from the $args array
        $label = $args['label'];
        $field_id = $args['field_id'];
        $field_name = $args['field_name'];
        $desc = $args['description'];
        // Get the value of this setting
        $value = $args['value'];
        // echo a proper "select"
        return sprintf('<label for="%s">%s</label><br />%s<br /><span class="description">%s</span>', 
                $field_id,
                $label,
                wp_dropdown_categories( array('selected' => $value, 'echo' => 0, 'hide_empty' => 0, 'name' => $field_name, 'id' => $field_id, 'hierarchical' => true, 'show_option_all' =>  __('All categories', 'smol-widget'), 'show_option_none' => __('No categories', 'smol-widget') ) ), 
                $desc
             );

    } // END public function settings_field_category($args)

    
    public function swd_in_widget_form_update($instance, $new_instance, $old_instance) {
      $instance['class_name'] = $new_instance['class_name'];
      $instance['display_url'] = strip_tags($new_instance['display_url']);//@TODO: check if path exist. And check aliases and/or system path.
      $instance['display_categ'] = $new_instance['display_categ'];
      $instance['on_children'] = $new_instance['on_children'];
      $instance['swd_posts'] = $new_instance['swd_posts'];
      $instance['swd_pages'] = $new_instance['swd_pages'];
      $instance['swd_blog'] = $new_instance['swd_blog'];
      
      return $instance;
    }
    
    function swd_dynamic_sidebar_params($params) {
      global $wp_registered_widgets, $wp;
        
      $widget_id = $params[0]['widget_id'];
      $widget_obj = $wp_registered_widgets[$widget_id];

      $callback = !is_admin() ? 'callback_original_wc' : '_callback';
      $widget_opt = get_option($widget_obj[$callback][0]->option_name);


      $widget_num = $widget_obj['params'][0]['number'];
      
      //custom class
      $class = '';

      if(isset($widget_opt[$widget_num]['class_name']) && $widget_opt[$widget_num]['class_name'] ) {
         $class = $widget_opt[$widget_num]['class_name'];
      }
      
      //test path & category
      add_filter('widget_display_callback', array( $this, 'hide_swd_widget'),5,3);

      $params[0]['before_widget'] = preg_replace( '/class="/', 'class="' . $class . ' ' ,  $params[0]['before_widget'], 1);

      return $params;
    }
    
    // Thanks to Drupal: http://api.drupal.org/api/function/drupal_match_path/6
  	public function match_path( $patterns ) {
  		global $wp;
  		$patterns_safe = array();
  
  		// Get the request URI from WP
  		$url_request = home_url( $wp->request );
      $patterns_safe = trim( trim( $patterns ), '/' ); // Trim trailing and leading slashes

  		$regexps = '/^('. preg_replace( '/\\\\\*/', '.*', preg_quote( $patterns_safe , '/' ) ) .')$/';

  		return preg_match( $regexps, $url_request ) ? true : false;
  	}
    
    //On categories pages
  	public function match_categ( $categ, $children = false ) {
  	  $display = false;
      // if categ == 0 display on all categories
      if( $categ == '0' && is_category() ) return true;
      
      // check for the existence of "the_content" filter
      if( array_key_exists( 'wpml_object_id' , $GLOBALS['wp_filter']) ) {
        // get the translated category ID if any. Return original if not
        $categ = apply_filters( 'wpml_object_id', $categ, 'category', true);
      }
  		$c = get_category( $categ );
  		$l = trim ( trim( get_category_link( $categ ) ), '/');

  		$display = $this->match_path($l);
  		
  		$display_child = false;
  		if( $children ) {
    		$display_child = $this->match_path($l . '/*' );
  		}
      return $display || $display_child ? true : false;
  	}
  	
    /**
     * Enqueue some styles
     */
    public function enqueue_swd_css(){
    	wp_enqueue_style('swd-styles', plugins_url( 'smol-widget-display.css' , __FILE__ ));
      do_action('swd_stylesheet');
    }
    
    /**
     * Filter function for visibility
     */
    public function hide_swd_widget($instance, $widget, $args) {
      $swd_path = isset($instance['display_url']) && $instance['display_url'] ? $instance['display_url'] : false;
      $swd_categ = isset($instance['display_categ']) && $instance['display_categ'] != -1 ? $instance['display_categ'] : false;
      $swd_children = isset($instance['on_children']) ? $instance['on_children'] : false;
      $swd_posts = isset($instance['swd_posts']) ? $instance['swd_posts'] : false;
      $swd_pages = isset($instance['swd_pages']) ? $instance['swd_pages'] : false;
      $swd_blog = isset($instance['swd_blog']) ? $instance['swd_blog'] : false;

      $display = true;

      if ( $swd_path ) $display = $this->match_path($swd_path);
      if ( $swd_categ || (string) $swd_categ == '0' ) $display = $this->match_categ($swd_categ, $swd_children);

      if ( strlen($swd_posts) && is_single() ) return $instance; 
      if ( strlen($swd_pages) && is_page() ) return $instance; 
      if ( strlen($swd_blog) && is_home() ) return $instance; 

      return $display ? $instance : false;
    }



  } // END class Smol_Widget_Display
} // END if(!class_exists('Smol_Widget_Display'))


if(class_exists('Smol_Widget_Display')) {
    // Installation and uninstallation hooks
    register_activation_hook(__FILE__, array('Smol_Widget_Display', 'activate'));
    register_deactivation_hook(__FILE__, array('Smol_Widget_Display', 'deactivate'));

    // instantiate the plugin class
    $smol_widget_display = new Smol_Widget_Display();
}

