<?php

require WPML_TM_PATH . '/inc/translation-proxy/interfaces/TranslationProxy_Service_Interface.php';
require WPML_TM_PATH . '/inc/translation-proxy/interfaces/TranslationProxy_Api_Interface.php';
require WPML_TM_PATH . '/inc/translation-proxy/interfaces/TranslationProxy_Project_Interface.php';
require WPML_TM_PATH . '/inc/translation-jobs/wpml-translation-batch.class.php';
require WPML_TM_PATH . '/inc/translation-jobs/wpml-translation-job-factory.class.php';

/**
 * @package wpml-core
 * @subpackage wpml-core
 */
interface TranslationProxy_Interface {
	public static function create_project( $service, $settings );

	public static function troubleshoot_service( $service, $delivery = 0 );

	public static function get_default_service();

	public static function get_services();

	public static function select_translation_service( $service_id );

	public static function is_batch_mode();

	public static function get_quote_form( $settings, $language_pairs, $word_count, $description );

	public static function get_existing_project( $settings );

	public static function get_custom_html( $project, $location, $locale, $popup_link, $max_count = 1000, $paragraph = true );

	public static function get_reminders( $settings, $project, $popup_link, $refresh );

	public static function get_service( $service_id );
}