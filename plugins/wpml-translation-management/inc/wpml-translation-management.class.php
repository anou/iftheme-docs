<?php

class WPML_Translation_Management{
	var $load_priority = 200;
	private static $instance;

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WPML_Translation_Management();
		}
		return self::$instance;
	}

	function __construct() {
		add_action( 'wpml_loaded', array( $this, 'load' ), $this->load_priority );
		add_action( 'init', array($this, 'verify_wpml') );

		//@todo: use autoloaded classes
		require_once WPML_TM_PATH . '/class-wpml-tm-ajax-interface.php';
		require_once WPML_TM_PATH . '/class-wpml-tm-ajax-factory.php';
		require_once WPML_TM_PATH . '/class-wpml-tm-service-activation-ajax.php';
	}
	
	function verify_wpml() {
		if ( ! defined('ICL_SITEPRESS_VERSION') ) {
			add_action( 'admin_notices', array('WPML_Translation_Management', 'notice_no_wpml') );
		}
	}

	static function notice_no_wpml() {
		?>
    <div class="error">
        <p><?php _e( 'Please activate WPML Multilingual CMS to have WPML Translation Management working.', 'wpml-translation-management' ); ?></p>
    </div>
    <?php
	}
	
	public static function ensure_includes($force = false)
	{
		$current_page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
		if($force || $current_page== WPML_TM_FOLDER . '/menu/main.php') {
			require_once WPML_TM_PATH . '/menu/wpml-tm-menus.class.php';
		}
		require_once WPML_TM_PATH . '/inc/wpml_tm_troubleshooting.class.php';
		if ( !defined( 'WPML_XLIFF_VERSION' ) ) {
			require_once WPML_TM_PATH . '/inc/wpml-translation-management-xliff.class.php';
		}
		if ( !defined( 'WPML_TRANSLATION_ANALYTICS_VERSION' ) ) {
			require_once WPML_TM_PATH . '/inc/wpml-translation-analytics.class.php';
		}
	}

	function load() {
		global $pagenow, $wpml_translation_job_factory;

		tm_after_load();
		$wpml_wp_api = new WPML_WP_API();
		$this->service_activation_ajax = new WPML_TM_Service_Activation_AJAX( $wpml_wp_api, $wpml_translation_job_factory );
		self::ensure_includes();

		$this->activate_embedded_modules();

		global $sitepress;
		$this->plugin_localization();
		
		add_action('wp_ajax_basket_extra_fields_refresh', array($this, 'basket_extra_fields_refresh') );

		// Check if WPML is active. If not display warning message and not load Sticky links
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) || ICL_PLUGIN_INACTIVE ) {
			if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) {
				add_action( 'admin_notices', array( $this, '_no_wpml_warning' ) );
			}
			return false;
		} elseif ( ! $sitepress->get_setting( 'setup_complete' ) ) {
			add_action( 'admin_notices', array( $this, '_wpml_not_installed_warning' ) );
			return false;
		} elseif ( version_compare( ICL_SITEPRESS_VERSION, '2.0.5', '<' ) ) {
			add_action( 'admin_notices', array( $this, '_old_wpml_warning' ) );

			return false;
		}

		if ( isset( $_GET['icl_action'] ) ) {
			if ( $_GET['icl_action'] === 'reminder_popup'
			     && isset( $_GET['_icl_nonce'] )
			     && wp_verify_nonce( $_GET['_icl_nonce'], 'reminder_popup_nonce' ) === 1
			) {
				add_action( 'init', array( 'TranslationProxy_Popup', 'display' ) );
			} elseif ( $_GET['icl_action'] === 'dismiss_help' ) {
				$sitepress->set_setting( 'dont_show_help_admin_notice', true, true );
			}
		}

        if ( is_admin() || defined( 'XMLRPC_REQUEST' ) ) {
            global $ICL_Pro_Translation;
            $ICL_Pro_Translation = new WPML_Pro_Translation();
        }

		if ( is_admin() ) {

			$this->automatic_service_selection();

			add_action( 'translation_service_authentication', array( $this, 'translation_service_authentication' ) );
			add_filter( 'translation_service_js_data', array( $this, 'translation_service_js_data' ) );
			add_filter( 'wpml_string_status_text',
			            array( 'WPML_Remote_String_Translation', 'string_status_text_filter' ),
			            10,
			            3 );
			add_action( 'wp_ajax_translation_service_authentication',
            array( $this, 'translation_service_authentication_ajax' ) );
			add_action( 'wp_ajax_translation_service_toggle', array( $this, 'translation_service_toggle_ajax' ) );
			add_action( 'trashed_post', array( $this, 'trashed_post_actions' ), 10, 2 );
			add_action( 'wp_ajax_icl_get_jobs_table', 'icl_get_jobs_table' );
			add_action( 'wp_ajax_icl_cancel_translation_jobs', 'icl_cancel_translation_jobs' );
			add_action( 'wp_ajax_icl_populate_translations_pickup_box', 'icl_populate_translations_pickup_box' );
			add_action( 'wp_ajax_icl_pickup_translations', 'icl_pickup_translations' );
			add_action( 'wp_ajax_icl_get_job_original_field_content', 'icl_get_job_original_field_content' );
			add_action( 'wp_ajax_icl_get_blog_users_not_translators', 'icl_get_blog_users_not_translators' );
			add_action( 'wp_ajax_get_translator_status', array('TranslationProxy_Translator', 'get_translator_status_ajax') );
			add_action( 'wpml_updated_translation_status', array( 'TranslationProxy_Batch', 'maybe_assign_generic_batch' ),  10, 2 );

			if(!defined('DOING_AJAX')) {
				$this->service_incomplete_local_jobs_notice();
				$this->service_authentication_notice();

				if ( $pagenow == 'admin.php'
									&& isset( $_GET[ 'page' ] )
									&& $_GET[ 'page' ] == WPML_TM_FOLDER . '/menu/main.php'
									&& ( !isset( $_GET[ 'sm' ] ) || $_GET['sm'] === 'dashboard' ) ) {
					$this->show_3_2_upgrade_notice();
				}
			}

			do_action('wpml_tm_init');

			if ( $pagenow != 'customize.php' ) { // stop TM scripts from messing up theme customizer
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
				add_action( 'admin_print_styles', array( $this, 'admin_print_styles' ) );
			}

			add_action( 'icl_wpml_top_menu_added', array( $this, '_icl_hook_top_menu' ) );
			add_action( 'admin_menu', array( $this, 'menu' ) );
			add_action( 'admin_menu', array( $this, 'menu_fix_order' ), 999 ); // force 'Translations' at the end

			add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );

			add_action( 'icl_dashboard_widget_content_top', array( $this, 'icl_dashboard_widget_content' ) );

			// Add a nice warning message if the user tries to edit a post manually and it's actually in the process of being translated
			global $pagenow;
			$request_get_trid = filter_input( INPUT_GET, 'trid', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
			$request_get_post = filter_input(
				INPUT_GET,
				'post',
				FILTER_SANITIZE_FULL_SPECIAL_CHARS,
				FILTER_NULL_ON_FAILURE
			);
			$request_get_lang = filter_input(
				INPUT_GET,
				'lang',
				FILTER_SANITIZE_FULL_SPECIAL_CHARS,
				FILTER_NULL_ON_FAILURE
			);
			if ( ( $pagenow == 'post-new.php' || $pagenow == 'post.php' ) && ( $request_get_trid || $request_get_post ) && $request_get_lang ) {
				add_action( 'admin_notices', array( $this, '_warn_editing_icl_translation' ) );
			}

			add_action( 'wp_ajax_dismiss_icl_side_by_site', array( $this, 'dismiss_icl_side_by_site' ) );
			add_action( 'wp_ajax_icl_tm_parent_filter', array( $this, '_icl_tm_parent_filter' ) );
			add_action( 'wp_ajax_icl_tm_toggle_promo', array( $this, '_icl_tm_toggle_promo' ) );

			if ( defined( 'WPML_ST_VERSION' ) ) {
				require WPML_TM_PATH . '/menu/string-translation/wpml-remote-string-translation.class.php';
				add_action( 'wpml_st_below_menu', array( 'WPML_Remote_String_Translation', 'display_string_menu' ) );
				//Todo: [WPML 3.3] this needs to be moved to ST plugin
				add_action( 'wpml_tm_send_string_jobs', array( 'WPML_Remote_String_Translation', 'send_strings_jobs' ), 10, 5 );
			}

			add_action ( 'wpml_support_page_after', array( $this, 'add_com_log_link' ) );
			add_action ( 'wpml_translation_basket_page_after', array( $this, 'add_com_log_link' ) );
			
			WPML_TM_Troubleshooting::init();
		}

		do_action( 'wpml_tm_loaded' );

		return true;
	}

	function trashed_post_actions( $post_id ) {
		//Removes trashed post from the basket
		TranslationProxy_Basket::delete_item_from_basket( $post_id );
	}

	function is_jobs_tab() {
		return $this->is_tm_page('jobs');
	}

	function is_translators_tab() {
		return $this->is_tm_page('translators');
	}

	function activate_embedded_modules() {

		$this->activate_translation_analytics();
		$this->activate_xliff();

	}

	function activate_translation_analytics() {

		if ( is_admin() ) {
			$this->remove_message_by_id('translation_analytics_deprecated');
			$this->remove_message_by_id( "translation_analytics_legacy" );
		}

		$message          = __( "Translation Analytics is now included with WPML's Translation Management. Please uninstall the Translation Analytics plugin.", 'wpml-translation-analytics' );

		$args = array(
			'id'            => 'translation_analytics_legacy',
			'group'         => 'translation_management_modules_check',
			'msg'           => $message,
			'type'          => 'error',
			'admin_notice'  => true,
			'hide'          => false
		);

		if ( defined("WPML_TRANSLATION_ANALYTICS_VERSION") && WPML_TRANSLATION_ANALYTICS_VERSION < '1.0.7' ) {

			if ( is_admin() ) {
				ICL_AdminNotifier::add_message( $args );
				add_action ('admin_menu', array($this, 'remove_deprecated_translaton_analytics_menu'), 100);
			}

		} else if ( defined('ENABLE_TRANSLATION_ANALYTICS') && ENABLE_TRANSLATION_ANALYTICS && $this->is_module_valid("WPML_Translation_Analytics", $args) ) {

			if ( is_admin() ) {
				$this->remove_message_by_id( "translation_analytics_legacy" );
			}

			global $WPML_Translation_Analytics;
			$WPML_Translation_Analytics = new WPML_Translation_Analytics();

		}
	}

	function remove_deprecated_translaton_analytics_menu() {
		$top_page = apply_filters('icl_menu_main_page', basename(ICL_PLUGIN_PATH).'/menu/languages.php');
		remove_submenu_page($top_page, WPML_TRANSLATION_ANALYTICS_FOLDER.'/menu/main.php');
	}

	function activate_xliff() {

		$message          = __( 'XLIFF is now included in WPML\'s Translation Management. Please deactivate and uninstall XLIFF Plugin, in order to use the new xliff features.', 'wpml-translation-management' );

		$args = array(
			'id'            => 'wpml_xliff_legacy',
			'group'         => 'translation_management_modules_check',
			'msg'           => $message,
			'type'          => 'error',
			'admin_notice'  => true,
			'hide'          => false
		);

		if ( defined("WPML_XLIFF_VERSION") && WPML_XLIFF_VERSION < '0.9.8' ) {

			if ( is_admin() ) {
				ICL_AdminNotifier::add_message( $args );
			}

		} else if ( $this->is_module_valid("WPML_Translation_Management_XLIFF", $args) ) {

			if ( is_admin() ) {
				$this->remove_message_by_id( "wpml_xliff_legacy" );
			}

			WPML_Translation_Management_XLIFF::get_instance();

		}
	}

	function remove_message_by_id($message_id) {
		if ( is_admin() ) {
			if ( ICL_AdminNotifier::message_id_exists( $message_id ) ) {
				ICL_AdminNotifier::remove_message( $message_id );
			}
		}
	}

	function is_module_valid($class_name, $args) {
		global $sitepress;

		if ( $sitepress && $sitepress->get_setting('setup_complete') && class_exists( $class_name ) ) {
			return true;
		}

		return false;
	}

	function admin_enqueue_scripts() {
		if ( ! defined( 'DOING_AJAX' ) ) {
			wp_register_script('wpml-tm-progressbar', WPML_TM_URL . '/res/js/wpml-progressbar.js', array('jquery', 'jquery-ui-progressbar', 'backbone'), WPML_TM_VERSION);
			wp_register_script('wpml-tm-scripts', WPML_TM_URL . '/res/js/scripts.js', array('jquery','wpml-tm-progressbar'), WPML_TM_VERSION);
			wp_enqueue_script('wpml-tm-scripts');
			wp_enqueue_style('wpml-tm-styles', WPML_TM_URL . '/res/css/style.css', array(), WPML_TM_VERSION); 
			wp_enqueue_style('wpml-tm-queue', WPML_TM_URL . '/res/css/translations-queue.css', array(), WPML_TM_VERSION);

			if ( filter_input(INPUT_GET, 'page') === WPML_TM_FOLDER . '/menu/main.php' ) {
				if ( isset( $_GET[ 'sm' ] ) && ( $_GET[ 'sm' ] == 'services' || $_GET[ 'sm' ] === 'translators' ) ) {
					wp_register_script( 'wpml-tm-translation-services',
					                    WPML_TM_URL . '/res/js/translation-services.js',
					                    array( 'wpml-tm-scripts', 'jquery-ui-dialog' ),
					                    WPML_TM_VERSION );
					wp_register_script( 'wpml-tm-translation-translators',
					                    WPML_TM_URL . '/res/js/translation-translators.js',
					                    array( 'wpml-tm-scripts', 'jquery-ui-autocomplete', 'underscore' ),
					                    WPML_TM_VERSION );

					$active_service = TranslationProxy::get_current_service();
					$service_name = isset($active_service->name) ? $active_service->name : __('Translation Service', 'wpml-translation-management');
					if (isset($active_service->url)) {
						$service_site_url = "<a href='{$active_service->url}' target='_blank'>{$service_name}</a>";
					} else {
						$service_site_url = $service_name;
					}
					$tm_ts_data = array(
						'strings' => array(
							'done' => __( 'Done', 'sitepress' ),
							'header' => sprintf(__('%s requires additional data', 'wpml-translation-management'), $service_name),
							'tip' => sprintf(__("You can find this at %s site", 'wpml-translation-management'), $service_site_url)
						),
					);

					$tm_tt_data = array(
						'no_matches' => __( 'No matches', 'wpml-translation-management' ),
						'found'      => __( 'User found', 'wpml-translation-management' )
					);

					$tm_ts_data = apply_filters( 'translation_service_js_data', $tm_ts_data );

					wp_localize_script( 'wpml-tm-translation-services', 'tm_ts_data', $tm_ts_data );
					wp_localize_script( 'wpml-tm-translation-translators', 'tm_tt_data', $tm_tt_data );
					wp_enqueue_script( 'wpml-tm-translation-services' );
					wp_enqueue_script( 'wpml-tm-translation-translators' );
				}

				wp_enqueue_script( 'wpml-tm-translation-proxy',
				                   WPML_TM_URL . '/res/js/translation-proxy.js',
				                   array( 'wpml-tm-scripts', 'jquery-ui-dialog' ),
				                   WPML_TM_VERSION );
			}

			wp_enqueue_style ( 'wp-jquery-ui-dialog' );
			wp_enqueue_script( 'thickbox' );
			wp_enqueue_script( 'sitepress-icl_reminders', WPML_TM_URL . '/res/js/icl_reminders.js', array(), WPML_TM_VERSION );
			do_action('wpml_tm_scripts_enqueued');
		}
	}

	function admin_print_styles() {
		if ( ! defined( 'DOING_AJAX' ) ) {
			wp_enqueue_style( 'wpml-tm-styles', WPML_TM_URL . '/res/css/style.css', array( 'jquery-ui-theme' ), WPML_TM_VERSION );
			wp_enqueue_style( 'wpml-tm-queue', WPML_TM_URL . '/res/css/translations-queue.css', array(), WPML_TM_VERSION );
		}
	}

	function translation_service_authentication() {
		$active_service = TranslationProxy::get_current_service();
		$custom_fields  = TranslationProxy::get_custom_fields( $active_service->id );

		$auth_content[] = '<div class="js-service-authentication">';
		$auth_content[] = '<ul>';
		if ( TranslationProxy::service_requires_authentication($active_service) ) {
			$auth_content[] = '<input type="hidden" name="service_id" id="service_id" value="' . $active_service->id . '" />';
			$custom_fields_data = TranslationProxy::get_custom_fields_data();
			if ( ! $custom_fields_data ) {
				$auth_content[] = '<li>';
				$auth_content[] = '<p>';
				$auth_content[] = sprintf( __( '%s is active, but requires authentication data.', 'wpml-translation-management' ), $active_service->name );
				$auth_content[] = '</p>';
				$auth_content[] = '</li>';
				$auth_content[] = '<li>';
				$auth_content[] = '<strong>';
				$auth_content[] = '<a href="#" class="js-authenticate-service" data-id="' . $active_service->id . '" data-custom-fields="' . esc_attr( wp_json_encode( $custom_fields ) ) . '">';
				$auth_content[] = __( 'Click here to authenticate.', 'wpml-translation-management' );
				$auth_content[] = '</a>';
				$auth_content[] = '</strong>';
				$auth_content[] = wp_nonce_field( 'authenticate_service', 'authenticate_service_nonce', true, false );
				$auth_content[] = '<input type="hidden" name="custom_fields_serialized" id="custom_fields_serialized" value="" />';
				$auth_content[] = '</li>';
			} else {
				$auth_content[] = '<li>';
				$auth_content[] = '<p>';
				$auth_content[] = sprintf( __( '%s is authorized.', 'wpml-translation-management' ), $active_service->name ) . '&nbsp;';
				$auth_content[] = '</p>';
				$auth_content[] = '</li>';
				$auth_content[] = '<li>';
				$auth_content[] = '<strong>';
				$auth_content[] = '<a href="#" class="js-invalidate-service" data-id="' . $active_service->id . '" data-custom-fields="' . esc_attr( wp_json_encode( $custom_fields ) ) . '">';
				$auth_content[] = __( 'Click here to de-authorize.', 'wpml-translation-management' );
				$auth_content[] = '</a>';
				$auth_content[] = '</strong>';
				$auth_content[] = wp_nonce_field( 'invalidate_service', 'invalidate_service_nonce', true, false );
				$auth_content[] = '</li>';
			}
		}
		if(!defined( 'WPML_TP_DEFAULT_SUID' )) {
			$auth_content[] = '<li>';
			$auth_content[] = '<strong>';
			$auth_content[] = '<a href="#" class="js-deactivate-service" data-id="' . $active_service->id . '" data-custom-fields="' . esc_attr( wp_json_encode( $custom_fields ) ) . '">';
			$auth_content[] = __( 'Click here to deactivate.', 'wpml-translation-management' );
			$auth_content[] = '</a>';
			$auth_content[] = '</strong>';
			$auth_content[] = '</li>';
		}
		$auth_content[] = '</ul>';
		$auth_content[] = '</div>';

		$auth_content_full = implode("\n", $auth_content);
		ICL_AdminNotifier::display_instant_message($auth_content_full);
	}

	function translation_service_toggle_ajax( ) {
		$translation_service_toggle = false;
		if ( isset( $_POST[ 'nonce' ] ) ) {
			$translation_service_toggle = wp_verify_nonce( $_POST[ 'nonce' ], 'translation_service_toggle' );
		}
		$errors  = 0;
		$message = '';

		if ( $translation_service_toggle ) {
			$service_id = false;
			if ( isset( $_POST[ 'service_id' ] ) ) {
				$service_id = $_POST[ 'service_id' ];
			}
			$enable = false;
			if ( isset( $_POST[ 'enable' ] ) ) {
				$enable = $_POST[ 'enable' ];
			}

			if ( ! $service_id ) {
				return;
			}

			if ( $enable && TranslationProxy::get_current_service_id() != $service_id ) {
				$result = TranslationProxy::select_service( $service_id );
				if ( is_wp_error( $result ) ) {
					$message = $result->get_error_message();
				}
			}
			if ( ! $enable && TranslationProxy::get_current_service_id() == $service_id ) {
				TranslationProxy::deselect_active_service();
			}
		} else {
			$message = __( 'You are not allowed to perform this action.', 'wpml-translation-management' );
			$errors ++;
		}

		$response = array(
			'errors'  => $errors,
			'message' => $message,
			'reload'  => ( ! $errors ? 1 : 0 )
		);
		echo wp_json_encode( $response );
		die();

	}
	
	function translation_service_authentication_ajax( ) {
		$translation_service_authentication = false;
		if ( isset( $_POST[ 'nonce' ] ) ) {
			$translation_service_authentication = wp_verify_nonce( $_POST[ 'nonce' ], 'translation_service_authentication' );
		}
		$errors  = 0;
		$message = '';

		$invalidate    = isset($_POST[ 'invalidate' ]) ? $_POST[ 'invalidate' ] : false;
		if ( $translation_service_authentication ) {
			if ( $invalidate ) {
				$result = TranslationProxy::invalidate_service( TranslationProxy::get_current_service_id() );
				if ( ! $result ) {
					$message = __( 'Unable to invalidate this service. Please contact WPML support.', 'wpml-translation-management' );
					$errors ++;
				} else {
					$message = __( 'Service invalidated.', 'wpml-translation-management' );
				}
			} else {
				if ( isset( $_POST[ 'custom_fields' ] ) ) {
					$custom_fields_data_serialized = $_POST[ 'custom_fields' ];
					$custom_fields_data            = json_decode( stripslashes( $custom_fields_data_serialized ), true );
					$result                        = TranslationProxy::authenticate_service( $_POST[ 'service_id' ], $custom_fields_data );

					if ( ! $result ) {
						$message = __( 'Unable to activate this service. Please check entered data and try again.', 'wpml-translation-management' );
						$errors ++;
					} else {
						$message = __( 'Service activated.', 'wpml-translation-management' );
					}
				}
			}
		} else {
			$message = __( 'You are not allowed to perform this action.', 'wpml-translation-management' );
			$errors ++;
		}
		$response = array(
			'errors'  => $errors,
			'message' => $message,
			'reload'  => ( ! $errors ? 1 : 0 )
		);
		echo wp_json_encode( $response );
		die();
	}

	function translation_service_js_data($data) {
		$data['nonce']['translation_service_authentication'] = wp_create_nonce( 'translation_service_authentication' );
		$data['nonce']['translation_service_toggle'] = wp_create_nonce( 'translation_service_toggle' );
		return $data;
	}

	function _no_wpml_warning(){
        ?>
        <div class="message error"><p><?php printf(__('WPML Translation Management is enabled but not effective. It requires <a href="%s">WPML</a> in order to work.', 'wpml-translation-management'), 
            'https://wpml.org/'); ?></p></div>
        <?php
    }
		function _wpml_not_installed_warning(){
			?>
			<div class="message error"><p><?php printf(__('WPML Translation Management is enabled but not effective. Please finish the installation of WPML first.', 'wpml-translation-management') ); ?></p></div>
		<?php
		}
    
    function _old_wpml_warning(){
        ?>
        <div class="message error"><p><?php printf(__('WPML Translation Management is enabled but not effective. It is not compatible with  <a href="%s">WPML</a> versions prior 2.0.5.', 'wpml-translation-management'), 
            'https://wpml.org/'); ?></p></div>
        <?php
    }    

	function _icl_hook_top_menu() {
		if ( !defined( 'ICL_PLUGIN_PATH' ) ) {
			return;
		}
		$top_page = apply_filters( 'icl_menu_main_page', basename( ICL_PLUGIN_PATH ) . '/menu/languages.php' );

		$menu_label = __('Translation Management', 'wpml-translation-management');
		add_submenu_page( $top_page, $menu_label, $menu_label, 'wpml_manage_translation_management', WPML_TM_FOLDER . '/menu/main.php', array($this, 'options_page')  );
	}

	function options_page() {
		global $wpml_tm_menus;
		$wpml_tm_menus = new WPML_TM_Menus();
		$wpml_tm_menus->display_main();
	}
    
    function menu(){
	    if(!defined('ICL_PLUGIN_PATH')) return;
        global $sitepress, $iclTranslationManagement;
        
        if ($iclTranslationManagement && method_exists($sitepress, 'setup') && $sitepress->setup() && 1 < count($sitepress->get_active_languages())) {
            
            $current_translator = $iclTranslationManagement->get_current_translator();
            if(!empty($current_translator->language_pairs) || current_user_can('wpml_manage_translation_management')){
                if(current_user_can('wpml_manage_translation_management')){
                    $top_page = apply_filters('icl_menu_main_page', ICL_PLUGIN_FOLDER.'/menu/languages.php');
                    add_submenu_page($top_page, 
                    __('Translations','wpml-translation-management'), __('Translations','wpml-translation-management'),
                    'wpml_manage_translation_management', WPML_TM_FOLDER.'/menu/translations-queue.php');
                } else {
                    add_menu_page(__('Translation interface','wpml-translation-management'), 
                        __('Translation interface','wpml-translation-management'), 'translate', 
                        WPML_TM_FOLDER.'/menu/translations-queue.php',null, ICL_PLUGIN_URL . '/res/img/icon16.png');
                }
            }
        }
                    
    }
    
    function menu_fix_order(){
        global $submenu;
        
        if(!isset($submenu[WPML_TM_FOLDER . '/menu/main.php'])) return;
        
        // Make sure 'Translations' stays at the end        
        $found = false;
        foreach($submenu[WPML_TM_FOLDER . '/menu/main.php'] as $id => $sm){            
            if($sm[2] == WPML_TM_FOLDER . '/menu/translations-queue.php'){
                $found = $sm;
                unset($submenu[WPML_TM_FOLDER . '/menu/main.php'][$id]);
                break;
            }                
        }
        if($found){
            $submenu[WPML_TM_FOLDER . '/menu/main.php'][] = $found;
        }
    }

    function _warn_editing_icl_translation(){
        global $wpdb, $sitepress, $iclTranslationManagement;
				$request_get_trid = filter_input(INPUT_GET, 'trid', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE);
				$request_get_post = filter_input(INPUT_GET, 'post', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE);
				$request_get_lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE);

		$post_type = false;
        if($request_get_trid){
            $translation_id = $wpdb->get_var($wpdb->prepare("
                    SELECT t.translation_id 
                        FROM {$wpdb->prefix}icl_translations t
                        JOIN {$wpdb->prefix}icl_translation_status s ON t.translation_id = s.translation_id
                        WHERE t.trid=%d AND t.language_code=%s"
                , $request_get_trid, $request_get_lang));
        }else{
            $post_type = $wpdb->get_var($wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID=%d", $request_get_post));
            $translation_id = $wpdb->get_var($wpdb->prepare("
                    SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s AND language_code=%s"
                , $request_get_post, 'post_' . $post_type, $request_get_lang));
        }
        
        if($translation_id){
            $translation_status = $wpdb->get_var($wpdb->prepare("
                SELECT status FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d"
            , $translation_id));  
            if(!is_null($translation_status) && $translation_status > 0 && $translation_status != ICL_TM_DUPLICATE && $translation_status < ICL_TM_COMPLETE){
                echo '<div class="error fade"><p id="icl_side_by_site">'. 
                    sprintf(__('<strong>Warning:</strong> You are trying to edit a translation that is currently in the process of being added using WPML.' , 'wpml-translation-management')) . '<br /><br />'.
                    sprintf(__('Please refer to the <a href="%s">Translation management dashboard</a> for the exact status of this translation.' , 'wpml-translation-management'),
                    admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/main.php&')) . '</p></div>';    
            }else{
				$is_original = false;
				if($post_type) {
					$element_language_details = $sitepress->get_element_language_details($request_get_post, 'post_' . $post_type);
					$is_original = !$element_language_details->source_language_code;
				}
                if(!$is_original && $iclTranslationManagement->settings['doc_translation_method'] == ICL_TM_TMETHOD_EDITOR){
                ?>
                <div class="error">
                    <p><?php _e('<strong>Warning:</strong> You are trying to edit a translation using the standard WordPress editor but your site is configured to use the WPML Translation Editor.' , 'wpml-translation-management')?></p>
                </div>
                <?php
                }
            }
        }elseif(($post_type && $sitepress->is_translated_post_type($post_type)) && $iclTranslationManagement->settings['doc_translation_method'] == ICL_TM_TMETHOD_EDITOR){
            ?>
            <div class="error">
                <p><?php _e('<strong>Warning:</strong> You are trying to add a translation using the standard WordPress editor but your site is configured to use the WPML Translation Editor.' , 'wpml-translation-management')?></p>
                <p><?php printf(__('You should use <a href="%s">Translation management dashboard</a> to send the original document to translation.' , 'wpml-translation-management'), admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/main.php')); ?>
                </p>
            </div>
            <?php
            }
        
    }
        
    function dismiss_icl_side_by_site(){
        global $iclTranslationManagement;
        $iclTranslationManagement->settings['doc_translation_method'] = ICL_TM_TMETHOD_MANUAL;
        $iclTranslationManagement->save_settings();
        exit;        
    }

    function icl_dashboard_widget_content(){
        global $wpdb;
        get_currentuserinfo();
        $docs_sent = 0;
        $docs_completed = 0;
        $docs_waiting = 0;
        $docs_statuses = $wpdb->get_results($wpdb->prepare("SELECT status FROM {$wpdb->prefix}icl_translation_status WHERE status > %d", ICL_TM_NOT_TRANSLATED));
        foreach ($docs_statuses as $doc_status) {
            $docs_sent += 1;
            if ($doc_status->status == ICL_TM_COMPLETE) {
                $docs_completed += 1;
            } elseif ($doc_status->status == ICL_TM_WAITING_FOR_TRANSLATOR
                    || $doc_status->status == ICL_TM_IN_PROGRESS) {
                $docs_waiting += 1;
            }
        }
        include WPML_TM_PATH . '/menu/_icl_dashboard_widget.php';
    }

    function plugin_action_links($links, $file){
        $this_plugin = basename(WPML_TM_PATH) . '/plugin.php';
        if($file == $this_plugin) {
            $links[] = '<a href="admin.php?page='.basename(WPML_TM_PATH) . '/menu/main.php">' . 
                __('Configure', 'wpml-translation-management') . '</a>';
        }
        return $links;
    }

    // Localization
    function plugin_localization(){
        load_plugin_textdomain( 'wpml-translation-management', false, WPML_TM_FOLDER . '/locale');
    }

    //
    function _icl_tm_parent_filter(){
        global $sitepress;
		$current_language = $sitepress->get_current_language();
		$request_post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE);
		$request_post_lang = filter_input(INPUT_POST, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE);
		$request_post_parent_id = filter_input(INPUT_POST, 'parent_id', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE);
		$request_post_parent_all = filter_input(INPUT_POST, 'parent_all', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE);
		$sitepress->switch_lang($request_post_lang);
        if($request_post_type == 'page'){
            $html = wp_dropdown_pages(array('echo'=>0, 'name'=>'filter[parent_id]', 'selected'=>$request_post_parent_id));
        }elseif($request_post_type == 'category'){
            $html = wp_dropdown_categories(array('echo'=>0, 'orderby'=>'name', 'name'=>'filter[parent_id]', 'selected'=>$request_post_parent_id));
        }else{
            $html = '';
        }
        $sitepress->switch_lang($current_language);
        
        $html .= "<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
				if(is_null($request_post_parent_all) || $request_post_parent_all) {
					$checked = ' checked="checked"';
				} else {
					$checked="";
				}
        $html .= "<label><input type=\"radio\" name=\"filter[parent_all]\" value=\"1\" {$checked} />&nbsp;" . __('Show all items under this parent.', 'wpml-translation-management') . '</label>';
        $html .= "<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        if(empty($request_post_parent_all)) {
					$checked = ' checked="checked"';
				} else {
					$checked="";
				}
        $html .= "<label><input type=\"radio\" name=\"filter[parent_all]\" value=\"0\" {$checked} />&nbsp;" . __('Show only items that are immediately under this parent.', 'wpml-translation-management') . '</label>';
        
        echo wp_json_encode(array('html'=>$html));
        exit;
        
    }

	function _icl_tm_toggle_promo() {
		global $sitepress;
		$iclsettings[ 'dashboard' ][ 'hide_icl_promo' ] = @intval( $_POST[ 'value' ] );
		$sitepress->save_settings( $iclsettings );
		exit;
	}

	/**
	 * @return array
	 */
	public function get_active_services() {
		$cache_key = 'active_services';
		$cache_group = '';

		$found = false;
		$result = wp_cache_get($cache_key, $cache_group, false, $found);
		if($found) return $result;

		$active_services = array( 'local' => array() );
		$current_service = TranslationProxy::get_current_service();

		if ( !is_wp_error( $current_service ) ) {
			if ( $current_service ) {
				$active_services[ $current_service->name ] = $current_service;
			}	
			
			wp_cache_set( $cache_key, $active_services, $cache_group );
		}
		return $active_services;
	}

	protected function automatic_service_selection() {
		if ( defined( 'DOING_AJAX' ) || !$this->automatic_service_selection_pages() ) {
			return;
		}

		$done = wp_cache_get('done', 'automatic_service_selection');

		ICL_AdminNotifier::remove_message( 'automatic_service_selection' );
		if (!$done && defined( 'WPML_TP_DEFAULT_SUID' ) ) {
			$selected_service = TranslationProxy::get_current_service();

			if ( isset($selected_service->suid) && $selected_service->suid == WPML_TP_DEFAULT_SUID ) {
				return;
			}

			try {
				$service_by_suid = TranslationProxy_Service::get_service_by_suid( WPML_TP_DEFAULT_SUID );
			} catch ( Exception $ex ) {
				$service_by_suid = false;
			}

			if ( isset($service_by_suid->id) ) {
				$selected_service_id = isset($selected_service->id) ? $selected_service->id : false;
				if ( ! $selected_service_id || $selected_service_id != $service_by_suid->id ) {
					if ( $selected_service_id ) {
						TranslationProxy::deselect_active_service();
					}
					$result = TranslationProxy::select_service( $service_by_suid->id );
					if ( is_wp_error( $result ) ) {
						$error_data        = $result->get_error_data();
						$error_data_string = false;
						foreach ( $error_data as $key => $error_data_message ) {
							$error_data_string .= $result->get_error_message() . '<br/>';
							$error_data_string .= $key . ': <pre>' . print_r( $error_data_message, true ) . '</pre>';
							$error_data_string .= $result->get_error_message() . $error_data_string;
						}
					}
					if ( defined( 'WPML_TP_SERVICE_CUSTOM_FIELDS' ) ) {
						TranslationProxy::authenticate_service( $service_by_suid->id, WPML_TP_SERVICE_CUSTOM_FIELDS );
					}
				}
			} else {
				$error_data_string = __("WPML can't find the translation service specified in WPML_TP_DEFAULT_SUID constant. Please remove the constant or set the correct value.", 'wpml-translation-management');
			}
		}
		
		if (isset($error_data_string)) {
			$automatic_service_selection_args = array(
			'id'           => 'automatic_service_selection',
			'group'        => 'automatic_service_selection',
			'msg'          => $error_data_string,
			'type'         => 'error',
			'admin_notice' => true,
			'hide'         => false,
			);
			ICL_AdminNotifier::add_message( $automatic_service_selection_args );
		}

		wp_cache_set('done', true, 'automatic_service_selection');
	}
	
	public function basket_extra_fields_refresh() {
		echo TranslationProxy_Basket::get_basket_extra_fields_inputs();
		die();
	}
	
	/**
	 * If user display Translation Dashboard or Translators
	 * 
	 * @return boolean
	 */
	function automatic_service_selection_pages() { 
		return is_admin() &&
					 isset($_GET['page']) &&
					 $_GET['page'] == WPML_TM_FOLDER . '/menu/main.php' &&
					 ( !isset($_GET['sm']) || $_GET['sm'] == 'translators' || $_GET['sm'] == 'dashboard' );
	}
	
	private function show_3_2_upgrade_notice( ) {

		global $sitepress;
		
		$upgrade_setting = 'tm_upgrade_3.2';
		if ( ! $sitepress->get_setting( $upgrade_setting ) ) {
	
			$args = array(
				'id'            => $upgrade_setting,
				'msg'           => '<p>' . __( "We've updated the way to send documents to translation", 'wpml-translation-management' ) . '</p>' .
										   '<a href="https://wpml.org/version/wpml-3-2/">' . __('WPML 3.2 release notes', 'wpml-translation-management') . '</a>',
				'admin_notice'  => true,
				'hide'          => true,
				'limit_to_page' => WPML_TM_FOLDER . '/menu/main.php',
				);
			ICL_AdminNotifier::add_message( $args );
			
			$sitepress->set_setting( $upgrade_setting, true );
			$sitepress->save_settings( );
			
		}
		
	}
	
	public function add_com_log_link( ) {
		require_once WPML_TM_PATH . '/inc/translation-proxy/translationproxy-com-log.class.php';
		TranslationProxy_Com_Log::add_com_log_link( );
	}

	public function service_activation_incomplete() {
		return $this->has_active_service() && ($this->service_requires_authentication() || $this->service_requires_translators());
	}

	private function has_active_service() {
		return TranslationProxy::get_current_service() !== false;
	}

	private function service_requires_translators() {
		$result                  = false;
		$service_has_translators = TranslationProxy::translator_selection_available();
		if ( $service_has_translators ) {
			$result = !$this->service_has_accepted_translators();
		}

		return $result;
	}

	private function service_requires_authentication() {
		$result = false;
		$service_has_translators = TranslationProxy::translator_selection_available();
		if ( !$service_has_translators ) {
			$has_custom_fields       = TranslationProxy::has_custom_fields();
			$custom_fields_data      = TranslationProxy::get_custom_fields_data();
			$result = $has_custom_fields && !$custom_fields_data;
		}

		return $result;
	}

	private function service_has_accepted_translators() {
		$result = false;
		$icl_data = TranslationProxy_Translator::get_icl_translator_status();
		if ( isset( $icl_data[ 'icl_lang_status' ] ) && is_array( $icl_data[ 'icl_lang_status' ] ) ) {
			foreach ( $icl_data[ 'icl_lang_status' ] as $translator ) {
				if ( isset( $translator[ 'contract_id' ] ) && $translator[ 'contract_id' ] != 0 ) {
					$result = true;
					break;
				}
			}
		}

		return $result;
	}

	private function service_incomplete_local_jobs_notice() {
		$message_id = 'service_incomplete_local_jobs_notice';
		$show_message = false;

		if ( $this->must_show_incomplete_jobs_notice() ) {
			$translation_filter = array( 'service' => 'local', 'translator' => 0, 'status__not' => ICL_TM_COMPLETE );
			global $iclTranslationManagement;
			$translation_jobs = $iclTranslationManagement->get_translation_jobs( $translation_filter );

			$jobs_count = count( $translation_jobs );
			if ( $jobs_count ) {
				$show_message = true;
				$current_service_name = TranslationProxy::get_current_service_name();

				$args       = array( '<strong>' . $jobs_count . '</strong>', '<strong>' . $current_service_name . '</strong>' );
				$messages[] = vsprintf( _x( "There are %s translation jobs for your local translators. These jobs will not go to %s.", 'Incomplete local jobs after TS activation: [message] Line 01', 'wpml-translation-management' ), $args );
				$args       = array( '<strong>' . $current_service_name . '</strong>' );
				$messages[] = vsprintf( _x( "You will need to cancel these jobs to send this content to %s.", 'Incomplete local jobs after TS activation: [message] Line 03', 'wpml-translation-management' ), $args );

				$message = '<p>' . implode( '</p><p>', $messages ) . '</p>';

				$options   = array();
				$action    = 'wpml_cancel_open_local_translators_jobs';
				$options[] = '<a href="#" data-action="' . $action . '" name="' . $action . '" class="wpml-action button-secondary">'
				             . _x( 'Cancel jobs to local translators', 'Incomplete local jobs after TS activation: [button] Cancel', 'wpml-translation-management' )
				             . '</a>';
				$message .= wp_nonce_field( $action, $action . '_wpnonce', true, true);

				$action    = 'wpml_keep_open_local_translators_jobs';
				$options[] = '<a href="#" data-action="' . $action . '" name="' . $action . '" class="wpml-action button-secondary">'
				             . _x( 'Keep current jobs to local translators', 'Incomplete local jobs after TS activation: [button] Keep', 'wpml-translation-management' )
				             . '</a>';
				$message .= wp_nonce_field( $action, $action . '_wpnonce', true, true);

				if ( $options ) {
					$message .= '<ol><li>';
					$message .= implode( '</li><li>', $options );
					$message .= '</li></ol>';

				}

				$args = array(
					'id'            => $message_id,
					'group'         => 'service_incomplete_local_jobs',
					'classes'       => array( 'wpml-service-activation-notice' ),
					'msg'           => $message,
					'type'          => 'error',
					'admin_notice'  => true,
					'limit_to_page' => array( WPML_TM_FOLDER . '/menu/main.php' ),
				);
				ICL_AdminNotifier::add_message( $args );
			}
		}

		if ( ! $show_message ) {
			ICL_AdminNotifier::remove_message( $message_id );
		}
	}

	private function service_authentication_notice() {
		$message_id = 'current_service_authentication_required';
		if ( ! $this->is_translators_tab() && $this->service_activation_incomplete() ) {
			$current_service_name = TranslationProxy::get_current_service_name();

			if ( defined( 'WPML_TP_DEFAULT_SUID' ) ) {
				$service_tab_name = $current_service_name;
			} else {
				$service_tab_name = __( 'Translators', 'wpml-translation-management' );
			}

			$services_url                = "admin.php?page=" . WPML_TM_FOLDER . "/menu/main.php&sm=translators";
			$href_open                   = '<strong><a href="' . $services_url . '">';
			$href_close                  = '</a></strong>';
			$services_link               = $href_open . $service_tab_name . ' Tab' . $href_close;
			$service_authentication_link = '<strong>' . __( 'Click here to authenticate', 'wpml-translation-management' ) . '</strong>';
			$service_deactivation_link   = '<strong>' . __( 'Click here to deactivate', 'wpml-translation-management' ) . '</strong>';

			if ( defined( 'WPML_TP_DEFAULT_SUID' ) ) {
				$authentication_message = __( "You are using a translation service which requires authentication.", 'wpml-translation-management' );
				$authentication_message .= '<ul>';
				$authentication_message .= '<li>';
				$authentication_message .= sprintf( __( "Please go to %s and use the link %s.", 'wpml-translation-management' ), $services_link, $service_authentication_link );
				$authentication_message .= '</li>';
			} else {

				$problem_detected = false;
				if ( $this->service_requires_authentication() ) {
					$authentication_message = __( "You have selected a translation service which requires authentication.", 'wpml-translation-management' );
				} elseif ( $this->service_requires_translators() ) {
					$authentication_message      = __( "You have selected a translation service which requires translators.", 'wpml-translation-management' );
					$service_authentication_link = '<strong>' . __( 'Add Translator', 'wpml-translation-management' ) . ' &raquo;</strong>';
				} else {
					$problem_detected       = true;
					$authentication_message = __( "There is a problem with your translation service.", 'wpml-translation-management' );
				}

				$authentication_message .= '<ul>';
				$authentication_message .= '<li>';

				if ( $this->service_requires_authentication() ) {
					$authentication_message .= sprintf( __( "If you wish to use %s, please go to %s and use the link %s.", 'wpml-translation-management' ), '<strong>'
					                                                                                                                                        . $current_service_name
					                                                                                                                                        . '</strong>', $services_link, $service_authentication_link );
				} elseif ( $this->service_requires_translators() ) {
					$authentication_message .= sprintf( __( "If you wish to use %s, please go to %s and use the link %s.", 'wpml-translation-management' ), '<strong>'
					                                                                                                                                        . $current_service_name
					                                                                                                                                        . '</strong>', $services_link, $service_authentication_link );
				} elseif ( $problem_detected ) {
					$authentication_message .= sprintf( __( "Please contact your administrator.", 'wpml-translation-management' ), $services_link, $service_authentication_link );
				}

				$authentication_message .= '</li>';

				$authentication_message .= '<li>';
				$authentication_message .= sprintf( __( "If you wish to use only local translators, please go to %s and use the link %s.", 'wpml-translation-management' ), $services_link, $service_deactivation_link );
				$authentication_message .= '</li>';
				$authentication_message .= '</ul>';
			}

			$args = array(
				'id'            => $message_id,
				'group'         => 'current_service_authentication',
				'msg'           => $authentication_message,
				'type'          => 'error',
				'admin_notice'  => true,
				'hide'          => false,
				'limit_to_page' => array( WPML_TM_FOLDER . '/menu/main.php' ),
			);
			ICL_AdminNotifier::add_message( $args );
		} else {
			ICL_AdminNotifier::remove_message( $message_id );
		}
	}

	private function is_tm_page($tab = null) {
		$result = is_admin()
		       && isset( $_GET[ 'page' ] )
		       && $_GET[ 'page' ] == WPML_TM_FOLDER . '/menu/main.php';

		if($tab) {
			$result = $result && isset($_GET['sm']) && $_GET['sm'] == $tab;
		}

		return $result;
	}

	/**
	 * @return bool
	 */
	private function must_show_incomplete_jobs_notice() {
		return $this->is_jobs_tab() && TranslationProxy::get_current_service_id() && !$this->service_activation_incomplete() && ! $this->service_activation_ajax->get_ignore_local_jobs();
	}

}
