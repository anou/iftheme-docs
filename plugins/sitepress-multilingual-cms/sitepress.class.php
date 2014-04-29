<?php
/**
 * Main SitePress Class
 *
 * @package wpml-core
 */
class SitePress
{

	private $settings;
	private $active_languages = array();
	private $this_lang;
	private $wp_query;
	private $admin_language = null;
	private $user_preferences = array();
	private $current_user;

	public $queries = array();

	/**
	 * @var icl_cache
	 */
	public $icl_translations_cache;
	/**
	 * @var icl_cache
	 */
	public $icl_locale_cache;
	/**
	 * @var icl_cache
	 */
	public $icl_flag_cache;
	/**
	 * @var icl_cache
	 */
	public $icl_language_name_cache;
	/**
	 * @var icl_cache
	 */
	public $icl_term_taxonomy_cache;

	function __construct()
	{
		global $wpdb, $pagenow;

		$this->settings = get_option( 'icl_sitepress_settings' );

		//TODO: To remove in WPML 3.5
		//@since 3.1
		if(is_admin() && !$this->get_setting('icl_capabilities_verified')) {
			icl_enable_capabilities();
			$this->settings = get_option( 'icl_sitepress_settings' );
		}

		// set up current user early
		// no authentication
		if ( defined( 'LOGGED_IN_COOKIE' ) && isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			list( $username ) = explode( '|', $_COOKIE[ LOGGED_IN_COOKIE ] );
			$user_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login = %s", array($username) ) );
		} else {
			$user_id = 0;
		}

		$this->current_user = new WP_User( $user_id );

		if ( is_null( $pagenow ) && is_multisite() ) {
			include ICL_PLUGIN_PATH . '/inc/hacks/vars-php-multisite.php';
		}

		if ( false != $this->settings ) {
			$this->verify_settings();
		}
		if ( isset( $_GET[ 'icl_action' ] ) ) {
			require_once ABSPATH . WPINC . '/pluggable.php';
			if ( $_GET[ 'icl_action' ] == 'reminder_popup' ) {
				add_action( 'init', array( $this, 'reminders_popup' ) );
			} elseif ( $_GET[ 'icl_action' ] == 'dismiss_help' ) {
				$this->settings[ 'dont_show_help_admin_notice' ] = true;
				$this->save_settings();
			}
		}

		if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == ICL_PLUGIN_FOLDER . '/menu/troubleshooting.php' && isset( $_GET[ 'debug_action' ] ) ) {
			ob_start();
		}

		if ( isset( $_REQUEST[ 'icl_ajx_action' ] ) ) {
			add_action( 'init', array( $this, 'ajax_setup' ), 15 );
		}
		add_action( 'admin_footer', array( $this, 'icl_nonces' ) );

		// Process post requests
		if ( !empty( $_POST ) ) {
			add_action( 'init', array( $this, 'process_forms' ) );
		}

		add_action( 'plugins_loaded', array( $this, 'initialize_cache' ), 0 );
		add_action( 'plugins_loaded', array( $this, 'init' ), 1 );
		add_action( 'init', array( $this, 'on_wp_init' ), 1 );

		add_action( 'admin_print_scripts', array( $this, 'js_scripts_setup' ) );
		add_action( 'admin_print_styles', array( $this, 'css_setup' ) );

		// Administration menus
		add_action( 'admin_menu', array( $this, 'administration_menu' ) );
		add_action( 'admin_menu', array( $this, 'administration_menu2' ), 30 );

		add_action( 'init', array( $this, 'plugin_localization' ) );

		//add_filter('tag_template', array($this, 'load_taxonomy_template'));

		if ( $this->settings[ 'existing_content_language_verified' ] ) {

			// Post/page language box
			if ( $pagenow == 'post.php' || $pagenow == 'post-new.php' || $pagenow == 'edit.php' ) {
				add_action( 'admin_head', array( $this, 'post_edit_language_options' ) );
			}
			//For when it will be possible to add custom bulk actions
			//add_action( 'bulk_actions-edit-post', array( $this, 'bulk_actions' ) );

			// Post/page save actions
			add_action( 'save_post', array( $this, 'save_post_actions' ), 10, 2 );

			//post/page delete taxonomy
			add_action( 'deleted_term_relationships', array( $this, 'deleted_term_relationships' ), 10, 2 );

			add_action( 'updated_post_meta', array( $this, 'update_post_meta' ), 100, 4 );
			add_action( 'added_post_meta', array( $this, 'update_post_meta' ), 100, 4 );
			add_action( 'updated_postmeta', array( $this, 'update_post_meta' ), 100, 4 ); // ajax
			add_action( 'added_postmeta', array( $this, 'update_post_meta' ), 100, 4 ); // ajax
			add_action( 'delete_postmeta', array( $this, 'delete_post_meta' ) ); // ajax

			// filter user taxonomy input
			add_filter( 'pre_post_tax_input', array( $this, 'validate_taxonomy_input' ) );

			// Post/page delete actions
			//            add_action('delete_post', array($this,'delete_post_actions'));
			add_action( 'before_delete_post', array( $this, 'before_delete_post_actions' ) );
			add_action( 'deleted_post', array( $this, 'deleted_post_actions' ) );
			add_action( 'wp_trash_post', array( $this, 'trash_post_actions' ) );
			add_action( 'untrashed_post', array( $this, 'untrashed_post_actions' ) );

			add_filter( 'posts_join', array( $this, 'posts_join_filter' ), 10, 2 );
			add_filter( 'posts_where', array( $this, 'posts_where_filter' ), 10, 2 );
			add_filter( 'comment_feed_join', array( $this, 'comment_feed_join' ) );

			add_filter( 'comments_clauses', array( $this, 'comments_clauses' ), 10, 2 );

			// Allow us to filter the Query vars before the posts query is being built and executed
			add_filter( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

			add_action( 'loop_start', array( $this, 'loop_start' ), 10 );
			add_filter( 'the_posts', array( $this, 'the_posts' ), 10 );
			add_filter( 'get_pages', array( $this, 'get_pages' ), 100, 2 );

			if ( $pagenow == 'edit.php' ) {
				add_action( 'admin_footer', array( $this, 'language_filter' ) );
			}

			add_filter( 'get_pages', array( $this, 'exclude_other_language_pages2' ) );
			add_filter( 'wp_dropdown_pages', array( $this, 'wp_dropdown_pages' ) );

			// posts and pages links filters
			add_filter( 'post_link', array( $this, 'permalink_filter' ), 1, 2 );
			add_filter( 'post_type_link', array( $this, 'permalink_filter' ), 1, 2 );
			add_filter( 'page_link', array( $this, 'permalink_filter' ), 1, 2 );

			add_filter( 'get_comment_link', array( $this, 'get_comment_link_filter' ) );

			//Taxonomies
			if ( version_compare( preg_replace( '#-RC[0-9]+(-[0-9]+)?$#', '', $GLOBALS[ 'wp_version' ] ), '3.1', '<' ) ) {
				add_filter( 'category_link', array( $this, 'category_permalink_filter' ), 1, 2 );
				add_filter( 'tag_link', array( $this, 'tax_permalink_filter' ), 1, 2 );
			}

			add_filter( 'term_link', array( $this, 'tax_permalink_filter' ), 1, 2 );

			add_action( 'create_term', array( $this, 'create_term' ), 1, 2 );
			add_action( 'edit_term', array( $this, 'create_term' ), 1, 2 );
			add_action( 'delete_term', array( $this, 'delete_term' ), 1, 3 );

			add_action( 'get_term', array( $this, 'get_term_filter' ), 1, 2 );
			add_filter( 'get_terms_args', array( $this, 'get_terms_args_filter' ) );

			// filters terms by language
			add_filter( 'terms_clauses', array( $this, 'terms_clauses' ), 10, 4 );
			add_filter( 'list_terms_exclusions', array( $this, 'exclude_other_terms' ), 1, 2 );

			// allow adding terms with the same name in different languages
			add_filter( "pre_term_name", array( $this, 'pre_term_name' ), 1, 2 );
			// allow adding categories with the same name in different languages
			add_action( 'admin_init', array( $this, 'pre_save_category' ) );

			//Hooking to 'option_{taxonomy}_children' to translate taxonomy children
//			global $wp_taxonomies;
//			foreach ( $wp_taxonomies as $tax_key => $tax ) {
//				if ( $this->is_translated_taxonomy( $tax_key ) && is_taxonomy_hierarchical($tax_key) ) {
//					add_filter("option_{$tax_key}_children", array($this, 'option_taxonomy_children'), 10 );
//					add_filter("pre_update_option_{$tax_key}_children", array($this, 'pre_update_option_taxonomy_children'), 10, 2 );
//				}
//			}

			add_filter( 'get_edit_term_link', array( $this, 'get_edit_term_link' ), 1, 4 );

			// short circuit get default category
			add_filter( 'pre_option_default_category', array( $this, 'pre_option_default_category' ) );
			add_filter( 'update_option_default_category', array( $this, 'update_option_default_category' ), 1, 2 );

			add_filter( 'the_category', array( $this, 'the_category_name_filter' ) );
			add_filter( 'get_terms', array( $this, 'get_terms_filter' ) );
			add_filter( 'get_the_terms', array( $this, 'get_the_terms_filter' ), 10, 3 );

			add_filter( 'single_cat_title', array( $this, 'the_category_name_filter' ) );
			add_filter( 'term_links-category', array( $this, 'the_category_name_filter' ) );

			add_filter( 'term_links-post_tag', array( $this, 'the_category_name_filter' ) );
			add_filter( 'tags_to_edit', array( $this, 'the_category_name_filter' ) );
			add_filter( 'single_tag_title', array( $this, 'the_category_name_filter' ) );


			// custom hook for adding the language selector to the template
			add_action( 'icl_language_selector', array( $this, 'language_selector' ) );

			// front end js
			add_action( 'wp_head', array( $this, 'front_end_js' ) );

			add_action( 'wp_head', array( $this, 'rtl_fix' ) );
			add_action( 'admin_print_styles', array( $this, 'rtl_fix' ) );

			add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );

			add_filter( 'get_edit_post_link', array( $this, 'get_edit_post_link' ), 1, 3 );

			// adjacent posts links
			add_filter( 'get_previous_post_join', array( $this, 'get_adjacent_post_join' ) );
			add_filter( 'get_next_post_join', array( $this, 'get_adjacent_post_join' ) );
			add_filter( 'get_previous_post_where', array( $this, 'get_adjacent_post_where' ) );
			add_filter( 'get_next_post_where', array( $this, 'get_adjacent_post_where' ) );

			// feeds links
			add_filter( 'feed_link', array( $this, 'feed_link' ) );

			// commenting links
			add_filter( 'post_comments_feed_link', array( $this, 'post_comments_feed_link' ) );
			add_filter( 'trackback_url', array( $this, 'trackback_url' ) );
			add_filter( 'user_trailingslashit', array( $this, 'user_trailingslashit' ), 1, 2 );

			// date based archives
			add_filter( 'year_link', array( $this, 'archives_link' ) );
			add_filter( 'month_link', array( $this, 'archives_link' ) );
			add_filter( 'day_link', array( $this, 'archives_link' ) );
			add_filter( 'getarchives_join', array( $this, 'getarchives_join' ) );
			add_filter( 'getarchives_where', array( $this, 'getarchives_where' ) );
			add_filter( 'pre_option_home', array( $this, 'pre_option_home' ) );

			if ( !is_admin() ) {
				add_filter( 'attachment_link', array( $this, 'attachment_link_filter' ), 10, 2 );
			}

			// Filter custom type archive link (since WP 3.1)
			add_filter( 'post_type_archive_link', array( $this, 'post_type_archive_link_filter' ), 10, 2 );

			add_filter( 'author_link', array( $this, 'author_link' ) );

			add_filter( 'wp_unique_post_slug', array( $this, 'wp_unique_post_slug' ), 100, 5 );

			add_filter( 'home_url', array( $this, 'home_url' ), 1, 4 );

			// language negotiation
			add_action( 'query_vars', array( $this, 'query_vars' ) );

			add_filter( 'language_attributes', array( $this, 'language_attributes' ) );

			add_action( 'locale', array( $this, 'locale' ) );

			if ( isset( $_GET[ '____icl_validate_domain' ] ) ) {
				echo '<!--' . get_home_url() . '-->';
				exit;
			}

			add_filter( 'pre_option_page_on_front', array( $this, 'pre_option_page_on_front' ) );
			add_filter( 'pre_option_page_for_posts', array( $this, 'pre_option_page_for_posts' ) );

			add_filter( 'option_sticky_posts', array( $this, 'option_sticky_posts' ) );

			add_filter( 'request', array( $this, 'request_filter' ) );

			add_action( 'wp_head', array( $this, 'set_wp_query' ) );

			add_action( 'show_user_profile', array( $this, 'show_user_options' ) );
			add_action( 'personal_options_update', array( $this, 'save_user_options' ) );

			// column with links to translations (or add translation) - low priority
			add_action( 'init', array( $this, 'configure_custom_column' ), 1010 ); // accommodate Types init@999

			// adjust queried categories and tags ids according to the language
			if ( $this->settings[ 'auto_adjust_ids' ] ) {
				add_action( 'parse_query', array( $this, 'parse_query' ) );
				add_action( 'wp_list_pages_excludes', array( $this, 'adjust_wp_list_pages_excludes' ) );
				if ( !is_admin() ) {
					add_filter( 'get_term', array( $this, 'get_term_adjust_id' ), 1, 1 );
					add_filter( 'category_link', array( $this, 'category_link_adjust_id' ), 1, 2 );
					add_filter( 'get_terms', array( $this, 'get_terms_adjust_ids' ), 1, 3 );
					add_filter( 'get_pages', array( $this, 'get_pages_adjust_ids' ), 1, 2 );
				}
			}

			if ( !is_admin() ) {
				add_action( 'wp_head', array( $this, 'meta_generator_tag' ) );
			}

			require_once ICL_PLUGIN_PATH . '/inc/wp-nav-menus/iclNavMenu.class.php';
			new iclNavMenu;

			if ( is_admin() || defined( 'XMLRPC_REQUEST' ) || preg_match( '#wp-comments-post\.php$#', $_SERVER[ 'REQUEST_URI' ] ) ) {
				global $iclTranslationManagement, $ICL_Pro_Translation;
				$iclTranslationManagement = new TranslationManagement;
				$ICL_Pro_Translation      = new ICL_Pro_Translation();
			}

			add_action( 'wp_login', array( $this, 'reset_admin_language_cookie' ) );

			if ( $this->settings[ 'seo' ][ 'canonicalization_duplicates' ] ) {
				add_action( 'template_redirect', array( $this, 'setup_canonical_urls' ), 100 );
			}
			add_filter( 'taxonomy_template', array($this, 'slug_template') );
			add_filter( 'category_template', array($this, 'slug_template') );

			add_action( 'init', array( $this, '_taxonomy_languages_menu' ), 99 ); //allow hooking in

			if ( $this->settings[ 'seo' ][ 'head_langs' ] ) {
				add_action( 'wp_head', array( $this, 'head_langs' ) );
			}


		} //end if the initial language is set - existing_content_language_verified

		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget_setup' ) );
		if ( is_admin() && $pagenow == 'index.php' ) {
			add_action( 'icl_dashboard_widget_notices', array( $this, 'print_translatable_custom_content_status' ) );
		}


		add_filter( 'core_version_check_locale', array( $this, 'wp_upgrade_locale' ) );

		if ( $pagenow == 'post.php' && isset( $_REQUEST[ 'action' ] ) && $_REQUEST[ 'action' ] == 'edit' && isset( $_GET[ 'post' ] ) ) {
			add_action( 'init', '_icl_trash_restore_prompt' );
		}

		add_action( 'init', array( $this, 'js_load' ), 2 ); // enqueue scripts - higher priority

		add_filter( 'get_pagenum_link', array( $this, 'get_pagenum_link' ) );

	}

	function get_pagenum_link( $url ) {

		// fix cases like that in url:
		// lang=pl%2F%3Flang%3Dpl
		// lang=pl/?lang=pl
		$current_language = $this->get_current_language();
		$find[ ] = 'lang=' . $current_language . '%2F%3Flang%3D' . $current_language;
		$find[ ] = 'lang=' . $current_language . '/?lang=' . $current_language;
		$replace = 'lang=' . $current_language;

		$url = str_replace( $find, $replace, $url );

		// fix cases like that:
		// ?lang=pl/page/3/?lang=pl

		$pattern = '/(\?lang=' . $current_language . ')(\/page\/\d+)/';

		$url = preg_replace( $pattern, '$2', $url );

		return $url;
	}
        
	function init()
	{
		global $wpdb;

		$this->get_user_preferences();
		$this->set_admin_language();

		$default_language = $this->get_default_language();

		// default value for theme_localization_type OR
		// reset theme_localization_type if string translation was on (theme_localization_type was set to 2) and then it was deactivated
		if ( !isset( $this->settings[ 'theme_localization_type' ] ) || ( $this->settings[ 'theme_localization_type' ] == 1 && !defined( 'WPML_ST_VERSION' ) && !defined( 'WPML_DOING_UPGRADE' ) ) ) {
			global $sitepress_settings;
			$this->settings[ 'theme_localization_type' ] = $sitepress_settings[ 'theme_localization_type' ] = 2;
		}

		//configure callbacks for plugin menu pages
		if ( defined( 'WP_ADMIN' ) && isset( $_GET[ 'page' ] ) && 0 === strpos( $_GET[ 'page' ], basename( ICL_PLUGIN_PATH ) . '/' ) ) {
			add_action( 'icl_menu_footer', array( $this, 'menu_footer' ) );
		}

		//Run only if existing content language has been verified, and is front-end or settings are not corrupted
		if (!empty( $this->settings[ 'existing_content_language_verified' ] ) && (!is_admin() || SitePress::check_settings_integrity()) ) {

			if ( $this->settings[ 'language_negotiation_type' ] == 1 && $this->settings[ 'urls' ][ 'directory_for_default_language' ] && $this->settings[ 'urls' ][ 'show_on_root' ] == 'page' ) {
				include ICL_PLUGIN_PATH . '/inc/home-url-functions.php';
			}

			if ( defined( 'WP_ADMIN' ) ) {

				if ( $this->settings[ 'language_negotiation_type' ] == 2 ) {
					//Login and Logout
					add_filter( 'login_url', array( $this, 'convert_url' ) );
					add_filter( 'logout_url', array( $this, 'convert_url' ) );
					add_filter( 'site_url', array( $this, 'convert_url' ) );
				}

				if ( isset( $_GET[ 'lang' ] ) ) {
					$this->this_lang = rtrim( strip_tags( $_GET[ 'lang' ] ), '/' );
					$al              = $this->get_active_languages();
					$al[ 'all' ]     = true;
					if ( empty( $al[ $this->this_lang ] ) ) {
						$this->this_lang = $default_language;
					}
					// force default language for string translation
					// we also make sure it's not saved in the cookie
				} elseif ( isset( $_GET[ 'page' ] ) && ( ( defined( 'WPML_ST_FOLDER' ) && $_GET[ 'page' ] == WPML_ST_FOLDER . '/menu/string-translation.php' ) || ( defined( 'WPML_TM_FOLDER' ) && $_GET[ 'page' ] == WPML_TM_FOLDER . '/menu/translations-queue.php' ) )
				) {
					$this->this_lang = $default_language;
				} elseif ( wpml_is_ajax() ) {
					$al = $this->get_active_languages();
					if ( isset( $_POST[ 'lang' ] ) && isset( $al[ $_POST[ 'lang' ] ] ) ) {
						$this->this_lang = $_POST[ 'lang' ];
					} else {
						$this->this_lang = $this->get_language_cookie();
					}
				} elseif ( $lang = $this->get_admin_language_cookie() ) {
					$this->this_lang = $lang;
				} elseif ( isset($_POST['action']) && $_POST['action'] == 'editpost' &&  isset($_POST['icl_post_language'])) {
					$this->this_lang = $_POST['icl_post_language'];
				}	elseif ( isset($_GET['post']) && is_numeric($_GET['post'])) {
					$post_type = get_post_type($_GET['post']);
					$this->this_lang = $this->get_language_for_element($_GET['post'], 'post_' . $post_type);
				} else {
					$this->this_lang = $default_language;
				}
				if ( ( isset( $_GET[ 'admin_bar' ] ) && $_GET[ 'admin_bar' ] == 1 ) && ( !isset( $_GET[ 'page' ] ) || !defined( 'WPML_ST_FOLDER' ) || $_GET[ 'page' ] != WPML_ST_FOLDER . '/menu/string-translation.php' ) ) {
					$this->set_admin_language_cookie();
				}
			} else {
				$al = $this->get_active_languages();
				foreach ( $al as $l ) {
					$active_languages[ ] = $l[ 'code' ];
				}
				$active_languages[ ] = 'all';

				$s                   = isset( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] == 'on' ? 's' : '';

				$home                = get_home_url();
				if ( $s ) {
					$home = preg_replace( '#^http://#', 'https://', $home );
				}
				$url_parts = parse_url( $home );

				$non_default_port = (isset($url_parts['port']) && $url_parts['port']!=80) ? ':' . $url_parts['port'] :'';

				$request             = 'http' . $s . '://' . $this->get_server_host_name() . $_SERVER[ 'REQUEST_URI' ];
				$blog_path = !empty( $url_parts[ 'path' ] ) ? $url_parts[ 'path' ] : '';
				
				switch ( $this->settings[ 'language_negotiation_type' ] ) {
					case 1:
                    
						$path = str_replace( $home, '', $request );
						$parts = explode( '?', $path );
						$path  = $parts[ 0 ];
						$exp   = explode( '/', trim( $path, '/' ) );
						$language_part =  $exp[ 0 ];

						if ( in_array( $language_part, $active_languages ) ) {
							$this->this_lang = $exp[ 0 ];

							// before hijacking the SERVER[REQUEST_URI]
							// override the canonical_redirect action
							// keep a copy of the original request uri
							remove_action( 'template_redirect', 'redirect_canonical' );
							global $_icl_server_request_uri;
							$_icl_server_request_uri = $_SERVER[ 'REQUEST_URI' ];
							add_action( 'template_redirect', array($this,'icl_redirect_canonical_wrapper'), 0 );

							//deal with situations when template files need to be called directly
							add_action( 'template_redirect', array( $this, '_allow_calling_template_file_directly' ) );

							//$_SERVER['REQUEST_URI'] = preg_replace('@^'. $blog_path . '/' . $this->this_lang.'@i', $blog_path ,$_SERVER['REQUEST_URI']);

							// Check for special case of www.example.com/fr where the / is missing on the end
							$parts = parse_url( $_SERVER[ 'REQUEST_URI' ] );
							if ( strlen( $parts[ 'path' ] ) == 0 ) {
								$_SERVER[ 'REQUEST_URI' ] = '/' . $_SERVER[ 'REQUEST_URI' ];
							}
						} else {
							$this->this_lang = $default_language;
						}

						if ( !trim( $path, '/' ) && $this->settings[ 'urls' ][ 'directory_for_default_language' ] ) {
							if ( $this->settings[ 'urls' ][ 'show_on_root' ] == 'html_file' ) {
								// html file
								if ( false === strpos( $this->settings[ 'urls' ][ 'root_html_file_path' ], '/' ) ) {
									$html_file = ABSPATH . $this->settings[ 'urls' ][ 'root_html_file_path' ];
								} else {
									$html_file = $this->settings[ 'urls' ][ 'root_html_file_path' ];
								}

								include $html_file;
								exit;

							} else {
								//page
								if ( !trim( $path, '/' ) ) {
									wpml_home_url_setup_root_page();
								}

							}
						}

						break;
					case 2:

						$this->this_lang = $default_language;
						foreach( $this->settings[ 'language_domains' ] as $language_code => $domain ){
								if( rtrim( $this->get_server_host_name() . $blog_path, '/' ) == rtrim( preg_replace( '@^https?://@', '', $domain ), '/' ) ){
										$this->this_lang = $language_code;
										break;
								}
						}
                    
						if ( defined( 'ICL_USE_MULTIPLE_DOMAIN_LOGIN' ) && ICL_USE_MULTIPLE_DOMAIN_LOGIN ) {
							include ICL_PLUGIN_PATH . '/modules/multiple-domains-login.php';
						}
						add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );
						add_filter( 'site_url', array( $this, 'convert_url' ) );
						break;
					case 3:
					default:
						if ( isset( $_GET[ 'lang' ] ) ) {
							$this->this_lang = preg_replace( "/[^0-9a-zA-Z-]/i", '', strip_tags( $_GET[ 'lang' ] ) );
							// set the language based on the content id - for short links
						} elseif ( isset( $_GET[ 'page_id' ] ) ) {
							$language_code_prepared = $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_type='post_page' AND element_id=%d", $_GET[ 'page_id' ] );
							$this->this_lang = $wpdb->get_var( $language_code_prepared );
						} elseif ( isset( $_GET[ 'p' ] ) ) {
							$post_type_prepared = $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID=%d", $_GET[ 'p' ] );
							$post_type       = $wpdb->get_var( $post_type_prepared );
							$language_code_prepared = $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_type=%s AND element_id=%d", 'post_' . $post_type, $_GET[ 'p' ] );
							$this->this_lang = $wpdb->get_var( $language_code_prepared );
						} elseif ( isset( $_GET[ 'cat_ID' ] ) ) {
							$cat_tax_id_prepared = $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy=%s", $_GET[ 'cat_ID' ], 'category' );
							$cat_tax_id      = $wpdb->get_var( $cat_tax_id_prepared );
							$language_code_prepared = $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_type='tax_category' AND element_id=%d", $cat_tax_id );
							$this->this_lang = $wpdb->get_var( $language_code_prepared );
						} elseif ( isset( $_GET[ 'tag' ] ) ) {
							$tag_tax_id_prepared = $wpdb->prepare( "
								   SELECT x.term_taxonomy_id FROM {$wpdb->term_taxonomy} x JOIN {$wpdb->terms} t ON t.term_id = x.term_id
								   WHERE t.slug=%s AND x.taxonomy='post_tag'", $_GET[ 'tag' ] );
							$tag_tax_id      = $wpdb->get_var( $tag_tax_id_prepared );
							$language_code_prepared = $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations
								   WHERE element_type='tax_post_tag' AND element_id=%d", $tag_tax_id );
							$this->this_lang = $wpdb->get_var( $language_code_prepared );
						}


						//
						if ( !isset( $_GET[ 'lang' ] ) && ( $this->this_lang && $this->this_lang != $default_language ) ) {
							if ( !isset( $GLOBALS[ 'wp_rewrite' ] ) ) {
								require_once ABSPATH . WPINC . '/rewrite.php';
								$GLOBALS[ 'wp_rewrite' ] = new WP_Rewrite();
							}
							define( 'ICL_DOING_REDIRECT', true );
							if ( isset( $_GET[ 'page_id' ] ) ) {
								wp_redirect( get_page_link( $_GET[ 'page_id' ] ), '301' );
								exit;
							} elseif ( isset( $_GET[ 'p' ] ) ) {
								wp_redirect( get_permalink( $_GET[ 'p' ] ), '301' );
								exit;
							} elseif ( isset( $_GET[ 'cat_ID' ] ) ) {
								wp_redirect( get_term_link( intval( $_GET[ 'cat_ID' ] ), 'category' ) );
								exit;
							} elseif ( isset( $_GET[ 'tag' ] ) ) {
								wp_redirect( get_term_link( $_GET[ 'tag' ], 'post_tag' ) );
								exit;
							} else {
								if ( isset( $this->settings[ 'taxonomies_sync_option' ] ) ) {
									$taxs = array_keys( (array)$this->settings[ 'taxonomies_sync_option' ] );
									foreach ( $taxs as $t ) {
										if ( isset( $_GET[ $t ] ) ) {
											$term_obj  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} x ON t.term_id = x.term_id
                                                WHERE t.slug=%s AND x.taxonomy=%s", $_GET[ $t ], $t ) );
											$term_link = get_term_link( $term_obj, $t );
											$term_link = str_replace( '&amp;', '&', $term_link ); // fix
											if ( $term_link && !is_wp_error( $term_link ) ) {
												wp_redirect( $term_link );
												exit;
											}
										}
									}
								}
							}

						}

						if ( empty( $this->this_lang ) ) {
							$this->this_lang = $default_language;
						}
				}
				// allow forcing the current language when it can't be decoded from the URL
				$this->this_lang = apply_filters( 'icl_set_current_language', $this->this_lang );

			}

			//reorder active language to put 'this_lang' in front
			foreach ( $this->active_languages as $k => $al ) {
				if ( $al[ 'code' ] == $this->this_lang ) {
					unset( $this->active_languages[ $k ] );
					$this->active_languages = array_merge( array( $k => $al ), $this->active_languages );
				}
			}

			// filter some queries
			add_filter( 'query', array( $this, 'filter_queries' ) );

			add_filter( 'option_rewrite_rules', array( $this, 'rewrite_rules_filter' ) );

			$this->set_language_cookie();

			if ( is_admin() && ( !isset( $_GET[ 'page' ] ) || !defined( 'WPML_ST_FOLDER' ) || $_GET[ 'page' ] != WPML_ST_FOLDER . '/menu/string-translation.php' ) && ( !isset( $_GET[ 'page' ] ) || !defined( 'WPML_TM_FOLDER' ) || $_GET[ 'page' ] != WPML_TM_FOLDER . '/menu/translations-queue.php' )
			) {

				if ( version_compare( $GLOBALS[ 'wp_version' ], '3.3', '<' ) ) {
					// Legacy code for admin language switcher
					if ( !$this->is_rtl() && version_compare( $GLOBALS[ 'wp_version' ], '3.3', '>' ) ) {
						add_action( 'admin_notices', 'wpml_set_admin_language_switcher_place', 100 );
						add_action( 'network_admin_notices', 'wpml_set_admin_language_switcher_place', 100 );
						add_action( 'user_admin_notices', 'wpml_set_admin_language_switcher_place', 100 );
						function wpml_set_admin_language_switcher_place()
						{
							echo '<br clear="all" />';
						}
					}
					add_action( 'in_admin_header', array( $this, 'admin_language_switcher_legacy' ) );
				} else {
					// Admin language switcher goes to the WP admin bar
					add_action( 'wp_before_admin_bar_render', array( $this, 'admin_language_switcher' ) );
				}

			}

			if ( !is_admin() && defined( 'DISQUS_VERSION' ) ) {
				include ICL_PLUGIN_PATH . '/modules/disqus.php';
			}
                        
		}
                
		/*
		 * If user perform bulk taxonomy deletion when displaying non-default
		 * language taxonomies, after deletion should stay with same language
		 */

		if ( is_admin() &&
				isset($_POST['_wp_http_referer'])
				&& false !== strpos($_POST['_wp_http_referer'], 'edit-tags.php')

				&& !empty($_POST['delete_tags'])
				&& is_array($_POST['delete_tags'])

				&& !empty($_GET['lang'])
				&& (
						$_POST['action'] == 'delete' || $_POST['action2'] == 'delete'
						) ) {
			add_filter('wp_redirect', array($this, 'preserve_lang_param_after_bulk_category_delete'));
		}

		if ( $this->is_rtl() ) {
			$GLOBALS[ 'text_direction' ] = 'rtl';
		}

		if (!wpml_is_ajax() && is_admin() && empty( $this->settings[ 'dont_show_help_admin_notice' ] ) ) {
			if ( !$this->get_setting('setup_wizard_step') ) {
				if(SitePress::check_settings_integrity()) {
					add_action( 'admin_notices', array( $this, 'help_admin_notice' ) );
				}
			}
		}

		$short_v = implode( '.', array_slice( explode( '.', ICL_SITEPRESS_VERSION ), 0, 3 ) );
		if ( is_admin() && ( !isset( $this->settings[ 'hide_upgrade_notice' ] ) || $this->settings[ 'hide_upgrade_notice' ] != $short_v ) ) {
			add_action( 'admin_notices', array( $this, 'upgrade_notice' ) );
		}

		require ICL_PLUGIN_PATH . '/inc/template-constants.php';
		if ( defined( 'WPML_LOAD_API_SUPPORT' ) ) {
			require ICL_PLUGIN_PATH . '/inc/wpml-api.php';

		}

		add_action( 'wp_footer', array( $this, 'display_wpml_footer' ), 20 );

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			add_action( 'xmlrpc_call', array( $this, 'xmlrpc_call_actions' ) );
			add_filter( 'xmlrpc_methods', array( $this, 'xmlrpc_methods' ) );
		}

		if ( defined( 'WPML_TM_VERSION' ) && is_admin() ) {
			require ICL_PLUGIN_PATH . '/inc/quote.php';
		}

		add_action( 'init', array( $this, 'set_up_language_selector' ) );

		global $pagenow;
		if ( $pagenow == 'admin-ajax.php' ) {
			if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'wpml_tt_show_terms') {
				$default_language = $this->get_default_language();
				$this->switch_lang($default_language, true);
			}
		}
		// Disable the Admin language switcher when in Taxonomy Translation page
		// If the page uses AJAX and the language must be forced to default, please use the
		// if ( $pagenow == 'admin-ajax.php' )above
		if ( is_admin() && isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == ICL_PLUGIN_FOLDER . '/menu/taxonomy-translation.php' ) {
			$this->switch_lang($default_language, true);
			add_action( 'init', array( $this, 'remove_admin_language_switcher'));
		}

		//Code to run when reactivating the plugin
		$recently_activated = $this->get_setting('just_reactivated');
		if($recently_activated) {
			add_action( 'init', array( $this, 'rebuild_language_information' ), 1000 );
		}
	}

	function icl_redirect_canonical_wrapper()
	{
		global $_icl_server_request_uri;
		$requested_url = ( !empty( $_SERVER[ 'HTTPS' ] ) && strtolower( $_SERVER[ 'HTTPS' ] ) == 'on' ) ? 'https://' : 'http://';
		$requested_url .= $this->get_server_host_name();
		$requested_url .= $_icl_server_request_uri;
		redirect_canonical( $requested_url );
	}

	/**
	 * If user perform bulk taxonomy deletion when displaying non-default
	 * language taxonomies, after deletion should stay with same language
	 *
	 * @param string $location Url where browser will redirect
	 * @return string Url where browser will redirect
	 */
	function preserve_lang_param_after_bulk_category_delete($location) {
		if (empty($_GET['lang'])) {
			return $location;
		}

		$location = add_query_arg( 'lang', $_GET['lang'], $location );

		return $location;
	}

	function remove_admin_language_switcher() {
		remove_action( 'wp_before_admin_bar_render', array( $this, 'admin_language_switcher' ) );
	}

	function rebuild_language_information() {
		$this->set_setting('just_reactivated', 0);
		$this->save_settings();
		global $iclTranslationManagement;
		if ( isset( $iclTranslationManagement ) ) {
			$iclTranslationManagement->add_missing_language_information();
		}
	}

	function on_wp_init()
	{
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			if ( $this->icl_account_configured() ) {
				add_action( 'admin_notices', array( $this, 'icl_reminders' ) );
			}
		}

		include ICL_PLUGIN_PATH . '/inc/translation-management/taxonomy-translation.php';
	}

	function setup()
	{
		$setup_complete = $this->get_setting('setup_complete');
		if(!$setup_complete) {
			$this->set_setting('setup_complete', false);
		}
		return $setup_complete;
	}

	function get_current_user()
	{
		global $current_user;
		if ( did_action( 'set_current_user' ) ) {
			return $current_user;
		} else {
			return $this->current_user; // created early / no authentication
		}
	}

	function ajax_setup()
	{
		require ICL_PLUGIN_PATH . '/ajax.php';
	}

	function configure_custom_column()
	{
		global $pagenow, $wp_post_types;

		$pagenow_ = '';

		$is_ajax = false;
		if ( $pagenow == 'admin-ajax.php' ) {
			if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'inline-save' || isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'fetch-list'
			) {
				$is_ajax = true;
			}
		}

		if ( ( $pagenow == 'edit.php' || $pagenow_ == 'edit-pages.php' || $is_ajax ) ) {
			$post_type = isset( $_REQUEST[ 'post_type' ] ) ? $_REQUEST[ 'post_type' ] : 'post';
			switch ( $post_type ) {
				case 'post':
				case 'page':
					add_filter( 'manage_' . $post_type . 's_columns', array( $this, 'add_posts_management_column' ) );
					if ( isset( $_GET[ 'post_type' ] ) && $_GET[ 'post_type' ] == 'page' ) {
						add_action( 'manage_' . $post_type . 's_custom_column', array( $this, 'add_content_for_posts_management_column' ) );
					}
					add_action( 'manage_posts_custom_column', array( $this, 'add_content_for_posts_management_column' ) );
					break;
				default:
					if ( in_array( $post_type, array_keys( $this->get_translatable_documents() ) ) ) {
						add_filter( 'manage_' . $post_type . '_posts_columns', array( $this, 'add_posts_management_column' ) );
						if ( $wp_post_types[ $post_type ]->hierarchical ) {
							add_action( 'manage_pages_custom_column', array( $this, 'add_content_for_posts_management_column' ) );
							add_action( 'manage_posts_custom_column', array( $this, 'add_content_for_posts_management_column' ) ); // add this too - for more types plugin
						} else {
							add_action( 'manage_posts_custom_column', array( $this, 'add_content_for_posts_management_column' ) );
						}
					}
			}
			add_action( 'admin_print_scripts', array( $this, '__set_posts_management_column_width' ) );
		}
	}

	function _taxonomy_languages_menu()
	{
		// tags language selection
		global $pagenow;
		if ( $pagenow == 'edit-tags.php' ) {
			// handle case of the tax edit page (after a taxonomy has been added)
			// needs to redirect back to
			if ( isset( $_GET[ 'trid' ] ) && isset( $_GET[ 'source_lang' ] ) ) {
				$translations = $this->get_element_translations( $_GET[ 'trid' ], 'tax_' . $_GET[ 'taxonomy' ] );
				if ( isset( $translations[ $_GET[ 'lang' ] ] ) ) {
					wp_redirect( get_edit_term_link( $translations[ $_GET[ 'lang' ] ]->term_id, $_GET[ 'taxonomy' ] ) );
					exit;
				} else {
					add_action( 'admin_notices', array( $this, '_tax_adding' ) );
				}
			}

			$taxonomy = isset( $_GET[ 'taxonomy' ] ) ? esc_sql( $_GET[ 'taxonomy' ] ) : 'post_tag';
			if ( $this->is_translated_taxonomy( $taxonomy ) ) {
				add_action( 'admin_print_scripts-edit-tags.php', array( $this, 'js_scripts_tags' ) );
				if ( $taxonomy == 'category' ) {
					add_action( 'edit_category_form', array( $this, 'edit_term_form' ) );
				} else {
					add_action( 'add_tag_form', array( $this, 'edit_term_form' ) );
					add_action( 'edit_tag_form', array( $this, 'edit_term_form' ) );
				}
				add_action( 'admin_footer', array( $this, 'terms_language_filter' ) );
				add_filter( 'wp_dropdown_cats', array( $this, 'wp_dropdown_cats_select_parent' ) );
			}
		}
	}

	function _tax_adding()
	{
		$translations = $this->get_element_translations( $_GET[ 'trid' ], 'tax_' . $_GET[ 'taxonomy' ] );
		if ( !empty( $translations ) && isset( $translations[ $_GET[ 'source_lang' ] ]->name ) ) {
			$tax_name = apply_filters( 'the_category', $translations[ $_GET[ 'source_lang' ] ]->name );
			echo '<div id="icl_tax_adding_notice" class="updated fade"><p>' . sprintf( __( 'Adding translation for: %s.', 'sitepress' ), $tax_name ) . '</p></div>';
		}
	}

	/**
	 * @param WP_Query $query
	 */
	function loop_start($query) {

		if($query->post_count) {
			$this->cache_translations($query->posts);
		}
	}

	/**
	 * Cache translated posts
	 *
	 * @param $posts
	 */
	function cache_translations($posts) {
		global $wpdb, $wp_query, $sitepress;
		static $last_query=false;

		if ( isset( $sitepress ) && isset( $wp_query ) && $wp_query->is_main_query() ) {

			if($last_query == $wp_query->query_vars_hash) return;

			$sticky_posts_ids = get_option( 'sticky_posts' );
			if ( $sticky_posts_ids ) {
				if ( count( $sticky_posts_ids ) == 1 ) {
					$sticky_posts_prepared = $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", array( $sticky_posts_ids[0] ) );
				} else {
					$sticky_posts_prepared = "SELECT * FROM {$wpdb->posts} WHERE ID IN (" . implode( ',', array_filter( $sticky_posts_ids ) ) . ")";
				}
				$sticky_posts  = $wpdb->get_results( $sticky_posts_prepared );
				$posts_objects = array_map( 'get_post', $sticky_posts );
				if ( !$posts ) {
					$posts = $posts_objects;
				} else {
					$posts = array_merge( $posts, $posts_objects );
					//Remove duplicates
					$posts = array_map( "unserialize", array_unique( array_map( "serialize", $posts ) ) );
				}
			}
			if ( $posts ) {
				$terms = array();

				//Query specific cache
				$cache_key                 = $wp_query->query_vars_hash;
				$cache_group               = 'wp_query:posts_translations';
				$cached_posts_translations = wp_cache_get( $cache_key, $cache_group );
				if ( !$cached_posts_translations ) {
					$post_types = array();
					foreach ( $posts as $post ) {
						$post_types[ $post->post_type ][] = $post->ID;
					}

					$trids = array();
					if ( $post_types ) {
						$trid_cache_group = 'element_trid';
						foreach ( $post_types as $post_type => $posts_ids ) {

							$element_type    = 'post_' . $post_type;
							$s_post_type_ids = join( ',', array_filter($posts_ids) );
							$trids_prepared  = $wpdb->prepare( "SELECT trid, element_id, language_code, source_language_code FROM {$wpdb->prefix}icl_translations WHERE element_id IN (" . $s_post_type_ids . ") AND element_type=%s GROUP BY trid", array( $element_type ) );
							$post_type_trids_data = $wpdb->get_results( $trids_prepared );
							foreach($post_type_trids_data as $post_type_trid_data) {
								$element_id = $post_type_trid_data->element_id;

								$trid_cache_key = $element_id . ':post_' . $post_type;

								$trid = wp_cache_get($trid_cache_key, $trid_cache_group);

								if(!$trid) {
									$trid = $post_type_trid_data->trid;
									$trids[] = $trid;
									wp_cache_add($trid_cache_key, $trid, $trid_cache_group);
								}
								if($trid) {
									$element_language_details_cache_group = 'element_language_details';

									$element_language_details = wp_cache_get($trid_cache_key, $element_language_details_cache_group);
									if(!$element_language_details) {
										$details = new stdClass();
										$details->trid = $trid;
										$details->language_code = $post_type_trid_data->language_code;
										$details->source_language_code = $post_type_trid_data->source_language_code;

										wp_cache_add($trid_cache_key, $details, $element_language_details_cache_group);
									}
								}

								//Deal with taxonomies
								//$_taxonomies = get_post_taxonomies($element_id);
								$_taxonomies= get_post_taxonomies($element_id);
								foreach($_taxonomies as $_taxonomy) {
									if($sitepress->is_translated_taxonomy($_taxonomy)) {
										$_terms = wp_get_post_terms($element_id, $_taxonomy);
										foreach($_terms as $_term) {
											$terms[$_term->taxonomy][] = $_term->term_id;
										}
									}
								}
							}
						}
					}

					if ( $trids ) {
						if(count($trids)==1) {
							$posts_translations_prepared = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translations WHERE trid = %d ", array($trids[0]) );
						}else {
							$posts_translations_prepared = "SELECT * FROM {$wpdb->prefix}icl_translations WHERE trid IN (" . join( ',', array_filter($trids) ) . ") ";
						}
						$posts_translations     = $wpdb->get_results( $posts_translations_prepared );
						$post_ids = array();
						foreach($posts_translations as $posts_translation) {
							$post_ids[] = $posts_translation->element_id;
						}
						$posts_data = wp_cache_get($cache_key, 'wp_query:posts');
						if(!$posts_data && $post_ids) {
							$posts_prepared  = "SELECT * FROM {$wpdb->posts} WHERE ID IN (" . join( ',', array_filter($post_ids) ) . ") ";
							$posts_data  = $wpdb->get_results( $posts_prepared );
							wp_cache_set($cache_key, $posts_data, 'wp_query:posts');
						}
						if ( $posts_data ) {
							foreach($posts_data as $post) {
								$_post = wp_cache_get( $post->ID, 'posts' );

								if ( ! $_post ) {
									$_post = $post;

									$_post = sanitize_post( $_post, 'raw' );
									wp_cache_add( $_post->ID, $_post, 'posts' );
								}
							}
						}
					}

					if ( $terms ) {
						$cache_group = 'element_language_details';
						foreach ( $terms as $taxonomy => $term_ids ) {

							$element_type                = 'tax_' . $taxonomy;
							$terms_translations_prepared = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translations WHERE element_type = %s AND element_id IN (" . join( ',', $term_ids ) . ")", array( $element_type ) );
							$terms_translations          = $wpdb->get_results( $terms_translations_prepared );
							foreach ( $terms_translations as $terms_translation ) {
								$cache_key      = $terms_translation->element_id . ':' . $terms_translation->element_type;
								$cached_details = wp_cache_get( $cache_key, $cache_group );
								if ( !$cached_details ) {
									wp_cache_set( $cache_key, $terms_translation, $cache_group );
								}

								$icl_object_id_cache_group    = 'icl_object_id';
								$icl_object_id_cache_key_args = array( $terms_translation->element_id, $taxonomy, false, $terms_translation->language_code );
								$icl_object_id_cache_key      = implode( ':', array_filter( $icl_object_id_cache_key_args ) );
								$icl_object_id                = wp_cache_get( $cache_key, $cache_group );
								if ( !$icl_object_id ) {
									wp_cache_set( $icl_object_id_cache_key, $terms_translation->element_id, $icl_object_id_cache_group );
								}

								$icl_object_id_cache_key_args = array( $terms_translation->element_id, $taxonomy, true, $terms_translation->language_code );
								$icl_object_id_cache_key      = implode( ':', array_filter( $icl_object_id_cache_key_args ) );
								$icl_object_id                = wp_cache_get( $cache_key, $cache_group );
								if ( !$icl_object_id ) {
									wp_cache_set( $icl_object_id_cache_key, $terms_translation->element_id, $icl_object_id_cache_group );
								}

							}
						}
					}
				}
			}
			$last_query = $wp_query->query_vars_hash;
		}
	}

	function the_posts( $posts ) {
		if ( !is_admin() && isset( $this->settings[ 'show_untranslated_blog_posts' ] ) && $this->settings[ 'show_untranslated_blog_posts' ] && $this->get_current_language() != $this->get_default_language() ) {
			// show untranslated posts

			global $wpdb, $wp_query;
			$default_language = $this->get_default_language();
			$current_language = $this->get_current_language();

			$debug_backtrace = $this->get_backtrace(4, true); //Limit to first 4 stack frames, since 3 is the highest index we use

			/** @var $custom_wp_query WP_Query */
			$custom_wp_query = isset( $debug_backtrace[ 3 ][ 'object' ] ) ? $debug_backtrace[ 3 ][ 'object' ] : false;
			//exceptions
			if ( ( $current_language == $default_language )
				 // original language
				 ||
				 ( $wp_query != $custom_wp_query )
				 // called by a custom query
				 ||
				 ( !$custom_wp_query->is_posts_page && !$custom_wp_query->is_home )
				 // not the blog posts page
				 ||
				 $wp_query->is_singular
				 //is singular
				 ||
				 !empty( $custom_wp_query->query_vars[ 'category__not_in' ] )
				 //|| !empty($custom_wp_query->query_vars['category__in'])
				 //|| !empty($custom_wp_query->query_vars['category__and'])
				 ||
				 !empty( $custom_wp_query->query_vars[ 'tag__not_in' ] ) ||
				 !empty( $custom_wp_query->query_vars[ 'post__in' ] ) ||
				 !empty( $custom_wp_query->query_vars[ 'post__not_in' ] ) ||
				 !empty( $custom_wp_query->query_vars[ 'post_parent' ] )
			) {
				return $posts;
			}

			// get the posts in the default language instead
			$this_lang       = $this->this_lang;
			$this->this_lang = $default_language;

			remove_filter( 'the_posts', array( $this, 'the_posts' ) );

			$custom_wp_query->query_vars[ 'suppress_filters' ] = 0;

			if ( isset( $custom_wp_query->query_vars[ 'pagename' ] ) && !empty( $custom_wp_query->query_vars[ 'pagename' ] ) ) {
				if ( isset( $custom_wp_query->queried_object_id ) && !empty( $custom_wp_query->queried_object_id ) ) {
					$page_id = $custom_wp_query->queried_object_id;
				} else {
					// urlencode added for languages that have urlencoded post_name field value
					$custom_wp_query->query_vars[ 'pagename' ] = urlencode( $custom_wp_query->query_vars[ 'pagename' ] );
					$page_id                                   = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_name='{$custom_wp_query->query_vars['pagename']}' AND post_type='page'" );
				}
				if ( $page_id ) {
					$tr_page_id = icl_object_id( $page_id, 'page', false, $default_language );
					if ( $tr_page_id ) {
						$custom_wp_query->query_vars[ 'pagename' ] = $wpdb->get_var( "SELECT post_name FROM {$wpdb->posts} WHERE ID={$tr_page_id}" );
					}
				}
			}

			// look for posts without translations
			if ( $posts ) {
				$pids = false;
				foreach ( $posts as $p ) {
					$pids[ ] = $p->ID;
				}
				if ( $pids ) {
					$trids = $wpdb->get_col( "
						SELECT trid
						FROM {$wpdb->prefix}icl_translations
						WHERE element_type='post_post' AND element_id IN (" . join( ',', $pids ) . ") AND language_code = '" . $this_lang . "'" );

					if ( !empty( $trids ) ) {
						$posts_not_translated = $wpdb->get_col( "
							SELECT element_id, COUNT(language_code) AS c
							FROM {$wpdb->prefix}icl_translations
							WHERE trid IN (" . join( ',', $trids ) . ") GROUP BY trid HAVING c = 1
						" );
						if ( !empty( $posts_not_translated ) ) {
							$GLOBALS[ '__icl_the_posts_posts_not_translated' ] = $posts_not_translated;
							add_filter( 'posts_where', array( $this, '_posts_untranslated_extra_posts_where' ), 99 );
						}
					}
				}
			}

			//fix page for posts
			unset( $custom_wp_query->query_vars[ 'pagename' ] );
			unset( $custom_wp_query->query_vars[ 'page_id' ] );
			unset( $custom_wp_query->query_vars[ 'p' ] );

			$my_query = new WP_Query( $custom_wp_query->query_vars );

			add_filter( 'the_posts', array( $this, 'the_posts' ) );
			$this->this_lang = $this_lang;

			// create a map of the translated posts
			foreach ( $posts as $post ) {
				$trans_posts[ $post->ID ] = $post;
			}

			// loop original posts
			foreach ( $my_query->posts as $k => $post ) { // loop posts in the default language
				$trid         = $this->get_element_trid( $post->ID );
				$translations = $this->get_element_translations( $trid ); // get translations

				if ( isset( $translations[ $current_language ] ) ) { // if there is a translation in the current language
					if ( isset( $trans_posts[ $translations[ $current_language ]->element_id ] ) ) { //check the map of translated posts
						$my_query->posts[ $k ] = $trans_posts[ $translations[ $current_language ]->element_id ];
					} else { // check if the translated post exists in the database still
						$_post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d AND post_status='publish' LIMIT 1", $translations[ $current_language ]->element_id ) );
						if ( !empty( $_post ) ) {
							$_post                 = sanitize_post( $_post );
							$my_query->posts[ $k ] = $_post;

						} else {
							$my_query->posts[ $k ]->original_language = true;
						}
					}
				} else {
					$my_query->posts[ $k ]->original_language = true;
				}

			}

			if ( $custom_wp_query == $wp_query ) {
				$wp_query->max_num_pages = $my_query->max_num_pages;
			}

			$posts = $my_query->posts;

			unset( $GLOBALS[ '__icl_the_posts_posts_not_translated' ] );
			remove_filter( 'posts_where', array( $this, '_posts_untranslated_extra_posts_where' ), 99 );
		}

		// cache translated posts
		$this->cache_translations($posts);

		return $posts;
	}

	function get_pages($pages, $r) {
		$this->cache_translations($pages);

		return $pages;
	}

	function _posts_untranslated_extra_posts_where( $where )
	{
		global $wpdb;
		$where .= ' OR ' . $wpdb->posts . '.ID IN (' . join( ',', $GLOBALS[ '__icl_the_posts_posts_not_translated' ] ) . ')';

		return $where;
	}

	function initialize_cache()
	{
		require_once ICL_PLUGIN_PATH . '/inc/cache.php';
		$this->icl_translations_cache  = new icl_cache();
		$this->icl_locale_cache        = new icl_cache( 'locale', true );
		$this->icl_flag_cache          = new icl_cache( 'flags', true );
		$this->icl_language_name_cache = new icl_cache( 'language_name', true );
		$this->icl_term_taxonomy_cache = new icl_cache();
	}

	function set_admin_language()
	{
		global $wpdb;

		$default_language = $this->get_default_language();

		$found = false;
		$cache_key = "active_languages";
		$cache_group = "set_admin_lang";

		$active_languages = wp_cache_get($cache_key, $cache_group, false, $found);

		if(!$found) {
			//don't use method get_active_language()
			$active_languages_col = $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active=1" );
			$active_languages = array_keys( $active_languages_col );
			wp_cache_set($cache_key, $active_languages, $cache_group);
		}

		if ( !empty( $this->get_current_user()->ID ) ) {
			$this->admin_language = $this->get_user_admin_language( $this->get_current_user()->ID );
		}

		if ( $this->admin_language != '' && !in_array( $this->admin_language, $active_languages ) ) {
			delete_user_meta( $this->get_current_user()->ID, 'icl_admin_language' );
		}
		if ( empty( $this->settings[ 'admin_default_language' ] ) || !in_array( $this->settings[ 'admin_default_language' ], $active_languages ) ) {
			$this->settings[ 'admin_default_language' ] = '_default_';
			$this->save_settings();
		}

		if ( !$this->admin_language ) {
			$this->admin_language = $this->settings[ 'admin_default_language' ];
		}
		if ( $this->admin_language == '_default_' && $default_language ) {
			$this->admin_language = $default_language;
		}

	}

	function get_admin_language()
	{
		$current_user = $this->get_current_user();
		if ( isset( $current_user->ID ) && get_user_meta( $current_user->ID, 'icl_admin_language_for_edit', true ) && icl_is_post_edit() ) {
			$admin_language = $this->get_current_language();
		} else {
			$admin_language = $this->admin_language;
		}

		return $admin_language;
	}

	function get_user_admin_language( $user_id )
	{
		static $lang = array();
		if ( !isset( $lang[ $user_id ] ) ) {
			$lang[ $user_id ] = get_user_meta( $user_id, 'icl_admin_language', true );
			if ( empty( $lang[ $user_id ] ) ) {
				if ( isset( $this->settings[ 'admin_default_language' ] ) ) {
					$lang[ $user_id ] = $this->settings[ 'admin_default_language' ];
				}
				if ( empty( $lang[ $user_id ] ) || '_default_' == $lang[ $user_id ] ) {
					$lang[ $user_id ] = $this->get_admin_language();
				}
			}
		}

		return $lang[ $user_id ];
	}

	function administration_menu()
	{
		if(!SitePress::check_settings_integrity()) return;

		ICL_AdminNotifier::removeMessage( 'setup-incomplete' );
		$main_page = apply_filters( 'icl_menu_main_page', basename( ICL_PLUGIN_PATH ) . '/menu/languages.php' );
		if ( SitePress_Setup::setup_complete() ) {
			add_menu_page( __( 'WPML', 'sitepress' ), __( 'WPML', 'sitepress' ), 'wpml_manage_languages', $main_page, null, ICL_PLUGIN_URL . '/res/img/icon16.png' );

			add_submenu_page( $main_page, __( 'Languages', 'sitepress' ), __( 'Languages', 'sitepress' ), 'wpml_manage_languages', basename( ICL_PLUGIN_PATH ) . '/menu/languages.php' );
			//By Gen, moved Translation management after language, because problems with permissions
			do_action( 'icl_wpml_top_menu_added' );
			add_submenu_page( $main_page, __( 'Theme and plugins localization', 'sitepress' ), __( 'Theme and plugins localization', 'sitepress' ), 'wpml_manage_theme_and_plugin_localization', basename( ICL_PLUGIN_PATH ) . '/menu/theme-localization.php' );
			if ( !defined( 'WPML_TM_VERSION' ) ) {
				add_submenu_page( $main_page, __( 'Translation options', 'sitepress' ), __( 'Translation options', 'sitepress' ), 'wpml_manage_translation_options', basename( ICL_PLUGIN_PATH ) . '/menu/translation-options.php' );
			}

		} else {
			$main_page = basename( ICL_PLUGIN_PATH ) . '/menu/languages.php';
			add_menu_page( __( 'WPML', 'sitepress' ), __( 'WPML', 'sitepress' ), 'manage_options', $main_page, null, ICL_PLUGIN_URL . '/res/img/icon16.png' );
			add_submenu_page( $main_page, __( 'Languages', 'sitepress' ), __( 'Languages', 'sitepress' ), 'wpml_manage_languages', $main_page );

			if ((!isset($_REQUEST['page']) || $_REQUEST['page']!=ICL_PLUGIN_FOLDER . '/menu/troubleshooting.php') && !SitePress_Setup::languages_table_is_complete() ) {
				$troubleshooting_url  = admin_url( 'admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/troubleshooting.php' );
				$troubleshooting_link = '<a href="' . $troubleshooting_url . '" title="' . esc_attr( __( 'Troubleshooting', 'sitepress' ) ) . '">' . __( 'Troubleshooting', 'sitepress' ) . '</a>';
				$message = '';
				$message .= __( 'WPML is missing some records in the languages tables and it cannot fully work until this issue is fixed.', 'sitepress' );
				$message .= '<br />';
				$message .= sprintf( __( 'Please go to the %s page and click on %s to fix this problem.', 'sitepress' ), $troubleshooting_link, __( 'Fix languages tables', 'sitepress' ) );
				$message .= '<br />';
				$message .= '<br />';
				$message .= __( 'This warning will disappear once this issue is issue is fixed.', 'sitepress' );
				ICL_AdminNotifier::removeMessage( 'setup-incomplete' );
				ICL_AdminNotifier::addMessage( 'setup-incomplete', $message, 'error', false, false, false, 'setup', true );
				ICL_AdminNotifier::displayMessages( 'setup' );
			}
		}

		add_submenu_page( $main_page, __( 'Support', 'sitepress' ), __( 'Support', 'sitepress' ), 'wpml_manage_support', ICL_PLUGIN_FOLDER . '/menu/support.php' );
		$this->troubleshooting_menu(ICL_PLUGIN_FOLDER . '/menu/support.php');
	}

	private function troubleshooting_menu( $main_page ) {
		$submenu_slug = basename( ICL_PLUGIN_PATH ) . '/menu/troubleshooting.php';
		//if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == $submenu_slug ) {
			add_submenu_page( $main_page, __( 'Troubleshooting', 'sitepress' ), __( 'Troubleshooting', 'sitepress' ), 'wpml_manage_troubleshooting', $submenu_slug );

			return $submenu_slug;
		//}

	}

	// lower priority
	function administration_menu2()
	{
		if(!SitePress::check_settings_integrity()) return;
		$main_page = apply_filters( 'icl_menu_main_page', ICL_PLUGIN_FOLDER . '/menu/languages.php' );
		if ( $this->setup() ) {
			add_submenu_page( $main_page, __( 'Taxonomy Translation', 'sitepress' ), __( 'Taxonomy Translation', 'sitepress' ), 'wpml_manage_taxonomy_translation', ICL_PLUGIN_FOLDER . '/menu/taxonomy-translation.php' );
		}
	}

	function save_settings( $settings = null )
	{
		global $sitepress_settings;
		if ( !is_null( $settings ) ) {
			foreach ( $settings as $k => $v ) {
				if ( is_array( $v ) ) {
					foreach ( $v as $k2 => $v2 ) {
						$this->settings[ $k ][ $k2 ] = $v2;
					}
				} else {
					$this->settings[ $k ] = $v;
				}
			}
		}
		if ( !empty( $this->settings ) ) {
			update_option( 'icl_sitepress_settings', $this->settings );
			$sitepress_settings = $this->settings;
		}
		do_action( 'icl_save_settings', $settings );
	}

	private function check_settings() {
		if ( !isset( $this->settings ) ) {
			$this->settings = get_option( 'icl_sitepress_settings' );
		}
	}

	/**
	 * @since 3.1
	 */
	function get_settings()
	{
		$this->check_settings();
		return $this->settings;
	}

	/**
	 * @param string     $key
	 * @param mixed|bool $default
	 *
	 * @since 3.1
	 *
	 * @return bool|mixed
	 */
	function get_setting($key, $default = false)
	{
		$this->check_settings();
		return isset($this->settings[$key]) ? $this->settings[$key] : $default;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 * @param bool   $save_now	Immediately update the settings record in the DB
	 *
	 * @since 3.1
	 */
	function set_setting($key, $value, $save_now = false)
	{
		$this->check_settings();
		$this->settings[$key] = $value;
		if($save_now) {
			$this->save_settings();
		}
	}

	function get_user_preferences()
	{
		if ( empty( $this->user_preferences ) ) {
			$this->user_preferences = get_user_meta( $this->get_current_user()->ID, '_icl_preferences', true );
		}

		return $this->user_preferences;
	}

	function set_user_preferences($value) {
		$this->user_preferences = $value;
	}

	function save_user_preferences()
	{
		update_user_meta( $this->get_current_user()->ID, '_icl_preferences', $this->user_preferences );
	}

	function get_option( $option_name )
	{
		return isset( $this->settings[ $option_name ] ) ? $this->settings[ $option_name ] : null;
	}

	function verify_settings()
	{

		$default_settings = array(
			'interview_translators'              => 1,
			'existing_content_language_verified' => 0,
			'language_negotiation_type'          => 3,
			'theme_localization_type'            => 1,
			'icl_lso_header'                     => 0,
			'icl_lso_link_empty'                 => 0,
			'icl_lso_flags'                      => 0,
			'icl_lso_native_lang'                => 1,
			'icl_lso_display_lang'               => 1,
			'sync_page_ordering'                 => 1,
			'sync_page_parent'                   => 1,
			'sync_page_template'                 => 1,
			'sync_ping_status'                   => 1,
			'sync_comment_status'                => 1,
			'sync_sticky_flag'                   => 1,
			'sync_private_flag'                  => 1,
			'sync_post_format'                   => 1,
			'sync_delete'                        => 0,
			'sync_delete_tax'                    => 0,
			'sync_post_taxonomies'               => 1,
			'sync_post_date'                     => 0,
			'sync_taxonomy_parents'              => 0,
			'translation_pickup_method'          => 0,
			'notify_complete'                    => 1,
			'translated_document_status'         => 1,
			'remote_management'                  => 0,
			'auto_adjust_ids'                    => 1,
			'alert_delay'                        => 0,
			'promote_wpml'                       => 0,
			'troubleshooting_options'            => array( 'http_communication' => 1 ),
			'automatic_redirect'                 => 0,
			'remember_language'                  => 24,
			'icl_lang_sel_type'                  => 'dropdown',
			'icl_lang_sel_stype'                 => 'classic',
			'icl_lang_sel_orientation'           => 'vertical',
			'icl_lang_sel_copy_parameters'       => '',
			'icl_widget_title_show'              => 1,
			'translated_document_page_url'       => 'auto-generate',
			'sync_comments_on_duplicates '       => 0,
			'seo'                                => array( 'head_langs' => 1, 'canonicalization_duplicates' => 1 ),
			'posts_slug_translation'             => array( 'on' => 0 ),
			'languages_order'                    => '',
			'urls'                               => array( 'directory_for_default_language' => 0, 'show_on_root' => '', 'root_html_file_path' => '', 'root_page' => 0, 'hide_language_switchers' => 1 )
		);

		//configured for three levels
		$update_settings = false;
		foreach ( $default_settings as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $k2 => $v2 ) {
					if ( is_array( $v2 ) ) {
						foreach ( $v2 as $k3 => $v3 ) {
							if ( !isset( $this->settings[ $key ][ $k2 ][ $k3 ] ) ) {
								$this->settings[ $key ][ $k2 ][ $k3 ] = $v3;
								$update_settings                      = true;
							}
						}
					} else {
						if ( !isset( $this->settings[ $key ][ $k2 ] ) ) {
							$this->settings[ $key ][ $k2 ] = $v2;
							$update_settings               = true;
						}
					}
				}
			} else {
				if ( !isset( $this->settings[ $key ] ) ) {
					$this->settings[ $key ] = $value;
					$update_settings        = true;
				}
			}
		}


		if ( $update_settings ) {
			$this->save_settings();
		}
	}

	function _validate_language_per_directory( $language_code )
	{

		if ( !class_exists( 'WP_Http' ) ) {
			include_once ABSPATH . WPINC . '/class-http.php';
		}
		$client = new WP_Http();
		if ( false === @strpos( $_POST[ 'url' ], '?' ) ) {
			$url_glue = '?';
		} else {
			$url_glue = '&';
		}
		$response = $client->request( get_home_url() . '/' . $language_code . '/' . $url_glue . '____icl_validate_domain=1', array( 'timeout' => 15, 'decompress' => false ) );

		return ( !is_wp_error( $response ) && ( $response[ 'response' ][ 'code' ] == '200' ) && ( $response[ 'body' ] == '<!--' . get_home_url() . '-->' ) );
	}

	function save_language_pairs()
	{
		// clear existing languages
		$lang_pairs = $this->settings[ 'language_pairs' ];
		if ( is_array( $lang_pairs ) ) {
			foreach ( $lang_pairs as $from => $to ) {
				$lang_pairs[ $from ] = array();
			}
		}

		// get the from languages
		$from_languages = array();
		foreach ( $_POST as $k => $v ) {
			if ( 0 === strpos( $k, 'icl_lng_from_' ) ) {
				$f                 = str_replace( 'icl_lng_from_', '', $k );
				$from_languages[ ] = $f;
			}
		}

		foreach ( $_POST as $k => $v ) {
			if ( 0 !== strpos( $k, 'icl_lng_' ) ) {
				continue;
			}
			if ( 0 === strpos( $k, 'icl_lng_to' ) ) {
				$t   = str_replace( 'icl_lng_to_', '', $k );
				$exp = explode( '_', $t );
				if ( in_array( $exp[ 0 ], $from_languages ) ) {
					$lang_pairs[ $exp[ 0 ] ][ $exp[ 1 ] ] = 1;
				}
			}
		}

		$iclsettings[ 'language_pairs' ] = $lang_pairs;
		$this->save_settings( $iclsettings );
	}

	function get_active_languages( $refresh = false )
	{
		global $wpdb, $current_user;

		if ( $refresh || !isset($this->active_languages) || !$this->active_languages ) {
			$current_language = $this->get_current_language();
			if ( defined( 'WP_ADMIN' ) && isset($this->admin_language) && $this->admin_language ) {
				$in_language = $this->admin_language;
			} else {
				$in_language = $current_language ? $current_language : $this->get_default_language();
			}
			if ( !$refresh && isset( $this->icl_language_name_cache ) ) {
				$res = $this->icl_language_name_cache->get( 'in_language_' . $in_language );
			} else {
				$res = null;
			}

			if ( !$res || !is_array( $res ) ) {
				if(!$in_language) {
					$in_language = 'en';
				}
				$res_prepared = $wpdb->prepare( "
                    SELECT l.id, code, english_name, active, lt.name AS display_name, l.encode_url, l.default_locale, l.tag
                    FROM {$wpdb->prefix}icl_languages l
                        JOIN {$wpdb->prefix}icl_languages_translations lt ON l.code=lt.language_code
                    WHERE
                        active=1 AND lt.display_language_code = %s
                    ORDER BY major DESC, english_name ASC", array($in_language));
				$res = $wpdb->get_results( $res_prepared, ARRAY_A );
				if ( isset( $this->icl_language_name_cache ) ) {
					$this->icl_language_name_cache->set( 'in_language_' . $in_language, $res );
				}
			}

			$languages = array();
			if ( $res ) {
				foreach ( $res as $r ) {
					$languages[ $r[ 'code' ] ] = $r;
				}
			}

			if ( isset( $this->icl_language_name_cache ) ) {
				$res = $this->icl_language_name_cache->get( 'languages' );
			} else {
				$res = null;
			}

			//WPML setup
			if ( !isset($this->settings[ 'setup_complete' ]) || empty( $this->settings[ 'setup_complete' ] ) ) {
				$res = null;
			}

			if ( !$res && $languages ) {

				$res = $wpdb->get_results( "
                    SELECT language_code, name
                    FROM {$wpdb->prefix}icl_languages_translations
                    WHERE language_code IN ('" . join( "','", array_keys( $languages ) ) . "') AND language_code = display_language_code
                " );
				if ( isset( $this->icl_language_name_cache ) ) {
					$this->icl_language_name_cache->set( 'languages', $res );
				}
			}

			if($res) {
				foreach ( $res as $row ) {
					$languages[ $row->language_code ][ 'native_name' ] = $row->name;
				}
				$this->active_languages = $languages;
			}
		}

		// hide languages for front end
		if ( !is_admin() && isset( $this->settings[ 'hidden_languages' ]) && !empty( $this->settings[ 'hidden_languages' ] ) && is_array( $this->settings[ 'hidden_languages' ] ) ) {
			if ( !isset( $current_user ) || !$current_user ) {
				get_currentuserinfo();
			}
			if ( empty( $current_user->data ) || !get_user_meta( $this->get_current_user()->ID, 'icl_show_hidden_languages', true ) ) {
				foreach ( $this->settings[ 'hidden_languages' ] as $l ) {
					unset( $this->active_languages[ $l ] );
				}
			}
		}

		return $this->active_languages;
	}

	function order_languages( $languages )
	{

		$ordered_languages = array();
		if ( is_array( $this->settings[ 'languages_order' ] ) ) {
			foreach ( $this->settings[ 'languages_order' ] as $code ) {
				if ( isset( $languages[ $code ] ) ) {
					$ordered_languages[ $code ] = $languages[ $code ];
					unset( $languages[ $code ] );
				}
			}
		} else {
			// initial save
			$iclsettings[ 'languages_order' ] = array_keys( $languages );
			$this->save_settings( $iclsettings );

		}

		if ( !empty( $languages ) ) {
			foreach ( $languages as $code => $lang ) {
				$ordered_languages[ $code ] = $lang;
			}

		}

		return $ordered_languages;

	}

	function set_active_languages( $arr )
	{
		global $wpdb;
		if ( !empty( $arr ) ) {
			$tmp = array();
			foreach ( $arr as $code ) {
				$tmp[ ] = esc_sql( trim( $code ) ); 
			}

			// set the locale
			$current_active_languages = (array)$wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1" );
			$new_languages            = array_diff( $tmp, $current_active_languages );

			if ( !empty( $new_languages ) ) {
				foreach ( $new_languages as $code ) {
					$default_locale_prepared = $wpdb->prepare( "SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE code=%s", $code );
					$default_locale          = $wpdb->get_var( $default_locale_prepared );
					if ( $default_locale ) {
						$code_exists_prepared = $wpdb->prepare( "SELECT code FROM {$wpdb->prefix}icl_locale_map WHERE code=%s", $code );
						$code_exists          = $wpdb->get_var( $code_exists_prepared );
						if ( $code_exists ) {
							$wpdb->update( $wpdb->prefix . 'icl_locale_map', array( 'locale' => $default_locale ), array( 'code' => $code ) );
						} else {
							$wpdb->insert( $wpdb->prefix . 'icl_locale_map', array( 'code' => $code, 'locale' => $default_locale ) );
						}
					}
				}
			}

			$codes = '(\'' . join( '\',\'', $tmp ) . '\')';
			$wpdb->update( $wpdb->prefix . 'icl_languages', array( 'active' => 0 ), array( 'active' => '1' ) );
			$wpdb->query( "UPDATE {$wpdb->prefix}icl_languages SET active=1 WHERE code IN {$codes}" );
			$this->icl_language_name_cache->clear();
		}

		$res_prepared = $wpdb->prepare( "
		            SELECT code, english_name, active, lt.name AS display_name
		            FROM {$wpdb->prefix}icl_languages l
		                JOIN {$wpdb->prefix}icl_languages_translations lt ON l.code=lt.language_code
		            WHERE
		                active=1 AND lt.display_language_code = %s
		            ORDER BY major DESC, english_name ASC", $this->get_default_language() );
		$res          = $wpdb->get_results( $res_prepared, ARRAY_A );
		$languages    = array();
		foreach ( $res as $r ) {
			$languages[ ] = $r;
		}
		$this->active_languages = $languages;

		return true;
	}

	function get_languages( $lang = false )
	{
		global $wpdb;
		if ( !$lang ) {
			$lang = $this->get_default_language();
		}
		$res       = $wpdb->get_results( "
            SELECT
                code, english_name, major, active, default_locale, lt.name AS display_name
            FROM {$wpdb->prefix}icl_languages l
                JOIN {$wpdb->prefix}icl_languages_translations lt ON l.code=lt.language_code
            WHERE lt.display_language_code = '{$lang}'
            ORDER BY major DESC, english_name ASC", ARRAY_A );
		$languages = array();
		foreach ( (array)$res as $r ) {
			$languages[ ] = $r;
		}

		return $languages;
	}
	
	function get_language_details( $code )
	{
		global $wpdb;
		if ( defined( 'WP_ADMIN' ) ) {
			$dcode = $this->admin_language;
		} else {
			$dcode = $code;
		}
		if ( isset( $this->icl_language_name_cache ) ) {
			$details = $this->icl_language_name_cache->get( 'language_details_' . $code . $dcode );
		} else {
			$details = null;
		}
		if ( !$details ) {
			$details = $wpdb->get_row( "
                SELECT
                    code, english_name, major, active, lt.name AS display_name
                FROM {$wpdb->prefix}icl_languages l
                    JOIN {$wpdb->prefix}icl_languages_translations lt ON l.code=lt.language_code
                WHERE lt.display_language_code = '{$dcode}' AND code='{$code}'
                ORDER BY major DESC, english_name ASC", ARRAY_A );
			if ( isset( $this->icl_language_name_cache ) ) {
				$this->icl_language_name_cache->set( 'language_details_' . $code . $dcode, $details );
			}
		}

		return $details;
	}

	function get_language_code( $english_name )
	{
		global $wpdb;
		$code = $wpdb->get_row( "
            SELECT
                code
            FROM {$wpdb->prefix}icl_languages
            WHERE english_name = '{$english_name}'", ARRAY_A );

		return $code[ 'code' ];
	}

	function get_icl_translator_status( &$iclsettings, $res = null )
	{

		if ( $res == null ) {
			// check what languages we have translators for.
			require_once ICL_PLUGIN_PATH . '/lib/Snoopy.class.php';
			require_once ICL_PLUGIN_PATH . '/lib/xml2array.php';
			require_once ICL_PLUGIN_PATH . '/lib/icl_api.php';

			$icl_query = false;
			if ( empty( $iclsettings[ 'site_id' ] ) ) {
				// Must be for support
				if ( !empty( $iclsettings[ 'support_site_id' ] ) ) {
					$icl_query = new ICanLocalizeQuery( $iclsettings[ 'support_site_id' ], $iclsettings[ 'support_access_key' ] );
				}
			} else {
				$icl_query = new ICanLocalizeQuery( $iclsettings[ 'site_id' ], $iclsettings[ 'access_key' ] );
			}

			if ( $icl_query === false ) {
				return;
			}

			$res = $icl_query->get_website_details();

		}
		if ( isset( $res[ 'translation_languages' ][ 'translation_language' ] ) ) {

			// reset $this->settings['icl_lang_status']
			$iclsettings[ 'icl_lang_status' ] = array();

			$translation_languages = $res[ 'translation_languages' ][ 'translation_language' ];
			if ( !isset( $translation_languages[ 0 ] ) ) {
				$buf                   = $translation_languages;
				$translation_languages = array( 0 => $buf );
			}

			$target = array();
			foreach ( $translation_languages as $lang ) {
				$translators = $_tr = array();
				$max_rate    = false;
				if ( isset( $lang[ 'translators' ] ) && !empty( $lang[ 'translators' ] ) ) {
					if ( !isset( $lang[ 'translators' ][ 'translator' ][ 0 ] ) ) {
						$_tr[ 0 ] = $lang[ 'translators' ][ 'translator' ];
					} else {
						$_tr = $lang[ 'translators' ][ 'translator' ];
					}
					foreach ( $_tr as $t ) {
						if ( $max_rate === false || $t[ 'attr' ][ 'amount' ] > $max_rate ) {
							$max_rate = $t[ 'attr' ][ 'amount' ];
						}
						$translators[ ] = array( 'id' => $t[ 'attr' ][ 'id' ], 'nickname' => $t[ 'attr' ][ 'nickname' ], 'contract_id' => $t[ 'attr' ][ 'contract_id' ] );
					}
				}
				$target[ ] = array(
					'from'                  => $this->get_language_code( ICL_Pro_Translation::server_languages_map( $lang[ 'attr' ][ 'from_language_name' ], true ) ),
					'to'                    => $this->get_language_code( ICL_Pro_Translation::server_languages_map( $lang[ 'attr' ][ 'to_language_name' ], true ) ), 'have_translators' => $lang[ 'attr' ][ 'have_translators' ],
					'available_translators' => $lang[ 'attr' ][ 'available_translators' ], 'applications' => $lang[ 'attr' ][ 'applications' ], 'contract_id' => $lang[ 'attr' ][ 'contract_id' ], 'id' => $lang[ 'attr' ][ 'id' ],
					'translators'           => $translators, 'max_rate' => $max_rate
				);
			}
			$iclsettings[ 'icl_lang_status' ] = $target;
		}

		if ( isset( $res[ 'client' ][ 'attr' ] ) ) {
			$iclsettings[ 'icl_balance' ]        = $res[ 'client' ][ 'attr' ][ 'balance' ];
			$iclsettings[ 'icl_anonymous_user' ] = $res[ 'client' ][ 'attr' ][ 'anon' ];
		}
		if ( isset( $res[ 'html_status' ][ 'value' ] ) ) {
			$iclsettings[ 'icl_html_status' ] = html_entity_decode( $res[ 'html_status' ][ 'value' ] );
			$iclsettings[ 'icl_html_status' ] = preg_replace_callback(
				'#<a([^>]*)href="([^"]+)"([^>]*)>#i',
				create_function( '$matches', 'global $sitepress; return $sitepress->create_icl_popup_link($matches[2], array(\'unload_cb\'=>\'icl_pt_reload_translation_box\'));' ),
				$iclsettings[ 'icl_html_status' ]
			);
		}

		if ( isset( $res[ 'translators_management_info' ][ 'value' ] ) ) {
			$iclsettings[ 'translators_management_info' ] = html_entity_decode( $res[ 'translators_management_info' ][ 'value' ] );
			$iclsettings[ 'translators_management_info' ] = preg_replace_callback(
				'#<a([^>]*)href="([^"]+)"([^>]*)>#i',
				create_function( '$matches', 'global $sitepress; return $sitepress->create_icl_popup_link($matches[2], array(\'unload_cb\'=>\'icl_pt_reload_translation_box\'));' ),
				$iclsettings[ 'translators_management_info' ]
			);
		}

		$iclsettings[ 'icl_support_ticket_id' ] = @intval( $res[ 'attr' ][ 'support_ticket_id' ] );
	}

	function get_language_status_text( $from_lang, $to_lang, $pop_close_cb = false )
	{

		$pop_args = array( 'title' => 'ICanLocalize' );
		if ( $pop_close_cb ) {
			$pop_args[ 'unload_cb' ] = $pop_close_cb;
		}

		$lang_status = !empty( $this->settings[ 'icl_lang_status' ] ) ? $this->settings[ 'icl_lang_status' ] : array();
		foreach ( $lang_status as $lang ) {
			if ( $from_lang == $lang[ 'from' ] && $to_lang == $lang[ 'to' ] ) {
				if ( isset( $lang[ 'available_translators' ] ) ) {
					if ( !$lang[ 'available_translators' ] ) {
						if ( $this->settings[ 'icl_support_ticket_id' ] == '' ) {
							// No translators available on icanlocalize for this language pair.
							$response = sprintf( __( '- (No translators available - please %sprovide more information about your site%s)', 'sitepress' ), $this->create_icl_popup_link( ICL_API_ENDPOINT . '/websites/' . $this->settings[ 'site_id' ] . '/explain?after=refresh_langs', $pop_args ), '</a>' );
						} else {
							$response = sprintf( __( '- (No translators available - %scheck progress%s)', 'sitepress' ), $this->create_icl_popup_link( ICL_API_ENDPOINT . '/support/show/' . $this->settings[ 'icl_support_ticket_id' ] . '?after=refresh_langs', $pop_args ), '</a>' );
						}

					} else {
						if ( !$lang[ 'applications' ] ) {
							// No translators have applied for this language pair.
							$pop_args[ 'class' ] = 'icl_hot_link';
							$response           = ' | ' . $this->create_icl_popup_link( "@select-translators;{$from_lang};{$to_lang}@", $pop_args ) . __( 'Select translators', 'sitepress' ) . '</a>';
						} else {
							if ( !$lang[ 'have_translators' ] ) {
								// translators have applied but none selected yet
								$pop_args[ 'class' ] = 'icl_hot_link';
								$response           = ' | ' . $this->create_icl_popup_link( "@select-translators;{$from_lang};{$to_lang}@", $pop_args ) . __( 'Select translators', 'sitepress' ) . '</a>';
							} else {
								// there are translators ready to translate
								$translators = array();
								if ( is_array( $lang[ 'translators' ] ) ) {
									foreach ( $lang[ 'translators' ] as $translator ) {
										$link           = $this->create_icl_popup_link( ICL_API_ENDPOINT . '/websites/' . $this->settings[ 'site_id' ] . '/website_translation_offers/' . $lang[ 'id' ] . '/website_translation_contracts/' . $translator[ 'contract_id' ], $pop_args );
										$translators[ ] = $link . esc_html( $translator[ 'nickname' ] ) . '</a>';
									}
								}
								$response = ' | ' . $this->create_icl_popup_link( "@select-translators;{$from_lang};{$to_lang}@", $pop_args ) . __( 'Select translators', 'sitepress' ) . '</a>';
								$response .= ' | ' . sprintf( __( 'Communicate with %s', 'sitepress' ), join( ', ', $translators ) );

							}
						}
					}

					return $response;

				}
				break;
			}

		}
		$pop_args[ 'class' ] = 'icl_hot_link';
		$response           = ' | ' . $this->create_icl_popup_link( "@select-translators;{$from_lang};{$to_lang}@", $pop_args ) . __( 'Select translators', 'sitepress' ) . '</a>';

		// no status found
		return $response;
	}

	function are_waiting_for_translators( $from_lang )
	{
		$lang_status = $this->settings[ 'icl_lang_status' ];
		if ( $lang_status && $this->icl_account_configured() ) {
			foreach ( $lang_status as $lang ) {
				if ( $from_lang == $lang[ 'from' ] ) {
					if ( isset( $lang[ 'available_translators' ] ) ) {
						if ( $lang[ 'available_translators' ] && !$lang[ 'applications' ] ) {
							return true;
						}
					}
				}
			}
		}

		return false;
	}

	function get_default_language()
	{
		return isset( $this->settings[ 'default_language' ] ) ? $this->settings[ 'default_language' ] : false;
	}

	function get_current_language()
	{
		return apply_filters( 'icl_current_language', $this->this_lang );
	}

	function get_strings_language()
	{
		return isset( $this->settings[ 'st' ][ 'strings_language' ] ) ? $this->settings[ 'st' ][ 'strings_language' ] : false;
	}

	function switch_lang( $code = null, $cookie_lang = false )
	{
		static $original_language, $original_language_cookie;

		if ( is_null( $original_language ) ) {
			$original_language = $this->get_current_language();
		}

		if ( is_null( $code ) ) {
			$this->this_lang      = $original_language;
			$this->admin_language = $original_language;

			// restore cookie language if case
			if ( !empty( $original_language_cookie ) ) {
				$this->update_language_cookie($original_language_cookie);
				$original_language_cookie = false;
			}

		} else {
			if ( $code == 'all' || in_array( $code, array_keys( $this->get_active_languages() ) ) ) {
				$this->this_lang      = $code;
				$this->admin_language = $code;
			}

			// override cookie language
			if ( $cookie_lang ) {
				$original_language_cookie = $this->get_language_cookie();
				$this->update_language_cookie($code);
			}

		}
	}

	function set_default_language( $code )
	{
		$iclsettings[ 'default_language' ] = $code;
		$this->save_settings( $iclsettings );

		// change WP locale
		$locale = $this->get_locale( $code );
		if ( $locale ) {
			update_option( 'WPLANG', $locale );
		}
		if ( $code != 'en' && !file_exists( ABSPATH . LANGDIR . '/' . $locale . '.mo' ) ) {
			return 1; //locale not installed
		}

		return true;
	}

	function get_icl_translation_enabled( $lang = null, $langto = null )
	{
		if ( !is_null( $lang ) ) {
			if ( !is_null( $langto ) ) {
				return $this->settings[ 'language_pairs' ][ $lang ][ $langto ];
			} else {
				return !empty( $this->settings[ 'language_pairs' ][ $lang ] );
			}
		} else {
			return isset( $this->settings[ 'enable_icl_translations' ] ) ? $this->settings[ 'enable_icl_translations' ] : false;
		}
	}

	function set_icl_translation_enabled()
	{
		$iclsettings[ 'translation_enabled' ] = true;
		$this->save_settings( $iclsettings );
	}

	function icl_account_reqs()
	{
		$errors = array();
		if ( !$this->get_icl_translation_enabled() ) {
			$errors[ ] = __( 'Professional translation not enabled', 'sitepress' );
		}

		return $errors;
	}

	function icl_account_configured()
	{
		return isset( $this->settings[ 'site_id' ] ) && $this->settings[ 'site_id' ] && isset( $this->settings[ 'access_key' ] ) && $this->settings[ 'access_key' ];
	}

	function icl_support_configured()
	{
		return isset( $this->settings[ 'support_site_id' ] ) && isset( $this->settings[ 'support_access_key' ] ) && $this->settings[ 'support_site_id' ] && $this->settings[ 'support_access_key' ];
	}

	function reminders_popup()
	{
		include ICL_PLUGIN_PATH . '/modules/icl-translation/icl-reminder-popup.php';
		exit;
	}

	function create_icl_popup_link( $link, $args = array(), $just_url = false, $support_mode = false )
	{

		// defaults
		/** @var $id int */
		/** @var $class string */
		$defaults = array(
			'title'     => null, 'class' => '', 'id' => '', 'ar' => 0, // auto_resize
			'unload_cb' => false, // onunload callback
		);

		extract( $defaults );
		extract( $args, EXTR_OVERWRITE );

		if ( !empty( $ar ) ) {
			$auto_resize = '&amp;auto_resize=1';
		} else {
			$auto_resize = '';
		}

		$unload_cb = isset( $unload_cb ) ? '&amp;unload_cb=' . $unload_cb : '';

		$url_glue = false !== strpos( $link, '?' ) ? '&' : '?';
		$link .= $url_glue . 'compact=1';

		if ( isset( $this->settings[ 'access_key' ] ) || isset( $this->settings[ 'support_access_key' ] ) ) {
			if ( $support_mode && isset( $this->settings[ 'support_access_key' ] ) ) {
				$link .= '&accesskey=' . $this->settings[ 'support_access_key' ];
			} elseif ( isset( $this->settings[ 'access_key' ] ) ) {
				$link .= '&accesskey=' . $this->settings[ 'access_key' ];
			}
		}

		if ( !empty( $id ) ) {
			$id = ' id="' . $id . '"';
		}
		if ( isset( $title ) && !$just_url ) {
			return '<a class="icl_thickbox ' . $class . '" title="' . $title . '" href="admin.php?page=' . ICL_PLUGIN_FOLDER . "/menu/languages.php&amp;icl_action=reminder_popup{$auto_resize}{$unload_cb}&amp;target=" . urlencode( $link ) . '"' . $id . '>';
		} else {
			if ( !$just_url ) {
				return '<a class="icl_thickbox ' . $class . '" href="admin.php?page=' . ICL_PLUGIN_FOLDER . "/menu/languages.php&amp;icl_action=reminder_popup{$auto_resize}{$unload_cb}&amp;target=" . urlencode( $link ) . '"' . $id . '>';
			} else {
				return 'admin.php?page=' . ICL_PLUGIN_FOLDER . "/menu/languages.php&amp;icl_action=reminder_popup{$auto_resize}{$unload_cb}&amp;target=" . urlencode( $link );
			}
		}
	}

	function js_scripts_setup()
	{
		//TODO: move javascript to external resource (use wp_localize_script() to pass arguments)
		global $pagenow, $wpdb;
		$default_language = $this->get_default_language();
		$current_language = $this->get_current_language();

		if ( isset( $_GET[ 'page' ] ) ) {
			$page          = basename( $_GET[ 'page' ] );
			$page_basename = str_replace( '.php', '', $page );
		} else {
			$page_basename = false;
		}

		$icl_ajax_url_root = rtrim(get_site_url(),'/');
		if(defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN) {
			$icl_ajax_url_root = str_replace('http://', 'https://', $icl_ajax_url_root);
		}
		$icl_ajax_url = $icl_ajax_url_root . '/wp-admin/admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/languages.php';
		?>
		<script type="text/javascript">
			// <![CDATA[
			var icl_ajx_url;
			icl_ajx_url = '<?php echo $icl_ajax_url; ?>';
			var icl_ajx_saved = '<?php echo icl_js_escape( __('Data saved','sitepress')); ?>';
			var icl_ajx_error = '<?php echo icl_js_escape( __('Error: data not saved','sitepress')); ?>';
			var icl_default_mark = '<?php echo icl_js_escape(__('default','sitepress')); ?>';
			var icl_this_lang = '<?php echo $this->this_lang ?>';
			var icl_ajxloaderimg_src = '<?php echo ICL_PLUGIN_URL ?>/res/img/ajax-loader.gif';
			var icl_cat_adder_msg = '<?php echo icl_js_escape(sprintf(__('To add categories that already exist in other languages go to the <a%s>category management page</a>','sitepress'), ' href="'.admin_url('edit-tags.php?taxonomy=category').'"'));?>';
			// ]]>

			<?php if(!$this->get_setting('ajx_health_checked')): ?>
			addLoadEvent(function () {
				jQuery.ajax({type: "POST", url: icl_ajx_url, data: "icl_ajx_action=health_check", error: function (msg) {
					var icl_initial_language = jQuery('#icl_initial_language');
					if (icl_initial_language.length) {
						icl_initial_language.find('input').attr('disabled', 'disabled');
					}
					jQuery('.wrap').prepend('<div class="error"><p><?php
                        echo icl_js_escape(sprintf(__("WPML can't run normally. There is an installation or server configuration problem. %sShow details%s",'sitepress'),
                        '<a href="#" onclick="jQuery(this).parent().next().slideToggle()">', '</a>'));
                    ?></p><p style="display:none"><?php echo icl_js_escape(__('AJAX Error:', 'sitepress'))?> ' + msg.statusText + ' [' + msg.status + ']<br />URL:' + icl_ajx_url + '</p></div>');
				}});
			});
			<?php endif; ?>
		</script>
		<?php
		if ( 'options-reading.php' == $pagenow ) {
			list( $warn_home, $warn_posts ) = $this->verify_home_and_blog_pages_translations();
			if ( $warn_home || $warn_posts ) {
				?>
				<script type="text/javascript">
					addLoadEvent(function () {
						jQuery('input[name="show_on_front"]').parent().parent().parent().parent().append('<?php echo str_replace("'","\\'",$warn_home . $warn_posts); ?>');
					});
				</script>
			<?php
			}
		}

		// display correct links on the posts by status break down
		// also fix links to category and tag pages
		if ( ( 'edit.php' == $pagenow || 'edit-pages.php' == $pagenow || 'categories.php' == $pagenow || 'edit-tags.php' == $pagenow ) && $current_language != $default_language ) {
			?>
			<script type="text/javascript">
				addLoadEvent(function () {
					jQuery('.subsubsub li a').each(function () {
						var h = jQuery(this).attr('href');
						var urlg;
						if (-1 == h.indexOf('?')) urlg = '?'; else urlg = '&';
						jQuery(this).attr('href', h + urlg + 'lang=<?php echo $current_language?>');
					});
					jQuery('.column-categories a, .column-tags a, .column-posts a').each(function () {
						jQuery(this).attr('href', jQuery(this).attr('href') + '&lang=<?php echo $current_language?>');
					});
				});
			</script>
		<?php
		}

		if ( 'edit-tags.php' == $pagenow ) {
			?>
			<script type="text/javascript">
				addLoadEvent(function () {
					var edit_tag = jQuery('#edittag');
					if (edit_tag.find('[name="_wp_original_http_referer"]').length && edit_tag.find('[name="_wp_http_referer"]').length) {
						edit_tag.find('[name="_wp_original_http_referer"]').val('<?php
                        $post_type = isset($_GET['post_type']) ? '&post_type=' . esc_html($_GET['post_type']) : '';
                        echo admin_url('edit-tags.php?taxonomy=' . esc_js($_GET['taxonomy']) . '&lang='.$current_language.'&message=3'.$post_type) ?>');
					}
				});
			</script>
		<?php
		}

		if ( 'post-new.php' == $pagenow ) {
			if ( isset( $_GET[ 'trid' ] ) ) {
				$translations = $wpdb->get_col( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d", $_GET[ 'trid' ] ) );
				remove_filter( 'option_sticky_posts', array( $this, 'option_sticky_posts' ) ); // remove filter used to get language relevant stickies. get them all
				$sticky_posts = get_option( 'sticky_posts' );
				add_filter( 'option_sticky_posts', array( $this, 'option_sticky_posts' ) ); // add filter back
				$is_sticky = false;
				foreach ( $translations as $t ) {
					if ( in_array( $t, $sticky_posts ) ) {
						$is_sticky = true;
						break;
					}
				}
				if ( isset( $_GET[ 'trid' ] ) && ( $this->settings[ 'sync_ping_status' ] || $this->settings[ 'sync_comment_status' ] ) ) {
					$res = $wpdb->get_row( $wpdb->prepare( "SELECT comment_status, ping_status FROM {$wpdb->prefix}icl_translations t
                    JOIN {$wpdb->posts} p ON t.element_id = p.ID WHERE t.trid=%d", $_GET[ 'trid' ] ) ); ?>
					<script type="text/javascript">addLoadEvent(function () {
							var comment_status = jQuery('#comment_status');
							var ping_status = jQuery('#ping_status');
							<?php if($this->settings['sync_comment_status']): ?>
							<?php if($res->comment_status == 'open'): ?>
							comment_status.attr('checked', 'checked');
							<?php else: ?>
							comment_status.removeAttr('checked');
							<?php endif; ?>
							<?php endif; ?>
							<?php if($this->settings['sync_ping_status']): ?>
							<?php if($res->ping_status == 'open'): ?>
							ping_status.attr('checked', 'checked');
							<?php else: ?>
							ping_status.removeAttr('checked');
							<?php endif; ?>
							<?php endif; ?>
						});</script><?php
				}
				if ( isset( $_GET[ 'trid' ] ) && $this->settings[ 'sync_private_flag' ] ) {
					if ( 'private' == $wpdb->get_var( $wpdb->prepare( "
                        SELECT p.post_status FROM {$wpdb->prefix}icl_translations t
                        JOIN {$wpdb->posts} p ON t.element_id = p.ID
                        WHERE t.trid=%d AND t.element_type='post_post'
                    ", $_GET[ 'trid' ] ) )
					) {
						?>
						<script type="text/javascript">addLoadEvent(function () {
								jQuery('#visibility-radio-private').attr('checked', 'checked');
								jQuery('#post-visibility-display').html('<?php echo icl_js_escape(__('Private', 'sitepress')); ?>');
							});
						</script><?php
					}
				}

				if ( isset( $_GET[ 'trid' ] ) && $this->settings[ 'sync_post_taxonomies' ] ) {

					$post_type         = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : 'post';
					$source_lang       = isset( $_GET[ 'source_lang' ] ) ? $_GET[ 'source_lang' ] : $default_language;
					$translatable_taxs = $this->get_translatable_taxonomies( true, $post_type );
					$all_taxs          = get_object_taxonomies( $post_type );


					$translations = $this->get_element_translations( $_GET[ 'trid' ], 'post_' . $post_type );
					$js           = array();
					if ( !empty( $all_taxs ) ) {
						foreach ( $all_taxs as $tax ) {
							$tax_detail = get_taxonomy( $tax );
							$terms      = get_the_terms( $translations[ $source_lang ]->element_id, $tax );
							$term_names = array();
							if ( $terms ) {
								foreach ( $terms as $term ) {
									if ( $tax_detail->hierarchical ) {
										if ( in_array( $tax, $translatable_taxs ) ) {
											$term_id = icl_object_id( $term->term_id, $tax, false );
										} else {
											$term_id = $term->term_id;
										}
										$js[ ] = "jQuery('#in-" . $tax . "-" . $term_id . "').attr('checked', 'checked');";
									} else {
										if ( in_array( $tax, $translatable_taxs ) ) {
											$term_id = icl_object_id( $term->term_id, $tax, false );
											if ( $term_id ) {
												$term          = get_term_by( 'id', $term_id, $tax );
												$term_names[ ] = esc_js( $term->name );
											}
										} else {
											$term_names[ ] = esc_js( $term->name );
										}
									}

								}
							}

							if ( $term_names ) {
								$js[ ] = "jQuery('#{$tax} .taghint').css('visibility','hidden');";
								$js[ ] = "jQuery('#new-tag-{$tax}').val('" . join( ', ', $term_names ) . "');";
							}
						}
					}

					if ( $js ) {
						echo '<script type="text/javascript">';
						echo PHP_EOL . '// <![CDATA[' . PHP_EOL;
						echo 'addLoadEvent(function(){' . PHP_EOL;
						echo join( PHP_EOL, $js );
						echo PHP_EOL . 'jQuery().ready(function() {
                        	jQuery(".tagadd").click();
                        	jQuery(\'html, body\').prop({scrollTop:0});
                        	jQuery(\'#title\').focus();
                        	});' . PHP_EOL;
						echo PHP_EOL . '});' . PHP_EOL;
						echo PHP_EOL . '// ]]>' . PHP_EOL;
						echo '</script>';
					}

				}

				// sync custom fields
				if ( !empty( $this->settings[ 'translation-management' ] ) ) {
					foreach ( (array)$this->settings[ 'translation-management' ][ 'custom_fields_translation' ] as $key => $sync_opt ) {
						if ( $sync_opt == 1 ) {
							$copied_cf[ ] = $key;
						}
					}
				}
				if ( !empty( $copied_cf ) ) {
					$source_lang     = isset( $_GET[ 'source_lang' ] ) ? $_GET[ 'source_lang' ] : $default_language;
					$lang_details    = $this->get_language_details( $source_lang );
					$original_custom = get_post_custom( $translations[ $source_lang ]->element_id );
					$copied_cf       = array_intersect( $copied_cf, array_keys( $original_custom ) );
					$copied_cf       = apply_filters( 'icl_custom_fields_to_be_copied', $copied_cf, $translations[ $source_lang ]->element_id );
					if ( !empty( $copied_cf ) && ( empty( $this->user_preferences[ 'notices' ] ) || empty( $this->user_preferences[ 'notices' ][ 'hide_custom_fields_copy' ] ) ) ) {
						$ccf_note = '<img src="' . ICL_PLUGIN_URL . '/res/img/alert.png" alt="Notice" width="16" height="16" style="margin-right:8px" />';
						$ccf_note .= '<a class="icl_user_notice_hide" href="#hide_custom_fields_copy" style="float:right;margin-left:20px;">' . __( 'Never show this.', 'sitepress' ) . '</a>';
						$ccf_note .= wp_nonce_field( 'save_user_preferences_nonce', '_icl_nonce_sup', false, false );
						$ccf_note .= sprintf( __( 'WPML will copy %s from %s when you save this post.', 'sitepress' ), '<i><strong>' . join( '</strong>, <strong>', $copied_cf ) . '</strong></i>', $lang_details[ 'display_name' ] );
						$this->admin_notices( $ccf_note, 'error' );
					}
				}

			}
			?>
			<?php if ( !empty( $is_sticky ) && $this->settings[ 'sync_sticky_flag' ] ): ?>
				<script type="text/javascript">
					addLoadEvent(
						function () {
							jQuery('#sticky').attr('checked', 'checked');
							var post_visibility_display = jQuery('#post-visibility-display');
							post_visibility_display.html(post_visibility_display.html() + ', <?php echo icl_js_escape(__('Sticky', 'sitepress')) ?>');
						});
				</script>
			<?php endif; ?>
		<?php
		}
		if ( 'page-new.php' == $pagenow || ( 'post-new.php' == $pagenow && isset( $_GET[ 'post_type' ] ) ) ) {
			if ( isset( $_GET[ 'trid' ] ) && ( $this->settings[ 'sync_page_template' ] || $this->settings[ 'sync_page_ordering' ] ) ) {
				$res = $wpdb->get_row( $wpdb->prepare( "
                    SELECT p.ID, p.menu_order FROM {$wpdb->prefix}icl_translations t
                    JOIN {$wpdb->posts} p ON t.element_id = p.ID
                    WHERE t.trid=%d AND p.post_type=%s AND t.element_type=%s
                ", $_GET[ 'trid' ], $_GET[ 'post_type' ], 'post_' . $_GET[ 'post_type' ] ) );
				if ( $this->settings[ 'sync_page_ordering' ] ) {
					$menu_order = $res->menu_order;
				} else {
					$menu_order = false;
				}
				if ( $this->settings[ 'sync_page_template' ] ) {
					$page_template = get_post_meta( $res->ID, '_wp_page_template', true );
				} else {
					$page_template = false;
				}
				if ( $menu_order || $page_template ) {
					?>
					<script type="text/javascript">addLoadEvent(function () { <?php
                    if($menu_order){ ?>
							jQuery('#menu_order').val(<?php echo $menu_order ?>);
							<?php }
                    if($page_template && 'default' != $page_template){ ?>
							jQuery('#page_template').val('<?php echo $page_template ?>');
							<?php }
                    ?>
						});</script><?php
				}
			}
		} elseif ( 'edit-comments.php' == $pagenow || 'index.php' == $pagenow || 'post.php' == $pagenow ) {
			wp_enqueue_script( 'sitepress-' . $page_basename, ICL_PLUGIN_URL . '/res/js/comments-translation.js', array(), ICL_SITEPRESS_VERSION );
		}

		// sync post dates
		if ( icl_is_post_edit() ) {
			// @since 3.1.5
			// Enqueing 'wp-jquery-ui-dialog', just in case it doesn't get automatically enqueued
			wp_enqueue_style( 'wp-jquery-ui-dialog' );
			wp_enqueue_style( 'sitepress-post-edit', ICL_PLUGIN_URL . '/res/css/post-edit.css', array( ), ICL_SITEPRESS_VERSION );
			wp_enqueue_script( 'sitepress-post-edit', ICL_PLUGIN_URL . '/res/js/post-edit.js', array( 'jquery-ui-dialog', 'jquery-ui-autocomplete', 'autosave' ), ICL_SITEPRESS_VERSION );

			if ( $this->settings[ 'sync_post_date' ] ) {
				$post_type = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : 'post';

				if ( isset( $_GET[ 'trid' ] ) ) {
					$trid = intval( $_GET[ 'trid' ] );
				} else {
					$post_id = @intval( $_GET[ 'post' ] );
					$trid    = $this->get_element_trid( $post_id, 'post_' . $post_type );
				}

				$translations = $this->get_element_translations( $trid, 'post_' . $post_type );
				if ( !empty( $translations ) && isset( $translations[ $current_language ] ) && !$translations[ $current_language ]->original ) {
					$source_lang = isset( $_GET[ 'source_lang' ] ) ? $_GET[ 'source_lang' ] : $default_language;
					if ( isset( $translations[ $source_lang ] ) ) {
						$original_date = $wpdb->get_var( $wpdb->prepare( "SELECT post_date FROM {$wpdb->posts} WHERE ID=%d", $translations[ $source_lang ]->element_id ) );
						$exp           = explode( ' ', $original_date );
						list( $aa, $mm, $jj ) = explode( '-', $exp[ 0 ] );
						list( $hh, $mn, $ss ) = explode( ':', $exp[ 1 ] );
						?>
						<script type="text/javascript">
							addLoadEvent(
								function () {
									jQuery('#aa').val('<?php echo $aa ?>').attr('readonly', 'readonly');
									jQuery('#mm').val('<?php echo $mm ?>').attr('readonly', 'readonly');
									jQuery('#jj').val('<?php echo $jj ?>').attr('readonly', 'readonly');
									jQuery('#hh').val('<?php echo $hh ?>').attr('readonly', 'readonly');
									jQuery('#mn').val('<?php echo $mn ?>').attr('readonly', 'readonly');
									jQuery('#ss').val('<?php echo $ss ?>').attr('readonly', 'readonly');
									var timestamp = jQuery('#timestamp');
									timestamp.find('b').html('<?php esc_html_e('copy from original', 'sitepress') ?>');
									timestamp.next().html('<?php esc_html_e('show', 'sitepress') ?>');
								});
						</script>
					<?php
					}
				}
			}
		}

		if ( 'post-new.php' == $pagenow && isset( $_GET[ 'trid' ] ) && $this->settings[ 'sync_post_format' ] && function_exists( 'get_post_format' ) ) {
			$format = get_post_format( $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d and language_code=%s", $_GET[ 'trid' ], $_GET[ 'source_lang' ] ) ) );
			?>
			<script type="text/javascript">
				addLoadEvent(function () {
					jQuery('#post-format-' + '<?php echo $format ?>').attr('checked', 'checked');
				});
			</script><?php
		}


		if ( is_admin() ) {
			wp_enqueue_script( 'thickbox' );
			wp_enqueue_script( 'theme-preview' );
			wp_enqueue_script( 'sitepress-icl_reminders', ICL_PLUGIN_URL . '/res/js/icl_reminders.js', array(), ICL_SITEPRESS_VERSION );
		}

		//if('content-translation' == $page_basename) {
		//    wp_enqueue_script('icl-sidebar-scripts', ICL_PLUGIN_URL . '/res/js/icl_sidebar.js', array(), ICL_SITEPRESS_VERSION);
		//}
		if ( 'languages' == $page_basename || 'string-translation' == $page_basename ) {
			wp_enqueue_script( 'colorpicker' );
			wp_enqueue_script( 'jquery-ui-sortable' );
		}
	}

	function js_load()
	{
		if ( is_admin() && !defined( 'DOING_AJAX' ) ) {
			if ( isset( $_GET[ 'page' ] ) ) {
				$page          = basename( $_GET[ 'page' ] );
				$page_basename = str_replace( '.php', '', $page );
			} else {
				$page_basename = false;
			}
			wp_enqueue_script( 'sitepress-scripts', ICL_PLUGIN_URL . '/res/js/scripts.js', array( 'jquery' ), ICL_SITEPRESS_VERSION );
			if ( isset( $page_basename ) && file_exists( ICL_PLUGIN_PATH . '/res/js/' . $page_basename . '.js' ) ) {
				$dependencies = array();
				switch ( $page_basename ) {
					case 'languages':
						$dependencies[ ] = 'colorpicker';
						break;
				}
				wp_enqueue_script( 'sitepress-' . $page_basename, ICL_PLUGIN_URL . '/res/js/' . $page_basename . '.js', $dependencies, ICL_SITEPRESS_VERSION );
			} else {
				wp_enqueue_script( 'translate-taxonomy', ICL_PLUGIN_URL . '/res/js/taxonomy-translation.js', array( 'jquery' ), ICL_SITEPRESS_VERSION );
			}

			if ( !wp_style_is( 'toolset-font-awesome', 'registered' ) ) { // check if styles are already registered
				wp_register_style( 'toolset-font-awesome', ICL_PLUGIN_URL . '/res/css/font-awesome.min.css', null, ICL_SITEPRESS_VERSION ); // register if not
			}
			wp_enqueue_style( 'toolset-font-awesome' ); // enqueue styles

		}
	}

	function front_end_js()
	{
		if ( defined( 'ICL_DONT_LOAD_LANGUAGES_JS' ) && ICL_DONT_LOAD_LANGUAGES_JS ) {
			return;
		}
		wp_register_script( 'sitepress', ICL_PLUGIN_URL . '/res/js/sitepress.js', false );
		wp_enqueue_script( 'sitepress' );

		$vars = array(
			'current_language' => $this->this_lang, 'icl_home' => $this->language_url(),
		);
		wp_localize_script( 'sitepress', 'icl_vars', $vars );
	}

	function js_scripts_categories()
	{
		wp_enqueue_script( 'sitepress-categories', ICL_PLUGIN_URL . '/res/js/categories.js', array(), ICL_SITEPRESS_VERSION );
	}

	function js_scripts_tags()
	{
		wp_enqueue_script( 'sitepress-tags', ICL_PLUGIN_URL . '/res/js/tags.js', array(), ICL_SITEPRESS_VERSION );
	}

	function rtl_fix()
	{
		global $wp_styles;
		if ( !empty( $wp_styles ) && $this->is_rtl() ) {
			$wp_styles->text_direction = 'rtl';
		}
	}

	function css_setup()
	{
		if ( isset( $_GET[ 'page' ] ) ) {
			$page          = basename( $_GET[ 'page' ] );
			$page_basename = str_replace( '.php', '', $page );
		}
		wp_enqueue_style( 'sitepress-style', ICL_PLUGIN_URL . '/res/css/style.css', array(), ICL_SITEPRESS_VERSION );
		if ( isset( $page_basename ) && file_exists( ICL_PLUGIN_PATH . '/res/css/' . $page_basename . '.css' ) ) {
			wp_enqueue_style( 'sitepress-' . $page_basename, ICL_PLUGIN_URL . '/res/css/' . $page_basename . '.css', array(), ICL_SITEPRESS_VERSION );
		}

		if ( is_admin() ) {
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_style( 'translate-taxonomy', ICL_PLUGIN_URL . '/res/css/taxonomy-translation.css', array(), ICL_SITEPRESS_VERSION );
		}

	}

	function transfer_icl_account( $create_account_and_transfer )
	{
		$user                     = $_POST[ 'user' ];
		$user[ 'site_id' ]        = $this->settings[ 'site_id' ];
		$user[ 'accesskey' ]      = $this->settings[ 'access_key' ];
		$user[ 'create_account' ] = $create_account_and_transfer ? '1' : '0';
		$icl_query                = new ICanLocalizeQuery();
		list( $success, $access_key ) = $icl_query->transfer_account( $user );
		if ( $success ) {
			$this->settings[ 'access_key' ] = $access_key;
			// set the support data the same.
			$this->settings[ 'support_access_key' ] = $access_key;
			$this->save_settings();

			return true;
		} else {
			$_POST[ 'icl_form_errors' ] = $access_key;

			return false;
		}
	}

	function process_forms()
	{
		global $wpdb;
		require_once ICL_PLUGIN_PATH . '/lib/Snoopy.class.php';
		require_once ICL_PLUGIN_PATH . '/lib/xml2array.php';
		require_once ICL_PLUGIN_PATH . '/lib/icl_api.php';

		if ( isset( $_POST[ 'icl_post_action' ] ) ) {
			switch ( $_POST[ 'icl_post_action' ] ) {
				case 'save_theme_localization':
					$locales = array();
					foreach ( $_POST as $k => $v ) {
						if ( 0 !== strpos( $k, 'locale_file_name_' ) || !trim( $v ) ) {
							continue;
						}
						$locales[ str_replace( 'locale_file_name_', '', $k ) ] = $v;
					}
					if ( !empty( $locales ) ) {
						$this->set_locale_file_names( $locales );
					}
					break;
			}
			return;
		}
		$create_account              = isset( $_POST[ 'icl_create_account_nonce' ] ) && $_POST[ 'icl_create_account_nonce' ] == wp_create_nonce( 'icl_create_account' );
		$create_account_and_transfer = isset( $_POST[ 'icl_create_account_and_transfer_nonce' ] ) && $_POST[ 'icl_create_account_and_transfer_nonce' ] == wp_create_nonce( 'icl_create_account_and_transfer' );
		$config_account              = isset( $_POST[ 'icl_configure_account_nonce' ] ) && $_POST[ 'icl_configure_account_nonce' ] == wp_create_nonce( 'icl_configure_account' );
		$create_support_account      = isset( $_POST[ 'icl_create_support_account_nonce' ] ) && $_POST[ 'icl_create_support_account_nonce' ] == wp_create_nonce( 'icl_create_support_account' );
		$config_support_account      = isset( $_POST[ 'icl_configure_support_account_nonce' ] ) && $_POST[ 'icl_configure_support_account_nonce' ] == wp_create_nonce( 'icl_configure_support_account' );
		$use_existing_account        = isset( $_POST[ 'icl_use_account_nonce' ] ) && $_POST[ 'icl_use_account_nonce' ] == wp_create_nonce( 'icl_use_account' );
		$transfer_to_account         = isset( $_POST[ 'icl_transfer_account_nonce' ] ) && $_POST[ 'icl_transfer_account_nonce' ] == wp_create_nonce( 'icl_transfer_account' );
		if ( $create_account || $config_account || $create_support_account || $config_support_account ) {
			if ( isset( $_POST[ 'icl_content_trans_setup_back_2' ] ) ) {
				// back button in wizard mode.
				$this->settings[ 'content_translation_setup_wizard_step' ] = 2;
				$this->save_settings();

			} else {
				$user                     = $_POST[ 'user' ];
				$user[ 'create_account' ] = ( isset( $_POST[ 'icl_create_account_nonce' ] ) || isset( $_POST[ 'icl_create_support_account_nonce' ] ) ) ? 1 : 0;
				$user[ 'platform_kind' ]  = 2;
				$user[ 'cms_kind' ]       = 1;
				$user[ 'blogid' ]         = $wpdb->blogid ? $wpdb->blogid : 1;
				$user[ 'url' ]            = get_home_url();
				$user[ 'title' ]          = get_option( 'blogname' );
				$user[ 'description' ]    = $this->settings[ 'icl_site_description' ];
				$user[ 'is_verified' ]    = 1;

				if ( $user[ 'create_account' ] && defined( 'ICL_AFFILIATE_ID' ) && defined( 'ICL_AFFILIATE_KEY' ) ) {
					$user[ 'affiliate_id' ]  = ICL_AFFILIATE_ID;
					$user[ 'affiliate_key' ] = ICL_AFFILIATE_KEY;
				}

				$user[ 'interview_translators' ] = $this->settings[ 'interview_translators' ];
				$user[ 'project_kind' ]          = $this->settings[ 'website_kind' ];
				/*
				 if(is_null($user['project_kind']) || $user['project_kind']==''){
					$_POST['icl_form_errors'] = __('Please select the kind of website','sitepress');
					return;
				}
				*/
				$user[ 'pickup_type' ] = intval( $this->settings[ 'translation_pickup_method' ] );

				$notifications = 0;
				if ( $this->settings[ 'icl_notify_complete' ] ) {
					$notifications += 1;
				}
				if ( $this->settings[ 'alert_delay' ] ) {
					$notifications += 2;
				}
				$user[ 'notifications' ] = $notifications;

				// prepare language pairs

				$pay_per_use = $this->settings[ 'translator_choice' ] == 1;

				$language_pairs = $this->settings[ 'language_pairs' ];
				$lang_pairs     = array();
				if ( isset( $language_pairs ) ) {
					foreach ( $language_pairs as $k => $v ) {
						$english_fr = $wpdb->get_var( "SELECT english_name FROM {$wpdb->prefix}icl_languages WHERE code='{$k}' " );
						$pay_per_use_increment = 0;
						foreach ( $v as $k => $v ) {
							$pay_per_use_increment++;
							$english_to                            = $wpdb->get_var( "SELECT english_name FROM {$wpdb->prefix}icl_languages WHERE code='{$k}' " );
							$lang_pairs[ 'from_language' . $pay_per_use_increment ] = ICL_Pro_Translation::server_languages_map( $english_fr );
							$lang_pairs[ 'to_language' . $pay_per_use_increment ]   = ICL_Pro_Translation::server_languages_map( $english_to );
							if ( $pay_per_use ) {
								$lang_pairs[ 'pay_per_use' . $pay_per_use_increment ] = 1;
							}
						}
					}
				}
				$icl_query = new ICanLocalizeQuery();
				list( $site_id, $access_key ) = $icl_query->createAccount( array_merge( $user, $lang_pairs ) );

				if ( !$site_id ) {
					$user[ 'pickup_type' ] = ICL_PRO_TRANSLATION_PICKUP_POLLING;
					list( $site_id, $access_key ) = $icl_query->createAccount( array_merge( $user, $lang_pairs ) );
				}

				if ( !$site_id ) {

					if ( $access_key ) {
						$_POST[ 'icl_form_errors' ] = $access_key;
					} else {
						$_POST[ 'icl_form_errors' ] = __( 'An unknown error has occurred when communicating with the ICanLocalize server. Please try again.', 'sitepress' );
						// We will force the next try to be http.
						update_option( '_force_mp_post_http', 1 );
					}
				} else {
					if ( $create_account || $config_account ) {
						$iclsettings[ 'site_id' ]           = $site_id;
						$iclsettings[ 'access_key' ]        = $access_key;
						$iclsettings[ 'icl_account_email' ] = $user[ 'email' ];
						// set the support data the same.
						$iclsettings[ 'support_site_id' ]           = $site_id;
						$iclsettings[ 'support_access_key' ]        = $access_key;
						$iclsettings[ 'support_icl_account_email' ] = $user[ 'email' ];
					} else {
						$iclsettings[ 'support_site_id' ]           = $site_id;
						$iclsettings[ 'support_access_key' ]        = $access_key;
						$iclsettings[ 'support_icl_account_email' ] = $user[ 'email' ];
					}
					if ( isset( $user[ 'pickup_type' ] ) && $user[ 'pickup_type' ] == ICL_PRO_TRANSLATION_PICKUP_POLLING ) {
						$iclsettings[ 'translation_pickup_method' ] = ICL_PRO_TRANSLATION_PICKUP_POLLING;
					}
					$this->save_settings( $iclsettings );
					if ( $user[ 'create_account' ] == 1 ) {
						$_POST[ 'icl_form_success' ] = __( 'A project on ICanLocalize has been created.', 'sitepress' ) . '<br />';

					} else {
						$_POST[ 'icl_form_success' ] = __( 'Project added', 'sitepress' );
					}
					$this->get_icl_translator_status( $iclsettings );
					$this->save_settings( $iclsettings );

				}

				if ( !$create_support_account && !$config_support_account && intval( $site_id ) > 0 && $access_key && $this->settings[ 'content_translation_setup_complete' ] == 0 && $this->settings[ 'content_translation_setup_wizard_step' ] == 3 && !isset( $_POST[ 'icl_form_errors' ] ) ) {
					// we are running the wizard, so we can finish it now.
					$this->settings[ 'content_translation_setup_complete' ]    = 1;
					$this->settings[ 'content_translation_setup_wizard_step' ] = 0;
					$this->save_settings();

				}

			}
		} elseif ( $use_existing_account || $transfer_to_account || $create_account_and_transfer ) {

			if ( isset( $_POST[ 'icl_content_trans_setup_back_2' ] ) ) {
				// back button in wizard mode.
				$this->settings[ 'content_translation_setup_wizard_step' ] = 2;
				$this->save_settings();
			} else {
				if ( $transfer_to_account ) {
					$_POST[ 'user' ][ 'email' ] = $_POST[ 'user' ][ 'email2' ];
				}
				// we will be using the support account for the icl_account
				$this->settings[ 'site_id' ]           = $this->settings[ 'support_site_id' ];
				$this->settings[ 'access_key' ]        = $this->settings[ 'support_access_key' ];
				$this->settings[ 'icl_account_email' ] = $this->settings[ 'support_icl_account_email' ];

				$this->save_settings();

				if ( $transfer_to_account || $create_account_and_transfer ) {
					if ( !$this->transfer_icl_account( $create_account_and_transfer ) ) {
						return;
					}

				}

				// we are running the wizard, so we can finish it now.
				$this->settings[ 'content_translation_setup_complete' ]    = 1;
				$this->settings[ 'content_translation_setup_wizard_step' ] = 0;
				$this->save_settings();

				$iclsettings[ 'site_id' ]    = $this->settings[ 'site_id' ];
				$iclsettings[ 'access_key' ] = $this->settings[ 'access_key' ];
				$this->get_icl_translator_status( $iclsettings );
				$this->save_settings( $iclsettings );


			}
		} elseif ( isset( $_POST[ 'icl_initial_languagenonce' ] ) && $_POST[ 'icl_initial_languagenonce' ] == wp_create_nonce( 'icl_initial_language' ) ) {

			$this->prepopulate_translations( $_POST[ 'icl_initial_language_code' ] );
			$wpdb->update( $wpdb->prefix . 'icl_languages', array( 'active' => '1' ), array( 'code' => $_POST[ 'icl_initial_language_code' ] ) );
			$blog_default_cat        = get_option( 'default_category' );
			$blog_default_cat_tax_id = $wpdb->get_var( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id='{$blog_default_cat}' AND taxonomy='category'" );

			if ( isset( $_POST[ 'save_one_language' ] ) ) {
				$this->settings[ 'setup_wizard_step' ] = 0;
				$this->settings[ 'setup_complete' ]    = 1;
			} else {
				$this->settings[ 'setup_wizard_step' ] = 2;
			}

			$this->settings[ 'default_categories' ]                 = array( $_POST[ 'icl_initial_language_code' ] => $blog_default_cat_tax_id );
			$this->settings[ 'existing_content_language_verified' ] = 1;
			$this->settings[ 'default_language' ]                   = $_POST[ 'icl_initial_language_code' ];
			$this->settings[ 'admin_default_language' ]             = $this->admin_language = $_POST[ 'icl_initial_language_code' ];

			// set the locale in the icl_locale_map (if it's not set)
			if ( !$wpdb->get_var( "SELECT code FROM {$wpdb->prefix}icl_locale_map WHERE code='{$_POST['icl_initial_language_code']}'" ) ) {
				$default_locale = $wpdb->get_var( "SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE code='{$_POST['icl_initial_language_code']}'" );
				if ( $default_locale ) {
					$wpdb->insert( $wpdb->prefix . 'icl_locale_map', array( 'code' => $_POST[ 'icl_initial_language_code' ], 'locale' => $default_locale ) );

				}
			}

			$this->save_settings();
			global $sitepress_settings;
			$sitepress_settings = $this->settings;
			$this->get_active_languages( true ); //refresh active languages list
			do_action( 'icl_initial_language_set' );
		} elseif ( isset( $_POST[ 'icl_language_pairs_formnounce' ] ) && $_POST[ 'icl_language_pairs_formnounce' ] == wp_create_nonce( 'icl_language_pairs_form' ) ) {
			$this->save_language_pairs();

			$this->settings[ 'content_translation_languages_setup' ] = 1;
			// Move onto the site description page
			$this->settings[ 'content_translation_setup_wizard_step' ] = 2;

			$this->settings[ 'website_kind' ]          = 2;
			$this->settings[ 'interview_translators' ] = 1;

			$this->save_settings();

		} elseif ( isset( $_POST[ 'icl_site_description_wizardnounce' ] ) && $_POST[ 'icl_site_description_wizardnounce' ] == wp_create_nonce( 'icl_site_description_wizard' ) ) {
			if ( isset( $_POST[ 'icl_content_trans_setup_back_2' ] ) ) {
				// back button.
				$this->settings[ 'content_translation_languages_setup' ]   = 0;
				$this->settings[ 'content_translation_setup_wizard_step' ] = 1;
				$this->save_settings();
			} elseif ( isset( $_POST[ 'icl_content_trans_setup_next_2' ] ) || isset( $_POST[ 'icl_content_trans_setup_next_2_enter' ] ) ) {
				// next button.
				$description = $_POST[ 'icl_description' ];
				if ( $description == "" ) {
					$_POST[ 'icl_form_errors' ] = __( 'Please provide a short description of the website so that translators know what background is required from them.', 'sitepress' );
				} else {
					$this->settings[ 'icl_site_description' ]                  = $description;
					$this->settings[ 'content_translation_setup_wizard_step' ] = 3;
					$this->save_settings();
				}
			}
		}
	}

	function prepopulate_translations( $lang )
	{
		global $wpdb;
		if ( !empty( $this->settings[ 'existing_content_language_verified' ] ) ) {
			return;
		}

		$this->icl_translations_cache->clear();

		// case of icl_sitepress_settings accidentally lost
		// if there's at least one translation do not initialize the languages for elements
		$one_translation = $wpdb->get_var( $wpdb->prepare( "SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE language_code<>%s", $lang ) );
		if ( $one_translation ) {
			return;
		}

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}icl_translations" );
		$wpdb->query( $wpdb->prepare("
            INSERT INTO {$wpdb->prefix}icl_translations(element_type, element_id, trid, language_code, source_language_code)
            SELECT CONCAT('post_',post_type), ID, ID, %s, NULL FROM {$wpdb->posts} WHERE post_status IN ('draft', 'publish','schedule','future','private', 'pending')
            ", $lang) );

		$maxtrid = 1 + $wpdb->get_var( "SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations" );

		global $wp_taxonomies;
		$taxonomies = array_keys( (array)$wp_taxonomies );
		foreach ( $taxonomies as $tax ) {
			$wpdb->query( $wpdb->prepare("
                INSERT INTO {$wpdb->prefix}icl_translations(element_type, element_id, trid, language_code, source_language_code)
                SELECT 'tax_" . $tax . "', term_taxonomy_id, {$maxtrid}+term_taxonomy_id, %s, NULL FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s
                ", $lang, $tax) );
			$maxtrid = 1 + $wpdb->get_var( "SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations" );
		}

		$wpdb->query( $wpdb->prepare( "
            INSERT INTO {$wpdb->prefix}icl_translations(element_type, element_id, trid, language_code, source_language_code)
            SELECT 'comment', comment_ID, {$maxtrid}+comment_ID, %s, NULL FROM {$wpdb->comments}
            ", $lang) );
	}

	function post_edit_language_options()
	{
		global $post, $iclTranslationManagement, $post_new_file, $post_type_object;

		if(!isset($post)) return;

		if ( !function_exists( 'post_type_supports' ) || post_type_supports( $post->post_type, 'editor' ) ) {
			add_action( 'icl_post_languages_options_after', array( $this, 'copy_from_original' ) );
		}

		if ( current_user_can( 'manage_options' ) ) {
			add_meta_box( 'icl_div_config', __( 'Multilingual Content Setup', 'sitepress' ), array( $this, 'meta_box_config' ), $post->post_type, 'normal', 'low' );
		}

		if ( isset( $_POST[ 'icl_action' ] ) && $_POST[ 'icl_action' ] == 'icl_mcs_inline' ) {

			if ( !in_array( $_POST[ 'custom_post' ], array( 'post', 'page' ) ) ) {
				$iclsettings[ 'custom_posts_sync_option' ][ $_POST[ 'custom_post' ] ] = @intval( $_POST[ 'translate' ] );
				if ( @intval( $_POST[ 'translate' ] ) ) {
					$this->verify_post_translations( $_POST[ 'custom_post' ] );
				}
			}

			if ( !empty( $_POST[ 'custom_taxs_off' ] ) ) {
				foreach ( $_POST[ 'custom_taxs_off' ] as $off ) {
					$iclsettings[ 'taxonomies_sync_option' ][ $off ] = 0;
				}
			}

			if ( !empty( $_POST[ 'custom_taxs_on' ] ) ) {
				foreach ( $_POST[ 'custom_taxs_on' ] as $on ) {
					$iclsettings[ 'taxonomies_sync_option' ][ $on ] = 1;
					$this->verify_taxonomy_translations( $on );
				}
			}

			if ( !empty( $_POST[ 'cfnames' ] ) ) {
				foreach ( $_POST[ 'cfnames' ] as $k => $v ) {
					$custom_field_name                                                                       = base64_decode( $v );
					$iclTranslationManagement->settings[ 'custom_fields_translation' ][ $custom_field_name ] = @intval( $_POST[ 'cfvals' ][ $k ] );
					$iclTranslationManagement->save_settings();

					// sync the custom fields for the current post
					if ( 1 == @intval( $_POST[ 'cfvals' ][ $k ] ) ) {

						$trid         = $this->get_element_trid( $_POST[ 'post_id' ], 'post_' . $_POST[ 'custom_post' ] );
						$translations = $this->get_element_translations( $trid, 'post_' . $_POST[ 'custom_post' ] );

						// determine original post id
						$original_post_id = false;
						foreach ( $translations as $t ) {
							if ( $t->original ) {
								$original_post_id = $t->element_id;
								break;
							}
						}

						// get a list of $custom_field_name values that the original document has
						$custom_fields_copy = get_post_meta( $original_post_id, $custom_field_name );

						foreach ( $translations as $t ) {

							if ( $t->original ) {
								continue;
							}

							// if none, then attempt to delete any that the tranlations would have
							if ( empty( $custom_fields_copy ) ) {
								delete_post_meta( $t->element_id, $custom_field_name );
							} else {

								// get a list of $custom_field_name values that the translated document has
								$translation_cfs = get_post_meta( $t->element_id, $custom_field_name );

								// see what elements have been deleted in the original document
								$deleted_fields = $translation_cfs;
								foreach ( $custom_fields_copy as $cfc ) {
									$tc_key = array_search( $cfc, $translation_cfs );
									if ( $tc_key !== false ) {
										unset( $deleted_fields[ $tc_key ] );
									}
								}

								if ( !empty( $deleted_fields ) ) {
									foreach ( $deleted_fields as $meta_value ) {
										delete_post_meta( $t->element_id, $custom_field_name, $meta_value );
									}
								}

								// update each custom field in the translated document
								foreach ( $custom_fields_copy as $meta_value ) {
									if ( !in_array( $meta_value, $translation_cfs ) ) {
										// if it doesn't exist, add
										add_post_meta( $t->element_id, $custom_field_name, $meta_value );
									}
								}
							}
						}
					}
				}
			}

			if ( !empty( $iclsettings ) ) {
				$this->save_settings( $iclsettings );
			}

		}

		$post_types = array_keys( $this->get_translatable_documents() );
		if ( in_array( $post->post_type, $post_types ) ) {
			add_meta_box( 'icl_div', __( 'Language', 'sitepress' ), array( $this, 'meta_box' ), $post->post_type, 'side', 'high' );
		}

		//Fix the "Add new" button adding the language argument, so to create new content in the same language
		if(isset($post_new_file) && isset($post_type_object) && $this->is_translated_post_type($post_type_object->name)) {
			$post_language = $this->get_language_for_element($post->ID, 'post_' . $post_type_object->name);
			$post_new_file = add_query_arg(array('lang' => $post_language), $post_new_file);
		}
	}

	/**
	 * @param int         $el_id
	 * @param string      $el_type
	 * @param int         $trid
	 * @param string      $language_code
	 * @param null|string $src_language_code
	 * @param bool        $check_duplicates
	 *
	 * @return bool|int|null|string
	 */
	function set_element_language_details( $el_id, $el_type = 'post_post', $trid, $language_code, $src_language_code = null, $check_duplicates = true )
	{
		global $wpdb;

		// Do not set language for the front page, if defined
		$is_root_page = isset( $sitepress_settings[ 'urls' ][ 'root_page' ] ) && $sitepress_settings[ 'urls' ][ 'root_page' ] == $el_id;
		if ( $is_root_page ) {
			$trid = $this->get_element_trid($el_id, $el_type);
			if($trid) {
				$this->delete_element_translation($trid, $el_type);
			}
			return false;
		}
		// special case for posts and taxonomies
		// check if a different record exists for the same ID
		// if it exists don't save the new element and get out
		if ( $check_duplicates && $el_id ) {
			$exp   = explode( '_', $el_type );
			$_type = $exp[ 0 ];
			if ( in_array( $_type, array( 'post', 'tax' ) ) ) {
				$_el_exists = $wpdb->get_var( "
                    SELECT translation_id FROM {$wpdb->prefix}icl_translations
                    WHERE element_id={$el_id} AND element_type <> '{$el_type}' AND element_type LIKE '{$_type}\\_%'" );
				if ( $_el_exists ) {
					trigger_error( 'Element ID already exists with a different type', E_USER_NOTICE );

					return false;
				}
			}
		}

		if ( $trid ) { // it's a translation of an existing element

			// check whether we have an orphan translation - the same trid and language but a different element id
			$translation_id = $wpdb->get_var( "
                SELECT translation_id FROM {$wpdb->prefix}icl_translations
                WHERE   trid = '{$trid}'
                    AND language_code = '{$language_code}'
                    AND element_id <> '{$el_id}'
            " );

			if ( $translation_id ) {
				$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id={$translation_id}" );
				$this->icl_translations_cache->clear();
			}

			if ( !is_null( $el_id ) && $translation_id = $wpdb->get_var( "SELECT translation_id FROM {$wpdb->prefix}icl_translations
                WHERE element_type='{$el_type}' AND element_id='{$el_id}' AND trid='{$trid}' AND element_id IS NOT NULL" )
			) {
				//case of language change
				$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'language_code' => $language_code ), array( 'translation_id' => $translation_id ) );
			} elseif ( !is_null( $el_id ) && $translation_id = $wpdb->get_var( "SELECT translation_id FROM {$wpdb->prefix}icl_translations
                WHERE element_type='{$el_type}' AND element_id='{$el_id}' AND element_id IS NOT NULL " )
			) {
				//case of changing the "translation of"
				if ( empty( $src_language_code ) ) {
					$src_language_code = $wpdb->get_var( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE trid={$trid} AND source_language_code IS NULL" );
				}
				$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'trid' => $trid, 'language_code' => $language_code, 'source_language_code' => $src_language_code ), array( 'element_type' => $el_type, 'element_id' => $el_id ) );
				$this->icl_translations_cache->clear();
			} elseif ( $translation_id = $wpdb->get_var( $wpdb->prepare( "
                SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND language_code='%s' AND element_id IS NULL", $trid, $language_code ) )
			) {
				$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'element_id' => $el_id ), array( 'translation_id' => $translation_id ) );
			} else {
				//get source
				if ( empty( $src_language_code ) ) {
					$src_language_code = $wpdb->get_var( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE trid={$trid} AND source_language_code IS NULL" );
				}

				// case of adding a new language
				$new = array(
					'trid' => $trid, 'element_type' => $el_type, 'language_code' => $language_code, 'source_language_code' => $src_language_code
				);
				if ( $el_id ) {
					$new[ 'element_id' ] = $el_id;
				}
				$wpdb->insert( $wpdb->prefix . 'icl_translations', $new );
				$translation_id = $wpdb->insert_id;
				$this->icl_translations_cache->clear();

			}
		} else { // it's a new element or we are removing it from a trid
			if ( $translation_id = $wpdb->get_var( "
                    SELECT translation_id
                    FROM {$wpdb->prefix}icl_translations WHERE element_type='{$el_type}' AND element_id='{$el_id}' AND element_id IS NOT NULL" )
			) {
				$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id={$translation_id}" );
				$this->icl_translations_cache->clear();
			}

			$trid = 1 + $wpdb->get_var( "SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations" );

			$new = array(
				'trid' => $trid, 'element_type' => $el_type, 'language_code' => $language_code
			);
			if ( $el_id ) {
				$new[ 'element_id' ] = $el_id;
			}

			$wpdb->insert( $wpdb->prefix . 'icl_translations', $new );
			$translation_id = $wpdb->insert_id;
		}

		return $translation_id;
	}

	function delete_element_translation( $trid, $el_type, $language_code = false )
	{
		global $wpdb;
		$trid    = intval( $trid );
		$el_type = esc_sql( $el_type );
		$where   = '';

		$delete_args = array($trid, $el_type);
		if ( $language_code ) {
			$where .= " AND language_code=%s";
			$delete_args[] = $language_code;
		}
		$delete_sql = "DELETE FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND element_type=%s" . $where;
		$delete_sql_prepared = $wpdb->prepare($delete_sql, $delete_args);
		$wpdb->query( $delete_sql_prepared );
		$this->icl_translations_cache->clear();
	}

	function get_element_language_details( $el_id, $el_type = 'post_post' )
	{
		$cache_key = $el_id . ':' .  $el_type;
		$cache_group = 'element_language_details';
		$cached_details = wp_cache_get($cache_key, $cache_group);
		if($cached_details) return $cached_details;

		global $wpdb;
		static $pre_load_done = false;
		if ( !$pre_load_done && !ICL_DISABLE_CACHE ) {
			// search previous queries for a group of posts
			foreach ( $this->queries as $query ) {
				$pos = strstr( $query, 'post_id IN (' );
				if ( $pos !== false ) {
					$group = substr( $pos, 10 );
					$group = substr( $group, 0, strpos( $group, ')' ) + 1 );

					$query = "SELECT element_id, trid, language_code, source_language_code
                        FROM {$wpdb->prefix}icl_translations
                        WHERE element_id IN {$group} AND element_type='{$el_type}'";
					$ret   = $wpdb->get_results( $query );
					foreach ( $ret as $details ) {
						if ( isset( $this->icl_translations_cache ) ) {
							$this->icl_translations_cache->set( $details->element_id . $el_type, $details );
						}
					}

					// get the taxonomy for the posts for later use
					// categories first
					$query = "SELECT DISTINCT(tr.term_taxonomy_id), tt.term_id, tt.taxonomy, icl.trid, icl.language_code, icl.source_language_code
                        FROM {$wpdb->prefix}term_relationships as tr
                        LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt
                        ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        LEFT JOIN {$wpdb->prefix}icl_translations as icl ON tr.term_taxonomy_id = icl.element_id
                        WHERE tr.object_id IN {$group}
                        AND (icl.element_type='tax_category' and tt.taxonomy='category')
                        ";
					$query .= "UNION
                    ";
					$query .= "SELECT DISTINCT(tr.term_taxonomy_id), tt.term_id, tt.taxonomy, icl.trid, icl.language_code, icl.source_language_code
                        FROM {$wpdb->prefix}term_relationships as tr
                        LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt
                        ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        LEFT JOIN {$wpdb->prefix}icl_translations as icl ON tr.term_taxonomy_id = icl.element_id
                        WHERE tr.object_id IN {$group}
                        AND (icl.element_type='tax_post_tag' and tt.taxonomy='post_tag')";
					global $wp_taxonomies;
					$custom_taxonomies = array_diff( array_keys( $wp_taxonomies ), array( 'post_tag', 'category', 'link_category' ) );
					if ( !empty( $custom_taxonomies ) ) {
						foreach ( $custom_taxonomies as $tax ) {
							$query .= " UNION
                                SELECT DISTINCT(tr.term_taxonomy_id), tt.term_id, tt.taxonomy, icl.trid, icl.language_code, icl.source_language_code
                                FROM {$wpdb->prefix}term_relationships as tr
                                LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt
                                ON tr.term_taxonomy_id = tt.term_taxonomy_id
                                LEFT JOIN {$wpdb->prefix}icl_translations as icl ON tr.term_taxonomy_id = icl.element_id
                                WHERE tr.object_id IN {$group}
                                AND (icl.element_type='tax_{$tax}' and tt.taxonomy='{$tax}')";
						}
					}
					$ret = $wpdb->get_results( $query );

					foreach ( $ret as $details ) {
						// save language details
						$lang_details                       = new stdClass();
						$lang_details->trid                 = $details->trid;
						$lang_details->language_code        = $details->language_code;
						$lang_details->source_language_code = $details->source_language_code;
						if ( isset( $this->icl_translations_cache ) ) {
							$this->icl_translations_cache->set( $details->term_taxonomy_id . 'tax_' . $details->taxonomy, $lang_details );
							// save the term taxonomy
							$this->icl_term_taxonomy_cache->set( 'category_' . $details->term_id, $details->term_taxonomy_id );
						}
					}
					break;
				}
			}
			$pre_load_done = true;
		}

		if ( isset( $this->icl_translations_cache ) && $this->icl_translations_cache->has_key( $el_id . $el_type ) ) {
			return $this->icl_translations_cache->get( $el_id . $el_type );
		}

		$details_prepared_sql = $wpdb->prepare( "
            SELECT trid, language_code, source_language_code
            FROM {$wpdb->prefix}icl_translations
            WHERE element_id=%d AND element_type=%s", array( $el_id, $el_type ) );

		$details = $wpdb->get_row( $details_prepared_sql );
		if ( isset( $this->icl_translations_cache ) ) {
			$this->icl_translations_cache->set( $el_id . $el_type, $details );
		}

		wp_cache_add($cache_key, $details, $cache_group);

		return $details;
	}


	//if set option "When deleting a taxonomy (category, tag or custom), delete translations as well"
	//when editing post and deleting tag or category or custom taxonomy in original language then delete this taxonomy in other languages
	function deleted_term_relationships( $object_id, $delete_terms )
	{
		global $wpdb;
		if ( $this->settings[ 'sync_post_taxonomies' ] ) {
			$post = get_post( $object_id );
			$trid = $this->get_element_trid( $object_id, 'post_' . $post->post_type );
			if ( $trid ) {
				$translations = $this->get_element_translations( $trid, 'post_' . $post->post_type );
				foreach ( $translations as $translation ) {
					if ( $translation->original == 1 && $translation->element_id == $object_id ) {
						$taxonomies = get_object_taxonomies( $post->post_type );
						foreach ( $taxonomies as $taxonomy ) {
							foreach ( $delete_terms as $delete_term ) {
								$trid = $this->get_element_trid( $delete_term, 'tax_' . $taxonomy );
								if ( $trid ) {
									$tags = $this->get_element_translations( $trid, 'tax_' . $taxonomy );
									foreach ( $tags as $tag ) {
										if ( !$tag->original && isset( $translations[ $tag->language_code ] ) ) {
											$translated_post = $translations[ $tag->language_code ];
											$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id = $tag->element_id", $translated_post->element_id ) );
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}

	function save_post_actions( $pidd, $post )
	{
		global $wpdb;

		wp_defer_term_counting( true );

		list( $post_type, $post_status ) = $wpdb->get_row( "SELECT post_type, post_status FROM {$wpdb->posts} WHERE ID = " . $pidd, ARRAY_N );

		// exceptions
		if ( !$this->is_translated_post_type( $post_type ) || (isset( $post) && $post->post_status == "auto-draft" ) || isset( $_POST[ 'autosave' ] ) || isset( $_POST[ 'skip_sitepress_actions' ] ) || ( isset( $_POST[ 'post_ID' ] ) && $_POST[ 'post_ID' ] != $pidd ) || ( isset( $_POST[ 'post_type' ] ) && $_POST[ 'post_type' ] == 'revision' ) || $post_type == 'revision' || get_post_meta( $pidd, '_wp_trash_meta_status', true ) || ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'untrash' )) {
			wp_defer_term_counting( false );

			return;
		}

		$default_language = $this->get_default_language();

		if(!isset($post) && $pidd) {
			$post = get_post($pidd);
		}
		// exception for auto-drafts - setting the right language

		// allow post arguments to be passed via wp_insert_post directly and not be expected on $_POST exclusively
		$post_vars = (array)$_POST;
		foreach ( (array)$post as $k => $v ) {
			$post_vars[ $k ] = $v;
		}

		if ( !isset( $post_vars[ 'post_type' ] ) ) {
			$post_vars[ 'post_type' ] = $post_type;
		}

		$element_type = 'post_' . $post_type;
		$language_code = false;
		if ( isset( $post_vars[ 'action' ] ) && $post_vars[ 'action' ] == 'post-quickpress-publish' ) {
			$post_id       = $pidd;
			$language_code = $default_language;
		} elseif ( isset( $_GET[ 'bulk_edit' ] ) ) {
			$post_id = $pidd;
		} else {
			$post_id = isset( $post_vars[ 'post_ID' ] ) ? $post_vars[ 'post_ID' ] : $pidd; //latter case for XML-RPC publishing

			if ( isset( $post_vars[ 'icl_post_language' ] ) ) {
				$language_code = $post_vars[ 'icl_post_language' ];
			} elseif ( isset( $_GET[ 'lang' ] ) ) {
				$language_code = $_GET[ 'lang' ];
			} elseif ( $_ldet = $this->get_element_language_details( $post_id, $element_type ) ) {
				$language_code = $_ldet->language_code;
			} else {
				$language_code = $default_language; //latter case for XML-RPC publishing
			}
		}

		$source_language = $default_language;
		if ( isset( $post_vars[ 'action' ] ) && $post_vars[ 'action' ] == 'inline-save' || isset( $_GET[ 'bulk_edit' ] ) || isset( $_GET[ 'doing_wp_cron' ] ) || ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'untrash' ) ) {
			$res = $this->get_element_language_details($post_id, 'post_' . $post->post_type);
			if(!isset($res) || !$res) return;
			$language_code = $res->language_code;
			$trid = $res->trid;
		} else {
			if ( isset( $post_vars[ 'icl_trid' ] ) ) {
				$trid = @intval( $post_vars[ 'icl_trid' ] );
			} elseif ( isset( $_GET[ 'trid' ] ) ) {
				$trid = @intval( $_GET[ 'trid' ] );
			} else {
				$trid = $this->get_element_trid( $post_id, 'post_' . $post->post_type );
			}

			// see if we have a "translation of" setting.
			if ( isset( $post_vars[ 'icl_translation_of' ] ) ) {
				if ( is_numeric( $post_vars[ 'icl_translation_of' ] ) ) {
					$translation_of_data_prepared = $wpdb->prepare( "SELECT trid, language_code FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $post_vars[ 'icl_translation_of' ], 'post_' . $post->post_type );
					list($trid, $source_language) = $wpdb->get_row( $translation_of_data_prepared, 'ARRAY_N' );
				} else {
					if ( empty( $post_vars[ 'icl_trid' ] ) ) {
						$trid = null;
					}
				}
			}
		}

		if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'inline-save' ) {
			$trid = $this->get_element_trid( $post_id, 'post_' . $post_type );
		} else if ( isset( $post_vars[ 'icl_translation_of' ] ) && $post_vars[ 'icl_translation_of' ] == 'none' ) {
			$trid = null;
		}

		// set trid if front-end translation creating
		$trid = apply_filters( 'wpml_save_post_trid_value', $trid, $post_status );
		// set post language if front-end translation creating
		$language_code = apply_filters( 'wpml_save_post_lang', $language_code );


		$translation_id = $this->set_element_language_details( $post_id, $element_type, $trid, $language_code, $source_language );
		//get trid of $translation_id
		$translated_id_trid = $wpdb->get_var( $wpdb->prepare( "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE translation_id=%d", array( $translation_id) ) );
		//get all translations
		$translated_element_id = $wpdb->get_col( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d", $translated_id_trid ) );


		if ( !in_array( $post_type, array( 'post', 'page' ) ) && !$this->is_translated_post_type( $post_type ) ) {
			wp_defer_term_counting( false );

			return;
		}

		// synchronize the page order for translations
		if ( $trid && $this->settings[ 'sync_page_ordering' ] && $translated_element_id && is_array($translated_element_id)) {
			$menu_order = esc_sql( $post_vars[ 'menu_order' ] );
			$wpdb->query( "UPDATE {$wpdb->posts} SET menu_order={$menu_order} WHERE ID IN (" . join( ',', $translated_element_id ) . ")" );
		}

			// synchronize the page parent for translations
		if ( $trid && $this->settings[ 'sync_page_parent' ] ) {
      $original_id = $this->get_original_element_id($post_id, 'post_' . $post_vars[ 'post_type' ]);
      if ($original_id == $post_id) {
        $translations = $this->get_element_translations( $trid, 'post_' . $post_vars[ 'post_type' ] );
        foreach ( $translations as $target_lang => $target_details ) {
          if ( $target_lang != $language_code ) {
            if ( $target_details->element_id ) {
              $this->fix_translated_parent( $post_id, $target_details->element_id, $target_lang);
            }
          }
        }
      }
			
		}

		// synchronize the page template
		if ( isset( $post_vars[ 'page_template' ] ) && $trid && $post_vars[ 'post_type' ] == 'page' && $this->settings[ 'sync_page_template' ] ) {
			if ( $translated_element_id && is_array($translated_element_id) ) {
				foreach ( $translated_element_id as $tp ) {
					if ( $tp != $post_id ) {
						update_post_meta( $tp, '_wp_page_template', $post_vars[ 'page_template' ] );
					}
				}
			}
		}

		// synchronize comment and ping status
		if ( $trid && ( $this->settings[ 'sync_ping_status' ] || $this->settings[ 'sync_comment_status' ] ) ) {
			$arr = array();
			if ( $this->settings[ 'sync_comment_status' ] ) {
				$arr[ 'comment_status' ] = $post_vars[ 'comment_status' ];
			}
			if ( $this->settings[ 'sync_ping_status' ] ) {
				$arr[ 'ping_status' ] = $post_vars[ 'ping_status' ];
			}
			if ( !empty( $arr ) &&  $translated_element_id && is_array($translated_element_id) ) {
				foreach ( $translated_element_id as $tp ) {
					if ( $tp != $post_id ) {
						$wpdb->update( $wpdb->posts, $arr, array( 'ID' => $tp ) );
					}
				}
			}
		}

		// copy custom fields from original
		$translations = $this->get_element_translations( $trid, 'post_' . $post_vars[ 'post_type' ] );
		if ( !empty( $translations ) ) {
			$original_post_id = false;
			foreach ( $translations as $t ) {
				if ( $t->original ) {
					$original_post_id = $t->element_id;
					break;
				}
			}

			// this runs only for translated documents
			if ( $post_id != $original_post_id ) {
				$this->copy_custom_fields( $original_post_id, $post_id );
			} else {
				foreach ( $translations as $t ) {
					if ( $original_post_id != $t->element_id ) {
						$this->copy_custom_fields( $original_post_id, $t->element_id );
					}
				}
			}
		}

		//sync posts stickiness
		if ( isset( $post_vars[ 'post_type' ] ) && $post_vars[ 'post_type' ] == 'post' && isset( $post_vars[ 'action' ] ) && $post_vars[ 'action' ] != 'post-quickpress-publish' && $this->settings[ 'sync_sticky_flag' ] ) { //not for quick press
			remove_filter( 'option_sticky_posts', array( $this, 'option_sticky_posts' ) ); // remove filter used to get language relevant stickies. get them all
			$sticky_posts = get_option( 'sticky_posts' );
			add_filter( 'option_sticky_posts', array( $this, 'option_sticky_posts' ) ); // add filter back
			// get ids of the translations
			if ( $trid ) {
				$translations = $wpdb->get_col( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d", $trid ) );
			} else {
				$translations = array();
			}
			if ( isset( $post_vars[ 'sticky' ] ) && $post_vars[ 'sticky' ] == 'sticky' ) {
				$sticky_posts = array_unique( array_merge( $sticky_posts, $translations ) );
			} else {
				//makes sure translations are not set to sticky if this posts switched from sticky to not-sticky
				$sticky_posts = array_diff( $sticky_posts, $translations );
			}
			update_option( 'sticky_posts', $sticky_posts );
		}

		//sync private flag
		if ( $this->settings[ 'sync_private_flag' ] ) {
			if ( $post_status == 'private' && ( empty( $post_vars[ 'original_post_status' ] ) || $post_vars[ 'original_post_status' ] != 'private' ) ) {
				if (  $translated_element_id && is_array($translated_element_id) ) {
					foreach ( $translated_element_id as $tp ) {
						if ( $tp != $post_id ) {
							$wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => $tp ) );
						}
					}
				}
			} elseif ( $post_status != 'private' && isset( $post_vars[ 'original_post_status' ] ) && $post_vars[ 'original_post_status' ] == 'private' ) {
				if (  $translated_element_id && is_array($translated_element_id) ) {
					foreach ( $translated_element_id as $tp ) {
						if ( $tp != $post_id ) {
							$wpdb->update( $wpdb->posts, array( 'post_status' => $post_status ), array( 'ID' => $tp ) );
						}
					}
				}
			}
		}

		//sync post format
		if ( $this->settings[ 'sync_post_format' ] && function_exists( 'set_post_format' ) ) {
			$format = get_post_format( $post_id );
			if (  $translated_element_id && is_array($translated_element_id) ) {
				foreach ( $translated_element_id as $tp ) {
					if ( $tp != $post_id ) {
						set_post_format( $tp, $format );
					}
				}
			}
		}

		// sync taxonomies (ONE WAY)
		if ( !empty( $this->settings[ 'sync_post_taxonomies' ] ) && $language_code == $default_language ) {
			$translatable_taxs = $this->get_translatable_taxonomies( true, $post_vars[ 'post_type' ] );
			$all_taxs          = get_object_taxonomies( $post_vars[ 'post_type' ] );
			if ( !empty( $all_taxs ) ) {
				$translations = $this->get_element_translations( $trid, 'post_' . $post_vars[ 'post_type' ] );
				foreach ( $all_taxs as $tt ) {
					$terms = get_the_terms( $post_id, $tt );
					if ( !empty( $terms ) ) {
						foreach ( $translations as $target_lang => $translation ) {
							if ( $target_lang != $language_code ) {
								$tax_sync = array();
								foreach ( $terms as $term ) {
									if ( in_array( $tt, $translatable_taxs ) ) {
										$term_id = icl_object_id( $term->term_id, $tt, false, $target_lang );
									} else {
										$term_id = $term->term_id;
									}
									if ( $term_id ) {
										$tax_sync[ ] = intval( $term_id );
									}
								}
								//set the fourth parameter in 'true' because we need to add new terms, instead of replacing all
								wp_set_object_terms( $translation->element_id, $tax_sync, $tt, true );
							}
						}
					}
				}
			}
		}

		// sync post dates
		if ( !empty( $this->settings[ 'sync_post_date' ] ) ) {
			if ( $language_code == $default_language ) {
				if (  $translated_element_id && is_array($translated_element_id) ) {
					$post_date = $wpdb->get_var( $wpdb->prepare( "SELECT post_date FROM {$wpdb->posts} WHERE ID=%d", $post_id ) );
					foreach ( $translated_element_id as $tp ) {
						if ( $tp != $post_id ) {
							$wpdb->update( $wpdb->posts, array( 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ), array( 'ID' => $tp ) );
						}
					}
				}
			} else {
				if ( !is_null( $trid ) ) {
					$source_lang = isset( $_GET[ 'source_lang' ] ) ? $_GET[ 'source_lang' ] : $default_language;
					$original_id = $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND language_code=%s", $trid, $source_lang ) );
					$post_date   = $wpdb->get_var( $wpdb->prepare( "SELECT post_date FROM {$wpdb->posts} WHERE ID=%d", $original_id ) );
					$wpdb->update( $wpdb->posts, array( 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ), array( 'ID' => $post_id ) );
				}
			}
		}

		// new categories created inline go to the correct language
		if ( isset( $post_vars[ 'post_category' ] ) && is_array( $post_vars[ 'post_category' ] ) && $post_vars[ 'action' ] != 'inline-save' && $post_vars[ 'icl_post_language' ] ) {
			foreach ( $post_vars[ 'post_category' ] as $cat ) {
				$ttid = $wpdb->get_var( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id={$cat} AND taxonomy='category'" );
				$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'language_code' => $post_vars[ 'icl_post_language' ] ), array( 'element_id' => $ttid, 'element_type' => 'tax_category' ) );
			}
		}

		if ( isset( $post_vars[ 'icl_tn_note' ] ) ) {
			update_post_meta( $post_id, '_icl_translator_note', $post_vars[ 'icl_tn_note' ] );
		}

		//fix guid
		if ( $this->settings[ 'language_negotiation_type' ] == 2 && $this->get_current_language() != $default_language ) {
			$guid = $this->convert_url( get_post_field( 'guid', $post_id ) );
			$wpdb->update( $wpdb->posts, array( 'guid' => $guid ), array( 'id' => $post_id ) );
		}

		require_once ICL_PLUGIN_PATH . '/inc/cache.php';
		icl_cache_clear( $post_vars[ 'post_type' ] . 's_per_language', true );
		wp_defer_term_counting( false );
	}

	/**
	 * If post has translations and is the original, returns false
	 *
	 * @param $post WP_Post
	 *
	 * @return bool
	 */
	function can_post_be_deleted( $post )
	{
		global $sitepress;

		$sitepress_settings = $sitepress->get_settings();

		if ( $sitepress_settings[ 'sync_delete' ] != 1 ) {
			$trid = $sitepress->get_element_trid( $post->ID, 'post_' . $post->post_type );
			if ( $trid ) {
				$translations = $sitepress->get_element_translations( $trid, 'post_' . $post->post_type );
				$original_id  = false;
				$can_delete   = true;
				foreach ( $translations as $translation ) {
					//TODO: check that the post exists too
					if ( $translation->language_code != $sitepress->get_default_language() ) {
						$can_delete = false;
					} else {
						$original_id = $translation->element_id;
					}
				}

				if ( $post->ID == $original_id && !$can_delete ) {
					return false;
				} else {
					return true;
				}
			}
		}

		return true;
	}

	function wp_unique_post_slug( $slug, $post_id, $post_status, $post_type, $post_parent ) {
		global $wpdb;

		$cache_key_array = array( $slug, $post_id, $post_status, $post_type, $post_parent );
		$cache_key       = md5( serialize( $cache_key_array ) );
		$cache_group     = 'wp_unique_post_slug';
		$cache_found     = false;

		$result = wp_cache_get( $cache_key, $cache_group, false, $cache_found );

		if ( $cache_found ) {
			return $result;
		}

		if ( $this->is_translated_post_type( $post_type ) && $post_id ) {

			$post_prepared = $wpdb->prepare( "SELECT ID, post_status, post_name, post_title FROM {$wpdb->posts} WHERE ID=%d", $post_id );
			$post = $wpdb->get_row( $post_prepared );

			if ( !in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) {

				global $wp_rewrite;

				$feeds = $wp_rewrite->feeds;
				if ( !is_array( $feeds ) ) {
					$feeds = array();
				}

				$post_language = $this->get_language_for_element( $post_id, 'post_' . $post_type );

				if ( isset( $_POST[ 'new_slug' ] ) ) {
					if ( $_POST[ 'new_slug' ] === '' ) {
						$slug = sanitize_title( $_POST[ 'new_title' ], $post->ID );
					} else {
						$slug = sanitize_title( $_POST[ 'new_slug' ], $post->ID );
					}
				} elseif ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'inline-save' ) {
					$slug = sanitize_title( $_POST[ 'post_name' ], $post->ID );
				} else {
					$slug = sanitize_title( $post->post_name ? $post->post_name : $post->post_title, $post->ID );
				}

				$hierarchical_post_types = get_post_types( array( 'hierarchical' => true ) );
				if ( in_array( $post_type, $hierarchical_post_types ) ) {
					// Page slugs must be unique within their own trees. Pages are in a separate
					// namespace than posts so page slugs are allowed to overlap post slugs.
					$post_name_check_sql       = "SELECT p.post_name
                            FROM $wpdb->posts p
                            JOIN {$wpdb->prefix}icl_translations t ON p.ID = t.element_id AND t.element_type = %s
                            WHERE p.post_name = %s AND p.post_type IN ( '" . implode( "', '", esc_sql( $hierarchical_post_types ) ) . "' )
                                AND p.ID != %d AND p.post_parent = %d AND t.language_code = %s LIMIT 1";
					$post_name_check = $wpdb->get_var( $wpdb->prepare( $post_name_check_sql, 'post_' . $post_type, $slug, $post_id, $post_parent, $post_language ) );

					if ( $post_name_check || in_array( $slug, $feeds ) || preg_match( "@^($wp_rewrite->pagination_base)?\d+$@", $slug ) || apply_filters( 'wp_unique_post_slug_is_bad_hierarchical_slug', false, $slug, $post_type, $post_parent ) ) {
						$suffix = 2;
						do {
							$alt_post_name   = substr( $slug, 0, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
							$post_name_check = $wpdb->get_var( $wpdb->prepare( $post_name_check_sql, 'post_' . $post_type, $alt_post_name, $post_id, $post_parent, $post_language ) );
							$suffix++;
						} while ( $post_name_check );
						$slug = $alt_post_name;
					}
				} else {
					// Post slugs must be unique across all posts.
					$post_name_check_sql      = "SELECT p.post_name
                            FROM $wpdb->posts p
                            JOIN {$wpdb->prefix}icl_translations t ON p.ID = t.element_id AND t.element_type = %s
                            WHERE p.post_name = %s AND p.post_type = %s AND p.ID != %d AND t.language_code = %s LIMIT 1";
					$post_name_check_prepared = $wpdb->prepare( $post_name_check_sql, 'post_' . $post_type, $slug, $post_type, $post_id, $post_language );
					$post_name_check          = $wpdb->get_var( $post_name_check_prepared );

					if ( $post_name_check || in_array( $slug, $feeds ) || apply_filters( 'wp_unique_post_slug_is_bad_flat_slug', false, $slug, $post_type ) ) {
						$suffix = 2;
						do {
							$alt_post_name   = substr( $slug, 0, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
							$post_name_check = $wpdb->get_var( $wpdb->prepare( $post_name_check_sql, 'post_' . $post_type, $alt_post_name, $post_type, $post_id, $post_language ) );
							$suffix++;
						} while ( $post_name_check );
						$slug = $alt_post_name;
					}
				}

				if ( isset( $_POST[ 'new_slug' ] ) ) {
					$wpdb->update( $wpdb->posts, array( 'post_name' => $slug ), array( 'ID' => $post_id ) );
				}
			}
		}

		wp_cache_set( $cache_key, $slug, $cache_group );

		return $slug;
	}

	/** Fix parent of translation
	 * User changed parent for $orginal_id and we are setting proper parent for $translation_id in $language_code_translated language
	 * @param $original_id - id of post with changed parent
	 * @param $translated_id - translation of changed post
	 * @param $translation_language - language we are fixing
	 */
	function fix_translated_parent( $original_id, $translated_id, $translation_language )
	{
		global $wpdb;

		$icl_post_type = isset( $_POST[ 'post_type' ] ) ? 'post_' . $_POST[ 'post_type' ] : 'post_page';

		// get parent of original page
		$original_parent = $wpdb->get_var( $wpdb->prepare( "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s", array($original_id, 'page') ) );

		if ( !is_null( $original_parent ) ) {

			if ( $original_parent == 0){

				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_parent=%d WHERE ID = %d", array( 0, $translated_id ) ) );

			}else {

				$trid = $this->get_element_trid( $original_parent, $icl_post_type );

				if ( $trid ) {
					//get parent translations
					$translations_of_parent = $this->get_element_translations( $trid, $icl_post_type );

					if ( isset( $translations_of_parent[ $translation_language ] ) ) {
						$current_translated_parent = $wpdb->get_var( $wpdb->prepare( "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d", array($translated_id) ) );
						if ( !is_null( $translations_of_parent[ $translation_language ]->element_id ) && $current_translated_parent != $translations_of_parent[ $translation_language ]->element_id ) {

							$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_parent=%d WHERE ID = %d", array( $translations_of_parent[$translation_language]->element_id, $translated_id ) ) );

						}
					}
				}
			}
		}
	}

	/* Custom fields synchronization - START */
	function _sync_custom_field( $post_id_from, $post_id_to, $meta_key )
	{
		global $wpdb;

		$values_from_prepared = $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s", array($post_id_from, $meta_key) );
		$values_to_prepared = $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s", array($post_id_to, $meta_key) );

		$values_from = $wpdb->get_col( $values_from_prepared );
		$values_to   = $wpdb->get_col( $values_to_prepared );

		// handle the case of 1 key - 1 value with updates in case of change
//		if ( count( $values_from ) == 1 && count( $values_to ) == 1 ) {
//
//			if ( $values_from[ 0 ] != $values_to[ 0 ] ) {
//				$update_prepared = $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE post_id=%d AND meta_key=%s", array( $values_from[ 0 ], $post_id_to, $meta_key ) );
//				$wpdb->query( $update_prepared );
//			}
//
//		} else {

			//removed
			$removed = array_diff( $values_to, $values_from );

			foreach ( $removed as $v ) {
				$delete_prepared = $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s AND meta_value=%s", $post_id_to, $meta_key, $v );
				$wpdb->query( $delete_prepared );
			}

			//added
			$added = array_diff( $values_from, $values_to );
			foreach ( $added as $v ) {
				$insert_prepared = $wpdb->prepare( "INSERT INTO {$wpdb->postmeta}(post_id, meta_key, meta_value) VALUES(%d, %s, %s)", $post_id_to, $meta_key, $v );
				$wpdb->query( $insert_prepared );
			}

//		}

	}

	function copy_custom_fields( $post_id_from, $post_id_to )
	{
		$cf_copy = array();

		if ( isset( $this->settings[ 'translation-management' ][ 'custom_fields_translation' ] ) ) {
			foreach ( $this->settings[ 'translation-management' ][ 'custom_fields_translation' ] as $meta_key => $option ) {
				if ( $option == 1 ) {
					$cf_copy[ ] = $meta_key;
				}
			}
		}

		foreach ( $cf_copy as $meta_key ) {
			$meta_from = get_post_meta($post_id_from, $meta_key) ;
			$meta_to = get_post_meta($post_id_to, $meta_key) ;
			if($meta_from || $meta_to) {
				$this->_sync_custom_field( $post_id_from, $post_id_to, $meta_key );
			}
		}

	}

	function update_post_meta( $meta_id, $object_id, $meta_key, $_meta_value )
	{
		return;

		global $wpdb, $iclTranslationManagement;
		// only handle custom fields that need to be copied
		$custom_fields_translation = isset($this->settings[ 'translation-management' ][ 'custom_fields_translation' ]) ? $this->settings[ 'translation-management' ][ 'custom_fields_translation' ] : false;

		if (!$custom_fields_translation || !isset( $custom_fields_translation[ $meta_key ] ) || !in_array( $custom_fields_translation[ $meta_key ], array( 1, 2 ) ) ) {
			return;
		}

		$post = get_post( $object_id );

		if ( $custom_fields_translation[ $meta_key ] == '2' ) {
			$trid = $this->get_element_trid( $object_id, 'post_' . $post->post_type );
			if ( $trid ) {
				$translations = $this->get_element_translations( $trid, 'post_' . $post->post_type );
				foreach ( $translations as $translation ) {
					if ( $translation->original == 1 && $translation->element_id == $object_id ) {
						$is_original = true;
						break;
					}
				}
				if ( isset( $is_original ) ) {
					$md5 = $iclTranslationManagement->post_md5( $object_id );
					foreach ( $translations as $translation ) {
						if ( !$translation->original ) {
							$emd5 = $wpdb->get_var( $wpdb->prepare( "SELECT md5 FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d", $translation->translation_id ) );
							if ( $md5 != $emd5 ) {

								$translation_package = $iclTranslationManagement->create_translation_package( $object_id );

								$translation_data = array(
									'translation_id'      => $translation->translation_id,
									'needs_update'        => 1,
									'md5'                 => $md5,
									'translation_package' => serialize( $translation_package )
								);

								list( $rid ) = $iclTranslationManagement->update_translation_status( $translation_data );
								$translator_id_prepared = $wpdb->prepare( "SELECT translator_id FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d", $translation->translation_id );
								$translator_id = $wpdb->get_var( $translator_id_prepared );
								$iclTranslationManagement->add_translation_job( $rid, $translator_id, $translation_package );
							}
						}
					}
				}
			}
		} else {

			$translated_docs = $this->get_translatable_documents();

			if ( !empty( $translated_docs[ $post->post_type ] ) ) {
				$original_id = $this->get_original_element_id($object_id, 'post_' . $post->post_type, true, $all_statuses = true);
				$trid = $this->get_element_trid( $object_id, 'post_' . $post->post_type );
				if ( $trid ) {
					$translations = $this->get_element_translations( $trid, 'post_' . $post->post_type, true, true );

					// CASE of updating the original document (source)
					if ( isset( $original_id ) ) {
						if ( $object_id == $original_id ) {
							foreach ( $translations as $t ) {
								if ( $object_id != $t->element_id ) {
									$this->_sync_custom_field( $object_id, $t->element_id, $meta_key, true );
								}
							}
						} else { // CASE of updating the translated document (target) - don't let writing something else here
							$_meta_value = get_post_meta($original_id, $meta_key);
							$this->update_post_meta( $meta_id, $original_id, $meta_key, $_meta_value );
						}
					}
				}
			}
		}
	}

	function delete_post_meta( $meta_id )
	{
		return;

		if(!isset($this->settings[ 'translation-management' ][ 'custom_fields_translation' ])) return;

		if ( !function_exists( 'get_post_meta_by_id' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		if ( is_array( $meta_id ) ) {
			$meta_id = $meta_id[ 0 ];
		}
		$meta = get_post_meta_by_id( $meta_id );

		if(!isset($this->settings[ 'translation-management' ][ 'custom_fields_translation' ][ $meta->meta_key ])) return;

		$custom_fields_translation_meta = $this->settings[ 'translation-management' ][ 'custom_fields_translation' ][ $meta->meta_key ];

		if ( $meta && in_array( $custom_fields_translation_meta, array( 1, 2 ) ) ) {

			$post            = get_post( $meta->post_id );
			$translated_docs = $this->get_translatable_documents();

			if ( !empty( $translated_docs[ $post->post_type ] ) ) {
				$trid = $this->get_element_trid( $meta->post_id, 'post_' . $post->post_type );
				if ( $trid ) {
					$translations = $this->get_element_translations( $trid, 'post_' . $post->post_type );
					if ( $translations ) {
						foreach ( $translations as $t ) {
							if ( $t->original ) {
								$original_id = $t->element_id;
							}
						}
					}

					if ( isset( $original_id ) ) {
						if ( $original_id == $meta->post_id ) {
							foreach ( $translations as $t ) {
								if ( !$t->original ) {
									$this->_sync_custom_field( $meta->post_id, $t->element_id, $meta->meta_key, $custom_fields_translation_meta==1 );
								}
							}
						} else {
							$this->_sync_custom_field( $original_id, $meta->post_id, $meta->meta_key, $custom_fields_translation_meta==1 );
						}
					}
				}
			}
		}

	}

	/* Custom fields synchronization - END */

	function before_delete_post_actions( $post_id )
	{
		global $wpdb;

		if ( !isset( $post_id ) || !$post_id ) {
			$post = get_post();
			$post_id = $post->ID;
		} else {
			$post = get_post( $post_id );
		}
		if ( $post == null ) {
			return;
		}

		$post_type = $post->post_type;

		//we need to save information which for which children we have to update translations after parent post delete
		if ( is_post_type_hierarchical( $post_type ) && $this->settings[ 'sync_page_parent' ] && !$this->settings[ 'sync_delete' ] ) {
			//get children of deleted post
			$children_ids = $wpdb->get_col( $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_parent=%d AND post_type=%s", array($post_id, $post_type) ) );
			//cache value will be used in in deleted_post_actions
			wp_cache_set('children_before_delete_post_actions_'.$post_id, $children_ids, 'icl_before_delete_post_actions');
		}

	}


	function deleted_post_actions( $post_id )
	{
		global $wpdb;

		if ( !isset( $post_id ) || !$post_id ) {
			$post = get_post();
			$post_id = $post->ID;
		} else {
			$post = get_post( $post_id );
		}
		if ( $post == null ) {
			return;
		}

		$post_type = $post->post_type;

		$post_type_exceptions = array('nav_menu_item');

		if(in_array($post_type, $post_type_exceptions)) return;

		require_once ICL_PLUGIN_PATH . '/inc/cache.php';
		icl_cache_clear( $post_type . 's_per_language', true );

		$element_type = 'post_' . $post_type;
		$trid         = $this->get_element_trid( $post_id, $element_type );

		if ( $trid ) {
			$language_details  = $this->get_element_language_details( $post_id, $element_type );
			if ( $language_details ) {
				$is_original       = ! $language_details->source_language_code;
				$original_language = $language_details->language_code;

				$this->delete_element_translation( $trid, $element_type, $language_details->language_code );

				//If we've just deleted the original and there are still translations, let's set the original to the first available translation
				$post_translations = $this->get_element_translations( $trid, $element_type );
				if ( $is_original && $post_translations ) {
					$languages                 = $this->get_active_languages( true );
					$languages                 = $this->order_languages( $languages );
					$fallback_language         = false;
					$new_source_translation_id = false;

					//Get first available languages (to keep their order)
					foreach ( $languages as $language ) {
						if ( $language[ 'code' ] != $original_language ) {
							if ( isset( $post_translations[ $language[ 'code' ] ] ) ) {
								$fallback_language         = $language[ 'code' ];
								$new_source_translation_id = $post_translations[ $fallback_language ]->element_id;
								break;
							}
						}
					}

					foreach ( $post_translations as $post_translation ) {
						$element_language_details = $this->get_element_language_details( $post_translation->element_id, $element_type );
						if ( $post_translation->element_id == $new_source_translation_id ) {
							$source_language = false;
						} elseif ( $original_language == $element_language_details->source_language_code ) {
							$source_language = $fallback_language;
						} else {
							$source_language = $element_language_details->source_language_code;
						}

						$update_data = array(
							'language_code' => $element_language_details->language_code
						);
						if ( $source_language ) {
							$update_data[ 'source_language_code' ] = $source_language;
						} else {
							$update_data[ 'source_language_code' ] = null;
						}

						$update_where = array(
							'translation_id' => $post_translation->translation_id
						);
						$wpdb->update( $wpdb->prefix . 'icl_translations', $update_data, $update_where );

						$_icl_lang_duplicate_of = get_post_meta( $post_translation->element_id, '_icl_lang_duplicate_of', true );
						if ( $_icl_lang_duplicate_of ) {
							if ( $element_language_details->language_code == $fallback_language ) {
								delete_post_meta( $post_translation->element_id, '_icl_lang_duplicate_of' );
							} else {
								update_post_meta( $post_translation->element_id, '_icl_lang_duplicate_of', $new_source_translation_id );
							}
						}
					}
				}
			}

			// synchronize the page parent for translations only when we do not delete translations and only for hierarchical types
			if ( is_post_type_hierarchical( $post_type ) && $this->settings[ 'sync_page_parent' ] && !$this->settings[ 'sync_delete' ]  ) {

				//get deleted post parent
				$parent_trid = $this->get_element_trid( $post->post_parent, $element_type );
				//get translations of deleted post parent
				$parent_translations = $this->get_element_translations( $parent_trid, $element_type );

				// get children of deleted post (stored in before_post_delete_actions)
				$children_ids = wp_cache_get( 'children_before_delete_post_actions_' . $post_id, 'icl_before_delete_post_actions' );

				if ( $children_ids ) {
					//for each children of deleted post
					foreach ( $children_ids as $child_id ) {
						//get translations
						$child_trid         = $this->get_element_trid( $child_id, $element_type );
						$child_translations = $this->get_element_translations( $child_trid, $element_type );

						//for each translation of child
						foreach ( $child_translations as $child_target_lang => $child_target_details ) {
							//if parent translation exists and it is a translations (not child of deleted post)

							if ( $child_target_details->element_id != $child_id ) {

								if ( isset( $parent_translations[ $child_target_lang ]->element_id ) ) {
									//set parent
									$wpdb->update( $wpdb->posts, array( 'post_parent' => $parent_translations[ $child_target_lang ]->element_id ), array( 'ID' => $child_target_details->element_id ) );
								} else {
									$wpdb->update( $wpdb->posts, array( 'post_parent' => 0 ), array( 'ID' => $child_target_details->element_id ) );
								}
							}
						}
					}
				}
				// remove children of deleted post (stored in before_post_delete_actions)
				wp_cache_delete( 'children_before_delete_post_actions_' . $post_id, 'icl_before_delete_post_actions' );

			}

		}

		if ( !$this->settings[ 'sync_delete' ] ) {
			return;
		}

		static $deleted_posts;

		if ( isset( $deleted_posts[ $post_id ] ) ) {
			return; // avoid infinite loop
		}


		if ( ( empty( $deleted_posts ) || ( is_array( $deleted_posts ) && !isset( $deleted_posts[ $post_id ] ) ) ) ) {
			$translations = $this->get_element_translations( $trid, $element_type );
			foreach ( $translations as $t ) {
				if ( $t->element_id != $post_id ) {
					$deleted_posts[ ] = $post_id;
					$post_exists_sql  = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE id = %d", $t->element_id );
					$post_exists      = $wpdb->get_col( $post_exists_sql );
					if ( $post_exists ) {
						wp_delete_post( $t->element_id );
					}
				}
			}
		}

		//$wpdb->query("DELETE FROM {$wpdb->prefix}icl_translations WHERE element_type='post_{$post_type}' AND element_id='{$post_id}' LIMIT 1");

		require_once ICL_PLUGIN_PATH . '/inc/cache.php';
		icl_cache_clear( $post_type . 's_per_language', true );
	}

	function trash_post_actions( $post_id )
	{            
		global $wpdb;
		$post_type = $wpdb->get_var( "SELECT post_type FROM {$wpdb->posts} WHERE ID={$post_id}" );

		// bulk deleting
		// using this to not try to delete a post that's going to be deleted anyway
		static $posts_to_delete_in_bulk;
		if ( is_null( $posts_to_delete_in_bulk ) ) {
			$posts_to_delete_in_bulk = isset( $_GET[ 'post' ] ) && is_array( $_GET[ 'post' ] ) ? $_GET[ 'post' ] : array();
		}

		if ( $this->settings[ 'sync_delete' ] ) {

			static $trashed_posts = array();
			if ( isset( $trashed_posts[ $post_id ] ) ) {
				return; // avoid infinite loop
			}

			$trashed_posts[ $post_id ] = $post_id;

			$trid         = $this->get_element_trid( $post_id, 'post_' . $post_type );
			$translations = $this->get_element_translations( $trid, 'post_' . $post_type );
			foreach ( $translations as $t ) {
				if ( $t->element_id != $post_id && !in_array( $t->element_id, $posts_to_delete_in_bulk ) ) {
					wp_trash_post( $t->element_id );
				}
			}
		}

		require_once ICL_PLUGIN_PATH . '/inc/cache.php';
		icl_cache_clear( $post_type . 's_per_language', true );
	}

	function untrashed_post_actions( $post_id )
	{
		global $wpdb;
		$post_type = $wpdb->get_var( "SELECT post_type FROM {$wpdb->posts} WHERE ID={$post_id}" );

		if ( $this->settings[ 'sync_delete' ] ) {
			static $untrashed_posts = array();

			if ( isset( $untrashed_posts[ $post_id ] ) ) {
				return; // avoid infinite loop
			}

			$untrashed_posts[ $post_id ] = $post_id;

			$trid         = $this->get_element_trid( $post_id, 'post_' . $post_type );
			$translations = $this->get_element_translations( $trid, 'post_' . $post_type );
			foreach ( $translations as $t ) {
				if ( $t->element_id != $post_id ) {
					wp_untrash_post( $t->element_id );
				}
			}
		}

		require_once ICL_PLUGIN_PATH . '/inc/cache.php';
		icl_cache_clear( $post_type . 's_per_language', true );
	}

	function validate_taxonomy_input( $input )
	{
		global $wpdb;
		static $runonce;


		if ( empty( $runonce ) ) {
			$post_language = isset( $_POST[ 'icl_post_language' ] ) ? $_POST[ 'icl_post_language' ] : $this->get_current_language();
			if ( !empty( $input ) && is_array( $input ) ) {
				foreach ( $input as $taxonomy => $values ) {
					if ( is_string( $values ) ) { // only not-hierarchical
						$values = array_map( 'trim', explode( ',', $values ) );
						foreach ( $values as $k => $term ) {

							// if the term exists in another language, apply the language suffix
							$term_info = term_exists( $term, $taxonomy );
							if ( $term_info ) {
								$term_language = $wpdb->get_var( $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations
                                        WHERE element_type=%s AND element_id=%d", 'tax_' . $taxonomy, $term_info[ 'term_taxonomy_id' ] ) );
								if ( $term_language && $term_language != $post_language ) {
									$term = $term . ' @' . $post_language;
								}
							}

							$values[ $k ] = $term;
						}
						$values             = join( ',', $values );
						$input[ $taxonomy ] = $values;
					}
				}
			}
			$runonce = true;
		}

		return $input;
	}

	/**
	 * @param int    $trid
	 * @param string $el_type Use comment, post, page, {custom post time name}, nav_menu, nav_menu_item, category, post_tag, etc. (prefixed with 'post_', 'tax_', or nothing for 'comment')
	 * @param bool   $skip_empty
	 * @param bool   $all_statuses
	 * @param bool   $skip_cache
	 *
	 * @return array|bool|mixed
	 */
	function get_element_translations( $trid, $el_type = 'post_post', $skip_empty = false, $all_statuses = false, $skip_cache = false )
	{
		$cache_key_args = array_filter( array( $trid, $el_type, $skip_empty, $all_statuses ) );
		$cache_key = md5(json_encode( $cache_key_args ));
		$cache_group = 'element_translations';

		$temp_elements = $skip_cache ? false : wp_cache_get($cache_key, $cache_group);
		if($temp_elements) return $temp_elements;

		global $wpdb;
		$translations = array();
		$sel_add      = '';
		$where_add    = '';
		if ( $trid ) {
			if ( 0 === strpos( $el_type, 'post_' ) ) {

					$sel_add     = ', p.post_title, p.post_status';
					$join_add    = " LEFT JOIN {$wpdb->posts} p ON t.element_id=p.ID";
					$groupby_add = "";

					if ( !is_admin() && empty( $all_statuses ) && $el_type != 'post_attachment' ) {
						// the current user may not be the admin but may have read private post/page caps!
						if ( current_user_can( 'read_private_pages' ) || current_user_can( 'read_private_posts' ) ) {
							$where_add .= " AND (p.post_status = 'publish' OR p.post_status = 'private')";
						} else {
							$where_add .= " AND (";
							$where_add .= "p.post_status = 'publish'";
							if ( $uid = $this->get_current_user()->ID ) {
								$where_add .= " OR (post_status in ('draft', 'private') AND  post_author = {$uid})";
							}
							$where_add .= ") ";
						}
					}

			} elseif ( preg_match( '#^tax_(.+)$#', $el_type ) ) {
				$sel_add     = ', tm.name, tm.term_id, COUNT(tr.object_id) AS instances';
				$join_add    = " LEFT JOIN {$wpdb->term_taxonomy} tt ON t.element_id=tt.term_taxonomy_id
                              LEFT JOIN {$wpdb->terms} tm ON tt.term_id = tm.term_id
                              LEFT JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id=tt.term_taxonomy_id
                              ";
				$groupby_add = "GROUP BY tm.term_id";
			}
			$where_add .= " AND t.trid='{$trid}'";

			if ( !isset( $join_add ) ) {
				$join_add = "";
			}
			if ( !isset( $groupby_add ) ) {
				$groupby_add = "";
			}

			$query = "
                SELECT t.translation_id, t.language_code, t.element_id, t.source_language_code, NULLIF(t.source_language_code, '') IS NULL AS original {$sel_add}
                FROM {$wpdb->prefix}icl_translations t
                     {$join_add}
                WHERE 1 {$where_add}
                {$groupby_add}
            ";

			$ret = $wpdb->get_results( $query );

			foreach ( $ret as $t ) {
				if ( ( preg_match( '#^tax_(.+)$#', $el_type ) ) && $t->instances == 0 && !_icl_tax_has_objects_recursive( $t->element_id ) && $skip_empty ) {
					continue;
				}


				$cached_object_key = $t->element_id . '#' . $el_type . '#0#' . $t->language_code;
				wp_cache_set( $cached_object_key, $cached_object_key, 'icl_object_id' );

				$translations[ $t->language_code ] = $t;
			}

		}

		if($translations) {
			wp_cache_set($cache_key, $translations, $cache_group);
		}
		return $translations;
	}


	static function get_original_element_id($element_id, $element_type = 'post_post', $skip_empty = false, $all_statuses = false, $skip_cache = false) {
		$cache_key_args = array_filter( array( $element_id, $element_type, $skip_empty, $all_statuses ) );
		$cache_key = md5(json_encode( $cache_key_args ));
		$cache_group = 'original_element';

		$temp_elements = $skip_cache ? false : wp_cache_get($cache_key, $cache_group);
		if($temp_elements) return $temp_elements;

		global $sitepress;

		$original_element_id = false;

		$trid = $sitepress->get_element_trid($element_id, $element_type);
		if($trid) {
			$element_translations = $sitepress->get_element_translations($trid, $element_type,$skip_empty,$all_statuses,$skip_cache);

			foreach($element_translations as $element_translation) {
				if($element_translation->original) {
					$original_element_id = $element_translation->element_id;
					break;
				}
			}
		}

		if($original_element_id) {
			wp_cache_set($cache_key, $original_element_id, $cache_group);
		}
		return $original_element_id;
	}

	/**
	 * @param int    $element_id Use term_taxonomy_id for taxonomies, post_id for posts
	 * @param string $el_type    Use comment, post, page, {custom post time name}, nav_menu, nav_menu_item, category, post_tag, etc. (prefixed with 'post_', 'tax_', or nothing for 'comment')
	 *
	 * @return bool|mixed|null|string
	 */
	function get_element_trid( $element_id, $el_type = 'post_post' ) {
		$cache_key = $element_id . ':' . $el_type;
		$cache_group = 'element_trid';

		$temp_trid = wp_cache_get($cache_key, $cache_group);

		if($temp_trid) return $temp_trid;

		global $wpdb;

		$trid_prepared = $wpdb->prepare( "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", array( $element_id, $el_type ) );

		$trid = $wpdb->get_var( $trid_prepared );

		if($trid) {
			wp_cache_add($cache_key, $trid, $cache_group);
		}

		return $trid;
	}

	/**
	 * @param int    $element_id Use term_taxonomy_id for taxonomies, post_id for posts
	 * @param string $el_type    Use comment, post, page, {custom post time name}, nav_menu, nav_menu_item, category, post_tag, etc. (prefixed with 'post_', 'tax_', or nothing for 'comment')
	 *
	 * @return null|string
	 */
	function get_language_for_element( $element_id, $el_type = 'post_post' ) {
		global $wpdb;

		$cache_key_array = array( $element_id, $el_type );
		$cache_key       = md5( serialize( $cache_key_array ) );
		$cache_group     = 'get_language_for_element';
		$cache_found     = false;

		$result = wp_cache_get( $cache_key, $cache_group, false, $cache_found );

		if ( $cache_found ) {
			return $result;
		}

		$language_for_element_prepared = $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", array( $element_id, $el_type ) );

		$result = $wpdb->get_var( $language_for_element_prepared );

		wp_cache_set( $cache_key, $result, $cache_group );

		return $result;
	}

	/**
	 * @param string $el_type     Use comment, post, page, {custom post time name}, nav_menu, nav_menu_item, category, post_tag, etc. (prefixed with 'post_', 'tax_', or nothing for 'comment')
	 * @param string $target_lang Target language code
	 * @param string $source_lang Source language code
	 *
	 * @return array
	 */
	function get_elements_without_translations( $el_type, $target_lang, $source_lang )
	{
		global $wpdb;

		// first get all the trids for the target languages
		// These will be the trids that we don't want.
		$sql = "SELECT
                    trid
                FROM
                    {$wpdb->prefix}icl_translations
                WHERE
                    language_code = '{$target_lang}'
                AND element_type = '{$el_type}'
        ";


		$trids_for_target = $wpdb->get_col( $sql );
		if ( sizeof( $trids_for_target ) > 0 ) {
			$trids_for_target = join( ',', $trids_for_target );
			$not_trids        = 'AND trid NOT IN (' . $trids_for_target . ')';
		} else {
			$not_trids = '';
		}

		$join = $where = '';
		// exclude trashed posts
		if ( 0 === strpos( $el_type, 'post_' ) ) {
			$join .= " JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = {$wpdb->prefix}icl_translations.element_id";
			$where .= " AND {$wpdb->posts}.post_status <> 'trash' AND {$wpdb->posts}.post_status <> 'auto-draft'";
		}

		// Now get all the elements that are in the source language that
		// are not already translated into the target language.
		$sql = "SELECT
                    element_id
                FROM
                    {$wpdb->prefix}icl_translations
                    {$join}
                WHERE
                    language_code = '{$source_lang}'
                    {$not_trids}
                    AND element_type= '{$el_type}'
                    {$where}
                ";

		return $wpdb->get_col( $sql );
	}

	/**
	 * @param string $selected_language
	 * @param string $default_language
	 * @param string $post_type
	 *
	 * @used_by SitePress:meta_box
	 *
	 * @return array
	 */
	function get_posts_without_translations( $selected_language, $default_language, $post_type = 'post_post' )
	{
		global $wpdb;
		$untranslated_ids = $this->get_elements_without_translations( $post_type, $selected_language, $default_language );

		$untranslated = array();

		foreach ( $untranslated_ids as $id ) {
			$untranslated[ $id ] = $wpdb->get_var( "SELECT post_title FROM {$wpdb->prefix}posts WHERE ID = {$id}" );
		}

		return $untranslated;
	}

	static function get_orphan_translations($trid, $post_type = 'post', $source_language) {
		global $sitepress, $wpdb;

		$translations = $sitepress->get_element_translations($trid, 'post_' . $post_type);

		if(count($translations) == 1) {
			$sql = " SELECT trid, ";

			$active_languages = $sitepress->get_active_languages();

			$language_codes = array_keys($active_languages);

			$sql_languages = array();
			$sql_languages_having = array();
			foreach($language_codes as $language_code) {
				$sql_languages[] = "SUM(CASE language_code WHEN '" . esc_sql($language_code) . "' THEN 1 ELSE 0 END) AS `" . esc_sql($language_code) . '`';
				if($language_code==$source_language) {
					$sql_languages_having[] = '`' . $language_code . '`= 0';
				}
			}
			$sql .= implode(',', $sql_languages);

			$sql .= " 	FROM {$wpdb->prefix}icl_translations
						WHERE element_type = %s ";

			$sql .= 'GROUP BY trid ';
			$sql .= 'HAVING ' . implode(' AND ', $sql_languages_having);

			$sql .= " ORDER BY trid;";

			$sql_prepared = $wpdb->prepare($sql, array('post_' . $post_type));
			$trid_results = $wpdb->get_results($sql_prepared, 'ARRAY_A');

			$trid_list = array_column($trid_results, 'trid');

			if($trid_list) {
				$sql = "SELECT trid AS value, CONCAT('[', t.language_code, '] ', (CASE p.post_title WHEN '' THEN CONCAT(LEFT(p.post_content, 30), '...') ELSE p.post_title END)) AS label
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->prefix}icl_translations t
							ON p.ID = t.element_id
						WHERE t.element_type = %s
							AND t.language_code <> %s
							AND t.trid IN (" . implode(',', $trid_list) . ')';
				$sql_prepared = $wpdb->prepare($sql, array('post_' . $post_type, $source_language));
				$results = $wpdb->get_results($sql_prepared);
			} else {
				$results = array();
			}
			return $results;
		}
		return false;
	}

	/**
	 * @param WP_Post $post
	 */
	function meta_box( $post ) {
		if ( in_array( $post->post_type, array_keys( $this->get_translatable_documents() ) ) ) {
			include ICL_PLUGIN_PATH . '/menu/post-menu.php';
		}
	}

	function icl_get_metabox_states()
	{
		global $icl_meta_box_globals, $wpdb;

		$translation   = false;
		$source_id     = null;
		$translated_id = null;
		if ( sizeof( $icl_meta_box_globals[ 'translations' ] ) > 0 ) {
			if ( !isset( $icl_meta_box_globals[ 'translations' ][ $icl_meta_box_globals[ 'selected_language' ] ] ) ) {
				// We are creating a new translation
				$translation = true;
				// find the original
				foreach ( $icl_meta_box_globals[ 'translations' ] as $trans_data ) {
					if ( $trans_data->original == '1' ) {
						$source_id = $trans_data->element_id;
						break;
					}
				}
			} else {
				$trans_data = $icl_meta_box_globals[ 'translations' ][ $icl_meta_box_globals[ 'selected_language' ] ];
				// see if this is an original or a translation.
				if ( $trans_data->original == '0' ) {
					// double check that it's not the original
					// This is because the source_language_code field in icl_translations is not always being set to null.

					$source_language_code = $wpdb->get_var( "SELECT source_language_code FROM {$wpdb->prefix}icl_translations WHERE translation_id = $trans_data->translation_id" );
					$translation          = !( $source_language_code == "" || $source_language_code == null );
					if ( $translation ) {
						$source_id     = $icl_meta_box_globals[ 'translations' ][ $source_language_code ]->element_id;
						$translated_id = $trans_data->element_id;
					} else {
						$source_id = $trans_data->element_id;
					}
				} else {
					$source_id = $trans_data->element_id;
				}
			}
		}

		return array( $translation, $source_id, $translated_id );

	}

	function meta_box_config( $post )
	{
		global $iclTranslationManagement;
		global $wp_taxonomies, $wp_post_types, $sitepress_settings;


		echo '<div class="icl_form_success" style="display:none">' . __( 'Settings saved', 'sitepress' ) . '</div>';

		$cp_editable = false;
		$checked     = false;
		if ( !in_array( $post->post_type, array( 'post', 'page' ) ) ) {

			if ( !isset( $iclTranslationManagement->settings[ 'custom_types_readonly_config' ][ $post->post_type ] ) || $iclTranslationManagement->settings[ 'custom_types_readonly_config' ][ $post->post_type ] !== 0 ) {

				if ( in_array( $post->post_type, array_keys( $this->get_translatable_documents() ) ) ) {
					$checked   = ' checked="checked"';
					$radio_disabled = isset( $iclTranslationManagement->settings[ 'custom_types_readonly_config' ][ $post->post_type ] ) ? 'disabled="disabled"' : '';
				} else {
					$checked = $radio_disabled = '';
				}

				if ( !$radio_disabled ) {
					$cp_editable = true;
				}

				echo '<br style="line-height:8px;" /><label><input id="icl_make_translatable" type="checkbox" value="' . $post->post_type . '"' . $checked . $radio_disabled . '/>&nbsp;' . sprintf( __( "Make '%s' translatable", 'sitepress' ), $wp_post_types[ $post->post_type ]->labels->name ) . '</label><br style="line-height:8px;" />';

			}

		} else {
			echo '<input id="icl_make_translatable" type="checkbox" checked="checked" value="' . $post->post_type . '" style="display:none" />';
			$checked = true;
		}

		echo '<br clear="all" /><span id="icl_mcs_details">';

		if ( $checked ) {

			//echo '<div style="width:49%;float:left;min-width:265px;margin-right:5px;margin-top:3px;">';

			$custom_taxonomies = array_diff( get_object_taxonomies( $post->post_type ), array( 'post_tag', 'category', 'nav_menu', 'link_category', 'post_format' ) );

			if ( !empty( $custom_taxonomies ) ) {
				?>
				<table class="widefat">
					<thead>
					<tr>
						<th colspan="2"><?php _e( 'Custom taxonomies', 'sitepress' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( $custom_taxonomies as $ctax ): ?>
						<?php
						$checked1  = !empty( $sitepress_settings[ 'taxonomies_sync_option' ][ $ctax ] ) ? ' checked="checked"' : '';
						$checked0  = empty( $sitepress_settings[ 'taxonomies_sync_option' ][ $ctax ] ) ? ' checked="checked"' : '';
						$radio_disabled = isset( $iclTranslationManagement->settings[ 'taxonomies_readonly_config' ][ $ctax ] ) ? ' disabled="disabled"' : '';
						?>
						<tr>
							<td><?php echo $wp_taxonomies[ $ctax ]->labels->name ?></td>
							<td align="right">
								<label><input name="icl_mcs_custom_taxs_<?php echo $ctax ?>" class="icl_mcs_custom_taxs" type="radio"
											  value="<?php echo $ctax ?>" <?php echo $checked1; ?><?php echo $radio_disabled ?> />&nbsp;<?php _e( 'Translate', 'sitepress' ) ?></label>
								<label><input name="icl_mcs_custom_taxs_<?php echo $ctax ?>" type="radio" value="0" <?php echo $checked0; ?><?php echo $radio_disabled ?> />&nbsp;<?php _e( 'Do nothing', 'sitepress' ) ?></label>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<br/>
			<?php
			}

			//echo '</div><div style="width:50%;float:left;min-width:265px;margin-top:3px;">';

			if ( defined( 'WPML_TM_VERSION' ) ) {
				$custom_keys        = (array)get_post_custom_keys( $post->ID );
				$cf_keys_exceptions = array(
					'_edit_last', '_edit_lock', '_wp_page_template', '_wp_attachment_metadata', '_icl_translator_note', '_alp_processed', '_pingme', '_encloseme', '_icl_lang_duplicate_of', '_wpml_media_duplicate', '_wpml_media_featured',
					'_thumbnail_id'
				);
				$custom_keys = array_diff( $custom_keys, $cf_keys_exceptions );
				$cf_settings_read_only = isset( $iclTranslationManagement->settings[ 'custom_fields_readonly_config' ] ) ? (array)$iclTranslationManagement->settings[ 'custom_fields_readonly_config' ] : array();
				$cf_settings = isset( $iclTranslationManagement->settings[ 'custom_fields_translation' ] ) ? $iclTranslationManagement->settings[ 'custom_fields_translation' ] : array();

				if ( !empty( $custom_keys ) ) {
					?>
					<table class="widefat">
						<thead>
						<tr>
							<th colspan="2"><?php _e( 'Custom fields', 'sitepress' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php
						foreach ( $custom_keys as $cfield ) {

							if ( empty( $cf_settings[ $cfield ] ) || $cf_settings[ $cfield ] != 3 ) {
								$radio_disabled = in_array( $cfield, $cf_settings_read_only ) ? 'disabled="disabled"' : '';
								$checked0  = empty( $cf_settings[ $cfield ] ) ? ' checked="checked"' : '';
								$checked1  = isset( $cf_settings[ $cfield ] ) && $cf_settings[ $cfield ] == 1 ? ' checked="checked"' : '';
								$checked2  = isset( $cf_settings[ $cfield ] ) && $cf_settings[ $cfield ] == 2 ? ' checked="checked"' : '';
								?>
								<tr>
									<td><?php echo $cfield; ?></td>
									<td align="right">
										<label><input class="icl_mcs_cfs" name="icl_mcs_cf_<?php echo base64_encode( $cfield ); ?> " type="radio"
													  value="0" <?php echo $radio_disabled . $checked0 ?> />&nbsp;<?php _e( "Don't translate", 'sitepress' ) ?></label>
										<label><input class="icl_mcs_cfs" name="icl_mcs_cf_<?php echo base64_encode( $cfield ); ?> " type="radio" value="1" <?php echo $radio_disabled . $checked1 ?> />&nbsp;<?php _e( "Copy", 'sitepress' ) ?>
										</label>
										<label><input class="icl_mcs_cfs" name="icl_mcs_cf_<?php echo base64_encode( $cfield ); ?> " type="radio" value="2" <?php echo $radio_disabled . $checked2 ?> />&nbsp;<?php _e( "Translate", 'sitepress' ) ?>
										</label>
									</td>
								</tr>
							<?php
							}
						}
						?>
						</tbody>
					</table>
					<br/>
				<?php
				}
			}

			//echo '</div><br clear="all" />';

			if ( !empty( $custom_taxonomies ) || !empty( $custom_keys ) ) {
				echo '<small>' . __( 'Note: Custom taxonomies and custom fields are shared across different post types.', 'sitepress' ) . '</small>';
			}

		}
		echo '</span>';

		if ( $cp_editable || !empty( $custom_taxonomies ) || !empty( $custom_keys ) ) {
			echo '<p class="submit" style="margin:0;padding:0"><input class="button-secondary" id="icl_make_translatable_submit" type="button" value="' . __( 'Apply', 'sitepress' ) . '" /></p><br clear="all" />';
			wp_nonce_field( 'icl_mcs_inline_nonce', '_icl_nonce_imi' );
		} else {
			_e( 'Nothing to configure.', 'sitepress' );
		}


	}

	function pre_get_posts( $wpq )
	{

		// case of internal links list
		//
		if ( isset( $_POST[ 'action' ] ) && 'wp-link-ajax' == $_POST[ 'action' ] ) {
			$default_language = $this->get_default_language();
			if ( !empty( $_SERVER[ 'HTTP_REFERER' ] ) ) {
				$parts = parse_url( $_SERVER[ 'HTTP_REFERER' ] );
				parse_str( strval( $parts[ 'query' ] ), $query );
				$lang = isset( $query[ 'lang' ] ) ? $query[ 'lang' ] : $default_language;
			} else $lang = $default_language;
			$this->this_lang                       = $lang;
			$wpq->query_vars[ 'suppress_filters' ] = false;
		}
		
		if ($this->is_gallery_on_root_page($wpq)) {
		    $wpq->query_vars['page_id'] = 0;
		    $wpq->query['page_id'] = 0;
		}

		return $wpq;
	}

	function is_gallery_on_root_page( $wpq ) {
		// are we on root page and we are searching for attachments to gallery on it?
		if ( isset( $this->settings[ 'urls' ][ 'show_on_root' ] ) &&
				 $this->settings[ 'urls' ][ 'show_on_root' ] == 'page' &&
				 isset( $this->settings[ 'urls' ][ 'root_page' ] ) &&
				 isset( $wpq->query_vars[ 'page_id' ] ) &&
				 $wpq->query_vars[ 'page_id' ] > 0 &&
				 $wpq->query_vars[ 'page_id' ] == $this->settings[ 'urls' ][ 'root_page' ] &&
				 isset( $wpq->query_vars[ 'post_type' ] ) &&
				 $wpq->query_vars[ 'post_type' ] == 'attachment'
		) {
			return true;
		}

		return false;
	}

	/**
	 * @param $join  string
	 * @param $query WP_Query
	 *
	 * @return string
	 */
	function posts_join_filter( $join, $query )
	{
		global $wpdb, $pagenow, $wp_taxonomies, $sitepress_settings;

		$attachment_is_translatable = $this->is_translated_post_type( 'attachment' );
		if ( (($pagenow == 'upload.php' || $pagenow == 'media-upload.php' || $query->is_attachment()) && !$attachment_is_translatable) || ( isset( $query->queried_object ) && isset( $query->queried_object->ID ) && $query->queried_object->ID == $sitepress_settings[ 'urls' ][ 'root_page' ] ) ) {
			return $join;
		}

		// determine post type
		$debug_backtrace = $this->get_backtrace( 0, true, false ); //Limit to a maximum level?
		$post_type = false;
		foreach ( $debug_backtrace as $o ) {
			if ( $o[ 'function' ] == 'apply_filters_ref_array' && $o[ 'args' ][ 0 ] == 'posts_join' ) {
				$post_type = esc_sql( $o[ 'args' ][ 1 ][ 1 ]->query_vars[ 'post_type' ] );
				break;
			}
		}

		if ( $post_type == 'any' || 'all' == $this->this_lang ) {
			$left_join = "LEFT";
		} else {
			$left_join = "";
		}

		if ( is_array( $post_type ) ) {
			$post_types = array();
			foreach ( $post_type as $ptype ) {
				if ( $this->is_translated_post_type( $ptype ) ) {
					$post_types[ ] = esc_sql( 'post_' . $ptype );
				}
			}
			if ( !empty( $post_types ) ) {
				$join .= " {$left_join} JOIN {$wpdb->prefix}icl_translations t ON {$wpdb->posts}.ID = t.element_id
                        AND t.element_type IN ('" . join( "','", $post_types ) . "') JOIN {$wpdb->prefix}icl_languages l ON t.language_code=l.code AND l.active=1";
			}
		} elseif ( $post_type ) {
			if ( $this->is_translated_post_type( $post_type ) ) {
				$join .= " {$left_join} JOIN {$wpdb->prefix}icl_translations t ON {$wpdb->posts}.ID = t.element_id
                        AND t.element_type = 'post_{$post_type}' JOIN {$wpdb->prefix}icl_languages l ON t.language_code=l.code AND l.active=1";
			} elseif ( $post_type == 'any' ) {
				$join .= " {$left_join} JOIN {$wpdb->prefix}icl_translations t ON {$wpdb->posts}.ID = t.element_id
                        AND t.element_type LIKE 'post\\_%' {$left_join} JOIN {$wpdb->prefix}icl_languages l ON t.language_code=l.code AND l.active=1";
			}
		} else {

			if ( is_tax() && is_main_query() ) {
				$tax    = get_query_var( 'taxonomy' );
				$taxonomy_post_types = $wp_taxonomies[ $tax ]->object_type;

				foreach ( $taxonomy_post_types as $k => $v ) {
					if ( !$this->is_translated_post_type( $v ) ) {
						unset( $taxonomy_post_types[ $k ] );
					}
				}
			} else {
				$taxonomy_post_types = array_keys( $this->get_translatable_documents( false ) );
			}

			if ( !empty( $taxonomy_post_types ) ) {
				foreach ( $taxonomy_post_types as $k => $v ) {
					$taxonomy_post_types[ $k ] = 'post_' . $v;
				}
				$post_types = "'" . join( "','", $taxonomy_post_types ) . "'";
				$join .= " {$left_join} JOIN {$wpdb->prefix}icl_translations t ON {$wpdb->posts}.ID = t.element_id
                        AND t.element_type IN ({$post_types}) JOIN {$wpdb->prefix}icl_languages l ON t.language_code=l.code AND l.active=1";
			}
		}


		return $join;
	}

	/**
	 * @param string $where
	 * @param WP_Query $query
	 *
	 * @return string
	 */
	function posts_where_filter( $where, $query )
	{
		global $pagenow, $wp_taxonomies, $sitepress, $sitepress_settings;
		//exceptions

		$post_type = false;

		if ( isset( $query->queried_object ) && isset( $query->queried_object->ID ) && $query->queried_object->ID == $sitepress_settings[ 'urls' ][ 'root_page' ] ) {
			return $where;
		}

		// determine post type
		$debug_backtrace = $this->get_backtrace( 0, true, false ); //Limit to a maximum level?
		foreach ( $debug_backtrace as $o ) {
			if ( $o[ 'function' ] == 'apply_filters_ref_array' && $o[ 'args' ][ 0 ] == 'posts_where' ) {
				$post_type = $o[ 'args' ][ 1 ][ 1 ]->query_vars[ 'post_type' ];
				break;
			}
		}

		// case of taxonomy archive
		if ( empty( $post_type ) && is_tax() ) {
			$tax       = get_query_var( 'taxonomy' );
			$post_type = $wp_taxonomies[ $tax ]->object_type;
			foreach ( $post_type as $k => $v ) {
				if ( !$this->is_translated_post_type( $v ) ) {
					unset( $post_type[ $k ] );
				}
			}
			if ( empty( $post_type ) ) {
				return $where;
			} // don't filter
		}

		if ( !$post_type ) {
			$post_type = 'post';
		}

		if ( is_array( $post_type ) && !empty( $post_type ) ) {
			$none_translated = true;
			foreach ( $post_type as $ptype ) {
				if ( $this->is_translated_post_type( $ptype ) ) {
					$none_translated = false;
				}
			}
			if ( $none_translated ) {
				return $where;
			}
		} else {
			if ( !$this->is_translated_post_type( $post_type ) && 'any' != $post_type ) {
				return $where;
			}
		}

		$attachment_is_translatable = $sitepress->is_translated_post_type( 'attachment' );
		if ( ($pagenow == 'upload.php' || $pagenow == 'media-upload.php' || $query->is_attachment()) && !$attachment_is_translatable) {
			return $where;
		}

		$current_language = $sitepress->get_current_language();
		$requested_id = false;
		// Fix for when $sitepress->get_current_language() does not return the correct value (e.g. when request is made for an attachment, an iframe or an ajax call)
		if ( isset( $_REQUEST[ 'attachment_id' ] ) && $_REQUEST[ 'attachment_id' ] ) {
			$requested_id        = $_REQUEST[ 'attachment_id' ];
		}
		if ( isset( $_REQUEST[ 'post_id' ] ) && $_REQUEST[ 'post_id' ] ) {
			$requested_id        = $_REQUEST[ 'post_id' ];
		}

		if($requested_id) {
			$post_type        = get_post_type( $requested_id );
			$current_language = $sitepress->get_language_for_element( $requested_id, 'post_' . $post_type );

			if(!$current_language) {
				$current_language = $sitepress->get_current_language();
			}
		}

		if ( 'all' != $this->this_lang ) {
			if ( 'any' == $post_type ) {
				$condition = " AND (t.language_code='" . esc_sql( $current_language ) . "' OR t.language_code IS NULL )";
			} else {
				$condition = " AND t.language_code='" . esc_sql( $current_language ) . "'";
			}
		} else {
			$condition = '';
		}

		$where .= $condition;

		return $where;
	}

	function comment_feed_join( $join )
	{
		global $wpdb, $wp_query;
		$type = $wp_query->query_vars[ 'post_type' ] ? esc_sql( $wp_query->query_vars[ 'post_type' ] ) : 'post';

		$wp_query->query_vars[ 'is_comment_feed' ] = true;
		$join .= " JOIN {$wpdb->prefix}icl_translations t ON {$wpdb->comments}.comment_post_ID = t.element_id
                    AND t.element_type='post_{$type}' AND t.language_code='" . esc_sql( $this->this_lang ) . "'";

		return $join;
	}

	function comments_clauses( $clauses, $obj )
	{
		global $wpdb;

		if ( isset( $obj->query_vars[ 'post_id' ] ) ) {
			$post_id = $obj->query_vars[ 'post_id' ];
		} elseif ( isset( $obj->query_vars[ 'post_ID' ] ) ) {
			$post_id = $obj->query_vars[ 'post_ID' ];
		}
		if ( !empty( $post_id ) ) {
			$post = get_post( $post_id );
			if ( !$this->is_translated_post_type( $post->post_type ) ) {
				return $clauses;
			}
		}

		$current_language = $this->get_current_language();

		if ( $current_language != 'all' ) {
			$clauses[ 'join' ] .= " JOIN {$wpdb->prefix}icl_translations icltr1 ON
                                    icltr1.element_id = {$wpdb->comments}.comment_ID
                                    JOIN {$wpdb->prefix}icl_translations icltr2 ON
                                    icltr2.element_id = {$wpdb->comments}.comment_post_ID
                                    ";
			$clauses[ 'where' ] .= " AND icltr1.element_type = 'comment'
                                   AND icltr1.language_code = '" . $current_language . "'
                                   AND icltr2.language_code = '" . $current_language . "'
                                   AND icltr2.element_type LIKE 'post\\_%' ";
		}

		return $clauses;
	}

	function language_filter()
	{
		require_once ICL_PLUGIN_PATH . '/inc/cache.php';
		global $wpdb;

		$type = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : 'post';

		if ( !in_array( $type, array( 'post', 'page' ) ) && !in_array( $type, array_keys( $this->get_translatable_documents() ) ) ) {
			return;
		}

		$active_languages = $this->get_active_languages();

		$post_status = get_query_var( 'post_status' );
		if ( is_string( $post_status ) ) {
			$post_status = $post_status ? array( $post_status ) : array();
		}

		$key = join( ',', $post_status );
		if ( $key ) {
			$key = '#' . $key;
		}
		$languages = icl_cache_get( $type . 's_per_language' . $key );

		if ( !$languages ) {

			$extra_conditions = "";
			if ( $post_status ) {
				$extra_conditions .= apply_filters( '_icl_posts_language_count_status', " AND post_status IN('" . join( "','", $post_status ) . "') " );
			}
			if ( $post_status != array( 'trash' ) ) {
				$extra_conditions .= " AND post_status <> 'trash'";
			}
			// dont count auto drafts
			$extra_conditions .= " AND post_status <> 'auto-draft'";

			// only active language
			$extra_conditions .= " AND t.language_code IN ('" . join( "','", array_keys( $active_languages ) ) . "') ";

			$res = $wpdb->get_results( "
                SELECT language_code, COUNT(p.ID) AS c FROM {$wpdb->prefix}icl_translations t
                JOIN {$wpdb->posts} p ON t.element_id=p.ID
                JOIN {$wpdb->prefix}icl_languages l ON t.language_code=l.code AND l.active = 1
                WHERE p.post_type='{$type}' AND t.element_type='post_{$type}' {$extra_conditions}
                GROUP BY language_code
                " );

			$languages[ 'all' ] = 0;
			foreach ( $res as $r ) {
				$languages[ $r->language_code ] = $r->c;
				$languages[ 'all' ] += $r->c;
			}
			icl_cache_set( $type . 's_per_language' . $key, $languages );
		}

		$active_languages[ ] = array( 'code' => 'all', 'display_name' => __( 'All languages', 'sitepress' ) );
		$as = array();
		foreach ( $active_languages as $lang ) {
			if ( $lang[ 'code' ] == $this->this_lang ) {
				$px = '<strong>';
				$sx = ' <span class="count">(' . @intval( $languages[ $lang[ 'code' ] ] ) . ')<\/span><\/strong>';
			} elseif ( !isset( $languages[ $lang[ 'code' ] ] ) ) {
				$px = '<span>';
				$sx = '<\/span>';
			} else {
				if ( $post_status ) {
					$px = '<a href="?post_type=' . $type . '&post_status=' . join( ',', $post_status ) . '&lang=' . $lang[ 'code' ] . '">';
				} else {
					$px = '<a href="?post_type=' . $type . '&lang=' . $lang[ 'code' ] . '">';
				}
				$sx = '<\/a> <span class="count">(' . intval( $languages[ $lang[ 'code' ] ] ) . ')<\/span>';
			}
			$as[ ] = $px . $lang[ 'display_name' ] . $sx;
		}
		$allas = join( ' | ', $as );
		if ( empty( $this->settings[ 'hide_how_to_translate' ] ) && $type == 'page' && !$this->get_icl_translation_enabled() ) {
			$prot_link = '<span id="icl_how_to_translate_link" class="button" style="padding-right:3px;" ><img align="baseline" src="' . ICL_PLUGIN_URL . '/res/img/icon16.png" width="16" height="16" style="margin-bottom:-4px" /> <a href="https://wpml.org/?page_id=3416">' . __( 'How to translate', 'sitepress' ) . '</a><a href="#" title="' . esc_attr__( 'hide this', 'sitepress' ) . '" onclick=" if(confirm(\\\'' . __( 'Are you sure you want to remove this button?', 'sitepress' ) . '\\\')) jQuery.ajax({url:icl_ajx_url,type:\\\'POST\\\',data:{icl_ajx_action:\\\'update_option\\\', option:\\\'hide_how_to_translate\\\',value:1,_icl_nonce:\\\'' . wp_create_nonce( 'update_option_nonce' ) . '\\\'},success:function(){jQuery(\\\'#icl_how_to_translate_link\\\').fadeOut()}});return false;" style="outline:none;"><img src="' . ICL_PLUGIN_URL . '/res/img/close2.png" width="10" height="10" style="border:none" alt="' . esc_attr__( 'hide', 'sitepress' ) . '" /><\/a>' . '<\/span>';
		} else {
			$prot_link = '';
		}
		?>
		<script type="text/javascript">
			jQuery(".subsubsub").append('<br /><span id="icl_subsubsub"><?php echo $allas ?></span><br /><?php echo $prot_link ?>');
		</script>
	<?php
	}

	function exclude_other_language_pages2( $arr )
	{
		$new_arr = $arr;

		$current_language = $this->get_current_language();
		if ( $current_language != 'all' ) {

			$cache_key = md5(json_encode($new_arr));
			$cache_group = 'exclude_other_language_pages2';
			$found = false;

			$result = wp_cache_get($cache_key, $cache_group, false, $found);

			if(!$found) {
				global $wpdb;

				if ( is_array( $new_arr ) && !empty( $new_arr[ 0 ]->post_type ) ) {
					$post_type = $new_arr[ 0 ]->post_type;
				} else {
					$post_type = 'page';
				}

				$filtered_pages = array();
				// grab list of pages NOT in the current language
				$excl_pages = $wpdb->get_col( $wpdb->prepare( "
				SELECT p.ID FROM {$wpdb->posts} p
				JOIN {$wpdb->prefix}icl_translations t ON p.ID = t.element_id
				WHERE t.element_type=%s AND p.post_type=%s AND t.language_code <> %s
				", 'post_' . $post_type, $post_type, $current_language ) );
				// exclude them from the result set

				if ( !empty( $new_arr ) ) {
					foreach ( $new_arr as $page ) {
						if ( !in_array( $page->ID, $excl_pages ) ) {
							$filtered_pages[ ] = $page;
						}
					}

					$new_arr = $filtered_pages;
				}
				wp_cache_set($cache_key, $new_arr, $cache_group);
			} else {
				$new_arr = $result;
			}
		}

		return $new_arr;
	}

	function wp_dropdown_pages( $output )
	{
		global $wpdb;
		if ( isset( $_POST[ 'lang_switch' ] ) ) {
			$post_id = esc_sql( $_POST[ 'lang_switch' ] );
			$lang    = esc_sql( strip_tags( $_GET[ 'lang' ] ) );
			$parent  = $wpdb->get_var( $wpdb->prepare( "SELECT post_parent FROM {$wpdb->posts} WHERE ID=%d", $post_id ) );
			if ( $parent ) {
				$trid                 = $wpdb->get_var( "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id='{$parent}' AND element_type='post_page'" );
				$translated_parent_id = $wpdb->get_var( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid='{$trid}' AND element_type='post_page' AND language_code='{$lang}'" );
				if ( $translated_parent_id ) {
					$output = str_replace( 'selected="selected"', '', $output );
					$output = str_replace( 'value="' . $translated_parent_id . '"', 'value="' . $translated_parent_id . '" selected="selected"', $output );
				}
			}
		} elseif ( isset( $_GET[ 'lang' ] ) && isset( $_GET[ 'trid' ] ) ) {
			$lang                 = esc_sql( strip_tags( $_GET[ 'lang' ] ) );
			$trid                 = esc_sql( $_GET[ 'trid' ] );
			$post_type            = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : 'page';
			$elements_id          = $wpdb->get_col( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations
                 WHERE trid=%d AND element_type=%s AND element_id IS NOT NULL", $trid, 'post_' . $post_type ) );
			$translated_parent_id = 0;
			foreach ( $elements_id as $element_id ) {
				$parent               = $wpdb->get_var( $wpdb->prepare( "SELECT post_parent FROM {$wpdb->posts} WHERE ID=%d", $element_id ) );
				$trid                 = $wpdb->get_var( $wpdb->prepare( "
                    SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $parent, 'post_' . $post_type ) );
				$translated_parent_id = $wpdb->get_var( $wpdb->prepare( "
                    SELECT element_id FROM {$wpdb->prefix}icl_translations
                    WHERE trid=%d AND element_type=%s AND language_code=%s", $trid, 'post_' . $post_type, $lang ) );
				if ( $translated_parent_id ) {
					break;
				}
			}
			if ( $translated_parent_id ) {
				$output = str_replace( 'selected="selected"', '', $output );
				$output = str_replace( 'value="' . $translated_parent_id . '"', 'value="' . $translated_parent_id . '" selected="selected"', $output );
			}
		}
		if ( !$output ) {
			$output = '<select id="parent_id"><option value="">' . __( 'Main Page (no parent)', 'sitepress' ) . '</option></select>';
		}

		return $output;
	}

	function edit_term_form( $term )
	{
		include ICL_PLUGIN_PATH . '/menu/taxonomy-menu.php';
	}

	function wp_dropdown_cats_select_parent( $html )
	{
		global $wpdb;
		if ( isset( $_GET[ 'trid' ] ) ) {
			$element_type     = $taxonomy = isset( $_GET[ 'taxonomy' ] ) ? $_GET[ 'taxonomy' ] : 'post_tag';
			$icl_element_type = 'tax_' . $element_type;
			$trid             = intval( $_GET[ 'trid' ] );
			$source_lang      = isset( $_GET[ 'source_lang' ] ) ? $_GET[ 'source_lang' ] : $this->get_default_language();
			$parent           = $wpdb->get_var( "
                SELECT parent
                FROM {$wpdb->term_taxonomy} tt
                    JOIN {$wpdb->prefix}icl_translations tr ON tr.element_id=tt.term_taxonomy_id
                        AND tr.element_type='{$icl_element_type}' AND tt.taxonomy='{$taxonomy}'
                WHERE trid='{$trid}' AND tr.language_code='{$source_lang}'
            " );
			if ( $parent ) {
				$parent = icl_object_id( $parent, $element_type );
				$html   = str_replace( 'value="' . $parent . '"', 'value="' . $parent . '" selected="selected"', $html );
			}
		}

		return $html;
	}

	function add_language_selector_to_page( $active_languages, $selected_language, $translations, $element_id, $type )
	{
		?>
		<div id="icl_tax_menu" style="display:none">

		<div id="dashboard-widgets" class="metabox-holder">
		<div class="postbox-container" style="width: 99%;line-height:normal;">

		<div id="icl_<?php echo $type ?>_lang" class="postbox" style="line-height:normal;">
		<h3 class="hndle">
			<span><?php echo __( 'Language', 'sitepress' ) ?></span>
		</h3>
		<div class="inside" style="padding: 10px;">

		<select name="icl_<?php echo $type ?>_language">

			<?php
			foreach ( $active_languages as $lang ) {
				if ( $lang[ 'code' ] == $selected_language ) {
					?>
					<option value="<?php echo $selected_language ?>" selected="selected"><?php echo $lang[ 'display_name' ] ?></option>
				<?php
				}
			}
			?>

			<?php foreach ( $active_languages as $lang ): ?>
				<?php if ( $lang[ 'code' ] == $selected_language || ( isset( $translations[ $lang[ 'code' ] ]->element_id ) && $translations[ $lang[ 'code' ] ]->element_id != $element_id ) ) {
					continue;
} ?>
				<option value="<?php echo $lang[ 'code' ] ?>"<?php if ( $selected_language == $lang[ 'code' ] ): ?> selected="selected"<?php endif; ?>><?php echo $lang[ 'display_name' ] ?></option>
			<?php endforeach; ?>
		</select>
	<?php
	}

	function get_category_name( $id )
	{
		_deprecated_function( __FUNCTION__, '2.3.1', 'get_cat_name()' );
		global $wpdb;
		$term_id = $wpdb->get_var( "SELECT term_id FROM {$wpdb->prefix}term_taxonomy WHERE term_taxonomy_id = {$id}" );
		if ( $term_id ) {
			return $wpdb->get_var( "SELECT name FROM {$wpdb->prefix}terms WHERE term_id = {$term_id}" );
		} else {
			return null;
		}
	}

	function add_translation_of_selector_to_page( $trid, $selected_language, $default_language, $source_language, $untranslated_ids, $element_id, $type )
	{
		global $wpdb;

		?>
		<input type="hidden" name="icl_trid" value="<?php echo $trid ?>"/>

		<?php
		if ( $selected_language != $default_language && 'all' != $this->get_current_language() ) {
			?>
			<br/><br/>
			<?php echo __( 'This is a translation of', 'sitepress' ); ?><br/>
			<select name="icl_translation_of" id="icl_translation_of"<?php if ( ( !isset( $_GET[ 'action' ] ) || $_GET[ 'action' ] != 'edit' ) && $trid ) {
				echo " disabled";
			} ?>>
			<?php
				if ( !$source_language || $source_language == $default_language ) {
					if ( $trid ) {
						?>
						<option value="none"><?php echo __( '--None--', 'sitepress' ) ?></option>
						<?php
						//get source
						$src_language_id = $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND language_code=%s", $trid, $default_language ) );
						if ( !$src_language_id ) {
							// select the first id found for this trid
							$src_language_id = $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d", $trid ) );
						}
						if ( $src_language_id && $src_language_id != $element_id ) {
							$term_id            = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $src_language_id ) );
							$src_language_title = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->terms} WHERE term_id=%d", $term_id ) );
						}

						if ( !empty( $src_language_title ) ) {
							?>
							<option value="<?php echo $src_language_id; ?>" selected="selected"><?php echo $src_language_title; ?></option>
						<?php
						}
					} else {
						?>
						<option value="none" selected="selected"><?php echo __( '--None--', 'sitepress' ); ?></option>
					<?php
					}
					foreach ( $untranslated_ids as $translation_of_id ) {
						$title = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->terms} WHERE term_id=%d", $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $translation_of_id ) ) ) );

						if ( !empty( $title ) ) {
							?>
							<option value="<?php echo $translation_of_id; ?>"><?php echo $title; ?></option>
						<?php
						}
					}
				} else {
					if ( $trid ) {

						$src_language_title = false;
						// add the source language
						$src_language_id = $wpdb->get_var( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid={$trid} AND language_code='{$source_language}'" );
						if ( $src_language_id ) {
							$term_id            = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $src_language_id ) );
							$src_language_title = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->terms} WHERE term_id=%d", $term_id ) );
						}

						if ( $src_language_title ) {
							?>
							<option value="<?php echo $src_language_id; ?>" selected="selected"><?php echo $src_language_title; ?></option>
						<?php
						}
					} else {
						?>
						<option value="none" selected="selected"><?php echo __( '--None--', 'sitepress' ); ?></option>
					<?php
					}
				}
				?>
			</select>

		<?php
		}
	}

	function add_translate_options( $trid, $active_languages, $selected_language, $translations, $type )
	{
		if ( $trid && isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'edit' ):
	?>

		<div id="icl_translate_options">

			<?php
			// count number of translated and un-translated pages.
			$translations_found = 0;
			$untranslated_found = 0;
			foreach ( $active_languages as $lang ) {
				if ( $selected_language == $lang[ 'code' ] ) {
					continue;
				}
				if ( isset( $translations[ $lang[ 'code' ] ]->element_id ) ) {
					$translations_found += 1;
				} else {
					$untranslated_found += 1;
				}
			}
			?>

			<?php if ( $untranslated_found > 0 ): ?>

				<table cellspacing="1" class="icl_translations_table" style="min-width:200px;margin-top:10px;">
					<thead>
					<tr>
						<th colspan="2" style="padding:4px;background-color:#DFDFDF"><b><?php _e( 'Translate', 'sitepress' ); ?></b></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( $active_languages as $lang ): if ( $selected_language == $lang[ 'code' ] ) {
						continue;
					} ?>
						<tr>
							<?php if ( !isset( $translations[ $lang[ 'code' ] ]->element_id ) ): ?>
								<td style="padding:4px;line-height:normal;"><?php echo $lang[ 'display_name' ] ?></td>
								<?php
								$taxonomy = $_GET[ 'taxonomy' ];
								$post_type_q = isset( $_GET[ 'post_type' ] ) ? '&amp;post_type=' . esc_html( $_GET[ 'post_type' ] ) : '';
								$add_link = admin_url( "edit-tags.php?taxonomy=" . esc_html( $taxonomy ) . "&amp;trid=" . $trid . "&amp;lang=" . $lang[ 'code' ] . "&amp;source_lang=" . $selected_language . $post_type_q );
								?>
								<td style="padding:4px;line-height:normal;"><a href="<?php echo $add_link ?>"><?php echo __( 'add', 'sitepress' ) ?></a></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( $translations_found > 0 ): ?>
				<p style="clear:both;margin:5px 0 5px 0">
					<b><?php _e( 'Translations', 'sitepress' ) ?></b>
					(<a class="icl_toggle_show_translations" href="#" <?php if (empty( $this->settings[ 'show_translations_flag' ] )): ?>style="display:none;"<?php endif; ?>><?php _e( 'hide', 'sitepress' ) ?></a><a
						class="icl_toggle_show_translations" href="#" <?php if (!empty( $this->settings[ 'show_translations_flag' ] )): ?>style="display:none;"<?php endif; ?>><?php _e( 'show', 'sitepress' ) ?></a>)

					<?php wp_nonce_field( 'toggle_show_translations_nonce', '_icl_nonce_tst' ) ?>
				<table cellspacing="1" width="100%" id="icl_translations_table" style="<?php if ( empty( $this->settings[ 'show_translations_flag' ] ) ): ?>display:none;<?php endif; ?>margin-left:0;">

					<?php foreach ( $active_languages as $lang ): if ( $selected_language == $lang[ 'code' ] )
						continue; ?>
						<tr>
							<?php if ( isset( $translations[ $lang[ 'code' ] ]->element_id ) ): ?>
								<td style="line-height:normal;"><?php echo $lang[ 'display_name' ] ?></td>
								<?php
								$taxonomy = $_GET[ 'taxonomy' ];
								$post_type_q = isset( $_GET[ 'post_type' ] ) ? '&amp;post_type=' . esc_html( $_GET[ 'post_type' ] ) : '';
								$edit_link = admin_url( "edit-tags.php?taxonomy=" . esc_html( $taxonomy ) . "&amp;action=edit&amp;tag_ID=" . $translations[ $lang[ 'code' ] ]->term_id . "&amp;lang=" . $lang[ 'code' ] . $post_type_q );
								?>
								<td align="right" width="30%"
									style="line-height:normal;"><?php echo isset( $translations[ $lang[ 'code' ] ]->name ) ? '<a href="' . $edit_link . '" title="' . __( 'Edit', 'sitepress' ) . '">' . $translations[ $lang[ 'code' ] ]->name . '</a>' : __( 'n/a', 'sitepress' ) ?></td>

							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</table>



			<?php endif; ?>

			<br clear="all" style="line-height:1px;"/>

		</div>
	<?php
		endif;
	}

	function create_term( $cat_id, $tt_id )
	{
		global $wpdb, $wp_taxonomies;

		$default_language = $this->get_default_language();

		// case of ajax inline category creation
		// ajax actions
		$ajx_actions = array();
		foreach ( $wp_taxonomies as $ktx => $tx ) {
			$ajx_actions[ ] = 'add-' . $ktx;
		}
		if ( isset( $_POST[ '_ajax_nonce' ] ) && in_array( $_POST[ 'action' ], $ajx_actions ) ) {
			$referer    = $_SERVER[ 'HTTP_REFERER' ];
			$url_pieces = parse_url( $referer );
			@parse_str( $url_pieces[ 'query' ], $qvars );
			if ( !empty( $qvars[ 'post' ] ) ) {
				$post_type    = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $qvars[ 'post' ] ) );
				$term_lang    = $qvars[ 'lang' ];
				if($this->is_translated_post_type($post_type)) {
					$lang_details = $this->get_element_language_details( $qvars[ 'post' ], 'post_' . $post_type );
					if(isset($lang_details->language_code)) {
						$term_lang    = $lang_details->language_code;
					}
				}
			} else {
				$term_lang = isset( $qvars[ 'lang' ] ) ? $qvars[ 'lang' ] : $this->get_language_cookie();
			}
		}

		$el_type = $wpdb->get_var( "SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id={$tt_id}" );

		if ( !$this->is_translated_taxonomy( $el_type ) ) {
			return;
		};

		$icl_el_type = 'tax_' . $el_type;

		// case of adding a tag via post save
		$post_action = isset( $_POST[ 'action' ] ) ? $_POST[ 'action' ] : false;
		if ( $post_action == 'editpost' && !empty( $_POST[ 'icl_post_language' ] ) ) {
			$term_lang = $_POST[ 'icl_post_language' ];
		} elseif ( $post_action == 'post-quickpress-publish' ) {
			$term_lang = $default_language;
		} elseif ( $post_action == 'inline-save-tax' ) {
			$lang_details = $this->get_element_language_details( $tt_id, $icl_el_type );
			$term_lang    = $lang_details->language_code;
		} elseif ( $post_action == 'inline-save' ) {
			$post_type    = $wpdb->get_var( "SELECT post_type FROM {$wpdb->posts} WHERE ID=" . $_POST[ 'post_ID' ] );
			$lang_details = $this->get_element_language_details( $_POST[ 'post_ID' ], 'post_' . $post_type );
			$term_lang    = $lang_details->language_code;
		}

		// has trid only when it's a translation of another tag
		$trid = isset( $_POST[ 'icl_trid' ] ) && ( isset( $_POST[ 'icl_' . $icl_el_type . '_language' ] ) ) ? $_POST[ 'icl_trid' ] : null;
		// see if we have a "translation of" setting.
		$src_language = false;
		if ( isset( $_POST[ 'icl_translation_of' ] ) && $_POST[ 'icl_translation_of' ] ) {
			$src_term_id = $_POST[ 'icl_translation_of' ];
			$trid = $this->get_element_trid( $src_term_id, $icl_el_type );
			if ( $src_term_id != 'none' && $trid ) {
				$language_details = $this->get_element_language_details( $trid, $icl_el_type );
				if ( empty( $language_details ) || !is_object( $language_details ) || !isset( $language_details->source_language_code ) ) {
					$src_language = null;
				} else {
					$src_language = $language_details->source_language_code;
				}
			} else {
				$trid = null;
			}
		}

		if ( !isset( $term_lang ) ) {
			$term_lang = isset( $_POST[ 'icl_' . $icl_el_type . '_language' ] ) ? $_POST[ 'icl_' . $icl_el_type . '_language' ] : $this->this_lang;
		}

		if ( $post_action == 'inline-save-tax' || $post_action == 'add-' . $el_type ) {
			$trid = $this->get_element_trid( $tt_id, $icl_el_type );
		}

		// set term language if front-end translation creating

		$term_lang = apply_filters( 'wpml_create_term_lang', $term_lang );

		$this->set_element_language_details( $tt_id, $icl_el_type, $trid, $term_lang, $src_language );

		// sync translations parent
		if ( $this->settings[ 'sync_taxonomy_parents' ] && isset( $_POST[ 'parent' ] ) && $term_lang == $default_language ) {
			$parent       = intval( $_POST[ 'parent' ] );
			$translations = $this->get_element_translations( $trid, $icl_el_type );
			$taxonomy = isset($_POST[ 'taxonomy' ]) ? $_POST[ 'taxonomy' ] : false;
			foreach ( $translations as $lang => $translation ) {
				if ( $lang != $default_language ) {
					$translated_parent = false;
					//check for translation only if we know the id
					if ( $parent > 0 ) {
						$translated_parent = icl_object_id( $parent, $el_type, false, $lang );
					}
					//update information about parent only if translation exists or we are setting parent to None
					if ( $parent == 0 || $parent == -1 || $translated_parent != $parent ) {
						$wpdb->update( $wpdb->term_taxonomy, array( 'parent' => $translated_parent ), array( 'term_taxonomy_id' => $translation->element_id ) );
					}
				}
			}

			$this->update_terms_relationship_cache( $parent, $taxonomy );

		}

	}

	function get_language_for_term( $term_id, $el_type )
	{
		global $wpdb;
		$term_id = $wpdb->get_var( "SELECT term_taxonomy_id FROM {$wpdb->prefix}term_taxonomy WHERE term_id = {$term_id}" );
		if ( $term_id ) {
			return $wpdb->get_var( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = {$term_id} AND element_type = '{$el_type}'" );
		} else {
			return $this->get_default_language();
		}
	}

	function pre_term_name( $value, $taxonomy )
	{
		//allow adding terms with the same name in different languages
		global $wpdb;
		//check if term exists
		$term_id = $wpdb->get_var( "SELECT term_id FROM {$wpdb->terms} WHERE name='" . esc_sql( $value ) . "'" );
		// translate to WPML notation
		$taxonomy = 'tax_' . $taxonomy;
		if ( !empty( $term_id ) ) {
			if ( isset( $_POST[ 'icl_' . $taxonomy . '_language' ] ) ) {
				// see if the term_id is for a different language
				$this_lang = $_POST[ 'icl_' . $taxonomy . '_language' ];
				if ( $this_lang != $this->get_language_for_term( $term_id, $taxonomy ) ) {
					if ( $this_lang != $this->get_default_language() ) {
						$value .= ' @' . $_POST[ 'icl_' . $taxonomy . '_language' ];
					}
				}
			}
		}

		return $value;
	}

	function pre_save_category()
	{
		// allow adding categories with the same name in different languages
		if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'add-cat' ) {
			if ( category_exists( $_POST[ 'cat_name' ] ) && isset( $_POST[ 'icl_category_language' ] ) && $_POST[ 'icl_category_language' ] != $this->get_default_language() ) {
				$_POST[ 'cat_name' ] .= ' @' . $_POST[ 'icl_category_language' ];
			}
		}
	}

	function delete_term( $cat, $tt_id, $taxonomy )
	{
		global $wpdb;
		$icl_el_type = 'tax_' . $taxonomy;

		static $recursion;
		if ( $this->settings[ 'sync_delete_tax' ] && empty( $recursion ) ) {

			// only for translated
			$lang_details = $this->get_element_language_details( $tt_id, $icl_el_type );
			if ( empty( $lang_details->source_language_code ) ) {

				// get translations
				$trid         = $this->get_element_trid( $tt_id, $icl_el_type );
				$translations = $this->get_element_translations( $trid, $icl_el_type );

				$recursion = true;
				// delete translations
				foreach ( $translations as $translation ) {
					if ( $translation->element_id != $tt_id ) {
						wp_delete_term( $translation->term_id, $taxonomy );
					}
				}
				$recursion = false;
			}
		}

		$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translations WHERE element_type ='{$icl_el_type}' AND element_id='{$tt_id}' LIMIT 1" );
	}

	function get_term_filter( $_term, $taxonomy )
	{
		// case of calling from get_category_parents
		$debug_backtrace = $this->get_backtrace( 6 ); //Limit to first 6 stack frames, since 5 is the highest index we use
		if ( isset( $debug_backtrace[ 5 ][ 'function' ] ) && $debug_backtrace[ 5 ][ 'function' ] == 'get_category_parents' ) {
			$_term->name = $this->the_category_name_filter( $_term->name );
		}

		return $_term;
	}

	function terms_language_filter()
	{
		global $wpdb;

		$taxonomy         = isset( $_GET[ 'taxonomy' ] ) ? $_GET[ 'taxonomy' ] : 'post_tag';
		$icl_element_type = 'tax_' . $taxonomy;

		$active_languages = $this->get_active_languages();
		$current_language=$this->get_current_language();
		$default_language=$this->get_default_language();

		$res   = $wpdb->get_results( "
            SELECT language_code, COUNT(tm.term_id) AS c FROM {$wpdb->prefix}icl_translations t
            JOIN {$wpdb->term_taxonomy} tt ON t.element_id = tt.term_taxonomy_id
            JOIN {$wpdb->terms} tm ON tt.term_id = tm.term_id
            JOIN {$wpdb->prefix}icl_languages l ON t.language_code = l.code
            WHERE t.element_type='{$icl_element_type}' AND tt.taxonomy='{$taxonomy}' AND l.active=1
            GROUP BY language_code
            " );
		$languages = array( 'all' => 0 );
		foreach ( $res as $r ) {
			$languages[ $r->language_code ] = $r->c;
			$languages[ 'all' ] += $r->c;
		}
		$active_languages[ ] = array( 'code' => 'all', 'display_name' => __( 'All languages', 'sitepress' ) );
		$languages_links = array();
		foreach ( $active_languages as $lang ) {
			if ( $lang[ 'code' ] == $this->this_lang ) {
				$px = '<strong>';
				$sx = ' (' . @intval( $languages[ $lang[ 'code' ] ] ) . ')<\/strong>';
				/*
            }elseif(!isset($langs[$lang['code']])){
                $px = '<span>';
                $sx = '<\/span>';
            */
			} else {
				$px = '<a href="?taxonomy=' . $taxonomy . '&amp;lang=' . $lang[ 'code' ];
				$px .= isset( $_GET[ 'post_type' ] ) ? '&amp;post_type=' . $_GET[ 'post_type' ] : '';
				$px .= '">';
				$sx = '<\/a> (' . @intval( $languages[ $lang[ 'code' ] ] ) . ')';
			}
			$languages_links[ ] = $px . $lang[ 'display_name' ] . $sx;
		}
		$all_languages_links = join( ' | ', $languages_links );
		?>
		<script type="text/javascript">
			jQuery('table.widefat').before('<span id="icl_subsubsub"><?php echo $all_languages_links ?><\/span>');
			<?php // the search form, add language ?>
			<?php if($current_language != $default_language): ?>
			jQuery('.search-form').append('<input type="hidden" name="lang" value="<?php echo $current_language ?>" />');
			<?php endif; ?>
		</script>
	<?php
	}

	function get_terms_args_filter( $args )
	{
		// Unique cache domain for each language.
		if ( isset( $args[ 'cache_domain' ] ) ) {
			$args[ 'cache_domain' ] .= '_' . $this->get_current_language();
		}

		// special case for when term hierarchy is cached in wp_options
		$debug_backtrace = $this->get_backtrace( 5 ); //Limit to first 5 stack frames, since 4 is the highest index we use
		if ( isset( $debug_backtrace[ 4 ] ) && $debug_backtrace[ 4 ][ 'function' ] == '_get_term_hierarchy' ) {
			$args[ '_icl_show_all_langs' ] = true;
		}

		return $args;
	}

	function exclude_other_terms( $exclusions, $args )
	{
		if ( !version_compare( $GLOBALS[ 'wp_version' ], '3.1', '>=' ) ) {
			$default_language = $this->get_default_language();

			// special case for when term hierarchy is cached in wp_options
			if ( isset( $args[ '_icl_show_all_langs' ] ) && $args[ '_icl_show_all_langs' ] )
				return $exclusions;

			// get_terms doesn't seem to have a filter that can be used efficiently in order to filter the terms by language
			// in addition the taxonomy name is not being passed to this filter we're using 'list_terms_exclusions'
			// getting the taxonomy name from debug_backtrace

			global $wpdb, $pagenow;

			$taxonomy = false;
			if ( isset( $_GET[ 'taxonomy' ] ) ) {
				$taxonomy = $_GET[ 'taxonomy' ];
			} elseif ( isset( $args[ 'taxonomy' ] ) ) {
				$taxonomy = $args[ 'taxonomy' ];
			} elseif ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'get-tagcloud' ) {
				$taxonomy = $_POST[ 'tax' ];
			} else {
				if ( in_array( $pagenow, array( 'post-new.php', 'post.php', 'edit.php' ) ) ) {
					$debug_backtrace = $this->get_backtrace( 4, false, true ); //Limit to first 4 stack frames, since 3 is the highest index we use
					if ( isset( $debug_backtrace[ 3 ][ 'args' ][ 0 ] ) ) {
						$taxonomy = $debug_backtrace[ 3 ][ 'args' ][ 0 ];
					} else {
						$taxonomy = 'post_tag';
					}
				}
			}

			if ( ! $taxonomy || ! $this->is_translated_taxonomy( $taxonomy ) ) {
				return $exclusions;
			}

			$icl_element_type = 'tax_' . $taxonomy;

			if ( isset( $_GET[ 'lang' ] ) && $_GET[ 'lang' ] == 'all' ) {
				return $exclusions;
			}
			if ( isset( $_GET[ 'tag_ID' ] ) && $_GET[ 'tag_ID' ] ) {
				$element_lang_details = $this->get_element_language_details( $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy=%s", $_GET[ 'tag_ID' ], $taxonomy ) ), $icl_element_type );
				$this_lang            = $element_lang_details->language_code;
			} elseif ( $this->this_lang != $default_language ) {
				$this_lang = $this->get_current_language();
			} elseif ( isset( $_GET[ 'post' ] ) ) {
				$icl_post_type        = isset( $_GET[ 'post_type' ] ) ? 'post_' . $_GET[ 'post_type' ] : 'post_' . $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $_GET[ 'post' ] ) );
				$element_lang_details = $this->get_element_language_details( $_GET[ 'post' ], $icl_post_type );
				$this_lang            = $element_lang_details ? $element_lang_details->language_code : $default_language;
			} elseif ( isset( $_POST[ 'action' ] ) && ( $_POST[ 'action' ] == 'get-tagcloud' || $_POST[ 'action' ] == 'menu-quick-search' ) ) {
				if ( ! isset( $_SERVER[ 'HTTP_REFERER' ] ) ) {
					$this_lang = $default_language;
				} else {
					$urlparts = parse_url( $_SERVER[ 'HTTP_REFERER' ] );
					@parse_str( $urlparts[ 'query' ], $qvars );
					$this_lang = isset( $qvars[ 'lang' ] ) ? $qvars[ 'lang' ] : $default_language;
				}
			} else {
				$this_lang = $default_language;
			}
			$exclude    = $wpdb->get_col( "
            SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt
            LEFT JOIN {$wpdb->terms} tm ON tt.term_id = tm.term_id
            LEFT JOIN {$wpdb->prefix}icl_translations t ON (tt.term_taxonomy_id = t.element_id OR t.element_id IS NULL)
            WHERE tt.taxonomy='{$taxonomy}' AND t.element_type='{$icl_element_type}' AND t.language_code <> '{$this_lang}'
            " );
			$exclude[ ] = 0;
			$exclusions .= ' AND tt.term_taxonomy_id NOT IN (' . join( ',', $exclude ) . ')';
		}
		return $exclusions;
	}

	function terms_clauses( $clauses, $taxonomies, $args )
	{
		if ( version_compare( $GLOBALS[ 'wp_version' ], '3.1', '>=' ) ) {

			global $wpdb;

			// special case for when term hierarchy is cached in wp_options
			$debug_backtrace = $this->get_backtrace( 6 ); //Limit to first 5 stack frames, since 4 is the highest index we use
			if ( isset( $debug_backtrace[ 4 ] ) && $debug_backtrace[ 4 ][ 'function' ] == '_get_term_hierarchy' ) {
				return $clauses;
			}

			//Todo: to remove: this is for debug purposes and only temporary
			if(defined('WP_DEBUG') && WP_DEBUG===true) {
				$wp_upload_dir = wp_upload_dir();
				$icl_log_file = $wp_upload_dir['basedir'] . "/wpml.debug.txt";
				foreach($debug_backtrace as $index => $data) {
					if($index!=4 && $debug_backtrace[ $index ][ 'function' ] == '_get_term_hierarchy') {

						file_put_contents($icl_log_file, '_get_term_hierarchy found on position ' . $index . PHP_EOL, FILE_APPEND);
						file_put_contents($icl_log_file, 'Stack: ' . print_r($debug_backtrace, true) . PHP_EOL, FILE_APPEND);
					}
				}
			}

			$int            = preg_match( '#tt\.taxonomy IN \(([^\)]+)\)#', $clauses[ 'where' ], $matches );
			$left_join      = '';
			$icl_taxonomies = array();
			if ( $int ) {
				$exp = explode( ',', $matches[ 1 ] );
				foreach ( $exp as $v ) {
					$tax = trim( $v, ' \'' );
					if ( $this->is_translated_taxonomy( $tax ) ) {
						$icl_taxonomies[ ] = 'tax_' . $tax;
					} else {
						$left_join = ' LEFT';
					}
				}
			} else {
				// taxonomy type not found
				return $clauses;
			}

			if ( empty( $icl_taxonomies ) )
				return $clauses;

			$icl_taxonomies = "'" . join( "','", $icl_taxonomies ) . "'";

			$lang = $this->get_current_language();
			if ( $lang == 'all' ) {
				$left_join  = ' LEFT';
				$where_lang = '';
			} else {
				$where_lang = " AND icl_t.language_code = '{$lang}'";
			}

			$clauses[ 'join' ] .= "{$left_join} JOIN {$wpdb->prefix}icl_translations icl_t ON icl_t.element_id = tt.term_taxonomy_id";
			$clauses[ 'where' ] .= "{$where_lang} AND icl_t.element_type IN({$icl_taxonomies})";

			//echo '<pre>' . print_r($clauses) . '</pre>';
		}

		return $clauses;
	}
	
	function set_wp_query()
	{
		global $wp_query;
		$this->wp_query = $wp_query;
	}

	// filter for WP home_url function
	function home_url( $url, $path, $orig_scheme, $blog_id )
	{
		$debug_backtrace = $this->get_backtrace( 7 ); //Limit to first 7 stack frames, since 6 is the highest index we use
		// exception for get_page_num_link and language_negotiation_type = 3
		if ( $this->settings[ 'language_negotiation_type' ] == 3 ) {
			if ( !empty( $debug_backtrace[ 6 ] ) && $debug_backtrace[ 6 ][ 'function' ] == 'get_pagenum_link' )
				return $url;
		}

		$convert_url = false;

		// only apply this in some specific cases (1)
		if(isset($debug_backtrace[5]) && $debug_backtrace[5]['function']=='get_post_type_archive_link') {
			$convert_url = true;
		}

		remove_filter( 'home_url', array( $this, 'home_url' ), 1 );

		// only apply this in some specific cases (2)
		if(!$convert_url && ( did_action( 'template_redirect' ) && rtrim( $url, '/' ) == rtrim( get_home_url(), '/' ) ) || $path == '/') {
			$convert_url = true;
		}

		if ( $convert_url ) {
			$url = $this->convert_url( $url );
		}
		add_filter( 'home_url', array( $this, 'home_url' ), 1, 4 );

		return $url;
	}

	/**
	 * Converts WP generated url to language specific based on plugin settings
	 *
	 * @param string      $url
	 * @param null|string $code	(if null, fallback to detaulf language for root page, or current language in all other cases)
	 *
	 * @return bool|string
	 */
	function convert_url( $url, $code = null ) {
		if(!$url) return false;

		$default_language          = $this->get_default_language();
		$language_negotiation_type = $this->settings[ 'language_negotiation_type' ];

		if ( is_null( $code ) && $language_negotiation_type == '2' && isset( $this->settings[ 'language_domains' ] ) ) {
			foreach ( $this->settings[ 'language_domains' ] as $lang => $domain ) {
				$domain = preg_replace( '/^https?\:\/\//', '', $domain );
				$domain_data = explode('/', $domain);
				$domain = $domain_data[0];
				if ( $domain == $this->get_server_host_name() ) {
					$code = $lang;
				}
			}
			if ( is_null( $code ) ) {
				$code = $default_language;
			}
		}

		if ( is_null( $code ) ) {
			$code = $this->this_lang;
		}

		$cache_key_args = array( $url, $code );
		$cache_key = md5(json_encode( $cache_key_args ));
		$cache_group = 'convert_url';

		$cache_found = false;
		$new_url = wp_cache_get($cache_key, $cache_group, false, $cache_found);

		if(!$cache_found) {

			$new_url = $url;

			if ( $code && ( $code != $default_language || ( $language_negotiation_type == 1 && $this->settings[ 'urls' ][ 'directory_for_default_language' ] ) ) ) {
				remove_filter( 'home_url', array( $this, 'home_url' ), 1 );
				$absolute_home_url = preg_replace( '@\?lang=' . $code . '@i', '', get_home_url() );
				add_filter( 'home_url', array( $this, 'home_url' ), 1, 4 );

				switch ( $language_negotiation_type ) {
					case '1':
						if ( 0 === strpos( $new_url, 'https://' ) ) {
							$absolute_home_url = preg_replace( '#^http://#', 'https://', $absolute_home_url );
						}
						if ( $absolute_home_url == $new_url ) {
							$new_url .= '/';
						}
						if ( 0 !== strpos( $new_url, $absolute_home_url . '/' . $code . '/' ) ) {
							// only replace if it is there already
							$new_url = str_replace( $absolute_home_url, $absolute_home_url . '/' . $code, $new_url );
						}
						break;
					case '2':
						$is_https = strpos( $new_url, 'https://' ) === 0;
						if ( $is_https ) {
							preg_replace( '#^http://#', 'https://', $new_url );
						} // normalize protocol
						$new_url = str_replace( $absolute_home_url, $this->settings[ 'language_domains' ][ $code ], $new_url );
						if ( $is_https ) {
							preg_replace( '#^http://#', 'https://', $new_url );
						} // normalize protocol (rev)
						break;
					case '3':
					default:
						// remove any previous value.
						if ( strpos( $new_url, '?lang=' . $code . '&' ) !== false ) {
							$new_url = str_replace( '?lang=' . $code . '&', '', $new_url );
						} elseif ( strpos( $new_url, '?lang=' . $code . '/' ) !== false ) {
							$new_url = str_replace( '?lang=' . $code . '/', '', $new_url );
						} elseif ( strpos( $new_url, '?lang=' . $code ) !== false ) {
							$new_url = str_replace( '?lang=' . $code, '', $new_url );
						} elseif ( strpos( $new_url, '&lang=' . $code . '/' ) !== false ) {
							$new_url = str_replace( '&lang=' . $code . '/', '', $new_url );
						} elseif ( strpos( $new_url, '&lang=' . $code ) !== false ) {
							$new_url = str_replace( '&lang=' . $code, '', $new_url );
						}

						if ( false === strpos( $new_url, '?' ) ) {
							$new_url_glue = '?';
						} else {
							$new_url_glue = '&';
						}
						$new_url .= $new_url_glue . 'lang=' . $code;
				}
			}

			wp_cache_set($cache_key, $new_url, $cache_group);
		}
		return $new_url;
	}

	function language_url( $code = null )
	{
		if ( is_null( $code ) )
			$code = $this->this_lang;

		remove_filter( 'home_url', array( $this, 'home_url' ), 1 );
		$abshome = get_home_url();
		add_filter( 'home_url', array( $this, 'home_url' ), 1, 4 );

		if ( $this->settings[ 'language_negotiation_type' ] == 1 || $this->settings[ 'language_negotiation_type' ] == 2 ) {
			$url = trailingslashit( $this->convert_url( $abshome, $code ) );
		} else {
			$url = $this->convert_url( $abshome, $code );
		}

		return $url;
	}

	function permalink_filter( $p, $pid )
	{
		if ( is_object( $pid ) ) {
			$post_type = $pid->post_type;
			$pid       = $pid->ID;
		} else {
			$_post     = get_post( $pid );
			$post_type = $_post->post_type;
		}

		if ( !$this->is_translated_post_type( $post_type ) )
			return $p;

		if ( $pid == (int)get_option( 'page_on_front' ) ) {
			return $p;
		}

		$default_language = $this->get_default_language();

		$element_lang_details = $this->get_element_language_details( $pid, 'post_' . $post_type );

		$use_directory = $this->settings[ 'language_negotiation_type' ] == 1 && $this->settings[ 'urls' ][ 'directory_for_default_language' ];

		if ( !empty( $element_lang_details ) && $element_lang_details->language_code && ( $default_language != $element_lang_details->language_code || $use_directory ) ) {
			$p = $this->convert_url( $p, $element_lang_details->language_code );
		} elseif ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'sample-permalink' ) { // check whether this is an autosaved draft
			if ( !isset( $_SERVER[ 'HTTP_REFERER' ] ) ) {
				$p = $this->convert_url( $p, $default_language );
			} else {
				$exp = explode( '?', $_SERVER[ "HTTP_REFERER" ] );
				if ( isset( $exp[ 1 ] ) )
					parse_str( $exp[ 1 ], $args );
				if ( isset( $args[ 'lang' ] ) && $default_language != $args[ 'lang' ] ) {
					$p = $this->convert_url( $p, $args[ 'lang' ] );
				}
			}
		}
		if ( is_feed() ) {
			$p = str_replace( "&lang=", "&#038;lang=", $p );
		}

		return $p;
	}

	function category_permalink_filter( $p, $cat_id )
	{
		global $wpdb;
		if ( isset( $this->icl_term_taxonomy_cache ) ) {
			$term_cat_id = $this->icl_term_taxonomy_cache->get( 'category_' . $cat_id );
		} else {
			$term_cat_id = null;
		}
		if ( !$term_cat_id ) {
			$term_cat_id = $wpdb->get_var( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id={$cat_id} AND taxonomy='category'" );
			if ( isset( $this->icl_term_taxonomy_cache ) ) {
				$this->icl_term_taxonomy_cache->set( 'category_' . $cat_id, $term_cat_id );
			}
		}
		$cat_id = $term_cat_id;

		$element_lang_details = $this->get_element_language_details( $cat_id, 'tax_category' );

		$use_directory = $this->settings[ 'language_negotiation_type' ] == 1 && $this->settings[ 'urls' ][ 'directory_for_default_language' ];

		if ( $this->get_default_language() != $element_lang_details->language_code || $use_directory ) {
			$p = $this->convert_url( $p, $element_lang_details->language_code );
		}

		return $p;
	}

	function post_type_archive_link_filter( $link, $post_type )
	{
		if ( isset( $this->settings[ 'custom_posts_sync_option' ][ $post_type ] ) && $this->settings[ 'custom_posts_sync_option' ][ $post_type ] ) {
			return $this->convert_url( $link );
		}

		return $link;
	}

	function tax_permalink_filter( $p, $tag )
	{
		global $wpdb;
		if ( is_object( $tag ) ) {
			$tag_id   = $tag->term_taxonomy_id;
			$taxonomy = $tag->taxonomy;
		} else {
			$taxonomy = 'post_tag';
			if ( empty( $tag_id ) ) {
				$tag_id = $wpdb->get_var( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id={$tag} AND taxonomy='{$taxonomy}'" );
				if ( isset( $this->icl_term_taxonomy_cache ) ) {
					$this->icl_term_taxonomy_cache->set( $taxonomy . '_' . $tag, $tag_id );
				}
			}
		}
		$cached_permalink_key =  $tag_id . '.' . $taxonomy;
		$cached_permalink = wp_cache_get($cached_permalink_key, 'icl_tax_permalink_filter');
		if($cached_permalink) {
			return $cached_permalink;
		}
		$element_lang_details = $this->get_element_language_details( $tag_id, 'tax_' . $taxonomy );

		$use_directory = $this->settings[ 'language_negotiation_type' ] == 1 && $this->settings[ 'urls' ][ 'directory_for_default_language' ];
		if ( !empty( $element_lang_details ) && ( $this->get_default_language() != $element_lang_details->language_code || $use_directory ) ) {
			$p = $this->convert_url( $p, $element_lang_details->language_code );
		}

		wp_cache_set($cached_permalink_key, $p, 'icl_tax_permalink_filter');
		return $p;
	}

	function get_comment_link_filter( $link )
	{
		// decode html characters since they are already encoded in the template for some reason
		$link = html_entity_decode( $link );

		return $link;
	}

	function attachment_link_filter( $link, $id )
	{
		//FIXME: check if we really need to call SitePress::convert_url in all other cases
		if($this->get_setting( 'language_negotiation_type' ) == 2) {
			$convert_url = $this->permalink_filter( $link, $id );
		} else {
			$convert_url = $this->convert_url( $link );
		}

		return $convert_url;
	}

	function get_ls_languages( $template_args = array() )
	{
		//Returns false if is admin and settings are corrupted
		if(is_admin() && !SitePress::check_settings_integrity()) return false;

		/** @var $wp_query WP_Query */
		global $sitepress, $wpdb, $wp_query, $w_this_lang;

		$current_language = $this->get_current_language();
		$default_language = $this->get_default_language();

		$cache_key_args = $template_args ? array_filter($template_args) : array('default');
		$cache_key_args[] = $current_language;
		$cache_key_args[] = $default_language;

		$cache_key_args = array_filter($cache_key_args);

		$cache_key = md5(json_encode($cache_key_args));
		$cache_group = 'ls_languages';

		$found = false;
		$ls_languages = wp_cache_get($cache_key, $cache_group, $found);
		if($found) return $ls_languages;

		if ( is_null( $this->wp_query ) )
			$this->set_wp_query();

		// use original wp_query for this
		// backup current $wp_query

		if ( !isset( $wp_query ) )
			return $this->get_active_languages();
		$_wp_query_back = clone $wp_query;
		unset( $wp_query );
		global $wp_query; // make it global again after unset
		$wp_query = clone $this->wp_query;

		$w_active_languages = $this->get_active_languages();

		$this_lang = $this->this_lang;
		if ( $this_lang == 'all' ) {
			$w_this_lang = array(
				'code' => 'all', 'english_name' => 'All languages', 'display_name' => __( 'All languages', 'sitepress' )
			);
		} else {
			$w_this_lang = $this->get_language_details( $this_lang );
		}

		if ( isset( $template_args[ 'skip_missing' ] ) ) {
			//override default setting
			$icl_lso_link_empty = !$template_args[ 'skip_missing' ];
		} else {
			$icl_lso_link_empty = $this->settings[ 'icl_lso_link_empty' ];
		}

		// 1. Determine translations
		if ( is_category() ) {
			$skip_empty                = false;
			$term_taxonomy_id_prepared = $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy=%s", array( get_query_var( 'cat' ), 'category' ) );
			$term_taxonomy_id          = $wpdb->get_var( $term_taxonomy_id_prepared );
			$trid                      = $sitepress->get_element_trid( $term_taxonomy_id, 'tax_category' );
			$translations              = $this->get_element_translations( $trid, 'tax_category', $skip_empty );
		} elseif ( is_tag() ) {
			$skip_empty                = false;
			$term_taxonomy_id_prepared = $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy=%s", array( get_query_var( 'tag_id' ), 'post_tag' ) );
			$term_taxonomy_id          = $wpdb->get_var( $term_taxonomy_id_prepared );
			$trid                      = $sitepress->get_element_trid( $term_taxonomy_id, 'tax_post_tag' );
			$translations              = $this->get_element_translations( $trid, 'tax_post_tag', $skip_empty );
		} elseif ( is_tax() ) {
			$skip_empty                = false;
			$term_taxonomy_id_prepared = $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy=%s", array( $wp_query->get_queried_object_id(), get_query_var( 'taxonomy' ) ) );
			$term_taxonomy_id          = $wpdb->get_var( $term_taxonomy_id_prepared );
			if ( $this->is_translated_taxonomy( get_query_var( 'taxonomy' ) ) ) {
				$trid         = $this->get_element_trid( $term_taxonomy_id, 'tax_' . get_query_var( 'taxonomy' ) );
				$translations = $this->get_element_translations( $trid, 'tax_' . get_query_var( 'taxonomy' ), $skip_empty );
			} else {
				$translations[ $this->get_current_language() ] = (object)array(
						'translation_id' => 0,
						'language_code'  => $this->get_default_language(),
						'original'       => 1,
						'name'           => get_query_var( 'taxonomy' ),
						'term_id'        => $wp_query->get_queried_object_id()
				);
			}
		} elseif ( is_archive() && !empty( $wp_query->posts ) ) {
			$translations = array();
		} elseif ( is_attachment() ) { // Exception for attachments. Not translated.
			$trid         = $sitepress->get_element_trid($wp_query->get_queried_object_id(), 'post_attachment' );
			$translations = $this->get_element_translations( $trid, 'post_attachment' );
		} elseif (is_page() || ('page' == get_option( 'show_on_front' ) && ( isset( $this->wp_query->queried_object_id ) && $this->wp_query->queried_object_id == get_option( 'page_on_front' ) || ( isset( $this->wp_query->queried_object_id ) && $this->wp_query->queried_object_id == get_option( 'page_for_posts' )) ) ) ) {
			$trid         = $sitepress->get_element_trid($wp_query->get_queried_object_id(), 'post_page' );
			$translations = $this->get_element_translations( $trid, 'post_page' );
		} elseif ( is_singular() && !empty( $wp_query->posts ) ) {
			$trid         = $sitepress->get_element_trid($this->wp_query->post->ID, 'post_' . $wp_query->posts[ 0 ]->post_type);
			$translations = $this->get_element_translations( $trid, 'post_' . $wp_query->posts[ 0 ]->post_type );
		} else {
			$wp_query->is_singular = false;
			$wp_query->is_archive  = false;
			$wp_query->is_category = false;
			$wp_query->is_404      = true;
		}

		// 2. determine url
		foreach ( $w_active_languages as $k => $lang ) {
			$skip_lang = false;
			if ( is_singular() || ( !empty( $this->wp_query->queried_object_id ) && $this->wp_query->queried_object_id == get_option( 'page_for_posts' ) ) ) {
				$this_lang_tmp       = $this->this_lang;
				$this->this_lang     = $lang[ 'code' ];
				$lang_page_on_front  = get_option( 'page_on_front' );
				$lang_page_for_posts = get_option( 'page_for_posts' );
				if($lang_page_on_front && $lang[ 'code' ] != $default_language) {
					$lang_page_on_front = icl_object_id($lang_page_on_front, 'page', false, $lang[ 'code' ]);
				}
				if($lang_page_for_posts && $lang[ 'code' ] != $default_language) {
					$lang_page_for_posts = icl_object_id($lang_page_for_posts, 'page', false, $lang[ 'code' ]);
				}
				if ( 'page' == get_option( 'show_on_front' ) && !empty( $translations[ $lang[ 'code' ] ] ) && $translations[ $lang[ 'code' ] ]->element_id == $lang_page_on_front ) {
					$lang[ 'translated_url' ] = $this->language_url( $lang[ 'code' ] );
				} elseif ( 'page' == get_option( 'show_on_front' ) && !empty( $translations[ $lang[ 'code' ] ] ) && $translations[ $lang[ 'code' ] ]->element_id && $translations[ $lang[ 'code' ] ]->element_id == $lang_page_for_posts ) {
					if ( $lang_page_for_posts ) {
						$lang[ 'translated_url' ] = get_permalink( $lang_page_for_posts );
					} else {
						$lang[ 'translated_url' ] = $this->language_url( $lang[ 'code' ] );
					}
				} else {
					if ( !empty( $translations[ $lang[ 'code' ] ] ) && isset( $translations[ $lang[ 'code' ] ]->post_title ) ) {
						$lang[ 'translated_url' ] = get_permalink( $translations[ $lang[ 'code' ] ]->element_id );
						$lang[ 'missing' ]        = 0;
					} else {
						if ( $icl_lso_link_empty ) {
							if ( !empty( $template_args[ 'link_empty_to' ] ) ) {
								$lang[ 'translated_url' ] = str_replace( '{%lang}', $lang[ 'code' ], $template_args[ 'link_empty_to' ] );
							} else {
								$lang[ 'translated_url' ] = $this->language_url( $lang[ 'code' ] );
							}

						} else {
							$skip_lang = true;
						}
						$lang[ 'missing' ] = 1;
					}
				}
				$this->this_lang     = $this_lang_tmp;
			} elseif ( is_category() ) {
				if ( isset( $translations[ $lang[ 'code' ] ] ) ) {
					global $icl_adjust_id_url_filter_off; // force  the category_link_adjust_id to not modify this
					$icl_adjust_id_url_filter_off = true;

					$lang[ 'translated_url' ] = get_category_link( $translations[ $lang[ 'code' ] ]->term_id );

					$icl_adjust_id_url_filter_off = false; // restore default bahavior
					$lang[ 'missing' ]            = 0;
				} else {
					if ( $icl_lso_link_empty ) {
						if ( !empty( $template_args[ 'link_empty_to' ] ) ) {
							$lang[ 'translated_url' ] = str_replace( '{%lang}', $lang[ 'code' ], $template_args[ 'link_empty_to' ] );
						} else {
							$lang[ 'translated_url' ] = $this->language_url( $lang[ 'code' ] );
						}
					} else {
						// dont skip the currrent language
						if ( $current_language != $lang[ 'code' ] ) {
							$skip_lang = true;
						}
					}
					$lang[ 'missing' ] = 1;
				}
			} elseif ( is_tax() ) {
				if ( isset( $translations[ $lang[ 'code' ] ] ) ) {
					global $icl_adjust_id_url_filter_off; // force  the category_link_adjust_id to not modify this
					$icl_adjust_id_url_filter_off = true;

					$lang[ 'translated_url' ] = get_term_link( (int)$translations[ $lang[ 'code' ] ]->term_id, get_query_var( 'taxonomy' ) );

					$icl_adjust_id_url_filter_off = false; // restore default bahavior
					$lang[ 'missing' ]            = 0;
				} else {
					if ( $icl_lso_link_empty ) {
						if ( !empty( $template_args[ 'link_empty_to' ] ) ) {
							$lang[ 'translated_url' ] = str_replace( '{%lang}', $lang[ 'code' ], $template_args[ 'link_empty_to' ] );
						} else {
							$lang[ 'translated_url' ] = $this->language_url( $lang[ 'code' ] );
						}
					} else {
						// dont skip the currrent language
						if ( $current_language != $lang[ 'code' ] ) {
							$skip_lang = true;
						}
					}
					$lang[ 'missing' ] = 1;
				}
			} elseif ( is_tag() ) {
				if ( isset( $translations[ $lang[ 'code' ] ] ) ) {
					global $icl_adjust_id_url_filter_off; // force  the category_link_adjust_id to not modify this
					$icl_adjust_id_url_filter_off = true;

					$lang[ 'translated_url' ] = get_tag_link( $translations[ $lang[ 'code' ] ]->term_id );

					$icl_adjust_id_url_filter_off = false; // restore default bahavior
					$lang[ 'missing' ]            = 0;
				} else {
					if ( $icl_lso_link_empty ) {
						if ( !empty( $template_args[ 'link_empty_to' ] ) ) {
							$lang[ 'translated_url' ] = str_replace( '{%lang}', $lang[ 'code' ], $template_args[ 'link_empty_to' ] );
						} else {
							$lang[ 'translated_url' ] = $this->language_url( $lang[ 'code' ] );
						}
					} else {
						// dont skip the currrent language
						if ( $current_language != $lang[ 'code' ] ) {
							$skip_lang = true;
						}
					}
					$lang[ 'missing' ] = 1;
				}
			} elseif ( is_author() ) {
				global $authordata, $wp_query;
				if ( empty( $authordata ) ) {
					$authordata = get_userdata( get_query_var( 'author' ) );
				}
				$post_type = get_query_var( 'post_type' ) ? get_query_var( 'post_type' ) : 'post';
				if ( $wpdb->get_var( "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
                        JOIN {$wpdb->prefix}icl_translations t ON p.ID=t.element_id AND t.element_type = 'post_{$post_type}'
                        WHERE p.post_author='{$authordata->ID}' AND post_type='{$post_type}' AND post_status='publish' AND language_code='{$lang['code']}'" )
				) {
					remove_filter( 'home_url', array( $this, 'home_url' ), 1, 4 );
					remove_filter( 'author_link', array( $this, 'author_link' ) );
					$author_url = get_author_posts_url( $authordata->ID );
					add_filter( 'home_url', array( $this, 'home_url' ), 1, 4 );
					add_filter( 'author_link', array( $this, 'author_link' ) );
					$lang[ 'translated_url' ] = $this->convert_url( $author_url, $lang[ 'code' ] );
					$lang[ 'missing' ]        = 0;
				} else {
					if ( $icl_lso_link_empty ) {
						if ( !empty( $template_args[ 'link_empty_to' ] ) ) {
							$lang[ 'translated_url' ] = str_replace( '{%lang}', $lang[ 'code' ], $template_args[ 'link_empty_to' ] );
						} else {
							$lang[ 'translated_url' ] = $this->language_url( $lang[ 'code' ] );
						}
					} else {
						// dont skip the currrent language
						if ( $current_language != $lang[ 'code' ] ) {
							$skip_lang = true;
						}
					}
					$lang[ 'missing' ] = 1;
				}
			} elseif ( is_archive() && !is_tag() ) {
				global $icl_archive_url_filter_off;
				$icl_archive_url_filter_off = true;
				if ( $this->wp_query->is_year ) {
					if ( isset( $this->wp_query->query_vars[ 'm' ] ) && !$this->wp_query->query_vars[ 'year' ] ) {
						$this->wp_query->query_vars[ 'year' ] = substr( $this->wp_query->query_vars[ 'm' ], 0, 4 );
					}
					$lang[ 'translated_url' ] = $this->archive_url( get_year_link( $this->wp_query->query_vars[ 'year' ] ), $lang[ 'code' ] );
				} elseif ( $this->wp_query->is_month ) {
					if ( isset( $this->wp_query->query_vars[ 'm' ] ) && !$this->wp_query->query_vars[ 'year' ] ) {
						$this->wp_query->query_vars[ 'year' ]     = substr( $this->wp_query->query_vars[ 'm' ], 0, 4 );
						$this->wp_query->query_vars[ 'monthnum' ] = substr( $this->wp_query->query_vars[ 'm' ], 4, 2 );
					} else {
						if ( $icl_lso_link_empty ) {
							if ( !empty( $template_args[ 'link_empty_to' ] ) ) {
								$lang[ 'translated_url' ] = str_replace( '{%lang}', $lang[ 'code' ], $template_args[ 'link_empty_to' ] );
							} else {
								$lang[ 'translated_url' ] = $this->language_url( $lang[ 'code' ] );
							}
						}
						$lang[ 'missing' ] = 1;
					}
					$lang[ 'translated_url' ] = $this->archive_url( get_month_link( $this->wp_query->query_vars[ 'year' ], $this->wp_query->query_vars[ 'monthnum' ] ), $lang[ 'code' ] );
				} elseif ( $this->wp_query->is_day ) {
					if ( isset( $this->wp_query->query_vars[ 'm' ] ) && !$this->wp_query->query_vars[ 'year' ] ) {
						$this->wp_query->query_vars[ 'year' ]     = substr( $this->wp_query->query_vars[ 'm' ], 0, 4 );
						$this->wp_query->query_vars[ 'monthnum' ] = substr( $this->wp_query->query_vars[ 'm' ], 4, 2 );
						$this->wp_query->query_vars[ 'day' ]      = substr( $this->wp_query->query_vars[ 'm' ], 6, 2 );
						gmdate( 'Y', current_time( 'timestamp' ) ); //force wp_timezone_override_offset to be called
					}
					$lang[ 'translated_url' ] = $this->archive_url( get_day_link( $this->wp_query->query_vars[ 'year' ], $this->wp_query->query_vars[ 'monthnum' ], $this->wp_query->query_vars[ 'day' ] ), $lang[ 'code' ] );
				} else if ( isset( $this->wp_query->query_vars[ 'post_type' ] ) ) {
					do_action( '_icl_before_archive_url', $this->wp_query->query_vars[ 'post_type' ], $lang[ 'code' ] );
					if ( $this->is_translated_post_type( $this->wp_query->query_vars[ 'post_type' ] ) && function_exists( 'get_post_type_archive_link' ) ) {
						remove_filter( 'post_type_archive_link', array( $this, 'post_type_archive_link_filter' ), 10 );
						$lang[ 'translated_url' ] = $this->convert_url( get_post_type_archive_link( $this->wp_query->query_vars[ 'post_type' ] ), $lang[ 'code' ] );
					} else {
						$lang[ 'translated_url' ] = $this->language_url( $lang[ 'code' ] );
					}
					do_action( '_icl_after_archive_url', $this->wp_query->query_vars[ 'post_type' ], $lang[ 'code' ] );
				}
				add_filter( 'post_type_archive_link', array( $this, 'post_type_archive_link_filter' ), 10, 2 );
				$icl_archive_url_filter_off = false;
			} elseif ( is_search() ) {
				$url_glue                 = strpos( $this->language_url( $lang[ 'code' ] ), '?' ) === false ? '?' : '&';
				$lang[ 'translated_url' ] = $this->language_url( $lang[ 'code' ] ) . $url_glue . 's=' . urlencode( $wp_query->query[ 's' ] );
			} else {
				global $icl_language_switcher_preview;
				if ( $icl_lso_link_empty || is_home() || is_404() || ( 'page' == get_option( 'show_on_front' ) && ( $this->wp_query->queried_object_id == get_option( 'page_on_front' ) || $this->wp_query->queried_object_id == get_option( 'page_for_posts' ) ) ) || $icl_language_switcher_preview ) {
					$lang[ 'translated_url' ] = $this->language_url( $lang[ 'code' ] );
					$skip_lang                = false;
				} else {
					$skip_lang = true;
					unset( $w_active_languages[ $k ] );
				}
			}
			if ( !$skip_lang ) {
				$w_active_languages[ $k ] = $lang;
			} else {
				unset( $w_active_languages[ $k ] );
			}
		}

		// 3.
		foreach ( $w_active_languages as $k => $v ) {
			$lang_code = $w_active_languages[ $k ][ 'language_code' ] = $w_active_languages[ $k ][ 'code' ];
			unset( $w_active_languages[ $k ][ 'code' ] );

			$native_name = $this->get_display_language_name( $lang_code, $lang_code );
			if ( !$native_name )
				$native_name = $w_active_languages[ $k ][ 'english_name' ];
			$w_active_languages[ $k ][ 'native_name' ] = $native_name;

			$translated_name = $this->get_display_language_name( $lang_code, $current_language );
			if ( !$translated_name )
				$translated_name = $w_active_languages[ $k ][ 'english_name' ];
			$w_active_languages[ $k ][ 'translated_name' ] = $translated_name;
			unset( $w_active_languages[ $k ][ 'display_name' ] );
			unset( $w_active_languages[ $k ][ 'english_name' ] );

			if ( isset( $w_active_languages[ $k ][ 'translated_url' ] ) ) {
				$w_active_languages[ $k ][ 'url' ] = $w_active_languages[ $k ][ 'translated_url' ];
				unset( $w_active_languages[ $k ][ 'translated_url' ] );
			} else {
				$w_active_languages[ $k ][ 'url' ] = $this->language_url( $k );
			}

			$flag = $this->get_flag( $lang_code );

			if ( $flag->from_template ) {
				$wp_upload_dir = wp_upload_dir();
				$flag_url      = $wp_upload_dir[ 'baseurl' ] . '/flags/' . $flag->flag;
			} else {
				$flag_url = ICL_PLUGIN_URL . '/res/flags/' . $flag->flag;
			}
			$w_active_languages[ $k ][ 'country_flag_url' ] = $flag_url;

			$w_active_languages[ $k ][ 'active' ] = $current_language == $lang_code ? '1' : 0;;
		}

		// 4. pass GET parameters
		$parameters_copied = apply_filters( 'icl_lang_sel_copy_parameters', array_map( 'trim', explode( ',', $this->settings[ 'icl_lang_sel_copy_parameters' ] ) ) );
		if ( $parameters_copied ) {
			foreach ( $_GET as $k => $v ) {
				if ( in_array( $k, $parameters_copied ) ) {
					$gets_passed[ $k ] = $v;
				}
			}
		}
		if ( !empty( $gets_passed ) ) {
			$gets_passed = http_build_query( $gets_passed );
			foreach ( $w_active_languages as $code => $al ) {
				if ( empty( $al[ 'missing' ] ) ) {
					$glue = false !== strpos( $w_active_languages[ $code ][ 'url' ], '?' ) ? '&' : '?';
					$w_active_languages[ $code ][ 'url' ] .= $glue . $gets_passed;
				}
			}
		}

		// restore current $wp_query
		unset( $wp_query );
		global $wp_query; // make it global again after unset
		$wp_query = clone $_wp_query_back;
		unset( $_wp_query_back );

		$w_active_languages = apply_filters( 'icl_ls_languages', $w_active_languages );

		$w_active_languages = $this->sort_ls_languages( $w_active_languages, $template_args );

		wp_reset_query();

		wp_cache_set($cache_key, $w_active_languages, $cache_group);
		return $w_active_languages;
	}

	function sort_ls_languages( $w_active_languages, $template_args )
	{
		// sort languages according to parameters
		$orderby = isset( $template_args[ 'orderby' ] ) ? $template_args[ 'orderby' ] : 'custom';
		$order   = isset( $template_args[ 'order' ] ) ? $template_args[ 'order' ] : 'asc';
		$comp    = $order == 'asc' ? '>' : '<';

		switch ( $orderby ) {
			case 'id':
				uasort( $w_active_languages, create_function( '$a,$b', 'return $a[\'id\'] ' . $comp . ' $b[\'id\'];' ) );
				break;
			case 'code':
				ksort( $w_active_languages );
				if ( $order == 'desc' ) {
					$w_active_languages = array_reverse( $w_active_languages );
				}
				break;
			case 'name':
				uasort( $w_active_languages, create_function( '$a,$b', 'return $a[\'translated_name\'] ' . $comp . ' $b[\'translated_name\'];' ) );
				break;
			case 'custom':
			default:
				$w_active_languages = $this->order_languages( $w_active_languages );
		}

		return $w_active_languages;

	}

	function get_display_language_name( $lang_code, $display_code )
	{
		global $wpdb;
		if ( isset( $this->icl_language_name_cache ) ) {
			$translated_name = $this->icl_language_name_cache->get( $lang_code . $display_code );
		} else {
			$translated_name = null;
		}
		if ( !$translated_name ) {
			$display_code    = $display_code == 'all' ? $this->get_admin_language() : $display_code;
			$translated_name = $wpdb->get_var( "SELECT name FROM {$wpdb->prefix}icl_languages_translations WHERE language_code='{$lang_code}' AND display_language_code='{$display_code}'" );
			if ( isset( $this->icl_language_name_cache ) ) {
				$this->icl_language_name_cache->set( $lang_code . $display_code, $translated_name );
			}
		}

		return $translated_name;
	}

	function get_flag( $lang_code )
	{
		global $wpdb;

		if ( isset( $this->icl_flag_cache ) ) {
			$flag = $this->icl_flag_cache->get( $lang_code );
		} else {
			$flag = null;
		}
		if ( !$flag ) {
			$flag = $wpdb->get_row( "SELECT flag, from_template FROM {$wpdb->prefix}icl_flags WHERE lang_code='{$lang_code}'" );
			if ( isset( $this->icl_flag_cache ) ) {
				$this->icl_flag_cache->set( $lang_code, $flag );
			}
		}

		return $flag;
	}

	function get_flag_url( $code )
	{
		$flag = $this->get_flag( $code );
		if ( $flag->from_template ) {
			$wp_upload_dir = wp_upload_dir();
			$flag_url      = $wp_upload_dir[ 'baseurl' ] . '/flags/' . $flag->flag;
		} else {
			$flag_url = ICL_PLUGIN_URL . '/res/flags/' . $flag->flag;
		}

		return $flag_url;
	}

	function set_up_language_selector()
	{
		// language selector
		// load js and style for js language selector
		if (isset($this->settings[ 'icl_lang_sel_type' ]) && $this->settings[ 'icl_lang_sel_type' ] == 'dropdown' && ( !is_admin() || ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == ICL_PLUGIN_FOLDER . '/menu/languages.php' ) ) ) {
			if ( $this->settings[ 'icl_lang_sel_stype' ] == 'mobile-auto' ) {
				include ICL_PLUGIN_PATH . '/lib/mobile-detect.php';
				$WPML_Mobile_Detect = new WPML_Mobile_Detect;
				$this->is_mobile    = $WPML_Mobile_Detect->isMobile();
				$this->is_tablet    = $WPML_Mobile_Detect->isTablet();
			}
			if ( ( $this->settings[ 'icl_lang_sel_stype' ] == 'mobile-auto' && ( !empty( $this->is_mobile ) || !empty( $this->is_tablet ) ) ) || $this->settings[ 'icl_lang_sel_stype' ] == 'mobile'
			) {
				wp_enqueue_script( 'language-selector', ICL_PLUGIN_URL . '/res/js/language-selector.js', ICL_SITEPRESS_VERSION );
				wp_enqueue_style( 'language-selector', ICL_PLUGIN_URL . '/res/css/language-selector-click.css', ICL_SITEPRESS_VERSION );
			}
		}
	}

	function language_selector()
	{
		// Mobile or auto
		$is_mobile = $this->settings[ 'icl_lang_sel_stype' ] == 'mobile' || ( $this->settings[ 'icl_lang_sel_stype' ] == 'mobile-auto' && !empty( $this->is_tablet ) && !empty( $this->is_mobile ) );
		if ( $this->settings[ 'icl_lang_sel_type' ] == 'dropdown' && ( $is_mobile )
		) {

			include ICL_PLUGIN_PATH . '/menu/language-selector-mobile.php';

		} else {

			global $icl_language_switcher_preview;
			if ( $this->settings[ 'icl_lang_sel_type' ] == 'list' || $icl_language_switcher_preview ) {
				global $icl_language_switcher;
				$icl_language_switcher->widget_list();
				if ( !$icl_language_switcher_preview ) {
					return '';
				}
			}

			$active_languages = $this->get_ls_languages();

			if($active_languages) {
				/**
				 * @var $main_language bool|string
				 * @used_by menu/language-selector.php
				 */
				foreach ( $active_languages as $k => $al ) {
					if ( $al[ 'active' ] == 1 ) {
						$main_language = $al;
						unset( $active_languages[ $k ] );
						break;
					}
				}
				include ICL_PLUGIN_PATH . '/menu/language-selector.php';
			}
		}

		return '';
	}

	function have_icl_translator( $source, $target )
	{
		// returns true if we have ICL translators for the language pair
		if ( isset( $this->settings[ 'icl_lang_status' ] ) ) {
			foreach ( $this->settings[ 'icl_lang_status' ] as $lang ) {
				if ( $lang[ 'from' ] == $source && $lang[ 'to' ] == $target ) {
					return $lang[ 'have_translators' ];
				}
			}

		}

		return false;
	}

	function get_default_categories()
	{
		$default_categories_all = $this->settings[ 'default_categories' ];

		$active_languages_codes = false;
		foreach ( $this->active_languages as $l ) {
			$active_languages_codes[ ] = $l[ 'code' ];
		}
		$default_categories = array();

		if ( is_array( $default_categories_all ) && is_array( $active_languages_codes ) ) {
			foreach ( $default_categories_all as $c => $v ) {
				if ( in_array( $c, $active_languages_codes ) ) {
					$default_categories[ $c ] = $v;
				}
			}
		}

		return $default_categories;
	}

	function set_default_categories( $def_cat )
	{
		$this->settings[ 'default_categories' ] = $def_cat;
		$this->save_settings();
	}

	function pre_option_default_category( $setting )
	{
		global $wpdb;
		if ( isset( $_POST[ 'icl_post_language' ] ) && $_POST[ 'icl_post_language' ] || ( isset( $_GET[ 'lang' ] ) && $_GET[ 'lang' ] != 'all' ) ) {
			$lang = isset( $_POST[ 'icl_post_language' ] ) && $_POST[ 'icl_post_language' ] ? $_POST[ 'icl_post_language' ] : $_GET[ 'lang' ];
			$ttid = @intval( $this->settings[ 'default_categories' ][ $lang ] );

			return $tid = $wpdb->get_var( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id={$ttid} AND taxonomy='category'" );
		}

		return false;
	}

	function update_option_default_category( $oldvalue, $new_value )
	{
		global $wpdb;
		$new_value     = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy='category' AND term_id=%d", $new_value ) );
		$translations = $this->get_element_translations( $this->get_element_trid( $new_value, 'tax_category' ) );
		if ( !empty( $translations ) ) {
			foreach ( $translations as $t ) {
				$icl_settings[ 'default_categories' ][ $t->language_code ] = $t->element_id;
			}
			if ( isset( $icl_settings ) ) {
				$this->save_settings( $icl_settings );
			}
		}
	}

	function the_category_name_filter( $name )
	{
		if ( is_array( $name ) ) {
			foreach ( $name as $k => $v ) {
				$name[ $k ] = $this->the_category_name_filter( $v );
			}

			return $name;
		}
		if ( false === strpos( $name, '@' ) )
			return $name;
		if ( false !== strpos( $name, '<a' ) ) {
			$int = preg_match_all( '|<a([^>]+)>([^<]+)</a>|i', $name, $matches );
			if ( $int && count( $matches[ 0 ] ) > 1 ) {
				$originals = $filtered = array();
				foreach ( $matches[ 0 ] as $m ) {
					$originals[ ] = $m;
					$filtered[ ]  = $this->the_category_name_filter( $m );
				}
				$name = str_replace( $originals, $filtered, $name );
			} else {
				$name_sh = strip_tags( $name );
				$exp     = explode( '@', $name_sh );
				$name    = str_replace( $name_sh, trim( $exp[ 0 ] ), $name );
			}
		} else {
			$name = preg_replace( '#(.*) @(.*)#i', '$1', $name );
		}

		return $name;
	}

	function get_terms_filter( $terms )
	{
		foreach ( $terms as $k => $v ) {
			if ( isset( $terms[ $k ]->name ) )
				$terms[ $k ]->name = $this->the_category_name_filter( $terms[ $k ]->name );
		}
		return $terms;
	}

	function get_the_terms_filter( $terms, $id, $taxonomy )
	{
		if ( !empty( $this->settings[ 'taxonomies_sync_option' ][ $taxonomy ] ) ) {
			$terms = $this->get_terms_filter( $terms );
		}

		return $terms;
	}

	function get_term_adjust_id( $term ) {
		//TODO: To remove? I couldn't find a single place where this is used, since $term->term_id == $translated_id. Testing always returning the passed value.
		// comment from Konrad: don't remove this as it is still used by nav menus

		global $icl_adjust_id_url_filter_off;
		if ( $icl_adjust_id_url_filter_off ) {
			return $term;
		} // special cases when we need the category in a different language

		// exception: don't filter when called from get_permalink. When category parents are determined
		$debug_backtrace = $this->get_backtrace( 7 ); //Limit to first 7 stack frames, since 6 is the highest index we use
		if ( isset( $debug_backtrace[ 5 ][ 'function' ] ) &&
			 $debug_backtrace[ 5 ][ 'function' ] == 'get_category_parents' ||
			 isset( $debug_backtrace[ 6 ][ 'function' ] ) &&
			 $debug_backtrace[ 6 ][ 'function' ] == 'get_permalink' ||
			 isset( $debug_backtrace[ 4 ][ 'function' ] ) &&
			 $debug_backtrace[ 4 ][ 'function' ] == 'get_permalink' // WP 3.5
		) {
			return $term;
		}

		$translated_id = icl_object_id( $term->term_id, $term->taxonomy, true );

		$cached_term = wp_cache_get( $translated_id, 'icl_get_term_adjust_id' );

		if ( $cached_term ) {
			return $cached_term;
		}
		if ( $translated_id != $term->term_id ) {
			//$translated_id = $wpdb->get_var("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id='{$translated_id}'");
			remove_filter( 'get_term', array( $this, 'get_term_adjust_id' ), 1 );
			$t_term = get_term( $translated_id, $term->taxonomy );
			if ( !is_wp_error( $t_term ) ) {
				$term = $t_term;
			}
			add_filter( 'get_term', array( $this, 'get_term_adjust_id' ), 1, 1 );
		}

		wp_cache_set( $translated_id, $term, 'icl_get_term_adjust_id' );

		return $term;
	}

	function get_term_by_name_and_lang( &$name, $type, $lang )
	{
		global $wpdb;

		//decode name
		// $name = htmlspecialchars_decode($name);

		$the_term = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} x ON x.term_id = t.term_id WHERE name=%s AND taxonomy=%s", array( $name , $type) ) );

		if ( $the_term ) {
			$term_lang = $wpdb->get_var( $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations
                                                            WHERE element_id=%d AND element_type=%s", $the_term->term_taxonomy_id, 'tax_' . $type ) );

			if ( $term_lang != $lang ) {
				// term is in the wrong language
				// Add lang code to term name.

				$name .= ' @' . $lang;

				$the_term = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} x ON x.term_id = t.term_id WHERE name=%s AND taxonomy=%s", array( $name , $type) ) );
			}
		}

		return $the_term;
	}

	function set_term_translation( $original_term, $translation_id, $type, $lang, $original_lang )
	{
		global $wpdb;

		if ( $original_term ) {
			$trid = $this->get_element_trid( $original_term->term_taxonomy_id, 'tax_' . $type );
			$this->set_element_language_details( $translation_id, 'tax_' . $type, $trid, $lang, $original_lang );
		} else {
			// Original has been deleled.
			// we need to set the language of the new term.
			$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'language_code' => $lang ), array( 'element_type' => 'tax_' . $type, 'element_id' => $translation_id ) );
		}
	}

	function wp_list_pages_adjust_ids( $out, $args )
	{
		static $__run_once = false; // only run for calls that have 'include' as an argument. ant only run once.
		if ( $args[ 'include' ] && !$__run_once && $this->get_current_language() != $this->get_default_language() ) {
			$__run_once = true;
			$include    = array_map( 'trim', explode( ',', $args[ 'include' ] ) );
			$tr_include = array();
			foreach ( $include as $i ) {
				$t = icl_object_id( $i, 'page', true );
				if ( $t ) {
					$tr_include[ ] = $t;
				}
			}
			$args[ 'include' ] = join( ',', $tr_include );
			$out               = wp_list_pages( $args );
		}

		return $out;
	}

	function get_terms_adjust_ids( $terms, $taxonomies, $args )
	{
		static $__run_once = false; // only run for calls that have 'include' as an argument. ant only run once.
		if ( $args[ 'include' ] && !$__run_once && $this->get_current_language() != $this->get_default_language() ) {
			$__run_once = true;
			if ( is_array( $args[ 'include' ] ) ) {
				$include = $args[ 'include' ];
			} else {
				$include = array_map( 'trim', explode( ',', $args[ 'include' ] ) );
			}
			$tr_include = array();
			foreach ( $include as $i ) {
				$t = icl_object_id( $i, $taxonomies[ 0 ], true );
				if ( $t ) {
					$tr_include[ ] = $t;
				}
			}
			$args[ 'include' ] = join( ',', $tr_include );
			$terms             = get_terms( $taxonomies, $args );
		}

		return $terms;
	}

	function get_pages_adjust_ids( $pages, $args )
	{
		if ($pages && $this->get_current_language() != $this->get_default_language() ) {

			$cache_key_args = md5(json_encode(wp_list_pluck($pages, 'ID')));
			$cache_key_args .= ":";
			$cache_key_args .= md5(json_encode($args));

			$cache_key = $cache_key_args;
			$cache_group = 'get_pages_adjust_ids';
			$found = false;
			$cached_result = wp_cache_get($cache_key, $cache_group, false, $found);

			if(!$found) {
				$args_updated = false;
				if ( $args[ 'include' ] ) {
					$include    = array_map( 'trim', explode( ',', $args[ 'include' ] ) );
					$tr_include = array();
					foreach ( $include as $i ) {
						$t = icl_object_id( $i, 'page', true );
						if ( $t ) {
							$tr_include[ ] = $t;
						}
					}
					$args[ 'include' ] = join( ',', $tr_include );
					$args_updated      = true;
				}
				if ( $args[ 'exclude' ] ) {
					$exclude    = array_map( 'trim', explode( ',', $args[ 'exclude' ] ) );
					$tr_exclude = array();
					foreach ( $exclude as $i ) {
						$t = icl_object_id( $i, 'page', true );
						if ( $t ) {
							$tr_exclude[ ] = $t;
						}
					}
					$args[ 'exclude' ] = join( ',', $tr_exclude );
					$args_updated      = true;
				}
				if ( $args[ 'child_of' ] ) {
					$args[ 'child_of' ] = icl_object_id( $args[ 'child_of' ], 'page', true );
					$args_updated       = true;
				}
				if ( $args_updated ) {
					remove_filter( 'get_pages', array( $this, 'get_pages_adjust_ids' ), 1 );
					$pages = get_pages( $args );
					add_filter( 'get_pages', array( $this, 'get_pages_adjust_ids' ), 1, 2 );
				}
				wp_cache_set($cache_key, $pages, $cache_group);
			} else {
                $pages = $cached_result;
            }
		}

		return $pages;
	}

	function category_link_adjust_id( $catlink, $cat_id )
	{
		global $icl_adjust_id_url_filter_off, $wpdb;
		if ( $icl_adjust_id_url_filter_off )
			return $catlink; // special cases when we need the categiry in a different language

		$translated_id = icl_object_id( $cat_id, 'category', true );
		if ( $translated_id && $translated_id != $cat_id ) {
			$translated_id = $wpdb->get_var( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id='{$translated_id}'" );
			remove_filter( 'category_link', array( $this, 'category_link_adjust_id' ), 1 );
			$catlink = get_category_link( $translated_id, 'category' );
			add_filter( 'category_link', array( $this, 'category_link_adjust_id' ), 1, 2 );
		}

		return $catlink;
	}

	// adjacent posts links
	function get_adjacent_post_join( $join )
	{
		global $wpdb;
		$post_type = get_query_var( 'post_type' );

		$cache_key = md5( json_encode( array( $post_type, $join ) ) );
		$cache_group = 'adjacent_post_join';

		$temp_join = wp_cache_get( $cache_key, $cache_group );
		if ( $temp_join ) {
			return $temp_join;
		}

		if ( !$post_type ) {
			$post_type = 'post';
		}
		if ( $this->is_translated_post_type( $post_type ) ) {
			$join .= " JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = 'post_{$post_type}'";
		}

		wp_cache_set( $cache_key, $join, $cache_group );

		return $join;
	}

	function get_adjacent_post_where( $where )
	{
		$post_type = get_query_var( 'post_type' );

		$cache_key   = md5(json_encode( array( $post_type, $where ) ) );
		$cache_group = 'adjacent_post_where';

		$temp_where = wp_cache_get( $cache_key, $cache_group );
		if ( $temp_where ) {
			return $temp_where;
		}

		if ( !$post_type ) {
			$post_type = 'post';
		}
		if ( $this->is_translated_post_type( $post_type ) ) {
			$where .= " AND language_code = '" . esc_sql( $this->this_lang ) . "'";
		}

		wp_cache_set( $cache_key, $where, $cache_group );

		return $where;

	}

	// feeds links
	function feed_link( $out )
	{
		return $this->convert_url( $out );
	}

	// commenting links
	function post_comments_feed_link( $out )
	{
		if ( $this->settings[ 'language_negotiation_type' ] == 3 ) {
			$out = preg_replace( '@(\?|&)lang=([^/]+)/feed/@i', 'feed/$1lang=$2', $out );
		}

		return $out;
		//return $this->convert_url($out);
	}

	function trackback_url( $out )
	{
		return $this->convert_url( $out );
	}

	function user_trailingslashit( $string, $type_of_url )
	{
		// fixes comment link for when the comments list pagination is enabled
		if ( $type_of_url == 'comment' ) {
			$string = preg_replace( '@(.*)/\?lang=([a-z-]+)/(.*)@is', '$1/$3?lang=$2', $string );
		}

		return $string;
	}

	// archives links
	function getarchives_join( $join )
	{
		global $wpdb;
		$join .= " JOIN {$wpdb->prefix}icl_translations t ON t.element_id = {$wpdb->posts}.ID AND t.element_type='post_post'";

		return $join;
	}

	function getarchives_where( $where )
	{
		$where .= " AND language_code = '" . esc_sql( $this->this_lang ) . "'";

		return $where;
	}

	function archives_link( $out )
	{
		global $icl_archive_url_filter_off;
		if ( !$icl_archive_url_filter_off ) {
			$out = $this->archive_url( $out, $this->this_lang );
		}
		$icl_archive_url_filter_off = false;

		return $out;
	}

	function archive_url( $url, $lang )
	{
		$url = $this->convert_url( $url, $lang );

		return $url;
	}

	function author_link( $url )
	{
		$url = $this->convert_url( $url );

		return preg_replace( '#^http://(.+)//(.+)$#', 'http://$1/$2', $url );
	}

	function pre_option_home( $setting = false )
	{
		if ( !defined( 'TEMPLATEPATH' ) )
			return $setting;

		$template_real_path = realpath( TEMPLATEPATH );

		$debug_backtrace = $this->get_backtrace( 7 ); //Ignore objects and limit to first 7 stack frames, since 6 is the highest index we use

		$inc_methods = array( 'include', 'include_once', 'require', 'require_once' );
		if ( isset( $debug_backtrace[ 4 ] ) && $debug_backtrace[ 4 ][ 'function' ] == 'get_bloginfo' && isset( $debug_backtrace[ 5 ] ) && $debug_backtrace[ 5 ][ 'function' ] == 'bloginfo' ) {
			// case of bloginfo
			$is_template_file = false !== strpos( $debug_backtrace[ 5 ][ 'file' ], $template_real_path );
			$is_direct_call   = in_array( $debug_backtrace[ 6 ][ 'function' ], $inc_methods ) || ( false !== strpos( $debug_backtrace[ 6 ][ 'file' ], $template_real_path ) );
		} elseif ( isset( $debug_backtrace[ 4 ] ) && $debug_backtrace[ '4' ][ 'function' ] == 'get_bloginfo' ) {
			// case of get_bloginfo
			$is_template_file = false !== strpos( $debug_backtrace[ 4 ][ 'file' ], $template_real_path );
			$is_direct_call   = in_array( $debug_backtrace[ 5 ][ 'function' ], $inc_methods ) || ( false !== strpos( $debug_backtrace[ 5 ][ 'file' ], $template_real_path ) );
		} elseif ( isset( $debug_backtrace[ 4 ] ) && $debug_backtrace[ '4' ][ 'function' ] == 'get_settings' ) {
			// case of get_settings
			$is_template_file = false !== strpos( $debug_backtrace[ 4 ][ 'file' ], $template_real_path );
			$is_direct_call   = in_array( $debug_backtrace[ 5 ][ 'function' ], $inc_methods ) || ( false !== strpos( $debug_backtrace[ 5 ][ 'file' ], $template_real_path ) );
		} else {
			// case of get_option
			$is_template_file = isset( $debug_backtrace[ 3 ][ 'file' ] ) && ( false !== strpos( $debug_backtrace[ 3 ][ 'file' ], $template_real_path ) );
			$is_direct_call   = isset( $debug_backtrace[ 4 ] ) && in_array( $debug_backtrace[ 4 ][ 'function' ], $inc_methods ) || ( isset( $debug_backtrace[ 4 ][ 'file' ] ) && false !== strpos( $debug_backtrace[ 4 ][ 'file' ], $template_real_path ) );
		}

		//if($dbbt[3]['file'] == @realpath(TEMPLATEPATH . '/header.php')){
		if ( $is_template_file && $is_direct_call ) {
			$ret = $this->language_url( $this->this_lang );
		} else {
			$ret = $setting;
		}

		return $ret;
	}

	function query_vars( $public_query_vars )
	{
		$public_query_vars[ ] = 'lang';
		global $wp_query;
		$wp_query->query_vars[ 'lang' ] = $this->this_lang;

		return $public_query_vars;
	}

	function parse_query( $q )
	{
		global $wp_query, $wpdb;
		//if($q == $wp_query) return; // not touching the WP query
		if ( is_admin() ) {
			return $q;
		}

		$current_language = $this->get_current_language();
		$default_language = $this->get_default_language();

		if ( $current_language != $default_language ) {
			$cat_array = array();

			// cat
			if ( isset( $q->query_vars[ 'cat' ] ) && !empty( $q->query_vars[ 'cat' ] ) ) {
				$cat_array = array_map( 'intval', array_map( 'trim', explode( ',', $q->query_vars[ 'cat' ] ) ) );
			}

			// category_name
			if ( isset( $q->query_vars[ 'category_name' ] ) && !empty( $q->query_vars[ 'category_name' ] ) ) {
				$cat = get_term_by( 'slug', preg_replace( '#((.*)/)#', '', $q->query_vars[ 'category_name' ] ), 'category' );
				if ( !$cat ) {
					$cat = get_term_by( 'name', $q->query_vars[ 'category_name' ], 'category' );
				}
				if ( $cat_id = $cat->term_id ) {
					$cat_array = array( $cat_id );
				} else {
					$q->query_vars[ 'p' ] = -1;
				}
			}

			// category_and
			if ( isset( $q->query_vars[ 'category__and' ] ) && !empty( $q->query_vars[ 'category__and' ] ) ) {
				$cat_array = $q->query_vars[ 'category__and' ];
			}
			// category_in
			if ( isset( $q->query_vars[ 'category__in' ] ) && !empty( $q->query_vars[ 'category__in' ] ) ) {
				$cat_array = array_unique( array_merge( $cat_array, array_map( 'intval', $q->query_vars[ 'category__in' ] ) ) );
			}
			// category__not_in
			if ( isset( $q->query_vars[ 'category__not_in' ] ) && !empty( $q->query_vars[ 'category__not_in' ] ) ) {
				$__cats    = array_map( create_function( '$a', 'return -1*intval($a);' ), $q->query_vars[ 'category__not_in' ] );
				$cat_array = array_unique( array_merge( $cat_array, $__cats ) );
			}

			if ( !empty( $cat_array ) ) {
				$translated_ids = array();
				foreach ( $cat_array as $c ) {
					if ( intval( $c ) < 0 ) {
						$sign = -1;
					} else {
						$sign = 1;
					}
					$translated_ids[ ] = $sign * intval( icl_object_id( abs( $c ), 'category', true ) );
				}

				//cat
				if ( isset( $q->query_vars[ 'cat' ] ) && !empty( $q->query_vars[ 'cat' ] ) ) {
					$q->query_vars[ 'cat' ] = join( ',', $translated_ids );
				}

				// category_name
				if ( isset( $q->query_vars[ 'category_name' ] ) && !empty( $q->query_vars[ 'category_name' ] ) ) {
					$_ctmp                            = get_term_by( 'id', $translated_ids[ 0 ], 'category' );
					$q->query_vars[ 'category_name' ] = $_ctmp->slug;
				}
				// category__and
				if ( isset( $q->query_vars[ 'category__and' ] ) && !empty( $q->query_vars[ 'category__and' ] ) ) {
					$q->query_vars[ 'category__and' ] = $translated_ids;
				}
				// category__in
				if ( isset( $q->query_vars[ 'category__in' ] ) && !empty( $q->query_vars[ 'category__in' ] ) ) {
					$q->query_vars[ 'category__in' ] = array_filter( $translated_ids, create_function( '$a', 'return $a>0;' ) );
				}
				// category__not_in
				if ( isset( $q->query_vars[ 'category__not_in' ] ) && !empty( $q->query_vars[ 'category__not_in' ] ) ) {
					$q->query_vars[ 'category__not_in' ] = array_filter( $translated_ids, create_function( '$a', 'return $a<0;' ) );
				}

			}

			// TAGS
			$tag_array = array();
			// tag
			$tag_glue = '';
			if ( isset( $q->query_vars[ 'tag' ] ) && !empty( $q->query_vars[ 'tag' ] ) ) {
				if ( false !== strpos( $q->query_vars[ 'tag' ], ' ' ) ) {
					$tag_glue = '+';
					$exp      = explode( ' ', $q->query_vars[ 'tag' ] );
				} else {
					$tag_glue = ',';
					$exp      = explode( ',', $q->query_vars[ 'tag' ] );
				}
				foreach ( $exp as $e ) {
					$tag_array[ ] = $wpdb->get_var( $wpdb->prepare( "SELECT x.term_id FROM $wpdb->terms t
                        JOIN $wpdb->term_taxonomy x ON t.term_id=x.term_id WHERE x.taxonomy='post_tag' AND t.slug=%s", $e ) );
				}
				$_tmp = array_unique( $tag_array );
				if ( count( $_tmp ) == 1 && empty( $_tmp[ 0 ] ) ) {
					$tag_array = array();
				}
			}
			// tag_id
			if ( isset( $q->query_vars[ 'tag_id' ] ) && !empty( $q->query_vars[ 'tag_id' ] ) ) {
				$tag_array = array_map( 'trim', explode( ',', $q->query_vars[ 'tag_id' ] ) );
			}

			// tag__and
			if ( isset( $q->query_vars[ 'tag__and' ] ) && !empty( $q->query_vars[ 'tag__and' ] ) ) {
				$tag_array = $q->query_vars[ 'tag__and' ];
			}
			// tag__in
			if ( isset( $q->query_vars[ 'tag__in' ] ) && !empty( $q->query_vars[ 'tag__in' ] ) ) {
				$tag_array = $q->query_vars[ 'tag__in' ];
			}
			// tag__not_in
			if ( isset( $q->query_vars[ 'tag__not_in' ] ) && !empty( $q->query_vars[ 'tag__not_in' ] ) ) {
				$tag_array = $q->query_vars[ 'tag__not_in' ];
			}
			// tag_slug__in
			if ( isset( $q->query_vars[ 'tag_slug__in' ] ) && !empty( $q->query_vars[ 'tag_slug__in' ] ) ) {
				foreach ( $q->query_vars[ 'tag_slug__in' ] as $t ) {
					if ( $tg = $wpdb->get_var( $wpdb->prepare( "
                                SELECT x.term_id FROM $wpdb->terms t
                                JOIN $wpdb->term_taxonomy x ON t.term_id=x.term_id
                                WHERE x.taxonomy='post_tag' AND t.slug=%s", $t ) )
					) {
						$tag_array[ ] = $tg;
					}
				}
			}

			// tag_slug__and
			if ( isset( $q->query_vars[ 'tag_slug__and' ] ) && !empty( $q->query_vars[ 'tag_slug__and' ] ) ) {
				foreach ( $q->query_vars[ 'tag_slug__and' ] as $t ) {
					$tag_array[ ] = $wpdb->get_var( $wpdb->prepare( "SELECT x.term_id FROM $wpdb->terms t
                        JOIN $wpdb->term_taxonomy x ON t.term_id=x.term_id WHERE x.taxonomy='post_tag' AND t.slug=%s", $t ) );
				}
			}

			if ( !empty( $tag_array ) ) {
				$translated_ids = array();
				foreach ( $tag_array as $c ) {
					if ( intval( $c ) < 0 ) {
						$sign = -1;
					} else {
						$sign = 1;
					}
					$_tid              = intval( icl_object_id( abs( $c ), 'post_tag', true ) );
					$translated_ids[ ] = $sign * $_tid;
				}
			}


			if ( !empty( $translated_ids ) ) {
				//tag
				if ( isset( $q->query_vars[ 'tag' ] ) && !empty( $q->query_vars[ 'tag' ] ) ) {
					$slugs                  = $wpdb->get_col( "SELECT slug FROM $wpdb->terms WHERE term_id IN (" . join( ',', $translated_ids ) . ")" );
					$q->query_vars[ 'tag' ] = join( $tag_glue, $slugs );
				}
				//tag_id
				if ( isset( $q->query_vars[ 'tag_id' ] ) && !empty( $q->query_vars[ 'tag_id' ] ) ) {
					$q->query_vars[ 'tag_id' ] = join( ',', $translated_ids );
				}
				// tag__and
				if ( isset( $q->query_vars[ 'tag__and' ] ) && !empty( $q->query_vars[ 'tag__and' ] ) ) {
					$q->query_vars[ 'tag__and' ] = $translated_ids;
				}
				// tag__in
				if ( isset( $q->query_vars[ 'tag__in' ] ) && !empty( $q->query_vars[ 'tag__in' ] ) ) {
					$q->query_vars[ 'tag__in' ] = $translated_ids;
				}
				// tag__not_in
				if ( isset( $q->query_vars[ 'tag__not_in' ] ) && !empty( $q->query_vars[ 'tag__not_in' ] ) ) {
					$q->query_vars[ 'tag__not_in' ] = array_map( 'abs', $translated_ids );
				}
				// tag_slug__in
				if ( isset( $q->query_vars[ 'tag_slug__in' ] ) && !empty( $q->query_vars[ 'tag_slug__in' ] ) ) {
					$q->query_vars[ 'tag_slug__in' ] = $wpdb->get_col( "SELECT slug FROM $wpdb->terms WHERE term_id IN (" . join( ',', $translated_ids ) . ")" );
				}
				// tag_slug__and
				if ( isset( $q->query_vars[ 'tag_slug__and' ] ) && !empty( $q->query_vars[ 'tag_slug__and' ] ) ) {
					$q->query_vars[ 'tag_slug__and' ] = $wpdb->get_col( "SELECT slug FROM $wpdb->terms WHERE term_id IN (" . join( ',', $translated_ids ) . ")" );
				}
			}

			// POST & PAGES
			$post_type = !empty( $q->query_vars[ 'post_type' ] ) ? $q->query_vars[ 'post_type' ] : 'post';
			if(!is_array($post_type)) {
				$post_type = (array)$post_type;
			}

			// page_id
			if ( isset( $q->query_vars[ 'page_id' ] ) && !empty( $q->query_vars[ 'page_id' ] ) ) {
				$q->query_vars[ 'page_id' ] = icl_object_id( $q->query_vars[ 'page_id' ], 'page', true );
				$q->query                   = preg_replace( '/page_id=[0-9]+/', 'page_id=' . $q->query_vars[ 'page_id' ], $q->query );
			}

			// Adjust included IDs adjusting them with translated element, if present
			if ( isset( $q->query_vars[ 'include' ] ) && !empty( $q->query_vars[ 'include' ] ) ) {
				$include_arr          = is_array( $q->query_vars[ 'include' ] ) ? $q->query_vars[ 'include' ] : explode( ',', $q->query_vars[ 'include' ] );
				$include_arr_adjusted = array();
				foreach ( $include_arr as $include_arr_id ) {
					$include_arr_adjusted[ ] = icl_object_id( $include_arr_id, get_post_type($include_arr_id), true );
				}
				$q->query_vars[ 'include' ] = is_array( $q->query_vars[ 'include' ] ) ? $include_arr_adjusted : implode( ',', $include_arr_adjusted );
			}

			// Adjust excluded IDs adjusting them with translated element, if present
			if ( isset( $q->query_vars[ 'exclude' ] ) && !empty( $q->query_vars[ 'exclude' ] ) ) {
				$exclude_arr          = is_array( $q->query_vars[ 'exclude' ] ) ? $q->query_vars[ 'exclude' ] : explode( ',', $q->query_vars[ 'exclude' ] );
				$exclude_arr_adjusted = array();
				foreach ( $exclude_arr as $exclude_arr_id ) {
					$exclude_arr_adjusted[ ] = icl_object_id( $exclude_arr_id, get_post_type($exclude_arr_id), true );
				}
				$q->query_vars[ 'exclude' ] = is_array( $q->query_vars[ 'exclude' ] ) ? $exclude_arr_adjusted : implode( ',',  $exclude_arr_adjusted );
			}

			// Adjust post id
			if ( isset( $q->query_vars[ 'p' ] ) && !empty( $q->query_vars[ 'p' ] ) ) {
				$q->query_vars[ 'p' ] = icl_object_id( $q->query_vars[ 'p' ], $post_type[0], true );
			}

			// Adjust name
			if ( isset( $q->query_vars[ 'name' ] ) && !empty( $q->query_vars[ 'name' ] ) ) {
				$pid_prepared = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_name=%s AND post_type=%s", array($q->query_vars[ 'name' ], $post_type[0]));
				$pid = $wpdb->get_var( $pid_prepared );
				if ( !empty( $pid ) ) {
					$q->query_vars[ 'p' ] = icl_object_id( $pid, $post_type[0], true );
					unset( $q->query_vars[ 'name' ] );
				}
			}

			// Adjust page name
			if ( isset( $q->query_vars[ 'pagename' ] ) && !empty( $q->query_vars[ 'pagename' ] ) ) {
				// find the page with the page name in the current language.
				$pid = $wpdb->get_var( $wpdb->prepare( "
                                SELECT ID
                                FROM $wpdb->posts p
                                JOIN {$wpdb->prefix}icl_translations t
                                ON p.ID = t.element_id AND element_type='post_page'
                                WHERE p.post_name=%s AND t.language_code = %s
                                ", $q->query_vars[ 'pagename' ], $current_language ) );

				if ( $pid ) {
					$q->query_vars[ 'page_id' ] = $pid;
					// We have found the page id
					unset( $q->query_vars[ 'pagename' ] );
					if ( $q->query_vars[ 'page_id' ] == get_option( 'page_for_posts' ) ) {
						// it's the blog page.
						$wp_query->is_page       = false;
						$wp_query->is_home       = true;
						$wp_query->is_posts_page = true;
					}
				}
			}
			// post__in
			if ( isset( $q->query_vars[ 'post__in' ] ) && !empty( $q->query_vars[ 'post__in' ] ) ) {
				$pid = array();
				foreach ( $q->query_vars[ 'post__in' ] as $p ) {
					if ( $post_type ) {
						foreach ( $post_type as $pt ) {
							$pid[ ] = icl_object_id( $p, $pt, true );
						}
					}
				}
				$q->query_vars[ 'post__in' ] = $pid;
			}
			// post__not_in
			if ( isset( $q->query_vars[ 'post__not_in' ] ) && !empty( $q->query_vars[ 'post__not_in' ] ) ) {
				$pid = array();
				foreach ( $q->query_vars[ 'post__not_in' ] as $p ) {
					if ( $post_type ) {
						foreach ( $post_type as $pt ) {
							$pid[ ] = icl_object_id( $p, $pt, true );
						}
					}
				}
				$q->query_vars[ 'post__not_in' ] = $pid;
			}
			// post_parent
			if ( isset( $q->query_vars[ 'post_parent' ] ) && !empty( $q->query_vars[ 'post_parent' ] ) && $q->query_vars[ 'post_type' ] != 'attachment' ) {
				if (  $post_type ) {
					$_parent_type                   = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID=%d", $q->query_vars[ 'post_parent' ] ) );
					$q->query_vars[ 'post_parent' ] = icl_object_id( $q->query_vars[ 'post_parent' ], $_parent_type, true );
				}
			}

			// custom taxonomies
			if ( isset( $q->query_vars[ 'taxonomy' ] ) && $q->query_vars[ 'taxonomy' ] ) {
				$tax_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE slug=%s", $q->query_vars[ 'term' ] ) );
				if ( $tax_id ) {
					$translated_tax_id = icl_object_id( $tax_id, $q->query_vars[ 'taxonomy' ], true );
				}
				if ( isset( $translated_tax_id ) ) {
					$q->query_vars[ 'term' ]                  = $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$wpdb->terms} WHERE term_id = %d", $translated_tax_id ) );
					$q->query[ $q->query_vars[ 'taxonomy' ] ] = $q->query_vars[ 'term' ];
				}
			}

			// TODO Discuss this. Why WP assumes it's there if query vars are altered?
			// Look at wp-includes/query.php line #2468 search: if ( $this->query_vars_changed ) {
			if ( !isset( $q->query_vars[ 'meta_query' ] ) ) {
				$q->query_vars[ 'meta_query' ] = array();
			}

			if ( isset( $q->query_vars[ 'tax_query' ] ) && is_array( $q->query_vars[ 'tax_query' ] ) ) {
				foreach ( $q->query[ 'tax_query' ] as $num => $fields ) {

					if ( ! isset( $fields[ 'terms' ] ) ) {
						continue;
					}

					if ( is_array( $fields[ 'terms' ] ) ) {

						foreach ( $fields[ 'terms' ] as $term ) {
							$taxonomy = get_term_by( $fields[ 'field' ], $term, $fields[ 'taxonomy' ] );
							if ( is_object( $taxonomy ) ) {
								if ( $fields[ 'field' ] == 'id' ) {
									$field = $taxonomy->term_id;
								} else {
									$field = $taxonomy->{$fields[ 'field' ]};
								}

								//$q->query[ 'tax_query' ][ $num ][ 'terms' ]    = array_diff( $q->query[ 'tax_query' ][ $num ][ 'terms' ], array( $term ) ); // removes from array element with original value
								//$q->query[ 'tax_query' ][ $num ][ 'terms' ][ ] = $field;
								//
								//$q->tax_query->queries[ $num ][ 'terms' ]    = array_diff( $q->tax_query->queries[ $num ][ 'terms' ], array( $term ) ); // see above
								//$q->tax_query->queries[ $num ][ 'terms' ][ ] = $field;
								//
								//$q->query_vars[ 'tax_query' ][ $num ][ 'terms' ]    = array_diff( $q->query_vars[ 'tax_query' ][ $num ][ 'terms' ], array( $term ) ); // see above
								//$q->query_vars[ 'tax_query' ][ $num ][ 'terms' ][ ] = $field;

								$tmp    = $q->query[ 'tax_query' ][ $num ][ 'terms' ];
								$tmp    = array_diff( $tmp, array( $term ) ); // removes from array element with original value
								$tmp[ ] = $field;
								//Reindex array
								$q->query[ 'tax_query' ][ $num ][ 'terms' ] = array_values( $tmp );

								$tmp    = $q->tax_query->queries[ $num ][ 'terms' ];
								$tmp    = array_diff( $tmp, array( $term ) ); // see above
								$tmp[ ] = $field;
								//Reindex array
								$q->tax_query->queries[ $num ][ 'terms' ] = array_values( $tmp );

								$tmp    = $q->query_vars[ 'tax_query' ][ $num ][ 'terms' ];
								$tmp    = array_diff( $tmp, array( $term ) ); // see above
								$tmp[ ] = $field;
								//Reindex array
								$q->query_vars[ 'tax_query' ][ $num ][ 'terms' ] = array_values( $tmp );

							}

							unset( $tmp );
						}
					} else if ( is_string( $fields[ 'terms' ] ) ) {
						$taxonomy = get_term_by( $fields[ 'field' ], $fields[ 'terms' ], $fields[ 'taxonomy' ] );
						if ( is_object( $taxonomy ) ) {

							$field = $taxonomy->{$fields[ 'field' ]};

							$q->query[ 'tax_query' ][ $num ][ 'terms' ] = $field;

							$q->tax_query->queries[ $num ][ 'terms' ][ 0 ] = $field;

							$q->query_vars[ 'tax_query' ][ $num ][ 'terms' ] = $field;
						}
					}
				}
			}
		}
                
		return $q;
	}

	function adjust_wp_list_pages_excludes( $pages )
	{
		foreach ( $pages as $k => $v ) {
			$pages[ $k ] = icl_object_id( $v, 'page', true );
		}

		return $pages;
	}

	function language_attributes( $output )
	{

		if ( preg_match( '#lang="[a-z-]+"#i', $output ) ) {
			$output = preg_replace( '#lang="([a-z-]+)"#i', 'lang="' . $this->this_lang . '"', $output );
		} else {
			$output .= ' lang="' . $this->this_lang . '"';
		}

		return $output;
	}

	// Localization
	function plugin_localization()
	{
		load_plugin_textdomain( 'sitepress', false, ICL_PLUGIN_FOLDER . '/locale' );
	}

	function locale()
	{
		global $locale;

		add_filter( 'language_attributes', array( $this, '_language_attributes' ) );

		$l = false;
		if(defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && isset($_REQUEST['lang'])) {
			$l = $this->get_locale($_REQUEST['lang']);
		}

		if(!$l) {
			if (defined( 'WP_ADMIN' ) ) {
				if ( get_user_meta( $this->get_current_user()->ID, 'icl_admin_language_for_edit', true ) && icl_is_post_edit() ) {
					$l = $this->get_locale( $this->get_current_language() );
				} else {
					$l = $this->get_locale( $this->admin_language );
				}
			} else {
				$l = $this->get_locale( $this->this_lang );
			}
		}

		if ( $l ) {
			$locale = $l;
		}

		// theme localization
		remove_filter( 'locale', array( $this, 'locale' ) ); //avoid infinite loop
		static $theme_locales_loaded = false;
		if ( !$theme_locales_loaded && !empty( $this->settings[ 'theme_localization_load_textdomain' ] ) && !empty( $this->settings[ 'gettext_theme_domain_name' ] ) && !empty( $this->settings[ 'theme_language_folders' ] )
		) {
			foreach ( $this->settings[ 'theme_language_folders' ] as $folder ) {
				load_textdomain( $this->settings[ 'gettext_theme_domain_name' ], $folder . '/' . $locale . '.mo' );
			}
			$theme_locales_loaded = true;
		}
		add_filter( 'locale', array( $this, 'locale' ) );


		return $locale;
	}

	function _language_attributes( $latr )
	{
		global $locale;
		$latr = preg_replace( '#lang="(.[a-z])"#i', 'lang="' . str_replace( '_', '-', $locale ) . '"', $latr );

		return $latr;
	}

	function get_language_tag( $code )
	{
		global $wpdb;
		static $all_tags = null;

		if ( is_null( $code ) )
			return false;
		if ( $tag = wp_cache_get( 'icl_language_tags_' . $code ) ) {
			return $tag;
		}

		if($all_tags==null) {
			$all_tags_data = $wpdb->get_results( "SELECT code, tag FROM {$wpdb->prefix}icl_languages" );
			foreach($all_tags_data as $tag_data) {
				$all_tags[$tag_data->code] = $tag_data->tag;
			}
		}

		$tag = $this->get_locale( $code );
		if($all_tags) {
			$tag = isset($all_tags[$code]) ? $all_tags[$code] : false;
			if ( $tag ) {
				wp_cache_set( 'icl_language_tags_' . $code, $tag );
			}
		}
		return $tag;
	}

	function get_locale( $code )
	{
		global $wpdb;
		$all_locales = null;

		if ( is_null( $code ) )
			return false;

		$found = false;
		$locale = wp_cache_get( 'get_locale' . $code, '', false, $found );
		if ( $found ) {
			return $locale;
		}

		$all_locales_data = $wpdb->get_results("SELECT code, locale FROM {$wpdb->prefix}icl_locale_map" );
		foreach($all_locales_data as $locales_data) {
			$all_locales[$locales_data->code] = $locales_data->locale;
		}

		$locale = isset($all_locales[$code]) ? $all_locales[$code] : false;
                
		if ($locale == false) {
			$this_locale_data = $wpdb->get_row( $wpdb->prepare("SELECT code, default_locale FROM {$wpdb->prefix}icl_languages WHERE code = '%s'", $code) );
			if ($this_locale_data) {
				$locale = $this_locale_data->default_locale;
			}
		}

		wp_cache_set( 'get_locale' . $code, $locale );
		return $locale;
	}

	function switch_locale( $lang_code = false )
	{
		global $l10n;
		static $original_l10n;
		if ( !empty( $lang_code ) ) {
			$original_l10n = $l10n[ 'sitepress' ];
			unset( $l10n[ 'sitepress' ] );
			load_textdomain( 'sitepress', ICL_PLUGIN_PATH . '/locale/sitepress-' . $this->get_locale( $lang_code ) . '.mo' );
		} else { // switch back
			$l10n[ 'sitepress' ] = $original_l10n;
		}
	}

	function get_locale_file_names()
	{
		global $wpdb;
		$locales = array();
		$res     = $wpdb->get_results( "
            SELECT lm.code, locale
            FROM {$wpdb->prefix}icl_locale_map lm JOIN {$wpdb->prefix}icl_languages l ON lm.code = l.code AND l.active=1" );
		foreach ( $res as $row ) {
			$locales[ $row->code ] = $row->locale;
		}

		return $locales;
	}

	function set_locale_file_names( $locale_file_names_pairs )
	{
		global $wpdb;
		$lfn = $this->get_locale_file_names();

		$new = array_diff( array_keys( $locale_file_names_pairs ), array_keys( $lfn ) );
		if ( !empty( $new ) ) {
			foreach ( $new as $code ) {
				$wpdb->insert( $wpdb->prefix . 'icl_locale_map', array( 'code' => $code, 'locale' => $locale_file_names_pairs[ $code ] ) );
			}
		}
		$remove = array_diff( array_keys( $lfn ), array_keys( $locale_file_names_pairs ) );
		if ( !empty( $remove ) ) {
			$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_locale_map WHERE code IN (" . join( ',', array_map( create_function( '$a', 'return "\'".$a."\'";' ), $remove ) ) . ")" );
		}

		$update = array_diff( $locale_file_names_pairs, $lfn );
		foreach ( $update as $code => $locale ) {
			$wpdb->update( $wpdb->prefix . 'icl_locale_map', array( 'locale' => $locale ), array( 'code' => $code ) );
		}

		$this->icl_locale_cache->clear();

		return true;
	}

	function pre_option_page_on_front() {
		global $wpdb;

		$result = null;

		static $page_on_front = null;

		if ($page_on_front===false || ($GLOBALS[ 'pagenow' ] == 'site-new.php' && isset( $_REQUEST[ 'action' ] ) && 'add-site' == $_REQUEST[ 'action' ] )) {
			return false;
		}

		$cache_key   = $this->this_lang;
		$cache_group = 'pre_option_page_on_front';

		$found  = false;
		$result = wp_cache_get( $cache_key, $cache_group, false, $found );

		if ( !$found || ICL_DISABLE_CACHE ) {
			$result        = false;
			$page_on_front = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='page_on_front'" );
			if ( $page_on_front ) {
				$_el_lang_det = $this->get_element_language_details( $page_on_front, 'post_page' );
				if ($_el_lang_det && !empty( $_el_lang_det->trid ) ) {
					$trid         = $_el_lang_det->trid;
					$translations = $wpdb->get_results( "SELECT element_id, language_code FROM {$wpdb->prefix}icl_translations WHERE trid={$trid}" );
					foreach ( $translations as $t ) {
						if ( $t->language_code == $this->this_lang ) {
							$result = $t->element_id;
							$page   = get_post( $result );
							if ( !$page || $page->post_type != 'page' ) {
								$result = false;
							}
						}
						$cache_key = $t->language_code;
						$found     = false;
						wp_cache_get( $cache_key, $cache_group, false, $found );
						if ( !$found ) {
							wp_cache_set( $cache_key, $result, $cache_group );
						}
					}
				}

				return $result;
			}
		}

		return $result;
	}

	function pre_option_page_for_posts() {
		global $wpdb;

		static $page_for_posts = null;

		static $result = null;

		if ($page_for_posts===false || $result != null ) {
			return $result;
		}

		$cache_key   = $this->this_lang;
		$cache_group = 'pre_option_page_for_posts';

		$found = false;
		$result = wp_cache_get($cache_key, $cache_group, false, $found);

		if ( !$found || ICL_DISABLE_CACHE ) {
			$page_for_posts = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='page_for_posts'" );
			$result         = false;
			if ( $page_for_posts ) {
				$_el_lang_det = $this->get_element_language_details( $page_for_posts, 'post_page' );
				if ($_el_lang_det && !empty( $_el_lang_det->trid ) ) {
					$trid         = $_el_lang_det->trid;
					$translations = $wpdb->get_results( "SELECT element_id, language_code FROM {$wpdb->prefix}icl_translations WHERE trid={$trid}" );
					foreach ( $translations as $t ) {
						if ( $t->language_code == $this->this_lang ) {
							$result = $t->element_id;
						}
						//Cache all translations
						$cache_key = $t->language_code;
						wp_cache_set( $cache_key, $result, $cache_group );
					}

					return $result;
				}
			}
		}

		return $result;
	}

	function verify_home_and_blog_pages_translations()
	{
		global $wpdb;
		$warn_home     = $warn_posts = '';
		$page_on_front = get_option( 'page_on_front' );
		if ( 'page' == get_option( 'show_on_front' ) && $page_on_front ) {
			$page_home_trid         = $wpdb->get_var( "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id={$page_on_front} AND element_type='post_page'" );
			$page_home_translations = $this->get_element_translations( $page_home_trid, 'post_page' );
			$missing_home           = array();
			foreach ( $this->active_languages as $lang ) {
				if ( !isset( $page_home_translations[ $lang[ 'code' ] ] ) ) {
					$missing_home[ ] = $lang[ 'display_name' ];
				} elseif ( $page_home_translations[ $lang[ 'code' ] ]->post_status != 'publish' ) {
					$missing_home[ ] = $lang[ 'display_name' ];
				}
			}
			if ( !empty( $missing_home ) ) {
				$warn_home = '<div class="icl_form_errors" style="font-weight:bold">';
				$warn_home .= sprintf( __( 'Your home page does not exist or its translation is not published in %s.', 'sitepress' ), join( ', ', $missing_home ) );
				$warn_home .= '<br />';
				$warn_home .= '<a href="' . get_edit_post_link( $page_on_front ) . '">' . __( 'Edit this page to add translations', 'sitepress' ) . '</a>';
				$warn_home .= '</div>';
			}
		}
		if ( get_option( 'page_for_posts' ) ) {
			$page_for_posts          = get_option( 'page_for_posts' );
			$page_posts_trid         = $wpdb->get_var( "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id={$page_for_posts} AND element_type='post_page'" );
			$page_posts_translations = $this->get_element_translations( $page_posts_trid, 'post_page' );
			$missing_posts           = array();
			foreach ( $this->active_languages as $lang ) {
				if ( !isset( $page_posts_translations[ $lang[ 'code' ] ] ) ) {
					$missing_posts[ ] = $lang[ 'display_name' ];
				} elseif ( $page_posts_translations[ $lang[ 'code' ] ]->post_status != 'publish' ) {
					$missing_posts[ ] = $lang[ 'display_name' ];
				}
			}
			if ( !empty( $missing_posts ) ) {
				$warn_posts = '<div class="icl_form_errors" style="font-weight:bold;margin-top:4px;">';
				$warn_posts .= sprintf( __( 'Your blog page does not exist or its translation is not published in %s.', 'sitepress' ), join( ', ', $missing_posts ) );
				$warn_posts .= '<br />';
				$warn_posts .= '<a href="' . get_edit_post_link( $page_for_posts ) . '">' . __( 'Edit this page to add translations', 'sitepress' ) . '</a>';
				$warn_posts .= '</div>';
			}
		}

		return array( $warn_home, $warn_posts );
	}

	// adds the language parameter to the admin post filtering/search
	function restrict_manage_posts()
	{
		echo '<input type="hidden" name="lang" value="' . $this->this_lang . '" />';
	}

	// adds the language parameter to the admin pages search
	function restrict_manage_pages()
	{
		?>
		<script type="text/javascript">
			addLoadEvent(function () {
				jQuery('p.search-box').append('<input type="hidden" name="lang" value="<?php echo $this->this_lang ?>">');
			});
		</script>
	<?php
	}

	function get_edit_post_link( $link, $id, $context = 'display' )
	{
		global $wpdb;
		$_cache_key = $link . '|' . $id . '|' . $context;

		$cached_edit_post_link = wp_cache_get($_cache_key, 'icl_get_edit_post_link');
		if ( $cached_edit_post_link ) {

			$link = $cached_edit_post_link;

		} else {

			if ( current_user_can( 'edit_post', $id ) ) {
				if ( 'display' == $context )
					$and = '&amp;'; else
					$and = '&';

				if ( $id ) {
					$post_type = $wpdb->get_var( "SELECT post_type FROM {$wpdb->posts} WHERE ID='{$id}'" );
					$details   = $this->get_element_language_details( $id, 'post_' . $post_type );
					if ( isset( $details->language_code ) ) {
						$lang = $details->language_code;
					} else {
						$lang = $this->get_current_language();
					}
					$link .= $and . 'lang=' . $lang;
				}
			}
			wp_cache_set($_cache_key , $link,'icl_get_edit_post_link');
		}

		return $link;
	}

	function get_edit_term_link( $link, $term_id, $taxonomy, $object_type )
	{
		global $wpdb;
		$default_language = $this->get_default_language();
		$term_tax_id      = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy=%s", $term_id, $taxonomy ) );
		$details          = $this->get_element_language_details( $term_tax_id, 'tax_' . $taxonomy );
		$and              = '&';
		$current_language = $this->get_current_language();
		if ( isset( $details->language_code ) ) {
			$lang = $details->language_code;
		} else {
			$lang = $current_language;
		}

		if ( $lang != $default_language || $current_language != $default_language ) {
			$link .= $and . 'lang=' . $lang;
		}

		return $link;
	}

	function option_sticky_posts( $posts ) {
		global $wpdb;

		if ( is_array( $posts ) && !empty( $posts ) ) {
			$md5_posts           = md5( json_encode( $posts ) );
			$cache_key           = $this->this_lang . ':' . $md5_posts;
			$cache_group         = 'icl_option_sticky_posts';
			$cached_sticky_posts = wp_cache_get( $cache_key, $cache_group );
			if ( $cached_sticky_posts ) {
				return $cached_sticky_posts;
			}

			$new_posts = array();
			$posts = array_filter( $posts, create_function( '$a', 'return $a > 0;' ) );

			if(count($posts)==1) {
				$option_sticky_posts_trids_prepared = $wpdb->prepare( "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type='post_post'", array($posts[0]) );
			} else {
				$option_sticky_posts_trids_prepared =  "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id IN (" . join( ',', $posts ) . ") AND element_type='post_post'";
			}
			$trids = $wpdb->get_col( $option_sticky_posts_trids_prepared );
			if ( $trids ) {
				if ( count( $trids ) == 1 ) {
					$option_sticky_posts_prepared = $wpdb->prepare( "SELECT trid, element_id, language_code FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND element_type='post_post'", array( $trids[ 0 ] ) );
				} else {
					$option_sticky_posts_prepared =  "SELECT trid, element_id, language_code FROM {$wpdb->prefix}icl_translations WHERE trid IN (" . join( ',', $trids ) . ") AND element_type='post_post'";
				}
				$option_sticky_posts = $wpdb->get_results( $option_sticky_posts_prepared );

				foreach ( $option_sticky_posts as $option_sticky_post ) {
					if ( $option_sticky_post->language_code == $this->this_lang ) {
						if ( !in_array( $option_sticky_post->element_id, $new_posts ) ) {
							$new_posts[ ] = $option_sticky_post->element_id;
						}
					}
				}

				wp_cache_set( $cache_key, $new_posts, $cache_group );
			}

			return $new_posts;
		}

		return $posts;
	}

	function request_filter( $request )
	{
		if ( !defined( 'WP_ADMIN' ) && $this->settings[ 'language_negotiation_type' ] == 3 && isset( $request[ 'lang' ] ) ) {
			// Count the parameters that have settings and remove our 'lang ' setting it's the only one.
			// This is required so that home page detection works for other languages.
			$count = 0;
			foreach ( $request as $data ) {
				if ( $data !== '' ) {
					$count += 1;
				}
			}
			if ( $count == 1 ) {
				unset( $request[ 'lang' ] );
			}
		}

		return $request;
	}

	function noscript_notice()
	{
		?>
		<noscript>
		<div class="error"><?php echo __( 'WPML admin screens require JavaScript in order to display. JavaScript is currently off in your browser.', 'sitepress' ) ?></div></noscript><?php
	}

	function filter_queries( $sql )
	{
		global $wpdb, $pagenow;
		// keep a record of the queries
		$this->queries[ ] = $sql;

		$current_language = $this->get_current_language();
		if ( $pagenow == 'categories.php' || $pagenow == 'edit-tags.php' ) {
			if ( preg_match( '#^SELECT COUNT\(\*\) FROM ' . $wpdb->term_taxonomy . ' WHERE taxonomy = \'(category|post_tag)\' $#', $sql, $matches ) ) {
				$element_type = 'tax_' . $matches[ 1 ];
				$sql          = "
                    SELECT COUNT(*) FROM {$wpdb->term_taxonomy} tx
                        JOIN {$wpdb->prefix}icl_translations tr ON tx.term_taxonomy_id=tr.element_id
                    WHERE tx.taxonomy='{$matches[1]}' AND tr.element_type='{$element_type}' AND tr.language_code='" . $current_language . "'";
			}
		}

		if ( $pagenow == 'edit.php' || $pagenow == 'edit-pages.php' ) {
			$post_type    = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : 'post';
			$element_type = 'post_' . $post_type;
			if ( $this->is_translated_post_type( $post_type ) ) {
				if ( preg_match( '#SELECT post_status, COUNT\( \* \) AS num_posts FROM ' . $wpdb->posts . ' WHERE post_type = \'(.+)\' GROUP BY post_status#i', $sql, $matches ) ) {
					if ( 'all' != $current_language ) {
						$sql = '
                        SELECT post_status, COUNT( * ) AS num_posts
                        FROM ' . $wpdb->posts . ' p
                            JOIN ' . $wpdb->prefix . 'icl_translations t ON p.ID = t.element_id
                        WHERE p.post_type = \'' . $matches[ 1 ] . '\'
                            AND t.element_type=\'' . $element_type . '\'
                            AND t.language_code=\'' . $current_language . '\'
                        GROUP BY post_status';
					} else {
						$sql = '
                        SELECT post_status, COUNT( * ) AS num_posts
                        FROM ' . $wpdb->posts . ' p
                            JOIN ' . $wpdb->prefix . 'icl_translations t ON p.ID = t.element_id
                            JOIN ' . $wpdb->prefix . 'icl_languages l ON t.language_code = l.code AND l.active = 1
                        WHERE p.post_type = \'' . $matches[ 1 ] . '\'
                            AND t.element_type=\'' . $element_type . '\'
                        GROUP BY post_status';
					}
				}
			}
		}

		if ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'ajax-tag-search' ) {
			$search = 'SELECT t.name FROM ' . $wpdb->term_taxonomy . ' AS tt INNER JOIN ' . $wpdb->terms . ' AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = \'' . esc_sql( $_GET[ 'tax' ] ) . '\' AND t.name LIKE (\'%' . esc_sql( $_GET[ 'q' ] ) . '%\')';
			if ( $sql == $search ) {
				$parts = parse_url( $_SERVER[ 'HTTP_REFERER' ] );
				@parse_str( $parts[ 'query' ], $query );
				$lang         = isset( $query[ 'lang' ] ) ? $query[ 'lang' ] : $this->get_language_cookie();
				$element_type = 'tax_' . $_GET[ 'tax' ];
				$sql          = 'SELECT t.name FROM ' . $wpdb->term_taxonomy . ' AS tt
                    INNER JOIN ' . $wpdb->terms . ' AS t ON tt.term_id = t.term_id
                    JOIN ' . $wpdb->prefix . 'icl_translations tr ON tt.term_taxonomy_id = tr.element_id
                    WHERE tt.taxonomy = \'' . esc_sql( $_GET[ 'tax' ] ) . '\' AND tr.language_code=\'' . $lang . '\' AND element_type=\'' . $element_type . '\'
                    AND t.name LIKE (\'%' . esc_sql( $_GET[ 'q' ] ) . '%\')
                ';
			}
		}

		// filter get page by path WP 3.9+
		if ( version_compare( $GLOBALS[ 'wp_version' ], '3.9', '>=' ) ) {
			if ( preg_match( "#\n\t\tSELECT ID, post_name, post_parent, post_type\n\t\tFROM {$wpdb->posts}\n\t\tWHERE post_name IN \(([^)]+)\)\n\t\tAND post_type IN \(([^)]+)\)#", $sql, $matches ) ) {

				//add 'post_' at the beginning of each post type
				$post_types = explode( ',', str_replace('\'', '', $matches[2]) );
				$element_types = array();
				foreach ($post_types as $post_type){
						$element_types[] = "'post_".$post_type."'";
				}
				$element_types = implode(',', $element_types);

				$sql = "SELECT p.ID, p.post_name, p.post_parent, post_type
						FROM {$wpdb->posts} p
						LEFT JOIN {$wpdb->prefix}icl_translations t on t.element_id = p.ID AND t.element_type IN ({$element_types}) AND t.language_code='" . $current_language . "'
						WHERE p.post_name IN ({$matches[1]}) AND p.post_type IN ({$matches[2]})
						ORDER BY t.language_code='" . $current_language . "' DESC
						";
				// added order by to ensure that we get the result in teh current language first
			}
		}elseif( version_compare( $GLOBALS[ 'wp_version' ], '3.5', '>=' ) ){
			if ( preg_match( "#SELECT ID, post_name, post_parent, post_type FROM {$wpdb->posts} WHERE post_name IN \(([^)]+)\) AND \(post_type = '([^']+)' OR post_type = 'attachment'\)#", $sql, $matches ) ) {
				$sql = "SELECT p.ID, p.post_name, p.post_parent, post_type
						FROM {$wpdb->posts} p
						LEFT JOIN {$wpdb->prefix}icl_translations t on t.element_id = p.ID AND t.element_type = 'post_{$matches[2]}' AND t.language_code='" . $current_language . "'
						WHERE p.post_name IN ({$matches[1]}) AND (p.post_type = '{$matches[2]}' OR p.post_type = 'attachment')
						ORDER BY t.language_code='" . $current_language . "' DESC
						";
				// added order by to ensure that we get the result in teh current language first
			}
		} else {
			// filter get page by path WP 3.3+
			if ( preg_match( "#SELECT ID, post_name, post_parent FROM {$wpdb->posts} WHERE post_name IN \(([^)]+)\) AND \(post_type = '([^']+)' OR post_type = 'attachment'\)#", $sql, $matches ) ) {
				$sql = "SELECT p.ID, p.post_name, p.post_parent
                        FROM {$wpdb->posts} p
                        LEFT JOIN {$wpdb->prefix}icl_translations t on t.element_id = p.ID AND t.element_type = 'post_{$matches[2]}' AND t.language_code='" . $current_language . "'
                        WHERE p.post_name IN ({$matches[1]}) AND (p.post_type = '{$matches[2]}' OR p.post_type = 'attachment')
                        ORDER BY t.language_code='" . $current_language . "' DESC
                        ";
				// added order by to ensure that we get the result in teh current language first
			} // filter get page by path < WP 3.3
			elseif ( preg_match( "#SELECT ID, post_name, post_parent FROM {$wpdb->posts} WHERE post_name = '([^']+)' AND \(post_type = '([^']+)' OR post_type = 'attachment'\)#", $sql, $matches ) ) {
				$sql = "SELECT p.ID, p.post_name, p.post_parent
                        FROM {$wpdb->posts} p
                        JOIN {$wpdb->prefix}icl_translations t on t.element_id = p.ID AND t.element_type = 'post_{$matches[2]}'
                        WHERE p.post_name = '{$matches[1]}' AND (p.post_type = '{$matches[2]}' OR p.post_type = 'attachment')
                            AND t.language_code='" . $current_language . "'";
			}
		}

		// filter calendar widget queries
		//elseif( preg_match("##", $sql, $matches) ){
		//
		//}

		return $sql;
	}

	function get_inactive_content()
	{
		global $wpdb;
		$inactive         = array();
		$current_language = $this->get_current_language();
		$res_p_prepared   = $wpdb->prepare( "
		           SELECT COUNT(p.ID) AS c, p.post_type, lt.name AS language FROM {$wpdb->prefix}icl_translations t
		            JOIN {$wpdb->posts} p ON t.element_id=p.ID AND t.element_type LIKE '%s'
		            JOIN {$wpdb->prefix}icl_languages l ON t.language_code = l.code AND l.active = 0
		            JOIN {$wpdb->prefix}icl_languages_translations lt ON lt.language_code = l.code  AND lt.display_language_code=%s
		            GROUP BY p.post_type, t.language_code
		        ", array('post\\_%', $current_language) );
		$res_p            = $wpdb->get_results( $res_p_prepared );
		foreach ( $res_p as $r ) {
			$inactive[ $r->language ][ $r->post_type ] = $r->c;
		}
		$res_t = $wpdb->get_results( "
           SELECT COUNT(p.term_taxonomy_id) AS c, p.taxonomy, lt.name AS language FROM {$wpdb->prefix}icl_translations t
            JOIN {$wpdb->term_taxonomy} p ON t.element_id=p.term_taxonomy_id
            JOIN {$wpdb->prefix}icl_languages l ON t.language_code = l.code AND l.active = 0
            JOIN {$wpdb->prefix}icl_languages_translations lt ON lt.language_code = l.code  AND lt.display_language_code='" . $current_language . "'
            WHERE t.element_type LIKE  'tax\\_%'
            GROUP BY p.taxonomy, t.language_code
        " );
		foreach ( $res_t as $r ) {
			if ( $r->taxonomy == 'category' && $r->c == 1 ) {
				continue; //ignore the case of just the default category that gets automatically created for a new language
			}
			$inactive[ $r->language ][ $r->taxonomy ] = $r->c;
		}

		return $inactive;
	}

	function menu_footer()
	{
		include ICL_PLUGIN_PATH . '/menu/menu-footer.php';
	}

	function _allow_calling_template_file_directly()
	{
		if ( is_404() ) {
			global $wp_query;
			$parts = parse_url( get_bloginfo( 'url' ) );
			if ( !isset( $parts[ 'path' ] ) )
				$parts[ 'path' ] = '';
			$req = str_replace( $parts[ 'path' ], '', $_SERVER[ 'REQUEST_URI' ] );
			if ( file_exists( ABSPATH . $req ) && !is_dir( ABSPATH . $req ) ) {
				$wp_query->is_404 = false;
				header( 'HTTP/1.1 200 OK' );
				include ABSPATH . $req;
				exit;
			}
		}
	}

	function show_user_options()
	{
		global $current_user;
		$active_languages = $this->get_active_languages();
		$default_language = $this->get_default_language();
		$user_language    = get_user_meta( $current_user->data->ID, 'icl_admin_language', true );
		if ( $this->settings[ 'admin_default_language' ] == '_default_' ) {
			$this->settings[ 'admin_default_language' ] = $default_language;
		}
		$lang_details           = $this->get_language_details( $this->settings[ 'admin_default_language' ] );
		$admin_default_language = $lang_details[ 'display_name' ];
		?>
		<a name="wpml"></a>
		<h3><?php _e( 'WPML language settings', 'sitepress' ); ?></h3>
		<table class="form-table">
			<tbody>
			<tr>
				<th><?php _e( 'Select your language:', 'sitepress' ) ?></th>
				<td>
					<select name="icl_user_admin_language">
						<option value=""<?php if ( $user_language == $this->settings[ 'admin_default_language' ] )
							echo ' selected="selected"' ?>><?php printf( __( 'Default admin language (currently %s)', 'sitepress' ), $admin_default_language ); ?>&nbsp;</option>
						<?php foreach ( $active_languages as $al ): ?>
							<option value="<?php echo $al[ 'code' ] ?>"<?php if ( $user_language == $al[ 'code' ] )
								echo ' selected="selected"' ?>><?php echo $al[ 'display_name' ];
								if ( $this->admin_language != $al[ 'code' ] )
									echo ' (' . $al[ 'native_name' ] . ')'; ?>&nbsp;</option>
						<?php endforeach; ?>
					</select>
					<span class="description"><?php _e( 'this will be your admin language and will also be used for translating comments.', 'sitepress' ); ?></span>
					<br/>
					<label><input type="checkbox" name="icl_admin_language_for_edit" value="1"
								  <?php if (get_user_meta( $this->get_current_user()->ID, 'icl_admin_language_for_edit', true )): ?>checked="checked"<?php endif; ?> />&nbsp;<?php _e( 'Set admin language as editing language.', 'sitepress' ); ?>
					</label>
				</td>
			</tr>
			<?php //display "hidden languages block" only if user can "manage_options"
			if ( current_user_can( 'manage_options' ) ): ?>
				<tr>
					<th><?php _e( 'Hidden languages:', 'sitepress' ) ?></th>
					<td>
						<p>
							<?php if ( !empty( $this->settings[ 'hidden_languages' ] ) ): ?>
								<?php
								if ( 1 == count( $this->settings[ 'hidden_languages' ] ) ) {
									printf( __( '%s is currently hidden to visitors.', 'sitepress' ), $active_languages[ $this->settings[ 'hidden_languages' ][ 0 ] ][ 'display_name' ] );
								} else {
									$hidden_languages_array = array();
									foreach ( $this->settings[ 'hidden_languages' ] as $l ) {
										$hidden_languages_array[ ] = $active_languages[ $l ][ 'display_name' ];
									}
									$hidden_languages = join( ', ', $hidden_languages_array );
									printf( __( '%s are currently hidden to visitors.', 'sitepress' ), $hidden_languages );
								}
								?>
							<?php else: ?>
								<?php _e( 'All languages are currently displayed. Choose what to do when site languages are hidden.', 'sitepress' ); ?>
							<?php endif; ?>
						</p>

						<p>
							<label><input name="icl_show_hidden_languages" type="checkbox" value="1" <?php
								if (get_user_meta( $current_user->data->ID, 'icl_show_hidden_languages', true )):?>checked="checked"<?php endif ?> />&nbsp;<?php
								_e( 'Display hidden languages', 'sitepress' ) ?></label>
						</p>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
	<?php
	}

	function  save_user_options()
	{
		$user_id = $_POST[ 'user_id' ];
		if ( $user_id ) {
			update_user_meta( $user_id, 'icl_admin_language', $_POST[ 'icl_user_admin_language' ] );
			update_user_meta( $user_id, 'icl_show_hidden_languages', isset( $_POST[ 'icl_show_hidden_languages' ] ) ? intval( $_POST[ 'icl_show_hidden_languages' ] ) : 0 );
			update_user_meta( $user_id, 'icl_admin_language_for_edit', isset( $_POST[ 'icl_admin_language_for_edit' ] ) ? intval( $_POST[ 'icl_admin_language_for_edit' ] ) : 0 );
			$this->icl_locale_cache->clear();
		}
	}

	function help_admin_notice()
	{
		$args = array(
			'name' => 'wpml-intro',
			'iso'  => defined( 'WPLANG' ) ? WPLANG : '',
			'src'  => get_home_url()
		);
		$q = http_build_query( $args );
		?>
		<br clear="all"/>
		<div id="message" class="updated message fade" style="clear:both;margin-top:5px;"><p>
				<?php _e( 'WPML is a powerful plugin with many features. Would you like to see a quick overview?', 'sitepress' ); ?>
			</p>

			<p>
				<a href="<?php echo ICL_API_ENDPOINT ?>/destinations/go?<?php echo $q ?>" target="_blank" class="button-primary"><?php _e( 'Yes', 'sitepress' ) ?></a>&nbsp;
				<input type="hidden" id="icl_dismiss_help_nonce" value="<?php echo $icl_dhn = wp_create_nonce( 'dismiss_help_nonce' ) ?>"/>
				<a href="admin.php?page=<?php echo basename( ICL_PLUGIN_PATH ) . '/menu/languages.php&icl_action=dismiss_help&_icl_nonce=' . $icl_dhn; ?>" class="button"><?php _e( 'No thanks, I will configure myself', 'sitepress' ) ?></a>&nbsp;
				<a title="<?php _e( 'Stop showing this message', 'sitepress' ) ?>" id="icl_dismiss_help" href=""><?php _e( 'Dismiss', 'sitepress' ) ?></a>
			</p>
		</div>
	<?php
	}

	function upgrade_notice()
	{
		include ICL_PLUGIN_PATH . '/menu/upgrade_notice.php';
	}

	function icl_reminders()
	{
		include ICL_PLUGIN_PATH . '/menu/icl_reminders.php';
	}

	function add_posts_management_column( $columns )
	{
		global $posts, $wpdb, $__management_columns_posts_translations;
		$element_type = isset( $_REQUEST[ 'post_type' ] ) ? 'post_' . $_REQUEST[ 'post_type' ] : 'post_post';
		if ( count( $this->get_active_languages() ) <= 1 || get_query_var( 'post_status' ) == 'trash' ) {
			return $columns;
		}

		if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'inline-save' && $_POST[ 'post_ID' ] ) {
			$p     = new stdClass();
			$p->ID = $_POST[ 'post_ID' ];
			$posts = array( $p );
		} elseif ( empty( $posts ) ) {
			return $columns;
		}
		if ( is_null( $__management_columns_posts_translations ) ) {
			$post_ids = array();
			foreach ( $posts as $p ) {
				$post_ids[ ] = $p->ID;
			}
			// get posts translations
			// get trids
			$trid_array = $wpdb->get_col( "
                SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_type='{$element_type}' AND element_id IN (" . join( ',', $post_ids ) . ")
            " );
			$elements_translations  = $wpdb->get_results( "
                SELECT trid, element_id, language_code, source_language_code FROM {$wpdb->prefix}icl_translations WHERE trid IN (" . join( ',', $trid_array ) . ")

            " );
			foreach ( $elements_translations as $v ) {
				$by_trid[ $v->trid ][ ] = $v;
			}

			foreach ( $elements_translations as $v ) {
				if ( in_array( $v->element_id, $post_ids ) ) {
					$el_trid = $v->trid;
					foreach ( $elements_translations as $val ) {
						if ( $val->trid == $el_trid ) {
							$__management_columns_posts_translations[ $v->element_id ][ $val->language_code ] = $val;
						}
					}
				}
			}
		}
		$active_languages = $this->get_active_languages();
		$languages = array();
		foreach ( $active_languages as $v ) {
			if ( $v[ 'code' ] == $this->get_current_language() )
				continue;
			$languages[ ] = $v[ 'code' ];
		}
		$res = $wpdb->get_results( "
            SELECT f.lang_code, f.flag, f.from_template, l.name
            FROM {$wpdb->prefix}icl_flags f
                JOIN {$wpdb->prefix}icl_languages_translations l ON f.lang_code = l.language_code
            WHERE l.display_language_code = '" . $this->admin_language . "' AND f.lang_code IN('" . join( "','", $languages ) . "')
        " );

		foreach ( $res as $r ) {
			if ( $r->from_template ) {
				$wp_upload_dir = wp_upload_dir();
				$flag_path         = $wp_upload_dir[ 'baseurl' ] . '/flags/';
			} else {
				$flag_path = ICL_PLUGIN_URL . '/res/flags/';
			}
			$flags[ $r->lang_code ] = '<img src="' . $flag_path . $r->flag . '" width="18" height="12" alt="' . $r->name . '" title="' . $r->name . '" />';
		}

		$flags_column = '';
		foreach ( $active_languages as $v ) {
			if ( isset( $flags[ $v[ 'code' ] ] ) )
				$flags_column .= $flags[ $v[ 'code' ] ];
		}

		$new_columns = array();
		foreach ( $columns as $k => $v ) {
			$new_columns[ $k ] = $v;
			if ( $k == 'title' ) {
				$new_columns[ 'icl_translations' ] = $flags_column;
			}
		}

		return $new_columns;
	}

	function add_content_for_posts_management_column( $column_name )
	{
		if ( $column_name != 'icl_translations' )
			return;

		global $wpdb, $id, $__management_columns_posts_translations, $sitepress, $iclTranslationManagement;
		$active_languages = $this->get_active_languages();
		$current_language = $this->get_current_language();
		foreach ( $active_languages as $v ) {
			if ( $v[ 'code' ] == $current_language )
				continue;
			$post_type = isset( $_REQUEST[ 'post_type' ] ) ? $_REQUEST[ 'post_type' ] : 'post';
			if ( isset( $__management_columns_posts_translations[ $id ][ $v[ 'code' ] ] ) && $__management_columns_posts_translations[ $id ][ $v[ 'code' ] ]->element_id ) {
				// Translation exists
				$exist_translation    = true;
				$trid                 = $this->get_element_trid( $__management_columns_posts_translations[ $id ][ $v[ 'code' ] ]->element_id, 'post_' . $post_type );
				$source_language_code = $wpdb->get_var( $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND source_language_code IS NULL", $trid ) );
				$needs_update         = $wpdb->get_var( $wpdb->prepare( "
                        SELECT needs_update
                        FROM {$wpdb->prefix}icl_translation_status s JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = s.translation_id
                        WHERE t.trid = %d AND t.language_code = '%s'
                    ", $trid, $v[ 'code' ] ) );
				if ( $needs_update ) {
					$img = 'needs-update.png';
					$alt = sprintf( __( 'Update %s translation', 'sitepress' ), $v[ 'display_name' ] );
				} else {
					$img = 'edit_translation.png';
					$alt = sprintf( __( 'Edit the %s translation', 'sitepress' ), $v[ 'display_name' ] );
				}

				switch ( $iclTranslationManagement->settings[ 'doc_translation_method' ] ) {
					case ICL_TM_TMETHOD_EDITOR:
						$job_id = $iclTranslationManagement->get_translation_job_id( $__management_columns_posts_translations[ $id ][ $v[ 'code' ] ]->trid, $v[ 'code' ] );
						$args   = array( 'lang_from' => $current_language, 'lang_to' => $v[ 'code' ], 'job_id' => @intval( $job_id ) );
						// is a translator of this document?
						$current_user_is_translator = $iclTranslationManagement->is_translator( $this->get_current_user()->ID, $args );
						if ( !$current_user_is_translator ) {
							$img  = 'edit_translation_disabled.png';
							$link = '#';

							// is a translator of this language?
							unset( $args[ 'job_id' ] );
							$current_user_is_translator = $iclTranslationManagement->is_translator( $this->get_current_user()->ID, $args );
							if ( $current_user_is_translator ) {
								$alt = sprintf( __( "You can't edit this translation because you're not the translator. <a%s>Learn more.</a>", 'sitepress' ), ' href="https://wpml.org/?page_id=52218"' );
							} else {
								$alt = sprintf( __( "You can't edit this translation because you're not a %s translator. <a%s>Learn more.</a>", 'sitepress' ), $v[ 'display_name' ], ' href="https://wpml.org/?page_id=52218"' );
							}

						} elseif ( $v[ 'code' ] == $source_language_code ) {
							$img  = 'edit_translation_disabled.png';
							$link = '#';
							$alt  = __( "You can't edit the original document using the translation editor", 'sitepress' );

						} else {
							if ( $job_id ) {
								$link = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/translations-queue.php&job_id=' . $job_id . '&lang=' . $v[ 'code' ] );
							} else {
								$link = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/translations-queue.php&icl_tm_action=create_job&iclpost[]=' . $id . '&translate_to[' . $v[ 'code' ] . ']=1&iclnonce=' . wp_create_nonce( 'pro-translation-icl' ) . '&lang=' . $v[ 'code' ] );
							}
						}
						break;
					case ICL_TM_TMETHOD_PRO:
						if ( !$__management_columns_posts_translations[ $id ][ $v[ 'code' ] ]->source_language_code ) {
							$link = get_edit_post_link( $__management_columns_posts_translations[ $id ][ $v[ 'code' ] ]->element_id );
							$alt  = __( 'Edit the original document', 'sitepress' );
						} else {
							$job_id = $iclTranslationManagement->get_translation_job_id( $__management_columns_posts_translations[ $id ][ $v[ 'code' ] ]->trid, $v[ 'code' ] );
							if ( $job_id ) {
								$job_details = $iclTranslationManagement->get_translation_job( $job_id );
								if ( $job_details->status == ICL_TM_IN_PROGRESS || $job_details->status == ICL_TM_WAITING_FOR_TRANSLATOR ) {
									$img  = 'in-progress.png';
									$alt  = sprintf( __( 'Translation to %s is in progress', 'sitepress' ), $v[ 'display_name' ] );
									$link = false;
									echo '<img style="padding:1px;margin:2px;" border="0" src="' . ICL_PLUGIN_URL . '/res/img/' . $img . '" title="' . $alt . '" alt="' . $alt . '" width="16" height="16" />';
								} else {
									$link = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/translations-queue.php&job_id=' . $job_id );
								}
							}
						}
						break;
					default:
						$link = 'post.php?post_type=' . $post_type . '&action=edit&amp;post=' . $__management_columns_posts_translations[ $id ][ $v[ 'code' ] ]->element_id . '&amp;lang=' . $v[ 'code' ];
				}

			} else {
				// Translation does not exist
				$exist_translation = false;
				$img               = 'add_translation.png';
				$alt               = sprintf( __( 'Add translation to %s', 'sitepress' ), $v[ 'display_name' ] );
				$default_language  = $this->get_default_language();
				$src_lang          = $current_language;

				if($src_lang == 'all') {
					$trid = $sitepress->get_element_trid($id, 'post_' . $post_type);
					$element_translations = $sitepress->get_element_translations($trid, 'post_' . $post_type);
					foreach($element_translations as $element_translation) {
						if($element_translation->original) {
							$src_lang = $element_translation->language_code;
							break;
						}
					}
				}

				switch ( $iclTranslationManagement->settings[ 'doc_translation_method' ] ) {
					case ICL_TM_TMETHOD_EDITOR:
						if ( isset( $__management_columns_posts_translations[ $id ][ $v[ 'code' ] ] ) ) {
							$job_id = $iclTranslationManagement->get_translation_job_id( $__management_columns_posts_translations[ $id ][ $v[ 'code' ] ]->trid, $v[ 'code' ] );
						} else {
							$job_id = 0;
						}
						$args                       = array( 'lang_from' => $src_lang, 'lang_to' => $v[ 'code' ], 'job_id' => @intval( $job_id ) );
						$current_user_is_translator = $iclTranslationManagement->is_translator( $this->get_current_user()->ID, $args );

						if ( $job_id ) {
							if ( $current_user_is_translator ) {
								$job_details = $iclTranslationManagement->get_translation_job( $job_id );
								if ( $job_details && $job_details->status == ICL_TM_IN_PROGRESS ) {
									$img = 'in-progress.png';
									$alt = sprintf( __( 'Translation to %s is in progress', 'sitepress' ), $v[ 'display_name' ] );
								}
								$link = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/translations-queue.php&job_id=' . $job_id . '&lang=' . $v[ 'code' ] );
							} else {
								$link = '#';
								$tres = $wpdb->get_row( $wpdb->prepare( "
                                    SELECT s.* FROM {$wpdb->prefix}icl_translation_status s
                                        JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = s.rid
                                        WHERE job_id=%d

                                ", $job_id ) );
								if ( $tres->status == ICL_TM_IN_PROGRESS ) {
									$img = 'in-progress.png';
									$alt = sprintf( __( 'Translation to %s is in progress (by a different translator)', 'sitepress' ), $v[ 'display_name' ] );
								} elseif ( $tres->status == ICL_TM_NOT_TRANSLATED || $tres->status == ICL_TM_WAITING_FOR_TRANSLATOR ) {
									$img = 'add_translation_disabled.png';
									$alt = sprintf( __( 'Translation to %s is in progress (by a different translator)', 'sitepress' ), $v[ 'display_name' ] );
								} elseif ( $tres->status == ICL_TM_NEEDS_UPDATE || $tres->status == ICL_TM_COMPLETE ) {
									$img = 'edit_translation_disabled.png';
									$alt = sprintf( __( 'Translation to %s is maintained by a different translator', 'sitepress' ), $v[ 'display_name' ] );
								}
							}
						} else {
							if ( $current_user_is_translator ) {
								$link = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/translations-queue.php&icl_tm_action=create_job&iclpost[]=' . $id . '&translate_to[' . $v[ 'code' ] . ']=1&iclnonce=' . wp_create_nonce( 'pro-translation-icl' ) );
								if ( $current_language != $default_language ) {
									$link .= '&translate_from=' . $current_language;
								}
							} else {
								$link = '#';
								$img  = 'add_translation_disabled.png';
								$alt  = sprintf( __( "You can't add this translation because you're not a %s translator. <a%s>Learn more.</a>", 'sitepress' ), $v[ 'display_name' ], ' href="https://wpml.org/?page_id=52218"' );
							}
						}

						break;
					case ICL_TM_TMETHOD_PRO:
						if ( $this->have_icl_translator( $src_lang, $v[ 'code' ] ) ) {

							if ( !isset( $__management_columns_posts_translations[ $id ][ $v[ 'code' ] ] ) )
								$job_id = false; else
								$job_id = @$iclTranslationManagement->get_translation_job_id( $__management_columns_posts_translations[ $id ][ $v[ 'code' ] ]->trid, $v[ 'code' ] );

							if ( $job_id ) {
								$job_details = $iclTranslationManagement->get_translation_job( $job_id );
								if ( $job_details->status == ICL_TM_IN_PROGRESS || $job_details->status == ICL_TM_WAITING_FOR_TRANSLATOR ) {
									$img  = 'in-progress.png';
									$alt  = sprintf( __( 'Translation to %s is in progress', 'sitepress' ), $v[ 'display_name' ] );
									$link = false;
									echo '<img style="padding:1px;margin:2px;" border="0" src="' . ICL_PLUGIN_URL . '/res/img/' . $img . '" title="' . $alt . '" alt="' . $alt . '" width="16" height="16" />';
								} else {
									$link = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/translations-queue.php&job_id=' . $job_id );
								}
							} else {
								$qs = array();
								if ( !empty( $_SERVER[ 'QUERY_STRING' ] ) )
									foreach ( $_exp = explode( '&', $_SERVER[ 'QUERY_STRING' ] ) as $q => $qv ) {
										$__exp      = explode( '=', $qv );
										$__exp[ 0 ] = preg_replace( '#\[(.*)\]#', '', $__exp[ 0 ] );
										if ( !in_array( $__exp[ 0 ], array( 'icl_tm_action', 'translate_from', 'translate_to', 'iclpost', 'service', 'iclnonce' ) ) ) {
											$qs[ $q ] = $qv;
										}
									}
								$link = admin_url( 'edit.php?' . join( '&', $qs ) . '&icl_tm_action=send_jobs&translate_from=' . $src_lang . '&translate_to[' . $v[ 'code' ] . ']=1&iclpost[]=' . $id . '&service=icanlocalize&iclnonce=' . wp_create_nonce( 'pro-translation-icl' ) );

							}
						} else {
							$link = false;
							$alt  = sprintf( __( 'Get %s translators', 'sitepress' ), $v[ 'display_name' ] );
							$img  = 'add_translators.png';
							echo $this->create_icl_popup_link( "@select-translators;{$src_lang};{$v['code']}@", array(
																													 'ar' => 1, 'title' => $alt, 'unload_cb' => 'icl_pt_reload_translation_box'
																												) ) . '<img style="padding:1px;margin:2px;" border="0" src="' . ICL_PLUGIN_URL . '/res/img/' . $img . '" alt="' . $alt . '" width="16" height="16" />' . '</a>';
						}
						break;
					default:
						global $sitepress;
						$trid = $sitepress->get_element_trid( $id, 'post_' . $post_type );
						$link = 'post-new.php?post_type=' . $post_type . '&trid=' . $trid . '&amp;lang=' . $v[ 'code' ] . '&amp;source_lang=' . $src_lang;

				}
			}
			if ( isset($link) && $link ) {
				if ( $link == '#' ) {
					icl_pop_info( $alt, ICL_PLUGIN_URL . '/res/img/' . $img, array( 'icon_size' => 16, 'but_style' => array( 'icl_pop_info_but_noabs' ) ) );
				} else {
					$link = apply_filters( 'wpml_link_to_translation', $link, $exist_translation, $v[ 'code' ] );
					echo '<a href="' . $link . '" title="' . $alt . '">';
					echo '<img style="padding:1px;margin:2px;" border="0" src="' . ICL_PLUGIN_URL . '/res/img/' . $img . '" alt="' . $alt . '" width="16" height="16" />';
					echo '</a>';
				}
			}
		}
	}

	function __set_posts_management_column_width()
	{
		$w = 22 * count( $this->get_active_languages() );
		echo '<style type="text/css">.column-icl_translations{width:' . $w . 'px;}.column-icl_translations img{margin:2px;}</style>';
	}

	function display_wpml_footer()
	{
		if ( $this->settings[ 'promote_wpml' ] ) {

			$wpml_in_other_langs = array( 'es', 'de', 'ja', 'zh-hans' );
			$cl                  = in_array( ICL_LANGUAGE_CODE, $wpml_in_other_langs ) ? ICL_LANGUAGE_CODE . '/' : '';

			$wpml_in_other_langs_icl = array( 'es', 'fr', 'de' );
			$cl_icl                  = in_array( ICL_LANGUAGE_CODE, $wpml_in_other_langs_icl ) ? ICL_LANGUAGE_CODE . '/' : '';

			$nofollow_wpml = is_home() ? '' : ' rel="nofollow"';

			if ( in_array( ICL_LANGUAGE_CODE, array( 'ja', 'zh-hans', 'zh-hant', 'ko' ) ) ) {
				// parameters order is set according to teh translation
				echo '<p id="wpml_credit_footer">' . sprintf( __( '<a href="%s"%s>Multilingual WordPress</a> by <a href="%s" rel="nofollow">ICanLocalize</a>', 'sitepress' ), 'http://www.icanlocalize.com/site/' . $cl_icl, 'https://wpml.org/' . $cl, $nofollow_wpml ) . '</p>';
			} else {
				echo '<p id="wpml_credit_footer">' . sprintf( __( '<a href="%s"%s>Multilingual WordPress</a> by <a href="%s" rel="nofollow">ICanLocalize</a>', 'sitepress' ), 'https://wpml.org/' . $cl, $nofollow_wpml, 'http://www.icanlocalize.com/site/' . $cl_icl ) . '</p>';
			}
		}
	}

	function xmlrpc_methods( $methods )
	{
		$methods[ 'icanlocalize.get_languages_list' ] = array( $this, 'xmlrpc_get_languages_list' );

		return $methods;
	}

	function xmlrpc_call_actions( $action )
	{
		global $HTTP_RAW_POST_DATA, $wpdb;
		$params = icl_xml2array( $HTTP_RAW_POST_DATA );
		add_filter( 'is_protected_meta', array( $this, 'xml_unprotect_wpml_meta' ), 10, 3 );
		switch ( $action ) {
			case 'wp.getPage':
			case 'blogger.getPost': // yet this doesn't return custom fields
				if ( isset( $params[ 'methodCall' ][ 'params' ][ 'param' ][ 1 ][ 'value' ][ 'int' ][ 'value' ] ) ) {
					$page_id         = $params[ 'methodCall' ][ 'params' ][ 'param' ][ 1 ][ 'value' ][ 'int' ][ 'value' ];
					$lang_details    = $this->get_element_language_details( $page_id, 'post_' . get_post_type( $page_id ) );
					$this->this_lang = $lang_details->language_code; // set the current language to the posts language
					update_post_meta( $page_id, '_wpml_language', $lang_details->language_code );
					update_post_meta( $page_id, '_wpml_trid', $lang_details->trid );
					$active_languages = $this->get_active_languages();
					$res              = $this->get_element_translations( $lang_details->trid );
					$translations     = array();
					foreach ( $active_languages as $k => $v ) {
						if ( $page_id != $res[ $k ]->element_id ) {
							$translations[ $k ] = isset( $res[ $k ]->element_id ) ? $res[ $k ]->element_id : 0;
						}
					}
					update_post_meta( $page_id, '_wpml_translations', json_encode( $translations ) );
				}
				break;
			case 'metaWeblog.getPost':
				if ( isset( $params[ 'methodCall' ][ 'params' ][ 'param' ][ 0 ][ 'value' ][ 'int' ][ 'value' ] ) ) {
					$page_id         = $params[ 'methodCall' ][ 'params' ][ 'param' ][ 0 ][ 'value' ][ 'int' ][ 'value' ];
					$lang_details    = $this->get_element_language_details( $page_id, 'post_' . get_post_type( $page_id ) );
					$this->this_lang = $lang_details->language_code; // set the current language to the posts language
					update_post_meta( $page_id, '_wpml_language', $lang_details->language_code );
					update_post_meta( $page_id, '_wpml_trid', $lang_details->trid );
					$active_languages = $this->get_active_languages();
					$res              = $this->get_element_translations( $lang_details->trid );
					$translations     = array();
					foreach ( $active_languages as $k => $v ) {
						if ( isset( $res[ $k ] ) && $page_id != $res[ $k ]->element_id ) {
							$translations[ $k ] = isset( $res[ $k ]->element_id ) ? $res[ $k ]->element_id : 0;
						}
					}
					update_post_meta( $page_id, '_wpml_translations', json_encode( $translations ) );
				}
				break;
			case 'metaWeblog.getRecentPosts':
				if ( isset( $params[ 'methodCall' ][ 'params' ][ 'param' ][ 3 ][ 'value' ][ 'int' ][ 'value' ] ) ) {
					$num_posts = intval( $params[ 'methodCall' ][ 'params' ][ 'param' ][ 3 ][ 'value' ][ 'int' ][ 'value' ] );
					if ( $num_posts ) {
						$posts = get_posts( 'suppress_filters=false&numberposts=' . $num_posts );
						foreach ( $posts as $p ) {
							$lang_details = $this->get_element_language_details( $p->ID, 'post_post' );
							update_post_meta( $p->ID, '_wpml_language', $lang_details->language_code );
							update_post_meta( $p->ID, '_wpml_trid', $lang_details->trid );
							$active_languages = $this->get_active_languages();
							$res              = $this->get_element_translations( $lang_details->trid );
							$translations     = array();
							foreach ( $active_languages as $k => $v ) {
								if ( $p->ID != $res[ $k ]->element_id ) {
									$translations[ $k ] = isset( $res[ $k ]->element_id ) ? $res[ $k ]->element_id : 0;
								}
							}
							update_post_meta( $p->ID, '_wpml_translations', json_encode( $translations ) );
						}
					}
				}
				break;

			case 'metaWeblog.newPost':

				$custom_fields = false;
				if ( is_array( $params[ 'methodCall' ][ 'params' ][ 'param' ][ 3 ][ 'value' ][ 'struct' ][ 'member' ] ) ) {
					foreach ( $params[ 'methodCall' ][ 'params' ][ 'param' ][ 3 ][ 'value' ][ 'struct' ][ 'member' ] as $m ) {
						if ( $m[ 'name' ][ 'value' ] == 'custom_fields' ) {
							$custom_fields_raw = $m[ 'value' ][ 'array' ][ 'data' ][ 'value' ];
							break;
						}
					}
				}

				if ( !empty( $custom_fields_raw ) ) {
					foreach ( $custom_fields_raw as $cf ) {
						$key = $value = null;
						foreach ( $cf[ 'struct' ][ 'member' ] as $m ) {
							if ( $m[ 'name' ][ 'value' ] == 'key' )
								$key = $m[ 'value' ][ 'string' ][ 'value' ]; elseif ( $m[ 'name' ][ 'value' ] == 'value' )
								$value = $m[ 'value' ][ 'string' ][ 'value' ];
						}
						if ( $key !== null && $value !== null )
							$custom_fields[ $key ] = $value;
					}
				}

				if ( is_array( $custom_fields ) && isset( $custom_fields[ '_wpml_language' ] ) && isset( $custom_fields[ '_wpml_trid' ] ) ) {

					$icl_post_language = $custom_fields[ '_wpml_language' ];
					$icl_trid          = $custom_fields[ '_wpml_trid' ];

					$post_type = $params[ 'methodCall' ][ 'params' ][ 'param' ][ 3 ][ 'value' ][ 'struct' ][ 'member' ][ 2 ][ 'value' ][ 'string' ][ 'value' ];
					if ( !$wpdb->get_var( "SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_type='post_{$post_type}' AND trid={$icl_trid} AND language_code='{$icl_post_language}'" ) ) {
						$_POST[ 'icl_post_language' ] = $icl_post_language;
						$_POST[ 'icl_trid' ]          = $icl_trid;

					} else {
						$IXR_Error = new IXR_Error( 401, __( 'A translation for this post already exists', 'sitepress' ) );
						echo $IXR_Error->getXml();
						exit( 1 );
					}

				}
				break;
			case 'metaWeblog.editPost':
				$post_id = $params[ 'methodCall' ][ 'params' ][ 'param' ][ 0 ][ 'value' ][ 'int' ][ 'value' ];
				if ( !$post_id ) {
					break;
				}
				$custom_fields = $params[ 'methodCall' ][ 'params' ][ 'param' ][ 3 ][ 'value' ][ 'struct' ][ 'member' ][ 3 ][ 'value' ][ 'array' ][ 'data' ][ 'value' ];
				if ( is_array( $custom_fields ) ) {
					$icl_trid = false;
					$icl_post_language = false;
					foreach ( $custom_fields as $cf ) {
						if ( $cf[ 'struct' ][ 'member' ][ 0 ][ 'value' ][ 'string' ][ 'value' ] == '_wpml_language' ) {
							$icl_post_language = $cf[ 'struct' ][ 'member' ][ 1 ][ 'value' ][ 'string' ][ 'value' ];
						} elseif ( $cf[ 'struct' ][ 'member' ][ 0 ][ 'value' ][ 'string' ][ 'value' ] == '_wpml_trid' ) {
							$icl_trid = $cf[ 'struct' ][ 'member' ][ 1 ][ 'value' ][ 'string' ][ 'value' ];
						}
					}

					$epost_id = $wpdb->get_var( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type='post_post'
                        AND trid={$icl_trid} AND language_code='{$icl_post_language}'" );
					if ( $icl_trid && $icl_post_language && ( !$epost_id || $epost_id == $post_id ) ) {
						$_POST[ 'icl_post_language' ] = $icl_post_language;
						$_POST[ 'icl_trid' ]          = $icl_trid;
					} else {
						$IXR_Error = new IXR_Error( 401, __( 'A translation in this language already exists', 'sitepress' ) );
						echo $IXR_Error->getXml();
						exit( 1 );
					}
				}
				break;
		}
	}

	function xmlrpc_get_languages_list( $lang )
	{
		global $wpdb;
		if ( !is_null( $lang ) ) {
			if ( !$wpdb->get_var( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE code='" . esc_sql( $lang ) . "'" ) ) { 
				$IXR_Error = new IXR_Error( 401, __( 'Invalid language code', 'sitepress' ) );
				echo $IXR_Error->getXml();
				exit( 1 );
			}
			$this->admin_language = $lang;
		}
		define( 'WP_ADMIN', true ); // hack - allow to force display language
		$active_languages = $this->get_active_languages( true );

		return $active_languages;

	}

	function xml_unprotect_wpml_meta( $protected, $meta_key, $meta_type )
	{
		$metas_list = array( '_wpml_trid', '_wpml_translations', '_wpml_language' );
		if ( in_array( $meta_key, $metas_list, true ) ) {
			$protected = false;
		}

		return $protected;
	}

	function get_current_action_step()
	{
		global $wpdb;

		$icl_lang_status = $this->settings[ 'icl_lang_status' ];
		$has_translators = false;
		foreach ( (array)$icl_lang_status as $k => $lang ) {
			if ( !is_numeric( $k ) )
				continue;
			if ( !empty( $lang[ 'translators' ] ) ) {
				$has_translators = true;
				break;
			}
		}
		if ( !$has_translators ) {
			return 0;
		}

		$cms_count = $wpdb->get_var( "SELECT COUNT(rid) FROM {$wpdb->prefix}icl_core_status WHERE status=3" );

		if ( $cms_count > 0 ) {
			return 4;
		}
		$cms_count = $wpdb->get_var( "SELECT COUNT(rid) FROM {$wpdb->prefix}icl_core_status WHERE 1" );
		if ( $cms_count == 0 ) {
			// No documents sent yet
			return 1;
		}

		if ( $this->settings[ 'icl_balance' ] <= 0 ) {
			return 2;
		}


		return 3;

	}

	function show_action_list()
	{
		$steps = array(
			__( 'Select translators', 'sitepress' ), __( 'Send documents to translation', 'sitepress' ), __( 'Deposit payment', 'sitepress' ), __( 'Translations will be returned to your site', 'sitepress' )
		);

		$current_step = $this->get_current_action_step();
		if ( $current_step >= sizeof( $steps ) ) {
			// everything is already setup.
			if ( $this->settings[ 'last_action_step_shown' ] ) {
				return '';
			} else {
				$this->save_settings( array( 'last_action_step_shown' => 1 ) );
			}
		}

		$output = '
            <h3>' . __( 'Setup check list', 'sitepress' ) . '</h3>
            <ul id="icl_check_list">';

		foreach ( $steps as $index => $step ) {
			$step_data = $step;

			if ( $index < $current_step || ( $index == 4 && $this->settings[ 'icl_balance' ] > 0 ) ) {
				$attr = ' class="icl_tick"';
			} else {
				$attr = ' class="icl_next_step"';
			}

			if ( $index == $current_step ) {
				$output .= '<li class="icl_info"><b>' . $step_data . '</b></li>';
			} else {
				$output .= '<li' . $attr . '>' . $step_data . '</li>';
			}
			$output .= "\n";
		}

		$output .= '
            </ul>';

		return $output;
	}

	function show_pro_sidebar()
	{
		$output = '<div id="icl_sidebar" class="icl_sidebar" style="display:none">';

		$action_list    = $this->show_action_list();
		$show_minimized = $this->settings[ 'icl_sidebar_minimized' ];
		if ( $action_list != '' ) {
			$show_minimized = false;
		}

		if ( $show_minimized ) {
			$output .= '<div id="icl_sidebar_full" style="display:none">';
		} else {
			$output .= '<div id="icl_sidebar_full">';
		}

		if ( $action_list == '' ) {
			$output .= '<a id="icl_sidebar_hide" href="#">hide</a>';
		} else {
			$output .= $action_list;
		}

		$output .= '<h3>' . __( 'Help', 'sitepress' ) . '</h3>';
		$output .= '<div id="icl_help_links"></div>';
		$output .= wp_nonce_field( 'icl_help_links_nonce', '_icl_nonce_hl', false, false );
		$output .= '</div>';
		if ( $show_minimized ) {
			$output .= '<div id="icl_sidebar_hide_div">';
		} else {
			$output .= '<div id="icl_sidebar_hide_div" style="display:none">';
		}
		$output .= '<a id="icl_sidebar_show" href="#"><img width="16" height="16" src="' . ICL_PLUGIN_URL . '/res/img/question1.png' . '" alt="' . __( 'Get help', 'sitepress' ) . '" title="' . __( 'Get help', 'sitepress' ) . '" /></a>';
		$output .= wp_nonce_field( 'icl_show_sidebar_nonce', '_icl_nonce_ss', false, false );
		$output .= '</div>';
		$output .= '</div>';

		return $output;

	}

	function meta_generator_tag()
	{
		$lids = array();
		$active_languages = $this->get_active_languages();
		if($active_languages) {
			foreach ( $active_languages as $l ) {
				$lids[ ] = $l[ 'id' ];
			}
			$stt = join( ",", $lids );
			$stt .= ";" . intval( $this->get_icl_translation_enabled() );
			printf( '<meta name="generator" content="WPML ver:%s stt:%s" />' . PHP_EOL, ICL_SITEPRESS_VERSION, $stt );
		}
	}

	function set_language_cookie()
	{
		if ( !headers_sent() ) {
			if ( preg_match( '@\.(css|js|png|jpg|gif|jpeg|bmp)@i', basename( preg_replace( '@\?.*$@', '', $_SERVER[ 'REQUEST_URI' ] ) ) ) || isset( $_POST[ 'icl_ajx_action' ] ) || isset( $_POST[ '_ajax_nonce' ] ) || defined( 'DOING_AJAX' ) ) {
				return;
			}

			$server_host_name = $this->get_server_host_name();
			$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : $server_host_name;
			$cookie_path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
			setcookie( '_icl_current_language', $this->get_current_language(), time() + 86400, $cookie_path, $cookie_domain );
		}
	}

	function update_language_cookie($language_code) {
		$_COOKIE[ '_icl_current_language' ] = $language_code;
	}

	function get_language_cookie()
	{
		static $active_languages = false;
		if ( isset( $_COOKIE[ '_icl_current_language' ] ) ) {
			$lang = substr( $_COOKIE[ '_icl_current_language' ], 0, 10 );
			if(!$active_languages) {
				$active_languages = $this->get_active_languages();
			}
			if ( !isset( $active_languages[ $lang ] ) ) {
				$lang = $this->get_default_language();
			}
		} else {
			$lang = '';
		}

		return $lang;
	}

	// _icl_current_language will have to be replaced with _icl_current_language
	function set_admin_language_cookie( $lang = false )
	{
		if ( !headers_sent() ) {
			if ( preg_match( '@\.(css|js|png|jpg|gif|jpeg|bmp)@i', basename( preg_replace( '@\?.*$@', '', $_SERVER[ 'REQUEST_URI' ] ) ) ) || isset( $_POST[ 'icl_ajx_action' ] ) || isset( $_POST[ '_ajax_nonce' ] ) ) {
				return;
			}

			$parts       = parse_url( admin_url() );
			$cookie_path = $parts[ 'path' ];
			if ( $lang === false )
				$lang = $this->get_current_language();
			setcookie( '_icl_current_admin_language', $lang, time() + 7200, $cookie_path );
		}
	}

	function get_admin_language_cookie()
	{
		static $active_languages = false;
		if ( isset( $_COOKIE[ '_icl_current_admin_language' ] ) ) {
			$lang             = $_COOKIE[ '_icl_current_admin_language' ];
			if(!$active_languages) {
				$active_languages = $this->get_active_languages();
			}
			if ( !isset( $active_languages[ $lang ] ) && $lang != 'all' ) {
				$lang = $this->get_default_language();
			}
		} else {
			$lang = '';
		}

		return $lang;
	}

	function reset_admin_language_cookie()
	{
		$this->set_admin_language_cookie( $this->get_default_language() );
	}

	function rewrite_rules_filter( $value ) {

		$current_language               = $this->get_current_language();
		$default_language               = $this->get_default_language();
		$directory_for_default_language = false;
		$setting_url                    = $this->get_setting( 'urls' );
		if ( $setting_url ) {
			$directory_for_default_language = $setting_url[ 'directory_for_default_language' ];
		}

		if ( $this->get_setting( 'language_negotiation_type' ) == 1 && ( $current_language != $default_language || $directory_for_default_language ) ) {

			foreach ( (array)$value as $k => $v ) {
				$value[ $current_language . '/' . $k ] = $v;
				unset( $value[ $k ] );

			}
			$value[ $current_language . '/?$' ] = 'index.php';

		}
		
		return $value;
	}

	function is_rtl( $lang = false )
	{
		if ( is_admin() ) {
			if ( empty( $lang ) )
				$lang = $this->get_admin_language();

		} else {
			if ( empty( $lang ) )
				$lang = $this->get_current_language();
		}

		return in_array( $lang, array( 'ar', 'he', 'fa', 'ku' ) );
	}

	function get_translatable_documents( $include_not_synced = false )
	{
		global $wp_post_types;
		$icl_post_types = array();
		$attachment_is_translatable = $this->is_translated_post_type( 'attachment' );
		$exceptions = array( 'revision', 'nav_menu_item' );
		if(!$attachment_is_translatable) {
			$exceptions[] = 'attachment';
		}

		foreach ( $wp_post_types as $k => $v ) {
 			if ( !in_array( $k, $exceptions ) ) {
				if ( !$include_not_synced && ( empty( $this->settings[ 'custom_posts_sync_option' ][ $k ] ) || $this->settings[ 'custom_posts_sync_option' ][ $k ] != 1 ) && !in_array( $k, array( 'post', 'page' ) ) )
					continue;
				$icl_post_types[ $k ] = $v;
			}
		}
		$icl_post_types = apply_filters( 'get_translatable_documents', $icl_post_types );

		return $icl_post_types;
	}

	function get_translatable_taxonomies( $include_not_synced = false, $object_type = 'post' )
	{
		global $wp_taxonomies;
		$t_taxonomies = array();
		if ( $include_not_synced ) {
			if ( in_array( $object_type, $wp_taxonomies[ 'post_tag' ]->object_type ) )
				$t_taxonomies[ ] = 'post_tag';
			if ( in_array( $object_type, $wp_taxonomies[ 'category' ]->object_type ) )
				$t_taxonomies[ ] = 'category';
		}
		foreach ( $wp_taxonomies as $taxonomy_name => $taxonomy ) {
			// exceptions
			if ( 'post_format' == $taxonomy_name )
				continue;
			if ( in_array( $object_type, $taxonomy->object_type ) && !empty( $this->settings[ 'taxonomies_sync_option' ][ $taxonomy_name ] ) ) {
				$t_taxonomies[ ] = $taxonomy_name;
			}
		}

		if ( has_filter( 'get_translatable_taxonomies' ) ) {
			$filtered     = apply_filters( 'get_translatable_taxonomies', array( 'taxs' => $t_taxonomies, 'object_type' => $object_type ) );
			$t_taxonomies = $filtered[ 'taxs' ];
			if ( empty( $t_taxonomies ) )
				$t_taxonomies = array();
		}

		return $t_taxonomies;
	}

	function is_translated_taxonomy( $tax )
	{
		global $sitepress_settings;
		$settings = empty( $sitepress_settings ) ? $this->settings : $sitepress_settings;

		$ret = false;

		if ( is_scalar( $tax ) ) {
			switch ( $tax ) {
				case 'category':
				case 'post_tag':
					$ret = true;
					break;
				default:
					if ( isset( $settings[ 'taxonomies_sync_option' ][ $tax ] ) ) {
						$ret = $settings[ 'taxonomies_sync_option' ][ $tax ];
					} elseif ( isset( $settings[ 'translation-management' ][ 'taxonomies_readonly_config' ][ $tax ] ) && $settings[ 'translation-management' ][ 'taxonomies_readonly_config' ][ $tax ] == 1 ) {
						$ret = true;
					} else {
						$ret = false;
					}
			}
		}

		return $ret;
	}

	function is_translated_post_type( $type )
	{
		global $sitepress_settings;
		$settings = empty( $sitepress_settings ) ? $this->settings : $sitepress_settings;

		$ret = false;

		if ( is_scalar( $type ) ) {
			switch ( $type ) {
				case 'post':
				case 'page':
					$ret = true;
					break;
				default:
					if ( isset( $settings[ 'custom_posts_sync_option' ][ $type ] ) ) {
						$ret = $settings[ 'custom_posts_sync_option' ][ $type ];
					} elseif ( isset( $settings[ 'translation-management' ][ 'custom_types_readonly_config' ][ $type ] ) ) {
						$ret = $settings[ 'translation-management' ][ 'custom_types_readonly_config' ][ $type ];
					} else {
						$ret = false;
					}
			}
		}

		return $ret;
	}

	function print_translatable_custom_content_status()
	{
		global $wp_taxonomies;
		$icl_post_types = $this->get_translatable_documents( true );
		$cposts         = array();
		$notice         = '';
		foreach ( $icl_post_types as $k => $v ) {
			if ( !in_array( $k, array( 'post', 'page' ) ) ) {
				$cposts[ $k ] = $v;
			}
		}
		foreach ( $cposts as $k => $cpost ) {
			if ( !isset( $this->settings[ 'custom_posts_sync_option' ][ $k ] ) ) {
				$cposts_sync_not_set[ ] = $cpost->labels->name;
			}
		}
		if ( defined( 'WPML_TM_VERSION' ) && !empty( $cposts_sync_not_set ) ) {
			$notice = '<p class="updated fade">';
			$notice .= sprintf( __( "You haven't set your <a %s>synchronization preferences</a> for these custom posts: %s. Default value was selected.", 'sitepress' ), 'href="admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=mcsetup"', '<i>' . join( '</i>, <i>', $cposts_sync_not_set ) . '</i>' );
			$notice .= '</p>';
		}

		$icl_post_types = $this->get_translatable_documents( true );
		if ( defined( 'WPML_TM_VERSION' ) && $icl_post_types ) {
			global $wpdb, $sitepress_settings;
			$default_language = $this->get_default_language();
			$custom_posts   = array();
			$icl_post_types = $this->get_translatable_documents( true );

			foreach ( $icl_post_types as $k => $v ) {
				if ( !in_array( $k, array( 'post', 'page' ) ) ) {
					$custom_posts[ $k ] = $v;
				}
			}

			foreach ( $custom_posts as $k => $custom_post ) {

				$_has_slug = isset( $custom_post->rewrite[ 'slug' ] ) && $custom_post->rewrite[ 'slug' ];
				$_translate = !empty($sitepress_settings['posts_slug_translation']['types'][$k]);
				if ( $_has_slug ) {
					if (isset($sitepress_settings[ 'st' ]) && $default_language != $sitepress_settings[ 'st' ][ 'strings_language' ] ) {
						$string_id_prepared = $wpdb->prepare( "
	                                                        SELECT s.id FROM {$wpdb->prefix}icl_strings s
	                                                            JOIN {$wpdb->prefix}icl_string_translations st
	                                                            ON st.string_id = s.id
	                                                            WHERE st.language=%s AND s.value=%s AND s.name LIKE %s
	                                                    ", array( $default_language, $custom_post->rewrite[ 'slug' ], 'URL slug: %' ) );
					} else {
						$string_id_prepared = $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}icl_strings WHERE name = %s AND value = %s ", array(
							'Url slug: ' . $custom_post->rewrite[ 'slug' ],
							$custom_post->rewrite[ 'slug' ]
						) );
					}
					$string_id = $wpdb->get_var( $string_id_prepared );
					if ( $_translate && !$string_id ) {
						$message = sprintf( __( "%s slugs are set to be translated, but they are missing their translation", 'sitepress'), $custom_post->labels->name);
						$notice .= ICL_AdminNotifier::displayInstantMessage( $message, 'error', 'below-h2', true );
					}
				}
			}
		}


		$ctaxonomies = array_diff( array_keys( (array)$wp_taxonomies ), array( 'post_tag', 'category', 'nav_menu', 'link_category', 'post_format' ) );
		foreach ( $ctaxonomies as $ctax ) {
			if ( !isset( $this->settings[ 'taxonomies_sync_option' ][ $ctax ] ) ) {
				$tax_sync_not_set[ ] = $wp_taxonomies[ $ctax ]->label;
			}
		}
		if ( defined( 'WPML_TM_VERSION' ) && !empty( $tax_sync_not_set ) ) {
			$notice .= '<p class="updated">';

			$notice .= sprintf( __( "You haven't set your <a %s>synchronization preferences</a> for these taxonomies: %s. Default value was selected.", 'sitepress' ), 'href="admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=mcsetup"', '<i>' . join( '</i>, <i>', $tax_sync_not_set ) . '</i>' );
			$notice .= '</p>';
		}

		echo $notice;
	}

	function dashboard_widget_setup()
	{
		if ( current_user_can( 'manage_options' ) ) {
			$dashboard_widgets_order = (array)get_user_option( "meta-box-order_dashboard" );
			$icl_dashboard_widget_id = 'icl_dashboard_widget';
			$all_widgets             = array();
			foreach ( $dashboard_widgets_order as $v ) {
				$all_widgets = array_merge( $all_widgets, explode( ',', $v ) );
			}
			if ( !in_array( $icl_dashboard_widget_id, $all_widgets ) ) {
				$install = true;
			} else {
				$install = false;
			}
			wp_add_dashboard_widget( $icl_dashboard_widget_id, sprintf( __( 'Multi-language | WPML %s', 'sitepress' ), ICL_SITEPRESS_VERSION ), array( $this, 'dashboard_widget' ), null );
			if ( $install ) {
				//FIXME: reported one case of NOTICE: wp-content/plugins/sitepress-multilingual-cms/sitepress.class.php:7815 - Undefined index: side
				$dashboard_widgets_order[ 'side' ] = $icl_dashboard_widget_id . ',' . @strval( $dashboard_widgets_order[ 'side' ] );
				$user                              = wp_get_current_user();
				update_user_option( $user->ID, 'meta-box-order_dashboard', $dashboard_widgets_order, true );
			}
		}
	}

	function dashboard_widget()
	{
		do_action( 'icl_dashboard_widget_notices' );
		include_once ICL_PLUGIN_PATH . '/menu/dashboard-widget.php';
	}

	function verify_post_translations( $post_type ) {
		global $wpdb;

		$active_languages = count( $this->get_active_languages() );

		$sql          = "
		            SELECT p1.ID, t.translation_id
		            FROM {$wpdb->prefix}icl_translations t
		            INNER JOIN {$wpdb->posts} p1
		            	ON t.element_id = p1.ID
		            LEFT JOIN {$wpdb->prefix}icl_translations tt
		            	ON t.trid = tt.trid
					WHERE t.element_type = %s
						AND t.source_language_code IS null
					GROUP BY p1.ID, p1.post_parent
					HAVING count(tt.language_code) < %d
		        ";
		$sql_prepared = $wpdb->prepare( $sql, array( 'post_' . $post_type, $active_languages ) );

		$results = $wpdb->get_results( $sql_prepared );

		if ( $results ) {
			foreach ( $results as $result ) {
				$id             = $result->ID;
				$translation_id = $result->translation_id;
				if ( !$translation_id ) {
					$this->set_element_language_details( $id, 'post_' . $post_type, false, $this->get_default_language() );
				}
			}
		} else {
			$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='{$post_type}' AND post_status <> 'auto-draft'" );
			if ( !empty( $post_ids ) ) {
				foreach ( $post_ids as $id ) {
					$translation_id_prepared = $wpdb->prepare( "SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", array( $id, 'post_' . $post_type ) );
					$translation_id          = $wpdb->get_var( $translation_id_prepared );
					if ( !$translation_id ) {
						$this->set_element_language_details( $id, 'post_' . $post_type, false, $this->get_default_language() );
					}
				}
			}
		}
	}

	function verify_taxonomy_translations( $taxonomy )
	{
		global $wpdb;
		$element_ids_prepared = $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s", $taxonomy);
		$element_ids = $wpdb->get_col( $element_ids_prepared );
		if ( !empty( $element_ids ) ) {
			foreach ( $element_ids as $id ) {
				$translation_id_prepared = $wpdb->prepare( "SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $id, 'tax_' . $taxonomy);
				$translation_id = $wpdb->get_var( $translation_id_prepared );
				if ( !$translation_id ) {
					$this->set_element_language_details( $id, 'tax_' . $taxonomy, false, $this->get_default_language() );
				}
			}
		}
	}

	function copy_from_original()
	{
		global $wpdb;
		$show = false;
		$trid = false;
		$source_lang = false;
		$source_lang_name = false;

		$disabled = '';
		if ( isset( $_GET[ 'source_lang' ] ) && isset( $_GET[ 'trid' ] ) ) {
			$source_lang      = $_GET[ 'source_lang' ];
			$trid             = intval( $_GET[ 'trid' ] );
			$_lang_details    = $this->get_language_details( $source_lang );
			$source_lang_name = $_lang_details[ 'display_name' ];
			$show             = true;
		} elseif ( isset( $_GET[ 'post' ] ) && isset( $_GET[ 'lang' ] ) && $_GET[ 'lang' ] != $this->get_default_language() ) {
			global $post;

			if ( trim( $post->post_content ) ) {
				$disabled = ' disabled="disabled"';
			}

			$trid             = $this->get_element_trid( $post->ID, 'post_' . $post->post_type );
			$source_lang      = $wpdb->get_var( $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE source_language_code IS NULL AND trid=%d", $trid ) );
			$_lang_details    = $this->get_language_details( $source_lang );
			$source_lang_name = $_lang_details[ 'display_name' ];

			$show = true && $source_lang;
		}

		if ( $show ) {
			wp_nonce_field( 'copy_from_original_nonce', '_icl_nonce_cfo_' . $trid );
			echo '<input id="icl_cfo" class="button-secondary" type="button" value="' . sprintf( __( 'Copy content from %s', 'sitepress' ), $source_lang_name ) . '"
                onclick="icl_copy_from_original(\'' . esc_js( $source_lang ) . '\', \'' . esc_js( $trid ) . '\')"' . $disabled . '/>';
			icl_pop_info( __( "This operation copies the content from the original language onto this translation. It's meant for when you want to start with the original content, but keep translating in this language. This button is only enabled when there's no content in the editor.", 'sitepress' ), 'question' );
			echo '<br clear="all" />';
		}

	}

	function wp_upgrade_locale( $locale )
	{
		if ( defined( 'WPLANG' ) && WPLANG ) {
			$locale = WPLANG;
		} else {
			$locale = ICL_WP_UPDATE_LOCALE;
		}

		return $locale;
	}

	function admin_language_switcher_legacy()
	{
		global $pagenow, $wpdb;

		$all_languages_enabled = true;
		$current_page          = basename( $_SERVER[ 'SCRIPT_NAME' ] );
		$current_language      = $this->get_current_language();

		// individual translations
		$is_post      = false;
		$is_tax       = false;
		$is_menu      = false;
		$post_type    = false;
		$trid         = false;
		$translations = false;

		switch ( $pagenow ) {
			case 'post.php':
				$is_post           = true;
				$all_languages_enabled = false;
				$post_id           = @intval( $_GET[ 'post' ] );
				$post              = get_post( $post_id );

				$trid         = $this->get_element_trid( $post_id, 'post_' . $post->post_type );
				$translations = $this->get_element_translations( $trid, 'post_' . $post->post_type, true );

				break;
			case 'post-new.php':
				$all_languages_enabled = false;
				if ( isset( $_GET[ 'trid' ] ) ) {
					$trid         = intval( $_GET[ 'trid' ] );
					$post_type    = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : 'post';
					$translations = $this->get_element_translations( $trid, 'post_' . $post_type, true );
					$is_post      = true;
				}
				break;
			case 'edit-tags.php':
				$is_tax = true;
				if ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'edit' ) {
					$all_languages_enabled = false;
				}
				$term_id     = @intval( $_GET[ 'tag_ID' ] );
				$taxonomy    = $_GET[ 'taxonomy' ];
				$term_tax_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s AND term_id=%d", $taxonomy, $term_id ) );

				$trid         = $this->get_element_trid( $term_tax_id, 'tax_' . $taxonomy );
				$translations = $this->get_element_translations( $trid, 'tax_' . $taxonomy, true );

				break;
			case 'nav-menus.php':
				$is_menu = true;
				if ( isset( $_GET[ 'menu' ] ) && $_GET[ 'menu' ] ) {
					$menu_id      = $_GET[ 'menu' ];
					$trid         = $trid = $this->get_element_trid( $menu_id, 'tax_nav_menu' );
					$translations = $this->get_element_translations( $trid, 'tax_nav_menu', true );
				}
				$all_languages_enabled = false;
				break;

		}

		foreach ( $this->get_active_languages() as $lang ) {

			$current_page_lang = $current_page;

			parse_str( $_SERVER[ 'QUERY_STRING' ], $query_vars );
			unset( $query_vars[ 'lang' ], $query_vars[ 'admin_bar' ] );

			// individual translations
			if ( $is_post ) {
				if ( isset( $translations[ $lang[ 'code' ] ] ) && isset( $translations[ $lang[ 'code' ] ]->element_id ) ) {
					$query_vars[ 'post' ] = $translations[ $lang[ 'code' ] ]->element_id;
					unset( $query_vars[ 'source_lang' ] );
					$current_page_lang      = 'post.php';
					$query_vars[ 'action' ] = 'edit';
				} else {
					$current_page_lang = 'post-new.php';
					if ( isset( $post ) ) {
						$query_vars[ 'post_type' ]   = $post->post_type;
						$query_vars[ 'source_lang' ] = $current_language;
					} else {
						$query_vars[ 'post_type' ] = $post_type;
					}
					$query_vars[ 'trid' ] = $trid;
					unset( $query_vars[ 'post' ], $query_vars[ 'action' ] );
				}
			} elseif ( $is_tax ) {
				if ( isset( $translations[ $lang[ 'code' ] ] ) && isset( $translations[ $lang[ 'code' ] ]->element_id ) ) {
					$query_vars[ 'tag_ID' ] = $translations[ $lang[ 'code' ] ]->element_id;
				} else {
					$query_vars[ 'trid' ]        = $trid;
					$query_vars[ 'source_lang' ] = $current_language;
					unset( $query_vars[ 'tag_ID' ], $query_vars[ 'action' ] );
				}
			} elseif ( $is_menu ) {
				if ( !empty( $menu_id ) ) {
					if ( isset( $translations[ $lang[ 'code' ] ]->element_id ) ) {
						$query_vars[ 'menu' ] = $translations[ $lang[ 'code' ] ]->element_id;
					} else {
						$query_vars[ 'menu' ]   = 0;
						$query_vars[ 'trid' ]   = $trid;
						$query_vars[ 'action' ] = 'edit';
					}
				}
			}

			$query_string = http_build_query( $query_vars );

			$query = '?';
			if ( !empty( $query_string ) ) {
				$query .= $query_string . '&';
			}
			$query .= 'lang=' . $lang[ 'code' ]; // the default language need to specified explictly yoo in order to set the lang cookie


			$link_url = admin_url( $current_page_lang . $query );

			$flag = $this->get_flag( $lang[ 'code' ] );

			if ( $flag->from_template ) {
				$wp_upload_dir = wp_upload_dir();
				$flag_url      = $wp_upload_dir[ 'baseurl' ] . '/flags/' . $flag->flag;
			} else {
				$flag_url = ICL_PLUGIN_URL . '/res/flags/' . $flag->flag;
			}

			$languages_links[ $lang[ 'code' ] ] = array(
				'url'  => $link_url . '&admin_bar=1', 'current' => $lang[ 'code' ] == $current_language, 'anchor' => $lang[ 'display_name' ],
				'flag' => '<img class="admin_iclflag" src="' . $flag_url . '" alt="' . $lang[ 'code' ] . '" width="18" height="12" />'
			);

		}

		if ( $all_languages_enabled ) {
			$query = '?';
			if ( !empty( $query_string ) ) {
				$query .= $query_string . '&';
			}
			$query .= 'lang=all';
			$link_url = admin_url( basename( $_SERVER[ 'SCRIPT_NAME' ] ) . $query );

			$languages_links[ 'all' ] = array(
				'url'  => $link_url, 'current' => 'all' == $current_language, 'anchor' => __( 'All languages', 'sitepress' ),
				'flag' => '<img class="admin_iclflag" src="' . ICL_PLUGIN_URL . '/res/img/icon16.png" alt="all" width="16" height="16" />'
			);
		} else {
			// set the default language as current
			if ( 'all' == $current_language ) {
				$languages_links[ $this->get_default_language() ][ 'current' ] = true;
			}
		}

		include ICL_PLUGIN_PATH . '/menu/admin-language-switcher.php';
	}

	function admin_language_switcher()
	{
		if(!SitePress::check_settings_integrity()) return;

		/** @var $wp_admin_bar WP_Admin_Bar */
		global $wpdb, $wp_admin_bar, $pagenow;

		$all_languages_enabled = true;
		$current_page      = basename( $_SERVER[ 'SCRIPT_NAME' ] );
		$post_type         = false;
		$trid              = false;
		$translations      = false;
		$languages_links   = array();

		// individual translations
		$is_post = false;
		$is_tax  = false;
		$is_menu = false;

		$current_language = $this->get_current_language();

		switch ( $pagenow ) {
			case 'post.php':
				$is_post           = true;
				$post_id           = @intval( $_GET[ 'post' ] );
				$post              = get_post( $post_id );

				$post_language = $this->get_language_for_element( $post_id, 'post_' . get_post_type( $post_id ) );
				if ( $post_language && $post_language != $current_language ) {
					$this->switch_lang( $post_language );
					$current_language = $this->get_current_language();
				}

				$trid         = $this->get_element_trid( $post_id, 'post_' . $post->post_type );
				$translations = $this->get_element_translations( $trid, 'post_' . $post->post_type, true );

				break;
			case 'post-new.php':
				$all_languages_enabled = false;
				if ( isset( $_GET[ 'trid' ] ) ) {
					$trid         = intval( $_GET[ 'trid' ] );
					$post_type    = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : 'post';
					$translations = $this->get_element_translations( $trid, 'post_' . $post_type, true );
					$is_post      = true;
				}
				break;
			case 'edit-tags.php':
				$is_tax = true;
				if ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'edit' ) {
					$all_languages_enabled = false;
				}
				$term_id     = @intval( $_GET[ 'tag_ID' ] );
				$taxonomy    = $_GET[ 'taxonomy' ];
				$term_tax_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s AND term_id=%d", $taxonomy, $term_id ) );

				$trid         = $this->get_element_trid( $term_tax_id, 'tax_' . $taxonomy );
				$translations = $this->get_element_translations( $trid, 'tax_' . $taxonomy, true );

				break;
			case 'nav-menus.php':
				$is_menu = true;
				if ( isset( $_GET[ 'menu' ] ) && $_GET[ 'menu' ] ) {
					$menu_id      = $_GET[ 'menu' ];
					$trid         = $trid = $this->get_element_trid( $menu_id, 'tax_nav_menu' );
					$translations = $this->get_element_translations( $trid, 'tax_nav_menu', true );
				}
				$all_languages_enabled = false;
				break;

		}

		foreach ( $this->get_active_languages() as $lang ) {
			$current_page_lang = $current_page;

			parse_str( $_SERVER[ 'QUERY_STRING' ], $query_vars );
			unset( $query_vars[ 'lang' ], $query_vars[ 'admin_bar' ] );

			// individual translations
			if ( $is_post ) {
				if ( isset( $translations[ $lang[ 'code' ] ] ) && isset( $translations[ $lang[ 'code' ] ]->element_id ) ) {
					$query_vars[ 'post' ] = $translations[ $lang[ 'code' ] ]->element_id;
					unset( $query_vars[ 'source_lang' ] );
					$current_page_lang      = 'post.php';
					$query_vars[ 'action' ] = 'edit';
				} else {
					$current_page_lang = 'post-new.php';
					if ( isset( $post ) ) {
						$query_vars[ 'post_type' ]   = $post->post_type;
						$query_vars[ 'source_lang' ] = $current_language;
					} else {
						$query_vars[ 'post_type' ] = $post_type;
					}
					$query_vars[ 'trid' ] = $trid;
					unset( $query_vars[ 'post' ], $query_vars[ 'action' ] );
				}
			} elseif ( $is_tax ) {
				if ( isset( $translations[ $lang[ 'code' ] ] ) && isset( $translations[ $lang[ 'code' ] ]->element_id ) ) {
					$query_vars[ 'tag_ID' ] = $translations[ $lang[ 'code' ] ]->element_id;
				} else {
					$query_vars[ 'trid' ]        = $trid;
					$query_vars[ 'source_lang' ] = $current_language;
					unset( $query_vars[ 'tag_ID' ], $query_vars[ 'action' ] );
				}
			} elseif ( $is_menu ) {
				if ( !empty( $menu_id ) ) {
					if ( isset( $translations[ $lang[ 'code' ] ]->element_id ) ) {
						$query_vars[ 'menu' ] = $translations[ $lang[ 'code' ] ]->element_id;
					} else {
						$query_vars[ 'menu' ]   = 0;
						$query_vars[ 'trid' ]   = $trid;
						$query_vars[ 'action' ] = 'edit';
					}
				}
			}

			$query_string = http_build_query( $query_vars );

			$query = '?';
			if ( !empty( $query_string ) ) {
				$query .= $query_string . '&';
			}
			$query .= 'lang=' . $lang[ 'code' ]; // the default language need to specified explicitly yoo in order to set the lang cookie

			$link_url = admin_url( $current_page_lang . $query );

			$flag = $this->get_flag( $lang[ 'code' ] );

			if ( $flag->from_template ) {
				$wp_upload_dir = wp_upload_dir();
				$flag_url      = $wp_upload_dir[ 'baseurl' ] . '/flags/' . $flag->flag;
			} else {
				$flag_url = ICL_PLUGIN_URL . '/res/flags/' . $flag->flag;
			}

			$languages_links[ $lang[ 'code' ] ] = array(
				'url'     => $link_url . '&admin_bar=1',
				'current' => $lang[ 'code' ] == $current_language,
				'anchor'  => $lang[ 'display_name' ],
				'flag'    => '<img class="icl_als_iclflag" src="' . $flag_url . '" alt="' . $lang[ 'code' ] . '" width="18" height="12" />'
			);

		}

		if ( $all_languages_enabled ) {
			$query = '?';
			if ( !empty( $query_string ) ) {
				$query .= $query_string . '&';
			}
			$query .= 'lang=all';
			$link_url = admin_url( basename( $_SERVER[ 'SCRIPT_NAME' ] ) . $query );

			$languages_links[ 'all' ] = array(
				'url'  => $link_url, 'current' => 'all' == $current_language, 'anchor' => __( 'All languages', 'sitepress' ),
				'flag' => '<img class="icl_als_iclflag" src="' . ICL_PLUGIN_URL . '/res/img/icon16.png" alt="all" width="16" height="16" />'
			);
		} else {
			// set the default language as current
			if ( 'all' == $current_language ) {
				$languages_links[ $this->get_default_language() ][ 'current' ] = true;
			}
		}

		$parent = 'WPML_ALS';
		$lang   = $languages_links[ $current_language ];
		// Current language
		$wp_admin_bar->add_menu( array(
									  'parent' => false, 'id' => $parent,
									  'title'  => $lang[ 'flag' ] . '&nbsp;' . $lang[ 'anchor' ] . '&nbsp;&nbsp;<img title="' . __( 'help', 'sitepress' ) . '" id="wpml_als_help_link" src="' . ICL_PLUGIN_URL . '/res/img/question1.png" alt="' . __( 'help', 'sitepress' ) . '" width="16" height="16"/>',
									  'href'   => false, 'meta' => array(
				'title' => __( 'Showing content in:', 'sitepress' ) . ' ' . $lang[ 'anchor' ],
			)
								 ) );

		if ( $languages_links ) {
			foreach ( $languages_links as $code => $lang ) {
				if ( $code == $current_language )
					continue;
				$wp_admin_bar->add_menu( array(
											  'parent' => $parent, 'id' => $parent . '_' . $code, 'title' => $lang[ 'flag' ] . '&nbsp;' . $lang[ 'anchor' ], 'href' => $lang[ 'url' ], 'meta' => array(
						'title' => __( 'Show content in:', 'sitepress' ) . ' ' . $lang[ 'anchor' ],
					)
										 ) );
			}
		}

		add_action( 'all_admin_notices', array( $this, '_admin_language_switcher_help_popup' ) );
	}

	function _admin_language_switcher_help_popup()
	{
		echo '<div id="icl_als_help_popup" class="icl_cyan_box icl_pop_info">';
		echo '<img class="icl_pop_info_but_close" align="right" src="' . ICL_PLUGIN_URL . '/res/img/ico-close.png" width="12" height="12" alt="x" />';
		printf( __( 'This language selector determines which content to display. You can choose items in a specific language or in all languages. To change the language of the WordPress Admin interface, go to <a%s>your profile</a>.', 'sitepress' ), ' href="' . admin_url( 'profile.php' ) . '"' );
		echo '</div>';

	}

	function admin_notices( $message, $class = "updated" )
	{
		static $hook_added = 0;
		$this->_admin_notices[ ] = array( 'class' => $class, 'message' => $message );

		if ( !$hook_added )
			add_action( 'admin_notices', array( $this, '_admin_notices_hook' ) );

		$hook_added = 1;
	}

	function _admin_notices_hook()
	{
		if ( !empty( $this->_admin_notices ) )
			foreach ( $this->_admin_notices as $n ) {
				echo '<div class="' . $n[ 'class' ] . '">';
				echo '<p>' . $n[ 'message' ] . '</p>';
				echo '</div>';
			}
	}

	/**
	 * Adjust template (taxonomy-)$taxonomy-$term.php for translated term slugs and IDs
	 *
	 * @since 3.1
	 *
	 * @param string $template
	 *
	 * @return string The template filename if found.
	 */
	function slug_template($template){
		global $wp_query;
		$term = $wp_query->get_queried_object();


		//Taxonomies
		if(!isset($term) || !$term) return $template;
		$taxonomy   = $term->taxonomy;
		if(!isset($taxonomy) || !$taxonomy) return $template;

		$templates = array();

		$template_prefix = 'taxonomy-';
		$is_taxonomy = true;
		if(in_array($taxonomy, array('category','tag'))) {
			$template_prefix = '';
			$is_taxonomy = false;
		}

		remove_filter( 'get_term', array( $this, 'get_term_adjust_id' ), 1 );

		$current_language = $this->get_current_language();
		$default_language = $this->get_default_language();

		if (!$is_taxonomy || $this->is_translated_taxonomy( $taxonomy ) && $current_language != $default_language ) {
			$current_term = get_term_by( "id", $term->term_id, $taxonomy );
			if ( $current_term ) {
				$templates[ ] = "$template_prefix$taxonomy-{$current_language}-{$current_term->slug}.php";
				$templates[ ] = "$template_prefix$taxonomy-{$current_language}-{$term->term_id}.php";
				$templates[ ] = "$template_prefix$taxonomy-{$current_language}.php";
				$templates[ ] = "$template_prefix$taxonomy-{$current_term->slug}.php";
				$templates[ ] = "$template_prefix$taxonomy-{$term->term_id}.php";
			}
		}

		$original_term_id = icl_object_id( $term->term_id, $taxonomy, true, $default_language );
		$original_term = get_term_by( "id", $original_term_id, $taxonomy );
		if ( $original_term ) {
			$templates[ ] = "$template_prefix$taxonomy-{$current_language}-{$original_term->slug}.php";
			$templates[ ] = "$template_prefix$taxonomy-{$current_language}-{$original_term_id}.php";
			$templates[ ] = "$template_prefix$taxonomy-{$original_term->slug}.php";
			$templates[ ] = "$template_prefix$taxonomy-{$original_term_id}.php";
			$templates[ ] = "$template_prefix$taxonomy-{$current_language}.php";
			$templates[ ] = "$template_prefix$taxonomy.php";
		}

		if ( $is_taxonomy ) {
			$templates[ ] = 'taxonomy-{$current_language}.php';
			$templates[ ] = 'taxonomy.php';
		}

		$templates = array_unique($templates);

		add_filter( 'get_term', array( $this, 'get_term_adjust_id' ), 1, 1 );

		$new_template = locate_template( $templates );
		if($new_template) {
			$template = $new_template;
		}

		return $template;
	}

	function setup_canonical_urls()
	{
		global $wp_the_query;

		// Yoast Exception
		global $wpseo_front;
		if ( isset( $wpseo_front ) && has_filter( 'wp_head', array( $wpseo_front, 'head' ) ) )
			return;

		if ( is_singular() ) {
			$id             = $wp_the_query->get_queried_object_id();
			$master_post_id = get_post_meta( $id, '_icl_lang_duplicate_of', true );
			if ( $id && $master_post_id != $id ) {
				remove_action( 'wp_head', 'rel_canonical' );
				add_action( 'wp_head', array( $this, 'rel_canonical' ) );
			}
		}
	}

	function rel_canonical()
	{
		global $wp_the_query;
		$id = $wp_the_query->get_queried_object_id();
		if ( $master_post_id = get_post_meta( $id, '_icl_lang_duplicate_of', true ) ) {
			$link = get_permalink( $master_post_id );
			echo "<link rel='canonical' href='$link' />\n";
		}
	}

	function head_langs()
	{
		$languages = $this->get_ls_languages( array( 'skip_missing' => true ) );
		// If there are translations and is not paged content...

		//Renders head alternate links only on certain conditions
		$the_post = get_post();
		$the_id   = $the_post ? $the_post->ID : false;
		$is_valid = $the_id && count( $languages ) > 1 && !is_paged() && ( ( ( is_single() || is_page() ) && get_post_status( $the_id ) == 'publish' ) || ( is_home() || is_front_page() || is_archive() ) );

		if ( $is_valid ) {
			foreach ( $languages as $code => $lang ) {
				printf( '<link rel="alternate" hreflang="%s" href="%s" />' . PHP_EOL, $this->get_language_tag( $code ), str_replace( '&amp;', '&', $lang[ 'url' ] ) );
			}
		}
	}

	function allowed_redirect_hosts( $hosts )
	{
		if ( $this->settings[ 'language_negotiation_type' ] == 2 ) {
			foreach ( $this->settings[ 'language_domains' ] as $code => $url ) {
				if ( !empty( $this->active_languages[ $code ] ) ) {
					$parts = parse_url( $url );
					if ( !in_array( $parts[ 'host' ], $hosts ) ) {
						$hosts[ ] = $parts[ 'host' ];
					}
				}
			}
		}

		return $hosts;
	}

	function icl_nonces()
	{
		//@since 3.1	Calls made only when in Translation Management pages
		$allowed_pages = array();
		if(defined('WPML_TM_FOLDER')) {
			$allowed_pages[] = WPML_TM_FOLDER . '/menu/main.php';
		}
		if(!isset($_REQUEST['page']) || !in_array($_REQUEST['page'], $allowed_pages)) {
			return;
		}
		//messages
		wp_nonce_field( 'icl_messages_nonce', '_icl_nonce_m' );
		wp_nonce_field( 'icl_show_reminders_nonce', '_icl_nonce_sr' );
	}

	//For when it will be possible to add custom bulk actions
	function bulk_actions($actions) {
		$active_languages = $this->get_active_languages();

		$actions['duplicate_all'] = 'duplicate_all';
		foreach($active_languages as $language_code => $language_name) {
			$actions['duplicate_' . $language_code] = 'duplicate_' . $language_code;
		}

		return $actions;
	}

	/**
	 * Returns SERVER_NAME, or HTTP_HOST if the first is not available
	 * @return mixed
	 */
	private function get_server_host_name() {
		if(!isset($_SERVER[ 'HTTP_HOST' ])) {
			$host =  $_SERVER[ 'SERVER_NAME' ];
			if(isset( $_SERVER[ 'SERVER_PORT' ] ) && $_SERVER[ 'SERVER_PORT' ]!=80) {
				$host .= ':' . $_SERVER[ 'SERVER_PORT' ];
			}
		} else {
			$host =  $_SERVER[ 'HTTP_HOST' ];
		}

		//Removes standard ports 443 (80 should be already omitted in all cases)
		$result = preg_replace( "@:[443]+([/]?)@", '$1', $host );

		return $result;
	}

	public static function get_installed_plugins() {
		if(!function_exists('get_plugins')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}
		$wp_plugins        = get_plugins();
		$wpml_plugins_list = array(
			'WPML Multilingual CMS'       => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'sitepress-multilingual-cms' ),
			'WPML CMS Nav'                => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'wpml-cms-nav' ),
			'WPML String Translation'     => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'wpml-string-translation' ),
			'WPML Sticky Links'           => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'wpml-sticky-links' ),
			'WPML Translation Management' => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'wpml-translation-management' ),
			'WPML Translation Analytics'  => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'wpml-translation-analytics' ),
			'WPML XLIFF'                  => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'wpml-xliff' ),
			'WPML Media'                  => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'wpml-media' ),
			'WooCommerce Multilingual'    => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'woocommerce-multilingual' ),
			'JigoShop Multilingual'       => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'jigoshop-multilingual' ),
			'Gravity Forms Multilingual'  => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'gravityforms-multilingual' ),
			'CRED Frontend Translation'   => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'cred-frontend-translation' ),
			'Installer'                   => array( 'installed' => false, 'active' => false, 'file' => false, 'plugin' => false, 'slug' => 'installer' ),
		);

		foreach ( $wpml_plugins_list as $wpml_plugin_name => $v ) {
			foreach ( $wp_plugins as $file => $plugin ) {
				$plugin_name = $plugin[ 'Name' ];
				if ( $plugin_name == $wpml_plugin_name ) {
					$wpml_plugins_list[ $plugin_name ][ 'installed' ] = true;
					$wpml_plugins_list[ $plugin_name ][ 'plugin' ]    = $plugin;
					$wpml_plugins_list[ $plugin_name ][ 'file' ]      = $file;
				}
			}
		}

		return $wpml_plugins_list;
	}

	public static function check_settings_integrity() {
		if(wpml_is_ajax()) return true;

		if ( isset( $_GET[ 'debug_action' ]) && $_GET[ 'nonce' ] == wp_create_nonce( $_GET[ 'debug_action' ] ) ) {
			if($_GET[ 'debug_action' ] == 'reset_wpml_settings') {
				$referrer = isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : get_admin_url();

				$current_settings = get_option( 'icl_sitepress_settings' );

				unset($current_settings['setup_complete']);
				unset($current_settings['setup_wizard_step']);
				unset($current_settings['existing_content_language_verified']);
				unset($current_settings['dont_show_help_admin_notice']);

				global $wpdb;
				$wpdb->query('TRUNCATE TABLE ' . $wpdb->prefix . 'icl_translations');

				update_option('icl_sitepress_settings', $current_settings);

				wp_redirect($referrer);
				exit();
			}
		}

		global $wpdb;

		static $result;

		if(isset($result)) {
			return $result;
		}

		$current_settings = get_option( 'icl_sitepress_settings' );

		if(!$current_settings) return true;

		$setup_wizard_step = false;
		if ( isset( $current_settings[ 'setup_wizard_step' ] ) ) {
			$setup_wizard_step = $current_settings[ 'setup_wizard_step' ];
		}

		$setup_complete         = false;
		$setup_complete_missing = true;
		if ( isset( $current_settings[ 'setup_complete' ] ) ) {
			$setup_complete         = $current_settings[ 'setup_complete' ];
			$setup_complete_missing = false;
		}

		//Skip checks during first setup wizard
		if(($setup_wizard_step!==false && $setup_wizard_step < 3) || (!$setup_complete_missing && $setup_complete===false && $setup_wizard_step==3 )) return true;

		$default_language         = false;
		$default_language_missing = true;
		if ( isset( $current_settings[ 'default_language' ] ) ) {
			$default_language         = $current_settings[ 'default_language' ];
			$default_language_missing = false;
		}

		$active_languages_sql      = "SELECT * FROM " . $wpdb->prefix . 'icl_languages WHERE active=%d';
		$active_languages_prepared = $wpdb->prepare( $active_languages_sql, array(1) );
		$active_languages         = $wpdb->get_results( $active_languages_prepared );

		$existing_translations_sql      = "SELECT count(*) FROM " . $wpdb->prefix . 'icl_translations';
		$existing_translations          = $wpdb->get_var( $existing_translations_sql );

		$show_notice = false;
		$message     = '';
		if ( (!$setup_complete || !$default_language) && $existing_translations ) {
			$message .= '<p>';
			$message .= __( 'Your WPML settings seem to be corrupted. To avoid corrupting your existing data, we have hidden WPML from this site.', 'sitepress' );
			$message .= '</p>';
			$message .= '<p>';
			$message .= __( 'If this is the first time you install WPML on this site, you may have faced a database or script connection drop, that caused settings to be not completely store.', 'sitepress' );
			$message .= __( 'In this case, you can click on the <strong>Reset Settings</strong> button: this will reset WPML settings and any language translation information, allowing you to restart the wizard.', 'sitepress' );
			$message .= '</p>';
			$message .= '<p>';
			$message .= sprintf( __( 'If you have just upgraded WPML or after starting over you keep getting this message, please contact the <a href="%s">support forum</a> as soon as possible, in order to provide you with a fix to this issue.', 'sitepress' ), 'https://wpml.org/forums/' );
			$message .= '</p>';
			$message .= '<p>';

			$confirm_message = _x('Are you sure you want to reset WPML Settings?', 'Reset WPML settings', 'sitepress');
			$confirm_message .= ' ';
			$confirm_message .= _x('This will also empty translation information (if any).', 'Reset WPML settings', 'sitepress');

			$message .= '<a href="?icl_reset_settings=1&debug_action=reset_wpml_settings&nonce=' . wp_create_nonce( 'reset_wpml_settings' ) . '" class="button" onclick="return window.confirm(\'' . $confirm_message  . '\');" >' . __('Reset Settings','sitepress') . '</a>';
			$message .= '&nbsp;';
			$message .= '&nbsp;';
			$message .= '&nbsp;';
			$message .= '<a href="https://wpml.org/forums/" class="button">' . __('Contact Support','sitepress') . '</a>';
			$message .= '</p>';
			$message .= '<p>';
			$message .= __( 'Additional details for the support team (there is no need to copy it, as the support team will be able to see it once logged in):', 'sitepress' );
			$message .= '</p>';
			$message .= '<p><textarea rows="10" style="width:100%;display:block;" onclick="this.focus();this.select();" readonly="readonly">';
			$message .= str_repeat( '=', 50 );

			$wpml_plugins_list = SitePress::get_installed_plugins();
			foreach ( $wpml_plugins_list as $name => $plugin_data ) {
				$plugin_name = $name;
				$file        = $plugin_data['file'];

				$message .= PHP_EOL . $plugin_name;
				$message .= ' ' . (isset( $plugin_data['plugin']['Version'] ) ? $plugin_data['plugin']['Version'] : __( 'Version n/a', 'sitepress' ));
				$message .= ' => ';

				if ( empty( $plugin_data['plugin'] ) ) {
					$message .= 'Not installed';
				} else {
					$message .= 'Installed';
				}
				$message .= '/';
				$message .= isset( $file ) && is_plugin_active( $file ) ? 'Active' : 'Not Active';
			}
			$message .= PHP_EOL . str_repeat( '-', 50 );

			$message .= PHP_EOL . 'icl_translations count: ' . ( $existing_translations ? $existing_translations : '0' );
			$message .= PHP_EOL . 'setup_complete: ' . ( $setup_complete ? 'true' : 'false' );
			$message .= PHP_EOL . 'setup_complete missing: ' . ( $setup_complete_missing ? 'true' : 'false' );
			$message .= PHP_EOL . 'default_language: ' . ( $default_language ? $default_language : '""' );
			$message .= PHP_EOL . 'default_language_missing: ' . ( $default_language_missing ? 'true' : 'false' );
			$message .= PHP_EOL . PHP_EOL . 'active_languages: ' . PHP_EOL . print_r( $active_languages, true );
			$message .= PHP_EOL . PHP_EOL . 'icl_sitepress_settings (serialized): ' . PHP_EOL . serialize( $current_settings );
			$message .= PHP_EOL . PHP_EOL . 'icl_sitepress_settings (unserialized): ' . PHP_EOL . print_r( $current_settings, true );

			$message .= PHP_EOL . str_repeat( '=', 50 );

			$message .= '</textarea></p>';
			$show_notice = true;
		}

		//		ICL_AdminNotifier::removeMessage( 'check_settings_integrity' );
		ICL_AdminNotifier::removeMessage( 'check_settings_integrity_corrupted' );
		if ( $show_notice ) {
			ICL_AdminNotifier::addMessage( 'check_settings_integrity_corrupted', $message, 'error', false, false, false, 'check_settings_integrity', true );
			ICL_AdminNotifier::displayMessages( 'check_settings_integrity' );
		}

		$result = !$show_notice;
		return $result;
	}

	/**
	 * @param int  $limit
	 * @param bool $provide_object
	 * @param bool $ignore_args
	 *
	 * @return array
	 */
	public function get_backtrace($limit = 0, $provide_object = false, $ignore_args = true) {
		$options = false;

		if ( version_compare( phpversion(), '5.3.6' ) < 0 ) {
			// Before 5.3.6, the only values recognized are TRUE or FALSE,
			// which are the same as setting or not setting the DEBUG_BACKTRACE_PROVIDE_OBJECT option respectively.
			$options = $provide_object;
		} else {
			// As of 5.3.6, 'options' parameter is a bitmask for the following options:
			if ( $provide_object )
				$options |= DEBUG_BACKTRACE_PROVIDE_OBJECT;
			if ( $ignore_args )
				$options |= DEBUG_BACKTRACE_IGNORE_ARGS;
		}
		if ( version_compare( phpversion(), '5.4.0' ) >= 0 ) {
			$actual_limit = $limit == 0 ? 0 : $limit + 1;
			$debug_backtrace = debug_backtrace($options , $actual_limit ); //add one item to include the current frame
		} else {
			$debug_backtrace = debug_backtrace( $options );
		}
		//Remove the current frame
		if($debug_backtrace) {
			array_shift($debug_backtrace);
		}
		return $debug_backtrace;
	}

	/**
	 * Translate the value returned by 'option_{taxonomy}_children' and store it in cache
	 *
	 * @param array       $original_value
	 * @param bool|string $current_language
	 * @param bool|string $taxonomy
	 *
	 * @return array
	 */
	function option_taxonomy_children( $original_value, $current_language = false, $taxonomy = false ) {
		if(!is_array($original_value) || count($original_value)==0) return $original_value;

		$current_language = !$current_language ? $this->get_current_language() : $current_language;
		$default_language = $this->get_default_language();

		if ( $current_language == $default_language ) {
			return $original_value;
		}

		$cache_key_array[ ] = $current_language;
		$cache_key_array[ ] = $default_language;
		$cache_key_array[ ] = $original_value;
		$cache_key          = md5( serialize( $cache_key_array ) );
		$cache_group        = 'translate_taxonomy_children';
		$cache_found        = false;

		$result = wp_cache_get( $cache_key, $cache_group, false, $cache_found );

		if ( $cache_found ) {
			return $result;
		}

		$debug_backtrace = $this->get_backtrace( 4, false, false );

		//Find the taxonomy name
		if ( !$taxonomy && isset( $debug_backtrace[ 3 ] ) && isset( $debug_backtrace[ 3 ][ 'args' ] ) ) {
			$option_name = $debug_backtrace[ 3 ][ 'args' ][ 0 ];
			$taxonomies = explode( '_', $option_name );
			$taxonomy    = $taxonomies[ 0 ];
		}
		$translated_children = array();
		if ( $taxonomy && is_array( $original_value ) ) {
			foreach ( $original_value as $children_term_ids ) {
				foreach ( $children_term_ids as $child_term_id ) {
					$translated_child_term_id = icl_object_id( $child_term_id, $taxonomy, false, $current_language );
					if ( $translated_child_term_id ) {
						$translated_parent_term_id = wp_get_term_taxonomy_parent_id( $translated_child_term_id, $taxonomy );
						if ( $translated_parent_term_id ) {
							if ( ! isset( $translated_children[ $translated_parent_term_id ] ) ) {
								$translated_children[ $translated_parent_term_id ] = array();
							}
							$translated_children[ $translated_parent_term_id ][ ] = $translated_child_term_id;
						}
					}
				}
			}
		}

		wp_cache_set( $cache_key, $translated_children, $cache_group );

		return $translated_children;
	}


	function pre_update_option_taxonomy_children($value, $old_value) {
		$current_language = $this->get_current_language();
		$default_language = $this->get_default_language();

		if ( $current_language == $default_language ) {
			return $value;
		}

		$cache_key_array[ ] = $current_language;
		$cache_key_array[ ] = $default_language;
		$cache_key_array[ ] = $value;
		$cache_key_array[ ] = $old_value;
		$cache_key          = md5( serialize( $cache_key_array ) );
		$cache_group        = 'pre_update_option_taxonomy_children';
		$cache_found        = false;

		$result = wp_cache_get( $cache_key, $cache_group, false, $cache_found );

		if ( $cache_found ) {
			return $result;
		}

		$debug_backtrace = $this->get_backtrace( 4, false, false );

		$taxonomy = false;
		//Find the taxonomy name
		if ( isset( $debug_backtrace[ 3 ] ) && isset( $debug_backtrace[ 3 ][ 'args' ] ) ) {
			$option_name = $debug_backtrace[ 3 ][ 'args' ][ 0 ];
			$taxonomies = explode( '_', $option_name );
			$taxonomy    = $taxonomies[ 0 ];
		}

		if($taxonomy) {

			remove_filter("option_{$taxonomy}_children", array($this, 'option_taxonomy_children'), 10 );
			remove_filter("pre_update_option_{$taxonomy}_children", array($this, 'pre_update_option_taxonomy_children'), 10 );

			$new_value = get_option("{$taxonomy}_children");

			add_filter("option_{$taxonomy}_children", array($this, 'option_taxonomy_children'), 10 );
			add_filter("pre_update_option_{$taxonomy}_children", array($this, 'pre_update_option_taxonomy_children'), 10, 2 );

			return $new_value;
		}

		return $value;
	}

	/**
	 * @param int|array $terms_ids
	 * @param $taxonomy
	 */
	public function update_terms_relationship_cache( $terms_ids, $taxonomy ) {

		remove_action( 'get_term', array( $this, 'get_term_filter' ), 1 );
		remove_filter( 'get_terms_args', array( $this, 'get_terms_args_filter' ) );
		remove_filter( 'terms_clauses', array( $this, 'terms_clauses' ), 10 );
		remove_filter( 'list_terms_exclusions', array( $this, 'exclude_other_terms' ), 1 );

		clean_term_cache( $terms_ids, $taxonomy );

		add_action( 'get_term', array( $this, 'get_term_filter' ), 1, 2 );
		add_filter( 'get_terms_args', array( $this, 'get_terms_args_filter' ) );
		// filters terms by language
		add_filter( 'terms_clauses', array( $this, 'terms_clauses' ), 10, 4 );
		add_filter( 'list_terms_exclusions', array( $this, 'exclude_other_terms' ), 1, 2 );

	}
}