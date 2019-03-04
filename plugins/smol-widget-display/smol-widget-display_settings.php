<?php
if(!class_exists('Byad_Countdown_Settings')) {
	class Byad_Countdown_Settings {
		/**
		 * Construct the plugin object
		 */
		public function __construct() {
			// register actions
      add_action('admin_init', array(&$this, 'admin_init'));
      add_action('admin_menu', array(&$this, 'add_menu'));
      add_action('admin_enqueue_scripts', array(&$this,'enqueue_date_picker'));
      add_action('wp_print_styles', array(&$this,'enqueue_countdown_css'));
		} // END public function __construct
		
    /**
     * hook into WP's admin_init action hook
     */
    public function admin_init() {
    	// register your plugin's settings
    	register_setting('smol-widget-group', 'byad_countdown_date_picker');
    	register_setting('smol-widget-group', 'byad_countdown_dateformat');
    	register_setting('smol-widget-group', 'byad_countdown_deadend');
    	register_setting('smol-widget-group', 'byad_countdown_years');

    	// add your settings section
    	add_settings_section(
    	    'smol-widget-section', 
    	    __('Smol Widget Display Settings', 'smol-widget'), 
    	    array(&$this, 'settings_section_byad_countdown'), 
    	    'smol-widget'
    	);
    	
    	// add your setting's fields        
      add_settings_field( 
        'byad-countdown-setting_end_date', 
        __('Final Date', 'smol-widget'),
        array(&$this, 'byad_countdown_display_date_picker'),
        'byad-countdown', 
        'byad-countdown-section',
        array(
          'field' => 'byad_countdown_date_picker',
          'description' => __('Click and select the countdown end date. If empty, you are dead tomorrow after midnight.', 'smol-widget'),
        )
      );

      add_settings_field(
        'byad-countdown-setting_dateformat', 
        __('Date format', 'byad-countdown'), 
        array(&$this, 'settings_field_input_select'), 
        'byad-countdown', 
        'byad-countdown-section',
        array(
          'field' => 'byad_countdown_dateformat',
          'description' => __("Please choose a date format. It must match the format of Final date field!", 'smol-widget'),
          'options' => array( 
                          'eur' => __('day - month - Year (d/m/Y)', 'byad-countdown'), 
                          'usa' => __('month - day - Year (m/d/Y)', 'byad-countdown'), 
                          'chi' => __('Year - month - day (Y/m/d)', 'byad-countdown') 
                        ),
          'value' => get_option('byad_countdown_dateformat', 'eur'),
        )
      );

      add_settings_field(
        'byad-countdown-setting_deadend', 
        __('After countdown string', 'byad-countdown'), 
        array(&$this, 'settings_field_input_text'), 
        'byad-countdown', 
        'byad-countdown-section',
        array(
          'field' => 'byad_countdown_deadend',
          'description' => __("Fill with your last words (displayed at the countdown's end. I promise).", 'byad-countdown'),
          'value' => get_option('byad_countdown_deadend', __('You are dead now!', 'byad-countdown')),
        )
      );

      add_settings_field( 
        'byad-countdown-setting_years', 
        __('Display years', 'byad-countdown'), 
        array(&$this, 'settings_field_input_checkbox'), 
        'byad-countdown', 
        'byad-countdown-section',
        array(
          'field' => 'byad_countdown_years',
          'description' => __("Check this box if you want to display Years.", 'byad-countdown'),
        )
      );
        // Possibly do additional admin_init tasks
    } // END public static function activate
      
    public function settings_section_byad_countdown() {
        // Think of this as help text for the section.
        _e("Here you can configure some settings for the BYAD Countdown. But if you're already dead, don't bother.", 'byad-countdown');
    }
      
      /**
       * This function provides text inputs for settings fields
       */
      public function settings_field_input_text($args) {
          // Get the field name from the $args array
          $field = $args['field'];
          $desc = $args['description'];
          // Get the value of this setting
          $value = $args['value'];
          // echo a proper input type="text"
          echo sprintf('<input type="text" name="%s" id="%s" value="%s" /> <span class="description">%s</span>', $field, $field, $value, $desc);

      } // END public function settings_field_input_text($args)

      /**
       * This function provides a select for settings fields (date format)
       */
      public function settings_field_input_select($args) {
          // Get the field name from the $args array
          $field = $args['field'];
          $desc = $args['description'];
          //get the options
          $options = $args['options'];
          // Get the value of this setting
          $value = $args['value'];
          //open the select
          echo sprintf('<select name="%s" id="%s">', $field, $field);
          //retreave options
          foreach( $options as $k => $format ){
            echo sprintf('<option value="%s" %s>%s</option>',
                    $k,
                    selected( $value, $k, false ),
                    $format
                 );
          }
          //close the select
          echo sprintf('</select><span class="description">%s</span>', $desc);

      } // END public function settings_field_input_text($args)

      /**
       * This function provides text inputs for settings fields
       */
      public function settings_field_input_checkbox($args) {
          // Get the field name from the $args array
          $field = $args['field'];
          $desc = $args['description'];
          // Get the value of this setting
          $value = get_option($field);
          // echo a proper checkbox
          echo sprintf('<input type="checkbox" name="%s" id="%s" value="1" %s /> <span class="description">%s</span>',
                  $field, 
                  $field, 
                  checked( 1, $value, false ),
                  $desc
                );
      } // END public function settings_field_input_text($args)
      
      /**
       * This function provides text inputs with datepicker for settings fields
       */
      
      public function byad_countdown_display_date_picker($args) {
          // Get the field name from the $args array
          $field = $args['field'];
          $desc = $args['description'];
          // Get the value of this setting
          $value = get_option($field);
          
          echo sprintf('<input style="vertical-align:bottom" type="text" name="%s" id="%s" value="%s" class="byadcd-datepicker" /> <span class="description">%s</span>', $field, $field, $value, $desc);

      }// END public function byad_countdown_display_date_picker($args)

      /**
       * Enqueue the date picker
       */
      public function enqueue_date_picker(){
        wp_enqueue_script( 'datepicker-i18n', plugins_url( 'js/jquery.ui.i18n.all.js' , __FILE__ ), array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker') );
        do_action('byad_datepicker_lang');

        wp_enqueue_script( 'field-date-js', plugins_url( 'js/field_date.js' , __FILE__ ), array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker'), time(), true );
        
        $params = apply_filters('byad_date_data', array(
          'byadRegional' => 'fr',
          'byadIcon' => plugins_url( 'images/calendar.png' , __FILE__ ),
          ) );
        wp_localize_script( 'field-date-js', 'dateJs_Data', $params );

      
        wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/base/jquery-ui.css');
      }
      /**
       * Enqueue the countdown style
       */
      public function enqueue_countdown_css(){
      	wp_enqueue_style('countdown-styles', plugins_url( 'byad-countdown.css' , __FILE__ ));
        do_action('byad_stylesheet');
      }

      
      /**
       * add a menu
       */		
      public function add_menu() {
        // Add a page to manage this plugin's settings
      	add_options_page(
      	  __('Before You Are Dead Countdown Settings', 'byad-countdown'), 
      	  __('BYAD Countdown', 'byad-countdown'), 
      	    'manage_options', 
      	    'byad-countdown', 
      	    array(&$this, 'plugin_settings_page')
      	);
      } // END public function add_menu()
  
      /**
       * Menu Callback
       */		
      public function plugin_settings_page() {
      	if(!current_user_can('manage_options'))
      	{
      		wp_die(__('You do not have sufficient permissions to access this page.', 'byad-countdown'));
      	}

      	// Render the settings template
      	include(sprintf("%s/templates/byad-settings-tpl.php", dirname(__FILE__)));
      } // END public function plugin_settings_page()
    } // END class Byad_Countdown_Settings
} // END if(!class_exists('Byad_Countdown_Settings'))
