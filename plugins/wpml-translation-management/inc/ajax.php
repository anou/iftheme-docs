<?php
function icl_get_job_original_field_content() {
	global $iclTranslationManagement;

	$error_msg = false;
	$job_id    = false;

	if ( isset( $_POST [ 'tm_editor_job_id' ] ) ) {
		$job_id = $_POST [ 'tm_editor_job_id' ];
	} else {
		$error_msg = "No job id provided.";
	}

	if ( ! $error_msg && isset( $_POST [ 'tm_editor_job_id' ] ) ) {
		$field = $_POST [ 'tm_editor_job_field' ];
	} else {
		$error_msg = "No field provided.";
	}

	if ( ! $error_msg && $job_id && isset($field) ) {
		$job = $iclTranslationManagement->get_translation_job( $job_id );

		if(isset($job->elements)) {
			foreach ( $job->elements as $element ) {
				if ( sanitize_title( $element->field_type ) == $field ) {
					// if we find a field by that name we need to decode its contents according to its format
					$field_contents = TranslationManagement::decode_field_data( $element->field_data, $element->field_format );
					wp_send_json_success( $field_contents );
				}
			}
		} elseif(!$job) {
			$error_msg = _("No translation job found: it might have been just cancelled.", 'wpml-translation-management');
		} else {
			$error_msg = _("No fields found in this translation job.", 'wpml-translation-management');
		}
	}
	if ( ! $error_msg ) {
		$error_msg = _("No such field found in the job.", 'wpml-translation-management');
	}

	wp_send_json_error( $error_msg );
}
