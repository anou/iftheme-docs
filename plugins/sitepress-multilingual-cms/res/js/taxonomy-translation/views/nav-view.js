(function () {
    TaxonomyTranslation.views.NavView = Backbone.View.extend({

        template: TaxonomyTranslation.getTemplate("nav"),
        model: TaxonomyTranslation.models.taxonomy,
        events: {"change .current-page": "goToPage"},

        initialize: function (data, options) {
            this.page = 1;
            this.perPage = options.perPage;
        },
        goToPage: function () {
            var self = this;
            var currentPageField = jQuery(".current-page");
            var page = currentPageField.val();

            if (page > 0 && page <= self.pages) {
                self.page = parseInt(page);
                self.trigger("newPage");
            } else {
                currentPageField.val(self.page);
            }

            return self;
        },
        render: function () {

            var self = this;

            var rows = TaxonomyTranslation.data.termRowsCollection.length;

            if (TaxonomyTranslation.mainView.termRowsView.count > -1) {
                rows = TaxonomyTranslation.mainView.termRowsView.count;
            }

            if (rows > self.perPage) {

                self.pages = Math.ceil(rows / self.perPage);

                self.$el.html(self.template({
                    page: self.page,
                    pages: self.pages,
                    items: rows
                }));

                var currentPageField = self.$el.find(".current-page");

                self.$el.find(".prev-page").on("click", function () {
                    currentPageField.val(self.page - 1).change();
                });
                self.$el.find(".next-page").on("click", function () {
                    currentPageField.val(self.page + 1).change();
                });
                self.$el.find(".last-page").on("click", function () {
                    currentPageField.val(self.pages).change();
                });
                self.$el.find(".first-page").on("click", function () {
                    currentPageField.val(1).change();
                });

                self.$el.show();
            } else {
                self.$el.hide();
            }


            return self;
        }
    })
})(TaxonomyTranslation);
