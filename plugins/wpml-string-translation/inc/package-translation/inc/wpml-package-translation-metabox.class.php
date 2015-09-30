<?php

class WPML_Package_Translation_Metabox {
	private $active_languages;
	private $container_attributes_html;
	private $dashboard_link;
	private $default_language;
	private $main_container_attributes;
	private $show_description;
	private $show_link;
	private $show_status;
	private $show_title;
	private $status_container_attributes;
	private $status_container_attributes_html;
	private $status_container_tag;
	private $status_element_tag;
	private $title_tag;

	public $metabox_data;

	/**
	 * @var SitePress
	 */
	private $sitepress;
	private $translation_statuses;
	/**
	 * @var WPDB
	 */
	private $wpdb;

	public function __construct( $package, $wpdb, $sitepress, $args = array() ) {

		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
		$this->package   = new WPML_Package( $package );

		if ( $this->got_package() ) {
			$this->dashboard_link = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&sm=dashboard&type=' . $this->package->kind_slug );
		}

		$this->parse_arguments( $args );
		$this->init_metabox_data();
	}

	private function init_metabox_data() {
		$this->metabox_data     = array();
		$this->active_languages = $this->sitepress->get_active_languages();
		$this->default_language = $this->sitepress->get_default_language();

		$this->metabox_data[ 'title' ]                  = __( 'WPML Translation', 'wpml-string-translation' );
		$this->metabox_data[ 'package_language_title' ] = sprintf( __( 'Language of this %s is %s', 'wpml-string-translation' ), $this->package->kind, $this->active_languages[ $this->default_language ][ 'display_name' ] );
		$this->metabox_data[ 'translate_title' ]        = sprintf( __( 'Send %s to translation', 'wpml-string-translation' ), $this->package->kind );

		if ( $this->got_package() ) {
			$this->metabox_data[ 'statuses_title' ] = __( 'Translation status:', 'wpml-string-translation' );
			$this->init_translation_statuses();
		} else {
			$this->metabox_data[ 'statuses_title' ] = __( 'There is nothing to translate.', 'wpml-string-translation' );
		}
	}

	function get_metabox() {
		$result = '';
		$result .= '<div ' . $this->container_attributes_html . '>';
		if ( $this->show_title ) {
			if ( $this->title_tag ) {
				$result .= $this->get_tag( $this->title_tag );
			}
			$result .= $this->metabox_data[ 'title' ];
			if ( $this->title_tag ) {
				$result .= $this->get_tag( $this->title_tag, 'closed' );
			}
		}
		if ( $this->show_description ) {
			$result .= '<p>' . $this->metabox_data[ 'package_language_title' ] . '</p>';
		}
		if ( $this->show_status ) {
			$result .= '<p>' . $this->metabox_data[ 'statuses_title' ] . '</p>';
		}

		if ( $this->got_package() ) {
			if ( $this->show_status && $this->metabox_data[ 'statuses' ] ) {
				if ( $this->status_container_tag ) {
					$result .= $this->get_tag( $this->status_container_tag . ' ' . $this->status_container_attributes_html );
				}
				foreach ( $this->metabox_data[ 'statuses' ] as $active_language => $status ) {
					$result .= $this->get_tag( $this->status_element_tag );
					$result .= $active_language . ' : ' . $status;
					$result .= $this->get_tag( $this->status_element_tag, 'closed' );
				}
				if ( $this->status_container_tag ) {
					$result .= $this->get_tag( $this->status_container_tag, 'closed' );
				}
			}
			if ( $this->show_link ) {
				$result .= '<p><a href="' . $this->dashboard_link . '" target="_blank">' . $this->metabox_data[ 'translate_title' ] . '</a></p>';
			}
		}
		$result .= '</div>';

		return $result;
	}

	/**
	 * @param $attributes
	 *
	 * @return string
	 */
	private function attributes_to_string( $attributes ) {
		$result = '';
		foreach ( $attributes as $key => $value ) {
			if ( $result ) {
				$result .= ' ';
			}
			$result .= esc_html( $key ) . '="' . esc_attr( $value ) . '"';
		}

		return $result;
	}

	function get_post_translations() {
		global $sitepress;

		$element_type = $this->package->get_package_element_type();
		$trid         = $sitepress->get_element_trid( $this->package->ID, $element_type );

		return $sitepress->get_element_translations( $trid, $element_type );
	}

	private function get_tag( $tag, $closed = false ) {
		$result = '<';
		if ( $closed ) {
			$result .= '/';
		}
		$result .= $tag . '>';

		return $result;
	}

	/**
	 * @param $args
	 */
	private function parse_arguments( $args ) {
		$default_args = array(
			'show_title'                  => true,
			'show_description'            => true,
			'show_status'                 => true,
			'show_link'                   => true,
			'title_tag'                   => 'h2',
			'status_container_tag'        => 'ul',
			'status_element_tag'          => 'li',
			'main_container_attributes'   => array(),
			'status_container_attributes' => array( 'style' => 'padding-left: 10px' ),
		);

		$args = array_merge( $default_args, $args );

		$this->show_title                  = $args[ 'show_title' ];
		$this->show_description            = $args[ 'show_description' ];
		$this->show_status                 = $args[ 'show_status' ];
		$this->show_link                   = $args[ 'show_link' ];
		$this->title_tag                   = $args[ 'title_tag' ];
		$this->status_container_tag        = $args[ 'status_container_tag' ];
		$this->status_element_tag          = $args[ 'status_element_tag' ];
		$this->main_container_attributes   = $args[ 'main_container_attributes' ];
		$this->status_container_attributes = $args[ 'status_container_attributes' ];

		$this->container_attributes_html        = $this->attributes_to_string( $this->main_container_attributes );
		$this->status_container_attributes_html = $this->attributes_to_string( $this->status_container_attributes );
	}

	/**
	 * @return bool
	 */
	private function got_package() {
		return $this->package && $this->package->ID;
	}

	private function get_translation_statuses() {
		$post_translations = $this->get_post_translations();
		$status            = array();
		foreach ( $post_translations as $language => $translation ) {
			$res_query   = "SELECT status, needs_update FROM {$this->wpdb->prefix}icl_translation_status WHERE translation_id=%d";
			$res_args    = array( $translation->translation_id );
			$res_prepare = $this->wpdb->prepare( $res_query, $res_args );
			$res         = $this->wpdb->get_row( $res_prepare );
			if ( $res ) {
				switch ( $res->status ) {
					case ICL_TM_WAITING_FOR_TRANSLATOR:
						$res->status = __( 'Waiting for translator', 'wpml-string-translation' );
						break;
					case ICL_TM_IN_PROGRESS:
						$res->status = __( 'In progress', 'wpml-string-translation' );
						break;
					case ICL_TM_NEEDS_UPDATE:
						$res->status = '';
						break;
					case ICL_TM_COMPLETE:
						$res->status = __( 'Complete', 'wpml-string-translation' );
						break;
					default:
						$res->status = __( 'Not translated', 'wpml-string-translation' );
						break;
				}

				if ( $res->needs_update ) {
					if ( $res->status ) {
						$res->status .= ' - ';
					}
					$res->status .= __( 'Needs update', 'wpml-string-translation' );
				}
				$status[ $language ] = $res;
			}
		}

		return $status;
	}

	private function init_translation_statuses() {
		$this->translation_statuses = $this->get_translation_statuses();
		foreach ( $this->active_languages as $language_data ) {
			if ( $language_data[ 'code' ] != $this->default_language ) {
				$display_name = $language_data[ 'display_name' ];

				$this->metabox_data[ 'statuses' ][ $display_name ] = $this->get_status_value( $language_data );
			}
		}
	}

	private function get_status_value( $language_data ) {
		if ( isset( $this->translation_statuses[ $language_data[ 'code' ] ] ) ) {
			$status_value = $this->translation_statuses[ $language_data[ 'code' ] ]->status;
		} else {
			$status_value = __( 'Not translated', 'wpml-string-translation' );
		}

		return $status_value;
	}
}