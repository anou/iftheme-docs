<?php
defined('WYSIJA') or die('Restricted access');
class WYSIJA_help_package extends WYSIJA_object{

//    Old urls
//    var $base_url_check = 'http://packager.mailpoet.com/release/check/?key=';
//    var $base_url_package = 'http://packager.mailpoet.com/download/zip?key=';

    var $base_url_check = 'http://packager.mailpoet.com/release/check?key=';
    var $base_url_package = 'http://packager.mailpoet.com/release/zip?key=';
    var $beta_param='';

    function WYSIJA_help_package(){
        add_filter( 'pre_set_site_transient_update_plugins', array($this,'check_available_update'));

        $model_config= WYSIJA::get('config','model');
        if($model_config->getValue('beta_mode')){
            $this->beta_param='&beta=true';
        }
    }

    /**
     *
     * @staticvar array $plugins_handled
     * @param string $plugin_name
     * @return boolean
     */
    function set_package($plugin_name=false, $url_check='', $url_package=''){
        static $plugins_handled;

        if($plugin_name===false) return $plugins_handled;
        $plugins_handled[$plugin_name]=array('url_check'=>$url_check, 'url_package'=>$url_package);

        return true;
    }


     /**
     * look for new update of the plugin
     * @param type $plugins_info
     * @return type
     */
    function check_available_update($plugins_info) {
        $plugins=$this->set_package();
        foreach($plugins as $plugin_key => $plugin_data){
            // check that there is no new version available and add it to the list of updatable plugins if there is a bigger version than the current one
            $plugin_path=$plugin_key.'/index.php';
            if(!isset($plugins_info->response[$plugin_path])){


                if(!empty($plugin_data['url_check']))    $url_check = $plugin_data['url_check'];
                else $url_check = $this->base_url_check.$plugin_key;

                $result = $this->_check_request($url_check.$this->beta_param, $plugin_path, $plugin_key);

                if($result!==false){
                    if(!empty($plugin_data['url_package']))    $url_package = $plugin_data['url_package'];
                    else $url_package = $this->base_url_package.$plugin_key;
                    $result->package=$url_package.$this->beta_param;

                    $plugins_info->response[$plugin_path]=$result;
                }

            }else{
                // if our plugin is in the list, we make sure that the package will be downloaded from our url
                $plugins_info->response[$plugin_path]->package = $this->base_url_package.$plugin_key.$this->beta_param;
            }
        }

        return $plugins_info;
    }

    /**
     * the part that handles the remote request
     * @param string $url_check
     * @param string $plugin_path
     * @param string $plugin_key
     * @return \stdClass|boolean
     */
    function _check_request($url_check, $plugin_path, $plugin_key){
        $helper_http = WYSIJA::get('http','helper');

        $content = trim( $helper_http->wp_request($url_check) );

        if($content && strlen($content)>2 && strlen($content)<10){
            $version_number = explode('.', $content);
            if(count($version_number) < 5){
                if( version_compare( trim($content) , WYSIJA::get_version($plugin_path) ) > 0){

                    $object_wjp=new stdClass();
                    $object_wjp->id=9999999;
                    $object_wjp->slug=$plugin_key;
                    $object_wjp->new_version=$content;
                    $object_wjp->url='http://www.mailpoet.com/wordpress-newsletter-plugin-premium/';

                    return $object_wjp;
                }
            }
        }
        return false;
    }

    /**
     * check the latest version on WordPress.org
     * @global type $wp_version
     * @return boolean
     */
    function _check_request_wp(){
        global $wp_version;

        $array_wysija=array(
            'Name'=>'Wysija Newsletters',
            'PluginURI'=>'http://www.mailpoet.com/',
            'Version'=>'1.0',
            'Description'=>'Create and send newsletters. Import and manage your lists. Add subscription forms in widgets, articles and pages. Wysija is a freemium plugin updated regularly with new features.',
            'Author'=>'Wysija',
            'AuthorURI'=>'http://www.mailpoet.com/',
            'TextDomain'=>'wysija-newsletters',
            'DomainPath'=>'/languages/',
            'Network'=>FALSE,
            'Title'=>'Wysija Newsletters',
            'AuthorName'=>'Wysija',
            );

        $plugins['wysija-newsletters/index.php']=$array_wysija;

	$active  = get_option( 'active_plugins', array() );
        $to_send = (object) compact('plugins', 'active');

	$options = array(
		'timeout' => ( ( defined('DOING_CRON') && DOING_CRON ) ? 30 : 3),
		'body' => array( 'plugins' => serialize( $to_send ) ),
		'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
	);

	$raw_response = wp_remote_post('http://api.wordpress.org/plugins/update-check/1.0/', $options);

        if ( is_wp_error( $raw_response ) || 200 != wp_remote_retrieve_response_code( $raw_response ) )
		return false;

	$response = maybe_unserialize( wp_remote_retrieve_body( $raw_response ) );

        return $response;
    }


    /**
     * install a plugin based on a url linking to a zipped package
     * @param string $package_url
     * @param boolean $key
     */
    function install($package_url = '', $key=false){
        //we need to download it
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once WYSIJA_INC . 'wp-special'.DS.'wp-upgrader-skin.php';

        // if we pass a key then we extend the current url
        if($key) $package_url = $this->base_url_package.$package_url.$this->beta_param;
        $upgrader = new Plugin_Upgrader( new WysijaPlugin_Upgrader_Skin( compact('title', 'url', 'nonce', 'plugin', 'api') ));

        $response=$upgrader->install($package_url);
    }

    /**
     * install a plugin based on a url linking to a zipped package
     * @param string $package_url
     * @param boolean $key
     */
    function re_install($package_url = ''){
        //we need to download it
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once WYSIJA_INC . 'wp-special'.DS.'wp-upgrader-skin.php';

        // based on the plugin there is a different title
        switch($package_url){
            case 'wysija-newsletters/index.php':
                $title=__('Downloading Wysija',WYSIJA);
                break;
            case 'wysija-newsletters-premium/index.php':
                $title=__('Downloading Wysija Premium',WYSIJA);
                break;
        }

        $upgrader = new Plugin_Upgrader( new WysijaPlugin_Upgrader_Skin( compact('title', 'url', 'nonce', 'plugin', 'api') ));

        $response=$upgrader->upgrade($package_url);
    }

    function emulate_downgrade_plugin($package_to_reinstall, $from_wysija_com=false){

        if($from_wysija_com === true){
            $plugin_key=  str_replace('/index.php', '', $package_to_reinstall);
            $url_check = $this->base_url_check.$plugin_key;
            $result = $this->_check_request($url_check.$this->beta_param, $package_to_reinstall, $plugin_key);
        }
        else{
            $result = $this->_check_request_wp();
        }
        $latest_stable_version=$result[$package_to_reinstall]->new_version;

        //if the latest stable version  is lower or equal to the current version do nothing
        if(version_compare($latest_stable_version, WYSIJA::get_version())>= 0){
            return false;
        }

        // process the latest stable version and lower it one version below
        $version_numbers = explode('.',$latest_stable_version);
        $last_number=end($version_numbers);
        $key=key($version_numbers);
        reset($version_numbers);
        $version_numbers[$key]=((int)$last_number-1);

        $downgrade_to_version = implode('.', $version_numbers);

        $file_name = WYSIJA_DIR.'index.php';

        // explode the file to an array of lines
        $lines = file($file_name);

        // Loop through our array, show HTML source as HTML source; and line numbers too.
        foreach ($lines as $line_num => &$line) {
            // if we spot the version line we replace the number to something lower thant the current stable one
            if(strpos($line, 'Version:') !== false){
                $line=str_replace(WYSIJA::get_version(), $downgrade_to_version, $line);
            }
        }

        // Open the file for writing.
        $fh = fopen($file_name, 'w');

        fwrite($fh, implode('', $lines));

        // Close the file handle.
        fclose($fh);
    }
}
