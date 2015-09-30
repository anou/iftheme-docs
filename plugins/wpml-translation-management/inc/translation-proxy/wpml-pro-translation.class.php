<?php
/**
 * @package wpml-core
 * @package wpml-core-pro-translation
 */
require WPML_TM_PATH . '/inc/translation-proxy/helpers/wpml-cms-id.class.php';
require WPML_TM_PATH . '/inc/translation-proxy/helpers/wpml-tp-download-helper.class.php';

class WPML_Pro_Translation{

	public $errors;
	private $tmg;
	const CMS_FAILED = 0;
	const CMS_SUCCESS = 1;

	/** @var  WPML_TM_CMS_ID $cms_id_helper */
	private $cms_id_helper;
	/** @var  WPML_TP_Download_Helper $download_helper */
	private $download_helper;

	function __construct() {

		$this->errors = array();
		global $iclTranslationManagement;
		$this->tmg             =& $iclTranslationManagement;
		$this->cms_id_helper   = new WPML_TM_CMS_ID();
		$this->download_helper = new WPML_TP_Download_Helper( $this->cms_id_helper, $this );
		add_filter( 'xmlrpc_methods', array( $this, 'custom_xmlrpc_methods' ) );
		add_action( 'post_submitbox_start', array( $this, 'post_submitbox_start' ) );
		add_action( 'icl_ajx_custom_call', array( $this, 'ajax_calls' ), 10, 2 );
		add_action( 'icl_hourly_translation_pickup', array( $this, 'poll_for_translations' ) );
	}

	function ajax_calls( $call, $data ) {
		global $sitepress_settings, $sitepress;
		switch ( $call ) {
			case 'set_pickup_mode':
				$method                                     = intval( $data[ 'icl_translation_pickup_method' ] );
				$iclsettings[ 'translation_pickup_method' ] = $method;
				$sitepress->save_settings( $iclsettings );

				try {
					$project = TranslationProxy::get_current_project(  );
					$project->set_delivery_method( $method == ICL_PRO_TRANSLATION_PICKUP_XMLRPC ? 'xmlrpc' : 'polling' );
				} catch ( Exception $e ) {
					echo wp_json_encode( array( 'error' => __( 'Could not update the translation pickup mode.', 'sitepress' ) ) );
				}

				if ( $method == ICL_PRO_TRANSLATION_PICKUP_XMLRPC ) {
					wp_clear_scheduled_hook( 'icl_hourly_translation_pickup' );
				} else {
					wp_schedule_event( time(), 'hourly', 'icl_hourly_translation_pickup' );
				}

				echo json_encode( array( 'message' => 'OK' ) );
				break;
			case 'pickup_translations':
				$errors                  = '';
				$status_completed        = '';
				$status_cancelled        = '';

				if ( $sitepress_settings[ 'translation_pickup_method' ] == ICL_PRO_TRANSLATION_PICKUP_POLLING ) {
					$results = $this->poll_for_translations( true );

					if ( $results[ 'errors' ]  ) {
						$status = __( 'Error', 'sitepress' );
						$errors = join( '<br />', $results[ 'errors' ] );
					} else {
						$status = __( 'OK', 'sitepress' );

						$status_completed = '&nbsp;' . sprintf( __( 'Fetched %d translations.', 'sitepress' ), $results[ 'completed' ] );
						if ( $results[ 'cancelled' ] ) {
							$status_cancelled = '&nbsp;' . sprintf( __( '%d translations have been marked as cancelled.', 'sitepress' ), $results[ 'cancelled' ] );
						}
					}
				} else {
					$status = __( 'Manual pick up is disabled.', 'sitepress' );
				}
				echo json_encode( array(
						                  'status'           => $status,
						                  'errors'           => $errors,
						                  'completed'        => $status_completed,
						                  'cancelled'        => $status_cancelled,
				                  ) );

				break;
		}
	}

	/**
	 * @param WP_Post|WPML_Package $post
	 * @param                      $target_languages
	 * @param int                  $translator_id
	 * @param                      $job_id
	 *
	 * @return bool|int
	 */
	function send_post( $post, $target_languages, $translator_id, $job_id ) {
		global $sitepress, $iclTranslationManagement;

		$this->maybe_init_translation_management( $iclTranslationManagement );

		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}
		if ( ! $post ) {
			return false;
		}

		$post_id             = $post->ID;
		$post_type           = $post->post_type;
		$element_type_prefix = $iclTranslationManagement->get_element_type_prefix_from_job_id( $job_id );
		$element_type        = $element_type_prefix . '_' . $post_type;

		$err = false;
		$res = false;

		$source_language = $sitepress->get_language_for_element( $post_id, $element_type );


		$target_language = is_array( $target_languages ) ? end( $target_languages ) : $target_languages;
		if ( empty( $target_language ) || $target_language === $source_language ) {
			return false;
		}

		$translation = $this->tmg->get_element_translation( $post_id, $target_language, $element_type );

		if ( ! $translation ) { // translated the first time
			$err = true;
		}

		if ( ! $err && ( $translation->needs_update || $translation->status == ICL_TM_NOT_TRANSLATED || $translation->status == ICL_TM_WAITING_FOR_TRANSLATOR ) ) {
			$tp_networking = wpml_tm_load_tp_networking();
			$project       = $tp_networking->get_current_project();

			if ( $iclTranslationManagement->is_external_type( $element_type_prefix ) ) {
				$job_object = new WPML_External_Translation_Job( $job_id );
			} else {
				$job_object = new WPML_Post_Translation_Job( $job_id );
				$job_object->load_terms_from_post_into_job();
			}

			list( $err, $project, $res ) = $job_object->send_to_tp( $project, TranslationProxy::is_batch_mode(), $translator_id, $this->cms_id_helper, $this->tmg );
			if ( $err ) {
				$this->enqueue_project_errors( $project );
			}
		}

		return $err ? false : $res; //last $ret
	}

	function server_languages_map( $language_name, $server2plugin = false ) {
		if ( is_array( $language_name ) ) {
			return array_map( array( $this, 'server_languages_map' ), $language_name );
		}
		$map = array(
			'Norwegian Bokmål'     => 'Norwegian',
			'Portuguese, Brazil'   => 'Portuguese',
			'Portuguese, Portugal' => 'Portugal Portuguese'
		);

		$map = $server2plugin ? array_flip( $map ) : $map;

		return isset( $map[ $language_name ] ) ? $map[ $language_name ] : $language_name;
	}

	function custom_xmlrpc_methods( $methods ) {
		//ICanLocalize XMLRPC calls for migration
		//Translation proxy XMLRPC calls
		$icl_methods[ 'translationproxy.test_xmlrpc' ]                = array( $this, '_test_xmlrpc' );
		$icl_methods[ 'translationproxy.updated_job_status' ]         = array( $this, 'xmlrpc_updated_job_status_with_log' );
		$icl_methods[ 'translationproxy.notify_comment_translation' ] = array( $this, '_xmlrpc_add_message_translation' );

		$methods = array_merge( $methods, $icl_methods );
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			if ( preg_match( '#<methodName>([^<]+)</methodName>#i', $GLOBALS[ 'HTTP_RAW_POST_DATA' ], $matches ) ) {
				$method = $matches[ 1 ];
				if ( in_array( $method, array_keys( $icl_methods ) ) ) {
					set_error_handler( array( $this, "translation_error_handler" ), E_ERROR | E_USER_ERROR );
				}
			}
		}

		return $methods;
	}

	function xmlrpc_updated_job_status_with_log( $args ) {
		
		require_once WPML_TM_PATH . '/inc/translation-proxy/translationproxy-com-log.class.php';

		TranslationProxy_Com_Log::log_xml_rpc( array( 'tp_job_id' => $args[0],
													  'cms_id'    => $args[1],
													  'status'    => $args[2],
													  'signature' => 'UNDISCLOSED') );
	
		$ret = $this->xmlrpc_updated_job_status( $args );
		
		TranslationProxy_Com_Log::log_xml_rpc( array( 'result'    => $ret ) );
		
		return $ret;
	}

	/**
	 *
	 * Handle job update notifications from TP
	 *
	 * @param $args
	 * @return int|string
	 */
	function xmlrpc_updated_job_status($args)
	{

		$translation_proxy_job_id 	= $args[0];
		$cms_id 					= $args[1];
		$status 					= $args[2];
		$signature 					= $args[3];
		

		//get current project
		$project = TranslationProxy::get_current_project();
        if (!$project) {
            return "Project does not exist";
        }

		//check signature
		$signature_chk = sha1( $project->id . $project->access_key . $translation_proxy_job_id . $cms_id . $status );

		if ( $signature_chk != $signature ) {
			return "Wrong signature";
		}

		switch ($status) {
			case "translation_ready" :
				$ret = $this->download_and_process_translation( $translation_proxy_job_id, $cms_id );
				break;
			case "cancelled" :
				$ret =  $this->cancel_translation( $translation_proxy_job_id, $cms_id );
				break;
			default :
				return "Not supported status: {$status}";
		}

		if ( $this->errors ) {
			return join( '', $this->errors );
		}

		if ( (bool) $ret === true ) {
			return self::CMS_SUCCESS;
		}

		// return failed by default
		return self::CMS_FAILED;

	}


	/**
	 *
	 * Cancel translation for given cms_id
	 *
	 * @param $rid
	 * @param $cms_id
	 * @return bool
	 */
	function cancel_translation( $rid, $cms_id ) {
		global $sitepress, $wpdb, $WPML_String_Translation, $iclTranslationManagement;

		$res           = false;
		if ( empty( $cms_id ) ) { // it's a string
			if ( isset( $WPML_String_Translation ) ) {
				$res = $WPML_String_Translation->cancel_remote_translation( $rid ) ;
			}
		}
		else{
			$cms_id_parts      = $this->cms_id_helper->parse_cms_id( $cms_id );
			$post_type    = $cms_id_parts[ 0 ];
			$_element_id  = $cms_id_parts[ 1 ];
			$_target_lang = $cms_id_parts[ 3 ];
			$job_id       = isset( $cms_id_parts[ 4 ] ) ? $cms_id_parts[ 4 ] : false;

			$element_type_prefix = 'post';
			if ( $job_id ) {
				$element_type_prefix = $iclTranslationManagement->get_element_type_prefix_from_job_id( $job_id );
			}

			$element_type = $element_type_prefix . '_' . $post_type;
			if ( $_element_id && $post_type && $_target_lang ) {
				$trid = $sitepress->get_element_trid( $_element_id, $element_type );
			} else {
				$trid = null;
			}

			if ( $trid ) {
				$translation_id_query   = "SELECT i.translation_id
																FROM {$wpdb->prefix}icl_translations i
																JOIN {$wpdb->prefix}icl_translation_status s
																ON i.translation_id = s.translation_id
																WHERE i.trid=%d
																	AND i.language_code=%s
																	AND s.status IN (%d, %d)
																LIMIT 1";
				$translation_id_args    = array( $trid, $_target_lang, ICL_TM_IN_PROGRESS, ICL_TM_WAITING_FOR_TRANSLATOR );
				$translation_id_prepare = $wpdb->prepare( $translation_id_query, $translation_id_args );
				$translation_id = $wpdb->get_var( $translation_id_prepare );

				if ( $translation_id ) {
					global $iclTranslationManagement;
					$iclTranslationManagement->cancel_translation_request( $translation_id );
					$res = true;
				}
			}
		}

		return $res;
	}

	function _test_xmlrpc() {
		return true;
	}

	function _xmlrpc_add_message_translation( $args ) {
		global $wpdb, $sitepress, $wpml_add_message_translation_callbacks;
		$signature   = $args[ 0 ];
		$rid         = $args[ 2 ];
		$translation = $args[ 3 ];

		$access_key      = $sitepress->get_setting( 'access_key' );
		$site_id         = $sitepress->get_setting( 'site_id' );
		$signature_check = md5( $access_key . $site_id . $rid );
		if ( $signature != $signature_check ) {
			return 0; // array('err_code'=>1, 'err_str'=> __('Signature mismatch','sitepress'));
		}

		$res = $wpdb->get_row( $wpdb->prepare("	SELECT to_language, object_id, object_type
												FROM {$wpdb->prefix}icl_message_status
												WHERE rid= %d ", $rid ) );
		if ( ! $res ) {
			return 0;
		}

		$to_language = $res->to_language;
		$object_id   = $res->object_id;
		$object_type = $res->object_type;

		try {
			if ( is_array( $wpml_add_message_translation_callbacks[ $object_type ] ) ) {
				foreach ( $wpml_add_message_translation_callbacks[ $object_type ] as $callback ) {
					if ( ! is_null( $callback ) ) {
						call_user_func( $callback, $object_id, $to_language, $translation );
					}
				}
			}
			$wpdb->update( $wpdb->prefix . 'icl_message_status', array( 'status' => MESSAGE_TRANSLATION_COMPLETE ), array( 'rid' => $rid ) );
		} catch ( Exception $e ) {
			return $e->getMessage() . '[' . $e->getFile() . ':' . $e->getLine() . ']';
		}

		return 1;
	}

	/**
	 *
	 * Downloads translation from TP and updates its document
	 *
	 * @param $translation_proxy_job_id
	 * @param $cms_id
	 *
	 * @return bool|string
	 *
	 */
	function download_and_process_translation( $translation_proxy_job_id, $cms_id ) {
		try {
			global $wpdb;

			if ( empty( $cms_id ) ) { // it's a string
				//TODO: [WPML 3.3] this should be handled as any other element type in 3.3
				$target = $wpdb->get_var( $wpdb->prepare( "SELECT target FROM {$wpdb->prefix}icl_core_status WHERE rid=%d", $translation_proxy_job_id ) );

				return $this->process_translated_string( $translation_proxy_job_id, $target );
			} else {
				$translation_id = $this->cms_id_helper->get_translation_id( $cms_id );
				if ( ! empty ( $translation_id ) ) {
					if ( $this->add_translated_document( $translation_id, $translation_proxy_job_id ) === true ) {
						$this->throw_exception_for_mysql_errors();

						return true;
					} else {
						$this->throw_exception_for_mysql_errors();

						return false;
					}
					//in other case do not process that request
				} else {

					return false;
				}
			}
		} catch ( Exception $e ) {
			return $e->getMessage();
		}
	}

	function add_translated_document( $translation_id, $translation_proxy_job_id ) {
		global $wpdb, $sitepress;
		$project = TranslationProxy::get_current_project();

		$translation_info = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translations WHERE translation_id=%d", $translation_id ) );
		$translation      = $project->fetch_translation( $translation_proxy_job_id );
		if ( ! $translation ) {
			$this->errors = array_merge( $this->errors, $project->errors );
		} else {
			$translation = apply_filters( 'icl_data_from_pro_translation', $translation );
		}
		$ret = true;

		if ( ! empty( $translation ) ) {

			try {
				/** @var $job_xliff_translation WP_Error|Array */
				$xliff                 = new WPML_TM_xliff();
				$job_xliff_translation = $xliff->get_job_xliff_translation( $translation );
				if ( is_wp_error( $job_xliff_translation ) ) {
					$this->add_error( $job_xliff_translation->get_error_message() );

					return false;
				} else {
					$data = $job_xliff_translation[1];
				}
				wpml_tm_save_data( $data );

				$translations = $sitepress->get_element_translations( $translation_info->trid, $translation_info->element_type, false, true, true );
				if ( isset( $translations[ $translation_info->language_code ] ) ) {
					$translation = $translations[ $translation_info->language_code ];
					if ( isset( $translation->element_id ) && $translation->element_id ) {
						$translation_post_type_prepared = $wpdb->prepare( "SELECT post_type FROM $wpdb->posts WHERE ID=%d", array( $translation->element_id ) );
						$translation_post_type          = $wpdb->get_var( $translation_post_type_prepared );
					} else {
						$translation_post_type = implode( '_', array_slice( explode( '_', $translation_info->element_type ), 1 ) );
					}
					if ( $translation_post_type == 'page' ) {
						$url = get_option( 'home' ) . '?page_id=' . $translation->element_id;
					} else {
						$url = get_option( 'home' ) . '?p=' . $translation->element_id;
					}
					$project->update_job( $translation_proxy_job_id, $url );
				} else {
					$project->update_job( $translation_proxy_job_id );
				}
			} catch ( Exception $e ) {
				$ret = false;
			}
		}

		return $ret;
	}

	/**
	 * Resolve a URL relative to a base path. This happens to work with POSIX
	 * file names as well. This is based on RFC 2396 section 5.2.
	 *
	 * @param string $base
	 * @param string $url
	 *
	 * @return bool|string
	 */
    function resolve_url($base, $url) {
            if (!strlen($base)) return $url;
            // Step 2
            if (!strlen($url)) return $base;
            // Step 3
            if (preg_match('!^[a-z]+:!i', $url)) return $url;
            $base = parse_url($base);
            if ($url{0} == "#") {
                    // Step 2 (fragment)
                    $base['fragment'] = substr($url, 1);
                    return $this->unparse_url($base);
            }
            unset($base['fragment']);
            unset($base['query']);
            if (substr($url, 0, 2) == "//") {
                    // Step 4
                    return $this->unparse_url(array(
                            'scheme'=>$base['scheme'],
                            'path'=>$url,
                    ));
            } else if ($url{0} == "/") {
                    // Step 5
                    $base['path'] = $url;
            } else {
                    // Step 6
                    $path = explode('/', $base['path']);
                    $url_path = explode('/', $url);
                    // Step 6a: drop file from base
                    array_pop($path);
                    // Step 6b, 6c, 6e: append url while removing "." and ".." from
                    // the directory portion
                    $end = array_pop($url_path);
                    foreach ($url_path as $segment) {
                            if ($segment == '.') {
                                    // skip
                            } else if ($segment == '..' && $path && $path[sizeof($path)-1] != '..') {
                                    array_pop($path);
                            } else {
                                    $path[] = $segment;
                            }
                    }
                    // Step 6d, 6f: remove "." and ".." from file portion
                    if ($end == '.') {
                            $path[] = '';
                    } else if ($end == '..' && $path && $path[sizeof($path)-1] != '..') {
                            $path[sizeof($path)-1] = '';
                    } else {
                            $path[] = $end;
                    }
                    // Step 6h
                    $base['path'] = join('/', $path);

            }
            // Step 7
            return $this->unparse_url($base);
    }

    function unparse_url($parsed){
        if (! is_array($parsed)) return false;
        $uri = isset($parsed['scheme']) ? $parsed['scheme'].':'.((wpml_mb_strtolower($parsed['scheme']) == 'mailto') ? '':'//'): '';
        $uri .= isset($parsed['user']) ? $parsed['user'].($parsed['pass']? ':'.$parsed['pass']:'').'@':'';
        $uri .= isset($parsed['host']) ? $parsed['host'] : '';
        $uri .= isset($parsed['port']) ? ':'.$parsed['port'] : '';
        if(isset($parsed['path']))
            {
            $uri .= (substr($parsed['path'],0,1) == '/')?$parsed['path']:'/'.$parsed['path'];
            }
        $uri .= isset($parsed['query']) ? '?'.$parsed['query'] : '';
        $uri .= isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';
        return $uri;
    }

    function _content_get_link_paths($body) {
      
        $regexp_links = array(
                            /*"/<a.*?href\s*=\s*([\"\']??)([^\"]*)[\"\']>(.*?)<\/a>/i",*/
                            "/<a[^>]*href\s*=\s*([\"\']??)([^\"^>]+)[\"\']??([^>]*)>/i",
                            );
        
        $links = array();
        
        foreach($regexp_links as $regexp) {
            if (preg_match_all($regexp, $body, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                  $links[] = $match;
                }
            }
        }
        return $links;
    }    
    
    public static function _content_make_links_sticky($element_id, $element_type='post', $string_translation = true) {        
        if(strpos($element_type, 'post') === 0){
            // only need to do it if sticky links is not enabled.
            // create the object
            require_once ICL_PLUGIN_PATH . '/inc/absolute-links/absolute-links.class.php';        
            $icl_abs_links = new AbsoluteLinks;
            $icl_abs_links->process_post($element_id);
        }elseif($element_type=='string'){             
            require_once ICL_PLUGIN_PATH . '/inc/absolute-links/absolute-links.class.php';        
            $icl_abs_links = new AbsoluteLinks; // call just for strings
            $icl_abs_links->process_string($element_id, $string_translation);                                        
        }
    }

    function _content_fix_links_to_translated_content($element_id, $target_lang_code, $element_type='post'){
        global $wpdb, $sitepress, $wp_taxonomies;
        self::_content_make_links_sticky($element_id, $element_type);

		$post = false;
		$body = false;
        if(strpos($element_type, 'post') === 0){
            $post_prepared = $wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d", array($element_id));
            $post = $wpdb->get_row($post_prepared);
            $body = $post->post_content;
        }elseif($element_type=='string'){
            $body_prepared = $wpdb->prepare("SELECT value FROM {$wpdb->prefix}icl_string_translations WHERE id=%d", array($element_id));
            $body = $wpdb->get_var($body_prepared);
        }
        $new_body = $body;

        $base_url_parts = parse_url(site_url());
        
        $links = $this->_content_get_link_paths($body);
        
        $all_links_fixed = 1;

        $pass_on_query_vars = array();
        $pass_on_fragments = array();

		$all_links_arr = array();

        foreach($links as $link_idx => $link) {
            $path = $link[2];
            $url_parts = parse_url($path);
            
            if(isset($url_parts['fragment'])){
                $pass_on_fragments[$link_idx] = $url_parts['fragment'];
            }
            
            if((!isset($url_parts['host']) or $base_url_parts['host'] == $url_parts['host']) and
                    (!isset($url_parts['scheme']) or $base_url_parts['scheme'] == $url_parts['scheme']) and
                    isset($url_parts['query'])) {
                $query_parts = explode('&', $url_parts['query']);
                
                foreach($query_parts as $query){
                    // find p=id or cat=id or tag=id queries
                    list($key, $value) = explode('=', $query);
                    $translations = NULL;
                    $is_tax = false;
					$kind = false;
					$taxonomy = false;
                    if($key == 'p'){
                        $kind = 'post_' . $wpdb->get_var( $wpdb->prepare("SELECT post_type
																		  FROM {$wpdb->posts}
																		  WHERE ID = %d ",
                                                                         $value));
                    } else if($key == "page_id"){
                        $kind = 'post_page';
                    } else if($key == 'cat' || $key == 'cat_ID'){
                        $kind = 'tax_category';
                        $taxonomy = 'category';
                    } else if($key == 'tag'){
                        $is_tax = true;
                        $taxonomy = 'post_tag';
                        $kind = 'tax_' . $taxonomy;                    
                        $value = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id
																FROM {$wpdb->terms} t
                                                                JOIN {$wpdb->term_taxonomy} x
                                                                  ON t.term_id = x.term_id
                                                                WHERE x.taxonomy = %s
                                                                  AND t.slug = %s", $taxonomy, $value ) );
                    } else {
                        $found = false;
                        foreach($wp_taxonomies as $taxonomy_name => $taxonomy_object){
                            if($taxonomy_object->query_var && $key == $taxonomy_object->query_var){
                                $found = true;
                                $is_tax = true;
                                $kind = 'tax_' . $taxonomy_name;
                                $value = $wpdb->get_var($wpdb->prepare("
                                    SELECT term_taxonomy_id
                                    FROM {$wpdb->terms} t
                                    JOIN {$wpdb->term_taxonomy} x
                                      ON t.term_id = x.term_id
                                    WHERE x.taxonomy = %s
                                      AND t.slug = %s",
                                    $taxonomy_name, $value ));
                                $taxonomy = $taxonomy_name;
                            }                        
                        }
                        if(!$found){
                            $pass_on_query_vars[$link_idx][] = $query;
                            continue;
                        } 
                    }

                    $link_id = (int)$value;  
                    
                    if (!$link_id) {
                        continue;
                    }

                    $trid = $sitepress->get_element_trid($link_id, $kind);
                    if(!$trid){
                        continue;
                    }
                    if($trid !== NULL){
                        $translations = $sitepress->get_element_translations($trid, $kind);
                    }
                    if(isset($translations[$target_lang_code]) && $translations[$target_lang_code]->element_id != null){
                        
                        // use the new translated id in the link path.
                        
                        $translated_id = $translations[$target_lang_code]->element_id;
                        
                        if($is_tax){
                            $translated_id = $wpdb->get_var($wpdb->prepare("SELECT slug
																			FROM {$wpdb->terms} t
																			JOIN {$wpdb->term_taxonomy} x
																				ON t.term_id=x.term_id
																			WHERE x.term_taxonomy_id = %d",
                                                                           $translated_id));
                        }
                        
                        // if absolute links is not on turn into WP permalinks                                                
                        if(empty($GLOBALS['WPML_Sticky_Links'])){
                            ////////
							$replace = false;
                            if(preg_match('#^post_#', $kind)){
                                $replace = get_permalink($translated_id);
                            }elseif(preg_match('#^tax_#', $kind)){
                                if(is_numeric($translated_id)) $translated_id = intval($translated_id);
                                $replace = get_term_link($translated_id, $taxonomy);                                
                            }
                            $new_link = str_replace($link[2], $replace, $link[0]);
                            
                            $replace_link_arr[$link_idx] = array('from'=> $link[2], 'to'=>$replace);
                        }else{
                            $replace = $key . '=' . $translated_id;
							$new_link = $link[0];
							if($replace) {
                            	$new_link = str_replace($query, $replace, $link[0]);
							}
                            
                            $replace_link_arr[$link_idx] = array('from'=> $query, 'to'=>$replace);
                        }
                        
                        // replace the link in the body.                        
                        // $new_body = str_replace($link[0], $new_link, $new_body);
                        $all_links_arr[$link_idx] = array('from'=> $link[0], 'to'=>$new_link);
                        // done in the next loop
                        
                    } else {
                        // translation not found for this.
                        $all_links_fixed = 0;
                    }
                }
            }
                        
        }

		if ( !empty( $replace_link_arr ) ) {
			foreach ( $replace_link_arr as $link_idx => $rep ) {
				$rep_to   = $rep[ 'to' ];
				$fragment = '';

				// if sticky links is not ON, fix query parameters and fragments
				if ( empty( $GLOBALS[ 'WPML_Sticky_Links' ] ) ) {
					if ( !empty( $pass_on_fragments[ $link_idx ] ) ) {
						$fragment = '#' . $pass_on_fragments[ $link_idx ];
					}
					if ( !empty( $pass_on_query_vars[ $link_idx ] ) ) {
						$url_glue = ( strpos( $rep[ 'to' ], '?' ) === false ) ? '?' : '&';
						$rep_to   = $rep[ 'to' ] . $url_glue . join( '&', $pass_on_query_vars[ $link_idx ] );
					}
				}

				$all_links_arr[ $link_idx ][ 'to' ] = str_replace( $rep[ 'to' ], $rep_to . $fragment, $all_links_arr[ $link_idx ][ 'to' ] );

			}
		}
        
        if(!empty($all_links_arr))
        foreach($all_links_arr as $link){
            $new_body = str_replace($link['from'], $link['to'], $new_body);
        }
        
        if ($new_body != $body){
            
            // save changes to the database.
            if(strpos($element_type, 'post') === 0){        
                $wpdb->update($wpdb->posts, array('post_content'=>$new_body), array('ID'=>$element_id));
                
                // save the all links fixed status to the database.
                $icl_element_type = 'post_' . $post->post_type;
                $translation_id = $wpdb->get_var($wpdb->prepare("SELECT translation_id
																 FROM {$wpdb->prefix}icl_translations
																 WHERE element_id=%d
																  AND element_type=%s",
                                                                $element_id,
                                                                $icl_element_type));
	            $q          = "UPDATE {$wpdb->prefix}icl_translation_status SET links_fixed=%s WHERE translation_id=%d";
	            $q_prepared = $wpdb->prepare( $q, array( $all_links_fixed, $translation_id ) );
                $wpdb->query($q_prepared);
                
            }elseif($element_type == 'string'){
                $wpdb->update($wpdb->prefix.'icl_string_translations', array('value'=>$new_body), array('id'=>$element_id));
            }
                    
        }
        
    }

	function throw_exception_for_mysql_errors() {
		global $EZSQL_ERROR, $sitepress_settings;
		if ( isset( $sitepress_settings[ 'troubleshooting_options' ][ 'raise_mysql_errors' ] ) && $sitepress_settings[ 'troubleshooting_options' ][ 'raise_mysql_errors' ] ) {
			if ( !empty( $EZSQL_ERROR ) ) {
				$mysql_errors = array();
				foreach ( $EZSQL_ERROR as $v ) {
					$mysql_errors[ ] = $v[ 'error_str' ] . ' [' . $v[ 'query' ] . ']';
				}
				throw new Exception( join( "\n", $mysql_errors ) );
			}
		}
	}

	function translation_error_handler($error_number, $error_string, $error_file, $error_line){
        switch($error_number){
            case E_ERROR:
            case E_USER_ERROR:
                throw new Exception ($error_string . ' [code:e' . $error_number . '] in '. $error_file . ':' . $error_line);
            case E_WARNING:
            case E_USER_WARNING:
                return true;                
            default:
                return true;
        }
        
    }    
    
    function post_submitbox_start(){
        global $post, $iclTranslationManagement;
        if(empty($post)|| !$post->ID){
            return;
        }
        
        $translations = $iclTranslationManagement->get_element_translations($post->ID, 'post_' . $post->post_type);
        $show_box = 'display:none';
        foreach($translations as $t){
            if($t->element_id == $post->ID){
				return;
            } 
            if($t->status == ICL_TM_COMPLETE && !$t->needs_update){
                $show_box = '';
                break;
            }
        }
        
        echo '<p id="icl_minor_change_box" style="float:left;padding:0;margin:3px;'.$show_box.'">';
        echo '<label><input type="checkbox" name="icl_minor_edit" value="1" style="min-width:15px;" />&nbsp;';
        echo __('Minor edit - don\'t update translation','sitepress');        
        echo '</label>';
        echo '<br clear="all" />';
        echo '</p>';
    }

    function get_total_jobs_in_progress(){
        return $this->get_jobs_in_progress() + $this->get_strings_in_progress();
    }

	function get_jobs_in_progress() {
		global $wpdb;
		$jobs_in_progress_sql      = "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translation_status WHERE status=%d AND translation_service=%s";
		$jobs_in_progress_prepared = $wpdb->prepare( $jobs_in_progress_sql, array(ICL_TM_IN_PROGRESS, TranslationProxy::get_current_service_id()) );
		$jobs_in_progress          = $wpdb->get_var( $jobs_in_progress_prepared );

		return $jobs_in_progress;
	}

	function get_strings_in_progress() {
		global $wpdb;
		$strings_in_progress_snipped = wpml_prepare_in( array( ICL_TM_IN_PROGRESS, ICL_TM_WAITING_FOR_TRANSLATOR ),
		                                                '%d' );
		$strings_in_progress_sql = "	SELECT COUNT(*)
											FROM {$wpdb->prefix}icl_string_translations
											WHERE status IN ({$strings_in_progress_snipped})
												AND translation_service = %d";
		$strings_in_progress_prepared = $wpdb->prepare( $strings_in_progress_sql,
		                                                TranslationProxy::get_current_service_id() );
		$strings_in_progress = $wpdb->get_var( $strings_in_progress_prepared );

		return $strings_in_progress;
	}

	/**
	 * @param bool|false $force
	 *
	 * @return array|int
	 */
	function poll_for_translations( $force = false ) {
		/** @var WPML_String_Translation $WPML_String_Translation */
		global $sitepress;

		if ( ! $force ) {
			// Limit to once per hour
			$translation_offset = strtotime( current_time( 'mysql' ) ) - @intval( $sitepress->get_setting( 'last_picked_up' ) ) - 3600;
			if ( $translation_offset < 0 || $force ) {
				return 0;
			}
		}
		$project = TranslationProxy::get_current_project();

		return (bool) $project === true ? $this->download_helper->poll_for_translations( $project ) : array();
	}


	function process_translated_string( $translation_proxy_job_id, $language ) {

		$project     = TranslationProxy::get_current_project( );
		$translation = $project->fetch_translation( $translation_proxy_job_id );
		$translation = apply_filters( 'icl_data_from_pro_translation', $translation );

		$ret = false;

		$xliff = new WPML_TM_xliff();
		$translation = $xliff->get_strings_xliff_translation( $translation );

		if ( $translation ) {
			$ret = icl_translation_add_string_translation( $translation_proxy_job_id, $translation, $language );
			if ( $ret ) {
				$project->update_job( $translation_proxy_job_id );
			}
		}

		return $ret;
	}

	private function add_error( $project_error ) {
		$this->errors[] = $project_error;
	}

	/**
	 * @param $project TranslationProxy_Project
	 */
	function enqueue_project_errors( $project ) {
		if ( isset( $project ) && isset( $project->errors ) && $project->errors ) {
			foreach ( $project->errors as $project_error ) {
				$this->add_error( $project_error );
			}
		}
	}

	/**
	 * @param TranslationManagement $iclTranslationManagement
	 */
	private function maybe_init_translation_management( $iclTranslationManagement ) {
		if ( empty( $this->tmg->settings ) ) {
			$iclTranslationManagement->init();
		}
	}
}
