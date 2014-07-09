<?php
    /**
    * @package Lightbox Plus Colorbox
    * @subpackage shortcode.class.php
    * @internal 2013.01.16
    * @author Dan Zappone / 23Systems
    * @version 2.7
    * @$Id: shortcode.class.php 654094 2013-01-17 05:54:08Z dzappone $
    * @$URL: http://plugins.svn.wordpress.org/lightbox-plus/trunk/classes/shortcode.class.php $
    */
    if (!class_exists('lbp_shortcode')) {
        class lbp_shortcode extends lbp_utilities {
            /**
            * Replacement shortcode gallery function adds rel="lightbox" or class="cboxModal"
            * Overrides the default gallery template
            *
            * @param string|false $attr
            */
            function lightboxPlusGallery($attr) {
                global $post;

                static $instance = 0;
                $instance++;

                // Allow plugins/themes to override the default gallery template.
                $output = apply_filters('post_gallery', '', $attr);
                if ( $output != '' )
                    return $output;

                // We're trusting author input, so let's at least make sure it looks like a valid orderby statement
                if ( isset( $attr['orderby'] ) ) {
                    $attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
                    if ( !$attr['orderby'] )
                        unset( $attr['orderby'] );
                }

                extract(shortcode_atts(array(
                    'order'      => 'ASC',
                    'orderby'    => 'menu_order ID',
                    'id'         => $post->ID,
                    'itemtag'    => 'dl',
                    'icontag'    => 'dt',
                    'captiontag' => 'dd',
                    'columns'    => 3,
                    'size'       => 'thumbnail',
                    'include'    => '',
                    'exclude'    => ''
                    ), $attr));

                $id = intval($id);
                if ( 'RAND' == $order )
                    $orderby = 'none';

                if ( !empty($include) ) {
                    $include = preg_replace( '/[^0-9,]+/', '', $include );
                    $_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );

                    $attachments = array();
                    foreach ( $_attachments as $key => $val ) {
                        $attachments[$val->ID] = $_attachments[$key];
                    }
                } elseif ( !empty($exclude) ) {
                    $exclude = preg_replace( '/[^0-9,]+/', '', $exclude );
                    $attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
                } else {
                    $attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
                }

                if ( empty($attachments) )
                    return '';

                if ( is_feed() ) {
                    $output = "\n";
                    foreach ( $attachments as $att_id => $attachment )
                        $output .= wp_get_attachment_link($att_id, $size, true) . "\n";
                    return $output;
                }

                $itemtag = tag_escape($itemtag);
                $captiontag = tag_escape($captiontag);
                $columns = intval($columns);
                $itemwidth = $columns > 0 ? floor(100/$columns) : 100;
                $float = is_rtl() ? 'right' : 'left';

                $selector = "gallery-{$instance}";

                $gallery_style = $gallery_div = '';
                if ( apply_filters( 'use_default_gallery_style', true ) )
                    $gallery_style = "
                    <style type='text/css'>
                    #{$selector} {
                    margin: auto;
                    }
                    #{$selector} .gallery-item {
                    float: {$float};
                    margin-top: 10px;
                    text-align: center;
                    width: {$itemwidth}%;
                    }
                    #{$selector} img {
                    border: 2px solid #cfcfcf;
                    }
                    #{$selector} .gallery-caption {
                    margin-left: 0;
                    }
                    </style>
                    <!-- see gallery_shortcode() in wp-includes/media.php -->";
                $size_class = sanitize_html_class( $size );
                $gallery_div = "<div id='$selector' class='gallery galleryid-{$id} gallery-columns-{$columns} gallery-size-{$size_class}'>";
                $output = apply_filters( 'gallery_style', $gallery_style . "\n\t\t" . $gallery_div );

                $i = 0;
                foreach ( $attachments as $id => $attachment ) {
                    $link = isset($attr['link']) && 'file' == $attr['link'] ? wp_get_attachment_link($id, $size, false, false) : wp_get_attachment_link($id, $size, true, false);

                    /**
                    * Rewrite links for wp-gallery to add lightbox plus properties (class or rel=[])
                    *
                    * @var mixed
                    */
                    if (!empty($this->lightboxOptions)) {$lightboxPlusOptions = $this->getAdminOptions($this->lightboxOptionsName);}
                    if ($lightboxPlusOptions['multiple_galleries']) {
                        $link = $this->lightboxPlusReplace($link,'-'.$instance);
                    }
                    else {
                        $link = $this->lightboxPlusReplace($link,'');
                    }
                    $output .= "<{$itemtag} class='gallery-item'>";
                    $output .= "
                    <{$icontag} class='gallery-icon'>
                    $link
                    </{$icontag}>";
                    if ( $captiontag && trim($attachment->post_excerpt) ) {
                        $output .= "
                        <{$captiontag} class='wp-caption-text gallery-caption'>
                        " . wptexturize($attachment->post_excerpt) . "
                        </{$captiontag}>";
                    }
                    $output .= "</{$itemtag}>";
                    if ( $columns > 0 && ++$i % $columns == 0 )
                        $output .= '<br style="clear: both" />';
                }

                $output .= "
                <br style='clear: both;' />
                </div>\n";

                return $output;
            }
        }
    }
?>