<?php
defined('WYSIJA') or die('Restricted access');
class WYSIJA_view_front extends WYSIJA_view{
    var $controller='';
    function WYSIJA_view_front(){

    }

    /**
     *
     * @param type $print TODO needs to be removed
     */
    function addScripts($print=true){
        wp_enqueue_script('wysija-validator-lang');
        wp_enqueue_script('wysija-validator');
        wp_enqueue_script('wysija-front-subscribers');
        wp_enqueue_style('validate-engine-css');
    }
}