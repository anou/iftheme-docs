/*globals labels */

(function () {

    TaxonomyTranslation.views.TermView = Backbone.View.extend({

        tagName: "td",
        template: TaxonomyTranslation.getTemplate("termTranslated"),
        model: TaxonomyTranslation.models.Term,
        popUpView: false,
        events: {
            "click .icl_tt_term_name": "openPopUPTerm"
        },

        initialize: function () {
            var self = this;
            self.listenTo(self.model, 'translationSaved', self.render);
            self.listenTo(self.model, 'translationSaved', function(){
                jQuery('#tax-apply').show();
            });
        },

        render: function () {
            var self = this;

            if (!self.model.get("name")) {
                self.template = TaxonomyTranslation.getTemplate("termNotTranslated");
            } else {
                self.template = TaxonomyTranslation.getTemplate("termTranslated");
            }

            self.$el.html(
                self.template({
                    trid: self.model.get("trid"),
                    lang: self.model.get("language_code"),
                    name: self.model.get("name"),
                    level: self.model.get("level")
                })
            );

            self.delegateEvents();
            return self;
        },
        getViewID: function () {
            return this.model.get("trid") + "|" + this.model.get("lang");
        },
        openPopUPTerm: function (e) {

            e.preventDefault();

            var self = this;

            var trid = self.model.get("trid");
            var lang = self.model.get("language_code");
            if (trid && lang) {
                if (TaxonomyTranslation.classes.termPopUpView && typeof TaxonomyTranslation.classes.termPopUpView !== 'undefined') {
                    TaxonomyTranslation.classes.termPopUpView.close();
                }
                TaxonomyTranslation.classes.termPopUpView = new TaxonomyTranslation.views.TermPopUpView({model: self.model});

                var popUpHTML = TaxonomyTranslation.classes.termPopUpView.render().el;
                var popUpDomEl = jQuery("#" + trid + '-popup-' + lang);
                popUpDomEl.html(popUpHTML);
                var iclttForm = popUpDomEl.find('.icl_tt_form');
                iclttForm.show();
                iclttForm.first('input').focus();
                TaxonomyTranslation.classes.termPopUpView.$el.find('.term-save').on("click", TaxonomyTranslation.classes.termPopUpView.saveTerm.bind(TaxonomyTranslation.classes.termPopUpView));
            }

        }
    })
})(TaxonomyTranslation);
