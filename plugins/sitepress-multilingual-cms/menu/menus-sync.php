<?php
/** @var $sitepress SitePress */
/** @var $icl_menus_sync ICLMenusSync */
$active_languages = $sitepress->get_active_languages();
$default_language = $sitepress->get_default_language();
$default_language_details = $sitepress->get_language_details( $default_language );

foreach ( $active_languages as $lang ) {
	if ( $lang[ 'code' ] != $default_language_details[ 'code' ] ) {
		$secondary_languages[ ] = $lang;
	}
}
?>
<!--suppress HtmlFormInputWithoutLabel --><!--suppress HtmlUnknownAttribute -->
<div class="wrap">
<div id="icon-wpml" class="icon32"><br/></div>
<h2><?php echo __( 'WP Menus Sync', 'sitepress' ) ?></h2>

<br/>
<?php
if ( $icl_menus_sync->is_preview ) {
	?>

	<form id="icl_msync_confirm_form" method="post">
	<input type="hidden" name="action" value="icl_msync_confirm"/>

	<table id="icl_msync_confirm" class="widefat icl_msync">
	<thead>
	<tr>
		<th scope="row" class="check-column"><input type="checkbox"/></th>
		<th><?php _e( 'Language', 'sitepress' ) ?></th>
		<th><?php _e( 'Action', 'sitepress' ) ?></th>
	</tr>
	</thead>
	<tbody>

	<?php
	if ( empty( $icl_menus_sync->sync_data ) ) {
		?>
		<tr>
			<td align="center" colspan="3"><?php _e( 'Nothing to sync.', 'sitepress' ) ?></td>
		</tr>
	<?php
	} else {
		//Menus
		foreach ( $icl_menus_sync->menus as $menu_id => $menu ) {

			if ( isset( $icl_menus_sync->sync_data[ 'menu_translations' ][ $menu_id ] ) ||
				 isset( $icl_menus_sync->sync_data[ 'menu_options' ][ $menu_id ] ) ||
				 isset( $icl_menus_sync->sync_data[ 'add' ][ $menu_id ] ) ||
				 isset( $icl_menus_sync->sync_data[ 'del' ][ $menu_id ] ) ||
				 isset( $icl_menus_sync->sync_data[ 'label_changed' ][ $menu_id ] ) ||
				 isset( $icl_menus_sync->sync_data[ 'url_changed' ][ $menu_id ] ) ||
				 isset( $icl_menus_sync->sync_data[ 'label_missing' ][ $menu_id ] ) ||
				 isset( $icl_menus_sync->sync_data[ 'url_missing' ][ $menu_id ] ) ||
				 isset( $icl_menus_sync->sync_data[ 'mov' ][ $menu_id ] )
			) {
				?>
				<tr class="icl_msync_menu_title">
					<td colspan="3"><?php echo $menu[ 'name' ] ?></td>
				</tr>

				<?php
				// Display actions per menu
				// menu translations
				if ( isset( $icl_menus_sync->sync_data[ 'menu_translations' ] ) && isset( $icl_menus_sync->sync_data[ 'menu_translations' ][ $menu_id ] ) ) {
					foreach ( $icl_menus_sync->sync_data[ 'menu_translations' ][ $menu_id ] as $language => $name ) {
						$lang_details = $sitepress->get_language_details( $language );
						?>
						<tr>
							<th scope="row" class="check-column"><input type="checkbox" name="sync[menu_translation][<?php echo $menu_id ?>][<?php echo $language ?>]" value="<?php echo esc_attr( $name ) ?>"/></th>
							<td><?php echo $lang_details[ 'display_name' ]; ?></td>
							<td><?php printf( __( 'Add menu translation:  %s', 'sitepress' ), '<strong>' . $name . '</strong>' ); ?> </td>
						</tr>
					<?php
					}
				}

				if ( isset( $icl_menus_sync->sync_data[ 'menu_options' ] ) && isset( $icl_menus_sync->sync_data[ 'menu_options' ][ $menu_id ] ) ) {
					foreach ( $icl_menus_sync->sync_data[ 'menu_options' ][ $menu_id ] as $language => $option ) {

						$lang_details = $sitepress->get_language_details( $language );
						foreach ( $option as $key => $value ) {
							if ( isset( $menu[ $key ] ) && $menu[ $key ] != $value ) {
								?>
								<tr>
									<th scope="row" class="check-column"><input type="checkbox" name="sync[menu_options][<?php echo $menu_id ?>][<?php echo $language ?>][<?php echo $key; ?>]"
																				value="<?php echo esc_attr( $menu[ $key ] ) ?>"/></th>
									<td><?php echo $lang_details[ 'display_name' ]; ?></td>
									<td><?php printf( __( 'Update %s menu option', 'sitepress' ), '<strong>' . $key . '</strong>' ); ?> </td>
								</tr>
							<?php
							}
						}
					}
				}

				// items translations / add
				if ( isset( $icl_menus_sync->sync_data[ 'add' ][ $menu_id ] ) ) {
					foreach ( $icl_menus_sync->sync_data[ 'add' ][ $menu_id ] as $item_id => $languages ) {
						foreach ( $languages as $language => $name ) {
							$lang_details = $sitepress->get_language_details( $language );
							?>
							<tr>
								<th scope="row" class="check-column">
									<input type="checkbox" name="sync[add][<?php echo $menu_id ?>][<?php echo $item_id ?>][<?php echo $language ?>]" value="<?php echo esc_attr( $name ) ?>"/>
								</th>
								<td><?php echo $lang_details[ 'display_name' ]; ?></td>
								<td><?php
									echo str_repeat( ' - ', $icl_menus_sync->get_item_depth( $menu_id, $item_id ) );
									printf( __( 'Add %s', 'sitepress' ), '<strong>' . $name . '</strong>' );
									?> </td>
							</tr>
						<?php
						}
					}
				}

				// items translations / mov
				if ( isset( $icl_menus_sync->sync_data[ 'mov' ][ $menu_id ] ) ) {
					foreach ( $icl_menus_sync->sync_data[ 'mov' ][ $menu_id ] as $item_id => $changes ) {
						foreach ( $changes as $language => $details ) {
							$lang_details   = $sitepress->get_language_details( $language );
							$new_menu_order = key( $details );
							$name           = current( $details );
							?>
							<tr>
								<th scope="row" class="check-column">
									<input type="hidden" name="sync[mov][<?php echo $menu_id ?>][<?php echo $item_id ?>][<?php echo $language ?>][<?php echo $new_menu_order ?>]" value="<?php echo esc_attr( $name ) ?>"/>
								</th>
								<td><?php echo $lang_details[ 'display_name' ]; ?></td>
								<td><?php
									echo str_repeat( ' - ', $icl_menus_sync->get_item_depth( $menu_id, $item_id ) );
									printf( __( 'Change menu order for %s', 'sitepress' ), '<strong>' . $name . '</strong>' );
									?> </td>
							</tr>
						<?php
						}
					}
				}

				// items translations / del
				if ( isset( $icl_menus_sync->sync_data[ 'del' ][ $menu_id ] ) ) {
					foreach ( $icl_menus_sync->sync_data[ 'del' ][ $menu_id ] as $language => $items ) {
						foreach ( $items as $item_id => $name ) {
							$lang_details = $sitepress->get_language_details( $language );
							?>
							<tr>
								<th scope="row" class="check-column">
									<input type="checkbox" name="sync[del][<?php echo $menu_id ?>][<?php echo $language ?>][<?php echo $item_id ?>]" value="<?php echo esc_attr( $name ) ?>"/>
								</th>
								<td><?php echo $lang_details[ 'display_name' ]; ?></td>
								<td><?php
									printf( __( 'Remove %s', 'sitepress' ), '<strong>' . $name . '</strong>' );
									?> </td>
							</tr>
						<?php
						}
					}
				}

				// items translations / label changed
				if ( isset( $icl_menus_sync->sync_data[ 'label_changed' ][ $menu_id ] ) ) {
					foreach ( $icl_menus_sync->sync_data[ 'label_changed' ][ $menu_id ] as $item_id => $languages ) {
						foreach ( $languages as $language => $name ) {
							$lang_details = $sitepress->get_language_details( $language );
							?>
							<tr>
								<th scope="row" class="check-column">
									<input type="checkbox" name="sync[label_changed][<?php echo $menu_id ?>][<?php echo $language ?>][<?php echo $item_id ?>]" value="<?php echo esc_attr( $name ) ?>"/>
								</th>
								<td><?php echo $lang_details[ 'display_name' ]; ?></td>
								<td><?php
									printf( __( 'Rename label to %s', 'sitepress' ), '<strong>' . $name . '</strong>' );
									?> </td>
							</tr>
						<?php
						}
					}
				}

				// items translations / url changed
				if ( isset( $icl_menus_sync->sync_data[ 'url_changed' ][ $menu_id ] ) ) {
					foreach ( $icl_menus_sync->sync_data[ 'url_changed' ][ $menu_id ] as $item_id => $languages ) {
						foreach ( $languages as $language => $url ) {
							$lang_details = $sitepress->get_language_details( $language );
							?>
							<tr>
								<th scope="row" class="check-column">
									<input type="checkbox" name="sync[url_changed][<?php echo $menu_id ?>][<?php echo $language ?>][<?php echo $item_id ?>]" value="<?php echo esc_attr( $url ) ?>"/>
								</th>
								<td><?php echo $lang_details[ 'display_name' ]; ?></td>
								<td><?php
									printf( __( 'Update URL to %s', 'sitepress' ), '<strong>' . $url . '</strong>' );
									?> </td>
							</tr>
						<?php
						}
					}
				}

				// items translations / label missing
				if ( isset( $icl_menus_sync->sync_data[ 'label_missing' ][ $menu_id ] ) ) {
					foreach ( $icl_menus_sync->sync_data[ 'label_missing' ][ $menu_id ] as $item_id => $languages ) {
						foreach ( $languages as $language => $name ) {
							$lang_details = $sitepress->get_language_details( $language );
							?>
							<tr>
								<th scope="row" class="check-column">
									<input type="checkbox" name="sync[label_missing][<?php echo $menu_id ?>][<?php echo $language ?>][<?php echo $item_id ?>]" value="<?php echo esc_attr( $name ) ?>"/>
								</th>
								<td><?php echo $lang_details[ 'display_name' ]; ?></td>
								<td><?php
									printf( __( 'Untranslated string %s', 'sitepress' ), '<strong>' . $name . '</strong>' );
									?>&nbsp;<?php printf(__('The selected strings can now be translated using the <a%s>string translation</a> screen', 'wpml-string-translation'), ' href="admin.php?page='.WPML_ST_FOLDER.'/menu/string-translation.php&context=admin_texts_theme_'.get_option('template').'"');?></td>
							</tr>
						<?php
						}
					}
				}

				// items translations / url missing
				if ( isset( $icl_menus_sync->sync_data[ 'url_missing' ][ $menu_id ] ) ) {
					foreach ( $icl_menus_sync->sync_data[ 'url_missing' ][ $menu_id ] as $item_id => $languages ) {
						foreach ( $languages as $language => $url ) {
							$lang_details = $sitepress->get_language_details( $language );
							?>
							<tr>
								<th scope="row" class="check-column">
									<input type="checkbox" name="sync[url_missing][<?php echo $menu_id ?>][<?php echo $language ?>][<?php echo $item_id ?>]" value="<?php echo esc_attr( $url ) ?>"/>
								</th>
								<td><?php echo $lang_details[ 'display_name' ]; ?></td>
								<td><?php
									printf( __( 'Untranslated URL %s', 'sitepress' ), '<strong>' . $url . '</strong>' );
									?> </td>
							</tr>
						<?php
						}
					}
				}
			}
		}
	}
	?>

	</tbody>
	</table>

	<p class="submit">
		<?php
		$icl_menu_sync_submit_disabled = '';
		if ( empty( $icl_menus_sync->sync_data ) || ( empty( $icl_menus_sync->sync_data[ 'mov' ] ) && empty( $icl_menus_sync->sync_data[ 'mov' ][ $menu_id ] ) ) ) {
			$icl_menu_sync_submit_disabled = 'disabled="disabled"';
		}
		?>
		<input id="icl_msync_submit" class="button-primary" type="submit" value="<?php _e( 'Apply changes' ) ?>" <?php echo $icl_menu_sync_submit_disabled; ?> />&nbsp;
		<input id="icl_msync_cancel" class="button-secondary" type="button" value="<?php _e( 'Cancel' ) ?>"/>
	</p>

	</form>

<?php
} else {
	$need_sync = 0;
	?>
	<form method="post" action="">
		<input type="hidden" name="action" value="icl_msync_preview"/>
		<table class="widefat icl_msync">
			<thead>
			<tr>
				<th><?php echo $default_language_details[ 'display_name' ]; ?></th>
				<?php
				foreach ( $secondary_languages as $lang ) {
					?>
					<th><?php echo $lang[ 'display_name' ]; ?></th>
				<?php
				}
				?>
			</tr>
			</thead>
			<tbody>
			<?php
			if ( empty( $icl_menus_sync->menus ) ) {
				?>
				<tr>
					<td align="center" colspan="<?php echo count( $active_languages ) ?>"><?php _e( 'No menus found', 'sitepress' ) ?></td>
				</tr>
			<?php
			} else {
				foreach ( $icl_menus_sync->menus as $menu_id => $menu ) {
					?>

					<tr class="icl_msync_menu_title">
						<td><strong><?php echo $menu[ 'name' ]; ?></strong></td>
						<?php
						foreach ( $secondary_languages as $l ) {
							?>
							<td>
								<?php
								if ( isset( $menu[ 'translations' ][ $l[ 'code' ] ][ 'name' ] ) ) {
									echo $menu[ 'translations' ][ $l[ 'code' ] ][ 'name' ];
								} else { // menu is translated in $l[code]
									$need_sync++;
									?>
									<input type="text" name="sync[menu_translations][<?php echo $menu_id ?>][<?php echo $l[ 'code' ] ?>]" class="icl_msync_add" value="<?php
									echo esc_attr( $menu[ 'name' ] ) . ' - ' . $l[ 'display_name' ] ?>"/>
									<small><?php _e( 'Auto-generated title. Edit to change.', 'sitepress' ) ?></small>
									<input type="hidden" name="sync[menu_options][<?php echo $menu_id ?>][<?php echo $l[ 'code' ] ?>][auto_add]"
																				value=""/>
								<?php
								}
								if ( isset( $menu[ 'translations' ][ $l[ 'code' ] ][ 'auto_add' ] ) ) {
									?>
									<input type="hidden" name="sync[menu_options][<?php echo $menu_id ?>][<?php echo $l[ 'code' ] ?>][auto_add]" value="<?php echo esc_attr( $menu[ 'translations' ][ $l[ 'code' ] ][ 'auto_add' ] ); ?>"/>
								<?php
								}
								?>
							</td>
						<?php
						} //foreach($secondary_languages as $l):
						?>
					</tr>
					<?php
					$need_sync += $icl_menus_sync->render_items_tree_default( $menu_id );

				} //foreach( $icl_menus_sync->menus as  $menu_id => $menu):
			}
			?>
			</tbody>
		</table>
		<p class="submit">
			<?php
			if ( $need_sync ) {
				?>
				<input id="icl_msync_sync" type="submit" class="button-primary" value="<?php _e( 'Sync', 'sitepress' ); ?>"<?php if ( !$need_sync ): ?> disabled="disabled"<?php endif; ?> />
			<?php
			} else {
				?>
				<input id="icl_msync_sync" type="submit" class="button-primary" value="<?php _e( 'Nothing Sync', 'sitepress' ); ?>"<?php if ( !$need_sync ): ?> disabled="disabled"<?php endif; ?> />
			<?php
			}
			?>
		</p>
	</form>

	<?php
	if ( !empty( $icl_menus_sync->operations ) ) {
		$show_string_translation_link = false;
		foreach ( $icl_menus_sync->operations as $op => $c ) {
			if ( $op == 'add' ) {
				?>
				<span class="icl_msync_item icl_msync_add"><?php _e( 'Item will be added', 'sitepress' ); ?></span>
			<?php
			} elseif ( $op == 'del' ) {
				?>
				<span class="icl_msync_item icl_msync_del"><?php _e( 'Item will be removed', 'sitepress' ); ?></span>
			<?php
			} elseif ( $op == 'not' ) {
				?>
				<span class="icl_msync_item icl_msync_not"><?php _e( 'Item cannot be added (parent not translated)', 'sitepress' ); ?></span>
			<?php
			} elseif ( $op == 'mov' ) {
				?>
				<span class="icl_msync_item icl_msync_mov"><?php _e( 'Item changed position', 'sitepress' ); ?></span>
			<?php
			} elseif ( $op == 'copy' ) {
				?>
				<span class="icl_msync_item icl_msync_copy"><?php _e( 'Item will be copied', 'sitepress' ); ?></span>
			<?php
			} elseif ( $op == 'label_changed' ) {
				?>
				<span class="icl_msync_item icl_msync_label_changed"><?php _e( 'Strings for menus will be updated', 'sitepress' ); ?></span>
			<?php
			} elseif ( $op == 'url_changed' ) {
				?>
				<span class="icl_msync_item icl_msync_url_changed"><?php _e( 'URLs for menus will be updated', 'sitepress' ); ?></span>
			<?php
			} elseif ( $op == 'label_missing' ) {
				?>
				<span class="icl_msync_item icl_msync_label_missing">
					<?php _e( 'Untranslated strings for menus', 'sitepress' ); ?>
				</span>
			<?php
			} elseif ( $op == 'url_missing' ) {
				?>
				<span class="icl_msync_item icl_msync_url_missing">
					<?php _e( 'Untranslated URLs for menus', 'sitepress' ); ?>
				</span>
			<?php
			}
		}
	}
	if ( $icl_menus_sync->string_translation_links ) {
		echo '<p>';
		echo __( 'Translate menu strings and URLs for:', 'wpml-string-translation' ) . ' ';
		$url_pattern = ' href="admin.php?page=' . WPML_ST_FOLDER . '/menu/string-translation.php&context=%s"';
		$menu_names       = array_keys( $icl_menus_sync->string_translation_links );
		$menu_links  = array();
		foreach ( $menu_names as $menu_name ) {
			$menu_url_pattern = sprintf($url_pattern, urlencode($menu_name . ' menu'));
			$menu_links[ ] = sprintf( __( '<a%s>%s</a>', 'wpml-string-translation' ), $menu_url_pattern, $menu_name );
		}
		$menu_links_string = join( ', ', $menu_links );
		echo $menu_links_string;
		echo '</p>';
	}
}
do_action( 'icl_menu_footer' );
?>
</div>
