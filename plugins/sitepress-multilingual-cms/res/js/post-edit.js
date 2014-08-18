/*globals icl_ajx_url */

/**
 * Created by andrea.
 * Date: 23/01/14
 * Time: 17:28
 */

jQuery(document).ready(function ($) {

	var postEdit = postEdit || {};

	postEdit.$connect_translations_dialog = $('#connect_translations_dialog');
	postEdit.$no_posts_found_message = postEdit.$connect_translations_dialog.find('.js-no-posts-found');
	postEdit.$posts_found_container = postEdit.$connect_translations_dialog.find('.js-posts-found');
	postEdit.$ajax_loader = postEdit.$connect_translations_dialog.find('.js-ajax-loader');
	postEdit.$connect_translations_dialog_confirm = $("#connect_translations_dialog_confirm");

	postEdit.connect_element_translations_open = function(event) {

		if (typeof(event.preventDefault) !== 'undefined' ) {
			event.preventDefault();
		} else {
			event.returnValue = false;
		}

//		alert(postEdit.$connect_translations_dialog.data('alert-text'));

		postEdit.$connect_translations_dialog.find('#post_search').val('');
		postEdit.$connect_translations_dialog.find('#assign_to_trid').val('');
		postEdit.$connect_translations_dialog.dialog('open');
		postEdit.connect_element_translations_data();

	};

	postEdit.connect_element_translations_data = function() {

		var $connect_translations_dialog_selector = $('#post_search', postEdit.$connect_translations_dialog );

		var trid = $('#icl_connect_translations_trid').val();
		var post_type = $('#icl_connect_translations_post_type').val();
		var source_language = $('#icl_connect_translations_language').val();
		var nonce = $('#_icl_nonce_get_orphan_posts').val();
		var data = 'icl_ajx_action=get_orphan_posts&source_language=' + source_language + '&trid=' + trid + '&post_type=' + post_type + '&_icl_nonce=' + nonce;

		postEdit.$ajax_loader.show();

		var request = $.ajax({
			type: "POST",
			url: icl_ajx_url,
			dataType: 'json',
			data: data
		});

		request.done(function( posts ) {

			var $assignPostButton = $('.js-assign-button');

			if ( posts.length > 0 ) {

				postEdit.$posts_found_container.show();
				postEdit.$no_posts_found_message.hide();
				$assignPostButton.prop('disabled', false);

				$connect_translations_dialog_selector.autocomplete({
					minLength: 0,
					source: posts,
					focus: function (event, ui) {
						$connect_translations_dialog_selector.val(ui.item.label);
						return false;
					},
					select: function (event, ui) {
						$connect_translations_dialog_selector.val(ui.item.label);
						$("#assign_to_trid").val(ui.item.value);
						return false;
					}
				})
					.focus()
					.data("ui-autocomplete")._renderItem = function (ul, item) {
					return $("<li>")
						.append("<a>" + item.label + "</a>")
						.appendTo(ul);

				};
			} else {
				postEdit.$posts_found_container.hide();
				postEdit.$no_posts_found_message.show();
				$assignPostButton.prop('disabled', true);
			}

		});

		request.fail(function (xhr, ajaxOptions, thrownError) {
			console.log(xhr.status + '\n' + thrownError);
		});

		request.always(function() {
			postEdit.$ajax_loader.hide(); // Hide ajax loader always, no matter if ajax succeed or not.
		});

	};

	postEdit.connect_element_translations_init = function() {

		postEdit.$connect_translations_dialog.dialog({
			dialogClass: 'wpml-dialog wp-dialog',
			modal: true,
			autoOpen: false,
			closeOnEscape: true,
			buttons: [
				{
					text: postEdit.$connect_translations_dialog.data('cancel-label'),
					'class': 'button button-secondary',
					click: function() {
						$(this).dialog("close");
					}
				},
				{
					text: postEdit.$connect_translations_dialog.data('ok-label'),
					'class': 'button button-primary js-assign-button',
					click: function() {
						$(this).dialog("close");
						postEdit.connect_element_translations_do();
					}
				}
			]
		});

	}(); // Auto executable function

	postEdit.connect_element_translations_do = function() {

		var trid = $("#assign_to_trid").val();
		var post_type = $('#icl_connect_translations_post_type').val();
		var post_id = $('#icl_connect_translations_post_id').val();
		var nonce = $('#_icl_nonce_get_posts_from_trid').val();

		var data = 'icl_ajx_action=get_posts_from_trid&trid=' + trid + '&post_type=' + post_type + '&_icl_nonce=' + nonce;

		var request = $.ajax({
			type: "POST",
			url: icl_ajx_url,
			dataType: 'json',
			data: data
		});

		request.done(function ( posts ) {

			if ( posts.length > 0 ) {
				var $list = $('#connect_translations_dialog_confirm_list');
				$list.empty();
				var $ul = $('<ul />').appendTo( $list );

				var translation_set_has_source_language = false;

				$.each(posts, function () {
					var $li  = $('<li>').append('<span>[' + this.language + '] ' + this.title + '</span>');
					$li.appendTo ( $ul );
					if(this.source_language && !translation_set_has_source_language) {
						translation_set_has_source_language = true;
					}
				});

				var alert = $('<p>').append('<strong>' + postEdit.$connect_translations_dialog.data('alert-text') + '</strong>');
				alert.appendTo($list);

				var set_as_source_checkbox = $('<input type="checkbox" value="1" name="set_as_source" />');

				if(!translation_set_has_source_language) {
					set_as_source_checkbox.attr('checked', 'checked');
				}
				var action = $('<label>').append(set_as_source_checkbox).append(postEdit.$connect_translations_dialog.data('set_as_source-text'));
				action.appendTo($list);

				postEdit.$connect_translations_dialog_confirm.dialog({
					dialogClass: 'wpml-dialog wp-dialog',
					resizable: false,
					autoOpen: true,
					modal: true,
					buttons: [
						{
							text: postEdit.$connect_translations_dialog_confirm.data('cancel-label'),
							'class': 'button button-secondary',
							click: function() {
								$(this).dialog("close");
								postEdit.$connect_translations_dialog.dialog('open');
							}
						},
						{
							text: postEdit.$connect_translations_dialog_confirm.data('assign-label'),
							'class': 'button button-primary js-confirm-connect-this-post',
							click: function() {

								console.log( $(this) );

								var $confirmButton = $('.js-confirm-connect-this-post');
								$confirmButton
									.prop('disabled', true)
									.removeClass('button-primary')
									.addClass('button-secondary');

								$('<span class="spinner" />').appendTo( $confirmButton );

								var nonce = $('#_icl_nonce_connect_translations').val();

								var data_object = {
									icl_ajx_action: 'connect_translations',
									post_id: post_id,
									post_type: post_type,
									new_trid: trid,
									_icl_nonce: nonce,
									set_as_source: (set_as_source_checkbox.is(':checked') ? 1 : 0)
								};

//								var data = 'icl_ajx_action=connect_translations&post_id=' + post_id + '&new_trid=' + trid + '&post_type=' + post_type + '&_icl_nonce=' + nonce;

//								console.log( $(this) );
//
//								return;

								var request = $.ajax({
									type: "POST",
									url: icl_ajx_url,
									dataType: 'json',
									data: data_object
								});

								request.done(function (result) {
									if ( result ) {
										postEdit.$connect_translations_dialog.dialog("close");
										location.reload();
									}
								});

								request.fail(function (xhr, ajaxOptions, thrownError) {
									console.log(xhr.status + '\n' + thrownError);
								});

								request.always(function(){
									//
								});

							}
						}
					]
				});
			}
			else {
				console.log('no posts found');
				// TODO: Shouldn't we do something if posts.length === 0 ?
			}

		});

		request.fail(function (xhr, ajaxOptions, thrownError) {
			console.log(xhr.status + '\n' + thrownError);
		});

		request.always(function () {

		});

	};

	$('#icl_document_connect_translations_dropdown')
		.find('.js-set-post-as-source')
		.on('click', postEdit.connect_element_translations_open );

});