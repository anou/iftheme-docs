<?php

class WPML_Remote_String_Translation {

	/**
	 * @param $item_type_name
	 * @param $item_type
	 * @param $strings_basket_items
	 * @param $translators
	 * @param $basket_name
	 */
	public static function send_strings_jobs( $item_type_name, $item_type, $strings_basket_items, $translators, $basket_name ) {
		/** @var $iclTranslationManagement TranslationManagement */
		global $iclTranslationManagement;
		$strings_local = array();
		if ( ! empty( $strings_basket_items ) ) {
			// for every string in cart
			// collect strings for local translation
			// collect string for remote translation
			$strings_remote = array();

			foreach ( $strings_basket_items as $basket_item_id => $basket_item ) {
				foreach ( $basket_item[ 'to_langs' ] as $language_code => $action ) {
					if ( is_numeric( $translators[ $language_code ] ) ) {
						$strings_local[ $language_code ][ ] = $basket_item_id;
					} else {
						$strings_remote[ $language_code ][ ] = $basket_item_id;
					}
				}
			}

			if ( $strings_remote ) {
				foreach ( $strings_remote as $target => $string_ids ) {
					$result = self::send_strings_to_translation_service( $string_ids,
					                                                     $target,
					                                                     $basket_name,
					                                                     $translators[ $target ] );
					if ( isset( $result[ 'errors' ] ) && count( $result[ 'errors' ] ) ) {
						foreach ( $result[ 'errors' ] as $error ) {
							$error_message = array(
								'type' => 'error',
								'text' => $error,
							);
							$iclTranslationManagement->add_message( $error_message );
						}
					}
					if ( ! $result ) {
						foreach ( $string_ids as $string_id ) {
							$default_string_language = TranslationProxy_Basket::get_source_language();

							$string  = icl_get_string_by_id( $string_id, $default_string_language );
							$message = array(
								'type' => 'error',
								'text' => sprintf( __( 'String "%s" has not been sent.', 'wpml-translation-management' ), $string ),
							);
							$iclTranslationManagement->add_message( $message );
						}
						break;
					}
				}
			}

			foreach ( $strings_local as $target => $string_ids ) {
				self::translation_send_strings_local( $string_ids, $target, $translators[ $target ], $basket_name );
			}
		}
	}

	public static function get_string_status_labels() {
		return array(
			ICL_TM_COMPLETE                => __( 'Translation complete', 'wpml-translation-management' ),
			ICL_STRING_TRANSLATION_PARTIAL => __( 'Partial translation', 'wpml-translation-management' ),
			ICL_TM_NEEDS_UPDATE            => __( 'Translation needs update',
			                                      'wpml-translation-management' ),
			ICL_TM_NOT_TRANSLATED          => __( 'Not translated', 'wpml-translation-management' ),
			ICL_TM_WAITING_FOR_TRANSLATOR  => __( 'Waiting for translator / In progress',
			                                      'wpml-translation-management' ),
			ICL_TM_IN_BASKET               => __( 'Strings in the basket', 'wpml-translation-management' ),
		);
	}

	public static function get_string_status_label( $status ) {
		$string_translation_states_enumeration = self::get_string_status_labels();
		if ( isset( $string_translation_states_enumeration[ $status ] ) ) {
			return $string_translation_states_enumeration[ $status ];
		}

		return false;
	}

	public static function send_strings_to_translation_service( $string_ids, $target_language, $basket_name, $translator_id ) {
		global $WPML_String_Translation;

		return $WPML_String_Translation->send_strings_to_translation_service( $string_ids,
		                                                                      $target_language,
		                                                                      $basket_name,
		                                                                      $translator_id );
	}

	public static function translation_send_strings_local( $string_ids, $target, $translator = null, $basket_name = null ) {
		global $wpdb, $WPML_String_Translation;
		static $site_translators;
		if ( ! isset( $site_translators ) ) {
			$site_translators = TranslationManagement::get_blog_translators();
		}

		$mkey  = $wpdb->prefix . 'strings_notification';
		$lkey  = $wpdb->prefix . 'language_pairs';
		$slang = $WPML_String_Translation->get_strings_language();

		foreach ( $string_ids as $string_id ) {

			$batch_id = TranslationProxy_Batch::update_translation_batch( $basket_name );

			$added = icl_add_string_translation( $string_id,
			                                     $target,
			                                     null,
			                                     ICL_TM_WAITING_FOR_TRANSLATOR,
			                                     $translator,
			                                     'local',
			                                     $batch_id );

			if ( $added ) {

				foreach ( $site_translators as $key => $st ) {
					$ulangs = isset( $st->$lkey ) ? $st->$lkey : false;
					if ( ! empty( $ulangs ) && ! empty( $ulangs[ $slang ][ $target ] ) ) {

						$enot = isset( $st->$mkey ) ? $st->$mkey : false;
						if ( empty( $enot[ $slang ][ $target ] ) ) {
							self::translator_notification( $st, $slang, $target );
							$enot[ $slang ][ $target ]       = 1;
							$site_translators[ $key ]->$mkey = $enot;
							update_option( $wpdb->prefix . 'icl_translators_cached', $site_translators );
						}
					}
				}
			}
		}

		return 1;
	}

	public static function translator_notification( $user, $source, $target ) {
		global $wpdb, $sitepress;

		$_ldetails = $sitepress->get_language_details( $source );
		$source_en = $_ldetails[ 'english_name' ];
		$_ldetails = $sitepress->get_language_details( $target );
		$target_en = $_ldetails[ 'english_name' ];

		$message = __( "You have been assigned to a new translation job from %s to %s.

	Start editing: %s

	You can view your other translation jobs here: %s

	 This message was automatically sent by Translation Management running on WPML. To stop receiving these notifications contact the system administrator at %s.

	 This email is not monitored for replies.

	 - WPML team
	", 'wpml-translation-management' );

		$to      = $user->user_email;
		$subject = sprintf( __( "You have been assigned to a new translation job on %s.", 'wpml-translation-management' ),
		                    get_bloginfo( 'name' ) );
		$body    = sprintf( $message,
		                    $source_en,
		                    $target_en,
		                    admin_url( 'admin.php?page=' . WPML_ST_FOLDER . '/menu/string-translation.php' ),
		                    admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/translations-queue.php' ),
		                    home_url() );

		wp_mail( $to, $subject, $body );

		$meta                       = get_user_meta( $user->ID, $wpdb->prefix . 'strings_notification', 1 );
		$meta[ $source ][ $target ] = 1;
		update_user_meta( $user->ID, $wpdb->prefix . 'strings_notification', $meta );
	}

	public static function display_string_menu( $lang_filter ) {
		if ( ! defined( 'WPML_TM_VERSION' ) ) {
			return;
		}
		global $sitepress, $WPML_String_Translation;
		$target_status            = array();
		$target_rate              = array();
		$lang_status              = icl_get_setting( 'icl_lang_status' );
		$strings_target_languages = $sitepress->get_active_languages();
		unset( $strings_target_languages[ $WPML_String_Translation->get_strings_language() ] );

		if ( $lang_status ) {
			foreach ( $lang_status as $lang ) {
				if ( $lang[ 'from' ] == $sitepress->get_current_language() ) {
					$target_status[ $lang[ 'to' ] ] = $lang[ 'have_translators' ];
					$target_rate[ $lang[ 'to' ] ]   = $lang[ 'max_rate' ];
				}
			}
		}
		?>
		<form method="post" id="icl_st_send_strings" name="icl_st_send_strings" action="">
			<input type="hidden" name="icl_st_action" value="send_strings"/>
			<input type="hidden" name="strings" value=""/>
			<input type="hidden" name="icl-tr-from" value="<?php echo $lang_filter; ?>"/>

			<table id="icl-tr-opt" class="widefat fixed" cellspacing="0" style="width:100%">
				<thead>
				<tr>
					<th><?php _e( 'Translation options', 'wpml-translation-management' ) ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
					<td>
						<ul id="icl_tm_languages">
							<?php
							foreach ( $strings_target_languages as $lang ) {
								if ( $lang[ 'code' ] == $lang_filter ) {
									continue;
								}
								$is_active_language = $sitepress->is_active_language( $lang[ 'code' ] );
								$checked            = checked( true, $is_active_language, false );
								$label_class = $is_active_language ? 'active' : 'non-active'
								?>
								<li>
									<input type="checkbox"
									       id="translate_to[<?php echo $lang[ 'code' ] ?>]"
									       name="translate_to[<?php echo $lang[ 'code' ] ?>]"
									       value="1"
									       id="icl_st_translate_to_<?php echo $lang[ 'code' ] ?>" <?php echo $checked; ?>/>
									<label for="translate_to[<?php echo $lang[ 'code' ] ?>]"
									       class="<?php echo $label_class; ?>">
										<?php printf( __( 'Translate to %s', 'wpml-translation-management' ),
										              $lang[ 'display_name' ] ) ?>
									</label>
									<?php
									if ( isset( $target_status[ $lang[ 'code' ] ] ) && $target_status[ $lang[ 'code' ] ] ) {
										?>
										<span style="display: none;"
										      id="icl_st_max_rate_<?php echo $lang[ 'code' ] ?>"><?php echo $target_rate[ $lang[ 'code' ] ] ?></span>
										<span style="display: none;"
										      id="icl_st_estimate_<?php echo $lang[ 'code' ] ?>_wrap"
										      class="icl_st_estimate_wrap">
		                                    &nbsp;(<?php printf( __( 'Estimated cost: %s USD',
		                                                             'wpml-translation-management' ),
		                                                         '<span id="icl_st_estimate_' . $lang[ 'code' ] . '">0</span>' ) ?>
											)</span>
									<?php
									}
									?>
								</li>
							<?php
							}
							?>
						</ul>
						<input name="iclnonce" type="hidden"
						       value="<?php echo wp_create_nonce( 'icl-string-translation' ) ?>"/>
						<input id="icl_send_strings" class="button-primary" type="submit"
						       value="<?php _e( 'Add to translation basket', 'wpml-translation-management' ); ?>"
						       disabled="disabled"/>

						<div style="width: 45%; margin: auto">
							<?php
							ICL_AdminNotifier::display_messages( 'string-translation-under-translation-options' );
							ICL_AdminNotifier::remove_message( 'items_added_to_basket' );
							?>
						</div>
					</td>
				</tr>
				</tbody>
			</table>
		</form>
	<?php
	}

    public static function string_status_text_filter( $text, $string_id ) {
        if ( TranslationProxy_Basket::is_string_in_basket_anywhere( $string_id ) ) {
            $text = __( 'In the translation basket', 'wpml-translation-management' );
        } else {
            global $wpdb;
            $translation_service = $wpdb->get_var(
                $wpdb->prepare(
                    "	SELECT translation_service
						FROM {$wpdb->prefix}icl_string_translations
						WHERE
						    string_id = %d
							AND translation_service > 0
							AND status IN (%d, %d)
						LIMIT 1
						",
	                    $string_id,
	                    ICL_TM_WAITING_FOR_TRANSLATOR,
	                    ICL_TM_IN_PROGRESS
                )
            );
            if ( $translation_service ) {
                $text = $text . " : " . sprintf( __( 'One or more strings sent to %s', 'wpml-translation-management'), TranslationProxy::get_service_name( $translation_service ) );
            }
        }

        return $text;
    }
}