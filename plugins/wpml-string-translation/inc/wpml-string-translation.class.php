<?php

class WPML_String_Translation
{
	public $load_priority = 400;
	private $messages = array();
	private $string_filters = array();
	private $strings_autoregister;
	private $active_languages;
	private $current_string_language_cache = array();

	function __construct()
	{
		if ( defined( 'WPML_TM_VERSION' ) ) {
			add_action( 'wpml_tm_loaded', array( $this, 'load' ) );
		} else {
			add_action( 'wpml_loaded', array( $this, 'load' ), $this->load_priority );
		}
		add_action( 'init', array($this, 'verify_wpml') );
		add_action( 'plugins_loaded', array( $this, 'check_db_for_gettext_context' ) , 1000 );
		add_action( 'wpml_language_has_switched', array( $this, 'wpml_language_has_switched' ) );
	}

	function verify_wpml() {
		if ( ! defined('ICL_SITEPRESS_VERSION') ) {
			add_action( 'admin_notices', array('WPML_String_Translation', 'notice_no_wpml') );
		} elseif ( version_compare( ICL_SITEPRESS_VERSION, '3.2', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'wpml_is_outdated' ) );

			return;
		}
	}
	
	static function notice_no_wpml() {
		?>
	    <div class="error">
	        <p><?php _e( 'Please activate WPML Multilingual CMS to have WPML String Translation working.', 'wpml-translation-management' ); ?></p>
	    </div>
	    <?php
	}
	
	function _wpml_not_installed_warning(){
		?>
			<div class="message error"><p><?php printf(__('WPML String Translation is enabled but not effective. Please finish the installation of WPML first.', 'wpml-translation-management') ); ?></p></div>
		<?php
	}

	function wpml_is_outdated(){
		?>
			<div class="message error"><p><?php printf(__('WPML String Translation is enabled but not effective, because WPML is outdated. Please update WPML first.', 'wpml-translation-management') ); ?></p></div>
		<?php
	}

	function init_active_languages() {
		global $sitepress;
		$this->active_languages = array_keys( $sitepress->get_active_languages() );
	}
	
	function load() {
		global $sitepress;

		if ( ! $sitepress ) {
			return;
		} elseif ( ! $sitepress->get_setting( 'setup_complete' ) ) {
			add_action( 'admin_notices', array( $this, '_wpml_not_installed_warning' ) );

			return;
		}
		
		$this->init_active_languages( );
		
		require WPML_ST_PATH . '/inc/admin-texts/wpml-admin-texts.class.php';
		require WPML_ST_PATH . '/inc/widget-text.php';
		require WPML_ST_PATH . '/inc/wpml-localization.class.php';
		require WPML_ST_PATH . '/inc/gettext/wpml-string-translation-mo-import.class.php';

		require WPML_ST_PATH . '/inc/wpml-string-shortcode.php';
		include WPML_ST_PATH . '/inc/slug-translation.php';
		wpml_st_load_admin_texts();

		add_action( 'init', array( $this, 'init' ) );
		wpml_st_load_slug_translation( );
		add_filter( 'pre_update_option_blogname', array( $this, 'pre_update_option_blogname' ), 5, 2 );
		add_filter( 'pre_update_option_blogdescription', array( $this, 'pre_update_option_blogdescription' ), 5, 2 );

		//Handle Admin Notices
		if ( isset( $GLOBALS[ 'pagenow' ] ) && ! ( in_array( $GLOBALS[ 'pagenow' ], array( 'wp-login.php', 'wp-register.php' ) ) ) ) {
			add_action('init', array( 'WPML_String_Translation', '_st_warnings') );
		}

		add_action( 'icl_ajx_custom_call', array( $this, 'ajax_calls' ), 10, 2 );
		add_action( 'init', array( $this, 'set_auto_register_status' ) );

		add_filter( 'WPML_ST_strings_language', array( $this, 'get_strings_language' ) );
		add_filter( 'WPML_ST_strings_context_language', array( $this, 'get_default_context_language' ), 10, 2 );
		add_filter( 'wpml_st_strings_language', array( $this, 'get_strings_language' ) );
		add_filter( 'wpml_st_strings_context_language', array( $this, 'get_default_context_language' ), 10, 2 );

		add_action('wpml_st_delete_all_string_data', array( $this, 'delete_all_string_data'), 10, 1 );
		add_action('wpml_scan_theme_for_strings', array( $this, 'scan_theme_for_strings'), 10, 1 );

		add_filter( 'wpml_st_string_status', array( $this, 'get_string_status_filter' ), 10, 2 );
		add_filter( 'wpml_string_id', array( $this, 'get_string_id_filter' ), 10, 2 );

		do_action( 'wpml_st_loaded' );
	}

	function init() {

		global $sitepress;

		if ( is_admin() ) {
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'thickbox' );
		}


		if ( is_admin() ) {
			require_once WPML_ST_PATH . '/inc/auto-download-locales.php';
			global $WPML_ST_MO_Downloader;
			$WPML_ST_MO_Downloader = new WPML_ST_MO_Downloader();
		}

		$this->plugin_localization();

		add_action( 'admin_menu', array( $this, 'menu' ) );

		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );

		if ( is_admin() && isset( $_GET[ 'page' ] ) && ( $_GET[ 'page' ] == WPML_ST_FOLDER . '/menu/string-translation.php' || $_GET[ 'page' ] == ICL_PLUGIN_FOLDER . '/menu/theme-localization.php' ) ) {
			wp_enqueue_script( 'wp-color-picker' );
			wp_enqueue_style( 'wp-color-picker'  );
			wp_enqueue_script( 'wpml-st-scripts', WPML_ST_URL . '/res/js/scripts.js', array(), WPML_ST_VERSION );
			wp_enqueue_style( 'wpml-st-styles', WPML_ST_URL . '/res/css/style.css', array(), WPML_ST_VERSION );
		}

		if ( $sitepress && $sitepress->get_setting( 'theme_localization_type' ) && $sitepress->get_setting( 'theme_localization_type' ) == 1 ) {
			add_action( 'icl_custom_localization_type', array( $this, 'localization_type_ui' ) );
		}

		add_action( 'wp_ajax_st_theme_localization_rescan', array( $this, 'scan_theme_for_strings' ) );
		add_action( 'wp_ajax_st_plugin_localization_rescan', array( $this, 'scan_plugins_for_strings' ) );
		add_action( 'wp_ajax_icl_st_pop_download', array( $this, 'plugin_po_file_download' ) );
		add_action( 'wp_ajax_icl_st_cancel_local_translation', array( $this, 'icl_st_cancel_local_translation' ) );
		add_action( 'wp_ajax_icl_st_string_status', array( $this, 'icl_st_string_status' ) );

		// add message to WPML dashboard widget
		add_action( 'icl_dashboard_widget_content', array( $this, 'icl_dashboard_widget_content' ) );

		global $icl_st_string_translation_statuses;
		
		$icl_st_string_translation_statuses = array(
			ICL_STRING_TRANSLATION_COMPLETE               => __( 'Translation complete', 'wpml-string-translation' ),
			ICL_STRING_TRANSLATION_PARTIAL                => __( 'Partial translation', 'wpml-string-translation' ),
			ICL_STRING_TRANSLATION_NEEDS_UPDATE           => __( 'Translation needs update', 'wpml-string-translation' ),
			ICL_STRING_TRANSLATION_NOT_TRANSLATED         => __( 'Not translated', 'wpml-string-translation' ),
			ICL_STRING_TRANSLATION_WAITING_FOR_TRANSLATOR => __( 'Waiting for translator', 'wpml-string-translation' )
		);    
		
		return true;
	}

	function plugin_localization()
	{
		load_plugin_textdomain( 'wpml-string-translation', false, WPML_ST_FOLDER . '/locale' );
	}

	/**
	 * @since 2.2.3
	 *
	 * @param            string $context
	 * @param            string $name
	 * @param bool|false        string $original_value
	 * @param boolean|null      $has_translation
	 * @param null|string       $target_lang
	 *
	 * @return string|bool
	 */
	function translate_string( $context, $name, $original_value = false, &$has_translation = null, $target_lang = null ) {

		return icl_translate( $context, $name, $original_value, false, $has_translation, $target_lang );
	}

	static function _st_warnings() {
		if(!class_exists('ICL_AdminNotifier')) return;

		$string_settings = apply_filters('wpml_get_setting', false, 'st' );

		if(isset($string_settings[ 'strings_language' ] )) {
			global $sitepress;
			if ( $sitepress->get_default_language() != $string_settings[ 'strings_language' ] ) {
				self::_st_default_language_warning();
			} elseif ( $string_settings[ 'strings_language' ] != 'en' ) {
				self::_st_default_and_st_language_warning();
			} else {
				ICL_AdminNotifier::removeMessage( '_st_default_and_st_language_warning' );
				ICL_AdminNotifier::removeMessage( '_st_default_language_warning' );
			}
		}
		
		// We removed the message about strings in wrong context
		// We still need to remove the old message otherwise the message will stick
		// Remove these in a later version.
		ICL_AdminNotifier::removeMessage( '_st_string_in_wrong_context_warning' );
		ICL_AdminNotifier::removeMessage( '_st_string_in_wrong_context_warning_short' );
	}

	static function _st_default_language_warning()
	{

		ICL_AdminNotifier::removeMessage( '_st_default_and_st_language_warning' );
		static $called = false;
		if ( !$called ) {
			global $sitepress;
			$languages             = $sitepress->get_active_languages();
			$translation_languages = array();
			foreach ( $languages as $language ) {
				if ( $language[ 'code' ] != 'en' ) {
					$translation_languages[ ] = $language[ 'display_name' ];
				}
			}
			$last_translation_language = $translation_languages[ count( $translation_languages ) - 1 ];
			unset( $translation_languages[ count( $translation_languages ) - 1 ] );
			$translation_languages_list = is_array( $translation_languages ) ? implode( ', ', $translation_languages ) : $translation_languages;

			$message = 'Because your default language is not English, you need to enter all strings in English and translate them to %s and %s.';
			$message .= ' ';
			$message .= '<strong><a href="%s" target="_blank">Read more</a></strong>';

			$message = __( $message, 'Read more string-translation-default-language-not-english', 'wpml-string-translation' );
			$message = sprintf( $message, $translation_languages_list, $last_translation_language, 'https://wpml.org/faq/string-translation-default-language-not-english/' );

			$fallback_message = __( '<a href="%s" target="_blank">How to translate strings when default language is not English</a>' );
			$fallback_message = sprintf( $fallback_message, 'https://wpml.org/faq/string-translation-default-language-not-english/' );

			ICL_AdminNotifier::addMessage( '_st_default_language_warning', $message, 'icl-admin-message-information', true, $fallback_message, false, 'string-translation' );
			$called = true;
		}
	}

	static function _st_default_and_st_language_warning()
	{
		global $sitepress;

		$string_settings = apply_filters('wpml_get_setting', false, 'st' );

		if(isset($string_settings[ 'strings_language' ] )) {
			ICL_AdminNotifier::removeMessage( '_st_default_language_warning' );
			static $called = false;
			if (defined('WPML_ST_FOLDER') && !$called ) {
				$st_language_code = $string_settings[ 'strings_language' ];
				$st_language = $sitepress->get_display_language_name($st_language_code, $sitepress->get_admin_language());

				$page = WPML_ST_FOLDER . '/menu/string-translation.php';
				$st_page_url = admin_url('admin.php?page=' . $page);

				$message = __(
					'The strings language in your site is set to %s instead of English.
					This means that all English texts that are hard-coded in PHP will appear when displaying content in %s.
					<strong><a href="%s" target="_blank">Read more</a> |  <a href="%s#icl_st_sw_form">Change strings language</a></strong>',
				'wpml-string-translation' );

				$message = sprintf( $message, $st_language, $st_language, 'https://wpml.org/faq/string-translation-default-language-not-english/', $st_page_url );

				$fallback_message = __( '<a href="%s" target="_blank">How to translate strings when default language is not English</a>', 'wpml-string-translation' );
				$fallback_message = sprintf( $fallback_message, 'https://wpml.org/faq/string-translation-default-language-not-english/' );

				ICL_AdminNotifier::addMessage( '_st_default_and_st_language_warning', $message, 'icl-admin-message-warning', true, $fallback_message, false, 'string-translation' );
				$called = true;
			}
		}
	}

	function add_message( $text, $type = 'updated' )
	{
		$this->messages[ ] = array( 'type' => $type, 'text' => $text );
	}

	function show_messages()
	{
		if ( !empty( $this->messages ) ) {
			foreach ( $this->messages as $m ) {
				printf( '<div class="%s fade"><p>%s</p></div>', $m[ 'type' ], $m[ 'text' ] );
			}
		}
	}

	function ajax_calls( $call, $data ) {
		require_once WPML_ST_PATH . '/inc/admin-texts/wpml-admin-text-configuration.php';

		switch ( $call ) {

			case 'icl_st_save_translation':
				$icl_st_complete = isset( $data[ 'icl_st_translation_complete' ] ) && $data[ 'icl_st_translation_complete' ] ? ICL_TM_COMPLETE : ICL_TM_NOT_TRANSLATED;
				if ( get_magic_quotes_gpc() ) {
					$data = stripslashes_deep( $data );
				}
				if ( icl_st_is_translator() ) {
					$translator_id = get_current_user_id() > 0 ? get_current_user_id() : null;
				} else {
					$translator_id = null;
				}
				echo icl_add_string_translation( $data[ 'icl_st_string_id' ], $data[ 'icl_st_language' ], stripslashes( $data[ 'icl_st_translation' ] ), $icl_st_complete, $translator_id );
				echo '|';
				global $icl_st_string_translation_statuses;

				$ts = icl_update_string_status( $data[ 'icl_st_string_id' ] );

				if ( icl_st_is_translator() ) {
					$ts = icl_get_relative_translation_status( $data[ 'icl_st_string_id' ], $translator_id );
				}

				echo $icl_st_string_translation_statuses[ $ts ];
				break;
			case 'icl_st_delete_strings':
				$arr = explode( ',', $data[ 'value' ] );
				__icl_unregister_string_multi( $arr );
				break;
			case 'icl_st_option_writes_form':
				if ( !empty( $data[ 'icl_admin_options' ] ) ) {
					$wpml_admin_text = wpml_st_load_admin_texts();
					$wpml_admin_text->icl_register_admin_options( $data[ 'icl_admin_options' ] );
					echo '1|';
				} else {
					echo '0' . __( 'No strings selected', 'wpml-string-translation' );
				}
				break;
			case 'icl_st_ow_export':
				// filter empty options out
				do {
					list( $data[ 'icl_admin_options' ], $empty_found ) = _icl_st_filter_empty_options_out( $data[ 'icl_admin_options' ] );
				} while ( $empty_found );

				if ( !empty( $data[ 'icl_admin_options' ] ) ) {
					foreach ( $data[ 'icl_admin_options' ] as $k => $opt ) {
						if ( !$opt ) {
							unset( $data[ 'icl_admin_options' ][ $k ] );
						}
					}
					$wpml_admin_text_config = new WPML_Admin_Text_Configuration();
					$message = __( 'Save the following to a wpml-config.xml in the root of your theme or plugin.', 'wpml-string-translation' )
						. "<textarea wrap=\"soft\" spellcheck=\"false\">" . htmlentities($wpml_admin_text_config->get_wpml_config_file($data[ 'icl_admin_options' ] )) .
							"</textarea>";
				} else {
					$message = __( 'Error: no strings selected', 'wpml-string-translation' );
				}
				echo json_encode( array( 'error' => 0, 'message' => $message ) );
				break;
		}
	}

	function menu() {
		if ( ! defined( 'ICL_PLUGIN_PATH' ) ) {
			return;
		}

		$setup_complete = apply_filters( 'wpml_get_setting', false, 'setup_complete' );
		if ( ! $setup_complete ) {
			return;
		}

		global $wpdb;
		$existing_content_language_verified = apply_filters( 'wpml_get_setting',
		                                                     false,
		                                                     'existing_content_language_verified' );

		if ( ! $existing_content_language_verified ) {
			return;
		}

		if ( current_user_can( 'wpml_manage_string_translation' ) ) {
			$top_page = apply_filters( 'icl_menu_main_page', basename( ICL_PLUGIN_PATH ) . '/menu/languages.php' );

			add_submenu_page( $top_page,
			                  __( 'String Translation', 'wpml-string-translation' ),
			                  __( 'String Translation', 'wpml-string-translation' ),
			                  'wpml_manage_string_translation',
			                  WPML_ST_FOLDER . '/menu/string-translation.php' );
		} else {
			$string_settings = apply_filters('wpml_get_setting', false, 'st' );
			
			$user_lang_pairs = get_user_meta( get_current_user_id(), $wpdb->prefix . 'language_pairs', true );
			if ( isset( $string_settings[ 'strings_language' ] ) && !empty( $user_lang_pairs[ $string_settings[ 'strings_language' ] ] ) ) {
				add_menu_page( __( 'String Translation', 'wpml-string-translation' ),
				               __( 'String Translation', 'wpml-string-translation' ),
				               'translate',
				               WPML_ST_FOLDER . '/menu/string-translation.php',
				               null,
				               ICL_PLUGIN_URL . '/res/img/icon16.png' );
			}

		}
	}

	function plugin_action_links( $links, $file )
	{
		$this_plugin = basename( WPML_ST_PATH ) . '/plugin.php';
		if ( $file == $this_plugin ) {
			$links[ ] = '<a href="admin.php?page=' . WPML_ST_FOLDER . '/menu/string-translation.php">' .
				__( 'Configure', 'wpml-string-translation' ) . '</a>';
		}

		return $links;
	}

	function localization_type_ui()
	{
		include WPML_ST_PATH . '/menu/theme-localization-ui.php';

	}

	function scan_theme_for_strings( $no_echo = false )
	{
		require_once WPML_ST_PATH . '/inc/gettext/wpml-theme-string-scanner.class.php';
		
		$scan_for_strings = new WPML_Theme_String_Scanner( );
		$scan_for_strings->scan( $no_echo );

	}

	function scan_plugins_for_strings( $no_echo = false )
	{
		require_once WPML_ST_PATH . '/inc/gettext/wpml-plugin-string-scanner.class.php';
		
		$scan_for_strings = new WPML_Plugin_String_Scanner( );
		$scan_for_strings->scan( $no_echo );
		
	}

	// Localization

	function icl_dashboard_widget_content()
	{
		global $wpdb;
		?>

		<div><a href="javascript:void(0)" onclick="jQuery(this).parent().next('.wrapper').slideToggle();"
				style="display:block; padding:5px; border: 1px solid #eee; margin-bottom:2px; background-color: #F7F7F7;"><?php _e( 'String translation', 'wpml-string-translation' ) ?></a></div>
		<div class="wrapper" style="display:none; padding: 5px 10px; border: 1px solid #eee; border-top: 0; margin:-11px 0 2px 0;">
			<p><?php echo __( 'String translation allows you to enter translation for texts such as the site\'s title, tagline, widgets and other text not contained in posts and pages.', 'wpml-string-translation' ) ?></p>
			<?php
			$strings_need_update = $wpdb->get_var( "SELECT COUNT(id) FROM {$wpdb->prefix}icl_strings WHERE status <> 1" );
			?>
			<?php if ( $strings_need_update == 1 ): ?>
				<p>
					<b><?php printf( __( 'There is <a href="%s"><b>1</b> string</a> that needs to be updated or translated. ', 'wpml-string-translation' ), 'admin.php?page=' . WPML_ST_FOLDER . '/menu/string-translation.php&amp;status=0' ) ?></b>
				</p>
			<?php elseif ( $strings_need_update ): ?>
				<p>
					<b><?php printf( __( 'There are <a href="%s"><b>%s</b> strings</a> that need to be updated or translated. ', 'wpml-string-translation' ), 'admin.php?page=' . WPML_ST_FOLDER . '/menu/string-translation.php&amp;status=0', $strings_need_update ) ?></b>
				</p>
			<?php else: ?>
				<p><?php echo __( 'All strings are up to date.', 'wpml-string-translation' ); ?></p>
			<?php endif; ?>

			<p>
				<a class="button secondary" href="<?php echo 'admin.php?page=' . WPML_ST_FOLDER . '/menu/string-translation.php' ?>"><?php echo __( 'Translate strings', 'wpml-string-translation' ) ?></a>
			</p>
		</div>
	<?php
	}

	function plugin_po_file_download( $file = false, $recursion = 0 )
	{
		global $__wpml_st_po_file_content;

		if ( empty( $file ) && !empty( $_GET[ 'file' ] ) ) {
			$file = WP_PLUGIN_DIR . '/' . $_GET[ 'file' ];
		}
		if ( empty( $file ) )
			return;

		if ( is_null( $__wpml_st_po_file_content ) ) {
			$__wpml_st_po_file_content = '';
		}

		require_once WPML_ST_PATH . '/inc/potx.php';

		if ( is_file( $file ) && WP_PLUGIN_DIR == dirname( $file ) ) {

			_potx_process_file( $file, 0, '__pos_scan_store_results', '_potx_save_version', '', POTX_API_7 );
		} else {

			if ( !$recursion ) {
				$file = dirname( $file );
			}

			if ( is_dir( $file ) ) {
				$dh = opendir( $file );
				while ( $dh && false !== ( $f = readdir( $dh ) ) ) {
					if ( 0 === strpos( $f, '.' ) )
						continue;
					$this->plugin_po_file_download( $file . '/' . $f, $recursion + 1 );
				}
			} elseif ( preg_match( '#(\.php|\.inc)$#i', $file ) ) {
				_potx_process_file( $file, 0, '__pos_scan_store_results', '_potx_save_version', '', POTX_API_7 );
			}
		}

		if ( ! $recursion ) {
			$po = "";
			$po .= '# This file was generated by WPML' . PHP_EOL;
			$po .= '# WPML is a WordPress plugin that can turn any WordPress site into a full featured multilingual content management system.' . PHP_EOL;
			$po .= '# https://wpml.org' . PHP_EOL;
			$po .= 'msgid ""' . PHP_EOL;
			$po .= 'msgstr ""' . PHP_EOL;
			$po .= '"Content-Type: text/plain; charset=utf-8\n"' . PHP_EOL;
			$po .= '"Content-Transfer-Encoding: 8bit\n"' . PHP_EOL;
			$po_title = 'WPML_EXPORT';
			if ( isset( $_GET[ 'context' ] ) ) {
				$po_title .= '_' . $_GET[ 'context' ];
			}
			$po .= '"Project-Id-Version:' . $po_title . '\n"' . PHP_EOL;
			$po .= '"POT-Creation-Date: \n"' . PHP_EOL;
			$po .= '"PO-Revision-Date: \n"' . PHP_EOL;
			$po .= '"Last-Translator: \n"' . PHP_EOL;
			$po .= '"Language-Team: \n"' . PHP_EOL;
			$translation_language = 'en';
			if ( isset( $_GET[ 'translation_language' ] ) ) {
				$translation_language = $_GET[ 'translation_language' ];
			}
			$po .= '"Language:' . $translation_language . '\n"' . PHP_EOL;
			$po .= '"MIME-Version: 1.0\n"' . PHP_EOL;

			$po .= $__wpml_st_po_file_content;

			header( "Content-Type: application/force-download" );
			header( "Content-Type: application/octet-stream" );
			header( "Content-Type: application/download" );
			header( 'Content-Transfer-Encoding: binary' );
			header( "Content-Disposition: attachment; filename=\"" . basename( $file ) . ".po\"" );
			header( "Content-Length: " . strlen( $po ) );
			echo $po;
			exit( 0 );
		}

	}

	function estimate_word_count( $string, $lang_code )
	{
		$__asian_languages = array( 'ja', 'ko', 'zh-hans', 'zh-hant', 'mn', 'ne', 'hi', 'pa', 'ta', 'th' );
		$words             = 0;
		if ( in_array( $lang_code, $__asian_languages ) ) {
			$words += strlen( strip_tags( $string ) ) / 6;
		} else {
			$words += count( explode( ' ', strip_tags( $string ) ) );
		}

		return (int)$words;
	}

	function icl_st_cancel_local_translation() {
		$id        = filter_input( INPUT_POST, 'id', FILTER_VALIDATE_INT );
		$string_id = $this->cancel_local_translation( $id, true );
		echo wp_json_encode( array( 'string_id' => $string_id ) );
		exit;
	}

	function cancel_remote_translation( $rid ) {
		/** @var wpdb $wpdb */
		global $wpdb;

		$translation_ids = $wpdb->get_col( $wpdb->prepare( "	SELECT string_translation_id
															FROM {$wpdb->prefix}icl_string_status
															WHERE rid = %d",
		                                                   $rid ) );
		$cancel_count    = 0;
		foreach ( $translation_ids as $translation_id ) {
			$res          = (bool) $this->cancel_local_translation( $translation_id );
			$cancel_count = $res ? $cancel_count + 1 : $cancel_count;
		}

		return $cancel_count;
	}

	function cancel_local_translation( $id, $return_original_id = false ) {
		global $wpdb;
		$string_id = $wpdb->get_var( $wpdb->prepare( "	SELECT string_id
														FROM {$wpdb->prefix}icl_string_translations
														WHERE id=%d AND status IN (%d, %d)",
		                                             $id,
		                                             ICL_TM_IN_PROGRESS,
		                                             ICL_TM_WAITING_FOR_TRANSLATOR ) );
		if ( $string_id ) {
			$wpdb->update( $wpdb->prefix . 'icl_string_translations',
			               array(
				               'status'              => ICL_TM_NOT_TRANSLATED,
				               'translation_service' => null,
				               'translator_id'       => null,
				               'batch_id'            => null
			               ),
			               array( 'id' => $id ) );
			icl_update_string_status( $string_id );
			$res = $return_original_id ? $string_id : $id;
		} else {
			$res = false;
		}

		return $res;
	}

	function icl_st_string_status()
	{
		global $wpdb, $icl_st_string_translation_statuses;
		$string_id = $_POST[ 'string_id' ];
		echo $icl_st_string_translation_statuses[ ( $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}icl_strings WHERE id=%d", $string_id ) ) ) ];
		exit;
	}
	
	function pre_update_option_blogname($value, $old_value) {
		return $this->pre_update_option_settings("Blog Title", $value, $old_value);
	}
	
	function pre_update_option_blogdescription($value, $old_value) {
		return $this->pre_update_option_settings("Tagline", $value, $old_value);
	}

	function pre_update_option_settings( $option, $value, $old_value ) {
		global $sitepress, $sitepress_settings, $switched;

		if ( ! $switched || ( $switched && wpml_get_setting_filter( false, 'setup_complete' ) ) ) {
			$current_language = $sitepress->get_current_language();
			$strings_language = $sitepress_settings[ 'st' ][ 'strings_language' ];
			if ( $current_language == $strings_language ) {
				return $value;
			}

			WPML_Config::load_config_run();
			$result = icl_update_string_translation( $option, $current_language, $value, ICL_TM_COMPLETE );
			if ( $result ) {
				// returning old_value in place of value will stop update_option() processing.
				// do not remove it!
				return $old_value;
			}
		}

		return $value;
	}

	public function clear_string_filter( $lang ) {
		unset( $this->string_filters[ $lang ] );
	}

	public function get_string_filter( $lang ) {
		global $sitepress_settings, $wpdb, $sitepress;

		$this->maybe_stat_string_filters();
		if ( (bool) $this->active_languages === true
			 && in_array( $lang, $this->active_languages )
			 && isset( $sitepress_settings[ 'st' ][ 'db_ok_for_gettext_context' ] )
		) {
			if ( ! $this->strings_autoregister ) {
				$this->string_filters[ $lang ] = isset( $this->string_filters[ $lang ] )
					? $this->string_filters[ $lang ] : new WPML_Displayed_String_Filter( $wpdb, $sitepress, $lang );
			} else {
				$this->string_filters[ $lang ] = $this->get_admin_string_filter( $lang );
			}

			return $this->string_filters[ $lang ];
		} else {
			return null;
		}
	}

	public function get_admin_string_filter( $lang ) {
		global $sitepress_settings, $wpdb, $sitepress;

		$this->maybe_stat_string_filters();
		if ( isset( $sitepress_settings['st']['db_ok_for_gettext_context'] ) ) {
			if ( ! ( isset( $this->string_filters[ $lang ] )
			         && get_class( $this->string_filters[ $lang ] ) == 'WPML_Admin_String_Filter' )
			) {
				$this->string_filters[ $lang ] = isset( $this->string_filters[ $lang ] ) ? $this->string_filters[ $lang ] : false;
				$this->string_filters[ $lang ] = new WPML_Admin_String_Filter( $wpdb,
				                                                               $sitepress,
				                                                               $lang,
				                                                               $this->string_filters[ $lang ] );
			}

			return $this->string_filters[ $lang ];
		} else {
			return null;
		}
	}
	
	public static function clear_use_original_cache_setting( ) {
		$string_settings = apply_filters( 'wpml_get_setting', false, 'st' );
		unset( $string_settings[ 'use_original_cache' ] );
		do_action( 'wpml_set_setting', 'st', $string_settings, true );
	}

	public function set_auto_register_status() {
		$string_settings = apply_filters('wpml_get_setting', false, 'st' );
		$icl_st_auto_reg = isset($string_settings[ 'icl_st_auto_reg' ]) ? $string_settings[ 'icl_st_auto_reg' ] : false;
		$auto_reg        = filter_var( $icl_st_auto_reg, FILTER_SANITIZE_STRING );

		$this->strings_autoregister = $auto_reg == 'auto-always' || ( $auto_reg == 'auto-admin' && current_user_can( 'manage_options' ) );

		return $this->strings_autoregister;
	}

	public function get_strings_language( $language = '' ) {
		$string_settings = $this->get_strings_settings();

		$string_language = $language ? $language : 'en';
		if ( isset( $string_settings[ 'strings_language' ] ) ) {
			$string_language = $string_settings[ 'strings_language' ];
		}

		return $string_language;
	}

	//TODO: [WPML 3.3] This will be needed in future and for forward compatibility, is currently needed in Package Translation
	public function get_default_context_language( $language, $context_name ) {
		$context_language = $this->get_strings_language( $language );
		if ( $context_name ) {
			$context_language = 'en';
		}

		return $context_language;
	}

	public function delete_all_string_data($string_id) {
		global $wpdb;

		$icl_string_positions_query    = "DELETE FROM {$wpdb->prefix}icl_string_positions WHERE string_id=%d";
		$icl_string_status_query       = "DELETE FROM {$wpdb->prefix}icl_string_status WHERE string_translation_id IN (SELECT id FROM {$wpdb->prefix}icl_string_translations WHERE string_id=%d)";
		$icl_string_translations_query = "DELETE FROM {$wpdb->prefix}icl_string_translations WHERE string_id=%d";
		$icl_strings_query             = "DELETE FROM {$wpdb->prefix}icl_strings WHERE id=%d";

		$icl_string_positions_prepare    = $wpdb->prepare( $icl_string_positions_query, $string_id );
		$icl_string_status_prepare       = $wpdb->prepare( $icl_string_status_query, $string_id );
		$icl_string_translations_prepare = $wpdb->prepare( $icl_string_translations_query, $string_id );
		$icl_strings_prepare             = $wpdb->prepare( $icl_strings_query, $string_id );

		$wpdb->query( $icl_string_positions_prepare );
		$wpdb->query( $icl_string_status_prepare );
		$wpdb->query( $icl_string_translations_prepare );
		$wpdb->query( $icl_strings_prepare );

	}

	public function get_strings_settings() {
		global $sitepress;

		if ( version_compare( ICL_SITEPRESS_VERSION, '3.2', '<' ) ) {
			global $sitepress_settings;

			$string_settings = isset($sitepress_settings['st']) ? $sitepress_settings['st'] : array();
		} else {
			$string_settings = $sitepress ? $sitepress->get_string_translation_settings() : array();
		}

		$string_settings[ 'strings_language' ] = 'en';
		if ( ! isset( $string_settings[ 'icl_st_auto_reg' ] ) ) {
			$string_settings[ 'icl_st_auto_reg' ] = 'disable';
		}
		$string_settings[ 'strings_per_page' ] = ICL_STRING_TRANSLATION_AUTO_REGISTER_THRESHOLD;

		return $string_settings;
	}
	
	function send_strings_to_translation_service( $string_ids, $target_language, $basket_name, $translator_id ) {
		global $wpdb;
		// get all the untranslated strings
		$untranslated = array();
		foreach ( $string_ids as $st_id ) {
			$untranslated[ ] = $st_id;
		}

		if ( sizeof( $untranslated ) > 0 ) {
			$project = TranslationProxy::get_current_project();

			$strings    = array();
			$word_count = 0;

			$source_language = $this->get_strings_language();
			foreach ( $untranslated as $string_id ) {
				$string_data_query   = "SELECT id, context, name, value FROM {$wpdb->prefix}icl_strings WHERE id=%d";
				$string_data_prepare = $wpdb->prepare( $string_data_query, $string_id );
				$string_data         = $wpdb->get_row( $string_data_prepare );
				$word_count += $this->estimate_word_count( $string_data->value, $source_language );
				$strings[ ] = $string_data;
			}

			$xliff = new WPML_TM_xliff();
			$file = $xliff->get_strings_xliff_file( $strings, $source_language, $target_language );

			$title     = "String Translations";
			$cms_id    = '';
			$url       = '';
			$timestamp = date( 'Y-m-d H:i:s' );

			if ( TranslationProxy::is_batch_mode() ) {
				$res = $project->send_to_translation_batch_mode( $file,
				                                                 $title,
				                                                 $cms_id,
				                                                 $url,
				                                                 $source_language,
				                                                 $target_language,
				                                                 $word_count );
			} else {
				$res = $project->send_to_translation( $file,
				                                      $title,
				                                      $cms_id,
				                                      $url,
				                                      $source_language,
				                                      $target_language,
				                                      $word_count );
			}

			if ( $res ) {
				foreach ( $strings as $string_data ) {


					$batch_id            = TranslationProxy_Batch::update_translation_batch( $basket_name );
					$translation_service = TranslationProxy_Service::get_translator_data_from_wpml( $translator_id );
					$added               = icl_add_string_translation( $string_data->id,
					                                                   $target_language,
					                                                   null,
					                                                   ICL_TM_WAITING_FOR_TRANSLATOR,
					                                                   $translation_service[ 'translator_id' ],
					                                                   $translation_service[ 'translation_service' ],
					                                                   $batch_id );
					if ( $added ) {
						$data = array(
							'rid'                   => $res,
							'string_translation_id' => $added,
							'timestamp'             => $timestamp,
							'md5'                   => md5( $string_data->value ),
						);
						$wpdb->insert( $wpdb->prefix . 'icl_string_status', $data ); //insert rid
					} else {
						$this->add_message( sprintf( __( 'Unable to add "%s" string in tables', 'sitepress' ),
						                             $string_data->name ),
						                    'error' );

						return 0;
					}
				}
				$wpdb->insert( $wpdb->prefix . 'icl_core_status',
				               array(
					               'rid'    => $res,
					               'module' => '',
					               'origin' => $source_language,
					               'target' => $target_language,
					               'status' => CMS_REQUEST_WAITING_FOR_PROJECT_CREATION
				               ) );

				if ( $project->errors && count( $project->errors ) ) {
					$res[ 'errors' ] = $project->errors;
				}

				return $res;
			}
		}

		return 0;
	}

	function get_string_job_id( $args ) {
		global $wpdb;

		$where_string = false;
		if ( $args ) {
			foreach ( $args as $key => $value ) {
				if ( $where_string ) {
					$where_string .= ' AND ';
				}
				$where_string .= $key . ' = %d';
			}
		}

		if ( $where_string ) {
			$where_values = array_values( $args );
			$job_id       = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}icl_string_status WHERE " . $where_string, $where_values ) );

			if ( $job_id !== null ) {
				return $job_id;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param null $empty   Not used, but needed for the hooked filter
	 * @param int $string_id
	 *
	 * @return null|string
	 */
	public function get_string_status_filter( $empty = null, $string_id ) {
		return $this->get_string_status( $string_id );
	}

	/**
	 * @param int|null $default     Set the default value to return in case no string or more than one string is found
	 * @param array    $string_data {
	 *
	 * @type string    $context
	 * @type string    $name        Optional
	 *                           }
	 * @return int|null If there is more than one string_id, it will return the value set in $default.
	 */
	public function get_string_id_filter( $default = null, $string_data ) {
		$result = $default;

		$string_id = $this->get_string_id( $string_data );

		return $string_id ? $string_id : $result;
	}

	protected function get_string_status( $string_id ) {
		global $wpdb;
		$status = $wpdb->get_var( $wpdb->prepare( "
		            SELECT	MIN(status)
		            FROM {$wpdb->prefix}icl_string_translations
		            WHERE
		                string_id=%d
		            ", $string_id ) );

		return $status !== null ? (int)$status : null;
	}

	/**
	 * @param array $string_data {
	 *
	 * @type string $context
	 * @type string $name        Optional
	 *                           }
	 * @return int|null
	 */
	private function get_string_id( $string_data ) {
		$context = isset( $string_data[ 'context' ] ) ? $string_data[ 'context' ] : null;
		$name    = isset( $string_data[ 'name' ] ) ? $string_data[ 'name' ] : null;

		$result = null;
		if ( $name && $context ) {
			global $wpdb;
			$string_id_query = "SELECT id FROM {$wpdb->prefix}icl_strings WHERE context=%s";
			$string_id_args  = array( $context );
			if ( $name ) {
				$string_id_query .= ' AND name=%s';
				$string_id_args[ ] = $name;
			}
			$string_id_prepare = $wpdb->prepare( $string_id_query, $string_id_args );
			$string_id         = $wpdb->get_var( $string_id_prepare );

			$result = (int) $string_id;
		}

		return $result;
	}

	/**
	 * Requires the string filter classes in case no string filter has yet been set.
	 */
	private function maybe_stat_string_filters() {
		$string_filters = array_filter( $this->string_filters );
		if ( empty( $string_filters ) ) {
			require_once WPML_ST_PATH . '/inc/filters/wpml-displayed-strings-filter.class.php';
			require_once WPML_ST_PATH . '/inc/filters/wpml-admin-string-filter.class.php';
		}
	}
	
	function check_db_for_gettext_context( ) {
		$string_settings = apply_filters( 'wpml_get_setting', false, 'st' );
		if ( ! isset( $string_settings[ 'db_ok_for_gettext_context' ] ) ) {
			
			if ( function_exists( 'icl_table_column_exists' ) && icl_table_column_exists( 'icl_strings', 'domain_name_context_md5' ) ) {
				$string_settings[ 'db_ok_for_gettext_context' ] = true;
				do_action( 'wpml_set_setting', 'st', $string_settings, true );
			}
		}
	}

	public function initialize_wp_and_widget_strings( ) {
		$this->check_db_for_gettext_context( );
		
		icl_register_string('WP',__('Blog Title','wpml-string-translation'), get_option('blogname'));
		icl_register_string('WP',__('Tagline', 'wpml-string-translation'), get_option('blogdescription'));

		__icl_st_init_register_widget_titles();
		
		// create a list of active widgets
		$active_text_widgets = array();
		$widgets = (array)get_option('sidebars_widgets');
		foreach($widgets as $k=>$w){             
			if('wp_inactive_widgets' != $k && $k != 'array_version'){
				if(is_array($widgets[$k])){
					foreach($widgets[$k] as $v){
						if(preg_match('#text-([0-9]+)#i',$v, $matches)){
							$active_text_widgets[] = $matches[1];
						}                            
					}
				}
			}
		}
														
		$widget_text = get_option('widget_text');
		if(is_array($widget_text)){
			foreach($widget_text as $k=>$w){
				if(!empty($w) && isset($w['title']) && in_array($k, $active_text_widgets)){
					icl_register_string('Widgets', 'widget body - ' . md5($w['text']), $w['text']);
				}
			}
		}
	}
	
	/**
	 * @param $name
	 * Returns the language the current string is to be translated into.
	 *
	 * @return string
	 */
	public function get_current_string_language( $name ) {
		
		if ( isset( $this->current_string_language_cache[ $name ] ) ) {
			return $this->current_string_language_cache[ $name ];
		}
	
		if ( defined( 'DOING_AJAX' ) ) {
			$current_language = apply_filters( 'WPML_get_language_cookie', '' );
		} else {
			$current_language = apply_filters( 'WPML_get_current_language', '' );
		}
	
		/*
		 * The logic for this is a little different in the admin backend. Here we always use the user admin language if the admin backend is accessed.
		 * We have to take care of two exceptions though.
		 * 1. Plugins doing admin ajax calls in the frontend.
		 * 2. Certain strings are to always be translated in the admin backend.
		 * 3. We have to handle special exception when check_if_admin_action_from_referrer is not available yet (during upgrade)
		 */
		if ( version_compare( ICL_SITEPRESS_VERSION, '3.1.7.2', '>' ) ) {
			if ( defined( 'WP_ADMIN' ) && ( apply_filters( 'WPML_is_admin_action_from_referer',
														   false ) || ! defined( 'DOING_AJAX' ) ) && ! is_translated_admin_string( $name )
			) {
				$current_user = apply_filters( 'WPML_current_user', '' );
				if ( isset( $current_user->ID ) ) {
					$admin_display_lang = apply_filters( 'WPML_get_user_admin_language', '', $current_user->ID );
					$current_language   = $admin_display_lang ? $admin_display_lang : $current_language;
				}
			}
		}
	
		$ret = apply_filters( 'icl_current_string_language', $current_language, $name );
	
		$this->current_string_language_cache[ $name ] = $ret;
	
		return $ret;
	}
	
	public function wpml_language_has_switched( ) {
		// clear the current language cache
		$this->current_string_language_cache = array();
	}
	
	
}

function __pos_scan_store_results( $string, $domain, $file, $line )
{
	global $__wpml_st_po_file_content;
	static $strings = array();

	//avoid duplicates
	if ( isset( $strings[ $domain ][ $string ] ) ) {
		return false;
	}
	$strings[ $domain ][ $string ] = true;

	$file = @file( $file );
	if ( !empty( $file ) ) {
		$__wpml_st_po_file_content .= PHP_EOL;
		$__wpml_st_po_file_content .= '# ' . @trim( $file[ $line - 2 ] ) . PHP_EOL;
		$__wpml_st_po_file_content .= '# ' . @trim( $file[ $line - 1 ] ) . PHP_EOL;
		$__wpml_st_po_file_content .= '# ' . @trim( $file[ $line ] ) . PHP_EOL;
	}

	//$__wpml_st_po_file_content .= 'msgid "'.str_replace('"', '\"', $string).'"' . PHP_EOL;
	$__wpml_st_po_file_content .= PHP_EOL;
	$__wpml_st_po_file_content .= 'msgid "' . $string . '"' . PHP_EOL;
	$__wpml_st_po_file_content .= 'msgstr ""' . PHP_EOL;

}
