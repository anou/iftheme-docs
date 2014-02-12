<?php
defined('WYSIJA') or die('Restricted access');
/**
 * @class Wysija Engine Helper (PHP4 version)
 */
class WYSIJA_help_wj_engine extends WYSIJA_object {
    // debug mode
    var $_debug = false;

    // contains email data
    var $_email_data = null;

    // rendering context (editor, email)
    var $_context = 'editor';

    // toggles for vib & unsub
    var $_hide_viewbrowser = false;
    var $_hide_unsubscribe = false;

    // data holders
    var $_data = null;
    var $_styles = null;

    // styles: defaults
    var $VIEWBROWSER_SIZES = array(7, 8, 9, 10, 11, 12, 13, 14);
    var $TEXT_SIZES = array(8, 9, 10, 11, 12, 13, 14, 16, 18, 24, 36, 48, 72);
    var $TITLE_SIZES = array(16, 18, 20, 22, 24, 26, 28, 30, 32, 34, 36, 40, 44, 48, 54, 60, 66, 72);
    var $FONTS = array("Arial", "Arial Black", "Comic Sans MS", "Courier New", "Georgia", "Impact", "Tahoma", "Times New Roman", "Trebuchet MS", "Verdana");

    /* Constructor */
    function WYSIJA_help_wj_engine(){ }

    /* i18n methods */
    function getTranslations() {
        return array(
            'dropHeaderNotice' => __('Drop your logo in this header.',WYSIJA),
            'dropFooterNotice' => __('Drop your footer image here.',WYSIJA),
            'dropBannerNotice' => __('If you leave this area empty, it will not display once you send your email',WYSIJA),
            'clickToEditText' => __('Click here to add a title or text.', WYSIJA),
            'alignmentLeft' =>  __('Align left',WYSIJA),
            'alignmentCenter' => __('Align center',WYSIJA),
            'alignmentRight' => __('Align right',WYSIJA),
            'addImageLink' => __('Add link / Alternative text',WYSIJA),
            'removeImageLink' => __('Remove link',WYSIJA),
            'removeImage' => __('Remove image',WYSIJA),
            'remove' => __('Remove', WYSIJA),
            'editText' => __( 'Edit text',WYSIJA),
            'removeText' => __('Remove text',WYSIJA),
            'textLabel' => __('Titles & text',WYSIJA),
            'dividerLabel' => __('Horizontal line',WYSIJA),
            'customDividerLabel' => __('Custom horizontal line',WYSIJA),
            'postLabel' => __('WordPress post',WYSIJA),
            'styleBodyLabel' => __('Text',WYSIJA),
            'styleViewbrowserLabel' => __('"View in browser"', WYSIJA),
            'styleH1Label' => __('Heading 1',WYSIJA),
            'styleH2Label' => __('Heading 2',WYSIJA),
            'styleH3Label' => __('Heading 3',WYSIJA),
            'styleLinksLabel' => __('Links',WYSIJA),
            'styleLinksDecorationLabel' => __('underline',WYSIJA),
            'styleFooterLabel' => __('Footer text',WYSIJA),
            'styleFooterBackgroundLabel' => __('Footer background',WYSIJA),
            'styleBodyBackgroundLabel' => __('Newsletter',WYSIJA),
            'styleHtmlBackgroundLabel' => __('Background', WYSIJA),
            'styleHeaderBackgroundLabel' => __('Header background', WYSIJA),
            'styleDividerLabel' => __('Horizontal line',WYSIJA),
            'styleUnsubscribeColorLabel' => __('Unsubscribe',WYSIJA),
            'articleSelectionTitle' => __('Post Selection', WYSIJA),
            'bookmarkSelectionTitle' => __('Social Bookmark Selection', WYSIJA),
            'dividerSelectionTitle' => __('Divider Selection', WYSIJA),
            'abouttodeletetheme' => __('You are about to delete the theme : %1$s. Do you really want to do that?', WYSIJA),
            'addLinkTitle' => __('Add Link & Alternative text', WYSIJA),
            'styleTransparent' => __('Check this box if you want transparency', WYSIJA),
            'ajaxLoading' => __('Loading...', WYSIJA),
            'customFieldsLabel' => __('Add first or last name of subscriber', WYSIJA),
            'autoPostSettingsTitle' => __('Selection options', WYSIJA),
            'autoPostEditSettings' => __('Edit Automatic latest content', WYSIJA),
            'autoPostImmediateNotice' => __('You can only add one widget when designing a post notification sent immediately after an article is published', WYSIJA),
            'toggleImagesTitle' => __('Preview without images', WYSIJA),
            // Tags labels
            'tags_user' => __('Subscriber', WYSIJA),
            'tags_user_firstname' => __('First Name', WYSIJA),
            'tags_user_lastname' => __('Last Name', WYSIJA),
            'tags_user_email' => __('Email Address', WYSIJA),
            'tags_user_displayname' => __('Wordpress user display name', WYSIJA),
            'tags_user_count' => __('Total of subscribers', WYSIJA),
            'tags_newsletter' => __('Newsletter', WYSIJA),
            'tags_newsletter_subject' => __('Newsletter Subject', WYSIJA),
            'tags_newsletter_autonl' => __('Post Notifications', WYSIJA),
            'tags_newsletter_total' => __('Total number of posts or pages', WYSIJA),
            'tags_newsletter_post_title' => __('Latest post title', WYSIJA),
            'tags_newsletter_number' => __('Issue number', WYSIJA),
            'tags_date' => __('Date', WYSIJA),
            'tags_date_d' => __('Current day of the month number', WYSIJA),
            'tags_date_dordinal' => __('Current day of the month in ordinal, ie. 2nd, 3rd, etc.', WYSIJA),
            'tags_date_dtext' => __('Full name of current day', WYSIJA),
            'tags_date_m' => __('Current month number', WYSIJA),
            'tags_date_mtext' => __('Full name of current month', WYSIJA),
            'tags_date_y' => __('Year', WYSIJA),
            'tags_global' => __('Links', WYSIJA),
            'tags_global_unsubscribe' => __('Unsubscribe link', WYSIJA),
            'tags_global_manage' => __('Edit subscription page link', WYSIJA),
            'tags_global_browser' => __('View in browser link', WYSIJA),
            // Themes specific labels
            'theme_setting_default' => __('Saving default style...', WYSIJA),
            'theme_saved_default' => __('Default style saved.', WYSIJA),
            'theme_save_as_default' => __('Set as default style.', WYSIJA)

        );
    }

    /* Data methods */
    function getData($type = null) {
        if($type !== null) {
            if(array_key_exists($type, $this->_data)) {
                return $this->_data[$type];
            } else {
                // return default value
                $defaults = $this->getDefaultData();
                return $defaults[$type];
            }
        }
        return $this->_data;
    }

    function setData($value = null, $decode = false) {
        if(!$value) {
            $this->_data = $this->getDefaultData();
        } else {
            $this->_data = $value;
            if($decode) {
                $this->_data = $this->getDecoded('data');
            }
        }
    }

    function getEmailData($key = null) {
        if($key === null) {
            return $this->_email_data;
        } else {
            if(array_key_exists($key, $this->_email_data)) {
                return $this->_email_data[$key];
            }
        }
        return null;
    }

    function setEmailData($value = null) {
        if($value !== null) {
            $this->_email_data = $value;
        }
    }

    function getDefaultData() {
        $dividersHelper = WYSIJA::get('dividers', 'helper');
        return array(
            'header' => array(
                'alignment' => 'center',
                'type' => 'header',
                'static' => '1',
                'text' => null,
                'image' => array(
                    'src' => null,
                    'width' => 600,
                    'height' => 86,
                    'url' => null,
                    'alignment' => 'center',
                    'static' => '1'
                )
            ),
            'body' => array(),
            'footer' => array(
                'alignment' => 'center',
                'type' => 'footer',
                'static' => '1',
                'text' => null,
                'image' => array(
                    'src' => null,
                    'width' => 600,
                    'height' => 86,
                    'url' => null,
                    'alignment' => 'center',
                    'static' => '1'
                )
            ),
            'widgets' => array(
                'divider' => array_merge($dividersHelper->getDefault(), array('type' => 'divider'))
            )
        );
    }

    /* Styles methods */
    function getStyles($keys = null) {
        if($keys === null) return $this->_styles;

        if(!is_array($keys)) {
            $keys = array($keys);
        }
        $output = array();
        for($i=0; $i<count($keys);$i++) {
            if(isset($this->_styles[$keys[$i]])) {
                $output = array_merge($output, $this->_styles[$keys[$i]]);
            }
        }
        return $output;
    }

    function getStyle($key, $subkey) {
        $styles = $this->getStyles($key);
        return $styles[$subkey];
    }

    function setStyles($value = null, $decode = false) {
        if(!$value) {
            $this->_styles = $this->getDefaultStyles();
        } else {
            $this->_styles = $value;
            if($decode) {
                $this->_styles = $this->getDecoded('styles');
            }
        }
    }

    function getDefaultStyles() {

        $defaults = array(
            'html' => array(
                'background' => 'FFFFFF'
            ),
            'header' => array(
                'background' => 'FFFFFF'
            ),
            'body' => array(
                'color' => '000000',
                'family' => 'Arial',
                'size' => $this->TEXT_SIZES[5],
                'background' => 'FFFFFF'
            ),
            'footer' => array(
                'color' => '000000',
                'family' => 'Arial',
                'size' => $this->TEXT_SIZES[5],
                'background' => 'cccccc'
            ),
            'h1' => array(
                'color' => '000000',
                'family' => 'Arial',
                'size' => $this->TITLE_SIZES[6]
            ),
            'h2' => array(
                'color' => '000000',
                'family' => 'Arial',
                'size' => $this->TITLE_SIZES[5]
            ),
            'h3' => array(
                'color' => '000000',
                'family' => 'Arial',
                'size' => $this->TITLE_SIZES[4]
            ),
            'a' => array(
                'color' => '0000FF',
                'underline' => false
            ),
            'unsubscribe' => array(
                'color' => '000000'
            ),
            'viewbrowser' => array(
                'color' => '000000',
                'family' => 'Arial',
                'size' => $this->VIEWBROWSER_SIZES[4]
            )
        );

        // get default selected theme
        $model_config = WYSIJA::get('config', 'model');
        $default_theme = $model_config->getValue('newsletter_default_theme', 'default');

        if($default_theme === 'default') {
            return $defaults;
        } else {
            $helper_themes = WYSIJA::get('themes', 'helper');
            $stylesheet = $helper_themes->getStylesheet($default_theme);

            $styles = array();
            // look for each tags
            foreach($defaults as $tag => $values) {
                // look for css rules
                preg_match('/\.?'.$tag.'\s?{(.+)}/Ui', $stylesheet, $matches);
                if(isset($matches[1])) {
                    // extract styles
                    $styles[$tag] = $this->extractStyles($matches[1]);
                } else {
                    // fallback to default
                    $styles[$tag] = $defaults[$tag];
                }
            }

            return $styles;
        }
    }

    /* Editor methods */
    function renderEditor() {
        $this->setContext('editor');

        if($this->isDataValid() === false) {
            throw new Exception('data is not valid');
        } else {
            $helper_render_engine = WYSIJA::get('render_engine', 'helper');
            $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

            // get company addressfrom settings
            $config=WYSIJA::get("config","model");

            $data = array(
                'header' => $this->renderEditorHeader(),
                'body' => $this->renderEditorBody(),
                'footer' => $this->renderEditorFooter(),
                'unsubscribe' => $config->emailFooterLinks(true),
                'company_address' => nl2br($config->getValue('company_address')),
                'is_debug' => $this->isDebug(),
                'i18n' => $this->getTranslations()
            );

            $viewbrowser = $config->viewInBrowserLink(true);
            if($viewbrowser) {
                $data['viewbrowser'] = $viewbrowser;
            }

            return $helper_render_engine->render($data, 'templates/editor/editor_template.html');
        }
    }

    function renderEditorHeader($data = null) {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
        $helper_render_engine->setStripSpecialchars(true);

        if($data !== null) {
            $block = $data;
        } else {
            $block = $this->getData('header');
        }

        $data = array_merge($block, array('i18n' => $this->getTranslations()));
        return $helper_render_engine->render($data, 'templates/editor/header_template.html');
    }

    function renderEditorBody() {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

        $blocks = $this->getData('body');
        if(empty($blocks)) return '';

        $body = '';
        foreach($blocks as $key => $block) {
            // generate block template
            $data = array_merge($block, array('i18n' => $this->getTranslations()));
            $body .= $helper_render_engine->render($data, 'templates/editor/block_template.html');
        }

        return $body;
    }

    function renderEditorFooter($data = null)
    {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

        if($data !== null) {
            $block = $data;
        } else {
            $block = $this->getData('footer');
        }

        $data = array_merge($block, array('i18n' => $this->getTranslations()));

        return $helper_render_engine->render($data, 'templates/editor/footer_template.html');
    }

    function renderEditorBlock($block = array()) {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
        $helper_render_engine->setStripSpecialchars(true);

        $block['i18n'] = $this->getTranslations();

        return $helper_render_engine->render($block, 'templates/editor/block_'.$block['type'].'.html');
    }

    /* render auto post */
    function renderEditorAutoPost($posts = array(), $params = array()) {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
        $helper_render_engine->setStripSpecialchars(true);

        if(isset($params['bgcolor1']) && strlen($params['bgcolor1']) === 0) {
            $params['bgcolor1'] = 'transparent';
        }
        if(isset($params['bgcolor2']) && strlen($params['bgcolor2']) === 0) {
            $params['bgcolor2'] = 'transparent';
        }

        $data = array(
            'posts' => $posts,
            'params' => $params
        );

        $html = $helper_render_engine->render($data, 'templates/editor/block_auto-post_content.html');

        return $html;
    }

    /* render draggable images list */
    function renderImages($data = array()) {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

        return $helper_render_engine->render(array('images' => $data), 'templates/toolbar/images.html');
    }

    /* render themes list */
    function renderThemes() {
        $themes = array();
        $hThemes = WYSIJA::get('themes', 'helper');

        $installed = $hThemes->getInstalled();

        // get default selected theme
        $model_config = WYSIJA::get('config', 'model');
        $default_theme = $model_config->getValue('newsletter_default_theme', 'default');

        if(empty($installed)) {
            return '';
        } else {
            foreach($installed as $theme) {
                $theme_info = $hThemes->getInformation($theme);
                $theme_info['is_selected'] = (bool)($default_theme === $theme);
                $themes[] = $theme_info;
            }
        }

        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);

        return $helper_render_engine->render(array('themes' => $themes, 'i18n' => $this->getTranslations()), 'templates/toolbar/themes.html');
    }

    function renderThemeStyles($theme = 'default') {
        $this->setContext('editor');

        $hThemes = WYSIJA::get('themes', 'helper');
        $stylesheet = $hThemes->getStylesheet($theme);

        if($stylesheet === NULL) {
            // load default settings
            $this->setStyles(null);
        } else {
            // a stylesheet has been found, let's extract styles
            $styles = array();
            $defaults = $this->getDefaultStyles();
            // look for each tags
            foreach($defaults as $tag => $values) {
                // look for css rules
                preg_match('/\.?'.$tag.'\s?{(.+)}/Ui', $stylesheet, $matches);
                if(isset($matches[1])) {
                    // extract styles
                    $styles[$tag] = $this->extractStyles($matches[1]);
                } else {
                    // fallback to default
                    $styles[$tag] = $defaults[$tag];
                }
            }
            $this->setStyles($styles);
        }

        return array(
            'css' => $this->renderStyles(),
            'form' => $this->renderStylesBar()
        );
    }

    function extractStyles($raw) {
        $rules = explode(';', $raw);
        $output = array();
        foreach($rules as $rule) {
            $sub_property = false;
            $combo = explode(':', $rule);
            if(count($combo) === 2) {
                list($property, $value) = $combo;
                // remove leading and trailing space
                $property = trim($property);
                $value = trim($value);
            } else {
                continue;
            }

            switch($property) {
                case 'background':
                case 'background-color':
                    $property = 'background';
                case 'color':
                    // remove # from color
                    $value = str_replace('#', '', $value);
                    // check if its a 3 chars color
                    if(strlen($value) === 3) {
                        $value = sprintf('%s%s%s%s%s%s', substr($value, 0, 1), substr($value, 0, 1), substr($value, 1, 1), substr($value, 1, 1), substr($value, 2, 1), substr($value, 2, 1));
                    }
                    break;
                case 'font-family':
                    $property = 'family';
                    $value = array_shift(explode(',', $value));
                    break;
                case 'font-size':
                    $property = 'size';
                case 'height':
                    $value = (int)$value;
                    break;
                case 'text-decoration':
                    $property = 'underline';
                    $value = ($value === 'none') ? '-1' : '1';
                    break;
                case 'border-color':
                    // remove # from color
                    $value = str_replace('#', '', $value);
                    // check if its a 3 chars color
                    if(strlen($value) === 3) {
                        $value = sprintf('%s%s%s%s%s%s', substr($value, 0, 1), substr($value, 0, 1), substr($value, 1, 1), substr($value, 1, 1), substr($value, 2, 1), substr($value, 2, 1));
                    }
                    list($property, $sub_property) = explode('-', $property);
                    break;
                case 'border-size':
                    $value = (int)$value;
                    list($property, $sub_property) = explode('-', $property);
                    break;
                case 'border-style':
                    list($property, $sub_property) = explode('-', $property);
                    break;
            }

            if($sub_property !== FALSE) {
                $output[$property][$sub_property] = $value;
            } else {
                $output[$property] = $value;
            }
        }
        return $output;
    }

    function renderTheme($theme = 'default') {
        $output = array(
            'header' => null,
            'footer' => null,
            'divider' => null
        );

        $hThemes = WYSIJA::get('themes', 'helper');
        $data = $hThemes->getData($theme);

        if($data['header'] !== NULL) {
            $output['header'] = $this->renderEditorHeader($data['header']);
        }

        if($data['footer'] !== NULL) {
            $output['footer'] = $this->renderEditorFooter($data['footer']);
        }

        if($data['divider'] !== NULL) {
            $output['divider'] = $this->renderEditorBlock(array_merge(array('no-block' => true), $data['divider']));
            $output['divider_options'] = $data['divider'];
        }

        return $output;
    }

    /* render styles bar */
    function renderStylesBar() {
        $this->setContext('editor');

        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
        $helper_render_engine->setStripSpecialchars(true);

        $data = $this->getStyles();
        $data['i18n'] = $this->getTranslations();
        $data['TEXT_SIZES'] = $this->TEXT_SIZES;
        $data['VIEWBROWSER_SIZES'] = $this->VIEWBROWSER_SIZES;
        $data['TITLE_SIZES'] = $this->TITLE_SIZES;
        $data['FONTS'] = $this->FONTS;

        return $helper_render_engine->render($data, 'templates/toolbar/styles.html');
    }

    function formatStyles($styles = array()) {
        if(empty($styles)) return;

        $data = array();
        foreach($styles as $style => $value) {
            $stylesArray = explode('-', $style);
            if(count($stylesArray) === 2) {
                $data[$stylesArray[0]][$stylesArray[1]] = $value;
            } else if(count($stylesArray) === 3) {
                // handle transparent colors
                if($stylesArray[2] === 'transparent') {
                    $data[$stylesArray[0]][$stylesArray[1]] = $stylesArray[2];
                } else {
                    $data[$stylesArray[0]][$stylesArray[1]][$stylesArray[2]] = $value;
                }
            }
        }

        return $data;
    }

    function getContext() {
        return $this->_context;
    }

    function setContext($value = null) {
        if($value !== null) $this->_context = $value;
    }

    function isDebug() {
        return ($this->_debug === true);
    }

    function getEncoded($type = 'data') {
        return base64_encode(serialize($this->{'get'.ucfirst($type)}()));
    }

    function getDecoded($type = 'data') {
        return unserialize(base64_decode($this->{'get'.ucfirst($type)}()));
    }

    /* methods */
    function isDataValid() {
        return ($this->getData() !== null);
    }

    /* Styles methods */
    function renderStyles() {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
        $helper_render_engine->setStripSpecialchars(true);
        $helper_render_engine->setInline(true);

        $data = $this->getStyles();
        $data['context'] = $this->getContext();

        switch($data['context']) {
            case 'editor':
                $helper_render_engine->setStripSpecialchars(false);
                $data['viewbrowser_container'] = '#wysija_viewbrowser';
                $data['wysija_container'] = '#wysija_wrapper';
                $data['header_container'] = '#wysija_header';
                $data['body_container'] = '#wysija_body';
                $data['text_container'] = '.editable';
                $data['footer_container'] = '#wysija_footer';
                $data['placeholder_container'] = '#wysija_block_placeholder';
                $data['unsubscribe_container'] = '#wysija_unsubscribe';
                return $helper_render_engine->render($data, 'styles/css-'.$data['context'].'.html');
            break;

            case 'email':
                $helper_render_engine->setStripSpecialchars(true);
                $data['viewbrowser_container'] = '#wysija_viewbrowser';
                $data['wysija_container'] = '#wysija_wrapper';
                $data['header_container'] = '#wysija_header_content';
                $data['body_container'] = '#wysija_body_content';
                $data['footer_container'] = '#wysija_footer_content';
                $data['text_container'] = '.wysija-text-container';
                $data['unsubscribe_container'] = '#wysija_unsubscribe';
                //right to left language property
                if(function_exists('is_rtl')) {
                    $data['is_rtl'] = is_rtl();
                } else {
                    $data['is_rtl'] = false;
                }

                return $helper_render_engine->render($data, 'templates/email_v3/css.html');
            break;
        }
    }

    /* Email methods */
    function renderNotification($email = NULL) {
        $this->_hide_viewbrowser = true;
        $this->_hide_unsubscribe = true;
        return $this->renderEmail($email);
    }

    function renderEmail($email = NULL) {

        // fixes issue with pcre functions
        @ini_set('pcre.backtrack_limit', 1000000);

        $this->setContext('email');

        if($this->isDataValid() === false) {
            throw new Exception('data is not valid');
        } else {
            // set email data for later use
            $this->setEmailData($email);

            // render header
            $data = array(
                'viewbrowser' => $this->renderEmailViewBrowser(),
                'header' => $this->renderEmailHeader(),
                'body' => $this->renderEmailBody(),
                'footer' => $this->renderEmailFooter(),
                'unsubscribe' => $this->renderEmailUnsubscribe(),
                'css' => $this->renderStyles(),
                'styles' => $this->getStyles(),
                'hide_viewbrowser' => $this->_hide_viewbrowser,
                'hide_unsubscribe' => $this->_hide_unsubscribe
            );

            //right to left language property
            if(function_exists('is_rtl')) {
                $data['is_rtl'] = is_rtl();
            } else {
                $data['is_rtl'] = false;
            }

            // set email subject if specified
            $data['subject'] = $this->getEmailData('subject');

            $helper_render_engine = WYSIJA::get('render_engine', 'helper');
            $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
            $helper_render_engine->setStripSpecialchars(true);
            $helper_render_engine->setInline(true);

            try {
                $template = $helper_render_engine->render($data, 'templates/email_v3/email_template.html');
                return $template;
            } catch(Exception $e) {
                return '';
            }
        }
    }

    function renderEmailViewBrowser() {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
        $helper_render_engine->setStripSpecialchars(true);

        $config=WYSIJA::get('config','model');
        $data = $config->viewInBrowserLink();
        if(!isset($data['link'])) {
            return '';
        } else {
            // generate block template
            $viewbrowser = $helper_render_engine->render($data, 'templates/email_v3/viewbrowser_template.html');

            // apply inline styles
            $viewbrowser = $this->applyInlineStyles('viewbrowser', $viewbrowser);

            return $viewbrowser;
        }
    }

    function renderEmailUnsubscribe() {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
        $helper_render_engine->setStripSpecialchars(true);

        $config = WYSIJA::get('config','model');

        $data = array(
            'unsubscribe' => $config->emailFooterLinks(),
            'company_address' => nl2br($config->getValue('company_address'))
        );

        // generate block template
        $unsubscribe = $helper_render_engine->render($data, 'templates/email_v3/unsubscribe_template.html');

        // apply inline styles
        $unsubscribe = $this->applyInlineStyles('unsubscribe', $unsubscribe);

        return $unsubscribe;
    }

    function renderEmailHeader() {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
        $helper_render_engine->setStripSpecialchars(true);

        $data = $this->getData('header');
        $data['styles'] = array('header' => $this->getStyles('header'));

        // check for emptyness
        if($data['text'] === NULL and $data['image']['static'] === TRUE) {
            return NULL;
        }

        // set header content width
        $data['block_width'] = 600;

        // generate block template
        $header = $helper_render_engine->render($data, 'templates/email_v3/header_template.html');

        // apply inline styles
        $header = $this->applyInlineStyles('header', $header);

        return $header;
    }

    function renderEmailBody() {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
        $helper_render_engine->setStripSpecialchars(true);

        $blocks = $this->getData('body');
        $styles = array('body' => $this->getStyles('body'));

        $body = '';

        foreach($blocks as $key => $block) {
            // specific background color
            $block_background_color = null;

            // get background color if specified
            if(isset($block['background_color']) && strlen($block['background_color']) === 6) {
                $block_background_color = $block['background_color'];
            }

            // block width
            $block['block_width'] = 600;

            if($block['type'] === 'auto-post') {
                // special case for auto post, we need to fetch posts, taking previously sent posts into account

                // get email data
                $email = $this->getEmailData();

                // get block params
                $blockParams = $block['params'];

                // format parameters
                $params = array();
                foreach($blockParams as $pairs) {
                    $params[$pairs['key']] = $pairs['value'];
                }

                // make sure empty bgcolors are set to transparent
                if(isset($params['bgcolor1']) && strlen($params['bgcolor1']) === 0) {
                    $params['bgcolor1'] = 'transparent';
                }
                if(isset($params['bgcolor2']) && strlen($params['bgcolor2']) === 0) {
                    $params['bgcolor2'] = 'transparent';
                }

                // get list of post ids already sent if present
                if(!empty($email['params']['autonl']['articles']['ids'])) {
                    $params['exclude'] = $email['params']['autonl']['articles']['ids'];
                } else {
                    if(array_key_exists('autonl', $email['params']) === false) {
                        $email['params']['autonl'] = array();
                    }
                    if(array_key_exists('articles', $email['params']['autonl']) === false) {
                        $email['params']['autonl']['articles'] = array(
                            'ids' => array(),
                            'count' => 0,
                            'first_subject' => ''
                        );
                    }
                }

                //we set the post_date to filter articles only older than that one
                if(isset($email['params']['autonl']['firstSend'])){
                    $params['post_date'] = $email['params']['autonl']['firstSend'];
                }

                 // if immediate let it know to the get post
                if(isset($email['params']['autonl']['articles']['immediatepostid'])){
                    $params['include'] = $email['params']['autonl']['articles']['immediatepostid'];
                    $params['post_limit'] = 1;
                }else{
                    //we set the post_date to filter articles only older than the last time we sent articles
                    if(isset($email['params']['autonl']['lastSend'])){
                        $params['post_date'] = $email['params']['autonl']['lastSend'];
                    }else{
                        //get the latest child newsletter sent_at value
                        $mEmail=WYSIJA::get('email','model');
                        $mEmail->reset();
                        $mEmail->orderBy('email_id','DESC');
                        $lastEmailSent=$mEmail->getOne(false,array('campaign_id'=>$email['campaign_id'],'type'=>'1'));

                        if(!empty($lastEmailSent)) $params['post_date'] = $lastEmailSent['sent_at'];
                    }
                }


                // decode readmore text & nopost message
                $params['readmore'] = trim(base64_decode($params['readmore']));

                // get posts for this block
                $hArticles = WYSIJA::get('articles', 'helper');
                $posts = $hArticles->getPosts($params);
                // cleanup post and get image
                $postIds = array();
                $postCount = 0;

                if(empty($posts)) {
                    // case when there's no post, but there's a message to be displayed
                    if(!isset($params['nopost_message']) || strlen($params['nopost_message']) === 0) {
                        $blockHTML = '';
                    } else {
                        $data = array('text' => array('value' => $params['nopost_message']), 'block_width' => $block['block_width']);
                        $blockHTML = $helper_render_engine->render($data, 'templates/email_v3/block_content.html');
                    }
                } else {
                    $blockHTML = '';
                    $divider = null;

                    // get divider if necessary
                    if($params['show_divider'] === 'yes') {
                        if(isset($email['params']['divider'])) {
                            $divider = $email['params']['divider'];
                        } else {
                            $dividersHelper = WYSIJA::get('dividers', 'helper');
                            $divider = $dividersHelper->getDefault();
                        }
                    }

                    $postIterator= 1;
                    $postCount = count($posts);
                    for($key = 0; $key < $postCount; $key++) {
                        // get post ids
                        $postIds[] = $posts[$key]['ID'];

                        if(strlen(trim($posts[$key]['post_title'])) > 0 and empty($email['params']['autonl']['articles']['first_subject'])) {
                            $email['params']['autonl']['articles']['first_subject'] = trim($posts[$key]['post_title']);
                        }

                        if($params['image_alignment'] !== 'none') {
                            // attempt to get post image
                            $posts[$key]['post_image'] = $hArticles->getImage($posts[$key]);

                            // set image alignment
                            if($params['image_alignment'] === 'alternate') {
                                $image_alignment = ($postIterator > 0) ? 'left' : 'right';
                            } else {
                                $image_alignment = $params['image_alignment'];
                            }
                        } else {
                            // set default alignment
                            $image_alignment = 'left';
                        }

                        // override image alignment in params
                        $post_params = array_merge($params, array('image_alignment' => $image_alignment));

                        // convert post data to block
                        $posts[$key] = $hArticles->convertPostToBlock($posts[$key], $post_params);

                        // set default background color to transparent
                        $posts[$key]['background_color'] = 'transparent';

                        // set background color for each post
                        if(isset($params['bgcolor1']) && $postIterator > 0) {
                            $posts[$key]['background_color'] = $params['bgcolor1'];
                        }
                        if(isset($params['bgcolor2']) && $postIterator < 0) {
                            $posts[$key]['background_color'] = $params['bgcolor2'];
                        }
                        $postIterator *= -1;

                        // render post into block
                        $data = array_merge($posts[$key], array('styles' => $styles, 'block_width' => $block['block_width']));

                        // apply inline styles
                        $blockHTML .= $this->applyInlineStyles('body', $helper_render_engine->render($data, 'templates/email_v3/block_content.html'), array('background_color' => $posts[$key]['background_color']));

                        // add divider if this is not the last post
                        if($divider !== null and $key !== ($postCount - 1)) {
                            $blockHTML .= $helper_render_engine->render(array_merge($divider, array('block_width' => $block['block_width'])), 'templates/email_v3/block_divider.html');
                        }
                    }
                }

                // update email post ids sent
                $email['params']['autonl']['articles']['ids'] = array_unique(array_merge($email['params']['autonl']['articles']['ids'], $postIds));
                // increment posts count
                if(!isset($email['params']['autonl']['articles']['count'])) $email['params']['autonl']['articles']['count']=0;
                $email['params']['autonl']['articles']['count'] = (int)$email['params']['autonl']['articles']['count'] + $postCount;

                $this->setEmailData($email);
            } else {
                // set styles
                $block['styles'] = $styles;
                // generate block template
                $blockHTML = $helper_render_engine->render($block, 'templates/email_v3/block_template.html');

                if($block['type'] !== 'raw') {
                    // apply inline styles
                    $blockHTML = $this->applyInlineStyles('body', $blockHTML, array('background_color' => $block_background_color));
                }
            }

            // append generated html to body
            if($blockHTML !== '') {
                // render each block
                $body .= $blockHTML;
            }
        }

        return $body;
    }

    function renderEmailFooter() {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
        $helper_render_engine->setStripSpecialchars(true);

        $data = $this->getData('footer');
        $data['styles'] = array('footer' => $this->getStyles('footer'));

        // check for emptyness
        if($data['text'] === NULL and $data['image']['static'] === TRUE) {
            return NULL;
        }

        // set footer content width
        $data['block_width'] = 600;

        // generate block template
        $footer = $helper_render_engine->render($data, 'templates/email_v3/footer_template.html');

        // apply inline styles
        $footer = $this->applyInlineStyles('footer', $footer);

        return $footer;
    }

    /**
     * area : header, body, footer, unsubscribe, viewbrowser
     * block : raw html
     */
    function applyInlineStyles($area, $block, $extra = array()) {
        $helper_render_engine = WYSIJA::get('render_engine', 'helper');
        $helper_render_engine->setTemplatePath(WYSIJA_EDITOR_TOOLS);
        $helper_render_engine->setInline(true);

        $tags = array();
        $classes = array();

        switch($area) {
            case 'header':
            case 'footer':
                $classes = array(
                    'wysija-image-container alone-left' => array('margin' => '0', 'padding' => '0'),
                    'wysija-image-container alone-center' => array('margin' => '0 auto 0 auto', 'padding' => '0', 'text-align' => 'center'),
                    'wysija-image-container alone-right' => array('margin' => '0', 'padding' => '0')
                );
            break;

            case 'body':
                // set class for links in titles
                $block = preg_replace_callback('#(<h([1|2|3])[^>]*>(.*)<\/h[1|2|3]>)#Ui',
                    create_function('$matches', '$class = \'h\'.(int)$matches[2].\'-link\'; return str_replace(\'<a\', \'<a class="\'.$class.\'"\', $matches[0]);'),
                    $block);

                $tags = array(
                    'h1' => array_merge($this->getStyles('h1'), array('word-wrap' => true, 'padding' => '0', 'margin' => '0 0 10px 0', 'font-weight' => 'normal', 'line-height' => '1.3em')),
                    'h2' => array_merge($this->getStyles('h2'), array('word-wrap' => true, 'padding' => '0', 'margin' => '0 0 10px 0', 'font-weight' => 'normal', 'line-height' => '1.2em')),
                    'h3' => array_merge($this->getStyles('h3'), array('word-wrap' => true, 'padding' => '0', 'margin' => '0 0 10px 0', 'font-weight' => 'normal', 'line-height' => '1.1em')),
                    'p' => array_merge($this->getStyles('body'), array('word-wrap' => true, 'padding' => '3px 0 0 0', 'margin' => '0 0 1em 0', 'line-height' => '1.5em', 'vertical-align' => 'top')),
                    'a' => array_merge($this->getStyles('body'), $this->getStyles('a')),
                    'ul' => array('line-height' => '1.5em', 'margin' => '0 0 1em 0', 'padding' => '0'),
                    'ol' => array('line-height' => '1.5em', 'margin' => '0 0 1em 0', 'padding' => '0'),
                    'li' => array_merge($this->getStyles('body'), array('font-weight' => 'normal', 'list-type' => 'none', 'list-style-type' => 'disc', 'margin' => '0 0 0.7em 30px', 'padding' => '0'))
                );

                $classes = array(
                    'wysija-image-container alone-left' => array('margin' => '0', 'padding' => '0'),
                    'wysija-image-container alone-center' => array('margin' => '1em auto 1em auto', 'padding' => '0', 'text-align' => 'center'),
                    'wysija-image-container alone-right' => array('margin' => '0', 'padding' => '0'),
                    'wysija-image-left' => array('vertical-align' => 'top'),
                    'wysija-image-center' => array('margin' => '0 auto 0 auto', 'vertical-align' => 'top'),
                    'wysija-image-right' => array('vertical-align' => 'top'),
                    'wysija-image-container align-left' => array('float' => 'left', 'margin' => '0', 'padding' => '0'),
                    'wysija-image-container align-center' => array('margin' => '0 auto 0 auto', 'text-align' => 'center', 'padding' => '0'),
                    'wysija-image-container align-right' => array('float' => 'right', 'margin' => '0', 'padding' => '0'),
                    'wysija-divider-container' => array('margin' => '0 auto 0 auto', 'padding' => '0', 'text-align' => 'center'),
                    'h1-link' => array_merge($this->getStyles('h1'), $this->getStyles('a')),
                    'h2-link' => array_merge($this->getStyles('h2'), $this->getStyles('a')),
                    'h3-link' => array_merge($this->getStyles('h3'), $this->getStyles('a')),
                    'align-left' => array('text-align' => 'left'),
                    'align-center' => array('text-align' => 'center'),
                    'align-right' => array('text-align' => 'right'),
                    'align-justify' => array('text-align' => 'justify')
                );

                // when an extra background_color is specified, apply it to paragraphs & links
                if(array_key_exists('background_color', $extra) and $extra['background_color'] !== null) {
                    $tags['p']['background'] = $extra['background_color'];
                    $tags['a']['background'] = $extra['background_color'];
                    $tags['ul']['background'] = $extra['background_color'];
                    $tags['li']['background'] = $extra['background_color'];
                    // fixes issue on Outlook.com Mobile where h2 have a white background
                    $tags['h2']['background'] = $extra['background_color'];
                } else {
                    // default newsletter background
                    // fixes issue on Outlook.com Mobile where h2 have a white background
                    $tags['h2']['background'] = $this->getStyle('body', 'background');
                }
            break;

            case 'unsubscribe':
                $tags = array(
                    'a' => $this->getStyles('unsubscribe')
                );
            break;
            case 'viewbrowser':
                $tags = array(
                    'a' => $this->getStyles('viewbrowser')
                );
            break;
        }

        if(empty($tags) === FALSE) {

            foreach($tags as $tag => $styles) {
                $styles = $this->splitSpacing($styles);
                $inlineStyles = $helper_render_engine->render(array_merge($styles, array('tag' => $tag)), 'styles/inline.html');
                $inlineStyles = preg_replace('/(\n*)/', '', $inlineStyles);
                $tags['#< *'.$tag.'((?:(?!style).)*)>#Ui'] = '<'.$tag.' style="'.$inlineStyles.'"$1>';
                unset($tags[$tag]);
            }

            $block = preg_replace(array_keys($tags), $tags, $block);
        }

        if(empty($classes) === FALSE) {
            foreach($classes as $class => $styles) {
                // split spacing styles
                $styles = $this->splitSpacing($styles);
                $inlineStyles = $helper_render_engine->render($styles, 'styles/inline.html');
                $inlineStyles = preg_replace('/(\n*)/', '', $inlineStyles);

                if(in_array($class, array('h1-link', 'h2-link', 'h3-link'))) {
                    $classes['#<([^ /]+) ((?:(?!>|style).)*)(?:style="([^"]*)")?((?:(?!>|style).)*)class="[^"]*'.$class.'[^"]*"((?:(?!>|style).)*)(?:style="([^"]*)")?((?:(?!>|style).)*)>#Ui'] = '<$1 $2$4$5$7 style="'.$inlineStyles.'">';
                } else {
                    $classes['#<([^ /]+) ((?:(?!>|style).)*)(?:style="([^"]*)")?((?:(?!>|style).)*)class="[^"]*'.$class.'[^"]*"((?:(?!>|style).)*)(?:style="([^"]*)")?((?:(?!>|style).)*)>#Ui'] = '<$1 $2$4$5$7 style="$3$6'.$inlineStyles.'">';
                }
                unset($classes[$class]);
            }

            $styledBlock = preg_replace(array_keys($classes), $classes, $block);
            // Check if the preg_replace worked. Otherwise we simply return the original block
            if(strlen(trim($styledBlock)) > 0) {
                $block = $styledBlock;
            }
        }

        // body
        if($area === 'body' && strlen($block) > 0) {
            // Outlook fixes
            // paragraph
            /*$block = preg_replace('#<\/p>#Ui', "<!--[if gte mso 9]></p><![endif]--></p>", $block);
            $block = preg_replace('#<p(.*)>#Ui', "\n<p$1><!--[if gte mso 9]></p><p class=\"wysija-fix-paragraph\"><![endif]-->", $block);
            // h2 titles
            $block = preg_replace('#<\/h2>#Ui', "<!--[if gte mso 9]></h2><![endif]--></h2>", $block);
            $block = preg_replace('#<h2(.*)>#Ui', "<h2$1><!--[if gte mso 9]></h2><h2 class=\"wysija-fix-h2\"><![endif]-->", $block);
            // h3 titles
            $block = preg_replace('#<\/h3>#Ui', "<!--[if gte mso 9]></h3><![endif]--></h3>", $block);
            $block = preg_replace('#<h3(.*)>#Ui', "<h3$1><!--[if gte mso 9]></h3><h3 class=\"wysija-fix-h3\"><![endif]-->", $block);

            // lists
            $block = preg_replace('#<ol(.*)>#Ui', "\n<ul$1>", $block);
            $block = preg_replace('#<ul(.*)>#Ui', "\n<ul$1>", $block);
            $block = preg_replace('#<li(.*)>#Ui', "\n<li$1>", $block);

            $pFixStyles = $this->splitSpacing(array_merge($this->getStyles('body'), array('padding' => '3px 0 0 0', 'margin' => '0 0 1.3em 0', 'line-height' => '1em', 'vertical-align' => 'top')));
            $h2FixStyles = $this->splitSpacing(array_merge($this->getStyles('h2'), array('padding' => '0', 'margin' => '0 0 10px 0', 'font-weight' => 'normal', 'line-height' => '1em')));
            $h3FixStyles = $this->splitSpacing(array_merge($this->getStyles('h3'), array('padding' => '0', 'margin' => '0 0 10px 0', 'font-weight' => 'normal', 'line-height' => '1em')));

            // apply block background color to elements if specified
            if(array_key_exists('background_color', $extra) and $extra['background_color'] !== null) {
                $pFixStyles['background'] = $extra['background_color'];
                $h2FixStyles['background'] = $extra['background_color'];
                $h3FixStyles['background'] = $extra['background_color'];
            }

            $block = str_replace('class="wysija-fix-paragraph"', 'style="'.$helper_render_engine->render($pFixStyles, 'styles/inline.html').'"', $block);
            $block = str_replace('class="wysija-fix-h2"', 'style="'.$helper_render_engine->render($h2FixStyles, 'styles/inline.html').'"', $block);
            $block = str_replace('class="wysija-fix-h3"', 'style="'.$helper_render_engine->render($h3FixStyles, 'styles/inline.html').'"', $block);*/
        }

        return $block;
    }

    function splitSpacing($styles) {
        foreach($styles as $property => $value) {
            if($property === 'margin' or $property === 'padding') {
                // extract multi-values
                $values = explode(' ', $value);

                // split values depending on values count
                switch(count($values)) {
                    case 1:
                        $styles[$property.'-top'] = $values[0];
                        $styles[$property.'-right'] = $values[0];
                        $styles[$property.'-bottom'] = $values[0];
                        $styles[$property.'-left'] = $values[0];
                    break;
                    case 2:
                        $styles[$property.'-top'] = $values[0];
                        $styles[$property.'-right'] = $values[1];
                        $styles[$property.'-bottom'] = $values[0];
                        $styles[$property.'-left'] = $values[1];
                    break;
                    case 4:
                        $styles[$property.'-top'] = $values[0];
                        $styles[$property.'-right'] = $values[1];
                        $styles[$property.'-bottom'] = $values[2];
                        $styles[$property.'-left'] = $values[3];
                    break;
                }

                // unset original value
                unset($styles[$property]);
            }
        }
        return $styles;
    }

    function formatColor($color) {
        if(strlen(trim($color)) === 0 or $color === 'transparent') {
            return 'transparent';
        } else {
            return '#'.$color;
        }
    }
}
