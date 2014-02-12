<?php
defined('WYSIJA') or die('Restricted access');
class WYSIJA_control_back_subscribers extends WYSIJA_control_back{
    var $model='user';
    var $view='subscribers';
    var $list_columns=array('user_id','firstname', 'lastname','email','created_at');
    var $searchable=array('email','firstname', 'lastname');
    var $_separators = array(',', ';'); // csv separator; comma is for standard csv, semi-colon is good for Excel
    var $_default_separator = ';';

    function WYSIJA_control_back_subscribers(){

    }

    function save(){
        $this->redirectAfterSave=false;
        $helperUser=WYSIJA::get('user','helper');
        if(isset($_REQUEST['id'])){
            $id=$_REQUEST['id'];
            parent::save();

            //run the unsubscribe process if needed
            if((int)$_REQUEST['wysija']['user']['status']==-1){
                $helperUser->unsubscribe($id);
            }

            /* update subscriptions */
            $modelUL=WYSIJA::get('user_list','model');
            $modelUL->backSave=true;
            /* list of core list */
            $modelLIST=WYSIJA::get('list','model');
            $results=$modelLIST->get(array('list_id'),array('is_enabled'=>'0'));
            $core_listids=array();
            foreach($results as $res){
                $core_listids[]=$res['list_id'];
            }

            //0 - get current lists of the user
            $userlists=$modelUL->get(array('list_id','unsub_date'),array('user_id'=>$id));

            $oldlistids=$newlistids=array();
            foreach($userlists as $listdata)    $oldlistids[$listdata['list_id']]=$listdata['unsub_date'];

            $config=WYSIJA::get('config','model');
            $dbloptin=$config->getValue('confirm_dbleoptin');
            //1 - insert new user_list
            if(isset($_POST['wysija']['user_list']) && $_POST['wysija']['user_list']){
                $modelUL->reset();
                $modelUL->update(array('sub_date'=>time()),array('user_id'=>$id));
                if(!empty($_POST['wysija']['user_list']['list_id'])){
                    foreach($_POST['wysija']['user_list']['list_id'] as $list_id){
                        //if the list is not already recorded for the user then we will need to insert it
                        if(!isset($oldlistids[$list_id])){
                            $modelUL->reset();
                            $newlistids[]=$list_id;
                            $dataul=array('user_id'=>$id,'list_id'=>$list_id,'sub_date'=>time());
                            //if double optin is on and user is unconfirmed or unsubscribed, then we need to set it as unconfirmed subscription
                            if($dbloptin && (int)$_POST['wysija']['user']['status']<1)  unset($dataul['sub_date']);
                            $modelUL->insert($dataul);
                        //if the list is recorded already then let's check the status, if it is an unsubed one then we update it
                        }else{
                            if($oldlistids[$list_id]>0){
                                $modelUL->reset();
                                $modelUL->update(array('unsub_date'=>0,'sub_date'=>time()),array('user_id'=>$id,'list_id'=>$list_id));
                            }
                        }
                    }
                }

            }else{
                // if no list is selected we unsubscribe them all
                $modelUL->reset();
                $modelUL->update(array('unsub_date'=>time(),'sub_date'=>0),array('user_id'=>$id));
            }

            //if a confirmation email needs to be sent then we send it
            if($dbloptin && (int)$_POST['wysija']['user']['status']==0 && !empty($newlistids)){
                $hUser=WYSIJA::get('user','helper');
                $hUser->sendConfirmationEmail($id,true,$newlistids);
            }

            if((int)$_POST['wysija']['user']['status']==0 || (int)$_POST['wysija']['user']['status']==1){
                $modelUL->reset();
                $modelUL->update(array('unsub_date'=>0,'sub_date'=>time()),array('user_id'=>$id,'list_id'=>$core_listids));
            }

            $arrayLists=array();
            if(isset($_POST['wysija']['user_list']['list_id'])) $arrayLists=$_POST['wysija']['user_list']['list_id'];
            $notEqual=array_merge($core_listids, $arrayLists);

            //unsubscribe from lists which exist in the old list but does not exist in the new list
            $unsubsribe_list = array_diff(array_keys($oldlistids),$_POST['wysija']['user_list']['list_id']);
            if(!empty($unsubsribe_list))
            {
                $modelUL->reset();
                $modelUL->update(array('unsub_date'=>time()),array('user_id'=>$id,'list_id'=>$unsubsribe_list));
            }
            $modelUL->reset();
        }else{
            //instead of going through a classic save we should save through the helper
            $data=$_REQUEST['wysija'];
            $data['user_list']['list_ids'] = !empty($data['user_list']['list_id']) ? $data['user_list']['list_id'] : array();
            unset($data['user_list']['list_id']);
            $data['message_success']=__('Subscriber has been saved.',WYSIJA);
            $id=$helperUser->addSubscriber($data,true);
            //$id= parent::save();
            if(!$id) {
                $this->viewShow=$this->action='add';
                $data=array('details'=>$_REQUEST['wysija']['user']);
                return $this->add($data);
            }
        }
        $this->redirect();
        return true;
    }



    function defaultDisplay(){
        $this->viewShow=$this->action='main';
        $this->js[]='wysija-admin-list';
        $this->viewObj->msgPerPage = __('Subscribers per page:',WYSIJA);

        $this->jsTrans['selecmiss'] = __('Select at least 1 subscriber!',WYSIJA);

        // get the total count for subscribed, unsubscribed and unconfirmed users
        $select = array( 'count(user_id) as users' , 'status' , 'max(created_at) as max_create_at');
        $count_group_by = 'status';
        $count_by_status = $this->modelObj->get_subscribers( $select , array() , $count_group_by );

        $counts = $this->modelObj->structure_user_status_count_array($count_by_status);
        $arr_max_create_at = $this->modelObj->get_max_create($count_by_status);

        // count the rows based on the filters
        $filters = $this->modelObj->detect_filters();
        $select = array( 'COUNT(DISTINCT([wysija]user.user_id)) as total_users', 'MAX([wysija]user.created_at) as max_create_at');
        $count_rows = $this->modelObj->get_subscribers( $select, $filters );

        // without filter we already have the total number of subscribers
        $this->data['max_create_at'] = null; //max value of create_at field of current list of users
        if(!empty($filters)){
            // used for pagination
            $this->modelObj->countRows = $count_rows['total_users'];
            // used for
            $this->data['max_create_at'] = $count_rows['max_create_at'];
        }else{
            $this->data['max_create_at'] = !empty($arr_max_create_at) ? max($arr_max_create_at) : 0;
            $this->modelObj->countRows=$counts['all'];
        }

        $select = array( '[wysija]user.firstname', '[wysija]user.lastname', '[wysija]user.status', '[wysija]user.email', '[wysija]user.created_at', '[wysija]user_list.user_id' );

        $this->data['subscribers'] = $this->modelObj->get_subscribers($select , $filters);

        $this->data['current_counts'] = $this->modelObj->countRows;
        $this->data['show_batch_select'] = ($this->modelObj->limit >= $this->modelObj->countRows) ? false : true;
        $this->modelObj->reset();


        // make the data object for the listing view
        $model_list = WYSIJA::get('list','model');
        $lists_db = $model_list->getLists();

        $lists=array();

        foreach($lists_db as $listobj){
            $lists[$listobj['list_id']]=$listobj;
        }

        $user_ids=array();
        foreach($this->data['subscribers'] as $subscriber){
            $user_ids[]=$subscriber['user_id'];
        }

        // 3 - user_list request
        if($user_ids){
            $modeluList=WYSIJA::get('user_list','model');
            $userlists=$modeluList->get(array('list_id','user_id','unsub_date'),array('user_id'=>$user_ids));
        }

        $this->data['lists']=$lists;
        $this->data['counts']=array_reverse($counts);

        // regrouping all the data in the same array
       foreach($this->data['subscribers'] as $keysus=>$subscriber){
            // default key while we don't have the data
            //TODO add data for stats about emails opened clicked etc
            $this->data['subscribers'][$keysus]['emails']=0;
            $this->data['subscribers'][$keysus]['opened']=0;
            $this->data['subscribers'][$keysus]['clicked']=0;

            if($userlists){
                foreach($userlists as $key=>$userlist){
                    if($subscriber['user_id']==$userlist['user_id'] && isset($lists[$userlist['list_id']])){
                        //what kind of list ist it ? unsubscribed ? or not

                        if($userlist['unsub_date']>0){
                            if(!isset($this->data['subscribers'][$keysus]['unsub_lists']) ){
                                $this->data['subscribers'][$keysus]['unsub_lists']=$this->data['lists'][$userlist['list_id']]['name'];
                            }else{
                                $this->data['subscribers'][$keysus]['unsub_lists'].=', '.$this->data['lists'][$userlist['list_id']]['name'];
                            }
                       }else{
                            if(!isset($this->data['subscribers'][$keysus]['lists']) ){
                                $this->data['subscribers'][$keysus]['lists']=$this->data['lists'][$userlist['list_id']]['name'];
                            }else{
                                $this->data['subscribers'][$keysus]['lists'].=', '.$this->data['lists'][$userlist['list_id']]['name'];
                            }
                        }
                    }
                }
            }
        }
        if(!$this->data['subscribers']){
            $this->notice(__('Yikes! Couldn\'t find any subscribers.',WYSIJA));
        }

    }

    function main(){
         $this->messages['insert'][true]=__('Subscriber has been saved.',WYSIJA);
        $this->messages['insert'][false]=__('Subscriber has not been saved.',WYSIJA);
        $this->messages['update'][true]=__('Subscriber has been modified. [link]Edit again[/link].',WYSIJA);
        $this->messages['update'][false]=__('Subscriber has not been modified.',WYSIJA);
        parent::WYSIJA_control_back();

        //we change the default model of the controller based on the action
        if(isset($_REQUEST['action'])){
            switch($_REQUEST['action']){
                case 'listsedit':
                case 'savelist':
                case 'lists':
                    $this->model='list';
                    break;
                default:
                    $this->model='user';
            }
        }

        $this->WYSIJA_control();
        if(!isset($_REQUEST['action']) || !$_REQUEST['action']) {
            $this->defaultDisplay();
            $this->checkTotalSubscribers();
        }
        else $this->_tryAction($_REQUEST['action']);

    }

    /**
     * bulk action copy to list
     * @global type $wpdb
     * @param type $data
     */
    function copytolist($data){
        $helpU=WYSIJA::get('user','helper');
        if(empty($this->_batch_select))
            $helpU->addToList($data['listid'],$_POST['wysija']['user']['user_id']);
        else
            $helpU->addToList($data['listid'],$this->_batch_select, true);

        $modelL=WYSIJA::get('list','model');
        $result=$modelL->getOne(array('name'),array('list_id'=>$data['listid']));

        if($this->_affected_rows > 1)
            $this->notice(sprintf(__('%1$s subscribers have been added to "%2$s".',WYSIJA),$this->_affected_rows,$result['name']));
        else
            $this->notice(sprintf(__('%1$s subscriber have been added to "%2$s".',WYSIJA),$this->_affected_rows,$result['name']));
        $this->redirect('admin.php?page=wysija_subscribers&filter-list='.$data['listid']);
    }

    /**
     * bulk action move to list
     * @param type $data = array('list_id'=>?)
     */
    function movetolist($data){
        $helpU=WYSIJA::get('user','helper');
        if(!empty($this->_batch_select))
            $helpU->moveToList($data['listid'],$this->_batch_select, true);
        else
            $helpU->moveToList($data['listid'],$_POST['wysija']['user']['user_id']);

        $modelL=WYSIJA::get('list','model');
        $result=$modelL->getOne(array('name'),array('list_id'=>$data['listid']));

        if($this->_affected_rows > 1)
            $this->notice(sprintf(__('%1$s subscribers have been moved to "%2$s".',WYSIJA),$this->_affected_rows,$result['name']));
        else
            $this->notice(sprintf(__('%1$s subscriber have been moved to "%2$s".',WYSIJA),$this->_affected_rows,$result['name']));
        $this->redirect('admin.php?page=wysija_subscribers&filter-list='.$data['listid']);
    }

    /**
     * Bulk action remove subscribers from all existing lists
     * @param type $data = array('list_id'=>?)
     */
    function removefromalllists($data){
        $helpU=WYSIJA::get('user','helper');
        if(!empty($this->_batch_select))
            $helpU->removeFromLists(array(),$this->_batch_select, true);
        else
            $helpU->removeFromLists(array(),$_POST['wysija']['user']['user_id']);

        if($this->_affected_rows > 1)
            $this->notice(sprintf(__('%1$s subscribers have been removed from all exising lists.',WYSIJA),$this->_affected_rows));
        else
            $this->notice(sprintf(__('%1$s subscriber have been removed from all exising lists.',WYSIJA),$this->_affected_rows));
        $this->defaultDisplay();
    }

    /**
     * Bulk action remove subscribers from all existing lists
     * @param type $data = array('list_id'=>?)
     */
    function removefromlist($data = array()){
        $helpU=WYSIJA::get('user','helper');
        if(!empty($this->_batch_select))
            $helpU->removeFromLists(array($data['listid']),$this->_batch_select, true);
        else
            $helpU->removeFromLists(array($data['listid']),$_POST['wysija']['user']['user_id']);
        $modelL=WYSIJA::get('list','model');
        $result=$modelL->getOne(array('name'),array('list_id'=>$data['listid']));

        if($this->_affected_rows > 1)
            $this->notice(sprintf(__('%1$s subscribers have been removed from "%2$s".',WYSIJA),$this->_affected_rows, $result['name']));
        else
            $this->notice(sprintf(__('%1$s subscriber have been removed from "%2$s".',WYSIJA),$this->_affected_rows, $result['name']));
        $this->redirect('admin.php?page=wysija_subscribers&filter-list='.$data['listid']);
    }

    /**
     * Bulk confirm users
     */
    function confirmusers(){
        $helpU=WYSIJA::get('user','helper');
        if(!empty($this->_batch_select))
            $helpU->confirmUsers($this->_batch_select, true);
        else
            $helpU->confirmUsers($_POST['wysija']['user']['user_id']);

        if($this->_affected_rows > 1)
            $this->notice(sprintf(__('%1$s subscribers have been confirmed.',WYSIJA),$this->_affected_rows));
        else
            $this->notice(sprintf(__('%1$s subscriber have been confirmed.',WYSIJA),$this->_affected_rows));
        $this->defaultDisplay();
    }

    /**
     * bulk action copy to list
     * @global type $wpdb
     * @param type $data
     */
    /*function unsubscribemany(){
        $helperUser=WYSIJA::get('user','helper');
        foreach($_POST['wysija']['user']['user_id'] as $uid)    $helperUser->unsubscribe($uid,true);
        $count=count($_POST['wysija']['user']['user_id']);
        $this->notice(sprintf(__('%1$d Subscribers have been unsubscribed.',WYSIJA),$count));
        $this->redirect();
    }*/

    function lists(){
        $this->js[]='wysija-admin-list';
        $this->_commonlists();

        $this->modelObj=WYSIJA::get('list','model');
        $this->viewObj->title=__('Edit lists',WYSIJA);
        $this->modelObj->countRows=$this->modelObj->count();

        $this->viewObj->model=$this->modelObj;
        $this->data['form']=$this->_getForm();
    }

    function editlist(){
        $this->_commonlists();
        $this->data['form']=$this->_getForm($_REQUEST['id']);

        $this->viewObj->title=sprintf(__('Editing list %1$s',WYSIJA), '<b><i>'.$this->data['form']['name'].'</i></b>');
    }

    function addlist(){
        $this->_commonlists();
        $this->viewObj->title=__('How about a new list?',WYSIJA);
        $this->data['form']=$this->_getForm();
    }

    function duplicatelist(){

        /* get the list's email id
         * 0 duplicate the list's welcome email
         * 1 duplicate the list
         * 2 duplicate the list's subscribers
         */
        $model=WYSIJA::get('list','model');
        $data=$model->getOne(array('name','namekey','welcome_mail_id','unsub_mail_id'),array('list_id'=>(int)$_REQUEST['id']));

        $query='INSERT INTO `[wysija]email` (`created_at`,`campaign_id`,`subject`,`body`,`from_email`,`from_name`,`replyto_email`,`replyto_name`,`attachments`,`status`)
            SELECT '.time().',`campaign_id`,`subject`,`body`,`from_email`,`from_name`,`replyto_email`,`replyto_name`,`attachments`,`status` FROM [wysija]email
            WHERE email_id='.(int)$data['welcome_mail_id'];
        $emailWelcomeid=$model->query($query);


        $query='INSERT INTO `[wysija]email` (`created_at`,`campaign_id`,`subject`,`body`,`from_email`,`from_name`,`replyto_email`,`replyto_name`,`attachments`,`status`)
            SELECT '.time().',`campaign_id`,`subject`,`body`,`from_email`,`from_name`,`replyto_email`,`replyto_name`,`attachments`,`status` FROM [wysija]email
            WHERE email_id='.(int)$data['unsub_mail_id'];
        $emailUnsubid=$model->query($query);


        $query='INSERT INTO `[wysija]list` (`created_at`,`name`,`namekey`,`description`,`welcome_mail_id`,`unsub_mail_id`,`is_enabled`,`ordering`)
            SELECT '.time().',"'.stripslashes(__('Copy of ',WYSIJA)).$data['name'].'" ,"copy_'.$data['namekey'].time().'" ,`description`,'.$emailWelcomeid.','.$emailUnsubid.' ,1,`ordering` FROM [wysija]list
            WHERE list_id='.(int)$_REQUEST['id'];

        $listid=$model->query($query);

        $query='INSERT INTO `[wysija]user_list` (`list_id`,`user_id`,`sub_date`,`unsub_date`)
            SELECT '.$listid.',`user_id`,`sub_date`,`unsub_date` FROM [wysija]user_list
            WHERE list_id='.(int)$_REQUEST['id'];

        $model->query($query);

        $this->notice(sprintf(__('List "%1$s" has been duplicated.',WYSIJA),$data['name']));
        $this->redirect('admin.php?page=wysija_subscribers&action=lists');

    }

    function add($data=false){
        $this->js[]='wysija-validator';
        $this->viewObj->add=true;

        $this->title=$this->viewObj->title=__('Add Subscriber',WYSIJA);

        $this->data=array();
        $this->data['user']=false;
        if($data)$this->data['user']=$data;
        $modelList=WYSIJA::get('list','model');
        $modelList->limitON=false;
        $this->data['list']=$modelList->get(false,array('greater'=>array('is_enabled'=>'0') ));

    }

    function back(){
        $this->redirect();
    }

    function backtolist(){
        $this->redirect('admin.php?page=wysija_subscribers&action=lists');
    }

    function edit($id=false){

        if(isset($_REQUEST['id']) || $id){
            if(!$id) $id=$_REQUEST['id'];

            $this->js[]='wysija-validator';
            $this->js[]='wysija-charts';

            $this->data=array();
            $this->data['user']=$this->modelObj->getDetails(array('user_id'=>$id),true);
            if(!$this->data['user']){
                $this->notice(__('No subscriber found, most probably because he was deleted.',WYSIJA));
                return $this->redirect();
            }
            $model_list=WYSIJA::get('list','model');
            $model_list->limitON=false;
            $model_list->orderBy('is_enabled','DESC');
            $this->data['list']=$model_list->get(false,array('greater'=>array('is_enabled'=>'-1') ));

            //we prepare the data to be passed to the charts script
            $this->data['charts']['title']=' ';
            $this->data['charts']['stats']=array();

            //group email user stats by status where userid
            $model_email_user_stat=WYSIJA::get('email_user_stat','model');
            $model_email_user_stat->setConditions(array('equal'=>array('user_id'=>$id)));
            $query='SELECT count(email_id) as emails, status FROM `[wysija]'.$model_email_user_stat->table_name."`";
            $query.=$model_email_user_stat->makeWhere();
            $query.=' GROUP BY status';
            $grouped_counts=$model_email_user_stat->query('get_res',$query,ARRAY_A);

            //-2 is an automatic unsubscribed made through bounce processing
            $statuses=array('-1'=>__('Bounced',WYSIJA),'0'=>__('Unopened',WYSIJA),'1'=>__('Opened',WYSIJA),'2'=>__('Clicked',WYSIJA),'3'=>__('Unsubscribed',WYSIJA) ,'-2'=>__('Unsubscribed',WYSIJA));
            foreach($grouped_counts as $count){
                $this->data['charts']['stats'][]=array('name'=>$statuses[$count['status']],'number'=>$count['emails']);
            }

            //email_user_url
            $modelEUU=WYSIJA::get('email_user_url','model');
            $modelEUU->setConditions(array('equal'=>array('user_id'=>$id)));
            $query='SELECT A.*,B.*,C.subject as name FROM `[wysija]'.$modelEUU->table_name."` as A JOIN `[wysija]url` as B on A.url_id=B.url_id JOIN `[wysija]email` as C on C.email_id=A.email_id ";
            $query.=$model_email_user_stat->makeWhere();
            $query.=' ORDER BY A.number_clicked DESC ';
            $this->data['clicks']=$model_email_user_stat->query('get_res',$query,ARRAY_A);

            foreach($this->data['clicks'] as $k => &$v){
                $v['url']=urldecode(utf8_encode($v['url']));
            }

            $chartsencoded=base64_encode(json_encode($this->data['charts']));
            wp_enqueue_script('wysija-admin-subscribers-edit-manual', WYSIJA_URL.'js/admin-subscribers-edit-manual.php?data='.$chartsencoded, array( 'wysija-charts' ), true);

            $this->viewObj->title=__('Edit',WYSIJA).' '.$this->data['user']['details']['email'];

        }else{
            $this->error('Cannot edit element primary key is missing : '. get_class($this));
        }

    }

    function deletelist(){
        $this->requireSecurity();

        /* get the list's email id
         * 0 delete the welcome email corresponding to that list
         * 1 delete the list subscribers reference
         * 2 delete the list campaigns references
         * 4 delete the list
         */
        $model_list=WYSIJA::get('list','model');
        $data=$model_list->getOne(array('name','namekey','welcome_mail_id'),array('list_id'=>(int)$_REQUEST['id']));

        if($data && isset($data['namekey']) && ($data['namekey']!='users')){

            //there is no welcome email per list that's old stuff
            $model_user_list=WYSIJA::get('user_list','model');
            $model_user_list->delete(array('list_id'=>$_REQUEST['id']));

            $model_campaign_list=WYSIJA::get('campaign_list','model');
            $model_campaign_list->delete(array('list_id'=>$_REQUEST['id']));

            $model_list->reset();
            $model_list->delete(array('list_id'=>$_REQUEST['id']));

            $this->notice(sprintf(__('List "%1$s" has been deleted.',WYSIJA),$data['name']));
        }else{
            $this->error(__('The list does not exists or cannot be deleted.',WYSIJA),true);
        }

        $this->redirect('admin.php?page=wysija_subscribers&action=lists');

    }


    function synchlist(){
        $this->requireSecurity();

        $helper_user=WYSIJA::get('user','helper');
        $helper_user->synchList($_REQUEST['id']);

        $this->redirect('admin.php?page=wysija_subscribers&action=lists');
    }

    function synchlisttotal(){
        $this->requireSecurity();

        global $current_user;

        if(is_multisite() && is_super_admin( $current_user->ID )){
            $helper_user=WYSIJA::get('user','helper');
            $helper_user->synchList($_REQUEST['id'],true);
        }

        $this->redirect('admin.php?page=wysija_subscribers&action=lists');
    }


    function savelist(){
        $this->_resetGlobMsg();
        $update=false;

        if($_REQUEST['wysija']['list']['list_id']) $update=true;
        /* save the result */
        /* 1-save the welcome email*/
        /* 2-save the list*/
        if(isset($_REQUEST['wysija']['list']['is_public'])){
            if($_REQUEST['wysija']['list']['is_public']=='on')$_REQUEST['wysija']['list']['is_public']=1;
            else $_REQUEST['wysija']['list']['is_public']=0;
        }else{
            $_REQUEST['wysija']['list']['is_public']=0;
        }

        if($update){
            $this->modelObj->update($_REQUEST['wysija']['list']);
            $this->notice(__('List has been updated.',WYSIJA));
        }else{
            $_REQUEST['wysija']['list']['created_at']=time();
            $_REQUEST['wysija']['list']['is_enabled']=1;

            $this->modelObj->insert($_REQUEST['wysija']['list']);
            $this->notice(__('Your brand-new list awaits its first subscriber.',WYSIJA));
        }


        $this->redirect('admin.php?page=wysija_subscribers&action=lists');
    }



    function importpluginsave($id=false){
        $this->requireSecurity();
        $this->_resetGlobMsg();
        $model_config=WYSIJA::get('config','model');
        $helper_import=WYSIJA::get('import','helper');
        $plugins_importable=$model_config->getValue('pluginsImportableEgg');
        $plugins_imported=array();
        foreach($_REQUEST['wysija']['import'] as $table_name =>$result){
            $connection_info=$helper_import->getPluginsInfo($table_name);

            if($result){
                $plugins_imported[]=$table_name;
                if(!$connection_info) $connection_info=$plugins_importable[$table_name];
                $helper_import->import($table_name,$connection_info);
                sleep(2);
                $this->notice(sprintf(__('Import from plugin %1$s has been completed.',WYSIJA),"<strong>'".$connection_info['name']."'</strong>"));
            }else{
                $this->notice(sprintf(__('Import from plugin %1$s has been cancelled.',WYSIJA),"<strong>'".$connection_info['name']."'</strong>"));
            }

        }

        $model_config->save(array('pluginsImportedEgg'=>$plugins_imported));

        $this->redirect('admin.php?page=wysija_subscribers&action=lists');
    }

    function importplugins($id=false){
        $this->js[]='wysija-validator';

        $this->viewObj->title=__('Import subscribers from plugins',WYSIJA);

        $model_config=WYSIJA::get('config','model');

        $this->data=array();
        $this->data['plugins']=$model_config->getValue('pluginsImportableEgg');
        $imported_plugins=$model_config->getValue('pluginsImportedEgg');

        if($imported_plugins){
            foreach($imported_plugins as $tablename){
                unset( $this->data['plugins'][$tablename]);
            }
        }


        if(!$this->data['plugins']){
            $this->notice(__('There is no plugin to import from.',WYSIJA));
            return $this->redirect();
        }
        $this->viewShow='importplugins';

    }

    function import($id=false){
        $this->js[]='wysija-validator';
        $this->viewObj->title=__('Import Subscribers',WYSIJA);
        $this->viewShow='import';
    }

    function importmatch(){
        $this->js[] = 'wysija-validator';
        $helper_numbers = WYSIJA::get('numbers','helper');
        $bytes = $helper_numbers->get_max_file_upload();

        if(isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH']>$bytes['maxbytes']){
            if(isset($_FILES['importfile']['name']) && $_FILES['importfile']['name']){
                $file_name = $_FILES['importfile']['name'];
            }else{
                $file_name = __('which you have pasted',WYSIJA);
            }

            $this->error(sprintf(__('Upload error, file %1$s is too large! (MAX:%2$s)',WYSIJA) , $file_name , $bytes['maxmegas']),true);
            $this->redirect('admin.php?page=wysija_subscribers&action=import');
            return false;
        }

        $import = new WJ_Import();
        $this->data = $import->scan_csv_file();

        if($this->data === false) $this->redirect('admin.php?page=wysija_subscribers&action=import');

        $this->viewObj->title=__('Import Subscribers',WYSIJA);
        $this->viewShow='importmatch';

    }

    /**
     *
     * @param type $input
     * @param type $rowstoread
     * @param type $delimiter
     * @param type $enclosure
     * @param type $linedelimiter
     * @return array
     */
    function _csvToArray($input,$rowstoread=0 , $delimiter=',',$enclosure='',$linedelimiter="\n"){
        $header = null;
        $data = array();

        $csvData = explode($linedelimiter,$input);
        $i=1;
        foreach($csvData as $csvLine){
            if($rowstoread!=0 && $i>$rowstoread) return $data;

            /* str_getcsv only exists in php5 ...*/
            if(!function_exists("str_getcsv")){
                $data[]= $this->csv_explode($csvLine, $delimiter,$enclosure);
            }else{
               $data[] = str_getcsv($csvLine, $delimiter,$enclosure);
            }

            $i++;
        }

        return $data;
    }

    function csv_explode($str,$delim, $enclose, $preserve=false){
      $resArr = array();
      $n = 0;
      if(empty($enclose)){
          $resArr = explode($delim, $str);
      }else{
          $expEncArr = explode($enclose, $str);
          foreach($expEncArr as $EncItem){
            if($n++%2){
              array_push($resArr, array_pop($resArr) . ($preserve?$enclose:'') . $EncItem.($preserve?$enclose:''));
            }else{
              $expDelArr = explode($delim, $EncItem);
              array_push($resArr, array_pop($resArr) . array_shift($expDelArr));
              $resArr = array_merge($resArr, $expDelArr);
            }
          }
      }

      return $resArr;
    }


    function import_save(){
        @ini_set('max_execution_time',0);

        $this->requireSecurity();
        $this->_resetGlobMsg();

        //we need to save a new list in that situation
        if(!empty($_REQUEST['wysija']['list']['newlistname'])){
            $model_list = WYSIJA::get('list','model');
            $data_list = array();
            $data_list['is_enabled'] = 1;
            $data_list['name'] = $_REQUEST['wysija']['list']['newlistname'];
            $_REQUEST['wysija']['user_list']['list'][] = $model_list->insert($data_list);
        }

        //if there is no list selected, we return to the same form prompting the user to take action
        if(!isset($_REQUEST['wysija']['user_list']['list']) || !$_REQUEST['wysija']['user_list']['list']){
            $this->error(__('You need to select at least one list.',WYSIJA),true);
            return $this->importmatch();
        }

        $import = new WJ_Import();
        $data_numbers = $import->import_subscribers();
        $duplicate_emails_count = $import->get_duplicate_emails_count();

        if($data_numbers === false){
            return $this->redirect('admin.php?page=wysija_subscribers&action=import');
        }

        //get a list of list name
        $model=WYSIJA::get('list','model');
        $results=$model->get(array('name'),array('list_id'=>$_REQUEST['wysija']['user_list']['list']));

        $listnames=array();
        foreach($results as $k =>$v) $listnames[]=$v['name'];

        $this->notice(sprintf(__('%1$s subscribers added to %2$s.', WYSIJA),
                    $data_numbers['list_user_ids'],
                    '"'.implode('", "',$listnames).'"'
                    ));

        if(count($duplicate_emails_count)>0){
            $list_emails = '';
            $i = 0;
            foreach($duplicate_emails_count as $email_address => $occurences){
                if( $i > 0 )$list_emails.=', ';
                $list_emails.= $email_address.' ('.$occurences.')';
                $i++;
            }
            //$emailsalreadyinserted=array_keys($emailsCount);
            $this->notice(sprintf(__('%1$s emails appear more than once in your file : %2$s.',WYSIJA),count($duplicate_emails_count),$list_emails),0);
        }

        if(count($data_numbers['invalid'])>0){
            $this->notice(sprintf(__('%1$s emails are not valid : %2$s.',WYSIJA),count($data_numbers['invalid']),implode(', ',$data_numbers['invalid'])),0);
        }

        $this->redirect();
    }


    function export(){
        $this->js[]='wysija-validator';

        $this->viewObj->title=__('Export Subscribers',WYSIJA);
        $this->data=array();
        //$this->data['lists']=$this->_getLists();
        $this->data['lists']=$modelList=WYSIJA::get('list','model');
        $listsDB=$modelList->getLists();

        $lists=array();

        foreach($listsDB as $listobj){
            $lists[$listobj['list_id']]=$listobj;
        }
        $this->data['lists']=$lists;

        $this->viewShow='export';
    }

    function exportcampaign(){
        if(isset($_REQUEST['file_name'])){
            $content=file_get_contents(base64_decode($_REQUEST['file_name']));
            $user_ids=explode(",",$content);
        }
        $_REQUEST['wysija']['user']['user_id']=$user_ids;

        $this->exportlist();
    }

    function exportlist(){

        if(!empty($_REQUEST['wysija']['user']['force_select_all'])){

            $select = array( 'COUNT(DISTINCT([wysija]user.user_id)) as total_users');
            if(!empty($_REQUEST['wysija']['filter']['filter_list'])){
                $select[] =  '[wysija]user_list.list_id';
            }

            // filters for unsubscribed
            $filters = $this->modelObj->detect_filters();

            $count = $this->modelObj->get_subscribers( $select, $filters );
            $number = $count['total_users'];
        } else {
            $number = count($_REQUEST['wysija']['user']['user_id']);
        }

        $this->viewObj->title = sprintf(__('Exporting %1$s subscribers',WYSIJA),$number);
        $this->data=array();

        $this->data['subscribers'] = $_REQUEST['wysija']['user']['user_id'];
        $this->data['user'] = $_REQUEST['wysija']['user'];//for batch-selecting

        if(!empty($_REQUEST['search']))  $_REQUEST['wysija']['filter']['search'] = $_REQUEST['search'];

        $this->data['filter'] = $_REQUEST['wysija']['filter'];//for batch-selecting
        $this->viewShow = 'export';
    }



    function sendconfirmation(){
        $helperUser=WYSIJA::get('user','helper');
        $helperUser->sendConfirmationEmail($_POST['wysija']['user']['user_id']);
        $this->redirect();
    }

    /**
     * bulk delete option
     */
    function deleteusers(){
        $helper_user=WYSIJA::get('user','helper');
        if(!empty($this->_batch_select))
            $helper_user->delete($this->_batch_select, false, true);
        else
            $helper_user->delete($_POST['wysija']['user']['user_id']);
        if($this->_affected_rows > 1)
            $this->notice(sprintf(__(' %1$s subscribers have been deleted.',WYSIJA),$this->_affected_rows));
        else
            $this->notice(sprintf(__(' %1$s subscriber have been deleted.',WYSIJA),$this->_affected_rows));

        // make sure the total count of subscribers is updated
        $helper_user->refreshUsers();
        $this->redirect();
    }

     /**
     * function generating an export file based on an array of user_ids
     */
    function export_get(){
        @ini_set('max_execution_time',0);

        $export = new WJ_Export();

        if(!empty($this->_batch_select))    $export->batch_select = $this->_batch_select;

        $file_path_result = $export->export_subscribers();

        $url=get_bloginfo('wpurl').'/wp-admin/admin.php?page=wysija_subscribers&action=exportedFileGet&file='.base64_encode($file_path_result);
        $this->notice(str_replace(
                array('[link]','[/link]'),
                array('<a href="'.$url.'" target="_blank" class="exported-file" >','</a>'),
                sprintf(__('%1$s subscribers were exported. Get the exported file [link]here[/link].',WYSIJA),$export->get_user_ids_rows())));

        if(isset($_REQUEST['camp_id'])){
            $this->redirect('admin.php?page=wysija_campaigns&action=viewstats&id='.$_REQUEST['camp_id']);
        }else{
            $this->redirect();
        }
    }

    function exportedFileGet(){
        if(isset($_REQUEST['file'])){
            $helper=WYSIJA::get('file','helper');
            $helper->send(base64_decode($_REQUEST['file']));
        }
    }






    /*
     * common task to all the list actions
     */
    function _commonlists(){
        $this->js[]='wysija-validator';

        $this->data=array();
        $this->data['list']=$this->_getLists(10);

    }

    function _getLists($limit=false){

        $modelList=WYSIJA::get('list','model');
        $modelList->escapingOn=true;
        $modelList->_limitison=$limit;
        return $modelList->getLists();
    }

    function _getForm($id=false){
        if($id){
            $model_list=WYSIJA::get('list','model');

            return $model_list->get_one_list($id);
        }else{
            $array=array('name'=>'','list_id'=>'','description'=>'','is_public'=>true,'is_enabled'=>true);
            return $array;
        }

    }
}
