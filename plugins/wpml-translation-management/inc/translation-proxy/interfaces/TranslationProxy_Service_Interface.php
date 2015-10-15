<?php
/**
 * @package wpml-core
 * @subpackage wpml-core
 */
interface TranslationProxy_Service_Interface {

	public static function get_service( $service_id );

	public static function languages_map( $service );

	public static function list_services();

	public static function get_language( $service, $language );
}