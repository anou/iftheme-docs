<?php
    /**
    * @package Lightbox Plus Colorbox
    * @subpackage actions.class.php
    * @internal 2013.01.16
    * @author Dan Zappone / 23Systems
    * @version 2.7
    * @$Id: actions.class.php 937945 2014-06-24 17:11:13Z dzappone $
    * @$URL: http://plugins.svn.wordpress.org/lightbox-plus/tags/2.7/classes/actions.class.php $
    */
    if (!class_exists('lbp_actions')) {
        class lbp_actions extends lbp_filters {
            /**
            * Tell WordPress to load jquery and jquery-colorbox-min.js in the front end and the admin panel
            */
            //            function lightboxPlusInitScripts() {
            //                global $g_lightbox_plus_url;
            //
            //            }
            function getPostID() {
                global $the_post_id;
                global $wp_query;
                $the_post_id = $wp_query->post->ID;
                echo $the_post_id;
            }

            /**
            * Add CSS styles to site page headers to display lightboxed images
            */
            function lightboxPlusAddHeader( ) {
                global $post;
                global $wp_version;
                global $g_lbp_version;
                global $g_lbp_colorbox_version;
                global $g_lightbox_plus_url;
                global $g_lbp_local_style_url;
                global $g_lbp_global_style_url;
                global $g_lbp_local_style_path;
                global $g_lbp_global_style_path;

                if ( !empty( $this->lightboxOptions ) ) { $lightboxPlusOptions = $this->getAdminOptions( $this->lightboxOptionsName ); }

                /**
                * Remove following line after a few versions or 2.6 is the prevelent version
                */
                $lightboxPlusOptions = $this->setMissingOptions($lightboxPlusOptions);

                if (!is_admin()) {
                    if (floatval($wp_version) < 3.1) {
                        wp_deregister_script('jquery'); 
                        wp_register_script('jquery', "http" . ($_SERVER['SERVER_PORT'] == 443 ? "s" : "") . "://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js", false, null);
                        wp_enqueue_script('jquery');
                    } else {
                        wp_enqueue_script('jquery','','','',true);
                    }
                    //wp_enqueue_script('jquery-colorbox', $g_lightbox_plus_url.'js/jquery.colorbox.'.$g_lbp_colorbox_version.'-min.js', array( 'jquery' ), $g_lbp_colorbox_version, $this->setLoadLocation($lightboxPlusOptions['load_location']));
                    wp_enqueue_script('jquery-colorbox', $g_lightbox_plus_url.'js/jquery.colorbox.'.$g_lbp_colorbox_version.'.js', array( 'jquery' ), $g_lbp_colorbox_version, $this->setLoadLocation($lightboxPlusOptions['load_location']));
                }

                if ($lightboxPlusOptions['use_custom_style']) {
                    $style_path_url = $g_lbp_global_style_url;
                    $style_path_dir = $g_lbp_global_style_path;
                } else {
                    $style_path_url = $g_lbp_local_style_url;
                    $style_path_dir = $g_lbp_local_style_path;
                }

                if ( $lightboxPlusOptions['disable_css'] ) {
                    echo "<!-- User set lightbox styles -->".PHP_EOL;
                } else {
                    wp_register_style('lightboxStyle', $style_path_url.'/'.$lightboxPlusOptions['lightboxplus_style'].'/colorbox.css','',$g_lbp_version,'screen');
                    wp_enqueue_style('lightboxStyle');
                    if (file_exists($style_path_dir.'/'.$lightboxPlusOptions['lightboxplus_style'].'/helper.js')) {
                        wp_enqueue_script('lbp-helper',$style_path_url.'/'.$lightboxPlusOptions['lightboxplus_style'].'/helper.js','',$g_lbp_version,$this->setLoadLocation($lightboxPlusOptions['load_location']));
                    }
                }

                return $post->ID;
            }

            /**
            * Add JavaScript (jQuery based) to page footer to activate LBP
            *
            * @echo string
            */
            function lightboxPlusColorbox( ) {
                global $g_lightbox_plus_url;
                global $g_lbp_version;
                global $g_lbp_colorbox_version;
                global $post;
                if ( !empty( $this->lightboxOptions ) ) {
                    $lightboxPlusOptions     = $this->getAdminOptions( $this->lightboxOptionsName );
                    /**
                    * Remove following line after a few versions or 2.6 is the prevelent version
                    */
                    $lightboxPlusOptions = $this->setMissingOptions($lightboxPlusOptions);

                    $lightboxPlusJavaScript  = "";
                    $lightboxPlusJavaScript .= '<!-- Lightbox Plus Colorbox v'.$g_lbp_version.'/'.$g_lbp_colorbox_version.' - 2013.01.24 - Message: '.$lightboxPlusOptions['lightboxplus_multi'].'-->'.PHP_EOL;
                    $lightboxPlusJavaScript .= '<script type="text/javascript">'.PHP_EOL;
                    $lightboxPlusJavaScript .= 'jQuery(document).ready(function($){'.PHP_EOL;
                    $lbpArrayPrimary = array();
                    if ( $lightboxPlusOptions['transition'] != 'elastic' ) { $lbpArrayPrimary[] = 'transition:"'.$lightboxPlusOptions['transition'].'"'; }
                    if ( $lightboxPlusOptions['speed'] != '300' ) { $lbpArrayPrimary[] = 'speed:'.$lightboxPlusOptions['speed']; }
                    if ( $lightboxPlusOptions['width'] != 'false' ) { $lbpArrayPrimary[] = 'width:'.$this->setValue( $lightboxPlusOptions['width'] ); }
                    if ( $lightboxPlusOptions['height'] != 'false'  ) { $lbpArrayPrimary[] = 'height:'.$this->setValue( $lightboxPlusOptions['height'] ); }
                    if ( $lightboxPlusOptions['inner_width'] != 'false'  ) { $lbpArrayPrimary[] = 'innerWidth:'.$this->setValue( $lightboxPlusOptions['inner_width'] ); }
                    if ( $lightboxPlusOptions['inner_height'] != 'false'  ) { $lbpArrayPrimary[] = 'innerHeight:'.$this->setValue( $lightboxPlusOptions['inner_height'] ); }
                    if ( $lightboxPlusOptions['initial_width'] != '600'  ) { $lbpArrayPrimary[] =  'initialWidth:'.$this->setValue( $lightboxPlusOptions['initial_width'] ); }
                    if ( $lightboxPlusOptions['initial_height'] != '450'  ) { $lbpArrayPrimary[] = 'initialHeight:'.$this->setValue( $lightboxPlusOptions['initial_height'] ); }
                    if ( $lightboxPlusOptions['max_width'] != 'false'  ) { $lbpArrayPrimary[] = 'maxWidth:'.$this->setValue( $lightboxPlusOptions['max_width'] ); }
                    if ( $lightboxPlusOptions['max_height'] != 'false'  ) { $lbpArrayPrimary[] = 'maxHeight:'.$this->setValue( $lightboxPlusOptions['max_height'] ); }
                    if ( $lightboxPlusOptions['resize'] != '1'  ) { $lbpArrayPrimary[] = 'scalePhotos:'.$this->setBoolean( $lightboxPlusOptions['resize'] ); }
                    if ( $lightboxPlusOptions['rel'] == 'nofollow'  )  { $lbpArrayPrimary[] = 'rel:'.$this->setValue( $lightboxPlusOptions['rel'] ); }
                    if ( $lightboxPlusOptions['opacity'] != '0.9' ) { $lbpArrayPrimary[] = 'opacity:'.$lightboxPlusOptions['opacity']; }
                    if ( $lightboxPlusOptions['preloading'] != '1' ) { $lbpArrayPrimary[] = 'preloading:'.$this->setBoolean( $lightboxPlusOptions['preloading'] ); }
                    if ( $lightboxPlusOptions['label_image'] != 'Image' && $lightboxPlusOptions['label_of'] != 'of' ) { $lbpArrayPrimary[] = 'current:"'.$lightboxPlusOptions['label_image'].' {current} '.$lightboxPlusOptions['label_of'].' {total}"'; }
                    if ( $lightboxPlusOptions['previous'] != 'previous' ) { $lbpArrayPrimary[] = 'previous:"'.$lightboxPlusOptions['previous'].'"'; }
                    if ( $lightboxPlusOptions['next'] != 'next' ) { $lbpArrayPrimary[] = 'next:"'.$lightboxPlusOptions['next'].'"'; }
                    if ( $lightboxPlusOptions['close'] != 'close' ) { $lbpArrayPrimary[] = 'close:"'.$lightboxPlusOptions['close'].'"'; }
                    if ( $lightboxPlusOptions['overlay_close'] != '1' ) { $lbpArrayPrimary[] = 'overlayClose:'.$this->setBoolean( $lightboxPlusOptions['overlay_close'] ); }
                    if ( $lightboxPlusOptions['loop'] != '1' ) { $lbpArrayPrimary[] = 'loop:'.$this->setBoolean( $lightboxPlusOptions['loop'] ); }
                    if ( $lightboxPlusOptions['slideshow'] == '1' ) { $lbpArrayPrimary[] = 'slideshow:'.$this->setBoolean( $lightboxPlusOptions['slideshow'] ); }
                    if ( $lightboxPlusOptions['slideshow'] == '1' ) {
                        if ( $lightboxPlusOptions['slideshow_auto'] != '1') { $lbpArrayPrimary[] = 'slideshowAuto:'.$this->setBoolean( $lightboxPlusOptions['slideshow_auto'] ); }
                        if ( $lightboxPlusOptions['slideshow_speed'] ) { $lbpArrayPrimary[] = 'slideshowSpeed:'.$lightboxPlusOptions['slideshow_speed']; }
                        if ( $lightboxPlusOptions['slideshow_start' ]) { $lbpArrayPrimary[] = 'slideshowStart:"'.$lightboxPlusOptions['slideshow_start'].'"'; }
                        if ( $lightboxPlusOptions['slideshow_stop'] ) { $lbpArrayPrimary[] =  'slideshowStop:"'.$lightboxPlusOptions['slideshow_stop'].'"'; }
                    }
                    if ( $lightboxPlusOptions['scrolling'] != '1' ) { $lbpArrayPrimary[] = 'scrolling:'.$this->setBoolean( $lightboxPlusOptions['scrolling'] ); }
                    if ( $lightboxPlusOptions['esc_key'] != '1' ) { $lbpArrayPrimary[] = 'escKey:'.$this->setBoolean( $lightboxPlusOptions['esc_key'] ); }
                    if ( $lightboxPlusOptions['arrow_key'] != '1' ) { $lbpArrayPrimary[] = 'arrowKey:'.$this->setBoolean( $lightboxPlusOptions['arrow_key'] ); }
                    if ( $lightboxPlusOptions['top'] != 'false' ) { $lbpArrayPrimary[] = 'top:'.$this->setValue( $lightboxPlusOptions['top'] ); }
                    if ( $lightboxPlusOptions['right'] != 'false'  ) { $lbpArrayPrimary[] = 'right:'.$this->setValue( $lightboxPlusOptions['right'] ); }
                    if ( $lightboxPlusOptions['bottom'] != 'false' ) { $lbpArrayPrimary[] = 'bottom:'.$this->setValue( $lightboxPlusOptions['bottom'] ); }
                    if ( $lightboxPlusOptions['left'] != 'false'  ) { $lbpArrayPrimary[] = 'left:'.$this->setValue( $lightboxPlusOptions['left'] ); }
                    if ( $lightboxPlusOptions['fixed'] == '1' ) { $lbpArrayPrimary[] = 'fixed:'.$this->setBoolean( $lightboxPlusOptions['fixed'] ); }
                    if (!is_admin()) {
                        $lbp_autoload = get_post_meta( $post->ID, '_lbp_autoload', true );
                        if ( $lbp_autoload == '1' ) { $lbpArrayPrimary[] = 'open:true'; }
                    }
                    switch ($lightboxPlusOptions['output_htmlv']) {
                        case 1:
                        $htmlv_prop = 'data-'.$lightboxPlusOptions['data_name'];
                        $lightboxPlusFnPrimary = '{rel:$(this).attr("'.$htmlv_prop.'"),'.implode(",", $lbpArrayPrimary).'}';
                        switch ( $lightboxPlusOptions['use_class_method'] ) {
                            case 1:
                                $lightboxPlusJavaScript .= '  $(".'.$lightboxPlusOptions['class_name'].'").each(function(){'.PHP_EOL;
                                $lightboxPlusJavaScript .= '    $(this).colorbox('.$lightboxPlusFnPrimary.');'.PHP_EOL;
                                $lightboxPlusJavaScript .= '  });'.PHP_EOL;
                                break;
                            default:
                                $lightboxPlusJavaScript .= '  $("a['.$htmlv_prop.'*=lightbox]").each(function(){'.PHP_EOL;
                                $lightboxPlusJavaScript .= '    $(this).colorbox('.$lightboxPlusFnPrimary.');'.PHP_EOL;
                                $lightboxPlusJavaScript .= '  });'.PHP_EOL;
                                break;
                        }                     
                        break;
                        default:
                        $lightboxPlusFnPrimary = '{'.implode(",", $lbpArrayPrimary).'}';
                        switch ( $lightboxPlusOptions['use_class_method'] ) {
                            case 1:
                                $lightboxPlusJavaScript .= '  $(".'.$lightboxPlusOptions['class_name'].'").colorbox('.$lightboxPlusFnPrimary.');'.PHP_EOL;
                                break;
                            default:
                            $lightboxPlusJavaScript .= '  $("a[rel*=lightbox]").colorbox('.$lightboxPlusFnPrimary.');'.PHP_EOL;
                            break;
                        }            
                        break;   
                    }
                    switch ( $lightboxPlusOptions['lightboxplus_multi'] ) {
                        case 1:
                            $lbpArraySecondary = array();
                            if ( $lightboxPlusOptions['transition_sec'] != 'elastic' ) { $lbpArraySecondary[] = 'transition:"'.$lightboxPlusOptions['transition_sec'].'"'; }
                            if ( $lightboxPlusOptions['speed_sec'] != '350' ) { $lbpArraySecondary[] = 'speed:'.$lightboxPlusOptions['speed_sec']; }
                            if ( $lightboxPlusOptions['width_sec'] && $lightboxPlusOptions['width_sec'] != 'false' ) { $lbpArraySecondary[] = 'width:'.$this->setValue( $lightboxPlusOptions['width_sec'] ); }
                            if ( $lightboxPlusOptions['height_sec'] && $lightboxPlusOptions['height_sec'] != 'false' ) { $lbpArraySecondary[] = 'height:'.$this->setValue( $lightboxPlusOptions['height_sec'] ); }
                            if ( $lightboxPlusOptions['inner_width_sec'] && $lightboxPlusOptions['inner_width_sec'] != 'false' ) { $lbpArraySecondary[] = 'innerWidth:'.$this->setValue( $lightboxPlusOptions['inner_width_sec'] ); }
                            if ( $lightboxPlusOptions['inner_height_sec'] && $lightboxPlusOptions['inner_height_sec'] != 'false' ) { $lbpArraySecondary[] = 'innerHeight:'.$this->setValue( $lightboxPlusOptions['inner_height_sec'] ); }
                            if ( $lightboxPlusOptions['initial_width_sec'] && $lightboxPlusOptions['initial_width_sec'] != '600' ) { $lbpArraySecondary[] =  'initialWidth:'.$this->setValue( $lightboxPlusOptions['initial_width_sec'] ); }
                            if ( $lightboxPlusOptions['initial_height_sec'] && $lightboxPlusOptions['initial_height_sec'] != '450' ) { $lbpArraySecondary[] = 'initialHeight:'.$this->setValue( $lightboxPlusOptions['initial_height_sec'] ); }
                            if ( $lightboxPlusOptions['max_width_sec'] && $lightboxPlusOptions['max_width_sec'] != 'false' ) { $lbpArraySecondary[] = 'maxWidth:'.$this->setValue( $lightboxPlusOptions['max_width_sec'] ); }
                            if ( $lightboxPlusOptions['max_height_sec'] && $lightboxPlusOptions['max_height_sec'] != 'false' ) { $lbpArraySecondary[] = 'maxHeight:'.$this->setValue( $lightboxPlusOptions['max_height_sec'] ); }
                            if ( $lightboxPlusOptions['resize_sec'] != '1' ) { $lbpArraySecondary[] = 'scalePhotos:'.$this->setBoolean( $lightboxPlusOptions['resize_sec'] ); }
                            if ( $lightboxPlusOptions['rel_sec'] == 'nofollow'  )  { $lbpArrayPrimary[] = 'rel:'.$this->setValue( $lightboxPlusOptions['rel'] ); }
                            if ( $lightboxPlusOptions['opacity_sec'] != '0.9' ) { $lbpArraySecondary[] = 'opacity:'.$lightboxPlusOptions['opacity_sec']; }
                            if ( $lightboxPlusOptions['preloading_sec'] != '1' ) { $lbpArraySecondary[] = 'preloading:'.$this->setBoolean( $lightboxPlusOptions['preloading_sec'] ); }
                            if ( $lightboxPlusOptions['label_image_sec'] != 'Image' && $lightboxPlusOptions['label_of_sec'] != 'of' ) { $lbpArraySecondary[] = 'current:"'.$lightboxPlusOptions['label_image_sec'].' {current} '.$lightboxPlusOptions['label_of_sec'].' {total}"'; }
                            if ( $lightboxPlusOptions['previous_sec'] != 'previous' ) { $lbpArraySecondary[] = 'previous:"'.$lightboxPlusOptions['previous_sec'].'"'; }
                            if ( $lightboxPlusOptions['next_sec'] != 'next' ) { $lbpArraySecondary[] = 'next:"'.$lightboxPlusOptions['next_sec'].'"'; }
                            if ( $lightboxPlusOptions['close_sec'] != 'close' ) { $lbpArraySecondary[] = 'close:"'.$lightboxPlusOptions['close_sec'].'"'; }
                            if ( $lightboxPlusOptions['overlay_close_sec'] != '1' ) { $lbpArraySecondary[] = 'overlayClose:'.$this->setBoolean( $lightboxPlusOptions['overlay_close_sec'] ); }
                            if ( $lightboxPlusOptions['loop_sec'] != '1' ) { $lbpArrayPrimary[] = 'loop:'.$this->setBoolean( $lightboxPlusOptions['loop_sec'] ); }
                            if ( $lightboxPlusOptions['slideshow_sec'] == '1' ) { $lbpArraySecondary[] = 'slideshow:'.$this->setBoolean( $lightboxPlusOptions['slideshow_sec'] ); }
                            if ( $lightboxPlusOptions['slideshow_sec']== '1' ) {
                                if ( $lightboxPlusOptions['slideshow_auto_sec']  != '1' ) { $lbpArraySecondary[] = 'slideshowAuto:'.$this->setBoolean( $lightboxPlusOptions['slideshow_auto_sec'] ); }
                                if ( $lightboxPlusOptions['slideshow_speed_sec'] ) { $lbpArraySecondary[] = 'slideshowSpeed:'.$lightboxPlusOptions['slideshow_speed_sec']; }
                                if ( $lightboxPlusOptions['slideshow_start_sec'] ) { $lbpArraySecondary[] = 'slideshowStart:"'.$lightboxPlusOptions['slideshow_start_sec'].'"'; }
                                if ( $lightboxPlusOptions['slideshow_stop_sec'] ) { $lbpArraySecondary[] =  'slideshowStop:"'.$lightboxPlusOptions['slideshow_stop_sec'].'"'; }
                            }
                            if ( $lightboxPlusOptions['iframe_sec'] != '0' ) { $lbpArraySecondary[] = 'iframe:'.$this->setBoolean( $lightboxPlusOptions['iframe_sec'] ); }
                            if ( $lightboxPlusOptions['scrolling_sec'] != '1' ) { $lbpArrayPrimary[] = 'scrolling:'.$this->setBoolean( $lightboxPlusOptions['scrolling_sec'] ); }
                            if ( $lightboxPlusOptions['esc_key_sec'] != '1' ) { $lbpArrayPrimary[] = 'escKey:'.$this->setBoolean( $lightboxPlusOptions['esc_key_sec'] ); }
                            if ( $lightboxPlusOptions['arrow_key_sec'] != '1' ) { $lbpArrayPrimary[] = 'arrowKey:'.$this->setBoolean( $lightboxPlusOptions['arrow_key_sec'] ); }
                            if ( $lightboxPlusOptions['top_sec'] != 'false' ) { $lbpArrayPrimary[] = 'top:'.$this->setValue( $lightboxPlusOptions['top_sec'] ); }
                            if ( $lightboxPlusOptions['right_sec'] != 'false'  ) { $lbpArrayPrimary[] = 'right:'.$this->setValue( $lightboxPlusOptions['right_sec'] ); }
                            if ( $lightboxPlusOptions['bottom_sec'] != 'false' ) { $lbpArrayPrimary[] = 'bottom:'.$this->setValue( $lightboxPlusOptions['bottom_sec'] ); }
                            if ( $lightboxPlusOptions['left_sec'] != 'false'  ) { $lbpArrayPrimary[] = 'left:'.$this->setValue( $lightboxPlusOptions['left_sec'] ); }
                            if ( $lightboxPlusOptions['fixed_sec'] == '1' ) { $lbpArrayPrimary[] = 'fixed:'.$this->setBoolean( $lightboxPlusOptions['fixed_sec'] ); }
                            //$lightboxPlusFnSecondary = '{'.implode(",", $lbpArraySecondary).'}';
                            switch ($lightboxPlusOptions['output_htmlv']) {
                                case 1:
                                    $htmlv_prop = 'data-'.$lightboxPlusOptions['data_name'];
                                    //$lightboxPlusFnSecondary = '{'.implode(",", $lbpArraySecondary).'}';
                                    $lightboxPlusFnSecondary = '{rel:$(this).attr("'.$htmlv_prop.'"),'.implode(",", $lbpArraySecondary).'}';
                                    $lightboxPlusJavaScript .= '  $(".'.$lightboxPlusOptions['class_name_sec'].'").each(function(){'.PHP_EOL;
                                    $lightboxPlusJavaScript .= '    $(this).colorbox('.$lightboxPlusFnSecondary.');'.PHP_EOL;
                                    $lightboxPlusJavaScript .= '  });'.PHP_EOL;
                                    break;
                                default:
                                    $lightboxPlusFnSecondary = '{'.implode(",", $lbpArraySecondary).'}';
                                    $lightboxPlusJavaScript .= '  $(".'.$lightboxPlusOptions['class_name_sec'].'").colorbox('.$lightboxPlusFnSecondary.');'.PHP_EOL;
                                    break;   
                            }
                            break;
                        default:
                            break;
                    }

                    if ($lightboxPlusOptions['use_inline'] && $lightboxPlusOptions['inline_num'] != '') {
                        $inline_links   = array();
                        $inline_hrefs   = array();
                        $inline_widths  = array();
                        $inline_heights = array();
                        for ($i = 1; $i <= $lightboxPlusOptions['inline_num']; $i++) {
                            $inline_links            = $lightboxPlusOptions['inline_links'];
                            $inline_hrefs            = $lightboxPlusOptions['inline_hrefs'];
                            $inline_transitions      = $lightboxPlusOptions['inline_transitions'];
                            $inline_speeds           = $lightboxPlusOptions['inline_speeds'];
                            $inline_widths           = $lightboxPlusOptions['inline_widths'];
                            $inline_heights          = $lightboxPlusOptions['inline_heights'];
                            $inline_inner_widths     = $lightboxPlusOptions['inline_inner_widths'];
                            $inline_inner_heights    = $lightboxPlusOptions['inline_inner_heights'];
                            $inline_max_widths       = $lightboxPlusOptions['inline_max_widths'];
                            $inline_max_heights      = $lightboxPlusOptions['inline_max_heights'];
                            $inline_position_tops    = $lightboxPlusOptions['inline_position_tops'];
                            $inline_position_rights  = $lightboxPlusOptions['inline_position_rights'];
                            $inline_position_bottoms = $lightboxPlusOptions['inline_position_bottoms'];
                            $inline_position_lefts   = $lightboxPlusOptions['inline_position_lefts'];
                            $inline_fixeds           = $lightboxPlusOptions['inline_fixeds'];
                            $inline_opens            = $lightboxPlusOptions['inline_opens'];
                            $inline_opacitys         = $lightboxPlusOptions['inline_opacitys'];
                            //echo "Opacity: ".$inline_opacitys[$i - 1];
                            $lightboxPlusJavaScript .= '  $(".'.$inline_links[$i - 1].'").colorbox({transition:'.$this->setValue( $inline_transitions[$i - 1] ).', speed:'.$this->setValue( $inline_speeds[$i - 1] ).', width:'.$this->setValue( $inline_widths[$i - 1] ).', height:'.$this->setValue( $inline_heights[$i - 1] ).', innerWidth:'.$this->setValue( $inline_inner_widths[$i - 1] ).', innerHeight:'.$this->setValue( $inline_inner_heights[$i - 1] ).', maxWidth:'.$this->setValue( $inline_max_widths[$i - 1] ).', maxHeight:'.$this->setValue( $inline_max_heights[$i - 1] ).', top:'.$this->setValue( $inline_position_tops[$i - 1] ).', right:'.$this->setValue( $inline_position_rights[$i - 1] ).', bottom:'.$this->setValue( $inline_position_bottoms[$i - 1] ).', left:'.$this->setValue( $inline_position_lefts[$i - 1] ).', fixed:'.$this->setBoolean( $inline_fixeds[$i - 1] ).', open:'. $this->setBoolean( $inline_opens[$i - 1] ).', opacity:'.$this->setValue( $inline_opacitys[$i - 1] ).', inline:true, href:"#'.$inline_hrefs[$i - 1].'"});'.PHP_EOL;
                        }
                    }

                    $lightboxPlusJavaScript .= '});'.PHP_EOL;
                    $lightboxPlusJavaScript .= '</script>'.PHP_EOL;
                    echo $lightboxPlusJavaScript;
                }
            }

            /**
            * Add new admin panel to WordPress under the Appearance category
            */
            function lightboxPlusAddPanel() {
                $plugin_page = add_theme_page( 'Lightbox Plus Colorbox', __('Lightbox Plus Colorbox', 'lightboxplus'), 'manage_options', 'lightboxplus', array( &$this, 'lightboxPlusAdminPanel' ) );
                add_action('admin_print_scripts-'.$plugin_page, array( &$this, 'lightboxPlusAdminScripts'));
                add_action('admin_head-'.$plugin_page, array( &$this, 'lightboxPlusColorbox'));
                add_action('admin_print_styles-'.$plugin_page, array( &$this, 'lightboxPlusAdminStyles'));
            }

            /**
            * Tells WordPress to load the jquery, jquery-ui-core and jquery-ui-dialog in the lightbox plus admin panel
            */
            function lightboxPlusAdminScripts( ) {
                global $g_lightbox_plus_url;
                global $g_lbp_version;
                global $g_lbp_colorbox_version;
                wp_enqueue_script('jquery','','','',true);
                wp_enqueue_script('jquery-ui-core','','','',true);
                wp_enqueue_script('jquery-ui-dialog','','','',true);
                wp_enqueue_script('jquery-ui-tabs','','','',true);
                wp_enqueue_script('jquery-colorbox', $g_lightbox_plus_url.'js/jquery.colorbox.'.$g_lbp_colorbox_version.'-min.js', array( 'jquery' ), $g_lbp_colorbox_version, true);
                wp_enqueue_script('lightboxplus-admin', $g_lightbox_plus_url.'js/lightbox.admin.js', array( 'jquery' ), $g_lbp_version, true);
            }

            /**
            * Add CSS styles to lightbox plus admin panel page headers to display lightboxed images
            */
            function lightboxPlusAdminStyles() {
                global $g_lightbox_plus_url;
                global $g_lbp_local_style_url;
                global $g_lbp_global_style_url;
                global $g_lbp_local_style_path;
                global $g_lbp_global_style_path;
                global $g_lbp_version;

                wp_register_style('lightboxplusStyles', $g_lightbox_plus_url.'admin/lightbox.admin.css','',$g_lbp_version,'screen');
                wp_enqueue_style('lightboxplusStyles');

                if ( !empty( $this->lightboxOptions ) ) {
                    $lightboxPlusOptions = $this->getAdminOptions( $this->lightboxOptionsName );

                    if ($lightboxPlusOptions['use_custom_style']) {
                        $style_path_url = $g_lbp_global_style_url;
                        $style_path_dir = $g_lbp_global_style_path;
                    }
                    else {
                        $style_path_url = $g_lbp_local_style_url;
                        $style_path_dir = $g_lbp_local_style_path;
                    }

                    if ( $lightboxPlusOptions['disable_css'] ) {
                        echo "<!-- User set lightbox styles -->".PHP_EOL;
                    } else {
                        wp_register_style('lightboxStyle', $style_path_url.'/'.$lightboxPlusOptions['lightboxplus_style'].'/colorbox.css','',$g_lbp_version,'screen');
                        wp_enqueue_style('lightboxStyle');
                        if (file_exists($style_path_dir.'/'.$lightboxPlusOptions['lightboxplus_style'].'/helper.js')) {
                            wp_enqueue_script('lbp-helper',$style_path_url.'/'.$lightboxPlusOptions['lightboxplus_style'].'/helper.js','',$g_lbp_version,true);
                        }
                    }
                }
            }

            /**
            * Add metabox to edit post/page for per page application of lightbox plus
            */
            function saveLightboxPlusMeta() {
                add_action( 'save_post', array( $this, 'lightboxPlusSaveMeta'),10,1 );
            }

            function lightboxPlusMetaBox() {
                add_meta_box( 'lbp-meta-box', __('Lightbox Plus Colorbox Per Page', 'lightboxplus'), array(&$this,'drawLightboxPlusMeta'), 'page', 'side', 'high' );
            }

            function drawLightboxPlusMeta($post) {
                wp_nonce_field('lbp_meta_nonce','nonce_lbp');
                $lbp_use = get_post_meta( $post->ID, '_lbp_use', true );
                $lbp_uid = get_post_meta( $post->ID, '_lbp_uid', true);
                $lbp_autoload = get_post_meta( $post->ID, '_lbp_autoload', true );
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Use with this page/post:','lightboxplus'); ?>: </th>
                    <td>
                        <input type="hidden" name="lbp_use" value="0">
                        <input type="checkbox" name="lbp_use" id="lbp_use" value="1" <?php if (isset($lbp_use)) { checked( '1', $lbp_use ); }?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Auto launch on this page/post:','lightboxplus'); ?>: </th>
                    <td>
                        <input type="hidden" name="lbp_autoload" value="0">
                        <input type="checkbox" name="lbp_autoload" id="lbp_autoload" value="1"<?php if (isset($lbp_autoload)) { checked( '1', $lbp_autoload ); }?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row" colspan="2"><?php _e('Lightbox Plus Colorbox unique ID for this page:','lightboxplus'); ?>: </th>
                </tr>
                <tr>
                    <td colspan="2">
                        <input type="text" id="lbp_uid" name="lbp_uid" size="40" value="<?php if (!empty($lbp_uid)) { echo $lbp_uid; } else {echo $post->post_name; }?>" />
                        <br />
                        <small><?php _e('(defaults to page/post name/slug)','lightboxplus'); ?></small>
                    </td>
                </tr>
            </table>

            <?php
            }

            function lightboxPlusSaveMeta($post_id) {
                if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }

                if (isset($_POST['nonce_lbp']) && !wp_verify_nonce( $_POST['nonce_lbp'], 'lbp_meta_nonce' ) ) { return; }

                if ( isset($_POST['post_type']) && $_POST['post_type'] == 'page' ) {
                    if ( !current_user_can( 'edit_page', $postid ) ) { return; }
                } else {
                    if ( isset($postid) && !current_user_can( 'edit_post', $postid ) ) { return; }
                }

                if ( isset($post_id)) {
                    if (isset($_POST['lbp_use'])) { $lbp_use = $_POST['lbp_use']; update_post_meta( $post_id, '_lbp_use', $lbp_use );}
                    if (isset($_POST['lbp_autoload'])) { $lbp_autoload = $_POST['lbp_autoload']; update_post_meta( $post_id, '_lbp_autoload', $lbp_autoload );}
                    if (isset($_POST['lbp_uid'])) { $lbp_uid = $_POST['lbp_uid']; update_post_meta( $post_id, '_lbp_uid', $lbp_uid );}
                }

                return $post_id;
            }
        }
    }
?>