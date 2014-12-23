<?php

if(!class_exists('ICL_Language_Switcher')) {
	include ICL_PLUGIN_PATH . '/inc/widgets/icl-language-switcher.class.php';
}

class SitePressLanguageSwitcher {

    var $widget_preview = false;
    var $widget_css_defaults;

    var $footer_css_defaults;

    var $color_schemes = array(
            'Gray' => array(
                'font-current-normal' => '#222222',
                'font-current-hover' => '#000000',
                'background-current-normal' => '#eeeeee',
                'background-current-hover' => '#eeeeee',
                'font-other-normal' => '#222222',
                'font-other-hover' => '#000000',
                'background-other-normal' => '#e5e5e5',
                'background-other-hover' => '#eeeeee',
                'border' => '#cdcdcd',
                'background' => '#e5e5e5'
            ),
            'White' => array(
                'font-current-normal' => '#444444',
                'font-current-hover' => '#000000',
                'background-current-normal' => '#ffffff',
                'background-current-hover' => '#eeeeee',
                'font-other-normal' => '#444444',
                'font-other-hover' => '#000000',
                'background-other-normal' => '#ffffff',
                'background-other-hover' => '#eeeeee',
                'border' => '#cdcdcd',
                'background' => '#ffffff'
            ),
            'Blue' => array(
                'font-current-normal' => '#ffffff',
                'font-current-hover' => '#000000',
                'background-current-normal' => '#95bedd',
                'background-current-hover' => '#95bedd',
                'font-other-normal' => '#000000',
                'font-other-hover' => '#ffffff',
                'background-other-normal' => '#cbddeb',
                'background-other-hover' => '#95bedd',
                'border' => '#0099cc',
                'background' => '#cbddeb'
            )
    );

    function __construct(){

        $this->widget_css_defaults = $this->color_schemes['White'];
        $this->footer_css_defaults = $this->color_schemes['White'];

        add_action('plugins_loaded',array($this,'init'));
    }

    function init(){

        global $sitepress_settings;
        $this->settings = $sitepress_settings;
        if (!empty($this->settings['icl_lang_sel_footer'])){
            add_action('wp_head', array($this, 'language_selector_footer_style'),19);
            add_action('wp_footer', array($this, 'language_selector_footer'),19);
        }
        if (is_admin()) {
            add_action('icl_language_switcher_options',array($this,'admin'),1);
        } else if (!empty($this->settings['icl_post_availability'])) {
            if(function_exists('icl_register_string')){
                icl_register_string('WPML', 'Text for alternative languages for posts', $this->settings['icl_post_availability_text']);
            }
            add_filter('the_content', array($this, 'post_availability'), 100);
        }

        // the language selector widget
	    add_action( 'widgets_init', array( $this, 'language_selector_widget_init' ) );

        if(is_admin() && isset($_GET['page']) && $_GET['page'] == ICL_PLUGIN_FOLDER . '/menu/languages.php'){
            add_action('admin_head', 'icl_lang_sel_nav_css', 1, 1, true);
            add_action('admin_head', array($this, 'custom_language_switcher_style'));
        }
        if(!is_admin()){
            add_action('wp_head', array($this, 'custom_language_switcher_style'));
        }

        if(!empty($sitepress_settings['display_ls_in_menu']) && ( !function_exists( 'wpml_home_url_ls_hide_check' ) || !wpml_home_url_ls_hide_check() ) ){
            add_filter('wp_nav_menu_items', array($this, 'wp_nav_menu_items_filter'), 10, 2);
            add_filter('wp_page_menu', array($this, 'wp_page_menu_filter'), 10, 2);
        }

    }

    function language_selector_widget_init(){
	   	register_widget( 'ICL_Language_Switcher' );
			add_action('template_redirect','icl_lang_sel_nav_ob_start', 0);
			add_action('wp_head','icl_lang_sel_nav_ob_end');
    }

    function set_widget(){
        global $sitepress, $sitepress_settings;
        if (isset($_POST['icl_widget_update'])){
            $sitepress_settings['icl_widget_title_show'] = (isset($_POST['icl_widget_title_show'])) ? 1 : 0;
            $sitepress->save_settings($sitepress_settings);
        }
        echo '<input type="hidden" name="icl_widget_update" value="1">';
        echo '<label><input type="checkbox" name="icl_widget_title_show" value="1"';
        if ($sitepress_settings['icl_widget_title_show']) echo ' checked="checked"';
        echo '>&nbsp;' . __('Display \'Languages\' as the widget\'s title', 'sitepress') . '</label><br>';
    }

    function post_availability($content){
        $out = '';
        if(is_singular()){
            $languages = icl_get_languages('skip_missing=true');
            if(1 < count($languages)){
                //$out .= $this->settings['post_available_before'] ? $this->settings['post_available_before'] : '';
				$langs = array();
                foreach($languages as $l){
                    if(!$l['active']) $langs[] = '<a href="'. apply_filters('WPML_filter_link', $l['url'], $l) .'">'.$l['translated_name'].'</a>';
                }
                $out .= join(', ', $langs);
                //$out .= $this->settings['post_available_after'] ? $this->settings['post_available_after'] : '';
                if(!function_exists('icl_t')){
                    function icl_t($c, $n, $str){return $str; }
                }
                $out = '<p class="icl_post_in_other_langs">' . sprintf(icl_t('WPML', 'Text for alternative languages for posts', $this->settings['icl_post_availability_text']), $out) . '</p>';
            }
        }

        $out = apply_filters('icl_post_alternative_languages', $out);

        if ($this->settings['icl_post_availability_position'] == 'above'){
            $content = $out . $content;
        }else{
            $content = $content . $out;
        }

        return $content;

    }

    function language_selector_footer_style(){

        $add = false;
        foreach($this->footer_css_defaults as $key=>$d){
            if (isset($this->settings['icl_lang_sel_footer_config'][$key]) && $this->settings['icl_lang_sel_footer_config'][$key] != $d){
                $add = true;
                break;
            }
        }
        if($add){
            echo "\n<style type=\"text/css\">";
            foreach($this->settings['icl_lang_sel_footer_config'] as $k=>$v){
                switch($k){
                    case 'font-current-normal':
                        //if($v != $this->color_schemes[$k])
                            echo '#lang_sel_footer a, #lang_sel_footer a.lang_sel_sel, #lang_sel_footer a.lang_sel_sel:visited{color:'.$v.';}';
                        break;
                    case 'font-current-hover':
                        //if($v != $this->color_schemes[$k])
                            echo '#lang_sel_footer a:hover, #lang_sel_footer a.lang_sel_sel:hover{color:'.$v.';}';
                        break;
                    case 'background-current-normal':
                        //if($v != $this->color_schemes[$k])
                            echo '#lang_sel_footer a.lang_sel_sel, #lang_sel_footer a.lang_sel_sel:visited{background-color:'.$v.';}';
                        break;
                    case 'background-current-hover':
                        //if($v != $this->color_schemes[$k])
                            echo '#lang_sel_footer a.lang_sel_sel:hover{background-color:'.$v.';}';
                        break;
                    case 'font-other-normal':
                        //if($v != $this->color_schemes[$k])
                            echo '#lang_sel_footer ul a, #lang_sel_footer ul a:visited{color:'.$v.';}';
                        break;
                    case 'font-other-hover':
                        //if($v != $this->color_schemes[$k])
                            echo '#lang_sel_footer ul a:hover{color:'.$v.';}';
                        break;
                    case 'background-other-normal':
                        //if($v != $this->color_schemes[$k])
                            echo '#lang_sel_footer ul a, #lang_sel_footer ul a:visited{background-color:'.$v.';}';
                        break;
                    case 'background-other-hover':
                        //if($v != $this->color_schemes[$k])
                            echo '#lang_sel_footer ul a:hover{background-color:'.$v.';}';
                        break;
                    case 'border':
                        //if($v != $this->color_schemes[$k])
                            echo '#lang_sel_footer{border-color:'.$v.';}';
                        break;
                    case 'background':
                        //if($v != $this->color_schemes[$k])
                            echo '#lang_sel_footer{background-color:'.$v.';}';
                        break;
                }
            }
            echo "</style>\n";
        }
    }

	static function get_language_selector_footer() {
		global $sitepress;

		$language_selector_footer = '';
		$languages = array();

		if ( !function_exists( 'wpml_home_url_ls_hide_check' ) || !wpml_home_url_ls_hide_check() ) {
			$languages = $sitepress->footer_preview ? icl_get_languages() : $sitepress->get_ls_languages();
		}

		if ( ! empty( $languages ) ) {
			$language_selector_footer = '
							<div id="lang_sel_footer">
									<ul>
									';
			foreach ( $languages as $lang ) {

				$alt_title_lang = $sitepress->get_setting( 'icl_lso_display_lang' ) ? esc_attr( $lang[ 'translated_name' ] ) : esc_attr( $lang[ 'native_name' ] );

				$language_selector_footer .= '    <li>';
				$language_selector_footer .= '<a href="' . apply_filters( 'WPML_filter_link', $lang[ 'url' ], $lang ) . '"';
				if ( $lang[ 'active' ] ) {
					$language_selector_footer .= ' class="lang_sel_sel"';
				}
				$language_selector_footer .= '>';
				if ( $sitepress->get_setting( 'icl_lso_flags' ) || $sitepress->footer_preview ) {
					$language_selector_footer .= '<img src="' . $lang[ 'country_flag_url' ] . '" alt="' . $alt_title_lang . '" class="iclflag" title="' . $alt_title_lang . '" ';
				}
				if ( ! $sitepress->get_setting( 'icl_lso_flags' ) && $sitepress->footer_preview ) {
					$language_selector_footer .= ' style="display:none;"';
				}
				if ( $sitepress->get_setting( 'icl_lso_flags' ) || $sitepress->footer_preview ) {
					$language_selector_footer .= ' />&nbsp;';
				}

				if ( $sitepress->footer_preview ) {
					$lang_native = $lang[ 'native_name' ];
					if ( $sitepress->get_setting( 'icl_lso_native_lang' ) ) {
						$lang_native_hidden = false;
					} else {
						$lang_native_hidden = true;
					}
					$lang_translated = $lang[ 'translated_name' ];
					if ( $sitepress->get_setting( 'icl_lso_display_lang' ) ) {
						$lang_translated_hidden = false;
					} else {
						$lang_translated_hidden = true;
					}
				} else {
					if ( $sitepress->get_setting( 'icl_lso_native_lang' ) ) {
						$lang_native = $lang[ 'native_name' ];
					} else {
						$lang_native = false;
					}
					if ( $sitepress->get_setting( 'icl_lso_display_lang' ) ) {
						$lang_translated = $lang[ 'translated_name' ];
					} else {
						$lang_translated = false;
					}
					$lang_native_hidden     = false;
					$lang_translated_hidden = false;
				}
				$language_selector_footer .= icl_disp_language( $lang_native, $lang_translated, $lang_native_hidden, $lang_translated_hidden );

				$language_selector_footer .= '</a>';
				$language_selector_footer .= '</li>
									';
			}
			$language_selector_footer .= '</ul>
							</div>';

		}
		return $language_selector_footer;
	}

	function language_selector_footer() {
		echo self::get_language_selector_footer();
	}

	function admin() {
		global $sitepress;
		foreach ( $this->color_schemes as $key => $val ): ?>
			<?php foreach ( $this->widget_css_defaults as $k => $v ): ?>
				<input type="hidden" id="icl_lang_sel_config_alt_<?php echo $key ?>_<?php echo $k ?>" value="<?php echo $this->color_schemes[ $key ][ $k ] ?>"/>
			<?php endforeach; ?>
		<?php endforeach; ?>

		<?php if ( !defined( 'ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS' ) || !ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS ): ?>
			<p>
				<a href="#icl_lang_preview_config_wrapper" class="js-toggle-colors-edit">
					<?php _e( 'Edit the language switcher widget colors', 'sitepress' ) ?>
					<i class="icon-caret-down js-arrow-toggle"></i>
				</a>
			</p>
			<div id="icl_lang_preview_config_wrapper" class="hidden">
				<table id="icl_lang_preview_config" style="width:auto;">
					<thead>
					<tr>
						<th>&nbsp;</th>
						<th><?php _e( 'Normal', 'sitepress' ) ?></th>
						<th><?php _e( 'Hover', 'sitepress' ) ?></th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td><?php _e( 'Current language font color', 'sitepress' ) ?></td>
						<td><input type="text" size="7" id="icl-lang-sel-config-font-current-normal" name="icl_lang_sel_config[font-current-normal]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_config' ][ 'font-current-normal' ] ) ) {
								echo $this->settings[ 'icl_lang_sel_config' ][ 'font-current-normal' ];
							} else {
								echo $this->widget_css_defaults[ 'font-current-normal' ];
							}
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-font-current-normal-picker" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-config-font-current-normal';cp.show('icl-font-current-normal-picker');return false;"/></td>
						<td><input type="text" size="7" id="icl-lang-sel-config-font-current-hover" name="icl_lang_sel_config[font-current-hover]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_config' ][ 'font-current-hover' ] ) ) {
								echo $this->settings[ 'icl_lang_sel_config' ][ 'font-current-hover' ];
							} else {
								echo $this->widget_css_defaults[ 'font-current-hover' ];
							}
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-font-current-hover-picker" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-config-font-current-hover';cp.show('icl-font-current-hover-picker');return false;"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Current language background color', 'sitepress' ) ?></td>
						<td><input type="text" size="7" id="icl-lang-sel-config-background-current-normal" name="icl_lang_sel_config[background-current-normal]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_config' ][ 'background-current-normal' ] ) ) {
								echo $this->settings[ 'icl_lang_sel_config' ][ 'background-current-normal' ];
							} else {
								echo $this->widget_css_defaults[ 'background-current-normal' ];
							}
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-background-current-normal-picker" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-config-background-current-normal';cp.show('icl-background-current-normal-picker');return false;"/></td>
						<td><input type="text" size="7" id="icl-lang-sel-config-background-current-hover" name="icl_lang_sel_config[background-current-hover]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_config' ][ 'background-current-hover' ] ) ) {
								echo $this->settings[ 'icl_lang_sel_config' ][ 'background-current-hover' ];
							} else {
								echo $this->widget_css_defaults[ 'background-current-hover' ];
							}
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-background-current-hover-picker" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-config-background-current-hover';cp.show('icl-background-current-hover-picker');return false;"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Other languages font color', 'sitepress' ) ?></td>
						<td><input type="text" size="7" id="icl-lang-sel-config-font-other-normal" name="icl_lang_sel_config[font-other-normal]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_config' ][ 'font-other-normal' ] ) ) {
								echo $this->settings[ 'icl_lang_sel_config' ][ 'font-other-normal' ];
							} else {
								echo $this->widget_css_defaults[ 'font-other-normal' ];
							}
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-font-other-normal-picker" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-config-font-other-normal';cp.show('icl-font-other-normal-picker');return false;"/></td>
						<td><input type="text" size="7" id="icl-lang-sel-config-font-other-hover" name="icl_lang_sel_config[font-other-hover]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_config' ][ 'font-other-hover' ] ) ) {
								echo $this->settings[ 'icl_lang_sel_config' ][ 'font-other-hover' ];
							} else {
								echo $this->widget_css_defaults[ 'font-other-hover' ];
							}
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-font-other-hover-picker" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-config-font-other-hover';cp.show('icl-font-other-hover-picker');return false;"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Other languages background color', 'sitepress' ) ?></td>
						<td><input type="text" size="7" id="icl-lang-sel-config-background-other-normal" name="icl_lang_sel_config[background-other-normal]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_config' ][ 'background-other-normal' ] ) ) {
								echo $this->settings[ 'icl_lang_sel_config' ][ 'background-other-normal' ];
							} else {
								echo $this->widget_css_defaults[ 'background-other-normal' ];
							}
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-background-other-normal-picker" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-config-background-other-normal';cp.show('icl-background-other-normal-picker');return false;"/></td>
						<td><input type="text" size="7" id="icl-lang-sel-config-background-other-hover" name="icl_lang_sel_config[background-other-hover]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_config' ][ 'background-other-hover' ] ) ) {
								echo $this->settings[ 'icl_lang_sel_config' ][ 'background-other-hover' ];
							} else {
								echo $this->widget_css_defaults[ 'background-other-hover' ];
							}
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-background-other-hover-picker" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-config-background-other-hover';cp.show('icl-background-other-hover-picker');return false;"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Border', 'sitepress' ) ?></td>
						<td><input type="text" size="7" id="icl-lang-sel-config-border" name="icl_lang_sel_config[border]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_config' ][ 'border' ] ) ) {
								echo $this->settings[ 'icl_lang_sel_config' ][ 'border' ];
							} else {
								echo $this->widget_css_defaults[ 'border' ];
							}
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-border-picker" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-config-border';cp.show('icl-border-picker');return false;"/></td>
						<td>&nbsp;</td>
					</tr>
					</tbody>

				</table>

				<?php _e( 'Presets:', 'sitepress' ) ?>
				<select id="icl_lang_sel_color_scheme" name="icl_lang_sel_color_scheme">
					<option value=""><?php _e( '--select--', 'sitepress' ) ?>&nbsp;</option>
					<option value="Gray"><?php _e( 'Gray', 'sitepress' ) ?>&nbsp;</option>
					<option value="White"><?php _e( 'White', 'sitepress' ) ?>&nbsp;</option>
					<option value="Blue"><?php _e( 'Blue', 'sitepress' ) ?>&nbsp;</option>
				</select>
				<span style="display:none"><?php _e( "Are you sure? The customization you may have made will be overridden once you click 'Apply'", 'sitepress' ) ?></span>
			</div>
		<?php else: ?>
			<em><?php printf( __( "%s is defined in your theme. The language switcher can only be customized using the theme's CSS.", 'sitepress' ), 'ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS' ) ?></em>
		<?php endif; ?>
		<div class="wpml-section-content-inner">

		<h4><?php _e( 'Footer language switcher style', 'sitepress' ) ?></h4>
		<p>
			<label>
				<input type="checkbox" name="icl_lang_sel_footer" value="1" <?php if (!empty( $this->settings[ 'icl_lang_sel_footer' ] )): ?>checked="checked"<?php endif ?> />
				<?php _e( 'Show language switcher in footer', 'sitepress' ) ?>
			</label>
		</p>
		<div id="icl_lang_sel_footer_preview_wrap" class="language-selector-preview language-selector-preview-footer">
			<div id="icl_lang_sel_footer_preview">
				<p><strong><?php _e( 'Footer language switcher preview', 'sitepress' ) ?></strong></p>
				<?php
				$sitepress->footer_preview = true;
				$this->language_selector_footer();
				?>
			</div>
		</div>
		<?php
		if ( !defined( 'ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS' ) || !ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS ) {
			?>

			<?php foreach ( $this->color_schemes as $key => $val ): ?>
				<?php foreach ( $this->footer_css_defaults as $k => $v ): ?>
					<input type="hidden" id="icl_lang_sel_footer_config_alt_<?php echo $key ?>_<?php echo $k ?>" value="<?php echo $this->color_schemes[ $key ][ $k ] ?>"/>
				<?php endforeach; ?>
			<?php endforeach; ?>

			<p>
				<a href="#icl_lang_preview_config_footer_editor_wrapper" id="icl_lang_sel_footer_preview_link" class="js-toggle-colors-edit">
					<?php _e( 'Edit the footer language switcher colors', 'sitepress' ) ?>
					<i class="icon-caret-down js-arrow-toggle"></i>
				</a>
			</p>
			<div class="hidden" id="icl_lang_preview_config_footer_editor_wrapper">
				<table id="icl_lang_preview_config_footer" style="width:auto;">
					<thead>
					<tr>
						<th>&nbsp;</th>
						<th><?php _e( 'Normal', 'sitepress' ) ?></th>
						<th><?php _e( 'Hover', 'sitepress' ) ?></th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td><?php _e( 'Current language font color', 'sitepress' ) ?></td>
						<td><input type="text" size="7" id="icl-lang-sel-footer-config-font-current-normal" name="icl_lang_sel_footer_config[font-current-normal]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_footer_config' ][ 'font-current-normal' ] ) )
								echo $this->settings[ 'icl_lang_sel_footer_config' ][ 'font-current-normal' ]; else
								echo $this->footer_css_defaults[ 'font-current-normal' ];
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-font-current-normal-picker-footer" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-footer-config-font-current-normal';cp.show('icl-font-current-normal-picker-footer');return false;"/></td>
						<td><input type="text" size="7" id="icl-lang-sel-footer-config-font-current-hover" name="icl_lang_sel_footer_config[font-current-hover]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_footer_config' ][ 'font-current-hover' ] ) )
								echo $this->settings[ 'icl_lang_sel_footer_config' ][ 'font-current-hover' ]; else
								echo $this->footer_css_defaults[ 'font-current-hover' ];
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-font-current-hover-picker-footer" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-footer-config-font-current-hover';cp.show('icl-font-current-hover-picker-footer');return false;"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Current language background color', 'sitepress' ) ?></td>
						<td><input type="text" size="7" id="icl-lang-sel-footer-config-background-current-normal" name="icl_lang_sel_footer_config[background-current-normal]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_footer_config' ][ 'background-current-normal' ] ) )
								echo $this->settings[ 'icl_lang_sel_footer_config' ][ 'background-current-normal' ]; else
								echo $this->footer_css_defaults[ 'background-current-normal' ];
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-background-current-normal-picker-footer" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-footer-config-background-current-normal';cp.show('icl-background-current-normal-picker-footer');return false;"/></td>
						<td><input type="text" size="7" id="icl-lang-sel-footer-config-background-current-hover" name="icl_lang_sel_footer_config[background-current-hover]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_footer_config' ][ 'background-current-hover' ] ) )
								echo $this->settings[ 'icl_lang_sel_footer_config' ][ 'background-current-hover' ]; else
								echo $this->footer_css_defaults[ 'background-current-hover' ];
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-background-current-hover-picker-footer" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-footer-config-background-current-hover';cp.show('icl-background-current-hover-picker-footer');return false;"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Other languages font color', 'sitepress' ) ?></td>
						<td><input type="text" size="7" id="icl-lang-sel-footer-config-font-other-normal" name="icl_lang_sel_footer_config[font-other-normal]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_footer_config' ][ 'font-other-normal' ] ) )
								echo $this->settings[ 'icl_lang_sel_footer_config' ][ 'font-other-normal' ]; else
								echo $this->footer_css_defaults[ 'font-other-normal' ];
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-font-other-normal-picker-footer" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-footer-config-font-other-normal';cp.show('icl-font-other-normal-picker-footer');return false;"/></td>
						<td><input type="text" size="7" id="icl-lang-sel-footer-config-font-other-hover" name="icl_lang_sel_footer_config[font-other-hover]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_footer_config' ][ 'font-other-hover' ] ) )
								echo $this->settings[ 'icl_lang_sel_footer_config' ][ 'font-other-hover' ]; else
								echo $this->footer_css_defaults[ 'font-other-hover' ];
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-font-other-hover-picker-footer" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-footer-config-font-other-hover';cp.show('icl-font-other-hover-picker-footer');return false;"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Other languages background color', 'sitepress' ) ?></td>
						<td><input type="text" size="7" id="icl-lang-sel-footer-config-background-other-normal" name="icl_lang_sel_footer_config[background-other-normal]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_footer_config' ][ 'background-other-normal' ] ) )
								echo $this->settings[ 'icl_lang_sel_footer_config' ][ 'background-other-normal' ]; else
								echo $this->footer_css_defaults[ 'background-other-normal' ];
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-background-other-normal-picker-footer" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-footer-config-background-other-normal';cp.show('icl-background-other-normal-picker-footer');return false;"/></td>
						<td><input type="text" size="7" id="icl-lang-sel-footer-config-background-other-hover" name="icl_lang_sel_footer_config[background-other-hover]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_footer_config' ][ 'background-other-hover' ] ) )
								echo $this->settings[ 'icl_lang_sel_footer_config' ][ 'background-other-hover' ]; else
								echo $this->footer_css_defaults[ 'background-other-hover' ];
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-background-other-hover-picker-footer" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-footer-config-background-other-hover';cp.show('icl-background-other-hover-picker-footer');return false;"/></td>
					</tr>

					<tr>
						<td><?php _e( 'Background', 'sitepress' ) ?></td>
						<td><input type="text" size="7" id="icl-lang-sel-footer-config-background" name="icl_lang_sel_footer_config[background]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_footer_config' ][ 'background' ] ) )
								echo $this->settings[ 'icl_lang_sel_footer_config' ][ 'background' ]; else
								echo $this->footer_css_defaults[ 'background' ];
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-background-picker-footer" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-footer-config-background';cp.show('icl-background-picker-footer');return false;"/></td>
						<td>&nbsp;</td>
					</tr>

					<tr>
						<td><?php _e( 'Border', 'sitepress' ) ?></td>
						<td><input type="text" size="7" id="icl-lang-sel-footer-config-border" name="icl_lang_sel_footer_config[border]" value="<?php
							if ( isset( $this->settings[ 'icl_lang_sel_footer_config' ][ 'border' ] ) )
								echo $this->settings[ 'icl_lang_sel_footer_config' ][ 'border' ]; else
								echo $this->footer_css_defaults[ 'border' ];
							?>"/><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/icon_color_picker.png" id="icl-border-picker-footer" alt="" border="0" style="vertical-align:bottom;cursor:pointer;" class="pick-show"
									  onclick="icl_cp_target='icl-lang-sel-footer-config-border';cp.show('icl-border-picker-footer');return false;"/></td>
						<td>&nbsp;</td>
					</tr>
					</tbody>

				</table>

				<?php _e( 'Presets:', 'sitepress' ) ?>
				<select id="icl_lang_sel_footer_color_scheme" name="icl_lang_sel_footer_color_scheme">
					<option value=""><?php _e( '--select--', 'sitepress' ) ?>&nbsp;</option>
					<option value="Gray"><?php _e( 'Gray', 'sitepress' ) ?>&nbsp;</option>
					<option value="White"><?php _e( 'White', 'sitepress' ) ?>&nbsp;</option>
					<option value="Blue"><?php _e( 'Blue', 'sitepress' ) ?>&nbsp;</option>
				</select>
				<span style="display:none"><?php _e( "Are you sure? The customization you may have made will be overridden once you click 'Apply'", 'sitepress' ) ?></span>
			</div> <!-- #icl_lang_preview_config_footer_editor_wrapper -->

			</div> <!-- .wpml-section-content-inner -->

			<div class="wpml-section-content-inner">
				<h4><?php _e( 'Show post translation links', 'sitepress' ); ?></h4>
				<ul>
					<li>
						<label>
							<input type="checkbox" name="icl_post_availability" id="js-post-availability" data-target=".js-post-availability-settings" value="1"
								   <?php if (!empty( $this->settings[ 'icl_post_availability' ] )): ?>checked<?php endif ?> />
							<?php _e( 'Yes', 'sitepress' ); ?>
						</label>
					</li>
					<li class="js-post-availability-settings <?php if ( empty( $this->settings[ 'icl_post_availability' ] ) ): ?>hidden<?php endif ?>">
						<label>
							<?php _e( 'Position', 'sitepress' ); ?>&nbsp;
							<select name="icl_post_availability_position">
								<option value="above"<?php if ( isset( $this->settings[ 'icl_post_availability_position' ] ) &&
																$this->settings[ 'icl_post_availability_position' ] == 'above'
								): ?> selected="selected"<?php endif ?>><?php _e( 'Above post', 'sitepress' ); ?>&nbsp;&nbsp;</option>
								<option value="below"<?php if ( empty( $this->settings[ 'icl_post_availability_position' ] ) || $this->settings[ 'icl_post_availability_position' ] == 'bellow' ||
																$this->settings[ 'icl_post_availability_position' ] == 'below'
								):?> selected="selected"<?php endif ?>><?php _e( 'Below post', 'sitepress' ); ?>&nbsp;&nbsp;</option>
							</select>
						</label>
					</li>
					<li class="js-post-availability-settings <?php if ( empty( $this->settings[ 'icl_post_availability' ] ) ): ?>hidden<?php endif ?>">
						<label>
							<?php _e( 'Text for alternative languages for posts', 'sitepress' ); ?>: <input type="text" name="icl_post_availability_text" value="<?php
							if ( isset( $this->settings[ 'icl_post_availability_text' ] ) )
								echo esc_attr( $this->settings[ 'icl_post_availability_text' ] ); else _e( 'This post is also available in: %s', 'sitepress' ); ?>" size="40"/>
						</label>
					</li>
				</ul>
			</div> <!-- .wpml-section-content-inner -->

			<div class="wpml-section-content-inner">
				<h4><label for="icl_additional_css"><?php _e( 'Additional CSS (optional)', 'sitepress' ); ?></label></h4>

				<p>
					<?php
					if ( ! empty( $this->settings[ 'icl_additional_css' ] ) ) {
						$icl_additional_css = trim( $this->settings[ 'icl_additional_css' ] );
					} else {
						$icl_additional_css = '';
					}
					?>
					<textarea id="icl_additional_css" name="icl_additional_css" rows="4" class="large-text"><?php echo $icl_additional_css; ?></textarea>
				</p>
			</div> <!-- .wpml-section-content-inner -->

		<?php
		}
	}

    function widget_list(){
        global $sitepress, $w_this_lang, $icl_language_switcher_preview;
        if($w_this_lang['code']=='all'){
            $main_language['native_name'] = __('All languages', 'sitepress');
        }
        $active_languages = icl_get_languages();
        if(empty($active_languages)) return; ?>

            <div id="lang_sel_list"<?php if(empty($this->settings['icl_lang_sel_type']) || $this->settings['icl_lang_sel_type'] == 'dropdown') echo ' style="display:none;"';?> class="lang_sel_list_<?php echo $this->settings['icl_lang_sel_orientation'] ?>">
            <ul>
                <?php
								foreach($active_languages as $lang){

									$language_url = apply_filters( 'WPML_filter_link', $lang[ 'url' ], $lang );
									if ( $lang[ 'language_code' ] == $sitepress->get_current_language() ) {
										$language_selected = ' class="lang_sel_sel"';
									} else {
										$language_selected = ' class="lang_sel_other"';
									}
									$language_flag_title = $this->settings[ 'icl_lso_display_lang' ] ? esc_attr( $lang[ 'translated_name' ] ) : esc_attr( $lang[ 'native_name' ] );


									$lang_native_hidden     = false;
									$lang_translated_hidden = true;
									if ( $icl_language_switcher_preview ) {
										$lang_native = $lang[ 'native_name' ];
										if ( $this->settings[ 'icl_lso_native_lang' ] ) {
											$lang_native_hidden = false;
										} else {
											$lang_native_hidden = true;
										}
										$lang_translated = $lang[ 'translated_name' ];
										if ( $this->settings[ 'icl_lso_display_lang' ] ) {
											$lang_translated_hidden = false;
										} else {
											$lang_translated_hidden = true;
										}
									} else {
										if ( $this->settings[ 'icl_lso_native_lang' ] ) {
											$lang_native = $lang[ 'native_name' ];
										} else {
											$lang_native = false;
										}
										if ( $this->settings[ 'icl_lso_display_lang' ] ) {
											$lang_translated = $lang[ 'translated_name' ];
										} else {
											$lang_translated = false;
										}
									}

									$country_flag_url = $lang[ 'country_flag_url' ];

									?>
                <li class="icl-<?php echo $lang['language_code']; ?>">
									<a href="<?php echo $language_url; ?>"<?php echo $language_selected; ?>>
										<?php
										if ( $this->settings[ 'icl_lso_flags' ] || $icl_language_switcher_preview ) {
											?>
											<img <?php if (!$this->settings[ 'icl_lso_flags' ]): ?>style="display:none"<?php endif ?> class="iclflag" src="<?php echo $country_flag_url; ?>" alt="<?php echo $lang[ 'language_code' ] ?>"
													 title="<?php echo $language_flag_title; ?>"/>&nbsp;
										<?php
										}
										echo @icl_disp_language( $lang_native, $lang_translated, $lang_native_hidden, $lang_translated_hidden );
										?>
									</a>
                </li>
                <?php
			}
?>
            </ul>
</div>
<?php
    }

    function custom_language_switcher_style(){
        if(defined('ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS') && ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS){
            return;
        }
        $add = false;
        foreach($this->widget_css_defaults as $key=>$d){
            if(isset($this->settings['icl_lang_sel_config'][$key]) && $this->settings['icl_lang_sel_config'][$key] != $d){
                $add = true;
                break;
            }
        }
        if($add){
            $list = ($this->settings['icl_lang_sel_type'] == 'list') ? true : false;
            echo "\n<style type=\"text/css\">";
            foreach($this->settings['icl_lang_sel_config'] as $k=>$v){
                switch($k){
                    case 'font-current-normal':
                        //if($v != $this->widget_css_defaults[$k])
                        if ($list)
                            echo '#lang_sel_list a.lang_sel_sel, #lang_sel_list a.lang_sel_sel:visited{color:'.$v.';}';
                        else
                            echo '#lang_sel a, #lang_sel a.lang_sel_sel{color:'.$v.';}';
                        break;
                    case 'font-current-hover':
                        //if($v != $this->widget_css_defaults[$k])
                        if ($list)
                            echo '#lang_sel_list a:hover, #lang_sel_list a.lang_sel_sel:hover{color:'.$v.';}';
                        else
                            echo '#lang_sel a:hover, #lang_sel a.lang_sel_sel:hover{color:'.$v.';}';
                        break;
                    case 'background-current-normal':
                        //if($v != $this->widget_css_defaults[$k])
                        if ($list)
                            echo '#lang_sel_list a.lang_sel_sel, #lang_sel_list a.lang_sel_sel:visited{background-color:'.$v.';}';
                        else
                            echo '#lang_sel a.lang_sel_sel, #lang_sel a.lang_sel_sel:visited{background-color:'.$v.';}';
                        break;
                    case 'background-current-hover':
                        //if($v != $this->widget_css_defaults[$k])
                        if ($list)
                            echo '#lang_sel_list a.lang_sel_sel:hover{background-color:'.$v.';}';
                        else
                            echo '#lang_sel a.lang_sel_sel:hover{background-color:'.$v.';}';
                        break;
                    case 'font-other-normal':
                        //if($v != $this->widget_css_defaults[$k])
                        if ($list)
                            echo '#lang_sel_list ul a.lang_sel_other, #lang_sel_list ul a.lang_sel_other:visited{color:'.$v.';}';
                        else
                            echo '#lang_sel li ul a, #lang_sel li ul a:visited{color:'.$v.';}';
                        break;
                    case 'font-other-hover':
                        //if($v != $this->widget_css_defaults[$k])
                        if ($list)
                            echo '#lang_sel_list ul a.lang_sel_other:hover{color:'.$v.';}';
                        else
                            echo '#lang_sel li ul a:hover{color:'.$v.';}';
                        break;
                    case 'background-other-normal':
                        //if($v != $this->widget_css_defaults[$k])
                        if ($list)
                             echo '#lang_sel_list ul a.lang_sel_other, #lang_sel li ul a:link, #lang_sel_list ul a.lang_sel_other:visited{background-color:'.$v.';}';
                        else
                            echo '#lang_sel li ul a, #lang_sel li ul a:link, #lang_sel li ul a:visited{background-color:'.$v.';}';
                        break;
                    case 'background-other-hover':
                        //if($v != $this->widget_css_defaults[$k])
                        if ($list)
                             echo '#lang_sel_list ul a.lang_sel_other:hover{background-color:'.$v.';}';
                        else
                            echo '#lang_sel li ul a:hover{background-color:'.$v.';}';
                        break;
                    case 'border':
                        //if($v != $this->widget_css_defaults[$k])
                        if ($list)
                             echo '#lang_sel_list a, #lang_sel_list a:visited{border-color:'.$v.';} #lang_sel_list  ul{border-top:1px solid '.$v.';}';
                        else
                            echo '#lang_sel a, #lang_sel a:visited{border-color:'.$v.';} #lang_sel ul ul{border-top:1px solid '.$v.';}';
                        break;

                }
            }
            echo "</style>\n";
        }
        if (isset($this->settings['icl_additional_css']) && !empty($this->settings['icl_additional_css'])) {
          echo "\r\n<style type=\"text/css\">";
          //echo implode("\r\n", $this->settings['icl_additional_css']);
          echo $this->settings['icl_additional_css'];
          echo "\r\n</style>";
        }
    }

    function wp_page_menu_filter($items, $args) {
        $obj_args = new stdClass();
        foreach ($args as $key => $value)
        {
            $obj_args->$key = $value;
        }

        $items = str_replace("</ul></div>", "", $items);

        $items = apply_filters( 'wp_nav_menu_items', $items, $obj_args );

        $items .= "</ul></div>";

        return $items;
    }
    function wp_nav_menu_items_filter($items, $args){
        global $sitepress_settings, $sitepress;

		$current_language = $sitepress->get_current_language();
		$default_language = $sitepress->get_default_language();
        // menu can be passed as integer or object
        if(isset($args->menu->term_id)) $args->menu = $args->menu->term_id;

		$abs_menu_id = icl_object_id($args->menu, 'nav_menu', false, $default_language );
	    $settings_menu_id = icl_object_id( $sitepress_settings[ 'menu_for_ls' ], 'nav_menu', false, $default_language );

	    if ( $abs_menu_id == $settings_menu_id  || false === $abs_menu_id ) {

            $languages = $sitepress->get_ls_languages();

            $items .= '<li class="menu-item menu-item-language menu-item-language-current">';
            if(isset($args->before)){
                $items .= $args->before;
            }
            $items .= '<a href="#" onclick="return false">';
            if(isset($args->link_before)){
                $items .= $args->link_before;
            }

			$language_name = '';
			if ( $sitepress_settings[ 'icl_lso_native_lang' ] ) {
				$language_name .= $languages[ $current_language ][ 'native_name' ];
			}
			if ( $sitepress_settings[ 'icl_lso_display_lang' ] && $sitepress_settings[ 'icl_lso_native_lang' ] ) {
				$language_name .= ' (';
			}
			if ( $sitepress_settings[ 'icl_lso_display_lang' ] ) {
				$language_name .= $languages[ $current_language ][ 'translated_name' ];
			}
			if ( $sitepress_settings[ 'icl_lso_display_lang' ] && $sitepress_settings[ 'icl_lso_native_lang' ] ) {
				$language_name .= ')';
			}

			$alt_title_lang = esc_attr($language_name);

            if( $sitepress_settings['icl_lso_flags'] ){
				$items .= '<img class="iclflag" src="' . $languages[ $current_language ][ 'country_flag_url' ] . '" width="18" height="12" alt="' . $alt_title_lang . '" title="' . esc_attr( $language_name ) . '" />';
			}

			$items .= $language_name;

			if(isset($args->link_after)){
                $items .= $args->link_after;
            }
            $items .= '</a>';
            if(isset($args->after)){
                $items .= $args->after;
            }

            unset($languages[ $current_language ]);
			$sub_items = false;
			$menu_is_vertical = !isset($sitepress_settings['icl_lang_sel_orientation']) || $sitepress_settings['icl_lang_sel_orientation'] == 'vertical';
            if(!empty($languages)){
                foreach($languages as $lang){
                    $sub_items .= '<li class="menu-item menu-item-language menu-item-language-current">';
                    $sub_items .= '<a href="'.$lang['url'].'">';

					$language_name = '';
					if ( $sitepress_settings[ 'icl_lso_native_lang' ] ) {
						$language_name .= $lang[ 'native_name' ];
					}
					if ( $sitepress_settings[ 'icl_lso_display_lang' ] && $sitepress_settings[ 'icl_lso_native_lang' ] ) {
						$language_name .= ' (';
					}
					if ( $sitepress_settings[ 'icl_lso_display_lang' ] ) {
						$language_name .= $lang[ 'translated_name' ];
					}
					if ( $sitepress_settings[ 'icl_lso_display_lang' ] && $sitepress_settings[ 'icl_lso_native_lang' ] ) {
						$language_name .= ')';
					 }
                    $alt_title_lang = esc_attr($language_name);

                    if( $sitepress_settings['icl_lso_flags'] ){
                        $sub_items .= '<img class="iclflag" src="'.$lang['country_flag_url'].'" width="18" height="12" alt="'.$alt_title_lang.'" title="' . $alt_title_lang . '" />';
                    }
					$sub_items .= $language_name;

                    $sub_items .= '</a>';
                    $sub_items .= '</li>';

                }
				if( $sub_items && $menu_is_vertical ) {
					$sub_items = '<ul class="sub-menu submenu-languages">' . $sub_items . '</ul>';
				}
            }
			if( $menu_is_vertical ) {
				$items .= $sub_items;
            	$items .= '</li>';
			} else {
				$items .= '</li>';
				$items .= $sub_items;
			}

        }

        return $items;
    }

} // end class





// language switcher functions
    function language_selector_widget($args){
        global $sitepress, $sitepress_settings;
        extract($args, EXTR_SKIP);
		/** @var $before_widget string */
		echo $before_widget;
        if ($sitepress_settings['icl_widget_title_show']) {
            echo $args['before_title'];
            _e('Languages','sitepress');
            echo $args['after_title'];
        }
        $sitepress->language_selector();
		/** @var $after_widget string */
		echo $after_widget;
    }

    function icl_lang_sel_nav_ob_start(){
           if(is_feed()) return;
        ob_start('icl_lang_sel_nav_prepend_css');
    }

    function icl_lang_sel_nav_ob_end(){
        $ob_handlers = ob_list_handlers();
        $active_handler = array_pop( $ob_handlers );
        if($active_handler == 'icl_lang_sel_nav_prepend_css'){
            ob_end_flush();
        }
    }

    function icl_lang_sel_nav_prepend_css($buf){
        if(defined('ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS') && ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS){
            return $buf;
           }
        return preg_replace('#</title>#i','</title>' . PHP_EOL . PHP_EOL . icl_lang_sel_nav_css(false), $buf);
    }

    function icl_lang_sel_nav_css($show = true){
        if(defined('ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS') && ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS){
            return '';
        }
        $link_tag = '<link rel="stylesheet" href="'. ICL_PLUGIN_URL . '/res/css/language-selector.css?v='.ICL_SITEPRESS_VERSION.'" type="text/css" media="all" />';
        if(!$show && (!isset($_GET['page']) || $_GET['page'] != ICL_PLUGIN_FOLDER . '/menu/languages.php')){
            return $link_tag;
        }else{
            echo $link_tag;
        }
		return $link_tag;
    }




global $icl_language_switcher;
$icl_language_switcher = new SitePressLanguageSwitcher;
