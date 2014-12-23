<?php

class WPML_Post_Edit_Ajax {

	/**
	 * Ajax handler that gets all terms in a given taxonomy and language.
	 * If given a post id terms in other languages, but not yet translated, that are assigned to the post will also be returned.
	 * todo: make popularity work for flat terms.
	 */
	public static function wpml_get_taxonomy_terms_json() {

		$lang          = false;
		$post_id       = false;
		$taxonomy      = false;
		$check_popular = true;

		if ( isset( $_POST[ 'wpml_lang' ] ) ) {
			$lang = $_POST[ 'wpml_lang' ];
		}

		if ( isset( $_POST[ 'wpml_taxonomy' ] ) ) {
			$taxonomy = $_POST[ 'wpml_taxonomy' ];
		}

		if ( isset( $_POST[ 'wpml_post_id' ] ) ) {
			$post_id = $_POST[ 'wpml_post_id' ];
		}

		if ( isset( $_POST[ 'wpml_check_popular' ] ) ) {
			$check_popular = $_POST[ 'wpml_check_popular' ];
		}

		$results = false;

		if ( $taxonomy && $lang ) {

			$args = array(
				'post_id'       => $post_id,
				'lang'          => $lang,
				'check_popular' => $check_popular
			);

			$elements = WPML_Terms_Translations::get_taxonomy_terms_by( $taxonomy, $args );

			$results = $elements;
		}

		wp_send_json_success( $results );
	}

	/**
	 * Ajax handler for adding a term via Ajax.
	 */
	public static function wpml_save_term() {
		global $sitepress;

		$taxonomy    = false;
		$lang        = false;
		$name        = false;
		$slug        = false;
		$trid        = false;
		$description = false;
		$sync        = false;

		if ( isset( $_POST[ 'term_language_code' ] ) ) {
			$lang = $_POST[ 'term_language_code' ];
		}

		if ( isset( $_POST[ 'taxonomy' ] ) ) {
			$taxonomy = $_POST[ 'taxonomy' ];
		}

		if ( isset( $_POST[ 'slug' ] ) ) {
			$slug = $_POST[ 'slug' ];
		}

		if ( isset( $_POST[ 'name' ] ) ) {
			$name = $_POST[ 'name' ];
		}

		if ( isset( $_POST[ 'trid' ] ) ) {
			$trid = $_POST[ 'trid' ];
		}

		if ( isset( $_POST[ 'description' ] ) ) {
			$description = $_POST[ 'description' ];
		}

		if ( isset( $_POST[ 'force_hierarchical_sync' ] ) ) {
			$sync = $_POST[ 'force_hierarchical_sync' ];
		}

		$new_term_object = false;

		if ( $name && $taxonomy && $trid && $lang ) {

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
				if ( $sync ) {
					$tree = new WPML_Translation_Tree( $taxonomy );
					$tree->sync_tree( $lang, $sync );
				}
			}
		}
		wp_send_json_success( $new_term_object );
	}

	/**
	 * Ajax handler allowing for the removal of a term from a post.
	 * todo: handle the return of this in js.
	 */
	public static function wpml_remove_terms_from_post() {

		$translated_post_id = false;
		$terms              = array();
		$taxonomy           = false;
		$arg_type           = 'strings';
		$result             = false;

		if ( isset( $_POST[ 'wpml_terms' ] ) ) {
			$terms = $_POST[ 'wpml_terms' ];
		}

		if ( isset( $_POST[ 'wpml_arg_type' ] ) ) {
			$arg_type = $_POST[ 'wpml_arg_type' ];
		}

		if ( isset( $_POST[ 'wpml_taxonomy' ] ) ) {
			$taxonomy = $_POST[ 'wpml_taxonomy' ];
		}
		if ( isset( $_POST[ 'wpml_post_id' ] ) ) {
			$translated_post_id = $_POST[ 'wpml_post_id' ];
		}

		if ( $arg_type == 'strings' && ! empty( $terms ) ) {
			$result = wp_remove_object_terms( $translated_post_id, $terms, $taxonomy );
		} elseif ( $arg_type == 'id' && ! empty( $terms ) ) {
			$result = wp_remove_object_terms( $translated_post_id, array( (int) $terms ), $taxonomy );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Ajax handler for previewing potentially untranslated terms on a posts,
	 * the language of which is about to be changed and whose connection to the post
	 * will therefore be lost.
	 */
	public static function wpml_before_switch_post_language() {
		$to      = false;
		$post_id = false;

		$result = false;

		if ( isset( $_POST[ 'wpml_to' ] ) ) {
			$to = $_POST[ 'wpml_to' ];
		}
		if ( isset( $_POST[ 'wpml_post_id' ] ) ) {
			$post_id = $_POST[ 'wpml_post_id' ];
		}

		if ( $to && $post_id ) {
			$result = WPML_Terms_Translations::get_untranslated_terms_for_post( $post_id, $to );

			if ( empty( $result ) ) {
				$result = false;
			}
		}

		wp_send_json_success( $result );
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

			$query_for_existing_translation = $wpdb->prepare( "SELECT translation_id, element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = %s AND trid = %d AND language_code = %s", $wpml_post_type, $trid, $to );
			$existing_translation           = $wpdb->get_row( $query_for_existing_translation );

			if ( $existing_translation && $existing_translation->element_id != $post_id ) {
				$result = false;
			} else {
				$sitepress->set_element_language_details( $post_id, $wpml_post_type, $trid, $to );
				// Synchronize the posts terms languages. Do not create automatic translations though.
				WPML_Terms_Translations::sync_post_terms_language( $post_id, false );
				require_once ICL_PLUGIN_PATH . '/inc/cache.php';
				icl_cache_clear( $post_type . 's_per_language', true );

				$result = $to;
			}
		}

		wp_send_json_success( $result );
	}

	/**
	 * Returns all data needed to create the language options postbox
	 */
	public static function wpml_get_translations_table_data() {
		global $sitepress, $iclTranslationManagement;

		$selected_lang_code = false;
		$translated_post_id = false;

		if ( isset( $_POST[ 'wpml_post_lang' ] ) ) {
			$selected_lang_code = $_POST[ 'wpml_post_lang' ];
		}

		if ( isset( $_POST[ 'wpml_post_id' ] ) ) {
			$translated_post_id = $_POST[ 'wpml_post_id' ];
		}

		$result = array();

		$translated_post = get_post( $translated_post_id );

		if ( $selected_lang_code && $translated_post ) {

			$active_languages = $sitepress->get_active_languages();

			$wpml_post_type = 'post_' . $translated_post->post_type;

			$post_language_details = $sitepress->get_element_language_details( $translated_post_id, 'post_' . $translated_post->post_type );

			if ( isset( $post_language_details->trid ) ) {
				$trid = $post_language_details->trid;

				$translations = $sitepress->get_element_translations( $trid, $wpml_post_type );

				$allowed_target_languages   = array();
				$forbidden_target_languages = array();

				foreach ( $active_languages as $language ) {

					$lang_code = $language [ 'code' ];
					$job_id    = $iclTranslationManagement->get_translation_job_id( $trid, $lang_code );

					if ( ! isset( $translations[ $lang_code ] ) && ! $job_id ) {
						$allowed_target_languages [ $lang_code ] = $language [ 'display_name' ];
					} elseif ( $job_id ) {

						$forbidden_target_languages [ $lang_code ] = $language [ 'display_name' ];
					}
				}

				$result [ 'allowed_languages' ]   = $allowed_target_languages;
				$result [ 'forbidden_languages' ] = $forbidden_target_languages;
			}
		}
		wp_send_json_success( $result );
	}

	/**
	 * Ajax wrapper for retrieving an array containing all taxonomies that are translated by WPML and a flag indicating whether they are hierarchical.
	 */
	public static function wpml_get_translated_taxonomies() {
		global $sitepress;

		$post_id = false;
		$lang    = false;

		if ( isset( $_POST[ 'wpml_post_id' ] ) ) {
			$post_id = $_POST[ 'wpml_post_id' ];
		}

		if ( isset( $_POST[ 'wpml_to' ] ) ) {
			$lang = $_POST[ 'wpml_to' ];
		}

		$translated_taxonomies = array();

		$taxonomy_search_args = array();

		if ( $post_id ) {
			$post_object              = get_post( $post_id );
			$taxonomy_search_args [ ] = array( $post_object->post_type );
		}

		$taxonomies = get_taxonomies( array(), 'objects' );

		foreach ( $taxonomies as $key => $taxobject ) {
			$tax          = $taxobject->name;
			$hierarchical = false;
			if ( $sitepress->is_translated_taxonomy( $tax ) ) {
				if ( is_taxonomy_hierarchical( $tax ) ) {
					$hierarchical = true;
				}

				$args = array(
					'post_id' => $post_id,
					'lang'    => $lang,
				);

				$terms_in_tax = WPML_Terms_Translations::get_taxonomy_terms_by( $tax, $args );

				if ( ! $terms_in_tax ) {
					$terms_in_tax = array();
				}

				$translated_taxonomies [ ] = array( 'label'        => $taxobject->label,
				                                    'name'         => $tax,
				                                    'hierarchical' => $hierarchical,
				                                    'terms'        => $terms_in_tax
				);
			}
		}

		wp_send_json_success( $translated_taxonomies );
	}

	/**
	 * Ajax wrapper for getting the correct parent if of a post, even if it is not yet assigned to the translated post's database entry.
	 */
	public static function wpml_get_post_parent_id_by_lang() {

		global $sitepress;

		$lang_code          = false;
		$translated_post_id = false;
		if ( isset( $_POST[ 'wpml_post_lang' ] ) ) {
			$lang_code = $_POST[ 'wpml_post_lang' ];
		}

		if ( isset( $_POST[ 'wpml_post_id' ] ) ) {
			$translated_post_id = $_POST[ 'wpml_post_id' ];
		}

		$original_post = false;

		if ( $translated_post_id ) {
			$translated_post = get_post( $translated_post_id );

			$original_post_id = $sitepress->get_original_element_id( $translated_post_id, 'post_' . $translated_post->post_type );

			$original_post = get_post( $original_post_id );
		}

		$parent_id = false;

		if ( $original_post ) {
			$parent_id = $original_post->post_parent;
		}
		if ( $parent_id ) {

			$parent_post         = get_post( $parent_id );
			$parent_translations = $sitepress->get_element_translations( $parent_id, 'post_' . $parent_post->post_type );

			foreach ( $parent_translations as $translated_parent ) {

				if ( $translated_parent->language_code == $lang_code ) {
					$parent_id = $translated_parent->element_id;
				}
			}
		} else {
			$parent_id = '';
		}

		wp_send_json_success( $parent_id );
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

	public static function wpml_get_post_permalink() {
		global $sitepress;

		$post_id = false;
		$lang    = false;

		if ( isset( $_POST[ 'wpml_post_id' ] ) ) {
			$post_id = $_POST[ 'wpml_post_id' ];
		}

		if ( isset( $_POST[ 'wpml_post_lang' ] ) ) {
			$lang = $_POST[ 'wpml_post_lang' ];
		}

		$permalink = false;

		if ( $post_id && $lang ) {
			$permalink = post_permalink( $post_id );
			$urls      = $sitepress->get_setting( 'urls' );
			$root_id   = 0;
			if ( isset( $urls[ 'root_page' ] ) ) {
				$root_id = $urls[ 'root_page' ];
			}

			if ( $post_id != $root_id ) {
				$permalink = $sitepress->convert_url( $permalink, $lang );
			}
		}
		/* The new permalink is set correctly by the filtering in the main SitePress class. */
		wp_send_json_success( $permalink );
	}

	public static function wpml_get_default_lang() {
		global $sitepress;
		wp_send_json_success( $sitepress->get_default_language() );
	}
}
