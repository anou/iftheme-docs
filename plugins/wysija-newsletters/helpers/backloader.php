<?php
defined('WYSIJA') or die('Restricted access');


/**
 * class managing the admin vital part to integrate wordpress
 */
class WYSIJA_help_backloader extends WYSIJA_help{

    var $jsVariables='';
    function WYSIJA_help_backloader(){

        parent::WYSIJA_help();

    }
    /**
     *
     * @param type $controller
     */
    function initLoad(&$controller){
        wp_enqueue_style('wysija-admin-css', WYSIJA_URL.'css/admin.css',array(),WYSIJA::get_version());
        wp_enqueue_script('wysija-admin', WYSIJA_URL.'js/admin.js', array( 'jquery' ), true, WYSIJA::get_version());
        /* default script on all wysija interfaces in admin */
        wp_enqueue_script('wysija-admin-if', WYSIJA_URL.'js/admin-wysija.js', array( 'jquery' ),WYSIJA::get_version());


        // TO IMPROVE: This has NOTHING TO DO HERE. It has to be moved to the subscribers controller
        if(!$controller->jsTrans){
            $controller->jsTrans['selecmiss']=__('Please make a selection first!',WYSIJA);
            $controller->jsTrans['suredelete']=__('Deleting a list will not delete any subscribers.',WYSIJA);


        }
        $controller->jsTrans['sure_to_switch_package']=__('Do you want to install that version?',WYSIJA);
        $controller->js[]='wysija-admin-ajax';
        $controller->js[]='thickbox';
        wp_enqueue_style( 'thickbox' );
    }

    /**
     * help to automatically loads scripts and stylesheets based on the request
     * @param type $pagename
     * @param type $dirname
     * @param type $urlname
     * @param type $controller
     * @param type $extension
     * @return type
     */
    function loadScriptsStyles($pagename,$dirname,$urlname,&$controller,$extension='newsletter') {

        if(isset($_REQUEST['action'])){
            $action=$_REQUEST['action'];

            //add form validators script for add and edit
            if(($action=='edit' || $action=='add') && is_object($controller)){
                $controller->js[]='wysija-validator';
            }

        }else{
            $action='default';
            //load the listing script
            if($pagename!='config')  wp_enqueue_script('wysija-admin-list');
        }
        //check for files based on this combinations of parameters pagename or pagename and action
        $possibleParameters=array(array($pagename),array($pagename,$action));
        $enqueueFileTypes=array('wp_enqueue_script'=>array('js'=>'js','php'=>'js'),'wp_enqueue_style'=>array('css'=>'css'));

        foreach($possibleParameters as $params){
            foreach($enqueueFileTypes as $wayToInclude =>$fileTypes){
                foreach($fileTypes as $fileType=>$folderLocation){
                    if(file_exists($dirname.$folderLocation.DS.'admin-'.implode('-', $params).'.'.$fileType)){
                        $sourceIdentifier='wysija-autoinc-'.$extension.'-admin-'.implode('-', $params).'-'.$fileType;
                        $sourceUrl=$urlname.$folderLocation.'/admin-'.implode('-', $params).'.'.$fileType;
                        call_user_func_array($wayToInclude, array($sourceIdentifier,$sourceUrl,array(),WYSIJA::get_version()));
                    }
                }
            }
        }

        return true;
    }

    /**
     * enqueu and load different scripts and style based on one script being requested in the controller
     * @param type $controller
     * @param type $pagename
     * @param string $urlbase
     */
    function jsParse(&$controller,$pagename,$urlbase=WYSIJA_URL){

        // find out the name of the plugin based on the urlbase parameter
        $plugin = substr(strrchr(substr($urlbase, 0, strlen($urlbase)-1), '/'), 1);

        /* enqueue all the scripts that have been declared in the controller */
            if($controller->js){
                foreach($controller->js as $kjs=> $js){
                    switch($js){
                        case 'jquery-ui-tabs':
                            wp_enqueue_script($js);
                            wp_enqueue_style('wysija-tabs-css', WYSIJA_URL."css/smoothness/jquery-ui-1.8.20.custom.css",array(),WYSIJA::get_version());
                            break;
                        case 'wysija-validator':
                            wp_enqueue_script('wysija-validator-lang');
                            wp_enqueue_script($js);
                            wp_enqueue_script('wysija-form');
                            wp_enqueue_style('validate-engine-css');
                            break;
                        case 'wysija-admin-ajax':
                            if($plugin!='wysija-newsletters')   $ajaxvarname=$plugin;
                            else $ajaxvarname='wysija';

                            $dataajaxxx=array(
                                'action' => 'wysija_ajax',
                                'controller' => $pagename,
                                'wysijaplugin' => $plugin,
                                'dataType'=>"json",
                                'ajaxurl'=>admin_url( 'admin-ajax.php', 'relative' ),
                                'pluginurl'=>plugins_url( 'wysija-newsletters' ),
                                'loadingTrans'  =>__('Loading...',WYSIJA)
                            );

                            if(is_user_logged_in()){
                                $dataajaxxx['adminurl']=admin_url( 'admin.php' );
                            }

                            wp_localize_script( 'wysija-admin-ajax', $ajaxvarname.'AJAX',$dataajaxxx );
                            wp_enqueue_script('jquery-ui-dialog');
                            wp_enqueue_script($js);
                            wp_enqueue_style('wysija-tabs-css', WYSIJA_URL.'css/smoothness/jquery-ui-1.8.20.custom.css',array(),WYSIJA::get_version());
                            break;
                        case 'wysija-admin-ajax-proto':
                            wp_enqueue_script($js);
                            break;
                        case 'wysija-edit-autonl':
                            wp_enqueue_script('wysija-edit-autonl', WYSIJA_URL.'js/admin-campaigns-editAutonl.js',array('jquery'),WYSIJA::get_version());
                            break;
                        case 'wysija-form-widget-settings':
                            wp_enqueue_script('wysija-prototype', WYSIJA_URL.'js/prototype/prototype.js',array(),WYSIJA::get_version());
                            wp_enqueue_script('wysija-proto-scriptaculous', WYSIJA_URL.'js/prototype/scriptaculous.js',array('wysija-prototype'),WYSIJA::get_version());
                            wp_enqueue_script('wysija-proto-dragdrop', WYSIJA_URL.'js/prototype/dragdrop.js',array('wysija-proto-scriptaculous'),WYSIJA::get_version());
                            wp_enqueue_script('wysija-proto-controls', WYSIJA_URL.'js/prototype/controls.js',array('wysija-proto-scriptaculous'),WYSIJA::get_version());
                        break;

                        case 'wysija-form-editor':
                            wp_enqueue_script('wysija-prototype', WYSIJA_URL.'js/prototype/prototype.js',array(),WYSIJA::get_version());
                            wp_enqueue_script('wysija-proto-scriptaculous', WYSIJA_URL.'js/prototype/scriptaculous.js',array('wysija-prototype'),WYSIJA::get_version());
                            wp_enqueue_script('wysija-proto-dragdrop', WYSIJA_URL.'js/prototype/dragdrop.js',array('wysija-proto-scriptaculous'),WYSIJA::get_version());
                            wp_enqueue_script('wysija-proto-controls', WYSIJA_URL.'js/prototype/controls.js',array('wysija-proto-scriptaculous'),WYSIJA::get_version());

                            // include form editor
                            wp_enqueue_script($js, WYSIJA_URL.'js/'.$js.'.js', array(), WYSIJA::get_version());

                            /* Wysija form editor i18n */
                            wp_localize_script('wysija-form-editor', 'Wysija_i18n', $controller->jsTrans);

                            // form editor css
                            wp_enqueue_style('wysija-form-editor-css', WYSIJA_URL."css/wysija-form-editor.css",array(),WYSIJA::get_version());
                            break;
                        case 'wysija-amcharts':
                            // Wysija chart
                            wp_enqueue_script("amcharts", WYSIJA_URL."js/amcharts/amcharts.js",array(),WYSIJA::get_version());
                            wp_enqueue_script("wysija-amcharts", WYSIJA_URL."js/wysija-charts.js",array(),WYSIJA::get_version());

                        case 'wysija-editor':

                            wp_enqueue_script("wysija-prototype", WYSIJA_URL."js/prototype/prototype.js",array(),WYSIJA::get_version());
                            wp_deregister_script('thickbox');

                            wp_register_script('thickbox',WYSIJA_URL.'js/thickbox/thickbox.js',array('jquery'),WYSIJA::get_version());

                            wp_localize_script('thickbox', 'thickboxL10n', array(
                                'next' => __('Next &gt;'),
                                'prev' => __('&lt; Prev'),
                                'image' => __('Image'),
                                'of' => __('of'),
                                'close' => __('Close'),
                                'noiframes' => __('This feature requires inline frames. You have iframes disabled or your browser does not support them.'),
                                'l10n_print_after' => 'try{convertEntities(thickboxL10n);}catch(e){};'
                            ));

                            wp_enqueue_script("wysija-proto-scriptaculous", WYSIJA_URL."js/prototype/scriptaculous.js",array("wysija-prototype"),WYSIJA::get_version());
                            wp_enqueue_script("wysija-proto-dragdrop", WYSIJA_URL."js/prototype/dragdrop.js",array("wysija-proto-scriptaculous"),WYSIJA::get_version());
                            wp_enqueue_script("wysija-proto-controls", WYSIJA_URL."js/prototype/controls.js",array("wysija-proto-scriptaculous"),WYSIJA::get_version());
                            wp_enqueue_script("wysija-timer", WYSIJA_URL."js/timer.js",array(),WYSIJA::get_version());
                            wp_enqueue_script($js, WYSIJA_URL."js/".$js.".js",array(),WYSIJA::get_version());
                            wp_enqueue_script('wysija-konami', WYSIJA_URL."js/konami.js",array(),WYSIJA::get_version());
                            wp_enqueue_script('wysija-tinymce', WYSIJA_URL."js/tinymce/tiny_mce.js",array(),WYSIJA::get_version());
                            wp_enqueue_script('wysija-tinymce-init', WYSIJA_URL."js/tinymce_init.js",array(),WYSIJA::get_version());
                            wp_enqueue_style('wysija-editor-css', WYSIJA_URL."css/wysija-editor.css",array(),WYSIJA::get_version());
                            wp_enqueue_script('wysija-colorpicker', WYSIJA_URL."js/excolor/jquery.modcoder.excolor.js",array(),WYSIJA::get_version());

                            /* Wysija editor i18n */
                            wp_localize_script('wysija-editor', 'Wysija_i18n', $controller->jsTrans);
                            break;
                        case 'wysija-colorpicker':
                            wp_enqueue_script('wysija-colorpicker', WYSIJA_URL."js/excolor/jquery.modcoder.excolor.js",array(),WYSIJA::get_version());
                            break;
                        default:

                            if(is_string($kjs)) {
                                // check if there's a trailing slash in the urlbase
                                if(substr($urlbase, -1) !== '/') $urlbase .= '/';
                                // check if there's already an extension specified for the file
                                if(substr($urlbase, -3) !== '.js') $js .= '.js';
                                // enqueue script

                                wp_enqueue_script($kjs, $urlbase.'js/'.$js,array(),WYSIJA::get_version());
                            } else {

                                wp_enqueue_script($js);
                            }
                    }

                }
            }


    }

    /**
     * add some js defined variable per script
     * @param type $pagename
     * @param type $dirname
     * @param type $urlname
     * @param type $controller
     * @param type $extension
     */
    function localize($pagename,$dirname,$urlname,&$controller,$extension="newsletter"){
        if($controller->jsLoc){
            foreach($controller->jsLoc as $key =>$value){
                foreach($value as $kf => $local){

                    //this function accepts multidimensional array some version like wp3.2.1 couldn't do that
                    $this->localizeme($key, $kf, $local);
                }
            }
        }
    }

    /**
     * multidimensional array are possible here
     * @param type $handle
     * @param type $object_name
     * @param type $l10n
     */
    function localizeme( $handle, $object_name, $l10n ) {

            foreach ( (array) $l10n as $key => $value ) {
                    if ( !is_scalar($value) )
                            continue;
                    $l10n[$key] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8');
            }

            $this->jsVariables.= "var $object_name = " . json_encode($l10n) . ';';
            add_action('admin_head',array($this,'printAdminLocalized'));
    }

    /**
     * load the variables in the html
     */
    function printAdminLocalized(){
        echo "<script type='text/javascript'>\n"; // CDATA and type='text/javascript' is not needed for HTML 5
        echo "/* <![CDATA[ */\n";
        echo $this->jsVariables."\n";
        echo "/* ]]> */\n";
        echo "</script>\n";
    }

}



