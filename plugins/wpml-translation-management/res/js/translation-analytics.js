/**
 * Created by andrea.
 * Date: 16/06/14
 * Time: 14:22
 */
/*global jQuery, icl_ajx_url, wpActiveEditor */

var WPML_tm = WPML_tm || {};

WPML_tm.TranslationAnalytics = function($)
{
    var self = this;

    var _init = function()
	{
		_initialize_event_handlers();
		if ( jQuery("#ifm").length == 1 ) {
			iFrameResize({ "minHeight":350 },'#ifm');
		}
	}
	
	var _initialize_event_handlers = function () {
		jQuery('a#icl-toggle-analytics').on('click', _enable_link_click);
		jQuery('div#translation_analytics_options_div input[type=submit]').on('click', _button_link_click);
	
		// Hook when items in translation basket have been committed for translation.
		jQuery(document).on('wpml-tm-basket-commit-complete', _tm_basket_commit_complete);
		
	}

	var _enable_link_click = function (event) {
		event.preventDefault();
	
		var status = jQuery(this).data('status');
		var message = jQuery(this).data('message');
	
		_enable( status, message );
	}

	var _button_link_click = function (event) {
		event.preventDefault();
	
		var status = jQuery("input[name=translation_analitycs_enable]").is(":checked");
		if (!status) status = 0;
		var ta_enabled = jQuery("input[name=translation_analytics_enabled]").val();
		var message = false;
		if (ta_enabled == "1") {
			var message = jQuery("input[name=translation_analytics_alert_message]").val();
		}
	
		_enable( status, message );
	}
	
	var _enable = function (status, message) {
	
		var continue_operation = true;
		if (!status && message) {
			continue_operation = confirm(message);
		}
	
		if (continue_operation) {
	
			jQuery.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'icl-toggle-analytics',
					'icl-toggle-analytics-status': status
				},
				dataType: 'json',
				success: function (redirect_url) {
					if (!redirect_url) {
						location.reload();
					} else {
						location.href = redirect_url;
					}
				}
			});
	
		}
	}
	
	
	_tm_basket_commit_complete = function (event, progress_bar) {
		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'icl-promote-analytics'
			},
			success: function (result) {
				if (result != '') {
					jQuery(result).insertAfter(jQuery(progress_bar));
					jQuery(progress_bar).hide();
				
					jQuery('a#icl-toggle-analytics').off('click');
					jQuery('a#icl-toggle-analytics').on('click', _enable_link_click);
				}
			}
		});
	}
	
	_init();
}

jQuery(document).ready(function ($) {
	WPML_tm.translation_analytics = new WPML_tm.TranslationAnalytics();
});


