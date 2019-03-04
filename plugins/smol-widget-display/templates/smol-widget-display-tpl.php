<div class="wrap">
    <h2><?php _e('Smol Widget Display', 'smol-widget');?></h2>
    <form method="post" action="options.php"> 
        <?php @settings_fields('smol-widget-group'); ?>
        <?php @do_settings_fields('smol-widget-group'); ?>

        <?php do_settings_sections('smol-widget'); ?>

        <?php @submit_button(); ?>
    </form>
</div>