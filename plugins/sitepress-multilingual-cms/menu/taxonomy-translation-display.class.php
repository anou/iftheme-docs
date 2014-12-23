<?php

class WPML_Taxonomy_Translation_Table_Display {

	private static function get_strings_translation_array() {

		$labels = array(
			"Show"               => __( "Show", "sitepress" ),
			"untranslated"       => __( "untranslated", "sitepress" ),
			"all"                => __( "all", "sitepress" ),
			"in"                 => __( "in", "sitepress" ),
			"to"                 => __( "to", "sitepress" ),
			"of"                 => __( "of", "sitepress" ),
			"taxonomy"           => __( "Taxonomy", "sitepress" ),
			"anyLang"            => __( "any language", "sitepress" ),
			"apply"              => __( "Refresh", "sitepress" ),
			"searchPlaceHolder"  => __( "search", "sitepress" ),
			"selectParent"       => __( "select parent", "sitepress" ),
			"taxToTranslate"     => __( "Select the taxonomy to translate: ", "sitepress" ),
			"translate"          => __( "Translate", "sitepress" ),
			"lowercaseTranslate" => __( "translate", "sitepress" ),
			"Name"               => __( "Name", "sitepress" ),
			"Slug"               => __( "Slug", "sitepress" ),
			"Description"        => __( "Description", "sitepress" ),
			"Ok"                 => __( "Ok", "sitepress" ),
			"Singular"           => __( "Singular", "sitepress" ),
			"Plural"             => __( "Plural", "sitepress" ),
			"cancel"             => __( "cancel", "sitepress" ),
			"loading"            => __( "loading", "sitepress" ),
			"Save"               => __( "Save", "sitepress" ),
			"currentPage"        => __( "Current page", "sitepress" ),
			"goToPreviousPage"   => __( "Go to previous page", "sitepress" ),
			"goToNextPage"       => __( "Go to the next page", "sitepress" ),
			"goToFirstPage"      => __( "Go to the first page", "sitepress" ),
			"goToLastPage"       => __( "Go to the last page", "sitepress" ),
			"items"              => __( "items", "sitepress" ),
			"item"               => __( "item", "sitepress" ),
			"summaryTerms"       => __( "This table summarizes all the terms for the taxonomy %taxonomy% and their translations. Click on any cell to translate.", "sitepress" ),
			"summaryLabels"      => __( "This table lets you translate the labels for the taxonomy %taxonomy%. These translations will appear in the WordPress admin menus.", "sitepress" ),
			"preparingTermsData" => __( "Loading ...", "sitepress" )

		);

		return $labels;
	}

	public static function enqueue_taxonomy_table_js() {

		$core_dependencies = array( "underscore", "jquery", "backbone" );
		wp_register_script( "templates", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/templates.js', $core_dependencies );
		$core_dependencies[ ] = "templates";
		wp_register_script( "main-util", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/util.js', $core_dependencies );

		wp_register_script( "main-model", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/main.js', $core_dependencies );
		$core_dependencies[ ] = "main-model";

		$dependencies = $core_dependencies;
		wp_register_script( "term-rows-collection", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/collections/term-rows.js', array_merge( $core_dependencies, array( "term-row-model" ) ) );
		$dependencies[ ] = "term-rows-collection";
		wp_register_script( "term-model", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/models/term.js', $core_dependencies );
		$dependencies[ ] = "term-model";
		wp_register_script( "taxonomy-model", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/models/taxonomy.js', $core_dependencies );
		$dependencies[ ] = "taxonomy-model";
		wp_register_script( "term-row-model", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/models/term-row.js', $core_dependencies );
		$dependencies[ ] = "term-row-model";
		wp_register_script( "filter-view", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/views/filter-view.js', $core_dependencies );
		$dependencies[ ] = "filter-view";
		wp_register_script( "nav-view", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/views/nav-view.js', $core_dependencies );
		$dependencies[ ] = "nav-view";
		wp_register_script( "table-view", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/views/table-view.js', $core_dependencies );
		$dependencies[ ] = "table-view";
		wp_register_script( "taxonomy-view", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/views/taxonomy-view.js', $core_dependencies );
		$dependencies[ ] = "taxonomy-view";
		wp_register_script( "term-popup-view", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/views/term-popup-view.js', $core_dependencies );
		$dependencies[ ] = "term-popup-view";
		wp_register_script( "label-popup-view", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/views/label-popup-view.js', $core_dependencies );
		$dependencies[ ] = "label-popup-view";
		wp_register_script( "term-row-view", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/views/term-row-view.js', $core_dependencies );
		$dependencies[ ] = "term-row-view";
		wp_register_script( "label-row-view", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/views/label-row-view.js', $core_dependencies );
		$dependencies[ ] = "label-row-view";
		wp_register_script( "term-rows-view", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/views/term-rows-view.js', $core_dependencies );
		$dependencies[ ] = "term-rows-view";
		wp_register_script( "term-view", ICL_PLUGIN_URL . '/res/js/taxonomy-translation/views/term-view.js', $core_dependencies );
		$dependencies[ ] = "term-view";

		foreach ( $dependencies as $dependency ) {
			if ( $dependency != "templates" ) {
				wp_localize_script( $dependency, "labels", self::get_strings_translation_array() );
			}
		}

		$need_enqueue    = $dependencies;
		$need_enqueue[ ] = "main-model";
		$need_enqueue[ ] = "main-util";
		$need_enqueue[ ] = "templates";

		foreach ( $need_enqueue as $handle ) {
			wp_enqueue_script( $handle );
		}

	}

	public static function wpml_get_table_taxonomies() {
		global $sitepress;

		$taxonomies = get_taxonomies( array(), 'objects' );

		$result = array( "taxonomies" => array(), "activeLanguages" => array() );
		$sitepress->set_admin_language();
		$active_langs = $sitepress->get_active_languages( );
		$default_lang = $sitepress->get_default_language();

		foreach ( $active_langs as $code => $lang ) {
			if ( is_array( $lang ) && isset( $lang[ 'display_name']) ) {
				$result[ "activeLanguages" ][ $code ] = array( "label" => $lang[ 'display_name' ] );
			}
		}

		if ( isset( $active_langs[ $default_lang ] ) ) {
			$def_lang = $active_langs[$default_lang];
			$result[ "activeLanguages" ] =  array( $default_lang => array( "label" => $def_lang[ 'display_name' ] ) ) + $result[ "activeLanguages" ] ;
		}

		foreach ( $taxonomies as $key => $tax ) {
			if ( $sitepress->is_translated_taxonomy( $key ) ) {
				$result[ "taxonomies" ][ $key ] = array(
					"label"         => $tax->label,
					"singularLabel" => $tax->labels->singular_name,
					"hierarchical"  => $tax->hierarchical,
					"name"          => $key
				);
			}
		}

		wp_send_json( $result );
	}

	public static function  get_label_translations( $taxonomy ) {
		global $sitepress, $wpdb;

		$return = false;

		$taxonomy_object = get_taxonomy( $taxonomy );

		$default_lang = $sitepress->get_default_language();
		$st_settings  = $sitepress->get_setting( 'st' );
		$default_lang = isset( $st_settings[ 'strings_language' ] ) ? $st_settings[ 'strings_language' ] : $default_lang;

		// Careful index checking here, otherwise some of those private taxonomies used by WooCommerce will result in errors here.
		if ( defined( 'WPML_ST_FOLDER' )
		     && $taxonomy_object
		     && isset( $taxonomy_object->label )
		     && isset( $taxonomy_object->labels )
		     && isset( $taxonomy_object->labels->singular_name )
		) {

			$label          = $taxonomy_object->label;
			$singular_label = $taxonomy_object->labels->singular_name;
			$return         = array(
				$default_lang => array(
					'singular' => $singular_label,
					'general'  => $label,
					'original' => true
				)
			);

			$active_lang_codes = array_keys( $sitepress->get_active_languages( true ) );

			foreach ( $active_lang_codes as $language ) {

				$singular = $wpdb->get_row( $wpdb->prepare( "SELECT t.value, t.string_id FROM {$wpdb->prefix}icl_string_translations t
                        JOIN {$wpdb->prefix}icl_strings s ON t.string_id = s.id
                        WHERE s.context='WordPress' AND ( s.name = %s OR t.value = %s ) AND t.language=%s",
				                                            'taxonomy singular name: ' . $singular_label,
				                                            $singular_label,
				                                            $language ) );
				$general  = $wpdb->get_row( $wpdb->prepare( "SELECT t.value, t.string_id  FROM {$wpdb->prefix}icl_string_translations t
                        JOIN {$wpdb->prefix}icl_strings s ON t.string_id = s.id
                        WHERE s.context='WordPress' AND ( s.name = %s OR t.value = %s ) AND t.language=%s",
				                                            'taxonomy general name: ' . $label,
				                                            $label,
				                                            $language ) );

				$update_singular = ( ! isset( $return [ $language ][ 'singular' ] ) || ! $return [ $language ][ 'singular' ] ) && $singular != null;
				$update_plural   = ( ! isset( $return [ $language ][ 'general' ] ) || $return [ $language ][ 'general' ] ) && $general != null;

				if ( $update_singular && $update_plural ) {
					$return [ $language ][ 'singular' ] = $singular->value;
					$return [ 'id_singular' ]           = $singular->string_id;
					$return [ $language ][ 'general' ]  = $general->value;
					$return [ 'id_general' ]            = $general->string_id;
				}


				if ( ! isset( $return [ 'id_singular' ] ) || ! $return [ 'id_singular' ] || ! isset( $return [ 'id_general' ] ) || ! $return [ 'id_singular' ] ) {
					$return [ 'id_singular' ] = icl_register_string( 'WordPress',
					                                                 'taxonomy singular name: ' . $singular_label,
					                                                 $singular_label );
					$return [ 'id_general' ]  = icl_register_string( 'WordPress',
					                                                 'taxonomy general name: ' . $label,
					                                                 $label );

				}
			}
		}

		$return[ 'st_default_lang' ] = $default_lang;

		return $return;
	}

	public static function wpml_get_terms_and_labels_for_taxonomy_table() {
		global $sitepress;
		$args     = array();
		$taxonomy = false;

		if ( isset( $_POST[ 'page' ] ) ) {
			$args[ 'page' ] = $_POST[ 'page' ];
		}

		if ( isset( $_POST[ 'perPage' ] ) ) {
			$args[ 'per_page' ] = $_POST[ 'perPage' ];
		}

		if ( isset( $_POST[ 'taxonomy' ] ) ) {
			$taxonomy = $_POST[ 'taxonomy' ];
		}

		if ( $taxonomy ) {
			$terms  = WPML_Taxonomy_Translation::get_terms_for_taxonomy_translation_screen( $taxonomy, $args );
			$labels = self::get_label_translations( $taxonomy );
			$def_lang = $sitepress->get_default_language();
			wp_send_json( array( "terms" => $terms, "taxLabelTranslations" => $labels, "defaultLanguage" => $def_lang ) );
		} else {
			wp_send_json_error();
		}
	}

}
