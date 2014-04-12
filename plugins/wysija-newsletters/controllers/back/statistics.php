<?php

defined('WYSIJA') or die('Restricted access');
require_once(WYSIJA_CORE.'module'.DS.'statistics.php'); // @todo

class WYSIJA_control_back_statistics extends WYSIJA_control_back {

    /**
     * Main model of this controller
     * @var string
     */
    public $model = 'statistics';

    /**
     * Main view of this controller
     * @var string
     */
    public $view = 'statistics';

    /**
     * Base URL of all requests
     * @var string
     */
    public $base_url = 'admin.php';

    /**
     * Load blocks at a same time (FALSE) or one by one (TRUE)
     * @var TRUE
     */
    protected $lazy_load = true;

    /**
     * list of pre-defined dates
     * @var Array
     */
    protected $pre_defined_dates = array();
    protected $date_format = 'Y/m/d';
    protected $js_date_format = 'yy/mm/dd';

    /**
     * Constructor
     */
    function WYSIJA_control_back_statistics() {}

    public function defaultDisplay() {
	$this->pre_defined_dates = $this->get_pre_defined_dates();
	// Define view
	$this->viewShow = $this->action = 'main';
	$this->js['jquery.core'] = 'jquery/ui/jquery.ui.core';
	$this->js['jquery.datepicker'] = 'jquery/ui/jquery.ui.datepicker';
	$this->js['wysijalazyload'] = 'wysija-lazyload';
	$this->js['admin-statistics-filter'] = 'admin-statistics-filter';
	wp_enqueue_style('jquery.core', WYSIJA_URL.'css/jquery/ui/themes/base/jquery.ui.core.min.css', array(), WYSIJA::get_version());
	wp_enqueue_style('jquery.core', WYSIJA_URL.'css/jquery/ui/themes/base/jquery.ui.theme.min.css', array(), WYSIJA::get_version());

	// date filter
	$default_duration = $this->get_default_duration();
        if( function_exists('date_diff')){
            $this->data['date_interval'] = date_diff(date_create($default_duration['from']), date_create($default_duration['to']));
        }else{
            $duration = strtotime($default_duration['to']) - strtotime($default_duration['from']);
            $helper_toolbox = WYSIJA::get('toolbox' , 'helper');
            $this->data['date_interval'] = (object)$helper_toolbox->convert_seconds_to_array($duration , false);
        }

	$this->data['custom_dates'] = $this->pre_defined_dates;
	$this->data['default_duration'] = $default_duration;
	$this->data['js_date_format'] = $this->js_date_format;

	// Process and push data into view
	$this->data['lazy_load'] = $this->lazy_load;
	$hook_name = 'hook_stats';
	$hook_params = array();
	$hook_params['top'] = WYSIJA_module_statistics::DEFAULT_TOP_RECORDS;
	$hook_params['from'] = !empty($_REQUEST['filter']['from']) ? $_REQUEST['filter']['from'] : $default_duration['from'];
	$hook_params['to'] = !empty($_REQUEST['filter']['to']) ? $_REQUEST['filter']['to'] : $default_duration['to'];
	$hook_params['group_by'] = ($this->data['date_interval']->days == 0 || $this->data['date_interval']->days > WYSIJA_module_statistics::SWITCHING_DATE_TO_MONTH_THRESHOLD) ?
		WYSIJA_module_statistics::GROUP_BY_MONTH :
		WYSIJA_module_statistics::GROUP_BY_DATE;// $this->data['date_interval']->days == 0, means, no begin date, no end date

	$modules = WYSIJA_module::get_modules_from_hook($hook_name);
	$this->data['modules'] = $modules;
	$this->data['lazy_load_modules'] = array();
	$this->data['first_module'] = '';

	if (!$this->lazy_load) {
	    $this->data['hooks'][$hook_name] = apply_filters('hook_stats', '', $hook_params);
	}
	else {
	    if (!empty($modules)) {
		$first_module = array_shift($modules);
		// List of lazy loaded modules
		$this->data['lazy_load_modules'] = $modules;

		// Evenly we are lazy loading, we always load the first module by default
		$this->data['first_module'] = apply_filters('custom_module_hook', '', $first_module, $hook_name, $hook_params);
	    }
	}
    }

    /**
     * get pre defined dates (duration)
     * @return type
     */
    protected function get_pre_defined_dates() {
	return array(
	    array(
		'value' => 7,
		'label' => __('Last 7 days', WYSIJA),
		'selected' => false,
		'from' => date($this->date_format, strtotime('-7 days')),
		'to' => date($this->date_format, strtotime('tomorrow'))
	    ),
	    array(
		'value' => 'last_month',
		'label' => __('Last month', WYSIJA),
		'selected' => false,
		'from' => date($this->date_format, strtotime('first day of previous month')),
		'to' => date($this->date_format, strtotime('last day of previous month'))
	    ),
	    array(
		'value' => 30,
		'label' => __('Last 30 days', WYSIJA),
		'selected' => false,
		'from' => date($this->date_format, strtotime('-30 days')),
		'to' => date($this->date_format, strtotime('tomorrow'))
	    ),
	    array(
		'value' => 90,
		'label' => __('Last 90 days', WYSIJA),
		'selected' => true,
		'from' => date($this->date_format, strtotime('-90 days')),
		'to' => date($this->date_format, strtotime('tomorrow'))
	    ),
	    array(
		'value' => 180,
		'label' => __('Last 180 days', WYSIJA),
		'selected' => false,
		'from' => date($this->date_format, strtotime('-180 days')),
		'to' => date($this->date_format, strtotime('tomorrow'))
	    ),
	    array(
		'value' => 365,
		'label' => __('Last 365 days', WYSIJA),
		'selected' => false,
		'from' => date($this->date_format, strtotime('-365 days')),
		'to' => date($this->date_format, strtotime('tomorrow'))
	    ),
	    array(
		'value' => 0,
		'label' => __('Custom dates', WYSIJA),
		'selected' => false,
		'from' => '',
		'to' => ''
	    ),
	);
    }

    /**
     * Get default duration of stats
     * @return int
     */
    protected function get_default_duration() {
	foreach ($this->pre_defined_dates as $duration)
	    if (isset($duration['selected']) && $duration['selected'])
		return $duration;
	return end($this->pre_defined_dates);
    }

    function date_diff($time_start,$time_end){
        $result = null;
        $duration = $time_end - $time_start;
        $result->days = floor($duration/(60*60*24));
        return $result;
    }
}
