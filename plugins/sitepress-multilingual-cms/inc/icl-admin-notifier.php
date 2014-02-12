<?php
/**
 * Admin Notifier Class
 *
 * Manages Admin Notices
 *
 *
 */
class ICL_AdminNotifier
{

	public static function init( $auto_display_messages = false )
	{
		if ( is_admin() ) {
			if ( $auto_display_messages )
				add_action( 'admin_notices', array( __CLASS__, 'displayMessages' ), 5 );
			add_action( 'admin_head', array( __CLASS__, 'addScript' ) );
			add_action( 'wp_ajax_icl-hide-admin-message', array( __CLASS__, 'hideMessage' ) );
			//			add_action( 'wp_ajax_icl-hide-admin-message', array( __CLASS__, 'hideMessage' ) );
		}
	}

	public static function addScript()
	{
		wp_enqueue_script( 'icl-admin-notifier', ICL_PLUGIN_URL . '/res/js/icl-admin-notifier.js', array(), ICL_SITEPRESS_VERSION );
	}

	public static function addInstantMessage( $message, $type = '' )
	{
		$messages                          = self::getMessages();
		$messages[ 'instant_messages' ][ ] = array(
			'text' => $message,
			'type' => $type
		);
		self::saveMessages( $messages );
	}

	private static function getMessages()
	{
		$messages = get_option( 'icl_admin_messages' );
		if ( !( isset( $messages ) && $messages != false ) )
			return array( 'messages' => array(), 'instant_messages' => array() );
		if ( !isset( $messages[ 'messages' ] ) || !isset( $messages[ 'instant_messages' ] ) ) {
			$messages = array( 'messages' => array(), 'instant_messages' => array() );
		}

		return (array)$messages;
	}

	private static function saveMessages( $messages )
	{
		if ( isset( $messages ) ) {
			update_option( 'icl_admin_messages', (array)$messages );
		}
	}

	public static function addMessage( $id, $msg, $type = '', $hide = true, $message_fallback_when_hidden = false, $type_fallback_when_hidden = false, $group = false )
	{
		if ( !isset( $id ) || $id == null )
			return;

		$messages = self::getMessages();

		if ( !isset( $messages[ 'messages' ][ $id ] ) ) {
			$messages[ 'messages' ][ $id ] = array(
				'text'          => $msg,
				'type'          => $type,
				'hide'          => $hide,
				'text_fallback' => $message_fallback_when_hidden,
				'type_fallback' => $type_fallback_when_hidden,
				'group'         => $group,
			);
			self::saveMessages( $messages );
		}
	}

	public static function hideMessage()
	{
		$message_id = isset( $_POST[ 'icl-admin-message-id' ] ) ? $_POST[ 'icl-admin-message-id' ] : '';
		$message_id = preg_replace( '/^icl-id-/', '', $message_id );
		if ( !isset( $message_id ) )
			exit;

		$fallback = self::removeMessage( $message_id );

		if ( $fallback ) {
			echo json_encode( $fallback );
		}
		exit;
	}

	public static function removeMessage( $message_id )
	{
		if ( $message_id == null || !isset( $message_id ) )
			return false;

		$messages = self::getMessages();

		if ( !isset( $messages[ 'messages' ][ $message_id ] ) )
			return false;

		$has_fallback = false;
		if ( $messages[ 'messages' ][ $message_id ][ 'text_fallback' ] ) {
			$messages[ 'messages' ][ $message_id ][ 'text' ] = $messages[ 'messages' ][ $message_id ][ 'text_fallback' ];
			//if ( $messages[ 'messages' ][ $message_id ][ 'type_fallback' ] ) {
				$messages[ 'messages' ][ $message_id ][ 'type' ] = $messages[ 'messages' ][ $message_id ][ 'type_fallback' ];
			//}
			$messages[ 'messages' ][ $message_id ][ 'text_fallback' ] = false;
			$messages[ 'messages' ][ $message_id ][ 'type_fallback' ] = false;
			$messages[ 'messages' ][ $message_id ][ 'hide' ]          = false;

			$has_fallback = true;
		} else {
			unset( $messages[ 'messages' ][ $message_id ] );
		}

		self::saveMessages( $messages );

		if ( $has_fallback )
			return $messages[ 'messages' ][ $message_id ];

		return false;
	}

	public static function displayMessages( $group = false )
	{
		if ( current_user_can( 'manage_options' ) ) {
			$messages = self::getMessages();

			foreach ( $messages[ 'messages' ] as $id => $msg ) {
				if ( !$group || (isset($msg[ 'group' ]) && $msg[ 'group' ] == $group )) {
					self::displayMessage( $id, $msg[ 'text' ], $msg[ 'type' ], $msg[ 'hide' ] );
				}
			}
			foreach ( $messages[ 'instant_messages' ] as $msg ) {
				self::displayInstantMessage( $msg[ 'text' ], $msg[ 'type' ] );
			}
			// delete instant messages
			$messages[ 'instant_messages' ] = array();
			self::saveMessages( $messages );
		}
	}

	private static function displayMessage( $id, $message, $type = '', $hide = true )
	{
		if ( $type != 'error' ) {
			$type = $type ? 'icl-admin-message ' . $type : '';
			?>
			<div class="<?php echo $type; ?>" id='<?php echo "icl-id-" . $id; ?>'>
		<?php } else { ?>
			<div class="error icl-admin-message <?php echo $type; ?>" id='<?php echo "icl-id-" . $id; ?>'>
		<?php
		}
		echo '<p>' . stripslashes( $message );
		if ( $hide ) {
			echo ' <a href="#" class="icl-admin-message-hide">' . __( 'Dismiss', 'ICanLocalize' ) . '</a>';
		}
		echo '</p>';
		?>
		</div>
	<?php
	}

	private static function displayInstantMessage( $message, $type = '' )
	{
		if ( $type != 'error' ) {
			?>
			<div class="updated icl-admin-instant-message">
		<?php } else { ?>
			<div class="error icl-admin-instant-message">
		<?php
		}
		echo stripslashes( $message );
		?>
		</div>
	<?php
	}
}

ICL_AdminNotifier::init();
?>
