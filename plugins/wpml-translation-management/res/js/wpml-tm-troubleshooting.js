/*globals jQuery, data */
/** @namespace tm_troubleshooting_data.nonce.icl_sync_jobs */
/** @namespace tm_troubleshooting_data.nonce.icl_cms_id_fix */
/** @namespace tm_troubleshooting_data.nonce.icl_check_migration */

jQuery(document).ready(function () {

	jQuery('#icl_sync_jobs').bind('click', icl_sync_jobs);
	jQuery('#icl_cms_id_fix').bind('click', icl_cms_id_fix);
	jQuery('#icl_sync_cancelled').bind('click', icl_sync_cancelled);
	jQuery('#icl_ts_cancel_cancel').bind('click', icl_ts_cancel_cancel);
	jQuery('#icl_ts_cancel_ok').bind('click', icl_ts_cancel_ok);
    jQuery('#icl_reset_pro_but').bind('click', icl_reset_pro_but);

	function icl_sync_jobs() {
		jQuery(this).attr('disabled', 'disabled');
		jQuery(this).after(icl_ajxloaderimg);

		var ajax_data = {
			'action': 'icl_sync_jobs',
			'nonce':  tm_troubleshooting_data.nonce.icl_sync_jobs
		};

		jQuery.ajax({
			type:     "POST",
			url:      ajaxurl,
			data:     ajax_data,
			dataType: 'json',
			success:  function () {
				var icl_sync_jobs = jQuery('#icl_sync_jobs');
				icl_sync_jobs.removeAttr('disabled');
				alert(tm_troubleshooting_data.strings.done);
				icl_sync_jobs.next().fadeOut();
			},
			error:    function (jqXHR, status, error) {
				var parsed_response = jqXHR.statusText || status || error;
				alert(parsed_response);
			}
		});
	}

	function _icl_sync_cms_id(offset) {
		jQuery('#icl_cms_id_fix_prgs_cnt').html(offset + 1);

		var ajax_data = {
			'action': 'icl_cms_id_fix',
			'nonce':  tm_troubleshooting_data.nonce.icl_cms_id_fix,
			'offset': offset
		};

		jQuery.ajax({
			type:     "POST",
			url:      ajaxurl,
			data:     ajax_data,
			dataType: 'json',
			success:  function (msg) {
				var icl_cms_id_fix = jQuery('#icl_cms_id_fix');
				if (msg.errors > 0) {
					alert(msg.message);
					icl_cms_id_fix.removeAttr('disabled');
					icl_cms_id_fix.next().fadeOut();
					jQuery('#icl_cms_id_fix_prgs').fadeOut();
				} else {
					offset++;
					/** @namespace msg.cont */
					if (msg.cont) {
						_icl_sync_cms_id(offset);
					} else {
						alert(msg.message);
						icl_cms_id_fix.removeAttr('disabled');
						icl_cms_id_fix.next().fadeOut();
						jQuery('#icl_cms_id_fix_prgs').fadeOut();
					}
				}
			},
			error:    function (jqXHR, status, error) {
				var parsed_response = jqXHR.statusText || status || error;
				alert(parsed_response);
			}
		});
	}

	function icl_cms_id_fix() {
		jQuery(this).attr('disabled', 'disabled');
		jQuery(this).after(icl_ajxloaderimg);
		jQuery('#icl_cms_id_fix_prgs').fadeIn();
		_icl_sync_cms_id(0);
	}

	function icl_sync_cancelled() {
		jQuery(this).attr('disabled', 'disabled');
		jQuery(this).after(icl_ajxloaderimg);
		var icl_sync_cancelled_resp = jQuery('#icl_sync_cancelled_resp');
		icl_sync_cancelled_resp.html('');
		icl_sync_cancelled_resp.removeClass('updated');
		icl_sync_cancelled_resp.removeClass('error');

		/** @namespace tm_troubleshooting_data.nonce.sync_cancelled */
		var ajax_data = {
			'action': 'sync_cancelled',
			'nonce':  tm_troubleshooting_data.nonce.sync_cancelled
		};

		jQuery.ajax({
			type:     "POST",
			url:      ajaxurl, // + '&debug_action=sync_cancelled&nonce=<?php echo wp_create_nonce('sync_cancelled'); ?>',
			data:     ajax_data,
			dataType: 'json',
			success:  function (msg) {
				if (msg.errors > 0) {
					icl_sync_cancelled_resp.html(msg.message);
					icl_sync_cancelled_resp.addClass('error');
				} else {
					icl_sync_cancelled_resp.html(msg.message);
					icl_sync_cancelled_resp.addClass('updated');

					/** @namespace msg.data.t2c */
					if (msg.data && typeof msg.data.t2c != 'undefined') {
						jQuery('#icl_ts_t2c').val(msg.data.t2c);
					}
				}
				var icl_sync_cancelled = jQuery('#icl_sync_cancelled');
				icl_sync_cancelled.removeAttr('disabled');
				icl_sync_cancelled.next().fadeOut();
			},
			error:    function (jqXHR, status, error) {
				var parsed_response = jqXHR.statusText || status || error;
				alert(parsed_response);
			}
		});
	}

	function icl_ts_cancel_cancel() {
		jQuery('#icl_sync_cancelled_resp').html('');
		return false;
	}

	function icl_ts_cancel_ok() {
		jQuery(this).attr('disabled', 'disabled');
		jQuery(this).after(icl_ajxloaderimg);
		/** @namespace tm_troubleshooting_data.nonce.sync_cancelled_do_delete */
		var ajax_data = {
			'action': 'sync_cancelled_do_delete',
			'nonce':  tm_troubleshooting_data.nonce.sync_cancelled_do_delete,
			't2c':    jQuery('#icl_ts_t2c').val()
		};

		jQuery.ajax({
			type:     "POST",
			url:      ajaxurl, // + '&debug_action=sync_cancelled_do_delete&nonce=<?php echo wp_create_nonce('sync_cancelled_do_delete'); ?>',
			data:     ajax_data,
			dataType: 'json',
			success:  function (msg) {
				if (msg.errors > 0) {
					jQuery('#icl_sync_cancelled_resp').html(msg.message);
				} else {
					alert('Done');
					jQuery('#icl_sync_cancelled_resp').html('');
				}
				var icl_ts_cancel_ok = jQuery('#icl_ts_cancel_ok');
				icl_ts_cancel_ok.removeAttr('disabled');
				icl_ts_cancel_ok.next().fadeOut();
			},
			error:    function (jqXHR, status, error) {
				var parsed_response = jqXHR.statusText || status || error;
				alert(parsed_response);
			}
		});
		return false;
	}

    function icl_reset_pro_but() {
        var self = jQuery(this);
        if (!!self.hasClass('button-primary-disabled')) {
            return false;
        }

        self.attr('disabled', 'disabled');
        self.after(icl_ajxloaderimg);

        var ajax_data = {
            'action': 'reset_pro_translation_configuration',
            'nonce':  tm_troubleshooting_data.nonce.reset_pro_translation_configuration
        };

        jQuery.ajax({
            type:     "POST",
            url:      ajaxurl,
            data:     ajax_data,
            dataType: 'json',
            success:  function (msg) {
                alert(msg.data.message);
                var icl_reset_pro_but = jQuery('#icl_reset_pro_but');
                icl_reset_pro_but.removeAttr('disabled');
                icl_reset_pro_but.next().fadeOut();
            },
            error:    function (jqXHR, status, error) {
                var parsed_response = jqXHR.statusText || status || error;
                alert(parsed_response);
            }
        });
        return false;
    }
});