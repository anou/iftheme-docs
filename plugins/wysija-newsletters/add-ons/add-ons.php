<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly ?>

<?php
class MailPoet_Add_ons {
	/**
	 * Constructor
	 */
	public function __construct(){
		$this->plugin_path = WYSIJA_DIR;
		$this->wp_plugin_path = str_replace('wysija-newsletters', '', $this->plugin_path);
		$this->plugin_url = WYSIJA_URL;
		$this->image_url = 'http://ps.w.org/wysija-newsletters/assets/add-ons/';

		$this->mailpoet_add_on_activated_notice();
		$this->mailpoet_add_on_deactivated_notice();
	}

	/**
	 * Runs when the plugin is initialized.
	 */
	public function init_mail_poet_add_ons(){
		// Load JavaScript and stylesheets.
		$this->register_scripts_and_styles();
	}

	/**
	 * Registers and enqueues stylesheets for the
	 * administration panel and the public facing site.
	 */
	public function register_scripts_and_styles(){
		if(is_admin()){
			wp_register_style('mail_poet_add_ons', WYSIJA_URL.'css/add-ons.css');
			wp_enqueue_style('mail_poet_add_ons');
		} // end if
	} // end register_scripts_and_styles

	/**
	 * This notifies the user that the add-on plugin
	 * is now activated and returns them back to the
	 * add-ons page.
	 */
	public function mailpoet_add_on_activated_notice(){
		global $current_screen;

		require_once(ABSPATH.'/wp-admin/includes/plugin.php');

		if(isset($_GET['action']) && $_GET['action'] == 'activate' && isset($_GET['module'])){

			$plugin = plugin_basename($_GET['module']);
			$plugin_data = get_plugin_data($this->wp_plugin_path.''.$plugin);

			$plugin_name = esc_attr(str_replace(' ', '_', $plugin_data['Name']));
			$plugin_name = esc_attr(str_replace('&#039;', '_', $plugin_name));

			if(isset($_GET['requires'])){
				if(file_exists($this->wp_plugin_path.''.plugin_basename($_GET['requires']))){
					if(!WYSIJA::is_plugin_active($_GET['requires'])){
						$location = admin_url('admin.php?page=wysija_config&status=not-activated&add-on='.$plugin_name.'&requires='.esc_attr(str_replace(' ', '_', $_GET['requires_name'])).'#tab-add-ons');
						wp_safe_redirect($location);
						exit;
					}
				}
				else{
					$location = admin_url('admin.php?page=wysija_config&status=not-installed&add-on='.$plugin_name.'&requires='.esc_attr(str_replace(' ', '_', $_GET['requires_name'])).'#tab-add-ons');
					wp_safe_redirect($location);
					exit;
				}
			}

			// Activate the add-on plugin.
			activate_plugin($plugin);

			// Return back to add-on page.
			$location = admin_url('admin.php?page=wysija_config&status=activated&add-on='.$plugin_name.'#tab-add-ons');
			wp_safe_redirect($location);
			exit;
		}

		/**
		 * Display message if the plugin was not able to activate due
		 * to a required plugin is not active first.
		 */
		if($current_screen->parent_base == 'wysija_campaigns' && isset($_GET['status']) && $_GET['status'] == 'not-activated' || isset($_GET['status']) && $_GET['status'] == 'not-installed'){
			echo '<div id="message" class="error fade" style="display:block !important;"><p><strong>'.str_replace('_', ' ', $_GET['add-on']).'</strong> '.sprintf(__('was not activated as it requires <strong><a href="%s">%s</a></strong> to be installed and active first.', WYSIJA), admin_url('plugin-install.php?tab=search&type=term&s='.strtolower(str_replace(' ', '+', $_GET['requires']))), str_replace('_', ' ', $_GET['requires'])).' <input type="button" class="button" value="'.__('Hide this message', WYSIJA).'" onclick="document.location.href=\''.admin_url('admin.php?page=wysija_config#tab-add_ons').'\';"></p></div>';
		}

		// Display message once the add-on has been activated.
		if($current_screen->parent_base == 'wysija_campaigns' && isset($_GET['status']) && $_GET['status'] == 'activated'){
			echo '<div id="message" class="updated fade" style="display:block !important;"><p><strong>'.str_replace('_', ' ', $_GET['add-on']).'</strong> '.__('has been activated.', WYSIJA).'</p></div>';
		}
	}

	/**
	 * This notifies the user that the add-on plugin
	 * is now deactivated and returns them back to the
	 * add-ons page.
	 */
	public function mailpoet_add_on_deactivated_notice(){
		global $current_screen;

		require_once(ABSPATH.'/wp-admin/includes/plugin.php');

		if(isset($_GET['action']) && $_GET['action'] == 'deactivate' && isset($_GET['module'])){
			$plugin = plugin_basename($_GET['module']);
			$plugin_data = get_plugin_data($this->wp_plugin_path.''.$plugin);

			// Deactivate the add-on plugin.
			deactivate_plugins($plugin);

			// Return back to add-on page.
			$location = admin_url('admin.php?page=wysija_config&status=deactivated&add-on='.esc_html(str_replace(' ', '_', $plugin_data['Name'])).'#tab-add-ons');
			wp_safe_redirect($location);
			exit;
		}

		// Display message once the add-on has been deactivated.
		if($current_screen->parent_base == 'wysija_campaigns' && isset($_GET['status']) && $_GET['status'] == 'deactivated'){
			echo '<div id="message" class="updated fade" style="display:block !important;"><p><strong>'.str_replace('_', ' ', $_GET['add-on']).'</strong> '.__('has been de-activated.', WYSIJA).'</p></div>';
		}

	}

	/**
	 * Displays the add ons page and lists
	 * the plugins and services available.
	 */
	public function add_ons_page(){
		require_once(WYSIJA_DIR.'/add-ons/add-ons-list.php');
		?>
		<div class="module-container">
		<?php
		foreach(add_ons_list() as $plugin => $product){
			if(empty($product['official']) || $product['official'] == 'yes'){

				$status = ''; // Status class.

				/**
				 * Queries if the plugin is installed,
				 * active and meets the requirements
				 * it requires if any.
				 */
				if(file_exists($this->wp_plugin_path.''.plugin_basename($product['plugin_url']))){ $status .= ' installed'; }else{ $status .= ' not-installed'; }
				if(WYSIJA::is_plugin_active($product['plugin_url'])){ $status .= ' active'; }else{ $status .= ' inactive'; }
				if(empty($product['requires'])){ $status .= ' ready'; }
				else if(!empty($product['requires']) && file_exists($this->wp_plugin_path.''.plugin_basename($product['requires']))){
					$status .= ' ready';
					if(WYSIJA::is_plugin_active($product['requires'])){ $status .= ' ready'; }
					else{ $status .= ' not-ready'; }
				}
				else if(!empty($product['requires']) && !file_exists($this->wp_plugin_path.''.plugin_basename($product['requires']))){ $status .= ' not-ready'; }
				if(WYSIJA::is_plugin_active('wysija-newsletters-premium/index.php')){ $status .= ' premium-active'; }
			?>
			<div class="mailpoet-module<?php echo $status; ?>" id="product">
				<h3><?php echo $product['name']; ?></h3>

				<?php if(!empty($product['thumbnail'])){ ?><div class="mailpoet-module-image"><img src="<?php echo $this->image_url.$product['thumbnail']; ?>" width="100%" title="<?php echo $product['name']; ?>" alt=""></div><?php } ?>

				<div class="mailpoet-module-content">
					<div class="mailpoet-module-description">
					<p><?php echo $product['description']; ?></p>
					<p><?php if(!empty($product['review'])){ echo '<strong>'.sprintf(__('MailPoet says:&nbsp;<em>%s</em>', WYSIJA), $product['review']).'</strong>'; } ?></p>
					<?php if(WYSIJA::is_plugin_active('wysija-newsletters-premium/index.php') && !empty($product['premium_offer'])){ ?><p><strong><?php echo $product['premium_offer']; ?></strong></p><?php } ?>
				</div>

				<div class="mailpoet-module-actions">
					<?php if(!empty($product['author_url'])){ ?><a href="<?php echo esc_url($product['author_url']); ?>" target="_blank" class="button-primary website"><?php _e('Website', WYSIJA); ?></a>&nbsp;<?php } ?>
					<?php if($product['free'] == 'yes' && !empty($product['download_url'])){ if(!file_exists($this->wp_plugin_path.''.plugin_basename($product['plugin_url']))){ ?><a href="<?php echo $product['download_url']; ?>" target="_blank" class="button-primary download"><?php _e('Download Plugin', WYSIJA); ?></a>&nbsp;<?php } } ?>
					<?php if($product['service'] == 'no'){ ?>
					<?php if($product['on_wordpress.org'] == 'yes'){ if(!file_exists($this->wp_plugin_path.''.plugin_basename($product['plugin_url']))){ ?><a href="<?php echo admin_url('plugin-install.php?tab=search&type=term&s='.strtolower(str_replace(' ', '+', $product['search']))); ?>" class="button-primary install"><?php _e('Install from WordPress.org', WYSIJA); ?></a>&nbsp;<?php } } ?>
					<?php if(file_exists($this->wp_plugin_path.''.plugin_basename($product['plugin_url']))){ if(!WYSIJA::is_plugin_active($product['plugin_url'])){ ?><a href="<?php if(!empty($product['requires'])){ $requires = '&amp;requires='.$product['requires'].'&amp;requires_name='.$product['requires_name']; }else{ $requires = ''; } echo admin_url('admin.php?page=wysija_config&amp;action=activate&amp;module='.$product['plugin_url'].''.$requires); ?>" class="button-primary activate"><?php _e('Activate', WYSIJA); ?></a>&nbsp;<?php }else{ ?>
					<?php if(!empty($product['config_url'])){ ?><a href="<?php echo $product['config_url']; ?>" class="mailpoet-configure-button button-secondary"><?php _e('Configure', WYSIJA); ?></a><?php } } } ?>
					<?php } ?>
				</div>
			</div>
		</div>
		<?php
			} // end if local is yes.
		}
		?>
		</div><!-- .module-container -->

		<div class="submit-idea">
			<p><?php echo sprintf(__('Don\'t see the add-on you\'re looking for? <a href="%s">Submit it</a> in our contact form.', WYSIJA), 'http://www.mailpoet.com/contact/" target="blank'); ?></p>
		</div>

		<div class="module-container">
			<h2><?php _e('Works with MailPoet', WYSIJA); ?></h2>
			<p><?php _e('This list of plugins and services that might be useful to you. We don\'t offer support for them, and we\'re not affiliated with them.', WYSIJA); ?></p>

		<?php
		foreach(add_ons_list() as $plugin => $product){
			if($product['official'] == 'no'){

				$status = ''; // Status class.

				/**
				 * Queries if the plugin is installed,
				 * active and meets the requirements
				 * it requires if any.
				 */
				if(file_exists($this->wp_plugin_path.''.plugin_basename($product['plugin_url']))){ $status .= ' installed'; }else{ $status .= ' not-installed'; }
				if(WYSIJA::is_plugin_active($product['plugin_url'])){ $status .= ' active'; }else{ $status .= ' inactive'; }
				if(empty($product['requires'])){ $status .= ' ready'; }
				else if(!empty($product['requires']) && file_exists($this->wp_plugin_path.''.plugin_basename($product['requires']))){
					$status .= ' ready';
					if(WYSIJA::is_plugin_active($product['requires'])){ $status .= ' ready'; }
					else{ $status .= ' not-ready'; }
				}
				else if(!empty($product['requires']) && !file_exists($this->wp_plugin_path.''.plugin_basename($product['requires']))){ $status .= ' not-ready'; }
				if(WYSIJA::is_plugin_active('wysija-newsletters-premium/index.php')){ $status .= ' premium-active'; }
			?>
			<div class="mailpoet-module<?php echo $status; ?>" id="product">
				<h3><?php echo $product['name']; ?></h3>
				<?php if(!empty($product['thumbnail'])){ ?><div class="mailpoet-module-image"><img src="<?php echo $this->image_url.$product['thumbnail']; ?>" width="100%" title="<?php echo $product['name']; ?>" alt=""></div><?php } ?>

				<div class="mailpoet-module-content">
					<div class="mailpoet-module-description">
						<p><?php echo $product['description']; ?></p>
						<p><?php if(!empty($product['review'])){ echo '<strong>'.sprintf(__('MailPoet says:&nbsp;<em>%s</em>', WYSIJA), $product['review']).'</strong>'; } ?></p>
						<?php if(WYSIJA::is_plugin_active('wysija-newsletters-premium/index.php') && !empty($product['premium_offer'])){ ?><p><strong><?php echo $product['premium_offer']; ?></strong></p><?php } ?>
					</div>

					<div class="mailpoet-module-actions">
						<?php if(!empty($product['author_url'])){ ?><a href="<?php echo esc_url($product['author_url']); ?>" target="_blank" rel="external" class="button-primary website"><?php _e('Website', WYSIJA); ?></a>&nbsp;<?php } ?>
						<?php
						if($product['free'] == 'no' && !empty($product['purchase_url'])){
							if(!empty($product['plugin_url']) && !file_exists($this->wp_plugin_path.''.plugin_basename($product['plugin_url']))){ ?><a href="<?php echo $product['purchase_url']; ?>" target="_blank" rel="external" class="button-primary purchase"><?php _e('Purchase', WYSIJA); ?></a>&nbsp;
						<?php
							} // end if plugin is installed, don't show purchase button.
						} // end if product is not free.
						?>

						<?php
						if($product['service'] == 'no'){
							if($product['on_wordpress.org'] == 'yes'){
								if(!file_exists($this->wp_plugin_path.''.plugin_basename($product['plugin_url']))){ ?><a href="<?php echo admin_url('plugin-install.php?tab=search&type=term&s='.strtolower(str_replace(' ', '+', $product['search']))); ?>" class="button-primary install"><?php _e('Install from WordPress.org', WYSIJA); ?></a>&nbsp;
								<?php } // end if file_exists.
							} // end if $product['on_wordpress.org'];

							if(!empty($product['plugin_url']) && file_exists($this->wp_plugin_path.''.plugin_basename($product['plugin_url']))){
								if(!WYSIJA::is_plugin_active($product['plugin_url'])){ ?><a href="<?php if(!empty($product['requires'])){ $requires = '&amp;requires='.$product['requires'].'&amp;requires_name='.$product['requires_name']; }else{ $requires = ''; } echo admin_url('admin.php?page=wysija_config&amp;action=activate&amp;module='.$product['plugin_url'].''.$requires); ?>" class="button-primary activate"><?php _e('Activate', WYSIJA); ?></a>&nbsp;<?php }else{ ?>
								<?php if(!empty($product['config_url'])){ ?><a href="<?php echo $product['config_url']; ?>" class="mailpoet-configure-button button-secondary"><?php _e('Configure', WYSIJA); ?></a><?php } // end if ?>
							<?php
							}
						}
					} // end if plugin is installed. ?>
					</div>
				</div>
			</div>
		<?php
			} // end if local is yes.
		}
		?>
		</div><!-- .module-container -->
		<?php
	}

} // end class

/**
 * This loads the add ons class and displays the page.
 *
 * @init_mail_poet_add_ons();
 * @add_ons_page();
 */
function load_add_ons_manager(){
	$mailpoet_add_ons = new MailPoet_Add_ons();
	$mailpoet_add_ons->init_mail_poet_add_ons();
	$mailpoet_add_ons->add_ons_page();
}
load_add_ons_manager();
?>
