<?php

class WPML_ST_Strings {

	/**
	 * @var SitePress
	 */
	private $sitepress;
	/**
	 * @var WP_Query
	 */
	private $wp_query;
	/**
	 * @var WPDB
	 */
	private $wpdb;

	public function __construct( &$sitepress, &$wpdb, &$wp_query ) {
		$this->wpdb               = &$wpdb;
		$this->sitepress          = &$sitepress;
		$this->wp_query           = &$wp_query;
		$this->sitepress_settings = $this->sitepress->get_settings();
	}

	public function get_string_translations() {
		$string_translations = array();

		$current_user                       = $this->sitepress->get_current_user();
		$active_languages                   = $this->sitepress->get_active_languages();
//		error_log(serialize($active_languages), 3, WP_CONTENT_DIR . '/debug.log');
		$current_user_can_translate_strings = $this->sitepress->get_wp_helper()->current_user_can_translate_strings();
		$user_lang_pairs                    = $this->sitepress->get_wp_helper()->get_user_language_pairs( $current_user );

		$extra_cond = "";

		if ( $current_user_can_translate_strings && isset( $_GET[ 'status' ] ) && preg_match( "#" . ICL_TM_WAITING_FOR_TRANSLATOR . "-(.+)#", $_GET[ 'status' ], $matches ) ) {
			$status_filter       = ICL_TM_WAITING_FOR_TRANSLATOR;
			$status_filter_lang  = $matches[ 1 ];
			$language_code_alias = str_replace( '-', '', $status_filter_lang );
			$extra_cond .= " AND str_{$language_code_alias}.language = '{$status_filter_lang}' ";
		} else {
			$status_filter = isset( $_GET[ 'status' ] ) ? intval( $_GET[ 'status' ] ) : false;
		}

		$search_filter = isset( $_GET[ 'search' ] ) ? $_GET[ 'search' ] : false;
		$exact_match   = isset( $_GET[ 'em' ] ) ? $_GET[ 'em' ] == 1 : false;

		if ( $status_filter !== false ) {
			if ( $status_filter == ICL_TM_COMPLETE ) {
				$extra_cond .= " AND s.status = " . ICL_TM_COMPLETE;
			} elseif ( $status_filter == ICL_TM_WAITING_FOR_TRANSLATOR ) {
				; // do nothing
			} else {
				$extra_cond .= " AND status IN (" . ICL_STRING_TRANSLATION_PARTIAL . "," . ICL_TM_NEEDS_UPDATE . "," . ICL_TM_NOT_TRANSLATED . "," . ICL_TM_WAITING_FOR_TRANSLATOR . ")";
			}
		}

		if ( $search_filter != false ) {
			if ( $exact_match ) {
				$extra_cond .= " AND s.value = '" . esc_sql( $search_filter ) . "' ";
			} else {
				$extra_cond .= " AND s.value LIKE '%" . esc_sql( $search_filter ) . "%' ";
			}
		}

		$context_filter = isset( $_GET[ 'context' ] ) ? $_GET[ 'context' ] : false;
		if ( $context_filter !== false ) {
			$extra_cond .= " AND s.context = '" . esc_sql( $context_filter ) . "'";
		}

		if ( isset( $_GET[ 'show_results' ] ) && $_GET[ 'show_results' ] == 'all' ) {
			$limit  = 9999;
			$offset = 0;
		} else {
			$limit = $this->sitepress_settings[ 'st' ][ 'strings_per_page' ];
			if ( ! isset( $_GET[ 'paged' ] ) ) {
				$_GET[ 'paged' ] = 1;
			}
			$offset = ( $_GET[ 'paged' ] - 1 ) * $limit;
		}

		/* TRANSLATOR - START */
		if ( $current_user_can_translate_strings ) {
			if ( ! empty( $status_filter_lang ) ) {

				$_joins = $_sels = $_where = array();
				foreach ( $active_languages as $l ) {
					if ( $l[ 'code' ] == $this->sitepress_settings[ 'st' ][ 'strings_language' ] ) {
						continue;
					}
					$language_code_alias = esc_sql( str_replace( '-', '', $l[ 'code' ] ) );
					$_sels[]             = "str_{$language_code_alias}.id AS id_{$language_code_alias},
	                             str_{$language_code_alias}.status AS status_{$language_code_alias},
	                             str_{$language_code_alias}.value AS value_{$language_code_alias},
	                             str_{$language_code_alias}.translator_id AS translator_{$language_code_alias},
	                             str_{$language_code_alias}.translation_date AS date_{$language_code_alias}
	                             ";
					$_joins[]            = $this->wpdb->prepare( " LEFT JOIN {$this->wpdb->prefix}icl_string_translations str_{$language_code_alias}
	                                                ON str_{$language_code_alias}.string_id = s.id AND str_{$language_code_alias}.language = %s ", $l[ 'code' ] );
				}

				$sql          = "
	                SELECT SQL_CALC_FOUND_ROWS s.id AS string_id, s.language AS string_language, s.context, s.gettext_context, s.name, s.value, s.status,
	                    " . join( ", ", $_sels ) . "
	                FROM  {$this->wpdb->prefix}icl_strings s 
	                " . join( "\n", $_joins ) . "
	                WHERE 
	                    str_{$status_filter_lang}.status = %d AND
	                    (str_{$status_filter_lang}.translator_id IS NULL OR str_{$status_filter_lang}.translator_id = %d)
	                    {$extra_cond}
	                ORDER BY string_id DESC
	                LIMIT {$offset},{$limit}
	            ";
				$sql_prepared = $this->wpdb->prepare( $sql, array( ICL_TM_WAITING_FOR_TRANSLATOR, $current_user->ID ) );
				$res          = $this->wpdb->get_results( $sql_prepared, ARRAY_A );
			} else {
				$_joins = $_sels = $_where = array();

				foreach ( $active_languages as $l ) {

					if ( $l[ 'code' ] == $this->sitepress_settings[ 'st' ][ 'strings_language' ]
							 || empty( $user_lang_pairs[ $this->sitepress_settings[ 'st' ][ 'strings_language' ] ][ $l[ 'code' ] ] )
					) {
						continue;
					}
					$language_code_alias = esc_sql( str_replace( '-', '', $l[ 'code' ] ) );

					$_sels[]  = "str_{$language_code_alias}.id AS id_{$language_code_alias},
	                             str_{$language_code_alias}.status AS status_{$language_code_alias},
	                             str_{$language_code_alias}.value AS value_{$language_code_alias},
	                             str_{$language_code_alias}.translator_id AS translator_{$language_code_alias},
	                             str_{$language_code_alias}.translation_date AS date_{$language_code_alias}
	                             ";
					$_joins[] = $this->wpdb->prepare( "LEFT JOIN {$this->wpdb->prefix}icl_string_translations str_{$language_code_alias}
	                                                ON str_{$language_code_alias}.string_id = s.id AND str_{$language_code_alias}.language = %s ", $l[ 'code' ] );

					if ( $status_filter == ICL_TM_COMPLETE ) {
						$_where[] .= " AND str_{$language_code_alias}.status = " . ICL_TM_COMPLETE;
					} else {
						if ( empty( $_lwhere ) ) {
							$_lwheres = array();
							$_lwhere  = ' AND (';
							foreach ( $active_languages as $l2 ) {
								if ( $l2[ 'code' ] == $this->sitepress_settings[ 'st' ][ 'strings_language' ] || empty( $user_lang_pairs[ $this->sitepress_settings[ 'st' ][ 'strings_language' ] ][ $l2[ 'code' ] ] ) ) {
									continue;
								}
								$l2code_alias = esc_sql( str_replace( '-', '', $l2[ 'code' ] ) );
								$_lwheres[]   = $this->wpdb->prepare( " str_{$l2code_alias}.status = %d
	                                                          OR str_{$l2code_alias}.translator_id = %d ", ICL_TM_WAITING_FOR_TRANSLATOR, $current_user->ID );
							}
							$_lwhere .= join( ' OR ', $_lwheres ) . ')';
							$_where[] = $_lwhere;
						}
					}
				}

				$sql = "
	                SELECT SQL_CALC_FOUND_ROWS s.id AS string_id, s.language AS string_language, s.context, s.gettext_context, s.name, s.value, s.status, " . join( ', ', $_sels ) . "
	                FROM {$this->wpdb->prefix}icl_strings s " . join( "\n", $_joins ) . "
	                WHERE s.language = '{$this->sitepress_settings['st']['strings_language']}' " . join( ' ', $_where ) . "
	                    {$extra_cond}
	                ORDER BY s.id DESC
	                LIMIT {$offset},{$limit}
	                ";

				$res = $this->wpdb->get_results( $sql, ARRAY_A );
			}

			$this->wp_query->found_posts                    = $this->wpdb->get_var( "SELECT FOUND_ROWS()" );
			$this->wp_query->query_vars[ 'posts_per_page' ] = $limit;
			$this->wp_query->max_num_pages                  = ceil( $this->wp_query->found_posts / $limit );

			if ( $res ) {
				if ( ! empty( $status_filter_lang ) ) {
					foreach ( $res as $row ) {

						$_translations = array();
						foreach ( $active_languages as $l ) {
							if ( $l[ 'code' ] == $this->sitepress_settings[ 'st' ][ 'strings_language' ] ) {
								continue;
							}
							$language_code_alias = esc_sql( str_replace( '-', '', $l[ 'code' ] ) );
							if ( $row[ 'id_' . $language_code_alias ] ) {
								$_translations[ $l[ 'code' ] ] = array(
									'id'               => $row[ 'id_' . $language_code_alias ],
									'status'           => $row[ 'status_' . $language_code_alias ],
									'language'         => $l[ 'code' ],
									'value'            => $row[ 'value_' . $language_code_alias ],
									'translator_id'    => $row[ 'translator_' . $language_code_alias ],
									'translation_date' => $row[ 'date_' . $language_code_alias ]
								);
							}
						}

						$string_translations[ $row[ 'string_id' ] ] = array(
							'string_id'       => $row[ 'string_id' ],
							'string_language' => $row[ 'string_language' ],
							'context'         => $row[ 'context' ],
							'gettext_context' => $row[ 'gettext_context' ],
							'name'            => $row[ 'name' ],
							'value'           => $row[ 'value' ],
							'status'          => ICL_TM_WAITING_FOR_TRANSLATOR,
							'translations'    => $_translations
						);
					}
				} else {
					foreach ( $res as $row ) {

						$_translations = array();

						$_status   = ICL_TM_NOT_TRANSLATED;
						$_statuses = array();
						foreach ( $active_languages as $l ) {
							if ( $l[ 'code' ] == $this->sitepress_settings[ 'st' ][ 'strings_language' ] || empty( $user_lang_pairs[ $this->sitepress_settings[ 'st' ][ 'strings_language' ] ][ $l[ 'code' ] ] ) ) {
								continue;
							}
							$language_code_alias = str_replace( '-', '', $l[ 'code' ] );
							if ( $row[ 'id_' . $language_code_alias ] ) {
								$_translations[ $l[ 'code' ] ] = array(
									'id'               => $row[ 'id_' . $language_code_alias ],
									'status'           => $row[ 'status_' . $language_code_alias ],
									'language'         => $l[ 'code' ],
									'value'            => $row[ 'value_' . $language_code_alias ],
									'translator_id'    => $row[ 'translator_' . $language_code_alias ],
									'translation_date' => $row[ 'date_' . $language_code_alias ]
								);
							}

							$_statuses[ $l[ 'code' ] ] = intval( $row[ 'status_' . $language_code_alias ] );

							if ( $row[ 'status_' . $language_code_alias ] == ICL_TM_WAITING_FOR_TRANSLATOR ) {
								$_status = ICL_TM_WAITING_FOR_TRANSLATOR;
							}
						}
						$_statuses = array_values( $_statuses );
						$_statuses = array_unique( $_statuses );

						if ( $_statuses == array( ICL_TM_NOT_TRANSLATED ) ) {
							$_status = ICL_TM_NOT_TRANSLATED;
						} elseif ( $_statuses == array( ICL_TM_COMPLETE, ICL_TM_NOT_TRANSLATED ) ) {
							$_status = ICL_STRING_TRANSLATION_PARTIAL;
						} elseif ( $_statuses == array( ICL_TM_COMPLETE ) ) {
							$_status = ICL_TM_COMPLETE;
						} elseif ( in_array( ICL_TM_WAITING_FOR_TRANSLATOR, $_statuses ) || in_array( ICL_TM_NEEDS_UPDATE, $_statuses ) ) {
							$_status = ICL_TM_WAITING_FOR_TRANSLATOR;
						}

						$string_translations[ $row[ 'string_id' ] ] = array(
							'string_id'       => $row[ 'string_id' ],
							'string_language' => $row[ 'string_language' ],
							'context'         => $row[ 'context' ],
							'gettext_context' => $row[ 'gettext_context' ],
							'name'            => $row[ 'name' ],
							'value'           => $row[ 'value' ],
							'status'          => $_status,
							'translations'    => $_translations
						);
					}
				}
			}
			/* TRANSLATOR - END */
		} else {

			// removed check for language = default lang
			if ( $status_filter != ICL_TM_WAITING_FOR_TRANSLATOR ) {
				$res = $this->wpdb->get_results( "
	                SELECT SQL_CALC_FOUND_ROWS id AS string_id, language AS string_language, context, gettext_context, name, value, status                
	                FROM  {$this->wpdb->prefix}icl_strings s
	                WHERE 
	                    1
	                    {$extra_cond}
	                ORDER BY string_id DESC
	                LIMIT {$offset},{$limit}
	            ", ARRAY_A );
			} else {
				$res = $this->wpdb->get_results( "
	                SELECT SQL_CALC_FOUND_ROWS s.id AS string_id, s.language AS string_language, s.context, s.gettext_context, s.name, s.value, " . ICL_TM_WAITING_FOR_TRANSLATOR . " AS status
	                FROM  {$this->wpdb->prefix}icl_strings s
	                JOIN {$this->wpdb->prefix}icl_string_translations str ON str.string_id = s.id
	                WHERE 
	                    str.status = " . ICL_TM_WAITING_FOR_TRANSLATOR . "
	                    {$extra_cond}
	                ORDER BY string_id DESC
	                LIMIT {$offset},{$limit}
	            ", ARRAY_A );
			}

			if ( ! is_null( $this->wp_query ) ) {
				$this->wp_query->found_posts                    = $this->wpdb->get_var( "SELECT FOUND_ROWS()" );
				$this->wp_query->query_vars[ 'posts_per_page' ] = $limit;
				$this->wp_query->max_num_pages                  = ceil( $this->wp_query->found_posts / $limit );
			}

			if ( $res ) {
				$extra_cond = '';
				if ( isset( $_GET[ 'translation_language' ] ) ) {
					$extra_cond .= " AND language='" . esc_sql( $_GET[ 'translation_language' ] ) . "'";
				}

				foreach ( $res as $row ) {
					$string_translations[ $row[ 'string_id' ] ] = $row;
					$tr                                         = $this->wpdb->get_results( $this->wpdb->prepare( "
	                    SELECT id, language, status, value, translator_id, translation_date  
	                    FROM {$this->wpdb->prefix}icl_string_translations 
	                    WHERE string_id=%d {$extra_cond}
	                ", $row[ 'string_id' ] ), ARRAY_A );
					if ( $tr ) {
						foreach ( $tr as $t ) {
							$string_translations[ $row[ 'string_id' ] ][ 'translations' ][ $t[ 'language' ] ] = $t;
						}
					}
				}
			}
		}

		return $string_translations;
	}
}