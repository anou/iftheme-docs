<?php

class WPML_Post_Edit_Ajax {

	/**
	 * Ajax handler for adding a term via Ajax.
	 */
	public static function wpml_save_term() {
		if ( !wpml_is_action_authenticated ( 'wpml_save_term' ) ) {
			wp_send_json_error ( 'Wrong Nonce' );
		}

		global $sitepress;

		$lang        = filter_input ( INPUT_POST, 'term_language_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$taxonomy    = filter_input ( INPUT_POST, 'taxonomy' );
		$slug        = filter_input ( INPUT_POST, 'slug' );
		$name        = filter_input ( INPUT_POST, 'name' );
		$trid        = filter_input ( INPUT_POST, 'trid', FILTER_SANITIZE_NUMBER_INT );
		$description = filter_input ( INPUT_POST, 'description' );
		$new_term_object = false;

		if ( $name !== "" && $taxonomy && $trid && $lang ) {

			$args = array(
				'taxonomy'  => $taxonomy,
				'lang_code' => $lang,
				'term'      => $name,
				'trid'      => $trid,
				'overwrite' => true
			);

			if ( $slug ) {
				$args[ 'slug' ] = $slug;
			}
			if ( $description ) {
				$args[ 'description' ] = $description;
			}

			$res = WPML_Terms_Translations::create_new_term( $args );

			if ( $res && isset( $res[ 'term_taxonomy_id' ] ) ) {
				/* res holds the term taxonomy id, we return the whole term objects to the ajax call */
				$new_term_object                = get_term_by( 'term_taxonomy_id', (int) $res[ 'term_taxonomy_id' ], $taxonomy );
				$lang_details                   = $sitepress->get_element_language_details( $new_term_object->term_taxonomy_id, 'tax_' . $new_term_object->taxonomy );
				$new_term_object->trid          = $lang_details->trid;
				$new_term_object->language_code = $lang_details->language_code;

				WPML_Terms_Translations::icl_save_term_translation_action( $taxonomy, $res );
			}
		}

		wp_send_json_success( $new_term_object );
	}

	/**
	 * Gets the content of a post, its excerpt as well as its title and returns it as an array
	 *
	 * @param string $content_type
	 * @param string $excerpt_type
	 * @param int    $trid
	 * @param string $lang
	 *
	 * @return array containing all the fields information
	 */
	public static function copy_from_original_fields( $content_type, $excerpt_type, $trid, $lang ) {
		global $wpdb;
		$post_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND language_code=%s",
			                $trid,
			                $lang ) );
		$post    = get_post( $post_id );

		$fields_to_copy = array( 'content' => 'post_content',
								 'title'   => 'post_title',
								 'excerpt' => 'post_excerpt' );

		$fields_contents = array();
		if ( ! empty( $post ) ) {
			foreach ( $fields_to_copy as $editor_key => $editor_field ) { //loops over the three fields to be inserted into the array
				if ( $editor_key === 'content' || $editor_key === 'excerpt' ) { //
					$editor_var = 'rich';
					if ( $editor_key === 'content' ) {
						$editor_var = $content_type; //these variables are supplied by a javascript call in scripts.js icl_copy_from_original(lang, trid)
					} elseif ( $editor_key === 'excerpt' ) {
						$editor_var = $excerpt_type;
					}
					
					if ( function_exists( 'format_for_editor' ) ) {
						// WordPress 4.3 uses format_for_editor
						$html_pre = $post->$editor_field;
						if($editor_var == 'rich') {
							$html_pre = convert_chars( $html_pre );
							$html_pre = wpautop( $html_pre );
						}
						$html_pre = format_for_editor( $html_pre, $editor_var );
					} else {
						// Backwards compatible for WordPress < 4.3
						if ( $editor_var === 'rich' ) {
							$html_pre = wp_richedit_pre( $post->$editor_field );
						} else {
							$html_pre = wp_htmledit_pre( $post->$editor_field );
						}
					}
					
					$fields_contents[$editor_key] = htmlspecialchars_decode( $html_pre );
				} elseif ( $editor_key === 'title' ) {
					$fields_contents[ $editor_key ] = strip_tags( $post->$editor_field );
				}
			}
			$fields_contents[ 'customfields' ] = apply_filters( 'wpml_copy_from_original_custom_fields',
			                                                    self::copy_from_original_custom_fields( $post ) );
		} else {
			$fields_contents[ 'error' ] = __( 'Post not found', 'sitepress' );
		}
		do_action( 'icl_copy_from_original', $post_id );

		return $fields_contents;
	}

	/**
	 * Gets the content of a custom posts custom field , its excerpt as well as its title and returns it as an array
	 *
	 * @param  WP_post $post
	 *
	 * @return array
	 */
	public static function copy_from_original_custom_fields( $post ) {

		$elements                 = array();
		$elements [ 'post_type' ] = $post->post_type;
		$elements[ 'excerpt' ]    = array(
			'editor_name' => 'excerpt',
			'editor_type' => 'text',
			'value'       => $post->post_excerpt
		);

		return $elements;
	}

	/**
	 * Ajax handler for switching the language of a post.
	 */
	public static function wpml_switch_post_language() {
		global $sitepress, $wpdb;

		$to      = false;
		$post_id = false;

		if ( isset( $_POST[ 'wpml_to' ] ) ) {
			$to = $_POST[ 'wpml_to' ];
		}
		if ( isset( $_POST[ 'wpml_post_id' ] ) ) {
			$post_id = $_POST[ 'wpml_post_id' ];
		}

		$result = false;

		set_transient( md5( $sitepress->get_current_user()->ID . 'current_user_post_edit_lang' ), $to );
		if ( $post_id && $to ) {

			$post_type      = get_post_type( $post_id );
			$wpml_post_type = 'post_' . $post_type;
			$trid           = $sitepress->get_element_trid( $post_id, $wpml_post_type );

			/* Check if a translation in that language already exists with a different post id.
			 * If so, then don't perform this action.
			 */

			$query_for_existing_translation = $wpdb->prepare( "	SELECT translation_id, element_id
																FROM {$wpdb->prefix}icl_translations
																WHERE element_type = %s
																	AND trid = %d
																	AND language_code = %s",
			                                                  $wpml_post_type, $trid, $to );
			$existing_translation           = $wpdb->get_row( $query_for_existing_translation );

			if ( $existing_translation && $existing_translation->element_id != $post_id ) {
				$result = false;
			} else {
				$sitepress->set_element_language_details( $post_id, $wpml_post_type, $trid, $to );
				// Synchronize the posts terms languages. Do not create automatic translations though.
				WPML_Terms_Translations::sync_post_terms_language( $post_id );
				require_once ICL_PLUGIN_PATH . '/inc/cache.php';
				icl_cache_clear( $post_type . 's_per_language', true );

				$result = $to;
			}
		}

		wp_send_json_success( $result );
	}

	/**
	 * Saves the language from which a user is editing the currently edited post as a transient.
	 * This is done so that filtering the language from which terms for the flat terms preview dropdown can be performed.
	 */
	public static function wpml_set_post_edit_lang() {
		global $sitepress;
		$lang_code = false;
		if ( isset( $_POST[ 'wpml_post_lang' ] ) ) {
			$lang_code = $_POST[ 'wpml_post_lang' ];
		}

		set_transient( md5( $sitepress->get_current_user()->ID . 'current_user_post_edit_lang' ), $lang_code );
	}

	public static function wpml_get_default_lang() {
		global $sitepress;
		wp_send_json_success( $sitepress->get_default_language() );
	}
}
