<?php

class WPML_Package_ST {
	public function get_context_default_language( $context ) {
		return apply_filters( 'WPML_ST_strings_context_language', 'en', $context );
	}

	public function get_string_element( $string_id, $column = false ) {
		global $wpdb;

		$package_query   = "SELECT * FROM {$wpdb->prefix}icl_strings WHERE id=%d";
		$package_prepare = $wpdb->prepare( $package_query, array( $string_id ) );
		$result          = $wpdb->get_row( $package_prepare );

		if ( $result && $column && isset( $result[ $column ] ) ) {
			$result = $result[ $column ];
		}

		return $result;
	}


	public function get_string_title( $title, $string_details ) {
		$string_title = $this->get_string_element( $string_details[ 'string_id' ], 'title' );
		if ( $string_title ) {
			return $string_title;
		} else {
			return $title;
		}
	}

}