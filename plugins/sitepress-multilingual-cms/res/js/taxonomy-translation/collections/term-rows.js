(function () {
    TaxonomyTranslation.collections.TermRows = Backbone.Collection.extend({
        model: TaxonomyTranslation.models.TermRow,


        initialize: function () {
        },


        fetch: function () {
        },

        getUntranslated: function () {
            return this.where({
                allTranslated: false
            });
        },

        getUntranslatedInLang: function (lang) {
            return this.where({
                lang: false
            });
        },

        getPage: function (page, perPage) {

        }


    });
})(TaxonomyTranslation);