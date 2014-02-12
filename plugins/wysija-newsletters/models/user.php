<?php
defined('WYSIJA') or die('Restricted access');
class WYSIJA_model_user extends WYSIJA_model{

    var $pk='user_id';
    var $table_name='user';
    var $columns=array(
        'user_id'=>array('auto'=>true),
        'wpuser_id' => array('req'=>true,'type'=>'integer'),
        'email' => array('req'=>true,'type'=>'email'),
        'firstname' => array(),
        'lastname' => array(),
        'ip' => array('req'=>true,'type'=>'ip'),
        'keyuser' => array(),
        'status' => array('req'=>true,'type'=>'boolean'),
        'created_at' => array('auto'=>true,'type'=>'date'),
    );
    var $searchable = array('email','firstname', 'lastname');

    function WYSIJA_model_user(){
        $this->columns['status']['label']=__('Status',WYSIJA);
        $this->columns['created_at']['label']=__('Created on',WYSIJA);
        $this->WYSIJA_model();
    }

    /**
     * return the subscription status of one subscriber using his user_id
     * @param int $user_id
     * @return int
     */
    function getSubscriptionStatus($user_id){
        $this->getFormat=OBJECT;
        $result=$this->getOne(array('status'),array('user_id'=>$user_id));
        return $result->status;
    }

    /**
     * count total subscribers per list based on parameters passed
     * TODO we should use get_subscribers() instead
     * @param array $list_ids
     * @param boolean $confirmed_subscribers
     * @return int
     */
    function countSubscribers(Array $list_ids = array(), $confirmed_subscribers = true)
    {
        $model_config = WYSIJA::get('config','model');
        $confirm_dbleoptin = $model_config->getValue('confirm_dbleoptin');
        if($confirm_dbleoptin) $confirmed_subscribers = true;


        $where = array();
        $where[] = 'C.is_enabled = 1';
        $where[] = $confirmed_subscribers ? 'status = 1' : 'status >= 0';
        if(!empty($list_ids)){
            $where[] = 'C.list_id IN ('.implode(',',$list_ids).')';
        }

        $query = '
            SELECT
                COUNT(DISTINCT A.user_id)
            FROM
               [wysija]user A
            JOIN
                [wysija]user_list B ON A.user_id = B.user_id
            JOIN
                [wysija]list C ON C.list_id = B.list_id
            WHERE 1';
        if(!empty($where)) $query .= ' AND '.implode (' AND ', $where);
        return $this->count($query);
    }


    /**
     * get a user object not an array as we do by default with "getOne"
     * @param int $user_id
     * @return object
     */
    function getObject($user_id){
        $this->getFormat=OBJECT;
        return $this->getOne(false,array('user_id'=>$user_id));
    }

    /**
     * Get User object using his email address
     * @param string $email
     * @return object WYSIJA_model_user
     */
    function get_object_by_email($email){
        $this->getFormat=OBJECT;
        return $this->getOne(false,array('email'=>$email));
    }

    /**
     * get the details, lists and stats email counts regarding one user
     * @param array $conditions : Wysija's condition format
     * @param boolean $stats : do we get the stats data of that user
     * @param boolean $subscribed_list_only :
     * @return boolean
     */
    function getDetails($conditions,$stats=false,$subscribed_list_only=false){
        $data=array();
        $this->getFormat=ARRAY_A;
        $array=$this->getOne(false,$conditions);
        if(!$array) return false;

        $data['details'] = $array;

        //get the list  that the user subscribed to
        $model_user_list = WYSIJA::get('user_list','model');
        $conditions = array('user_id'=>$data['details']['user_id']);
        if($subscribed_list_only){
            $conditions['unsub_date']=0;
        }

        $data['lists'] = $model_user_list->get(false,$conditions);

        //get the user stats if requested
        if($stats){
            $model_email_user_stat = WYSIJA::get('email_user_stat','model');
            $model_email_user_stat->setConditions(array('equal'=>array('user_id'=>$data['details']['user_id'])));
            $data['emails'] = $model_email_user_stat->count(false);
        }

        return $data;
    }

    /**
     * return the subscriber object for the currently logged in WordPress user
     * @return object user
     */
    function getCurrentSubscriber(){
        static $result_user;
        if(!empty($result_user)) return $result_user;
        $this->getFormat = OBJECT;
        $result_user = $this->getOne(false,array('wpuser_id'=>WYSIJA::wp_get_userdata('ID')));

        if(!$result_user){
            $this->getFormat = OBJECT;
            $result_user = $this->getOne(false,array('email'=>WYSIJA::wp_get_userdata('user_email')));
            $this->update(array('wpuser_id'=>WYSIJA::wp_get_userdata('ID')),array('email'=>WYSIJA::wp_get_userdata('user_email')));
        }

        //the subscriber doesn't seem to exist let's insert it in the DB
        if(!$result_user){
            $data = get_userdata(WYSIJA::wp_get_userdata('ID'));
            $firstname = $data->first_name;
            $lastname = $data->last_name;
            if(!$data->first_name && !$data->last_name) $firstname = $data->display_name;
            $this->noCheck=true;
            $this->insert(array(
                'wpuser_id'=>$data->ID,
                'email'=>$data->user_email,
                'firstname'=>$firstname,
                'lastname'=>$lastname));

            $this->getFormat = OBJECT;
            $result_user = $this->getOne(false,array('wpuser_id'=>WYSIJA::wp_get_userdata('ID')));
        }

        return $result_user;
    }

    /**
     * function used to generate the links for subscriber management, confirm, unsubscribe, edit subscription
     * @param boolean,object $user_obj
     * @param string $action what axctin will be performed (subscribe, unsubscribe, subscriptions)
     * @param string $text name of the link
     * @param boolean $url_only returns only the url no html wrapper
     * @param string $target how does the link open
     * @return type
     */
    function getConfirmLink($user_obj = false, $action = 'subscribe', $text = false, $url_only = false, $target = '_blank'){
        if(!$text) $text=__('Click here to subscribe',WYSIJA);
        $users_preview=false;
        //if($action=='subscriptions')dbg($userObj);
        if(!$user_obj){
            //preview mode
            $user_obj = $this->getCurrentSubscriber();
            $users_preview = true;
        }
        $params = array(
        'wysija-page'=>1,
        'controller'=>'confirm',
        );
        if($user_obj && isset($user_obj->keyuser)){
            //if the user key doesn exists let's generate it
            if(!$user_obj->keyuser){
                $user_obj->keyuser = $this->generateKeyuser($user_obj->email);
                while($this->exists(array('keyuser'=>$user_obj->keyuser))){
                    $user_obj->keyuser=$this->generateKeyuser($user_obj->email);
                }
               $this->update(array('keyuser'=>$user_obj->keyuser),array('user_id'=>$user_obj->user_id));
            }

            $this->reset();
            $params['wysija-key']=$user_obj->keyuser;
        }
        $params['action']=$action;
        $model_config=WYSIJA::get('config','model');
        if($users_preview) $params['demo']=1;
        $full_url=WYSIJA::get_permalink($model_config->getValue('confirm_email_link'),$params);
        if($url_only) return $full_url;
        return '<a href="'.$full_url.'" target="'.$target.'">'.$text.'</a>';
    }

    /**
     * get the edit subscription link
     * @param type $user_obj
     * @param type $url_only
     * @param type $target
     * @return type
     */
    function getEditsubLink($user_obj=false,$url_only=false, $target = '_blank'){
        return $this->getConfirmLink($user_obj,'subscriptions',__('Edit your subscriptions',WYSIJA),$url_only,false,$target);
    }

    /**
     * get the unsubscribe link
     * @param type $user_obj
     * @param type $url_only
     * @return string
     */
    function getUnsubLink($user_obj=false,$url_only=false){
        $model_config=WYSIJA::get('config','model');
        return $this->getConfirmLink($user_obj,'unsubscribe',$model_config->getValue('unsubscribe_linkname'),$url_only);
    }

    /**
     * used to generate a hash to identify each subscriber, this is used later in confirmation links etc...
     * @param string $email
     * @return string md5
     */
    function generateKeyuser($email){
        return md5($email.time());
    }

    /**
     * returns the user_id providing either the wpuser_id value or an email
     * @param string $email
     * @return int
     */
    function user_id($email){
        $this->getFormat=ARRAY_A;
        if(is_numeric($email)){
            $result = $this->getOne(array('user_id'),array('wpuser_id'=>$email));
        }else{
            $result = $this->getOne(array('user_id'),array('email'=>$email));
        }
        return (int)$result['user_id'];
    }


    /**
     * prepare the filters for a user select query based on the PHP global parameters
     * @return array
     */
    function detect_filters(){
        $filters = array();
        // get the filters
        if(!empty($_REQUEST['search'])){
            $filters['search'] = $_REQUEST['search'];
        }

        if(!empty($_REQUEST['wysija']['filter']['search'])){
            $filters['search'] = $_REQUEST['wysija']['filter']['search'];
        }

        // Lists filters
        // - override wysija[filter][filter-list] if this is empty
        if( !empty($_REQUEST['redirect'])
                && (!empty($_REQUEST['filter_list']))
                && empty($_REQUEST['wysija']['filter']['filter_list']) ){
            $_REQUEST['wysija']['filter']['filter_list'] = !empty ($_REQUEST['filter_list']) ? $_REQUEST['filter_list'] : $_REQUEST['filter-list'];
        }

        if(!empty($_REQUEST['wysija']['filter']['filter_list'])){
            if ($_REQUEST['wysija']['filter']['filter_list'] == 'orphaned') {
                $filters['lists'] = null;
            } else {
                //we only get subscribed or unconfirmed users
                $filters['lists'] = $_REQUEST['wysija']['filter']['filter_list'];
            }
        }

        if(!empty($_REQUEST['link_filter'])){
            $filters['status'] = $_REQUEST['link_filter'];
        }

        if(!empty($_REQUEST['wysija']['user']['timestamp'])){
            //$filters['created_at']= $_REQUEST['wysija']['user']['timestamp'];
        }

        return $filters;
    }


    /**
     * count the confirmed and unconfirmed users for each list by status
     * @param type $list_ids
     * @return type
     */
    function count_users_per_list($list_ids=array()){
        $select = array( 'COUNT(DISTINCT([wysija]user.user_id)) as total_users', '[wysija]user_list.list_id');
        $count_group_by = 'list_id';

        // filters for unsubscribed
        $filters = array();
        $filters['lists'] = $list_ids;
        $filters['status'] = 'unsubscribed';

        $unsubscribed_users = $this->get_subscribers( $select, $filters, $count_group_by );

        $list_count_per_status=array();
        foreach($unsubscribed_users as $unsubscribed){
            if(!isset($list_count_per_status['list_id']['unsubscribers'])){
                $list_count_per_status[$unsubscribed['list_id']]['unsubscribers']=$unsubscribed['total_users'];
            }
        }

        // count confirmed subscribers
        $filters = array();
        $filters['lists'] = $list_ids;
        $filters['status'] = 'subscribed';

        $subscribed_users = $this->get_subscribers( $select, $filters, $count_group_by );

        foreach($subscribed_users as $subscribed){
            if(!isset($list_count_per_status['list_id']['subscribers'])){
                $list_count_per_status[$subscribed['list_id']]['subscribers']=$subscribed['total_users'];
            }
        }

        // count unconfirmed subscribers
        $filters = array();
        $filters['lists'] = $list_ids;
        $filters['status'] = 'unconfirmed';

        $unconfirmed_users = $this->get_subscribers( $select, $filters, $count_group_by);

        foreach($unconfirmed_users as $unconfirmed){
            if(!isset($list_count_per_status['list_id']['unconfirmed'])){
                $list_count_per_status[$unconfirmed['list_id']]['unconfirmed']=$unconfirmed['total_users'];
            }
        }

        // get the total count of subscribers per list
        $filters = array();
        $filters['lists'] = $list_ids;
        $total_belonging = $this->get_subscribers( $select, $filters , $count_group_by );

        // get the count of confirmed user per each and unconfirmed user per list
        foreach($total_belonging as $belonging){
            if(!isset($list_count_per_status['list_id']['belonging'])){
                $list_count_per_status[$belonging['list_id']]['belonging']=$belonging['total_users'];
            }
        }

        return $list_count_per_status;
    }


    function _convert_filters($filters_in){
        $filters_out = array();
        $model_config = WYSIJA::get('config','model');
        $filter_has_list = false;

        // here we found a search condition
        if(!empty($filters_in['search'])){
            $filters_out['like'] = array();
            $filters_in['search'] = trim($filters_in['search']);
            foreach($this->searchable as $field){
                $filters_out['like'][$field] = trim($filters_in['search']);
            }
        }

        // as soon as we detect lists we set the query that way
        if(!empty($filters_in['lists'])){
            $filters_out['equal']['list_id'] = $filters_in['lists'];
            $filter_has_list = true;
        }

        // we detect a status condition
        if(!empty($filters_in['status'])){
            switch($filters_in['status']){
                case 'unconfirmed':
                    $filters_out['equal']['status'] = 0;

                    if($filter_has_list){
                        $filters_out['greater_eq']['sub_date'] =0;
                        $filters_out['equal']['unsub_date'] =0;
                    }
                    break;
                case 'unsubscribed':
                    $filters_out['equal']['status'] = -1;

                    if($filter_has_list){
                        $filters_out['equal']['sub_date'] =0;
                        $filters_out['greater_eq']['unsub_date'] =0;
                    }
                    break;
                case 'subscribed':
                    if($model_config->getValue('confirm_dbleoptin'))  $filters_out['equal']['status'] = 1;
                    else $filters_out['greater_eq']=array('status'=>0);

                    if($filter_has_list){
                        $filters_out['greater_eq']['sub_date'] =0;
                        $filters_out['equal']['unsub_date'] =0;
                    }

                    break;
                case 'all':


                    break;
            }
        }

        if(!empty($filters_in['created_at'])){
            $filters_out['less_eq']['created_at'] = $filters_in['created_at'];
        }

        return $filters_out;
    }
    /**
     *
     * @param type $select
     * @param type $filters
     */
    function get_subscribers($select = array(), $filters = array(), $count_group_by = '', $return_query=false){
        $this->noCheck=true;
        $is_count = false;

        $select = str_replace(array('[wysija]user_list', '[wysija]user', 'count('), array('B', 'A', 'COUNT('), $select);

        if(!empty($filters)){
            $filters = $this->_convert_filters($filters);
            $this->setConditions($filters);
        }



        //1 - prepare select
        if(isset($filters['equal']['list_id'])){
            // orphans are selected with this kind of join
            if($filters['equal']['list_id']==null) {

                $select_string = implode(', ', $select);
                if(strpos($select_string, 'COUNT(') === false){
                    $select_string = str_replace('B.user_id', 'DISTINCT(B.user_id)', $select_string);
                }else{
                    $is_count = true;
                }

                $query = 'SELECT '.$select_string.' FROM `[wysija]user` as A';
                $query .= ' LEFT JOIN `[wysija]user_list` as B on B.user_id=A.user_id';
            }else{
                // standard select when lists ids are in the filters

                $select_string = implode(', ', $select);
                if(strpos($select_string, 'COUNT(') === false){
                    $select_string = str_replace('A.user_id', 'DISTINCT(B.user_id)', $select_string);
                }else{
                    $is_count = true;
                }

                $query = 'SELECT '.$select_string.' FROM `[wysija]user_list` as B';
                $query .= ' JOIN `[wysija]user` as A on A.user_id=B.user_id';
            }
        } else {
            // when there is no filter list
            $select_string = implode(', ', $select);
            if(strpos($select_string, 'COUNT(') === false){
                $select_string = str_replace('B.user_id', 'A.user_id', $select_string);
            }else{
                $is_count = true;
            }

            $query = 'SELECT '.$select_string.' FROM `[wysija]user` as A';
        }

        $query .= $this->makeWhere();

        if(!$is_count){
            if($return_query) return $query;

            $order_by=' ORDER BY ';
            if(!empty($_REQUEST['orderby'])){
                $order_by.=$_REQUEST['orderby'].' '.$_REQUEST['ordert'];
            }else{
                $order_by.=$this->pk.' desc';
            }

            $query = $query.' '.$order_by.$this->setLimit();
            return $this->getResults($query);
        }else{
            if(!empty($count_group_by)){
                return $this->getResults($query.' GROUP BY '.$count_group_by);
            }else{
                $result = $this->getResults($query);
                return $result[0];
            }
        }

    }

    public function structure_user_status_count_array($count_by_status){
        $arr_max_create_at = array();
        foreach($count_by_status as $status_data){

            switch($status_data['status']){
                case '-1':
                    $counts['unsubscribed'] = $status_data['users'];
                    break;
                case '0':
                    $counts['unconfirmed'] = $status_data['users'];
                    break;
                case '1':
                    $counts['subscribed'] = $status_data['users'];
                    break;
            }
            $arr_max_create_at[] = $status_data['max_create_at'];
        }
        $counts['all'] = 0;
        if(isset($counts['unsubscribed'])) $counts['all'] += $counts['unsubscribed'];
        if(isset($counts['unconfirmed'])) $counts['all'] += $counts['unconfirmed'];
        if(isset($counts['subscribed'])) $counts['all'] += $counts['subscribed'];

        return $counts;
    }

    public function get_max_create($count_by_status){
        $arr_max_create_at = array();
        foreach($count_by_status as $status_data){
            $arr_max_create_at[] = $status_data['max_create_at'];
        }
        return $arr_max_create_at;
    }

    /**
     * triggered before a user is inserte using the insert function of the user model
     * @return boolean
     */
    function beforeInsert(){
        // set the activation key
        $model_user=WYSIJA::get('user','model');

        $this->values['keyuser']=md5($this->values['email'].$this->values['created_at']);
        while($model_user->exists(array('keyuser'=>$this->values['keyuser']))){
            $this->values['keyuser']=$this->generateKeyuser($this->values['email']);
            $model_user->reset();
        }

        if(!isset($this->values['status'])) $this->values['status']=0;

        return true;
    }

    /**
     * triggered before a user is deleted using the delete function of the user model
     * @return boolean
     */
    function beforeDelete($conditions){
        $model_user = new WYSIJA_model_user();
        $users = $model_user->get(array('user_id'),$this->conditions);
        $user_ids = array();
        foreach($users as $user) $user_ids[]=$user['user_id'];

        //delete all the user stats
        $model_email_user_stat=WYSIJA::get('email_user_stat','model');
        $conditions=array('user_id'=>$user_ids);
        $model_email_user_stat->delete($conditions);
        //delete all the queued emails
        $model_queue=WYSIJA::get('queue','model');
        $model_queue->delete($conditions);
        return true;
    }

    /**
     * triggered after a user is deleted using the delete function of the user model
     * @return boolean
     */
    function afterDelete(){
        $helper_user=WYSIJA::get('user','helper');
        $helper_user->refreshUsers();
        return true;
    }

    /**
     * triggered after a user is inserted using the insert function of the user model
     * @return boolean
     */
    function afterInsert($id){
        $helper_user=WYSIJA::get('user','helper');
        $helper_user->refreshUsers();

        do_action('wysija_subscriber_added', $id);
        return true;
    }

    /**
     * triggered after a user is updated using the u pdate function of the user model
     * @return boolean
     */
    function afterUpdate($id){
        $helper_user=WYSIJA::get('user','helper');
        $helper_user->refreshUsers();

        do_action('wysija_subscriber_modified', $id);
        return true;
    }


      /**
     * function used to generate the links if they are not valid anymore,
     * will be needed for old version of the plugin still using old unsafe links
     * @param int $user_id
     * @param int $email_id
     * @return string
     */
    function old_get_new_link_for_expired_links($user_id,$email_id){
        $params=array(
            'wysija-page'=>1,
            'controller'=>'confirm',
            'action'=>'resend',
            'user_id'=>$user_id,
            'email_id'=>$email_id
        );

        $model_config = WYSIJA::get('config','model');
        return WYSIJA::get_permalink($model_config->getValue('confirm_email_link'),$params);
    }
}
