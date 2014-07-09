/*globals icl_ajx_url */

/**
 * Created by andrea.
 * Date: 23/01/14
 * Time: 17:28
 */

jQuery(document).ready(function ($) {

	var postEdit = postEdit || {};

	postEdit.$set_as_source_of_dialog = $('#set_as_source_of_dialog');
	postEdit.$no_posts_found_message = postEdit.$set_as_source_of_dialog.find('.js-no-posts-found');
	postEdit.$posts_found_container = postEdit.$set_as_source_of_dialog.find('.js-posts-found');
	postEdit.$ajax_loader = postEdit.$set_as_source_of_dialog.find('.js-ajax-loader');
	postEdit.$set_as_source_of_dialog_confirm = $("#set_as_source_of_dialog_confirm");

	postEdit.set_element_as_source_open = function() {

		event.preventDefault();

		alert(postEdit.$set_as_source_of_dialog.data('alert-text'));

		postEdit.$set_as_source_of_dialog.find('#post_search').val('');
		postEdit.$set_as_source_of_dialog.find('#assign_to_trid').val('');
		postEdit.$set_as_source_of_dialog.dialog('open');
		postEdit.set_element_as_source_data();

	};

	postEdit.set_element_as_source_data = function() {

		var $set_as_source_of_dialog_selector = $('#post_search', postEdit.$set_as_source_of_dialog );
		// var posts = [];

		var trid = $('#icl_set_as_source_of_trid').val();
		var post_type = $('#icl_set_as_source_of_post_type').val();
		var source_language = $('#icl_set_as_source_of_language').val();
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

				$set_as_source_of_dialog_selector.autocomplete({
					minLength: 0,
					source: posts,
					focus: function (event, ui) {
						$set_as_source_of_dialog_selector.val(ui.item.label);
						return false;
					},
					select: function (event, ui) {
						$set_as_source_of_dialog_selector.val(ui.item.label);
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
			}

			else {
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

	postEdit.set_element_as_source_init = function() {

		postEdit.$set_as_source_of_dialog.dialog({
			dialogClass: 'wpml-dialog wp-dialog',
			modal: true,
			autoOpen: false,
			closeOnEscape: true,
			buttons: [
				{
					text: postEdit.$set_as_source_of_dialog.data('cancel-label'),
					'class': 'button button-secondary',
					click: function() {
						$(this).dialog("close");
					}
				},
				{
					text: postEdit.$set_as_source_of_dialog.data('ok-label'),
					'class': 'button button-primary js-assign-button',
					click: function() {
						$(this).dialog("close");
						postEdit.set_element_as_source_do();
					}
				}
			]
		});

	}(); // Auto executable function

	postEdit.set_element_as_source_do = function() {

		var trid = $("#assign_to_trid").val();
		var post_type = $('#icl_set_as_source_of_post_type').val();
		var post_id = $('#icl_set_as_source_of_post_id').val();
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
				var $list = $('#set_as_source_of_dialog_confirm_list');
				$list.empty();
				var $ul = $('<ul />').appendTo( $list );

				$.each(posts, function () {
					var $li  = $('<li>').append('<span>[' + this.language + '] ' + this.title + '</span>');
					$li.appendTo ( $ul );
				});

				postEdit.$set_as_source_of_dialog_confirm.dialog({
					'dialogClass': 'wpml-dialog wp-dialog',
					resizable: false,
					autoOpen: true,
					modal: true,
					buttons: [
						{
							text: postEdit.$set_as_source_of_dialog_confirm.data('cancel-label'),
							'class': 'button button-secondary',
							click: function() {
								$(this).dialog("close");
								postEdit.$set_as_source_of_dialog.dialog('open');
							}
						},
						{
							text: postEdit.$set_as_source_of_dialog_confirm.data('assign-label'),
							'class': 'button button-primary js-confirm-connect-this-post',
							click: function() {

								console.log( $(this) );

								var $confirmButton = $('.js-confirm-connect-this-post');
								$confirmButton
									.prop('disabled', true)
									.removeClass('button-primary')
									.addClass('button-secondary');

								$('<span class="spinner" />').appendTo( $confirmButton );

								var nonce = $('#_icl_nonce_set_as_source_of').val();
								var data = 'icl_ajx_action=set_as_source_of&post_id=' + post_id + '&new_trid=' + trid + '&post_type=' + post_type + '&_icl_nonce=' + nonce;

								var request = $.ajax({
									type: "POST",
									url: icl_ajx_url,
									dataType: 'json',
									data: data
								});

								request.done(function (result) {
									if ( result ) {
										postEdit.$set_as_source_of_dialog.dialog("close");
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

	$('#icl_document_set_as_source_of_dropdown')
		.find('.js-set-post-as-source')
		.on('click', postEdit.set_element_as_source_open );

});