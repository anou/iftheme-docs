<?php
/*
 * Plugin Name: IF Plugin
 * Description: Site specific code for IF (for examplke, adds the "news" custom content type...)
 * Version: 1.0.0
 * Author: David THOMAS
 * Author URI: http://www.smol.org/studio-de-creation-sympathique/habitants/anou
 * License: GPL2
 * Text Domain: ifplugin
 * Domain Path: /languages
 */
/*
  Copyright 2014  David THOMAS  (email: anou@smol.org)
  
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.
  
  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
  
  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * TODO/IDEA: 
 * - Adapt theme with "if ifplugin installed"
 * - 
 */
/**
 * Load plugin textdomain.
 *
 * @since 1.0
 */
function ifplugin_load_textdomain() {
  load_plugin_textdomain('ifplugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );  
}
add_action( 'plugins_loaded', 'ifplugin_load_textdomain' );

/**
 * Register a news post type.
 *
 * @link http://codex.wordpress.org/Function_Reference/register_post_type
 */
function news_post_init() {
	$labels = array(
		'name'               => _x( 'News', 'post type general name', 'ifplugin' ),
		'singular_name'      => _x( 'News', 'post type singular name', 'ifplugin' ),
		'menu_name'          => _x( 'News', 'admin menu', 'ifplugin' ),
		'name_admin_bar'     => _x( 'News', 'add new on admin bar', 'ifplugin' ),
		'add_new'            => _x( 'Add New', 'news', 'ifplugin' ),
		'add_new_item'       => __( 'Add New News', 'ifplugin' ),
		'new_item'           => __( 'New News', 'ifplugin' ),
		'edit_item'          => __( 'Edit News', 'ifplugin' ),
		'view_item'          => __( 'View News', 'ifplugin' ),
		'all_items'          => __( 'All News', 'ifplugin' ),
		'search_items'       => __( 'Search News', 'ifplugin' ),
		'parent_item_colon'  => __( 'Parent News:', 'ifplugin' ),
		'not_found'          => __( 'No news found.', 'ifplugin' ),
		'not_found_in_trash' => __( 'No news found in Trash.', 'ifplugin' )
	);

	$args = array(
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'query_var'           => true,
		'rewrite'             => array( 
		                          'slug' => 'if-news',
		                          'with_front' => false,
		                          
                             ),
		'capability_type'     => 'post',
		'has_archive'         => true,
		'hierarchical'        => false,
		'menu_position'       => null,
		'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', /* 'comments' */ ),
		'taxonomies'          => array( 'category' ),
		'register_meta_box_cb'=> 'add_news_metaboxes',
		'menu_icon' => 'dashicons-format-aside', //https://developer.wordpress.org/resource/dashicons
	);

	register_post_type( 'news', $args );
}
add_action( 'init', 'news_post_init', 0 );


/**
 * to fix permalinks on activation/Desactivation
 */
function news_rewrite_flush() {
    // First, we "add" the custom post type via the above written function.
    // Note: "add" is written with quotes, as CPTs don't get added to the DB,
    // They are only referenced in the post_type column with a post entry, 
    // when you add a post of this CPT.
    news_post_init();

    // ATTENTION: This is *only* done during plugin activation hook in this example!
    // You should *NEVER EVER* do this on every page load!!
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'news_rewrite_flush' );

add_filter('post_updated_messages', 'set_messages' );
function set_messages($messages) {
  global $post, $post_ID;
  $post_type = get_post_type( $post_ID );
  
  $obj = get_post_type_object($post_type);
  $singular = $obj->labels->singular_name;
  
  $messages[$post_type] = array(
    0 => '', // Unused. Messages start at index 1.
    1 => sprintf( __('%1$s updated. <a href="%2$s">View %1$s</a>', 'ifplugin'), $singular, esc_url( get_permalink($post_ID) ) ),
    2 => __('Custom field updated.', 'ifplugin'),
    3 => __('Custom field deleted.', 'ifplugin'),
    4 => sprintf( __('%s updated.', 'ifplugin'), $singular ),
    5 => isset($_GET['revision']) ? sprintf( __('%1$s restored to revision from %2$s', 'ifplugin'), $singular, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
    6 => sprintf( __('%1$s published. <a href="%2$s">View %1$s</a>', 'ifplugin'), $singular, esc_url( get_permalink($post_ID) ) ),
    7 => __('Page saved.', 'ifplugin'),
    8 => sprintf( __('%1$s submitted. <a target="_blank" href="%2$s">Preview %1$s</a>', 'ifplugin'), $singular, esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
    9 => sprintf( __('%3$s scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview %3$s</a>', 'ifplugin'), date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ), $singular ),
    10 => sprintf( __('%1$s draft updated. <a target="_blank" href="%2$s">Preview %1$s</a>', 'ifplugin'), $singular, esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
  );
  return $messages;
}



/**
 * Add the News Meta Boxes
 */
function add_news_metaboxes() {
  $meta_boxes = ifplugin_meta_boxes();

  foreach( $meta_boxes as $k => $meta_box) {
    //add_meta_box( $id, $title, $callback, $screen, $context, $priority, $callback_args );
    add_meta_box($meta_box['id'], $meta_box['title'], 'ifp_news_meta_html', $meta_box['post_type'], $meta_box['context'], $meta_box['priority'], $meta_box['meta_box_fields']);
  }
}

/**
 * Defines custom meta boxes array
 */
function ifplugin_meta_boxes() {
 
    // Defines $prefix. Should begin with an underscore unless you want fields to be doubled in the Custom Fields editor.
    $prefix = '_ifp_';
 
    // Defines meta box array.
    $meta_boxes[] = array(
      'id' => 'ifp_newsdate',
      'title' => __('News Date', 'ifplugin'),
      'post_type' => 'news',
      'context' => 'side', // Where to put the MB.
      'priority' => 'high', // Position vs other blocks.
      'meta_box_fields' => array(
        array(
          'id' => $prefix . 'news_date',
          'label' => __( 'Please enter a date (YYYY-MM-DD)', 'ifplugin' ),
          'type' => 'text',
          'description' => '', // Optional.
          'placeholder' => date('Y-m-d'), // Optional
          'required' => true
        ),
      ) // End array meta_box_fields
    ); // End array $meta_boxes
 
    $meta_boxes[] = array(
      'id' => 'ifp_news_subhead',
      'title' => __('News Subhead', 'ifplugin'),
      'post_type' => 'news',
      'context' => 'normal', // Where to put the MB.
      'priority' => 'high', // Position vs other blocks.
      'meta_box_fields' => array(
        array(
          'id' => $prefix . 'news_subhead',
          'label' => __( 'Subhead text', 'ifplugin' ),
          'type' => 'text',
          'description' => '', // Optional.
          'placeholder' => '', // Optional
          'required' => false
        ),
      ) // End array meta_box_fields
    ); // End array $meta_boxes
 
    // Add other meta boxes here as needed.
 
    return $meta_boxes;
} // End function thtk_example_meta_boxes()


/**
 * The News Metaboxes output
 */
function ifp_news_meta_html( $post, $fields ) {
  $output = '';
  $infos = $fields['args'];
  // Add an nonce field so we can check for it later.
	wp_nonce_field( 'ifp_meta_box', 'ifp_meta_box_nonce' );
  foreach($infos as $k => $field) {

   	$value = get_post_meta( $post->ID, $field['id'], true );
   	
   	$value = $field['id'] == '_ifp_news_date' ? date('Y-m-d', $value) : $value;

    switch( $field['type']){
      case 'text':
      	$output .= '<label for="' . $field['id'] . '">';
      	$output .= $field['label'];
      	$output .= '</label>&nbsp;';
      	$output .= $field['required'] ? '<span style="color:red" title="' . __("Mandatory field", 'ifplugin') . '">*</span>&nbsp;' : '';
      	$output .= '<input placeholder="' . $field['placeholder'] . '" type="text" id="' . $field['id'] . '" name="' . $field['id'] . '" value="' . esc_attr( $value ) . '" />';
      break;
    }
  }
  
  echo $output;
}

/**
 * The News Date Metabox
 */
/*
function ifp_newsdate_html( $post ) {
  // Add an nonce field so we can check for it later.
	wp_nonce_field( 'ifp_meta_box', 'ifp_meta_box_nonce' );

	// Use get_post_meta() to retrieve an existing value
	// from the database and use the value for the form.
	$value = get_post_meta( $post->ID, '_ifp_news_date', true );
	$value = date('Y-m-d', $value);

	echo '<label for="ifp_news_date">';
	_e( 'Please enter a date (YYYY-MM-DD)', 'ifplugin' );
	echo '</label>&nbsp;<span style="color:red" title="' . __("Mandatory field", 'ifplugin') . '">*</span>';
	echo '<input placeholder="' . date('Y-m-d') . '" type="text" id="ifp_news_date" name="ifp_news_date" value="' . esc_attr( $value ) . '" size="10" />';

}
*/

/**
 * Adds error class to metabox
 *
 * TODO: find how to do it on validation or post save...
 */
function add_metabox_classes($classes) {
    array_push($classes,'ifplugin-error');
    return $classes;
}
//add class to metabox
//add_filter('postbox_classes_news_ifp_newsdate','add_metabox_classes');


/**
 * When the post is saved, saves our custom data.
 *
 * @param int $post_id The ID of the post being saved.
 */
add_action( 'save_post', 'ifplugin_save_news_meta' );
function ifplugin_save_news_meta( $post_id ) {
	// We need to verify this came from our screen and with proper authorization,
	// because the save_post action can be triggered at other times.
	// Check if our nonce is set.
	if ( ! isset( $_POST['ifp_meta_box_nonce'] ) ) {
		return;
	}
	// Verify that the nonce is valid.
	if ( ! wp_verify_nonce( $_POST['ifp_meta_box_nonce'], 'ifp_meta_box' ) ) {
		return;
	}
	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	// Check the user's permissions.
	if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}
	} else {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}
  /* OK, it's safe for us to save the data now. */
	
  $meta_boxes = ifplugin_meta_boxes();

  foreach( $meta_boxes as $k => $meta_box ) {
    $fields[] = $meta_box['meta_box_fields'];
  }

  foreach( $fields as $k => $field ) {

  	$fid = $field[0]['id'];
  	
  	// Make sure that it is set.
  	//_ifp_news_subhead & _ifp_news_date
  	if ( !isset( $_POST[$fid] ) ) {
  		return;
  	}
  	// Sanitize user input.
  	$news_data = sanitize_text_field( $_POST[$fid] );
    $prevent_publish = false;//Set to true if data was invalid.
    
    //check format
  	//Date must be YYYY-MM-DD
  	$check_date = $fid == '_ifp_news_date' ? _ifplugin_check_date( $news_data ) : true;
  
  	if ( !$check_date ) {
    	$prevent_publish = true;
//       add_filter( 'post_updated_messages', 'remove_all_messages_on_error' );
    	ifplugin_set_news_error();
  	} 
  	else {
    	switch($fid) {
      	case '_ifp_news_date':
      	  //transform in timestamp
 	        list( $year , $month , $day ) = explode('-',$news_data);
          $field_data = mktime(date('H'),date('i'),date('s'),$month,$day,$year);
      	break;
      	default:
      	  $field_data = $news_data;
      	  
    	}

      //save it finally
      ifplugin_update_news_meta( $post_id, $fid, $field_data );
    }
  }//end foreach fields


  if ($prevent_publish) {

      // unhook this function to prevent indefinite loop
      remove_action('save_post', 'ifplugin_save_news_meta');

      // update the post to change post status
      wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));

      // re-hook this function again
      add_action('save_post', 'ifplugin_save_news_meta');
  }

}

/*
//remove "post saved" message on error
function remove_all_messages_on_error( $messages ) {
  return array();
}
*/


function ifplugin_update_news_meta( $post_id, $field_key, $news_data ) {
  // We passed all the check so,
	// Update the meta field in the database.
	update_post_meta( $post_id, $field_key, $news_data );
}

/**
 * validate format
 *
 * @$news_date = (string) YYYY-MM-DD
 */
function _ifplugin_validate_format_date( $news_date ) {
  $news_date = "2015-03-02";
  $pat = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/';
  return ereg("^[0-9]{4}-[01][0-9]-[0-3][0-9]$", $news_date​ );
}

/**
 * validate date
 *
 * @$news_date = array(YYYY,MM,DD)
 */
function _ifplugin_validate_valid_date( $news_date = array() ) {
  $month = $news_date[1];
  $day = $news_date[2];
  $year = $news_date[0];
  return  (bool)checkdate ( (int) $month , (int) $day , (int) $year );

}

/**
 * Validate format and date
 */
function _ifplugin_check_date( $postedDate ) {
   if ( preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/',$postedDate) ) {
      list( $year , $month , $day ) = explode('-',$postedDate);
      return( checkdate( $month , $day , $year ) );
   } else {
      return( false );
   }
}


function ifplugin_set_news_error( $op = '' ) {
  $msg = __('News not published! Saved has a draft.');
  $msg .= '<br />'; 
  switch ($op) {
    case 'valid':
      $msg .= __('News Date field must be a valid date','ifplugin');
    break;
    case 'format':
      $msg .= __('News Date field must be in the right format: YYYY-MM-DD','ifplugin');
    break;
    default :
      $msg .= __('News Date field must be in the right format: YYYY-MM-DD and a valid date','ifplugin');
  }
  
  
  add_settings_error(
    'news-date-misformatted',
    'news-date-misformatted',
    $msg,
    'error'
  );
  set_transient( 'news_date_errors', get_settings_errors(), 3600 );
}

/**
* Writes an error message to the screen if the 'Plan' meta data is not specified for the current
* post.
*
* @since 1.0.0
*/
add_action( 'admin_notices', '_news_date_admin_notices' );
function _news_date_admin_notices() {
  // If there are no errors, then we'll exit the function
  if ( ! ( $errors = get_transient( 'news_date_errors' ) ) ) {
    return;
  }
  // Otherwise, build the list of errors that exist in the settings errors
  $message = '<div id="ifplugin-message" class="error is-dismissible"><p><ul>';
  foreach ( $errors as $error ) {
    $message .= '<li>' . $error['message'] . '</li>';
  }
  $message .= '</ul></p></div><!-- #error -->';
  // Write them out to the screen
  echo $message;
  // Clear and the transient and unhook any other notices so we don't see duplicate messages
  delete_transient( 'news_date_errors' );
  remove_action( 'admin_notices', '_news_date_admin_notices' );
}


add_filter('redirect_post_location','_ifplugin_redirect_location',10,2);
function _ifplugin_redirect_location($location,$post_id){
    //If post was published...
    if (isset($_POST['publish'])){
        //obtain current post status
        $status = get_post_status( $post_id );

        //The post was 'published', but if it is still a draft, display draft message (10).
        if($status=='draft')
            $location = add_query_arg('message', 10, $location);
    }

    return $location;
} 

/**
 * Add data for news for display in front end
 */
add_filter('if_event_data', 'ifplugin_data_news');
function ifplugin_data_news($data) {
  $pid = $data['post_id'];
  $type = get_post_type( $pid );
  
  if( 'news' == $type ) {
    $meta = get_post_meta($pid);
    $data['start'] = utf8_encode(strftime('%d %b',$meta['_ifp_news_date'][0]));
    $data['type'] = $type;
    $data['subhead'] = $meta['_ifp_news_subhead'][0];
  }
	return $data;
}

/**
 * Add stylesheet to the page
 */
add_action( 'wp_enqueue_scripts', 'ifplugin_stylesheet' );
function ifplugin_stylesheet() {
    wp_enqueue_style( 'ifplugin-style', plugins_url('ifplugin.css', __FILE__) );
}

/**
 * admin Pages for settings
 */
/*
add_action( 'admin_menu', 'wporg_custom_admin_menu' );
function wporg_custom_admin_menu() {
    add_options_page(
        __('IF News','iftheme'),
        __('IF News Settings','iftheme'),
        'manage_options',
        'news-ifplugin',
        'if_news_options_page'
    );
}

function if_news_options_page() {
    ?>
    <div class="wrap">
        <h2>My Plugin Options</h2>
        your form goes here
    </div>
    <?php
}
*/

/** end admin **/

/**
* Alter main query to be aware of our custom type
*/
/*
function ifplugin_news_posts ( $query ) {
  $default_types = get_post_types();
	if( $query->is_main_query() && !is_admin() ) {
    $post_types = $query->get('post_type');
    if( !$post_types || $post_types == 'post' ) $query->set('post_type', $default_types );
    elseif ( is_array($post_types) ) $query->set('post_type', array_merge($post_types, $default_types) );
	}
}
*/
//add_action( 'pre_get_posts', 'ifplugin_news_posts' );

