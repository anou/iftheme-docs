(function($) {
    $.fn.matchColumn = function(options) {
	var settings = $.extend({
	    showAlert: false,
	    alertMessage: '',
	    showConfirmation: false,
	    confirmationMessage:'',
	    defaultValue: 'nomatch',
	}, options);

	var previousValue;
	
	/**
	 * List of dom elements which are in scope
	 */
	var objects = new Array();
	
	this.each(function() {
	    objects.push(this);
	});

	/**
	 * reset Columns which were matched previously with a same value
	 * @param {dom} currentObject current dom
	 * @param {boolean} onInit is invoked on load?
	 */
	var resetColumns = function(currentObject, onInit) {
	    if (typeof onInit === undefined) {
		onInit = false;
	    }
	    isMatchedColumn = false;
	    for (i = 0; i < objects.length; i++) {
		if (
			!isMatchedColumn 
			&& $(currentObject).val() !== settings.defaultValue
			&& !$(objects[i]).is(currentObject) 
			&& $(objects[i]).val() === $(currentObject).val()
	    ) {
		    isMatchedColumn = true;
		}
	    }
	    if (isMatchedColumn) {
		if (
			!onInit
			&& settings.showConfirmation 
			&& typeof settings.confirmationMessage !== undefined 
			&& settings.confirmationMessage !== '' 
			&& settings.defaultValue !== undefined) {
			    yes = confirm(settings.confirmationMessage);
			    if (!yes) {
				$(currentObject).val($(currentObject).data('lastValue'));
				return;
			    }
		}
		else if (
			!onInit
			&& settings.showAlert 
			&& typeof settings.alertMessage !== undefined 
			&& settings.alertMessage !== '' 
			&& settings.defaultValue !== undefined) {
			    alert(settings.alertMessage);
		}
		for (i = 0; i < objects.length; i++) {
		    if (
			    !$(objects[i]).is(currentObject) 
			    && $(currentObject).val() !== settings.defaultValue
			    && $(objects[i]).val() === $(currentObject).val()
		) {
			$(objects[i]).val(settings.defaultValue).data('lastValue',settings.defaultValue);
		    }
		}
	    }
	};
	
	this.each(function() {
	    previousValue = $(this).val();
	    resetColumns(this, true); 
	    $(this).data('lastValue',$(this).val());
	});
	
	$(this)
		.change(function() {
		    resetColumns(this);
		    $(this).data('lastValue',$(this).val());
		});
	return this;
    };
}(jQuery));
