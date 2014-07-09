<?php
    /**
    * @package Lightbox Plus Colorbox
    * @subpackage lightbox.inline.php
    * @internal 2013.01.16
    * @author Dan Zappone / 23Systems
    * @version 2.7
    * @$Id: lightbox.inline.php 937945 2014-06-24 17:11:13Z dzappone $
    * @$URL: http://plugins.svn.wordpress.org/lightbox-plus/tags/2.7/admin/lightbox.inline.php $
    */
?>
<!-- Inline Lightbox Settings -->
<div id="poststuff" class="lbp">
    <div class="postbox">
        <h3 class="handle"><?php _e( 'Lightbox Plus Colorbox - Inline Lightbox Settings','lightboxplus' ); ?></h3>
        <div class="inside toggle">
            <div id="ilbp-tabs">
                <ul>
                    <li><a href="#ilbp-tabs-1"><?php _e( 'General','lightboxplus' ); ?></a></li>
                    <li><a href="#ilbp-tabs-2"><?php _e( 'Usage','lightboxplus' ); ?></a></li>
                    <li><a href="#ilbp-tabs-3"><?php _e( 'Demo/Test','lightboxplus' ); ?></a></li>
                </ul>
                <!-- General -->
                <div id="ilbp-tabs-1">
                    <input type="hidden" name="ready_inline" value="1" />
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th>&nbsp;</th>
                                <th style="text-align:center;"><b><?php _e( 'Link Class','lightboxplus' ); ?></b><br /><b><?php _e( 'Content ID','lightboxplus' ); ?></b></th>
                                <th style="text-align:center;"><b><?php _e( 'Transition','lightboxplus' ); ?></b><br /><b><?php _e( 'Speed','lightboxplus' ); ?></b></th>
                                <th style="text-align:center;"><b><?php _e( 'Width','lightboxplus' ); ?><br /><?php _e( 'Height','lightboxplus' ); ?></b></th>
                                <th style="text-align:center;"><b><?php _e( 'Inner Width','lightboxplus' ); ?><br /><?php _e( 'Inner Height','lightboxplus' ); ?></b></th>
                                <th style="text-align:center;"><b><?php _e( 'Max Width','lightboxplus' ); ?><br /><?php _e( 'Max Height','lightboxplus' ); ?></b></th>
                                <th style="text-align:center;"><b><?php _e( 'Position','lightboxplus' ); ?></b><br /><div style="font-size:8px;line-height:9px;"><?php _e( 'Top','lightboxplus' ); ?><br /><?php _e( 'Right, Bottom','lightboxplus' ); ?><br /><?php _e( 'Left','lightboxplus' ); ?></div></th>
                                <th style="text-align:center;"><b><?php _e( 'Fixed</b>','lightboxplus' ); ?></th>
                                <th style="text-align:center;"><b><?php _e( 'Auto Open','lightboxplus' ); ?></b></th>
                                <th style="text-align:center;"><b><?php _e( 'Overlay Opacity','lightboxplus' ); ?></b></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                for ($i = 1; $i <= $lightboxPlusOptions['inline_num']; $i++) {
                                    $inline_links            = array();
                                    $inline_hrefs            = array();
                                    $inline_transitions      = array();
                                    $inline_speeds           = array();
                                    $inline_widths           = array();
                                    $inline_heights          = array();
                                    $inline_inner_widths     = array();
                                    $inline_inner_heights    = array();
                                    $inline_max_widths       = array();
                                    $inline_max_heights      = array();
                                    $inline_position_tops    = array();
                                    $inline_position_rights  = array();
                                    $inline_position_bottoms = array();
                                    $inline_position_lefts   = array();
                                    $inline_fixeds           = array();
                                    $inline_opens            = array();
                                    $inline_opacitys         = array();
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
                                ?>
                                <tr <?php if ($i % 2 == 0) {echo 'class="alternate"';} ?>>
                                    <td><?php _e( 'Inline Lightbox #'.$i, 'lightboxplus' )?>:</td>
                                    <td align="center">
                                        <input type="text" size="15" name="inline_link_<?php echo $i; ?>" id="inline_link_<?php echo $i; ?>" value="<?php if (empty( $inline_links[$i - 1] )) { echo 'lbp-inline-link-'.$i; } else {echo $inline_links[$i - 1];}?>" /><br /><input type="text" size="15" name="inline_href_<?php echo $i; ?>" id="inline_href_<?php echo $i; ?>" value="<?php if (empty( $inline_hrefs[$i - 1] )) { echo 'lbp-inline-href-'.$i; } else {echo $inline_hrefs[$i - 1];}?>" />
                                    </td>
                                    <td align="center">
                                        <select name="inline_transition_<?php echo $i; ?>" id="inline_transition_<?php echo $i; ?>">
                                            <option value="elastic"<?php if ( $inline_transitions[$i - 1] == 'elastic' ) echo ' selected="selected"'?>>Elastic</option>
                                            <option value="fade"<?php if ( $inline_transitions[$i - 1] == 'fade' ) echo ' selected="selected"'?>>Fade</option>
                                            <option value="none"<?php if ( $inline_transitions[$i - 1] == 'none' ) echo ' selected="selected"'?>>None</option>
                                        </select><br />
                                        <select name="inline_speed_<?php echo $i; ?>" id="inline_speed_<?php echo $i; ?>">
                                            <?php
                                                for($j = 0;$j <= 5001;){ ?>
                                                <option value="<?php echo $j; ?>"<?php if ( $inline_speeds[$i - 1] == strval($j) ) echo ' selected="selected"'?>><?php echo $j; ?></option>
                                                <?php
                                                    if ($j >= 2000) { $j = $j + 500; }
                                                    elseif ($j >= 1250) { $j = $j + 250; }
                                                    else { $j = $j + 50; }
                                                }
                                            ?>
                                        </select>
                                    </td>
                                    <td align="center">
                                        <input type="text" size="5" name="inline_width_<?php echo $i; ?>" id="inline_width_<?php echo $i; ?>" value="<?php if (empty( $inline_widths[$i - 1] )) { echo '80%'; } else {echo $inline_widths[$i - 1];}?>" /><br />
                                        <input type="text" size="5" name="inline_height_<?php echo $i; ?>" id="inline_height_<?php echo $i; ?>" value="<?php if (empty( $inline_heights[$i - 1] )) { echo '80%'; } else  {echo $inline_heights[$i - 1];}?>" />
                                    </td>
                                    <td align="center">
                                        <input type="text" size="5" name="inline_inner_width_<?php echo $i; ?>" id="inline_inner_width_<?php echo $i; ?>" value="<?php if (empty( $inline_inner_widths[$i - 1] )) { echo 'false'; } else {echo $inline_inner_widths[$i - 1];}?>" /><br />
                                        <input type="text" size="5" name="inline_inner_height_<?php echo $i; ?>" id="inline_inner_height_<?php echo $i; ?>" value="<?php if (empty( $inline_inner_heights[$i - 1] )) { echo 'false'; } else {echo $inline_inner_heights[$i - 1];}?>" />
                                    </td>
                                    <td align="center">
                                        <input type="text" size="5" name="inline_max_width_<?php echo $i; ?>" id="inline_max_width_<?php echo $i; ?>" value="<?php if (empty( $inline_max_widths[$i - 1] )) { echo '80%'; } else {echo $inline_max_widths[$i - 1];}?>" /><br />
                                        <input type="text" size="5" name="inline_max_height_<?php echo $i; ?>" id="inline_max_height_<?php echo $i; ?>" value="<?php if (empty( $inline_max_heights[$i - 1] )) { echo '80%'; } else  {echo $inline_max_heights[$i - 1];}?>" />
                                    </td>
                                    <td align="center">
                                        <input type="text" size="5" name="inline_position_top_<?php echo $i; ?>" id="inline_position_top_<?php echo $i; ?>" value="<?php if (empty( $inline_position_tops[$i - 1] )) { echo ''; } else {echo $inline_position_tops[$i - 1];}?>" /><br />
                                        <input type="text" size="5" name="inline_position_right_<?php echo $i; ?>" id="inline_position_right_<?php echo $i; ?>" value="<?php if (empty( $inline_position_rights[$i - 1] )) { echo ''; } else {echo $inline_position_rights[$i - 1];}?>" /><input type="text" size="5" name="inline_position_bottom_<?php echo $i; ?>" id="inline_position_bottom_<?php echo $i; ?>" value="<?php if (empty( $inline_position_bottoms[$i - 1] )) { echo ''; } else {echo $inline_position_bottoms[$i - 1];}?>" /><br />
                                        <input type="text" size="5" name="inline_position_left_<?php echo $i; ?>" id="inline_position_left_<?php echo $i; ?>" value="<?php if (empty( $inline_position_lefts[$i - 1] )) { echo ''; } else {echo $inline_position_lefts[$i - 1];}?>" />
                                    </td>
                                    <?php
                                        /**
                                        * @todo fix inline fixed and open saving
                                        */
                                    ?>
                                    <td align="center">
                                        <input type="hidden" name="inline_fixed_<?php echo $i; ?>" value="0" />
                                        <input type="checkbox" name="inline_fixed_<?php echo $i; ?>" id="inline_fixed_<?php echo $i; ?>" value="1"<?php if ( ($inline_fixeds[$i - 1] )) { echo ' checked="checked"'; }?> />
                                    </td>
                                    <td align="center">
                                    <input type="hidden" name="inline_open_<?php echo $i; ?>" value="0" />
                                        <input type="checkbox" name="inline_open_<?php echo $i; ?>" id="inline_open_<?php echo $i; ?>" value="1"<?php if ( ($inline_opens[$i - 1] )) { echo ' checked="checked"'; }?> />
                                    </td>
                                    <td align="center">

                                        <select name="inline_opacity_<?php echo $i; ?>" id="inline_opacity_<?php echo $i; ?>">
                                            <?php
                                                for($j = 0; $j <= 1.01; $j = $j + .05){ ?>
                                                <option value="<?php echo $j; ?>"<?php if ( $inline_opacitys[$i - 1] == strval($j) ) { echo ' selected="selected"'; }?>><?php echo ($j*100); ?>%</option>
                                                <?php
                                                }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <?php
                                }
                            ?>
                        </tbody>
                    </table>
                </div>
                <!-- Usage -->
                <div id="ilbp-tabs-2">
                    <table class="form-table">
                        <tr>
                            <td>
                                <h4><?php _e('Using Inline Lightboxes', 'lightboxplus')?></h4>
                                <div id="lbp_for_inline_tip">
                                <p><?php _e( 'Inline lightboxes are used to display content that exists on the current page.  It can be used to display a form, video or any other content that is contained on the page.  In order to display inline content using Lightbox Plus Colorbox and Colorbox you must at a minimum has the following items set: Link Class, Content ID, Width, Height, and Opacity.', 'lightboxplus')?></p>
                                <div class="ui-state-highlight ui-corner-all" style="margin-top: 20px; padding: 0 .7em;">
                                    <h5><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span><?php _e('Example', 'lightboxplus')?></h5>
                                    <p><?php _e( 'The following example shows how to setup content for display in a lightbox.  You will need to create a link to the content that contains a class that has the same value as the Link Class for the inline lightbox you are using.', 'lightboxplus')?></p>
                                    <p class="codebox">
                                        <code>&lt;a class="lbp-inline-link-1" href="#"><?php _e( 'Inline HTML Link Name', 'lightboxplus') ?>&lt;/a></code>
                                    </p>
                                    <p><?php _e( 'You will also need to set up a div element to contain you content.  The div element that contains the content must contains have and id with a value of the Content ID for the inline light box you are using.  Finally if you want the content to be hidden until the visitor clicks the link, wrap the content div with another div and set the value for style to display:none or assign a class that has display:none for a property', 'lightboxplus')?></p>
                                    <p class="codebox">
                                        <code>
                                            &lt;div style="display:none"><br />
                                            &nbsp;&nbsp;&nbsp;&nbsp;&lt;div id="lbp-inline-href-1" style="padding: 10px;background: #fff"><br />
                                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e( 'Inline Content Goes Here', 'lightboxplus') ?><br />
                                            &nbsp;&nbsp;&nbsp;&nbsp;&lt;/div><br />
                                            &lt;/div></code>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                <!-- Demo/Test -->
                <div id="ilbp-tabs-3">
                    <table class="form-table">
                        <tr valign="top">
                            <td>
                                <?php _e('Here you can test you settings with various different implementation of Lightbox Plus Colorbox using inline content.  This demo makes use of the first inline lightbox you have set up.  If they do not work try reloading the page and please check that you have the following items set: Link Class, Content ID, Width, Height, and Opacity.  You will not be able to display this example without the minimum options set.',"lightboxplus"); ?>
                                <p class="inline_link_test_item">
                                    <a class="<?php echo $inline_links[0]; ?>" href="#"><?php _e('Inline Content Test including form',"lightboxplus"); ?></a>
                                </p>
                                <p class="codebox">
                                    <strong style="font-size:0.8em;">Skeleton code</strong><br /><br />
                                    <code>
                                        &lt;div style="display:none"><br />
                                        &nbsp;&nbsp;&nbsp;&nbsp;&lt;div id="<?php echo $inline_hrefs[0]; ?>" style="padding: 10px;background: #fff"><br />
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&lt;h3><?php _e( 'TITLE HERE','lightboxplus' ); ?>&lt;/h3><br />
                                        &nbsp;&nbsp;&nbsp;&nbsp;&lt;div><br />
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e( 'FORM HERE','lightboxplus' ); ?><br />
                                        &nbsp;&nbsp;&nbsp;&nbsp;&lt;/div><br />
                                        &nbsp;&nbsp;&nbsp;&nbsp;&lt;p style="text-align: justify;"><br />
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e( 'TEXT HERE','lightboxplus' ); ?><br />
                                        &nbsp;&nbsp;&nbsp;&nbsp;&lt;/p><br />
                                        &lt;/div><br />
                                    </code>
                                </p>

                            </td>
                        </tr>
                    </table>
                    <!-- end testing -->
                </div>
            </div>
            <p class="submit">
                <input type="submit" style="padding:5px 30px 5px 30px;" name="Submit" title="<?php _e( 'Save all Lightbox Plus Colorbox settings', 'lightboxplus' )?>" value="<?php _e( 'Save all settings', 'lightboxplus' )?> &raquo;" />
            </p>
        </div>
    </div>
</div>
	<!-- End Inline Lightbox -->