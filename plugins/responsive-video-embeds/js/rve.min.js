/**
 * Responsive Video Embeds
 *
 * Create and maintained by Kevin Leary, www.kevinleary.net, WordPress development in Boston, MA
 */(function(e){var t={config:{container:e(".rve"),selector:"object, embed, iframe"},init:function(n){t.config.container.length>0&&e(window).on("resize load",t.resize)},resize:function(){e(t.config.selector,t.config.container).each(function(){var t=e(this),n=t.parent().width(),r=Math.round(n*.5625);t.attr("height",r);t.attr("width",n)})}};t.init()})(jQuery);
