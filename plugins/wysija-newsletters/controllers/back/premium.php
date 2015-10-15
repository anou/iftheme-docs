<?php

defined('WYSIJA') or die('Restricted access');

class WYSIJA_control_back_premium extends WYSIJA_control_back {

    /**
     * Main view of this controller
     * @var string
     */
    public $view = 'premium';
    public $model = 'config';


    /**
     * Constructor
     */
    function __construct(){
        parent::__construct();
    }

    function defaultDisplay() {
        $this->jsTrans['premium_activate'] = __('Already paid? Click here to activate', WYSIJA);
        $this->jsTrans['premium_activating'] = __('Checking license', WYSIJA);
    }

    function premium_ben() {
        $this->jsTrans['premium_activate'] = __('Already paid? Click here to activate', WYSIJA);
        $this->jsTrans['premium_activating'] = __('Checking license', WYSIJA);
    }

    function premium_kim() {
        $this->jsTrans['premium_activate'] = __('Already paid? Click here to activate', WYSIJA);
        $this->jsTrans['premium_activating'] = __('Checking license', WYSIJA);
    }
}
