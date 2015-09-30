<?php

/**
 * Created by OnTheGo Systems
 */
class WPML_String_Translation_MO_Import {

	private $language;
	private $active_languages;
	private $version;
	private $user_messages        = array();
	private $user_errors          = array();
	private $added_translation    = array();
	private $updated_translations = 0;

	public function __construct( $mo_lang, $version ) {
		global $sitepress;

		$this->language         = $mo_lang;
		$this->active_languages = $sitepress->get_active_languages();
		$this->version          = $version;
	}

	/**
	 * Handles the current $_POST request and routes it to the correct response handling and renderer
	 */
	public function handle_request() {

		if ( isset( $_POST[ 'action' ] )
		     && $_POST[ 'action' ] == 'icl_admo_add_translations'
		     && wp_verify_nonce( $_POST[ '_wpnonce' ], 'icl_adm_save_translations' )
		) {
			if ( ! empty( $_POST[ 'add_new' ] ) ) {
				$this->save_translations_from_serialized_encoded_array( $_POST[ 'add_new' ] );
			}
			if ( ! empty( $_POST[ 'selected' ] ) ) {
				$this->update_translations_from_encoded_array( $_POST[ 'selected' ] );
			}
			$this->save_translations_to_db();
		}

		$html = $this->render_response();

		return $html;
	}

	/**
	 * @return string html of response to the current request
	 */
	private function render_response() {
		$html = '<div class="wrap">';
		$html .= ' <div id="icon-wpml" class="icon32"><br /></div>';
		$html .= '<h2>' . __( 'Auto-download WordPress translations', 'wpml-string-translation' ) . '</h2>';

		if ( $this->updated_translations > 0 || ! empty( $this->added_translation ) ) {
			$html .= $this->render_translations_updated_response();
		} elseif ( ! $this->version || ! isset( $this->active_languages[ $this->language ] ) ) {
			$html .= $this->render_missing_information_response();
		} elseif ( ! empty( $this->user_errors ) ) {
			$html .= $this->render_user_errors();
		} else {
			$downloaded_translations = $this->download_mo();
			if ( ! empty( $downloaded_translations ) ) {
				$this->render_downloaded_translations_summary();
				$html .= $this->render_add_mo_file_form( $downloaded_translations );
			} else {
				$html .= $this->render_nothing_to_be_added();
			}
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * @return string html of the output, in case no new strings to be added were found.
	 */
	private function render_nothing_to_be_added() {
		$html = '<p>' . __( 'There is nothing to be updated or to be added.', 'wpml-string-translation' ) . '</p>';

		$html .= '<p><a href="' . admin_url( 'admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/theme-localization.php' ) . '"';
		$html .= 'class="button-secondary">' . __( 'Check other languages', 'wpml-string-translation' ) . '</a></p>';

		return $html;
	}

	/**
	 * @param $updated_translations array of updated string translations
	 *
	 * @return string html of the table holding the updated string translations
	 */
	private function render_updated_translations_table( $updated_translations ) {

		$html = '<h3>' . sprintf( __( 'Updated translations (%d)', 'wpml-string-translation' ), count( $updated_translations ) ) . '</h3>';
		$html .= '<table id="icl_admo_list_table" class="widefat">';
		$html .= '<thead><tr>';
		$html .= '<th class="manage-column column-cb check-column" scope="col"><input type="checkbox" name="" value="" checked="checked" /></th>';
		$html .= '<th>' . __( 'String', 'wpml-string-translation' ) . '</th>';
		$html .= '<th style="text-align:center;">' . __( 'Existing translation', 'wpml-string-translation' ) . '</th>';
		$html .= '<th style="text-align:center;">' . __( 'New translation', 'wpml-string-translation' ) . '</th>';
		$html .= '</tr></thead>	<tbody>';

		foreach ( $updated_translations as $idx => $translation ) {
			$html .= '<tr><td class="column-cb">';
			$html .= '<input type="hidden" name="selected[' . $idx . ']" value="0" />';
			$html .= '<input type="checkbox" name="selected[' . $idx . ']" value="1"  checked="checked" />';
			$html .= '</td><td>';
			$html .= esc_html( $translation[ 'string' ] );
			$html .= '<input type="hidden" name="string[' . $idx . ']" value="' . base64_encode( $translation[ 'string' ] ) . '" />';
			$html .= '<input type="hidden" name="name[' . $idx . ']" value="' . base64_encode( $translation[ 'name' ] ) . '" />';
			$html .= '</td><td colspan="2">';
			$html .= wp_text_diff( $translation[ 'translation' ], $translation[ 'new' ] );
			$html .= '<input type="hidden" name="translation[' . $idx . ']" value="' . base64_encode( $translation[ 'new' ] ) . '" />';
			$html .= '</td></tr>';
		}

		$html .= '</tbody><tfoot><tr>';
		$html .= '<th class="manage-column column-cb check-column" scope="col"><input type="checkbox" name="" value="" checked="checked" /></th>';
		$html .= '<th>' . __( 'String', 'wpml-string-translation' ) . '</th>';
		$html .= '<th style="text-align:center;">' . __( 'Existing translation', 'wpml-string-translation' ) . '</th>';
		$html .= '<th style="text-align:center;">' . __( 'New translation', 'wpml-string-translation' ) . '</th>';
		$html .= '</tr></tfoot>';
		$html .= '</table>';

		return $html;
	}

	/**
	 * @param $new_translations array of new, to be imported, string translations
	 *
	 * @return string html of the table holding the new string translations
	 */
	private function render_new_translations_table( $new_translations ) {

		$html = '<h3>' . sprintf( __( 'New translations (%d)', 'wpml-string-translation' ), count( $new_translations ) ) . '</h3>';
		$html .= '<table class="widefat">';
		$html .= '<thead><tr>';
		$html .= '<th>' . __( 'String', 'wpml-string-translation' ) . '</th>';
		$html .= '<th>' . __( 'Translation', 'wpml-string-translation' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $new_translations as $idx => $translation ) {
			$html .= '<tr><td>' . esc_html( $translation[ 'string' ] ) . '</td>';
			$html .= '<td>' . esc_html( $translation[ 'new' ] ) . '&nbsp;</td></tr>';
		}

		$html .= '</tbody>';
		$html .= '<tfoot><tr>';

		$html .= '<th>' . __( 'String', 'wpml-string-translation' ) . '</th>';
		$html .= '<th>' . __( 'Translation', 'wpml-string-translation' ) . '</th>';

		$html .= '</tr></tfoot></table>';

		$html .= '<p>';

		$html .= '<label><input type="checkbox" name="add_new" value="' . base64_encode( serialize( $new_translations ) ) . '" checked="checked" />&nbsp;';
		$html .= __( 'Add the new translations.', 'wpml-string-translation' ) . '</label>';

		$html .= '</p>';

		return $html;
	}

	/**
	 * @param $downloaded_translations array holding information about the updated and new string translations in
	 *                                 the downloaded .mo files.
	 *
	 * @return string
	 */
	private function render_add_mo_file_form( $downloaded_translations ) {
		$html = '<form id="icl_admo_list" method="post" action="">';
		$html .= '<input type="hidden" name="action" value="icl_admo_add_translations" />';
		$html .= '<input type="hidden" name="language" value="' . $this->language . '" />';
		$html .= '<input type="hidden" name="version" value="' . $this->version . '" />';
		$html .= wp_nonce_field( 'icl_adm_save_translations', ' _wpnonce', true, false );

		if ( ! empty( $downloaded_translations[ 'updated' ] ) ) {
			$updated_translations = $downloaded_translations[ 'updated' ];
			$html .= $this->render_updated_translations_table( $updated_translations );
		}

		if ( ! empty( $downloaded_translations[ 'new' ] ) ) {
			$new_translations = $downloaded_translations[ 'new' ];
			$html .= $this->render_new_translations_table( $new_translations );
		}

		$html .= '<a name="adm-proceed"></a><p class="submit">';
		$html .= '<input type="submit" class="button-primary" value="' . esc_attr( 'Proceed', 'wpml-string-translation' ) . '" />&nbsp;';

		$html .= '<a class="button-secondary" href="' . admin_url( 'admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/theme-localization.php' ) . '">' . __( 'Cancel', 'wpml-string-translation' ) . '</a>';
		$html .= '</p></form>';

		return $html;
	}

	/**
	 * @return string html of a summary display of the numbers of updated and new string translations,
	 * after they have been imported.
	 */
	private function render_downloaded_translations_summary() {
		$number_of_new_strings     = isset( $translations[ 'new' ] ) ? count( $translations[ 'new' ] ) : 0;
		$number_of_updated_strings = isset( $translations[ 'updated' ] ) ? count( $translations[ 'updated' ] ) : 0;

		$link_to_bottom_of_page = '<a href="#adm-proceed">' . __( 'bottom of this page', 'wpml-string-translation' ) . '</a>';

		$html = '<br /><div class="icl_cyan_box">';
		$html .= sprintf( __( 'This update includes %d new strings and %d updated strings. You can review the strings below. Then, go to the %s and click on the Proceed button.', 'wpml-string-translation' ), $number_of_new_strings, $number_of_updated_strings, $link_to_bottom_of_page );
		$html .= '</div>';

		return $html;
	}

	/**
	 * @return string html containing the messages to be displayed to the user after successful import of new translations
	 */
	private function render_translations_updated_response() {
		$html = '<p><strong>' . __( 'Success!', 'wpml-string-translation' ) . '</strong></p>';
		foreach ( $this->user_messages as $message ) {
			$html .= '<p>' . $message . '</p>';
		}

		$html .= '<a href="' . admin_url( 'admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/theme-localization.php' );
		$html .= '" class="button-secondary">' . __( 'Check other languages', 'wpml-string-translation' ) . '</a>';

		return $html;
	}

	/**
	 * @return string html to be rendered in case the information in the $_REQUEST was not sufficient.
	 * Either due to a lack of providing a version or because of failing to provide the language of the to be imported
	 * strings.
	 */
	private function render_missing_information_response() {

		$html = '<div class="error"><p>';
		if ( ! $this->version ) {
			$html .= __( 'Missing version number for translation.', 'wpml-string-translation' );
		} elseif ( ! isset( $this->active_languages[ $this->language ] ) ) {
			$html .= sprintf( __( 'Invalid language: %s', 'wpml-string-translation' ), $this->$language );
		}
		$html .= '</p></div>';

		return $html;
	}

	/**
	 * @return string html of a div holding error messages, that came about during the import of string translations
	 */
	private function render_user_errors() {
		$html = '<div class="error below-h2">';
		foreach ( $this->user_errors as $user_error ) {
			$html .= '<p>' . $user_error . '</p>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * @return bool|array Either false, if downloading new translations fails, or an array holding newly downloaded
	 * string translations
	 */
	private function download_mo() {
		/** @var WPML_ST_MO_Downloader $WPML_ST_MO_Downloader */
		global $WPML_ST_MO_Downloader;

		$res = false;

		try {
			$WPML_ST_MO_Downloader->load_xml();
			$WPML_ST_MO_Downloader->get_translation_files();
			$version_projects = explode( ';', $this->version );
			$types            = array();
			foreach ( $version_projects as $project ) {
				$exp      = explode( '|', $project );
				$types[ ] = $exp[ 0 ];
			}
			$res = $WPML_ST_MO_Downloader->get_translations( $this->language, array( 'types' => $types ) );
		} catch ( Exception $error ) {
			$this->user_errors[ ] = $error->getMessage();
		}

		return $res;
	}

	/**
	 * @param $encoded_serialized_array string Base64 encoded serialized array of string translations
	 *                                  derived from a .mo file
	 *
	 * @return array An associative array holding the imported translations.
	 */
	public function save_translations_from_serialized_encoded_array( $encoded_serialized_array ) {

		$new_translations = unserialize( base64_decode( $encoded_serialized_array ) );
		$translations_add = array();

		foreach ( $new_translations as $tr ) {
			$translations_add[ ] = array(
				'string'      => $tr[ 'string' ],
				'translation' => $tr[ 'new' ],
				'name'        => $tr[ 'name' ]
			);
		}

		if ( ! empty( $translations_add ) ) {
			$this->user_messages[ ] = sprintf( _n( '%d new translation was added.', '%d new translations were added.', count( $translations_add ), 'wpml-string-translation' ), count( $translations_add ) );
		}

		$this->added_translation = array_merge( $this->added_translation, $translations_add );

		return $translations_add;
	}

	/**
	 * Wrapper for saving imported string translations to the database
	 * @return array|bool Either true on success or an array holding the errors that happened during saving the strings
	 * to the database
	 */
	public function save_translations_to_db() {
		/** @var WPML_ST_MO_Downloader $WPML_ST_MO_Downloader */
		global $WPML_ST_MO_Downloader;

		if ( $this->added_translation ) {
			$WPML_ST_MO_Downloader->save_translations( $this->added_translation, $this->language, $this->version );
			$res = true;
		} else {
			$this->user_errors[ ] = __( 'No action performed. Please select the strings that you need to update or add.', 'wpml-string-translation' );
			$res                  = $this->user_errors;
		}

		return $res;
	}

	/**
	 * @param $encoded_array array of base64 encoded string translations
	 *
	 * @return array An associative array holding the updated translations.
	 */
	public function update_translations_from_encoded_array( $encoded_array ) {
		$translations_add = array();

		$translations_updated = 0;
		foreach ( $encoded_array as $idx => $v ) {
			if ( ! empty( $v ) ) {
				$translations_add[ ] = array(
					'string'      => base64_decode( $_POST[ 'string' ][ $idx ] ),
					'translation' => base64_decode( $_POST[ 'translation' ][ $idx ] ),
					'name'        => base64_decode( $_POST[ 'name' ][ $idx ] )
				);
				$translations_updated ++;
			}
		}
		if ( $translations_updated ) {
			$this->user_messages[ ] = sprintf( _n( '%d translation was updated.', '%d translations were updated.', $translations_updated, 'wpml-string-translation' ), $translations_updated );
		}

		$this->added_translation    = array_merge( $this->added_translation, $translations_add );
		$this->updated_translations = $translations_updated;

		return $translations_add;
	}

	/**
	 * Returns the HTML showing the .mo file options on the Plugins and Theme Localization Screen.
	 * @return string
	 */
	public static function render_settings_menu() {
		ICL_WP_Pointers::add( array( 'ICL_WP_Pointers', 'pointer_mo_auto_download_260' ) );
		$auto_download_mo = icl_get_sub_setting( 'st', 'auto_download_mo' );
		$dl_mo            = empty( $auto_download_mo );

		$output = '<br />';
		$output .= '<form id="icl_auto_download_mo" name="icl_auto_download_mo" method="post" action="">';
		$output .= '<input type="hidden" name="action" value="icl_adm_save_preferences" />';
		$output .= '<h3>' . __( 'Select how to get translations for WordPress core', 'wpml-string-translation' ) . '</h3>';
		$output .= wp_nonce_field( 'icl_auto_download_mo_nonce', '_icl_nonce', true, false );
		$output .= '<ul style="display:inline-block;padding:0;margin:0;" id="icl_adm_options"><li><label>';
		$output .= '<input type="radio" name="auto_download_mo" value="1"';
		if ( ! $dl_mo ) {
			$output .= ' checked="checked" ';
		}
		$output .= '/>';
		$output .= '&nbsp' . __( 'WPML will automatically download translations for WordPress', 'wpml-string-translation' );
		$output .= '</label></li><li><label>';
		$output .= '<input type="radio" name="auto_download_mo" value="0"';
		if ( $dl_mo ) {
			$output .= 'checked="checked"';
		}
		$output .= '/>';
		$output .= '&nbsp;' . __( 'I will download translations for WordPress and save .mo files in wp-content/languages', 'wpml-string-translation' );
		$output .= '</label></li></ul><p>';
		$output .= '<input class="button-secondary" type="submit" value="' . __( 'Save', 'wpml-string-translation' ) . '" />';
		$output .= '<span class="icl_ajx_response" id="icl_ajx_response2" style="display:inline"></span></p></form>';

		return $output;
	}
}