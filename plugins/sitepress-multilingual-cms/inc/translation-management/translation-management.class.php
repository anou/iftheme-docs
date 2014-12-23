<?php
define ( 'ICL_TM_NOT_TRANSLATED', 0);
define ( 'ICL_TM_WAITING_FOR_TRANSLATOR', 1);
define ( 'ICL_TM_IN_PROGRESS', 2);
define ( 'ICL_TM_NEEDS_UPDATE', 3);  //virt. status code (based on needs_update)
define ( 'ICL_TM_DUPLICATE', 9);
define ( 'ICL_TM_COMPLETE', 10);

define('ICL_TM_NOTIFICATION_NONE', 0);
define('ICL_TM_NOTIFICATION_IMMEDIATELY', 1);
define('ICL_TM_NOTIFICATION_DAILY', 2);

define('ICL_TM_TMETHOD_MANUAL', 0);
define('ICL_TM_TMETHOD_EDITOR', 1);
define('ICL_TM_TMETHOD_PRO', 2);

define('ICL_TM_DOCS_PER_PAGE', 20);

$asian_languages = array('ja', 'ko', 'zh-hans', 'zh-hant', 'mn', 'ne', 'hi', 'pa', 'ta', 'th');

if(!class_exists('WPML_Config')) {
	require ICL_PLUGIN_PATH . '/inc/wpml-config/wpml-config.class.php';
}

class TranslationManagement{

	private $selected_translator = array('ID'=>0);
	private $current_translator = array('ID'=>0);
	public $messages = array();
	public $dashboard_select = array();
	public $settings;
	public $admin_texts_to_translate = array();

	function __construct(){
		add_action('init', array($this, 'init'), 1500);

		if(isset($_GET['icl_tm_message'])){
			$this->messages[] = array(
				'type' => isset($_GET['icl_tm_message_type']) ? $_GET['icl_tm_message_type'] : 'updated',
				'text'  => $_GET['icl_tm_message']
			);
		}

		add_action('save_post', array($this, 'save_post_actions'), 110, 2); // calling *after* the Sitepress actions

		add_action('delete_post', array($this, 'delete_post_actions'), 1, 1); // calling *before* the Sitepress actions

		//add_action('edit_term',  array($this, 'edit_term'),11, 2); // calling *after* the Sitepress actions

		add_action('icl_ajx_custom_call', array($this, 'ajax_calls'), 10, 2);
		add_action('wp_ajax_show_post_content', array($this, '_show_post_content'));

		if(isset($_GET['sm']) && ($_GET['sm'] == 'dashboard' || $_GET['sm'] == 'jobs')){@session_start();}
		elseif(isset($_GET['page']) && preg_match('@/menu/translations-queue\.php$@', $_GET['page'])){@session_start();}
		add_filter('icl_additional_translators', array($this, 'icl_additional_translators'), 99, 3);

		add_filter('icl_translators_list', array(__CLASS__, 'icanlocalize_translators_list'));

		add_action('user_register', array($this, 'clear_cache'));
		add_action('profile_update', array($this, 'clear_cache'));
		add_action('delete_user', array($this, 'clear_cache'));
		add_action('added_existing_user', array($this, 'clear_cache'));
		add_action('remove_user_from_blog', array($this, 'clear_cache'));

		add_action('admin_print_scripts', array($this, '_inline_js_scripts'));

		add_action('wp_ajax_icl_tm_user_search', array($this, '_user_search'));

		add_action('wp_ajax_icl_tm_abort_translation', array($this, 'abort_translation'));

	}

	function save_settings()
	{
		global $sitepress, $sitepress_settings;

		$iclsettings[ 'translation-management' ] = $this->settings;
		$custom_posts_sync_option                = isset( $sitepress_settings[ 'custom_posts_sync_option' ] ) ? $sitepress_settings[ 'custom_posts_sync_option' ] : false;

		if ( is_array( $custom_posts_sync_option ) ) {
			foreach ( $custom_posts_sync_option as $k => $v ) {
				$iclsettings[ 'custom_posts_sync_option' ][ $k ] = $v;
			}
		}
		$sitepress->save_settings( $iclsettings );
	}

	function init(){

		global $wpdb, $current_user, $sitepress_settings, $sitepress;

		$this->settings =& $sitepress_settings['translation-management'];

		//logic for syncing comments
		if($sitepress->get_option('sync_comments_on_duplicates')){
			add_action('delete_comment', array($this, 'duplication_delete_comment'));
			add_action('edit_comment', array($this, 'duplication_edit_comment'));
			add_action('wp_set_comment_status', array($this, 'duplication_status_comment'), 10, 2);
			add_action('wp_insert_comment', array($this, 'duplication_insert_comment'), 100);
		}

		$this->initial_custom_field_translate_states();

		// defaults
		if(!isset($this->settings['notification']['new-job'])) $this->settings['notification']['new-job'] = ICL_TM_NOTIFICATION_IMMEDIATELY;
		if(!isset($this->settings['notification']['completed'])) $this->settings['notification']['completed'] = ICL_TM_NOTIFICATION_IMMEDIATELY;
		if(!isset($this->settings['notification']['resigned'])) $this->settings['notification']['resigned'] = ICL_TM_NOTIFICATION_IMMEDIATELY;
		if(!isset($this->settings['notification']['dashboard'])) $this->settings['notification']['dashboard'] = true;
		if(!isset($this->settings['notification']['purge-old'])) $this->settings['notification']['purge-old'] = 7;

		if(!isset($this->settings['custom_fields_translation'])) $this->settings['custom_fields_translation'] = array();
		if(!isset($this->settings['doc_translation_method'])) $this->settings['doc_translation_method'] = ICL_TM_TMETHOD_MANUAL;

		get_currentuserinfo();
		$user = false;
		if(isset($current_user->ID)){
			$user = new WP_User($current_user->ID);
		}

		if(!$user || empty($user->data)) return;

		$ct['translator_id'] =  $current_user->ID;
		$ct['display_name'] =  isset($user->data->display_name) ? $user->data->display_name : $user->data->user_login;
		$ct['user_login'] =  $user->data->user_login;
		$ct['language_pairs'] = get_user_meta($current_user->ID, $wpdb->prefix.'language_pairs', true);
		if(empty($ct['language_pairs'])) $ct['language_pairs'] = array();

		$this->current_translator = (object)$ct;

		WPML_Config::load_config();

		if(isset($_POST['icl_tm_action'])){
			$this->process_request($_POST['icl_tm_action'], $_POST);
		}elseif(isset($_GET['icl_tm_action'])){
			$this->process_request($_GET['icl_tm_action'], $_GET);
		}

		if($GLOBALS['pagenow']=='edit.php'){ // use standard WP admin notices
			add_action('admin_notices', array($this, 'show_messages'));
		}else{                               // use custom WP admin notices
			add_action('icl_tm_messages', array($this, 'show_messages'));
		}

		if(isset($_GET['page']) && basename($_GET['page']) == 'translations-queue.php' && isset($_GET['job_id'])){
			add_filter('admin_head',array($this, '_show_tinyMCE'));
		}


		//if(!isset($this->settings['doc_translation_method'])){
		if(isset($this->settings['doc_translation_method']) && $this->settings['doc_translation_method'] < 0 ){
			if(isset($_GET['sm']) && $_GET['sm']=='mcsetup' && isset($_GET['src']) && $_GET['src']=='notice'){
						$this->settings['doc_translation_method'] = ICL_TM_TMETHOD_MANUAL;
						$this->save_settings();
			}else{
				add_action('admin_notices', array($this, '_translation_method_notice'));
			}
		}

		if(defined('WPML_TM_VERSION') && isset($_GET['page']) && $_GET['page'] == WPML_TM_FOLDER. '/menu/main.php' && isset($_GET['sm']) && $_GET['sm'] == 'translators'){
			$iclsettings =& $sitepress_settings;
			$sitepress->get_icl_translator_status($iclsettings);
			$sitepress->save_settings($iclsettings);
		}

		// default settings
		if(empty($this->settings['doc_translation_method']) || !defined('WPML_TM_VERSION')){
			$this->settings['doc_translation_method'] = ICL_TM_TMETHOD_MANUAL;
		}
	}

	function initial_custom_field_translate_states() {
		global $wpdb;

		$cf_keys_limit = 1000; // jic
		$custom_keys = $wpdb->get_col( "
			SELECT meta_key
			FROM $wpdb->postmeta
			GROUP BY meta_key
			ORDER BY meta_key
			LIMIT $cf_keys_limit" );

		$changed = false;

		foreach($custom_keys as $cfield) {
			if(empty($this->settings['custom_fields_translation'][$cfield]) || $this->settings['custom_fields_translation'][$cfield] == 0) {
				// see if a plugin handles this field
				$override = apply_filters('icl_cf_translate_state', 'nothing', $cfield);
				switch($override) {
					case 'nothing':
						break;

					case 'ignore':
						$changed = true;
						$this->settings['custom_fields_translation'][$cfield] = 3;
						break;

					case 'translate':
						$changed = true;
						$this->settings['custom_fields_translation'][$cfield] = 2;
						break;

					case 'copy':
						$changed = true;
						$this->settings['custom_fields_translation'][$cfield] = 1;
						break;
				}

			}
		}
		if ($changed) {
			$this->save_settings();
		}
	}

	function _translation_method_notice(){
		echo '<div class="error fade"><p id="icl_side_by_site">'.sprintf(__('New - side-by-site translation editor: <a href="%s">try it</a> | <a href="#cancel">no thanks</a>.', 'sitepress'),
				admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/main.php&sm=mcsetup&src=notice')) . '</p></div>';
	}

	function _show_tinyMCE() {
		wp_print_scripts('editor');
		//add_filter('the_editor', array($this, 'editor_directionality'), 9999);
		add_filter('tiny_mce_before_init', array($this, '_mce_set_direction'), 9999);
		add_filter('mce_buttons', array($this, '_mce_remove_fullscreen'), 9999);

		if (version_compare($GLOBALS['wp_version'], '3.1.4', '<=') && function_exists('wp_tiny_mce'))
		try{
			/** @noinspection PhpDeprecationInspection */
			@wp_tiny_mce();
		} catch(Exception $e) {  /*don't do anything with this */ }
	}

	function _mce_remove_fullscreen($options){
		foreach($options as $k=>$v) if($v == 'fullscreen') unset($options[$k]);
		return $options;
	}

	function _inline_js_scripts(){
		// remove fullscreen mode
		if(defined('WPML_TM_FOLDER') && isset($_GET['page']) && $_GET['page'] == WPML_TM_FOLDER . '/menu/translations-queue.php' && isset($_GET['job_id'])){
			?>
			<script type="text/javascript">addLoadEvent(function(){jQuery('#ed_fullscreen').remove();});</script>
			<?php
		}
	}


	function _mce_set_direction($settings) {
		$job = $this->get_translation_job((int)$_GET['job_id'], false, true);
		if (!empty($job)) {
			$rtl_translation = in_array($job->language_code, array('ar','he','fa'));
			if ($rtl_translation) {
				$settings['directionality'] = 'rtl';
			} else {
				$settings['directionality'] = 'ltr';
			}
		}
		return $settings;
	}

	/*
	function editor_directionality($tag) {
		$job = $this->get_translation_job((int)$_GET['job_id'], false, true);
		$rtl_translation = in_array($job->language_code, array('ar','he','fa'));
		if ($rtl_translation) {
			$dir = 'dir="rtl"';
		} else {
			$dir = 'dir="ltr"';
		}
		return str_replace('<textarea', '<textarea ' . $dir, $tag);
	}
	*/

	function process_request($action, $data){
		$data = stripslashes_deep($data);
		switch($action){
			case 'add_translator':
				if(wp_create_nonce('add_translator') == $data['add_translator_nonce']){
					// Initial adding
					if (isset($data['from_lang']) && isset($data['to_lang'])) {
					  $data['lang_pairs'] = array();
					  $data['lang_pairs'][$data['from_lang']] = array($data['to_lang'] => 1);
					}
					$this->add_translator($data['user_id'], $data['lang_pairs']);
					$_user = new WP_User($data['user_id']);
					wp_redirect('admin.php?page='.WPML_TM_FOLDER.'/menu/main.php&sm=translators&icl_tm_message='.urlencode(sprintf(__('%s has been added as a translator for this site.','sitepress'),$_user->data->display_name)).'&icl_tm_message_type=updated');
				}
				break;
			case 'edit_translator':
				if(wp_create_nonce('edit_translator') == $data['edit_translator_nonce']){
					$this->edit_translator($data['user_id'], isset($data['lang_pairs']) ? $data['lang_pairs'] : array());
					$_user = new WP_User($data['user_id']);
					wp_redirect('admin.php?page='.WPML_TM_FOLDER.'/menu/main.php&sm=translators&icl_tm_message='.urlencode(sprintf(__('Language pairs for %s have been edited.','sitepress'),$_user->data->display_name)).'&icl_tm_message_type=updated');
				}
				break;
			case 'remove_translator':
				if(wp_create_nonce('remove_translator') == $data['remove_translator_nonce']){
					$this->remove_translator($data['user_id']);
					$_user = new WP_User($data['user_id']);
					wp_redirect('admin.php?page='.WPML_TM_FOLDER.'/menu/main.php&sm=translators&icl_tm_message='.urlencode(sprintf(__('%s has been removed as a translator for this site.','sitepress'),$_user->data->display_name)).'&icl_tm_message_type=updated');
				}
				break;
			case 'edit':
				$this->selected_translator['ID'] = intval($data['user_id']);
				break;
			case 'dashboard_filter':
				$_SESSION['translation_dashboard_filter'] = $data['filter'];
				wp_redirect('admin.php?page='.WPML_TM_FOLDER . '/menu/main.php&sm=dashboard');
				break;
		   case 'sort':
				if(isset($data['sort_by'])) $_SESSION['translation_dashboard_filter']['sort_by'] = $data['sort_by'];
				if(isset($data['sort_order'])) $_SESSION['translation_dashboard_filter']['sort_order'] = $data['sort_order'];
				break;
		   case 'reset_filters':
				unset($_SESSION['translation_dashboard_filter']);
				break;
		   case 'send_jobs':
				if(isset($data['iclnonce']) && wp_verify_nonce($data['iclnonce'], 'pro-translation-icl')){
					$this->send_jobs($data);
				}
				break;
		   case 'jobs_filter':
				$_SESSION['translation_jobs_filter'] = $data['filter'];
				wp_redirect('admin.php?page='.WPML_TM_FOLDER . '/menu/main.php&sm=jobs');
				break;
		   case 'ujobs_filter':
				$_SESSION['translation_ujobs_filter'] = $data['filter'];
				wp_redirect('admin.php?page='.WPML_TM_FOLDER . '/menu/translations-queue.php');
				break;
		   case 'save_translation':
				if(!empty($data['resign'])){
					$this->resign_translator($data['job_id']);
					wp_redirect(admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&resigned='.$data['job_id']));
					exit;
				}else{
					$this->save_translation($data);
				}
				break;
		   case 'save_notification_settings':
				$this->settings['notification'] = $data['notification'];
				$this->save_settings();
				$this->messages[] = array(
					'type'=>'updated',
					'text' => __('Preferences saved.', 'sitepress')
				);
				break;
		   case 'create_job':
				global $current_user;
				if(!isset($this->current_translator->ID) && isset($current_user->ID)){
					$this->current_translator->ID  = $current_user->ID;
				}
				$data['translator'] = $this->current_translator->ID;

				$job_ids = $this->send_jobs($data);
				wp_redirect('admin.php?page='.WPML_TM_FOLDER . '/menu/translations-queue.php&job_id=' . array_pop($job_ids));
				break;
		   case 'cancel_jobs':
				$ret = $this->cancel_translation_request($data['icl_translation_id']);
				$this->messages[] = array(
					'type'=>'updated',
					'text' => __('Translation requests cancelled.', 'sitepress')
				);
				break;
		}
	}

	function ajax_calls( $call, $data ) {
		global $wpdb, $sitepress, $sitepress_settings;
		switch ( $call ) {
			/*
			case 'save_dashboard_setting':
					$iclsettings['dashboard'] = $sitepress_settings['dashboard'];
					if(isset($data['setting']) && isset($data['value'])){
							$iclsettings['dashboard'][$data['setting']] = $data['value'];
							$sitepress->save_settings($iclsettings);
					}
					break;
			*/
			case 'assign_translator':

				$_exp          = explode( '-', $data[ 'translator_id' ] );
				$service       = isset( $_exp[ 1 ] ) ? $_exp[ 1 ] : 'local';
				$translator_id = $_exp[ 0 ];
				if ( $this->assign_translation_job( $data[ 'job_id' ], $translator_id, $service ) ) {
					if ( $service == 'icanlocalize' ) {
						$job = $this->get_translation_job( $data[ 'job_id' ] );
						global $ICL_Pro_Translation;
						$ICL_Pro_Translation->send_post( $job->original_doc_id, array( $job->language_code ), $translator_id );
						$lang_tr_id = false;
						$contract_id = false;
						foreach ( $sitepress_settings[ 'icl_lang_status' ] as $lp ) {
							if ( $lp[ 'from' ] == $job->source_language_code && $lp[ 'to' ] == $job->language_code ) {
								$contract_id = $lp[ 'contract_id' ];
								$lang_tr_id  = $lp[ 'id' ];
								break;
							}
						}
						$popup_link = ICL_API_ENDPOINT . '/websites/' . $sitepress_settings[ 'site_id' ] . '/website_translation_offers/' . $lang_tr_id . '/website_translation_contracts/' . $contract_id;
						$popup_args = array(
								'title'     => __( 'Chat with translator', 'sitepress' ),
								'unload_cb' => 'icl_thickbox_refresh'
						);
						$translator_edit_link = $sitepress->create_icl_popup_link( $popup_link, $popup_args );
						$translator_edit_link .= esc_html( ICL_Pro_Translation::get_translator_name( $translator_id ) );
						$translator_edit_link .= '</a> (ICanLocalize)';

					} else {
						$translator_edit_link =
								'<a href="' . $this->get_translator_edit_url( $data[ 'translator_id' ] ) . '">' . esc_html( $wpdb->get_var( $wpdb->prepare( "SELECT display_name FROM {$wpdb->users} WHERE ID=%d", $data[ 'translator_id' ] ) ) ) . '</a>';
					}
					echo json_encode( array( 'error' => 0, 'message' => $translator_edit_link, 'status' => $this->status2text( ICL_TM_WAITING_FOR_TRANSLATOR ), 'service' => $service ) );
				} else {
					echo json_encode( array( 'error' => 1 ) );
				}
				break;
			case 'icl_cf_translation':
				if ( !empty( $data[ 'cf' ] ) ) {
					foreach ( $data[ 'cf' ] as $k => $v ) {
						$cft[ base64_decode( $k ) ] = $v;
					}
					if ( isset( $cft ) ) {
						$this->settings['custom_fields_translation'] = $cft;
						$this->save_settings();
					}

				}
				echo '1|';
				break;
			case 'icl_doc_translation_method':
				$this->settings['doc_translation_method'] = intval($data['t_method']);
				$sitepress->set_setting( 'hide_how_to_translate', empty( $data[ 'how_to_translate' ] ) );
				if (isset($data[ 'tm_block_retranslating_terms' ])) {
					$sitepress->set_setting( 'tm_block_retranslating_terms', $data[ 'tm_block_retranslating_terms' ] );
				} else {
					$sitepress->set_setting( 'tm_block_retranslating_terms', '' );
				}
				$this->save_settings();
				echo '1|';
				break;
			case 'reset_duplication':
				$this->reset_duplicate_flag( $_POST[ 'post_id' ] );
				break;
			case 'set_duplication':
				$this->set_duplicate( $_POST[ 'post_id' ] );
				break;
			case 'make_duplicates':
				$mdata[ 'iclpost' ] = array( $data[ 'post_id' ] );
				$langs              = explode( ',', $data[ 'langs' ] );
				foreach ( $langs as $lang ) {
					$mdata[ 'duplicate_to' ][ $lang ] = 1;
				}
				$this->make_duplicates( $mdata );
				break;
		}
	}

	function show_messages(){
		if(!empty($this->messages)){
			foreach($this->messages as $m){
				echo '<div class="'.$m['type'].' below-h2"><p>' . $m['text'] . '</p></div>';
			}
		}
	}

	/* TRANSLATORS */
	/* ******************************************************************************************** */
	function add_translator($user_id, $language_pairs){
		global $wpdb;

		$user = new WP_User($user_id);
		$user->add_cap('translate');

		$um = get_user_meta($user_id, $wpdb->prefix . 'language_pairs', true);
		if(!empty($um)){
			foreach($um as $fr=>$to){
				if(isset($language_pairs[$fr])){
					$language_pairs[$fr] = array_merge($language_pairs[$fr], $to);
				}

			}
		}

		update_user_meta($user_id, $wpdb->prefix . 'language_pairs',  $language_pairs);
		$this->clear_cache();
	}

	function edit_translator($user_id, $language_pairs){
		global $wpdb;
		$_user = new WP_User($user_id);
		if(empty($language_pairs)){
			$this->remove_translator($user_id);
			wp_redirect('admin.php?page='.WPML_TM_FOLDER.'/menu/main.php&sm=translators&icl_tm_message='.
				urlencode(sprintf(__('%s has been removed as a translator for this site.','sitepress'),$_user->data->display_name)).'&icl_tm_message_type=updated'); exit;
		}
		else{
			if(!$_user->has_cap('translate')) $_user->add_cap('translate');
			update_user_meta($user_id, $wpdb->prefix . 'language_pairs',  $language_pairs);
		}
	}

	function remove_translator($user_id){
		global $wpdb;
		$user = new WP_User($user_id);
		$user->remove_cap('translate');
		delete_user_meta($user_id, $wpdb->prefix . 'language_pairs');
		$this->clear_cache();
	}

	function is_translator( $user_id, $args = array() )
	{
		extract( $args, EXTR_OVERWRITE );

		global $wpdb;
		$user = new WP_User( $user_id );

		$is_translator = $user->has_cap( 'translate' );

		// check if user is administrator and return true if he is
		$user_caps = $user->caps;
		if ( isset( $user_caps[ 'activate_plugins' ] ) && $user_caps[ 'activate_plugins' ] == true ) {
			$is_translator = true;
		} else {

			if ( isset( $lang_from ) && isset( $lang_to ) ) {
				$um            = get_user_meta( $user_id, $wpdb->prefix . 'language_pairs', true );
				$is_translator = $is_translator && isset( $um[ $lang_from ] ) && isset( $um[ $lang_from ][ $lang_to ] ) && $um[ $lang_from ][ $lang_to ];
			}
			if ( isset( $job_id ) ) {
				$translator_id = $wpdb->get_var( $wpdb->prepare( "
							SELECT j.translator_id
								FROM {$wpdb->prefix}icl_translate_job j
								JOIN {$wpdb->prefix}icl_translation_status s ON j.rid = s.rid
							WHERE job_id = %d AND s.translation_service='local'
						", $job_id ) );

				$is_translator = $is_translator && ( ( $translator_id == $user_id ) || empty( $translator_id ) );
			}

		}

		return $is_translator;
	}

	function translator_exists($id, $from, $to, $type = 'local'){
		global $sitepress_settings;
		$exists = false;
		if($type == 'icanlocalize' && !empty($sitepress_settings['icl_lang_status'])){
			foreach($sitepress_settings['icl_lang_status'] as $lpair){
				if($lpair['from'] == $from && $lpair['to'] == $to){
					if(!empty($lpair['translators'])){
						foreach($lpair['translators'] as $t){
							if($t['id'] == $id){
								$exists = true;
								break(2);
							}
						}
					}
				}
			}
		}elseif($type == 'local'){
			$exists = $this->is_translator($id, array('lang_from'=>$from, 'lang_to'=>$to));
		}
		return $exists;
	}

	function set_default_translator($id, $from, $to, $type = 'local'){
		global $sitepress, $sitepress_settings;
		$iclsettings['default_translators'] = isset($sitepress_settings['default_translators']) ? $sitepress_settings['default_translators'] : array();
		$iclsettings['default_translators'][$from][$to] = array('id'=>$id, 'type'=>$type);
		$sitepress->save_settings($iclsettings);
	}

	function get_default_translator($from, $to){
		global $sitepress_settings;
		if(isset($sitepress_settings['default_translators'][$from][$to])){
			$dt = $sitepress_settings['default_translators'][$from][$to];
		}else{
			$dt = array();
		}
		return $dt;
	}

	public static function get_blog_not_translators(){
		global $wpdb;
		$cached_translators = get_option($wpdb->prefix . 'icl_non_translators_cached', array());
		if (!empty($cached_translators)) {
			return $cached_translators;
		}
		$sql = "SELECT u.ID, u.user_login, u.display_name, m.meta_value AS caps
			FROM {$wpdb->users} u JOIN {$wpdb->usermeta} m ON u.id=m.user_id AND m.meta_key = '{$wpdb->prefix}capabilities' ORDER BY u.display_name";
		$res = $wpdb->get_results($sql);

		$users = array();
		foreach($res as $row){
			$caps = @unserialize($row->caps);
			if(!isset($caps['translate'])){
				$users[] = $row;
			}
		}
		update_option($wpdb->prefix . 'icl_non_translators_cached', $users);
		return $users;
	}

	/**
	 * Implementation of 'icl_translators_list' hook
	 *
	 * @global object $sitepress
	 * @param array $array
	 * @return array
	 */
	public static function icanlocalize_translators_list() {
	  global $sitepress_settings, $sitepress;

	  $lang_status = isset($sitepress_settings['icl_lang_status']) ? $sitepress_settings['icl_lang_status'] : array();
	  if (0 != key($lang_status)){
		$buf[] = $lang_status;
		$lang_status = $buf;
	  }

	  $translators = array();
	  foreach($lang_status as $lpair){
		  foreach((array)$lpair['translators'] as $translator){
			$translators[$translator['id']]['name'] = $translator['nickname'];
			$translators[$translator['id']]['langs'][$lpair['from']][] = $lpair['to'];
			$translators[$translator['id']]['type'] = 'ICanLocalize';
			$translators[$translator['id']]['action'] = $sitepress->create_icl_popup_link(ICL_API_ENDPOINT . '/websites/' . $sitepress_settings['site_id']
				. '/website_translation_offers/' . $lpair['id'] . '/website_translation_contracts/'
				. $translator['contract_id'], array('title' => __('Chat with translator', 'sitepress'), 'unload_cb' => 'icl_thickbox_refresh', 'ar'=>1)) . __('Chat with translator', 'sitepress') . '</a>';
		  }
	  }

	  return $translators;
	}

	public static function get_blog_translators($args = array()){
		global $wpdb;
		$args_default = array('from'=>false, 'to'=>false);
		extract($args_default);
		extract($args, EXTR_OVERWRITE);

//        $sql = "SELECT u.ID, u.user_login, u.display_name, u.user_email, m.meta_value AS caps
//                    FROM {$wpdb->users} u JOIN {$wpdb->usermeta} m ON u.id=m.user_id AND m.meta_key LIKE '{$wpdb->prefix}capabilities' ORDER BY u.display_name";
//        $res = $wpdb->get_results($sql);

		$cached_translators = get_option($wpdb->prefix . 'icl_translators_cached', array());

		if (empty($cached_translators)) {
			$sql = "SELECT u.ID FROM {$wpdb->users} u JOIN {$wpdb->usermeta} m ON u.id=m.user_id AND m.meta_key = '{$wpdb->prefix}language_pairs' ORDER BY u.display_name";
			$res = $wpdb->get_results($sql);
			update_option($wpdb->prefix . 'icl_translators_cached', $res);
		} else {
			$res = $cached_translators;
		}

		$users = array();
		foreach($res as $row){
			$user = new WP_User($row->ID);
//            $caps = @unserialize($row->caps);
//            $row->language_pairs = (array)get_user_meta($row->ID, $wpdb->prefix.'language_pairs', true);
			$user->language_pairs = (array)get_user_meta($row->ID, $wpdb->prefix.'language_pairs', true);
//            if(!empty($from) && !empty($to) && (!isset($row->language_pairs[$from][$to]) || !$row->language_pairs[$from][$to])){
//                continue;
//            }
			if(!empty($from) && !empty($to) && (!isset($user->language_pairs[$from][$to]) || !$user->language_pairs[$from][$to])){
				continue;
			}
//            if(isset($caps['translate'])){
//                $users[] = $user;
//            }
			if($user->has_cap('translate')){
				$users[] = $user;
			}
		}
		return $users;
	}

	function get_selected_translator(){
		global $wpdb;
		if($this->selected_translator['ID']){
			$user = new WP_User($this->selected_translator['ID']);
			$this->selected_translator['display_name'] =  $user->data->display_name;
			$this->selected_translator['user_login'] =  $user->data->user_login;
			$this->selected_translator['language_pairs'] = get_user_meta($this->selected_translator['ID'], $wpdb->prefix.'language_pairs', true);
		}else{
			$this->selected_translator['ID'] = 0;
		}
		return (object)$this->selected_translator;
	}

	function get_current_translator(){
		return $this->current_translator;
	}

	public function get_translator_edit_url($translator_id){
		$url = '';
		if(!empty($translator_id)){
			$url = 'admin.php?page='. WPML_TM_FOLDER .'/menu/main.php&amp;sm=translators&icl_tm_action=edit&amp;user_id='. $translator_id;
		}
		return $url;
	}

	public function translators_dropdown( $args = array() ) {
		global $sitepress_settings;
		$args_default = array(
				'from'         => false,
				'to'           => false,
				'name'         => 'translator_id',
				'selected'     => 0,
				'echo'         => true,
				'services'     => array( 'local' ),
				'show_service' => true,
				'disabled'     => false
		);
		extract( $args_default );
		extract( $args, EXTR_OVERWRITE );

		$translators = array();

		/** @var $from string|false */
		/** @var $to string|false */
		/** @var $name string|false */
		/** @var $selected bool */
		/** @var $echo bool */
		/** @var $services array */
		/** @var $show_service bool */
		/** @var $disabled bool */
		if ( in_array( 'icanlocalize', $services ) ) {
			if ( empty( $sitepress_settings[ 'icl_lang_status' ] ) ) {
				$sitepress_settings[ 'icl_lang_status' ] = array();
			}
			foreach ( (array) $sitepress_settings[ 'icl_lang_status' ] as $language_pair ) {
				if ( $from && $from != $language_pair[ 'from' ] ) {
					continue;
				}
				if ( $to && $to != $language_pair[ 'to' ] ) {
					continue;
				}

				if ( !empty( $language_pair[ 'translators' ] ) ) {
					if ( 1 < count( $language_pair[ 'translators' ] ) ) {
						$translators[ ] = (object) array(
								'ID'           => '0-icanlocalize',
								'display_name' => __( 'First available', 'sitepress' ),
								'service'      => 'ICanLocalize'
						);
					}
					foreach ( $language_pair[ 'translators' ] as $tr ) {
						if ( !isset( $_icl_translators[ $tr[ 'id' ] ] ) ) {
							$translators[ ] = $_icl_translators[ $tr[ 'id' ] ] = (object) array(
									'ID'           => $tr[ 'id' ] . '-icanlocalize',
									'display_name' => $tr[ 'nickname' ],
									'service'      => 'ICanLocalize'
							);
						}
					}
				}
			}
		}

		if ( in_array( 'local', $services ) ) {
			$translators[ ] = (object) array(
					'ID'           => 0,
					'display_name' => __( 'First available', 'sitepress' ),
			);
			$translators    = array_merge( $translators, self::get_blog_translators( array( 'from' => $from, 'to' => $to ) ) );
		}
		$translators = apply_filters( 'wpml_tm_translators_list', $translators );
		?>
		<select name="<?php echo $name ?>" <?php if ($disabled): ?>disabled="disabled"<?php endif; ?>>
			<?php foreach ( $translators as $t ): ?>
				<option value="<?php echo $t->ID ?>" <?php if ($selected == $t->ID): ?>selected="selected"<?php endif; ?>><?php echo esc_html( $t->display_name );
					?> <?php if ( $show_service ) {
						echo '(';
						echo isset( $t->service ) ? $t->service : _e( 'Local', 'sitepress' );
						echo ')';
					} ?></option>
			<?php endforeach; ?>
		</select>
	<?php
	}

	public function get_number_of_docs_sent($service = 'icanlocalize'){
		global $wpdb;
		$n = $wpdb->get_var($wpdb->prepare("
			SELECT COUNT(rid) FROM {$wpdb->prefix}icl_translation_status WHERE translation_service=%s
		", $service));
		return $n;
	}

	public function get_number_of_docs_pending($service = 'icanlocalize'){
		global $wpdb;
		$n = $wpdb->get_var($wpdb->prepare("
			SELECT COUNT(rid) FROM {$wpdb->prefix}icl_translation_status WHERE translation_service=%s AND status < " . ICL_TM_COMPLETE . "
		", $service));
		return $n;
	}


	/* HOOKS */
	/* ******************************************************************************************** */
	function save_post_actions( $post_id, $post, $force_set_status = false )
	{
		global $wpdb, $sitepress, $current_user;

		// skip revisions
		if ( $post->post_type == 'revision' ) {
			return;
		}
		// skip auto-drafts
		if ( $post->post_status == 'auto-draft' ) {
			return;
		}
		// skip autosave
		if ( isset( $_POST[ 'autosave' ] ) ) {
			return;
		}
		if ( isset( $_POST[ 'icl_trid' ] ) && is_numeric($_POST['icl_trid']) ) {
			$trid = $_POST['icl_trid'];
		} else {
			$trid = $sitepress->get_element_trid( $post_id, 'post_' . $post->post_type );
		}


		// set trid and lang code if front-end translation creating
		$trid = apply_filters( 'wpml_tm_save_post_trid_value', isset( $trid ) ? $trid : '', $post_id );
		$lang = apply_filters( 'wpml_tm_save_post_lang_value', isset( $lang ) ? $lang : '', $post_id );

		// is this the original document?
		$is_original = false;
		if ( !empty( $trid ) ) {
			$is_original = $wpdb->get_var( $wpdb->prepare( "SELECT source_language_code IS NULL FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND trid=%d", $post_id, $trid ) );
		}

		// when a manual translation is added/edited make sure to update translation tables
		if ( !empty( $trid ) && !$is_original ) {

			if ( ( !isset( $lang ) || !$lang ) && isset( $_POST[ 'icl_post_language' ] ) && !empty( $_POST[ 'icl_post_language' ] ) ) {
				$lang = $_POST[ 'icl_post_language' ];
			}

			$res = $wpdb->get_row( $wpdb->prepare( "
			 SELECT element_id, language_code FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND source_language_code IS NULL
		 ", $trid ) );
			if ( $res ) {
				$original_post_id = $res->element_id;
				$from_lang        = $res->language_code;
				$original_post    = get_post( $original_post_id );
				$md5              = $this->post_md5( $original_post );

				$translation_id_prepared = $wpdb->prepare( "SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND language_code=%s", $trid, $lang );
				$translation_id          = $wpdb->get_var( $translation_id_prepared );

				get_currentuserinfo();
				$user_id = $current_user->ID;


				if ( !$this->is_translator( $user_id, array( 'lang_from' => $from_lang, 'lang_to' => $lang ) ) ) {
					$this->add_translator( $user_id, array( $from_lang => array( $lang => 1 ) ) );
				}

				if ( $translation_id ) {
					$translation_package = $this->create_translation_package( $original_post_id );

					list( $rid, $update ) = $this->update_translation_status( array(
																				   'translation_id'      => $translation_id,
																				   'status'              => isset( $force_set_status ) && $force_set_status > 0 ? $force_set_status : ICL_TM_COMPLETE,
																				   'translator_id'       => $user_id,
																				   'needs_update'        => 0,
																				   'md5'                 => $md5,
																				   'translation_service' => 'local',
																				   'translation_package' => serialize( $translation_package )
																			  ) );
					if ( !$update ) {
						$job_id = $this->add_translation_job( $rid, $user_id, $translation_package );
					} else {
						$job_id = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(job_id) FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d GROUP BY rid", $rid ) );
					}

					// saving the translation
					$this->save_job_fields_from_post( $job_id, $post );
				}
			}

		}

		// if this is an original post - compute md5 hash and mark for update if neded
		if ( !empty( $trid ) && empty( $_POST[ 'icl_minor_edit' ] ) ) {

			$is_original  = false;
			$translations = $sitepress->get_element_translations( $trid, 'post_' . $post->post_type );

			foreach ( $translations as $lang => $translation ) {
				if ( $translation->original == 1 && $translation->element_id == $post_id ) {
					$is_original = true;
					break;
				}
			}

			if ( $is_original ) {
				$md5 = $this->post_md5( $post_id );

				foreach ( $translations as $lang => $translation ) {
					if ( !$translation->original ) {
						$emd5_prepared = $wpdb->prepare( "SELECT md5 FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d", $translation->translation_id );
						$emd5          = $wpdb->get_var( $emd5_prepared );

						if ( $md5 != $emd5 ) {

							$translation_package = $this->create_translation_package( $post_id );

							list( $rid, $update ) = $this->update_translation_status( array(
																						   'translation_id'      => $translation->translation_id,
																						   'needs_update'        => 1,
																						   'md5'                 => $md5,
																						   'translation_package' => serialize( $translation_package )
																					  ) );

							// update

							$translator_id_prepared = $wpdb->prepare( "SELECT translator_id FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d", $translation->translation_id );
							$translator_id          = $wpdb->get_var( $translator_id_prepared );
							$job_id                 = $this->add_translation_job( $rid, $translator_id, $translation_package );

							// updating a post that's being translated - update fields in icl_translate
							if ( false === $job_id ) {
								$job_id_prepared = $wpdb->prepare( "SELECT MAX(job_id) FROM {$wpdb->prefix}icl_translate_job WHERE rid = %d", $rid );
								$job_id          = $wpdb->get_var( $job_id_prepared );
								if ( $job_id ) {
									$job = $this->get_translation_job( $job_id );

									if ( $job ) {
										foreach ( $job->elements as $element ) {
											unset( $field_data );
											$_taxs_ids = false;
											switch ( $element->field_type ) {
												case 'title':
													$field_data = $this->encode_field_data( $post->post_title, $element->field_format );
													break;
												case 'body':
													$field_data = $this->encode_field_data( $post->post_content, $element->field_format );
													break;
												case 'excerpt':
													$field_data = $this->encode_field_data( $post->post_excerpt, $element->field_format );
													break;
												case 'tags':
													$terms = (array)get_the_terms( $post->ID, 'post_tag' );
													$_taxs = array();
													foreach ( $terms as $term ) {
														$_taxs[ ]     = $term->name;
														$_taxs_ids[ ] = $term->term_taxonomy_id;
													}
													$field_data = $this->encode_field_data( $_taxs, $element->field_format );
													break;
												case 'categories':
													$terms = get_the_terms( $post->ID, 'category' );
													$_taxs = array();
													foreach ( $terms as $term ) {
														$_taxs[ ]     = $term->name;
														$_taxs_ids[ ] = $term->term_taxonomy_id;
													}
													$field_data = $this->encode_field_data( $_taxs, $element->field_format );
													break;

												default:
													if ( false !== strpos( $element->field_type, 'field-' ) && !empty( $this->settings[ 'custom_fields_translation' ] ) ) {
														$cf_name = preg_replace( '#^field-#', '', $element->field_type );
														if ( isset( $this->settings[ 'custom_fields_translation' ][ $cf_name ] ) ) {
															$field_data = get_post_meta( $post->ID, $cf_name, 1 );
															$field_data = $this->encode_field_data( $field_data, $element->field_format );
														}
													} else {
														// taxonomies
														if ( taxonomy_exists( $element->field_type ) ) {
															$terms = get_the_terms( $post->ID, $element->field_type );
															$_taxs = array();
															foreach ( $terms as $term ) {
																$_taxs[ ]     = $term->name;
																$_taxs_ids[ ] = $term->term_taxonomy_id;
															}
															$field_data = $this->encode_field_data( $_taxs, $element->field_format );
														}
													}
											}

											if ( isset( $field_data ) && $field_data != $element->field_data ) {

												$wpdb->update( $wpdb->prefix . 'icl_translate', array( 'field_data' => $field_data ), array( 'tid' => $element->tid ) );

												if ( $_taxs_ids && $element->field_type == 'categories' ) {
													$wpdb->update( $wpdb->prefix . 'icl_translate', array( 'field_data' => join( ',', $_taxs_ids ) ), array( 'job_id' => $job_id, 'field_type' => 'category_ids' ) );
												}

											}

										}
									}

								}

							}

						}
					}
				}
			}
		}

		// sync copies/duplicates
		$duplicates = $this->get_duplicates( $post_id );
		static $duplicated_post_ids;
		if ( !isset( $duplicated_post_ids ) ) {
			$duplicated_post_ids = array();
		}
		foreach ( $duplicates as $lang => $_pid ) {
			// Avoid infinite recursions
			if ( !in_array( $post_id . '|' . $lang, $duplicated_post_ids ) ) {
				$duplicated_post_ids[ ] = $post_id . '|' . $lang;
				$this->make_duplicate( $post_id, $lang );
			}
		}

	}


	function make_duplicates( $data )
	{
		foreach ( $data[ 'iclpost' ] as $master_post_id ) {
			foreach ( $data[ 'duplicate_to' ] as $lang => $one ) {
				$this->make_duplicate( $master_post_id, $lang );
			}
		}
	}

	function make_duplicate( $master_post_id, $lang )
	{
		static $duplicated_post_ids;
		if(!isset($duplicated_post_ids)) {
			$duplicated_post_ids = array();
		}

		//It is already done? (avoid infinite recursions)
		if(in_array($master_post_id . '|' . $lang, $duplicated_post_ids)) {
			return true;
		}
		$duplicated_post_ids[] = $master_post_id . '|' . $lang;

		global $sitepress, $sitepress_settings, $wpdb;

		do_action( 'icl_before_make_duplicate', $master_post_id, $lang );

		$master_post = get_post( $master_post_id );

		$is_duplicated = false;
		$trid          = $sitepress->get_element_trid( $master_post_id, 'post_' . $master_post->post_type );
		if ( $trid ) {
			$translations = $sitepress->get_element_translations( $trid, 'post_' . $master_post->post_type );

			if ( isset( $translations[ $lang ] ) ) {
				$post_array[ 'ID' ] = $translations[ $lang ]->element_id;
				$is_duplicated      = get_post_meta( $translations[ $lang ]->element_id, '_icl_lang_duplicate_of', true );
			}
		}

		// covers the case when deleting in bulk from all languages
		// setting post_status to trash before wp_trash_post runs issues an WP error
		$posts_to_delete_or_restore_in_bulk = array();
		if ( isset( $_GET[ 'action' ] ) && ( $_GET[ 'action' ] == 'trash' || $_GET[ 'action' ] == 'untrash' ) && isset( $_GET[ 'lang' ] ) && $_GET[ 'lang' ] == 'all' ) {
			static $posts_to_delete_or_restore_in_bulk;
			if ( is_null( $posts_to_delete_or_restore_in_bulk ) ) {
				$posts_to_delete_or_restore_in_bulk = isset( $_GET[ 'post' ] ) && is_array( $_GET[ 'post' ] ) ? $_GET[ 'post' ] : array();
			}
		}

		$post_array[ 'post_author' ]   = $master_post->post_author;
		$post_array[ 'post_date' ]     = $master_post->post_date;
		$post_array[ 'post_date_gmt' ] = $master_post->post_date_gmt;
		$post_array[ 'post_content' ]  = addslashes_gpc(apply_filters( 'icl_duplicate_generic_string', $master_post->post_content, $lang, array( 'context' => 'post', 'attribute' => 'content', 'key' => $master_post->ID ) ));
		$post_array[ 'post_title' ]    = addslashes_gpc(apply_filters( 'icl_duplicate_generic_string', $master_post->post_title, $lang, array( 'context' => 'post', 'attribute' => 'title', 'key' => $master_post->ID ) ));
		$post_array[ 'post_excerpt' ]  = addslashes_gpc(apply_filters( 'icl_duplicate_generic_string', $master_post->post_excerpt, $lang, array( 'context' => 'post', 'attribute' => 'excerpt', 'key' => $master_post->ID ) ));

		if ( isset( $sitepress_settings[ 'sync_post_status' ] ) && $sitepress_settings[ 'sync_post_status' ] ) {
			$sync_post_status = true;
		} else {
			$sync_post_status = ( !isset( $post_array[ 'ID' ] ) || ( $sitepress_settings[ 'sync_delete' ] && $master_post->post_status == 'trash' ) || $is_duplicated );
			$sync_post_status &= ( !$posts_to_delete_or_restore_in_bulk || ( !isset( $post_array[ 'ID' ] ) || !in_array( $post_array[ 'ID' ], $posts_to_delete_or_restore_in_bulk ) ) );
		}

		if ( $sync_post_status ) {
			$post_array[ 'post_status' ] = $master_post->post_status;
		}

		$post_array[ 'comment_status' ] = $master_post->comment_status;
		$post_array[ 'ping_status' ]    = $master_post->ping_status;
		$post_array[ 'post_name' ]      = $master_post->post_name;

		if ( $master_post->post_parent ) {
			$parent                      = icl_object_id( $master_post->post_parent, $master_post->post_type, false, $lang );
			$post_array[ 'post_parent' ] = $parent;
		}

		$post_array[ 'menu_order' ]     = $master_post->menu_order;
		$post_array[ 'post_type' ]      = $master_post->post_type;
		$post_array[ 'post_mime_type' ] = $master_post->post_mime_type;


		$trid = $sitepress->get_element_trid( $master_post->ID, 'post_' . $master_post->post_type );

		$_POST[ 'icl_trid' ]               = $trid;
		$_POST[ 'icl_post_language' ]      = $lang;
		$_POST[ 'skip_sitepress_actions' ] = true;
		$_POST[ 'post_type' ]              = $master_post->post_type;

		if ( isset( $post_array[ 'ID' ] ) ) {
			$id = wp_update_post( $post_array );
		} else {
			$id = $this->icl_insert_post( $post_array, $lang );
		}

		require_once ICL_PLUGIN_PATH . '/inc/cache.php';
		icl_cache_clear( $post_array[ 'post_type' ] . 's_per_language' );

		global $ICL_Pro_Translation;
		$ICL_Pro_Translation->_content_fix_links_to_translated_content( $id, $lang );

		if ( !is_wp_error( $id ) ) {

			$sitepress->set_element_language_details( $id, 'post_' . $master_post->post_type, $trid, $lang );

			$this->save_post_actions( $id, get_post( $id ), ICL_TM_DUPLICATE );

			$this->duplicate_fix_children( $master_post_id, $lang );

			// dup comments
			if ( $sitepress->get_option( 'sync_comments_on_duplicates' ) ) {
				$this->duplicate_comments( $id, $master_post_id );
			}

			// make sure post name is copied
			$wpdb->update( $wpdb->posts, array( 'post_name' => $master_post->post_name ), array( 'ID' => $id ) );

			update_post_meta( $id, '_icl_lang_duplicate_of', $master_post->ID );

			if ( $sitepress->get_option( 'sync_post_taxonomies' ) ) {
				$this->duplicate_taxonomies( $master_post_id, $lang );
			}
			$this->duplicate_custom_fields( $master_post_id, $lang );

			$ret = $id;
			do_action( 'icl_make_duplicate', $master_post_id, $lang, $post_array, $id );

		} else {
			$ret = false;
		}


		return $ret;

	}

	function duplicate_taxonomies( $master_post_id, $lang )
	{
		global $wpdb, $sitepress;

		$post_type = get_post_field( 'post_type', $master_post_id );

		$taxonomies = get_object_taxonomies( $post_type );

		$trid              = $sitepress->get_element_trid( $master_post_id, 'post_' . $post_type );
		$duplicate_post_id = false;
		if ( $trid ) {
			$translations = $sitepress->get_element_translations( $trid, 'post_' . $post_type, false, false, true );
			if ( isset( $translations[ $lang ] ) ) {
				$duplicate_post_id = $translations[ $lang ]->element_id;
				/* If we have an existing post, we first of all remove all terms currently attached to it.
				 * The main reason behind is the removal of the potentially present default category on the post.
				 */
				wp_delete_object_term_relationships( $duplicate_post_id, $taxonomies );
			} else {
				return false; // translation not found!
			}
		}

		foreach ( $taxonomies as $taxonomy ) {
			if ( $sitepress->is_translated_taxonomy ( $taxonomy ) ) {
				WPML_Terms_Translations::sync_post_and_taxonomy_terms_language( $master_post_id, $taxonomy, true );
			}
		}
		return true;
	}

	function duplicate_custom_fields( $master_post_id, $lang )
	{
		global $wpdb, $sitepress;

		$duplicate_post_id = false;
		$post_type = get_post_field( 'post_type', $master_post_id );

		$trid = $sitepress->get_element_trid( $master_post_id, 'post_' . $post_type );
		if ( $trid ) {
			$translations = $sitepress->get_element_translations( $trid, 'post_' . $post_type );
			if ( isset( $translations[ $lang ] ) ) {
				$duplicate_post_id = $translations[ $lang ]->element_id;
			} else {
				return false; // translation not found!
			}
		}

		$default_exceptions = array(
				'_wp_old_slug',
				'_edit_last',
				'_edit_lock',
				'_icl_translator_note',
				'_icl_lang_duplicate_of',
				'_wpml_media_duplicate',
				'_wpml_media_featured'
		);

		$exceptions = $default_exceptions;
		//Todo: make sure the following filter won't remove the default exceptions
		$exceptions = apply_filters('wpml_duplicate_custom_fields_exceptions', $exceptions);

		// low level copy
		$custom_fields_master    = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id=%d group by meta_key", $master_post_id ) );
		$custom_fields_duplicate = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id=%d group by meta_key", $duplicate_post_id ) );

		$custom_fields_master    = array_diff( $custom_fields_master, $exceptions );
		$custom_fields_duplicate = array_diff( $custom_fields_duplicate, $exceptions );

		$remove = array_diff( $custom_fields_duplicate, $custom_fields_master );
		foreach ( $remove as $key ) {
			delete_post_meta( $duplicate_post_id, $key );
		}

		foreach ( $custom_fields_master as $key ) {
			$master_custom_field_values_array    = get_post_meta( $master_post_id, $key );
			$master_custom_field_values_single    = get_post_meta( $master_post_id, $key, true );

			$is_repeated = false;
			if($master_custom_field_values_array != $master_custom_field_values_single) {
				//Repeated fields
				$master_custom_field_values	= $master_custom_field_values_array;
				$is_repeated = true;
			} else {
				//Field stored as serialized array
				$master_custom_field_values[] = $master_custom_field_values_single;
			}

			if($is_repeated) {
				$duplicate_custom_field_values = get_post_meta( $duplicate_post_id, $key );
			} else {
				$duplicate_custom_field_values[] = get_post_meta( $duplicate_post_id, $key, true );
			}

			if ( !$duplicate_custom_field_values || $master_custom_field_values != $duplicate_custom_field_values ) {
				if($is_repeated) {
					//Delete the old one
					delete_post_meta($duplicate_post_id, $key);
					//And add new ones from the original
					foreach($master_custom_field_values as $master_custom_field_value) {
						add_post_meta( $duplicate_post_id, $key, addslashes_gpc(apply_filters( 'icl_duplicate_generic_string', $master_custom_field_value, $lang, array( 'context' => 'custom_field', 'attribute' => 'value', 'key' => $key ) ) ) );
					}
				} else {
					update_post_meta( $duplicate_post_id, $key, addslashes_gpc(apply_filters( 'icl_duplicate_generic_string', $master_custom_field_value, $lang, array( 'context' => 'custom_field', 'attribute' => 'value', 'key' => $key ) ) ) );
				}
			}
		}

		return true;
	}

	function duplicate_fix_children( $master_post_id, $lang )
	{
		global $wpdb;

		$post_type       = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID=%d", $master_post_id ) );
		$master_children = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent=%d AND post_type != 'revision'", $master_post_id ) );
		$dup_parent      = icl_object_id( $master_post_id, $post_type, false, $lang );

		if ( $master_children ) {
			foreach ( $master_children as $master_child ) {
				$dup_child = icl_object_id( $master_child, $post_type, false, $lang );
				if ( $dup_child ) {
					$wpdb->update( $wpdb->posts, array( 'post_parent' => $dup_parent ), array( 'ID' => $dup_child ) );
				}
				$this->duplicate_fix_children( $master_child, $lang );
			}
		}
	}

	function make_duplicates_all( $master_post_id )
	{
		global $sitepress;

		$master_post               = get_post( $master_post_id );
		if($master_post->post_status == 'auto-draft' || $master_post->post_type == 'revision') {
			return;
		}

		$language_details_original = $sitepress->get_element_language_details( $master_post_id, 'post_' . $master_post->post_type );

		if(!$language_details_original) return;

		$data[ 'iclpost' ] = array( $master_post_id );
		foreach ( $sitepress->get_active_languages() as $lang => $details ) {
			if ( $lang != $language_details_original->language_code ) {
				$data[ 'duplicate_to' ][ $lang ] = 1;
			}
		}

		$this->make_duplicates( $data );
	}

	function reset_duplicate_flag( $post_id )
	{
		global $sitepress;

		$post = get_post( $post_id );

		$trid         = $sitepress->get_element_trid( $post_id, 'post_' . $post->post_type );
		$translations = $sitepress->get_element_translations( $trid, 'post_' . $post->post_type );

		foreach ( $translations as $tr ) {
			if ( $tr->element_id == $post_id ) {
				$this->update_translation_status( array(
													   'translation_id' => $tr->translation_id,
													   'status'         => ICL_TM_COMPLETE
												  ) );
			}
		}

		delete_post_meta( $post_id, '_icl_lang_duplicate_of' );


	}

	function set_duplicate( $post_id )
	{
		global $sitepress;
		// find original (source) and copy
		$post         = get_post( $post_id );
		$trid         = $sitepress->get_element_trid( $post_id, 'post_' . $post->post_type );
		$translations = $sitepress->get_element_translations( $trid, 'post_' . $post->post_type );

		foreach ( $translations as $lang => $tr ) {
			if ( $tr->original ) {
				$master_post_id = $tr->element_id;
			} elseif ( $tr->element_id == $post_id ) {
				$this_language = $lang;
			}
		}

		$this->make_duplicate( $master_post_id, $this_language );

	}

	function get_duplicates( $master_post_id )
	{
		global $wpdb, $sitepress;

		$duplicates = array();

		$res = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta}
		 WHERE meta_key='_icl_lang_duplicate_of' AND meta_value=%d", $master_post_id ) );

		foreach ( $res as $post_id ) {

			$post             = get_post( $post_id );
			$language_details = $sitepress->get_element_language_details( $post_id, 'post_' . $post->post_type );

			$duplicates[ $language_details->language_code ] = $post_id;
		}

		return $duplicates;

	}

	function duplicate_comments( $post_id, $master_post_id ) {
		global $wpdb, $sitepress;

		// delete existing comments
		$current_comments = $wpdb->get_results( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID = %d", $post_id ) );
		foreach ( $current_comments as $current_comment ) {
			if ( isset( $current_comment->comment_ID ) && is_numeric( $current_comment->comment_ID ) ) {
				wp_delete_comment( $current_comment->comment_ID );
			}
		}


		$original_comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_post_id = %d", $master_post_id ), ARRAY_A );

		$post_type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID=%d", $post_id ) );
		$language  = $wpdb->get_var( $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $post_id, 'post_' . $post_type ) );

		$wpdb->update( $wpdb->posts, array( 'comment_count' => count( $original_comments ) ), array( 'ID' => $post_id ) );

		foreach ( $original_comments as $comment ) {

			$original_comment_id = $comment[ 'comment_ID' ];
			unset( $comment[ 'comment_ID' ] );

			$comment[ 'comment_post_ID' ] = $post_id;
			$wpdb->insert( $wpdb->comments, $comment );
			$comment_id = $wpdb->insert_id;

			update_comment_meta( $comment_id, '_icl_duplicate_of', $original_comment_id );

			// comment meta
			$meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->commentmeta} WHERE comment_id=%d", $original_comment_id ) );
			foreach ( $meta as $meta_data ) {
				if ( is_object( $meta_data ) && isset( $meta_data->meta_key ) && isset( $meta_data->meta_value ) ) {
					$wpdb->insert( $wpdb->commentmeta, array(
						'comment_id' => $comment_id,
						'meta_key'   => $meta_data->meta_key,
						'meta_value' => $meta_data->meta_value
					) );
				}
			}

			$original_comment_tr = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $original_comment_id, 'comment' ) );

			if ( $original_comment_tr && isset( $original_comment_tr->trid ) ) {
				$comment_translation = array(
					'element_type'  => 'comment',
					'element_id'    => $comment_id,
					'trid'          => $original_comment_tr->trid,
					'language_code' => $language,
					/*'source_language_code'  => $original_comment_tr->language_code */
				);

				$comments_map[ $original_comment_id ] = array( 'trid' => $original_comment_tr->trid, 'comment' => $comment_id );

				$existing_translation_tr = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND element_type=%s AND language_code=%s", $original_comment_tr->trid, 'comment', $language ) );

				if ( $existing_translation_tr ) {
					$wpdb->update( $wpdb->prefix . 'icl_translations', $comment_translation, array( 'trid' => $comment_id, 'element_type' => 'comment', 'language_code' => $language ) );
				} else {
					$wpdb->insert( $wpdb->prefix . 'icl_translations', $comment_translation );
				}
			}
		}

		// sync parents
		foreach ( $original_comments as $comment ) {
			if ( $comment[ 'comment_parent' ] ) {

				$tr_comment_id = $comments_map[ $comment[ 'comment_ID' ] ][ 'comment' ];
				$tr_parent     = icl_object_id( $comment[ 'comment_parent' ], 'comment', false, $language );
				if ( $tr_parent ) {
					$wpdb->update( $wpdb->comments, array( 'comment_parent' => $tr_parent ), array( 'comment_ID' => $tr_comment_id ) );
				}

			}
		}


	}

	function duplication_delete_comment( $comment_id )
	{
		global $wpdb;
		static $_avoid_8_loop;

		if ( isset( $_avoid_8_loop ) ) {
			return;
		}
		$_avoid_8_loop = true;

		$original_comment = get_comment_meta( $comment_id, '_icl_duplicate_of', true );
		if ( $original_comment ) {
			$duplicates = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='_icl_duplicate_of' AND meta_value=%d", $original_comment ) );
			$duplicates = array( $original_comment ) + array_diff( $duplicates, array( $comment_id ) );
			foreach ( $duplicates as $dup ) {
				wp_delete_comment( $dup );
			}
		} else {
			$duplicates = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='_icl_duplicate_of' AND meta_value=%d", $comment_id ) );
			if ( $duplicates ) {
				foreach ( $duplicates as $dup ) {
					wp_delete_comment( $dup );
				}
			}
		}

		unset( $_avoid_8_loop );
	}

	function duplication_edit_comment( $comment_id )
	{
		global $wpdb;

		$comment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_ID=%d", $comment_id ), ARRAY_A );
		unset( $comment[ 'comment_ID' ], $comment[ 'comment_post_ID' ] );

		$comment_meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->commentmeta} WHERE comment_id=%d AND meta_key <> '_icl_duplicate_of'", $comment_id ) );

		$original_comment = get_comment_meta( $comment_id, '_icl_duplicate_of', true );
		if ( $original_comment ) {
			$duplicates = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='_icl_duplicate_of' AND meta_value=%d", $original_comment ) );
			$duplicates = array( $original_comment ) + array_diff( $duplicates, array( $comment_id ) );
		} else {
			$duplicates = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='_icl_duplicate_of' AND meta_value=%d", $comment_id ) );
		}

		if ( !empty( $duplicates ) ) {
			foreach ( $duplicates as $dup ) {

				$wpdb->update( $wpdb->comments, $comment, array( 'comment_ID' => $dup ) );

				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->commentmeta} WHERE comment_id=%d AND meta_key <> '_icl_duplicate_of'", $dup ) );

				if ( $comment_meta ) {
					foreach ( $comment_meta as $key => $value ) {
						update_comment_meta( $dup, $key, $value );
					}
				}

			}
		}


	}

	function duplication_status_comment( $comment_id, $comment_status )
	{
		global $wpdb;

		static $_avoid_8_loop;

		if ( isset( $_avoid_8_loop ) ) {
			return;
		}
		$_avoid_8_loop = true;


		$original_comment = get_comment_meta( $comment_id, '_icl_duplicate_of', true );
		if ( $original_comment ) {
			$duplicates = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='_icl_duplicate_of' AND meta_value=%d", $original_comment ) );
			$duplicates = array( $original_comment ) + array_diff( $duplicates, array( $comment_id ) );
		} else {
			$duplicates = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='_icl_duplicate_of' AND meta_value=%d", $comment_id ) );
		}

		if ( !empty( $duplicates ) ) {
			foreach ( $duplicates as $duplicate ) {
				wp_set_comment_status( $duplicate, $comment_status );
			}
		}

		unset( $_avoid_8_loop );


	}

	function duplication_insert_comment( $comment_id )
	{
		global $wpdb, $sitepress;

		$comment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_ID=%d", $comment_id ), ARRAY_A );

		// loop duplicate posts, add new comment
		$post_id = $comment[ 'comment_post_ID' ];

		// if this is a duplicate post
		$duplicate_of = get_post_meta( $post_id, '_icl_lang_duplicate_of', true );
		if ( $duplicate_of ) {
			$post_duplicates = $this->get_duplicates( $duplicate_of );
			$_lang           = $wpdb->get_var( $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_type='comment' AND element_id=%d", $comment_id ) );
			unset( $post_duplicates[ $_lang ] );
			$_post                          = get_post( $duplicate_of );
			$_orig_lang                     = $sitepress->get_language_for_element( $duplicate_of, 'post_' . $_post->post_type );
			$post_duplicates[ $_orig_lang ] = $duplicate_of;
		} else {
			$post_duplicates = $this->get_duplicates( $post_id );
		}

		unset( $comment[ 'comment_ID' ], $comment[ 'comment_post_ID' ] );

		foreach ( $post_duplicates as $lang => $dup_id ) {
			$comment[ 'comment_post_ID' ] = $dup_id;

			if ( $comment[ 'comment_parent' ] ) {
				$comment[ 'comment_parent' ] = icl_object_id( $comment[ 'comment_parent' ], 'comment', false, $lang );
			}


			$wpdb->insert( $wpdb->comments, $comment );

			$dup_comment_id = $wpdb->insert_id;

			update_comment_meta( $dup_comment_id, '_icl_duplicate_of', $comment_id );

			// comment meta
			$meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->commentmeta} WHERE comment_id=%d", $comment_id ) );
			foreach ( $meta as $key => $val ) {
				$wpdb->insert( $wpdb->commentmeta, array(
														'comment_id' => $dup_comment_id,
														'meta_key'   => $key,
														'meta_value' => $val
												   ) );
			}

			$original_comment_tr = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $comment_id, 'comment' ) );

			$comment_translation = array(
				'element_type'  => 'comment',
				'element_id'    => $dup_comment_id,
				'trid'          => $original_comment_tr->trid,
				'language_code' => $lang,
				/*'source_language_code'  => $original_comment_tr->language_code */
			);

			$wpdb->insert( $wpdb->prefix . 'icl_translations', $comment_translation );

		}


	}

	function delete_post_actions($post_id){
		global $wpdb;
		$post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID={$post_id}");
		if(!empty($post_type)){
			$translation_id = $wpdb->get_var($wpdb->prepare("SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $post_id, 'post_' . $post_type));
			if($translation_id){
				$rid = $wpdb->get_var($wpdb->prepare("SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", $translation_id));
				$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", $translation_id));
				if($rid){
					$jobs = $wpdb->get_col($wpdb->prepare("SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d", $rid));
					$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d", $rid));
					if(!empty($jobs)){
						$wpdb->query("DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id IN (".join(',', $jobs).")");
					}
				}
			}
		}
	}

	function edit_term($cat_id, $tt_id){
		global $wpdb, $sitepress;

		$el_type = $wpdb->get_var("SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id={$tt_id}");

		if(!$sitepress->is_translated_taxonomy($el_type)){
			return;
		};

		$icl_el_type = 'tax_' . $el_type;

		// has trid only when it's a translation of another tag
		$trid = isset($_POST['icl_trid']) && (isset($_POST['icl_'.$icl_el_type.'_language'])) ? $_POST['icl_trid']:null;
		// see if we have a "translation of" setting.
		$src_language = false;
		if (isset($_POST['icl_translation_of']) && $_POST['icl_translation_of']) {
			$src_term_id = $_POST['icl_translation_of'];
			if ($src_term_id != 'none') {
				$res = $wpdb->get_row("SELECT trid, language_code
					FROM {$wpdb->prefix}icl_translations WHERE element_id={$src_term_id} AND element_type='{$icl_el_type}'");
				$trid = $res->trid;
				$src_language = $res->language_code;
			} else {
				$trid = null;
			}
		}

		if(isset($_POST['action']) && $_POST['action'] == 'inline-save-tax'){
			$trid = $sitepress->get_element_trid($tt_id, $icl_el_type);
		}

	// update icl_translate if necessary
		// get the terms translations
		$element_translations = $sitepress->get_element_translations($trid, $icl_el_type);
		$tr_ids = array();
		foreach($element_translations as $el_lang => $el_info){
			if($tt_id != $el_info->term_id){
				$tr_ids[] = $el_info->term_id;
			}
		}

		// does the term taxonomy we are currently editing have a record in the icl_translate?
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT field_data, job_id FROM {$wpdb->prefix}icl_translate WHERE field_type = %s", $el_type.'_ids'
		));

		if(!empty($results)){
			$job_ids = array(); // this will hold the job ids that contain our term under field_data
			$job_ids2 = array(); // this will hold the job ids that contain our term under field_data_translated
			foreach($results as $result){
				$r_ids = explode (',', $result->field_data);
				// is the term found?
				if(in_array ($tt_id, $r_ids)){
					$job_ids[] = $result->job_id;
					// this is used further down to identify the correct term name to update
					foreach($r_ids as $k => $v){
						if($v == $tt_id){
							$t_k[] = $k;
						}
					}
				}

				// the name of the current category being edited could also be under field_data_translated
				if(!empty($tr_ids)){
					foreach($tr_ids as $tr_id){
						// is the term found?
						if(in_array ($tr_id, $r_ids)){
							$job_ids2[] = $result->job_id;
							// this is used further down to identify the correct term name to update
							foreach($r_ids as $k => $v){
								if($v == $tr_id){
									$t_k2[] = $k;
								}
							}
						}
					}
				}
			}

			// if we have job_ids to be updated proceed
			if(!empty($job_ids)){
				$in = implode (',', $job_ids);
				$field_type = ($el_type == 'category' ? 'categories' : $el_type);
				//grab the term names
				$results = $wpdb->get_results( $wpdb->prepare(
					"SELECT tid, field_data, field_format FROM {$wpdb->prefix}icl_translate WHERE job_id IN ({$in}) AND field_type = %s", $field_type
				));

				$count = 0;
				foreach($results as $result){
					// decode
					$decoded_data = self::decode_field_data($result->field_data, $result->field_format);
					// we may have multiple comma separated term names - pass the new term name to the correct one!
					$decoded_data[$t_k[$count]] = $_POST['name'];
					// encode
					$encoded_data =$this->encode_field_data($decoded_data, $result->field_format);
					// update
					$wpdb->update($wpdb->prefix.'icl_translate',
							array('field_data'=>$encoded_data),
							array('tid'=>$result->tid)
						);
				$count++;
				}

				// update the translation status as "needs_update"
				foreach($job_ids as $job_id){
					list($translator_id, $rid) = $wpdb->get_row($wpdb->prepare("SELECT translator_id, rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $job_id), ARRAY_N);
					// update
					$wpdb->update($wpdb->prefix.'icl_translation_status',
							array('needs_update'=>1),
							array('rid'=>$rid, 'translator_id'=>$translator_id)
						);
				}
			}

			// if we have job_ids2 to be updated proceed
			if(!empty($job_ids2)){
				$in = implode (',', $job_ids2);
				$field_type = ($el_type == 'category' ? 'categories' : $el_type);
				//grab the term names
				$results = $wpdb->get_results( $wpdb->prepare(
					"SELECT tid, field_data_translated, field_format FROM {$wpdb->prefix}icl_translate WHERE job_id IN ({$in}) AND field_type  = %s", $field_type
				));

				$count = 0;
				foreach($results as $result){
					if(!empty($result->field_data_translated)){
						// decode
						$decoded_data = self::decode_field_data($result->field_data_translated, $result->field_format);
						// we may have multiple comma separated term names - pass the new term name to the correct one!
						$decoded_data[$t_k2[$count]] = $_POST['name'];
						// encode
						$encoded_data =$this->encode_field_data($decoded_data, $result->field_format);
						// update
						$wpdb->update($wpdb->prefix.'icl_translate',
								array('field_data_translated'=>$encoded_data),
								array('tid'=>$result->tid)
							);
					}
				$count++;
				}
			}
		}
	}

	/* TRANSLATIONS */
	/* ******************************************************************************************** */
	/**
	* calculate post md5
	*
	* @param object|int $post
	* @return string
	*
	* @todo full support for custom posts and custom taxonomies
	*/
	function post_md5($post){

		if (isset($post->external_type) && $post->external_type) {

			$md5str = '';

			foreach ($post->string_data as $key => $value) {
				$md5str .= $key . $value;
			}


		} else {

			$post_tags = $post_categories = $custom_fields_values = array();

			if(is_numeric($post)){
				$post = get_post($post);
			}

			foreach(wp_get_object_terms($post->ID, 'post_tag') as $tag){
				$post_tags[] = $tag->name;
			}
			if(is_array($post_tags)){
				sort($post_tags, SORT_STRING);
			}
			foreach(wp_get_object_terms($post->ID, 'category') as $cat){
				$post_categories[] = $cat->name;
			}
			if(is_array($post_categories)){
				sort($post_categories, SORT_STRING);
			}

			global $wpdb, $sitepress_settings;
			// get custom taxonomies
			$taxonomies = $wpdb->get_col("
				SELECT DISTINCT tx.taxonomy
				FROM {$wpdb->term_taxonomy} tx JOIN {$wpdb->term_relationships} tr ON tx.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tr.object_id = {$post->ID}
			");

			sort($taxonomies, SORT_STRING);
			foreach($taxonomies as $t){
				if(taxonomy_exists($t)){
					if(@intval($sitepress_settings['taxonomies_sync_option'][$t]) == 1){
						$taxs = array();
						foreach(wp_get_object_terms($post->ID, $t) as $trm){
							$taxs[] = $trm->name;
						}
						if($taxs){
							sort($taxs,SORT_STRING);
							$all_taxs[] = '['.$t.']:'.join(',',$taxs);
						}
					}
				}
			}

			$custom_fields_values = array();
			if ( is_array( $this->settings['custom_fields_translation'] ) ) {
				foreach ( $this->settings['custom_fields_translation'] as $cf => $op ) {
					if ( $op == 2 || $op == 1 ) {
						$value = get_post_meta( $post->ID, $cf, true );
						if ( !is_array( $value ) && !is_object( $value ) ) {
							$custom_fields_values[] = $value;
						}
					}
				}
			}

			$md5str =
				$post->post_title . ';' .
				$post->post_content . ';' .
				join(',',$post_tags).';' .
				join(',',$post_categories) . ';' .
				join(',', $custom_fields_values);

			if(!empty($all_taxs)){
				$md5str .= ';' . join(';', $all_taxs);
			}

			if($sitepress_settings['translated_document_page_url'] == 'translate'){
				$md5str .=  $post->post_name . ';';
			}


		}

		$md5 = md5($md5str);

		return $md5;
	}

	/**
	 * get documents
	 *
	 * @param array $args
	 *
	 * @return mixed
	 */
	function get_documents($args){


		$parent_id = false;
		$parent_all = false;
		$to_lang = false;
		$from_lang = false;
		$tstatus = false;
		$sort_by = false;
		$sort_order = false;
		$limit_no = 0;

		extract($args);

		global $wpdb, $wp_query, $sitepress;

		$t_el_types = array_keys($sitepress->get_translatable_documents());

		// SELECT
		$select = " p.ID AS post_id, p.post_title, p.post_content, p.post_type, p.post_status, p.post_date, t.trid, t.source_language_code <> '' AS is_translation";
		$active_languages = $sitepress->get_active_languages();
		if($to_lang){
			$select .= ", iclts.status, iclts.needs_update";
		}else{
			foreach( $active_languages as $lang){
				if($lang['code'] == $from_lang) continue;
				$tbl_alias_suffix = str_replace('-','_',$lang['code']);
				$select .= ", iclts_{$tbl_alias_suffix}.status AS status_{$tbl_alias_suffix}, iclts_{$tbl_alias_suffix}.needs_update AS needs_update_{$tbl_alias_suffix}";
			}
		}

		// FROM
		$from   = " {$wpdb->posts} p";

		// JOIN
		$join = "";
		$join   .= " LEFT JOIN {$wpdb->prefix}icl_translations t ON t.element_id=p.ID\n";
		if($to_lang){
			$tbl_alias_suffix = str_replace('-','_',$to_lang);
			$join .= " LEFT JOIN {$wpdb->prefix}icl_translations iclt_{$tbl_alias_suffix}
						ON iclt_{$tbl_alias_suffix}.trid=t.trid AND iclt_{$tbl_alias_suffix}.language_code='{$to_lang}'\n";
			$join   .= " LEFT JOIN {$wpdb->prefix}icl_translation_status iclts ON iclts.translation_id=iclt_{$tbl_alias_suffix}.translation_id\n";
		}else{
			foreach( $active_languages as $lang){
				if($lang['code'] == $from_lang) continue;
				$tbl_alias_suffix = str_replace('-','_',$lang['code']);
				$join .= " LEFT JOIN {$wpdb->prefix}icl_translations iclt_{$tbl_alias_suffix}
						ON iclt_{$tbl_alias_suffix}.trid=t.trid AND iclt_{$tbl_alias_suffix}.language_code='{$lang['code']}'\n";
				$join   .= " LEFT JOIN {$wpdb->prefix}icl_translation_status iclts_{$tbl_alias_suffix}
						ON iclts_{$tbl_alias_suffix}.translation_id=iclt_{$tbl_alias_suffix}.translation_id\n";
			}
		}


		// WHERE
		$where = " t.language_code = '{$from_lang}' AND p.post_status NOT IN ('trash', 'auto-draft') \n";
		if(!empty($type)){
			$where .= " AND p.post_type = '{$type}'";
			$where .= " AND t.element_type = 'post_{$type}'\n";
		}else{
			$where .= " AND p.post_type IN ('".join("','",$t_el_types)."')\n";
			foreach($t_el_types as $k=>$v){
				$t_el_types[$k] = 'post_' . $v;
			}
			$where .= " AND t.element_type IN ('".join("','",$t_el_types)."')\n";
		}
		if(!empty($title)){
			$where .= " AND p.post_title LIKE '%".esc_sql($title)."%'\n";
		}

		if(!empty($status)){
			$where .= " AND p.post_status = '{$status}'\n";
		}

		if(isset($from_date)){
			$where .= " AND p.post_date > '{$from_date}'\n";
		}

		if(isset($to_date)){
			$where .= " AND p.post_date > '{$to_date}'\n";
		}

		if($tstatus){
			if($to_lang){
				if($tstatus == 'not'){
					$where .= " AND (iclts.status IS NULL OR iclts.status = ".ICL_TM_WAITING_FOR_TRANSLATOR." OR iclts.needs_update = 1)\n";
				}elseif($tstatus == 'need-update'){
					$where .= " AND iclts.needs_update = 1\n";
				}elseif($tstatus == 'in_progress'){
					$where .= " AND iclts.status = ".ICL_TM_IN_PROGRESS." AND iclts.needs_update = 0\n";
				}elseif($tstatus == 'complete'){
					$where .= " AND (iclts.status = ".ICL_TM_COMPLETE." OR iclts.status = ".ICL_TM_DUPLICATE.") AND iclts.needs_update = 0\n";
				}

			}elseif( $active_languages && count($active_languages)>1 ){
				if($tstatus == 'not'){
					$where .= " AND (";
					$wheres = array();
					foreach( $active_languages as $lang){
						if($lang['code'] == $from_lang) continue;
						$tbl_alias_suffix = str_replace('-','_',$lang['code']);
						$wheres[] = "iclts_{$tbl_alias_suffix}.status IS NULL OR iclts_{$tbl_alias_suffix}.status = ".ICL_TM_WAITING_FOR_TRANSLATOR." OR iclts_{$tbl_alias_suffix}.needs_update = 1\n";
					}
					$where .= join(' OR ', $wheres) . ")";
				}elseif($tstatus == 'need-update'){
					$where .= " AND (";
					$wheres = array();
					foreach( $active_languages as $lang){
						if($lang['code'] == $from_lang) continue;
						$tbl_alias_suffix = str_replace('-','_',$lang['code']);
						$wheres[] = "iclts_{$tbl_alias_suffix}.needs_update = 1\n";
					}
					$where .= join(' OR ', $wheres) . ")";
				}elseif($tstatus == 'in_progress'){
					$where .= " AND (";
					$wheres = array();
					foreach( $active_languages as $lang){
						if($lang['code'] == $from_lang) continue;
						$tbl_alias_suffix = str_replace('-','_',$lang['code']);
						$wheres[] = "iclts_{$tbl_alias_suffix}.status = ".ICL_TM_IN_PROGRESS."\n";
					}
					$where .= join(' OR ', $wheres)  . ")";
				}elseif($tstatus == 'complete'){
					foreach( $active_languages as $lang){
						if($lang['code'] == $from_lang) continue;
						$tbl_alias_suffix = str_replace('-','_',$lang['code']);
						$where .= " AND (iclts_{$tbl_alias_suffix}.status = ".ICL_TM_COMPLETE." OR iclts_{$tbl_alias_suffix}.status = ".ICL_TM_DUPLICATE.") AND iclts_{$tbl_alias_suffix}.needs_update = 0\n";
					}
				}
			}
		}

		if(isset($parent_type) && $parent_type == 'page' && $parent_id > 0){
			if($parent_all){
				$children = icl_get_post_children_recursive($parent_id);
				if(!$children) $children[] = -1;
				$where .= ' AND p.ID IN (' . join(',', $children) . ')';
			}else{
				$where .= ' AND p.post_parent=' . intval($parent_id);
			}
		}

		if(isset($parent_type) && $parent_type == 'category' && $parent_id > 0){
			if($parent_all){
				$children = icl_get_tax_children_recursive($parent_id);
				$children[] = $parent_id;
				$join .= "  JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
							JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND taxonomy = 'category'
							JOIN {$wpdb->terms} tm ON tt.term_id = tm.term_id AND tm.term_id IN(" . join(',', $children) . ")";
			}else{
				$join .= "  JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
							JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND taxonomy = 'category'
							JOIN {$wpdb->terms} tm ON tt.term_id = tm.term_id AND tm.term_id = " . intval($parent_id);
			}
		}

		// ORDER
		if($sort_by){
			$order = " $sort_by ";
		}else{
			$order = " p.post_date DESC";
		}
		if($sort_order){
			$order .= $sort_order;
		}else{
			$order .= 'DESC';
		}



		// LIMIT
		if(!isset($_GET['paged'])) $_GET['paged'] = 1;
		$offset = ($_GET['paged']-1)*$limit_no;
		$limit = " " . $offset . ',' . $limit_no;


		$sql = "
			SELECT SQL_CALC_FOUND_ROWS {$select}
			FROM {$from}
			{$join}
			WHERE {$where}
			ORDER BY {$order}
			LIMIT {$limit}
		";

		$results = $wpdb->get_results($sql);

		$count = $wpdb->get_var("SELECT FOUND_ROWS()");

		$wp_query->found_posts = $count;
		$wp_query->query_vars['posts_per_page'] = $limit_no;
		$wp_query->max_num_pages = ceil($wp_query->found_posts/$limit_no);

		// post process
		foreach($results as $k=>$v){
			if($v->is_translation){
				$source_language = $wpdb->get_var($wpdb->prepare("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND source_language_code IS NULL", $v->trid));
				$_tmp = 'status_' . $source_language;
				$v->$_tmp = ICL_TM_COMPLETE;
			}
		}

		return $results;

	}

	function get_element_translation($element_id, $language, $element_type='post_post'){
		global $wpdb, $sitepress;
		$trid = $sitepress->get_element_trid($element_id, $element_type);
		$translation = array();
		if($trid){
			$translation = $wpdb->get_row($wpdb->prepare("
				SELECT *
				FROM {$wpdb->prefix}icl_translations tr
				JOIN {$wpdb->prefix}icl_translation_status ts ON tr.translation_id = ts.translation_id
				WHERE tr.trid=%s AND tr.language_code='%s'
			", $trid, $language));
		}
		return $translation;
	}

	function get_element_translations($element_id, $element_type='post_post', $service = false){
		global $wpdb, $sitepress;
		$trid = $sitepress->get_element_trid($element_id, $element_type);
		$translations = array();
		if($trid){
			$service =  $service ? " AND translation_service = '$service'" : '';
			$translations = $wpdb->get_results($wpdb->prepare("
				SELECT *
				FROM {$wpdb->prefix}icl_translations tr
				JOIN {$wpdb->prefix}icl_translation_status ts ON tr.translation_id = ts.translation_id
				WHERE tr.trid=%s {$service}
			", $trid));
			foreach($translations as $k=>$v){
				$translations[$v->language_code] = $v;
				unset($translations[$k]);
			}
		}
		return $translations;
	}

	/**
	 * returns icon file name according to status code
	 *
	 * @param int $status
	 * @param int $needs_update
	 *
	 * @return string
	 */
	public function status2img_filename($status, $needs_update = 0){
		if($needs_update){
			$img_file = 'needs-update.png';
		}else{
			switch($status){
				case ICL_TM_NOT_TRANSLATED: $img_file = 'not-translated.png'; break;
				case ICL_TM_WAITING_FOR_TRANSLATOR: $img_file = 'in-progress.png'; break;
				case ICL_TM_IN_PROGRESS: $img_file = 'in-progress.png'; break;
				case ICL_TM_NEEDS_UPDATE: $img_file = 'needs-update.png'; break;
				case ICL_TM_DUPLICATE: $img_file = 'copy.png'; break;
				case ICL_TM_COMPLETE: $img_file = 'complete.png'; break;
				default: $img_file = '';
			}
		}
		return $img_file;
	}

	public function status2text($status){
		switch($status){
			case ICL_TM_NOT_TRANSLATED: $text = __('Not translated', 'sitepress'); break;
			case ICL_TM_WAITING_FOR_TRANSLATOR: $text = __('Waiting for translator', 'sitepress'); break;
			case ICL_TM_IN_PROGRESS: $text = __('In progress', 'sitepress'); break;
			case ICL_TM_NEEDS_UPDATE: $text = __('Needs update', 'sitepress'); break;
			case ICL_TM_DUPLICATE: $text = __('Duplicate', 'sitepress'); break;
			case ICL_TM_COMPLETE: $text = __('Complete', 'sitepress'); break;
			default: $text = '';
		}
		return $text;
	}

	public static function estimate_word_count($data, $lang_code){
		global $asian_languages;

		$words = 0;
		if(isset($data->post_title)){
			if(in_array($lang_code, $asian_languages)){
				$words += strlen(strip_tags($data->post_title)) / 6;
			} else {
				$words += count(preg_split(
					'/[\s\/]+/', $data->post_title, 0, PREG_SPLIT_NO_EMPTY));
			}
		}
		if(isset($data->post_content)){
			if(in_array($lang_code, $asian_languages)){
				$words += strlen(strip_tags($data->post_content)) / 6;
			} else {
				$words += count(preg_split(
					'/[\s\/]+/', strip_tags($data->post_content), 0, PREG_SPLIT_NO_EMPTY));
			}
		}

		return (int)$words;

	}

	public static function estimate_custom_field_word_count( $post_id, $lang_code ) {
		global $asian_languages, $sitepress_settings;

		$words = 0;

		if ( !empty( $sitepress_settings[ 'translation-management' ][ 'custom_fields_translation' ] ) && is_array( $sitepress_settings[ 'translation-management' ][ 'custom_fields_translation' ] ) ) {
			$custom_fields = array();
			foreach ( $sitepress_settings[ 'translation-management' ][ 'custom_fields_translation' ] as $cf => $op ) {
				if ( $op == 2 ) {
					$custom_fields[ ] = $cf;
				}
			}
			foreach ( $custom_fields as $cf ) {
				$custom_fields_value = get_post_meta( $post_id, $cf );
				if ( $custom_fields_value ) {
					if ( is_scalar( $custom_fields_value ) ) { // only support scalar values fo rnow
						if ( in_array( $lang_code, $asian_languages ) ) {
							$words += strlen( strip_tags( $custom_fields_value ) ) / 6;
						} else {
							$words += count( preg_split( '/[\s\/]+/', strip_tags( $custom_fields_value ), 0, PREG_SPLIT_NO_EMPTY ) );
						}
					} else {
						foreach ( $custom_fields_value as $custom_fields_value_item ) {
							if ( $custom_fields_value_item && is_scalar( $custom_fields_value_item ) ) { // only support scalar values fo rnow
								if ( in_array( $lang_code, $asian_languages ) ) {
									$words += strlen( strip_tags( $custom_fields_value_item ) ) / 6;
								} else {
									$words += count( preg_split( '/[\s\/]+/', strip_tags( $custom_fields_value_item ), 0, PREG_SPLIT_NO_EMPTY ) );
								}
							}
						}
					}
				}
			}
		}

		return (int)$words;
	}

	public static function decode_field_data($data, $format){
		if($format == 'base64'){
			$data = base64_decode($data);
		}elseif($format == 'csv_base64'){
			$exp = explode(',', $data);
			foreach($exp as $k=>$e){
				$exp[$k] = base64_decode(trim($e,'"'));
			}
			$data = $exp;
		}
		return $data;
	}

	public function encode_field_data($data, $format){
		if($format == 'base64'){
			$data = base64_encode($data);
		}elseif($format == 'csv_base64'){
			$exp = $data;
			foreach($exp as $k=>$e){
				$exp[$k] = '"' . base64_encode(trim($e)) . '"';
			}
			$data = join(',', $exp);
		}
		return $data;
	}

	/**
	 * create translation package
	 *
	 * @param object|int $post
	 *
	 * @return array
	 */
	function create_translation_package($post){
		global $sitepress, $sitepress_settings;

		$package = array();


		if(is_numeric($post)){
			$post = get_post($post);
		}

		if (isset($post->external_type) && $post->external_type) {

			foreach ($post->string_data as $key => $value) {
				$package['contents'][$key] = array(
					'translate' => 1,
					'data'      => $this->encode_field_data($value, 'base64'),
					'format'    => 'base64'
				);
			}

			$package['contents']['original_id'] = array(
				'translate' => 0,
				'data'      => $post->post_id,
			);
		} else {
			$home_url = get_home_url();
			if($post->post_type=='page'){
				$package['url'] = htmlentities( $home_url . '?page_id=' . ($post->ID));
			}else{
				$package['url'] = htmlentities( $home_url . '?p=' . ($post->ID));
			}

			$package['contents']['title'] = array(
				'translate' => 1,
				'data'      => $this->encode_field_data($post->post_title, 'base64'),
				'format'    => 'base64'
			);

			if($sitepress_settings['translated_document_page_url'] == 'translate'){
				$package['contents']['URL'] = array(
					'translate' => 1,
					'data'      => $this->encode_field_data($post->post_name, 'base64'),
					'format'    => 'base64'
				);
			}

			$package['contents']['body'] = array(
				'translate' => 1,
				'data'      => $this->encode_field_data($post->post_content, 'base64'),
				'format'    => 'base64'
			);

			if(!empty($post->post_excerpt)){
				$package['contents']['excerpt'] = array(
					'translate' => 1,
					'data'      => base64_encode($post->post_excerpt),
					'format'    => 'base64'
				);
			}

			$package['contents']['original_id'] = array(
				'translate' => 0,
				'data'      => $post->ID
			);

			if(!empty($this->settings['custom_fields_translation']))
			foreach($this->settings['custom_fields_translation'] as $cf => $op){
				if ($op == 2) { // translate

					/* */
					$custom_fields_value = get_post_meta($post->ID, $cf, true);
					if ($custom_fields_value != '' && is_scalar($custom_fields_value)) {
						$package['contents']['field-'.$cf] = array(
							'translate' => 1,
							'data' => $this->encode_field_data($custom_fields_value, 'base64'),
							'format' => 'base64'
						);
						$package['contents']['field-'.$cf.'-name'] = array(
							'translate' => 0,
							'data' => $cf
						);
						$package['contents']['field-'.$cf.'-type'] = array(
							'translate' => 0,
							'data' => 'custom_field'
						);
					}
				}
			}

			foreach((array)$sitepress->get_translatable_taxonomies(true, $post->post_type) as $taxonomy){
				$terms = get_the_terms( $post->ID , $taxonomy );
				if(!empty($terms)){
					$_taxs = $_tax_ids = array();
					foreach($terms as $term){
						$_taxs[] = $term->name;
						$_tax_ids[] = $term->term_taxonomy_id;
					}
					if($taxonomy == 'post_tag'){
						$tax_package_key  = 'tags';
						$tax_id_package_key  = 'tag_ids';
					}
					elseif($taxonomy == 'category'){
						$tax_package_key  = 'categories';
						$tax_id_package_key  = 'category_ids';
					}
					else{
						$tax_package_key  = $taxonomy;
						$tax_id_package_key  = $taxonomy . '_ids';
					}

					$package['contents'][$tax_package_key] = array(
						'translate' => 1,
						'data'      => $this->encode_field_data($_taxs,'csv_base64'),
						'format'=>'csv_base64'
					);

					$package['contents'][$tax_id_package_key] = array(
						'translate' => 0,
						'data'      => join(',', $_tax_ids)
					);
				}
			}
		}
		return $package;
	}

	/**
	 * add/update icl_translation_status record
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	function update_translation_status($data){
		global $wpdb;
		if(!isset($data['translation_id'])) return;
		$rid = $wpdb->get_var($wpdb->prepare("SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", $data['translation_id']));
		if($rid){

			$wpdb->update($wpdb->prefix.'icl_translation_status', $data, array('rid'=>$rid));

			$update = true;
		}else{
			$wpdb->insert($wpdb->prefix.'icl_translation_status',$data);
			$rid = $wpdb->insert_id;
			$update = false;
		}

		return array($rid, $update);
	}

	function _get_post($post_id) {
		if (is_string($post_id) && strcmp(substr($post_id, 0, strlen('external_')), 'external_')===0) {
			$item = null;
			return apply_filters('WPML_get_translatable_item', $item, $post_id);
		} else {
			return get_post($post_id);
		}
	}

	/* TRANSLATION JOBS */
	/* ******************************************************************************************** */

	function send_jobs($data){
		global $wpdb, $sitepress;

		if(!isset($data['tr_action']) && isset($data['translate_to'])){ //adapt new format
			$data['tr_action'] = $data['translate_to'];
			unset($data['translate_to']);
		}

		/** @var $translate_from string */
		// tr_action (translate_to)
		// translator
		// post
		// service
		// defaults
		$data_default = array(
			'translate_from'    => $sitepress->get_default_language()
		);
		extract($data_default);
		extract($data, EXTR_OVERWRITE);

		// no language selected ?
		if(!isset($tr_action) || empty($tr_action)){
			$this->messages[] = array(
				'type'=>'error',
				'text' => __('Please select at least one language to translate into.', 'sitepress')
			);
			$this->dashboard_select = $data; // prepopulate dashboard
			return false;
		}
		// no post selected ?
		if(!isset($iclpost) || empty($iclpost)){
			$this->messages[] = array(
				'type'=>'error',
				'text' => __('Please select at least one document to translate.', 'sitepress')
			);
			$this->dashboard_select = $data; // pre-populate dashboard
			return false;
		}

		$selected_posts = $iclpost;
		$selected_translators = isset($translator) ? $translator : array();
		$selected_languages = $tr_action;
		$job_ids = array();

		foreach($selected_posts as $post_id){
			$post = $this->_get_post($post_id);
			$post_trid = $sitepress->get_element_trid($post->ID, 'post_' . $post->post_type);
			$post_translations = $sitepress->get_element_translations($post_trid, 'post_' . $post->post_type);
			$md5 = $this->post_md5($post);

			$translation_package = $this->create_translation_package($post);

			foreach($selected_languages as $lang => $action){

				// making this a duplicate?
				if($action == 2){
					// dont send documents that are in progress
					$current_translation_status = $this->get_element_translation($post_id, $lang, 'post_' . $post->post_type);
					if($current_translation_status && $current_translation_status->status == ICL_TM_IN_PROGRESS) continue;

					$job_ids[] = $this->make_duplicate($post_id, $lang);
				}elseif($action == 1){

					if(empty($post_translations[$lang])){
											$translation_id = $sitepress->set_element_language_details(null , 'post_' . $post->post_type, $post_trid, $lang, $translate_from);
					}else{
						$translation_id = $post_translations[$lang]->translation_id;
					}

					$current_translation_status = $this->get_element_translation($post_id, $lang, 'post_' . $post->post_type);

					// don't send documents that are in progress
					// don't send documents that are already translated and don't need update
										// don't send documents that are waiting for translator
					if(!empty($current_translation_status)){
						if($current_translation_status->status == ICL_TM_IN_PROGRESS) continue;
						if($current_translation_status->status == ICL_TM_COMPLETE && !$current_translation_status->needs_update) continue;
												if($current_translation_status->status == ICL_TM_WAITING_FOR_TRANSLATOR) continue;
					}

					$_status = ICL_TM_WAITING_FOR_TRANSLATOR;

					$_exp = isset($selected_translators[$lang]) ? explode('-', $selected_translators[$lang]) : false;
					if(!isset($service)){
						$translation_service = isset($_exp[1]) ? $_exp[1] : 'local';
					}else{
						$translation_service = $service;
					}
					$translator_id = $_exp[0];

					// set as default translator
					if($translator_id > 0){
						$this->set_default_translator($translator_id, $translate_from, $lang, $translation_service);
					}

					// add translation_status record
					$data = array(
						'translation_id'        => $translation_id,
						'status'                => $_status,
						'translator_id'         => $translator_id,
						'needs_update'          => 0,
						'md5'                   => $md5,
						'translation_service'   => $translation_service,
						'translation_package'   => serialize($translation_package)
					);

					$_prevstate = $wpdb->get_row($wpdb->prepare("
						SELECT status, translator_id, needs_update, md5, translation_service, translation_package, timestamp, links_fixed
						FROM {$wpdb->prefix}icl_translation_status
						WHERE translation_id = %d
					", $translation_id), ARRAY_A);
					if(!empty($_prevstate)){
						$data['_prevstate'] = serialize($_prevstate);
					}

					list($rid, $update) = $this->update_translation_status($data);

					$job_ids[] = $this->add_translation_job($rid, $translator_id, $translation_package);
					if( $translation_service == 'icanlocalize' ){
						global $ICL_Pro_Translation;
						$sent = $ICL_Pro_Translation->send_post($post, array($lang), $translator_id);
						if(!$sent){
							$job_id = array_pop($job_ids);
							$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $job_id));
							$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}icl_translate_job SET revision = NULL WHERE rid=%d ORDER BY job_id DESC LIMIT 1", $rid));
							$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id=%d", $job_id));
						}
					}
				} // if / else is making a duplicate
			}

		}

		$job_ids = array_unique($job_ids);
		if(array(false) == $job_ids || empty($job_ids)){
			$this->messages[] = array(
				'type'=>'error',
				'text' => __('No documents were sent to translation. Make sure that translations are not currently in progress or already translated for the selected language(s).', 'sitepress')
			);
		}elseif(in_array(false, $job_ids)){
			$this->messages[] = array(
				'type'=>'updated',
				'text' => __('Some documents were sent to translation.', 'sitepress')
			);
			$this->messages[] = array(
				'type'=>'error',
				'text' => __('Some documents were <i>not</i> sent to translation. Make sure that translations are not currently in progress for the selected language(s).', 'sitepress')
			);
		}else{
			$this->messages[] = array(
				'type'=>'updated',
				'text' => __('Selected document(s) sent to translation.', 'sitepress')
			);
		}

		return $job_ids;
	}

	/**
	 * Adds a translation job record in icl_translate_job
	 *
	 * @param mixed $rid
	 * @param mixed $translator_id
	 * @param       $translation_package
	 *
	 * @return bool|int
	 */
	function add_translation_job($rid, $translator_id, $translation_package){
		global $wpdb, $current_user;
		get_currentuserinfo();
		if(!$current_user->ID){
			$manager_id = $wpdb->get_var($wpdb->prepare("SELECT manager_id FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d ORDER BY job_id DESC LIMIT 1", $rid));
		}else{
			$manager_id = $current_user->ID;
		}

		$translation_status = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}icl_translation_status WHERE rid=%d", $rid));

		// if we have a previous job_id for this rid mark it as the top (last) revision
		list($prev_job_id, $prev_job_translated) = $wpdb->get_row($wpdb->prepare("
					SELECT job_id, translated FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d AND revision IS NULL
		", $rid), ARRAY_N);
			if ( !is_null( $prev_job_id ) ) {

				// if previous job is not complete bail out
				if ( !$prev_job_translated ) {
					//trigger_error(sprintf(__('Translation is in progress for job: %s.', 'sitepress'), $prev_job_id), E_USER_NOTICE);
					return false;
				}

				$last_rev = $wpdb->get_var( $wpdb->prepare( "
				SELECT MAX(revision) AS rev FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d AND revision IS NOT NULL
			", $rid ) );
				$wpdb->update( $wpdb->prefix . 'icl_translate_job', array( 'revision' => $last_rev + 1 ), array( 'job_id' => $prev_job_id ) );

				$prev_job = $this->get_translation_job( $prev_job_id );

				if ( isset( $prev_job->original_doc_id ) ) {
					$original_post = get_post( $prev_job->original_doc_id );
					foreach ( $prev_job->elements as $element ) {
						$prev_translation[ $element->field_type ] = $element->field_data_translated;
						switch ( $element->field_type ) {
							case 'title':
								if ( self::decode_field_data( $element->field_data, $element->field_format ) == $original_post->post_title ) {
									//$unchanged[$element->field_type] = $element->field_data_translated;
									$unchanged[ $element->field_type ] = true;
								}
								break;
							case 'body':
								if ( self::decode_field_data( $element->field_data, $element->field_format ) == $original_post->post_content ) {
									//$unchanged[$element->field_type] = $element->field_data_translated;
									$unchanged[ $element->field_type ] = true;
								}
								break;
							case 'excerpt':
								if ( self::decode_field_data( $element->field_data, $element->field_format ) == $original_post->post_excerpt ) {
									//$unchanged[$element->field_type] = $element->field_data_translated;
									$unchanged[ $element->field_type ] = true;
								}
								break;
							case 'tags':
								$terms = get_the_terms( $prev_job->original_doc_id, 'post_tag' );
								$_taxs = array();
								if ( $terms ) {
									foreach ( $terms as $term ) {
										$_taxs[ ] = $term->name;
									}
								}
								if ( $element->field_data == $this->encode_field_data( $_taxs, $element->field_format ) ) {
									//$unchanged['tags'] = $element->field_data_translated;
									$unchanged[ 'tags' ] = true;
								}
								break;
							case 'categories':
								$terms = get_the_terms( $prev_job->original_doc_id, 'category' );
								$_taxs = array();
								if ( $terms ) {
									foreach ( $terms as $term ) {
										$_taxs[ ] = $term->name;
									}
								}
								if ( $element->field_data == $this->encode_field_data( $_taxs, $element->field_format ) ) {
									//$unchanged['categories'] = $element->field_data_translated;
									$unchanged[ 'categories' ] = true;
								}
								break;
							default:
								if ( false !== strpos( $element->field_type, 'field-' ) && !empty( $this->settings[ 'custom_fields_translation' ] ) ) {
									$cf_name = preg_replace( '#^field-#', '', $element->field_type );
									if ( self::decode_field_data( $element->field_data, $element->field_format ) == get_post_meta( $prev_job->original_doc_id, $cf_name, 1 ) ) {
										//$unchanged[$element->field_type] = $element->field_data_translated;
										$unchanged[ $element->field_type ] = true;
									}
								} else {
									// taxonomies
									if ( taxonomy_exists( $element->field_type ) ) {
										$terms = get_the_terms( $prev_job->original_doc_id, $element->field_type );
										$_taxs = array();
										if ( $terms ) {
											foreach ( $terms as $term ) {
												$_taxs[ ] = $term->name;
											}
										}
										if ( $element->field_data == $this->encode_field_data( $_taxs, $element->field_format ) ) {
											//$unchanged[$element->field_type] = $field['data_translated'];
											$unchanged[ $element->field_type ] = true;
										}
									}
								}
						}
					}
				}
			}

		$wpdb->insert($wpdb->prefix . 'icl_translate_job', array(
			'rid' => $rid,
			'translator_id' => $translator_id,
			'translated'    => 0,
			'manager_id'    => $manager_id
		));
		$job_id = $wpdb->insert_id;

		foreach($translation_package['contents'] as $field => $value){
			$job_translate = array(
				'job_id'            => $job_id,
				'content_id'        => 0,
				'field_type'        => $field,
				'field_format'      => isset($value['format'])?$value['format']:'',
				'field_translate'   => $value['translate'],
				'field_data'        => $value['data'],
				'field_data_translated' => isset($prev_translation[$field]) ? $prev_translation[$field] : '',
				'field_finished'    => 0
			);
			if(isset($unchanged[$field])){
				$job_translate['field_finished'] = 1;
			}
			//$job_translate['field_data_translated'] = $unchanged[$field];

			$wpdb->insert($wpdb->prefix . 'icl_translate', $job_translate);
		}

		if($this->settings['doc_translation_method'] == ICL_TM_TMETHOD_EDITOR){ // only send notifications if the translation editor method is on
			if(!defined('ICL_TM_DISABLE_ALL_NOTIFICATIONS') && $translation_status->translation_service=='local'){
				if($this->settings['notification']['new-job'] == ICL_TM_NOTIFICATION_IMMEDIATELY){
					require_once ICL_PLUGIN_PATH . '/inc/translation-management/tm-notification.class.php';
					if($job_id){
						$tn_notification = new TM_Notification();
						if(empty($translator_id)){
							$tn_notification->new_job_any($job_id);
						}else{
							$tn_notification->new_job_translator($job_id, $translator_id);
						}
					}
				}
			}
		}

		return $job_id;

	}

	function assign_translation_job($job_id, $translator_id, $service='local'){
		global $wpdb, $iclTranslationManagement;

		// make sure TM is running
		if(empty($this->settings)){
			$iclTranslationManagement->init();
		}

		list($prev_translator_id, $rid) = $wpdb->get_row($wpdb->prepare("SELECT translator_id, rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $job_id), ARRAY_N);

		require_once ICL_PLUGIN_PATH . '/inc/translation-management/tm-notification.class.php';
		$tn_notification = new TM_Notification();
		if($this->settings['notification']['resigned'] == ICL_TM_NOTIFICATION_IMMEDIATELY){
			if(!empty($prev_translator_id) && $prev_translator_id != $translator_id){
				if($job_id){
					$tn_notification->translator_removed($prev_translator_id, $job_id);
				}
			}
		}

		if($this->settings['notification']['new-job'] == ICL_TM_NOTIFICATION_IMMEDIATELY){
			if(empty($translator_id)){
				$tn_notification->new_job_any($job_id);
			}else{
				$tn_notification->new_job_translator($job_id, $translator_id);
			}
		}

		$wpdb->update($wpdb->prefix.'icl_translation_status',
			array('translator_id'=>$translator_id, 'status'=>ICL_TM_WAITING_FOR_TRANSLATOR, 'translation_service' => $service),
			array('rid'=>$rid));
		$wpdb->update($wpdb->prefix.'icl_translate_job', array('translator_id'=>$translator_id), array('job_id'=>$job_id));
		return true;
	}

	function get_translation_jobs($args = array()){
		global $wpdb, $sitepress, $wp_query;

		// defaults
		/** @var $translator_id int */
		/** @var $status int */
		/** @var $include_unassigned bool */
		/** @var $orderby bool|string */
		/** @var $limit_no int */
		$args_default = array(
			'translator_id' => 0,
			'status' => false,
			'include_unassigned' => false
		);

		extract($args_default);
		extract($args, EXTR_OVERWRITE);

		$language_pairs = array();

		$_exp = explode('-', $translator_id);
		$service = isset($_exp[1]) ? $_exp[1] : 'local';
		$translator_id = $_exp[0];

		$where = " s.status > " . ICL_TM_NOT_TRANSLATED;
		if($status != ''){
			$where .= " AND s.status=" . intval($status);
		}
		if($status != ICL_TM_DUPLICATE){
			$where .= " AND s.status <> " . ICL_TM_DUPLICATE;
		}
		if(!empty($translator_id)){
			if($include_unassigned){
				$where .= " AND (j.translator_id=" . intval($translator_id) . " OR j.translator_id=0) ";
			}else{
				$where .= " AND j.translator_id=" . intval($translator_id);
			}
			if(!empty($service)){
				$where .= " AND s.translation_service='{$service}'";
			}

			$language_pairs = get_user_meta($translator_id, $wpdb->prefix.'language_pairs', true);
		}

		// HANDLE FROM
		if(!empty($from)){
			$where .= PHP_EOL . " AND t.source_language_code='".esc_sql($from)."'";
		}else{
			// only if we filter by translator, make sure to use just the 'from' languages that apply
			// in no translator_id, ommit condition and all will be pulled
			if($translator_id){
				if(!empty($to)){
					// get 'from' languages corresdonding to $to (to $translator_id)
					$from_languages = array();
					foreach($language_pairs as $fl => $tls){
						if(isset($tls[$to])) $from_languages[] = $fl;
					}
					if($from_languages){
						$where .= PHP_EOL . sprintf(" AND t.source_language_code IN(%s)", "'" . join("','", $from_languages) . "'");
					}
				}else{
					// all to all case
					// get all possible combinations for $translator_id
					$from_languages = array_keys($language_pairs);
					$where_conditions = array();
					foreach($from_languages as $fl){
						$to_languages = "'" . join("','", array_keys($language_pairs[$fl])) . "'";
						$where_conditions[] = sprintf(" (t.source_language_code='%s' AND t.language_code IN (%s)) ", $fl, $to_languages);
					}
					if(!empty($where_conditions)){
						$where .= PHP_EOL . ' AND ( ' . join (' OR ', $where_conditions) . ')';
					}
				}
			}

		}

		// HANDLE TO
		if(!empty($to)){
			$where .= PHP_EOL . " AND t.language_code='".esc_sql($to)."'";
		}else{
			// only if we filter by translator, make sure to use just the 'from' languages that apply
			// in no translator_id, omit condition and all will be pulled
			if($translator_id){
				if(!empty($from)){
					// get languages the user can translate into from $from
					$tos = isset($language_pairs[$from]) ? array_keys($language_pairs[$from]) : array();
					if($tos){
						$where .= PHP_EOL . sprintf(" AND t.language_code IN(%s)", "'" . join("','", $tos) . "'");
					}
				}else{
					// covered by 'all to all case' above
				}
			}
		}

		// ORDER BY
		if($include_unassigned){
			$orderby[] = 'j.translator_id DESC';
		}
		$orderby[] = ' j.job_id DESC ';
		$orderby = join(', ', $orderby);

		// LIMIT
		if(!isset($_GET['paged'])) $_GET['paged'] = 1;
		$offset = ($_GET['paged']-1)*$limit_no;
		$limit = " " . $offset . ',' . $limit_no;

		$jobs = $wpdb->get_results(
			"SELECT SQL_CALC_FOUND_ROWS
				j.job_id, t.trid, t.language_code, t.source_language_code,
				s.translation_id, s.status, s.needs_update, s.translator_id, u.display_name AS translator_name, s.translation_service
				FROM {$wpdb->prefix}icl_translate_job j
					JOIN {$wpdb->prefix}icl_translation_status s ON j.rid = s.rid
					JOIN {$wpdb->prefix}icl_translations t ON s.translation_id = t.translation_id
					LEFT JOIN {$wpdb->users} u ON s.translator_id = u.ID
				WHERE {$where} AND revision IS NULL
				ORDER BY {$orderby}
				LIMIT {$limit}
			"
		);


		//echo '<pre>';
		//print_r($wpdb->last_query);
		//echo '</pre>';

		$count = $wpdb->get_var("SELECT FOUND_ROWS()");

		$wp_query->found_posts = $count;
		$wp_query->query_vars['posts_per_page'] = $limit_no;
		$wp_query->max_num_pages = ceil($wp_query->found_posts/$limit_no);

		foreach($jobs as $k=>$row){
			//original
			$post_id = $wpdb->get_var($wpdb->prepare("
													 SELECT field_data
													 FROM {$wpdb->prefix}icl_translate
													 WHERE job_id=%d and field_type='original_id'", $row->job_id));

			$parts = explode('_', $post_id);
			if ($parts[0] == 'external') {
				$jobs[$k]->original_doc_id = $post_id;

				$jobs[$k]->post_title = base64_decode($wpdb->get_var($wpdb->prepare("
														 SELECT field_data
														 FROM {$wpdb->prefix}icl_translate
														 WHERE job_id=%d and field_type='name'", $row->job_id)));
				if ($jobs[$k]->post_title == "") {
					// try the title field.
					$jobs[$k]->post_title = base64_decode($wpdb->get_var($wpdb->prepare("
														 SELECT field_data
														 FROM {$wpdb->prefix}icl_translate
														 WHERE job_id=%d and field_type='title'", $row->job_id)));
				}
				
				$jobs[$k]->post_title = apply_filters('WPML_translation_job_title', $jobs[$k]->post_title, $post_id);

				$jobs[$k]->edit_link = self::tm_post_link($post_id);
				$ldf = $sitepress->get_language_details($row->source_language_code);
			} else {
				$doc = $wpdb->get_row($wpdb->prepare("SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE ID=%d", $post_id));
				if ($doc) {
					$jobs[$k]->post_title = $doc->post_title;
					$jobs[$k]->original_doc_id = $doc->ID;
					$jobs[$k]->edit_link = get_edit_post_link($doc->ID);
					$ldf = $sitepress->get_language_details($sitepress->get_element_language_details($post_id, 'post_' . $doc->post_type)->language_code);
				} else {
					$jobs[$k]->post_title = __("The original has been deleted!", "sitepress");
					$jobs[$k]->original_doc_id = 0;
					$jobs[$k]->edit_link = "";
					$ldf['display_name'] = __("Deleted", "sitepress");
				}
			}
			$ldt = $sitepress->get_language_details($row->language_code);
			$jobs[$k]->lang_text = $ldf['display_name'] . ' &raquo; ' . $ldt['display_name'];
			if($row->translation_service=='icanlocalize'){
				$row->translator_name = ICL_Pro_Translation::get_translator_name($row->translator_id);
			}
		}
	   return $jobs;

	}

	function get_translation_job($job_id, $include_non_translatable_elements = false, $auto_assign = false, $revisions = 0){
		global $wpdb, $sitepress, $current_user;
		get_currentuserinfo();
		$job = $wpdb->get_row($wpdb->prepare("
			SELECT
				j.rid, j.translator_id, j.translated, j.manager_id,
				s.status, s.needs_update, s.translation_service,
				t.trid, t.language_code, t.source_language_code
			FROM {$wpdb->prefix}icl_translate_job j
				JOIN {$wpdb->prefix}icl_translation_status s ON j.rid = s.rid
				JOIN {$wpdb->prefix}icl_translations t ON s.translation_id = t.translation_id
			WHERE j.job_id = %d", $job_id));
								
				if (!$job) {
					return false;
				}

		$post_id = $wpdb->get_var($wpdb->prepare("
												 SELECT field_data
												 FROM {$wpdb->prefix}icl_translate
												 WHERE job_id=%d and field_type='original_id'", $job_id));

		$parts = explode('_', $post_id);
		if ($parts[0] == 'external') {
			$job->original_doc_id = $post_id;
			$job->original_doc_title = base64_decode($wpdb->get_var($wpdb->prepare("
													 SELECT field_data
													 FROM {$wpdb->prefix}icl_translate
													 WHERE job_id=%d and field_type='name'", $job_id)));
			if ($job->original_doc_title == "") {
				// try the title field.
				$job->original_doc_title = base64_decode($wpdb->get_var($wpdb->prepare("
														 SELECT field_data
														 FROM {$wpdb->prefix}icl_translate
														 WHERE job_id=%d and field_type='title'", $job_id)));
			}
			$job->original_post_type = $wpdb->get_var($wpdb->prepare("
																	 SELECT element_type
																	 FROM {$wpdb->prefix}icl_translations
																	 WHERE trid=%d AND language_code=%s",
																	 $job->trid, $job->source_language_code));
		} else {

			$original = $wpdb->get_row($wpdb->prepare("
				SELECT t.element_id, p.post_title, p.post_type
				FROM {$wpdb->prefix}icl_translations t
				JOIN {$wpdb->posts} p ON t.element_id = p.ID AND t.trid = %d
				WHERE t.source_language_code IS NULL", $job->trid));

			if($original){
				$job->original_doc_title = $original->post_title;
				$job->original_doc_id = $original->element_id;
				$job->original_post_type = $original->post_type;
			}
		}

		$_ld = $sitepress->get_language_details($job->source_language_code);
		$job->from_language = $_ld['display_name'];
		$_ld = $sitepress->get_language_details($job->language_code);
		$job->to_language = $_ld['display_name'];

		if(!$include_non_translatable_elements){
			$jelq = ' AND field_translate = 1';
		}else{
			$jelq = '';
		}
		$job->elements = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}icl_translate WHERE job_id = %d {$jelq} ORDER BY tid ASC", $job_id));
		if($job->translator_id == 0 || $job->status == ICL_TM_WAITING_FOR_TRANSLATOR){
			if($auto_assign){
				$wpdb->update($wpdb->prefix . 'icl_translate_job', array('translator_id' => $this->current_translator->translator_id), array('job_id'=>$job_id));
				$wpdb->update($wpdb->prefix . 'icl_translation_status',
					array('translator_id' => $this->current_translator->translator_id, 'status' => ICL_TM_IN_PROGRESS),
					array('rid'=>$job->rid)
				);
			}
		} elseif ( $job->translator_id != @intval( $this->current_translator->translator_id ) && !defined( 'XMLRPC_REQUEST' ) && $job->manager_id != $current_user->ID ) {
			// Returns the job for admin users
			if ( $this->is_translator($current_user->ID) ) {
				return $job;
			} else {
				static $erroronce = array();
				if ( empty( $erroronce[ $job_id ] ) ) {
					$this->messages[ ]    = array(
						'type' => 'error',
						'text' => sprintf( __( "You can't translate this document. It's assigned to a different translator.<br /> Document: <strong>%s</strong> (ID = %d).", 'sitepress' ), $job->original_doc_title, $job_id )
					);
					$erroronce[ $job_id ] = true;
				}

				return false;
			}
		}

		//do we have a previous version
		if($revisions > 0){
			$prev_version_job_id = $wpdb->get_var($wpdb->prepare("
				SELECT MAX(job_id)
				FROM {$wpdb->prefix}icl_translate_job
				WHERE rid=%d AND job_id < %d", $job->rid, $job_id));
			if($prev_version_job_id){
				$job->prev_version = $this->get_translation_job($prev_version_job_id, false, false, $revisions - 1);
			}

		}

		// allow adding custom elements
		$job->elements = apply_filters('icl_job_elements', $job->elements, $post_id, $job_id);

		return $job;
	}

	function get_translation_job_id($trid, $language_code){
		global $wpdb, $sitepress;

		$job_id = $wpdb->get_var($wpdb->prepare("
			SELECT tj.job_id FROM {$wpdb->prefix}icl_translate_job tj
				JOIN {$wpdb->prefix}icl_translation_status ts ON tj.rid = ts.rid
				JOIN {$wpdb->prefix}icl_translations t ON ts.translation_id = t.translation_id
				WHERE t.trid = %d AND t.language_code='%s'
				ORDER BY tj.job_id DESC LIMIT 1
		", $trid, $language_code));

		return $job_id;
	}

	function _save_translation_field($tid, $field){
		global $wpdb;
		$update['field_data_translated'] = $this->encode_field_data($field['data'], $field['format']);
		if(isset($field['finished']) && $field['finished']){
			$update['field_finished'] = 1;
		}
		$wpdb->update($wpdb->prefix . 'icl_translate', $update, array('tid'=>$tid));
	}

	function save_translation($data){
		global $wpdb, $sitepress, $sitepress_settings, $ICL_Pro_Translation;

				$new_post_id = false;
		$is_incomplete = false;
		foreach($data['fields'] as $field){
			$this->_save_translation_field($field['tid'], $field);
			if(!isset($field['finished']) || !$field['finished']){
				$is_incomplete = true;
			}
		}

		//check if translation job still exists
		$job_count = $wpdb->get_var( $wpdb->prepare( "SELECT count(1) FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $data[ 'job_id' ] ) );
		if ( $job_count == 0 ) {
			wp_redirect( admin_url( sprintf( 'admin.php?page=%s', WPML_TM_FOLDER . '/menu/translations-queue.php', 'job-cancelled' ) ) );
			exit;
		}

		if(!empty($data['complete']) && !$is_incomplete){
			$wpdb->update($wpdb->prefix . 'icl_translate_job', array('translated'=>1), array('job_id'=>$data['job_id']));
			$rid = $wpdb->get_var($wpdb->prepare("SELECT rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $data['job_id']));
			$translation_id = $wpdb->get_var($wpdb->prepare("SELECT translation_id FROM {$wpdb->prefix}icl_translation_status WHERE rid=%d", $rid));
			$wpdb->update($wpdb->prefix . 'icl_translation_status', array('status'=>ICL_TM_COMPLETE, 'needs_update'=>0), array('rid'=>$rid));
			list($element_id, $trid) = $wpdb->get_row($wpdb->prepare("SELECT element_id, trid FROM {$wpdb->prefix}icl_translations WHERE translation_id=%d", $translation_id), ARRAY_N);
			$job = $this->get_translation_job($data['job_id'], true);

			$parts = explode('_', $job->original_doc_id);
			if ($parts[0] == 'external') {

				// Translations are saved in the string table for 'external' types

				$id = array_pop($parts);
				unset($parts[0]);
				$type = implode('_', $parts);
				$type = apply_filters('WPML_get_package_type', $type, $job->original_doc_id);

				foreach($job->elements as $field){
					if ($field->field_translate) {
						if (function_exists('icl_st_is_registered_string')) {
							$string_id = icl_st_is_registered_string($type, $id . '_' . $field->field_type);
							if (!$string_id) {
								icl_register_string($type, $id . '_' . $field->field_type, self::decode_field_data($field->field_data, $field->field_format));
								$string_id = icl_st_is_registered_string($type, $id . '_' . $field->field_type);
							}
							if ($string_id) {
								icl_add_string_translation($string_id, $job->language_code, self::decode_field_data($field->field_data_translated, $field->field_format), ICL_STRING_TRANSLATION_COMPLETE);
							}
						}
					}
				}

			} else {

				if(!is_null($element_id)){
					$postarr['ID'] = $_POST['post_ID'] = $element_id;
				}

				foreach($job->elements as $field){
					switch($field->field_type){
						case 'title':
							$postarr['post_title'] = self::decode_field_data($field->field_data_translated, $field->field_format);
							break;
						case 'body':
							$postarr['post_content'] = self::decode_field_data($field->field_data_translated, $field->field_format);
							break;
						case 'excerpt':
							$postarr['post_excerpt'] = self::decode_field_data($field->field_data_translated, $field->field_format);
							break;
						case 'URL':
							$postarr['post_name'] = self::decode_field_data($field->field_data_translated, $field->field_format);
							break;
						default:
							break;
					}
				}

				$original_post = get_post($job->original_doc_id);

				$postarr['post_author'] = $original_post->post_author;
				$postarr['post_type'] = $original_post->post_type;

				if($sitepress_settings['sync_comment_status']){
					$postarr['comment_status'] = $original_post->comment_status;
				}
				if($sitepress_settings['sync_ping_status']){
					$postarr['ping_status'] = $original_post->ping_status;
				}
				if($sitepress_settings['sync_page_ordering']){
					$postarr['menu_order'] = $original_post->menu_order;
				}
				if($sitepress_settings['sync_private_flag'] && $original_post->post_status=='private'){
					$postarr['post_status'] = 'private';
				}
				if($sitepress_settings['sync_post_date']){
					$postarr['post_date'] = $original_post->post_date;
				}

				//set as draft or the same status as original post
				$postarr['post_status'] = !$sitepress_settings['translated_document_status'] ? 'draft' : $original_post->post_status;

				if($original_post->post_parent){
					$post_parent_trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations
						WHERE element_type='post_{$original_post->post_type}' AND element_id='{$original_post->post_parent}'");
					if($post_parent_trid){
						$parent_id = $wpdb->get_var("SELECT element_id FROM {$wpdb->prefix}icl_translations
							WHERE element_type='post_{$original_post->post_type}' AND trid='{$post_parent_trid}' AND language_code='{$job->language_code}'");
					}
				}

				if(isset($parent_id) && $sitepress_settings['sync_page_parent']){
					$_POST['post_parent'] = $postarr['post_parent'] = $parent_id;
					$_POST['parent_id'] = $postarr['parent_id'] = $parent_id;
				}

				$_POST['trid'] = $trid;
				$_POST['lang'] = $job->language_code;
				$_POST['skip_sitepress_actions'] = true;

				$postarr = apply_filters('icl_pre_save_pro_translation', $postarr);

				if(isset($element_id)){ // it's an update so dont change the url
					$postarr['post_name'] = $wpdb->get_var($wpdb->prepare("SELECT post_name FROM {$wpdb->posts} WHERE ID=%d", $element_id));
				}

				if(isset($element_id)){ // it's an update so dont change post date
					$existing_post = get_post($element_id);
					$postarr['post_date'] = $existing_post->post_date;
					$postarr['post_date_gmt'] = $existing_post->post_date_gmt;
				}

				$new_post_id = $this->icl_insert_post( $postarr, $job->language_code );
				icl_cache_clear( $postarr['post_type'] . 's_per_language' ); // clear post counter per language in cache

				do_action('icl_pro_translation_saved', $new_post_id, $data['fields']);


				if (!isset($postarr['post_name']) || empty($postarr['post_name'])) {
					// Allow identical slugs
					$post_name = sanitize_title($postarr['post_title']);

					// for Translated documents options:Page URL = Translate
									if(isset($data['fields']['URL']['data']) && $data['fields']['URL']['data']){
											$post_name = $data['fields']['URL']['data'];
									}

					$post_name_rewritten = $wpdb->get_var($wpdb->prepare("SELECT post_name FROM {$wpdb->posts} WHERE ID=%d", $new_post_id));

					$post_name_base = $post_name;

					if ( $post_name != $post_name_rewritten || $postarr[ 'post_type' ] == 'post' || $postarr[ 'post_type' ] == 'page' ) {
						$incr = 1;
						do{

							$exists = $wpdb->get_var($wpdb->prepare("
								SELECT p.ID FROM {$wpdb->posts} p
									JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID
								WHERE p.ID <> %d AND t.language_code = %s AND p.post_name=%s
							",  $new_post_id, $job->language_code, $post_name));

							if($exists){
								$incr++;
							}else{
								break;
							}
							$post_name = $post_name_base . '-' . $incr;

						}while($exists);

						$wpdb->update($wpdb->posts, array('post_name' => $post_name), array('ID' => $new_post_id));
					}
				}
				
				$ICL_Pro_Translation->_content_fix_links_to_translated_content($new_post_id, $job->language_code);

				// update body translation with the links fixed
				$new_post_content = $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $new_post_id));
				foreach($job->elements as $jel){
					if($jel->field_type=='body'){
						$fields_data_translated = $this->encode_field_data($new_post_content, $jel->field_format);
						break;
					}
				}
				$wpdb->update($wpdb->prefix.'icl_translate', array('field_data_translated'=>$fields_data_translated), array('job_id'=>$data['job_id'], 'field_type'=>'body'));


				// set stickiness
				//is the original post a sticky post?
				remove_filter('option_sticky_posts', array($sitepress,'option_sticky_posts')); // remove filter used to get language relevant stickies. get them all
				$sticky_posts = get_option('sticky_posts');
				$is_original_sticky = $original_post->post_type=='post' && in_array($original_post->ID, $sticky_posts);

				if($is_original_sticky && $sitepress_settings['sync_sticky_flag']){
					stick_post($new_post_id);
				}else{
					if($original_post->post_type=='post' && !is_null($element_id)){
						unstick_post($new_post_id); //just in case - if this is an update and the original post stckiness has changed since the post was sent to translation
					}
				}

				//sync plugins texts
				foreach((array)$this->settings['custom_fields_translation'] as $cf => $op){
					if ($op == 1) {
						update_post_meta($new_post_id, $cf, get_post_meta($original_post->ID,$cf,true));
					}
				}

				// set specific custom fields
				$copied_custom_fields = array('_top_nav_excluded', '_cms_nav_minihome');
				foreach($copied_custom_fields as $ccf){
					$val = get_post_meta($original_post->ID, $ccf, true);
					update_post_meta($new_post_id, $ccf, $val);
				}

				// sync _wp_page_template
				if($sitepress_settings['sync_page_template']){
					$_wp_page_template = get_post_meta($original_post->ID, '_wp_page_template', true);
					if(!empty($_wp_page_template)){
						update_post_meta($new_post_id, '_wp_page_template', $_wp_page_template);
					}
				}

								// sync post format
								if ( $sitepress_settings[ 'sync_post_format' ] ) {
									$_wp_post_format = get_post_format( $original_post->ID );
									set_post_format( $new_post_id, $_wp_post_format );
								}


				// set the translated custom fields if we have any.
				foreach((array)$this->settings['custom_fields_translation'] as $field_name => $val){
					if ($val == 2) { // should be translated
						// find it in the translation
						foreach($job->elements as $name => $eldata) {
							if ($eldata->field_data == $field_name) {
								if (preg_match("/field-(.*?)-name/", $eldata->field_type, $match)) {
									$field_id = $match[1];
									foreach($job->elements as $k => $v){
										if($v->field_type=='field-'.$field_id){
											$field_translation = self::decode_field_data($v->field_data_translated, $v->field_format) ;
										}
										if($v->field_type=='field-'.$field_id.'-type'){
											$field_type = $v->field_data;
										}
									}
									if (isset($field_type) && $field_type == 'custom_field') {
										$field_translation = str_replace ( '&#0A;', "\n", $field_translation );
										// always decode html entities  eg decode &amp; to &
										$field_translation = html_entity_decode($field_translation);
										update_post_meta($new_post_id, $field_name, $field_translation);
									}
								}
							}
						}
					}
				}
				$link = get_edit_post_link($new_post_id);
				if ($link == '') {
					// the current user can't edit so just include permalink
					$link = get_permalink($new_post_id);
				}
				if(is_null($element_id)){
					$wpdb->delete ( $wpdb->prefix . 'icl_translations', array( 'element_id' => $new_post_id, 'element_type' => 'post_' . $postarr[ 'post_type' ] ) );
					$wpdb->update ( $wpdb->prefix . 'icl_translations', array( 'element_id' => $new_post_id), array('translation_id' => $translation_id) );
					$user_message = __('Translation added: ', 'sitepress') . '<a href="'.$link.'">' . $postarr['post_title'] . '</a>.';
				}else{
					$user_message = __('Translation updated: ', 'sitepress') . '<a href="'.$link.'">' . $postarr['post_title'] . '</a>.';
				}

				// synchronize the page parent for translations
				if ($trid && $sitepress_settings['sync_page_parent']) {
					$translations = $sitepress->get_element_translations($trid, 'post_' . $postarr['post_type']);

					foreach ($translations as $target_lang => $target_details) {
						if ($target_lang != $job->language_code) {
							if ($target_details->element_id) {
								$sitepress->fix_translated_parent($new_post_id, $target_details->element_id, $target_lang);
							}
						}
					}
				}
			}

						if(isset($user_message)) {
							$this->messages[] = array(
									'type'=>'updated',
									'text' => $user_message
							);
						}

			if($this->settings['notification']['completed'] != ICL_TM_NOTIFICATION_NONE){
				require_once ICL_PLUGIN_PATH . '/inc/translation-management/tm-notification.class.php';
				if($data['job_id']){
					$tn_notification = new TM_Notification();
					$tn_notification->work_complete($data['job_id'], !is_null($element_id));
				}
			}

			self::set_page_url($new_post_id);

			// redirect to jobs list
			wp_redirect(admin_url(sprintf('admin.php?page=%s&%s=%d',
				WPML_TM_FOLDER . '/menu/translations-queue.php', is_null($element_id) ? 'added' : 'updated', is_null($element_id) ? $new_post_id : $element_id )));

		}else{
			$this->messages[] = array('type'=>'updated', 'text' => __('Translation (incomplete) saved.', 'sitepress'));
		}

		/*
		 * After all previous functionality the terms form the job are assigned to the new post just created or updated.
		 * $overwrite is true by default for now.
		 */
		$overwrite = true;
		WPML_Terms_Translations::save_all_terms_from_job( $data[ 'job_id' ], $new_post_id, $overwrite );

		// Set the posts mime type correctly.
 
		if ( isset( $original_post ) && isset( $original_post->ID ) && $original_post->post_type == 'attachment' ) {
			$attached_file = get_post_meta ( $original_post->ID, '_wp_attached_file', false );
			update_post_meta ( $new_post_id, '_wp_attached_file', array_pop ( $attached_file ) );
			$mime_type = get_post_mime_type ( $original_post->ID );
			if ( $mime_type ) {
				$wpdb->update ( $wpdb->posts, array( 'post_mime_type' => $mime_type ), array( 'ID' => $new_post_id ) );
			}
		}
		
		do_action('icl_pro_translation_completed', $new_post_id);

	}

	// returns a front end link to a post according to the user access
	// hide_empty - if current user doesn't have access to the link don't show at all
	public static function tm_post_link($post_id, $anchor = false, $hide_empty = false){
		global $current_user;
		get_currentuserinfo();

		$parts = explode('_', $post_id);
		if ($parts[0] == 'external') {

			$link = '';
			return apply_filters('WPML_get_link', $link, $post_id, $anchor, $hide_empty);
		}

		if(false === $anchor){
			$anchor = get_the_title($post_id);
		}

		$opost = get_post($post_id);
		if(!$opost || ($opost->post_status == 'draft' || $opost->post_status == 'private' || $opost->post_status == 'trash') && $opost->post_author != $current_user->data->ID){
			if($hide_empty){
				$elink = '';
			}else{
				$elink = sprintf('<i>%s</i>', $anchor);
			}
		}else{
			$elink = sprintf('<a href="%s">%s</a>', get_permalink($post_id), $anchor);
		}

		return $elink;

	}

	public function tm_post_permalink($post_id){
		global $current_user;
		get_currentuserinfo();

		$parts = explode('_', $post_id);
		if ($parts[0] == 'external') {

			return '';
		}

		$opost = get_post($post_id);
		if(!$opost || ($opost->post_status == 'draft' || $opost->post_status == 'private' || $opost->post_status == 'trash') && $opost->post_author != $current_user->data->ID){
			$elink = '';
		}else{
			$elink = get_permalink($post_id);
		}

		return $elink;

	}

	// when the translated post was created, we have the job_id and need to update the job
	function save_job_fields_from_post($job_id, $post){
		global $wpdb, $sitepress;
		$data['complete'] = 1;
		$data['job_id'] = $job_id;
		$job = $this->get_translation_job($job_id,1);

		if(is_array($job->elements))
		foreach($job->elements as $element){
			$field_data = '';
			switch($element->field_type){
				case 'title':
					$field_data = $this->encode_field_data($post->post_title, $element->field_format);
					break;
				case 'body':
					$field_data = $this->encode_field_data($post->post_content, $element->field_format);
					break;
				case 'excerpt':
					$field_data = $this->encode_field_data($post->post_excerpt, $element->field_format);
					break;
				default:
					if(false !== strpos($element->field_type, 'field-') && !empty($this->settings['custom_fields_translation'])){
						$cf_name = preg_replace('#^field-#', '', $element->field_type);
						if(isset($this->settings['custom_fields_translation'][$cf_name])){
							if($this->settings['custom_fields_translation'][$cf_name] == 1){ //copy
								// @todo: Check when this code is run, it seems obsolete
								$field_data = get_post_meta($job->original_doc_id, $cf_name, 1);
								if(is_scalar($field_data))
								$field_data = $this->encode_field_data($field_data, $element->field_format);
								else $field_data = '';
							}elseif($this->settings['custom_fields_translation'][$cf_name] == 2){ // translate
								$field_data = get_post_meta($post->ID, $cf_name, 1);
								if(is_scalar($field_data))
								$field_data = $this->encode_field_data($field_data, $element->field_format);
								else $field_data = '';
							}
						}
					}else{
						if(in_array($element->field_type, $sitepress->get_translatable_taxonomies(true, $post->post_type))){
							$ids = array();
							foreach($job->elements as $je){
								if($je->field_type == $element->field_type .'_ids' ){
									$ids = explode(',', $je->field_data);
								}
							}
							$translated_tax_names = array();
							foreach($ids as $id){
								$translated_tax_id = icl_object_id($id, $element->field_type,false,$job->language_code);
								if($translated_tax_id){
									$translated_tax_names[] = $wpdb->get_var($wpdb->prepare("
										SELECT t.name FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} x ON t.term_id = x.term_id
										WHERE x.term_taxonomy_id = %d
									", $translated_tax_id));
								}
							}
							$field_data = $this->encode_field_data($translated_tax_names, $element->field_format);

						}
					}
			}
			$wpdb->update($wpdb->prefix.'icl_translate',
				array('field_data_translated'=>$field_data, 'field_finished'=>1),
				array('tid'=>$element->tid)
			);

		}

		$this->mark_job_done($job_id);

	}

	public static function determine_translated_taxonomies($elements, $taxonomy, $translated_language){
		global $sitepress, $wpdb;
		$translated_elements = false;
		foreach($elements as $k=>$element){
			$term = get_term_by('name', $element, $taxonomy);
			if ($term) {
				$trid = $sitepress->get_element_trid($term->term_taxonomy_id, 'tax_' . $taxonomy);
				$translations = $sitepress->get_element_translations($trid, 'tax_' . $taxonomy);
				if(isset($translations[$translated_language])){
					$translated_elements[$k] = $translations[$translated_language]->name;
				}else{
					$translated_elements[$k] = '';
				}
			} else {
				$translated_elements[$k] = '';
			}
		}

		return $translated_elements;
	}

	function mark_job_done($job_id){
		global $wpdb;
		$wpdb->update($wpdb->prefix.'icl_translate_job', array('translated'=>1), array('job_id'=>$job_id));
		$wpdb->update($wpdb->prefix.'icl_translate', array('field_finished'=>1), array('job_id'=>$job_id));
	}

	function resign_translator($job_id){
		global $wpdb;
		list($translator_id, $rid) = $wpdb->get_row($wpdb->prepare("SELECT translator_id, rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $job_id), ARRAY_N);

		if(!empty($translator_id)){
			if($this->settings['notification']['resigned'] != ICL_TM_NOTIFICATION_NONE){
				require_once ICL_PLUGIN_PATH . '/inc/translation-management/tm-notification.class.php';
				if($job_id){
					$tn_notification = new TM_Notification();
					$tn_notification->translator_resigned($translator_id, $job_id);
				}
			}
		}

		$wpdb->update($wpdb->prefix.'icl_translate_job', array('translator_id'=>0), array('job_id'=>$job_id));
		$wpdb->update($wpdb->prefix.'icl_translation_status', array('translator_id'=>0, 'status'=>ICL_TM_WAITING_FOR_TRANSLATOR), array('rid'=>$rid));
	}

	function remove_translation_job($job_id, $new_translation_status = ICL_TM_WAITING_FOR_TRANSLATOR, $new_translator_id = 0){
		global $wpdb;

		list($prev_translator_id, $rid) = $wpdb->get_row($wpdb->prepare("SELECT translator_id, rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $job_id), ARRAY_N);

		$wpdb->update($wpdb->prefix . 'icl_translate_job', array('translator_id' => $new_translator_id), array('job_id' => $job_id));
		$wpdb->update($wpdb->prefix . 'icl_translate', array('field_data_translated' => '', 'field_finished' => 0), array('job_id' => $job_id));

		$error = false;

		if($rid){
			$wpdb->update($wpdb->prefix . 'icl_translation_status', array('status' => $new_translation_status, 'translator_id' => $new_translator_id), array('rid' => $rid));

			if($this->settings['notification']['resigned'] == ICL_TM_NOTIFICATION_IMMEDIATELY && !empty($prev_translator_id)){
				require_once ICL_PLUGIN_PATH . '/inc/translation-management/tm-notification.class.php';
				$tn_notification = new TM_Notification();
				$tn_notification->translator_removed($prev_translator_id, $job_id);
				$tn_notification->mail_queue();
			}

		}else{
			$error = sprintf(__('Translation entry not found for: %d', 'wpml-translation-management'), $job_id);
		}

	}

	function abort_translation(){
		global $wpdb;

		$job_id = $_POST['job_id'];
		$error = '';
		$message = '';

		$error = $this->remove_translation_job($job_id, ICL_TM_WAITING_FOR_TRANSLATOR, 0);
		if(!$error){
			$message = __('Job removed', 'wpml-translation-management');
		}

		echo json_encode(array('message' => $message, 'error' => $error));
		exit;

	}

	// $translation_id - int or array
	function cancel_translation_request($translation_id){
		global $wpdb;

		if(is_array($translation_id)){
			foreach($translation_id as $id){
				$this->cancel_translation_request($id);
			}
		}else{

			list($rid, $translator_id) = $wpdb->get_row($wpdb->prepare("SELECT rid, translator_id FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", $translation_id), ARRAY_N);
			$job_id = $wpdb->get_var($wpdb->prepare("SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d AND revision IS NULL ", $rid));

			if($this->settings['notification']['resigned'] == ICL_TM_NOTIFICATION_IMMEDIATELY && !empty($translator_id)){
				require_once ICL_PLUGIN_PATH . '/inc/translation-management/tm-notification.class.php';
				$tn_notification = new TM_Notification();
				$tn_notification->translator_removed($translator_id, $job_id);
				$tn_notification->mail_queue();
			}

			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $job_id));
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id=%d", $job_id));

			$max_job_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(job_id) FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d", $rid));
			if($max_job_id){
				$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}icl_translate_job SET revision = NULL WHERE job_id=%d", $max_job_id));
				$_prevstate = $wpdb->get_var($wpdb->prepare("SELECT _prevstate FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d", $translation_id));
				if(!empty($_prevstate)){
					$_prevstate = unserialize($_prevstate);
					$wpdb->update($wpdb->prefix . 'icl_translation_status',
						array(
							'status'                => $_prevstate['status'],
							'translator_id'         => $_prevstate['translator_id'],
							'needs_update'          => $_prevstate['needs_update'],
							'md5'                   => $_prevstate['md5'],
							'translation_service'   => $_prevstate['translation_service'],
							'translation_package'   => $_prevstate['translation_package'],
							'timestamp'             => $_prevstate['timestamp'],
							'links_fixed'           => $_prevstate['links_fixed']
						),
						array('translation_id'=>$translation_id)
					);
				}
			}else{
				$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", $translation_id));
			}

			// delete record from icl_translations if trid is null
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id=%d AND element_id IS NULL", $translation_id));

		}

	}


	function _array_keys_recursive( $arr )
	{
		$arr_rec_ret = array();
		foreach ( (array)$arr as $k => $v ) {
			if ( is_array( $v ) ) {
				$arr_rec_ret[ $k ] = $this->_array_keys_recursive( $v );
			} else {
				$arr_rec_ret[$k] = $v;
			}
		}

		return $arr_rec_ret;
	}

	function _read_admin_texts_recursive(&$arr, $keys, $config_contexts){
		if(!is_numeric(key($keys))){
			$_keys = array($keys);
			$keys = $_keys;
			unset($_keys);
		}
		if($keys) {
			foreach($keys as $key){
				if(isset($key['key'])){
					$this->_read_admin_texts_recursive($arr, $key['key'], $config_contexts);
				}else{
					if(isset($config_contexts['wpml-config']['admin-texts']['key'][$key[ 'attr' ][ 'name' ]])) {
						$context = $config_contexts['wpml-config']['admin-texts']['key'][$key[ 'attr' ][ 'name' ]]['slug'];
						$arr[$context][$key['attr']['name']] = 1;
					}
				}
			}
		}
		return $arr;
	}

	function _register_string_recursive($key, $value, $arr, $prefix = '', $suffix){
		if(is_scalar($value)){
			if(!empty($value) && $arr == 1){
				icl_register_string('admin_texts_' . $suffix, $prefix . $key , $value);
			}
		}else{
			if(!is_null($value)){
				foreach($value as $sub_key=>$sub_value){
					$is_wildcard = false;
					if($arr && !isset($arr[$sub_key])){ //wildcard
						if ( is_array( $arr ) ) {
							$array_keys = array_keys( $arr );
							if ( is_array( $array_keys ) ) {
								foreach( $array_keys as $array_key){
									$array_key = str_replace('/', '\/',  $array_key);
									$array_key = '/' . str_replace('*', '(.*)', $array_key) . '/';
									if(preg_match($array_key, $sub_key)){
										$is_wildcard = true;
										$arr[$sub_key] = true; //placeholder
										break;
									};
								}
							}
						}
					}

					if(isset($arr[$sub_key]) || $is_wildcard){
						$this->_register_string_recursive($sub_key, $sub_value, $arr[$sub_key], $prefix . '[' . $key .']', $suffix);
					}

				}
			}
		}
	}

	function _read_settings_recursive($config_settings){
		$iclsettings = false;
		foreach($config_settings as $s){
			if(isset($s['key'])){
				if(!is_numeric(key($s['key']))){
					$sub_key[0] = $s['key'];
				}else{
					$sub_key = $s['key'];
				}
				$read_settings_recursive = $this->_read_settings_recursive( $sub_key );
				if($read_settings_recursive) {
					$iclsettings[$s['attr']['name']] = $read_settings_recursive;
				}
			}else{
				$iclsettings[$s['attr']['name']] = $s['value'];
			}
		}
		return $iclsettings;
	}

	function render_option_writes($name, $value, $key=''){
		if(!defined('WPML_ST_FOLDER')) return;
		//Cache the previous option, when called recursively
		static $option = false;

		if(!$key){
			$option = maybe_unserialize(get_option($name));
			if(is_object($option)){
				$option = (array)$option;
			}
		}

		$admin_option_names = get_option('_icl_admin_option_names');

		// determine theme/plugin name (string context)
		$es_context = '';

		foreach($admin_option_names as $context => $element) {
			$found = false;
			foreach ( (array)$element as $slug => $options ) {
				$found = false;
				foreach ( (array)$options as $option_key => $option_value ) {
					$found = false;
					$es_context = '';
					if( $option_key == $name ) {
						if ( is_scalar( $option_value ) ) {
							$es_context = 'admin_texts_' . $context . '_' . $slug;
							$found = true;
						} elseif ( is_array( $option_value ) && is_array( $value ) && ( $option_value == $value ) ) {
							$es_context = 'admin_texts_' . $context . '_' . $slug;
							$found = true;
						}
					}
					if($found) break;
				}
				if($found) break;
			}
			if($found) break;
		}

		echo '<ul class="icl_tm_admin_options">';
		echo '<li>';

		$context_html = '';
		if(!$key){
			$context_html = '[' . $context . ': ' . $slug . '] ';
		}

		if(is_scalar($value)){
			$int = preg_match_all('#\[([^\]]+)\]#', $key, $matches);

			if(count($matches[1]) > 1){
				$o_value = $option;
				for($i = 1; $i < count($matches[1]); $i++){
					$o_value = $o_value[$matches[1][$i]];
				}
				$o_value = $o_value[$name];
				$edit_link = '';
			}else{
				if(is_scalar($option)){
					$o_value = $option;
				}elseif(isset($option[$name])){
					$o_value = $option[$name];
				}else{
					$o_value = '';
				}

				if(!$key){
					if(icl_st_is_registered_string($es_context, $name)) {
						$edit_link = '[<a href="'.admin_url('admin.php?page='.WPML_ST_FOLDER.'/menu/string-translation.php&context='.$es_context) . '">' . __('translate', 'sitepress') . '</a>]';
					} else {
						$edit_link = '<div class="updated below-h2">' . __('string not registered', 'sitepress') . '</div>';
					}
				}else{
					$edit_link = '';
				}
			}

			if(false !== strpos($name, '*')){
				$o_value = '<span style="color:#bbb">{{ '  . __('Multiple options', 'wpml-translation-management') .  ' }}</span>';
			}else{
				$o_value = esc_html($o_value);
				if(strlen($o_value) > 200){
					$o_value = substr($o_value, 0, 200) . ' ...';
				}
			}
			echo '<li>' . $context_html . $name . ': <i>' . $o_value  . '</i> ' . $edit_link . '</li>';
		}else{
			$edit_link = '[<a href="'.admin_url('admin.php?page='.WPML_ST_FOLDER.'/menu/string-translation.php&context='.$es_context) . '">' . __('translate', 'sitepress') . '</a>]';
			echo '<strong>' . $context_html  . $name . '</strong> ' . $edit_link;
			if(!icl_st_is_registered_string($es_context, $name)) {
				$notice = '<div class="updated below-h2">' . __('some strings might be not registered', 'sitepress') . '</div>';
				echo $notice;
			}

			foreach((array)$value as $o_key=>$o_value){
				$this->render_option_writes($o_key, $o_value, $o_key . '[' . $name . ']');
			}

			//Reset cached data
			$option = false;
		}
		echo '</li>';
		echo '</ul>';
	}

	function _override_get_translatable_documents($types){
		global $wp_post_types;
		foreach($types as $k=>$type){
			if(isset($this->settings['custom_types_readonly_config'][$k]) && !$this->settings['custom_types_readonly_config'][$k]){
				unset($types[$k]);
			}
		}
		foreach($this->settings['custom_types_readonly_config'] as $cp=>$translate){
			if($translate && !isset($types[$cp]) && isset($wp_post_types[$cp])){
				$types[$cp] = $wp_post_types[$cp];
			}
		}
		return $types;
	}

	function _override_get_translatable_taxonomies($taxs_obj_type){
		global $wp_taxonomies, $sitepress;

		$taxs = $taxs_obj_type['taxs'];

		$object_type = $taxs_obj_type['object_type'];
		foreach($taxs as $k=>$tax){
			if(!$sitepress->is_translated_taxonomy($tax)){
				unset($taxs[$k]);
			}
		}

		foreach($this->settings['taxonomies_readonly_config'] as $tx=>$translate){
			if($translate && !in_array($tx, $taxs) && isset($wp_taxonomies[$tx]) && in_array($object_type, $wp_taxonomies[$tx]->object_type)){
				$taxs[] = $tx;
			}
		}

		$ret = array('taxs'=>$taxs, 'object_type'=>$taxs_obj_type['object_type']);

		return $ret;
	}

	public static function icanlocalize_service_info($info = array()) {
		global $sitepress;
		$return = array();
		$return['name'] = 'ICanLocalize';
		$return['logo'] = ICL_PLUGIN_URL . '/res/img/web_logo_small.png';
		$return['setup_url'] = $sitepress->create_icl_popup_link('@select-translators;from_replace;to_replace@', array('ar' => 1), true);
		$return['header'] = __('Looking for a quality translation service?', 'sitepress');
		$return['description'] = __('ICanLocalize, the makers of WPML, offers excellent<br /> human service done by expert translators, for only $0.09 per word.', 'sitepress');
		$info['icanlocalize'] = $return;
		return $info;
	}

	public function clear_cache() {
		global $wpdb;
		delete_option($wpdb->prefix . 'icl_translators_cached');
		delete_option($wpdb->prefix . 'icl_non_translators_cached');
	}

	// shows post content for visual mode (iframe) in translation editor
	function _show_post_content(){
		global $tinymce_version;
		$data = '';
		
		$post = $this->_get_post($_GET['post_id']);
		
		if($post){
			
			if(0 === strpos($_GET['field_type'], 'field-')){
				// A Types field
				$data = get_post_meta($_GET['post_id'], preg_replace('#^field-#', '', $_GET['field_type']), true);
			}else{
				if (isset($post->string_data[$_GET['field_type']])) {
					// A string from an external
					$data = $post->string_data[$_GET['field_type']];
				} else {
					// The post body.
					remove_filter('the_content', 'do_shortcode', 11);
					$data = apply_filters('the_content', $post->post_content);
				}
			}

			if(@intval($_GET['rtl'])){
				$rtl = ' dir="rtl"';
			}else{
				$rtl = '';
			}
			echo '<html'.$rtl.'>';
			echo '<head>';
			$csss = array(
				'/' . WPINC . '/js/tinymce/themes/advanced/skins/wp_theme/content.css?ver='.$tinymce_version,
				'/' . WPINC . '/js/tinymce/plugins/spellchecker/css/content.css?ver='.$tinymce_version,
				'/' . WPINC . '/js/tinymce/plugins/wordpress/css/content.css?ver='.$tinymce_version
			);
			foreach($csss as $css){
				echo '<link rel="stylesheet" href="'.site_url() . $css . '">' . "\n";
			}
			echo '</head>';
			echo '<body>';
			echo $data;
			echo '</body>';
			echo '</html>';
			exit;
		}else{
			wp_die(__('Post not found!', 'sitepress'));
		}
		exit;
	}

	function _user_search(){
		$q = $_POST['q'];

		$non_translators = self::get_blog_not_translators();

		$matched_users = array();
		foreach($non_translators as $t){
			if(false !== stripos($t->user_login, $q) || false !== stripos($t->display_name, $q)){
				$matched_users[] = $t;
			}
			if(count($matched_users) == 100) break;
		}

		if(!empty($matched_users)){
			$cssheight  = count($matched_users) > 10 ? '200' : 20*count($matched_users) + 5;
			echo '<select size="10" class="icl_tm_auto_suggest_dd" style="height:'.$cssheight.'px">';
			foreach($matched_users as $u){
				echo '<option value="' . $u->ID . '|' . esc_attr($u->display_name).'">'.$u->display_name . ' ('.$u->user_login.')'.'</option>';

			}
			echo '</select>';

		}else{
			echo '&nbsp;<span id="icl_user_src_nf">';
			_e('No matches', 'sitepress');
			echo '</span>';
		}


		exit;

	}

	// set slug according to user preference
	static function set_page_url($post_id){

		global $sitepress, $sitepress_settings, $wpdb;

		if($sitepress_settings['translated_document_page_url'] == 'copy-encoded'){

			$post = $wpdb->get_row($wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID=%d", $post_id));
			$translation_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $post_id, 'post_' . $post->post_type));

			$encode_url = $wpdb->get_var($wpdb->prepare("SELECT encode_url FROM {$wpdb->prefix}icl_languages WHERE code=%s", $translation_row->language_code));
			if($encode_url){

				$trid = $sitepress->get_element_trid($post_id, 'post_' . $post->post_type);
				$original_post_id = $wpdb->get_var($wpdb->prepare("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND source_language_code IS NULL", $trid));
				$post_name_original = $wpdb->get_var($wpdb->prepare("SELECT post_name FROM {$wpdb->posts} WHERE ID = %d", $original_post_id));

				$post_name_to_be = $post_name_original;
				$taken = true;
				$incr = 1;
				do{
					$taken = $wpdb->get_var($wpdb->prepare("
						SELECT ID FROM {$wpdb->posts} p
						JOIN {$wpdb->prefix}icl_translations t ON p.ID = t.element_id
						WHERE ID <> %d AND t.element_type = %s AND t.language_code = %s AND p.post_name = %s
						", $post_id, 'post_' . $post->post_type, $translation_row->language_code, $post_name_to_be ));
					if($taken){
						$incr++;
						$post_name_to_be = $post_name_original . '-' . $incr;
					}else{
						$taken = false;
					}
				}while($taken == true);
				$wpdb->update($wpdb->posts, array('post_name' => $post_name_to_be), array('ID' => $post_id));

			}

		}

	}

	/**
	 * @param $postarr
	 *
	 * @param $lang
	 *
	 * @return int|WP_Error
	 */
	public function icl_insert_post( $postarr, $lang )
	{
		global $sitepress;
		$current_language = $sitepress->get_current_language();
		$sitepress->switch_lang( $lang, false );
		$new_post_id = wp_insert_post( $postarr );
		$sitepress->switch_lang( $current_language, false );

		return $new_post_id;
	}

	/**
	 * Add missing language to posts
	 *
	 * @param array $post_types
	 */
	protected function add_missing_language_to_posts( $post_types )
	{
		global $wpdb;

		//This will be improved when it will be possible to pass an array to the IN clause
		$posts_prepared = "SELECT ID, post_type, post_status FROM {$wpdb->posts} WHERE post_type IN ('" . implode("', '", esc_sql($post_types)) . "')";
		$posts = $wpdb->get_results( $posts_prepared );
		if ( $posts ) {
			foreach ( $posts as $post ) {
				$this->add_missing_language_to_post( $post );
			}
		}
	}

	/**
	 * Add missing language to a given post
	 *
	 * @param WP_Post $post
	 */
	protected function add_missing_language_to_post( $post ) {
		global $sitepress, $wpdb;

		$query_prepared   = $wpdb->prepare( "SELECT translation_id, language_code FROM {$wpdb->prefix}icl_translations WHERE element_type=%s AND element_id=%d", array( 'post_' . $post->post_type, $post->ID ) );
		$query_results    = $wpdb->get_row( $query_prepared );

		//if translation exists
		if (!is_null($query_results)){
			$translation_id   = $query_results->translation_id;
			$language_code    = $query_results->language_code;
		}else{
			$translation_id   = null;
			$language_code    = null;
		}

		$urls             = $sitepress->get_setting( 'urls' );
		$is_root_page     = $urls && isset( $urls[ 'root_page' ] ) && $urls[ 'root_page' ] == $post->ID;
		$default_language = $sitepress->get_default_language();

		if ( !$translation_id && !$is_root_page && !in_array( $post->post_status, array( 'auto-draft' ) ) ) {
			$sitepress->set_element_language_details( $post->ID, 'post_' . $post->post_type, null, $default_language );
		} elseif ( $translation_id && $is_root_page ) {
			$trid = $sitepress->get_element_trid( $post->ID, 'post_' . $post->post_type );
			if ( $trid ) {
				$sitepress->delete_element_translation( $trid, 'post_' . $post->post_type );
			}
		} elseif ( $translation_id && !$language_code && $default_language ) {
			$where = array( 'translation_id' => $translation_id );
			$data  = array( 'language_code' => $default_language );
			$wpdb->update( $wpdb->prefix . 'icl_translations', $data, $where );
		}
	}

	/**
	 * Add missing language to taxonomies
	 *
	 * @param array $post_types
	 */
	protected function add_missing_language_to_taxonomies( $post_types )
	{
		global $sitepress, $wpdb;
		$taxonomy_types = array();
		foreach ( $post_types as $post_type ) {
			$taxonomy_types = array_merge( $sitepress->get_translatable_taxonomies( true, $post_type ), $taxonomy_types );
		}
		$taxonomy_types = array_unique( $taxonomy_types );
		$taxonomies     = $wpdb->get_results( "SELECT taxonomy, term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('" . join( "','", $taxonomy_types ) . "')" );
		if ( $taxonomies ) {
			foreach ( $taxonomies as $taxonomy ) {
				$this->add_missing_language_to_taxonomy( $taxonomy );
			}
		}
	}

	/**
	 * Add missing language to a given taxonomy
	 *
	 * @param OBJECT $taxonomy
	 */
	protected function add_missing_language_to_taxonomy( $taxonomy )
	{
		global $sitepress, $wpdb;
		$tid_prepared = $wpdb->prepare( "SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_type=%s AND element_id=%d", 'tax_' . $taxonomy->taxonomy, $taxonomy->term_taxonomy_id );
		$tid          = $wpdb->get_var( $tid_prepared );
		if ( !$tid ) {
			$sitepress->set_element_language_details( $taxonomy->term_taxonomy_id, 'tax_' . $taxonomy->taxonomy, null, $sitepress->get_default_language() );
		}
	}

	/**
	 * Add missing language to comments
	 */
	protected function add_missing_language_to_comments()
	{
		global $sitepress, $wpdb;
		$comment_ids_prepared = $wpdb->prepare( "SELECT c.comment_ID FROM {$wpdb->comments} c LEFT JOIN {$wpdb->prefix}icl_translations t ON t.element_id = c.comment_id AND t.element_type=%s WHERE t.element_id IS NULL", 'comment' );
		$comment_ids          = $wpdb->get_col( $comment_ids_prepared );
		if ( $comment_ids ) {
			foreach ( $comment_ids as $comment_id ) {
				$sitepress->set_element_language_details( $comment_id, 'comment', null, $sitepress->get_default_language() );
			}
		}
	}

	/**
	 * Add missing language information to entities that don't have this
	 * information configured.
	 */
	public function add_missing_language_information()
	{
		global $sitepress;
		$translatable_documents = array_keys( $sitepress->get_translatable_documents(true) );
		if( $translatable_documents ) {
			$this->add_missing_language_to_posts( $translatable_documents );
			$this->add_missing_language_to_taxonomies( $translatable_documents );
		}
		$this->add_missing_language_to_comments();
	}

}
