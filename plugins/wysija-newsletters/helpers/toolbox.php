<?php
defined('WYSIJA') or die('Restricted access');
class WYSIJA_help_toolbox extends WYSIJA_object{

    function WYSIJA_help_toolbox(){

    }


    /**
     * make a temporary file
     * @param type $content
     * @param type $key
     * @param type $format
     * @return type
     */
    function temp($content,$key='temp',$format='.tmp'){
        $helperF=WYSIJA::get('file','helper');
        $tempDir=$helperF->makeDir();


        $filename=$key.'-'.time().$format;
        $handle=fopen($tempDir.$filename, 'w');
        fwrite($handle, $content);
        fclose($handle);

        return array('path'=>$tempDir.$filename,'name'=>$filename, 'url'=>$this->url($filename,'temp'));
    }

    /**
     * Get the url of a wysija file based on the filename and the wysija folder
     * @param type $filename
     * @param type $folder
     * @return string
     */
    function url($filename,$folder='temp'){
        $upload_dir = wp_upload_dir();

        if(file_exists($upload_dir['basedir'].DS.'wysija')){
            $url=$upload_dir['baseurl'].'/wysija/'.$folder.'/'.$filename;
        }else{
            $url=$upload_dir['baseurl'].'/'.$filename;
        }
        return $url;
    }

    /**
     * send file to be downloaded
     * @param type $path
     */
    function send($path){
        /* submit the file to the admin */
        if(file_exists($path)){
            header('Content-type: application/csv');
            header('Content-Disposition: attachment; filename="export_wysija.csv"');
            readfile($path);
            exit();
        }else $this->error(__('Yikes! We couldn\'t export. Make sure that your folder permissions for /wp-content/uploads/wysija/temp is set to 755.',WYSIJA),true);

    }

    /**
     * clear upload folders from things we don't need anymore
     */
    function clear(){
        $foldersToclear=array('import','temp');
        $filenameRemoval=array('import-','export-');
        $deleted=array();
        $helperF=WYSIJA::get('file','helper');
        foreach($foldersToclear as $folder){
            $path=$helperF->getUploadDir($folder);
            /* get a list of files from this folder and clear them */

            $files = scandir($path);
            foreach($files as $filename){
                if(!in_array($filename, array('.','..','.DS_Store','Thumbs.db'))){
                    if(preg_match('/('.implode($filenameRemoval,'|').')[0-9]*\.csv/',$filename,$match)){
                       $deleted[]=$path.$filename;
                    }
                }
            }
        }
        foreach($deleted as $filename){
            if(file_exists($filename)){
                unlink($filename);
            }
        }

    }

    function closetags($html) {
        #put all opened tags into an array
        preg_match_all('#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
        $openedtags = $result[1];   #put all closed tags into an array
        preg_match_all('#</([a-z]+)>#iU', $html, $result);
        $closedtags = $result[1];
        $len_opened = count($openedtags);
        # all tags are closed
        if(count($closedtags) === $len_opened) {
            return $html;
        }

        $openedtags = array_reverse($openedtags);
        # close tags
        for($i=0; $i < $len_opened; $i++) {
            if(!in_array($openedtags[$i], $closedtags)){
                $html .= '</'.$openedtags[$i].'>';
            } else {
                unset($closedtags[array_search($openedtags[$i], $closedtags)]);
            }
        }
        return $html;
    }

    /**
     * make an excerpt with a certain number of words
     * @param type $text
     * @param type $num_words
     * @param type $more
     * @return type
     */
    function excerpt($text,$num_words=8,$more=' ...'){

        $words_array = preg_split('/[\r\t ]+/', $text, $num_words + 1, PREG_SPLIT_NO_EMPTY);
        if(count($words_array) > $num_words) {
                array_pop($words_array);
                $text = implode(' ', $words_array);
                $text = $text . $more;
        } else {
            $text = implode( ' ', $words_array );
        }

        return $this->closetags($text);
    }

    /**
     * make a domain name out of a url
     * @param type $url
     * @return type
     */
    function _make_domain_name($url=false){
        if(!$url) $url=admin_url('admin.php');

        $domain_name=str_replace(array('https://','http://','www.'),'',strtolower($url));
        //$domain_name=preg_replace(array('#^https?://(www\.)*#i','#^www\.#'),'',$url);
        $domain_name=explode('/',$domain_name);
        return $domain_name[0];
    }

    /**
     * creates a duration string to tell when is the next batch to be processed for instance
     * @param type $s
     * @param type $durationin
     * @param type $level
     * @return string
     */
    function duration($s,$durationin=false,$level=1){
        $t=time();
        if($durationin){
            $e=$t+$s;
            $s=$t;
            //Find out the seconds between each dates
            $timestamp = $e - $s;
        }else{
            $timestamp = $t - $s;
        }

        //Clever Maths
        $years=floor($timestamp/(60*60*24*365));$timestamp%=60*60*24*365;
        $weeks=floor($timestamp/(60*60*24*7));$timestamp%=60*60*24*7;
        $days=floor($timestamp/(60*60*24));$timestamp%=60*60*24;
        $hrs=floor($timestamp/(60*60));$timestamp%=60*60;
        $mins=floor($timestamp/60);
        if($timestamp>60)$secs=$timestamp%60;
        else $secs=$timestamp;


        //Display for date, can be modified more to take the S off
        $str='';
        $mylevel=0;
        if ($mylevel<$level && $years >= 1) { $str.= sprintf(_n( '%1$s year', '%1$s years', $years, WYSIJA ),$years).' ';$mylevel++; }
        if ($mylevel<$level && $weeks >= 1) { $str.= sprintf(_n( '%1$s week', '%1$s weeks', $weeks, WYSIJA ),$weeks).' ';$mylevel++; }
        if ($mylevel<$level && $days >= 1) { $str.=sprintf(_n( '%1$s day', '%1$s days', $days, WYSIJA ),$days).' ';$mylevel++; }
        if ($mylevel<$level && $hrs >= 1) { $str.=sprintf(_n( '%1$s hour', '%1$s hours', $hrs, WYSIJA ),$hrs).' ';$mylevel++; }
        if ($mylevel<$level && $mins >= 1) { $str.=sprintf(_n( '%1$s minute', '%1$s minutes', $mins, WYSIJA ),$mins).' ';$mylevel++; }
        if ($mylevel<$level && $secs >= 1) { $str.=sprintf(_n( '%1$s second', '%1$s seconds', $secs, WYSIJA ),$secs).' ';$mylevel++; }

        return $str;

    }

    /**
     *
     * @param type $time
     * @param type $justtime
     * @return type
     */
    function localtime($time,$justtime=false){
        if($justtime) $time=strtotime($time);
        return date(get_option('time_format'),$time);
    }

    /**
     * return the offseted time formated(used in post notifications)
     * @param type $val
     * @return string
     */
    function time_tzed($val=false){
        return gmdate( 'Y-m-d H:i:s', $this->servertime_to_localtime($val) );
    }

    /**
     * specify a unix server time int and it will convert it to the local time if you don't specify any unixTime value it will convert the current time
     * @param type $unixTime
     * @return int
     */
    function servertime_to_localtime($unixTime=false){

         //this should get GMT-0  time in int date('Z') is the server's time offset compared to GMT-0
        $current_server_time = time();
        $gmt_time = $current_server_time - date('Z');

        //this is the local time on this site :  current time at GMT-0 + the offset chosen in WP settings
        $current_local_time = $gmt_time + ( get_option( 'gmt_offset' ) * 3600 );

        if(!$unixTime) return $current_local_time;
        else{
            //if we've specified a time value in the function, we calculate the difference between the current servertime and the offseted current time
            $time_difference = $current_local_time - $current_server_time;
            //unix time was recorded non offseted so it's the server's time we add the timedifference to it to get the local time
            return $unixTime + $time_difference;
        }
    }

    /**
     * specify a local time int and we will convert it to the server time
     * mostly used with values produced with strtotime() strtotime converts Monday 5pm to the server time's 5pm
     * and if we want to get Monday 5pm of the local time in the server time we need to do a conversion of that value from local to server
     * @param int $server_time time value recorded in the past using time() or strtotime()
     * @return int
     */
    function localtime_to_servertime($server_time){
        //this should get GMT-0  time in int date('Z') is the server's time offset compared to GMT-0
        $current_server_time = time();
        $gmt_time = $current_server_time - date('Z');

        //this is the local time on this site :  current time at GMT-0 + the offset chosen in WP settings
        $current_local_time = $gmt_time + ( get_option( 'gmt_offset' ) * 3600 );

        //this is the time difference between the t
        $time_difference = $current_local_time - $current_server_time;
        //unix time was recorded as local time we substract to it the time difference
        return $server_time - $time_difference;
    }

    function site_current_time($date_format = 'H:i:s'){
        // display the current time
        $current_server_time = time();
        $gmt_time = $current_server_time - date('Z');

        //this is the local time on this site :  current time at GMT-0 + the offset chosen in WP settings
        $current_local_time = $gmt_time + ( get_option( 'gmt_offset' ) * 3600 );
        return date($date_format , $current_local_time);
    }

    /**
     * get the translated day name based on a lowercase day namekey
     * @param type $day if specified we return only one value otherwise we return the entire array
     * @return mixed
     */
    function getday($day=false){

        $days=array('monday'=>__('Monday',WYSIJA),
                    'tuesday'=>__('Tuesday',WYSIJA),
                    'wednesday'=>__('Wednesday',WYSIJA),
                    'thursday'=>__('Thursday',WYSIJA),
                    'friday'=>__('Friday',WYSIJA),
                    'saturday'=>__('Saturday',WYSIJA),
                    'sunday'=>__('Sunday',WYSIJA));
        if(!$day || !isset($days[$day])) return $days;
        else return $days[$day];
    }

    /**
     * get the translated day name based on a lowercase day namekey
     * @param type $week if specified we return only one, otherwise we return the entire array
     * @return mixed
     */
    function getweeksnumber($week=false){
        $weeks=array(
                    '1'=>__('1st',WYSIJA),
                    '2'=>__('2nd',WYSIJA),
                    '3'=>__('3rd',WYSIJA),
                    '4'=>__('Last',WYSIJA),
                    );
        if(!$week || !isset($weeks[$week])) return $weeks;
        else return $weeks[$week];
    }

    /**
     * get the translated day number based on the number in the month until 29th
     * @param type $day if specified we just return one otherwise we return the entire array
     * @return mixed
     */
    function getdaynumber($day=false){
        $daynumbers=array();
        //prepare an array of numbers
        for($i = 1;$i < 29;$i++) {
            switch($i){
                case 1:
                    $number=__('1st',WYSIJA);
                    break;
                case 2:
                    $number=__('2nd',WYSIJA);
                    break;
                case 3:
                    $number=__('3rd',WYSIJA);
                    break;
                default:
                    $number=sprintf(__('%1$sth',WYSIJA),$i);
            }

            $daynumbers[$i] = $number;
        }

        if(!$day || !isset($daynumbers[$day])) return $daynumbers;
        else return $daynumbers[$day];
    }

    /**
     * we use to deal with the WPLANG constant but that's silly considering there are plugins like
     * WPML which needs to alter that value
     * @return string
     */
    function get_language_code(){

        // in WP Multisite if we have a WPLANG defined in the wp-config,
        // it won't be used each site needs to have a WPLANG option defined and if it's not defined it will be empty and default to en_US
        if ( is_multisite() ) {
		// Don't check blog option when installing.
		if ( defined( 'WP_INSTALLING' ) || ( false === $ms_locale = get_option( 'WPLANG' ) ) )
			$ms_locale = get_site_option('WPLANG');

		if ( $ms_locale !== false )
			$locale = $ms_locale;
                // make sure we don't default to en_US if we have an empty locale and a WPLANG defined
                if(empty($locale)) $locale = WPLANG;
	}else{
            $locale = get_locale();
        }

        if($locale!=''){
            if(strpos($locale, '_')!==false){
                $locale = explode('_',$locale);
                $language_code = $locale[0];
            }else{
                $language_code = $locale;
            }
        }else{
            $language_code = 'en';
        }
        return $language_code;
    }

    /**
     * check if a domain exist
     * @param type $domain
     * @return boolean
     */
    function check_domain_exist($domain){

        $mxhosts = array();
        // 1 - Check if the domain exists
        $checkDomain = getmxrr($domain, $mxhosts);
        // 2 - Sometimes the returned host is checkyouremailaddress-hostnamedoesnotexist262392208.com ... not sure why!
        // But we remove it if it's the case...
        if(!empty($mxhosts) && strpos($mxhosts[0],'hostnamedoesnotexist')) array_shift($mxhosts);


        if(!$checkDomain || empty($mxhosts)){
                // 3 - Lets check with another function in case of...
                $dns = @dns_get_record($domain, DNS_A);
                if(empty($dns)) return false;
        }
        return true;
    }

    function check_email_domain($email){
        return $this->check_domain_exist(substr($email,strrpos($email,'@')+1));
    }
}
