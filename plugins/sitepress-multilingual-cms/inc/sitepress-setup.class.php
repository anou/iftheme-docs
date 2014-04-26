<?php
class SitePress_Setup {

	static function setup_complete() {
		global $sitepress;

		return $sitepress->get_setting('setup_complete');
	}
	
	static function languages_complete() {
		return self::active_languages_complete() && self::languages_table_is_complete();
	}

	private static function active_languages_complete() {
		static $result = null;

		if ( $result === null ) {
			global $sitepress;

			$result = isset( $sitepress ) && 1 < count( $sitepress->get_active_languages() );

		}

		return $result;
	}

	private static function get_languages_codes() {
		static $languages_codes = null;
		if ( $languages_codes == null ) {
			$languages_codes = icl_get_languages_codes();
		}

		return $languages_codes;
	}

	private static function get_languages_names() {
		static $languages_names = null;
		if ( $languages_names == null ) {
			$languages_names = icl_get_languages_names();
		}

		return $languages_names;
	}

	private static function get_languages_names_count() {
		return count( self::get_languages_names() );
	}

	static function get_charset_collate() {
		static $charset_collate = null;

		if ( $charset_collate == null ) {
			$charset_collate = '';
			global $wpdb;
			if ( method_exists( $wpdb, 'has_cap' ) && $wpdb->has_cap( 'collation' ) ) {
				if ( !empty( $wpdb->charset ) ) {
					$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
				}
				if ( !empty( $wpdb->collate ) ) {
					$charset_collate .= " COLLATE $wpdb->collate";
				}
			}
		}

		return $charset_collate;
	}

	private static function create_languages() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'icl_languages';
		$sql_table_check = $wpdb->prepare( "SHOW TABLES LIKE %s", array( $table_name ) );
		if ( 0 !== strcasecmp( $wpdb->get_var( $sql_table_check ), $table_name ) ) {
			$sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
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
				  ) " . self::get_charset_collate();

			if ($wpdb->query($sql) === false) {
				return false;
			} 
		}

		return true;
	}

	static function languages_table_is_complete() {
		global $wpdb;
		$table_name    = $wpdb->prefix . 'icl_languages';
		$sql           = "SELECT count(id) FROM {$table_name}";
		$records_count = $wpdb->get_var( $sql );

		$languages_names_count = self::get_languages_names_count();

		if( $records_count < $languages_names_count) return false;

		$languages_codes = self::get_languages_codes();

		$table_name    = $wpdb->prefix . 'icl_languages_translations';
		foreach ( self::get_languages_names() as $lang => $val ) {
			if ( strpos( $lang, 'Norwegian Bokm' ) === 0 ) {
				$lang                     = 'Norwegian Bokmål';
				$languages_codes[ $lang ] = 'nb';
			}
			foreach ( $val[ 'tr' ] as $k => $display ) {
				if ( strpos( $k, 'Norwegian Bokm' ) === 0 ) {
					$k = 'Norwegian Bokmål';
				}
				$sql = $wpdb->prepare( "SELECT id FROM {$table_name} WHERE language_code=%s AND display_language_code=%s", array( $languages_codes[ $lang ], $languages_codes[ $k ] ) );
				if ( !( $wpdb->get_var( $sql ) ) ) {
					return false;
				}
			}
		}
		return true;
	}

	static function fill_languages() {
		global $wpdb, $sitepress;

		$languages_codes = icl_get_languages_codes();
        $lang_locales = icl_get_languages_locales();

		$table_name = $wpdb->prefix . 'icl_languages';
		if ( !self::create_languages() ) {
			return false;
		}

		if ( !self::languages_table_is_complete() ) {
			//First truncate the table
			$active_languages = $sitepress->get_active_languages();

			$wpdb->hide_errors();

			$sql = "TRUNCATE " . $table_name;

			$truncate_result = $wpdb->query( $sql );

			$wpdb->show_errors();

			if ( $truncate_result ) {
				foreach ( self::get_languages_names()  as $key => $val ) {
					$language_code     = $languages_codes[ $key ];
					if ( strpos( $key, 'Norwegian Bokm' ) === 0 ) {
						$key                     = 'Norwegian Bokmål';
						$language_code = 'nb';
					} // exception for norwegian
					$default_locale = isset( $lang_locales[ $language_code ] ) ? $lang_locales[ $language_code ] : '';

					$args = array(
						'english_name'   => $key,
						'code'           => $language_code,
						'major'          => $val[ 'major' ],
						'active'         => isset($active_languages[ $language_code ]) ? 1 : 0,
						'default_locale' => $default_locale,
						'tag'            => str_replace( '_', '-', $default_locale )
					);
					if ( $wpdb->insert( $table_name, $args )  === false) {
						return false;
					}                  
				}
			}
		}

		return true;
	}

	private static function create_languages_translations() {
		global $wpdb;
		// languages translations table
		$table_name      = $wpdb->prefix . 'icl_languages_translations';
		$sql_table_check = $wpdb->prepare( "SHOW TABLES LIKE %s", array( $table_name ) );
		if ( 0 !== strcasecmp( $wpdb->get_var( $sql_table_check ), $table_name ) ) {
			$sql = "
             CREATE TABLE IF NOT EXISTS `{$table_name}` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `language_code`  VARCHAR( 7 ) NOT NULL ,
                `display_language_code` VARCHAR( 7 ) NOT NULL ,
                `name` VARCHAR( 255 ) CHARACTER SET utf8 NOT NULL,
                UNIQUE(`language_code`, `display_language_code`)
            ) " . self::get_charset_collate();
			if ($wpdb->query($sql) === false) {
				return false;
			}  
		}

		return true;
	}

	static function fill_languages_translations() {
		global $wpdb;

		$languages_codes = icl_get_languages_codes();

		$table_name = $wpdb->prefix . 'icl_languages_translations';

		if ( !self::create_languages_translations() ) {
			return false;
		}

		if ( !self::languages_table_is_complete() ) {

			//First truncate the table
			$wpdb->hide_errors();

			$sql =  "TRUNCATE " . $table_name;

			$truncate_result = $wpdb->query( $sql );

			$wpdb->show_errors();

			if ( $truncate_result ) {

				$languages_names = self::get_languages_names();
				foreach ( $languages_names as $lang => $val ) {
					if ( strpos( $lang, 'Norwegian Bokm' ) === 0 ) {
						$lang                     = 'Norwegian Bokmål';
						$languages_codes[ $lang ] = 'nb';
					}
					foreach ( $val[ 'tr' ] as $k => $display ) {
						if ( strpos( $k, 'Norwegian Bokm' ) === 0 ) {
							$k = 'Norwegian Bokmål';
						}
						if ( !trim( $display ) ) {
							$display = $lang;
						}
						$sql = $wpdb->prepare( "SELECT id FROM {$table_name} WHERE language_code=%s AND display_language_code=%s", array( $languages_codes[ $lang ], $languages_codes[ $k ] ) );
						if ( !( $wpdb->get_var( $sql ) ) ) {
							$args = array(
								'language_code'         => $languages_codes[ $lang ],
								'display_language_code' => $languages_codes[ $k ],
								'name'                  => $display
							);
							if ($wpdb->insert( $wpdb->prefix . 'icl_languages_translations', $args ) === false) {
								return false;
							}  
						}
					}
				}
			}
		}

		return true;

	}
}
