<?php
if(!defined('ICL_PLUGIN_PATH')) return;

define ('WPML_XLIFF_EMBED_VERSION', '0.0.1');

require_once( ABSPATH . 'wp-admin/includes/file.php' );
require_once( WPML_TM_PATH . '/inc/wpml-xliff.class.php' );
require_once( WPML_TM_PATH . '/inc/wpml_zip.php' );

class WPML_Translation_Management_XLIFF{

    private static $instance;
    public static function get_instance() {
        if(!isset(self::$instance)) {
            self::$instance = new WPML_Translation_Management_XLIFF();
        }
        return self::$instance;
    }

	public $success;
	public $error;

	private $attachments = array();

	private static $_available_xliff_version = array(
													"10" => "1.0",
													"11" => "1.1",
													"12" => "1.2"
												);

	static function get_available_xliff_version() {
		return WPML_Translation_Management_XLIFF::$_available_xliff_version;
	}

    protected function __construct( )
    {
		// For xliff upload or download we need to make sure other plugins are loaded first.
		$init_priority = ( isset( $_POST[ 'xliff_upload' ] ) || ( isset( $_GET[ 'wpml_xliff_action' ] ) && $_GET[ 'wpml_xliff_action' ] == 'download' ) ) ? 1501 : 10;
		add_action('init', array($this,'init'), $init_priority );
		
		if ( defined( 'WPML_XLIFF_VERSION' ) && version_compare( WPML_XLIFF_VERSION, '0.9.9', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'wpml_xliff_warning' ) );
		}
		
    }

	function wpml_xliff_warning() {
	
        ?>
        <div class="message error"><p><?php _e('WPML XLIFF is now included with WPML\'s Translation Management. Please uninstall the WPML XLIFF plugin.', 'wpml-translation-management'); ?></p></div>
        <?php
    }    

    function __destruct(){
        return;
    }

	function init() {

		$this->attachments = array();

		$this->error = null;

		if ( is_admin() ) {

			add_action( 'admin_head', array( $this, 'js_scripts' ) );
			add_action( 'wp_ajax_set_xliff_options', array( $this, 'ajax_set_xliff_options' ), 10, 2 );

			global $sitepress, $sitepress_settings;

			if ( !$sitepress->get_setting( 'xliff_newlines' ) ) {
				$sitepress->set_setting( 'xliff_newlines', WPML_XLIFF_TM_NEWLINES_REPLACE, true );
			}

			if ( !$sitepress->get_setting( 'tm_xliff_version' ) ) {
				$sitepress->set_setting( 'tm_xliff_version', '12', true );
			}

			if ( 1 < count( $sitepress->get_active_languages() ) ) {

				add_filter( 'WPML_translation_queue_actions', array( $this, 'translation_queue_add_actions' ) );
				add_action( 'WPML_xliff_select_actions', array( $this, 'translation_queue_xliff_select_actions' ), 10, 2 );
				add_action( 'WPML_translation_queue_do_actions_export_xliff', array( $this, 'translation_queue_do_actions_export_xliff' ), 10, 2 );

				add_action( 'WPML_translator_notification', array( $this, 'translator_notification' ), 10, 0 );

				add_filter( 'WPML_new_job_notification', array( $this, 'new_job_notification' ), 10, 2 );
				add_filter( 'WPML_new_job_notification_body', array( $this, 'new_job_notification_body' ), 10, 2 );
				add_filter( 'WPML_new_job_notification_attachments', array( $this, 'new_job_notification_attachments' ) );
			}

			if ( isset( $_GET[ 'wpml_xliff_action' ] ) && $_GET[ 'wpml_xliff_action' ] == 'download' && $_GET[ 'nonce' ] = wp_create_nonce( 'xliff-export' ) ) {
				$this->export_xliff( $_GET[ "xliff_version" ] );
			}

			if ( isset( $_POST[ 'xliff_upload' ] ) ) {
				$this->error = $this->import_xliff( $_FILES[ 'import' ] );
				if ( is_wp_error ( $this->error ) ) {
					add_action( 'admin_notices', array( $this, '_error' ) );
				}
			}

			if ( isset( $_POST[ 'icl_tm_action' ] ) && $_POST[ 'icl_tm_action' ] == 'save_notification_settings' ) {
				$include_xliff = false;
				if ( isset( $_POST[ 'include_xliff' ] ) && $_POST[ 'include_xliff' ] ) {
					$include_xliff = true;
				}

				$sitepress->save_settings( array( 'include_xliff_in_notification' => $include_xliff ) );
				$sitepress_settings[ 'include_xliff_in_notification' ] = $include_xliff;
			}
		}

		return true;
	}

	function get_user_xliff_version() {
		global $sitepress;

		$version = $sitepress->get_setting("tm_xliff_version") ? $sitepress->get_setting("tm_xliff_version") : false;

		return $version;
	}

	function ajax_set_xliff_options() {

		check_ajax_referer( 'icl_xliff_options_form_nonce', 'security' );

		global $sitepress;

		$newlines = intval($_POST['icl_xliff_newlines']);
		$sitepress->set_setting("xliff_newlines", $newlines, true);

		$version = intval($_POST['icl_xliff_version']);
		$sitepress->set_setting("tm_xliff_version", $version, true);

		wp_send_json_success( array('message'=>'OK', 'newlines_saved' => $newlines, 'version_saved' => $version) );
		die();
	}

	/**
	 * @param array $mail
	 * @param int   $job_id
	 *
	 * @return array
	 */
	function new_job_notification( $mail, $job_id ) {
		global $sitepress_settings;

		if ( ! empty( $sitepress_settings['include_xliff_in_notification'] ) ) {
			$xliff_version = $this->get_user_xliff_version();
			$xliff_file    = $this->get_xliff_file( $job_id, $xliff_version );
			$temp_dir      = get_temp_dir();
			$file_name     = $temp_dir . get_bloginfo( 'name' ) . '-translation-job-' . $job_id . '.xliff';
			$fh            = fopen( $file_name, 'w' );
			if ( $fh ) {
				fwrite( $fh, $xliff_file );
				fclose( $fh );
				$mail['attachment']           = $file_name;
				$this->attachments[ $job_id ] = $file_name;
				$mail['body'] .= __( ' - A xliff file is attached.', 'wpml-translation-management' );
			}
		}

		return $mail;
	}

	function new_job_notification_body($body, $tj_url) {
		
		if (strpos($body, __(' - A xliff file is attached.', 'wpml-translation-management')) !== FALSE) {
			$body = str_replace(sprintf(__('You can view your other translation jobs here: %s', 'sitepress'), $tj_url), sprintf(__('To return the completed translation and view other translation jobs, go here: %s', 'wpml-translation-management'), $tj_url) . "\n" . sprintf(__('For help, see translator guidelines: %s', 'wpml-translation-management'), 'https://wpml.org/?page_id=8021'), $body);
		}
		
		return $body;		
	}
	
	function _get_zip_name_from_attachments() {
		return $this->_get_zip_name_from_jobs(array_keys($this->attachments));
	}
	
	function _get_zip_name_from_jobs($job_ids) {
		$min_job = min($job_ids);
		$max_job = max($job_ids);
		
		if ($max_job == $min_job) {
			return get_bloginfo( 'name' ) . '-translation-job-' . $max_job . '.zip';
		} else {
			return get_bloginfo( 'name' ) . '-translation-job-' . $min_job . '-' . $max_job . '.zip';
		}
	}
	
	function new_job_notification_attachments($attachments) {

		// check for xliff attachments and add them to a zip file.

		$found = false;		

		$archive = new wpml_zip();
		
		foreach ($attachments as $index => $attachment) {
			if (in_array($attachment, $this->attachments)) {
				$fh = fopen($attachment, 'r');
				$xliff_file = fread($fh, filesize($attachment));
				fclose($fh);
				$archive->addFile($xliff_file, basename($attachment));

				unset($attachments[$index]);
				$found = true;
			}
		}
		
		if ($found) {
			// add the zip file to the attachments.
			$archive_data = $archive->getZipData();

			$temp_dir = get_temp_dir();
		
			$file_name = $temp_dir . $this->_get_zip_name_from_attachments();
			
			$fh = fopen($file_name, 'w');
			fwrite($fh, $archive_data);
			fclose($fh);
			
			$attachments[] = $file_name;
			
		}
		return $attachments;
	}
	
	function get_xliff_file( $job_id, $xliff_version = WPML_XLIFF_DEFAULT_VERSION ) {
		$xliff = new WPML_TM_xliff( $xliff_version );
		return $xliff->generate_job_xliff( $job_id );
	}

	function export_xliff( $xliff_version ) {
		global $wpdb, $current_user;
		get_currentuserinfo();

		$data = $_GET[ 'xliff_export_data' ];
		$data = unserialize( base64_decode( $data ) );

		$archive = new wpml_zip();

		$job_ids = array();
		foreach ( $data[ 'job' ] as $job_id => $dummy ) {
			$xliff_file = $this->get_xliff_file( $job_id, $xliff_version );

			// assign the job to this translator
			$rid        = $wpdb->get_var( $wpdb->prepare("SELECT rid
														  FROM {$wpdb->prefix}icl_translate_job
														  WHERE job_id=%d ", $job_id ) );
			$data       = array( 'translator_id' => $current_user->ID );
			$data_where = array( 'job_id' => $job_id );
			$wpdb->update( $wpdb->prefix . 'icl_translate_job', $data, $data_where );
			$data_where = array( 'rid' => $rid );
			$wpdb->update( $wpdb->prefix . 'icl_translation_status', $data, $data_where );

			$archive->addFile( $xliff_file, get_bloginfo( 'name' ) . '-translation-job-' . $job_id . '.xliff' );

			$job_ids[ ] = $job_id;
		}

		$archive->sendZip($this->_get_zip_name_from_jobs($job_ids));
		exit;
	}

	function _stop_redirect($location) {
		// Stop any redirects from happening when we call the
		// translation manager to save the translations.
		return null;
	}

	/**
	 * @param array $file
	 *
	 * @return bool|WP_Error
	 */
	function import_xliff($file) {
		
	  global $current_user;
	  get_currentuserinfo();

		// We don't want any redirects happening when we save the translation
		add_filter('wp_redirect', array($this, '_stop_redirect'));
		
		global $iclTranslationManagement;

		$this->success = array();
		
		$contents = array();

		// test for a zip file
		$zip_file = false;

		if ( isset( $file[ 'tmp_name' ] ) && $file[ 'tmp_name' ] ) {
			$fh   = fopen( $file[ 'tmp_name' ], 'r' );
			$data = fread( $fh, 4 );
			fclose( $fh );

			if ( $data[ 0 ] == 'P' && $data[ 1 ] == 'K' && $data[ 2 ] == chr( 03 ) && $data[ 3 ] == chr( 04 ) ) {
				$zip_file = true;
			}

			if ( $zip_file ) {
				if ( class_exists( 'ZipArchive' ) ) {
					$z = new ZipArchive();

					// PHP4-compat - php4 classes can't contain constants
					$zopen = $z->open( $file[ 'tmp_name' ], /* ZIPARCHIVE::CHECKCONS */
					                   4 );
					if ( true !== $zopen ) {
						return new WP_Error( 'incompatible_archive', __( 'Incompatible Archive.' ) );
					}

					for ( $i = 0; $i < $z->numFiles; $i ++ ) {
						if ( ! $info = $z->statIndex( $i ) ) {
							return new WP_Error( 'stat_failed', __( 'Could not retrieve file from archive.' ) );
						}

						$content = $z->getFromIndex( $i );
						if ( false === $content ) {
							return new WP_Error( 'extract_failed', __( 'Could not extract file from archive.' ), $info[ 'name' ] );
						}

						$contents[ $info[ 'name' ] ] = $content;
					}
				} else {
					require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );

					$archive = new PclZip( $file[ 'tmp_name' ] );

					// Is the archive valid?
					if ( false == ( $archive_files = $archive->extract( PCLZIP_OPT_EXTRACT_AS_STRING ) ) ) {
						return new WP_Error( 'incompatible_archive', __( 'Incompatible Archive.' ), $archive->errorInfo( true ) );
					}

					if ( 0 == count( $archive_files ) ) {
						return new WP_Error( 'empty_archive', __( 'Empty archive.' ) );
					}

					foreach ( $archive_files as $content ) {
						$contents[ $content[ 'filename' ] ] = $content[ 'content' ];
					}
				}
			} else {
				$fh   = fopen( $file[ 'tmp_name' ], 'r' );
				$data = fread( $fh, $file[ 'size' ] );
				fclose( $fh );
				$contents[ $file[ 'name' ] ] = $data;
			}

			foreach ( $contents as $name => $content ) {
				if ( ! function_exists( 'simplexml_load_string' ) ) {
					return new WP_Error( 'xml_missing', __( 'The Simple XML library is missing.', 'wpml-translation-management' ) );
				}

				$new_error_handler = create_function( '$errno, $errstr, $errfile, $errline', 'throw new ErrorException( $errstr, $errno, 1, $errfile, $errline );' );
				set_error_handler( $new_error_handler );

				try {
					$xml = simplexml_load_string( $content );
				} catch ( Exception $e ) {
					$xml = false;
				}

				restore_error_handler();

				if ( ! $xml ) {
					return new WP_Error( 'not_xml_file', sprintf( __( '"%s" is not a valid XLIFF file.', 'wpml-translation-management' ), $name ) );
				}

				if ( ! isset( $xml->file ) ) {
					return new WP_Error( 'not_xml_file', sprintf( __( '"%s" is not a valid XLIFF file.', 'wpml-translation-management' ), $name ) );
				}

				$file_attributes = $xml->file->attributes();
				if ( ! $file_attributes || ! isset( $file_attributes[ 'original' ] ) ) {
					return new WP_Error( 'not_xml_file', sprintf( __( '"%s" is not a valid XLIFF file.', 'wpml-translation-management' ), $name ) );
				}

				$original = (string) $file_attributes[ 'original' ];
				list( $job_id, $md5 ) = explode( '-', $original );

				$job = $iclTranslationManagement->get_translation_job( (int) $job_id, false, false, 1 ); // don't include not-translatable and don't auto-assign

				if ( ! $job || ( $md5 != md5( $job_id . $job->original_doc_id ) ) ) {
					return new WP_Error( 'xliff_doesnt_match', __( 'The uploaded xliff file doesn\'t belong to this system.', 'wpml-translation-management' ) );
				}

				if ( $current_user->ID != $job->translator_id ) {
					return new WP_Error( 'not_your_job', sprintf( __( 'The translation job (%s) doesn\'t belong to you.', 'wpml-translation-management' ), $job_id ) );
				}

				$data = array( 'job_id' => $job_id, 'fields' => array(), 'complete' => 1 );

				foreach ( $xml->file->body->children() as $node ) {
					$attr   = $node->attributes();
					$type   = (string) $attr[ 'id' ];
					$source = (string) $node->source;
					$target = $this->get_xliff_node_target( $node );

					if ( ! $target ) {
					  return new WP_Error( 'xliff_invalid', __( 'The uploaded xliff file does not seem to be properly formed.', 'wpml-translation-management' ) );
					}

					foreach ( $job->elements as $element ) {
						if ( $element->field_type == $type ) {
							$target = str_replace( '<br class="xliff-newline" />', "\n", $target );
							if ( $element->field_format == 'csv_base64' ) {
								$target = explode( ',', $target );
							}
							$field                 = array();
							$field[ 'data' ]       = $target;
							$field[ 'finished' ]   = 1;
							$field[ 'tid' ]        = $element->tid;
							$field[ 'field_type' ] = $element->field_type;
							$field[ 'format' ]     = $element->field_format;

							$data[ 'fields' ][ ] = $field;
							break;
						}
					}
				}

				wpml_tm_save_data($data);
				$this->success[ ] = sprintf( __( 'Translation of job %s has been uploaded and completed.', 'wpml-translation-management' ), $job_id );
			}

			if ( sizeof( $this->success ) > 0 ) {
				add_action( 'admin_notices', array( $this, '_success' ) );

				return true;
			}
		}
		return false;
	}

	function translation_queue_xliff_select_actions( $actions, $action_name ) {
		if(sizeof($actions)>0):
			$user_version = $this->get_user_xliff_version();
		?>
			<div class="alignleft actions">
				<select name="<?php echo $action_name; ?>">
					<option value="-1" <?php echo $user_version==false?"selected='selected'":""; ?>><?php _e('Bulk Actions'); ?></option>
					<?php foreach($actions as $key => $action):?>
						<option value="<?php echo $key; ?>" <?php echo $user_version == $key?"selected='selected'":""; ?>><?php echo $action; ?></option>
					<?php endforeach; ?>
				</select>
				<input type="submit" value="<?php esc_attr_e('Apply'); ?>" name="do<?php echo $action_name; ?>" class="button-secondary action" />
			</div>
		<?php
		endif;
	}
	
	function translation_queue_add_actions($actions) {

		$actions = array();

		foreach (self::$_available_xliff_version as $key=>$value) {
			$actions[ $key ] = __( sprintf( 'Export XLIFF %s', $value ), 'wpml-translation-management' );
		}

		return $actions;
	}

	function translation_queue_do_actions_export_xliff($data, $xliff_version) {

		?>
		<script type="text/javascript">
		<?php
		if (isset($data['job'])) {
			// Add an on load javascript event and redirect to a download link.
			
			$data = base64_encode(serialize($data));
			$nonce = wp_create_nonce('xliff-export');
		?>
				
				var xliff_export_data = "<?php echo $data; ?>";
				var xliff_export_nonce = "<?php echo $nonce; ?>";
				var xliff_version = "<?php echo $xliff_version; ?>";
				addLoadEvent(function(){
					window.location = "<?php echo htmlentities($_SERVER['REQUEST_URI']) ?>&wpml_xliff_action=download&xliff_export_data=" + xliff_export_data + "&nonce=" + xliff_export_nonce + "&xliff_version=" + xliff_version;
					});
							
		<?php
		} else {
		?>
			var error_message = "<?php echo __('No translation jobs were selected for export.', 'wpml-translation-management'); ?>";
			alert( error_message );
		<?php
		}
		?>
		</script>
		<?php
	}
	
    function menu(){
	    if(!defined('ICL_PLUGIN_PATH')) return;
        $top_page = apply_filters('icl_menu_main_page', basename(ICL_PLUGIN_PATH).'/menu/languages.php');
        add_submenu_page($top_page, __('XLIFF','wpml-translation-management'), __('XLIFF','wpml-translation-management'), 'manage_options', 'wpml-xliff', array($this,'menu_content'));
    }
    
    function menu_content(){
        global $wpdb;
		
        include WPML_TM_PATH . '/menu/xliff-management.php';
    }

    
    function _error(){
        ?>
        <div class="message error"><p><?php echo $this->error->get_error_message()?></p></div>
        <?php
    }    
	
    function _success(){
        ?>
        <div class="message updated"><p><ul>
		<?php
			foreach($this->success as $message) {
				echo '<li>' . $message . '</li>';
			}
		?>
		</ul></p></div>
        <?php
    }    
	
    function js_scripts(){
		global $pagenow;

		if(!defined('WPML_TM_FOLDER')) return;

		if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == WPML_TM_FOLDER . '/menu/translations-queue.php') {
	        $form_data = '<br /><form enctype="multipart/form-data" method="post" id="translation-xliff-upload" action="">';
			$form_data .= '<table class="widefat"><thead><tr><th>' . __('Import XLIFF', 'wpml-translation-management') . '</th></tr></thead><tbody><tr><td>';
			
			$form_data .= '<label for="upload-xliff-file">' . __('Select the xliff file or zip file to upload from your computer:&nbsp;', 'wpml-translation-management') . '</label>';
			$form_data .= '<input type="file" id="upload-xliff-file" name="import" /><input type="submit" value="' . __('Upload', 'wpml-translation-management') . '" name="xliff_upload" id="xliff_upload" class="button-secondary action" />';
			
			$form_data .=  '</td></tr></tbody></table>';

			$form_data .= '</form>';
			?>
			<script type="text/javascript">
				addLoadEvent(function(){                     
					jQuery('form[name$="translation-jobs-action"]').append('<?php echo $form_data?>');
				});
			</script>
			
			<?php
		}

	    if (!defined ('DOING_AJAX')) {
		    ?>
		    <script type="text/javascript">
			    var wpml_xliff_ajax_nonce = '<?php echo wp_create_nonce( "icl_xliff_options_form_nonce" ); ?>';
		    </script>
	    <?php
	    }
	}

	function translator_notification() {
		global $sitepress_settings;
		
		$checked = '';
		if (isset($sitepress_settings['include_xliff_in_notification']) && $sitepress_settings['include_xliff_in_notification']) {
			$checked = 'checked="checked"';
		}
		?>
		<input type="checkbox" name="include_xliff" id="icl_include_xliff" value="1" <?php echo $checked; ?>/>
        <label for="icl_include_xliff"><?php _e('Include XLIFF files in notification emails', 'wpml-translation-management'); ?></label>
		<?php
		
	}

	private function get_xliff_node_target( $xliff_node ) {
		//@todo use logic in \WPML_TM_xliff::get_xliff_node_target
		if ( isset( $xliff_node->target->mrk ) ) {
			$target = (string) $xliff_node->target->mrk;

			return $target;
		} else {
			$target = (string) $xliff_node->target;

			return $target;
		}
	}
}
