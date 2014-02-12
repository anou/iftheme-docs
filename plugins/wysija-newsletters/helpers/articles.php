<?php
defined('WYSIJA') or die('Restricted access');
class WYSIJA_help_articles extends WYSIJA_object {

    function WYSIJA_help_articles() {

    }

    function getPosts($params = array()) {
        if(!empty($params['exclude'])) {
            $exclude = $params['exclude'];
        } else {
            $exclude = NULL;
        }

        if(!empty($params['include'])) {
            $include = $params['include'];
        } else {
            $include = NULL;
        }

        if(!empty($params['includeonly'])) {
            $includeonly = $params['includeonly'];
        } else {
            $includeonly = NULL;
        }

        // transform category_ids to array
        if(strlen($params['category_ids']) === 0) {
            $categories = NULL;
        } else {
            $categories = explode(',', $params['category_ids']);
        }
        if(!isset($params['cpt'])) $params['cpt']='post';
        $args = array(
            'numberposts'     => (int)$params['post_limit'],
            'offset'          => 0,
            'category'        => $categories,
            'orderby'         => 'post_date',
            'order'           => 'DESC',
            'include'         => $include,
            'includeonly'         => $includeonly,
            'exclude'         => $exclude,
            'meta_key'        => NULL,
            'meta_value'      => NULL,
            'post_type'       => $params['cpt'],
            'post_mime_type'  => NULL,
            'post_parent'     => NULL,
            'post_status'     => 'publish'
        );

        if(isset($params['post_date'])) {
            $args['post_date'] = $params['post_date'];
        }

        $modelPosts=WYSIJA::get('wp_posts','model');

        $posts=$modelPosts->get_posts($args);

        if(empty($posts)) return array();
        $mConfig=WYSIJA::get('config','model');
        foreach($posts as $key => $post) {
            if($mConfig->getValue('interp_shortcode'))    $post['post_content']=apply_filters('the_content',$post['post_content']);
            $posts[$key] = (array)$post;
        }
        return $posts;
    }

    function convertPostToBlock($post, $params = array()) {

        // defaults
        $defaults = array(
            'title_tag' => 'h1',
            'title_alignment' => 'left',
            'image_alignment' => 'left',
            'readmore' => __('Read online.', WYSIJA),
            'post_content' => 'full'
        );

        // merge params with default params
        $params = array_merge($defaults, $params);

        if($params['post_content'] === 'full') {
            $content = $post['post_content'];
        } else {
            // get excerpt
            if(!empty($post['post_excerpt'])) {
                $content = $post['post_excerpt'];
            } else {
                // remove shortcodes before getting the excerpt
                $post['post_content'] = preg_replace('/\[[^\[\]]*\]/', '', $post['post_content']);

                // if excerpt is empty then try to find the "more" tag
                $excerpts = explode('<!--more-->', $post['post_content']);
                if(count($excerpts) > 1){
                    $content = $excerpts[0];
                }else{
                    // finally get a made up excerpt if there is no other choice
                    $helperToolbox = WYSIJA::get('toolbox', 'helper');
                    $content = $helperToolbox->excerpt($post['post_content'], 60);
                }
            }
            // strip title tags from excerpt
            $content = preg_replace('/<([\/])?h[123456](.*?)>/', '<$1p$2>', $content);
        }

        // convert new lines into <p>
        $content = wpautop($content);

        // remove images
        $content = preg_replace('/<img[^>]+./','', $content);

        // remove shortcodes
        $content = preg_replace('/\[[^\[\]]*\]/', '', $content);

        // remove wysija nl shortcode
        $content= preg_replace('/\<div class="wysija-register">(.*?)\<\/div>/','',$content);

        // convert embedded content if necessary
        $content = $this->convertEmbeddedContent($content);

        // convert h4 h5 h6 to h3
        $content = preg_replace('/<([\/])?h[456](.*?)>/', '<$1h3$2>', $content);

        // convert ol to ul
        $content = preg_replace('/<([\/])?ol(.*?)>/', '<$1ul$2>', $content);

        // convert dollar signs
        $content = str_replace(array('$', 'â‚¬', 'Â£', 'Â¥'), array('&#36;', '&euro;', '&pound;', '&#165;'), $content);

        // strip useless tags
        $content = strip_tags($content, '<p><em><span><b><strong><i><h1><h2><h3><a><ul><ol><li><br>');

        // set post title if present
        if(strlen(trim($post['post_title'])) > 0) {
            // cleanup post title
            $post['post_title'] = trim(str_replace(array('$', 'â‚¬', 'Â£', 'Â¥'), array('&#36;', '&euro;', '&pound;', '&#165;'), strip_tags($post['post_title'])));
            // build content starting with title
            $content = '<'.$params['title_tag'].' class="align-'.$params['title_alignment'].'">'.  $post['post_title'].'</'.$params['title_tag'].'>'.$content;
        }

        // add read online link
        $content .= '<p><a href="'.get_permalink($post['ID']).'" target="_blank">'.esc_attr($params['readmore']).'</a></p>';

        // set image/text alignment based on present data
        $post_image = null;

        if(isset($post['post_image'])) {
            $post_image = $post['post_image'];

            // set image alignment to match block's
            $post_image['alignment'] = $params['image_alignment'];

            // constrain size depending on alignment
            if(empty($post_image['height']) or $post_image['height'] === 0) {
                $post_image = null;
            } else {
                $ratio = round(($post_image['width'] / $post_image['height']) * 1000) / 1000;
                switch($params['image_alignment']) {
                    case 'alternate':
                    case 'left':
                    case 'right':
                        // constrain width to 325px max
                        $post_image['width'] = min($post_image['width'], 325);
                        break;
                    case 'center':
                        // constrain width to 564px max
                        $post_image['width'] = min($post_image['width'], 564);
                        break;
                }

                if($ratio > 0) {
                    // if ratio has been calculated, deduce image height
                    $post_image['height'] = (int)($post_image['width'] / $ratio);
                } else {
                    // otherwise skip the image
                    $post_image = null;
                }
            }
        }

        $block = array(
          'position' => 0,
          'type' => 'content',
          'text' => array(
              'value' => base64_encode($content)
          ),
          'image' => $post_image,
          'alignment' => $params['image_alignment']
        );

        return $block;
    }

    function getImage($post) {
        $image_info = null;
        $post_image = null;

        // check if has_post_thumbnail function exists, if not, include wordpress class
        if(!function_exists('has_post_thumbnail')) {
            require_once(ABSPATH.WPINC.'/post-thumbnail-template.php');
        }

        // check for post thumbnail
        if(has_post_thumbnail($post['ID'])) {
            $post_thumbnail = get_post_thumbnail_id($post['ID']);

            // get attachment data (src, width, height)
            $image_info = wp_get_attachment_image_src($post_thumbnail, 'single-post-thumbnail');

            // get alt text
            $altText = trim(strip_tags(get_post_meta($post_thumbnail, '_wp_attachment_image_alt', true)));
            if(strlen($altText) === 0) {
                // if the alt text is empty then use the post title
                $altText = trim(strip_tags($post['post_title']));
            }
        }

        if($image_info !== null) {
            $post_image = array(
                'src' => $image_info[0],
                'width' => $image_info[1],
                'height' => $image_info[2],
                'alt' => urlencode($altText)
            );
        } else {
            $matches = $matches2 = array();

            $output = preg_match_all('/<img.+src=['."'".'"]([^'."'".'"]+)['."'".'"].*>/i', $post['post_content'], $matches);

            if(isset($matches[0][0])){
                preg_match_all('/(src|height|width|alt)="([^"]*)"/i', $matches[0][0], $matches2);

                if(isset($matches2[1])){
                    foreach($matches2[1] as $k2 => $v2) {
                        if(in_array($v2, array('src', 'width', 'height', 'alt'))) {
                            if($post_image === null) $post_image = array();

                            if($v2 === 'alt') {
                                // special case for alt text as it requireds url encoding
                                $post_image[$v2] = urlencode($matches2[2][$k2]);
                            } else {
                                // otherwise simply get the value
                                $post_image[$v2] = $matches2[2][$k2];
                            }
                        }
                    }
                }
            }
        }

        $helper_images = WYSIJA::get('image','helper');
        $post_image = $helper_images->valid_image($post_image);

        if($post_image===null) return $post_image;
        return array_merge($post_image, array('url' => get_permalink($post['ID'])));
    }

    function convertEmbeddedContent($content = '') {
        // remove embedded video and replace with links
        $content = preg_replace('#<iframe.*?src=\"(.+?)\".*><\/iframe>#', '<a href="$1">'.__('Click here to view media.', WYSIJA).'</a>', $content);

        // replace youtube links
        $content = preg_replace('#http://www.youtube.com/embed/([a-zA-Z0-9_-]*)#Ui', 'http://www.youtube.com/watch?v=$1', $content);

        return $content;
    }

}
