<?php

class WPML_Displayed_String_Filter extends WPML_WPDB_And_SP_User {

	protected $language;
	protected $original_cache = array();
	protected $name_cache = array();
	protected $untranslated_cache = array();
	protected $use_original_cache;
	protected $cache_is_warm = false;

	/**
	 * @param wpdb      $wpdb
	 * @param SitePress $sitepress
	 * @param string    $language
	 */
	public function __construct( &$wpdb, &$sitepress, $language, $existing_filter = null ) {
		parent::__construct( $wpdb, $sitepress );
		$this->language           = $language;
		
		if ( $existing_filter ) {
			$this->original_cache = $existing_filter->original_cache;
			$this->name_cache = $existing_filter->name_cache;
			$this->untranslated_cache = $existing_filter->untranslated_cache;
			$this->use_original_cache = $existing_filter->use_original_cache;
			$this->cache_is_warm = $existing_filter->cache_is_warm;
		} else {
			$this->use_original_cache = $this->use_original_cache();
		}
	}

	/**
	 * @param string       $untranslated_text
	 * @param string       $name
	 * @param string|array $context
	 * @param null|bool    $has_translation
	 *
	 * @return bool|false|string
	 */
	public function translate_by_name_and_context( $untranslated_text, $name, $context = "", &$has_translation = null ) {
		$res = $this->string_from_registered( $name, $context, $untranslated_text );
		if ( $res === false
		     && (bool) $untranslated_text === true
		     && $this->use_original_cache && substr( $name, 0, 10 ) !== 'URL slug: '
		) {
			// Didn't find a translation with the exact name and context
			// lookup translation from original text (but don't do it for URL slug)
			$key = md5( $untranslated_text );
			$res = isset( $this->original_cache[ $key ] ) ? $this->original_cache[ $key ] : false;
		}
		list( , , $key ) = $this->key_by_name_and_context( $name, $context );
		$has_translation = $res !== false && ! isset( $this->untranslated_cache[ $key ] ) ? true : null;
		$res             = $res === false && (bool) $untranslated_text === true ? $untranslated_text : $res;
		$res             = $res === false ? $this->get_original_value( $name, $context ) : $res;

		return $res;
	}

	/**
	 * @param string $name
	 * @param string $context
	 * @param string $untranslated_text
	 *
	 * Tries to retrieve a string from the cache and runs fallback logic for the default WP context
	 *
	 * @return bool
	 */
	protected function string_from_registered( $name, $context = "", $untranslated_text = "" ) {
		if ( $this->cache_is_warm === false ) {
			$this->warm_cache();
		}

		$res = $this->get_string_from_cache( $name, $context );
		$res = $res === false && $context === 'default' ? $this->get_string_from_cache( $name, 'WordPress' ) : $res;
		$res = $res === false && $context === 'WordPress' ? $this->get_string_from_cache( $name, 'default' ) : $res;

		return $res;
	}

	/**
	 * Populates the caches in this object
	 *
	 * @param string|null          $name
	 * @param string|string[]|null $context
	 * @param string               $untranslated_value
	 */
	protected function warm_cache( $name = null, $context = null, $untranslated_value = "" ) {
		$res_args    = array( ICL_TM_COMPLETE, $this->language );

		$filter = '';
		if ( null !== $name ) {
			list( , , $key ) = $this->key_by_name_and_context( $name, $context );
			if ( isset( $this->name_cache[ $key ] ) ) {
				return;
			} else {
				$name_cache[ $key ]               = $untranslated_value;
				$this->untranslated_cache[ $key ] = true;
				$filter                           = ' WHERE s.name=%s';
				$res_args[] = $name;
			}
		} else {
			$this->cache_is_warm = true;
		}

		$res_query   = "
					SELECT
						st.value AS tra,
						s.value AS org,
						s.domain_name_context_md5 AS ctx
					FROM {$this->wpdb->prefix}icl_strings s
					LEFT JOIN {$this->wpdb->prefix}icl_string_translations st
						ON s.id=st.string_id
							AND st.status=%d
							AND st.language=%s
					{$filter}
					";
		$res_prepare = $this->wpdb->prepare( $res_query, $res_args );
		$res         = $this->wpdb->get_results( $res_prepare, ARRAY_A );

		$name_cache = array();
		$warm_cache = array();
		foreach ( $res as $str ) {
			if ( $str['tra'] != null ) {
				$name_cache[ $str['ctx'] ] = &$str['tra'];
			} else {
				$name_cache[ $str['ctx'] ] = &$str['org'];
			}
			$this->untranslated_cache[ $str['ctx'] ] = $str['tra'] == '' ? true : null;
			// use the original cache if some string were registered with 'plugin XXXX' or 'theme XXXX' context
			// This is how they were registered before the 3.2 release of WPML
			if ( $this->use_original_cache ) {
				$warm_cache[ md5( stripcslashes( $str['org'] ) ) ] = stripcslashes( $name_cache[ $str['ctx'] ] );
			}
		}

		$this->original_cache = $warm_cache;
		$this->name_cache     = $name_cache;
	}
	
	protected function truncate_name_and_context( $name, $context) {
		if ( is_array( $context ) ) {
			$domain = isset ( $context[ 'domain' ] ) ? $context[ 'domain' ] : '';
			$gettext_context = isset ( $context[ 'context' ] ) ? $context[ 'context' ] : '';
		} else {
			$domain = $context;
			$gettext_context = '';
		}
		
		if (strlen( $name ) > WPML_STRING_TABLE_NAME_CONTEXT_LENGTH ) {
			// truncate to match length in db
			$name = substr( $name, 0, intval( WPML_STRING_TABLE_NAME_CONTEXT_LENGTH ) );
		}
		if (strlen( $domain ) > WPML_STRING_TABLE_NAME_CONTEXT_LENGTH ) {
			// truncate to match length in db
			$domain = substr( $domain, 0, intval( WPML_STRING_TABLE_NAME_CONTEXT_LENGTH ) );
		}
		
		// combine the $name and $gettext_context as the returned name
		// since this is the way we'll search the cache.
		return array( $name . $gettext_context, $domain );
	}

	protected function key_by_name_and_context( $name, $context ) {
		if ( is_array( $context ) ) {
			$domain          = isset ( $context['domain'] ) ? $context['domain'] : '';
			$gettext_context = isset ( $context['context'] ) ? md5( $context['context'] ) : '';
		} else {
			$domain          = $context;
			$gettext_context = '';
		}

		return array( $domain, $gettext_context, md5( $domain . $name . $gettext_context ) );
	}

	/**
	 * @param string       $name
	 * @param string|array $context
	 *
	 * @return string|bool|false
	 */
	private function get_original_value( $name, $context ) {

		static $domains_loaded = array();

		list( $domain, $gettext_context, $key ) = $this->key_by_name_and_context( $name, $context );
		if ( ! isset( $this->name_cache[ $key ] ) ) {
			if ( ! in_array( $domain, $domains_loaded ) ) {
				// preload all strings in this context
				$query   = $this->wpdb->prepare(
					"SELECT value, name FROM {$this->wpdb->prefix}icl_strings WHERE context = %s",
					$name,
					$domain
				);
				$results = $this->wpdb->get_results( $query );
				foreach ( $results as $string ) {
					$string_key = md5( $domain . $string->name . $gettext_context );
					if ( ! isset( $this->name_cache[ $string_key ] ) ) {
						$this->name_cache[ $string_key ] = $string->value;
					}
				}
				$domains_loaded[] = $domain;
			}

			if ( ! isset( $this->name_cache[ $key ] ) ) {
				$this->name_cache[ $key ] = false;
			}
		}

		return $this->name_cache[ $key ];
	}

	private function get_string_from_cache( $name, $context ) {
		list( $name, $context ) = $this->truncate_name_and_context( $name, $context );
		$key = md5( $context . $name );
		$res = isset( $this->name_cache[ $key ] ) ? $this->name_cache[ $key ] : false;

		return $res;
	}

	/**
	 * Checks if the site uses strings registered by a version older than WPML 3.2 and caches the result
	 *
	 * @return bool
	 */
	private function use_original_cache() {
		$string_settings = $this->sitepress->get_setting( 'st', array() );
		if ( ! isset( $string_settings['use_original_cache'] ) ) {
			// See if any strings have been registered with 'plugin XXXX' or 'theme XXXX' context
			// This is how they were registered before the 3.2 release of WPML
			// We only need to do this once and then save the result
			$query = "
						SELECT COUNT(*)
						FROM {$this->wpdb->prefix}icl_strings
						WHERE context LIKE 'plugin %' OR context LIKE 'theme %' ";
			$found = $this->wpdb->get_var( $query );

			$string_settings['use_original_cache'] = $found > 0 ? true : false;
			$this->sitepress->set_setting( 'st', $string_settings, true );
		}

		return (bool) $string_settings['use_original_cache'];
	}
}