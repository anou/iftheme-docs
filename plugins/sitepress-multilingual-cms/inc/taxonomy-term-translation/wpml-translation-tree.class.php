<?php

class WPML_Translation_Tree {

	private $taxonomy;
	private $root_trid;
	private $tree;
	private $trid_levels;
	private $language_order;
	private $term_ids;

	//todo: implement the root functionality to improve performance in some situations
	/**
	 * @param string       $element_type
	 * @param bool         $root
	 * @param bool | array $terms
	 */
	public function __construct ( $element_type, $root = false, $terms = false ) {
		$this->taxonomy = $element_type;
		$this->root_trid = $root;
		$this->tree = false;

		if ( !$terms ) {
			$terms = $this->get_terms_by_taxonomy ( $element_type );
		}

		$this->language_order = $this->get_language_order ( $terms );
		$this->tree = $this->get_tree_from_terms_array ( $terms );
	}

	/**
	 * @param $taxonomy string
	 *                  Gets all the terms in a taxonomy together with their language information
	 *
	 * @return array|bool
	 */
	private function get_terms_by_taxonomy ( $taxonomy ) {
		global $wpdb;

		/* Get all the term objects */
		$terms_in_taxonomy = $wpdb->get_results (
			"SELECT icl_translations.element_id AS term_taxonomy_id,
					icl_translations.trid AS trid,
					icl_translations.language_code AS language_code,
					wp_tt.parent,
					wp_tt.term_id
			FROM {$wpdb->term_taxonomy} as wp_tt
			JOIN {$wpdb->prefix}icl_translations AS icl_translations
			ON concat('tax_',wp_tt.taxonomy) = icl_translations.element_type AND wp_tt.term_taxonomy_id = icl_translations.element_id
			WHERE wp_tt.taxonomy = '{$taxonomy}'" );

		return $terms_in_taxonomy;
	}

	/**
	 * @param $terms array
	 *
	 * Generates a tree representation of an array of terms objects
	 *
	 * @return array|bool
	 */
	private function get_tree_from_terms_array ( $terms ) {

		$trids = $this->generate_trid_groups ( $terms );

		$trid_tree = $this->parse_tree ( $trids, false, 0 );

		return $trid_tree;
	}

	/**
	 * @param $terms array
	 *
	 * Groups an array of terms objects by their trid and language_code
	 *
	 * @return array
	 */
	private function generate_trid_groups ( $terms ) {

		$trids = array();

		foreach ( $terms as $term ) {
			$trids [ $term->trid ] [ $term->language_code ] = array(
				'ttid'    => $term->term_taxonomy_id,
				'parent'  => $term->parent,
				'term_id' => $term->term_id,
			);

			if ( isset( $term->name ) ) {
				$trids [ $term->trid ] [ $term->language_code ][ 'name' ] = $term->name;
			}

			$this->term_ids[ ] = $term->term_id;
		}

		return $trids;
	}

	/**
	 * @param $trids           array
	 * @param $root_trid_group array| false
	 * @param $level           int current depth in the tree
	 *                         Recursively turns an array of unordered trid objects into a tree.
	 *
	 * @return array|bool
	 */
	private function parse_tree ( $trids, $root_trid_group, $level ) {
		/* Turn them into  an array of trees */
		$return = array();

		foreach ( $trids as $trid => $trid_group ) {
			if ( $this->is_root ( $trid_group, $root_trid_group ) ) {
				unset( $trids[ $trid ] );

				if ( !isset( $this->trid_levels[ $trid ] ) ) {
					$this->trid_levels[ $trid ] = 0;
				}
				$this->trid_levels[ $trid ] = max ( array( $level, $this->trid_levels[ $trid ] ) );
				$return [ $trid ] = array(
					'trid'     => $trid,
					'elements' => $trid_group,
					'children' => $this->parse_tree ( $trids, $trid_group, $level + 1 )
				);
			}
		}

		return empty( $return ) ? false : $return;
	}

	/**
	 * @param $parent array|bool
	 * @param $child  array
	 *                Checks if one trid is the root of another. This is the case if at least one parent child relationship between both trids exists.
	 *
	 * @return bool
	 */
	private function is_root ( $child, $parent ) {

		foreach ( $child as $c_lang => $child_in_lang ) {

			if ( $parent === false ) {
				if ( $child_in_lang[ 'parent' ] > 0 && in_array ( $child_in_lang[ 'parent' ], $this->term_ids ) ) {
					return false;
				}
			} else {

				foreach ( (array)$parent as $p_lang => $parent_in_lang ) {
					if ( $c_lang == $p_lang && $child_in_lang[ 'parent' ] == $parent_in_lang[ 'term_id' ] ) {
						return true;
					}
				}
			}
		}

		if ( $parent === false ) {
			return true;
		}

		return false;
	}

	/**
	 * @param $trid int
	 *              Returns the number of trids that hold a parent for the trid.
	 *
	 * @return int
	 */
	private function get_level_for_trid ( $trid ) {
		$level = 0;

		if ( isset( $this->trid_levels[ $trid ] ) ) {
			$level = $this->trid_levels[ $trid ];
		}

		return $level;
	}

	public function get_tree(){
		return $this->tree;
	}

	private function sort_trids_alphabetically( $trid_groups ) {

		$terms_in_trids = array();

		$ordered_trids = array();

		if ( ! is_array( $trid_groups ) ) {
			return $ordered_trids;
		}

		foreach ( $trid_groups as $trid_group ) {
			foreach ( $trid_group[ 'elements' ] as $lang => $term ) {
				if ( ! isset( $terms_in_trids[ $lang ] ) ) {
					$terms_in_trids[ $lang ] = array();
				}
				$trid                                = $trid_group[ 'trid' ];
				$term[ 'trid' ]                      = $trid;
				$terms_in_trids[ $lang ][ $trid ][ ] = $term;
			}
		}

		$sorted = array();
		foreach ( $this->language_order as $lang ) {

			if ( isset( $terms_in_trids[ $lang ] ) ) {
				$terms_in_lang_and_trids = $terms_in_trids[ $lang ];

				$term_names           = array();
				$term_names_numerical = array();
				foreach ( $terms_in_lang_and_trids as $trid => $terms_in_lang_and_trid ) {

					if ( in_array( $trid, $sorted ) ) {
						continue;
					}

					$term_in_lang_and_trid     = array_pop( $terms_in_lang_and_trid );
					$term_name                 = $term_in_lang_and_trid[ 'name' ];
					$term_names [ $term_name ] = $trid;
					$term_names_numerical [ ]  = $term_name;
				}

				natsort( $term_names_numerical );

				foreach ( $term_names_numerical as $name ) {
					$ordered_trids [ ] = $trid_groups[ $term_names[ $name ] ];
					$sorted[ ]         = $term_names[ $name ];
				}
			}
		}

		return $ordered_trids;
	}

	/**
	 * Returns all terms in the translation tree, ordered by hierarchy and as well as alphabetically within a level and/or parent term relationship.
	 *
	 * @return array
	 */
	public function get_alphabetically_ordered_list () {
		$root_list = $this->tree;

		$root_list = $this->sort_trids_alphabetically ( $root_list );

		$ordered_list_flattened = array();
		foreach ( $root_list as $root_trid_group ) {
			$ordered_list_flattened = $this->get_children_recursively ( $root_trid_group, $ordered_list_flattened );
		}

		return $ordered_list_flattened;
	}

	/**
	 * @param array $trid_group
	 * @param array $existing_list
	 *
	 * Reads in a trid array and appends it and its children to the input array.
	 * This is done in the order parent->alphabetically ordered children -> ( alphabetically ordered children's children) ...
	 *
	 * @return array
	 */
	private function get_children_recursively ( $trid_group, $existing_list = array() ) {

		$children = $trid_group[ 'children' ];

		unset( $trid_group[ 'children' ] );
		$existing_list [ ] = $this->add_level_information_to_terms ( $trid_group );

		if ( is_array ( $children ) ) {

			$children = $this->sort_trids_alphabetically ( $children );

			foreach ( $children as $child ) {
				$existing_list = $this->get_children_recursively ( $child, $existing_list );
			}
		}

		return $existing_list;
	}

	/**
	 * @param $tridgroup array
	 *
	 * Adds the hierarchical depth as a variable to all terms.
	 * 0 means, that the term has no parent.
	 *
	 * @return array
	 */
	private function add_level_information_to_terms ( $tridgroup ) {

		$level = $this->get_level_for_trid ( $tridgroup[ 'trid' ] );

		foreach ( $tridgroup[ 'elements' ] as &$term ) {
			$term[ 'level' ] = $level;
		}

		return $tridgroup;
	}

	/**
	 * @param $trid | int
	 * @param $node | array
	 *
	 * @return bool|array
	 */
	private function trid_to_tridgroup ( $trid, $node = false ) {

		if ( !$node ) {
			$node = $this->tree;
		}

		$children = isset( $node[ 'children' ] ) ? $node[ 'children' ] : $node;

		foreach ( (array)$children as $key => $tridgroup ) {
			if ( $key == $trid ) {
				return $tridgroup;
			} else {
				$return = $this->trid_to_tridgroup ( $trid, $children[ $key ] );
				if ( $return && isset( $return[ 'elements' ] ) ) {
					return $return;
				}
			}
		}

		return false;
	}

	/**
	 * @param $terms array
	 *
	 * Counts the number of terms per language and returns an array of language codes,
	 * that is ordered by the number of terms in every language.
	 *
	 * @return array
	 */
	private function get_language_order ( $terms ) {
		global $sitepress;

		$langs = array();

		$default_lang = $sitepress->get_default_language();
		foreach ( $terms as $term ) {
			$term_lang = $term->language_code;
			if($term_lang == $default_lang){
				continue;
			}
			if ( isset( $langs[ $term_lang ] ) ) {
				$langs[ $term_lang ] += 1;
			} else {
				$langs[ $term_lang ] = 1;
			}
		}

		natsort ( $langs );

		$return = array_keys($langs);
		$return [] = $default_lang;

		$return = array_reverse( $return );
		return $return;
	}

	/**
	 * @param  string $lang  Language in which the taxonomy from which this object was created should be synchronized.
	 * @param bool    $force If set to true, this will override the sitepress setting on  whether the hierarchy is to be
	 *                       synchronized between languages or not.
	 *
	 * @return bool
	 */
	public function sync_tree( $lang, $force = false ) {
		global $sitepress;

		if ( $sitepress->get_option( 'sync_taxonomy_parents' ) || $force ) {
			foreach ( (array) $this->tree as $element ) {
				$this->sync_subtree( $lang, $element );
			}
		}
		delete_option( 'category_children' );
		return true;
	}

	/**
	 * @param $lang
	 * @param $tree
	 * Helper function for sync_tree.
	 *
	 * @return bool
	 */
	private function sync_subtree ( $lang, $tree ) {
		global $wpdb;
		if ( isset( $tree[ 'children' ] ) && $tree[ 'children' ] ) {
			$children = $tree[ 'children' ];
		} else {
			return false;
		}
		if ( !is_array ( $children ) ) {
			return false;
		}

		foreach ( $children as $trid => $element ) {
			if ( isset( $tree[ 'elements' ][ $lang ][ 'ttid' ] ) && isset( $element[ 'elements' ][ $lang ] ) ) {
				$wpdb->update ( $wpdb->term_taxonomy, array( 'parent' => $tree[ 'elements' ][ $lang ][ 'term_id' ] ), array( 'term_taxonomy_id' => $element[ 'elements' ][ $lang ][ 'ttid' ] ) );
			}
			/* todo: update tree‚*/
			$this->sync_subtree ( $lang, $element );
		}

		return true;
	}

	/**
	 * @param $ttid int Taxonomy Term Id of the term in question
	 * @param $lang string Language of the term. Optional, but using it will improve the performance of this function.
	 *              Fetches the correct parent taxonomy_term_id even when it is not correctly assigned in the term_taxonomy wp core database yet.
	 *
	 * @return bool|int
	 */
	public function get_parent_for_ttid ( $ttid, $lang ) {

		if ( !is_array ( $this->tree ) ) {
			return false;
		}

		foreach ( $this->tree as $trid => $element ) {
			$res = $this->get_parent_from_subtree ( $ttid, $lang, $element );
			if ( $res ) {
				return $res;
			}
		}

		return false;
	}

	/**
	 * @param $ttid
	 * @param $lang
	 * @param $tree
	 * Helper function for get_parent_for_ttid.
	 *
	 * @return bool
	 */
	private function get_parent_from_subtree ( $ttid, $lang, $tree ) {
		if ( isset( $tree[ 'children' ] ) && $tree[ 'children' ] ) {
			$children = $tree[ 'children' ];
		} else {
			return false;
		}
		if ( !is_array ( $children ) ) {
			return false;
		}

		foreach ( $children as $trid => $element ) {
			if ( isset( $tree[ 'elements' ][ $lang ][ 'term_id' ] ) && isset( $element[ 'elements' ][ $lang ] ) && $ttid == $element[ 'elements' ][ $lang ][ 'ttid' ] ) {
				return $tree[ 'elements' ][ $lang ][ 'term_id' ];
			} else {
				$res = $this->get_parent_from_subtree ( $ttid, $lang, $element );
				if ( $res ) {
					return $res;
				}
			}
		}

		return false;
	}
}
