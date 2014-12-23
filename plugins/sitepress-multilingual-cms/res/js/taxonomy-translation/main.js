/*globals ajaxurl */

/* WCML compatibility */
WPML_Translate_taxonomy = {};
WPML_Translate_taxonomy.callbacks = jQuery.Callbacks();

(function () {


    jQuery(document).ready(function () {
        jQuery('.icl_tt_main_bottom').hide();
        jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            data: {action: 'wpml_get_table_taxonomies'},
            success: function (response) {
                if (response.taxonomies !== undefined && response.activeLanguages !== undefined) {

                    TaxonomyTranslation.data.activeLanguages = response.activeLanguages;
                    TaxonomyTranslation.data.taxonomies = response.taxonomies;
                    TaxonomyTranslation.util.init();

                    var headerHTML = TaxonomyTranslation.getTemplate("main")({taxonomies: TaxonomyTranslation.data.taxonomies});
                    jQuery("#wpml_tt_taxonomy_translation_wrap").html(headerHTML);
                    var spinner = jQuery('.loading-taxonomy');

                    // WCML compatibility
                    var taxonomySwitcher = jQuery("#icl_tt_tax_switch");
                    var potentialHiddenSelectInput = jQuery('#tax-selector-hidden');
                    var potentialHiddenTaxInput = jQuery('#tax-preselected');
                    if (potentialHiddenSelectInput.length !== 0 && potentialHiddenSelectInput.val() && potentialHiddenTaxInput.length !== 0 && potentialHiddenTaxInput.val()) {
                        var taxonomy = potentialHiddenTaxInput.val();
                        taxonomySwitcher.closest('label').hide();
                        jQuery('[id="term-table-header"]').hide();
                        jQuery('[id="term-table-summary"]').hide();
                        taxonomySwitcher.val(taxonomy);
                        TaxonomyTranslation.classes.taxonomy = new TaxonomyTranslation.models.Taxonomy({taxonomy: taxonomy});
                        TaxonomyTranslation.mainView = new TaxonomyTranslation.views.TaxonomyView({model: TaxonomyTranslation.classes.taxonomy});

                    } else {
                        taxonomySwitcher.one("change", function () {
                            spinner.show();
                            spinner.closest('div').show();
                            TaxonomyTranslation.classes.taxonomy = new TaxonomyTranslation.models.Taxonomy({taxonomy: jQuery(this).val()});
                            TaxonomyTranslation.mainView = new TaxonomyTranslation.views.TaxonomyView({model: TaxonomyTranslation.classes.taxonomy});
                            jQuery(" #icl_tt_tax_switch").on("change", function () {
                                spinner.show();
                                jQuery('.icl_tt_main_bottom').hide();
                                spinner.closest('div').show();
                                jQuery('#taxonomy-translation').html('');
                                TaxonomyTranslation.mainView.selectTaxonomy();
                            });

                        })

                    }
                }
            }
        });


    })
})(TaxonomyTranslation);