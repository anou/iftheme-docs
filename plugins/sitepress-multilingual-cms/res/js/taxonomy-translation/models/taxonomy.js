/*globals labels, ajaxurl */

(function () {
    TaxonomyTranslation.models.Taxonomy = Backbone.Model.extend({

        defaults: function () {
            return {
                name: false,
                taxonomy: false,
                terms: {},
                parents: {}
            };
        },

        initialize: function () {
            TaxonomyTranslation.data.termRowsCollection = new TaxonomyTranslation.collections.TermRows();
            this.setTaxonomy(this.get("taxonomy"));
        },

        setTaxonomy: function (taxonomy) {
            this.set("taxonomy", taxonomy, {silent: true});
            TaxonomyTranslation.data.termRowsCollection.reset();

            if (taxonomy !== undefined) {
                this.getTaxonomyTerms(taxonomy);
            } else {
                this.trigger('newTaxonomySet');
            }
        },

        getTaxonomyTerms: function (taxonomy) {
            var self = this;
            jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: {action: 'wpml_get_terms_and_labels_for_taxonomy_table', taxonomy: self.get("taxonomy")},
                success: function (response) {
                    var termsData = response.terms;
                    var labelsData = response.taxLabelTranslations;

                    if (response.defaultLanguage){
                        self.set('defaultLang', response.defaultLanguage);
                    }

                    if (labelsData) {
                        TaxonomyTranslation.data.translatedTaxonomyLabels = labelsData;
                        if(labelsData.st_default_lang){
                            self.set('stDefaultLang', labelsData.st_default_lang);
                        }
                    } else {
                        TaxonomyTranslation.data.translatedTaxonomyLabels = false;
                    }

                    if (termsData) {
                        self.processData(termsData);
                    } else {
                        self.trigger('newTaxonomySet');
                    }
                }
            });
        },

        processData: function (termsData) {

            var parentTermIDs = [];
            var parents = {};

            _.each(termsData, function (tridGroup) {
                var termsObject = {};
                _.each(TaxonomyTranslation.data.activeLanguages, function (lang, code) {
                    var term;
                    if (tridGroup[code] !== undefined && tridGroup[code].term_taxonomy_id) {
                        term = new TaxonomyTranslation.models.Term(tridGroup[code]);
                        var parent = term.get("parent");
                        if (parent > 0) {
                            parentTermIDs.push(parent);
                        }
                        termsObject[code] = term;
                    }
                });
                TaxonomyTranslation.data.termRowsCollection.add(new TaxonomyTranslation.models.TermRow({
                    trid: tridGroup.trid,
                    terms: termsObject
                }));
            });

            _.each(termsData, function (tridGroup) {
                _.each(TaxonomyTranslation.data.activeLanguages, function (lang, code) {
                    if (tridGroup[code] !== undefined && parentTermIDs.indexOf(tridGroup[code].term_id) !== -1) {
                        parents[tridGroup[code].term_id] = tridGroup[code].name;
                    }

                });
            });

            this.set("parents", parents, {silent: true});

            this.trigger('newTaxonomySet');
        },

        getOriginalName: function (termLang, trid) {
            var row = TaxonomyTranslation.data.termRowsCollection.get(trid);
            var lang;
            var terms = row.get("terms");
            lang = terms[termLang].get("source_language_code");

            if (!lang) {
                var i;
                for (i in TaxonomyTranslation.util.langCodes) {
                    var otherLang = TaxonomyTranslation.util.langCodes[i];
                    if (terms[otherLang] && terms[otherLang].get("name")) {
                        lang = otherLang;
                        break;
                    }
                }
            }
            return terms[lang].get("name");
        },

        saveLabel: function (singular, plural, lang) {

            var self = this;

            jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: 'wpml_tt_save_labels_translation',
                    singular: singular,
                    plural: plural,
                    taxonomy_language_code: lang,
                    taxonomy: self.get('taxonomy')
                },
                success: function (response) {

                    if (response.data) {
                        var newLabelData = response.data;

                        if (newLabelData.singular && newLabelData.general && newLabelData.lang) {

                            TaxonomyTranslation.data.translatedTaxonomyLabels[newLabelData.lang] = {
                                singular: newLabelData.singular,
                                general: newLabelData.general
                            };

                            WPML_Translate_taxonomy.callbacks.fire('wpml_tt_save_term_translation', self.get('taxonomy'));

                            self.trigger("labelTranslationSaved");
                            return self;
                        }
                    }
                    self.trigger("saveFailed");
                    return self;
                },
                error: function () {
                    self.trigger("saveFailed");
                    return self;
                }
            });
        }

    });
})(TaxonomyTranslation);
