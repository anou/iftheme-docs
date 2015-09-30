<?php

class WPML_Admin_String_Filter extends WPML_Displayed_String_Filter {

	private $registered_string_cache = array();
	
	/**
	 * @param wpdb                         $wpdb
	 * @param SitePress                    $sitepress
	 * @param string                       $language
	 * @param WPML_Displayed_String_Filter $existing_filter
	 */
	public function __construct( &$wpdb, &$sitepress, $language, $existing_filter ) {
		parent::__construct( $wpdb, $sitepress, $language, $existing_filter );
	}

	public function translate_by_name_and_context( $untranslated_text, $name, $context = "", &$has_translation = null ) {
		if ( $untranslated_text ) {
			$translation = $this->string_from_registered( $name, $context, $untranslated_text );
			if ( $translation === false && $untranslated_text !== false && $this->use_original_cache ) {
				// lookup translation from original text
				$key         = md5( $untranslated_text );
				$translation = isset( $this->original_cache[ $key ] ) ? $this->original_cache[ $key ] : false;
			}

			if ( $translation === false ) {
				$this->register_string( $context, $name, $untranslated_text );
				$translation = $untranslated_text;
			}
		} else {
			$translation = parent::translate_by_name_and_context( $untranslated_text, $name, $context );
		}
		$has_translation = $translation !== false && $translation != $untranslated_text;

		return $translation !== false ? $translation : $untranslated_text;
	}

	public function register_string( $context, $name, $value, $allow_empty_value = false ) {
		$name = trim( $name ) ? $name : md5( $value );
		/* cpt slugs - do not register them when scanning themes and plugins
		 * if name starting from 'URL slug: '
		 * and context is different from 'WordPress'
		 */
		if ( substr( $name, 0, 10 ) === 'URL slug: ' && 'WordPress' !== $context ) {
			return false;
		}

		list( $domain, $context ) = $this->key_by_name_and_context( $name, $context );
		list( $name, $context )   = $this->truncate_name_and_context( $name, $context );
		
		$res = $this->get_registered_string( $domain, $context, $name );
		if ( $res ) {
			$string_id = $res[ 'id' ];
			/*
			 * If Sticky Links plugin is active and set to change links in Strings,
			 * we need to process $value and change links into sticky before comparing
			 * with saved in DB $res->value.
			 * Otherwise after every String Translation screen refresh status of this string
			 * will be changed into 'needs update'
			 */
			$alp_settings = get_option( 'alp_settings' );
			if ( ! empty( $alp_settings['sticky_links_strings'] ) // do we have setting about sticky links in strings?
			     && $alp_settings['sticky_links_strings'] // is this set to TRUE?
			     && defined( 'WPML_STICKY_LINKS_VERSION' )
			) { // sticky links plugin is active?
				require_once ICL_PLUGIN_PATH . '/inc/absolute-links/absolute-links.class.php';
				$absolute_links_object = new AbsoluteLinks;
				$alp_broken_links      = array();
				$value                 = $absolute_links_object->_process_generic_text( $value, $alp_broken_links );
			}
			$update_string = array();
			if ( $value != $res[ 'value' ] ) {
				$update_string['value'] = $value;
			}
			if ( ! empty( $update_string ) ) {
				$this->wpdb->update( $this->wpdb->prefix . 'icl_strings', $update_string, array( 'id' => $string_id ) );
				$this->wpdb->update( $this->wpdb->prefix . 'icl_string_translations',
				                     array( 'status' => ICL_TM_NEEDS_UPDATE ),
				                     array( 'string_id' => $string_id ) );
				icl_update_string_status( $string_id );
			}
		} else {
			$string_id = $this->save_string( $value, $allow_empty_value, 'en', $domain, $context, $name );
		}

		global $WPML_Sticky_Links;
		if ( defined( 'WPML_TM_PATH' ) && ! empty( $WPML_Sticky_Links ) && $WPML_Sticky_Links->settings['sticky_links_strings'] ) {
			require_once WPML_TM_PATH . '/inc/translation-proxy/wpml-pro-translation.class.php';
			WPML_Pro_Translation::_content_make_links_sticky( $string_id, 'string', false );
		}
		$this->name_cache[ md5( $domain . $name . $context ) ] = $value;

		return $string_id;
	}
	
	private function get_registered_string( $domain, $context, $name ) {
		
		if ( ! isset( $this->registered_string_cache[ $domain ] ) ) {
			// preload all the strings for this domain.

			$query = $this->wpdb->prepare( "SELECT id, value, gettext_context, name FROM {$this->wpdb->prefix}icl_strings WHERE context=%s",
										   $domain,
										   $context,
										   $name );
			$res   = $this->wpdb->get_results( $query );
			$this->registered_string_cache[ $domain ] = array();
			
			foreach( $res as $string ) {
				$this->registered_string_cache[ $domain ][ md5( $domain . $string->name . $string->gettext_context ) ] = array( 'id'    => $string->id,
																															    'value' => $string->value
																															  );
			}
		}

		$key = md5( $domain . $name . $context );
		if ( ! isset( $this->registered_string_cache[ $domain ][ $key ] ) ) {
			$this->registered_string_cache[ $domain ][ $key ] = null;
		}
		return $this->registered_string_cache[ $domain ][ $key ];
	}
	
	

	private function save_string( $value, $allow_empty_value, $language, $domain, $context, $name ) {
		if ( ( $name || $value )
		     && ( ! empty( $value )
		          && is_scalar( $value ) && trim( $value ) || $allow_empty_value )
		) {
			$name   = trim( $name ) ? $name : md5( $value );
			$string = array(
				'language'                => $language,
				'context'                 => $domain,
				'gettext_context'         => $context,
				'domain_name_context_md5' => md5( $domain . $name . $context ),
				'name'                    => $name,
				'value'                   => $value,
				'status'                  => ICL_TM_NOT_TRANSLATED,
			);

			$this->wpdb->insert( $this->wpdb->prefix . 'icl_strings', $string );
			$string_id = $this->wpdb->insert_id;

			icl_update_string_status( $string_id );
			
			$key = md5( $domain . $name . $context );
			$this->registered_string_cache[ $domain ][ $key ] = array( 'id'    => $string_id,
																	   'value' => $value
																	 );
		} else {
			$string_id = 0;
		}

		return $string_id;
	}
}