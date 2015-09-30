<div>

	<p>
		<?php
			global $WPML_Translation_Analytics;
			echo $WPML_Translation_Analytics->get_enable_analytics_message();
		?>
	</p>

	<a class="button button-primary" href="#" id="icl-toggle-analytics" data-status="1">
		<?php
		echo __( "Start using Translation Analytics", 'sitepress' );
		?>
	</a>

</div>
