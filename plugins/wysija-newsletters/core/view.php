<?php
defined('WYSIJA') or die('Restricted access');
class WYSIJA_view extends WYSIJA_object{

    var $title="DEFAULT TITLE";
    var $icon="icon-edit";
    var $links=array();
    var $search=array();
    var $cols_nks=array();//correspondance between user_id and user-id once processed

    function WYSIJA_view(){

    }



    function renderErrorInstall(){
        $this->title=__("Your server's configuration doesn't allow us to complete Wysija's Installation!",WYSIJA);
        $this->header();
        $this->footer();
    }

    /**
     *
     * @param type $type
     * @param type $data
     */
    function render($type,$data){
        $this->action=$type;
        $this->header($data);
        if($type !== NULL) {
            $this->$type($data);
        }
        $this->footer();
    }

    /**
     * display all the messages that have queued
     * @global type $wysija_msg
     */
    function messages($noglobal=false){
        $wysija_msg = $this->getMsgs();

        if(isset($wysija_msg['g-updated'])) {
           if(!$noglobal) {
               if(isset($wysija_msg['updated']))  $wysija_msg['updated']=array_merge((array)$wysija_msg['updated'], $wysija_msg['g-updated']);
               else $wysija_msg['updated']= $wysija_msg['g-updated'];
           }
           unset($wysija_msg['g-updated']);
        }
        if(isset($wysija_msg['g-error'])) {
           if(!$noglobal){
               if(isset($wysija_msg['error']))  $wysija_msg['error']=array_merge((array)$wysija_msg['error'], $wysija_msg['g-error']);
               else $wysija_msg['error']= $wysija_msg['g-error'];
           }
           unset($wysija_msg['g-error']);
        }

        $wpnonce='<input type="hidden" value="'.wp_create_nonce('wysija_ajax').'" id="wysijax" />';
        if(!$wysija_msg) return '<div class="wysija-msg ajax"></div>'.$wpnonce;
        $html='<div class="wysija-msg">';

            foreach($wysija_msg as $level =>$messages){
                $msg_class = '';
                switch($level){
                    case 'updated':
                        $msg_class = 'notice-msg';
                        break;
                    case 'error':
                        $msg_class = 'error-msg';
                        break;
                }

                $html.='<div class="'.$msg_class.'">';
                $html.='<ul>';

                if(count($messages)>0){
                   foreach($messages as $msg){
                         $html.='<li>'.$msg.'</li>';
                    }
                }


                $html.='</ul>';
                $html.='</div>';
            }

        $html.='</div><div class="wysija-msg ajax"></div>'.$wpnonce;

        return $html;
    }

    /**
     * this function let us generate a nonce which is an encrypted unique word based n the user info and some other stuff.
     * by default it will create an hidden input nonce field
     * @param type $params
     * @param type $get
     * @return type
     */
    function secure($params=array(),$get=false,$echo=true){
        $controller='';
        if(!is_array($params)) $action=$params;
        else{
            $action=$params['action'];
            if(isset($params['controller']))    $controller=$params['controller'];
            elseif(isset($_REQUEST['page'])) $controller=$_REQUEST['page'];
        }
        $nonceaction=$controller.'-action_'.$action;

        if(is_array($params) && isset($params['id']) && $params['id']) $nonceaction.='-id_'.$params['id'];

        if($get){
            return wp_create_nonce($nonceaction);
        }else{
            return wp_nonce_field($nonceaction,'_wpnonce',true,$echo);
        }

    }

    /**
     * this allows us to get a field class to be validated by when making a form field
     * @param type $params
     * @param string $prefixclass
     * @return string
     */
    function getClassValidate($params,$returnAttr=false,$prefixclass=""){
        $class_validate = '';
        $recognised_types = array('email','url');

        if(isset($params['req'])){
            $class_validate='required';
            if(isset($params['type']) && in_array($params['type'], $recognised_types))  {
                $class_validate.=',custom['.$params['type'].']';
            }
        }else{
           if(isset($params['type']) && in_array($params['type'],$recognised_types ))  {
                $class_validate.='custom['.$params['type'].']';
            }
        }

        if($prefixclass) $prefixclass.=' ';
        if($class_validate) $class_validate='validate['.$class_validate.']';
        if(!$returnAttr && $class_validate)  $class_validate= ' class="'.$prefixclass.$class_validate.'" ';

        return $class_validate;
    }
}
