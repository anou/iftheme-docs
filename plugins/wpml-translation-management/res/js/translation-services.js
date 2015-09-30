/*globals tm_ts_data*/
jQuery(document).ready(function ($) {
	/** @namespace tm_ts_data.nonce.translation_service_authentication */
	/** @namespace tm_ts_data.nonce.translation_service_toggle */

	var header = tm_ts_data.strings.header;
	var tip = tm_ts_data.strings.tip;
	var service_dialog = $('<div id="service_dialog"><h4>'+header+'</h4><div class="custom_fields_wrapper"></div><i>'+tip+'</i></div>');
	var ajax_spinner = $('<span class="spinner"></span>');
	ajax_spinner.show();
	ajax_spinner.css('visibility', 'visible');

	//Service image
	var activate_service_image = $('.js-activate-service');
	//Service 'Activate' link
	var activate_service_link = $('.js-activate-service-id');
	//Service 'Deactivate' link
	var deactivate_service_link = $('.js-deactivate-service');
	//Service 'Authenticate' link
	var authenticate_service_link = $('.js-authenticate-service');
	//Service 'De-authorize' link
	var invalidate_service_link = $('.js-invalidate-service');

	var custom_fields_serialized = $('#custom_fields_serialized');

	activate_service_image.bind('click', function (event) {
		if(event !== 'undefined') {
			if (typeof(event.preventDefault) !== 'undefined') {
				event.preventDefault();
			} else {
				event.returnValue = false;
			}
		}

		var link = $(this).closest('li').find('.js-activate-service-id');
		link.trigger('click');
		return false;
	});

	activate_service_link.bind('click', function (event) {
		if(event !== 'undefined') {
			if (typeof(event.preventDefault) !== 'undefined') {
				event.preventDefault();
			} else {
				event.returnValue = false;
			}
		}
		var button = jQuery(this);
		var service_id = $(this).data('id');
		toggle_service(service_id, button, 1);

		return false;
	});

	deactivate_service_link.bind('click', function (event) {
		if(event !== 'undefined') {
			if (typeof(event.preventDefault) !== 'undefined') {
				event.preventDefault();
			} else {
				event.returnValue = false;
			}
		}

		var button = jQuery(this);
		var service_id = $(this).data('id');
		toggle_service(service_id, button, 0);

		return false;
	});

	invalidate_service_link.bind('click', function (event) {
		if(event !== 'undefined') {
			if (typeof(event.preventDefault) !== 'undefined') {
				event.preventDefault();
			} else {
				event.returnValue = false;
			}
		}

		var button = jQuery(this);
		var service_id = $(this).data('id');
		translation_service_authentication(service_id, button, 1);

		return false;
	});

	authenticate_service_link.bind('click', function (event) {
		if(event !== 'undefined') {
			if (typeof(event.preventDefault) !== 'undefined') {
				event.preventDefault();
			} else {
				event.returnValue = false;
			}
		}

		var service_id = $(this).data('id');
		var custom_fields = $(this).data('custom-fields');

		service_authentication_dialog(custom_fields, service_id);

		return false;
	});

	function toggle_service(service_id, button, enable) {
		enable = typeof enable !== 'undefined' ? enable : 0;

		button.attr('disabled', 'disabled');
		button.after(ajax_spinner);

		var id = button.data('id');

		var ajax_data = {
			'action': 'translation_service_toggle',
			'nonce': tm_ts_data.nonce.translation_service_toggle,
			'service_id': service_id,
			'enable': enable
		};

		jQuery.ajax({
			type:     "POST",
			url: ajaxurl,
			data: ajax_data,
			dataType: 'json',
			success: function (msg) {
				if(msg.message !== 'undefined' && msg.message.trim() != '') {
						alert(msg.message);
				}
				if (msg.reload) {
					location.reload(true);
				} else {
					if(button) {
						button.removeAttr('disabled');
						button.next().fadeOut();
					}
				}
			},
			error: function (jqXHR, status, error) {
				var parsed_response = jqXHR.statusText || status || error;
				alert(parsed_response);
			}
		});
	}

	function service_authentication_dialog(custom_fields, service_id) {
		service_dialog.dialog(
			{
				dialogClass: 'wpml-dialog wp-dialog',
				width      : 'auto',
				title      : "Translation Services",
				modal      : true,
				open       : function (event, ui) {

					var custom_fields_wrapper = service_dialog.find('.custom_fields_wrapper');

					custom_fields_wrapper.empty();

					var custom_fields_form = $('<div></div>');
					custom_fields_form.appendTo(custom_fields_wrapper);

					var custom_fields_list = $('<ul></ul>');
					custom_fields_list.appendTo(custom_fields_form);

					$.each(
						custom_fields.custom_fields, function (i, item) {
							var custom_fields_list_item = $('<li></li>');
							custom_fields_list_item.appendTo(custom_fields_list);

							var $item_id = 'custom_field_' + item.name;
							var $item_label, $item_input;
							if (item.type != 'hidden') {
								$item_label = $('<label for="' + $item_id + '">' + item.label + ':</label>');
								$item_label.appendTo(custom_fields_list_item);
								$item_label.append('&nbsp;');
							}
							switch (item.type) {
								case 'text':
									$item_input = $('<input type="text" id="' + $item_id + '" class="custom_fields" name="' + item.name + '" />');
									break;
								case 'checkbox':
									$item_input = $('<input type="checkbox" id="' + $item_id + '" class="custom_fields" name="' + item.name + '" />');
									break;
								default:
									$item_input = $('<input type="hidden" id="' + $item_id + '" class="custom_fields" name="' + item.name + '" />');
									break;
							}
							$item_input.appendTo(custom_fields_list_item);
						}
					);

					$(':input', this).keyup(
						function (event) {
							if (event.keyCode == 13) {
								$(this).closest('.ui-dialog').find('.ui-dialog-buttonpane').find('button:first').click();
								return false;
							}
						}
					);

				},
				buttons    : [
					{
						text : "Submit",
						click: function () {
							hide_buttons();

							var custom_fields_input = $('.custom_fields');
							var custom_fields_data = {};
							$.each(
								custom_fields_input, function (i, item) {
									custom_fields_data[$(item).attr('name')] = $(item).val();
								}
							);
							custom_fields_serialized.val(JSON.stringify(custom_fields_data, null, ' '));
							translation_service_authentication(service_id, false, 0, null, show_buttons);
						}
					}, {
						text : "Cancel",
						click: function () {
							$(this).dialog("close");
						}
					}
				]
			}
		);
	}

	function hide_buttons() {
		ajax_spinner.appendTo( service_dialog );
		service_dialog.parent().find('.ui-dialog-buttonpane').fadeOut();
	}

	function show_buttons() {
		service_dialog.find(ajax_spinner).remove();
		service_dialog.parent().find('.ui-dialog-buttonpane').fadeIn();
	}

	function translation_service_authentication(service_id, button, invalidate, authentication_passed, authentication_failed) {
		var button_pane = $(this).parent().find('.ui-dialog-buttonpane').fadeOut();

		invalidate = typeof invalidate !== 'undefined' ? invalidate : 0;

		if(isNaN(service_id)) {
			alert('service_id isNAN');
			return false;
		}

		if(isNaN(invalidate)) {
			alert('invalidate isNAN');
			return false;
		}

		if(button) {
			button.attr('disabled', 'disabled');
			button.after(ajax_spinner);
		}

		var ajax_data = {
			'action': 'translation_service_authentication',
			'nonce': tm_ts_data.nonce.translation_service_authentication,
			'service_id': service_id,
			'invalidate': invalidate,
			'custom_fields': custom_fields_serialized.val()
		};

		jQuery.ajax({
			type:     "POST",
			url: ajaxurl,
			data: ajax_data,
			dataType: 'json',
			success: function (msg) {
				if(msg.message !== 'undefined' && msg.message.trim() != '') {
					alert(msg.message);
					if(msg.errors !== 'undefined' && msg.errors) {
						authentication_failed && authentication_failed();
						return false;
					}
				}

				authentication_passed && authentication_passed();

				if (msg.reload) {
					location.reload(true);
				} else {
					if (button) {
						button.removeAttr('disabled');
						button.next().fadeOut();
					}
				}
				return true;
			},
			error: function (jqXHR, status, error) {
				var parsed_response = jqXHR.statusText || status || error;
				alert(parsed_response);
				return false;
			}
		});
	}

});

