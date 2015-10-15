jQuery(document).ready(function($){
    $(document).bind('cbox_open', function () {
        // Hide close button initially.
        $('#cboxClose').css('opacity', 0);
    });
    $(document).bind('cbox_complete', function () {
        // Show close button with a delay.
        $('#cboxClose').show('fast', 0, function () {$(this).css('opacity', 1)});
    });
});