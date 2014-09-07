<?php
/**
 * WP SEO by Yoast sitemap filter class
 * 
 * - Languages per domain
 * -- Filters home_url
 * -- Adds post types and taxonomies in different languages to index sitemap
 * --- Filters JOIN, WHERE to limit posts only to sitemap language (if any)
 *      otherwise will show posts for current language domain
 * --- Filters sitemap XSL (removed)
 * --- Fixes WP SEO post type if sitemap name contains language info
 * --- Fixes modification date for posts
 * -- Handles sitemap language (post-en-sitemap.xml {post_type}-{lang}-sitemap.xml)
 *      (removed because it's not needed with current logic)
 * 
 * - Languages per directory (and GET var)
 * -- Filters sitemap entries for home pages (to show language root instead of pagename)
 * 
 * 
 * Changelog
 * 
 * 1.0.1
 * - Added filtering home_url
 * - Added summary to WP SEO admin screen
 * - Added filtering/fixing home URLs filter_home_pages()
 *      (negotiation directory and query var)
 * - Added filtering/fixing list entries filter_entry()
 * - Removed/fixed outdated code in set_stylesheet() and add_to_index()
 * - Fixed get_sitemap_language()
 * - Removed filtering XSL stylesheet (URL is adjusted)
 * - Removed add_to_index (not needed with current logic)
 * 
 * @version 1.0.1
 */
class WPSEO_XML_Sitemaps_Filter {

    protected $_active_languages, $_is_per_domain, $_default_home_url, $_current_home_url;

	public function __construct() {

        global $sitepress, $sitepress_settings;

        $this->_is_per_domain = isset( $sitepress_settings['language_negotiation_type'] ) && $sitepress_settings['language_negotiation_type'] == 2;
        $this->_active_languages = $sitepress->get_active_languages();

        // Add summary on WP SEO admin screen
        if ( $this->_is_per_domain ){
            add_action( 'wpseo_xmlsitemaps_config', array($this, 'summary') );
        }

        // Triggered when sitemap or XSL is requested
        if ( !empty( $_REQUEST['sitemap'] )
					|| basename( $_SERVER['REQUEST_URI'] ) == 'sitemap_index.xml'
					|| strpos( basename( $_SERVER['REQUEST_URI'] ), '-sitemap.xml' ) !== false
					|| basename( $_SERVER['REQUEST_URI'] ) == 'main-sitemap.xsl' ) {
            if ( $this->_is_per_domain
                    && $sitepress->get_default_language() != $sitepress->get_current_language()){
                add_action( 'init', array($this, 'init') );
//                add_filter( 'wpseo_sitemap_index', array($this, 'add_to_index') );
                add_action( 'wpseo_xmlsitemaps_config', array($this, 'summary') );
                $this->_default_home_url = home_url();
                $this->_current_home_url = $sitepress_settings['language_domains'][$sitepress->get_current_language()];
                add_filter( 'home_url', array( $this, 'home_url' ), 1, 4 );
                add_filter( 'post_type_archive_link', array( $this, 'post_type_archive_link' ), 10, 2 );
            }
            if ( !$this->_is_per_domain && get_option( 'page_on_front' ) ) {
                add_filter( 'wpseo_sitemap_entry',
                        array($this, 'filter_home_pages'), 10, 3 );
            }
        }
    }

    /**
     * Init if languages per domain.
     */
	public function init() {
		add_filter( 'wpseo_typecount_join', array( $this, 'typecount_join' ), 10, 2 );
		add_filter( 'wpseo_typecount_where', array( $this, 'typecount_where' ), 10, 2 );
		add_filter( 'wpseo_posts_join', array( $this, 'posts_join' ), 10, 2 );
		add_filter( 'wpseo_posts_where', array( $this, 'posts_where' ), 10, 2 );
//		add_filter( 'wpseo_stylesheet_url', array( $this, 'set_stylesheet' ) );
//		add_filter( 'wpseo_build_sitemap_post_type', array( $this, 'get_post_type' ) );
	}

    /**
     * Adds sitemap info on WP SEO admin screen.
     * 
     * @global type $sitepress
     */
    public function summary() {
        global $sitepress;
        echo '<h2>WPML</h2>';
        echo __('Sitemaps for each languages can be accessed here:', 'sitepress') . '<ul>';
        foreach ($sitepress->get_ls_languages() as $lang) {
            $url = $lang['url'] . 'sitemap_index.xml';
            echo '<li>' . $lang['translated_name'] . ' <a href="' . $url
                    . '" target="_blank">' . $url . '</a></li>';
        }
        echo '</ul>';
    }

    /**
     * Adds active languages sitemap links to sitemap_index.xml
     * @param type $str
     * 
     * @TODO Remove - not needed since 1.0.1
     */
	function add_to_index( $str ) {
		global $sitepress, $sitepress_settings, $wpdb;
        // Deprecated call
		$options = version_compare( WPSEO_VERSION, '1.5.0', '>=' ) ? WPSEO_Options::get_all() : get_wpseo_options();
		$default_language = $sitepress->get_default_language();
		$current_language = $sitepress->get_current_language();

		foreach($sitepress->get_active_languages() as $lang_code => $array){
			if(isset($sitepress_settings['language_domains'][$lang_code])){
				$home_url = $sitepress_settings['language_domains'][$lang_code];
			} else {
				$home_url = home_url();
			}
			
			foreach (get_post_types(array('public' => true)) as $post_type) {
				$sitepress->switch_lang($lang_code);
				$count = get_posts(array('post_type' => $post_type, 'post_status' => 'publish', 'suppress_filters' => 0));
				$sitepress->switch_lang($current_language);

				if(count($count) > 0 && $sitepress->is_translated_post_type($post_type)){
					if (empty($options['post_types-'.$post_type.'-not_in_sitemap']) && $lang_code !== $default_language){
						$filename = $post_type .'-'.  $lang_code .'-sitemap.xml';
						$date = $this->get_last_mod_date($post_type, $lang_code);
						$str .= '<sitemap>' . "\n";
						$str .= '<loc>' . $home_url . '/' . $filename . '</loc>' . "\n";
						$str .= '<lastmod>' . $date . '</lastmod>' . "\n";
						$str .= '</sitemap>' . "\n";
					}
				}
			}
			
			foreach ( get_taxonomies( array('public' => true) ) as $tax ) {				
				$sitepress->switch_lang($lang_code);
				$count = get_terms($tax, array('suppress_filters' => 0));
				$sitepress->switch_lang($current_language);
				
				if ( count($count) > 0 && $sitepress->is_translated_taxonomy($tax)){
					if (empty($options['taxonomies-'.$tax.'-not_in_sitemap']) && $lang_code !== $default_language){
						$filename = $tax .'-'. $lang_code .'-sitemap.xml';
						$date = $this->get_last_mod_date('post', $lang_code);
						$str .= '<sitemap>' . "\n";
						$str .= '<loc>' . $home_url . '/' . $filename . '</loc>' . "\n";
						$str .= '<lastmod>' . $date . '</lastmod>' . "\n";
						$str .= '</sitemap>' . "\n";
					}
				}
			}
		}
		
		return $str;
    }
	
    /**
     * Filters WPSEO typecount SQL query
     */
    function typecount_join($join, $post_type){
    	global $wpdb, $sitepress;

        if($sitepress->is_translated_post_type($post_type)){
    	    $join .= " INNER JOIN {$wpdb->prefix}icl_translations 
    	              ON $wpdb->posts.ID = {$wpdb->prefix}icl_translations.element_id";
        }
    	
    	return $join;
    }
    
    function typecount_where($where, $post_type){
    	global $wpdb, $sitepress;
    	$sitemap_language = $this->get_sitemap_language();
    	
        if($sitepress->is_translated_post_type($post_type)){
    	    $where .= " AND {$wpdb->prefix}icl_translations.language_code = '{$sitemap_language}'
                        AND {$wpdb->prefix}icl_translations.element_type = 'post_{$post_type}'";
        }
    	
    	return $where;
    }
    
    /**
     * Filters WPSEO posts query
     */
	function posts_join($join, $post_type){
    	global $wpdb, $sitepress;

        if($sitepress->is_translated_post_type($post_type)){
    	    $join .= " INNER JOIN {$wpdb->prefix}icl_translations 
    	               ON $wpdb->posts.ID = {$wpdb->prefix}icl_translations.element_id";
        }
    	
    	return $join;
	}
	
    function posts_where($where, $post_type){
    	global $wpdb, $sitepress;
        
        if($sitepress->is_translated_post_type($post_type)){
    	    $sitemap_language = $this->get_sitemap_language();
    	    
    	    $where .= " AND {$wpdb->prefix}icl_translations.language_code = '{$sitemap_language}' 
                        AND {$wpdb->prefix}icl_translations.element_type = 'post_{$post_type}'";
        }
    	
    	return $where;
    }
	
    /**
     * Filters XML sitemap stylesheet
     * 
     * @TODO Remove - not needed since 1.0.1
     */
    function set_stylesheet( $stylesheet ){
        // Deprecated
        if ( version_compare( WPSEO_VERSION, '1.4.25', '<=' ) ) {
            global $sitepress_settings;
            
            if(@$sitepress_settings['language_domains'][$this->get_sitemap_language()]){
                $language_domain = $sitepress_settings['language_domains'][$this->get_sitemap_language()];
                $wpseo_dirname = str_replace('wp-seo.php', '', WPSEO_BASENAME);
                $wpseo_domain_path = $language_domain . '/wp-content/plugins/' . $wpseo_dirname;

                $this->stylesheet = '<?xml-stylesheet type="text/xsl" href="'.$wpseo_domain_path.'css/xml-sitemap.xsl"?>';
            } else {
                $this->stylesheet = '<?xml-stylesheet type="text/xsl" href="'
                        . plugins_url( 'css/xml-sitemap.xsl', WPSEO_BASENAME )
                        . '"?>';
            }
            return $this->stylesheet;
        } else {
            return '<?xml-stylesheet type="text/xsl" href="'
                    . preg_replace( '/(^http[s]?:)/', '',
                            esc_url( $this->_current_home_url ) )
                    . '/main-sitemap.xsl"?>';
        }
        return $stylesheet;
    }
	
	/**
	 * Get post type from sitemap name
     * 
     * @TODO Remove - not needed since 1.0.1
	 */
	function get_post_type($post_type){
		if($post_type !== '1'){ // sitemap_index.xml 
			$get_sitemap_name = basename($_SERVER['REQUEST_URI']);
			$post_type = explode("-", $get_sitemap_name);	
			$post_type = $post_type[0];
		}
		
		return $post_type;
	}
	
	/**
	 * Get sitemap language from sitemap name
     * 
     * @TODO Remove - not needed since 1.0.1
	 */
	function get_sitemap_language(){
		global $sitepress;
        
		$get_sitemap_name = basename($_SERVER['REQUEST_URI']);
		$sitemap_language = explode("-", $get_sitemap_name);

		if(isset($sitemap_language[1])){
			$sitemap_language = $sitemap_language[1];
		}
		
		foreach($sitepress->get_active_languages() as $language_code => $array){
			$active_languages[] = $language_code;
		}
		
		if(!in_array($sitemap_language, $active_languages)){
            // This should be current language, already determined by WPML
			$sitemap_language = ICL_LANGUAGE_CODE;//$sitepress->get_default_language();
		}
		
		return $sitemap_language;
	}

	/**
	 * Get last sitemap post type modified date by language
	 * @param type $post_type
	 * @param type $language_code
	 */
	function get_last_mod_date($post_type, $language_code){
		global $wpdb;
		
		$date = $wpdb->get_var( "SELECT post_modified_gmt, ID FROM $wpdb->posts 
		INNER JOIN {$wpdb->prefix}icl_translations
		ON $wpdb->posts.ID = {$wpdb->prefix}icl_translations.element_id
		WHERE $wpdb->posts.post_status = 'publish' 
		AND $wpdb->posts.post_type = '$post_type' 
		AND {$wpdb->prefix}icl_translations.language_code = '$language_code'
		ORDER BY post_modified_gmt DESC LIMIT 1 OFFSET 0");
		
		$date = strtotime($date);
		$date = date( 'c', $date );
		
		if(!isset($date)){
			$result = strtotime( get_lastpostmodified( 'gmt' ) );
			$date = date( 'c', $result );
		}
		
		return $date;
	}
    
    /**
     * Filters home page URLs.
     * 
     * If there is page_on_front, adjust URL to e.g. http://site.com/es
     * do not leave actual page name e.g. http://site.com/es/sample-page
     * 
     * @global type $sitepress
     * @param type $url
     * @param type $post_type
     * @param type $post
     * @return type
     */
    public function filter_home_pages( $url, $post_type, $post ) {
        // Basic check
        if ( empty( $post->ID ) || !isset( $post->post_type )
                || $post->post_type != 'page' ) {
            return $url;
        }
        // Collect info on home IDs
        static $home_pages;
        if ( is_null( $home_pages ) ) {
            foreach ( $this->_active_languages as $l ) {
                if ( $l['code'] == ICL_LANGUAGE_CODE ) continue;
                $home_pages[$l['code']] = icl_object_id( get_option( 'page_on_front' ),
                        'page', false, $l['code'] );
            }
        }
        // Is done?
        if ( empty( $home_pages ) ) {
            remove_filter( 'wpseo_sitemap_entry', array( $this, 'filter_home_pages' ), 10 );
            return $url;
        }
        // If post ID in home_pages
        global $sitepress;
        if ( $lang = array_search( $post->ID, $home_pages ) ) {
            $url['loc'] = $sitepress->language_url( $lang );
            unset( $home_pages[$lang] );
        }

        return $url;
    }

    /**
	 * Filters home URL.
	 *
	 * @param string $url
	 * @return bool|string
	 */
	public function home_url( $url ) {
        return str_replace( $this->_default_home_url, $this->_current_home_url, $url );
    }

    /**
     * Filters post type archive link if slugs translated.
     * 
     * @TODO WPML do not filter archive link if post type slugs are translated
     * 
     * @param type $link
     * @param type $post_type
     */
    public function post_type_archive_link( $link, $post_type ) {
        global $sitepress, $sitepress_settings, $wp_rewrite;
        $translate = !empty( $sitepress_settings['posts_slug_translation']['types'][$post_type] );
        if ( $translate && class_exists( 'WPML_Slug_Translation') ) {
            $post_type_obj = get_post_type_object($post_type);
            $translated_slug = WPML_Slug_Translation::get_translated_slug( $post_type,
                            $sitepress->get_current_language() );
            if ( get_option( 'permalink_structure' )
                    && is_array( $post_type_obj->rewrite ) ) {
                $struct = ( true === $post_type_obj->has_archive ) ? $translated_slug : $post_type_obj->has_archive;
                if ( $post_type_obj->rewrite['with_front'] ) {
                    $struct = $wp_rewrite->front . $struct;
                } else {
                    $struct = $wp_rewrite->root . $struct;
                }
                $link = home_url( user_trailingslashit( $struct,
                                'post_type_archive' ) );
            } else {
                $link = home_url( '?post_type=' . $translated_slug );
            }
        }
        return $link;
    }

}

$wpseo_xml_filter = new WPSEO_XML_Sitemaps_Filter();
