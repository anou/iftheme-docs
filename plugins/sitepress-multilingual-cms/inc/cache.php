<?php
define('ICL_DISABLE_CACHE', false);

if ( !defined( 'ICL_CACHE_TRANSLATIONS' ) ) {
	/**
	 * Constant used for narrowing the cache scope to translations
	 *
	 * @name ICL_TRANSIENT_EXPIRATION
	 * @param string='translations'
	 */
	define( 'ICL_CACHE_TRANSLATIONS', 'translations' );
}
$icl_cache_scopes[ ] = ICL_CACHE_TRANSLATIONS;

if ( !defined( 'ICL_CACHE_LOCALE' ) ) {
	/**
	 * Constant used for narrowing the cache scope to locales
	 *
	 * @name ICL_CACHE_LOCALE
	 * @param string='locale'
	 */
	define( 'ICL_CACHE_LOCALE', 'locale' );
}
$icl_cache_scopes[ ] = ICL_CACHE_LOCALE;

if ( !defined( 'ICL_CACHE_FLAGS' ) ) {
	/**
	 * Constant used for narrowing the cache scope to flags
	 *
	 * @name ICL_CACHE_FLAGS
	 * @param string='flags'
	 */
	define( 'ICL_CACHE_FLAGS', 'flags' );
}
$icl_cache_scopes[ ] = ICL_CACHE_FLAGS;

if ( !defined( 'ICL_CACHE_LANGUAGE_NAME' ) ) {
	/**
	 * Constant used for narrowing the cache scope to language names
	 *
	 * @name ICL_CACHE_LANGUAGE_NAME
	 * @param string='language_name'
	 */
	define( 'ICL_CACHE_LANGUAGE_NAME', 'language_name' );
}
$icl_cache_scopes[ ] = ICL_CACHE_LANGUAGE_NAME;

if ( !defined( 'ICL_CACHE_TERM_TAXONOMY' ) ) {
	/**
	 * Constant used for narrowing the cache scope to term taxonomies
	 *
	 * @name ICL_CACHE_TERM_TAXONOMY
	 * @param string='term_taxonomy'
	 */
	define( 'ICL_CACHE_TERM_TAXONOMY', 'term_taxonomy' );
}
$icl_cache_scopes[ ] = ICL_CACHE_TERM_TAXONOMY;

if ( !defined( 'ICL_CACHE_COMMENT_COUNT' ) ) {
	/**
	 * Constant used for narrowing the cache scope to comments count
	 *
	 * @name ICL_CACHE_COMMENT_COUNT
	 * @param string='comment_count'
	 */
	define( 'ICL_CACHE_COMMENT_COUNT', 'comment_count' );
}
$icl_cache_scopes[ ] = ICL_CACHE_COMMENT_COUNT;

function icl_cache_get($key){
    $icl_cache = get_option('_icl_cache');
    if(isset($icl_cache[$key])){
        return $icl_cache[$key];
    }else{
        return false;
    }
}  

function icl_cache_set($key, $value=null){
    
    global $switched;
    if(!empty($switched)) return; 
    
    $icl_cache = get_option('_icl_cache');
    if(false === $icl_cache){
        delete_option('_icl_cache');
    }
        
    if(!is_null($value)){
        $icl_cache[$key] = $value;    
    }else{
        if(isset($icl_cache[$key])){
            unset($icl_cache[$key]);
        }        
    }
    
    
    update_option('_icl_cache', $icl_cache);
}

function icl_cache_clear($key = false, $key_as_prefix = false){
    if($key === false){
        delete_option('_icl_cache');    
    }else{
        $icl_cache = get_option('_icl_cache');

		if(is_array($icl_cache)) {
			if(isset($icl_cache[$key])){
				unset($icl_cache[$key]);
			}

			if($key_as_prefix) {
				$cache_keys = array_keys($icl_cache);
				foreach($cache_keys as $cache_key) {
					if(strpos($cache_key, $key)===0) {
						unset($icl_cache[$key]);
					}
				}
			}

			// special cache of 'per language' - clear different statuses
			if(false !== strpos($key, '_per_language')){
				foreach($icl_cache as $k => $v){
					if(false !== strpos($k, $key . '#')){
						unset($icl_cache[$k]);
					}
				}
			}

			update_option('_icl_cache', $icl_cache);
		}
    }
}

//function get_arg_value( $args, $key, $string = false ) {
//	if ( is_array( $args[ $key ] ) ) {
//		$where = ' AND ' . $key .' ' . $args[ $key ][ 'op' ] . ' ' . ($string ? '%s' : '%d');
//		$query_arg = $args[ $key ][ 'value' ];
//	} else {
//		$where = ' AND ' . $key . ' = ' . ($string ? '%s' : '%d');
//		$query_arg = $args[ $key ];
//	}
//
//	return array( $where, $query_arg );
//}
//
//function handle_element_type($element_type) {
//	return in_array($element_type, array('post_post'));
//}
//
//function icl_cache_get_translation() {
//	global $wp_query;
//	if(!isset($wp_query) || !isset($wp_query->query_vars_hash)) return false;
//	$cache_key   = $wp_query->query_vars_hash;
//	$cache_group = 'wp_query:posts_translations';
//	return wp_cache_get( $cache_key, $cache_group );
//}
//
//function icl_cache_set_translation($cached_posts_translations) {
//	global $wp_query;
//	if(!isset($wp_query) || !isset($wp_query->query_vars_hash)) return false;
//	$cache_key   = $wp_query->query_vars_hash;
//	$cache_group = 'wp_query:posts_translations';
//	//Remove duplicates
//	$cached_posts_translations = array_map("unserialize", array_unique(array_map("serialize", $cached_posts_translations)));
//	wp_cache_set( $cache_key, $cached_posts_translations, $cache_group );
//
//	$global_cache_key                 = 'global';
//	$global_cache_group               = 'wp_query:posts_translations';
//	$global_cached_posts_translations = wp_cache_get( $cache_key, $cache_group );
//
//	if(!$global_cached_posts_translations) {
//		$global_cached_posts_translations = $cached_posts_translations;
//	} else {
//		foreach($cached_posts_translations as $cached_posts_translation) {
//			$add = true;
//			foreach($global_cached_posts_translations as $global_cached_posts_translation) {
//				if($global_cached_posts_translation->translation_id == $cached_posts_translation->translation_id) {
//					$add = false;
//					break;
//				}
//			}
//			if($add) {
//				$global_cached_posts_translations[] = $cached_posts_translation;
//			}
//		}
//	}
//	//Remove duplicates
//	$global_cached_posts_translations = array_map("unserialize", array_unique(array_map("serialize", $global_cached_posts_translations)));
//
//	wp_cache_set( $global_cache_key, $global_cached_posts_translations, $global_cache_group );
//}
//
///**
// * @param int|array   $source
// * @param string|bool $post_type
// *
// * @return array
// */
//function icl_cache_adjust_post_id_from_cache($source, $post_type = false) {
//	if(!handle_element_type('post_' . $post_type)) return array();
//
//	global $sitepress;
//	$current_language = $sitepress->get_current_language();
//
//	if(!is_array($source)) {
//		$source  = array($source);
//	}
//
//	$results = array();
//	foreach($source as $id) {
//		if(is_numeric($id)) {
//			$element_type = $post_type;
//			if(!$element_type) {
//				$element_type = get_post_type($id);
//			}
//			$element_type = "post_" . $element_type;
//
//			$trid = icl_cache_get_element_trid($id, $element_type);
//			if($trid){
//				$translations = icl_cache_get_translated_elements_from_cache($trid, $element_type);
//				if($translations && isset($translations[$current_language])) {
//					$results[$id] = $translations[$current_language]->element_id;
//				} else {
//					break;
//				}
//			}
//		}
//	}
//	if(count($results)!=count($source)) {
//		$diff = array();
//		if(!$results) {
//			$diff = $source;
//		} else {
//			foreach($source as $id) {
//				if(!isset($results[$id])) {
//					$diff[] = $results[$id];
//				}
//			}
//		}
//
//		$cache = icl_cache_get_translation();
//		foreach($diff as $id) {
//			$t_args = array(
//					'fields'          => '*',
//					'trid'            => false,
//					'element_id'      => $id,
//					'element_type'    => 'post_' . $post_type,
//					'language_code'   => false,
//					'source_language' => false,
//					'result_type'     => 'results',
//					'use_cache'       => true,
//				);
//			$translations = icl_cache_get_translation_element($t_args);
//			$trid = false;
//			if ( !empty( $translations ) ) {
//				foreach($translations as $translation) {
//					if($translation->element_id == $id) {
//						$trid = $translation->trid;
//						break;
//					}
//				}
//				foreach($translations as $translation) {
//					if($translation->trid == $trid && $translation->language_code == $current_language) {
//						$results[] = $translation->element_id;
//						break;
//					}
//				}
//			} else {
//				$trid = $sitepress->get_element_trid($id,'post_' . $post_type);
//				$translations = $sitepress->get_element_translations($trid,'post_' . $post_type,true);
//				foreach($translations as $lang_code => $translation) {
//					if($lang_code == $current_language) {
//						$results[] = $translation->element_id;
//						break;
//					}
//				}
//			}
//			$cache = array_merge($cache, $results);
//			//Remove duplicates
//			$cache = array_map("unserialize", array_unique(array_map("serialize", $cache)));
//		}
//	}
//
//	if(count($results)==count($source)) {
//		return array_values($results);
//	} else {
//		return false;
//	}
//}
//
//function icl_cache_get_element_trid($element_id, $element_type) {
//	if(!handle_element_type($element_type)) return false;
//
//	global $wpdb;
//	$t_args = array(
//			'fields'          => 'trid',
//			'trid'            => false,
//			'element_id'      => $element_id,
//			'element_type'    => $element_type,
//			'language_code'   => false,
//			'source_language' => false,
//			'result_type'     => 'var',
//			'use_cache'       => true,
//		);
//	$trid = icl_cache_get_translation_element($t_args);
//	if(!$trid && $element_id) {
//		$trid         = $wpdb->get_var( "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id={$element_id} AND element_type = '{$element_type}'" );
//	}
//
//	return $trid;
//}
//
//function icl_cache_get_translated_elements_from_post_type($post_type) {
//	if(!handle_element_type('post_' . $post_type)) return array();
//
//	$t_args = array(
//			'fields'          => '*',
//			'trid'            => false,
//			'element_id'      => false,
//			'element_type'    => 'post_' . $post_type,
//			'language_code'   => false,
//			'source_language' => false,
//			'result_type'     => 'results',
//			'use_cache'       => true,
//		);
//	$results = icl_cache_get_translation_element($t_args);
//	if ( !$results ) {
//		global $wpdb;
//		$prepared_translations_sql = "SELECT * FROM {$wpdb->prefix}icl_translations WHERE element_type = %s";
//		$prepared_translations     = $wpdb->prepare( $prepared_translations_sql, array( 'post_' . $post_type ) );
//		$translations_results      = $wpdb->get_results( $prepared_translations );
//
//		$t_args['element_type'] = false;
//
//		if($translations_results) {
//			icl_cache_set_translation($translations_results);
//		}
//		$results = icl_cache_get_translation_element($t_args);
//	}
//	return $results;
//}
//
//function icl_cache_get_translated_elements_from_cache($trid, $element_type) {
//	if(!handle_element_type($element_type)) return false;
//
//	$t_args = array(
//			'fields'          => '*',
//			'trid'            => $trid,
//			'element_id'      => false,
//			'element_type'    => $element_type,
//			'language_code'   => false,
//			'source_language' => false,
//			'result_type'     => 'results',
//			'use_cache'       => true,
//		);
//	$temp_translations = icl_cache_get_translation_element($t_args);
//
//	if($temp_translations) {
//
//		$translations = array();
//		foreach($temp_translations as $temp_translation) {
//			$row = new stdClass();
//			$row->translation_id = $temp_translation->translation_id;
//			$row->language_code = $temp_translation->language_code;
//			$row->element_id = $temp_translation->element_id;
//			$row->original = ($temp_translation->source_language_code == NULL) ? false : $temp_translation->source_language_code;
//			$translations[ $temp_translation->language_code ] = $row;
//		}
//		return $translations;
//	}
//	return false;
//}
//
//function icl_cache_get_element_language_code($element_id, $element_type) {
//	global $wpdb;
//	$t_args = array(
//			'fields'          => 'language_code',
//			'trid'            => false,
//			'element_id'      => $element_id,
//			'element_type'    => $element_type,
//			'language_code'   => false,
//			'source_language' => false,
//			'result_type'     => 'var',
//			'use_cache'       => true,
//		);
//	$this_lang = icl_cache_get_translation_element($t_args);
//	if(!$this_lang) {
//		$language_code_prepared = $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_type=%s AND element_id=%d", array('post_' . $element_type, $_GET[ 'p' ]) );
//		$this_lang = $wpdb->get_var( $language_code_prepared );
//	}
//
//	return $this_lang;
//}
//
//function icl_cache_find_by_page_name($page_name) {
//	$current_language = $this->get_current_language();
//
//	$pid = false;
//
//	$t_args = array(
//			'fields'          => '*',
//			'trid'            => false,
//			'element_id'      => false,
//			'element_type'    => 'post_page',
//			'language_code'   => $current_language,
//			'source_language' => false,
//			'result_type'     => 'results',
//			'use_cache'       => true,
//		);
//	$results = icl_cache_get_translation_element($t_args);
//
//	if($results) {
//		foreach($results as $result) {
//			if(isset($result->post)) {
//				/** @var $post WP_Post */
//				$post = $result->post;
//				if($post->post_name == $page_name) {
//					return $post->ID;
//				}
//			}
//		}
//	}
//
//	if($pid) {
//		global $wpdb;
//		$pid_prepared = $wpdb->prepare( "
//								 SELECT ID
//								 FROM $wpdb->posts p
//								 JOIN {$wpdb->prefix}icl_translations t
//								 ON p.ID = t.element_id AND element_type='post_page'
//								 WHERE p.post_name=%s AND t.language_code = %s
//								 ", $page_name, $current_language );
//		$pid = $wpdb->get_var( $pid_prepared );
//	}
//	return $pid;
//}
//
//function icl_cache_get_post_type( $post_id ) {
//	$t_args = array(
//			'fields'          => '*',
//			'trid'            => false,
//			'element_id'      => $post_id,
//			'element_type'    => false,
//			'language_code'   => false,
//			'source_language' => false,
//			'result_type'     => 'row',
//			'use_cache'       => true,
//		);
//	$row = icl_cache_get_translation_element($t_args);
//
//	$post_type = false;
//	if($row && isset($row->post)) {
//		$post_type = $row->post->post_type;
//	}
//	if(!$post_type) {
//		$post_type = get_post_type($post_id);
//	}
//	return $post_type;
//}
//
///**
// * Queries the icl_translations table based on give arguments
// * $args: array(
// *        'field' => false|string,    //Field name or comma separated value
// *        'trid' => false|int,        //Field value, array or false (*)
// *        'element_id' => false|int,    //Field value, array or false (*)
// *        'element_type' => false|string,    //Field value, array or false (*)
// *        'language_code' => false|string,    //Field value, array or false (*)
// *        'source_language' => false|string,    //Field value, array or false (*)
// *        'result_type' => 'results'|'col'|'row'|'var',    //Result type
// *        'use_cache' => bool,    //Return value from data cached for current $wp_query (if available)
// *    );
// *
// * (*) if array, use this format:
// *    array(
// *        'op' => '=',    // or any allowed operation
// *        'value' => '',    // a value to filter (or %something% if using 'LIKE' as operator
// *  )
// *
// * @param      $args
// *
// * @param bool $use_global_cache_fallback
// *
// * @return array|bool|mixed|null|string
// */
//function icl_cache_get_translation_element($args, $use_global_cache_fallback = false ) {
//	if ( !$args || count( $args ) == 0 ) {
//		return false;
//	}
//
//	global $wpdb;
//
//	$defaults = array(
//		'fields'          => false,
//		'trid'            => false,
//		'element_id'      => false,
//		'element_type'    => false,
//		'language_code'   => false,
//		'source_language' => false,
//		'result_type'     => 'results',
//		'use_cache'       => true,
//	);
//
//	$args = array_merge( $defaults, $args );
//
//	if ( !$args[ 'fields' ] ) {
//		return false;
//	}
//
//	$where      = "";
//	$query_args = array();
//	if ( $args[ 'trid' ] ) {
//		$key = 'trid';
//		$arg_value = get_arg_value( $args, $key );
//		$where .= $arg_value[ 0 ];
//		$query_args[ $key ] = $arg_value[ 1 ];
//	}
//	if ( $args[ 'element_id' ] ) {
//		$key = 'element_id';
//		$arg_value = get_arg_value( $args, $key );
//		$where .= $arg_value[ 0 ];
//		$query_args[ $key ] = $arg_value[ 1 ];
//	}
//	if ( $args[ 'element_type' ] ) {
//		$key = 'element_type';
//		$arg_value = get_arg_value( $args, $key, true );
//		$where .= $arg_value[ 0 ];
//		$query_args[ $key ] = $arg_value[ 1 ];
//	}
//	if ( $args[ 'language_code' ] ) {
//		$key = 'language_code';
//		$arg_value = get_arg_value( $args, $key, true );
//		$where .= $arg_value[ 0 ];
//		$query_args[ $key ] = $arg_value[ 1 ];
//	}
//	if ( $args[ 'source_language' ] ) {
//		$key = 'source_language';
//		$arg_value = get_arg_value( $args, $key, true );
//		$where .= $arg_value[ 0 ];
//		$query_args[ $key ] = $arg_value[ 1 ];
//	}
//
//	if ( $where ) {
//		/** @var $cached_posts_translations array() */
//		$cached_posts_translations = false;
//		if ( $args[ 'use_cache' ] ) {
//			if($use_global_cache_fallback) {
//				$cached_posts_translations = icl_cache_get_translation(true);
//			} else {
//				$cached_posts_translations = icl_cache_get_translation();
//			}
//		}
//		if ( !$cached_posts_translations ) {
//			if(!$use_global_cache_fallback) {
//				return icl_cache_get_translation_element($args, true );
//			} else {
//				$where          = 'WHERE 1=1 ' . $where;
//				$prepared_query = $wpdb->prepare( "SELECT " . $args[ 'fields' ] . " FROM {$wpdb->prefix}icl_translations " . $where, array_values( $query_args ) );
//				switch ( $args[ 'result_type' ] ) {
//					case 'results':
//						$filtered_results =  $wpdb->get_results( $prepared_query );
//
//						if(!$filtered_results || count($filtered_results)==0) return false;
//
//						$final_filtered_results = array();
//						return $final_filtered_results;
//					case 'col':
//						$filtered_results =  $wpdb->get_col( $prepared_query );
//						return $filtered_results;
//					case 'row':
//						$filtered_result =  $wpdb->get_row( $prepared_query );
//						return $filtered_result;
//					case 'var':
//						$filtered_results =  $wpdb->get_var( $prepared_query );
//						return $filtered_results;
//					default:
//						return false;
//				}
//			}
//		} else {
//			$filtered_results = array();
//			foreach ( $cached_posts_translations as $cached_posts_translation ) {
//				$add = true;
//
//				foreach ( $query_args as $field => $value ) {
//					if ($value!==false && $cached_posts_translation->$field != $value ) {
//						$add = false;
//						break;
//					}
//				}
//				if ( $add ) {
//					$filtered_results[ ] = $cached_posts_translation;
//				}
//			}
//
//			if(!$filtered_results || count($filtered_results)==0) {
//				if(!$use_global_cache_fallback) {
//					return icl_cache_get_translation_element($args, true );
//				} else {
//					return false;
//				}
//			}
//
//			if($args[ 'fields' ]=='*') {
//				$filtered_result_item = (array)$filtered_results[ 0 ];
//				$filtered_result_keys = array_keys( $filtered_result_item );
//				$args[ 'fields' ] = $filtered_result_keys[0];
//			}
//
//			switch ( $args[ 'result_type' ] ) {
//				case 'results':
//					return $filtered_results;
//				case 'col':
//					$cols = array();
//					foreach ( $filtered_results as $filtered_result ) {
//						$cols[ ] = $filtered_result->$args[ 'fields' ];
//					}
//
//					return $cols;
//				case 'row':
//					return $filtered_results[ 0 ];
//				case 'var':
//					return $filtered_results[ 0 ]->$args[ 'fields' ];
//				default:
//					return false;
//			}
//		}
//	}
//}

class icl_cache{
   
    private $data;
    
    function __construct($name = "", $cache_to_option = false){
        $this->data = array();
        $this->name = $name;
        $this->cache_to_option = $cache_to_option;
        
        if ($cache_to_option) {
            $this->data = icl_cache_get($name.'_cache_class');
            if ($this->data == false){
                $this->data = array();
            }
        }
    }
    
    function get($key) {
        if(ICL_DISABLE_CACHE){
            return null;
        }
        return isset($this->data[$key]) ? $this->data[$key] : false;
    }
    
    function has_key($key){
        if(ICL_DISABLE_CACHE){
            return false;
        }
        return array_key_exists($key, (array)$this->data);
    }
    
    function set($key, $value) {
        if(ICL_DISABLE_CACHE){
            return;
        }
        $this->data[$key] = $value;
        if ($this->cache_to_option) {
            icl_cache_set($this->name.'_cache_class', $this->data);
        }
    }
    
    function clear() {
        $this->data = array();
        if ($this->cache_to_option) {
            icl_cache_clear($this->name.'_cache_class');
        }
    }
}
