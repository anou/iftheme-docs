/*globals labels */

(function () {
    TaxonomyTranslation.views.TableView = Backbone.View.extend({

        template: TaxonomyTranslation.getTemplate("table"),
        tag: 'div',
        termsView: {},

        model: TaxonomyTranslation.models.Taxonomy,

        initialize: function (data,options) {
            this.type = options.type;
        },

        render: function () {

            var tableType = this.type;

            if (!TaxonomyTranslation.classes.taxonomy.get("taxonomy")) {
                return false;
            }

            var langs = TaxonomyTranslation.data.activeLanguages;

            var count = 1;

            if (tableType == "terms") {
                count = TaxonomyTranslation.data.termRowsCollection.length;
            }

            this.$el.html(this.template({
                langs: langs,
                tableType: tableType,
                count: count
            }));

            return this;
        },
        clear: function () {

        }

    })
})(TaxonomyTranslation);


