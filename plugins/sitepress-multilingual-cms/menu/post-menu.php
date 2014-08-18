<?php
/** @var $this SitePress */
/** @var $post WP_post */
global $wpdb, $wp_post_types, $iclTranslationManagement;

$this->noscript_notice();

$active_languages = $this->get_active_languages();
$default_language = $this->get_default_language();
$current_language = $this->get_current_language();
if ( $post->ID && $post->post_status != 'auto-draft' ) {
	$res  = $this->get_element_language_details( $post->ID, 'post_' . $post->post_type );
	$trid = @intval( $res->trid );
	if ( $trid ) {
		$element_lang_code = $res->language_code;
	} else {
		$translation_id    = $this->set_element_language_details( $post->ID, 'post_' . $post->post_type, null, $current_language );
		$trid_sql          = "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE translation_id = %d";
		$trid_prepared     = $wpdb->prepare( $trid_sql, array( $translation_id ) );
		$trid              = $wpdb->get_var( $trid_prepared );
		$element_lang_code = $current_language;
	}
} else {
	$trid              = isset( $_GET[ 'trid' ] ) ? intval( $_GET[ 'trid' ] ) : false;
	$element_lang_code = isset( $_GET[ 'lang' ] ) ? strip_tags( $_GET[ 'lang' ] ) : $current_language;
}

$translations = array();
if ( $trid ) {
	$translations = $this->get_element_translations( $trid, 'post_' . $post->post_type );
}

$selected_language = $element_lang_code ? $element_lang_code : $current_language;

if ( isset( $_GET[ 'lang' ] ) ) {
	$_selected_language = strip_tags( $_GET[ 'lang' ] );
} else {
	$_selected_language = $selected_language;
}

/**
 * @var $untranslated array
 */
$untranslated = array();
if ( $_selected_language != $default_language ) {
	$untranslated = $this->get_posts_without_translations( $_selected_language, $default_language, 'post_' . $post->post_type );
}

/**
 * @var $source_language bool|string
 */
$source_language = isset( $_GET[ 'source_lang' ] ) ? $_GET[ 'source_lang' ] : false;

$is_original = false;
if ( !$source_language ) {
	if ( isset( $translations[ $selected_language ] ) ) {
		$selected_content_translation      = $translations[ $selected_language ];
		$is_original = $selected_content_translation->original;
		if(!$is_original) {
			$selected_content_language_details = $this->get_element_language_details( $selected_content_translation->element_id, 'post_' . $post->post_type );
			if ( isset( $selected_content_language_details ) && isset( $selected_content_language_details->source_language_code ) ) {
				$source_language = $selected_content_language_details->source_language_code;
			}
		}
	}
}
//globalize some variables to make them available through hooks
global $icl_meta_box_globals;
$icl_meta_box_globals = array(
	'active_languages'  => $active_languages,
	'translations'      => $translations,
	'selected_language' => $selected_language
);

$icl_lang_duplicate_of = get_post_meta($post->ID, '_icl_lang_duplicate_of', true);

$post_type_label = strtolower( $wp_post_types[ $post->post_type ]->labels->singular_name != "" ? $wp_post_types[ $post->post_type ]->labels->singular_name : $wp_post_types[ $post->post_type ]->labels->name );

if($icl_lang_duplicate_of): ?>
<div class="icl_cyan_box"><?php
    printf(__('This document is a duplicate of %s and it is maintained by WPML.', 'sitepress'),
        '<a href="'.get_edit_post_link($icl_lang_duplicate_of).'">' .
		get_the_title($icl_lang_duplicate_of) . '</a>');
?>
    <p><input id="icl_translate_independent" class="button-secondary" type="button" value="<?php _e('Translate independently', 'sitepress') ?>" /></p>
    <?php wp_nonce_field('reset_duplication_nonce', '_icl_nonce_rd') ?>
    <i><?php printf(__('WPML will no longer synchronize this %s with the original content.', 'sitepress'), $post->post_type); ?></i>
</div>

<span style="display:none"> <?php /* Hide everything else; */ ?>
<?php endif; ?>

<div id="icl_document_language_dropdown" class="icl_box_paragraph">
    <p>
    	<strong><?php printf(__('Language of this %s', 'sitepress'), $post_type_label ); ?></strong>
    </p>

    <select name="icl_post_language" id="icl_post_language">
    <?php foreach($active_languages as $lang):?>
    <?php if(isset($translations[$lang['code']]->element_id) && $translations[$lang['code']]->element_id != $post->ID) continue ?>
    <option value="<?php echo $lang['code'] ?>" <?php if($selected_language==$lang['code']): ?>selected="selected"<?php endif;?>><?php echo $lang['display_name'] ?>&nbsp;</option>
    <?php endforeach; ?>
    </select>
    <input type="hidden" name="icl_trid" value="<?php echo $trid ?>" />
</div>
<?php
if (isset($translations) && count($translations) == 1 && count(SitePress::get_orphan_translations($trid, $post->post_type, $this->get_current_language())) > 0){

	$language_name = $this->get_display_language_name( $selected_language, $this->get_default_language() );
	?>
	<div id="icl_document_connect_translations_dropdown" class="icl_box_paragraph">
		<p>
			<a class="js-set-post-as-source" href="#">
				<?php _e( 'Connect with translations', 'sitepress' ); ?>
			</a>
		</p>
		<input type="hidden" id="icl_connect_translations_post_id" name="icl_connect_translations_post_id" value="<?php echo $post->ID; ?>"/>
		<input type="hidden" id="icl_connect_translations_trid" name="icl_connect_translations_trid" value="<?php echo $trid; ?>"/>
		<input type="hidden" id="icl_connect_translations_post_type" name="icl_connect_translations_post_type" value="<?php echo $post->post_type; ?>"/>
		<input type="hidden" id="icl_connect_translations_language" name="icl_connect_translations_language" value="<?php echo $this->get_current_language(); ?>"/>
		<?php wp_nonce_field( 'get_orphan_posts_nonce', '_icl_nonce_get_orphan_posts' ); ?>
	</div>

	<div class="hidden">
		<div id="connect_translations_dialog" 	title="<?php _e( 'Choose a post to assign','sitepress' ); ?>"
			 								data-set_as_source-text="<?php echo esc_attr( sprintf(__("Make %s the original language for this %s",'sitepress'), $language_name, $post->post_type));?>"
			 								data-alert-text="<?php echo esc_attr(__("Please make sure to save your post, if you've made any change, before proceeding with this action!",'sitepress'));?>"
											data-cancel-label="<?php echo esc_attr(__( 'Cancel','sitepress' )); ?>"
 											data-ok-label="<?php echo esc_attr(__( 'Ok','sitepress' )); ?>"
			>
			<div class="wpml-dialog-content">
				<p class="js-ajax-loader ajax-loader">
					<?php _e('Loading') ?>&hellip; <span class="spinner"></span>
				</p>
				<div class="posts-found js-posts-found">
					<p id="post-label">
						<?php _e( 'Type a post title', 'sitepress' ); ?>:
					</p>
					<input id="post_search" type="text">
				</div>
				<p class="js-no-posts-found no-posts-found"><?php _e('No posts found','sitepress') ?></p>
				<input type="hidden" id="assign_to_trid">
			</div>
		</div>
		<div id="connect_translations_dialog_confirm" 	title="<?php echo esc_attr(__( 'Connect this post?','sitepress' )); ?>"
			 										data-cancel-label="<?php echo esc_attr(__( 'Cancel','sitepress' )); ?>"
			 										data-assign-label="<?php echo esc_attr(__( 'Assign','sitepress' )); ?>"
			>
			<div class="wpml-dialog-content">
				<p>
					<span class="ui-icon ui-icon-alert"></span>
					<?php _e( 'You are about to connect the current post with these following posts','sitepress' ); ?>:
				</p>
				<div id="connect_translations_dialog_confirm_list">
					<p class="js-ajax-loader ajax-loader">
						<?php _e('Loading') ?>&hellip; <span class="spinner"></span>
					</p>
				</div>
				<?php wp_nonce_field( 'get_posts_from_trid_nonce', '_icl_nonce_get_posts_from_trid' ); ?>
				<?php wp_nonce_field( 'connect_translations_nonce', '_icl_nonce_connect_translations' ); ?>
			</div>
		</div>
	</div>

<?php
}
	?>
	<div id="translation_of_wrap">
		<?php
		if (!$is_original && ( $selected_language != $source_language || ( isset( $_GET[ 'lang' ] ) && $_GET[ 'lang' ] != $source_language ) ) && 'all' != $this->get_current_language() ) {
			$disabled = ( ( empty( $_GET[ 'action' ] ) || $_GET[ 'action' ] != 'edit' ) && $trid ) ? ' disabled="disabled"' : false;
			?>

			<div id="icl_translation_of_panel" class="icl_box_paragraph">
				<?php echo __( 'This is a translation of', 'sitepress' ); ?>&nbsp;
				<select name="icl_translation_of" id="icl_translation_of"<?php echo $disabled; ?>>
					<?php
					if (!$is_original || !$source_language || $source_language == $selected_language ) {
						if ( $trid ) {
							if(!$source_language) {
								$source_language = $default_language;
							}
							?>
							<option value="none"><?php echo __( '--None--', 'sitepress' ); ?></option>
							<?php
							//get source
							$source_element_id = $wpdb->get_var( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid={$trid} AND language_code='{$source_language}'" );
							if ( !$source_element_id ) {
								// select the first id found for this trid
								$source_element_id = $wpdb->get_var( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid={$trid}" );
							}
							if ( $source_element_id && $source_element_id != $post->ID ) {
								$src_language_title = $wpdb->get_var( "SELECT post_title FROM {$wpdb->prefix}posts WHERE ID = {$source_element_id}" );
							}
							if ( isset( $src_language_title ) && !isset( $_GET[ 'icl_ajx' ] ) ) {
								?>
								<option value="<?php echo $source_element_id ?>" selected="selected"><?php echo $src_language_title; ?>&nbsp;</option>
							<?php
							}
						} else {
							?>
							<option value="none" selected="selected"><?php echo __( '--None--', 'sitepress' ); ?></option>
						<?php
						}
						foreach ( $untranslated as $translation_of_id => $translation_of_title ) {
							?>
							<option value="<?php echo $translation_of_id ?>"><?php echo $translation_of_title; ?>&nbsp;</option>
						<?php
						}
					} else {
						if ( $trid ) {

							// add the source language
							$source_element_id = $wpdb->get_var( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid={$trid} AND language_code='{$source_language}'" );
							if ( $source_element_id ) {
								$src_language_title = $wpdb->get_var( "SELECT post_title FROM {$wpdb->prefix}posts WHERE ID = {$source_element_id}" );
							}
							if ( isset( $src_language_title ) ) {
								?>
								<option value="<?php echo $source_element_id; ?>" selected="selected"><?php echo $src_language_title; ?></option>
							<?php
							}
						} else {
							?>
							<option value="none" selected="selected"><?php echo __( '--None--', 'sitepress' ); ?></option>
						<?php
						}
					}
					?>
				</select>
				<?php //Add hidden value when the dropdown is hidden ?>
				<?php
				if ( $disabled ) {
					?>
					<input type="hidden" name="icl_translation_of" id="icl_translation_of_hidden" value="<?php echo $source_element_id; ?>">
				<?php
				}
				?>
			</div>
		<?php
		}
		?>
	</div><!--//translation_of_wrap--><?php // don't delete this html comment ?>

<br clear="all" />

<?php if(isset($_GET['action']) && $_GET['action'] == 'edit' && $trid): ?>

    <?php do_action('icl_post_languages_options_before', $post->ID);?>

    <div id="icl_translate_options">
    <?php
        // count number of translated and un-translated pages.
        $translations_found = 0;
        $untranslated_found = 0;
        foreach($active_languages as $lang) {
            if($selected_language==$lang['code']) continue;
            if(isset($translations[$lang['code']]->element_id)) {
                $translations_found += 1;
            } else {
                $untranslated_found += 1;
            }
        }
    ?>

    <?php if($untranslated_found > 0 && (empty($iclTranslationManagement->settings['doc_translation_method']) || $iclTranslationManagement->settings['doc_translation_method'] != ICL_TM_TMETHOD_PRO)): ?>
        <?php if($this->get_icl_translation_enabled()):?>
            <p style="clear:both;"><b><?php _e('or, translate manually:', 'sitepress'); ?> </b></p>
        <?php else: ?>
            <p style="clear:both;"><b><?php _e('Translate yourself', 'sitepress'); ?></b></p>
        <?php endif; ?>
        <table width="100%" class="icl_translations_table">
        <tr>
            <th>&nbsp;</th>
            <th align="right"><?php _e('Translate', 'sitepress') ?></th>
            <th align="right" width="10" style="padding-left:8px;"><?php _e('Duplicate', 'sitepress') ?></th>
        </tr>
        <?php $oddev = 1; ?>
        <?php foreach($active_languages as $lang): if($selected_language==$lang['code']) continue; ?>
        <tr <?php if($oddev < 0): ?>class="icl_odd_row"<?php endif; ?>>
            <?php if(!isset($translations[$lang['code']]->element_id)):?>
                <?php $oddev = $oddev*-1; ?>
                <td style="padding-left: 4px;">
                    <?php echo $lang['display_name'] ?>
                </td>
                <?php
                    $add_anchor =  __('add translation','sitepress');
                    $img = 'add_translation.png';
                    if(!empty($iclTranslationManagement->settings['doc_translation_method']) && $iclTranslationManagement->settings['doc_translation_method'] == ICL_TM_TMETHOD_EDITOR){
                            $job_id = $iclTranslationManagement->get_translation_job_id($trid, $lang['code']);

                            $args = array('lang_from'=>$selected_language, 'lang_to'=>$lang['code'], 'job_id'=>@intval($job_id));
                            $current_user_is_translator = $iclTranslationManagement->is_translator(get_current_user_id(), $args);

                            if($job_id){
                                $job_details = $iclTranslationManagement->get_translation_job($job_id);

                                if($current_user_is_translator){
                                    if($job_details->status == ICL_TM_IN_PROGRESS){
                                        $add_anchor =  __('in progress','sitepress');
                                        $img = 'in-progress.png';
                                    }
                                }else{
									$tres_prepared = $wpdb->prepare( "
                                        SELECT s.* FROM {$wpdb->prefix}icl_translation_status s
                                            JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = s.rid
                                            WHERE job_id=%d", $job_id );
									$tres = $wpdb->get_row( $tres_prepared );
                                    if($tres->status == ICL_TM_IN_PROGRESS){
                                        $img = 'edit_translation_disabled.png';
                                        $add_anchor =  sprintf(__('In progress (by a different translator). <a%s>Learn more</a>.','sitepress'), ' href="https://wpml.org/?page_id=52218"');
                                    }elseif($tres->status == ICL_TM_NOT_TRANSLATED || $tres->status == ICL_TM_WAITING_FOR_TRANSLATOR){
                                        $img = 'add_translation_disabled.png';
                                        $add_anchor = sprintf(__('You are not the translator of this document. <a%s>Learn more</a>.','sitepress'), ' href="https://wpml.org/?page_id=52218"');
                                    }elseif($tres->status == ICL_TM_NEEDS_UPDATE || $tres->status == ICL_TM_COMPLETE){
                                        $img = 'edit_translation_disabled.png';
                                        $add_anchor = sprintf(__('You are not the translator of this document. <a%s>Learn more</a>.','sitepress'), ' href="https://wpml.org/?page_id=52218"');
                                    }

                                }
                                if($current_user_is_translator){
                                    $add_link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&job_id='.$job_id);
                                }else{
                                    $add_link = '#';
                                    $add_anchor =  sprintf(__('In progress (by a different translator). <a%s>Learn more</a>.','sitepress'), ' href="https://wpml.org/?page_id=52218"');
                                }

                            }else{
                                if($current_user_is_translator){
                                    $add_link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&icl_tm_action=create_job&iclpost[]='.
                                    $post->ID.'&translate_to['.$lang['code'].']=1&iclnonce=' . wp_create_nonce('pro-translation-icl'));
                                    if($this->get_current_language() != $this->get_default_language()){
                                        $add_link .= '&translate_from=' . $this->get_current_language();
                                    }
                                }else{
                                    $add_link = '#';
                                    $img = 'add_translation_disabled.png';
                                    $add_anchor = sprintf(__('You are not the translator of this document. <a%s>Learn more</a>.','sitepress'), ' href="https://wpml.org/?page_id=52218"');
                                }
                            }
                    }else{
                        $add_link = admin_url("post-new.php?post_type={$post->post_type}&trid=" .
                            $trid . "&lang=" . $lang['code'] . "&source_lang=" . $selected_language);
                    }
                ?>
                <?php  $add_link = apply_filters('wpml_post_edit_page_link_to_translation',$add_link); ?>
                <td align="right">
                <?php if($add_link == '#'):
                    icl_pop_info($add_anchor, ICL_PLUGIN_URL . '/res/img/' .$img, array('icon_size' => 16, 'but_style'=>array('icl_pop_info_but_noabs')));
                 else: ?>
                <a href="<?php echo $add_link?>" title="<?php echo esc_attr($add_anchor) ?>"><img  border="0" src="<?php
                    echo ICL_PLUGIN_URL . '/res/img/' . $img ?>" alt="<?php echo esc_attr($add_anchor) ?>" width="16" height="16"  /></a>
                <?php endif; ?>

                </td>
                <td align="right">
                    <?php
                        // do not allow creating duplicates for posts that are being translated
                        $ddisabled = '';
                        $dtitle = esc_attr__('create duplicate', 'sitepress');
                        if(defined('WPML_TM_VERSION')){
                            $translation_id = $wpdb->get_var($wpdb->prepare("
                                SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND language_code=%s"
                                , $trid, $lang['code']));
                            if($translation_id){
                                $translation_status = $wpdb->get_var($wpdb->prepare("
                                    SELECT status FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d"
                                , $translation_id));
                                if(!is_null($translation_status) && $translation_status < ICL_TM_COMPLETE){
                                    $ddisabled = ' disabled="disabled"';
                                    $dtitle    = esc_attr__("Can't create a duplicate. A translation is in progress.", 'sitepress');
                                }
                            }
                        }
                        // do not allow creating duplicates for posts for which parents are not translated
                        if($post->post_parent){
                            $parent_tr = icl_object_id($post->post_parent, $post->post_type, false, $lang['code']);
                            if(is_null($parent_tr)){
                                $ddisabled = ' disabled="disabled"';
                                $dtitle    = esc_attr__("Can't create a duplicate. The parent of this post is not translated.", 'sitepress');
                            }
                        }
                    ?>
                    <input<?php echo $ddisabled?> type="checkbox" name="icl_dupes[]" value="<?php echo $lang['code'] ?>" title="<?php echo $dtitle ?>" />
                </td>

            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="3" align="right">
                <input id="icl_make_duplicates" type="button" class="button-secondary" value="<?php echo esc_attr('Duplicate', 'sitepress') ?>" disabled="disabled" style="display:none;" />
                <?php wp_nonce_field('make_duplicates_nonce', '_icl_nonce_mdup'); ?>
            </td>
        </tr>
        </table>
    <?php endif; ?>
    <?php if($translations_found > 0): ?>
    <?php if(!empty($iclTranslationManagement)){ $dupes = $iclTranslationManagement->get_duplicates($post->ID); } ?>
     <div class="icl_box_paragraph">

            <b><?php _e('Translations', 'sitepress') ?></b>
            (<a class="icl_toggle_show_translations" href="#" <?php if(empty($this->settings['show_translations_flag'])):?>style="display:none;"<?php endif;?>><?php _e('hide','sitepress')?></a><a class="icl_toggle_show_translations" href="#" <?php if(!empty($this->settings['show_translations_flag'])):?>style="display:none;"<?php endif;?>><?php _e('show','sitepress')?></a>)
            <?php wp_nonce_field('toggle_show_translations_nonce', '_icl_nonce_tst') ?>
        <table width="100%" class="icl_translations_table" id="icl_translations_table" <?php if(empty($this->settings['show_translations_flag'])):?>style="display:none;"<?php endif;?>>
        <?php $oddev = 1; ?>
        <?php foreach($active_languages as $lang): if($selected_language==$lang['code']) continue; ?>
        <tr <?php if($oddev < 0): ?>class="icl_odd_row"<?php endif; ?>>
            <?php if(isset($translations[$lang['code']]->element_id)):?>
                <?php
                    $oddev = $oddev*-1;
                    $img = 'edit_translation.png';
                    $edit_anchor = __('edit','sitepress');
                    list($needs_update, $in_progress) = $wpdb->get_row($wpdb->prepare("
                        SELECT needs_update, status = ".ICL_TM_IN_PROGRESS." FROM {$wpdb->prefix}icl_translation_status s JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = s.translation_id
                        WHERE t.trid = %d AND t.language_code = '%s'
                    ", $trid, $lang['code']), ARRAY_N);
                    $source_language_code  = $wpdb->get_var($wpdb->prepare("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND source_language_code IS NULL", $trid));
                    switch($iclTranslationManagement->settings['doc_translation_method']){
                        case ICL_TM_TMETHOD_EDITOR:
                            $job_id = $iclTranslationManagement->get_translation_job_id($trid, $lang['code']);

                            $args = array('lang_from'=>$selected_language, 'lang_to'=>$lang['code'], 'job_id'=>@intval($job_id));
                            $current_user_is_translator = $iclTranslationManagement->is_translator(get_current_user_id(), $args);

                            if($needs_update){
                                $img = 'needs-update.png';
                                $edit_anchor = __('Update translation','sitepress');
                                if($current_user_is_translator){
                                    $edit_link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&job_id='.$job_id);
                                    //$edit_link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&icl_tm_action=create_job&iclpost[]='.
                                    //    $post->ID.'&translate_to['.$lang['code'].']=1&iclnonce=' . wp_create_nonce('pro-translation-icl'));
                                }else{
                                    $edit_link = '#';
                                    $img = 'edit_translation_disabled.png';
                                    $edit_anchor = sprintf(__('You are not the translator of this document. <a%s>Learn more</a>.','sitepress'), ' href="https://wpml.org/?page_id=52218"');
                                }
                            }else{
                                if($lang['code'] == $source_language_code){
                                    $edit_link = '#';
                                    $img = 'edit_translation_disabled.png';
                                    $edit_anchor = __("You can't edit the original document using the translation editor",'sitepress');
                                }elseif($current_user_is_translator){
                                    $edit_link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&job_id='.$job_id);
                                }else{
                                    $edit_link = '#';
                                    $img = 'edit_translation_disabled.png';
                                    $edit_anchor = sprintf(__('You are not the translator of this document. <a%s>Learn more</a>.','sitepress'), ' href="https://wpml.org/?page_id=52218"');
                                }
                            }
                            break;
                        case ICL_TM_TMETHOD_PRO:
                            $job_id = $iclTranslationManagement->get_translation_job_id($trid, $lang['code']);
                            if($in_progress){
                                $img = 'in-progress.png';
                                $edit_link = '#';
                                $edit_anchor = __('Translation is in progress','sitepress');
                            }elseif($needs_update){
                                $img = 'needs-update.png';
                                $edit_anchor = __('Update translation','sitepress');
                                $qs = array();
                                if(!empty($_SERVER['QUERY_STRING']))
                                foreach($_exp = explode('&', $_SERVER['QUERY_STRING']) as $q=>$qv){
                                    $__exp = explode('=', $qv);
                                    $__exp[0] = preg_replace('#\[(.*)\]#', '', $__exp[0]);
                                    if(!in_array($__exp[0], array('icl_tm_action', 'translate_from', 'translate_to', 'iclpost', 'service', 'iclnonce'))){
                                        $qs[$q] = $qv;
                                    }
                                }
                                $edit_link = admin_url('post.php?'.join('&', $qs).'&icl_tm_action=send_jobs&translate_from='.$source_language
                                    .'&translate_to['.$lang['code'].']=1&iclpost[]='.$post->ID
                                    .'&service=icanlocalize&iclnonce=' . wp_create_nonce('pro-translation-icl'));
                            }else{
                                $edit_link = admin_url('admin.php?page='.WPML_TM_FOLDER.'/menu/translations-queue.php&job_id='.$job_id);
                            }
                            break;
                        default:
							if($needs_update){
							   $img = 'needs-update.png';
						   	}

                            $edit_link = get_edit_post_link($translations[$lang['code']]->element_id);
                    }
                ?>
                <td style="padding-left: 4px;">
                    <?php echo $lang['display_name'] ?>
                    <?php if(isset($dupes[$lang['code']])) echo ' (' . __('duplicate', 'sitepress') . ')'; ?>
                </td>
                <?php  $edit_link = apply_filters('wpml_post_edit_page_link_to_translation',$edit_link); ?>
                <td align="right" >

                <?php if($edit_link == '#'):
                    icl_pop_info($edit_anchor, ICL_PLUGIN_URL . '/res/img/' .$img, array('icon_size' => 16, 'but_style'=>array('icl_pop_info_but_noabs')));
                else: ?>
                <a href="<?php echo $edit_link ?>" title="<?php echo esc_attr($edit_anchor) ?>"><img border="0" src="<?php
                    echo ICL_PLUGIN_URL . '/res/img/' . $img ?>" alt="<?php echo esc_attr($edit_anchor) ?>" width="16" height="16" /></a>
                <?php endif; ?>

                </td>

            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </table>

       </div>

    <?php endif; ?>



    </div>
<?php endif; ?>

<?php do_action('icl_post_languages_options_after') ?>

<?php if(get_post_meta($post->ID, '_icl_lang_duplicate_of', true)): ?>
</span> <?php /* Hide everything else; */ ?>
<?php else: ?>


<?php
$show_dup_button = false;
$tr_original_id = 0;
$original_language = false;
if(!empty($translations))
{
	foreach($translations as $lang=>$tr){
		if($tr->original){
			$lang_details = $this->get_language_details($lang);
			$original_language = $lang_details['display_name'];
			$tr_original_id = $tr->element_id;
		}
		if($tr->element_id == $post->ID){
			$show_dup_button = true;
		}
	}
}
?>
<?php if($original_language && $tr_original_id != $post->ID && $show_dup_button): ?>
    <?php wp_nonce_field('set_duplication_nonce', '_icl_nonce_sd') ?>
    <input id="icl_set_duplicate" type="button" class="button-secondary" value="<?php printf(__('Overwrite with %s content.', 'sitepress'), $original_language) ?>" style="float: left;" />
    <span style="display: none;"><?php echo esc_js(sprintf(__('The current content of this %s will be permanently lost. WPML will copy the %s content and replace the current content.', 'sitepress'), $post->post_type, $original_language)); ?></span>
    <?php icl_pop_info(__("This operation will synchronize this translation with the original language. When you edit the original, this translation will update immediately. It's meant when you want the content in this language to always be the same as the content in the original language.", 'sitepress'), 'question'); ?>
    <br clear="all" />

<?php
endif;

endif;

