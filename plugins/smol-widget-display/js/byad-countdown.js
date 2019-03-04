function BYADCountDown() {
    var today = new Date(); // Today's date
    var endDate = new Date(jbydCD_Data.endDate*1000); // Get End date from settings
    var testToday = new Date(jbydCD_Data.today*1000); // test.
    var displayYears = jbydCD_Data.display_years; // Get setting for displaying Years
    
    var timeLeft = endDate.getTime() - today.getTime(); // Time left in milliseconds

    //============ CONVERSIONS ========================
    var sLeft = timeLeft / 1000; //Convert in seconds
    var mLeft = sLeft / 60; // in minutes
    var hLeft = mLeft / 60; // in hours
    var dLeft = hLeft / 24; // in days
    var yLeft = dLeft / 365; // in years
        sLeft = ('0' + Math.floor(sLeft % 60)).slice(-2); //Seconds left
        mLeft = ('0' + Math.floor(mLeft % 60)).slice(-2); //Minutes left
        hLeft = ('0' + Math.floor(hLeft % 24)).slice(-2); //Hours left
        dLeft = ('0' + Math.floor(dLeft)).slice(-2); //Days left
        yLeft = ('0' + Math.floor(yLeft)).slice(-2); //Years left
    //===================================================================
   
    // prepare output
    var openSpan = '<span>', closeSpan = '</span>', openLi = '<li>', closeLi = '</li>';
   
    // output
    var textCountDown = '<ul>';
    //years (if checked)
    if ( displayYears == 1 ) textCountDown += openLi + yLeft + ' ' + openSpan + jbydCD_Data.year + closeSpan + closeLi;
    //days
    textCountDown += openLi + dLeft + ' ' + openSpan + jbydCD_Data.day + closeSpan + closeLi;
    //hours
    textCountDown += openLi + hLeft + ' ' + openSpan + jbydCD_Data.hour + closeSpan + closeLi;
    //minutes
    textCountDown += openLi + mLeft + ' ' + openSpan + jbydCD_Data.min + closeSpan + closeLi;
    //secondes
    textCountDown += openLi + sLeft + ' ' + openSpan + jbydCD_Data.sec + closeSpan + closeLi;
    
    textCountDown += '</ul>'
  
    var CountDown = document.getElementsByClassName(jbydCD_Data.byadClass);

//timeLeft = 0;//uncomment to test the end of countdown. Have a taste of what death looks like ;-)
    
    //if no more time, do something else
    if (timeLeft <= 0) {
      for (var i = 0; i < CountDown.length; i++) {
        var rootContext = CountDown[i].getAttribute("data-root");
      }     
      
      //you can remove this in CSS. TODO: make plugin themeable via css in user's theme
      var sKull = '<img src="' + rootContext + jbydCD_Data.byadImg + '" alt="' + jbydCD_Data.byadAlt + '" />';
      
      // display text from settings
      textCountDown = openSpan + jbydCD_Data.deadend + sKull + closeSpan;
      
      //stop interval
      clearInterval(timer);
    }
    //you can have multiple countdown on the same page. For now they just count the same time :-)
    for (var i = 0; i < CountDown.length; i++) {
      CountDown[i].innerHTML = textCountDown;
    }
} // End BYADCountDown() function

var timer = setInterval(BYADCountDown, 1000); // Callback the function every 1000 milliseconds (every second what!)
//to stop countdown for dev purpose, you can comment above (var timer) and uncomment the line below this one:
//BYADCountDown();