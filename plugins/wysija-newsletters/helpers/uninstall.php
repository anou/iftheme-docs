<?php
defined('WYSIJA') or die('Restricted access');
class WYSIJA_help_uninstall extends WYSIJA_object{

    function WYSIJA_help_uninstall(){
        //require_once(ABSPATH . 'wp-admin'.DS.'includes'.DS.'upgrade.php');
    }

    function reinstall(){

        if (current_user_can('delete_plugins') && $this->removeProcess()) {
            $this->notice(__('Wysija has been reinstalled successfully using the same version. Settings and data have been deleted.',WYSIJA));
        } else {
            $this->notice(__('Wysija cannot be reinstalled because your folder <em>wp-content/uploads/wysija</em> needs to be writable. Change your permissions and reinstall.',WYSIJA));
        }

    }

    function uninstall(){

        if(current_user_can('delete_plugins') && $this->removeProcess()) {
            $this->wp_notice(__("Wysija has been uninstalled. Your site is now cleared of Wysija.",WYSIJA));
        }

    }

    function removeProcess(){

            // Remove the wysija folder in uploads.
            $hFile = WYSIJA::get('file','helper');
            $upload_dir = $hFile->getUploadDir();
            $is_writable = is_writable($upload_dir);
            if ($is_writable) {
                $hFile->rrmdir($upload_dir);
            } elseif ($upload_dir!=false) {
                return false;
            }

            $filename = WYSIJA_DIR.'sql'.DS.'uninstall.sql';
            $handle = fopen($filename, 'r');
            $query = fread($handle, filesize($filename));
            fclose($handle);

            $queries=str_replace('DROP TABLE `','DROP TABLE `[wysija]',$query);

            $queries=explode('-- QUERY ---',$queries);
            $modelWysija=new WYSIJA_model();
            global $wpdb;
            foreach($queries as $query)
                $modelWysija->query($query);

            delete_option('wysija');
            delete_option('installation_step');

            wysija::update_option('wysija_reinstall',1);

            global $wp_roles;
            foreach($wp_roles->roles as $rolek=>$roled){
                if($rolek=='administrator') continue;
                $role=get_role($rolek);
                //remove wysija's cap
                $arr=array('wysija_newsletters','wysija_subscribers','wysija_config');

                foreach($arr as $arrkey)    $role->remove_cap( $arrkey );
            }

            return true;

    }


}
