<?php

class WPML_Package_Helper {
	private   $default_language;
	protected $registered_strings;

	private $cache_group;

	function __construct() {
		$this->registered_strings = array();
		$this->cache_group        = 'string_package';
	}

	/**
	 * @param $package_id
	 */
	protected function delete_package( $package_id ) {
		// delete the strings and the translations

		$this->delete_package_strings( $package_id );
		$this->delete_package_translation_jobs( $package_id );
		$this->delete_package_translations( $package_id );

		global $wpdb;
		$delete_query   = "DELETE FROM {$wpdb->prefix}icl_string_packages WHERE id=%d";
		$delete_prepare = $wpdb->prepare( $delete_query, $package_id );
		$wpdb->query( $delete_prepare );
	}

	/**
	 * @param $package_id
	 *
	 * @return array
	 */
	protected function get_strings_ids_from_package_id( $package_id ) {
		global $wpdb;
		$string_ids_query   = "SELECT id FROM {$wpdb->prefix}icl_strings WHERE string_package_id=%d";
		$string_ids_prepare = $wpdb->prepare( $string_ids_query, $package_id );
		$string_ids         = $wpdb->get_col( $string_ids_prepare );

		return $string_ids;
	}

	/**
	 * @param $package_id
	 */
	protected function delete_package_strings( $package_id ) {
		$strings = $this->get_strings_ids_from_package_id( $package_id );

		foreach ( $strings as $string_id ) {
			do_action( 'wpml_st_delete_all_string_data', $string_id );
		}
	}

	protected function delete_package_translation_jobs( $package_id ) {
		$package = new WPML_Package( $package_id );
		$tm      = new WPML_Package_TM( $package );
		$tm->delete_translation_jobs();
	}

	protected function delete_package_translations( $package_id ) {
		$package = new WPML_Package( $package_id );
		$tm      = new WPML_Package_TM( $package );
		$tm->delete_translations();
	}

	protected function loaded() {
		$this->default_language = icl_get_default_language();
	}

	/**
	 * @param string             $string_value
	 * @param string             $string_name
	 * @param array|WPML_Package $package
	 * @param string             $string_title
	 * @param string             $string_type
	 */
	final function register_string_action( $string_value, $string_name, $package, $string_title, $string_type ) {
		$this->register_string_for_translation( $string_value, $string_name, $package, $string_title, $string_type );
	}

	/**
	 * @param string             $string_value
	 * @param string             $string_name
	 * @param array|WPML_Package $package
	 * @param string             $string_title
	 * @param string             $string_type
	 *
	 * @return string
	 */
	final function register_string_for_translation( $string_value, $string_name, $package, $string_title, $string_type ) {
		$package    = new WPML_Package( $package );
		$package_id = $package->ID;
		if ( ! $package_id ) {
			// need to create a new record.

			if ( $package->has_kind_and_name() ) {
				$package_id = $this->create_new_package( $package );
				$package    = new WPML_Package( $package_id );
			}
		}
		if ( $package_id ) {
			$this->maybe_update_package( $package );
			$tm = new WPML_Package_TM( $package );
			$tm->validate_translations();

			$this->init_package_registered_strings( $package_id );

			$string_name = $package->sanitize_string_name( $string_name );

			$this->set_package_registered_strings( $package_id, $string_type, $string_title, $string_name, $string_value );
			$this->register_string_with_wpml( $package, $string_name, $string_title, $string_type, $string_value );
		}

		return $string_value;
	}

	final function get_string_context_from_package( $package ) {
		$package = new WPML_Package( $package );

		return $package->get_string_context_from_package();
	}

	/**
	 * @param WPML_Package $package
	 * @param string       $string_name
	 * @param string       $string_title
	 * @param string       $string_type
	 * @param string       $string_value
	 */
	final function register_string_with_wpml( $package, $string_name, $string_title, $string_type, $string_value ) {
		$string_id = $this->get_string_id_from_package( $package, $string_name, $string_value );

		if ( $string_id ) {
			$package_id = $package->ID;
			$this->update_string_from_package( $package_id, $string_title, $string_type, $string_value, $string_id );
		}
	}

	final function translate_string( $string_value, $string_name, $package ) {
		$package = new WPML_Package( $package );

		$result = $string_value;

		if ( $package ) {
			$sanitized_string_name = $package->sanitize_string_name( $string_name );

			$result = $package->translate_string( $string_value, $sanitized_string_name );
		}

		return $result;
	}

	final function get_translated_strings( $strings, $package ) {
		$package = new WPML_Package( $package );

		return $package->get_translated_strings( $strings );
	}

	final function set_translated_strings( $translations, $package ) {
		$package = new WPML_Package( $package );
		$package->set_translated_strings( $translations );
	}

	final function get_translatable_types( $types ) {
		global $wpdb;

		$package_kinds = $wpdb->get_results( "SELECT kind, kind_slug FROM {$wpdb->prefix}icl_string_packages WHERE id>0" );

		// Add any packages found to the $types array
		foreach ( $package_kinds as $package_data ) {
			$package_kind_slug = $package_data->kind_slug;
			$package_kind      = $package_data->kind;
			$kinds_added       = array_keys( $types );
			if ( ! in_array( $package_kind_slug, $kinds_added ) ) {
				$translatable_type                        = new stdClass();
				$translatable_type->name                  = $package_kind_slug;
				$translatable_type->label                 = $package_kind;
				$translatable_type->prefix                = 'package';
				$translatable_type->labels                = new stdClass();
				$translatable_type->labels->singular_name = $package_kind;
				$translatable_type->labels->name          = $package_kind;
				$translatable_type->external_type         = 1;
				$types[ $package_kind_slug ]              = $translatable_type;
			}
		}

		return $types;
	}

	final function get_translatable_items( $items, $kind, $translation_filter ) {
		global $wpdb;

		$packages_query   = "SELECT name FROM {$wpdb->prefix}icl_string_packages WHERE kind = %s";
		$packages_prepare = $wpdb->prepare( $packages_query, $kind );
		$packages         = $wpdb->get_col( $packages_prepare );

		//TODO: deprecated, use the 'wpml_register_string_packages' action
		do_action( 'WPML_register_string_packages', $kind, $packages );
		do_action( 'wpml_register_string_packages', $kind, $packages );

		$packages_query   = "SELECT id FROM {$wpdb->prefix}icl_string_packages WHERE kind = %s";
		$packages_prepare = $wpdb->prepare( $packages_query, $kind );
		$packages         = $wpdb->get_col( $packages_prepare );

		//  for the Translation Dashboard

		$from_language = $translation_filter[ 'from_lang' ];
		foreach ( $packages as $package_id ) {
			$item = new WPML_Package( $package_id );

			$item_default_language = $item->get_default_language();
			if ( $item_default_language == $from_language ) {
				$tm = new WPML_Package_TM( $item );

				$translation_statuses = $tm->get_translation_statuses();
				if ( $translation_statuses ) {
					$items = array_merge( $items, $translation_statuses );
				} else {
					$items[ ] = $item;
				}
			}
		}

		return $items;
	}

	/**
	 * @param WPML_Package             $item
	 * @param int|WP_Post|WPML_Package $package
	 *
	 * @return bool|WPML_Package
	 */
	final public function get_translatable_item( $item, $package ) {
		$tm = new WPML_Package_TM( $item );

		return $tm->get_translatable_item( $package );
	}

	final function get_post_title( $title, $package_id ) {
		$package = new WPML_Package( $package_id );
		if ( $package ) {
			$title = $package->kind . ' - ' . $package->title;
		}

		return $title;
	}

	final function get_editor_string_name( $name, $package ) {
		$package    = new WPML_Package( $package );
		$package_id = $package->ID;
		$title      = $this->get_editor_string_element( $name, $package_id, 'title' );
		if ( $title && $title != '' ) {
			$name = $title;
		}

		return $name;
	}

	final function get_editor_string_style( $style, $field_type, $package ) {
		$package       = new WPML_Package( $package );
		$package_id    = $package->ID;
		$element_style = $this->get_editor_string_element( $field_type, $package_id, 'type' );
		if ( $element_style ) {
			$style = 0;
			if ( defined( $element_style ) ) {
				$style = constant( $element_style );
			}
		}

		return $style;
	}

	final public function get_element_id_from_package_filter( $default, $package_id ) {
		global $wpdb;
		$element_id_query   = 'SELECT name FROM ' . $wpdb->prefix . 'icl_string_packages WHERE ID=%d';
		$element_id_prepare = $wpdb->prepare( $element_id_query, $package_id );
		$element_id         = $wpdb->get_var( $element_id_prepare );
		if ( ! $element_id ) {
			$element_id = $default;
		}

		return $element_id;
	}

	final public function get_package_type( $type, $post_id ) {
		$package = new WPML_Package( $post_id );
		if ( $package ) {
			return $this->get_package_context( $package );
		} else {
			return $type;
		}
	}

	final public function get_package_type_prefix( $type, $post_id ) {
		if ( $type == 'package' ) {
			$package = new WPML_Package( $post_id );
			if ( $package ) {
				$type = $package->get_string_context_from_package();
			}
		}

		return $type;
	}

	/**
	 * @param              $language_for_element
	 * @param WPML_Package $current_document
	 *
	 * @return mixed
	 */
	final public function get_language_for_element( $language_for_element, $current_document ) {
		if ( $this->is_a_package( $current_document ) ) {
			global $sitepress;
			$language_for_element = $sitepress->get_language_for_element( $current_document->ID, $current_document->get_translation_element_type() );
		}

		return $language_for_element;
	}

	final protected function get_package_context( $package ) {
		$package = new WPML_Package( $package );

		return $package->kind_slug . '-' . $package->name;
	}

	final function delete_packages_ajax() {
		if ( ! $this->verify_ajax_call( 'wpml_package_nonce' ) ) {
			die( 'verification failed' );
		}
		$packages_ids = $_POST[ 'packages' ];

		$this->delete_packages( $packages_ids );

		exit;
	}

	final function delete_package_action( $name, $kind ) {
		$package_data[ 'name' ] = $name;
		$package_data[ 'kind' ] = $kind;

		$package                = new WPML_Package( $package_data );
		if ( $package && $this->is_a_package( $package ) ) {
			$this->delete_package( $package->ID );
			$this->flush_cache();
		}
	}

	final protected function delete_packages( $packages_ids ) {
		$flush_cache = false;

		foreach ( $packages_ids as $package_id ) {
			$this->delete_package( $package_id );

			$flush_cache = true;
		}
		if ( $flush_cache ) {
			$this->flush_cache();
		}
	}

	/**
	 * @param $string_name
	 * @param $package_id
	 * @param $column
	 *
	 * @return mixed
	 */
	final private function get_editor_string_element( $string_name, $package_id, $column ) {
		global $wpdb;

		$element_query    = "SELECT " . $column . "
						FROM {$wpdb->prefix}icl_strings
						WHERE string_package_id=%d AND name=%s";
		$element_prepared = $wpdb->prepare( $element_query, array( $package_id, $string_name ) );
		$element          = $wpdb->get_var( $element_prepared );

		return $element;
	}

	final private function flush_cache() {
		/** @var WP_Object_Cache $wp_object_cache */
		global $wp_object_cache;
		$cache = $wp_object_cache->__get( 'cache' );
		if ( isset( $cache[ $this->cache_group ] ) ) {
			foreach ( $cache[ $this->cache_group ] as $cache_key => $data ) {
				wp_cache_delete( $cache_key, $this->cache_group );
			}
		}
	}

	/**
	 * @param WPML_Package $package
	 *
	 * @return bool|mixed|null|string
	 */
	final private function create_new_package( $package ) {
		$package_id = $package->create_new_package_record();

		$tm = new WPML_Package_TM( $package );

		$tm->update_package_translations( true );

		return $package_id;
	}

	/**
	 * @param $package_id
	 */
	final private function init_package_registered_strings( $package_id ) {
		if ( ! isset( $this->registered_strings[ $package_id ] ) ) {
			$this->registered_strings[ $package_id ] = array( 'strings' => array() );
		}
	}

	/**
	 * @param $package_id
	 * @param $string_type
	 * @param $string_title
	 * @param $string_name
	 * @param $string_value
	 */
	final private function set_package_registered_strings( $package_id, $string_type, $string_title, $string_name, $string_value ) {
		$this->registered_strings[ $package_id ][ 'strings' ][ $string_name ] = array(
			'title' => $string_title,
			'kind'  => $string_type,
			'value' => $string_value
		);
	}

	/**
	 * @param $package
	 * @param $string_name
	 * @param $string_value
	 *
	 * @return bool|int|mixed
	 */
	final private function get_string_id_from_package( $package, $string_name, $string_value ) {
		$package = new WPML_Package( $package );

		return $package->get_string_id_from_package( $string_name, $string_value );
	}

	/**
	 * @param $package_id
	 * @param $string_title
	 * @param $string_type
	 * @param $string_value
	 * @param $string_id
	 */
	final private function update_string_from_package( $package_id, $string_title, $string_type, $string_value, $string_id ) {
		global $wpdb;

		$update_data  = array(
			'string_package_id' => $package_id,
			'type'              => $string_type,
			'title'             => $string_title,
			'value'             => $string_value
		);
		$update_where = array( 'id' => $string_id );

		$did_update = $wpdb->update( $wpdb->prefix . 'icl_strings', $update_data, $update_where );
		if ( $did_update ) {
			$status_update_data = array( 'status' => ICL_TM_NEEDS_UPDATE );
			$wpdb->update( $wpdb->prefix . 'icl_strings', $status_update_data, $update_where );
			$update_icl_string_translations_where = array( 'string_id' => $string_id );
			$wpdb->update( $wpdb->prefix . 'icl_string_translations', $status_update_data, $update_icl_string_translations_where );
			$translation_ids = $wpdb->get_col( $wpdb->prepare( " SELECT translation_id
                      FROM {$wpdb->prefix}icl_translations
                      WHERE trid = ( SELECT trid
                      FROM {$wpdb->prefix}icl_translations
                      WHERE element_id = %d
                        AND element_type LIKE 'package%%'
                      LIMIT 1 )", $package_id ) );
			if ( ! empty( $translation_ids ) ) {
				$wpdb->query( "UPDATE {$wpdb->prefix}icl_translation_status
                              SET needs_update = 1
                              WHERE translation_id IN (" . wpml_prepare_in( $translation_ids, '%d' ) . " ) " );
			}
		}

		$this->flush_cache();
	}

	final function get_external_id_from_package( $package ) {
		return 'external_' . $package[ 'kind_slug' ] . '_' . $package[ 'ID' ];
	}

	final function get_string_context( $package ) {
		return sanitize_title_with_dashes( $package[ 'kind_slug' ] . '-' . $package[ 'name' ] );
	}

	final function get_package_id( $package, $from_cache = true ) {
		global $wpdb;
		static $cache = array();

		if ( $this->is_a_package( $package ) ) {
			$package = object_to_array( $package );
		}

		$key = $this->get_string_context( $package );
		if ( ! $from_cache || ! array_key_exists( $key, $cache ) ) {
			$package_id_query   = "SELECT ID FROM {$wpdb->prefix}icl_string_packages WHERE kind_slug = %s AND name = %s";
			$package_id_prepare = $wpdb->prepare( $package_id_query, array( $package[ 'kind_slug' ], $package[ 'name' ] ) );
			$package_id         = $wpdb->get_var( $package_id_prepare );
			if ( ! $package_id ) {
				return false;
			}
			$cache[ $key ] = $package_id;
		}

		return $cache[ $key ];
	}

	final public function get_all_packages() {
		global $wpdb;
		$cache_key   = 'get_all_packages';
		$cache_found = false;

		$all_packages = wp_cache_get( $cache_key, $this->cache_group, false, $cache_found );

		if ( ! $cache_found ) {
			$all_packages            = array();
			$all_packages_data_query = "SELECT * FROM {$wpdb->prefix}icl_string_packages";
			$all_packages_data       = $wpdb->get_results( $all_packages_data_query );
			foreach ( $all_packages_data as $package_data ) {
				$package                      = new WPML_Package( $package_data );
				$all_packages[ $package->ID ] = $package;
			}
			if ( $all_packages ) {
				wp_cache_set( $cache_key, $all_packages, $this->cache_group );
			}
		}

		return $all_packages;
	}

	final protected function is_a_package( $element ) {
		return is_a( $element, 'WPML_Package' );
	}

	protected function verify_ajax_call( $ajax_action ) {
		return isset( $_POST[ 'wpnonce' ] ) && wp_verify_nonce( $_POST[ 'wpnonce' ], $ajax_action );
	}

	protected function sanitize_string_name( $string_name ) {
		$string_name = preg_replace( '/[ \[\]]+/', '-', $string_name );

		return $string_name;
	}

	public function refresh_packages() {

		//TODO: deprecated.
		// This is required to support Layouts 1.0
		do_action( 'WPML_register_string_packages', 'layout', array() );
		// TODO: END deprecated.

		do_action( 'wpml_register_string_packages' );
	}

	/**
	 * @param WPML_Package $package
	 */
	private function maybe_update_package( $package ) {
		if ( $package->new_title ) {
			$package->title = $package->new_title;
			$package->update_package_record();
		}
	}
}