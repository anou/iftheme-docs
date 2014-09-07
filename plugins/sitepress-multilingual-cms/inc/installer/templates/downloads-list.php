                    <br clear="all" /><br />
                    <strong><?php _e('Downloads:', 'installer') ?></strong>
                    
                    <form method="post" class="otgsi_downloads_form">
                    
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>&nbsp;</th>
                                <th><?php _e('Plugin', 'installer') ?></th>
                                <th><?php _e('Current version', 'installer') ?></th>
                                <th><?php _e('Released', 'installer') ?></th>
                                <th><?php _e('Installed version', 'installer') ?></th>
                                <th>&nbsp;</th>
                                <th>&nbsp;</th>
                                <th>&nbsp;</th>
                            </tr>
                        </thead>                        
                        <tbody>
                        <?php foreach($product['downloads'] as $download): ?>
                            <tr>
                                <td>
                                    <label>
                                    <?php 
                                        $url =  $this->append_site_key_to_download_url($download['url'], $site_key); 
                                    ?>
                                    <input type="checkbox" name="downloads[]" value="<?php echo base64_encode(json_encode(array('url' => $url, 
                                        'basename' => $download['basename'], 'nonce' => wp_create_nonce('install_plugin_' . $url)))); ?>" <?php 
                                        if($this->plugin_is_installed($download['name'], $download['basename'], $download['version'])): ?>disabled="disabled"<?php endif; ?> />&nbsp;
                                        
                                    </label>                                
                                </td>
                                <td><?php echo $download['name'] ?></td>
                                <td><?php echo $download['version'] ?></td>
                                <td><?php echo date_i18n('F j, Y', strtotime($download['date'])) ?></td>
                                <td>
                                    <?php if($v = $this->plugin_is_installed($download['name'], $download['basename'])): $class = version_compare($v, $download['version'], '>=') ? 'installer-green-text' : 'installer-red-text'; ?>
                                    <span class="<?php echo $class ?>"><?php echo $v; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="installer-status-installing"><?php _e('installing...', 'installer') ?></span>
                                    <span class="installer-status-updating"><?php _e('updating...', 'installer') ?></span>
                                    <span class="installer-status-installed" data-fail="<?php _e('failed!', 'installer') ?>"><?php _e('installed', 'installer') ?></span>
                                    <span class="installer-status-updated" data-fail="<?php _e('failed!', 'installer') ?>"><?php _e('updated', 'installer') ?></span>
                                </td>
                                <td>
                                    <span class="installer-status-activating"><?php _e('activating', 'installer') ?></span>                                    
                                    <span class="installer-status-activated"><?php _e('activated', 'installer') ?></span>
                                </td>
                                <td class="for_spinner_js">&nbsp;</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <br />
                    <input type="submit" class="button-secondary" value="<?php esc_attr_e('Download', 'installer') ?>" disabled="disabled" />
                    &nbsp;
                    <label><input name="activate" type="checkbox" value="1" disabled="disabled" />&nbsp;<?php _e('Activate after download', 'installer') ?></label>
                    
                    <div class="installer-status-success"><p><?php _e('Operation complete!', 'installer') ?></p></div>
                    <span class="installer-revalidate-message hidden"><?php _e("Download failed!\n\nClick OK to revalidate your subscription or CANCEL to try again.", 'installer') ?></span>
                    </form>         
