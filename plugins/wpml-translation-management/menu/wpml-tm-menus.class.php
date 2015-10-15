<?php
require WPML_TM_PATH . '/menu/basket-tab/sitepress-table-basket.class.php';
require WPML_TM_PATH . '/menu/dashboard/wpml-tm-dashboard.class.php';

if ( filter_input( INPUT_GET, 'sm', FILTER_SANITIZE_STRING ) === 'basket' ) {
    add_action( 'init', array( 'SitePress_Table_Basket', 'enqueue_js' ) );
}

class WPML_TM_Menus
{
    private $active_languages;
	private $translatable_types;
    private $current_document_words_count;
    private $current_language;
    private $filter_post_status;
    private $filter_translation_type;
    private $messages = array();

    private $odd_row;
    private $post_statuses;
    private $post_types;
    private $selected_languages;
    private $source_language;

    private $tab_items;

    private $base_target_url;

    private $current_shown_item;

    private $dashboard_title_sort_link;

    private $dashboard_date_sort_link;

    private $documents;

    private $selected_posts = array();
    private $translation_filter;

	function __construct() {
		$this->odd_row                      = false;
		$this->current_document_words_count = 0;
		$this->current_shown_item           = isset( $_GET[ 'sm' ] ) ? $_GET[ 'sm' ] : 'dashboard';
		$this->base_target_url              = dirname( __FILE__ );
	}

    public function display_main()
    {
        $this->render_main();
    }

    private function render_main()
    {
        ?>
        <div class="wrap">
            <div id="icon-wpml" class="icon32"><br/></div>
            <h2><?php echo __('Translation management', 'wpml-translation-management') ?></h2>

            <?php do_action('icl_tm_messages');

            $this->implode_messages();

            $this->build_tab_items();

            $this->render_items();
            ?>
        </div>
    <?php

    }

    private function implode_messages()
    {
        if ($this->messages) {
            echo implode('', $this->messages);
        }
    }

    private function build_tab_item_target_url($target)
    {
        return $this->base_target_url . $target;
    }

    private function build_tab_items()
    {
        $this->tab_items = array();

        $this->build_dashboard_item();
        $this->build_translators_item();
        $this->build_basket_item();
        $this->build_translation_jobs_item();
        $this->build_mcs_item();
        $this->build_translation_notifications_item();
		$this->build_tp_com_log_item();

        $this->tab_items = apply_filters('wpml_tm_tab_items', $this->tab_items);
    }

	/**
	 * @param int $basket_items_count
	 *
	 * @return string|void
	 */
    private function build_basket_item_caption( $basket_items_count = 0 )
    {

		if ( isset( $_GET[ 'clear_basket' ] ) && $_GET[ 'clear_basket' ] ) {
            $basket_items_count = 0;
        } else {

			if (! is_numeric( $basket_items_count )) {
				$basket_items_count = TranslationProxy_Basket::get_basket_items_count( true );
			}
            if ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'delete' && isset( $_GET[ 'id' ] ) && $_GET[ 'id' ] ) {
                $basket_items_count -= 1;
            }
        }
        $basket_items_count_caption = __('Translation Basket', 'wpml-translation-management');
        if ($basket_items_count > 0) {
            $basket_item_count_badge = '<span id="wpml-basket-items"><span id="basket-item-count">' . $basket_items_count . '</span></span>';
            $basket_items_count_caption .= $basket_item_count_badge;
        }
        return $basket_items_count_caption;

    }

	/**
	 * @return bool
	 */
	private function can_display_translation_services() {
		global $sitepress;

		return ( defined( 'WPML_BYPASS_TS_CHECK' ) && WPML_BYPASS_TS_CHECK )
			   || ! $sitepress->get_setting( 'translation_service_plugin_activated' );
	}

    private function build_translation_notifications_item()
    {
        $this->tab_items['notifications']['caption'] = __('Translation Notifications', 'wpml-translation-management');
        //$this->tab_items['notifications']['target'] = $this->build_tab_item_target_url('/sub/notifications.php');
        $this->tab_items['notifications']['callback'] = array($this, 'build_content_translation_notifications');
    }

    private function build_mcs_item()
    {
        $this->tab_items['mcsetup']['caption'] = __('Multilingual Content Setup', 'wpml-translation-management');
        //$this->tab_items['mcsetup']['target'] = $this->build_tab_item_target_url('/sub/mcsetup.php');
        $this->tab_items['mcsetup']['callback'] = array($this, 'build_content_mcs');
    }

    private function build_translation_jobs_item()
    {
        $this->tab_items['jobs']['caption'] = __('Translation Jobs', 'wpml-translation-management');
        //$this->tab_items['jobs']['target'] = $this->build_tab_item_target_url('/sub/jobs.php');
        $this->tab_items['jobs']['callback'] = array($this, 'build_content_translation_jobs');
    }

    private function build_basket_item()
    {
	    $basket_items_count = TranslationProxy_Basket::get_basket_items_count( true );

        if ( $basket_items_count > 0 ) {

            $this->tab_items['basket']['caption'] = $this->build_basket_item_caption( $basket_items_count );
            //$this->tab_items['basket']['target'] = $this->build_tab_item_target_url( '/sub/basket.php' );
            $this->tab_items['basket']['callback'] = array($this, 'build_content_basket');

        }
    }

    private function build_translators_item()
    {
        $this->tab_items['translators']['caption'] = __('Translators', 'wpml-translation-management');
        $this->tab_items['translators']['current_user_can'] = 'list_users';
        //$this->tab_items['translators']['target'] = $this->build_tab_item_target_url('/sub/translators.php');
        $this->tab_items['translators']['callback'] = array($this, 'build_content_translators');
    }

    private function build_dashboard_item()
    {
        $this->tab_items['dashboard']['caption'] = __('Translation Dashboard', 'wpml-translation-management');
        $this->tab_items['dashboard']['callback'] = array($this, 'build_content_dashboard');
    }

    /**
     * @return string
     */
    private function get_current_shown_item()
    {
        return $this->current_shown_item;
    }

    /**
     * @return array
     */
    private function build_tabs()
    {
        $tm_sub_menu = $this->get_current_shown_item();
        foreach ($this->tab_items as $id => $tab_item) {
            if (!isset($tab_item['caption'])) {
                continue;
            }
            if (!isset($tab_item['target']) && !isset($tab_item['callback'])) {
                continue;
            }

            $caption = $tab_item['caption'];
            $current_user_can = isset($tab_item['current_user_can']) ? $tab_item['current_user_can'] : false;

            if ($current_user_can && !current_user_can($current_user_can)) {
                continue;
            }

            $classes = array(
                'nav-tab'
            );
            if ($tm_sub_menu == $id) {
                $classes[] = 'nav-tab-active';
            }

            $class = implode(' ', $classes);
            $href = 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=' . $id;
            ?>
            <a class="<?php echo $class; ?>" href="<?php echo $href; ?>">
                <?php echo $caption; ?>
            </a>
        <?php
        }
    }

    private function build_content()
    {
        $tm_sub_menu = $this->get_current_shown_item();
        foreach ($this->tab_items as $id => $tab_item) {
            if (!isset($tab_item['caption'])) {
                continue;
            }
            if (!isset($tab_item['target']) && !isset($tab_item['callback'])) {
                continue;
            }

            if ($tm_sub_menu == $id) {
                if (isset($tab_item['target'])) {
                    $target = $tab_item['target'];
                    /** @noinspection PhpIncludeInspection */
                    include $this->build_tab_item_target_url($target);
                }
                if (isset($tab_item['callback'])) {
                    $callback = $tab_item['callback'];
                    call_user_func($callback);
                }
            }
        }
        do_action('icl_tm_menu_' . $tm_sub_menu);
    }

    public function build_content_dashboard()
    {
	    /** @var SitePress $sitepress */
        global $sitepress;
        $this->active_languages = $sitepress->get_active_languages();
		$this->translatable_types = $sitepress->get_translatable_documents();
        $this->build_dashboard_data();

        $this->build_content_dashboard_filter();
        $this->build_content_dashboard_results();
        $this->build_content_dashboard_remote_translations_controls();
    }

    public function build_content_translators() {
        global $iclTranslationManagement, $wpdb, $sitepress;

        require_once 'wpml-translator-settings.class.php';
        $translator_settings = new WPML_Translator_Settings( $wpdb, $sitepress, $iclTranslationManagement );
        $translator_settings->build_header_content();
        ?>

		<a href="#your_translators"><?php _e( 'Your Translators', 'wpml-translation-management' ); ?></a> &nbsp;&nbsp;<a href="#translation_services"><?php _e( 'Translation Services', 'wpml-translation-management' ); ?></a>

		<a id="your_translators"><h3><?php _e( 'Your Translators', 'wpml-translation-management' ); ?></h3></a><?php

        $translator_settings->build_content_translators();

        if ( !defined( 'ICL_HIDE_TRANSLATION_SERVICES' ) || !ICL_HIDE_TRANSLATION_SERVICES ) {
            ?><a id="translation_services"><h3><?php _e( 'Available Translation Services', 'wpml-translation-management' ) ?></h3></a><?php
            if ( $this->can_display_translation_services() ) {

                if ( $this->site_key_exist() || $this->is_any_translation_service_active() ) {
                    $translator_settings->build_content_translation_services();
                } else {
                    echo $this->build_link_to_register_plugin();
                }
            }
        }
    }
	
	private function site_key_exist(){
				
		if(class_exists('WP_Installer')){
			$repository_id 	= 'wpml';
			$site_key 		= WP_Installer()->get_site_key($repository_id); 
		}			
			
	return $does_exist = ($site_key !== false ? true : false );
	}
	
	private function is_any_translation_service_active(){
		
		$is_active = TranslationProxy::get_current_service();		
		
	return $feedback = ( $is_active !== false ? true : false );
	}	
	
	private function build_link_to_register_plugin(){
		
		$link = sprintf( '<a class="button-secondary" href="%s">' . __( 'Please register WPML to enable the professional translation option', 'wpml-translation-management') . '</a>',
						admin_url('plugin-install.php?tab=commercial#repository-wpml') );

	return $link;
	}		

	public function build_content_basket() {
		$basket_table = new SitePress_Table_Basket();
		$basket_table->prepare_items();

		$action_url = esc_attr( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=' . $_GET[ 'sm' ] );

		?>
		<h3>1. <?php _e( 'Review documents for translation', 'wpml-translation-management' ) ?></h3>

		<form method="post" id="translation-jobs-basket-form" class="js-translation-jobs-basket-form"
		      data-message="<?php _e( 'You are about to delete selected items from the basket. Are you sure you want to do that?',
		                              'wpml-translation-management' ) ?>"
		      name="translation-jobs-basket" action="<?php echo $action_url; ?>">
			<?php
			$basket_table->display();
			?>
		</form>
		<?php
		$this->build_translation_options();
	}

    private function build_translation_options() {
        global $sitepress, $wpdb;
        $basket_items_number = TranslationProxy_Basket::get_basket_items_count(true);
        $basket_name_max_length = TranslationProxy::get_current_service_batch_name_max_length();

        if ( $basket_items_number > 0 ) {
            $source_language         = TranslationProxy_Basket::get_source_language();
            $basket_name_placeholder = sprintf( __( "%s|WPML|%s", 'wpml-translation-management' ), get_option( 'blogname' ), $source_language );
            $basket = new WPML_Translation_Basket( $wpdb );
            $basket_name_placeholder = esc_attr( $basket->get_unique_basket_name( $basket_name_placeholder, $basket_name_max_length ) );
            ?>
            <h3>2. <?php _e( 'Choose translation options', 'wpml-translation-management' ) ?></h3>

            <form method="post" id="translation-jobs-translators-form" name="translation-jobs-translators" action="">
                <input type="hidden" name="icl_tm_action" value="send_all_jobs"/>
                <label for="basket_name"><strong><?php _e( 'Batch name', 'wpml-translation-management' ) ?>:</strong></label>
                &nbsp;<input id="basket_name"
                             name="basket_name"
                             type="text"
                             style="width: 40%;"
                             value="<?php echo $basket_name_placeholder; ?>"
                             maxlength="<?php echo $basket_name_max_length; ?>"
                             placeholder="<?php echo $basket_name_placeholder; ?>">
                <br/><span class="description"><?php _e( 'Give a name to the batch. If omitted, the default name will be applied.', 'wpml-translation-management' ) ?></span>

                <table class="widefat fixed" id="icl-translation-translators" cellspacing="0">
                    <thead>
                    <tr>
                        <th scope="col" width="15%"><?php _e( 'Language', 'wpml-translation-management' ) ?></th>
                        <th scope="col"><?php _e( 'Translator', 'wpml-translation-management' ) ?></th>
                    </tr>
                    </thead>
                    <tfoot>
                    <tr>
                        <th scope="col"><?php _e( 'Language', 'wpml-translation-management' ) ?></th>
                        <th scope="col"><?php _e( 'Translator', 'wpml-translation-management' ) ?></th>
                    </tr>
                    </tfoot>
                    <?php
                    $basket_languages = TranslationProxy_Basket::get_target_languages();
                    if ( $basket_languages ) {
                        ?>
                        <tbody>
                        <?php
                        $target_languages = $sitepress->get_active_languages();
                        foreach ( $target_languages as $key => $lang ) {
                            if ( ! in_array( $lang[ 'code' ], $basket_languages ) ) {
                                unset( $target_languages[ $key ] );
                            }
                        }
                        foreach ( $target_languages as $lang ) {
                            if ( $lang[ 'code' ] === TranslationProxy_Basket::get_source_language() ) {
                                continue;
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo $lang[ 'display_name' ] ?></strong></td>
                                <td>
                                    <label for="<?php echo esc_attr( 'translator[' . $lang[ 'code' ] . ']' ); ?>">
                                        <?php _e( 'Translate by', 'wpml-translation-management' ); ?>
                                    </label>
                                    <?php
                                    $selected_translator = isset( $icl_selected_translators[ $lang[ 'code' ] ] ) ? $icl_selected_translators[ $lang[ 'code' ] ] : false;
                                    if ( $selected_translator === false ) {
                                        $selected_translator = TranslationProxy_Service::get_wpml_translator_id();
                                    }
                                    $args = array(
                                        'from'     => TranslationProxy_Basket::get_source_language(),
                                        'to'       => $lang[ 'code' ],
                                        'name'     => 'translator[' . $lang[ 'code' ] . ']',
                                        'selected' => $selected_translator,
                                        'services' => array( 'local', TranslationProxy::get_current_service_id() )
                                    );
                                    TranslationManagement::translators_dropdown( $args );
                                    ?>
                                    <a href="admin.php?page=<?php echo WPML_TM_FOLDER ?>/menu/main.php&sm=translators"><?php _e( 'Manage translators', 'wpml-translation-management' ); ?></a>
                                </td>
                            </tr>
                        <?php
                        }
                        ?>
                        </tbody>
                    <?php
                    }
                    ?>
                </table>
                <br>
                <?php echo TranslationProxy_Basket::get_basket_extra_fields_section(); ?>
                <?php wp_nonce_field( 'send_basket_items_nonce', '_icl_nonce_send_basket_items' ); ?>
                <?php wp_nonce_field( 'send_basket_item_nonce', '_icl_nonce_send_basket_item' ); ?>
                <?php wp_nonce_field( 'send_basket_commit_nonce', '_icl_nonce_send_basket_commit' ); ?>
                <?php wp_nonce_field( 'check_basket_name_nonce', '_icl_nonce_check_basket_name' ); ?>
                <input type="submit" class="button-primary" name="send-all-jobs-for-translation" value="<?php _e( 'Send all items for translation', 'wpml-translation-management' ); ?>">
            </form>
        <?php
        }
		
		do_action( 'wpml_translation_basket_page_after' );
    }

	public function build_content_translation_jobs() {
		?>

		<span class="spinner waiting-1" style="display: inline-block; float:none; visibility: visible"></span>

		<fieldset class="filter-row"></fieldset>
		<div class="listing-table wpml-translation-management-jobs" id="icl-tm-jobs-form" style="display: none;">
			<h3><?php _e( 'Jobs', 'wpml-translation-management' ) ?></h3>
			<table id="icl-translation-jobs" class="wp-list-table widefat fixed">
				<thead>
				<tr>
					<th scope="col" id="cb" class="manage-column check-column" style="">
						<label class="screen-reader-text" for="bulk-select-top"><?php _e( 'Select All', 'wpml-translation-management' ) ?></label>
						<input id="bulk-select-top" class="bulk-select-checkbox" type="checkbox">
					</th>
					<th scope="col" id="job_id" class="manage-column column-job_id" style="">
						<?php _e( 'Job ID', 'wpml-translation-management' ) ?>
					</th>
					<th scope="col" id="title" class="manage-column column-title" style="">
						<?php _e( 'Title', 'wpml-translation-management' ) ?>
					</th>
					<th scope="col" id="language" class="manage-column column-language" style="">
						<?php _e( 'Language', 'wpml-translation-management' ) ?>
					</th>
					<th scope="col" id="status" class="manage-column column-status" style="">
						<?php _e( 'Status', 'wpml-translation-management' ) ?>
					</th>
					<th scope="col" id="translator" class="manage-column column-translator" style="">
						<?php _e( 'Translator', 'wpml-translation-management' ) ?>
					</th>
				</tr>
                </thead>
                <tfoot>
				<tr>
					<th scope="col" id="cb" class="manage-column check-column" style="">
						<label class="screen-reader-text" for="bulk-select-bottom"><?php _e( 'Select All', 'wpml-translation-management' ) ?></label>
						<input id="bulk-select-bottom" class="bulk-select-checkbox" type="checkbox">
					</th>
					<th scope="col" id="job_id" class="manage-column column-job_id" style="">
						<?php _e( 'Job ID', 'wpml-translation-management' ) ?>
					</th>
					<th scope="col" id="title" class="manage-column column-title" style="">
						<?php _e( 'Title', 'wpml-translation-management' ) ?>
					</th>
					<th scope="col" id="language" class="manage-column column-language" style="">
						<?php _e( 'Language', 'wpml-translation-management' ) ?>
					</th>
					<th scope="col" id="status" class="manage-column column-status" style="">
						<?php _e( 'Status', 'wpml-translation-management' ) ?>
					</th>
					<th scope="col" id="translator" class="manage-column column-translator" style="">
						<?php _e( 'Translator', 'wpml-translation-management' ) ?>
					</th>
				</tr>
				</tfoot>
                <tbody class="groups"></tbody>
            </table>

			<br/>

			<?php wp_nonce_field( 'assign_translator_nonce', '_icl_nonce_at' ) ?>
			<input type="hidden" name="icl_tm_action" value=""/>
			<input id="icl-tm-jobs-cancel-but" name="icl-tm-jobs-cancel-but" class="button-primary" type="submit" value="<?php _e( 'Cancel selected', 'wpml-translation-management' ) ?>" disabled="disabled"/>
			<span id="icl-tm-jobs-cancel-msg" style="display: none"><?php _e( 'Are you sure you want to cancel these jobs?', 'wpml-translation-management' ); ?></span>
			<span id="icl-tm-jobs-cancel-msg-2" style="display: none"><?php _e( 'WARNING: %s job(s) are currently being translated.', 'wpml-translation-management' ); ?></span>
			<span id="icl-tm-jobs-cancel-msg-3" style="display: none"><?php _e( 'Are you sure you want to abort this translation?', 'wpml-translation-management' ); ?></span>

			<span class="navigator"></span>

			<span class="spinner waiting-2" style="display: none; float:none; visibility: visible"></span>

			<?php wp_nonce_field( 'icl_cancel_translation_jobs_nonce', 'icl_cancel_translation_jobs_nonce' ); ?>
			<?php wp_nonce_field( 'icl_get_jobs_table_data_nonce', 'icl_get_jobs_table_data_nonce' ); ?>
		</div>

		<?php
		TranslationManagement::include_underscore_templates( 'listing' );
	}

    public function build_content_mcs()
    {
        /**
         * included by menu translation-management.php
         *
         * @uses TranslationManagement
         */
        global $sitepress, $iclTranslationManagement;

	      $doc_translation_method = isset($iclTranslationManagement->settings['doc_translation_method']) ? intval($iclTranslationManagement->settings['doc_translation_method']) : ICL_TM_TMETHOD_MANUAL;

        ?>

        <ul class="wpml-navigation-links js-wpml-navigation-links">
            <li><a href="#ml-content-setup-sec-1"><?php _e('How to translate posts and pages', 'wpml-translation-management'); ?></a></li>
            <li><a href="#ml-content-setup-sec-2"><?php _e('Posts and pages synchronization', 'wpml-translation-management'); ?></a></li>
            <li>
                <a href="#ml-content-setup-sec-3"><?php _e('Translated documents options', 'wpml-translation-management'); ?></a>
            </li>
            <?php if (defined('WPML_ST_VERSION')): ?>
                <li>
                    <a href="#ml-content-setup-sec-4"><?php _e('Custom posts slug translation options', 'wpml-string-translation'); ?></a>
                </li>
            <?php endif; ?>
            <li>
                <a href="#ml-content-setup-sec-5"><?php _e('Translation pickup mode', 'wpml-translation-management'); ?></a>
            </li>
            <?php if (defined('WPML_XLIFF_EMBED_VERSION')): ?>
                <li><a href="#ml-content-setup-sec-5-1"><?php _e('XLIFF file options', 'wpml-xliff'); ?></a></li>
            <?php endif; ?>
            <li>
                <a href="#ml-content-setup-sec-6"><?php _e('Custom fields translation', 'wpml-translation-management'); ?></a>
            </li>
            <?php


            $custom_posts = array();
            $this->post_types = $sitepress->get_translatable_documents(true);

            foreach ($this->post_types as $k => $v) {
                if (!in_array($k, array('post', 'page'))) {
                    $custom_posts[$k] = $v;
                }
            }

            global $wp_taxonomies;
            $custom_taxonomies = array_diff(array_keys((array)$wp_taxonomies), array('post_tag', 'category', 'nav_menu', 'link_category', 'post_format'));
            ?>
            <?php if ($custom_posts): ?>
                <li><a href="#ml-content-setup-sec-7"><?php _e('Custom posts', 'wpml-translation-management'); ?></a>
                </li>
            <?php endif; ?>
            <?php if ($custom_taxonomies): ?>
                <li><a href="#ml-content-setup-sec-8"><?php _e('Custom taxonomies', 'wpml-translation-management'); ?></a>
                </li>
            <?php endif; ?>
            <?php if (!empty($iclTranslationManagement->admin_texts_to_translate) && function_exists('icl_register_string')): ?>
                <li>
                    <a href="#ml-content-setup-sec-9"><?php _e('Admin Strings to Translate', 'wpml-translation-management'); ?></a>
                </li>
            <?php endif; ?>
        </ul>

        <div class="wpml-section wpml-section-notice">
            <div class="updated below-h2">
                <p>
                    <?php _e("WPML can read a configuration file that tells it what needs translation in themes and plugins. The file is named wpml-config.xml and it's placed in the root folder of the plugin or theme.", 'wpml-translation-management'); ?>
                </p>

                <p>
                    <a href="https://wpml.org/?page_id=5526"><?php _e('Learn more', 'wpml-translation-management') ?></a>
                </p>
            </div>
        </div>

        <div class="wpml-section" id="ml-content-setup-sec-1">

            <div class="wpml-section-header">
                <h3><?php _e('How to translate posts and pages', 'wpml-translation-management'); ?></h3>
            </div>

            <div class="wpml-section-content">

                <form id="icl_doc_translation_method" name="icl_doc_translation_method" action="">
                    <?php wp_nonce_field('icl_doc_translation_method_nonce', '_icl_nonce') ?>

                    <ul class="t_method">
                        <li>
	                        <label>
		                        <input type="radio" name="t_method" value="<?php echo ICL_TM_TMETHOD_MANUAL ?>"
		                               <?php if ( ! $doc_translation_method): ?>checked="checked"<?php endif; ?> />
		                        <?php _e( 'Create translations manually', 'wpml-translation-management' ) ?>
	                        </label>
                        </li>
	                    <li>
		                    <label>
			                    <input type="radio" name="t_method" value="<?php echo ICL_TM_TMETHOD_EDITOR ?>"
			                           <?php if ($doc_translation_method): ?>checked="checked"<?php endif; ?> />
			                    <?php _e( 'Use the translation editor', 'wpml-translation-management' ) ?>
		                    </label>
	                    </li>
                    </ul>
                    <p id="tm_block_retranslating_terms"><label>
                            <input name="tm_block_retranslating_terms"
                                   value="1" <?php checked( icl_get_setting( 'tm_block_retranslating_terms' ),
                                                            "1" ) ?>
                                   type="checkbox"/>
                            <?php _e( 'Block translating taxonomy terms (from the Translation Editor) that have already been translated.',
                                      'wpml-translation-management' ) ?>
                        </label>
                    </p>

                    <p>
                        <label>
                            <input name="how_to_translate"
                                   value="1" <?php checked(icl_get_setting('hide_how_to_translate'), false) ?>
                                   type="checkbox"/>
                            <?php _e('Show translation instructions in the list of pages', 'wpml-translation-management') ?>
                        </label>
                    </p>

                    <p>
                        <a href="https://wpml.org/?page_id=3416"
                           target="_blank"><?php _e('Learn more about the different translation options', 'wpml-translation-management') ?></a>
                    </p>

                    <p class="buttons-wrap">
                        <span class="icl_ajx_response" id="icl_ajx_response_dtm"></span>
                        <input type="submit" class="button-primary"
                               value="<?php _e('Save', 'wpml-translation-management') ?>"/>
                    </p>

                </form>
            </div>
            <!-- .wpml-section-content -->

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
                            <?php _e('Document status', 'wpml-translation-management') ?>
                        </h4>
                        <ul>
                            <li>
                                <label>
                                    <input type="radio" name="icl_translated_document_status" value="0"
                                           <?php checked(icl_get_setting('translated_document_status'), false); ?> />
                                    <?php _e('Draft', 'wpml-translation-management') ?>
                                </label>
                            </li>
                            <li>
                                <label>
                                    <input type="radio" name="icl_translated_document_status" value="1"
                                           <?php checked(icl_get_setting('translated_document_status'), true); ?> />
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
                            <?php _e('Page URL', 'wpml-translation-management') ?>
                        </h4>
                        <ul>
                            <li>
                                <label><input type="radio" name="icl_translated_document_page_url" value="auto-generate"
                                              <?php if (empty($sitepress_settings['translated_document_page_url']) ||
                                              $sitepress_settings['translated_document_page_url'] == 'auto-generate'): ?>checked="checked"<?php endif; ?> />
                                    <?php _e('Auto-generate from title (default)', 'wpml-translation-management') ?>
                                </label>
                            </li>
                            <li>
                                <label><input type="radio" name="icl_translated_document_page_url" value="translate"
                                              <?php if ($sitepress_settings['translated_document_page_url'] == 'translate'): ?>checked="checked"<?php endif; ?> />
                                    <?php _e('Translate (this will include the slug in the translation and not create it automatically from the title)', 'wpml-translation-management') ?>
                                </label>
                            </li>
                            <li>
                                <label><input type="radio" name="icl_translated_document_page_url" value="copy-encoded"
                                              <?php if ($sitepress_settings['translated_document_page_url'] == 'copy-encoded'): ?>checked="checked"<?php endif; ?> />
                                    <?php _e('Copy from original language if translation language uses encoded URLs', 'wpml-translation-management') ?>
                                </label>
                            </li>
                        </ul>
                    </div>

                    <div class="wpml-section-content-inner">
                        <p class="buttons-wrap">
                            <span class="icl_ajx_response" id="icl_ajx_response_tdo"></span>
                            <input type="submit" class="button-primary"
                                   value="<?php _e('Save', 'wpml-translation-management') ?>"/>
                        </p>
                    </div>

                </form>
            </div>
            <!-- .wpml-section-content -->

        </div> <!-- .wpml-section -->

        <?php if (defined('WPML_ST_VERSION')) include WPML_ST_PATH . '/menu/_slug-translation-options.php'; ?>

        <div class="wpml-section" id="ml-content-setup-sec-5">

            <div class="wpml-section-header">
                <h3><?php _e('Translation pickup mode', 'wpml-translation-management'); ?></h3>
            </div>

            <div class="wpml-section-content">

                <form id="icl_translation_pickup_mode" name="icl_translation_pickup_mode" action="">
                    <?php wp_nonce_field('set_pickup_mode_nonce', '_icl_nonce') ?>

                    <p>
                        <?php echo __('How should the site receive completed translations from Translation Service?', 'wpml-translation-management'); ?>
                    </p>

                    <p>
                        <label>
                            <input type="radio" name="icl_translation_pickup_method"
                                   value="<?php echo ICL_PRO_TRANSLATION_PICKUP_XMLRPC ?>"
                                   <?php if ($sitepress_settings['translation_pickup_method'] == ICL_PRO_TRANSLATION_PICKUP_XMLRPC): ?>checked="checked"<?php endif ?>/>
                            <?php echo __('Translation Service will deliver translations automatically using XML-RPC', 'wpml-translation-management'); ?>
                        </label>
                    </p>

                    <p>
                        <label>
                            <input type="radio" name="icl_translation_pickup_method"
                                   value="<?php echo ICL_PRO_TRANSLATION_PICKUP_POLLING ?>"
                                   <?php if ($sitepress_settings['translation_pickup_method'] == ICL_PRO_TRANSLATION_PICKUP_POLLING): ?>checked="checked"<?php endif; ?> />
                            <?php _e('The site will fetch translations manually', 'wpml-translation-management'); ?>
                        </label>
                    </p>


                    <p class="buttons-wrap">
                        <span class="icl_ajx_response" id="icl_ajx_response_tpm"></span>
                        <input class="button-primary" name="save"
                               value="<?php _e('Save', 'wpml-translation-management') ?>" type="submit"/>
                    </p>

                    <?php
                    $this->build_content_dashboard_fetch_translations_box();
                    ?>
                </form>

            </div>
            <!-- .wpml-section-content -->

        </div> <!-- .wpml-section -->

        <?php
        if (defined('WPML_XLIFF_EMBED_VERSION')) {
            include WPML_TM_PATH . '/menu/xliff-options.php';
        }

	      $this->build_content_mcs_custom_fields();


	      include ICL_PLUGIN_PATH . '/menu/_custom_types_translation.php'; ?>

        <?php if (!empty($iclTranslationManagement->admin_texts_to_translate) && function_exists('icl_register_string')): //available only with the String Translation plugin ?>
        <div class="wpml-section" id="ml-content-setup-sec-9">

            <div class="wpml-section-header">
                <h3><?php _e('Admin Strings to Translate', 'wpml-translation-management'); ?></h3>
            </div>

            <div class="wpml-section-content">
                <table class="widefat">
                    <thead>
                    <tr>
                        <th colspan="3">
                            <?php _e('Admin Strings', 'wpml-translation-management'); ?>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <?php
                            foreach ($iclTranslationManagement->admin_texts_to_translate as $option_name => $option_value) {
                                $iclTranslationManagement->render_option_writes($option_name, $option_value);
                            }
                            ?>
                            <br/>

                            <p><a class="button-secondary"
                                  href="<?php echo admin_url('admin.php?page=' . WPML_ST_FOLDER . '/menu/string-translation.php') ?>"><?php _e('Edit translatable strings', 'wpml-translation-management') ?></a>
                            </p>
                        </td>
                    </tr>
                    </tbody>
                </table>

            </div>
            <!-- .wpml-section-content -->

        </div> <!-- .wpml-section -->
    <?php

    endif;
    }

	private function build_content_mcs_custom_fields() {
		global $wpdb, $iclTranslationManagement;
		?>

		<div class="wpml-section wpml-section-cf-translation" id="ml-content-setup-sec-6">

			<div class="wpml-section-header">
				<h3><?php _e( 'Custom fields translation', 'wpml-translation-management' ); ?></h3>
			</div>

			<div class="wpml-section-content">

				<form id="icl_cf_translation" name="icl_cf_translation" action="">

					<?php

					$custom_fields_settings_name                 = $iclTranslationManagement->get_translation_setting_name( 'custom_fields' );
					$custom_fields_readonly_settings_name        = $iclTranslationManagement->get_readonly_translation_setting_name( 'custom_fields' );
					$custom_fields_readonly_custom_settings_name = $iclTranslationManagement->get_custom_readonly_translation_setting_name( 'custom_fields' );

					$custom_fields_settings                 = $iclTranslationManagement->settings[ $custom_fields_settings_name ];
					$custom_fields_settings                 = isset( $custom_fields_settings ) ? $custom_fields_settings : array();
					$custom_fields_readonly_settings        = $iclTranslationManagement->settings[ $custom_fields_readonly_settings_name ];
					$custom_fields_readonly_settings        = isset( $custom_fields_readonly_settings ) ? $custom_fields_readonly_settings : array();
					$custom_fields_readonly_custom_settings = $iclTranslationManagement->settings[ $custom_fields_readonly_custom_settings_name ];
					$custom_fields_readonly_custom_settings = isset( $custom_fields_readonly_custom_settings ) ? $custom_fields_readonly_custom_settings : array();

					$custom_fields_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM $wpdb->postmeta" );

					$custom_fields_keys = array_unique( array_merge( $custom_fields_keys, $custom_fields_readonly_settings ) );
					$custom_fields_keys = array_unique( array_merge( $custom_fields_keys, $custom_fields_readonly_custom_settings ) );

					if ( $custom_fields_keys ) {
						natcasesort( $custom_fields_keys );
					}

					?>
					<?php wp_nonce_field( 'icl_cf_translation_nonce', '_icl_nonce' ); ?>
					<?php if ( empty( $custom_fields_keys ) ): ?>
						<p>
							<?php _e( 'No custom fields found. It is possible that they will only show up here after you add more posts after installing a new plugin.', 'wpml-translation-management' ); ?>
						</p>
					<?php else: ?>
						<table class="widefat fixed">
							<thead>
							<tr>
								<th>
									<?php _e( 'Custom fields', 'wpml-translation-management' ); ?>
								</th>
								<th>
									<?php _e( "Don't translate", 'wpml-translation-management' ) ?>
								</th>
								<th>
									<?php _e( "Copy from original to translation", 'wpml-translation-management' ) ?>
								</th>
								<th>
									<?php _e( "Translate", 'wpml-translation-management' ) ?>
								</th>
							</tr>
							</thead>
							<tfoot>
							<tr>
								<th>
									<?php _e( 'Custom fields', 'wpml-translation-management' ); ?>
								</th>
								<th>
									<?php _e( "Don't translate", 'wpml-translation-management' ) ?>
								</th>
								<th>
									<?php _e( "Copy from original to translation", 'wpml-translation-management' ) ?>
								</th>
								<th>
									<?php _e( "Translate", 'wpml-translation-management' ) ?>
								</th>
							</tr>
							</tfoot>
							<tbody>
							<?php foreach ( $custom_fields_keys as $cf_index => $cf_key ): ?>
								<?php
								if ( ! is_numeric( $cf_index ) ) {
									continue;
								}

								$is_readonly = in_array( $cf_key, $custom_fields_readonly_settings ) || in_array( $cf_key, $custom_fields_readonly_custom_settings );
								$is_hidden   = false;
								if ($is_readonly && array_key_exists( $cf_key, $custom_fields_settings ) ) {
									$is_hidden = in_array( $custom_fields_settings[ $cf_key ], array( 0, 3 ) );
								}
								$html_disabled = $is_readonly ? 'disabled="disabled"' : '';

								if ( $is_hidden ) {
									continue;
								}
								?>
								<tr>
									<td><?php echo $cf_key ?></td>
									<td title="<?php _e( "Don't translate", 'wpml-translation-management' ) ?>">
										<input type="radio" name="cf[<?php echo base64_encode( $cf_key ) ?>]" value="0" <?php echo $html_disabled ?>
										       <?php if (isset( $custom_fields_settings[ $cf_key ] ) && $custom_fields_settings[ $cf_key ] == 0): ?>checked<?php endif; ?> />
									</td>
									<td title="<?php _e( "Copy from original to translation", 'wpml-translation-management' ) ?>">
										<input type="radio" name="cf[<?php echo base64_encode( $cf_key ) ?>]" value="1" <?php echo $html_disabled ?>
										       <?php if (isset( $custom_fields_settings[ $cf_key ] ) && $custom_fields_settings[ $cf_key ] == 1): ?>checked<?php endif; ?> />
									</td>
									<td title="<?php _e( "Translate", 'wpml-translation-management' ) ?>">
										<input type="radio" name="cf[<?php echo base64_encode( $cf_key ) ?>]" value="2" <?php echo $html_disabled ?>
										       <?php if (isset( $custom_fields_settings[ $cf_key ] ) && $custom_fields_settings[ $cf_key ] == 2): ?>checked<?php endif; ?> />
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>

						<p class="buttons-wrap">
							<span class="icl_ajx_response" id="icl_ajx_response_cf"></span>
							<input type="submit" class="button-primary" value="<?php _e( 'Save', 'wpml-translation-management' ) ?>"/>
						</p>
					<?php endif; ?>

				</form>

			</div>
			<!-- .wpml-section-content -->

		</div> <!-- .wpml-section -->

	<?php
	}

    public function build_content_translation_notifications()
    {
        global $iclTranslationManagement;
        $nsettings = $iclTranslationManagement->settings['notification']; ?>

        <form method="post" name="translation-notifications"
              action="admin.php?page=<?php echo WPML_TM_FOLDER ?>/menu/main.php&amp;sm=notifications">
            <input type="hidden" name="icl_tm_action" value="save_notification_settings"/>

            <div class="wpml-section" id="translation-notifications-sec-1">
                <div class="wpml-section-header">
                    <h4><?php _e('Notify translator about new job:', 'wpml-translation-management'); ?></h4>
                </div>
                <div class="wpml-section-content">
                    <ul>
                        <li>
                            <input name="notification[new-job]" type="radio" id="icl_tm_notify_translator"
                                   value="<?php echo ICL_TM_NOTIFICATION_IMMEDIATELY ?>" <?php if ($nsettings['new-job']
                            == ICL_TM_NOTIFICATION_IMMEDIATELY): ?>checked="checked"<?php endif; ?> />
                            <label
                                for="icl_tm_notify_translator"><?php _e('Notify immediately', 'wpml-translation-management'); ?></label>
                        </li>
                        <li>
                            <input name="notification[new-job]" type="radio" id="icl_tm_notify_translator_dont"
                                   value="<?php echo ICL_TM_NOTIFICATION_NONE ?>" <?php if ($nsettings['new-job']
                            == ICL_TM_NOTIFICATION_NONE): ?>checked="checked"<?php endif; ?> />
                            <label
                                for="icl_tm_notify_translator_dont"><?php _e('No notification', 'wpml-translation-management'); ?></label>
                        </li>
                    </ul>
                    <?php do_action('WPML_translator_notification'); ?>
                </div>
            </div>
            <div class="wpml-section" id="translation-notifications- sec-2">
                <div class="wpml-section-header">

                    <h4><?php _e('Notify translator manager when job is completed:', 'wpml-translation-management'); ?></h4>
                </div>
                <div class="wpml-section-content">
                    <ul>
                        <li>
                            <input name="notification[completed]" type="radio" id="icl_tm_notify_complete1"
                                   value="<?php echo ICL_TM_NOTIFICATION_IMMEDIATELY ?>"
                                   <?php if ($nsettings['completed']
                                   == ICL_TM_NOTIFICATION_IMMEDIATELY): ?>checked="checked"<?php endif; ?> />
                            <label
                                for="icl_tm_notify_complete1"><?php _e('Notify immediately', 'wpml-translation-management'); ?></label>
                        </li>
                        <li>
                            <input name="notification[completed]" type="radio" id="icl_tm_notify_complete0"
                                   value="<?php echo ICL_TM_NOTIFICATION_NONE ?>"
                                   <?php if ($nsettings['completed'] == ICL_TM_NOTIFICATION_NONE): ?>checked="checked"<?php endif; ?> />
                            <label
                                for="icl_tm_notify_complete0"><?php _e('No notification', 'wpml-translation-management'); ?></label>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="wpml-section" id="translation-notifications-sec-3">
                <div class="wpml-section-header">

                    <h4><?php _e('Notify translator when removed from job:', 'wpml-translation-management'); ?></h4>
                </div>
                <div class="wpml-section-content">
                    <ul>
                        <li>
                            <input name="notification[resigned]" type="radio" id="icl_tm_notify_resigned1"
                                   value="<?php echo ICL_TM_NOTIFICATION_IMMEDIATELY ?>"
                                   <?php if ($nsettings['resigned']
                                   == ICL_TM_NOTIFICATION_IMMEDIATELY): ?>checked="checked"<?php endif; ?> />
                            <label
                                for="icl_tm_notify_resigned1"><?php _e('Notify immediately', 'wpml-translation-management'); ?></label>
                        </li>
                        <li>
                            <input name="notification[resigned]" type="radio" id="icl_tm_notify_resigned0"
                                   value="<?php echo ICL_TM_NOTIFICATION_NONE ?>"
                                   <?php if ($nsettings['resigned'] == ICL_TM_NOTIFICATION_NONE): ?>checked="checked"<?php endif; ?> />
                            <label
                                for="icl_tm_notify_resigned0"><?php _e('No notification', 'wpml-translation-management'); ?></label>
                        </li>
                    </ul>
                </div>

                <p class="submit">
                    <input type="submit" class="button-primary"
                           value="<?php _e('Save', 'wpml-translation-management') ?>"/>
                </p>
            </div>

        </form>

    <?php
    }

    private function render_items()
    {
        if ($this->tab_items) {
            ?>
            <p class="icl-translation-management-menu">
                <?php
                $this->build_tabs();
                ?>
            </p>
            <div class="icl_tm_wrap">
                <?php
                $this->build_content();
                ?>
            </div>
        <?php
        }
    }

	private function build_dashboard_filter_arguments() {
		global $sitepress, $iclTranslationManagement;

		$this->current_language = $sitepress->get_current_language();
		$this->source_language  = TranslationProxy_Basket::get_source_language();

		if ( isset( $_SESSION[ 'translation_dashboard_filter' ] ) ) {
			$this->translation_filter = $_SESSION[ 'translation_dashboard_filter' ];
		}
		if ( $this->source_language || ! isset( $this->translation_filter[ 'from_lang' ] ) ) {
			if ( $this->source_language ) {
				$this->translation_filter[ 'from_lang' ] = $this->source_language;
			} else {
				$this->translation_filter[ 'from_lang' ] = isset( $_GET[ 'lang' ] ) ? $_GET[ 'lang' ] : $this->current_language;
			}
		}

        if (!isset($this->translation_filter['to_lang'])) {
            $this->translation_filter['to_lang'] = isset($_GET['to_lang']) ? $_GET['to_lang'] : '';
        }

        if ($this->translation_filter['to_lang'] == $this->translation_filter['from_lang']) {
            $this->translation_filter['to_lang'] = false;
        }

        if (!isset($this->translation_filter['tstatus'])) {
            $this->translation_filter['tstatus'] = isset($_GET['tstatus']) ? $_GET['tstatus'] : -1; // -1 == All documents
        }

        if (!isset($this->translation_filter['sort_by']) || !$this->translation_filter['sort_by']) {
            $this->translation_filter['sort_by'] = 'date';
        }
        if (!isset($this->translation_filter['sort_order']) || !$this->translation_filter['sort_order']) {
            $this->translation_filter['sort_order'] = 'DESC';
        }
        $sort_order_next = $this->translation_filter['sort_order'] == 'ASC' ? 'DESC' : 'ASC';
        $this->dashboard_title_sort_link = 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=dashboard&icl_tm_action=sort&sort_by=title&sort_order=' . $sort_order_next;
        $this->dashboard_date_sort_link = 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=dashboard&icl_tm_action=sort&sort_by=date&sort_order=' . $sort_order_next;
                
        $this->post_statuses = array(
            'publish' => __('Published', 'wpml-translation-management'),
            'draft' => __('Draft', 'wpml-translation-management'),
            'pending' => __('Pending Review', 'wpml-translation-management'),
            'future' => __('Scheduled', 'wpml-translation-management'),
            'private' => __('Private', 'wpml-translation-management')
        );
        $this->post_statuses = apply_filters('wpml_tm_dashboard_post_statuses', $this->post_statuses);
    
        // Get the document types that we can translate
        $this->post_types = $sitepress->get_translatable_documents();
        $this->post_types = apply_filters('wpml_tm_dashboard_translatable_types', $this->post_types);
        $this->build_external_types();
       
        $this->translation_filter['limit_no'] = isset($_GET['show_all']) && $_GET['show_all'] ? 10000 : ICL_TM_DOCS_PER_PAGE;
        if (!isset($this->translation_filter['parent_type'])) {
            $this->translation_filter['parent_type'] = 'any';
        }

        $this->selected_languages = array();
        if (!empty($iclTranslationManagement->dashboard_select)) {
            $this->selected_posts = $iclTranslationManagement->dashboard_select['post'];
            $this->selected_languages = $iclTranslationManagement->dashboard_select['translate_to'];
        }
        if (isset($this->translation_filter['icl_selected_posts'])) {
            parse_str($this->translation_filter['icl_selected_posts'], $this->selected_posts);
        }

        $this->filter_post_status = isset($this->translation_filter['status']) ? $this->translation_filter['status'] : false;

        if ( isset( $_GET[ 'type' ] ) ) {
            $this->translation_filter[ 'type' ] = $_GET[ 'type' ];
        }
        $this->filter_translation_type = isset( $this->translation_filter[ 'type' ] ) ? $this->translation_filter[ 'type' ] : false;
    }

    private function build_content_dashboard_documents_sorting_link( $url, $label, $filter_argument ) {
        $caption = $label;
        if ( $this->translation_filter[ 'sort_by' ] == $filter_argument ) {
            $caption .= '&nbsp;';
            $caption .= $this->translation_filter[ 'sort_order' ] == 'ASC' ? '&uarr;' : '&darr;';
        }
        ?>
        <a href="<?php echo $url ?>">
            <?php echo $caption ?>
        </a>
    <?php
    }

    private function build_content_dashboard_documents_head_footer_cells() {
        global $sitepress;
        ?>
        <tr>
            <th scope="col" class="manage-column column-cb check-column">
                <?php
                $check_all_checked = checked( true, isset( $_GET[ 'post_id' ] ), false );
                ?>
                <input type="checkbox" <?php echo $check_all_checked; ?>/>
            </th>
            <th scope="col" class="manage-column column-title">
                <?php
                $dashboard_title_sort_caption = __( 'Title', 'wpml-translation-management' );
                $this->build_content_dashboard_documents_sorting_link( $this->dashboard_title_sort_link, $dashboard_title_sort_caption, 'p.post_title' );
                ?>
            </th>
            <th scope="col" class="manage-column column-date">
                <?php
                $dashboard_date_sort_label = __( 'Date', 'wpml-translation-management' );
                $this->build_content_dashboard_documents_sorting_link( $this->dashboard_date_sort_link, $dashboard_date_sort_label, 'p.post_date' );
                ?>
            </th>
            <th scope="col" class="manage-column column-note">
                <img title="<?php _e( 'Note for translators', 'wpml-translation-management' ) ?>" src="<?php echo WPML_TM_URL ?>/res/img/notes.png" alt="note" width="16" height="16"/>
            </th>
            <th scope="col" class="manage-column column-date">
                <?php echo __( 'Type', 'wpml-translation-management' ) ?>
            </th>
            <th scope="col" class="manage-column column-date">
                <?php echo __( 'Status', 'wpml-translation-management' ) ?>
            </th>

	        <?php
	        $lang_count = count($sitepress->get_active_languages());
	        if ($lang_count > 10) {
		        $lang_col_width = "30%";
	        } else {
		        $lang_col_width = $lang_count*17 . "px";
	        }
	        ?>

	        <th scope="col" class="manage-column column-active-languages" style="width: <?php echo $lang_col_width; ?>">
            <?php
            if ( $this->translation_filter[ 'to_lang' ] ) {
                ?>

                    <img src="<?php echo $sitepress->get_flag_url( $this->translation_filter[ 'to_lang' ] ) ?>" width="16" height="12" alt="<?php echo $this->translation_filter[ 'to_lang' ] ?>"/>
            <?php
            } else {
                foreach ( $sitepress->get_active_languages() as $lang ) {
                    if ( $lang[ 'code' ] == $this->translation_filter[ 'from_lang' ] ) {
                        continue;
                    }
                    ?>
                        <img src="<?php echo $sitepress->get_flag_url( $lang[ 'code' ] ) ?>" width="16" height="12" alt="<?php echo $lang[ 'code' ] ?>"/>
                <?php
                }
            }
            ?>
	        </th>
        </tr>
    <?php
    }

    private function build_content_dashboard_documents()
    {
        ?>

        <input type="hidden" name="icl_tm_action" value="add_jobs"/>
        <input type="hidden" name="translate_from" value="<?php echo $this->translation_filter['from_lang'] ?>"/>
        <table class="widefat fixed" id="icl-tm-translation-dashboard" cellspacing="0">
            <thead>
            <?php $this->build_content_dashboard_documents_head_footer_cells(); ?>
            </thead>
            <tfoot>
            <?php $this->build_content_dashboard_documents_head_footer_cells(); ?>
            </tfoot>
            <tbody>
            <?php
            $this->build_content_dashboard_documents_body();
            ?>
            </tbody>
        </table>
        <?php
	    global $wp_query;
        if (isset($_GET['show_all']) && $_GET['show_all'] && count($this->documents) > ICL_TM_DOCS_PER_PAGE) {
            echo '<a style="width: auto; float:right" href="' . admin_url('admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=dashboard') . '">' . sprintf(__('Show %d documents per page', 'wpml-translation-management'), ICL_TM_DOCS_PER_PAGE) . '</a>';
        }
        // pagination
        $paged = (int)filter_input(INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT);
        $paged = $paged ? $paged : 1;
        $page_links = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total' => $wp_query->max_num_pages,
            'current' => $paged,
            'add_args' => isset($this->translation_filter) ? $this->translation_filter : array()
        ));

        ?>

	    <div class="tablenav">
		    <div style="float:left;margin-top:4px;">
			    <strong><?php echo __( 'Word count estimate:', 'wpml-translation-management' ) ?></strong>
			    <?php printf( __( '%s words', 'wpml-translation-management' ), '<span id="icl-tm-estimated-words-count">0</span>' ) ?>
			    <span id="icl-tm-doc-wrap" style="display: none">
	                <?php printf( __( 'in %s document(s)', 'wpml-translation-management' ), '<span id="icl-tm-sel-doc-count">0</span>' ); ?>
                </span>
		    </div>
		    <?php
		    if ( $page_links ) {
			    ?>
			    <div class="tablenav-pages">
				    <?php
				    if ( ! isset( $_GET[ 'show_all' ] ) && $wp_query->found_posts > ICL_TM_DOCS_PER_PAGE ) {
					    echo '<a style="width: auto; font-weight:normal" href="' . admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=dashboard&show_all=1' ) . '">' . __( 'show all', 'wpml-translation-management' ) . '</a>';
				    }
				    $page_links_parts[ 'from' ]  = number_format_i18n( ( $paged - 1 ) * $wp_query->query_vars[ 'posts_per_page' ] + 1 );
				    $page_links_parts[ 'to' ]    = number_format_i18n( min( $paged * $wp_query->query_vars[ 'posts_per_page' ], $wp_query->found_posts ) );
				    $page_links_parts[ 'total' ] = number_format_i18n( $wp_query->found_posts );
				    ?>
				    <span class="displaying-num">
	                <?php
	                echo sprintf( __( 'Displaying %s&#8211;%s of %s', 'wpml-translation-management' ) . '</span>', $page_links_parts[ 'from' ], $page_links_parts[ 'to' ], $page_links_parts[ 'total' ] );
	                ?>
	                </span>
				    <?php
				    echo $page_links;
				    ?>
			    </div>
		    <?php
		    }
		    ?>
	    </div>
        <?php // pagination - end
    }

    public function build_content_dashboard_fetch_translations_box()
    {
        if (TranslationProxy::is_current_service_active_and_authenticated()) {
            ?>
            <div id="icl_tm_pickup_wrap">
                <div class="icl_cyan_box">
                    <div id="icl_tm_pickup_wrap_errors" class="icl_tm_pickup_wrap" style="display:none"><p></p></div>
                    <div id="icl_tm_pickup_wrap_completed" class="icl_tm_pickup_wrap" style="display:none"><p></p></div>
                    <div id="icl_tm_pickup_wrap_cancelled" class="icl_tm_pickup_wrap" style="display:none"><p></p></div>
	                <div id="icl_tm_pickup_wrap_error_submitting" class="icl_tm_pickup_wrap" style="display:none"><p></p></div>
	                <p id="icl_pickup_nof_jobs"></p>
	                <p><input type="button" class="button-secondary" value="" id="icl_tm_get_translations"/></p>
	                <p id="icl_pickup_last_pickup"></p>
                </div>
            </div>
	        <br clear="all"/>
            <?php
            wp_nonce_field('icl_pickup_translations_nonce', '_icl_nonce_pickup_t');
            wp_nonce_field('icl_populate_translations_pickup_box_nonce', '_icl_nonce_populate_t');
        }
    }

	private function build_external_types() {
		$this->post_types = apply_filters( 'wpml_get_translatable_types', $this->post_types );
		foreach ( $this->post_types as $id => $type_info ) {
			if ( isset( $type_info->prefix ) ) {
				// this is an external type returned by wpml_get_translatable_types
				$new_type                        = new stdClass();
				$new_type->labels                = new stdClass();
				$new_type->labels->singular_name = isset( $type_info->labels->singular_name ) ? $type_info->labels->singular_name : $type_info->label;
				$new_type->labels->name          = isset( $type_info->labels->name ) ? $type_info->labels->name : $type_info->label;
				$new_type->prefix                = $type_info->prefix;
				$new_type->external_type         = 1;

				$this->post_types[ $id ] = $new_type;
			}
		}
	}

    private function build_content_dashboard_filter() {
        require WPML_TM_PATH . '/menu/dashboard/wpml-tm-dashboard-display-filter.class.php';
        $dashboard_filter = new WPML_TM_Dashboard_Display_Filter(
            $this->active_languages,
            $this->source_language,
            $this->translation_filter,
            $this->post_types,
            $this->post_statuses
        );
        $dashboard_filter->display();
    }

    private function build_content_dashboard_results() {
        ?>
        <form method="post" id="icl_tm_dashboard_form">
            <?php
            // #############################################
            // Display the items for translation in a table.
            // #############################################

            $this->build_content_dashboard_documents();
            $this->build_content_dashboard_documents_options();

            ?>
        </form>

        <br/>
    <?php
    }
    private function is_translation_locked() {
			global $WPML_Translation_Management;
			$result = $WPML_Translation_Management->service_activation_incomplete();

			return $result;
    }

    private function build_content_dashboard_documents_options() {
        $translate_checked = 'checked="checked"';
        $duplicate_checked = '';
        $do_nothing_checked = '';
        if( $this->is_translation_locked() ) {
            $translate_checked = 'disabled="disabled"';
            $do_nothing_checked = 'checked="checked"';
        }

        ?>
        <table class="widefat fixed" cellspacing="0" style="width:100%">
            <thead>
            <tr>
                <th><?php _e( 'Translation options', 'wpml-translation-management' ) ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <table id="icl_tm_languages" class="widefat" style="width:auto;border: none;">
                        <thead>
                        <tr>
                            <td><strong style="font-size: large"><?php _e('All Languages', 'wpml-translation-management'); ?></strong></td>
                            <td>
                                <input type="radio" id="translate-all" value="1" name="radio-action-all" <?php echo $translate_checked;?> /> <?php _e( 'Translate',
                                                                   'wpml-translation-management' ) ?>
                            </td>
                            <td>
                                <input type="radio" id="duplicate-all" value="2" name="radio-action-all" <?php echo $duplicate_checked ?> /> <?php _e( 'Duplicate content',
                                                                   'wpml-translation-management' ) ?>
                            </td>
                            <td>
                                <input type="radio" id="update-none" value="0" name="radio-action-all" <?php echo $do_nothing_checked; ?> /> <?php _e( 'Do nothing', 'wpml-translation-management' ) ?>
                            </td>
                        </tr>
                        <tr class="blank_row">
                            <td colspan="3" style="height:6px!important;"></td>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $this->active_languages as $lang ): ?>
                            <?php
                            if ( $lang[ 'code' ] == $this->translation_filter[ 'from_lang' ] ) {
                                continue;
                            }
                            $radio_prefix_html = '<input type="radio" name="tr_action[' . $lang[ 'code' ] . ']" ';
                            ?>
                            <tr>
                                <td><strong><?php echo $lang[ 'display_name' ] ?></strong></td>
                                <td>
                                    <label>
                                        <?php echo $radio_prefix_html ?> value="1" <?php echo $translate_checked ?>/>
                                        <?php _e( 'Translate', 'wpml-translation-management' ); ?>
                                    </label>
                                </td>
                                <td>
                                    <label>
                                        <?php echo $radio_prefix_html ?> value="2" <?php echo $duplicate_checked ?>/>
                                        <?php _e( 'Duplicate content', 'wpml-translation-management' ); ?>
                                    </label>
                                </td>
                                <td>
                                    <label>
                                        <?php echo $radio_prefix_html ?> value="0" <?php echo $do_nothing_checked ?>/>
                                        <?php _e( 'Do nothing', 'wpml-translation-management' ); ?>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <br/>

                    <input name="iclnonce" type="hidden" value="<?php echo wp_create_nonce( 'pro-translation-icl' ) ?>"/>
                    <?php
                    $tm_jobs_submit_disabled = disabled(empty( $this->selected_languages ) && empty( $this->selected_posts ), true, false);
                    $tm_jobs_submit_caption = __( 'Add to translation basket', 'wpml-translation-management' );
                    ?>
                    <input id="icl_tm_jobs_submit" class="button-primary" type="submit" value="<?php echo $tm_jobs_submit_caption; ?>" <?php echo $tm_jobs_submit_disabled; ?> />

                    <div id="icl_dup_ovr_warn" class="icl_dup_ovr_warn" style="display:none;">
                        <?php
                        $dup_message = '<p>';
                        $dup_message .= __( 'Any existing content (translations) will be overwritten when creating duplicates.', 'wpml-translation-management' );
                        $dup_message .= '</p>';
                        $dup_message .= '<p>';
                        $dup_message .= __( "When duplicating content, please first duplicate parent pages to maintain the site's hierarchy.", 'wpml-translation-management' );
                        $dup_message .= '</p>';

                        ICL_AdminNotifier::display_instant_message( $dup_message, 'error' );

                        ?>
                    </div>
                    <div style="width: 45%; margin: auto; position: relative; top: -30px;">
                        <?php
                        ICL_AdminNotifier::display_messages( 'translation-dashboard-under-translation-options' );
                        ICL_AdminNotifier::remove_message( 'items_added_to_basket' );
                        ?>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    <?php
    }

    private function build_content_dashboard_remote_translations_controls() {
        // shows only when translation polling is on and there are translations in progress
        $this->build_content_dashboard_fetch_translations_box();

        $active_service = icl_do_not_promote() ? false : TranslationProxy::get_current_service();
        $service_dashboard_info = TranslationProxy::get_service_dashboard_info();
        if ( $active_service && $service_dashboard_info ) {
            ?>
            <div class="icl_cyan_box">
                <h3><?php echo $active_service->name . ' ' . __( 'account status',
                                                                 'wpml-translation-management' ) ?></h3>
                <?php echo $service_dashboard_info; ?>
            </div>
            <?php
        }
    }

    private function build_dashboard_documents() {
        global $wpdb;
        $types          = $this->translatable_types;
        $filtered_types = array();
        foreach ( $types as $type ) {
            $filtered_types[ ] = 'post_' . $type->name;
        }
        $ext_types = $this->post_types;
        foreach ( $ext_types as $key => $type ) {
            if ( isset( $type->prefix ) ) {
                $filtered_types[ ] = $type->prefix . '_' . $key;
            }
        }
        $tm_dashboard    = new WPML_TM_Dashboard( $this->active_languages, $filtered_types, $wpdb );
        $this->documents = $tm_dashboard->get_documents( $this->translation_filter );
    }

    private function build_dashboard_data() {
        $this->build_dashboard_filter_arguments();
        $this->build_dashboard_documents();
    }

    private function build_content_dashboard_documents_body() {
        global $sitepress;
        $this->current_document_words_count = 0;
        if ( !$this->documents ) {
            $colspan = 6 + ( $this->translation_filter[ 'to_lang' ]
                    ? 1
                    : count(
                          $sitepress->get_active_languages()
                      ) - 1 );
            ?>
            <tr>
                <td scope="col" colspan="<?php echo $colspan; ?>" align="center">
                    <?php _e( 'No documents found', 'wpml-translation-management' ) ?>
                </td>
            </tr>
        <?php
        } else {
            $this->odd_row = false;
            wp_nonce_field( 'save_translator_note_nonce', '_icl_nonce_stn_' );
            require WPML_TM_PATH . '/menu/dashboard/wpml-tm-dashboard-document-row.class.php';
            $odd_row          = true;
            $active_languages = $this->translation_filter[ 'to_lang' ]
                ? array( $this->translation_filter[ 'to_lang' ] => $this->active_languages[ $this->translation_filter[ 'to_lang' ] ] )
                : $this->active_languages;
            foreach ( $this->documents as $doc ) {
                $selected = is_array( $this->selected_posts ) && in_array( $doc->ID, $this->selected_posts );
                $doc_row  = new WPML_TM_Dashboard_Document_Row(
                    $doc,
                    $this->translation_filter,
                    $this->post_types,
                    $this->post_statuses,
                    $active_languages,
                    $selected
                );
                $doc_row->display( $odd_row );
                $odd_row = !$odd_row;
            }
        }
    }
	
	private function build_tp_com_log_item( ) {
		
        if ( isset( $_GET[ 'sm' ] ) && $_GET[ 'sm' ] == 'com-log' ) {
			$this->tab_items['com-log']['caption'] = __('Communication Log', 'wpml-translation-management');
			$this->tab_items['com-log']['callback'] = array($this, 'build_tp_com_log');
		}
	}
	
	private function build_tp_com_log( ) {
		require_once WPML_TM_PATH . '/inc/translation-proxy/translationproxy-com-log.class.php';
		
		if ( isset( $_POST[ 'tp-com-clear-log' ] ) ) {
			TranslationProxy_Com_Log::clear_log( );
		}

		if ( isset( $_POST[ 'tp-com-disable-log' ] ) ) {
			TranslationProxy_Com_Log::set_logging_state( false );
		}

		if ( isset( $_POST[ 'tp-com-enable-log' ] ) ) {
			TranslationProxy_Com_Log::set_logging_state( true );
		}
		
		$action_url = esc_attr( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=' . $_GET[ 'sm' ] );
		$com_log = TranslationProxy_Com_Log::get_log( );

		?>

		<form method="post" id="tp-com-log-form" name="tp-com-log-form" action="<?php echo $action_url; ?>">
		
			<?php if ( TranslationProxy_Com_Log::is_logging_enabled( ) ): ?>
			
				<?php _e("This is a log of the communication between your site and the translation system. It doesn't include any private information and allows WPML support to help with problems related to sending content to translation.", 'wpml-translation-management'); ?>
	
				<br />
				<br />
				<?php if ( $com_log != '' ): ?>
					<textarea wrap="off" readonly="readonly" rows="16" style="font-size:10px; width:100%"><?php echo $com_log; ?></textarea>
					<br />
					<br />
					<input class="button-secondary" type="submit" name="tp-com-clear-log" value="<?php _e( 'Clear log', 'wpml-translation-management' ); ?>">
				<?php else: ?>
					<strong><?php _e('The communication log is empty.', 'wpml-translation-management'); ?></strong>
					<br />
					<br />
				<?php endif; ?>
				
				<input class="button-secondary" type="submit" name="tp-com-disable-log" value="<?php _e( 'Disable logging', 'wpml-translation-management' ); ?>">
				
			<?php else: ?>
				<?php _e("Communication logging is currently disabled. To allow WPML support to help you with issues related to sending content to translation, you need to enable the communication logging.", 'wpml-translation-management'); ?>
	
				<br />
				<br />
				<input class="button-secondary" type="submit" name="tp-com-enable-log" value="<?php _e( 'Enable logging', 'wpml-translation-management' ); ?>">
			
			<?php endif; ?>

		</form>		
		<?php
		
	}
}
