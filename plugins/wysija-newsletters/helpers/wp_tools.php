<?php
defined('WYSIJA') or die('Restricted access');
class WYSIJA_help_wp_tools extends WYSIJA_object{

    function WYSIJA_help_wp_tools(){

    }

    /**
     * add wysija's default capabilities to the admin and super admin roles
     */
    function set_default_rolecaps(){
        //add role capabilities
        //get the "administrator" role object
        $rolesadmin=array('administrator','super_admin');

        foreach($rolesadmin as $roladm){
            $role = get_role($roladm);
            if(!$role) continue;
            //add wysija's capabilities to it so that other widgets can reuse it
            $arr=array('wysija_newsletters','wysija_subscribers','wysija_config','wysija_theme_tab','wysija_style_tab', 'wysija_stats_dashboard');

            foreach($arr as $arrkey){
                if(!$role->has_cap($arrkey)) $role->add_cap( $arrkey );
            }
        }
    }

    /**
     * get an array of WordPress roles with a numbered index
     * @global type $wp_roles
     * @return array
     */
    function wp_get_roles() {
        //Careful WordPress global
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        $editable_roles = apply_filters('editable_roles', $all_roles);

        $rolearray=array();
        $sum=6;
        foreach($editable_roles as $keyrol => $roledetails){
            switch($keyrol){
                case 'super_admin':
                    $index=1;
                    break;
                case 'administrator':
                    $index=2;
                    break;
                case 'editor':
                    $index=3;
                    break;
                case 'author':
                    $index=4;
                    break;
                case 'contributor':
                    $index=5;
                    break;
                case 'subscriber':
                    $index=6;
                    break;
                default:
                    $sum++;
                    $index=$sum;
            }
            $rolearray[$index]=array('key'=>$keyrol,'name'=>$roledetails['name']);
        }
        ksort($rolearray);
        return $rolearray;
    }

    /**
     * get an array of WordPress roles with a special capability of that role as index
     * @global type $wp_roles
     * @return array
     */
    function wp_get_editable_roles() {
        //Careful WordPress global
        global $wp_roles;

        $all_roles = $wp_roles->roles;
        $editable_roles = apply_filters('editable_roles', $all_roles);

        $possible_values=array();
        foreach ( $all_roles as $role => $details ) {
            $name = translate_user_role($details['name'] );
            switch($role){
                case 'administrator':
                    $keyrole='switch_themes';
                    break;
                case 'editor':
                    $keyrole='moderate_comments';
                    break;
                case 'author':
                    $keyrole='upload_files';
                    break;
                case 'contributor':
                    $keyrole='edit_posts';
                    break;
                case 'subscriber':
                    $keyrole='read';
                    break;
                default:
                    $keyrole=$role;
            }
            $possible_values[$keyrole]=$name;
            //$possible_values[key($details['capabilities'])]=$name;
        }

        return $possible_values;
    }

    /**
     * get roles by name ? Not so sure why use that function
     * @global type $wp_roles
     * @return array
     */
    function wp_get_all_roles() {
        //Careful WordPress global
        global $wp_roles;
        $all_roles = $wp_roles->get_names();
        return $all_roles;
    }

    /**
     * check whether there is a caching plugin active on this site, we were using that function at some point, it can be useful
     * @global type $cache_enabled
     * @global type $super_cache_enabled
     * @return boolean
     */
    function is_caching_active(){
        $checkPlugins=array(
            'wp-super-cache/wp-cache.php' ,
            'w3-total-cache/w3-total-cache.php',
            'quick-cache/quick-cache.php',
            'hyper-cache/plugin.php'
            );

        foreach($checkPlugins as $pluginFileName){
            if(WYSIJA::is_plugin_active($pluginFileName)){
                switch($pluginFileName){
                    case 'wp-super-cache/wp-cache.php':
                        global $cache_enabled, $super_cache_enabled;
                        if(!(WP_CACHE && $cache_enabled && $super_cache_enabled))   continue(2);
                        break;
                    case 'w3-total-cache/w3-total-cache.php':
                        $config = & w3_instance("W3_Config");
                        if(!(WP_CACHE && $config->get_boolean("pgcache.enabled")))   continue(2);

                        break;
                    case 'quick-cache/quick-cache.php':
                        if(!(WP_CACHE && $GLOBALS["WS_PLUGIN__"]["qcache"]["o"]["enabled"]))   continue(2);
                        break;
                    case 'hyper-cache/plugin.php':
                        if(!(WP_CACHE))   continue(2);
                        break;
                    default:
                        continue(2);
                }
                return true;
            }
        }
        return false;
    }

    /**
     * extends the get_permalink of WordPress since at the beginning we had a lot of problems with people who didn't have pretty urls activated etc..
     * @param int $pageid
     * @param array $params pass an array of parameters to the url
     * @param boolean $simple leading to the home not sure in which case we need that again
     * @return string
     */
    function get_permalink($pageid,$params=array(),$simple=false){
        $post = get_post($pageid);

        $url=get_permalink($post);

        if(!$url){
            //we need to recreate the subscription page
            $values=array();
            $helperInstall=WYSIJA::get('install','helper');
            $helperInstall->createPage($values);

            $modelConf=WYSIJA::get('config','model');
            $modelConf->save($values);
            $post = get_post($values['confirm_email_link']);
            $url=get_permalink($post);
        }

        $paramsquery=parse_url($url);

        if($params!==false) $params[$post->post_type]=$post->post_name;
        //make a simple url leading to the home
        if($simple){
            $url=site_url();
            // make sure we have a trailing slash at the end
            if($url{strlen($url) - 1} !== '/') $url .= '/';
        }

        if(isset($paramsquery['query'])){
            $myparams=explode('&',$paramsquery['query']);
            //get the param from the url obtain in permalink and transfer it to our url
            foreach($myparams as $paramvalu){
                $splitkeyval=explode('=',$paramvalu);
                $params[$splitkeyval[0]]=$splitkeyval[1];
            }
        }

        // make sure we include the port if it's specified
        if(isset($paramsquery['port']) && strlen(trim($paramsquery['port'])) > 0) {
            $port = ':'.(int)$paramsquery['port'];
        } else {
            $port = '';
        }

        // build url
        $url = sprintf('%s://%s%s%s', $paramsquery['scheme'], $paramsquery['host'], $port, $paramsquery['path']);

        if($params) {
            if(strpos($url, '?') !== false) $charStart='&';
            else $charStart='?';
            $url.=$charStart;
            $paramsinline=array();
            foreach($params as $k => $v){
                if(is_array($v))    $v = http_build_query(array($k => $v));
                $paramsinline[]=$k.'='.$v;
            }
            $url.=implode('&',$paramsinline);
        }

        // Transform relative URLs in Absolute URLs (Protect from external URL transforming plugins)
        $parsed_url = parse_url($url);
        if (empty($parsed_url['scheme'])) {
            $url = get_bloginfo('url') . $url;
        }

        return $url;
    }

    /**
     * return a list of post types
     * @return mixed
     */
    function get_post_types($return_type = 'objects') {
        $args=array(
          'public'   => true,
          '_builtin' => false,
          'show_in_menu'=>true,
          'show_ui'=>true,
        );
        return get_post_types($args, $return_type);
    }

    /**
     * return a list of post types
     * @return mixed
     */
    function get_post_statuses(){
        return array_merge(get_post_statuses(), array('future'=>__('Scheduled',WYSIJA)));
    }

    /**
     * get a multidimensionnal array that returns the categories per CPT
     */
    function get_categories($custom_post_type = null) {
        if($custom_post_type === null) {
            $post_types = array_merge(array('post', 'page'), array_keys($this->get_post_types('names')));
        } else {
            $post_types = array($post_type);
        }

        $cpt_categories = array();
        $cpt_taxonomies = array();

        foreach($post_types as $post_type) {
            // get taxonomies for each post type
            if($post_type === 'post') {
                $cpt_taxonomies = array('category');
            } else {
                $cpt_taxonomies = get_object_taxonomies($post_type);
            }

            if(!empty($cpt_taxonomies)) {
                 foreach($cpt_taxonomies as $taxonomy) {

                    $post_type_categories = array();
                    $args = array(
                        'orderby' => 'name',
                        'show_count' => 0,
                        'pad_counts' => 0,
                        'hierarchical' => 1,
                        'taxonomy' => $taxonomy,
                        'title_li' => '',
                        'hide_empty' => 0
                    );

                    $post_type_categories = get_categories($args);

                    if(!empty($post_type_categories)) {
                        foreach($post_type_categories as $post_type_category) {
                            $cpt_categories[$post_type][$post_type_category->cat_ID] = array('id' => $post_type_category->cat_ID, 'name' => $post_type_category->name);
                        }
                    }
                }
                $cpt_categories[$post_type] = array_values($cpt_categories[$post_type]);
            }
        }

        if($custom_post_type === null) {
            return $cpt_categories;
        } else {
            return $cpt_categories[$post_type];
        }
    }

    function get_post_category_ids($post = null) {

        $cpt_categories = array();
        $cpt_taxonomies = array();

        // get taxonomies for each post type
        $post_type = 'post';
        $cpt_taxonomies = get_object_taxonomies($post->post_type);

        if(!empty($cpt_taxonomies)) {
             foreach($cpt_taxonomies as $taxonomy) {
                $post_type_categories = wp_get_post_terms($post->ID, $taxonomy);

                if(!empty($post_type_categories)) {
                    foreach($post_type_categories as $post_type_category) {
                        $cpt_categories[] = $post_type_category->term_id;
                    }
                }
            }
            $cpt_categories = array_unique($cpt_categories);
        }

        return $cpt_categories;
    }
}