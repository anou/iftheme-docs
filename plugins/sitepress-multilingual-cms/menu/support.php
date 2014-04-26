
<div class="wrap">    
    <div id="icon-wpml" class="icon32" style="clear:both" ><br /></div>    
    <h2><?php _e('Support', 'sitepress') ?></h2>
    
    <p style="margin-top: 20px;">
        <?php _e('Technical support for clients is available via <a target="_blank" href="https://wpml.org/forums/">WPML forums</a>.','sitepress'); ?>
    </p>

    <?php
    
    // Installer plugin active?

	$wpml_plugins_list = SitePress::get_installed_plugins();
	$installer_on = defined('WPRC_VERSION') && WPRC_VERSION;

    echo '
        <table class="widefat" style="width: auto;">
            <thead>
                <tr>    
                    <th>' . __('Plugin Name', 'sitepress') . '</th>
                    <th style="text-align:right">' . __('Status', 'sitepress') . '</th>
                    <th>' . __('Active', 'sitepress') . '</th>
                    <th>' . __('Version', 'sitepress') . '</th>
                </tr>
            </thead>    
            <tbody>
        ';
    if($installer_on){
        if(!defined('ICL_WPML_ORG_REPO_ID')){ //backward compatibility
            $wpml_org_repo_id = $wpdb->get_var("
                SELECT id FROM {$wpdb->prefix}".WPRC_DB_TABLE_REPOSITORIES." WHERE repository_endpoint_url='http://api.wpml.org/'");
                define('ICL_WPML_ORG_REPO_ID', $wpml_org_repo_id);
        }
    }

	foreach ( $wpml_plugins_list as $name => $plugin_data ) {

		$plugin_name = $name;
		$file        = $plugin_data['file'];
		$dir = dirname($file);

		echo '<tr>';
		echo '<td><i class="icon18 '. $plugin_data['slug'] . '"></i>' . $plugin_name . '</td>';
		echo '<td align="right">';
		if ( empty( $plugin_data['plugin'] ) ) {
			if ( !$installer_on ) {
				echo __( 'Not installed' );
			} else {
				echo '<a href="' . admin_url( 'plugin-install.php?repos[]=' . ICL_WPML_ORG_REPO_ID . '&amp;tab=search&amp;s=' ) . urlencode( $plugin_name ) . '">' . __( 'Download', 'sitepress' ) . '</a>';
			}
		} else {
			if ( !$installer_on ) {
				echo __( 'Installed' );
			} else {
				echo '<a href="' . admin_url( 'plugin-install.php?repos[]=' . ICL_WPML_ORG_REPO_ID . '&amp;tab=search&amp;s=' ) . urlencode( $plugin_name ) . '">' . __( 'Installed', 'sitepress' ) . '</a>';
			}
		}
		echo '</td>';
		echo '<td align="center">';
		echo isset( $file ) && is_plugin_active( $file ) ? __( 'Yes', 'sitepress' ) : __( 'No', 'sitepress' );
		echo '</td>';
		echo '<td align="right">';
		echo isset( $plugin_data['plugin']['Version'] ) ? $plugin_data['plugin']['Version'] : __( 'n/a', 'sitepress' );
		echo '</td>';
		echo '</tr>';

	}

    echo '
            </tbody>
        </table>
    ';
        
    if(!$installer_on){
        echo '
            <br />
            <div class="icl_cyan_box">
                <p>' . __('The recommended way to install WPML on new sites and upgrade WPML on this site is by using our Installer plugin.', 'sitepress') . '</p>
                <br />
                <p>
                    <a class="button-primary" href="http://wp-compatibility.com/installer-plugin/">' . __('Download Installer', 'sitepress') . '</a>&nbsp;
                    <a href="https://wpml.org/faq/install-wpml/#2">' . __('Instructions', 'sitepress') . '</a>
                </p>
            </div>
        ';
    }else{
        echo '
            <br />
            <div class="icl_cyan_box">
                <p>' . __("To check for new versions, please visit your site's plugins section.", 'sitepress') . '</p>
            </div>
        ';
    }
    ?>
    
    <p style="margin-top: 20px;">
    <?php printf(__('For advanced access or to completely uninstall WPML and remove all language information, use the <a href="%s">troubleshooting</a> page.', 'sitepress'), admin_url('admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/troubleshooting.php')); ?> 
    </p>
    
    
</div>
