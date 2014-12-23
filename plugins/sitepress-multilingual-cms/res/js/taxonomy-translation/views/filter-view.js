(function () {
    TaxonomyTranslation.views.FilterView = Backbone.View.extend({

        template: TaxonomyTranslation.getTemplate("filter"),
        model: TaxonomyTranslation.models.Taxonomy,
        tag: "div",
        untranslated: false,
        parent: 0,
        lang: 'all',
        search: '',

        events: {
            "change #child_of": "updateFilter",
            "change #status-select": "updateFilter",
            "change #in-lang": "updateFilter",
            "click #tax-apply": "updateFilter",
            "keyup #tax-search": "updateFilter"
        },

        initialize: function () {
            this.listenTo(this.model, 'newTaxonomySet', this.render);
        },

        render: function () {
            var self = this;
            var currentTaxonomy = self.model.get("taxonomy");

            if (!currentTaxonomy) {
                return false;
            } else {
                currentTaxonomy = TaxonomyTranslation.data.taxonomies[currentTaxonomy];
            }

            self.$el.html(self.template({
                langs: TaxonomyTranslation.data.activeLanguages,
                taxonomy: currentTaxonomy,
                parents: self.model.get("parents")
            }));

            return self;

        },

        updateFilter: function () {
            var self = this;


            var parent = self.$el.find("#child_of").val();
            if (parent != undefined && parent != -1) {
                self.parent = parent;
            } else {
                self.parent = 0;
            }

            var untranslated = self.$el.find("#status-select").val();

            if (untranslated != undefined && untranslated == 1) {
                self.untranslated = true;
            } else {
                self.untranslated = false;
            }

            var inLangSelect = self.$el.find("#in-lang");
            var inLangLabel = jQuery('#in-lang-label');

            if (self.untranslated) {
                var lang = inLangSelect.val();
                if (lang != undefined && lang != 'all') {
                    self.lang = lang;
                } else {
                    self.lang = 'all';
                }
                inLangSelect.show();
                inLangLabel.show();
            } else {
                self.lang = 'all';
                inLangSelect.hide();
                inLangLabel.hide();
            }

            var search = self.$el.find("#tax-search").val();

            if (search.length > 1) {
                self.search = search;
            } else {
                self.search = 0;
            }

            self.$el.find('#tax-apply').hide();
            self.trigger("updatedFilter");
            return self;
        }

    })
})(TaxonomyTranslation);
