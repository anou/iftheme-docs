<?php
/**
 * @package wpml-core
 * @subpackage wpml-core
 */
interface TranslationProxy_Api_Interface {
	public static function proxy_download( $path, $params );

	public static function service_request( $url, $params = array(), $method = 'GET', $multi_part = false );

	public static function proxy_request( $path, $params = array(), $method = 'GET', $multi_part = false );

	public static function add_parameters_to_url( $url, $params );
}