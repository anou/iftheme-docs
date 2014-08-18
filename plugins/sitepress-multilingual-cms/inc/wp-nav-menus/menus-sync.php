<?php

class ICLMenusSync
{

	var $menus;
	var $is_preview = false;
	var $sync_data = false;
	var $string_translation_links = array();

	function __construct()
	{
		add_action( 'init', array( $this, 'init' ), 20 );

		if ( isset( $_GET[ 'updated' ] ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
	}

	function init()
	{
		$this->get_menus_tree();

		if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'icl_msync_preview' ) {
			$this->is_preview = true;
			$this->sync_data  = isset( $_POST[ 'sync' ] ) ? $_POST[ 'sync' ] : false;
		} elseif ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'icl_msync_confirm' ) {
			$this->do_sync( $_POST[ 'sync' ] );
		}
	}

	function get_menus_tree()
	{
		global $sitepress, $wpdb;

		$menus = $wpdb->get_results( $wpdb->prepare( "
            SELECT tm.term_id, tm.name FROM {$wpdb->terms} tm 
                JOIN {$wpdb->term_taxonomy} tx ON tx.term_id = tm.term_id
                JOIN {$wpdb->prefix}icl_translations tr ON tr.element_id = tx.term_taxonomy_id AND tr.element_type='tax_nav_menu'
            WHERE tr.language_code=%s
        ", $sitepress->get_default_language() ) );

		if ( $menus ) {

			$menu_options = get_option( 'nav_menu_options' );

			foreach ( $menus as $menu ) {
				$this->menus[ $menu->term_id ] = array(
					'name'         => $menu->name,
					'items'        => $this->get_menu_items( $menu->term_id, true ),
					'translations' => $this->get_menu_translations( $menu->term_id ),
					'auto_add'     => isset( $menu_options[ 'auto_add' ] ) && in_array( $menu->term_id, $menu_options[ 'auto_add' ] )
				);
			}

			$this->add_ghost_entries();
			$this->set_new_menu_order();
		}

	}

	function get_menu_items( $menu_id, $translations = true )
	{
		$items = wp_get_nav_menu_items( $menu_id );

		$menu_items = array();

		foreach ( $items as $item ) {
			$item->object_type = get_post_meta( $item->ID, '_menu_item_type', true );
			$_item_add         = array(
				'ID'          => $item->ID,
				'menu_order'  => $item->menu_order,
				'parent'      => $item->menu_item_parent,
				'object'      => $item->object,
				'object_type' => $item->object_type,
				'object_id'   => $item->object_id,
				'title'       => $item->title,
				'depth'       => $this->get_menu_item_depth( $item->ID )
			);

			if ( $translations ) {
				$_item_add[ 'translations' ] = $this->get_menu_item_translations( $item, $menu_id );
			}
			$menu_items[ $item->ID ] = $_item_add;
		}

		return $menu_items;

	}

	function get_menu_item_depth( $item_id )
	{

		$depth = 0;

		do {
			$object_parent = get_post_meta( $item_id, '_menu_item_menu_item_parent', true );

			if ( $object_parent == $item_id )
				return 0;

			if ( $object_parent ) {
				$item_id = $object_parent;
				$depth++;
			}

		} while ( $object_parent > 0 );

		return $depth;
	}

	function get_menu_item_translations( $item, $menu_id )
	{
		global $sitepress;

		$current_language = $sitepress->get_current_language();
		$languages        = $sitepress->get_active_languages();

		$translations = array();

		$trid              = $sitepress->get_element_trid( $item->ID, 'post_nav_menu_item' );
		$item_translations = $sitepress->get_element_translations( $trid, 'post_nav_menu_item', true );

		foreach ( $languages as $language ) {
			if ( $language[ 'code' ] != $sitepress->get_default_language() ) {

				$translation = false;

				if ( $item->object_type == 'custom' ) {
					$element_type = 'nav_menu_item';
				} else {
					$element_type = $item->object;
				}
				// Does the object of this item have a translation?
				$translated_object_id = icl_object_id( $item->object_id, $element_type, false, $language[ 'code' ] );
				if ( !isset( $translated_object_id ) || $translated_object_id == null ) {
					$translated_object_id = false;
				}

				// Is the object corresponding to the parent item translated?
				$parent_not_translated = 0;
				if ( $item->menu_item_parent > 0 ) {
					$item_parent_object_id = get_post_meta( $item->menu_item_parent, '_menu_item_object_id', true );
					$item_parent_object    = get_post_meta( $item->menu_item_parent, '_menu_item_object', true );

					$parent_element_type = $item_parent_object;
					if ( $item_parent_object == 'custom' ) {
						$parent_element_type = 'nav_menu_item';
					}
					$parent_translated = icl_object_id( $item_parent_object_id, $parent_element_type, false, $language[ 'code' ] );
					if ( empty( $parent_translated ) ) {
						$parent_not_translated = 1;
					}
				}

				if ( $translated_object_id || $item->object_type == 'custom' ) {

					$translated_object_title = '';
					$translated_object_url   = $item->url;

					$icl_st_label_exists = true;
					$icl_st_url_exists   = true;
					$label_changed       = false;
					$url_changed         = false;

					if ( $item->object_type == 'post_type' ) {
						$translated_object = get_post( $translated_object_id );
						if ( $translated_object->post_status == 'trash' ) {
							$translated_object_id = $translated_object = false;
						} else {
							$translated_object_title = $translated_object->post_title;
						}
					} elseif ( $item->object_type == 'taxonomy' ) {
						$taxonomy                = get_post_meta( $item->ID, '_menu_item_object', true );
						$translated_object       = get_term( $translated_object_id, $taxonomy );
						$translated_object_title = $translated_object->name;
					} elseif ( $item->object_type == 'custom' ) {

						$translated_object_title = $item->post_title;
						$translated_object_url   = $item->url;

						if ( defined( 'WPML_ST_PATH' ) ) {
							if ( !function_exists( 'icl_translate' ) ) {
								require WPML_ST_PATH . '/inc/functions.php';
							}

							$sitepress->switch_lang( $language[ 'code' ], false );

							$menu_name = $this->get_menu_name( $menu_id );

							$translated_object_title_t = '';
							$translated_object_url_t = '';

							if(function_exists('icl_t') && $this->string_translation_default_language_ok()) {
								$translated_object_title_t = icl_t( $menu_name . ' menu', 'Menu Item Label ' . $item->ID, $item->post_title, $icl_st_label_exists, true );
								$translated_object_url_t   = icl_t( $menu_name . ' menu', 'Menu Item URL ' . $item->ID, $item->url, $icl_st_url_exists, true );
							} elseif($translated_object_id && isset($item_translations[$language[ 'code' ]])) {
								$translated_menu_id = $this->get_translated_menu_id($menu_id, $language[ 'code' ]);
								$translated_menu_items = wp_get_nav_menu_items($translated_menu_id);
								$translated_menu_item_found = false;
								foreach($translated_menu_items as $translated_menu_item) {
									if($translated_menu_item->ID == $translated_object_id) {
										$translated_object_title_t = $translated_menu_item->title;
										$translated_object_url_t   = $translated_menu_item->url;
										$translated_menu_item_found = true;
										break;
									}
								}
								if(!$translated_menu_item_found) {
									$translated_object_title_t = $item->post_title . ' @' . $language[ 'code' ];
									$translated_object_url_t   = $item->url;
								}
							} else {
								$translated_object_title_t = $item->post_title . ' @' . $language[ 'code' ];
								$translated_object_url_t   = $item->url;
							}

							$sitepress->switch_lang( $current_language, false );

							if ( $translated_object_id ) {
								$translated_object      = get_post( $translated_object_id );
								$translated_object->url = get_post_meta( $translated_object_id, '_menu_item_url', true );

								if($this->string_translation_default_language_ok()) {
									$label_changed = ( $translated_object_title_t != $translated_object->post_title );
									$url_changed   = ( $translated_object_url_t != $translated_object->url );
								}

								if ( $icl_st_label_exists )
									$translated_object_title = $translated_object_title_t;
								if ( $icl_st_url_exists )
									$translated_object_url = $translated_object_url_t;
							}
						}
					}

					$translated_item_id = false;
					if ( isset( $item_translations[ $language[ 'code' ] ] ) ) {
						$translated_item_id = $item_translations[ $language[ 'code' ] ]->element_id;
					}

					$menu_item_depth = $this->get_menu_item_depth( $translated_item_id );
					if ( $translated_item_id ) {

						$translated_item         = get_post( $translated_item_id ); // get details for item
						$translated_object_title = !empty( $translated_item->post_title ) && !$icl_st_label_exists ? $translated_item->post_title : $translated_object_title;

						$translate_item_parent_item_id = intval( get_post_meta( $translated_item_id, '_menu_item_menu_item_parent', true ) );
						if ( $item->menu_item_parent > 0 ) {
							$translate_item_parent_item_id_from_original = icl_object_id( $item->menu_item_parent, get_post_type( $item->menu_item_parent ), false, $language[ 'code' ] );
							if ( $translate_item_parent_item_id != $translate_item_parent_item_id_from_original ) {
								$translate_item_parent_item_id = 0;
								$menu_item_depth               = 0;
							}
						}

						$translation = array(
							'ID'                    => $translated_item_id,
							'menu_order'            => $translated_item->menu_order,
							'parent'                => $translate_item_parent_item_id,
							'parent_not_translated' => $parent_not_translated,
							'depth'                 => $menu_item_depth,

						);
					} elseif ( $item->object_type == 'custom' ) {
						$translation = array(
							'ID'                    => false,
							'menu_order'            => $item->menu_order,
							'parent'                => 0,
							'parent_not_translated' => $parent_not_translated,
							'depth'                 => $menu_item_depth,
						);
					} else {
						$translation = array(
							'ID'                    => false,
							'menu_order'            => 0,
							'parent'                => 0,
							'parent_not_translated' => $parent_not_translated
						);
					}

					$translation[ 'object' ]        = $item->object;
					$translation[ 'object_type' ]   = $item->object_type;
					$translation[ 'object_id' ]     = $translated_object_id;
					$translation[ 'title' ]         = $translated_object_title;
					$translation[ 'url' ]           = $translated_object_url;
					$translation[ 'target' ]        = $item->target;
					$translation[ 'classes' ]       = $item->classes;
					$translation[ 'xfn' ]           = $item->xfn;
					$translation[ 'attr-title' ]    = $item->attr_title;
					$translation[ 'label_changed' ] = $label_changed;
					$translation[ 'url_changed' ]   = $url_changed;
					$translation[ 'label_missing' ] = !$icl_st_label_exists;
					$translation[ 'url_missing' ]   = !$icl_st_url_exists;

					if ( $this->string_translation_default_language_ok() ) {
						$translation[ 'label_missing' ] = !$icl_st_label_exists;
						$translation[ 'url_missing' ]   = !$icl_st_url_exists;
					} else {
						$translation[ 'label_missing' ] = false;
						$translation[ 'url_missing' ]   = false;
					}
				}

				$translations[ $language[ 'code' ] ] = $translation;

			}
		}

		return $translations;
	}

	private function string_translation_default_language_ok() {
		static $result = null;
		if ( $result == null ) {
			global $sitepress, $sitepress_settings;
			$default_language           = $sitepress->get_default_language();
			$string_translation_enabled = isset( $sitepress_settings[ 'st' ] ) && class_exists( 'WPML_String_Translation' );
			$result                     = $string_translation_enabled && isset( $sitepress_settings[ 'st' ][ 'strings_language' ] ) && $sitepress_settings[ 'st' ][ 'strings_language' ] == $default_language;
		}

		return $result;
	}

	private function get_menu_name( $menu_id )
	{
		$menu = $this->get_translated_menu( $menu_id );
		if ( $menu ) {
			return $menu[ 'name' ];
		}

		return false;
	}

	/**
	 * @param $menu_id
	 * @param $language_code
	 *
	 * @return bool
	 */
	private function get_translated_menu( $menu_id, $language_code = false )
	{
		global $sitepress;
		if ( !$language_code ) {
			$language_code = $sitepress->get_default_language();
		}

		$menus = $this->get_menu_translations( $menu_id, true );
		foreach ( $menus as $code => $menu ) {
			if ( $language_code == $code ) {
				return $menu;
			}
		}

		return false;
	}

	/**
	 * @param  int $menu_id
	 * @param bool $include_original
	 *
	 * @return bool|array
	 */
	function get_menu_translations( $menu_id, $include_original = false )
	{
		global $sitepress, $wpdb;
		static $menu_translations;

		if ( isset( $menu_translations[ $menu_id ][ $include_original ? $include_original : 0 ] ) )
			return $menu_translations[ $menu_id ][ $include_original ? $include_original : 0 ];

		$menu_options = get_option( 'nav_menu_options' );
		$languages = $sitepress->get_active_languages();

		$translations = false;

		foreach ( $languages as $language ) {
			if ( $include_original || $language[ 'code' ] != $sitepress->get_default_language() ) {

				$menu_translated_id = icl_object_id( $menu_id, 'nav_menu', 0, $language[ 'code' ] );
				$menu_data          = array();
				if ( $menu_translated_id ) {

					$menu_query              = "
                        SELECT t.term_id, t.name, t.slug, t.term_group, x.term_taxonomy_id, x.taxonomy, x.description, x.parent, x.count
                        FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} x ON t.term_id = t.term_id AND x.taxonomy='nav_menu'
                        WHERE t.term_id = %d
                    ";

					$menu_object             = $wpdb->get_row( $wpdb->prepare( $menu_query, $menu_translated_id ) );
					$menu_data[ 'id' ]       = $menu_object->term_id;
					$menu_data[ 'name' ]     = $menu_object->name;
					$current_lang = $sitepress->get_current_language();

					$sitepress->switch_lang($language[ 'code' ], false);
					$menu_data[ 'items' ]    = $this->get_menu_items( $menu_translated_id, false, $menu_object->name );
					$sitepress->switch_lang($current_lang, false);
					$menu_data[ 'auto_add' ] = isset( $menu_options[ 'auto_add' ] ) && in_array( $menu_translated_id, $menu_options[ 'auto_add' ] );
				}
				$translations[ $language[ 'code' ] ] = $menu_data;
			}
		}

		$menu_translations[ $menu_id ][ $include_original ? $include_original : 0 ] = $translations;

		return $translations;
	}

	function add_ghost_entries()
	{

		if ( is_array( $this->menus ) ) {
			foreach ( $this->menus as $menu_id => $menu ) {

				foreach ( $menu[ 'translations' ] as $language => $tmenu ) {
					if ( !empty( $tmenu ) ) {
						foreach ( $tmenu[ 'items' ] as $titem ) {

							// has a place in the default menu?
							$exists = false;
							foreach ( $this->menus[ $menu_id ][ 'items' ] as $item ) {
								if ( $item[ 'translations' ][ $language ][ 'ID' ] == $titem[ 'ID' ] ) {
									$exists = true;
								}
							}

							if ( !$exists ) {
								$this->menus[ $menu_id ][ 'translations' ][ $language ][ 'deleted_items' ][ ] = array(
									'ID'         => $titem[ 'ID' ],
									'title'      => $titem[ 'title' ],
									'menu_order' => $titem[ 'menu_order' ]
								);
							}
						}
					}
				}
			}
		}
	}

	function set_new_menu_order()
	{

		if ( is_array( $this->menus ) ) {
			foreach ( $this->menus as $menu_id => $menu ) {
				$menu_index_by_lang = array();
				foreach ( $menu[ 'items' ] as $item_id => $item ) {
					foreach ( $item[ 'translations' ] as $language => $item_translation ) {
						if ( $item_translation[ 'ID' ] ) {
							$new_menu_order                                                                                    = empty( $menu_index_by_lang[ $language ] ) ? 1 : $menu_index_by_lang[ $language ] + 1;
							$menu_index_by_lang[ $language ]                                                                   = $new_menu_order;
							$this->menus[ $menu_id ][ 'items' ][ $item_id ][ 'translations' ][ $language ][ 'menu_order_new' ] = $new_menu_order;
						}
					}
				}
			}
		}

	}

	function do_sync( $data )
	{
		global $wpdb, $sitepress;
		$current_language = $sitepress->get_current_language();
		$default_language = $sitepress->get_default_language();

		// menu translations
		if ( !empty( $data[ 'menu_translation' ] ) ) {
			foreach ( $data[ 'menu_translation' ] as $menu_id => $translations ) {
				foreach ( $translations as $language => $name ) {

					$_POST[ 'icl_translation_of' ]    = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy='nav_menu'", $menu_id ) );
					$_POST[ 'icl_nav_menu_language' ] = $language;

					$menu_indentation = '';
					$menu_increment = 0;
					do {
						$new_menu_id = wp_update_nav_menu_object( 0, array( 'menu-name' => $name . $menu_indentation . $menu_increment ) );
						$menu_increment        = $menu_increment != '' ? $menu_increment + 1 : 2;
						$menu_indentation        = '-';
					} while ( is_wp_error( $new_menu_id ) && $menu_increment < 10 );

					$this->menus[ $menu_id ][ 'translations' ][ $language ] = array(
						'id' => $new_menu_id
					);

				}
			}

		}

		// menu options
		if ( !empty( $data[ 'menu_options' ] ) ) {
			foreach ( $data[ 'menu_options' ] as $menu_id => $translations ) {
				foreach ( $translations as $language => $option ) {
					$translated_menu_id = $this->get_translated_menu_id($menu_id, $language);

					foreach ( $option as $key => $value ) {
						switch ( $key ) {
							case 'auto_add':

								// Store 'auto-add' pages.
								$auto_add        = $value;
								$nav_menu_option = (array)get_option( 'nav_menu_options' );
								if ( !isset( $nav_menu_option[ 'auto_add' ] ) )
									$nav_menu_option[ 'auto_add' ] = array();
								if ( $auto_add ) {
									if ( !in_array( $translated_menu_id, $nav_menu_option[ 'auto_add' ] ) )
										$nav_menu_option[ 'auto_add' ][ ] = $translated_menu_id;
								} else {
									if ( false !== ( $key = array_search( $translated_menu_id, $nav_menu_option[ 'auto_add' ] ) ) )
										unset( $nav_menu_option[ 'auto_add' ][ $key ] );
								}
								// Remove nonexistent/deleted menus
								$nav_menu_option[ 'auto_add' ] = array_intersect( $nav_menu_option[ 'auto_add' ], wp_get_nav_menus( array( 'fields' => 'ids' ) ) );
								update_option( 'nav_menu_options', $nav_menu_option );

								wp_defer_term_counting( false );

								do_action( 'wp_update_nav_menu', $translated_menu_id );

								break;
						}
					}

				}
			}

		}

		// deleting items
		if ( !empty( $data[ 'del' ] ) ) {
			foreach ( $data[ 'del' ] as $languages ) {
				foreach ( $languages as $items ) {
					foreach ( $items as $item_id => $name ) {
						wp_delete_post( $item_id, true );
						$delete_trid = $sitepress->get_element_trid( $item_id, 'post_nav_menu_item' );
						if ( $delete_trid ) {
							$sitepress->delete_element_translation( $delete_trid, 'post_nav_menu_item' );
						}
					}
				}
			}
		}

		// moving items
		if ( !empty( $data[ 'mov' ] ) ) {

			foreach ( $data[ 'mov' ] as $menu_id => $items ) {
				foreach ( $items as $item_id => $changes ) {
					$trid = $sitepress->get_element_trid( $item_id, 'post_nav_menu_item' );
					if ( !$trid ) {
						$sitepress->set_element_language_details( $item_id, 'post_nav_menu_item', false, $default_language );
						$trid = $sitepress->get_element_trid( $item_id, 'post_nav_menu_item' );
					}

					foreach ( $changes as $language => $details ) {
						$translated_item_id = $this->menus[ $menu_id ][ 'items' ][ $item_id ][ 'translations' ][ $language ][ 'ID' ];

						$new_menu_order                                                                                = key( $details );
						$this->menus[ $menu_id ][ 'items' ][ $item_id ][ 'translations' ][ $language ][ 'menu_order' ] = $new_menu_order;

						$wpdb->update( $wpdb->posts,
									   array( 'menu_order' => $new_menu_order ),
									   array( 'ID' => $translated_item_id ) );

						$sitepress->set_element_language_details( $translated_item_id, 'post_nav_menu_item', $trid, $language );
					}
				}

			}

			// fix hierarchy
			foreach ( $data[ 'mov' ] as $menu_id => $items ) {
				foreach ( $items as $item_id => $changes ) {
					$parent_item = get_post_meta( $item_id, '_menu_item_menu_item_parent', true );

					foreach ( $changes as $language => $details ) {

						$translated_item_id             = $this->menus[ $menu_id ][ 'items' ][ $item_id ][ 'translations' ][ $language ][ 'ID' ];
						$translated_parent_menu_item_id = icl_object_id( $parent_item, get_post_type( $parent_item ), false, $language );

						if ( $translated_parent_menu_item_id == $translated_item_id ) {
							$translated_parent_menu_item_id = false;
						}
						update_post_meta( $translated_item_id, '_menu_item_menu_item_parent', $translated_parent_menu_item_id );
					}
				}
			}

		}

		// adding items
		if ( !empty( $data[ 'add' ] ) ) {
			foreach ( $data[ 'add' ] as $menu_id => $items ) {

				foreach ( $items as $item_id => $translations ) {
					$trid = $sitepress->get_element_trid( $item_id, 'post_nav_menu_item' );
					if ( !$trid ) {
						$sitepress->set_element_language_details( $item_id, 'post_nav_menu_item', false, $default_language );
						$trid = $sitepress->get_element_trid( $item_id, 'post_nav_menu_item' );
					}

					foreach ( $translations as $language => $name ) {
						$translated_object = $this->menus[ $menu_id ][ 'items' ][ $item_id ][ 'translations' ][ $language ];

						$menu_name = $this->get_menu_name( $menu_id );

						$object_type = $translated_object[ 'object_type' ];
						$object_title = $translated_object[ 'title' ];
						$object_url = $translated_object[ 'url' ];
						$icl_st_label_exists = false;
						$icl_st_url_exists = false;
						if($object_type == 'custom' && (function_exists('icl_t') || !$this->string_translation_default_language_ok())) {
							if ( function_exists( 'icl_t' ) && $this->string_translation_default_language_ok() ) {
								$sitepress->switch_lang( $language, false );
								$object_title = icl_t( $menu_name . ' menu', 'Menu Item Label ' . $item_id, $object_title, $icl_st_label_exists, false );
								$object_url   = icl_t( $menu_name . ' menu', 'Menu Item URL ' . $item_id, $object_url, $icl_st_url_exists, false );

								$sitepress->switch_lang( $current_language, false );

								if(!$icl_st_label_exists) icl_register_string($menu_name . ' menu', 'Menu Item Label ' . $item_id, $object_title);
								if(!$icl_st_url_exists) icl_register_string($menu_name . ' menu', 'Menu Item URL ' . $item_id, $object_url);
							} else {
								$object_title = $name;
							}
						}

						$menudata = array(
							'menu-item-db-id'       => 0,
							'menu-item-object-id'   => $translated_object[ 'object_id' ],
							'menu-item-object'      => $translated_object[ 'object' ],
							'menu-item-parent-id'   => 0, // we'll fix the hierarchy on a second pass
							'menu-item-position'    => 0,
							'menu-item-type'        => $object_type,
							'menu-item-title'       => $object_title,
							'menu-item-url'         => $object_url,
							'menu-item-description' => '',
							'menu-item-attr-title'  => $translated_object[ 'attr-title' ],
							'menu-item-target'      => $translated_object[ 'target' ],
							'menu-item-classes'     => ( $translated_object[ 'classes' ] ? implode( ' ', $translated_object[ 'classes' ] ) : '' ),
							'menu-item-xfn'         => $translated_object[ 'xfn' ],
							'menu-item-status'      => 'publish',
						);

						$translated_menu_id = $this->menus[ $menu_id ][ 'translations' ][ $language ][ 'id' ];

						remove_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ) ); // AVOID filtering to current language
						$translated_item_id = wp_update_nav_menu_item( $translated_menu_id, 0, $menudata );

						// set language explicitly since the 'wp_update_nav_menu_item' is still TBD
						$sitepress->set_element_language_details( $translated_item_id, 'post_nav_menu_item', $trid, $language );

						$menu_tax_id_prepared = $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy='nav_menu'", $translated_menu_id );
						$menu_tax_id = $wpdb->get_var( $menu_tax_id_prepared );

						if ( $translated_item_id && $menu_tax_id ) {
							$rel_prepared = $wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE object_id=%d AND term_taxonomy_id=%d", $translated_item_id, $menu_tax_id );
							$rel = $wpdb->get_var( $rel_prepared );
							if ( !$rel ) {
								$wpdb->insert( $wpdb->term_relationships, array( 'object_id' => $translated_item_id, 'term_taxonomy_id' => $menu_tax_id ) );
							}
						}

						$this->menus[ $menu_id ][ 'items' ][ $item_id ][ 'translations' ][ $language ][ 'ID' ] = $translated_item_id;
					}

				}

			}

			// set/fix hierarchy
			foreach ( $data[ 'add' ] as $menu_id => $items ) {
				foreach ( $items as $item_id => $translations ) {
					foreach ( $translations as $language => $name ) {

						$item_parent = $this->menus[ $menu_id ][ 'items' ][ $item_id ][ 'parent' ];
						if ( $item_parent ) {
							$parent_object_id = $this->menus[ $menu_id ][ 'items' ][ $item_parent ][ 'object_id' ];
							$parent_object    = $this->menus[ $menu_id ][ 'items' ][ $item_parent ][ 'object' ];
							$element_type     = $parent_object;
							if ( $this->menus[ $menu_id ][ 'items' ][ $item_parent ][ 'object_type' ] == 'custom' ) {
								$element_type = 'nav_menu_item';
							}
							$translated_parent_object_id = icl_object_id( $parent_object_id, $element_type, false, $language );

							if ( $translated_parent_object_id ) {
								$translated_parent_item_id = $wpdb->get_var( $wpdb->prepare( "
                                    SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_menu_item_object_id' AND meta_value=%d ORDER BY meta_id DESC LIMIT 1",
																							 $translated_parent_object_id ) );
								$translated_item_id        = $this->menus[ $menu_id ][ 'items' ][ $item_id ][ 'translations' ][ $language ][ 'ID' ];
								update_post_meta( $translated_item_id, '_menu_item_menu_item_parent', $translated_parent_item_id );
							}
						}
					}
				}
			}

		}

		// update strings: caption
		if ( !empty( $data[ 'label_changed' ] ) ) {
			foreach ( $data[ 'label_changed' ] as $languages ) {
				foreach ( $languages as $language => $items ) {
					foreach ( $items as $item_id => $name ) {
						$trid = $sitepress->get_element_trid( $item_id, 'post_nav_menu_item' );
						if ( $trid ) {
							$item_translations = $sitepress->get_element_translations( $trid, 'post_nav_menu_item', true );
							if ( isset( $item_translations[ $language ] ) ) {
								$translated_item_id          = $item_translations[ $language ]->element_id;
								$translated_item             = get_post( $translated_item_id );
								$translated_item->post_title = $name;
								wp_update_post( $translated_item );
							}
						}
					}
				}
			}
		}

		// update strings: url
		if ( !empty( $data[ 'url_changed' ] ) ) {
			foreach ( $data[ 'url_changed' ] as $languages ) {
				foreach ( $languages as $language => $items ) {
					foreach ( $items as $item_id => $url ) {
						$trid = $sitepress->get_element_trid( $item_id, 'post_nav_menu_item' );
						if ( $trid ) {
							$item_translations = $sitepress->get_element_translations( $trid, 'post_nav_menu_item', true );
							if ( isset( $item_translations[ $language ] ) ) {
								$translated_item_id = $item_translations[ $language ]->element_id;
								if ( $url ) {
									update_post_meta( $translated_item_id, '_menu_item_url', $url );
								}
							}
						}
					}
				}
			}
		}

		// add string to ST: caption
		if ( !empty( $data[ 'label_missing' ] ) ) {
			static $labels_to_add;
			if ( !isset( $labels_to_add ) )
				$labels_to_add = array();

			foreach ( $data[ 'label_missing' ] as $menu_id => $languages ) {
				foreach ( $languages as $items ) {
					foreach ( $items as $item_id => $name ) {
						if ( !in_array( $menu_id . '-' . $item_id, $labels_to_add ) ) {
							$item = get_post( $item_id );
							icl_register_string( $this->get_menu_name( $menu_id ) . ' menu', 'Menu Item Label ' . $item_id, $item->post_title );
							$labels_to_add[ ] = $menu_id . '-' . $item_id;
						}
					}
				}
			}
		}

		// add string to ST: url
		if ( !empty( $data[ 'url_missing' ] ) ) {
			static $urls_to_add;
			if ( !isset( $urls_to_add ) )
				$urls_to_add = array();

			foreach ( $data[ 'url_missing' ] as $menu_id => $languages ) {
				foreach ( $languages as $items ) {
					foreach ( $items as $item_id => $url ) {
						if ( !in_array( $menu_id . '-' . $item_id, $urls_to_add ) ) {
							icl_register_string( $this->get_menu_name( $menu_id ) . ' menu', 'Menu Item URL ' . $item_id, $url );
							$urls_to_add[ ] = $menu_id . '-' . $item_id;
						}
					}
				}
			}
		}

		// set menu order
		foreach ( $this->menus as $menu_id => $menu ) {

			$menu_index_by_lang = array();
			foreach ( $menu[ 'items' ] as $item_id => $item ) {
				foreach ( $item[ 'translations' ] as $language => $item_translation ) {
					if ( $item_translation[ 'ID' ] ) {
						$new_menu_order                  = empty( $menu_index_by_lang[ $language ] ) ? 1 : $menu_index_by_lang[ $language ] + 1;
						$menu_index_by_lang[ $language ] = $new_menu_order;
						if ( $new_menu_order != $this->menus[ $menu_id ][ 'items' ][ $item_id ][ 'translations' ][ $language ][ 'menu_order' ] ) {
							$this->menus[ $menu_id ][ 'items' ][ $item_id ][ 'translations' ][ $language ][ 'menu_order' ] = $new_menu_order;
							$wpdb->update( $wpdb->posts,
										   array( 'menu_order' => $this->menus[ $menu_id ][ 'items' ][ $item_id ][ 'translations' ][ $language ][ 'menu_order' ] ),
										   array( 'ID' => $item_translation[ 'ID' ] ) );
						}
					}
				}
			}
		}

		wp_redirect( admin_url( 'admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/menus-sync.php&updated=1' ) );
		exit;

	}

	function render_items_tree_default( $menu_id, $parent = 0, $depth = 0 )
	{
		global $sitepress;

		$need_sync = 0;
		$default_language = $sitepress->get_default_language();
		foreach ( $this->menus[ $menu_id ][ 'items' ] as $item ) {

			// deleted items #2 (menu order beyond)
			static $d2_items = array();
			$deleted_items = array();
			foreach ( $this->menus[ $menu_id ][ 'translations' ] as $language => $tmenu ) {

				if ( !isset( $d2_items[ $language ] ) )
					$d2_items[ $language ] = array();

				if ( !empty( $this->menus[ $menu_id ][ 'translations' ][ $language ][ 'deleted_items' ] ) ) {
					foreach ( $this->menus[ $menu_id ][ 'translations' ][ $language ][ 'deleted_items' ] as $deleted_item ) {
						if ( !in_array( $deleted_item[ 'ID' ], $d2_items[ $language ] ) && $deleted_item[ 'menu_order' ] > count( $this->menus[ $menu_id ][ 'items' ] ) ) {
							$deleted_items[ $language ][ ] = $deleted_item;
							$d2_items[ $language ][ ]      = $deleted_item[ 'ID' ];
						}
					}

				}
			}
			if ( $deleted_items ) {
				?>
				<tr>
					<td>&nbsp;</td>
					<?php foreach ( $sitepress->get_active_languages() as $language ): if ( $language[ 'code' ] == $default_language )
						continue; ?>
						<td>
							<?php if ( isset( $deleted_items[ $language[ 'code' ] ] ) ): ?>
								<?php $need_sync++; ?>
								<?php foreach ( $deleted_items[ $language[ 'code' ] ] as $deleted_item ): ?>
									<?php echo str_repeat( ' - ', $depth ) ?><span class="icl_msync_item icl_msync_del"><?php echo $deleted_item[ 'title' ] ?></span>
									<input type="hidden" name="sync[del][<?php echo $menu_id ?>][<?php echo $language[ 'code' ] ?>][<?php echo $deleted_item[ 'ID' ] ?>]" value="<?php echo esc_attr( $deleted_item[ 'title' ] ) ?>"/>
									<?php $this->operations[ 'del' ] = empty( $this->operations[ 'del' ] ) ? 1 : $this->operations[ 'del' ]++; ?>
									<br/>
								<?php endforeach; ?>
							<?php else: ?>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php
			}

			// show deleted item?
			static $mo_added = array();
			$deleted_items = array();
			foreach ( $this->menus[ $menu_id ][ 'translations' ] as $language => $tmenu ) {

				if ( !isset( $mo_added[ $language ] ) )
					$mo_added[ $language ] = array();

				if ( !empty( $this->menus[ $menu_id ][ 'translations' ][ $language ][ 'deleted_items' ] ) ) {
					foreach ( $this->menus[ $menu_id ][ 'translations' ][ $language ][ 'deleted_items' ] as $deleted_item ) {

						if ( !in_array( $item[ 'menu_order' ], $mo_added[ $language ] ) && $deleted_item[ 'menu_order' ] == $item[ 'menu_order' ] ) {
							$deleted_items[ $language ] = $deleted_item;
							$mo_added[ $language ][ ]   = $item[ 'menu_order' ];
							$need_sync++;
						}

					}
				}
			}

			if ( $deleted_items ) {
				?>
				<tr>
					<td>&nbsp;</td>
					<?php foreach ( $sitepress->get_active_languages() as $language ): if ( $language[ 'code' ] == $default_language )
						continue; ?>
						<td>
							<?php if ( isset( $deleted_items[ $language[ 'code' ] ] ) ): ?>
								<?php $need_sync++; ?>
								<?php echo str_repeat( ' - ', $depth ) ?><span class="icl_msync_item icl_msync_del"><?php echo $deleted_items[ $language[ 'code' ] ][ 'title' ] ?></span>
								<input type="hidden" name="sync[del][<?php echo $menu_id ?>][<?php echo $language[ 'code' ] ?>][<?php echo $deleted_items[ $language[ 'code' ] ][ 'ID' ] ?>]"
									   value="<?php echo esc_attr( $deleted_items[ $language[ 'code' ] ][ 'title' ] ) ?>"/>
								<?php $this->operations[ 'del' ] = empty( $this->operations[ 'del' ] ) ? 1 : $this->operations[ 'del' ]++; ?>
							<?php else: ?>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php
			}

			if ( $item[ 'parent' ] == $parent ) {
				?>
				<tr>
					<td><?php
						echo str_repeat( ' - ', $depth ) . $item[ 'title' ];
						?></td>
					<?php foreach ( $sitepress->get_active_languages() as $language ) {
						if ( $language[ 'code' ] == $default_language )
							continue; ?>
						<td>
							<?php
							$item_translation = $item[ 'translations' ][ $language[ 'code' ] ];
							echo str_repeat( ' - ', $depth );

							if ( !empty( $item_translation[ 'ID' ] ) ) {
								// item translation exists
								if ( $item_translation[ 'menu_order' ] != $item_translation[ 'menu_order_new' ] || $item_translation[ 'depth' ] != $item[ 'depth' ] ) { // MOVED
									echo '<span class="icl_msync_item icl_msync_mov">' . $item_translation[ 'title' ] . '</span>';
									echo '<input type="hidden" name="sync[mov][' . $menu_id . '][' . $item[ 'ID' ] . '][' . $language[ 'code' ] . '][' . $item_translation[ 'menu_order_new' ] . ']" value="' . esc_attr( $item_translation[ 'title' ] ) . '" />';
									$this->operations[ 'mov' ] = empty( $this->operations[ 'mov' ] ) ? 1 : $this->operations[ 'mov' ]++;
									$need_sync++;
								} elseif ( $item_translation[ 'label_missing' ] ) {
									$this->string_translation_links[$this->menus[$menu_id]['name']] = 1;
									// item translation does not exist but is a custom item that will be created
									echo '<span class="icl_msync_item icl_msync_label_missing">' . $item_translation[ 'title' ] . '</span>';
									echo '<input type="hidden" name="sync[label_missing][' . $menu_id . '][' . $item[ 'ID' ] . '][' . $language[ 'code' ] . ']" value="' . esc_attr( $item_translation[ 'title' ] ) . '" />';
									$this->operations[ 'label_missing' ] = empty( $this->operations[ 'label_missing' ] ) ? 1 : $this->operations[ 'label_missing' ]++;
									$need_sync++;
								} elseif ( $item_translation[ 'label_changed' ] ) {
									$this->string_translation_links[$this->menus[$menu_id]['name']] = 1;
									// item translation does not exist but is a custom item that will be created
									echo '<span class="icl_msync_item icl_msync_label_changed">' . $item_translation[ 'title' ] . '</span>';
									echo '<input type="hidden" name="sync[label_changed][' . $menu_id . '][' . $item[ 'ID' ] . '][' . $language[ 'code' ] . ']" value="' . esc_attr( $item_translation[ 'title' ] ) . '" />';
									$this->operations[ 'label_changed' ] = empty( $this->operations[ 'label_changed' ] ) ? 1 : $this->operations[ 'label_changed' ]++;
									$need_sync++;
								} elseif ( $item_translation[ 'url_missing' ] ) {
									$this->string_translation_links[$this->menus[$menu_id]['name']] = 1;
									// item translation does not exist but is a custom item that will be created
									echo '<span class="icl_msync_item icl_msync_url_missing">' . $item_translation[ 'url' ] . '</span>';
									echo '<input type="hidden" name="sync[url_missing][' . $menu_id . '][' . $item[ 'ID' ] . '][' . $language[ 'code' ] . ']" value="' . esc_attr( $item_translation[ 'url' ] ) . '" />';
									$this->operations[ 'url_missing' ] = empty( $this->operations[ 'url_missing' ] ) ? 1 : $this->operations[ 'url_missing' ]++;
									$need_sync++;
								} elseif ( $item_translation[ 'url_changed' ] ) {
									$this->string_translation_links[$this->menus[$menu_id]['name']] = 1;
									// item translation does not exist but is a custom item that will be created
									echo '<span class="icl_msync_item icl_msync_url_changed">' . $item_translation[ 'url' ] . '</span>';
									echo '<input type="hidden" name="sync[url_changed][' . $menu_id . '][' . $item[ 'ID' ] . '][' . $language[ 'code' ] . ']" value="' . esc_attr( $item_translation[ 'url' ] ) . '" />';
									$this->operations[ 'url_changed' ] = empty( $this->operations[ 'url_changed' ] ) ? 1 : $this->operations[ 'url_changed' ]++;
									$need_sync++;
								} else { // NO CHANGE
									echo $item_translation[ 'title' ];
								}

							} elseif ( $item_translation[ 'object_type' ] == 'custom' ) {
								// item translation does not exist but is a custom item that will be created
								echo '<span class="icl_msync_item icl_msync_add">' . $item_translation[ 'title' ] . ' @' .  $language[ 'code' ] . '</span>';
								echo '<input type="hidden" name="sync[add][' . $menu_id . '][' . $item[ 'ID' ] . '][' . $language[ 'code' ] . ']" value="' . esc_attr( $item_translation[ 'title' ] . ' @' .  $language[ 'code' ] ) . '" />';
								$this->operations[ 'add' ] = empty( $this->operations[ 'add' ] ) ? 1 : $this->operations[ 'add' ]++;
								$need_sync++;
							} elseif ( !empty( $item_translation[ 'object_id' ] ) ) {
								// item translation does not exist but translated object does
								if ( $item_translation[ 'parent_not_translated' ] ) {
									echo '<span class="icl_msync_item icl_msync_not">' . $item_translation[ 'title' ] . '</span>';
									$this->operations[ 'not' ] = empty( $this->operations[ 'not' ] ) ? 1 : $this->operations[ 'not' ]++;
									$need_sync++;
								} else {
									$translated_id = icl_object_id( $item[ 'ID' ], 'nav_menu_item', false, $language[ 'code' ] );
									if ( !$translated_id ) {
										// item translation does not exist but translated object does
										echo '<span class="icl_msync_item icl_msync_add">' . $item_translation[ 'title' ] . '</span>';
										echo '<input type="hidden" name="sync[add][' . $menu_id . '][' . $item[ 'ID' ] . '][' . $language[ 'code' ] . ']" value="' . esc_attr( $item_translation[ 'title' ] ) . '" />';
										$this->operations[ 'add' ] = empty( $this->operations[ 'add' ] ) ? 1 : $this->operations[ 'add' ]++;
										$need_sync++;
									}
								}
							} else {
								// item translation and object translation do not exist
								echo '<i class="inactive">' . __( 'Not translated', 'sitepress' ) . '</i>';
							}

							?>

						</td>
					<?php } ?>
				</tr>
				<?php

				if ( $this->_item_has_children( $menu_id, $item[ 'ID' ] ) ) {
					$need_sync += $this->render_items_tree_default( $menu_id, $item[ 'ID' ], $depth + 1 );
				}

			}

		}

		return $need_sync;
	}

	function _item_has_children( $menu_id, $item_id )
	{
		$has = false;
		foreach ( $this->menus[ $menu_id ][ 'items' ] as $item ) {
			if ( $item[ 'parent' ] == $item_id ) {
				$has = true;
			}
		}

		return $has;
	}

	function get_item_depth( $menu_id, $item_id )
	{
		$depth = 0;
		$parent = 0;

		do {
			foreach ( $this->menus[ $menu_id ][ 'items' ] as $item ) {
				if ( $item[ 'ID' ] == $item_id ) {
					$parent = $item[ 'parent' ];
					if ( $parent > 0 ) {
						$depth++;
						$item_id = $parent;
					} else {
						break;
					}
				}
			}
		} while ( $parent > 0 );

		return $depth;

	}

	function admin_notices()
	{
		echo '<div class="updated"><p>' . __( 'Menu(s) syncing complete.', 'sitepress' ) . '</p></div>';
	}

	private function get_translated_menu_id( $menu_id, $language_code )
	{
		static $item_ids;
		if ( isset( $item_ids[ $menu_id ][ $language_code ] ) )
			return $item_ids[ $menu_id ][ $language_code ];
		$menu = $this->get_translated_menu( $menu_id, $language_code );
		if ( $menu ) {
			$item_ids[ $menu_id ][ $language_code ] = $menu[ 'id' ];

			return $menu[ 'id' ];
		}

		return false;
	}

}

?>
