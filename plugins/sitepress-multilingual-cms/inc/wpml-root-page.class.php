<?php

define( "QUERY_IS_ROOT", 1 );
define( "QUERY_IS_OTHER_THAN_ROOT", 2 );
define( "QUERY_IS_NOT_FOR_POST", 3 );
define( "QUERY_IS_CORRUPT", 4 );

class WPML_Root_Page {

	/**
	 * @param $url
	 * Filters links to the root page, so that they are displayed properly in the front-end.
	 *
	 * @return mixed
	 */
	public static function filter_root_permalink( $url ) {
		global $sitepress;

		if ( $sitepress && self::is_root_page( $url ) ) {
			$root_slug = self::get_root_slug();
			$method    = '';
			$new_url   = str_replace( 'http://', '', $url );
			if ( $new_url != $url ) {
				$method = 'http://';
			} else {
				$new_url = str_replace( 'https://', '', $url );
				if ( $new_url != $url ) {
					$method = 'https://';
				}
			}

			$query = '';

			if ( strpos( $new_url, '?' ) !== false ) {

				$split_by_get_url  = explode( '?', $new_url );
				$url_without_query = array_shift( $split_by_get_url );
				$query             = implode( '?', $split_by_get_url );
			} else {
				$url_without_query = $new_url;
			}

			$slugs = explode( '/', $url_without_query );

			foreach ( $slugs as $key => $slug ) {
				if ( $slug == '' || strpos( $slug, parse_url( $url, PHP_URL_HOST ) ) !== false ) {
					unset( $slugs[ $key ] );
				}
			}

			$last_slug   = array_pop( $slugs );
			$second_slug = array_pop( $slugs );

			if ( $second_slug == $root_slug && ( is_numeric( $last_slug ) || $last_slug == "" ) ) {
				$url_without_query   = str_replace( '/' . $second_slug . '/', '/', $url_without_query );
				$potential_lang_slug = array_pop( $slugs );
			} elseif ( $last_slug == $root_slug ) {
				$url_without_query   = str_replace( '/' . $last_slug, '/', $url_without_query );
				$potential_lang_slug = $second_slug;
			} else {
				$potential_lang_slug = array_pop( $slugs );
			}

			$all_langs = $sitepress->get_active_languages();

			foreach ( $all_langs as $lang ) {
				if ( $lang[ 'code' ] == $potential_lang_slug ) {
					$url_without_query = str_replace( '/' . $potential_lang_slug, '/', $url_without_query );
					break;
				}
			}
			$new_url = trailingslashit( $url_without_query );

			list( $new_url, $query ) = self::filter_pagination_on_root_preview( $last_slug, $query, $new_url );

			if ( $query != '' ) {
				$new_url = $new_url . '?' . $query;
			}

			if ( empty( $slugs ) ) {
				$url = $method . $new_url;
			}
		}

		return $url;
	}

	/**
	 * @param $last_slug string
	 * @param $query string
	 * @param $new_url string
	 *
	 * @return array
	 */
	public static function filter_pagination_on_root_preview( $last_slug, $query, $new_url ) {

		if ( is_numeric( $last_slug ) && strpos( $query, 'preview_id=' . self::get_root_id() ) ) {
			$new_url = str_replace( '/' . $last_slug . '/', '/', $new_url );
			$query .= '&page=' . $last_slug;
		}

		return array( $new_url, $query );
	}

	/**
	 * Checks if the value in $_SERVER['REQUEST_URI] points towards the root page.
	 * Therefore this can be used to check if the current request points towards the root page.
	 *
	 * @return bool
	 */
	public static function is_current_request_root() {
		return self::is_root_page( $_SERVER[ 'REQUEST_URI' ] );
	}

	/**
	 * @param $requested_url string
	 *
	 * Checks if a requested url points towards the root page.
	 *
	 * @return bool
	 */
	public static function is_root_page( $requested_url ) {
		global $sitepress;

		$cached_val = wp_cache_get( md5( $requested_url ) );

		if ( $cached_val !== false ) {
			return (bool) $cached_val;
		}

		if ( ! $sitepress || ! self::is_root_page_enabled() ) {
			$result = false;
		} else {
			$request_parts = self::get_slugs_and_get_query( $requested_url );
			$slugs         = $request_parts[ 'slugs' ];
			$gets          = $request_parts[ 'querystring' ];

			$target_of_gets = self::get_query_target_from_query_string( $gets );

			if ( $target_of_gets == QUERY_IS_ROOT ) {
				$result = true;
			} elseif ( $target_of_gets == QUERY_IS_OTHER_THAN_ROOT ) {
				$result = false;
			} elseif ( $target_of_gets == QUERY_IS_NOT_FOR_POST && self::query_points_to_archive( $gets ) ) {
				$result = false;
			} else {
				if ( self::slugs_point_to_root( $slugs ) ) {
					$result = true;
				} else {
					$result = false;
				}
			}
		}

		if ( $result ) {
			wp_cache_add( md5( $requested_url ), 1 );
		} else {
			wp_cache_add( md5( $requested_url ), 0 );
		}

		return $result;
	}

	/**
	 * Checks if the root page is even enabled in the SitePress settings.
	 *
	 * @return bool
	 */
	private static function is_root_page_enabled() {
		global $sitepress;

		$is_root_enabled = true;

		$urls = $sitepress->get_setting( 'urls' );

		if ( ! $urls
		     || ! isset( $urls[ 'directory_for_default_language' ] )
		     || ! $urls[ 'directory_for_default_language' ]
		     || ! isset( $urls[ 'root_page' ] )
		     || $urls[ 'root_page' ] == 0
		) {
			$is_root_enabled = false;
		}

		return $is_root_enabled;

	}

	/**
	 * Returns the id of the root page or false if it isn't set.
	 *
	 * @return bool|int
	 */
	public static function get_root_id() {
		global $sitepress;

		$root_id = false;

		$urls = $sitepress->get_setting( 'urls' );
		if ( isset( $urls[ 'root_page' ] ) ) {
			$root_id = $urls[ 'root_page' ];
		}

		return $root_id;

	}

	/**
	 *
	 * Returns the slug of the root page or false if non exists.
	 *
	 * @return bool|string
	 */
	private static function get_root_slug() {

		$root_id = self::get_root_id();

		$root_slug = false;
		if ( $root_id ) {
			$root_page_object = get_post( $root_id );
			if ( $root_page_object && isset( $root_page_object->post_name ) ) {
				$root_slug = $root_page_object->post_name;
			}
		}

		return $root_slug;
	}

	/**
	 * @param $requested_url string
	 *
	 * Takes a request_url in the format of $_SERVER['REQUEST_URI']
	 * and returns an associative array containing its slugs ans query string.
	 *
	 * @return array
	 */
	private static function get_slugs_and_get_query( $requested_url ) {

		$result = array();

		$request_path      = parse_url( $requested_url, PHP_URL_PATH );
		$request_path      = wpml_strip_subdir_from_url( $request_path );
		$slugs             = self::get_slugs_array( $request_path );
		$result[ 'slugs' ] = $slugs;

		$query_string = parse_url( $requested_url, PHP_URL_QUERY );
		if ( ! $query_string ) {
			$query_string = '';
		}
		$result[ 'querystring' ] = $query_string;

		return $result;
	}

	/**
	 * @param $path string
	 *
	 * Turns a query string into an array of its slugs.
	 * The array is filtered so to not contain empty values and
	 * consecutively and numerically indexed starting at 0.
	 *
	 * @return array
	 */
	private static function get_slugs_array( $path ) {
		$slugs = explode( '/', $path );
		$slugs = array_filter( $slugs );
		$slugs = array_values( $slugs );

		return $slugs;
	}

	/**
	 * @param $slugs array
	 *
	 * Checks if a given set of slugs points towards the root page or not.
	 * The result of this can always be overridden by GET parameters and is not a certain
	 * check as to being on the root page or not.
	 *
	 * @return bool
	 */
	private static function slugs_point_to_root( $slugs ) {

		$result = true;

		if ( ! empty( $slugs ) ) {
			$root_slug = self::get_root_slug();

			$last_slug   = array_pop( $slugs );
			$second_slug = array_pop( $slugs );
			$third_slug  = array_pop( $slugs );

			if ( ( $root_slug != $last_slug && ! is_numeric( $last_slug ) )
			     || ( is_numeric( $last_slug )
			          && $second_slug != null
			          && $root_slug != $second_slug
			          && ( ( 'page' != $second_slug )
			               || ( 'page' == $second_slug && ( $third_slug && $third_slug != $root_slug ) ) ) )
			) {
				$result = false;
			}
		}

		return $result;
	}

	/**
	 * @param $query_string
	 *
	 * Turns a given query string into an associative array of its parameters.
	 *
	 * @return array
	 */
	private static function get_query_array_from_string( $query_string ) {
		$all_query_params = array();
		parse_str( $query_string, $all_query_params );

		return $all_query_params;
	}

	/**
	 * @param $query_string string
	 *
	 * Checks if the WP_Query functionality can decisively recognize if a querystring points
	 * towards an archive.
	 *
	 * @return bool
	 */
	private static function query_points_to_archive( $query_string ) {

		remove_action( 'parse_query', 'wpml_home_url_parse_query' );

		$query_string = str_replace( '?', '', $query_string );
		$query        = new WP_Query( $query_string );

		add_action( 'parse_query', 'wpml_home_url_parse_query' );

		return $query->is_archive();
	}

	/**
	 * @param $query_string string
	 * Checks if a given query string decisively points towards or away from the root page.
	 *
	 * @return int
	 */
	private static function get_query_target_from_query_string( $query_string ) {
		$params_array = self::get_query_array_from_string( $query_string );

		return self::get_query_target_from_params_array( $params_array );
	}

	/**
	 * @param $query_params array
	 *
	 * Checks if a set of query parameters decisively points towards or away from the root page.
	 *
	 * @return int
	 */
	private static function get_query_target_from_params_array( $query_params ) {

		if ( ! isset( $query_params[ 'p' ] ) && ! isset( $query_params[ 'page_id' ] ) && ! isset( $query_params[ 'name' ] ) && ! isset( $query_params[ 'pagename' ] ) ) {
			$result = QUERY_IS_NOT_FOR_POST;
		} else {

			$root_id   = self::get_root_id();
			$root_slug = self::get_root_slug();

			if ( ( isset( $query_params[ 'p' ] ) && $query_params[ 'p' ] != $root_id )
			     || ( isset( $query_params[ 'page_id' ] ) && $query_params[ 'page_id' ] != $root_id )
			     || ( isset( $query_params[ 'name' ] ) && $query_params[ 'name' ] != $root_slug )
			     || ( isset( $query_params[ 'pagename' ] ) && $query_params[ 'pagename' ] != $root_slug )
			     || ( isset( $query_params[ 'preview_id' ] ) && $query_params[ 'preview_id' ] != $root_id )
			) {
				$result = QUERY_IS_OTHER_THAN_ROOT;
			} elseif ( ( isset( $query_params[ 'p' ] ) && $query_params[ 'p' ] == $root_id )
			           || ( isset( $query_params[ 'page_id' ] ) && $query_params[ 'page_id' ] == $root_id )
			           || ( isset( $query_params[ 'name' ] ) && $query_params[ 'name' ] == $root_slug )
			           || ( isset( $query_params[ 'pagename' ] ) && $query_params[ 'pagename' ] == $root_slug )
			           || ( isset( $query_params[ 'preview_id' ] ) && $query_params[ 'preview_id' ] == $root_id )

			) {
				$result = QUERY_IS_ROOT;
			} else {
				$result = QUERY_IS_CORRUPT;
			}
		}

		return $result;
	}

	/**
	 * @param $post false|WP_Post
	 * Filters the postID used by the preview for the case of the root page preview.
	 *
	 * @return null|WP_Post
	 */
	public static function front_page_id_filter( $post ) {

		$preview_id = isset( $_GET[ 'preview_id' ] ) ? $_GET[ 'preview_id' ] : - 1;

		if ( $preview_id == self::get_root_id() ) {
			$post = get_post( $preview_id );
		}

		return $post;
	}
}