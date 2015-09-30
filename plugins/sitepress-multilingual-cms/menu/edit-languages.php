<?php

class SitePress_EditLanguages {
	public $active_languages;
    public $upload_dir;
    public $is_writable = false;
    public $required_fields = array('code' => '', 'english_name' => '', 'translations' => 'array', 'flag' => '', 'default_locale' => '', 'tag' => '');
    public $add_validation_failed = false;
    private $built_in_languages = array();
    private $error = '';
    private $message = '';

	function __construct() {

        wp_enqueue_script(
            'edit-languages',
            ICL_PLUGIN_URL . '/res/js/languages/edit-languages.js',
            array( 'jquery', 'sitepress-scripts' ),
            ICL_SITEPRESS_VERSION,
            true
        );

		$lang_codes = icl_get_languages_codes();
        $this->built_in_languages = array_values($lang_codes);
        
        if(isset($_GET['action']) && $_GET['action'] == 'delete-language' && wp_create_nonce('delete-language' . @intval($_GET['id'])) == $_GET['icl_nonce']){
            $lang_id = @intval($_GET['id']);
            $this->delete_language($lang_id);
        }
        
		// Set upload dir
		$wp_upload_dir = wp_upload_dir();
		$this->upload_dir = $wp_upload_dir['basedir'] . '/flags';
		
		if (!is_dir($this->upload_dir)) {
			if (!mkdir($this->upload_dir)) {
				$this->error(__('Upload directory cannot be created. Check your permissions.','sitepress'));
			}
		}
		if (!$this->is_writable = is_writable($this->upload_dir)) {
			$this->error(__('Upload dir is not writable','sitepress'));
		}
		
		$this->migrate();
		
		$this->get_active_languages();
		
			// Trigger save.
		if (isset($_POST['icl_edit_languages_action']) && $_POST['icl_edit_languages_action'] == 'update') {
            if(wp_verify_nonce($_POST['_wpnonce'], 'icl_edit_languages')){
                $this->update();    
            }
		}
?>
<div class="wrap">
    <div id="icon-wpml" class="icon32"><br /></div>
    <h2><?php _e('Edit Languages', 'sitepress') ?></h2>
	<div id="icl_edit_languages_info">
<?php
	_e('This table allows you to edit languages for your site. Each row represents a language.
<br /><br />
For each language, you need to enter the following information:
<ul>
    <li><strong>Code:</strong> a unique value that identifies the language. Once entered, the language code cannot be changed.</li>
    <li><strong>Translations:</strong> the way the language name will be displayed in different languages.</li>
    <li><strong>Flag:</strong> the flag to display next to the language (optional). You can either upload your own flag or use one of WPML\'s built in flag images.</li>
    <li><strong>Default locale:</strong> This determines the locale value for this language. You should check the name of WordPress localization file to set this correctly.</li>
</ul>', 'sitepress'); ?>

	</div>
<?php
	if ($this->error) {
		echo '	<div class="below-h2 error"><p>'.$this->error.'</p></div>'; 
	}
    
    if ($this->message) {
        echo '    <div class="below-h2 updated"><p>'.$this->message.'</p></div>'; 
    }
    
?>
	<br />
	<?php $this->edit_table(); ?>
	<div class="icl_error_text icl_edit_languages_show" style="display: none; margin:10px;"><p><?php _e('Please note: language codes cannot be changed after adding languages. Make sure you enter the correct code.', 'sitepress'); ?></p></div>
</div>
<?php
	}

	function edit_table() {
?>
	<form enctype="multipart/form-data" action="<?php echo admin_url('admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/languages.php&amp;trop=1') ?>" method="post" id="icl_edit_languages_form">
	<input type="hidden" name="icl_edit_languages_action" value="update" />
	<input type="hidden" name="icl_edit_languages_ignore_add" id="icl_edit_languages_ignore_add" value="<?php echo ($this->add_validation_failed) ? 'false' : 'true'; ?>" />
    <?php wp_nonce_field('icl_edit_languages'); ?>
	<table id="icl_edit_languages_table" class="widefat" cellspacing="0">
            <thead>
                <tr>
                    <th><?php _e('Language name', 'sitepress'); ?></th>
					<th><?php _e('Code', 'sitepress'); ?></th>
					<th <?php if (!$this->add_validation_failed) echo 'style="display:none;" ';?>class="icl_edit_languages_show"><?php _e('Translation (new)', 'sitepress'); ?></th>
					<?php foreach ($this->active_languages as $lang) { ?>
					<th><?php _e('Translation', 'sitepress'); ?> (<?php echo $lang['english_name']; ?>)</th>
					<?php } ?>
					<th><?php _e('Flag', 'sitepress'); ?></th>
					<th><?php _e('Default locale', 'sitepress'); ?></th>
                    <th><?php _e('Encode URLs', 'sitepress'); ?></th>
                    <th><?php _e('Language tag', 'sitepress'); ?></th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <th><?php _e('Language name', 'sitepress'); ?></th>
					<th><?php _e('Code', 'sitepress'); ?></th>
					<th <?php if (!$this->add_validation_failed) echo 'style="display:none;" ';?>class="icl_edit_languages_show"><?php _e('Translation (new)', 'sitepress'); ?></th>
					<?php foreach ($this->active_languages as $lang) { ?>
					<th><?php _e('Translation', 'sitepress'); ?> (<?php echo $lang['english_name']; ?>)</th>
					<?php } ?>
					<th><?php _e('Flag', 'sitepress'); ?></th>
                    <th><?php _e('Default locale', 'sitepress'); ?></th>
					<th><?php _e('Encode URLs', 'sitepress'); ?></th>
                    <th><?php _e('Language tag', 'sitepress'); ?></th>
                    <th>&nbsp;</th>
                </tr>
            </tfoot>        
            <tbody>
<?php
		foreach ($this->active_languages as $lang) {
			$this->table_row($lang);
		}
		if ($this->add_validation_failed) {
			$_POST['icl_edit_languages']['add']['id'] = 'add';
			$new_lang = $_POST['icl_edit_languages']['add'];
		} else {
			$new_lang = array('id'=>'add');
		}
		$this->table_row($new_lang,true,true);
?>
			</tbody>
	</table>
	<p class="submit alignleft"><a href="admin.php?page=<?php echo ICL_PLUGIN_FOLDER ?>/menu/languages.php">&laquo;&nbsp;<?php _e('Back to languages', 'sitepress'); ?></a></p>

	<p class="submit alignright">
		<input type="button" name="icl_edit_languages_add_language_button" id="icl_edit_languages_add_language_button" value="<?php _e('Add Language', 'sitepress'); ?>" class="button-secondary"<?php if ($this->add_validation_failed) { ?> style="display:none;"<?php } ?> />&nbsp;<input type="button" name="icl_edit_languages_cancel_button" id="icl_edit_languages_cancel_button" value="<?php _e('Cancel', 'sitepress'); ?>" class="button-secondary icl_edit_languages_show"<?php if (!$this->add_validation_failed) { ?> style="display:none;"<?php } ?> />&nbsp;<input disabled type="submit" class="button-primary" value="<?php _e('Save', 'sitepress'); ?>" /></p>
    <br clear="all" />
	</form>
    
    <p>
        <?php wp_nonce_field('reset_languages_nonce', '_icl_nonce_rl'); ?>
        <input class="button-primary" type="button" id="icl_reset_languages" value="<?php _e('Reset languages', 'sitepress'); ?>" />        
        <span class="hidden"><?php _e('WPML will reset all language information to its default values. Any languages that you added or edited will be lost.','sitepress')?></span>
    </p>

<?php
	}

	function table_row( $lang, $echo = true, $add = false ){
        if ($lang['id'] == 'add') {
            $lang['english_name'] = isset($_POST['icl_edit_languages']['add']['english_name']) ? stripslashes_deep($_POST['icl_edit_languages']['add']['english_name']) : '';
            $lang['code'] = isset($_POST['icl_edit_languages']['add']['code']) ? $_POST['icl_edit_languages']['add']['code'] : '';
            $lang['default_locale'] = isset($_POST['icl_edit_languages']['add']['default_locale']) ? $_POST['icl_edit_languages']['add']['default_locale'] : '';
            $lang['flag'] = '';
            $lang['from_template'] = true;
            $lang['tag'] = isset($_POST['icl_edit_languages']['add']['tag']) ? $_POST['icl_edit_languages']['add']['tag'] : '';
        }
        global $sitepress;
        ?>
		
		<tr style="<?php if ($add && !$this->add_validation_failed) echo 'display:none; '; if ($add) echo 'background-color:yellow; '; ?>"<?php if ($add) echo ' class="icl_edit_languages_show"'; ?>>
					<td><input type="text" name="icl_edit_languages[<?php echo $lang['id']; ?>][english_name]" value="<?php echo $lang['english_name']; ?>"<?php if (!$add) { ?> readonly="readonly"<?php } ?> /></td>
					<td><input type="text" name="icl_edit_languages[<?php echo $lang['id']; ?>][code]" value="<?php echo $lang['code']; ?>" style="width:30px;"<?php if (!$add) { ?> readonly="readonly"<?php } ?> /></td>
					<td <?php if (!$this->add_validation_failed) echo 'style="display:none;" ';?>class="icl_edit_languages_show"><input type="text" name="icl_edit_languages[<?php echo $lang['id']; ?>][translations][add]" value="<?php echo isset($_POST['icl_edit_languages'][$lang['id']]['translations']['add']) ? stripslashes_deep($_POST['icl_edit_languages'][$lang['id']]['translations']['add']) : ''; ?>" /></td>
					<?php foreach($this->active_languages as $translation){ 
						if ($lang['id'] == 'add') {
							$value = isset($_POST['icl_edit_languages']['add']['translations'][$translation['code']]) ? $_POST['icl_edit_languages']['add']['translations'][$translation['code']] : '';
						} else {
							$value = isset($lang['translation'][$translation['id']]) ? $lang['translation'][$translation['id']] : '';
						}
					?>
					<td><input type="text" name="icl_edit_languages[<?php echo $lang['id']; ?>][translations][<?php echo $translation['code']; ?>]" value="<?php echo stripslashes_deep($value); ?>" /></td>
					<?php } ?>
					<td><?php if ($this->is_writable) { ?><input type="hidden" name="MAX_FILE_SIZE" value="100000" /><input name="icl_edit_languages[<?php echo $lang['id']; ?>][flag_file]" class="icl_edit_languages_flag_upload_field file" style="display:none; float:left;" type="file"  size="10" />&nbsp;<?php } ?><input type="text" name="icl_edit_languages[<?php echo $lang['id']; ?>][flag]" value="<?php echo $lang['flag']; ?>" class="icl_edit_languages_flag_enter_field" style="width:60px; float:left;" /><?php if ($this->is_writable) { ?><div style="float:left;"><label><input type="radio" name="icl_edit_languages[<?php echo $lang['id']; ?>][flag_upload]" value="true" class="radio icl_edit_languages_use_upload"<?php if ($lang['from_template']) { ?> checked="checked"<?php } ?> />&nbsp;<?php _e('Upload flag', 'sitepress'); ?></label><br /><label><input type="radio" name="icl_edit_languages[<?php echo $lang['id']; ?>][flag_upload]" value="false" class="radio icl_edit_languages_use_field"<?php if (!$lang['from_template']) { ?> checked="checked"<?php } ?> />&nbsp;<?php _e('Use flag from WPML', 'sitepress'); ?></label></div><?php } ?></td>
					<td><input type="text" name="icl_edit_languages[<?php echo $lang['id']; ?>][default_locale]" value="<?php echo $lang['default_locale']; ?>" style="width:60px;" /></td>
                    
                    <td>
                        <select name="icl_edit_languages[<?php echo $lang['id']; ?>][encode_url]">  
                            <option value="0" <?php if(empty($lang['encode_url'])): ?>selected="selected"<?php endif;?>><?php _e('No', 'sitepress') ?></option>
                            <option value="1" <?php if(!empty($lang['encode_url'])): ?>selected="selected"<?php endif;?>><?php _e('Yes', 'sitepress') ?></option>
                        </select>
                    </td>
                    
                    <td><input type="text" name="icl_edit_languages[<?php echo $lang['id']; ?>][tag]" value="<?php echo $lang['tag']; ?>" /></td>
                    
                    <td>
                        <?php
                        if (
                            !$add
                            && !in_array( $lang[ 'code' ], $this->built_in_languages )
                            && $lang[ 'code' ] != $sitepress->get_default_language()
                            && count( $this->active_languages ) > 1
                            ):
                        ?>
                            <a href="<?php echo admin_url('admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/languages.php&amp;trop=1&amp;action=delete-language&amp;id=' .
                            $lang['id'] . '&amp;icl_nonce=' . wp_create_nonce('delete-language' . $lang['id'])) ?>" title="<?php esc_attr_e('Delete', 'sitepress') 
                            ?>" onclick="if(!confirm('<?php echo esc_js(sprintf(__('Are you sure you want to delete this language?%sALL the data associated with this language will be ERASED!', 'sitepress'), "\n")) 
                            ?>')) return false;"><img src="<?php echo ICL_PLUGIN_URL ?>/res/img/close.png" alt="<?php esc_attr_e('Delete', 'sitepress') 
                            ?>" width="16" height="16" /></a>
                        <?php endif; ?>
                    </td>
                    
				</tr>
<?php
	}

	function get_active_languages() {
		global $sitepress, $wpdb;
		$this->active_languages = $sitepress->get_active_languages(true);        
        
		foreach ($this->active_languages as $lang) {
			foreach ($this->active_languages as $lang_translation) {
				$this->active_languages[$lang['code']]['translation'][$lang_translation['id']] = $sitepress->get_display_language_name($lang['code'], $lang_translation['code']);
			}
			$flag = $sitepress->get_flag($lang['code']);
			$this->active_languages[$lang['code']]['flag'] = $flag->flag;
			$this->active_languages[$lang['code']]['from_template'] = $flag->from_template;
			$this->active_languages[$lang['code']]['default_locale'] = $wpdb->get_var("SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE code='".$lang['code']."'");
            $this->active_languages[$lang['code']]['encode_url'] = $lang['encode_url'];
            $this->active_languages[$lang['code']]['tag'] = $lang['tag'];
		}
        
        
	}

	function insert_main_table($code, $english_name, $default_locale, $major = 0, $active = 0, $encode_url = 0, $tag = '') {
		global $wpdb;
        return $wpdb->insert($wpdb->prefix . 'icl_languages', array(
            'code'          => $code,
            'english_name'  => $english_name,
            'default_locale'=> $default_locale,
            'major'         => $major,
            'active'        => $active,
            'encode_url'    => $encode_url,
            'tag'           => $tag
        ));
	}

	function update_main_table($id, $code, $default_locale, $encode_url, $tag){
		global $wpdb;
    $wpdb->update($wpdb->prefix . 'icl_languages', array('code' => $code, 'default_locale' => $default_locale, 'encode_url'=>$encode_url, 'tag' => $tag), array('ID' => $id));
	}

	function insert_translation($name, $language_code, $display_language_code) {
		global $wpdb;
		$insert_sql       = "INSERT INTO {$wpdb->prefix}icl_languages_translations (name, language_code, display_language_code) VALUES(%s, %s, %s)";
		$insert_prepared = $wpdb->prepare( $insert_sql, array($name, $language_code, $display_language_code) );
		return $wpdb->query( $insert_prepared );
	}

	function update_translation($name, $language_code, $display_language_code) {
		global $wpdb;
		$update_sql      = "UPDATE {$wpdb->prefix}icl_languages_translations SET name=%s WHERE language_code = %s AND display_language_code = %s";
		$update_prepared = $wpdb->prepare( $update_sql, array($name, $language_code, $display_language_code) );
		$wpdb->query( $update_prepared );
	}

	function insert_flag($lang_code, $flag, $from_template) {
		global $wpdb;
		$insert_sql      = "INSERT INTO {$wpdb->prefix}icl_flags (lang_code, flag, from_template) VALUES(%s, %s, %s)";
		$insert_prepared = $wpdb->prepare( $insert_sql, array($lang_code, $flag, $from_template) );
		return $wpdb->query( $insert_prepared );
	}

	function update_flag($lang_code, $flag, $from_template) {
		global $wpdb;
		$update_sql      = "UPDATE {$wpdb->prefix}icl_flags SET flag= %s,from_template=%s WHERE lang_code = %s";
		$update_prepared = $wpdb->prepare( $update_sql, array($flag, $from_template, $lang_code) );
		$wpdb->query( $update_prepared );
	}
	
	function update() {
        
		// Basic check.
		if (!isset($_POST['icl_edit_languages']) || !is_array($_POST['icl_edit_languages'])){
			$this->error(__('Please, enter valid data.','sitepress'));
			return;
		}
		
		global $sitepress,$wpdb;
		
			// First check if add and validate it.
		if (isset($_POST['icl_edit_languages']['add']) && $_POST['icl_edit_languages_ignore_add'] == 'false') {
			if ($this->validate_one('add', $_POST['icl_edit_languages']['add'])) {
				$this->insert_one($this->sanitize($_POST['icl_edit_languages']['add']));
			}
				// Reset flag upload field.
			$_POST['icl_edit_languages']['add']['flag_upload'] = 'false';
		}
		
		foreach ($_POST['icl_edit_languages'] as $id => $data){
				// Ignore insert.
			if ($id == 'add') { continue; }
			
				// Validate and sanitize data.
			if (!$this->validate_one($id, $data)) continue;
			$data = stripslashes_deep($data);
			
				// Update main table.
			$this->update_main_table($id, $data['code'], $data['default_locale'], $data['encode_url'], $data['tag']);
            
            if (
                $wpdb->get_var(
                    $wpdb->prepare( "SELECT code FROM {$wpdb->prefix}icl_locale_map WHERE code = %s", $data[ 'code' ] )
                )
            ) {
                $wpdb->update($wpdb->prefix.'icl_locale_map', array('locale'=>$data['default_locale']), array('code'=>$data['code']));
            }else{
                $wpdb->insert($wpdb->prefix.'icl_locale_map', array('code'=>$data['code'], 'locale'=>$data['default_locale']));
            }
            
				// Update translations table.
			foreach ($data['translations'] as $translation_code => $translation_value) {
				
					// If new (add language) translations are submitted.
				if ($translation_code == 'add') {
					if ($this->add_validation_failed || $_POST['icl_edit_languages_ignore_add'] == 'true') {
						continue;
					}
					if (empty($translation_value)) {
						$translation_value = $data['english_name'];
					}
					$translation_code = $_POST['icl_edit_languages']['add']['code'];
				}
				
					// Check if update.
                if ( $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}icl_languages_translations WHERE language_code = %s AND display_language_code=%s",
                        $data[ 'code' ],
                        $translation_code
                    ) )
                ) {
					$this->update_translation($translation_value, $data['code'], $translation_code);
				} else {
					if (!$this->insert_translation($translation_value, $data['code'], $translation_code)) {
						$this->error(sprintf(__('Error adding translation %s for %s.', 'sitepress'), $data['code'], $translation_code));
					}
				}
			}
			
				// Handle flag.
			if ($data['flag_upload'] == 'true' && !empty($_FILES['icl_edit_languages']['name'][$id]['flag_file'])) {
				if ($filename = $this->upload_flag($id, $data)) {
					$data['flag'] = $filename;
					$from_template = 1;
				} else {
					$data['flag'] = $data['code'] . '.png';
					$this->error(__('Error uploading flag file.', 'sitepress'));
					$from_template = 0;
				}
			} else {
				if (empty($data['flag'])) {
					$data['flag'] = $data['code'] . '.png';
					$from_template = 0;
				} else {
                    $from_template = $data['flag_upload'] == 'true' ? 1 : 0;
				}
			}
				// Update flag table.
			$this->update_flag($data['code'], $data['flag'], $from_template);
				// Reset flag upload field.
			$_POST['icl_edit_languages'][$id]['flag_upload'] = 'false';
		}
			// Refresh cache.
		$sitepress->icl_language_name_cache->clear();
		$sitepress->icl_flag_cache->clear();
		delete_option('_icl_cache');
		
			// Unset ADD fields.
		if (!$this->add_validation_failed) {
			unset($_POST['icl_edit_languages']['add']);
		}
			// Reset active languages.
		$this->get_active_languages();
	}

	function insert_one($data) {
		global $sitepress, $wpdb;
		
		$data = stripslashes_deep(stripslashes_deep($data));
			// Insert main table.
		if (!$this->insert_main_table($data['code'], $data['english_name'], $data['default_locale'], 0, 1, $data['encode_url'], $data['tag'])) {
			$this->error(__('Adding language failed.', 'sitepress'));
			return false;
		}

		// add locale map
        $locale_exists = $wpdb->get_var($wpdb->prepare("SELECT code
                                                        FROM {$wpdb->prefix}icl_locale_map
                                                        WHERE code=%s", $data['code']));
        if($locale_exists){
            $wpdb->update($wpdb->prefix.'icl_locale_map', array('locale'=>$data['default_locale']), array('code'=>$data['code']));
        }else{
            $wpdb->insert($wpdb->prefix.'icl_locale_map', array('code'=>$data['code'], 'locale'=>$data['default_locale']));
        }
		
			// Insert translations.
        $all_languages = $sitepress->get_languages();
        foreach ( $all_languages as $key => $lang ) {

            // If submitted.
            if ( array_key_exists( $lang[ 'code' ], $data[ 'translations' ] ) ) {
                if ( empty( $data[ 'translations' ][ $lang[ 'code' ] ] ) ) {
                    $data[ 'translations' ][ $lang[ 'code' ] ] = $data[ 'english_name' ];
                }
                if ( !$this->insert_translation(
                    $data[ 'translations' ][ $lang[ 'code' ] ],
                    $data[ 'code' ],
                    $lang[ 'code' ]
                )
                ) {
                    $this->error(
                        sprintf(
                            __( 'Error adding translation %s for %s.', 'sitepress' ),
                            $data[ 'code' ],
                            $lang[ 'code' ]
                        )
                    );
                }
            } else {
                if ( !$this->insert_translation( $data[ 'english_name' ], $data[ 'code' ], $lang[ 'code' ] ) ) {
                    $this->error(
                        sprintf(
                            __( 'Error adding translation %s for %s.', 'sitepress' ),
                            $data[ 'code' ],
                            $lang[ 'code' ]
                        )
                    );
                }
            }
        }
		
			// Insert native name.
		if (!isset($data['translations']['add']) || empty($data['translations']['add'])) {
			$data['translations']['add'] = $data['english_name'];
		}
		if (!$this->insert_translation($data['translations']['add'], $data['code'], $data['code'])) {
			$this->error(__('Error adding native name.', 'sitepress'));
		}
		
			// Handle flag.
		if ($data['flag_upload'] == 'true' && !empty($_FILES['icl_edit_languages']['name']['add']['flag_file'])) {
			if ($filename = $this->upload_flag('add', $data)) {
				$data['flag'] = $filename;
				$from_template = 1;
			} else {
				$data['flag'] = $data['code'] . '.png';
				$from_template = 0;
			}
		} else {
			if (empty($data['flag'])) {
				$data['flag'] = $data['code'] . '.png';
			}
			$from_template = 0;
		}
		
			// Insert flag table.
		if (!$this->insert_flag($data['code'], $data['flag'], $from_template)) {
			$this->error(__('Error adding flag.', 'sitepress'));
		}
        SitePress_Setup::insert_default_category ( $data[ 'code' ] );
	}

	function validate_one($id, $data) {
	
		global $wpdb;
		
		// If insert, check if language code (unique) exists.
        $exists = $wpdb->get_var($wpdb->prepare("SELECT code
                                                 FROM {$wpdb->prefix}icl_languages WHERE
                                                 code=%s LIMIT 1", $data['code']));
		if ($exists && $id == 'add') {
            $this->error = __( 'Language code exists', 'sitepress' );
            $this->add_validation_failed = true;
            return false;
        }
		
		foreach ($this->required_fields as $name => $type) {
			if ($name == 'flag') {
				if ($data['flag_upload'] == 'true') {
					$check =  $_FILES['icl_edit_languages']['name'][$id]['flag_file'];
					if (empty($check)) continue;
					if (!$this->check_extension($check)) {
						if ($id == 'add') {
							$this->add_validation_failed = true;
						}
						return false;
					}
				}
				continue;
			}
			if (!isset($_POST['icl_edit_languages'][$id][$name]) || empty($_POST['icl_edit_languages'][$id][$name])) {
				if ($_POST['icl_edit_languages_ignore_add'] == 'true') {
					return false;
				}
				$this->error(__('Please, enter required data.','sitepress'));
				if ($id == 'add') {
					$this->add_validation_failed = true;
				}
				return false;
			}
			if ($type == 'array' && !is_array($_POST['icl_edit_languages'][$id][$name])) {
				if ($id == 'add') {
					$this->add_validation_failed = true;
				}
				$this->error(__('Please, enter valid data.','sitepress')); return false;
			}
		}
		return true;
	}
    
    function delete_language($lang_id){
        global $wpdb, $sitepress;
        $lang = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}icl_languages WHERE id=%d", $lang_id));
        if($lang){
            if(in_array($lang->code, $this->built_in_languages)){
                $error = __("Error: This is a built in language. You can't delete it.", 'sitepress');
            }else{
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_languages WHERE id=%d", $lang_id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_languages_translations WHERE language_code=%s", $lang->code));
                
                $translation_ids = $wpdb->get_col($wpdb->prepare("SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE language_code=%s", $lang->code));
                if($translation_ids){
                    $rids = $wpdb->get_col("SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id IN (" . wpml_prepare_in($translation_ids, '%d' ) . ")");
                    if($rids){
                        $job_ids = $wpdb->get_col("SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid IN (" . wpml_prepare_in($rids, '%d' ) . ")");
                        if($job_ids){
                            $wpdb->query("DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id IN (" . wpml_prepare_in($job_ids, '%d' ) . ")");
                        }
                    }    
                }
                
                // delete posts
                $post_ids = $wpdb->get_col(
												$wpdb->prepare("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type LIKE %s AND language_code=%s", 
																array( wpml_like_escape('post_') . '%', $lang->code ) )
																);
                remove_action('delete_post', array($sitepress,'delete_post_actions'));
                foreach($post_ids as $post_id){
                    wp_delete_post($post_id, true);
                }
                add_action('delete_post', array($sitepress,'delete_post_actions'));
                
                // delete terms
                remove_action('delete_term',  array($sitepress, 'delete_term'),1,3);
                $tax_ids = $wpdb->get_col(
												$wpdb->prepare("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type LIKE %s AND language_code=%s", 
																array( wpml_like_escape('tax_') . '%', $lang->code ) )
																);
                foreach($tax_ids as $tax_id){
                    $row = $wpdb->get_row($wpdb->prepare("SELECT term_id, taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $tax_id));
                    if($row){
                        wp_delete_term($row->term_id, $row->taxonomy);    
                    }
                }
                add_action('delete_term',  array($sitepress, 'delete_term'),1,3);
                
                // delete comments
                global $IclCommentsTranslation;
                remove_action('delete_comment', array($IclCommentsTranslation, 'delete_comment_actions'));
                foreach($post_ids as $post_id){
                    wp_delete_post($post_id, true);
                }
                add_action('delete_comment', array($IclCommentsTranslation, 'delete_comment_actions'));
                
                
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_translations WHERE language_code=%s", $lang->code));

                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_strings WHERE language=%s", $lang->code));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_string_translations WHERE language=%s", $lang->code));
                
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_locale_map WHERE code=%s", $lang->code));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}icl_flags WHERE lang_code=%s", $lang->code));
                
                icl_cache_clear(false);
                
                $sitepress->icl_translations_cache->clear();
                $sitepress->icl_flag_cache->clear();
                $sitepress->icl_language_name_cache->clear();
                
                $this->message(sprintf(__("The language %s was deleted.", 'sitepress'), '<strong>' . $lang->code . '</strong>'));
                
            }                
        }else{
            $error = __('Error: Language not found.', 'sitepress');
        }
        if(!empty($error)){
            $this->error($error);
        }            
    }
		
	function sanitize($data) {
		global $wpdb;
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				foreach ($value as $k => $v) {
					$data[$key][$k] = esc_sql($v);
				}
			}
			$data[$key] = esc_sql($value);
		}
		return $data;
	}

	function check_extension($file) {        
		$extension = substr($file, strrpos($file, '.') + 1);
		if (!in_array(strtolower($extension),array('png','gif','jpg'))) {
			$this->error(__('File extension not allowed.','sitepress'));
			return false;
		}
		return true;
	}

	function error($str = false) {
		$this->error .= $str . '<br />';
	}
    
    function message($str = false) {
        $this->message .= $str . '<br />';
    }
    

	function upload_flag($id, $data) {
		$filename = basename($_FILES['icl_edit_languages']['name'][$id]['flag_file']);
		$target_path = $this->upload_dir . '/' . $filename;
        
        $fileinfo = getimagesize($_FILES['icl_edit_languages']['tmp_name'][$id]['flag_file']);
        $validated = is_array($fileinfo) && in_array($fileinfo['mime'], array('image/gif', 'image/jpeg', 'image/png')) && $fileinfo['0'] > 0;
        
		if ($validated && move_uploaded_file($_FILES['icl_edit_languages']['tmp_name'][$id]['flag_file'], $target_path) ) {
            
            if(function_exists('wp_get_image_editor')){
                $image = wp_get_image_editor( $target_path );
                if ( ! is_wp_error( $image ) ) {
                    $image->resize( 18, 12, true );
                    $image->save( $target_path );
                }                
            }
            
    		return $filename;
		} else {
    		$this->error(__('There was an error uploading the file, please try again!','sitepress'));
			return false;
		}
	}

	function migrate() {
		global $sitepress, $sitepress_settings;
		if (!isset($sitepress_settings['edit_languages_flag_migration'])) {
			foreach( glob(get_stylesheet_directory().'/flags/*') as $filename ){
				rename($filename, $this->upload_dir . '/' . basename($filename));
			}
			$sitepress->save_settings(array('edit_languages_flag_migration' => 1));
		}
	}

}

global $icl_edit_languages;
$icl_edit_languages = new SitePress_EditLanguages;
