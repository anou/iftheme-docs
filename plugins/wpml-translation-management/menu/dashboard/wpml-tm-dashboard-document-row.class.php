<?php

class WPML_TM_Dashboard_Document_Row {

    private $data;
    private $post_types;
    private $translation_filter;
    private $active_languages;
    private $selected;
    private $note_text;
    private $note_icon;
    private $post_statuses;

    public function __construct(
        $doc_data,
        $translation_filter,
        $post_types,
        $post_statuses,
        $active_languages,
        $selected
    ) {
        $this->data               = $doc_data;
        $this->post_statuses      = $post_statuses;
        $this->selected           = $selected;
        $this->post_types         = $post_types;
        $this->active_languages   = $active_languages;
        $this->translation_filter = $translation_filter;
    }

    public function get_word_count() {
        $current_document = $this->data;
        $count            = $this->estimate_word_count();

        if ( !$this->is_external_type() ) {
            $count += $this->estimate_custom_field_word_count();
            $estimate_words_count_filter_tag = 'wpml_tm_post';
        } else {
            $estimate_words_count_filter_tag = 'wpml_tm_' . strtolower( get_class( $current_document ) );
        }
        $estimate_words_count_filter_tag .= '_estimate_word_count';
        $count = apply_filters(
            $estimate_words_count_filter_tag,
            $count,
            $current_document
        );

        return $count;
    }

    public function get_title() {

        return $this->data->title;
    }

    private function exclude_shortcodes_in_words_count() {
        /**
         * This is for future use.
         * To not count shortcodes words, define a constant as such:
         * define('EXCLUDE_SHORTCODES_IN_WORDS_COUNT', true); in wp-config.php
         * @todo: Next step: GUI support, we can expand it to check if we have sitepress->get_setting with info about this
         * @link https://onthegosystems.myjetbrains.com/youtrack/issue/wpmltm-239 YT ticket where it was introduced
         */
        if ( defined( 'EXCLUDE_SHORTCODES_IN_WORDS_COUNT' ) ) {
            return EXCLUDE_SHORTCODES_IN_WORDS_COUNT;
        }
        return false;
    }

    private function estimate_word_count() {
        global $asian_languages;
        $data  = $this->data;
        $words = 0;
        if ( isset( $data->language_code ) ) {
            $lang_code = $data->language_code;
            if ( isset( $data->title ) ) {
                if ( $asian_languages && in_array( $lang_code, $asian_languages ) ) {
                    $words += strlen( strip_tags( $data->title ) ) / ICL_ASIAN_LANGUAGE_CHAR_SIZE;
                } else {
                    $words += count( preg_split( '/[\s\/]+/', $data->title, 0, PREG_SPLIT_NO_EMPTY ) );
                }
            }
            $post_obj     = get_post( $data->ID );
            $post_content = $post_obj ? $post_obj->post_content : "";
            $post_content = strip_tags( $post_content );
            if ( $this->exclude_shortcodes_in_words_count() ) {
                $post_content = strip_shortcodes( $post_content );
            }
            if ( $asian_languages && in_array( $lang_code, $asian_languages ) ) {
                $words += strlen( $post_content ) / ICL_ASIAN_LANGUAGE_CHAR_SIZE;
            } else {
                $words += count( preg_split( '/[\s\/]+/', $post_content, 0, PREG_SPLIT_NO_EMPTY ) );
            }

        }

        return $words;
    }

    private function estimate_custom_field_word_count() {
        global $asian_languages, $sitepress_settings;

        if ( !isset( $this->data->language_code ) || !isset( $this->data->ID ) || $this->is_external_type() ) {
            return 0;
        }

        $words     = 0;
        $post_id   = $this->data->ID;
        $lang_code = $this->data->language_code;

        if ( !empty( $sitepress_settings[ 'translation-management' ][ 'custom_fields_translation' ] ) && is_array(
                $sitepress_settings[ 'translation-management' ][ 'custom_fields_translation' ]
            )
        ) {
            $custom_fields = array();
            foreach ( $sitepress_settings[ 'translation-management' ][ 'custom_fields_translation' ] as $cf => $op ) {
                if ( $op == 2 ) {
                    $custom_fields[ ] = $cf;
                }
            }
            foreach ( $custom_fields as $cf ) {
                $custom_fields_value = get_post_meta( $post_id, $cf );
                if ( $custom_fields_value && is_scalar(
                        $custom_fields_value
                    )
                ) {  // only support scalar values fo rnow
                    if ( in_array( $lang_code, $asian_languages ) ) {
                        $words += strlen( strip_tags( $custom_fields_value ) ) / 6;
                    } else {
                        $words += count(
                            preg_split( '/[\s\/]+/', strip_tags( $custom_fields_value ), 0, PREG_SPLIT_NO_EMPTY )
                        );
                    }
                } else {
                    foreach ( $custom_fields_value as $custom_fields_value_item ) {
                        if ( $custom_fields_value_item && is_scalar(
                                $custom_fields_value_item
                            )
                        ) { // only support scalar values fo rnow
                            if ( in_array( $lang_code, $asian_languages ) ) {
                                $words += strlen( strip_tags( $custom_fields_value_item ) ) / 6;
                            } else {
                                $words += count(
                                    preg_split(
                                        '/[\s\/]+/',
                                        strip_tags( $custom_fields_value_item ),
                                        0,
                                        PREG_SPLIT_NO_EMPTY
                                    )
                                );
                            }
                        }
                    }
                }
            }
        }

        return (int) $words;
    }

    private function is_external_type() {
        $doc = $this->data;

        return strpos($doc->translation_element_type, 'post_' ) !== 0;
    }

    public function get_type_prefix(){
        $type = $this->data->translation_element_type;
        $type = explode( '_', $type );
        if ( count( $type ) > 1 ) {
            $type = $type[ 0 ];
        }

        return $type;
    }

    public function get_type() {
        $type = $this->data->translation_element_type;
        $type = explode( '_', $type );
        if ( count( $type ) > 1 ) {
            unset( $type[ 0 ] );
        }
        $type = join( '_', $type );

        return $type;
    }

	public function display( $odd_row ) {
		global $iclTranslationManagement;
		$alternate         = $odd_row ? 'class="alternate"' : '';
		$current_document  = $this->data;
		$count             = $this->get_word_count();
		$post_actions      = array();
		$post_actions_link = "";
		$element_type      = $this->get_type_prefix();
		$check_field_name  = $element_type;
		$post_title        = $this->get_title();

		$post_view_link = '';
		$post_edit_link = '';
		if ( ! $this->is_external_type() ) {
			$post_view_link = TranslationManagement::tm_post_link( $current_document->ID, __( 'View', 'wpml-translation-management' ), true );
			$post_edit_link = TranslationManagement::tm_post_link( $current_document->ID, __( 'Edit', 'wpml-translation-management' ), true, true, true, true );
		}

		$post_view_link = apply_filters( 'wpml_document_view_item_link', $post_view_link, __( 'View', 'wpml-translation-management' ), $current_document, $element_type, $this->get_type());
		if ( $post_view_link ) {
			$post_actions[ ] = "<span class='view'>" . $post_view_link . "</span>";
		}
		$post_edit_link = apply_filters( 'wpml_document_edit_item_link', $post_edit_link, __( 'Edit', 'wpml-translation-management' ), $current_document, $element_type, $this->get_type() );
		if ( $post_edit_link ) {
			$post_actions[ ] = "<span class='edit'>" . $post_edit_link . "</span>";
		}

		if ( $post_actions ) {
			$post_actions_link .= '<div class="row-actions">' . implode( ' | ', $post_actions ) . '</div>';
		}
		?>
		<tr id="row_<?php echo sanitize_html_class( $current_document->ID ); ?>" data-word_count="<?php echo $count; ?>" <?php echo $alternate; ?>>
			<td scope="row">
				<?php
				$checked = checked( true, isset( $_GET[ 'post_id' ] ) || $this->selected, false );
				?>
				<input type="checkbox" value="<?php echo $current_document->ID ?>" name="<?php echo $check_field_name; ?>[<?php echo $current_document->ID; ?>][checked]" <?php echo $checked; ?> />
				<input type="hidden" value="<?php echo $element_type; ?>" name="<?php echo $check_field_name; ?>[<?php echo $current_document->ID; ?>][type]"/>
			</td>
			<td scope="row" class="post-title column-title">
				<?php
				echo esc_html( $post_title );
				echo $post_actions_link;
				?>
				<div class="icl_post_note" id="icl_post_note_<?php echo $current_document->ID ?>">
					<?php
					$note = '';
					if ( ! $current_document->is_translation ) {
						$note            = get_post_meta( $current_document->ID, '_icl_translator_note', true );
						$this->note_text = '';
						if ( $note ) {
							$this->note_text = __( 'Edit note for the translators', 'wpml-translation-management' );
							$this->note_icon = 'edit_translation.png';
						} else {
							$this->note_text = __( 'Add note for the translators', 'wpml-translation-management' );
							$this->note_icon = 'add_translation.png';
						}
					}
					?>
					<label for="post_note_<?php echo $current_document->ID ?>">
						<?php _e( 'Note for the translators', 'wpml-translation-management' ) ?>
					</label>
                    <textarea id="post_note_<?php echo $current_document->ID ?>" rows="5"><?php echo $note ?></textarea>
					<table width="100%">
						<tr>
							<td style="border-bottom:none">
								<input type="button" class="icl_tn_clear button" value="<?php _e( 'Clear', 'wpml-translation-management' ) ?>" <?php if ( ! $note): ?>disabled="disabled"<?php endif; ?> />
								<input class="icl_tn_post_id" type="hidden" value="<?php echo $current_document->ID ?>"/>
							</td>
							<td align="right" style="border-bottom:none">
								<input type="button" class="icl_tn_save button-primary" value="<?php _e( 'Save', 'wpml-translation-management' ) ?>"/>
							</td>
						</tr>
					</table>
				</div>
			</td>
			<td scope="row" class="post-date column-date">
				<?php
				$element_date = $this->get_date();
				if ( $element_date ) {
					echo date( 'Y-m-d', strtotime( $element_date ) );
				}
				?>
			</td>
			<td scope="row" class="icl_tn_link" id="icl_tn_link_<?php echo $current_document->ID ?>">
				<?php
				if ( ! $current_document->is_translation ) {
					?>
					<a title="<?php echo $this->note_text ?>" href="#">
						<img src="<?php echo WPML_TM_URL ?>/res/img/<?php echo $this->note_icon ?>" width="16" height="16"/>
					</a>
				<?php
				}
				?>
			</td>
			<td scope="row" class="manage-column column-date">
				<?php
				if ( isset( $this->post_types[ $this->get_type() ] ) ) {
					$custom_post_type_labels = $this->post_types[ $this->get_type() ]->labels;
					if ( $custom_post_type_labels->singular_name != "" ) {
						echo $custom_post_type_labels->singular_name;
					} else {
						echo $custom_post_type_labels->name;
					}
				} else {
					echo $this->get_type();
				}
				?>
			</td>
			<td scope="row" class="manage-column column-date">
				<?php echo $this->get_general_status(); ?>
			</td>
			<td scope="row" class="manage-column column-active-languages">
				<?php
				foreach ( $this->active_languages as $code => $lang ) {
					if ( $code == $this->data->language_code ) {
						continue;
					}

					$status = $this->get_status_in_lang( $code );
					switch ( $status ) {
						case ICL_TM_NOT_TRANSLATED :
							$translation_status_text = esc_attr( __( 'Not translated', 'wpml-translation-management' ) );
							break;
						case ICL_TM_WAITING_FOR_TRANSLATOR :
							$translation_status_text = esc_attr( __( 'Waiting for translator', 'wpml-translation-management' ) );
							break;
						case ICL_TM_IN_BASKET :
							$translation_status_text = esc_attr( __( 'In basket', 'wpml-translation-management' ) );
							break;
						case ICL_TM_IN_PROGRESS :
							$translation_status_text = esc_attr( __( 'In progress', 'wpml-translation-management' ) );
							break;
						case ICL_TM_DUPLICATE :
							$translation_status_text = esc_attr( __( 'Duplicate', 'wpml-translation-management' ) );
							break;
						case ICL_TM_COMPLETE :
							$translation_status_text = esc_attr( __( 'Complete', 'wpml-translation-management' ) );
							break;
						case ICL_TM_NEEDS_UPDATE :
							$translation_status_text = ' - ' . esc_attr( __( 'needs update', 'wpml-translation-management' ) );
							break;
						default:
							$translation_status_text = '';
					}

					$status_image_file_name = $iclTranslationManagement->status2img_filename( $status, ICL_TM_NEEDS_UPDATE === (int) $status );
					?>

					<span data-document_status="<?php echo $status; ?>" style="padding-top:4px;">
                    <img title="<?php echo $lang[ 'display_name' ]; ?>: <?php echo $translation_status_text ?>"
                         src="<?php echo WPML_TM_URL ?>/res/img/<?php echo $status_image_file_name; ?>"
                         width="16"
                         height="16"
                         alt="<?php echo $lang[ 'display_name' ]; ?>: <?php echo $translation_status_text ?>"/>
                </span>
				<?php
				}
				?>
			</td>
		</tr>
	<?php
	}

    private function get_date() {
        if ( !$this->is_external_type() ) {
            /** @var WP_Post $post */
            $post = get_post( $this->data->ID );
            $date = get_post_time( 'U', false, $post );
        } else {
            $date = apply_filters(
                'wpml_tm_dashboard_date',
                time(),
                $this->data->ID,
                $this->data->translation_element_type
            );
        }
        $date = date( 'y-m-d', $date );

        return $date;
    }

    private function get_general_status() {
        if ( !$this->is_external_type() ) {
            $status      = get_post_status( $this->data->ID );
            $status_text = isset( $this->post_statuses[ $status ] ) ? $this->post_statuses[ $status ] : $status;
        } else {
            $status_text = apply_filters(
                'wpml_tm_dashboard_status',
                'external',
                $this->data->ID,
                $this->data->translation_element_type
            );
        }

        return $status_text;
    }

    private function get_status_in_lang( $language_code ) {
        $status_helper = wpml_get_post_status_helper ();

        return $status_helper->get_status ( false, $this->data->trid, $language_code );
    }
}