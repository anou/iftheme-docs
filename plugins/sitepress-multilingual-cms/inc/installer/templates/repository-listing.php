<?php if(($match = $this->get_matching_cp($repository)) && $match['exp']): ?>
<p class="alignright installer_highlight"><strong><?php printf('Price offers available until %s', date_i18n(get_option( 'date_format' ), $match['exp'])) ?></strong></p>
<?php endif; ?>


<h3><?php echo $repository['data']['name'] ?></h3>

<?php 
    $generic_product_name = $this->settings['repositories'][$repository_id]['data']['product-name'];
?>

<table class="widefat otgs_wp_installer_table">

    <tr>
        <td>&nbsp;</td>
        <td class="otgsi_register_product_wrap" align="center" valign="top">
            
            <?php // IF NO SUBSCRIPTION ?>
            <?php if(!$this->repository_has_subscription($repository_id)): ?>            
            
            <div style="text-align: right;">
                <span><?php _e('Already bought?', 'installer'); ?>&nbsp;</span>
                <a class="enter_site_key_js button-primary" href="#"><?php printf(__('Register %s', 'installer'), $generic_product_name); ?></a>&nbsp;&nbsp;                                                
                <form class="otgsi_site_key_form" method="post">
                <input type="hidden" name="action" value="save_site_key" />
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('save_site_key_' . $repository_id) ?>" />
                <input type="hidden" name="repository_id" value="<?php echo $repository_id ?>">
                <?php _e('2. Enter your site key', 'installer'); ?>
                <input type="text" size="10" name="site_key_<?php echo $repository_id ?>" placeholder="<?php echo esc_attr('site key') ?>" />
                <input class="button-primary" type="submit" value="<?php esc_attr_e('OK', 'installer') ?>" />
                <input class="button-secondary cancel_site_key_js" type="button" value="<?php esc_attr_e('Cancel', 'installer') ?>" />
                
                <div class="alignleft" style="margin-top:6px;"><?php printf(__('1. Go to your %s%s account%s and add this site URL: %s', 'installer'), 
                    '<a href="' . $this->settings['repositories'][$repository_id]['data']['url'] . '">',
                      $generic_product_name, '</a>', $this->site_url); ?></div>
                </form>
            </div>            
            
            <?php 
                $site_key = false;
                
            // IF SUBSCRIPTION
            else: 
                
                $site_key = $this->settings['repositories'][$repository_id]['subscription']['key']; 
                $subscription_type = $this->get_subscription_type_for_repository($repository_id);
                
                $upgrade_options = $this->get_upgrade_options($repository_id);
                $expired = false;
                
            ?>
            
            <?php if($this->repository_has_expired_subscription($repository_id)): $expired = true; ?>
                <div><p class="installer-warn-box"><?php _e('Subscription is expired. You need to either purchase a new subscription or upgrade if available.', 'installer') ?></p></div>
            <?php else: ?>
                <?php $this->show_subscription_renew_warning($repository_id, $subscription_type); ?>
            <?php endif; ?>
            
            <div class="alignright">
                <a class="remove_site_key_js button-secondary" href="#" data-repository=<?php echo $repository_id ?> data-confirmation="<?php esc_attr_e('Are you sure you want to unregister?', 'installer') ?>" data-nonce="<?php echo wp_create_nonce('remove_site_key_' . $repository_id) ?>"><?php printf(__("Unregister %s from this site", 'installer'), $generic_product_name) ?></a>&nbsp;            
                <a class="update_site_key_js button-secondary" href="#" data-repository=<?php echo $repository_id ?> data-nonce="<?php echo wp_create_nonce('update_site_key_' . $repository_id) ?>"><?php _e('Update this info', 'installer') ?></a>
            </div>
            
            <?php if(empty($expired)): ?>
            <div class="alignleft">
                <?php if($expires = $this->settings['repositories'][$repository_id]['subscription']['data']->expires): ?>
                    <?php printf(__('%s is registered on this site. You will receive automatic updates until %s', 'installer'), $generic_product_name, date_i18n('F j, Y', strtotime($expires))); ?>
                <?php else: ?>
                    <?php printf(__('%s is registered on this site. Your Lifetime account gives you updates for life.', 'installer'), $generic_product_name); ?>
                <?php endif; ?>
            </div>
            <?php endif; //if(empty($expired)) ?>
            
            <?php endif; // if(!repository_has_subscription) ?>
            
        </td>        
    </tr>
    
<?php $products_avaliable = array(); ?>
<?php foreach($repository['data']['packages'] as $package_id => $package): ?>
    <?php $products_avaliable = array(); ?>
    
    <tr>
        <td><img src="<?php echo $package['image_url'] ?>" /></td>
        <td>
            <p><strong><?php echo $package['name'] ?></strong></p>
            <p><?php echo $package['description'] ?></p>
            
            <?php foreach($package['products'] as $product): ?>
               
                <?php //1. SHOW BUY BASE PRODUCTS ?>
                <?php if(empty($subscription_type) || $expired): ?>
                
                <?php $products_avaliable[] = $product; ?>
                
                <span class="button-secondary">
                <a href="<?php echo $this->append_parameters_to_buy_url($product['url']) ?>"><?php echo $product['call2action'] ?></a> - 
                    <?php if(!empty($product['price_disc'])): ?>
                    <?php  printf('$%s %s$%d%s (USD)', $product['price_disc'], '&nbsp;&nbsp;<del>' , $product['price'] , '</del>') ?>
                    <?php else: ?>
                    <?php  printf('$%d (USD)', $product['price']) ?>
                    <?php endif; ?>
                </span>
                    
                &nbsp;&nbsp;&nbsp;
                
                <?php //2. SHOW RENEW OPTIONS ?>
                <?php elseif(isset($subscription_type) && $product['subscription_type'] == $subscription_type): ?>
                
                    <ul class="installer-products-list" style="display:inline">
                    <?php foreach($product['renewals'] as $renewal): ?>
                        <?php $products_avaliable[] = $renewal; ?>
                        <li class="button-secondary">
                            <a href="<?php echo $this->append_parameters_to_buy_url($renewal['url']) ?>"><?php echo $renewal['call2action'] ?></a> - <?php printf('$%d (USD)', $renewal['price']) ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                
                <?php //3. NOTHING ?>    
                <?php else: ?>                    
                    
                   <?php ; ?>
                    
                <?php endif; ?>
                
                <?php // 4. SHOW UPGRADE OPTIONS ?>
                <?php if(!empty($upgrade_options[$product['subscription_type']])): ?>
                    <ul class="installer-products-list" style="display:inline;">
                    <?php foreach($upgrade_options[$product['subscription_type']] as $stype => $upgrade): if($stype != $subscription_type) continue; ?>
                    
                        <?php $products_avaliable[] = $upgrade; ?>
                        
                        <li class="button-secondary">
                            <a href="<?php echo $this->append_parameters_to_buy_url($upgrade['url']) ?>"><?php echo $upgrade['call2action'] ?></a> - 
                            
                            <?php if(!empty($product['price_disc'])): ?>
                            <?php  printf('$%s %s$%d%s (USD)', $upgrade['price_disc'], '&nbsp;&nbsp;<del>' , $upgrade['price'] , '</del>') ?>
                            <?php else: ?>
                            <?php  printf('$%d (USD)', $upgrade['price']) ?>
                            <?php endif; ?>
                            
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
            <?php endforeach; ?>
            
            <?php // second loop, to show downloads ?>
            <?php 
                $showing_downloads = false;
                foreach($package['products'] as $product){                    
                    if(isset($subscription_type) && !$expired && $product['subscription_type'] == $subscription_type){
                        include $this->plugin_path() . '/templates/downloads-list.php';
                        $showing_downloads = true;
                    }
                }
            ?>
            
            <?php if(empty($products_avaliable) && empty($showing_downloads)): ?>
            <i><?php _e('No available products for this package.', 'installer') ?></i>
            <?php endif; ?>
            
           
        </td>        
    </tr>

<?php endforeach; ?>

</table>


<p><i><?php printf(__('This page lets you install plugins and update existing plugins. To remove any of these plugins, go to the %splugins%s page and if you have the permission to remove plugins you should be able to do this.', 'installer'), '<a href="' . admin_url('plugins.php') . '">' , '</a>'); ?></i></p>



<br />