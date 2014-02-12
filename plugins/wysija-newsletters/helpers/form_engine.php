<?php
defined('WYSIJA') or die('Restricted access');
/**
 * @class Wysija Engine Helper (PHP5 version)
 */
class WYSIJA_help_form_engine extends WYSIJA_object {
    // debug mode
    private $_debug = false;

    // rendering context (editor, web)
    private $_context = 'editor';
    // rendering mode (live, preview)
    private $_mode = 'live';

    // data holders
    private $_data = null;
    private $_styles = null;
    private $_lists = null;

    // static form fields
    private $_static_fields = array('email', 'submit');

    // unique form fields
    private $_unique_fields = array('firstname', 'lastname', 'list');

    // constructor
    function __construct() {

    }

    // i18n methods
    public function get_translations() {
        return array(
            'savingnl' => __('Saving form...', WYSIJA),
            'save' => __('Save', WYSIJA),
            'edit_settings' => __('Edit', WYSIJA),
            'list_cannot_be_empty' => __('You have to select at least 1 list', WYSIJA)
        );
    }

    // getters/setters
    public function get_data($type = null) {
        if($type !== null) {
            if(array_key_exists($type, $this->_data)) {
                return $this->_data[$type];
            } else {
                // return default value
                $defaults = $this->get_default_data();
                return $defaults[$type];
            }
        }
        return $this->_data;
    }

    public function set_data($value = null, $decode = false) {
        if(!$value) {
            $this->_data = $this->get_default_data();
        } else {
            $this->_data = $value;
            if($decode) {
                $this->_data = $this->get_decoded('data');
            }
        }
    }

    public function set_lists($lists = array()) {
        $this->_lists = $lists;
    }

    public function get_formatted_lists() {

        $lists = $this->get_lists();
        $formatted_lists = array();

        foreach($lists as $list) {
            $formatted_lists[$list['list_id']] = $list['name'];
        }
        return $formatted_lists;
    }

    public function get_lists() {
        if($this->_lists === null) {
            // get available lists which users can subscribe to
            $model_list = WYSIJA::get('list','model');

            // get lists users can subscribe to (aka "enabled list")
            $lists = $model_list->get(array('name', 'list_id', 'is_public'), array('is_enabled' => 1));

            $this->set_lists($lists);
        }
        return $this->_lists;
    }

    private function get_context() {
        return $this->_context;
    }

    private function set_context($value = null) {
        if($value !== null) $this->_context = $value;
    }

    public function set_mode($value = null) {
        if($value !== null) $this->_mode = $value;
    }

    private function get_mode() {
        return $this->_mode;
    }

    public function get_encoded($type = 'data') {
        return base64_encode(serialize($this->{'get_'.$type}()));
    }

    public function get_decoded($type = 'data') {
        return unserialize(base64_decode($this->{'get_'.$type}()));
    }

    private function get_default_data() {

        $lists = $this->get_lists();

        // select default list
        $default_list = array();
        if(!empty($lists)) {
            $default_list[] = $lists[0]['list_id'];
        }

        return array(
            'version' => '0.2',
            'settings' => array(
                'on_success' => 'message',
                'success_message' => __('Check your inbox now to confirm your subscription.', WYSIJA),
                'lists' => $default_list,
                'lists_selected_by' => 'admin'
            ),
            'body' => array(
                array(
                    'name' => __('Email', WYSIJA),
                    'type' => 'input',
                    'field' => 'email',
                    'params' => array(
                        'label' => __('Email', WYSIJA),
                        'required' => true
                    )
                ),
                array(
                    'name' => __('Submit', WYSIJA),
                    'type' => 'submit',
                    'field' => 'submit',
                    'params' => array(
                        'label' => __('Subscribe!', WYSIJA)
                    )
                )
            )
        );
    }

    public function get_setting($key = null) {
        if($key === null) return null;

        if($this->is_data_valid() === true) {
            $settings = $this->get_data('settings');
            if(array_key_exists($key, $settings)) {
                // otherwise, simply return the value
                return $settings[$key];
            } else {
                return null;
            }
        }
    }

    // common methods
    private function is_debug() {
        return ($this->_debug === true);
    }

    private function is_data_valid() {
        return ($this->get_data() !== null);
    }

    // editor rendering methods
    public function render_editor_toolbar($fields = array()) {
        $output = '';
        //$output = '<div class="add_custom_field"><a href="javascript:;" class="button">'.__('Add Custom Field', WYSIJA).'</a></div>';

        foreach($fields as $field) {
            // get field type or defaults to "input"
            $type = (isset($field['column_type'])) ? $field['column_type'] : 'input';

            // set unique if the field type matches the unique_fields
            $is_unique = (in_array($field['column_name'], $this->_unique_fields));

            // generate html for toolbar item
            $output .= '<li><a class="wysija_form_item" id="'.$field['column_name'].'" wysija_field="'.$field['column_name'].'" wysija_name="'.$field['name'].'" wysija_unique="'.$is_unique.'" wysija_type="'.$type.'">'.$field['name'].'</a></li>';
        }

        return $output;
    }

    // renders all widgets' templates
    function render_editor_templates($fields = array()) {
        $this->set_context('editor');
        // get parser helper
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

        // define html output string
        $output = '';

        foreach($fields as $field) {
             // get field type or defaults to "input"
            $type = (isset($field['column_type'])) ? $field['column_type'] : 'input';

            // get label from params, defaults to field name
            $label = (isset($field['params']['label'])) ? $field['params']['label'] : $field['name'];

            // build field data in order to pass it to the widget template
            $fieldData = array(
                'field' => $field['column_name'],
                'type' => $type,
                'name' => $field['name'],
                'unique' => (in_array($field['column_name'], $this->_unique_fields)),
                'static' => (in_array($field['column_name'], $this->_static_fields)),
                'params' => array(
                    'label' => $label
                ),
                'i18n' => $this->get_translations()
            );

            // get field extra params if specified
            if(isset($field['params'])) {
                // merge the params
                $fieldData['params'] = array_merge($field['params'], $fieldData['params']);
            }

            // special case for lists
            if($fieldData['field'] === 'list') {
                $fieldData = $this->set_lists_names($fieldData);
            }

            // render widget templates
            $output .= $helper_render_engine->render($fieldData, 'templates/form/editor/widgets/template.html');
        }
        return $output;
    }

    private function set_lists_names($block = array()) {
        // get lists using each list id as key
        $lists = $this->get_formatted_lists();

        if($this->get_context() === 'editor') {
            $block['lists'] = $lists;
        } else {
            // if the block has no list, then simply return the block
            if(!isset($block['params']['values']) or empty($block['params']['values'])) return $block;

            $values = array();

            foreach($block['params']['values'] as $list) {
                // check if the list id exists in the lists
                if(isset($lists[$list['list_id']])) {
                    $is_checked = (isset($list['is_checked']) ? (int)$list['is_checked'] : 0);
                    $values[] = array('name' => $lists[$list['list_id']], 'list_id' => $list['list_id'], 'is_checked' => $is_checked);
                }
            }

            $block['params']['values'] = $values;
        }

        return $block;
    }

    // renders a single widget's template
    public function render_editor_template($block = array()) {
        $this->set_context('editor');

        // get parser helper
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

        // special case for lists
        if($block['field'] === 'list') {
            $block = $this->set_lists_names($block);
        }

        $block = array_merge($block, array(
            'unique' => (in_array($block['field'], $this->_unique_fields)),
            'static' => (in_array($block['field'], $this->_static_fields)),
            'i18n' => $this->get_translations()
        ));

        // render widget templates
        return $helper_render_engine->render($block, 'templates/form/editor/widgets/template.html');
    }

    // renders the editor
    function render_editor() {
        $this->set_context('editor');

        if($this->is_data_valid() === false) {
            throw new Exception('data is not valid');
        } else {
            $helper_render_engine = WYSIJA::get('render_engine', 'helper');
            $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

            $data = array(
                'body' => $this->render_editor_body(),
                'is_debug' => $this->is_debug(),
                'i18n' => $this->get_translations()
            );

            return $helper_render_engine->render($data, 'templates/form/editor/template.html');
        }
    }

    // renders editor's body
    function render_editor_body() {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

        $blocks = $this->get_data('body');
        if(empty($blocks)) return '';

        $body = '';
        foreach($blocks as $block) {
            // special case for list widget
            if($block['field'] === 'list') {
                $block = $this->set_lists_names($block);
            }

            // generate block template
            $data = array_merge($block, array(
                'unique' => (in_array($block['field'], $this->_unique_fields)),
                'static' => (in_array($block['field'], $this->_static_fields)),
                'i18n' => $this->get_translations())
            );

            $body .= $helper_render_engine->render($data, 'templates/form/editor/widgets/template.html');
        }

        return $body;
    }

    // web rendering methods
    public function render_web($data = array()) {
        $this->set_context('web');

        if($this->is_data_valid() === false) {
            throw new Exception('data is not valid');
        } else {
            $helper_render_engine = WYSIJA::get('render_engine', 'helper');
            $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

            $data = array(
                'preview' => ($this->get_mode() === 'preview'),
                'settings' => $this->get_data('settings'),
                'body' => $this->render_web_body()
            );

            // in live mode, we need to specify the form id
            if($this->get_mode() === 'live') {
                $data['form_id'] = (int)$this->get_data('form_id');
            }

            $helper_render_engine = WYSIJA::get('render_engine', 'helper');
            $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

            // make sure we get the messages
            $output = '';

            $posted_form = (isset($_POST['form_id']) && (int)$_POST['form_id'] > 0) ? (int)$_POST['form_id'] : 0;

            if($data['form_id'] === $posted_form) {
                $view = WYSIJA::get('widget_nl','view','front');
                if(count($view->getMsgs()) > 0) {
                    $output .= $view->messages();
                }
            }

            try {
                $output .= $helper_render_engine->render($data, 'templates/form/web/template.html');
                return $output;
            } catch(Exception $e) {
                return '';
            }
        }
    }

    protected function get_validation_class($block) {
        $rules = array();

        // if it's the email field, it's mandatory and needs to be valid
        if($block['field'] === 'email') {
            $rules[] = 'required';
            $rules[] = 'custom[email]';
        }

        // if it's the list field, at least one option needs to be selected
        if($block['field'] === 'list') {
            $rules[] = 'required';
        }

        // check if the field is required
        if(isset($block['params']['required']) && (bool)$block['params']['required'] === true) {
            $rules[] = 'required';
        }

        // generate string if there is at least one rule to validate against
        if(empty($rules)) {
            return '';
        } else {
            // make sure rules are not duplicated
            $rules = array_unique($rules);
            return 'validate['.join(',', $rules).']';
        }
    }

    protected function render_web_body() {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

        $blocks = $this->get_data('body');

        if(empty($blocks)) return '';

        $body = '';
        foreach($blocks as $key => $block) {
            // special case for list widget
            if($block['field'] === 'list') {
                $block = $this->set_lists_names($block);
            }

            // special case for email widget
            if($block['field'] === 'email') {
                $user_email = WYSIJA::wp_get_userdata('user_email');

                if($user_email && is_string($user_email) && is_user_logged_in() && !current_user_can('switch_themes') && !is_admin()) {
                    $block['value'] = $user_email;
                }
            }

            // generate block template
            $data = array_merge($block, array(
                'preview' => ($this->get_mode() === 'preview'),
                'i18n' => $this->get_translations(),
                'validation' => $this->get_validation_class($block)
            ));
            $body .= $helper_render_engine->render($data, 'templates/form/web/widgets/template.html');
        }

        return $body;
    }

    public function get_exports($form_id) {
        return array(
            'iframe' => base64_encode($this->export($form_id, 'iframe')),
            'php' => base64_encode($this->export($form_id, 'php')),
            'html' => base64_encode($this->export($form_id, 'html'))
        );
    }

    public function render_editor_export($form_id) {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

        $data = array(
            'types' => array(
                'iframe' => $this->export($form_id, 'iframe'),
                'php' => $this->export($form_id, 'php'),
                'html' => $this->export($form_id, 'html'),
                'shortcode' => $this->export($form_id, 'shortcode')
            )
        );

        return $helper_render_engine->render($data, 'templates/form/web/export.html');
    }

    public function export($form_id, $type) {
        switch($type) {
            case 'iframe':
                $url_params = array(
                    'wysija-page' => 1,
                    'controller' => 'subscribers',
                    'action' => 'wysija_outter',
                    'wysija_form' => $form_id
                );

                $url_params['external_site'] = 1;

                $model_config = WYSIJA::get('config','model');
                $source_url = WYSIJA::get_permalink($model_config->getValue('confirm_email_link'), $url_params, true);

                return '<iframe width="100%" scrolling="no" frameborder="0" src="'.$source_url.'" class="iframe-wysija" vspace="0" tabindex="0" style="position: static; top: 0pt; margin: 0px; border-style: none; height: 330px; left: 0pt; visibility: visible;" marginwidth="0" marginheight="0" hspace="0" allowtransparency="true" title="'.__('Subscription Wysija',WYSIJA).'"></iframe>';
            break;
            case 'php':
                $output = array(
                    '$widgetNL = new WYSIJA_NL_Widget(true);',
                    'echo $widgetNL->widget(array(\'form\' => '.(int)$form_id.', \'form_type\' => \'php\'));'
                );
                return join("\n", $output);
            break;
            case 'html':
                //need some language for the validation
                $helper_toolbox = WYSIJA::get('toolbox','helper');
                $wp_language_code = $helper_toolbox->get_language_code();

                $wysija_version=WYSIJA::get_version();
                $scripts_to_include='<!--START Scripts : this is the script part you can add to the header of your theme-->'."\n";
                $scripts_to_include.='<script type="text/javascript" src="'.includes_url().'js/jquery/jquery.js'.'?ver='.$wysija_version.'"></script>'."\n";
                if(file_exists(WYSIJA_DIR.'js'.DS.'validate'.DS.'languages'.DS.'jquery.validationEngine-'.$wp_language_code.'.js')){
                    $scripts_to_include.='<script type="text/javascript" src="'.WYSIJA_URL.'js/validate/languages/jquery.validationEngine-'.$wp_language_code.'.js'.'?ver='.$wysija_version.'"></script>'."\n";
                }else{
                    $scripts_to_include.='<script type="text/javascript" src="'.WYSIJA_URL.'js/validate/languages/jquery.validationEngine-en.js'.'?ver='.$wysija_version.'"></script>'."\n";
                }
                $scripts_to_include.='<script type="text/javascript" src="'.WYSIJA_URL.'js/validate/jquery.validationEngine.js'.'?ver='.$wysija_version.'"></script>'."\n";
                $scripts_to_include.='<script type="text/javascript" src="'.WYSIJA_URL.'js/front-subscribers.js'.'?ver='.$wysija_version.'"></script>'."\n";
                $scripts_to_include.='<script type="text/javascript">
                /* <![CDATA[ */
                var wysijaAJAX = {"action":"wysija_ajax","controller":"subscribers","ajaxurl":"'.admin_url('admin-ajax.php','absolute').'","loadingTrans":"'.__('Loading...',WYSIJA).'"};
                /* ]]> */
                </script>';
                $scripts_to_include.='<script type="text/javascript" src="'.WYSIJA_URL.'js/front-subscribers.js?ver='.$wysija_version.'"></script>'."\n";
                $scripts_to_include.='<!--END Scripts-->'."\n"."\n";

                //enqueue the scripts
                $html_result=$scripts_to_include;

                // add the html for the form
                $widget_NL = new WYSIJA_NL_Widget(true);
                $html_result.= $widget_NL->widget(array('form' => (int)$form_id, 'form_type' => 'html'));

                return $html_result;
            break;
            case 'shortcode':
                return '[wysija_form id="'.(int)$form_id.'"]';
            break;
        }
    }
}
