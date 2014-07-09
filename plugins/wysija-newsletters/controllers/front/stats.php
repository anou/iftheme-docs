<?php
defined('WYSIJA') or die('Restricted access');


class WYSIJA_control_front_stats extends WYSIJA_control_front{
    var $model=''   ;
    var $view='';
    
    /**
     * Possible characters to be url encoded
     * @var array 
     */
    protected $characters_to_encode = array(
        '@'
    );

    function WYSIJA_control_front_stats(){
        parent::WYSIJA_control_front();
    }

    /**
     * count the click statistic and redirect to the right url
     * @return boolean
     */
    function analyse(){
        if(isset($_REQUEST['email_id']) && isset($_REQUEST['user_id'])){

            $WJ_Stats = new WJ_Stats();
            if(!empty($WJ_Stats->clicked_url)){
                // clicked stats
                $url = $this->encode_url($WJ_Stats->subscriber_clicked());
                do_action('mpoet_click_stats', $WJ_Stats);
                $this->redirect($url);
            }else{
                // opened stat
                $WJ_Stats->subscriber_opened();
            }
        }

        return true;
    }
    
    /**
     * Encode some special characters in url
     * @param string $url
     * @return string
     */
    protected function encode_url($url) {
        return str_replace(
                $this->characters_to_encode, 
                array_map('urlencode', $this->characters_to_encode), 
                $url
                );
    }
    
}
