<?php
    if(!is_plugin_active(basename(dirname(dirname(__FILE__))) . "/sitepress.php")){
        ?>
        <h2><?php echo __('Setup WPML', 'sitepress') ?></h2>
        <div class="updated fade">
            <p style="line-height:1.5"><?php _e('The WPML Multilingual CMS plugin is not currently enabled.', 'sitepress');?></p>
            <p style="line-height:1.5"><?php printf(__('Please go to the <a href="%s">Plugins</a> page and enable the WPML Multilingual CMS plugin before trying to configure the plugin.', 'sitepress'), 'plugins.php');?></p>
        </div>
        <?php
        return;
    }

    if (isset($_GET['trop'])) { require_once dirname(__FILE__).'/edit-languages.php'; return; }
    if(!$sitepress_settings['existing_content_language_verified']){
        // try to determine the blog language
        $blog_current_lang = 0;
        if($blog_lang = get_option('WPLANG')){
            $exp = explode('_',$blog_lang);
            $blog_current_lang = $wpdb->get_var("SELECT code FROM {$wpdb->prefix}icl_languages WHERE code='{$exp[0]}'");
        }
        if(!$blog_current_lang && defined('WPLANG') && WPLANG != ''){
            $blog_current_lang = $wpdb->get_var($wpdb->prepare("SELECT code FROM {$wpdb->prefix}icl_languages WHERE default_locale=%s", WPLANG));
            if(!$blog_current_lang){
                $blog_lang = WPLANG;
                $exp = explode('_',$blog_lang);
                $blog_current_lang = $wpdb->get_var("SELECT code FROM {$wpdb->prefix}icl_languages WHERE code='{$exp[0]}'");
            }
        }
        if(!$blog_current_lang){
            $blog_current_lang = 'en';
        }
        $languages = $sitepress->get_languages($blog_current_lang);
    }else{
        $active_languages = $sitepress->get_active_languages();
        $languages = $sitepress->get_languages($sitepress->get_admin_language());
        $sitepress_settings = $sitepress->get_settings();
        foreach($active_languages as $lang){
            if($lang['code'] != $sitepress->get_default_language()){
                $sample_lang = $lang;
                break;
            }
        }
        $default_language = $sitepress->get_language_details($sitepress->get_default_language());
        $inactive_content = $sitepress->get_inactive_content();
    }

global $language_switcher_defaults, $language_switcher_defaults_alt;
?>
<?php $sitepress->noscript_notice() ?>

<div class="wrap <?php if( empty($sitepress_settings['setup_complete']) ): ?>wpml-wizard<?php endif; ?>">
    <div id="icon-wpml" class="icon32" ><br /></div>
    <h2><?php _e('Setup WPML', 'sitepress') ?></h2>

    <?php if( empty($sitepress_settings['setup_complete']) ): /* setup wizard */ ?>
        <?php
            if(!$sitepress_settings['existing_content_language_verified']){
                $sw_width = 20;
            }elseif(count($sitepress->get_active_languages()) < 2 || $sitepress_settings['setup_wizard_step'] == 2){
                $sw_width = 50;
            }else{
                $sw_width = 80;
            }
        ?>
        <div id="icl_setup_wizard_wrap" class="wpml-section wpml-section-wizard-steps">
            <div class="wpml-section-header">
                <h3><?php _e('Before you can start using WPML, it needs to be set up', 'sitepress') ?></h3>
            </div>
            <div id="icl_setup_wizard" class="wpml-section-content">
                <div class="icl_setup_wizard_step"><?php _e('1. Language for existing contents', 'sitepress')?></div>
                <div class="icl_setup_wizard_step"><?php _e('2. Select languages', 'sitepress')?></div>
                <div class="icl_setup_wizard_step"><?php _e('3. Add a language switcher', 'sitepress')?></div>
            </div>
            <br clear="all" />
            <div id="icl_setup_wizard_progress">
                <div id="icl_setup_wizard_progress_bar" style="width:<?php echo $sw_width ?>%">&nbsp;</div>
            </div>
        </div>
    <?php endif; /* setup wizard */ ?>

    <?php if(!$sitepress_settings['existing_content_language_verified']): ?>
    <div class="wpml-section">
        <div class="wpml-section-header">
            <h3><?php _e('Current content language', 'sitepress') ?></h3>
        </div>

        <div class="wpml-section-content">
            <form id="icl_initial_language" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">

                <?php wp_nonce_field('icl_initial_language','icl_initial_languagenonce') ?>
                <p>
                    <?php _e('Before adding other languages, please select the language existing contents are written in:', 'sitepress') ?>
                </p>
                <?php
                    // for the wizard
                    if($sitepress->get_default_language()){
                        $blog_current_lang = $sitepress->get_default_language();
                    }
                ?>
                <p>
                    <select name="icl_initial_language_code">
                    <?php foreach($languages as $lang):?>
                        <option <?php if($blog_current_lang==$lang['code']):?>selected<?php endif;?> value="<?php echo $lang['code']?>"><?php echo $lang['display_name']?></option>
                    <?php endforeach; ?>
                    </select>
                </p>
                <p class="buttons-wrap">
                    <input class="button-primary" name="save" value="<?php _e('Next', 'sitepress') ?>" type="submit" />
                    <?php /*
                    <input class="button-primary" name="save" value="<?php echo __('Add more languages', 'sitepress') ?> &raquo;" type="submit" />
                    <input class="button" name="save_one_language" value="<?php echo __('Done (just one language)', 'sitepress') ?>" type="submit" />
                    */ ?>
                </p>
            </form>
        </div>
    </div> <!-- .wpml-section -->
    <?php else: ?>
        <?php if(!empty($sitepress_settings['setup_complete']) || $sitepress_settings['setup_wizard_step'] == 2): ?>
            <?php if(!empty($sitepress_settings['setup_complete']) && (count($active_languages) > 1)): ?>
                <p>
                    <strong><?php _e('This screen contains the language settings for your site.','sitepress'); ?></strong>
                </p>
                <ul class="wpml-navigation-links js-wpml-navigation-links">
                    <li><a href="#lang-sec-1"><?php _e('Site Languages','sitepress'); ?></a></li>
                    <li><a href="#lang-sec-2"><?php _e('Language URL format', 'sitepress'); ?></a></li>
                    <li><a href="#lang-sec-3"><?php _e('Language switcher options', 'sitepress'); ?></a></li>
                    <li><a href="#lang-sec-4"><?php _e('Admin language', 'sitepress'); ?></a></li>
                    <li><a href="#lang-sec-6"><?php _e('Blog posts to display', 'sitepress'); ?></a></li>
                    <li><a href="#lang-sec-7"><?php _e('Hide languages', 'sitepress'); ?></a></li>
                    <li><a href="#lang-sec-8"><?php _e('Make themes work multilingual', 'sitepress'); ?></a></li>
                    <li><a href="#lang-sec-9"><?php _e('Browser language redirect', 'sitepress'); ?></a></li>
                    <li><a href="#lang-sec-9-5"><?php _e('SEO Options', 'sitepress'); ?></a></li>
                    <li><a href="#lang-sec-10"><?php _e('WPML love', 'sitepress'); ?></a></li>
                </ul>
            <?php endif; ?>

            <div id="lang-sec-1" class="wpml-section wpml-section-languages">
                <div class="wpml-section-header">
                    <h3><?php _e('Site Languages', 'sitepress') ?></h3>
                </div>

                <div class="wpml-section-content">
                    <div class="wpml-section-content-inner">
                        <?php if(!empty($sitepress_settings['setup_complete'])): ?>
                            <h4><?php _e('These languages are enabled for this site:','sitepress'); ?></h4>
                            <ul id="icl_enabled_languages" class="enabled-languages">
                                    <?php foreach($active_languages as $lang): $is_default = ($sitepress->get_default_language()==$lang['code']); ?>
                                    <?php
                                    if(!empty($sitepress_settings['hidden_languages']) && in_array($lang['code'], $sitepress_settings['hidden_languages'])){
                                        $hidden = '&nbsp<strong style="color:#f00">('.__('hidden', 'sitepress').')</strong>';
                                    }else{
                                        $hidden = '';
                                    }
                                    ?>
                                <li <?php if($is_default):?>class="selected"<?php endif;?>><label><input name="default_language" type="radio" value="<?php echo $lang['code'] ?>" <?php if($is_default):?>checked="checked"<?php endif;?> /> <?php echo $lang['display_name'] . $hidden ?> <?php if($is_default):?>(<?php echo __('default', 'sitepress') ?>)<?php endif?></label></li>
                                <?php endforeach ?>
                            </ul>
                        <?php else: ?>
                            <p><?php _e('Select the languages to enable for your site (you can also add and remove languages later).','sitepress'); ?></p>
                        <?php endif; ?>
                        <?php wp_nonce_field('set_default_language_nonce', 'set_default_language_nonce'); ?>
                        <p class="buttons-wrap">
                            <button id="icl_cancel_default_button" class="button-secondary action"><?php _e('Cancel', 'sitepress') ?></button>
                            <button id="icl_save_default_button" class="button-primary action"><?php _e('Save', 'sitepress') ?></button>
                        </p>
                        <?php if(!empty($sitepress_settings['setup_complete'])): ?>
                            <p>
                                <button id="icl_change_default_button" class="button-secondary action <?php if(count($active_languages) < 2): ?>hidden<?php endif ?>"><?php _e('Change default language', 'sitepress') ?></button>
                                <button id="icl_add_remove_button" class="button-secondary action"><?php _e('Add / Remove languages', 'sitepress') ?></button>
                            </p>
                            <p class="icl_ajx_response" id="icl_ajx_response"></p>
                        <?php endif; ?>
                        <div id="icl_avail_languages_picker" class="<?php if( !empty($sitepress_settings['setup_complete']) ) echo 'hidden'; ?>">
                            <ul class="available-languages">
                            <?php foreach($languages as $lang): ?>
                                <li><label><input type="checkbox" value="<?php echo $lang['code'] ?>" <?php if($lang['active']):?>checked="checked"<?php endif;?>
                                <?php if($sitepress->get_default_language()==$lang['code']):?>disabled="disabled"<?php endif;?>/>
                                    <?php if($lang['major']):?><strong><?php endif;?><?php echo $lang['display_name'] ?><?php if($lang['major']):?></strong><?php endif;?></label></li>
                            <?php endforeach ?>
                            </ul>
                            <?php if(!empty($sitepress_settings['setup_complete'])): ?>
                            <p class="buttons-wrap">
                                <input id="icl_cancel_language_selection" type="button" class="button-secondary action" value="<?php _e('Cancel', 'sitepress') ?>" />
                                <input id="icl_save_language_selection" type="button" class="button-primary action" value="<?php _e('Save', 'sitepress') ?>" />
                            </p>
                            <?php endif; ?>
                            <?php wp_nonce_field('set_active_languages_nonce', 'set_active_languages_nonce'); ?>
                        </div>

                        <?php if (!empty($sitepress_settings['setup_complete'])): ?>
                            <p>
                                <a href="admin.php?page=<?php echo ICL_PLUGIN_FOLDER ?>/menu/languages.php&amp;trop=1"><?php _e('Edit Languages','sitepress'); ?></a>
                            </p>
                        <?php endif; ?>
                    </div> <!-- wpml-section-content-inner -->


                    <?php if( !empty($inactive_content) ): ?>
                        <div class="wpml-section-content-inner">
                            <?php
                                $t_posts = $t_pages = $t_cats = $t_tags = 0;
                                foreach($inactive_content as $language=>$ic){
                                    $t_posts += @$ic['post'];
                                    $t_pages += @$ic['page'];
                                    $t_cats += @$ic['category'];
                                    $t_tags += @$ic['post_tag'];
                                }
                            ?>
                            <h4><?php _e('Inactive content', 'sitepress') ?></h4>
                            <p class="explanation-text"><?php _e('In order to edit or delete these you need to activate the corresponding language first', 'sitepress') ?></p>
                            <table class="widefat inactive-content-table" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php _e('Language', 'sitepress') ?></th>
                                        <th scope="col"><?php _e('Posts', 'sitepress') ?></th>
                                        <th scope="col"><?php _e('Pages', 'sitepress') ?></th>
                                        <th scope="col"><?php _e('Categories', 'sitepress') ?></th>
                                        <th scope="col"><?php _e('Tags', 'sitepress') ?></th>
                                    </tr>
                                </thead>
                                <tfoot>
                                    <tr>
                                        <th scope="col"><?php _e('Total', 'sitepress') ?></th>
                                        <td scope="col"><?php echo @intval($t_posts) ?></td>
                                        <td scope="col"><?php echo @intval($t_pages) ?></td>
                                        <td scope="col"><?php echo @intval($t_cats) ?></td>
                                        <td scope="col"><?php echo @intval($t_tags) ?></td>
                                    </tr>
                                </tfoot>
                                <tbody>
                                    <?php foreach($inactive_content as $language=>$ic): ?>
                                        <tr>
                                            <th scope="col"><?php echo $language ?></th>
                                            <td scope="col"><?php echo @intval($ic['post']); ?></td>
                                            <td scope="col"><?php echo @intval($ic['page']); ?></td>
                                            <td scope="col"><?php echo @intval($ic['category']); ?></td>
                                        <td scope="col"><?php echo @intval($ic['post_tag']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div> <!-- wpml-section-content-inner -->
                    <?php endif; ?>

                    <?php if($sitepress_settings['setup_wizard_step']==2): ?>
                        <div style="text-align:right">
                            <input id="icl_setup_back_1" class="button-primary" name="save" value="<?php echo __('Back', 'sitepress') ?>" type="button" />
                            <?php wp_nonce_field('setup_got_to_step1_nonce', '_icl_nonce_gts1'); ?>
                            <input id="icl_setup_next_1" class="button-primary" name="save" value="<?php echo __('Next', 'sitepress') ?>" type="button" <?php if(count($active_languages) < 2):?>disabled="disabled"<?php endif;?> />
                        </div>
                    <?php endif; ?>

                </div> <!-- .wcml-section-content -->
            </div> <!-- .wpml-section-languages -->

        <?php endif; ?>


        <?php if(!empty($sitepress_settings['setup_complete'])): ?>
            <?php if(count($active_languages) > 1): ?>
                <div class="wpml-section wpml-section-url-format" id="lang-sec-2">
                    <div class="wpml-section-header">
                        <h3><?php _e('Language URL format', 'sitepress'); ?></h3>
                    </div>
                    <div class="wpml-section-content">
                        <h4><?php _e('Choose how to determine which language visitors see contents in', 'sitepress'); ?></h4>
                        <form id="icl_save_language_negotiation_type" name="icl_save_language_negotiation_type" action="">
                            <?php wp_nonce_field('icl_save_language_negotiation_type_nonce', '_icl_nonce') ?>
                            <ul>
                                <?php
                                    if(!class_exists('WP_Http')) include_once ABSPATH . WPINC . '/class-http.php';
                                    $client = new WP_Http();
                                    if (empty($_POST['url']) || false === strpos($_POST['url'],'?')){$url_glue='?';}else{$url_glue='&';}
                                    $response = $client->request(get_option('home') . '/' . $sample_lang['code'] .'/' . $url_glue . '____icl_validate_domain=1', array('timeout'=>15, 'decompress'=>false));
                                    if (!is_wp_error($response) && ($response['response']['code']=='200') && ($response['body'] == '<!--'.get_option('home').'-->')){
                                        $icl_folder_url_disabled = false;
                                    }else {
                                        $icl_folder_url_disabled = true;
                                    }
                                ?>
                                <li>
                                    <label>
                                        <input type="radio" name="icl_language_negotiation_type" value="1" <?php if($sitepress_settings['language_negotiation_type']==1):?>checked<?php endif?> />
                                        <?php _e('Different languages in directories', 'sitepress'); ?>
                                        <span class="explanation-text">
                                        (<?php
                                            echo sprintf('%s%s - %s, %s - %s',
                                            trailingslashit(get_option('home')),
                                            empty($sitepress_settings['urls']['directory_for_default_language']) ? '' : trailingslashit($sitepress->get_default_language()),
                                            $default_language['display_name'] ,
                                            trailingslashit(get_option('home') . '/' . $sample_lang['code']),
                                            $sample_lang['display_name'] );
                                        ?>)
                                        </span>
                                    </label>

                                    <div id="icl_use_directory_wrap" style="<?php if($sitepress_settings['language_negotiation_type'] != 1): ?>display:none;<?php endif; ?>" >
                                        <p class="sub-section">
                                            <label>
                                                <input type="checkbox" name="use_directory" id="icl_use_directory" value="1" <?php if(!empty($sitepress_settings['urls']['directory_for_default_language'])):?>checked<?php endif; ?> />
                                                <?php _e('Use directory for default language', 'sitepress') ?>
                                            </label>
                                        </p>

                                        <div id="icl_use_directory_details" class="sub-section" <?php if(empty($sitepress_settings['urls']['directory_for_default_language'])) echo ' style="display:none"'  ?> >

                                            <p><?php _e('What to show for the root url:', 'sitepress') ?></p>

                                            <ul>
                                                <li>
                                                    <label>
                                                        <input id="wpml_show_on_root_html_file" type="radio" name="show_on_root" value="html_file" <?php if($sitepress_settings['urls']['show_on_root'] == 'html_file'):
                                                        ?>checked="checked"<?php endif; ?> />
                                                        <?php _e('HTML file', 'sitepress') ?> &ndash; <span class="explanation-text"><?php _e('please enter path: absolute or relative to the WordPress installation folder','sitepress'); ?></span>
                                                    </label>
                                                    <p>
                                                        <input type="text" name="root_html_file_path" value="<?php echo $sitepress_settings['urls']['root_html_file_path']?>" />
                                                        <span class="icl_error_text icl_error_1" style="display: none;"><?php _e('Please select what to show for the root url.', 'sitepress') ?></span>
                                                    </p>
                                                </li>

                                                <li>
                                                    <label>
                                                        <input id="wpml_show_on_root_page" type="radio" name="show_on_root" value="page" <?php if($sitepress_settings['urls']['show_on_root'] == 'page'): ?>checked<?php endif; ?>  <?php if($sitepress_settings['urls']['show_on_root'] == 'page'):?>class="active"<?php endif; ?> />
                                                        <?php _e('A page', 'sitepress') ?>

                                                        <span style="display: none;" id="wpml_show_page_on_root_x"><?php echo esc_js(__("Please save the settings first by clicking Save.", 'sitepress')); ?></span>

                                                        <span id="wpml_show_page_on_root_details" <?php if($sitepress_settings['urls']['show_on_root'] != 'page'):
                                                        ?>style="display:none"<?php endif; ?>>
                                                        <?php
                                                        $rp_exists = false;
                                                        if(!empty($sitepress_settings['urls']['root_page'])){
                                                            $rp = get_post($sitepress_settings['urls']['root_page']);
                                                            if($rp && $rp->post_status != 'trash'){
                                                                $rp_exists = true;
                                                            }
                                                        }
                                                        ?>
                                                        <?php if($rp_exists): ?>
                                                            <a href="<?php echo get_edit_post_link($sitepress_settings['urls']['root_page']) ?>"><?php _e('Edit root page.', 'sitepress') ?></a>
                                                        <?php else: ?>
                                                            <a href="<?php echo admin_url('post-new.php?post_type=page&wpml_root_page=1') ?>"><?php _e('Create root page.', 'sitepress') ?></a>
                                                        <?php endif; ?>
                                                        </span>
                                                    </label>
                                                </li>

                                                <p id="icl_hide_language_switchers" class="sub-section" <?php if($sitepress_settings['urls']['show_on_root'] != 'page'): ?>style="display:none"<?php endif; ?>>
                                                    <label>
                                                        <input type="checkbox" name="hide_language_switchers" id="icl_hide_language_switchers" value="1" <?php checked($sitepress_settings['urls']['hide_language_switchers']) ?> />
                                                        <?php _e('Hide language switchers on the root page', 'sitepress') ?>
                                                    </label>
                                                </p>

                                            </ul>

                                        </div>
                                    </div>

                                    <?php if($icl_folder_url_disabled):?>
                                    <div class="icl_error_text" style="margin:10px;">
                                        <p>
                                            <?php _e('It looks like languages per directories will not function.', 'sitepress'); ?>
                                            <a href="#" onClick="jQuery(this).parent().parent().next().toggle();return false">Details</a>
                                        </p>
                                    </div>
                                    <div class="icl_error_text" style="display:none;margin:10px;">
                    					<p><?php _e('This can be a result of either:') ?></p>
                    					<ul>
                        					<li><?php _e("WordPress is installed in a directory (not root) and you're using default links.",'sitepress') ?></li>
                        					<li><?php _e("URL rewriting is not enabled in your web server.",'sitepress') ?></li>
                                            <li><?php _e("The web server cannot write to the .htaccess file",'sitepress') ?></li>
                    					</ul>
                                        <a href="http://wpml.org/?page_id=1010"><?php _e('How to fix','sitepress') ?></a>
                                            <p>
                                                <?php printf(__('When WPML accesses <a target="_blank" href="%s">%s</a> it gets:', 'sitepress'), $__url = get_option('home').'/' . $sample_lang['code'] .'/?____icl_validate_domain=1', $__url); ?>
                                                <br />
                                                <?php
                                                    if(is_wp_error($response)){
                                                        echo '<strong>';
                                                        echo $response->get_error_message();
                                                        echo '</strong>';
                                                    }elseif($response['response']['code']!='200'){
                                                        echo '<strong>';
                                                        printf(__('HTTP code: %s (%s)', 'sitepress'), $response['response']['code'], $response['response']['message']);
                                                        echo '</strong>';
                                                    }else{
                                                        echo '<div style="width:100%;height:150px;overflow:auto;background-color:#fff;color:#000;font-family:Courier;font-style:normal;border:1px solid #aaa;">'.htmlentities($response['body']).'</div>';
                                                    }
                                                ?>
                                            </p>
                                            <p>
                                                <?php printf(__('The expected value is: %s', 'sitepress'), '<br /><strong>&lt;!--'.get_option('home').'--&gt;</strong>'); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </li>
                                <?php
                                global $wpmu_version;
                                if(isset($wpmu_version) || function_exists('is_multisite') && is_multisite() && (!defined('WPML_SUNRISE_MULTISITE_DOMAINS') || !WPML_SUNRISE_MULTISITE_DOMAINS)){
                                    $icl_lnt_disabled = 'disabled="disabled" ';
                                }else{
                                    $icl_lnt_disabled = '';
                                }
                                ?>
                                <li>
                                    <label>
                                        <input <?php echo $icl_lnt_disabled ?>id="icl_lnt_domains" type="radio" name="icl_language_negotiation_type" value="2" <?php if($sitepress_settings['language_negotiation_type']==2):?>checked<?php endif?> />
                                        <?php _e('A different domain per language', 'sitepress') ?>
                                        <?php if($icl_lnt_disabled): ?>
                                            <span class="icl_error_text"><?php _e('This option is not yet available for Multisite installs', 'sitepress')?></span>
                                        <?php endif; ?>
                                        <?php if(defined('WPML_SUNRISE_MULTISITE_DOMAINS') && WPML_SUNRISE_MULTISITE_DOMAINS): ?>
                                            <span class="icl_error_text"><?php _e('Experimental', 'sitepress')?></span>
                                        <?php endif; ?>
                                    </label>
                                    <?php wp_nonce_field('language_domains_nonce', '_icl_nonce_ldom', false); ?>
                                    <?php wp_nonce_field('validate_language_domain_nonce', '_icl_nonce_vd', false); ?>
                                    <?php if($sitepress_settings['language_negotiation_type']==2):?>
                                    <div id="icl_lnt_domains_box">
                                        <table class="language_domains">
                                        <?php foreach($active_languages as $lang) :?>
                                            <tr>
                                                <td><?php echo $lang['display_name'] ?></td>
                                                <?php if($lang['code']==$sitepress->get_default_language()): ?>
                                                    <td id="icl_ln_home"><?php echo get_option('home') ?></td>
                                                    <td>&nbsp;</td>
                                                    <td>&nbsp;</td>
                                                <?php else: ?>
                                                    <td><input type="text" id="language_domain_<?php echo $lang['code'] ?>" name="language_domains[<?php echo $lang['code'] ?>]" value="<?php echo $sitepress_settings['language_domains'][$lang['code']] ?>" size="40" /></td>
                                                    <td><label><input class="validate_language_domain" type="checkbox" name="validate_language_domains[]" value="<?php echo $lang['code'] ?>" checked /> <?php _e('Validate on save', 'sitepress') ?></td>
                                                    <td><span id="ajx_ld_<?php echo $lang['code'] ?>"></span></td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </li>
                                <li>
                                    <label>
                                        <input type="radio" name="icl_language_negotiation_type" value="3" <?php if($sitepress_settings['language_negotiation_type']==3):?>checked<?php endif?> />
                                        <?php _e('Language name added as a parameter', 'sitepress') ?>
                                        <span class="explanation-text"><?php echo sprintf('(%s?lang=%s - %s)',get_option('home'),$sample_lang['code'],$sample_lang['display_name']) ?></span>
                                    </label>
                                </li>
                            </ul>
                            <p class="buttons-wrap">
                                <span class="icl_ajx_response" id="icl_ajx_response2"></span>
                                <input class="button button-primary" name="save" value="<?php _e('Save','sitepress') ?>" type="submit" />
                            </p>
                        </form>
                    </div>
                </div> <!-- .wpml-section-url-format -->
            <?php endif; ?>
        <?php endif; ?>


        <?php if(!empty($sitepress_settings['setup_complete']) && count($active_languages) > 1 || $sitepress_settings['setup_wizard_step']==3): ?>
            <div class="wpml-section wpml-section-language-switcher" id="lang-sec-3">
                <form id="icl_save_language_switcher_options" name="icl_save_language_switcher_options" action="">
                    <?php wp_nonce_field('icl_save_language_switcher_options_nonce', '_icl_nonce'); ?>

                    <div class="wpml-section-header">
                        <h3><?php _e('Language switcher options', 'sitepress') ?></h3>
                    </div>

                    <div class="wpml-section-content">

                        <div class="wpml-section-content-inner">
                            <p class="icl_form_errors" style="display:none"></p>
                            <?php if(isset($_GET['icl_ls_reset']) && $_GET['icl_ls_reset'] == 'default'): ?>
                                <p class="icl_form_success"><?php _e('Default settings have been loaded', 'sitepress')?></p>
                            <?php endif; ?>
                            <h4><?php _e('Language switcher widget', 'sitepress')?></h4>
                            <?php
                                global $wp_registered_sidebars;
                                $swidgets = wp_get_sidebars_widgets();
                                $sb = '';
                                foreach($swidgets as $k=>$v){
                                    if(is_array($v) && in_array('icl_lang_sel_widget', $v)){
                                        $sb = $k;
                                        $active_sidebar_check = $k;
                                    }
                                }
                            ?>
                            <?php if (!empty($sitepress_settings['setup_complete']) && !empty($active_sidebar_check) && !array_key_exists($active_sidebar_check, $wp_registered_sidebars)): ?>
                                <span class="icl_error_text"><strong><?php sprintf(__('Theme has changed and widget is not active. Please visit %swidgets page%s.', 'sitepress'), '<a href="widgets.php">', '</a>'); ?></strong></span>
                            <?php else: ?>
                                <?php _e('Choose where to display the language switcher widget:', 'sitepress') ?>
                                <select name="icl_language_switcher_sidebar">
                                    <?php foreach($wp_registered_sidebars as $rs): ?>
                                        <option value="<?php echo $rs['id']?>" <?php if($sb == $rs['id']) echo 'selected="selected"'?>><?php echo $rs['name']?>&nbsp;</option>
                                    <?php endforeach;?>
                                    <option value="" <?php if(!$sb && !empty($sitepress_settings['setup_complete'])) echo 'selected="selected"' ?> ><?php _e('--none--', 'sitepress'); ?></option>
                                </select>
                            <?php endif; ?>

                            <p class="icl_advanced_feature">
                                <?php printf(__('The drop-down language switcher can be added to your theme by inserting this PHP code: %s or as a widget','sitepress'),'<code class="php">&lt;?php do_action(\'icl_language_selector\'); ?&gt;</code>'); ?>.
                            </p>

                            <p class="icl_advanced_feature"><?php _e('You can also create custom language switchers, such as a list of languages or country flags.','sitepress'); ?>
                                <a href="http://wpml.org/?page_id=989"><?php _e('Custom language switcher creation guide','sitepress')?></a>.
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="icl_widget_title_show" value="1"<?php if ($sitepress_settings['icl_widget_title_show']) echo ' checked="checked"'; ?> />
                                    <?php _e('Display \'Languages\' as the widget title', 'sitepress'); ?>
                                </label>
                            </p>
                        </div> <!-- .wpml-section-content-inner -->

                        <div class="wpml-section-content-inner">
                            <h4><?php _e('Language switcher in the WP Menu', 'sitepress')?></h4>
                            <p>
                                <label>
                                    <input type="checkbox" id="icl_display_ls_in_menu" name="display_ls_in_menu" value="1"<?php if (!empty($sitepress_settings['display_ls_in_menu'])) echo ' checked="checked"'; ?> />
                                    <?php _e('Display the language switcher in the WP Menu', 'sitepress'); ?>
                                </label>
                                <span id="icl_ls_menus_list" <?php if (empty($sitepress_settings['display_ls_in_menu'])):?> style="display: none;"<?php endif;?>>
                                    <?php $nav_menus = wp_get_nav_menus( array('orderby' => 'name') ); ?>
                                    <?php ?>
                                    <select name="menu_for_ls">
                                        <?php if(empty($nav_menus)): ?>
                                        <option value="">--<?php _e('no menus defined', 'sitepress')?>--</option>
                                        <?php else: ?>
                                        <option value="">--<?php _e('select', 'sitepress')?>--</option>
                                        <?php endif; ?>
                                        <?php foreach($nav_menus as $nav_menu):?>
                                            <option value="<?php echo $nav_menu->term_id ?>"<?php if(isset($sitepress_settings['menu_for_ls']) && $nav_menu->term_id == $sitepress_settings['menu_for_ls']):?> selected="selected"<?php endif;?>><?php echo $nav_menu->name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </span>
                            </p>
                        </div>

                        <div class="wpml-section-content-inner">
                            <h4><?php _e('Languages order', 'sitepress')?></h4>
                            <?php $active_languages_ordered = $sitepress->order_languages($active_languages); ?>
                            <ul id="icl_languages_order" class="languages-order">
                                <?php foreach($active_languages_ordered as $language): ?>
                                    <li class="icl_languages_order_<?php echo $language['code']?>" ><?php echo $language['display_name']?></li>
                                <?php endforeach; ?>
                            </ul>
                            <span style="display:none;" class="icl_languages_order_ajx_resp"></span>
                            <input type="hidden" id="icl_languages_order_nonce" value="<?php echo wp_create_nonce('set_languages_order_nonce') ?>" />
                            <p class="explanation-text"><?php _e('Drag the languages to change their order', 'sitepress') ?></p>
                        </div>

                        <div class="wpml-section-content-inner">

                            <h4><?php _e('Language switcher style', 'sitepress')?></h4>

                            <ul>
                                <li>
                                    <label>
                                        <input type="radio" name="icl_lang_sel_type" value="dropdown" <?php if(!$sitepress_settings['icl_lang_sel_type'] || $sitepress_settings['icl_lang_sel_type'] == 'dropdown'):?>checked="checked"<?php endif?> />
                                        <?php echo __('Drop-down menu', 'sitepress') ?>
                                    </label>
                                    <select name="icl_lang_sel_stype" <?php if($sitepress_settings['icl_lang_sel_type'] != 'dropdown'): ?>style="display:none<?php endif;?>">
                                        <option <?php selected( $sitepress_settings['icl_lang_sel_stype'], 'classic' ); ?> value="classic"><?php _e('Classic', 'sitepress') ?></option>
                                        <option <?php selected( $sitepress_settings['icl_lang_sel_stype'], 'mobile-auto' ); ?> value="mobile-auto"><?php _e('Mobile friendly for mobile agents only', 'sitepress') ?></option>
                                        <option <?php selected( $sitepress_settings['icl_lang_sel_stype'], 'mobile' ); ?> value="mobile"><?php _e('Mobile friendly always', 'sitepress') ?></option>
                                    </select>
                                </li>
                                <li>
                                    <label>
                                        <input type="radio" name="icl_lang_sel_type" value="list" <?php if($sitepress_settings['icl_lang_sel_type'] == 'list'):?>checked="checked"<?php endif?> />
                                        <?php echo __('List of languages', 'sitepress') ?>
                                    </label>
                                    <select name="icl_lang_sel_orientation" <?php if($sitepress_settings['icl_lang_sel_type'] != 'list'): ?>style="display: none;"<?php endif;?>>
                                        <option value="vertical"><?php _e('Vertical', 'sitepress') ?></option>
                                        <option value="horizontal" <?php if($sitepress_settings['icl_lang_sel_orientation']=='horizontal'): ?>selected="selected"<?php endif;?>><?php _e('Horizontal', 'sitepress') ?></option>
                                    </select>
                                </li>
                            </ul>

                        </div>

                        <div class="wpml-section-content-inner">
                            <h4><?php _e('What to include in the language switcher', 'sitepress')?></h4>
                            <ul>
                                <li>
                                    <label>
                                        <input class="icl_ls_include" type="checkbox" name="icl_lso_flags" value="1" <?php if($sitepress_settings['icl_lso_flags']):?>checked<?php endif?> />
                                        <?php _e('Flag', 'sitepress') ?>
                                    </label>
                                </li>
                                <li>
                                    <p>
                                        <label>
                                            <input class="icl_ls_include" type="checkbox" name="icl_lso_native_lang" value="1" <?php if($sitepress_settings['icl_lso_native_lang']):?>checked<?php endif?> />
                                            <?php _e('Native language name', 'sitepress') ?>
                                            <span class="explanation-text"><?php _e("(the language name as it's written in that language)", 'sitepress') ?></span>
                                        </label>
                                    </p>
                                    <p>
                                        <label>
                                            <input class="icl_ls_include" type="checkbox" name="icl_lso_display_lang" value="1" <?php if($sitepress_settings['icl_lso_display_lang']):?>checked<?php endif?> />
                                            <?php _e('Language name in display language', 'sitepress') ?>
                                            <span class="explanation-text"><?php _e("(the language name as it's written in the currently displayed language)", 'sitepress') ?></span>
                                        </label>
                                    </p>
                                </li>
                            </ul>

                            <?php if ( !defined('ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS') || !ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS ) : ?>
                                <div id="icl_lang_sel_preview_wrap" class="language-selector-preview" style="min-height:<?php echo 50 + 25 * count($sitepress->get_active_languages())?>px">
                                    <div id="icl_lang_sel_preview">
                                        <p><strong><?php _e('Language switcher widget preview', 'sitepress')?></strong></p>
                                        <?php
                                            global $icl_language_switcher_preview;
                                            $icl_language_switcher_preview = true;
                                            $sitepress->language_selector();
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>

                        <div class="wpml-section-content-inner">
                            <h4><?php _e('How to handle languages without translation', 'sitepress')?></h4>
                            <p><?php _e('Some pages or posts may not be translated to all languages. Select how the language selector should behave in case translation is missing.', 'sitepress') ?></p>
                            <ul>
                                <li>
                                    <label>
                                        <input type="radio" name="icl_lso_link_empty" value="0" <?php if(!$sitepress_settings['icl_lso_link_empty']):?>checked="checked"<?php endif?> />
                                        <?php _e('Skip language', 'sitepress') ?>
                                    </label>
                                </li>
                                <li>
                                <label>
                                    <input type="radio" name="icl_lso_link_empty" value="1" <?php if($sitepress_settings['icl_lso_link_empty']==1):?>checked="checked"<?php endif?> />
                                    <?php _e('Link to home of language for missing translations', 'sitepress') ?>
                                </label>
                                </li>
                            </ul>
                        </div>

                        <div class="wpml-section-content-inner">
                            <h4><?php _e('Preserve url parameters', 'sitepress')?></h4>
                            <?php _e('These url parameters will be copied to the translated urls in the language switcher. Enter parameters separated by commas.', 'sitepress') ?><br />
                            <input type="text" size="100" name="copy_parameters" value="<?php echo $sitepress_settings['icl_lang_sel_copy_parameters']; ?>" />
                        </div>

                        <?php do_action('icl_language_switcher_options'); ?>

                        <?php if(!empty($sitepress_settings['setup_complete'])): ?>
                        <div class="wpml-section-content-inner">
                            <p class="buttons-wrap">
                                <span class="icl_ajx_response" id="icl_ajx_response3"></span>
                                <a class="button button-secondary" onclick="if(!confirm('<?php echo esc_js(__('Are you sure you want to reset to the default settings?', 'sitepress')) ?>')) return false;"
                                    href="<?php echo admin_url('admin.php?page='.$_GET['page'].'&amp;restore_ls_settings=1') ?>"><?php _e('Restore default', 'sitepress')?></a>
                                <button class="button-primary" name="save" type="submit"><?php _e('Save','sitepress') ?></button>
                            </p>
                        </div>
                        <?php endif; ?>

                        <?php if(empty($sitepress_settings['setup_complete'])): ?>
                            <div id="icl_setup_nav_3" style="text-align:right">
                                <input id="icl_setup_back_2" class="button-primary" name="save" value="<?php echo __('Back', 'sitepress') ?>" type="button" />
                                <?php wp_nonce_field('setup_got_to_step2_nonce', '_icl_nonce_gts2'); ?>
                                <input class="button-primary" name="save" value="<?php echo __('Finish', 'sitepress') ?>" type="submit" />
                            </div>
                            <script type="text/javascript">
                                addLoadEvent(function(){
                                    jQuery('#icl_save_language_switcher_options').submit(function(){
                                        iclSaveForm_success_cb.push(function(){
                                            location.href = location.href.replace(/#.*/,'')
                                        });
                                    });
                                });
                            </script>
                        <?php endif; ?>

                    </div> <!-- .wpml-section-content -->
                </form>

            </div> <!-- .wpml-section -->
        <?php endif; ?>

        <?php if(!empty($sitepress_settings['setup_complete']) && count($active_languages) > 1): ?>
            <div class="wpml-section wpml-section-admin-language" id="lang-sec-4">
                <div class="wpml-section-header">
                    <h3><?php _e('Admin language', 'sitepress') ?></h3>
                </div>
                <div class="wpml-section-content">
                    <form id="icl_admin_language_options" name="icl_admin_language_options" action="">
                        <?php wp_nonce_field('icl_admin_language_options_nonce', '_icl_nonce'); ?>
                        <?php if(is_admin()): ?>
                        <p>
                            <label>
                                <?php _e('Default admin language: ', 'sitepress'); ?>
                                <?php $default_language = $sitepress->get_language_details($sitepress->get_default_language()); ?>
                                <select name="icl_admin_default_language">
                                <option value="_default_"><?php printf(__('Default language (currently %s)', 'sitepress'),  $default_language['display_name']); ?></option>
                                <?php foreach($active_languages as $al):?>
                                <option value="<?php echo $al['code'] ?>"<?php if($sitepress_settings['admin_default_language']==$al['code']) echo ' selected="selected"'?>><?php echo $al['display_name']; if($sitepress->get_admin_language() != $al['code']) echo ' ('. $al['native_name'] .')' ?>&nbsp;</option>
                                <?php endforeach; ?>
                                </select>
                            </label>
                        </p>
                        <?php endif; ?>
                        <p><?php printf(__('Each user can choose the admin language. You can edit your language preferences by visiting your <a href="%s">profile page</a>.','sitepress'),'profile.php#wpml')?></p>
                        <p class="buttons-wrap">
                            <span class="icl_ajx_response" id="icl_ajx_response_al"></span>
                            <input class="button button-primary" name="save" value="<?php _e('Save','sitepress') ?>" type="submit" />
                        </p>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if(!empty($sitepress_settings['setup_complete']) && count($active_languages) > 1): ?>
            <div class="wpml-section wpml-section-blog-posts" id="lang-sec-6">
                <div class="wpml-section-header">
                    <h3><?php _e('Blog posts to display', 'sitepress') ?></h3>
                </div>
                <div class="wpml-section-content">
                    <form id="icl_blog_posts" name="icl_blog_posts" action="">
                        <?php wp_nonce_field('icl_blog_posts_nonce', '_icl_nonce'); ?>
                        <p>
                            <label>
                                <input type="radio" name="icl_untranslated_blog_posts" <?php if(empty($sitepress_settings['show_untranslated_blog_posts'])) echo 'checked="checked"' ?> value="0" /> <?php _e('Only translated posts.','sitepress'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="radio" name="icl_untranslated_blog_posts" <?php if(!empty($sitepress_settings['show_untranslated_blog_posts'])) echo 'checked="checked"' ?> value="1" /> <?php _e('All posts (display translation if it exists or posts in default language otherwise).','sitepress'); ?>
                            </label>
                        </p>
                        <p class="buttons-wrap">
                            <span class="icl_ajx_response" id="icl_ajx_response_bp"></span>
                            <input class="button button-primary" name="save" value="<?php _e('Save','sitepress') ?>" type="submit" />
                        </p>
                    </form>
                </div>
            </div>

            <div class="wpml-section wpml-section-hide-languages" id="lang-sec-7">
                <div class="wpml-section-header">
                    <h3><?php _e('Hide languages', 'sitepress') ?></h3>
                </div>
                <div class="wpml-section-content">
                    <p><?php _e("You can completely hide content in specific languages from visitors and search engines, but still view it yourself. This allows reviewing translations that are in progress.", 'sitepress') ?></p>
                    <form id="icl_hide_languages" name="icl_hide_languages" action="">
                        <?php wp_nonce_field('icl_hide_languages_nonce', '_icl_nonce') ?>
                        <?php foreach($active_languages as $l): ?>
                        <?php if($l['code'] == $default_language['code']) continue; ?>
                            <p>
                                <label>
                                    <input type="checkbox" name="icl_hidden_languages[]" <?php if(!empty($sitepress_settings['hidden_languages']) && in_array($l['code'],$sitepress_settings['hidden_languages'])) echo 'checked="checked"' ?> value="<?php echo $l['code']?>" /> <?php echo $l['display_name'] ?>
                                </label>
                            </p>
                        <?php endforeach; ?>
                        <p id="icl_hidden_languages_status">
                            <?php
                                if (!empty($sitepress_settings['hidden_languages'])){

                                     if (1 == count($sitepress_settings['hidden_languages'])){
                                         printf(__('%s is currently hidden to visitors.', 'sitepress'),
                                            $active_languages[$sitepress_settings['hidden_languages'][0]]['display_name']);
                                     }
                                     else {
                                         foreach($sitepress_settings['hidden_languages'] as $l){
                                             $_hlngs[] = $active_languages[$l]['display_name'];
                                         }
                                         $hlangs = join(', ', $_hlngs);
                                         printf(__('%s are currently hidden to visitors.', 'sitepress'), $hlangs);
                                     }
                                     echo '<p>';
                                        printf(__('You can enable its/their display for yourself, in your <a href="%s">profile page</a>.', 'sitepress'),'profile.php#wpml');
                                     echo '</p>';
                                }
                                else {
                                    _e('All languages are currently displayed.', 'sitepress');
                                }
                            ?>
                        </p>
                        <p class="buttons-wrap">
                            <span class="icl_ajx_response" id="icl_ajx_response_hl"></span>
                            <input class="button button-primary" name="save" value="<?php _e('Save','sitepress') ?>" type="submit" />
                        </p>
                    </form>
                </div>
            </div>

            <div class="wpml-section wpml-section-ml-themes" id="lang-sec-8">
                <div class="wpml-section-header">
                    <h3><?php _e('Make themes work multilingual', 'sitepress') ?></h3>
                </div>
                <div class="wpml-section-content">
                        <form id="icl_adjust_ids" name="icl_adjust_ids" action="">
                            <?php wp_nonce_field('icl_adjust_ids_nonce', '_icl_nonce'); ?>
                            <p><?php _e('This feature turns themes into multilingual, without having to edit their PHP files.', 'sitepress')?></p>
                            <p>
                                <label>
                                    <input type="checkbox" value="1" name="icl_adjust_ids" <?php if($sitepress_settings['auto_adjust_ids']) echo 'checked="checked"' ?> />
                                    <?php _e('Adjust IDs for multilingual functionality', 'sitepress')?>
                                </label>
                            </p>
                            <p class="explanation-text"><?php _e('Note: auto-adjust IDs will increase the number of database queries for your site.', 'sitepress')?></p>
                            <p class="buttons-wrap">
                                <span class="icl_ajx_response" id="icl_ajx_response_ai"></span>
                                <input class="button button-primary" name="save" value="<?php _e('Save','sitepress') ?>" type="submit" />
                            </p>
                        </form>
                </div>
            </div>

            <div class="wpml-section wpml-section-redirect" id="lang-sec-9">
                <div class="wpml-section-header">
                    <h3><?php _e('Browser language redirect', 'sitepress') ?></h3>
                </div>
                <div class="wpml-section-content">
                    <p><?php _e('WPML can automatically redirect visitors according to browser language.', 'sitepress')?></p>
                    <p class="explanation-text"><?php _e("This feature uses Javascript. Make sure that your site doesn't have JS errors.", 'sitepress'); ?></p>
                    <form id="icl_automatic_redirect" name="icl_automatic_redirect" action="">
                        <?php wp_nonce_field('icl_automatic_redirect_nonce', '_icl_nonce') ?>
                        <ul>
                            <li><label>
                                <input type="radio" value="0" name="icl_automatic_redirect" <?php if(empty($sitepress_settings['automatic_redirect'])) echo 'checked="checked"' ?> />
                                <?php _e('Disable browser language redirect', 'sitepress')?>
                            </label></li>
                            <li><label>
                                <input type="radio" value="1" name="icl_automatic_redirect" <?php if(@intval($sitepress_settings['automatic_redirect']) == 1) echo 'checked="checked"' ?> />
                                <?php _e('Redirect visitors based on browser language only if translations exist', 'sitepress')?>
                            </label></li>
                            <li><label>
                                <input type="radio" value="2" name="icl_automatic_redirect" <?php if(@intval($sitepress_settings['automatic_redirect']) == 2) echo 'checked="checked"' ?> />
                                <?php _e('Always redirect visitors based on browser language (redirect to home page if translations are missing)', 'sitepress')?>
                            </label></li>
                        </ul>
                        <ul>
                            <li>
                                <label><?php printf(__("Remember visitors' language preference for %s hours.", 'sitepress'),
                                    '<input size="2" type="text" value="'.@intval($sitepress_settings['remember_language']).'" name="icl_remember_language" /> ');
                                ?>
                                <?php if(!$sitepress->get_language_cookie()): ?>
                                <span class="icl_error_text"><?php _e("Your browser doesn't seem to be allowing cookies to be set.", 'sitepress'); ?></span>
                                <?php endif; ?>
                            </label></li>
                        </ul>
                        <p class="buttons-wrap">
                            <span class="icl_ajx_response" id="icl_ajx_response_ar"></span>
                            <input class="button button-primary" name="save" value="<?php _e('Save','sitepress') ?>" type="submit" />
                        </p>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>


    <?php if(!empty($sitepress_settings['setup_complete'])): ?>

        <?php do_action('icl_extra_options_' . $_GET['page']); ?>

        <div class="wpml-section wpml-section-seo-options" id="lang-sec-9-5">
            <div class="wpml-section-header">
                <h3><?php _e('SEO Options', 'sitepress') ?></h3>
            </div>
            <div class="wpml-section-content">
                <form id="icl_seo_options" name="icl_seo_options" action="">
                    <?php wp_nonce_field('icl_seo_options_nonce', '_icl_nonce'); ?>
                    <p>
                        <label><input type="checkbox" name="icl_seo_head_langs" <?php if($sitepress_settings['seo']['head_langs']) echo 'checked="checked"' ?> value="1" />
                        <?php _e("Display alternative languages in the HEAD section.", 'sitepress'); ?></label>
                    </p>
                    <p>
                        <label><input type="checkbox" name="icl_seo_canonicalization_duplicates" <?php if($sitepress_settings['seo']['canonicalization_duplicates']) echo 'checked="checked"' ?> value="1" />
                        <?php _e('Add links to the original content with rel="canonical" attributes.', 'sitepress'); ?></label>
                    </p>
                    <p class="buttons-wrap">
                        <span class="icl_ajx_response" id="icl_ajx_response_seo"></span>
                        <input class="button button-primary" name="save" value="<?php _e('Save','sitepress') ?>" type="submit" />
                    </p>
                </form>
            </div>
        </div>

        <div class="wpml-section wpml-section-wpml-love" id="lang-sec-10">
            <div class="wpml-section-header">
                <h3><?php _e('WPML love', 'sitepress') ?></h3>
            </div>
            <div class="wpml-section-content">
                <form id="icl_promote_form" name="icl_promote_form" action="">
                    <?php wp_nonce_field('icl_promote_form_nonce', '_icl_nonce'); ?>
                    <p>
                        <label><input type="checkbox" name="icl_promote" <?php if($sitepress_settings['promote_wpml']) echo 'checked="checked"' ?> value="1" />
                        <?php printf(__("Tell the world your site is running multilingual with WPML (places a message in your site's footer) - <a href=\"%s\">read more</a>", 'sitepress'),'http://wpml.org/?page_id=4560'); ?></label>
                    </p>
                    <p class="buttons-wrap">
                        <span class="icl_ajx_response" id="icl_ajx_response_lv"></span>
                        <input class="button button-primary" name="save" value="<?php _e('Save','sitepress') ?>" type="submit" />
                    </p>
                </form>
            </div>
        </div>

    <?php endif; ?>

    <?php do_action('icl_menu_footer'); ?>

</div> <!-- .wrap -->
