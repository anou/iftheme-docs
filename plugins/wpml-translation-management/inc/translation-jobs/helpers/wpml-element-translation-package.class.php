<?php

/**
 * Class WPML_Element_Translation_Package
 *
 * @package wpml-core
 */
class WPML_Element_Translation_Package extends WPML_Translation_Job_Helper{

	/**
	 * create translation package
	 *
	 * @param object|int $post
	 *
	 * @return array
	 */
	function create_translation_package( $post ) {
		global $sitepress;

		$package   = array();
		$post      = is_numeric( $post ) ? get_post( $post ) : $post;
		$post_type = $post->post_type;
		if ( apply_filters( 'wpml_is_external', false, $post ) ) {
			$post_contents = (array) $post->string_data;
			$original_id   = $post->post_id;
			$type          = 'external';
		} else {
			$home_url       = get_home_url();
			$package['url'] = htmlentities( $home_url . '?' . ( $post_type === 'page' ? 'page_id' : 'p' ) . '=' . ( $post->ID ) );

			$post_contents = array(
				'title'   => $post->post_title,
				'body'    => $post->post_content,
				'excerpt' => $post->post_excerpt
			);

			if ( wpml_get_setting_filter( false, 'translated_document_page_url' ) === 'translate' ) {
				$post_contents['URL'] = $post->post_name;
			}

			$original_id             = $post->ID;
			$cf_translation_settings = $this->get_tm_setting( array( 'custom_fields_translation' ) );
			if ( ! empty( $cf_translation_settings ) ) {
				$package = $this->add_custom_field_contents( $package,
				                                             $post,
				                                             $cf_translation_settings );
			}

			foreach ( (array) $sitepress->get_translatable_taxonomies( true, $post_type ) as $taxonomy ) {
				$terms = get_the_terms( $post->ID, $taxonomy );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$post_contents[ 't_' . $term->term_taxonomy_id ] = $term->name;
					}
				}
			}
			$type = 'post';
		}
		$package['contents']['original_id'] = array( 'translate' => 0, 'data' => $original_id );
		$package['type']                    = $type;
		foreach ( $post_contents as $key => $entry ) {
			$package['contents'][ $key ] = array(
				'translate' => 1,
				'data'      => base64_encode( $entry ),
				'format'    => 'base64'
			);
		}

		return $package;
	}

	/**
	 * @param array   $package
	 * @param object $post
	 * @param array   $fields
	 * @return array
	 */
	private function add_custom_field_contents( $package, $post, $fields ) {

		foreach ( $fields as $key => $op ) {
			if ( $op == 2 ) { // translate
				$custom_fields_values = array_values( array_filter( get_post_meta( $post->ID, $key ) ) );
				foreach ( $custom_fields_values as $index => $custom_field_val ) {
					if ( ! is_scalar( $custom_field_val ) ) {
						continue;
					}
					$key_index                  = $key . '-' . $index;
					$cf                         = 'field-' . $key_index;
					$package['contents'][ $cf ] = array(
							'translate' => 1,
							'data'      => base64_encode( $custom_field_val ),
							'format'    => 'base64'
					);
					foreach ( array( 'name' => $key_index, 'type' => 'custom_field' ) as $field_key => $setting ) {
						$package['contents'][ $cf . '-' . $field_key ] = array( 'translate' => 0, 'data' => $setting );
					}
				}
			}
		}

		return $package;
	}

	/**
	 * @param array $translation_package
	 * @param array $prev_translation
	 * @param int $job_id
	 */
	public function save_package_to_job( array $translation_package, $job_id, $prev_translation ) {
		global $wpdb;

		foreach ( $translation_package['contents'] as $field => $value ) {
			$job_translate = array(
					'job_id'                => $job_id,
					'content_id'            => 0,
					'field_type'            => $field,
					'field_format'          => isset( $value['format'] ) ? $value['format'] : '',
					'field_translate'       => $value['translate'],
					'field_data'            => $value['data'],
					'field_data_translated' => isset( $prev_translation[ $field ] ) ? $prev_translation[ $field ] : '',
					'field_finished'        => 0
			);

			$wpdb->hide_errors();
			$wpdb->insert( $wpdb->prefix . 'icl_translate', $job_translate );
		}
	}

	/**
	 * @param object $job
	 * @param int    $post_id
	 * @param array  $fields
	 */
	function save_job_custom_fields( $job, $post_id, $fields ) {
		$field_names = array();
		foreach ( $fields as $field_name => $val ) {
			if ( $val == 2 ) { // should be translated
				// find it in the translation
				foreach ( $job->elements as $el_data ) {
					if ( strpos( $el_data->field_data, (string) $field_name ) === 0
					     && preg_match( "/field-(.*?)-name/", $el_data->field_type, $match ) === 1
					) {
						$field_names[ $field_name ] = isset( $field_names[ $field_name ] )
								? $field_names[ $field_name ] : array();
						$field_id_string            = $match[1];
						$explode                    = explode( '-', $field_id_string );
						$sub_id                     = $explode[ count( $explode ) - 1 ];
						$field_translation          = false;
						foreach ( $job->elements as $v ) {
							if ( $v->field_type === 'field-' . $field_id_string ) {
								$field_translation = $this->decode_field_data(
										$v->field_data_translated,
										$v->field_format
								);
							}
							if ( $v->field_type === 'field-' . $field_id_string . '-type' ) {
								$field_type = $v->field_data;
							}
						}
						if ( $field_translation !== false && isset( $field_type ) && $field_type === 'custom_field' ) {
							$field_translation = str_replace( '&#0A;', "\n", $field_translation );
							// always decode html entities  eg decode &amp; to &
							$field_translation                     = html_entity_decode( $field_translation );
							$contents[ $sub_id ]                   = $field_translation;
							$field_names[ $field_name ][ $sub_id ] = $field_translation;
						}
					}
				}
			}
		}

		$this->save_custom_field_values( $field_names, $post_id );
	}

	private function save_custom_field_values( $field_names, $post_id ) {
		foreach ( $field_names as $name => $contents ) {
			delete_post_meta ( $post_id, $name );
			$single = count ( $contents ) === 1;
			foreach ( $contents as $val ) {
				add_post_meta ( $post_id, $name, $val, $single );
			}
		}
	}
}