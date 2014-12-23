(function () {

    TaxonomyTranslation.views.TaxonomyView = Backbone.View.extend({

        el: jQuery("#taxonomy-translation"),
        template: TaxonomyTranslation.getTemplate("taxonomy"),
        model: TaxonomyTranslation.models.Taxonomy,
        tag: "div",
        termRowViews: {},
        perPage: 10,

        initialize: function () {
            var self = this;
            this.fragment = document.createDocumentFragment();
            this.navView = new TaxonomyTranslation.views.NavView({model: self.model}, {perPage: self.perPage});
            this.listenTo(this.navView, 'newPage', this.render);
            this.filterView = new TaxonomyTranslation.views.FilterView({model: this.model});
            this.listenTo(this.filterView, 'updatedFilter', function () {
                self.navView.page = 1;
                self.renderRows();
            });
            this.termTableView = new TaxonomyTranslation.views.TableView({model: this.model}, {type: "terms"});
            this.labelTableView = new TaxonomyTranslation.views.TableView({model: this.model}, {type: "labels"});
            this.termRowsView = new TaxonomyTranslation.views.TermRowsView({collection: TaxonomyTranslation.data.termRowsCollection}, {
                start: 0,
                end: self.perPage
            });

            this.listenTo(this.model, 'newTaxonomySet', this.render);

        },

        setLabels: function () {
            var tax = this.model.get("taxonomy");
            var taxonomyDefaultLabel = TaxonomyTranslation.data.taxonomies[tax].label;
            this.headerTerms = labels.translate + " " + taxonomyDefaultLabel;
            this.summaryTerms = labels.summaryTerms.replace('%taxonomy%', taxonomyDefaultLabel);
            this.labelSummary = labels.summaryLabels.replace('%taxonomy%', taxonomyDefaultLabel);
        },

        renderRows: function () {
            var self = this;
            if (TaxonomyTranslation.data.termRowsCollection.length > 0) {
                self.termRowsView.start = (self.navView.page - 1 ) * self.perPage;
                self.termRowsView.end = self.termRowsView.start + self.perPage;
                var termRowsFragment = self.termRowsView.render().el;
                jQuery("#tax-table-terms").first('tbody').append(termRowsFragment);
            }

            self.navView.render();
        },

        render: function () {

            var self = this;

            this.setLabels();

            var mainFragment = document.createElement("div");

            mainFragment.innerHTML = (self.template({
                taxonomy: TaxonomyTranslation.data.taxonomies[self.model.get("taxonomy")],
                langs: TaxonomyTranslation.data.activeLanguages,
                headerTerms: self.headerTerms,
                summaryTerms: self.summaryTerms,
                labelSummary: self.labelSummary
            }));


            if (!self.filterFragment) {
                self.filterFragment = self.filterView.render().el;
            }

            mainFragment.querySelector("#wpml-taxonomy-translation-filters").appendChild(self.filterFragment);

            self.fragment.appendChild(mainFragment);


            if (TaxonomyTranslation.data.termRowsCollection.length > self.perPage && this.fragment.querySelector("#wpml-taxonomy-translation-terms-nav")) {
                var navFragment = self.navView.render().el;
                self.fragment.querySelector("#wpml-taxonomy-translation-terms-nav").appendChild(navFragment);
            }

            var termTableFragment = self.termTableView.render().el;
            mainFragment.querySelector("#wpml-taxonomy-translation-terms-table").appendChild(termTableFragment);

            if (TaxonomyTranslation.data.termRowsCollection.length > 0) {
                self.termRowsView.start = (self.navView.page - 1 ) * self.perPage;
                self.termRowsView.end = self.termRowsView.start + self.perPage;
                var termRowsFragment = self.termRowsView.render().el;
                mainFragment.querySelector("#tax-table-terms").appendChild(termRowsFragment);
            }

            if (TaxonomyTranslation.data.translatedTaxonomyLabels) {
                var labelTableFragment = self.labelTableView.render().el;

                mainFragment.querySelector("#wpml-taxonomy-translation-labels-table").appendChild(labelTableFragment);

                if (this.fragment.querySelector("#tax-table-labels")) {
                    var labelRowFragment = new TaxonomyTranslation.views.LabelRowView(({model: self.model})).render().el;
                    mainFragment.querySelector("#tax-table-labels").appendChild(labelRowFragment);
                }
            }

            jQuery("#taxonomy-translation").html(self.fragment);


            jQuery(".icl_tt_term_name").on("click", self.openPopUPTerm);

            jQuery(".icl_tt_label").on("click", self.openPopUPLabel);

            self.isRendered = true;

            jQuery('.loading-taxonomy').closest('div').hide();

            self.filterView.delegateEvents();
            self.delegateEvents();

            jQuery('.icl_tt_main_bottom').show();
            // WCML compatibility
            var taxonomySwitcher = jQuery("#icl_tt_tax_switch");
            var potentialHiddenSelectInput = jQuery('#tax-selector-hidden');
            var potentialHiddenTaxInput = jQuery('#tax-preselected');
            if (potentialHiddenSelectInput.length !== 0 && potentialHiddenSelectInput.val() && potentialHiddenTaxInput.length !== 0 && potentialHiddenTaxInput.val()) {
                var taxonomy = potentialHiddenTaxInput.val();
                taxonomySwitcher.closest('label').hide();
                jQuery('[id="term-table-header"]').hide();
                jQuery('[id="term-table-summary"]').hide();
            }

            return self;

        },

        selectTaxonomy: function () {
            var tax = jQuery("#icl_tt_tax_switch").val();
            if (tax != undefined && tax != this.model.get("taxonomy")) {
                this.model.setTaxonomy(tax);
            }
        }

    });
})(TaxonomyTranslation);