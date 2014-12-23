(function () {

    TaxonomyTranslation.views.TermRowView = Backbone.View.extend({

        tagName: "tr",
        model: TaxonomyTranslation.models.TermRow,
        termViews: {},

        render: function () {

            var termsFragments = {};
            var self = this;
            var langs = TaxonomyTranslation.util.langCodes;

            var terms = self.model.get("terms");

            _.each(langs, function (lang) {

                var term = terms[lang];
                if (term === undefined) {
                    term = new TaxonomyTranslation.models.Term({language_code: lang, trid: self.model.get("trid")});
                    terms[lang] = term;
                    self.model.set("terms", terms, {silent: true});
                }
                var newView = new TaxonomyTranslation.views.TermView({model: term});
                self.termViews[lang] = newView;
                termsFragments[lang] = newView.render().el;
            });

            var newRowFragment = document.createDocumentFragment();

            _.each(langs, function(lang){
                newRowFragment.appendChild(termsFragments[lang]);
            });

            self.$el.html(newRowFragment);

            return self;

        }
    })
}(TaxonomyTranslation));

