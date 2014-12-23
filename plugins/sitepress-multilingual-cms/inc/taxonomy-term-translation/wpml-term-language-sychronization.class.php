<?php

	class WPML_Term_Language_Synchronization {

		private $taxonomy;
		private $data;
		private $missing_terms = array();

		public function __construct( $taxonomy ) {

			$this->taxonomy = $taxonomy;

			$this->data = $this->set_affected_ids();

			$this->prepare_missing_terms_data();

		}

		/**
		 * Assigns language information to terms that are to be treated as originals at the time of
		 * their taxonomy being set to translated instead of 'do nothing'.
		 */
		private function prepare_missing_originals() {
			global $sitepress;
			foreach ( $this->missing_terms as $ttid => $missing_lang_data ) {

				if ( ! isset( $this->data[ $ttid ][ 'tlang' ][ 'trid' ] ) ) {
					foreach ( $missing_lang_data as $lang => $post_ids ) {
						$sitepress->set_element_language_details( $ttid, 'tax_' . $this->taxonomy, null, $lang );
						$trid = $sitepress->get_element_trid( $ttid, 'tax_' . $this->taxonomy );
						if ( $trid ) {
							$this->data[ $ttid ][ 'tlang' ][ 'trid' ] = $trid;
							$this->data[ $ttid ][ 'tlang' ][ 'lang' ] = $lang;
							unset( $this->missing_terms[ $ttid ][ $lang ] );
							break;
						}

					}
				}

				if ( isset( $this->data[ $ttid ][ 'tlang' ][ 'trid' ] ) ) {
					$this->prepare_missing_translations( $this->data[ $ttid ][ 'tlang' ][ 'trid' ], $this->data[ $ttid ][ 'tlang' ][ 'lang' ], array_keys( $this->missing_terms[ $ttid ] ) );
				}
			}

		}

		/**
		 * Uses the API provided in \WPML_Terms_Translations to create missing term translations.
		 * These arise when a term, previously having been untranslated, is set to be translated
		 * and assigned to posts in more than one language.
		 *
		 * @param $trid int The trid value for which term translations are missing.
		 * @param $source_lang string The source language of this trid.
		 * @param $langs array The languages' codes for which term translations are missing.
		 */
		private function prepare_missing_translations( $trid, $source_lang, $langs ) {

			foreach ( $langs as $lang ) {
				WPML_Terms_Translations::create_automatic_translation( array(
					                                                       'lang_code'       => $lang,
					                                                       'source_language' => $source_lang,
					                                                       'trid'            => $trid,
					                                                       'taxonomy'        => $this->taxonomy
				                                                       ) );
			}
		}

		/**
		 * Uses the data retrieved from the database and saves information about,
		 * in need of fixing terms to this object.
		 */
		public function prepare_missing_terms_data() {
			global $sitepress;
			$default_lang = $sitepress->get_default_language();

			$data = $this->data;

			$missing = array();

			foreach ( $data as $ttid => $data_item ) {

				if ( empty( $data_item[ 'plangs' ] ) && empty( $data_item[ 'tlang' ] ) ) {
					$missing[ $ttid ][ $default_lang ] = - 1;
				} else {

					$affected_languages = array_diff( $data_item[ 'plangs' ], $data_item[ 'tlang' ] );

					if ( ! empty( $affected_languages ) ) {
						foreach ( $data_item[ 'plangs' ] as $post_id => $lang ) {
							if ( ! isset( $missing[ $ttid ][ $lang ] ) ) {
								$missing[ $ttid ][ $lang ] = array( $post_id );
							} else {
								$missing[ $ttid ][ $lang ][ ] = $post_id;
							}
						}
					}
				}

			}

			$this->missing_terms = $missing;

		}

		/**
		 * Retrieves all term_ids, and if applicable, their language and assigned to posts,
		 * in an associative array,
		 * which are in the situation of not being assigned to any language or in which a term
		 * is assigned to a post in a language different from its own.
		 *
		 * @return array
		 */
		private function set_affected_ids() {
			global $wpdb;

			$query_for_post_ids = $wpdb->prepare( "
				SELECT tl.trid AS trid, tl.ttid AS ttid, tl.tlang AS term_lang, tl.pid AS post_id, pl.plang AS post_lang
				FROM (
					SELECT
					o.object_id AS pid,
					tt.term_taxonomy_id AS ttid,
					i.language_code AS tlang,
					i.trid AS trid
				FROM
					{$wpdb->term_relationships} AS o
				RIGHT JOIN
					{$wpdb->term_taxonomy} AS tt
				ON
					o.term_taxonomy_id = tt.term_taxonomy_id
				LEFT JOIN
					{$wpdb->prefix}icl_translations AS i
				ON
					i.element_id = tt.term_taxonomy_id
					AND i.element_type = CONCAT('tax_', tt.taxonomy)
				WHERE tt.taxonomy = %s) AS tl
				LEFT JOIN
				( SELECT
					p.ID AS pid,
					i.language_code AS plang
				FROM
					{$wpdb->posts} AS p
				JOIN
					{$wpdb->prefix}icl_translations AS i
				ON
					i.element_id = p.ID AND
					i.element_type = CONCAT('post_', p.post_type)
				) AS pl
					ON tl.pid = pl.pid
				", $this->taxonomy );

			$ttid_pid_pairs = $wpdb->get_results( $query_for_post_ids );

			return $this->format_data( $ttid_pid_pairs );

		}

		/**
		 * @param $sql_result array of Objects holding the information retrieved in \self::set_affected_ids
		 *
		 * @return array The associative array to be returned by \self::set_affected_ids
		 */
		private function format_data( $sql_result ) {

			$res = array();

			foreach ( $sql_result as $pair ) {

				$res[ $pair->ttid ] = isset( $res[ $pair->ttid ] )
					? $res[ $pair->ttid ]
					: array(
						'tlang'  => array(),
						'plangs' => array(),
					);

				if ( $pair->term_lang && $pair->trid ) {
					$res[ $pair->ttid ][ 'tlang' ] = array( 'lang' => $pair->term_lang, 'trid' => $pair->trid );

				}
				if ( $pair->post_lang ) {
					$res[ $pair->ttid ][ 'plangs' ][ $pair->post_id ] = $pair->post_lang;
				}

			}

			return $res;

		}

		/**
		 * Performs an SQL query assigning all terms to their correct language equivalent if it exists.
		 * This should only be run after the previous functionality in here has finished.
		 * Afterwards the term counts are recalculated globally, since term assignments bypassing the WordPress Core,
		 * will not trigger any sort of update on those.
		 */
		private function reassign_terms() {
			global $wpdb;

			$update_query = $wpdb->prepare(
				"UPDATE {$wpdb->term_relationships} AS o,
					{$wpdb->prefix}icl_translations AS ic,
					{$wpdb->prefix}icl_translations AS iw,
					{$wpdb->prefix}icl_translations AS ip,
					{$wpdb->posts} AS p
						SET o.term_taxonomy_id = ic.element_id
						WHERE
						ic.trid = iw.trid
						AND ic.element_type = iw.element_type
						AND iw.element_id = o.term_taxonomy_id
						AND ic.language_code = ip.language_code
						AND ip.element_type = CONCAT('post_', p.post_type)
						AND ip.element_id = p.ID
						AND o.object_id = p.ID
						AND iw.element_type = %s", 'tax_' . $this->taxonomy );

			$wpdb->query( $update_query );

			$term_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s"
					, $this->taxonomy ) );

			// Do not run the count update on taxonomies that are not actually registered as proper taxonomy objects, e.g. WooCommerce Product Attributes.
			$taxonomy_object = get_taxonomy( $this->taxonomy );
			if ( $taxonomy_object && isset( $taxonomy_object->object_type ) ) {
				wp_update_term_count( $term_ids, $this->taxonomy );
			}
		}

		/**
		 * Wrapper for the two database actions performed by this object.
		 * First those terms are created that lack translations and then following that,
		 * the assignment of posts and languages is corrected, taking advantage of the newly created terms
		 * and resulting in a state of no conflicts in the form of a post language being different from
		 * an assigned terms language, remaining.
		 */
		public function set_translated() {

			$this->prepare_missing_originals();

			$this->reassign_terms();
		}

		/**
		 * Helper function for the installation process,
		 * finds all terms missing an entry in icl_translations and then
		 * assigns them the default language.
		 *
		 * @param $taxonomy string
		 */
		public static function set_initial_term_language( $taxonomy ) {
			global $wpdb, $sitepress;

			$element_ids_prepared = $wpdb->prepare( "
													SELECT tt.term_taxonomy_id
													FROM {$wpdb->term_taxonomy} AS tt
													LEFT OUTER JOIN {$wpdb->prefix}icl_translations AS i
													ON tt.term_taxonomy_id = i.element_id AND tt.taxonomy = CONCAT('tax_', i.element_type)
													WHERE taxonomy=%s AND i.element_id IS NULL
													", $taxonomy );

			$element_ids      = $wpdb->get_col( $element_ids_prepared );
			$default_language = $sitepress->get_default_language();

			foreach ( $element_ids as $id ) {

				$sitepress->set_element_language_details( $id, 'tax_' . $taxonomy, false, $default_language );
			}
		}



	}