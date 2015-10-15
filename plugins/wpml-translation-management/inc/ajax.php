<?php
global $wpdb;

require WPML_TM_PATH . '/menu/basket-tab/wpml-basket-tab-ajax.class.php';

$basket_ajax = new WPML_Basket_Tab_Ajax( TranslationProxy::get_current_project(),
                                         wpml_tm_load_basket_networking(),
                                         new WPML_Translation_Basket( $wpdb ) );
add_action( 'init', array( $basket_ajax, 'init' ) );

function icl_get_jobs_table() {
	require_once WPML_TM_PATH . '/menu/wpml-translation-jobs-table.class.php';
	global $iclTranslationManagement;

	$nonce = filter_input( INPUT_POST, 'icl_get_jobs_table_data_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	if ( !wp_verify_nonce( $nonce, 'icl_get_jobs_table_data_nonce' ) ) {
		die( 'Wrong Nonce' );
	}

	$table = new WPML_Translation_Jobs_Table($iclTranslationManagement);
	$data  = $table->get_paginated_jobs();
	
	wp_send_json_success( $data );
}

function icl_get_job_original_field_content() {
    global $iclTranslationManagement;

    if ( !wpml_is_action_authenticated ( 'icl_get_job_original_field_content' ) ) {
        die( 'Wrong Nonce' );
    }

    $job_id = filter_input ( INPUT_POST, 'tm_editor_job_id', FILTER_SANITIZE_NUMBER_INT );
    $field = filter_input ( INPUT_POST, 'tm_editor_job_field' );
    $data = array();

    $job = $job_id !== null && $field !== null ? $job = $iclTranslationManagement->get_translation_job ( $job_id )
        : null;
    $elements = $job && isset( $job->elements ) ? $job->elements : array();

    foreach ( $elements as $element ) {
        $sanitized_type = sanitize_title ( $element->field_type );
        if ( $field === 'icl_all_fields' || $sanitized_type === $field ) {
            // if we find a field by that name we need to decode its contents according to its format
            $field_contents = $iclTranslationManagement->decode_field_data (
                $element->field_data,
                $element->field_format
            );
            if ( is_scalar ( $field_contents ) ) {
                $field_contents = strpos ( $field_contents, "\n" ) !== false ? wpautop ( $field_contents )
                                                                             : $field_contents;
                $data[ ] = array( 'field_type' => $sanitized_type, 'field_data' => $field_contents );
            }
        }
    }

    if ( (bool) $data !== false ) {
        wp_send_json_success ( $data );
    } else {
        wp_send_json_error ( 0 );
    }
}

function icl_populate_translations_pickup_box() {
	if ( !wpml_is_action_authenticated( 'icl_populate_translations_pickup_box' ) ) {
		die( 'Wrong Nonce' );
	}

	global $ICL_Pro_Translation, $sitepress;

	$last_picked_up     = $sitepress->get_setting( 'last_picked_up' );
	$translation_offset = strtotime( current_time( 'mysql' ) ) - @intval( $last_picked_up ) - 5 * 60;

	if ( WP_DEBUG == false && $translation_offset < 0 ) {
		$time_left = floor( abs( $translation_offset ) / 60 );
		if ( $time_left == 0 ) {
			$time_left = abs( $translation_offset );
			$wait_text = '<p><i>' . sprintf( __( 'You can check again in %s seconds.', 'sitepress' ), '<span id="icl_sec_tic">' . $time_left . '</span>' ) . '</i></p>';
		} else {
			$wait_text = sprintf( __( 'You can check again in %s minutes.', 'sitepress' ), '<span id="icl_sec_tic">' . $time_left . '</span>' ) . '</i></p>';
		}

		$result = array(
			'wait_text' => $wait_text,
		);
	} else {
        /** @var WPML_Pro_Translation $ICL_Pro_Translation */
		$job_in_progress       = $ICL_Pro_Translation->get_total_jobs_in_progress();
		$button_text           = __( 'Get completed translations', 'sitepress' );
		if ($job_in_progress == 1) {
			$jobs_in_progress_text = __( '1 job has been sent to the translation service.', 'sitepress' );
		} else {
			$jobs_in_progress_text = sprintf(__( '%d jobs have been sent to the translation service.', 'sitepress' ), $job_in_progress );
		}
		$last_picked_up        = $sitepress->get_setting( 'last_picked_up' );
		$last_time_picked_up   = ! empty( $last_picked_up ) ? date_i18n( 'Y, F jS @g:i a', $last_picked_up ) : __( 'never', 'sitepress' );
		$last_pickup_text      = sprintf( __( 'Last time translations were picked up: %s', 'sitepress' ), $last_time_picked_up );

		$result = array(
			'jobs_in_progress_text' => $jobs_in_progress_text,
			'button_text'           => $button_text,
			'last_pickup_text'      => $last_pickup_text
		);
	}

	wp_send_json_success( $result );
}

function icl_pickup_translations() {
	if ( !wpml_is_action_authenticated( 'icl_pickup_translations' ) ) {
		die( 'Wrong Nonce' );
	}

	/** @var WPML_Pro_Translation $ICL_Pro_Translation */
	global $ICL_Pro_Translation;

	$errors                  = '';
	$status_completed        = '';
	$status_cancelled        = '';
	$results = $ICL_Pro_Translation->poll_for_translations( true );

	if ( $results[ 'errors' ] ) {
		$status = __( 'Error', 'sitepress' );
		$errors = join( '<br />', $results[ 'errors' ] );
	} else {
		$status = __( 'OK', 'sitepress' );

		if ($results[ 'completed' ] == 1) {
			$status_completed = __( '1 translation has been fetched from the translation service.', 'sitepress' );
		} else {
			$status_completed = sprintf( __( '%d translations have been fetched from the translation service.', 'sitepress' ), $results[ 'completed' ] );
		}

		if ( $results[ 'cancelled' ] ) {
			$status_cancelled = sprintf( __( '%d translations have been marked as cancelled.', 'sitepress' ), $results[ 'cancelled' ] );
		}
	}
	$response = array(
		'status'           => $status,
		'errors'           => $errors,
		'completed'        => $status_completed,
		'cancelled'        => $status_cancelled,
	);

	wp_send_json_success( $response );
}

function icl_get_blog_users_not_translators() {
	$translator_drop_down_options = array();

	$nonce = filter_input( INPUT_POST, 'get_users_not_trans_nonce' );
	if ( !wp_verify_nonce( $nonce, 'get_users_not_trans_nonce' ) ) {
		die( 'Wrong Nonce' );
	}

	$blog_users_nt = TranslationManagement::get_blog_not_translators();

	foreach ( (array) $blog_users_nt as $u ) {
		$label                           = $u->display_name . ' (' . $u->user_login . ')';
		$value                           = esc_attr( $u->display_name );
		$translator_drop_down_options[ ] = array(
			'label' => $label,
			'value' => $value,
			'id'    => $u->ID
		);
	}

	wp_send_json_success( $translator_drop_down_options );
}

/**
 * Ajax handler for canceling translation Jobs.
 */
function icl_cancel_translation_jobs() {
	if ( !wpml_is_action_authenticated ( 'icl_cancel_translation_jobs' ) ) {
		die( 'Wrong Nonce' );
	}

	/** @var TranslationManagement $iclTranslationManagement */
	global $iclTranslationManagement;

	$job_ids = isset( $_POST[ 'job_ids' ] ) ? $_POST[ 'job_ids' ] : false;
	if ( $job_ids ) {
		foreach ( (array) $job_ids as $key => $job_id ) {
			$iclTranslationManagement->cancel_translation_request( $job_id );
		}
	}

	wp_send_json_success( $job_ids );
}
