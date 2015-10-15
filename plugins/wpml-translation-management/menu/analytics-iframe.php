
<div class="wrap">
	<div id="icon-wpml" class="icon32"><br/></div>
	<h2><?php echo __('Translation Analytics', 'wpml-translation-analytics') ?></h2>
	<?php
	global $WPML_Translation_Analytics;
	$WPML_Translation_Analytics->show_messages();
	$WPML_Translation_Analytics->show_translation_analytics_dashboard();
	?>
</div>

<div id="iframe-bottom">
	<a href="#" class="button button-secondary" id="icl-toggle-analytics" data-status="0" data-message="<?php $WPML_Translation_Analytics->get_alert_message(); ?>"><?php echo __('Disable Translation Analytics', 'wpml-translation-analytics');?></a>
</div>
