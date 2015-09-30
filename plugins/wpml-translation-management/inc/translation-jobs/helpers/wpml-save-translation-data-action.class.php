<?php

class WPML_Save_Translation_Data_Action extends WPML_Translation_Job_Helper_With_API {

	/** @var  array $data */
	private $data;

	private $redirect_target = false;

	public function __construct( $data ) {
		parent::__construct();
		$this->data = $data;
	}

	function save_translation() {
		global $wpdb, $sitepress, $ICL_Pro_Translation, $iclTranslationManagement, $wpml_post_translations;

		$new_post_id         = false;
		$is_incomplete       = false;
		$data                = $this->data;
		/** @var stdClass $job */
		$job                 = ! empty( $data['job_id'] ) ? $this->get_translation_job( $data['job_id'], true ) : null;
		$original_post       = null;
		$element_type_prefix = null;
		if ( is_object( $job ) ) {
			$element_type_prefix = $iclTranslationManagement->get_element_type_prefix_from_job( $job );
			$original_post       = $iclTranslationManagement->get_post( $job->original_doc_id, $element_type_prefix );
		}

		$is_external      = apply_filters( 'wpml_is_external', false, $element_type_prefix );
		$data_to_validate = array(
			'original_post' => $original_post,
			'type_prefix'   => $element_type_prefix,
			'data'          => $data,
			'is_external'   => $is_external
		);

		$validation_results = $this->get_validation_results( $job, $data_to_validate );

		if ( ! $validation_results['is_valid'] ) {
			$this->handle_failed_validation( $validation_results, $data_to_validate );
			$res = false;
		} else {
			foreach ( $data['fields'] as $fieldname => $field ) {
				if ( substr( $fieldname, 0, 6 ) === 'field-' ) {
					$field = apply_filters( 'wpml_tm_save_translation_cf', $field, $fieldname, $data );
				}
				$this->save_translation_field( $field['tid'], $field );
				if ( ! isset( $field['finished'] ) || ! $field['finished'] ) {
					$is_incomplete = true;
				}
			}

			$rid            = $wpdb->get_var( $wpdb->prepare( "SELECT rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $data['job_id'] ) );
			$translation_id = $wpdb->get_var( $wpdb->prepare( "SELECT translation_id FROM {$wpdb->prefix}icl_translation_status WHERE rid=%d", $rid ) );
			if ( ( $is_incomplete === true || empty( $data['complete'] ) ) && empty( $data['resign'] ) ) {
				$status_update = array( 'translation_id' => $translation_id, 'status' => ICL_TM_IN_PROGRESS );
				$iclTranslationManagement->update_translation_status( $status_update );
				$wpdb->update( $wpdb->prefix . 'icl_translate_job', array( 'translated' => 0 ), array( 'job_id' => $data['job_id'] ) );
			}

			if ( ! empty( $data['complete'] ) && ! $is_incomplete ) {
				$wpdb->update( $wpdb->prefix . 'icl_translate_job', array( 'translated' => 1 ), array( 'job_id' => $data['job_id'] ) );
				$wpdb->update( $wpdb->prefix . 'icl_translation_status', array(
					'status'       => ICL_TM_COMPLETE,
					'needs_update' => 0
				), array( 'rid' => $rid ) );
				list( $element_id, $trid ) = $wpdb->get_row( $wpdb->prepare( "SELECT element_id, trid FROM {$wpdb->prefix}icl_translations WHERE translation_id=%d", $translation_id ), ARRAY_N );
				$job = $this->get_translation_job( $data['job_id'], true );

				if ( $is_external ) {
					$this->save_external( $element_type_prefix, $job );
				} else {
					if ( ! is_null( $element_id ) ) {
						$postarr['ID'] = $_POST['post_ID'] = $element_id;
					}
					foreach ( $job->elements as $field ) {
						switch ( $field->field_type ) {
							case 'title':
								$postarr['post_title'] = $this->decode_field_data( $field->field_data_translated, $field->field_format );
								break;
							case 'body':
								$postarr['post_content'] = $this->decode_field_data( $field->field_data_translated, $field->field_format );
								break;
							case 'excerpt':
								$postarr['post_excerpt'] = $this->decode_field_data( $field->field_data_translated, $field->field_format );
								break;
							case 'URL':
								$postarr['post_name'] = $this->decode_field_data( $field->field_data_translated, $field->field_format );
								break;
							default:
								break;
						}
					}

					$postarr['post_author'] = $original_post->post_author;
					$postarr['post_type']   = $original_post->post_type;

					if ( $sitepress->get_setting( 'sync_comment_status' ) ) {
						$postarr['comment_status'] = $original_post->comment_status;
					}
					if ( $sitepress->get_setting( 'sync_ping_status' ) ) {
						$postarr['ping_status'] = $original_post->ping_status;
					}
					if ( $sitepress->get_setting( 'sync_page_ordering' ) ) {
						$postarr['menu_order'] = $original_post->menu_order;
					}
					if ( $sitepress->get_setting( 'sync_private_flag' ) && $original_post->post_status == 'private' ) {
						$postarr['post_status'] = 'private';
					}
					if ( $sitepress->get_setting( 'sync_post_date' ) ) {
						$postarr['post_date'] = $original_post->post_date;
					}

					//set as draft or the same status as original post
					$postarr['post_status'] = ! $sitepress->get_setting( 'translated_document_status' ) ? 'draft' : $original_post->post_status;

					if ( $original_post->post_parent ) {
						$parent_id = $wpml_post_translations->element_id_in( $original_post->post_parent, $job->language_code );
					}

					if ( isset( $parent_id ) && $sitepress->get_setting( 'sync_page_parent' ) ) {
						$_POST['post_parent'] = $postarr['post_parent'] = $parent_id;
						$_POST['parent_id']   = $postarr['parent_id'] = $parent_id;
					}

					$_POST['trid']                   = $trid;
					$_POST['lang']                   = $job->language_code;
					$_POST['skip_sitepress_actions'] = true;

					$postarr = apply_filters( 'icl_pre_save_pro_translation', $postarr );

					// it's an update and user do not want to translate urls so do not change the url
					if ( isset( $element_id ) && $sitepress->get_setting( 'translated_document_page_url' ) !== 'translate' ) {
						$postarr['post_name'] = $wpdb->get_var( $wpdb->prepare( "SELECT post_name
																				 FROM {$wpdb->posts}
																			     WHERE ID=%d
																			     LIMIT 1",
							$element_id ) );
					}

					if ( isset( $element_id ) ) { // it's an update so dont change post date
						$existing_post            = get_post( $element_id );
						$postarr['post_date']     = $existing_post->post_date;
						$postarr['post_date_gmt'] = $existing_post->post_date_gmt;
					}

					$new_post_id = $iclTranslationManagement->icl_insert_post( $postarr, $job->language_code );
					icl_cache_clear( $postarr['post_type'] . 's_per_language' ); // clear post counter per language in cache

					// set taxonomies for users with limited caps
					if ( ! current_user_can( 'manage-categories' ) && ! empty( $postarr['tax_input'] ) ) {
						foreach ( $postarr['tax_input'] as $taxonomy => $terms ) {
							wp_set_post_terms( $new_post_id, $terms, $taxonomy, false ); // true to append to existing tags | false to replace existing tags
						}
					}

					do_action( 'icl_pro_translation_saved', $new_post_id, $data['fields'] );

					if ( $ICL_Pro_Translation ) {
						/** @var WPML_Pro_Translation $ICL_Pro_Translation */
						$ICL_Pro_Translation->_content_fix_links_to_translated_content( $new_post_id, $job->language_code );
					}

					// update body translation with the links fixed
					$new_post_content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $new_post_id ) );
					foreach ( $job->elements as $jel ) {
						if ( $jel->field_type === 'body' ) {
							$fields_data_translated = $this->encode_field_data( $new_post_content, $jel->field_format );
							$wpdb->update( $wpdb->prefix . 'icl_translate', array( 'field_data_translated' => $fields_data_translated ), array(
								'job_id'     => $data['job_id'],
								'field_type' => 'body'
							) );

							break;
						}
					}

					// set stickiness
					//is the original post a sticky post?
					$sticky_posts       = get_option( 'sticky_posts' );
					$is_original_sticky = $original_post->post_type == 'post' && in_array( $original_post->ID, $sticky_posts );

					if ( $is_original_sticky && $sitepress->get_setting( 'sync_sticky_flag' ) ) {
						stick_post( $new_post_id );
					} else {
						if ( $original_post->post_type == 'post' && ! is_null( $element_id ) ) {
							unstick_post( $new_post_id ); //just in case - if this is an update and the original post stckiness has changed since the post was sent to translation
						}
					}

					//sync plugins texts
					$cf_translation_settings = $this->get_tm_setting( array( 'custom_fields_translation' ) );
					foreach ( (array) $cf_translation_settings as $cf => $op ) {
						if ( $op == 1 ) {
							update_post_meta( $new_post_id, $cf, get_post_meta( $original_post->ID, $cf, true ) );
						}
					}

					// set specific custom fields
					$copied_custom_fields = array( '_top_nav_excluded', '_cms_nav_minihome' );
					foreach ( $copied_custom_fields as $ccf ) {
						$val = get_post_meta( $original_post->ID, $ccf, true );
						update_post_meta( $new_post_id, $ccf, $val );
					}

					// sync _wp_page_template
					if ( $sitepress->get_setting( 'sync_page_template' ) ) {
						$_wp_page_template = get_post_meta( $original_post->ID, '_wp_page_template', true );
						if ( ! empty( $_wp_page_template ) ) {
							update_post_meta( $new_post_id, '_wp_page_template', $_wp_page_template );
						}
					}

					// sync post format
					if ( $sitepress->get_setting( 'sync_post_format' ) ) {
						$_wp_post_format = get_post_format( $original_post->ID );
						set_post_format( $new_post_id, $_wp_post_format );
					}

					$this->package_helper->save_job_custom_fields( $job, $new_post_id, (array) $cf_translation_settings );

					$link = get_edit_post_link( $new_post_id );
					if ( $link == '' ) {
						// the current user can't edit so just include permalink
						$link = get_permalink( $new_post_id );
					}

					if ( is_null( $element_id ) ) {
						$wpdb->delete( $wpdb->prefix . 'icl_translations', array(
							'element_id'   => $new_post_id,
							'element_type' => 'post_' . $postarr['post_type']
						) );
						$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'element_id' => $new_post_id ), array( 'translation_id' => $translation_id ) );
						$user_message = __( 'Translation added: ', 'sitepress' ) . '<a href="' . $link . '">' . $postarr['post_title'] . '</a>.';
					} else {
						$user_message = __( 'Translation updated: ', 'sitepress' ) . '<a href="' . $link . '">' . $postarr['post_title'] . '</a>.';
					}

					$this->add_message( array(
						'type' => 'updated',
						'text' => $user_message
					) );
				}

				if ( $this->get_tm_setting( array( 'notification', 'completed' ) ) != ICL_TM_NOTIFICATION_NONE
				     && $data['job_id']
				) {
					do_action( 'wpml_tm_complete_job_notification', $data['job_id'], ! is_null( $element_id ) );
				}

				$iclTranslationManagement->set_page_url( $new_post_id );

				if ( isset( $job ) && isset( $job->language_code ) && isset( $job->source_language_code ) ) {
					$this->save_terms_for_job( $data['job_id'] );
				}

				do_action( 'icl_pro_translation_completed', $new_post_id );

				if ( ! defined( 'XMLRPC_REQUEST' ) && ! defined( 'DOING_AJAX' ) && ! isset( $_POST['xliff_upload'] ) ) {
					$action_type           = is_null( $element_id ) ? 'added' : 'updated';
					$element_id            = is_null( $element_id ) ? $new_post_id : $element_id;
					$this->redirect_target = admin_url( sprintf( 'admin.php?page=%s&%s=%d&element_type=%s', WPML_TM_FOLDER . '/menu/translations-queue.php', $action_type, $element_id, $element_type_prefix ) );
				}
			} else {
				$this->add_message( array(
					'type' => 'updated',
					'text' => __( 'Translation (incomplete) saved.', 'sitepress' )
				) );
			}

			$res = true;
		}

		return $res;
	}

	/**
	 * Returns false if after saving the translation no redirection is to happen or the target of the redirection
	 * in case saving the data is followed by a redirect.
	 *
	 * @return false|string
	 */
	function get_redirect_target() {

		return $this->redirect_target;
	}

	private function save_translation_field( $tid, $field ) {
		global $wpdb;

		$update = array();
		if ( isset( $field[ 'data' ] ) ) {
			$update[ 'field_data_translated' ] = $this->encode_field_data( $field[ 'data' ], $field[ 'format' ] );
		}
		if ( isset( $field[ 'finished' ] ) && $field[ 'finished' ] ) {
			$update[ 'field_finished' ] = 1;
		} else {
			$update[ 'field_finished' ] = 0;
		}
		if ( !empty( $update ) ) {
			$wpdb->update( $wpdb->prefix . 'icl_translate', $update, array( 'tid' => $tid ) );
		}
	}

	private function handle_failed_validation( $validation_results, $data_to_validate ) {
		if ( isset( $validation_results['messages'] ) ) {
			$messages = (array) $validation_results['messages'];
			if ( $messages ) {
				foreach ( $messages as $message ) {
					$this->add_message( array( 'type' => 'error', 'text' => $message ) );
				}
			} else {
				$this->add_message( array(
					'type' => 'error',
					'text' => __( 'Submitted data is not valid.', 'sitepress' )
				) );
			}
		}
		do_action( 'wpml_translation_validation_failed', $validation_results, $data_to_validate );
	}

	private function get_validation_results( $job, $data_to_validate ) {

		$is_valid                   = true;
		$original_post              = $data_to_validate['original_post'];
		$element_type_prefix        = $data_to_validate['type_prefix'];
		$validation_default_results = array( 'is_valid' => $is_valid, 'messages' => array() );
		if ( ! $job || ! $original_post || ! $element_type_prefix ) {
			$is_valid = false;
			if ( ! $job ) {
				$validation_default_results['messages'][] = __( 'Job ID is missing', 'sitepress' );
			}
			if ( ! $original_post ) {
				$validation_default_results['messages'][] = __( 'The original post cannot be retrieved', 'sitepress' );
			}
			if ( ! $element_type_prefix ) {
				$validation_default_results['messages'][] = __( 'The type of the post cannot be retrieved', 'sitepress' );
			}
		}
		$validation_default_results['is_valid'] = $is_valid;
		$validation_results                     = apply_filters( 'wpml_translation_validation_data', $validation_default_results, $data_to_validate );
		$validation_results                     = array_merge( $validation_results, $validation_default_results );

		if ( ! $is_valid && $validation_results['is_valid'] ) {
			$validation_results['is_valid'] = $is_valid;
		}

		return $validation_results;
	}

	private function save_terms_for_job( $job_id ) {
		require_once WPML_TM_PATH . '/inc/translation-jobs/wpml-translation-jobs-collection.class.php';

		$job = new WPML_Post_Translation_Job( $job_id );
		$job->save_terms_to_post();
	}

	private function add_message( $message ) {
		global $iclTranslationManagement;

		$iclTranslationManagement->add_message( $message );
	}

	/**
	 * @param string $element_type_prefix
	 * @param object $job
	 * todo: Move to ST via an action to make this testable
	 */
	private function save_external( $element_type_prefix, $job ) {

		// Translations are saved in the string table for 'external' types

		$element_type_prefix = apply_filters( 'wpml_get_package_type_prefix', $element_type_prefix, $job->original_doc_id );

		foreach ( $job->elements as $field ) {
			if ( $field->field_translate ) {
				if ( function_exists( 'icl_st_is_registered_string' ) ) {
					$string_id = icl_st_is_registered_string( $element_type_prefix, $field->field_type );
					if ( ! $string_id ) {
						icl_register_string( $element_type_prefix, $field->field_type, $this->decode_field_data( $field->field_data, $field->field_format ) );
						$string_id = icl_st_is_registered_string( $element_type_prefix, $field->field_type );
					}
					if ( $string_id ) {
						icl_add_string_translation( $string_id, $job->language_code, $this->decode_field_data( $field->field_data_translated, $field->field_format ), ICL_TM_COMPLETE );
					}
				}
			}
		}
	}
}
