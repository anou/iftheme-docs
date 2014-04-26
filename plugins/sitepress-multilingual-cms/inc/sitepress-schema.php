<?php
function icl_sitepress_activate() {
	global $wpdb;

	$langs_names  = icl_get_languages_names();
	$lang_codes   = icl_get_languages_codes();
	$lang_locales = icl_get_languages_locales();

	$charset_collate = '';
	if ( method_exists( $wpdb, 'has_cap' ) && $wpdb->has_cap( 'collation' ) ) {
		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}
	}

	try {
		// languages table
		$table_name = $wpdb->prefix . 'icl_languages';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
             CREATE TABLE IF NOT EXISTS `{$table_name}` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `code` VARCHAR( 7 ) NOT NULL ,
                `english_name` VARCHAR( 128 ) NOT NULL ,            
                `major` TINYINT NOT NULL DEFAULT '0', 
                `active` TINYINT NOT NULL ,
                `default_locale` VARCHAR( 8 ),
                `tag` VARCHAR( 8 ),
                `encode_url` TINYINT( 1 ) NOT NULL DEFAULT 0,
                UNIQUE KEY `code` (`code`),
                UNIQUE KEY `english_name` (`english_name`)
            ) {$charset_collate}";

			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}

			foreach ( $langs_names as $key => $val ) {
				if ( strpos( $key, 'Norwegian Bokm' ) === 0 ) {
					$key                = 'Norwegian Bokmål';
					$lang_codes[ $key ] = 'nb';
				} // exception for norwegian
				$default_locale = isset( $lang_locales[ $lang_codes[ $key ] ] ) ? $lang_locales[ $lang_codes[ $key ] ] : '';
				$wpdb->insert( $wpdb->prefix . 'icl_languages', array(
					'english_name'   => $key,
					'code'           => $lang_codes[ $key ],
					'major'          => $val[ 'major' ],
					'active'         => 0,
					'default_locale' => $default_locale,
					'tag'            => str_replace( '_', '-', $default_locale )
				) );
			}
		}

		// languages translations table
		$add_languages_translations = false;
		$table_name                 = $wpdb->prefix . 'icl_languages_translations';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
             CREATE TABLE IF NOT EXISTS `{$table_name}` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `language_code`  VARCHAR( 7 ) NOT NULL ,
                `display_language_code` VARCHAR( 7 ) NOT NULL ,            
                `name` VARCHAR( 255 ) CHARACTER SET utf8 NOT NULL,
                UNIQUE(`language_code`, `display_language_code`)            
            ) {$charset_collate}";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
			$add_languages_translations = true;
		}

		if ( $add_languages_translations ) {
			foreach ( $langs_names as $lang => $val ) {
				if ( strpos( $lang, 'Norwegian Bokm' ) === 0 ) {
					$lang                = 'Norwegian Bokmål';
					$lang_codes[ $lang ] = 'nb';
				}
				foreach ( $val[ 'tr' ] as $k => $display ) {
					if ( strpos( $k, 'Norwegian Bokm' ) === 0 ) {
						$k = 'Norwegian Bokmål';
					}
					if ( ! trim( $display ) ) {
						$display = $lang;
					}
					if ( ! ( $wpdb->get_var( "SELECT id FROM {$table_name} WHERE language_code='{$lang_codes[$lang]}' AND display_language_code='{$lang_codes[$k]}'" ) ) ) {
						$wpdb->insert( $wpdb->prefix . 'icl_languages_translations', array( 'language_code' => $lang_codes[ $lang ], 'display_language_code' => $lang_codes[ $k ], 'name' => $display ) );
					}
				}
			}
		}

		// translations
		$table_name = $wpdb->prefix . 'icl_translations';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
             CREATE TABLE IF NOT EXISTS `{$table_name}` (
                `translation_id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `element_type` VARCHAR( 36 ) NOT NULL DEFAULT 'post_post',
                `element_id` BIGINT NULL DEFAULT NULL ,
                `trid` BIGINT NOT NULL ,
                `language_code` VARCHAR( 7 ) NOT NULL,
                `source_language_code` VARCHAR( 7 ),
                UNIQUE KEY `el_type_id` (`element_type`,`element_id`),
                UNIQUE KEY `trid_lang` (`trid`,`language_code`),
                KEY `trid` (`trid`)
                
            ) {$charset_collate}";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		// translation_status table
		$table_name = $wpdb->prefix . 'icl_translation_status';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
                 CREATE TABLE IF NOT EXISTS `{$table_name}` (
                 `rid` bigint(20) NOT NULL AUTO_INCREMENT,
                 `translation_id` bigint(20) NOT NULL,
                 `status` tinyint(4) NOT NULL,
                 `translator_id` bigint(20) NOT NULL,
                 `needs_update` tinyint(4) NOT NULL,
                 `md5` varchar(32) NOT NULL,
                 `translation_service` varchar(16) NOT NULL,
                 `translation_package` text NOT NULL,
                 `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                 `links_fixed` tinyint(4) NOT NULL DEFAULT 0,
                 `_prevstate` longtext,
                 PRIMARY KEY (`rid`),
                 UNIQUE KEY `translation_id` (`translation_id`)
                ) {$charset_collate}    
            ";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		// translation jobs
		$table_name = $wpdb->prefix . 'icl_translate_job';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
                 CREATE TABLE IF NOT EXISTS `{$table_name}` (
                `job_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `rid` BIGINT UNSIGNED NOT NULL ,
                `translator_id` INT UNSIGNED NOT NULL ,
                `translated` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `manager_id` INT UNSIGNED NOT NULL ,
                `revision` INT UNSIGNED NULL,
                INDEX ( `rid` , `translator_id` )
                ) {$charset_collate}    
            ";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		// translate table
		$table_name = $wpdb->prefix . 'icl_translate';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
                 CREATE TABLE IF NOT EXISTS `{$table_name}` (
                `tid` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `job_id` BIGINT UNSIGNED NOT NULL ,
                `content_id` BIGINT UNSIGNED NOT NULL ,
                `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                `field_type` VARCHAR( 128 ) NOT NULL ,
                `field_format` VARCHAR( 16 ) NOT NULL ,
                `field_translate` TINYINT NOT NULL ,
                `field_data` longtext NOT NULL ,
                `field_data_translated` longtext NOT NULL ,
                `field_finished` TINYINT NOT NULL DEFAULT 0,
                INDEX ( `job_id` )
                ) {$charset_collate}
            ";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		// languages locale file names
		$table_name = $wpdb->prefix . 'icl_locale_map';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
                 CREATE TABLE IF NOT EXISTS `{$table_name}` (
                    `code` VARCHAR( 7 ) NOT NULL ,
                    `locale` VARCHAR( 8 ) NOT NULL ,
                    UNIQUE (`code` ,`locale`)
                ) {$charset_collate}";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		// flags table
		$table_name = $wpdb->prefix . 'icl_flags';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
                 CREATE TABLE IF NOT EXISTS `{$table_name}` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `lang_code` VARCHAR( 10 ) NOT NULL ,
                `flag` VARCHAR( 32 ) NOT NULL ,
                `from_template` TINYINT NOT NULL DEFAULT '0',
                UNIQUE (`lang_code`)
                ) {$charset_collate}";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
			$codes = $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages" );
			foreach ( $codes as $code ) {
				if ( ! $code || $wpdb->get_var( "SELECT lang_code FROM {$wpdb->prefix}icl_flags WHERE lang_code='{$code}'" ) ) {
					continue;
				}
				if ( ! file_exists( ICL_PLUGIN_PATH . '/res/flags/' . $code . '.png' ) ) {
					$file = 'nil.png';
				} else {
					$file = $code . '.png';
				}
				$wpdb->insert( $wpdb->prefix . 'icl_flags', array( 'lang_code' => $code, 'flag' => $file, 'from_template' => 0 ) );
			}
		}

		/* general string translation */
		$table_name = $wpdb->prefix . 'icl_strings';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
                 CREATE TABLE IF NOT EXISTS `{$table_name}` (
                  `id` bigint(20) unsigned NOT NULL auto_increment,
                  `language` varchar(7) NOT NULL,
                  `context` varchar(160) NOT NULL,
                  `name` varchar(160) NOT NULL,
                  `value` text NOT NULL,
                  `status` TINYINT NOT NULL,
                  PRIMARY KEY  (`id`),
                  UNIQUE KEY `context_name` (`context`,`name`),
                  KEY `language_context` (`language`, `context`)
                ) {$charset_collate}";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		$table_name = $wpdb->prefix . 'icl_string_translations';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
                 CREATE TABLE IF NOT EXISTS `{$table_name}` (
                  `id` bigint(20) unsigned NOT NULL auto_increment,
                  `string_id` bigint(20) unsigned NOT NULL,
                  `language` varchar(10) NOT NULL,
                  `status` tinyint(4) NOT NULL,
                  `value` text NULL DEFAULT NULL,              
                  `translator_id` bigint(20) unsigned DEFAULT NULL, 
                  `translation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY  (`id`),
                  UNIQUE KEY `string_language` (`string_id`,`language`)
                ) {$charset_collate}";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		$table_name = $wpdb->prefix . 'icl_string_status';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
                  CREATE TABLE IF NOT EXISTS `{$table_name}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `rid` BIGINT NOT NULL ,
                `string_translation_id` BIGINT NOT NULL ,
                `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                `md5` VARCHAR( 32 ) NOT NULL,
                INDEX ( `string_translation_id` )
                ) {$charset_collate}";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		$table_name = $wpdb->prefix . 'icl_string_positions';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
                  CREATE TABLE IF NOT EXISTS `{$table_name}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `string_id` BIGINT NOT NULL ,
                `kind` TINYINT,
                `position_in_page` VARCHAR( 255 ) NOT NULL,
                INDEX ( `string_id` )
                ) {$charset_collate}";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		// message status table
		$table_name = $wpdb->prefix . 'icl_message_status';
		if ( 0 !== strcasecmp( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ), $table_name ) ) {
			$sql = "
                  CREATE TABLE IF NOT EXISTS `{$table_name}` (
                      `id` bigint(20) unsigned NOT NULL auto_increment,
                      `rid` bigint(20) unsigned NOT NULL,
                      `object_id` bigint(20) unsigned NOT NULL,
                      `from_language` varchar(10) NOT NULL,
                      `to_language` varchar(10) NOT NULL,
                      `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
                      `md5` varchar(32) NOT NULL,
                      `object_type` varchar(64) NOT NULL,
                      `status` smallint(6) NOT NULL,
                      PRIMARY KEY  (`id`),
                      UNIQUE KEY `rid` (`rid`),
                      KEY `object_id` (`object_id`)
                ) {$charset_collate}";
			if ( $wpdb->query( $sql ) === false ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		/* string translation - start */
		$icl_translation_sql = "
             CREATE TABLE IF NOT EXISTS {$wpdb->prefix}icl_core_status (
            `id` BIGINT NOT NULL auto_increment,
            `rid` BIGINT NOT NULL,
            `module` VARCHAR( 16 ) NOT NULL ,
            `origin` VARCHAR( 64 ) NOT NULL ,
            `target` VARCHAR( 64 ) NOT NULL ,
            `status` SMALLINT NOT NULL,
            PRIMARY KEY ( `id` ) ,
            INDEX ( `rid` )
            ) {$charset_collate}
      ";
		if ( $wpdb->query( $icl_translation_sql ) === false ) {
			throw new Exception( $wpdb->last_error );
		}

		$icl_translation_sql = "
            CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_content_status` (
            `rid` BIGINT NOT NULL ,
            `nid` BIGINT NOT NULL ,
            `timestamp` DATETIME NOT NULL ,
            `md5` VARCHAR( 32 ) NOT NULL ,
            PRIMARY KEY ( `rid` ) ,
            INDEX ( `nid` )
            ) {$charset_collate} 
      ";
		if ( $wpdb->query( $icl_translation_sql ) === false ) {
			throw new Exception( $wpdb->last_error );
		}


		$icl_translation_sql = "
            CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_node` (
            `nid` BIGINT NOT NULL ,
            `md5` VARCHAR( 32 ) NOT NULL ,
            `links_fixed` TINYINT NOT NULL DEFAULT 0,
            PRIMARY KEY ( `nid` )
            ) {$charset_collate}  
      ";
		if ( $wpdb->query( $icl_translation_sql ) === false ) {
			throw new Exception( $wpdb->last_error );
		}

		$icl_translation_sql = "
            CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_reminders` (
            `id` BIGINT NOT NULL ,
            `message` TEXT NOT NULL ,
            `url`  TEXT NOT NULL ,
            `can_delete` TINYINT NOT NULL ,
            `show` TINYINT NOT NULL ,
            PRIMARY KEY ( `id` )
            ) {$charset_collate}  
      ";
		if ( $wpdb->query( $icl_translation_sql ) === false ) {
			throw new Exception( $wpdb->last_error );
		}

	} catch ( Exception $e ) {
		trigger_error( $e->getMessage(), E_USER_ERROR );
		exit;
	}

	if ( get_option( 'icl_sitepress_version' ) ) {
		icl_plugin_upgrade();
	}

	// don't set the new version if a multi-step upgrade is in progress
	if ( ! defined( 'ICL_MULTI_STEP_UPGRADE' ) ) {
		delete_option( 'icl_sitepress_version' );
		add_option( 'icl_sitepress_version', ICL_SITEPRESS_VERSION, '', true );
	}


	$iclsettings = get_option( 'icl_sitepress_settings' );
	if ( $iclsettings === false ) {
		$short_v  = implode( '.', array_slice( explode( '.', ICL_SITEPRESS_VERSION ), 0, 3 ) );
		$settings = array(
			'hide_upgrade_notice' => $short_v
		);
		add_option( 'icl_sitepress_settings', $settings, '', true );
	} else {
		// reset ajx_health_flag
		$iclsettings[ 'ajx_health_checked' ] = 0;
		$iclsettings[ 'just_reactivated' ]   = 1;
		update_option( 'icl_sitepress_settings', $iclsettings );
	}

	//Set new caps for all administrator role
	icl_enable_capabilities();

}

function icl_sitepress_deactivate() {
	icl_disable_capabilities();
}

function icl_enable_capabilities() {
	global $wp_roles;

	if ( ! isset( $wp_roles ) || ! is_object( $wp_roles ) ) {
		$wp_roles = new WP_Roles();
	}

	$iclsettings      = get_option( 'icl_sitepress_settings' );
	$icl_capabilities = icl_sitepress_get_capabilities();

	//Set WPML capabilities to all roles with cap:"".
	$roles = $wp_roles->get_names();
	foreach ( $roles as $current_role => $role_name ) {
		if ( isset( $wp_roles->roles[ $current_role ][ 'capabilities' ][ 'manage_options' ] ) ) {
			$role = get_role( $current_role );
			if ( isset( $role ) && is_object( $role ) ) {
				for ( $i = 0, $caps_limit = count( $icl_capabilities ); $i < $caps_limit; $i ++ ) {
					if ( ! isset( $wp_roles->roles[ $current_role ][ 'capabilities' ][ $icl_capabilities[ $i ] ] ) ) {
						$role->add_cap( $icl_capabilities[ $i ] );
					}
				}
			}

		}
	}


	//Set new caps for all Super Admins
	$super_admins = get_super_admins();
	foreach ( $super_admins as $admin ) {
		$user = new WP_User( $admin );
		for ( $i = 0, $caps_limit = count( $icl_capabilities ); $i < $caps_limit; $i ++ ) {
			$user->add_cap( $icl_capabilities[ $i ] );
		}
	}

	$iclsettings[ 'icl_capabilities_verified' ] = true;
	update_option( 'icl_sitepress_settings', $iclsettings );
}

function icl_disable_capabilities() {
	//by Gen. We not remove WPML caps for super admin, because can cause problem in other sites.
	// Trunk version with this code: 19502
	//	$iclsettings['icl_capabilities_verified'] = false;
	//	update_option('icl_sitepress_settings',$iclsettings);
}


// Changed to use lowercased wpdb prefix. Some users have table name in uppercase.
// http://bugs.mysql.com/bug.php?id=39894
if ( isset( $_GET[ 'activate' ] ) ) {
	global $wpdb;
	if ( isset( $wpdb ) ) {
		$table_name = $wpdb->prefix . 'icl_languages';
		if ( strtolower( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) ) != strtolower( $table_name ) ) {
			add_action( 'admin_notices', 'icl_cant_create_table' );
			function icl_cant_create_table() {
				echo '<div class="error"><ul><li><strong>';
				echo __( 'WPML cannot create the database tables! Make sure that your mysql user has the CREATE privilege', 'sitepress' );
				echo '</strong></li></ul></div>';
				$active_plugins    = get_option( 'active_plugins' );
				$icl_sitepress_idx = array_search( ICL_PLUGIN_FOLDER . '/sitepress.php', $active_plugins );
				if ( false !== $icl_sitepress_idx ) {
					unset( $active_plugins[ $icl_sitepress_idx ] );
					update_option( 'active_plugins', $active_plugins );
					unset( $_GET[ 'activate' ] );
					$recently_activated = get_option( 'recently_activated' );
					if ( ! isset( $recently_activated[ ICL_PLUGIN_FOLDER . '/sitepress.php' ] ) ) {
						$recently_activated[ ICL_PLUGIN_FOLDER . '/sitepress.php' ] = time();
						update_option( 'recently_activated', $recently_activated );
					}
				}
			}
		}
	}
}
