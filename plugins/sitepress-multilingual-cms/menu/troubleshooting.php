<?php

include_once ICL_PLUGIN_PATH . '/inc/functions-troubleshooting.php';

/* DEBUG ACTION */
/**
 * @param $term_object
 *
 * @return callable
 */
function get_term_taxonomy_id_from_term_object($term_object)
{
	return $term_object->term_taxonomy_id;
}

if ( isset( $_GET[ 'debug_action' ] ) && $_GET[ 'nonce' ] == wp_create_nonce( $_GET[ 'debug_action' ] ) ) {
	ob_end_clean();
	switch ( $_GET[ 'debug_action' ] ) {
		case 'fix_languages':
			SitePress_Setup::fill_languages();
			SitePress_Setup::fill_languages_translations();
            icl_cache_clear();
			exit;
		case 'reset_pro_translation_configuration':
			$sitepress_settings = get_option( 'icl_sitepress_settings' );

			$sitepress_settings[ 'content_translation_languages_setup' ] = false;
			$sitepress_settings[ 'content_translation_setup_complete' ]  = false;
			unset( $sitepress_settings[ 'content_translation_setup_wizard_step' ] );
			unset( $sitepress_settings[ 'site_id' ] );
			unset( $sitepress_settings[ 'access_key' ] );
			unset( $sitepress_settings[ 'translator_choice' ] );
			unset( $sitepress_settings[ 'icl_lang_status' ] );
			unset( $sitepress_settings[ 'icl_balance' ] );
			unset( $sitepress_settings[ 'icl_support_ticket_id' ] );
			unset( $sitepress_settings[ 'icl_current_session' ] );
			unset( $sitepress_settings[ 'last_get_translator_status_call' ] );
			unset( $sitepress_settings[ 'last_icl_reminder_fetch' ] );
			unset( $sitepress_settings[ 'icl_account_email' ] );
			unset( $sitepress_settings[ 'translators_management_info' ] );

			update_option( 'icl_sitepress_settings', $sitepress_settings );

			global $wpdb;
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}icl_core_status" ); 
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}icl_content_status" );
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}icl_string_status" );
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}icl_node" );
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}icl_reminders" );

			echo "<script type=\"text/javascript\">location.href='admin.php?page=" .
				basename( ICL_PLUGIN_PATH ) . '/menu/troubleshooting.php&message=' . __( 'PRO translation was reset.', 'sitepress' ) . "'</script>";
			exit;
		case 'ghost_clean':

			// clean the icl_translations table
			$orphans = $wpdb->get_col( "
                SELECT t.translation_id 
                FROM {$wpdb->prefix}icl_translations t 
                LEFT JOIN {$wpdb->posts} p ON t.element_id = p.ID 
                WHERE t.element_id IS NOT NULL AND t.element_type LIKE 'post\\_%' AND p.ID IS NULL
            " );
			if ( !empty( $orphans ) ) {
				$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id IN (" . join( ',', $orphans ) . ")" );
			}

			$orphans = $wpdb->get_col( "
                SELECT t.translation_id 
                FROM {$wpdb->prefix}icl_translations t 
                LEFT JOIN {$wpdb->comments} c ON t.element_id = c.comment_ID
                WHERE t.element_type = 'comment' AND c.comment_ID IS NULL " );
			if ( false === $orphans ) {
				echo $wpdb->last_result; 
			}
			if ( !empty( $orphans ) ) {
				$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id IN (" . join( ',', $orphans ) . ")" );
			}

			$orphans = $wpdb->get_col( "
                SELECT t.translation_id 
                FROM {$wpdb->prefix}icl_translations t 
                LEFT JOIN {$wpdb->term_taxonomy} p ON t.element_id = p.term_taxonomy_id 
                WHERE t.element_id IS NOT NULL AND t.element_type LIKE 'tax\\_%' AND p.term_taxonomy_id IS NULL" );
			if ( !empty( $orphans ) ) {
				$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id IN (" . join( ',', $orphans ) . ")" );
			}

			global $wp_taxonomies;
			if ( is_array( $wp_taxonomies ) ) {
				foreach ( $wp_taxonomies as $t => $v ) {
					$orphans = $wpdb->get_col( "
                SELECT t.translation_id 
                FROM {$wpdb->prefix}icl_translations t 
                LEFT JOIN {$wpdb->term_taxonomy} p 
                ON t.element_id = p.term_taxonomy_id 
                WHERE t.element_type = 'tax_{$t}' 
                AND p.taxonomy <> '{$t}'
                    " );
					if ( !empty( $orphans ) ) {
						$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id IN (" . join( ',', $orphans ) . ")" );
					}
				}
			}

			// remove ghost translations
			// get unlinked rids
			$rids = $wpdb->get_col( "SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id NOT IN (SELECT translation_id FROM {$wpdb->prefix}icl_translations)" );
			if ( $rids ) {
				$jids = $wpdb->get_col( "SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid IN (" . join( ',', $rids ) . ")" );
				if ( $jids ) {
					$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id IN (" . join( ',', $jids ) . ")" );
					$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translate_job WHERE job_id IN (" . join( ',', $jids ) . ")" );
					$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translation_status WHERE rid IN (" . join( ',', $rids ) . ")" );
				}
			}

			// remove any duplicates in icl_translations
			$trs = $wpdb->get_results( "SELECT element_id, GROUP_CONCAT(translation_id) AS tids FROM {$wpdb->prefix}icl_translations
                WHERE element_id > 0 AND element_type LIKE 'post\\_%' GROUP BY element_id" );
			foreach ( $trs as $r ) {
				$exp = explode( ',', $r->tids );
				if ( count( $exp ) > 1 ) {
					$maxtid = max( $exp );
					foreach ( $exp as $e ) {
						if ( $e != $maxtid ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id=%d", $e ) );
						}
					}
				}
			}


			exit;
			break;
		case 'icl_sync_jobs':

			$iclq     = new ICanLocalizeQuery( $sitepress_settings[ 'site_id' ], $sitepress_settings[ 'access_key' ] );
			$requests = $iclq->cms_requests_all();
			if ( !empty( $requests ) )
				foreach ( $requests as $request ) {
					$source_language = ICL_Pro_Translation::server_languages_map( $request[ 'language_name' ], true );
					$target_language = ICL_Pro_Translation::server_languages_map( $request[ 'target' ][ 'language_name' ], true );

					$source_language = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE english_name=%s", $source_language ) );
					$target_language = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE english_name=%s", $target_language ) );

					// only handle old-style cms_id values
					if ( !is_numeric( $request[ 'cms_id' ] ) )
						continue;

					$tr = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translations WHERE translation_id=%d", $request[ 'cms_id' ] ) );
					if ( empty( $tr ) ) {
						$trs = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", $request[ 'cms_id' ] ) );
						if ( !empty( $trs ) ) {
							$tpack       = unserialize( $trs->translation_package );
							$original_id = $tpack[ 'contents' ][ 'original_id' ][ 'data' ];
							list( $trid, $element_type ) = $wpdb->get_row( "
                                SELECT trid, element_type 
                                FROM {$wpdb->prefix}icl_translations 
                                WHERE element_id={$original_id}
                                AND element_type LIKE 'post\\_%'
                            ", ARRAY_N );
							if ( $trid ) {
								$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translations WHERE trid={$trid} AND language_code='{$target_language}'" );
								$recover = array(
									'translation_id'       => $request[ 'cms_id' ],
									'element_type'         => $element_type,
									//'element_id'     => this is NULL
									'trid'                 => $trid,
									'language_code'        => $target_language,
									'source_language_code' => $source_language
								);
								$wpdb->insert( $wpdb->prefix . 'icl_translations', $recover );
							}
						}
					}
				}

			// Do a check to see if the icl_translation_status is consistant.
			// There was a problem with the cancel logic leaving it in a status where
			// Translations couldn't be sent.

			global $iclTranslationManagement;

			$res_prepared = "SELECT rid, status, needs_update, md5, translation_package FROM {$wpdb->prefix}icl_translation_status";
			$res = $wpdb->get_results( $res_prepared );
			foreach ( $res as $row ) {
				if ( $row->status == ICL_TM_NOT_TRANSLATED || $row->needs_update == 1 ) {

					$tpack       = unserialize( $row->translation_package );
					$original_id = $tpack[ 'contents' ][ 'original_id' ][ 'data' ];

					$post_md5 = $iclTranslationManagement->post_md5( $original_id );

					if ( $post_md5 == $row->md5 ) {
						// The md5 shouldn't be the same if it's not translated or needs update.
						// Add a dummy md5 and mark it as needs_update.
						$data = array( 'needs_update' => 1, 'md5' => 'XXXX' );
						$wpdb->update( $wpdb->prefix . 'icl_translation_status', $data, array( 'rid' => $row->rid ) );
					}
				}
			}

			exit;
		//break;
		case 'icl_cms_id_fix':
			$iclq = new ICanLocalizeQuery( $sitepress_settings[ 'site_id' ], $sitepress_settings[ 'access_key' ] );

			$p = $wpdb->get_row( "SELECT t.* FROM {$wpdb->prefix}icl_translations t JOIN {$wpdb->prefix}icl_translation_status s ON t.translation_id=s.translation_id
                WHERE t.element_type LIKE 'post\\_%' AND t.source_language_code IS NOT NULL AND s.translation_service='icanlocalize' LIMIT {$_REQUEST['offset']}, 1" );
			if ( !empty( $p ) ) {

				$original_id = $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND source_language_code IS NULL", $p->trid ) );
				if ( $p->element_type == 'post_page' ) {
					$permalink = get_home_url() . '?page_id=' . $original_id;
				} else {
					$permalink = get_home_url() . '?p=' . $original_id;
				}
				$_lang_details = $sitepress->get_language_details( $p->source_language_code );
				$from_language = ICL_Pro_Translation::server_languages_map( $_lang_details[ 'english_name' ] );
				$_lang_details = $sitepress->get_language_details( $p->language_code );
				$to_language   = ICL_Pro_Translation::server_languages_map( $_lang_details[ 'english_name' ] );
				$cms_id        = sprintf( '%s_%d_%s_%s', preg_replace( '#^post_#', '', $p->element_type ), $original_id, $p->source_language_code, $p->language_code );

				$ret = $iclq->update_cms_id( compact( 'permalink', 'from_language', 'to_language', 'cms_id' ) );

				if ( $ret != $cms_id && $iclq->error() ) {
					echo json_encode( array( 'errors' => 1, 'message' => $iclq->error(), 'cont' => 0 ) );
				} else {
					echo json_encode( array( 'errors' => 0, 'message' => 'OK', 'cont' => 1 ) );
				}

			} else {
				echo json_encode( array( 'errors' => 0, 'message' => __( 'Done', 'sitepress' ), 'cont' => 0 ) );
			}

			exit;
		//break;
		case 'icl_cleanup':
			global $sitepress, $wpdb, $wp_post_types;
			$post_types = array_keys( $wp_post_types );
			foreach ( $post_types as $pt ) {
				$types[ ] = 'post_' . $pt;
			}
			/*
			 * Messed up on 2.0 upgrade
			 */
			// fix source_language_code
			// all source documents must have null
			$default_language = $sitepress->get_default_language();
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}icl_translations SET source_language_code = NULL
                WHERE element_type IN('" . join( "','", $types ) . "') AND source_language_code = '' AND language_code=%s", $default_language ) );
			// get translated documents with missing source language
			$res = $wpdb->get_results( $wpdb->prepare( "
                SELECT translation_id, trid, language_code
                FROM {$wpdb->prefix}icl_translations
                WHERE (source_language_code = '' OR source_language_code IS NULL)
                    AND element_type IN('" . join( "','", $types ) . "')
                    AND language_code <> %s
                    ", $default_language
									   ) );
			foreach ( $res as $row ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}icl_translations SET source_language_code = %s WHERE translation_id=%d", $default_language, $row->translation_id ) );
			}
			break;
		case 'assign_translation_status_to_duplicates':
			//ICL_TM_DUPLICATE
			if(!class_exists('TranslationManagement')) break;

			global $sitepress, $iclTranslationManagement;

			$active_languages = $sitepress->get_active_languages();

			$duplicated_posts_sql = "select meta_value from {$wpdb->postmeta} where meta_key='_icl_lang_duplicate_of' AND meta_value<>'' group by meta_value;";
			$duplicated_posts = $wpdb->get_results($duplicated_posts_sql);

			$updated_items = 0;
			foreach ( $duplicated_posts as $duplicated_posts_row ) {

				//$duplicated_post_id = $duplicated_posts_row->post_id;
				$original_post_id   = $duplicated_posts_row->meta_value;
				$element_type    = 'post_' . get_post_type( $original_post_id );
				$trid = $sitepress->get_element_trid($original_post_id, $element_type);

				$element_language_details = $sitepress->get_element_translations($trid, $element_type );

				$item_updated = false;
				foreach ( $active_languages as $code => $active_language ) {
					if ( !isset( $element_language_details[ $code ] ) ) {
						continue;
					}

					$element_translation = $element_language_details[ $code ];
					if ( !isset( $element_translation ) || $element_translation->original ) {
						continue;
					}

					$translation = $iclTranslationManagement->get_element_translation( $element_translation->element_id, $code, $element_type );
					if ( !$translation ) {
						$_POST['icl_trid'] = $trid;
						$_POST['icl_post_language'] = $code;
						$translated_post = get_post( $element_translation->element_id );
						$iclTranslationManagement->save_post_actions( $element_translation->element_id, $translated_post, ICL_TM_DUPLICATE );
						unset($_POST['icl_post_language']);
						unset($_POST['icl_trid']);
						$item_updated = true;
					}
				}

				if ( $item_updated ) {
					$updated_items++;
				}
				if ( $updated_items >= 20 ) {
					break;
				}
			}

			echo json_encode( array( 'updated' => $updated_items ));
			exit;
		case 'sync_cancelled':

			$iclq     = new ICanLocalizeQuery( $sitepress_settings[ 'site_id' ], $sitepress_settings[ 'access_key' ] );
			$requests = $iclq->cms_requests_all();

			if ( $requests === false ) {
				echo json_encode( array( 'errors' => 1, 'message' => 'Failed fetching jobs list from the server.' ) );
				exit;
			}

			$cms_ids = array();
			if ( !empty( $requests ) )
				foreach ( $requests as $request ) {
					$cms_ids[ ] = $request[ 'cms_id' ];
				}

			// get jobs that are in progress
			$translations = $wpdb->get_results( "
                SELECT t.element_id, t.element_type, t.language_code, t.source_language_code, t.trid, 
                    s.rid, s._prevstate, s.translation_id 
                FROM {$wpdb->prefix}icl_translation_status s 
                JOIN {$wpdb->prefix}icl_translations t
                    ON t.translation_id = s.translation_id    
                WHERE s.translation_service='icanlocalize'
                AND s.status = " . ICL_TM_IN_PROGRESS . "
            " );

			$job2delete = $rids2cancel = array();
			foreach ( $translations as $t ) {
				$original_id = $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations
                    WHERE trid=%d AND source_language_code IS NULL", $t->trid ) );
				$cms_id      = sprintf( '%s_%d_%s_%s', preg_replace( '#^post_#', '', $t->element_type ), $original_id, $t->source_language_code, $t->language_code );
				if ( !in_array( $cms_id, $cms_ids ) ) {
					$_lang_details          = $sitepress->get_language_details( $t->source_language_code );
					$lang_from              = $_lang_details[ 'english_name' ];
					$_lang_details          = $sitepress->get_language_details( $t->language_code );
					$lang_to                = $_lang_details[ 'english_name' ];
					$jobs2delete[ ]         = '<a href="' . get_permalink( $original_id ) . '">' . get_the_title( $original_id ) . '</a>' . sprintf( ' - from %s to %s',
																																					 $lang_from, $lang_to );
					$translations2cancel[ ] = $t;
				}
			}

			if ( !empty( $jobs2delete ) ) {
				echo json_encode( array(
									   'errors'  => 0,
									   'message' => '<div class="error" style="padding-top:5px;font-size:11px;">About to cancel these jobs:<br />
                                <ul style="margin-left:10px;"><li>' . join( '</li><li>', $jobs2delete ) . '</li></ul><br />
                                <a id="icl_ts_cancel_ok" href="#" class="button-secondary">OK</a>&nbsp;
                                    <a id="icl_ts_cancel_cancel" href="#" class="button-secondary">Cancel</a><br clear="all" /><br />
                                </div>',
									   'data'    => array( 't2c' => serialize( $translations2cancel ) )
								  )
				);
			} else {
				echo json_encode( array( 'errors' => 0, 'message' => 'Nothing to cancel.' ) );
			}

			exit;
		case 'sync_cancelled_do_delete':
			$translations = unserialize( stripslashes( $_POST[ 't2c' ] ) );
			if ( is_array( $translations ) )
				foreach ( $translations as $t ) {
					$job_id = $wpdb->get_var( $wpdb->prepare( "SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d AND revision IS NULL", $t->rid ) );
					if ( $job_id ) {
						$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $job_id ) );
						$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id=%d", $job_id ) );
						$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}icl_translate_job SET revision = NULL WHERE rid=%d ORDER BY job_id DESC LIMIT 1", $t->rid ) );
					}

					if ( !empty( $t->_prevstate ) ) {
						$_prevstate = unserialize( $t->_prevstate );
						$wpdb->update( $wpdb->prefix . 'icl_translation_status',
									   array(
											'status'              => $_prevstate[ 'status' ],
											'translator_id'       => $_prevstate[ 'translator_id' ],
											'status'              => $_prevstate[ 'status' ],
											'needs_update'        => $_prevstate[ 'needs_update' ],
											'md5'                 => $_prevstate[ 'md5' ],
											'translation_service' => $_prevstate[ 'translation_service' ],
											'translation_package' => $_prevstate[ 'translation_package' ],
											'timestamp'           => $_prevstate[ 'timestamp' ],
											'links_fixed'         => $_prevstate[ 'links_fixed' ]
									   ),
									   array( 'translation_id' => $t->translation_id )
						);
						$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}icl_translation_status SET _prevstate = NULL WHERE translation_id=%d", $t->translation_id ) );
					} else {
						$wpdb->update( $wpdb->prefix . 'icl_translation_status', array( 'status' => ICL_TM_NOT_TRANSLATED, 'needs_update' => 0 ), array( 'translation_id' => $t->translation_id ) );
					}
				}

			echo json_encode( array( 'errors' => 0, 'message' => 'OK' ) );

			exit;
		case 'icl_ts_add_missing_language':
			global $iclTranslationManagement;
			$iclTranslationManagement->add_missing_language_information();
			exit;
		case 'link_post_type':
			$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'element_type' => 'post_' . $_GET[ 'new_value' ] ), array( 'element_type' => 'post_' . $_GET[ 'old_value' ] ) );
			exit;
		case 'link_taxonomy':
			$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'element_type' => 'tax_' . $_GET[ 'new_value' ] ), array( 'element_type' => 'tax_' . $_GET[ 'old_value' ] ) );
			exit;
		case 'icl_fix_terms_count':
			global $sitepress;

			remove_filter('get_terms_args', array($sitepress, 'get_terms_args_filter'));
			remove_filter('get_term', array($sitepress,'get_term_adjust_id'));
			remove_filter('terms_clauses', array($sitepress,'terms_clauses'));
			foreach ( get_taxonomies( array(), 'names' ) as $taxonomy ) {

				$terms_objects = get_terms( $taxonomy, 'hide_empty=0'  );
				if ( $terms_objects ) {
					$term_taxonomy_ids = array_map( 'get_term_taxonomy_id_from_term_object', $terms_objects );
					wp_update_term_count( $term_taxonomy_ids, $taxonomy, true );
				}

			}
			add_filter('terms_clauses', array($sitepress,'terms_clauses'));
			add_filter('get_term', array($sitepress,'get_term_adjust_id'));
			add_filter('get_terms_args', array($sitepress, 'get_terms_args_filter'));
			exit;
	}
}
/* DEBUG ACTION */

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

if ( ( isset( $_POST[ 'icl_reset_allnonce' ] ) && $_POST[ 'icl_reset_allnonce' ] == wp_create_nonce( 'icl_reset_all' ) ) ) {
	if ( $_POST[ 'icl-reset-all' ] == 'on' ) {
		icl_reset_wpml();
		echo '<script type="text/javascript">location.href=\'' . admin_url( 'plugins.php?deactivate=true' ) . '\'</script>';
	}
}


?>
<div class="wrap">
<div id="icon-wpml" class="icon32"><br/></div>
<h2><?php echo __( 'Troubleshooting', 'sitepress' ) ?></h2>
<?php if ( isset( $_GET[ 'message' ] ) ){ ?>
	<div class="updated message fade"><p>
			<?php echo esc_html( $_GET[ 'message' ] ); ?>
		</p></div>
<?php } ?>
<?php
/*
foreach($icl_tables as $icl_table){
	echo '<a href="#'.$icl_table.'_anch">'.$icl_table.'</a> | ';
}
*/
echo '<a href="#wpml-settings">' . __( 'WPML Settings', 'sitepress' ) . '</a>';
echo '<br /><hr /><h3 id="wpml-settings"> ' . __( 'WPML settings', 'sitepress' ) . '</h3>';
echo '<textarea style="font-size:10px;width:100%" wrap="off" rows="16" readonly="readonly">';
ob_start();
print_r( $sitepress->get_settings() );
$ob = ob_get_contents();
ob_end_clean();
echo esc_html( $ob );
echo '</textarea>';

?>

<script type="text/javascript">
	jQuery(document).ready(function () {
		jQuery('#icl_troubleshooting_more_options').submit(iclSaveForm);
	})
</script>
<br clear="all"/><br/>

<?php if (SitePress_Setup::setup_complete() && (!defined( 'ICL_DONT_PROMOTE' ) || !ICL_DONT_PROMOTE) ){ ?>

	<div class="icl_cyan_box">
		<h3><?php _e( 'More options', 'sitepress' ) ?></h3>

		<form name="icl_troubleshooting_more_options" id="icl_troubleshooting_more_options" action="">
			<?php wp_nonce_field( 'icl_troubleshooting_more_options_nonce', '_icl_nonce' ); ?>
			<label><input type="checkbox" name="troubleshooting_options[raise_mysql_errors]" value="1" <?php
				if (!empty( $sitepress_settings[ 'troubleshooting_options' ][ 'raise_mysql_errors' ] )){ ?>checked="checked"<?php } ?>/>&nbsp;<?php
				_e( 'Raise mysql errors on XML-RPC calls', 'sitepress' )?></label>
			<br/>
			<label><input type="checkbox" name="troubleshooting_options[http_communication]" value="1" <?php
				if ($sitepress_settings[ 'troubleshooting_options' ][ 'http_communication' ]){ ?>checked="checked"<?php } ?>/>&nbsp;<?php
				_e( 'Communicate with ICanLocalize using HTTP instead of HTTPS', 'sitepress' )?></label>

			<p>
				<input class="button" name="save" value="<?php echo __( 'Apply', 'sitepress' ) ?>" type="submit"/>
				<span class="icl_ajx_response" id="icl_ajx_response"></span>
			</p>
		</form>
	</div>

	<br clear="all"/>
<?php } ?>
<br/>
<script type="text/javascript">
	function wpml_ts_link_post_type(select, old_value) {
		if (!select.val()) return;
		select.attr('disabled', 'disabled');
		select.after(icl_ajxloaderimg);
		jQuery.post(location.href + '&debug_action=link_post_type&nonce=<?php echo wp_create_nonce('link_post_type'); ?>&new_value=' + select.val() + '&old_value=' + old_value, function () {
			alert('<?php echo esc_js(__('Done', 'sitepress')) ?>');
			select.next().fadeOut();
			location.reload();
		});
	}

	function wpml_ts_link_taxonomy(select, old_value) {
		if (!select.val()) return;
		select.attr('disabled', 'disabled');
		select.after(icl_ajxloaderimg);
		jQuery.post(location.href + '&debug_action=link_taxonomy&nonce=<?php echo wp_create_nonce('link_taxonomy'); ?>&new_value=' + select.val() + '&old_value=' + old_value, function () {
			alert('<?php echo esc_js(__('Done', 'sitepress')) ?>');
			select.next().fadeOut();
			location.reload();
		});
	}

	function parse_xhr_error(xhr, status, error) {
		return xhr.statusText || status || error;
	}

	jQuery(document).ready(function () {
		jQuery('#icl_fix_languages').click(function () {
			jQuery(this).attr('disabled', 'disabled');
			jQuery(this).after(icl_ajxloaderimg);


			var icl_fix_languages = jQuery('#icl_fix_languages');

			jQuery.ajax({
				type: 'POST',
				contentType: "application/json; charset=utf-8",
				url: location.href + '&debug_action=fix_languages&nonce=<?php echo wp_create_nonce('fix_languages'); ?>',
				timeout: 60000,
				success: function () {
					icl_fix_languages.removeAttr('disabled');
					alert('<?php echo esc_js(__('Done', 'sitepress')) ?>');
					icl_fix_languages.next().fadeOut();
					location.reload();
				},
				error: function (jqXHR, status, error) {
					var parsed_response = parse_xhr_error(jqXHR, status, error);

					<?php
					$timeout_message = 'The operation timed out, but languages may still get fixed in the background.\n';
					$timeout_message .= 'Please wait 5-10 minutes, then refresh or come back to this page.\n';
					$timeout_message .= 'If languages are still not fixed, please retry or contact the WPML support.'
					?>

					if(parsed_response=='timeout') {
						alert('<?php echo __($timeout_message, 'sitepress');?>');
					} else {
						alert(parsed_response);
					}
					icl_fix_languages.next().fadeOut();
				}
			});
		});

		jQuery('#icl_remove_ghost').click(function () {
			jQuery(this).attr('disabled', 'disabled');
			jQuery(this).after(icl_ajxloaderimg);
			jQuery.post(location.href + '&debug_action=ghost_clean&nonce=<?php echo wp_create_nonce('ghost_clean'); ?>', function () {
				jQuery('#icl_remove_ghost').removeAttr('disabled');
				alert('<?php echo esc_js(__('Done', 'sitepress')) ?>');
				jQuery('#icl_remove_ghost').next().fadeOut();

			});
		})
		jQuery('#icl_sync_jobs').click(function () {
			jQuery(this).attr('disabled', 'disabled');
			jQuery(this).after(icl_ajxloaderimg);
			jQuery.post(location.href + '&debug_action=icl_sync_jobs&nonce=<?php echo wp_create_nonce('icl_sync_jobs'); ?>', function () {
				jQuery('#icl_sync_jobs').removeAttr('disabled');
				alert('<?php echo esc_js(__('Done', 'sitepress')) ?>');
				jQuery('#icl_sync_jobs').next().fadeOut();

			});
		})
		jQuery('#icl_cleanup').click(function () {
			jQuery(this).attr('disabled', 'disabled');
			jQuery(this).after(icl_ajxloaderimg);

			jQuery.post(location.href + '&debug_action=icl_cleanup&nonce=<?php echo wp_create_nonce('icl_cleanup'); ?>', function () {
				jQuery('#icl_cleanup').removeAttr('disabled');
				alert('<?php echo esc_js(__('Done', 'sitepress')) ?>');
				jQuery('#icl_cleanup').next().fadeOut();

			});
		});

		// #assign_translation_status_to_duplicates_resp: BEGIN
		var assign_translation_status_to_duplicates_loader = jQuery(icl_ajxloaderimg);
		var assign_translation_status_to_duplicates_cycles = 0;
		var assign_translation_status_to_duplicates_updated = 0;
		var response_element = jQuery('#assign_translation_status_to_duplicates_resp');
		var assign_translation_status_to_duplicates_element = jQuery('#assign_translation_status_to_duplicates');
		assign_translation_status_to_duplicates_element.click(function () {
			assign_translation_status_to_duplicates();
		});

		function assign_translation_status_to_duplicates() {

			if (assign_translation_status_to_duplicates_cycles == 0) {
				assign_translation_status_to_duplicates_element.attr('disabled', 'disabled');
				response_element.text('');
				response_element.show();
				assign_translation_status_to_duplicates_element.after(assign_translation_status_to_duplicates_loader);

			}
			assign_translation_status_to_duplicates_cycles++;

			jQuery.ajax({
				type: 'POST',
				contentType: "application/json; charset=utf-8",
				url: location.href + '&debug_action=assign_translation_status_to_duplicates&nonce=<?php echo wp_create_nonce('assign_translation_status_to_duplicates'); ?>',
				dataType: 'json',
				success: function (msg) {
					assign_translation_status_to_duplicates_updated += msg.updated;
					var response_message;
					if (msg.updated > 0) {
						response_message = assign_translation_status_to_duplicates_updated + ' <?php echo esc_js(_x('translation jobs updated', 'Sets the translation status to DUPLICATE in the icl_translation_status table, for posts that are marked as duplicated', 'sitepress')); ?>';

						if (assign_translation_status_to_duplicates_cycles >= 50) {
							response_message += '. <?php echo esc_js(_x('Partially done.', 'Sets the translation status to DUPLICATE in the icl_translation_status table, for posts that are marked as duplicated','sitepress')) ?>';
							response_message += '. <?php echo esc_js(_x('There might be more content to fix: please repeat the process.', 'Sets the translation status to DUPLICATE in the icl_translation_status table, for posts that are marked as duplicated','sitepress')) ?>';
							response_element.text(response_message);
							alert('<?php echo esc_js(_x('Partially done', 'Sets the translation status to DUPLICATE in the icl_translation_status table, for posts that are marked as duplicated', 'sitepress')) ?>');
							response_element.fadeOut();
							assign_translation_status_to_duplicates_loader.fadeOut(function() {
								assign_translation_status_to_duplicates_element.remove(assign_translation_status_to_duplicates_loader);
							});
							assign_translation_status_to_duplicates_element.removeAttr('disabled');

							//Reset counters
							assign_translation_status_to_duplicates_cycles = 0;
							assign_translation_status_to_duplicates_updated = 0;
						} else {
							response_message += ' ...';
							response_element.text(response_message);
							assign_translation_status_to_duplicates();
						}
					} else {
						response_message = '';
						if (assign_translation_status_to_duplicates_updated != 0) {
							response_message += assign_translation_status_to_duplicates_updated + '.';
						}
						response_message += '<?php echo esc_js(__('Done', 'sitepress')) ?>';
						response_element.text(response_message);

						alert('<?php echo esc_js(__('Done', 'sitepress')) ?>');

						response_element.fadeOut();
						assign_translation_status_to_duplicates_loader.fadeOut(function() {
							assign_translation_status_to_duplicates_element.remove(assign_translation_status_to_duplicates_loader);
						});
						assign_translation_status_to_duplicates_element.removeAttr('disabled');
					}
				},
				error: function (xhr, status, error) {
					var parsed_response = parse_xhr_error(xhr, status, error);
					response_element.text('');
					response_element.html(parsed_response);
					assign_translation_status_to_duplicates_loader.fadeOut(function() {
						assign_translation_status_to_duplicates_element.remove(assign_translation_status_to_duplicates_loader);
					});
					assign_translation_status_to_duplicates_element.attr('disabled', 'disabled');
				}
			});
		}

		// #assign_translation_status_to_duplicates_resp: END

		function _icl_sync_cms_id(offset) {
			jQuery('#icl_cms_id_fix_prgs_cnt').html(offset + 1);
			jQuery.ajax({
				type: "POST",
				url: location.href + '&debug_action=icl_cms_id_fix&nonce=<?php echo wp_create_nonce('icl_cms_id_fix'); ?>&offset=' + offset,
				data: 'debug_action=icl_cms_id_fix&nonce=<?php echo wp_create_nonce('icl_cms_id_fix'); ?>&offset=' + offset,
				dataType: 'json',
				success: function (msg) {
					if (msg.errors > 0) {
						alert(msg.message);
						jQuery('#icl_cms_id_fix').removeAttr('disabled');
						jQuery('#icl_cms_id_fix').next().fadeOut();
						jQuery('#icl_cms_id_fix_prgs').fadeOut();
					} else {
						offset++;
						if (msg.cont) {
							_icl_sync_cms_id(offset);
						} else {
							alert(msg.message);
							jQuery('#icl_cms_id_fix').removeAttr('disabled');
							jQuery('#icl_cms_id_fix').next().fadeOut();
							jQuery('#icl_cms_id_fix_prgs').fadeOut();
						}
					}
				}
			});
		}

		jQuery('#icl_cms_id_fix').click(function () {
			jQuery(this).attr('disabled', 'disabled');
			jQuery(this).after(icl_ajxloaderimg);
			jQuery('#icl_cms_id_fix_prgs').fadeIn();
			_icl_sync_cms_id(0);
		})

		jQuery('#icl_sync_cancelled').click(function () {
			jQuery(this).attr('disabled', 'disabled');
			jQuery(this).after(icl_ajxloaderimg);
			jQuery('#icl_sync_cancelled_resp').html('');
			jQuery.ajax({
				type: "POST",
				url: location.href.replace(/#/, '') + '&debug_action=sync_cancelled&nonce=<?php echo wp_create_nonce('sync_cancelled'); ?>',
				data: 'debug_action=sync_cancelled&nonce=<?php echo wp_create_nonce('sync_cancelled'); ?>',
				dataType: 'json',
				success: function (msg) {
					if (msg.errors > 0) {
						jQuery('#icl_sync_cancelled_resp').html(msg.message);
					} else {
						jQuery('#icl_sync_cancelled_resp').html(msg.message);
						if (msg.data) {
							jQuery('#icl_ts_t2c').val(msg.data.t2c);
						}
					}
					jQuery('#icl_sync_cancelled').removeAttr('disabled');
					jQuery('#icl_sync_cancelled').next().fadeOut();
				}
			});
		});

		jQuery(document).delegate('#icl_ts_cancel_cancel', 'click', function () {
			jQuery('#icl_sync_cancelled_resp').html('');
			return false;
		});

		jQuery(document).delegate('#icl_ts_cancel_ok', 'click', function () {
			jQuery(this).attr('disabled', 'disabled');
			jQuery(this).after(icl_ajxloaderimg);
			jQuery.ajax({
				type: "POST",
				url: location.href.replace(/#/, '') + '&debug_action=sync_cancelled_do_delete&nonce=<?php echo wp_create_nonce('sync_cancelled_do_delete'); ?>',
				data: 'debug_action=sync_cancelled_do_delete&nonce=<?php echo wp_create_nonce('sync_cancelled_do_delete'); ?>&t2c=' + jQuery('#icl_ts_t2c').val(),
				dataType: 'json',
				success: function (msg) {
					if (msg.errors > 0) {
						jQuery('#icl_sync_cancelled_resp').html(msg.message);
					} else {
						alert('Done');
						jQuery('#icl_sync_cancelled_resp').html('');
					}
					jQuery('#icl_ts_cancel_ok').removeAttr('disabled');
					jQuery('#icl_ts_cancel_ok').next().fadeOut();
				}
			});
			return false;
		});

		jQuery('#icl_add_missing_lang').click(function () {
			jQuery(this).attr('disabled', 'disabled');
			jQuery(this).after(icl_ajxloaderimg);
			jQuery.post(location.href + '&debug_action=icl_ts_add_missing_language&nonce=<?php echo wp_create_nonce('icl_ts_add_missing_language'); ?>', function () {
				jQuery('#icl_add_missing_lang').removeAttr('disabled');
				alert('<?php echo esc_js(__('Done', 'sitepress')) ?>');
				jQuery('#icl_add_missing_lang').next().fadeOut();

			});
		});

		jQuery('#icl_fix_terms_count').click(function () {
			jQuery(this).attr('disabled', 'disabled');
			jQuery(this).after(icl_ajxloaderimg);
			jQuery.post(location.href + '&debug_action=icl_fix_terms_count&nonce=<?php echo wp_create_nonce('icl_fix_terms_count'); ?>', function () {
				jQuery('#icl_fix_terms_count').removeAttr('disabled');
				alert('<?php echo esc_js(__('Done', 'sitepress')) ?>');
				jQuery('#icl_fix_terms_count').next().fadeOut();

			});
		});
	})
</script>
<div class="icl_cyan_box">
	<h3><?php _e( 'Clean up', 'sitepress' ) ?></h3>

	<p class="icl_form_errors" style="padding:6px;"><?php _e( 'Please make backup of your database before using this.', 'sitepress' ) ?></p>

	<?php if ( !SitePress_Setup::languages_complete() ){ ?>
		<p>
            <br />
            <label><input type="checkbox" onchange="if(jQuery(this).prop('checked')){jQuery('#icl_fix_languages').prop('disabled', false);}else{jQuery('#icl_fix_languages').prop('disabled', true);}">
                &nbsp;<?php _e("This operation will reset WPML's language tables and reinstall it. Any custom languages that you added will be removed.", 'sitepress') ?></label><br /><br />
			<input disabled="disabled" id="icl_fix_languages" type="button" class="button-secondary" value="<?php _e( 'Clear language information and repopulate languages', 'sitepress' ) ?>"/><br/><br />
			<small style="margin-left:10px;"><?php _e( "This operation will remove WPML's language table and recreate it. You should use it if you just installed WPML and you're not seeing a complete list of avaialble languages.", 'sitepress' ) ?></small>
            <br /><br />
		</p>
	<?php } ?>

	<?php if(SitePress_Setup::setup_complete()) { ?>
		<?php do_action('before_setup_complete_troubleshooting_functions'); ?>
	<p>
		<input id="icl_remove_ghost" type="button" class="button-secondary" value="<?php _e( 'Remove ghost entries from the translation tables', 'sitepress' ) ?>"/><br/>
		<small style="margin-left:10px;"><?php _e( 'Removes entries from the WPML tables that are not linked properly. Cleans the table off entries left over upgrades, bug fixes or undetermined factors.', 'sitepress' ) ?></small>
	</p>
	<?php if ( $sitepress->get_setting('site_id') && $sitepress->get_setting('access_key') && $sitepress->get_setting('site_id') && $sitepress->get_setting('access_key') ){ ?>
		<p>
			<input id="icl_sync_jobs" type="button" class="button-secondary" value="<?php _e( 'Synchronize translation jobs with ICanLocalize', 'sitepress' ) ?>"/><br/>
			<small style="margin-left:10px;"><?php _e( 'Fixes links between translation entries in the database and ICanLocalize.', 'sitepress' ) ?></small>
		</p>
		<p>
			<input id="icl_cms_id_fix" type="button" class="button-secondary" value="<?php _e( 'CMS ID fix', 'sitepress' ) ?>"/>
			<span id="icl_cms_id_fix_prgs"
				  style="display: none;"><?php printf( __( 'fixing %s/%d', 'sitepress' ), '<span id="icl_cms_id_fix_prgs_cnt">0</span>', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations t JOIN {$wpdb->prefix}icl_translation_status s ON t.translation_id=s.translation_id WHERE t.element_type LIKE 'post\\_%' AND t.source_language_code IS NOT NULL AND s.translation_service='icanlocalize'" ) ) ?></span><br/>
			<small
				style="margin-left:10px;"><?php _e( "Updates translation in progress with new style identifiers for documents. The new identifiers depend on the document being translated and the languages so it's not possible to get out of sync when translations are being deleted locally.", 'sitepress' ) ?></small>
		</p>
	<?php } ?>
	<p>
		<input id="icl_cleanup" type="button" class="button-secondary" value="<?php _e( 'General clean up', 'sitepress' ) ?>"/><br/>
		<small style="margin-left:10px;"><?php _e( 'Sets source language to NULL in the icl_translations table.', 'sitepress' ) ?> </small>
	</p>

	<?php if(class_exists('TranslationManagement')){ ?>
	<p>
		<input id="assign_translation_status_to_duplicates" type="button" class="button-secondary" value="<?php _e( 'Assign translation status to duplicated content', 'sitepress' ) ?>"/><span id="assign_translation_status_to_duplicates_resp"></span><br/>
		<small style="margin-left:10px;"><?php _e( 'Sets the translation status to DUPLICATE in the icl_translation_status table, for posts that are marked as duplicated.', 'sitepress' ) ?> </small>
	</p>
	<?php } ?>

	<?php if ( $sitepress->get_setting('site_id') && $sitepress->get_setting('access_key') && $sitepress->get_setting('site_id') && $sitepress->get_setting('access_key') ){ ?>
		<p>
			<input id="icl_sync_cancelled" type="button" class="button-secondary" value="<?php _e( 'Check cancelled jobs on ICanLocalize', 'sitepress' ) ?>"/><br/>
			<small style="margin-left:10px;"><?php _e( 'When using the translation pickup mode cancelled jobs on ICanLocalize need to be synced manually.', 'sitepress' ) ?></small>
		</p>
		<span id="icl_sync_cancelled_resp"></span>
		<input type="hidden" id="icl_ts_t2c" value=""/>
	<?php } ?>
	<p>
		<input id="icl_add_missing_lang" type="button" class="button-secondary" value="<?php _e( 'Set language information', 'sitepress' ) ?>"/><br/>
		<small style="margin-left:10px;"><?php _e( 'Adds language information to posts and taxonomies that are missing this information.', 'sitepress' ) ?></small>
	</p>
	<p>
		<input id="icl_fix_terms_count" type="button" class="button-secondary" value="<?php _e( 'Fix terms count', 'sitepress' ) ?>"/><br/>
		<small style="margin-left:10px;"><?php _e( 'Correct terms count in case something went wrong with translated contents.', 'sitepress' ) ?></small>
	</p>

	<p>
		<br/>
		<?php _e( 'Translatable custom posts linking', 'sitepress' ); ?><br/>
		<small style="margin-left:10px;"><?php _e( 'Allows linking existing translations after changing custom posts definition (name) ', 'sitepress' ) ?></small>

		<?php
		$translatable_posts = $sitepress->get_translatable_documents();
		$res = $wpdb->get_col( "SELECT DISTINCT element_type FROM {$wpdb->prefix}icl_translations WHERE element_type LIKE 'post\\_%'" );
		echo '<table class="widefat" style="width:300px;">';

		foreach ( $res as $row ) {

			$post_type = preg_replace( '#^post_#', '', $row );
			if ( $post_type == 'nav_menu_item' )
				continue;

			echo '<tr>';
			echo '<td>' . $post_type . '</td>';

			if ( isset( $translatable_posts[ $post_type ] ) ) {

				echo '<td>' . __( 'linked to: ', 'sitepress' ) . $translatable_posts[ $post_type ]->labels->name . '</td>';

			} else {
				echo '<td>';
				echo '<select onchange="wpml_ts_link_post_type(jQuery(this), \'' . $post_type . '\')">';
				echo '<option value="">' . __( '--select--', 'sitepress' ) . '</option>';
				foreach ( $translatable_posts as $name => $type ) {
					echo '<option value="' . $name . '">' . $type->labels->name . '(' . $name . ')' . '</option>';
				}
				echo '</select>';
				echo '</td>';
			}
			echo '</tr>';
		}

		echo '</table>';
		echo '<br />';
		echo __( 'Note: if you edited the custom post declaration you may need to re-configure WPML to mark it as translatable.', 'sitepress' );

		?>
	</p>

	<p>
		<br/>
		<?php _e( 'Translatable taxonomies linking', 'sitepress' ) ?><br/>
		<small style="margin-left:10px;"><?php _e( 'Allows linking existing translations after changing custom taxonomies definition (name) ', 'sitepress' ) ?></small>

		<?php
		$translatable_taxs = array();
		foreach ( $wp_post_types as $name => $post_type ) {
			$translatable_taxs = array_merge( $translatable_taxs, $sitepress->get_translatable_taxonomies( true, $name ) );
		}
		$translatable_taxs = array_unique( $translatable_taxs );

		$res = $wpdb->get_col( "SELECT DISTINCT element_type FROM {$wpdb->prefix}icl_translations WHERE element_type LIKE 'tax\\_%'" );
		echo '<table class="widefat" style="width:300px;">';

		foreach ( $res as $row ) {

			$tax = preg_replace( '#^tax_#', '', $row );
			if ( $tax == 'link_category' || $tax == 'nav_menu' )
				continue;

			echo '<tr>';

			echo '<td>' . $tax . '</td>';

			if ( in_array( $tax, $translatable_taxs ) ) {

				echo '<td>' . __( 'linked to: ', 'sitepress' ) . $wp_taxonomies[ $tax ]->labels->name . '</td>';

			} else {
				echo '<td>';
				echo '<select onchange="wpml_ts_link_taxonomy(jQuery(this), \'' . $tax . '\')">';
				echo '<option value="">' . __( '--select--', 'sitepress' ) . '</option>';
				foreach ( $translatable_taxs as $name ) {

					echo '<option value="' . $name . '">' . $wp_taxonomies[ $name ]->labels->name . '(' . $name . ')' . '</option>';
				}
				echo '</select>';
				echo '</td>';
			}
			echo '</tr>';
		}

		echo '</table>';
		echo '<br />';
		echo __( 'Note: if you edited the custom taxonomy declaration you may need to re-configure WPML to mark it as translatable.', 'sitepress' );

		?>
	</p>

	<?php do_action('after_setup_complete_troubleshooting_functions'); ?>

	<?php } ?>

</div>

<br clear="all"/>
<?php if (SitePress_Setup::setup_complete() && (!defined( 'ICL_DONT_PROMOTE' ) || !ICL_DONT_PROMOTE) ){ ?>
	<br/>
	<div class="icl_cyan_box">
		<h3><?php _e( 'Reset PRO translation configuration', 'sitepress' ) ?></h3>

		<div
			class="icl_form_errors"><?php _e( "Resetting your ICanLocalize account will interrupt any translation jobs that you have in progress. Only use this function if your ICanLocalize account doesn't include any jobs, or if the account was deleted.", 'sitepress' ); ?></div>
		<p style="padding:6px;"><label><input onchange="if(jQuery(this).attr('checked')) jQuery('#icl_reset_pro_but').removeClass('button-primary-disabled'); else jQuery('#icl_reset_pro_but').addClass('button-primary-disabled');"
											  id="icl_reset_pro_check" type="checkbox" value="1"/>&nbsp;<?php echo _e( 'I am about to reset the ICanLocalize project setting.', 'sitepress' ); ?></label></p>

		<a id="icl_reset_pro_but" onclick="if(!jQuery('#icl_reset_pro_check').attr('checked') || !confirm('<?php echo esc_js( __( 'Are you sure you want to reset the PRO translation configuration?', 'sitepress' ) ) ?>')) return false;"
		   href="admin.php?page=<?php echo basename( ICL_PLUGIN_PATH ) ?>/menu/troubleshooting.php&amp;debug_action=reset_pro_translation_configuration&amp;nonce=<?php echo wp_create_nonce( 'reset_pro_translation_configuration' ) ?>"
		   class="button-primary button-primary-disabled"><?php _e( 'Reset PRO translation configuration', 'sitepress' ); ?></a>

	</div>

	<br clear="all"/>
<?php } ?>


<br clear="all"/>
<?php if ( !defined( 'ICL_DONT_PROMOTE' ) || !ICL_DONT_PROMOTE ){ ?>
	<br/>
	<div class="icl_cyan_box">
		<a name="icl-connection-test"></a>

		<h3><?php _e( 'ICanLocalize connection test', 'sitepress' ) ?></h3>
		<?php if ( isset( $_GET[ 'icl_action' ] ) && $_GET[ 'icl_action' ] == 'icl-connection-test' ){ ?>
			<?php
			$icl_query = new ICanLocalizeQuery();
			if ( isset( $_GET[ 'data' ] ) ) {
				$user = unserialize( base64_decode( $_GET[ 'data' ] ) );
			} else {
				$user[ 'create_account' ] = 1;
				$user[ 'anon' ]           = 1;
				$user[ 'platform_kind' ]  = 2;
				$user[ 'cms_kind' ]       = 1;
				$user[ 'blogid' ]         = $wpdb->blogid ? $wpdb->blogid : 1;
				$user[ 'url' ]            = get_option( 'siteurl' );
				$user[ 'title' ]          = get_option( 'blogname' );
				$user[ 'description' ]    = $sitepress->get_setting('icl_site_description') ? $sitepress_settings[ 'icl_site_description' ] : '';
				$user[ 'is_verified' ]    = 1;
				if ( defined( 'ICL_AFFILIATE_ID' ) && defined( 'ICL_AFFILIATE_KEY' ) ) {
					$user[ 'affiliate_id' ]  = ICL_AFFILIATE_ID;
					$user[ 'affiliate_key' ] = ICL_AFFILIATE_KEY;
				}
				$user[ 'interview_translators' ] = $sitepress_settings[ 'interview_translators' ];
				$user[ 'project_kind' ]          = 2;
				$user[ 'pickup_type' ]           = intval( $sitepress_settings[ 'translation_pickup_method' ] );
				$notifications                   = 0;
				if ( $sitepress->get_setting('icl_notify_complete') ) {
					$notifications += 1;
				}
				if ( $sitepress_settings[ 'alert_delay' ] ) {
					$notifications += 2;
				}
				$user[ 'notifications' ]    = $notifications;
				$user[ 'ignore_languages' ] = 0;
				$user[ 'from_language1' ]   = isset( $_GET[ 'lang_from' ] ) ? $_GET[ 'lang_from' ] : 'English';
				$user[ 'to_language1' ]     = isset( $_GET[ 'lang_to' ] ) ? $_GET[ 'lang_to' ] : 'French';
			}

			define( 'ICL_DEB_SHOW_ICL_RAW_RESPONSE', true );
			$resp = $icl_query->createAccount( $user );
			echo '<textarea style="width:100%;height:400px;font-size:9px;">';
			if ( defined( 'ICL_API_ENDPOINT' ) ) {
				echo ICL_API_ENDPOINT . "\r\n\r\n";
			}
			echo __( 'Data', 'sitepress' ) . "\n----------------------------------------\n" .
				print_r( $user, 1 ) .
				__( 'Response', 'sitepress' ) . "\n----------------------------------------\n" .
				print_r( $resp, 1 ) .
				'</textarea>';

			?>

		<?php } ?>
		<a class="button" href="admin.php?page=<?php echo ICL_PLUGIN_FOLDER ?>/menu/troubleshooting.php&ts=<?php echo time() ?>&icl_action=icl-connection-test#icl-connection-test"><?php _e( 'Connect', 'sitepress' ) ?></a>
	</div>
	<br clear="all"/>
<?php } ?>


<br/>

<div class="icl_cyan_box">

	<?php
	echo '<h3 id="wpml-settings"> ' . __( 'Reset', 'sitepress' ) . '</h3>';
	?>

	<?php if ( function_exists( 'is_multisite' ) && is_multisite() ){ ?>

		<p><?php _e( 'This function is available through the Network Admin section.', 'sitepress' ); ?></p>
		<?php if ( current_user_can( 'manage_sites' ) ){ ?>
			<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/network.php' ) ) ?>"><?php _e( 'Go to WPML Network settings.', 'sitepress' ) ?></a>
		<?php } else { ?>
			<i><?php _e( 'You are not allowed to manage the WPML Network settings.', 'sitepress' ) ?></i>
		<?php } ?>

	<?php } else { ?>


		<?php
		echo '<form method="post" onsubmit="return confirm(\'' . __( 'Are you sure you want to reset all languages data? This operation cannot be reversed.', 'sitepress' ) . '\')">';
		wp_nonce_field( 'icl_reset_all', 'icl_reset_allnonce' );
		echo '<p class="error" style="padding:6px;">' . __( "All translations you have sent to ICanLocalize will be lost if you reset WPML's data. They cannot be recovered later.", 'sitepress' )
			. '</p>';
		echo '<label><input type="checkbox" name="icl-reset-all" ';
		if ( !function_exists( 'is_super_admin' ) || is_super_admin() ) {
			echo 'onchange="if(this.checked) jQuery(\'#reset-all-but\').removeAttr(\'disabled\'); else  jQuery(\'#reset-all-but\').attr(\'disabled\',\'disabled\');"';
		}
		echo ' /> ' . __( 'I am about to reset all language data.', 'sitepress' ) . '</label><br /><br />';

		echo '<input id="reset-all-but" type="submit" disabled="disabled" class="button-primary" value="' . __( 'Reset all language data and deactivate WPML', 'sitepress' ) . '" />';
		echo '</form>';
		?>

	<?php } ?>

</div>

<?php do_action( 'icl_menu_footer' ); ?>
</div>
