<?php

class WPML_TP_Download_Helper {

	/** @var  WPML_TM_CMS_ID $cms_id_helper */
	private $cms_id_helper;
	/** @var  WPML_Pro_Translation $pro_translation */
	private $pro_translation;

	/**
	 * @param WPML_TM_CMS_ID $cms_id_helper
	 * @param WPML_Pro_Translation $pro_translation
	 */
	function __construct( &$cms_id_helper, &$pro_translation ) {
		$this->cms_id_helper   = $cms_id_helper;
		$this->pro_translation = $pro_translation;
	}

	/**
	 * @param TranslationProxy_Project $project
	 *
	 * @return array
	 */
	function poll_for_translations( $project ) {
		/** @var WPML_String_Translation $WPML_String_Translation */
		global $sitepress, $WPML_String_Translation;

		$pending_jobs   = $project->pending_jobs();
		$cancelled_jobs = $project->cancelled_jobs();

		$results = array_fill_keys( array( 'completed', 'cancelled', 'errors' ), 0 );

		$posts_need_sync = array();
		if ( $pending_jobs ) {
			foreach ( $pending_jobs as $job ) {
				$ret = $this->pro_translation->download_and_process_translation( $job->id, $job->cms_id );
				if ( $ret ) {
					$results['completed'] ++;
					if ( $job->cms_id ) {
						list( , $id_to_sync ) = $this->cms_id_helper->parse_cms_id( $job->cms_id );
					} else {
						$id_to_sync = $job->id;
					}
					$posts_need_sync[] = $id_to_sync;
				}
			}
		}

		if ( ! empty( $cancelled_jobs ) ) {
			foreach ( $cancelled_jobs as $job ) {
				$ret = false;
				if ( $job->cms_id != "" ) {
					//we have a cms id for post translations
					$ret = $this->pro_translation->cancel_translation( $job->id, $job->cms_id );
					$ret = $ret ? 1 : 0;
				} else {
					//we only have an empty string here for string translations
					if ( isset( $WPML_String_Translation ) ) {
						$ret = isset( $job->id ) ? $WPML_String_Translation->cancel_remote_translation( $job->id ) : false;
					}
				}
				if ( $ret ) {
					$results['cancelled'] += $ret;
				}
			}
		}

		$sitepress->set_setting( 'last_picked_up', strtotime( current_time( 'mysql' ) ) );
		$sitepress->save_settings();
		$this->pro_translation->enqueue_project_errors( $project );
		do_action( 'wpml_new_duplicated_terms', $posts_need_sync, false );

		return $results;
	}
}