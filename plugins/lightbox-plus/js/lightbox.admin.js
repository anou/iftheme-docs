jQuery(document).ready(function($){
    if (!$('#use_inline').attr('checked')) { $('.base_gen').hide(); }
    if (!$('#use_perpage').attr('checked')) { $('.base_blog').hide(); }
    if ($("#output_htmlv").attr('checked')) { $('.htmlv_settings').show(); }
    if ($('#rel').attr('checked')) { $('.grouping_prim').hide(); }
    if ($("#use_class_method").attr('checked')) { $('.primary_class_name').show(); }
    if (!$('#slideshow').attr('checked')) { $('.slideshow_prim').hide(); }
    if ($('#rel_sec').attr('checked')) { $('.grouping_sec').hide(); }
    if (!$('#slideshow_sec').attr('checked')) { $('.slideshow_sec').hide(); }

    $('.close-me').each(function() {$(this).addClass("closed");});
    $('#lbp_message').each(function() {$(this).fadeOut(5000);});
    $('.postbox h3').click( function() {$(this).next('.toggle').slideToggle('fast');});
    $('.lbp-info').click( function() {$(this).next('.lbp-bigtip').slideToggle(100);});
    $("#blbp-tabs").tabs({ fx: { height: 'toggle', duration: 'fast' } });
    $("#plbp-tabs").tabs({ fx: { height: 'toggle', duration: 'fast' } });
    $("#slbp-tabs").tabs({ fx: { height: 'toggle', duration: 'fast' } });
    $("#ilbp-tabs").tabs({ fx: { height: 'toggle', duration: 'fast' } });

    $("#use_inline").click(function(){ if ($("#use_inline").attr("checked")) { $(".base_gen").show("fast"); } else { $(".base_gen").hide("fast"); } });
    $("#output_htmlv").click(function(){ if ($("#output_htmlv").attr('checked')) { $(".htmlv_settings").show("fast"); } else { $(".htmlv_settings").hide("fast"); } });
    $("#lbp_setting_detail").click(function(){ $('#lbp_detail').toggle('fast') });
    $("#use_perpage").click(function(){ if ($("#use_perpage").attr('checked')) { $(".base_blog").show("fast"); } else { $(".base_blog").hide("fast"); } });
    $("#rel").click(function(){  if ($("#rel").attr('checked')) { $(".grouping_prim").hide("fast"); } else { $(".grouping_prim").show("fast"); } });
    $("#use_class_method").click(function(){ if ($("#use_class_method").attr("checked")) { $(".primary_class_name").show("fast"); } else { $(".primary_class_name").hide("fast"); } });
    $("#slideshow").click(function(){ if ($("#slideshow").attr('checked')) { $(".slideshow_prim").show("fast"); } else { $(".slideshow_prim").hide("fast"); } });
    $("#rel_sec").click(function(){ if ($("#rel_sec").attr('checked')) { $(".grouping_sec").hide("fast"); } else { $(".grouping_sec").show("fast"); } });
    $("#slideshow_sec").click(function(){ if ($("#slideshow_sec").attr('checked')) { $(".slideshow_sec").show("fast"); } else { $(".slideshow_sec").hide("fast"); } });

    $("#lightboxplus_style").change(function () {
        var style = $(this).attr('value')
        $('#lbp-style-screenshot').find(".lbp-sample-current").hide(0).removeClass('lbp-sample-current').addClass('lbp-sample');
        $('#lbp-style-screenshot').find("#lbp-sample-"+style).show(0).addClass('lbp-sample-current').removeClass('lbp-sample');
    });
});