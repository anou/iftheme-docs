<?php

class WPML_Slug_Translation {

	/** @var array $post_link_cache */
	private $post_link_cache = array();

	/** @var  SitePress $sitepress */
	private $sitepress;

	/** @var wpdb $wpdb */
	private $wpdb;
	
	/** @var array $translated_slugs */
	private $translated_slugs = array();

	/**
	 * @param SitePress               $sitepress_instance
	 * @param wpdb                    $wpdb_instance
	 */
	function __construct( &$sitepress_instance, &$wpdb_instance ) {
		$this->sitepress   = $sitepress_instance;
		$this->wpdb        = $wpdb_instance;
	}

	function init() {
		$slug_settings = $this->sitepress->get_setting( 'posts_slug_translation' );
		if ( ! empty( $slug_settings['on'] ) ) {
			add_filter( 'option_rewrite_rules', array( $this, 'rewrite_rules_filter' ), 1, 1 ); // high priority
			add_filter( 'post_type_link', array( $this, 'post_type_link_filter' ), 1, 4 ); // high priority
			add_filter( 'query_vars', array( $this, 'add_cpt_names' ), 1, 2 );
			add_filter( 'pre_get_posts', array( $this, 'filter_pre_get_posts' ), - 1000, 2 );
			// Slug translation API
			add_filter( 'wpml_get_translated_slug', array( $this, 'get_translated_slug' ), 1, 2 );
			add_filter( 'wpml_get_slug_translation_languages',
			            array( $this, 'get_slug_translation_languages_filter' ),
			            1,
			            2 );
		}
		add_action( 'icl_ajx_custom_call', array( $this, 'gui_save_options' ), 10, 2 );
		// Slug translation API
		add_filter( 'wpml_slug_translation_available', array( $this, 'slug_translation_available_filter' ), 1, 1 );
		add_action( 'wpml_activate_slug_translation', array( $this, 'activate_slug_translation_action' ), 1, 2 );
		add_action( 'wpml_save_cpt_sync_settings', array( $this, 'save_sync_options' ), 1, 0 );
		add_filter( 'wpml_get_slug_translation_url', array( $this, 'get_slug_translation_url_filter' ), 1, 1 );
	}

	private static function get_slug_by_type( $type ) {
		$post_type_obj = get_post_type_object ( $type );

		return isset( $post_type_obj->rewrite[ 'slug' ] ) ? trim ( $post_type_obj->rewrite[ 'slug' ], '/' ) : false;
	}

	static function rewrite_rules_filter( $value ) {
		global $sitepress, $sitepress_settings, $wpdb;

		if ( isset( $sitepress_settings[ 'st' ][ 'strings_language' ] ) ) {
			$strings_language = $sitepress_settings[ 'st' ][ 'strings_language' ];
		} else {
			$strings_language = false;
		}

		$current_language = $sitepress->get_current_language();
		if ( $current_language != $strings_language ) {

			$queryable_post_types = get_post_types( array( 'publicly_queryable' => true ) );

			foreach ( $queryable_post_types as $type ) {

				if ( ! isset( $sitepress_settings[ 'posts_slug_translation' ][ 'types' ][ $type ] ) || ! $sitepress_settings[ 'posts_slug_translation' ][ 'types' ][ $type ] || ! $sitepress->is_translated_post_type( $type ) ) {
					continue;
				}

				$slug = self::get_slug_by_type ( $type );
				if ( $slug === false ) {
					continue;
				}
				$slug_translation = $wpdb->get_var( $wpdb->prepare( "
                            SELECT t.value 
                            FROM {$wpdb->prefix}icl_string_translations t
                                JOIN {$wpdb->prefix}icl_strings s ON t.string_id = s.id
                            WHERE t.language = %s AND s.name = %s AND s.value = %s AND t.status = %d
                        ",
				                                                    $current_language,
				                                                    'URL slug: ' . $slug,
				                                                    $slug,
				                                                    ICL_TM_COMPLETE ) );

				if ( ! $slug_translation ) {
					$slug_translation = $slug;
					$slug             = $wpdb->get_var( $wpdb->prepare( "
                            SELECT s.value
                            FROM {$wpdb->prefix}icl_string_translations t
                                JOIN {$wpdb->prefix}icl_strings s ON t.string_id = s.id
                            WHERE t.language = %s AND s.name LIKE 'URL slug:%%' AND t.value = %s AND t.status = %d
                        ",
					                                                    $current_language,
					                                                    $slug,
					                                                    ICL_TM_COMPLETE ) );
				}

				$using_tags = false;

				/* case of slug using %tags% - PART 1 of 2 - START */
				if ( preg_match( '#%([^/]+)%#', $slug ) ) {
					$slug       = preg_replace( '#%[^/]+%#', '.+?', $slug );
					$using_tags = true;
				}
				if ( preg_match( '#%([^/]+)%#', $slug_translation ) ) {
					$slug_translation = preg_replace( '#%[^/]+%#', '.+?', $slug_translation );
					$using_tags       = true;
				}
				/* case of slug using %tags% - PART 1 of 2 - END */

				$buff_value = array();
				foreach ( (array) $value as $k => $v ) {

					if ( $slug && $slug != $slug_translation ) {
						if ( preg_match( '#^[^/]*/?' . preg_quote( $slug ) . '/#',
						                 $k ) && $slug != $slug_translation
						) {
							$k = str_replace( $slug . '/', $slug_translation . '/', $k );
						}

					}
					$buff_value[ $k ] = $v;
				}

				$value = $buff_value;
				unset( $buff_value );

				/* case of slug using %tags% - PART 2 of 2 - START */
				if ( $using_tags ) {
					if ( preg_match( '#\.\+\?#', $slug ) ) {
						$slug = preg_replace( '#\.\+\?#', '(.+?)', $slug );
					}
					if ( preg_match( '#\.\+\?#', $slug_translation ) ) {
						$slug_translation = preg_replace( '#\.\+\?#', '(.+?)', $slug_translation );
					}
					$buff_value = array();
					foreach ( $value as $k => $v ) {

						if ( trim( $slug ) && trim( $slug_translation ) && $slug != $slug_translation ) {
							if ( preg_match( '#^[^/]*/?' . preg_quote( $slug ) . '/#',
							                 $k ) && $slug != $slug_translation
							) {
								$k = str_replace( $slug . '/', $slug_translation . '/', $k );
							}
						}
						$buff_value[ $k ] = $v;
					}

					$value = $buff_value;
					unset( $buff_value );
				}
				/* case of slug using %tags% - PART 2 of 2 - END */

			}
		}

		return $value;
	}

	/**
	 * @param string      $slug
	 * @param string|bool $language
	 *
	 * @return string
	 */
	function get_translated_slug( $slug, $language = false ) {
		if ( $slug ) {
			$current_language = $this->sitepress->get_current_language();
			$language         = $language ? $language : $current_language;
			
			if ( !isset( $this->translated_slugs[ $slug ][ $language ] ) ) {
				
				$slugs_translations = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT t.value, t.language
										FROM {$this->wpdb->prefix}icl_strings s
										JOIN {$this->wpdb->prefix}icl_string_translations t ON t.string_id = s.id
										WHERE s.name = %s
										    AND (s.context = %s OR s.context = %s)
											AND t.status = %d
											AND t.value <> ''",
											'URL slug: ' . $slug,
											'default',
											'WordPress',
											ICL_TM_COMPLETE ) );
				
				foreach( $slugs_translations as $translation ) {
					$this->translated_slugs[ $slug ][ $translation->language ] = $translation->value;
				}
				
				// Add empty values for languages not found.
				
				foreach( $this->sitepress->get_active_languages() as $lang ) {
					if ( ! isset( $this->translated_slugs[ $slug ][ $lang[ 'code' ] ] ) ) {
						$this->translated_slugs[ $slug ][ $lang[ 'code' ] ] = '';
					}
				}
				
			}
			if ( $this->translated_slugs[ $slug ][ $language ] ) {
				$has_translation = true;
				$slug = $this->translated_slugs[ $slug ][ $language ];
			} else {
				$has_translation = false;
			}
			if ( $has_translation ) {
				return $slug;
			}
		} else {
			$has_translation = true;
		}

		return $has_translation ? $slug : $this->st_fallback( $slug, $language );
	}

	function post_type_link_filter( $post_link, $post, $leavename, $sample ) {
		if ( ! $this->sitepress->is_translated_post_type( $post->post_type )
		     || ! ( $ld = $this->sitepress->get_element_language_details( $post->ID, 'post_' . $post->post_type ) )
		) {
			return $post_link;
		}

		if ( isset( $this->post_link_cache[ $post->ID ][ $leavename . '#' . $sample ] ) ) {
			$post_link = $this->post_link_cache[ $post->ID ][ $leavename . '#' . $sample ];
		} else {
			$st_settings      = $this->sitepress->get_setting( 'st' );
			$strings_language = ! empty( $st_settings['strings_language'] ) ? $st_settings['strings_language'] : 'en';

			// fix permalink when object is not in the current language
			if ( $ld->language_code != $strings_language ) {
				$slug_settings = $this->sitepress->get_setting( 'posts_slug_translation' );
				$slug_settings = ! empty( $slug_settings['types'][ $post->post_type ] ) ? $slug_settings['types'][ $post->post_type ] : null;
				if ( (bool) $slug_settings === true ) {
					$slug_this = $this->get_slug_by_type( $post->post_type );
					$slug_real = $this->get_translated_slug( $slug_this, $ld->language_code );

					if ( empty( $slug_real ) ) {
						return $post_link;
					}

					global $wp_rewrite;

					if ( isset( $wp_rewrite->extra_permastructs[ $post->post_type ] ) ) {
						$struct_original = $wp_rewrite->extra_permastructs[ $post->post_type ]['struct'];

						$lslash                                                       = false !== strpos( $struct_original,
						                                                                                  '/' . $slug_this ) ? '/' : '';
						$wp_rewrite->extra_permastructs[ $post->post_type ]['struct'] = preg_replace( '@' . $lslash . $slug_this . '/@',
						                                                                              $lslash . $slug_real . '/',
						                                                                              $struct_original );
						remove_filter( 'post_type_link', array( $this, 'post_type_link_filter' ), 1 ); // high priority
						$post_link = get_post_permalink( $post->ID, $leavename, $sample );
						add_filter( 'post_type_link', array( $this, 'post_type_link_filter' ), 1, 4 ); // high priority
						$wp_rewrite->extra_permastructs[ $post->post_type ]['struct'] = $struct_original;
					} else {
						$post_link = str_replace( $slug_this . '=', $slug_real . '=', $post_link );
					}
				}
				$this->post_link_cache[ $post->ID ][ $leavename . '#' . $sample ] = $post_link;
			}
		}

		return $post_link;
	}

	/**
	 * @param string $slug
	 * @param string $language
	 *
	 * @return string
	 */
	private function st_fallback( $slug, $language ) {
		$wpdb = $this->wpdb;

		$cache_key_args     = array( $slug, $language );
		$cache_key          = implode( ':', array_filter( $cache_key_args ) );
		$cache_group        = 'get_translated_slug';
		$has_cached_value   = false;
		$slugs_translations = wp_cache_get( $cache_key, $cache_group, false, $has_cached_value );

		if ( ! $has_cached_value ) {
			$slugs_translations_sql      = "
										SELECT s.value as original, t.value
										FROM {$wpdb->prefix}icl_strings s
										JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id
										WHERE t.language = %s
										AND s.name LIKE %s
                                        AND t.status = %d
						";
			$slugs_translations_prepared = $wpdb->prepare( $slugs_translations_sql,
			                                               array(
				                                               $language,
				                                               'URL slug: %',
				                                               ICL_TM_COMPLETE
			                                               ) );
			$slugs_translations          = $wpdb->get_results( $slugs_translations_prepared, 'ARRAY_A' );
			wp_cache_set( $cache_key, $slugs_translations, $cache_group );
		}

		if ( (bool) $slugs_translations === true ) {
			foreach ( $slugs_translations as $slugs_row ) {
				if ( $slugs_row['original'] == $slug && ! empty( $slugs_row['value'] ) ) {
					$slug = $slugs_row['value'];
					break;
				}
			}
		}

		return $slug;
	}

	private static function get_all_slug_translations() {
		global $wpdb, $sitepress_settings;

		$cache_key   = 'WPML_Slug_Translation::get_all_slug_translations';

		$slugs_translations = wp_cache_get( $cache_key );
		
		if ( ! is_array( $slugs_translations ) ) {
			$in = '';
			
			if ( isset( $sitepress_settings[ 'posts_slug_translation' ][ 'types' ] ) ) {
				$types = $sitepress_settings[ 'posts_slug_translation' ][ 'types' ];
				foreach ( $types as $type => $state ) {
					if ( $state ) {
						if ( $in != '' ) {
							$in .= ', ';
						}
						$in .= "'URL slug: " . $type . "'";
					}
				}
			}
			
			if ( $in ) {
			
				$slugs_translations = $wpdb->get_col( $wpdb->prepare( "SELECT t.value
										FROM {$wpdb->prefix}icl_strings s
										JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id
										WHERE s.name IN ({$in})
											AND t.status = %d
											AND t.value <> ''",
											   ICL_TM_COMPLETE ) );
			} else {
				$slugs_translations = array( );
			}
			
			wp_cache_set( $cache_key, $slugs_translations );
		}
		
		return $slugs_translations;
	}
	
	public static function add_cpt_names( $qvars ) {

		$all_slugs_translations = self::get_all_slug_translations();
		$qvars                  = array_merge( $qvars, $all_slugs_translations );

		return $qvars;
	}

	function filter_pre_get_posts( $query ) {

		$all_slugs_translations = self::get_all_slug_translations();

		foreach ( $query->query as $slug => $post_name ) {
			if ( in_array( $slug, $all_slugs_translations ) ) {
				$new_slug = $this->get_translated_slug( $slug, 'en' );
				unset( $query->query[ $slug ] );
				$query->query[ $new_slug ]   = $post_name;
				$query->query[ 'name' ]      = $post_name;
				$query->query[ 'post_type' ] = $new_slug;
				unset( $query->query_vars[ $slug ] );
				$query->query_vars[ $new_slug ]   = $post_name;
				$query->query_vars[ 'name' ]      = $post_name;
				$query->query_vars[ 'post_type' ] = $new_slug;
				
			}
		}

		return $query;
	}

	static function gui_save_options( $action, $data ) {

		switch ( $action ) {
			case 'icl_slug_translation':
				global $sitepress;
				$iclsettings[ 'posts_slug_translation' ][ 'on' ] = intval( ! empty( $_POST[ 'icl_slug_translation_on' ] ) );
				$sitepress->save_settings( $iclsettings );
				echo '1|' . $iclsettings[ 'posts_slug_translation' ][ 'on' ];
				break;
		}

	}
	
	static function get_sql_to_get_string_id( $slug ) {
		global $wpdb;
		
		return $wpdb->prepare( "SELECT id
                                FROM {$wpdb->prefix}icl_strings
                                WHERE name = %s",
                                'URL slug: ' . $slug
                             );
	}
	
	static function get_translations ( $slug ) {
		global $sitepress, $sitepress_settings, $wpdb;
		
		$default_language = $sitepress->get_default_language( );
		if( $default_language != $sitepress_settings[ 'st' ][ 'strings_language' ] ) {
            $string_id_prepared = $wpdb->prepare( "SELECT s.id FROM {$wpdb->prefix}icl_strings s
												   JOIN {$wpdb->prefix}icl_string_translations st
												   ON st.string_id = s.id
												   WHERE st.language=%s AND name = %s ",
												   $default_language,
												   'URL slug: ' . $slug
												);
        }
        else {
            $string_id_prepared = self::get_sql_to_get_string_id( $slug );
        }
        $string_id = $wpdb->get_var( $string_id_prepared );
        $slug_translations = icl_get_string_translations_by_id( $string_id );
		
		return array( $string_id, $slug_translations );
	}

	static function save_sync_options() {
		global $sitepress, $wpdb;

		$slug_settings = $sitepress->get_setting( 'posts_slug_translation' );
		if ( isset( $slug_settings['on'] ) && $slug_settings['on'] && ! empty( $_POST['translate_slugs'] ) ) {
			foreach ( $_POST['translate_slugs'] as $type => $data ) {
				$slug_settings['types'][ $type ] = isset( $data['on'] ) ? intval( ! empty( $data['on'] ) ) : false;
				if ( empty( $slug_settings['types'][ $type ] ) ) {
					continue;
				}
				$post_type_obj = get_post_type_object( $type );
				$slug          = trim( $post_type_obj->rewrite['slug'], '/' );
				$string_id     = $wpdb->get_var( self::get_sql_to_get_string_id( $slug ) );
				$string_id     = empty( $string_id ) ? icl_register_string( 'WordPress',
				                                                            'URL slug: ' . $slug,
				                                                            $slug ) : $string_id;
				if ( $string_id ) {
					foreach ( $sitepress->get_active_languages() as $lang ) {
						$string_translation_settings = $sitepress->get_setting( 'st' );
						if ( $lang['code'] != $string_translation_settings['strings_language'] ) {
							$data['langs'][ $lang['code'] ] = join( '/',
							                                        array_map( array( 'WPML_Slug_Translation', 'sanitize' ),
							                                                   explode( '/',
							                                                            $data['langs'][ $lang['code'] ] ) ) );
							$data['langs'][ $lang['code'] ] = urldecode( $data['langs'][ $lang['code'] ] );
							icl_add_string_translation( $string_id,
							                            $lang['code'],
							                            $data['langs'][ $lang['code'] ],
							                            ICL_TM_COMPLETE );
						}
					}
					icl_update_string_status( $string_id );
				}
			}
		}

		$sitepress->set_setting( 'posts_slug_translation', $slug_settings, true );
	}
	
	static function sanitize( $slug ) {
		
		// we need to preserve the %
		$slug = str_replace( '%', '%45', $slug );
		$slug = sanitize_title_with_dashes( $slug );
		$slug = str_replace( '%45', '%', $slug );
		
		return $slug;
	}

	static function slug_translation_available_filter( $value ) {
		return true;
	}
	
	static function activate_slug_translation_action( $slug ) {
		global $wpdb, $sitepress_settings, $sitepress;
		
		$string_id = $wpdb->get_var( self::get_sql_to_get_string_id( $slug ) );

		if( ! $string_id ){
			icl_register_string( 'WordPress', 'URL slug: ' . $slug, $slug );
		}

		if( empty( $sitepress_settings[ 'posts_slug_translation' ][ 'on' ] ) || empty( $sitepress_settings[ 'posts_slug_translation' ][ 'types' ][ $slug ] ) ) {
			$iclsettings[ 'posts_slug_translation' ][ 'on' ] = 1;
			$iclsettings[ 'posts_slug_translation' ][ 'types' ][ $slug ] = 1;
			$sitepress->save_settings( $iclsettings );
		}
	}
	
	function get_slug_translation_languages_filter( $languages, $slug ) {
		global $wpdb;
		
        $slug_translation_languages = $wpdb->get_col( $wpdb->prepare(
																	 "SELECT tr.language
																	  FROM {$wpdb->prefix}icl_strings AS s
																	  LEFT JOIN {$wpdb->prefix}icl_string_translations AS tr
																	  ON s.id = tr.string_id
																	  WHERE s.name = %s AND
																			s.value = %s AND
																			tr.status = %s",
																	 'URL slug: ' . $slug,
																	 $slug,
																	 ICL_TM_COMPLETE));
		return $slug_translation_languages;
	}
	
	static function get_slug_translation_url_filter ( $url ) {
		if ( defined( 'WPML_TM_VERSION' ) ) {
			return admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=mcsetup#ml-content-setup-sec-7' );
		} else {
			return admin_url( 'admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/translation-options.php#ml-content-setup-sec-7' );
		}
	}
}