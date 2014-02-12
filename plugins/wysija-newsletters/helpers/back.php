<?php
defined('WYSIJA') or die('Restricted access');


/**
 * class managing the admin vital part to integrate wordpress
 */
class WYSIJA_help_back extends WYSIJA_help{

    function WYSIJA_help_back(){
        parent::WYSIJA_help();
        //check that the application has been installed properly
        $config=WYSIJA::get('config','model');

        define('WYSIJA_DBG',(int)$config->getValue('debug_new'));
        //by default do not show the errors until we get into the debug file
        if(!defined('WP_DEBUG') || !WP_DEBUG){
            error_reporting(0);
            ini_set('display_errors', '0');
        }



        //the controller is backend is it from our pages or from wordpress?
        //are we pluging-in to wordpress interfaces or doing entirely our own page?
        if(isset($_GET['page']) && substr($_GET['page'],0,7)=='wysija_'){
            define('WYSIJA_ITF',TRUE);
            $this->controller=WYSIJA::get(str_replace('wysija_','',$_GET['page']),'controller');
        }else{//check if we are pluging in wordpress interface
            define('WYSIJA_ITF',FALSE);
        }

        if( WYSIJA_DBG>0 ) include_once(WYSIJA_INC.'debug.php');

        if(!function_exists('dbg')) {
            function dbg($mixed,$exit=true){}
        }


        //we set up the important hooks for backend: menus js css etc
        if(defined('DOING_AJAX')){
            //difference between frontend and backend

            add_action( 'after_setup_theme', array($this, 'ajax_setup') );

        }else{
            if(WYSIJA_ITF)  {
                add_action('admin_init', array($this->controller, 'main'));
                if(!isset($_REQUEST['action']) || (isset($_REQUEST['action']) && $_REQUEST['action'] !== 'editTemplate')) {
                    add_action('admin_footer',array($this,'version'),9);
                }

                add_action('after_setup_theme',array($this,'resolveConflicts'));
            }
            //this is a fix for qtranslate as we were loading translatable string quite early


            //somehow if we add caps to one role the user with that role doesnt get its caps updated ...
            add_action('after_setup_theme', array('WYSIJA', 'update_user_caps'),11);
            add_action('admin_menu', array($this, 'define_translated_strings'),98);
            add_action('admin_menu', array($this, 'add_menus'),99);
            add_action('admin_enqueue_scripts',array($this, 'add_js'),10,1);


            //add specific page script
            add_action('admin_head-post-new.php',array($this,'addCodeToPagePost'));
            add_action('admin_head-post.php',array($this,'addCodeToPagePost'));

            //make sure that admin and super admin always have the highest access
             $wptools = WYSIJA::get('wp_tools', 'helper');
             $wptools->set_default_rolecaps();

             //code disabling updates for premium users who dont have WJP activated
            if($config->getValue('premium_key') && !WYSIJA::is_plugin_active('wysija-newsletters-premium/index.php')){

                if(file_exists(WYSIJA_PLG_DIR.'wysija-newsletters-premium'.DS.'index.php')){
                    //send a message to the user so that he activates the premium plugin or try to fetch it directly.
                    $this->notice('<p>'.__('You need to activate the Wysija Premium plugin.',WYSIJA).' <a id="install-wjp" class="button-primary"  href="admin.php?page=wysija_campaigns&action=install_wjp">'.__('Activate now',WYSIJA).'</a></p>');
                }else{
                    //send a message to the user so that he gets the premium plugin or try to fetch it directly.
                    $this->notice('<p>'.__('Congrats, your Premium license is active. One last step...',WYSIJA).' <a id="install-wjp" class="button-primary"  href="admin.php?page=wysija_campaigns&action=install_wjp">'.__('Install the Premium plugin.',WYSIJA).'</a></p>');
                }

                $this->controller->jsTrans['instalwjp']='Installing Wysija Newsletter Premium plugin';

            }
        }

        //if the comment form option is activated then we add an approval action
        if($config->getValue('commentform')){
            add_action('wp_set_comment_status',  array($this,'comment_approved'), 60,2);
        }

        // if the beta mode is on we look for the updates on a different server
        if($config->getValue('beta_mode')){
            $package_helper = WYSIJA::get('package', 'helper');
            $package_helper->set_package('wysija-newsletters');
        }



    }

    function comment_approved($cid,$comment_status){
        //if the comment is approved and the meta wysija_comment_subscribe appears, then we have one subscriber more to add
        $metaresult=get_comment_meta($cid, 'wysija_comment_subscribe', true);

        if($comment_status=='approve' && get_comment_meta($cid, 'wysija_comment_subscribe', true)){
            $mConfig=WYSIJA::get('config','model');
            $comment = get_comment($cid);
            $userHelper=WYSIJA::get('user','helper');
            $data=array('user'=>array('email'=>$comment->comment_author_email,'firstname'=>$comment->comment_author),'user_list'=>array('list_ids'=>$mConfig->getValue('commentform_lists')));
            $userHelper->addSubscriber($data);
        }
    }

    function ajax_setup(){
        if(!isset($_REQUEST['adminurl']) && !is_user_logged_in())    add_action('wp_ajax_nopriv_wysija_ajax', array($this, 'ajax'));
        else    add_action('wp_ajax_wysija_ajax', array($this, 'ajax'));
    }


    /**
     * let's fix all the conflicts that we may have
     */
    function resolveConflicts(){

        // check conflicting themes
        $possibleConflictiveThemes = $this->controller->get_conflictive_plugins(true);

        $conflictingTheme = null;
        $currentTheme = strtolower(function_exists( 'wp_get_theme' ) ? wp_get_theme() : get_current_theme());
        foreach($possibleConflictiveThemes as $keyTheme => $conflictTheme) {
            if($keyTheme === $currentTheme) {
                $conflictingTheme = $keyTheme;
            }
        }

        // if the current theme is known to make troubles, let's resolve this
        if($conflictingTheme !== null) {
            $helperConflicts = WYSIJA::get('conflicts', 'helper');
            $helperConflicts->resolve(array($possibleConflictiveThemes[$conflictingTheme]));
        }

        // check conflicting plugins
        $possibleConflictivePlugins=$this->controller->get_conflictive_plugins();

        $conflictingPlugins=array();
        foreach($possibleConflictivePlugins as $keyPlg => $conflictPlug){
            if(WYSIJA::is_plugin_active($conflictPlug['file'])) {
                //plugin is activated
                $conflictingPlugins[$keyPlg]=$conflictPlug;
            }
        }

        if($conflictingPlugins){
            $helperConflicts=WYSIJA::get('conflicts','helper');
            $helperConflicts->resolve($conflictingPlugins);
        }
    }

    /**
     * translatable strings need to be not loaded to early, this is why we put them ina separate function
     * @global type $wysija_installing
     */
    function define_translated_strings(){
        $config=WYSIJA::get('config','model');
        $linkcontent=__("It doesn't always work the way we want it to, doesn't it? We have a [link]dedicated support website[/link] with documentation and a ticketing system.",WYSIJA);
        $finds=array('[link]','[/link]');
        $replace=array('<a target="_blank" href="http://support.mailpoet.com" title="support.mailpoet.com">','</a>');
        $truelinkhelp='<p>'.str_replace($finds,$replace,$linkcontent).'</p>';

        $extra='<a href="admin.php?page=wysija_config&scroll_to=beta_mode_setting#tab-advanced" title="'.__('Switch to beta',WYSIJA).'">'.__('Switch to beta',WYSIJA).'</a>';

        $truelinkhelp.='<p>'.str_replace($finds,$replace,$extra).'</p>';

        $truelinkhelp.='<p>'.__('Wysija Version: ',WYSIJA).'<strong>'.WYSIJA::get_version().'</strong></p>';

        $this->menus=array(
            'campaigns'=>array('title'=>'Wysija'),
            'subscribers'=>array('title'=>__('Subscribers',WYSIJA)),
            'config'=>array('title'=>__('Settings',WYSIJA)),
            //"support"=>array("title"=>__("Support",WYSIJA))
        );
        $this->menuHelp=$truelinkhelp;

        if($config->getValue('queue_sends_slow')){
            $msg=$config->getValue('ignore_msgs');
            if(!isset($msg['queuesendsslow'])){
                $this->notice(
                        __('Tired of waiting more than 48h to send your emails?',WYSIJA).' '. str_replace(array('[link]','[/link]'), array('<a href="http://support.mailpoet.com/knowledgebase/how-fast-can-i-send-emails-optimal-sending-configurations-explained/?utm_source=wpadmin&utm_campaign=slowqueue" target="_blank">','</a>'), __('[link]Find out[/link] how you can improve this.',WYSIJA)).
                        ' <a class="linkignore queuesendsslow" href="javascript:;">'.__('Hide!',WYSIJA).'</a>');
            }
        }


        if(WYSIJA_ITF){
            global $wysija_installing;
            if( !$config->getValue('sending_emails_ok')){
                $msg=$config->getValue('ignore_msgs');

                $urlsendingmethod='admin.php?page=wysija_config#tab-sendingmethod';
                if($_REQUEST['page'] === 'wysija_config') {
                    $urlsendingmethod='#tab-sendingmethod';
                }

            }
        }
    }


    function add_menus(){

        $modelC=WYSIJA::get('config','model');
        $count=0;

        //WordPress globals be careful there
        global $menu,$submenu;



        //anti conflicting menus code to make sure that another plugin is not at the same level as us
        $position=50;
        $positionplus1=$position+1;

        while(isset($menu[$position]) || isset($menu[$positionplus1])){
            $position++;
            $positionplus1=$position+1;
            //check that there is no menu at our level neither at ourlevel+1 because that will make us disappear in some case :/
            if(!isset($menu[$position]) && isset($menu[$positionplus1])){
                $position=$position+2;
            }
        }

        global $wysija_installing;
        foreach($this->menus as $action=> $menutemp){
            $actionFull='wysija_'.$action;
            if(!isset($menutemp['subtitle'])) $menutemp['subtitle']=$menutemp['title'];
            if($action=='campaigns')    $roleformenu='wysija_newsletters';
            elseif($action=='subscribers')    $roleformenu='wysija_subscribers';
            else $roleformenu='wysija_config';

            if($wysija_installing===true){
                if($count==0){
                    $parentmenu=$actionFull;
                    $hookname=add_menu_page($menutemp['title'], $menutemp['subtitle'], $roleformenu, $actionFull , array($this->controller, 'errorInstall'), WYSIJA_EDITOR_IMG.'mail.png', $position);
                }
            }else{
                if($count==0){
                    $parentmenu=$actionFull;
                    $hookname=add_menu_page($menutemp['title'], $menutemp['subtitle'], $roleformenu, $actionFull , array($this->controller, 'render'), WYSIJA_EDITOR_IMG.'mail.png', $position);
                }else{
                    $hookname=add_submenu_page($parentmenu,$menutemp['title'], $menutemp['subtitle'], $roleformenu, $actionFull , array($this->controller, 'render'));
                }

                //manage wp help tab
                if(WYSIJA_ITF){
                    //wp3.3
                    if(version_compare(get_bloginfo('version'), '3.3.0')>= 0){
                        add_action('load-'.$hookname, array($this,'add_help_tab'));
                    }else{
                        //wp3.0
                        add_contextual_help($hookname, $this->menuHelp);
                    }
                }
            }
            $count++;
        }

        if(isset($submenu[$parentmenu])){
            if($submenu[$parentmenu][0][2]=="wysija_subscribers") $textmenu=__('Subscribers',WYSIJA);
            else $textmenu=__('Newsletters',WYSIJA);
            $submenu[$parentmenu][0][0]=$submenu[$parentmenu][0][3]=$textmenu;
        }

    }

    function add_help_tab($params){
        $screen = get_current_screen();

        if(method_exists($screen, "add_help_tab")){
            $screen->add_help_tab(array(
            'id'	=> 'wysija_help_tab',
            'title'	=> __('Get Help!',WYSIJA),
            'content'=> $this->menuHelp));
            $tabfunc=true;
        }
    }

    function add_js($hook) {
        //needed in all the wordpress admin pages including wysija's ones

        $jstrans=array();
        wp_register_script('wysija-charts', 'https://www.google.com/jsapi', array( 'jquery' ), true);
        wp_register_script('wysija-admin-list', WYSIJA_URL.'js/admin-listing.js', array( 'jquery' ), true, WYSIJA::get_version());
        wp_register_script('wysija-base-script-64', WYSIJA_URL.'js/base-script-64.js', array( 'jquery' ), true, WYSIJA::get_version());


        wp_enqueue_style('wysija-admin-css-widget', WYSIJA_URL.'css/admin-widget.css',array(),WYSIJA::get_version());

        // If Cron enabled sending, send Mixpanel data and reset flag.
        $model_config = WYSIJA::get('config', 'model');
        if ($model_config->getValue('send_analytics_now') == 1) {
            $analytics = new WJ_Analytics();
            $analytics->generate_data();
            $analytics->send();
            // Reset sending flag.
            $model_config->save(array('send_analytics_now' => 0));
        }


        //we are in wysija's admin interface
        if(WYSIJA_ITF){
            wp_enqueue_style('wysija-admin-css-global', WYSIJA_URL.'css/admin-global.css',array(),WYSIJA::get_version());
            wp_enqueue_script('wysija-admin-js-global', WYSIJA_URL.'js/admin-wysija-global.js',array(),WYSIJA::get_version());
            $pagename=str_replace('wysija_','',$_REQUEST['page']);
            $backloader=WYSIJA::get('backloader','helper');
            $backloader->initLoad($this->controller);

            //$this->controller->jsTrans["ignoremsg"]=__('Are you sure you want to ignore this message?.',WYSIJA);
            $jstrans=$this->controller->jsTrans;
            //if(!in_array('wysija-admin-ajax-proto',$this->controller->js)) $this->controller->js[]='wysija-admin-ajax';

            $jstrans['gopremium']=__('Go Premium!',WYSIJA);

            //enqueue all the scripts that have been declared in the controller
            $backloader->jsParse($this->controller,$pagename,WYSIJA_URL);

            //this will load automatically existing scripts and stylesheets based on the page and action parameters
            $backloader->loadScriptsStyles($pagename,WYSIJA_DIR,WYSIJA_URL,$this->controller);

            //add some translation
            $backloader->localize($pagename,WYSIJA_DIR,WYSIJA_URL,$this->controller);

            // add rtl support
            if ( is_rtl() ) {
                wp_enqueue_style('wysija-admin-rtl', WYSIJA_URL.'css/rtl.css',array(),WYSIJA::get_version());
            }

        }
            $jstrans['newsletters']=__('Newsletters',WYSIJA);
            $jstrans['urlpremium']='admin.php?page=wysija_config#tab-premium';
            if(isset($_REQUEST['page']) && $_REQUEST['page']=='wysija_config'){
                $jstrans['urlpremium']='#tab-premium';
            }
            wp_localize_script('wysija-admin', 'wysijatrans', $jstrans);
    }

    /**
     * code only executed in the page or post in admin
     */
    function addCodeToPagePost(){

        //code to add external buttons to the tmce only if the user has the rights to add the forms
        if(current_user_can('wysija_subscriwidget') &&  get_user_option('rich_editing') == 'true') {
         add_filter("mce_external_plugins", array($this,"addRichPlugin"));
         add_filter('mce_buttons', array($this,'addRichButton1'),999);
         $myStyleUrl = "../../plugins/wysija-newsletters/css/tmce/style.css";
         add_editor_style($myStyleUrl);
         //add_filter('tiny_mce_before_init', array($this,'TMCEinnercss'),12 );
         wp_enqueue_style('custom_TMCE_admin_css', WYSIJA_URL.'css/tmce/panelbtns.css');
         wp_print_styles('custom_TMCE_admin_css');

       }
    }

    function addRichPlugin($plugin_array) {
       $plugin_array['wysija_register'] = WYSIJA_URL.'mce/wysija_register/editor_plugin.js';
       $plugin_array['wysija_subscribers'] = WYSIJA_URL.'mce/wysija_subscribers/editor_plugin.js';

       return $plugin_array;
    }

    function addRichButton1($buttons) {
       $newButtons=array();
       foreach($buttons as $value) $newButtons[]=$value;
       //array_push($newButtons, "|", "styleselect");
       array_push($newButtons, '|', 'wysija_register');
       //array_push($newButtons, "|", "wysija_links");
       //array_push($newButtons, '|', 'wysija_subscribers');
       return $newButtons;
    }

    /**
     *
     */
    function version(){
        $wysija_footer_links= '<div class="wysija-version">';
        $wysija_footer_links.='<div class="social-foot">';

        $link_start=' | <a target="_blank" id="switch_to_package" href="admin.php?page=wysija_campaigns&action=switch_to_package&plugin=wysija-newsletters&_wpnonce='.WYSIJA_view::secure(array('controller'=>'wysija_campaigns', 'action'=>'switch_to_package'),true);
        if(WYSIJA::is_beta()){
            $beta_link=$link_start.'&stable=1"  title="'.__('Switch back to stable',WYSIJA).'">'.__('Switch back to stable',WYSIJA).'</a>';
        }else{
            $beta_link=$link_start.'"   title="'.__('Switch to beta',WYSIJA).'">'.__('Switch to beta',WYSIJA).'</a>';
        }
        if( (is_multisite() && !WYSIJA::current_user_can('manage_network')) || (!is_multisite() && !WYSIJA::current_user_can('switch_themes')) ) $beta_link='';
        $wysija_footer_links.= '<div id="upperfoot"><div class="support"><a target="_blank" href="http://support.mailpoet.com/?utm_source=wpadmin&utm_campaign=footer" >'.__('Support',WYSIJA).'</a>'.$beta_link;

        add_filter('wysija_footer_add_stars', array($this,'footer_add_stars'),10);
        $wysija_footer_links.=apply_filters('wysija_footer_add_stars', '');

        $wysija_footer_links.= '<div class="version">'.__('Wysija Version: ',WYSIJA).'<a href="admin.php?page=wysija_campaigns&action=whats_new">'.WYSIJA::get_version().'</a></div></div>';

        /*$config=WYSIJA::get('config','model');
        $msg=$config->getValue('ignore_msgs');
        if(!isset($msg['socialfoot'])){
            $wysijaversion .= $this->controller->__get_social_buttons();
        }*/

        $wysija_footer_links.= '</div></div>';
        echo $wysija_footer_links;
    }

    /**
     *
     * @param string $message
     * @return string
     */
    function footer_add_stars($message){
        $message.=' | '.str_replace(
                array('[stars]','[link]','[/link]'),

                array('<a target="_blank" href="http://goo.gl/LVsvys" >&#9733;&#9733;&#9733;&#9733;&#9733;</a>','<a target="_blank" href="http://goo.gl/PFGphH" >','</a>'),

                __('Add your [stars] on [link]wordpress.org[/link] and keep this plugin essentially free.',WYSIJA)
                );
        return $message;
    }



}


