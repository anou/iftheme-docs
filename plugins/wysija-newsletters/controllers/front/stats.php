<?php
defined('WYSIJA') or die('Restricted access');


class WYSIJA_control_front_stats extends WYSIJA_control_front{
    var $model=''   ;
    var $view='';

    function WYSIJA_control_front_stats(){
        parent::WYSIJA_control_front();
    }


    function rm_url_param($param_rm=array(), $query='')
    {
        if(!$query) return $query;
        $queries=explode('?',$query);
        $params=array();
        parse_str($queries[1], $params);

        foreach($param_rm as $param_rmuniq)
            unset($params[$param_rmuniq]);
        $newquery = $queries[0];

        if($params){
            $newquery.='?';
            $i=0;
            foreach($params as $k => $v){
                if($i>0)    $newquery .= '&';
                $newquery.=$k.'='.$v;
                $i++;

            }
        }else return $newquery;

        return substr($newquery,1);
    }

    /**
     * count the click statistic and redirect to the right url
     * @return boolean
     */
    function analyse(){
        if(isset($_REQUEST['debug'])){
            if(version_compare(phpversion(), '5.4')>= 0){
                error_reporting(E_ALL ^ E_STRICT);

            }else{
                error_reporting(E_ALL);
            }
            ini_set('display_errors', '1');
        }
        if(isset($_REQUEST['email_id']) && isset($_REQUEST['user_id'])){
            $email_id=(int)$_REQUEST['email_id'];
            $user_id=(int)$_REQUEST['user_id'];

            //debug message
            if(isset($_REQUEST['debug']))   echo '<h2>isset email_id and user_id</h2>';

            $requesturlencoded=false;
            if(isset($_REQUEST['urlencoded'])){
                $requesturlencoded=$_REQUEST['urlencoded'];
            }elseif(isset($_REQUEST['urlpassed'])){
                $requesturlencoded=$_REQUEST['urlpassed'];
            }

            if($requesturlencoded){
                //clicked stats
                if(isset($_REQUEST['no64'])){
                    $recorded_url=$decoded_url=$requesturlencoded;
                }else{
                    $recorded_url=$decoded_url=base64_decode($requesturlencoded);
                }
                if(strpos($recorded_url, 'utm_source')!==false){
                    $recorded_url=$this->rm_url_param(array('utm_source','utm_campaign','utm_medium'),$recorded_url);
                }

                //debug message
                if(isset($_REQUEST['debug']))   echo '<h2>isset urlencoded '.$decoded_url.'</h2>';

                if($email_id && !isset($_REQUEST['demo'])){ //if not email_id that means it is an email preview
                    //look for url entry and insert if not exists
                    $model_url = WYSIJA::get('url','model');

                    $url_array = $model_url->getOne(false,array('url'=>$recorded_url));

                    if(!$url_array){
                        //we need to insert in url
                        $model_url->insert(array('url'=>$recorded_url));
                        $url_array = $model_url->getOne(false,array('url'=>$recorded_url));
                    }
                    $model_url = null;

                    //look for email_user_url entry and insert if not exists
                    $model_email_user_url = WYSIJA::get('email_user_url','model');
                    $data_email_user_url = array('email_id'=>$email_id,'user_id'=>$user_id,'url_id'=>$url_array['url_id']);
                    $email_user_url_array = $model_email_user_url->getOne(false,$data_email_user_url);
                    $unique_click = false;

                    if( !$email_user_url_array && $email_id > 0 && $user_id > 0 && $url_array['url_id'] > 0 ){
                        //we need to insert in email_user_url
                        $model_email_user_url->reset();
                        $query_EmailUserUrl = 'INSERT IGNORE INTO [wysija]email_user_url (`email_id` ,`user_id`,`url_id`) ';
                        $query_EmailUserUrl .= 'VALUES ('.$email_id.', '.$user_id.', '.$url_array['url_id'].')';

                        $model_email_user_url->query($query_EmailUserUrl);

                        //$modelEmailUserUrl->insert($dataEmailUserUrl);
                        $unique_click=true;
                    }

                    //increment stats counter on email_user_url clicked
                    $model_email_user_url = WYSIJA::get('email_user_url','model');
                    $model_email_user_url->update(array('clicked_at'=>time(),'number_clicked'=>'[increment]'),$data_email_user_url);
                    $model_email_user_url=null;

                    //look for url_mail entry and insert if not exists
                    $model_url_mail = WYSIJA::get('url_mail','model');
                    $data_url_mail = array('email_id'=>$email_id,'url_id'=>$url_array['url_id']);
                    $urlMailObj=$model_url_mail->getOne(false,$data_url_mail);
                    if(!$urlMailObj){
                        //we need to insert in url_mail
                        $model_url_mail->reset();
                        $model_url_mail->insert($data_url_mail );
                    }

                    $dataUpdate = array('total_clicked'=>'[increment]');
                    if(!$unique_click)    $dataUpdate['unique_clicked']='[increment]';
                    //increment stats counter on url_mail clicked
                    $model_url_mail->update($dataUpdate,$data_url_mail);
                    $model_url_mail = null;

                    $status_email_user_stat = 2;
                    if(in_array($recorded_url,array('[unsubscribe_link]','[subscriptions_link]','[view_in_browser_link]'))){
                        $this->subscriberClass = WYSIJA::get('user','model');
                        $this->subscriberClass->getFormat=OBJECT;

                        //check if the security hash is passed to insure privacy
                        $receiver = $link = false;
                        if(isset($_REQUEST['hash'])){
                            if($_REQUEST['hash']==md5(AUTH_KEY.$recorded_url.$user_id)){
                                $receiver = $this->subscriberClass->getOne(array('user_id'=>$user_id));
                            }else{
                                die('Security check failure.');
                            }
                        }else{
                            //link is not valid anymore
                            //propose to resend the newsletter with good links ?
                            $link = $this->subscriberClass->old_get_new_link_for_expired_links($user_id,$email_id);
                        }


                        switch($recorded_url){
                            case '[unsubscribe_link]':
                                //we need to make sure that this link belongs to that user
                                if($receiver){
                                    $link = $this->subscriberClass->getUnsubLink($receiver,true);
                                    $status_email_user_stat=3;
                                }
                                break;
                            case '[subscriptions_link]':
                                if($receiver){
                                    $link = $this->subscriberClass->getEditsubLink($receiver,true);
                                }
                                break;
                            case '[view_in_browser_link]':
                                $model_email = WYSIJA::get('email','model');
                                $data_email = $model_email->getOne(false,array('email_id'=>$email_id));
                                $helper_email = WYSIJA::get('email','helper');
                                $link = $helper_email->getVIB($data_email);
                                break;
                        }

                        //if the subscriber still exists in the DB we will have a link
                        if($link){
                            $decoded_url = $link;
                        }else{
                            //the subscriber doesn't appear in the DB we can redirect to the web version
                            $decoded_url = $this->_get_browser_link($email_id);

                            return $this->redirect($decoded_url);
                        }

                    }else{

                        if(strpos($decoded_url, 'http://' )=== false && strpos($decoded_url, 'https://' )=== false) $decoded_url='http://'.$decoded_url;
                        //check that there is no broken unsubscribe link such as http://[unsubscribe_link]
                        if(strpos($decoded_url, '[unsubscribe_link]')!==false){
                            $this->subscriberClass = WYSIJA::get('user','model');
                            $this->subscriberClass->getFormat=OBJECT;
                            $receiver = $this->subscriberClass->getOne($user_id);
                            $decoded_url=$this->subscriberClass->getUnsubLink($receiver,true);
                        }

                        if(strpos($decoded_url, '[view_in_browser_link]')!==false){
                            $link=$this->_get_browser_link($email_id);
                            $decoded_url=$link;
                        }

                    }

                    //debug information
                    if(isset($_REQUEST['debug']))   echo '<h2>isset decoded url '.$decoded_url.'</h2>';

                    $model_email_user_stat = WYSIJA::get('email_user_stat','model');
                    $exists = $model_email_user_stat->getOne( false,array('equal'=>array('email_id'=>$email_id,'user_id'=>$user_id), 'less'=>array('status'=>$status_email_user_stat)) );
                    $data_update = array('status' => $status_email_user_stat);
                    if($exists && isset($exists['opened_at']) && !(int)$exists['opened_at']){
                        $data_update['opened_at']=time();
                    }

                    $model_email_user_stat->reset();
                    $model_email_user_stat->colCheck=false;
                    $model_email_user_stat->update($data_update,array('equal'=>array('email_id'=>$email_id,'user_id'=>$user_id), 'less'=>array('status'=>$status_email_user_stat)));


                }else{
                   if(in_array($recorded_url,array('[unsubscribe_link]','[subscriptions_link]','[view_in_browser_link]'))){
                        $model_user = WYSIJA::get('user','model');
                        $model_user->getFormat=OBJECT;
                        $user_object = $model_user->getOne(false,array('wpuser_id'=>get_current_user_id()));
                        switch($recorded_url){
                            case '[unsubscribe_link]':
                                $link=$model_user->getConfirmLink($user_object,'unsubscribe',false,true).'&demo=1';

                                break;
                            case '[subscriptions_link]':
                                $link=$model_user->getConfirmLink($user_object,'subscriptions',false,true).'&demo=1';
                                //$link=$this->subscriberClass->getEditsubLink($receiver,true);
                                break;
                            case 'view_in_browser_link':
                            case '[view_in_browser_link]':
                                if(!$email_id) $email_id=$_REQUEST['id'];

                                $link=$this->_get_browser_link($email_id);
                                break;
                        }
                        $decoded_url=$link;

                    }else{
                        if(strpos($decoded_url, 'http://' )=== false && strpos($decoded_url, 'https://' )=== false) $decoded_url='http://'.$decoded_url;
                    }
                    if(isset($_REQUEST['debug']))   {
                        echo '<h2>not email_id </h2>';
                    }
                }

                //sometimes this will be a life saver :)
                $decoded_url = str_replace('&amp;','&',$decoded_url);
                if(isset($_REQUEST['debug']))   {
                    echo '<h2>final decoded url '.$decoded_url.'</h2>';
                    exit;
                }
                $this->redirect($decoded_url);


            }else{
                //opened stat */
                //$modelEmail=WYSIJA::get("email","model");
                //$modelEmail->update(array('number_opened'=>"[increment]"),array("email_id"=>$email_id));

                $model_email_user_stat=WYSIJA::get('email_user_stat','model');
                $model_email_user_stat->reset();
                $model_email_user_stat->update(
                        array('status'=>1,'opened_at'=>time()),
                        array('email_id'=>$email_id,'user_id'=>$user_id,'status'=>0));

		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );

		if(empty($picture)) $picture = WYSIJA_DIR_IMG.'statpicture.png';
		$handle = fopen($picture, 'r');

		if(!$handle) exit;
		header('Content-type: image/png');
		$contents = fread($handle, filesize($picture));
		fclose($handle);
		echo $contents;
                exit;
            }


        }

        return true;
    }

    function _get_browser_link($email_id){
        $paramsurl=array(
            'wysija-page'=>1,
            'controller'=>'email',
            'action'=>'view',
            'email_id'=>$email_id,
            'user_id'=>0
            );
        $config=WYSIJA::get('config','model');
        return WYSIJA::get_permalink($config->getValue('confirm_email_link'),$paramsurl);
    }

}
