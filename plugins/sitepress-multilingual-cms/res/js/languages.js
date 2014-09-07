/*jslint browser: true, nomen: true*/
/*global iclSaveForm, iclSaveForm_success_cb, jQuery, alert, confirm, icl_ajx_url, icl_ajx_saved, icl_ajxloaderimg, icl_default_mark, icl_ajx_error, fadeInAjxResp, icl_cp_target, icl_ajxloaderimg_src, ColorPicker, icl_cp_target */

var icl_lp_font_current_normal = false;
var icl_lp_font_current_hover = false;
var icl_lp_background_current_normal = false;
var icl_lp_background_current_hover = false;
var icl_lp_font_other_normal = false;
var icl_lp_font_other_hover = false;
var icl_lp_background_other_normal = false;
var icl_lp_background_other_hover = false;
var icl_lp_border = false;
var icl_lp_flag = false;

// FOOTER
var icl_lp_footer_font_current_normal = false;
var icl_lp_footer_font_current_hover = false;
var icl_lp_footer_background_current_normal = false;
var icl_lp_footer_background_current_hover = false;
var icl_lp_footer_font_other_normal = false;
var icl_lp_footer_font_other_hover = false;
var icl_lp_footer_background_other_normal = false;
var icl_lp_footer_background_other_hover = false;
var icl_lp_footer_border = false;
var icl_lp_footer_flag = false;
var icl_lp_footer_background = false;

// Picker f
var cp;
cp = new ColorPicker();
cp.writeDiv();

function pickColor(color) {
    var icl_cp_target_element = jQuery('#' + icl_cp_target);
    icl_cp_target_element.val(color);
    icl_cp_target_element.trigger('keyup');
}

function iclHandleToggle() {
	var toggle_value_name = jQuery(this).data('toggle_value_name');
	var toggle_value_checked = jQuery(this).data('toggle_checked_value');
	var toggle_value_unchecked = jQuery(this).data('toggle_unchecked_value');
	var toggle_value = jQuery('[name="' + toggle_value_name + '"]');
	if(toggle_value.length == 0) {
		toggle_value = jQuery('<input type="hidden" name="' + toggle_value_name + '">');
		toggle_value.insertAfter(this);
	}
	if(jQuery(this).is(':checked')) {
		toggle_value.val(toggle_value_checked);
	} else {
		toggle_value.val(toggle_value_unchecked);
	}
}

function editingDefaultLanguage() {
    jQuery('#icl_change_default_button').hide();
    jQuery('#icl_save_default_button').show();
    jQuery('#icl_cancel_default_button').show();
    var enabled_languages = jQuery('#icl_enabled_languages').find('input');
    enabled_languages.show();
    enabled_languages.prop('disabled', false);
    jQuery('#icl_add_remove_button').hide();

}
function doneEditingDefaultLanguage() {
    jQuery('#icl_change_default_button').show();
    jQuery('#icl_save_default_button').hide();
    jQuery('#icl_cancel_default_button').hide();
    var enabled_languages = jQuery('#icl_enabled_languages').find('input');
    enabled_languages.hide();
    enabled_languages.prop('disabled', true);
    jQuery('#icl_add_remove_button').show();
}

function saveDefaultLanguage() {
    var enabled_languages, arr, def_lang;
    enabled_languages = jQuery('#icl_enabled_languages');
    arr = enabled_languages.find('input[type="radio"]');
    def_lang = '';
    jQuery.each(arr, function () {
        if (this.checked) {
            def_lang = this.value;
        }
    });
    jQuery.ajax({
        type: "POST",
        url: icl_ajx_url,
        data: "icl_ajx_action=set_default_language&lang=" + def_lang + '&_icl_nonce=' + jQuery('#set_default_language_nonce').val(),
        success: function (msg) {
            var enabled_languages_items, spl, selected_language, avail_languages_picker;
            spl = msg.split('|');
            selected_language = enabled_languages.find('li input[value="' + def_lang + '"]');
            if (spl[0] === '1') {
                fadeInAjxResp(icl_ajx_saved);
                avail_languages_picker = jQuery('#icl_avail_languages_picker');
                avail_languages_picker.find('input[value="' + spl[1] + '"]').prop('disabled', false);
                avail_languages_picker.find('input[value="' + def_lang + '"]').prop('disabled', true);
                enabled_languages_items = jQuery('#icl_enabled_languages').find('li');
                enabled_languages_items.removeClass('selected');
                selected_language.parent().parent().addClass('selected');
                selected_language.parent().append(' (' + icl_default_mark + ')');
                enabled_languages_items.find('input').removeAttr('checked');
                selected_language.attr('checked', 'checked');
                enabled_languages.find('input[value="' + spl[1] + '"]').parent().html(enabled_languages.find('input[value="' + spl[1] + '"]').parent().html().replace('(' + icl_default_mark + ')', ''));
                doneEditingDefaultLanguage();
                fadeInAjxResp('#icl_ajx_response', icl_ajx_saved);
                if (spl[2]) {
                    jQuery('#icl_ajx_response').html(spl[2]);
                } else {
                    location.href = location.href.replace(/#[\w\W]*/, '') + '&setup=2';
                }
            } else {
                //noinspection JSLint
                fadeInAjxResp('#icl_ajx_response', icl_ajx_error);
            }
        }
    });

}
function showLanguagePicker() {
    jQuery('#icl_avail_languages_picker').slideDown();
    jQuery('#icl_add_remove_button').hide();
    jQuery('#icl_change_default_button').hide();
}
function hideLanguagePicker() {
    jQuery('#icl_avail_languages_picker').slideUp();
    jQuery('#icl_add_remove_button').fadeIn();
    jQuery('#icl_change_default_button').fadeIn();
}
function saveLanguageSelection() {
    fadeInAjxResp('#icl_ajx_response', icl_ajxloaderimg);
    var arr = jQuery('#icl_avail_languages_picker').find('ul input[type="checkbox"]'), sel_lang = [];
    jQuery.each(arr, function () {
        if (this.checked) {
            sel_lang.push(this.value);
        }
    });
    jQuery.ajax({
        type: "POST",
        url: icl_ajx_url,
        data: "icl_ajx_action=set_active_languages&langs=" + sel_lang.join(',') + '&_icl_nonce=' + jQuery('#set_active_languages_nonce').val(),
        success: function (msg) {
            var spl = msg.split('|');
            if (spl[0] === '1') {
                fadeInAjxResp('#icl_ajx_response', icl_ajx_saved);
                jQuery('#icl_enabled_languages').html(spl[1]);
            } else {
                fadeInAjxResp('#icl_ajx_response', icl_ajx_error, true);
            }
            if (spl[2] === '1') {
                location.href = location.href.replace(/#[\w\W]*/, '');
            } else if (spl[2] === '-1') {
                location.href = location.href.replace(/#[\w\W]*/, '');
            } else {
                location.href = location.href.replace(/(#|&)[\w\W]*/, '');
            }

        }
    });
    hideLanguagePicker();
}

function iclLntDomains() {
    var language_negotiation_type, icl_lnt_domains_box;
    icl_lnt_domains_box = jQuery('#icl_lnt_domains_box');

	icl_lnt_domains_options = jQuery('#icl_lnt_domains');

    if (icl_lnt_domains_options.attr('checked')) {
		if(icl_lnt_domains_box.length) {
			jQuery('#icl_lnt_domains_box').remove();
		}
		icl_lnt_domains_options.parent().parent().append('<div id="icl_lnt_domains_box"></div>');
		icl_lnt_domains_box = jQuery('#icl_lnt_domains_box');
        icl_lnt_domains_box.html(icl_ajxloaderimg);
        language_negotiation_type = jQuery('#icl_save_language_negotiation_type').find('input[type="submit"]');
        language_negotiation_type.prop('disabled', true);
        jQuery.ajax({
            type: "POST",
            url: icl_ajx_url,
            data: 'icl_ajx_action=language_domains' + '&_icl_nonce=' + jQuery('#_icl_nonce_ldom').val(),
            success: function (resp) {
                icl_lnt_domains_box.html(resp);
                language_negotiation_type.prop('disabled', false);
            }
        });
    } else {
        if (icl_lnt_domains_box.length) {
            icl_lnt_domains_box.fadeOut('fast', function () {
                jQuery('#icl_lnt_domains_box').remove();
            });
        }
    }

    if (jQuery(this).val() != 1) {
        jQuery('#icl_use_directory_wrap').hide();
    } else {
        jQuery('#icl_use_directory_wrap').fadeIn();
    }


}

function iclToggleShowOnRoot() {
    if (jQuery(this).val() === 'page') {
        jQuery('#wpml_show_page_on_root_details').fadeIn();
        jQuery('#icl_hide_language_switchers').fadeIn();
    } else {
        jQuery('#wpml_show_page_on_root_details').fadeOut();
        jQuery('#icl_hide_language_switchers').fadeOut();
    }
}

function iclUseDirectoryToggle() {
    if (jQuery(this).attr('checked')) {
        jQuery('#icl_use_directory_details').fadeIn();
    } else {
        jQuery('#icl_use_directory_details').fadeOut();
    }
}

function iclSaveLanguageNegotiationType() {

    var ajx_resp, use_directory_wrap, negotiation_type, form_name, form_errors, used_urls;
    use_directory_wrap = jQuery('#icl_use_directory_wrap');
    negotiation_type = jQuery('#icl_save_language_negotiation_type');
    use_directory_wrap.find('.icl_error_text').hide();

    if (negotiation_type.find('[name=use_directory]:checked').length && (!negotiation_type.find('[name=show_on_root]:checked').length || negotiation_type.find('[name=show_on_root]:checked').val() === 'html_file') && !negotiation_type.find('[name=root_html_file_path]').val()) {
        use_directory_wrap.find('.icl_error_text.icl_error_1').fadeIn();
        return false;
    }

    form_name = jQuery(this).attr('name');
    form_errors = false;
    used_urls = [jQuery('#icl_ln_home').html()];
    jQuery('form[name="' + form_name + '"] .icl_form_errors').html('').hide();
    jQuery('form[name="' + form_name + '"] input').css('color', '#000');
    ajx_resp = jQuery('form[name="' + form_name + '"] .icl_ajx_response').attr('id');
    fadeInAjxResp('#' + ajx_resp, icl_ajxloaderimg);
    jQuery.ajaxSetup({async: false});
    jQuery('.validate_language_domain').each(function () {
        var lang_domain_input, lang_td, lang, language_domain;
        if (jQuery(this).prop('checked')) {
            lang = jQuery(this).attr('value');
            language_domain = jQuery('#ajx_ld_' + lang);
            language_domain.html(icl_ajxloaderimg);
            lang_td = jQuery('#icl_validation_result_' + lang);
            lang_domain_input = jQuery('#language_domain_' + lang);
            if (used_urls.indexOf(lang_domain_input.attr('value')) !== -1) {
                language_domain.html('');
                lang_domain_input.css('color', '#f00');
                form_errors = true;
            } else {
                used_urls.push(lang_domain_input.attr('value'));
                lang_domain_input.css('color', '#000');
                language_domain.load(icl_ajx_url,
                    {icl_ajx_action: 'validate_language_domain', url: lang_domain_input.attr('value'), _icl_nonce: jQuery('#_icl_nonce_vd').val()},
                    function (resp) {
                        jQuery('#ajx_ld_' + lang).html('');
                        if (resp === '0') {
                            lang_domain_input.css('color', '#f00');
                            form_errors = true;

                        }
                    });
            }
        }
    });
    jQuery.ajaxSetup({async: true});
    if (form_errors) {
        fadeInAjxResp('#' + ajx_resp, icl_ajx_error, true);
        return false;
    }
    jQuery.ajax({
        type: "POST",
        url: icl_ajx_url,
        data: "icl_ajx_action=" + jQuery(this).attr('name') + "&" + jQuery(this).serialize(),
        success: function (msg) {
            var form_errors, root_html_file, root_page, spl;
            spl = msg.split('|');
            if (spl[0] === '1') {
                fadeInAjxResp('#' + ajx_resp, icl_ajx_saved);

                if (jQuery('input[name=show_on_root]').length) {
                    root_html_file = jQuery('#wpml_show_on_root_html_file');
                    root_page = jQuery('#wpml_show_on_root_page');
                    if (root_html_file.prop('checked')) {
                        root_html_file.addClass('active');
                        root_page.removeClass('active');
                    }
                    if (root_page.prop('checked')) {
                        root_page.addClass('active');
                        root_html_file.removeClass('active');
                    }
                }

            } else {
                form_errors = jQuery('form[name="' + form_name + '"] .icl_form_errors');
                form_errors.html(spl[1]);
                form_errors.fadeIn();
                fadeInAjxResp('#' + ajx_resp, icl_ajx_error, true);
            }
        }
    });
    return false;
}


function iclSetupStep1() {
    jQuery.ajax({
        type: "POST",
        url: icl_ajx_url,
        data: "icl_ajx_action=setup_got_to_step1&_icl_nonce=" + jQuery('#_icl_nonce_gts1').val(),
        success: function () {
            location.href = location.href.replace(/#[\w\W]*/, '');
        }
    });
    return false;
}
function iclSetupStep2() {
    jQuery.ajax({
        type: "POST",
        url: icl_ajx_url,
        data: "icl_ajx_action=setup_got_to_step2&_icl_nonce=" + jQuery('#_icl_nonce_gts2').val(),
        success: function () {
            location.href = location.href.replace(/#[\w\W]*/, '');
        }
    });
    return false;
}

function iclUpdateLangSelPreview() {
    var preview = jQuery('#icl_lang_sel_preview_wrap');
    preview.html(icl_ajxloaderimg);
    preview.load(location.href + ' #icl_lang_sel_preview');
}

function iclRenderLangPreview() {

    var lang_sel_list, lang_sel_first, default_lang_link, lang_link, lang_sel;
    lang_sel = jQuery('#lang_sel');
    default_lang_link = lang_sel.find('li ul a');
    lang_sel_list = jQuery('#lang_sel_list');
    lang_link = lang_sel_list.find('ul a');
    if (icl_lp_font_other_normal) {
        default_lang_link.css('color', icl_lp_font_other_normal);
        lang_link.css('color', icl_lp_font_other_normal);
    }
    if (icl_lp_font_other_hover) {
        default_lang_link.unbind('hover');
        default_lang_link.hover(
            function () {
                jQuery(this).css('color', icl_lp_font_other_hover);
            },
            function () {
                jQuery(this).css('color', icl_lp_font_other_normal);
            }
        );
        lang_link.unbind('hover');
        lang_link.hover(
            function () {
                jQuery(this).css('color', icl_lp_font_other_hover);
            },
            function () {
                jQuery(this).css('color', icl_lp_font_other_normal);
            }
        );
    }

    if (icl_lp_background_other_normal) {
        default_lang_link.css('background-color', icl_lp_background_other_normal);
        default_lang_link.unbind('hover');
        default_lang_link.hover(
            function () {
                jQuery(this).css('background-color', '');
            },
            function () {
                jQuery(this).css('background-color', icl_lp_background_other_normal);
            }
        );

        lang_link.css('background-color', icl_lp_background_other_normal);
        lang_link.unbind('hover');
        lang_link.hover(
            function () {
                jQuery(this).css('background-color', '');
            },
            function () {
                jQuery(this).css('background-color', icl_lp_background_other_normal);
            }
        );
    }
    if (icl_lp_background_other_hover) {
        default_lang_link.unbind('hover');
        default_lang_link.hover(
            function () {
                jQuery(this).css('background-color', icl_lp_background_other_hover);
            },
            function () {
                jQuery(this).css('background-color', icl_lp_background_other_normal);
            }
        );
        lang_link.unbind('hover');
        lang_link.hover(
            function () {
                jQuery(this).css('background-color', icl_lp_background_other_hover);
            },
            function () {
                jQuery(this).css('background-color', icl_lp_background_other_normal);
            }
        );
    }

    if (icl_lp_border) {
        lang_sel.find('a').css('border-color', icl_lp_border);
        lang_sel.find('ul ul').css('border-color', icl_lp_border);

        lang_sel_list.find('a').css('border-color', icl_lp_border);
        lang_sel_list.find('ul').css('border-color', icl_lp_border);
    }

    if (jQuery('#icl_save_language_switcher_options').find(':checkbox[name="icl_lso_flags"]').attr('checked')) {
        lang_sel.find('.iclflag').show();
        lang_sel_list.find('.iclflag').show();
    } else {
        lang_sel.find('.iclflag').hide();
        lang_sel_list.find('.iclflag').hide();
    }

    lang_sel_first = lang_sel.find('a:first');
    if (icl_lp_font_current_normal) {
        lang_sel_first.css('color', icl_lp_font_current_normal);
        lang_sel_list.find('a.lang_sel_sel').css('color', icl_lp_font_current_normal);
    }
    if (icl_lp_font_current_hover) {
        jQuery('a:first, a.lang_sel_sel', lang_sel).unbind('hover');
        jQuery('a:first, a.lang_sel_sel', lang_sel).hover(
            function () {
                jQuery(this).css('color', icl_lp_font_current_hover);
            },
            function () {
                jQuery(this).css('color', icl_lp_font_current_normal);
                jQuery('#lang_sel').find('a.lang_sel_sel').css('color', icl_lp_font_current_normal);
            }
        );
        lang_sel_list.find('a.lang_sel_sel').unbind('hover');
        lang_sel_list.find('a.lang_sel_sel').hover(
            function () {
                jQuery(this).css('color', icl_lp_font_current_hover);
            },
            function () {
                jQuery(this).css('color', icl_lp_font_current_normal);
                jQuery('#lang_sel_list').find('a.lang_sel_sel').css('color', icl_lp_font_current_normal);
            }
        );
    }

    if (icl_lp_background_current_normal) {
        lang_sel_first.css('background-color', icl_lp_background_current_normal);
        lang_sel_list.find('a.lang_sel_sel').css('background-color', icl_lp_background_current_normal);

        lang_sel_first.unbind('hover');
        lang_sel_first.hover(
            function () {
                jQuery(this).css('background-color', '');
            },
            function () {
                jQuery(this).css('background-color', icl_lp_background_current_normal);
            }
        );

        lang_sel_list.find('a.lang_sel_sel').unbind('hover');
        lang_sel_list.find('a.lang_sel_sel').hover(
            function () {
                jQuery(this).css('background-color', '');
            },
            function () {
                jQuery(this).css('background-color', icl_lp_background_current_normal);
            }
        );

    }

    if (icl_lp_background_current_hover) {
        lang_sel_first.unbind('hover');
        lang_sel_first.hover(
            function () {
                jQuery(this).css('background-color', icl_lp_background_current_hover);
            },
            function () {
                jQuery(this).css('background-color', icl_lp_background_current_normal);
            }
        );
        jQuery('a:first, a.lang_sel_sel', lang_sel).unbind('hover');
        jQuery('a:first, a.lang_sel_sel', lang_sel).hover(
            function () {
                jQuery(this).css('background-color', icl_lp_background_current_hover);
            },
            function () {
                jQuery(this).css('background-color', icl_lp_background_current_normal);
            }
        );
    }

}

function iclUpdateLangSelQuickPreview() {
    var preview_name, preview_value;
    preview_name = jQuery(this).attr('name');
    preview_value = jQuery(this).val();
    switch (preview_name) {
        case 'icl_lang_sel_config[font-current-normal]':
            icl_lp_font_current_normal = preview_value;
            break;
        case 'icl_lang_sel_config[font-current-hover]':
            icl_lp_font_current_hover = preview_value;
            break;
        case 'icl_lang_sel_config[background-current-normal]':
            icl_lp_background_current_normal = preview_value;
            break;
        case 'icl_lang_sel_config[background-current-hover]':
            icl_lp_background_current_hover = preview_value;
            break;
        case 'icl_lang_sel_config[font-other-normal]':
            icl_lp_font_other_normal = preview_value;
            break;
        case 'icl_lang_sel_config[font-other-hover]':
            icl_lp_font_other_hover = preview_value;
            break;
        case 'icl_lang_sel_config[background-other-normal]':
            icl_lp_background_other_normal = preview_value;
            break;
        case 'icl_lang_sel_config[background-other-hover]':
            icl_lp_background_other_hover = preview_value;
            break;
        case 'icl_lang_sel_config[border]':
            icl_lp_border = preview_value;
            break;
        case 'icl_lso_flags':
            icl_lp_flag = jQuery(this).attr('checked');
            break;
    }
    iclRenderLangPreview();
}

function iclUpdateLangSelColorScheme() {
    var scheme = jQuery(this).val();
    if (scheme && confirm(jQuery(this).next().html())) {
        jQuery('#icl_lang_preview_config').find('input[type="text"]').each(function () {
            var this_n, value;
            this_n = jQuery(this).attr('name').replace('icl_lang_sel_config[', '').replace(']', '');
            value = jQuery('#icl_lang_sel_config_alt_' + scheme + '_' + this_n).val();
            jQuery(this).val(value);

            switch (jQuery(this).attr('name')) {
                case 'icl_lang_sel_config[font-current-normal]':
                    icl_lp_font_current_normal = value;
                    break;
                case 'icl_lang_sel_config[font-current-hover]':
                    icl_lp_font_current_hover = value;
                    break;
                case 'icl_lang_sel_config[background-current-normal]':
                    icl_lp_background_current_normal = value;
                    break;
                case 'icl_lang_sel_config[background-current-hover]':
                    icl_lp_background_current_hover = value;
                    break;
                case 'icl_lang_sel_config[font-other-normal]':
                    icl_lp_font_other_normal = value;
                    break;
                case 'icl_lang_sel_config[font-other-hover]':
                    icl_lp_font_other_hover = value;
                    break;
                case 'icl_lang_sel_config[background-other-normal]':
                    icl_lp_background_other_normal = value;
                    break;
                case 'icl_lang_sel_config[background-other-hover]':
                    icl_lp_background_other_hover = value;
                    break;
                case 'icl_lang_sel_config[border]':
                    icl_lp_border = value;
                    break;
            }

        });

        iclRenderLangPreview();

    }
}


function iclUpdateLangSelQuickPreviewFooter() {
    var name = jQuery(this).attr('name');
    var value = jQuery(this).val();
    switch (name) {
        case 'icl_lang_sel_footer_config[font-current-normal]':
            icl_lp_footer_font_current_normal = value;
            break;
        case 'icl_lang_sel_footer_config[font-current-hover]':
            icl_lp_footer_font_current_hover = value;
            break;
        case 'icl_lang_sel_footer_config[background-current-normal]':
            icl_lp_footer_background_current_normal = value;
            break;
        case 'icl_lang_sel_footer_config[background-current-hover]':
            icl_lp_footer_background_current_hover = value;
            break;
        case 'icl_lang_sel_footer_config[font-other-normal]':
            icl_lp_footer_font_other_normal = value;
            break;
        case 'icl_lang_sel_footer_config[font-other-hover]':
            icl_lp_footer_font_other_hover = value;
            break;
        case 'icl_lang_sel_footer_config[background-other-normal]':
            icl_lp_footer_background_other_normal = value;
            break;
        case 'icl_lang_sel_footer_config[background-other-hover]':
            icl_lp_footer_background_other_hover = value;
            break;
        case 'icl_lang_sel_footer_config[border]':
            icl_lp_footer_border = value;
            break;
        case 'icl_lso_footer_flags':
            icl_lp_footer_flag = jQuery(this).attr('checked');
            break;
        case 'icl_lang_sel_footer_config[background]':
            icl_lp_footer_background = value;
            break;
    }
    iclRenderLangPreviewFooter();
}

function iclRenderLangPreviewFooter() {


    var lang_sel_footer, footer_link;
    lang_sel_footer = jQuery('#lang_sel_footer');
    footer_link = lang_sel_footer.find('ul a');
    if (icl_lp_footer_font_other_normal) {
        footer_link.css('color', icl_lp_footer_font_other_normal);
    }
    if (icl_lp_footer_font_other_hover) {
        footer_link.unbind('hover');
        footer_link.hover(
            function () {
                jQuery(this).css('color', icl_lp_footer_font_other_hover);
            },
            function () {
                jQuery(this).css('color', icl_lp_footer_font_other_normal);
            }
        );
    }

    if (icl_lp_footer_background_other_normal) {
        footer_link.css('background-color', icl_lp_footer_background_other_normal);
        footer_link.unbind('hover');
        footer_link.hover(
            function () {
                jQuery(this).css('background-color', '');
            },
            function () {
                jQuery(this).css('background-color', icl_lp_footer_background_other_normal);
            }
        );
    }
    if (icl_lp_footer_background_other_hover) {
        footer_link.unbind('hover');
        footer_link.hover(
            function () {
                jQuery(this).css('background-color', icl_lp_footer_background_other_hover);
            },
            function () {
                jQuery(this).css('background-color', icl_lp_footer_background_other_normal);
            }
        );
    }

    if (icl_lp_footer_border) {
        lang_sel_footer.css('border-color', icl_lp_footer_border);
    }

    if (icl_lp_footer_background) {
        lang_sel_footer.css('background-color', icl_lp_footer_background);
    }

    if (jQuery('#icl_save_language_switcher_options').find(':checkbox[name="icl_lso_flags"]').attr('checked')) {
        lang_sel_footer.find('.iclflag').show();
    } else {
        lang_sel_footer.find('.iclflag').hide();
    }

    if (icl_lp_footer_font_current_normal) {
        lang_sel_footer.find('a:first').css('color', icl_lp_footer_font_current_normal);
    }

    if (icl_lp_footer_font_current_hover) {
        jQuery('a:first, a.lang_sel_sel', lang_sel_footer).unbind('hover');
        jQuery('a:first, a.lang_sel_sel', lang_sel_footer).hover(
            function () {
                jQuery(this).css('color', icl_lp_footer_font_current_hover);
            },
            function () {
                jQuery(this).css('color', icl_lp_footer_font_current_normal);
                jQuery('a.lang_sel_sel', lang_sel_footer).css('color', icl_lp_footer_font_current_normal);
            }
        );
    }

    if (icl_lp_footer_background_current_normal) {
        lang_sel_footer.find('a:first').css('background-color', icl_lp_footer_background_current_normal);

        lang_sel_footer.find('a:first').unbind('hover');
        lang_sel_footer.find('a:first').hover(
            function () {
                jQuery(this).css('background-color', '');
            },
            function () {
                jQuery(this).css('background-color', icl_lp_footer_background_current_normal);
            }
        );

    }

    if (icl_lp_footer_background_current_hover) {
        lang_sel_footer.find('a:first').unbind('hover');
        lang_sel_footer.find('a:first').hover(
            function () {
                jQuery(this).css('background-color', icl_lp_footer_background_current_hover);
            },
            function () {
                jQuery(this).css('background-color', icl_lp_footer_background_current_normal);
            }
        );
    }

}

function iclUpdateLangSelColorSchemeFooter() {
    var scheme = jQuery(this).val();
    if (scheme && confirm(jQuery(this).next().html())) {
        jQuery('#icl_lang_preview_config_footer').find('input[type="text"]').each(function () {
            var this_n = jQuery(this).attr('name').replace('icl_lang_sel_footer_config[', '').replace(']', '');
            var value = jQuery('#icl_lang_sel_footer_config_alt_' + scheme + '_' + this_n).val();
            jQuery(this).val(value);

            switch (jQuery(this).attr('name')) {
                case 'icl_lang_sel_footer_config[font-current-normal]':
                    icl_lp_footer_font_current_normal = value;
                    break;
                case 'icl_lang_sel_footer_config[font-current-hover]':
                    icl_lp_footer_font_current_hover = value;
                    break;
                case 'icl_lang_sel_footer_config[background-current-normal]':
                    icl_lp_footer_background_current_normal = value;
                    break;
                case 'icl_lang_sel_footer_config[background-current-hover]':
                    icl_lp_footer_background_current_hover = value;
                    break;
                case 'icl_lang_sel_footer_config[font-other-normal]':
                    icl_lp_footer_font_other_normal = value;
                    break;
                case 'icl_lang_sel_footer_config[font-other-hover]':
                    icl_lp_footer_font_other_hover = value;
                    break;
                case 'icl_lang_sel_footer_config[background-other-normal]':
                    icl_lp_footer_background_other_normal = value;
                    break;
                case 'icl_lang_sel_footer_config[background-other-hover]':
                    icl_lp_footer_background_other_hover = value;
                    break;
                case 'icl_lang_sel_footer_config[border]':
                    icl_lp_footer_border = value;
                    break;
                case 'icl_lang_sel_footer_config[background]':
                    icl_lp_footer_background = value;
                    break;
            }

        });

        iclRenderLangPreviewFooter();

    }
}

function iclHideLanguagesCallback() {
    iclSaveForm_success_cb.push(function (frm, res) {
        jQuery('#icl_hidden_languages_status').html(res[1]);
    });
}

function icl_reset_languages() {
    var this_b = jQuery(this);
    if (confirm(this_b.next().html())) {
        this_b.attr('disabled', 'disabled').next().html(icl_ajxloaderimg).fadeIn();
        jQuery.ajax({
            type: "POST",
            url: icl_ajx_url,
            data: "icl_ajx_action=reset_languages&_icl_nonce=" + jQuery('#_icl_nonce_rl').val(),
            success: function () {
                location.href = location.pathname + location.search;
            }
        });


    }
}

function iclEnableContentTranslation() {
    var val = jQuery(':radio[name=icl_translation_option]:checked').val();
    jQuery(this).attr('disabled', 'disabled');
    jQuery.ajax({
        type: "POST",
        url: icl_ajx_url,
        data: "icl_ajx_action=toggle_content_translation&wizard=1&new_val=" + val,
        success: function (msg) {
            var spl = msg.split('|');
            if (spl[1]) {
                location.href = spl[1];
            } else {
                location.href = location.href.replace(/#[\w\W]*/, '');
            }
        }
    });
    return false;
}

function installer_registration_form_submit(){
    
    var thisf = jQuery(this);
    
    
    var action = jQuery('#installer_registration_form input[name=button_action]').val();
    
    thisf.find('.status_msg').html('');
    
    thisf.find(':submit').attr('disabled', 'disabled');
    jQuery('<span class="spinner"></span>').css({display: 'inline-block', float: 'none'}).prependTo(thisf.find(':submit:first').parent());        

    if(action == 'later'){
        thisf.find('input[name=installer_site_key]').parent().remove();            
    }

    jQuery.ajax({
        type: "POST",
        url: icl_ajx_url,
        dataType: 'json',
        data: "icl_ajx_action=registration_form_submit&" + thisf.serialize(),
        success: function (msg) {
            
            if(action == 'register' || action == 'later'){
                
                thisf.find('.spinner').remove();    

                if(msg.error){
                    thisf.find('.status_msg').html(msg.error).addClass('icl_error_text');            
                    
                }else{
                    thisf.find('.status_msg').html(msg.success).addClass('icl_valid_text');                
                    
                    thisf.find(':submit:visible').hide();
                    thisf.find(':submit[name=finish]').show();
                }
                
                thisf.find(':submit').removeAttr('disabled', 'disabled');
                
            }else{ // action = finish
            
                location.href = location.href.replace(/#[\w\W]*/, '');                    
            }
            
        }
    });

    return false;
    
}


addLoadEvent(function () {
	var icl_lang_preview_config_footer, icl_lang_preview_config, icl_flag_visible, icl_hide_languages, icl_save_language_switcher_options;

	jQuery('.toggle:checkbox').click(iclHandleToggle);
	jQuery('#icl_change_default_button').click(editingDefaultLanguage);
	jQuery('#icl_save_default_button').click(saveDefaultLanguage);
	jQuery('#icl_cancel_default_button').click(doneEditingDefaultLanguage);
	jQuery('#icl_add_remove_button').click(showLanguagePicker);
	jQuery('#icl_cancel_language_selection').click(hideLanguagePicker);
	jQuery('#icl_save_language_selection').click(saveLanguageSelection);
	jQuery('#icl_enabled_languages').find('input').attr('disabled', 'disabled');
	jQuery('#icl_save_language_negotiation_type').submit(iclSaveLanguageNegotiationType);
	icl_save_language_switcher_options = jQuery('#icl_save_language_switcher_options');
	icl_save_language_switcher_options.submit(iclSaveForm);
	jQuery('#icl_admin_language_options').submit(iclSaveForm);
	jQuery('#icl_lang_more_options').submit(iclSaveForm);
	jQuery('#icl_blog_posts').submit(iclSaveForm);
	icl_hide_languages = jQuery('#icl_hide_languages');
	icl_hide_languages.submit(iclHideLanguagesCallback);
	icl_hide_languages.submit(iclSaveForm);
	jQuery('#icl_adjust_ids').submit(iclSaveForm);
	jQuery('#icl_automatic_redirect').submit(iclSaveForm);
	jQuery('input[name="icl_language_negotiation_type"]').change(iclLntDomains);
	jQuery('#icl_use_directory').change(iclUseDirectoryToggle);

	jQuery('input[name="show_on_root"]').change(iclToggleShowOnRoot);
	jQuery('#wpml_show_page_on_root_details').find('a').click(function () {
		if (!jQuery('#wpml_show_on_root_page').hasClass('active')) {
			alert(jQuery('#wpml_show_page_on_root_x').html());
			return false;
		}
	});

	jQuery('#icl_seo_options').submit(iclSaveForm);

	jQuery('#icl_setup_back_1').click(iclSetupStep1);
	jQuery('#icl_setup_back_2').click(iclSetupStep2);
	jQuery('#icl_setup_next_1').click(saveLanguageSelection);

	jQuery('#icl_avail_languages_picker').find('li input:checkbox').click(function () {
		if (jQuery('#icl_avail_languages_picker').find('li input:checkbox:checked').length > 1) {
			jQuery('#icl_setup_next_1').removeAttr('disabled');
		} else {
			jQuery('#icl_setup_next_1').attr('disabled', 'disabled');
		}
	});

	icl_flag_visible = jQuery('.iclflag:visible');
	icl_lp_flag = icl_flag_visible.length > 0;
	icl_lp_footer_flag = icl_flag_visible.length > 0;

	icl_lang_preview_config = jQuery('#icl_lang_preview_config');
	icl_lang_preview_config.find('input').each(iclUpdateLangSelQuickPreview);
	icl_lang_preview_config_footer = jQuery('#icl_lang_preview_config_footer');
	icl_lang_preview_config_footer.find('input').each(iclUpdateLangSelQuickPreviewFooter);
	// Picker align
	jQuery(".pick-show").click(function () {
		var set = jQuery(this).offset();
		jQuery("#colorPickerDiv").css({"top": set.top + 25, "left": set.left});
	});

	jQuery('#icl_promote_form').submit(iclSaveForm);

	icl_lang_preview_config.find('input').keyup(iclUpdateLangSelQuickPreview);
	icl_lang_preview_config_footer.find('input').keyup(iclUpdateLangSelQuickPreviewFooter);

	icl_save_language_switcher_options.find(':checkbox[name="icl_lso_flags"]').change(function () {
		if (jQuery(this).prop('checked')) {
			jQuery('#lang_sel').find('.iclflag').show();
			jQuery('#lang_sel_list').find('.iclflag').show();
			jQuery('#lang_sel_footer').find('.iclflag').show();
		} else {
			if (!jQuery('#icl_save_language_switcher_options').find(':checkbox:checked.icl_ls_include').length) {
				jQuery(this).prop('checked', true);
				return false;
			}

			jQuery('#lang_sel').find('.iclflag').hide();
			jQuery('#lang_sel_list').find('.iclflag').hide();
			jQuery('#lang_sel_footer').find('.iclflag').hide();
		}
	});


	icl_save_language_switcher_options.find(':checkbox[name="icl_lso_native_lang"]').change(function () {
		if (jQuery(this).attr('checked')) {
			jQuery('.icl_lang_sel_native').show();
			jQuery('.icl_lang_sel_current').show();
		} else {
			if (!icl_save_language_switcher_options.find(':checkbox:checked.icl_ls_include').length) {
				jQuery(this).attr('checked', true);
				return false;
			}

			jQuery('.icl_lang_sel_native').hide();
			if (!icl_save_language_switcher_options.find(':checkbox[name="icl_lso_display_lang"]').attr('checked')) {
				jQuery('.icl_lang_sel_current').hide();
			}
		}
	});

	icl_save_language_switcher_options.find(':checkbox[name="icl_lso_display_lang"]').change(function () {
		if (jQuery(this).attr('checked')) {
			jQuery('.icl_lang_sel_translated').show();
			jQuery('.icl_lang_sel_current').show();
		} else {

			if (!icl_save_language_switcher_options.find(':checkbox:checked.icl_ls_include').length) {
				jQuery(this).attr('checked', 'checked');
				return false;
			}

			jQuery('.icl_lang_sel_translated').hide();
			if (!icl_save_language_switcher_options.find(':checkbox[name="icl_lso_native_lang"]').attr('checked')) {
				jQuery('.icl_lang_sel_current').hide();
			}

		}
	});

	jQuery('#icl_lang_sel_color_scheme').change(iclUpdateLangSelColorScheme);
	jQuery('#icl_lang_sel_footer_color_scheme').change(iclUpdateLangSelColorSchemeFooter);

	var icl_arrow_img = icl_ajxloaderimg_src.replace("ajax-loader.gif", "nav-arrow-down.png");
	icl_save_language_switcher_options.find(':radio[name="icl_lang_sel_type"]').change(function () {
		if (jQuery(this).val() === 'dropdown') {
			jQuery('#lang_sel_list').hide();
			jQuery('#lang_sel').show();
		} else {
			jQuery('#lang_sel').hide();
			jQuery('#lang_sel_list').show();
		}
	});

	jQuery('#icl_reset_languages').click(icl_reset_languages);

	jQuery(':radio[name=icl_translation_option]').change(function () {
		jQuery('#icl_enable_content_translation').removeAttr('disabled');
	});
	jQuery('#icl_enable_content_translation, .icl_noenable_content_translation').click(iclEnableContentTranslation);

	jQuery('#icl_display_ls_in_menu').change(function () {
		if (jQuery(this).attr('checked')) {
			jQuery('#icl_ls_menus_list').show();
		}
		else {
			jQuery('#icl_ls_menus_list').hide();
		}
	});

	jQuery('input[name=icl_lang_sel_type]').change(function () {
		if (jQuery(this).val() === 'dropdown') {
			jQuery('select[name=icl_lang_sel_stype]').fadeIn();
			jQuery('select[name=icl_lang_sel_orientation]').hide();
		} else {
			jQuery('select[name=icl_lang_sel_stype]').hide();
			jQuery('select[name=icl_lang_sel_orientation]').fadeIn();
		}
	});

	jQuery('select[name=icl_lang_sel_orientation]').change(function () {
		var lang_sel_list = jQuery('#lang_sel_list');
		lang_sel_list.removeClass('lang_sel_list_horizontal').removeClass('lang_sel_list_vertical');
		lang_sel_list.addClass('lang_sel_list_' + jQuery(this).val());
	});

	jQuery('#icl_languages_order').sortable({
		update: function () {
			jQuery('.icl_languages_order_ajx_resp').html(icl_ajxloaderimg).fadeIn();
			var languages_order = [];
			jQuery('#icl_languages_order').find('li').each(function () {
				languages_order.push(jQuery(this).attr('class').replace(/icl_languages_order_/, ''));
			});
			jQuery.ajax({
				type: "POST",
				url: icl_ajx_url,
				dataType: 'json',
				data: 'icl_ajx_action=set_languages_order&_icl_nonce=' + jQuery('#icl_languages_order_nonce').val() + '&order=' + languages_order.join(';'),
				success: function (resp) {
					fadeInAjxResp('.icl_languages_order_ajx_resp', resp.message);
				}
			});
		}
	});
    
    
    jQuery(document).on('submit', '#installer_registration_form', installer_registration_form_submit)
    jQuery(document).on('click', '#installer_registration_form :submit', function(){
        jQuery('#installer_registration_form input[name=button_action]').val(jQuery(this).attr('name'));
        
    })
    
});
