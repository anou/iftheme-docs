<?php
/**
 * Translation Analytics
 * Holds the functionality for sending and displaying Translation Snapshots.
 */

class WPML_Translation_Analytics {

	private $messages = array();
	private $ta_settings = array();

	function __construct() {

		global $sitepress;

		add_action( 'init', array( $this, 'init' ) );
	}

	function __destruct() {
	}

	function get_enable_analytics_message() {
		$message    = __( "WPML can help you track the progress of your site's translation. You will receive concise reports about what you've sent to translation and how translation is advancing.", 'wpml-translation-management' );
		return $message;
	}

	function schedule_translation_analytics_snapshot() {
		wp_schedule_event( current_time( 'timestamp' ), TA_SCHEDULE_OCCURENCE, 'icl_send_translation_snapshots' );
	}

	function clear_schedule_translation_analytics_snapshot() {
		wp_clear_scheduled_hook( 'icl_send_translation_snapshots' );
	}

	function enable_analytics() {
		global $sitepress;

		$status = (bool) ( isset( $_POST[ 'icl-toggle-analytics-status' ] ) ? $_POST[ 'icl-toggle-analytics-status' ] : $sitepress->get_setting( 'enable_analytics' ));
		$sitepress->set_setting( 'enable_analytics', $status );
		$sitepress->save_settings();

		if ($status) {
			$this->schedule_translation_analytics_snapshot();
		} else {
			$this->clear_schedule_translation_analytics_snapshot();
		}

		$redirect_url = admin_url('admin.php?page=' . WPML_TM_FOLDER . '/menu/analytics.php');
		echo wp_json_encode($redirect_url);
		exit;
	}
	
	function promote_analytics() {
		global $sitepress;
		
		if ( ! $sitepress->get_setting( 'enable_analytics' ) ) {
			?>
			<div class="tm-analytics-promote">
				<h1><?php _e('Done!', 'wpml-translation-management'); ?></h1>
				<p>
					<?php echo $this->get_enable_analytics_message(); ?>
					<br />
					<a href="#" id="icl-toggle-analytics" data-status="1"><?php _e( "Start using Translation Analytics", 'wpml-translation-management' ); ?></a>
				</p>
			</div>
			<?php
		}
		exit;
	}

	function load_settings() {
		$this->ta_settings = get_option( 'wpml_ta_settings', array() );
	}

	function save_settings() {
		update_option ( 'wpml_ta_settings', $this->ta_settings );
	}

	function init() {

		global $sitepress;
		$schedule_translation_analytics = wp_get_schedule( 'icl_send_translation_snapshots' );

		$service = $sitepress->get_setting( "translation_service" );
		if ( is_object( $service ) ) {
			if ( $schedule_translation_analytics != "" ) {
				$this->clear_schedule_translation_analytics_snapshot();
			}
			return;
		}

		// If WPML not active, doesn't load the plugin
		if ( ! $this->is_wpml_active() ) {
			return;
		}

		if ( is_admin() ) {
			if ( ! defined( 'DOING_AJAX' ) ) {
				wp_enqueue_script( 'wpml-ta', WPML_TM_URL . '/res/js/translation-analytics.js', array( 'jquery' ), WPML_TM_VERSION );

				add_action( 'admin_menu', array( $this, 'menu' ) );
				if (!defined("DOING_AJAX")) {
					wp_enqueue_script( 'custom_js', WPML_TM_URL .'/res/js/iframeResizer.min.js', array('jquery'), WPML_TM_VERSION );
				}
			}
			add_action( 'wp_ajax_icl-toggle-analytics', array( $this, 'enable_analytics' ) );
			add_action( 'wp_ajax_icl-promote-analytics', array( $this, 'promote_analytics' ) );
			// add_action( 'wpml_before_mce_setup_html', array( $this, 'get_options_menu_dashboard' ));
		}

		if ( $sitepress->get_setting( 'enable_analytics' ) ) {

			$this->load_settings();

			// add message to WPML dashboard widget
			add_action( 'icl_dashboard_widget_content', array( $this, 'icl_dashboard_widget_content' ) );
			add_action( 'icl_send_translation_snapshots', array( $this, 'send_translation_snapshots' ) );

			if ( $schedule_translation_analytics == "" ) {
				$this->schedule_translation_analytics_snapshot();
			}
		}
	}

	/**
	 * Menu item to appear under WPML menu.
	 */
	function menu()
	{
		$top_page = apply_filters( 'icl_menu_main_page', basename( ICL_PLUGIN_PATH ) . '/menu/languages.php' );
		add_submenu_page( $top_page, __( 'Translation Analytics', 'wpml-translation-management' ), __( 'Translation Analytics', 'wpml-translation-management' ), 'wpml_manage_translation_analytics', WPML_TM_FOLDER . '/menu/analytics.php' );
	}

	function get_options_menu_dashboard() {
		global $sitepress;
		$ta_enabled = $sitepress->get_setting( "enable_analytics" );
		?>
		<div class="wpml-section" id="translation_analytics_options_div">
			<div class="wpml-section-header">
				<h3><?php _e('Translation Analytics', 'wpml-translation-management');?></h3>
			</div>

			<div class="wpml-section-content">
				<?php wp_nonce_field('wpml_translation_analytics_nonce', '_wpml_ta_nonce') ?>

				<p>
					<label>
						<input name="translation_analitycs_enable" type="checkbox" value="1" <?php echo $ta_enabled ? "checked" : "" ?> />
						<?php echo __('Use Translation Analytics', 'wpml-translation-management'); ?>
					</label>
				</p>

				<p class="buttons-wrap">
					<span class="icl_ajx_response" id="icl_ajx_response_dtm"></span>
					<input type="submit" class="button-primary" value="<?php _e('Save', 'wpml-translation-management')?>" />
				</p>

				<input type="hidden" name="translation_analytics_alert_message" value="<?php echo $this->get_alert_message(); ?>" />
				<input type="hidden" name="translation_analytics_enabled" value="<?php echo $ta_enabled ?>" />
			</div>

		</div>
		<?php
	}

	/**
	 * The content to be displayed on the dashboard widget.
	 */
	function icl_dashboard_widget_content() {
		?>
		<div>
			<a href="javascript:void(0)" onclick="jQuery(this).parent().next('.wrapper').slideToggle();" style="display:block; padding:5px; border: 1px solid #eee;
                margin-bottom:2px; background-color: #F7F7F7;">
				<?php _e( 'Translation Analytics', 'wpml-translation-management' ) ?>
			</a>
		</div>

		<div class="wrapper" style="display:none; padding: 5px 10px;
            border: 1px solid #eee; border-top: 0px; margin:-11px 0 2px 0;">
			<p>
				<?php echo __( 'WPML Translation Analytics allows you to see the
                status of your translations and shows you warnings when
                completion time may not be met based on planned schedule
                versus actual progress.', 'wpml-translation-management' ) ?>
			</p>

			<p>
				<a class="button secondary" href="
                    <?php echo 'admin.php?page=' . basename( WPML_TM_PATH ) . '/menu/analytics.php'?>">
					<?php echo __( 'View Translation Analytics', 'wpml-translation-management' ) ?>
				</a>
			</p>
		</div>
	<?php
	}

	/**
	 * Alows adding messages to the plugin message area.
	 *
	 * @param string $text The message to be displayed
	 * @param string $type The type of message to be displayed
	 */
	function add_message( $text, $type = 'updated' ) {
		$this->messages[ ] = array( 'type' => $type, 'text' => $text );
	}

	/**
	 * Displays the current plugin messages.
	 */
	function show_messages() {
		if ( !empty( $this->messages ) ) {
			foreach ( $this->messages as $m ) {
				printf( '<div class="%s fade"><p>%s</p></div>', $m[ 'type' ], $m[ 'text' ] );
			}
		}
	}

	function get_alert_message() {
		echo __('Translation Analytics lets you track the progress of your translation jobs. Are you sure you want to disable it?', 'wpml-translation-management');
	}

	function create_user_account() {
		global $sitepress, $wpdb;

		$user = array();
		$user['create_account'] = 1;
		$user['anon'] = 1;
		$user['platform_kind'] = 2;
		$user['cms_kind'] = 1;
		$user['blogid'] = $wpdb->blogid ? $wpdb->blogid : 1;
		$user['url'] = get_option('home');
		$user['title'] = get_option('blogname');
		$user['description'] =  $sitepress->get_setting('icl_site_description', '');
		$user['is_verified'] = 1;
		$user['interview_translators'] = $sitepress->get_setting('interview_translators', '');
		$user['project_kind'] = $sitepress->get_setting('website_kind', 2);
		$user['pickup_type'] = intval( $sitepress->get_setting('translation_pickup_method') );
		$user['ignore_languages'] = 1;

		if (defined('ICL_AFFILIATE_ID') && defined('ICL_AFFILIATE_KEY')) {
			$user['affiliate_id'] = ICL_AFFILIATE_ID;
			$user['affiliate_key'] = ICL_AFFILIATE_KEY;
		}
		$notifications = 0;
		$icl_notify_complete = $sitepress->get_setting( "icl_notify_complete", null );
		if ( null !== $icl_notify_complete ) {
			if ($icl_notify_complete) {
				$notifications += 1;
			}

			$alert_delay = $sitepress->get_setting( 'alert_delay' );
			if ( $alert_delay ) {
				$notifications += 2;
			}
		}
		$user['notifications'] = $notifications;

		return $user;
	}

	function create_icl_account(){
		global $sitepress;

		$site_id = false;
		$access_key = false;

		$user = $this->create_user_account();

		require_once ICL_PLUGIN_PATH . '/lib/icl_api.php';
		$icl_query = new ICanLocalizeQuery();
		list($site_id, $access_key) = $icl_query->createAccount($user, TA_URL_ENDPOINT);

		if ( ! $site_id ) {
			$user['pickup_type'] = ICL_PRO_TRANSLATION_PICKUP_POLLING;
			list($site_id, $access_key) = $icl_query->createAccount($user, TA_URL_ENDPOINT);
		}

		if ( $site_id ) {
			if($user['pickup_type'] == ICL_PRO_TRANSLATION_PICKUP_POLLING){
				$sitepress->set_setting('translation_pickup_method', ICL_PRO_TRANSLATION_PICKUP_POLLING);
			}

			$icl_query = new ICanLocalizeQuery( $site_id, $access_key );
			$website_details = $icl_query->get_website_details( TA_URL_ENDPOINT );
			TranslationProxy_Translator::get_icl_translator_status( $website_details );
		}

		return array($site_id, $access_key);
	}

	function create_ta_account()
	{
		list($site_id, $access_key) = $this->create_icl_account();

		if ( $site_id ) {
			$this->ta_settings['setup_complete'] = true;
			$this->ta_settings['ta_site_id']     = $site_id;
			$this->ta_settings['ta_access_key']  = $access_key;
			$this->save_settings();
		}
	}
	/**
	 * Displays a frame showing the translation analytics obtained from
	 * ICanLocalize.
	 */
	function show_translation_analytics_dashboard() {
		global $sitepress;


		if ( ! isset ( $this->ta_settings[ 'setup_complete' ] ) || ! $this->ta_settings[ 'setup_complete' ] ) {
				// create a new ta project
			$this->create_ta_account();
		}

		// Try sending first translation snapshot, if nothing was sent yet
		if ( ! isset( $this->ta_settings[ 'snapshot_word_count' ] ) ) {
			$this->send_translation_snapshots();
		}

		if ( $this->is_ready() ) {

			$service_id = false;

			$translation_analytics_link =
				TA_URL_ENDPOINT . '/translation_analytics/overview?' .
				'accesskey=' . $this->ta_settings['ta_access_key'] .
				'&wid=' . $this->ta_settings['ta_site_id'] .
				'&project_id=' . $this->ta_settings['ta_site_id'] .
				'&project_type=Website' .
				'&lc=' . $sitepress->get_locale( $sitepress->get_admin_language() ) .
				'&from_cms=1';

			echo "<iframe seamless=\"seamless\" id=\"ifm\" src=\"" . $translation_analytics_link . "\"></iframe>";

		} else {

			echo __( 'An unknown error has occurred when configuring with the Translation Analytics server. Please try again.', 'wpml-translation-management' ) . '<br/><br/>';

		}

	}

	function is_ready() {
		if ( isset( $this->ta_settings[ 'setup_complete' ] ) && $this->ta_settings[ 'setup_complete' ] ) {
			return true;
		}
		return false;
	}

	/** * Creates and sends translation snapshots.  *
	 * Called by the scheduled cron event.
	 */
	function send_translation_snapshots() {
		global $sitepress;

		if ( $this->is_ready() ) {
			$jobs             = $this->get_translation_jobs();
			$total_word_count = $this->get_translation_word_count( $jobs );

			// Do not send snapshots when nothing changed
			if ( isset( $this->ta_settings[ 'snapshot_word_count' ] ) && ( $this->ta_settings[ 'snapshot_word_count' ] == $total_word_count ) ) {
				return;
			} else {
				$this->ta_settings[ 'snapshot_word_count' ] = $total_word_count;
				$this->save_settings();
			}

			$datetime = new DateTime();
			if ( !empty( $total_word_count[ 'total' ] ) ) {
				foreach ( $total_word_count[ 'total' ] as $from => $to_list ) {
					foreach ( $to_list as $to => $value ) {
						// Convert language names to icanlocalize format

						$from_language_name = $this->server_languages_map( $from );
						$to_language_name   = $this->server_languages_map( $to );

						$params = array(
							'date'               => $datetime->format( "Y-m-d\TH:i:s-P" ),
							'from_language_name' => $from_language_name,
							'to_language_name'   => $to_language_name,
							'words_to_translate' => $value,
							'translated_words'   => $total_word_count[ 'finished' ][ $from ][ $to ],
							'accesskey'         => $this->ta_settings[ 'ta_access_key' ],
							'website_id'         => $this->ta_settings[ 'ta_site_id' ]
						);

						$url      = TA_URL_ENDPOINT . "/translation_snapshots/create_by_cms.xml";
						$response = wp_remote_post( $url, array( 'body' => $params ) );

						if ( is_wp_error( $response ) ) {
							error_log( 'Translation Analytics: Could not send translation snapshot:\n' . $response->get_error_message() );
						}
					}
				}
			}
		}
	}

	/**
	 * Retrieves translations jobs from the database.
	 *
	 * @param string $service The service used on the translation jobs
	 *
	 * @return array The retrieved jobs
	 */
	function get_translation_jobs( $service = 'local' ) {
		require_once ICL_PLUGIN_PATH . '/inc/translation-management/translation-management.class.php';

		global $wpdb;
		$where = " s.status > " . ICL_TM_NOT_TRANSLATED;
		$where .= " AND s.status <> " . ICL_TM_DUPLICATE;

		// Getting translations for all services
		//$where .= " AND s.translation_service='{$service}'";

		$orderby = ' j.job_id DESC ';

		$jobs = $wpdb->get_results( "SELECT j.job_id, t.trid, t.language_code, t.source_language_code,
            l1.english_name AS language_name, l2.english_name AS source_language_name,
            s.translation_id, s.status, s.translation_service 
            FROM {$wpdb->prefix}icl_translate_job j
                JOIN {$wpdb->prefix}icl_translation_status s ON j.rid = s.rid
                JOIN {$wpdb->prefix}icl_translations t ON s.translation_id = t.translation_id
                JOIN {$wpdb->prefix}icl_languages l1 ON t.language_code  = l1.code
                JOIN {$wpdb->prefix}icl_languages l2 ON t.source_language_code  = l2.code
            WHERE {$where} AND revision IS NULL
            ORDER BY {$orderby}
            " );

		foreach ( $jobs as $job ) {
			$job->elements           = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}icl_translate
	                 WHERE job_id = %d  AND field_translate = 1
	                 ORDER BY tid ASC",
					$job->job_id
				)
			);
			$job->original_post_type = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT element_type
                     FROM {$wpdb->prefix}icl_translations
                     WHERE trid=%d AND language_code=%s",
					$job->trid,
					$job->source_language_code
				)
			);
		}

		return $jobs;
	}

	/**
	 *  Updates the number of words (total and finished).
	 *
	 * @param array   $total_word_count The word count for each language pair
	 * @param string  $from             The language id of the original language
	 *
	 * @oaram string $to The language id of the translation language
	 *
	 * @param string  $content          The translation content
	 * @param boolean $finished         Indicates if the content translation is done
	 *
	 * @param         int               The word count for the translation
	 */
	function update_word_count( &$total_word_count, $from, $to, $content, $finished ) {
		require_once ICL_PLUGIN_PATH . '/inc/wpml-api.php';
		$word_count = wpml_get_word_count( $content );
		$word_count = $word_count[ 'count' ];
		$total_word_count[ 'total' ][ $from ][ $to ] += $word_count;
		if ( $finished ) {
			$total_word_count[ 'finished' ][ $from ][ $to ] += $word_count;
		}

		return $word_count;
	}

	/**
	 * Calculates the word count for each language pair according to the
	 * given jobs.
	 *
	 * @param array $jobs The $jobs used to count the number of words.
	 *
	 * @return array The word count, total and finished, for each language
	 * pair
	 */
	function get_translation_word_count( $jobs ) {
		global $sitepress, $iclTranslationManagement;
		$total_word_count = array();

		foreach ( $jobs as $job ) {
			$from = $job->source_language_name;
			$to   = $job->language_name;

			// Initializes language pair word count
			if ( !isset( $total_word_count[ 'total' ][ $from ][ $to ] ) ) {
				$total_word_count[ 'total' ][ $from ][ $to ]    = 0;
				$total_word_count[ 'finished' ][ $from ][ $to ] = 0;
			}

			foreach ( $job->elements as $element ) {
				$icl_tm_original_content = $iclTranslationManagement->decode_field_data( $element->field_data, $element->field_format );
				$translatable_taxonomies = $sitepress->get_translatable_taxonomies( false, $job->original_post_type );

				if ( $element->field_type == 'tags' || $element->field_type == 'categories' || in_array( $element->field_type, $translatable_taxonomies ) ) {
					foreach ( $icl_tm_original_content as $k => $c ) {
						$word_count = $this->update_word_count( $total_word_count, $from, $to, $icl_tm_original_content[ $k ], $element->field_finished );
						//print $icl_tm_original_content[$k] . "($word_count words) <br>";
					}
				} else {
					$word_count = $this->update_word_count( $total_word_count, $from, $to, $icl_tm_original_content, $element->field_finished );
					//print $icl_tm_original_content . "($word_count words) <br>";
				}
			}
		}

		return $total_word_count;
	}

	/**
	 * Checks if WPML is active. If not display a warning message.
	 * Also checks if WPML is in a compatible version.
	 */
	function is_wpml_active() {
		if ( !defined( 'ICL_SITEPRESS_VERSION' ) || ICL_PLUGIN_INACTIVE ) {

			return false;
		} elseif ( version_compare( ICL_SITEPRESS_VERSION, '2.0.5', '<' ) ) {

			return false;
		}

		return true;
	}

	private function server_languages_map($language_name, $server2plugin = false){
        if(is_array($language_name)){
            return array_map(array(__CLASS__, 'icl_server_languages_map'), $language_name);
        }
        $map = array(
            'Norwegian Bokmål' => 'Norwegian',
            'Portuguese, Brazil' => 'Portuguese',
            'Portuguese, Portugal' => 'Portugal Portuguese'
        );
        if($server2plugin){
            $map = array_flip($map);
        }
        if(isset($map[$language_name])){
            return $map[$language_name];
        }else{
            return $language_name;
        }
    }	
	
}
