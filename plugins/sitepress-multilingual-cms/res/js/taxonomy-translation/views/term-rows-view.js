(function () {
    TaxonomyTranslation.views.TermRowsView = Backbone.View.extend({

        tagName: 'tbody',
        collection: TaxonomyTranslation.data.termRowsCollection,
        rowViews: [],
        start: 0,
        end: 10,
        count: -1,

        initialize: function (data, options) {
            var self = this;
            self.end = options.end;
            self.start = options.start;
        },
        getDisplayedRows: function () {

            var self = this;

            var displayedRows = self.collection;

            if (!displayedRows) {
                self.count = -1;
                return false;
            }

            var parentFilter = false;
            if (TaxonomyTranslation.mainView.filterView.parent) {
                parentFilter = TaxonomyTranslation.mainView.filterView.parent;
            }


            if (parentFilter) {
                displayedRows = displayedRows.filter(function (row) {
                    return row.parentOf(parentFilter)
                });
            }

            var untranslatedFilter = false;

            if (TaxonomyTranslation.mainView.filterView.untranslated) {
                untranslatedFilter = TaxonomyTranslation.mainView.filterView.untranslated;
            }

            if (untranslatedFilter) {
                displayedRows = displayedRows.filter(function (row) {
                    return !row.allTermsTranslated();
                });
            }

            var langFilter = false;

            if (TaxonomyTranslation.mainView.filterView.lang && TaxonomyTranslation.mainView.filterView.lang != 'all') {
                langFilter = TaxonomyTranslation.mainView.filterView.lang;
            }

            if (langFilter && langFilter != 'all' && (untranslatedFilter || parentFilter)) {
                displayedRows = displayedRows.filter(function (row) {
                    return !row.translatedIn(langFilter);
                });
            }

            var searchFilter = false;

            if (TaxonomyTranslation.mainView.filterView.search && TaxonomyTranslation.mainView.filterView.search != '') {
                searchFilter = TaxonomyTranslation.mainView.filterView.search;
            }

            if (searchFilter && searchFilter != '') {
                displayedRows = displayedRows.filter(function (row) {
                    if (langFilter && langFilter != 'all') {
                        return row.matchesInLang(searchFilter, langFilter);
                    } else {
                        return row.matches(searchFilter);
                    }
                });
            }

            self.count = displayedRows.length;

            return displayedRows;

        },
        render: function () {

            var self = this;
            var output = document.createDocumentFragment();
            self.rowViews = [];

            var displayedRows = self.getDisplayedRows();

            if (displayedRows) {
                displayedRows = displayedRows.slice(self.start, self.end);


                displayedRows.forEach(function (row) {
                    var newView = new TaxonomyTranslation.views.TermRowView({model: row});
                    self.rowViews.push(newView);
                    output.appendChild(newView.render().el);
                    newView.delegateEvents();
                });
            }
            self.$el.html(output);

            return self;

        }
    })
})(TaxonomyTranslation);
