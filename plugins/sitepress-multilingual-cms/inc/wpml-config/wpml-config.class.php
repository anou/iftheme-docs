<?php
class WPML_Config
{
	static $wpml_config_files = array();
    static $active_plugins = array();

	public static function load_config()
	{
		global $pagenow;

		if ( !( is_admin() && !wpml_is_ajax() && ( !isset( $_POST[ 'action' ] ) || $_POST[ 'action' ] != 'heartbeat' ) ) ) {
			return;
		}

		$white_list_pages = array(
			'theme_options',
			'plugins.php',
			'themes.php',
			ICL_PLUGIN_FOLDER . '/menu/languages.php',
			ICL_PLUGIN_FOLDER . '/menu/theme-localization.php',
			ICL_PLUGIN_FOLDER . '/menu/translation-options.php',
		);
		if (defined('WPML_ST_FOLDER')) {
			$white_list_pages[] = WPML_ST_FOLDER . '/menu/string-translation.php';
		}
		if(defined('WPML_TM_FOLDER')) {
			$white_list_pages[] = WPML_TM_FOLDER . '/menu/main.php';
		}

		//Runs the load config process only on specific pages
		$current_page = isset($_GET[ 'page' ]) ? $_GET[ 'page' ] : null;
		if((isset( $current_page ) && in_array( $current_page, $white_list_pages)) || (isset($pagenow) && in_array($pagenow, $white_list_pages))) {
			self::load_config_run();
		}
	}

	static function load_config_run() {
		global $sitepress;
		self::load_config_pre_process();
		self::load_plugins_wpml_config();
		self::load_theme_wpml_config();
		self::parse_wpml_config_files();
		self::load_config_post_process();
		$sitepress->save_settings();
	}

	static function load_config_pre_process()
	{
		global $iclTranslationManagement;
		$tm_settings = $iclTranslationManagement->settings;

		$tm_settings[ '__custom_types_readonly_config_prev' ] = ( isset( $tm_settings[ 'custom_types_readonly_config' ] ) && is_array( $tm_settings[ 'custom_types_readonly_config' ] ) ) ? $tm_settings[ 'custom_types_readonly_config' ] : array();
		$tm_settings[ 'custom_types_readonly_config' ]        = array();

		$tm_settings[ '__custom_fields_readonly_config_prev' ] = ( isset( $tm_settings[ 'custom_fields_readonly_config' ] ) && is_array( $tm_settings[ 'custom_fields_readonly_config' ] ) ) ? $tm_settings[ 'custom_fields_readonly_config' ] : array();
		$tm_settings[ 'custom_fields_readonly_config' ]        = array();
	}

	static function load_plugins_wpml_config()
	{
		if ( is_multisite() ) {
			// Get multi site plugins
			$plugins = get_site_option( 'active_sitewide_plugins' );
			if ( !empty( $plugins ) ) {
				foreach ( $plugins as $p => $dummy ) {
                    if(!self::check_on_config_file($dummy)){
                        continue;
                    }
					$plugin_slug = dirname( $p );
					$config_file = WP_PLUGIN_DIR . '/' . $plugin_slug . '/wpml-config.xml';
					if ( trim( $plugin_slug, '\/.' ) && file_exists( $config_file ) ) {
						self::$wpml_config_files[ ] = $config_file;
					}
				}
			}
		}

		// Get single site or current blog active plugins
		$plugins = get_option( 'active_plugins' );
		if ( !empty( $plugins ) ) {
			foreach ( $plugins as $p ) {
                if(!self::check_on_config_file($p)){
                    continue;
                }

				$plugin_slug = dirname( $p );
				$config_file = WP_PLUGIN_DIR . '/' . $plugin_slug . '/wpml-config.xml';
				if ( trim( $plugin_slug, '\/.' ) && file_exists( $config_file ) ) {
					self::$wpml_config_files[ ] = $config_file;
				}
			}
		}

		// Get the must-use plugins
		$mu_plugins = wp_get_mu_plugins();

		if ( !empty( $mu_plugins ) ) {
			foreach ( $mu_plugins as $mup ) {
                if(!self::check_on_config_file($mup)){
                    continue;
                }

				$plugin_dir_name  = dirname( $mup );
				$plugin_base_name = basename( $mup, ".php" );
				$plugin_sub_dir   = $plugin_dir_name . '/' . $plugin_base_name;
				if ( file_exists( $plugin_sub_dir . '/wpml-config.xml' ) ) {
					$config_file                = $plugin_sub_dir . '/wpml-config.xml';
					self::$wpml_config_files[ ] = $config_file;
				}
			}
		}

		return self::$wpml_config_files;
	}

    static function check_on_config_file( $name ){

        if(empty(self::$active_plugins)){
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            self::$active_plugins = get_plugins();
        }
        $config_index_file_data = maybe_unserialize(get_option('wpml_config_index'));
        $config_files_arr = maybe_unserialize(get_option('wpml_config_files_arr'));

        if(!$config_index_file_data || !$config_files_arr){
            return true;
        }


        if(isset(self::$active_plugins[$name])){
            $plugin_info = self::$active_plugins[$name];
            $plugin_slug = dirname( $name );
            $name = $plugin_info['Name'];
            $config_data = $config_index_file_data->plugins;
            $config_files_arr = $config_files_arr->plugins;
            $config_file = WP_PLUGIN_DIR . '/' . $plugin_slug . '/wpml-config.xml';
            $type = 'plugin';

        }else{
            $config_data = $config_index_file_data->themes;
            $config_files_arr = $config_files_arr->themes;
            $config_file = get_template_directory() . '/wpml-config.xml';
            $type = 'theme';
        }

        foreach($config_data as $item){
            if($name == $item->name && isset($config_files_arr[$item->name])){
                if($item->override_local || !file_exists( $config_file )){
                    end(self::$wpml_config_files);
                    $key = key(self::$wpml_config_files)+1;
                    self::$wpml_config_files[$key] = new stdClass();
                    self::$wpml_config_files[$key]->config = icl_xml2array($config_files_arr[$item->name]);
                    self::$wpml_config_files[$key]->type = $type;
                    self::$wpml_config_files[$key]->admin_text_context = basename( dirname( $config_file ) );;
                    return false;
                }else{
                    return true;
                }
            }
        }

        return true;

    }

	static function load_theme_wpml_config()
	{
        $theme_data = wp_get_theme();
        if(!self::check_on_config_file($theme_data->get('Name'))){
            return self::$wpml_config_files;
        }

		if ( get_template_directory() != get_stylesheet_directory() ) {
			$config_file = get_stylesheet_directory() . '/wpml-config.xml';
			if ( file_exists( $config_file ) ) {
				self::$wpml_config_files[ ] = $config_file;
			}
		}

		$config_file = get_template_directory() . '/wpml-config.xml';
		if ( file_exists( $config_file ) ) {
			self::$wpml_config_files[ ] = $config_file;
		}

		return self::$wpml_config_files;
	}

	static function parse_wpml_config_files()
	{
		if ( !empty( self::$wpml_config_files ) ) {

			$config_all[ 'wpml-config' ] = array(
				'custom-fields'              => array(),
				'custom-types'               => array(),
				'taxonomies'                 => array(),
				'admin-texts'                => array(),
				'language-switcher-settings' => array()
			);

			foreach ( self::$wpml_config_files as $file ) {
                if(is_object($file)){
                    $config = $file->config;
                    $type = $file->type;
                    $admin_text_context = $file->admin_text_context;
                }else{
				$config = icl_xml2array( file_get_contents( $file ) );
                }

				if ( isset( $config[ 'wpml-config' ] ) ) {

					//custom-fields
					if ( isset( $config[ 'wpml-config' ][ 'custom-fields' ][ 'custom-field' ] ) ) {
						if ( isset( $config[ 'wpml-config' ][ 'custom-fields' ][ 'custom-field' ][ 'value' ] ) ) { //single
							$config_all[ 'wpml-config' ][ 'custom-fields' ][ 'custom-field' ][ ] = $config[ 'wpml-config' ][ 'custom-fields' ][ 'custom-field' ];
						} else {
							foreach ( (array) $config[ 'wpml-config' ][ 'custom-fields' ][ 'custom-field' ] as $cf ) {
								$config_all[ 'wpml-config' ][ 'custom-fields' ][ 'custom-field' ][ ] = $cf;
							}
						}
					}

					//custom-types
					if ( isset( $config[ 'wpml-config' ][ 'custom-types' ][ 'custom-type' ] ) ) {
						if ( isset( $config[ 'wpml-config' ][ 'custom-types' ][ 'custom-type' ][ 'value' ] ) ) { //single
							$config_all[ 'wpml-config' ][ 'custom-types' ][ 'custom-type' ][ ] = $config[ 'wpml-config' ][ 'custom-types' ][ 'custom-type' ];
						} else {
							foreach ( (array) $config[ 'wpml-config' ][ 'custom-types' ][ 'custom-type' ] as $cf ) {
								$config_all[ 'wpml-config' ][ 'custom-types' ][ 'custom-type' ][ ] = $cf;
							}
						}
					}

					//taxonomies
					if ( isset( $config[ 'wpml-config' ][ 'taxonomies' ][ 'taxonomy' ] ) ) {
						if ( isset( $config[ 'wpml-config' ][ 'taxonomies' ][ 'taxonomy' ][ 'value' ] ) ) { //single
							$config_all[ 'wpml-config' ][ 'taxonomies' ][ 'taxonomy' ][ ] = $config[ 'wpml-config' ][ 'taxonomies' ][ 'taxonomy' ];
						} else {
							foreach ( (array) $config[ 'wpml-config' ][ 'taxonomies' ][ 'taxonomy' ] as $cf ) {
								$config_all[ 'wpml-config' ][ 'taxonomies' ][ 'taxonomy' ][ ] = $cf;
							}
						}
					}

					//admin-texts
					if ( isset( $config[ 'wpml-config' ][ 'admin-texts' ][ 'key' ] ) ) {

						$type = ( dirname( $file ) == get_template_directory() || dirname( $file ) == get_stylesheet_directory() ) ? 'theme' : 'plugin';

						$admin_text_context = basename( dirname( $file ) );


						if ( ! is_numeric( key( @current( $config[ 'wpml-config' ][ 'admin-texts' ] ) ) ) ) { //single
							$config[ 'wpml-config' ][ 'admin-texts' ][ 'key' ][ 'type' ]    = $type;
							$config[ 'wpml-config' ][ 'admin-texts' ][ 'key' ][ 'context' ] = $admin_text_context;
							$config_all[ 'wpml-config' ][ 'admin-texts' ][ 'key' ][ ]       = $config[ 'wpml-config' ][ 'admin-texts' ][ 'key' ];
						} else {
							foreach ( (array) $config[ 'wpml-config' ][ 'admin-texts' ][ 'key' ] as $cf ) {
								$cf[ 'type' ]                                             = $type;
								$cf[ 'context' ]                                          = $admin_text_context;
								$config_all[ 'wpml-config' ][ 'admin-texts' ][ 'key' ][ ] = $cf;
							}
						}
					}

					//language-switcher-settings
					if ( isset( $config[ 'wpml-config' ][ 'language-switcher-settings' ][ 'key' ] ) ) {
						if ( !is_numeric( key( $config[ 'wpml-config' ][ 'language-switcher-settings' ][ 'key' ] ) ) ) { //single
							$config_all[ 'wpml-config' ][ 'language-switcher-settings' ][ 'key' ][ ] = $config[ 'wpml-config' ][ 'language-switcher-settings' ][ 'key' ];
						} else {
							foreach ( $config[ 'wpml-config' ][ 'language-switcher-settings' ][ 'key' ] as $cf ) {
								$config_all[ 'wpml-config' ][ 'language-switcher-settings' ][ 'key' ][ ] = $cf;
							}
						}
					}
				}
			}

			$config_all = apply_filters( 'icl_wpml_config_array', $config_all );

			self::parse_wpml_config( $config_all );
		}
	}

	static function load_config_post_process()
	{
		global $iclTranslationManagement;

		$changed = false;
		if ( isset( $iclTranslationManagement->settings[ '__custom_types_readonly_config_prev' ] ) ) {
			foreach ( $iclTranslationManagement->settings[ '__custom_types_readonly_config_prev' ] as $pk => $pv ) {
				if ( !isset( $iclTranslationManagement->settings[ 'custom_types_readonly_config' ][ $pk ] ) || $iclTranslationManagement->settings[ 'custom_types_readonly_config' ][ $pk ] != $pv ) {
					$changed = true;
					break;
				}
			}
		}
		if ( isset( $iclTranslationManagement->settings[ 'custom_types_readonly_config' ] ) ) {
			foreach ( $iclTranslationManagement->settings[ 'custom_types_readonly_config' ] as $pk => $pv ) {
				if ( !isset( $iclTranslationManagement->settings[ '__custom_types_readonly_config_prev' ][ $pk ] ) || $iclTranslationManagement->settings[ '__custom_types_readonly_config_prev' ][ $pk ] != $pv ) {
					$changed = true;
					break;
				}
			}
		}
		if ( isset( $iclTranslationManagement->settings[ 'custom_fields_readonly_config' ]  ) && isset($iclTranslationManagement->settings[ '__custom_fields_readonly_config_prev' ]) ) {
			foreach ( $iclTranslationManagement->settings[ '__custom_fields_readonly_config_prev' ] as $cf ) {
				if ( !in_array( $cf, $iclTranslationManagement->settings[ 'custom_fields_readonly_config' ] ) ) {
					$changed = true;
					break;
				}
			}

			foreach ( $iclTranslationManagement->settings[ 'custom_fields_readonly_config' ] as $cf ) {
				if ( !in_array( $cf, $iclTranslationManagement->settings[ '__custom_fields_readonly_config_prev' ] ) ) {
					$changed = true;
					break;
				}
			}
		}

		if ( $changed ) {
			$iclTranslationManagement->save_settings();
		}


	}

	static function parse_wpml_config( $config )
	{
		global $sitepress, $sitepress_settings, $iclTranslationManagement;

		// custom fields
		self::parse_custom_fields( $config );

		// custom types
		self::parse_custom_types( $config );

		// taxonomies
		self::parse_taxonomies( $config );

		// admin texts
		self::parse_admin_texts( $config );

		// language-switcher-settings
		if ( empty( $sitepress_settings[ 'language_selector_initialized' ] ) || ( isset( $_GET[ 'restore_ls_settings' ] ) && $_GET[ 'restore_ls_settings' ] == 1 ) ) {
			if ( !empty( $config[ 'wpml-config' ][ 'language-switcher-settings' ] ) ) {

				if ( !is_numeric( key( $config[ 'wpml-config' ][ 'language-switcher-settings' ][ 'key' ] ) ) ) {
					$cfgsettings[ 0 ] = $config[ 'wpml-config' ][ 'language-switcher-settings' ][ 'key' ];
				} else {
					$cfgsettings = $config[ 'wpml-config' ][ 'language-switcher-settings' ][ 'key' ];
				}
				$iclsettings = $iclTranslationManagement->_read_settings_recursive( $cfgsettings );

				$iclsettings[ 'language_selector_initialized' ] = 1;

				$sitepress->save_settings( $iclsettings );

				if ( !empty( $sitepress_settings[ 'setup_complete' ] ) && !empty( $_GET[ 'page' ] ) ) {
					wp_redirect( admin_url( 'admin.php?page=' . $_GET[ 'page' ] . '&icl_ls_reset=default#icl_save_language_switcher_options' ) );
				}
			}
		}
	}

	/**
	 * @param $config
	 *
	 * @return mixed
	 */
	protected static function parse_custom_fields( $config )
	{
		global $iclTranslationManagement;
		if ( !empty( $config[ 'wpml-config' ][ 'custom-fields' ] ) ) {
			if ( !is_numeric( key( current( $config[ 'wpml-config' ][ 'custom-fields' ] ) ) ) ) {
				$cf[ 0 ] = $config[ 'wpml-config' ][ 'custom-fields' ][ 'custom-field' ];
			} else {
				$cf = $config[ 'wpml-config' ][ 'custom-fields' ][ 'custom-field' ];
			}
			foreach ( $cf as $c ) {
				if ( $c[ 'attr' ][ 'action' ] == 'translate' ) {
					$action = 2;
				} elseif ( $c[ 'attr' ][ 'action' ] == 'copy' ) {
					$action = 1;
				} else {
					$action = 0;
				}
				$iclTranslationManagement->settings[ 'custom_fields_translation' ][ $c[ 'value' ] ] = $action;
				if (isset($iclTranslationManagement->settings[ 'custom_fields_readonly_config' ]) && is_array( $iclTranslationManagement->settings[ 'custom_fields_readonly_config' ] ) && !in_array( $c[ 'value' ], $iclTranslationManagement->settings[ 'custom_fields_readonly_config' ] ) ) {
					$iclTranslationManagement->settings[ 'custom_fields_readonly_config' ][ ] = $c[ 'value' ];
				}

			}
		}

	}

	/**
	 * @param $config
	 *
	 * @return array
	 */
	protected static function parse_custom_types( $config )
	{
		global $sitepress, $iclTranslationManagement;
		$cf = array();
		$custom_posts_sync_option = array();

		if ( !empty( $config[ 'wpml-config' ][ 'custom-types' ] ) ) {
			if ( !is_numeric( key( current( $config[ 'wpml-config' ][ 'custom-types' ] ) ) ) ) {
				$cf[ 0 ] = $config[ 'wpml-config' ][ 'custom-types' ][ 'custom-type' ];
			} else {
				$cf = $config[ 'wpml-config' ][ 'custom-types' ][ 'custom-type' ];
			}

			foreach ( $cf as $c ) {

				$translate = intval( $c[ 'attr' ][ 'translate' ] );
				$iclTranslationManagement->settings[ 'custom_types_readonly_config' ][ $c[ 'value' ] ]	= $translate;
				$custom_posts_sync_option[ 'custom_posts_sync_option' ][ $c[ 'value' ] ]				= $translate;

				if ( $translate == 1) {
					$sitepress->verify_post_translations( $c[ 'value' ] );
					$iclTranslationManagement->save_settings();
				}

			}

			$sitepress->save_settings( $custom_posts_sync_option );

			// add_filter( 'get_translatable_documents', array( $iclTranslationManagement, '_override_get_translatable_documents' ) );
		}


		// custom post types - check what's been removed
		if ( !empty( $iclTranslationManagement->settings[ 'custom_types_readonly_config' ] ) ) {
			$config_values = array();
			foreach ( $cf as $config_value ) {
				$config_values[ $config_value[ 'value' ] ] = $config_value[ 'attr' ][ 'translate' ];
			}
			$do_save = false;
			foreach ( $iclTranslationManagement->settings[ 'custom_types_readonly_config' ] as $tconf => $tconf_val ) {
				if ( !isset( $config_values[ $tconf ] ) ) {
					unset( $iclTranslationManagement->settings[ 'custom_types_readonly_config' ][ $tconf ] );
					$do_save = true;
				}
			}
			if ( $do_save ) {
				$iclTranslationManagement->save_settings();
			}
		}



	}

	/**
	 * @param $config
	 *
	 * @return array
	 */
	protected static function parse_taxonomies( $config )
	{
		global $sitepress, $iclTranslationManagement;
		$cf = array();
		$taxonomies_sync_option = array();

		if ( !empty( $config[ 'wpml-config' ][ 'taxonomies' ] ) ) {
			if ( !is_numeric( key( current( $config[ 'wpml-config' ][ 'taxonomies' ] ) ) ) ) {
				$cf[ 0 ] = $config[ 'wpml-config' ][ 'taxonomies' ][ 'taxonomy' ];
			} else {
				$cf = $config[ 'wpml-config' ][ 'taxonomies' ][ 'taxonomy' ];
			}

			foreach ( $cf as $c ) {

				$translate  																			= intval( $c[ 'attr' ][ 'translate' ] );
				$iclTranslationManagement->settings[ 'taxonomies_readonly_config' ][ $c[ 'value' ] ]	= $translate;
				$taxonomies_sync_option[ 'taxonomies_sync_option' ][ $c[ 'value' ] ]					= $translate;

				// this has just changed. save.
				if ( $translate == 1 ) {
					$sitepress->verify_taxonomy_translations( $c[ 'value' ] );
					$iclTranslationManagement->save_settings();
				}
			}

			$sitepress->save_settings( $taxonomies_sync_option );

			add_filter( 'get_translatable_taxonomies', array( $iclTranslationManagement, '_override_get_translatable_taxonomies' ) );
		}

		// taxonomies - check what's been removed
		if ( !empty( $iclTranslationManagement->settings[ 'taxonomies_readonly_config' ] ) ) {
			$config_values = array();
			foreach ( $cf as $config_value ) {
				$config_values[ $config_value[ 'value' ] ] = $config_value[ 'attr' ][ 'translate' ];
			}
			$do_save = false;
			foreach ( $iclTranslationManagement->settings[ 'taxonomies_readonly_config' ] as $tconf => $tconf_val ) {
				if ( !isset( $config_values[ $tconf ] ) ) {
					unset( $iclTranslationManagement->settings[ 'taxonomies_readonly_config' ][ $tconf ] );
					$do_save = true;
				}
			}
			if ( $do_save ) {
				$iclTranslationManagement->save_settings();
			}
		}
	}

	/**
	 * @param $config
	 *
	 * @return array
	 */
	protected static function parse_admin_texts( $config )
	{
		global $iclTranslationManagement;

		if ( function_exists( 'icl_register_string' ) ) {
			$admin_texts = array();
			if ( !empty( $config[ 'wpml-config' ][ 'admin-texts' ] ) ) {

				if ( !is_numeric( key( @current( $config[ 'wpml-config' ][ 'admin-texts' ] ) ) ) ) {
					$admin_texts[ 0 ] = $config[ 'wpml-config' ][ 'admin-texts' ][ 'key' ];
				} else {
					$admin_texts = $config[ 'wpml-config' ][ 'admin-texts' ][ 'key' ];
				}

				$type               = 'plugin';
				$admin_text_context = '';

				foreach ( $admin_texts as $a ) {

					if ( isset( $a[ 'type' ] ) ) {
						$type = $a[ 'type' ];
					}
					if ( isset( $a[ 'context' ] ) ) {
						$admin_text_context = $a[ 'context' ];
					}
					if ( !isset( $type ) ) {
						$type = 'plugin';
					}
					if ( !isset( $admin_text_context ) ) {
						$admin_text_context = '';
					}

					$keys = array();
					if ( !isset( $a[ 'key' ]) ) {
						$arr[ $a[ 'attr' ][ 'name' ] ] = 1;
						$arr_context[ $a[ 'attr' ][ 'name' ] ] = $admin_text_context;
						$arr_type[ $a[ 'attr' ][ 'name' ] ] = $type;
						continue;
					} elseif ( !is_numeric( key( $a[ 'key' ] ) ) ) {
						$keys[ 0 ] = $a[ 'key' ];
					} else {
						$keys = $a[ 'key' ];
					}

					foreach ( $keys as $key ) {
						if ( isset( $key[ 'key' ] ) ) {
							$arr[ $a[ 'attr' ][ 'name' ] ][ $key[ 'attr' ][ 'name' ] ] = self::read_admin_texts_recursive( $key[ 'key' ], $admin_text_context, $type, $arr_context, $arr_type );
						} else {
							$arr[ $a[ 'attr' ][ 'name' ] ][ $key[ 'attr' ][ 'name' ] ] = 1;
						}
						$arr_context[ $a[ 'attr' ][ 'name' ] ] = $admin_text_context;
						$arr_type[ $a[ 'attr' ][ 'name' ] ] = $type;
					}
				}

				if ( isset( $arr ) ) {
					$iclTranslationManagement->admin_texts_to_translate = array_merge( $iclTranslationManagement->admin_texts_to_translate, $arr );
				}

				$_icl_admin_option_names = get_option( '_icl_admin_option_names' );

				$arr_options = array();
				if ( isset( $arr ) && is_array( $arr ) ) {
					foreach ( $arr as $key => $v ) {
						remove_filter( 'option_' . $key, 'icl_st_translate_admin_string' ); // dont try to translate this one below
						$value = get_option( $key );
						add_filter( 'option_' . $key, 'icl_st_translate_admin_string' ); // put the filter back on

						$value = maybe_unserialize( $value );
						$admin_text_context  = isset($arr_context[$key]) ? $arr_context[$key] : '';
						$type = isset($arr_type[$key]) ? $arr_type[$key] : '';

						if ( false === $value ) {

							// wildcard? register all matching options in wp_options
							global $wpdb;
							$src     = str_replace( '*', '%', wpml_like_escape( $key ) );
							$matches = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '{$src}'" );
							foreach ( $matches as $match ) {
								icl_register_string( 'admin_texts_' . $type . '_' . $admin_text_context, $match->option_name, $match->option_value );

								$_icl_admin_option_names[ $type ][ $admin_text_context ][ ] = $match->option_name;
							}
							unset( $arr[ $key ] );

						}
						if ( is_scalar( $value ) ) {
							icl_register_string( 'admin_texts_' . $type . '_' . $admin_text_context, $key, $value );
						} else {
							if ( is_object( $value ) ) {
								$value = (array)$value;
							}
							if ( !empty( $value ) ) {
								$iclTranslationManagement->_register_string_recursive( $key, $value, $arr[ $key ], '', $type . '_' . $admin_text_context );
							}
						}
						$arr_options[$type][$admin_text_context][$key] = $v;
					}

					if(is_array($_icl_admin_option_names)) {
						$_icl_admin_option_names = @array_merge( (array)$_icl_admin_option_names, $arr_options );
					} else {
						$_icl_admin_option_names = $arr_options ;
					}
				}

				//$_icl_admin_option_names[ $type ][ $admin_text_context ] = __array_unique_recursive( $_icl_admin_option_names[ $type ][ $admin_text_context ] );

				update_option( '_icl_admin_option_names', $_icl_admin_option_names );

			}
		}

	}

	private static function read_admin_texts_recursive( $keys, $admin_text_context, $type, &$arr_context, &$arr_type )
	{
		if ( !is_numeric( key( $keys ) ) ) {
			$_keys = array( $keys );
			$keys  = $_keys;
			unset( $_keys );
		}
		$arr = false;
		if ( $keys ) {
			foreach ( $keys as $key ) {
				if ( isset( $key[ 'key' ] ) ) {
					$arr[ $key[ 'attr' ][ 'name' ] ] = self::read_admin_texts_recursive( $key[ 'key' ], $admin_text_context, $type, $arr_context, $arr_type );
				} else {
					$arr[ $key[ 'attr' ][ 'name' ] ] = 1;
					$arr_context[ $key[ 'attr' ][ 'name' ] ] = $admin_text_context;
					$arr_type[ $key[ 'attr' ][ 'name' ] ] = $type;
				}
			}
		}

		return $arr;
	}
}
