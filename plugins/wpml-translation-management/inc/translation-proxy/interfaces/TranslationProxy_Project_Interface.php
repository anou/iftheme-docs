<?php
/**
 * @package wpml-core
 * @subpackage wpml-core
 */
interface TranslationProxy_Project_Interface {
	public function send_to_translation( $file, $title, $cms_id, $url, $source_language, $target_language, $word_count, $translator_id = 0, $note = '', $is_update = 0 );

	public function translator_contact_iframe_url( $translator_id );

	public function test_affiliation( $key, $id );

	public static function test_xmlrpc( $url );

	public static function generate_service_index( $service );

	public function create_batch_job($source_language, $target_languages);

	public function select_translator_iframe_url( $source_language, $target_language );

	public function send_to_translation_batch_mode( $file, $title, $cms_id, $url, $source_language, $target_language, $word_count, $translator_id = 0, $note = '', $is_update = 0 );

	public function commit_batch_job();

	public function update_job( $job_id, $url = null, $state = 'delivered' );

	public function canceled_jobs();

	public function create( $url, $name, $description, $blog_id = 1, $delivery = 'xmlrpc', $affiliate_id = '', $affiliate_key = '', $dummy = false );

	public function custom_text( $location, $locale = "en" );

	public function fetch_translation( $job_id );

	public function pending_jobs();

	public function get_batch_job_id();

	public function jobs();

	public function set_delivery_method( $method );
}
