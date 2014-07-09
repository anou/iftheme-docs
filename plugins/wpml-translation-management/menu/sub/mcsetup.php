<?php
/**
 * included by menu translation-management.php
 *
 * @uses TranslationManagement
 */

$cf_keys_limit = 1000; // jic
$cf_keys = $wpdb->get_col( "
        SELECT meta_key
        FROM $wpdb->postmeta
        GROUP BY meta_key
        ORDER BY meta_key
        LIMIT $cf_keys_limit" );

$cf_keys_exceptions = array(
	'_edit_last',
	'_edit_lock',
	'_wp_page_template',
	'_wp_attachment_metadata',
	'_icl_translator_note',
	'_alp_processed',
	'_encloseme',
	'_pingme',
	'_wpml_media_duplicate',
	'_wpml_media_featured',
	'_wp_attached_file',
	'_thumbnail_id'
);
// '_wp_attached_file'

$cf_keys = array_diff($cf_keys, $cf_keys_exceptions);

/** @var $iclTranslationManagement TranslationManagement */
$cf_keys = array_unique(@array_merge($cf_keys, (array)$iclTranslationManagement->settings['custom_fields_readonly_config']));

    if ( $cf_keys )
        natcasesort($cf_keys);

    $cf_settings = isset($iclTranslationManagement->settings['custom_fields_translation']) ? $iclTranslationManagement->settings['custom_fields_translation'] : array();
    $cf_settings_ro = isset($iclTranslationManagement->settings['custom_fields_readonly_config']) ? (array)$iclTranslationManagement->settings['custom_fields_readonly_config'] : array();
    $doc_translation_method = isset($iclTranslationManagement->settings['doc_translation_method']) ? intval($iclTranslationManagement->settings['doc_translation_method']) : ICL_TM_TMETHOD_MANUAL;

    //show custom fields defined in types and not used yet
    if(function_exists('types_get_fields')){
        $types_cf = types_get_fields(array(), 'wpml' );
        foreach($types_cf as $key => $option){
            if ( !in_array($option['meta_key'], $cf_keys ) ){
                $cf_keys[] = $option['meta_key'];
                $cf_settings[$option['meta_key']] = $option['wpml_action'];
            }
        }
    }

?>

<ul class="wpml-navigation-links js-wpml-navigation-links">
    <li><a href="#ml-content-setup-sec-1"><?php _e('How to translate posts and pages','sitepress'); ?></a></li>
    <li><a href="#ml-content-setup-sec-2"><?php _e('Posts and pages synchronization', 'sitepress'); ?></a></li>
    <li><a href="#ml-content-setup-sec-3"><?php _e('Translated documents options', 'wpml-translation-management'); ?></a></li>
    <?php if (defined('WPML_ST_VERSION')): ?>
         <li><a href="#ml-content-setup-sec-4"><?php _e('Custom posts slug translation options', 'wpml-string-translation'); ?></a></li>
    <?php endif; ?>
    <li><a href="#ml-content-setup-sec-5"><?php _e('Translation pickup mode', 'wpml-translation-management'); ?></a></li>
	<li><a href="#ml-content-setup-sec-6"><?php _e('Custom fields translation', 'wpml-translation-management'); ?></a></li>
	<?php


		$custom_posts = array();
		$icl_post_types = $sitepress->get_translatable_documents( true );

		foreach ( $icl_post_types as $k => $v ) {
			if ( !in_array( $k, array( 'post', 'page' ) ) ) {
				$custom_posts[ $k ] = $v;
			}
		}

	global $wp_taxonomies;
	$custom_taxonomies = array_diff( array_keys( (array)$wp_taxonomies ), array( 'post_tag', 'category', 'nav_menu', 'link_category', 'post_format' ) );
	?>
	<?php if($custom_posts): ?>
		<li><a href="#ml-content-setup-sec-7"><?php _e('Custom posts', 'sitepress'); ?></a></li> <?php // TODO: This menu item should be displayed conditionally if custom post types are defined ?>
	<?php endif; ?>
	<?php if($custom_taxonomies): ?>
    <li><a href="#ml-content-setup-sec-8"><?php _e('Custom taxonomies', 'sitepress'); ?></a></li> <?php // TODO: This menu item should be displayed conditionally if custom taxonomies are defined ?>
	<?php endif; ?>
    <?php if( !empty($iclTranslationManagement->admin_texts_to_translate) && function_exists('icl_register_string')):  ?>
        <li><a href="#ml-content-setup-sec-9"><?php _e('Admin Strings to Translate', 'wpml-translation-management'); ?></a></li>
    <?php endif; ?>
</ul>

<div class="wpml-section wpml-section-notice">
    <div class="updated below-h2">
        <p>
            <?php _e("WPML can read a configuration file that tells it what needs translation in themes and plugins. The file is named wpml-config.xml and it's placed in the root folder of the plugin or theme.", 'wpml-translation-management'); ?>
        </p>
        <p>
            <a href="http://wpml.org/?page_id=5526"><?php _e('Learn more', 'wpml-translation-management') ?></a>
        </p>
    </div>
</div>

<div class="wpml-section" id="ml-content-setup-sec-1">

    <div class="wpml-section-header">
        <h3><?php _e('How to translate posts and pages', 'wpml-translation-management');?></h3>
    </div>

    <div class="wpml-section-content">

        <form id="icl_doc_translation_method" name="icl_doc_translation_method" action="">
            <?php wp_nonce_field('icl_doc_translation_method_nonce', '_icl_nonce') ?>

            <ul>
                <li>
                    <label>
                        <input type="radio" name="t_method" value="<?php echo ICL_TM_TMETHOD_MANUAL ?>" <?php if($doc_translation_method==ICL_TM_TMETHOD_MANUAL): ?>checked="checked"<?php endif; ?> />
                        <?php _e('Create translations manually', 'wpml-translation-management')?>
                    </label>
                </li>
                <li>
                    <label>
                        <input type="radio" name="t_method" value="<?php echo ICL_TM_TMETHOD_EDITOR ?>" <?php if($doc_translation_method==ICL_TM_TMETHOD_EDITOR): ?>checked="checked"<?php endif; ?> />
                        <?php _e('Use the translation editor', 'wpml-translation-management')?>
                    </label>
                </li>
                <li>
                    <label>
                        <input type="radio" name="t_method" value="<?php echo ICL_TM_TMETHOD_PRO ?>" <?php if($doc_translation_method==ICL_TM_TMETHOD_PRO): ?>checked="checked"<?php endif; ?> />
                        <?php _e('Send to professional translation', 'wpml-translation-management')?>
                    </label>
                </li>
            </ul>

            <p>
                <label>
                    <input name="how_to_translate" value="1" <?php checked(empty($sitepress_settings['hide_how_to_translate']), true) ?> type="checkbox" />
                    <?php _e('Show translation instructions in the list of pages', 'wpml-translation-management') ?>
                </label>
            </p>

            <p>
                <a href="http://wpml.org/?page_id=3416" target="_blank"><?php _e('Learn more about the different translation options') ?></a>
            </p>

            <p class="buttons-wrap">
                <span class="icl_ajx_response" id="icl_ajx_response_dtm"></span>
                <input type="submit" class="button-primary" value="<?php _e('Save', 'wpml-translation-management')?>" />
            </p>

        </form>
    </div> <!-- .wpml-section-content -->

</div> <!-- .wpml-section -->

<?php include ICL_PLUGIN_PATH . '/menu/_posts_sync_options.php'; ?>

<div class="wpml-section" id="ml-content-setup-sec-3">

    <div class="wpml-section-header">
        <h3><?php _e('Translated documents options', 'wpml-translation-management') ?></h3>
    </div>

    <div class="wpml-section-content">

        <form name="icl_tdo_options" id="icl_tdo_options" action="">
            <?php wp_nonce_field('icl_tdo_options_nonce', '_icl_nonce'); ?>

            <div class="wpml-section-content-inner">
                <h4>
                    <?php _e('Document status', 'wpml-translation-management')?>
                </h4>
                <ul>
                    <li>
                        <label>
                            <input type="radio" name="icl_translated_document_status" value="0" <?php if(!$sitepress_settings['translated_document_status']): ?>checked="checked"<?php endif;?> />
                            <?php _e('Draft', 'wpml-translation-management') ?>
                        </label>
                    </li>
                    <li>
                        <label>
                            <input type="radio" name="icl_translated_document_status" value="1" <?php if($sitepress_settings['translated_document_status']): ?>checked="checked"<?php endif;?> />
                            <?php _e('Same as the original document', 'wpml-translation-management') ?>
                        </label>
                    </li>
                </ul>
                <p class="explanation-text">
                    <?php _e("Choose if translations should be published when received. Note: If Publish is selected, the translation will only be published if the original node is published when the translation is received.", 'wpml-translation-management') ?>
                </p>
            </div>

            <div class="wpml-section-content-inner">
                <h4>
                    <?php _e('Page URL', 'wpml-translation-management')?>
                </h4>
                <ul>
                    <li>
                        <label><input type="radio" name="icl_translated_document_page_url" value="auto-generate"
                            <?php if(empty($sitepress_settings['translated_document_page_url']) ||
                                $sitepress_settings['translated_document_page_url'] == 'auto-generate'): ?>checked="checked"<?php endif;?> />
                            <?php _e('Auto-generate from title (default)', 'wpml-translation-management') ?>
                        </label>
                    </li>
                    <li>
                        <label><input type="radio" name="icl_translated_document_page_url" value="translate"
                            <?php if($sitepress_settings['translated_document_page_url'] == 'translate'): ?>checked="checked"<?php endif;?> />
                            <?php _e('Translate (this will include the slug in the translation and not create it automatically from the title)', 'wpml-translation-management') ?>
                        </label>
                    </li>
                    <li>
                        <label><input type="radio" name="icl_translated_document_page_url" value="copy-encoded"
                            <?php if($sitepress_settings['translated_document_page_url'] == 'copy-encoded'): ?>checked="checked"<?php endif;?> />
                            <?php _e('Copy from original language if translation language uses encoded URLs', 'wpml-translation-management') ?>
                        </label>
                    </li>
                </ul>
            </div>

            <div class="wpml-section-content-inner">
                <p class="buttons-wrap">
                    <span class="icl_ajx_response" id="icl_ajx_response_tdo"></span>
                    <input type="submit" class="button-primary" value="<?php _e('Save', 'wpml-translation-management')?>" />
                </p>
            </div>

        </form>
    </div> <!-- .wpml-section-content -->

</div> <!-- .wpml-section -->

<?php if(defined('WPML_ST_VERSION')) include WPML_ST_PATH . '/menu/_slug-translation-options.php'; ?>

<div class="wpml-section" id="ml-content-setup-sec-5">

    <div class="wpml-section-header">
        <h3><?php _e('Translation pickup mode', 'wpml-translation-management');?></h3>
    </div>

    <div class="wpml-section-content">

        <form id="icl_translation_pickup_mode" name="icl_translation_pickup_mode" action="">
            <?php wp_nonce_field('set_pickup_mode_nonce', '_icl_nonce') ?>

            <p>
                <?php _e('How should the site receive completed translations from ICanLocalize?', 'wpml-translation-management'); ?>
            </p>
            <p>
                <label>
                    <input type="radio" name="icl_translation_pickup_method" value="<?php echo ICL_PRO_TRANSLATION_PICKUP_XMLRPC ?>"<?php if ( $sitepress_settings['translation_pickup_method'] == ICL_PRO_TRANSLATION_PICKUP_XMLRPC ): ?>checked<?php endif ?>/>
                    <?php _e('ICanLocalize will deliver translations automatically using XML-RPC', 'wpml-translation-management'); ?>
                </label>
            </p>

            <?php if( $sitepress_settings['translation_pickup_method'] == ICL_PRO_TRANSLATION_PICKUP_XMLRPC ): ?>
                <p>
                    <label>
                        <input type="checkbox" name="icl_disable_reminders" value="1" <?php if(!empty($sitepress_settings['icl_disable_reminders'])): ?>checked<?php endif;?> />
                        <?php _e('Hide reminders', 'wpml-translation-management'); ?>
                    </label>
                </p>
            <?php endif; ?>

            <p>
                <label>
                <input type="radio" name="icl_translation_pickup_method" value="<?php echo ICL_PRO_TRANSLATION_PICKUP_POLLING ?>"<?php if ( $sitepress_settings['translation_pickup_method'] == ICL_PRO_TRANSLATION_PICKUP_POLLING ): ?>checked<?php endif; ?> />
                    <?php _e('The site will fetch translations manually', 'wpml-translation-management'); ?>
                </label>
            </p>

            <p>
                <label>
                    <input name="icl_notify_complete" type="checkbox" value="1" <?php if ( !empty($sitepress_settings['icl_notify_complete']) ): ?>checked<?php endif;?> />
                    <?php _e('Send an email notification when translations complete', 'sitepress'); ?>
                </label>
            </p>
            <p class="buttons-wrap">
                <span class="icl_ajx_response" id="icl_ajx_response_tpm"></span>
                <input class="button-primary" name="save" value="<?php _e('Save','wpml-translation-management') ?>" type="submit" />
            </p>

            <?php $ICL_Pro_Translation->get_icl_manually_tranlations_box(''); // shows only when translation polling is on and there are translations in progress ?>
        </form>

    </div> <!-- .wpml-section-content -->

</div> <!-- .wpml-section -->

<div class="wpml-section wpml-section-cf-translation" id="ml-content-setup-sec-6">

    <div class="wpml-section-header">
        <h3><?php _e('Custom fields translation', 'wpml-translation-management');?></h3>
    </div>

    <div class="wpml-section-content">

        <form id="icl_cf_translation" name="icl_cf_translation" action="">

            <?php wp_nonce_field('icl_cf_translation_nonce', '_icl_nonce'); ?>
            <?php if(empty($cf_keys)): ?>
                <p>
                    <?php _e('No custom fields found. It is possible that they will only show up here after you add more posts after installing a new plugin.', 'wpml-translation-management'); ?>
                </p>
            <?php else: ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>
                                <?php _e('Custom fields', 'wpml-translation-management');?>
                            </th>
                            <th>
                                <?php _e("Don't translate", 'wpml-translation-management')?>
                            </th>
                            <th>
                                <?php _e("Copy from original to translation", 'wpml-translation-management')?>
                            </th>
                            <th>
                                <?php _e("Translate", 'wpml-translation-management')?>
                            </th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>
                                <?php _e('Custom fields', 'wpml-translation-management');?>
                            </th>
                            <th>
                                <?php _e("Don't translate", 'wpml-translation-management')?>
                            </th>
                            <th>
                                <?php _e("Copy from original to translation", 'wpml-translation-management')?>
                            </th>
                            <th>
                                <?php _e("Translate", 'wpml-translation-management')?>
                            </th>
                        </tr>
                    </tfoot>
                    <tbody>
                        <?php foreach($cf_keys as $cf_key): ?>
                            <?php
                                $rdisabled = in_array($cf_key, $cf_settings_ro) ? 'disabled' : '';
                                if($rdisabled && $cf_settings[$cf_key]==0) continue;

                                if (!empty($cf_settings[$cf_key]) && $cf_settings[$cf_key] == 3) {
                                    continue;
                                }
                            ?>
                            <tr>
                                <td><?php echo $cf_key ?></td>
                                <td title="<?php _e("Don't translate", 'wpml-translation-management')?>">
                                    <input type="radio" name="cf[<?php echo base64_encode($cf_key) ?>]" value="0" <?php echo $rdisabled ?> <?php if(isset($cf_settings[$cf_key]) && $cf_settings[$cf_key]==0):?>checked<?php endif;?> />
                                </td>
                                <td title="<?php _e("Copy from original to translation", 'wpml-translation-management')?>">
                                    <input type="radio" name="cf[<?php echo base64_encode($cf_key) ?>]" value="1" <?php echo $rdisabled ?> <?php if(isset($cf_settings[$cf_key]) && $cf_settings[$cf_key]==1):?>checked<?php endif;?> />
                                </td>
                                <td title="<?php _e("Translate", 'wpml-translation-management')?>">
                                    <input type="radio" name="cf[<?php echo base64_encode($cf_key) ?>]" value="2" <?php echo $rdisabled ?> <?php if(isset($cf_settings[$cf_key]) && $cf_settings[$cf_key]==2):?>checked<?php endif;?> />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="buttons-wrap">
                    <span class="icl_ajx_response" id="icl_ajx_response_cf"></span>
                    <input type="submit" class="button-primary" value="<?php _e('Save', 'wpml-translation-management') ?>" />
                </p>
            <?php endif; ?>

        </form>

    </div> <!-- .wpml-section-content -->

</div> <!-- .wpml-section -->

<?php include ICL_PLUGIN_PATH . '/menu/_custom_types_translation.php'; ?>

<?php if(!empty($iclTranslationManagement->admin_texts_to_translate) && function_exists('icl_register_string')): //available only with the String Translation plugin ?>
<div class="wpml-section" id="ml-content-setup-sec-9">

        <div class="wpml-section-header">
            <h3><?php _e('Admin Strings to Translate', 'wpml-translation-management');?></h3>
        </div>

        <div class="wpml-section-content">

            <table class="widefat">
                <thead>
                    <tr>
                        <th colspan="3">
                            <?php _e('Admin Strings', 'wpml-translation-management');?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php foreach($iclTranslationManagement->admin_texts_to_translate as $option_name=>$option_value): ?>
                            <?php $iclTranslationManagement->render_option_writes($option_name, $option_value); ?>
                            <?php endforeach ?>
                            <br />
                            <p><a class="button-secondary" href="<?php echo admin_url('admin.php?page='.WPML_ST_FOLDER.'/menu/string-translation.php') ?>"><?php _e('Edit translatable strings', 'wpml-translation-management') ?></a></p>
                        </td>
                    </tr>
                </tbody>
            </table>

        </div> <!-- .wpml-section-content -->

    </div> <!-- .wpml-section -->
    <?php endif; ?>