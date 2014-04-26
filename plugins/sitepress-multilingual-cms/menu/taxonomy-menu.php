<?php
$this->noscript_notice();

global $sitepress, $wpdb;

$element_id = isset( $term->term_taxonomy_id ) ? $term->term_taxonomy_id : false;

$element_type = isset( $_GET[ 'taxonomy' ] ) ? esc_sql( $_GET[ 'taxonomy' ] ) : 'post_tag';
$icl_element_type = 'tax_' . $element_type;

$default_language = $this->get_default_language();
$current_language = $this->get_current_language();

if ( $element_id ) {
	$res_prepared = $wpdb->prepare( "SELECT trid, language_code, source_language_code
				  FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", array( $element_id, $icl_element_type ) );
	$res          = $wpdb->get_row( $res_prepared );
	$trid         = $res->trid;
	if ( $trid ) {
		$element_lang_code = $res->language_code;
	} else {
		$element_lang_code = $current_language;

		$translation_id = $this->set_element_language_details( $element_id, $icl_element_type, null, $element_lang_code );
		//get trid of $translation_id
		$trid = $wpdb->get_var( $wpdb->prepare( "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE translation_id=%d", array( $translation_id) ) );

	}
} else {
	$trid              = isset( $_GET[ 'trid' ] ) ? intval( $_GET[ 'trid' ] ) : false;
	$element_lang_code = isset( $_GET[ 'lang' ] ) ? strip_tags( $_GET[ 'lang' ] ) : $current_language;
}

$translations = false;
if ( $trid ) {
	$translations = $this->get_element_translations( $trid, $icl_element_type );
}
$active_languages = $this->get_active_languages();
$selected_language = $element_lang_code ? $element_lang_code : $default_language;
$source_language = isset( $_GET[ 'source_lang' ] ) ? strip_tags( $_GET[ 'source_lang' ] ) : false;
$untranslated_ids = $this->get_elements_without_translations( $icl_element_type, $selected_language, $default_language );

$sitepress->add_language_selector_to_page( $active_languages, $selected_language, empty( $translations ) ? array() : $translations, $element_id, $icl_element_type );
$sitepress->add_translation_of_selector_to_page( $trid, $selected_language, $default_language, $source_language, $untranslated_ids, $element_id, $icl_element_type );
$sitepress->add_translate_options( $trid, $active_languages, $selected_language, empty( $translations ) ? array() : $translations, $icl_element_type );

?>

</div></div></div></div></div>
