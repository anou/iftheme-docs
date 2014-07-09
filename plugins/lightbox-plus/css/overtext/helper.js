jQuery(document).ready(function($){
    // Only run if there is a title.
    if ($('#cboxTitle:empty').length == false) {
        setTimeout(function () { $('#cboxTitle').slideUp() }, 1500);
        $('#cboxLoadedContent img').bind('mouseover', function () {
            $('#cboxTitle').slideDown();
        });
        $('#cboxOverlay').bind('mouseover', function () {
            $('#cboxTitle').slideUp();
        });
    }
    else {
        $('#cboxTitle').hide();
    }
});