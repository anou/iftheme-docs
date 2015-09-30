<?php

class WPML_TM_Filters extends WPML_WPDB_And_SP_User {

	/**
	 * Filters the active languages to include all languages in which strings exist.
	 *
	 * @param array[] $source_langs
	 *
	 * @return array[]
	 */
	public function filter_tm_source_langs( $source_langs ) {

		$string_lang_codes = $this->wpdb->get_col( "	SELECT DISTINCT(s.language)
														FROM {$this->wpdb->prefix}icl_strings s
														WHERE s.language
															NOT IN (" . wpml_prepare_in( array_keys( $source_langs ) ) . ")" );

		foreach ( $string_lang_codes as $lang_code ) {
			$language = $this->sitepress->get_language_details( $lang_code );
			if ( (bool) $language === true ) {
				$source_langs[ $lang_code ] = $language;
			}
		}

		return $source_langs;
	}
}