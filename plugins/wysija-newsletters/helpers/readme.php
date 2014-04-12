<?php
defined('WYSIJA') or die('Restricted access');
class WYSIJA_help_readme extends WYSIJA_object{
    var $changelog=array();
    function WYSIJA_help_readme(){

    }

    function scan($file=false){
        if(!$file) $file = WYSIJA_DIR.'readme.txt';
        $handle = fopen($file, 'r');
        $content = fread ($handle, filesize ($file));
        fclose($handle);

        // get the changelog content from the readme
        $exploded = explode('== Changelog ==', $content);
        $exploded_versions = explode("\n=", $this->text_links_to_html_links($exploded[1]) );
        foreach($exploded_versions as $key=> $version){
            if(!trim($version)) unset($exploded_versions[$key]);
        }

        foreach($exploded_versions as $key => $version){
            $version_number = '';
            foreach(explode("\n", $version) as $key => $commented_line){
                if($key==0){
                    //extract version number
                    $expldoed_version_number = explode(' - ',$commented_line);
                    $version_number = trim($expldoed_version_number[0]);
                }else{
                    //strip the stars
                    if(!isset($this->changelog[$version_number])) $this->changelog[$version_number] = array();
                    if(trim($commented_line))    $this->changelog[$version_number][] = str_replace('* ', '', $commented_line);
                }
            }
        }

    }

    /**
     * preg_replace that will parse a txt and look for links starting with http, https, ftp, file
     * @param string $content
     * @return string
     */
    function text_links_to_html_links($content){
        return preg_replace_callback('#(?<!href\=[\'"])(https?|ftp|file)://[-A-Za-z0-9+&@\#/%()?=~_|$!:,.;]*[-A-Za-z0-9+&@\#/%()=~_|$]#', array($this,'regexp_url_replace'), $content);
    }

    /**
     * function replacing preg text link to  a html link
     * @param string $array_result
     * @return string
     */
    function regexp_url_replace($array_result){

        $utm_source = (defined('WP_ADMIN') ? 'wpadmin' : 'wpfront');
        $utm_campaign = (!empty($_REQUEST['page']) ? $_REQUEST['page'] : '');
        $utm_campaign .= '_'.(!empty($_REQUEST['action']) ? $_REQUEST['action'] : 'undefined');
        $ga_string = 'utm_source='.$utm_source.'&utm_campaign='.$utm_campaign;

        $parse = parse_url($array_result[0]);

        $parse['scheme'] .= '://';

        if(isset($parse['query'])) $parse['query'] .= $ga_string;
        else $parse['query'] = $ga_string;
        $parse['query'] = '?'. $parse['query'];

        return sprintf('<a href="%1$s" target="_blank">%2$s</a>', implode('',$parse), $array_result[0]);
    }

}