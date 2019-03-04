(function ($) {
  $(document).ready(function () {
    
   	$.datepicker.setDefaults( $.datepicker.regional[''] );
	  
	  $('.byadcd-datepicker').datepicker(
	    $.extend({ 
        showOn: 'button', 
        buttonImage: dateJs_Data.byadIcon, 
        buttonImageOnly: true

      }, 
      $.datepicker.regional[ dateJs_Data.byadRegional ])
    );
    
  });//end document.ready
})(jQuery);