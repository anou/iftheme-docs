<?php

/**
 *  This class holds all basic functionality for translating terms.
 */
class WPML_Terms_Translations {

	/**
	 * @deprecated since Version 3.1.8.3
	 * @param $terms array
	 * @param $taxonomies array|string
	 * This is only used by the WP core AJAX call that fetches the preview auto-complete for flat taxonomy term adding
	 *
	 * @return mixed
	 */
	public static function get_terms_filter( $terms, $taxonomies ) {
		global $wpdb, $sitepress;

		$lang = $sitepress->get_current_language();

		foreach ( $taxonomies as $taxonomy ) {

			if ( $sitepress->is_translated_taxonomy( $taxonomy ) ) {

				$element_type = 'tax_' . $taxonomies[ 0 ];

				$query = $wpdb->prepare( "SELECT wptt.term_id FROM {$wpdb->prefix}icl_translations AS iclt JOIN {$wpdb->prefix}term_taxonomy AS wptt ON iclt.element_id = wptt.term_taxonomy_id WHERE language_code=%s AND element_type = '{$element_type}'", $lang );

				$element_ids = $wpdb->get_results( $query );

				$element_ids_array = array();

				foreach ( $element_ids as $element ) {
					$element_ids_array [ ] = (int) $element->term_id;
				}

				foreach ( $terms as $key => $term ) {
					if ( ! is_object( $term ) ) {
						$term = get_term_by( 'name', $term, $taxonomy );
					}
					if ( $term && isset( $term->taxonomy ) && $term->taxonomy == $taxonomy && ! in_array( $term->term_id, $element_ids_array ) ) {
						unset( $terms[ $key ] );
					}
				}
			}
		}
		return $terms;
	}

	/**
	 * @param $slug
	 * @param $taxonomy
	 * Filters slug input, so to ensure uniqueness of term slugs.
	 *
	 * @return string
	 */
	public static function pre_term_slug_filter( $slug, $taxonomy ) {
		global $sitepress;

		if ( ( isset( $_REQUEST[ 'tag-name' ] ) || isset( $_REQUEST[ 'name' ] ) )
		     && ( ( isset( $_REQUEST[ 'submit' ] )
		            && mb_strpos( $_REQUEST[ 'submit' ], 'Update' ) === false )
		          || ( isset( $_REQUEST[ 'action' ] ) && $_REQUEST[ 'action' ] == 'add-tag' ) )
		) {
			// When a term is created, it is likely that this term is to be created in the currently selected language
			$lang = $sitepress->get_current_language();

			/* Still WPML allows for creating it in a different language, via adding the proper hidden field on the edit-tags.php.
			 * Settings in this hidden field take priority over the current_language in the cookie.
			 */
			if ( isset( $_REQUEST[ 'icl_tax_post_tag_language' ] ) && $sitepress->is_active_language( $_POST[ 'icl_tax_post_tag_language' ] ) ) {
				$lang = $_REQUEST[ 'icl_tax_post_tag_language' ];
			} elseif ( isset( $_REQUEST[ 'language' ] ) && $sitepress->is_active_language( $_POST[ 'language' ] ) ) {
				$lang = $_REQUEST[ 'language' ];
			}

			if ( $slug == '' ) {

				if ( isset( $_REQUEST[ 'tag-name' ] ) ) {
					$slug = sanitize_title( $_REQUEST[ 'tag-name' ] );
				} elseif ( isset( $_REQUEST[ 'name' ] ) ) {
					$slug = sanitize_title( $_REQUEST[ 'name' ] );
				}
			}

			if ( $slug != '' ) {
				$slug = self::term_unique_slug( $slug, $taxonomy, $lang );
			}
		}

		return $slug;
	}

	/**
	 * @param $slug
	 * @param $taxonomy
	 * @param $lang
	 * Creates a unique slug for a given term, using a scheme
	 * encoding the language code in the slug.
	 *
	 * @return string
	 */
	public static function term_unique_slug( $slug, $taxonomy, $lang ) {

		if ( self::term_slug_exists( $slug, $lang, $taxonomy ) ) {
			$slug .= '-' . $lang;
		}

		$i      = 2;
		$suffix = '-' . $i;

		if ( self::term_slug_exists( $slug, $lang, $taxonomy ) ) {
			while ( self::term_slug_exists( $slug . $suffix, $lang, $taxonomy ) ) {
				$i ++;
				$suffix = '-' . $i;
			}
			$slug .= $suffix;
		}

		return $slug;
	}

	/**
	 * @param      $slug
	 * @param      $language
	 * @param bool $taxonomy
	 * If $taxonomy is given, then slug existence is checked only for the specific taxonomy.
	 *
	 * @return bool
	 */
	private static function term_slug_exists( $slug, $language, $taxonomy = false ) {
		global $wpdb, $sitepress;

		$result = false;

		$existing_term_prepared_query = $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE slug=%s", $slug );
		$term_id                      = $wpdb->get_var( $existing_term_prepared_query );

		if ( $term_id ) {
			$result = true;

			if ( $taxonomy ) {
				$taxonomy_query_prepared = $wpdb->prepare( "SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $term_id );
				$taxonomies              = $wpdb->get_col( $taxonomy_query_prepared );

				if ( ! empty( $taxonomies ) ) {
					$ttid_query_prepared = $wpdb->prepare( "SELECT term_taxonomy_id, taxonomy FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $term_id );
					$existing_elements   = $wpdb->get_results( $ttid_query_prepared );

					$exists_in_other_language = false;
					foreach ( $existing_elements as $element ) {

						if ( $language != $sitepress->get_language_for_element( $element->term_taxonomy_id, 'tax_' . $element->taxonomy ) ) {
							$exists_in_other_language = true;
						}
					}

					if ( ! in_array( $taxonomy, $taxonomies ) && ! $exists_in_other_language ) {
						$result = false;
					}
				}
			}
		}

		return $result;
	}

	/**
	 *
	 * Once we create a new term, it could be that this term is actually the translation of another term in more than one taxonomy.
	 * In this case entries for all taxonomies have to be created in icl_translations.
	 * This action creates these entries.
	 *
	 * @param $tt_id
	 * @param $language_code
	 * @param $taxonomy
	 */
	public static function sync_ttid_action( $taxonomy, $tt_id, $language_code ) {
		global $wpdb, $sitepress;

		// First we get all taxonomies, to which the new term's original element belongs.
		$original_ttid   = $sitepress->get_original_element_id( $tt_id, 'tax_' . $taxonomy );
		$source_langauge = $sitepress->get_language_for_element( $original_ttid, 'tax_' . $taxonomy );

		$query_for_original_term_id = $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $original_ttid );
		$original_term_id           = $wpdb->get_var( $query_for_original_term_id );

		if ( $original_term_id ) {
			$taxonomy_query_prepared = $wpdb->prepare( "SELECT taxonomy, term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $original_term_id );
			$original_tax_terms      = $wpdb->get_results( $taxonomy_query_prepared );

			$query_for_translated_term_id = $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $tt_id );
			$translated_term_id           = $wpdb->get_var( $query_for_translated_term_id );

			$taxonomy_query_prepared   = $wpdb->prepare( "SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $translated_term_id );
			$taxonomies_on_translation = $wpdb->get_col( $taxonomy_query_prepared );

			foreach ( $original_tax_terms as $original_tax_term ) {
				if ( isset( $original_tax_term->taxonomy ) && isset( $original_tax_term->term_taxonomy_id ) ) {
					$original_taxonomy = $original_tax_term->taxonomy;
					$original_tax_ttid = $original_tax_term->term_taxonomy_id;
					if ( ! in_array( $original_taxonomy, $taxonomies_on_translation ) ) {
						$ttid_row = array( 'term_id' => $translated_term_id, 'taxonomy' => $original_taxonomy );
						if ( is_taxonomy_hierarchical( $taxonomy ) ) {
							$original_term_parent_query_prepared = $wpdb->prepare( "SELECT parent FROM {$wpdb->term_taxonomy} WHERE $original_tax_ttid = %d", $original_tax_ttid );
							$parent                              = $wpdb->get_var( $original_term_parent_query_prepared );
							if ( $parent > 0 ) {
								$ttid_row [ 'parent' ] = $parent;
							}
						}

						$update = false;
						$trid   = $sitepress->get_element_trid( $original_tax_ttid, 'tax_' . $original_taxonomy );

						if ( $trid ) {

							$data = array(
								'trid'                 => $trid,
								'language_code'        => $language_code,
								'source_language_code' => $source_langauge,
								'element_type'         => 'tax_' . $original_taxonomy
							);

							$existing_translations = $sitepress->get_element_translations( $trid, 'tax_' . $original_taxonomy );
							if ( isset( $existing_translations[ $language_code ] ) ) {
								$update = true;
							}

							if ( ! $update ) {
								$wpdb->insert( $wpdb->term_taxonomy, $ttid_row );
								$new_ttid             = $wpdb->insert_id;
								$data[ 'element_id' ] = $new_ttid;
								$wpdb->insert( $wpdb->prefix . 'icl_translations', $data );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * This function provides an action hook only used by WCML.
	 * It will be removed in the future and should not be implemented in new spots.
	 * @deprecated deprecated since version 3.1.8.3
	 *
	 * @param $taxonomy        string The identifier of the taxonomy the translation was just saved to.
	 * @param $translated_term array The associative array holding term taxonomy id and term id,
	 *                         as returned by wp_insert_term or wp_update_term.
	 */
	public static function icl_save_term_translation_action( $taxonomy, $translated_term ) {
		global $wpdb, $sitepress;

		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$term_taxonomy_id = $translated_term[ 'term_taxonomy_id' ];

			$original_ttid = $sitepress->get_original_element_id( $term_taxonomy_id, 'tax_' . $taxonomy );

			$original_tax_sql      = "SELECT * FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s AND term_taxonomy_id = %d";
			$original_tax_prepared = $wpdb->prepare( $original_tax_sql, array( $taxonomy, $original_ttid ) );
			$original_tax          = $wpdb->get_row( $original_tax_prepared );

			do_action( 'icl_save_term_translation', $original_tax, $translated_term );
		}
	}



	/**
	 * @param $cat
	 * @param $tt_id
	 * @param $taxonomy
	 */
	public static function delete_term_filter( $cat, $tt_id, $taxonomy )
	{
		global $wpdb, $sitepress;
		$icl_el_type = 'tax_' . $taxonomy;

		static $recursion;
		if ( $sitepress->get_setting( 'sync_delete_tax' ) && empty( $recursion ) ) {

			// only for translated
			$lang_details = $sitepress->get_element_language_details( $tt_id, $icl_el_type );
			if ( empty( $lang_details->source_language_code ) ) {

				// get translations
				$trid         = $sitepress->get_element_trid( $tt_id, $icl_el_type );
				$translations = $sitepress->get_element_translations( $trid, $icl_el_type );

				$recursion = true;
				// delete translations
				foreach ( $translations as $translation ) {
					if ( $translation->element_id != $tt_id ) {
						wp_delete_term( $translation->term_id, $taxonomy );
					}
				}
				$recursion = false;
			}
		}

		$wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translations WHERE element_type =%s AND element_id=%d LIMIT 1", array($icl_el_type, $tt_id) ) );
	}

	/**
	 * Filters the display of the categories list in order to prevent the default category from being delete-able.
	 * This is done by printing a hidden div containing a JSON encoded array with all category id's, the checkboxes of which are to be removed.
	 *
	 * @param $taxonomy
	 */
	public static function category_display_action( $taxonomy ) {
		global $sitepress;

		//first of all we get the default category regardless of it's current language
		$default_category_id = get_option( 'default_category' );

		$default_cat_ids = array();

		if ( $default_category_id ) {
			$default_category_object = get_term( $default_category_id, 'category' );
			if ( $default_category_object && isset( $default_category_object->term_taxonomy_id ) ) {
				$default_category_tax_id       = $default_category_object->term_taxonomy_id;
				$trid                          = $sitepress->get_element_trid( $default_category_tax_id, 'tax_category' );
				$default_category_translations = $sitepress->get_element_translations( $trid, 'tax_category' );
				foreach ( $default_category_translations as $translation ) {
					if ( isset( $translation->element_id ) ) {
						$translation_object = get_term_by( 'term_taxonomy_id', $translation->element_id, 'category' );
						if ( isset( $translation_object->term_id ) ) {
							$default_cat_ids [ ] = $translation_object->term_id;
						}
					}
				}
			}
		}
		$default_cats_json = json_encode( $default_cat_ids );
		$output            = '<div id="icl-default-category-ids" style="display: none;">' . $default_cats_json . '</div>';

		echo $output;
	}

	/**
	 * Prints a hidden div, containing the list of allowed terms for a post type in each language.
	 * This is used to only display the correct categories and tags in the quick-edit fields of the post table.
	 *
	 * @param $column_name
	 * @param $post_type
	 */
	public static function quick_edit_terms_removal( $column_name, $post_type ) {
		global $sitepress;
		if ( $column_name == 'icl_translations' ) {
			$languages                      = $sitepress->get_active_languages();
			$taxonomies                     = get_object_taxonomies( $post_type );
			$terms_by_language_and_taxonomy = array();

			foreach ( $taxonomies as $tax ) {
				if ( $sitepress->is_translated_taxonomy( $tax ) ) {
					foreach ( $languages as $lang => $langinfo ) {
						$terms_in_tax = self::get_taxonomy_terms_by( $tax, array( 'lang' => $lang ) );

						foreach ( $terms_in_tax as $term ) {
							if ( isset( $term->term_id ) ) {
								$terms_by_language_and_taxonomy[ $lang ][ $tax ][ ] = $term->term_id;
							}
						}
					}
				}
			}
			$terms_json = json_encode( $terms_by_language_and_taxonomy );
			$output     = '<div id="icl-terms-by-lang" style="display: none;">' . $terms_json . '</div>';
			echo $output;
		}
	}

	/**
	 * @param $post_id
	 * @param $target_lang
	 *
	 * Function for displaying all terms on a post, that do not possess a translation in the given target language.
	 *
	 * @return array
	 */
	public static function get_untranslated_terms_for_post( $post_id, $target_lang ) {
		global $sitepress;

		// First we get a list of all taxonomies that are translated.

		$post_object = get_post( $post_id );

		$taxonomies = get_object_taxonomies( $post_object, 'objects' );

		$untranslated_terms = array();

		foreach ( $taxonomies as $key => $taxobject ) {
			$tax = $taxobject->name;
			if ( $sitepress->is_translated_taxonomy( $tax ) ) {
				$terms_for_tax = wp_get_post_terms( $post_id, $tax );

				if ( $terms_for_tax ) {
					$untranslated_terms_in_taxonomy = array();
					foreach ( $terms_for_tax as $term_in_tax ) {
						$trid              = $sitepress->get_element_trid( $term_in_tax->term_taxonomy_id, 'tax_' . $tax );
						$term_translations = $sitepress->get_element_translations( $trid, 'tax_' . $tax );
						//Check each of these translated taxonomies for terms that are not available in the target language
						if ( ! isset( $term_translations [ $target_lang ] ) ) {
							$untranslated_terms_in_taxonomy[ ] = $term_in_tax->name;
						}
					}
					if ( ! empty( $untranslated_terms_in_taxonomy ) ) {
						// The return only differentiates between hierarchical and flat taxonomies. Also it is ensured that all terms only show up once in the output of this function.
						if ( isset( $untranslated_terms [ $tax ] ) ) {
							$untranslated_terms [ $tax ] = array_unique( array_merge( $untranslated_terms [ $tax ], $untranslated_terms_in_taxonomy ) );
						} else {
							$untranslated_terms [ $taxobject->label ] = $untranslated_terms_in_taxonomy;
						}
					}
				}
			}
		}

		return $untranslated_terms;
	}

	/**
	 * @param $taxonomy
	 * @param $args
	 * Retrieves an array of taxonomy terms, filtered by various parameters.
	 * @return array|WP_Error
	 */
	public static function get_taxonomy_terms_by( $taxonomy, $args ) {
		global $wpdb;

		wp_cache_flush();
		$lang          = false;
		$post_id       = false;
		$check_popular = false;

		extract( $args, EXTR_OVERWRITE );

		$pop_items = false;

		$element_type = 'tax_' . $taxonomy;

		if ( $check_popular ) {
			$pop_items = wp_popular_terms_checklist( $taxonomy, $default = 0, $number = 10, $echo = false );
		}

		$query = $wpdb->prepare( "SELECT wptt.term_id FROM {$wpdb->prefix}icl_translations AS iclt JOIN {$wpdb->prefix}term_taxonomy AS wptt ON iclt.element_id = wptt.term_taxonomy_id WHERE language_code=%s AND element_type = '{$element_type}'", $lang );

		$element_ids = $wpdb->get_results( $query );

		$element_ids_array = array();

		foreach ( $element_ids as $element ) {
			$element_ids_array [ ] = (int) $element->term_id;
		}

		$all_elements_in_lang = array();
		if ( ! empty( $element_ids_array ) ) {
			$all_elements_in_lang = get_terms( array( $taxonomy ), array( 'include' => $element_ids_array, 'hide_empty' => false ) );
		}
		$post_terms = array();

		if ( $post_id ) {
			$post_terms = wp_get_post_terms( $post_id, $taxonomy, array( "fields" => "ids" ) );
		}

		foreach ( $all_elements_in_lang as &$term ) {
			if ( $post_terms ) {
				$term_key = array_search( $term->term_id, $post_terms );
				if ( $term_key !== false ) {
					$term->selected = true;
					//remove this value from the $post_terms since we already accounted for it completely
					unset( $post_terms[ $term_key ] );
				} else {
					$term->selected = false;
				}
			}

			if ( $pop_items ) {
				if ( in_array( $term->term_id, $pop_items ) ) {
					$term->popular = true;
				} else {
					$term->popular = false;
				}
			}
		}

		/* Now the situation could arise in which we still have terms on the post that are not in the proper language
		 * This is dealt with by synchronizing the posts terms language. If a term has a translatation it will be
		 * appended to the post instead of the original. If it does not have a translation, it will be removed
		 * from the post.
		 */
		if ( count( $post_terms ) > 0 ) {
			//todo:this is computationally very expensive in some cases, add caching
			self::sync_post_and_taxonomy_terms_language( $post_id, $taxonomy );

			//after having done so we get all terms on the post again
			$remaining_untranslated_terms_on_post = (array) wp_get_post_terms( $post_id, $taxonomy );

			//these terms are all selected
			foreach ( $remaining_untranslated_terms_on_post as $key => &$term ) {
				if ( in_array( $term->term_id, $element_ids_array ) ) {
					//if we already have that element added, we do not need to add it again
					$term->selected = true;
				} else {
					wp_remove_object_terms( $post_id, $term->term_id, $taxonomy );
				}
			}
		}
		$result = false;
		if ( is_array( $all_elements_in_lang ) ) {
			$result = $all_elements_in_lang;
		}

		return $result;
	}

	/**
	 * @param      $job_id
	 * @param      $post_id
	 * @param bool $overwrite Sets whether existing translations are to be overwritten or new ones to be created.
	 *                        This is parameter is set by the sitepress setting tm_block_retranslating_terms
	 *
	 * @return array
	 */
	public static function save_all_terms_from_job( $job_id, $post_id, $overwrite = true ) {
		global $iclTranslationManagement, $sitepress;

		remove_action( 'create_term', array( $sitepress, 'create_term' ), 1);
		remove_action( 'edit_term', array( $sitepress, 'create_term' ), 1 );

		/* The first step is to get the new $post object well as the new $job */

		/** @noinspection PhpUndefinedMethodInspection */
		$job         = $iclTranslationManagement->get_translation_job( $job_id );
		$post_object = get_post( $post_id );

		/*
		 * Now we need all taxonomies from which the job contains elements.
		 * We do only care about translated taxonomies.
		 * We treat hierarchical and non-hierarchical taxonomies differently.
		 */

		$taxonomies = get_object_taxonomies( $post_object );

		$translated_taxonomies = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( $sitepress->is_translated_taxonomy( $taxonomy ) ) {

				$translated_taxonomies [ ] = $taxonomy;
			}
		}

		$terms = array();

		foreach ( $job->elements as $field ) {

			$field_type = $field->field_type;

			/* Naming convention adjustments */
			if ( $field_type == 'categories' ) {
				$field_type = 'category';
			} elseif ( $field_type == 'tags' ) {
				$field_type = 'post_tag';
			}

			if ( in_array( $field_type, $translated_taxonomies ) ) {
				$terms [ $field_type ] [ ] = $field;
			}
		}

		/* Here begins the actual saving of the terms
		 * First the arguments pertaining to a possible scenarios of term saving are set.
		 */

		$term_save_args = array(
			'lang_code'       => $job->language_code,
			'source_language' => $job->source_language_code,
			'overwrite'       => $overwrite
		);

		$result = array();

		foreach ( $terms as $tax => $fields_array ) {
			foreach ( $fields_array as $flat_terms_field ) {

				/* The term names are encoded in the job object and have to be decoded */
				$translated_terms_array = TranslationManagement::decode_field_data( $flat_terms_field->field_data_translated, $flat_terms_field->field_format );
				$original_terms_array   = TranslationManagement::decode_field_data( $flat_terms_field->field_data, $flat_terms_field->field_format );

				foreach ( $translated_terms_array as $key => $translated_term ) {

					/* Each term has its own trid.
					 * We get it by first fetching the original term object by its name and then getting its original taxonomy id
					 */
					$original_term = $original_terms_array[ $key ];
					//todo: handle this differently for hierarchical terms, otherwise we might run into problems here, generally try moving towards using term_ids
					$original_term_object = get_term_by( 'name', $original_term, $tax );

					$term_save_args[ 'term' ] = $translated_term;

					$term_save_args[ 'trid' ] = $sitepress->get_element_trid( $original_term_object->term_taxonomy_id, 'tax_' . $tax );

					$term_save_args[ 'taxonomy' ] = $tax;

					$saved_term = self::create_new_term( $term_save_args );

					$append_result = wp_set_object_terms( $post_id, $saved_term[ 'term_id' ], $tax, true );

					if ( ! isset( $append_result[ 0 ] ) || $append_result[ 0 ] != $saved_term[ 'term_taxonomy_id' ] ) {
						$result [ ] = $flat_terms_field;
					} else {
						$result [ ] = $append_result;
					}
				}
			}
			self::sync_post_and_taxonomy_terms_language( $post_id, $tax );
		}

		add_action( 'create_term', array( $sitepress, 'create_term' ), 1, 2 );
		add_action( 'edit_term', array( $sitepress, 'create_term' ), 1, 2 );

		return $result;
	}

	/**
	 * @param $post_id int
	 * @param $args    array Arguments for the term creation
	 *                 Adds a term to a post. Creates the term if it does not yet exist.
	 *
	 * @return bool
	 */
	public static function create_term_on_post( $post_id, $args ) {

		$term        = false;
		$taxonomy    = false;
		$parent      = false;
		$slug        = false;
		$term_object = false;

		extract( $args, EXTR_OVERWRITE );

		/* We have to have be careful when handling hierarchical taxonomies here.
		/ Adding two terms by the same name but with different parents is ensured by this check */
		if ( !$parent || $parent == -1 ) {
			$term_object = get_term_by( 'name', $term, $taxonomy );
		} else {
			$termchildren = get_term_children( $parent, $taxonomy );
			foreach ( $termchildren as $child ) {
				$child_term_object = get_term_by( 'id', $child, $taxonomy );
				if ( $term === $child_term_object->name ) {
					$term_object = $child_term_object;
					break;
				}
			}
		}
		/* Simply true for now */
		$update_translations           = true;
		$args[ 'update_translations' ] = $update_translations;

		if ( ! $term_object ) {
			$saved_term = self::create_new_term( $args );

			$append_result = wp_set_object_terms( $post_id, (int)$saved_term[ 'term_id' ], $taxonomy, true );
		} else {
			$append_result = wp_set_object_terms( $post_id, $term_object->term_id, $taxonomy, true );
		}

		if ( ! is_array( $append_result ) || ! isset( $append_result[ 0 ] ) ) {
			$result = false;
		} else {
			$result = $append_result[ 0 ];
		}

		self::sync_post_and_taxonomy_terms_language( $post_id, $taxonomy );

		return $result;
	}

	/**
	 * Creates a new term from an argument array.
	 * @param array $args
	 * @return array|bool
	 * Returns either an array containing the term_id and term_taxonomy_id of the term resulting from this database
	 * write or false on error.
	 */
	public static function create_new_term( $args ) {

		/** @var string $taxonomy */
		$taxonomy = false;
		/** @var string $lang_code */
		$lang_code = false;
		/**
		 * Sets whether translations of posts are to be updated by the newly created term,
		 * should they be missing a translation still.
		 * During debug actions designed to synchronise post and term languages this should not be set to true,
		 * doing so introduces the possibility of removing terms from posts before switching
		 * them with their translation in the correct language.
		 * @var  bool
		 */
		$sync = false;

		extract( $args, EXTR_OVERWRITE );

		require_once 'wpml-update-term-action.class.php';

		$new_term_action = new WPML_Update_Term_Action( $args );
		$new_term        = $new_term_action->execute();

		if ( $sync && $new_term && $taxonomy && $lang_code ) {
			self::sync_taxonomy_terms_language( $taxonomy );
			self::sync_parent_child_relations( $taxonomy, $lang_code );

		}

		return $new_term;
	}

	/**
	 * @param $taxonomy
	 * @param $lang
	 * Synchronizes the parent child relationships with those of the source terms, for all terms in the given taxonomy
	 * and language.
	 * @return bool
	 */
	private static function sync_parent_child_relations( $taxonomy, $lang ) {
		$taxonomy_tree = new WPML_Translation_Tree( $taxonomy );
		$taxonomy_tree->sync_tree( $lang );
		return true;
	}

	/**
	 * @param $args
	 * Creates an automatic translation of a term, the name of which is set as "original" . @ "lang_code" and the slug of which is set as "original_slug" . - . "lang_code".
	 *
	 * @return array|bool
	 */
	public static function create_automatic_translation( $args ) {
		global $sitepress;

		$term                = false;
		$lang_code           = false;
		$taxonomy            = false;
		$original_id         = false;
		$original_tax_id     = false;
		$trid                = false;
		$original_term       = false;
		$update_translations = false;
		$source_language     = null;

		extract( $args, EXTR_OVERWRITE );

		if ( $trid && ! $original_id ) {
			$original_tax_id = SitePress::get_original_element_id_by_trid( $trid );
			$original_term = get_term_by( 'term_taxonomy_id', $original_tax_id, $taxonomy, OBJECT, 'no' );
		}

		if ( $original_id && ! $original_tax_id ) {
			$original_term = get_term( $original_id, $taxonomy, OBJECT, 'no' );
			if ( isset ( $original_term[ 'term_taxonomy_id' ] ) ) {
				$original_tax_id = $original_term[ 'term_taxonomy_id' ];
			}
		}

		if ( ! $trid ) {
			$trid = $sitepress->get_element_trid( $original_tax_id, 'tax_' . $taxonomy );
		}

		if ( ! $source_language ) {
			$source_language = $sitepress->get_source_language_by_trid( $trid );
		}

		$existing_translations = $sitepress->get_element_translations( $trid, 'tax_' . $taxonomy );
		if ( $lang_code && isset( $existing_translations[ $lang_code ] ) ) {
			$new_translated_term = false;
		} else {

			if ( ! $original_term ) {
				if ( $original_id ) {
					$original_term = get_term( $original_id, $taxonomy, OBJECT, 'no' );
				} elseif ( $original_tax_id ) {
					$original_term = get_term_by( 'term_taxonomy_id', $original_tax_id, $taxonomy, OBJECT, 'no' );
				}
			}
			$translated_slug = false;

			if ( ! $term && isset( $original_term->name ) ) {
				$term = $original_term->name;
			}
			if ( isset( $original_term->slug ) ) {
				$translated_slug = self::term_unique_slug( $original_term->slug, $taxonomy, $lang_code );
			}
			$new_translated_term = false;
			if ( $term ) {
				$new_term_args = array(
					'term'                => $term,
					'slug'                => $translated_slug,
					'taxonomy'            => $taxonomy,
					'lang_code'           => $lang_code,
					'original_tax_id'     => $original_tax_id,
					'update_translations' => $update_translations,
					'trid'                => $trid,
					'source_language'     => $source_language
				);

				$new_translated_term = self::create_new_term( $new_term_args );
			}
		}

		return $new_translated_term;
	}

	/**
	 * @param      $taxonomy
	 * @param bool $automatic_translation
	 *
	 * Sets all taxonomy terms to the correct language on each post, having at least one term from the taxonomy.
	 */
	public static function sync_taxonomy_terms_language( $taxonomy, $automatic_translation = false ) {
		$all_posts_in_taxonomy = get_posts( array( 'tax_query' => array( 'taxonomy' => $taxonomy ) ) );

		foreach ( $all_posts_in_taxonomy as $post_in_taxonomy ) {
			self::sync_post_and_taxonomy_terms_language( $post_in_taxonomy->ID, $taxonomy, $automatic_translation );
		}
	}

	/**
	 * @param      $post_id
	 * @param bool $automatic_translation
	 *
	 * Sets all taxonomy terms ot the correct language for a given post.
	 */
	public static function sync_post_terms_language( $post_id,  $automatic_translation = false ) {

		$taxonomies = get_taxonomies();

		foreach ( $taxonomies as $taxonomy ) {
			self::sync_post_and_taxonomy_terms_language( $post_id, $taxonomy, $automatic_translation );
		}
	}

	/**
	 * @param             $post_id
	 * @param             $taxonomy
	 * @param bool        $automatic_translation
	 * Synchronizes a posts taxonomy term's languages with the posts language for all translations of the post.
	 *
	 */
	public static function sync_post_and_taxonomy_terms_language( $post_id, $taxonomy, $automatic_translation = false ) {
		global $sitepress;

		$post                     = get_post( $post_id );
		$post_type                = $post->post_type;
		$post_trid                = $sitepress->get_element_trid( $post_id, 'post_' . $post_type );
		$post_translations        = $sitepress->get_element_translations( $post_trid, 'post_' . $post_type );
		$terms_from_original_post = wp_get_post_terms( $post_id, $taxonomy );

		$is_original = true;

		if ( $sitepress->get_original_element_id( $post_id, 'post_' . $post_type ) != $post_id ) {
			$is_original = false;
		}

		foreach ( $post_translations as $post_language => $translated_post ) {

			$translated_post_id         = $translated_post->element_id;
			$terms_from_translated_post = wp_get_post_terms( $translated_post_id, $taxonomy );
			if ( $is_original ) {
				$terms = array_merge( $terms_from_original_post, $terms_from_translated_post );
			} else {
				$terms = $terms_from_translated_post;
			}
			foreach ( (array) $terms as $term ) {
				$term_original_tax_id          = $term->term_taxonomy_id;
				$term_original_term_id         = $term->term_id;
				$original_term_language_object = $sitepress->get_element_language_details( $term_original_tax_id, 'tax_' . $term->taxonomy );
				if ( $original_term_language_object && isset( $original_term_language_object->language_code ) ) {
					$original_term_language = $original_term_language_object->language_code;
				} else {
					$original_term_language = $post_language;
				}
				if ( $original_term_language != $post_language ) {
					$term_trid        = $sitepress->get_element_trid( $term_original_tax_id, 'tax_' . $term->taxonomy );
					$translated_terms = $sitepress->get_element_translations( $term_trid, 'tax_' . $term->taxonomy, false, false, true );

					$term_id = $term->term_id;
					wp_remove_object_terms( $translated_post_id, (int) $term_id, $taxonomy );

					if ( isset( $translated_terms[ $post_language ] ) ) {
						$term_in_correct_language = $translated_terms[ $post_language ];
					} else {
						$term_in_correct_language = false;
						if ( $automatic_translation ) {

							$automatic_translation_args = array(
								'lang_code'       => $post_language,
								'taxonomy'        => $taxonomy,
								'trid'            => $term_trid,
								'source_language' => $original_term_language
							);

							$term_in_correct_language = self::create_automatic_translation( $automatic_translation_args );
						}
						if ( ! is_array( $term_in_correct_language ) || ! isset( $term_in_correct_language[ 'term_id' ] ) ) {
							continue;
						}
						$term_in_correct_language = get_term( $term_in_correct_language[ 'term_id' ], $taxonomy );
					}

					wp_set_post_terms( $translated_post_id, array( (int) $term_in_correct_language->term_id ), $taxonomy, true );

					if ( isset( $term->term_taxonomy_id ) ) {
						wp_update_term_count( $term->term_taxonomy_id, $taxonomy );
					}
				}
				wp_update_term_count( $term_original_tax_id, $taxonomy );
			}
			self::sync_parent_child_relations( $taxonomy, $post_language );
		}
	}

	/**
	 * @param int    $post_id    Object ID.
	 * @param array  $terms      An array of object terms.
	 * @param array  $tt_ids     An array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param bool   $append     Whether to append new terms to the old terms.
	 * @param array  $old_tt_ids Old array of term taxonomy IDs.
	 */
	public static function set_object_terms_action( $post_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {

		$bulk = false;

		if ( isset( $_REQUEST[ 'bulk_edit' ] ) ) {
			$bulk = true;
		}

		$tt_ids = array_merge( $tt_ids, $old_tt_ids );

		self::quick_edited_post_terms( $post_id, $taxonomy, $tt_ids, $bulk );
	}

	/**
	 * @param int    $post_id
	 * @param string $taxonomy
	 * @param array  $changed_ttids
	 * @param bool   $bulk
	 * Running this function will remove certain issues arising out of bulk adding of terms to posts of various languages.
	 * This case can result in situations in which the WP Core functionality adds a term to a post, before the language assignment
	 * operations of WPML are triggered. This leads to states in which terms can be assigned to a post even though their language
	 * differs from that of the post.
	 * This function behaves between hierarchical and flag taxonomies. Hierarchical terms from the wrong taxonomy are simply removed
	 * from the post. Flat terms are added with the same name but in the correct language.
	 * For flat terms this implies either the use of the existing term or the creation of a new one.
	 * This function uses wpdb queries instead of the WordPress API, it is therefore save to be run out of
	 * any language setting.
	 */
	public static function quick_edited_post_terms( $post_id, $taxonomy, $changed_ttids = array(), $bulk = false ) {
		global $wpdb, $sitepress;

		if ( ! $sitepress->is_translated_taxonomy( $taxonomy ) ) {
			return;
		}

		// First we get a list of all ttids that are in the posts language and currently acted upon taxonomy.

		$post_type = get_post_type( $post_id );
		$post_lang = $sitepress->get_language_for_element( $post_id, 'post_' . $post_type );

		if ( ! $post_lang ) {
			return;
		}

		$query_for_allowed_ttids = $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE language_code = %s AND element_type = %s", $post_lang, 'tax_' . $taxonomy );

		$allowed_ttids = $wpdb->get_col( $query_for_allowed_ttids );
		$new_ttids = array();

		foreach ( $changed_ttids as $ttid ) {

			if ( ! in_array( $ttid, $allowed_ttids ) ) {

				$wrong_term_where = array( 'object_id' => $post_id, 'term_taxonomy_id' => $ttid );

				if ( is_taxonomy_hierarchical( $taxonomy ) ) {
					// Hierarchical terms are simply deleted if they land on the wrong language
					$wpdb->delete( $wpdb->term_relationships, array( 'object_id' => $post_id, 'term_taxonomy_id' => $ttid ) );
				} else {

					/* Flat taxonomy terms could also be given via their names and not their ttids
					 * In this case we append the ttids resulting from these names to the $changed_ttids array,
					 * we do this only in the case of these terms actually being present in another but the
					 * posts' language.
					 */

					$query_for_term_name = $wpdb->prepare( "SELECT t.name FROM {$wpdb->terms} AS t JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id WHERE tt.term_taxonomy_id=%d", $ttid );
					$term_name           = $wpdb->get_var( $query_for_term_name );

					$ttid_in_correct_lang = false;

					if ( ! empty( $allowed_ttids ) ) {

						$in = wpml_prepare_in($allowed_ttids, "%d");
						// Try to get the ttid of a term in the correct language, that has the same
						$ttid_in_correct_lang = $wpdb->get_var( $wpdb->prepare( "SELECT tt.term_taxonomy_id
							FROM
								{$wpdb->terms} AS t
								JOIN {$wpdb->term_taxonomy} AS tt
									ON t.term_id = tt.term_id
							WHERE t.name=%s AND tt.taxonomy=%s AND tt.term_taxonomy_id IN ({$in})", $term_name, $taxonomy ) );
					}
					if ( ! $ttid_in_correct_lang ) {
						/* If we do not have a term by this name in the given taxonomy and language we have to create it.
						 * In doing so we must avoid interactions with filtering by wpml on this functionality and ensure uniqueness for the slug of the newly created term.
						 */

						$new_term = wp_insert_term( $term_name, $taxonomy, array( 'slug' => self::term_unique_slug( sanitize_title( $term_name ), $taxonomy, $post_lang ) ) );
						if ( isset( $new_term[ 'term_taxonomy_id' ] ) ) {
							$ttid_in_correct_lang = $new_term[ 'term_taxonomy_id' ];
							$trid                 = false;
							if ( $bulk ) {
								$trid = $sitepress->get_element_trid( $ttid, 'tax_' . $taxonomy );
							}
							$sitepress->set_element_language_details( $ttid_in_correct_lang, 'tax_' . $taxonomy, $trid, $post_lang );
						}
					}

					if ( ! in_array( $ttid_in_correct_lang, $changed_ttids ) ) {
						$wpdb->update( $wpdb->term_relationships, array( 'term_taxonomy_id' => $ttid_in_correct_lang ), $wrong_term_where );
						$new_ttids [ ] = $ttid_in_correct_lang;
					} else {
						$wpdb->delete( $wpdb->term_relationships, array('object_id'=>$post_id, 'term_taxonomy_id' => $ttid) );
					}
				}
			}
		}
		// Update term counts manually here, since using sql, will not trigger the updating of term counts automatically.
		wp_update_term_count ( array_merge ( $changed_ttids, $new_ttids ), $taxonomy );
	}

	/**
	 * Returns an array of all terms, that have a language suffix on them.
	 * This is used by troubleshooting functionality.
	 *
	 * @return array
	 */
	public static function get_all_terms_with_language_suffix() {
		global $wpdb;

		$lang_codes = $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages" );

		/* Build the expression to find all potential candidates for renaming.
		 * These must have the part "<space>@lang_code<space>" in them.
		 */

		$where_parts = array();

		foreach ( $lang_codes as $key => $code ) {
			$where_parts[ $key ] = "t.name LIKE '" . '% @' . $code . "%'";
		}

		$where = '(' . join( ' OR ', $where_parts ) . ')';

		$terms_with_suffix = $wpdb->get_results( "SELECT t.name, t.term_id, tt.taxonomy FROM {$wpdb->terms} AS t JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id WHERE {$where}" );

		$terms = array();

		foreach ( $terms_with_suffix as $term ) {

			if ( $term->name == WPML_Troubleshooting_Terms_Menu::strip_language_suffix( $term->name ) ) {
				continue;
			}

			$term_id = $term->term_id;

			$term_taxonomy_label = $term->taxonomy;

			$taxonomy = get_taxonomy($term->taxonomy);

			if ( $taxonomy && isset( $taxonomy->labels ) && isset( $taxonomy->labels->name ) ) {
				$term_taxonomy_label = $taxonomy->labels->name;
			}

			if ( isset( $terms[ $term_id ] ) && isset( $terms[ $term_id ][ 'taxonomies' ] ) ) {
				if ( ! in_array( $term_taxonomy_label, $terms[ $term_id ][ 'taxonomies' ] ) ) {
					$terms[ $term_id ][ 'taxonomies' ][ ] =$term_taxonomy_label;
				}
			} else {
				$terms[ $term_id ] = array( 'name' => $term->name, 'taxonomies' => array( $term_taxonomy_label ) );
			}
		}

		return $terms;
	}
}
