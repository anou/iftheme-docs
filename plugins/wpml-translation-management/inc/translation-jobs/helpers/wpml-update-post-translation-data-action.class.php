<?php
require_once WPML_TM_PATH . '/inc/translation-jobs/helpers/wpml-update-translation-data-action.class.php';

class WPML_TM_Update_Post_Translation_Data_Action extends WPML_TM_Update_Translation_Data_Action {

	protected function populate_prev_translation( $rid, array $package ) {
		global $wpml_post_translations;

		$prev_translation = array();
		if ( (bool) ( $lang = $this->get_lang_by_rid( $rid ) ) === true ) {
			$translated_post_id = $wpml_post_translations->element_id_in( $package['contents']['original_id']['data'],
			                                                              $lang );
			if ( (bool) $translated_post_id === true ) {
				$package_trans       = $this->package_helper->create_translation_package( $translated_post_id );
				$translated_contents = $package_trans['contents'];
				foreach ( $package['contents'] as $field_name => $field ) {
					if ( array_key_exists( 'translate', $field )
					     && (bool) $field['translate'] === true
					     && array_key_exists( $field_name, $translated_contents )
					     && array_key_exists( 'data', $translated_contents[ $field_name ] )
					) {
						$prev_translation[ $field_name ] = $translated_contents[ $field_name ]['data'];
					}
				}
			}
		}

		return $prev_translation;
	}
}