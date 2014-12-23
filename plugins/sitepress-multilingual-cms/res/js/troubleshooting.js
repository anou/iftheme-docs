jQuery(document).ready(function () {
	var fix_post_types_and_source_langs_button = jQuery("#icl_fix_post_types");
	var updateTermNamesButton = jQuery("#icl-update-term-names");

	updateTermNamesButton.click(iclUpdateTermNames);

	fix_post_types_and_source_langs_button.click(
		function () {
			jQuery(this).attr('disabled', 'disabled');
			icl_repair_broken_translations();
			jQuery(this).after(icl_ajxloaderimg);

		}
	);

	function icl_repair_broken_translations () {
		jQuery.ajax(
			{
				url: ajaxurl,
				data: {
					action: 'icl_repair_broken_type_and_language_assignments'
				},
				success: function (response) {
					var rows_fixed = response.data;
					fix_post_types_and_source_langs_button.removeAttr('disabled');
					fix_post_types_and_source_langs_button.next().fadeOut();
					var text = '';
					if (rows_fixed > 0) {
						text = troubleshooting_strings.success_1 + rows_fixed + troubleshooting_strings.success_2;
					} else {
						text = troubleshooting_strings.no_problems;
					}
					var type_term_popup_html = '<div id="icl_fix_languages_and_post_types"><p>' + text + '</p></div>';
					jQuery(type_term_popup_html).dialog({
						modal: true,
						buttons: {
							Ok: function () {
								jQuery( this ).dialog( "close" );
							}
						}
					});
				}
			});
	}


	function iclUpdateTermNames() {

		var updatedTermNamesTable = jQuery('#icl-updated-term-names-table');

		/* First of all we get all selected rows and the displayed Term names. */

		var selectedTermRows = updatedTermNamesTable.find('input[type="checkbox"]');

		var selectedIDs = {};

		jQuery.each(selectedTermRows, function (index, selectedRow) {
			selectedRow = jQuery(selectedRow);
			if(selectedRow.is(':checked') && selectedRow.val() && selectedRow.attr('name') && jQuery.trim(selectedRow.attr('name')) !== ''){
				selectedIDs[selectedRow.val().toString()] = selectedRow.attr('name');
			}
		});

		var selectedIDsJSON = JSON.stringify(selectedIDs);

		jQuery.ajax(
			{
				url: ajaxurl,
				method: "POST",
				data: {
					action: 'wpml_update_term_names_troubleshoot',
					terms: selectedIDsJSON
				},
				success: function (response) {

					jQuery.each(response.data, function (index, id) {
						updatedTermNamesTable.find('input[type="checkbox"][value="'+ id +'"]').closest('tr').remove();
					});

					var remainingRows = jQuery('.icl-term-with-suffix-row');

					if (remainingRows.length === 0 ){
						updatedTermNamesTable.hide();
						jQuery('#icl-update-term-names').hide();
						jQuery('#icl-update-term-names-done').show();
					}

					var termSuffixUpdatedHTML = '<div id="icl_fix_term_suffixes"><p>' + troubleshooting_strings.suffixesRemoved + '</p></div>';
					jQuery(termSuffixUpdatedHTML).dialog({
	          modal: true,
	          buttons: {
	            Ok: function () {
	              jQuery( this ).dialog( "close" );
	            }
	          }

					});
				}
			});
	}
});