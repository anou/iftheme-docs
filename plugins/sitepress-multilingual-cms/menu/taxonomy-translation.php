<div class="wrap">
    <div id="icon-wpml" class="icon32" style="clear:both" ><br /></div>    
    <h2><?php _e('Taxonomy Translation', 'sitepress') ?></h2>
    
    <br />
    <?php 
    $WPML_Translate_Taxonomy = new WPML_Taxonomy_Translation();
    $WPML_Translate_Taxonomy->render();
    ?>    
    
    <?php do_action('icl_menu_footer'); ?>
    
</div>
